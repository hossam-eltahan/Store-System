-- =====================================================
-- Store-System Fresh Install (cafe_management)
-- Generated from cafe_schema + seeds. Do NOT edit by hand.
-- Default login: admin / admin123 (change immediately!)
-- =====================================================

-- ---------- database/cafe_schema.sql ----------
-- ==========================================================
-- كافيه وصالة ألعاب "عروسة النيل" - هيكل قاعدة البيانات المتكامل
-- Cafe, PlayStation & Billiards Management System Schema
-- ==========================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. جدول المستخدمين (Users)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `avatar` VARCHAR(255) DEFAULT NULL,
    `role` ENUM('admin','cashier','barista','employee') NOT NULL DEFAULT 'cashier',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_login` DATETIME DEFAULT NULL,
    `last_login_ip` VARCHAR(45) DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `default_warehouse_id` INT DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. صلاحيات المستخدمين (User Permissions)
CREATE TABLE IF NOT EXISTS `user_permissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `permission_key` VARCHAR(100) NOT NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_user_permission` (`user_id`, `permission_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. جلسات المستخدمين (User Sessions)
CREATE TABLE IF NOT EXISTS `user_sessions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `session_token` VARCHAR(64) NOT NULL UNIQUE,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME NOT NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. إعدادات النظام (Settings)
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(50) NOT NULL UNIQUE,
    `value` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. سجل الأنشطة والرقابة (Activity Log)
CREATE TABLE IF NOT EXISTS `activity_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. العملاء (Customers)
CREATE TABLE IF NOT EXISTS `customers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `notes` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. الموردين (Suppliers)
CREATE TABLE IF NOT EXISTS `suppliers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `company` VARCHAR(100) DEFAULT NULL,
    `company_name` VARCHAR(100) DEFAULT NULL,
    `visit_days` TEXT DEFAULT NULL,
    `expected_products` TEXT DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `notes` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. تصنيفات المصروفات (Expense Categories)
