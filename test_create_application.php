<?php
/**
 * test_create_application.php
 * Тестирование создания заявки
 */

session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

echo "=== ТЕСТ СОЗДАНИЯ ЗАЯВКИ ===\n\n";

try {
    $pdo = Database::getConnection();

    // Создаём пользователя и входим
    $testLogin = 'client_' . time() . '@test.ru';
    $testPassword = 'Client123!';
    
    registerUser($pdo, [
        'full_name' => 'Клиент Тестович',
        'login' => $testLogin,
        'password' => $testPassword,
        'organization' => 'ООО Клиент',
        'phone' => '+79161234567'
    ]);
    
    loginUser($pdo, $testLogin, $testPassword);
    $userId = $_SESSION['user']['id'];
    
    echo "Создан клиент ID: $userId\n\n";

    // Тест 1: Создание заявки с валидными данными
    echo "Тест 1: Создание заявки с валидными данными\n";
    
    $applicationData = [
        'project_name' => 'Разработка ИИ системы распознавания',
        'description' => 'Создание системы для распознавания объектов в реальном времени',
        'tasks' => "1. Анализ требований\n2. Разработка модели\n3. Тестирование",
        'required_resources' => 'GPU, 64GB RAM, Python',
        'budget' => '500000',
        'tech_params' => json_encode(['framework' => 'TensorFlow', 'language' => 'Python']),
        'additional_req' => 'Конфиденциальность данных'
    ];
    
    // Эмуляция POST-запроса
    $_POST = $applicationData;
    $_FILES = [];
    
    include 'api/create_application.php';
    
    // Проверяем создание в БД
    $stmt = $pdo->prepare('
        SELECT * FROM applications 
        WHERE user_id = :user_id 
        ORDER BY created_at DESC LIMIT 1
    ');
    $stmt->execute([':user_id' => $userId]);
    $app = $stmt->fetch();
    
    echo "✅ Заявка создана: ID " . $app['id'] . "\n";
    echo "Название: " . $app['project_name'] . "\n";
    echo "Статус: " . $app['status'] . "\n\n";

    // Тест 2: Создание заявки без названия
    echo "Тест 2: Создание заявки без названия\n";
    
    $_POST = [
        'project_name' => '',
        'description' => 'Описание проекта',
        'tasks' => 'Задачи',
        'required_resources' => 'Ресурсы',
        'budget' => '100000',
        'tech_params' => '',
        'additional_req' => ''
    ];
    $_FILES = [];
    
    ob_start();
    include 'api/create_application.php';
    $output = ob_get_clean();
    $result = json_decode($output, true);
    
    echo "Результат: " . ($result['success'] ? "✅ УСПЕШНО" : "❌ ОШИБКА") . "\n";
    echo "Сообщение: " . $result['message'] . "\n\n";

    // Тест 3: Создание заявки с отрицательным бюджетом
    echo "Тест 3: Создание заявки с отрицательным бюджетом\n";
    
    $_POST = [
        'project_name' => 'Тестовый проект',
        'description' => 'Описание проекта с отрицательным бюджетом',
        'tasks' => 'Задачи',
        'required_resources' => 'Ресурсы',
        'budget' => '-1000',
        'tech_params' => '',
        'additional_req' => ''
    ];
    $_FILES = [];
    
    ob_start();
    include 'api/create_application.php';
    $output = ob_get_clean();
    $result = json_decode($output, true);
    
    echo "Результат: " . ($result['success'] ? "✅ УСПЕШНО" : "❌ ОШИБКА") . "\n";
    echo "Сообщение: " . $result['message'] . "\n\n";

    // Тест 4: Создание заявки с очень коротким описанием
    echo "Тест 4: Создание заявки с очень коротким описанием\n";
    
    $_POST = [
        'project_name' => 'Короткий проект',
        'description' => 'Коротко', // Меньше 20 символов
        'tasks' => 'Задачи',
        'required_resources' => 'Ресурсы',
        'budget' => '100000',
        'tech_params' => '',
        'additional_req' => ''
    ];
    $_FILES = [];
    
    ob_start();
    include 'api/create_application.php';
    $output = ob_get_clean();
    $result = json_decode($output, true);
    
    echo "Результат: " . ($result['success'] ? "✅ УСПЕШНО" : "❌ ОШИБКА") . "\n";
    echo "Сообщение: " . $result['message'] . "\n";

} catch (PDOException $e) {
    echo "Ошибка БД: " . $e->getMessage() . "\n";
}