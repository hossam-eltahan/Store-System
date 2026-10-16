-- Migration: 013
-- Description: per-line capped returns for purchase and sale invoices.
--              Cumulative returned qty per source line can never exceed it.
-- Created: 2026-10-16

CREATE TABLE IF NOT EXISTS `invoice_returns` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `return_number` VARCHAR(30) NOT NULL UNIQUE,
    `invoice_kind` ENUM('purchase','pos','sale') NOT NULL,
    `invoice_id` INT NOT NULL,
    `return_date` DATE NOT NULL,
    `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `notes` VARCHAR(255) DEFAULT NULL,
    `return_key` VARCHAR(64) DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_return_key` (`return_key`),
    INDEX `idx_ret_inv` (`invoice_kind`, `invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `invoice_return_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `return_id` INT NOT NULL,
    `line_kind` ENUM('invoice','order') NOT NULL,
    `line_id` INT NOT NULL,
    `product_id` INT DEFAULT NULL,
    `ingredient_id` INT DEFAULT NULL,
    `item_name` VARCHAR(255) NOT NULL,
    `unit` VARCHAR(50) DEFAULT NULL,
    `quantity` DECIMAL(12,3) NOT NULL,
    `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `line_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (`return_id`) REFERENCES `invoice_returns`(`id`) ON DELETE CASCADE,
    INDEX `idx_ret_cap` (`line_kind`, `line_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
