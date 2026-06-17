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

    // Базовый запрос для получения заявок
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

    // Сортировка и пагинация
    $sql .= ' ORDER BY a.created_at DESC LIMIT :limit OFFSET :offset';

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
        FROM applications a
        JOIN users u ON a.user_id = u.id
        WHERE 1=1
    ';
    $countParams = [];

    if (!empty($status)) {
        $countSql .= ' AND a.status = :status';
        $countParams[':status'] = $status;
    }

    if (!empty($search)) {
        $countSql .= ' AND (a.project_name ILIKE :search OR u.full_name ILIKE :search)';
        $countParams[':search'] = '%' . $search . '%';
    }

    $stmt = $pdo->prepare($countSql);
    foreach ($countParams as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total = (int) $stmt->fetchColumn();

    // Статистика по статусам (только для текущей страницы, можно сделать отдельным запросом)
    $stats = [
        'total' => $total,
        'pending' => 0,
        'approved' => 0,
        'revision' => 0,
    ];

    // Получаем статистику по всем заявкам (не только на текущей странице)
    $statsSql = '
        SELECT status, COUNT(*) as count
        FROM applications a
        JOIN users u ON a.user_id = u.id
        WHERE 1=1
    ';
    $statsParams = [];
    
    if (!empty($search)) {
        $statsSql .= ' AND (a.project_name ILIKE :search OR u.full_name ILIKE :search)';
        $statsParams[':search'] = '%' . $search . '%';
    }
    
    $statsSql .= ' GROUP BY status';
    
    $stmt = $pdo->prepare($statsSql);
    foreach ($statsParams as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $statsResult = $stmt->fetchAll();
    
    foreach ($statsResult as $row) {
        if ($row['status'] === 'pending') $stats['pending'] = (int) $row['count'];
        if ($row['status'] === 'approved') $stats['approved'] = (int) $row['count'];
        if ($row['status'] === 'revision') $stats['revision'] = (int) $row['count'];
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
    echo json_encode(['success' => false, 'message' => 'Ошибка сервера: ' . $e->getMessage()]);
}