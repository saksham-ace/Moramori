</main>

<footer class="site-footer">
  <div class="footer-news">
    <h3 class="serif">Join the Mora Mori circle</h3>
    <p>New collections, restocks and private offers — first.</p>
    <form class="news-form" data-newsletter>
      <input type="email" name="email" placeholder="Your email address" required>
      <button type="submit">Subscribe</button>
    </form>
  </div>
  <div class="footer-cols">
    <div>
      <h4>Shop</h4>
      <a href="<?= url('shop.php') ?>">All products</a>
      <?php foreach (categories() as $c): ?>
        <a href="<?= url('shop.php?category=' . urlencode($c['slug'])) ?>"><?= e($c['name']) ?></a>
      <?php endforeach; ?>
    </div>
    <div>
      <h4>Help</h4>
      <a href="<?= url('track.php') ?>">Track your order</a>
      <a href="<?= url('page.php?p=delivery') ?>">Delivery &amp; payment</a>
      <a href="<?= url('page.php?p=returns') ?>">Returns &amp; exchanges</a>
      <a href="<?= url('page.php?p=contact') ?>">Contact us</a>
    </div>
    <div>
      <h4>Account</h4>
      <?php if (current_user()): ?>
        <a href="<?= url('account/') ?>">My account</a>
        <a href="<?= url('account/orders.php') ?>">Order history</a>
        <a href="<?= url('account/security.php') ?>">Login activity</a>
      <?php else: ?>
        <a href="<?= url('account/login.php') ?>">Sign in</a>
        <a href="<?= url('account/register.php') ?>">Create account</a>
      <?php endif; ?>
    </div>
    <div>
      <h4>Contact</h4>
      <?php if (setting('store_phone')): ?><span><?= e(setting('store_phone')) ?></span><?php endif; ?>
      <?php if (setting('store_email')): ?><a href="mailto:<?= e(setting('store_email')) ?>"><?= e(setting('store_email')) ?></a><?php endif; ?>
      <?php if (setting('store_address')): ?><span><?= e(setting('store_address')) ?></span><?php endif; ?>
      <div class="socials">
        <?php if (setting('instagram')): ?><a href="<?= e(setting('instagram')) ?>" target="_blank" rel="noopener">Instagram</a><?php endif; ?>
        <?php if (setting('facebook')): ?><a href="<?= e(setting('facebook')) ?>" target="_blank" rel="noopener">Facebook</a><?php endif; ?>
        <?php if (setting('tiktok')): ?><a href="<?= e(setting('tiktok')) ?>" target="_blank" rel="noopener">TikTok</a><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="footer-base">
    <span class="logo-sm"><?= e(strtoupper(setting('store_name', 'Mora Mori'))) ?></span>
    <span>&copy; <?= date('Y') ?> <?= e(setting('store_name', 'Mora Mori')) ?>. All rights reserved.</span>
  </div>
</footer>

<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
