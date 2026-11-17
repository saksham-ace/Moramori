<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$order = q_one('SELECT * FROM orders WHERE id = ?', [$id]);
if (!$order) { flash('error', 'Order not found.'); redirect('admin/orders.php'); }

if (is_post()) {
    csrf_check();
    $action = post('action');
    $note = post('note') ?: null;
    $notify = post('notify') === '1';

    if ($action === 'verify_payment' || $action === 'reject_payment') {
        $ps = $action === 'verify_payment' ? 'verified' : 'rejected';
        $cod = $order['payment_method'] === 'cod';
        q('UPDATE orders SET payment_status = ? WHERE id = ?', [$ps, $id]);
        add_order_history($id, 'payment', ($ps === 'verified' ? ($cod ? 'Cash collected' : 'Payment verified') : 'Payment rejected') . ($note ? ': ' . $note : ''));
        if ($ps === 'verified' && !$cod && $order['status'] === 'placed') {
            q("UPDATE orders SET status = 'confirmed' WHERE id = ?", [$id]);
            add_order_history($id, 'confirmed', 'Payment verified — order confirmed.');
        }
        $order = q_one('SELECT * FROM orders WHERE id = ?', [$id]);
        if ($notify) mail_payment_update($order, $note);
        flash('success', $ps === 'verified' ? ($cod ? 'Cash marked as collected.' : 'Payment verified and order confirmed.') : 'Payment marked as rejected.');
    }

    if ($action === 'status') {
        $new = post('status');
        if (isset(ORDER_STATUSES[$new]) && $new !== $order['status']) {
            db()->beginTransaction();
            q('UPDATE orders SET status = ? WHERE id = ?', [$new, $id]);
            // return stock when cancelling; take it again if un-cancelling
            if ($new === 'cancelled' || $order['status'] === 'cancelled') {
                $sign = $new === 'cancelled' ? '+' : '-';
                foreach (q_all('SELECT product_id, qty FROM order_items WHERE order_id = ? AND product_id IS NOT NULL', [$id]) as $it) {
                    q("UPDATE products SET stock = stock $sign ? WHERE id = ?", [$it['qty'], $it['product_id']]);
                }
            }
            add_order_history($id, $new, $note);
            // cash on delivery: delivered means the cash was collected
            if ($new === 'delivered' && $order['payment_method'] === 'cod' && $order['payment_status'] !== 'verified') {
                q("UPDATE orders SET payment_status = 'verified' WHERE id = ?", [$id]);
                add_order_history($id, 'payment', 'Cash collected on delivery');
            }
            db()->commit();
            $order = q_one('SELECT * FROM orders WHERE id = ?', [$id]);
            if ($notify) mail_status_update($order, $note);
            flash('success', 'Order status updated to “' . status_label($new) . '”' . ($notify ? ' and customer emailed.' : '.'));
        } elseif ($note) {
            add_order_history($id, $order['status'], $note);
            if ($notify) mail_status_update($order, $note);
            flash('success', 'Note added.');
        }
    }
    redirect('admin/order.php?id=' . $id);
}

$items = q_all('SELECT * FROM order_items WHERE order_id = ?', [$id]);
$history = q_all('SELECT * FROM order_status_history WHERE order_id = ? ORDER BY id DESC', [$id]);
$customer = $order['user_id'] ? q_one('SELECT id, name, email, created_at FROM users WHERE id = ?', [$order['user_id']]) : null;
$prevOrders = (int)q_val('SELECT COUNT(*) FROM orders WHERE email = ? AND id <> ?', [$order['email'], $id]);

$pageTitle = 'Order ' . $order['order_no'];
include __DIR__ . '/_head.php';
?>
<div class="inline-form" style="margin-bottom:18px">
  <a class="btn btn-outline btn-sm" href="<?= url('admin/orders.php') ?>">← Orders</a>
  <span class="badge <?= $order['status'] === 'cancelled' ? 'b-bad' : 'b-dark' ?>"><?= e(status_label($order['status'])) ?></span>
  <span class="badge <?= ['verified' => 'b-ok', 'submitted' => 'b-warn', 'rejected' => 'b-bad'][$order['payment_status']] ?? '' ?>"><?= e(payment_label($order['payment_status'], $order['payment_method'])) ?></span>
  <span class="muted small">Placed <?= fmt_date($order['created_at'], 'd M Y, g:i a') ?></span>
  <a class="btn btn-outline btn-sm" style="margin-left:auto" target="_blank" href="<?= url('track.php?order=' . urlencode($order['order_no']) . '&email=' . urlencode($order['email'])) ?>">Customer view ↗</a>
</div>

