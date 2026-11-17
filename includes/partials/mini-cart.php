<?php /** @var array $cart */ ?>
<?php if (!$cart['items']): ?>
  <div class="empty-bag">
    <p>Your bag is empty</p>
    <a class="btn" href="<?= url('shop.php') ?>">Discover the collection</a>
  </div>
<?php else: ?>
  <div class="mini-items">
    <?php foreach ($cart['items'] as $i => $it): ?>
      <div class="mini-item" style="animation-delay: <?= $i * 60 ?>ms">
        <a href="<?= url('product.php?slug=' . urlencode($it['slug'])) ?>"><img src="<?= e(img_url($it['image'])) ?>" alt="<?= e($it['name']) ?>"></a>
        <div>
          <div class="name"><?= e($it['name']) ?></div>
          <?php if ($it['size']): ?><div class="meta">Size: <?= e($it['size']) ?></div><?php endif; ?>
          <div class="meta"><?= money($it['price']) ?><?= $it['preorder'] ? ' · Pre-order' : '' ?></div>
          <div style="display:flex;gap:14px;align-items:center;margin-top:10px">
            <div class="qty">
              <button data-cart-qty="<?= $it['qty'] - 1 ?>" data-key="<?= e($it['key']) ?>" aria-label="Decrease">−</button>
              <span><?= $it['qty'] ?></span>
              <button data-cart-qty="<?= $it['qty'] + 1 ?>" data-key="<?= e($it['key']) ?>" aria-label="Increase">+</button>
            </div>
            <button class="remove-link" data-cart-remove data-key="<?= e($it['key']) ?>">Remove</button>
          </div>
        </div>
        <div class="price"><?= money($it['line']) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="mini-foot">
    <div class="row"><span>Subtotal</span><span><?= money($cart['subtotal']) ?></span></div>
    <p class="muted" style="font-size:12px;margin:-6px 0 16px">Delivery calculated at checkout based on your location.</p>
    <a class="btn btn-block" href="<?= url('checkout.php') ?>">Checkout</a>
    <a class="btn btn-outline btn-block" style="margin-top:8px" href="<?= url('cart.php') ?>">View bag</a>
  </div>
<?php endif; ?>
