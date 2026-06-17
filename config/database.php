<?php

class Database
{
    private static ?PDO $instance = null;

    // Параметры подключения (в реальном проекте — через переменные окружения)
    private const DB_HOST = 'edu-pg.itiscaf.ru';
    private const DB_PORT = '5432';
    private const DB_NAME = 'dbproject';        // Обычно имя БД совпадает с именем пользователя
    private const DB_USER = 'filimonenko_ev';
    private const DB_PASS = '%#8Ir$U$zW6';           // Спецсимволы нужно экранировать

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                self::DB_HOST,
                self::DB_PORT,
                self::DB_NAME
            );

            self::$instance = new PDO($dsn, self::DB_USER, self::DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }

        return self::$instance;
    }

    private function __construct() {}
    private function __clone() {}
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}