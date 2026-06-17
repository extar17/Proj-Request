<?php
/**
 * API: Получение списка заявок для модератора
 * Возвращает JSON с данными и пагинацией
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Проверка авторизации и роли
if (!isLoggedIn() || !in_array(getCurrentUserRole(), ['moderator', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Доступ запрещён']);
    exit;
}

try {
    $pdo = Database::getConnection();

    // Параметры
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = min(50, max(1, (int) ($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;

    $status = $_GET['status'] ?? '';
    $search = trim($_GET['search'] ?? '');

    // Построение запроса
    $sql = '
        SELECT a.*, u.full_name AS client_name, u.organization
        FROM applications a
        JOIN users u ON a.user_id = u.id
        WHERE 1=1
    ';
    $params = [];

    if (!empty($status)) {
        $sql .= ' AND a.status = :status';
        $params[':status'] = $status;
    }

    if (!empty($search)) {
        $sql .= ' AND (a.project_name ILIKE :search OR u.full_name ILIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }

    // Сортировка
    $sql .= ' ORDER BY a.created_at DESC';

    // Подсчёт общего количества
    $countSql = str_replace('a.*, u.full_name AS client_name, u.organization', 'COUNT(*)', $sql);
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();

    // Пагинация
    $sql .= ' LIMIT :limit OFFSET :offset';
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $applications = $stmt->fetchAll();

    // Статистика
    $stats = [
        'total' => $total,
        'pending' => 0,
        'approved' => 0,
        'revision' => 0,
    ];

    foreach ($applications as $app) {
        if ($app['status'] === 'pending') $stats['pending']++;
        if ($app['status'] === 'approved') $stats['approved']++;
        if ($app['status'] === 'revision') $stats['revision']++;
    }

    echo json_encode([
        'success' => true,
        'applications' => $applications,
        'total' => $total,
        'total_pages' => ceil($total / $limit),
        'current_page' => $page,
        'stats' => $stats,
    ]);

} catch (PDOException $e) {
    error_log('Ошибка в moderator_applications.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ошибка сервера']);
}