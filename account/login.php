<?php
require __DIR__ . '/../includes/bootstrap.php';
if (current_user()) redirect(is_admin() && isset($_GET['admin']) ? 'admin/' : 'account/');

if (($_GET['next'] ?? '') === 'checkout') $_SESSION['after_login'] = url('checkout.php');
$error = '';
$email = '';
if (is_post()) {
    csrf_check();
    $email = strtolower(post('email'));
    [$ok, $res] = attempt_login($email, (string)($_POST['password'] ?? ''));
    if ($ok) {
        $to = $_SESSION['after_login'] ?? ($res['role'] === 'admin' ? url('admin/') : url('account/'));
        unset($_SESSION['after_login']);
        // only allow local redirects
        if (!str_starts_with($to, BASE_URL . '/') && $to !== BASE_URL) $to = url('account/');
        flash('success', 'Welcome back, ' . explode(' ', $res['name'])[0] . '.');
        header('Location: ' . $to);
        exit;
    }
    $error = $res;
}
$pageTitle = 'Sign in';
include __DIR__ . '/../includes/header.php';
?>
<div class="auth-wrap">
  <div class="auth-media"><img src="<?= e(img_url(setting('auth_image', 'assets/img/story.svg'))) ?>" alt=""></div>
  <div class="auth-form">
    <div class="eyebrow reveal"><?= isset($_GET['admin']) ? 'Admin access' : 'My account' ?></div>
    <h1 class="reveal reveal-d1">Sign in</h1>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="reveal reveal-d2">
      <?= csrf_field() ?>
      <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="<?= e($email) ?>" required autocomplete="email" autofocus></div>
      <div class="field"><label for="password">Password</label><input id="password" type="password" name="password" required autocomplete="current-password"></div>
      <div style="display:flex;justify-content:flex-end;margin:-6px 0 20px"><a class="remove-link" href="<?= url('account/forgot.php') ?>">Forgot password?</a></div>
      <button class="btn btn-block" type="submit">Sign in</button>
      <p class="auth-alt">New to <?= e(setting('store_name', 'Mora Mori')) ?>? <a href="<?= url('account/register.php') ?>">Create an account</a></p>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
