<?php
/**
 * API: Получение заявок текущего клиента
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'redirect' => true]);
    exit;
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = min(20, max(1, (int) ($_GET['limit'] ?? 5)));
$offset = ($page - 1) * $limit;

$status = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

try {
    $pdo = Database::getConnection();
    $userId = $_SESSION['user']['id'];

    // Базовый запрос для получения заявок
    $sql = '
        SELECT *
        FROM applications
        WHERE user_id = :user_id
    ';
    $params = [':user_id' => $userId];

    if (!empty($status)) {
        $sql .= ' AND status = :status';
        $params[':status'] = $status;
    }

    if (!empty($search)) {
        $sql .= ' AND project_name ILIKE :search';
        $params[':search'] = '%' . $search . '%';
    }

    // Сортировка и пагинация
    $sql .= ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset';

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $applications = $stmt->fetchAll();

    // ОТДЕЛЬНЫЙ ЗАПРОС для подсчёта общего количества
    $countSql = '
        SELECT COUNT(*)
        FROM applications
        WHERE user_id = :user_id
    ';
    $countParams = [':user_id' => $userId];

    if (!empty($status)) {
        $countSql .= ' AND status = :status';
        $countParams[':status'] = $status;
    }

    if (!empty($search)) {
        $countSql .= ' AND project_name ILIKE :search';
        $countParams[':search'] = '%' . $search . '%';
    }

    $stmt = $pdo->prepare($countSql);
    foreach ($countParams as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total = (int) $stmt->fetchColumn();

    // Отладка (можно удалить после)
    error_log('applications.php: found ' . count($applications) . ' applications');
    error_log('applications.php: total = ' . $total);

    echo json_encode([
        'success' => true,
        'applications' => $applications,
        'has_more' => $total > ($page * $limit),
    ]);

} catch (PDOException $e) {
    error_log('Ошибка в applications.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ошибка сервера: ' . $e->getMessage()]);
}