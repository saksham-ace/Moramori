<?php
/* Session cart: $_SESSION['cart'] = [ "productId|size" => qty ] */

function cart_raw(): array
{
    return $_SESSION['cart'] ?? [];
}

function cart_key(int $pid, string $size = ''): string
{
    return $pid . '|' . $size;
}

function cart_add(int $pid, string $size, int $qty): array
{
    $p = q_one('SELECT * FROM products WHERE id = ? AND is_active = 1', [$pid]);
    if (!$p) return [false, 'Product not found.'];
    $sizes = product_sizes($p);
    if ($sizes && !in_array($size, $sizes, true)) return [false, 'Please select a size.'];
    if (!$sizes) $size = '';
    $key = cart_key($pid, $size);
    $current = $_SESSION['cart'][$key] ?? 0;
    $inCartTotal = cart_qty_for_product($pid);
    if (!$p['allow_backorder'] && $inCartTotal + $qty > (int)$p['stock']) {
        return [false, $p['stock'] > 0 ? 'Only ' . $p['stock'] . ' left in stock.' : 'Sorry, this item is sold out.'];
    }
    $_SESSION['cart'][$key] = $current + max(1, $qty);
    return [true, 'Added to bag.'];
}

function cart_qty_for_product(int $pid): int
{
    $n = 0;
    foreach (cart_raw() as $k => $q) {
        if ((int)explode('|', $k)[0] === $pid) $n += $q;
    }
    return $n;
}

function cart_update(string $key, int $qty): array
{
    if (!isset($_SESSION['cart'][$key])) return [false, 'Item not in bag.'];
    if ($qty <= 0) {
        unset($_SESSION['cart'][$key]);
        return [true, 'Removed.'];
    }
    $pid = (int)explode('|', $key)[0];
    $p = q_one('SELECT stock, allow_backorder FROM products WHERE id = ?', [$pid]);
    $stock = (int)($p['stock'] ?? 0);
    $others = cart_qty_for_product($pid) - $_SESSION['cart'][$key];
    if ($qty > 99) $qty = 99;
    if ($p && !$p['allow_backorder'] && $others + $qty > $stock) {
        $_SESSION['cart'][$key] = max(1, $stock - $others);
        return [false, 'Only ' . $stock . ' available.'];
    }
    $_SESSION['cart'][$key] = $qty;
    return [true, 'Updated.'];
}

function cart_clear(): void
{
    $_SESSION['cart'] = [];
}

/** Returns detailed lines + totals, dropping unavailable products. */
function cart_items(): array
{
    $raw = cart_raw();
    if (!$raw) return ['items' => [], 'count' => 0, 'subtotal' => 0];
    $ids = array_unique(array_map(fn($k) => (int)explode('|', $k)[0], array_keys($raw)));
    $in = implode(',', array_fill(0, count($ids), '?'));
    $products = [];
    foreach (with_images(q_all("SELECT * FROM products WHERE id IN ($in) AND is_active = 1", $ids)) as $p) {
        $products[$p['id']] = $p;
    }
    $items = [];
    $count = 0;
    $subtotal = 0;
    foreach ($raw as $key => $qty) {
        [$pid, $size] = array_pad(explode('|', $key, 2), 2, '');
        if (!isset($products[$pid])) {
            unset($_SESSION['cart'][$key]);
            continue;
        }
        $p = $products[$pid];
        $line = (float)$p['price'] * $qty;
        $items[] = [
            'key' => $key, 'product_id' => (int)$pid, 'name' => $p['name'], 'slug' => $p['slug'],
            'size' => $size, 'price' => (float)$p['price'], 'qty' => (int)$qty, 'line' => $line,
            'image' => $p['image'], 'stock' => (int)$p['stock'], 'backorder' => (bool)$p['allow_backorder'],
            'preorder' => (bool)$p['allow_backorder'] && (int)$p['stock'] < (int)$qty,
        ];
        $count += $qty;
        $subtotal += $line;
    }
    return ['items' => $items, 'count' => $count, 'subtotal' => $subtotal];
}

function cart_count(): int
{
    return array_sum(cart_raw());
}
