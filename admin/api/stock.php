<?php
define('IS_API', true);
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireApiLogin();
if (!in_array($_SESSION['user_role'], ['admin']) && !hasPermission('manage_stock')) jsonResponse(false, 'غير مصرح');

$db     = getDB();
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$id     = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($action === 'save') {
    $name  = trim($_POST['name'] ?? '');
    $unit  = trim($_POST['unit'] ?? 'قطعة');
    $qty   = (float)($_POST['quantity'] ?? 0);
    $min   = (float)($_POST['min_quantity'] ?? 0);
    $cost  = (float)($_POST['cost_per_unit'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if (!$name) jsonResponse(false, 'الاسم مطلوب');

    if ($id) {
        $db->prepare('UPDATE inventory SET name=?,unit=?,quantity=?,min_quantity=?,cost_per_unit=?,notes=? WHERE id=?')
           ->execute([$name, $unit, $qty, $min, $cost, $notes, $id]);
        jsonResponse(true, 'تم تعديل المادة');
    } else {
        $db->prepare('INSERT INTO inventory(name,unit,quantity,min_quantity,cost_per_unit,notes) VALUES(?,?,?,?,?,?)')
           ->execute([$name, $unit, $qty, $min, $cost, $notes]);
        jsonResponse(true, 'تم إضافة المادة');
    }
}

if ($action === 'transaction') {
    $invId = (int)($_POST['inventory_id'] ?? 0);
    $type  = $_POST['type'] ?? 'in';
    $qty   = (float)($_POST['quantity'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if (!$invId || $qty <= 0) jsonResponse(false, 'بيانات غير صحيحة');

    $db->beginTransaction();
    $item = fetchOne('SELECT * FROM inventory WHERE id=?', [$invId]);
    if (!$item) { $db->rollBack(); jsonResponse(false, 'المادة غير موجودة'); }

    if ($type === 'in') {
        $newQty = $item['quantity'] + $qty;
    } elseif ($type === 'out') {
        $newQty = $item['quantity'] - $qty;
    } elseif ($type === 'adjustment') {
        $newQty = $qty;
    } else {
        $newQty = $item['quantity'];
    }

    if ($newQty < 0) { $db->rollBack(); jsonResponse(false, 'الكمية المصروفة أكبر من المتاح'); }

    $shift = getActiveShift();
    $db->prepare('UPDATE inventory SET quantity=? WHERE id=?')->execute([$newQty, $invId]);
    $db->prepare('INSERT INTO inventory_transactions(inventory_id,type,quantity,balance_after,notes,user_id,shift_id) VALUES(?,?,?,?,?,?,?)')
       ->execute([$invId, $type, $qty, $newQty, $notes, $_SESSION['user_id'], $shift['id'] ?? null]);
    $db->commit();

    jsonResponse(true, 'تم تسجيل الحركة. الرصيد الجديد: ' . number_format($newQty, 3));
}

if ($action === 'reset_all') {
    if ($_SESSION['user_role'] !== 'admin') jsonResponse(false, 'للأدمن فقط');

    $password = $_POST['password'] ?? '';
    if (!$password) jsonResponse(false, 'كلمة المرور مطلوبة');

    $admin = fetchOne('SELECT password FROM users WHERE id=?', [$_SESSION['user_id']]);
    if (!$admin || !password_verify($password, $admin['password'])) {
        jsonResponse(false, 'كلمة المرور غير صحيحة');
    }

    $shift  = getActiveShift();
    $items  = fetchAll('SELECT id, quantity FROM inventory WHERE is_active=1');
    $db->beginTransaction();
    foreach ($items as $item) {
        $db->prepare('UPDATE inventory SET quantity=0 WHERE id=?')->execute([$item['id']]);
        $db->prepare('INSERT INTO inventory_transactions(inventory_id,type,quantity,balance_after,notes,user_id,shift_id) VALUES(?,?,?,?,?,?,?)')
           ->execute([$item['id'], 'adjustment', 0, 0, 'تصفير شامل للمخزون — جرد', $_SESSION['user_id'], $shift['id'] ?? null]);
    }
    $db->commit();
    jsonResponse(true, 'تم تصفير ' . count($items) . ' صنف بنجاح');
}

if ($action === 'mark_request_read') {
    $reqId = (int)($_POST['request_id'] ?? 0);
    if ($reqId) {
        $db->prepare('UPDATE stock_requests SET is_read=1 WHERE id=?')->execute([$reqId]);
        jsonResponse(true, 'تم');
    }
    jsonResponse(false, 'معرّف غير صحيح');
}

if ($action === 'mark_all_requests_read') {
    $db->exec('UPDATE stock_requests SET is_read=1 WHERE is_read=0');
    jsonResponse(true, 'تم تعليم الكل كمقروء');
}

if ($action === 'delete') {
    $db->prepare('UPDATE inventory SET is_active=0 WHERE id=?')->execute([$id]);
    jsonResponse(true, 'تم الحذف');
}

if ($action === 'history') {
    $rows = fetchAll("
        SELECT t.*, u.name as user_name
        FROM inventory_transactions t
        LEFT JOIN users u ON u.id=t.user_id
        WHERE t.inventory_id=?
        ORDER BY t.created_at DESC LIMIT 50
    ", [$id]);

    $html = '<table class="table table-sm"><thead><tr><th>النوع</th><th>الكمية</th><th>الرصيد بعد</th><th>ملاحظات</th><th>الموظف</th><th>التاريخ</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $typeLbl = ['in'=>'<span class="badge bg-success">وارد</span>','out'=>'<span class="badge bg-danger">صادر</span>','adjustment'=>'<span class="badge bg-warning text-dark">تسوية</span>'][$r['type']] ?? '';
        $html   .= "<tr><td>{$typeLbl}</td><td>{$r['quantity']}</td><td>{$r['balance_after']}</td>
                    <td>" . htmlspecialchars($r['notes'] ?? '—') . "</td>
                    <td>" . htmlspecialchars($r['user_name'] ?? '—') . "</td>
                    <td>" . formatDateAr($r['created_at']) . "</td></tr>";
    }
    if (empty($rows)) $html .= '<tr><td colspan="6" class="text-center text-muted py-3">لا توجد حركات</td></tr>';
    $html .= '</tbody></table>';
    jsonResponse(true, '', ['html' => $html]);
}

jsonResponse(false, 'إجراء غير معروف');
