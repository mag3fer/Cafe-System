<?php
define('IS_API', true);
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireAdmin();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── Delete logo ───────────────────────────────────────────
if ($action === 'delete') {
    $current = getSetting('cafe_logo', '');
    if ($current) {
        $path = __DIR__ . '/../../' . ltrim($current, '/');
        if (file_exists($path)) @unlink($path);
    }
    $db = getDB();
    $db->prepare("INSERT INTO `settings` (`key`,`value`) VALUES ('cafe_logo','') ON DUPLICATE KEY UPDATE `value`=''")
       ->execute();
    jsonResponse(true, 'تم حذف اللوجو');
}

// ── Upload logo ───────────────────────────────────────────
if (!isset($_FILES['logo'])) jsonResponse(false, 'لم يتم إرسال ملف');

$file    = $_FILES['logo'];
$maxSize = 2 * 1024 * 1024; // 2MB
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$exts    = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];

if ($file['error'] !== UPLOAD_ERR_OK) jsonResponse(false, 'خطأ في رفع الملف');
if ($file['size'] > $maxSize)          jsonResponse(false, 'الملف أكبر من 2 ميجابايت');

// Verify actual mime type (not trusted header)
$finfo    = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowed)) jsonResponse(false, 'صيغة غير مسموحة. المسموح: JPG, PNG, WebP');

$ext     = $exts[$mimeType];
$imgDir  = __DIR__ . '/../../assets/img/';
if (!is_dir($imgDir)) mkdir($imgDir, 0755, true);

// Delete old logo first
$current = getSetting('cafe_logo', '');
if ($current) {
    $oldPath = __DIR__ . '/../../' . ltrim($current, '/');
    if (file_exists($oldPath)) @unlink($oldPath);
}

$filename = 'cafe_logo.' . $ext;
$dest     = $imgDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) jsonResponse(false, 'فشل حفظ الملف');

// Check dimensions
$size = @getimagesize($dest);
$w = $size ? $size[0] : 0;
$h = $size ? $size[1] : 0;

$logoPath = '/cafe/assets/img/' . $filename;

$db = getDB();
$db->prepare("INSERT INTO `settings` (`key`,`value`) VALUES ('cafe_logo',?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)")
   ->execute([$logoPath]);

$warn = '';
if ($w > 600 || $h > 250) $warn = ' (تنبيه: الصورة كبيرة، الأفضل 400×150 بيكسل)';

jsonResponse(true, 'تم رفع اللوجو بنجاح' . $warn, ['url' => $logoPath . '?v=' . time()]);
