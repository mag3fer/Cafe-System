<?php
require_once __DIR__ . '/config/auth.php';
logoutUser();
header('Location: ' . BASE_URL . '/login.php');
exit;
