-- Migration 003: Manual service settlement (POS billing without timer/session)
-- Two service cards (PlayStation / Billiards); options and live rates come from
-- Settings inside the POS modal, so the products only act as entry points.

ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `is_service` TINYINT(1) NOT NULL DEFAULT 0 AFTER `has_modifiers`;

INSERT IGNORE INTO `categories` (`id`, `name`, `icon`, `color`, `sort_order`, `is_active`) VALUES
(19, 'خدمات وتسوية يدوية', '🧾', '#0ea5e9', 19, 1);

INSERT IGNORE INTO `products` (`category_id`, `code`, `name`, `price`, `has_modifiers`, `is_service`, `display_order`) VALUES
(19, 'SRV-PS', 'بلايستيشن', 0, 0, 1, 1),
(19, 'SRV-BL', 'بلياردو', 0, 0, 1, 2);
