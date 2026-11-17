<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();
$failed = !empty($_GET['failed']);
$rows = q_all('SELECT h.*, u.name FROM login_history h LEFT JOIN users u ON u.id = h.user_id'
    . ($failed ? ' WHERE h.success = 0' : '') . ' ORDER BY h.id DESC LIMIT 300');
$pageTitle = 'Login history';
include __DIR__ . '/_head.php';
?>
<div class="filters">
  <a class="<?= !$failed ? 'on' : '' ?>" href="<?= url('admin/logins.php') ?>">All attempts</a>
  <a class="<?= $failed ? 'on' : '' ?>" href="<?= url('admin/logins.php?failed=1') ?>">Failed only</a>
</div>
<div class="card"><div class="table-wrap"><table>
  <thead><tr><th>When</th><th>User</th><th>Email used</th><th>Device</th><th>IP</th><th>Result</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $h): ?>
    <tr>
      <td class="nowrap small"><?= fmt_date($h['created_at'], 'd M Y, g:i:s a') ?></td>
      <td><?= $h['user_id'] ? '<a class="link" href="' . url('admin/customer.php?id=' . $h['user_id']) . '">' . e($h['name']) . '</a>' : '<span class="muted">—</span>' ?></td>
      <td class="small"><?= e($h['email']) ?></td>
      <td class="small" title="<?= e($h['user_agent']) ?>"><?= e(describe_agent($h['user_agent'])) ?></td>
      <td class="small"><?= e($h['ip']) ?></td>
      <td><span class="badge <?= $h['success'] ? 'b-ok' : 'b-bad' ?>"><?= $h['success'] ? 'Success' : 'Failed' ?></span></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>
<?php include __DIR__ . '/_foot.php'; ?>
