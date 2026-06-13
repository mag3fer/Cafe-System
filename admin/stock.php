<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

runMigrations();
$inventory = fetchAll('SELECT * FROM inventory WHERE is_active=1 ORDER BY name');

// فلتر التاريخ لحركات الصرف
$dispenseDate = $_GET['dispense_date'] ?? date('Y-m-d');

// طلبات التوريد غير المقروءة (للكارت التنبيه)
$stockRequests = fetchAll("
    SELECT r.*, i.name as item_name, i.unit, u.name as cashier_name
    FROM stock_requests r
    JOIN inventory i ON i.id = r.inventory_id
    JOIN users u ON u.id = r.user_id
    WHERE r.is_read = 0
    ORDER BY r.created_at DESC
", []);

// سجل كل طلبات التوريد (للأرشيف)
$reqLogDate  = $_GET['req_date']  ?? '';
$reqLogMonth = $_GET['req_month'] ?? '';
$reqLogParams = [];
$reqLogWhere  = '1=1';
if ($reqLogDate) {
    $reqLogWhere  = 'DATE(r.created_at) = ?';
    $reqLogParams = [$reqLogDate];
} elseif ($reqLogMonth) {
    $reqLogWhere  = "DATE_FORMAT(r.created_at,'%Y-%m') = ?";
    $reqLogParams = [$reqLogMonth];
}
$allRequests = fetchAll("
    SELECT r.*, i.name as item_name, i.unit, u.name as cashier_name
    FROM stock_requests r
    JOIN inventory i ON i.id = r.inventory_id
    JOIN users u ON u.id = r.user_id
    WHERE $reqLogWhere
    ORDER BY r.created_at DESC
    LIMIT 200
", $reqLogParams);

// قائمة التسوق المجمّعة (لطباعة)
$shoppingList = fetchAll("
    SELECT i.name as item_name, i.unit,
           SUM(r.quantity_needed) as total_qty,
           COUNT(*) as req_count,
           GROUP_CONCAT(DISTINCT u.name ORDER BY u.name SEPARATOR ', ') as cashiers
    FROM stock_requests r
    JOIN inventory i ON i.id = r.inventory_id
    JOIN users u ON u.id = r.user_id
    WHERE $reqLogWhere
    GROUP BY r.inventory_id, i.name, i.unit
    ORDER BY total_qty DESC
", $reqLogParams);

// تسمية الفترة الزمنية للطباعة
if ($reqLogDate) {
    $periodLabel = date('Y/m/d', strtotime($reqLogDate));
} elseif ($reqLogMonth) {
    $months = ['01'=>'يناير','02'=>'فبراير','03'=>'مارس','04'=>'أبريل','05'=>'مايو','06'=>'يونيو',
               '07'=>'يوليو','08'=>'أغسطس','09'=>'سبتمبر','10'=>'أكتوبر','11'=>'نوفمبر','12'=>'ديسمبر'];
    list($yr, $mn) = explode('-', $reqLogMonth);
    $periodLabel = ($months[$mn] ?? $mn) . ' ' . $yr;
} else {
    $periodLabel = 'كل الفترات';
}

// حركات الصرف في التاريخ المحدد
$todayDispense = fetchAll("
    SELECT t.quantity, t.balance_after, t.notes, t.created_at,
           i.name as item_name, i.unit,
           u.name as cashier_name
    FROM inventory_transactions t
    JOIN inventory i ON i.id = t.inventory_id
    JOIN users u ON u.id = t.user_id
    WHERE t.type = 'out' AND DATE(t.created_at) = ?
    ORDER BY t.created_at DESC
", [$dispenseDate]);

// إجمالي الصرف في التاريخ المحدد لكل كاشير
$cashierTotals = fetchAll("
    SELECT u.name as cashier_name, COUNT(*) as ops,
           GROUP_CONCAT(DISTINCT i.name ORDER BY i.name SEPARATOR ', ') as items
    FROM inventory_transactions t
    JOIN users u ON u.id = t.user_id
    JOIN inventory i ON i.id = t.inventory_id
    WHERE t.type = 'out' AND DATE(t.created_at) = ?
    GROUP BY t.user_id, u.name
    ORDER BY ops DESC
", [$dispenseDate]);

$pageTitle  = 'إدارة المخزون';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<?php if (!empty($stockRequests)): ?>
<div class="card mb-4" style="border:2px solid #f59e0b">
  <div class="card-header d-flex justify-content-between align-items-center"
       style="background:linear-gradient(135deg,#fffbeb,#fef3c7)">
    <span style="color:#78350f;font-weight:700">
      <i class="fa fa-bell me-2" style="color:#f59e0b"></i>
      طلبات توريد من الكاشيرز
      <span class="badge ms-1" style="background:#f59e0b;color:#fff"><?= count($stockRequests) ?></span>
    </span>
    <button class="btn btn-sm btn-outline-secondary" onclick="markAllRead()">
      <i class="fa fa-check-double me-1"></i> تعليم الكل كمقروء
    </button>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0 align-middle">
      <thead style="background:#fffbeb">
        <tr>
          <th>الكاشير</th>
          <th>الصنف</th>
          <th>الكمية المطلوبة</th>
          <th>ملاحظات</th>
          <th>الوقت</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($stockRequests as $req): ?>
        <tr id="req-<?= $req['id'] ?>">
          <td>
            <i class="fa fa-user me-1 text-warning"></i>
            <strong><?= e($req['cashier_name']) ?></strong>
          </td>
          <td class="fw-bold text-danger"><?= e($req['item_name']) ?></td>
          <td class="fw-bold" style="color:#d97706">
            <?= number_format((float)$req['quantity_needed'], 3) ?> <?= e($req['unit']) ?>
          </td>
          <td class="text-muted"><?= e($req['notes'] ?: '—') ?></td>
          <td class="text-muted" style="white-space:nowrap;font-size:12px">
            <?= date('h:i A', strtotime($req['created_at'])) ?>
            <br><span style="font-size:11px"><?= date('Y/m/d', strtotime($req['created_at'])) ?></span>
          </td>
          <td>
            <button class="btn btn-sm btn-outline-success" onclick="markRead(<?= $req['id'] ?>)" title="تعليم كمقروء">
              <i class="fa fa-check"></i>
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span>
      <i class="fa fa-boxes-stacked text-danger me-2"></i>حركات الصرف
      <?php if ($dispenseDate === date('Y-m-d')): ?>
        <span class="text-muted fw-normal" style="font-size:13px">— اليوم</span>
      <?php else: ?>
        <span class="text-muted fw-normal" style="font-size:13px">— <?= date('Y/m/d', strtotime($dispenseDate)) ?></span>
      <?php endif; ?>
    </span>
    <div class="d-flex align-items-center gap-2">
      <?php if (!empty($todayDispense)): ?>
      <span class="badge bg-danger"><?= count($todayDispense) ?> عملية</span>
      <?php endif; ?>
      <form method="get" class="d-flex align-items-center gap-1" style="margin:0">
        <?php foreach ($_GET as $k => $v): if ($k === 'dispense_date') continue; ?>
        <input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>">
        <?php endforeach; ?>
        <input type="date" name="dispense_date" class="form-control form-control-sm"
               value="<?= e($dispenseDate) ?>" style="width:150px"
               onchange="this.form.submit()">
        <?php if ($dispenseDate !== date('Y-m-d')): ?>
        <a href="?" class="btn btn-sm btn-outline-secondary" title="العودة لليوم">
          <i class="fa fa-rotate-left"></i>
        </a>
        <?php endif; ?>
      </form>
    </div>
  </div>
<?php if (!empty($todayDispense)): ?>
  <div class="card-body p-0">

    <!-- ملخص الكاشيرز -->
    <?php if (!empty($cashierTotals)): ?>
    <div class="p-3 border-bottom bg-light d-flex gap-3 flex-wrap">
      <?php foreach ($cashierTotals as $ct): ?>
      <div class="d-flex align-items-center gap-2 px-3 py-2 rounded"
           style="background:#fff;border:1px solid #e2e8f0">
        <i class="fa fa-user-circle text-primary"></i>
        <div>
          <div class="fw-bold" style="font-size:13px"><?= e($ct['cashier_name']) ?></div>
          <div style="font-size:11px;color:#64748b"><?= $ct['ops'] ?> عملية — <?= e($ct['items']) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- تفاصيل الحركات -->
    <div style="max-height:320px;overflow-y:auto">
      <table class="table table-sm table-hover mb-0">
        <thead style="position:sticky;top:0;background:#f8fafc;z-index:1">
          <tr>
            <th>الكاشير</th>
            <th>الصنف</th>
            <th>الكمية المصروفة</th>
            <th>الرصيد بعد</th>
            <th>ملاحظات</th>
            <th>الوقت</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($todayDispense as $r): ?>
          <tr>
            <td><i class="fa fa-user me-1 text-muted"></i><?= e($r['cashier_name']) ?></td>
            <td class="fw-bold"><?= e($r['item_name']) ?></td>
            <td class="text-danger fw-bold">- <?= number_format((float)$r['quantity'], 3) ?> <?= e($r['unit']) ?></td>
            <td class="<?= $r['balance_after'] <= 0 ? 'text-danger' : 'text-muted' ?>">
              <?= number_format((float)$r['balance_after'], 3) ?> <?= e($r['unit']) ?>
            </td>
            <td class="text-muted"><?= e($r['notes'] ?: '—') ?></td>
            <td class="text-muted" style="white-space:nowrap"><?= date('h:i A', strtotime($r['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php else: ?>
  <div class="card-body text-center text-muted py-4">
    <i class="fa fa-inbox fa-2x mb-2 d-block opacity-50"></i>
    لا توجد حركات صرف في هذا اليوم
  </div>
<?php endif; ?>
</div>

<div class="row g-3 mb-4">
  <?php
  $total    = count($inventory);
  $low      = count(array_filter($inventory, function($i) { return $i['quantity'] <= $i['min_quantity']; }));
  $totalVal = array_sum(array_map(function($i) { return $i['quantity'] * $i['cost_per_unit']; }, $inventory));
  ?>
  <div class="col-md-3 col-6">
    <div class="stat-card"><div class="stat-icon blue"><i class="fa fa-boxes-stacked"></i></div>
      <div><div class="stat-value"><?= $total ?></div><div class="stat-label">إجمالي الأصناف</div></div></div>
  </div>
  <div class="col-md-3 col-6">
    <div class="stat-card"><div class="stat-icon red"><i class="fa fa-triangle-exclamation"></i></div>
      <div><div class="stat-value text-danger"><?= $low ?></div><div class="stat-label">منخفض المخزون</div></div></div>
  </div>
  <div class="col-md-3 col-6">
    <div class="stat-card"><div class="stat-icon teal"><i class="fa fa-coins"></i></div>
      <div><div class="stat-value" style="font-size:16px"><?= money($totalVal) ?></div><div class="stat-label">قيمة المخزون</div></div></div>
  </div>
</div>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="fa fa-boxes-stacked"></i> المواد والمخزون</span>
    <div class="d-flex gap-2">
      <a href="<?= BASE_URL ?>/admin/import_inventory.php" class="btn btn-outline-success btn-sm">
        <i class="fa fa-file-import me-1"></i> استيراد Excel
      </a>
      <button class="btn btn-outline-danger btn-sm" onclick="resetAll()">
        <i class="fa fa-rotate-left me-1"></i> تصفير المخزون
      </button>
      <button class="btn btn-outline-info btn-sm" onclick="openTxModal()">
        <i class="fa fa-arrows-up-down me-1"></i> حركة مخزون
      </button>
      <button class="btn btn-accent btn-sm" onclick="openModal()">
        <i class="fa fa-plus me-1"></i> مادة جديدة
      </button>
    </div>
  </div>
  <div class="card-body">
    <table id="stockTable" class="table table-hover align-middle">
      <thead>
        <tr><th>#</th><th>المادة</th><th>الوحدة</th><th>الكمية الحالية</th>
            <th>الحد الأدنى</th><th>سعر الوحدة</th><th>القيمة الإجمالية</th>
            <th>الحالة</th><th>الإجراءات</th></tr>
      </thead>
      <tbody>
        <?php foreach ($inventory as $item): ?>
        <?php $isLow = $item['quantity'] <= $item['min_quantity']; ?>
        <tr id="row-<?= $item['id'] ?>">
          <td><?= $item['id'] ?></td>
          <td class="fw-bold"><?= e($item['name']) ?></td>
          <td><?= e($item['unit']) ?></td>
          <td class="fw-bold <?= $isLow ? 'text-danger low-stock' : 'text-success stock-good' ?>">
            <?= number_format((float)$item['quantity'], 2) ?>
            <?= $isLow ? '<i class="fa fa-triangle-exclamation me-1"></i>' : '' ?>
          </td>
          <td class="text-muted"><?= number_format((float)$item['min_quantity'], 2) ?></td>
          <td><?= money((float)$item['cost_per_unit']) ?></td>
          <td class="fw-bold"><?= money($item['quantity'] * $item['cost_per_unit']) ?></td>
          <td>
            <span class="badge <?= $isLow ? 'bg-danger' : 'bg-success' ?>">
              <?= $isLow ? 'منخفض' : 'كافي' ?>
            </span>
          </td>
          <td>
            <button class="btn btn-sm btn-outline-success" onclick="quickAdd(<?= $item['id'] ?>, '<?= e($item['name']) ?>')" title="إضافة مخزون">
              <i class="fa fa-plus"></i>
            </button>
            <button class="btn btn-sm btn-outline-primary ms-1" onclick='editModal(<?= json_encode($item) ?>)' title="تعديل">
              <i class="fa fa-pen"></i>
            </button>
            <button class="btn btn-sm btn-outline-secondary ms-1" onclick="viewHistory(<?= $item['id'] ?>, '<?= e($item['name']) ?>')" title="السجل">
              <i class="fa fa-clock-rotate-left"></i>
            </button>
            <button class="btn btn-sm btn-outline-danger ms-1"
                    onclick="confirmDelete('<?= BASE_URL ?>/admin/api/stock.php?id=<?= $item['id'] ?>', () => $('#row-<?= $item['id'] ?>').remove())">
              <i class="fa fa-trash"></i>
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add/Edit Item Modal -->
<div class="modal fade" id="stockModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="modalTitle">مادة جديدة</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form id="stockForm">
        <div class="modal-body">
          <input type="hidden" name="id" id="stockId">
          <div class="row g-3">
            <div class="col-8">
              <label class="form-label">اسم المادة <span class="text-danger">*</span></label>
              <input type="text" name="name" id="stockName" class="form-control" required>
            </div>
            <div class="col-4">
              <label class="form-label">الوحدة</label>
              <select id="stockUnitSelect" class="form-select" onchange="handleUnitChange(this.value)">
                <option value="قطعة">قطعة</option>
                <option value="كيلو">كيلو</option>
                <option value="جرام">جرام</option>
                <option value="لتر">لتر</option>
                <option value="علبة">علبة</option>
                <option value="باكيت">باكيت</option>
                <option value="__other__">أخرى...</option>
              </select>
              <input type="text" name="unit" id="stockUnit" class="form-control mt-1"
                     placeholder="اكتب الوحدة..." style="display:none">
            </div>
            <div class="col-4">
              <label class="form-label">الكمية الحالية</label>
              <input type="number" name="quantity" id="stockQty" class="form-control" step="0.001" min="0" value="0">
            </div>
            <div class="col-4">
              <label class="form-label">الحد الأدنى</label>
              <input type="number" name="min_quantity" id="stockMin" class="form-control" step="0.001" min="0" value="0">
            </div>
            <div class="col-4">
              <label class="form-label">سعر الوحدة</label>
              <input type="number" name="cost_per_unit" id="stockCost" class="form-control" step="0.01" min="0" value="0">
            </div>
            <div class="col-12">
              <label class="form-label">ملاحظات</label>
              <textarea name="notes" id="stockNotes" class="form-control" rows="2"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
          <button type="submit" class="btn btn-accent">حفظ</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Transaction Modal -->
<div class="modal fade" id="txModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">حركة مخزون</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form id="txForm">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">المادة <span class="text-danger">*</span></label>
            <select name="inventory_id" id="txItem" class="form-select" required>
              <option value="">-- اختر مادة --</option>
              <?php foreach ($inventory as $item): ?>
              <option value="<?= $item['id'] ?>"><?= e($item['name']) ?> (<?= number_format((float)$item['quantity'],2) ?> <?= e($item['unit']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label">النوع</label>
              <select name="type" id="txType" class="form-select">
                <option value="in">إضافة (وارد)</option>
                <option value="out">صرف (صادر)</option>
                <option value="adjustment">تسوية</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label">الكمية <span class="text-danger">*</span></label>
              <input type="number" name="quantity" class="form-control" step="0.001" min="0.001" required>
            </div>
            <div class="col-12">
              <label class="form-label">ملاحظات</label>
              <input type="text" name="notes" class="form-control" placeholder="سبب الحركة...">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
          <button type="submit" class="btn btn-accent">تسجيل</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- History Modal -->
<div class="modal fade" id="historyModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="historyTitle">سجل الحركة</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" id="historyBody"></div>
    </div>
  </div>
</div>

<script>
$('#stockTable').DataTable();
const modal   = new bootstrap.Modal(document.getElementById('stockModal'));
const txModal = new bootstrap.Modal(document.getElementById('txModal'));
const histM   = new bootstrap.Modal(document.getElementById('historyModal'));

var KNOWN_UNITS = ['قطعة','كيلو','جرام','لتر','علبة','باكيت'];

function handleUnitChange(val) {
  var customInput = document.getElementById('stockUnit');
  if (val === '__other__') {
    customInput.style.display = 'block';
    customInput.value = '';
    customInput.focus();
  } else {
    customInput.style.display = 'none';
    customInput.value = val;
  }
}

function setUnitField(unit) {
  var sel = document.getElementById('stockUnitSelect');
  var inp = document.getElementById('stockUnit');
  if (KNOWN_UNITS.indexOf(unit) !== -1) {
    sel.value = unit;
    inp.style.display = 'none';
    inp.value = unit;
  } else {
    sel.value = '__other__';
    inp.style.display = 'block';
    inp.value = unit;
  }
}

function openModal() {
  document.getElementById('modalTitle').textContent = 'مادة جديدة';
  document.getElementById('stockForm').reset();
  document.getElementById('stockId').value = '';
  setUnitField('قطعة');
  modal.show();
}
function editModal(item) {
  document.getElementById('modalTitle').textContent = 'تعديل المادة';
  document.getElementById('stockId').value    = item.id;
  document.getElementById('stockName').value  = item.name;
  document.getElementById('stockQty').value   = item.quantity;
  document.getElementById('stockMin').value   = item.min_quantity;
  document.getElementById('stockCost').value  = item.cost_per_unit;
  document.getElementById('stockNotes').value = item.notes || '';
  setUnitField(item.unit || 'قطعة');
  modal.show();
}
function openTxModal() { document.getElementById('txForm').reset(); txModal.show(); }
function quickAdd(id, name) {
  document.getElementById('txForm').reset();
  document.getElementById('txItem').value = id;
  document.getElementById('txType').value = 'in';
  txModal.show();
}

$('#stockForm').submit(function(e) {
  e.preventDefault();
  $.post('<?= BASE_URL ?>/admin/api/stock.php', $(this).serialize() + '&action=save', res => {
    if (res.success) { modal.hide(); toast('success', res.message); setTimeout(() => location.reload(), 800); }
    else toast('error', res.message);
  }, 'json');
});

$('#txForm').submit(function(e) {
  e.preventDefault();
  $.post('<?= BASE_URL ?>/admin/api/stock.php', $(this).serialize() + '&action=transaction', res => {
    if (res.success) { txModal.hide(); toast('success', res.message); setTimeout(() => location.reload(), 800); }
    else toast('error', res.message);
  }, 'json');
});

function resetAll() {
  Swal.fire({
    title: 'تصفير المخزون',
    html: `<p class="mb-3">هذا الإجراء سيضع كمية <strong>كل الأصناف</strong> بالصفر وتسجيلها كتسوية في السجل.</p>
           <p class="text-danger fw-bold mb-3">لا يمكن التراجع عن هذا الإجراء</p>
           <label class="form-label fw-bold w-100 text-start">أدخل كلمة مرور الأدمن للتأكيد</label>
           <input id="swal-admin-pass" type="password" class="form-control text-start"
                  placeholder="كلمة المرور..." autocomplete="current-password">`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'تأكيد التصفير',
    cancelButtonText: 'إلغاء',
    focusConfirm: false,
    preConfirm: function() {
      var pass = document.getElementById('swal-admin-pass').value.trim();
      if (!pass) {
        Swal.showValidationMessage('كلمة المرور مطلوبة');
        return false;
      }
      return pass;
    }
  }).then(r => {
    if (!r.isConfirmed) return;
    $.post('<?= BASE_URL ?>/admin/api/stock.php',
      { action: 'reset_all', password: r.value },
      function(res) {
        if (res.success) { toast('success', res.message); setTimeout(() => location.reload(), 1000); }
        else Swal.fire('خطأ', res.message, 'error');
      }, 'json');
  });
}

function markRead(reqId) {
  $.post('<?= BASE_URL ?>/admin/api/stock.php', { action: 'mark_request_read', request_id: reqId }, function(res) {
    if (res.success) {
      var row = document.getElementById('req-' + reqId);
      if (row) { row.style.transition = 'opacity .4s'; row.style.opacity = '0'; setTimeout(function(){ row.remove(); }, 450); }
    }
  }, 'json');
}

function markAllRead() {
  $.post('<?= BASE_URL ?>/admin/api/stock.php', { action: 'mark_all_requests_read' }, function(res) {
    if (res.success) { toast('success', res.message); setTimeout(() => location.reload(), 800); }
  }, 'json');
}

function printRequests() {
  var w = window.open('', '_blank', 'width=700,height=600');
  w.document.write(document.getElementById('printSection').innerHTML);
  w.document.close();
  w.focus();
  setTimeout(function(){ w.print(); w.close(); }, 400);
}

function viewHistory(id, name) {
  document.getElementById('historyTitle').textContent = 'سجل حركة: ' + name;
  document.getElementById('historyBody').innerHTML = '<div class="text-center py-3"><i class="fa fa-spinner fa-spin fa-2x"></i></div>';
  histM.show();
  fetch(`<?= BASE_URL ?>/admin/api/stock.php?action=history&id=${id}`)
    .then(r => r.json())
    .then(data => { document.getElementById('historyBody').innerHTML = data.html || 'لا توجد بيانات'; });
}
</script>

<!-- سجل طلبات التوريد -->
<div class="card mt-4" id="reqLogCard">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2"
       style="background:linear-gradient(135deg,#fffbeb,#fef3c7);border-bottom:2px solid #f59e0b">
    <span style="color:#78350f;font-weight:700">
      <i class="fa fa-clock-rotate-left me-2" style="color:#f59e0b"></i>
      سجل طلبات التوريد
      <?php if (!empty($allRequests)): ?>
      <span class="badge ms-1" style="background:#f59e0b;color:#fff"><?= count($allRequests) ?> طلب</span>
      <?php endif; ?>
    </span>
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <form method="get" class="d-flex align-items-center gap-1" style="margin:0" id="reqFilterForm">
        <?php foreach ($_GET as $k => $v): if (in_array($k, ['req_date','req_month'])) continue; ?>
        <input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>">
        <?php endforeach; ?>
        <select name="req_month" class="form-select form-select-sm" style="width:150px"
                onchange="document.getElementById('reqDateInput').value='';this.form.submit()">
          <option value="">— كل الشهور —</option>
          <?php
          $months = ['01'=>'يناير','02'=>'فبراير','03'=>'مارس','04'=>'أبريل','05'=>'مايو','06'=>'يونيو',
                     '07'=>'يوليو','08'=>'أغسطس','09'=>'سبتمبر','10'=>'أكتوبر','11'=>'نوفمبر','12'=>'ديسمبر'];
          for ($y = (int)date('Y'); $y >= (int)date('Y')-1; $y--) {
              for ($m = 12; $m >= 1; $m--) {
                  $val = $y . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
                  if ($val > date('Y-m')) continue;
                  echo '<option value="'.$val.'" '.($reqLogMonth===$val?'selected':'').'>'.
                       ($months[str_pad($m,2,'0',STR_PAD_LEFT)]??$m).' '.$y.'</option>';
              }
          }
          ?>
        </select>
        <input type="date" name="req_date" id="reqDateInput" class="form-control form-control-sm"
               value="<?= e($reqLogDate) ?>" style="width:145px"
               onchange="document.getElementById('reqFilterForm').querySelector('[name=req_month]').value='';this.form.submit()">
        <?php if ($reqLogDate || $reqLogMonth): ?>
        <a href="?" class="btn btn-sm btn-outline-secondary" title="إظهار الكل"><i class="fa fa-rotate-left"></i></a>
        <?php endif; ?>
      </form>
      <?php if (!empty($shoppingList)): ?>
      <button class="btn btn-sm btn-outline-warning fw-bold" onclick="printRequests()">
        <i class="fa fa-print me-1"></i> طباعة القائمة
      </button>
      <?php endif; ?>
    </div>
  </div>
  <?php if (!empty($allRequests)): ?>
  <div class="card-body p-0">
    <div style="max-height:380px;overflow-y:auto">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead style="position:sticky;top:0;background:#fffbeb;z-index:1">
          <tr>
            <th>التاريخ والوقت</th>
            <th>الكاشير</th>
            <th>الصنف</th>
            <th>الكمية المطلوبة</th>
            <th>ملاحظات</th>
            <th>الحالة</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($allRequests as $r): ?>
          <tr style="<?= !$r['is_read'] ? 'background:#fffbeb' : '' ?>">
            <td style="white-space:nowrap;font-size:12px">
              <?= date('Y/m/d', strtotime($r['created_at'])) ?>
              <br><span class="text-muted"><?= date('h:i A', strtotime($r['created_at'])) ?></span>
            </td>
            <td><i class="fa fa-user me-1 text-muted"></i><?= e($r['cashier_name']) ?></td>
            <td class="fw-bold"><?= e($r['item_name']) ?></td>
            <td class="fw-bold" style="color:#d97706">
              <?= number_format((float)$r['quantity_needed'], 3) ?> <?= e($r['unit']) ?>
            </td>
            <td class="text-muted"><?= e($r['notes'] ?: '—') ?></td>
            <td>
              <?php if ($r['is_read']): ?>
              <span class="badge bg-success"><i class="fa fa-check me-1"></i>تمت</span>
              <?php else: ?>
              <span class="badge" style="background:#f59e0b;color:#fff">
                <i class="fa fa-bell me-1"></i>جديد
              </span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php else: ?>
  <div class="card-body text-center text-muted py-4">
    <i class="fa fa-inbox fa-2x mb-2 d-block opacity-50"></i>
    لا توجد طلبات توريد <?= $reqLogDate ? 'في هذا التاريخ' : 'حتى الآن' ?>
  </div>
  <?php endif; ?>
</div>

<!-- قسم الطباعة (مخفي في الصفحة) -->
<div id="printSection" style="display:none">
<html><head><meta charset="UTF-8">
<style>
  body { font-family: 'Cairo', Arial, sans-serif; direction: rtl; margin: 20px; color: #111; font-size: 13px; }
  h2   { text-align: center; font-size: 18px; margin-bottom: 4px; }
  .sub { text-align: center; color: #555; margin-bottom: 16px; font-size: 12px; }
  table { width: 100%; border-collapse: collapse; margin-top: 12px; }
  th { background: #f59e0b; color: #fff; padding: 8px 10px; text-align: right; font-size: 13px; }
  td { padding: 7px 10px; border-bottom: 1px solid #e5e7eb; }
  tr:nth-child(even) td { background: #fffbeb; }
  .total-row td { font-weight: bold; background: #fef3c7; border-top: 2px solid #f59e0b; }
  .footer { margin-top: 20px; text-align: center; color: #888; font-size: 11px; border-top: 1px dashed #ccc; padding-top: 10px; }
  .num { text-align: center; font-weight: bold; }
  @page { size: A4; margin: 15mm; }
</style>
</head><body>
<h2><?= e(appName()) ?> — قائمة طلبات التوريد</h2>
<div class="sub">
  الفترة: <?= e($periodLabel) ?> &nbsp;|&nbsp;
  طُبع بواسطة: <?= e($_SESSION['user_name'] ?? 'الأدمن') ?> &nbsp;|&nbsp;
  <?= date('Y/m/d h:i A') ?>
</div>
<table>
  <thead>
    <tr>
      <th>#</th>
      <th>الصنف</th>
      <th>الوحدة</th>
      <th>إجمالي الكمية المطلوبة</th>
      <th>عدد الطلبات</th>
      <th>طلب من قِبَل</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($shoppingList as $i => $row): ?>
    <tr>
      <td class="num"><?= $i + 1 ?></td>
      <td><strong><?= e($row['item_name']) ?></strong></td>
      <td><?= e($row['unit']) ?></td>
      <td><strong><?= number_format((float)$row['total_qty'], 3) ?></strong></td>
      <td class="num"><?= $row['req_count'] ?></td>
      <td style="font-size:11px;color:#555"><?= e($row['cashiers']) ?></td>
    </tr>
    <?php endforeach; ?>
    <tr class="total-row">
      <td colspan="3">الإجمالي</td>
      <td><?= count($shoppingList) ?> صنف</td>
      <td class="num"><?= count($allRequests) ?> طلب</td>
      <td></td>
    </tr>
  </tbody>
</table>
<div class="footer">
  <?= e(appName()) ?> — نظام إدارة المخزون
</div>
</body></html>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
