<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

// ── xlsx / csv parsers ────────────────────────────────────────────────

function colToIdx($col) {
    $idx = 0;
    $col = strtoupper($col);
    for ($i = 0; $i < strlen($col); $i++) {
        $idx = $idx * 26 + (ord($col[$i]) - 64);
    }
    return $idx - 1;
}

function parseXlsx($path) {
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return ['error' => 'تعذّر فتح ملف Excel'];

    $ss = [];
    $ssRaw = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssRaw) {
        $ssXml = simplexml_load_string($ssRaw);
        foreach ($ssXml->si as $si) {
            $t = '';
            if (isset($si->t)) {
                $t = (string)$si->t;
            } else {
                foreach ($si->r as $r) { $t .= isset($r->t) ? (string)$r->t : ''; }
            }
            $ss[] = $t;
        }
    }

    $wsRaw = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if (!$wsRaw) return ['error' => 'لا يوجد شيت في الملف'];

    $ws   = simplexml_load_string($wsRaw);
    $rows = [];
    foreach ($ws->sheetData->row as $rowNode) {
        $cells = [];
        foreach ($rowNode->c as $c) {
            preg_match('/^([A-Z]+)/', (string)$c['r'], $m);
            $ci   = colToIdx($m[1]);
            $type = (string)$c['t'];
            if ($type === 's') {
                $val = $ss[(int)((string)$c->v)] ?? '';
            } elseif ($type === 'inlineStr') {
                $val = (string)($c->is->t ?? '');
            } else {
                $val = (string)($c->v ?? '');
            }
            $cells[$ci] = trim($val);
        }
        if (empty($cells)) continue;
        $maxIdx = max(array_keys($cells));
        $row = [];
        for ($i = 0; $i <= max($maxIdx, 4); $i++) $row[] = $cells[$i] ?? '';
        $rows[] = $row;
    }
    return ['rows' => $rows];
}

function parseCsv($path) {
    $content = file_get_contents($path);
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") $content = substr($content, 3);
    $lines = explode("\n", str_replace("\r\n", "\n", $content));
    $rows  = [];
    foreach ($lines as $line) {
        $line = rtrim($line);
        if ($line === '') continue;
        $rows[] = str_getcsv($line);
    }
    return ['rows' => $rows];
}

// ── validation & processing ───────────────────────────────────────────

function processRows($rows) {
    $existingCats  = fetchAll('SELECT id, name FROM categories ORDER BY name');
    $catByName = [];
    foreach ($existingCats as $c) {
        $catByName[mb_strtolower(trim($c['name']))] = $c;
    }

    $existingItems = fetchAll(
        'SELECT i.name AS iname, c.name AS cname FROM items i JOIN categories c ON c.id=i.category_id'
    );
    $dupSet = [];
    foreach ($existingItems as $ei) {
        $dupSet[mb_strtolower(trim($ei['cname'])) . '|' . mb_strtolower(trim($ei['iname']))] = true;
    }

    $items      = [];
    $newCatsMap = [];
    $hdrSkipped = false;

    foreach ($rows as $row) {
        $cat   = trim($row[0] ?? '');
        $name  = trim($row[1] ?? '');
        $price = trim($row[2] ?? '');
        $cost  = trim($row[3] ?? '');
        $desc  = trim($row[4] ?? '');

        // Skip header row (contains "فئة" in column A)
        if (!$hdrSkipped && mb_strpos($cat, 'فئة') !== false) {
            $hdrSkipped = true;
            continue;
        }

        // Skip empty rows
        if ($cat === '' && $name === '' && $price === '') continue;

        $errs   = [];
        if ($cat === '')                                       $errs[] = 'الفئة فارغة';
        if ($name === '')                                      $errs[] = 'اسم الصنف فارغ';
        if (!is_numeric($price) || (float)$price <= 0)        $errs[] = 'السعر غير صحيح';

        $catKey   = mb_strtolower(trim($cat));
        $isNewCat = $cat !== '' && !isset($catByName[$catKey]) && !isset($newCatsMap[$catKey]);
        if ($isNewCat) $newCatsMap[$catKey] = $cat;

        $isDup = isset($dupSet[$catKey . '|' . mb_strtolower(trim($name))]);

        $items[] = [
            'cat'        => $cat,
            'name'       => $name,
            'price'      => is_numeric($price) ? (float)$price : 0,
            'cost'       => (is_numeric($cost) && (float)$cost >= 0) ? (float)$cost : 0,
            'desc'       => $desc,
            'is_new_cat' => $isNewCat,
            'is_dup'     => $isDup,
            'errors'     => $errs,
        ];
    }

    return ['items' => $items, 'new_cats' => array_values($newCatsMap)];
}

