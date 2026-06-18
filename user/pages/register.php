<?php
$pageTitle = "Register - Karavali Lodge";
require_once __DIR__ . '/../includes/config.php';

// Prevent browser from caching this page
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Already logged in → go to bookings
if (isLoggedIn()) redirect(SITE_URL . '/pages/my-bookings.php');

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = sanitize($_POST['name']             ?? '');
    $mobile      = sanitize($_POST['mobile']           ?? '');
    $email       = sanitize($_POST['email']            ?? '');
    $nationality = sanitize($_POST['nationality']      ?? 'Indian');
    $address     = sanitize($_POST['address']          ?? '');
    $password    = $_POST['password']                  ?? '';
    $confirmPass = $_POST['confirm_password']          ?? '';

    // Validation
    if (!$name)                     $errors[] = 'Full name is required.';
    if (!$mobile)                   $errors[] = 'Mobile number is required.';
    if (!$email)                    $errors[] = 'Email address is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (!$nationality)              $errors[] = 'Nationality is required.';
    if (!$address)                  $errors[] = 'City is required.';
    if (strlen($mobile) < 10)      $errors[] = 'Enter a valid 10-digit mobile number.';
    if (!$password)                 $errors[] = 'Password is required.';
    else $errors = array_merge($errors, validatePassword($password, $confirmPass));

    if (empty($errors)) {
        $db           = getDB();
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // ── Guest registration only ───────────────────────────────
        // Admin accounts are managed separately via admin/setup_admin.php
        $check = $db->prepare("SELECT id FROM guests WHERE mobile = ?");
        $check->execute([$mobile]);
        if ($check->fetch()) {
            $errors[] = 'This mobile number is already registered. Please <a href="' . SITE_URL . '/pages/login.php" style="color:#C4943A;font-weight:600">Sign In</a> instead.';
        } else {
            $id = generateId();
            $db->prepare("INSERT INTO guests (id, name, mobile, email, nationality, address, password_hash)
                          VALUES (?, ?, ?, ?, ?, ?, ?)")
               ->execute([$id, $name, $mobile, $email, $nationality, $address, $passwordHash]);

            // Regenerate session ID before setting auth data to prevent
            // session fixation — same pattern used in login.php
            session_regenerate_id(true);
            $_SESSION['guest_id']   = $id;
            $_SESSION['guest_name'] = $name;
            redirect(SITE_URL . '/pages/my-bookings.php');
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

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,400&family=Cormorant+Garamond:ital,wght@1,300;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">

    <!-- Bootstrap + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="<?= SITE_URL ?>/css/style.css" rel="stylesheet">

    <style>
        body {
            background: linear-gradient(135deg, #2A1007 0%, #3B1A0A 50%, #5C2E15 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 60px 0;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse at 20% 50%, rgba(196,148,58,0.12) 0%, transparent 55%),
                radial-gradient(ellipse at 80% 20%, rgba(196,148,58,0.08) 0%, transparent 45%);
            pointer-events: none;
        }

                .register-wrapper {
            width: 100%;
            position: relative;
            z-index: 1;
        }

        .register-card {
            background: #FFFFFF;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 24px 64px rgba(0,0,0,0.35);
            border: 1px solid rgba(196,148,58,0.15);
            max-width: 520px;
            width: 100%;
        }

        /* Card top banner — matches login white style */
        .register-banner {
            background: #FFFFFF;
            padding: 36px 36px 24px;
            text-align: center;
        }

        .register-emblem {
            background: none;
            box-shadow: none;
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
        }

        .register-banner h2 {
            font-family: 'Playfair Display', serif;
            color: #3B1A0A;
            font-size: 1.7rem;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .register-banner p {
            font-family: 'Cormorant Garamond', serif;
            color: #8B7355;
            font-style: italic;
            font-size: 1rem;
            margin: 0;
        }

        /* Form body */
        .register-body {
            padding: 36px;
        }

        /* Field styling */
        .field-group {
            margin-bottom: 18px;
        }

        .field-label {
            display: block;
            font-size: 0.72rem;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            font-weight: 600;
            color: #8B7355;
            margin-bottom: 7px;
            font-family: 'Jost', sans-serif;
        }

        .field-label .req {
            color: #C4943A;
            margin-left: 2px;
        }

        .field-input {
            width: 100%;
            border: 1.5px solid #E8D9C0;
            border-radius: 9px;
            padding: 12px 14px;
            font-family: 'Jost', sans-serif;
            font-size: 0.92rem;
            color: #2A1007;
            background: #FBF7F2;
            transition: all 0.3s ease;
            outline: none;
        }

        .field-input:focus {
            border-color: #C4943A;
            background: #FFFFFF;
            box-shadow: 0 0 0 3px rgba(196,148,58,0.12);
        }

        .field-input::placeholder {
            color: #C4B89A;
        }

        .field-icon-wrap {
            position: relative;
        }

        .field-icon-wrap .field-input {
            padding-left: 42px;
        }

        .field-icon-wrap .f-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #C4943A;
            font-size: 1rem;
        }

        /* Submit button */
        .btn-register {
            width: 100%;
            background: linear-gradient(135deg, #C4943A, #D4AD5E);
            color: #2A1007;
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-family: 'Jost', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 6px 20px rgba(196,148,58,0.35);
            margin-top: 8px;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(196,148,58,0.5);
            background: linear-gradient(135deg, #D4AD5E, #C4943A);
        }

        /* Big dots only when typing password, normal placeholder */
        .pass-field { font-size: 1rem !important; }
        .pass-field:not(:placeholder-shown) {
            font-size: 1.5rem !important;
            letter-spacing: 4px !important;
        }

        /* Divider */
        .auth-divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 20px 0;
        }

        .auth-divider::before,
        .auth-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #E8D9C0;
        }

        .auth-divider span {
            font-size: 0.78rem;
            color: #8B7355;
            font-family: 'Jost', sans-serif;
            white-space: nowrap;
        }

        /* Login link */
        .login-link-box {
            text-align: center;
            padding: 16px;
            background: #FBF7F2;
            border-top: 1px solid #E8D9C0;
        }

        .login-link-box p {
            font-size: 0.88rem;
            color: #8B7355;
            margin: 0;
            font-family: 'Jost', sans-serif;
        }

        .login-link-box a {
            color: #C4943A;
            font-weight: 600;
            text-decoration: none;
        }

        .login-link-box a:hover {
            color: #3B1A0A;
        }

        /* Error alert */
        .alert-err {
            background: #FEF2F2;
            border: 1px solid #FECACA;
            color: #991B1B;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 0.88rem;
            font-family: 'Jost', sans-serif;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }

        .alert-err i { color: #EF4444; font-size: 1rem; margin-top: 1px; flex-shrink: 0; }

        /* Back to home */
        .back-home {
            text-align: center;
            margin-top: 20px;
            position: relative;
            z-index: 1;
        }

        .back-home a {
            color: rgba(255,255,255,0.5);
            font-size: 0.85rem;
            font-family: 'Jost', sans-serif;
            text-decoration: none;
            transition: color 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .back-home a:hover { color: #D4AD5E; }

        /* Optional field hint */
        .optional-hint {
            font-size: 0.72rem;
            color: #C4B89A;
            font-family: 'Jost', sans-serif;
            margin-top: 4px;
        }

        /* Row gap fix */
        .field-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .req-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.75rem;
            font-family: 'Jost', sans-serif;
            color: #8B7355;
            transition: color .2s;
        }
        .req-item.met { color: #16A34A; }
        .req-item.met i { color: #16A34A !important; }
        .req-item i { font-size: 0.8rem; }

        @media (max-width: 480px) {
            .register-body    { padding: 24px 20px; }
            .register-banner  { padding: 24px 20px; }
            .field-row        { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="register-wrapper">
    <div class="container">
        <div class="d-flex justify-content-center">
            <div class="register-card">

                <!-- Banner -->
                <div class="register-banner">
                    <div class="register-emblem" style="background:none;box-shadow:none;width:80px;height:80px;margin:0 auto 12px;">
                        <img src="<?= SITE_URL ?>/images/Lodge_Logoo.png"
                             alt="Karavali Lodge"
                             style="width:80px;height:80px;object-fit:contain;">
                    </div>
                    <h2>Create Account</h2>
                    <p>Join us and enjoy seamless bookings</p>
                </div>

                <!-- Form Body -->
                <div class="register-body">

                    <?php if (!empty($errors)): ?>
                    <div class="alert-err">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <div>
                            <?php foreach ($errors as $e): ?>
                                <div><?= $e ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <form method="POST" id="registerForm" novalidate>

                        <!-- Full Name -->
                        <div class="field-group">
                            <label class="field-label">Full Name <span class="req">*</span></label>
                            <div class="field-icon-wrap">
                                <i class="bi bi-person f-icon"></i>
                                <input
                                    type="text"
                                    name="name"
                                    class="field-input"
                                    placeholder="e.g. Rajesh Kumar"
                                    value="<?= sanitize($_POST['name'] ?? '') ?>"
                                    required
                                    maxlength="100"
                                >
                            </div>
                        </div>

                        <!-- Mobile -->
                        <div class="field-group">
                            <label class="field-label">Mobile Number <span class="req">*</span></label>
                            <div class="field-icon-wrap">
                                <i class="bi bi-telephone f-icon"></i>
                                <input
                                    type="tel"
                                    name="mobile"
                                    class="field-input"
                                    placeholder="10-digit mobile number"
                                    value="<?= sanitize($_POST['mobile'] ?? '') ?>"
                                    required
                                    maxlength="15"
                                    id="mobileField"
                                >
                            </div>
                            <div class="optional-hint" id="mobileHint"></div>
                        </div>

                        <!-- Email -->
                        <div class="field-group">
                            <label class="field-label">Email Address <span style="color:#C4943A">*</span></label>
                            <div class="field-icon-wrap">
                                <i class="bi bi-envelope f-icon"></i>
                                <input
                                    type="email"
                                    name="email"
                                    class="field-input"
                                    placeholder="your@email.com"
                                    value="<?= sanitize($_POST['email'] ?? '') ?>"
                                    maxlength="100"
                                    required
                                >
                            </div>
                        </div>

                        <!-- Nationality + Address row -->
                        <div class="field-row">
                            <div class="field-group" style="margin-bottom:0">
                                <label class="field-label">Nationality <span style="color:#C4943A">*</span></label>
                                <div class="field-icon-wrap">
                                    <i class="bi bi-globe f-icon"></i>
                                    <input
                                        type="text"
                                        name="nationality"
                                        class="field-input"
                                        placeholder="Indian"
                                        value="<?= sanitize($_POST['nationality'] ?? 'Indian') ?>"
                                        maxlength="50"
                                        required
                                    >
                                </div>
                            </div>
                            <div class="field-group" style="margin-bottom:0">
                                <label class="field-label">City <span style="color:#C4943A">*</span></label>
                                <div class="field-icon-wrap">
                                    <i class="bi bi-geo-alt f-icon"></i>
                                    <input
                                        type="text"
                                        name="address"
                                        class="field-input"
                                        placeholder="Your city"
                                        value="<?= sanitize($_POST['address'] ?? '') ?>"
                                        maxlength="100"
                                        required
                                    >
                                </div>
                            </div>
                        </div>

                        <!-- Password -->
                        <div class="field-group" style="margin-top:24px;">
                            <label class="field-label">PASSWORD <span class="req">*</span></label>
                            <div class="field-icon-wrap" style="position:relative;">
                                <i class="bi bi-lock f-icon" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#C4943A;font-size:1rem;z-index:1;"></i>
                                <input
                                    type="password"
                                    name="password"
                                    id="passwordField"
                                    class="field-input"
                                    placeholder="Minimum 8 characters"
                                    required
                                    minlength="8"
                                    style="padding-left:42px;padding-right:44px;font-size:1rem;height:50px;"
                                    oninput="checkStrength(this.value)"
                                >
                                <button type="button" onclick="togglePass('passwordField','eyeIcon1')"
                                    style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:#C4943A;cursor:pointer;font-size:1.1rem;padding:0;">
                                    <i class="bi bi-eye" id="eyeIcon1"></i>
                                </button>
                            </div>

                            <!-- Strength bar -->
                            <div id="strengthWrap" style="display:none;margin-top:8px;">
                                <div style="height:5px;background:#EEE;border-radius:4px;overflow:hidden;">
                                    <div id="strengthBar"
                                         style="height:100%;width:0;border-radius:4px;transition:width .35s,background .35s;">
                                    </div>
                                </div>
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:5px;">
                                    <span id="strengthLabel"
                                          style="font-size:0.75rem;font-family:'Jost',sans-serif;font-weight:600;">
                                    </span>
                                    <span id="strengthTip"
                                          style="font-size:0.72rem;color:#8B7355;font-family:'Jost',sans-serif;">
                                    </span>
                                </div>
                                <!-- Requirements checklist -->
                                <div id="reqList" style="margin-top:8px;display:grid;grid-template-columns:1fr 1fr;gap:3px 12px;">
                                    <div class="req-item" id="req-len">
                                        <i class="bi bi-x-circle-fill" style="color:#ddd;"></i>
                                        <span>8+ characters</span>
                                    </div>
                                    <div class="req-item" id="req-upper">
                                        <i class="bi bi-x-circle-fill" style="color:#ddd;"></i>
                                        <span>Uppercase letter</span>
                                    </div>
                                    <div class="req-item" id="req-num">
                                        <i class="bi bi-x-circle-fill" style="color:#ddd;"></i>
                                        <span>Number</span>
                                    </div>
                                    <div class="req-item" id="req-special">
                                        <i class="bi bi-x-circle-fill" style="color:#ddd;"></i>
                                        <span>Special character</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="field-group">
                            <label class="field-label">CONFIRM PASSWORD <span class="req">*</span></label>
                            <div class="field-icon-wrap" style="position:relative;">
                                <i class="bi bi-lock-fill f-icon" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#C4943A;font-size:1rem;z-index:1;"></i>
                                <input
                                    type="password"
                                    name="confirm_password"
                                    id="confirmPassField"
                                    class="field-input pass-field"
                                    placeholder="Re-enter your password"
                                    required
                                    style="padding-left:42px;padding-right:44px;font-size:1rem;height:50px;"
                                >
                                <button type="button" onclick="togglePass('confirmPassField','eyeIcon2')"
                                    style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:#C4943A;cursor:pointer;font-size:1.1rem;padding:0;">
                                    <i class="bi bi-eye" id="eyeIcon2"></i>
                                </button>
                            </div>
                            <div id="passHint" style="font-size:0.78rem;margin-top:5px;display:none;"></div>
                        </div>

                        <!-- Terms -->
                        <div style="display:flex;align-items:flex-start;gap:10px;margin:20px 0 4px">
                            <input
                                type="checkbox"
                                id="agreeTerms"
                                required
                                style="width:17px;height:17px;margin-top:2px;accent-color:#C4943A;flex-shrink:0;cursor:pointer"
                            >
                            <label for="agreeTerms" style="font-size:0.83rem;color:#8B7355;font-family:'Jost',sans-serif;cursor:pointer;line-height:1.5">
                                I agree to the
                                <a href="#" style="color:#C4943A;font-weight:600">Terms & Conditions</a>
                                and
                                <a href="#" style="color:#C4943A;font-weight:600">Privacy Policy</a>
                            </label>
                        </div>

                        <!-- Submit -->
                        <button type="submit" class="btn-register">
                            <i class="bi bi-person-check"></i>
                            Create My Account
                        </button>

                    </form>
                </div>

                <!-- Login Link -->
                <div class="login-link-box">
                    <p>
                        Already have an account?
                        <a href="<?= SITE_URL ?>/pages/login.php">Sign In</a>
                        &nbsp;·&nbsp;
                        <a href="<?= SITE_URL ?>/index.php" style="color:#8B7355">
                            <i class="bi bi-house me-1"></i>Home
                        </a>
                    </p>
                    <p style="margin-top:8px;margin-bottom:0;">
                        <a href="<?= SITE_URL ?>/index.php" style="color:#C4943A;font-size:0.92rem;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                            <i class="bi bi-arrow-left"></i> Back to Hotel Website
                        </a>
                    </p>
                </div>

            </div><!-- /.register-card -->
        </div>
    </div>
</div>

<script>
// Live mobile check — show hint if number already registered
const mobileField = document.getElementById('mobileField');
const mobileHint  = document.getElementById('mobileHint');

let debounceTimer;
mobileField?.addEventListener('input', function () {
    clearTimeout(debounceTimer);
    const val = this.value.trim();
    if (val.length < 10) { mobileHint.textContent = ''; return; }

    debounceTimer = setTimeout(async () => {
        try {
            const res  = await fetch('<?= SITE_URL ?>/pages/check-guest.php?mobile=' + encodeURIComponent(val));
            const data = await res.json();
            if (data.exists) {
                mobileHint.style.color = '#C4943A';
                mobileHint.innerHTML  = '<i class="bi bi-info-circle me-1"></i>Account found — you will be signed in automatically.';
            } else {
                mobileHint.style.color = '#16A34A';
                mobileHint.innerHTML  = '<i class="bi bi-check-circle me-1"></i>Mobile number available.';
            }
        } catch (e) {
            mobileHint.textContent = '';
        }
    }, 500);
});

// Nice popup instead of browser alert
function showPopup(message, icon, color) {
    // Remove existing popup if any
    const existing = document.getElementById('validationPopup');
    if (existing) existing.remove();

    const popup = document.createElement('div');
    popup.id = 'validationPopup';
    popup.style.cssText = `
        position:fixed;inset:0;z-index:99999;
        display:flex;align-items:center;justify-content:center;
        background:rgba(0,0,0,0.5);backdrop-filter:blur(4px);
        animation:fadeIn 0.2s ease;
    `;
    popup.innerHTML = `
        <style>@keyframes fadeIn{from{opacity:0}to{opacity:1}}
               @keyframes slideIn{from{transform:scale(0.85);opacity:0}to{transform:scale(1);opacity:1}}</style>
        <div style="background:#fff;border-radius:18px;padding:36px 32px;max-width:360px;
                    width:90%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.2);
                    animation:slideIn 0.25s ease;">
            <div style="width:60px;height:60px;background:${color}22;border-radius:50%;
                        display:flex;align-items:center;justify-content:center;
                        margin:0 auto 16px;font-size:1.6rem;">
                <i class="bi ${icon}" style="color:${color};"></i>
            </div>
            <p style="font-family:'Jost',sans-serif;font-size:1rem;color:#3B1A0A;
                      margin-bottom:24px;line-height:1.6;">${message}</p>
            <button onclick="document.getElementById('validationPopup').remove()"
                    style="background:linear-gradient(135deg,#C4943A,#D4AD5E);color:#2A1007;
                           border:none;border-radius:10px;padding:12px 32px;
                           font-weight:700;font-size:0.95rem;cursor:pointer;
                           box-shadow:0 6px 20px rgba(196,148,58,0.35);">
                OK, Got It
            </button>
        </div>
    `;
    // Close on outside click
    popup.addEventListener('click', function(e) {
        if (e.target === popup) popup.remove();
    });
    document.body.appendChild(popup);
}

// ── Password strength checker ────────────────────────────────────
const COMMON_PASSWORDS = new Set([
    '12345678','password','password1','qwerty123','abc12345',
    '11111111','00000000','iloveyou','welcome1','admin123',
    '123456789','1234567890','pass1234','letmein1','monkey123'
]);

function checkStrength(val) {
    const wrap  = document.getElementById('strengthWrap');
    const bar   = document.getElementById('strengthBar');
    const label = document.getElementById('strengthLabel');
    const tip   = document.getElementById('strengthTip');
    if (!wrap) return;

    if (!val) { wrap.style.display = 'none'; return; }
    wrap.style.display = 'block';

    const checks = {
        len:     val.length >= 8,
        upper:   /[A-Z]/.test(val),
        num:     /[0-9]/.test(val),
        special: /[^A-Za-z0-9]/.test(val),
    };

    // Update checklist items
    Object.keys(checks).forEach(k => {
        const el = document.getElementById('req-' + k);
        if (!el) return;
        const icon = el.querySelector('i');
        if (checks[k]) {
            el.classList.add('met');
            icon.className = 'bi bi-check-circle-fill';
        } else {
            el.classList.remove('met');
            icon.className = 'bi bi-x-circle-fill';
            icon.style.color = '#ddd';
        }
    });

    const isCommon = COMMON_PASSWORDS.has(val.toLowerCase());
    let score = Object.values(checks).filter(Boolean).length;
    if (val.length >= 12) score++;
    if (isCommon) score = 0;

    const levels = [
        { pct:'15%',  bg:'#ef4444', lbl:'Very Weak',  tip:'Too easy to guess'         },
        { pct:'30%',  bg:'#f97316', lbl:'Weak',       tip:'Add uppercase or numbers'  },
        { pct:'55%',  bg:'#eab308', lbl:'Fair',       tip:'Getting better!'           },
        { pct:'75%',  bg:'#22c55e', lbl:'Strong',     tip:'Good password'             },
        { pct:'100%', bg:'#16a34a', lbl:'Very Strong',tip:'Excellent password! ✓'     },
    ];

    const lv = levels[Math.min(score, 4)];
    bar.style.width      = lv.pct;
    bar.style.background = lv.bg;
    label.textContent    = lv.lbl;
    label.style.color    = lv.bg;
    tip.textContent      = isCommon ? '⚠ This password is too common' : lv.tip;
    tip.style.color      = isCommon ? '#ef4444' : '#8B7355';
}

// ── Form validation ───────────────────────────────────────────────
document.getElementById('registerForm')?.addEventListener('submit', function (e) {
    const name     = this.querySelector('[name="name"]').value.trim();
    const mobile   = this.querySelector('[name="mobile"]').value.trim();
    const password = document.getElementById('passwordField').value;
    const confirm  = document.getElementById('confirmPassField').value;
    const agree    = document.getElementById('agreeTerms').checked;

    if (!name) {
        e.preventDefault();
        showPopup('Please enter your full name.', 'bi-person-fill', '#C4943A');
        return;
    }
    if (mobile.replace(/\D/g,'').length < 10) {
        e.preventDefault();
        showPopup('Please enter a valid 10-digit mobile number.', 'bi-telephone-fill', '#C4943A');
        return;
    }
    if (password.length < 8) {
        e.preventDefault();
        showPopup('Password must be at least 8 characters long.', 'bi-lock-fill', '#ef4444');
        document.getElementById('passwordField').focus();
        return;
    }
    if (COMMON_PASSWORDS.has(password.toLowerCase())) {
        e.preventDefault();
        showPopup('This password is too common and easy to guess.<br>Please choose a stronger password.', 'bi-shield-x', '#ef4444');
        document.getElementById('passwordField').focus();
        return;
    }
    if (/^[0-9]+$/.test(password)) {
        e.preventDefault();
        showPopup('Password cannot be only numbers.<br>Add at least one letter.', 'bi-shield-x', '#ef4444');
        document.getElementById('passwordField').focus();
        return;
    }
    if (password !== confirm) {
        e.preventDefault();
        showPopup('Passwords do not match. Please re-enter.', 'bi-x-circle', '#ef4444');
        document.getElementById('confirmPassField').focus();
        return;
    }
    if (!agree) {
        e.preventDefault();
        showPopup('Please agree to the Terms &amp; Conditions to continue.', 'bi-shield-check', '#dc3545');
        return;
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePass(fieldId, iconId) {
    const inp  = document.getElementById(fieldId);
    const icon = document.getElementById(iconId);
    if (inp.type === 'password') {
        inp.type       = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        inp.type       = 'password';
        icon.className = 'bi bi-eye';
    }
}

// Live confirm password match check
document.getElementById('confirmPassField')?.addEventListener('input', function() {
    const pass    = document.getElementById('passwordField').value;
    const hint    = document.getElementById('passHint');
    hint.style.display = 'block';
    if (this.value === pass) {
        hint.style.color = '#28a745';
        hint.innerHTML   = '<i class="bi bi-check-circle me-1"></i>Passwords match';
    } else {
        hint.style.color = '#dc3545';
        hint.innerHTML   = '<i class="bi bi-x-circle me-1"></i>Passwords do not match';
    }
});
</script>
</body>
</html>