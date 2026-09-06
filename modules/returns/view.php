<?php
/**
 * View Return Details
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requirePermission('returns.view');

$id = $_GET['id'] ?? 0;

$return = getRow(
    "SELECT r.*, i.invoice_number, c.name as customer_name_db
     FROM returns r
     LEFT JOIN invoices i ON r.original_invoice_id = i.id
     LEFT JOIN customers c ON r.customer_id = c.id
     WHERE r.id = ?",
    [$id]
);

if (!$return) {
    setError('المرتجع غير موجود');
    redirect('index.php');
}

$items = getRows("SELECT * FROM return_items WHERE return_id = ?", [$id]);

$pageTitle = 'تفاصيل المرتجع: ' . $return['return_number'];

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>🔄 تفاصيل المرتجع: <?php echo $return['return_number']; ?></span>
            <div>
                <a href="print.php?id=<?php echo $return['id']; ?>" class="btn btn-primary">🖨️ طباعة</a>
                <a href="index.php" class="btn btn-secondary">← رجوع</a>
            </div>
        </div>
        
        <div class="card-body">
            <!-- Return Info -->
            <div class="grid grid-2" style="gap: 30px; margin-bottom: 30px;">
                <div>
                    <h3 style="margin-bottom: 15px;">📋 معلومات المرتجع</h3>
                    <table class="table" style="background: #f9fafb;">
                        <tr>
                            <td style="width: 40%; font-weight: bold;">رقم المرتجع</td>
                            <td><strong><?php echo $return['return_number']; ?></strong></td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold;">الفاتورة الأصلية</td>
                            <td>
                                <a href="../invoices/print.php?id=<?php echo $return['original_invoice_id']; ?>">
                                    <?php echo $return['invoice_number']; ?>
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold;">العميل</td>
                            <td><?php echo $return['customer_name_db'] ?? $return['customer_name']; ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold;">تاريخ المرتجع</td>
                            <td><?php echo date('Y/m/d', strtotime($return['return_date'])); ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold;">المسؤول</td>
                            <td><?php echo $return['handled_by']; ?></td>
                        </tr>
                    </table>
                </div>
                
                <div>
                    <h3 style="margin-bottom: 15px;">💰 معلومات مالية</h3>
                    <div style="background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; padding: 20px; border-radius: 10px; text-align: center; margin-bottom: 15px;">
                        <div style="font-size: 0.9em;">إجمالي المرتجع</div>
                        <div style="font-size: 2em; font-weight: bold;"><?php echo formatCurrency($return['total_amount']); ?></div>
                    </div>
                    
                    <div style="background: <?php echo $return['refund_method'] === 'deducted' ? '#dbeafe' : '#d1fae5'; ?>; padding: 15px; border-radius: 10px; text-align: center;">
                        <div style="font-size: 0.9em;">طريقة الاسترداد</div>
                        <div style="font-size: 1.3em; font-weight: bold;">
                            <?php if ($return['refund_method'] === 'deducted'): ?>
                                📝 خصم من حساب العميل
                                <br><small>(تم خصم <?php echo formatCurrency($return['deducted_from_balance']); ?>)</small>
                            <?php else: ?>
                                💵 استلم العميل كاش
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Items -->
            <h3 style="margin-bottom: 15px;">📦 الأصناف المرتجعة</h3>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>م</th>
                            <th>كود الصنف</th>
                            <th>اسم الصنف</th>
                            <th>الوحدة</th>
                            <th>الكمية</th>
                            <th>سعر الوحدة</th>
                            <th>الإجمالي</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $num = 1; foreach ($items as $item): ?>
                        <tr>
                            <td><?php echo $num++; ?></td>
                            <td><?php echo $item['product_code']; ?></td>
                            <td><?php echo $item['product_name']; ?></td>
                            <td><?php echo $item['unit']; ?></td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td><?php echo formatCurrency($item['unit_price']); ?></td>
                            <td><strong><?php echo formatCurrency($item['total']); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($return['notes']): ?>
            <div style="margin-top: 20px; padding: 15px; background: #fef3c7; border-radius: 10px;">
                <strong>ملاحظات:</strong> <?php echo $return['notes']; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
