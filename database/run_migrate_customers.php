<?php
declare(strict_types=1);

/**
 * Migration comptes clients — idempotente (MySQL / MariaDB / WAMP)
 * Usage : php database/run_migrate_customers.php
 */

require __DIR__ . '/../includes/Database.php';
require __DIR__ . '/../includes/helpers.php';

if (!is_file(__DIR__ . '/../config/database.php')) {
    fwrite(STDERR, "Configuration absente. Lancez install.php d'abord.\n");
    exit(1);
}

Database::connect(db_config());
$pdo = Database::pdo();

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function index_exists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $stmt->execute([$table, $index]);
    return (int) $stmt->fetchColumn() > 0;
}

function foreign_key_exists(PDO $pdo, string $table, string $constraint): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?'
    );
    $stmt->execute([$table, $constraint, 'FOREIGN KEY']);
    return (int) $stmt->fetchColumn() > 0;
}

echo "Migration comptes clients…\n";

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS customers (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        phone_norm VARCHAR(20) NOT NULL,
        phone_display VARCHAR(50) NOT NULL,
        email VARCHAR(191) DEFAULT NULL,
        password_hash VARCHAR(255) NOT NULL,
        name VARCHAR(255) NOT NULL DEFAULT \'\',
        address TEXT DEFAULT NULL,
        city VARCHAR(100) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_customer_phone (phone_norm),
        UNIQUE KEY uk_customer_email (email),
        INDEX idx_customer_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);
echo "OK  : table customers\n";

if (!column_exists($pdo, 'orders', 'customer_id')) {
    $pdo->exec('ALTER TABLE orders ADD COLUMN customer_id INT UNSIGNED DEFAULT NULL AFTER customer_notes');
    echo "OK  : colonne orders.customer_id\n";
} else {
    echo "SKIP: colonne orders.customer_id (déjà présente)\n";
}

if (!index_exists($pdo, 'orders', 'idx_customer')) {
    $pdo->exec('ALTER TABLE orders ADD INDEX idx_customer (customer_id)');
    echo "OK  : index idx_customer\n";
} else {
    echo "SKIP: index idx_customer (déjà présent)\n";
}

if (!foreign_key_exists($pdo, 'orders', 'fk_orders_customer')) {
    $pdo->exec(
        'ALTER TABLE orders ADD CONSTRAINT fk_orders_customer
         FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL'
    );
    echo "OK  : clé étrangère fk_orders_customer\n";
} else {
    echo "SKIP: clé étrangère fk_orders_customer (déjà présente)\n";
}

echo "Migration terminée.\n";
