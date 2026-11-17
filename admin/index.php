<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$s = q_one("SELECT
  (SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()) AS today,
  (SELECT COUNT(*) FROM orders WHERE payment_status = 'submitted' AND status <> 'cancelled') AS to_verify,
  (SELECT COALESCE(SUM(total),0) FROM orders WHERE payment_status = 'verified' AND status <> 'cancelled' AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')) AS month_rev,
  (SELECT COUNT(*) FROM orders WHERE status IN ('confirmed','processing','shipped','out_for_delivery')) AS in_progress,
  (SELECT COUNT(*) FROM users WHERE role = 'customer') AS customers,
  (SELECT COUNT(*) FROM products WHERE is_active = 1) AS products");
$recent = q_all('SELECT * FROM orders ORDER BY created_at DESC LIMIT 8');
$lowStock = q_all('SELECT id, name, stock FROM products WHERE is_active = 1 AND stock <= 3 ORDER BY stock LIMIT 8');
$failed = (int)q_val('SELECT COUNT(*) FROM login_history WHERE success = 0 AND created_at > NOW() - INTERVAL 1 DAY');

$pageTitle = 'Dashboard';
include __DIR__ . '/_head.php';
?>
<div class="stats">
  <a class="stat <?= $s['to_verify'] ? 'alert' : '' ?>" href="<?= url('admin/orders.php?payment=submitted') ?>"><b><?= (int)$s['to_verify'] ?></b><span>Payments to verify</span></a>
  <div class="stat"><b><?= (int)$s['today'] ?></b><span>Orders today</span></div>
  <div class="stat"><b><?= (int)$s['in_progress'] ?></b><span>In progress</span></div>
  <div class="stat"><b><?= money($s['month_rev']) ?></b><span>Verified revenue this month</span></div>
</div>
<div class="cols">
  <div class="card">
    <div class="card-head"><h2>Recent orders</h2><a class="btn btn-outline btn-sm" href="<?= url('admin/orders.php') ?>">All orders</a></div>
    <?php include __DIR__ . '/_orders_table.php'; ?>
  </div>
  <div>
    <div class="card">
      <h2>Store</h2>
      <dl class="kv"><dt>Customers</dt><dd><?= (int)$s['customers'] ?></dd><dt>Live products</dt><dd><?= (int)$s['products'] ?></dd>
        <dt>Failed logins (24h)</dt><dd><a class="link" href="<?= url('admin/logins.php?failed=1') ?>"><?= $failed ?></a></dd>
        <dt>Email sending</dt><dd><?= setting('smtp_enabled') === '1' ? '<span class="badge b-ok">SMTP on</span>' : '<span class="badge b-warn">Log only</span>' ?></dd>
        <dt>Payment QR</dt><dd><?= setting('payment_qr') ? '<span class="badge b-ok">Set</span>' : '<a class="badge b-bad" href="' . url('admin/settings.php?tab=payment') . '">Not set</a>' ?></dd></dl>
    </div>
    <div class="card">
      <h2>Low stock</h2>
      <?php if (!$lowStock): ?><p class="muted small">All products are well stocked.</p><?php endif; ?>
      <?php foreach ($lowStock as $p): ?>
        <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--line)">
          <a class="link" href="<?= url('admin/product-edit.php?id=' . $p['id']) ?>"><?= e($p['name']) ?></a>
          <span class="badge <?= $p['stock'] <= 0 ? 'b-bad' : 'b-warn' ?>"><?= (int)$p['stock'] ?> left</span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php include __DIR__ . '/_foot.php'; ?>
