<?php
// =============================================
// Karavali Lodge — OTP Sender
// karavali_lodge/user/pages/send-otp.php
//
// Sends OTP via Gmail only.
// Guests without an email on file are directed
// to call hotel reception for a manual reset.
//
// Requires: notifications.php (sendGmail + normalisePhone)
// =============================================

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/notifications.php';

// ── Auto-create OTP table if it doesn't exist ─────────────────────
function ensureOtpTable(): void {
    getDB()->exec("
        CREATE TABLE IF NOT EXISTS password_reset_otps (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            mobile     VARCHAR(20)  NOT NULL,
            otp        VARCHAR(255) NOT NULL,
            attempts   TINYINT      NOT NULL DEFAULT 0,
            created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME     NOT NULL,
            used       TINYINT(1)   NOT NULL DEFAULT 0,
            INDEX idx_mobile  (mobile),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

// ── Rate limit: max 3 requests per 10 minutes per mobile ──────────
function countRecentOtps(string $mobile): int {
    $s = getDB()->prepare(
        "SELECT COUNT(*) FROM password_reset_otps
         WHERE mobile = ? AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)"
    );
    $s->execute([$mobile]);
    return (int) $s->fetchColumn();
}

// ── Build OTP email HTML ──────────────────────────────────────────
function buildOtpEmail(string $guestName, string $otpCode): string {
    $year = date('Y');
    return "
    <div style='font-family:\"Segoe UI\",Arial,sans-serif;max-width:480px;margin:0 auto;'>
      <div style='background:linear-gradient(135deg,#2A1007,#3B1A0A);padding:24px 32px;
                  text-align:center;border-radius:12px 12px 0 0;'>
        <h2 style='color:#C4943A;font-family:Georgia,serif;font-style:italic;margin:0;'>
            Karavali Lodge
        </h2>
        <p style='color:rgba(255,255,255,0.5);font-size:0.8rem;margin:4px 0 0;'>
            K.S. Rao Road, Mangalore
        </p>
      </div>
      <div style='background:#fff;padding:36px 32px;border:1px solid #e8d9c0;
                  border-top:none;border-radius:0 0 12px 12px;text-align:center;'>
        <div style='width:64px;height:64px;background:#fff3cd;border-radius:50%;
                    display:inline-flex;align-items:center;justify-content:center;
                    font-size:1.8rem;margin-bottom:16px;'>&#x1F510;</div>
        <h3 style='color:#3B1A0A;font-family:Georgia,serif;margin:0 0 8px;'>
            Password Reset OTP
        </h3>
        <p style='color:#666;font-size:0.9rem;margin:0 0 24px;'>
          Dear <strong>" . htmlspecialchars($guestName, ENT_QUOTES) . "</strong>,
          use the code below to reset your password.
        </p>
        <div style='background:#FBF7F2;border:2px dashed #C4943A;border-radius:12px;
                    padding:20px 32px;display:inline-block;margin-bottom:24px;'>
          <div style='font-size:2.4rem;font-weight:700;letter-spacing:12px;
                      color:#3B1A0A;font-family:monospace;'>{$otpCode}</div>
        </div>
        <p style='color:#e67e22;font-size:0.85rem;font-weight:600;margin:0 0 8px;'>
          &#x23F1; This code expires in <strong>10 minutes</strong>.
        </p>
        <p style='color:#999;font-size:0.78rem;margin:0 0 24px;'>
          Do not share this code with anyone.<br>
          If you did not request a reset, please ignore this email.
        </p>
        <hr style='border:none;border-top:1px solid #eee;margin:0 0 16px;'>
        <p style='color:#bbb;font-size:0.75rem;margin:0;'>
          &copy; {$year} Karavali Lodge &middot; 0824-2389156
        </p>
      </div>
    </div>";
}

// ── Bootstrap ─────────────────────────────────────────────────────
ensureOtpTable();
$db     = getDB();
$action = sanitize($_GET['action'] ?? '');
$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$mobile = sanitize($input['mobile'] ?? '');
$otp    = sanitize($input['otp']    ?? '');

switch ($action) {

    // ══════════════════════════════════════════════════════════════
    // SEND OTP — email only
    // ══════════════════════════════════════════════════════════════
    case 'send':
        if (!$mobile || strlen(preg_replace('/\D/', '', $mobile)) < 10) {
            echo json_encode([
                'success' => false,
                'error'   => 'Please enter a valid 10-digit mobile number.',
            ]);
            exit;
        }

        // ── IP rate limit — applied BEFORE the existence check below, so
        // "No account found" can't be used to enumerate registered mobile
        // numbers at unlimited speed. This is separate from the per-mobile
        // limit further down, which throttles actual OTP volume to one number.
        $clientIp = getClientIp();
        if (isRateLimited('send_otp', $clientIp, 10, 10)) {
            echo json_encode([
                'success'      => false,
                'rate_limited' => true,
                'error'        => 'Too many requests from this device. Please wait a few minutes and try again.',
            ]);
            exit;
        }
        recordLookupAttempt('send_otp', $clientIp);

        $mobile = normalisePhone($mobile); // → +91XXXXXXXXXX

        // Extract last 10 digits — handles any stored format
        // e.g. 9019509467 / +919019509467 / 09019509467
        $digits = preg_replace('/\D/', '', $mobile);
        $last10 = substr($digits, -10);

        // Try all common storage formats in one query (MySQL 5.x compatible)
        $s = $db->prepare(
            "SELECT id, name, email FROM guests
             WHERE mobile = ?
                OR mobile = ?
                OR mobile = ?
                OR mobile = ?
             LIMIT 1"
        );
        // Variants: 10-digit, +91 prefix, 91 prefix, 0 prefix
        $s->execute([
            $last10,                  // 9019509467
            '+91' . $last10,          // +919019509467
            '91'  . $last10,          // 919019509467
            '0'   . $last10,          // 09019509467
        ]);
        $guest = $s->fetch();

        if (!$guest) {
            echo json_encode([
                'success' => false,
                'error'   => 'No account found with this mobile number.',
            ]);
            exit;
        }

        // Guest has no email — cannot send OTP, direct to reception
        if (empty(trim($guest['email'] ?? ''))) {
            echo json_encode([
                'success'  => false,
                'no_email' => true,
                'error'    => 'No email address is linked to your account. '
                            . 'Please contact hotel reception at <strong>0824-2389156</strong> '
                            . 'to reset your password.',
            ]);
            exit;
        }

        // Rate limit: max 3 OTPs per 10 minutes
        if (countRecentOtps($mobile) >= 3) {
            echo json_encode([
                'success'      => false,
                'rate_limited' => true,
                'error'        => 'Too many OTP requests. Please wait 10 minutes and try again.',
            ]);
            exit;
        }

        // Invalidate any existing unused OTPs for this mobile (use last10 as key)
        $db->prepare("UPDATE password_reset_otps SET used=1 WHERE mobile=? AND used=0")
           ->execute([$last10]);

        // Generate cryptographically secure 6-digit OTP
        $otpCode   = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        // Store hashed OTP — use last10 as consistent key across all cases
        $db->prepare("INSERT INTO password_reset_otps (mobile, otp, expires_at) VALUES (?, ?, ?)")
           ->execute([$last10, password_hash($otpCode, PASSWORD_DEFAULT), $expiresAt]);

        // Send OTP email
        $emailResult = sendGmail(
            $guest['email'],
            'Your OTP for Password Reset — Karavali Lodge',
            buildOtpEmail($guest['name'], $otpCode)
        );
        $emailSent = $emailResult['sent'] ?? false;

        // Mask email for display  e.g. de***@gmail.com
        [$eLocal, $eDomain] = array_pad(explode('@', $guest['email'], 2), 2, '');
        $maskedEmail = substr($eLocal, 0, 2)
                     . str_repeat('*', max(2, strlen($eLocal) - 2))
                     . '@' . $eDomain;

        if ($emailSent) {
            echo json_encode([
                'success'      => true,
                'email_sent'   => true,
                'masked_email' => $maskedEmail,
                'message'      => 'OTP sent to your email.',
            ]);
        } else {
            echo json_encode([
                'success'     => false,
                'error'       => 'Failed to send OTP email. Please try again or contact reception.',
                'debug_email' => $emailResult['error'] ?? 'Unknown error',
            ]);
        }
        break;

    // ══════════════════════════════════════════════════════════════
    // VERIFY OTP
    // ══════════════════════════════════════════════════════════════
    case 'verify':
        if (!$mobile || !$otp) {
            echo json_encode(['success' => false, 'error' => 'Mobile and OTP are required.']);
            exit;
        }

        $mobile = normalisePhone($mobile);
        $last10 = substr(preg_replace('/\D/', '', $mobile), -10);

        $s = $db->prepare(
            "SELECT id, otp, attempts FROM password_reset_otps
             WHERE mobile = ? AND used = 0 AND expires_at > NOW()
             ORDER BY created_at DESC LIMIT 1"
        );
        $s->execute([$last10]);
        $row = $s->fetch();

        if (!$row) {
            echo json_encode([
                'success' => false,
                'expired' => true,
                'error'   => 'OTP has expired or does not exist. Please request a new one.',
            ]);
            exit;
        }

        // Max 5 wrong attempts → lock OTP
        if ((int) $row['attempts'] >= 5) {
            $db->prepare("UPDATE password_reset_otps SET used=1 WHERE id=?")
               ->execute([$row['id']]);
            echo json_encode([
                'success' => false,
                'locked'  => true,
                'error'   => 'Too many incorrect attempts. Please request a new OTP.',
            ]);
            exit;
        }

        if (!password_verify($otp, $row['otp'])) {
            $db->prepare("UPDATE password_reset_otps SET attempts=attempts+1 WHERE id=?")
               ->execute([$row['id']]);
            $remaining = max(0, 4 - (int) $row['attempts']);
            echo json_encode([
                'success'   => false,
                'error'     => 'Incorrect OTP. ' . $remaining . ' attempt(s) remaining.',
                'remaining' => $remaining,
            ]);
            exit;
        }

        // OTP correct — issue a session-bound reset token (never in URL)
        $resetToken  = bin2hex(random_bytes(32));
        $tokenExpiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $db->prepare("UPDATE password_reset_otps SET used=1 WHERE id=?")
           ->execute([$row['id']]);

        $_SESSION['pwd_reset_token']  = $resetToken;
        $_SESSION['pwd_reset_mobile'] = $mobile;
        $_SESSION['pwd_reset_expiry'] = $tokenExpiry;

        echo json_encode([
            'success'     => true,
            'reset_token' => $resetToken,
            'message'     => 'OTP verified successfully.',
        ]);
        break;

    // ══════════════════════════════════════════════════════════════
    // RESET PASSWORD
    // ══════════════════════════════════════════════════════════════
    case 'reset_password':
        $token       = sanitize($input['reset_token'] ?? '');
        $newPassword = $input['new_password'] ?? '';

        if (!$token || !$newPassword) {
            echo json_encode([
                'success' => false,
                'error'   => 'Reset token and new password are required.',
            ]);
            exit;
        }

        $sessionToken  = $_SESSION['pwd_reset_token']  ?? '';
        $sessionMobile = $_SESSION['pwd_reset_mobile'] ?? '';
        $sessionExpiry = $_SESSION['pwd_reset_expiry'] ?? '';

        if (!$sessionToken || !hash_equals($sessionToken, $token)) {
            echo json_encode([
                'success' => false,
                'error'   => 'Invalid or expired reset session. Please start over.',
            ]);
            exit;
        }
        if (strtotime($sessionExpiry) < time()) {
            echo json_encode([
                'success' => false,
                'expired' => true,
                'error'   => 'Reset session expired. Please verify OTP again.',
            ]);
            exit;
        }

        // Same password policy as registration — a reset must never be
        // allowed to produce a weaker password than signup would.
        $pwdErrors = validatePassword($newPassword);
        if ($pwdErrors) {
            echo json_encode([
                'success' => false,
                'error'   => $pwdErrors[0],
            ]);
            exit;
        }

        // sessionMobile is last10 — match all storage formats
        $digits = preg_replace('/\D/', '', $sessionMobile);
        $last10 = substr($digits, -10);
        $db->prepare(
            "UPDATE guests SET password_hash=?, updated_at=NOW()
             WHERE mobile = ? OR mobile = ? OR mobile = ? OR mobile = ?"
        )->execute([
            password_hash($newPassword, PASSWORD_DEFAULT),
            $last10,
            '+91' . $last10,
            '91'  . $last10,
            '0'   . $last10,
        ]);

        unset(
            $_SESSION['pwd_reset_token'],
            $_SESSION['pwd_reset_mobile'],
            $_SESSION['pwd_reset_expiry']
        );

        echo json_encode([
            'success' => true,
            'message' => 'Password updated successfully. You can now log in.',
        ]);
        break;

    default:
        echo json_encode(['error' => 'Unknown action. Use: send | verify | reset_password']);
}