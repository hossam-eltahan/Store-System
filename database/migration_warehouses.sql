-- =============================================
-- Migration: Multi-Warehouse Management System
-- نظام إدارة المخازن المتعددة
-- =============================================

-- 1. جدول المخازن
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

-- 2. جدول رصيد المنتجات في كل مخزن
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

-- 3. جدول عمليات تحويل المخزون بين المخازن
CREATE TABLE IF NOT EXISTS stock_transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transfer_number VARCHAR(50) NOT NULL UNIQUE,
    from_warehouse_id INT NOT NULL,
    to_warehouse_id INT NOT NULL,
    transfer_date DATE NOT NULL,
    status ENUM('pending', 'completed', 'cancelled') NOT NULL DEFAULT 'completed',
    notes TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (from_warehouse_id) REFERENCES warehouses(id),
    FOREIGN KEY (to_warehouse_id) REFERENCES warehouses(id),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. جدول بنود تحويل المخزون
CREATE TABLE IF NOT EXISTS stock_transfer_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transfer_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (transfer_id) REFERENCES stock_transfers(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. إضافة الأعمدة إلى الجداول الحالية
ALTER TABLE users ADD COLUMN IF NOT EXISTS default_warehouse_id INT DEFAULT NULL;
ALTER TABLE invoices ADD COLUMN IF NOT EXISTS warehouse_id INT DEFAULT NULL;
ALTER TABLE returns ADD COLUMN IF NOT EXISTS warehouse_id INT DEFAULT NULL;

-- إضافة المفاتيح الأجنبية
ALTER TABLE users ADD CONSTRAINT fk_user_warehouse FOREIGN KEY (default_warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL;
ALTER TABLE invoices ADD CONSTRAINT fk_invoice_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL;
ALTER TABLE returns ADD CONSTRAINT fk_return_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL;

-- 6. إنشاء المخزن الرئيسي الافتراضي (إذا لم يكن موجوداً)
INSERT INTO warehouses (id, name, code, location, is_default, is_active, notes)
VALUES (1, 'المخزن الرئيسي', 'WH-MAIN', 'المقر الرئيسي', 1, 1, 'المخزن الرئيسي الافتراضي للنظام')
ON DUPLICATE KEY UPDATE name = VALUES(name), is_default = 1;

-- 7. ترحيل البيانات الحالية (Legacy Data Migration)
-- أ) ترحيل رصيد المنتجات الحالية إلى المخزن الرئيسي
INSERT INTO warehouse_stock (warehouse_id, product_id, quantity, min_stock_level)
SELECT 1, id, stock_quantity, min_stock_level 
FROM products
ON DUPLICATE KEY UPDATE 
    quantity = VALUES(quantity), 
    min_stock_level = VALUES(min_stock_level);

-- ب) تعيين المخزن الرئيسي كمخزن افتراضي لجميع المستخدمين الحاليين
UPDATE users SET default_warehouse_id = 1 WHERE default_warehouse_id IS NULL;

-- ج) ربط جميع الفواتير الحالية بالمخزن الرئيسي
UPDATE invoices SET warehouse_id = 1 WHERE warehouse_id IS NULL;

-- د) ربط جميع المرتجعات الحالية بالمخزن الرئيسي
UPDATE returns SET warehouse_id = 1 WHERE warehouse_id IS NULL;
