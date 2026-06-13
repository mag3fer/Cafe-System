<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
require_once __DIR__ . '/database.php';

// ── Auth Guards ───────────────────────────────────────────

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if ($_SESSION['user_role'] !== 'admin') {
        header('Location: ' . BASE_URL . '/cashier/');
        exit;
    }
}

function requireCashier(): void {
    requireLogin();
    if ($_SESSION['user_role'] === 'admin') return;
    if (!hasPermission('manage_orders')) {
        http_response_code(403);
        die('<div style="font-family:Cairo,sans-serif;direction:rtl;text-align:center;margin-top:100px">
             <h2>403 - غير مصرح لك بالوصول</h2>
             <a href="' . BASE_URL . '/cashier/">رجوع</a></div>');
    }
}

// ── Permission Helpers ────────────────────────────────────

function hasPermission(string $perm): bool {
    if (!isLoggedIn()) return false;
    if (($_SESSION['user_role'] ?? '') === 'admin') return true;
    return in_array($perm, $_SESSION['permissions'] ?? [], true);
}

function anyPermission(array $perms): bool {
    foreach ($perms as $p) {
        if (hasPermission($p)) return true;
    }
    return false;
}

// ── Session Management ────────────────────────────────────

function loginUser(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id']     = $user['id'];
    $_SESSION['user_name']   = $user['name'];
    $_SESSION['user_role']   = $user['role'];
    $_SESSION['permissions'] = json_decode($user['permissions'] ?? '[]', true) ?? [];
}

function logoutUser(): void {
    session_unset();
    session_destroy();
}

// ── Data Helpers ──────────────────────────────────────────

function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    $db   = getDB();
    $stmt = $db->prepare('SELECT id,name,username,role,permissions FROM users WHERE id=? AND is_active=1');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function getActiveShift(): ?array {
    if (!isLoggedIn()) return null;
    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM shifts WHERE user_id=? AND check_out IS NULL ORDER BY check_in DESC LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

// ── API Response Helper ───────────────────────────────────

function jsonResponse(bool $success, string $message = '', array $data = []): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

function requireApiLogin(): void {
    if (!isLoggedIn()) {
        jsonResponse(false, 'غير مسجّل الدخول');
    }
}
