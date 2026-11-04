-- ============================================================
-- Migration: 004
-- Description: Automatic Barcode Support & Indexing
-- ============================================================

-- Ensure index on products.barcode exists if not already present
SET @exist := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND INDEX_NAME = 'idx_products_barcode');
SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE `products` ADD INDEX `idx_products_barcode` (`barcode`)', 'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO `schema_migrations` (`migration_number`, `migration_file`, `description`)
VALUES (4, '004_barcodes.sql', 'Automatic barcode support, search by barcode, and barcode scanning in invoices');
