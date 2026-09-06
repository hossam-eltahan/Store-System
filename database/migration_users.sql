-- =============================================
-- Migration: Users & Permissions System
-- نظام المستخدمين والصلاحيات
-- =============================================

-- جدول المستخدمين
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    email VARCHAR(100) DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    role ENUM('admin', 'employee') NOT NULL DEFAULT 'employee',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login DATETIME DEFAULT NULL,
    last_login_ip VARCHAR(45) DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول الصلاحيات لكل مستخدم
CREATE TABLE IF NOT EXISTS user_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    permission_key VARCHAR(50) NOT NULL,
    UNIQUE KEY unique_user_perm (user_id, permission_key),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول سجل الجلسات
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    login_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    logout_at TIMESTAMP NULL DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إضافة عمود user_id في الجداول الحالية
ALTER TABLE activity_log ADD COLUMN IF NOT EXISTS user_id INT DEFAULT NULL AFTER id;
ALTER TABLE invoices ADD COLUMN IF NOT EXISTS user_id INT DEFAULT NULL AFTER handled_by;
ALTER TABLE payments ADD COLUMN IF NOT EXISTS user_id INT DEFAULT NULL AFTER handled_by;
ALTER TABLE returns ADD COLUMN IF NOT EXISTS user_id INT DEFAULT NULL;
ALTER TABLE installment_plans ADD COLUMN IF NOT EXISTS user_id INT DEFAULT NULL;
ALTER TABLE installment_payments ADD COLUMN IF NOT EXISTS user_id INT DEFAULT NULL;

-- إنشاء حساب المدير الافتراضي (كلمة المرور: admin123)
-- bcrypt hash of 'admin123'
INSERT INTO users (username, password, full_name, role, is_active)
VALUES ('admin', '$2y$10$FVxC20yF5AI837GbP73V..3aJE./h3CDDanGkhFnKOWi0zP00/Bt2', 'المدير', 'admin', 1)
ON DUPLICATE KEY UPDATE username = username;
