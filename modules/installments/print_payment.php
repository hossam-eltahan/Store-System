<?php
/**
 * Print Installment Payment Receipt - إيصال دفع قسط
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';

$planId = $_GET['plan_id'] ?? 0;
$paidAmount = floatval($_GET['amount'] ?? 0);

$plan = getRow("SELECT * FROM installment_plans WHERE id = ?", [$planId]);
if (!$plan) {
    die('خطة التقسيط غير موجودة');
}

// Get entity information
if ($plan['type'] === 'customer') {
    $entity = getRow("SELECT * FROM customers WHERE id = ?", [$plan['entity_id']]);
    $entityLabel = 'العميل';
} else {
    $entity = getRow("SELECT * FROM suppliers WHERE id = ?", [$plan['entity_id']]);
    $entityLabel = 'المورد';
}

// Get settings
$settings = getAllSettings();
$storeName = $settings['store_name'] ?? 'محل الأجهزة المنزلية';
$storeAddress = $settings['store_address'] ?? '';
$storePhone = $settings['store_phone'] ?? '';
$currency = $settings['currency'] ?? 'جنيه';

// Get next payment (including partial)
$nextPayment = getRow(
    "SELECT * FROM installment_payments WHERE plan_id = ? AND status IN ('pending', 'overdue', 'partial') ORDER BY installment_number LIMIT 1",
    [$planId]
);

$pageTitle = 'إيصال دفع قسط';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Cairo', 'Segoe UI', Arial, sans-serif;
            direction: rtl;
            font-size: 10pt;
            background: #f0f0f0;
        }
        
        .no-print {
            text-align: center;
            padding: 8px;
            background: #8b5cf6;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }
        
        .no-print button, .no-print a {
            padding: 6px 15px;
            font-size: 12px;
            cursor: pointer;
            border: none;
            border-radius: 4px;
            margin: 0 3px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-print { background: white; color: #8b5cf6; }
        .btn-close { background: #dc2626; color: white; }
        .btn-view { background: #10b981; color: white; }
        
        .receipt {
            width: 210mm;
            margin: 45px auto 10px;
            background: white;
            padding: 8mm;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
        }
        
        .header {
            text-align: center;
            padding-bottom: 8px;
            border-bottom: 2px solid #8b5cf6;
            margin-bottom: 10px;
        }
        
        .store-name {
            font-size: 16pt;
            font-weight: bold;
            color: #8b5cf6;
        }
        
        .receipt-type {
            font-size: 12pt;
            font-weight: bold;
            text-align: center;
            padding: 6px;
            background: linear-gradient(135deg, #ede9fe, #ddd6fe);
            border: 1px solid #8b5cf6;
            color: #5b21b6;
            margin-bottom: 10px;
        }
        
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        
        .info-table td {
            padding: 5px 8px;
            border: 1px solid #8b5cf6;
        }
        
        .info-table .label {
            font-weight: bold;
            background: #ede9fe;
            width: 30%;
        }
        
        .amount-box {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 12px;
            border-radius: 10px;
            text-align: center;
            margin: 10px 0;
        }
        
        .amount-box .title {
            font-size: 1em;
            margin-bottom: 5px;
        }
        
        .amount-box .amount {
            font-size: 2em;
            font-weight: bold;
        }
        
        .balance-section {
            display: flex;
            gap: 10px;
            margin: 10px 0;
        }
        
        .balance-box {
            flex: 1;
            padding: 8px;
            border-radius: 6px;
            text-align: center;
        }
        
        .balance-before {
            background: #dbeafe;
            border: 1px solid #3b82f6;
        }
        
        .balance-paid {
            background: #d1fae5;
            border: 1px solid #10b981;
        }
        
        .balance-remaining {
            background: #fef3c7;
            border: 1px solid #f59e0b;
        }
        
        .next-installment {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 6px;
            padding: 8px;
            text-align: center;
            margin: 10px 0;
        }
        
        .completed-badge {
            background: #d1fae5;
            border: 1px solid #10b981;
            border-radius: 6px;
            padding: 8px;
            text-align: center;
            margin: 10px 0;
        }
        
        .footer {
            text-align: center;
            padding-top: 10px;
            border-top: 1px dashed #8b5cf6;
            margin-top: 15px;
        }
        
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .receipt { 
                width: 100%;
                margin: 0; 
                padding: 5mm; 
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" class="btn-print">🖨️ طباعة</button>
        <a href="view.php?id=<?php echo $planId; ?>" class="btn-view">📋 عرض التفاصيل</a>
        <button onclick="goBack()" class="btn-close">❌ إغلاق</button>
    </div>
    
    <div class="receipt">
        <div class="header">
            <div class="store-name"><?php echo $storeName; ?></div>
            <?php if ($storeAddress): ?>
                <div>العنوان: <?php echo $storeAddress; ?></div>
            <?php endif; ?>
            <?php if ($storePhone): ?>
                <div>تليفون: <?php echo $storePhone; ?></div>
            <?php endif; ?>
        </div>
        
        <div class="receipt-type">📋 إيصال دفع قسط</div>
        
        <table class="info-table">
            <tr>
                <td class="label">رقم خطة التقسيط:</td>
                <td><strong><?php echo str_pad($plan['id'], 5, '0', STR_PAD_LEFT); ?></strong></td>
            </tr>
            <tr>
                <td class="label">التاريخ:</td>
                <td><?php echo date('Y/m/d'); ?></td>
            </tr>
            <tr>
                <td class="label"><?php echo $entityLabel; ?>:</td>
                <td><strong><?php echo $plan['entity_name']; ?></strong></td>
            </tr>
            <tr>
                <td class="label">التليفون:</td>
                <td><?php echo $entity['phone'] ?? '-'; ?></td>
            </tr>
            <tr>
                <td class="label">عدد الأقساط:</td>
                <td><?php echo $plan['number_of_installments']; ?> قسط</td>
            </tr>
        </table>
        
        <?php if ($paidAmount > 0): ?>
        <div class="amount-box">
            <div class="title">المبلغ المدفوع</div>
            <div class="amount"><?php echo number_format($paidAmount, 2); ?></div>
            <div><?php echo $currency; ?></div>
        </div>
        <?php endif; ?>
        
        <div class="balance-section">
            <div class="balance-box balance-before">
                <div style="font-weight: bold; margin-bottom: 5px;">إجمالي خطة التقسيط</div>
                <div style="font-size: 1.5em; color: #1e40af;"><?php echo number_format($plan['total_amount'], 2); ?> <?php echo $currency; ?></div>
            </div>
            <div class="balance-box balance-paid">
                <div style="font-weight: bold; margin-bottom: 5px;">المدفوع حتى الآن</div>
                <div style="font-size: 1.5em; color: #059669;"><?php echo number_format($plan['paid_amount'], 2); ?> <?php echo $currency; ?></div>
            </div>
            <div class="balance-box balance-remaining">
                <div style="font-weight: bold; margin-bottom: 5px;">المتبقي</div>
                <div style="font-size: 1.5em; color: #92400e;"><?php echo number_format($plan['remaining_amount'], 2); ?> <?php echo $currency; ?></div>
            </div>
        </div>
        
        <?php if ($nextPayment && $plan['remaining_amount'] > 0): ?>
        <div class="next-installment">
            <span style="color: #92400e;">📅 القسط القادم: </span>
            <span style="font-weight: bold; color: #92400e;"><?php echo number_format($nextPayment['amount'], 2); ?> <?php echo $currency; ?></span>
            <span style="color: #92400e;"> - موعد الاستحقاق: <?php echo date('Y/m/d', strtotime($nextPayment['due_date'])); ?></span>
        </div>
        <?php elseif ($plan['remaining_amount'] <= 0): ?>
        <div class="completed-badge">
            <span style="font-size: 1.3em; color: #059669;">✅ تم سداد جميع الأقساط بالكامل</span>
        </div>
        <?php endif; ?>
        
        <div class="footer">
            <p style="margin-top: 30px;">
                توقيع <?php echo $entityLabel; ?>: ___________________ &nbsp;&nbsp;&nbsp;&nbsp; 
                توقيع المسؤول: ___________________
            </p>
        </div>
    </div>
    
    <script>
    function goBack() {
        if (window.history.length > 1) {
            window.history.back();
        } else {
            window.location.href = 'index.php';
        }
    }
    </script>
</body>
</html>
