<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $db   = getDB();
        $stmt = $db->prepare('SELECT * FROM users WHERE username=? AND is_active=1 LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            loginUser($user);
            if ($user['role'] === 'admin') {
                header('Location: ' . BASE_URL . '/admin/');
            } else {
                header('Location: ' . BASE_URL . '/cashier/');
            }
            exit;
        }
        $error = 'اسم المستخدم أو كلمة المرور غير صحيحة';
    } else {
        $error = 'يرجى إدخال اسم المستخدم وكلمة المرور';
    }
}

$db         = getDB();
$adminExists = (int) $db->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
$cafeName   = appName();
$cafeAddr   = getSetting('cafe_address', '');
$cafePhone  = getSetting('cafe_phone', '');

// اللوجو: المرفوع أولاً، ثم SVG الافتراضي
$uploadedLogo = getSetting('cafe_logo', '');
$svgDefault   = BASE_URL . '/assets/img/cafe_logo.svg';
$cafeLogo     = $uploadedLogo ?: $svgDefault;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>تسجيل الدخول — <?= e($cafeName) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800;900&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'Cairo', sans-serif;
  min-height: 100vh;
  display: flex;
  background: #0f172a;
}

/* ── Left panel ── */
.login-left {
  width: 45%;
  min-height: 100vh;
  background: linear-gradient(160deg, #1e3a5f 0%, #0f172a 40%, #1a1a2e 100%);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 48px;
  position: relative;
  overflow: hidden;
}

/* decorative circles */
.login-left::before {
  content: '';
  position: absolute;
  width: 380px; height: 380px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(245,158,11,.12) 0%, transparent 70%);
  top: -80px; right: -80px;
}
.login-left::after {
  content: '';
  position: absolute;
  width: 280px; height: 280px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(245,158,11,.08) 0%, transparent 70%);
  bottom: -60px; left: -60px;
}

