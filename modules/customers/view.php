<?php
/**
 * Customer Details View
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';

$id = $_GET['id'] ?? 0;
$customer = getRow("SELECT * FROM customers WHERE id = ?", [$id]);

if (!$customer) {
    setError('العميل غير موجود');
    redirect('index.php');
}

$pageTitle = 'تفاصيل العميل: ' . $customer['name'];
$settings = getAllSettings();

// Get customer invoices
$invoices = getRows(
    "SELECT * FROM invoices WHERE customer_id = ? ORDER BY created_at DESC",
    [$id]
);

// Get customer installment plans (from new table)
$installments = getRows(
    "SELECT ip.*, 
            (SELECT COUNT(*) FROM installment_payments WHERE plan_id = ip.id AND status = 'paid') as paid_count,
            (SELECT COUNT(*) FROM installment_payments WHERE plan_id = ip.id AND status = 'overdue') as overdue_count,
            (SELECT MIN(due_date) FROM installment_payments WHERE plan_id = ip.id AND status IN ('pending', 'overdue')) as next_due
     FROM installment_plans ip
     WHERE ip.type = 'customer' AND ip.entity_id = ? 
     ORDER BY ip.created_at DESC",
    [$id]
);

// Calculate totals
$totalPurchases = 0;
$totalPaid = 0;
$totalRemaining = 0;

foreach ($invoices as $inv) {
    $totalPurchases += $inv['total_amount'];
    $totalPaid += $inv['paid_amount'];
    $totalRemaining += $inv['remaining_amount'];
}

// Calculate installments
$activeInstallments = 0;
$totalInstallmentAmount = 0;
$totalInstallmentPaid = 0;
$totalInstallmentRemaining = 0;

foreach ($installments as $inst) {
    if ($inst['status'] === 'active') {
        $activeInstallments++;
    }
    $totalInstallmentAmount += $inst['total_amount'];
    $totalInstallmentPaid += $inst['paid_amount'];
    $totalInstallmentRemaining += $inst['remaining_amount'];
}

// Get customer returns
$returns = getRows(
    "SELECT r.*, i.invoice_number 
     FROM returns r 
     LEFT JOIN invoices i ON r.original_invoice_id = i.id 
     WHERE r.customer_id = ? 
     ORDER BY r.created_at DESC",
    [$id]
);

$totalReturns = 0;
foreach ($returns as $ret) {
    $totalReturns += $ret['total_amount'];
}

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container">
    <!-- Customer Info Card -->
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>👤 تفاصيل العميل</span>
            <div>
                <?php if ($customer['balance'] < 0): ?>
                <a href="../payments/pay_customer.php?customer_id=<?php echo $customer['id']; ?>" class="btn btn-warning">💵 تحصيل مستحقات</a>
                <?php endif; ?>
                <a href="../reports/statement.php?type=customer&id=<?php echo $customer['id']; ?>" class="btn btn-info">🖨️ كشف حساب</a>
                <a href="edit.php?id=<?php echo $customer['id']; ?>" class="btn btn-primary">✏️ تعديل</a>
                <a href="index.php" class="btn btn-secondary">↩️ رجوع</a>
            </div>
        </div>
        <div class="card-body">
            <div class="grid grid-2" style="gap: 30px;">
                <!-- Basic Info -->
                <div>
                    <h3 style="margin-bottom: 20px; color: #333;">📋 معلومات أساسية</h3>
                    <table class="table" style="background: #f8f9fa;">
                        <tr>
                            <td style="width: 40%; font-weight: bold;">اسم العميل</td>
                            <td style="font-size: 1.2em;"><?php echo $customer['name']; ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold;">التليفون</td>
                            <td>
                                <a href="tel:<?php echo $customer['phone']; ?>" style="color: #2563eb;">
                                    📞 <?php echo $customer['phone']; ?>
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold;">العنوان</td>
                            <td><?php echo ($customer['address'] ?? '') ?: 'غير محدد'; ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold;">ملاحظات</td>
                            <td><?php echo ($customer['notes'] ?? '') ?: 'لا توجد ملاحظات'; ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold;">تاريخ التسجيل</td>
                            <td><?php echo date('Y/m/d', strtotime($customer['created_at'])); ?></td>
                        </tr>
                    </table>
                </div>
                
                <!-- Financial Info -->
                <div>
                    <h3 style="margin-bottom: 20px; color: #333;">💰 معلومات مالية</h3>
                    <div class="stats-grid" style="grid-template-columns: 1fr;">
                        <div class="stat-card info">
                            <div class="stat-label">إجمالي المشتريات</div>
                            <div class="stat-value"><?php echo formatCurrency($totalPurchases); ?></div>
                            <small><?php echo count($invoices); ?> فاتورة</small>
                        </div>
                        
                        <div class="stat-card success">
                            <div class="stat-label">إجمالي المدفوع</div>
                            <div class="stat-value"><?php echo formatCurrency($totalPaid); ?></div>
                        </div>
                        
                        <div class="stat-card <?php echo $customer['balance'] < 0 ? 'danger' : 'success'; ?>">
                            <div class="stat-label">الرصيد الحالي</div>
                            <div class="stat-value">
                                <?php if ($customer['balance'] < 0): ?>
                                    <?php echo formatCurrency(abs($customer['balance'])); ?> (مدين)
                                <?php elseif ($customer['balance'] > 0): ?>
                                    <?php echo formatCurrency($customer['balance']); ?> (دائن)
                                <?php else: ?>
                                    0.00
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($activeInstallments > 0): ?>
                        <div class="stat-card warning">
                            <div class="stat-label">أقساط نشطة</div>
                            <div class="stat-value"><?php echo $activeInstallments; ?></div>
                            <small>متبقي: <?php echo formatCurrency($totalInstallmentRemaining); ?></small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Installments Card -->
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>📅 الأقساط (<?php echo count($installments); ?>)</span>
            <div>
                <?php if ($customer['balance'] < 0): ?>
                <a href="../installments/create.php?type=customer&entity_id=<?php echo $customer['id']; ?>" class="btn btn-info">➕ إنشاء قسط</a>
                <?php endif; ?>
                <?php if (!empty($installments)): ?>
                <a href="../installments/index.php?filter=customer" class="btn btn-primary">📋 عرض الكل</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($installments)): ?>
            <div class="alert alert-info">لا توجد خطط تقسيط لهذا العميل</div>
            <?php else: ?>
            <?php foreach ($installments as $installment): 
                $hasOverdue = $installment['overdue_count'] > 0;
                $progress = $installment['total_amount'] > 0 ? (($installment['paid_amount'] / $installment['total_amount']) * 100) : 0;
            ?>
            <div style="background: <?php echo $hasOverdue ? '#fef2f2' : ($installment['status'] === 'active' ? '#fff7ed' : '#f0fdf4'); ?>; border: 1px solid <?php echo $hasOverdue ? '#fecaca' : ($installment['status'] === 'active' ? '#fed7aa' : '#bbf7d0'); ?>; border-radius: 10px; padding: 20px; margin-bottom: 15px;">
                <div class="d-flex justify-between align-center" style="margin-bottom: 15px;">
                    <h4 style="margin: 0;">
                        <span class="badge <?php echo $installment['status'] === 'active' ? ($hasOverdue ? 'badge-danger' : 'badge-warning') : 'badge-success'; ?>">
                            <?php echo $hasOverdue ? 'متأخر' : ($installment['status'] === 'active' ? 'نشط' : 'مكتمل'); ?>
                        </span>
                        خطة تقسيط #<?php echo $installment['id']; ?>
                        <?php if ($installment['invoice_id']): ?>
                        <small style="color: #6b7280;">(فاتورة)</small>
                        <?php endif; ?>
                    </h4>
                    <?php if ($installment['status'] === 'active'): ?>
                    <a href="../installments/pay.php?plan_id=<?php echo $installment['id']; ?>" class="btn btn-success">💵 دفع</a>
                    <?php endif; ?>
                </div>
                
                <div class="grid grid-3" style="gap: 15px;">
                    <div style="background: white; padding: 15px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 0.85em; color: #666;">المبلغ الكلي</div>
                        <div style="font-size: 1.3em; font-weight: bold;"><?php echo formatCurrency($installment['total_amount']); ?></div>
                    </div>
                    <div style="background: white; padding: 15px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 0.85em; color: #666;">المدفوع</div>
                        <div style="font-size: 1.3em; font-weight: bold; color: #10b981;"><?php echo formatCurrency($installment['paid_amount']); ?></div>
                    </div>
                    <div style="background: white; padding: 15px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 0.85em; color: #666;">المتبقي</div>
                        <div style="font-size: 1.3em; font-weight: bold; color: #dc2626;"><?php echo formatCurrency($installment['remaining_amount']); ?></div>
                    </div>
                </div>
                
                <!-- Progress Bar -->
                <div style="margin-top: 15px;">
                    <div style="height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden;">
                        <div style="height: 100%; width: <?php echo $progress; ?>%; background: linear-gradient(90deg, #10b981, #059669);"></div>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-top: 5px; font-size: 0.85em; color: #6b7280;">
                        <span><?php echo $installment['number_of_installments']; ?> قسط × <?php echo formatCurrency($installment['installment_amount']); ?></span>
                        <span><?php echo round($progress); ?>% مكتمل</span>
                    </div>
                </div>
                
                <?php if ($installment['next_due']): ?>
                <div style="margin-top: 15px; padding: 10px; background: <?php echo $hasOverdue ? '#fee2e2' : '#fffbeb'; ?>; border-radius: 5px; text-align: center;">
                    <span style="color: <?php echo $hasOverdue ? '#dc2626' : '#92400e'; ?>;">
                        <?php echo $hasOverdue ? '⏰ متأخر من' : '📅 القسط القادم:'; ?>
                        <?php echo date('Y/m/d', strtotime($installment['next_due'])); ?>
                    </span>
                </div>
                <?php endif; ?>
                
                <div style="margin-top: 15px; text-align: center;">
                    <a href="../installments/view.php?id=<?php echo $installment['id']; ?>" class="btn btn-info btn-sm">📋 التفاصيل</a>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Returns Card -->
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>🔄 المرتجعات (<?php echo count($returns); ?>)</span>
            <a href="../returns/create.php" class="btn btn-warning">+ مرتجع جديد</a>
        </div>
        <div class="card-body">
            <?php if (empty($returns)): ?>
            <div class="alert alert-info">لا توجد مرتجعات مسجلة لهذا العميل</div>
            <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>رقم المرتجع</th>
                            <th>الفاتورة</th>
                            <th>التاريخ</th>
                            <th>المبلغ</th>
                            <th>طريقة الاسترداد</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($returns as $return): ?>
                        <tr>
                            <td><strong><?php echo $return['return_number']; ?></strong></td>
                            <td><?php echo $return['invoice_number']; ?></td>
                            <td><?php echo date('Y/m/d', strtotime($return['return_date'])); ?></td>
                            <td><?php echo formatCurrency($return['total_amount']); ?></td>
                            <td>
                                <?php if ($return['refund_method'] === 'deducted'): ?>
                                    <span class="badge badge-info">خصم من الحساب</span>
                                <?php else: ?>
                                    <span class="badge badge-success">كاش</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="../returns/view.php?id=<?php echo $return['id']; ?>" class="btn btn-info btn-sm">👁️</a>
                                <a href="../returns/print.php?id=<?php echo $return['id']; ?>" class="btn btn-primary btn-sm">🖨️</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Invoices Card -->
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>📜 فواتير العميل (<?php echo count($invoices); ?>)</span>
            <a href="../invoices/sale.php" class="btn btn-success">+ فاتورة بيع جديدة</a>
        </div>
        <div class="card-body">
            <?php if (empty($invoices)): ?>
            <div class="alert alert-info">لا توجد فواتير مسجلة لهذا العميل</div>
            <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>رقم الفاتورة</th>
                            <th>التاريخ</th>
                            <th>الإجمالي</th>
                            <th>المدفوع</th>
                            <th>الباقي</th>
                            <th>حالة الدفع</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $invoice): ?>
                        <tr>
                            <td><strong><?php echo $invoice['invoice_number']; ?></strong></td>
                            <td><?php echo date('Y/m/d', strtotime($invoice['date'])); ?></td>
                            <td><?php echo formatCurrency($invoice['total_amount']); ?></td>
                            <td><?php echo formatCurrency($invoice['paid_amount']); ?></td>
                            <td><?php echo formatCurrency($invoice['remaining_amount']); ?></td>
                            <td>
                                <?php
                                $statusClass = [
                                    'paid' => 'badge-success',
                                    'partial' => 'badge-warning',
                                    'unpaid' => 'badge-danger'
                                ];
                                $statusText = [
                                    'paid' => 'مدفوع',
                                    'partial' => 'جزئي',
                                    'unpaid' => 'غير مدفوع'
                                ];
                                ?>
                                <span class="badge <?php echo $statusClass[$invoice['payment_status']]; ?>">
                                    <?php echo $statusText[$invoice['payment_status']]; ?>
                                </span>
                            </td>
                            <td>
                                <a href="../invoices/print.php?id=<?php echo $invoice['id']; ?>" class="btn btn-primary btn-sm">🖨️</a>
                                <?php if (($settings['allow_edit_invoices'] ?? '0') === '1'): ?>
                                <a href="../invoices/edit_sale.php?id=<?php echo $invoice['id']; ?>" class="btn btn-info btn-sm" title="تعديل">✏️</a>
                                <?php endif; ?>
                                <?php if (($settings['allow_delete_invoices'] ?? '0') === '1'): ?>
                                <a href="../invoices/delete.php?id=<?php echo $invoice['id']; ?>" class="btn btn-danger btn-sm" title="مسح" onclick="return confirm('هل أنت متأكد من مسح هذه الفاتورة؟');">🗑️</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
