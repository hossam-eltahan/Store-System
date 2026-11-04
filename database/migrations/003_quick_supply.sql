-- ============================================================
-- Migration: 003
-- Description: quick supply movements (stock increase without a purchase invoice)
-- ============================================================
-- Additive only: creates a standalone movement log used by the quick-supply
-- feature. No existing table or column is modified.

CREATE TABLE IF NOT EXISTS `product_stock_movements` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `product_id` INT(11) NOT NULL,
    `warehouse_id` INT(11) NOT NULL,
    `movement_type` ENUM('supply','adjustment') NOT NULL DEFAULT 'supply',
    `quantity` INT(11) NOT NULL DEFAULT 0,
    `balance_before` INT(11) NOT NULL DEFAULT 0,
    `balance_after` INT(11) NOT NULL DEFAULT 0,
    `unit` VARCHAR(50) DEFAULT NULL,
    `unit_cost` DECIMAL(10,2) DEFAULT NULL,
    `supplier_id` INT(11) DEFAULT NULL,
    `reference_type` VARCHAR(30) DEFAULT NULL,
    `reference_id` INT(11) DEFAULT NULL,
    `reason` TEXT DEFAULT NULL,
    `user_id` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_psm_product` (`product_id`),
    KEY `idx_psm_warehouse` (`warehouse_id`),
    KEY `idx_psm_type_date` (`movement_type`, `created_at`),
    KEY `idx_psm_supplier` (`supplier_id`),
    KEY `idx_psm_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `schema_migrations` (`migration_number`, `migration_file`, `description`)
VALUES (3, '003_quick_supply.sql', 'Quick supply movements (stock increase without purchase invoice)');
