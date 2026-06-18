<?php
// =============================================
// Karavali Lodge — Notification Service
// karavali_lodge/user/includes/notifications.php
//
// Sends booking notifications via Gmail only.
//
// ── CREDENTIALS SETUP ────────────────────────────────────────────
// Credentials are loaded from the .env file located at the project
// root (karavali_lodge/.env) so they are never accessible via
// browser even if .htaccess fails.
//
// .env file contents (copy and fill in your values):
//   GMAIL_FROM_EMAIL=your@gmail.com
//   GMAIL_APP_PASSWORD=xxxx xxxx xxxx xxxx
//   ADMIN_NOTIFY_EMAIL=admin@gmail.com
// ─────────────────────────────────────────────────────────────────

// ── Load .env file ────────────────────────────────────────────────
function loadEnvFile(): void {
    // Try multiple locations — covers XAMPP, WAMP, Linux servers
    $candidates = [
        dirname(__DIR__, 4) . '/.env',   // 4 levels up from includes/
        dirname(__DIR__, 3) . '/.env',   // 3 levels up
        dirname(__DIR__, 2) . '/.env',   // 2 levels up (web root)
        $_SERVER['DOCUMENT_ROOT'] . '/../.env', // above htdocs/
        $_SERVER['DOCUMENT_ROOT'] . '/.env',    // htdocs/ itself (dev only)
    ];

    foreach ($candidates as $path) {
        if (!file_exists($path)) continue;

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            // Skip comments and blank lines
            if ($line === '' || str_starts_with($line, '#')) continue;
            if (!str_contains($line, '=')) continue;

            [$key, $value] = array_map('trim', explode('=', $line, 2));
            // Strip surrounding quotes if present — use double-quoted string
            // to avoid smart-quote corruption that breaks single-quoted syntax
            $value = trim($value, "\"'");
            if ($key !== '' && getenv($key) === false) {
                putenv("{$key}={$value}");
            }
        }
        break; // stop at first found .env
    }
}

loadEnvFile();

// ── Credential definitions — loaded from .env only ──────────────
// All values MUST exist in your .env file.
// If any are missing the app will log a warning and disable email
// rather than silently using hardcoded secrets.
$_missingEnv = [];
foreach (['GMAIL_FROM_EMAIL', 'GMAIL_APP_PASSWORD', 'ADMIN_NOTIFY_EMAIL'] as $_key) {
    if (!getenv($_key)) $_missingEnv[] = $_key;
}

if (!empty($_missingEnv)) {
    error_log('[notifications.php] Missing .env keys: ' . implode(', ', $_missingEnv) . '. Email notifications disabled.');
    define('NOTIFY_EMAIL_ENABLED', false);
    define('GMAIL_FROM_EMAIL',   '');
    define('GMAIL_FROM_NAME',    'Karavali Lodge');
    define('GMAIL_APP_PASSWORD', '');
    define('ADMIN_NOTIFY_EMAIL', '');
} else {
    define('GMAIL_FROM_EMAIL',   getenv('GMAIL_FROM_EMAIL'));
    define('GMAIL_FROM_NAME',    'Karavali Lodge');
    define('GMAIL_APP_PASSWORD', getenv('GMAIL_APP_PASSWORD'));
    define('ADMIN_NOTIFY_EMAIL', getenv('ADMIN_NOTIFY_EMAIL'));
    define('NOTIFY_EMAIL_ENABLED', true);
}

