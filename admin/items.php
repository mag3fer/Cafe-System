<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

runMigrations();
$items      = fetchAll('SELECT i.*,c.name as cat_name FROM items i LEFT JOIN categories c ON c.id=i.category_id ORDER BY c.sort_order,i.name');
$categories = fetchAll('SELECT id,name FROM categories WHERE is_active=1 ORDER BY sort_order,name');
$inventory  = fetchAll('SELECT id,name,unit FROM inventory WHERE is_active=1 ORDER BY name');
$pageTitle  = 'إدارة الأصناف';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="fa fa-utensils"></i> الأصناف والمنيو</span>
    <div class="d-flex gap-2">
      <a href="<?= BASE_URL ?>/admin/import_items.php" class="btn btn-outline-success btn-sm">
        <i class="fa fa-file-import me-1"></i> استيراد Excel
      </a>
      <button class="btn btn-accent btn-sm" onclick="openModal()">
        <i class="fa fa-plus me-1"></i> صنف جديد
      </button>
    </div>
  </div>
  <div class="card-body">
    <table id="itemsTable" class="table table-hover align-middle">
      <thead>
        <tr>
          <th>#</th><th>الصنف</th><th>الفئة</th>
          <th>السعر</th><th>التكلفة</th><th>الربح</th>
          <th>الحالة</th><th>الإجراءات</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $item): ?>
        <?php $profit = $item['price'] - $item['cost']; ?>
        <tr id="row-<?= $item['id'] ?>">
          <td><?= $item['id'] ?></td>
          <td>
            <div class="fw-bold"><?= e($item['name']) ?></div>
            <?php if ($item['description']): ?>
            <small class="text-muted"><?= e(mb_substr($item['description'],0,40)) ?>...</small>
            <?php endif; ?>
          </td>
          <td><span class="badge bg-info text-dark"><?= e($item['cat_name'] ?? '—') ?></span></td>
          <td class="fw-bold text-success"><?= money((float)$item['price']) ?></td>
          <td class="text-muted"><?= money((float)$item['cost']) ?></td>
          <td>
            <span class="<?= $profit >= 0 ? 'text-success' : 'text-danger' ?> fw-bold">
              <?= money($profit) ?>
            </span>
          </td>
          <td>
            <span class="badge <?= $item['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
              <?= $item['is_active'] ? 'نشط' : 'مخفي' ?>
            </span>
          </td>
          <td>
            <button class="btn btn-sm btn-outline-primary" onclick='editModal(<?= json_encode($item) ?>)'>
              <i class="fa fa-pen"></i>
            </button>
            <button class="btn btn-sm btn-outline-danger ms-1"
                    onclick="confirmDelete('<?= BASE_URL ?>/admin/api/items.php?id=<?= $item['id'] ?>', () => $('#row-<?= $item['id'] ?>').remove())">
              <i class="fa fa-trash"></i>
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal -->
<div class="modal fade" id="itemModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalTitle">صنف جديد</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="itemForm">
        <div class="modal-body">
          <input type="hidden" name="id" id="itemId">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label">اسم الصنف <span class="text-danger">*</span></label>
              <input type="text" name="name" id="itemName" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">الفئة <span class="text-danger">*</span></label>
              <select name="category_id" id="itemCat" class="form-select" required>
                <option value="">-- اختر --</option>
                <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">السعر (<?= CURRENCY ?>) <span class="text-danger">*</span></label>
              <input type="number" name="price" id="itemPrice" class="form-control" step="0.01" min="0" required oninput="calcProfit()">
            </div>
            <div class="col-md-4">
              <label class="form-label">التكلفة (<?= CURRENCY ?>)</label>
              <input type="number" name="cost" id="itemCost" class="form-control" step="0.01" min="0" value="0" oninput="calcProfit()">
            </div>
            <div class="col-md-4">
              <label class="form-label">هامش الربح</label>
              <div class="form-control bg-light fw-bold" id="profitDisplay" style="color:#22c55e">—</div>
            </div>
            <div class="col-md-8">
              <label class="form-label">الوصف</label>
              <textarea name="description" id="itemDesc" class="form-control" rows="2"></textarea>
            </div>
            <div class="col-md-4">
              <label class="form-label">الحالة</label>
              <select name="is_active" id="itemActive" class="form-select">
                <option value="1">نشط</option>
                <option value="0">مخفي</option>
              </select>
            </div>
            <div class="col-12">
              <hr class="my-1">
              <label class="form-label fw-bold">
                <i class="fa fa-boxes-stacked me-1 text-success"></i> ربط المخزون (اختياري)
              </label>
              <div class="row g-2">
                <div class="col-8">
                  <select name="inventory_id" id="itemInvId" class="form-select">
                    <option value="">— بدون ربط —</option>
                    <?php foreach ($inventory as $inv): ?>
                    <option value="<?= $inv['id'] ?>"><?= e($inv['name']) ?> (<?= e($inv['unit']) ?>)</option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-4">
                  <input type="number" name="inventory_qty" id="itemInvQty" class="form-control"
                         step="0.001" min="0.001" value="1" placeholder="الكمية لكل بيعة">
                </div>
                <div class="col-12">
                  <small class="text-muted">كل ما اتباع الصنف ده، هتنقص الكمية دي من المخزون أوتوماتيك عند إغلاق الأوردر</small>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
          <button type="submit" class="btn btn-accent">حفظ الصنف</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
$('#itemsTable').DataTable();
const modal = new bootstrap.Modal(document.getElementById('itemModal'));

function calcProfit() {
  const p = parseFloat(document.getElementById('itemPrice').value) || 0;
  const c = parseFloat(document.getElementById('itemCost').value) || 0;
  const profit = p - c;
  const pct = p > 0 ? ((profit / p) * 100).toFixed(1) : 0;
  const el = document.getElementById('profitDisplay');
  el.textContent = profit.toFixed(2) + ' <?= CURRENCY ?> (' + pct + '%)';
  el.style.color = profit >= 0 ? '#22c55e' : '#ef4444';
}

function openModal() {
  document.getElementById('modalTitle').textContent = 'صنف جديد';
  document.getElementById('itemForm').reset();
  document.getElementById('itemId').value = '';
  document.getElementById('profitDisplay').textContent = '—';
  modal.show();
}

function editModal(item) {
  document.getElementById('modalTitle').textContent = 'تعديل الصنف';
  document.getElementById('itemId').value      = item.id;
  document.getElementById('itemName').value    = item.name;
  document.getElementById('itemCat').value     = item.category_id || '';
  document.getElementById('itemPrice').value   = item.price;
  document.getElementById('itemCost').value    = item.cost || 0;
  document.getElementById('itemDesc').value    = item.description || '';
  document.getElementById('itemActive').value  = item.is_active;
  document.getElementById('itemInvId').value   = item.inventory_id || '';
  document.getElementById('itemInvQty').value  = item.inventory_qty || 1;
  calcProfit();
  modal.show();
}

$('#itemForm').submit(function(e) {
  e.preventDefault();
  $.post('<?= BASE_URL ?>/admin/api/items.php', $(this).serialize() + '&action=save', res => {
    if (res.success) {
      modal.hide();
      toast('success', res.message);
      setTimeout(() => location.reload(), 1000);
    } else {
      toast('error', res.message || 'حدث خطأ');
    }
  }, 'json');
});
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
