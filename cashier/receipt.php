<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$orderId = (int)($_GET['order'] ?? 0);
if (!$orderId) { header('Location: ' . BASE_URL . '/cashier/tables.php'); exit; }

$order = fetchOne("
    SELECT o.*, t.number as table_num, t.name as table_name, u.name as cashier_name
    FROM orders o
    JOIN cafe_tables t ON t.id=o.table_id
    JOIN users u ON u.id=o.user_id
    WHERE o.id=?
", [$orderId]);

if (!$order) { header('Location: ' . BASE_URL . '/cashier/tables.php'); exit; }

$items = fetchAll("SELECT * FROM order_items WHERE order_id=? ORDER BY created_at", [$orderId]);

$payLabel = ['cash'=>'نقدي','card'=>'بطاقة','instapay'=>'انستاباي','other'=>'أخرى','split'=>'مقسم (نقدي + بطاقة)'][$order['payment_method']] ?? '';

$cafeName    = getSetting('cafe_name', APP_NAME);
$cafePhone   = getSetting('cafe_phone', '');
$cafeAddress = getSetting('cafe_address', '');
$rcpHeader   = getSetting('receipt_header', '');
$rcpFooter1  = getSetting('receipt_footer1', 'شكراً لزيارتكم ✨');
$rcpFooter2  = getSetting('receipt_footer2', 'نأمل أن تعودوا مرة أخرى');
$svcEnabled  = getSetting('service_enabled', '0') === '1';
$taxEnabled  = getSetting('tax_enabled', '0') === '1';
$svcLabel    = getSetting('service_label', 'رسوم الخدمة');
$taxLabel    = getSetting('tax_label', 'ضريبة القيمة المضافة');
$svcPct      = (float)getSetting('service_percent', '0');
$taxPct      = (float)getSetting('tax_percent', '0');

$taxAmount = isset($order['tax_amount']) ? (float)$order['tax_amount'] : 0;
$svcAmount = isset($order['service_amount']) ? (float)$order['service_amount'] : 0;
$cafeLogoRaw = getSetting('cafe_logo', '');
$cafeLogoUrl = $cafeLogoRaw ? $cafeLogoRaw . '?v=' . filemtime(rtrim($_SERVER['DOCUMENT_ROOT'], '/') . $cafeLogoRaw) : '';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>فاتورة — <?= generateOrderNumber($orderId) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<style>
body { background: #f0f2f5; font-family: 'Cairo', sans-serif; direction: rtl; }
.receipt-wrapper { max-width: 420px; margin: 30px auto; padding: 20px; }

@media print {
  * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }

  html, body {
    direction: ltr !important;
    background: white !important;
    margin: 0 !important;
    padding: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
  }

  .no-print { display: none !important; }
  .receipt-wrapper { display: none !important; }

  .receipt {
    direction: rtl !important;
    width: 80mm !important;
    max-width: 80mm !important;
    margin: 0 auto !important;
    padding: 3mm 2mm !important;
    box-shadow: none !important;
    border: none !important;
    border-radius: 0 !important;
    background: white !important;
    font-size: 12px !important;
  }

  @page {
    size: 80mm auto;
    margin: 0;
  }
}
</style>
</head>
<body>

<div class="receipt-wrapper no-print d-flex gap-2 mb-3 justify-content-center flex-wrap">
  <button onclick="window.print()" class="btn btn-accent btn-lg">
    <i class="fa fa-print me-2"></i>طباعة الفاتورة
  </button>
  <a href="<?= BASE_URL ?>/cashier/tables.php" class="btn btn-outline-secondary btn-lg">
    <i class="fa fa-arrow-right me-2"></i>العودة للطاولات
  </a>
  <a href="<?= BASE_URL ?>/cashier/shift.php" class="btn btn-outline-info btn-lg">
    <i class="fa fa-chart-bar me-2"></i>تفاصيل الشيفت
  </a>
</div>

<div class="receipt">
  <!-- Header -->
  <?php if ($cafeLogoUrl): ?>
  <div style="text-align:center;margin-bottom:10px;padding-bottom:10px;border-bottom:1px dashed #eee">
    <img src="<?= e($cafeLogoUrl) ?>" alt="<?= e($cafeName) ?>"
         style="max-width:200px;max-height:80px;object-fit:contain;display:block;margin:0 auto">
  </div>
  <div class="receipt-logo" style="font-size:16px">
    <?= e($cafeName) ?>
  </div>
  <?php else: ?>
  <div class="receipt-logo">
    <i class="fa fa-mug-hot"></i> <?= e($cafeName) ?>
  </div>
  <?php endif; ?>
  <?php if ($rcpHeader): ?>
  <div class="text-center mb-1" style="font-size:12px;color:#666"><?= e($rcpHeader) ?></div>
  <?php endif; ?>
  <?php if ($cafePhone): ?>
  <div class="text-center mb-1" style="font-size:12px;color:#555">
    <i class="fa fa-phone me-1"></i><?= e($cafePhone) ?>
  </div>
  <?php endif; ?>
  <?php if ($cafeAddress): ?>
  <div class="text-center mb-2" style="font-size:11px;color:#777">
    <i class="fa fa-location-dot me-1"></i><?= e($cafeAddress) ?>
  </div>
  <?php endif; ?>
  <div class="receipt-line"></div>

  <!-- Order Info -->
  <div class="receipt-row"><span>رقم الطلب:</span><span class="fw-bold"><?= generateOrderNumber($orderId) ?></span></div>
  <div class="receipt-row"><span>الطاولة:</span><span><?= e($order['table_name'] ?? 'طاولة ' . $order['table_num']) ?></span></div>
  <div class="receipt-row"><span>الكاشير:</span><span><?= e($order['cashier_name']) ?></span></div>
  <div class="receipt-row"><span>التاريخ:</span><span><?= date('Y/m/d', strtotime($order['closed_at'] ?? $order['opened_at'])) ?></span></div>
  <div class="receipt-row"><span>الوقت:</span><span><?= date('h:i A', strtotime($order['closed_at'] ?? $order['opened_at'])) ?></span></div>
  <div class="receipt-line"></div>

  <!-- Items -->
  <div style="font-size:13px;font-weight:700;margin-bottom:6px">الأصناف:</div>
  <?php foreach ($items as $item): ?>
  <div class="receipt-row" style="font-size:13px">
    <span><?= e($item['item_name']) ?> × <?= $item['quantity'] ?></span>
    <span><?= money((float)$item['subtotal']) ?></span>
  </div>
  <?php endforeach; ?>
  <div class="receipt-line"></div>

  <!-- Totals -->
  <div class="receipt-row"><span>المجموع:</span><span><?= money((float)$order['total']) ?></span></div>
  <?php if ($order['discount'] > 0): ?>
  <div class="receipt-row" style="color:#e74c3c">
    <span>الخصم <?= $order['discount_type']==='percent' ? '('.$order['discount'].'%)' : '' ?>:</span>
    <span>- <?= money((float)$order['discount']) ?></span>
  </div>
  <?php endif; ?>
  <?php if ($svcAmount > 0): ?>
  <div class="receipt-row" style="color:#7c3aed">
    <span><?= e($svcLabel) ?> (<?= $svcPct ?>%):</span>
    <span>+ <?= money($svcAmount) ?></span>
  </div>
  <?php endif; ?>
  <?php if ($taxAmount > 0): ?>
  <div class="receipt-row" style="color:#ea580c">
    <span><?= e($taxLabel) ?> (<?= $taxPct ?>%):</span>
    <span>+ <?= money($taxAmount) ?></span>
  </div>
  <?php endif; ?>
  <div class="receipt-line"></div>
  <div class="receipt-row receipt-total">
    <span>الإجمالي:</span>
    <span style="color:#27ae60"><?= money((float)$order['final_total']) ?></span>
  </div>
  <div class="receipt-row" style="font-size:13px">
    <span>طريقة الدفع:</span>
    <span class="fw-bold"><?= $payLabel ?></span>
  </div>
  <?php if ($order['payment_method'] === 'split'): ?>
  <?php if (($order['cash_amount'] ?? 0) > 0): ?>
  <div class="receipt-row" style="font-size:12px;color:#555;padding-right:8px">
    <span><i class="fa fa-money-bill me-1" style="color:#16a34a"></i>نقدي:</span>
    <span><?= money((float)$order['cash_amount']) ?></span>
  </div>
  <?php endif; ?>
  <?php if (($order['card_amount'] ?? 0) > 0): ?>
  <div class="receipt-row" style="font-size:12px;color:#555;padding-right:8px">
    <span><i class="fa fa-credit-card me-1" style="color:#0284c7"></i>بطاقة:</span>
    <span><?= money((float)$order['card_amount']) ?></span>
  </div>
  <?php endif; ?>
  <?php if (($order['instapay_amount'] ?? 0) > 0): ?>
  <div class="receipt-row" style="font-size:12px;color:#555;padding-right:8px">
    <span><i class="fa fa-mobile-screen-button me-1" style="color:#c2185b"></i>انستاباي:</span>
    <span><?= money((float)$order['instapay_amount']) ?></span>
  </div>
  <?php endif; ?>
  <?php endif; ?>

  <?php if ($order['notes']): ?>
  <div class="receipt-line"></div>
  <div style="font-size:12px"><strong>ملاحظات:</strong> <?= e($order['notes']) ?></div>
  <?php endif; ?>

  <div class="receipt-line"></div>
  <div class="receipt-foot">
    <?php if ($rcpFooter1): ?>
    <div><?= e($rcpFooter1) ?></div>
    <?php endif; ?>
    <?php if ($rcpFooter2): ?>
    <div><?= e($rcpFooter2) ?></div>
    <?php endif; ?>
    <div class="mt-2" style="font-size:10px"><?= e($cafeName) ?> — <?= date('Y') ?></div>
  </div>
</div>

</body>
</html>