// ── import ────────────────────────────────────────────────────────────

function doImport($data) {
    $db = getDB();

    $catRows = fetchAll('SELECT id, name FROM categories');
    $catMap  = [];
    foreach ($catRows as $c) $catMap[mb_strtolower(trim($c['name']))] = (int)$c['id'];

    $maxSort = (int)$db->query('SELECT COALESCE(MAX(sort_order),0) FROM categories')->fetchColumn();

    $createdCats = 0;
    $addedItems  = 0;
    $skipped     = 0;

    $db->beginTransaction();
    try {
        foreach ($data['items'] as $item) {
            if (!empty($item['errors']) || $item['is_dup']) { $skipped++; continue; }

            $catKey = mb_strtolower(trim($item['cat']));

            if (!isset($catMap[$catKey])) {
                $maxSort++;
                $db->prepare('INSERT INTO categories (name, sort_order, is_active) VALUES (?,?,1)')
                   ->execute([$item['cat'], $maxSort]);
                $catMap[$catKey] = (int)$db->lastInsertId();
                $createdCats++;
            }

            $catId = $catMap[$catKey];

            // Guard against within-file duplicates
            $dup = $db->prepare('SELECT id FROM items WHERE category_id=? AND name=?');
            $dup->execute([$catId, $item['name']]);
            if ($dup->fetch()) { $skipped++; continue; }

            $db->prepare('INSERT INTO items (category_id,name,price,cost,description,is_active) VALUES (?,?,?,?,?,1)')
               ->execute([$catId, $item['name'], $item['price'], $item['cost'], $item['desc']]);
            $addedItems++;
        }
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        return ['error' => 'خطأ أثناء الحفظ: ' . $e->getMessage()];
    }

    return ['created_cats' => $createdCats, 'added_items' => $addedItems, 'skipped' => $skipped];
}

// ── controller ────────────────────────────────────────────────────────

$step   = 'upload';
$error  = null;
$data   = null;
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['do_import']) && !empty($_SESSION['import_data'])) {
        $result = doImport($_SESSION['import_data']);
        unset($_SESSION['import_data']);
        $step = isset($result['error']) ? 'upload' : 'done';
        if (isset($result['error'])) $error = $result['error'];

    } elseif (!empty($_FILES['items_file']['tmp_name'])) {
        $file = $_FILES['items_file'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($file['size'] > 5 * 1024 * 1024) {
            $error = 'حجم الملف أكبر من 5 ميجابايت';
        } elseif (!in_array($ext, ['xlsx','csv'], true)) {
            $error = 'يرجى رفع ملف .xlsx أو .csv فقط';
        } else {
            $parsed = $ext === 'xlsx' ? parseXlsx($file['tmp_name']) : parseCsv($file['tmp_name']);
            if (isset($parsed['error'])) {
                $error = $parsed['error'];
            } else {
                $data = processRows($parsed['rows']);
                if (empty($data['items'])) {
                    $error = 'الملف فارغ أو لا يحتوي على بيانات صالحة';
                } else {
                    $_SESSION['import_data'] = $data;
                    $step = 'preview';
                }
            }
        }
    }
}

// counts for preview
$cntValid = $cntDup = $cntErr = 0;
if ($data) {
    foreach ($data['items'] as $it) {
        if (!empty($it['errors'])) $cntErr++;
        elseif ($it['is_dup'])     $cntDup++;
        else                        $cntValid++;
    }
}

