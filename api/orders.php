<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$input = api_input();
$action = api_action($input);

try {
    switch ($action) {
        case 'list':
            respond(['ok' => true, 'orders' => OrderRepository::listAll()]);

        case 'get':
            $id = trim((string) ($input['id'] ?? ''));
            if ($id === '') {
                respond(['ok' => false, 'error' => 'ID manquant'], 400);
            }
            $order = OrderRepository::findFormatted($id);
            if (!$order) {
                respond(['ok' => false, 'error' => 'Commande introuvable'], 404);
            }
            respond(['ok' => true, 'order' => $order]);

        case 'search':
            $phone = (string) ($input['phone'] ?? '');
            $email = (string) ($input['email'] ?? '');
            respond([
                'ok' => true,
                'orders' => OrderRepository::search($phone, $email),
            ]);

        case 'create':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                respond(['ok' => false, 'error' => 'POST requis'], 405);
            }
            try {
                $order = OrderRepository::create($input);
            } catch (InvalidArgumentException $e) {
                respond(['ok' => false, 'error' => $e->getMessage()], 400);
            }
            respond(['ok' => true, 'order' => $order], 201);

        case 'update_status':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                respond(['ok' => false, 'error' => 'POST requis'], 405);
            }

            // Session admin OU clé legacy (migration)
            $legacyKey = (string) ($input['adminKey'] ?? '');
            $app = app_config();
            $legacyOk = $legacyKey !== '' && $legacyKey === ($app['default_admin']['password'] ?? '');

            if (!Auth::check() && !$legacyOk) {
                respond(['ok' => false, 'error' => 'Accès admin refusé'], 403);
            }

            $id = trim((string) ($input['id'] ?? ''));
            $newStatus = trim((string) ($input['status'] ?? ''));
            $note = trim((string) ($input['note'] ?? ''));

            if ($id === '' || $newStatus === '') {
                respond(['ok' => false, 'error' => 'Paramètres manquants'], 400);
            }

            try {
                $order = OrderRepository::updateStatus($id, $newStatus, $note);
            } catch (InvalidArgumentException $e) {
                respond(['ok' => false, 'error' => $e->getMessage()], 400);
            }
            respond(['ok' => true, 'order' => $order]);

        case 'confirm_delivery':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                respond(['ok' => false, 'error' => 'POST requis'], 405);
            }
            $id = trim((string) ($input['id'] ?? ''));
            $phone = (string) ($input['phone'] ?? '');
            if ($id === '') {
                respond(['ok' => false, 'error' => 'ID manquant'], 400);
            }
            try {
                $order = OrderRepository::confirmDelivery($id, $phone);
            } catch (InvalidArgumentException $e) {
                respond(['ok' => false, 'error' => $e->getMessage()], 400);
            }
            respond(['ok' => true, 'order' => $order]);

        case 'statuses':
            respond([
                'ok' => true,
                'statuses' => order_status_labels(),
                'flow' => order_status_flow(),
            ]);

        default:
            respond(['ok' => false, 'error' => 'Action inconnue'], 400);
    }
} catch (Throwable $e) {
    respond(['ok' => false, 'error' => $e->getMessage()], 500);
}
