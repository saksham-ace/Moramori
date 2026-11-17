<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

if (isset($_GET['view'])) {
    $m = q_one('SELECT body FROM mail_log WHERE id = ?', [(int)$_GET['view']]);
    header('Content-Type: text/html; charset=utf-8');
    header("Content-Security-Policy: default-src 'none'; img-src * data:; style-src 'unsafe-inline'");
    echo $m['body'] ?? 'Not found';
    exit;
}
$rows = q_all('SELECT id, recipient, subject, status, error, created_at FROM mail_log ORDER BY id DESC LIMIT 200');
$open = (int)($_GET['id'] ?? ($rows[0]['id'] ?? 0));
$pageTitle = 'Email log';
include __DIR__ . '/_head.php';
?>
<?php if (setting('smtp_enabled') !== '1'): ?>
  <div class="flash flash-info" style="margin:0 0 16px">SMTP is off, so emails are only recorded here and not delivered. Turn it on in <a class="link" href="<?= url('admin/settings.php?tab=email') ?>">Settings → Email</a>.</div>
<?php endif; ?>
<div class="cols-even">
  <div class="card"><div class="table-wrap"><table>
    <thead><tr><th>When</th><th>To</th><th>Subject</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $m): ?>
      <tr onclick="location.href='?id=<?= $m['id'] ?>'" style="cursor:pointer;<?= $m['id'] === $open ? 'background:var(--soft)' : '' ?>">
        <td class="nowrap small"><?= fmt_date($m['created_at'], 'd M, g:i a') ?></td>
        <td class="small"><?= e($m['recipient']) ?></td>
        <td class="small"><?= e($m['subject']) ?></td>
        <td><span class="badge <?= ['sent' => 'b-ok', 'failed' => 'b-bad'][$m['status']] ?? 'b-warn' ?>" title="<?= e($m['error']) ?>"><?= e($m['status']) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div></div>
  <div class="card">
    <?php if ($open): ?><iframe class="mail-frame" sandbox src="?view=<?= $open ?>"></iframe><?php else: ?><div class="empty">No emails yet.</div><?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/_foot.php'; ?>
