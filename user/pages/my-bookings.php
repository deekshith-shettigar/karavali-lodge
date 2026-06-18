<?php
$pageTitle = "My Bookings - Karavali Lodge";
require_once __DIR__ . '/../includes/config.php';

if (!isLoggedIn()) redirect(SITE_URL . '/pages/login.php');

$guest = getLoggedInGuest();

// Guard: if session exists but guest record is missing, clear and redirect to login
if ($guest === null) {
    session_destroy();
    redirect(SITE_URL . '/pages/login.php?msg=session_expired');
}

$db = getDB();

// ── CSRF token — generated once per session ──────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// ── Handle cancellation — POST only + CSRF verified ──────────────
// Changed from GET (?cancel=ID) to POST to prevent CSRF attacks.
// A malicious site could silently cancel bookings via an <img> tag
// using a GET link — POST with a token makes that impossible.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel'])) {
    // Verify CSRF token first
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        redirect(SITE_URL . '/pages/my-bookings.php?msg=invalid_request');
    }

    $reqId = sanitize($_POST['cancel']);

    // Fetch all needed columns in one query — no second lookup required
    $chk = $db->prepare("SELECT id, status, created_at, room_id, check_in FROM online_booking_requests WHERE id=? AND mobile=?");
    $chk->execute([$reqId, $guest['mobile']]);
    $bk = $chk->fetch();

    $canCancel = false;
    if ($bk) {
        // Block if guest has already checked in OR checked out for this specific room+date
        if (!empty($bk['room_id'])) {
            // Use check_in from the first query result — no second DB roundtrip needed
            $checkInDate = !empty($bk['check_in']) ? date('Y-m-d', strtotime($bk['check_in'])) : null;

            if ($checkInDate) {
                $ciChk = $db->prepare("SELECT id FROM checkins WHERE mobile=? AND room_id=? AND check_in_date=? AND status IN ('Checked-In','Checked-Out')");
                $ciChk->execute([$guest['mobile'], $bk['room_id'], $checkInDate]);
                if ($ciChk->fetch()) {
                    redirect(SITE_URL . '/pages/my-bookings.php?msg=already_checkedin');
                }
            }
        }

        if ($bk['status'] === 'Pending') {
            $canCancel = true;
        } elseif ($bk['status'] === 'Confirmed') {
            // Allow cancel only within 24 hours of booking creation
            $bookedAt  = new DateTime($bk['created_at'], new DateTimeZone('Asia/Kolkata'));
            $now       = new DateTime('now',              new DateTimeZone('Asia/Kolkata'));
            $diffHours = ($now->getTimestamp() - $bookedAt->getTimestamp()) / 3600;
            if ($diffHours <= 24) $canCancel = true;
        }
    }

    if ($canCancel) {
        // Cancel the online request
        $db->prepare("UPDATE online_booking_requests SET status='Cancelled', updated_at=NOW() WHERE id=?")
           ->execute([$reqId]);

        // Also cancel any linked booking in bookings table and free the room
        $linked = $db->prepare("SELECT id, room_id FROM bookings WHERE booking_no IN (
            SELECT request_no FROM online_booking_requests WHERE id=?
        ) OR (guest_name=(SELECT guest_name FROM online_booking_requests WHERE id=?)
              AND check_in=(SELECT check_in FROM online_booking_requests WHERE id=?)
              AND mobile=?)");
        $linked->execute([$reqId, $reqId, $reqId, $guest['mobile']]);
        $linkedBooking = $linked->fetch();
        if ($linkedBooking) {
            $db->prepare("UPDATE bookings SET status='Cancelled', updated_at=NOW() WHERE id=?")
               ->execute([$linkedBooking['id']]);
            if ($linkedBooking['room_id']) {
                $db->prepare("UPDATE rooms SET status='Available', updated_at=NOW() WHERE id=? AND status IN ('Reserved','Occupied','Cleaning')")
                   ->execute([$linkedBooking['room_id']]);
            }
        }
        redirect(SITE_URL . '/pages/my-bookings.php?msg=cancelled');
    } else {
        redirect(SITE_URL . '/pages/my-bookings.php?msg=cancel_expired');
    }
}

