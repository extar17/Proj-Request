<?php
/**
 * test_get_applications.php
 * Тестирование получения списка заявок клиента
 */

session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

echo "=== ТЕСТ ПОЛУЧЕНИЯ СПИСКА ЗАЯВОК ===\n\n";

try {
    $pdo = Database::getConnection();

    // Создаём клиента
    $clientLogin = 'client_' . time() . '@test.ru';
    $stmt = $pdo->prepare('
        INSERT INTO users (full_name, login, password_hash, role, organization, phone)
        VALUES (:full_name, :login, :password_hash, :role, :organization, :phone)
    ');
    $stmt->execute([
        ':full_name' => 'Клиент Спискович',
        ':login' => $clientLogin,
        ':password_hash' => password_hash('Client123!', PASSWORD_BCRYPT),
        ':role' => 'client',
        ':organization' => 'ООО Список',
        ':phone' => '+79161234567'
    ]);
    $clientId = (int) $pdo->lastInsertId();

    // Создаём несколько заявок
    $statuses = ['pending', 'approved', 'completed', 'pending', 'revision'];
    $titles = [
        'ИИ для распознавания лиц',
        'Автоматизация склада',
        'Мобильное приложение',
        'Веб-платформа для обучения',
        'Система мониторинга'
    ];
    
    for ($i = 0; $i < 5; $i++) {
        $stmt = $pdo->prepare('
            INSERT INTO applications (user_id, project_name, description, budget, status)
            VALUES (:user_id, :project_name, :description, :budget, :status)
        ');
        $stmt->execute([
            ':user_id' => $clientId,
            ':project_name' => $titles[$i] . ' ' . ($i + 1),
            ':description' => 'Описание проекта ' . ($i + 1),
            ':budget' => 100000 * ($i + 1),
            ':status' => $statuses[$i]
        ]);
    }
    
    echo "Создано 5 заявок для клиента ID: $clientId\n\n";

    // Тест 1: Получение всех заявок (без фильтра)
    echo "Тест 1: Получение всех заявок\n";
    
    $_SESSION['user'] = ['id' => $clientId];
    $_GET = ['page' => 1, 'limit' => 10];
    
    ob_start();
    include 'api/applications.php';
    $output = ob_get_clean();
    $result = json_decode($output, true);
    
    if ($result && $result['success']) {
        echo "✅ УСПЕШНО\n";
        echo "Найдено заявок: " . count($result['applications']) . "\n";
        echo "Есть еще: " . ($result['has_more'] ? 'Да' : 'Нет') . "\n";
        foreach ($result['applications'] as $app) {
            echo "  - #{$app['id']}: {$app['project_name']} ({$app['status']})\n";
        }
    } else {
        echo "❌ ОШИБКА\n";
        echo "Сообщение: " . ($result['message'] ?? 'Неизвестная ошибка') . "\n";
    }
    echo "\n";

    // Тест 2: Фильтр по статусу
    echo "Тест 2: Фильтр по статусу 'pending'\n";
    
    $_GET = ['page' => 1, 'limit' => 10, 'status' => 'pending'];
    
    ob_start();
    include 'api/applications.php';
    $output = ob_get_clean();
    $result = json_decode($output, true);
    
    if ($result && $result['success']) {
        echo "✅ УСПЕШНО\n";
        echo "Найдено заявок со статусом 'pending': " . count($result['applications']) . "\n";
    } else {
        echo "❌ ОШИБКА\n";
    }
    echo "\n";

    // Тест 3: Поиск по названию
    echo "Тест 3: Поиск по названию 'распознавания'\n";
    
    $_GET = ['page' => 1, 'limit' => 10, 'search' => 'распознавания'];
    
    ob_start();
    include 'api/applications.php';
    $output = ob_get_clean();
    $result = json_decode($output, true);
    
    if ($result && $result['success']) {
        echo "✅ УСПЕШНО\n";
        echo "Найдено заявок: " . count($result['applications']) . "\n";
        foreach ($result['applications'] as $app) {
            echo "  - #{$app['id']}: {$app['project_name']}\n";
        }
    } else {
        echo "❌ ОШИБКА\n";
    }

} catch (PDOException $e) {
    echo "Ошибка БД: " . $e->getMessage() . "\n";
}