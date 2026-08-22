<?php

declare(strict_types=1);

final class ProductRepository
{
    public static function listActive(?string $category = null, ?string $search = null): array
    {
        $sql = 'SELECT * FROM products WHERE is_active = 1';
        $params = [];

        if ($category && $category !== 'all') {
            $sql .= ' AND category = ?';
            $params[] = $category;
        }

        if ($search) {
            $sql .= ' AND (name LIKE ? OR short_desc LIKE ? OR category_label LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= ' ORDER BY sort_order ASC, name ASC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return array_map('format_product_row', $stmt->fetchAll());
    }

    public static function listAll(): array
    {
        $stmt = Database::pdo()->query('SELECT * FROM products ORDER BY sort_order ASC, name ASC');
        return array_map('format_product_row', $stmt->fetchAll());
    }

    public static function findById(string $id, bool $activeOnly = true): ?array
    {
        $sql = 'SELECT * FROM products WHERE id = ?';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' LIMIT 1';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? format_product_row($row) : null;
    }

    public static function findManyByIds(array $ids): array
    {
        if (!$ids) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM products WHERE id IN ($placeholders) AND is_active = 1"
        );
        $stmt->execute(array_values($ids));

        $products = [];
        foreach ($stmt->fetchAll() as $row) {
            $products[$row['id']] = format_product_row($row);
        }
        return $products;
    }

    public static function countActive(): int
    {
        return (int) Database::pdo()->query('SELECT COUNT(*) FROM products WHERE is_active = 1')->fetchColumn();
    }

    public static function upsert(array $data): array
    {
        $benefits = $data['benefits'] ?? [];
        if (is_string($benefits)) {
            $benefits = json_decode($benefits, true) ?: [];
        }

        $stmt = Database::pdo()->prepare(
            'INSERT INTO products (id, name, category, category_label, price, badge, image, short_desc, description, benefits, stock, is_active, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
                stock = VALUES(stock),
                is_active = VALUES(is_active),
                sort_order = VALUES(sort_order)'
        );

        $stmt->execute([
            $data['id'],
            $data['name'],
            $data['category'],
            $data['categoryLabel'] ?? $data['category_label'] ?? ucfirst($data['category']),
            (int) ($data['price'] ?? 0),
            $data['badge'] ?? null,
            $data['image'],
            $data['short'] ?? $data['short_desc'] ?? '',
            $data['desc'] ?? $data['description'] ?? '',
            json_encode(array_values($benefits), JSON_UNESCAPED_UNICODE),
            (int) ($data['stock'] ?? 100),
            isset($data['isActive']) ? (int) $data['isActive'] : (int) ($data['is_active'] ?? 1),
            (int) ($data['sortOrder'] ?? $data['sort_order'] ?? 0),
        ]);

        return self::findById($data['id'], false) ?? [];
    }

    public static function delete(string $id): bool
    {
        $stmt = Database::pdo()->prepare('UPDATE products SET is_active = 0 WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public static function decrementStock(string $id, int $qty, ?PDO $pdo = null): void
    {
        self::reserveStock($id, $qty, $pdo);
    }

    /** Décrémente le stock uniquement si la quantité est disponible (concurrence sûre). */
    public static function reserveStock(string $id, int $qty, ?PDO $pdo = null): void
    {
        if ($qty < 1) {
            throw new InvalidArgumentException('Quantité invalide');
        }
        $db = $pdo ?? Database::pdo();
        $stmt = $db->prepare(
            'UPDATE products SET stock = stock - ? WHERE id = ? AND is_active = 1 AND stock >= ?'
        );
        $stmt->execute([$qty, $id, $qty]);
        if ($stmt->rowCount() === 0) {
            throw new InvalidArgumentException('Stock insuffisant ou produit indisponible');
        }
    }

    public static function lockForUpdate(string $id, PDO $pdo): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT * FROM products WHERE id = ? AND is_active = 1 FOR UPDATE'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? format_product_row($row) : null;
    }
}
