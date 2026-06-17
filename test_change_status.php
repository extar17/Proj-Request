<?php
/**
 * test_change_status.php
 * Тестирование изменения статуса заявки (модератор)
 */

session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/status.php';

echo "=== ТЕСТ ИЗМЕНЕНИЯ СТАТУСА ===\n\n";

try {
    $pdo = Database::getConnection();

    // Создаём модератора
    $moderatorLogin = 'moderator_' . time() . '@test.ru';
    $moderatorPassword = 'Moderator123!';
    
    $stmt = $pdo->prepare('
        INSERT INTO users (full_name, login, password_hash, role, organization, phone)
        VALUES (:full_name, :login, :password_hash, :role, :organization, :phone)
    ');
    $stmt->execute([
        ':full_name' => 'Модератор Тестович',
        ':login' => $moderatorLogin,
        ':password_hash' => password_hash($moderatorPassword, PASSWORD_BCRYPT),
        ':role' => 'moderator',
        ':organization' => 'НовГУ',
        ':phone' => '+79160000000'
    ]);
    $moderatorId = (int) $pdo->lastInsertId();
    
    echo "Создан модератор ID: $moderatorId\n";

    // Создаём клиента и заявку
    $clientLogin = 'client_' . time() . '@test.ru';
    $stmt = $pdo->prepare('
        INSERT INTO users (full_name, login, password_hash, role, organization, phone)
        VALUES (:full_name, :login, :password_hash, :role, :organization, :phone)
    ');
    $stmt->execute([
        ':full_name' => 'Клиент Тестович',
        ':login' => $clientLogin,
        ':password_hash' => password_hash('Client123!', PASSWORD_BCRYPT),
        ':role' => 'client',
        ':organization' => 'ООО Тест',
        ':phone' => '+79161234567'
    ]);
    $clientId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare('
        INSERT INTO applications (user_id, project_name, description, budget, status)
        VALUES (:user_id, :project_name, :description, :budget, :status)
    ');
    $stmt->execute([
        ':user_id' => $clientId,
        ':project_name' => 'Тестовая заявка для модерации',
        ':description' => 'Описание тестовой заявки',
        ':budget' => 100000,
        ':status' => 'pending'
    ]);
    $applicationId = (int) $pdo->lastInsertId();
    
    echo "Создана заявка ID: $applicationId (статус: pending)\n\n";

    // Тест 1: Одобрение заявки
    echo "Тест 1: Одобрение заявки (pending → approved)\n";
    $result = changeApplicationStatus($pdo, $applicationId, 'approved', $moderatorId, 'Заявка одобрена');
    echo "Результат: " . ($result['success'] ? "✅ УСПЕШНО" : "❌ ОШИБКА") . "\n";
    echo "Сообщение: " . $result['message'] . "\n";
    
    // Проверяем статус в БД
    $stmt = $pdo->prepare('SELECT status FROM applications WHERE id = :id');
    $stmt->execute([':id' => $applicationId]);
    $status = $stmt->fetchColumn();
    echo "Текущий статус: $status\n\n";

    // Тест 2: Отправка на доработку
    echo "Тест 2: Отправка на доработку (approved → revision)\n";
    $result = changeApplicationStatus($pdo, $applicationId, 'revision', $moderatorId, 'Требуется доработка ТЗ');
    echo "Результат: " . ($result['success'] ? "✅ УСПЕШНО" : "❌ ОШИБКА") . "\n";
    echo "Сообщение: " . $result['message'] . "\n";
    
    $stmt = $pdo->prepare('SELECT status FROM applications WHERE id = :id');
    $stmt->execute([':id' => $applicationId]);
    $status = $stmt->fetchColumn();
    echo "Текущий статус: $status\n\n";

    // Тест 3: Попытка изменения на недопустимый статус
    echo "Тест 3: Попытка изменения на недопустимый статус\n";
    $result = changeApplicationStatus($pdo, $applicationId, 'invalid_status', $moderatorId, '');
    echo "Результат: " . ($result['success'] ? "✅ УСПЕШНО" : "❌ ОШИБКА") . "\n";
    echo "Сообщение: " . $result['message'] . "\n\n";

    // Тест 4: Изменение статуса несуществующей заявки
    echo "Тест 4: Изменение статуса несуществующей заявки\n";
    $result = changeApplicationStatus($pdo, 99999, 'approved', $moderatorId, '');
    echo "Результат: " . ($result['success'] ? "✅ УСПЕШНО" : "❌ ОШИБКА") . "\n";
    echo "Сообщение: " . $result['message'] . "\n";

    // Проверяем историю статусов
    echo "\nИстория изменений статуса:\n";
    $stmt = $pdo->prepare('
        SELECT old_status, new_status, comment, changed_at 
        FROM status_history 
        WHERE application_id = :app_id 
        ORDER BY changed_at DESC
    ');
    $stmt->execute([':app_id' => $applicationId]);
    $history = $stmt->fetchAll();
    
    foreach ($history as $entry) {
        echo "  {$entry['old_status']} → {$entry['new_status']} ";
        if ($entry['comment']) echo "({$entry['comment']})";
        echo " [" . date('H:i:s', strtotime($entry['changed_at'])) . "]\n";
    }

} catch (PDOException $e) {
    echo "Ошибка БД: " . $e->getMessage() . "\n";
}