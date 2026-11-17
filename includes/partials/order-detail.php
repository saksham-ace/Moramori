<?php
/** @var array $order */
$items = q_all('SELECT * FROM order_items WHERE order_id = ?', [$order['id']]);
$history = q_all('SELECT * FROM order_status_history WHERE order_id = ? ORDER BY created_at, id', [$order['id']]);
$flow = ['placed', 'confirmed', 'processing', 'shipped', 'out_for_delivery', 'delivered'];
$cancelled = $order['status'] === 'cancelled';
$idx = array_search($order['status'], $flow, true);
$idx = $idx === false ? 0 : $idx;
$pct = $cancelled ? 0 : round($idx / (count($flow) - 1) * 100);
$histByStatus = [];
foreach ($history as $h) $histByStatus[$h['status']] = $h;
$payBadge = ['verified' => 'badge-ok', 'submitted' => 'badge-warn', 'pending' => 'badge-warn', 'rejected' => 'badge-bad'][$order['payment_status']] ?? '';
?>
<div class="two-col">
  <div>
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap">
      <div>
        <div class="eyebrow">Order</div>
        <div class="serif" style="font-size:34px;line-height:1.1"><?= e($order['order_no']) ?></div>
        <div class="muted" style="font-size:13px">Placed <?= fmt_date($order['created_at'], 'd M Y, g:i a') ?></div>
      </div>
      <div style="display:flex;gap:6px;flex-wrap:wrap">
        <span class="badge <?= $cancelled ? 'badge-bad' : 'badge-dark' ?>"><?= e(status_label($order['status'])) ?></span>
        <span class="badge <?= $payBadge ?>"><?= e(payment_label($order['payment_status'], $order['payment_method'])) ?></span>
      </div>
    </div>

    <?php if (!$cancelled): ?>
      <div class="progress-bar"><span data-w="<?= $pct ?>%" style="width:0"></span></div>
      <div class="progress-labels"><span>Placed</span><span>Shipped</span><span>Delivered</span></div>
    <?php endif; ?>

    <?php if ($order['payment_method'] === 'qr' && in_array($order['payment_status'], ['rejected', 'pending'], true)): ?>
      <div class="alert alert-error" style="margin-top:28px">
        <strong>We could not verify your payment.</strong> Please upload a clear screenshot of the completed payment of <?= money($order['total']) ?>.
        <form method="post" action="<?= url('track.php') ?>" enctype="multipart/form-data" style="margin-top:14px">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="reupload">
          <input type="hidden" name="order_no" value="<?= e($order['order_no']) ?>">
          <div class="dropzone" style="margin-bottom:10px">
            <img class="dz-preview" alt=""><div class="dz-title">Upload new screenshot</div>
            <div class="dz-sub">JPG, PNG, WEBP up to 5 MB</div>
            <input type="file" name="screenshot" accept="image/*" required>
          </div>
          <button class="btn btn-sm" type="submit">Submit screenshot</button>
        </form>
      </div>
    <?php endif; ?>

    <h3 class="block-title" style="margin-top:44px">Tracking</h3>
    <ul class="timeline">
      <?php if ($cancelled): ?>
        <li class="done"><span class="dot"></span><div class="t-label">Order placed</div><div class="t-meta"><?= fmt_date($order['created_at'], 'd M Y, g:i a') ?></div></li>
        <li class="cancel"><span class="dot"></span><div class="t-label">Cancelled</div>
          <?php if (isset($histByStatus['cancelled'])): ?><div class="t-meta"><?= fmt_date($histByStatus['cancelled']['created_at'], 'd M Y, g:i a') ?><?= $histByStatus['cancelled']['note'] ? ' · ' . e($histByStatus['cancelled']['note']) : '' ?></div><?php endif; ?></li>
      <?php else: foreach ($flow as $i => $s):
          $cls = $i < $idx ? 'done' : ($i === $idx ? ($s === 'delivered' ? 'done' : 'current') : '');
          $h = $histByStatus[$s] ?? null; ?>
        <li class="<?= $cls ?>"><span class="dot"></span>
          <div class="t-label"><?= e(status_label($s)) ?></div>
          <?php if ($h && $i <= $idx): ?><div class="t-meta"><?= fmt_date($h['created_at'], 'd M Y, g:i a') ?><?= $h['note'] ? ' · ' . e($h['note']) : '' ?></div><?php endif; ?>
        </li>
      <?php endforeach; endif; ?>
    </ul>

    <h3 class="block-title" style="margin-top:30px">Items</h3>
    <?php foreach ($items as $it): ?>
      <div class="sum-item" style="border-bottom:1px solid var(--line);padding:12px 0">
        <div class="thumb"><img src="<?= e(img_url($it['image'])) ?>" alt=""><b><?= (int)$it['qty'] ?></b></div>
        <div><?= e($it['name']) ?><?php if ($it['size']): ?><div class="muted" style="font-size:12px">Size <?= e($it['size']) ?></div><?php endif; ?></div>
        <div><?= money($it['price'] * $it['qty']) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <aside class="panel sticky-panel">
    <h3>Summary</h3>
    <div class="summary-row"><span>Subtotal</span><span><?= money($order['subtotal']) ?></span></div>
    <div class="summary-row"><span>Delivery · <?= e($order['location_name']) ?></span><span><?= (float)$order['delivery_fee'] > 0 ? money($order['delivery_fee']) : 'Free' ?></span></div>
    <div class="summary-row summary-total"><span>Total</span><span><?= money($order['total']) ?></span></div>
    <h3 style="margin-top:32px">Delivering to</h3>
    <p style="font-size:14px;margin:0"><?= e($order['name']) ?><br><?= e($order['address']) ?><br><?= e($order['location_name']) ?><br><?= e($order['phone']) ?><br><span class="muted"><?= e($order['email']) ?></span></p>
    <p class="muted" style="font-size:12px;margin-top:16px">Payment: <?= $order['payment_method'] === 'cod' ? 'Cash on delivery' : 'QR payment' ?></p>
    <?php if ($order['payment_ref']): ?><p class="muted" style="font-size:12px;margin-top:16px">Payment ref: <?= e($order['payment_ref']) ?></p><?php endif; ?>
  </aside>
</div>
