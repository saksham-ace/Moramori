<?php
require __DIR__ . '/includes/bootstrap.php';

$user = current_user();
if (!$user && setting('guest_checkout', '1') !== '1') {
    require_login();
}
$cart = cart_items();
if (!$cart['items']) {
    flash('info', 'Your bag is empty.');
    redirect('shop.php');
}

$locations = q_all('SELECT * FROM delivery_locations ORDER BY is_active DESC, sort, name');
$showUnavailable = setting('show_unavailable_locations', '1') === '1';
$qrEnabled = setting('qr_enabled', '1') === '1';
$codEnabled = setting('cod_enabled', '1') === '1';
if (!$qrEnabled && !$codEnabled) $qrEnabled = true; // never leave checkout without a payment option
$errors = [];
$old = [
    'name' => $user['name'] ?? '', 'email' => $user['email'] ?? '', 'phone' => $user['phone'] ?? '',
    'address' => $user['address'] ?? '', 'location_id' => '', 'notes' => '', 'payment_ref' => '',
    'payment_method' => $qrEnabled ? 'qr' : 'cod',
];

if (is_post()) {
    csrf_check();
    foreach ($old as $k => $_) $old[$k] = (string)post($k);
    $old['email'] = strtolower($old['email']);

    if ($old['name'] === '') $errors[] = 'Please enter your full name.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (!preg_match('/^[0-9+\-\s]{7,20}$/', $old['phone'])) $errors[] = 'Please enter a valid phone number.';
    if (mb_strlen($old['address']) < 5) $errors[] = 'Please enter your full delivery address.';
    $loc = q_one('SELECT * FROM delivery_locations WHERE id = ? AND is_active = 1', [(int)$old['location_id']]);
    if (!$loc) $errors[] = 'Sorry, we do not deliver to the selected location. Please choose another.';

    $method = $old['payment_method'] === 'cod' ? 'cod' : 'qr';
    if ($method === 'cod' && !$codEnabled) $errors[] = 'Cash on delivery is not available right now.';
    elseif ($method === 'cod' && $loc && !$loc['cod_available']) $errors[] = 'Cash on delivery is not available for ' . $loc['name'] . '. Please pay by QR.';
    if ($method === 'qr' && !$qrEnabled) $errors[] = 'Please choose cash on delivery.';

    // stock re-check
    foreach ($cart['items'] as $it) {
        if (!$it['backorder'] && $it['qty'] > $it['stock']) $errors[] = $it['name'] . ' — only ' . $it['stock'] . ' left in stock.';
    }

    $shot = null;
    if (!$errors && $method === 'qr') {
        try {
            $shot = store_upload($_FILES['screenshot'] ?? ['error' => UPLOAD_ERR_NO_FILE], 'payments');
        } catch (RuntimeException $e) {
            $errors[] = 'Payment screenshot: ' . $e->getMessage();
        }
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $fee = (float)$loc['fee'];
            $orderNo = generate_order_no();
            q('INSERT INTO orders (order_no, user_id, name, email, phone, address, location_id, location_name, notes, subtotal, delivery_fee, total, payment_method, payment_ref, payment_screenshot, payment_status, status)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
                $orderNo, $user['id'] ?? null, $old['name'], $old['email'], $old['phone'], $old['address'],
                $loc['id'], $loc['name'], $old['notes'] ?: null, $cart['subtotal'], $fee, $cart['subtotal'] + $fee,
                $method, $method === 'qr' ? ($old['payment_ref'] ?: null) : null, $shot,
                $method === 'qr' ? 'submitted' : 'pending', 'placed',
            ]);
            $orderId = (int)$pdo->lastInsertId();
            foreach ($cart['items'] as $it) {
                $st = q('UPDATE products SET stock = stock - ? WHERE id = ? AND (stock >= ? OR allow_backorder = 1)', [$it['qty'], $it['product_id'], $it['qty']]);
                if ($st->rowCount() === 0) throw new RuntimeException($it['name'] . ' just sold out.');
                q('INSERT INTO order_items (order_id, product_id, name, size, price, qty, image) VALUES (?,?,?,?,?,?,?)',
                    [$orderId, $it['product_id'], $it['name'], $it['size'] ?: null, $it['price'], $it['qty'], $it['image']]);
            }
            add_order_history($orderId, 'placed', $method === 'cod' ? 'Order placed — cash on delivery.' : 'Order placed and payment screenshot uploaded.');
            if ($user) {
                q('UPDATE users SET phone = COALESCE(NULLIF(phone, ""), ?), address = COALESCE(NULLIF(address, ""), ?) WHERE id = ?', [$old['phone'], $old['address'], $user['id']]);
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            if ($shot) @unlink(ROOT_PATH . '/' . $shot);
            $errors[] = $ex instanceof RuntimeException ? $ex->getMessage() : 'Could not place the order. Please try again.';
            if (APP_DEBUG && !($ex instanceof RuntimeException)) $errors[] = $ex->getMessage();
        }

        if (!$errors) {
            cart_clear();
            $_SESSION['my_orders'][] = $orderNo;
            $order = q_one('SELECT * FROM orders WHERE id = ?', [$orderId]);
            mail_order_placed($order);
            redirect('order-success.php?no=' . urlencode($orderNo));
        }
    }
}

