-- Migration 003: Manual service settlement (POS billing without timer/session)
-- Adds a flag for service products + a services category + 4 service products
-- priced from Settings (synced on every settings save).

ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `is_service` TINYINT(1) NOT NULL DEFAULT 0 AFTER `has_modifiers`;

INSERT IGNORE INTO `categories` (`id`, `name`, `icon`, `color`, `sort_order`, `is_active`) VALUES
(19, 'خدمات وتسوية يدوية', '🧾', '#0ea5e9', 19, 1);

INSERT IGNORE INTO `products` (`category_id`, `code`, `name`, `price`, `has_modifiers`, `is_service`, `display_order`) VALUES
(19, 'SRV-PS-SINGLE', 'ساعة بلايستيشن فردي', (SELECT `setting_value` FROM `settings` WHERE `setting_key` = 'ps_default_rate_single'), 0, 1, 1),
(19, 'SRV-PS-MULTI', 'ساعة بلايستيشن زوجي', (SELECT `setting_value` FROM `settings` WHERE `setting_key` = 'ps_default_rate_multi'), 0, 1, 2),
(19, 'SRV-BL-HOUR', 'ساعة بلياردو', (SELECT `setting_value` FROM `settings` WHERE `setting_key` = 'billiard_default_rate_hour'), 0, 1, 3),
(19, 'SRV-BL-GAME', 'جيم بلياردو', (SELECT `setting_value` FROM `settings` WHERE `setting_key` = 'billiard_default_rate_game'), 0, 1, 4);
