-- Migration: 005
-- Description: warehouses/payments schema gaps and backfills for pre-1.0.6 installs
-- Created: 2026-10-14

CREATE TABLE IF NOT EXISTS `warehouses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(50) DEFAULT NULL,
    `location` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `manager_name` VARCHAR(100) DEFAULT NULL,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `warehouse_stock` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `warehouse_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `quantity` INT NOT NULL DEFAULT 0,
    `min_stock_level` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_warehouse_product` (`warehouse_id`, `product_id`),
    FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `payment_number` VARCHAR(50) NOT NULL UNIQUE,
    `type` ENUM('customer','supplier') NOT NULL DEFAULT 'customer',
    `entity_id` INT DEFAULT NULL,
    `entity_name` VARCHAR(255) DEFAULT NULL,
    `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `old_balance` DECIMAL(10,2) DEFAULT 0.00,
    `new_balance` DECIMAL(10,2) DEFAULT 0.00,
    `payment_date` DATE DEFAULT NULL,
    `payment_method` VARCHAR(50) DEFAULT NULL,
    `reference` VARCHAR(100) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `handled_by` VARCHAR(100) DEFAULT NULL,
    `user_id` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `default_warehouse_id` INT DEFAULT 1;
ALTER TABLE `invoices` ADD COLUMN IF NOT EXISTS `warehouse_id` INT DEFAULT NULL;

-- Older installs may carry a lean warehouses table (missing seed columns).
-- Ensure every column this migration touches exists before the seed below.
ALTER TABLE `warehouses`
    ADD COLUMN IF NOT EXISTS `code` VARCHAR(50) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `location` VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `phone` VARCHAR(20) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `manager_name` VARCHAR(100) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS `notes` TEXT DEFAULT NULL;

INSERT IGNORE INTO `warehouses` (`id`, `name`, `code`, `location`, `is_default`, `is_active`, `notes`)
VALUES (1, 'المخزن الرئيسي', 'WH-MAIN', 'المقر الرئيسي', 1, 1, 'المخزن الرئيسي الافتراضي للنظام');

-- Guarantee exactly one default warehouse without touching customer rows:
-- mark #1 default only when no other warehouse is already default.
UPDATE `warehouses` SET `is_default` = 1 WHERE `id` = 1 AND NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM `warehouses` WHERE `is_default` = 1) AS `dflt`);

-- Same guard for the stock backfill target columns.
ALTER TABLE `warehouse_stock`
    ADD COLUMN IF NOT EXISTS `quantity` INT NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `min_stock_level` INT DEFAULT NULL;

INSERT IGNORE INTO `warehouse_stock` (`warehouse_id`, `product_id`, `quantity`, `min_stock_level`)
SELECT 1, `id`, `stock_quantity`, `min_stock_level` FROM `products`;

UPDATE `users` SET `default_warehouse_id` = 1 WHERE `default_warehouse_id` IS NULL;
UPDATE `invoices` SET `warehouse_id` = 1 WHERE `warehouse_id` IS NULL;
