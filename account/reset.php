<?php
require __DIR__ . '/../includes/bootstrap.php';
$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$u = $token !== '' ? q_one('SELECT * FROM users WHERE reset_token = ? AND reset_expires > NOW()', [hash('sha256', $token)]) : null;
$errors = [];
if ($u && is_post()) {
    csrf_check();
    $pw = (string)($_POST['password'] ?? '');
    if (strlen($pw) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($pw !== (string)($_POST['password2'] ?? '')) $errors[] = 'Passwords do not match.';
    if (!$errors) {
        q('UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $u['id']]);
        flash('success', 'Password updated — please sign in.');
        redirect('account/login.php');
    }
}
$pageTitle = 'Choose a new password';
include __DIR__ . '/../includes/header.php';
?>
<div class="auth-wrap">
  <div class="auth-media"><img src="<?= e(img_url(setting('auth_image', 'assets/img/story.svg'))) ?>" alt=""></div>
  <div class="auth-form">
    <div class="eyebrow">Account help</div>
    <h1>New password</h1>
    <?php if (!$u): ?>
      <div class="alert alert-error">This reset link is invalid or has expired.</div>
      <a class="btn btn-outline" href="<?= url('account/forgot.php') ?>">Request a new link</a>
    <?php else: ?>
      <?php if ($errors): ?><div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="field"><label for="password">New password</label><input id="password" type="password" name="password" minlength="8" required autocomplete="new-password"></div>
        <div class="field"><label for="password2">Confirm password</label><input id="password2" type="password" name="password2" minlength="8" required autocomplete="new-password"></div>
        <button class="btn btn-block" type="submit">Save password</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
