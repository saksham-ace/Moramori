<?php
require __DIR__ . '/includes/bootstrap.php';
$cart = cart_items();
$pageTitle = 'Your bag';
include __DIR__ . '/includes/header.php';
?>
<section class="page-hero container">
  <div class="eyebrow">Shopping</div>
  <h1>Your bag</h1>
</section>

<div class="container" style="padding-bottom:100px">
<?php if (!$cart['items']): ?>
  <div class="empty-state">
    <h2>Your bag is empty</h2>
    <a class="btn" href="<?= url('shop.php') ?>" style="margin-top:12px">Discover the collection</a>
  </div>
<?php else: ?>
  <div class="two-col">
    <div>
      <?php foreach ($cart['items'] as $it): ?>
        <div class="cart-line reveal">
          <a href="<?= url('product.php?slug=' . urlencode($it['slug'])) ?>"><img src="<?= e(img_url($it['image'])) ?>" alt=""></a>
          <div>
            <a class="name" href="<?= url('product.php?slug=' . urlencode($it['slug'])) ?>"><?= e($it['name']) ?></a>
            <div class="muted" style="font-size:13px"><?= $it['size'] ? 'Size ' . e($it['size']) . ' · ' : '' ?><?= money($it['price']) ?><?= $it['preorder'] ? ' · Pre-order, ships when restocked' : '' ?></div>
            <div class="actions">
              <div class="qty">
                <button data-cart-qty="<?= $it['qty'] - 1 ?>" data-key="<?= e($it['key']) ?>">−</button>
                <span><?= $it['qty'] ?></span>
                <button data-cart-qty="<?= $it['qty'] + 1 ?>" data-key="<?= e($it['key']) ?>">+</button>
              </div>
              <button class="remove-link" data-cart-remove data-key="<?= e($it['key']) ?>">Remove</button>
            </div>
          </div>
          <div class="price line-total"><?= money($it['line']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <aside class="panel sticky-panel">
      <h3>Summary</h3>
      <div class="summary-row"><span>Subtotal (<?= $cart['count'] ?> items)</span><span><?= money($cart['subtotal']) ?></span></div>
      <div class="summary-row muted"><span>Delivery</span><span>At checkout</span></div>
      <div class="summary-row summary-total"><span>Estimated total</span><span><?= money($cart['subtotal']) ?></span></div>
      <a class="btn btn-block" style="margin-top:24px" href="<?= url('checkout.php') ?>">Proceed to checkout</a>
      <a class="text-link" style="display:inline-block;margin-top:18px" href="<?= url('shop.php') ?>">Continue shopping</a>
    </aside>
  </div>
<?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
