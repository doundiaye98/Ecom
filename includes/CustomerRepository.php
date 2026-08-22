<?php

declare(strict_types=1);

final class CustomerRepository
{
    public static function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM customers WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? self::format($row) : null;
    }

    public static function findByPhone(string $phone): ?array
    {
        $norm = normalize_phone_sn($phone);
        if ($norm === '') {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM customers WHERE phone_norm = ? LIMIT 1');
        $stmt->execute([$norm]);
        $row = $stmt->fetch();
        return $row ? self::format($row, true) : null;
    }

    public static function register(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $phone = trim((string) ($input['phone'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        $address = trim((string) ($input['address'] ?? ''));
        $city = trim((string) ($input['city'] ?? ''));

        if ($name === '') {
            throw new InvalidArgumentException('Le nom est requis');
        }
        if (!is_valid_phone_sn($phone)) {
            throw new InvalidArgumentException('Numéro de téléphone sénégalais invalide');
        }
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Le mot de passe doit contenir au moins 8 caractères');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Email invalide');
        }
        if ($email !== '' && self::emailTaken($email)) {
            throw new InvalidArgumentException('Cet email est déjà utilisé');
        }

        $norm = normalize_phone_sn($phone);
        if (self::findByPhone($phone) !== null) {
            throw new InvalidArgumentException('Un compte existe déjà avec ce numéro');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = Database::pdo()->prepare(
            'INSERT INTO customers (phone_norm, phone_display, email, password_hash, name, address, city)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $norm,
            $phone,
            $email !== '' ? $email : null,
            $hash,
            $name,
            $address !== '' ? $address : null,
            $city !== '' ? $city : null,
        ]);

        $id = (int) Database::pdo()->lastInsertId();
        self::linkOrders($id, $norm);

        $customer = self::findById($id);
        if (!$customer) {
            throw new RuntimeException('Compte client créé mais introuvable');
        }
        return $customer;
    }

    public static function verifyPassword(array $customer, string $password): bool
    {
        $hash = (string) ($customer['passwordHash'] ?? '');
        return $hash !== '' && password_verify($password, $hash);
    }

    public static function updateProfile(int $customerId, array $input): array
    {
        $customer = self::findById($customerId);
        if (!$customer) {
            throw new InvalidArgumentException('Compte introuvable');
        }

        $name = trim((string) ($input['name'] ?? $customer['name']));
        $email = strtolower(trim((string) ($input['email'] ?? $customer['email'] ?? '')));
        $address = trim((string) ($input['address'] ?? $customer['address'] ?? ''));
        $city = trim((string) ($input['city'] ?? $customer['city'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        if ($name === '') {
            throw new InvalidArgumentException('Le nom est requis');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Email invalide');
        }
        if ($email !== '' && self::emailTaken($email, $customerId)) {
            throw new InvalidArgumentException('Cet email est déjà utilisé');
        }

        $fields = [
            'name' => $name,
            'email' => $email !== '' ? $email : null,
            'address' => $address !== '' ? $address : null,
            'city' => $city !== '' ? $city : null,
        ];

        if ($password !== '') {
            if (strlen($password) < 8) {
                throw new InvalidArgumentException('Le mot de passe doit contenir au moins 8 caractères');
            }
            $fields['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $sets = [];
        $values = [];
        foreach ($fields as $col => $val) {
            $sets[] = "$col = ?";
            $values[] = $val;
        }
        $values[] = $customerId;

        Database::pdo()->prepare(
            'UPDATE customers SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = ?'
        )->execute($values);

        return self::findById($customerId) ?? $customer;
    }

    public static function linkOrders(int $customerId, string $phoneNorm): int
    {
        $suffix = strlen($phoneNorm) >= 9 ? substr($phoneNorm, -9) : $phoneNorm;
        $stmt = Database::pdo()->prepare(
            'UPDATE orders
             SET customer_id = ?
             WHERE customer_id IS NULL
               AND REPLACE(REPLACE(REPLACE(customer_phone, " ", ""), "+", ""), "-", "") LIKE ?'
        );
        $stmt->execute([$customerId, '%' . $suffix]);
        return $stmt->rowCount();
    }

    private static function emailTaken(string $email, ?int $exceptId = null): bool
    {
        $sql = 'SELECT id FROM customers WHERE LOWER(email) = ?';
        $params = [strtolower($email)];
        if ($exceptId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $exceptId;
        }
        $sql .= ' LIMIT 1';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }

    private static function format(array $row, bool $withHash = false): array
    {
        $data = [
            'id' => (int) $row['id'],
            'phone' => (string) $row['phone_display'],
            'phoneNorm' => (string) $row['phone_norm'],
            'email' => (string) ($row['email'] ?? ''),
            'name' => (string) $row['name'],
            'address' => (string) ($row['address'] ?? ''),
            'city' => (string) ($row['city'] ?? ''),
            'createdAt' => gmdate('c', strtotime((string) $row['created_at'])),
        ];
        if ($withHash) {
            $data['passwordHash'] = (string) $row['password_hash'];
        }
        return $data;
    }

    public static function publicProfile(array $customer): array
    {
        return [
            'id' => $customer['id'],
            'phone' => $customer['phone'],
            'email' => $customer['email'],
            'name' => $customer['name'],
            'address' => $customer['address'],
            'city' => $customer['city'],
            'createdAt' => $customer['createdAt'],
        ];
    }
}
