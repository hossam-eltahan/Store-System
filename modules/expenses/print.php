<?php
/**
 * Print Expense Payment Voucher (سند صرف نقدية)
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
require_once '../../config/expenses.php';

requireAnyPermission(['expenses.view', 'expenses.add', 'expenses.edit']);

$id = intval($_GET['id'] ?? 0);

$expense = getRow("
    SELECT e.*, c.name as category_name, c.icon as category_icon
    FROM expenses e
    LEFT JOIN expense_categories c ON e.category_id = c.id
    WHERE e.id = ?
", [$id]);

if (!$expense) {
    die('سند المصروف غير موجود.');
}

$settings = getAllSettings();
$storeName = $settings['store_name'] ?? 'نظام إدارة المحل';
$storeAddress = $settings['store_address'] ?? '';
$storePhone = $settings['store_phone'] ?? '';
$storePhone2 = $settings['store_phone2'] ?? '';
$storeLogo = $settings['store_logo'] ?? '';
$currency = $settings['currency'] ?? 'جنيه';

$pageTitle = 'سند صرف مصروف رقم ' . $expense['expense_number'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title><?php echo $pageTitle; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f8fafc; direction: rtl; font-size: 12px; color: #1e293b; }
        
        .no-print { text-align: center; padding: 12px; background: #1e3a8a; }
        .no-print .btn { display: inline-block; padding: 8px 18px; margin: 0 4px; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; text-decoration: none; color: white; font-weight: 600; }
        .btn-print { background: #10b981; }
        .btn-close { background: #ef4444; }
        .btn-back { background: #3b82f6; }
        
        .voucher-card {
            max-width: 200mm;
            margin: 20px auto;
            background: white;
            padding: 10mm;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border: 1px solid #e2e8f0;
        }
        
        .voucher-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 12px;
            border-bottom: 2px solid #2563eb;
            margin-bottom: 15px;
        }
        .store-details { text-align: right; }
        .store-title { font-size: 20px; font-weight: 800; color: #1e3a8a; }
        .store-sub { font-size: 11px; color: #64748b; margin-top: 3px; }
        .logo-box { width: 65px; height: 65px; display: flex; align-items: center; justify-content: center; font-size: 36px; }
        .logo-box img { max-width: 100%; max-height: 100%; object-fit: contain; }

        .voucher-badge {
            text-align: center;
            background: #eff6ff;
            border: 2px solid #3b82f6;
            color: #1e40af;
            font-size: 16px;
            font-weight: 800;
            padding: 6px 16px;
            border-radius: 6px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .data-table td { padding: 8px 10px; border: 1px solid #cbd5e1; font-size: 12px; }
        .data-table .label { font-weight: 700; background: #f8fafc; color: #334155; width: 130px; }
        .data-table .val { color: #0f172a; }

        .amount-highlight {
            background: #fef2f2;
            border: 2px dashed #dc2626;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .amount-num { font-size: 24px; font-weight: 800; color: #b91c1c; }

        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
            margin-top: 35px;
            padding-top: 15px;
            border-top: 1px dashed #cbd5e1;
            text-align: center;
        }
        .sig-box { font-size: 12px; font-weight: 600; color: #475569; }
        .sig-line { margin-top: 45px; border-bottom: 1px dotted #94a3b8; }

        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .voucher-card { border: none; box-shadow: none; margin: 0; padding: 5mm; max-width: 100%; }
            @page { margin: 8mm; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button class="btn btn-print" onclick="window.print()">🖨️ طباعة السند</button>
    <a href="index.php" class="btn btn-back">📋 العودة للمصروفات</a>
    <button class="btn btn-close" onclick="window.close()">❌ إغلاق</button>
</div>

<div class="voucher-card">
    <!-- Store Header -->
    <div class="voucher-header">
        <div class="store-details">
            <div class="store-title"><?php echo htmlspecialchars($storeName); ?></div>
            <?php if ($storeAddress): ?>
            <div class="store-sub">📍 <?php echo htmlspecialchars($storeAddress); ?></div>
            <?php endif; ?>
            <?php if ($storePhone): ?>
            <div class="store-sub">📞 <?php echo htmlspecialchars($storePhone . ($storePhone2 ? ' - ' . $storePhone2 : '')); ?></div>
            <?php endif; ?>
        </div>
        <div class="logo-box">
            <?php if ($storeLogo): ?>
            <img src="../../assets/uploads/<?php echo $storeLogo; ?>" alt="Logo">
            <?php else: ?>
            <span>🏪</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Title Badge -->
    <div class="voucher-badge">
        <span>سند صرف نقدية / مصروفات</span>
        <span style="font-family: monospace; font-size: 15px;"><?php echo htmlspecialchars($expense['expense_number']); ?></span>
    </div>

    <!-- Details Table -->
    <table class="data-table">
        <tr>
            <td class="label">تاريخ الصرف:</td>
            <td class="val" style="font-weight: 700;"><?php echo date('Y-m-d', strtotime($expense['expense_date'])); ?></td>
            <td class="label">بند المصروف:</td>
            <td class="val">
                <?php echo $expense['category_icon'] ?: '💸'; ?>
                <strong><?php echo htmlspecialchars($expense['category_name'] ?: 'مصروف عام'); ?></strong>
            </td>
        </tr>
        <tr>
            <td class="label">يصرف إلى السيد/ة:</td>
            <td class="val" colspan="3">
                <strong><?php echo htmlspecialchars($expense['paid_to'] ?: 'نثريات داخلية / غير محدد'); ?></strong>
            </td>
        </tr>
        <tr>
            <td class="label">وذلك عن (البيان):</td>
            <td class="val" colspan="3" style="font-size: 13px; font-weight: 600; color: #1e3a8a;">
                <?php echo htmlspecialchars($expense['title']); ?>
            </td>
        </tr>
        <tr>
            <td class="label">طريقة الدفع:</td>
            <td class="val"><?php echo htmlspecialchars($expense['payment_method']); ?></td>
            <td class="label">رقم الإيصال الورقي:</td>
            <td class="val"><?php echo htmlspecialchars($expense['receipt_number'] ?: '—'); ?></td>
        </tr>
        <?php if (!empty($expense['notes'])): ?>
        <tr>
            <td class="label">ملاحظات:</td>
            <td class="val" colspan="3"><?php echo nl2br(htmlspecialchars($expense['notes'])); ?></td>
        </tr>
        <?php endif; ?>
    </table>

    <!-- Amount Highlight Box -->
    <div class="amount-highlight">
        <div>
            <div style="font-size: 12px; color: #7f1d1d; font-weight: 600; margin-bottom: 2px;">المبلغ المدفوع بالكامل:</div>
            <div style="font-size: 13px; font-weight: 700; color: #991b1b;">
                فقط وقدره: <?php echo formatCurrency($expense['amount']); ?>
            </div>
        </div>
        <div class="amount-num">
            <?php echo number_format($expense['amount'], 2); ?> <span style="font-size: 16px; font-weight: normal;"><?php echo $currency; ?></span>
        </div>
    </div>

    <!-- Signatures Section -->
    <div class="signatures">
        <div class="sig-box">
            <span>توقيع المستلم</span>
            <div class="sig-line"></div>
        </div>
        <div class="sig-box">
            <span>المحاسب / أمين الخزينة</span>
            <div class="sig-line"></div>
            <div style="font-size: 10px; color: #94a3b8; margin-top: 4px;"><?php echo htmlspecialchars($expense['handled_by'] ?: 'المسؤول'); ?></div>
        </div>
        <div class="sig-box">
            <span>اعتماد الإدارة</span>
            <div class="sig-line"></div>
        </div>
    </div>

    <div style="text-align: center; margin-top: 25px; font-size: 10px; color: #94a3b8; border-top: 1px solid #f1f5f9; padding-top: 6px;">
        طبع في: <?php echo date('Y-m-d h:i A'); ?> • نظام إدارة المحل
    </div>
</div>

</body>
</html>
