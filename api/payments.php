<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/PaymentService.php';

$input = api_input();
$action = api_action($input);

function payment_headers(): array
{
    $headers = [];
    foreach ($_SERVER as $key => $value) {
        if (str_starts_with($key, 'HTTP_')) {
            $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
            $headers[$name] = (string) $value;
        }
    }
    return $headers;
}

try {
    switch ($action) {
        case 'config':
            respond([
                'ok' => true,
                'payments' => PaymentService::publicConfig(),
            ]);

        case 'check':
            respond([
                'ok' => true,
                'deployment' => PaymentService::deploymentCheck(),
            ]);

        case 'initiate':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                respond(['ok' => false, 'error' => 'POST requis'], 405);
            }
            $method = trim((string) ($input['method'] ?? ''));
            $result = PaymentService::initiate($method, $input);
            respond(['ok' => true, 'payment' => $result]);

        case 'verify':
            $method = trim((string) ($input['method'] ?? ''));
            $reference = trim((string) ($input['reference'] ?? ''));
            if ($method === '' || $reference === '') {
                respond(['ok' => false, 'error' => 'Paramètres manquants'], 400);
            }
            $result = PaymentService::verify($method, $reference);
            respond(['ok' => true, 'payment' => $result]);

        case 'webhook_wave':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                respond(['ok' => false, 'error' => 'POST requis'], 405);
            }
            $raw = file_get_contents('php://input') ?: '';
            $result = PaymentService::handleWebhook('wave', $raw, payment_headers());
            respond(['ok' => true, 'received' => true, 'webhook' => $result]);

        case 'webhook_orange':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                respond(['ok' => false, 'error' => 'POST requis'], 405);
            }
            $raw = file_get_contents('php://input') ?: '';
            $result = PaymentService::handleWebhook('orange', $raw, payment_headers());
            respond(['ok' => true, 'received' => true, 'webhook' => $result]);

        default:
            respond(['ok' => false, 'error' => 'Action inconnue'], 400);
    }
} catch (InvalidArgumentException $e) {
    respond(['ok' => false, 'error' => $e->getMessage()], 400);
} catch (Throwable $e) {
    respond(['ok' => false, 'error' => $e->getMessage()], 500);
}
