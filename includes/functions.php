<?php
/* ---------- output & urls ---------- */

function e($v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = ROOT_PATH . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? filemtime($file) : 1;
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

function absolute_url(string $path = ''): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . url($path);
}

function redirect(string $path): void
{
    $target = preg_match('#^https?://#', $path) ? $path : url($path);
    header('Location: ' . $target);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function post(string $key, $default = '')
{
    return isset($_POST[$key]) ? (is_string($_POST[$key]) ? trim($_POST[$key]) : $_POST[$key]) : $default;
}

/* ---------- flash messages ---------- */

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------- CSRF ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">';
}

function csrf_check(): void
{
    $t = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        http_response_code(419);
        exit('Your session expired. Please go back, refresh the page and try again.');
    }
}

/* ---------- settings ---------- */

function setting(string $key, $default = '')
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (q_all('SELECT `key`, `value` FROM settings') as $r) {
                $cache[$r['key']] = $r['value'];
            }
        } catch (Throwable $e) {
        }
    }
    return array_key_exists($key, $cache) && $cache[$key] !== null && $cache[$key] !== '' ? $cache[$key] : $default;
}

function save_setting(string $key, $value): void
{
    q('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)', [$key, $value]);
}

/* ---------- formatting ---------- */

function money($amount): string
{
    $cur = setting('currency', 'Rs.');
    return $cur . ' ' . number_format((float)$amount, 0);
}

function slugify(string $s): string
{
    $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-'));
    return $s !== '' ? $s : 'item-' . bin2hex(random_bytes(3));
}

function unique_slug(string $table, string $name, ?int $ignoreId = null): string
{
    $base = slugify($name);
    $slug = $base;
    $i = 2;
    while (true) {
        $sql = "SELECT id FROM $table WHERE slug = ?" . ($ignoreId ? ' AND id <> ?' : '');
        $params = $ignoreId ? [$slug, $ignoreId] : [$slug];
        if (!q_val($sql, $params)) {
            return $slug;
        }
        $slug = $base . '-' . $i++;
    }
}

function fmt_date(?string $dt, string $format = 'd M Y'): string
{
    return $dt ? date($format, strtotime($dt)) : '';
}

function client_ip(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 64);
}

function describe_agent(?string $ua): string
{
    $ua = (string)$ua;
    $browser = 'Browser';
    foreach (['Edg' => 'Edge', 'OPR' => 'Opera', 'Chrome' => 'Chrome', 'Firefox' => 'Firefox', 'Safari' => 'Safari', 'curl' => 'curl'] as $k => $v) {
        if (stripos($ua, $k) !== false) { $browser = $v; break; }
    }
    $os = 'Unknown OS';
    foreach (['Windows' => 'Windows', 'Android' => 'Android', 'iPhone' => 'iPhone', 'iPad' => 'iPad', 'Mac OS' => 'macOS', 'Linux' => 'Linux'] as $k => $v) {
        if (stripos($ua, $k) !== false) { $os = $v; break; }
    }
    return "$browser on $os";
}

/* ---------- products ---------- */

function product_images(int $productId): array
{
    return q_all('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort, id', [$productId]);
}

function img_url(?string $path): string
{
    if (!$path) {
        return url('assets/img/placeholder.svg');
    }
    return url($path);
}

/** Attaches `image` and `image2` keys to each product row. */
function with_images(array $products): array
{
    if (!$products) return $products;
    $ids = array_column($products, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $imgs = [];
    foreach (q_all("SELECT product_id, path FROM product_images WHERE product_id IN ($in) ORDER BY sort, id", $ids) as $r) {
        $imgs[$r['product_id']][] = $r['path'];
    }
    foreach ($products as &$p) {
        $p['image'] = $imgs[$p['id']][0] ?? null;
        $p['image2'] = $imgs[$p['id']][1] ?? null;
    }
    return $products;
}

/** True if the product can be ordered (in stock, or set to keep selling when out of stock). */
function can_buy(array $p): bool
{
    return (int)$p['stock'] > 0 || !empty($p['allow_backorder']);
}

function product_sizes(array $p): array
{
    $s = array_filter(array_map('trim', explode(',', (string)($p['sizes'] ?? ''))), 'strlen');
    return array_values($s);
}

/* ---------- uploads ---------- */

/**
 * Validates and stores an uploaded image. Returns relative path (e.g. uploads/products/abc.jpg) or throws.
 */
function store_upload(array $file, string $folder): string
{
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new RuntimeException('Invalid upload.');
    }
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Please choose a file.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed (code ' . $file['error'] . ').');
    }
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('File is too large (max 5 MB).');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, PNG, WEBP or GIF images are allowed.');
    }
    if (@getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('That file is not a valid image.');
    }
    $dir = UPLOAD_PATH . '/' . $folder;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $name = date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], "$dir/$name")) {
        throw new RuntimeException('Could not save the file.');
    }
    return "uploads/$folder/$name";
}

/** Normalises $_FILES['x'] with multiple files into a list of single-file arrays. */
function files_list(array $f): array
{
    if (!is_array($f['name'])) return [$f];
    $out = [];
    foreach ($f['name'] as $i => $n) {
        $out[] = ['name' => $n, 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    }
    return $out;
}

/* ---------- orders ---------- */

const ORDER_STATUSES = [
    'placed'           => 'Order placed',
    'confirmed'        => 'Confirmed',
    'processing'       => 'Being prepared',
    'shipped'          => 'Shipped',
    'out_for_delivery' => 'Out for delivery',
    'delivered'        => 'Delivered',
    'cancelled'        => 'Cancelled',
];

const PAYMENT_STATUSES = [
    'pending'   => 'Awaiting payment',
    'submitted' => 'Under verification',
    'verified'  => 'Payment verified',
    'rejected'  => 'Payment rejected',
];

function status_label(string $s): string
{
    return ORDER_STATUSES[$s] ?? ucfirst(str_replace('_', ' ', $s));
}

function payment_label(string $s, string $method = 'qr'): string
{
    if ($method === 'cod') {
        return ['pending' => 'Cash on delivery', 'verified' => 'Cash collected', 'rejected' => 'Not paid'][$s] ?? ucfirst($s);
    }
    return PAYMENT_STATUSES[$s] ?? ucfirst($s);
}

function payment_badge(string $s): string
{
    return ['verified' => 'ok', 'submitted' => 'warn', 'pending' => 'warn', 'rejected' => 'bad'][$s] ?? '';
}

function generate_order_no(): string
{
    do {
        $no = 'MM' . date('ymd') . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
    } while (q_val('SELECT id FROM orders WHERE order_no = ?', [$no]));
    return $no;
}

function add_order_history(int $orderId, string $status, ?string $note = null): void
{
    q('INSERT INTO order_status_history (order_id, status, note) VALUES (?, ?, ?)', [$orderId, $status, $note]);
}

/** Customer may view an order if they own it, placed it in this session, or verified it via order no + email. */
function can_view_order(array $order): bool
{
    $u = current_user();
    if ($u && ($u['role'] === 'admin' || (int)$order['user_id'] === (int)$u['id'])) return true;
    if ($u && $order['user_id'] === null && strtolower($order['email']) === strtolower($u['email'])) return true;
    return in_array($order['order_no'], $_SESSION['my_orders'] ?? [], true);
}

function categories(): array
{
    static $c = null;
    if ($c === null) {
        $c = q_all('SELECT * FROM categories ORDER BY sort, name');
    }
    return $c;
}

function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
