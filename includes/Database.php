<?php

declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    public static function connect(array $config): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            (int) $config['port'],
            $config['database'],
            $config['charset'] ?? 'utf8mb4'
        );

        self::$pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_TIMEOUT => 10,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
        ]);

        return self::$pdo;
    }

    public static function pdo(): PDO
    {
        if (!self::$pdo instanceof PDO) {
            throw new RuntimeException('Base de données non initialisée.');
        }
        return self::$pdo;
    }

    /** Exécute une transaction avec retry automatique en cas de deadlock/concurrence. */
    public static function transaction(callable $callback, int $maxAttempts = 3): mixed
    {
        $attempt = 0;
        while (true) {
            $attempt++;
            $pdo = self::pdo();
            $pdo->beginTransaction();
            try {
                $result = $callback($pdo);
                $pdo->commit();
                return $result;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($attempt < $maxAttempts && self::isRetryable($e)) {
                    usleep(100000 * $attempt);
                    continue;
                }
                throw $e;
            }
        }
    }

    private static function isRetryable(Throwable $e): bool
    {
        if ($e instanceof PDOException) {
            $code = (string) ($e->errorInfo[1] ?? $e->getCode());
            return in_array($code, ['1213', '1205', '2006', '2013'], true);
        }
        $msg = strtolower($e->getMessage());
        return str_contains($msg, 'deadlock') || str_contains($msg, 'lock wait timeout');
    }
}
