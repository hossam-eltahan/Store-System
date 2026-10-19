<?php
/**
 * Settlement Receipt — إيصال محاسبة فواتير عميل (مدمج)
 * يعرض كل عناصر التسوية من customer_settlements.per_item في قالب طباعة مضغوط.
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requireAnyPermission(['customers.view', 'payments.customer', 'payments.view']);

$id = (int)($_GET['id'] ?? 0);

$settlement = getRow(
    "SELECT s.*, c.name AS customer_name, c.phone AS customer_phone
     FROM customer_settlements s
     LEFT JOIN customers c ON s.customer_id = c.id
     WHERE s.id = ?",
    [$id]
);

if (!$settlement) {
    die('إيصال المحاسبة غير موجود');
}

$perItem = json_decode((string)$settlement['per_item'], true);
if (!is_array($perItem)) {
    $perItem = [];
}

$appliedTotal = 0.0;
$grandTotal = 0.0;
foreach ($perItem as $it) {
    $appliedTotal += (float)($it['applied'] ?? 0);
    $grandTotal += (float)($it['total_amount'] ?? 0);
}
$leftTotal = max(0, round($grandTotal - $appliedTotal, 2));

// Get settings
$settings = getAllSettings();
$storeName = $settings['store_name'] ?? 'المحل';
$storeAddress = $settings['store_address'] ?? '';
$storePhone = $settings['store_phone'] ?? '';
$storePhone2 = $settings['store_phone2'] ?? '';
$storeLogo = $settings['store_logo'] ?? '';
$currency = $settings['currency'] ?? 'جنيه';

$pageTitle = 'إيصال محاسبة ' . $settlement['settlement_number'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title><?php echo $pageTitle; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f5f5f5; direction: rtl; font-size: 11px; }

        .no-print { text-align: center; padding: 8px; background: #f59e0b; }
        .no-print .btn { display: inline-block; padding: 6px 15px; margin: 0 3px; border: none; border-radius: 5px; cursor: pointer; font-size: 12px; text-decoration: none; color: white; }
        .btn-print { background: white; color: #f59e0b; }
        .btn-close { background: #dc2626; }

        .print-area { max-width: 190mm; margin: 10px auto; background: white; padding: 5mm; }

        .header { display: flex; align-items: center; justify-content: center; gap: 12px; padding-bottom: 5px; border-bottom: 2px solid #f59e0b; margin-bottom: 5px; }
        .header-logo { width: 50px; height: 50px; object-fit: contain; }
        .header-text { text-align: center; }
        .store-name { font-size: 18px; font-weight: bold; color: #f59e0b; }
        .header-info { font-size: 10px; color: #666; }

        .receipt-type { text-align: center; border: 2px solid #f59e0b; background: #fef3c7; padding: 5px; margin: 5px 0; font-size: 14px; font-weight: bold; color: #92400e; }

        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .info-table td { padding: 4px 6px; border: 1px solid #f59e0b; font-size: 11px; }
        .info-table .label { font-weight: bold; background: #fef3c7; width: 100px; }

        .amount-box { background: #10b981; color: white; padding: 10px; border-radius: 6px; text-align: center; margin: 8px 0; }
        .amount-box .title { font-size: 11px; margin-bottom: 3px; }
        .amount-box .amount { font-size: 24px; font-weight: bold; }

        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .items-table th { background: #0f172a; color: #fff; padding: 5px 6px; font-size: 10.5px; border: 1px solid #0f172a; }
        .items-table td { padding: 5px 6px; border: 1px solid #f59e0b; font-size: 10.5px; text-align: center; }
        .items-table td.item-name { text-align: right; font-weight: bold; }
        .items-table tr:nth-child(even) td { background: #fffbeb; }

        .totals-row { display: flex; gap: 8px; margin: 8px 0; }
        .totals-box { flex: 1; padding: 8px; border-radius: 6px; text-align: center; font-size: 11px; }
        .totals-box .amount { font-size: 15px; font-weight: bold; margin-top: 3px; }
        .tb-billed { background: #eff6ff; border: 1px solid #2563eb; }
        .tb-applied { background: #d1fae5; border: 1px solid #10b981; }
        .tb-left { background: <?php echo $leftTotal > 0.009 ? '#fee2e2; border: 1px solid #dc2626;' : '#f1f5f9; border: 1px solid #94a3b8;'; ?> }

        .notes-box { background: #f3f4f6; padding: 6px; border-radius: 4px; margin-bottom: 8px; font-size: 10px; }

        .footer-sig { text-align: center; padding-top: 8px; border-top: 2px dashed #f59e0b; margin-top: 8px; font-size: 10px; }

        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .print-area { max-width: 100%; margin: 0; padding: 4mm; box-shadow: none; }
            @page { margin: 8mm; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn btn-print" onclick="window.print()">🖨️ طباعة</button>
        <button class="btn btn-close" onclick="window.close()">❌ إغلاق</button>
    </div>

    <div class="print-area">
        <div class="header">
            <?php if ($storeLogo): ?>
            <img src="../../assets/uploads/<?php echo $storeLogo; ?>" alt="Logo" class="header-logo">
            <?php endif; ?>
            <div class="header-text">
                <div class="store-name"><?php echo $storeName; ?></div>
                <div class="header-info">
                    <?php if ($storeAddress): ?>العنوان: <?php echo $storeAddress; ?><?php endif; ?>
                    <?php if ($storePhone): ?> | ت: <?php echo $storePhone; ?><?php endif; ?>
                    <?php if ($storePhone2): ?> - <?php echo $storePhone2; ?><?php endif; ?>
                </div>
            </div>
            <?php if ($storeLogo): ?>
            <img src="../../assets/uploads/<?php echo $storeLogo; ?>" alt="" class="header-logo" style="visibility:hidden;">
            <?php endif; ?>
        </div>

        <div class="receipt-type">🧾 إيصال محاسبة فواتير عميل</div>

        <table class="info-table">
            <tr>
                <td class="label">رقم التسوية:</td>
                <td><strong><?php echo $settlement['settlement_number']; ?></strong></td>
                <td class="label">التاريخ:</td>
                <td><?php echo date('Y/m/d h:i A', strtotime($settlement['created_at'])); ?></td>
            </tr>
            <tr>
                <td class="label">العميل:</td>
                <td><strong><?php echo $settlement['customer_name'] ?: '-'; ?></strong></td>
                <td class="label">التليفون:</td>
                <td><?php echo $settlement['customer_phone'] ?: '-'; ?></td>
            </tr>
            <tr>
                <td class="label">عدد الفواتير:</td>
                <td><?php echo count($perItem); ?></td>
                <td class="label">رقم الشيفت:</td>
                <td><?php echo $settlement['shift_id'] ? ('#' . $settlement['shift_id']) : '-'; ?></td>
            </tr>
        </table>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width:24px;">#</th>
                    <th>الفاتورة / الطلب</th>
                    <th>التاريخ</th>
                    <th>الإجمالي</th>
                    <th>المحاصل عليه</th>
                    <th>المتبقي</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($perItem as $it): ?>
                <tr>
                    <td><?php echo $i++; ?></td>
                    <td class="item-name"><?php echo htmlspecialchars(($it['label'] ?? '') . ' — ' . ($it['ref_number'] ?? '')); ?></td>
                    <td><?php echo date('d/m/Y • h:i A', strtotime($it['item_date'] ?? $settlement['created_at'])); ?></td>
                    <td><?php echo number_format((float)($it['total_amount'] ?? 0), 2); ?></td>
                    <td style="color:#059669; font-weight:bold;"><?php echo number_format((float)($it['applied'] ?? 0), 2); ?></td>
                    <td style="color:<?php echo (float)($it['left_after'] ?? 0) > 0.009 ? '#dc2626' : '#94a3b8'; ?>;"><?php echo number_format((float)($it['left_after'] ?? 0), 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="amount-box">
            <div class="title">إجمالي المحصل في هذه التسوية</div>
            <div class="amount"><?php echo number_format((float)$settlement['paid_amount'], 2); ?> <?php echo $currency; ?></div>
        </div>

        <div class="totals-row">
            <div class="totals-box tb-billed">
                <div>إجمالي الفواتير المحسوبة</div>
                <div class="amount"><?php echo number_format((float)$settlement['total_amount'], 2); ?> <?php echo $currency; ?></div>
            </div>
            <div class="totals-box tb-applied">
                <div>المحصول منها</div>
                <div class="amount"><?php echo number_format((float)$settlement['paid_amount'], 2); ?> <?php echo $currency; ?></div>
            </div>
            <div class="totals-box tb-left">
                <div>المتبقي غير محاسب</div>
                <div class="amount"><?php echo number_format($leftTotal, 2); ?> <?php echo $currency; ?></div>
            </div>
        </div>

        <?php if ($settlement['notes']): ?>
        <div class="notes-box">
            <strong>ملاحظات:</strong> <?php echo $settlement['notes']; ?>
        </div>
        <?php endif; ?>

        <div class="footer-sig">
            <p>شكراً لتعاملكم معنا</p>
            <p style="margin-top: 15px;">
                توقيع العميل: _______________ &nbsp;&nbsp;&nbsp;&nbsp;
                توقيع المسؤول: _______________
            </p>
        </div>
    </div>
</body>
</html>
