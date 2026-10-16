-- Migration: 015
-- Description: remember-me persistent login (token hash + expiry on users).
-- Created: 2026-10-16

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `remember_token` VARCHAR(64) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `remember_expiry` DATETIME DEFAULT NULL;
