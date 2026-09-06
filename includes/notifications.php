<?php
/**
 * Notifications Helper
 * نظام إدارة محل أجهزة منزلية
 */

require_once __DIR__ . '/../config/auth.php';

/**
 * Get all notifications for the notification bell
 * @return array
 */
function getNotifications() {
    global $pdo;
    
    $notifications = [];
    
    // 1. Low stock products (only if products.view)
    if (hasPermission('products.view')) {
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
    }
    
    // 2. Today's due installments (only if installments.view)
    if (hasPermission('installments.view')) {
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
                'link' => hasPermission('installments.pay') ? 'modules/installments/pay.php?plan_id=' . $payment['plan_id'] : 'modules/installments/view.php?id=' . $payment['plan_id'],
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
                'link' => hasPermission('installments.pay') ? 'modules/installments/pay.php?plan_id=' . $payment['plan_id'] : 'modules/installments/view.php?id=' . $payment['plan_id'],
                'priority' => 0
            ];
        }
    }
    
    // 4. Tomorrow's suppliers (only if suppliers.view)
    if (hasPermission('suppliers.view')) {
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
    $count = 0;
    if (hasPermission('products.view')) {
        $lowStock = getRow("SELECT COUNT(*) as count FROM products WHERE stock_quantity <= min_stock_level");
        $count += ($lowStock['count'] ?? 0);
    }
    if (hasPermission('installments.view')) {
        $dueToday = getRow("SELECT COUNT(*) as count FROM installment_payments WHERE due_date = CURDATE() AND status = 'pending'");
        $overdue = getRow("SELECT COUNT(*) as count FROM installment_payments WHERE status = 'overdue'");
        $count += ($dueToday['count'] ?? 0) + ($overdue['count'] ?? 0);
    }
    if (hasPermission('suppliers.view')) {
        $tomorrowDay = getTomorrowDayName();
        $tomorrowSuppliers = getRow("SELECT COUNT(*) as count FROM suppliers WHERE visit_days LIKE ?", ["%$tomorrowDay%"]);
        $count += ($tomorrowSuppliers['count'] ?? 0);
    }
    
    return $count;
}
?>
