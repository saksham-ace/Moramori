<?php
require __DIR__ . '/includes/bootstrap.php';

$order = null;
$error = '';
$orderNo = strtoupper(trim($_REQUEST['order'] ?? ''));
$email = strtolower(trim($_REQUEST['email'] ?? ''));

// re-upload payment screenshot
if (is_post() && post('action') === 'reupload') {
    csrf_check();
    $o = q_one('SELECT * FROM orders WHERE order_no = ?', [post('order_no')]);
    if (!$o || !can_view_order($o)) redirect('track.php');
    if (!in_array($o['payment_status'], ['pending', 'rejected'], true)) {
        flash('info', 'This payment is already being processed.');
    } else {
        try {
            $path = store_upload($_FILES['screenshot'] ?? ['error' => UPLOAD_ERR_NO_FILE], 'payments');
            q("UPDATE orders SET payment_screenshot = ?, payment_status = 'submitted' WHERE id = ?", [$path, $o['id']]);
            add_order_history((int)$o['id'], 'payment', 'New payment screenshot uploaded.');
            $o = q_one('SELECT * FROM orders WHERE id = ?', [$o['id']]);
            $admin = setting('admin_email', setting('store_email'));
            if ($admin) {
                Mailer::send($admin, 'New payment screenshot — ' . $o['order_no'], email_layout('Payment re-submitted',
                    '<p>The customer re-uploaded a payment screenshot for <strong>' . e($o['order_no']) . '</strong>.</p>'
                    . email_button('Review payment', absolute_url('admin/order.php?id=' . $o['id']))));
            }
            flash('success', 'Thank you — we will verify your payment shortly.');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }
    }
    redirect('track.php?order=' . urlencode($o['order_no']));
}

if ($orderNo !== '') {
    $o = q_one('SELECT * FROM orders WHERE order_no = ?', [$orderNo]);
    if ($o && ($email !== '' ? strtolower($o['email']) === $email : can_view_order($o))) {
        $order = $o;
        $_SESSION['my_orders'][] = $o['order_no'];
        $_SESSION['my_orders'] = array_values(array_unique($_SESSION['my_orders']));
    } elseif ($email !== '' || $o) {
        $error = 'We could not find an order with those details. Please check the order number and email.';
    }
}

$pageTitle = 'Track your order';
include __DIR__ . '/includes/header.php';
?>
<section class="page-hero container">
  <div class="eyebrow reveal">Order status</div>
  <h1 class="reveal reveal-d1">Track your order</h1>
</section>
<div class="container" style="padding-bottom:100px">
  <?php if ($order): ?>
    <?php include __DIR__ . '/includes/partials/order-detail.php'; ?>
  <?php else: ?>
    <div class="narrow" style="max-width:520px">
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
      <form method="get" class="reveal reveal-d2">
        <div class="field"><label for="order">Order number</label><input id="order" name="order" value="<?= e($orderNo) ?>" placeholder="e.g. MM261117A1B2C" required></div>
        <div class="field"><label for="email">Email used at checkout</label><input id="email" type="email" name="email" value="<?= e($email) ?>" required></div>
        <button class="btn btn-block" type="submit">Track order</button>
        <p class="auth-alt">Have an account? <a href="<?= url('account/orders.php') ?>">See all your orders</a></p>
      </form>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
