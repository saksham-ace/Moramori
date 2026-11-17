<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

if (is_post()) {
    csrf_check();
    $action = post('action');
    $lid = (int)post('id');
    if ($action === 'save') {
        $name = post('name');
        if ($name === '') { flash('error', 'Location name is required.'); redirect('admin/locations.php'); }
        $vals = [$name, max(0, (float)post('fee')), post('eta') ?: null, post('is_active') ? 1 : 0, post('cod_available') ? 1 : 0, (int)post('sort')];
        if ($lid) {
            q('UPDATE delivery_locations SET name=?, fee=?, eta=?, is_active=?, cod_available=?, sort=? WHERE id=?', array_merge($vals, [$lid]));
        } else {
            q('INSERT INTO delivery_locations (name, fee, eta, is_active, cod_available, sort) VALUES (?,?,?,?,?,?)', $vals);
        }
        flash('success', 'Location saved.');
    } elseif ($action === 'toggle_cod') {
        q('UPDATE delivery_locations SET cod_available = 1 - cod_available WHERE id = ?', [$lid]);
        flash('success', 'Cash on delivery updated.');
    } elseif ($action === 'all_cod') {
        q('UPDATE delivery_locations SET cod_available = ?', [post('value') === '1' ? 1 : 0]);
        flash('success', 'Cash on delivery updated for all locations.');
    } elseif ($action === 'toggle') {
        q('UPDATE delivery_locations SET is_active = 1 - is_active WHERE id = ?', [$lid]);
        flash('success', 'Delivery availability updated.');
    } elseif ($action === 'delete') {
        q('DELETE FROM delivery_locations WHERE id = ?', [$lid]);
        flash('success', 'Location deleted.');
    } elseif ($action === 'bulk') {
        $n = 0;
        foreach (preg_split('/\r?\n/', post('lines')) as $line) {
            $parts = array_map('trim', explode(',', $line));
            if (($parts[0] ?? '') === '') continue;
            $cod = strtolower($parts[3] ?? 'yes');
            q('INSERT INTO delivery_locations (name, fee, eta, is_active, cod_available) VALUES (?, ?, ?, 1, ?)', [$parts[0], (float)($parts[1] ?? 0), ($parts[2] ?? '') ?: null, in_array($cod, ['no', '0', 'n'], true) ? 0 : 1]);
            $n++;
        }
        flash('success', "$n locations added.");
    } elseif ($action === 'prefs') {
        save_setting('show_unavailable_locations', post('show_unavailable') ? '1' : '0');
        flash('success', 'Saved.');
    }
    redirect('admin/locations.php');
}

$locs = q_all('SELECT l.*, (SELECT COUNT(*) FROM orders o WHERE o.location_id = l.id) AS n FROM delivery_locations l ORDER BY sort, name');
$edit = isset($_GET['edit']) ? q_one('SELECT * FROM delivery_locations WHERE id = ?', [(int)$_GET['edit']]) : null;
$pageTitle = 'Delivery locations';
include __DIR__ . '/_head.php';
?>
<div class="cols">
  <div class="card">
    <div class="card-head"><h2>Locations (<?= count($locs) ?>)</h2>
      <div class="inline-form">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="all_cod"><input type="hidden" name="value" value="1"><button class="btn btn-outline btn-sm">COD on for all</button></form>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="all_cod"><input type="hidden" name="value" value="0"><button class="btn btn-outline btn-sm">COD off for all</button></form>
      </div>
    </div>
    <div style="margin:-6px 0 12px">
      <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="prefs">
        <label class="check small"><input type="checkbox" name="show_unavailable" value="1" <?= setting('show_unavailable_locations', '1') === '1' ? 'checked' : '' ?> onchange="this.form.submit()"> Show unavailable locations at checkout (greyed out)</label></form>
    </div>
    <?php if (!$locs): ?><div class="empty">No delivery locations yet. Add one to start accepting orders.</div><?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Location</th><th>Fee</th><th>Delivery time</th><th>Cash on delivery</th><th>Delivering <span class="muted" title="Optional — turn off to pause delivery to a location">(optional)</span></th><th>Orders</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($locs as $l): ?>
        <tr>
          <td><?= e($l['name']) ?></td>
          <td class="nowrap"><?= (float)$l['fee'] > 0 ? money($l['fee']) : '<span class="badge b-ok">Free</span>' ?></td>
          <td class="small muted"><?= e($l['eta'] ?: '—') ?></td>
          <td>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_cod"><input type="hidden" name="id" value="<?= $l['id'] ?>">
              <label class="toggle" title="<?= $l['cod_available'] ? 'COD available' : 'COD not available' ?>"><input type="checkbox" <?= $l['cod_available'] ? 'checked' : '' ?> onchange="this.form.submit()"><span></span></label></form>
          </td>
          <td>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $l['id'] ?>">
              <label class="toggle" title="<?= $l['is_active'] ? 'Delivering' : 'Not delivering' ?>"><input type="checkbox" <?= $l['is_active'] ? 'checked' : '' ?> onchange="this.form.submit()"><span></span></label></form>
          </td>
          <td><?= (int)$l['n'] ?></td>
          <td class="nowrap">
            <a class="btn btn-outline btn-sm" href="?edit=<?= $l['id'] ?>">Edit</a>
            <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $l['id'] ?>">
              <button class="btn-link" data-confirm="Delete <?= e($l['name']) ?>?">Delete</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>

  <div>
    <div class="card">
      <h2><?= $edit ? 'Edit location' : 'Add location' ?></h2>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
        <div class="field"><label>Location name</label><input name="name" value="<?= e($edit['name'] ?? '') ?>" placeholder="e.g. Kathmandu Valley" required></div>
        <div class="row">
          <div class="field"><label>Delivery fee</label><input type="number" step="0.01" min="0" name="fee" value="<?= e($edit['fee'] ?? '0') ?>"><div class="hint">0 = free delivery</div></div>
          <div class="field"><label>Sort</label><input type="number" name="sort" value="<?= e($edit['sort'] ?? '0') ?>"></div>
        </div>
        <div class="field"><label>Delivery time (shown to customer)</label><input name="eta" value="<?= e($edit['eta'] ?? '') ?>" placeholder="e.g. 1–2 days"></div>
        <p><label class="check"><input type="checkbox" name="cod_available" value="1" <?= ($edit['cod_available'] ?? 1) ? 'checked' : '' ?>> Cash on delivery available here</label></p>
        <p><label class="check"><input type="checkbox" name="is_active" value="1" <?= ($edit['is_active'] ?? 1) ? 'checked' : '' ?>> Delivering to this location</label></p>
        <button class="btn"><?= $edit ? 'Save changes' : 'Add location' ?></button>
        <?php if ($edit): ?><a class="btn btn-outline" href="<?= url('admin/locations.php') ?>">Cancel</a><?php endif; ?>
      </form>
    </div>
    <div class="card">
      <h2>Bulk add</h2>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="bulk">
        <div class="field"><textarea name="lines" rows="6" placeholder="One per line: Name, fee, delivery time, COD (yes/no)&#10;Pokhara, 150, 2–3 days, yes&#10;Jumla, 300, 6–8 days, no"></textarea></div>
        <button class="btn btn-outline">Add all</button>
      </form>
    </div>
  </div>
</div>
<?php include __DIR__ . '/_foot.php'; ?>
