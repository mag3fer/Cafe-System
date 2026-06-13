<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

// ── Reset Menu (Categories + Items) ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_menu') {
    define('IS_API', true);
    header('Content-Type: application/json; charset=utf-8');
    $pass  = trim($_POST['password'] ?? '');
    $admin = fetchOne('SELECT password FROM users WHERE id=? AND role=? AND is_active=1',
                      [$_SESSION['user_id'], 'admin']);
    if (!$admin || !password_verify($pass, $admin['password'])) {
        echo json_encode(['success' => false, 'message' => 'كلمة المرور غير صحيحة']); exit;
    }
    $db = getDB();
    try {
        $db->exec('SET foreign_key_checks = 0');
        $db->exec('TRUNCATE TABLE items');
        $db->exec('TRUNCATE TABLE categories');
        $db->exec('SET foreign_key_checks = 1');
        echo json_encode(['success' => true, 'message' => 'تم مسح جميع الأصناف والفئات']);
    } catch (Exception $e) {
        $db->exec('SET foreign_key_checks = 1');
        echo json_encode(['success' => false, 'message' => 'خطأ: ' . $e->getMessage()]);
    }
    exit;
}

// ── Reset Inventory ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_inventory') {
    define('IS_API', true);
    header('Content-Type: application/json; charset=utf-8');
    $pass  = trim($_POST['password'] ?? '');
    $admin = fetchOne('SELECT password FROM users WHERE id=? AND role=? AND is_active=1',
                      [$_SESSION['user_id'], 'admin']);
    if (!$admin || !password_verify($pass, $admin['password'])) {
        echo json_encode(['success' => false, 'message' => 'كلمة المرور غير صحيحة']); exit;
    }
    $db = getDB();
    try {
        $db->exec('SET foreign_key_checks = 0');
        $db->exec('TRUNCATE TABLE inventory_transactions');
        $db->exec('TRUNCATE TABLE stock_requests');
        $db->exec('TRUNCATE TABLE inventory');
        // إزالة ربط الأصناف بالمخزون
        $db->exec('UPDATE items SET inventory_id=NULL, inventory_qty=1');
        $db->exec('SET foreign_key_checks = 1');
        echo json_encode(['success' => true, 'message' => 'تم مسح المخزون بالكامل']);
    } catch (Exception $e) {
        $db->exec('SET foreign_key_checks = 1');
        echo json_encode(['success' => false, 'message' => 'خطأ: ' . $e->getMessage()]);
    }
    exit;
}

// ── Reset All Data ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_data') {
    define('IS_API', true);
    header('Content-Type: application/json; charset=utf-8');

    $pass = trim($_POST['password'] ?? '');
    $admin = fetchOne('SELECT password FROM users WHERE id=? AND role=? AND is_active=1',
                      [$_SESSION['user_id'], 'admin']);
    if (!$admin || !password_verify($pass, $admin['password'])) {
        echo json_encode(['success' => false, 'message' => 'كلمة المرور غير صحيحة']);
        exit;
    }

    $db = getDB();
    try {
        $db->exec('SET foreign_key_checks = 0');
        // حذف بيانات التشغيل
        $db->exec('TRUNCATE TABLE order_items');
        $db->exec('TRUNCATE TABLE orders');
        $db->exec('TRUNCATE TABLE shifts');
        $db->exec('TRUNCATE TABLE inventory_transactions');
        $db->exec('TRUNCATE TABLE stock_requests');
        // تصفير حالة الطاولات
        $db->exec("UPDATE cafe_tables SET status='available'");
        $db->exec('SET foreign_key_checks = 1');
        echo json_encode(['success' => true, 'message' => 'تم مسح جميع بيانات التشغيل بنجاح']);
    } catch (Exception $e) {
        $db->exec('SET foreign_key_checks = 1');
        echo json_encode(['success' => false, 'message' => 'خطأ: ' . $e->getMessage()]);
    }
    exit;
}

