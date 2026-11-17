<?php
require __DIR__ . '/includes/bootstrap.php';

$catSlug = $_GET['category'] ?? '';
$search = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'new';

$where = ['p.is_active = 1'];
$params = [];
$category = null;
if ($catSlug !== '') {
    $category = q_one('SELECT * FROM categories WHERE slug = ?', [$catSlug]);
    if ($category) {
        $where[] = 'p.category_id = ?';
        $params[] = $category['id'];
    }
}
if ($search !== '') {
    $where[] = '(p.name LIKE ? OR p.description LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$orderBy = [
    'new'        => 'p.created_at DESC',
    'price_asc'  => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'featured'   => 'p.is_featured DESC, p.created_at DESC',
][$sort] ?? 'p.created_at DESC';

$products = with_images(q_all(
    'SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id
     WHERE ' . implode(' AND ', $where) . " ORDER BY $orderBy",
    $params
));

$pageTitle = $category['name'] ?? ($search ? 'Search' : 'Shop');
include __DIR__ . '/includes/header.php';

$qs = fn(array $over) => url('shop.php?' . http_build_query(array_filter(array_merge(['category' => $catSlug, 'q' => $search, 'sort' => $sort !== 'new' ? $sort : ''], $over), 'strlen')));
?>
<section class="page-hero container">
  <div class="eyebrow reveal"><?= $search ? 'Search results' : 'Collection' ?></div>
  <h1 class="reveal reveal-d1"><?= $search ? '“' . e($search) . '”' : e($category['name'] ?? 'Shop all') ?></h1>
  <p class="muted reveal reveal-d2"><?= count($products) ?> <?= count($products) === 1 ? 'piece' : 'pieces' ?></p>
</section>

<div class="container">
  <div class="toolbar">
    <div class="chips">
      <a class="chip <?= !$category ? 'active' : '' ?>" href="<?= $qs(['category' => '']) ?>">All</a>
      <?php foreach (categories() as $c): ?>
        <a class="chip <?= $category && $category['id'] == $c['id'] ? 'active' : '' ?>" href="<?= $qs(['category' => $c['slug']]) ?>"><?= e($c['name']) ?></a>
      <?php endforeach; ?>
    </div>
    <form method="get">
      <?php if ($catSlug): ?><input type="hidden" name="category" value="<?= e($catSlug) ?>"><?php endif; ?>
      <?php if ($search): ?><input type="hidden" name="q" value="<?= e($search) ?>"><?php endif; ?>
      <label class="sr-only" for="sort">Sort</label>
      <select class="sort-select" id="sort" name="sort" onchange="this.form.submit()">
        <option value="new" <?= $sort === 'new' ? 'selected' : '' ?>>Newest</option>
        <option value="featured" <?= $sort === 'featured' ? 'selected' : '' ?>>Featured</option>
        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: low to high</option>
        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: high to low</option>
      </select>
    </form>
  </div>

  <?php if ($products): ?>
    <div class="products" style="padding-bottom:80px">
      <?php foreach ($products as $p) include __DIR__ . '/includes/partials/product-card.php'; ?>
    </div>
  <?php else: ?>
    <div class="empty-state">
      <h2>Nothing here yet</h2>
      <p class="muted">Try another category or search term.</p>
      <a class="btn btn-outline" href="<?= url('shop.php') ?>" style="margin-top:20px">View all products</a>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
