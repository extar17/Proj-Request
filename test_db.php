<?php
/**
 * Проверка подключения к БД
 */
require_once __DIR__ . '/config/database.php';

try {
    $pdo = Database::getConnection();
    echo "<h2 style='color: green;'>✓ Подключение к БД успешно!</h2>";

    // Проверяем наличие таблиц
    $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "<h3>Таблицы в базе данных:</h3>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>✓ {$table}</li>";
    }
    echo "</ul>";

    // Проверяем пользователей
    $stmt = $pdo->query("SELECT id, full_name, login, role FROM users");
    $users = $stmt->fetchAll();

    echo "<h3>Пользователи:</h3>";
    echo "<table border='1' cellpadding='8' cellspacing='0'>";
    echo "<tr><th>ID</th><th>ФИО</th><th>Логин</th><th>Роль</th></tr>";
    foreach ($users as $user) {
        echo "<tr>
                <td>{$user['id']}</td>
                <td>" . htmlspecialchars($user['full_name']) . "</td>
                <td>" . htmlspecialchars($user['login']) . "</td>
                <td>{$user['role']}</td>
              </tr>";
    }
    echo "</table>";

} catch (PDOException $e) {
    echo "<h2 style='color: red;'>✕ Ошибка подключения:</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";

    // Подсказки по ошибке
    $msg = $e->getMessage();
    if (strpos($msg, 'could not translate host name') !== false) {
        echo "<p><b>Решение:</b> Проверьте, что сервер <code>edu-pg.itiscaf.ru</code> доступен.</p>";
    } elseif (strpos($msg, 'password authentication failed') !== false) {
        echo "<p><b>Решение:</b> Проверьте логин и пароль в <code>config/database.php</code>.</p>";
    } elseif (strpos($msg, 'does not exist') !== false) {
        echo "<p><b>Решение:</b> Проверьте имя базы данных (DB_NAME). Возможно, оно отличается от логина.</p>";
    } elseif (strpos($msg, 'could not connect') !== false) {
        echo "<p><b>Решение:</b> Возможно, сервер недоступен из вашей сети. Попробуйте использовать VPN или проверьте firewall.</p>";
    }
}
?>