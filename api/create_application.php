<?php
/**
 * API-эндпоинт создания заявки
 * Принимает POST-запрос с данными формы, сохраняет заявку в БД
 */

// Запуск сессии и проверка авторизации
session_start();

// Подключаем зависимости
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Заголовки ответа
header('Content-Type: application/json; charset=utf-8');

// Проверка метода запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Метод не поддерживается']);
    exit;
}

// Проверка авторизации и роли
if (!isLoggedIn() || getCurrentUserRole() !== 'client') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Доступ запрещён']);
    exit;
}

try {
    $pdo = Database::getConnection();

    // Получение и фильтрация входных данных
    $projectName       = trim($_POST['project_name'] ?? '');
    $description       = trim($_POST['description'] ?? '');
    $tasks             = trim($_POST['tasks'] ?? '');
    $requiredResources = trim($_POST['required_resources'] ?? '');
    $budget            = trim($_POST['budget'] ?? '');
    $techParams        = trim($_POST['tech_params'] ?? '');
    $additionalReq     = trim($_POST['additional_req'] ?? '');

    // Серверная валидация (дублируем клиентскую для безопасности)
    $errors = [];
    if (mb_strlen($projectName) < 5 || mb_strlen($projectName) > 255) {
        $errors[] = 'Название проекта должно быть от 5 до 255 символов';
    }
    if (mb_strlen($description) < 20 || mb_strlen($description) > 5000) {
        $errors[] = 'Описание должно быть от 20 до 5000 символов';
    }
    if (!is_numeric($budget) || floatval($budget) < 0) {
        $errors[] = 'Некорректный бюджет';
    }

    if (!empty($errors)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => implode('; ', $errors)]);
        exit;
    }

    // Обработка загрузки файла
    $filePath = null;
    if (isset($_FILES['tech_file']) && $_FILES['tech_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['tech_file'];

        // Проверка размера (до 10 МБ)
        if ($file['size'] > 10 * 1024 * 1024) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Файл превышает 10 МБ']);
            exit;
        }

        // Проверка MIME-типа по содержимому
        $allowedMimes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/x-zip-compressed'
        ];

        $detectedMime = mime_content_type($file['tmp_name']);
        if (!in_array($detectedMime, $allowedMimes, true)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Недопустимый тип файла']);
            exit;
        }

        // Генерация безопасного имени файла
        $uploadDir = __DIR__ . '/../assets/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safeName = uniqid('app_', true) . '.' . $extension;
        $destination = $uploadDir . $safeName;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            $filePath = 'assets/uploads/' . $safeName;
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Ошибка сохранения файла']);
            exit;
        }
    }

    // Шифрование дополнительных требований, если заполнены
    $encryptedReq = '';
    if (!empty($additionalReq)) {
        $encryptedReq = encryptData($additionalReq);
    }

    // Начало транзакции
    $pdo->beginTransaction();

    // Вставка заявки
    $stmt = $pdo->prepare(
        'INSERT INTO applications (user_id, project_name, description, tasks, required_resources, budget, status)
         VALUES (:user_id, :project_name, :description, :tasks, :resources, :budget, :status)'
    );
    $stmt->execute([
        ':user_id'       => $_SESSION['user']['id'],
        ':project_name'  => $projectName,
        ':description'   => $description,
        ':tasks'         => $tasks,
        ':resources'     => $requiredResources,
        ':budget'        => floatval($budget),
        ':status'        => 'pending',
    ]);

    $applicationId = (int) $pdo->lastInsertId();

    // Вставка деталей ТЗ
    $stmt = $pdo->prepare(
        'INSERT INTO task_details (application_id, file_path, technical_parameters, additional_requirements)
         VALUES (:app_id, :file_path, :tech_params, :add_req)'
    );
    $stmt->execute([
        ':app_id'      => $applicationId,
        ':file_path'   => $filePath,
        ':tech_params' => !empty($techParams) ? $techParams : null,
        ':add_req'     => !empty($encryptedReq) ? $encryptedReq : null,
    ]);

    // Запись в историю статусов
    $stmt = $pdo->prepare(
        'INSERT INTO status_history (application_id, old_status, new_status, changed_by, comment)
         VALUES (:app_id, :old_status, :new_status, :changed_by, :comment)'
    );
    $stmt->execute([
        ':app_id'     => $applicationId,
        ':old_status' => 'draft',
        ':new_status' => 'pending',
        ':changed_by' => $_SESSION['user']['id'],
        ':comment'    => 'Заявка создана и отправлена на рассмотрение',
    ]);

    // Фиксация транзакции
    $pdo->commit();

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Заявка успешно создана',
        'id'      => $applicationId
    ]);

} catch (PDOException $e) {
    // Откат транзакции в случае ошибки
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Ошибка создания заявки: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Внутренняя ошибка сервера']);
}