<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
runMigrations();

$pageTitle = 'الإعدادات';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<style>
.settings-tabs{display:flex;gap:8px;margin-bottom:24px;border-bottom:2px solid #e2e8f0;padding-bottom:0}
.st-tab{padding:10px 22px;border-radius:12px 12px 0 0;border:none;background:transparent;font-family:'Cairo',sans-serif;font-size:14px;font-weight:700;color:#64748b;cursor:pointer;transition:all .18s;border-bottom:3px solid transparent;margin-bottom:-2px}
.st-tab.active{color:#f59e0b;border-bottom-color:#f59e0b;background:#fffbeb}
.settings-section{display:none}.settings-section.active{display:block}
.setting-group{background:#fff;border-radius:16px;padding:24px;margin-bottom:20px;border:1px solid #e2e8f0}
.setting-group h6{font-size:15px;font-weight:800;color:#0f172a;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid #f1f5f9}
.form-label{font-weight:700;font-size:13px;color:#334155}
.toggle-switch{display:flex;align-items:center;gap:12px}
.toggle-switch input[type=checkbox]{width:44px;height:24px;appearance:none;background:#e2e8f0;border-radius:99px;cursor:pointer;transition:background .2s;position:relative;flex-shrink:0}
.toggle-switch input[type=checkbox]:checked{background:#22c55e}
.toggle-switch input[type=checkbox]::after{content:'';position:absolute;top:3px;right:3px;width:18px;height:18px;border-radius:50%;background:#fff;transition:right .2s;box-shadow:0 1px 3px rgba(0,0,0,.2)}
.toggle-switch input[type=checkbox]:checked::after{right:23px}
.toggle-label{font-size:14px;font-weight:700;color:#334155}
.pct-input{display:flex;align-items:center;gap:8px}
.pct-input input{width:90px}
.pct-input span{font-size:14px;color:#64748b;font-weight:700}
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h5 class="fw-bold mb-0">الإعدادات</h5>
    <small class="text-muted">ضبط إعدادات الكافيه والفاتورة والضرائب</small>
  </div>
  <button class="btn btn-accent" onclick="saveSettings()">
    <i class="fa fa-save me-2"></i>حفظ الإعدادات
  </button>
</div>

<!-- Tabs -->
<div class="settings-tabs">
  <button class="st-tab active" onclick="switchTab('cafe',this)"><i class="fa fa-store me-2"></i>بيانات الكافيه</button>
  <button class="st-tab" onclick="switchTab('receipt',this)"><i class="fa fa-receipt me-2"></i>إعدادات الفاتورة</button>
  <button class="st-tab" onclick="switchTab('taxes',this)"><i class="fa fa-percent me-2"></i>الضريبة والخدمة</button>
</div>

<form id="settingsForm">

<!-- ═══ Tab 1: بيانات الكافيه ═══════════════════════════════ -->
<div class="settings-section active" id="tab-cafe">
  <div class="setting-group">
    <h6><i class="fa fa-store me-2 text-warning"></i>بيانات المكان</h6>
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">اسم الكافيه <span class="text-danger">*</span></label>
        <input type="text" name="cafe_name" class="form-control" value="<?= e(getSetting('cafe_name', APP_NAME)) ?>" placeholder="كافيه ماستر">
      </div>
      <div class="col-md-6">
        <label class="form-label">رقم الموبايل</label>
        <input type="text" name="cafe_phone" class="form-control" value="<?= e(getSetting('cafe_phone')) ?>" placeholder="01xxxxxxxxx" dir="ltr">
      </div>
      <div class="col-12">
        <label class="form-label">العنوان</label>
        <input type="text" name="cafe_address" class="form-control" value="<?= e(getSetting('cafe_address')) ?>" placeholder="اسم الشارع، المنطقة، المدينة">
      </div>
    </div>
  </div>
</div>

  <!-- Logo Upload -->
  <div class="setting-group">
    <h6><i class="fa fa-image me-2" style="color:#f97316"></i>لوجو الكافيه في الفاتورة</h6>

    <!-- Current logo preview -->
    <?php $currentLogo = getSetting('cafe_logo',''); ?>
    <div id="logoPreviewWrap" style="<?= $currentLogo ? '' : 'display:none' ?>;text-align:center;margin-bottom:16px;padding:16px;background:#f8fafc;border-radius:12px;border:1px dashed #e2e8f0">
      <img id="logoPreviewImg" src="<?= e($currentLogo) ?>?v=<?= time() ?>" alt="لوجو الكافيه"
           style="max-width:220px;max-height:90px;object-fit:contain;border-radius:8px">
      <div style="margin-top:10px">
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteLogo()">
          <i class="fa fa-trash me-1"></i>حذف اللوجو
        </button>
      </div>
    </div>

    <!-- Upload area -->
    <div id="uploadArea" style="border:2px dashed #e2e8f0;border-radius:16px;padding:28px;text-align:center;cursor:pointer;transition:all .2s;background:#fafafa"
         onclick="document.getElementById('logoFile').click()"
         ondragover="event.preventDefault();this.style.borderColor='#f59e0b';this.style.background='#fffbeb'"
         ondragleave="this.style.borderColor='#e2e8f0';this.style.background='#fafafa'"
         ondrop="handleDrop(event)">
      <div style="font-size:36px;margin-bottom:8px">🖼️</div>
      <div style="font-weight:700;color:#334155;margin-bottom:4px">اضغط لاختيار صورة اللوجو</div>
      <div style="font-size:12px;color:#94a3b8">أو اسحب الملف وأفلته هنا</div>
      <div style="font-size:11px;color:#cbd5e1;margin-top:6px">JPG · PNG · WebP — حتى 2MB</div>
      <input type="file" id="logoFile" accept="image/jpeg,image/png,image/webp" style="display:none" onchange="uploadLogo(this.files[0])">
    </div>

    <!-- Upload progress -->
    <div id="uploadProgress" style="display:none;margin-top:12px">
      <div class="progress" style="height:8px;border-radius:99px">
        <div class="progress-bar progress-bar-striped progress-bar-animated bg-warning" style="width:100%"></div>
      </div>
      <div style="font-size:12px;color:#64748b;text-align:center;margin-top:6px">جاري الرفع...</div>
    </div>

    <!-- Guide box -->
    <div style="margin-top:20px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:16px;padding:20px">
      <div style="font-size:14px;font-weight:800;color:#0369a1;margin-bottom:14px">
        <i class="fa fa-circle-info me-2"></i>دليل تحضير اللوجو — اقرأ قبل الرفع
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
        <div style="background:#fff;border-radius:12px;padding:12px;border:1px solid #e0f2fe">
          <div style="font-size:11px;color:#64748b;font-weight:700;margin-bottom:4px">الحجم المُوصى به</div>
          <div style="font-size:15px;font-weight:800;color:#0f172a">400 × 150 بيكسل</div>
          <div style="font-size:11px;color:#94a3b8;margin-top:2px">عرض × ارتفاع</div>
        </div>
        <div style="background:#fff;border-radius:12px;padding:12px;border:1px solid #e0f2fe">
          <div style="font-size:11px;color:#64748b;font-weight:700;margin-bottom:4px">الصيغة المُوصى بها</div>
          <div style="font-size:15px;font-weight:800;color:#0f172a">PNG شفاف</div>
          <div style="font-size:11px;color:#94a3b8;margin-top:2px">خلفية شفافة للطباعة</div>
        </div>
      </div>

      <div style="font-size:13px;font-weight:700;color:#0369a1;margin-bottom:10px">خطوات التحضير:</div>
      <div style="display:flex;flex-direction:column;gap:8px">
        <div style="display:flex;gap:10px;align-items:flex-start">
          <span style="width:24px;height:24px;background:#0ea5e9;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;flex-shrink:0">1</span>
          <div>
            <div style="font-size:12px;font-weight:700;color:#1e293b">إزالة الخلفية (اختياري للـ PNG)</div>
            <div style="font-size:11px;color:#64748b">اذهب إلى <strong>remove.bg</strong> — ارفع الصورة — احفظ بدون خلفية</div>
          </div>
        </div>
        <div style="display:flex;gap:10px;align-items:flex-start">
          <span style="width:24px;height:24px;background:#0ea5e9;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;flex-shrink:0">2</span>
          <div>
            <div style="font-size:12px;font-weight:700;color:#1e293b">تغيير الحجم</div>
            <div style="font-size:11px;color:#64748b">اذهب إلى <strong>iloveimg.com</strong> — اختر «تغيير حجم الصورة» — اضبطه على <strong>400 عرض × 150 ارتفاع</strong></div>
          </div>
        </div>
        <div style="display:flex;gap:10px;align-items:flex-start">
          <span style="width:24px;height:24px;background:#0ea5e9;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;flex-shrink:0">3</span>
          <div>
            <div style="font-size:12px;font-weight:700;color:#1e293b">ضغط الحجم (اختياري)</div>
            <div style="font-size:11px;color:#64748b">اذهب إلى <strong>tinypng.com</strong> — ارفع الصورة — احفظ النسخة المضغوطة</div>
          </div>
        </div>
        <div style="display:flex;gap:10px;align-items:flex-start">
          <span style="width:24px;height:24px;background:#22c55e;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;flex-shrink:0">4</span>
          <div>
            <div style="font-size:12px;font-weight:700;color:#1e293b">ارفع الصورة هنا ↑</div>
            <div style="font-size:11px;color:#64748b">اضغط على المربع أعلاه أو اسحب الصورة عليه مباشرةً</div>
          </div>
        </div>
      </div>

      <div style="margin-top:14px;background:#fef9c3;border:1px solid #fde047;border-radius:10px;padding:10px 12px;font-size:11px;color:#713f12">
        <i class="fa fa-triangle-exclamation me-1"></i>
        <strong>للطابعة الحرارية (80mm):</strong> اللوجو بيتطبع بالأبيض والأسود — استخدم صورة ذات تباين عالي وتجنب الألوان الفاتحة جداً.
      </div>
    </div>
  </div>

<!-- ═══ Tab 2: إعدادات الفاتورة ════════════════════════════ -->
<div class="settings-section" id="tab-receipt">
  <div class="setting-group">
    <h6><i class="fa fa-receipt me-2 text-info"></i>نصوص الفاتورة</h6>
    <div class="row g-3">
      <div class="col-12">
        <label class="form-label">النص أسفل اسم الكافيه</label>
        <input type="text" name="receipt_header" class="form-control" value="<?= e(getSetting('receipt_header')) ?>" placeholder="مثال: كافيه احترافي — نظام إدارة متكامل">
        <div class="form-text">يظهر تحت اسم الكافيه في الفاتورة</div>
      </div>
      <div class="col-md-6">
        <label class="form-label">نص التذييل السطر الأول</label>
        <input type="text" name="receipt_footer1" class="form-control" value="<?= e(getSetting('receipt_footer1')) ?>" placeholder="شكراً لزيارتكم">
      </div>
      <div class="col-md-6">
        <label class="form-label">نص التذييل السطر الثاني</label>
        <input type="text" name="receipt_footer2" class="form-control" value="<?= e(getSetting('receipt_footer2')) ?>" placeholder="نأمل أن تعودوا مرة أخرى">
      </div>
    </div>
  </div>

  <!-- Preview -->
  <div class="setting-group">
    <h6><i class="fa fa-eye me-2 text-secondary"></i>معاينة الفاتورة</h6>
    <div style="max-width:320px;margin:0 auto;background:#fff;border:1px dashed #cbd5e1;border-radius:12px;padding:20px;font-family:'Cairo',monospace;font-size:12px;text-align:center;color:#1e293b">
      <div style="font-size:20px;font-weight:900;margin-bottom:4px" id="prev_cafe_name"><?= e(getSetting('cafe_name', APP_NAME)) ?></div>
      <div style="color:#666;font-size:11px;margin-bottom:8px" id="prev_receipt_header"><?= e(getSetting('receipt_header')) ?></div>
      <?php if (getSetting('cafe_phone')): ?>
      <div style="color:#666;font-size:11px;margin-bottom:2px" id="prev_cafe_phone">📞 <?= e(getSetting('cafe_phone')) ?></div>
      <?php else: ?>
      <div style="color:#aaa;font-size:11px;margin-bottom:2px" id="prev_cafe_phone"></div>
      <?php endif; ?>
      <div style="border-top:1px dashed #ccc;margin:10px 0"></div>
      <div style="text-align:right;font-size:11px;color:#333">
        <div style="display:flex;justify-content:space-between"><span>قهوة عربي × 2</span><span>24.00 ج.م</span></div>
        <div style="display:flex;justify-content:space-between"><span>كيك شوكولاتة × 1</span><span>22.00 ج.م</span></div>
      </div>
      <div style="border-top:1px dashed #ccc;margin:10px 0"></div>
      <div style="text-align:right;font-size:11px">
        <div style="display:flex;justify-content:space-between"><span>المجموع:</span><span>46.00 ج.م</span></div>
        <?php if (getSetting('service_enabled')==='1'): ?>
        <div style="display:flex;justify-content:space-between;color:#64748b"><span><?= e(getSetting('service_label','رسوم الخدمة')) ?> (<?= e(getSetting('service_percent','12')) ?>%):</span><span><?= number_format(46*floatval(getSetting('service_percent','12'))/100,2) ?> ج.م</span></div>
        <?php endif; ?>
        <?php if (getSetting('tax_enabled')==='1'): ?>
        <div style="display:flex;justify-content:space-between;color:#64748b"><span><?= e(getSetting('tax_label','ضريبة القيمة المضافة')) ?> (<?= e(getSetting('tax_percent','14')) ?>%):</span><span><?= number_format(46*floatval(getSetting('tax_percent','14'))/100,2) ?> ج.م</span></div>
        <?php endif; ?>
        <div style="display:flex;justify-content:space-between;font-weight:800;font-size:14px;margin-top:4px"><span>الإجمالي:</span><span style="color:#22c55e">46.00 ج.م</span></div>
      </div>
      <div style="border-top:1px dashed #ccc;margin:10px 0"></div>
      <div style="color:#555;font-size:11px" id="prev_footer1"><?= e(getSetting('receipt_footer1')) ?></div>
      <div style="color:#555;font-size:11px" id="prev_footer2"><?= e(getSetting('receipt_footer2')) ?></div>
    </div>
  </div>
</div>

<!-- ═══ Tab 3: الضريبة والخدمة ══════════════════════════════ -->
<div class="settings-section" id="tab-taxes">
  <div class="row g-4">

    <!-- Service Charge -->
    <div class="col-md-6">
      <div class="setting-group h-100">
        <h6><i class="fa fa-concierge-bell me-2" style="color:#8b5cf6"></i>رسوم الخدمة</h6>
        <div class="mb-3">
          <div class="toggle-switch">
            <input type="checkbox" name="service_enabled" id="svcToggle" value="1" <?= getSetting('service_enabled')==='1' ? 'checked' : '' ?>>
            <label class="toggle-label" for="svcToggle">تفعيل رسوم الخدمة</label>
          </div>
          <small class="text-muted d-block mt-2">تُضاف تلقائياً على كل فاتورة</small>
        </div>
        <div class="mb-3">
          <label class="form-label">اسم البند في الفاتورة</label>
          <input type="text" name="service_label" class="form-control" value="<?= e(getSetting('service_label','رسوم الخدمة')) ?>" placeholder="رسوم الخدمة">
        </div>
        <div>
          <label class="form-label">النسبة المئوية</label>
          <div class="pct-input">
            <input type="number" name="service_percent" class="form-control" value="<?= e(getSetting('service_percent','12')) ?>" min="0" max="100" step="0.5">
            <span>% من الإجمالي بعد الخصم</span>
          </div>
        </div>
        <div class="mt-3 p-3 rounded-3" style="background:#f5f3ff">
          <div style="font-size:12px;color:#7c3aed;font-weight:700">مثال على فاتورة 100 ج.م</div>
          <div style="font-size:12px;color:#64748b;margin-top:4px" id="svcExampleText">رسوم الخدمة = <span id="svcPctLabel"><?= getSetting('service_percent','12') ?></span>% × 100 = <strong><span id="svcExampleVal"><?= getSetting('service_percent','12') ?></span> ج.م</strong></div>
        </div>
      </div>
    </div>

    <!-- Tax -->
    <div class="col-md-6">
      <div class="setting-group h-100">
        <h6><i class="fa fa-landmark me-2" style="color:#ef4444"></i>الضريبة</h6>
        <div class="mb-3">
          <div class="toggle-switch">
            <input type="checkbox" name="tax_enabled" id="taxToggle" value="1" <?= getSetting('tax_enabled')==='1' ? 'checked' : '' ?>>
            <label class="toggle-label" for="taxToggle">تفعيل الضريبة</label>
          </div>
          <small class="text-muted d-block mt-2">ضريبة القيمة المضافة أو أي ضريبة أخرى</small>
        </div>
        <div class="mb-3">
          <label class="form-label">اسم البند في الفاتورة</label>
          <input type="text" name="tax_label" class="form-control" value="<?= e(getSetting('tax_label','ضريبة القيمة المضافة')) ?>" placeholder="ضريبة القيمة المضافة">
        </div>
        <div>
          <label class="form-label">النسبة المئوية</label>
          <div class="pct-input">
            <input type="number" name="tax_percent" class="form-control" value="<?= e(getSetting('tax_percent','14')) ?>" min="0" max="100" step="0.5">
            <span>% من الإجمالي بعد الخصم</span>
          </div>
        </div>
        <div class="mt-3 p-3 rounded-3" style="background:#fef2f2">
          <div style="font-size:12px;color:#dc2626;font-weight:700">مثال على فاتورة 100 ج.م</div>
          <div style="font-size:12px;color:#64748b;margin-top:4px" id="taxExampleText">الضريبة = <span id="taxPctLabel"><?= getSetting('tax_percent','14') ?></span>% × 100 = <strong><span id="taxExampleVal"><?= getSetting('tax_percent','14') ?></span> ج.م</strong></div>
        </div>
      </div>
    </div>

  </div>

  <!-- Combined example -->
  <div class="setting-group mt-2">
    <h6><i class="fa fa-calculator me-2 text-secondary"></i>مثال على الحساب الكامل</h6>
    <div class="table-responsive">
      <table class="table table-sm table-borderless mb-0" style="max-width:360px">
        <tr><td class="text-muted">المجموع الفرعي</td><td class="fw-bold">100.00 ج.م</td></tr>
        <tr><td class="text-muted">خصم (مثال 10%)</td><td class="text-danger fw-bold">- 10.00 ج.م</td></tr>
        <tr><td class="text-muted">بعد الخصم</td><td class="fw-bold">90.00 ج.م</td></tr>
        <tr id="cmbSvcRow" style="<?= getSetting('service_enabled')==='1' ? '' : 'display:none' ?>">
          <td class="text-muted" id="cmbSvcLabel"><?= e(getSetting('service_label','رسوم الخدمة')) ?> (<span id="cmbSvcPct"><?= getSetting('service_percent','12') ?></span>%)</td>
          <td style="color:#8b5cf6;font-weight:700">+ <span id="cmbSvcVal"><?= number_format(90*floatval(getSetting('service_percent','12'))/100,2) ?></span> ج.م</td>
        </tr>
        <tr id="cmbTaxRow" style="<?= getSetting('tax_enabled')==='1' ? '' : 'display:none' ?>">
          <td class="text-muted" id="cmbTaxLabel"><?= e(getSetting('tax_label','ضريبة القيمة المضافة')) ?> (<span id="cmbTaxPct"><?= getSetting('tax_percent','14') ?></span>%)</td>
          <td style="color:#dc2626;font-weight:700">+ <span id="cmbTaxVal"><?= number_format(90*floatval(getSetting('tax_percent','14'))/100,2) ?></span> ج.م</td>
        </tr>
        <tr style="border-top:2px solid #e2e8f0">
          <td class="fw-bold">الإجمالي النهائي</td>
          <td class="fw-bold text-success fs-6"><span id="cmbTotal"><?php
            $ex = 90;
            $svc = getSetting('service_enabled')==='1' ? $ex*floatval(getSetting('service_percent','12'))/100 : 0;
            $tax = getSetting('tax_enabled')==='1' ? $ex*floatval(getSetting('tax_percent','14'))/100 : 0;
            echo number_format($ex+$svc+$tax,2);
          ?></span> ج.م</td>
        </tr>
      </table>
    </div>
  </div>
</div>

</form>

<script>
function uploadLogo(file) {
  if (!file) return;
  const allowed = ['image/jpeg','image/png','image/webp'];
  if (!allowed.includes(file.type)) { toast('error', 'الصيغة غير مسموحة. استخدم JPG أو PNG أو WebP'); return; }
  if (file.size > 2*1024*1024) { toast('error', 'الملف أكبر من 2 ميجابايت'); return; }

  const fd = new FormData();
  fd.append('logo', file);
  document.getElementById('uploadProgress').style.display = 'block';
  document.getElementById('uploadArea').style.opacity = '0.5';

  fetch('<?= BASE_URL ?>/admin/api/upload_logo.php', { method:'POST', body:fd })
    .then(r => r.json())
    .then(res => {
      document.getElementById('uploadProgress').style.display = 'none';
      document.getElementById('uploadArea').style.opacity = '1';
      if (res.success) {
        document.getElementById('logoPreviewImg').src = res.url;
        document.getElementById('logoPreviewWrap').style.display = 'block';
        toast('success', res.message);
      } else {
        toast('error', res.message);
      }
    })
    .catch(() => {
      document.getElementById('uploadProgress').style.display = 'none';
      document.getElementById('uploadArea').style.opacity = '1';
      toast('error', 'حدث خطأ في الاتصال');
    });
}

function handleDrop(e) {
  e.preventDefault();
  document.getElementById('uploadArea').style.borderColor = '#e2e8f0';
  document.getElementById('uploadArea').style.background = '#fafafa';
  const file = e.dataTransfer.files[0];
  if (file) uploadLogo(file);
}

function deleteLogo() {
  Swal.fire({
    title: 'حذف اللوجو؟',
    text: 'سيتم حذف اللوجو من الفاتورة',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    confirmButtonText: 'نعم، احذف',
    cancelButtonText: 'إلغاء',
    reverseButtons: true,
  }).then(r => {
    if (!r.isConfirmed) return;
    fetch('<?= BASE_URL ?>/admin/api/upload_logo.php', {
      method:'POST',
      body: new URLSearchParams({action:'delete'})
    }).then(r => r.json()).then(res => {
      if (res.success) {
        document.getElementById('logoPreviewWrap').style.display = 'none';
        document.getElementById('logoPreviewImg').src = '';
        toast('success', res.message);
      }
    });
  });
}

function switchTab(id, btn) {
  document.querySelectorAll('.settings-section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.st-tab').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + id).classList.add('active');
  btn.classList.add('active');
}

// Live preview update
document.querySelectorAll('[name="cafe_name"],[name="receipt_header"],[name="cafe_phone"],[name="receipt_footer1"],[name="receipt_footer2"]').forEach(inp => {
  inp.addEventListener('input', function() {
    const map = {'cafe_name':'prev_cafe_name','receipt_header':'prev_receipt_header','receipt_footer1':'prev_footer1','receipt_footer2':'prev_footer2'};
    const el = document.getElementById(map[this.name]);
    if (el) el.textContent = this.value;
    if (this.name === 'cafe_phone') {
      const ph = document.getElementById('prev_cafe_phone');
      if (ph) ph.textContent = this.value ? '📞 ' + this.value : '';
    }
  });
});

function updateExamples() {
  const svcPct = parseFloat(document.querySelector('[name="service_percent"]').value) || 0;
  const taxPct = parseFloat(document.querySelector('[name="tax_percent"]').value) || 0;
  const svcOn  = document.getElementById('svcToggle').checked;
  const taxOn  = document.getElementById('taxToggle').checked;

  // Small example boxes
  document.getElementById('svcPctLabel').textContent  = svcPct;
  document.getElementById('svcExampleVal').textContent = svcPct.toFixed(2);
  document.getElementById('taxPctLabel').textContent   = taxPct;
  document.getElementById('taxExampleVal').textContent = taxPct.toFixed(2);

  // Combined table
  const base = 90;
  const svcAmt = svcOn ? parseFloat((base * svcPct / 100).toFixed(2)) : 0;
  const taxAmt = taxOn ? parseFloat((base * taxPct / 100).toFixed(2)) : 0;

  document.getElementById('cmbSvcRow').style.display = svcOn ? '' : 'none';
  document.getElementById('cmbTaxRow').style.display = taxOn ? '' : 'none';
  document.getElementById('cmbSvcPct').textContent = svcPct;
  document.getElementById('cmbSvcVal').textContent = svcAmt.toFixed(2);
  document.getElementById('cmbTaxPct').textContent = taxPct;
  document.getElementById('cmbTaxVal').textContent = taxAmt.toFixed(2);
  document.getElementById('cmbTotal').textContent  = (base + svcAmt + taxAmt).toFixed(2);
}

document.querySelector('[name="service_percent"]').addEventListener('input', updateExamples);
document.querySelector('[name="tax_percent"]').addEventListener('input', updateExamples);
document.getElementById('svcToggle').addEventListener('change', updateExamples);
document.getElementById('taxToggle').addEventListener('change', updateExamples);

function saveSettings() {
  const form = document.getElementById('settingsForm');
  const data = new FormData(form);

  // Add unchecked checkboxes as 0
  ['service_enabled','tax_enabled'].forEach(k => {
    if (!data.has(k)) data.append(k, '0');
    else data.set(k, '1');
  });

  fetch('<?= BASE_URL ?>/admin/api/settings.php', {
    method: 'POST',
    body: data
  }).then(r => r.json()).then(res => {
    if (res.success) {
      toast('success', res.message);
      setTimeout(() => location.reload(), 900);
    } else {
      toast('error', res.message || 'حدث خطأ');
    }
  });
}
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