function sendBookingNotification(string $event, array $data): array {
    $results = [];
    $d = [
        'guestName'     => $data['guestName']     ?? $data['guest_name']    ?? 'Guest',
        'mobile'        => $data['mobile']         ?? '',
        'email'         => $data['email']          ?? '',
        'bookingNo'     => $data['bookingNo']      ?? $data['request_no']   ?? '',
        'roomNumber'    => $data['roomNumber']     ?? $data['room_number']   ?? '',
        'roomType'      => $data['roomType']       ?? $data['room_type']     ?? '',
        'checkIn'       => fmtDate($data['checkIn']  ?? $data['check_in']   ?? ''),
        'checkOut'      => fmtDate($data['checkOut'] ?? $data['check_out']  ?? ''),
        'totalAmount'   => fmtCurrency($data['totalAmount'] ?? $data['total_amount'] ?? 0),
        'paymentStatus' => $data['paymentStatus']  ?? $data['payment_status'] ?? 'Pending',
        'nights'        => calcNights(
                               $data['checkIn']  ?? $data['check_in']  ?? '',
                               $data['checkOut'] ?? $data['check_out'] ?? ''
                           ),
    ];

    [$guestEmail, $adminEmail] = buildEmailMessages($event, $d);

    if (NOTIFY_EMAIL_ENABLED && !empty($d['email'])) {
        $results['guest_email'] = sendGmail($d['email'], $guestEmail['subject'], $guestEmail['html']);
    }
    if (NOTIFY_EMAIL_ENABLED) {
        $results['admin_email'] = sendGmail(ADMIN_NOTIFY_EMAIL, $adminEmail['subject'], $adminEmail['html']);
    }

    logNotification($event, $d, $results);
    return $results;
}

