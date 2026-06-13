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
    $desc     = trim($_POST['description'] ?? '');
    $order    = (int)($_POST['sort_order'] ?? 0);
    $active   = (int)($_POST['is_active'] ?? 1);

    if (!$name) jsonResponse(false, 'الاسم مطلوب');

    if ($id) {
        $db->prepare('UPDATE categories SET name=?,description=?,sort_order=?,is_active=? WHERE id=?')
           ->execute([$name, $desc, $order, $active, $id]);
        jsonResponse(true, 'تم تعديل الفئة بنجاح');
    } else {
        $db->prepare('INSERT INTO categories(name,description,sort_order,is_active) VALUES(?,?,?,?)')
           ->execute([$name, $desc, $order, $active]);
        jsonResponse(true, 'تم إضافة الفئة بنجاح');
    }
}

if ($action === 'delete' || ($_POST['action'] ?? '') === 'delete') {
    // Check items using this category
    $used = (int)$db->prepare('SELECT COUNT(*) FROM items WHERE category_id=?')->execute([$id]) && false;
    $stmt = $db->prepare('SELECT COUNT(*) FROM items WHERE category_id=?');
    $stmt->execute([$id]);
    if ((int)$stmt->fetchColumn() > 0) {
        jsonResponse(false, 'لا يمكن الحذف، توجد أصناف مرتبطة بهذه الفئة');
    }
    $db->prepare('DELETE FROM categories WHERE id=?')->execute([$id]);
    jsonResponse(true, 'تم الحذف بنجاح');
}

jsonResponse(false, 'إجراء غير معروف');
