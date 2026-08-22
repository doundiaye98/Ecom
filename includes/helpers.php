<?php

declare(strict_types=1);

function app_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require dirname(__DIR__) . '/config/app.php';
    }
    return $config;
}

function db_config(): array
{
    static $config = null;
    if ($config === null) {
        $path = dirname(__DIR__) . '/config/database.php';
        if (!is_file($path)) {
            throw new RuntimeException('Configuration base de données manquante. Lancez install.php');
        }
        $config = require $path;
    }
    return $config;
}

function ends_with(string $haystack, string $needle): bool
{
    if ($needle === '') {
        return true;
    }
    return substr($haystack, -strlen($needle)) === $needle;
}

function json_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function respond(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function generate_order_id(): string
{
    return 'PEV-' . strtoupper(base_convert((string) time(), 10, 36)) . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

function generate_tracking(): string
{
    return 'TRK' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
}

function normalize_phone(string $phone): string
{
    return preg_replace('/\D+/', '', $phone) ?? '';
}

/** Numéro mobile Sénégal au format international 221XXXXXXXXX */
function normalize_phone_sn(string $phone): string
{
    $digits = normalize_phone($phone);
    if ($digits === '') {
        return '';
    }
    if (strlen($digits) === 9 && str_starts_with($digits, '7')) {
        return '221' . $digits;
    }
    if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
        return '221' . substr($digits, 1);
    }
    if (strlen($digits) === 12 && str_starts_with($digits, '221')) {
        return $digits;
    }
    return $digits;
}

function is_valid_phone_sn(string $phone): bool
{
    $digits = normalize_phone_sn($phone);
    return (bool) preg_match('/^2217[0-9]{8}$/', $digits);
}

function order_status_labels(): array
{
    return [
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
}

function order_status_flow(): array
{
    return [
        'pending_payment',
        'paid',
        'processing',
        'shipped',
        'in_transit',
        'out_for_delivery',
        'delivered',
        'completed',
    ];
}

function format_product_row(array $row): array
{
    $benefits = $row['benefits'] ?? '[]';
    if (is_string($benefits)) {
        $benefits = json_decode($benefits, true) ?: [];
    }

    return [
        'id' => $row['id'],
        'name' => $row['name'],
        'category' => $row['category'],
        'categoryLabel' => $row['category_label'],
        'price' => (int) $row['price'],
        'badge' => $row['badge'] ?: null,
        'image' => $row['image'],
        'short' => $row['short_desc'],
        'desc' => $row['description'],
        'benefits' => $benefits,
        'stock' => (int) ($row['stock'] ?? 0),
        'isActive' => (bool) ($row['is_active'] ?? true),
    ];
}

function format_order_row(array $order, array $items, array $timeline): array
{
    return [
        'id' => $order['id'],
        'trackingNumber' => $order['tracking_number'],
        'createdAt' => gmdate('c', strtotime($order['created_at'])),
        'updatedAt' => gmdate('c', strtotime($order['updated_at'])),
        'status' => $order['status'],
        'items' => array_map(static function (array $item): array {
            return [
                'id' => $item['product_id'],
                'name' => $item['product_name'],
                'price' => (int) $item['price'],
                'qty' => (int) $item['qty'],
                'image' => $item['image'] ?? '',
            ];
        }, $items),
        'subtotal' => (int) $order['subtotal'],
        'shippingFee' => (int) $order['shipping_fee'],
        'total' => (int) $order['total'],
        'currency' => $order['currency'],
        'customer' => [
            'name' => $order['customer_name'],
            'phone' => $order['customer_phone'],
            'email' => $order['customer_email'] ?? '',
            'address' => $order['customer_address'],
            'city' => $order['customer_city'],
            'notes' => $order['customer_notes'] ?? '',
        ],
        'payment' => [
            'method' => $order['payment_method'],
            'paid' => (bool) $order['payment_paid'],
            'reference' => $order['payment_reference'] ?? '',
            'paidAt' => $order['payment_paid_at']
                ? gmdate('c', strtotime($order['payment_paid_at']))
                : null,
        ],
        'shipping' => [
            'carrier' => $order['shipping_carrier'],
            'estimatedDays' => (int) $order['shipping_estimated_days'],
            'fee' => (int) $order['shipping_fee'],
        ],
        'timeline' => array_map(static function (array $t): array {
            return [
                'status' => $t['status'],
                'label' => $t['label'],
                'at' => gmdate('c', strtotime($t['created_at'])),
                'note' => $t['note'] ?? '',
            ];
        }, $timeline),
        'deliveryConfirmedAt' => $order['delivery_confirmed_at']
            ? gmdate('c', strtotime($order['delivery_confirmed_at']))
            : null,
    ];
}
