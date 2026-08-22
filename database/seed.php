<?php

declare(strict_types=1);

function js_object_keys_to_json(string $jsArray): string
{
    // Retire les virgules finales (syntaxe JS)
    $json = preg_replace('/,\s*(\]|\})/', '$1', $jsArray) ?? $jsArray;
    // Quote les clés d'objet JS : { id: "x" } → { "id": "x" }
    $json = preg_replace(
        '/([{\[,]\s*)([a-zA-Z_][a-zA-Z0-9_]*)(\s*):/',
        '$1"$2"$3:',
        $json
    ) ?? $json;

    return $json;
}

function load_products_from_js(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException('Fichier products.js introuvable');
    }

    $content = file_get_contents($path);
    if (!preg_match('/const\s+(?:FALLBACK_)?PRODUCTS\s*=\s*(\[[\s\S]*?\]);/m', $content, $matches)) {
        throw new RuntimeException('Impossible de lire products.js');
    }

    $json = js_object_keys_to_json($matches[1]);
    $products = json_decode($json, true);

    if (!is_array($products)) {
        $detail = json_last_error_msg();
        throw new RuntimeException('Format products.js invalide' . ($detail ? " ($detail)" : ''));
    }

    return $products;
}

function seed_products(PDO $pdo, array $products): int
{
    $count = 0;
    $sort = 0;

    foreach ($products as $product) {
        $benefits = $product['benefits'] ?? [];
        $stmt = $pdo->prepare(
            'INSERT INTO products (id, name, category, category_label, price, badge, image, short_desc, description, benefits, stock, is_active, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 100, 1, ?)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                category = VALUES(category),
                category_label = VALUES(category_label),
                price = VALUES(price),
                badge = VALUES(badge),
                image = VALUES(image),
                short_desc = VALUES(short_desc),
                description = VALUES(description),
                benefits = VALUES(benefits),
                sort_order = VALUES(sort_order)'
        );

        $stmt->execute([
            $product['id'],
            $product['name'],
            $product['category'],
            $product['categoryLabel'] ?? ucfirst($product['category']),
            (int) ($product['price'] ?? 0),
            $product['badge'] ?? null,
            $product['image'],
            $product['short'] ?? '',
            $product['desc'] ?? '',
            json_encode(array_values($benefits), JSON_UNESCAPED_UNICODE),
            $sort++,
        ]);
        $count++;
    }

    return $count;
}

function migrate_orders_json(PDO $pdo, string $jsonPath): int
{
    if (!is_file($jsonPath)) {
        return 0;
    }

    $orders = json_decode((string) file_get_contents($jsonPath), true);
    if (!is_array($orders) || !$orders) {
        return 0;
    }

    $imported = 0;
    foreach ($orders as $order) {
        $id = $order['id'] ?? '';
        if ($id === '') {
            continue;
        }

        $check = $pdo->prepare('SELECT id FROM orders WHERE id = ? LIMIT 1');
        $check->execute([$id]);
        if ($check->fetch()) {
            continue;
        }

        $pdo->prepare(
            'INSERT INTO orders (
                id, tracking_number, status, subtotal, shipping_fee, total, currency,
                customer_name, customer_phone, customer_email, customer_address, customer_city, customer_notes,
                payment_method, payment_paid, payment_reference, payment_paid_at,
                shipping_carrier, shipping_estimated_days, delivery_confirmed_at, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $id,
            $order['trackingNumber'] ?? generate_tracking(),
            $order['status'] ?? 'pending_payment',
            (int) ($order['subtotal'] ?? 0),
            (int) ($order['shippingFee'] ?? 0),
            (int) ($order['total'] ?? 0),
            $order['currency'] ?? 'XOF',
            $order['customer']['name'] ?? '',
            $order['customer']['phone'] ?? '',
            $order['customer']['email'] ?? '',
            $order['customer']['address'] ?? '',
            $order['customer']['city'] ?? '',
            $order['customer']['notes'] ?? '',
            $order['payment']['method'] ?? 'card',
            !empty($order['payment']['paid']) ? 1 : 0,
            $order['payment']['reference'] ?? null,
            !empty($order['payment']['paidAt']) ? date('Y-m-d H:i:s', strtotime($order['payment']['paidAt'])) : null,
            $order['shipping']['carrier'] ?? 'Pure Essence Express',
            (int) ($order['shipping']['estimatedDays'] ?? 3),
            !empty($order['deliveryConfirmedAt']) ? date('Y-m-d H:i:s', strtotime($order['deliveryConfirmedAt'])) : null,
            date('Y-m-d H:i:s', strtotime($order['createdAt'] ?? 'now')),
            date('Y-m-d H:i:s', strtotime($order['updatedAt'] ?? 'now')),
        ]);

        foreach ($order['items'] ?? [] as $item) {
            $pdo->prepare(
                'INSERT INTO order_items (order_id, product_id, product_name, price, qty, image) VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([
                $id,
                $item['id'] ?? '',
                $item['name'] ?? 'Produit',
                (int) ($item['price'] ?? 0),
                (int) ($item['qty'] ?? 1),
                $item['image'] ?? '',
            ]);
        }

        foreach ($order['timeline'] ?? [] as $entry) {
            $pdo->prepare(
                'INSERT INTO order_timeline (order_id, status, label, note, created_at) VALUES (?, ?, ?, ?, ?)'
            )->execute([
                $id,
                $entry['status'] ?? '',
                $entry['label'] ?? '',
                $entry['note'] ?? '',
                date('Y-m-d H:i:s', strtotime($entry['at'] ?? 'now')),
            ]);
        }

        $imported++;
    }

    return $imported;
}

function generate_tracking(): string
{
    return 'TRK' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
}
