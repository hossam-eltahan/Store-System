<?php
/**
 * View Installment Plan - عرض خطة التقسيط
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';

$id = $_GET['id'] ?? 0;
$plan = getRow("SELECT * FROM installment_plans WHERE id = ?", [$id]);

if (!$plan) {
    setError('خطة التقسيط غير موجودة');
    redirect('index.php');
}

$pageTitle = 'خطة تقسيط: ' . $plan['entity_name'];

// Get entity info
if ($plan['type'] === 'customer') {
    $entity = getRow("SELECT * FROM customers WHERE id = ?", [$plan['entity_id']]);
    $entityLabel = 'العميل';
} else {
    $entity = getRow("SELECT * FROM suppliers WHERE id = ?", [$plan['entity_id']]);
    $entityLabel = 'المورد';
}

// Get all payments
$payments = getRows(
    "SELECT * FROM installment_payments WHERE plan_id = ? ORDER BY installment_number",
    [$id]
);

// If no payments found, create them
if (empty($payments)) {
    $numberOfInstallments = $plan['number_of_installments'];
    $installmentAmount = $plan['installment_amount'];
    $totalAmount = $plan['total_amount'];
    
    $currentDate = new DateTime($plan['start_date']);
    
    for ($i = 1; $i <= $numberOfInstallments; $i++) {
        $paymentNumber = 'INS-' . str_pad($plan['id'], 5, '0', STR_PAD_LEFT) . '-' . $i;
        $amount = ($i == $numberOfInstallments) ? 
            ($totalAmount - ($installmentAmount * ($numberOfInstallments - 1))) : 
            $installmentAmount;
        
        $status = ($currentDate < new DateTime()) ? 'overdue' : 'pending';
        
        if ($plan['paid_amount'] >= ($installmentAmount * $i)) {
            $status = 'paid';
        }
        
        insert(
            "INSERT INTO installment_payments (plan_id, payment_number, installment_number, amount, due_date, status)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$plan['id'], $paymentNumber, $i, $amount, $currentDate->format('Y-m-d'), $status]
        );
        
        $currentDate->modify('+1 month');
    }
    
    $payments = getRows(
        "SELECT * FROM installment_payments WHERE plan_id = ? ORDER BY installment_number",
        [$id]
    );
}

// Update overdue status
execute("
    UPDATE installment_payments 
    SET status = 'overdue' 
    WHERE status = 'pending' AND due_date < CURDATE() AND plan_id = ?
", [$id]);

// Reload after overdue update
$payments = getRows(
    "SELECT * FROM installment_payments WHERE plan_id = ? ORDER BY installment_number",
    [$id]
);

// Calculate stats
$paidCount = 0;
$pendingCount = 0;
$overdueCount = 0;
$partialCount = 0;
foreach ($payments as $p) {
    if ($p['status'] === 'paid') $paidCount++;
    elseif ($p['status'] === 'partial') $partialCount++;
    elseif ($p['status'] === 'pending') $pendingCount++;
    elseif ($p['status'] === 'overdue') $overdueCount++;
}

$progress = $plan['total_amount'] > 0 ? ($plan['paid_amount'] / $plan['total_amount']) * 100 : 0;

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<style>
.stat-card {
    padding: 12px;
    border-radius: 8px;
    text-align: center;
    transition: transform 0.3s;
}
.stat-card:hover { transform: translateY(-2px); }
.stat-card .icon { font-size: 1.3em; margin-bottom: 4px; }
.stat-card .label { font-size: 0.75em; color: #6b7280; margin-bottom: 2px; }
.stat-card .value { font-size: 1.1em; font-weight: bold; }
.stat-card.purple { background: linear-gradient(135deg, #ede9fe, #ddd6fe); }
.stat-card.purple .value { color: #7c3aed; }
.stat-card.blue { background: linear-gradient(135deg, #dbeafe, #bfdbfe); }
.stat-card.blue .value { color: #2563eb; }
.stat-card.green { background: linear-gradient(135deg, #d1fae5, #a7f3d0); }
.stat-card.green .value { color: #059669; }
.stat-card.yellow { background: linear-gradient(135deg, #fef3c7, #fde68a); }
.stat-card.yellow .value { color: #d97706; }

.progress-container {
    background: #f3f4f6;
    border-radius: 8px;
    padding: 10px;
    margin: 10px 0;
}
.progress-bar {
    height: 10px;
    background: #e5e7eb;
    border-radius: 5px;
    overflow: hidden;
}
.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #10b981, #059669);
    transition: width 0.5s ease;
}
.progress-stats {
    display: flex;
    justify-content: space-between;
    margin-top: 8px;
    font-size: 0.8em;
}
.progress-stats .stat { text-align: center; }
.progress-stats .stat .num { font-weight: bold; font-size: 1.1em; }
.progress-stats .stat.paid { color: #10b981; }
.progress-stats .stat.pending { color: #f59e0b; }
.progress-stats .stat.overdue { color: #dc2626; }

.payment-list {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.payment-item {
    display: flex;
    align-items: center;
    padding: 8px 10px;
    border-radius: 8px;
    background: white;
    border: 1px solid #e5e7eb;
    transition: all 0.3s;
    font-size: 0.85em;
}
.payment-item:hover { box-shadow: 0 2px 6px rgba(0,0,0,0.08); }
.payment-item.paid { border-color: #10b981; background: #f0fdf4; }
.payment-item.partial { border-color: #8b5cf6; background: #f5f3ff; }
.payment-item.pending { border-color: #f59e0b; background: #fffbeb; }
.payment-item.overdue { border-color: #dc2626; background: #fef2f2; }

.payment-number {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 0.9em;
    margin-left: 10px;
    flex-shrink: 0;
}
.payment-number.paid { background: #10b981; color: white; }
.payment-number.partial { background: #8b5cf6; color: white; }
.payment-number.pending { background: #f59e0b; color: white; }
.payment-number.overdue { background: #dc2626; color: white; }

.payment-details { flex: 1; }
.payment-amount { font-size: 1em; font-weight: bold; color: #1f2937; }
.payment-dates { font-size: 0.8em; color: #6b7280; margin-top: 2px; }

.payment-status { text-align: left; }
.status-badge {
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 0.75em;
    font-weight: bold;
}
.status-badge.paid { background: #d1fae5; color: #059669; }
.status-badge.partial { background: #ede9fe; color: #7c3aed; }
.status-badge.pending { background: #fef3c7; color: #d97706; }
.status-badge.overdue { background: #fee2e2; color: #dc2626; }

.entity-info {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
    border-radius: 8px;
    margin-bottom: 10px;
}
.entity-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2em;
}
.entity-details h3 { margin: 0 0 2px; color: #1e40af; font-size: 1em; }
.entity-details p { margin: 0; color: #6b7280; font-size: 0.8em; }

.container {
    padding: 0px !important;
}
.card {
    margin-bottom: 8px !important;
    border-radius: 6px !important;
}
.card-header {
    padding: 6px 10px !important;
    font-size: 0.9em;
}
.card-body {
    padding: 8px !important;
}
.grid-4 {
    gap: 8px !important;
    margin-bottom: 10px !important;
}
.btn {
    padding: 4px 10px !important;
    font-size: 0.8em !important;
}
</style>

<div class="container">
    <!-- Plan Header -->
    <div class="card">
        <div class="card-header d-flex justify-between align-center" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white;">
            <span>📋 خطة التقسيط</span>
            <div style="display: flex; gap: 10px;">
                <?php if ($plan['status'] === 'active'): ?>
                <a href="pay.php?plan_id=<?php echo $plan['id']; ?>" class="btn" style="background: #10b981; color: white;">💵 دفع قسط</a>
                <?php endif; ?>
                <a href="print_payment.php?plan_id=<?php echo $plan['id']; ?>&amount=0" class="btn" style="background: white; color: #7c3aed;">🖨️ طباعة</a>
                <a href="index.php" class="btn btn-secondary">← رجوع</a>
            </div>
        </div>
        
        <div class="card-body">
            <!-- Entity Info -->
            <div class="entity-info">
                <div class="entity-avatar">
                    <?php echo $plan['type'] === 'customer' ? '👤' : '🚚'; ?>
                </div>
                <div class="entity-details">
                    <h3><?php echo $plan['entity_name']; ?></h3>
                    <p>📱 <?php echo $entity['phone'] ?? 'بدون رقم'; ?></p>
                </div>
                <div style="margin-right: auto; text-align: left;">
                    <span style="font-size: 0.85em; color: #6b7280;">الحالة</span>
                    <div style="font-weight: bold; color: <?php echo $plan['status'] === 'completed' ? '#10b981' : ($overdueCount > 0 ? '#dc2626' : '#f59e0b'); ?>;">
                        <?php 
                        if ($plan['status'] === 'completed') echo '✓ مكتمل';
                        elseif ($overdueCount > 0) echo '⏰ يوجد متأخرات';
                        else echo '⏳ نشط';
                        ?>
                    </div>
                </div>
            </div>
            
            <!-- Stats Grid -->
            <div class="grid grid-4" style="gap: 15px; margin-bottom: 20px;">
                <div class="stat-card purple">
                    <div class="icon">📋</div>
                    <div class="label">عدد الأقساط</div>
                    <div class="value"><?php echo $plan['number_of_installments']; ?></div>
                </div>
                <div class="stat-card blue">
                    <div class="icon">💰</div>
                    <div class="label">إجمالي المبلغ</div>
                    <div class="value"><?php echo number_format($plan['total_amount'], 2); ?></div>
                </div>
                <div class="stat-card green">
                    <div class="icon">✅</div>
                    <div class="label">المدفوع</div>
                    <div class="value"><?php echo number_format($plan['paid_amount'], 2); ?></div>
                </div>
                <div class="stat-card yellow">
                    <div class="icon">⏳</div>
                    <div class="label">المتبقي</div>
                    <div class="value"><?php echo number_format($plan['remaining_amount'], 2); ?></div>
                </div>
            </div>
            
            <!-- Progress -->
            <div class="progress-container">
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <span style="font-weight: bold;">التقدم في السداد</span>
                    <span style="font-weight: bold; color: #10b981;"><?php echo round($progress); ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $progress; ?>%;"></div>
                </div>
                <div class="progress-stats">
                    <div class="stat paid">
                        <div class="num"><?php echo $paidCount; ?></div>
                        <div>مدفوع</div>
                    </div>
                    <div class="stat pending">
                        <div class="num"><?php echo $pendingCount; ?></div>
                        <div>في الانتظار</div>
                    </div>
                    <?php if ($partialCount > 0): ?>
                    <div class="stat" style="background: #f5f3ff; color: #7c3aed;">
                        <div class="num"><?php echo $partialCount; ?></div>
                        <div>جزئي</div>
                    </div>
                    <?php endif; ?>
                    <div class="stat overdue">
                        <div class="num"><?php echo $overdueCount; ?></div>
                        <div>متأخر</div>
                    </div>
                </div>
            </div>
            
            <!-- Plan Info -->
            <div style="display: flex; gap: 20px; margin-bottom: 20px; padding: 15px; background: #f9fafb; border-radius: 10px; font-size: 0.9em;">
                <div>📅 <strong>تاريخ البدء:</strong> <?php echo date('Y/m/d', strtotime($plan['start_date'])); ?></div>
                <div>💵 <strong>قيمة القسط:</strong> <?php echo number_format($plan['installment_amount'], 2); ?> جنيه</div>
                <div>📆 <strong>تاريخ الإنشاء:</strong> <?php echo date('Y/m/d', strtotime($plan['created_at'])); ?></div>
            </div>
            
            <?php if ($plan['notes']): ?>
            <div style="padding: 15px; background: #fef3c7; border-radius: 10px; margin-bottom: 20px;">
                <strong>📝 ملاحظات:</strong> <?php echo $plan['notes']; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Payments List -->
    <div class="card">
        <div class="card-header" style="background: linear-gradient(135deg, #f3f4f6, #e5e7eb);">
            📋 تفاصيل الأقساط
        </div>
        <div class="card-body">
            <div class="payment-list">
                <?php foreach ($payments as $payment): ?>
                <div class="payment-item <?php echo $payment['status']; ?>">
                    <div class="payment-number <?php echo $payment['status']; ?>">
                        <?php echo $payment['installment_number']; ?>
                    </div>
                    <div class="payment-details">
                        <div class="payment-amount">
                            <?php echo number_format($payment['amount'], 2); ?> جنيه
                        </div>
                        <div class="payment-dates">
                            📅 موعد الاستحقاق: <?php echo date('Y/m/d', strtotime($payment['due_date'])); ?>
                            <?php if ($payment['paid_date']): ?>
                            &nbsp;|&nbsp; ✅ تم الدفع: <?php echo date('Y/m/d', strtotime($payment['paid_date'])); ?>
                            <?php endif; ?>
                            <?php if ($payment['notes']): ?>
                            &nbsp;|&nbsp; 📝 <?php echo $payment['notes']; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="payment-status">
                        <span class="status-badge <?php echo $payment['status']; ?>">
                            <?php 
                            if ($payment['status'] === 'paid') echo '✓ مدفوع';
                            elseif ($payment['status'] === 'partial') echo '⚡ جزئي (مدفوع: ' . number_format($payment['paid_amount'], 2) . ')';
                            elseif ($payment['status'] === 'pending') echo '⏳ في الانتظار';
                            else echo '⏰ متأخر';
                            ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
