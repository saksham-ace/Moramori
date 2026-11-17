<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$status = $_GET['status'] ?? '';
$payment = $_GET['payment'] ?? '';
$search = trim($_GET['q'] ?? '');
$where = ['1=1'];
$params = [];
if (isset(ORDER_STATUSES[$status])) { $where[] = 'status = ?'; $params[] = $status; }
if (isset(PAYMENT_STATUSES[$payment])) { $where[] = 'payment_status = ?'; $params[] = $payment; }
if ($search !== '') {
    $where[] = '(order_no LIKE ? OR name LIKE ? OR email LIKE ? OR phone LIKE ?)';
    array_push($params, "%$search%", "%$search%", "%$search%", "%$search%");
}
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 30;
$total = (int)q_val('SELECT COUNT(*) FROM orders WHERE ' . implode(' AND ', $where), $params);
$recent = q_all('SELECT * FROM orders WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per), $params);

$link = fn(array $o) => url('admin/orders.php?' . http_build_query(array_filter(array_merge(['status' => $status, 'payment' => $payment, 'q' => $search], $o), 'strlen')));
$pageTitle = 'Orders';
include __DIR__ . '/_head.php';
?>
<div class="filters">
  <a class="<?= !$status && !$payment ? 'on' : '' ?>" href="<?= url('admin/orders.php') ?>">All</a>
  <a class="<?= $payment === 'submitted' ? 'on' : '' ?>" href="<?= $link(['payment' => 'submitted', 'status' => '']) ?>">Payment to verify</a>
  <?php foreach (ORDER_STATUSES as $k => $l): ?>
    <a class="<?= $status === $k ? 'on' : '' ?>" href="<?= $link(['status' => $k, 'payment' => '']) ?>"><?= e($l) ?></a>
  <?php endforeach; ?>
  <form method="get" class="inline-form">
    <input class="input" style="width:220px" name="q" value="<?= e($search) ?>" placeholder="Order no, name, email, phone">
    <button class="btn btn-sm">Search</button>
  </form>
</div>
<div class="card">
  <?php include __DIR__ . '/_orders_table.php'; ?>
  <?php if ($total > $per): ?>
    <div class="inline-form" style="margin-top:16px;justify-content:flex-end">
      <?php if ($page > 1): ?><a class="btn btn-outline btn-sm" href="<?= $link(['page' => $page - 1]) ?>">← Prev</a><?php endif; ?>
      <span class="muted small">Page <?= $page ?> of <?= ceil($total / $per) ?></span>
      <?php if ($page * $per < $total): ?><a class="btn btn-outline btn-sm" href="<?= $link(['page' => $page + 1]) ?>">Next →</a><?php endif; ?>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/_foot.php'; ?>
