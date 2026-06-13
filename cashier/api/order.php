<?php
define('IS_API', true);
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireApiLogin();

$db     = getDB();
$action = $_POST['action'] ?? '';

// ── Add Item to Order ──────────────────────────────
if ($action === 'add_item') {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $itemId  = (int)($_POST['item_id']  ?? 0);

    $order = fetchOne("SELECT * FROM orders WHERE id=? AND status='open'", [$orderId]);
    if (!$order) jsonResponse(false, 'الطلب غير موجود أو مغلق');

    $item = fetchOne('SELECT * FROM items WHERE id=? AND is_active=1', [$itemId]);
    if (!$item) jsonResponse(false, 'الصنف غير موجود');

    // Check if already in order
    $existing = fetchOne('SELECT * FROM order_items WHERE order_id=? AND item_id=?', [$orderId, $itemId]);
    if ($existing) {
        $newQty = $existing['quantity'] + 1;
        $newSub = $newQty * $item['price'];
        $db->prepare('UPDATE order_items SET quantity=?,subtotal=? WHERE id=?')
           ->execute([$newQty, $newSub, $existing['id']]);
        $oi = array_merge($existing, ['quantity' => $newQty, 'subtotal' => $newSub]);
    } else {
        $db->prepare('INSERT INTO order_items(order_id,item_id,item_name,quantity,price,subtotal) VALUES(?,?,?,1,?,?)')
           ->execute([$orderId, $itemId, $item['name'], $item['price'], $item['price']]);
        $oi = fetchOne('SELECT * FROM order_items WHERE id=?', [(int)$db->lastInsertId()]);
    }

    // Recalculate order total
    $total = $db->prepare('SELECT COALESCE(SUM(subtotal),0) FROM order_items WHERE order_id=?');
    $total->execute([$orderId]);
    $newTotal = (float)$total->fetchColumn();
    $db->prepare('UPDATE orders SET total=?,final_total=? WHERE id=?')->execute([$newTotal, $newTotal, $orderId]);

    jsonResponse(true, '', ['order_item' => $oi, 'subtotal' => $newTotal]);
}

// ── Update Quantity ────────────────────────────────
if ($action === 'update_qty') {
    $oiId  = (int)($_POST['oi_id'] ?? 0);
    $delta = (int)($_POST['delta'] ?? 0);

    $oi = fetchOne('SELECT * FROM order_items WHERE id=?', [$oiId]);
    if (!$oi) jsonResponse(false, 'السجل غير موجود');

    $newQty = $oi['quantity'] + $delta;

    if ($newQty <= 0) {
        $db->prepare('DELETE FROM order_items WHERE id=?')->execute([$oiId]);
        $removed = true;
    } else {
        $newSub = $newQty * $oi['price'];
        $db->prepare('UPDATE order_items SET quantity=?,subtotal=? WHERE id=?')
           ->execute([$newQty, $newSub, $oiId]);
        $removed = false;
    }

    $total = $db->prepare('SELECT COALESCE(SUM(subtotal),0) FROM order_items WHERE order_id=?');
    $total->execute([$oi['order_id']]);
    $newTotal = (float)$total->fetchColumn();
    $db->prepare('UPDATE orders SET total=?,final_total=? WHERE id=?')->execute([$newTotal, $newTotal, $oi['order_id']]);

    jsonResponse(true, '', [
        'removed'     => $removed,
        'quantity'    => $newQty,
        'subtotal'    => $removed ? 0 : $newSub,
        'order_total' => $newTotal,
    ]);
}

// ── Remove Item ────────────────────────────────────
if ($action === 'remove_item') {
    $oiId = (int)($_POST['oi_id'] ?? 0);
    $oi   = fetchOne('SELECT * FROM order_items WHERE id=?', [$oiId]);
    if (!$oi) jsonResponse(false, 'السجل غير موجود');
    $db->prepare('DELETE FROM order_items WHERE id=?')->execute([$oiId]);

    $total = $db->prepare('SELECT COALESCE(SUM(subtotal),0) FROM order_items WHERE order_id=?');
    $total->execute([$oi['order_id']]);
    $newTotal = (float)$total->fetchColumn();
    $db->prepare('UPDATE orders SET total=?,final_total=? WHERE id=?')->execute([$newTotal, $newTotal, $oi['order_id']]);

    jsonResponse(true, '', ['order_total' => $newTotal]);
}

