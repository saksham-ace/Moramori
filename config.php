<?php
/**
 * Mora Mori — core configuration.
 * Edit the database credentials below to match your server.
 * Store details, SMTP mail, payment QR etc. are managed from Admin → Settings.
 */

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'moramori');
define('DB_USER', 'root');
define('DB_PASS', '');

// Path of the site relative to the domain root ('' if installed at domain root).
define('BASE_URL', '/moramori');

// Set to false in production.
define('APP_DEBUG', true);

define('ROOT_PATH', __DIR__);
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024); // 5 MB

date_default_timezone_set('Asia/Kathmandu');

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}
