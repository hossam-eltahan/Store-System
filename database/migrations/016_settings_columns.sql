-- Migration: 016
-- Description: settings table modernization (key/value renamed to
--              setting_key/setting_value + description). All shipped
--              installs up to 1.0.7 use the old names; the app code
--              reads setting_key/setting_value exclusively.
-- Created: 2026-10-16

ALTER TABLE `settings` CHANGE COLUMN `key` `setting_key` VARCHAR(50) NOT NULL UNIQUE;
ALTER TABLE `settings` CHANGE COLUMN `value` `setting_value` TEXT DEFAULT NULL;
ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `description` VARCHAR(255) DEFAULT NULL;
