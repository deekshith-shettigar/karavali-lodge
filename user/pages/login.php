<?php
$pageTitle = "Login - Karavali Lodge";
$bodyClass = 'auth-page-body';
require_once __DIR__ . '/../includes/config.php';

if (isLoggedIn()) redirect(SITE_URL . '/pages/my-bookings.php');

$error      = '';
$msg        = $_GET['msg'] ?? '';
$lockoutMsg = '';

// ══════════════════════════════════════════════════════════════════
// BRUTE FORCE PROTECTION
// Tracks failed login attempts per IP address.
// After 5 failures within 10 minutes → locked for 10 minutes.
// Every failed attempt also adds a 1-second delay to slow bots.
// ══════════════════════════════════════════════════════════════════

function ensureAttemptsTable(): void {
    getDB()->exec("
        CREATE TABLE IF NOT EXISTS login_attempts (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            ip         VARCHAR(45)  NOT NULL,
            mobile     VARCHAR(20)  NOT NULL DEFAULT '',
            attempted_at DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ip_time (ip, attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

// getClientIp() now lives in includes/config.php (shared with
// check-guest.php and send-otp.php for rate limiting).

function countRecentFailures(string $ip): int {
    $s = getDB()->prepare(
        "SELECT COUNT(*) FROM login_attempts
         WHERE ip = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)"
    );
    $s->execute([$ip]);
    return (int) $s->fetchColumn();
}

function recordFailure(string $ip, string $mobile): void {
    getDB()->prepare(
        "INSERT INTO login_attempts (ip, mobile) VALUES (?, ?)"
    )->execute([$ip, $mobile]);
    // 1-second delay on every failure — makes automated attacks 1000× slower
    sleep(1);
}

function clearFailures(string $ip): void {
    getDB()->prepare(
        "DELETE FROM login_attempts WHERE ip = ?"
    )->execute([$ip]);
}

function minutesUntilUnlock(string $ip): int {
    $s = getDB()->prepare(
        "SELECT attempted_at FROM login_attempts
         WHERE ip = ? ORDER BY attempted_at DESC LIMIT 1"
    );
    $s->execute([$ip]);
    $row = $s->fetch();
    if (!$row) return 0;
    $unlockAt = strtotime($row['attempted_at']) + 600; // 10 minutes
    return max(1, (int) ceil(($unlockAt - time()) / 60));
}

// ── Normal login POST ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mobile   = sanitize($_POST['mobile']   ?? '');
    $password = $_POST['password'] ?? '';

    if (!$mobile)       $error = 'Mobile number is required.';
    elseif (!$password) $error = 'Password is required.';
    else {
        $db  = getDB();
        $ip  = getClientIp();
        ensureAttemptsTable();

        // ── Check lockout before even querying the DB ─────────────
        $failures = countRecentFailures($ip);
        if ($failures >= 5) {
            $mins  = minutesUntilUnlock($ip);
            $error = "Too many failed attempts. Please wait {$mins} minute" . ($mins > 1 ? 's' : '') . " before trying again.";
            $lockoutMsg = $error;
        } else {
            $rawRedirect = $_SESSION['redirect_after_login'] ?? '';
            unset($_SESSION['redirect_after_login']);

            // Validate redirect target — must start with SITE_URL to prevent
            // open redirect attacks where an injected external URL could send
            // the guest off-site after login (e.g. to a phishing page).
            $defaultRedirect = SITE_URL . '/pages/my-bookings.php';
            if ($rawRedirect !== '' && str_starts_with($rawRedirect, SITE_URL)) {
                $redirectTo = $rawRedirect;
            } else {
                $redirectTo = $defaultRedirect;
            }

            // Check admins table first
            $admin = getAdminByMobile($mobile);
            if ($admin) {
                if (password_verify($password, $admin['password_hash'])) {
                    clearFailures($ip);
                    session_regenerate_id(true);
                    $_SESSION['admin_id']   = $admin['id'];
                    $_SESSION['admin_name'] = $admin['name'];
                    header('Cache-Control: no-store, no-cache, must-revalidate');
                    header('Pragma: no-cache');
                    header('Expires: 0');
                    header('Location: ' . ADMIN_URL . '/index.php');
                    exit;
                } else {
                    recordFailure($ip, $mobile);
                    $failures++;
                    $remaining = max(0, 5 - $failures);
                    $error = 'Incorrect password.'
                           . ($remaining > 0 ? " ({$remaining} attempt" . ($remaining > 1 ? 's' : '') . " remaining)" : '');
                }
            } else {
                $stmt = $db->prepare("SELECT * FROM guests WHERE mobile = ?");
                $stmt->execute([$mobile]);
                $guest = $stmt->fetch();

                if (!$guest) {
                    recordFailure($ip, $mobile);
                    $failures++;
                    $remaining = max(0, 5 - $failures);
                    $error = 'No account found with this mobile number.'
                           . ($remaining > 0 ? " ({$remaining} attempt" . ($remaining > 1 ? 's' : '') . " remaining)" : '');
                } elseif (!$guest['password_hash']) {
                    // First login — set password
                    $db->prepare("UPDATE guests SET password_hash=? WHERE id=?")
                       ->execute([password_hash($password, PASSWORD_DEFAULT), $guest['id']]);
                    clearFailures($ip);
                    session_regenerate_id(true);
                    $_SESSION['guest_id']   = $guest['id'];
                    $_SESSION['guest_name'] = $guest['name'];
                    redirect($redirectTo);
                } elseif (password_verify($password, $guest['password_hash'])) {
                    clearFailures($ip);
                    session_regenerate_id(true);
                    $_SESSION['guest_id']   = $guest['id'];
                    $_SESSION['guest_name'] = $guest['name'];
                    redirect($redirectTo);
                } else {
                    recordFailure($ip, $mobile);
                    $failures++;
                    $remaining = max(0, 5 - $failures);
                    $error = 'Incorrect password. Please try again.'
                           . ($remaining > 0 ? " ({$remaining} attempt" . ($remaining > 1 ? 's' : '') . " remaining)" : '');
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,400&family=Cormorant+Garamond:ital,wght@1,300;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/css/style.css" rel="stylesheet">
    <style>
        /* ── OTP digit boxes ─────────────────────────── */
        .otp-boxes { display:flex; gap:10px; justify-content:center; margin:20px 0; }
        .otp-box {
            width:48px; height:56px; text-align:center;
            font-size:1.5rem; font-weight:700;
            border:2px solid #E8D9C0; border-radius:10px;
            background:#FDFAF5; color:#3B1A0A;
            outline:none; transition:border-color .2s, box-shadow .2s;
            font-family:'Jost',sans-serif;
        }
        .otp-box:focus { border-color:#C4943A; box-shadow:0 0 0 3px rgba(196,148,58,.18); }
        .otp-box.filled { border-color:#C4943A; background:#FFF8EE; }
        .otp-box.error  { border-color:#dc3545; background:#fff5f5; animation:shake .3s ease; }
        @keyframes shake {
            0%,100%{transform:translateX(0)}
            25%{transform:translateX(-5px)}
            75%{transform:translateX(5px)}
        }
        /* ── Step visibility ─────────────────────────── */
        .otp-step { display:none; }
        .otp-step.active { display:block; }
        /* ── Countdown ───────────────────────────────── */
        .countdown-wrap { display:inline-flex; align-items:center; gap:6px; font-size:.83rem; color:#8B7355; }
        .countdown-num { font-weight:700; color:#C4943A; min-width:24px; }
        /* ── Success animation ───────────────────────── */
        @keyframes popIn {
            0%  { transform:scale(.7); opacity:0 }
            70% { transform:scale(1.1); opacity:1 }
            100%{ transform:scale(1); opacity:1 }
        }
        .pop-in { animation:popIn .4s ease forwards; }
    </style>
</head>
<body>
<div class="auth-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="auth-card">

                    <?php if ($msg === 'login_required'): ?>
                    <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:10px;
                                padding:14px 18px;margin-bottom:20px;font-size:.88rem;color:#856404;
                                display:flex;align-items:center;gap:10px;">
                        <i class="bi bi-lock-fill" style="font-size:1.1rem;"></i>
                        <div><strong>Login Required</strong><br>Please login or register to book a room.</div>
                    </div>
                    <?php elseif ($msg === 'session_expired'): ?>
                    <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:10px;
                                padding:14px 18px;margin-bottom:20px;font-size:.88rem;color:#856404;
                                display:flex;align-items:center;gap:10px;">
                        <i class="bi bi-clock-history" style="font-size:1.1rem;"></i>
                        <div><strong>Session Expired</strong><br>Please login again to continue.</div>
                    </div>
                    <?php endif; ?>

                    <!-- Logo + heading -->
                    <div class="auth-logo" style="text-align:center;margin-bottom:28px;">
                        <img src="<?= SITE_URL ?>/images/Lodge_Logoo.png" alt="Karavali Lodge"
                             style="width:80px;height:80px;object-fit:contain;margin-bottom:12px;">
                        <h2 class="auth-title">Welcome Back</h2>
                        <p class="auth-subtitle" style="color:#8B7355;font-size:.88rem;margin:0;">
                            Sign in to view your bookings
                        </p>
                    </div>

                    <?php if ($error): ?>
                    <div id="errorPopup" style="position:fixed;inset:0;z-index:9999;display:flex;
                         align-items:center;justify-content:center;background:rgba(0,0,0,.55);
                         backdrop-filter:blur(4px);">
                        <div style="background:#fff;border-radius:20px;padding:40px 32px;
                                    max-width:360px;width:90%;text-align:center;
                                    box-shadow:0 24px 64px rgba(0,0,0,.25);">
                            <?php if ($lockoutMsg): ?>
                            <!-- Lockout state -->
                            <div style="width:64px;height:64px;background:#fef2f2;border-radius:50%;
                                        display:flex;align-items:center;justify-content:center;
                                        margin:0 auto 16px;">
                                <i class="bi bi-shield-lock-fill" style="font-size:1.8rem;color:#ef4444;"></i>
                            </div>
                            <h5 style="font-family:'Playfair Display',serif;color:#3B1A0A;
                                       font-size:1.1rem;margin-bottom:8px;">Account Temporarily Locked</h5>
                            <p style="color:#6b7280;font-size:.88rem;margin-bottom:8px;line-height:1.6;">
                                <?= sanitize($error) ?>
                            </p>
                            <p style="color:#e67e22;font-size:.82rem;margin-bottom:24px;">
                                <i class="bi bi-clock me-1"></i>Try again after the lockout period expires.
                            </p>
                            <button onclick="document.getElementById('errorPopup').remove()"
                                    style="background:#f3f4f6;color:#374151;border:none;border-radius:10px;
                                           padding:12px 32px;font-weight:600;font-size:.92rem;
                                           cursor:pointer;width:100%;">
                                OK
                            </button>
                            <?php else: ?>
                            <!-- Normal wrong password/mobile state -->
                            <div style="width:64px;height:64px;background:#fef2f2;border-radius:50%;
                                        display:flex;align-items:center;justify-content:center;
                                        margin:0 auto 16px;">
                                <i class="bi bi-exclamation-circle-fill"
                                   style="font-size:1.8rem;color:#ef4444;"></i>
                            </div>
                            <h5 style="font-family:'Playfair Display',serif;color:#3B1A0A;
                                       font-size:1.1rem;margin-bottom:8px;">Login Failed</h5>
                            <p style="color:#6b7280;font-size:.9rem;margin-bottom:24px;line-height:1.6;">
                                <?= sanitize($error) ?>
                            </p>
                            <button onclick="document.getElementById('errorPopup').remove()"
                                    style="background:linear-gradient(135deg,#C4943A,#D4AD5E);
                                           color:#2A1007;border:none;border-radius:10px;
                                           padding:12px 32px;font-weight:700;font-size:.92rem;
                                           cursor:pointer;width:100%;margin-bottom:12px;">
                                OK, Try Again
                            </button>
                            <?php if (str_contains($error, 'No account')): ?>
                            <a href="<?= SITE_URL ?>/pages/register.php"
                               style="display:block;color:#C4943A;font-size:.88rem;font-weight:600;text-decoration:none;">
                                <i class="bi bi-person-plus me-1"></i>Create New Account
                            </a>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Login form -->
                    <form method="POST" id="loginForm">
                        <div class="mb-3">
                            <label class="form-group-label">Mobile Number *</label>
                            <input type="tel" name="mobile" class="form-control-hotel"
                                   placeholder="+91 XXXXX XXXXX" required
                                   value="<?= sanitize($_POST['mobile'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-group-label">Password *</label>
                            <div style="position:relative;">
                                <input type="password" name="password" id="loginPass"
                                       class="form-control-hotel" placeholder="Enter your password"
                                       required style="padding-right:44px;">
                                <button type="button" onclick="toggleLoginPass()"
                                        style="position:absolute;right:12px;top:50%;
                                               transform:translateY(-50%);background:none;
                                               border:none;color:#C4943A;cursor:pointer;">
                                    <i class="bi bi-eye" id="loginEye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-2 d-flex justify-content-between align-items-center">
                            <a href="#" onclick="openForgotModal();return false;"
                               style="font-size:.82rem;color:#8B7355;text-decoration:none;">
                                Forgot Password?
                            </a>
                            <a href="<?= SITE_URL ?>/pages/register.php"
                               style="font-size:.82rem;color:#C4943A;text-decoration:none;">
                                New user? Register →
                            </a>
                        </div>
                        <button type="submit" class="btn-accent-hotel w-100"
                                style="justify-content:center;padding:14px;border-radius:8px;
                                       font-size:.95rem;margin-top:8px;">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Login
                        </button>
                    </form>

                    <hr style="border-color:var(--border);margin:20px 0;">
                    <div class="text-center">
                        <p style="font-size:.85rem;color:var(--text-muted);">
                            Want to make a new booking?
                            <a href="<?= SITE_URL ?>/pages/rooms.php"
                               style="color:var(--accent);font-weight:600;">Browse Rooms</a>
                        </p>
                        <a href="<?= SITE_URL ?>/index.php"
                           style="font-size:.88rem;color:#C4943A;font-weight:600;
                                  text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                            <i class="bi bi-arrow-left"></i> Back to Hotel Website
                        </a>
                    </div>

                </div><!-- /auth-card -->
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════
     FORGOT PASSWORD MODAL
     Step 1 — Enter mobile
     Step 2 — Enter 6-digit OTP (sent to email)
     Step 3 — Set new password
     Step 4 — Success screen
═══════════════════════════════════════════════════════════════════ -->
<div id="forgotModal"
     style="display:none;position:fixed;inset:0;z-index:9999;
            background:rgba(0,0,0,.6);backdrop-filter:blur(4px);
            align-items:center;justify-content:center;">

    <div style="background:#fff;border-radius:20px;padding:40px 36px;
                max-width:430px;width:90%;box-shadow:0 24px 64px rgba(0,0,0,.3);
                position:relative;max-height:90vh;overflow-y:auto;">

        <!-- Close button -->
        <button onclick="closeForgotModal()"
                style="position:absolute;top:16px;right:16px;background:none;border:none;
                       font-size:1.3rem;color:#8B7355;cursor:pointer;">
            <i class="bi bi-x-lg"></i>
        </button>

        <!-- ── STEP 1: Enter mobile ─────────────────────── -->
        <div id="step1" class="otp-step active">
            <div style="text-align:center;margin-bottom:24px;">
                <div style="width:64px;height:64px;background:#fff3cd;border-radius:50%;
                            display:flex;align-items:center;justify-content:center;
                            margin:0 auto 14px;font-size:1.6rem;color:#C4943A;">
                    <i class="bi bi-shield-lock"></i>
                </div>
                <h4 style="font-family:'Playfair Display',serif;color:#3B1A0A;
                           font-size:1.3rem;margin-bottom:8px;">Reset Password</h4>
                <p style="font-size:.85rem;color:#8B7355;margin:0;line-height:1.6;">
                    Enter your registered mobile number.<br>
                    We'll send a 6-digit OTP to your email address.
                </p>
            </div>

            <div id="step1Error"
                 style="display:none;background:#f8d7da;border:1px solid #f5c6cb;
                        border-radius:8px;padding:11px 14px;color:#721c24;
                        font-size:.84rem;margin-bottom:14px;line-height:1.5;">
            </div>

            <label style="font-size:.72rem;letter-spacing:1.5px;text-transform:uppercase;
                          font-weight:600;color:#8B7355;display:block;margin-bottom:6px;">
                Mobile Number *
            </label>
            <input type="tel" id="s1Mobile" placeholder="+91 XXXXX XXXXX"
                   style="width:100%;border:1.5px solid #E8D9C0;border-radius:10px;
                          padding:12px 16px;font-size:1rem;color:#3B1A0A;
                          background:#FDFAF5;outline:none;box-sizing:border-box;
                          transition:border-color .2s;"
                   onfocus="this.style.borderColor='#C4943A'"
                   onblur="this.style.borderColor='#E8D9C0'"
                   onkeydown="if(event.key==='Enter')sendOtp()">

            <button onclick="sendOtp()" id="sendOtpBtn"
                    style="width:100%;margin-top:16px;
                           background:linear-gradient(135deg,#C4943A,#D4AD5E);
                           color:#2A1007;border:none;border-radius:10px;padding:14px;
                           font-weight:700;cursor:pointer;font-size:.95rem;
                           box-shadow:0 6px 20px rgba(196,148,58,.35);">
                <i class="bi bi-envelope-check me-2"></i>Send OTP to Email
            </button>
        </div>

        <!-- ── STEP 2: Enter OTP ────────────────────────── -->
        <div id="step2" class="otp-step">
            <div style="text-align:center;margin-bottom:20px;">
                <div style="width:64px;height:64px;background:#e8f4fd;border-radius:50%;
                            display:flex;align-items:center;justify-content:center;
                            margin:0 auto 14px;font-size:1.6rem;color:#3498db;">
                    <i class="bi bi-envelope-open"></i>
                </div>
                <h4 style="font-family:'Playfair Display',serif;color:#3B1A0A;
                           font-size:1.2rem;margin-bottom:6px;">Check Your Email</h4>
                <p style="font-size:.83rem;color:#8B7355;margin:0;line-height:1.6;"
                   id="otpSentMsg">
                    A 6-digit OTP has been sent to your email.
                </p>
            </div>

            <div id="step2Error"
                 style="display:none;background:#f8d7da;border:1px solid #f5c6cb;
                        border-radius:8px;padding:11px 14px;color:#721c24;
                        font-size:.84rem;margin-bottom:14px;">
            </div>

            <!-- 6 OTP boxes -->
            <div class="otp-boxes">
                <input class="otp-box" type="text" inputmode="numeric" maxlength="1" id="ob0">
                <input class="otp-box" type="text" inputmode="numeric" maxlength="1" id="ob1">
                <input class="otp-box" type="text" inputmode="numeric" maxlength="1" id="ob2">
                <input class="otp-box" type="text" inputmode="numeric" maxlength="1" id="ob3">
                <input class="otp-box" type="text" inputmode="numeric" maxlength="1" id="ob4">
                <input class="otp-box" type="text" inputmode="numeric" maxlength="1" id="ob5">
            </div>

            <!-- Countdown + resend -->
            <div style="text-align:center;margin-bottom:18px;">
                <span class="countdown-wrap" id="countdownWrap">
                    <i class="bi bi-clock" style="font-size:.9rem;color:#C4943A;"></i>
                    Expires in <span class="countdown-num" id="countdownNum">10:00</span>
                </span>
                <div id="resendWrap" style="display:none;font-size:.82rem;color:#8B7355;">
                    Didn't receive it?
                    <a onclick="resendOtp()"
                       style="color:#C4943A;cursor:pointer;font-weight:600;">
                        Resend OTP
                    </a>
                    &nbsp;or&nbsp;
                    <a onclick="goToStep(1)"
                       style="color:#8B7355;cursor:pointer;text-decoration:underline;font-size:.8rem;">
                        change mobile
                    </a>
                </div>
            </div>

            <button onclick="verifyOtp()" id="verifyOtpBtn"
                    style="width:100%;background:linear-gradient(135deg,#C4943A,#D4AD5E);
                           color:#2A1007;border:none;border-radius:10px;padding:14px;
                           font-weight:700;cursor:pointer;font-size:.95rem;
                           box-shadow:0 6px 20px rgba(196,148,58,.35);">
                <i class="bi bi-check-circle me-2"></i>Verify OTP
            </button>

            <div style="text-align:center;margin-top:14px;">
                <a onclick="goToStep(1)"
                   style="font-size:.8rem;color:#8B7355;cursor:pointer;text-decoration:none;">
                    <i class="bi bi-arrow-left me-1"></i>Change mobile number
                </a>
            </div>
        </div>

        <!-- ── STEP 3: New password ─────────────────────── -->
        <div id="step3" class="otp-step">
            <div style="text-align:center;margin-bottom:20px;">
                <div style="width:64px;height:64px;background:#d4edda;border-radius:50%;
                            display:flex;align-items:center;justify-content:center;
                            margin:0 auto 14px;font-size:1.6rem;color:#28a745;">
                    <i class="bi bi-key"></i>
                </div>
                <h4 style="font-family:'Playfair Display',serif;color:#3B1A0A;
                           font-size:1.2rem;margin-bottom:4px;">Set New Password</h4>
                <p style="font-size:.83rem;color:#28a745;font-weight:600;margin:0;">
                    <i class="bi bi-shield-check me-1"></i>Identity verified
                </p>
            </div>

            <div id="step3Error"
                 style="display:none;background:#f8d7da;border:1px solid #f5c6cb;
                        border-radius:8px;padding:11px 14px;color:#721c24;
                        font-size:.84rem;margin-bottom:14px;">
            </div>

            <div style="margin-bottom:14px;">
                <label style="font-size:.72rem;letter-spacing:1.5px;text-transform:uppercase;
                              font-weight:600;color:#8B7355;display:block;margin-bottom:6px;">
                    New Password *
                </label>
                <div style="position:relative;">
                    <input type="password" id="s3Pass" placeholder="Minimum 8 characters"
                           style="width:100%;border:1.5px solid #E8D9C0;border-radius:10px;
                                  padding:12px 44px 12px 16px;font-size:1rem;color:#3B1A0A;
                                  background:#FDFAF5;outline:none;box-sizing:border-box;">
                    <button type="button" onclick="toggleField('s3Pass','s3Eye1')"
                            style="position:absolute;right:12px;top:50%;transform:translateY(-50%);
                                   background:none;border:none;color:#C4943A;cursor:pointer;">
                        <i class="bi bi-eye" id="s3Eye1"></i>
                    </button>
                </div>
                <!-- Password strength bar -->
                <div id="strengthWrap" style="display:none;margin-top:6px;">
                    <div style="height:4px;background:#eee;border-radius:4px;overflow:hidden;">
                        <div id="strengthFill"
                             style="height:100%;width:0;border-radius:4px;
                                    transition:width .3s,background .3s;"></div>
                    </div>
                    <div id="strengthLabel"
                         style="font-size:.72rem;margin-top:3px;color:#8B7355;"></div>
                </div>
            </div>

            <div style="margin-bottom:20px;">
                <label style="font-size:.72rem;letter-spacing:1.5px;text-transform:uppercase;
                              font-weight:600;color:#8B7355;display:block;margin-bottom:6px;">
                    Confirm Password *
                </label>
                <div style="position:relative;">
                    <input type="password" id="s3Conf" placeholder="Re-enter new password"
                           style="width:100%;border:1.5px solid #E8D9C0;border-radius:10px;
                                  padding:12px 44px 12px 16px;font-size:1rem;color:#3B1A0A;
                                  background:#FDFAF5;outline:none;box-sizing:border-box;">
                    <button type="button" onclick="toggleField('s3Conf','s3Eye2')"
                            style="position:absolute;right:12px;top:50%;transform:translateY(-50%);
                                   background:none;border:none;color:#C4943A;cursor:pointer;">
                        <i class="bi bi-eye" id="s3Eye2"></i>
                    </button>
                </div>
                <div id="matchHint" style="font-size:.78rem;margin-top:5px;min-height:18px;"></div>
            </div>

            <button onclick="resetPassword()" id="resetPassBtn"
                    style="width:100%;background:linear-gradient(135deg,#C4943A,#D4AD5E);
                           color:#2A1007;border:none;border-radius:10px;padding:14px;
                           font-weight:700;cursor:pointer;font-size:.95rem;
                           box-shadow:0 6px 20px rgba(196,148,58,.35);">
                <i class="bi bi-lock-fill me-2"></i>Update Password
            </button>
        </div>

        <!-- ── STEP 4: Success ──────────────────────────── -->
        <div id="step4" class="otp-step" style="text-align:center;padding:10px 0;">
            <div class="pop-in"
                 style="width:80px;height:80px;background:linear-gradient(135deg,#28a745,#20c997);
                        border-radius:50%;display:flex;align-items:center;justify-content:center;
                        margin:0 auto 20px;box-shadow:0 8px 24px rgba(40,167,69,.3);">
                <i class="bi bi-check-lg" style="font-size:2.2rem;color:#fff;"></i>
            </div>
            <h4 style="font-family:'Playfair Display',serif;color:#3B1A0A;
                       font-size:1.3rem;margin-bottom:8px;">Password Updated!</h4>
            <p style="color:#6b7280;font-size:.88rem;margin-bottom:28px;line-height:1.6;">
                Your password has been reset successfully.<br>
                You can now log in with your new password.
            </p>
            <button onclick="closeForgotModal()"
                    style="background:linear-gradient(135deg,#C4943A,#D4AD5E);color:#2A1007;
                           border:none;border-radius:10px;padding:13px 40px;
                           font-weight:700;cursor:pointer;font-size:.95rem;
                           box-shadow:0 6px 20px rgba(196,148,58,.35);">
                <i class="bi bi-box-arrow-in-right me-2"></i>Back to Login
            </button>
        </div>

    </div><!-- /modal inner -->
</div><!-- /forgotModal -->

<script>
// ── Config ────────────────────────────────────────────────────────
const OTP_API = '<?= SITE_URL ?>/pages/send-otp.php';
let _mobile      = '';
let _resetToken  = '';
let _countdown   = null;

// ── Modal open / close ────────────────────────────────────────────
function openForgotModal() {
    document.getElementById('forgotModal').style.display = 'flex';
    goToStep(1);
    setTimeout(() => document.getElementById('s1Mobile').focus(), 100);
}
function closeForgotModal() {
    document.getElementById('forgotModal').style.display = 'none';
    clearCountdown();
}
// Close on backdrop click
document.getElementById('forgotModal').addEventListener('click', function(e) {
    if (e.target === this) closeForgotModal();
});

// ── Step navigation ───────────────────────────────────────────────
function goToStep(n) {
    [1,2,3,4].forEach(i => {
        document.getElementById('step'+i)?.classList.toggle('active', i === n);
    });
}

// ── UI helpers ────────────────────────────────────────────────────
function showError(stepId, msg) {
    const el = document.getElementById(stepId + 'Error');
    if (el) { el.style.display = 'block'; el.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i>' + msg; }
}
function hideError(stepId) {
    const el = document.getElementById(stepId + 'Error');
    if (el) el.style.display = 'none';
}
function setLoading(btnId, loading, label) {
    const btn = document.getElementById(btnId);
    if (!btn) return;
    btn.disabled = loading;
    btn.innerHTML = loading
        ? '<span class="spinner-border spinner-border-sm me-2"></span>Please wait...'
        : label;
}
function toggleField(inputId, iconId) {
    const inp = document.getElementById(inputId);
    const ico = document.getElementById(iconId);
    inp.type      = inp.type === 'password' ? 'text' : 'password';
    ico.className = inp.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}
function toggleLoginPass() {
    toggleField('loginPass', 'loginEye');
}

// ── Countdown timer ───────────────────────────────────────────────
function startCountdown(secs) {
    clearCountdown();
    const numEl    = document.getElementById('countdownNum');
    const wrapEl   = document.getElementById('countdownWrap');
    const resendEl = document.getElementById('resendWrap');
    if (wrapEl)   wrapEl.style.display   = 'inline-flex';
    if (resendEl) resendEl.style.display = 'none';

    let rem = secs;
    _countdown = setInterval(() => {
        rem--;
        const m = String(Math.floor(rem / 60)).padStart(2,'0');
        const s = String(rem % 60).padStart(2,'0');
        if (numEl) numEl.textContent = m + ':' + s;
        if (rem <= 0) {
            clearCountdown();
            if (wrapEl)   wrapEl.style.display   = 'none';
            if (resendEl) resendEl.style.display = 'block';
        }
    }, 1000);
}
function clearCountdown() {
    if (_countdown) { clearInterval(_countdown); _countdown = null; }
}

// ── OTP boxes — keyboard + paste ─────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {

    for (let i = 0; i < 6; i++) {
        const box = document.getElementById('ob' + i);
        if (!box) continue;

        box.addEventListener('input', function () {
            const val = this.value.replace(/\D/g, '');
            // Handle full 6-digit paste
            if (val.length >= 6) {
                for (let j = 0; j < 6; j++) {
                    const b = document.getElementById('ob' + j);
                    if (b) { b.value = val[j] || ''; b.classList.toggle('filled', !!val[j]); }
                }
                document.getElementById('ob5')?.focus();
                return;
            }
            this.value = val.slice(0, 1);
            this.classList.toggle('filled', !!this.value);
            if (this.value && i < 5) document.getElementById('ob' + (i+1))?.focus();
        });

        box.addEventListener('keydown', function (e) {
            if (e.key === 'Backspace' && !this.value && i > 0)
                document.getElementById('ob' + (i-1))?.focus();
            if (e.key === 'Enter') verifyOtp();
        });

        box.addEventListener('focus', function () { this.select(); });
    }

    // Password strength meter
    document.getElementById('s3Pass')?.addEventListener('input', function () {
        const wrap  = document.getElementById('strengthWrap');
        const fill  = document.getElementById('strengthFill');
        const label = document.getElementById('strengthLabel');
        if (!wrap) return;
        const v = this.value;
        wrap.style.display = v ? 'block' : 'none';
        let score = 0;
        if (v.length >= 6)           score++;
        if (v.length >= 10)          score++;
        if (/[A-Z]/.test(v))         score++;
        if (/[0-9]/.test(v))         score++;
        if (/[^A-Za-z0-9]/.test(v))  score++;
        const levels = [
            { pct:'20%',  bg:'#e74c3c', txt:'Very weak'   },
            { pct:'40%',  bg:'#e67e22', txt:'Weak'        },
            { pct:'60%',  bg:'#f1c40f', txt:'Fair'        },
            { pct:'80%',  bg:'#2ecc71', txt:'Strong'      },
            { pct:'100%', bg:'#27ae60', txt:'Very strong' },
        ];
        const lv = levels[Math.min(score, 4)];
        fill.style.width = lv.pct; fill.style.background = lv.bg;
        label.style.color = lv.bg; label.textContent = lv.txt;
    });

    // Password match hint
    document.getElementById('s3Conf')?.addEventListener('input', function () {
        const hint = document.getElementById('matchHint');
        const pass = document.getElementById('s3Pass').value;
        if (!hint) return;
        if (!this.value) { hint.innerHTML = ''; return; }
        hint.innerHTML = this.value === pass
            ? '<span style="color:#27ae60"><i class="bi bi-check-circle-fill me-1"></i>Passwords match</span>'
            : '<span style="color:#dc3545"><i class="bi bi-x-circle-fill me-1"></i>Passwords do not match</span>';
    });
});

// ── STEP 1: Send OTP ──────────────────────────────────────────────
async function sendOtp() {
    hideError('step1');
    const mobile = document.getElementById('s1Mobile').value.trim();
    if (!mobile || mobile.replace(/\D/g,'').length < 10) {
        showError('step1', 'Please enter a valid 10-digit mobile number.');
        return;
    }

    setLoading('sendOtpBtn', true, '');
    try {
        const res  = await fetch(OTP_API + '?action=send', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ mobile })
        });
        const data = await res.json();

        if (data.success) {
            _mobile = mobile;
            document.getElementById('otpSentMsg').innerHTML =
                'OTP sent to <strong>' + data.masked_email + '</strong>.<br>' +
                'Valid for <strong>10 minutes</strong>. Check your inbox (and spam folder).';

            // Clear all OTP boxes
            for (let i = 0; i < 6; i++) {
                const b = document.getElementById('ob' + i);
                if (b) { b.value = ''; b.classList.remove('filled','error'); }
            }
            goToStep(2);
            startCountdown(600);
            setTimeout(() => document.getElementById('ob0')?.focus(), 150);

        } else if (data.no_email) {
            // Guest has no email — show clear message to call reception
            showError('step1', data.error);

        } else {
            showError('step1', data.error || 'Failed to send OTP. Please try again.');
        }
    } catch (e) {
        showError('step1', 'Network error. Please check your connection and try again.');
    } finally {
        setLoading('sendOtpBtn', false,
            '<i class="bi bi-envelope-check me-2"></i>Send OTP to Email');
    }
}

// ── STEP 2: Resend OTP ────────────────────────────────────────────
async function resendOtp() {
    hideError('step2');
    document.getElementById('resendWrap').style.display  = 'none';
    document.getElementById('countdownWrap').style.display = 'inline-flex';
    for (let i = 0; i < 6; i++) {
        const b = document.getElementById('ob' + i);
        if (b) { b.value = ''; b.classList.remove('filled','error'); }
    }
    try {
        const res  = await fetch(OTP_API + '?action=send', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ mobile: _mobile })
        });
        const data = await res.json();
        if (data.success) {
            startCountdown(600);
            document.getElementById('ob0')?.focus();
        } else {
            showError('step2', data.error || 'Could not resend OTP. Please try again.');
            document.getElementById('resendWrap').style.display = 'block';
        }
    } catch (e) {
        showError('step2', 'Network error while resending OTP.');
        document.getElementById('resendWrap').style.display = 'block';
    }
}

// ── STEP 2: Verify OTP ────────────────────────────────────────────
async function verifyOtp() {
    hideError('step2');
    let otp = '';
    for (let i = 0; i < 6; i++) otp += (document.getElementById('ob' + i)?.value || '');

    if (otp.length < 6) {
        showError('step2', 'Please enter the complete 6-digit OTP.');
        for (let i = 0; i < 6; i++) {
            const b = document.getElementById('ob' + i);
            if (b && !b.value) {
                b.classList.add('error');
                setTimeout(() => b.classList.remove('error'), 400);
            }
        }
        return;
    }

    setLoading('verifyOtpBtn', true, '');
    try {
        const res  = await fetch(OTP_API + '?action=verify', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ mobile: _mobile, otp })
        });
        const data = await res.json();

        if (data.success) {
            _resetToken = data.reset_token;
            clearCountdown();
            goToStep(3);
            setTimeout(() => document.getElementById('s3Pass')?.focus(), 150);
        } else {
            // Shake boxes on wrong OTP
            for (let i = 0; i < 6; i++) {
                const b = document.getElementById('ob' + i);
                if (b) { b.classList.add('error'); setTimeout(() => b.classList.remove('error'), 400); }
            }
            if (data.expired || data.locked) {
                showError('step2',
                    data.error +
                    ' <a style="color:#721c24;font-weight:700;cursor:pointer;" onclick="goToStep(1)">Request new OTP</a>');
            } else {
                showError('step2', data.error || 'Incorrect OTP. Please try again.');
            }
        }
    } catch (e) {
        showError('step2', 'Network error. Please try again.');
    } finally {
        setLoading('verifyOtpBtn', false, '<i class="bi bi-check-circle me-2"></i>Verify OTP');
    }
}

// ── STEP 3: Reset password ────────────────────────────────────────
async function resetPassword() {
    hideError('step3');
    const pass = document.getElementById('s3Pass').value;
    const conf = document.getElementById('s3Conf').value;

    if (!pass || pass.length < 8) {
        showError('step3', 'Password must be at least 8 characters.');
        document.getElementById('s3Pass').focus();
        return;
    }
    if (pass !== conf) {
        showError('step3', 'Passwords do not match. Please re-enter.');
        document.getElementById('s3Conf').focus();
        return;
    }

    setLoading('resetPassBtn', true, '');
    try {
        const res  = await fetch(OTP_API + '?action=reset_password', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ reset_token: _resetToken, new_password: pass })
        });
        const data = await res.json();

        if (data.success) {
            goToStep(4);
        } else {
            showError('step3', data.error || 'Password update failed. Please try again.');
            if (data.expired) setTimeout(() => goToStep(1), 2500);
        }
    } catch (e) {
        showError('step3', 'Network error. Please try again.');
    } finally {
        setLoading('resetPassBtn', false, '<i class="bi bi-lock-fill me-2"></i>Update Password');
    }
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>