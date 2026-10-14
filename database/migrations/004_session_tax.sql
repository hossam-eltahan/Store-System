-- Migration 004: Session bill tax (VAT + hall service on PlayStation/Billiards closes)
-- Tax is computed on the untaxed remainder only (time charge + due cafe amounts
-- minus tax already included inside linked cafe orders), so nothing is taxed twice.

ALTER TABLE `gaming_sessions` ADD COLUMN IF NOT EXISTS `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE `billiard_sessions` ADD COLUMN IF NOT EXISTS `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00;
