<?php
/**
 * Ajax Handler for Transfer Details
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!hasPermission('warehouses.view')) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'معرف غير صالح']);
    exit;
}

$transfer = getRow(
    "SELECT st.*, 
            w_from.name as from_warehouse_name,
            w_to.name as to_warehouse_name,
            u.full_name as user_name
     FROM stock_transfers st
     JOIN warehouses w_from ON st.from_warehouse_id = w_from.id
     JOIN warehouses w_to ON st.to_warehouse_id = w_to.id
     LEFT JOIN users u ON st.created_by = u.id
     WHERE st.id = ?",
    [$id]
);

if (!$transfer) {
    echo json_encode(['success' => false, 'message' => 'سجل التحويل غير موجود']);
    exit;
}

$items = getRows(
    "SELECT sti.*, p.name as product_name, p.code as product_code, p.unit
     FROM stock_transfer_items sti
     JOIN products p ON sti.product_id = p.id
     WHERE sti.transfer_id = ?",
    [$id]
);

echo json_encode([
    'success' => true,
    'transfer' => $transfer,
    'items' => $items
]);
