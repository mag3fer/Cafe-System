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
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $phone    = trim($_POST['phone'] ?? '');
    $role     = $_POST['role'] ?? 'cashier';
    $active   = (int)($_POST['is_active'] ?? 1);
    $perms    = $_POST['permissions'] ?? [];

    if (!$name || !$username) jsonResponse(false, 'الاسم واسم المستخدم مطلوبان');

    // Check username unique
    $chk = $db->prepare('SELECT id FROM users WHERE username=? AND id!=?');
    $chk->execute([$username, $id]);
    if ($chk->fetch()) jsonResponse(false, 'اسم المستخدم مستخدم بالفعل');

    $permsJson = json_encode(array_values($perms));

    if ($id) {
        if ($password) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $db->prepare('UPDATE users SET name=?,username=?,password=?,phone=?,role=?,permissions=?,is_active=? WHERE id=?')
               ->execute([$name, $username, $hash, $phone, $role, $permsJson, $active, $id]);
        } else {
            $db->prepare('UPDATE users SET name=?,username=?,phone=?,role=?,permissions=?,is_active=? WHERE id=?')
               ->execute([$name, $username, $phone, $role, $permsJson, $active, $id]);
        }
        jsonResponse(true, 'تم تعديل بيانات الموظف');
    } else {
        if (!$password) jsonResponse(false, 'كلمة المرور مطلوبة');
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $db->prepare('INSERT INTO users(name,username,password,phone,role,permissions,is_active) VALUES(?,?,?,?,?,?,?)')
           ->execute([$name, $username, $hash, $phone, $role, $permsJson, $active]);
        jsonResponse(true, 'تم إضافة الموظف بنجاح');
    }
}

if ($action === 'delete') {
    if ($id === (int)$_SESSION['user_id']) jsonResponse(false, 'لا يمكنك حذف حسابك الخاص');
    $db->prepare('UPDATE users SET is_active=0 WHERE id=?')->execute([$id]);
    jsonResponse(true, 'تم تعطيل الحساب');
}

jsonResponse(false, 'إجراء غير معروف');
