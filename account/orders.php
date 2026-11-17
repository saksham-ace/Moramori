<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();
$recent = q_all('SELECT * FROM orders WHERE user_id = ? OR (user_id IS NULL AND email = ?) ORDER BY created_at DESC', [$user['id'], $user['email']]);
$pageTitle = 'Order history';
include __DIR__ . '/../includes/header.php';
?>
<div class="container account">
  <?php include __DIR__ . '/../includes/partials/account-nav.php'; ?>
  <div>
    <div class="eyebrow">My account</div>
    <h1>Order history</h1>
    <?php include __DIR__ . '/../includes/partials/orders-table.php'; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
