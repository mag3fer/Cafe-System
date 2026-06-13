<?php
// =====================================================
//  Database Configuration
//  انسخ الملف ده لـ database.php وعدّل القيم
//  Copy this file to database.php and edit the values
// =====================================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');          // XAMPP: فارغة عادةً | WAMP: فارغة أو كلمة السر
define('DB_NAME', 'cafe_management');

// Application Settings
define('APP_NAME', 'كافيه ماستر');
define('APP_VERSION', '1.0');
define('CURRENCY', 'ج.م');

// Base URL - change if not running at /cafe/
define('BASE_URL', '/cafe');

// =====================================================

function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } catch (PDOException $e) {
        $isApi = (defined('IS_API') && IS_API) ||
                 str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
        if ($isApi) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'خطأ في الاتصال بقاعدة البيانات']);
            exit;
        }
        die('
        <div style="font-family:Cairo,Tahoma,sans-serif;direction:rtl;text-align:center;margin-top:100px;color:#c0392b;">
            <h2>⚠ خطأ في الاتصال بقاعدة البيانات</h2>
            <p>يرجى التحقق من إعدادات الملف <code>config/database.php</code></p>
            <p>تأكد من تشغيل MySQL وإنشاء قاعدة البيانات <strong>' . DB_NAME . '</strong></p>
        </div>');
    }
    return $pdo;
}
