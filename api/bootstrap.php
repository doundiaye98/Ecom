<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/Env.php';
require_once dirname(__DIR__) . '/includes/ErrorHandler.php';
require_once dirname(__DIR__) . '/includes/RateLimiter.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/Auth.php';
require_once dirname(__DIR__) . '/includes/Csrf.php';
require_once dirname(__DIR__) . '/includes/SettingsRepository.php';
require_once dirname(__DIR__) . '/includes/ProductRepository.php';
require_once dirname(__DIR__) . '/includes/OrderRepository.php';
require_once dirname(__DIR__) . '/includes/ContactRepository.php';

Env::load();
ErrorHandler::register(true);

date_default_timezone_set(app_config()['timezone'] ?? 'UTC');

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$appUrl = rtrim(Env::get('APP_URL', '') ?? '', '/');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && $appUrl !== '' && str_starts_with($origin, $appUrl)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
} elseif (is_debug_mode()) {
    header('Access-Control-Allow-Origin: *');
} elseif ($appUrl !== '') {
    header('Access-Control-Allow-Origin: ' . $appUrl);
}

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!is_file(dirname(__DIR__) . '/config/database.php')) {
    respond(['ok' => false, 'error' => 'Site non installé. Ouvrez install.php'], 503);
}

try {
    Database::connect(db_config());
} catch (Throwable $e) {
    ErrorHandler::log('DB', $e->getMessage());
    respond(['ok' => false, 'error' => 'Connexion base de données impossible'], 503);
}

$rateGet = Env::int('RATE_LIMIT_GET', 180);
$ratePost = Env::int('RATE_LIMIT_POST', 40);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    RateLimiter::enforce('api_write', $ratePost, 60);
} else {
    RateLimiter::enforce('api_read', $rateGet, 60);
}

function api_input(): array
{
    return array_merge($_GET, $_POST, json_input());
}

function api_action(array $input): string
{
    $action = $_GET['action'] ?? '';
    if ($action === '' && isset($input['action'])) {
        $action = (string) $input['action'];
    }
    return $action;
}

function api_require_admin_csrf(array $input): void
{
    Auth::requireAdmin();
    $token = (string) ($input['_csrf'] ?? $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!Csrf::validate($token)) {
        respond(['ok' => false, 'error' => 'Token CSRF invalide ou session expirée'], 403);
    }
}
