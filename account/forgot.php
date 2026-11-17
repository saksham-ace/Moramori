<?php
require __DIR__ . '/../includes/bootstrap.php';
$sent = false;
if (is_post()) {
    csrf_check();
    $email = strtolower(post('email'));
    $u = q_one('SELECT * FROM users WHERE email = ?', [$email]);
    if ($u) {
        $token = bin2hex(random_bytes(32));
        q('UPDATE users SET reset_token = ?, reset_expires = (NOW() + INTERVAL 1 HOUR) WHERE id = ?', [hash('sha256', $token), $u['id']]);
        mail_password_reset($u, $token);
    }
    $sent = true; // same message either way, so emails cannot be enumerated
}
$pageTitle = 'Forgot password';
include __DIR__ . '/../includes/header.php';
?>
<div class="auth-wrap">
  <div class="auth-media"><img src="<?= e(img_url(setting('auth_image', 'assets/img/story.svg'))) ?>" alt=""></div>
  <div class="auth-form">
    <div class="eyebrow">Account help</div>
    <h1>Reset password</h1>
    <?php if ($sent): ?>
      <div class="alert alert-success">If an account exists for that email, we have sent a link to reset your password. It is valid for 1 hour.</div>
      <a class="text-link" href="<?= url('account/login.php') ?>">Back to sign in</a>
    <?php else: ?>
      <form method="post">
        <?= csrf_field() ?>
        <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" required autofocus></div>
        <button class="btn btn-block" type="submit">Send reset link</button>
        <p class="auth-alt"><a href="<?= url('account/login.php') ?>">Back to sign in</a></p>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