// ── Export DB as SQL ──────────────────────────────────────────────────
if (isset($_GET['export'])) {
    $db   = getDB();
    $name = DB_NAME;
    $sql  = "-- =====================================================\n";
    $sql .= "-- HALF TIME PS AND CAFE — Database Backup\n";
    $sql .= "-- Date: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- =====================================================\n\n";
    $sql .= "SET NAMES utf8mb4;\n";
    $sql .= "SET foreign_key_checks = 0;\n\n";
    $sql .= "CREATE DATABASE IF NOT EXISTS `{$name}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n";
    $sql .= "USE `{$name}`;\n\n";

    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        // DROP + CREATE
        $create = $db->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
        $createSql = array_values($create)[1];
        $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $sql .= $createSql . ";\n\n";

        // Data
        $rows = $db->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            $cols = '`' . implode('`, `', array_keys($rows[0])) . '`';
            $sql .= "INSERT INTO `{$table}` ({$cols}) VALUES\n";
            $vals = [];
            foreach ($rows as $row) {
                $escaped = array_map(function($v) use ($db) {
                    if ($v === null) return 'NULL';
                    return "'" . str_replace(["\\","'","\n","\r"], ["\\\\","\\'","\\n","\\r"], $v) . "'";
                }, array_values($row));
                $vals[] = '(' . implode(', ', $escaped) . ')';
            }
            $sql .= implode(",\n", $vals) . ";\n\n";
        }
    }

    $sql .= "SET foreign_key_checks = 1;\n";

    $fname = 'cafe_backup_' . date('Ymd_His') . '.sql';
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    header('Content-Length: ' . strlen($sql));
    echo $sql;
    exit;
}

