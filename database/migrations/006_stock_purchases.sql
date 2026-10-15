-- Migration: 006
-- Description: stock tracking per product (purchase/stock units + conversion),
--              purchase invoice draft/approve workflow, product stock movements
--              ledger, POS sale idempotency key, negative-stock prevention flag.
-- Created: 2026-10-15

-- 1. Per-product stock configuration
ALTER TABLE `products`
    ADD COLUMN IF NOT EXISTS `purchase_unit` VARCHAR(50) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `units_per_purchase` DECIMAL(12,4) NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS `track_stock` TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `is_direct_sale` TINYINT(1) NOT NULL DEFAULT 1;

-- 2. Purchase invoice approval workflow (existing rows stay approved = old behavior)
ALTER TABLE `invoices`
    ADD COLUMN IF NOT EXISTS `status` ENUM('draft','approved','cancelled') NOT NULL DEFAULT 'approved';

-- 3. Snapshot of units/conversion on each purchase line (history stays correct
--    even if the product settings change later)
ALTER TABLE `invoice_items`
    ADD COLUMN IF NOT EXISTS `purchase_unit` VARCHAR(50) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `conversion_factor` DECIMAL(12,4) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `stock_quantity` INT DEFAULT NULL;

-- 4. Product stock movements ledger (append-only; corrections are new rows)
CREATE TABLE IF NOT EXISTS `product_stock_movements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `warehouse_id` INT DEFAULT NULL,
    `movement_type` ENUM('opening','purchase','sale','customer_return','supplier_return','adjustment','transfer_in','transfer_out','reversal') NOT NULL DEFAULT 'adjustment',
    `qty_in` INT NOT NULL DEFAULT 0,
    `qty_out` INT NOT NULL DEFAULT 0,
    `balance_after` INT NOT NULL DEFAULT 0,
    `unit` VARCHAR(50) DEFAULT NULL,
    `reference_type` VARCHAR(30) DEFAULT NULL,
    `reference_id` INT DEFAULT NULL,
    `reason` VARCHAR(255) DEFAULT NULL,
    `user_id` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_psm_product` (`product_id`),
    INDEX `idx_psm_type` (`movement_type`),
    INDEX `idx_psm_created` (`created_at`),
    INDEX `idx_psm_ref` (`reference_type`, `reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. POS idempotency key (retries of the same checkout return the same order)
ALTER TABLE `orders`
    ADD COLUMN IF NOT EXISTS `client_key` VARCHAR(64) DEFAULT NULL,
    ADD UNIQUE KEY IF NOT EXISTS `unique_client_key` (`client_key`);

-- 6. Negative-stock prevention flag (0 = warn only, 1 = block oversell)
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
('prevent_negative_stock', '0');

-- 7. Align invoice_items with the invoice code: the code reads/writes
--    product_code/product_name everywhere (sale + purchase + print + ajax).
ALTER TABLE `invoice_items`
    ADD COLUMN IF NOT EXISTS `product_code` VARCHAR(50) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `product_name` VARCHAR(255) DEFAULT NULL;
