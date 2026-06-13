<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$db    = getDB();
$shift = getActiveShift();

// Handle check-in
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'checkin' && !$shift) {
        $db->prepare('INSERT INTO shifts(user_id,check_in) VALUES(?,NOW())')
           ->execute([$_SESSION['user_id']]);
        $_SESSION['shift_just_started'] = true;
        header('Location: ' . BASE_URL . '/cashier/');
        exit;
    }
}

// Today stats for this user
$myStats = fetchOne("
    SELECT COUNT(*) as orders, COALESCE(SUM(o.final_total),0) as sales
    FROM orders o
    WHERE o.user_id=? AND o.status='closed' AND DATE(o.closed_at)=CURDATE()
", [$_SESSION['user_id']]);

$shiftStats = null;
if ($shift) {
    $shiftStats = fetchOne("
        SELECT COUNT(*) as orders, COALESCE(SUM(final_total),0) as sales
        FROM orders WHERE shift_id=? AND status='closed'
    ", [$shift['id']]);
}

$tableStats = getTableStats();
$pageTitle  = 'الرئيسية';
require_once __DIR__ . '/../includes/cashier_header.php';
?>

<?php if (!$shift): ?>
<!-- CHECK-IN SCREEN -->
<div class="row justify-content-center mt-5">
  <div class="col-md-6 col-lg-5">
    <div class="card text-center shadow-lg border-0">
      <div class="card-body py-5 px-4">
        <div style="font-size:80px;margin-bottom:16px">⏰</div>
        <h3 class="fw-bold mb-2">مرحباً، <?= e($_SESSION['user_name']) ?></h3>
        <p class="text-muted mb-4">اضغط على زر تسجيل الحضور لبدء شيفتك</p>
        <div class="mb-4 text-muted">
          <i class="fa fa-calendar me-2"></i>
          <?= date('l، d F Y', time()) ?>
          <br>
          <i class="fa fa-clock me-2"></i>
          <span id="live-clock2" style="font-size:24px;font-weight:700;color:#f59e0b"></span>
        </div>
        <form method="post">
          <input type="hidden" name="action" value="checkin">
          <button type="submit" class="btn btn-accent btn-lg px-5 py-3" style="font-size:18px">
            <i class="fa fa-fingerprint me-2"></i> تسجيل الحضور
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
<script>
setInterval(() => {
  document.getElementById('live-clock2').textContent =
    new Date().toLocaleTimeString('ar-EG',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
}, 1000);
</script>

<?php else: ?>
<!-- DASHBOARD WITH ACTIVE SHIFT -->

<?php if (!empty($_SESSION['shift_just_started'])): unset($_SESSION['shift_just_started']); ?>
<div class="alert alert-success d-flex align-items-center mb-4" id="shiftAlert"
     style="transition:opacity .6s ease">
  <i class="fa fa-circle-check fa-lg me-3"></i>
  <div>
    <strong>تم تسجيل الحضور بنجاح</strong> — الشيفت بدأ الساعة
    <strong><?= date('h:i A', strtotime($shift['check_in'])) ?></strong>
  </div>
</div>
<script>
  setTimeout(function() {
    var a = document.getElementById('shiftAlert');
    if (a) { a.style.opacity = '0'; setTimeout(function(){ a.remove(); }, 650); }
  }, 4000);
</script>
<?php endif; ?>

<!-- Stats -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon green"><i class="fa fa-circle-check"></i></div>
      <div><div class="stat-value"><?= $tableStats['available'] ?></div><div class="stat-label">طاولات متاحة</div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon red"><i class="fa fa-users"></i></div>
      <div><div class="stat-value"><?= $tableStats['occupied'] ?></div><div class="stat-label">مشغولة</div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon blue"><i class="fa fa-receipt"></i></div>
      <div><div class="stat-value"><?= $shiftStats['orders'] ?? 0 ?></div><div class="stat-label">طلبات الشيفت</div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon amber"><i class="fa fa-coins"></i></div>
      <div><div class="stat-value" style="font-size:16px"><?= money((float)($shiftStats['sales'] ?? 0)) ?></div><div class="stat-label">مبيعات الشيفت</div></div>
    </div>
  </div>
</div>

<!-- Quick Actions -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <a href="<?= BASE_URL ?>/cashier/tables.php" class="card text-center p-4 text-decoration-none hover-card">
      <div style="font-size:48px">🪑</div>
      <div class="fw-bold mt-2 fs-5">إدارة الطاولات</div>
      <div class="text-muted small">فتح طاولة وإضافة طلبات</div>
    </a>
  </div>
  <div class="col-md-4">
    <a href="<?= BASE_URL ?>/cashier/stock.php" class="card text-center p-4 text-decoration-none hover-card">
      <div style="font-size:48px">📦</div>
      <div class="fw-bold mt-2 fs-5">المخزون</div>
      <div class="text-muted small">عرض وتحديث المخزون</div>
    </a>
  </div>
  <div class="col-md-4">
    <a href="<?= BASE_URL ?>/cashier/shift.php" class="card text-center p-4 text-decoration-none hover-card">
      <div style="font-size:48px">📊</div>
      <div class="fw-bold mt-2 fs-5">تفاصيل الشيفت</div>
      <div class="text-muted small">عرض وإنهاء الشيفت</div>
    </a>
  </div>
</div>

<style>
.hover-card { transition: transform .2s, box-shadow .2s; color: inherit; }
.hover-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,.1); color: inherit; }
</style>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/cashier_footer.php'; ?>
