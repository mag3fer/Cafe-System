<?php
define('IS_API', true);
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireApiLogin();

$db     = getDB();
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$id     = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($action === 'save' && $_SESSION['user_role'] === 'admin') {
    $number   = (int)($_POST['number'] ?? 0);
    $name     = trim($_POST['name'] ?? '');
    $capacity = (int)($_POST['capacity'] ?? 4);

    if ($number <= 0) jsonResponse(false, 'رقم الطاولة مطلوب');

    if ($id) {
        $db->prepare('UPDATE cafe_tables SET number=?,name=?,capacity=? WHERE id=?')
           ->execute([$number, $name ?: "طاولة $number", $capacity, $id]);
        jsonResponse(true, 'تم تعديل الطاولة بنجاح');
    } else {
        // Check duplicate
        $exists = $db->prepare('SELECT id FROM cafe_tables WHERE number=?');
        $exists->execute([$number]);
        if ($exists->fetch()) jsonResponse(false, 'رقم الطاولة موجود بالفعل');
        $db->prepare('INSERT INTO cafe_tables(number,name,capacity) VALUES(?,?,?)')
           ->execute([$number, $name ?: "طاولة $number", $capacity]);
        jsonResponse(true, 'تم إضافة الطاولة بنجاح');
    }
}

if ($action === 'delete' && $_SESSION['user_role'] === 'admin') {
    $stmt = $db->prepare("SELECT status FROM cafe_tables WHERE id=?");
    $stmt->execute([$id]);
    $t = $stmt->fetch();
    if (!$t) jsonResponse(false, 'الطاولة غير موجودة');
    if ($t['status'] === 'occupied') jsonResponse(false, 'لا يمكن حذف طاولة مشغولة');
    $db->prepare('DELETE FROM cafe_tables WHERE id=?')->execute([$id]);
    jsonResponse(true, 'تم الحذف بنجاح');
}

// Dashboard table map HTML
if ($action === 'map') {
    $tables = $db->query("
        SELECT t.*, o.id as order_id, o.total as order_total, o.opened_at,
               TIMESTAMPDIFF(SECOND, o.opened_at, NOW()) as elapsed_sec,
               u.name as cashier_name
        FROM cafe_tables t
        LEFT JOIN orders o ON o.table_id=t.id AND o.status='open'
        LEFT JOIN users u ON u.id=o.user_id
        ORDER BY t.number
    ")->fetchAll();

    $html = '';
    foreach ($tables as $t) {
        $isVip     = !empty($t['is_vip']);
        $cardClass = $t['status'] . ($isVip ? ' vip' : '');
        $clickAttr = $t['status'] === 'occupied' ? "onclick='showOrderDetails({$t['order_id']})' style='cursor:pointer'" : '';
        $vipBadge  = $isVip ? "<span class='tc-badge vip-tag'><i class='fa fa-crown'></i> VIP</span>" : '';
        $statusIcon = $t['status'] === 'available' ? "<i class='fa fa-circle-check'></i> متاحة" : "<i class='fa fa-fire'></i> مشغولة";
        $cur        = defined('CURRENCY') ? CURRENCY : 'ج.م';

        if ($t['status'] === 'occupied') {
            $elapsed = max(0, (int)$t['elapsed_sec']);
            $body = "<div><div class='tc-amount'>" . number_format((float)$t['order_total'], 2) . "</div>
                     <div class='tc-amount-cur'>{$cur}</div></div>
                     <div class='tc-timer' data-elapsed='{$elapsed}'>
                       <i class='fa fa-clock me-1'></i><span class='timer-val'>...</span>
                     </div>";
            if ($t['cashier_name']) {
                $body .= "<div class='tc-cashier'><i class='fa fa-user me-1'></i>" . htmlspecialchars($t['cashier_name']) . "</div>";
            }
        } else {
            $body = "<div class='tc-capacity'><i class='fa fa-users me-1'></i>{$t['capacity']} أشخاص</div>";
        }

        $html .= "
        <div class='table-card {$cardClass}' {$clickAttr}>
          <div class='tc-header'>
            <div class='tc-num'>{$t['number']}</div>
            <div class='tc-name'>" . htmlspecialchars($t['name']) . "</div>
            <div class='tc-badges'>
              <span class='tc-badge {$t['status']}'>{$statusIcon}</span>{$vipBadge}
            </div>
          </div>
          <div class='tc-body'>{$body}</div>
        </div>";
    }
    jsonResponse(true, '', ['html' => $html]);
}

// Order detail for modal
if ($action === 'order_detail') {
    $orderId = (int)($_GET['id'] ?? 0);
    $order   = fetchOne("SELECT o.*,t.number as tnum,u.name as cashier FROM orders o JOIN cafe_tables t ON t.id=o.table_id JOIN users u ON u.id=o.user_id WHERE o.id=?", [$orderId]);
    if (!$order) jsonResponse(false, 'الطلب غير موجود');

    $items = fetchAll("SELECT * FROM order_items WHERE order_id=?", [$orderId]);

    $html = "<div class='mb-3'><strong>طاولة {$order['tnum']}</strong> — {$order['cashier']}<br>
             <small class='text-muted'>منذ: " . formatDateAr($order['opened_at']) . "</small></div>
             <table class='table table-sm'>
             <thead><tr><th>الصنف</th><th>الكمية</th><th>السعر</th><th>الإجمالي</th></tr></thead><tbody>";
    foreach ($items as $item) {
        $html .= "<tr><td>" . htmlspecialchars($item['item_name']) . "</td>
                  <td>{$item['quantity']}</td>
                  <td>" . money((float)$item['price']) . "</td>
                  <td class='fw-bold'>" . money((float)$item['subtotal']) . "</td></tr>";
    }
    $html .= "</tbody><tfoot><tr class='table-warning'><td colspan='3' class='fw-bold'>المجموع</td><td class='fw-bold'>" . money((float)$order['total']) . "</td></tr></tfoot></table>";
    jsonResponse(true, '', ['html' => $html]);
}

jsonResponse(false, 'إجراء غير معروف');
