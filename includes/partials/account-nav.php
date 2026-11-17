<?php $cur = basename($_SERVER['SCRIPT_NAME']); ?>
<nav class="acc-nav">
  <a href="<?= url('account/') ?>" class="<?= $cur === 'index.php' ? 'active' : '' ?>">Overview</a>
  <a href="<?= url('account/orders.php') ?>" class="<?= in_array($cur, ['orders.php', 'order.php']) ? 'active' : '' ?>">Order history</a>
  <a href="<?= url('account/profile.php') ?>" class="<?= $cur === 'profile.php' ? 'active' : '' ?>">Profile</a>
  <a href="<?= url('account/security.php') ?>" class="<?= $cur === 'security.php' ? 'active' : '' ?>">Login history</a>
  <?php if (is_admin()): ?><a href="<?= url('admin/') ?>">Admin panel</a><?php endif; ?>
  <a href="<?= url('account/logout.php') ?>">Sign out</a>
</nav>
