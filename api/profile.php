<?php
/**
 * API: Получение данных текущего пользователя
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'redirect' => true]);
    exit;
}

try {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare('
        SELECT id, full_name, login, organization, phone, role, created_at
        FROM users WHERE id = :id
    ');
    $stmt->execute([':id' => $_SESSION['user']['id']]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Пользователь не найден']);
        exit;
    }

    // Удаляем чувствительные данные
    unset($user['password_hash']);

    echo json_encode(['success' => true, 'user' => $user]);

} catch (PDOException $e) {
    error_log('Ошибка в profile.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ошибка сервера']);
}