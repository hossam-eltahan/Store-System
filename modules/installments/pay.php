<?php
/**
 * Pay Installment - دفع قسط
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';

$pageTitle = 'دفع قسط';
$planId = $_GET['plan_id'] ?? 0;

$plan = getRow("SELECT * FROM installment_plans WHERE id = ?", [$planId]);
if (!$plan || $plan['status'] !== 'active') {
    setError('خطة التقسيط غير موجودة أو غير نشطة');
    redirect('index.php');
}

// Get pending payments (including partial)
$pendingPayments = getRows(
    "SELECT * FROM installment_payments WHERE plan_id = ? AND status IN ('pending', 'overdue', 'partial') ORDER BY installment_number",
    [$planId]
);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        beginTransaction();
        
        $paymentAmount = floatval($_POST['payment_amount']);
        $paymentMethod = sanitize($_POST['payment_method'] ?? 'كاش');
        $handledBy = sanitize($_POST['handled_by'] ?? 'المدير');
        $notes = sanitize($_POST['notes'] ?? '');
        
        if ($paymentAmount <= 0) {
            throw new Exception('المبلغ يجب أن يكون أكبر من صفر');
        }
        
        if ($paymentAmount > $plan['remaining_amount']) {
            throw new Exception('المبلغ أكبر من المتبقي');
        }
        
        $remainingPayment = $paymentAmount;
        $paidInstallments = [];
        
        // Apply payment to installments in order
        foreach ($pendingPayments as $payment) {
            if ($remainingPayment <= 0) break;
            
            if ($remainingPayment >= $payment['amount']) {
                // Full payment for this installment
                execute(
                    "UPDATE installment_payments SET status = 'paid', paid_date = CURDATE(), payment_method = ?, handled_by = ?, notes = ? WHERE id = ?",
                    [$paymentMethod, $handledBy, $notes, $payment['id']]
                );
                $remainingPayment -= $payment['amount'];
                $paidInstallments[] = $payment['installment_number'];
            } else {
                // Partial payment - reduce the installment amount and mark as partial
                $newAmount = $payment['amount'] - $remainingPayment;
                
                // Update this installment with remaining amount and mark as partial
                execute(
                    "UPDATE installment_payments SET amount = ?, status = 'partial', paid_amount = ?, notes = CONCAT(IFNULL(notes, ''), ' - دفعة جزئية: ', ?) WHERE id = ?",
                    [$newAmount, $remainingPayment, $remainingPayment, $payment['id']]
                );
                
                $paidInstallments[] = $payment['installment_number'] . ' (جزئي)';
                $remainingPayment = 0;
            }
        }
        
        // Update plan totals
        $newPaidAmount = $plan['paid_amount'] + $paymentAmount;
        $newRemainingAmount = $plan['total_amount'] - $newPaidAmount;
        $newStatus = $newRemainingAmount <= 0 ? 'completed' : 'active';
        
        execute(
            "UPDATE installment_plans SET paid_amount = ?, remaining_amount = ?, status = ? WHERE id = ?",
            [$newPaidAmount, max(0, $newRemainingAmount), $newStatus, $planId]
        );
        
        // Update entity balance
        if ($plan['type'] === 'customer') {
            execute(
                "UPDATE customers SET balance = balance + ? WHERE id = ?",
                [$paymentAmount, $plan['entity_id']]
            );
        } else {
            execute(
                "UPDATE suppliers SET balance = balance - ? WHERE id = ?",
                [$paymentAmount, $plan['entity_id']]
            );
        }
        
        $typeText = $plan['type'] === 'customer' ? 'العميل' : 'المورد';
        logActivity('دفع قسط', "تم دفع {$paymentAmount} من أقساط {$typeText} {$plan['entity_name']}", $handledBy);
        
        commit();
        
        // Redirect to print
        header("Location: print_payment.php?plan_id=$planId&amount=$paymentAmount&installments=" . implode(',', $paidInstallments));
        exit;
        
    } catch (Exception $e) {
        rollback();
        setError($e->getMessage());
    }
}

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<style>
.payment-summary {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    color: white;
    padding: 12px;
    border-radius: 10px;
    margin-bottom: 10px;
}
.payment-summary .entity-label { font-size: 0.8em; opacity: 0.9; }
.payment-summary .entity-name { font-size: 1.2em; font-weight: bold; }
.payment-summary .remaining-value { font-size: 1.5em; font-weight: bold; }
.quick-btn {
    padding: 6px 12px;
    border: 1px solid #8b5cf6;
    border-radius: 15px;
    background: white;
    color: #7c3aed;
    cursor: pointer;
    font-size: 0.8em;
    transition: all 0.3s;
}
.quick-btn:hover {
    background: #8b5cf6;
    color: white;
}

.container {
    padding: 0px !important;
}
.card {
    margin-bottom: 0px !important;
    border-radius: 6px !important;
}
.card-header {
    padding: 6px 10px !important;
    font-size: 0.9em;
}
.card-body {
    padding: 8px 10px !important;
}
.form-group {
    margin-bottom: 8px !important;
}
.form-label {
    font-size: 0.85em;
    margin-bottom: 3px;
}
.form-control {
    padding: 5px 8px !important;
    font-size: 0.85em !important;
}
.form-row {
    gap: 10px !important;
    margin-bottom: 8px !important;
}
.btn-lg {
    padding: 8px 20px !important;
    font-size: 0.9em !important;
}
.btn-secondary {
    padding: 4px 10px !important;
    font-size: 0.8em !important;
}
textarea.form-control {
    min-height: 35px !important;
    resize: none;
}
#paymentAmount {
    font-size: 1.2em !important;
}
</style>

<div class="container">
    <div class="card">
        <div class="card-header d-flex justify-between align-center" style="background: linear-gradient(135deg, #10b981, #059669); color: white;">
            <span>💵 دفع قسط</span>
            <a href="view.php?id=<?php echo $planId; ?>" class="btn btn-secondary">← رجوع</a>
        </div>
        
        <div class="card-body">
            <!-- Plan Summary -->
            <div class="payment-summary">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-size: 0.9em; opacity: 0.9;">
                            <?php echo $plan['type'] === 'customer' ? '👤 العميل' : '🚚 المورد'; ?>
                        </div>
                        <div style="font-size: 1.5em; font-weight: bold;"><?php echo $plan['entity_name']; ?></div>
                    </div>
                    <div style="text-align: left;">
                        <div style="font-size: 0.9em; opacity: 0.9;">المتبقي</div>
                        <div style="font-size: 2em; font-weight: bold;"><?php echo number_format($plan['remaining_amount'], 2); ?></div>
                        <div style="font-size: 0.85em;">من <?php echo number_format($plan['total_amount'], 2); ?> جنيه</div>
                    </div>
                </div>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label class="form-label required">💰 المبلغ المدفوع</label>
                    <input type="number" name="payment_amount" id="paymentAmount" class="form-control" 
                           step="0.01" min="1" max="<?php echo $plan['remaining_amount']; ?>" required
                           style="font-size: 1.5em; text-align: center; border: 2px solid #10b981;">
                    <small style="color: #6b7280;">الحد الأقصى: <?php echo number_format($plan['remaining_amount'], 2); ?> جنيه</small>
                </div>
                
                <!-- Quick Buttons -->
                <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px;">
                    <?php 
                    $oneInstallment = $pendingPayments[0]['amount'] ?? 0;
                    $twoInstallments = ($pendingPayments[0]['amount'] ?? 0) + ($pendingPayments[1]['amount'] ?? 0);
                    $allRemaining = $plan['remaining_amount'];
                    ?>
                    <button type="button" class="quick-btn" onclick="setAmount(<?php echo $oneInstallment; ?>)">
                        قسط واحد (<?php echo number_format($oneInstallment, 2); ?>)
                    </button>
                    <?php if (count($pendingPayments) >= 2): ?>
                    <button type="button" class="quick-btn" onclick="setAmount(<?php echo $twoInstallments; ?>)">
                        قسطين (<?php echo number_format($twoInstallments, 2); ?>)
                    </button>
                    <?php endif; ?>
                    <button type="button" class="quick-btn" onclick="setAmount(<?php echo $allRemaining; ?>)">
                        كل المتبقي (<?php echo number_format($allRemaining, 2); ?>)
                    </button>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">طريقة الدفع</label>
                        <select name="payment_method" class="form-control">
                            <option value="كاش">كاش</option>
                            <option value="تحويل بنكي">تحويل بنكي</option>
                            <option value="فودافون كاش">فودافون كاش</option>
                            <option value="انستاباي">انستاباي</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">المسؤول</label>
                        <input type="text" name="handled_by" class="form-control" value="المدير">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">ملاحظات</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
                
                <button type="submit" class="btn btn-success btn-lg" style="width: 100%;">
                    💾 دفع وطباعة الإيصال
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function setAmount(amount) {
    document.getElementById('paymentAmount').value = amount;
}
</script>

<?php include '../../includes/footer.php'; ?>
