<?php
/**
 * API: Выход из системы
 */

session_start();
require_once __DIR__ . '/../includes/auth.php';

session_unset();
session_destroy();

echo json_encode(['success' => true]);