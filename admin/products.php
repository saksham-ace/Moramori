<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

if (is_post()) {
    csrf_check();
    $pid = (int)post('id');
    if (post('action') === 'toggle') {
        q('UPDATE products SET is_active = 1 - is_active WHERE id = ?', [$pid]);
        flash('success', 'Visibility updated.');
    } elseif (post('action') === 'delete') {
        $used = (int)q_val('SELECT COUNT(*) FROM order_items WHERE product_id = ?', [$pid]);
        foreach (q_all('SELECT path FROM product_images WHERE product_id = ?', [$pid]) as $img) {
            if (str_starts_with($img['path'], 'uploads/')) @unlink(ROOT_PATH . '/' . $img['path']);
        }
        q('DELETE FROM product_images WHERE product_id = ?', [$pid]);
        q('UPDATE order_items SET product_id = NULL WHERE product_id = ?', [$pid]);
        q('DELETE FROM products WHERE id = ?', [$pid]);
        flash('success', 'Product deleted' . ($used ? ' (past orders keep their item details).' : '.'));
    }
    redirect('admin/products.php');
}

$search = trim($_GET['q'] ?? '');
$params = [];
$where = '1=1';
if ($search !== '') { $where = 'p.name LIKE ?'; $params[] = "%$search%"; }
$products = with_images(q_all("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE $where ORDER BY p.created_at DESC", $params));

$pageTitle = 'Products';
include __DIR__ . '/_head.php';
?>
<div class="filters">
  <a class="btn" style="color:#fff" href="<?= url('admin/product-edit.php') ?>">+ Add product</a>
  <form method="get" class="inline-form"><input class="input" style="width:220px" name="q" value="<?= e($search) ?>" placeholder="Search products"><button class="btn btn-sm">Search</button></form>
</div>
<div class="card">
  <?php if (!$products): ?><div class="empty">No products yet.</div><?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th></th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Sizes</th><th>Visible</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($products as $p): ?>
      <tr>
        <td style="width:58px"><img class="thumb" src="<?= e(img_url($p['image'])) ?>" alt=""></td>
        <td><a class="link" href="<?= url('admin/product-edit.php?id=' . $p['id']) ?>"><?= e($p['name']) ?></a><?= $p['is_featured'] ? ' <span class="badge">Featured</span>' : '' ?></td>
        <td><?= e($p['category_name'] ?: '—') ?></td>
        <td class="nowrap"><?= money($p['price']) ?></td>
        <td class="nowrap"><span class="badge <?= $p['stock'] <= 0 && !$p['allow_backorder'] ? 'b-bad' : ($p['stock'] <= 3 ? 'b-warn' : '') ?>"><?= (int)$p['stock'] ?></span><?= $p['allow_backorder'] ? ' <span class="muted small" title="Keeps selling when out of stock">∞ keeps selling</span>' : '' ?></td>
        <td class="small muted"><?= e($p['sizes'] ?: '—') ?></td>
        <td>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $p['id'] ?>">
            <label class="toggle"><input type="checkbox" <?= $p['is_active'] ? 'checked' : '' ?> onchange="this.form.submit()"><span></span></label></form>
        </td>
        <td class="nowrap">
          <a class="btn btn-outline btn-sm" href="<?= url('admin/product-edit.php?id=' . $p['id']) ?>">Edit</a>
          <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $p['id'] ?>">
            <button class="btn-link" data-confirm="Delete <?= e($p['name']) ?>? This cannot be undone.">Delete</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/_foot.php'; ?>
