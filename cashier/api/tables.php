<?php
define('IS_API', true);
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireApiLogin();

$db     = getDB();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'open') {
    $tableId = (int)($_POST['table_id'] ?? 0);
    if (!$tableId) jsonResponse(false, 'رقم الطاولة مطلوب');

    $table = fetchOne('SELECT * FROM cafe_tables WHERE id=?', [$tableId]);
    if (!$table) jsonResponse(false, 'الطاولة غير موجودة');
    if ($table['status'] === 'occupied') jsonResponse(false, 'الطاولة مشغولة بالفعل');

    $shift = getActiveShift();
    if (!$shift) jsonResponse(false, 'يجب تسجيل الحضور أولاً');

    // Create order
    $db->prepare("INSERT INTO orders(table_id,shift_id,user_id,status,opened_at) VALUES(?,?,?,'open',NOW())")
       ->execute([$tableId, $shift['id'], $_SESSION['user_id']]);
    $db->prepare("UPDATE cafe_tables SET status='occupied' WHERE id=?")->execute([$tableId]);

    jsonResponse(true, 'تم فتح الطاولة', ['order_id' => (int)$db->lastInsertId()]);
}

if ($action === 'toggle_vip') {
    $tableId = (int)($_POST['table_id'] ?? 0);
    if (!$tableId) jsonResponse(false, 'رقم الطاولة مطلوب');
    $db->prepare("UPDATE cafe_tables SET is_vip = 1 - is_vip WHERE id=?")->execute([$tableId]);
    $row = fetchOne("SELECT is_vip FROM cafe_tables WHERE id=?", [$tableId]);
    jsonResponse(true, 'تم تحديث حالة VIP', ['is_vip' => (bool)$row['is_vip']]);
}

jsonResponse(false, 'إجراء غير معروف');
