<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$p = $id ? q_one('SELECT * FROM products WHERE id = ?', [$id]) : null;
if ($id && !$p) { flash('error', 'Product not found.'); redirect('admin/products.php'); }
$p = $p ?: ['id' => 0, 'name' => '', 'category_id' => null, 'description' => '', 'price' => '', 'compare_price' => '',
            'stock' => 10, 'allow_backorder' => 0, 'sizes' => '', 'is_active' => 1, 'is_featured' => 0];
$errors = [];
$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

if (is_post()) {
    csrf_check();
    $data = [
        'name' => post('name'), 'category_id' => (int)post('category_id') ?: null, 'description' => post('description'),
        'price' => (float)post('price'), 'compare_price' => post('compare_price') !== '' ? (float)post('compare_price') : null,
        'stock' => (int)post('stock'), 'allow_backorder' => post('allow_backorder') ? 1 : 0,
        'sizes' => implode(',', array_unique(array_filter(array_map('trim', explode(',', post('sizes'))), 'strlen'))),
        'is_active' => post('is_active') ? 1 : 0, 'is_featured' => post('is_featured') ? 1 : 0,
    ];
    if ($data['name'] === '') $errors[] = 'Please enter a product name.';
    if ($data['price'] <= 0) $errors[] = 'Please enter a price greater than 0.';

    // upload new photos first so a bad file stops the save
    $uploads = [];
    if (!$errors && !empty($_FILES['images']['name'])) {
        foreach (files_list($_FILES['images']) as $i => $f) {
            if ($f['error'] === UPLOAD_ERR_NO_FILE) continue;
            try { $uploads[$i] = store_upload($f, 'products'); }
            catch (RuntimeException $e) { $errors[] = ($f['name'] ?: 'Photo') . ': ' . $e->getMessage(); }
        }
    }

    if (!$errors) {
        // inline "new category"
        $newCat = trim((string)post('new_category'));
        if ($newCat !== '') {
            $existing = q_val('SELECT id FROM categories WHERE name = ?', [$newCat]);
            if ($existing) {
                $data['category_id'] = (int)$existing;
            } else {
                q('INSERT INTO categories (name, slug, sort) VALUES (?, ?, ?)', [$newCat, unique_slug('categories', $newCat), (int)q_val('SELECT COALESCE(MAX(sort),0)+1 FROM categories')]);
                $data['category_id'] = (int)db()->lastInsertId();
            }
        }

        $cols = [$data['name'], $data['category_id'], $data['description'], $data['price'], $data['compare_price'],
                 $data['stock'], $data['allow_backorder'], $data['sizes'] ?: null, $data['is_active'], $data['is_featured']];
        if ($id) {
            q('UPDATE products SET name=?, category_id=?, description=?, price=?, compare_price=?, stock=?, allow_backorder=?, sizes=?, is_active=?, is_featured=?, slug=? WHERE id=?',
                array_merge($cols, [unique_slug('products', $data['name'], $id), $id]));
        } else {
            q('INSERT INTO products (name, category_id, description, price, compare_price, stock, allow_backorder, sizes, is_active, is_featured, slug) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                array_merge($cols, [unique_slug('products', $data['name'])]));
            $id = (int)db()->lastInsertId();
        }

        // removed photos
        foreach ((array)($_POST['removed_images'] ?? []) as $rid) {
            $img = q_one('SELECT * FROM product_images WHERE id = ? AND product_id = ?', [(int)$rid, $id]);
            if (!$img) continue;
            if (str_starts_with($img['path'], 'uploads/')) @unlink(ROOT_PATH . '/' . $img['path']);
            q('DELETE FROM product_images WHERE id = ?', [$img['id']]);
        }

        // photo order: tokens "e:<image id>" (existing) or "n:<upload index>" (new)
        $order = (array)($_POST['image_order'] ?? []);
        $placed = [];
        $sort = 0;
        foreach ($order as $tok) {
            [$kind, $ref] = array_pad(explode(':', (string)$tok, 2), 2, '');
            if ($kind === 'e') {
                q('UPDATE product_images SET sort = ? WHERE id = ? AND product_id = ?', [++$sort, (int)$ref, $id]);
            } elseif ($kind === 'n' && isset($uploads[(int)$ref])) {
                q('INSERT INTO product_images (product_id, path, sort) VALUES (?, ?, ?)', [$id, $uploads[(int)$ref], ++$sort]);
                $placed[(int)$ref] = true;
            }
        }
        // anything not in the order list (e.g. browser without JavaScript) goes at the end
        $sort = max($sort, (int)q_val('SELECT COALESCE(MAX(sort), 0) FROM product_images WHERE product_id = ?', [$id]));
        foreach ($uploads as $i => $path) {
            if (empty($placed[$i])) q('INSERT INTO product_images (product_id, path, sort) VALUES (?, ?, ?)', [$id, $path, ++$sort]);
        }

        flash('success', '“' . $data['name'] . '” saved.');
        $next = post('after') === 'new' ? url('admin/product-edit.php') : url('admin/product-edit.php?id=' . $id);
        if ($isAjax) json_out(['ok' => true, 'redirect' => $next]);
        header('Location: ' . $next);
        exit;
    }
    foreach ($uploads as $path) @unlink(ROOT_PATH . '/' . $path);
    if ($isAjax) json_out(['ok' => false, 'errors' => $errors], 422);
    $p = array_merge($p, $data);
}

$images = $id ? product_images($id) : [];
$sizes = product_sizes($p);
$pageTitle = $id ? 'Edit product' : 'Add product';
include __DIR__ . '/_head.php';
?>
<div class="inline-form" style="margin-bottom:18px">
  <a class="btn btn-outline btn-sm" href="<?= url('admin/products.php') ?>">← Products</a>
  <?php if ($id && $p['is_active']): ?><a class="btn btn-outline btn-sm" target="_blank" href="<?= url('product.php?slug=' . urlencode(q_val('SELECT slug FROM products WHERE id = ?', [$id]))) ?>">View in store ↗</a><?php endif; ?>
</div>
<div class="flash flash-error" id="formErrors" style="margin:0 0 16px;<?= $errors ? '' : 'display:none' ?>"><?= e(implode(' ', $errors)) ?></div>

<form method="post" enctype="multipart/form-data" class="cols" id="productForm" novalidate>
  <?= csrf_field() ?>
  <div>
    <div class="card">
      <div class="card-head"><h2>Photos</h2><span class="muted small" id="photoCount"></span></div>
      <div class="photo-grid" id="photoGrid">
        <?php foreach ($images as $img): ?>
          <div class="ph" data-token="e:<?= $img['id'] ?>">
            <img src="<?= e(img_url($img['path'])) ?>" alt="" draggable="false">
            <span class="ph-tag"></span>
            <button type="button" class="ph-del" aria-label="Remove photo">&times;</button>
          </div>
        <?php endforeach; ?>
        <label class="ph-add" id="photoAdd">
          <input type="file" name="images[]" accept="image/*" multiple id="photoInput">
          <span class="ph-add-icon">+</span>
          <span>Add photos</span>
        </label>
      </div>
      <p class="hint" style="margin-top:12px">Drag photos onto this box or click <b>Add photos</b>. <b>Drag a photo to move it</b>: the first is the cover, the second shows when shoppers hover. Large phone photos are resized automatically.</p>
    </div>

    <div class="card">
      <h2>Details</h2>
      <div class="field"><label for="name">Product name</label><input id="name" name="name" value="<?= e($p['name']) ?>" placeholder="e.g. Linen Overshirt" required autofocus></div>
      <div class="field"><label for="description">Description</label><textarea id="description" name="description" rows="5" placeholder="Material, fit, care, what makes it special…"><?= e($p['description']) ?></textarea></div>
      <div class="row">
        <div class="field"><label for="price">Price (<?= e(setting('currency', 'Rs.')) ?>)</label><input id="price" type="number" step="0.01" min="0" name="price" value="<?= e($p['price']) ?>" required inputmode="decimal"></div>
        <div class="field"><label for="compare_price">Original price <span class="muted">(optional)</span></label><input id="compare_price" type="number" step="0.01" min="0" name="compare_price" value="<?= e($p['compare_price']) ?>" inputmode="decimal"><div class="hint">Fill this to show it as a sale</div></div>
      </div>
    </div>

    <div class="card">
      <h2>Sizes <span class="muted" style="text-transform:none;letter-spacing:0">(optional)</span></h2>
      <input type="hidden" name="sizes" id="sizesValue" value="<?= e(implode(',', $sizes)) ?>">
      <div class="chips-input" id="sizeChips">
        <input type="text" id="sizeEntry" placeholder="Type a size and press Enter">
      </div>
      <div class="presets">
        <span class="muted small">Quick add:</span>
        <button type="button" class="chip-btn" data-sizes="XS,S,M,L,XL">XS–XL</button>
        <button type="button" class="chip-btn" data-sizes="S,M,L,XL">S–XL</button>
        <button type="button" class="chip-btn" data-sizes="28,30,32,34,36">28–36</button>
        <button type="button" class="chip-btn" data-sizes="36,37,38,39,40,41">36–41</button>
        <button type="button" class="chip-btn" data-sizes="Free size">Free size</button>
        <button type="button" class="chip-btn" data-clear>Clear</button>
      </div>
      <div class="hint">Leave empty if the product has no sizes — customers can add it to the bag straight away.</div>
    </div>

    <div class="card">
      <h2>Stock</h2>
      <div class="row" style="align-items:end">
        <div class="field"><label for="stock">Quantity in stock</label><input id="stock" type="number" name="stock" value="<?= e($p['stock']) ?>" inputmode="numeric"></div>
        <div class="field">
          <label class="switch-row">
            <span class="toggle"><input type="checkbox" name="allow_backorder" value="1" <?= $p['allow_backorder'] ? 'checked' : '' ?>><span></span></span>
            <span><b>Keep selling when out of stock</b><br><span class="muted small">Customers can still order (shown as pre-order)</span></span>
          </label>
        </div>
      </div>
    </div>
  </div>

  <div class="side-col">
    <div class="card">
      <h2>Organise</h2>
      <div class="field"><label for="category_id">Category</label>
        <select id="category_id" name="category_id">
          <option value="">— None —</option>
          <?php foreach (categories() as $c): ?><option value="<?= $c['id'] ?>" <?= $p['category_id'] == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
          <option value="__new">+ New category…</option>
        </select>
        <input class="input" style="margin-top:8px;display:none" name="new_category" id="newCategory" placeholder="New category name">
      </div>
      <label class="switch-row"><span class="toggle"><input type="checkbox" name="is_active" value="1" <?= $p['is_active'] ? 'checked' : '' ?>><span></span></span><span>Visible in store</span></label>
      <label class="switch-row"><span class="toggle"><input type="checkbox" name="is_featured" value="1" <?= $p['is_featured'] ? 'checked' : '' ?>><span></span></span><span>Featured on home page</span></label>
    </div>
    <div class="card save-card">
      <button class="btn" style="width:100%" name="after" value="stay" type="submit"><span class="btn-label">Save product</span></button>
      <?php if (!$id): ?><button class="btn btn-outline" style="width:100%;margin-top:8px" name="after" value="new" type="submit">Save &amp; add another</button><?php endif; ?>
      <div class="upload-bar" id="uploadBar"><span></span></div>
    </div>
  </div>
</form>
<script src="<?= asset('js/product-editor.js') ?>"></script>
<?php include __DIR__ . '/_foot.php'; ?>
