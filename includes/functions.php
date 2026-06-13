<?php
require_once __DIR__ . '/../config/database.php';

function appName(): string {
    return getSetting('cafe_name', APP_NAME);
}

function money(float $amount): string {
    return number_format($amount, 2) . ' ' . CURRENCY;
}

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)   return 'الآن';
    if ($diff < 3600) return floor($diff / 60) . ' دقيقة';
    if ($diff < 86400) return floor($diff / 3600) . ' ساعة';
    return date('Y-m-d', strtotime($datetime));
}

function formatDateAr(string $datetime): string {
    return date('Y/m/d h:i A', strtotime($datetime));
}

function shiftDuration(string $checkIn, ?string $checkOut = null): string {
    if (!$checkOut) {
        try {
            $row = fetchOne("SELECT NOW() as t");
            $checkOut = $row['t'];
        } catch (Exception $e) {
            $checkOut = date('Y-m-d H:i:s');
        }
    }
    $diff = max(0, strtotime($checkOut) - strtotime($checkIn));
    $h    = floor($diff / 3600);
    $m    = floor(($diff % 3600) / 60);
    return sprintf('%02d:%02d', $h, $m) . ' ساعة';
}

function generateOrderNumber(int $orderId): string {
    return 'ORD-' . str_pad($orderId, 5, '0', STR_PAD_LEFT);
}

// ── DB Helpers ─────────────────────────────────────────────

function fetchAll(string $sql, array $params = []): array {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function fetchOne(string $sql, array $params = []): ?array {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch() ?: null;
}

function execute(string $sql, array $params = []): int {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return (int) getDB()->lastInsertId();
}

// ── Table Stats ────────────────────────────────────────────

function getTableStats(): array {
    $db  = getDB();
    $all = (int) $db->query('SELECT COUNT(*) FROM cafe_tables')->fetchColumn();
    $occ = (int) $db->query("SELECT COUNT(*) FROM cafe_tables WHERE status='occupied'")->fetchColumn();
    return ['total' => $all, 'occupied' => $occ, 'available' => $all - $occ];
}

function getTodayStats(): array {
    $db  = getDB();
    $sql = "SELECT
              COUNT(*) as orders,
              COALESCE(SUM(final_total),0) as sales
            FROM orders
            WHERE status='closed' AND DATE(closed_at) = CURDATE()";
    $row = $db->query($sql)->fetch();
    return ['orders' => (int)$row['orders'], 'sales' => (float)$row['sales']];
}

function getActiveShiftsCount(): int {
    return (int) getDB()->query('SELECT COUNT(*) FROM shifts WHERE check_out IS NULL')->fetchColumn();
}

function getLowStockCount(): int {
    return (int) getDB()->query('SELECT COUNT(*) FROM inventory WHERE quantity <= min_quantity AND is_active=1')->fetchColumn();
}

function getPendingStockRequests(): int {
    try {
        return (int) getDB()->query('SELECT COUNT(*) FROM stock_requests WHERE is_read=0')->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

// ── Settings ───────────────────────────────────────────────

function getSetting(string $key, string $default = ''): string {
    try {
        static $cache = [];
        if (array_key_exists($key, $cache)) return $cache[$key];
        $row = fetchOne("SELECT `value` FROM `settings` WHERE `key`=?", [$key]);
        $cache[$key] = ($row !== null) ? (string)$row['value'] : $default;
        return $cache[$key];
    } catch (PDOException $e) {
        return $default;
    }
}

function runMigrations(): void {
    $db = getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS `settings` (
        `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `key`        VARCHAR(100) NOT NULL UNIQUE,
        `value`      TEXT,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $defaults = [
        'cafe_name'       => APP_NAME,
        'cafe_phone'      => '',
        'cafe_address'    => '',
        'receipt_header'  => 'كافيه احترافي — نظام إدارة متكامل',
        'cafe_logo'       => '',
        'receipt_footer1' => 'شكراً لزيارتكم ✨',
        'receipt_footer2' => 'نأمل أن تعودوا مرة أخرى',
        'tax_enabled'     => '0',
        'tax_percent'     => '14',
        'tax_label'       => 'ضريبة القيمة المضافة',
        'service_enabled' => '0',
        'service_percent' => '12',
        'service_label'   => 'رسوم الخدمة',
    ];
    foreach ($defaults as $k => $v) {
        $db->prepare("INSERT IGNORE INTO `settings` (`key`, `value`) VALUES (?, ?)")->execute([$k, $v]);
    }

    try { $db->exec("ALTER TABLE `orders` ADD COLUMN `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0"); }
    catch (PDOException $e) {}
    try { $db->exec("ALTER TABLE `orders` ADD COLUMN `service_amount` DECIMAL(10,2) NOT NULL DEFAULT 0"); }
    catch (PDOException $e) {}
    try { $db->exec("ALTER TABLE `cafe_tables` ADD COLUMN `is_vip` TINYINT(1) NOT NULL DEFAULT 0"); }
    catch (PDOException $e) {}
    try { $db->exec("ALTER TABLE `inventory_transactions` ADD COLUMN `shift_id` INT UNSIGNED DEFAULT NULL"); }
    catch (PDOException $e) {}
    try { $db->exec("ALTER TABLE `items` ADD COLUMN `inventory_id` INT UNSIGNED DEFAULT NULL"); }
    catch (PDOException $e) {}
    try { $db->exec("ALTER TABLE `items` ADD COLUMN `inventory_qty` DECIMAL(10,3) NOT NULL DEFAULT 1.000"); }
    catch (PDOException $e) {}
    try { $db->exec("CREATE TABLE IF NOT EXISTS `stock_requests` (
        `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `inventory_id`    INT UNSIGNED NOT NULL,
        `user_id`         INT UNSIGNED NOT NULL,
        `quantity_needed` DECIMAL(10,3) NOT NULL DEFAULT 0,
        `notes`           TEXT,
        `is_read`         TINYINT(1) NOT NULL DEFAULT 0,
        `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); }
    catch (PDOException $e) {}
}
