<?php
/**
 * Shift Summary Thermal Receipt (80mm)
 * تقرير تقفيل وردية الكاشير - كافيه عروسة النيل
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';

requireLogin();

$shiftId = (int)($_GET['id'] ?? 0);

$shift = getRow(
    "SELECT s.*, u.full_name as cashier_name, u_close.full_name as closed_by_name
     FROM shifts s 
     LEFT JOIN users u ON s.user_id = u.id 
     LEFT JOIN users u_close ON s.closed_by = u_close.id 
     WHERE s.id = ?",
    [$shiftId]
);

if (!$shift) {
    die("الوردية غير موجودة");
}

$movements = getRows("SELECT * FROM cash_movements WHERE shift_id = ? ORDER BY id ASC", [$shiftId]);

$storeName = getSetting('store_name', 'كافيه عروسة النيل');
$storePhone = getSetting('store_phone', '');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقرير تقفيل وردية #<?php echo htmlspecialchars($shift['shift_number']); ?></title>
    <style>
        @page { size: 80mm auto; margin: 0; }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Cairo', 'Courier New', monospace, sans-serif; -webkit-print-color-adjust: exact; }
        body { width: 80mm; max-width: 80mm; margin: 0 auto; padding: 8px 10px; background: #fff; color: #000; font-size: 12px; line-height: 1.4; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .fw-bold { font-weight: 700; }
        .divider { border-top: 1px dashed #000; margin: 6px 0; }
        .double-divider { border-top: 2px solid #000; margin: 6px 0; }
        .header { margin-bottom: 8px; text-align: center; }
        .header h1 { font-size: 17px; font-weight: 900; }
        .badge { display: inline-block; padding: 3px 8px; background: #000; color: #fff; font-size: 12px; font-weight: bold; border-radius: 4px; margin: 4px 0; }
        .meta-table { width: 100%; font-size: 11.5px; margin-bottom: 6px; }
        .meta-table td { padding: 2px 0; }
        .totals-table { width: 100%; margin-top: 6px; font-size: 12px; }
        .totals-table td { padding: 3px 0; }
        .grand-total { font-size: 15px; font-weight: 900; border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 4px 0 !important; }
        .no-print { background: #f1f5f9; padding: 10px; text-align: center; margin-bottom: 10px; }
        .btn-print { background: #2563eb; color: #fff; border: none; padding: 6px 16px; border-radius: 6px; font-weight: bold; cursor: pointer; }
        @media print { .no-print { display: none !important; } body { padding: 0; } }
    </style>
</head>
<body>

    <div class="no-print">
        <button class="btn-print" onclick="window.print()">🖨️ طباعة التقرير الآن</button>
        <button class="btn-print" style="background:#475569;" onclick="window.close()">✖️ إغلاق</button>
    </div>

    <div class="header">
        <h1><?php echo htmlspecialchars($storeName); ?></h1>
        <div class="badge">⏱️ تقرير تقفيل وردية الكاشير</div>
        <?php if ($storePhone): ?><p style="font-size:11px;">هاتف: <?php echo htmlspecialchars($storePhone); ?></p><?php endif; ?>
    </div>

    <div class="divider"></div>

    <table class="meta-table">
        <tr>
            <td class="fw-bold">رقم الوردية:</td>
            <td class="text-left fw-bold">#<?php echo htmlspecialchars($shift['shift_number']); ?></td>
        </tr>
        <tr>
            <td>الكاشير:</td>
            <td class="text-left"><?php echo htmlspecialchars($shift['cashier_name']); ?></td>
        </tr>
        <tr>
            <td>وقت الفتح:</td>
            <td class="text-left"><?php echo date('Y-m-d h:i A', strtotime($shift['opened_at'])); ?></td>
        </tr>
        <tr>
            <td>وقت الإغلاق:</td>
            <td class="text-left"><?php echo $shift['closed_at'] ? date('Y-m-d h:i A', strtotime($shift['closed_at'])) : 'مفتوحة حالياً'; ?></td>
        </tr>
    </table>

    <div class="double-divider"></div>

    <table class="totals-table">
        <tr>
            <td>الرصيد الافتتاحي (عهدة البداية):</td>
            <td class="text-left fw-bold"><?php echo number_format($shift['opening_balance'], 2); ?> ج.م</td>
        </tr>
        <tr>
            <td>مبيعات الكاش (نقدية):</td>
            <td class="text-left fw-bold"><?php echo number_format($shift['total_sales_cash'], 2); ?> ج.م</td>
        </tr>
        <tr>
            <td>مبيعات الفيزا والبطاقات:</td>
            <td class="text-left"><?php echo number_format($shift['total_sales_card'], 2); ?> ج.م</td>
        </tr>
        <tr>
            <td>مبيعات المحافظ الإلكترونية / إنستاباي:</td>
            <td class="text-left"><?php echo number_format($shift['total_sales_wallet'], 2); ?> ج.م</td>
        </tr>
        <tr>
            <td>إيراد البلايستيشن:</td>
            <td class="text-left"><?php echo number_format($shift['total_gaming_revenue'], 2); ?> ج.م</td>
        </tr>
        <tr>
            <td>إيراد البلياردو:</td>
            <td class="text-left"><?php echo number_format($shift['total_billiard_revenue'], 2); ?> ج.م</td>
        </tr>
        <?php if ($shift['total_cash_in'] > 0): ?>
        <tr>
            <td>إيداعات نقدية إضافية:</td>
            <td class="text-left">+<?php echo number_format($shift['total_cash_in'], 2); ?> ج.م</td>
        </tr>
        <?php endif; ?>
        <?php if ($shift['total_cash_out'] > 0): ?>
        <tr style="color:#dc2626;">
            <td>سحوبات نقدية من الدرج:</td>
            <td class="text-left">-<?php echo number_format($shift['total_cash_out'], 2); ?> ج.م</td>
        </tr>
        <?php endif; ?>
        <?php if ($shift['total_expenses'] > 0): ?>
        <tr style="color:#dc2626;">
            <td>مصروفات مدفوعة من الدرج:</td>
            <td class="text-left">-<?php echo number_format($shift['total_expenses'], 2); ?> ج.م</td>
        </tr>
        <?php endif; ?>

        <tr class="grand-total">
            <td>النقدية المتوقعة في الدرج:</td>
            <td class="text-left"><?php echo number_format($shift['expected_cash'], 2); ?> ج.م</td>
        </tr>
        <tr class="grand-total" style="background:#f1f5f9;">
            <td>النقدية الفعلية المحصورة:</td>
            <td class="text-left"><?php echo number_format($shift['actual_cash'], 2); ?> ج.م</td>
        </tr>
        
        <?php 
        $variance = floatval($shift['cash_variance']);
        if ($variance != 0): ?>
        <tr style="font-weight:900; font-size:14px; color: <?php echo $variance < 0 ? '#dc2626' : '#16a34a'; ?>;">
            <td><?php echo $variance < 0 ? '⚠️ عجز في النقدية:' : '🎉 زيادة في النقدية:'; ?></td>
            <td class="text-left"><?php echo ($variance > 0 ? '+' : '') . number_format($variance, 2); ?> ج.م</td>
        </tr>
        <?php if (!empty($shift['variance_reason'])): ?>
        <tr style="font-size:11px; color:#64748b;">
            <td colspan="2">سبب الفارق: <?php echo htmlspecialchars($shift['variance_reason']); ?></td>
        </tr>
        <?php endif; ?>
        <?php else: ?>
        <tr style="font-weight:bold; color:#16a34a;">
            <td>حالة الخزينة:</td>
            <td class="text-left">✅ النقدية مطابقة 100%</td>
        </tr>
        <?php endif; ?>
    </table>

    <?php if (!empty($shift['closing_notes'])): ?>
    <div class="divider"></div>
    <div style="font-size:11px;">
        <span class="fw-bold">ملاحظات الإغلاق:</span> <?php echo htmlspecialchars($shift['closing_notes']); ?>
    </div>
    <?php endif; ?>

    <div class="divider"></div>
    <div style="display:flex; justify-content:space-between; margin-top:20px; font-size:11px; padding:0 10px;">
        <div>توقيع الكاشير: ............</div>
        <div>توقيع المدير: ............</div>
    </div>

    <script>
        window.onload = function() { setTimeout(function() { window.print(); }, 300); };
        window.onafterprint = function() {
            setTimeout(function() {
                try {
                    if (window.self === window.top) {
                        window.close();
                    } else if (window.parent && typeof window.parent.closeReceiptModal === 'function') {
                        window.parent.closeReceiptModal();
                    }
                } catch (e) {}
            }, 100);
        };
    </script>
</body>
</html>
