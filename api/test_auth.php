<?php
// Показываем ВСЕ ошибки
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

echo "=== Шаг 1: Старт скрипта ===\n";

// Проверяем, что пришли POST-данные
echo "Метод запроса: " . $_SERVER['REQUEST_METHOD'] . "\n";
echo "POST-данные: ";
print_r($_POST);
echo "\n";

echo "=== Шаг 2: Подключаем database.php ===\n";
try {
    require_once __DIR__ . '/../config/database.php';
    echo "OK: database.php подключён\n";
} catch (Exception $e) {
    echo "ОШИБКА: " . $e->getMessage() . "\n";
    exit;
}

echo "=== Шаг 3: Подключаем auth.php ===\n";
try {
    require_once __DIR__ . '/../includes/auth.php';
    echo "OK: auth.php подключён\n";
} catch (Exception $e) {
    echo "ОШИБКА: " . $e->getMessage() . "\n";
    exit;
}

echo "=== Шаг 4: Пробуем подключиться к БД ===\n";
try {
    $pdo = Database::getConnection();
    echo "OK: подключение к БД успешно\n";
} catch (PDOException $e) {
    echo "ОШИБКА БД: " . $e->getMessage() . "\n";
    exit;
}

echo "=== Шаг 5: Проверяем таблицу users ===\n";
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $count = $stmt->fetchColumn();
    echo "Найдено пользователей: " . $count . "\n";
} catch (PDOException $e) {
    echo "ОШИБКА: " . $e->getMessage() . "\n";
    exit;
}

echo "=== Шаг 6: Пробуем loginUser ===\n";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = $_POST['login'] ?? 'client@test.ru';
    $password = $_POST['password'] ?? 'Client123!';
    
    echo "Логин: " . $login . "\n";
    echo "Пароль: " . $password . "\n";
    
    $result = loginUser($pdo, $login, $password);
    echo "Результат: ";
    print_r($result);
} else {
    echo "Отправьте POST-запрос с login и password для проверки входа\n";
}

echo "\n=== ГОТОВО ===";