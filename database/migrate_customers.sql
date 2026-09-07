-- Migration : comptes clients (installations existantes qui ont déjà la table orders)
-- Si orders n'existe pas (#1146) : installer d'abord database/schema.sql (ou install.php),
-- puis ignorer les étapes 2–4 (customer_id est déjà dans le schéma).
--
-- Compatible MySQL / MariaDB (WAMP) — pas de "IF NOT EXISTS" sur ADD COLUMN
--
-- Option A (recommandée) : php database/run_migrate_customers.php
-- Option B : exécuter chaque bloc ci-dessous dans phpMyAdmin (ignorer l'erreur si déjà appliqué)

CREATE TABLE IF NOT EXISTS customers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    phone_norm VARCHAR(20) NOT NULL,
    phone_display VARCHAR(50) NOT NULL,
    email VARCHAR(191) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL DEFAULT '',
    address TEXT DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_customer_phone (phone_norm),
    UNIQUE KEY uk_customer_email (email),
    INDEX idx_customer_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Étape 2 — colonne (erreur #1060 "Duplicate column" = déjà OK, passez à l'étape 3)
ALTER TABLE orders ADD COLUMN customer_id INT UNSIGNED DEFAULT NULL AFTER customer_notes;

-- Étape 3 — index (erreur #1061 "Duplicate key" = déjà OK)
ALTER TABLE orders ADD INDEX idx_customer (customer_id);

-- Étape 4 — clé étrangère (erreur #1826 / duplicate = déjà OK)
ALTER TABLE orders ADD CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL;
