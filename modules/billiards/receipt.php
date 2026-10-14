<?php
/**
 * Billiards Session Thermal Receipt (80mm)
 * Ø¨ÙˆÙ† Ø¬Ù„Ø³Ø© Ø¨Ù„ÙŠØ§Ø±Ø¯Ùˆ ÙƒØ§ÙÙŠÙ‡ Ø¹Ø±ÙˆØ³Ø© Ø§Ù„Ù†ÙŠÙ„
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';

requireLogin();

$sessionId = (int)($_GET['id'] ?? 0);

$session = getRow(
        "SELECT bs.*, bt.display_name as table_name, bt.table_type,
            bt.min_billable_minutes,
            u_open.full_name as opened_by_name,
            u_close.full_name as closed_by_name
     FROM billiard_sessions bs 
     JOIN billiard_tables bt ON bs.table_id = bt.id 
     LEFT JOIN users u_open ON bs.opened_by = u_open.id 
     LEFT JOIN users u_close ON bs.closed_by = u_close.id 
     WHERE bs.id = ?",
    [$sessionId]
);

if (!$session) {
    die("Ø§Ù„Ø¬Ù„Ø³Ø© ØºÙŠØ± Ù…ÙˆØ¬ÙˆØ¯Ø©");
}

$cafeOrders = getRows("SELECT * FROM orders WHERE billiard_session_id = ?", [$sessionId]);
$allOrderItems = [];
$cafeGrossTotal = 0;
$prepaidCafeTotal = 0;
foreach ($cafeOrders as $ord) {
    $cafeGrossTotal += (float)$ord['total_amount'];
    $orderPayment = getRow(
        "SELECT COALESCE(SUM(amount), 0) AS total FROM payment_records WHERE source_type = 'order' AND source_id = ? AND payment_type = 'payment'",
        [$ord['id']]
    );
    $prepaidCafeTotal += (float)($orderPayment['total'] ?? 0);
    $items = getRows("SELECT * FROM order_items WHERE order_id = ?", [$ord['id']]);
    $allOrderItems = array_merge($allOrderItems, $items);
}

$storeName = getSetting('store_name', 'ÙƒØ§ÙÙŠÙ‡ Ø¹Ø±ÙˆØ³Ø© Ø§Ù„Ù†ÙŠÙ„');
$storePhone = getSetting('store_phone', '');
$receiptFooter = getSetting('receipt_footer', 'Ø´ÙƒØ±Ø§Ù‹ Ù„Ø²ÙŠØ§Ø±ØªÙƒÙ… - Ù†ØªÙ…Ù†Ù‰ Ù„ÙƒÙ… ÙˆÙ‚ØªØ§Ù‹ Ù…Ù…ØªØ¹Ø§Ù‹');

$isHourly = ($session['billing_mode'] === 'hourly');
$isCompleted = $session['status'] === 'completed';
$gamingCharge = (float)($session['gaming_charge'] ?? 0);
$discountAmount = (float)($session['discount_amount'] ?? 0);
$sessionTotal = (float)($session['total_amount'] ?? 0);

if (!$isCompleted) {
    if ($isHourly) {
        $startedTimestamp = strtotime($session['started_at']);
        $elapsedSeconds = max(0, time() - $startedTimestamp);
        $pausedSeconds = (int)($session['total_paused_seconds'] ?? 0);
        if ($session['status'] === 'paused' && !empty($session['paused_at'])) {
            $pausedSeconds += max(0, time() - strtotime($session['paused_at']));
        }
        $minutes = (int)ceil(max(0, $elapsedSeconds - $pausedSeconds) / 60);
        $minutes = max((int)($session['min_billable_minutes'] ?? 15), $minutes);
        $gamingCharge = round(($minutes / 60) * (float)$session['applied_rate'], 2);
    } else {
        $minutes = 0;
        $gamingCharge = (int)($session['game_count'] ?? 0) * (float)$session['applied_rate'];
    }
} else {
    $minutes = (int)($session['billable_minutes'] ?? 0);
}

$grossTotal = max(0, $gamingCharge + $cafeGrossTotal - $discountAmount);
$displayTotal = $isCompleted ? $sessionTotal : $grossTotal;
$endedAtLabel = !empty($session['ended_at'])
    ? date('h:i A', strtotime($session['ended_at']))
    : 'Ù„Ù… ØªÙØºÙ„Ù‚ Ø¨Ø¹Ø¯';
$hours = floor($minutes / 60);
$remMinutes = $minutes % 60;
$durationFormatted = ($hours > 0 ? "$hours Ø³Ø§Ø¹Ø© Ùˆ " : "") . "$remMinutes Ø¯Ù‚ÙŠÙ‚Ø©";
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ÙØ§ØªÙˆØ±Ø© Ø¨Ù„ÙŠØ§Ø±Ø¯Ùˆ - <?php echo htmlspecialchars($session['table_name']); ?></title>
    <style>
        @page { size: 80mm auto; margin: 0; }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Cairo', 'Courier New', monospace, sans-serif; -webkit-print-color-adjust: exact; }
        body { width: 80mm; max-width: 80mm; margin: 0 auto; padding: 8px 10px; background: #fff; color: #000; font-size: 11.5px; line-height: 1.35; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .fw-bold { font-weight: 700; }
        .divider { border-top: 1px dashed #000; margin: 6px 0; }
        .double-divider { border-top: 2px solid #000; margin: 6px 0; }
        .header { margin-bottom: 8px; text-align: center; }
        .header h1 { font-size: 17px; font-weight: 900; }
        .badge { display: inline-block; padding: 3px 8px; background: #000; color: #fff; font-size: 12px; font-weight: bold; border-radius: 4px; margin: 4px 0; }
        .meta-table { width: 100%; font-size: 11px; margin-bottom: 6px; }
        .meta-table td { padding: 2px 0; }
        .items-table { width: 100%; border-collapse: collapse; font-size: 11.5px; }
        .items-table td { padding: 4px 2px; }
        .totals-table { width: 100%; margin-top: 6px; font-size: 12px; }
        .totals-table td { padding: 2px 0; }
        .grand-total { font-size: 12.5px; font-weight: 900; border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 4px 0 !important; white-space: nowrap; }
        .no-print { background: #f1f5f9; padding: 10px; text-align: center; margin-bottom: 10px; }
        .btn-print { background: #059669; color: #fff; border: none; padding: 6px 16px; border-radius: 6px; font-weight: bold; cursor: pointer; }
        @media print { .no-print { display: none !important; } body { padding: 0; } }
    </style>
</head>
<body>

    <div class="no-print">
        <button class="btn-print" onclick="window.print()">ðŸ–¨ï¸ Ø·Ø¨Ø§Ø¹Ø© Ø§Ù„ÙØ§ØªÙˆØ±Ø© Ø§Ù„Ø¢Ù†</button>
        <button class="btn-print" style="background:#475569;" onclick="window.close()">âœ–ï¸ Ø¥ØºÙ„Ø§Ù‚</button>
    </div>

    <div class="header">
        <h1><?php echo htmlspecialchars($storeName); ?></h1>
        <div class="badge">ðŸŽ± ÙØ§ØªÙˆØ±Ø© ØµØ§Ù„Ø© Ø§Ù„Ø¨Ù„ÙŠØ§Ø±Ø¯Ùˆ</div>
        <?php if ($storePhone): ?><p style="font-size:11px;">Ù‡Ø§ØªÙ: <?php echo htmlspecialchars($storePhone); ?></p><?php endif; ?>
    </div>

    <div class="divider"></div>

    <table class="meta-table">
        <tr>
            <td class="fw-bold">Ø§Ù„Ø·Ø§ÙˆÙ„Ø©:</td>
            <td class="text-left fw-bold"><?php echo htmlspecialchars($session['table_name']); ?></td>
        </tr>
        <tr>
            <td>Ù†Ø¸Ø§Ù… Ø§Ù„Ø­Ø³Ø§Ø¨:</td>
            <td class="text-left fw-bold"><?php echo $isHourly ? 'Ø¨Ø§Ù„Ø³Ø§Ø¹Ø© (ØªØ§ÙŠÙ…Ø±)' : "Ø¨Ø§Ù„Ø¬ÙŠÙ… ({$session['game_count']} Ø¬ÙŠÙ…)"; ?></td>
        </tr>
        <tr>
            <td>ÙˆÙ‚Øª Ø§Ù„Ø¨Ø¯Ø¡:</td>
            <td class="text-left"><?php echo date('Y-m-d h:i A', strtotime($session['started_at'])); ?></td>
        </tr>
        <tr>
            <td>ÙˆÙ‚Øª Ø§Ù„Ø§Ù†ØªÙ‡Ø§Ø¡:</td>
            <td class="text-left"><?php echo $endedAtLabel; ?></td>
        </tr>
        <?php if ($isHourly): ?>
        <tr>
            <td>Ø¥Ø¬Ù…Ø§Ù„ÙŠ ÙˆÙ‚Øª Ø§Ù„Ù„Ø¹Ø¨:</td>
            <td class="text-left fw-bold"><?php echo $durationFormatted; ?></td>
        </tr>
        <?php endif; ?>
        <?php if (!empty($session['customer_name'])): ?>
        <tr>
            <td>Ø§Ù„Ø¹Ù…ÙŠÙ„:</td>
            <td class="text-left"><?php echo htmlspecialchars($session['customer_name']); ?></td>
        </tr>
        <?php endif; ?>
    </table>

    <div class="double-divider"></div>

    <?php if (!empty($allOrderItems)): ?>
    <div class="divider"></div>
    <div style="font-size:11px; font-weight:bold; margin-bottom:4px;">ØªÙØ§ØµÙŠÙ„ Ø§Ù„Ù…Ø´Ø±ÙˆØ¨Ø§Øª Ø§Ù„Ù…Ø¶Ø§ÙØ©:</div>
    <table class="items-table">
        <?php foreach ($allOrderItems as $it): ?>
        <tr>
            <td>â€¢ <?php echo htmlspecialchars($it['product_name']); ?></td>
            <td class="text-center">x<?php echo $it['quantity']; ?></td>
            <td class="text-left"><?php echo number_format($it['line_total'], 2); ?> Ø¬.Ù…</td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <?php
    $remainingTotal = $isCompleted
        ? max(0, $sessionTotal)
        : max(0, $grossTotal - $prepaidCafeTotal);
    ?>

    <div class="double-divider"></div>
    <table class="totals-table">
        <tr>
            <td class="fw-bold">Ø­Ø³Ø§Ø¨ Ø·Ø§ÙˆÙ„Ø© Ø§Ù„Ø¨Ù„ÙŠØ§Ø±Ø¯Ùˆ:</td>
            <td class="text-left fw-bold"><?php echo number_format($gamingCharge, 2); ?> Ø¬.Ù…</td>
        </tr>
        <?php if ($cafeGrossTotal > 0): ?>
        <tr>
            <td>Ù…Ø´Ø±ÙˆØ¨Ø§Øª ÙˆØ·Ù„Ø¨Ø§Øª ÙƒØ§ÙÙŠÙ‡:</td>
            <td class="text-left"><?php echo number_format($cafeGrossTotal, 2); ?> Ø¬.Ù…</td>
        </tr>
        <?php endif; ?>
        <?php if ($discountAmount > 0): ?>
        <tr style="color:#dc2626;">
            <td>Ø§Ù„Ø®ØµÙ…:</td>
            <td class="text-left">-<?php echo number_format($discountAmount, 2); ?> Ø¬.Ù…</td>
        </tr>
        <?php endif; ?>
        <tr class="grand-total">
            <td>Ø§Ù„Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø§Ù„Ù…Ø·Ù„ÙˆØ¨:</td>
            <td class="text-left"><?php echo number_format($displayTotal, 2); ?> Ø¬.Ù…</td>
        </tr>
        <tr>
            <td>Ø§Ù„Ù…Ø¯ÙÙˆØ¹ Ù…Ø³Ø¨Ù‚Ø§Ù‹:</td>
            <td class="text-left"><?php echo number_format($prepaidCafeTotal, 2); ?> Ø¬.Ù…</td>
        </tr>
        <tr style="font-weight:900; color:<?php echo $remainingTotal > 0 ? '#d97706' : '#059669'; ?>;">
            <td>Ø§Ù„Ù…ØªØ¨Ù‚ÙŠ Ù„Ù„ØªØ­ØµÙŠÙ„:</td>
            <td class="text-left"><?php echo number_format($remainingTotal, 2); ?> Ø¬.Ù…</td>
        </tr>
    </table>

    <div class="divider"></div>
    <div style="text-align:center; font-size:11px; margin-top:6px;">
        <p><?php echo htmlspecialchars($receiptFooter); ?></p>
        <p style="font-size:9.5px; opacity:0.7; margin-top:3px;">ÙƒØ§Ø´ÙŠØ±: <?php echo htmlspecialchars($session['closed_by_name'] ?? 'Ø§Ù„ÙƒØ§Ø´ÙŠØ±'); ?></p>
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