$pageTitle = 'استيراد أصناف';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<?php if ($step === 'upload'): ?>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header">
        <i class="fa fa-file-import me-2"></i> استيراد أصناف من Excel
      </div>
      <div class="card-body">

        <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fa fa-circle-exclamation me-2"></i><?= e($error) ?></div>
        <?php endif; ?>

        <a href="<?= BASE_URL ?>/admin/template_items.php" class="btn btn-outline-success mb-4">
          <i class="fa fa-download me-2"></i> تنزيل قالب Excel الجاهز
        </a>

        <form method="post" enctype="multipart/form-data" id="uploadForm">
          <div id="dropArea" onclick="document.getElementById('fileInput').click()"
               style="border:2px dashed var(--border-color,#dee2e6);border-radius:12px;
                      padding:40px 20px;text-align:center;cursor:pointer;
                      transition:border-color .2s,background .2s">
            <i class="fa fa-file-excel" style="font-size:52px;color:#217346;display:block;margin-bottom:12px"></i>
            <div style="font-size:16px;font-weight:600;margin-bottom:6px">اسحب الملف هنا أو اضغط للاختيار</div>
            <div class="text-muted" style="font-size:13px">xlsx أو csv — بحد أقصى 5 ميجابايت</div>
            <div id="fileName" class="mt-2 text-success fw-bold" style="display:none"></div>
            <input type="file" name="items_file" id="fileInput" accept=".xlsx,.csv"
                   required style="display:none" onchange="onFileChange(this)">
          </div>
          <button type="submit" class="btn btn-accent mt-3 w-100" id="submitBtn" disabled>
            <i class="fa fa-magnifying-glass me-2"></i> رفع وفحص الملف
          </button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card">
      <div class="card-header"><i class="fa fa-circle-info me-2"></i> تعليمات</div>
      <div class="card-body" style="font-size:14px">
        <ol class="mb-3" style="padding-right:20px;line-height:2">
          <li>نزّل القالب وافتحه في Excel</li>
          <li>امسح بيانات المثال وأدخل أصنافك</li>
          <li>لفئة جديدة: اكتب اسمها في عمود A فقط</li>
          <li>احفظ وارفع الملف هنا</li>
          <li>راجع الـ preview وأكّد الاستيراد</li>
        </ol>
        <div class="alert alert-warning p-2 mb-2" style="font-size:12px">
          <i class="fa fa-triangle-exclamation me-1"></i>
          <strong>الأعمدة المطلوبة:</strong> الفئة، اسم الصنف، السعر
        </div>
        <div class="alert alert-info p-2 mb-0" style="font-size:12px">
          <i class="fa fa-info-circle me-1"></i>
          الأصناف المكررة (نفس الاسم في نفس الفئة) ستُتجاهل تلقائياً
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function onFileChange(input) {
    var name = input.files[0] ? input.files[0].name : '';
    document.getElementById('fileName').textContent = name;
    document.getElementById('fileName').style.display = name ? '' : 'none';
    document.getElementById('submitBtn').disabled = !name;
    var area = document.getElementById('dropArea');
    area.style.borderColor = name ? '#217346' : '';
    area.style.background  = name ? '#f0fff4' : '';
}

var dropArea = document.getElementById('dropArea');
dropArea.addEventListener('dragover', function(e) {
    e.preventDefault();
    dropArea.style.borderColor = '#217346';
    dropArea.style.background  = '#f0fff4';
});
dropArea.addEventListener('dragleave', function() {
    if (!document.getElementById('fileInput').files.length) {
        dropArea.style.borderColor = '';
        dropArea.style.background  = '';
    }
});
dropArea.addEventListener('drop', function(e) {
    e.preventDefault();
    var input = document.getElementById('fileInput');
    input.files = e.dataTransfer.files;
    onFileChange(input);
});
</script>

<?php elseif ($step === 'preview'): ?>

<?php
$data = $_SESSION['import_data'] ?? null;
if (!$data) { header('Location: import_items.php'); exit; }
$cntValid = $cntDup = $cntErr = 0;
foreach ($data['items'] as $it) {
    if (!empty($it['errors'])) $cntErr++;
    elseif ($it['is_dup'])     $cntDup++;
    else                        $cntValid++;
}
?>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card text-center p-3">
      <div style="font-size:28px;font-weight:700;color:#16a34a"><?= $cntValid ?></div>
      <div class="text-muted" style="font-size:13px">سيتم إضافتهم</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center p-3">
      <div style="font-size:28px;font-weight:700;color:#7c3aed"><?= count($data['new_cats']) ?></div>
      <div class="text-muted" style="font-size:13px">فئات جديدة</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center p-3">
      <div style="font-size:28px;font-weight:700;color:#d97706"><?= $cntDup ?></div>
      <div class="text-muted" style="font-size:13px">مكررة (ستُتجاهل)</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center p-3">
      <div style="font-size:28px;font-weight:700;color:#dc2626"><?= $cntErr ?></div>
      <div class="text-muted" style="font-size:13px">أخطاء (ستُتجاهل)</div>
    </div>
  </div>
</div>

