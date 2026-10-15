-- Migration: 007
-- Description: align live DB with long-standing invoice/supplier code
--              (missing old_balance, supplier profile columns) + repair
--              corrupted display data (warehouse name, double-encoded unit).
-- Created: 2026-10-15

-- 1. invoices.old_balance is written by purchase.php / sale.php / edit_*.php
ALTER TABLE `invoices`
    ADD COLUMN IF NOT EXISTS `old_balance` DECIMAL(10,2) DEFAULT 0.00;

-- 2. suppliers profile columns used by suppliers add/edit/index/view
ALTER TABLE `suppliers`
    ADD COLUMN IF NOT EXISTS `company_name` VARCHAR(100) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `visit_days` TEXT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `expected_products` TEXT DEFAULT NULL;

-- Carry over the legacy `company` value where the new column is empty
UPDATE `suppliers` SET `company_name` = `company` WHERE (`company_name` IS NULL OR `company_name` = '') AND `company` IS NOT NULL AND `company` <> '';

-- 3. Default warehouse name was destroyed into literal '?' characters; reseed it
UPDATE `warehouses` SET `name` = 'المخزن الرئيسي' WHERE `id` = 1 AND `name` LIKE '%?%';

-- 4. products.unit double-encoded mojibake ('طلب' stored as latin1-bytes-as-utf8):
--    repair only the exact known-bad byte sequence, nothing else
UPDATE `products` SET `unit` = 'طلب' WHERE HEX(`unit`) = 'C38FC380E29498C3A4C38FC2BF';
