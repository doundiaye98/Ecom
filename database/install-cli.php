<?php
declare(strict_types=1);

require __DIR__ . '/database/seed.php';

$host = '127.0.0.1';
$port = 3306;
$db = 'pure_essence_vita';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$db`");

    $schema = file_get_contents(__DIR__ . '/database/schema.sql');
    foreach (array_filter(array_map('trim', explode(';', $schema))) as $query) {
        if ($query !== '') {
            $pdo->exec($query);
        }
    }

    $count = seed_products($pdo, load_products_from_js(__DIR__ . '/js/products.js'));
    $orders = migrate_orders_json($pdo, __DIR__ . '/data/orders.json');

    $hash = password_hash('pev_admin_2026', PASSWORD_DEFAULT);
    $pdo->prepare(
        'INSERT INTO admin_users (username, password_hash) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)'
    )->execute(['admin', $hash]);

    $config = <<<'PHP'
<?php
return [
    'host' => '127.0.0.1',
    'port' => 3306,
    'database' => 'pure_essence_vita',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
];

PHP;

    file_put_contents(__DIR__ . '/config/database.php', $config);

    echo "OK: $count products imported, $orders orders migrated\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
