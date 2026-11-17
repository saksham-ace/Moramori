<?php
require __DIR__ . '/includes/bootstrap.php';
$pages = [
    'delivery' => ['Delivery & payment', 'page_delivery', "## Where we deliver\nWe deliver to every location listed at checkout. Delivery fees depend on your location and are shown before you pay.\n\n## How to pay\nAt checkout, scan our QR code with your banking or wallet app, pay the exact total and upload a screenshot of the successful payment. We verify every payment by hand and email you as soon as it is confirmed.\n\n## Delivery time\nOrders are dispatched after payment verification. You can follow every step from the Track Order page."],
    'returns'  => ['Returns & exchanges', 'page_returns', "## Exchanges\nUnused items in their original packaging can be exchanged within 7 days of delivery.\n\n## How to request\nContact us with your order number and the item you would like to exchange. We will guide you through the next steps."],
    'contact'  => ['Contact us', 'page_contact', "## We are here to help\nWrite to us or call during business hours and we will get back to you as soon as possible."],
];
$key = $_GET['p'] ?? '';
if (!isset($pages[$key])) redirect('');
[$title, $settingKey, $default] = $pages[$key];
$content = setting($settingKey, $default);

$pageTitle = $title;
include __DIR__ . '/includes/header.php';
?>
<section class="page-hero container"><div class="eyebrow">Customer care</div><h1><?= e($title) ?></h1></section>
<div class="narrow prose" style="padding-bottom:100px">
  <?php foreach (preg_split('/\n{2,}/', trim($content)) as $block):
      if (str_starts_with($block, '## ')) echo '<h2>' . e(substr($block, 3)) . '</h2>';
      else echo '<p>' . nl2br(e($block)) . '</p>';
  endforeach; ?>
  <?php if ($key === 'contact'): ?>
    <p><?= e(setting('store_phone')) ?><br><a class="link" href="mailto:<?= e(setting('store_email')) ?>"><?= e(setting('store_email')) ?></a><br><?= e(setting('store_address')) ?></p>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
