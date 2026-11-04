-- Backup created: 2026-09-06 22:21:46
-- Database: store_management

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `activity_log`;
CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `user` varchar(100) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=154 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `backups`;
CREATE TABLE `backups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `size` bigint(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text DEFAULT NULL,
  `balance` decimal(10,2) DEFAULT 0.00 COMMENT 'Ï▒ÏÁ┘èÏ» Ïº┘äÏ╣┘à┘è┘ä - Ï│Ïº┘äÏ¿ ┘èÏ╣┘å┘è ┘àÏ»┘è┘ê┘å ┘ä┘åÏº',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`),
  KEY `idx_phone` (`phone`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `installment_payments`;
CREATE TABLE `installment_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `plan_id` int(11) NOT NULL,
  `payment_number` varchar(50) NOT NULL,
  `installment_number` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `paid_amount` decimal(10,2) DEFAULT 0.00,
  `due_date` date NOT NULL,
  `paid_date` date DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `handled_by` varchar(100) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `status` enum('pending','paid','overdue','partial') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `plan_id` (`plan_id`),
  KEY `idx_status` (`status`),
  KEY `idx_due_date` (`due_date`),
  CONSTRAINT `installment_payments_ibfk_1` FOREIGN KEY (`plan_id`) REFERENCES `installment_plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `installment_plans`;
CREATE TABLE `installment_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('customer','supplier') NOT NULL,
  `entity_id` int(11) NOT NULL,
  `entity_name` varchar(255) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL COMMENT 'NULL for manual installments from old balance',
  `total_amount` decimal(12,2) NOT NULL,
  `paid_amount` decimal(12,2) DEFAULT 0.00,
  `remaining_amount` decimal(12,2) NOT NULL,
  `number_of_installments` int(11) NOT NULL,
  `installment_amount` decimal(12,2) NOT NULL,
  `start_date` date NOT NULL,
  `status` enum('active','completed','cancelled') DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type`),
  KEY `idx_entity` (`entity_id`),
  KEY `idx_status` (`status`),
  KEY `idx_invoice` (`invoice_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `installments`;
CREATE TABLE `installments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `down_payment` decimal(10,2) NOT NULL DEFAULT 0.00,
  `remaining_amount` decimal(10,2) NOT NULL,
  `num_installments` int(11) NOT NULL,
  `installment_amount` decimal(10,2) NOT NULL,
  `status` enum('active','completed','cancelled') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `invoice_id` (`invoice_id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `installments_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `installments_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `invoice_items`;
CREATE TABLE `invoice_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_code` varchar(50) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `unit` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL COMMENT 'Ïº┘äÏ│Ï╣Ï▒ ┘ê┘éÏ¬ Ïº┘äÏ¿┘èÏ╣',
  `total` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_invoice_id` (`invoice_id`),
  KEY `idx_product_id` (`product_id`),
  CONSTRAINT `invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invoice_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `invoices`;
CREATE TABLE `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) NOT NULL,
  `type` enum('sale','purchase') NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL COMMENT 'ÏºÏ│┘à Ïº┘äÏ╣┘à┘è┘ä ÏÑÏ░Ïº ┘âÏº┘å Ï║┘èÏ▒ ┘àÏ│Ï¼┘ä',
  `customer_phone` varchar(20) DEFAULT NULL COMMENT 'Ï▒┘é┘à Ïº┘äÏ╣┘à┘è┘ä ÏÑÏ░Ïº ┘âÏº┘å Ï║┘èÏ▒ ┘àÏ│Ï¼┘ä',
  `supplier_id` int(11) DEFAULT NULL,
  `date` date NOT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(10,2) DEFAULT 0.00,
  `paid_amount` decimal(10,2) DEFAULT 0.00,
  `remaining_amount` decimal(10,2) DEFAULT 0.00,
  `old_balance` decimal(10,2) DEFAULT 0.00,
  `payment_method` varchar(50) DEFAULT NULL COMMENT '┘âÏºÏ┤Ïî Ïú┘éÏ│ÏºÏÀÏî Ïº┘åÏ│Ï¬ÏºÏ¿Ïº┘èÏî ┘ü┘êÏ»Ïº┘ü┘ê┘å ┘âÏºÏ┤Ïî ┘ü┘èÏ▓Ïº',
  `payment_status` enum('paid','partial','unpaid') DEFAULT 'unpaid',
  `handled_by` varchar(100) NOT NULL COMMENT '┘à┘å ┘éÏº┘à Ï¿Ïº┘äÏ¿┘èÏ╣/Ïº┘äÏºÏ│Ï¬┘äÏº┘à',
  `user_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `warehouse_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `customer_id` (`customer_id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `idx_invoice_number` (`invoice_number`),
  KEY `idx_type` (`type`),
  KEY `idx_date` (`date`),
  KEY `idx_payment_status` (`payment_status`),
  KEY `fk_invoice_warehouse` (`warehouse_id`),
  CONSTRAINT `fk_invoice_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_number` varchar(50) NOT NULL,
  `type` enum('customer','supplier') NOT NULL,
  `entity_id` int(11) NOT NULL,
  `entity_name` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `old_balance` decimal(10,2) DEFAULT 0.00,
  `new_balance` decimal(10,2) DEFAULT 0.00,
  `payment_date` date NOT NULL,
  `payment_method` varchar(50) DEFAULT 'كاش',
  `reference` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `handled_by` varchar(100) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `unit` varchar(50) NOT NULL DEFAULT '┘éÏÀÏ╣Ï®',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `wholesale_price` decimal(10,2) DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL COMMENT 'Ï│Ï╣Ï▒ Ïº┘äÏ┤Ï▒ÏºÏí ┘äÏ¡Ï│ÏºÏ¿ Ïº┘äÏúÏ▒Ï¿ÏºÏ¡',
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `min_stock_level` int(11) NOT NULL DEFAULT 5,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_code` (`code`),
  KEY `idx_name` (`name`),
  KEY `idx_stock` (`stock_quantity`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `return_items`;
CREATE TABLE `return_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `return_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_code` varchar(50) DEFAULT NULL,
  `product_name` varchar(200) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_return` (`return_id`),
  KEY `idx_product` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `returns`;
CREATE TABLE `returns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `return_number` varchar(20) NOT NULL,
  `type` enum('customer','supplier') DEFAULT 'customer',
  `original_invoice_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `supplier_name` varchar(255) DEFAULT NULL,
  `return_date` date NOT NULL,
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `old_balance` decimal(10,2) DEFAULT 0.00,
  `deducted_from_balance` decimal(10,2) DEFAULT 0.00,
  `cash_refund` decimal(10,2) DEFAULT 0.00,
  `refund_method` enum('deducted','cash','mixed') DEFAULT 'cash',
  `handled_by` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  `warehouse_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_invoice` (`original_invoice_id`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_date` (`return_date`),
  KEY `fk_return_warehouse` (`warehouse_id`),
  CONSTRAINT `fk_return_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `stock_transfer_items`;
CREATE TABLE `stock_transfer_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transfer_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transfer_id` (`transfer_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `stock_transfer_items_ibfk_1` FOREIGN KEY (`transfer_id`) REFERENCES `stock_transfers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_transfer_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `stock_transfers`;
CREATE TABLE `stock_transfers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transfer_number` varchar(50) NOT NULL,
  `from_warehouse_id` int(11) NOT NULL,
  `to_warehouse_id` int(11) NOT NULL,
  `transfer_date` date NOT NULL,
  `status` enum('pending','completed','cancelled') NOT NULL DEFAULT 'completed',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `transfer_number` (`transfer_number`),
  KEY `from_warehouse_id` (`from_warehouse_id`),
  KEY `to_warehouse_id` (`to_warehouse_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `stock_transfers_ibfk_1` FOREIGN KEY (`from_warehouse_id`) REFERENCES `warehouses` (`id`),
  CONSTRAINT `stock_transfers_ibfk_2` FOREIGN KEY (`to_warehouse_id`) REFERENCES `warehouses` (`id`),
  CONSTRAINT `stock_transfers_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `visit_days` varchar(200) DEFAULT NULL COMMENT 'JSON array of weekdays',
  `expected_products` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `balance` decimal(10,2) DEFAULT 0.00 COMMENT 'Ïº┘ä┘àÏ¿┘äÏ║ Ïº┘ä┘àÏ│Ï¬Ï¡┘é ┘ä┘ä┘à┘êÏ▒Ï»',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `user_permissions`;
CREATE TABLE `user_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `permission_key` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_perm` (`user_id`,`permission_key`),
  CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=122 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `user_sessions`;
CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `login_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `logout_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `role` enum('admin','employee') NOT NULL DEFAULT 'employee',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `default_warehouse_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `fk_user_warehouse` (`default_warehouse_id`),
  CONSTRAINT `fk_user_warehouse` FOREIGN KEY (`default_warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `warehouse_stock`;
CREATE TABLE `warehouse_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `warehouse_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `min_stock_level` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_warehouse_product` (`warehouse_id`,`product_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `warehouse_stock_ibfk_1` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `warehouse_stock_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `warehouses`;
CREATE TABLE `warehouses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `manager_name` varchar(100) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


SET FOREIGN_KEY_CHECKS=1;


-- ========================================================
-- Schema Migrations Tracking Table
-- ========================================================
CREATE TABLE IF NOT EXISTS `schema_migrations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `migration_number` INT NOT NULL,
    `migration_file` VARCHAR(255) NOT NULL,
    `description` VARCHAR(500) DEFAULT NULL,
    `applied_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_migration` (`migration_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `schema_migrations` (`migration_number`, `migration_file`, `description`) 
VALUES (1, '001_baseline.sql', 'الإصدار الأساسي لقاعدة البيانات v1.0.0');

-- ========================================================
-- POS module (002_pos.sql) - additive only
-- ========================================================
ALTER TABLE `invoices`
    ADD COLUMN `source` ENUM('manual','pos') NOT NULL DEFAULT 'manual',
    ADD COLUMN `client_key` VARCHAR(64) DEFAULT NULL,
    ADD COLUMN `cash_received` DECIMAL(10,2) DEFAULT NULL,
    ADD UNIQUE KEY `uq_invoices_client_key` (`client_key`),
    ADD INDEX `idx_invoices_source_date` (`source`, `date`);

ALTER TABLE `products`
    ADD COLUMN `barcode` VARCHAR(50) DEFAULT NULL,
    ADD COLUMN `is_service` TINYINT(1) NOT NULL DEFAULT 0,
    ADD INDEX `idx_products_barcode` (`barcode`);

CREATE TABLE IF NOT EXISTS `pos_holds` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `hold_number` VARCHAR(30) NOT NULL,
    `warehouse_id` INT DEFAULT NULL,
    `customer_id` INT DEFAULT NULL,
    `customer_name` VARCHAR(255) DEFAULT NULL,
    `customer_phone` VARCHAR(20) DEFAULT NULL,
    `cart_json` LONGTEXT NOT NULL,
    `note` VARCHAR(255) DEFAULT NULL,
    `user_id` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_pos_holds_number` (`hold_number`),
    KEY `idx_pos_holds_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `schema_migrations` (`migration_number`, `migration_file`, `description`)
VALUES (2, '002_pos.sql', 'POS module: invoice source/client_key/cash_received, product barcode/is_service, held carts');

INSERT INTO `warehouses` (`id`, `name`, `code`, `location`, `phone`, `manager_name`, `is_default`, `is_active`, `notes`, `created_at`, `updated_at`) VALUES (1, 'Main Warehouse', 'WH-MAIN', NULL, NULL, NULL, 1, 1, NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);
INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `phone`, `email`, `avatar`, `role`, `is_active`, `last_login`, `last_login_ip`, `created_by`, `created_at`, `updated_at`, `default_warehouse_id`) VALUES (1, 'admin', '$2y$10$FVxC20yF5AI837GbP73V..3aJE./h3CDDanGkhFnKOWi0zP00/Bt2', 'System Administrator', NULL, NULL, NULL, 'admin', 1, NULL, NULL, NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 1);
