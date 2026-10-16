-- Migration: 016
-- Description: settings table modernization (key/value renamed to
--              setting_key/setting_value + description). All shipped
--              installs up to 1.0.7 use the old names; the app code
--              reads setting_key/setting_value exclusively.
--              Fully adaptive: works whether the table has the old names,
--              the new names, or a mix (e.g. partially migrated DBs).
-- Created: 2026-10-16

-- 1. Ensure the modern columns exist (no-op when already modern)
ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `setting_key` VARCHAR(50) DEFAULT NULL;
ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `setting_value` TEXT DEFAULT NULL;
ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `description` VARCHAR(255) DEFAULT NULL;

-- 2. Copy legacy values over (only when the legacy columns exist).
--    Fallback is DO (not SELECT) so no result set is ever left pending on
--    the shared PDO connection used by the migration runner.
SET @has_old_key = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings' AND COLUMN_NAME = 'key');
SET @sql_copy_key = IF(@has_old_key > 0, 'UPDATE `settings` SET `setting_key` = `key` WHERE `setting_key` IS NULL', 'DO 1');
PREPARE stmt_copy_key FROM @sql_copy_key;
EXECUTE stmt_copy_key;
DEALLOCATE PREPARE stmt_copy_key;

SET @has_old_val = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings' AND COLUMN_NAME = 'value');
SET @sql_copy_val = IF(@has_old_val > 0, 'UPDATE `settings` SET `setting_value` = `value` WHERE `setting_value` IS NULL', 'DO 1');
PREPARE stmt_copy_val FROM @sql_copy_val;
EXECUTE stmt_copy_val;
DEALLOCATE PREPARE stmt_copy_val;

-- NOTE: legacy `key`/`value` columns are intentionally left in place.
-- Dropping them cannot be done conditionally in plain SQL and they are
-- harmless; the app only reads the modern columns.
