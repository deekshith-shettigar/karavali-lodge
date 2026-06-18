<?php
$pageTitle = "Book Your Room - Karavali Lodge";
require_once __DIR__ . '/../includes/config.php';

// ── ID Number Format Validation ───────────────────────────────────
function validateIdNumber($type, $number) {
    $number = strtoupper(preg_replace('/\s+/', '', $number));
    $patterns = [
        'Aadhaar'         => '/^\d{12}$/',
        'Passport'        => '/^[A-Z]\d{7}$/',
        'Driving License' => '/^[A-Z]{2}\d{13}$/',
        'Voter ID'        => '/^[A-Z]{3}\d{7}$/',
        'PAN Card'        => '/^[A-Z]{5}\d{4}[A-Z]$/',
    ];
    $hints = [
        'Aadhaar'         => '12 digits (e.g. 123456789012)',
        'Passport'        => '1 letter + 7 digits (e.g. A1234567)',
        'Driving License' => 'State code + 13 digits (e.g. KA0120210012345)',
        'Voter ID'        => '3 letters + 7 digits (e.g. ABC1234567)',
        'PAN Card'        => '5 letters + 4 digits + 1 letter (e.g. ABCDE1234F)',
    ];
    if (!isset($patterns[$type])) return null;
    if (!preg_match($patterns[$type], $number)) {
        return 'Invalid ' . $type . ' number. Format: ' . ($hints[$type] ?? '');
    }
    return null;
}

// ── Razorpay safe fallbacks (in case config.php hasn't been updated yet) ──
if (!defined('RAZORPAY_KEY_ID'))          define('RAZORPAY_KEY_ID',          '');
if (!defined('RAZORPAY_KEY_SECRET'))      define('RAZORPAY_KEY_SECRET',       '');
if (!defined('RAZORPAY_CURRENCY'))        define('RAZORPAY_CURRENCY',         'INR');
if (!defined('RAZORPAY_ADVANCE_PERCENT')) define('RAZORPAY_ADVANCE_PERCENT',  1.00);

if (!isLoggedIn()) {
    redirect(SITE_URL . '/pages/login.php');
}

$db          = getDB();
$loggedGuest = getLoggedInGuest();

// Pre-fill from GET params (passed from rooms.php)
$preRoomId  = sanitize($_GET['room_id']   ?? '');
$preCheckIn = sanitize($_GET['check_in']  ?? date('Y-m-d'));
$preCheckOut= sanitize($_GET['check_out'] ?? date('Y-m-d', strtotime('+1 day')));

// Fetch the selected room
$room = null;
if ($preRoomId) {
    $stmt = $db->prepare("SELECT * FROM rooms WHERE id = ? AND status NOT IN ('Maintenance','Out of Order')");
    $stmt->execute([$preRoomId]);
    $room = $stmt->fetch();
}

// If no valid room, redirect to rooms list
if (!$room) {
    redirect(SITE_URL . '/pages/rooms.php');
}

// ── Razorpay keys check ──
$razorpayReady = (RAZORPAY_KEY_ID !== '' && RAZORPAY_KEY_ID !== 'rzp_test_XXXXXXXXXXXXXXXXXX');

