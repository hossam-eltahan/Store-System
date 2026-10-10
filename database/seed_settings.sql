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
('service_charge', '0.00', 'نسبة الخدمة');

-- نقل مستخدم الأدمن الحالي
INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `phone`, `role`, `is_active`)
VALUES (1, 'admin', '$2y$10$FVxC20yF5AI837GbP73V..3aJE./h3CDDanGkhFnKOWi0zP00/Bt2', 'المدير العام', '01271191616', 'admin', 1)
ON DUPLICATE KEY UPDATE `username`='admin';
