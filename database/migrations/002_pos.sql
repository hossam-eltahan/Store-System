-- ============================================================
-- Migration: 002
-- Description: POS module — additive columns + held carts
-- ============================================================
-- This migration is additive only: no existing column is modified and
-- every new column has a default/NULL so all legacy code keeps working.

ALTER TABLE `invoices`
    ADD COLUMN `source` ENUM('manual','pos') NOT NULL DEFAULT 'manual',
    ADD COLUMN `client_key` VARCHAR(64) DEFAULT NULL,
    ADD COLUMN `cash_received` DECIMAL(10,2) DEFAULT NULL,
    ADD UNIQUE KEY `uq_invoices_client_key` (`client_key`),
    ADD INDEX `idx_invoices_source_date` (`source`, `date`);

ALTER TABLE `products`
    ADD COLUMN `barcode` VARCHAR(50) DEFAULT NULL,
    ADD COLUMN `is_service` TINYINT(1) NOT NULL DEFAULT 0,
    ADD INDEX `idx_products_barcode` (`barcode`);

CREATE TABLE IF NOT EXISTS `pos_holds` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `hold_number` VARCHAR(30) NOT NULL,
    `warehouse_id` INT DEFAULT NULL,
    `customer_id` INT DEFAULT NULL,
    `customer_name` VARCHAR(255) DEFAULT NULL,
    `customer_phone` VARCHAR(20) DEFAULT NULL,
    `cart_json` LONGTEXT NOT NULL,
    `note` VARCHAR(255) DEFAULT NULL,
    `user_id` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_pos_holds_number` (`hold_number`),
    KEY `idx_pos_holds_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `schema_migrations` (`migration_number`, `migration_file`, `description`)
VALUES (2, '002_pos.sql', 'POS module: invoice source/client_key/cash_received, product barcode/is_service, held carts');