// ── CSRF token ────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Handle form submission
$success   = false;
$errors    = [];
$requestNo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token first
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        http_response_code(403);
        die('Invalid request. Please reload the page and try again.');
    }

    $guestName      = sanitize($_POST['guest_name']        ?? '');
    $mobile         = sanitize($_POST['mobile']             ?? '');
    $email          = sanitize($_POST['email']              ?? '');
    $roomId         = sanitize($_POST['room_id']            ?? '');
    $checkIn        = sanitize($_POST['check_in']           ?? '');
    $checkOut       = sanitize($_POST['check_out']          ?? '');
    $numAdults      = (int)($_POST['num_adults']            ?? 1);
    $numChildren    = (int)($_POST['num_children']          ?? 0);
    $specialReq     = sanitize($_POST['special_requests']   ?? '');
    $checkinTime    = sanitize($_POST['checkin_time']        ?? '');
    $checkoutTime   = sanitize($_POST['checkout_time']       ?? '');
    $idProofType    = sanitize($_POST['id_proof_type']       ?? '');
    $idProofNo      = sanitize($_POST['id_proof_number']     ?? '');

    // ── Razorpay payment fields ──
    $razorpayPaymentId  = sanitize($_POST['razorpay_payment_id']  ?? '');
    $razorpayOrderId    = sanitize($_POST['razorpay_order_id']    ?? '');
    $razorpaySignature  = sanitize($_POST['razorpay_signature']   ?? '');
    $paymentMode        = sanitize($_POST['payment_mode']         ?? 'online'); // 'online' or 'hotel'
    $paymentVerified    = false;

    if (!$guestName) $errors[] = 'Guest name is required.';
    if (!$mobile)    $errors[] = 'Mobile number is required.';
    if (!$roomId)    $errors[] = 'Room not found.';
    if (!$checkIn)   $errors[] = 'Check-in date is required.';
    if (!$checkOut)  $errors[] = 'Check-out date is required.';
    if ($checkIn && strtotime($checkIn) < strtotime(date('Y-m-d')))
        $errors[] = 'Check-in date cannot be in the past.';
    if ($checkIn && $checkOut && $checkOut <= $checkIn)
        $errors[] = 'Check-out must be after check-in.';

    // Capacity validation
    if (empty($errors) && $roomId) {
        $capStmt = $db->prepare("SELECT capacity FROM rooms WHERE id = ?");
        $capStmt->execute([$roomId]);
        $capRow = $capStmt->fetch();
        if ($capRow) {
            $maxCap      = (int)$capRow['capacity'];
            $totalGuests = $numAdults + $numChildren;
            if ($totalGuests > $maxCap) {
                $errors[] = "This room fits a maximum of <strong>{$maxCap} guest" . ($maxCap > 1 ? 's' : '') . "</strong>. You selected {$totalGuests} guests (adults + children).";
            }
        }
    }
    if (!$idProofType)  $errors[] = 'Please select an ID proof type.';
    if (!$idProofNo)    $errors[] = 'Please enter your ID proof number.';
    if ($idProofType && $idProofNo) {
        $idErr = validateIdNumber($idProofType, $idProofNo);
        if ($idErr) $errors[] = $idErr;
    }
    if (!$checkinTime)  $errors[] = 'Preferred check-in time is required.';
    if (!$checkoutTime) $errors[] = 'Preferred check-out time is required.';

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

    // ── Verify Razorpay payment (only if paying online) ──────────
    if (empty($errors)) {
        if ($paymentMode === 'online') {
            if (!$razorpayPaymentId || !$razorpayOrderId || !$razorpaySignature) {
                $errors[] = 'Payment not completed. Please complete the online payment or choose "Pay at Hotel".';
            } else {
                $expectedSig = hash_hmac('sha256', $razorpayOrderId . '|' . $razorpayPaymentId, RAZORPAY_KEY_SECRET);
                if (!hash_equals($expectedSig, $razorpaySignature)) {
                    $errors[] = 'Payment verification failed. Please try again or contact support.';
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
            // Pay at hotel — no payment needed now
            $paymentVerified = true;
        }
    }

    // ── Handle ID photo uploads ──────────────────────────────────
    $idFrontPath = null;
    $idBackPath  = null;
    $uploadDir   = __DIR__ . '/../../uploads/id_proofs/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $maxSize = 2 * 1024 * 1024; // 2MB

    // Allowed MIME types mapped to safe extensions.
    // Extension is derived from real MIME — never from the filename —
    // so a file named shell.php sent as image/jpeg still gets .jpg.
    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/jpg'  => 'jpg',
        'image/png'  => 'png',
    ];

    /**
     * Validate an uploaded file using finfo_file() (reads actual magic bytes,
     * not the browser-supplied Content-Type which can be spoofed).
     * Returns the safe extension on success, or an error string on failure.
     */
    $validateUpload = function(array $file, bool $required, string $label) use ($maxSize, $allowedMimes): string|null {
        // Not uploaded
        if (empty($file['name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return $required ? "{$label} photo is required." : null;
        }

        // PHP upload error
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return "Upload error for {$label} photo (code {$file['error']}). Please try again.";
        }

        // File size
        if ($file['size'] > $maxSize) {
            return "{$label} photo must be under 2MB.";
        }

        // Real MIME from magic bytes — finfo_file() reads the actual file content
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!isset($allowedMimes[$realMime])) {
            return "{$label} photo must be a real JPG or PNG image. Detected: {$realMime}.";
        }

        // All good — return the safe extension derived from real MIME (never from filename)
        return $allowedMimes[$realMime]; // e.g. 'jpg' or 'png'
    };

    // Front photo — required
    if (empty($errors)) {
        $frontResult = $validateUpload($_FILES['id_front'] ?? [], true, 'ID Front');
        if ($frontResult === null || strlen($frontResult) > 4) {
            if ($frontResult !== null) $errors[] = $frontResult;
        } else {
            $safeExt     = $frontResult;
            $idFrontPath = 'uploads/id_proofs/' . uniqid('front_') . '.' . $safeExt;
            if (!move_uploaded_file($_FILES['id_front']['tmp_name'], __DIR__ . '/../../' . $idFrontPath)) {
                $errors[] = 'Failed to save ID Front photo. Please try again.';
                $idFrontPath = null;
            }
        }
    }

    // Back photo — optional
    if (!empty($_FILES['id_back']['name']) && ($_FILES['id_back']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $backResult = $validateUpload($_FILES['id_back'], false, 'ID Back');
        if ($backResult !== null && strlen($backResult) <= 4) {
            $safeExt    = $backResult;
            $idBackPath = 'uploads/id_proofs/' . uniqid('back_') . '.' . $safeExt;
            if (!move_uploaded_file($_FILES['id_back']['tmp_name'], __DIR__ . '/../../' . $idBackPath))
                $idBackPath = null;
        } elseif ($backResult !== null && strlen($backResult) > 4) {
            // Error on optional back photo — soft-fail, skip without blocking booking
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
            $roomData = $stmt->fetch();

        if (!$roomData) {
            $db->rollBack();
            $errors[] = 'Room not found. Please go back and select again.';
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
                AND    check_out > CURDATE()
                LIMIT 1
            ");
            $overlapBookings->execute([$roomId, $checkOut, $checkIn]);

            if ($overlapRequests->fetch() || $overlapBookings->fetch()) {
                $db->rollBack();
                $errors[] = 'Sorry, this room was just booked by another guest for your selected dates. Please choose different dates or another room.';
            } else {

            $nights       = (int) ceil((strtotime($checkOut) - strtotime($checkIn)) / 86400);
            $roomCharges  = $nights * (float)$roomData['price'];
            $tax          = $roomCharges * 0.12;
            $total        = $roomCharges + $tax;
            $advancePaid  = round($total * RAZORPAY_ADVANCE_PERCENT, 2);
            $requestNo    = 'REQ-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

            // Append time preferences to special requests
            $timeParts = [];
            if ($checkinTime)  $timeParts[] = "Preferred check-in: {$checkinTime}";
            if ($checkoutTime) $timeParts[] = "Preferred check-out: {$checkoutTime}";
            $timeNote       = implode(' | ', $timeParts);
            $fullSpecialReq = implode("\n", array_filter([$specialReq, $timeNote]));

            try {
                $payStatus   = ($paymentMode === 'online') ? 'Paid'    : 'Pay at Hotel';
                $bookingNote = ($paymentMode === 'hotel')
                    ? '[Pay at Hotel] ' . $fullSpecialReq
                    : $fullSpecialReq;

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
                    $roomData['room_type'], $roomId, $roomData['room_number'],
                    $checkIn, $checkOut,
                    $checkinTime, $checkoutTime,
                    $numAdults, $numChildren,
                    $bookingNote, $total,
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

                // Save ID proof record
                if ($idFrontPath) {
                    $proofId = generateId();
                    $db->prepare("
                        INSERT INTO id_proofs (id, guest_id, id_type, id_number, photo, photo_back, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ")->execute([
                        $proofId, $guestId, $idProofType, $idProofNo,
                        $idFrontPath, $idBackPath
                    ]);
                    $db->prepare("UPDATE guests SET id_proof_type=?, id_proof_number=? WHERE id=?")
                       ->execute([$idProofType, $idProofNo, $guestId]);
                }

                $success = true;

                // ── COMMIT — lock released, booking is now saved ──
                $db->commit();

            } catch (\PDOException $e) {
                $db->rollBack();
                $errors[] = 'Booking could not be saved. Please try again.';
            }
            } // end overlap check
        } // end room check
        } catch (\PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            $errors[] = 'A database error occurred. Please try again.';
        }
    }
}
$roomImages = [
    'Single'    => 'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=600&q=80',
    'Double'    => 'https://images.unsplash.com/photo-1611892440504-42a792e24d32?w=600&q=80',
    'Deluxe'    => 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=600&q=80',
    'Suite'     => 'https://images.unsplash.com/photo-1578683010236-d716f9a3f461?w=600&q=80',
    'Family'    => 'https://images.unsplash.com/photo-1595576508898-0ad5c879a061?w=600&q=80',
    'Dormitory' => 'https://images.unsplash.com/photo-1555854877-bab0e564b8d5?w=600&q=80',
];
$imgUrl    = $roomImages[$room['room_type']] ?? $roomImages['Single'];
$amenities = array_map('trim', explode(',', $room['amenities'] ?? ''));

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Flatpickr -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
/* ── Flatpickr Hotel Theme ── */
.flatpickr-calendar {
    font-family: 'Jost', sans-serif !important;
    box-shadow: 0 20px 60px rgba(59,26,10,0.25) !important;
    border-radius: 18px !important;
    border: 1px solid #E8D9C0 !important;
    overflow: hidden !important;
    width: 320px !important;
    padding: 0 !important;
    background: #fff !important;
}
.flatpickr-months {
    background: linear-gradient(135deg, #2A1007, #4a1e08) !important;
    border-radius: 18px 18px 0 0 !important;
    height: 56px !important;
    display: flex !important;
    align-items: center !important;
    position: relative !important;
}
.flatpickr-month {
    height: 56px !important;
    color: #fff !important;
    fill: #fff !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
}
.flatpickr-current-month {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 4px !important;
    padding: 0 !important;
    height: 56px !important;
    font-size: 1rem !important;
}
/* Month dropdown — styled as clickable pill */
.flatpickr-current-month .flatpickr-monthDropdown-months {
    -webkit-appearance: none !important;
    appearance: none !important;
    background: rgba(255,255,255,0.12) !important;
    border: 1px solid rgba(196,148,58,0.5) !important;
    border-radius: 8px !important;
    color: #fff !important;
    font-family: 'Playfair Display', Georgia, serif !important;
    font-size: 1rem !important;
    font-weight: 700 !important;
    font-style: italic !important;
    cursor: pointer !important;
    padding: 4px 28px 4px 10px !important;
    outline: none !important;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%23C4943A' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    background-position: right 8px center !important;
    background-size: 16px !important;
    transition: all 0.2s !important;
}
.flatpickr-current-month .flatpickr-monthDropdown-months:hover {
    background-color: rgba(196,148,58,0.25) !important;
    border-color: #C4943A !important;
}
.flatpickr-current-month .flatpickr-monthDropdown-months option {
    background: #3B1A0A !important;
    color: #fff !important;
    font-style: normal !important;
    font-family: 'Jost', sans-serif !important;
}
/* Year input wrapper */
.flatpickr-current-month .numInputWrapper {
    background: rgba(255,255,255,0.12) !important;
    border: 1px solid rgba(196,148,58,0.5) !important;
    border-radius: 8px !important;
    padding: 0 !important;
    width: 72px !important;
    transition: all 0.2s !important;
}
.flatpickr-current-month .numInputWrapper:hover {
    background: rgba(196,148,58,0.25) !important;
    border-color: #C4943A !important;
}
.flatpickr-current-month input.cur-year {
    color: #D4AD5E !important;
    font-size: 0.95rem !important;
    font-weight: 700 !important;
    font-family: 'Jost', sans-serif !important;
    background: transparent !important;
    border: none !important;
    outline: none !important;
    padding: 4px 6px 4px 10px !important;
    width: 100% !important;
    cursor: pointer !important;
}
/* Year up/down arrows */
.flatpickr-current-month .numInputWrapper span {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    border: none !important;
    right: 0 !important;
    width: 18px !important;
    opacity: 1 !important;
}
.flatpickr-current-month .numInputWrapper span.arrowUp {
    top: 0 !important;
    height: 50% !important;
    border-bottom: 1px solid rgba(196,148,58,0.3) !important;
}
.flatpickr-current-month .numInputWrapper span.arrowDown {
    top: 50% !important;
    height: 50% !important;
}
.flatpickr-current-month .numInputWrapper span::after {
    border-left-color: #C4943A !important;
    border-right-color: #C4943A !important;
}
.flatpickr-current-month .numInputWrapper span.arrowUp::after { border-bottom-color: #C4943A !important; }
.flatpickr-current-month .numInputWrapper span.arrowDown::after { border-top-color: #C4943A !important; }

/* Nav arrows */
.flatpickr-prev-month,
.flatpickr-next-month {
    fill: #C4943A !important;
    color: #C4943A !important;
    height: 56px !important;
    padding: 0 14px !important;
    display: flex !important;
    align-items: center !important;
    top: 0 !important;
    transition: background 0.2s !important;
}
.flatpickr-prev-month:hover,
.flatpickr-next-month:hover { background: rgba(196,148,58,0.2) !important; }
.flatpickr-prev-month svg,
.flatpickr-next-month svg { width: 14px !important; height: 14px !important; fill: #C4943A !important; }
.flatpickr-prev-month:hover svg,
.flatpickr-next-month:hover svg { fill: #D4AD5E !important; }

/* Hint bar */
.flatpickr-months::after {
    content: 'Click month/year to change  ·  Use ‹ › to navigate';
    position: absolute;
    bottom: -20px;
    left: 0; right: 0;
    text-align: center;
    font-size: 0.6rem;
    color: rgba(196,148,58,0.7);
    font-family: 'Jost', sans-serif;
    letter-spacing: 0.3px;
    pointer-events: none;
}

/* Weekdays */
.flatpickr-weekdays {
    background: #FBF7F2 !important;
    border-bottom: 1px solid #EFE5D8 !important;
    height: 38px !important;
    margin-top: 20px !important;
}
.flatpickr-weekday {
    color: #C4943A !important;
    font-weight: 700 !important;
    font-size: 0.7rem !important;
    letter-spacing: 0.8px !important;
    text-transform: uppercase !important;
}
/* Days */
.flatpickr-innerContainer { background: #fff !important; }
.flatpickr-days { border: none !important; }
.dayContainer {
    padding: 8px 8px 10px !important;
    width: 100% !important;
    min-width: 100% !important;
    max-width: 100% !important;
}
.flatpickr-day {
    border-radius: 10px !important;
    color: #3B1A0A !important;
    font-size: 0.84rem !important;
    font-weight: 500 !important;
    height: 36px !important;
    line-height: 36px !important;
    max-width: 36px !important;
    border: 1.5px solid transparent !important;
    transition: all 0.15s !important;
    margin: 2px !important;
}
.flatpickr-day:hover { background: #FBF0DC !important; border-color: #E8D9C0 !important; }
.flatpickr-day.today {
    border-color: #C4943A !important;
    background: #FFF8EC !important;
    font-weight: 800 !important;
    color: #8B6914 !important;
}
.flatpickr-day.selected,
.flatpickr-day.selected:hover {
    background: linear-gradient(135deg, #C4943A, #D4AD5E) !important;
    border-color: #C4943A !important;
    color: #fff !important;
    font-weight: 700 !important;
    box-shadow: 0 3px 12px rgba(196,148,58,0.4) !important;
}
.flatpickr-day.disabled,
.flatpickr-day.prevMonthDay,
.flatpickr-day.nextMonthDay {
    color: #D5C5B5 !important;
    background: transparent !important;
    border-color: transparent !important;
}
.flatpickr-day.flatpickr-disabled,
.flatpickr-day.flatpickr-disabled:hover {
    color: #E0D5C8 !important;
    cursor: not-allowed !important;
}
</style>

<!-- Page Hero -->
<div class="page-hero">
    <div class="container page-hero-content">
        <div class="breadcrumb-hotel mb-3">
            <a href="<?= SITE_URL ?>/index.php">Home</a>
            <span class="sep">›</span>
            <a href="<?= SITE_URL ?>/pages/rooms.php">Rooms</a>
            <span class="sep">›</span>
            <span class="current">Book Room <?= sanitize($room['room_number']) ?></span>
        </div>
        <h1>Book Your Room</h1>
        <p>Complete your reservation for Room <?= sanitize($room['room_number']) ?> — <?= sanitize($room['room_type']) ?></p>
    </div>
</div>

<section class="section-pad" style="background:var(--cream)">
    <div class="container">

        <?php if ($success): ?>
        <!-- ── Success State ── -->
        <div style="max-width:580px;margin:0 auto;text-align:center;padding:60px 20px;">
            <div style="width:80px;height:80px;background:<?= $paymentMode === 'online' ? 'linear-gradient(135deg,#28a745,#20c997)' : 'linear-gradient(135deg,#C4943A,#D4AD5E)' ?>;
                        border-radius:50%;display:flex;align-items:center;justify-content:center;
                        margin:0 auto 24px;box-shadow:0 8px 24px rgba(40,167,69,.3);">
                <i class="bi bi-<?= $paymentMode === 'online' ? 'check-lg' : 'calendar-check' ?>" style="font-size:2.2rem;color:<?= $paymentMode === 'online' ? '#fff' : '#3B1A0A' ?>;"></i>
            </div>
            <h2 style="font-family:'Playfair Display',serif;color:var(--primary);margin-bottom:10px;">
                <?= $paymentMode === 'online' ? 'Booking Confirmed &amp; Paid!' : 'Booking Request Sent!' ?>
            </h2>
            <p style="font-family:'Cormorant Garamond',serif;font-style:italic;color:var(--text-muted);font-size:1.1rem;margin-bottom:8px;">
                <?= $paymentMode === 'online' ? 'Your payment was successful. Booking request number:' : 'Your booking request has been submitted. Request number:' ?>
            </p>
            <div style="font-size:1.4rem;font-weight:700;color:var(--accent);
                        background:#fff;border:2px dashed var(--accent);
                        border-radius:12px;padding:16px 28px;display:inline-block;margin-bottom:16px;">
                <?= sanitize($requestNo) ?>
            </div>
            <!-- Status badge -->
            <?php if ($paymentMode === 'online'): ?>
            <div style="display:inline-flex;align-items:center;gap:8px;background:#d4edda;
                        border:1px solid #c3e6cb;border-radius:50px;padding:6px 16px;
                        margin-bottom:24px;font-size:0.85rem;color:#155724;font-weight:600;display:block;">
                <i class="bi bi-shield-check-fill"></i>
                Payment Verified · <?= sanitize($razorpayPaymentId ?? '') ?>
            </div>
            <?php else: ?>
            <div style="display:inline-flex;align-items:center;gap:8px;background:#fff3cd;
                        border:1px solid #ffc107;border-radius:12px;padding:14px 20px;
                        margin-bottom:24px;font-size:0.88rem;color:#856404;text-align:left;max-width:400px;">
                <i class="bi bi-info-circle-fill" style="font-size:1.2rem;flex-shrink:0"></i>
                <div>
                    <strong>Payment due at check-in.</strong><br>
                    <span style="font-size:0.82rem;">Please carry cash or card. Our team will confirm your booking shortly.</span>
                </div>
            </div>
            <?php endif; ?>
            <p style="color:var(--text-muted);font-size:0.92rem;margin-bottom:32px;">
                You can track your booking status in <strong>My Bookings</strong>.
            </p>
            <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
                <a href="<?= SITE_URL ?>/pages/my-bookings.php" class="btn-accent-hotel" style="padding:12px 28px;border-radius:8px">
                    <i class="bi bi-calendar-check me-2"></i>View My Bookings
                </a>
                <a href="<?= SITE_URL ?>/pages/rooms.php"
                   style="padding:12px 28px;border-radius:8px;border:2px solid var(--accent);
                          color:var(--accent);font-weight:600;text-decoration:none;
                          display:inline-flex;align-items:center;">
                    <i class="bi bi-arrow-left me-2"></i>Back to Rooms
                </a>
            </div>
        </div>

        <?php else: ?>

        <div class="row g-5">

            <!-- ── LEFT: Booking Form ── -->
            <div class="col-lg-7">

                <?php if (!$razorpayReady): ?>
                <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:12px;padding:16px 20px;margin-bottom:20px;display:flex;align-items:flex-start;gap:12px;">
                    <i class="bi bi-exclamation-triangle-fill" style="color:#e6a817;font-size:1.3rem;flex-shrink:0;margin-top:2px"></i>
                    <div>
                        <strong style="color:#856404;font-size:0.92rem;">Payment Gateway Not Configured</strong>
                        <p style="color:#856404;font-size:0.83rem;margin:4px 0 0">
                            Razorpay keys are missing. Open <code>user/includes/config.php</code> and set
                            <code>RAZORPAY_KEY_ID</code> and <code>RAZORPAY_KEY_SECRET</code> to enable online payments.
                            Get your keys from <a href="https://dashboard.razorpay.com/app/keys" target="_blank" style="color:#856404;font-weight:600">dashboard.razorpay.com</a>.
                        </p>
                    </div>
                </div>
                <?php endif; ?>

                <div style="background:var(--white);border-radius:var(--radius-lg);overflow:hidden;
                            box-shadow:var(--shadow-md);border:1px solid var(--border)">

                    <div style="background:linear-gradient(135deg,var(--primary-dark),var(--primary));padding:28px 32px">
                        <h3 style="font-family:'Playfair Display',serif;color:var(--white);margin-bottom:4px">
                            <i class="bi bi-calendar-plus me-2"></i>Complete Your Booking
                        </h3>
                        <p style="color:rgba(255,255,255,.6);font-family:'Cormorant Garamond',serif;font-style:italic;margin:0">
                            Room <?= sanitize($room['room_number']) ?> · <?= sanitize($room['room_type']) ?> · <?= formatCurrency($room['price']) ?>/night
                        </p>
                    </div>

                    <div style="padding:32px">

                        <?php if (!empty($errors)): ?>
                        <div class="alert-hotel alert-error-hotel mb-4">
                            <i class="bi bi-exclamation-circle-fill"></i>
                            <div><?= implode('<br>', $errors) ?></div>
                        </div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data" id="bookingForm">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                            <input type="hidden" name="room_id" value="<?= sanitize($room['id']) ?>">
                            <!-- Razorpay payment fields — populated by JS after payment -->
                            <input type="hidden" name="razorpay_payment_id"  id="rzp_payment_id">
                            <input type="hidden" name="razorpay_order_id"    id="rzp_order_id">
                            <input type="hidden" name="razorpay_signature"   id="rzp_signature">
                            <!-- Payment mode: 'online' or 'hotel' -->
                            <input type="hidden" name="payment_mode" id="payment_mode" value="online">

                            <!-- Guest Details -->
                            <div style="font-family:'Playfair Display',serif;color:var(--primary);font-size:1rem;font-weight:600;
                                        margin-bottom:18px;padding-bottom:10px;border-bottom:2px solid var(--accent);
                                        display:flex;align-items:center;gap:8px;">
                                <i class="bi bi-person"></i> Guest Details
                            </div>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-group-label">Full Name *</label>
                                    <input type="text" name="guest_name" class="form-control-hotel"
                                           placeholder="Your full name" required
                                           value="<?= sanitize($loggedGuest['name'] ?? $_POST['guest_name'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-group-label">Mobile *</label>
                                    <input type="tel" name="mobile" class="form-control-hotel"
                                           placeholder="+91 XXXXX XXXXX" required
                                           value="<?= sanitize($loggedGuest['mobile'] ?? $_POST['mobile'] ?? '') ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-group-label">Email Address</label>
                                    <input type="email" name="email" class="form-control-hotel"
                                           placeholder="your@email.com"
                                           value="<?= sanitize($loggedGuest['email'] ?? $_POST['email'] ?? '') ?>">
                                </div>
                            </div>

                            <!-- Stay Details -->
                            <div style="font-family:'Playfair Display',serif;color:var(--primary);font-size:1rem;font-weight:600;
                                        margin-bottom:18px;padding-bottom:10px;border-bottom:2px solid var(--accent);
                                        display:flex;align-items:center;gap:8px;">
                                <i class="bi bi-calendar-range"></i> Stay Details
                            </div>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-group-label">Check-In Date *</label>
                                    <input type="text" id="check_in" name="check_in" class="form-control-hotel"
                                           placeholder="Select date" required readonly
                                           value="<?= sanitize($_POST['check_in'] ?? $preCheckIn) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-group-label">Check-Out Date *</label>
                                    <input type="text" id="check_out" name="check_out" class="form-control-hotel"
                                           placeholder="Select date" required readonly
                                           value="<?= sanitize($_POST['check_out'] ?? $preCheckOut) ?>">
                                </div>
                                <!-- Check-In Time -->
                                <div class="col-md-6">
                                    <label class="form-group-label">Preferred Check-In Time <span style="color:var(--accent)">*</span></label>
                                    <div style="position:relative">
                                        <input type="text" name="checkin_time" id="checkin_time_field" class="form-control-hotel"
                                               placeholder="e.g. 2:00 PM, 3:00 PM, after noon"
                                               style="padding-left:38px" required
                                               value="<?= sanitize($_POST['checkin_time'] ?? '') ?>">
                                        <i class="bi bi-clock" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--accent);pointer-events:none;font-size:0.95rem"></i>
                                    </div>
                                    <small style="font-size:0.72rem;color:var(--text-muted);margin-top:4px;display:block">
                                        <i class="bi bi-info-circle me-1"></i>Required — enter your preferred arrival time
                                    </small>
                                </div>
                                <!-- Check-Out Time -->
                                <div class="col-md-6">
                                    <label class="form-group-label">Preferred Check-Out Time <span style="color:var(--accent)">*</span></label>
                                    <div style="position:relative">
                                        <input type="text" name="checkout_time" id="checkout_time_field" class="form-control-hotel"
                                               placeholder="e.g. 10:00 AM, 11:00 AM, noon"
                                               style="padding-left:38px" required
                                               value="<?= sanitize($_POST['checkout_time'] ?? '') ?>">
                                        <i class="bi bi-clock" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--accent);pointer-events:none;font-size:0.95rem"></i>
                                    </div>
                                    <small style="font-size:0.72rem;color:var(--text-muted);margin-top:4px;display:block">
                                        <i class="bi bi-info-circle me-1"></i>Required — enter your preferred departure time
                                    </small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-group-label">Adults</label>
                                    <select name="num_adults" id="numAdults" class="form-select-hotel" onchange="updateChildrenMax()">
                                        <?php for ($i = 1; $i <= ($room['capacity'] ?? 4); $i++): ?>
                                        <option value="<?= $i ?>" <?= (($_POST['num_adults'] ?? 1) == $i) ? 'selected' : '' ?>>
                                            <?= $i ?> Adult<?= $i > 1 ? 's' : '' ?>
                                        </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-group-label">Children</label>
                                    <select name="num_children" id="numChildren" class="form-select-hotel">
                                        <?php
                                        $capacity    = (int)($room['capacity'] ?? 4);
                                        $selAdults   = (int)($_POST['num_adults']   ?? 1);
                                        $selChildren = (int)($_POST['num_children'] ?? 0);
                                        $maxChildren = max(0, $capacity - $selAdults);
                                        for ($i = 0; $i <= $maxChildren; $i++):
                                        ?>
                                        <option value="<?= $i ?>" <?= ($selChildren == $i) ? 'selected' : '' ?>>
                                            <?= $i ?> <?= $i == 1 ? 'Child' : 'Children' ?>
                                        </option>
                                        <?php endfor; ?>
                                    </select>
                                    <small style="font-size:0.72rem;color:var(--text-muted);margin-top:4px;display:block">
                                        <i class="bi bi-info-circle me-1"></i>Max capacity: <?= $capacity ?> guest<?= $capacity > 1 ? 's' : '' ?> total
                                    </small>
                                </div>
                                <!-- Capacity warning -->
                                <div class="col-12" id="capacityWarn" style="display:none">
                                    <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:10px 14px;font-size:0.83rem;color:#856404;display:flex;align-items:center;gap:8px">
                                        <i class="bi bi-exclamation-triangle-fill" style="color:#f59e0b;flex-shrink:0"></i>
                                        Room capacity is full (<?= $capacity ?> guest<?= $capacity > 1 ? 's' : '' ?> max). No children can be added.
                                    </div>
                                </div>
                                <div class="col-12">
                                    <!-- ── ID Proof Section ── -->
                                    <div style="border-top:2px solid var(--accent);padding-top:20px;margin-top:8px;margin-bottom:20px;">
                                        <div style="font-family:'Playfair Display',serif;color:var(--primary);font-size:1rem;font-weight:600;margin-bottom:16px;display:flex;align-items:center;gap:8px;">
                                            <i class="bi bi-person-badge" style="color:var(--accent)"></i> ID Verification
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-group-label">ID Proof Type <span style="color:var(--accent)">*</span></label>
                                                <select name="id_proof_type" class="form-select-hotel" required onchange="onIdTypeChange(this)">
                                                    <option value="">Select ID Type</option>
                                                    <?php foreach (['Aadhaar','Passport','Driving License','Voter ID','PAN Card'] as $t): ?>
                                                        <option value="<?= $t ?>" <?= (($_POST['id_proof_type'] ?? '') === $t) ? 'selected' : '' ?>><?= $t ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-group-label">ID Number <span style="color:var(--accent)">*</span></label>
                                                <input type="text" name="id_proof_number" class="form-control-hotel"
                                                       placeholder="Enter ID number" required maxlength="30"
                                                       value="<?= sanitize($_POST['id_proof_number'] ?? '') ?>"
                                                       oninput="onIdNumberInput(this)">
                                                <div id="idFormatHint" style="margin-top:6px;font-size:0.8rem;color:#8B7355;display:none"></div>
                                                <div id="idValidationMsg" style="margin-top:4px;display:none"></div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-group-label">ID Photo – Front <span style="color:var(--accent)">*</span></label>
                                                <div style="position:relative;">
                                                    <div id="frontUploadBox" onclick="document.getElementById('id_front').click()"
                                                         style="border:2px dashed var(--accent);border-radius:10px;height:130px;
                                                                display:flex;flex-direction:column;align-items:center;justify-content:center;
                                                                cursor:pointer;background:#FDFAF5;transition:all 0.3s;overflow:hidden;position:relative;">
                                                        <div id="frontPlaceholder" style="text-align:center;pointer-events:none;">
                                                            <i class="bi bi-cloud-upload" style="font-size:1.6rem;color:var(--accent);display:block;margin-bottom:6px"></i>
                                                            <div style="font-size:0.8rem;color:var(--text-muted)">Click to upload front of ID<br><span style="font-size:0.72rem">JPG, PNG · Max 2MB</span></div>
                                                        </div>
                                                        <img id="frontPreview" src="" alt=""
                                                             style="display:none;width:100%;height:100%;object-fit:cover;position:absolute;inset:0;">
                                                    </div>
                                                    <!-- Remove button -->
                                                    <button type="button" id="frontRemoveBtn" onclick="removePhoto('front')"
                                                            style="display:none;position:absolute;top:-8px;right:-8px;
                                                                   width:24px;height:24px;border-radius:50%;border:none;
                                                                   background:#dc3545;color:#fff;font-size:0.75rem;
                                                                   cursor:pointer;z-index:10;line-height:1;
                                                                   box-shadow:0 2px 6px rgba(0,0,0,0.25);
                                                                   display:none;align-items:center;justify-content:center;">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                </div>
                                                <div id="frontFileName" style="font-size:0.75rem;color:#28a745;margin-top:5px;display:none;"></div>
                                                <input type="file" id="id_front" name="id_front" accept="image/jpeg,image/png,image/jpg" style="display:none" onchange="previewFile(this,'front')">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-group-label">ID Photo – Back <span style="color:#aaa;font-weight:400;font-size:0.75rem">(optional)</span></label>
                                                <div style="position:relative;">
                                                    <div id="backUploadBox" onclick="document.getElementById('id_back').click()"
                                                         style="border:2px dashed #ddd;border-radius:10px;height:130px;
                                                                display:flex;flex-direction:column;align-items:center;justify-content:center;
                                                                cursor:pointer;background:#FDFAF5;transition:all 0.3s;overflow:hidden;position:relative;">
                                                        <div id="backPlaceholder" style="text-align:center;pointer-events:none;">
                                                            <i class="bi bi-cloud-upload" style="font-size:1.6rem;color:#aaa;display:block;margin-bottom:6px"></i>
                                                            <div style="font-size:0.8rem;color:var(--text-muted)">Click to upload back of ID<br><span style="font-size:0.72rem">JPG, PNG · Max 2MB · Optional</span></div>
                                                        </div>
                                                        <img id="backPreview" src="" alt=""
                                                             style="display:none;width:100%;height:100%;object-fit:cover;position:absolute;inset:0;">
                                                    </div>
                                                    <!-- Remove button -->
                                                    <button type="button" id="backRemoveBtn" onclick="removePhoto('back')"
                                                            style="display:none;position:absolute;top:-8px;right:-8px;
                                                                   width:24px;height:24px;border-radius:50%;border:none;
                                                                   background:#dc3545;color:#fff;font-size:0.75rem;
                                                                   cursor:pointer;z-index:10;line-height:1;
                                                                   box-shadow:0 2px 6px rgba(0,0,0,0.25);
                                                                   display:none;align-items:center;justify-content:center;">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                </div>
                                                <div id="backFileName" style="font-size:0.75rem;color:#28a745;margin-top:5px;display:none;"></div>
                                                <input type="file" id="id_back" name="id_back" accept="image/jpeg,image/png,image/jpg" style="display:none" onchange="previewFile(this,'back')">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label class="form-group-label">Special Requests</label>
                                    <textarea name="special_requests" class="form-control-hotel" rows="3"
                                              placeholder="Early check-in, extra pillows, dietary needs..."
                                              style="resize:vertical"><?= sanitize($_POST['special_requests'] ?? '') ?></textarea>
                                </div>
                            </div>

                            <!-- Booking Policies -->
                            <div style="background:linear-gradient(135deg,#fdfaf5,#faf4e8);border-radius:14px;padding:20px 22px;margin-bottom:22px;border:1px solid #ede0c8;">
                                <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
                                    <div style="width:34px;height:34px;background:linear-gradient(135deg,#C4943A,#D4AD5E);border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                        <i class="bi bi-info-circle-fill" style="color:#fff;font-size:0.95rem"></i>
                                    </div>
                                    <span style="font-family:'Playfair Display',serif;font-weight:700;color:#3B1A0A;font-size:1rem">Booking Policies</span>
                                </div>
                                <ul style="margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:9px">
                                    <?php
                                    $policies = [
                                        ['bi-lightning-charge','Booking requests confirmed within <strong>30 minutes</strong>'],
                                        ['bi-person-badge',    'Valid <strong>Government ID</strong> required at check-in'],
                                        ['bi-receipt',         'GST <strong>(12%)</strong> is included in the total amount shown'],
                                        ['bi-shield-check',    'Free cancellation up to <strong>24 hours</strong> before check-in'],
                                    ];
                                    foreach ($policies as $p): ?>
                                    <li style="display:flex;align-items:flex-start;gap:10px;font-size:0.85rem;color:#6b5c45;line-height:1.5">
                                        <span style="width:22px;height:22px;border-radius:6px;background:rgba(196,148,58,0.15);display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px">
                                            <i class="bi <?= $p[0] ?>" style="color:#C4943A;font-size:0.72rem"></i>
                                        </span>
                                        <span><?= $p[1] ?></span>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>

                            <!-- Terms -->
                            <div class="d-flex align-items-center gap-2 mb-4">
                                <input type="checkbox" id="agreeTerms" class="form-check-input"
                                       style="width:18px;height:18px;border-color:var(--accent);flex-shrink:0">
                                <label for="agreeTerms" style="font-size:0.88rem;color:var(--text-muted)">
                                    I agree to the <a href="#" style="color:var(--accent)">Terms & Conditions</a> and
                                    <a href="#" style="color:var(--accent)">Cancellation Policy</a>
                                </label>
                            </div>

                            <!-- Payment Options -->
                            <div style="margin-bottom:8px">
                                <div style="font-family:'Playfair Display',serif;color:var(--primary);font-size:0.95rem;font-weight:600;margin-bottom:14px;display:flex;align-items:center;gap:8px;">
                                    <i class="bi bi-credit-card" style="color:var(--accent)"></i> Choose Payment Option
                                </div>
                                <div class="row g-3">
                                    <!-- Option 1: Pay Online Now -->
                                    <div class="col-md-6">
                                        <div id="optOnline" onclick="selectPaymentOption('online')"
                                             style="border:2px solid var(--accent);border-radius:12px;padding:16px;cursor:pointer;background:linear-gradient(135deg,#fdfaf5,#faf4e8);transition:all 0.2s;position:relative;">
                                            <div style="position:absolute;top:10px;right:12px;">
                                                <div id="radioOnline" style="width:18px;height:18px;border-radius:50%;border:2px solid var(--accent);background:var(--accent);display:flex;align-items:center;justify-content:center;">
                                                    <div style="width:7px;height:7px;background:#fff;border-radius:50%;"></div>
                                                </div>
                                            </div>
                                            <div style="font-size:1.5rem;margin-bottom:6px;">💳</div>
                                            <div style="font-weight:700;color:var(--primary);font-size:0.95rem;margin-bottom:4px;">Pay Online Now</div>
                                            <div style="font-size:0.78rem;color:var(--text-muted);line-height:1.4;">Secure payment via Razorpay. UPI, Cards, Net Banking accepted.</div>
                                            <div style="margin-top:8px;font-size:0.75rem;background:#d4edda;color:#155724;padding:3px 8px;border-radius:50px;display:inline-block;font-weight:600;">
                                                <i class="bi bi-shield-check me-1"></i>Instant Confirmation
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Option 2: Pay at Hotel -->
                                    <div class="col-md-6">
                                        <div id="optHotel" onclick="selectPaymentOption('hotel')"
                                             style="border:2px solid var(--border);border-radius:12px;padding:16px;cursor:pointer;background:#fff;transition:all 0.2s;position:relative;">
                                            <div style="position:absolute;top:10px;right:12px;">
                                                <div id="radioHotel" style="width:18px;height:18px;border-radius:50%;border:2px solid var(--border);background:#fff;display:flex;align-items:center;justify-content:center;">
                                                    <div style="width:7px;height:7px;background:transparent;border-radius:50%;"></div>
                                                </div>
                                            </div>
                                            <div style="font-size:1.5rem;margin-bottom:6px;">🏨</div>
                                            <div style="font-weight:700;color:var(--primary);font-size:0.95rem;margin-bottom:4px;">Pay at Hotel</div>
                                            <div style="font-size:0.78rem;color:var(--text-muted);line-height:1.4;">Pay cash or card when you arrive. Subject to admin confirmation.</div>
                                            <div style="margin-top:8px;font-size:0.75rem;background:#fff3cd;color:#856404;padding:3px 8px;border-radius:50px;display:inline-block;font-weight:600;">
                                                <i class="bi bi-clock me-1"></i>Pending Confirmation
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Dynamic submit button — changes based on selection -->
                            <div id="submitArea" style="margin-top:16px;">
                                <button type="button" onclick="validateAndPay()" class="btn-accent-hotel w-100"
                                        id="payBtn"
                                        <?= !$razorpayReady ? 'disabled title="Configure Razorpay keys in config.php first"' : '' ?>
                                        style="padding:16px;font-size:1rem;justify-content:center;border-radius:10px;<?= !$razorpayReady ? 'opacity:0.5;cursor:not-allowed;' : 'opacity:1;' ?>">
                                    <i class="bi bi-lock-fill me-2"></i><?= $razorpayReady ? 'Pay &amp; Confirm Booking' : 'Payment Not Configured' ?>
                                    <span id="payBtnAmount" style="margin-left:8px;opacity:0.85;font-size:0.92em"></span>
                                </button>
                                <p id="paySecureNote" style="text-align:center;font-size:0.75rem;color:var(--text-muted);margin-top:10px;margin-bottom:0">
                                    <i class="bi bi-shield-lock me-1" style="color:var(--accent)"></i>
                                    Secured by <strong>Razorpay</strong> · 256-bit SSL Encryption
                                </p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- ── RIGHT: Room Info + Summary ── -->
            <div class="col-lg-5">

                <!-- Room Card -->
                <div style="background:var(--white);border-radius:var(--radius-lg);overflow:hidden;
                            box-shadow:var(--shadow-md);border:1px solid var(--border);margin-bottom:24px;">
                    <div style="position:relative;height:200px;overflow:hidden;">
                        <img src="<?= $imgUrl ?>" alt="<?= sanitize($room['room_type']) ?>"
                             style="width:100%;height:100%;object-fit:cover;">
                        <span style="position:absolute;top:12px;left:12px;background:var(--primary);
                                     color:#fff;font-size:0.78rem;font-weight:600;
                                     padding:4px 12px;border-radius:20px;">
                            <?= sanitize($room['room_type']) ?>
                        </span>
                    </div>
                    <div style="padding:20px 24px;">
                        <h4 style="font-family:'Playfair Display',serif;color:var(--primary);margin-bottom:8px;">
                            Room <?= sanitize($room['room_number']) ?>
                        </h4>
                        <div style="display:flex;gap:16px;font-size:0.83rem;color:var(--text-muted);margin-bottom:14px;">
                            <span><i class="bi bi-people me-1"></i><?= (int)$room['capacity'] ?> Guests</span>
                            <span><i class="bi bi-building me-1"></i>Floor <?= sanitize($room['floor'] ?? 'G') ?></span>
                        </div>
                        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px;">
                            <?php foreach (array_slice($amenities, 0, 6) as $am): ?>
                            <span style="background:var(--accent-pale);color:var(--primary);font-size:0.75rem;
                                         font-weight:500;padding:3px 10px;border-radius:20px;">
                                <i class="<?= getAmenityIcon($am) ?> me-1"></i><?= sanitize($am) ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <div style="font-family:'Playfair Display',serif;font-size:1.4rem;color:var(--accent);font-weight:700;">
                            <?= formatCurrency($room['price']) ?>
                            <span style="font-size:0.75rem;color:var(--text-muted);font-family:'Jost',sans-serif;font-weight:400;">/night</span>
                        </div>
                    </div>
                </div>

                <!-- Booking Summary -->
                <div class="booking-summary-box" id="bookingSummary" style="display:none">
                    <div class="booking-summary-title">
                        <i class="bi bi-receipt me-2"></i>Booking Summary
                    </div>
                    <div class="summary-row"><span>Room</span><span><?= sanitize($room['room_type']) ?> — <?= sanitize($room['room_number']) ?></span></div>
                    <div class="summary-row"><span>Check-In</span><span id="summCheckIn">—</span></div>
                    <div class="summary-row"><span>Check-Out</span><span id="summCheckOut">—</span></div>
                    <div class="summary-row"><span>Duration</span><span><span id="summNights">0</span> Night(s)</span></div>
                    <div class="summary-row"><span>Room Charges</span><span id="summRoomCharges">₹0.00</span></div>
                    <div class="summary-row"><span>GST (12%)</span><span id="summGST">₹0.00</span></div>
                    <div class="summary-row summary-total"><span>Total</span><span id="summTotal">₹0.00</span></div>
                    <div style="font-size:0.72rem;color:rgba(255,255,255,.45);margin-top:14px;
                                padding-top:12px;border-top:1px solid rgba(255,255,255,.1)">
                        <i class="bi bi-lock-fill me-1" style="color:#D4AD5E"></i>Secured via Razorpay · Full payment now
                    </div>
                    <div id="summAdvanceBox" style="display:none;margin-top:10px;background:rgba(196,148,58,0.15);border-radius:8px;padding:10px 12px;">
                        <div style="font-size:0.78rem;color:rgba(255,255,255,0.8);margin-bottom:4px;font-weight:600"><i class="bi bi-credit-card me-1"></i>Pay Now (Advance)</div>
                        <div style="font-family:Playfair Display,serif;font-size:1.1rem;color:#D4AD5E;font-weight:700" id="summAdvance">₹0.00</div>
                        <div style="font-size:0.7rem;color:rgba(255,255,255,0.5);margin-top:2px">Remaining balance due at hotel</div>
                    </div>
                </div><!-- /booking-summary-box -->

            </div><!-- /col-lg-5 -->
        </div><!-- /row -->
        <?php endif; ?>

    </div>
</section>

<!-- Razorpay Checkout SDK -->
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<!-- Flatpickr -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const roomPrice        = <?= (float)$room['price'] ?>;
    const advancePercent   = <?= (float)RAZORPAY_ADVANCE_PERCENT ?>;
    const rzpKeyId         = '<?= RAZORPAY_KEY_ID ?>';
    const apiUrl           = '<?= API_URL ?>';

    // ── Date pickers ──────────────────────────────────────────────
    const coPicker = flatpickr('#check_out', {
        dateFormat: 'Y-m-d',
        minDate: new Date(new Date().setDate(new Date().getDate() + 1)),
        disableMobile: true,
        onChange: recalc
    });

    const ciPicker = flatpickr('#check_in', {
        dateFormat: 'Y-m-d',
        minDate: 'today',
        disableMobile: true,
        onChange: function (sel) {
            if (sel[0]) {
                const nd = new Date(sel[0]);
                nd.setDate(nd.getDate() + 1);
                coPicker.set('minDate', nd);
                if (coPicker.selectedDates[0] && coPicker.selectedDates[0] <= sel[0]) {
                    coPicker.setDate(nd);
                }
            }
            recalc();
        }
    });

    // ── Booking summary recalculator ─────────────────────────────
    window._currentTotal   = 0;
    window._currentNights  = 0;
    window._currentAdvance = 0;

    function recalc() {
        const ci  = document.getElementById('check_in').value;
        const co  = document.getElementById('check_out').value;
        const box = document.getElementById('bookingSummary');
        if (!ci || !co) { box.style.display = 'none'; return; }

        const d1 = new Date(ci), d2 = new Date(co);
        if (d2 <= d1) { box.style.display = 'none'; return; }

        const nights  = Math.ceil((d2 - d1) / 86400000);
        const room    = nights * roomPrice;
        const gst     = room * 0.12;
        const total   = room + gst;
        const advance = Math.round(total * advancePercent * 100) / 100;
        const fmt     = n => '₹' + n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const fmtD    = s => new Date(s).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });

        document.getElementById('summNights').textContent      = nights;
        document.getElementById('summRoomCharges').textContent = fmt(room);
        document.getElementById('summGST').textContent         = fmt(gst);
        document.getElementById('summTotal').textContent       = fmt(total);
        document.getElementById('summCheckIn').textContent     = fmtD(ci);
        document.getElementById('summCheckOut').textContent    = fmtD(co);
        box.style.display = 'block';

        // Advance box (if partial payment)
        const advBox = document.getElementById('summAdvanceBox');
        const advEl  = document.getElementById('summAdvance');
        if (advancePercent < 1.0 && advBox && advEl) {
            advEl.textContent    = fmt(advance);
            advBox.style.display = 'block';
        }

        // Pay button amount label
        const payLabel = document.getElementById('payBtnAmount');
        const mode     = document.getElementById('payment_mode')?.value || 'online';
        if (payLabel && mode === 'online') payLabel.textContent = '— ' + fmt(advance);

        window._currentTotal   = total;
        window._currentNights  = nights;
        window._currentAdvance = advance;
    }

    window.recalc = recalc;
    recalc(); // initial calc

    // ── Initialize button appearance based on default selected option ──
    // 'Pay Online Now' is selected by default — apply full button styling on load
    selectPaymentOption('online');
});

// ── Capacity limiter ─────────────────────────────────────────────
const ROOM_CAPACITY = <?= (int)($room['capacity'] ?? 4) ?>;

function updateChildrenMax() {
    const adults      = parseInt(document.getElementById('numAdults').value) || 1;
    const childSelect = document.getElementById('numChildren');
    const currentVal  = parseInt(childSelect.value) || 0;
    const maxChildren = Math.max(0, ROOM_CAPACITY - adults);
    childSelect.innerHTML = '';
    for (let i = 0; i <= maxChildren; i++) {
        const opt = document.createElement('option');
        opt.value       = i;
        opt.textContent = i === 0 ? '0 Children' : i === 1 ? '1 Child' : i + ' Children';
        if (i === Math.min(currentVal, maxChildren)) opt.selected = true;
        childSelect.appendChild(opt);
    }
    document.getElementById('capacityWarn').style.display = (adults >= ROOM_CAPACITY) ? 'block' : 'none';
}

document.addEventListener('DOMContentLoaded', function() { updateChildrenMax(); });

// ── ID photo upload previews ─────────────────────────────────────
function previewFile(input, side) {
    const file = input.files[0];
    if (!file) return;
    if (file.size > 2 * 1024 * 1024) {
        showValidationPopup('File size must be under 2MB. Please choose a smaller image.');
        input.value = '';
        return;
    }
    const box         = document.getElementById(side + 'UploadBox');
    const placeholder = document.getElementById(side + 'Placeholder');
    const preview     = document.getElementById(side + 'Preview');
    const fileName    = document.getElementById(side + 'FileName');
    const removeBtn   = document.getElementById(side + 'RemoveBtn');
    const reader = new FileReader();
    reader.onload = function(e) {
        preview.src = e.target.result;
        preview.style.display = 'block';
        placeholder.style.display = 'none';
        box.style.borderColor = '#28a745';
        box.style.borderStyle = 'solid';
        box.style.background  = '#f0fff4';
        if (fileName)  { fileName.textContent = '✓ ' + file.name; fileName.style.display = 'block'; }
        if (removeBtn) { removeBtn.style.display = 'flex'; }
    };
    reader.readAsDataURL(file);
}

function removePhoto(side) {
    const input       = document.getElementById('id_' + side);
    const box         = document.getElementById(side + 'UploadBox');
    const placeholder = document.getElementById(side + 'Placeholder');
    const preview     = document.getElementById(side + 'Preview');
    const fileName    = document.getElementById(side + 'FileName');
    const removeBtn   = document.getElementById(side + 'RemoveBtn');
    input.value = '';
    preview.src = ''; preview.style.display = 'none';
    placeholder.style.display = 'flex';
    box.style.borderColor = side === 'front' ? 'var(--accent)' : '#ddd';
    box.style.borderStyle = 'dashed';
    box.style.background  = '#FDFAF5';
    if (fileName)  { fileName.textContent = ''; fileName.style.display = 'none'; }
    if (removeBtn) { removeBtn.style.display = 'none'; }
}

// ── Validation popup ─────────────────────────────────────────────
function showValidationPopup(message) {
    const existing = document.getElementById('validationPopup');
    if (existing) existing.remove();
    const popup = document.createElement('div');
    popup.id = 'validationPopup';
    popup.style.cssText = 'position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.55);backdrop-filter:blur(4px);';
    popup.innerHTML = `
        <div style="background:#fff;border-radius:20px;padding:40px 36px;max-width:380px;width:90%;text-align:center;box-shadow:0 24px 64px rgba(0,0,0,0.25);animation:popIn 0.25s ease;">
            <style>@keyframes popIn{from{transform:scale(0.85);opacity:0}to{transform:scale(1);opacity:1}}</style>
            <div style="width:64px;height:64px;background:#fff3cd;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:1.6rem;">
                <i class="bi bi-exclamation-triangle-fill" style="color:#f59e0b;"></i>
            </div>
            <h5 style="font-family:'Playfair Display',serif;color:#3B1A0A;font-size:1.1rem;margin-bottom:10px;">Required Field Missing</h5>
            <p style="color:#6b7280;font-size:0.92rem;line-height:1.6;margin-bottom:24px;">${message}</p>
            <button onclick="document.getElementById('validationPopup').remove()"
                    style="background:linear-gradient(135deg,#C4943A,#D4AD5E);color:#2A1007;border:none;border-radius:10px;padding:12px 36px;font-weight:700;font-size:0.95rem;cursor:pointer;">
                OK, Got It
            </button>
        </div>`;
    popup.addEventListener('click', e => { if (e.target === popup) popup.remove(); });
    document.body.appendChild(popup);
}

// ── Payment option selector ──────────────────────────────────────
function selectPaymentOption(mode) {
    document.getElementById('payment_mode').value = mode;

    const optOnline  = document.getElementById('optOnline');
    const optHotel   = document.getElementById('optHotel');
    const radioOnline = document.getElementById('radioOnline');
    const radioHotel  = document.getElementById('radioHotel');
    const payBtn     = document.getElementById('payBtn');
    const secureNote = document.getElementById('paySecureNote');
    const payLabel   = document.getElementById('payBtnAmount');

    if (mode === 'online') {
        // Highlight online option
        optOnline.style.border  = '2px solid var(--accent)';
        optOnline.style.background = 'linear-gradient(135deg,#fdfaf5,#faf4e8)';
        optHotel.style.border   = '2px solid var(--border)';
        optHotel.style.background = '#fff';
        // Radios
        radioOnline.style.background = 'var(--accent)';
        radioOnline.style.borderColor = 'var(--accent)';
        radioOnline.querySelector('div').style.background = '#fff';
        radioHotel.style.background = '#fff';
        radioHotel.style.borderColor = 'var(--border)';
        radioHotel.querySelector('div').style.background = 'transparent';
        // Button
        payBtn.innerHTML = '<i class="bi bi-lock-fill me-2"></i>Pay &amp; Confirm Booking<span id="payBtnAmount" style="margin-left:8px;opacity:0.85;font-size:0.92em">' + (window._currentAdvance > 0 ? ' — ₹' + window._currentAdvance.toLocaleString('en-IN',{minimumFractionDigits:2}) : '') + '</span>';
        payBtn.style.background = 'linear-gradient(135deg,var(--accent),var(--accent-light))';
        payBtn.style.color = 'var(--primary-dark)';
        payBtn.style.opacity = '1';
        payBtn.disabled = <?= $razorpayReady ? 'false' : 'true' ?>;
        secureNote.style.display = 'block';
    } else {
        // Highlight hotel option
        optHotel.style.border   = '2px solid var(--accent)';
        optHotel.style.background = 'linear-gradient(135deg,#fdfaf5,#faf4e8)';
        optOnline.style.border  = '2px solid var(--border)';
        optOnline.style.background = '#fff';
        // Radios
        radioHotel.style.background = 'var(--accent)';
        radioHotel.style.borderColor = 'var(--accent)';
        radioHotel.querySelector('div').style.background = '#fff';
        radioOnline.style.background = '#fff';
        radioOnline.style.borderColor = 'var(--border)';
        radioOnline.querySelector('div').style.background = 'transparent';
        // Button
        payBtn.innerHTML = '<i class="bi bi-calendar-check me-2"></i>Request Booking (Pay at Hotel)';
        payBtn.style.background = 'linear-gradient(135deg,var(--primary),var(--primary-light))';
        payBtn.style.color = 'var(--accent-light)';
        payBtn.disabled = false;
        secureNote.style.display = 'none';
    }
}

// ── Form field validation (runs before payment) ──────────────────
function validateForm() {
    const form      = document.getElementById('bookingForm');
    const guestName = form.querySelector('[name="guest_name"]').value.trim();
    const mobile    = form.querySelector('[name="mobile"]').value.trim();
    const checkIn   = form.querySelector('[name="check_in"]').value.trim();
    const checkOut  = form.querySelector('[name="check_out"]').value.trim();
    const ciTime    = form.querySelector('[name="checkin_time"]')?.value.trim();
    const coTime    = form.querySelector('[name="checkout_time"]')?.value.trim();
    const idType    = form.querySelector('[name="id_proof_type"]').value.trim();
    const idNumber  = form.querySelector('[name="id_proof_number"]').value.trim();
    const idFront   = document.getElementById('id_front');
    const agreed    = document.getElementById('agreeTerms').checked;

    if (!guestName)              { showValidationPopup('Please enter your full name.'); return false; }
    if (!mobile || mobile.length < 10) { showValidationPopup('Please enter a valid 10-digit mobile number.'); return false; }
    if (!checkIn || !checkOut)   { showValidationPopup('Please select both Check-In and Check-Out dates.'); return false; }
    if (window._currentTotal <= 0) { showValidationPopup('Please select valid Check-In and Check-Out dates.'); return false; }
    if (!ciTime) { showValidationPopup('Please enter your preferred check-in time.<br><small>e.g. 2:00 PM, 3:00 PM, after noon</small>'); document.getElementById('checkin_time_field')?.focus(); return false; }
    if (!coTime) { showValidationPopup('Please enter your preferred check-out time.<br><small>e.g. 10:00 AM, 11:00 AM, noon</small>'); document.getElementById('checkout_time_field')?.focus(); return false; }
    if (!idType)   { showValidationPopup('Please select your ID Proof type.'); return false; }
    if (!idNumber) { showValidationPopup('Please enter your ID Proof number.'); return false; }
    const idFmtErr = validateIdFormat(idType, idNumber);
    if (idFmtErr)  { showValidationPopup('<i class="bi bi-exclamation-triangle-fill me-2"></i>' + idFmtErr); return false; }
    if (!idFront.files || idFront.files.length === 0) {
        const box = document.getElementById('frontUploadBox');
        if (box) { box.style.borderColor = '#dc3545'; box.style.borderStyle = 'solid'; box.style.background = '#fff5f5'; box.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
        showValidationPopup('Please upload the <strong>front photo</strong> of your ID proof.<br><small>Accepted: JPG, PNG · Max 2MB</small>');
        return false;
    }
    if (!agreed) { showValidationPopup('Please agree to the Terms &amp; Conditions to continue.'); return false; }
    return true;
}


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
    const clean = number.trim().toUpperCase().replace(/\s+/g, ' ');
    return rule.regex.test(clean) ? null : ('Invalid ' + type + ' number. Format: ' + rule.hint);
}

function onIdTypeChange(selectEl) {
    const type   = selectEl.value;
    const numEl  = selectEl.closest('form').querySelector('[name="id_proof_number"]');
    const hintEl = document.getElementById('idFormatHint');
    const msgEl  = document.getElementById('idValidationMsg');
    const rule   = ID_RULES[type];

    if (numEl) {
        numEl.value       = '';
        numEl.maxLength   = rule ? rule.maxlen : 30;
        numEl.placeholder = rule ? (rule.hint.split('—')[1]?.trim() || 'Enter ID number') : 'Enter ID number';
        numEl.classList.remove('is-valid', 'is-invalid');
    }
    if (hintEl) {
        hintEl.style.display = rule ? 'block' : 'none';
        hintEl.innerHTML = rule ? '<i class="bi bi-info-circle me-1"></i>' + rule.hint : '';
    }
    if (msgEl) msgEl.style.display = 'none';
}

function onIdNumberInput(inputEl) {
    const form   = inputEl.closest('form');
    const type   = form.querySelector('[name="id_proof_type"]')?.value;
    const msgEl  = document.getElementById('idValidationMsg');
    const rule   = ID_RULES[type];
    if (!rule || !msgEl) return;

    inputEl.classList.remove('is-valid', 'is-invalid');
    msgEl.style.display = 'none';

    const val    = inputEl.value.trim();
    const digits = val.replace(/[^a-zA-Z0-9]/g, '').length; // count only alphanumeric
    if (!val) return;

    const err = validateIdFormat(type, val);

    if (!err) {
        // Format is valid — always show green
        inputEl.classList.add('is-valid');
        msgEl.style.display = 'block';
        msgEl.innerHTML = '<small style="color:#27ae60"><i class="bi bi-check-circle-fill me-1"></i>Valid ' + type + ' number ✓</small>';
    } else if (digits >= rule.maxlen) {
        // Customer has typed the full expected length but it's still wrong
        inputEl.classList.add('is-invalid');
        msgEl.style.display = 'block';
        msgEl.innerHTML = '<small style="color:#dc3545"><i class="bi bi-x-circle-fill me-1"></i>' + err + '</small>';
    }
    // While still typing (digits < maxlen and not yet valid) — show nothing
}

// ── Main: validate → branch on payment mode ──────────────────────
async function validateAndPay() {
    if (!validateForm()) return;

    const mode = document.getElementById('payment_mode').value;
    const btn  = document.getElementById('payBtn');

    // ── Pay at Hotel: just submit the form directly ───────────────
    if (mode === 'hotel') {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting booking...';
        document.getElementById('bookingForm').submit();
        return;
    }

    // ── Pay Online: Razorpay flow ─────────────────────────────────
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating order...';

    const form      = document.getElementById('bookingForm');
    const guestName = form.querySelector('[name="guest_name"]').value.trim();
    const mobile    = form.querySelector('[name="mobile"]').value.trim();
    const email     = form.querySelector('[name="email"]').value.trim();
    const amountPaise = Math.round(window._currentAdvance * 100);

    try {
        // Step 1: Create Razorpay order via local handler
        const orderRes = await fetch('<?= SITE_URL ?>/pages/razorpay-order.php?action=create_order', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                amount_paise: amountPaise,
                receipt:      'rcpt_' + Date.now(),
                notes:        { guest: guestName, mobile: mobile }
            })
        });

        let orderData;
        try {
            orderData = await orderRes.json();
        } catch(e) {
            showValidationPopup('Server returned an invalid response. Check that XAMPP is running and PHP errors are not outputting HTML.<br><small>HTTP ' + orderRes.status + '</small>');
            resetPayBtn(); return;
        }

        if (!orderData.success || !orderData.order?.id) {
            showValidationPopup('Could not create payment order.<br><small>' + (orderData.error || JSON.stringify(orderData.details || '')) + '</small>');
            resetPayBtn(); return;
        }

        // Step 2: Open Razorpay checkout
        const fmt = n => '₹' + n.toLocaleString('en-IN', { minimumFractionDigits: 2 });
        const options = {
            key:         '<?= RAZORPAY_KEY_ID ?>',
            amount:      amountPaise,
            currency:    'INR',
            name:        'Karavali Lodge',
            description: 'Room Booking — ' + document.getElementById('check_in').value + ' to ' + document.getElementById('check_out').value,
            image:       '<?= SITE_URL ?>/images/Lodge_Logoo.png',
            order_id:    orderData.order.id,
            prefill: {
                name:    guestName,
                email:   email,
                contact: mobile
            },
            theme: {
                color: '#C4943A'
            },
            modal: {
                ondismiss: function() {
                    showValidationPopup('Payment was cancelled. Please try again to complete your booking.');
                    resetPayBtn();
                }
            },
            handler: async function(response) {
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Verifying payment...';

                // Step 3: Verify signature server-side
                const verifyRes = await fetch('<?= SITE_URL ?>/pages/razorpay-order.php?action=verify_payment', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({
                        razorpay_order_id:   response.razorpay_order_id,
                        razorpay_payment_id: response.razorpay_payment_id,
                        razorpay_signature:  response.razorpay_signature
                    })
                });
                const verifyData = await verifyRes.json();

                if (!verifyData.success) {
                    showValidationPopup('Payment verification failed. Please contact support with Payment ID: ' + response.razorpay_payment_id);
                    resetPayBtn(); return;
                }

                // Step 4: Populate hidden fields and submit form
                document.getElementById('rzp_payment_id').value = response.razorpay_payment_id;
                document.getElementById('rzp_order_id').value   = response.razorpay_order_id;
                document.getElementById('rzp_signature').value  = response.razorpay_signature;

                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving booking...';
                document.getElementById('bookingForm').submit();
            }
        };

        const rzp = new Razorpay(options);
        rzp.on('payment.failed', function(resp) {
            showValidationPopup('Payment failed: ' + (resp.error?.description || 'Unknown error') + '<br><small>Error Code: ' + (resp.error?.code || '') + '</small>');
            resetPayBtn();
        });
        rzp.open();

    } catch (err) {
        console.error('Payment error:', err);
        showValidationPopup('Payment error: ' + (err.message || 'Unknown error') + '<br><small>Check browser console for details.</small>');
        resetPayBtn();
    }
}

function resetPayBtn() {
    const btn  = document.getElementById('payBtn');
    const mode = document.getElementById('payment_mode').value;
    btn.disabled = false;
    if (mode === 'hotel') {
        btn.innerHTML = '<i class="bi bi-calendar-check me-2"></i>Request Booking (Pay at Hotel)';
    } else {
        const fmt = n => n > 0 ? ' — ₹' + n.toLocaleString('en-IN', { minimumFractionDigits: 2 }) : '';
        btn.innerHTML = '<i class="bi bi-lock-fill me-2"></i>Pay &amp; Confirm Booking<span style="margin-left:8px;opacity:0.85;font-size:0.92em">' + fmt(window._currentAdvance) + '</span>';
    }
}
</script>