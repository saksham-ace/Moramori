<?php
/* Branded email templates. */

function email_layout(string $title, string $content): string
{
    $store = e(setting('store_name', 'Mora Mori'));
    $home = absolute_url('');
    return '<!doctype html><html><body style="margin:0;background:#f6f5f2;font-family:Helvetica,Arial,sans-serif;color:#111">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f6f5f2;padding:32px 12px"><tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff">
<tr><td style="padding:36px 40px 20px;text-align:center;border-bottom:1px solid #eee">
<a href="' . $home . '" style="text-decoration:none;color:#111;font-family:Georgia,serif;font-size:26px;letter-spacing:6px">' . strtoupper($store) . '</a></td></tr>
<tr><td style="padding:36px 40px">
<h1 style="font-family:Georgia,serif;font-weight:normal;font-size:24px;margin:0 0 20px">' . e($title) . '</h1>
<div style="font-size:14px;line-height:1.7;color:#333">' . $content . '</div></td></tr>
<tr><td style="padding:24px 40px;border-top:1px solid #eee;font-size:11px;color:#888;text-align:center;letter-spacing:1px">
' . $store . ' · ' . e(setting('store_phone', '')) . ' · ' . e(setting('store_email', '')) . '</td></tr>
</table></td></tr></table></body></html>';
}

function email_button(string $label, string $href): string
{
    return '<p style="margin:28px 0"><a href="' . e($href) . '" style="background:#111;color:#fff;text-decoration:none;padding:14px 28px;font-size:12px;letter-spacing:2px;text-transform:uppercase">' . e($label) . '</a></p>';
}

function email_order_table(array $order): string
{
    $items = q_all('SELECT * FROM order_items WHERE order_id = ?', [$order['id']]);
    $rows = '';
    foreach ($items as $i) {
        $rows .= '<tr><td style="padding:8px 0;border-bottom:1px solid #f0f0f0">' . e($i['name'])
            . ($i['size'] ? ' <span style="color:#888">(' . e($i['size']) . ')</span>' : '')
            . ' × ' . (int)$i['qty'] . '</td><td align="right" style="padding:8px 0;border-bottom:1px solid #f0f0f0">'
            . money($i['price'] * $i['qty']) . '</td></tr>';
    }
    return '<table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;margin:16px 0">' . $rows
        . '<tr><td style="padding:8px 0;color:#666">Subtotal</td><td align="right">' . money($order['subtotal']) . '</td></tr>'
        . '<tr><td style="padding:4px 0;color:#666">Delivery (' . e($order['location_name']) . ')</td><td align="right">' . money($order['delivery_fee']) . '</td></tr>'
        . '<tr><td style="padding:10px 0;font-weight:bold;border-top:1px solid #111">Total</td><td align="right" style="font-weight:bold;border-top:1px solid #111">' . money($order['total']) . '</td></tr></table>';
}

function track_link(array $order): string
{
    return absolute_url('track.php?order=' . urlencode($order['order_no']) . '&email=' . urlencode($order['email']));
}

function mail_welcome(array $user): void
{
    $html = email_layout('Welcome, ' . $user['name'],
        '<p>Thank you for creating an account with ' . e(setting('store_name', 'Mora Mori')) . '.</p>'
        . '<p>You can now track your orders, see your order history and check your account activity at any time.</p>'
        . email_button('Visit the store', absolute_url('shop.php')));
    Mailer::send($user['email'], 'Welcome to ' . setting('store_name', 'Mora Mori'), $html);
}

function mail_password_reset(array $user, string $token): void
{
    $link = absolute_url('account/reset.php?token=' . urlencode($token));
    $html = email_layout('Reset your password',
        '<p>Hi ' . e($user['name']) . ',</p><p>We received a request to reset your password. This link is valid for 1 hour.</p>'
        . email_button('Choose a new password', $link)
        . '<p style="color:#888;font-size:12px">If you did not ask for this, you can ignore this email.</p>');
    Mailer::send($user['email'], 'Reset your password', $html);
}

