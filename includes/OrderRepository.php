<?php

declare(strict_types=1);

final class OrderRepository
{
    private static function fetchItems(string $orderId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC');
        $stmt->execute([$orderId]);
        return $stmt->fetchAll();
    }

    private static function fetchTimeline(string $orderId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM order_timeline WHERE order_id = ? ORDER BY id ASC');
        $stmt->execute([$orderId]);
        return $stmt->fetchAll();
    }

    public static function findFormatted(string $idOrTracking): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM orders WHERE id = ? OR tracking_number = ? LIMIT 1'
        );
        $stmt->execute([$idOrTracking, $idOrTracking]);
        $order = $stmt->fetch();
        if (!$order) {
            return null;
        }

        return format_order_row(
            $order,
            self::fetchItems($order['id']),
            self::fetchTimeline($order['id'])
        );
    }

    public static function listAll(): array
    {
        $stmt = Database::pdo()->query('SELECT * FROM orders ORDER BY created_at DESC');
        $orders = [];
        foreach ($stmt->fetchAll() as $order) {
            $orders[] = format_order_row(
                $order,
                self::fetchItems($order['id']),
                self::fetchTimeline($order['id'])
            );
        }
        return $orders;
    }

    public static function search(string $phone, string $email): array
    {
        $orders = self::listAll();
        $phoneNorm = normalize_phone($phone);
        $emailNorm = strtolower(trim($email));

        return array_values(array_filter($orders, static function (array $order) use ($phoneNorm, $emailNorm): bool {
            $oPhone = normalize_phone($order['customer']['phone'] ?? '');
            $oEmail = strtolower($order['customer']['email'] ?? '');
            $matchPhone = $phoneNorm !== '' && $oPhone !== '' && (
                ends_with($oPhone, $phoneNorm) ||
                ends_with($phoneNorm, $oPhone) ||
                $oPhone === $phoneNorm
            );
            $matchEmail = $emailNorm !== '' && $oEmail === $emailNorm;
            return $matchPhone || $matchEmail;
        }));
    }

    public static function create(array $input): array
    {
        $itemsInput = $input['items'] ?? [];
        $customer = $input['customer'] ?? [];
        $payment = $input['payment'] ?? [];
        $shipping = $input['shipping'] ?? [];

        if (!is_array($itemsInput) || count($itemsInput) < 1) {
            throw new InvalidArgumentException('Panier vide');
        }

        if (empty($customer['name']) || empty($customer['phone']) || empty($customer['address']) || empty($customer['city'])) {
            throw new InvalidArgumentException('Informations client incomplètes');
        }

        $productIds = [];
        $qtyMap = [];
        foreach ($itemsInput as $item) {
            $id = (string) ($item['id'] ?? '');
            $qty = max(1, (int) ($item['qty'] ?? 1));
            if ($id === '') {
                continue;
            }
            $productIds[] = $id;
            $qtyMap[$id] = ($qtyMap[$id] ?? 0) + $qty;
        }

        if (!$productIds) {
            throw new InvalidArgumentException('Panier invalide');
        }

        $products = ProductRepository::findManyByIds(array_unique($productIds));
        if (count($products) !== count(array_unique($productIds))) {
            throw new InvalidArgumentException('Un ou plusieurs produits sont introuvables');
        }

        $cleanItems = [];
        $subtotal = 0;
        foreach ($qtyMap as $id => $qty) {
            $product = $products[$id];
            if ($product['stock'] < $qty) {
                throw new InvalidArgumentException('Stock insuffisant pour : ' . $product['name']);
            }
            $lineTotal = $product['price'] * $qty;
            $subtotal += $lineTotal;
            $cleanItems[] = [
                'id' => $product['id'],
                'name' => $product['name'],
                'price' => $product['price'],
                'qty' => $qty,
                'image' => $product['image'],
            ];
        }

        $shippingFee = SettingsRepository::getInt('shipping_fee', 2000);
        $freeFrom = SettingsRepository::getInt('free_shipping_from', 50000);
        if ($subtotal >= $freeFrom) {
            $shippingFee = 0;
        }

        $total = $subtotal + $shippingFee;
        $method = (string) ($payment['method'] ?? 'cod');
        $paid = !empty($payment['paid']);
        $status = match (true) {
            $method === 'cod' => 'processing',
            $paid => 'paid',
            default => 'pending_payment',
        };
        $now = gmdate('Y-m-d H:i:s');

        $orderId = generate_order_id();
        $tracking = generate_tracking();
        $reference = (string) ($payment['reference'] ?? ('PAY-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8))));

        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO orders (
                    id, tracking_number, status, subtotal, shipping_fee, total, currency,
                    customer_name, customer_phone, customer_email, customer_address, customer_city, customer_notes,
                    payment_method, payment_paid, payment_reference, payment_paid_at,
                    shipping_carrier, shipping_estimated_days
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $stmt->execute([
                $orderId,
                $tracking,
                $status,
                $subtotal,
                $shippingFee,
                $total,
                'XOF',
                trim((string) $customer['name']),
                trim((string) $customer['phone']),
                trim((string) ($customer['email'] ?? '')),
                trim((string) $customer['address']),
                trim((string) $customer['city']),
                trim((string) ($customer['notes'] ?? '')),
                $method,
                $paid ? 1 : 0,
                $reference,
                $paid ? $now : null,
                (string) ($shipping['carrier'] ?? 'Pure Essence Express'),
                (int) ($shipping['estimatedDays'] ?? 3),
            ]);

            $itemStmt = $pdo->prepare(
                'INSERT INTO order_items (order_id, product_id, product_name, price, qty, image)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            foreach ($cleanItems as $item) {
                $itemStmt->execute([
                    $orderId,
                    $item['id'],
                    $item['name'],
                    $item['price'],
                    $item['qty'],
                    $item['image'],
                ]);
                ProductRepository::decrementStock($item['id'], $item['qty']);
            }

            $labels = order_status_labels();
            self::addTimeline($orderId, 'pending_payment', $labels['pending_payment'], 'Commande créée', $now);

            if ($status === 'paid' || $status === 'processing') {
                self::addTimeline(
                    $orderId,
                    'paid',
                    $labels['paid'],
                    $method === 'cod'
                        ? 'Paiement à la livraison sélectionné'
                        : 'Paiement reçu via ' . strtoupper($method),
                    $now
                );
            }

            if ($status === 'processing') {
                self::addTimeline($orderId, 'processing', $labels['processing'], 'Commande en cours de préparation', $now);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return self::findFormatted($orderId) ?? [];
    }

    public static function updateStatus(string $id, string $newStatus, string $note = ''): array
    {
        $labels = order_status_labels();
        if (!isset($labels[$newStatus])) {
            throw new InvalidArgumentException('Statut invalide');
        }

        $order = self::findFormatted($id);
        if (!$order) {
            throw new InvalidArgumentException('Commande introuvable');
        }

        $now = gmdate('Y-m-d H:i:s');
        $pdo = Database::pdo();

        $pdo->prepare('UPDATE orders SET status = ?, updated_at = ? WHERE id = ?')
            ->execute([$newStatus, $now, $id]);

        if ($newStatus === 'paid') {
            $pdo->prepare('UPDATE orders SET payment_paid = 1, payment_paid_at = ? WHERE id = ?')
                ->execute([$now, $id]);
        }

        if ($newStatus === 'completed') {
            $pdo->prepare('UPDATE orders SET delivery_confirmed_at = ? WHERE id = ?')
                ->execute([$now, $id]);
        }

        self::addTimeline(
            $id,
            $newStatus,
            $labels[$newStatus],
            $note !== '' ? $note : $labels[$newStatus],
            $now
        );

        return self::findFormatted($id) ?? [];
    }

    public static function confirmDelivery(string $id, string $phone = ''): array
    {
        $order = self::findFormatted($id);
        if (!$order) {
            throw new InvalidArgumentException('Commande introuvable');
        }

        $oPhone = normalize_phone($order['customer']['phone'] ?? '');
        $phoneNorm = normalize_phone($phone);

        if ($phoneNorm !== '' && $oPhone !== '' && $phoneNorm !== $oPhone &&
            !ends_with($oPhone, $phoneNorm) && !ends_with($phoneNorm, $oPhone)) {
            throw new InvalidArgumentException('Téléphone non reconnu pour cette commande');
        }

        if (!in_array($order['status'], ['delivered', 'out_for_delivery', 'in_transit'], true)) {
            throw new InvalidArgumentException('La commande n\'est pas encore livrable / livrée');
        }

        return self::updateStatus($id, 'completed', 'Le client a confirmé la réception de la commande');
    }

    private static function addTimeline(string $orderId, string $status, string $label, string $note, string $at): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO order_timeline (order_id, status, label, note, created_at) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$orderId, $status, $label, $note, $at]);
    }
}