CREATE TABLE IF NOT EXISTS `expense_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. المصروفات (Expenses)
CREATE TABLE IF NOT EXISTS `expenses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `date` DATE NOT NULL,
    `description` TEXT DEFAULT NULL,
    `paid_to` VARCHAR(100) DEFAULT NULL,
    `shift_id` INT DEFAULT NULL,
    `user_id` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `expense_categories`(`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. تصنيفات المنيو (Categories)
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `icon` VARCHAR(50) DEFAULT '☕',
    `color` VARCHAR(20) DEFAULT '#6F4E37',
    `parent_id` INT UNSIGNED DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. منتجات المنيو (Products)
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT UNSIGNED DEFAULT NULL,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(255) NOT NULL,
    `unit` VARCHAR(50) NOT NULL DEFAULT 'طلب',
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `cost_price` DECIMAL(10,2) DEFAULT 0.00,
    `stock_quantity` INT NOT NULL DEFAULT 999,
    `min_stock_level` INT NOT NULL DEFAULT 5,
    `purchase_unit` VARCHAR(50) DEFAULT NULL,
    `units_per_purchase` DECIMAL(12,4) NOT NULL DEFAULT 1,
    `track_stock` TINYINT(1) NOT NULL DEFAULT 0,
    `is_direct_sale` TINYINT(1) NOT NULL DEFAULT 1,
    `is_available` TINYINT(1) NOT NULL DEFAULT 1,
    `has_variants` TINYINT(1) NOT NULL DEFAULT 0,
    `has_modifiers` TINYINT(1) NOT NULL DEFAULT 0,
    `is_service` TINYINT(1) NOT NULL DEFAULT 0,
    `preparation_time_min` INT DEFAULT 5,
    `display_order` INT NOT NULL DEFAULT 0,
    `calories` INT DEFAULT NULL,
    `barcode` VARCHAR(50) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `image` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. أحجام وخيارات المنتج (Product Variants)
CREATE TABLE IF NOT EXISTS `product_variants` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `variant_name` VARCHAR(100) NOT NULL,
    `variant_group` VARCHAR(50) DEFAULT 'size',
    `price_adjustment` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `final_price` DECIMAL(10,2) NOT NULL,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `is_available` TINYINT(1) NOT NULL DEFAULT 1,
    `display_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. الإضافات والصوصات (Product Modifiers)
CREATE TABLE IF NOT EXISTS `product_modifiers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `modifier_group` VARCHAR(50) DEFAULT 'extra',
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `is_available` TINYINT(1) NOT NULL DEFAULT 1,
    `applies_to_category_id` INT UNSIGNED DEFAULT NULL,
    `display_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. طاولات الجلوس العادية (Cafe Tables)
CREATE TABLE IF NOT EXISTS `cafe_tables` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `table_number` VARCHAR(20) NOT NULL UNIQUE,
    `display_name` VARCHAR(50) NOT NULL,
    `section` VARCHAR(50) DEFAULT 'main',
    `capacity` INT DEFAULT 4,
    `status` ENUM('available','occupied','reserved','maintenance') NOT NULL DEFAULT 'available',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `display_order` INT NOT NULL DEFAULT 0,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. جلسات الطاولات (Table Sessions)
CREATE TABLE IF NOT EXISTS `table_sessions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `table_id` INT NOT NULL,
    `opened_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `closed_at` DATETIME DEFAULT NULL,
    `status` ENUM('active','closed','cancelled') NOT NULL DEFAULT 'active',
    `customer_name` VARCHAR(100) DEFAULT NULL,
    `guest_count` INT DEFAULT 1,
    `notes` TEXT DEFAULT NULL,
    `opened_by` INT DEFAULT NULL,
    `closed_by` INT DEFAULT NULL,
    `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`table_id`) REFERENCES `cafe_tables`(`id`),
    FOREIGN KEY (`opened_by`) REFERENCES `users`(`id`),
    FOREIGN KEY (`closed_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. أجهزة البلايستيشن (Gaming Devices)
CREATE TABLE IF NOT EXISTS `gaming_devices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `device_number` VARCHAR(20) NOT NULL UNIQUE,
    `display_name` VARCHAR(50) NOT NULL,
    `device_type` ENUM('ps4','ps5') NOT NULL DEFAULT 'ps4',
    `room_type` ENUM('normal','vip') NOT NULL DEFAULT 'normal',
    `controller_count` INT NOT NULL DEFAULT 2,
    `rate_single` DECIMAL(10,2) NOT NULL DEFAULT 30.00,
    `rate_multi` DECIMAL(10,2) NOT NULL DEFAULT 40.00,
    `rate_vip` DECIMAL(10,2) DEFAULT NULL,
    `min_billable_minutes` INT NOT NULL DEFAULT 15,
    `billing_increment_minutes` INT NOT NULL DEFAULT 1,
    `rounding_policy` ENUM('up','down','nearest','exact') NOT NULL DEFAULT 'nearest',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `display_order` INT NOT NULL DEFAULT 0,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. جلسات البلايستيشن بالتايمر (Gaming Sessions)
CREATE TABLE IF NOT EXISTS `gaming_sessions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `device_id` INT NOT NULL,
    `session_type` ENUM('single','multi','vip') NOT NULL DEFAULT 'single',
    `started_at` DATETIME NOT NULL,
    `ended_at` DATETIME DEFAULT NULL,
    `paused_at` DATETIME DEFAULT NULL,
    `total_paused_seconds` INT NOT NULL DEFAULT 0,
    `time_mode` ENUM('open','countdown') NOT NULL DEFAULT 'open',
    `preset_minutes` INT DEFAULT NULL,
    `applied_rate` DECIMAL(10,2) NOT NULL,
    `billable_minutes` DECIMAL(10,2) DEFAULT NULL,
    `gaming_charge` DECIMAL(10,2) DEFAULT NULL,
    `cafe_orders_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(10,2) DEFAULT NULL,
    `paid_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('active','paused','completed','cancelled') NOT NULL DEFAULT 'active',
    `opened_by` INT DEFAULT NULL,
    `closed_by` INT DEFAULT NULL,
    `customer_name` VARCHAR(100) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`device_id`) REFERENCES `gaming_devices`(`id`),
    FOREIGN KEY (`opened_by`) REFERENCES `users`(`id`),
    FOREIGN KEY (`closed_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 18b. جدول تتبع إشعارات انتهاء الجلسات (Session Timer Notifications Tracking)
CREATE TABLE IF NOT EXISTS `session_timer_notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `entity_type` ENUM('gaming', 'billiards') NOT NULL,
    `session_id` INT NOT NULL,
    `notified_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_session` (`entity_type`, `session_id`),
    INDEX `idx_notified_at` (`notified_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 18. فترات الإيقاف المؤقت للبلايستيشن (Gaming Session Pauses)
CREATE TABLE IF NOT EXISTS `gaming_session_pauses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `session_id` INT NOT NULL,
    `paused_at` DATETIME NOT NULL,
    `resumed_at` DATETIME DEFAULT NULL,
    `duration_seconds` INT DEFAULT NULL,
    FOREIGN KEY (`session_id`) REFERENCES `gaming_sessions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 19. طاولات البلياردو (Billiard Tables)
CREATE TABLE IF NOT EXISTS `billiard_tables` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `table_number` VARCHAR(20) NOT NULL UNIQUE,
    `display_name` VARCHAR(50) NOT NULL,
    `table_type` ENUM('pool','snooker','ping_pong') NOT NULL DEFAULT 'pool',
    `billing_mode` ENUM('hourly','per_game') NOT NULL DEFAULT 'hourly',
    `rate_per_hour` DECIMAL(10,2) DEFAULT 40.00,
    `rate_per_game` DECIMAL(10,2) DEFAULT 10.00,
    `min_billable_minutes` INT DEFAULT 15,
    `billing_increment_minutes` INT NOT NULL DEFAULT 1,
    `rounding_policy` ENUM('up','down','nearest','exact') NOT NULL DEFAULT 'nearest',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `display_order` INT NOT NULL DEFAULT 0,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 20. جلسات البلياردو (Billiard Sessions)
CREATE TABLE IF NOT EXISTS `billiard_sessions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `table_id` INT NOT NULL,
    `billing_mode` ENUM('hourly','per_game') NOT NULL,
    `started_at` DATETIME NOT NULL,
    `ended_at` DATETIME DEFAULT NULL,
    `paused_at` DATETIME DEFAULT NULL,
    `total_paused_seconds` INT NOT NULL DEFAULT 0,
    `time_mode` ENUM('open','countdown') NOT NULL DEFAULT 'open',
    `preset_minutes` INT DEFAULT NULL,
    `game_count` INT NOT NULL DEFAULT 0,
    `applied_rate` DECIMAL(10,2) NOT NULL,
    `billable_minutes` DECIMAL(10,2) DEFAULT NULL,
    `gaming_charge` DECIMAL(10,2) DEFAULT NULL,
    `cafe_orders_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(10,2) DEFAULT NULL,
    `paid_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('active','paused','completed','cancelled') NOT NULL DEFAULT 'active',
    `opened_by` INT DEFAULT NULL,
    `closed_by` INT DEFAULT NULL,
    `customer_name` VARCHAR(100) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`table_id`) REFERENCES `billiard_tables`(`id`),
    FOREIGN KEY (`opened_by`) REFERENCES `users`(`id`),
    FOREIGN KEY (`closed_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 21. فترات الإيقاف المؤقت للبلياردو (Billiard Session Pauses)
CREATE TABLE IF NOT EXISTS `billiard_session_pauses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `session_id` INT NOT NULL,
    `paused_at` DATETIME NOT NULL,
    `resumed_at` DATETIME DEFAULT NULL,
    `duration_seconds` INT DEFAULT NULL,
    FOREIGN KEY (`session_id`) REFERENCES `billiard_sessions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 22. الورديات / الشيفتات (Shifts)
CREATE TABLE IF NOT EXISTS `shifts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `shift_number` VARCHAR(30) NOT NULL UNIQUE,
    `opened_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `closed_at` DATETIME DEFAULT NULL,
    `status` ENUM('open','closed') NOT NULL DEFAULT 'open',
    `opening_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `expected_cash` DECIMAL(10,2) DEFAULT NULL,
    `actual_cash` DECIMAL(10,2) DEFAULT NULL,
    `cash_variance` DECIMAL(10,2) DEFAULT NULL,
    `variance_reason` TEXT DEFAULT NULL,
    `total_sales_cash` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_sales_card` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_sales_wallet` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_gaming_revenue` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_billiard_revenue` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_refunds` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_expenses` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_cash_in` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_cash_out` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `closing_notes` TEXT DEFAULT NULL,
    `closed_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
    FOREIGN KEY (`closed_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 23. حركات النقدية خلال الشيفت (Cash Movements)
CREATE TABLE IF NOT EXISTS `cash_movements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `shift_id` INT NOT NULL,
    `movement_type` ENUM('cash_in','cash_out','sale','refund','expense') NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `payment_method` VARCHAR(30) NOT NULL DEFAULT 'cash',
    `description` TEXT DEFAULT NULL,
    `reference_type` VARCHAR(50) DEFAULT NULL,
    `reference_id` INT DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`shift_id`) REFERENCES `shifts`(`id`),
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 24. الطلبات الموحدة (Orders)
CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_number` VARCHAR(30) NOT NULL UNIQUE,
    `order_type` ENUM('takeaway','dine_in','gaming_ps','gaming_billiard') NOT NULL DEFAULT 'takeaway',
    `table_session_id` INT DEFAULT NULL,
    `gaming_session_id` INT DEFAULT NULL,
    `billiard_session_id` INT DEFAULT NULL,
    `status` ENUM('pending','preparing','ready','served','completed','cancelled') NOT NULL DEFAULT 'pending',
    `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `discount_type` ENUM('fixed','percentage') NOT NULL DEFAULT 'fixed',
    `discount_value` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `paid_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `payment_status` ENUM('unpaid','partial','paid','refunded') NOT NULL DEFAULT 'unpaid',
    `payment_method` VARCHAR(30) DEFAULT NULL,
    `customer_name` VARCHAR(100) DEFAULT NULL,
    `customer_phone` VARCHAR(20) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `shift_id` INT DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `cancelled_by` INT DEFAULT NULL,
    `cancel_reason` TEXT DEFAULT NULL,
    `client_key` VARCHAR(64) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_client_key` (`client_key`),
    FOREIGN KEY (`table_session_id`) REFERENCES `table_sessions`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`gaming_session_id`) REFERENCES `gaming_sessions`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`billiard_session_id`) REFERENCES `billiard_sessions`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`shift_id`) REFERENCES `shifts`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`),
    FOREIGN KEY (`cancelled_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 25. أصناف الطلب (Order Items)
CREATE TABLE IF NOT EXISTS `order_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `variant_id` INT DEFAULT NULL,
    `product_name` VARCHAR(200) NOT NULL,
    `variant_name` VARCHAR(100) DEFAULT NULL,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `modifiers_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `line_total` DECIMAL(10,2) NOT NULL,
    `item_notes` TEXT DEFAULT NULL,
    `preparation_status` ENUM('pending','preparing','ready','served','cancelled') NOT NULL DEFAULT 'pending',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`),
    FOREIGN KEY (`variant_id`) REFERENCES `product_variants`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 26. إضافات صنف الطلب (Order Item Modifiers)
CREATE TABLE IF NOT EXISTS `order_item_modifiers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_item_id` INT NOT NULL,
    `modifier_id` INT NOT NULL,
    `modifier_name` VARCHAR(100) NOT NULL,
    `modifier_price` DECIMAL(10,2) NOT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    FOREIGN KEY (`order_item_id`) REFERENCES `order_items`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`modifier_id`) REFERENCES `product_modifiers`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 27. سجل المدفوعات التفصيلي (Payment Records)
