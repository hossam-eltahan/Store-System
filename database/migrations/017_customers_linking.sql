-- Migration: 017
-- Description: Customer accounts linking — orders/sessions get customer_id + settlements table
-- Created: 2026-10-19
-- Data-safety: additive only (ADD COLUMN nullable / CREATE TABLE IF NOT EXISTS).
--               Backfills only fill NULL customer_id from exact matches.
--               No DELETE / DROP / UPDATE of any existing value.

-- 1) customer_id on orders
ALTER TABLE orders ADD COLUMN customer_id INT NULL AFTER customer_name;
ALTER TABLE orders ADD INDEX idx_orders_customer (customer_id);

-- 2) customer_id on hall sessions
ALTER TABLE gaming_sessions ADD COLUMN customer_id INT NULL AFTER customer_name;
ALTER TABLE gaming_sessions ADD INDEX idx_gamingsess_customer (customer_id);
ALTER TABLE billiard_sessions ADD COLUMN customer_id INT NULL AFTER customer_name;
ALTER TABLE billiard_sessions ADD INDEX idx_billardsess_customer (customer_id);
ALTER TABLE table_sessions ADD COLUMN customer_id INT NULL AFTER customer_name;
ALTER TABLE table_sessions ADD INDEX idx_tablesess_customer (customer_id);

-- 3) Consolidated settlement records (محاسبة الكل / المحدد)
CREATE TABLE IF NOT EXISTS customer_settlements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    settlement_number VARCHAR(30) NOT NULL UNIQUE,
    customer_id INT NOT NULL,
    items_count INT NOT NULL DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    per_item TEXT NULL,
    notes TEXT NULL,
    shift_id INT NULL,
    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) Backfill: link orders by phone+name first (highest trust), then by exactly
--    one customer having that name. NULL customer_name is never linked.
UPDATE orders o
JOIN customers c ON c.name = o.customer_name AND c.phone = o.customer_phone
SET o.customer_id = c.id
WHERE o.customer_id IS NULL AND o.customer_name <> '' AND o.customer_phone <> '';

UPDATE orders o
JOIN (SELECT name, MIN(id) AS cid, COUNT(*) AS cnt FROM customers GROUP BY name) m
  ON m.name = o.customer_name
SET o.customer_id = m.cid
WHERE o.customer_id IS NULL AND o.customer_name <> '' AND m.cnt = 1;

UPDATE gaming_sessions gs
JOIN (SELECT name, MIN(id) AS cid, COUNT(*) AS cnt FROM customers GROUP BY name) m
  ON m.name = gs.customer_name
SET gs.customer_id = m.cid
WHERE gs.customer_id IS NULL AND gs.customer_name <> '' AND m.cnt = 1;

UPDATE billiard_sessions bs
JOIN (SELECT name, MIN(id) AS cid, COUNT(*) AS cnt FROM customers GROUP BY name) m
  ON m.name = bs.customer_name
SET bs.customer_id = m.cid
WHERE bs.customer_id IS NULL AND bs.customer_name <> '' AND m.cnt = 1;

UPDATE table_sessions ts
JOIN (SELECT name, MIN(id) AS cid, COUNT(*) AS cnt FROM customers GROUP BY name) m
  ON m.name = ts.customer_name
SET ts.customer_id = m.cid
WHERE ts.customer_id IS NULL AND ts.customer_name <> '' AND m.cnt = 1;