<div class="cols">
  <div>
    <div class="card">
      <div class="card-head"><h2>Payment</h2><strong style="font-family:var(--serif);font-size:26px"><?= money($order['total']) ?></strong></div>
      <?php if ($order['payment_method'] === 'cod'): ?>
        <div class="cod-panel">
          <span class="badge b-dark">Cash on delivery</span>
          <p style="margin:12px 0 0">Collect <strong><?= money($order['total']) ?></strong> in cash on delivery. Mark it as collected once you have the money
            (this also happens automatically when you set the order to <em>Delivered</em>).</p>
        </div>
        <form method="post" style="margin-top:16px">
          <?= csrf_field() ?>
          <input type="hidden" name="note" value="">
          <div class="inline-form">
            <label class="check"><input type="checkbox" name="notify" value="1"> Email customer a receipt</label>
            <span style="flex:1"></span>
            <?php if ($order['payment_status'] !== 'verified'): ?>
              <button class="btn btn-ok" name="action" value="verify_payment">Mark cash collected</button>
            <?php else: ?><span class="badge b-ok">Cash collected</span><?php endif; ?>
          </div>
        </form>
      <?php else: ?>
      <?php if ($order['payment_screenshot']): ?>
        <div class="shot"><a href="<?= url('admin/file.php?order=' . $id) ?>" target="_blank"><img src="<?= url('admin/file.php?order=' . $id) ?>" alt="Payment screenshot"></a></div>
      <?php else: ?><div class="empty">No screenshot uploaded.</div><?php endif; ?>
      <p class="small muted" style="margin:10px 0 16px">Reference: <?= e($order['payment_ref'] ?: '—') ?></p>
      <form method="post">
        <?= csrf_field() ?>
        <div class="field"><label>Note to customer (optional — included in the email)</label><textarea name="note" rows="2" placeholder="e.g. Amount received did not match the total"></textarea></div>
        <div class="inline-form">
          <label class="check"><input type="checkbox" name="notify" value="1" checked> Email customer</label>
          <span style="flex:1"></span>
          <button class="btn btn-bad" name="action" value="reject_payment" data-confirm="Mark this payment as rejected?">Reject payment</button>
          <button class="btn btn-ok" name="action" value="verify_payment">Verify payment</button>
        </div>
      </form>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>Items</h2>
      <table>
        <thead><tr><th></th><th>Product</th><th>Size</th><th class="right">Price</th><th class="right">Qty</th><th class="right">Total</th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): ?>
          <tr>
            <td style="width:58px"><img class="thumb" src="<?= e(img_url($it['image'])) ?>" alt=""></td>
            <td><?= $it['product_id'] ? '<a class="link" href="' . url('admin/product-edit.php?id=' . $it['product_id']) . '">' . e($it['name']) . '</a>' : e($it['name']) ?></td>
            <td><?= e($it['size'] ?: '—') ?></td>
            <td class="right nowrap"><?= money($it['price']) ?></td>
            <td class="right"><?= (int)$it['qty'] ?></td>
            <td class="right nowrap"><?= money($it['price'] * $it['qty']) ?></td>
          </tr>
        <?php endforeach; ?>
        <tr><td colspan="5" class="right muted">Subtotal</td><td class="right nowrap"><?= money($order['subtotal']) ?></td></tr>
        <tr><td colspan="5" class="right muted">Delivery — <?= e($order['location_name']) ?></td><td class="right nowrap"><?= money($order['delivery_fee']) ?></td></tr>
        <tr><td colspan="5" class="right"><strong>Total</strong></td><td class="right nowrap"><strong><?= money($order['total']) ?></strong></td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <div>
    <div class="card">
      <h2>Update status</h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="status">
        <div class="field"><select name="status">
          <?php foreach (ORDER_STATUSES as $k => $l): ?><option value="<?= $k ?>" <?= $order['status'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
        </select></div>
        <div class="field"><textarea name="note" rows="2" placeholder="Tracking note, e.g. courier name & tracking no. (optional)"></textarea></div>
        <div class="inline-form" style="justify-content:space-between">
          <label class="check"><input type="checkbox" name="notify" value="1" checked> Email customer</label>
          <button class="btn">Save</button>
        </div>
      </form>
    </div>

    <div class="card">
      <h2>Customer</h2>
      <dl class="kv">
        <dt>Name</dt><dd><?= e($order['name']) ?></dd>
        <dt>Email</dt><dd><a class="link" href="mailto:<?= e($order['email']) ?>"><?= e($order['email']) ?></a></dd>
        <dt>Phone</dt><dd><a class="link" href="tel:<?= e($order['phone']) ?>"><?= e($order['phone']) ?></a></dd>
        <dt>Address</dt><dd><?= e($order['address']) ?></dd>
        <dt>Location</dt><dd><?= e($order['location_name']) ?></dd>
        <?php if ($order['notes']): ?><dt>Notes</dt><dd><?= nl2br(e($order['notes'])) ?></dd><?php endif; ?>
        <dt>Account</dt><dd><?= $customer ? '<a class="link" href="' . url('admin/customer.php?id=' . $customer['id']) . '">Registered</a>' : 'Guest' ?></dd>
        <dt>Other orders</dt><dd><?= $prevOrders ? '<a class="link" href="' . url('admin/orders.php?q=' . urlencode($order['email'])) . '">' . $prevOrders . '</a>' : '0' ?></dd>
      </dl>
    </div>

    <div class="card">
      <h2>History</h2>
      <ul class="timeline">
        <?php foreach ($history as $h): ?>
          <li><strong><?= e($h['status'] === 'payment' ? 'Payment' : status_label($h['status'])) ?></strong>
            <?php if ($h['note']): ?> — <?= e($h['note']) ?><?php endif; ?>
            <div class="muted small"><?= fmt_date($h['created_at'], 'd M Y, g:i a') ?></div></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</div>
<?php include __DIR__ . '/_foot.php'; ?>