CREATE TABLE IF NOT EXISTS `payment_records` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `payment_number` VARCHAR(30) NOT NULL UNIQUE,
    `source_type` ENUM('order','gaming_session','billiard_session','table_session') NOT NULL,
    `source_id` INT NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `payment_method` ENUM('cash','card','vodafone_cash','instapay','wallet','other') NOT NULL DEFAULT 'cash',
    `payment_type` ENUM('payment','refund') NOT NULL DEFAULT 'payment',
    `notes` TEXT DEFAULT NULL,
    `shift_id` INT DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`shift_id`) REFERENCES `shifts`(`id`),
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 28. الخامات والمكونات الأساسية (Ingredients)
CREATE TABLE IF NOT EXISTS `ingredients` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `sku` VARCHAR(50) DEFAULT NULL UNIQUE,
    `base_unit` ENUM('g','kg','ml','l','piece') NOT NULL DEFAULT 'piece',
    `purchase_unit` VARCHAR(30) DEFAULT NULL,
    `conversion_factor` DECIMAL(10,4) NOT NULL DEFAULT 1.0000,
    `current_stock` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    `min_stock_level` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    `last_purchase_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `weighted_avg_cost` DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
    `category` VARCHAR(50) DEFAULT NULL,
    `supplier_id` INT DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 29. الوصفات والمقادير (Recipes)
CREATE TABLE IF NOT EXISTS `recipes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `variant_id` INT DEFAULT NULL,
    `version` INT NOT NULL DEFAULT 1,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `effective_date` DATE DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`variant_id`) REFERENCES `product_variants`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 30. مكونات الوصفة (Recipe Items)
CREATE TABLE IF NOT EXISTS `recipe_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `recipe_id` INT NOT NULL,
    `ingredient_id` INT NOT NULL,
    `quantity` DECIMAL(10,4) NOT NULL,
    `unit` ENUM('g','kg','ml','l','piece') NOT NULL,
    `waste_percentage` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `notes` TEXT DEFAULT NULL,
    FOREIGN KEY (`recipe_id`) REFERENCES `recipes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 31. حركات مخزون الخامات (Ingredient Stock Movements)
CREATE TABLE IF NOT EXISTS `ingredient_stock_movements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ingredient_id` INT NOT NULL,
    `movement_type` ENUM('purchase','consumption','adjustment','waste','return','opening') NOT NULL,
    `quantity` DECIMAL(10,3) NOT NULL,
    `unit_cost` DECIMAL(10,4) DEFAULT NULL,
    `reference_type` VARCHAR(50) DEFAULT NULL,
    `reference_id` INT DEFAULT NULL,
    `balance_after` DECIMAL(10,3) DEFAULT NULL,
    `reason` TEXT DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients`(`id`),
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 32. فواتير المشتريات والموردين (Invoices)
CREATE TABLE IF NOT EXISTS `invoices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `invoice_number` VARCHAR(50) NOT NULL UNIQUE,
    `type` ENUM('sale','purchase') NOT NULL DEFAULT 'purchase',
    `customer_id` INT DEFAULT NULL,
    `customer_name` VARCHAR(255) DEFAULT NULL,
    `customer_phone` VARCHAR(20) DEFAULT NULL,
    `supplier_id` INT DEFAULT NULL,
    `date` DATE NOT NULL,
    `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `discount` DECIMAL(10,2) DEFAULT 0.00,
    `paid_amount` DECIMAL(10,2) DEFAULT 0.00,
    `remaining_amount` DECIMAL(10,2) DEFAULT 0.00,
    `payment_method` VARCHAR(50) DEFAULT 'cash',
    `payment_status` ENUM('paid','partial','unpaid') DEFAULT 'unpaid',
    `handled_by` VARCHAR(100) DEFAULT NULL,
    `user_id` INT DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `status` ENUM('draft','approved','cancelled') NOT NULL DEFAULT 'approved',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 33. بنود الفواتير (Invoice Items)
CREATE TABLE IF NOT EXISTS `invoice_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `invoice_id` INT NOT NULL,
    `product_id` INT DEFAULT NULL,
    `ingredient_id` INT DEFAULT NULL,
    `item_name` VARCHAR(255) NOT NULL,
    `product_code` VARCHAR(50) DEFAULT NULL,
    `product_name` VARCHAR(255) DEFAULT NULL,
    `unit` VARCHAR(50) DEFAULT 'قطعة',
    `quantity` DECIMAL(10,2) NOT NULL,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `total` DECIMAL(10,2) NOT NULL,
    `purchase_unit` VARCHAR(50) DEFAULT NULL,
    `conversion_factor` DECIMAL(12,4) DEFAULT NULL,
    `stock_quantity` INT DEFAULT NULL,
    FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 33b. سجل حركات مخزون الأصناف (Product Stock Movements — append-only)
