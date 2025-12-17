<?php
/**
 * Installment Payments
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';

$pageTitle = 'سداد أقساط';

$id = $_GET['id'] ?? 0; // Installment ID
$installment = getRow(
    "SELECT i.*, c.name as customer_name, c.phone as customer_phone, inv.invoice_number
     FROM installments i
     JOIN customers c ON i.customer_id = c.id
     JOIN invoices inv ON i.invoice_id = inv.id
     WHERE i.id = ?",
    [$id]
);

if (!$installment) {
    setError('خطة التقسيط غير موجودة');
    redirect('index.php');
}

// Handle payment
if (isset($_POST['pay_installment'])) {
    $paymentId = $_POST['payment_id'];
    $amount = floatval($_POST['amount']);
    $notes = sanitize($_POST['notes']);
    
    try {
        beginTransaction();
        
        // Get payment info
        $payment = getRow("SELECT * FROM installment_payments WHERE id = ?", [$paymentId]);
        
        if ($payment && $payment['status'] != 'paid') {
            // Update payment record
            execute(
                "UPDATE installment_payments SET paid_amount = ?, paid_date = CURDATE(), status = 'paid', notes = ? WHERE id = ?",
                [$amount, $notes, $paymentId]
            );
            
            // Update installment remaining amount
            execute(
                "UPDATE installments SET remaining_amount = remaining_amount - ? WHERE id = ?",
                [$amount, $id]
            );
            
            // Update customer balance
            execute(
                "UPDATE customers SET balance = balance + ? WHERE id = ?",
                [$amount, $installment['customer_id']]
            );
            
            // Generate receipt (optional - implies logged activity)
            logActivity('سداد قسط', "تم سداد قسط بقيمة $amount للعميل {$installment['customer_name']}");
            
            // Check if all installments paid
            $remaining = getRow("SELECT COUNT(*) as count FROM installment_payments WHERE installment_id = ? AND status != 'paid'", [$id]);
            if ($remaining['count'] == 0) {
                execute("UPDATE installments SET status = 'completed' WHERE id = ?", [$id]);
            }
            
            commit();
            setSuccess('تم تسجيل الدفعة بنجاح');
            
            // Refresh updated data
            $installment = getRow("SELECT * FROM installments WHERE id = ?", [$id]);
        }
        
    } catch (Exception $e) {
        rollback();
        setError('حدث خطأ أثناء التسجيل: ' . $e->getMessage());
    }
}

// Get payments list
$payments = getRows("SELECT * FROM installment_payments WHERE installment_id = ? ORDER BY payment_number", [$id]);

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container">
    <div class="card mb-2">
        <div class="card-header">تفاصيل التقسيط - <?php echo $installment['customer_name']; ?></div>
        <div class="card-body">
            <div class="grid grid-3">
                <div>
                    <strong>رقم الفاتورة:</strong> <?php echo $installment['invoice_number']; ?>
                </div>
                <div>
                    <strong>المبلغ الكلي:</strong> <?php echo formatCurrency($installment['total_amount']); ?>
                </div>
                <div>
                    <strong>المتبقي:</strong> <span class="text-danger"><?php echo formatCurrency($installment['remaining_amount']); ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">جدول الأقساط</div>
        <div class="card-body">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>رقم القسط</th>
                            <th>تاريخ الاستحقاق</th>
                            <th>المبلغ المستحق</th>
                            <th>الحالة</th>
                            <th>تاريخ السداد</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?php echo $payment['payment_number']; ?></td>
                            <td><?php echo date('Y/m/d', strtotime($payment['due_date'])); ?></td>
                            <td><?php echo formatCurrency($payment['amount']); ?></td>
                            <td>
                                <?php if ($payment['status'] === 'paid'): ?>
                                    <span class="badge badge-success">مدفوع</span>
                                <?php elseif (strtotime($payment['due_date']) < time() && $payment['status'] === 'pending'): ?>
                                    <span class="badge badge-danger">متأخر</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">قيد الانتظار</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $payment['paid_date'] ? date('Y/m/d', strtotime($payment['paid_date'])) : '-'; ?>
                            </td>
                            <td>
                                <?php if ($payment['status'] !== 'paid'): ?>
                                <button type="button" class="btn btn-primary btn-sm" onclick="openPaymentModal(<?php echo $payment['id']; ?>, <?php echo $payment['amount']; ?>)">
                                    💰 دفع
                                </button>
                                <?php else: ?>
                                <span class="text-success">✓</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Simple Payment Modal -->
<div id="paymentModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
    <div class="card" style="width: 400px; max-width: 90%;">
        <div class="card-header">تسجيل دفعة</div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="payment_id" id="modalPaymentId">
                
                <div class="form-group">
                    <label class="form-label required">المبلغ المدفوع</label>
                    <input type="number" step="0.01" name="amount" id="modalAmount" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">ملاحظات</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" name="pay_installment" class="btn btn-success btn-block">تأكيد الدفع</button>
                    <button type="button" class="btn btn-secondary btn-block" onclick="document.getElementById('paymentModal').style.display='none'">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openPaymentModal(id, amount) {
    document.getElementById('modalPaymentId').value = id;
    document.getElementById('modalAmount').value = amount;
    document.getElementById('paymentModal').style.display = 'flex';
}
</script>

<?php include '../../includes/footer.php'; ?>
