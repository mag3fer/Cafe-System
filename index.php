<?php
require_once __DIR__ . '/config/auth.php';

if (!isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}
if ($_SESSION['user_role'] === 'admin') {
    header('Location: ' . BASE_URL . '/admin/');
} else {
    header('Location: ' . BASE_URL . '/cashier/');
}
exit;
