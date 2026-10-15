-- Migration: 009
-- Description: generic stock-config backfill — every product gets a purchase
--              unit and a conversion factor where missing. No stock quantities,
--              no tracking flags and no units are changed here.
-- Created: 2026-10-15

UPDATE `products`
SET `purchase_unit` = `unit`
WHERE `purchase_unit` IS NULL OR `purchase_unit` = '';

UPDATE `products`
SET `units_per_purchase` = 1
WHERE `units_per_purchase` IS NULL OR `units_per_purchase` <= 0;
