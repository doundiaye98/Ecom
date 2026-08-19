<?php
/**
 * Pure Essence Vita — Orders API
 * Actions: list | get | create | update_status | confirm_delivery | search
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function ends_with(string $haystack, string $needle): bool {
    if ($needle === '') return true;
    return substr($haystack, -strlen($needle)) === $needle;
}

$dataFile = dirname(__DIR__) . '/data/orders.json';
$adminKey = 'pev_admin_2026'; // Changez ce mot de passe admin

function read_orders(string $file): array {
    if (!file_exists($file)) {
        file_put_contents($file, '[]');
        return [];
    }
    $raw = file_get_contents($file);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function write_orders(string $file, array $orders): bool {
    $fp = fopen($file, 'c+');
    if (!$fp) return false;
    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode(array_values($orders), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return true;
}

function json_input(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function respond($payload, int $code = 200): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function generate_order_id(): string {
    return 'PEV-' . strtoupper(base_convert((string) time(), 10, 36)) . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

function generate_tracking(): string {
    return 'TRK' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
}

$STATUS_FLOW = [
    'pending_payment',
    'paid',
    'processing',
    'shipped',
    'in_transit',
    'out_for_delivery',
    'delivered',
    'completed',
];

$STATUS_LABELS = [
    'pending_payment' => 'En attente de paiement',
    'paid' => 'Paiement confirmé',
    'processing' => 'Préparation de la commande',
    'shipped' => 'Commande expédiée',
    'in_transit' => 'En transit',
    'out_for_delivery' => 'En cours de livraison',
    'delivered' => 'Livrée — en attente de validation',
    'completed' => 'Livraison validée',
    'cancelled' => 'Annulée',
];

$action = $_GET['action'] ?? '';
$input = array_merge($_GET, $_POST, json_input());

if ($action === '' && isset($input['action'])) {
    $action = $input['action'];
}

switch ($action) {
    case 'list':
        $orders = read_orders($dataFile);
        usort($orders, fn($a, $b) => strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? ''));
        respond(['ok' => true, 'orders' => $orders]);

    case 'get':
        $id = trim((string)($input['id'] ?? ''));
        if ($id === '') respond(['ok' => false, 'error' => 'ID manquant'], 400);
        $orders = read_orders($dataFile);
        foreach ($orders as $order) {
            if (($order['id'] ?? '') === $id || ($order['trackingNumber'] ?? '') === $id) {
                respond(['ok' => true, 'order' => $order]);
            }
        }
        respond(['ok' => false, 'error' => 'Commande introuvable'], 404);

    case 'search':
        $phone = preg_replace('/\D+/', '', (string)($input['phone'] ?? ''));
        $email = strtolower(trim((string)($input['email'] ?? '')));
        $orders = read_orders($dataFile);
        $found = array_values(array_filter($orders, function ($o) use ($phone, $email) {
            $oPhone = preg_replace('/\D+/', '', (string)($o['customer']['phone'] ?? ''));
            $oEmail = strtolower((string)($o['customer']['email'] ?? ''));
            $matchPhone = $phone !== '' && $oPhone !== '' && (ends_with($oPhone, $phone) || ends_with($phone, $oPhone) || $oPhone === $phone);
            $matchEmail = $email !== '' && $oEmail === $email;
            return $matchPhone || $matchEmail;
        }));
        usort($found, fn($a, $b) => strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? ''));
        respond(['ok' => true, 'orders' => $found]);

    case 'create':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond(['ok' => false, 'error' => 'POST requis'], 405);
        }

        $items = $input['items'] ?? [];
        $customer = $input['customer'] ?? [];
        $payment = $input['payment'] ?? [];
        $shipping = $input['shipping'] ?? [];

        if (!is_array($items) || count($items) < 1) {
            respond(['ok' => false, 'error' => 'Panier vide'], 400);
        }
        if (empty($customer['name']) || empty($customer['phone']) || empty($customer['address'])) {
            respond(['ok' => false, 'error' => 'Informations client incomplètes'], 400);
        }

        $method = $payment['method'] ?? 'card';
        $paid = !empty($payment['paid']) || $method === 'cod';
        $now = gmdate('c');
        $status = $paid ? ($method === 'cod' ? 'processing' : 'paid') : 'pending_payment';

        $timeline = [[
            'status' => 'pending_payment',
            'label' => $STATUS_LABELS['pending_payment'],
            'at' => $now,
            'note' => 'Commande créée',
        ]];

        if ($status === 'paid' || $status === 'processing') {
            $timeline[] = [
                'status' => 'paid',
                'label' => $STATUS_LABELS['paid'],
                'at' => $now,
                'note' => $method === 'cod'
                    ? 'Paiement à la livraison sélectionné'
                    : 'Paiement reçu via ' . strtoupper((string)$method),
            ];
        }
        if ($status === 'processing') {
            $timeline[] = [
                'status' => 'processing',
                'label' => $STATUS_LABELS['processing'],
                'at' => $now,
                'note' => 'Commande en cours de préparation',
            ];
        }

        $subtotal = 0;
        $cleanItems = [];
        foreach ($items as $item) {
            $qty = max(1, (int)($item['qty'] ?? 1));
            $price = max(0, (int)($item['price'] ?? 0));
            $subtotal += $qty * $price;
            $cleanItems[] = [
                'id' => (string)($item['id'] ?? ''),
                'name' => (string)($item['name'] ?? 'Produit'),
                'price' => $price,
                'qty' => $qty,
                'image' => (string)($item['image'] ?? ''),
            ];
        }

        $shippingFee = (int)($shipping['fee'] ?? 0);
        $total = $subtotal + $shippingFee;

        $order = [
            'id' => generate_order_id(),
            'trackingNumber' => generate_tracking(),
            'createdAt' => $now,
            'updatedAt' => $now,
            'status' => $status,
            'items' => $cleanItems,
            'subtotal' => $subtotal,
            'shippingFee' => $shippingFee,
            'total' => $total,
            'currency' => 'XOF',
            'customer' => [
                'name' => trim((string)$customer['name']),
                'phone' => trim((string)$customer['phone']),
                'email' => trim((string)($customer['email'] ?? '')),
                'address' => trim((string)$customer['address']),
                'city' => trim((string)($customer['city'] ?? '')),
                'notes' => trim((string)($customer['notes'] ?? '')),
            ],
            'payment' => [
                'method' => $method,
                'paid' => (bool)$paid,
                'reference' => (string)($payment['reference'] ?? ('PAY-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)))),
                'paidAt' => $paid ? $now : null,
            ],
            'shipping' => [
                'carrier' => (string)($shipping['carrier'] ?? 'Pure Essence Express'),
                'estimatedDays' => (int)($shipping['estimatedDays'] ?? 3),
                'fee' => $shippingFee,
            ],
            'timeline' => $timeline,
            'deliveryConfirmedAt' => null,
        ];

        $orders = read_orders($dataFile);
        array_unshift($orders, $order);
        if (!write_orders($dataFile, $orders)) {
            respond(['ok' => false, 'error' => 'Impossible d\'enregistrer la commande'], 500);
        }

        respond(['ok' => true, 'order' => $order], 201);

    case 'update_status':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond(['ok' => false, 'error' => 'POST requis'], 405);
        }
        $key = (string)($input['adminKey'] ?? '');
        if ($key !== $adminKey) {
            respond(['ok' => false, 'error' => 'Accès admin refusé'], 403);
        }

        $id = trim((string)($input['id'] ?? ''));
        $newStatus = trim((string)($input['status'] ?? ''));
        $note = trim((string)($input['note'] ?? ''));

        if ($id === '' || $newStatus === '') {
            respond(['ok' => false, 'error' => 'Paramètres manquants'], 400);
        }
        if (!isset($STATUS_LABELS[$newStatus])) {
            respond(['ok' => false, 'error' => 'Statut invalide'], 400);
        }

        $orders = read_orders($dataFile);
        $found = false;
        foreach ($orders as &$order) {
            if (($order['id'] ?? '') !== $id) continue;
            $found = true;
            $now = gmdate('c');
            $order['status'] = $newStatus;
            $order['updatedAt'] = $now;
            if ($newStatus === 'paid') {
                $order['payment']['paid'] = true;
                $order['payment']['paidAt'] = $now;
            }
            if ($newStatus === 'completed') {
                $order['deliveryConfirmedAt'] = $now;
            }
            $order['timeline'][] = [
                'status' => $newStatus,
                'label' => $STATUS_LABELS[$newStatus],
                'at' => $now,
                'note' => $note !== '' ? $note : $STATUS_LABELS[$newStatus],
            ];
            break;
        }
        unset($order);

        if (!$found) respond(['ok' => false, 'error' => 'Commande introuvable'], 404);
        write_orders($dataFile, $orders);
        $updated = null;
        foreach ($orders as $o) {
            if ($o['id'] === $id) { $updated = $o; break; }
        }
        respond(['ok' => true, 'order' => $updated]);

    case 'confirm_delivery':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond(['ok' => false, 'error' => 'POST requis'], 405);
        }

        $id = trim((string)($input['id'] ?? ''));
        $phone = preg_replace('/\D+/', '', (string)($input['phone'] ?? ''));
        if ($id === '') respond(['ok' => false, 'error' => 'ID manquant'], 400);

        $orders = read_orders($dataFile);
        $found = false;
        foreach ($orders as &$order) {
            if (($order['id'] ?? '') !== $id) continue;
            $found = true;
            $oPhone = preg_replace('/\D+/', '', (string)($order['customer']['phone'] ?? ''));
            if ($phone !== '' && $oPhone !== '' && $phone !== $oPhone && !ends_with($oPhone, $phone) && !ends_with($phone, $oPhone)) {
                respond(['ok' => false, 'error' => 'Téléphone non reconnu pour cette commande'], 403);
            }
            if (!in_array($order['status'], ['delivered', 'out_for_delivery', 'in_transit'], true)) {
                respond(['ok' => false, 'error' => 'La commande n\'est pas encore livrable / livrée'], 400);
            }
            $now = gmdate('c');
            $order['status'] = 'completed';
            $order['updatedAt'] = $now;
            $order['deliveryConfirmedAt'] = $now;
            $order['timeline'][] = [
                'status' => 'completed',
                'label' => $STATUS_LABELS['completed'],
                'at' => $now,
                'note' => 'Le client a confirmé la réception de la commande',
            ];
            break;
        }
        unset($order);

        if (!$found) respond(['ok' => false, 'error' => 'Commande introuvable'], 404);
        write_orders($dataFile, $orders);
        $updated = null;
        foreach ($orders as $o) {
            if ($o['id'] === $id) { $updated = $o; break; }
        }
        respond(['ok' => true, 'order' => $updated]);

    case 'statuses':
        respond(['ok' => true, 'statuses' => $STATUS_LABELS, 'flow' => $STATUS_FLOW]);

    default:
        respond(['ok' => false, 'error' => 'Action inconnue. Utilisez list, get, create, update_status, confirm_delivery, search'], 400);
}
