<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();

$stats = q_one("SELECT COUNT(*) AS n, COALESCE(SUM(CASE WHEN status <> 'cancelled' THEN total END), 0) AS spent,
                SUM(status NOT IN ('delivered','cancelled')) AS active FROM orders WHERE user_id = ?", [$user['id']]);
$recent = q_all('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 3', [$user['id']]);
$lastLogin = q_one('SELECT * FROM login_history WHERE user_id = ? AND success = 1 ORDER BY id DESC LIMIT 1 OFFSET 1', [$user['id']]);

$pageTitle = 'My account';
include __DIR__ . '/../includes/header.php';
?>
<div class="container account">
  <?php include __DIR__ . '/../includes/partials/account-nav.php'; ?>
  <div>
    <div class="eyebrow">My account</div>
    <h1>Hello, <?= e(explode(' ', $user['name'])[0]) ?></h1>
    <div class="stat-cards">
      <div class="stat-card reveal"><b><?= (int)$stats['n'] ?></b><span>Orders</span></div>
      <div class="stat-card reveal reveal-d1"><b><?= (int)$stats['active'] ?></b><span>In progress</span></div>
      <div class="stat-card reveal reveal-d2"><b><?= money($stats['spent']) ?></b><span>Total spent</span></div>
    </div>

    <div class="section-head" style="margin-bottom:10px"><h3 class="block-title" style="margin:0">Recent orders</h3><a class="text-link" href="<?= url('account/orders.php') ?>">View all</a></div>
    <?php include __DIR__ . '/../includes/partials/orders-table.php'; ?>

    <?php if ($lastLogin): ?>
      <p class="muted" style="font-size:13px;margin-top:36px">Previous sign-in: <?= fmt_date($lastLogin['created_at'], 'd M Y, g:i a') ?> · <?= e(describe_agent($lastLogin['user_agent'])) ?> · <?= e($lastLogin['ip']) ?>
        — <a class="link" href="<?= url('account/security.php') ?>">see all activity</a></p>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
