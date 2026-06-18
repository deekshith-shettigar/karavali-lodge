<?php
$pageTitle = "Contact Us - Karavali Lodge";
require_once __DIR__ . '/../includes/config.php';

$success = false;
$errors  = [];

// ── CSRF token ────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token first
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        http_response_code(403);
        die('Invalid request. Please reload the page and try again.');
    }

    $name    = sanitize($_POST['name']    ?? '');
    $email   = sanitize($_POST['email']   ?? '');
    $mobile  = sanitize($_POST['mobile']  ?? '');
    $subject = sanitize($_POST['subject'] ?? '');
    $message = sanitize($_POST['message'] ?? '');

    if (!$name)    $errors[] = 'Your name is required.';
    if (!$message) $errors[] = 'Please write a message.';
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Please enter a valid email address (e.g. name@example.com).';

    if (empty($errors)) {
        // ── Rate limit: max 3 messages per session per hour ───────
        $now = time();
        if (!isset($_SESSION['contact_submissions'])) {
            $_SESSION['contact_submissions'] = [];
        }
        // Remove entries older than 1 hour
        $_SESSION['contact_submissions'] = array_filter(
            $_SESSION['contact_submissions'],
            fn($t) => ($now - $t) < 3600
        );
        if (count($_SESSION['contact_submissions']) >= 3) {
            $errors[] = 'You have sent too many messages. Please wait an hour before trying again.';
        } else {
            $db = getDB();
            $stmt = $db->prepare("INSERT INTO contact_messages (name, email, mobile, subject, message) VALUES (?,?,?,?,?)");
            $stmt->execute([$name, $email, $mobile, $subject, $message]);
            $_SESSION['contact_submissions'][] = $now;
            $success = true;
        }
    }
}

// ── Load admin replies for logged-in user ─────────────────────────
$myMessages = [];
$guest = getLoggedInGuest();

// Guard: if session exists but guest record is missing, clear and redirect to login
if ($guest === null && isLoggedIn()) {
    session_destroy();
    redirect(SITE_URL . '/pages/login.php?msg=session_expired');
}

