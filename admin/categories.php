<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

if (is_post()) {
    csrf_check();
    $action = post('action');
    $cid = (int)post('id');
    if ($action === 'save') {
        $name = post('name');
        if ($name === '') { flash('error', 'Name is required.'); redirect('admin/categories.php'); }
        $image = null;
        if (!empty($_FILES['image']['name'])) {
            try { $image = store_upload($_FILES['image'], 'categories'); } catch (RuntimeException $e) { flash('error', $e->getMessage()); redirect('admin/categories.php'); }
        }
        if ($cid) {
            q('UPDATE categories SET name = ?, slug = ?, sort = ?' . ($image ? ', image = ?' : '') . ' WHERE id = ?',
                array_merge([$name, unique_slug('categories', $name, $cid), (int)post('sort')], $image ? [$image] : [], [$cid]));
        } else {
            q('INSERT INTO categories (name, slug, sort, image) VALUES (?, ?, ?, ?)', [$name, unique_slug('categories', $name), (int)post('sort'), $image]);
        }
        flash('success', 'Category saved.');
    } elseif ($action === 'delete') {
        q('UPDATE products SET category_id = NULL WHERE category_id = ?', [$cid]);
        q('DELETE FROM categories WHERE id = ?', [$cid]);
        flash('success', 'Category deleted. Its products are now uncategorised.');
    }
    redirect('admin/categories.php');
}

$cats = q_all('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS n FROM categories c ORDER BY sort, name');
$pageTitle = 'Categories';
include __DIR__ . '/_head.php';
?>
<div class="cols">
  <div class="card">
    <h2>All categories</h2>
    <p class="hint" style="margin-top:-8px;margin-bottom:12px">The first three (by sort order) appear as tiles on the home page; the first four appear in the top menu.</p>
    <table>
      <thead><tr><th></th><th>Name &amp; sort</th><th>Image</th><th>Products</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($cats as $c): ?>
        <tr>
          <td style="width:58px"><img class="thumb" src="<?= e(img_url($c['image'])) ?>" alt=""></td>
          <td>
            <form method="post" enctype="multipart/form-data" class="inline-form" id="cat-<?= $c['id'] ?>">
              <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $c['id'] ?>">
              <input class="input" style="width:180px" name="name" value="<?= e($c['name']) ?>">
              <input class="input" style="width:70px" type="number" name="sort" value="<?= (int)$c['sort'] ?>" title="Sort order">
            </form>
          </td>
          <td><input form="cat-<?= $c['id'] ?>" type="file" name="image" accept="image/*" style="width:180px"></td>
          <td><?= (int)$c['n'] ?></td>
          <td class="nowrap">
            <button form="cat-<?= $c['id'] ?>" class="btn btn-outline btn-sm">Save</button>
            <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $c['id'] ?>">
              <button class="btn-link" data-confirm="Delete category “<?= e($c['name']) ?>”?">Delete</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card">
    <h2>Add category</h2>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?><input type="hidden" name="action" value="save">
      <div class="field"><label>Name</label><input name="name" required></div>
      <div class="field"><label>Sort order</label><input type="number" name="sort" value="<?= count($cats) + 1 ?>"></div>
      <div class="field"><label>Tile image (optional)</label><input type="file" name="image" accept="image/*"><div class="hint">If empty, the first product photo is used.</div></div>
      <button class="btn">Add category</button>
    </form>
  </div>
</div>
<?php include __DIR__ . '/_foot.php'; ?>