$msg = sanitize($_GET['msg'] ?? '');
// Map msg codes to user-facing text
$msgTexts = [
    'cancelled'       => ['success', 'Your booking has been cancelled successfully.'],
    'cancel_expired'  => ['warning', 'This booking can no longer be cancelled (outside the 24-hour window or already processed).'],
    'already_checkedin' => ['info',  'Cannot cancel — you have already checked in for this booking.'],
    'invalid_request' => ['danger',  'Invalid request. Please try again.'],
];

// Get this guest's booking requests
$stmt = $db->prepare("
    SELECT r.*, rm.room_number, rm.room_type, rm.price
    FROM online_booking_requests r
    LEFT JOIN rooms rm ON rm.id = r.room_id
    WHERE r.mobile = ?
    ORDER BY r.created_at DESC
");
$stmt->execute([$guest['mobile']]);
$bookings = $stmt->fetchAll();

// Get actual stay history from checkins
$stmt2 = $db->prepare("
    SELECT c.*, rm.room_number, rm.room_type
    FROM checkins c
    LEFT JOIN rooms rm ON rm.id = c.room_id
    WHERE c.mobile = ?
    ORDER BY c.created_at DESC
");
$stmt2->execute([$guest['mobile']]);
$checkins = $stmt2->fetchAll();

// Build a map keyed by "room_id|check_in_date" so only the exact booking's stay blocks cancel
// Normalize date to Y-m-d to ensure format consistency
$activeCheckinRooms = []; // key: "roomId|YYYY-MM-DD" => status
foreach ($checkins as $c) {
    if (in_array($c['status'], ['Checked-In', 'Checked-Out']) && $c['room_id'] && $c['check_in_date']) {
        $normalizedDate = date('Y-m-d', strtotime($c['check_in_date']));
        $key = $c['room_id'] . '|' . $normalizedDate;
        $activeCheckinRooms[$key] = $c['status'];
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Page Hero -->
<div class="page-hero">
    <div class="container page-hero-content">
        <div class="breadcrumb-hotel mb-3">
            <a href="<?= SITE_URL ?>/index.php">Home</a>
            <span class="sep">›</span>
            <span class="current">My Bookings</span>
        </div>
        <h1>My Bookings</h1>
        <p>Welcome back, <?= sanitize($guest['name']) ?></p>
    </div>
</div>

<section class="section-pad" style="background:var(--cream)">
    <div class="container">

        <?php if ($msg === 'cancelled'): ?>
        <div class="alert-hotel alert-info-hotel mb-4" data-auto-dismiss="5000">
            <i class="bi bi-check-circle-fill"></i>
            Booking cancelled successfully. The room has been released.
        </div>
        <?php elseif ($msg === 'cancel_expired'): ?>
        <div class="alert-hotel alert-error-hotel mb-4" data-auto-dismiss="6000">
            <i class="bi bi-exclamation-circle-fill"></i>
            Cancellation window has expired. Confirmed bookings can only be cancelled within 24 hours of booking.
        </div>
        <?php elseif ($msg === 'already_checkedin'): ?>
        <div class="alert-hotel alert-error-hotel mb-4" data-auto-dismiss="6000">
            <i class="bi bi-exclamation-circle-fill"></i>
            You have already checked into this room. Cancellation is not possible after check-in.
        </div>
        <?php endif; ?>

        <!-- Guest Info Card -->
        <div style="background:var(--white);border-radius:var(--radius-lg);padding:24px 28px;border:1px solid var(--border);box-shadow:var(--shadow-sm);margin-bottom:36px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px">
            <div style="display:flex;align-items:center;gap:16px">
                <div style="width:56px;height:56px;background:linear-gradient(135deg,var(--primary),var(--primary-light));border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--accent-light);font-size:1.4rem;font-family:'Playfair Display',serif;font-weight:700;flex-shrink:0">
                    <?= strtoupper(substr($guest['name'], 0, 1)) ?>
                </div>
                <div>
                    <div style="font-family:'Playfair Display',serif;font-size:1.2rem;color:var(--primary);font-weight:600"><?= sanitize($guest['name']) ?></div>
                    <div style="font-size:0.85rem;color:var(--text-muted)">
                        <i class="bi bi-telephone me-1"></i><?= sanitize($guest['mobile']) ?>
                        <?= $guest['email'] ? '<span class="mx-2">·</span><i class="bi bi-envelope me-1"></i>'.sanitize($guest['email']) : '' ?>
                    </div>
                </div>
            </div>
            <div style="display:flex;gap-12px;gap:12px">
                <a href="<?= SITE_URL ?>/pages/rooms.php" class="btn-accent-hotel" style="padding:10px 22px;font-size:0.88rem;border-radius:7px">
                    <i class="bi bi-calendar-plus me-2"></i>New Booking
                </a>
                <a href="<?= SITE_URL ?>/pages/logout.php" class="btn-outline-hotel" style="padding:9px 20px;font-size:0.85rem;border-radius:7px">
                    <i class="bi bi-box-arrow-right me-2"></i>Logout
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="row g-3 mb-5">
            <div class="col-md-4">
                <div style="background:var(--white);border-radius:var(--radius);padding:20px 24px;border:1px solid var(--border);border-left:4px solid var(--accent);box-shadow:var(--shadow-sm)">
                    <div style="font-family:'Playfair Display',serif;font-size:2rem;color:var(--primary);font-weight:700"><?= count($bookings) ?></div>
                    <div style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px">Total Requests</div>
                </div>
            </div>
            <div class="col-md-4">
                <div style="background:var(--white);border-radius:var(--radius);padding:20px 24px;border:1px solid var(--border);border-left:4px solid #28a745;box-shadow:var(--shadow-sm)">
                    <div style="font-family:'Playfair Display',serif;font-size:2rem;color:var(--primary);font-weight:700">
                        <?= count(array_filter($bookings, fn($b) => $b['status'] === 'Confirmed')) ?>
                    </div>
                    <div style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px">Confirmed</div>
                </div>
            </div>
            <div class="col-md-4">
                <div style="background:var(--white);border-radius:var(--radius);padding:20px 24px;border:1px solid var(--border);border-left:4px solid var(--primary);box-shadow:var(--shadow-sm)">
                    <div style="font-family:'Playfair Display',serif;font-size:2rem;color:var(--primary);font-weight:700"><?= count($checkins) ?></div>
                    <div style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px">Completed Stays</div>
                </div>
            </div>
        </div>

        <!-- Booking Requests -->
        <h4 style="font-family:'Playfair Display',serif;color:var(--primary);margin-bottom:20px">
            <i class="bi bi-calendar-check me-2" style="color:var(--accent)"></i>Booking Requests
        </h4>

        <?php if (empty($bookings)): ?>
        <div class="empty-state-hotel mb-5">
            <i class="bi bi-calendar-x d-block"></i>
            <h5>No Booking Requests Yet</h5>
            <p>You haven't made any booking requests. Start your journey!</p>
            <a href="<?= SITE_URL ?>/pages/rooms.php" class="btn-primary-hotel">
                <i class="bi bi-calendar-plus me-2"></i>Book a Room
            </a>
        </div>
        <?php else: ?>
        <?php foreach ($bookings as $b):
            $statusClass = strtolower($b['status']);
            $nights = (int)ceil((strtotime($b['check_out']) - strtotime($b['check_in'])) / 86400);

            // Compute cancellation eligibility and remaining time
            $bookedAt      = new DateTime($b['created_at'], new DateTimeZone('Asia/Kolkata'));
            $cancelDeadline = clone $bookedAt;
            $cancelDeadline->modify('+24 hours');
            $nowDT          = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
            $withinWindow   = ($nowDT < $cancelDeadline);
            $secondsLeft    = max(0, $cancelDeadline->getTimestamp() - $nowDT->getTimestamp());

            $canCancelPending   = ($b['status'] === 'Pending');
            $canCancelConfirmed = ($b['status'] === 'Confirmed' && $withinWindow);

            // Block cancel if guest has already checked into this room
            // Block cancel only if THIS specific booking's room+date was checked in/out
            $normalizedCheckIn = !empty($b['check_in']) ? date('Y-m-d', strtotime($b['check_in'])) : '';
            $hkKey          = ($b['room_id'] ?? '') . '|' . $normalizedCheckIn;
            $alreadyCheckedIn = !empty($b['room_id']) && !empty($normalizedCheckIn) && isset($activeCheckinRooms[$hkKey]);
            if ($alreadyCheckedIn) {
                $canCancelPending   = false;
                $canCancelConfirmed = false;
            }

            $showCancelBtn      = $canCancelPending || $canCancelConfirmed;
        ?>
        <div class="booking-history-card">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div style="flex:1">
                    <div class="booking-no"><?= sanitize($b['request_no']) ?></div>
                    <div class="booking-room-name">
                        <?= $b['room_number'] ? 'Room ' . sanitize($b['room_number']) . ' — ' : '' ?>
                        <?= sanitize($b['room_type'] ?? 'Room') ?>
                    </div>
                    <div style="font-size:0.85rem;color:var(--text-muted);margin-top:6px">
                        <i class="bi bi-calendar-range me-1"></i>
                        <?= date('d M Y', strtotime($b['check_in'])) ?> &rarr; <?= date('d M Y', strtotime($b['check_out'])) ?>
                        &nbsp;·&nbsp;
                        <strong><?= $nights ?></strong> night<?= $nights !== 1 ? 's' : '' ?>
                        &nbsp;·&nbsp;
                        <?= (int)$b['num_adults'] ?> Adult<?= $b['num_adults'] > 1 ? 's' : '' ?>
                        <?= $b['num_children'] > 0 ? ', ' . $b['num_children'] . ' Child' . ($b['num_children'] > 1 ? 'ren' : '') : '' ?>
                    </div>
                    <?php
                        $displayReq = $b['special_requests'] ?? '';
                        $isPayHotel = str_starts_with($displayReq, '[Pay at Hotel]');
                        $cleanReq   = trim(str_replace('[Pay at Hotel]', '', $displayReq));
                    ?>
                    <?php if ($isPayHotel): ?>
                    <div style="font-size:0.75rem;margin-top:6px;">
                        <span style="background:#fff3cd;color:#856404;padding:3px 10px;border-radius:50px;font-weight:600;border:1px solid #ffc107;">
                            <i class="bi bi-building me-1"></i>Pay at Hotel
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php if ($cleanReq): ?>
                    <div style="font-size:0.8rem;color:var(--text-muted);margin-top:4px;font-style:italic">
                        <i class="bi bi-chat-text me-1"></i><?= sanitize($cleanReq) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div style="text-align:right">
                    <span class="status-badge status-<?= strtolower($b['status']) ?>" style="display:inline-block;margin-bottom:8px;white-space:nowrap;">
                        <?= sanitize($b['status']) ?>
                    </span>
                    <div style="font-family:'Playfair Display',serif;font-size:1.2rem;color:var(--accent);font-weight:700">
                        <?= formatCurrency($b['total_amount']) ?>
                    </div>
                    <div style="font-size:0.72rem;color:var(--text-muted)">Total incl. GST</div>
                    <?php if ($showCancelBtn): ?>
                    <div class="mt-2">
                        <?php if ($canCancelConfirmed): ?>
                        <div style="font-size:0.72rem;color:var(--text-muted);margin-bottom:4px;">
                            <i class="bi bi-clock me-1"></i>Cancel window closes in:
                            <strong class="cancel-countdown" data-seconds="<?= $secondsLeft ?>" style="color:#dc3545"></strong>
                        </div>
                        <?php endif; ?>
                        <form method="POST" action="" style="display:inline;"
                              onsubmit="return confirm('Are you sure you want to cancel this booking? This cannot be undone.')">
                            <input type="hidden" name="cancel"     value="<?= htmlspecialchars($b['id'], ENT_QUOTES) ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                            <button type="submit"
                                    style="font-size:0.82rem;color:#dc3545;background:transparent;
                                           display:inline-flex;align-items:center;gap:4px;
                                           padding:5px 12px;border:1.5px solid #dc3545;
                                           border-radius:6px;font-weight:600;cursor:pointer;
                                           transition:all 0.2s;"
                                    onmouseover="this.style.background='#dc3545';this.style.color='#fff'"
                                    onmouseout="this.style.background='transparent';this.style.color='#dc3545'">
                                <i class="bi bi-x-circle"></i>Cancel Booking
                            </button>
                        </form>
                    </div>
                    <?php elseif ($alreadyCheckedIn): ?>
                    <div class="mt-2" style="font-size:0.72rem;color:<?= ($activeCheckinRooms[$hkKey] ?? '') === 'Checked-Out' ? 'var(--text-muted)' : '#28a745' ?>;font-weight:600;">
                        <?php if (($activeCheckinRooms[$hkKey] ?? '') === 'Checked-Out'): ?>
                            <i class="bi bi-box-arrow-right me-1"></i>Already checked out
                        <?php else: ?>
                            <i class="bi bi-door-open me-1"></i>Currently checked in
                        <?php endif; ?>
                    </div>
                    <?php elseif ($b['status'] === 'Confirmed' && !$withinWindow): ?>
                    <div class="mt-2" style="font-size:0.72rem;color:var(--text-muted);">
                        <i class="bi bi-lock me-1"></i>Cancellation window expired
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div style="font-size:0.72rem;color:var(--text-muted);margin-top:12px;padding-top:10px;border-top:1px solid var(--border)">
                <i class="bi bi-clock me-1"></i>Requested: <?= date('d M Y, h:i A', strtotime($b['created_at'])) ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <!-- Stay History -->
        <?php if (!empty($checkins)): ?>
        <h4 style="font-family:'Playfair Display',serif;color:var(--primary);margin-bottom:20px;margin-top:40px">
            <i class="bi bi-clock-history me-2" style="color:var(--accent)"></i>Stay History
        </h4>
        <?php foreach ($checkins as $c):
            $nights = $c['check_out_date'] ? (int)ceil((strtotime($c['check_out_date']) - strtotime($c['check_in_date'])) / 86400) : 0;
        ?>
        <div class="booking-history-card" style="border-left:3px solid <?= $c['status'] === 'Checked-In' ? '#28a745' : 'var(--border)' ?>">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <div class="booking-room-name">
                        <?= $c['room_number'] ? 'Room ' . sanitize($c['room_number']) : 'Room' ?>
                        <?= $c['room_type'] ? ' — ' . sanitize($c['room_type']) : '' ?>
                    </div>
                    <div style="font-size:0.85rem;color:var(--text-muted);margin-top:6px">
                        <i class="bi bi-box-arrow-in-right me-1"></i>
                        Check-In: <?= date('d M Y', strtotime($c['check_in_date'])) ?>
                        <?php if ($c['check_out_date']): ?>
                        &nbsp;·&nbsp;
                        <i class="bi bi-box-arrow-right me-1"></i>
                        Check-Out: <?= date('d M Y', strtotime($c['check_out_date'])) ?>
                        &nbsp;·&nbsp;<?= $nights ?> night<?= $nights !== 1 ? 's' : '' ?>
                        <?php endif; ?>
                    </div>
                </div>
                <span class="status-badge status-<?= strtolower(str_replace('-', '', $c['status'])) ?>">
                    <?= sanitize($c['status']) ?>
                </span>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
// Live countdown timers for cancel windows
(function() {
    function formatTime(sec) {
        if (sec <= 0) return '00:00:00';
        var h = Math.floor(sec / 3600);
        var m = Math.floor((sec % 3600) / 60);
        var s = sec % 60;
        return [h, m, s].map(function(v) { return String(v).padStart(2, '0'); }).join(':');
    }

    var timers = document.querySelectorAll('.cancel-countdown');
    timers.forEach(function(el) {
        var seconds = parseInt(el.getAttribute('data-seconds'));
        el.textContent = formatTime(seconds);

        var interval = setInterval(function() {
            seconds--;
            if (seconds <= 0) {
                clearInterval(interval);
                // Hide the entire cancel block when window expires
                var cancelDiv = el.closest('.mt-2');
                if (cancelDiv) {
                    cancelDiv.innerHTML = '<span style="font-size:0.72rem;color:var(--text-muted);"><i class="bi bi-lock me-1"></i>Cancellation window expired</span>';
                }
                // Also hide the cancel button sibling
                var nextEl = cancelDiv ? cancelDiv.nextElementSibling : null;
                if (nextEl && nextEl.tagName === 'A') nextEl.style.display = 'none';
            } else {
                el.textContent = formatTime(seconds);
                // Turn red when under 1 hour
                if (seconds < 3600) el.style.color = '#dc3545';
            }
        }, 1000);
    });
})();
</script>