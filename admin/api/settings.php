<?php
define('IS_API', true);
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireAdmin();

runMigrations();

$db = getDB();
$keys = [
    'cafe_name','cafe_phone','cafe_address',
    'receipt_header','receipt_footer1','receipt_footer2',
    'tax_enabled','tax_percent','tax_label',
    'service_enabled','service_percent','service_label',
];

foreach ($keys as $k) {
    if (isset($_POST[$k])) {
        $db->prepare("INSERT INTO `settings` (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)")
           ->execute([$k, trim($_POST[$k])]);
    }
}

jsonResponse(true, 'تم حفظ الإعدادات بنجاح');
