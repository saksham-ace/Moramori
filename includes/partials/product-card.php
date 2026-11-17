<?php /** @var array $p */
$sizes = product_sizes($p);
$soldOut = !can_buy($p);
$preorder = !$soldOut && (int)$p['stock'] <= 0;
?>
<a class="card reveal" href="<?= url('product.php?slug=' . urlencode($p['slug'])) ?>">
  <div class="card-media">
    <?php if ($soldOut): ?><span class="card-badge">Sold out</span>
    <?php elseif ($preorder): ?><span class="card-badge">Pre-order</span>
    <?php elseif (!empty($p['compare_price']) && $p['compare_price'] > $p['price']): ?><span class="card-badge">Sale</span>
    <?php elseif (strtotime($p['created_at']) > strtotime('-30 days')): ?><span class="card-badge">New</span><?php endif; ?>
    <img src="<?= e(img_url($p['image'])) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
    <?php if ($p['image2']): ?><img class="img-2" src="<?= e(img_url($p['image2'])) ?>" alt="" loading="lazy"><?php endif; ?>
    <?php if (!$soldOut): ?>
      <?php if ($sizes): ?><span class="card-quick">Choose size</span>
      <?php else: ?><span class="card-quick" data-quick-add="<?= (int)$p['id'] ?>">Quick add</span><?php endif; ?>
    <?php endif; ?>
  </div>
  <div class="card-info">
    <div>
      <h3><?= e($p['name']) ?></h3>
      <?php if (!empty($p['category_name'])): ?><div class="cat"><?= e($p['category_name']) ?></div><?php endif; ?>
    </div>
    <div class="price">
      <?php if (!empty($p['compare_price']) && $p['compare_price'] > $p['price']): ?><s><?= money($p['compare_price']) ?></s><?php endif; ?>
      <?= money($p['price']) ?>
    </div>
  </div>
</a>
