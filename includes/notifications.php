<?php
/**
 * Notifications Helper
 * نظام إدارة محل أجهزة منزلية
 */

/**
 * Get all notifications for the notification bell
 * @return array
 */
function getNotifications() {
    global $pdo;
    
    $notifications = [];
    
    // 1. Low stock products
    $lowStockProducts = getRows("SELECT id, name, code, stock_quantity, unit FROM products WHERE stock_quantity <= min_stock_level ORDER BY stock_quantity ASC LIMIT 5");
    foreach ($lowStockProducts as $product) {
        $notifications[] = [
            'type' => 'warning',
            'icon' => '⚠️',
            'title' => 'مخزون منخفض',
            'message' => $product['name'] . ' (' . $product['stock_quantity'] . ' ' . $product['unit'] . ')',
            'link' => 'modules/products/index.php?filter=low_stock',
            'priority' => 2
        ];
    }
    
    // 2. Today's due installments
    $dueInstallments = getRows("
        SELECT ipy.*, ip.entity_name, ip.type, ip.id as plan_id
        FROM installment_payments ipy
        JOIN installment_plans ip ON ipy.plan_id = ip.id
        WHERE ipy.due_date = CURDATE() AND ipy.status = 'pending'
        LIMIT 5
    ");
    foreach ($dueInstallments as $payment) {
        $notifications[] = [
            'type' => 'danger',
            'icon' => '💰',
            'title' => 'قسط مستحق اليوم',
            'message' => $payment['entity_name'] . ' - ' . number_format($payment['amount'], 2) . ' ج.م',
            'link' => 'modules/installments/pay.php?plan_id=' . $payment['plan_id'],
            'priority' => 1
        ];
    }
    
    // 3. Overdue installments
    $overdueInstallments = getRows("
        SELECT ipy.*, ip.entity_name, ip.type, ip.id as plan_id
        FROM installment_payments ipy
        JOIN installment_plans ip ON ipy.plan_id = ip.id
        WHERE ipy.status = 'overdue'
        ORDER BY ipy.due_date ASC
        LIMIT 5
    ");
    foreach ($overdueInstallments as $payment) {
        $notifications[] = [
            'type' => 'danger',
            'icon' => '🚨',
            'title' => 'قسط متأخر',
            'message' => $payment['entity_name'] . ' - متأخر من ' . date('Y/m/d', strtotime($payment['due_date'])),
            'link' => 'modules/installments/pay.php?plan_id=' . $payment['plan_id'],
            'priority' => 0
        ];
    }
    
    // 4. Tomorrow's suppliers
    $tomorrowDay = getTomorrowDayName();
    $tomorrowSuppliers = getRows("SELECT id, name, phone, expected_products FROM suppliers WHERE visit_days LIKE ?", ["%$tomorrowDay%"]);
    foreach ($tomorrowSuppliers as $supplier) {
        $notifications[] = [
            'type' => 'info',
            'icon' => '🚚',
            'title' => 'مورد قادم غداً',
            'message' => $supplier['name'] . ' - ' . $supplier['expected_products'],
            'link' => 'modules/suppliers/index.php',
            'priority' => 3
        ];
    }
    
    // Sort by priority (lower = more important)
    usort($notifications, function($a, $b) {
        return $a['priority'] - $b['priority'];
    });
    
    return $notifications;
}

/**
 * Get notification count
 * @return int
 */
function getNotificationCount() {
    $lowStock = getRow("SELECT COUNT(*) as count FROM products WHERE stock_quantity <= min_stock_level");
    $dueToday = getRow("SELECT COUNT(*) as count FROM installment_payments WHERE due_date = CURDATE() AND status = 'pending'");
    $overdue = getRow("SELECT COUNT(*) as count FROM installment_payments WHERE status = 'overdue'");
    $tomorrowDay = getTomorrowDayName();
    $tomorrowSuppliers = getRow("SELECT COUNT(*) as count FROM suppliers WHERE visit_days LIKE ?", ["%$tomorrowDay%"]);
    
    return ($lowStock['count'] ?? 0) + ($dueToday['count'] ?? 0) + ($overdue['count'] ?? 0) + ($tomorrowSuppliers['count'] ?? 0);
}
?>
