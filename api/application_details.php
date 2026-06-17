<?php
/**
 * API: Получение деталей заявки
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Доступ запрещён']);
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Неверный ID заявки']);
    exit;
}

try {
    $pdo = Database::getConnection();

    $stmt = $pdo->prepare('
        SELECT a.*, u.full_name AS client_name, u.organization, u.login AS client_email
        FROM applications a
        JOIN users u ON a.user_id = u.id
        WHERE a.id = :id
    ');
    $stmt->execute([':id' => $id]);
    $application = $stmt->fetch();

    if (!$application) {
        echo json_encode(['success' => false, 'message' => 'Заявка не найдена']);
        exit;
    }

    // Проверка прав: модератор видит все, клиент — только свои
    $role = getCurrentUserRole();
    if ($role === 'client' && $application['user_id'] != $_SESSION['user']['id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Доступ запрещён']);
        exit;
    }

    echo json_encode(['success' => true, 'application' => $application]);

} catch (PDOException $e) {
    error_log('Ошибка в application_details.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ошибка сервера']);
}