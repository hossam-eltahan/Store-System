<?php
/**
 * POS Receipt Printing Template (Thermal 80mm / 57mm)
 * Ø¨ÙˆÙ† Ø·Ø¨Ø§Ø¹Ø© ÙƒØ§Ø´ÙŠØ± ÙƒØ§ÙÙŠÙ‡ Ø¹Ø±ÙˆØ³Ø© Ø§Ù„Ù†ÙŠÙ„
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';

requireLogin();

$orderId = (int)($_GET['id'] ?? 0);
$type = $_GET['type'] ?? 'customer'; // customer or kitchen

$order = getRow(
    "SELECT o.*, u.full_name as cashier_name,
            ct.display_name as table_name,
            gd.display_name as ps_name,
            bt.display_name as billiard_name
     FROM orders o 
     LEFT JOIN users u ON o.created_by = u.id 
     LEFT JOIN table_sessions ts ON o.table_session_id = ts.id 
     LEFT JOIN cafe_tables ct ON ts.table_id = ct.id 
     LEFT JOIN gaming_sessions gs ON o.gaming_session_id = gs.id 
     LEFT JOIN gaming_devices gd ON gs.device_id = gd.id 
     LEFT JOIN billiard_sessions bs ON o.billiard_session_id = bs.id 
     LEFT JOIN billiard_tables bt ON bs.table_id = bt.id 
     WHERE o.id = ?",
    [$orderId]
);

if (!$order) {
    header('Location: index.php?receipt_error=not_found');
    exit;
}

$items = getRows("SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC", [$orderId]);
foreach ($items as &$it) {
    $it['modifiers'] = getRows("SELECT * FROM order_item_modifiers WHERE order_item_id = ?", [$it['id']]);
}

$storeName = getSetting('store_name', 'ÙƒØ§ÙÙŠÙ‡ Ø¹Ø±ÙˆØ³Ø© Ø§Ù„Ù†ÙŠÙ„');
$storeAddress = getSetting('store_address', '');
$storePhone = getSetting('store_phone', '');
$receiptHeader = getSetting('receipt_header', 'Ø£Ù‡Ù„Ø§Ù‹ Ø¨ÙƒÙ… ÙÙŠ ÙƒØ§ÙÙŠÙ‡ Ø¹Ø±ÙˆØ³Ø© Ø§Ù„Ù†ÙŠÙ„');
$receiptFooter = getSetting('receipt_footer', 'Ø´ÙƒØ±Ø§Ù‹ Ù„Ø²ÙŠØ§Ø±ØªÙƒÙ… - Ù†ØªÙ…Ù†Ù‰ Ù„ÙƒÙ… ÙˆÙ‚ØªØ§Ù‹ Ù…Ù…ØªØ¹Ø§Ù‹');
$isKitchen = ($type === 'kitchen');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ø¨ÙˆÙ† Ø·Ù„Ø¨ #<?php echo htmlspecialchars($order['order_number']); ?> - <?php echo $isKitchen ? 'Ø§Ù„Ù…Ø·Ø¨Ø® ÙˆØ§Ù„Ø¨Ø§Ø±' : 'Ø§Ù„Ø¹Ù…ÙŠÙ„'; ?></title>
    <style>
        @page {
            size: 80mm auto;
            margin: 0;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Cairo', 'Courier New', monospace, sans-serif;
            -webkit-print-color-adjust: exact;
        }
        body {
            width: 80mm;
            max-width: 80mm;
            margin: 0 auto;
            padding: 8px 10px;
            background: #fff;
            color: #000;
            font-size: 12px;
            line-height: 1.4;
        }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .fw-bold { font-weight: 700; }
        .divider {
            border-top: 1px dashed #000;
            margin: 6px 0;
        }
        .double-divider {
            border-top: 2px solid #000;
            margin: 6px 0;
        }
        .header {
            margin-bottom: 8px;
        }
        .header h1 {
            font-size: 17px;
            font-weight: 900;
            margin-bottom: 3px;
        }
        .header p {
            font-size: 11px;
            color: #222;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border: 1px solid #000;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            margin: 4px 0;
        }
        .kitchen-badge {
            background: #000;
            color: #fff;
            padding: 4px 10px;
            font-size: 14px;
            font-weight: 900;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 6px;
        }
        .meta-table {
            width: 100%;
            font-size: 11px;
            margin-bottom: 6px;
        }
        .meta-table td {
            padding: 2px 0;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        .items-table th {
            border-bottom: 1px solid #000;
            padding: 4px 2px;
            font-size: 11px;
        }
        .items-table td {
            padding: 4px 2px;
            vertical-align: top;
        }
        .item-row {
            border-bottom: 1px dotted #ccc;
        }
        .variant-tag {
            font-size: 10.5px;
            color: #444;
            display: block;
        }
        .mod-tag {
            font-size: 10px;
            color: #555;
            padding-right: 8px;
            display: block;
        }
        .note-tag {
            font-size: 10px;
            font-style: italic;
            color: #d32f2f;
            display: block;
        }
        .totals-table {
            width: 100%;
            margin-top: 6px;
            font-size: 12px;
        }
        .totals-table td {
            padding: 2px 0;
        }
        .grand-total {
            font-size: 15px;
            font-weight: 900;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 4px 0 !important;
        }
        .footer {
            margin-top: 10px;
            font-size: 10.5px;
            text-align: center;
        }
        .no-print {
            background: #f1f5f9;
            padding: 10px;
            text-align: center;
            border-bottom: 2px solid #cbd5e1;
            margin-bottom: 10px;
        }
        .btn-print {
            background: #2563eb;
            color: #fff;
            border: none;
            padding: 6px 16px;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            font-size: 13px;
        }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button class="btn-print" onclick="window.print()">PRINT</button>
        <button class="btn-print" style="background:#475569;" onclick="window.close()">CLOSE</button>
        <?php if (!$isKitchen): ?>
            <a href="receipt.php?id=<?php echo $orderId; ?>&type=kitchen" style="text-decoration:none; margin-right:8px;" class="btn-print" style="background:#e11d48;">ðŸ‘¨â€ðŸ³ Ø¨ÙˆÙ† Ø§Ù„Ø¨Ø§Ø±/Ø§Ù„Ù…Ø·Ø¨Ø®</a>
        <?php else: ?>
            <a href="receipt.php?id=<?php echo $orderId; ?>&type=customer" style="text-decoration:none; margin-right:8px;" class="btn-print" style="background:#059669;">ðŸ§¾ Ø¨ÙˆÙ† Ø§Ù„Ø¹Ù…ÙŠÙ„</a>
        <?php endif; ?>
    </div>

    <div class="header text-center">
        <?php if ($isKitchen): ?>
            <div class="kitchen-badge">KITCHEN ORDER</div>
        <?php else: ?>
            <h1><?php echo htmlspecialchars($storeName); ?></h1>
            <?php if ($storeAddress): ?><p><?php echo htmlspecialchars($storeAddress); ?></p><?php endif; ?>
            <?php if ($storePhone): ?><p>Phone: <?php echo htmlspecialchars($storePhone); ?></p><?php endif; ?>
            <?php if ($receiptHeader): ?><p style="margin-top:3px;"><?php echo htmlspecialchars($receiptHeader); ?></p><?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="divider"></div>

    <table class="meta-table">
        <tr>
            <td class="fw-bold">Ø±Ù‚Ù… Ø§Ù„Ø·Ù„Ø¨:</td>
            <td class="text-left fw-bold"><?php echo htmlspecialchars($order['order_number']); ?></td>
        </tr>
        <tr>
            <td>Ø§Ù„ØªØ§Ø±ÙŠØ® ÙˆØ§Ù„ÙˆÙ‚Øª:</td>
            <td class="text-left"><?php echo date('Y-m-d h:i A', strtotime($order['created_at'])); ?></td>
        </tr>
        <tr>
            <td>Ø§Ù„ÙƒØ§Ø´ÙŠØ±:</td>
            <td class="text-left"><?php echo htmlspecialchars($order['cashier_name'] ?? 'Ø§Ù„ÙƒØ§Ø´ÙŠØ±'); ?></td>
        </tr>
        <tr>
            <td>Ù†ÙˆØ¹ Ø§Ù„Ø·Ù„Ø¨:</td>
            <td class="text-left fw-bold">
                <?php 
                $types = [
                    'takeaway' => 'ØªÙŠÙƒ Ø£ÙˆØ§ÙŠ (Takeaway) ðŸ›ï¸',
                    'dine_in' => 'ØµØ§Ù„Ø© (Ø·Ø§ÙˆÙ„Ø©) ðŸª‘' . ($order['table_name'] ? ' - ' . $order['table_name'] : ''),
                    'gaming_ps' => 'Ø¨Ù„Ø§ÙŠØ³ØªÙŠØ´Ù† ðŸŽ®' . ($order['ps_name'] ? ' - ' . $order['ps_name'] : ''),
                    'gaming_billiard' => 'Ø¨Ù„ÙŠØ§Ø±Ø¯Ùˆ ðŸŽ±' . ($order['billiard_name'] ? ' - ' . $order['billiard_name'] : ''),
                ];
                echo $types[$order['order_type']] ?? $order['order_type'];
                ?>
            </td>
        </tr>
        <?php if (!empty($order['customer_name'])): ?>
        <tr>
            <td>Ø§Ù„Ø¹Ù…ÙŠÙ„:</td>
            <td class="text-left"><?php echo htmlspecialchars($order['customer_name']); ?></td>
        </tr>
        <?php endif; ?>
    </table>

    <div class="double-divider"></div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 50%;" class="text-right">Ø§Ù„ØµÙ†Ù</th>
                <th style="width: 15%;" class="text-center">Ø§Ù„Ø¹Ø¯Ø¯</th>
                <?php if (!$isKitchen): ?>
                <th style="width: 15%;" class="text-center">Ø§Ù„Ø³Ø¹Ø±</th>
                <th style="width: 20%;" class="text-left">Ø§Ù„Ø¥Ø¬Ù…Ø§Ù„ÙŠ</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr class="item-row">
                <td class="text-right">
                    <span class="fw-bold"><?php echo htmlspecialchars($item['product_name']); ?></span>
                    <?php if (!empty($item['variant_name'])): ?>
                        <span class="variant-tag">(<?php echo htmlspecialchars($item['variant_name']); ?>)</span>
                    <?php endif; ?>
                    <?php if (!empty($item['modifiers'])): ?>
                        <?php foreach ($item['modifiers'] as $mod): ?>
                            <span class="mod-tag">+ <?php echo htmlspecialchars($mod['modifier_name']); ?><?php echo !$isKitchen ? ' (' . number_format($mod['modifier_price'], 2) . ')' : ''; ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if (!empty($item['item_notes'])): ?>
                        <span class="note-tag">âš ï¸ Ù…Ù„Ø§Ø­Ø¸Ø©: <?php echo htmlspecialchars($item['item_notes']); ?></span>
                    <?php endif; ?>
                </td>
                <td class="text-center fw-bold" style="font-size: 13px;">x<?php echo $item['quantity']; ?></td>
                <?php if (!$isKitchen): ?>
                <td class="text-center"><?php echo number_format($item['unit_price'], 2); ?></td>
                <td class="text-left fw-bold"><?php echo number_format($item['line_total'], 2); ?></td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (!$isKitchen): ?>
    <div class="double-divider"></div>
    <table class="totals-table">
        <tr>
            <td>Ø§Ù„Ù…Ø¬Ù…ÙˆØ¹ Ø§Ù„ÙØ±Ø¹ÙŠ:</td>
            <td class="text-left"><?php echo number_format($order['subtotal'], 2); ?> Ø¬.Ù…</td>
        </tr>
        <?php if ($order['discount_amount'] > 0): ?>
        <tr style="color: #dc2626;">
            <td>Ø§Ù„Ø®ØµÙ…:</td>
            <td class="text-left">-<?php echo number_format($order['discount_amount'], 2); ?> Ø¬.Ù…</td>
        </tr>
        <?php endif; ?>
        <?php if ((float)$order['tax_amount'] > 0): ?>
        <tr>
            <td>Ø§Ù„Ø¶Ø±ÙŠØ¨Ø© ÙˆØ§Ù„Ø®Ø¯Ù…Ø©:</td>
            <td class="text-left"><?php echo number_format($order['tax_amount'], 2); ?> Ø¬.Ù…</td>
        </tr>
        <?php endif; ?>
        <tr class="grand-total">
            <td>Ø§Ù„Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø§Ù„Ù…Ø·Ù„ÙˆØ¨:</td>
            <td class="text-left"><?php echo number_format($order['total_amount'], 2); ?> Ø¬.Ù…</td>
        </tr>
        <tr>
            <td>Ø·Ø±ÙŠÙ‚Ø© Ø§Ù„Ø¯ÙØ¹:</td>
            <td class="text-left fw-bold">
                <?php 
                $methods = ['cash' => 'Ù†Ù‚Ø¯Ø§Ù‹ (ÙƒØ§Ø´)', 'card' => 'ÙÙŠØ²Ø§ / Ø¨Ø·Ø§Ù‚Ø©', 'vodafone_cash' => 'ÙÙˆØ¯Ø§ÙÙˆÙ† ÙƒØ§Ø´', 'instapay' => 'Ø¥Ù†Ø³ØªØ§Ø¨Ø§ÙŠ (InstaPay)'];
                echo $methods[$order['payment_method']] ?? $order['payment_method'];
                ?>
            </td>
        </tr>
        <tr>
            <td>Ø§Ù„Ù…Ø¯ÙÙˆØ¹:</td>
            <td class="text-left"><?php echo number_format($order['paid_amount'], 2); ?> Ø¬.Ù…</td>
        </tr>
        <?php if ($order['total_amount'] > $order['paid_amount']): ?>
        <tr style="color:#d97706; font-weight:bold;">
            <td>Ø§Ù„Ù…ØªØ¨Ù‚ÙŠ (Ù…Ø¹Ù„Ù‚):</td>
            <td class="text-left"><?php echo number_format($order['total_amount'] - $order['paid_amount'], 2); ?> Ø¬.Ù…</td>
        </tr>
        <?php endif; ?>
    </table>
    <?php endif; ?>

    <?php if (!empty($order['notes'])): ?>
    <div class="divider"></div>
    <div style="font-size:11px;">
        <span class="fw-bold">Ù…Ù„Ø§Ø­Ø¸Ø§Øª Ø§Ù„Ø·Ù„Ø¨:</span> <?php echo htmlspecialchars($order['notes']); ?>
    </div>
    <?php endif; ?>

    <div class="footer">
        <div class="divider"></div>
        <p><?php echo htmlspecialchars($receiptFooter); ?></p>
        <p style="font-size:9.5px; opacity:0.7; margin-top:4px;">Ù†Ø¸Ø§Ù… ÙƒØ§Ø´ÙŠØ± Ø¹Ø±ÙˆØ³Ø© Ø§Ù„Ù†ÙŠÙ„ v2.0</p>
    </div>

    <script>
        // Ø·Ø¨Ø§Ø¹Ø© ØªÙ„Ù‚Ø§Ø¦ÙŠØ© Ø¹Ù†Ø¯ Ø§Ù„ÙØªØ­
        if (window.self === window.top) {
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                }, 300);
            };
        }
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
