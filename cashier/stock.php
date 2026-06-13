<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
runMigrations();

// تحديث الصلاحيات من قاعدة البيانات مباشرة (لضمان أي تغيير من الأدمن يسري فوراً)
$_freshUser = fetchOne('SELECT permissions FROM users WHERE id=? AND is_active=1', [$_SESSION['user_id']]);
$_SESSION['permissions'] = json_decode($_freshUser['permissions'] ?? '[]', true) ?? [];

$canDispense = anyPermission(['manage_stock', 'stock_dispense']);
$canAdd      = anyPermission(['manage_stock', 'stock_add']);

$inventory = fetchAll('SELECT * FROM inventory WHERE is_active=1 ORDER BY name');
$pageTitle  = 'المخزون';
require_once __DIR__ . '/../includes/cashier_header.php';
?>

<style>
@keyframes dangerPulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(239,68,68,.0); }
  50%       { box-shadow: 0 0 0 10px rgba(239,68,68,.22); }
}
@keyframes emptyPulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(245,158,11,.0); border-color: #f59e0b; }
  50%       { box-shadow: 0 0 0 14px rgba(245,158,11,.25); border-color: #d97706; }
}
@keyframes alertGlow {
  0%, 100% { box-shadow: 0 0 0 0 rgba(245,158,11,.0); }
  50%       { box-shadow: 0 0 16px 4px rgba(245,158,11,.35); }
}
.card-low {
  border: 2px solid #ef4444 !important;
  animation: dangerPulse 2s ease-in-out infinite;
}
.card-empty {
  border: 2px solid #f59e0b !important;
  animation: emptyPulse 1.5s ease-in-out infinite;
}
.low-warn-banner {
  background: #fef2f2;
  border: 1px dashed #ef4444;
  border-radius: 8px;
  padding: 6px 12px;
  margin-bottom: 10px;
  color: #dc2626;
  font-size: 12px;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 6px;
}
.low-warn-banner i { animation: dangerPulse 1s ease-in-out infinite; }
.empty-warn-banner {
  background: linear-gradient(135deg,#fffbeb,#fef3c7);
  border: 1px dashed #f59e0b;
  border-radius: 8px;
  padding: 8px 12px;
  margin-bottom: 10px;
  color: #92400e;
  font-size: 12px;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 6px;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h5 class="fw-bold mb-0">المخزون</h5>
    <small class="text-muted">عرض المواد وتسجيل الصرف</small>
  </div>
</div>

<?php
$emptyItems = array_filter($inventory, function($i) { return (float)$i['quantity'] <= 0; });
$lowItems   = array_filter($inventory, function($i) { return (float)$i['quantity'] > 0 && (float)$i['quantity'] <= (float)$i['min_quantity']; });
?>
<?php if (count($emptyItems)): ?>
<div id="emptyAlert" class="d-flex align-items-center justify-content-between mb-3 px-4 py-3"
     style="background:linear-gradient(135deg,#fffbeb,#fef3c7);
            border:2px solid #f59e0b;border-radius:14px;color:#78350f;
            animation:alertGlow 2s ease-in-out infinite">
  <div class="d-flex align-items-center gap-3">
    <i class="fa fa-triangle-exclamation fa-xl" style="color:#d97706"></i>
    <div>
      <div class="fw-bold" style="font-size:14px">
        نفد المخزون!
        <span class="badge ms-1" style="background:#f59e0b;color:#fff">
          <?= count($emptyItems) ?> <?= count($emptyItems) === 1 ? 'صنف' : 'أصناف' ?>
        </span>
      </div>
      <div style="font-size:12px;opacity:.85">اضغط زرار "أخبر الأدمن" على الصنف المنتهي لإرسال طلب التوريد</div>
    </div>
  </div>
  <button type="button" onclick="document.getElementById('emptyAlert').remove()"
          style="background:none;border:none;color:#92400e;font-size:18px;cursor:pointer;padding:4px 8px;border-radius:50%;transition:.2s"
          onmouseover="this.style.background='rgba(245,158,11,.2)'" onmouseout="this.style.background='none'">
    <i class="fa fa-xmark"></i>
  </button>
</div>
<?php endif; ?>
<?php if (count($lowItems)): ?>
<div class="alert alert-danger mb-4 d-flex align-items-center gap-2">
  <i class="fa fa-triangle-exclamation fa-lg"></i>
  <div>
    <strong>تنبيه مخزون منخفض!</strong>
    يوجد <strong><?= count($lowItems) ?></strong>
    <?= count($lowItems) === 1 ? 'صنف' : 'أصناف' ?> قاربت على الانتهاء — أخبر الأدمن فوراً
  </div>
</div>
<?php endif; ?>

<div class="row g-3">
  <?php foreach ($inventory as $item):
    $qty    = (float)$item['quantity'];
    $min    = (float)$item['min_quantity'];
    $isEmpty = $qty <= 0;
    $isLow   = !$isEmpty && $qty <= $min;
    $pct     = $min > 0 ? min(100, ($qty / ($min * 2)) * 100) : ($isEmpty ? 0 : 100);
  ?>
  <div class="col-md-6 col-lg-4">
    <div class="card <?= $isEmpty ? 'card-empty' : ($isLow ? 'card-low' : '') ?>" id="card-<?= $item['id'] ?>">
      <div class="card-body">

        <?php if ($isEmpty): ?>
        <div class="empty-warn-banner">
          <i class="fa fa-ban"></i>
          نفد المخزون تماماً — أبلغ الأدمن فوراً!
        </div>
        <?php elseif ($isLow): ?>
        <div class="low-warn-banner">
          <i class="fa fa-circle-exclamation"></i>
          قارب على الانتهاء! المتبقي: <?= number_format($qty, 2) ?> <?= e($item['unit']) ?>
        </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-start mb-3">
          <div>
            <div class="fw-bold fs-6"><?= e($item['name']) ?></div>
            <small class="text-muted"><?= e($item['unit']) ?></small>
          </div>
          <span class="badge <?= $isEmpty ? 'bg-dark' : ($isLow ? 'bg-danger' : 'bg-success') ?>">
            <?= $isEmpty ? 'نفد' : ($isLow ? 'منخفض' : 'كافي') ?>
          </span>
        </div>

        <div class="d-flex justify-content-between mb-1">
          <span class="text-muted">الكمية الحالية:</span>
          <span class="fw-bold <?= $isEmpty ? 'text-dark' : ($isLow ? 'text-danger' : 'text-success') ?>" id="qty-<?= $item['id'] ?>">
            <?= number_format($qty, 2) ?> <?= e($item['unit']) ?>
          </span>
        </div>
        <div class="d-flex justify-content-between mb-2">
          <span class="text-muted">الحد الأدنى:</span>
          <span><?= number_format((float)$item['min_quantity'], 2) ?> <?= e($item['unit']) ?></span>
        </div>
        <div class="progress mb-3" style="height:6px">
          <div class="progress-bar <?= $isEmpty ? 'bg-dark' : ($isLow ? 'bg-danger' : 'bg-success') ?>"
               style="width:<?= $pct ?>%"></div>
        </div>

        <?php if ($canDispense || $canAdd): ?>
        <div class="d-flex gap-2 <?= $isEmpty ? 'mb-2' : '' ?>">
          <?php if ($canDispense): ?>
          <button class="btn btn-danger <?= $canAdd ? 'flex-fill' : 'w-100' ?>"
                  onclick="dispense(<?= $item['id'] ?>, '<?= e($item['name']) ?>', '<?= e($item['unit']) ?>')"
                  <?= $isEmpty ? 'disabled title="المخزون نفد"' : '' ?>>
            <i class="fa fa-minus me-1"></i> صرف
          </button>
          <?php endif; ?>
          <?php if ($canAdd): ?>
          <button class="btn btn-success <?= $canDispense ? 'flex-fill' : 'w-100' ?>"
                  onclick="addStock(<?= $item['id'] ?>, '<?= e($item['name']) ?>', '<?= e($item['unit']) ?>')">
            <i class="fa fa-plus me-1"></i> إضافة
          </button>
          <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="text-center text-muted py-1 <?= $isEmpty ? 'mb-2' : '' ?>"
             style="font-size:12px;border:1px dashed #e2e8f0;border-radius:8px;padding:8px">
          <i class="fa fa-eye me-1"></i> عرض فقط
        </div>
        <?php endif; ?>

        <button class="btn w-100 mt-2 fw-bold"
                style="background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;border:0;opacity:<?= ($isEmpty||$isLow) ? '1' : '.75' ?>"
                onclick="notifyAdmin(<?= $item['id'] ?>, '<?= e($item['name']) ?>', '<?= e($item['unit']) ?>', <?= $qty ?>)">
          <i class="fa fa-bell me-1"></i>
          <?= $isEmpty ? 'أخبر الأدمن — نفد المخزون' : ($isLow ? 'أخبر الأدمن — قارب على الانتهاء' : 'طلب توريد من الأدمن') ?>
        </button>

      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Notify Admin Modal -->
<div class="modal fade" id="notifyModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header text-white" style="background:linear-gradient(135deg,#f59e0b,#d97706)">
        <h5 class="modal-title"><i class="fa fa-bell me-2"></i><span id="notifyTitle">طلب توريد</span></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="notifyForm">
        <div class="modal-body">
          <input type="hidden" name="inventory_id" id="notifyId">
          <input type="hidden" name="action" value="request_stock">
          <div class="alert py-2 mb-3" style="background:#fffbeb;border:1px solid #f59e0b;color:#78350f;font-size:13px">
            <i class="fa fa-info-circle me-1"></i>
            سيصل التنبيه للأدمن مع اسمك والكمية المطلوبة
            <div class="mt-1 fw-bold" id="notifyCurrentQty"></div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">الكمية المطلوبة <span class="text-danger">*</span></label>
            <div class="input-group">
              <input type="number" name="quantity_needed" id="notifyQty"
                     class="form-control form-control-lg" step="0.001" min="0.001"
                     required autofocus placeholder="0.000">
              <span class="input-group-text" id="notifyUnit"></span>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">ملاحظة إضافية</label>
            <input type="text" name="notes" class="form-control" placeholder="مثال: ضروري قبل الغد...">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
          <button type="submit" class="btn fw-bold text-white px-4" style="background:linear-gradient(135deg,#f59e0b,#d97706);border:0">
            <i class="fa fa-paper-plane me-1"></i> إرسال التنبيه
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Add Stock Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title"><i class="fa fa-plus-circle me-2"></i><span id="addTitle">إضافة مخزون</span></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="addForm">
        <div class="modal-body">
          <input type="hidden" name="inventory_id" id="addId">
          <input type="hidden" name="action" value="add">
          <div class="mb-3">
            <label class="form-label fw-bold">الكمية المضافة <span class="text-danger">*</span></label>
            <div class="input-group">
              <input type="number" name="quantity" id="addQty"
                     class="form-control form-control-lg" step="0.001" min="0.001"
                     required autofocus placeholder="0.000">
              <span class="input-group-text" id="addUnit"></span>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">السبب / ملاحظات</label>
            <input type="text" name="notes" class="form-control" placeholder="اختياري...">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
          <button type="submit" class="btn btn-success px-4">
            <i class="fa fa-check me-1"></i> تأكيد الإضافة
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Dispense Modal -->
<div class="modal fade" id="dispenseModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title"><i class="fa fa-minus-circle me-2"></i><span id="dispenseTitle">صرف مخزون</span></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="dispenseForm">
        <div class="modal-body">
          <input type="hidden" name="inventory_id" id="dispenseId">
          <input type="hidden" name="action" value="dispense">
          <div class="mb-3">
            <label class="form-label fw-bold">الكمية المصروفة <span class="text-danger">*</span></label>
            <div class="input-group">
              <input type="number" name="quantity" id="dispenseQty"
                     class="form-control form-control-lg" step="0.001" min="0.001"
                     required autofocus placeholder="0.000">
              <span class="input-group-text" id="dispenseUnit"></span>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">السبب / ملاحظات</label>
            <input type="text" name="notes" class="form-control" placeholder="اختياري...">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
          <button type="submit" class="btn btn-danger px-4">
            <i class="fa fa-check me-1"></i> تأكيد الصرف
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const dispModal   = new bootstrap.Modal(document.getElementById('dispenseModal'));
const addModal    = new bootstrap.Modal(document.getElementById('addModal'));
const notifyModal = new bootstrap.Modal(document.getElementById('notifyModal'));

function notifyAdmin(itemId, name, unit, currentQty) {
  document.getElementById('notifyId').value          = itemId;
  document.getElementById('notifyTitle').textContent = 'طلب توريد: ' + name;
  document.getElementById('notifyUnit').textContent  = unit;
  document.getElementById('notifyForm').reset();
  document.getElementById('notifyId').value = itemId;
  var hint = document.getElementById('notifyCurrentQty');
  if (hint) hint.textContent = 'الكمية الحالية: ' + (currentQty > 0 ? currentQty.toFixed(2) + ' ' + unit : 'نفدت تماماً');
  notifyModal.show();
  setTimeout(() => document.getElementById('notifyQty').focus(), 400);
}

$('#notifyForm').submit(function(e) {
  e.preventDefault();
  var btn = $(this).find('[type=submit]');
  btn.prop('disabled', true);
  $.post('<?= BASE_URL ?>/cashier/api/stock.php', $(this).serialize(), function(res) {
    btn.prop('disabled', false);
    if (res.success) {
      notifyModal.hide();
      toast('success', res.message);
    } else {
      toast('error', res.message);
    }
  }, 'json').fail(function() {
    btn.prop('disabled', false);
    toast('error', 'حدث خطأ في الاتصال');
  });
});

function addStock(itemId, name, unit) {
  document.getElementById('addId').value          = itemId;
  document.getElementById('addTitle').textContent = name;
  document.getElementById('addUnit').textContent  = unit;
  document.getElementById('addForm').reset();
  document.getElementById('addId').value = itemId;
  addModal.show();
  setTimeout(() => document.getElementById('addQty').focus(), 400);
}

$('#addForm').submit(function(e) {
  e.preventDefault();
  var btn = $(this).find('[type=submit]');
  btn.prop('disabled', true);
  $.post('<?= BASE_URL ?>/cashier/api/stock.php', $(this).serialize(), function(res) {
    btn.prop('disabled', false);
    if (res.success) {
      addModal.hide();
      toast('success', res.message);
      setTimeout(() => location.reload(), 1000);
    } else {
      toast('error', res.message);
    }
  }, 'json').fail(function() {
    btn.prop('disabled', false);
    toast('error', 'حدث خطأ في الاتصال');
  });
});

function dispense(itemId, name, unit) {
  document.getElementById('dispenseId').value       = itemId;
  document.getElementById('dispenseTitle').textContent = name;
  document.getElementById('dispenseUnit').textContent  = unit;
  document.getElementById('dispenseForm').reset();
  document.getElementById('dispenseId').value = itemId;
  dispModal.show();
  setTimeout(() => document.getElementById('dispenseQty').focus(), 400);
}

$('#dispenseForm').submit(function(e) {
  e.preventDefault();
  var btn = $(this).find('[type=submit]');
  btn.prop('disabled', true);
  $.post('<?= BASE_URL ?>/cashier/api/stock.php', $(this).serialize(), function(res) {
    btn.prop('disabled', false);
    if (res.success) {
      dispModal.hide();
      toast('success', res.message);
      setTimeout(() => location.reload(), 1000);
    } else {
      toast('error', res.message);
    }
  }, 'json').fail(function() {
    btn.prop('disabled', false);
    toast('error', 'حدث خطأ في الاتصال');
  });
});
</script>

<?php require_once __DIR__ . '/../includes/cashier_footer.php'; ?>
