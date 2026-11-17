<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_admin();

// [key => [label, type, hint]]  types: text, textarea, image, check, password, select:opt1|opt2
$tabs = [
    'general' => ['General', [
        'store_name'    => ['Store name', 'text', ''],
        'tagline'       => ['Tagline', 'text', 'Shown in the browser tab on the home page'],
        'currency'      => ['Currency symbol', 'text', 'e.g. Rs., NPR, ₹, $'],
        'store_email'   => ['Store email', 'text', 'Shown in footer & emails'],
        'store_phone'   => ['Store phone', 'text', ''],
        'store_address' => ['Store address', 'text', ''],
        'admin_email'   => ['Order notifications go to', 'text', 'New order & payment emails are sent here'],
        'announcement'  => ['Announcement bar', 'text', 'Leave empty to hide'],
        'instagram'     => ['Instagram URL', 'text', ''],
        'facebook'      => ['Facebook URL', 'text', ''],
        'tiktok'        => ['TikTok URL', 'text', ''],
        'guest_checkout'=> ['Allow guest checkout (without an account)', 'check', ''],
        'meta_description' => ['SEO description', 'textarea', ''],
    ]],
    'payment' => ['Payment', [
        'qr_enabled'           => ['Accept QR payment (customer uploads a screenshot)', 'check', ''],
        'cod_enabled'          => ['Accept cash on delivery', 'check', 'Choose which locations allow it in Delivery locations'],
        'cod_note'             => ['Cash on delivery message at checkout', 'text', ''],
        'backorder_note'       => ['Message for out-of-stock products that keep selling', 'text', 'Shown on the product page'],
        'payment_qr'           => ['Payment QR code', 'image', 'Your static QR (eSewa / Khalti / Fonepay / bank). Shown at checkout.'],
        'payment_account_name' => ['Account name', 'text', 'Shown under the QR so customers can confirm'],
        'payment_apps'         => ['Apps customers can pay with', 'text', 'e.g. eSewa, Khalti or any mobile banking app'],
    ]],
    'homepage' => ['Home page', [
        'hero_image'   => ['Hero image', 'image', 'Large landscape photo, at least 2000px wide'],
        'hero_eyebrow' => ['Hero small text', 'text', ''],
        'hero_title'   => ['Hero headline', 'text', ''],
        'hero_button'  => ['Hero button label', 'text', ''],
        'intro_text'   => ['Intro statement', 'textarea', ''],
        'story_image'  => ['Story image', 'image', 'Portrait photo'],
        'story_title'  => ['Story headline', 'text', ''],
        'story_text'   => ['Story text', 'textarea', ''],
        'auth_image'   => ['Sign-in page image', 'image', ''],
    ]],
    'email' => ['Email', [
        'smtp_enabled'   => ['Send emails via SMTP', 'check', 'When off, emails are only recorded in the Email log'],
        'smtp_host'      => ['SMTP host', 'text', 'Gmail: smtp.gmail.com · Zoho: smtp.zoho.com'],
        'smtp_port'      => ['SMTP port', 'text', '587 for TLS, 465 for SSL'],
        'smtp_secure'    => ['Encryption', 'select:tls|ssl|none', ''],
        'smtp_user'      => ['SMTP username', 'text', 'Usually your full email address'],
        'smtp_pass'      => ['SMTP password', 'password', 'For Gmail use an App Password. Leave blank to keep the current one.'],
        'mail_from'      => ['From email', 'text', 'Must be allowed by your SMTP account'],
        'mail_from_name' => ['From name', 'text', ''],
    ]],
    'pages' => ['Pages', [
        'delivery_info' => ['Product page — delivery text', 'textarea', ''],
        'returns_info'  => ['Product page — returns text', 'textarea', ''],
        'page_delivery' => ['Delivery & payment page', 'textarea', 'Start a line with “## ” for a heading. Blank line = new paragraph.'],
        'page_returns'  => ['Returns page', 'textarea', ''],
        'page_contact'  => ['Contact page', 'textarea', ''],
    ]],
];
$tab = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'general';
$fields = $tabs[$tab][1];

