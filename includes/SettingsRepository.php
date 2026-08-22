<?php

declare(strict_types=1);

final class SettingsRepository
{
    private static array $cache = [];

    public static function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }
        $stmt = Database::pdo()->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        $value = $row ? (string) $row['setting_value'] : $default;
        self::$cache[$key] = $value;
        return $value;
    }

    public static function getInt(string $key, int $default): int
    {
        $value = self::get($key);
        return $value !== null ? (int) $value : $default;
    }

    public static function allPublic(): array
    {
        $app = app_config();
        return [
            'shippingFee' => self::getInt('shipping_fee', (int) $app['shipping_fee']),
            'freeShippingFrom' => self::getInt('free_shipping_from', (int) $app['free_shipping_from']),
            'siteName' => self::get('site_name', $app['name']),
            'whatsapp' => self::get('whatsapp', '221000000000'),
            'contactEmail' => self::get('contact_email', 'contact@pureessencevita.com'),
            'currency' => $app['currency'] ?? 'XOF',
        ];
    }

    public static function set(string $key, string $value): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $stmt->execute([$key, $value]);
        self::$cache[$key] = $value;
    }
}