function mail_order_placed(array $order): void
{
    $html = email_layout('Thank you for your order',
        '<p>Hi ' . e($order['name']) . ',</p>'
        . ($order['payment_method'] === 'cod'
            ? '<p>We have received your order <strong>' . e($order['order_no']) . '</strong>. You chose <strong>cash on delivery</strong> — '
              . 'please keep <strong>' . money($order['total']) . '</strong> ready when it arrives. We will confirm your order shortly.</p>'
            : '<p>We have received your order <strong>' . e($order['order_no']) . '</strong> and your payment screenshot. '
              . 'Our team will verify the payment and confirm your order shortly.</p>')
        . email_order_table($order)
        . '<p><strong>Delivering to:</strong><br>' . e($order['address']) . ', ' . e($order['location_name']) . '<br>' . e($order['phone']) . '</p>'
        . email_button('Track your order', track_link($order)));
    Mailer::send($order['email'], 'Order ' . $order['order_no'] . ' received', $html);

    $admin = setting('admin_email', setting('store_email'));
    if ($admin) {
        $ahtml = email_layout('New order ' . $order['order_no'],
            '<p><strong>' . e($order['name']) . '</strong> (' . e($order['email']) . ', ' . e($order['phone']) . ') placed an order.</p>'
            . email_order_table($order)
            . ($order['payment_method'] === 'cod'
                ? '<p><strong>Payment: Cash on delivery</strong> — deliver to ' . e($order['location_name']) . '.</p>'
                : '<p>Payment: QR · reference ' . e($order['payment_ref'] ?: '—') . '</p>')
            . email_button($order['payment_method'] === 'cod' ? 'View order' : 'Review payment', absolute_url('admin/order.php?id=' . $order['id'])));
        Mailer::send($admin, 'New order ' . $order['order_no'] . ' — ' . money($order['total']) . ($order['payment_method'] === 'cod' ? ' (COD)' : ''), $ahtml);
    }
}

function mail_payment_update(array $order, ?string $note = null): void
{
    if ($order['payment_method'] === 'cod' && $order['payment_status'] === 'verified') {
        $title = 'Payment received';
        $msg = '<p>Thank you — we have received your cash payment of <strong>' . money($order['total']) . '</strong> for order <strong>' . e($order['order_no']) . '</strong>.</p>';
    } elseif ($order['payment_status'] === 'verified') {
        $title = 'Payment confirmed';
        $msg = '<p>Good news — we have verified your payment of <strong>' . money($order['total']) . '</strong> for order <strong>' . e($order['order_no']) . '</strong>. We are preparing it now.</p>';
    } else {
        $title = 'We could not verify your payment';
        $msg = '<p>Unfortunately we could not verify the payment for order <strong>' . e($order['order_no']) . '</strong>.</p>'
            . '<p>Please upload a clear screenshot of the completed payment from the order tracking page.</p>';
    }
    if ($note) $msg .= '<p style="background:#f6f5f2;padding:12px 16px"><em>' . nl2br(e($note)) . '</em></p>';
    Mailer::send($order['email'], $title . ' — ' . $order['order_no'], email_layout($title, $msg . email_button('View order', track_link($order))));
}

function mail_status_update(array $order, ?string $note = null): void
{
    $label = status_label($order['status']);
    $msg = '<p>Hi ' . e($order['name']) . ',</p><p>Your order <strong>' . e($order['order_no']) . '</strong> is now: <strong>' . e($label) . '</strong>.</p>';
    if ($note) $msg .= '<p style="background:#f6f5f2;padding:12px 16px"><em>' . nl2br(e($note)) . '</em></p>';
    Mailer::send($order['email'], 'Order ' . $order['order_no'] . ': ' . $label, email_layout($label, $msg . email_button('Track your order', track_link($order))));
}
