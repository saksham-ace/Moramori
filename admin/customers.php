<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();
$search = trim($_GET['q'] ?? '');
$params = [];
$where = '1=1';
if ($search !== '') { $where = '(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)'; $params = ["%$search%", "%$search%", "%$search%"]; }
$users = q_all("SELECT u.*, (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS orders,
    (SELECT COALESCE(SUM(total),0) FROM orders o WHERE o.user_id = u.id AND o.status <> 'cancelled') AS spent,
    (SELECT MAX(created_at) FROM login_history h WHERE h.user_id = u.id AND h.success = 1) AS last_login
    FROM users u WHERE $where ORDER BY u.created_at DESC LIMIT 200", $params);
$subs = (int)q_val('SELECT COUNT(*) FROM newsletter');
$pageTitle = 'Customers';
include __DIR__ . '/_head.php';
?>
<div class="filters">
  <span class="muted small"><?= count($users) ?> accounts · <?= $subs ?> newsletter subscribers</span>
  <form method="get" class="inline-form"><input class="input" style="width:240px" name="q" value="<?= e($search) ?>" placeholder="Name, email or phone"><button class="btn btn-sm">Search</button></form>
</div>
<div class="card"><div class="table-wrap"><table>
  <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Orders</th><th>Spent</th><th>Last login</th><th>Joined</th></tr></thead>
  <tbody>
  <?php foreach ($users as $u): ?>
    <tr>
      <td><a class="link" href="<?= url('admin/customer.php?id=' . $u['id']) ?>"><?= e($u['name']) ?></a></td>
      <td><?= e($u['email']) ?></td>
      <td><?= e($u['phone'] ?: '—') ?></td>
      <td><span class="badge <?= $u['role'] === 'admin' ? 'b-dark' : '' ?>"><?= e($u['role']) ?></span></td>
      <td><?= (int)$u['orders'] ?></td>
      <td class="nowrap"><?= money($u['spent']) ?></td>
      <td class="small muted nowrap"><?= $u['last_login'] ? fmt_date($u['last_login'], 'd M Y, g:i a') : '—' ?></td>
      <td class="small muted nowrap"><?= fmt_date($u['created_at']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>
<?php include __DIR__ . '/_foot.php'; ?>
