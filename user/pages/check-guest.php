<?php
// =============================================
// Karavali Lodge — Live "mobile already registered" check
// karavali_lodge/user/pages/check-guest.php
//
// Used by register.php to show an inline hint while typing.
// Unauthenticated by design (runs before login/signup), so it's
// rate-limited per IP to stop it being used to mass-enumerate which
// mobile numbers are registered guests.
// =============================================
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

$ip = getClientIp();

// Max 20 checks per 5 minutes per IP — generous for a real person
// typing/retrying their own number, but blocks scripted enumeration.
if (isRateLimited('check_guest', $ip, 20, 5)) {
    http_response_code(429);
    echo json_encode(['exists' => false, 'rate_limited' => true]);
    exit;
}
recordLookupAttempt('check_guest', $ip);

$mobile = sanitize($_GET['mobile'] ?? '');
if (!$mobile) {
    echo json_encode(['exists' => false]);
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT id FROM guests WHERE mobile = ?");
$stmt->execute([$mobile]);
$guest = $stmt->fetch();

echo json_encode(['exists' => (bool)$guest]);