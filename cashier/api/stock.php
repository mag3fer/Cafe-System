<?php
define('IS_API', true);
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireApiLogin();

// تحديث الصلاحيات من DB لضمان سريان أي تغيير فوري
$_freshUser = fetchOne('SELECT permissions FROM users WHERE id=? AND is_active=1', [$_SESSION['user_id']]);
$_SESSION['permissions'] = json_decode($_freshUser['permissions'] ?? '[]', true) ?? [];

$canDispense = anyPermission(['manage_stock', 'stock_dispense']);
$canAdd      = anyPermission(['manage_stock', 'stock_add']);

$db     = getDB();
$action = $_POST['action'] ?? '';

if ($action === 'dispense') {
    if (!$canDispense) jsonResponse(false, 'ليس لديك صلاحية الصرف');

    $invId = (int)($_POST['inventory_id'] ?? 0);
    $qty   = (float)($_POST['quantity'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $shift = getActiveShift();

    if (!$invId || $qty <= 0) jsonResponse(false, 'بيانات غير صحيحة');

    $db->beginTransaction();
    $item = fetchOne('SELECT * FROM inventory WHERE id=? AND is_active=1', [$invId]);
    if (!$item) { $db->rollBack(); jsonResponse(false, 'المادة غير موجودة'); }

    $newQty = (float)$item['quantity'] - $qty;
    if ($newQty < 0) {
        $db->rollBack();
        jsonResponse(false, 'الكمية المصروفة أكبر من المتاح (' . number_format((float)$item['quantity'], 3) . ' ' . $item['unit'] . ')');
    }

    $db->prepare('UPDATE inventory SET quantity=? WHERE id=?')->execute([$newQty, $invId]);
    $db->prepare('INSERT INTO inventory_transactions(inventory_id,type,quantity,balance_after,notes,user_id,shift_id) VALUES(?,?,?,?,?,?,?)')
       ->execute([$invId, 'out', $qty, $newQty, $notes, $_SESSION['user_id'], $shift['id'] ?? null]);
    $db->commit();

    $isLow = $newQty <= (float)$item['min_quantity'];
    $warn  = $isLow ? ' — تحذير: المخزون أصبح منخفضاً!' : '';
    jsonResponse(true, 'تم صرف ' . number_format($qty, 3) . ' ' . $item['unit'] . $warn, [
        'new_qty' => $newQty,
        'is_low'  => $isLow,
    ]);
}

if ($action === 'add') {
    if (!$canAdd) jsonResponse(false, 'ليس لديك صلاحية الإضافة');

    $invId = (int)($_POST['inventory_id'] ?? 0);
    $qty   = (float)($_POST['quantity'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $shift = getActiveShift();

    if (!$invId || $qty <= 0) jsonResponse(false, 'بيانات غير صحيحة');

    $db->beginTransaction();
    $item = fetchOne('SELECT * FROM inventory WHERE id=? AND is_active=1', [$invId]);
    if (!$item) { $db->rollBack(); jsonResponse(false, 'المادة غير موجودة'); }

    $newQty = (float)$item['quantity'] + $qty;

    $db->prepare('UPDATE inventory SET quantity=? WHERE id=?')->execute([$newQty, $invId]);
    $db->prepare('INSERT INTO inventory_transactions(inventory_id,type,quantity,balance_after,notes,user_id,shift_id) VALUES(?,?,?,?,?,?,?)')
       ->execute([$invId, 'in', $qty, $newQty, $notes, $_SESSION['user_id'], $shift['id'] ?? null]);
    $db->commit();

    $isLow = $newQty <= (float)$item['min_quantity'];
    jsonResponse(true, 'تم إضافة ' . number_format($qty, 3) . ' ' . $item['unit'], [
        'new_qty' => $newQty,
        'is_low'  => $isLow,
    ]);
}

if ($action === 'request_stock') {
    $invId   = (int)($_POST['inventory_id'] ?? 0);
    $qty     = (float)($_POST['quantity_needed'] ?? 0);
    $notes   = trim($_POST['notes'] ?? '');

    if (!$invId) jsonResponse(false, 'الصنف مطلوب');
    if ($qty <= 0) jsonResponse(false, 'أدخل الكمية المطلوبة');

    $item = fetchOne('SELECT name, unit FROM inventory WHERE id=? AND is_active=1', [$invId]);
    if (!$item) jsonResponse(false, 'الصنف غير موجود');

    $db->prepare('INSERT INTO stock_requests(inventory_id, user_id, quantity_needed, notes) VALUES(?,?,?,?)')
       ->execute([$invId, $_SESSION['user_id'], $qty, $notes]);

    jsonResponse(true, 'تم إرسال التنبيه للأدمن بنجاح');
}

jsonResponse(false, 'إجراء غير معروف');