// ── Close Order ────────────────────────────────────
if ($action === 'close_order') {
    $orderId    = (int)($_POST['order_id'] ?? 0);
    $discount   = (float)($_POST['discount'] ?? 0);
    $discType   = in_array($_POST['discount_type'] ?? 'amount', ['amount','percent']) ? $_POST['discount_type'] : 'amount';
    $payment    = in_array($_POST['payment_method'] ?? 'cash', ['cash','card','instapay','split','other']) ? $_POST['payment_method'] : 'cash';
    $notes      = trim($_POST['notes'] ?? '');

    $cashAmount    = (float)($_POST['cash_amount']    ?? 0);
    $cardAmount    = (float)($_POST['card_amount']    ?? 0);
    $instapayAmount = (float)($_POST['instapay_amount'] ?? 0);

    $order = fetchOne("SELECT * FROM orders WHERE id=? AND status='open'", [$orderId]);
    if (!$order) jsonResponse(false, 'الطلب غير موجود');

    $total       = (float)$order['total'];
    $discAmount  = $discType === 'percent' ? $total * $discount / 100 : $discount;
    $discAmount  = max(0, min($discAmount, $total));
    $afterDisc   = $total - $discAmount;

    $svcEnabled  = getSetting('service_enabled', '0') === '1';
    $taxEnabled  = getSetting('tax_enabled', '0') === '1';
    $svcAmount   = $svcEnabled ? round($afterDisc * (float)getSetting('service_percent', '0') / 100, 2) : 0;
    $taxAmount   = $taxEnabled ? round($afterDisc * (float)getSetting('tax_percent', '0') / 100, 2) : 0;
    $finalTotal  = $afterDisc + $svcAmount + $taxAmount;

    try {
        $db->prepare("UPDATE orders SET status='closed', discount=?, discount_type=?, final_total=?, tax_amount=?, service_amount=?, payment_method=?, notes=?, cash_amount=?, card_amount=?, instapay_amount=?, closed_at=NOW() WHERE id=?")
           ->execute([$discAmount, $discType, $finalTotal, $taxAmount, $svcAmount, $payment, $notes, $cashAmount, $cardAmount, $instapayAmount, $orderId]);
    } catch (PDOException $e) {
        $db->prepare("UPDATE orders SET status='closed', discount=?, discount_type=?, final_total=?, payment_method=?, notes=?, closed_at=NOW() WHERE id=?")
           ->execute([$discAmount, $discType, $finalTotal, $payment, $notes, $orderId]);
    }

    // Free the table
    $db->prepare("UPDATE cafe_tables SET status='available' WHERE id=?")->execute([$order['table_id']]);

    // Auto-deduct linked inventory items
    $linkedItems = fetchAll(
        "SELECT oi.quantity AS sold_qty, i.inventory_id, i.inventory_qty, inv.quantity AS inv_balance, inv.name AS inv_name, inv.unit
         FROM order_items oi
         JOIN items i ON i.id = oi.item_id
         JOIN inventory inv ON inv.id = i.inventory_id
         WHERE oi.order_id = ? AND i.inventory_id IS NOT NULL",
        [$orderId]
    );
    foreach ($linkedItems as $li) {
        $deduct  = $li['inventory_qty'] * $li['sold_qty'];
        $newBal  = max(0, $li['inv_balance'] - $deduct);
        $db->prepare("UPDATE inventory SET quantity=? WHERE id=?")
           ->execute([$newBal, $li['inventory_id']]);
        $db->prepare("INSERT INTO inventory_transactions (inventory_id,user_id,type,quantity,balance_after,notes) VALUES (?,?,?,?,?,?)")
           ->execute([$li['inventory_id'], $_SESSION['user_id'], 'out', $deduct, $newBal, 'بيع — أوردر #' . $orderId]);
    }

    // Reassign order to the closing cashier's shift, then update that shift's totals
    $closingShift = getActiveShift();
    if ($closingShift) {
        $db->prepare("UPDATE orders SET shift_id=? WHERE id=?")->execute([$closingShift['id'], $orderId]);
        $db->prepare("UPDATE shifts SET total_sales=total_sales+?, total_orders=total_orders+1 WHERE id=?")
           ->execute([$finalTotal, $closingShift['id']]);
    }

    jsonResponse(true, 'تم إغلاق الطلب بنجاح', ['final_total' => $finalTotal]);
}

// ── Cancel Order ───────────────────────────────────
if ($action === 'cancel_order') {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $order   = fetchOne("SELECT * FROM orders WHERE id=? AND status='open'", [$orderId]);
    if (!$order) jsonResponse(false, 'الطلب غير موجود');

    $db->prepare("UPDATE orders SET status='cancelled', closed_at=NOW() WHERE id=?")->execute([$orderId]);
    $db->prepare("UPDATE cafe_tables SET status='available' WHERE id=?")->execute([$order['table_id']]);

    jsonResponse(true, 'تم إلغاء الطلب');
}

// ── Stock Transaction (from cashier) ──────────────
if ($action === 'stock_tx') {
    if (!hasPermission('manage_stock')) jsonResponse(false, 'غير مصرح');

    $invId = (int)($_POST['inventory_id'] ?? 0);
    $type  = in_array($_POST['type'] ?? '', ['in','out','adjustment']) ? $_POST['type'] : 'in';
    $qty   = (float)($_POST['quantity'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if (!$invId || $qty <= 0) jsonResponse(false, 'بيانات غير صحيحة');

    $item = fetchOne('SELECT * FROM inventory WHERE id=?', [$invId]);
    if (!$item) jsonResponse(false, 'المادة غير موجودة');

    if ($type === 'in') {
        $newQty = $item['quantity'] + $qty;
    } elseif ($type === 'out') {
        $newQty = $item['quantity'] - $qty;
    } else {
        $newQty = $qty;
    }
    if ($newQty < 0) jsonResponse(false, 'الكمية المصروفة أكبر من المتاح في المخزون');

    $db->prepare('UPDATE inventory SET quantity=? WHERE id=?')->execute([$newQty, $invId]);
    $db->prepare('INSERT INTO inventory_transactions(inventory_id,type,quantity,balance_after,notes,user_id) VALUES(?,?,?,?,?,?)')
       ->execute([$invId, $type, $qty, $newQty, $notes, $_SESSION['user_id']]);

    jsonResponse(true, 'تم تسجيل الحركة. الرصيد الجديد: ' . number_format($newQty, 3));
}

jsonResponse(false, 'إجراء غير معروف');
