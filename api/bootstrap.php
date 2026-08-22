<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/Env.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/Auth.php';
require_once dirname(__DIR__) . '/includes/SettingsRepository.php';
require_once dirname(__DIR__) . '/includes/ProductRepository.php';
require_once dirname(__DIR__) . '/includes/OrderRepository.php';
require_once dirname(__DIR__) . '/includes/ContactRepository.php';

Env::load();

date_default_timezone_set(app_config()['timezone'] ?? 'UTC');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

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
    respond(['ok' => false, 'error' => 'Connexion base de données impossible'], 503);
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
