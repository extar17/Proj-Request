<?php
/**
 * API-эндпоинт получения списка заявок для панели модератора
 * Возвращает готовый HTML для вставки в таблицу
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Проверка авторизации и роли (модератор или админ)
if (!isLoggedIn() || !in_array(getCurrentUserRole(), ['moderator', 'admin'], true)) {
    http_response_code(403);
    echo '<tr><td colspan="7" style="color: #c62828; text-align: center;">Доступ запрещён</td></tr>';
    exit;
}

try {
    $pdo = Database::getConnection();

    // Параметры фильтрации из GET-запроса
    $status = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';

    // Построение SQL-запроса с фильтрацией
    $sql = '
        SELECT a.id, a.project_name, a.budget, a.status, a.created_at,
               u.full_name AS client_name, u.organization
        FROM applications a
        JOIN users u ON a.user_id = u.id
        WHERE 1=1
    ';
    $params = [];

    // Фильтр по статусу
    if (!empty($status)) {
        $sql .= ' AND a.status = :status';
        $params[':status'] = $status;
    }

    // Поиск по названию
    if (!empty($search)) {
        $sql .= ' AND a.project_name ILIKE :search';
        $params[':search'] = '%' . $search . '%';
    }

    // Сортировка: сначала новые
    $sql .= ' ORDER BY a.created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $applications = $stmt->fetchAll();

    // Если нет заявок — возвращаем пустой результат
    if (empty($applications)) {
        echo '';
        exit;
    }

    /**
     * Возвращает CSS-класс для бейджа статуса
     */
    function getStatusBadgeClass(string $status): string {
        return match ($status) {
            'pending'   => 'badge-pending',
            'approved'  => 'badge-approved',
            'rejected'  => 'badge-rejected',
            'revision'  => 'badge-revision',
            'completed' => 'badge-completed',
            'draft'     => 'badge-draft',
            default     => '',
        };
    }

    /**
     * Возвращает русское название статуса
     */
    function getStatusLabel(string $status): string {
        return match ($status) {
            'pending'   => 'На рассмотрении',
            'approved'  => 'Одобрена',
            'rejected'  => 'Отклонена',
            'revision'  => 'На доработке',
            'completed' => 'Завершена',
            'draft'     => 'Черновик',
            default     => $status,
        };
    }

    // Формирование HTML-строк таблицы
    foreach ($applications as $app) {
        $badgeClass = getStatusBadgeClass($app['status']);
        $statusLabel = getStatusLabel($app['status']);
        $budgetFormatted = number_format((float) $app['budget'], 2, ',', ' ');
        $createdDate = date('d.m.Y H:i', strtotime($app['created_at']));

        // Экранирование вывода для защиты от XSS
        $id = (int) $app['id'];
        $name = htmlspecialchars($app['project_name'], ENT_QUOTES, 'UTF-8');
        $client = htmlspecialchars($app['client_name'], ENT_QUOTES, 'UTF-8');
        $org = htmlspecialchars($app['organization'] ?? '—', ENT_QUOTES, 'UTF-8');

        echo <<<HTML
        <tr>
            <td>{$id}</td>
            <td><a href="application_card.php?id={$id}" class="app-link">{$name}</a></td>
            <td>{$client}</td>
            <td>{$org}</td>
            <td>{$budgetFormatted} ₽</td>
            <td><span class="badge {$badgeClass}">{$statusLabel}</span></td>
            <td>{$createdDate}</td>
        </tr>
        HTML;
    }

} catch (PDOException $e) {
    error_log('Ошибка получения заявок: ' . $e->getMessage());
    http_response_code(500);
    echo '<tr><td colspan="7" style="color: #c62828; text-align: center;">Ошибка загрузки данных</td></tr>';
}