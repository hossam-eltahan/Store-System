-- Migration: 010
-- Description: link purchase invoice lines to the quick-supply movement they
--              document, so approving such an invoice never adds stock twice.
-- Created: 2026-10-15

ALTER TABLE `invoice_items`
    ADD COLUMN IF NOT EXISTS `source_movement_id` INT DEFAULT NULL,
    ADD UNIQUE KEY IF NOT EXISTS `unique_source_movement` (`source_movement_id`);

ALTER TABLE `product_stock_movements`
    ADD COLUMN IF NOT EXISTS `linked_invoice_id` INT DEFAULT NULL;
