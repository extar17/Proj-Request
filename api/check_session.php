<?php
/**
 * API: Проверка сессии и получение данных пользователя
 * Используется для защиты страниц и редиректов
 */

session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Не авторизован',
        'redirect' => '../index.html'
    ]);
    exit;
}

try {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare('
        SELECT id, full_name, login, organization, role, phone, registered_at
        FROM users WHERE id = :id
    ');
    $stmt->execute([':id' => $_SESSION['user']['id']]);
    $user = $stmt->fetch();

    if (!$user) {
        session_unset();
        session_destroy();
        echo json_encode([
            'success' => false,
            'message' => 'Пользователь не найден',
            'redirect' => '../index.html'
        ]);
        exit;
    }

    // Определяем страницу для редиректа в зависимости от роли
    $profilePage = in_array($user['role'], ['moderator', 'admin'])
        ? 'templates/moderator_panel.html'
        : 'templates/client_profile.html';

    echo json_encode([
        'success' => true,
        'user' => $user,
        'role' => $user['role'],
        'profile_page' => $profilePage
    ]);

} catch (PDOException $e) {
    error_log('Ошибка проверки сессии: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка сервера',
        'redirect' => '../index.html'
    ]);
}