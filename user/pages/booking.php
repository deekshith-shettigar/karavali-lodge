<?php
$pageTitle = "Book Your Stay - Karavali Lodge";
require_once __DIR__ . '/../includes/config.php';

// ── PHP ID number format validation ──────────────────────────────
function validateIdNumber(string $type, string $number): ?string {
    $n = strtoupper(preg_replace('/[\s\-]/', '', trim($number)));
    $rules = [
        'Aadhaar'         => ['/^\d{12}$/',            'Aadhaar must be exactly 12 digits (e.g. 123456789012)'],
        'Passport'        => ['/^[A-Z]\d{7}$/',        'Passport: 1 letter + 7 digits (e.g. A1234567)'],
        'Driving License' => ['/^[A-Z]{2}\d{13}$/',    'Driving License: State code + 13 digits (e.g. KA0120210012345)'],
        'Voter ID'        => ['/^[A-Z]{3}\d{7}$/',     'Voter ID: 3 letters + 7 digits (e.g. ABC1234567)'],
        'PAN Card'        => ['/^[A-Z]{5}\d{4}[A-Z]$/','PAN Card: 5 letters + 4 digits + 1 letter (e.g. ABCDE1234F)'],
    ];
    if (!isset($rules[$type])) return null;
    [$pattern, $message] = $rules[$type];
    return preg_match($pattern, $n) ? null : $message;
}

// ── Razorpay safe fallbacks ──
if (!defined('RAZORPAY_KEY_ID'))          define('RAZORPAY_KEY_ID',          '');
if (!defined('RAZORPAY_KEY_SECRET'))      define('RAZORPAY_KEY_SECRET',       '');
if (!defined('RAZORPAY_CURRENCY'))        define('RAZORPAY_CURRENCY',         'INR');
if (!defined('RAZORPAY_ADVANCE_PERCENT')) define('RAZORPAY_ADVANCE_PERCENT',  1.00);

$db          = getDB();
$loggedGuest = getLoggedInGuest();
$isLoggedIn  = isLoggedIn();

// ── Razorpay keys check ──
$razorpayReady = (RAZORPAY_KEY_ID !== '' && RAZORPAY_KEY_ID !== 'rzp_test_XXXXXXXXXXXXXXXXXX');

// ── CSRF token — generate once per session ───────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Handle form submission
$success   = false;
$errors    = [];
$requestNo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ── CSRF check — must be the very first thing in POST handling ──
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        http_response_code(403);
        die('Invalid request. Please reload the page and try again.');
    }
    $guestName    = sanitize($_POST['guest_name']        ?? '');
    $mobile       = sanitize($_POST['mobile']             ?? '');
    $email        = sanitize($_POST['email']              ?? '');
    $roomId       = sanitize($_POST['room_id']            ?? '');
    $checkIn      = sanitize($_POST['check_in']           ?? '');
    $checkOut     = sanitize($_POST['check_out']          ?? '');
    $numAdults    = (int)($_POST['num_adults']            ?? 1);
    $numChildren  = (int)($_POST['num_children']          ?? 0);
    $specialReq   = sanitize($_POST['special_requests']   ?? '');
    $checkinTime  = sanitize($_POST['checkin_time']        ?? '');
    $checkoutTime = sanitize($_POST['checkout_time']       ?? '');
    $idProofType  = sanitize($_POST['id_proof_type']       ?? '');
    $idProofNo    = sanitize($_POST['id_proof_number']     ?? '');

    // ── Razorpay payment fields ──
    $razorpayPaymentId = sanitize($_POST['razorpay_payment_id'] ?? '');
    $razorpayOrderId   = sanitize($_POST['razorpay_order_id']   ?? '');
    $razorpaySignature = sanitize($_POST['razorpay_signature']  ?? '');
    $paymentMode       = sanitize($_POST['payment_mode']        ?? 'hotel');
    $paymentVerified   = false;

    // Validation
    if (!$guestName) $errors[] = 'Guest name is required.';
    if (!$mobile)    $errors[] = 'Mobile number is required.';
    if (!$roomId)    $errors[] = 'Please select a room.';
    if (!$checkIn)   $errors[] = 'Check-in date is required.';
    if (!$checkOut)  $errors[] = 'Check-out date is required.';
    if ($checkIn && strtotime($checkIn) < strtotime(date('Y-m-d')))
        $errors[] = 'Check-in date cannot be in the past.';
    if ($checkIn && $checkOut && $checkOut <= $checkIn)
        $errors[] = 'Check-out date must be after check-in date.';
    if (!$checkinTime)  $errors[] = 'Preferred check-in time is required.';
    if (!$checkoutTime) $errors[] = 'Preferred check-out time is required.';
    if (!$idProofType) $errors[] = 'Please select an ID proof type.';
    if (!$idProofNo)   $errors[] = 'Please enter your ID proof number.';
    if ($idProofType && $idProofNo) {
        $idErr = validateIdNumber($idProofType, $idProofNo);
        if ($idErr) $errors[] = $idErr;
    }

    // Pre-calculate advancePaid for payment tamper check.
    // Room price is fetched inside the DB transaction below, but we need
    // advancePaid here for the Razorpay amount mismatch check.
    $advancePaid = 0;
    if (empty($errors) && $roomId && $checkIn && $checkOut) {
        $priceStmt = getDB()->prepare("SELECT price FROM rooms WHERE id = ?");
        $priceStmt->execute([$roomId]);
        $priceRow = $priceStmt->fetch();
        if ($priceRow) {
            $preNights      = (int) ceil((strtotime($checkOut) - strtotime($checkIn)) / 86400);
            $preRoomCharges = $preNights * (float)$priceRow['price'];
            $preTax         = $preRoomCharges * 0.12;
            $preTotal       = $preRoomCharges + $preTax;
            $advancePaid    = round($preTotal * RAZORPAY_ADVANCE_PERCENT, 2);
        }
    }

    // ── Verify Razorpay payment (only if paying online) ──
    if (empty($errors)) {
        if ($paymentMode === 'online') {
            if (!$razorpayPaymentId || !$razorpayOrderId || !$razorpaySignature) {
                $errors[] = 'Payment not completed. Please complete the online payment or choose "Pay at Hotel".';
            } else {
                $expectedSig = hash_hmac('sha256', $razorpayOrderId . '|' . $razorpayPaymentId, RAZORPAY_KEY_SECRET);
                if (!hash_equals($expectedSig, $razorpaySignature)) {
                    $errors[] = 'Payment verification failed. Please try again.';
                } else {
                    // Amount tamper check — compare what Razorpay was asked to charge
                    // (stored in session when order was created) against what the server
                    // calculated from the real room price. Prevents a guest from
                    // manipulating data-price in DevTools to pay less than the room costs.
                    $sessionOrder  = $_SESSION['rzp_order_' . $razorpayOrderId] ?? null;
                    $expectedPaise = (int) round($advancePaid * 100);
                    if (!$sessionOrder || (int)$sessionOrder['amount_paise'] !== $expectedPaise) {
                        $errors[] = 'Payment amount mismatch. Please try again or contact the hotel.';
                    } else {
                        unset($_SESSION['rzp_order_' . $razorpayOrderId]);
                        $paymentVerified = true;
                    }
                }
            }
        } else {
            $paymentVerified = true;
        }
    }

    // ── Handle ID photo uploads ──────────────────────────────────────
    // Validation uses finfo_file() to read the actual magic bytes from
    // the uploaded file — browser-supplied $_FILES[]['type'] is NOT trusted
    // because a malicious file (e.g. PHP script) renamed to .jpg would
    // pass a simple MIME string check.
    $idFrontPath = null;
    $idBackPath  = null;
    $uploadDir   = __DIR__ . '/../../uploads/id_proofs/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    // Map real MIME → safe extension (derived from file content, never from filename)
    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
    ];
    $maxSize = 2 * 1024 * 1024; // 2 MB

    /**
     * Validate an upload by reading magic bytes with finfo_file().
     * Returns the safe extension string on success (e.g. 'jpg'),
     * an error message string on failure, or null if not uploaded and not required.
     */
    $validateUpload = function(array $file, bool $required, string $label) use ($maxSize, $allowedMimes): ?string {
        if (empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $required ? "{$label} photo is required." : null;
        }
        if (($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
            return "Upload error for {$label} photo (code {$file['error']}). Please try again.";
        }
        if ($file['size'] > $maxSize) {
            return "{$label} photo must be under 2MB.";
        }
        // Read actual MIME from file content — not from the browser header
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!isset($allowedMimes[$realMime])) {
            return "{$label} photo must be a real JPG or PNG image.";
        }
        // Return safe extension derived from real MIME (never from filename)
        return $allowedMimes[$realMime];
    };

    if (empty($errors)) {
        $frontResult = $validateUpload($_FILES['id_front'] ?? [], true, 'ID Front');
        if ($frontResult === null || strlen($frontResult) > 4) {
            if ($frontResult !== null) $errors[] = $frontResult;
        } else {
            $idFrontPath = 'uploads/id_proofs/' . uniqid('front_') . '.' . $frontResult;
            if (!move_uploaded_file($_FILES['id_front']['tmp_name'], __DIR__ . '/../../' . $idFrontPath)) {
                $errors[] = 'Failed to save ID Front photo. Please try again.';
                $idFrontPath = null;
            }
        }
    }

    if (!empty($_FILES['id_back']['name'] ?? '')) {
        $backResult = $validateUpload($_FILES['id_back'] ?? [], false, 'ID Back');
        if (is_string($backResult) && strlen($backResult) <= 4) {
            $idBackPath = 'uploads/id_proofs/' . uniqid('back_') . '.' . $backResult;
            if (!move_uploaded_file($_FILES['id_back']['tmp_name'], __DIR__ . '/../../' . $idBackPath))
                $idBackPath = null;
        }
    }

    if (empty($errors)) {
        // ── BEGIN transaction — prevents double booking race condition ──
        // SELECT FOR UPDATE locks this room row until COMMIT, so two
        // simultaneous submissions cannot both pass the availability check.
        $db->beginTransaction();
        try {
            // Lock the room row for this transaction
            $stmt = $db->prepare("SELECT * FROM rooms WHERE id = ? FOR UPDATE");
            $stmt->execute([$roomId]);
            $room = $stmt->fetch();

        if (!$room) {
            $db->rollBack();
            $errors[] = 'Selected room was not found. Please choose again.';
        } elseif (($numAdults + $numChildren) > (int)$room['capacity']) {
            $db->rollBack();
            $errors[] = 'Total guests (' . ($numAdults + $numChildren) . ') exceeds this room\'s capacity of ' . (int)$room['capacity'] . '. Please select fewer guests or choose a larger room.';
        } else {
            // Double-booking check: check BOTH tables so a confirmed admin booking
            // cannot be overridden by a new online request and vice versa
            $overlapRequests = $db->prepare("
                SELECT id FROM online_booking_requests
                WHERE  room_id = ?
                AND    status  NOT IN ('Cancelled','Rejected')
                AND    check_in  < ?
                AND    check_out > ?
                AND    check_out > CURDATE()
                LIMIT 1
            ");
            $overlapRequests->execute([$roomId, $checkOut, $checkIn]);

            $overlapBookings = $db->prepare("
                SELECT id FROM bookings
                WHERE  room_id = ?
                AND    status  NOT IN ('Cancelled','Completed','Checked-Out')
                AND    check_in  < ?
                AND    check_out > ?
                LIMIT 1
            ");
            $overlapBookings->execute([$roomId, $checkOut, $checkIn]);

            if ($overlapRequests->fetch() || $overlapBookings->fetch()) {
                $db->rollBack();
                $errors[] = 'Sorry, this room was just booked by another guest for your selected dates. Please choose different dates or another room.';
            } else {

            if (strtotime($checkOut) <= strtotime($checkIn)) {
                $errors[] = 'Check-out date must be after check-in date.';
            }
            $nights      = (int) ceil((strtotime($checkOut) - strtotime($checkIn)) / 86400);
            if ($nights < 1) $errors[] = 'Minimum stay is 1 night.';
            $roomCharges = $nights * (float)$room['price'];
            $tax         = $roomCharges * 0.12;
            $total       = $roomCharges + $tax;
            $advancePaid = round($total * RAZORPAY_ADVANCE_PERCENT, 2);
            $requestNo   = 'REQ-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

            // Append time preferences to special requests
            $timeParts = [];
            if ($checkinTime)  $timeParts[] = "Preferred check-in: {$checkinTime}";
            if ($checkoutTime) $timeParts[] = "Preferred check-out: {$checkoutTime}";
            $timeNote       = implode(' | ', $timeParts);
            $payHotelPrefix = ($paymentMode === 'hotel') ? '[Pay at Hotel] ' : '';
            $fullSpecialReq = $payHotelPrefix . implode("\n", array_filter([$specialReq, $timeNote]));

            $payStatus = ($paymentMode === 'online') ? 'Paid' : 'Pay at Hotel';

            try {
                $stmt = $db->prepare("
                    INSERT INTO online_booking_requests
                        (request_no, guest_name, mobile, email,
                         room_type, room_id, room_number,
                         check_in, check_out,
                         checkin_time, checkout_time,
                         num_adults, num_children,
                         special_requests, total_amount,
                         payment_status, payment_id, payment_order_id, advance_paid,
                         status)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ");
                $stmt->execute([
                    $requestNo,
                    $guestName, $mobile, $email,
                    $room['room_type'], $roomId, $room['room_number'],
                    $checkIn, $checkOut,
                    $checkinTime, $checkoutTime,
                    $numAdults, $numChildren,
                    $fullSpecialReq, $total,
                    $payStatus,
                    $razorpayPaymentId ?: null,
                    $razorpayOrderId   ?: null,
                    ($paymentMode === 'online') ? $advancePaid : 0,
                    'Pending',
                ]);

                // Auto-create guest profile if new mobile
                $chk = $db->prepare("SELECT id FROM guests WHERE mobile = ?");
                $chk->execute([$mobile]);
                $existingGuest = $chk->fetch();
                if (!$existingGuest) {
                    $gid = generateId();
                    $db->prepare("INSERT INTO guests (id, name, mobile, email) VALUES (?,?,?,?)")
                       ->execute([$gid, $guestName, $mobile, $email]);
                    $guestId = $gid;
                } else {
                    $guestId = $existingGuest['id'];
                }

                // Save ID proof
                if ($idFrontPath) {
                    $proofId = generateId();
                    $db->prepare("INSERT INTO id_proofs (id, guest_id, id_type, id_number, photo, photo_back, created_at) VALUES (?,?,?,?,?,?,NOW())")
                       ->execute([$proofId, $guestId, $idProofType, $idProofNo, $idFrontPath, $idBackPath]);
                    $db->prepare("UPDATE guests SET id_proof_type=?, id_proof_number=? WHERE id=?")
                       ->execute([$idProofType, $idProofNo, $guestId]);
                }

                // ── COMMIT — lock released, booking is now saved ──
                // Notification is sent AFTER commit so the guest only receives
                // a confirmation email once the booking is durably stored.
                // If commit() throws, we roll back and the email is never sent.
                $db->commit();

                $success = true;

                // ── Send booking received notification ───────────────────
                if (file_exists(__DIR__ . '/../includes/notifications.php')) {
                    require_once __DIR__ . '/../includes/notifications.php';
                    sendBookingNotification('booking_received', [
                        'guestName'     => $guestName,
                        'mobile'        => $mobile,
                        'email'         => $email,
                        'bookingNo'     => $requestNo,
                        'roomNumber'    => $room['room_number'] ?? '',
                        'roomType'      => $room['room_type']   ?? '',
                        'checkIn'       => $checkIn,
                        'checkOut'      => $checkOut,
                        'totalAmount'   => $total,
                        'paymentStatus' => $payStatus,
                    ]);
                }

            } catch (\PDOException $e) {
                $db->rollBack();
                $errors[] = 'Booking could not be saved. Please try again.';
            }
            } // end overlap check
        } // end room checks
        } catch (\PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            $errors[] = 'A database error occurred. Please try again.';
        }
    }
}
// Pre-fill values from GET (coming from rooms page / homepage search)
$preRoomId   = sanitize($_GET['room_id']   ?? '');
$preCheckIn  = sanitize($_GET['check_in']  ?? date('Y-m-d'));
$preCheckOut = sanitize($_GET['check_out'] ?? date('Y-m-d', strtotime('+1 day')));
$preType     = sanitize($_GET['room_type'] ?? '');
$preGuests   = (int)($_GET['num_guests']   ?? 1);

// Fetch available rooms
// Include Available, Occupied, and Checked-Out rooms — availability is
// determined by date overlap, not just the room's current status flag.
$where  = ["status NOT IN ('Maintenance','Out of Order')"];
$params = [];

if ($preCheckIn && $preCheckOut) {
    // Exclude rooms with an overlapping confirmed booking
    $where[] = "id NOT IN (
        SELECT room_id FROM bookings
        WHERE  room_id IS NOT NULL
        AND    status  NOT IN ('Cancelled','Completed','Checked-Out')
        AND    check_in  < ?
        AND    check_out > ?
    )";
    $params[] = $preCheckOut;
    $params[] = $preCheckIn;
    // Also exclude rooms with an overlapping online request (Pending or Confirmed)
    $where[] = "id NOT IN (
        SELECT room_id FROM online_booking_requests
        WHERE  room_id IS NOT NULL
        AND    status  NOT IN ('Cancelled','Rejected')
        AND    check_in  < ?
        AND    check_out > ?
    )";
    $params[] = $preCheckOut;
    $params[] = $preCheckIn;
}

