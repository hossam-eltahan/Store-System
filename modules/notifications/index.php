<?php
/**
 * All Notifications Page - With Pagination & Filter
 * صفحة كل الإشعارات
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/auth.php';
require_once '../../config/settings.php';
require_once '../../includes/notifications.php';

requireLogin();

$pageTitle = 'جميع الإشعارات';

// Get all notifications (no limit) filtered by user permissions
function getAllNotifications() {
    $notifications = [];
    
    // 1. Low stock products - ALL (Only if user has products.view)
    if (hasPermission('products.view')) {
        $lowStockProducts = getRows("SELECT id, name, code, stock_quantity, unit, min_stock_level FROM products WHERE stock_quantity <= min_stock_level ORDER BY stock_quantity ASC");
        $prodLink = hasPermission('products.edit') ? 'modules/products/edit.php?id=' : 'modules/products/index.php?search=';
        foreach ($lowStockProducts as $product) {
            $notifications[] = [
                'type' => 'warning',
                'icon' => '⚠️',
                'title' => 'مخزون منخفض',
                'message' => $product['name'] . ' (' . $product['stock_quantity'] . '/' . $product['min_stock_level'] . ')',
                'link' => hasPermission('products.edit') ? 'modules/products/edit.php?id=' . $product['id'] : 'modules/products/index.php',
                'priority' => 2,
                'category' => 'stock'
            ];
        }
    }
    
    // 2. Today's due installments - ALL (Only if user has installments.view)
    if (hasPermission('installments.view')) {
        $dueInstallments = getRows("
            SELECT ipy.*, ip.entity_name, ip.type, ip.id as plan_id
            FROM installment_payments ipy
            JOIN installment_plans ip ON ipy.plan_id = ip.id
            WHERE ipy.due_date = CURDATE() AND ipy.status IN ('pending', 'partial')
        ");
        foreach ($dueInstallments as $payment) {
            $notifications[] = [
                'type' => 'danger',
                'icon' => '💰',
                'title' => 'قسط مستحق اليوم',
                'message' => $payment['entity_name'] . ' - ' . number_format($payment['amount'], 2),
                'link' => hasPermission('installments.pay') ? 'modules/installments/pay.php?plan_id=' . $payment['plan_id'] : 'modules/installments/index.php',
                'priority' => 1,
                'category' => 'installments'
            ];
        }
        
        // 3. Overdue installments - ALL
        $overdueInstallments = getRows("
            SELECT ipy.*, ip.entity_name, ip.type, ip.id as plan_id,
                   DATEDIFF(CURDATE(), ipy.due_date) as days_overdue
            FROM installment_payments ipy
            JOIN installment_plans ip ON ipy.plan_id = ip.id
            WHERE ipy.status = 'overdue'
            ORDER BY ipy.due_date ASC
        ");
        foreach ($overdueInstallments as $payment) {
            $notifications[] = [
                'type' => 'danger',
                'icon' => '🚨',
                'title' => 'قسط متأخر (' . $payment['days_overdue'] . ' يوم)',
                'message' => $payment['entity_name'] . ' - ' . date('Y/m/d', strtotime($payment['due_date'])),
                'link' => hasPermission('installments.pay') ? 'modules/installments/pay.php?plan_id=' . $payment['plan_id'] : 'modules/installments/index.php',
                'priority' => 0,
                'category' => 'installments'
            ];
        }
    }
    
    // 4. Tomorrow's suppliers - ALL (Only if user has suppliers.view)
    if (hasPermission('suppliers.view')) {
        $tomorrowDay = getTomorrowDayName();
        $tomorrowSuppliers = getRows("SELECT id, name, phone, expected_products FROM suppliers WHERE visit_days LIKE ?", ["%$tomorrowDay%"]);
        foreach ($tomorrowSuppliers as $supplier) {
            $notifications[] = [
                'type' => 'info',
                'icon' => '🚚',
                'title' => 'مورد قادم غداً',
                'message' => $supplier['name'] . ($supplier['expected_products'] ? ' - ' . $supplier['expected_products'] : ''),
                'link' => 'modules/suppliers/index.php',
                'priority' => 3,
                'category' => 'suppliers'
            ];
        }
    }
    
    // Sort by priority (lower = more important)
    usort($notifications, function($a, $b) {
        return $a['priority'] - $b['priority'];
    });
    
    return $notifications;
}

$allNotifications = getAllNotifications();

// Count by category
$counts = [
    'installments' => 0,
    'stock' => 0,
    'suppliers' => 0
];
foreach ($allNotifications as $n) {
    $cat = $n['category'] ?? 'other';
    if (isset($counts[$cat])) $counts[$cat]++;
}

// Filter by category if specified
$filterCategory = $_GET['category'] ?? '';
$categoryNames = [
    'installments' => 'الأقساط',
    'stock' => 'المخزون',
    'suppliers' => 'الموردين'
];

// Sanitize filterCategory with permissions
if ($filterCategory === 'installments' && !hasPermission('installments.view')) $filterCategory = '';
if ($filterCategory === 'stock' && !hasPermission('products.view')) $filterCategory = '';
if ($filterCategory === 'suppliers' && !hasPermission('suppliers.view')) $filterCategory = '';

if ($filterCategory && isset($counts[$filterCategory])) {
    $allNotifications = array_filter($allNotifications, function($n) use ($filterCategory) {
        return ($n['category'] ?? '') === $filterCategory;
    });
    $allNotifications = array_values($allNotifications);
    $pageTitle = 'إشعارات ' . ($categoryNames[$filterCategory] ?? '');
}

$totalNotifications = count($allNotifications);

// Pagination
$perPage = 15;
$page = max(1, intval($_GET['page'] ?? 1));
$totalPages = max(1, ceil($totalNotifications / $perPage));
$offset = ($page - 1) * $perPage;

// Get current page notifications
$pageNotifications = array_slice($allNotifications, $offset, $perPage);

// Build pagination URL
$paginationUrl = $filterCategory ? "?category=$filterCategory&page=" : "?page=";

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<style>
.notification-item { display: flex; align-items: center; gap: 10px; padding: 8px 12px; border-bottom: 1px solid #e5e7eb; text-decoration: none; color: inherit; transition: background 0.2s; font-size: 0.9em; }
.notification-item:hover { background: #f9fafb; }
.notification-item:last-child { border-bottom: none; }
.notification-item.danger { border-right: 3px solid #dc2626; }
.notification-item.warning { border-right: 3px solid #f59e0b; }
.notification-item.info { border-right: 3px solid #3b82f6; }
.notification-icon { font-size: 1.2em; width: 30px; text-align: center; }
.notification-content { flex: 1; }
.notification-title { font-weight: bold; color: #1f2937; font-size: 0.95em; }
.notification-message { font-size: 0.85em; color: #6b7280; }
.stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; margin-bottom: 15px; }
.stat-box { background: white; border-radius: 8px; padding: 10px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05); cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; text-decoration: none; color: inherit; display: block; }
.stat-box:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
.stat-box.active { box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.5); transform: translateY(-2px); }
.stat-box.danger { border-top: 3px solid #dc2626; }
.stat-box.warning { border-top: 3px solid #f59e0b; }
.stat-box.info { border-top: 3px solid #3b82f6; }
.stat-box.success { border-top: 3px solid #10b981; }
.stat-number { font-size: 1.5em; font-weight: bold; }
.stat-label { color: #6b7280; font-size: 0.8em; }
.notification-list { border: 1px solid #e5e7eb; border-radius: 8px; background: white; }
.empty-section { padding: 30px; text-align: center; color: #6b7280; background: #f9fafb; border-radius: 8px; }
.page-info-bar { display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; background: #f3f4f6; border-radius: 6px; margin-bottom: 10px; font-size: 0.85em; }
.pagination { display: flex; gap: 5px; flex-wrap: wrap; }
.pagination a, .pagination span { padding: 5px 10px; border-radius: 5px; text-decoration: none; font-size: 0.85em; }
.pagination a { background: #e5e7eb; color: #374151; }
.pagination a:hover { background: #8b5cf6; color: white; }
.pagination .current { background: #8b5cf6; color: white; }
.container { padding: 10px !important; }
.card { margin-bottom: 10px !important; }
.card-header { padding: 10px 15px !important; font-size: 0.95em; }
.card-body { padding: 12px !important; }
.filter-label { background: rgba(255,255,255,0.2); padding: 3px 10px; border-radius: 15px; font-size: 0.85em; }
.clear-filter { color: white; margin-right: 10px; font-size: 0.85em; background: rgba(255,255,255,0.2); padding: 3px 8px; border-radius: 4px; text-decoration: none; }
.clear-filter:hover { background: rgba(255,255,255,0.3); }
</style>

<div class="container">
    <div class="card">
        <div class="card-header d-flex justify-between align-center" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white;">
            <span>
                🔔 
                <?php if ($filterCategory): ?>
                <span class="filter-label"><?php echo $categoryNames[$filterCategory] ?? $filterCategory; ?></span>
                <a href="?" class="clear-filter">✕ عرض الكل</a>
                <?php else: ?>
                جميع الإشعارات
                <?php endif; ?>
            </span>
            <a href="<?php echo BASE_URL; ?>index.php" class="btn btn-secondary" style="padding: 5px 12px; font-size: 0.85em;">← رجوع</a>
        </div>
        
        <div class="card-body">
            <!-- Stats Summary - Clickable -->
            <div class="stats-row">
                <?php if (hasPermission('installments.view')): ?>
                <a href="?category=installments" class="stat-box danger <?php echo $filterCategory === 'installments' ? 'active' : ''; ?>">
                    <div class="stat-number"><?php echo $counts['installments']; ?></div>
                    <div class="stat-label">💰 أقساط</div>
                </a>
                <?php endif; ?>
                <?php if (hasPermission('products.view')): ?>
                <a href="?category=stock" class="stat-box warning <?php echo $filterCategory === 'stock' ? 'active' : ''; ?>">
                    <div class="stat-number"><?php echo $counts['stock']; ?></div>
                    <div class="stat-label">⚠️ مخزون</div>
                </a>
                <?php endif; ?>
                <?php if (hasPermission('suppliers.view')): ?>
                <a href="?category=suppliers" class="stat-box info <?php echo $filterCategory === 'suppliers' ? 'active' : ''; ?>">
                    <div class="stat-number"><?php echo $counts['suppliers']; ?></div>
                    <div class="stat-label">🚚 موردين</div>
                </a>
                <?php endif; ?>
            </div>
            
            <?php if (empty($allNotifications)): ?>
            <div class="empty-section">
                <div style="font-size: 2.5em; margin-bottom: 8px;">✅</div>
                <div style="font-size: 1.1em; font-weight: bold;">لا توجد إشعارات!</div>
                <div style="font-size: 0.9em;">كل شيء على ما يرام</div>
            </div>
            <?php else: ?>
            
            <!-- Page Info Bar -->
            <div class="page-info-bar">
                <span>عرض <?php echo $offset + 1; ?> - <?php echo min($offset + $perPage, $totalNotifications); ?> من <?php echo $totalNotifications; ?> إشعار</span>
                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                    <a href="<?php echo $paginationUrl; ?>1">««</a>
                    <a href="<?php echo $paginationUrl . ($page - 1); ?>">«</a>
                    <?php endif; ?>
                    
                    <?php 
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    for ($i = $start; $i <= $end; $i++): 
                    ?>
                    <?php if ($i == $page): ?>
                    <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                    <a href="<?php echo $paginationUrl . $i; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <a href="<?php echo $paginationUrl . ($page + 1); ?>">»</a>
                    <a href="<?php echo $paginationUrl . $totalPages; ?>">»»</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Notifications List -->
            <div class="notification-list">
                <?php foreach ($pageNotifications as $notif): ?>
                <a href="<?php echo BASE_URL . $notif['link']; ?>" class="notification-item <?php echo $notif['type']; ?>">
                    <span class="notification-icon"><?php echo $notif['icon']; ?></span>
                    <div class="notification-content">
                        <div class="notification-title"><?php echo $notif['title']; ?></div>
                        <div class="notification-message"><?php echo $notif['message']; ?></div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            
            <!-- Bottom Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="page-info-bar" style="margin-top: 10px;">
                <span>صفحة <?php echo $page; ?> من <?php echo $totalPages; ?></span>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                    <a href="<?php echo $paginationUrl . ($page - 1); ?>">« السابق</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                    <a href="<?php echo $paginationUrl . ($page + 1); ?>">التالي »</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