CREATE TABLE IF NOT EXISTS `product_stock_movements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `warehouse_id` INT DEFAULT NULL,
    `movement_type` ENUM('opening','purchase','sale','customer_return','supplier_return','adjustment','transfer_in','transfer_out','reversal') NOT NULL DEFAULT 'adjustment',
    `qty_in` INT NOT NULL DEFAULT 0,
    `qty_out` INT NOT NULL DEFAULT 0,
    `balance_after` INT NOT NULL DEFAULT 0,
    `unit` VARCHAR(50) DEFAULT NULL,
    `reference_type` VARCHAR(30) DEFAULT NULL,
    `reference_id` INT DEFAULT NULL,
    `reason` VARCHAR(255) DEFAULT NULL,
    `user_id` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_psm_product` (`product_id`),
    INDEX `idx_psm_type` (`movement_type`),
    INDEX `idx_psm_created` (`created_at`),
    INDEX `idx_psm_ref` (`reference_type`, `reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 34. سجل الرقابة والتدقيق (Audit Logs)
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL,
    `action` VARCHAR(50) NOT NULL,
    `entity_type` VARCHAR(50) NOT NULL,
    `entity_id` INT DEFAULT NULL,
    `old_value` TEXT DEFAULT NULL,
    `new_value` TEXT DEFAULT NULL,
    `reason` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 35. النسخ الاحتياطي (Backups)
CREATE TABLE IF NOT EXISTS `backups` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `filename` VARCHAR(255) NOT NULL,
    `file_size` BIGINT DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 36. تتبع التحديثات (Schema Migrations)
CREATE TABLE IF NOT EXISTS `schema_migrations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `migration_number` INT NOT NULL UNIQUE,
    `migration_file` VARCHAR(255) NOT NULL,
    `description` VARCHAR(500) DEFAULT NULL,
    `applied_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------- database/seed_settings.sql ----------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(50) NOT NULL UNIQUE,
    `setting_value` TEXT DEFAULT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES
('store_name', 'كافيه عروسة النيل', 'اسم الكافيه'),
('store_address', 'مصر', 'عنوان الكافيه'),
('store_phone', '01271191616', 'رقم الهاتف الرئيسي'),
('store_phone2', '01271901076', 'رقم هاتف إضافي'),
('store_logo', '', 'شعار الكافيه'),
('currency', 'ج.م', 'العملة'),
('enable_stock_alerts', '1', 'تنبيهات المخزون'),
('enable_sound_alerts', '1', 'التنبيهات الصوتية'),
('backup_enabled', '1', 'النسخ الاحتياطي التلقائي'),
('backup_frequency', 'daily', 'تكرار النسخ الاحتياطي'),
('default_user', 'المدير', 'المستخدم الافتراضي'),
('invoice_prefix_sale', 'ORDER-', 'بادئة رقم الطلب'),
('invoice_prefix_purchase', 'PUR-', 'بادئة فاتورة الشراء'),
('receipt_header', 'أهلاً بكم في كافيه عروسة النيل', 'ترويسة الفاتورة'),
('receipt_footer', 'شكراً لزيارتكم - نتمنى لكم وقتاً ممتعاً', 'تذييل الفاتورة'),
('ps_default_rate_single', '30.00', 'سعر ساعة البلايستيشن الفردي الافتراضي'),
('ps_default_rate_multi', '40.00', 'سعر ساعة البلايستيشن الجماعي الافتراضي'),
('billiard_default_rate_hour', '40.00', 'سعر ساعة البلياردو الافتراضي'),
('billiard_default_rate_game', '10.00', 'سعر جيم البلياردو الافتراضي'),
('vat_rate', '0.00', 'نسبة الضريبة'),
('service_charge', '0.00', 'نسبة الخدمة'),
('prevent_negative_stock', '0', 'منع البيع بأكثر من المخزون المتاح');

-- نقل مستخدم الأدمن الحالي
INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `phone`, `role`, `is_active`)
VALUES (1, 'admin', '$2y$10$FVxC20yF5AI837GbP73V..3aJE./h3CDDanGkhFnKOWi0zP00/Bt2', 'المدير العام', '01271191616', 'admin', 1)
ON DUPLICATE KEY UPDATE `username`='admin';

-- ---------- database/seed_menu.sql ----------
-- ==========================================================
-- كافيه عروسة النيل - سيدر المنيو الكامل والتصنيفات
-- Full Menu Seeder for Cafe Arouset El-Neel
-- ==========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE `product_modifiers`;
TRUNCATE TABLE `product_variants`;
TRUNCATE TABLE `products`;
TRUNCATE TABLE `categories`;

-- 1. التصنيفات الرئيسية للمنيو (16 قسم)
INSERT INTO `categories` (`id`, `name`, `icon`, `color`, `sort_order`) VALUES
(1, 'مشروبات الإسبريسو', '☕', '#6F4E37', 1),
(2, 'مشروبات القهوة', '☕', '#795548', 2),
(3, 'القهوة التركي', '☕', '#8D6E63', 3),
(4, 'مشروبات ساخنة', '🍵', '#D32F2F', 4),
(5, 'مشروبات الشوكولاتة', '🍫', '#5D4037', 5),
(6, 'عصائر فريش', '🍊', '#FF9800', 6),
(7, 'سموزي', '🥤', '#9C27B0', 7),
(8, 'فرابيه', '🧊', '#0288D1', 8),
(9, 'ميكسات وكوكتيلات', '🍹', '#E91E63', 9),
(10, 'ميلك شيك', '🥛', '#FFA000', 10),
(11, 'بوبينج بوبا', '🧋', '#009688', 11),
(12, 'بان كيك', '🥞', '#F57C00', 12),
(13, 'وافل', '🧇', '#8D6E63', 13),
(14, 'حلويات وديزيرت', '🍰', '#E91E63', 14),
(15, 'سلطة فواكه', '🍓', '#4CAF50', 15),
(16, 'آيس كريم', '🍦', '#00BCD4', 16),
(17, 'صودا ومشروبات غازية', '🥤', '#3F51B5', 17),
(18, 'إضافات وصوصات', '➕', '#607D8B', 18),
(19, 'خدمات وتسوية يدوية', '🧾', '#0ea5e9', 19);

-- 2. إدخال الإضافات العامة (Modifiers)
INSERT INTO `product_modifiers` (`id`, `name`, `modifier_group`, `price`, `is_available`, `applies_to_category_id`, `display_order`) VALUES
(1, 'صوص بلوبيري', 'sauce', 25.00, 1, NULL, 1),
(2, 'صوص فراولة', 'sauce', 25.00, 1, NULL, 2),
(3, 'صوص كراميل', 'sauce', 25.00, 1, NULL, 3),
(4, 'صوص شوكولاتة', 'sauce', 25.00, 1, NULL, 4),
(5, 'سيرب فانيليا', 'syrup', 30.00, 1, NULL, 5),
(6, 'سيرب كراميل', 'syrup', 30.00, 1, NULL, 6),
(7, 'سيرب بندق', 'syrup', 30.00, 1, NULL, 7),
(8, 'حليب إضافي', 'extra', 25.00, 1, NULL, 8),
(9, 'شوت إسبريسو إضافي', 'extra', 20.00, 1, NULL, 9),
(10, 'ميكس صوص إضافي للوافل/البان كيك', 'extra', 15.00, 1, NULL, 10);

-- 3. المنتجات والـ Variants

-- ==================== 1. مشروبات الإسبريسو ====================
INSERT INTO `products` (`id`, `category_id`, `code`, `name`, `price`, `has_variants`, `display_order`) VALUES
(1, 1, 'ESP-01', 'إسبريسو', 30.00, 1, 1),
(2, 1, 'ESP-02', 'ميكاتو', 40.00, 1, 2),
(3, 1, 'ESP-03', 'أفوجاتو (آيس كريم - إسبريسو)', 55.00, 0, 3),
(4, 1, 'ESP-04', 'أمريكانو', 45.00, 0, 4);

INSERT INTO `product_variants` (`product_id`, `variant_name`, `variant_group`, `final_price`, `is_default`, `display_order`) VALUES
(1, 'سنجل (Single)', 'size', 30.00, 1, 1),
(1, 'دبل (Double)', 'size', 40.00, 0, 2),
(2, 'سنجل (Single)', 'size', 40.00, 1, 1),
(2, 'دبل (Double)', 'size', 50.00, 0, 2);

-- ==================== 2. مشروبات القهوة ====================
INSERT INTO `products` (`id`, `category_id`, `code`, `name`, `price`, `has_variants`, `display_order`) VALUES
(10, 2, 'COF-01', 'لاتيه', 60.00, 0, 1),
(11, 2, 'COF-02', 'كابتشينو', 75.00, 0, 2),
(12, 2, 'COF-03', 'كورتادو', 65.00, 0, 3),
(13, 2, 'COF-04', 'فلات وايت', 70.00, 0, 4),
(14, 2, 'COF-05', 'سبانش لاتيه', 80.00, 0, 5),
(15, 2, 'COF-06', 'موكا (وايت / دارك)', 75.00, 1, 6),
(16, 2, 'COF-07', 'كراميل ميكاتو', 75.00, 0, 7),
(17, 2, 'COF-08', 'نوتيلا موكا', 75.00, 0, 8),
(18, 2, 'COF-09', 'بيستاشيو لاتيه', 85.00, 0, 9),
(19, 2, 'COF-10', 'لوتس لاتيه', 75.00, 0, 10),
(20, 2, 'COF-11', 'نسكافيه', 45.00, 0, 11);

INSERT INTO `product_variants` (`product_id`, `variant_name`, `variant_group`, `final_price`, `is_default`, `display_order`) VALUES
(15, 'دارك موكا', 'type', 75.00, 1, 1),
(15, 'وايت موكا', 'type', 75.00, 0, 2);

-- ==================== 3. القهوة التركي ====================
INSERT INTO `products` (`id`, `category_id`, `code`, `name`, `price`, `has_variants`, `display_order`) VALUES
(30, 3, 'TRK-01', 'قهوة تركي سادة', 25.00, 1, 1),
(31, 3, 'TRK-02', 'قهوة تركي محوج', 35.00, 1, 2),
(32, 3, 'TRK-03', 'قهوة فرنساوي كلاسيك', 40.00, 1, 3),
(33, 3, 'TRK-04', 'قهوة بندق', 35.00, 1, 4),
(34, 3, 'TRK-05', 'قهوة نوتيلا', 45.00, 1, 5),
(35, 3, 'TRK-06', 'قهوة لوتس', 45.00, 1, 6);

INSERT INTO `product_variants` (`product_id`, `variant_name`, `variant_group`, `final_price`, `is_default`, `display_order`) VALUES
(30, 'سنجل (Single)', 'size', 25.00, 1, 1),
(30, 'دبل (Double)', 'size', 35.00, 0, 2),
(31, 'سنجل (Single)', 'size', 35.00, 1, 1),
(31, 'دبل (Double)', 'size', 45.00, 0, 2),
(32, 'سنجل (Single)', 'size', 40.00, 1, 1),
(32, 'دبل (Double)', 'size', 50.00, 0, 2),
(33, 'سنجل (Single)', 'size', 35.00, 1, 1),
(33, 'دبل (Double)', 'size', 45.00, 0, 2),
(34, 'سنجل (Single)', 'size', 45.00, 1, 1),
(34, 'دبل (Double)', 'size', 60.00, 0, 2),
(35, 'سنجل (Single)', 'size', 45.00, 1, 1),
(35, 'دبل (Double)', 'size', 60.00, 0, 2);

-- ==================== 4. المشروبات الساخنة ====================
INSERT INTO `products` (`id`, `category_id`, `code`, `name`, `price`, `display_order`) VALUES
(40, 4, 'HOT-01', 'شاي كشري', 10.00, 1),
(41, 4, 'HOT-02', 'شاي فتلة', 15.00, 2),
(42, 4, 'HOT-03', 'أعشاب (نعناع / ينسون / كركديه)', 15.00, 3),
(43, 4, 'HOT-04', 'شاي فليفر', 20.00, 4),
(44, 4, 'HOT-05', 'هوت سيدر (عصير تفاح - قرفة)', 45.00, 5),
(45, 4, 'HOT-06', 'شاي فليفر كولد (خوخ/باشون/فانيليا/فراولة)', 65.00, 6),
(46, 4, 'HOT-07', 'سحلب بالحليب', 35.00, 7),
(47, 4, 'HOT-08', 'سحلب حليب مكسرات', 50.00, 8),
(48, 4, 'HOT-09', 'كوفي ميكس', 35.00, 9);

-- ==================== 5. مشروبات الشوكولاتة ====================
INSERT INTO `products` (`id`, `category_id`, `code`, `name`, `price`, `has_variants`, `display_order`) VALUES
(50, 5, 'CHO-01', 'هوت شوكلت (دارك / وايت)', 50.00, 1, 1),
(51, 5, 'CHO-02', 'هوت شوكلت نوتيلا', 65.00, 0, 2),
(52, 5, 'CHO-03', 'هوت شوكلت مارشميلو', 75.00, 0, 3),
(53, 5, 'CHO-04', 'هوت نوتيلا شوكلت (مارشميلو)', 80.00, 0, 4),
(54, 5, 'CHO-05', 'كولد شوكلت (دارك / وايت)', 60.00, 1, 5),
(55, 5, 'CHO-06', 'كولد نوتيلا شوكلت', 70.00, 0, 6);

INSERT INTO `product_variants` (`product_id`, `variant_name`, `variant_group`, `final_price`, `is_default`, `display_order`) VALUES
(50, 'دارك (Dark)', 'type', 50.00, 1, 1),
(50, 'وايت (White)', 'type', 50.00, 0, 2),
(54, 'دارك (Dark)', 'type', 60.00, 1, 1),
(54, 'وايت (White)', 'type', 60.00, 0, 2);

-- ==================== 6. عصائر فريش ====================
INSERT INTO `products` (`id`, `category_id`, `code`, `name`, `price`, `display_order`) VALUES
(60, 6, 'JUI-01', 'عصير مانجا فريش', 45.00, 1),
(61, 6, 'JUI-02', 'عصير مانجا خوخ', 55.00, 2),
(62, 6, 'JUI-03', 'عصير جوافة فريش', 45.00, 3),
(63, 6, 'JUI-04', 'عصير فراولة فريش', 45.00, 4),
(64, 6, 'JUI-05', 'عصير برتقال فريش', 45.00, 5),
(65, 6, 'JUI-06', 'عصير ليمون فريش', 35.00, 6),
(66, 6, 'JUI-07', 'عصير ليمون نعناع فريش', 40.00, 7);

-- ==================== 7. سموزي ====================
INSERT INTO `products` (`id`, `category_id`, `code`, `name`, `price`, `display_order`) VALUES
(70, 7, 'SMO-01', 'سموزي مانجو', 50.00, 1),
(71, 7, 'SMO-02', 'سموزي مانجو خوخ', 60.00, 2),
(72, 7, 'SMO-03', 'سموزي فراولة', 50.00, 3),
(73, 7, 'SMO-04', 'سموزي ليمون نعناع', 45.00, 4),
(74, 7, 'SMO-05', 'سموزي بلوبيري', 50.00, 5),
(75, 7, 'SMO-06', 'سموزي ميكس بيري', 50.00, 6),
(76, 7, 'SMO-07', 'سموزي خوخ', 50.00, 7),
(77, 7, 'SMO-08', 'سموزي بطيخ', 50.00, 8),
(78, 7, 'SMO-09', 'سموزي كيوي', 50.00, 9),
(79, 7, 'SMO-10', 'سموزي أناناس', 50.00, 10),
(80, 7, 'SMO-11', 'سموزي أناناس باشون', 60.00, 11),
(81, 7, 'SMO-12', 'سموزي كيوي ليمون', 60.00, 12);

-- ==================== 8. فرابيه ====================
INSERT INTO `products` (`id`, `category_id`, `code`, `name`, `price`, `display_order`) VALUES
(90, 8, 'FRP-01', 'فرابيه فانيليا', 60.00, 1),
(91, 8, 'FRP-02', 'فرابيه كراميل', 70.00, 2),
(92, 8, 'FRP-03', 'فرابيه موكا (دارك / وايت)', 70.00, 3),
(93, 8, 'FRP-04', 'فرابيه بلوبيري', 70.00, 4),
(94, 8, 'FRP-05', 'فرابتشينو', 80.00, 5);

-- ==================== 9. ميكسات وكوكتيلات ====================
INSERT INTO `products` (`id`, `category_id`, `code`, `name`, `price`, `description`, `display_order`) VALUES
(100, 9, 'MIX-01', 'فلوريدا', 75.00, 'مانجو + جوافة + فراولة', 1),
(101, 9, 'MIX-02', 'موز بالحليب كراميل', 75.00, 'موز + حليب + صوص كراميل', 2),
(102, 9, 'MIX-03', 'موخيتو كلاسيك', 70.00, 'سيرب موخيتو + ليمون + نعناع + صودا', 3),
(103, 9, 'MIX-04', 'صودا بلو', 70.00, 'سيرب بلو كارساو + ليمون + نعناع + صودا', 4),
(104, 9, 'MIX-05', 'بلو باشون', 80.00, 'بلو كارساو + بيوريه باشون فروت + بيوريه أناناس + صودا', 5),
(105, 9, 'MIX-06', 'بينا كولادا', 75.00, 'بيوريه أناناس + سيرب جوز هند + صودا', 6),
(106, 9, 'MIX-07', 'صن شاين', 75.00, 'سيرب رمان + عصير برتقال + ليمون + نعناع + صودا', 7),
(107, 9, 'MIX-08', 'صن رايز', 75.00, 'سيرب فراولة + عصير برتقال + ليمون + نعناع + صودا', 8),
(108, 9, 'MIX-09', 'ليمون كيوي صودا', 75.00, 'بيوريه كيوي + عصير ليمون + نعناع + صودا', 9),
(109, 9, 'MIX-10', 'بينك ليمونادا', 80.00, 'سيرب فراولة + سيرب سكر + عصير ليمون + صودا', 10),
(110, 9, 'MIX-11', 'صودا رمان', 70.00, 'سيرب رمان + كولا', 11),
(111, 9, 'MIX-12', 'شيري كولا', 75.00, 'بيوريه كريز + كولا', 12);

-- ==================== 10. ميلك شيك ====================
INSERT INTO `products` (`id`, `category_id`, `code`, `name`, `price`, `display_order`) VALUES
(120, 10, 'MLK-01', 'ميلك شيك فانيليا', 45.00, 1),
(121, 10, 'MLK-02', 'ميلك شيك شيكولاتة', 45.00, 2),
(122, 10, 'MLK-03', 'ميلك شيك نوتيلا', 65.00, 3),
(123, 10, 'MLK-04', 'ميلك شيك أوريو', 60.00, 4),
(124, 10, 'MLK-05', 'ميلك شيك لوتس', 60.00, 5),
(125, 10, 'MLK-06', 'ميلك شيك كوكيز', 65.00, 6),
(126, 10, 'MLK-07', 'ميلك شيك براونيز', 65.00, 7),
(127, 10, 'MLK-08', 'ميلك شيك كيندر', 65.00, 8),
(128, 10, 'MLK-09', 'ميلك شيك بيستاشيو', 85.00, 9),
(129, 10, 'MLK-10', 'ميلك شيك بلوبيري', 60.00, 10),
(130, 10, 'MLK-11', 'ميلك شيك ميكس بيري', 60.00, 11),
(131, 10, 'MLK-12', 'ميلك شيك مانجا', 60.00, 12),
(132, 10, 'MLK-13', 'ميلك شيك فراولة', 60.00, 13),
(133, 10, 'MLK-14', 'ميلك شيك بطيخ', 60.00, 14);

-- ==================== 11. بوبينج بوبا ====================
INSERT INTO `products` (`id`, `category_id`, `code`, `name`, `price`, `display_order`) VALUES
(140, 11, 'BBA-01', 'بوبينج بوبا باشون فروت', 35.00, 1),
(141, 11, 'BBA-02', 'بوبينج بوبا فراولة', 35.00, 2),
(142, 11, 'BBA-03', 'بوبينج بوبا بلوبيري', 35.00, 3),
(143, 11, 'BBA-04', 'بوبينج بوبا بطيخ', 35.00, 4),
(144, 11, 'BBA-05', 'بوبينج بوبا كولا', 35.00, 5),
(145, 11, 'BBA-06', 'بوبينج بوبا خوخ', 35.00, 6),
(146, 11, 'BBA-07', 'بوبينج بوبا كوفي', 35.00, 7);

-- ==================== 12. بان كيك ====================
INSERT INTO `products` (`id`, `category_id`, `code`, `name`, `price`, `has_variants`, `display_order`) VALUES
(150, 12, 'PNK-01', 'بان كيك شيكولاتة (نوتيلا - بيانكو - كراميل)', 60.00, 1, 1),
(151, 12, 'PNK-02', 'بان كيك نوتيلا', 70.00, 1, 2),
(152, 12, 'PNK-03', 'بان كيك كيندر', 75.00, 1, 3),
(153, 12, 'PNK-04', 'بان كيك لوتس', 70.00, 1, 4),
(154, 12, 'PNK-05', 'بان كيك أوريو', 70.00, 1, 5),
(155, 12, 'PNK-06', 'بان كيك بيستاشيو', 90.00, 1, 6),
(156, 12, 'PNK-07', 'بان كيك قهوة', 75.00, 1, 7),
(157, 12, 'PNK-08', 'بان كيك ميكس من اختيارك', 75.00, 1, 8);

INSERT INTO `product_variants` (`product_id`, `variant_name`, `variant_group`, `final_price`, `is_default`, `display_order`) VALUES
(150, '10 قطع', 'count', 60.00, 1, 1),
(150, '20 قطعة', 'count', 80.00, 0, 2),
(151, '10 قطع', 'count', 70.00, 1, 1),
(151, '20 قطعة', 'count', 85.00, 0, 2),
(152, '10 قطع', 'count', 75.00, 1, 1),
(152, '20 قطعة', 'count', 90.00, 0, 2),
(153, '10 قطع', 'count', 70.00, 1, 1),
(153, '20 قطع', 'count', 90.00, 0, 2),
(154, '10 قطع', 'count', 70.00, 1, 1),
(154, '20 قطعة', 'count', 90.00, 0, 2),
(155, '10 قطع', 'count', 90.00, 1, 1),
(155, '20 قطعة', 'count', 110.00, 0, 2),
(156, '10 قطع', 'count', 75.00, 1, 1),
(156, '20 قطعة', 'count', 90.00, 0, 2),
(157, '10 قطع', 'count', 75.00, 1, 1),
(157, '20 قطعة', 'count', 95.00, 0, 2);

-- ==================== 13. وافل ====================
INSERT INTO `products` (`id`, `category_id`, `code`, `name`, `price`, `has_variants`, `display_order`) VALUES
(160, 13, 'WFL-01', 'وافل شيكولاتة (نوتيلا - بيانكو - كراميل)', 60.00, 1, 1),
(161, 13, 'WFL-02', 'وافل نوتيلا', 70.00, 1, 2),
(162, 13, 'WFL-03', 'وافل كيندر', 75.00, 1, 3),
(163, 13, 'WFL-04', 'وافل لوتس', 70.00, 1, 4),
(164, 13, 'WFL-05', 'وافل أوريو', 70.00, 1, 5),
(165, 13, 'WFL-06', 'وافل بيستاشيو', 70.00, 1, 6),
(166, 13, 'WFL-07', 'وافل قهوة', 75.00, 1, 7),
(167, 13, 'WFL-08', 'وافل ميكس من اختيارك', 75.00, 1, 8);

INSERT INTO `product_variants` (`product_id`, `variant_name`, `variant_group`, `final_price`, `is_default`, `display_order`) VALUES
(160, 'حجم M (وسط)', 'size', 60.00, 1, 1),
(160, 'حجم L (كبير)', 'size', 80.00, 0, 2),
(161, 'حجم M (وسط)', 'size', 70.00, 1, 1),
(161, 'حجم L (كبير)', 'size', 85.00, 0, 2),
(162, 'حجم M (وسط)', 'size', 75.00, 1, 1),
(162, 'حجم L (كبير)', 'size', 95.00, 0, 2),
(163, 'حجم M (وسط)', 'size', 70.00, 1, 1),
(163, 'حجم L (كبير)', 'size', 90.00, 0, 2),
(164, 'حجم M (وسط)', 'size', 70.00, 1, 1),
(164, 'حجم L (كبير)', 'size', 90.00, 0, 2),
(165, 'حجم M (وسط)', 'size', 70.00, 1, 1),
(165, 'حجم L (كبير)', 'size', 115.00, 0, 2),
(166, 'حجم M (وسط)', 'size', 75.00, 1, 1),
(166, 'حجم L (كبير)', 'size', 95.00, 0, 2),
(167, 'حجم M (وسط)', 'size', 75.00, 1, 1),
(167, 'حجم L (كبير)', 'size', 90.00, 0, 2);

-- ==================== 14. حلويات وديزيرت ====================
INSERT INTO `products` (`id`, `category_id`, `code`, `name`, `price`, `description`, `display_order`) VALUES
(170, 14, 'DST-01', 'تشيز كيك (أوريو / لوتس / كراميل / بلوبيري / فراولة)', 65.00, 'تشيز كيك بالنكهة المختارة', 1),
(171, 14, 'DST-02', 'تشيز كيك بيستاشيو', 85.00, 'تشيز كيك فاخر بصوص البيستاشيو', 2),
(172, 14, 'DST-03', 'مولتن شوكلت', 65.00, 'كيك شوكولاتة دافئ محشو شوكولاتة ذائبة', 3),
(173, 14, 'DST-04', 'براونيز شوكلت (نوتيلا / كيندر / أوريو / لوتس)', 60.00, 'قطعة براونيز غنية بالصوص المفضل', 4),
(174, 14, 'DST-05', 'دبي كيك', 75.00, 'كيكة دبي الشهيرة', 5),
(175, 14, 'DST-06', 'مولتن لوتس', 75.00, 'مولتن كيك محشو صوص لوتس كريمي', 6),
(176, 14, 'DST-07', 'سينابون كلاسيك', 65.00, 'لفائف السينابون الطازجة بالقرفة والصوص الأبيض', 7);

-- ==================== 15. سلطة فواكه وأطباق ميكس ====================
INSERT INTO `products` (`id`, `category_id`, `code`, `name`, `price`, `description`, `display_order`) VALUES
(180, 15, 'FRT-01', 'سلطة فواكه كلاسيك', 60.00, 'تشكيلة فواكه موسمية طازجة مع صوص', 1),
(181, 15, 'FRT-02', 'وافل ميكس + سلطة فواكه', 110.00, 'وافل (نوتيلا / بيانكو / لوتس / أوريو) + طبق سلطة فواكه', 2),
(182, 15, 'FRT-03', 'بان كيك ميكس + سلطة فواكه', 110.00, 'بان كيك (نوتيلا / بيانكو / لوتس / أوريو) + طبق سلطة فواكه', 3),
(183, 15, 'FRT-04', 'وافل آيس كريم', 100.00, 'وافل (نوتيلا / بيانكو / لوتس / أوريو) + بولات آيس كريم', 4);

-- ==================== 16. آيس كريم ====================
INSERT INTO `products` (`id`, `category_id`, `code`, `name`, `price`, `display_order`) VALUES
(190, 16, 'ICE-01', 'آيس كريم 1 بولة', 20.00, 1),
(191, 16, 'ICE-02', 'آيس كريم 2 بولة', 40.00, 2),
(192, 16, 'ICE-03', 'آيس كريم 4 بولات ميكس', 50.00, 3);

-- ==================== 17. صودا ومشروبات غازية ====================
INSERT INTO `products` (`id`, `category_id`, `code`, `name`, `price`, `display_order`) VALUES
(200, 17, 'SOD-01', 'سفن أب (7UP)', 25.00, 1),
(201, 17, 'SOD-02', 'بيبسي', 25.00, 2),
(202, 17, 'SOD-03', 'ميرندا (برتقال / تفاح)', 25.00, 3),
(203, 17, 'SOD-04', 'تويست', 25.00, 4),
(204, 17, 'SOD-05', 'فولت', 20.00, 5),
(205, 17, 'SOD-06', 'ريد بول (Red Bull)', 75.00, 6),
(206, 17, 'SOD-07', 'مياه معدنية', 10.00, 7);

-- 4. إعداد أجهزة البلايستيشن المبدئية (Gaming Devices Seeder)
INSERT INTO `gaming_devices` (`device_number`, `display_name`, `device_type`, `room_type`, `rate_single`, `rate_multi`, `rate_vip`, `controller_count`, `display_order`) VALUES
('PS-01', 'جهاز 1 (PS4)', 'ps4', 'normal', 30.00, 40.00, NULL, 2, 1),
('PS-02', 'جهاز 2 (PS4)', 'ps4', 'normal', 30.00, 40.00, NULL, 2, 2),
('PS-03', 'جهاز 3 (PS4)', 'ps4', 'normal', 30.00, 40.00, NULL, 2, 3),
('PS-04', 'جهاز 4 (PS5)', 'ps5', 'normal', 40.00, 50.00, NULL, 2, 4),
('PS-05', 'جهاز 5 (PS5)', 'ps5', 'normal', 40.00, 50.00, NULL, 2, 5),
('VIP-01', 'غرفة VIP (PS5)', 'ps5', 'vip', 50.00, 60.00, 60.00, 4, 6);

-- 5. إعداد طاولات البلياردو المبدئية (Billiards Tables Seeder)
INSERT INTO `billiard_tables` (`table_number`, `display_name`, `table_type`, `billing_mode`, `rate_per_hour`, `rate_per_game`, `display_order`) VALUES
('TBL-B1', 'طاولة بلياردو 1', 'pool', 'hourly', 40.00, 10.00, 1),
('TBL-B2', 'طاولة بلياردو 2', 'pool', 'hourly', 40.00, 10.00, 2),
('TBL-B3', 'طاولة سنوكير 3', 'snooker', 'hourly', 50.00, 15.00, 3);

-- 6. إعداد طاولات الصالة المبدئية (Cafe Tables Seeder)
INSERT INTO `cafe_tables` (`table_number`, `display_name`, `section`, `capacity`, `display_order`) VALUES
('T-01', 'طاولة 1', 'main', 4, 1),
('T-02', 'طاولة 2', 'main', 4, 2),
('T-03', 'طاولة 3', 'main', 4, 3),
('T-04', 'طاولة 4', 'main', 4, 4),
('T-05', 'طاولة 5', 'main', 6, 5),
('T-06', 'طاولة 6', 'outdoor', 4, 6),
('T-07', 'طاولة 7', 'outdoor', 4, 7),
('T-08', 'طاولة 8', 'outdoor', 4, 8);

-- 7. تصنيفات المصروفات المناسبة للكافيه
INSERT INTO `expense_categories` (`id`, `name`, `description`) VALUES
(1, 'خامات ومشتريات بوفيه', 'بن، حليب، عصائر، فواكه، صوصات'),
(2, 'إيجار المكان', 'إيجار مقر الكافيه'),
(3, 'فواتير ومرافق (كهرباء ومياه وإنترنت)', 'استهلاك الكهرباء، التكييف، النت، المياه'),
(4, 'صيانة أجهزة وألعاب', 'دراعات بلايستيشن، شاشات، كاسيت، عصيان بلياردو، جوخ'),
(5, 'أجور ومرتبات ويوميات', 'رواتب كاشير، باريستا، ويتر، عمالة نظافة'),
(6, 'أدوات نظافة ومستهلكات', 'أكواب، شاليموه، أكياس، مناديل، منظفات'),
(7, 'مصروفات نثرية وطارئة', 'ضيافة، صيانة سباكة، مصاريف نقل');

SET FOREIGN_KEY_CHECKS = 1;

-- 8. تفعيل نافذة الإضافات لكل الأصناف (ما عدا الخدمات) + أصناف قسم الإضافات والصوصات (تباع منفصلة)
UPDATE `products` SET `has_modifiers` = 1 WHERE `is_service` = 0;
INSERT INTO `products` (`category_id`, `code`, `name`, `price`, `has_modifiers`, `display_order`) VALUES
(18, 'EXT-01', 'صوص بلوبيري', 25.00, 0, 1),
(18, 'EXT-02', 'صوص فراولة', 25.00, 0, 2),
(18, 'EXT-03', 'صوص كراميل', 25.00, 0, 3),
(18, 'EXT-04', 'صوص شوكولاتة', 25.00, 0, 4),
(18, 'EXT-05', 'سيرب فانيليا', 30.00, 0, 5),
(18, 'EXT-06', 'سيرب كراميل', 30.00, 0, 6),
(18, 'EXT-07', 'سيرب بندق', 30.00, 0, 7),
(18, 'EXT-08', 'حليب إضافي', 25.00, 0, 8),
(18, 'EXT-09', 'شوت إسبريسو إضافي', 20.00, 0, 9),
(18, 'EXT-10', 'ميكس صوص إضافي للوافل/البان كيك', 15.00, 0, 10);

-- 9. خدمات التسوية اليدوية (كارت بلايستيشن + كارت بلياردو — الاختيارات والأسعار الحية من الإعدادات داخل شاشة الكاشير)
INSERT INTO `products` (`category_id`, `code`, `name`, `price`, `has_modifiers`, `is_service`, `display_order`) VALUES
(19, 'SRV-PS', 'بلايستيشن', 0.00, 0, 1, 1),
(19, 'SRV-BL', 'بلياردو', 0.00, 0, 1, 2);

-- ---------- database/seed_ingredients.sql ----------
-- Seed Initial Cafe Raw Materials (Ingredients) for Cafe Arusa El Neel
INSERT INTO `ingredients` (`name`, `sku`, `base_unit`, `category`, `current_stock`, `min_stock_level`, `weighted_avg_cost`) VALUES
('حبوب بن اسبريسو (برازيلي/كولومبي)', 'ING-001', 'g', 'حبوب وبودرة', 5000.000, 1000.000, 0.4500),
('بن تركي محوج', 'ING-002', 'g', 'حبوب وبودرة', 3000.000, 500.000, 0.3500),
('بن تركي سادة', 'ING-003', 'g', 'حبوب وبودرة', 3000.000, 500.000, 0.3000),
('حليب طازج كامل الدسم', 'ING-004', 'ml', 'ألبان وسوائل', 20000.000, 3000.000, 0.0350),
('سكر أبيض ناعم', 'ING-005', 'g', 'حبوب وبودرة', 15000.000, 2000.000, 0.0300),
('سيرب فانيليا مونين', 'ING-006', 'ml', 'ألبان وسوائل', 1000.000, 200.000, 0.2500),
('سيرب كراميل مونين', 'ING-007', 'ml', 'ألبان وسوائل', 1000.000, 200.000, 0.2500),
('صوص شيكولاتة هيرشيز', 'ING-008', 'g', 'صوصات وتوبينج', 2000.000, 300.000, 0.1500),
('صوص لوتس', 'ING-009', 'g', 'صوصات وتوبينج', 1500.000, 200.000, 0.2000),
('شاي أسود ناعم / خرز', 'ING-010', 'g', 'حبوب وبودرة', 2000.000, 300.000, 0.1200),
('أكواب ورقية هوت درينك 8oz', 'ING-011', 'piece', 'تعبئة وتغليف', 500.000, 100.000, 1.2500),
('أكواب بلاستيك سموزي وكولد 16oz', 'ING-012', 'piece', 'تعبئة وتغليف', 400.000, 100.000, 2.0000),
('شاليموه ومزازات عريضة', 'ING-013', 'piece', 'تعبئة وتغليف', 1000.000, 200.000, 0.1500);

-- ---------- fresh-install overrides ----------
UPDATE `users` SET `password` = '$2y$10$UaTK6e2Qo1mEuhLHRabvt.ZF4/2EE00MC1NQgWc57rO08grQ5nbzK' WHERE `username` = 'admin';

-- ---------- warehouses (fresh-install port of database/migration_warehouses.sql) ----------
CREATE TABLE IF NOT EXISTS warehouses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50) DEFAULT NULL,
    location VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    manager_name VARCHAR(100) DEFAULT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS warehouse_stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    warehouse_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    min_stock_level INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_warehouse_product (warehouse_id, product_id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE invoices ADD COLUMN IF NOT EXISTS warehouse_id INT DEFAULT NULL;

INSERT INTO warehouses (id, name, code, location, is_default, is_active, notes)
VALUES (1, 'المخزن الرئيسي', 'WH-MAIN', 'المقر الرئيسي', 1, 1, 'المخزن الرئيسي الافتراضي للنظام')
ON DUPLICATE KEY UPDATE name = VALUES(name), is_default = 1;

INSERT INTO warehouse_stock (warehouse_id, product_id, quantity, min_stock_level)
SELECT 1, id, stock_quantity, min_stock_level FROM products
ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), min_stock_level = VALUES(min_stock_level);

UPDATE users SET default_warehouse_id = 1 WHERE default_warehouse_id IS NULL;
UPDATE invoices SET warehouse_id = 1 WHERE warehouse_id IS NULL;

-- ---------- payments ledger (create only, no seed) ----------
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_number VARCHAR(50) NOT NULL UNIQUE,
    type ENUM('customer','supplier') NOT NULL DEFAULT 'customer',
    entity_id INT DEFAULT NULL,
    entity_name VARCHAR(255) DEFAULT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    old_balance DECIMAL(10,2) DEFAULT 0.00,
    new_balance DECIMAL(10,2) DEFAULT 0.00,
    payment_date DATE DEFAULT NULL,
    payment_method VARCHAR(50) DEFAULT NULL,
    reference VARCHAR(100) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    handled_by VARCHAR(100) DEFAULT NULL,
    user_id INT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
