<?php
declare(strict_types=1);

/**
 * PDO connection + Repository for the 40hours project.
 *
 * Expected constants (e.g. from 40hours_config.php or similar):
 * - DATABASE_HOST
 * - DATABASE_NAME
 * - DATABASE_USER
 * - DATABASE_PASSWORD
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            DATABASE_HOST,
            DATABASE_NAME
        );

        try {
            self::$pdo = new PDO($dsn, DATABASE_USER, DATABASE_PASSWORD, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Real prepared statements (more predictable + safer)
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            return self::$pdo;
        } catch (PDOException $e) {
            error_log('DB connection failed: ' . $e->getMessage());
            throw new RuntimeException('Datenbankverbindung fehlgeschlagen');
        }
    }
}