<?php if (!empty($data['new_cats'])): ?>
<div class="alert alert-primary mb-3" style="font-size:13px">
  <i class="fa fa-sparkles me-2"></i>
  <strong>فئات جديدة ستُنشأ:</strong>
  <?php foreach ($data['new_cats'] as $nc): ?>
  <span class="badge bg-primary ms-1"><?= e($nc) ?></span>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="fa fa-list me-2"></i> تفاصيل الاستيراد (<?= count($data['items']) ?> سطر)</span>
    <span class="text-muted" style="font-size:12px">
      <span class="badge bg-success">جديد</span>
      <span class="badge bg-warning text-dark ms-1">مكرر</span>
      <span class="badge bg-danger ms-1">خطأ</span>
    </span>
  </div>
  <div class="card-body p-0">
    <div style="max-height:420px;overflow-y:auto">
      <table class="table table-sm mb-0" style="font-size:13px">
        <thead style="position:sticky;top:0;z-index:1;background:#f8f9fa">
          <tr>
            <th style="width:36px">#</th>
            <th>الفئة</th>
            <th>الصنف</th>
            <th>السعر</th>
            <th>الكوست</th>
            <th>ملاحظة</th>
            <th>الحالة</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($data['items'] as $idx => $it): ?>
          <?php
            if (!empty($it['errors']))  { $rowCls = 'table-danger';  $badge = '<span class="badge bg-danger">خطأ: '.e(implode('، ',$it['errors'])).'</span>'; }
            elseif ($it['is_dup'])      { $rowCls = 'table-warning'; $badge = '<span class="badge bg-warning text-dark">مكرر</span>'; }
            else                        { $rowCls = 'table-success'; $badge = '<span class="badge bg-success">جديد</span>'; }
          ?>
          <tr class="<?= $rowCls ?>">
            <td class="text-muted"><?= $idx + 1 ?></td>
            <td>
              <?= e($it['cat']) ?>
              <?php if ($it['is_new_cat'] && empty($it['errors'])): ?>
              <span class="badge bg-primary" style="font-size:10px">جديدة</span>
              <?php endif; ?>
            </td>
            <td class="fw-bold"><?= e($it['name']) ?></td>
            <td><?= $it['price'] > 0 ? number_format($it['price'],2) : '—' ?></td>
            <td><?= $it['cost'] > 0 ? number_format($it['cost'],2) : '—' ?></td>
            <td class="text-muted"><?= e($it['desc']) ?></td>
            <td><?= $badge ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="d-flex gap-2 align-items-center">
  <?php if ($cntValid > 0): ?>
  <form method="post">
    <input type="hidden" name="do_import" value="1">
    <button type="submit" class="btn btn-success btn-lg">
      <i class="fa fa-check me-2"></i> تأكيد إضافة <?= $cntValid ?> صنف
      <?php if (count($data['new_cats']) > 0): ?>
      + <?= count($data['new_cats']) ?> فئة جديدة
      <?php endif; ?>
    </button>
  </form>
  <?php else: ?>
  <div class="alert alert-warning mb-0">لا يوجد أصناف صالحة للاستيراد</div>
  <?php endif; ?>
  <a href="import_items.php" class="btn btn-outline-secondary btn-lg">
    <i class="fa fa-arrow-right me-2"></i> رجوع
  </a>
</div>

<?php elseif ($step === 'done'): ?>

<div class="row justify-content-center">
  <div class="col-md-6">
    <div class="card text-center p-4">
      <div style="font-size:56px;color:#16a34a;margin-bottom:12px">
        <i class="fa fa-circle-check"></i>
      </div>
      <h4 class="mb-3">تم الاستيراد بنجاح!</h4>

      <div class="row g-3 mb-4">
        <div class="col-4">
          <div style="font-size:32px;font-weight:700;color:#16a34a"><?= $result['added_items'] ?></div>
          <div class="text-muted" style="font-size:12px">صنف أضيف</div>
        </div>
        <div class="col-4">
          <div style="font-size:32px;font-weight:700;color:#7c3aed"><?= $result['created_cats'] ?></div>
          <div class="text-muted" style="font-size:12px">فئة أنشئت</div>
        </div>
        <div class="col-4">
          <div style="font-size:32px;font-weight:700;color:#64748b"><?= $result['skipped'] ?></div>
          <div class="text-muted" style="font-size:12px">تجاهلت</div>
        </div>
      </div>

      <div class="d-flex gap-2 justify-content-center">
        <a href="<?= BASE_URL ?>/admin/items.php" class="btn btn-accent">
          <i class="fa fa-utensils me-2"></i> عرض الأصناف
        </a>
        <a href="import_items.php" class="btn btn-outline-secondary">
          <i class="fa fa-file-import me-2"></i> استيراد آخر
        </a>
      </div>
    </div>
  </div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
