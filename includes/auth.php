<?php
function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['uid'])) {
            $user = q_one('SELECT id, name, email, phone, address, role, created_at FROM users WHERE id = ?', [$_SESSION['uid']]);
            if (!$user) unset($_SESSION['uid']);
        }
    }
    return $user;
}

function is_admin(): bool
{
    $u = current_user();
    return $u && $u['role'] === 'admin';
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? url('account/');
        flash('info', 'Please sign in to continue.');
        redirect('account/login.php');
    }
    return $u;
}

function require_admin(): array
{
    $u = current_user();
    if (!$u || $u['role'] !== 'admin') {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? url('admin/');
        redirect('account/login.php?admin=1');
    }
    return $u;
}

function log_login_attempt(?int $userId, string $email, bool $success): void
{
    q('INSERT INTO login_history (user_id, email, ip, user_agent, success) VALUES (?, ?, ?, ?, ?)', [
        $userId, substr($email, 0, 190), client_ip(), substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255), $success ? 1 : 0,
    ]);
}

/** Simple brute-force guard: 8 failed attempts per email/IP in 15 minutes. */
function too_many_attempts(string $email): bool
{
    $n = (int)q_val(
        'SELECT COUNT(*) FROM login_history WHERE success = 0 AND (email = ? OR ip = ?) AND created_at > (NOW() - INTERVAL 15 MINUTE)',
        [$email, client_ip()]
    );
    return $n >= 8;
}

function attempt_login(string $email, string $password): array
{
    $email = strtolower(trim($email));
    if (too_many_attempts($email)) {
        return [false, 'Too many failed attempts. Please wait 15 minutes and try again.'];
    }
    $u = q_one('SELECT * FROM users WHERE email = ?', [$email]);
    if (!$u || !password_verify($password, $u['password'])) {
        log_login_attempt($u['id'] ?? null, $email, false);
        return [false, 'Incorrect email or password.'];
    }
    if (password_needs_rehash($u['password'], PASSWORD_DEFAULT)) {
        q('UPDATE users SET password = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
    }
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    log_login_attempt((int)$u['id'], $email, true);
    return [true, $u];
}

function logout_user(): void
{
    $cart = $_SESSION['cart'] ?? [];
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['cart'] = $cart;
}
