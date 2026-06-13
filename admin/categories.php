<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$categories = fetchAll('SELECT * FROM categories ORDER BY sort_order, name');
$pageTitle   = 'إدارة الفئات';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="fa fa-tags"></i> الفئات</span>
    <button class="btn btn-accent btn-sm" onclick="openModal()">
      <i class="fa fa-plus me-1"></i> فئة جديدة
    </button>
  </div>
  <div class="card-body">
    <table id="catTable" class="table table-hover align-middle">
      <thead>
        <tr>
          <th>#</th><th>الاسم</th><th>الوصف</th><th>الترتيب</th>
          <th>الحالة</th><th>الإجراءات</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($categories as $c): ?>
        <tr id="row-<?= $c['id'] ?>">
          <td><?= $c['id'] ?></td>
          <td class="fw-bold"><?= e($c['name']) ?></td>
          <td class="text-muted"><?= e($c['description'] ?? '—') ?></td>
          <td><?= $c['sort_order'] ?></td>
          <td>
            <span class="badge <?= $c['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
              <?= $c['is_active'] ? 'نشطة' : 'مخفية' ?>
            </span>
          </td>
          <td>
            <button class="btn btn-sm btn-outline-primary" onclick='editModal(<?= json_encode($c) ?>)'>
              <i class="fa fa-pen"></i>
            </button>
            <button class="btn btn-sm btn-outline-danger ms-1"
                    onclick="confirmDelete('<?= BASE_URL ?>/admin/api/categories.php?id=<?= $c['id'] ?>', () => $('#row-<?= $c['id'] ?>').remove())">
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
<div class="modal fade" id="catModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalTitle">فئة جديدة</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="catForm">
        <div class="modal-body">
          <input type="hidden" name="id" id="catId">
          <div class="mb-3">
            <label class="form-label">اسم الفئة <span class="text-danger">*</span></label>
            <input type="text" name="name" id="catName" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">الوصف</label>
            <textarea name="description" id="catDesc" class="form-control" rows="2"></textarea>
          </div>
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label">الترتيب</label>
              <input type="number" name="sort_order" id="catOrder" class="form-control" value="0" min="0">
            </div>
            <div class="col-6">
              <label class="form-label">الحالة</label>
              <select name="is_active" id="catActive" class="form-select">
                <option value="1">نشطة</option>
                <option value="0">مخفية</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
          <button type="submit" class="btn btn-accent" id="saveBtn">حفظ</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
$('#catTable').DataTable();
const modal = new bootstrap.Modal(document.getElementById('catModal'));

function openModal() {
  document.getElementById('modalTitle').textContent = 'فئة جديدة';
  document.getElementById('catForm').reset();
  document.getElementById('catId').value = '';
  modal.show();
}

function editModal(c) {
  document.getElementById('modalTitle').textContent = 'تعديل الفئة';
  document.getElementById('catId').value    = c.id;
  document.getElementById('catName').value  = c.name;
  document.getElementById('catDesc').value  = c.description || '';
  document.getElementById('catOrder').value = c.sort_order;
  document.getElementById('catActive').value = c.is_active;
  modal.show();
}

$('#catForm').submit(function(e) {
  e.preventDefault();
  const data = $(this).serialize() + '&action=save';
  $.post('<?= BASE_URL ?>/admin/api/categories.php', data, res => {
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
