<?php
/**
 * Модуль управления статусами заявок
 * Содержит логику смены статуса, уведомлений и логирования
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php'; // Для функции encryptData/decryptData

/**
 * Отправка email-уведомления (заглушка)
 * В реальном проекте — через PHPMailer или библиотеку
 *
 * @param string $toEmail Email получателя
 * @param string $subject Тема письма
 * @param string $body Тело письма
 * @return bool Успешность отправки
 */
function sendNotification(string $toEmail, string $subject, string $body): bool
{
    // Заглушка: в реальном проекте здесь будет вызов mail() или библиотеки
    error_log("Уведомление отправлено на {$toEmail}: {$subject}");
    return true;
}

/**
 * Смена статуса заявки
 *
 * @param PDO $pdo Соединение с БД
 * @param int $applicationId ID заявки
 * @param string $newStatus Новый статус
 * @param int $moderatorId ID модератора, меняющего статус
 * @param string $comment Комментарий к изменению
 * @return array Результат: ['success' => bool, 'message' => string]
 */
function changeApplicationStatus(
    PDO $pdo,
    int $applicationId,
    string $newStatus,
    int $moderatorId,
    string $comment = ''
): array {
    // Проверка допустимых статусов
    $allowedStatuses = ['draft', 'pending', 'approved', 'rejected', 'revision', 'completed'];
    if (!in_array($newStatus, $allowedStatuses, true)) {
        return ['success' => false, 'message' => 'Недопустимый статус'];
    }

    try {
        // Получаем текущий статус заявки и email заказчика
        $stmt = $pdo->prepare(
            'SELECT a.status, a.user_id, u.login, u.full_name
             FROM applications a
             JOIN users u ON a.user_id = u.id
             WHERE a.id = :id'
        );
        $stmt->execute([':id' => $applicationId]);
        $application = $stmt->fetch();

        if (!$application) {
            return ['success' => false, 'message' => 'Заявка не найдена'];
        }

        $oldStatus = $application['status'];
        $userEmail = $application['login'];
        $userName  = $application['full_name'];

        // Проверка корректности перехода статусов
        if ($oldStatus === $newStatus) {
            return ['success' => false, 'message' => 'Заявка уже имеет этот статус'];
        }

        // Начало транзакции
        $pdo->beginTransaction();

        // Обновление статуса заявки
        $stmt = $pdo->prepare(
            'UPDATE applications SET status = :status, moderator_id = :moderator_id, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([
            ':status'       => $newStatus,
            ':moderator_id' => $moderatorId,
            ':id'           => $applicationId,
        ]);

        // Запись в историю статусов
        $stmt = $pdo->prepare(
            'INSERT INTO status_history (application_id, old_status, new_status, changed_by, comment)
             VALUES (:app_id, :old_status, :new_status, :changed_by, :comment)'
        );
        $stmt->execute([
            ':app_id'     => $applicationId,
            ':old_status' => $oldStatus,
            ':new_status' => $newStatus,
            ':changed_by' => $moderatorId,
            ':comment'    => $comment,
        ]);

        // Фиксация транзакции
        $pdo->commit();

        // Отправка уведомлений в зависимости от статуса
        $subject = '';
        $body = '';

        switch ($newStatus) {
            case 'revision':
                $subject = 'Заявка отправлена на доработку';
                $body = "Уважаемый(ая) {$userName}, ваша заявка №{$applicationId} отправлена на доработку.\n";
                $body .= "Комментарий модератора: {$comment}\n";
                $body .= "Пожалуйста, внесите необходимые изменения.";
                sendNotification($userEmail, $subject, $body);
                break;

            case 'approved':
                $subject = 'Заявка одобрена';
                $body = "Уважаемый(ая) {$userName}, ваша заявка №{$applicationId} одобрена!";
                sendNotification($userEmail, $subject, $body);
                break;

            case 'rejected':
                $subject = 'Заявка отклонена';
                $body = "Уважаемый(ая) {$userName}, ваша заявка №{$applicationId} отклонена.\n";
                $body .= "Причина: {$comment}";
                sendNotification($userEmail, $subject, $body);
                break;

            case 'completed':
                $subject = 'Заявка завершена';
                $body = "Уважаемый(ая) {$userName}, работа по заявке №{$applicationId} завершена.";
                sendNotification($userEmail, $subject, $body);
                break;
        }

        return ['success' => true, 'message' => "Статус изменён на '{$newStatus}'"];

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Ошибка смены статуса: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Ошибка сервера при смене статуса'];
    }
}