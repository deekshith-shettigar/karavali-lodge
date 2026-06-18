<?php
// =============================================
// Karavali Lodge — Admin ID Photo Viewer
// karavali_lodge/admin/photo.php
//
// Serves guest ID proof photos from uploads/id_proofs/ to logged-in
// admins only. Direct browser access to uploads/id_proofs/ is blocked
// by .htaccess — this script is the only way to view those files,
// and it requires an active admin session.
//
// Usage:  admin/photo.php?f=front_a3f82c1d9e4b7605.jpg
// =============================================

require_once __DIR__ . '/../user/includes/config.php';

// ── Auth check — only logged-in admins can view guest ID photos ───
if (!isAdminSession()) {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo 'Forbidden: admin login required.';
    exit;
}

// ── Validate filename — prevent path traversal ─────────────────────
$file = $_GET['f'] ?? '';

// Only allow simple filenames: letters, digits, underscore, dash, dot
// Rejects '../', '/', '\\', and anything else that could escape the
// id_proofs directory.
if ($file === '' || !preg_match('/^[A-Za-z0-9_\-]+\.[A-Za-z0-9]+$/', $file)) {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo 'Invalid filename.';
    exit;
}

$path = realpath(__DIR__ . '/../uploads/id_proofs/' . $file);
$baseDir = realpath(__DIR__ . '/../uploads/id_proofs/');

// Ensure resolved path is actually inside id_proofs/ (defence in depth
// against any path traversal that somehow passed the regex above)
if ($path === false || $baseDir === false || !str_starts_with($path, $baseDir)) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'File not found.';
    exit;
}

if (!is_file($path)) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'File not found.';
    exit;
}

// ── Serve the file with correct MIME type ──────────────────────────
$finfo    = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $path);
finfo_close($finfo);

// Only allow image types — guest ID photos should always be images
$allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
if (!in_array($mimeType, $allowedMimes, true)) {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo 'Forbidden file type.';
    exit;
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=3600'); // cache for admin's browser only
header('X-Content-Type-Options: nosniff');

readfile($path);
exit;