function buildEmailMessages(string $event, array $d): array {
    $hotelName   = 'Karavali Lodge';
    $hotelPhone  = '0824-2389156';
    $brandColor  = '#3B1A0A';
    $accentColor = '#C4943A';

    $header = "
    <div style='font-family:\"Segoe UI\",Arial,sans-serif;max-width:600px;margin:0 auto;'>
    <div style='background:linear-gradient(135deg,#2A1007,#3B1A0A);padding:28px 32px;text-align:center;border-radius:12px 12px 0 0;'>
        <h1 style='color:{$accentColor};font-family:Georgia,serif;font-style:italic;font-size:1.5rem;margin:0 0 4px;'>{$hotelName}</h1>
        <p style='color:rgba(255,255,255,0.6);margin:0;font-size:0.85rem;'>K.S. Rao Road, Mangalore · {$hotelPhone}</p>
    </div>
    <div style='background:#fff;padding:32px;border:1px solid #e8d9c0;border-top:none;border-radius:0 0 12px 12px;'>";

    $footer = "
        <hr style='border:none;border-top:1px solid #e8e8e8;margin:28px 0 20px;'>
        <p style='color:#aaa;font-size:0.78rem;text-align:center;margin:0;'>
            &copy; " . date('Y') . " {$hotelName}, Mangalore. All rights reserved.<br>
            For queries: {$hotelPhone} &middot; enquiry@karavalilodge.com
        </p>
    </div></div>";

    $detailsTable = "
    <table style='width:100%;border-collapse:collapse;margin:20px 0;font-size:0.9rem;'>
        <tr style='background:#fdf8f2;'><td style='padding:10px 14px;border:1px solid #e8d9c0;font-weight:600;color:#666;width:40%'>Booking No</td><td style='padding:10px 14px;border:1px solid #e8d9c0;color:{$brandColor};font-weight:700;'>{$d['bookingNo']}</td></tr>
        <tr><td style='padding:10px 14px;border:1px solid #e8d9c0;font-weight:600;color:#666;'>Room</td><td style='padding:10px 14px;border:1px solid #e8d9c0;'>{$d['roomType']} — Room {$d['roomNumber']}</td></tr>
        <tr style='background:#fdf8f2;'><td style='padding:10px 14px;border:1px solid #e8d9c0;font-weight:600;color:#666;'>Check-In</td><td style='padding:10px 14px;border:1px solid #e8d9c0;'>{$d['checkIn']}</td></tr>
        <tr><td style='padding:10px 14px;border:1px solid #e8d9c0;font-weight:600;color:#666;'>Check-Out</td><td style='padding:10px 14px;border:1px solid #e8d9c0;'>{$d['checkOut']}</td></tr>
        <tr style='background:#fdf8f2;'><td style='padding:10px 14px;border:1px solid #e8d9c0;font-weight:600;color:#666;'>Duration</td><td style='padding:10px 14px;border:1px solid #e8d9c0;'>{$d['nights']} Night(s)</td></tr>
        <tr><td style='padding:10px 14px;border:1px solid #e8d9c0;font-weight:600;color:#666;'>Total Amount</td><td style='padding:10px 14px;border:1px solid #e8d9c0;font-weight:700;color:{$accentColor};'>{$d['totalAmount']}</td></tr>
        <tr style='background:#fdf8f2;'><td style='padding:10px 14px;border:1px solid #e8d9c0;font-weight:600;color:#666;'>Payment</td><td style='padding:10px 14px;border:1px solid #e8d9c0;'>{$d['paymentStatus']}</td></tr>
    </table>";

    switch ($event) {
        case 'booking_received':
            $g = $header . "
                <div style='text-align:center;margin-bottom:24px;'>
                    <div style='width:60px;height:60px;background:#fff3cd;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:1.8rem;margin-bottom:12px;'>&#x23F3;</div>
                    <h2 style='color:{$brandColor};font-family:Georgia,serif;font-size:1.3rem;margin:0 0 8px;'>Booking Request Received!</h2>
                    <p style='color:#666;margin:0;'>Dear <strong>{$d['guestName']}</strong>, your booking request has been submitted and is awaiting confirmation.</p>
                </div>
                {$detailsTable}
                <div style='background:#fff3cd;border:1px solid #ffc107;border-radius:10px;padding:14px 18px;margin-top:16px;font-size:0.86rem;color:#856404;'>
                    <strong>What happens next?</strong><br>
                    Our team will confirm your booking within <strong>30 minutes</strong>.
                </div>" . $footer;
            $a = $header . "
                <h2 style='color:{$brandColor};font-family:Georgia,serif;'>New Booking Request</h2>
                <p style='color:#666;margin-bottom:20px;'>From <strong>{$d['guestName']}</strong> ({$d['mobile']}).</p>
                {$detailsTable}
                <div style='text-align:center;margin-top:20px;'>
                    <a href='" . ADMIN_URL . "/index.php' style='background:linear-gradient(135deg,{$accentColor},#D4AD5E);color:{$brandColor};padding:12px 28px;border-radius:8px;font-weight:700;text-decoration:none;display:inline-block;'>View in Admin Panel →</a>
                </div>" . $footer;
            return [
                ['subject' => "Booking Request Received — {$d['bookingNo']} | {$hotelName}", 'html' => $g],
                ['subject' => "New Booking: {$d['guestName']} — {$d['bookingNo']}",          'html' => $a],
            ];

        case 'booking_confirmed':
            $g = $header . "
                <div style='text-align:center;margin-bottom:24px;'>
                    <div style='width:60px;height:60px;background:#d4edda;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:1.8rem;margin-bottom:12px;'>&#x2705;</div>
                    <h2 style='color:{$brandColor};font-family:Georgia,serif;font-size:1.3rem;margin:0 0 8px;'>Booking Confirmed!</h2>
                    <p style='color:#666;margin:0;'>Dear <strong>{$d['guestName']}</strong>, your room is confirmed. We look forward to welcoming you!</p>
                </div>
                {$detailsTable}
                <div style='background:#d4edda;border:1px solid #c3e6cb;border-radius:10px;padding:14px 18px;margin-top:16px;font-size:0.86rem;color:#155724;'>
                    Please carry a valid government-issued ID at check-in. Standard check-in time: 12:00 PM.
                </div>" . $footer;
            $a = $header . "
                <h2 style='color:{$brandColor};font-family:Georgia,serif;'>Booking Confirmed</h2>
                <p style='color:#666;margin-bottom:20px;'>You confirmed the booking for <strong>{$d['guestName']}</strong>.</p>
                {$detailsTable}" . $footer;
            return [
                ['subject' => "Booking Confirmed — {$d['bookingNo']} | {$hotelName}", 'html' => $g],
                ['subject' => "Confirmed: {$d['guestName']} — {$d['bookingNo']}",     'html' => $a],
            ];

        case 'booking_rejected':
            $g = $header . "
                <div style='text-align:center;margin-bottom:24px;'>
                    <div style='width:60px;height:60px;background:#f8d7da;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:1.8rem;margin-bottom:12px;'>&#x274C;</div>
                    <h2 style='color:{$brandColor};font-family:Georgia,serif;font-size:1.3rem;margin:0 0 8px;'>Booking Not Available</h2>
                    <p style='color:#666;margin:0;'>Dear <strong>{$d['guestName']}</strong>, unfortunately we couldn't accommodate your request for these dates.</p>
                </div>
                {$detailsTable}
                <div style='background:#f8d7da;border:1px solid #f5c6cb;border-radius:10px;padding:14px 18px;margin-top:16px;font-size:0.86rem;color:#721c24;'>
                    Please try alternate dates or call us to check availability.
                </div>
                <div style='text-align:center;margin-top:20px;'>
                    <a href='" . SITE_URL . "/pages/rooms.php' style='background:linear-gradient(135deg,{$accentColor},#D4AD5E);color:{$brandColor};padding:12px 28px;border-radius:8px;font-weight:700;text-decoration:none;display:inline-block;'>Browse Other Rooms →</a>
                </div>" . $footer;
            $a = $header . "
                <h2 style='color:{$brandColor};font-family:Georgia,serif;'>Booking Rejected</h2>
                <p style='color:#666;margin-bottom:20px;'>You rejected the booking for <strong>{$d['guestName']}</strong>.</p>
                {$detailsTable}" . $footer;
            return [
                ['subject' => "Booking Update — {$d['bookingNo']} | {$hotelName}", 'html' => $g],
                ['subject' => "Rejected: {$d['guestName']} — {$d['bookingNo']}",   'html' => $a],
            ];

        case 'booking_cancelled':
            $g = $header . "
                <div style='text-align:center;margin-bottom:24px;'>
                    <div style='width:60px;height:60px;background:#e2e3e5;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:1.8rem;margin-bottom:12px;'>&#x1F6AB;</div>
                    <h2 style='color:{$brandColor};font-family:Georgia,serif;font-size:1.3rem;margin:0 0 8px;'>Booking Cancelled</h2>
                    <p style='color:#666;margin:0;'>Dear <strong>{$d['guestName']}</strong>, your booking has been cancelled.</p>
                </div>
                {$detailsTable}
                <div style='text-align:center;margin-top:16px;'>
                    <a href='" . SITE_URL . "/pages/rooms.php' style='background:linear-gradient(135deg,{$accentColor},#D4AD5E);color:{$brandColor};padding:12px 28px;border-radius:8px;font-weight:700;text-decoration:none;display:inline-block;'>Book Again →</a>
                </div>" . $footer;
            $a = $header . "
                <h2 style='color:{$brandColor};font-family:Georgia,serif;'>Booking Cancelled by Guest</h2>
                <p style='color:#666;margin-bottom:20px;'><strong>{$d['guestName']}</strong> ({$d['mobile']}) cancelled their booking.</p>
                {$detailsTable}" . $footer;
            return [
                ['subject' => "Booking Cancelled — {$d['bookingNo']} | {$hotelName}",      'html' => $g],
                ['subject' => "Cancelled by Guest: {$d['guestName']} — {$d['bookingNo']}", 'html' => $a],
            ];

        default:
            $fb = $header . '<p style="color:#666;">Booking update from Karavali Lodge.</p>' . $footer;
            return [
                ['subject' => "Booking Update — {$hotelName}", 'html' => $fb],
                ['subject' => "Booking Update — {$hotelName}", 'html' => $fb],
            ];
    }
}

