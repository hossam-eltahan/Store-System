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
    <title>إيصال #<?php echo htmlspecialchars($order['order_number']); ?></title>
    <style>
        @page { size: 80mm auto; margin: 0; }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Cairo', 'Courier New', monospace, sans-serif; -webkit-print-color-adjust: exact; }
        body { width: 80mm; max-width: 80mm; margin: 0 auto; padding: 8px 10px; background: #fff; color: #000; font-size: 12px; line-height: 1.4; direction: rtl; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .fw-bold { font-weight: 700; }
        .divider { border-top: 1px dashed #000; margin: 6px 0; }
        .header { margin-bottom: 8px; text-align: center; }
        .header h1 { font-size: 17px; font-weight: 900; margin-bottom: 3px; }
        .header p { font-size: 11px; color: #222; }
        .meta-table { width: 100%; font-size: 11px; margin-bottom: 6px; }
        .meta-table td { padding: 2px 0; }
        .items-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .items-table th { border-bottom: 1px solid #000; padding: 4px 2px; font-size: 11px; }
        .items-table td { padding: 4px 2px; vertical-align: top; }
        .item-row { border-bottom: 1px dotted #ccc; }
        .variant-tag { font-size: 10.5px; color: #444; display: block; }
        .mod-tag { font-size: 10px; color: #555; padding-right: 8px; display: block; }
        .note-tag { font-size: 10px; font-style: italic; color: #d32f2f; display: block; }
        .totals-table { width: 100%; margin-top: 6px; font-size: 12px; }
        .totals-table td { padding: 2px 0; }
        .grand-total { font-size: 15px; font-weight: 900; border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 4px 0 !important; }
        .footer { margin-top: 10px; font-size: 10.5px; text-align: center; }
        .no-print { background: #f1f5f9; padding: 10px; text-align: center; border-bottom: 2px solid #cbd5e1; margin-bottom: 10px; }
        .btn-print { background: #2563eb; color: #fff; border: none; padding: 6px 16px; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 13px; }
        @media print { .no-print { display: none !important; } body { padding: 0; } }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn-print" onclick="window.print()">PRINT</button>
        <button class="btn-print" style="background:#475569;" onclick="window.close()">CLOSE</button>
    </div>

    <div class="header text-center">
        <h1><?php echo htmlspecialchars($storeName); ?></h1>
        <?php if ($storeAddress): ?><p><?php echo htmlspecialchars($storeAddress); ?></p><?php endif; ?>
        <?php if ($storePhone): ?><p>Phone: <?php echo htmlspecialchars($storePhone); ?></p><?php endif; ?>
        <?php if ($receiptHeader): ?><p style="margin-top:3px;"><?php echo htmlspecialchars($receiptHeader); ?></p><?php endif; ?>
    </div>

    <div class="divider"></div>

    <table class="meta-table">
        <tr>
            <td class="fw-bold">Order No:</td>
            <td class="text-left fw-bold"><?php echo htmlspecialchars($order['order_number']); ?></td>
        </tr>
        <tr>
            <td>Date/Time:</td>
            <td class="text-left"><?php echo date('Y-m-d h:i A', strtotime($order['created_at'])); ?></td>
        </tr>
        <tr>
            <td>Cashier:</td>
            <td class="text-left"><?php echo htmlspecialchars($order['cashier_name'] ?? 'Cashier'); ?></td>
        </tr>
        <tr>
            <td>Order Type:</td>
            <td class="text-left fw-bold"><?php echo htmlspecialchars($order['order_type']); ?></td>
        </tr>
        <?php if (!empty($order['notes'])): ?>
        <tr>
            <td>Notes:</td>
            <td class="text-left"><?php echo htmlspecialchars($order['notes']); ?></td>
        </tr>
        <?php endif; ?>
    </table>

    <div class="divider"></div>

    <?php if (!empty($items)): ?>
    <table class="items-table">
        <thead>
            <tr>
                <th class="text-right">Item</th>
                <th class="text-center">Qty</th>
                <th class="text-center">Price</th>
                <th class="text-left">Total</th>
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
                            <span class="mod-tag">+ <?php echo htmlspecialchars($mod['modifier_name']); ?> (<?php echo number_format($mod['modifier_price'], 2); ?>)</span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if (!empty($item['item_notes'])): ?>
                        <span class="note-tag">! <?php echo htmlspecialchars($item['item_notes']); ?></span>
                    <?php endif; ?>
                </td>
                <td class="text-center fw-bold" style="font-size: 13px;">x<?php echo $item['quantity']; ?></td>
                <td class="text-center"><?php echo number_format($item['unit_price'], 2); ?></td>
                <td class="text-left fw-bold"><?php echo number_format($item['line_total'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div style="text-align:center; padding:10px; font-size:12px; color:#666;">No items on this order.</div>
    <?php endif; ?>

    <div class="divider"></div>
    <table class="totals-table">
        <tr>
            <td>Subtotal:</td>
            <td class="text-left"><?php echo number_format($order['subtotal'] ?? 0, 2); ?> JOD</td>
        </tr>
        <?php if (!empty($order['discount_amount']) && $order['discount_amount'] > 0): ?>
        <tr style="color:#dc2626;">
            <td>Discount:</td>
            <td class="text-left">-<?php echo number_format($order['discount_amount'], 2); ?> JOD</td>
        </tr>
        <?php endif; ?>
        <?php if (!empty($order['tax_amount']) && $order['tax_amount'] > 0): ?>
        <tr>
            <td>Tax:</td>
            <td class="text-left"><?php echo number_format($order['tax_amount'], 2); ?> JOD</td>
        </tr>
        <?php endif; ?>
        <tr class="grand-total">
            <td>Total:</td>
            <td class="text-left"><?php echo number_format($order['total_amount'], 2); ?> JOD</td>
        </tr>
        <tr>
            <td>Paid:</td>
            <td class="text-left"><?php echo number_format($order['paid_amount'] ?? $order['total_amount'], 2); ?> JOD</td>
        </tr>
        <tr>
            <td>Payment Method:</td>
            <td class="text-left fw-bold"><?php $methods = ['cash' => 'Cash', 'card' => 'Card', 'instapay' => 'InstaPay', 'vodafone_cash' => 'Vodafone Cash']; $pm = $order['payment_method'] ?? 'cash'; echo $methods[$pm] ?? $pm; ?></td>
        </tr>
    </table>

    <div class="footer">
        <div class="divider"></div>
        <p><?php echo htmlspecialchars($receiptFooter); ?></p>
        <p style="font-size:9.5px; opacity:0.7; margin-top:4px;">POS v2.0</p>
    </div>

    <script>
        if (window.self === window.top) {
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                }, 400);
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
