<?php /** @var array $recent */ ?>
<?php if (!$recent): ?>
  <div class="panel" style="text-align:center">
    <p class="serif" style="font-size:24px;margin:0 0 16px">No orders yet</p>
    <a class="btn" href="<?= url('shop.php') ?>">Start shopping</a>
  </div>
<?php else: ?>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Order</th><th>Date</th><th>Status</th><th>Payment</th><th>Total</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($recent as $o): ?>
        <tr>
          <td><a class="link" href="<?= url('account/order.php?no=' . urlencode($o['order_no'])) ?>"><?= e($o['order_no']) ?></a></td>
          <td><?= fmt_date($o['created_at']) ?></td>
          <td><span class="badge <?= $o['status'] === 'cancelled' ? 'badge-bad' : ($o['status'] === 'delivered' ? 'badge-ok' : '') ?>"><?= e(status_label($o['status'])) ?></span></td>
          <td><span class="badge <?= ['verified' => 'badge-ok', 'rejected' => 'badge-bad'][$o['payment_status']] ?? 'badge-warn' ?>"><?= e(payment_label($o['payment_status'], $o['payment_method'])) ?></span></td>
          <td><?= money($o['total']) ?></td>
          <td><a class="text-link" href="<?= url('account/order.php?no=' . urlencode($o['order_no'])) ?>">Track</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
