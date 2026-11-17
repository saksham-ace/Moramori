<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_admin();
$id = (int)($_GET['id'] ?? 0);
$u = q_one('SELECT * FROM users WHERE id = ?', [$id]);
if (!$u) { flash('error', 'Customer not found.'); redirect('admin/customers.php'); }

if (is_post()) {
    csrf_check();
    if (post('action') === 'role' && $id !== (int)$me['id']) {
        $role = post('role') === 'admin' ? 'admin' : 'customer';
        q('UPDATE users SET role = ? WHERE id = ?', [$role, $id]);
        flash('success', 'Role updated.');
    }
    redirect('admin/customer.php?id=' . $id);
}

$recent = q_all('SELECT * FROM orders WHERE user_id = ? OR email = ? ORDER BY created_at DESC', [$id, $u['email']]);
$logins = q_all('SELECT * FROM login_history WHERE user_id = ? OR email = ? ORDER BY id DESC LIMIT 30', [$id, $u['email']]);
$pageTitle = $u['name'];
include __DIR__ . '/_head.php';
?>
<div class="inline-form" style="margin-bottom:18px"><a class="btn btn-outline btn-sm" href="<?= url('admin/customers.php') ?>">← Customers</a></div>
<div class="cols">
  <div>
    <div class="card"><h2>Orders</h2><?php include __DIR__ . '/_orders_table.php'; ?></div>
    <div class="card">
      <h2>Login history</h2>
      <table><thead><tr><th>When</th><th>Device</th><th>IP</th><th>Result</th></tr></thead><tbody>
      <?php foreach ($logins as $h): ?>
        <tr><td class="nowrap small"><?= fmt_date($h['created_at'], 'd M Y, g:i a') ?></td><td class="small"><?= e(describe_agent($h['user_agent'])) ?></td><td class="small"><?= e($h['ip']) ?></td>
          <td><span class="badge <?= $h['success'] ? 'b-ok' : 'b-bad' ?>"><?= $h['success'] ? 'Success' : 'Failed' ?></span></td></tr>
      <?php endforeach; ?>
      </tbody></table>
    </div>
  </div>
  <div class="card">
    <h2>Details</h2>
    <dl class="kv">
      <dt>Name</dt><dd><?= e($u['name']) ?></dd>
      <dt>Email</dt><dd><a class="link" href="mailto:<?= e($u['email']) ?>"><?= e($u['email']) ?></a></dd>
      <dt>Phone</dt><dd><?= e($u['phone'] ?: '—') ?></dd>
      <dt>Address</dt><dd><?= e($u['address'] ?: '—') ?></dd>
      <dt>Joined</dt><dd><?= fmt_date($u['created_at'], 'd M Y') ?></dd>
    </dl>
    <?php if ($id !== (int)$me['id']): ?>
      <form method="post" class="inline-form" style="margin-top:18px">
        <?= csrf_field() ?><input type="hidden" name="action" value="role">
        <select class="input" name="role" style="width:auto"><option value="customer">Customer</option><option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option></select>
        <button class="btn btn-outline btn-sm" data-confirm="Change this user's role?">Update role</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/_foot.php'; ?>
