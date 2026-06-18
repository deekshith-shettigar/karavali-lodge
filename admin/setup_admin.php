<?php
// ============================================================
// Karavali Lodge — Admin Registration
// karavali_lodge/admin/setup_admin.php
//
// ── SECURITY INSTRUCTIONS ────────────────────────────────────
// 1. This page is protected by the secret key below.
// 2. Run it ONCE to create your admin account.
// 3. After creating your admin account, block this file
//    permanently by adding this to admin/.htaccess:
//
//      <Files "setup_admin.php">
//          Require all denied
//      </Files>
//
// URL (one-time use):
//   http://localhost/karavali_lodge/admin/setup_admin.php?key=SETUP_SECRET_KEY
// ============================================================

require_once __DIR__ . '/../user/includes/config.php';

// ── Secret key — 48-character random alphanumeric string ─────
// Generated fresh — much harder to guess than the old key.
// After use: block this file via .htaccess (see instructions above).
define('SETUP_SECRET_KEY', 'RHddF1eLPVa1mIjrWV7fgludyKTk65Lx351yqVEMB64hXqs1');

// ── Rate limit: max 5 key attempts per 10 minutes per IP ─────
// Prevents brute-forcing the key via automation.
function setupRateLimit(): void {
    $ip      = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $key     = 'setup_attempts_' . md5($ip);
    $data    = $_SESSION[$key] ?? ['count' => 0, 'since' => time()];

    // Reset window after 10 minutes
    if (time() - $data['since'] > 600) {
        $data = ['count' => 0, 'since' => time()];
    }

    if ($data['count'] >= 5) {
        $wait = max(1, (int) ceil((600 - (time() - $data['since'])) / 60));
        http_response_code(429);
        die("Too many attempts. Wait {$wait} minute(s).");
    }

    $data['count']++;
    $_SESSION[$key] = $data;
}