if ($preType) {
    $where[]  = "room_type = ?";
    $params[] = $preType;
}

$sql       = "SELECT * FROM rooms WHERE " . implode(' AND ', $where) . " ORDER BY price ASC";
$stmtRooms = $db->prepare($sql);
$stmtRooms->execute($params);
$availableRooms = $stmtRooms->fetchAll();

// Logged-in guest pre-fill
$guest = getLoggedInGuest();

require_once __DIR__ . '/../includes/header.php';
?>
<!-- Flatpickr -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
/* ── Flatpickr Hotel Theme (full — matches room-booking.php) ── */
.flatpickr-calendar { font-family:'Jost',sans-serif!important;box-shadow:0 20px 60px rgba(59,26,10,0.25)!important;border-radius:18px!important;border:1px solid #E8D9C0!important;width:320px!important;overflow:hidden!important;padding:0!important;background:#fff!important; }
.flatpickr-months { background:linear-gradient(135deg,#2A1007,#4a1e08)!important;border-radius:18px 18px 0 0!important;height:56px!important;display:flex!important;align-items:center!important;position:relative!important; }
.flatpickr-month { height:56px!important;color:#fff!important;fill:#fff!important;display:flex!important;align-items:center!important;justify-content:center!important; }
.flatpickr-current-month { display:flex!important;align-items:center!important;justify-content:center!important;gap:4px!important;padding:0!important;height:56px!important;font-size:1rem!important; }
.flatpickr-current-month .flatpickr-monthDropdown-months { -webkit-appearance:none!important;appearance:none!important;background:rgba(255,255,255,0.12)!important;border:1px solid rgba(196,148,58,0.5)!important;border-radius:8px!important;color:#fff!important;font-family:'Playfair Display',Georgia,serif!important;font-size:1rem!important;font-weight:700!important;font-style:italic!important;cursor:pointer!important;padding:4px 28px 4px 10px!important;outline:none!important;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%23C4943A' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E")!important;background-repeat:no-repeat!important;background-position:right 8px center!important;background-size:16px!important;transition:all 0.2s!important; }
.flatpickr-current-month .flatpickr-monthDropdown-months:hover { background-color:rgba(196,148,58,0.25)!important;border-color:#C4943A!important; }
.flatpickr-current-month .flatpickr-monthDropdown-months option { background:#3B1A0A!important;color:#fff!important;font-style:normal!important;font-family:'Jost',sans-serif!important; }
.flatpickr-current-month .numInputWrapper { background:rgba(255,255,255,0.12)!important;border:1px solid rgba(196,148,58,0.5)!important;border-radius:8px!important;padding:0!important;width:72px!important;transition:all 0.2s!important; }
.flatpickr-current-month .numInputWrapper:hover { background:rgba(196,148,58,0.25)!important;border-color:#C4943A!important; }
.flatpickr-current-month input.cur-year { color:#D4AD5E!important;font-size:0.95rem!important;font-weight:700!important;font-family:'Jost',sans-serif!important;background:transparent!important;border:none!important;outline:none!important;padding:4px 6px 4px 10px!important;width:100%!important;cursor:pointer!important; }
.flatpickr-current-month .numInputWrapper span { display:flex!important;align-items:center!important;justify-content:center!important;border:none!important;right:0!important;width:18px!important;opacity:1!important; }
.flatpickr-current-month .numInputWrapper span.arrowUp { top:0!important;height:50%!important;border-bottom:1px solid rgba(196,148,58,0.3)!important; }
.flatpickr-current-month .numInputWrapper span.arrowDown { top:50%!important;height:50%!important; }
.flatpickr-current-month .numInputWrapper span::after { border-left-color:#C4943A!important;border-right-color:#C4943A!important; }
.flatpickr-current-month .numInputWrapper span.arrowUp::after { border-bottom-color:#C4943A!important; }
.flatpickr-current-month .numInputWrapper span.arrowDown::after { border-top-color:#C4943A!important; }
.flatpickr-prev-month,.flatpickr-next-month { fill:#C4943A!important;color:#C4943A!important;height:56px!important;padding:0 14px!important;display:flex!important;align-items:center!important;top:0!important;transition:background 0.2s!important; }
.flatpickr-prev-month:hover,.flatpickr-next-month:hover { background:rgba(196,148,58,0.2)!important; }
.flatpickr-prev-month svg,.flatpickr-next-month svg { width:14px!important;height:14px!important;fill:#C4943A!important; }
.flatpickr-prev-month:hover svg,.flatpickr-next-month:hover svg { fill:#D4AD5E!important; }
.flatpickr-weekdays { background:#FBF7F2!important;border-bottom:1px solid #EFE5D8!important;height:38px!important;margin-top:20px!important; }
.flatpickr-weekday { color:#C4943A!important;font-weight:700!important;font-size:0.7rem!important;letter-spacing:0.8px!important;text-transform:uppercase!important; }
.flatpickr-innerContainer { background:#fff!important; }
.flatpickr-days { border:none!important; }
.dayContainer { padding:8px 8px 10px!important;width:100%!important;min-width:100%!important;max-width:100%!important; }
.flatpickr-day { border-radius:10px!important;color:#3B1A0A!important;font-size:0.84rem!important;font-weight:500!important;height:36px!important;line-height:36px!important;max-width:36px!important;border:1.5px solid transparent!important;transition:all 0.15s!important;margin:2px!important; }
.flatpickr-day:hover { background:#FBF0DC!important;border-color:#E8D9C0!important; }
.flatpickr-day.today { border-color:#C4943A!important;background:#FFF8EC!important;font-weight:800!important;color:#8B6914!important; }
.flatpickr-day.selected,.flatpickr-day.selected:hover { background:linear-gradient(135deg,#C4943A,#D4AD5E)!important;border-color:#C4943A!important;color:#fff!important;font-weight:700!important;box-shadow:0 3px 12px rgba(196,148,58,0.4)!important; }
.flatpickr-day.disabled,.flatpickr-day.prevMonthDay,.flatpickr-day.nextMonthDay { color:#D5C5B5!important;background:transparent!important;border-color:transparent!important; }
.flatpickr-day.flatpickr-disabled,.flatpickr-day.flatpickr-disabled:hover { color:#E0D5C8!important;cursor:not-allowed!important; }

/* ── Booking page layout ── */
.bk-section { background:var(--cream);padding:60px 0 80px; }
.bk-container { max-width:1200px;margin:0 auto;padding:0 20px; }

/* ── Step indicator ── */
.bk-steps { display:flex;align-items:center;justify-content:center;gap:0;margin-bottom:48px; }
.bk-step { display:flex;align-items:center;flex-direction:column;position:relative; }
.bk-step-circle { width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.85rem;font-weight:700;border:2px solid var(--border);background:var(--white);color:var(--text-muted);transition:all 0.3s;font-family:'Jost',sans-serif;z-index:1; }
.bk-step.active .bk-step-circle { background:var(--accent);border-color:var(--accent);color:var(--primary-dark);box-shadow:0 4px 16px rgba(196,148,58,0.4); }
.bk-step.done .bk-step-circle { background:var(--primary);border-color:var(--primary);color:#fff; }
.bk-step-label { font-size:0.7rem;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin-top:6px;font-family:'Jost';font-weight:600;white-space:nowrap; }
.bk-step.active .bk-step-label { color:var(--accent); }
.bk-step-line { width:80px;height:2px;background:var(--border);margin:0 4px;margin-bottom:24px;transition:all 0.3s; }
.bk-step.done + .bk-step-line { background:var(--accent); }

/* ── Card wrapper ── */
.bk-card { background:var(--white);border-radius:20px;box-shadow:0 4px 32px rgba(59,26,10,0.08);border:1px solid var(--border);overflow:hidden;margin-bottom:20px; }
.bk-card-header { background:linear-gradient(135deg,var(--primary-dark),var(--primary));padding:22px 28px;display:flex;align-items:center;gap:14px; }
.bk-card-header-icon { width:42px;height:42px;background:rgba(196,148,58,0.2);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
.bk-card-header-icon i { color:var(--accent-light);font-size:1.1rem; }
.bk-card-header h3 { font-family:'Playfair Display',serif;color:var(--white);font-size:1.1rem;margin:0;font-weight:600; }
.bk-card-header p { color:rgba(255,255,255,0.6);font-size:0.8rem;margin:0;font-family:'Cormorant Garamond',serif;font-style:italic; }
.bk-card-body { padding:28px; }

/* ── Form inputs ── */
.bk-label { font-size:0.72rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--text-muted);margin-bottom:7px;display:block;font-family:'Jost'; }
.bk-input { border:1.5px solid var(--border);border-radius:10px;padding:11px 15px;font-family:'Jost';color:var(--text-dark);font-size:0.9rem;background:var(--cream);transition:all 0.25s;width:100%; }
.bk-input:focus { outline:none;border-color:var(--accent);background:var(--white);box-shadow:0 0 0 3px rgba(196,148,58,0.12); }
.bk-select { appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%23C4943A' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;background-size:18px;padding-right:36px; }
.bk-input-icon { position:relative; }
.bk-input-icon i { position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--accent);font-size:0.9rem;pointer-events:none; }
.bk-input-icon .bk-input { padding-left:38px; }
.bk-helper { font-size:0.71rem;color:var(--text-muted);margin-top:5px;display:block; }

/* ── Room cards ── */
.bk-room-card { border:2px solid var(--border);border-radius:14px;padding:18px;cursor:pointer;transition:all 0.25s;background:var(--white);position:relative;height:100%; }
.bk-room-card:hover { border-color:var(--accent);box-shadow:0 6px 24px rgba(196,148,58,0.15);transform:translateY(-2px); }
.bk-room-card.selected { border-color:var(--accent);background:linear-gradient(135deg,#fdfaf5,#faf4e8);box-shadow:0 6px 24px rgba(196,148,58,0.2); }
.bk-room-card.selected::before { content:'✓';position:absolute;top:10px;right:12px;width:24px;height:24px;background:var(--accent);color:var(--primary-dark);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:900;line-height:24px;text-align:center; }
.bk-room-number { font-family:'Playfair Display',serif;font-size:1.05rem;color:var(--primary);font-weight:700;margin-bottom:2px; }
.bk-room-type { font-size:0.7rem;text-transform:uppercase;letter-spacing:1.5px;color:var(--accent);font-weight:600;margin-bottom:10px; }
.bk-room-price { font-family:'Playfair Display',serif;font-size:1.2rem;color:var(--accent);font-weight:700; }
.bk-room-meta { font-size:0.76rem;color:var(--text-muted);margin-top:8px; }
.bk-amenity-pill { background:var(--accent-pale);color:var(--primary-light);font-size:0.68rem;padding:2px 8px;border-radius:50px;font-weight:500;display:inline-flex;align-items:center;gap:3px; }

/* ── Upload boxes ── */
.bk-upload { border:2px dashed var(--accent);border-radius:12px;height:130px;display:flex;flex-direction:column;align-items:center;justify-content:center;cursor:pointer;background:#FDFAF5;transition:all 0.3s;overflow:hidden;position:relative; }
.bk-upload:hover { border-style:solid;background:var(--accent-pale); }
.bk-upload.uploaded { border-color:#28a745;border-style:solid;background:#f0fff4; }
.bk-upload-secondary { border-color:#ddd;border-color:rgba(196,148,58,0.3); }
.bk-upload img { width:100%;height:100%;object-fit:cover;position:absolute;inset:0; }

/* ── Payment option cards ── */
.bk-pay-card { border:2px solid var(--border);border-radius:14px;padding:20px;cursor:pointer;transition:all 0.25s;background:var(--white);position:relative;height:100%; }
.bk-pay-card:hover { border-color:rgba(196,148,58,0.5);box-shadow:0 4px 16px rgba(196,148,58,0.1); }
.bk-pay-card.selected { border-color:var(--accent);background:linear-gradient(135deg,#fdfaf5,#faf4e8); }
.bk-pay-radio { width:20px;height:20px;border-radius:50%;border:2px solid var(--border);background:var(--white);display:flex;align-items:center;justify-content:center;transition:all 0.2s;flex-shrink:0; }
.bk-pay-radio .dot { width:8px;height:8px;background:transparent;border-radius:50%;transition:all 0.2s; }
.bk-pay-card.selected .bk-pay-radio { border-color:var(--accent);background:var(--accent); }
.bk-pay-card.selected .bk-pay-radio .dot { background:#fff; }
.bk-pay-emoji { font-size:1.8rem;margin-bottom:10px; }
.bk-pay-title { font-weight:700;color:var(--primary);font-size:0.95rem;margin-bottom:4px; }
.bk-pay-sub { font-size:0.78rem;color:var(--text-muted);line-height:1.4; }
.bk-pay-badge { font-size:0.7rem;padding:3px 10px;border-radius:50px;font-weight:600;display:inline-block;margin-top:10px; }

/* ── Policy box ── */
.bk-policy { background:linear-gradient(135deg,#fdfaf5,#faf4e8);border-radius:14px;padding:20px 22px;border:1px solid #ede0c8;margin-bottom:22px; }
.bk-policy-item { display:flex;align-items:flex-start;gap:10px;font-size:0.84rem;color:#6b5c45;line-height:1.5;margin-bottom:10px; }
.bk-policy-item:last-child { margin-bottom:0; }
.bk-policy-icon { width:24px;height:24px;border-radius:6px;background:rgba(196,148,58,0.15);display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px; }
.bk-policy-icon i { color:#C4943A;font-size:0.72rem; }

/* ── Summary sidebar ── */
.bk-summary { background:linear-gradient(160deg,var(--primary-dark),var(--primary));border-radius:20px;padding:28px; }
.bk-summary-title { font-family:'Playfair Display',serif;color:var(--accent-light);font-size:1rem;margin-bottom:20px;padding-bottom:14px;border-bottom:1px solid rgba(255,255,255,0.1);display:flex;align-items:center;gap:8px; }
.bk-sum-row { display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;font-size:0.85rem; }
.bk-sum-row span:first-child { color:rgba(255,255,255,0.6); }
.bk-sum-row span:last-child { color:var(--white);font-weight:500; }
.bk-sum-total { border-top:1px solid rgba(255,255,255,0.15);padding-top:14px;margin-top:6px; }
.bk-sum-total span:first-child { color:var(--accent-light);font-weight:600;font-size:0.9rem; }
.bk-sum-total span:last-child { font-family:'Playfair Display',serif;font-size:1.3rem;color:var(--accent-light);font-weight:700; }
.bk-why { margin-top:20px;padding-top:16px;border-top:1px solid rgba(255,255,255,0.1); }
.bk-why-item { display:flex;align-items:center;gap:10px;font-size:0.78rem;color:rgba(255,255,255,0.65);margin-bottom:10px; }
.bk-why-item i { color:var(--accent-light);font-size:0.85rem;flex-shrink:0; }

/* ── Filter tabs ── */
.bk-type-tabs { display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px; }
.bk-type-tab { padding:6px 14px;border-radius:50px;border:1.5px solid var(--border);background:var(--white);color:var(--text-muted);font-size:0.78rem;font-weight:600;cursor:pointer;transition:all 0.2s;font-family:'Jost'; }
.bk-type-tab:hover { border-color:var(--accent);color:var(--accent); }
.bk-type-tab.active { background:var(--primary);border-color:var(--primary);color:var(--accent-light); }

/* ── Submit button ── */
.bk-submit { padding:16px;font-size:1rem;border-radius:12px;width:100%;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;font-weight:700;transition:all 0.3s;font-family:'Jost'; }
.bk-submit:disabled { opacity:0.6;cursor:not-allowed; }
.bk-submit-hotel { background:linear-gradient(135deg,var(--primary),var(--primary-light));color:var(--accent-light); }
.bk-submit-hotel:hover:not(:disabled) { transform:translateY(-2px);box-shadow:0 8px 24px rgba(59,26,10,0.3); }
.bk-submit-online { background:linear-gradient(135deg,var(--accent),var(--accent-light));color:var(--primary-dark); }
.bk-submit-online:hover:not(:disabled) { transform:translateY(-2px);box-shadow:0 8px 24px rgba(196,148,58,0.4); }

/* ── Terms ── */
.bk-terms { display:flex;align-items:center;gap:10px;margin-bottom:18px;padding:14px 16px;background:#f8f4ef;border-radius:10px;border:1px solid var(--border); }
.bk-terms input { width:18px;height:18px;border-color:var(--accent);flex-shrink:0;accent-color:var(--accent); }
.bk-terms label { font-size:0.84rem;color:var(--text-muted);margin:0; }

/* ── Divider ── */
.bk-divider { display:flex;align-items:center;gap:14px;margin:24px 0 20px; }
.bk-divider span { font-size:0.7rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--text-muted);white-space:nowrap;font-family:'Jost'; }
.bk-divider::before,.bk-divider::after { content:'';flex:1;height:1px;background:var(--border); }

/* ── No rooms empty state ── */
.bk-empty { text-align:center;padding:48px 24px;background:var(--white);border-radius:16px;border:2px dashed var(--border); }
.bk-empty i { font-size:3rem;color:var(--border);display:block;margin-bottom:12px; }

@media (max-width:768px) {
    .bk-card-body { padding:20px; }
    .bk-summary { position:static;margin-top:20px; }
    .bk-steps { display:none; }
}
</style>

<!-- Login Required Popup -->
<?php if (!$isLoggedIn): ?>
<div id="loginModal" style="position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.65);backdrop-filter:blur(6px);">
    <div style="background:#fff;border-radius:24px;padding:48px 40px;max-width:440px;width:90%;text-align:center;box-shadow:0 32px 80px rgba(0,0,0,0.35);animation:popIn 0.3s ease;">
        <style>@keyframes popIn{from{transform:scale(0.85);opacity:0}to{transform:scale(1);opacity:1}}</style>
        <img src="<?= SITE_URL ?>/images/Lodge_Logoo.png" style="width:70px;height:70px;object-fit:contain;margin-bottom:16px;">
        <h3 style="font-family:'Playfair Display',serif;color:#3B1A0A;font-size:1.5rem;margin-bottom:8px;">Login Required</h3>
        <p style="color:#8B7355;font-family:'Cormorant Garamond',serif;font-style:italic;font-size:1rem;margin-bottom:28px;">Please login or create an account to book a room.</p>
        <div style="display:flex;flex-direction:column;gap:12px;">
            <a href="<?= SITE_URL ?>/pages/login.php" onclick="sessionStorage.setItem('redirectAfterLogin','<?= SITE_URL ?>/pages/booking.php')"
               style="background:linear-gradient(135deg,#C4943A,#D4AD5E);color:#2A1007;padding:14px 24px;border-radius:12px;font-weight:700;font-size:1rem;text-decoration:none;display:block;box-shadow:0 6px 20px rgba(196,148,58,0.35);">
                <i class="bi bi-box-arrow-in-right me-2"></i>Login to My Account
            </a>
            <a href="<?= SITE_URL ?>/pages/register.php"
               style="background:#f8f4ef;color:#3B1A0A;padding:14px 24px;border-radius:12px;font-weight:600;font-size:1rem;text-decoration:none;display:block;border:1.5px solid #E8D9C0;">
                <i class="bi bi-person-plus me-2"></i>Create New Account
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Page Hero -->
<div class="page-hero">
    <div class="container page-hero-content">
        <div class="breadcrumb-hotel mb-3">
            <a href="<?= SITE_URL ?>/index.php">Home</a><span class="sep">›</span>
            <a href="<?= SITE_URL ?>/pages/rooms.php">Rooms</a><span class="sep">›</span>
            <span class="current">Book Now</span>
        </div>
        <h1>Reserve Your Room</h1>
        <p>Complete your booking in just a few easy steps</p>
    </div>
</div>

<section class="bk-section">
<div class="bk-container">

    <!-- Step indicator -->
    <div class="bk-steps">
        <div class="bk-step active"><div class="bk-step-circle">1</div><div class="bk-step-label">Dates & Room</div></div>
        <div class="bk-step-line"></div>
        <div class="bk-step"><div class="bk-step-circle">2</div><div class="bk-step-label">Guest Details</div></div>
        <div class="bk-step-line"></div>
        <div class="bk-step"><div class="bk-step-circle">3</div><div class="bk-step-label">ID & Payment</div></div>
        <div class="bk-step-line"></div>
        <div class="bk-step"><div class="bk-step-circle"><i class="bi bi-check2" style="font-size:0.9rem"></i></div><div class="bk-step-label">Confirmed</div></div>
    </div>

    <?php if ($success): ?>
    <!-- SUCCESS -->
    <div style="max-width:620px;margin:0 auto;">
        <div class="bk-card" style="text-align:center;overflow:visible;">
            <div style="background:linear-gradient(135deg,<?= $paymentMode==='online' ? '#28a745,#20c997' : '#C4943A,#D4AD5E' ?>);height:6px;border-radius:20px 20px 0 0;"></div>
            <div class="bk-card-body" style="padding:48px 40px;">
                <div style="width:80px;height:80px;background:<?= $paymentMode==='online' ? 'linear-gradient(135deg,#28a745,#20c997)' : 'linear-gradient(135deg,#C4943A,#D4AD5E)' ?>;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;box-shadow:0 8px 28px <?= $paymentMode==='online' ? 'rgba(40,167,69,0.35)' : 'rgba(196,148,58,0.4)' ?>;">
                    <i class="bi bi-<?= $paymentMode==='online' ? 'check-lg' : 'calendar-check' ?>" style="font-size:2.2rem;color:<?= $paymentMode==='online' ? '#fff' : '#2A1007' ?>;"></i>
                </div>
                <h2 style="font-family:'Playfair Display',serif;color:var(--primary);font-size:1.9rem;margin-bottom:10px;">
                    <?= $paymentMode==='online' ? 'Booking Confirmed &amp; Paid!' : 'Booking Request Sent!' ?>
                </h2>
                <p style="font-family:'Cormorant Garamond',serif;font-style:italic;color:var(--text-muted);font-size:1.05rem;margin-bottom:24px;">
                    <?= $paymentMode==='online' ? 'Your payment was successful.' : 'Thank you for choosing Karavali Lodge.' ?>
                </p>

                <!-- Reference number -->
                <div style="background:var(--accent-pale);border:2px dashed var(--accent);border-radius:14px;padding:20px 28px;margin-bottom:20px;display:inline-block;">
                    <div style="font-size:0.68rem;letter-spacing:2.5px;text-transform:uppercase;color:var(--accent);font-weight:700;margin-bottom:6px;font-family:'Jost';">Booking Reference</div>
                    <div style="font-family:'Playfair Display',serif;font-size:1.7rem;color:var(--primary);font-weight:700;"><?= sanitize($requestNo) ?></div>
                </div>

                <?php if ($paymentMode==='online'): ?>
                <div style="display:inline-flex;align-items:center;gap:8px;background:#d4edda;border:1px solid #c3e6cb;border-radius:50px;padding:7px 18px;margin-bottom:24px;font-size:0.82rem;color:#155724;font-weight:600;display:block;max-width:380px;margin:0 auto 20px;">
                    <i class="bi bi-shield-check-fill me-1"></i>Payment Verified · <?= sanitize($razorpayPaymentId ?? '') ?>
                </div>
                <?php else: ?>
                <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:12px;padding:14px 18px;margin:0 auto 20px;max-width:360px;text-align:left;display:flex;align-items:flex-start;gap:10px;">
                    <i class="bi bi-info-circle-fill" style="color:#e6a817;margin-top:2px;flex-shrink:0;"></i>
                    <div style="font-size:0.83rem;color:#856404;"><strong>Payment due at check-in.</strong><br>Our team will confirm your booking shortly.</div>
                </div>
                <?php endif; ?>

                <p style="color:var(--text-muted);font-size:0.88rem;margin-bottom:28px;">Save your reference number for follow-ups.</p>
                <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
                    <a href="<?= SITE_URL ?>/pages/my-bookings.php" class="btn-accent-hotel" style="padding:12px 28px;border-radius:10px;">
                        <i class="bi bi-calendar-check me-2"></i>My Bookings
                    </a>
                    <a href="<?= SITE_URL ?>/index.php" class="btn-outline-hotel" style="padding:12px 26px;border-radius:10px;">
                        <i class="bi bi-house me-2"></i>Home
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- BOOKING FORM -->
    <div class="row g-4" style="align-items:flex-start;">

        <!-- LEFT: Form (col-lg-8) -->
        <div class="col-lg-8">

            <?php if (!empty($errors)): ?>
            <div class="alert-hotel alert-error-hotel mb-4">
                <i class="bi bi-exclamation-circle-fill" style="font-size:1.1rem;flex-shrink:0;"></i>
                <div><?php foreach ($errors as $e) echo '<div>' . $e . '</div>'; ?></div>
            </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="bookingForm">
                <!-- CSRF protection -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                <!-- Hidden fields -->
                <input type="hidden" name="razorpay_payment_id" id="rzp_payment_id">
                <input type="hidden" name="razorpay_order_id"   id="rzp_order_id">
                <input type="hidden" name="razorpay_signature"  id="rzp_signature">
                <input type="hidden" name="payment_mode"        id="payment_mode" value="hotel">
                <!-- Hidden room select (used by JS) -->
                <select name="room_id" id="room_id" style="display:none" required>
                    <option value="">-- select --</option>
                    <?php foreach ($availableRooms as $r): ?>
                        <option value="<?= $r['id'] ?>" data-price="<?= (float)$r['price'] ?>"
                            <?= (($_POST['room_id'] ?? $preRoomId) === $r['id']) ? 'selected' : '' ?>>
                            Room <?= sanitize($r['room_number']) ?> – <?= sanitize($r['room_type']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- ── SECTION 1: DATES ── -->
                <div class="bk-card">
                    <div class="bk-card-header">
                        <div class="bk-card-header-icon"><i class="bi bi-calendar3"></i></div>
                        <div><h3>Stay Dates</h3><p>Select your check-in and check-out</p></div>
                    </div>
                    <div class="bk-card-body">
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="bk-label">Check-In Date *</label>
                                <div class="bk-input-icon">
                                    <i class="bi bi-calendar-event"></i>
                                    <input type="text" name="check_in" id="check_in" class="bk-input" readonly
                                           value="<?= sanitize($_POST['check_in'] ?? $preCheckIn) ?>" required placeholder="Select date">
                                </div>
                            </div>
                            <div class="col-md-5">
                                <label class="bk-label">Check-Out Date *</label>
                                <div class="bk-input-icon">
                                    <i class="bi bi-calendar-event"></i>
                                    <input type="text" name="check_out" id="check_out" class="bk-input" readonly
                                           value="<?= sanitize($_POST['check_out'] ?? $preCheckOut) ?>" required placeholder="Select date">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── SECTION 2: ROOM SELECTION ── -->
                <div class="bk-card">
                    <div class="bk-card-header">
                        <div class="bk-card-header-icon"><i class="bi bi-door-open"></i></div>
                        <div><h3>Select a Room</h3><p><?= count($availableRooms) ?> room<?= count($availableRooms)!==1?'s':'' ?> available for your dates</p></div>
                    </div>
                    <div class="bk-card-body">
                        <!-- Type filter tabs -->
                        <div class="bk-type-tabs">
                            <div class="bk-type-tab active" onclick="filterByType('',this)">All</div>
                            <?php foreach (['Single','Double','Deluxe','Suite','Family','Dormitory'] as $t): ?>
                                <div class="bk-type-tab" onclick="filterByType('<?= $t ?>',this)"><?= $t ?></div>
                            <?php endforeach; ?>
                        </div>

                        <?php if (empty($availableRooms)): ?>
                        <div class="bk-empty">
                            <i class="bi bi-door-closed"></i>
                            <h5 style="font-family:'Playfair Display',serif;color:var(--text-muted);margin-bottom:8px;">No Rooms Available</h5>
                            <p style="color:var(--text-muted);font-size:0.88rem;margin-bottom:16px;">Try different dates or room type.</p>
                            <a href="<?= SITE_URL ?>/pages/rooms.php" class="btn-outline-hotel" style="font-size:0.85rem;padding:9px 20px;">Browse All Rooms</a>
                        </div>
                        <?php else: ?>
                        <div class="row g-3" id="roomCardsContainer">
                            <?php
                            $roomImages = ['Single'=>'🛏️','Double'=>'🛏️🛏️','Deluxe'=>'✨','Suite'=>'👑','Family'=>'👨‍👩‍👧','Dormitory'=>'🏨'];
                            foreach ($availableRooms as $r):
                                $amenities  = array_slice(array_map('trim', explode(',', $r['amenities']??'')), 0, 4);
                                $isSelected = (($_POST['room_id']??$preRoomId)===$r['id']);
                            ?>
                            <div class="col-md-6 room-card-col" data-type="<?= sanitize($r['room_type']) ?>">
                                <div class="bk-room-card <?= $isSelected?'selected':'' ?>"
                                     data-capacity="<?= (int)$r['capacity'] ?>"
                                     onclick="selectRoom('<?= $r['id'] ?>', <?= (float)$r['price'] ?>, 'Room <?= sanitize($r['room_number']) ?> — <?= sanitize($r['room_type']) ?>', <?= (int)$r['capacity'] ?>)">
                                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">
                                        <div>
                                            <div class="bk-room-type"><?= sanitize($r['room_type']) ?></div>
                                            <div class="bk-room-number">Room <?= sanitize($r['room_number']) ?></div>
                                        </div>
                                        <div style="text-align:right;">
                                            <div class="bk-room-price"><?= formatCurrency($r['price']) ?></div>
                                            <div style="font-size:0.7rem;color:var(--text-muted);">/night</div>
                                        </div>
                                    </div>
                                    <div style="display:flex;flex-wrap:wrap;gap:5px;margin-bottom:10px;">
                                        <?php foreach ($amenities as $am): ?>
                                            <span class="bk-amenity-pill"><i class="<?= getAmenityIcon($am) ?>"></i><?= sanitize($am) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="bk-room-meta">
                                        <i class="bi bi-people me-1"></i>Up to <?= (int)$r['capacity'] ?> guests
                                        &nbsp;·&nbsp;<i class="bi bi-building me-1"></i>Floor <?= sanitize($r['floor']??'G') ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ── SECTION 3: GUEST DETAILS ── -->
                <div class="bk-card">
                    <div class="bk-card-header">
                        <div class="bk-card-header-icon"><i class="bi bi-person"></i></div>
                        <div><h3>Guest Information</h3><p>Your personal and contact details</p></div>
                    </div>
                    <div class="bk-card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="bk-label">Full Name *</label>
                                <input type="text" name="guest_name" class="bk-input" placeholder="Your full name"
                                       value="<?= sanitize($_POST['guest_name']??($guest['name']??'')) ?>" required maxlength="100">
                            </div>
                            <div class="col-md-6">
                                <label class="bk-label">Mobile Number *</label>
                                <div class="bk-input-icon">
                                    <i class="bi bi-telephone"></i>
                                    <input type="tel" name="mobile" class="bk-input" placeholder="+91 XXXXX XXXXX"
                                           value="<?= sanitize($_POST['mobile']??($guest['mobile']??'')) ?>" required maxlength="15">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="bk-label">Email Address</label>
                                <div class="bk-input-icon">
                                    <i class="bi bi-envelope"></i>
                                    <input type="email" name="email" class="bk-input" placeholder="your@email.com"
                                           value="<?= sanitize($_POST['email']??($guest['email']??'')) ?>" maxlength="100">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="bk-label">Adults</label>
                                <select name="num_adults" id="bk_adults" class="bk-input bk-select" onchange="updateChildrenOptions(); recalcSummary();">
                                    <?php for ($i=1;$i<=6;$i++): ?>
                                        <option value="<?= $i ?>" <?= (($_POST['num_adults']??$preGuests)==$i)?'selected':'' ?>><?= $i ?> Adult<?= $i>1?'s':'' ?></option>
                                    <?php endfor; ?>
                                </select>
                                <span class="bk-helper" id="bk_capacity_hint" style="display:none;color:var(--accent);"><i class="bi bi-people me-1"></i><span id="bk_cap_text"></span></span>
                            </div>
                            <div class="col-md-3">
                                <label class="bk-label">Children</label>
                                <select name="num_children" id="bk_children" class="bk-input bk-select" onchange="recalcSummary();">
                                    <option value="0">0</option>
                                </select>
                                <span class="bk-helper" id="bk_capacity_warn" style="display:none;color:#dc3545;font-weight:600;"><i class="bi bi-exclamation-triangle me-1"></i>Room capacity full</span>
                            </div>
                            <div class="col-md-6">
                                <label class="bk-label">Preferred Check-In Time <span style="color:var(--accent)">*</span></label>
                                <div class="bk-input-icon">
                                    <i class="bi bi-clock"></i>
                                    <input type="text" name="checkin_time" id="checkin_time_field" class="bk-input" placeholder="e.g. 2:00 PM, 3:00 PM, after noon"
                                           required value="<?= sanitize($_POST['checkin_time']??'') ?>">
                                </div>
                                <span class="bk-helper"><i class="bi bi-info-circle me-1"></i>Required — enter your preferred arrival time</span>
                            </div>
                            <div class="col-md-6">
                                <label class="bk-label">Preferred Check-Out Time <span style="color:var(--accent)">*</span></label>
                                <div class="bk-input-icon">
                                    <i class="bi bi-clock"></i>
                                    <input type="text" name="checkout_time" id="checkout_time_field" class="bk-input" placeholder="e.g. 10:00 AM, 11:00 AM, noon"
                                           required value="<?= sanitize($_POST['checkout_time']??'') ?>">
                                </div>
                                <span class="bk-helper"><i class="bi bi-info-circle me-1"></i>Required — enter your preferred departure time</span>
                            </div>
                            <div class="col-12">
                                <label class="bk-label">Special Requests</label>
                                <textarea name="special_requests" class="bk-input" rows="3" style="resize:vertical" maxlength="500"
                                          placeholder="Dietary needs, accessibility, early check-in, extra pillows..."><?= sanitize($_POST['special_requests']??'') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── SECTION 4: ID VERIFICATION ── -->
                <div class="bk-card">
                    <div class="bk-card-header">
                        <div class="bk-card-header-icon"><i class="bi bi-person-badge"></i></div>
                        <div><h3>ID Verification</h3><p>Required for hotel check-in compliance</p></div>
                    </div>
                    <div class="bk-card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="bk-label">ID Proof Type <span style="color:var(--accent)">*</span></label>
                                <select name="id_proof_type" class="bk-input bk-select" required onchange="onIdTypeChange(this)">
                                    <option value="">Select ID Type</option>
                                    <?php foreach (['Aadhaar','Passport','Driving License','Voter ID','PAN Card'] as $t): ?>
                                        <option value="<?= $t ?>" <?= (($_POST['id_proof_type']??'')===$t)?'selected':'' ?>><?= $t ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="bk-label">ID Number <span style="color:var(--accent)">*</span></label>
                                <input type="text" name="id_proof_number" class="bk-input" placeholder="Enter your ID number"
                                       required maxlength="30" value="<?= sanitize($_POST['id_proof_number']??'') ?>"
                                       oninput="onIdNumberInput(this)">
                                <div id="idFormatHint" style="margin-top:6px;font-size:0.8rem;color:#8B7355;display:none"></div>
                                <div id="idValidationMsg" style="margin-top:4px;display:none"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="bk-label">ID Photo — Front <span style="color:var(--accent)">*</span></label>
                                <div class="bk-upload" id="frontUploadBox" onclick="document.getElementById('id_front').click()">
                                    <div id="frontPlaceholder" style="text-align:center;pointer-events:none;">
                                        <i class="bi bi-cloud-arrow-up" style="font-size:1.8rem;color:var(--accent);display:block;margin-bottom:6px;"></i>
                                        <div style="font-size:0.8rem;color:var(--text-muted);">Click to upload front</div>
                                        <div style="font-size:0.7rem;color:rgba(0,0,0,0.35);margin-top:3px;">JPG, PNG · Max 2MB</div>
                                    </div>
                                    <img id="frontPreview" src="" alt="" style="display:none;">
                                </div>
                                <div id="frontFileName" style="font-size:0.72rem;color:#28a745;margin-top:5px;display:none;"></div>
                                <input type="file" id="id_front" name="id_front" accept="image/jpeg,image/png,image/jpg" style="display:none" onchange="previewFile(this,'front')">
                            </div>
                            <div class="col-md-6">
                                <label class="bk-label">ID Photo — Back <span style="color:rgba(0,0,0,0.3);font-weight:400;font-size:0.7rem;">(optional)</span></label>
                                <div class="bk-upload bk-upload-secondary" id="backUploadBox" onclick="document.getElementById('id_back').click()">
                                    <div id="backPlaceholder" style="text-align:center;pointer-events:none;">
                                        <i class="bi bi-cloud-arrow-up" style="font-size:1.8rem;color:#aaa;display:block;margin-bottom:6px;"></i>
                                        <div style="font-size:0.8rem;color:var(--text-muted);">Click to upload back</div>
                                        <div style="font-size:0.7rem;color:rgba(0,0,0,0.3);margin-top:3px;">Optional</div>
                                    </div>
                                    <img id="backPreview" src="" alt="" style="display:none;">
                                </div>
                                <div id="backFileName" style="font-size:0.72rem;color:#28a745;margin-top:5px;display:none;"></div>
                                <input type="file" id="id_back" name="id_back" accept="image/jpeg,image/png,image/jpg" style="display:none" onchange="previewFile(this,'back')">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── SECTION 5: PAYMENT ── -->
                <div class="bk-card">
                    <div class="bk-card-header">
                        <div class="bk-card-header-icon"><i class="bi bi-credit-card"></i></div>
                        <div><h3>Payment Option</h3><p>Choose how you'd like to pay</p></div>
                    </div>
                    <div class="bk-card-body">
                        <div class="row g-3 mb-4">
                            <!-- Pay Online -->
                            <div class="col-md-6">
                                <div class="bk-pay-card" id="optOnline" onclick="selectPaymentOption('online')">
                                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
                                        <div class="bk-pay-emoji">💳</div>
                                        <div class="bk-pay-radio" id="radioOnline"><div class="dot"></div></div>
                                    </div>
                                    <div class="bk-pay-title">Pay Online Now</div>
                                    <div class="bk-pay-sub">UPI, Cards, Net Banking &amp; Wallets via Razorpay</div>
                                    <div class="bk-pay-badge" style="background:#d4edda;color:#155724;">
                                        <i class="bi bi-shield-check me-1"></i>Instant Confirmation
                                    </div>
                                </div>
                            </div>
                            <!-- Pay at Hotel -->
                            <div class="col-md-6">
                                <div class="bk-pay-card selected" id="optHotel" onclick="selectPaymentOption('hotel')">
                                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
                                        <div class="bk-pay-emoji">🏨</div>
                                        <div class="bk-pay-radio selected" id="radioHotel"><div class="dot" style="background:#fff;"></div></div>
                                    </div>
                                    <div class="bk-pay-title">Pay at Hotel</div>
                                    <div class="bk-pay-sub">Pay cash or card when you arrive at the hotel</div>
                                    <div class="bk-pay-badge" style="background:#fff3cd;color:#856404;">
                                        <i class="bi bi-clock me-1"></i>Pending Confirmation
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Policies -->
                        <div class="bk-policy">
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
                                <div style="width:32px;height:32px;background:linear-gradient(135deg,#C4943A,#D4AD5E);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="bi bi-info-circle-fill" style="color:#fff;font-size:0.88rem;"></i>
                                </div>
                                <span style="font-family:'Playfair Display',serif;font-weight:700;color:var(--primary);font-size:0.95rem;">Booking Policies</span>
                            </div>
                            <?php
                            $policies = [
                                ['bi-lightning-charge', 'Booking requests confirmed within <strong>30 minutes</strong>'],
                                ['bi-person-badge',     'Valid <strong>Government ID</strong> required at check-in'],
                                ['bi-receipt',          'GST <strong>(12%)</strong> is included in the total amount shown'],
                                ['bi-shield-check',     'Free cancellation within <strong>24 hours</strong> of booking confirmation'],
                            ];
                            foreach ($policies as $pol): ?>
                            <div class="bk-policy-item">
                                <div class="bk-policy-icon"><i class="bi <?= $pol[0] ?>"></i></div>
                                <span><?= $pol[1] ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Terms -->
                        <div class="bk-terms">
                            <input type="checkbox" id="agreeTerms" required style="width:18px;height:18px;accent-color:var(--accent);flex-shrink:0;cursor:pointer;">
                            <label for="agreeTerms" style="font-size:0.85rem;color:var(--text-muted);cursor:pointer;margin:0;">
                                I agree to the <a href="#" style="color:var(--accent);font-weight:600;">Terms &amp; Conditions</a> and <a href="#" style="color:var(--accent);font-weight:600;">Cancellation Policy</a>
                            </label>
                        </div>

                        <!-- Submit button -->
                        <button type="button" onclick="validateAndPay()" class="bk-submit bk-submit-hotel" id="payBtn">
                            <i class="bi bi-calendar-check"></i>
                            <span id="payBtnText">Request Booking (Pay at Hotel)</span>
                            <span id="payBtnAmount" style="opacity:0.8;font-weight:500;font-size:0.9em;"></span>
                        </button>
                        <p id="paySecureNote" style="text-align:center;font-size:0.73rem;color:var(--text-muted);margin-top:10px;margin-bottom:0;display:none;">
                            <i class="bi bi-lock-fill me-1" style="color:var(--accent);"></i>
                            256-bit SSL · Powered by <strong>Razorpay</strong>
                        </p>
                    </div>
                </div>

            </form>
        </div><!-- /col-lg-8 -->

        <!-- RIGHT: Sticky Summary (col-lg-4) -->
        <div class="col-lg-4" style="align-self:flex-start;position:sticky;top:80px;">
            <div class="bk-summary" id="bookingSummary" style="display:none;">
                <div class="bk-summary-title">
                    <i class="bi bi-receipt-cutoff"></i> Booking Summary
                </div>
                <div class="bk-sum-row"><span>Room</span><span id="summRoomType" style="font-family:'Playfair Display',serif;">—</span></div>
                <div class="bk-sum-row"><span>Check-In</span><span id="summCheckIn">—</span></div>
                <div class="bk-sum-row"><span>Check-Out</span><span id="summCheckOut">—</span></div>
                <div class="bk-sum-row"><span>Duration</span><span><span id="summNights">0</span> Night(s)</span></div>
                <div style="border-top:1px solid rgba(255,255,255,0.1);margin:12px 0;"></div>
                <div class="bk-sum-row"><span>Room Charges</span><span id="summRoomCharges">₹0.00</span></div>
                <div class="bk-sum-row"><span>GST (12%)</span><span id="summGST">₹0.00</span></div>
                <div class="bk-sum-row bk-sum-total"><span>Total</span><span id="summTotal">₹0.00</span></div>

                <!-- Why book here -->
                <div class="bk-why">
                    <div class="bk-why-item"><i class="bi bi-patch-check-fill"></i> Free cancellation within 24 hours</div>
                    <div class="bk-why-item"><i class="bi bi-clock-fill"></i> Confirmed within 30 minutes</div>
                    <div class="bk-why-item"><i class="bi bi-headset"></i> 24/7 reception support</div>
                    <div class="bk-why-item"><i class="bi bi-shield-lock-fill"></i> Secure &amp; encrypted booking</div>
                </div>
            </div>

            <!-- Contact card (shown before dates selected) -->
            <div id="contactCard" style="background:var(--white);border-radius:20px;padding:24px;border:1px solid var(--border);box-shadow:0 2px 16px rgba(59,26,10,0.06);">
                <h5 style="font-family:'Playfair Display',serif;color:var(--primary);margin-bottom:16px;font-size:1rem;">Need Help?</h5>
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
                    <div style="width:36px;height:36px;background:var(--accent-pale);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="bi bi-telephone-fill" style="color:var(--accent);"></i>
                    </div>
                    <div>
                        <div style="font-size:0.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;">Call Us</div>
                        <div style="font-weight:600;color:var(--primary);font-size:0.88rem;">0824-2389156</div>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
                    <div style="width:36px;height:36px;background:var(--accent-pale);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="bi bi-envelope-fill" style="color:var(--accent);"></i>
                    </div>
                    <div>
                        <div style="font-size:0.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;">Email</div>
                        <div style="font-weight:600;color:var(--primary);font-size:0.85rem;">enquiry@karavalilodge.com</div>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:12px;">
                    <div style="width:36px;height:36px;background:var(--accent-pale);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="bi bi-clock-fill" style="color:var(--accent);"></i>
                    </div>
                    <div>
                        <div style="font-size:0.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;">Reception</div>
                        <div style="font-weight:600;color:var(--primary);font-size:0.88rem;">Open 24/7</div>
                    </div>
                </div>
            </div>
        </div><!-- /col-lg-4 -->

    </div><!-- /row -->
    <?php endif; ?>

</div>
</section>
<script>
// ── Room card selection ──────────────────────────────────────────
window._selectedCapacity = 99; // default — no room selected yet

function selectRoom(roomId, price, label, capacity) {
    capacity = capacity || 6;
    window._selectedCapacity = capacity;

    const sel = document.getElementById('room_id');
    if (sel) {
        for (let i = 0; i < sel.options.length; i++) {
            if (sel.options[i].value === roomId) { sel.selectedIndex = i; break; }
        }
    }
    document.querySelectorAll('.bk-room-card').forEach(c => c.classList.remove('selected'));
    // Highlight the clicked card (user click) or find it programmatically (pre-selection)
    const clickedCard = (typeof event !== 'undefined' && event && event.currentTarget)
        ? event.currentTarget.closest('.bk-room-card')
        : document.querySelector(`.bk-room-card[onclick*="'${roomId}'"]`);
    if (clickedCard) clickedCard.classList.add('selected');
    const rt = document.getElementById('summRoomType');
    if (rt) rt.textContent = label;

    // Rebuild guest dropdowns to respect this room's capacity
    updateGuestDropdowns(capacity);
    recalcSummary();
}

function updateGuestDropdowns(maxCapacity) {
    const adultsEl   = document.getElementById('bk_adults');
    const childrenEl = document.getElementById('bk_children');
    if (!adultsEl || !childrenEl) return;

    const currentAdults = parseInt(adultsEl.value) || 1;

    // Rebuild adults options (1 to maxCapacity)
    adultsEl.innerHTML = '';
    for (let i = 1; i <= maxCapacity; i++) {
        const opt = document.createElement('option');
        opt.value = i;
        opt.textContent = i + ' Adult' + (i > 1 ? 's' : '');
        if (i === Math.min(currentAdults, maxCapacity)) opt.selected = true;
        adultsEl.appendChild(opt);
    }

    // Show capacity hint
    const hint = document.getElementById('bk_capacity_hint');
    const capText = document.getElementById('bk_cap_text');
    if (hint && capText) {
        capText.textContent = 'Max ' + maxCapacity + ' guest' + (maxCapacity > 1 ? 's' : '') + ' total';
        hint.style.display = 'block';
    }

    // Update children too
    updateChildrenOptions();
}

function updateChildrenOptions() {
    const adultsEl   = document.getElementById('bk_adults');
    const childrenEl = document.getElementById('bk_children');
    if (!adultsEl || !childrenEl) return;

    const adults      = parseInt(adultsEl.value) || 1;
    const maxChildren = Math.max(0, window._selectedCapacity - adults);
    const current     = parseInt(childrenEl.value) || 0;

    childrenEl.innerHTML = '';
    for (let i = 0; i <= maxChildren; i++) {
        const opt = document.createElement('option');
        opt.value = i;
        opt.textContent = i;
        if (i === Math.min(current, maxChildren)) opt.selected = true;
        childrenEl.appendChild(opt);
    }

    // Show/hide capacity full warning
    const warn = document.getElementById('bk_capacity_warn');
    if (warn) warn.style.display = (maxChildren === 0 && window._selectedCapacity < 99) ? 'block' : 'none';
}

// ── Room type filter ─────────────────────────────────────────────
function filterByType(type, tabEl) {
    document.querySelectorAll('.bk-type-tab').forEach(t => t.classList.remove('active'));
    if (tabEl) tabEl.classList.add('active');
    document.querySelectorAll('.room-card-col').forEach(col => {
        col.style.display = (!type || col.getAttribute('data-type') === type) ? '' : 'none';
    });
}

// ── Booking summary calculator ───────────────────────────────────
window._currentAdvance = 0;
window._currentTotal   = 0;

function recalcSummary() {
    const ci  = document.getElementById('check_in');
    const co  = document.getElementById('check_out');
    const sel = document.getElementById('room_id');
    const box = document.getElementById('bookingSummary');
    const cc  = document.getElementById('contactCard');
    if (!ci || !co || !sel || !box) return;

    const opt   = sel.options[sel.selectedIndex];
    const price = parseFloat(opt ? (opt.getAttribute('data-price') || 0) : 0);

    if (!ci.value || !co.value || !price) { box.style.display = 'none'; if(cc) cc.style.display='block'; return; }

    const d1 = new Date(ci.value), d2 = new Date(co.value);
    if (d2 <= d1) { box.style.display = 'none'; return; }

    const nights = Math.ceil((d2 - d1) / 86400000);
    const room   = nights * price;
    const gst    = room * 0.12;
    const total  = room + gst;
    const adv    = Math.round(total * ADVANCE_PCT * 100) / 100;

    const fmt     = n => '₹' + n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const fmtDate = s => new Date(s).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });

    document.getElementById('summNights').textContent      = nights;
    document.getElementById('summRoomCharges').textContent = fmt(room);
    document.getElementById('summGST').textContent         = fmt(gst);
    document.getElementById('summTotal').textContent       = fmt(total);
    document.getElementById('summCheckIn').textContent     = fmtDate(ci.value);
    document.getElementById('summCheckOut').textContent    = fmtDate(co.value);

    const rtEl = document.getElementById('summRoomType');
    if (rtEl && (rtEl.textContent === '—' || !rtEl.textContent.trim())) {
        rtEl.textContent = opt ? opt.text : '—';
    }

    box.style.display = 'block';
    if(cc) cc.style.display = 'none';

    window._currentTotal   = total;
    window._currentAdvance = adv;

    // Update pay button amount if online mode
    const mode = document.getElementById('payment_mode')?.value;
    if (mode === 'online') {
        const amt = document.getElementById('payBtnAmount');
        if (amt) amt.textContent = '— ' + fmt(adv);
    }
}

// ── Payment option selector ──────────────────────────────────────
function selectPaymentOption(mode) {
    document.getElementById('payment_mode').value = mode;
    const optOnline  = document.getElementById('optOnline');
    const optHotel   = document.getElementById('optHotel');
    const radioOnline = document.getElementById('radioOnline');
    const radioHotel  = document.getElementById('radioHotel');
    const payBtn     = document.getElementById('payBtn');
    const btnText    = document.getElementById('payBtnText');
    const btnAmt     = document.getElementById('payBtnAmount');
    const secureNote = document.getElementById('paySecureNote');

    if (mode === 'online') {
        optOnline.classList.add('selected');    optHotel.classList.remove('selected');
        radioOnline.classList.add('selected');  radioHotel.classList.remove('selected');
        radioOnline.querySelector('.dot').style.background = '#fff';
        radioHotel.querySelector('.dot').style.background  = 'transparent';
        if (btnText) btnText.textContent = 'Pay & Confirm Booking';
        payBtn.className = 'bk-submit bk-submit-online';
        payBtn.innerHTML = '<i class="bi bi-lock-fill"></i><span id="payBtnText">Pay &amp; Confirm Booking</span><span id="payBtnAmount" style="opacity:0.8;font-size:0.9em;">' + (window._currentAdvance > 0 ? '— ₹' + window._currentAdvance.toLocaleString('en-IN',{minimumFractionDigits:2}) : '') + '</span>';
        secureNote.style.display = 'block';
    } else {
        optHotel.classList.add('selected');     optOnline.classList.remove('selected');
        radioHotel.classList.add('selected');   radioOnline.classList.remove('selected');
        radioHotel.querySelector('.dot').style.background  = '#fff';
        radioOnline.querySelector('.dot').style.background = 'transparent';
        payBtn.className = 'bk-submit bk-submit-hotel';
        payBtn.innerHTML = '<i class="bi bi-calendar-check"></i><span id="payBtnText">Request Booking (Pay at Hotel)</span><span id="payBtnAmount" style="opacity:0.8;font-size:0.9em;"></span>';
        secureNote.style.display = 'none';
    }
}

// ── Validation popup ─────────────────────────────────────────────
function showPopup(message) {
    const existing = document.getElementById('validationPopup');
    if (existing) existing.remove();
    const popup = document.createElement('div');
    popup.id = 'validationPopup';
    popup.style.cssText = 'position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.55);backdrop-filter:blur(4px);';
    popup.innerHTML = `
        <div style="background:#fff;border-radius:20px;padding:40px 36px;max-width:380px;width:90%;text-align:center;box-shadow:0 24px 64px rgba(0,0,0,0.25);animation:popIn2 0.25s ease;">
            <style>@keyframes popIn2{from{transform:scale(0.85);opacity:0}to{transform:scale(1);opacity:1}}</style>
            <div style="width:60px;height:60px;background:#fff3cd;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:1.5rem;">
                <i class="bi bi-exclamation-triangle-fill" style="color:#f59e0b;"></i>
            </div>
            <h5 style="font-family:'Playfair Display',serif;color:#3B1A0A;margin-bottom:10px;">Required Field Missing</h5>
            <p style="color:#6b7280;font-size:0.9rem;line-height:1.6;margin-bottom:22px;">${message}</p>
            <button onclick="document.getElementById('validationPopup').remove()"
                    style="background:linear-gradient(135deg,#C4943A,#D4AD5E);color:#2A1007;border:none;border-radius:10px;padding:11px 32px;font-weight:700;cursor:pointer;font-size:0.95rem;">OK, Got It</button>
        </div>`;
    popup.addEventListener('click', e => { if (e.target === popup) popup.remove(); });
    document.body.appendChild(popup);
}

// ── ID photo preview ─────────────────────────────────────────────
function previewFile(input, side) {
    const file = input.files[0];
    if (!file) return;
    if (file.size > 2 * 1024 * 1024) { showPopup('File must be under 2MB.'); input.value=''; return; }
    const box = document.getElementById(side + 'UploadBox');
    const ph  = document.getElementById(side + 'Placeholder');
    const pre = document.getElementById(side + 'Preview');
    const fn  = document.getElementById(side + 'FileName');
    const reader = new FileReader();
    reader.onload = e => {
        pre.src = e.target.result; pre.style.display = 'block';
        ph.style.display = 'none';
        box.classList.add('uploaded');
        if (fn) { fn.textContent = '✓ ' + file.name; fn.style.display = 'block'; }
    };
    reader.readAsDataURL(file);
}

// ── Form validation ──────────────────────────────────────────────

// ── ID Number Client-side Validation ─────────────────────────────
const ID_RULES = {
    'Aadhaar':         { regex: /^\d{12}$|^\d{4} \d{4} \d{4}$/, hint: '12 digits — e.g. 1234 5678 9012', maxlen: 12 },
    'Passport':        { regex: /^[A-Z]\d{7}$/i,                    hint: '1 letter + 7 digits — e.g. A1234567', maxlen: 8 },
    'Driving License': { regex: /^[A-Z]{2}\d{13}$/i,               hint: 'State code + 13 digits — e.g. KA0120210012345', maxlen: 15 },
    'Voter ID':        { regex: /^[A-Z]{3}\d{7}$/i,                 hint: '3 letters + 7 digits — e.g. ABC1234567', maxlen: 10 },
    'PAN Card':        { regex: /^[A-Z]{5}\d{4}[A-Z]$/i,           hint: '5 letters + 4 digits + 1 letter — e.g. ABCDE1234F', maxlen: 10 },
};
function validateIdFormat(type, number) {
    const rule = ID_RULES[type];
    if (!rule) return null;
    return rule.regex.test(number.trim().toUpperCase()) ? null : ('Invalid ' + type + ' number. Format: ' + rule.hint);
}
function onIdTypeChange(selectEl) {
    const type   = selectEl.value;
    const form   = selectEl.closest('form');
    const numEl  = form.querySelector('[name="id_proof_number"]');
    const hintEl = document.getElementById('idFormatHint');
    const msgEl  = document.getElementById('idValidationMsg');
    const rule   = ID_RULES[type];
    if (numEl) {
        numEl.value = '';
        numEl.maxLength = rule ? rule.maxlen : 30;
        numEl.placeholder = rule ? rule.hint.split('—')[1]?.trim() || 'Enter ID number' : 'Enter your ID number';
        numEl.classList.remove('is-valid','is-invalid');
    }
    if (hintEl) { hintEl.style.display = rule ? 'block' : 'none'; hintEl.innerHTML = rule ? '<i class="bi bi-info-circle me-1"></i>' + rule.hint : ''; }
    if (msgEl)  msgEl.style.display = 'none';
}
function onIdNumberInput(inputEl) {
    const form  = inputEl.closest('form');
    const type  = form.querySelector('[name="id_proof_type"]')?.value;
    const msgEl = document.getElementById('idValidationMsg');
    const rule  = ID_RULES[type];
    if (!rule || !msgEl) return;

    inputEl.classList.remove('is-valid','is-invalid');
    msgEl.style.display = 'none';

    const val    = inputEl.value.trim();
    const digits = val.replace(/[^a-zA-Z0-9]/g, '').length;
    if (!val) return;

    const err = validateIdFormat(type, val);

    if (!err) {
        // Valid — show green
        inputEl.classList.add('is-valid');
        msgEl.style.display = 'block';
        msgEl.innerHTML = '<small style="color:#27ae60"><i class="bi bi-check-circle-fill me-1"></i>Valid ' + type + ' number ✓</small>';
    } else if (digits >= rule.maxlen) {
        // Full length typed but wrong — show red
        inputEl.classList.add('is-invalid');
        msgEl.style.display = 'block';
        msgEl.innerHTML = '<small style="color:#dc3545"><i class="bi bi-x-circle-fill me-1"></i>' + err + '</small>';
    }
    // Still typing → no feedback
}

function validateForm() {
    const form       = document.getElementById('bookingForm');
    const name       = form.querySelector('[name="guest_name"]').value.trim();
    const mobile     = form.querySelector('[name="mobile"]').value.trim();
    const roomId     = document.getElementById('room_id')?.value;
    const checkIn    = form.querySelector('[name="check_in"]').value.trim();
    const checkOut   = form.querySelector('[name="check_out"]').value.trim();
    const ciTime     = form.querySelector('[name="checkin_time"]')?.value.trim();
    const coTime     = form.querySelector('[name="checkout_time"]')?.value.trim();
    const idType     = form.querySelector('[name="id_proof_type"]')?.value.trim();
    const idNum      = form.querySelector('[name="id_proof_number"]')?.value.trim();
    const idFront    = document.getElementById('id_front');
    const agreed     = document.getElementById('agreeTerms').checked;

    if (!name)   { showPopup('Please enter your full name.'); return false; }
    if (!mobile || mobile.length < 10) { showPopup('Please enter a valid 10-digit mobile number.'); return false; }
    if (!roomId) { showPopup('Please select a room from the list above.'); return false; }
    if (!checkIn || !checkOut) { showPopup('Please select both check-in and check-out dates.'); return false; }

    // Capacity check — only if a room is actually selected
    if (window._selectedCapacity < 99) {
        const totalGuests = (parseInt(document.getElementById('bk_adults')?.value)||0) + (parseInt(document.getElementById('bk_children')?.value)||0);
        if (totalGuests > window._selectedCapacity) {
            showPopup(`This room fits up to <strong>${window._selectedCapacity} guests</strong>.<br>You've selected ${totalGuests}. Please reduce guests or choose a larger room.`);
            return false;
        }
    }
    if (!ciTime) { showPopup('Please enter your preferred check-in time.<br><small>e.g. 2:00 PM, after noon, 3:00 PM</small>'); document.getElementById('checkin_time_field')?.focus(); return false; }
    if (!coTime) { showPopup('Please enter your preferred check-out time.<br><small>e.g. 10:00 AM, 11:00 AM, noon</small>'); document.getElementById('checkout_time_field')?.focus(); return false; }
    if (!idType) { showPopup('Please select your ID Proof type.'); return false; }
    if (!idNum)  { showPopup('Please enter your ID Proof number.'); return false; }
    const idFmtErr = validateIdFormat(idType, idNum);
    if (idFmtErr) { showPopup('<i class="bi bi-exclamation-triangle-fill me-2"></i>' + idFmtErr); return false; }
    if (!idFront?.files?.length) {
        const box = document.getElementById('frontUploadBox');
        if (box) { box.classList.remove('uploaded'); box.style.borderColor='#dc3545'; box.style.borderStyle='solid'; box.style.background='#fff5f5'; box.scrollIntoView({behavior:'smooth',block:'center'}); }
        showPopup('Please upload the front photo of your ID proof.'); return false;
    }
    if (!agreed) { showPopup('Please agree to the Terms &amp; Conditions to continue.'); return false; }
    return true;
}

// ── Main: validate → pay or submit ──────────────────────────────
async function validateAndPay() {
    if (!validateForm()) return;
    const mode = document.getElementById('payment_mode').value;
    const btn  = document.getElementById('payBtn');

    if (mode === 'hotel') {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting booking...';
        document.getElementById('bookingForm').submit();
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating order...';

    const form      = document.getElementById('bookingForm');
    const guestName = form.querySelector('[name="guest_name"]').value.trim();
    const mobile    = form.querySelector('[name="mobile"]').value.trim();
    const email     = form.querySelector('[name="email"]').value.trim();
    const amountPaise = Math.round((window._currentAdvance || 0) * 100);

    if (amountPaise <= 0) { showPopup('Please select check-in and check-out dates first.'); resetPayBtn(); return; }

    try {
        const orderRes = await fetch('<?= SITE_URL ?>/pages/razorpay-order.php?action=create_order', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ amount_paise: amountPaise, receipt: 'rcpt_' + Date.now(), notes: {guest: guestName, mobile} })
        });
        let orderData;
        try { orderData = await orderRes.json(); } catch(e) {
            showPopup('Server error. Check XAMPP is running.<br><small>HTTP ' + orderRes.status + '</small>'); resetPayBtn(); return;
        }
        if (!orderData.success || !orderData.order?.id) {
            showPopup('Could not create payment order.<br><small>' + (orderData.error || '') + '</small>'); resetPayBtn(); return;
        }

        const rzp = new Razorpay({
            key: '<?= RAZORPAY_KEY_ID ?>',
            amount: amountPaise, currency:'INR',
            name: 'Karavali Lodge',
            description: 'Room Booking — ' + form.querySelector('[name="check_in"]').value + ' to ' + form.querySelector('[name="check_out"]').value,
            image: '<?= SITE_URL ?>/images/Lodge_Logoo.png',
            order_id: orderData.order.id,
            prefill: { name: guestName, email, contact: mobile },
            theme: { color: '#C4943A' },
            modal: { ondismiss: () => { showPopup('Payment was cancelled. Please try again.'); resetPayBtn(); } },
            handler: async function(response) {
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Verifying payment...';
                const vr = await fetch('<?= SITE_URL ?>/pages/razorpay-order.php?action=verify_payment', {
                    method:'POST', headers:{'Content-Type':'application/json'},
                    body: JSON.stringify({ razorpay_order_id: response.razorpay_order_id, razorpay_payment_id: response.razorpay_payment_id, razorpay_signature: response.razorpay_signature })
                });
                const vd = await vr.json();
                if (!vd.success) { showPopup('Payment verification failed. ID: ' + response.razorpay_payment_id); resetPayBtn(); return; }
                document.getElementById('rzp_payment_id').value = response.razorpay_payment_id;
                document.getElementById('rzp_order_id').value   = response.razorpay_order_id;
                document.getElementById('rzp_signature').value  = response.razorpay_signature;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving booking...';
                document.getElementById('bookingForm').submit();
            }
        });
        rzp.on('payment.failed', r => { showPopup('Payment failed: ' + (r.error?.description || 'Unknown error')); resetPayBtn(); });
        rzp.open();
    } catch(err) {
        showPopup('Error: ' + (err.message || 'Unknown error')); resetPayBtn();
    }
}

function resetPayBtn() {
    const mode = document.getElementById('payment_mode').value;
    const btn  = document.getElementById('payBtn');
    btn.disabled = false;
    if (mode === 'hotel') {
        btn.className = 'bk-submit bk-submit-hotel';
        btn.innerHTML = '<i class="bi bi-calendar-check"></i><span id="payBtnText">Request Booking (Pay at Hotel)</span><span id="payBtnAmount" style="opacity:0.8;font-size:0.9em;"></span>';
    } else {
        btn.className = 'bk-submit bk-submit-online';
        const amt = window._currentAdvance > 0 ? '— ₹' + window._currentAdvance.toLocaleString('en-IN',{minimumFractionDigits:2}) : '';
        btn.innerHTML = '<i class="bi bi-lock-fill"></i><span id="payBtnText">Pay &amp; Confirm Booking</span><span id="payBtnAmount" style="opacity:0.8;font-size:0.9em;">' + amt + '</span>';
    }
}

const ADVANCE_PCT = <?= (float)RAZORPAY_ADVANCE_PERCENT ?>;

document.addEventListener('DOMContentLoaded', function () {
    const ci = document.getElementById('check_in');
    const co = document.getElementById('check_out');
    if (ci) ci.addEventListener('change', recalcSummary);
    if (co) co.addEventListener('change', recalcSummary);
    document.getElementById('room_id')?.addEventListener('change', recalcSummary);

    // When adults changes, recalculate max children
    // (also wired via onchange in HTML, this is just a safety backup)
    document.getElementById('bk_adults')?.addEventListener('change', () => { updateChildrenOptions(); recalcSummary(); });
    recalcSummary();

    // Pre-select room from URL (?room_id=...) — call selectRoom() fully so
    // _selectedCapacity is set, guest dropdowns are rebuilt, and the summary
    // sidebar populates automatically without needing a manual click.
    const preId = '<?= addslashes($preRoomId) ?>';
    if (preId) {
        const preCard = document.querySelector(`.bk-room-card[onclick*="'${preId}'"]`);
        if (preCard) {
            // Parse all args from the onclick attribute — avoids re-hardcoding them
            const match = (preCard.getAttribute('onclick') || '')
                .match(/selectRoom\('([^']+)',\s*([\d.]+),\s*'([^']+)',\s*(\d+)/);
            if (match) selectRoom(match[1], parseFloat(match[2]), match[3], parseInt(match[4]));
        }
    }

    // ── Step indicator: IntersectionObserver approach ─────────────
    const cards = document.querySelectorAll('.bk-card');
    const steps = document.querySelectorAll('.bk-step');
    const stepLines = document.querySelectorAll('.bk-step-line');

    function setStep(index) {
        steps.forEach((step, i) => {
            step.classList.remove('active', 'done');
            if (i < index)      step.classList.add('done');
            else if (i === index) step.classList.add('active');
        });
        stepLines.forEach((line, i) => {
            line.style.background = i < index ? 'var(--accent)' : 'var(--border)';
        });
    }

    // Track which card is most visible
    let currentSection = 0;
    const obs = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const idx = Array.from(cards).indexOf(entry.target);
                if (idx !== -1 && idx >= currentSection) {
                    currentSection = idx;
                    setStep(Math.min(idx, steps.length - 2));
                }
            }
        });
    }, { threshold: 0.25, rootMargin: '-80px 0px 0px 0px' });

    cards.forEach(card => obs.observe(card));
    setStep(0); // start on step 1

    // Remove JS sticky since parent col handles it now
});
</script>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const coPicker = flatpickr('#check_out', {
        dateFormat:'Y-m-d', disableMobile:true,
        minDate: new Date(new Date().setDate(new Date().getDate()+1)),
        onChange: recalcSummary
    });
    flatpickr('#check_in', {
        dateFormat:'Y-m-d', minDate:'today', disableMobile:true,
        onChange: function(sel) {
            if (sel[0]) {
                const nd = new Date(sel[0]); nd.setDate(nd.getDate()+1);
                coPicker.set('minDate', nd);
                if (coPicker.selectedDates[0] && coPicker.selectedDates[0] <= sel[0]) coPicker.setDate(nd);
            }
            recalcSummary();
        }
    });
});
</script>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>