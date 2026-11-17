<?php
require_once dirname(__DIR__) . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_URL === '' ? '/' : BASE_URL . '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_name('moramori_sess');
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'install.php') {
    require_once __DIR__ . '/migrate.php';
}
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/cart.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/emails.php';
