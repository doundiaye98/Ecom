-- Migration optionnelle pour installations existantes (concurrence & performance)
-- Exécuter une fois dans phpMyAdmin ou : mysql -u root pure_essence_vita < database/migrate_indexes.sql

ALTER TABLE orders ADD INDEX idx_payment_ref (payment_reference);
