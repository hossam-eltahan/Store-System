-- Migration: 014
-- Description: cross-hall session transfer (gaming <-> tables <-> billiards).
--              Carried time balance (transferred_amount) settles with the target bill.
--              Source sessions close as 'transferred' (no payment, excluded from reports).
-- Created: 2026-10-16

ALTER TABLE `gaming_sessions`
    ADD COLUMN IF NOT EXISTS `transferred_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS `transfer_from_type` VARCHAR(20) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `transfer_from_id` INT DEFAULT NULL,
    MODIFY `status` ENUM('active','paused','completed','cancelled','transferred') NOT NULL DEFAULT 'active';

ALTER TABLE `billiard_sessions`
    ADD COLUMN IF NOT EXISTS `transferred_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS `transfer_from_type` VARCHAR(20) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `transfer_from_id` INT DEFAULT NULL,
    MODIFY `status` ENUM('active','paused','completed','cancelled','transferred') NOT NULL DEFAULT 'active';

ALTER TABLE `table_sessions`
    ADD COLUMN IF NOT EXISTS `transferred_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS `transfer_from_type` VARCHAR(20) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `transfer_from_id` INT DEFAULT NULL,
    MODIFY `status` ENUM('active','closed','cancelled','transferred') NOT NULL DEFAULT 'active';
