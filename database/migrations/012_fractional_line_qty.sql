-- Migration: 012
-- Description: allow fractional stock quantities on invoice lines
--              (ingredients like grams/milliliters).
-- Created: 2026-10-16

ALTER TABLE `invoice_items`
    MODIFY COLUMN `stock_quantity` DECIMAL(12,3) DEFAULT NULL;
