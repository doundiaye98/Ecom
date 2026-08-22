<?php

declare(strict_types=1);

final class Auth
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $app = app_config();
        session_name($app['session_name'] ?? 'PEV_SESSION');
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        session_set_cookie_params([
            'lifetime' => (int) ($app['session_lifetime'] ?? 86400),
            'path' => '/',
            'httponly' => true,
            'secure' => $secure,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function login(string $username, string $password): bool
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id, username, password_hash FROM admin_users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        self::startSession();
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $user['id'];
        $_SESSION['admin_username'] = $user['username'];
        $_SESSION['admin_logged_in'] = true;

        require_once __DIR__ . '/Csrf.php';
        Csrf::regenerate();

        return true;
    }

    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'] ?? false, $params['httponly'] ?? true);
        }
        session_destroy();
    }

    public static function check(): bool
    {
        self::startSession();
        return !empty($_SESSION['admin_logged_in']) && !empty($_SESSION['admin_id']);
    }

    public static function requireAdmin(): void
    {
        if (!self::check()) {
            respond(['ok' => false, 'error' => 'Authentification admin requise'], 401);
        }
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }
        return [
            'id' => (int) $_SESSION['admin_id'],
            'username' => (string) $_SESSION['admin_username'],
        ];
    }

    public static function createAdmin(string $username, string $password): void
    {
        $pdo = Database::pdo();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            'INSERT INTO admin_users (username, password_hash) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)'
        );
        $stmt->execute([$username, $hash]);
    }
}
