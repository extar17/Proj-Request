<?php
/**
 * API-эндпоинт аутентификации
 */
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Метод не поддерживается']);
    exit;
}

try {
    $pdo = Database::getConnection();
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка подключения к БД']);
    exit;
}

// РЕГИСТРАЦИЯ
if ($action === 'register') {
    $data = [
        'full_name'    => trim($_POST['full_name'] ?? ''),
        'login'        => trim($_POST['login'] ?? ''),
        'password'     => $_POST['password'] ?? '',
        'organization' => trim($_POST['organization'] ?? ''),
        'phone'        => trim($_POST['phone'] ?? ''),
    ];

    $result = registerUser($pdo, $data);
    echo json_encode($result);
    exit;
}

// ВХОД
if ($action === 'login') {
    $login    = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($login) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Введите логин и пароль']);
        exit;
    }

    $result = loginUser($pdo, $login, $password);
    
    // Добавляем роль в ответ для перенаправления
    if ($result['success'] && isset($_SESSION['user']['role'])) {
        $result['role'] = $_SESSION['user']['role'];
        $result['redirect'] = $_SESSION['user']['role'] === 'moderator' || $_SESSION['user']['role'] === 'admin'
            ? 'templates/moderator_panel.html'
            : 'templates/client_profile.html';
    }
    error_log('auth.php login: session после входа = ' . print_r($_SESSION, true));
    
    echo json_encode($result);
    exit;
}

// НЕИЗВЕСТНОЕ ДЕЙСТВИЕ
echo json_encode(['success' => false, 'message' => 'Неизвестное действие']);