$pageTitle = 'النسخ الاحتياطي والنقل';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="row g-4">

  <!-- Export -->
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header">
        <i class="fa fa-download me-2 text-success"></i> تصدير قاعدة البيانات
      </div>
      <div class="card-body">
        <p class="text-muted mb-4" style="font-size:14px;line-height:2">
          اضغط الزر لتحميل ملف <code>.sql</code> يحتوي على كل البيانات
          (الأصناف، الطاولات، الموظفين، الأوردرات، المخزون، الإعدادات).
        </p>
        <a href="?export=1" class="btn btn-success btn-lg w-100">
          <i class="fa fa-database me-2"></i> تحميل نسخة احتياطية (.sql)
        </a>
        <div class="alert alert-info mt-3 mb-0" style="font-size:13px">
          <i class="fa fa-circle-info me-2"></i>
          احفظ الملف مع مجلد <code>cafe</code> معاً — هما اللي هتنقلهم للجهاز الجديد
        </div>
      </div>
    </div>
  </div>

  <!-- Instructions -->
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header">
        <i class="fa fa-circle-info me-2 text-primary"></i> خطوات التركيب على الجهاز الجديد
      </div>
      <div class="card-body p-0">
        <div class="list-group list-group-flush" style="font-size:14px">

          <div class="list-group-item">
            <div class="d-flex gap-3 align-items-start">
              <span class="badge bg-primary rounded-circle" style="width:28px;height:28px;line-height:20px;flex-shrink:0">1</span>
              <div>
                <strong>ثبّت XAMPP أو WAMP</strong><br>
                <span class="text-muted">وتأكد إن Apache + MySQL شغالين (أيقوناتهم خضراء)</span>
              </div>
            </div>
          </div>

          <div class="list-group-item">
            <div class="d-flex gap-3 align-items-start">
              <span class="badge bg-primary rounded-circle" style="width:28px;height:28px;line-height:20px;flex-shrink:0">2</span>
              <div>
                <strong>انسخ مجلد <code>cafe</code></strong><br>
                <span class="text-muted">
                  WAMP: <code>C:\wamp64\www\cafe\</code><br>
                  XAMPP: <code>C:\xampp\htdocs\cafe\</code>
                </span>
              </div>
            </div>
          </div>

          <div class="list-group-item">
            <div class="d-flex gap-3 align-items-start">
              <span class="badge bg-primary rounded-circle" style="width:28px;height:28px;line-height:20px;flex-shrink:0">3</span>
              <div>
                <strong>افتح phpMyAdmin</strong><br>
                <span class="text-muted">
                  من المتصفح: <code>localhost/phpmyadmin</code><br>
                  اضغط <strong>Import</strong> واختار ملف الـ <code>.sql</code>
                </span>
              </div>
            </div>
          </div>

          <div class="list-group-item">
            <div class="d-flex gap-3 align-items-start">
              <span class="badge bg-warning rounded-circle text-dark" style="width:28px;height:28px;line-height:20px;flex-shrink:0">4</span>
              <div>
                <strong>لو XAMPP: عدّل كلمة السر</strong><br>
                <span class="text-muted">
                  افتح <code>cafe/config/database.php</code><br>
                  غيّر <code>DB_PASS</code> لو عندك password على MySQL
                </span>
              </div>
            </div>
          </div>

          <div class="list-group-item">
            <div class="d-flex gap-3 align-items-start">
              <span class="badge bg-success rounded-circle" style="width:28px;height:28px;line-height:20px;flex-shrink:0">5</span>
              <div>
                <strong>افتح السيستيم</strong><br>
                <span class="text-muted">
                  <code>localhost/cafe/admin</code> — أدمن<br>
                  <code>localhost/cafe/cashier</code> — كاشير
                </span>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>

  <!-- Reset Data -->
  <div class="col-12">
    <div class="card" style="border:2px solid #ef4444">
      <div class="card-header" style="background:linear-gradient(135deg,#fef2f2,#fee2e2)">
        <i class="fa fa-triangle-exclamation me-2 text-danger"></i>
        <span style="color:#dc2626;font-weight:700">منطقة الخطر — تصفير السيستيم</span>
      </div>
      <div class="card-body">
        <p class="mb-3" style="font-size:14px;line-height:2">
          هذا الإجراء سيمسح <strong>جميع بيانات التشغيل</strong> ويرجع السيستيم لبداية جديدة:
        </p>
        <div class="row g-2 mb-4">
          <div class="col-6 col-md-3">
            <div class="d-flex align-items-center gap-2 p-2 rounded" style="background:#fef2f2;border:1px solid #fecaca">
              <i class="fa fa-trash text-danger"></i>
              <span style="font-size:13px">جميع الأوردرات</span>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="d-flex align-items-center gap-2 p-2 rounded" style="background:#fef2f2;border:1px solid #fecaca">
              <i class="fa fa-trash text-danger"></i>
              <span style="font-size:13px">جميع الشيفتات</span>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="d-flex align-items-center gap-2 p-2 rounded" style="background:#fef2f2;border:1px solid #fecaca">
              <i class="fa fa-trash text-danger"></i>
              <span style="font-size:13px">حركات المخزون</span>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="d-flex align-items-center gap-2 p-2 rounded" style="background:#fef2f2;border:1px solid #fecaca">
              <i class="fa fa-trash text-danger"></i>
              <span style="font-size:13px">طلبات التوريد</span>
            </div>
          </div>
        </div>
        <div class="row g-2 mb-4">
          <div class="col-6 col-md-3">
            <div class="d-flex align-items-center gap-2 p-2 rounded" style="background:#f0fdf4;border:1px solid #bbf7d0">
              <i class="fa fa-circle-check text-success"></i>
              <span style="font-size:13px">الأصناف والفئات</span>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="d-flex align-items-center gap-2 p-2 rounded" style="background:#f0fdf4;border:1px solid #bbf7d0">
              <i class="fa fa-circle-check text-success"></i>
              <span style="font-size:13px">الموظفون والصلاحيات</span>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="d-flex align-items-center gap-2 p-2 rounded" style="background:#f0fdf4;border:1px solid #bbf7d0">
              <i class="fa fa-circle-check text-success"></i>
              <span style="font-size:13px">المخزون وكمياته</span>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="d-flex align-items-center gap-2 p-2 rounded" style="background:#f0fdf4;border:1px solid #bbf7d0">
              <i class="fa fa-circle-check text-success"></i>
              <span style="font-size:13px">الإعدادات والطاولات</span>
            </div>
          </div>
        </div>
        <div class="alert alert-danger mb-4" style="font-size:13px">
          <i class="fa fa-circle-exclamation me-2"></i>
          <strong>لا يمكن التراجع عن هذا الإجراء.</strong>
          تأكد من أخذ نسخة احتياطية أولاً قبل المتابعة.
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <button class="btn btn-danger btn-lg" onclick="confirmReset()">
            <i class="fa fa-rotate-left me-2"></i> تصفير السيستيم
          </button>
          <button class="btn btn-outline-danger btn-lg" onclick="confirmResetInventory()">
            <i class="fa fa-boxes-stacked me-2"></i> مسح المخزون كاملاً
          </button>
          <button class="btn btn-outline-danger btn-lg" onclick="confirmResetMenu()">
            <i class="fa fa-utensils me-2"></i> مسح الفئات والأصناف
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- DB Info -->
  <div class="col-12">
    <div class="card">
      <div class="card-header"><i class="fa fa-circle-info me-2"></i> معلومات الاتصال الحالية</div>
      <div class="card-body">
        <div class="row g-3 text-center">
          <?php
          $db = getDB();
          $tableCount = count($db->query("SHOW TABLES")->fetchAll());
          $orderCount = (int)$db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
          $itemCount  = (int)$db->query("SELECT COUNT(*) FROM items WHERE is_active=1")->fetchColumn();
          $userCount  = (int)$db->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn();
          ?>
          <div class="col-6 col-md-3">
            <div class="stat-card">
              <div class="stat-icon blue"><i class="fa fa-database"></i></div>
              <div><div class="stat-value"><?= DB_NAME ?></div><div class="stat-label">اسم قاعدة البيانات</div></div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="stat-card">
              <div class="stat-icon teal"><i class="fa fa-table"></i></div>
              <div><div class="stat-value"><?= $tableCount ?></div><div class="stat-label">جداول</div></div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="stat-card">
              <div class="stat-icon green"><i class="fa fa-receipt"></i></div>
              <div><div class="stat-value"><?= $orderCount ?></div><div class="stat-label">أوردر مسجل</div></div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="stat-card">
              <div class="stat-icon orange"><i class="fa fa-utensils"></i></div>
              <div><div class="stat-value"><?= $itemCount ?></div><div class="stat-label">صنف في المنيو</div></div>
            </div>
          </div>
        </div>

        <div class="alert alert-warning mt-3 mb-0" style="font-size:13px">
          <i class="fa fa-triangle-exclamation me-2"></i>
          <strong>config/database.php:</strong>
          Host = <code><?= DB_HOST ?></code> |
          User = <code><?= DB_USER ?></code> |
          Password = <code><?= DB_PASS === '' ? '(فارغة)' : '***' ?></code> |
          Database = <code><?= DB_NAME ?></code>
        </div>
      </div>
    </div>
  </div>

