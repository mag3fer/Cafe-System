<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$employees = fetchAll('SELECT * FROM users ORDER BY role, name');
$pageTitle  = 'إدارة الموظفين';

$allPerms = [
    'manage_orders'  => 'إدارة الطلبات',
    'close_orders'   => 'إغلاق الطلبات',
    'stock_view'     => 'عرض المخزون',
    'stock_dispense' => 'صرف من المخزون',
    'stock_add'      => 'إضافة للمخزون',
    'view_reports'   => 'عرض التقارير',
    'apply_discount' => 'تطبيق الخصومات',
];
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="fa fa-users"></i> الموظفون</span>
    <button class="btn btn-accent btn-sm" onclick="openModal()">
      <i class="fa fa-plus me-1"></i> موظف جديد
    </button>
  </div>
  <div class="card-body">
    <table id="empTable" class="table table-hover align-middle">
      <thead>
        <tr><th>#</th><th>الاسم</th><th>اسم المستخدم</th><th>الدور</th>
            <th>الصلاحيات</th><th>الهاتف</th><th>الحالة</th><th>الإجراءات</th></tr>
      </thead>
      <tbody>
        <?php foreach ($employees as $emp): ?>
        <?php $perms = json_decode($emp['permissions'] ?? '[]', true) ?? []; ?>
        <tr id="row-<?= $emp['id'] ?>">
          <td><?= $emp['id'] ?></td>
          <td class="fw-bold"><?= e($emp['name']) ?></td>
          <td><code><?= e($emp['username']) ?></code></td>
          <td>
            <span class="badge <?= $emp['role']==='admin' ? 'bg-warning text-dark' : 'bg-primary' ?>">
              <?= $emp['role']==='admin' ? 'مدير' : 'كاشير' ?>
            </span>
          </td>
          <td>
            <?php if ($emp['role']==='admin'): ?>
            <span class="badge bg-success">جميع الصلاحيات</span>
            <?php else: ?>
              <?php foreach ($perms as $p): ?>
              <span class="badge bg-light text-dark border me-1"><?= e($allPerms[$p] ?? $p) ?></span>
              <?php endforeach; ?>
              <?php if (empty($perms)): ?><span class="text-muted">—</span><?php endif; ?>
            <?php endif; ?>
          </td>
          <td><?= e($emp['phone'] ?? '—') ?></td>
          <td>
            <span class="badge <?= $emp['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
              <?= $emp['is_active'] ? 'نشط' : 'موقوف' ?>
            </span>
          </td>
          <td>
            <button class="btn btn-sm btn-outline-primary" onclick='editModal(<?= json_encode($emp) ?>)'>
              <i class="fa fa-pen"></i>
            </button>
            <?php if ($emp['id'] != $_SESSION['user_id']): ?>
            <button class="btn btn-sm btn-outline-danger ms-1"
                    onclick="confirmDelete('<?= BASE_URL ?>/admin/api/employees.php?id=<?= $emp['id'] ?>', () => $('#row-<?= $emp['id'] ?>').remove())">
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
<div class="modal fade" id="empModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalTitle">موظف جديد</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="empForm">
        <div class="modal-body">
          <input type="hidden" name="id" id="empId">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">الاسم الكامل <span class="text-danger">*</span></label>
              <input type="text" name="name" id="empName" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">اسم المستخدم <span class="text-danger">*</span></label>
              <input type="text" name="username" id="empUsername" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">كلمة المرور <span id="passHint" class="text-muted">(اتركها فارغة للإبقاء)</span></label>
              <input type="password" name="password" id="empPass" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">الهاتف</label>
              <input type="text" name="phone" id="empPhone" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label">الدور</label>
              <select name="role" id="empRole" class="form-select" onchange="togglePerms(this.value)">
                <option value="cashier">كاشير</option>
                <option value="admin">مدير</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">الحالة</label>
              <select name="is_active" id="empActive" class="form-select">
                <option value="1">نشط</option>
                <option value="0">موقوف</option>
              </select>
            </div>
            <!-- Permissions Section -->
            <div class="col-12" id="permsSection">
              <label class="form-label fw-bold">الصلاحيات</label>
              <div class="row g-2">
                <?php foreach ($allPerms as $key => $label): ?>
                <div class="col-md-4">
                  <div class="form-check">
                    <input type="checkbox" name="permissions[]" value="<?= $key ?>"
                           id="perm_<?= $key ?>" class="form-check-input">
                    <label class="form-check-label" for="perm_<?= $key ?>"><?= $label ?></label>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
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
$('#empTable').DataTable();
const modal = new bootstrap.Modal(document.getElementById('empModal'));

function togglePerms(role) {
  document.getElementById('permsSection').style.display = role === 'admin' ? 'none' : '';
}

function openModal() {
  document.getElementById('modalTitle').textContent = 'موظف جديد';
  document.getElementById('empForm').reset();
  document.getElementById('empId').value = '';
  document.getElementById('passHint').textContent = '(مطلوبة)';
  togglePerms('cashier');
  modal.show();
}

function editModal(emp) {
  document.getElementById('modalTitle').textContent = 'تعديل الموظف';
  document.getElementById('empId').value       = emp.id;
  document.getElementById('empName').value     = emp.name;
  document.getElementById('empUsername').value = emp.username;
  document.getElementById('empPhone').value    = emp.phone || '';
  document.getElementById('empRole').value     = emp.role;
  document.getElementById('empActive').value   = emp.is_active;
  document.getElementById('passHint').textContent = '(اتركها فارغة للإبقاء)';

  const perms = JSON.parse(emp.permissions || '[]');
  document.querySelectorAll('[name="permissions[]"]').forEach(cb => {
    cb.checked = perms.includes(cb.value);
  });
  togglePerms(emp.role);
  modal.show();
}

$('#empForm').submit(function(e) {
  e.preventDefault();
  $.post('<?= BASE_URL ?>/admin/api/employees.php', $(this).serialize() + '&action=save', res => {
    if (res.success) { modal.hide(); toast('success', res.message); setTimeout(() => location.reload(), 800); }
    else toast('error', res.message);
  }, 'json');
});
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
