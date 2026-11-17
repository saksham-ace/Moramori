<?php
/**
 * One-time installer: creates the database, tables, demo content and the first admin.
 * Web:  open /install.php in the browser.
 * CLI:  php install.php "Admin Name" admin@email.com "password"
 * After it runs, an install.lock file is created; delete it to run again (existing data is kept).
 */
require_once __DIR__ . '/config.php';
$cli = PHP_SAPI === 'cli';
$lock = __DIR__ . '/install.lock';

function out(string $m): void { global $cli; echo $cli ? $m . PHP_EOL : '<p>' . htmlspecialchars($m) . '</p>'; }

if (is_file($lock)) {
    $cli ? exit("Already installed. Delete install.lock to re-run.\n")
         : exit('<p style="font-family:sans-serif;padding:40px">Already installed. Delete <code>install.lock</code> to run the installer again. <a href="' . BASE_URL . '/">Go to store</a></p>');
}

$error = '';
$input = null;
if ($cli) {
    $input = ['name' => $argv[1] ?? '', 'email' => strtolower($argv[2] ?? ''), 'password' => $argv[3] ?? ''];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = ['name' => trim($_POST['name'] ?? ''), 'email' => strtolower(trim($_POST['email'] ?? '')), 'password' => (string)($_POST['password'] ?? '')];
}

if ($input) {
    if ($input['name'] === '' || !filter_var($input['email'], FILTER_VALIDATE_EMAIL) || strlen($input['password']) < 8) {
        $error = 'Enter a name, a valid email and a password of at least 8 characters.';
        if ($cli) exit($error . PHP_EOL);
    } else {
        try {
            install($input);
            file_put_contents($lock, date('c'));
            if ($cli) exit("Installed. Admin: {$input['email']}\n");
            header('Location: ' . BASE_URL . '/account/login.php?admin=1');
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
            if ($cli) exit('Install failed: ' . $error . PHP_EOL);
        }
    }
}