</div>

<script>
function confirmReset() {
    Swal.fire({
        title: 'تصفير السيستيم',
        html: `<p class="mb-3 text-danger fw-bold">هذا الإجراء لا يمكن التراجع عنه!</p>
               <p class="mb-3">سيتم مسح جميع الأوردرات والشيفتات وحركات المخزون.</p>
               <label class="form-label fw-bold w-100 text-end">أدخل كلمة مرور الأدمن للتأكيد</label>
               <input id="swal-pass" type="password" class="form-control" dir="ltr" style="text-align:left" placeholder="Password">`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'نعم، صفّر السيستيم',
        cancelButtonText: 'إلغاء',
        focusConfirm: false,
        preConfirm: function() {
            var pass = document.getElementById('swal-pass').value.trim();
            if (!pass) { Swal.showValidationMessage('كلمة المرور مطلوبة'); return false; }
            return pass;
        }
    }).then(function(r) {
        if (!r.isConfirmed) return;
        Swal.fire({ title: 'جاري التصفير...', allowOutsideClick: false, didOpen: function(){ Swal.showLoading(); } });
        $.post('', { action: 'reset_data', password: r.value }, function(res) {
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'تم بنجاح', text: res.message, confirmButtonText: 'حسناً' })
                    .then(function(){ location.reload(); });
            } else {
                Swal.fire('خطأ', res.message, 'error');
            }
        }, 'json').fail(function(){
            Swal.fire('خطأ', 'حدث خطأ في الاتصال', 'error');
        });
    });
}

