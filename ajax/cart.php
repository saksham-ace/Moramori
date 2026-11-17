<?php
require __DIR__ . '/../includes/bootstrap.php';
if (!is_post()) json_out(['ok' => false, 'message' => 'Bad request'], 405);
csrf_check();

$action = post('action');
$ok = true;
$message = '';

if ($action === 'add') {
    [$ok, $message] = cart_add((int)post('product_id'), (string)post('size'), max(1, (int)post('qty', 1)));
} elseif ($action === 'update') {
    [$ok, $message] = cart_update((string)post('key'), (int)post('qty'));
}

$cart = cart_items();
ob_start();
include __DIR__ . '/../includes/partials/mini-cart.php';
$html = ob_get_clean();

json_out(['ok' => $ok, 'message' => $message, 'count' => $cart['count'], 'html' => $html]);