if ($guest) {
    $db = getDB();
    $params = [];
    $where  = [];
    if (!empty($guest['mobile'])) { $where[] = 'mobile = ?'; $params[] = $guest['mobile']; }
    if (!empty($guest['email']))  { $where[] = 'email = ?';  $params[] = $guest['email'];  }
    if ($where) {
        $q = $db->prepare("SELECT * FROM contact_messages WHERE (" . implode(' OR ', $where) . ") ORDER BY created_at DESC LIMIT 10");
        $q->execute($params);
        $myMessages = $q->fetchAll();
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
            <span class="current">Contact Us</span>
        </div>
        <h1>Get In Touch</h1>
        <p>We're always happy to hear from you</p>
    </div>
</div>

<section class="section-pad" style="background:var(--cream)">
    <div class="container">
        <!-- Contact Cards Row -->
        <div class="row g-4 mb-5">
            <?php
            $contacts = [
                ['icon' => 'bi-telephone-fill', 'title' => 'Phone',    'lines' => ['0824-2389156', '0824-4178293']],
                ['icon' => 'bi-envelope-fill',  'title' => 'Email',    'lines' => ['enquiry@karavalilodge.com']],
                ['icon' => 'bi-geo-alt-fill',   'title' => 'Address',  'lines' => ['K.S. Rao Road,', 'Mangalore - 575001']],
                ['icon' => 'bi-clock-fill',     'title' => 'Hours',    'lines' => ['Reception: 24/7', 'Check-In: 12PM | Out: 11AM']],
            ];
            foreach ($contacts as $c):
            ?>
            <div class="col-md-6 col-lg-3">
                <div class="contact-card text-center">
                    <div class="contact-icon mx-auto">
                        <i class="<?= $c['icon'] ?>"></i>
                    </div>
                    <h5 style="font-family:'Playfair Display',serif;color:var(--primary);margin-bottom:10px"><?= $c['title'] ?></h5>
                    <?php foreach ($c['lines'] as $l): ?>
                        <p style="font-size:0.88rem;color:var(--text-muted);margin-bottom:4px"><?= $l ?></p>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="row g-5">
            <!-- Contact Form -->
            <div class="col-lg-7">
                <div style="background:var(--white);border-radius:var(--radius-lg);overflow:hidden;box-shadow:var(--shadow-md);border:1px solid var(--border)">
                    <div style="background:linear-gradient(135deg,var(--primary-dark),var(--primary));padding:28px 32px">
                        <h3 style="font-family:'Playfair Display',serif;color:var(--white);margin-bottom:4px">Send Us a Message</h3>
                        <p style="color:rgba(255,255,255,0.6);font-family:'Cormorant Garamond',serif;font-style:italic;margin:0">
                            We'll get back to you within 24 hours
                        </p>
                    </div>
                    <div style="padding:32px">
                        <?php if ($success): ?>
                        <div class="alert-hotel alert-success-hotel" data-auto-dismiss="5000">
                            <i class="bi bi-check-circle-fill"></i>
                            <strong>Message sent!</strong> We'll respond to your inquiry shortly.
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($errors)): ?>
                        <div class="alert-hotel alert-error-hotel">
                            <i class="bi bi-exclamation-circle-fill"></i>
                            <div><?= implode('<br>', $errors) ?></div>
                        </div>
                        <?php endif; ?>

                        <form method="POST" id="contactForm">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-group-label">Your Name *</label>
                                    <input type="text" name="name" class="form-control-hotel"
                                           placeholder="Full name"
                                           value="<?= $success ? '' : sanitize($_POST['name'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-group-label">Mobile</label>
                                    <input type="tel" name="mobile" class="form-control-hotel"
                                           placeholder="+91 XXXXX XXXXX"
                                           value="<?= $success ? '' : sanitize($_POST['mobile'] ?? '') ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-group-label">Email Address</label>
                                    <input type="email" name="email" class="form-control-hotel"
                                           placeholder="your@email.com"
                                           value="<?= $success ? '' : sanitize($_POST['email'] ?? '') ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-group-label">Subject</label>
                                    <select name="subject" class="form-select-hotel">
                                        <option value="">Select a subject</option>
                                        <option value="Booking Inquiry">Booking Inquiry</option>
                                        <option value="Room Information">Room Information</option>
                                        <option value="Amenities">Amenities & Services</option>
                                        <option value="Cancellation">Cancellation Request</option>
                                        <option value="Feedback">Feedback / Complaint</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-group-label">Message *</label>
                                    <textarea name="message" class="form-control-hotel" rows="5"
                                              placeholder="Write your message here..."
                                              style="resize:vertical" required><?= $success ? '' : sanitize($_POST['message'] ?? '') ?></textarea>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn-accent-hotel" style="padding:14px 36px;font-size:0.95rem;border-radius:8px">
                                        <i class="bi bi-send me-2"></i>Send Message
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Map + Social -->
            <div class="col-lg-5">
                <!-- Google Map -->
                <div style="border-radius:var(--radius-lg);overflow:hidden;box-shadow:var(--shadow-md);margin-bottom:24px;border:1px solid var(--border)">
                    <iframe
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d248872.838281923!2d74.688909825511!3d12.930966197626665!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3ba35a4c37bf488f%3A0x827bbc7a74fcfe64!2sMangaluru%2C%20Karnataka!5e0!3m2!1sen!2sin!4v1781414115313!5m2!1sen!2sin"
                        width="100%" height="300"
                        style="border:0;display:block;"
                        allowfullscreen="" loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade">
                    </iframe>
                </div>

                <!-- Quick Info -->
                <div style="background:var(--white);border-radius:var(--radius-lg);padding:24px;border:1px solid var(--border);box-shadow:var(--shadow-sm)">
                    <h5 style="font-family:'Playfair Display',serif;color:var(--primary);margin-bottom:18px">Quick Info</h5>
                    <?php
                    $quickInfo = [
                        ['icon' => 'bi-car-front',   'text' => 'Free parking available'],
                        ['icon' => 'bi-airplane',    'text' => 'Airport transfer on request'],
                        ['icon' => 'bi-wifi',        'text' => 'Free WiFi throughout'],
                        ['icon' => 'bi-credit-card', 'text' => 'All payments accepted'],
                        ['icon' => 'bi-shield-check','text' => '24/7 security'],
                        ['icon' => 'bi-star',        'text' => 'Concierge available anytime'],
                    ];
                    foreach ($quickInfo as $q):
                    ?>
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;font-size:0.88rem;color:var(--text-body)">
                        <div style="width:32px;height:32px;background:var(--accent-pale);border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--accent);flex-shrink:0">
                            <i class="<?= $q['icon'] ?>"></i>
                        </div>
                        <?= $q['text'] ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if ($guest && !empty($myMessages)): ?>
<section class="section-pad" style="background:var(--white);border-top:1px solid var(--border);padding-top:40px;padding-bottom:60px;">
    <div class="container">
        <h3 style="font-family:'Playfair Display',serif;color:var(--primary);margin-bottom:6px;">
            <i class="bi bi-chat-square-text me-2" style="color:var(--accent)"></i>My Messages &amp; Replies
        </h3>
        <p style="color:var(--text-muted);font-size:0.88rem;margin-bottom:28px;">
            Your previous messages and responses from our team.
        </p>
        <div style="display:flex;flex-direction:column;gap:16px;max-width:820px;">
            <?php foreach ($myMessages as $msg): ?>
            <div style="background:var(--cream);border-radius:16px;border:1px solid var(--border);overflow:hidden;box-shadow:0 2px 12px rgba(59,26,10,0.06);">
                <!-- Header -->
                <div style="background:linear-gradient(135deg,var(--primary-dark),var(--primary));padding:14px 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <?php if ($msg['subject']): ?>
                        <span style="background:rgba(196,148,58,0.25);color:#D4AD5E;font-size:0.75rem;padding:3px 10px;border-radius:50px;font-weight:600;">
                            <i class="bi bi-tag me-1"></i><?= sanitize($msg['subject']) ?>
                        </span>
                        <?php endif; ?>
                        <span style="font-size:0.78rem;color:rgba(255,255,255,0.55);">
                            <i class="bi bi-clock me-1"></i><?= date('d M Y, h:i A', strtotime($msg['created_at'])) ?>
                        </span>
                    </div>
                    <?php if (!empty($msg['admin_reply'])): ?>
                    <span style="background:#28a745;color:#fff;font-size:0.7rem;padding:3px 10px;border-radius:50px;font-weight:700;">
                        <i class="bi bi-check2-circle me-1"></i>Replied
                    </span>
                    <?php else: ?>
                    <span style="background:rgba(255,255,255,0.15);color:rgba(255,255,255,0.7);font-size:0.7rem;padding:3px 10px;border-radius:50px;">
                        <i class="bi bi-hourglass-split me-1"></i>Awaiting Reply
                    </span>
                    <?php endif; ?>
                </div>
                <div style="padding:18px 20px;">
                    <!-- Guest message -->
                    <div style="display:flex;gap:10px;margin-bottom:<?= !empty($msg['admin_reply']) ? '16px' : '0' ?>;">
                        <div style="width:36px;height:36px;border-radius:50%;background:var(--accent-pale);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-weight:700;color:var(--accent);">
                            <?= strtoupper(substr($msg['name'], 0, 1)) ?>
                        </div>
                        <div style="flex:1;">
                            <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:5px;font-weight:600;">You</div>
                            <div style="background:#fff;border-radius:0 12px 12px 12px;padding:12px 16px;font-size:0.9rem;color:var(--text-dark);line-height:1.6;border:1px solid var(--border);">
                                <?= nl2br(sanitize($msg['message'])) ?>
                            </div>
                        </div>
                    </div>
                    <!-- Admin reply -->
                    <?php if (!empty($msg['admin_reply'])): ?>
                    <div style="display:flex;gap:10px;flex-direction:row-reverse;">
                        <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#C4943A,#D4AD5E);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-weight:700;color:#2A1007;font-size:0.85rem;">H</div>
                        <div style="flex:1;text-align:right;">
                            <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:5px;font-weight:600;">
                                Karavali Lodge
                                <?php if ($msg['replied_at']): ?>
                                &nbsp;<span style="font-weight:400;"><?= date('d M Y, h:i A', strtotime($msg['replied_at'])) ?></span>
                                <?php endif; ?>
                            </div>
                            <div style="background:linear-gradient(135deg,var(--primary-dark),var(--primary));border-radius:12px 0 12px 12px;padding:12px 16px;font-size:0.9rem;color:#fff;line-height:1.6;display:inline-block;max-width:90%;text-align:left;">
                                <?= nl2br(sanitize($msg['admin_reply'])) ?>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div style="text-align:center;padding:12px 0 2px;font-size:0.82rem;color:var(--text-muted);">
                        <i class="bi bi-hourglass-split me-1" style="color:var(--accent)"></i>Our team will respond within 24 hours.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($success): ?>
<div id="successPopup" style="
    position:fixed;inset:0;z-index:9999;
    display:flex;align-items:center;justify-content:center;
    background:rgba(0,0,0,0.55);backdrop-filter:blur(4px);">
    <div style="
        background:#fff;border-radius:20px;padding:48px 40px;
        max-width:420px;width:90%;text-align:center;
        box-shadow:0 24px 64px rgba(0,0,0,0.25);
        animation:popIn 0.3s ease;">
        <style>@keyframes popIn{from{transform:scale(0.85);opacity:0}to{transform:scale(1);opacity:1}}</style>

        <!-- Success icon -->
        <div style="width:72px;height:72px;background:linear-gradient(135deg,#28a745,#20c997);
                    border-radius:50%;display:flex;align-items:center;justify-content:center;
                    margin:0 auto 20px;box-shadow:0 8px 24px rgba(40,167,69,0.3);">
            <i class="bi bi-check-lg" style="font-size:2rem;color:#fff;"></i>
        </div>

        <h3 style="font-family:'Playfair Display',serif;color:#3B1A0A;
                   font-size:1.5rem;margin-bottom:10px;">Message Sent!</h3>
        <p style="color:#8B7355;font-family:'Cormorant Garamond',serif;
                  font-style:italic;font-size:1rem;margin-bottom:28px;">
            Thank you for reaching out. We'll get back to you within 24 hours.
        </p>

        <button onclick="document.getElementById('successPopup').style.display='none'"
                style="background:linear-gradient(135deg,#C4943A,#D4AD5E);color:#2A1007;
                       border:none;border-radius:10px;padding:14px 36px;font-weight:700;
                       font-size:0.95rem;cursor:pointer;
                       box-shadow:0 6px 20px rgba(196,148,58,0.35);">
            <i class="bi bi-check2 me-2"></i>OK, Got It
        </button>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>