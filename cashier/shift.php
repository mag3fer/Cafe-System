<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$db    = getDB();
$shift = getActiveShift();

// Handle checkout — POST (main form) or GET (from shift-receipt popup)
if ($shift && (
    ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'checkout') ||
    ($_SERVER['REQUEST_METHOD'] === 'GET'  && isset($_GET['checkout']))
)) {
    // Calculate totals from orders attributed to this shift
    $totals = fetchOne("SELECT COUNT(*) as cnt, COALESCE(SUM(final_total),0) as total FROM orders WHERE shift_id=? AND status='closed'", [$shift['id']]);
    $db->prepare("UPDATE shifts SET check_out=NOW(), total_sales=?, total_orders=? WHERE id=?")
       ->execute([$totals['total'], $totals['cnt'], $shift['id']]);
    header('Location: ' . BASE_URL . '/logout.php');
    exit;
}

// Load current or last shift
$displayShift = $shift ?? fetchOne("SELECT s.*,u.name as user_name FROM shifts s JOIN users u ON u.id=s.user_id WHERE s.user_id=? ORDER BY s.check_in DESC LIMIT 1", [$_SESSION['user_id']]);

$shiftOrders = [];
if ($displayShift) {
    $shiftOrders = fetchAll("
        SELECT o.*, t.number as table_num
        FROM orders o
        JOIN cafe_tables t ON t.id=o.table_id
        WHERE o.shift_id=? AND o.status='closed'
        ORDER BY o.closed_at DESC
    ", [$displayShift['id']]);
}

$pageTitle = 'تفاصيل الشيفت';
require_once __DIR__ . '/../includes/cashier_header.php';
?>

<?php if (isset($error)): ?>
<div class="alert alert-danger"><i class="fa fa-circle-exclamation me-2"></i><?= e($error) ?></div>
<?php endif; ?>

<?php if ($displayShift): ?>

<div class="row g-4 mb-4">
  <!-- Shift Card -->
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header"><i class="fa fa-clock"></i> معلومات الشيفت</div>
      <div class="card-body">
        <table class="table table-sm mb-0">
          <tr><td class="text-muted">الموظف:</td><td class="fw-bold"><?= e($displayShift['user_name'] ?? $_SESSION['user_name']) ?></td></tr>
          <tr><td class="text-muted">تسجيل الحضور:</td><td><?= formatDateAr($displayShift['check_in']) ?></td></tr>
          <tr><td class="text-muted">تسجيل الانصراف:</td>
              <td><?= $displayShift['check_out'] ? formatDateAr($displayShift['check_out']) : '<span class="badge bg-success">نشط الآن</span>' ?></td></tr>
          <tr><td class="text-muted">المدة:</td>
              <td class="fw-bold"><?= shiftDuration($displayShift['check_in'], $displayShift['check_out']) ?></td></tr>
        </table>
      </div>
    </div>
  </div>

  <!-- Shift Stats -->
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header"><i class="fa fa-chart-bar"></i> إحصائيات الشيفت</div>
      <div class="card-body">
        <?php
        $totals = fetchOne("SELECT COUNT(*) as orders, COALESCE(SUM(final_total),0) as sales, COALESCE(SUM(discount),0) as discounts FROM orders WHERE shift_id=? AND status='closed'", [$displayShift['id']]);
        ?>
        <div class="row g-3 text-center">
          <div class="col-4">
            <div class="fw-bold" style="font-size:28px;color:#3b82f6"><?= $totals['orders'] ?></div>
            <small class="text-muted">طلبات</small>
          </div>
          <div class="col-4">
            <div class="fw-bold" style="font-size:22px;color:#22c55e"><?= money((float)$totals['sales']) ?></div>
            <small class="text-muted">إجمالي المبيعات</small>
          </div>
          <div class="col-4">
            <div class="fw-bold" style="font-size:22px;color:#ef4444"><?= money((float)$totals['discounts']) ?></div>
            <small class="text-muted">الخصومات</small>
          </div>
        </div>
        <?php
        $payBreak = fetchAll("SELECT payment_method, COUNT(*) as cnt, SUM(final_total) as total FROM orders WHERE shift_id=? AND status='closed' GROUP BY payment_method", [$displayShift['id']]);
        ?>
        <?php if ($payBreak): ?>
        <hr>
        <div class="mt-2">
          <small class="text-muted fw-bold d-block mb-2">توزيع طرق الدفع:</small>
          <?php foreach ($payBreak as $pb):
            $label = ['cash'=>'نقدي','card'=>'بطاقة','instapay'=>'انستاباي','other'=>'أخرى','split'=>'مقسم'][$pb['payment_method']] ?? ''; ?>
          <div class="d-flex justify-content-between mb-1">
            <span><?= $label ?> (<?= $pb['cnt'] ?> طلب)</span>
            <span class="fw-bold"><?= money((float)$pb['total']) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Checkout Button -->
<?php if ($shift):
    $openCount  = (int) fetchOne("SELECT COUNT(*) as cnt FROM orders WHERE shift_id=? AND status='open'", [$shift['id']])['cnt'];
    $rcptTotals = fetchOne("SELECT COUNT(*) as orders, COALESCE(SUM(final_total),0) as sales, COALESCE(SUM(discount),0) as discounts FROM orders WHERE shift_id=? AND status='closed'", [$shift['id']]);
    $rcptPay    = fetchAll("SELECT payment_method, COUNT(*) as cnt, SUM(final_total) as total FROM orders WHERE shift_id=? AND status='closed' GROUP BY payment_method", [$shift['id']]);
    $payLabels  = ['cash' => 'نقدي', 'card' => 'بطاقة', 'instapay' => 'انستاباي', 'split' => 'مقسم', 'other' => 'أخرى'];
?>
<div class="card border-danger mb-4">
  <div class="card-body text-center py-4">
    <h5 class="fw-bold mb-2"><i class="fa fa-print me-2 text-danger"></i>إنهاء الشيفت</h5>
    <?php if ($openCount > 0): ?>
    <div class="alert alert-warning d-inline-block mb-3 py-2 px-4" style="font-size:13px">
      <i class="fa fa-triangle-exclamation me-1"></i>
      يوجد <strong><?= $openCount ?></strong> <?= $openCount === 1 ? 'طاولة مفتوحة' : 'طاولات مفتوحة' ?> —
      ستبقى للكاشير القادم بعد تسليم الشيفت
    </div>
    <?php else: ?>
    <p class="text-muted mb-3">جميع الطاولات مغلقة، يمكنك تسجيل الانصراف بأمان</p>
    <?php endif; ?>
    <button type="button" class="btn btn-danger btn-lg px-5"
            data-bs-toggle="modal" data-bs-target="#receiptModal">
      <i class="fa fa-print me-2"></i>طباعة تقرير الشيفت وإنهاؤه
    </button>
  </div>
</div>

<!-- Receipt Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered" style="max-width:380px">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body pt-1 px-4" id="rcptBody"
           style="font-family:Arial,sans-serif;font-size:13px;direction:rtl">
        <div style="text-align:center;font-weight:bold;font-size:19px"><?= e(appName()) ?></div>
        <div style="text-align:center;font-size:11px;color:#666;margin-top:2px">تقرير إنهاء الشيفت</div>
        <div class="rcpt-sep"></div>
        <div class="rcpt-row"><span>الكاشير:</span><span class="fw-bold"><?= e($_SESSION['user_name'] ?? '') ?></span></div>
        <div class="rcpt-row"><span>بداية الشيفت:</span><span><?= date('d/m/Y h:i A', strtotime($shift['check_in'])) ?></span></div>
        <div class="rcpt-row"><span>نهاية الشيفت:</span><span><?= date('d/m/Y h:i A') ?></span></div>
        <div class="rcpt-sep"></div>
        <div class="rcpt-row fw-bold"><span>عدد الطلبات المكتملة:</span><span><?= (int)$rcptTotals['orders'] ?></span></div>
        <div class="rcpt-row fw-bold" style="font-size:15px"><span>إجمالي المبيعات:</span><span><?= number_format((float)$rcptTotals['sales'], 2) ?></span></div>
        <div class="rcpt-row"><span>الخصومات:</span><span><?= number_format((float)$rcptTotals['discounts'], 2) ?></span></div>
        <div class="rcpt-sep"></div>
        <div class="fw-bold mb-1">طرق الدفع:</div>
        <?php foreach ($rcptPay as $p):
              $lbl = $payLabels[$p['payment_method']] ?? $p['payment_method']; ?>
        <div class="rcpt-row"><span><?= e($lbl) ?> (<?= (int)$p['cnt'] ?> طلب):</span><span><?= number_format((float)$p['total'], 2) ?></span></div>
        <?php endforeach; ?>
        <?php if ($openCount > 0): ?>
        <div class="rcpt-sep"></div>
        <div class="rcpt-row" style="color:#b45309">
          <span>&#9888; طاولات للشيفت القادم:</span><span><?= $openCount ?></span>
        </div>
        <?php endif; ?>
        <div class="rcpt-sep"></div>
        <div style="text-align:center;margin-top:16px;font-size:11px">توقيع الكاشير: ________________</div>
        <div style="text-align:center;margin-top:6px;font-size:10px;color:#999">طُبع في: <?= date('d/m/Y h:i A') ?></div>
      </div>
      <div class="modal-footer justify-content-center gap-2 pt-2">
        <button onclick="printShiftReceipt()" class="btn btn-primary">
          <i class="fa fa-print me-1"></i>طباعة
        </button>
        <a href="<?= BASE_URL ?>/cashier/shift.php?checkout=1" class="btn btn-danger">
          <i class="fa fa-check me-1"></i>تأكيد إنهاء الشيفت
        </a>
      </div>
    </div>
  </div>
</div>

<style>
.rcpt-sep { border-top:1px dashed #ccc; margin:8px 0; }
.rcpt-row { display:flex; justify-content:space-between; margin:4px 0; }
</style>
<script>
function toggleItems(id) {
  var row = document.getElementById('items-' + id);
  var eye = document.getElementById('eye-' + id);
  var open = row.style.display === 'none';
  row.style.display = open ? 'table-row' : 'none';
  eye.className = open ? 'fa fa-eye-slash' : 'fa fa-eye';
}
function printShiftReceipt() {
  var body = document.getElementById('rcptBody').innerHTML;
  var w = window.open('', '_blank', 'width=380,height=580');
  w.document.write('<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:Arial,sans-serif;font-size:13px;width:310px;margin:0 auto;padding:14px;direction:rtl}.rcpt-sep{border-top:1px dashed #000;margin:8px 0}.rcpt-row{display:flex;justify-content:space-between;margin:4px 0}.fw-bold{font-weight:bold}@media print{@page{margin:4mm}}</style></head><body>'+body+'</body></html>');
  w.document.close();
  w.focus();
  setTimeout(function(){ w.print(); w.close(); }, 300);
}
</script>
<?php endif; ?>

<!-- Orders List -->
<div class="card">
  <div class="card-header d-flex justify-content-between">
    <span><i class="fa fa-list"></i> طلبات الشيفت (<?= count($shiftOrders) ?>)</span>
  </div>
  <div class="card-body p-0">
    <?php if (empty($shiftOrders)): ?>
    <div class="text-center text-muted py-5">لا توجد طلبات مكتملة في هذا الشيفت</div>
    <?php else: ?>
    <table class="table table-hover mb-0">
      <thead><tr><th>رقم الطلب</th><th>الطاولة</th><th>المجموع</th><th>الخصم</th><th>الصافي</th><th>الدفع</th><th>الوقت</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($shiftOrders as $o):
          $orderItems = fetchAll("SELECT * FROM order_items WHERE order_id=?", [$o['id']]);
        ?>
        <tr>
          <td class="fw-bold"><?= generateOrderNumber($o['id']) ?></td>
          <td>طاولة <?= $o['table_num'] ?></td>
          <td><?= money((float)$o['total']) ?></td>
          <td><?= $o['discount'] > 0 ? money((float)$o['discount']) : '—' ?></td>
          <td class="fw-bold text-success"><?= money((float)$o['final_total']) ?></td>
          <td>
            <?php if ($o['payment_method'] === 'split'): ?>
            <span class="badge text-white" style="background:#7c3aed">مقسم</span>
            <small class="d-block text-muted" style="font-size:10px;line-height:1.6">
              <?php
              if (($o['cash_amount']??0)>0)    echo 'ك '.money((float)$o['cash_amount']).'<br>';
              if (($o['card_amount']??0)>0)     echo 'ب '.money((float)$o['card_amount']).'<br>';
              if (($o['instapay_amount']??0)>0) echo 'ا '.money((float)$o['instapay_amount']);
              ?>
            </small>
            <?php elseif ($o['payment_method'] === 'instapay'): ?>
            <span class="badge text-white" style="background:#e91e8c">انستاباي</span>
            <?php else: ?>
            <span class="badge <?= $o['payment_method']==='cash' ? 'bg-success' : 'bg-primary' ?>">
              <?= ['cash'=>'نقدي','card'=>'بطاقة','other'=>'أخرى'][$o['payment_method']] ?? '' ?>
            </span>
            <?php endif; ?>
          </td>
          <td style="font-size:12px">
            <?php if (!empty($o['opened_at'])): ?>
            <span class="text-muted"><?= date('h:i A', strtotime($o['opened_at'])) ?></span>
            <span class="text-muted mx-1">→</span>
            <?php endif; ?>
            <span class="fw-bold"><?= date('h:i A', strtotime($o['closed_at'])) ?></span>
          </td>
          <td class="text-nowrap">
            <button onclick="toggleItems(<?= $o['id'] ?>)"
                    class="btn btn-xs btn-outline-info me-1" title="تفاصيل الطلب">
              <i class="fa fa-eye" id="eye-<?= $o['id'] ?>"></i>
            </button>
            <a href="<?= BASE_URL ?>/cashier/receipt.php?order=<?= $o['id'] ?>"
               class="btn btn-xs btn-outline-secondary" target="_blank" title="إعادة طباعة">
              <i class="fa fa-print"></i>
            </a>
          </td>
        </tr>
        <tr id="items-<?= $o['id'] ?>" style="display:none;background:#f0f9ff">
          <td colspan="8" class="p-0">
            <table class="table table-sm mb-0" style="font-size:12px">
              <thead style="background:#dbeafe">
                <tr>
                  <th class="ps-4">الصنف</th><th>الكمية</th><th>سعر الوحدة</th><th>الإجمالي</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($orderItems as $oi): ?>
                <tr>
                  <td class="ps-4"><?= e($oi['item_name']) ?></td>
                  <td>× <?= $oi['quantity'] ?></td>
                  <td><?= money((float)$oi['price']) ?></td>
                  <td class="fw-bold"><?= money((float)$oi['subtotal']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($orderItems)): ?>
                <tr><td colspan="4" class="text-center text-muted py-2">لا توجد أصناف</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr class="table-success fw-bold">
          <td colspan="4" class="text-end">الإجمالي:</td>
          <td><?= money(array_sum(array_column($shiftOrders, 'final_total'))) ?></td>
          <td colspan="3"></td>
        </tr>
      </tfoot>
    </table>
    <?php endif; ?>
  </div>
</div>

<?php else: ?>
<div class="text-center mt-5">
  <div style="font-size:80px">📋</div>
  <h4 class="mt-3">لا يوجد شيفت سابق</h4>
  <a href="<?= BASE_URL ?>/cashier/" class="btn btn-accent mt-3">تسجيل الحضور</a>
</div>
<?php endif; ?>


<?php require_once __DIR__ . '/../includes/cashier_footer.php'; ?>
