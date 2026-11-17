<?php /** @var array $recent */ ?>
<?php if (!$recent): ?><div class="empty">No orders yet.</div><?php else: ?>
<div class="table-wrap"><table>
  <thead><tr><th>Order</th><th>Customer</th><th>Location</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th></tr></thead>
  <tbody>
  <?php foreach ($recent as $o): ?>
    <tr onclick="location.href='<?= url('admin/order.php?id=' . $o['id']) ?>'" style="cursor:pointer">
      <td><a class="link" href="<?= url('admin/order.php?id=' . $o['id']) ?>"><?= e($o['order_no']) ?></a></td>
      <td><?= e($o['name']) ?><div class="muted small"><?= e($o['phone']) ?></div></td>
      <td><?= e($o['location_name']) ?></td>
      <td class="nowrap"><?= money($o['total']) ?><div class="muted small"><?= $o['payment_method'] === 'cod' ? 'COD' : 'QR' ?></div></td>
      <td><span class="badge <?= ['verified' => 'b-ok', 'submitted' => 'b-warn', 'rejected' => 'b-bad'][$o['payment_status']] ?? '' ?>"><?= e(payment_label($o['payment_status'], $o['payment_method'])) ?></span></td>
      <td><span class="badge <?= $o['status'] === 'cancelled' ? 'b-bad' : ($o['status'] === 'delivered' ? 'b-ok' : '') ?>"><?= e(status_label($o['status'])) ?></span></td>
      <td class="nowrap muted small"><?= fmt_date($o['created_at'], 'd M, g:i a') ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
