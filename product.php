<?php
require __DIR__ . '/includes/bootstrap.php';

$p = q_one('SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM products p
            LEFT JOIN categories c ON c.id = p.category_id WHERE p.slug = ? AND p.is_active = 1', [$_GET['slug'] ?? '']);
if (!$p) {
    http_response_code(404);
    $pageTitle = 'Not found';
    include __DIR__ . '/includes/header.php';
    echo '<div class="empty-state"><h2>We could not find that piece</h2><a class="btn btn-outline" href="' . url('shop.php') . '">Back to shop</a></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}
$images = product_images((int)$p['id']);
$sizes = product_sizes($p);
$stock = (int)$p['stock'];
$buyable = can_buy($p);
$preorder = $buyable && $stock <= 0;
$maxQty = $p['allow_backorder'] ? 99 : $stock;
$related = with_images(q_all(
    'SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.is_active = 1 AND p.id <> ? ORDER BY (p.category_id = ?) DESC, RAND() LIMIT 4',
    [$p['id'], $p['category_id']]
));

$pageTitle = $p['name'];
include __DIR__ . '/includes/header.php';
?>
<section class="pdp">
  <div class="pdp-gallery">
    <?php if (!$images): ?><figure><img src="<?= e(img_url(null)) ?>" alt=""></figure><?php endif; ?>
    <?php foreach ($images as $img): ?>
      <figure><img src="<?= e(img_url($img['path'])) ?>" alt="<?= e($p['name']) ?>"></figure>
    <?php endforeach; ?>
  </div>

  <div class="pdp-info">
    <div class="breadcrumb">
      <a href="<?= url('shop.php') ?>">Shop</a>
      <?php if ($p['category_name']): ?> / <a href="<?= url('shop.php?category=' . urlencode($p['category_slug'])) ?>"><?= e($p['category_name']) ?></a><?php endif; ?>
    </div>
    <h1><?= e($p['name']) ?></h1>
    <div class="pdp-price price">
      <?php if ($p['compare_price'] && $p['compare_price'] > $p['price']): ?><s><?= money($p['compare_price']) ?></s><?php endif; ?>
      <?= money($p['price']) ?>
    </div>

    <form data-add-to-cart>
      <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
      <?php if ($sizes): ?>
        <div class="eyebrow" style="margin-bottom:10px">Size</div>
        <div class="sizes">
          <?php foreach ($sizes as $s): ?>
            <label class="size-opt"><input type="radio" name="size" value="<?= e($s) ?>" <?= count($sizes) === 1 ? 'checked' : '' ?>><span><?= e($s) ?></span></label>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if ($buyable): ?>
        <div class="buy-row">
          <div class="qty" data-stepper>
            <button type="button" data-step="-1" aria-label="Decrease">−</button>
            <input type="number" name="qty" value="1" min="1" max="<?= $maxQty ?>" readonly>
            <button type="button" data-step="1" aria-label="Increase">+</button>
          </div>
          <button type="submit" class="btn"><?= $preorder ? 'Pre-order' : 'Add to bag' ?></button>
        </div>
        <div class="stock-note"><?php if ($preorder): ?><?= e(setting('backorder_note', 'Out of stock — order now and we will ship as soon as it is back.')) ?><?php else: ?><?= $stock <= 5 && !$p['allow_backorder'] ? 'Only ' . $stock . ' left' : 'In stock' ?><?php endif; ?></div>
      <?php else: ?>
        <button type="button" class="btn btn-block" disabled>Sold out</button>
      <?php endif; ?>
    </form>

    <?php if ($p['description']): ?><div class="pdp-desc"><?= nl2br(e($p['description'])) ?></div><?php endif; ?>

    <div class="accordion">
      <div class="acc-item">
        <button class="acc-head" type="button">Delivery</button>
        <div class="acc-body"><div><div><?= nl2br(e(setting('delivery_info', 'We deliver to the locations listed at checkout. Delivery charges depend on your location and are shown before you pay.'))) ?></div></div></div>
      </div>
      <div class="acc-item">
        <button class="acc-head" type="button">Payment</button>
        <div class="acc-body"><div><div>Scan our QR code at checkout, pay the total amount, and upload a screenshot of the payment. We verify every payment and email you once it is confirmed.</div></div></div>
      </div>
      <div class="acc-item">
        <button class="acc-head" type="button">Returns &amp; care</button>
        <div class="acc-body"><div><div><?= nl2br(e(setting('returns_info', 'Exchanges are accepted within 7 days of delivery for unused items in original packaging. Contact us with your order number to start an exchange.'))) ?></div></div></div>
      </div>
    </div>
  </div>
</section>

<?php if ($related): ?>
<section class="container section">
  <div class="section-head reveal"><div><div class="eyebrow">You may also like</div><h2>Complete the look</h2></div></div>
  <div class="products">
    <?php foreach ($related as $p) include __DIR__ . '/includes/partials/product-card.php'; ?>
  </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