// ── Key check — wrong/missing key → 404 (looks like page doesn't exist) ──
$key = $_GET['key'] ?? $_POST['setup_key'] ?? $_SESSION['setup_key'] ?? '';
if ($key !== SETUP_SECRET_KEY) {
    setupRateLimit(); // increment counter on every wrong attempt
    http_response_code(404);
    die('<!DOCTYPE html><html><head><title>404 Not Found</title></head>
         <body><h1>Not Found</h1><p>The requested URL was not found on this server.</p></body></html>');
}

// ── Key correct — clear rate limit and persist key in session ────
$ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
unset($_SESSION['setup_attempts_' . md5($ip)]);
$_SESSION['setup_key'] = SETUP_SECRET_KEY;

// ── Auto-block: writes deny rule to .htaccess after admin created ──
// This means you NEVER need to manually edit .htaccess.
// Works on localhost (XAMPP) and on live hosting servers.
function autoBlockSetupPage(): void {
    $htaccess  = __DIR__ . '/.htaccess';
    $denyBlock = '
# Auto-added by setup_admin.php — blocks this file after first use
<Files "setup_admin.php">
    Require all denied
</Files>
';

    // Don't add twice
    if (file_exists($htaccess) && str_contains(file_get_contents($htaccess), 'setup_admin.php')) {
        return;
    }

    // Append to existing .htaccess or create new one
    file_put_contents($htaccess, $denyBlock, FILE_APPEND | LOCK_EX);
}

// ── Create admins table if it doesn't exist ──────────────────
try {
    getDB()->exec("CREATE TABLE IF NOT EXISTS admins (
        id            VARCHAR(64)  PRIMARY KEY,
        name          VARCHAR(100) NOT NULL,
        mobile        VARCHAR(20)  NOT NULL UNIQUE,
        email         VARCHAR(100) DEFAULT '',
        password_hash VARCHAR(255) NOT NULL,
        role          VARCHAR(20)  DEFAULT 'admin',
        created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
        updated_at    DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) {
    die('<p style="color:red;padding:20px">DB Error: ' . $e->getMessage() . '</p>');
}

$admins  = getDB()->query("SELECT id, name, mobile, email, created_at FROM admins ORDER BY created_at DESC")->fetchAll();
$message = '';
$msgType = '';

// ── Handle form submit ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // CREATE
    if ($action === 'create') {
        $name     = trim($_POST['name']     ?? '');
        $mobile   = preg_replace('/\D/', '', trim($_POST['mobile'] ?? ''));
        $email    = trim($_POST['email']    ?? '');
        $password = $_POST['password']      ?? '';
        $confirm  = $_POST['confirm']       ?? '';

        if (!$name || !$mobile || !$password) {
            $message = 'Name, mobile and password are required.';
            $msgType = 'error';
        } elseif (strlen($mobile) < 10) {
            $message = 'Enter a valid 10-digit mobile number.';
            $msgType = 'error';
        } elseif (strlen($password) < 6) {
            $message = 'Password must be at least 6 characters.';
            $msgType = 'error';
        } elseif ($password !== $confirm) {
            $message = 'Passwords do not match.';
            $msgType = 'error';
        } else {
            $chk = getDB()->prepare("SELECT id FROM admins WHERE mobile = ?");
            $chk->execute([$mobile]);
            if ($chk->fetch()) {
                $message = "An admin with mobile {$mobile} already exists.";
                $msgType = 'error';
            } else {
                $id   = generateId();
                $hash = password_hash($password, PASSWORD_DEFAULT);
                getDB()->prepare("INSERT INTO admins (id, name, mobile, email, password_hash, role) VALUES (?,?,?,?,?,'admin')")
                       ->execute([$id, $name, $mobile, $email, $hash]);

                // Auto-block this page — no manual .htaccess editing needed
                autoBlockSetupPage();

                $message = "Admin account created for {$name} ({$mobile}). This setup page has been automatically blocked for security. Next time you need it, remove the last 4 lines from admin/.htaccess";
                $msgType = 'success';
                $admins  = getDB()->query("SELECT id, name, mobile, email, created_at FROM admins ORDER BY created_at DESC")->fetchAll();
            }
        }
    }

    // RESET PASSWORD
    if ($action === 'reset') {
        $adminId  = $_POST['admin_id']  ?? '';
        $password = $_POST['password']  ?? '';
        $confirm  = $_POST['confirm']   ?? '';

        if (!$adminId || !$password) {
            $message = 'Select an admin and enter a new password.';
            $msgType = 'error';
        } elseif (strlen($password) < 6) {
            $message = 'Password must be at least 6 characters.';
            $msgType = 'error';
        } elseif ($password !== $confirm) {
            $message = 'Passwords do not match.';
            $msgType = 'error';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            getDB()->prepare("UPDATE admins SET password_hash = ?, updated_at = NOW() WHERE id = ?")
                   ->execute([$hash, $adminId]);
            $message = 'Password updated successfully.';
            $msgType = 'success';
            $admins  = getDB()->query("SELECT id, name, mobile, email, created_at FROM admins ORDER BY created_at DESC")->fetchAll();
        }
    }

    // DELETE
    if ($action === 'delete') {
        $adminId = $_POST['admin_id'] ?? '';
        if ($adminId) {
            getDB()->prepare("DELETE FROM admins WHERE id = ?")->execute([$adminId]);
            $message = 'Admin account deleted.';
            $msgType = 'info';
            $admins  = getDB()->query("SELECT id, name, mobile, email, created_at FROM admins ORDER BY created_at DESC")->fetchAll();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Registration — Karavali Lodge</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#f4f0eb;min-height:100vh;color:#2A1007}

.topbar{background:linear-gradient(135deg,#2A1007,#3B1A0A);padding:16px 28px;
        display:flex;align-items:center;justify-content:space-between}
.brand{display:flex;align-items:center;gap:12px}
.brand h1{color:#C4943A;font-family:Georgia,serif;font-style:italic;font-size:1.1rem;font-weight:400}
.brand p{color:rgba(255,255,255,0.45);font-size:0.72rem;margin-top:2px}
.back-link{color:#C4943A;text-decoration:none;font-size:0.83rem;font-weight:600;
           border:1px solid rgba(196,148,58,0.4);padding:6px 14px;border-radius:6px}
.back-link:hover{background:rgba(196,148,58,0.15)}

.wrap{max-width:820px;margin:0 auto;padding:32px 16px 60px}

.alert{border-radius:10px;padding:12px 18px;margin-bottom:22px;font-size:0.9rem}
.alert.success{background:#d4edda;border:1px solid #c3e6cb;color:#155724}
.alert.error  {background:#f8d7da;border:1px solid #f5c6cb;color:#721c24}
.alert.info   {background:#d1ecf1;border:1px solid #bee5eb;color:#0c5460}

.card{background:#fff;border-radius:14px;border:1px solid #E8D9C0;
      box-shadow:0 2px 12px rgba(59,26,10,0.07);margin-bottom:28px;overflow:hidden}
.card-head{background:linear-gradient(135deg,#3B1A0A,#5C2E15);color:#fff;
           padding:14px 22px;display:flex;align-items:center;justify-content:space-between}
.card-head h2{font-size:0.95rem;font-weight:600}
.card-head .cnt{background:rgba(196,148,58,0.3);color:#D4AD5E;
                padding:2px 10px;border-radius:20px;font-size:0.75rem}
.card-body{padding:22px}

label{display:block;font-size:0.72rem;font-weight:600;text-transform:uppercase;
      letter-spacing:1px;color:#8B7355;margin-bottom:5px;margin-top:14px}
label:first-of-type{margin-top:0}
input[type=text],input[type=tel],input[type=email],input[type=password],select{
    width:100%;border:1.5px solid #E8D9C0;border-radius:8px;
    padding:10px 14px;font-size:0.9rem;color:#2A1007;
    background:#fdfaf5;outline:none;transition:border-color 0.2s}
input:focus,select:focus{border-color:#C4943A;background:#fff}
.g2{display:grid;grid-template-columns:1fr 1fr;gap:14px}

.btn{display:inline-flex;align-items:center;gap:6px;padding:10px 22px;
     border:none;border-radius:8px;font-size:0.87rem;font-weight:700;
     cursor:pointer;transition:all 0.2s;font-family:inherit}
.btn-gold{background:linear-gradient(135deg,#C4943A,#D4AD5E);color:#2A1007}
.btn-gold:hover{transform:translateY(-1px);box-shadow:0 4px 14px rgba(196,148,58,0.4)}
.btn-dark{background:#3B1A0A;color:#fff}
.btn-dark:hover{background:#5C2E15}
.btn-sm{padding:5px 12px;font-size:0.76rem;border-radius:6px}
.btn-oa{background:none;border:1px solid #C4943A;color:#C4943A}
.btn-oa:hover{background:rgba(196,148,58,0.1)}
.btn-od{background:none;border:1px solid #dc3545;color:#dc3545}
.btn-od:hover{background:#dc3545;color:#fff}

table{width:100%;border-collapse:collapse;font-size:0.85rem}
th{background:#3B1A0A;color:#fff;padding:10px 14px;text-align:left;font-weight:500;font-size:0.79rem}
td{padding:10px 14px;border-bottom:1px solid #f0e8d8;vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:#fdfaf5}
.acts{display:flex;gap:6px;flex-wrap:wrap}

.empty{text-align:center;padding:36px;color:#bbb;font-size:0.9rem}

.modal-wrap{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);
            z-index:999;align-items:center;justify-content:center;padding:16px}
.modal-wrap.open{display:flex}
.modal{background:#fff;border-radius:14px;padding:26px;width:100%;max-width:400px;
       box-shadow:0 20px 60px rgba(0,0,0,0.2)}
.modal h3{color:#3B1A0A;font-size:0.97rem;margin-bottom:16px;
          padding-bottom:10px;border-bottom:2px solid #C4943A}
.modal-foot{display:flex;gap:8px;margin-top:16px;justify-content:flex-end}

.url-box{background:#f4f0eb;border-radius:8px;padding:10px 16px;
         font-family:monospace;font-size:0.82rem;color:#3B1A0A;
         word-break:break-all;margin-top:10px;border:1px solid #E8D9C0}

@media(max-width:580px){
    .g2{grid-template-columns:1fr}
    .topbar{padding:12px 16px}
    .wrap{padding:20px 12px}
    table{font-size:0.78rem}
    th,td{padding:8px 10px}
}
</style>
</head>
<body>

<div class="topbar">
    <div class="brand">
        <img src="<?= ADMIN_URL ?>/images/Lodge_Logoo.png"
             alt="Karavali Lodge Logo"
             style="width:48px;height:48px;object-fit:contain;border-radius:8px;flex-shrink:0;">
        <div>
            <h1>Karavali Lodge</h1>
            <p>Admin Registration Panel</p>
        </div>
    </div>
    <a href="<?= ADMIN_URL ?>/index.php" class="back-link">← Admin Panel</a>
</div>

<div class="wrap">

    <?php if ($message): ?>
    <div class="alert <?= $msgType ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- ── Registered admins ── -->
    <div class="card">
        <div class="card-head">
            <h2>👥 Registered Admins</h2>
            <span class="cnt"><?= count($admins) ?> account<?= count($admins) !== 1 ? 's' : '' ?></span>
        </div>
        <div class="card-body" style="padding:0">
            <?php if (empty($admins)): ?>
            <div class="empty">No admins yet — register the first one below.</div>
            <?php else: ?>
            <div style="overflow-x:auto">
            <table>
                <thead>
                    <tr><th>Name</th><th>Mobile</th><th>Email</th><th>Registered On</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($admins as $a): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($a['name']) ?></strong></td>
                    <td><?= htmlspecialchars($a['mobile']) ?></td>
                    <td><?= htmlspecialchars($a['email'] ?: '—') ?></td>
                    <td><?= date('d M Y', strtotime($a['created_at'])) ?></td>
                    <td>
                        <div class="acts">
                            <button class="btn btn-sm btn-oa"
                                    onclick="openReset('<?= $a['id'] ?>','<?= htmlspecialchars($a['name']) ?>')">
                                Reset Password
                            </button>
                            <button class="btn btn-sm btn-od"
                                    onclick="doDelete('<?= $a['id'] ?>','<?= htmlspecialchars($a['name']) ?>')">
                                Delete
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Register new admin ── -->
    <div class="card">
        <div class="card-head">
            <h2>➕ Register New Admin</h2>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action"    value="create">
                <input type="hidden" name="setup_key" value="<?= htmlspecialchars(SETUP_SECRET_KEY) ?>">
                <div class="g2">
                    <div>
                        <label>Full Name *</label>
                        <input type="text" name="name" placeholder="e.g. Deekshith Shettigar"
                               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required maxlength="100">
                    </div>
                    <div>
                        <label>Mobile Number *</label>
                        <input type="tel" name="mobile" placeholder="10-digit mobile"
                               value="<?= htmlspecialchars($_POST['mobile'] ?? '') ?>" required maxlength="15">
                    </div>
                    <div>
                        <label>Password *</label>
                        <input type="password" name="password" placeholder="Minimum 6 characters" required>
                    </div>
                    <div>
                        <label>Confirm Password *</label>
                        <input type="password" name="confirm" placeholder="Re-enter password" required>
                    </div>
                </div>
                <label style="margin-top:20px">Email (optional)</label>
                <input type="email" name="email" placeholder="admin@hotel.com"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" maxlength="100">
                <div style="margin-top:20px">
                    <button type="submit" class="btn btn-gold">🔐 Register Admin</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Access info ── -->
    <div class="card">
        <div class="card-head"><h2>🔑 Bookmark This URL</h2></div>
        <div class="card-body">
            <p style="font-size:0.85rem;color:#666;line-height:1.7">
                This page is protected by a secret key. Without the correct key in the URL,
                the page shows <strong>404 Not Found</strong>. Bookmark this URL and keep it safe.
            </p>
            <div class="url-box">
                <?= rtrim(ADMIN_URL, '/') ?>/setup_admin.php?key=<?= htmlspecialchars(SETUP_SECRET_KEY) ?>
            </div>
            <p style="font-size:0.78rem;color:#aaa;margin-top:8px">
                ⚠ You can change <code>SETUP_SECRET_KEY</code> inside the file to any word/phrase without special characters like #, &amp;, %.
            </p>
        </div>
    </div>

</div>

<!-- Reset password modal -->
<div class="modal-wrap" id="mReset">
    <div class="modal">
        <h3 id="mResetTitle">Reset Password</h3>
        <form method="POST">
            <input type="hidden" name="action"    value="reset">
            <input type="hidden" name="setup_key" value="<?= htmlspecialchars(SETUP_SECRET_KEY) ?>">
            <input type="hidden" name="admin_id"  id="mResetId">
            <label>New Password *</label>
            <input type="password" name="password" placeholder="Minimum 6 characters" required>
            <label>Confirm Password *</label>
            <input type="password" name="confirm"  placeholder="Re-enter" required>
            <div class="modal-foot">
                <button type="button" class="btn btn-sm btn-dark" onclick="closeModal('mReset')">Cancel</button>
                <button type="submit" class="btn btn-sm btn-gold">Update Password</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete form (hidden) -->
<form method="POST" id="delForm" style="display:none">
    <input type="hidden" name="action"    value="delete">
    <input type="hidden" name="setup_key" value="<?= htmlspecialchars(SETUP_SECRET_KEY) ?>">
    <input type="hidden" name="admin_id"  id="delId">
</form>

<script>
function openReset(id, name) {
    document.getElementById('mResetId').value = id;
    document.getElementById('mResetTitle').textContent = 'Reset Password — ' + name;
    document.getElementById('mReset').classList.add('open');
}
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
}
function doDelete(id, name) {
    if (!confirm('Delete admin account for ' + name + '?\n\nThis cannot be undone.')) return;
    document.getElementById('delId').value = id;
    document.getElementById('delForm').submit();
}
document.querySelectorAll('.modal-wrap').forEach(m => {
    m.addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('open');
    });
});
</script>
</body>
</html>