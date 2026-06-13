<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$tables    = fetchAll('SELECT * FROM cafe_tables ORDER BY number');
$pageTitle = 'إدارة الطاولات';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="fa fa-table-cells-large"></i> الطاولات</span>
    <button class="btn btn-accent btn-sm" onclick="openModal()">
      <i class="fa fa-plus me-1"></i> طاولة جديدة
    </button>
  </div>
  <div class="card-body">
    <div class="table-grid mb-4">
      <?php foreach ($tables as $t): ?>
      <?php $isVip = !empty($t['is_vip']); ?>
      <div class="table-card <?= $t['status'] . ($isVip ? ' vip' : '') ?>">
        <div class="tc-header">
          <div class="tc-num"><?= $t['number'] ?></div>
          <div class="tc-name"><?= e($t['name']) ?></div>
          <div class="tc-badges">
            <span class="tc-badge <?= $t['status'] ?>">
              <?= $t['status']==='available'
                  ? '<i class="fa fa-circle-check"></i> متاحة'
                  : '<i class="fa fa-fire"></i> مشغولة' ?>
            </span>
            <?php if ($isVip): ?>
            <span class="tc-badge vip-tag"><i class="fa fa-crown"></i> VIP</span>
            <?php endif; ?>
          </div>
        </div>
        <div class="tc-body">
          <div class="tc-capacity"><i class="fa fa-users me-1"></i><?= $t['capacity'] ?> أشخاص</div>
          <div class="d-flex gap-1 justify-content-center mt-2">
            <button class="btn btn-xs btn-outline-primary" onclick='editModal(<?= json_encode($t) ?>)' title="تعديل">
              <i class="fa fa-pen" style="font-size:10px"></i>
            </button>
            <?php if ($t['status']==='available'): ?>
            <button class="btn btn-xs btn-outline-danger"
                    onclick="confirmDelete('<?= BASE_URL ?>/admin/api/tables.php?id=<?= $t['id'] ?>', () => location.reload())"
                    title="حذف">
              <i class="fa fa-trash" style="font-size:10px"></i>
            </button>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <table id="tablesDataTable" class="table table-hover align-middle">
      <thead>
        <tr><th>#</th><th>الرقم</th><th>الاسم</th><th>السعة</th><th>الحالة</th><th>الإجراءات</th></tr>
      </thead>
      <tbody>
        <?php foreach ($tables as $t): ?>
        <tr id="row-<?= $t['id'] ?>">
          <td><?= $t['id'] ?></td>
          <td class="fw-bold">طاولة <?= $t['number'] ?></td>
          <td><?= e($t['name']) ?></td>
          <td><?= $t['capacity'] ?> أشخاص</td>
          <td>
            <span class="badge <?= $t['status']==='available' ? 'bg-success' : 'bg-danger' ?>">
              <?= $t['status']==='available' ? 'متاحة' : 'مشغولة' ?>
            </span>
          </td>
          <td>
            <button class="btn btn-sm btn-outline-primary" onclick='editModal(<?= json_encode($t) ?>)'>
              <i class="fa fa-pen"></i>
            </button>
            <?php if ($t['status']==='available'): ?>
            <button class="btn btn-sm btn-outline-danger ms-1"
                    onclick="confirmDelete('<?= BASE_URL ?>/admin/api/tables.php?id=<?= $t['id'] ?>', () => $('#row-<?= $t['id'] ?>').remove())">
              <i class="fa fa-trash"></i>
            </button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal -->
<div class="modal fade" id="tableModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalTitle">طاولة جديدة</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="tableForm">
        <div class="modal-body">
          <input type="hidden" name="id" id="tableId">
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label">رقم الطاولة <span class="text-danger">*</span></label>
              <input type="number" name="number" id="tableNumber" class="form-control" min="1" required>
            </div>
            <div class="col-6">
              <label class="form-label">اسم الطاولة</label>
              <input type="text" name="name" id="tableName" class="form-control" placeholder="طاولة VIP...">
            </div>
            <div class="col-6">
              <label class="form-label">السعة (أشخاص)</label>
              <input type="number" name="capacity" id="tableCapacity" class="form-control" min="1" value="4">
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

<script>
$('#tablesDataTable').DataTable();
const modal = new bootstrap.Modal(document.getElementById('tableModal'));

function openModal() {
  document.getElementById('modalTitle').textContent = 'طاولة جديدة';
  document.getElementById('tableForm').reset();
  document.getElementById('tableId').value = '';
  modal.show();
}

function editModal(t) {
  document.getElementById('modalTitle').textContent = 'تعديل الطاولة';
  document.getElementById('tableId').value       = t.id;
  document.getElementById('tableNumber').value   = t.number;
  document.getElementById('tableName').value     = t.name || '';
  document.getElementById('tableCapacity').value = t.capacity;
  modal.show();
}

$('#tableForm').submit(function(e) {
  e.preventDefault();
  $.post('<?= BASE_URL ?>/admin/api/tables.php', $(this).serialize() + '&action=save', res => {
    if (res.success) { modal.hide(); toast('success', res.message); setTimeout(() => location.reload(), 800); }
    else toast('error', res.message);
  }, 'json');
});
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
