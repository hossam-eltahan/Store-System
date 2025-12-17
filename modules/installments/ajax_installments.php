<?php
/**
 * AJAX - Get Customer Installments
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';

$customerId = $_GET['customer_id'] ?? 0;

if (!$customerId) {
    echo '<div class="alert alert-danger">خطأ في البيانات</div>';
    exit;
}

$customer = getRow("SELECT * FROM customers WHERE id = ?", [$customerId]);
$installments = getRows(
    "SELECT i.*, inv.invoice_number 
     FROM installments i 
     JOIN invoices inv ON i.invoice_id = inv.id 
     WHERE i.customer_id = ? AND i.status = 'active'
     ORDER BY i.created_at DESC",
    [$customerId]
);

if (empty($installments)) {
    echo '<div class="alert alert-success">لا توجد أقساط نشطة لهذا العميل</div>';
    exit;
}
?>

<?php foreach ($installments as $inst): ?>
<div class="installment-card">
    <h4>
        <span class="badge badge-info">فاتورة: <?php echo $inst['invoice_number']; ?></span>
    </h4>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
        <div><strong>المبلغ الكلي:</strong> <?php echo formatCurrency($inst['total_amount']); ?></div>
        <div><strong>المقدم:</strong> <?php echo formatCurrency($inst['down_payment']); ?></div>
        <div><strong>المدفوع:</strong> <span class="text-success"><?php echo formatCurrency($inst['paid_amount']); ?></span></div>
        <div><strong>المتبقي:</strong> <span class="text-danger"><?php echo formatCurrency($inst['remaining_amount']); ?></span></div>
        <div><strong>عدد الأقساط:</strong> <?php echo $inst['num_installments']; ?></div>
        <div><strong>قيمة القسط:</strong> <?php echo formatCurrency($inst['installment_amount']); ?></div>
    </div>
    
    <form method="POST" action="customers.php" style="background: #fff; padding: 15px; border-radius: 8px;">
        <input type="hidden" name="customer_id" value="<?php echo $customerId; ?>">
        <input type="hidden" name="installment_id" value="<?php echo $inst['id']; ?>">
        
        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 10px; margin-bottom: 10px;">
            <label style="font-weight: bold; display: flex; align-items: center;">المبلغ المدفوع:</label>
            <input type="number" step="0.01" name="amount" class="form-control" 
                   value="<?php echo $inst['installment_amount']; ?>" 
                   max="<?php echo $inst['remaining_amount']; ?>" required>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 10px; margin-bottom: 10px;">
            <label style="font-weight: bold; display: flex; align-items: center;">ملاحظات:</label>
            <input type="text" name="notes" class="form-control" placeholder="اختياري">
        </div>
        
        <button type="submit" name="quick_pay" class="btn btn-success btn-block">
            💰 تسجيل دفعة
        </button>
        
        <div style="margin-top: 10px;">
            <a href="payment.php?id=<?php echo $inst['id']; ?>" class="btn btn-primary btn-sm btn-block">
                📋 تفاصيل جدول الأقساط
            </a>
        </div>
    </form>
</div>
<?php endforeach; ?>
