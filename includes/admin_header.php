<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle ?? 'لوحة التحكم') ?> — <?= appName() ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</head>
<body class="admin-layout">

<!-- Sidebar -->
<nav class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <i class="fa fa-mug-hot"></i>
    <span><?= appName() ?></span>
  </div>
  <div class="sidebar-user">
    <div class="su-avatar"><i class="fa fa-user-shield"></i></div>
    <div class="su-info">
      <div class="su-name"><?= e($_SESSION['user_name'] ?? '') ?></div>
      <div class="su-role">مدير النظام</div>
    </div>
  </div>
  <ul class="sidebar-nav">
    <li class="nav-section">الرئيسية</li>
    <li><a href="<?= BASE_URL ?>/admin/" class="<?= basename($_SERVER['PHP_SELF']) === 'index.php' && strpos($_SERVER['PHP_SELF'], 'admin') !== false ? 'active' : '' ?>">
      <i class="fa fa-chart-pie"></i><span>لوحة التحكم</span></a></li>

    <li class="nav-section">إدارة المنيو</li>
    <li><a href="<?= BASE_URL ?>/admin/categories.php" class="<?= basename($_SERVER['PHP_SELF']) === 'categories.php' ? 'active' : '' ?>">
      <i class="fa fa-tags"></i><span>الفئات</span></a></li>
    <li><a href="<?= BASE_URL ?>/admin/items.php" class="<?= basename($_SERVER['PHP_SELF']) === 'items.php' ? 'active' : '' ?>">
      <i class="fa fa-utensils"></i><span>الأصناف</span></a></li>
    <li><a href="<?= BASE_URL ?>/admin/import_items.php" class="<?= basename($_SERVER['PHP_SELF']) === 'import_items.php' ? 'active' : '' ?>">
      <i class="fa fa-file-import"></i><span>استيراد Excel</span></a></li>

    <li class="nav-section">إدارة النظام</li>
    <li><a href="<?= BASE_URL ?>/admin/tables.php" class="<?= basename($_SERVER['PHP_SELF']) === 'tables.php' ? 'active' : '' ?>">
      <i class="fa fa-table-cells-large"></i><span>الطاولات</span></a></li>
    <li><a href="<?= BASE_URL ?>/admin/employees.php" class="<?= basename($_SERVER['PHP_SELF']) === 'employees.php' ? 'active' : '' ?>">
      <i class="fa fa-users"></i><span>الموظفون</span></a></li>
    <?php
    $__lowStock   = getLowStockCount();
    $__pendingReq = getPendingStockRequests();
    ?>
    <li><a href="<?= BASE_URL ?>/admin/stock.php" class="<?= basename($_SERVER['PHP_SELF']) === 'stock.php' ? 'active' : '' ?>">
      <i class="fa fa-boxes-stacked"></i><span>المخزون</span>
      <?php if ($__pendingReq > 0): ?>
      <span class="badge ms-auto" style="background:#f59e0b;color:#fff" title="طلبات توريد معلقة"><?= $__pendingReq ?></span>
      <?php elseif ($__lowStock > 0): ?>
      <span class="badge bg-danger ms-auto"><?= $__lowStock ?></span>
      <?php endif; ?>
    </a></li>
    <li><a href="<?= BASE_URL ?>/admin/import_inventory.php" class="<?= basename($_SERVER['PHP_SELF']) === 'import_inventory.php' ? 'active' : '' ?>">
      <i class="fa fa-file-import"></i><span>استيراد Excel مخزون</span></a></li>

    <li class="nav-section">التقارير</li>
    <li><a href="<?= BASE_URL ?>/admin/reports.php" class="<?= basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : '' ?>">
      <i class="fa fa-chart-bar"></i><span>التقارير</span></a></li>

    <li class="nav-section">النظام</li>
    <li><a href="<?= BASE_URL ?>/admin/settings.php" class="<?= basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : '' ?>">
      <i class="fa fa-gear"></i><span>الإعدادات</span></a></li>
    <li><a href="<?= BASE_URL ?>/admin/backup.php" class="<?= basename($_SERVER['PHP_SELF']) === 'backup.php' ? 'active' : '' ?>">
      <i class="fa fa-database"></i><span>النسخ الاحتياطي</span></a></li>

    <li class="nav-section">الحساب</li>
    <li><a href="<?= BASE_URL ?>/logout.php" class="text-danger-menu">
      <i class="fa fa-right-from-bracket"></i><span>تسجيل الخروج</span></a></li>
  </ul>
</nav>

<!-- Main Wrapper -->
<div class="main-wrapper">
  <!-- Top Navbar -->
  <header class="top-navbar">
    <button class="sidebar-toggle" id="sidebarToggle"><i class="fa fa-bars"></i></button>
    <div class="navbar-title"><?= e($pageTitle ?? 'لوحة التحكم') ?></div>
    <div class="navbar-actions">
      <span class="badge-pill">
        <i class="fa fa-clock"></i>
        <span id="live-clock"></span>
      </span>
      <?php $ts = getTableStats(); ?>
      <span class="badge-pill text-success">
        <i class="fa fa-circle"></i> <?= $ts['available'] ?> متاحة
      </span>
      <span class="badge-pill text-danger">
        <i class="fa fa-circle"></i> <?= $ts['occupied'] ?> مشغولة
      </span>
    </div>
  </header>

  <!-- Page Content -->
  <main class="page-content">
