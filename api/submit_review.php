<?php
/**
 * API: Отправка отзыва по заявке
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Доступ запрещён']);
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$rating = (float) ($_POST['rating'] ?? 0);
$review = trim($_POST['review'] ?? '');

if ($id <= 0 || $rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Неверные параметры']);
    exit;
}

if (empty($review)) {
    echo json_encode(['success' => false, 'message' => 'Введите текст отзыва']);
    exit;
}

try {
    $pdo = Database::getConnection();
    $userId = $_SESSION['user']['id'];

    // Проверяем, что заявка принадлежит пользователю и завершена
    $stmt = $pdo->prepare('
        SELECT id, status FROM applications 
        WHERE id = :id AND user_id = :user_id
    ');
    $stmt->execute([':id' => $id, ':user_id' => $userId]);
    $app = $stmt->fetch();

    if (!$app) {
        echo json_encode(['success' => false, 'message' => 'Заявка не найдена']);
        exit;
    }

    if ($app['status'] !== 'completed') {
        echo json_encode(['success' => false, 'message' => 'Отзыв можно оставить только по завершённой заявке']);
        exit;
    }

    // Сохраняем отзыв
    $stmt = $pdo->prepare('
        UPDATE applications 
        SET rating = :rating, review = :review, updated_at = NOW()
        WHERE id = :id
    ');
    $stmt->execute([
        ':rating' => $rating,
        ':review' => $review,
        ':id' => $id
    ]);

    echo json_encode(['success' => true, 'message' => 'Отзыв сохранён']);

} catch (PDOException $e) {
    error_log('Ошибка сохранения отзыва: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ошибка сервера']);
}