$qr = setting('payment_qr', '');
$pageTitle = 'Checkout';
include __DIR__ . '/includes/header.php';
?>
<section class="page-hero container" style="padding-bottom:20px">
  <div class="eyebrow">Secure checkout</div>
  <h1>Checkout</h1>
</section>

<div class="container" style="padding-bottom:100px">
  <?php if ($errors): ?>
    <div class="alert alert-error"><?php foreach ($errors as $er) echo '<div>' . e($er) . '</div>'; ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" id="checkoutForm" class="two-col"
        data-subtotal="<?= e($cart['subtotal']) ?>" data-currency="<?= e(setting('currency', 'Rs.')) ?>" novalidate>
    <?= csrf_field() ?>
    <div>
      <?php if (!$user): ?>
        <div class="alert">Have an account? <a class="link" href="<?= url('account/login.php?next=checkout') ?>">Sign in</a> to save this order to your history. Or continue as a guest.</div>
      <?php endif; ?>

      <div class="form-section">
        <h3 class="block-title"><span class="num">1</span>Contact &amp; address</h3>
        <div class="grid-2">
          <div class="field"><label for="name">Full name</label><input id="name" name="name" value="<?= e($old['name']) ?>" required autocomplete="name"></div>
          <div class="field"><label for="phone">Phone</label><input id="phone" name="phone" value="<?= e($old['phone']) ?>" required autocomplete="tel"></div>
        </div>
        <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="<?= e($old['email']) ?>" required autocomplete="email">
          <div class="hint">We will send your order confirmation and updates here.</div></div>
        <div class="field"><label for="address">Street address, area, landmark</label><input id="address" name="address" value="<?= e($old['address']) ?>" required autocomplete="street-address"></div>
        <div class="field"><label for="notes">Order notes (optional)</label><textarea id="notes" name="notes" rows="2"><?= e($old['notes']) ?></textarea></div>
      </div>

      <div class="form-section">
        <h3 class="block-title"><span class="num">2</span>Delivery location</h3>
        <?php if (count($locations) > 6): ?>
          <input type="search" id="locSearch" class="input loc-search" placeholder="Search your city / area">
        <?php endif; ?>
        <div class="loc-list">
          <?php $anyActive = false; foreach ($locations as $l): if (!$l['is_active'] && !$showUnavailable) continue; $anyActive = $anyActive || $l['is_active']; ?>
            <label class="loc-opt <?= $l['is_active'] ? '' : 'disabled' ?>" data-name="<?= e(strtolower($l['name'])) ?>">
              <input type="radio" name="location_id" value="<?= (int)$l['id'] ?>" data-fee="<?= e($l['fee']) ?>" data-cod="<?= $codEnabled && $l['cod_available'] ? 1 : 0 ?>"
                <?= !$l['is_active'] ? 'disabled' : '' ?> <?= $old['location_id'] == $l['id'] ? 'checked' : '' ?>>
              <span class="loc-name"><?= e($l['name']) ?>
                <span class="loc-eta"><?= $l['is_active'] ? e($l['eta'] ?: 'Standard delivery') : 'Delivery not available' ?>
                  <?php if ($l['is_active'] && $codEnabled && $l['cod_available']): ?> · <span class="cod-tag">Cash on delivery available</span><?php endif; ?></span></span>
              <span class="loc-fee"><?= $l['is_active'] ? ((float)$l['fee'] > 0 ? money($l['fee']) : 'Free') : '—' ?></span>
            </label>
          <?php endforeach; ?>
          <?php if (!$anyActive): ?><div class="alert">We are not accepting deliveries at the moment. Please check back soon.</div><?php endif; ?>
        </div>
      </div>

      <div class="form-section">
        <h3 class="block-title"><span class="num">3</span>Payment</h3>
        <div class="pay-methods">
          <?php if ($qrEnabled): ?>
          <label class="pay-method">
            <input type="radio" name="payment_method" value="qr" <?= $old['payment_method'] === 'qr' ? 'checked' : '' ?>>
            <span><strong>Pay now by QR</strong><small><?= e(setting('payment_apps', 'Mobile banking / wallet')) ?></small></span>
          </label>
          <?php endif; ?>
          <?php if ($codEnabled): ?>
          <label class="pay-method" id="codOption">
            <input type="radio" name="payment_method" value="cod" <?= $old['payment_method'] === 'cod' ? 'checked' : '' ?>>
            <span><strong>Cash on delivery</strong><small data-cod-note>Pay in cash when your order arrives</small></span>
          </label>
          <?php endif; ?>
        </div>

        <div class="cod-box" data-pay-panel="cod">
          <div class="eyebrow">Amount to pay on delivery</div>
          <div class="pay-amount" data-total><?= money($cart['subtotal']) ?></div>
          <p class="muted" style="font-size:13px;margin:0"><?= e(setting('cod_note', 'Please keep the exact amount ready. We may call you to confirm the order before dispatch.')) ?></p>
        </div>

        <div data-pay-panel="qr">
        <div class="pay-box">
          <div class="qr-frame">
            <?php if ($qr): ?><img src="<?= e(img_url($qr)) ?>" alt="Payment QR code">
            <?php else: ?><img src="<?= url('assets/img/qr-placeholder.svg') ?>" alt="QR not set"><?php endif; ?>
          </div>
          <div>
            <div class="eyebrow">Amount to pay</div>
            <div class="pay-amount" data-total><?= money($cart['subtotal']) ?></div>
            <ol class="pay-steps">
              <li>Scan the QR with <?= e(setting('payment_apps', 'your mobile banking / wallet app')) ?>.</li>
              <li>Pay the exact total shown above.</li>
              <li>Take a screenshot of the successful payment and upload it below.</li>
            </ol>
            <?php if (setting('payment_account_name')): ?>
              <p class="muted" style="font-size:12px;margin:12px 0 0">Account name: <?= e(setting('payment_account_name')) ?></p>
            <?php endif; ?>
          </div>
        </div>
        <div class="field" style="margin-top:18px">
          <label>Payment screenshot</label>
          <div class="dropzone">
            <img class="dz-preview" alt="">
            <div class="dz-title">Upload screenshot</div>
            <div class="dz-sub">Click or drag an image here · JPG, PNG, WEBP up to 5 MB</div>
            <input type="file" name="screenshot" accept="image/*">
          </div>
        </div>
        <div class="field"><label for="payment_ref">Transaction ID / reference (optional)</label><input id="payment_ref" name="payment_ref" value="<?= e($old['payment_ref']) ?>"></div>
        </div>
      </div>
    </div>

    <aside class="panel sticky-panel">
      <h3>Order summary</h3>
      <div class="sum-items">
        <?php foreach ($cart['items'] as $it): ?>
          <div class="sum-item">
            <div class="thumb"><img src="<?= e(img_url($it['image'])) ?>" alt=""><b><?= $it['qty'] ?></b></div>
            <div><?= e($it['name']) ?><?php if ($it['size']): ?><div class="muted" style="font-size:12px">Size <?= e($it['size']) ?></div><?php endif; ?></div>
            <div><?= money($it['line']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="summary-row"><span>Subtotal</span><span><?= money($cart['subtotal']) ?></span></div>
      <div class="summary-row"><span>Delivery</span><span data-fee>—</span></div>
      <div class="summary-row summary-total"><span>Total</span><span data-total><?= money($cart['subtotal']) ?></span></div>
      <button type="submit" class="btn btn-block" style="margin-top:24px">Place order</button>
      <p class="muted" style="font-size:12px;margin-top:14px" data-pay-panel="qr">Your order is confirmed once we verify your payment. You will receive an email at every step.</p>
      <p class="muted" style="font-size:12px;margin-top:14px" data-pay-panel="cod">You pay when the order arrives. You will receive an email at every step.</p>
    </aside>
  </form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
