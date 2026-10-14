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
    `is_available` TINYINT(1) NOT NULL DEFAULT 1,
    `has_variants` TINYINT(1) NOT NULL DEFAULT 0,
    `has_modifiers` TINYINT(1) NOT NULL DEFAULT 0,
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
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
    `unit` VARCHAR(50) DEFAULT 'قطعة',
    `quantity` DECIMAL(10,2) NOT NULL,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `total` DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE
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
