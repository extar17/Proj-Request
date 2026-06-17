<?php
/**
 * API: Изменение статуса заявки
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/status.php';

// Только модератор или админ
if (!isLoggedIn() || !in_array(getCurrentUserRole(), ['moderator', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Доступ запрещён']);
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$status = $_POST['status'] ?? '';
$comment = trim($_POST['comment'] ?? '');

if ($id <= 0 || empty($status)) {
    echo json_encode(['success' => false, 'message' => 'Неверные параметры']);
    exit;
}

try {
    $pdo = Database::getConnection();
    $result = changeApplicationStatus($pdo, $id, $status, $_SESSION['user']['id'], $comment);

    echo json_encode($result);

} catch (PDOException $e) {
    error_log('Ошибка в change_status.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ошибка сервера']);
}