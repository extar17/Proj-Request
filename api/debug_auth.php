<?php
// Включаем ВСЕ ошибки
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Логируем всё в файл
$logFile = __DIR__ . '/debug.log';
file_put_contents($logFile, "=== " . date('Y-m-d H:i:s') . " ===\n", FILE_APPEND);
file_put_contents($logFile, "GET: " . print_r($_GET, true) . "\n", FILE_APPEND);
file_put_contents($logFile, "POST: " . print_r($_POST, true) . "\n", FILE_APPEND);

// Запускаем сессию
session_start();
file_put_contents($logFile, "Session started\n", FILE_APPEND);

// Подключаем БД
require_once __DIR__ . '/../config/database.php';
file_put_contents($logFile, "database.php loaded\n", FILE_APPEND);

require_once __DIR__ . '/../includes/auth.php';
file_put_contents($logFile, "auth.php loaded\n", FILE_APPEND);

// Пробуем подключиться
try {
    $pdo = Database::getConnection();
    file_put_contents($logFile, "DB connected\n", FILE_APPEND);
} catch (Exception $e) {
    file_put_contents($logFile, "DB ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
    exit;
}

// Вызываем loginUser
$login = $_POST['login'] ?? '';
$password = $_POST['password'] ?? '';
file_put_contents($logFile, "Calling loginUser('$login', '***')\n", FILE_APPEND);

$result = loginUser($pdo, $login, $password);
file_put_contents($logFile, "Result: " . print_r($result, true) . "\n", FILE_APPEND);

// Выводим JSON
header('Content-Type: application/json; charset=utf-8');
$json = json_encode($result);
file_put_contents($logFile, "JSON output: " . $json . "\n", FILE_APPEND);
echo $json;