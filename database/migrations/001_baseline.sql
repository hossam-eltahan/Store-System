-- Migration: 001
-- Version: 1.0.0
-- Description: Baseline schema marker - no changes needed
-- This file marks the initial database schema version.
-- All tables already exist from the initial installation.

-- Create schema_versions table to track applied migrations
CREATE TABLE IF NOT EXISTS `schema_migrations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `migration_number` INT NOT NULL,
    `migration_file` VARCHAR(255) NOT NULL,
    `description` VARCHAR(500) DEFAULT NULL,
    `applied_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_migration` (`migration_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mark baseline as applied
INSERT IGNORE INTO `schema_migrations` (`migration_number`, `migration_file`, `description`)
VALUES (1, '001_baseline.sql', 'Initial schema baseline - v1.0.0');
