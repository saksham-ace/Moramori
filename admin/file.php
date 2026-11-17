<?php
/* Serves private payment screenshots to admins only. */
require __DIR__ . '/../includes/bootstrap.php';
require_admin();
$path = q_val('SELECT payment_screenshot FROM orders WHERE id = ?', [(int)($_GET['order'] ?? 0)]);
$full = $path ? realpath(ROOT_PATH . '/' . $path) : false;
$base = realpath(UPLOAD_PATH . '/payments');
if (!$full || !$base || !str_starts_with($full, $base) || !is_file($full)) {
    http_response_code(404);
    exit('Not found');
}
header('Content-Type: ' . (new finfo(FILEINFO_MIME_TYPE))->file($full));
header('Content-Length: ' . filesize($full));
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
readfile($full);
