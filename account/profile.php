<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();
$errors = [];
if (is_post()) {
    csrf_check();
    $name = post('name');
    $phone = post('phone');
    $address = post('address');
    if ($name === '') $errors[] = 'Name is required.';
    if (!$errors) {
        q('UPDATE users SET name = ?, phone = ?, address = ? WHERE id = ?', [$name, $phone ?: null, $address ?: null, $user['id']]);
        flash('success', 'Profile saved.');
        redirect('account/profile.php');
    }
}
$pageTitle = 'Profile';
include __DIR__ . '/../includes/header.php';
?>
<div class="container account">
  <?php include __DIR__ . '/../includes/partials/account-nav.php'; ?>
  <div>
    <div class="eyebrow">My account</div>
    <h1>Profile</h1>
    <?php if ($errors): ?><div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
    <form method="post" style="max-width:560px">
      <?= csrf_field() ?>
      <div class="field"><label>Email</label><input value="<?= e($user['email']) ?>" disabled></div>
      <div class="field"><label for="name">Full name</label><input id="name" name="name" value="<?= e($user['name']) ?>" required></div>
      <div class="field"><label for="phone">Phone</label><input id="phone" name="phone" value="<?= e($user['phone']) ?>"></div>
      <div class="field"><label for="address">Default delivery address</label><input id="address" name="address" value="<?= e($user['address']) ?>"></div>
      <button class="btn" type="submit">Save changes</button>
    </form>
    <p class="muted" style="font-size:13px;margin-top:30px">Member since <?= fmt_date($user['created_at'], 'F Y') ?></p>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
