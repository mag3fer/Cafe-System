<?php
define('IS_API', true);
require_once __DIR__ . '/../../config/auth.php';
requireApiLogin();
if ($_SESSION['user_role'] !== 'admin') jsonResponse(false, 'غير مصرح');

$db     = getDB();
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$id     = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($action === 'save') {
    $name     = trim($_POST['name'] ?? '');
    $catId    = (int)($_POST['category_id'] ?? 0);
    $price    = (float)($_POST['price'] ?? 0);
    $cost     = (float)($_POST['cost'] ?? 0);
    $desc     = trim($_POST['description'] ?? '');
    $active   = (int)($_POST['is_active'] ?? 1);
    $invId    = (int)($_POST['inventory_id'] ?? 0) ?: null;
    $invQty   = max(0.001, (float)($_POST['inventory_qty'] ?? 1));

    if (!$name) jsonResponse(false, 'الاسم مطلوب');
    if ($price <= 0) jsonResponse(false, 'السعر يجب أن يكون أكبر من صفر');

    if ($id) {
        $db->prepare('UPDATE items SET category_id=?,name=?,price=?,cost=?,description=?,is_active=?,inventory_id=?,inventory_qty=? WHERE id=?')
           ->execute([$catId ?: null, $name, $price, $cost, $desc, $active, $invId, $invQty, $id]);
        jsonResponse(true, 'تم تعديل الصنف بنجاح');
    } else {
        $db->prepare('INSERT INTO items(category_id,name,price,cost,description,is_active,inventory_id,inventory_qty) VALUES(?,?,?,?,?,?,?,?)')
           ->execute([$catId ?: null, $name, $price, $cost, $desc, $active, $invId, $invQty]);
        jsonResponse(true, 'تم إضافة الصنف بنجاح');
    }
}

if ($action === 'delete') {
    $db->prepare('UPDATE items SET is_active=0 WHERE id=?')->execute([$id]);
    jsonResponse(true, 'تم حذف الصنف بنجاح');
}

// Return all active items (for POS)
if ($action === 'list') {
    $items = $db->query("SELECT i.*,c.name as cat_name FROM items i LEFT JOIN categories c ON c.id=i.category_id WHERE i.is_active=1 ORDER BY c.sort_order,i.name")->fetchAll();
    jsonResponse(true, '', ['items' => $items]);
}

jsonResponse(false, 'إجراء غير معروف');
