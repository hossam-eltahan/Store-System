-- Migration: 011
-- Description: per-product modifier (sauce/extra) assignment, so each product
--              can prompt for its own sauce list in POS like sizes do.
-- Created: 2026-10-16

CREATE TABLE IF NOT EXISTS `product_modifier_links` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `modifier_id` INT NOT NULL,
    `display_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_product_modifier` (`product_id`, `modifier_id`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`modifier_id`) REFERENCES `product_modifiers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
