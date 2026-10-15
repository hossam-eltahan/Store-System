<?php
/**
 * Supplier Details View
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requirePermission('suppliers.view');

$id = $_GET['id'] ?? 0;
$supplier = getRow("SELECT * FROM suppliers WHERE id = ?", [$id]);

if (!$supplier) {
    setError('المورد غير موجود');
    redirect('index.php');
}

$pageTitle = 'تفاصيل المورد: ' . $supplier['name'];
$settings = getAllSettings();

// Get supplier invoices
$invoices = getRows(
    "SELECT * FROM invoices WHERE supplier_id = ? ORDER BY created_at DESC",
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

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container">
    <!-- Supplier Info Card -->
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>🚚 تفاصيل المورد</span>
            <div>
                <?php if ($supplier['balance'] > 0): ?>
                <a href="../payments/pay_supplier.php?supplier_id=<?php echo $supplier['id']; ?>" class="btn btn-success">💵 دفع مستحقات</a>
                <?php endif; ?>
                <?php if (hasPermission('reports.statement')): ?>
                <a href="../reports/statement.php?type=supplier&id=<?php echo $supplier['id']; ?>" class="btn btn-info">🖨️ كشف حساب</a>
                <?php endif; ?>
                <a href="edit.php?id=<?php echo $supplier['id']; ?>" class="btn btn-primary">✏️ تعديل</a>
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
                            <td style="width: 40%; font-weight: bold;">اسم المورد</td>
                            <td style="font-size: 1.2em;"><?php echo $supplier['name']; ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold;">التليفون</td>
                            <td>
                                <a href="tel:<?php echo $supplier['phone']; ?>" style="color: #2563eb;">
                                    📞 <?php echo $supplier['phone']; ?>
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold;">العنوان</td>
                            <td><?php echo ($supplier['address'] ?? '') ?: 'غير محدد'; ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold;">اسم الشركة</td>
                            <td><?php echo ($supplier['company_name'] ?? '') ?: 'غير محدد'; ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold;">أيام الزيارة</td>
                            <td>
                                <?php 
                                $days = json_decode($supplier['visit_days'] ?? '[]');
                                if ($days && is_array($days)) {
                                    foreach ($days as $day) {
                                        echo "<span class='badge badge-info' style='margin-left: 5px;'>$day</span>";
                                    }
                                } else {
                                    echo 'غير محدد';
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold;">البضاعة المتوقعة</td>
                            <td><?php echo ($supplier['expected_products'] ?? '') ?: 'غير محدد'; ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold;">ملاحظات</td>
                            <td><?php echo ($supplier['notes'] ?? '') ?: 'لا توجد ملاحظات'; ?></td>
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
                        
                        <div class="stat-card <?php echo $totalRemaining > 0 ? 'danger' : 'success'; ?>">
                            <div class="stat-label">المتبقي علينا للمورد</div>
                            <div class="stat-value"><?php echo formatCurrency($supplier['balance']); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Invoices Card -->
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>🧾 فواتير الشراء (<?php echo count($invoices); ?>)</span>
            <a href="../invoices/purchase.php?supplier_id=<?php echo $id; ?>" class="btn btn-success">+ فاتورة شراء جديدة</a>
        </div>
        <div class="card-body">
            <?php if (empty($invoices)): ?>
            <div class="alert alert-info">لا توجد فواتير مسجلة لهذا المورد</div>
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
                                <?php if (hasAnyPermission(['invoices.purchase.view', 'invoices.purchase.create'])): ?>
                                <a href="../invoices/print_purchase.php?id=<?php echo $invoice['id']; ?>" class="btn btn-primary btn-sm">🖨️</a>
                                <?php endif; ?>
                                <?php if (hasPermission('invoices.purchase.edit') && (($settings['allow_edit_invoices'] ?? '0') === '1')): ?>
                                <a href="../invoices/edit_purchase.php?id=<?php echo $invoice['id']; ?>" class="btn btn-info btn-sm" title="تعديل">✏️</a>
                                <?php endif; ?>
                                <?php if (hasPermission('invoices.purchase.delete') && (($settings['allow_delete_invoices'] ?? '0') === '1')): ?>
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
