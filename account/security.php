<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();

$errors = [];
if (is_post()) {
    csrf_check();
    $row = q_one('SELECT password FROM users WHERE id = ?', [$user['id']]);
    $cur = (string)($_POST['current'] ?? '');
    $new = (string)($_POST['new'] ?? '');
    if (!password_verify($cur, $row['password'])) $errors[] = 'Your current password is incorrect.';
    if (strlen($new) < 8) $errors[] = 'New password must be at least 8 characters.';
    if ($new !== (string)($_POST['new2'] ?? '')) $errors[] = 'New passwords do not match.';
    if (!$errors) {
        q('UPDATE users SET password = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        Mailer::send($user['email'], 'Your password was changed', email_layout('Password changed',
            '<p>Hi ' . e($user['name']) . ',</p><p>Your password was changed on ' . date('d M Y, g:i a') . ' from IP ' . e(client_ip()) . '.</p>'
            . '<p>If this was not you, reset your password immediately and contact us.</p>'));
        flash('success', 'Password updated.');
        redirect('account/security.php');
    }
}

$history = q_all('SELECT * FROM login_history WHERE user_id = ? ORDER BY id DESC LIMIT 50', [$user['id']]);
$pageTitle = 'Login history';
include __DIR__ . '/../includes/header.php';
?>
<div class="container account">
  <?php include __DIR__ . '/../includes/partials/account-nav.php'; ?>
  <div>
    <div class="eyebrow">Security</div>
    <h1>Login history</h1>
    <p class="muted" style="margin-top:-16px;margin-bottom:24px;font-size:14px">The last 50 sign-in attempts on your account. If you see something you do not recognise, change your password.</p>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Date &amp; time</th><th>Device</th><th>IP address</th><th>Result</th></tr></thead>
        <tbody>
        <?php foreach ($history as $h): ?>
          <tr>
            <td><?= fmt_date($h['created_at'], 'd M Y, g:i a') ?></td>
            <td><?= e(describe_agent($h['user_agent'])) ?></td>
            <td><?= e($h['ip']) ?></td>
            <td><span class="badge <?= $h['success'] ? 'badge-ok' : 'badge-bad' ?>"><?= $h['success'] ? 'Success' : 'Failed' ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <h3 class="block-title" style="margin-top:56px">Change password</h3>
    <?php if ($errors): ?><div class="alert alert-error"><?php foreach ($errors as $er) echo '<div>' . e($er) . '</div>'; ?></div><?php endif; ?>
    <form method="post" style="max-width:480px">
      <?= csrf_field() ?>
      <div class="field"><label for="current">Current password</label><input id="current" type="password" name="current" required autocomplete="current-password"></div>
      <div class="field"><label for="new">New password</label><input id="new" type="password" name="new" minlength="8" required autocomplete="new-password"></div>
      <div class="field"><label for="new2">Confirm new password</label><input id="new2" type="password" name="new2" minlength="8" required autocomplete="new-password"></div>
      <button class="btn" type="submit">Update password</button>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
