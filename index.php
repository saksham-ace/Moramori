<?php
require __DIR__ . '/includes/bootstrap.php';

$featured = with_images(q_all(
    'SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.is_active = 1 ORDER BY p.is_featured DESC, p.created_at DESC LIMIT 8'
));
$cats = array_slice(categories(), 0, 3);
foreach ($cats as &$c) {
    if (!$c['image']) {
        $c['image'] = q_val('SELECT pi.path FROM product_images pi JOIN products p ON p.id = pi.product_id
                             WHERE p.category_id = ? AND p.is_active = 1 ORDER BY p.is_featured DESC, pi.sort LIMIT 1', [$c['id']]);
    }
}
unset($c);

$pageTitle = '';
$bodyClass = 'home';
include __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <div class="hero-media"><img src="<?= e(img_url(setting('hero_image', 'assets/img/hero.svg'))) ?>" alt=""></div>
  <div class="hero-content">
    <div class="eyebrow"><?= e(setting('hero_eyebrow', 'New collection')) ?></div>
    <h1><?= e(setting('hero_title', 'Quiet, made to last')) ?></h1>
    <div><a class="btn btn-light" href="<?= url('shop.php') ?>"><?= e(setting('hero_button', 'Discover')) ?></a></div>
  </div>
  <div class="scroll-cue"></div>
</section>

<section class="section">
  <div class="container">
    <p class="intro-text reveal"><?= e(setting('intro_text', 'Mora Mori is a study in restraint — honest materials, clean lines and pieces designed to stay with you, season after season.')) ?></p>
  </div>
</section>

<?php if ($cats): ?>
<section class="container" style="padding-bottom: clamp(64px, 9vw, 130px)">
  <div class="tiles">
    <?php foreach ($cats as $i => $c): ?>
      <a class="tile reveal reveal-d<?= $i ?>" href="<?= url('shop.php?category=' . urlencode($c['slug'])) ?>">
        <img src="<?= e(img_url($c['image'])) ?>" alt="<?= e($c['name']) ?>" loading="lazy">
        <div class="tile-label"><h3><?= e($c['name']) ?></h3><span>Shop now</span></div>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="container" style="padding-bottom: clamp(64px, 9vw, 130px)">
  <div class="section-head reveal">
    <div><div class="eyebrow">Selected for you</div><h2>The edit</h2></div>
    <a class="text-link" href="<?= url('shop.php') ?>">View all</a>
  </div>
  <div class="products">
    <?php foreach ($featured as $p) include __DIR__ . '/includes/partials/product-card.php'; ?>
  </div>
</section>

<section class="split">
  <div class="split-media"><img data-parallax src="<?= e(img_url(setting('story_image', 'assets/img/story.svg'))) ?>" alt="" loading="lazy"></div>
  <div class="split-text">
    <div class="eyebrow reveal">Our story</div>
    <h2 class="reveal reveal-d1"><?= e(setting('story_title', 'Designed slowly, worn always')) ?></h2>
    <p class="reveal reveal-d2"><?= e(setting('story_text', 'Every Mora Mori piece begins with a simple question: will you still reach for it in ten years? We work in small batches, with materials chosen for how they age, not how they photograph.')) ?></p>
    <div class="reveal reveal-d3"><a class="btn btn-outline" href="<?= url('shop.php') ?>">Explore the collection</a></div>
  </div>
</section>

<section class="values">
  <div class="reveal"><h4>Nationwide delivery</h4><p>To every location we serve</p></div>
  <div class="reveal reveal-d1"><h4>Easy payment</h4><p>Scan, pay &amp; upload</p></div>
  <div class="reveal reveal-d2"><h4>Order tracking</h4><p>Follow every step</p></div>
  <div class="reveal reveal-d3"><h4>Thoughtful care</h4><p>We are here to help</p></div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
