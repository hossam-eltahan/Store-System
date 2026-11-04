<?php
/**
 * Quick supply (توريد سريع)
 * Increases stock immediately and records a supply movement.
 * No purchase invoice is created and no supplier debt is added.
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';

requireLogin();
requirePermission('warehouses.supply');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

$returnTo = (($_POST['return_to'] ?? 'products') === 'stock') ? 'stock' : 'products';
$warehouseId = (int)($_POST['warehouse_id'] ?? 0);
$productId = (int)($_POST['product_id'] ?? 0);
$qtyRaw = trim((string)($_POST['quantity'] ?? ''));
$priceRaw = trim((string)($_POST['unit_cost'] ?? ''));
$supplierId = (int)($_POST['supplier_id'] ?? 0);
$notes = sanitize((string)($_POST['notes'] ?? ''));

$returnWarehouseId = $warehouseId > 0 ? $warehouseId : (int)getCurrentWarehouseId();
$returnUrl = $returnTo === 'stock'
    ? '../warehouses/stock.php?warehouse_id=' . $returnWarehouseId
    : 'index.php';

/**
 * Create the movement log table on installations that have not run the
 * bundled migration yet. Kept additive and idempotent.
 */
function ensureQuickSupplyTable() {
    global $pdo;

    if (getRow("SHOW TABLES LIKE 'product_stock_movements'")) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS `product_stock_movements` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `product_id` INT(11) NOT NULL,
        `warehouse_id` INT(11) NOT NULL,
        `movement_type` ENUM('supply','adjustment') NOT NULL DEFAULT 'supply',
        `quantity` INT(11) NOT NULL DEFAULT 0,
        `balance_before` INT(11) NOT NULL DEFAULT 0,
        `balance_after` INT(11) NOT NULL DEFAULT 0,
        `unit` VARCHAR(50) DEFAULT NULL,
        `unit_cost` DECIMAL(10,2) DEFAULT NULL,
        `supplier_id` INT(11) DEFAULT NULL,
        `reference_type` VARCHAR(30) DEFAULT NULL,
        `reference_id` INT(11) DEFAULT NULL,
        `reason` TEXT DEFAULT NULL,
        `user_id` INT(11) DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_psm_product` (`product_id`),
        KEY `idx_psm_warehouse` (`warehouse_id`),
        KEY `idx_psm_type_date` (`movement_type`, `created_at`),
        KEY `idx_psm_supplier` (`supplier_id`),
        KEY `idx_psm_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `schema_migrations` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `migration_number` INT NOT NULL,
        `migration_file` VARCHAR(255) NOT NULL,
        `description` VARCHAR(500) DEFAULT NULL,
        `applied_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_migration` (`migration_number`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $stmt = $pdo->prepare("INSERT IGNORE INTO schema_migrations (migration_number, migration_file, description) VALUES (3, '003_quick_supply.sql', 'Quick supply movements (stock increase without purchase invoice)')");
    $stmt->execute();
}

if ($qtyRaw === '' || !is_numeric($qtyRaw) || (float)$qtyRaw <= 0 || abs((float)$qtyRaw - round((float)$qtyRaw)) > 0.0001) {
    setError('كمية التوريد يجب أن تكون عدداً صحيحاً أكبر من صفر');
    redirect($returnUrl);
}
$qty = (int)round((float)$qtyRaw);

$unitCost = null;
if ($priceRaw !== '') {
    if (!is_numeric($priceRaw) || (float)$priceRaw < 0) {
        setError('سعر التكلفة يجب أن يكون رقماً صحيحاً');
        redirect($returnUrl);
    }
    $unitCost = round((float)$priceRaw, 2);
    if ($unitCost <= 0) {
        $unitCost = null;
    }
}

$supplierName = '';
if ($supplierId > 0) {
    $supplier = getRow("SELECT id, name FROM suppliers WHERE id = ?", [$supplierId]);
    if ($supplier) {
        $supplierName = $supplier['name'];
    } else {
        $supplierId = 0;
    }
}

try {
    ensureQuickSupplyTable();
    beginTransaction();

    $product = getRow("SELECT * FROM products WHERE id = ? FOR UPDATE", [$productId]);
    if (!$product) {
        throw new Exception('الصنف غير موجود');
    }
    if (!empty($product['is_service'])) {
        throw new Exception('الأصناف الخدمية لا تُخزَّن ولا يمكن توريدها');
    }

    $warehouse = getRow("SELECT id, name FROM warehouses WHERE id = ? AND is_active = 1", [$warehouseId]);
    if (!$warehouse) {
        throw new Exception('المخزن غير موجود أو غير مفعّل');
    }

    $stockRow = getRow("SELECT quantity FROM warehouse_stock WHERE warehouse_id = ? AND product_id = ? FOR UPDATE", [$warehouseId, $productId]);
    if ($stockRow) {
        $balanceBefore = (int)$stockRow['quantity'];
    } else {
        $balanceBefore = 0;

        // Legacy products may still carry their stock only in
        // products.stock_quantity. Seed the first warehouse row from it so the
        // following sync does not erase that legacy balance.
        $anyWarehouseRow = getRow("SELECT id FROM warehouse_stock WHERE product_id = ? LIMIT 1", [$productId]);
        if (!$anyWarehouseRow) {
            $legacyStock = max(0, (int)($product['stock_quantity'] ?? 0));
            if ($legacyStock > 0) {
                $seeded = insert(
                    "INSERT INTO warehouse_stock (warehouse_id, product_id, quantity) VALUES (?, ?, ?)",
                    [$warehouseId, $productId, $legacyStock]
                );
                if ($seeded === false) {
                    throw new Exception('تعذر تهيئة رصيد المخزن للصنف');
                }
                $balanceBefore = $legacyStock;
            }
        }
    }

    $balanceAfter = updateWarehouseStock($warehouseId, $productId, $qty, 'add');
    if ($balanceAfter === false) {
        throw new Exception('تعذر تحديث رصيد المخزن');
    }

    $unit = $product['unit'] ?: 'قطعة';
    $reason = 'توريد سريع بدون فاتورة (' . $qty . ' ' . $unit . ')'
        . ($supplierName !== '' ? ' — المورد: ' . $supplierName : '')
        . ($notes !== '' ? ' — ' . $notes : '');

    $movementId = insert(
        "INSERT INTO product_stock_movements
            (product_id, warehouse_id, movement_type, quantity, balance_before, balance_after, unit, unit_cost, supplier_id, reference_type, reason, user_id)
         VALUES (?, ?, 'supply', ?, ?, ?, ?, ?, ?, 'quick_supply', ?, ?)",
        [
            $productId,
            $warehouseId,
            $qty,
            $balanceBefore,
            (int)$balanceAfter,
            $unit,
            $unitCost,
            $supplierId > 0 ? $supplierId : null,
            $reason,
            getCurrentUserId(),
        ]
    );
    if ($movementId === false) {
        throw new Exception('تعذر تسجيل حركة التوريد');
    }

    if ($unitCost !== null) {
        execute("UPDATE products SET cost_price = ? WHERE id = ?", [$unitCost, $productId]);
    }

    commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        rollback();
    }
    setError('تعذر التوريد: ' . $e->getMessage());
    redirect($returnUrl);
}

logActivity(
    'توريد سريع',
    'تم توريد ' . $qty . ' ' . ($product['unit'] ?: 'قطعة') . ' من «' . $product['name'] . '» إلى ' . $warehouse['name']
        . ($supplierName !== '' ? ' — المورد: ' . $supplierName : '')
        . ($notes !== '' ? ' — ' . $notes : '') . ' (بدون فاتورة)',
    getCurrentUserName()
);

setSuccess(
    'تم توريد ' . $qty . ' ' . ($product['unit'] ?: 'قطعة') . ' من «' . $product['name'] . '» إلى ' . $warehouse['name']
        . ' — بدون فاتورة أو مديونية على المورد'
);

redirect($returnUrl);
