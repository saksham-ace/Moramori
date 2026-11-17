<?php
require __DIR__ . '/includes/bootstrap.php';
$order = q_one('SELECT * FROM orders WHERE order_no = ?', [$_GET['no'] ?? '']);
if (!$order || !can_view_order($order)) redirect('track.php');

$pageTitle = 'Thank you';
include __DIR__ . '/includes/header.php';
?>
<section class="page-hero container">
  <div class="eyebrow reveal">Order received</div>
  <h1 class="reveal reveal-d1">Thank you, <?= e(explode(' ', $order['name'])[0]) ?></h1>
  <p class="muted reveal reveal-d2" style="max-width:520px;margin:12px auto 0">
    <?php if ($order['payment_method'] === 'cod'): ?>We have received your order. Please keep <?= money($order['total']) ?> ready in cash for the delivery. We will email<?php else: ?>We have received your order and payment screenshot. We will verify the payment and email<?php endif; ?>
    <strong style="color:var(--ink)"><?= e($order['email']) ?></strong> as soon as your order is confirmed.
  </p>
</section>
<div class="container" style="padding-bottom:100px">
  <?php include __DIR__ . '/includes/partials/order-detail.php'; ?>
  <div style="margin-top:40px;display:flex;gap:10px;flex-wrap:wrap">
    <a class="btn" href="<?= url('shop.php') ?>">Continue shopping</a>
    <?php if (!current_user()): ?><a class="btn btn-outline" href="<?= url('account/register.php?email=' . urlencode($order['email'])) ?>">Create an account</a><?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
