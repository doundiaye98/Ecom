-- Pure Essence Vita — Schéma MySQL
-- Charset: utf8mb4

CREATE TABLE IF NOT EXISTS products (
    id VARCHAR(80) NOT NULL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(50) NOT NULL,
    category_label VARCHAR(100) NOT NULL,
    price INT UNSIGNED NOT NULL DEFAULT 0,
    badge VARCHAR(50) DEFAULT NULL,
    image VARCHAR(500) NOT NULL,
    short_desc TEXT NOT NULL,
    description TEXT NOT NULL,
    benefits JSON NOT NULL,
    stock INT NOT NULL DEFAULT 100,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_active (is_active),
    INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS orders (
    id VARCHAR(40) NOT NULL PRIMARY KEY,
    tracking_number VARCHAR(20) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending_payment',
    subtotal INT UNSIGNED NOT NULL DEFAULT 0,
    shipping_fee INT UNSIGNED NOT NULL DEFAULT 0,
    total INT UNSIGNED NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'XOF',
    customer_name VARCHAR(255) NOT NULL,
    customer_phone VARCHAR(50) NOT NULL,
    customer_email VARCHAR(191) DEFAULT NULL,
    customer_address TEXT NOT NULL,
    customer_city VARCHAR(100) NOT NULL,
    customer_notes TEXT DEFAULT NULL,
    customer_id INT UNSIGNED DEFAULT NULL,
    payment_method VARCHAR(20) NOT NULL DEFAULT 'card',
    payment_paid TINYINT(1) NOT NULL DEFAULT 0,
    payment_reference VARCHAR(50) DEFAULT NULL,
    payment_paid_at DATETIME DEFAULT NULL,
    shipping_carrier VARCHAR(100) NOT NULL DEFAULT 'Native Vita Express',
    shipping_estimated_days TINYINT UNSIGNED NOT NULL DEFAULT 3,
    delivery_confirmed_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tracking (tracking_number),
    INDEX idx_payment_ref (payment_reference),
    INDEX idx_status (status),
    INDEX idx_phone (customer_phone),
    INDEX idx_email (customer_email),
    INDEX idx_customer (customer_id),
    INDEX idx_created (created_at),
    CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(40) NOT NULL,
    product_id VARCHAR(80) NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    price INT UNSIGNED NOT NULL,
    qty INT UNSIGNED NOT NULL DEFAULT 1,
    image VARCHAR(500) DEFAULT NULL,
    INDEX idx_order (order_id),
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_timeline (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(40) NOT NULL,
    status VARCHAR(30) NOT NULL,
    label VARCHAR(255) NOT NULL,
    note TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order_timeline (order_id),
    CONSTRAINT fk_timeline_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_messages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_read (is_read),
    INDEX idx_created_msg (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (setting_key, setting_value) VALUES
    ('shipping_fee', '2000'),
    ('free_shipping_from', '50000'),
    ('site_name', 'Native Vita'),
    ('whatsapp', '13478441197'),
    ('contact_email', 'contact@nativevita.com')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