if (is_post()) {
    csrf_check();
    if (post('action') === 'test_mail') {
        $ok = Mailer::send($me['email'], 'Test email from ' . setting('store_name', 'Mora Mori'),
            email_layout('It works', '<p>Your store can send emails. Order confirmations and updates will look like this.</p>'));
        $last = q_one('SELECT status, error FROM mail_log ORDER BY id DESC LIMIT 1');
        flash($ok ? 'success' : 'error', $last['status'] === 'sent' ? 'Test email sent to ' . $me['email'] . '.' : ($last['status'] === 'logged' ? 'SMTP is off — the test was recorded in the Email log only.' : 'Sending failed: ' . $last['error']));
        redirect('admin/settings.php?tab=email');
    }
    foreach ($fields as $key => [$label, $type]) {
        if ($type === 'check') {
            save_setting($key, post($key) ? '1' : '0');
        } elseif ($type === 'image') {
            if (post('remove_' . $key)) save_setting($key, '');
            if (!empty($_FILES[$key]['name'])) {
                try { save_setting($key, store_upload($_FILES[$key], 'site')); }
                catch (RuntimeException $e) { flash('error', $label . ': ' . $e->getMessage()); }
            }
        } elseif ($type === 'password') {
            if (post($key) !== '') save_setting($key, post($key));
        } else {
            save_setting($key, post($key));
        }
    }
    flash('success', 'Settings saved.');
    redirect('admin/settings.php?tab=' . $tab);
}

$defaults = ['currency' => 'Rs.', 'guest_checkout' => '1', 'qr_enabled' => '1', 'cod_enabled' => '1',
    'cod_note' => 'Please keep the exact amount ready. We may call you to confirm the order before dispatch.',
    'backorder_note' => 'Out of stock — order now and we will ship as soon as it is back.', 'smtp_port' => '587', 'smtp_secure' => 'tls', 'store_name' => 'Mora Mori'];
$pageTitle = 'Settings';
include __DIR__ . '/_head.php';
?>
<div class="tabs">
  <?php foreach ($tabs as $k => [$label]): ?><a class="<?= $k === $tab ? 'on' : '' ?>" href="?tab=<?= $k ?>"><?= e($label) ?></a><?php endforeach; ?>
</div>
<form method="post" enctype="multipart/form-data" class="card" style="max-width:760px">
  <?= csrf_field() ?>
  <?php foreach ($fields as $key => [$label, $type, $hint]):
      $val = setting($key, $defaults[$key] ?? ''); ?>
    <div class="field">
      <?php if ($type === 'check'): ?>
        <label class="check"><input type="checkbox" name="<?= $key ?>" value="1" <?= $val === '1' ? 'checked' : '' ?>> <?= e($label) ?></label>
      <?php else: ?>
        <label for="<?= $key ?>"><?= e($label) ?></label>
        <?php if ($type === 'textarea'): ?>
          <textarea id="<?= $key ?>" name="<?= $key ?>" rows="<?= str_starts_with($key, 'page_') ? 9 : 3 ?>"><?= e($val) ?></textarea>
        <?php elseif ($type === 'image'): ?>
          <?php if ($val): ?><img src="<?= e(img_url($val)) ?>" alt="" style="max-height:180px;border-radius:6px;margin-bottom:8px;border:1px solid var(--line)">
            <label class="check small" style="margin-bottom:8px"><input type="checkbox" name="remove_<?= $key ?>" value="1"> Remove</label><?php endif; ?>
          <input id="<?= $key ?>" type="file" name="<?= $key ?>" accept="image/*">
        <?php elseif ($type === 'password'): ?>
          <input id="<?= $key ?>" type="password" name="<?= $key ?>" value="" placeholder="<?= $val ? '•••••••• (saved)' : '' ?>" autocomplete="new-password">
        <?php elseif (str_starts_with($type, 'select:')): ?>
          <select id="<?= $key ?>" name="<?= $key ?>"><?php foreach (explode('|', substr($type, 7)) as $o): ?><option <?= $val === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?></select>
        <?php else: ?>
          <input id="<?= $key ?>" name="<?= $key ?>" value="<?= e($val) ?>">
        <?php endif; ?>
      <?php endif; ?>
      <?php if ($hint): ?><div class="hint"><?= e($hint) ?></div><?php endif; ?>
    </div>
  <?php endforeach; ?>
  <div class="inline-form">
    <button class="btn">Save settings</button>
    <?php if ($tab === 'email'): ?><button class="btn btn-outline" name="action" value="test_mail" formnovalidate>Send test email to <?= e($me['email']) ?></button><?php endif; ?>
  </div>
</form>
<?php include __DIR__ . '/_foot.php'; ?>
