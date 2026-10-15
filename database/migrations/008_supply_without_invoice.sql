-- Migration: 008
-- Description: quick supply without purchase invoice (movement type 'supply'
--              + supplier attribution + unit cost for valuation).
-- Created: 2026-10-15

ALTER TABLE `product_stock_movements`
    MODIFY COLUMN `movement_type` ENUM('opening','purchase','supply','sale','customer_return','supplier_return','adjustment','transfer_in','transfer_out','reversal') NOT NULL DEFAULT 'adjustment',
    ADD COLUMN IF NOT EXISTS `supplier_id` INT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `unit_cost` DECIMAL(10,2) DEFAULT NULL;

CREATE INDEX IF NOT EXISTS `idx_psm_supplier` ON `product_stock_movements` (`supplier_id`);
