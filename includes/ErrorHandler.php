<?php

declare(strict_types=1);

final class ErrorHandler
{
    private static bool $registered = false;

    public static function register(bool $jsonResponses = true): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        self::ensureLogDir();

        set_exception_handler(static function (Throwable $e) use ($jsonResponses): void {
            self::handleThrowable($e, $jsonResponses);
        });

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        register_shutdown_function(static function () use ($jsonResponses): void {
            $error = error_get_last();
            if ($error === null) {
                return;
            }
            $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
            if (!in_array($error['type'], $fatal, true)) {
                return;
            }
            self::log('FATAL', $error['message'], $error['file'], $error['line']);
            if (!headers_sent() && $jsonResponses) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'ok' => false,
                    'error' => safe_error_message('Erreur serveur temporaire'),
                ], JSON_UNESCAPED_UNICODE);
            }
        });
    }

    public static function log(string $level, string $message, ?string $file = null, ?int $line = null): void
    {
        self::ensureLogDir();
        $date = gmdate('Y-m-d H:i:s');
        $fileInfo = $file ? " {$file}:{$line}" : '';
        $lineOut = "[{$date}] [{$level}] {$message}{$fileInfo}\n";
        @file_put_contents(self::logPath(), $lineOut, FILE_APPEND | LOCK_EX);
    }

    private static function handleThrowable(Throwable $e, bool $jsonResponses): void
    {
        self::log('ERROR', $e->getMessage(), $e->getFile(), $e->getLine());

        if (headers_sent()) {
            return;
        }

        $public = safe_error_message($e->getMessage());

        if ($jsonResponses) {
            $code = $e instanceof InvalidArgumentException ? 400 : 500;
            http_response_code($code);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => $public], JSON_UNESCAPED_UNICODE);
            exit;
        }

        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Erreur</title></head><body>';
        echo '<h1>Service momentanément indisponible</h1>';
        echo '<p>' . htmlspecialchars($public, ENT_QUOTES, 'UTF-8') . '</p>';
        echo '<p><a href="index.php">Retour à la boutique</a></p></body></html>';
        exit;
    }

    private static function logPath(): string
    {
        return dirname(__DIR__) . '/storage/logs/app-' . gmdate('Y-m-d') . '.log';
    }

    private static function ensureLogDir(): void
    {
        $dir = dirname(__DIR__) . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }
}
