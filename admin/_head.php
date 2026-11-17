<?php
/** @var string $pageTitle */
$admin = require_admin();
$cur = basename($_SERVER['SCRIPT_NAME']);
$pendingPayments = (int)q_val("SELECT COUNT(*) FROM orders WHERE payment_status = 'submitted' AND status <> 'cancelled'");
$nav = [
    'index.php'      => ['Dashboard', []],
    'orders.php'     => ['Orders', ['order.php']],
    'products.php'   => ['Products', ['product-edit.php']],
    'categories.php' => ['Categories', []],
    'locations.php'  => ['Delivery locations', []],
    'customers.php'  => ['Customers', ['customer.php']],
    'logins.php'     => ['Login history', []],
    'mail-log.php'   => ['Email log', []],
    'settings.php'   => ['Settings', []],
];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($pageTitle ?? 'Admin') . ' — ' . setting('store_name', 'Mora Mori') . ' Admin') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
<link rel="icon" href="<?= url('assets/img/favicon.svg') ?>" type="image/svg+xml">
</head>
<body>
<aside class="side" id="side">
  <a class="side-logo" href="<?= url('admin/') ?>"><?= e(strtoupper(setting('store_name', 'Mora Mori'))) ?><small>Admin</small></a>
  <nav>
    <?php foreach ($nav as $file => [$label, $also]): ?>
      <a href="<?= url('admin/' . $file) ?>" class="<?= $cur === $file || in_array($cur, $also, true) ? 'active' : '' ?>">
        <?= e($label) ?>
        <?php if ($file === 'orders.php' && $pendingPayments): ?><span class="pill"><?= $pendingPayments ?></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="side-foot">
    <a href="<?= url('') ?>" target="_blank">View store ↗</a>
    <a href="<?= url('account/logout.php') ?>">Sign out</a>
  </div>
</aside>
<div class="main">
  <header class="topbar">
    <button class="menu-btn" onclick="document.getElementById('side').classList.toggle('open')" aria-label="Menu">☰</button>
    <h1><?= e($pageTitle ?? 'Admin') ?></h1>
    <span class="who"><?= e($admin['name']) ?></span>
  </header>
  <?php foreach (flashes() as $f): ?><div class="flash flash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div><?php endforeach; ?>
  <div class="content">