.brand-logo {
  margin-bottom: 32px;
  text-align: center;
  position: relative;
  z-index: 1;
}
.brand-logo-img-wrap {
  background: rgba(255,255,255,.95);
  border-radius: 20px;
  padding: 14px 24px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 4px;
  box-shadow: 0 4px 24px rgba(0,0,0,.25);
}
.brand-logo img {
  width: 150px;
  height: 80px;
  object-fit: contain;
  display: block;
}
.brand-logo .icon-fallback {
  width: 90px; height: 90px;
  border-radius: 24px;
  background: linear-gradient(135deg, #f59e0b, #d97706);
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto;
  box-shadow: 0 8px 32px rgba(245,158,11,.35);
}
.brand-logo .icon-fallback i { font-size: 40px; color: #fff; }

.brand-name {
  font-size: 32px;
  font-weight: 900;
  color: #fff;
  margin-top: 20px;
  text-align: center;
  position: relative;
  z-index: 1;
  letter-spacing: -0.5px;
}
.brand-tagline {
  color: rgba(255,255,255,.55);
  font-size: 14px;
  text-align: center;
  margin-top: 8px;
  position: relative;
  z-index: 1;
}

.brand-divider {
  width: 60px; height: 3px;
  background: linear-gradient(90deg, #f59e0b, #d97706);
  border-radius: 2px;
  margin: 28px auto;
  position: relative;
  z-index: 1;
}

.feature-list {
  list-style: none;
  padding: 0;
  width: 100%;
  max-width: 280px;
  position: relative;
  z-index: 1;
}
.feature-list li {
  display: flex;
  align-items: center;
  gap: 12px;
  color: rgba(255,255,255,.7);
  font-size: 13px;
  padding: 9px 0;
  border-bottom: 1px solid rgba(255,255,255,.06);
}
.feature-list li:last-child { border-bottom: none; }
.feature-list li .fi {
  width: 32px; height: 32px;
  border-radius: 8px;
  background: rgba(245,158,11,.15);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.feature-list li .fi i { color: #f59e0b; font-size: 13px; }

.brand-footer {
  position: absolute;
  bottom: 24px;
  color: rgba(255,255,255,.25);
  font-size: 11px;
  z-index: 1;
}

/* ── Right panel ── */
.login-right {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px 32px;
  background: #f8fafc;
}

.login-form-box {
  width: 100%;
  max-width: 400px;
  animation: fadeUp .5s ease;
}

@keyframes fadeUp {
  from { opacity: 0; transform: translateY(24px); }
  to   { opacity: 1; transform: translateY(0); }
}

.login-form-box h2 {
  font-size: 26px;
  font-weight: 800;
  color: #0f172a;
  margin-bottom: 6px;
}
.login-form-box .subtitle {
  color: #64748b;
  font-size: 13px;
  margin-bottom: 32px;
}

.form-label {
  font-weight: 600;
  color: #374151;
  font-size: 13px;
  margin-bottom: 6px;
}
.input-group-text {
  background: #f1f5f9;
  border-color: #e2e8f0;
  color: #64748b;
}
.form-control {
  border-color: #e2e8f0;
  background: #fff;
  font-family: 'Cairo', sans-serif;
  font-size: 14px;
  padding: 11px 14px;
}
.form-control:focus {
  border-color: #f59e0b;
  box-shadow: 0 0 0 3px rgba(245,158,11,.12);
}
.form-control-lg { font-size: 15px; }

.btn-login {
  background: linear-gradient(135deg, #f59e0b, #d97706);
  border: none;
  color: #fff;
  font-family: 'Cairo', sans-serif;
  font-size: 16px;
  font-weight: 700;
  padding: 14px;
  border-radius: 12px;
  width: 100%;
  margin-top: 8px;
  transition: all .25s;
  box-shadow: 0 4px 16px rgba(245,158,11,.35);
}
.btn-login:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(245,158,11,.45);
  color: #fff;
}
.btn-login:active { transform: translateY(0); }

.alert { border-radius: 10px; font-size: 13px; }
.alert-danger { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
.alert-warning { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }

.login-copy {
  text-align: center;
  color: #94a3b8;
  font-size: 11px;
  margin-top: 32px;
}

/* ── Mobile ── */
@media (max-width: 768px) {
  body { flex-direction: column; }
  .login-left {
    width: 100%;
    min-height: auto;
    padding: 40px 24px 32px;
  }
  .login-left::before, .login-left::after { display: none; }
  .feature-list { display: none; }
  .brand-footer { position: static; margin-top: 20px; }
  .login-right { padding: 32px 20px; background: #f8fafc; }
}
</style>
</head>
<body>

<!-- Left Panel -->
<div class="login-left">
  <div class="brand-logo">
    <?php if ($cafeLogo): ?>
    <div class="brand-logo-img-wrap" id="logoWrap">
      <img src="<?= e($cafeLogo) ?>?v=<?= time() ?>" alt="<?= e($cafeName) ?>"
           onerror="document.getElementById('logoWrap').style.display='none';document.getElementById('iconFallback').style.display='flex'">
    </div>
    <div class="icon-fallback" id="iconFallback" style="display:none"><i class="fa fa-mug-hot"></i></div>
    <?php else: ?>
    <div class="icon-fallback"><i class="fa fa-mug-hot"></i></div>
    <?php endif; ?>
  </div>

  <div class="brand-name"><?= e($cafeName) ?></div>
  <div class="brand-tagline">نظام إدارة الكافيه المتكامل</div>
  <div class="brand-divider"></div>

  <ul class="feature-list">
    <li>
      <div class="fi"><i class="fa fa-table-cells-large"></i></div>
      <span>إدارة الطاولات والطلبات بسهولة</span>
    </li>
    <li>
      <div class="fi"><i class="fa fa-boxes-stacked"></i></div>
      <span>متابعة المخزون وطلبات التوريد</span>
    </li>
    <li>
      <div class="fi"><i class="fa fa-chart-bar"></i></div>
      <span>تقارير الشيفتات والمبيعات</span>
    </li>
    <li>
      <div class="fi"><i class="fa fa-receipt"></i></div>
      <span>طباعة فواتير مباشرة للعميل</span>
    </li>
    <li>
      <div class="fi"><i class="fa fa-users"></i></div>
      <span>إدارة الكاشيرز والصلاحيات</span>
    </li>
  </ul>

  <?php if ($cafePhone || $cafeAddr): ?>
  <div class="brand-footer">
    <?= $cafePhone ? e($cafePhone) : '' ?>
    <?= ($cafePhone && $cafeAddr) ? ' — ' : '' ?>
    <?= $cafeAddr ? e($cafeAddr) : '' ?>
  </div>
  <?php endif; ?>
</div>

<!-- Right Panel -->
<div class="login-right">
  <div class="login-form-box">

    <h2>مرحباً بك 👋</h2>
    <p class="subtitle">سجّل دخولك للمتابعة</p>

    <?php if (!$adminExists): ?>
    <div class="alert alert-warning mb-4">
      <i class="fa fa-triangle-exclamation me-2"></i>
      لم يتم إعداد النظام بعد.
      <a href="<?= BASE_URL ?>/setup.php" class="fw-bold">إعداد النظام الآن</a>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger mb-3" id="errAlert">
      <i class="fa fa-circle-exclamation me-2"></i><?= e($error) ?>
    </div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <div class="mb-3">
        <label class="form-label">اسم المستخدم</label>
        <div class="input-group">
          <span class="input-group-text"><i class="fa fa-user"></i></span>
          <input type="text" name="username" class="form-control form-control-lg"
                 value="<?= e($_POST['username'] ?? '') ?>"
                 placeholder="أدخل اسم المستخدم" autofocus required>
        </div>
      </div>
      <div class="mb-4">
        <label class="form-label">كلمة المرور</label>
        <div class="input-group">
          <span class="input-group-text"><i class="fa fa-lock"></i></span>
          <input type="password" name="password" id="passInput"
                 class="form-control form-control-lg"
                 placeholder="أدخل كلمة المرور" required>
          <button type="button" class="input-group-text" onclick="togglePass()" style="cursor:pointer">
            <i class="fa fa-eye" id="eyeIcon"></i>
          </button>
        </div>
      </div>
      <button type="submit" class="btn-login">
        <i class="fa fa-right-to-bracket me-2"></i> تسجيل الدخول
      </button>
    </form>

    <div class="login-copy">
      <?= e($cafeName) ?> &copy; <?= date('Y') ?> — جميع الحقوق محفوظة
    </div>
  </div>
</div>

<script>
function togglePass() {
  var inp = document.getElementById('passInput');
  var ico = document.getElementById('eyeIcon');
  if (inp.type === 'password') {
    inp.type = 'text';
    ico.className = 'fa fa-eye-slash';
  } else {
    inp.type = 'password';
    ico.className = 'fa fa-eye';
  }
}
// Auto-dismiss error alert
var ea = document.getElementById('errAlert');
if (ea) setTimeout(function(){ ea.style.transition='opacity .5s'; ea.style.opacity='0'; setTimeout(function(){ea.remove();},500); }, 4000);
</script>
</body>
</html>
