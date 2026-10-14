-- Migration: 002
-- Description: Create session_timer_notifications tracking table + add time_mode/preset_minutes to billiard_sessions
-- Created: 2026-10-14

-- Create tracking table (avoids needing ALTER TABLE on existing session tables)
CREATE TABLE IF NOT EXISTS `session_timer_notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `entity_type` ENUM('gaming', 'billiards') NOT NULL,
    `session_id` INT NOT NULL,
    `notified_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_session` (`entity_type`, `session_id`),
    INDEX `idx_notified_at` (`notified_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add time_mode and preset_minutes to billiard_sessions (used by countdown timer feature)
ALTER TABLE billiard_sessions ADD COLUMN IF NOT EXISTS time_mode ENUM('open','countdown') NOT NULL DEFAULT 'open';
ALTER TABLE billiard_sessions ADD COLUMN IF NOT EXISTS preset_minutes INT DEFAULT NULL;