function install(array $admin): void
{
    $pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `' . DB_NAME . '`');
    foreach (array_filter(array_map('trim', explode(';', preg_replace('/^--.*$/m', '', file_get_contents(__DIR__ . '/database.sql'))))) as $sql) {
        $pdo->exec($sql);
    }

    // folders
    foreach (['products', 'payments', 'site', 'categories'] as $d) {
        if (!is_dir(UPLOAD_PATH . "/$d")) mkdir(UPLOAD_PATH . "/$d", 0775, true);
    }

    // admin
    $st = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $st->execute([$admin['email']]);
    if ($id = $st->fetchColumn()) {
        $pdo->prepare("UPDATE users SET role = 'admin', password = ? WHERE id = ?")->execute([password_hash($admin['password'], PASSWORD_DEFAULT), $id]);
    } else {
        $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')")
            ->execute([$admin['name'], $admin['email'], password_hash($admin['password'], PASSWORD_DEFAULT)]);
    }

    // settings (only missing keys)
    $settings = [
        'store_name' => 'Mora Mori', 'tagline' => 'Thoughtfully made', 'currency' => 'Rs.',
        'store_email' => $admin['email'], 'admin_email' => $admin['email'], 'store_phone' => '', 'store_address' => 'Kathmandu, Nepal',
        'announcement' => 'Delivery across Nepal · Pay easily by QR', 'guest_checkout' => '1', 'show_unavailable_locations' => '1',
        'smtp_enabled' => '0', 'smtp_port' => '587', 'smtp_secure' => 'tls', 'mail_from_name' => 'Mora Mori',
        'payment_apps' => 'eSewa, Khalti or any mobile banking app',
        'schema_version' => '2', 'qr_enabled' => '1', 'cod_enabled' => '1',
    ];
    $ins = $pdo->prepare('INSERT IGNORE INTO settings (`key`, `value`) VALUES (?, ?)');
    foreach ($settings as $k => $v) $ins->execute([$k, $v]);

    // demo content only on a fresh database
    if ((int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() === 0) seed_demo($pdo);
    if ((int)$pdo->query('SELECT COUNT(*) FROM delivery_locations')->fetchColumn() === 0) {
        $loc = $pdo->prepare('INSERT INTO delivery_locations (name, fee, eta, is_active, sort) VALUES (?, ?, ?, ?, ?)');
        foreach ([
            ['Kathmandu Valley', 100, '1–2 days', 1], ['Pokhara', 150, '2–3 days', 1], ['Chitwan', 150, '2–4 days', 1],
            ['Butwal', 200, '3–5 days', 1], ['Biratnagar', 200, '3–5 days', 1], ['Dharan', 200, '3–5 days', 1],
            ['Nepalgunj', 250, '4–6 days', 0],
        ] as $i => $l) $loc->execute([$l[0], $l[1], $l[2], $l[3], $i]);
    }
}

function seed_demo(PDO $pdo): void
{
    $cats = ['Clothing' => 1, 'Bags' => 2, 'Accessories' => 3];
    $catIds = [];
    $c = $pdo->prepare('INSERT INTO categories (name, slug, sort) VALUES (?, ?, ?)');
    foreach ($cats as $name => $sort) {
        $c->execute([$name, strtolower($name), $sort]);
        $catIds[$name] = (int)$pdo->lastInsertId();
    }
    $products = [
        ['Linen Overshirt', 'Clothing', 4800, null, 'S,M,L,XL', 'A relaxed overshirt in washed linen. Wear it open over a tee or buttoned as a light jacket. Softens beautifully with every wash.', 1, 'shirt'],
        ['Relaxed Wool Trousers', 'Clothing', 5200, null, 'S,M,L', 'Wide, fluid trousers in a light wool blend with a clean pressed crease and a comfortable mid rise.', 1, 'trousers'],
        ['Cotton Knit Sweater', 'Clothing', 4200, 4900, 'S,M,L', 'A soft, mid-weight knit in combed cotton. Ribbed cuffs and hem, a slightly dropped shoulder.', 0, 'knit'],
        ['Everyday Tote', 'Bags', 3600, null, '', 'A structured canvas tote with leather handles and an inner zip pocket. Fits a 14" laptop.', 1, 'tote'],
        ['Mini Crossbody', 'Bags', 3900, null, '', 'A compact crossbody with an adjustable strap — just enough room for phone, cards and keys.', 1, 'crossbody'],
        ['Leather Card Holder', 'Accessories', 1800, null, '', 'Four card slots and a centre pocket, cut from vegetable-tanned leather that darkens with time.', 0, 'card'],
        ['Silk Square Scarf', 'Accessories', 2400, null, '', 'A 70 × 70 cm square in pure silk twill with hand-rolled edges.', 1, 'scarf'],
        ['Cashmere Beanie', 'Accessories', 2100, 2800, '', 'A fine-gauge beanie in soft cashmere blend. One size.', 0, 'beanie'],
    ];
    $palettes = [['#e9e4dc', '#cfc6b8'], ['#e4e1da', '#b9b2a6'], ['#ece6df', '#c9b8a6'], ['#e6e2db', '#a9a196'],
                 ['#efe9e1', '#d4c2ad'], ['#e2ddd5', '#9d958a'], ['#ebe7e1', '#c4b5a5'], ['#e8e3dd', '#b5aa9c']];
    $dir = ROOT_PATH . '/assets/img/seed';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $p = $pdo->prepare('INSERT INTO products (category_id, name, slug, description, price, compare_price, stock, sizes, is_featured, created_at) VALUES (?,?,?,?,?,?,?,?,?, NOW() - INTERVAL ? DAY)');
    $img = $pdo->prepare('INSERT INTO product_images (product_id, path, sort) VALUES (?, ?, ?)');
    foreach ($products as $i => [$name, $cat, $price, $compare, $sizes, $desc, $feat, $shape]) {
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
        $p->execute([$catIds[$cat], $name, $slug, $desc, $price, $compare, $i === 7 ? 3 : 15, $sizes ?: null, $feat, $i * 6]);
        $pid = (int)$pdo->lastInsertId();
        foreach ([0, 1] as $v) {
            $file = "$slug-" . ($v + 1) . '.svg';
            file_put_contents("$dir/$file", product_svg($shape, $palettes[$i], $v));
            $img->execute([$pid, "assets/img/seed/$file", $v]);
        }
    }
}

/** Minimal editorial placeholder art so the store looks finished before real photos are uploaded. */
function product_svg(string $shape, array $pal, int $variant): string
{
    [$bg, $fg] = $pal;
    if ($variant === 1) { $bg = $fg; $fg = '#f4f1ec'; }
    $shapes = [
        'shirt'     => '<path d="M300 250l70-40h60l70 40 60 120-60 30-30-60v300H330V340l-30 60-60-30z"/><path d="M370 210q30 50 60 0" fill="none" stroke="' . $bg . '" stroke-width="6"/>',
        'trousers'  => '<path d="M320 220h160l30 470h-90l-20-330-20 330h-90z"/>',
        'knit'      => '<path d="M290 260l80-40q30 40 60 0l80 40 50 250-50 10-30-150v300H320V370l-30 150-50-10z"/>',
        'tote'      => '<path d="M290 360h220l-20 320H310z"/><path d="M350 360q0-110 50-110t50 110" fill="none" stroke="' . $fg . '" stroke-width="10"/>',
        'crossbody' => '<rect x="320" y="420" width="160" height="130" rx="26"/><path d="M330 430L400 200 470 430" fill="none" stroke="' . $fg . '" stroke-width="5"/>',
        'card'      => '<rect x="320" y="360" width="160" height="110" rx="10"/><rect x="335" y="376" width="130" height="10" rx="4" fill="' . $bg . '" opacity=".5"/>',
        'scarf'     => '<rect x="290" y="300" width="220" height="220" transform="rotate(45 400 410)"/><rect x="330" y="340" width="140" height="140" transform="rotate(45 400 410)" fill="none" stroke="' . $bg . '" stroke-width="4" opacity=".6"/>',
        'beanie'    => '<path d="M300 480q0-170 100-170t100 170z"/><rect x="290" y="470" width="220" height="60" rx="8"/>',
    ];
    $art = $shapes[$shape] ?? '<circle cx="400" cy="450" r="120"/>';
    $transform = $variant === 1 ? ' transform="translate(400 450) scale(1.25) translate(-400 -450)"' : '';
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 1000" width="800" height="1000">'
        . '<rect width="800" height="1000" fill="' . $bg . '"/>'
        . '<ellipse cx="400" cy="760" rx="190" ry="18" fill="#000" opacity=".06"/>'
        . '<g fill="' . $fg . '"' . $transform . '>' . $art . '</g>'
        . '<text x="400" y="930" text-anchor="middle" font-family="Georgia, serif" font-size="18" letter-spacing="8" fill="#000" opacity=".25">MORA MORI</text>'
        . '</svg>';
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install Mora Mori</title>
<style>
  body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f5f3ef;font-family:Helvetica,Arial,sans-serif;color:#111}
  form{background:#fff;padding:48px;width:min(440px,92vw)}
  h1{font-family:Georgia,serif;font-weight:400;letter-spacing:.3em;text-align:center;margin:0 0 6px}
  p.sub{text-align:center;color:#888;font-size:13px;margin:0 0 30px}
  label{display:block;font-size:11px;letter-spacing:.14em;text-transform:uppercase;margin:0 0 6px}
  input{width:100%;height:46px;border:1px solid #e5e2dc;padding:0 14px;margin-bottom:16px;box-sizing:border-box;font:inherit}
  button{width:100%;height:50px;background:#111;color:#fff;border:0;letter-spacing:.18em;text-transform:uppercase;font-size:12px;cursor:pointer}
  .err{background:#fbf1f0;color:#a3322b;padding:12px;font-size:13px;margin-bottom:16px}
</style></head>
<body>
<form method="post">
  <h1>MORA MORI</h1>
  <p class="sub">Store setup · creates the database &amp; your admin account</p>
  <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <label>Your name</label><input name="name" required value="<?= htmlspecialchars($input['name'] ?? '') ?>">
  <label>Admin email</label><input type="email" name="email" required value="<?= htmlspecialchars($input['email'] ?? '') ?>">
  <label>Admin password (min 8 characters)</label><input type="password" name="password" minlength="8" required>
  <button type="submit">Install store</button>
</form>
</body></html>