// ── Send via Gmail SMTP ───────────────────────────────────────────
function sendGmail(string $to, string $subject, string $htmlBody): array {
    if (empty($to)) return ['sent' => false, 'error' => 'No recipient email address.'];
    try {
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            return sendGmailViaPHPMailer($to, $subject, $htmlBody);
        }
        return sendGmailViaSocket($to, $subject, $htmlBody);
    } catch (Exception $e) {
        logError('Gmail', $e->getMessage());
        return ['sent' => false, 'error' => $e->getMessage()];
    }
}

function sendGmailViaPHPMailer(string $to, string $subject, string $htmlBody): array {
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = GMAIL_FROM_EMAIL;
        $mail->Password   = GMAIL_APP_PASSWORD;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom(GMAIL_FROM_EMAIL, GMAIL_FROM_NAME);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $htmlBody));
        $mail->send();
        return ['sent' => true];
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        return ['sent' => false, 'error' => $mail->ErrorInfo];
    }
}

function sendGmailViaSocket(string $to, string $subject, string $htmlBody): array {
    $host = 'smtp.gmail.com'; $port = 587; $timeout = 15;
    $username = GMAIL_FROM_EMAIL;
    $password = str_replace(' ', '', GMAIL_APP_PASSWORD);
    $from = GMAIL_FROM_EMAIL; $fromName = GMAIL_FROM_NAME;

    $sock = @fsockopen($host, $port, $errno, $errstr, $timeout);
    if (!$sock) return ['sent' => false, 'error' => "Cannot connect to {$host}:{$port} — {$errstr}"];

    $read = function() use ($sock) {
        $data = '';
        while ($line = fgets($sock, 515)) { $data .= $line; if (substr($line, 3, 1) === ' ') break; }
        return $data;
    };
    $send = function(string $cmd) use ($sock, $read): string { fwrite($sock, $cmd . "\r\n"); return $read(); };

    $read(); $send("EHLO localhost"); $send("STARTTLS");
    stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
    $send("EHLO localhost"); $send("AUTH LOGIN");
    $send(base64_encode($username));
    $r = $send(base64_encode($password));
    if (strpos($r, '235') === false) { fclose($sock); return ['sent' => false, 'error' => 'Gmail AUTH failed. Response: ' . trim($r)]; }

    $send("MAIL FROM:<{$from}>"); $send("RCPT TO:<{$to}>"); $send("DATA");
    $boundary  = md5(uniqid());
    // Message-ID is required by virtually every spam filter as a basic
    // sign of a legitimate email — without it, mail to a fresh recipient
    // (anyone other than your own Gmail inbox, which Gmail trusts more
    // leniently) is much more likely to be routed to spam.
    $messageId = '<' . md5(uniqid((string) microtime(true), true)) . '@gmail.com>';
    $headers  = "From: {$fromName} <{$from}>\r\nTo: {$to}\r\n"
        . "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n"
        . "MIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n"
        . "Date: " . date('r') . "\r\nMessage-ID: {$messageId}\r\nX-Mailer: KaravaliLodgeMailer/1.0\r\n\r\n";
    $plain = strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $htmlBody));
    $body  = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($plain)) . "\r\n"
        . "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($htmlBody)) . "\r\n--{$boundary}--\r\n";

    $r = $send($headers . $body . "\r\n."); $send("QUIT"); fclose($sock);
    return strpos($r, '250') !== false ? ['sent' => true] : ['sent' => false, 'error' => 'SMTP error: ' . trim($r)];
}

