<?php
session_start();
error_log('check.php: session = ' . print_r($_SESSION, true));
header('Content-Type: application/json');
echo json_encode([
    'session' => $_SESSION ?? null,
    'user' => $_SESSION['user'] ?? null,
    'is_logged_in' => isset($_SESSION['user'])
]);