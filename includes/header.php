<?php
/** @var string $pageTitle @var string $bodyClass */
$pageTitle = $pageTitle ?? '';
$bodyClass = $bodyClass ?? '';
$storeName = setting('store_name', 'Mora Mori');
$user = current_user();
$announcement = setting('announcement', '');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ? $pageTitle . ' — ' . $storeName : $storeName . ' — ' . setting('tagline', 'Thoughtfully made')) ?></title>
<meta name="description" content="<?= e(setting('meta_description', $storeName . ' official online store.')) ?>">
<meta name="csrf" content="<?= csrf_token() ?>">
<meta name="base" content="<?= e(BASE_URL) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/style.css') ?>">
<link rel="icon" href="<?= url('assets/img/favicon.svg') ?>" type="image/svg+xml">
</head>
<body class="<?= e($bodyClass) ?>">
<div class="page-fade"></div>

<?php if ($announcement): ?>
<div class="announce"><div class="announce-track"><span><?= e($announcement) ?></span></div></div>
<?php endif; ?>

<header class="site-header" id="siteHeader">
  <div class="header-inner">
    <div class="header-left">
      <button class="icon-btn burger" id="menuOpen" aria-label="Menu"><span></span><span></span></button>
      <nav class="main-nav">
        <a href="<?= url('shop.php') ?>">Shop all</a>
        <?php foreach (array_slice(categories(), 0, 4) as $c): ?>
          <a href="<?= url('shop.php?category=' . urlencode($c['slug'])) ?>"><?= e($c['name']) ?></a>
        <?php endforeach; ?>
      </nav>
    </div>
    <a class="logo" href="<?= url('') ?>"><?= e(strtoupper($storeName)) ?></a>
    <div class="header-right">
      <button class="icon-btn" id="searchOpen" aria-label="Search">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
      </button>
      <a class="icon-btn hide-sm" href="<?= url('track.php') ?>" aria-label="Track order" title="Track order">
        <svg viewBox="0 0 24 24"><path d="M3 7h11v9H3zM14 10h4l3 3v3h-7"/><circle cx="7" cy="17.5" r="1.6"/><circle cx="17" cy="17.5" r="1.6"/></svg>
      </a>
      <a class="icon-btn" href="<?= url($user ? 'account/' : 'account/login.php') ?>" aria-label="Account" title="<?= $user ? e($user['name']) : 'Sign in' ?>">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/></svg>
      </a>
      <button class="icon-btn bag-btn" id="cartOpen" aria-label="Bag">
        <svg viewBox="0 0 24 24"><path d="M5 8h14l-1 13H6z"/><path d="M9 8V6a3 3 0 016 0v2"/></svg>
        <span class="bag-count" data-cart-count><?= cart_count() ?: '' ?></span>
      </button>
    </div>
  </div>
</header>

<!-- mobile menu -->
<aside class="drawer drawer-left" id="menuDrawer" aria-hidden="true">
  <div class="drawer-head"><span class="drawer-title">Menu</span><button class="icon-btn" data-close aria-label="Close">&times;</button></div>
  <nav class="drawer-nav">
    <a href="<?= url('shop.php') ?>">Shop all</a>
    <?php foreach (categories() as $c): ?>
      <a href="<?= url('shop.php?category=' . urlencode($c['slug'])) ?>"><?= e($c['name']) ?></a>
    <?php endforeach; ?>
    <a href="<?= url('track.php') ?>">Track order</a>
    <?php if ($user): ?>
      <a href="<?= url('account/') ?>">My account</a>
      <?php if ($user['role'] === 'admin'): ?><a href="<?= url('admin/') ?>">Admin</a><?php endif; ?>
      <a href="<?= url('account/logout.php') ?>">Sign out</a>
    <?php else: ?>
      <a href="<?= url('account/login.php') ?>">Sign in</a>
      <a href="<?= url('account/register.php') ?>">Create account</a>
    <?php endif; ?>
  </nav>
</aside>

<!-- cart drawer -->
<aside class="drawer drawer-right" id="cartDrawer" aria-hidden="true">
  <div class="drawer-head"><span class="drawer-title">Your bag</span><button class="icon-btn" data-close aria-label="Close">&times;</button></div>
  <div class="drawer-body" id="cartDrawerBody"><div class="loader"></div></div>
</aside>

<!-- search -->
<div class="search-overlay" id="searchOverlay" aria-hidden="true">
  <form action="<?= url('shop.php') ?>" method="get" class="search-form">
    <input type="search" name="q" placeholder="Search the collection" autocomplete="off">
    <button type="button" class="icon-btn" data-close aria-label="Close">&times;</button>
  </form>
</div>
<div class="scrim" id="scrim"></div>

<?php $fl = flashes(); if ($fl): ?>
<div class="toasts"><?php foreach ($fl as $f): ?><div class="toast toast-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<main class="site-main">
