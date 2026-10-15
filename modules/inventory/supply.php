<?php
/**
 * Quick supply without a purchase invoice (توريد سريع)
 * Increases stock + logs a 'supply' movement. No supplier debt is created.
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requireLogin();
requirePermission('inventory.adjust');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

try {
    $productId = (int)($_POST['product_id'] ?? 0);
    $warehouseId = (int)($_POST['warehouse_id'] ?? 0);
    $qtyRaw = trim($_POST['quantity'] ?? '');
    $qtyUnit = ($_POST['qty_unit'] ?? 'stock') === 'purchase' ? 'purchase' : 'stock';
    $priceRaw = trim($_POST['price'] ?? '');
    $supplierId = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
    $notes = sanitize(trim($_POST['notes'] ?? ''));

    if ($productId <= 0 || $warehouseId <= 0 || $qtyRaw === '' || !is_numeric($qtyRaw) || (float)$qtyRaw <= 0) {
        throw new Exception('بيانات التوريد غير مكتملة');
    }

    $product = getRow("SELECT * FROM products WHERE id = ?", [$productId]);
    if (!$product) {
        throw new Exception('الصنف غير موجود');
    }
    $warehouse = getRow("SELECT id, name FROM warehouses WHERE id = ?", [$warehouseId]);
    if (!$warehouse) {
        throw new Exception('المخزن غير موجود');
    }

    $factor = (float)($product['units_per_purchase'] ?: 1);
    if ($factor <= 0) {
        throw new Exception('معامل التحويل لهذا الصنف غير صالح — حدّثه من شاشة الصنف أولًا');
    }
    $purchaseUnit = $product['purchase_unit'] ?: $product['unit'];

    // Convert to base stock units (whole-number rule enforced)
    if ($qtyUnit === 'purchase') {
        $res = (float)$qtyRaw * $factor;
        if (abs($res - round($res)) > 0.0001) {
            throw new Exception('ناتج التحويل لوحدات المخزون يجب أن يكون عدداً صحيحاً');
        }
        $stockQty = (int)round($res);
        $qtyLabel = $qtyRaw . ' ' . $purchaseUnit;
    } else {
        if (abs((float)$qtyRaw - round((float)$qtyRaw)) > 0.0001) {
            throw new Exception('كمية المخزون يجب أن تكون عدداً صحيحاً');
        }
        $stockQty = (int)$qtyRaw;
        $qtyLabel = $stockQty . ' ' . $product['unit'];
    }

    // Optional valuation: purchase-unit price → stock-unit cost
    $unitCost = null;
    $price = ($priceRaw !== '' && is_numeric($priceRaw)) ? max(0, (float)$priceRaw) : 0;
    if ($price > 0) {
        $unitCost = ($qtyUnit === 'purchase' && $factor > 0) ? ($price / $factor) : $price;
    }

    $supplierName = '';
    if ($supplierId) {
        $sup = getRow("SELECT id, name FROM suppliers WHERE id = ?", [$supplierId]);
        if ($sup) {
            $supplierName = $sup['name'];
        } else {
            $supplierId = null;
        }
    }

    $reason = 'توريد سريع بدون فاتورة (' . $qtyLabel . ')'
        . ($supplierName !== '' ? ' — المورد: ' . $supplierName : '')
        . ($notes !== '' ? ' — ' . $notes : '');

    beginTransaction();
    addProductStock($productId, $warehouseId, $stockQty, 'supply', 'supply', null, $reason, $unitCost, $supplierId);
    if ($unitCost !== null && $unitCost > 0) {
        execute("UPDATE products SET cost_price = ? WHERE id = ?", [$unitCost, $productId]);
    }
    commit();

    logActivity('توريد سريع', "توريد {$product['name']}: {$qtyLabel} = {$stockQty} {$product['unit']} إلى {$warehouse['name']}", $_SESSION['full_name'] ?? '');
    setSuccess('تم توريد ' . $stockQty . ' ' . $product['unit'] . ' من «' . $product['name'] . '» إلى ' . $warehouse['name'] . ' وتسجيلها (بدون فاتورة)');
    redirect('index.php?warehouse_id=' . $warehouseId);
} catch (Exception $e) {
    rollback();
    setError('تعذر التوريد: ' . $e->getMessage());
    redirect('index.php');
}
