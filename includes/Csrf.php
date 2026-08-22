<?php

declare(strict_types=1);

final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function init(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            Auth::startSession();
        }
        if (empty($_SESSION[self::SESSION_KEY])) {
            self::regenerate();
        }
    }

    public static function regenerate(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            Auth::startSession();
        }
        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
    }

    public static function token(): string
    {
        self::init();
        return (string) $_SESSION[self::SESSION_KEY];
    }

    public static function validate(?string $token): bool
    {
        self::init();
        if (!is_string($token) || $token === '') {
            return false;
        }
        return hash_equals((string) $_SESSION[self::SESSION_KEY], $token);
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' .
            htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '" />';
    }

    public static function requireValid(?string $token = null): void
    {
        $token ??= (string) ($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!self::validate($token)) {
            http_response_code(403);
            $wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') ||
                str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
            if ($wantsJson && function_exists('respond')) {
                respond(['ok' => false, 'error' => 'Token CSRF invalide ou session expirée'], 403);
            }
            exit('Session expirée ou requête invalide. Rechargez la page.');
        }
    }
}
