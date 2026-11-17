<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();
$order = q_one('SELECT * FROM orders WHERE order_no = ?', [$_GET['no'] ?? '']);
if (!$order || !can_view_order($order)) {
    flash('error', 'Order not found.');
    redirect('account/orders.php');
}
$pageTitle = 'Order ' . $order['order_no'];
include __DIR__ . '/../includes/header.php';
?>
<div class="container account">
  <?php include __DIR__ . '/../includes/partials/account-nav.php'; ?>
  <div>
    <a class="text-link" href="<?= url('account/orders.php') ?>">&larr; All orders</a>
    <div style="height:28px"></div>
    <?php include __DIR__ . '/../includes/partials/order-detail.php'; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
