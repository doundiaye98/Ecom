<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$input = api_input();
$action = api_action($input);

try {
    switch ($action) {
        case 'list':
            Auth::requireAdmin();
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
            if (!Auth::check()) {
                require_once dirname(__DIR__) . '/includes/CustomerAuth.php';
                CustomerAuth::requireCustomer();
                if (!OrderRepository::belongsToCustomer($id, CustomerAuth::id() ?? 0)) {
                    respond(['ok' => false, 'error' => 'Accès refusé à cette commande'], 403);
                }
            }
            respond(['ok' => true, 'order' => $order]);

        case 'mine':
            require_once dirname(__DIR__) . '/includes/CustomerAuth.php';
            CustomerAuth::requireCustomer();
            respond([
                'ok' => true,
                'orders' => OrderRepository::listForCustomer(CustomerAuth::id() ?? 0),
            ]);

        case 'search':
            require_once dirname(__DIR__) . '/includes/CustomerAuth.php';
            CustomerAuth::requireCustomer();
            respond([
                'ok' => true,
                'orders' => OrderRepository::listForCustomer(CustomerAuth::id() ?? 0),
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

            api_require_admin_csrf($input);

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
            require_once dirname(__DIR__) . '/includes/CustomerAuth.php';
            CustomerAuth::requireCustomer();

            $id = trim((string) ($input['id'] ?? ''));
            $phone = (string) ($input['phone'] ?? '');
            if ($id === '') {
                respond(['ok' => false, 'error' => 'ID manquant'], 400);
            }
            if (!OrderRepository::belongsToCustomer($id, CustomerAuth::id() ?? 0)) {
                respond(['ok' => false, 'error' => 'Accès refusé à cette commande'], 403);
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
    ErrorHandler::log('API', $e->getMessage(), $e->getFile(), $e->getLine());
    respond(['ok' => false, 'error' => safe_error_message($e->getMessage())], 500);
}
