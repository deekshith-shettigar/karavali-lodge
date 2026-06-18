<?php
// =============================================
// Karavali Lodge — Razorpay Order Handler
// karavali_lodge/user/pages/razorpay-order.php
// Handles: create_order, verify_payment
// =============================================

header('Content-Type: application/json');

// ── CORS — only allow requests from this site's own origin ────────
// Wildcard (*) is unsafe for a payment endpoint: any site on the
// internet could trigger order creation in the hotel's name.
// Mirror the allowlist pattern used in admin/api.php.
$allowedOrigins = [
    'http://localhost',
    'http://127.0.0.1',
    'http://localhost:80',
    'http://localhost:8080',
    // Add your live domain when deploying, e.g.:
    // 'https://karavalilodge.com',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
}
// Unknown / no origin — same-page requests have no Origin header and
// are always allowed through; external origins get no CORS grant.

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../includes/config.php';

// Safe fallbacks if keys not defined
if (!defined('RAZORPAY_KEY_ID'))     define('RAZORPAY_KEY_ID',     '');
if (!defined('RAZORPAY_KEY_SECRET')) define('RAZORPAY_KEY_SECRET', '');
if (!defined('RAZORPAY_CURRENCY'))   define('RAZORPAY_CURRENCY',   'INR');

$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

// ── Guard: must be logged in ──────────────────────────────────────
if (!isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// ── Guard: keys must be configured ───────────────────────────────
if (empty(RAZORPAY_KEY_ID) || empty(RAZORPAY_KEY_SECRET)) {
    echo json_encode(['error' => 'Razorpay keys not configured in config.php']);
    exit;
}

switch ($action) {

    // ── CREATE ORDER ──────────────────────────────────────────────
    case 'create_order':
        $amount  = (int)($input['amount_paise'] ?? 0);
        $receipt = sanitize($input['receipt'] ?? ('rcpt_' . time()));
        $notes   = $input['notes'] ?? [];

        if ($amount <= 0) {
            echo json_encode(['error' => 'Invalid amount: ' . $amount]);
            exit;
        }

        // Check cURL is available
        if (!function_exists('curl_init')) {
            echo json_encode(['error' => 'cURL is not enabled on this server. Enable it in php.ini.']);
            exit;
        }

        $payload = json_encode([
            'amount'          => $amount,
            'currency'        => RAZORPAY_CURRENCY,
            'receipt'         => $receipt,
            'notes'           => $notes,
            'payment_capture' => 1,
        ]);

        $ch = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_USERPWD        => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_SSL_VERIFYPEER => true,  // verify Razorpay's SSL certificate
            CURLOPT_SSL_VERIFYHOST => 2,     // verify hostname matches certificate
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            echo json_encode(['error' => 'cURL error: ' . $curlErr]);
            exit;
        }

        $order = json_decode($response, true);

        if ($httpCode !== 200 || empty($order['id'])) {
            echo json_encode([
                'error'   => 'Razorpay API error (HTTP ' . $httpCode . ')',
                'details' => $order,
                'raw'     => $response,
            ]);
            exit;
        }

        // Store expected amount in session keyed by Razorpay order ID.
        // booking.php and room-booking.php will compare the server-calculated
        // advance against this value after signature verification — preventing
        // a guest from manipulating data-price in DevTools to pay less.
        $_SESSION['rzp_order_' . $order['id']] = [
            'amount_paise' => $amount,
            'created_at'   => time(),
        ];

        echo json_encode(['success' => true, 'order' => $order]);
        break;

    // ── VERIFY PAYMENT ────────────────────────────────────────────
    case 'verify_payment':
        $orderId   = $input['razorpay_order_id']   ?? '';
        $paymentId = $input['razorpay_payment_id'] ?? '';
        $signature = $input['razorpay_signature']  ?? '';

        if (!$orderId || !$paymentId || !$signature) {
            echo json_encode(['error' => 'Missing payment parameters']);
            exit;
        }

        $expectedSig = hash_hmac('sha256', $orderId . '|' . $paymentId, RAZORPAY_KEY_SECRET);

        if (!hash_equals($expectedSig, $signature)) {
            echo json_encode(['success' => false, 'error' => 'Signature verification failed']);
            exit;
        }

        echo json_encode(['success' => true, 'verified' => true, 'payment_id' => $paymentId]);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
        break;
}