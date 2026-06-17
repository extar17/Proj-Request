<?php
/**
 * API: Обновление данных профиля пользователя
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Доступ запрещён']);
    exit;
}

$fullName = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$organization = trim($_POST['organization'] ?? '');

// Валидация
if (empty($fullName)) {
    echo json_encode(['success' => false, 'message' => 'ФИО обязательно']);
    exit;
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Некорректный email']);
    exit;
}

try {
    $pdo = Database::getConnection();
    $userId = $_SESSION['user']['id'];

    // Проверяем, не занят ли email другим пользователем
    $stmt = $pdo->prepare('SELECT id FROM users WHERE login = :email AND id != :id');
    $stmt->execute([':email' => $email, ':id' => $userId]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Этот email уже используется']);
        exit;
    }

    // Обновляем данные
    $stmt = $pdo->prepare('
        UPDATE users 
        SET full_name = :full_name, 
            login = :email, 
            phone = :phone, 
            organization = :organization 
        WHERE id = :id
    ');
    $stmt->execute([
        ':full_name' => $fullName,
        ':email' => $email,
        ':phone' => $phone,
        ':organization' => $organization,
        ':id' => $userId
    ]);

    // Обновляем данные в сессии
    $_SESSION['user']['full_name'] = $fullName;
    $_SESSION['user']['login'] = $email;

    // Получаем обновлённые данные
    $stmt = $pdo->prepare('
        SELECT id, full_name, login, organization, phone, role, registered_at
        FROM users WHERE id = :id
    ');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'message' => 'Профиль обновлён',
        'user' => $user
    ]);

} catch (PDOException $e) {
    error_log('Ошибка обновления профиля: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ошибка сервера']);
}