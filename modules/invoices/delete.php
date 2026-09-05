<?php
/**
 * Delete Invoice - حذف فاتورة
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';

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
    
    // 2. Reverse inventory
    $items = getRows("SELECT * FROM invoice_items WHERE invoice_id = ?", [$id]);
    foreach ($items as $item) {
        if ($invoice['type'] === 'sale') {
            execute("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?", [$item['quantity'], $item['product_id']]);
        } else {
            execute("UPDATE products SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE id = ?", [$item['quantity'], $item['product_id']]);
        }
    }
    
    // 3. Reverse entity balance
    if ($invoice['type'] === 'sale' && $invoice['customer_id']) {
        execute("UPDATE customers SET balance = balance + ? WHERE id = ?", [$invoice['remaining_amount'], $invoice['customer_id']]);
    } elseif ($invoice['type'] === 'purchase' && $invoice['supplier_id']) {
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
    setSuccess('تم حذف الفاتورة بنجاح واسترجاع الأرصدة والمخزون');
    redirect('list.php');
    
} catch (Exception $e) {
    rollback();
    setError('حدث خطأ أثناء مسح الفاتورة: ' . $e->getMessage());
    redirect('list.php');
}