function confirmResetMenu() {
    Swal.fire({
        title: 'مسح الفئات والأصناف',
        html: `<p class="mb-3 text-danger fw-bold">سيتم مسح جميع الفئات والأصناف من المنيو!</p>
               <p class="mb-3">الأوردرات القديمة لن تتأثر لأن أسماء الأصناف محفوظة فيها.</p>
               <label class="form-label fw-bold w-100 text-end">أدخل كلمة مرور الأدمن للتأكيد</label>
               <input id="swal-pass3" type="password" class="form-control" dir="ltr" style="text-align:left" placeholder="Password">`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'نعم، امسح المنيو',
        cancelButtonText: 'إلغاء',
        focusConfirm: false,
        preConfirm: function() {
            var pass = document.getElementById('swal-pass3').value.trim();
            if (!pass) { Swal.showValidationMessage('كلمة المرور مطلوبة'); return false; }
            return pass;
        }
    }).then(function(r) {
        if (!r.isConfirmed) return;
        Swal.fire({ title: 'جاري المسح...', allowOutsideClick: false, didOpen: function(){ Swal.showLoading(); } });
        $.post('', { action: 'reset_menu', password: r.value }, function(res) {
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'تم بنجاح', text: res.message, confirmButtonText: 'حسناً' })
                    .then(function(){ location.reload(); });
            } else {
                Swal.fire('خطأ', res.message, 'error');
            }
        }, 'json').fail(function(){ Swal.fire('خطأ', 'حدث خطأ في الاتصال', 'error'); });
    });
}

function confirmResetInventory() {
    Swal.fire({
        title: 'مسح المخزون كاملاً',
        html: `<p class="mb-3 text-danger fw-bold">سيتم مسح جميع أصناف المخزون وحركاته!</p>
               <p class="mb-3">وسيتم إلغاء ربط الأصناف بالمخزون تلقائياً.</p>
               <label class="form-label fw-bold w-100 text-end">أدخل كلمة مرور الأدمن للتأكيد</label>
               <input id="swal-pass2" type="password" class="form-control" dir="ltr" style="text-align:left" placeholder="Password">`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'نعم، امسح المخزون',
        cancelButtonText: 'إلغاء',
        focusConfirm: false,
        preConfirm: function() {
            var pass = document.getElementById('swal-pass2').value.trim();
            if (!pass) { Swal.showValidationMessage('كلمة المرور مطلوبة'); return false; }
            return pass;
        }
    }).then(function(r) {
        if (!r.isConfirmed) return;
        Swal.fire({ title: 'جاري المسح...', allowOutsideClick: false, didOpen: function(){ Swal.showLoading(); } });
        $.post('', { action: 'reset_inventory', password: r.value }, function(res) {
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'تم بنجاح', text: res.message, confirmButtonText: 'حسناً' })
                    .then(function(){ location.reload(); });
            } else {
                Swal.fire('خطأ', res.message, 'error');
            }
        }, 'json').fail(function(){ Swal.fire('خطأ', 'حدث خطأ في الاتصال', 'error'); });
    });
}
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