// ── Helpers ───────────────────────────────────────────────────────
function fmtDate(string $date): string {
    if (!$date) return '—';
    $d = DateTime::createFromFormat('Y-m-d', $date) ?: DateTime::createFromFormat('Y-m-d H:i:s', $date);
    return $d ? $d->format('d M Y') : $date;
}
function fmtCurrency($amount): string { return '₹' . number_format((float)$amount, 2); }
function calcNights(string $in, string $out): int {
    if (!$in || !$out) return 0;
    return max(1, (int) ceil((strtotime($out) - strtotime($in)) / 86400));
}
function normalisePhone(string $mobile): string {
    $mobile = preg_replace('/[^0-9+]/', '', $mobile);
    if (empty($mobile))                                return '';
    if (substr($mobile, 0, 1) === '+')                 return $mobile;
    if (strlen($mobile) === 10)                        return '+91' . $mobile;
    if (strlen($mobile) === 11 && $mobile[0] === '0')  return '+91' . substr($mobile, 1);
    return '+' . $mobile;
}
function logNotification(string $event, array $d, array $results): void {
    $logDir = __DIR__ . '/../../logs/';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    @file_put_contents($logDir . 'notifications.log',
        date('Y-m-d H:i:s') . " | {$event} | {$d['bookingNo']} | {$d['guestName']} | " . json_encode($results) . "\n",
        FILE_APPEND | LOCK_EX);
}
function logError(string $channel, string $message): void {
    $logDir = __DIR__ . '/../../logs/';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    @file_put_contents($logDir . 'notifications_errors.log',
        date('Y-m-d H:i:s') . " | [{$channel}] {$message}\n",
        FILE_APPEND | LOCK_EX);
}