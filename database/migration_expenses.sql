-- ==========================================================
-- Migration: Operating Expenses & Costs System
-- نظام إدارة المصروفات والتكاليف التشغيلية
-- ==========================================================

-- 1. جدول تصنيفات وبنود المصروفات
CREATE TABLE IF NOT EXISTS expense_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    icon VARCHAR(50) DEFAULT '💸',
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_expense_cat_name (name),
    INDEX idx_expense_cat_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إدخال التصنيفات الافتراضية الأكثر شيوعاً
INSERT IGNORE INTO expense_categories (name, description, icon, sort_order) VALUES
('إيجار', 'إيجار المحل أو المخازن أو المقرات', '🏢', 1),
('كهرباء', 'فواتير واستهلاك الكهرباء وشحن العدادات', '⚡', 2),
('مياه وغاز', 'فواتير المياه والغاز الطبيعي', '💧', 3),
('مرتبات وأجور', 'مرتبات العاملين والموظفين والمكافآت', '👥', 4),
('صيانة وإصلاحات', 'صيانة المحل، الديكورات، الأجهزة، والإنارة', '🔧', 5),
('بوفيه ونثريات', 'مشروبات، ضيافة، أدوات نظافة ومأكولات', '☕', 6),
('نقل وشحن ومواصلات', 'مصاريف الانتقال، نولون البضاعة والشحن', '🚚', 7),
('دعاية وتسويق', 'إعلانات ممولة، لافتات، طباعة كروت وبنرات', '📢', 8),
('أدوات ومهمات تشغيل', 'أكياس تعبئة، بكر فواتير، أوراق، مستلزمات مكتبية', '📦', 9),
('ضرائب ورسوم حكومية', 'ضرائب، تأمينات، تراخيص ورسوم مهنية', '🏛️', 10),
('مصروفات أخرى', 'أي مصروفات أو تكاليف متنوعة أخرى', '📝', 99);

-- 2. جدول سندات المصروفات
CREATE TABLE IF NOT EXISTS expenses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    expense_number VARCHAR(50) NOT NULL,
    category_id INT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    expense_date DATE NOT NULL,
    payment_method VARCHAR(50) NOT NULL DEFAULT 'نقدي',
    paid_to VARCHAR(150) NULL,
    receipt_number VARCHAR(100) NULL,
    notes TEXT NULL,
    user_id INT NULL,
    handled_by VARCHAR(100) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_expense_number (expense_number),
    INDEX idx_expense_date (expense_date),
    INDEX idx_expense_category (category_id),
    INDEX idx_expense_user (user_id),
    INDEX idx_expense_pay_method (payment_method),
    CONSTRAINT fk_expenses_category 
        FOREIGN KEY (category_id) REFERENCES expense_categories(id) 
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
