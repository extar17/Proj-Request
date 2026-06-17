<?php
/**
 * Модуль аутентификации и регистрации пользователей
 * Обрабатывает вход, регистрацию и управление сессиями
 */

// Запускаем сессию, если ещё не запущена
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Подключаем конфигурацию БД
require_once __DIR__ . '/../config/database.php';

/**
 * Шифрование конфиденциальных данных
 *
 * @param string $data Данные для шифрования
 * @return string Зашифрованная строка в base64
 */
function encryptData(string $data): string
{
    // Ключ шифрования (в реальном проекте хранить в переменных окружения)
    $key = getenv('ENCRYPTION_KEY') ?: 'default-32-char-secure-key!!1';
    $cipher = 'aes-256-cbc';
    $ivLength = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($ivLength);
    $encrypted = openssl_encrypt($data, $cipher, $key, 0, $iv);
    return base64_encode($iv . $encrypted);
}

/**
 * Расшифровка конфиденциальных данных
 *
 * @param string $encryptedData Зашифрованная строка в base64
 * @return string Расшифрованные данные
 */
function decryptData(string $encryptedData): string
{
    $key = getenv('ENCRYPTION_KEY') ?: 'default-32-char-secure-key!!1';
    $cipher = 'aes-256-cbc';
    $data = base64_decode($encryptedData);
    $ivLength = openssl_cipher_iv_length($cipher);
    $iv = substr($data, 0, $ivLength);
    $encrypted = substr($data, $ivLength);
    return openssl_decrypt($encrypted, $cipher, $key, 0, $iv);
}

/**
 * Регистрация нового пользователя
 *
 * @param PDO $pdo Соединение с БД
 * @param array $data Массив с полями: full_name, login, password, organization, phone
 * @return array Результат операции: ['success' => bool, 'message' => string, 'user_id' => int|null]
 */
function registerUser(PDO $pdo, array $data): array
{
    // Валидация обязательных полей
    $requiredFields = ['full_name', 'login', 'password', 'organization', 'phone'];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            return ['success' => false, 'message' => "Поле '{$field}' обязательно для заполнения", 'user_id' => null];
        }
    }

    // Валидация email (логина)
    if (!filter_var($data['login'], FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Некорректный формат email', 'user_id' => null];
    }

    // Проверка длины ФИО
    if (mb_strlen($data['full_name']) < 2 || mb_strlen($data['full_name']) > 100) {
        return ['success' => false, 'message' => 'ФИО должно быть от 2 до 100 символов', 'user_id' => null];
    }

    // Проверка существования пользователя с таким логином
    $stmt = $pdo->prepare('SELECT id FROM users WHERE login = :login');
    $stmt->execute([':login' => $data['login']]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Пользователь с таким email уже зарегистрирован', 'user_id' => null];
    }

    // Хеширование пароля (bcrypt)
    $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);

    // Сохранение пользователя в БД
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO users (full_name, login, password_hash, organization, role, phone)
             VALUES (:full_name, :login, :password_hash, :organization, :role, :phone)'
        );
        $stmt->execute([
            ':full_name'     => $data['full_name'],
            ':login'         => $data['login'],
            ':password_hash' => $passwordHash,
            ':organization'  => $data['organization'],
            ':role'          => 'client', // По умолчанию все новые пользователи — заказчики
            ':phone'         => $data['phone'],
        ]);

        $userId = (int) $pdo->lastInsertId();
        return ['success' => true, 'message' => 'Регистрация успешна', 'user_id' => $userId];
    } catch (PDOException $e) {
        error_log('Ошибка регистрации: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Ошибка сервера при регистрации', 'user_id' => null];
    }
}

/**
 * Аутентификация пользователя
 *
 * @param PDO $pdo Соединение с БД
 * @param string $login Логин (email)
 * @param string $password Пароль в открытом виде
 * @return array Результат: ['success' => bool, 'message' => string]
 */
function loginUser(PDO $pdo, string $login, string $password): array
{
    // Поиск пользователя по логину
    $stmt = $pdo->prepare(
        'SELECT id, full_name, login, password_hash, role, failed_attempts, locked_until
         FROM users WHERE login = :login'
    );
    $stmt->execute([':login' => $login]);
    $user = $stmt->fetch();

    // Пользователь не найден — возвращаем общую ошибку (не уточняем причину)
    if (!$user) {
        return ['success' => false, 'message' => 'Неверный логин или пароль'];
    }

    // Проверка блокировки аккаунта
    if (!empty($user['locked_until'])) {
        $lockedUntil = new DateTime($user['locked_until']);
        $now = new DateTime();
        if ($lockedUntil > $now) {
            $remaining = $now->diff($lockedUntil);
            $minutes = $remaining->i + $remaining->h * 60;
            return [
                'success' => false,
                'message' => "Аккаунт заблокирован. Повторите попытку через {$minutes} мин."
            ];
        }
    }

    // Проверка пароля
    if (password_verify($password, $user['password_hash'])) {
        // Успешный вход: сбрасываем счётчик неудач, обновляем last_login
        $stmt = $pdo->prepare(
            'UPDATE users SET failed_attempts = 0, last_login = NOW(), locked_until = NULL WHERE id = :id'
        );
        $stmt->execute([':id' => $user['id']]);

        // Сохраняем данные в сессию
        $_SESSION['user'] = [
            'id'        => (int) $user['id'],
            'full_name' => $user['full_name'],
            'login'     => $user['login'],
            'role'      => $user['role'],
        ];

        return ['success' => true, 'message' => 'Вход выполнен успешно'];
    }

    // Неверный пароль: увеличиваем счётчик неудач
    $newAttempts = (int) $user['failed_attempts'] + 1;
    $lockedUntil = null;

    // При 5 неудачных попытках блокируем на 15 минут
    if ($newAttempts >= 5) {
        $lockedUntil = (new DateTime())->add(new DateInterval('PT15M'))->format('Y-m-d H:i:s');
        $newAttempts = 0; // Сбрасываем, чтобы после разблокировки не заблокировать сразу
    }

    $stmt = $pdo->prepare(
        'UPDATE users SET failed_attempts = :attempts, locked_until = :locked WHERE id = :id'
    );
    $stmt->execute([
        ':attempts' => $newAttempts,
        ':locked'   => $lockedUntil,
        ':id'       => $user['id'],
    ]);

    $remaining = 5 - $newAttempts;
    return [
        'success' => false,
        'message' => "Неверный пароль. Осталось попыток: {$remaining}"
    ];
}

/**
 * Проверка авторизации пользователя
 *
 * @return bool Авторизован ли пользователь
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user']);
}

/**
 * Получение роли текущего пользователя
 *
 * @return string|null Роль или null
 */
function getCurrentUserRole(): ?string
{
    return $_SESSION['user']['role'] ?? null;
}

/**
 * Выход из системы
 */
function logoutUser(): void
{
    session_unset();
    session_destroy();
}