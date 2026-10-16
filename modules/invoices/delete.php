<?php
/**
 * Delete Invoice - حذف فاتورة
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';

// Check if deletion is allowed
$settings = getAllSettings();
if (($settings['allow_delete_invoices'] ?? '0') !== '1') {
    setError('خاصية مسح الفواتير غير مفعلة في الإعدادات');
    redirect('list.php');
}

$id = $_GET['id'] ?? 0;
$invoice = getRow("SELECT * FROM invoices WHERE id = ?", [$id]);

if (!$invoice) {
    setError('الفاتورة غير موجودة');
    redirect('list.php');
}

// Require appropriate delete permission
if ($invoice['type'] === 'purchase') {
    requirePermission('invoices.purchase.delete');
} else {
    requirePermission('invoices.sale.delete');
}

try {
    beginTransaction();
    
    // 1. Check if installment plan exists and has payments
    $plan = getRow("SELECT * FROM installment_plans WHERE invoice_id = ?", [$id]);
    if ($plan) {
        $paidCountRow = getRow(
            "SELECT COUNT(*) as count FROM installment_payments WHERE plan_id = ? AND status IN ('paid', 'partial')", 
            [$plan['id']]
        );
        if ($paidCountRow['count'] > 0) {
            throw new Exception('لا يمكن حذف الفاتورة لأنه تم سداد أقساط منها بالفعل. يرجى عمل مرتجع بدلاً من ذلك.');
        }
    }
    
    // 2. Reverse inventory for the specific warehouse
    // Draft purchase invoices never touched stock/balance — delete rows only.
    $isDraftPurchase = ($invoice['type'] === 'purchase' && ($invoice['status'] ?? 'approved') === 'draft');
    $warehouseId = (int)($invoice['warehouse_id'] ?? 1);
    $items = getRows("SELECT * FROM invoice_items WHERE invoice_id = ?", [$id]);
    if (!$isDraftPurchase) {
        foreach ($items as $item) {
            if ($invoice['type'] === 'sale') {
                updateWarehouseStock($warehouseId, $item['product_id'], $item['quantity'], 'add');
            } else {
                // Lines documenting a quick supply: stock never came from this invoice — skip reversal
                if (!empty($item['source_movement_id'])) {
                    continue;
                }
                // Raw-material lines reverse from ingredients stock + ledger
                if (!empty($item['ingredient_id'])) {
                    $reverseQty = ($item['stock_quantity'] !== null) ? (float)$item['stock_quantity'] : (float)$item['quantity'];
                    removeIngredientStock((int)$item['ingredient_id'], $reverseQty, 'return', 'purchase_invoice', $id,
                        'عكس فاتورة شراء قبل الحذف ' . $invoice['invoice_number']);
                    continue;
                }
                $reverseQty = ($item['stock_quantity'] !== null) ? (int)$item['stock_quantity'] : (int)$item['quantity'];
                updateWarehouseStock($warehouseId, $item['product_id'], $reverseQty, 'subtract');
                $delProd = getRow("SELECT unit FROM products WHERE id = ?", [(int)$item['product_id']]);
                recordProductMovement((int)$item['product_id'], $warehouseId, 'reversal', 0, $reverseQty,
                    $delProd ? $delProd['unit'] : ($item['unit'] ?? null), 'purchase_invoice', $id,
                    'عكس فاتورة شراء قبل الحذف ' . $invoice['invoice_number']);
            }
        }
    }

    // Free any supply movements linked to this invoice
    execute("UPDATE product_stock_movements SET linked_invoice_id = NULL WHERE linked_invoice_id = ?", [$id]);

    // 3. Reverse entity balance (drafts have none)
    if ($invoice['type'] === 'sale' && $invoice['customer_id']) {
        execute("UPDATE customers SET balance = balance + ? WHERE id = ?", [$invoice['remaining_amount'], $invoice['customer_id']]);
    } elseif ($invoice['type'] === 'purchase' && $invoice['supplier_id'] && !$isDraftPurchase) {
        execute("UPDATE suppliers SET balance = balance - ? WHERE id = ?", [$invoice['remaining_amount'], $invoice['supplier_id']]);
    }
    
    // 4. Delete installment plan and payments if exists
    if ($plan) {
        execute("DELETE FROM installment_payments WHERE plan_id = ?", [$plan['id']]);
        execute("DELETE FROM installment_plans WHERE id = ?", [$plan['id']]);
    }
    
    // 5. Delete invoice items and invoice
    execute("DELETE FROM invoice_items WHERE invoice_id = ?", [$id]);
    execute("DELETE FROM invoices WHERE id = ?", [$id]);
    
    $typeText = $invoice['type'] === 'sale' ? 'بيع' : 'شراء';
    logActivity('حذف فاتورة', "تم حذف فاتورة {$typeText} رقم {$invoice['invoice_number']}");
    
    commit();
    if (!empty($isDraftPurchase)) {
        setSuccess('تم حذف المسودة بنجاح (لم تكن قد حرّكت المخزون)');
    } else {
        setSuccess('تم حذف الفاتورة بنجاح واسترجاع الأرصدة والمخزون');
    }
    redirect('list.php');
    
} catch (Exception $e) {
    rollback();
    setError('حدث خطأ أثناء مسح الفاتورة: ' . $e->getMessage());
    redirect('list.php');
}
