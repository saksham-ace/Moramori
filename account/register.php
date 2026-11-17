<?php
require __DIR__ . '/../includes/bootstrap.php';
if (current_user()) redirect('account/');

$errors = [];
$old = ['name' => '', 'email' => strtolower($_GET['email'] ?? ''), 'phone' => ''];
if (is_post()) {
    csrf_check();
    $old = ['name' => post('name'), 'email' => strtolower(post('email')), 'phone' => post('phone')];
    $pw = (string)($_POST['password'] ?? '');
    if ($old['name'] === '') $errors[] = 'Please enter your name.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email.';
    if (strlen($pw) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($pw !== (string)($_POST['password2'] ?? '')) $errors[] = 'Passwords do not match.';
    if (!$errors && q_val('SELECT id FROM users WHERE email = ?', [$old['email']])) $errors[] = 'An account with this email already exists.';
    if (!$errors) {
        q('INSERT INTO users (name, email, phone, password) VALUES (?, ?, ?, ?)', [$old['name'], $old['email'], $old['phone'] ?: null, password_hash($pw, PASSWORD_DEFAULT)]);
        $id = (int)db()->lastInsertId();
        // link earlier guest orders placed with the same email
        q('UPDATE orders SET user_id = ? WHERE user_id IS NULL AND email = ?', [$id, $old['email']]);
        session_regenerate_id(true);
        $_SESSION['uid'] = $id;
        log_login_attempt($id, $old['email'], true);
        mail_welcome(['name' => $old['name'], 'email' => $old['email']]);
        flash('success', 'Welcome to ' . setting('store_name', 'Mora Mori') . '!');
        $to = $_SESSION['after_login'] ?? url('account/');
        unset($_SESSION['after_login']);
        if (!str_starts_with($to, BASE_URL . '/')) $to = url('account/');
        header('Location: ' . $to);
        exit;
    }
}
$pageTitle = 'Create account';
include __DIR__ . '/../includes/header.php';
?>
<div class="auth-wrap">
  <div class="auth-media"><img src="<?= e(img_url(setting('auth_image', 'assets/img/story.svg'))) ?>" alt=""></div>
  <div class="auth-form">
    <div class="eyebrow reveal">Join us</div>
    <h1 class="reveal reveal-d1">Create account</h1>
    <?php if ($errors): ?><div class="alert alert-error"><?php foreach ($errors as $er) echo '<div>' . e($er) . '</div>'; ?></div><?php endif; ?>
    <form method="post" class="reveal reveal-d2">
      <?= csrf_field() ?>
      <div class="field"><label for="name">Full name</label><input id="name" name="name" value="<?= e($old['name']) ?>" required autocomplete="name"></div>
      <div class="grid-2">
        <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="<?= e($old['email']) ?>" required autocomplete="email"></div>
        <div class="field"><label for="phone">Phone (optional)</label><input id="phone" name="phone" value="<?= e($old['phone']) ?>" autocomplete="tel"></div>
      </div>
      <div class="grid-2">
        <div class="field"><label for="password">Password</label><input id="password" type="password" name="password" minlength="8" required autocomplete="new-password"></div>
        <div class="field"><label for="password2">Confirm password</label><input id="password2" type="password" name="password2" minlength="8" required autocomplete="new-password"></div>
      </div>
      <button class="btn btn-block" type="submit">Create account</button>
      <p class="auth-alt">Already have an account? <a href="<?= url('account/login.php') ?>">Sign in</a></p>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
