<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle ?? 'كاشير') ?> — <?= appName() ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</head>
<body class="cashier-layout">

<!-- Sidebar -->
<nav class="sidebar cashier-sidebar" id="sidebar">
  <div class="sidebar-brand">
    <i class="fa fa-mug-hot"></i>
    <span><?= appName() ?></span>
  </div>
  <div class="sidebar-user">
    <div class="su-avatar cashier-avatar">
      <?= mb_substr($_SESSION['user_name'] ?? 'K', 0, 1) ?>
    </div>
    <div class="su-info">
      <div class="su-name"><?= e($_SESSION['user_name'] ?? '') ?></div>
      <div class="su-role">
        <?php $shift = getActiveShift(); ?>
        <?php if ($shift): ?>
          <span class="badge bg-success">شيفت نشط</span>
        <?php else: ?>
          <span class="badge bg-secondary">لا يوجد شيفت</span>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <ul class="sidebar-nav">
    <li class="nav-section">القائمة الرئيسية</li>
    <li><a href="<?= BASE_URL ?>/cashier/" class="<?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">
      <i class="fa fa-house"></i><span>الرئيسية</span></a></li>
    <li><a href="<?= BASE_URL ?>/cashier/tables.php" class="<?= basename($_SERVER['PHP_SELF']) === 'tables.php' ? 'active' : '' ?>">
      <i class="fa fa-table-cells-large"></i><span>الطاولات</span></a></li>

    <li><a href="<?= BASE_URL ?>/cashier/stock.php" class="<?= basename($_SERVER['PHP_SELF']) === 'stock.php' ? 'active' : '' ?>">
      <i class="fa fa-boxes-stacked"></i><span>المخزون</span></a></li>

    <li class="nav-section">الشيفت</li>
    <li><a href="<?= BASE_URL ?>/cashier/shift.php" class="<?= basename($_SERVER['PHP_SELF']) === 'shift.php' ? 'active' : '' ?>">
      <i class="fa fa-clock-rotate-left"></i><span>تفاصيل الشيفت</span></a></li>

    <li class="nav-section">الحساب</li>
    <li><a href="<?= BASE_URL ?>/logout.php" class="text-danger-menu">
      <i class="fa fa-right-from-bracket"></i><span>تسجيل الخروج</span></a></li>
  </ul>
</nav>

<!-- Main Wrapper -->
<div class="main-wrapper">
  <header class="top-navbar">
    <button class="sidebar-toggle" id="sidebarToggle"><i class="fa fa-bars"></i></button>
    <div class="navbar-title"><?= e($pageTitle ?? 'الكاشير') ?></div>
    <div class="navbar-actions" style="gap:12px">

      <!-- Date + live clock -->
      <div style="text-align:right;line-height:1.25">
        <div style="font-size:11px;font-weight:700;color:var(--accent)" id="ndt-day"></div>
        <div style="font-size:11px;color:var(--text-muted);font-weight:600" id="ndt-date"></div>
        <div style="font-size:17px;font-weight:900;color:var(--text);
                    font-variant-numeric:tabular-nums;letter-spacing:.5px"
             id="ndt-time"></div>
      </div>

      <?php
      $shiftElapsedSec = 0;
      if (!empty($shift)) {
          $row = fetchOne("SELECT TIMESTAMPDIFF(SECOND, ?, NOW()) as s", [$shift['check_in']]);
          $shiftElapsedSec = max(0, (int)($row['s'] ?? 0));
      }
      ?>
      <?php if (!empty($shift)): ?>
      <div style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);
                  border:1.5px solid #86efac;border-radius:12px;
                  padding:5px 14px;text-align:center;min-width:80px">
        <div style="font-size:10px;color:#64748b;font-weight:600">مدة الشيفت</div>
        <div style="font-size:18px;font-weight:900;color:#16a34a;
                    font-variant-numeric:tabular-nums;letter-spacing:1px"
             id="shift-elapsed" data-sec="<?= $shiftElapsedSec ?>">00:00</div>
      </div>
      <?php endif; ?>

    </div>

<script>
(function() {
  var DAYS_AR = ['الأحد','الاثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت'];
  var MONTHS  = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

  var shiftEl  = document.getElementById('shift-elapsed');
  var shiftSec = shiftEl ? parseInt(shiftEl.dataset.sec, 10) : 0;
  var loadedAt = Date.now();

  function pad(n) { return n < 10 ? '0' + n : n; }

  function tick() {
    var now = new Date();

    // Day + date + time
    var dayEl  = document.getElementById('ndt-day');
    var dateEl = document.getElementById('ndt-date');
    var timeEl = document.getElementById('ndt-time');
    if (dayEl)  dayEl.textContent  = DAYS_AR[now.getDay()];
    if (dateEl) dateEl.textContent = MONTHS[now.getMonth()] + ' ' + now.getDate() + ', ' + now.getFullYear();
    if (timeEl) {
      var h  = now.getHours();
      var ap = h >= 12 ? 'PM' : 'AM';
      h = h % 12 || 12;
      timeEl.textContent = pad(h) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds()) + ' ' + ap;
    }

    // Shift elapsed (based on server-calc + time since page load)
    if (shiftEl) {
      var total = shiftSec + Math.floor((Date.now() - loadedAt) / 1000);
      var hh = Math.floor(total / 3600);
      var mm = Math.floor((total % 3600) / 60);
      var ss = total % 60;
      shiftEl.textContent = pad(hh) + ':' + pad(mm) + ':' + pad(ss);
    }
  }

  tick();
  setInterval(tick, 1000);
})();
</script>
  </header>
  <main class="page-content">
