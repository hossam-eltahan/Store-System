<?php
/**
 * Dashboard - Main Page
 * نظام إدارة محل أجهزة منزلية
 */

require_once 'config/database.php';
require_once 'config/settings.php';
require_once 'config/auth.php';
requireLogin();

$pageTitle = 'الرئيسية - لوحة التحكم';

// Permissions checks for dashboard elements
$canViewSuppliers = hasPermission('suppliers.view');
$canViewProducts = hasPermission('products.view');
$canViewInstallments = hasPermission('installments.view');
$canViewSalesStats = isAdmin() || hasPermission('invoices.sale.view');
$canViewWarehouses = hasPermission('warehouses.view');

// Auto backup check - create backup if no backup today (only for admin or settings.backup)
if (hasPermission('settings.backup')) {
    $lastBackupDate = getSetting('last_backup_date', '');
    if ($lastBackupDate !== date('Y-m-d')) {
        $autoBackupUrl = BASE_URL . 'modules/settings/backup.php?auto=1';
        @file_get_contents($autoBackupUrl);
    }
}

// Get today's statistics only if authorized
$todaySuppliersCount = 0;
if ($canViewSuppliers) {
    $todayDay = getTodayDayName();
    $todaySuppliers = getRows("SELECT * FROM suppliers WHERE visit_days LIKE ?", ["%$todayDay%"]);
    $todaySuppliersCount = count($todaySuppliers);
}

$warehouseCount = 0;
if ($canViewWarehouses) {
    $whRow = getRow("SELECT COUNT(*) as count FROM warehouses WHERE is_active = 1");
    $warehouseCount = intval($whRow['count'] ?? 0);
}

$lowStockCount = ['count' => 0];
if ($canViewProducts) {
    $lowStockCount = getRow("SELECT COUNT(*) as count FROM products WHERE stock_quantity <= min_stock_level");
}

$pendingInstallments = ['count' => 0];
$overdueInstallments = ['count' => 0];
if ($canViewInstallments) {
    // Update overdue installments first
    execute("UPDATE installment_payments SET status = 'overdue' WHERE status = 'pending' AND due_date < CURDATE()");
    $pendingInstallments = getRow("SELECT COUNT(*) as count FROM installment_payments WHERE due_date = CURDATE() AND status = 'pending'");
    $overdueInstallments = getRow("SELECT COUNT(*) as count FROM installment_payments WHERE status = 'overdue'");
}

// Top 3 best selling products - only if authorized
$topProducts = [];
if ($canViewSalesStats) {
    $topProducts = getRows("
        SELECT p.name, p.code, SUM(ii.quantity) as total_sold
        FROM invoice_items ii
        JOIN products p ON ii.product_id = p.id
        JOIN invoices i ON ii.invoice_id = i.id
        WHERE i.type = 'sale'
        GROUP BY ii.product_id
        ORDER BY total_sold DESC
        LIMIT 3
    ");
}

// Last 7 days invoice count (not money) - only if authorized
$invoiceData = [];
$maxInvoice = 1;
if ($canViewSalesStats) {
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $dayName = ['أحد', 'إثنين', 'ثلاثاء', 'أربعاء', 'خميس', 'جمعة', 'سبت'][date('w', strtotime($date))];
        $count = getRow("SELECT COUNT(*) as count FROM invoices WHERE type = 'sale' AND DATE(date) = ?", [$date]);
        $invoiceData[] = [
            'day' => $dayName,
            'count' => intval($count['count'] ?? 0)
        ];
    }
    $maxInvoice = max(array_column($invoiceData, 'count')) ?: 1;
}

// Stock status distribution - only if authorized
$normalStock = ['count' => 0];
$lowStock = ['count' => 0];
$outOfStock = ['count' => 0];
$totalProducts = 0;
if ($canViewProducts) {
    $normalStock = getRow("SELECT COUNT(*) as count FROM products WHERE stock_quantity > min_stock_level");
    $lowStock = getRow("SELECT COUNT(*) as count FROM products WHERE stock_quantity <= min_stock_level AND stock_quantity > 0");
    $outOfStock = getRow("SELECT COUNT(*) as count FROM products WHERE stock_quantity = 0");
    $totalProducts = ($normalStock['count'] ?? 0) + ($lowStock['count'] ?? 0) + ($outOfStock['count'] ?? 0);
}

// Count available top stat cards
$hasAnyStatCard = $canViewSuppliers || $canViewProducts || $canViewInstallments || $canViewWarehouses;

include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
        <h1 class="mb-0" style="font-size: 1.6em;">👋 مرحباً، <?php echo htmlspecialchars(getCurrentUserName()); ?></h1>
        <span style="background: rgba(59, 130, 246, 0.1); color: #3b82f6; padding: 4px 12px; border-radius: 20px; font-weight: 600; font-size: 0.85em;">
            <?php echo isAdmin() ? '👑 مدير النظام' : '👤 موظف'; ?>
        </span>
    </div>
    
    <!-- Statistics Cards (Only shown if user has permissions) -->
    <?php if ($hasAnyStatCard): ?>
    <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 15px;">
        <?php if ($canViewWarehouses): ?>
        <a href="modules/warehouses/index.php" class="stat-card clickable info" style="text-decoration: none; color: inherit;">
            <div class="stat-icon">🏢</div>
            <div class="stat-label">المخازن النشطة</div>
            <div class="stat-value"><?php echo $warehouseCount; ?></div>
        </a>
        <?php endif; ?>

        <?php if ($canViewSuppliers): ?>
        <a href="modules/suppliers/index.php?filter=today" class="stat-card clickable <?php echo $todaySuppliersCount > 0 ? 'info' : 'success'; ?>" style="text-decoration: none; color: inherit;">
            <div class="stat-icon">🚚</div>
            <div class="stat-label">موردين قادمين اليوم</div>
            <div class="stat-value"><?php echo $todaySuppliersCount; ?></div>
        </a>
        <?php endif; ?>
        
        <?php if ($canViewProducts): ?>
        <a href="modules/products/index.php?filter=low_stock" class="stat-card clickable <?php echo $lowStockCount['count'] > 0 ? 'warning' : 'success'; ?>" style="text-decoration: none; color: inherit;">
            <div class="stat-icon">📦</div>
            <div class="stat-label">حالة المخزون المنخفض</div>
            <div class="stat-value"><?php echo $lowStockCount['count']; ?></div>
        </a>
        <?php endif; ?>
        
        <?php if ($canViewInstallments): ?>
        <a href="modules/installments/index.php" class="stat-card clickable <?php echo $pendingInstallments['count'] > 0 ? 'danger' : 'success'; ?>" style="text-decoration: none; color: inherit;">
            <div class="stat-icon">⏰</div>
            <div class="stat-label">أقساط اليوم</div>
            <div class="stat-value"><?php echo $pendingInstallments['count']; ?></div>
        </a>
        
        <?php if ($overdueInstallments['count'] > 0): ?>
        <a href="modules/installments/index.php?status=overdue" class="stat-card clickable danger" style="text-decoration: none; color: inherit;">
            <div class="stat-icon">🚨</div>
            <div class="stat-label">أقساط متأخرة</div>
            <div class="stat-value"><?php echo $overdueInstallments['count']; ?></div>
        </a>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- Main Content Grid -->
    <div class="grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 15px; margin-top: 15px;">
        <!-- Quick Actions Card -->
        <div class="card">
            <div class="card-header" style="padding: 10px 14px; font-weight: 700;">⚡ إجراءات سريعة مصرح بها</div>
            <div class="card-body" style="padding: 12px;">
                <?php
                // Build list of permitted primary actions
                $primaryActions = [];
                if (hasPermission('invoices.sale.create')) {
                    $primaryActions[] = '<a href="modules/invoices/sale.php" class="action-btn sale">🧾 فاتورة بيع</a>';
                }
                if (hasPermission('invoices.purchase.create')) {
                    $primaryActions[] = '<a href="modules/invoices/purchase.php" class="action-btn purchase">📦 فاتورة شراء</a>';
                }
                if (hasPermission('warehouses.transfer')) {
                    $primaryActions[] = '<a href="modules/warehouses/transfer.php" class="action-btn secondary" style="background: linear-gradient(135deg, #0d9488, #0f766e); color: white;">🔄 تحويل مخزون</a>';
                }
                if (hasAnyPermission(['returns.create_customer', 'returns.create_supplier'])) {
                    $primaryActions[] = '<a href="modules/returns/create.php" class="action-btn return">🔄 إنشاء مرتجع</a>';
                }
                if (hasPermission('payments.customer')) {
                    $primaryActions[] = '<a href="modules/payments/pay_customer.php" class="action-btn pay">💵 تحصيل من عميل</a>';
                }
                if (hasPermission('payments.supplier')) {
                    $primaryActions[] = '<a href="modules/payments/pay_supplier.php" class="action-btn pay">💸 دفع لمورد</a>';
                }
                if (hasPermission('installments.view')) {
                    $primaryActions[] = '<a href="modules/installments/index.php" class="action-btn installment">📅 الأقساط</a>';
                }
                if (hasPermission('installments.create')) {
                    $primaryActions[] = '<a href="modules/installments/create.php" class="action-btn installment">➕ إنشاء قسط</a>';
                }
                if (hasAnyPermission(['invoices.sale.view', 'invoices.purchase.view'])) {
                    $primaryActions[] = '<a href="modules/invoices/list.php" class="action-btn secondary">📋 الفواتير</a>';
                }

                // Build list of permitted secondary actions
                $secondaryActions = [];
                if (hasPermission('products.add')) {
                    $secondaryActions[] = '<a href="modules/products/add.php" class="action-btn-sm">➕ صنف</a>';
                }
                if (hasPermission('warehouses.view')) {
                    $secondaryActions[] = '<a href="modules/warehouses/index.php" class="action-btn-sm">🏢 المخازن</a>';
                }
                if (hasPermission('warehouses.manage')) {
                    $secondaryActions[] = '<a href="modules/warehouses/add.php" class="action-btn-sm">➕ مخزن</a>';
                }
                if (hasPermission('customers.add')) {
                    $secondaryActions[] = '<a href="modules/customers/add.php" class="action-btn-sm">👤 عميل</a>';
                }
                if (hasPermission('suppliers.add')) {
                    $secondaryActions[] = '<a href="modules/suppliers/add.php" class="action-btn-sm">🚚 مورد</a>';
                }
                if (isAdmin()) {
                    $secondaryActions[] = '<a href="modules/reports/index.php" class="action-btn-sm">📊 تقارير</a>';
                } else {
                    if (hasPermission('reports.view')) {
                        $secondaryActions[] = '<a href="modules/reports/index.php" class="action-btn-sm">📊 تقاريري</a>';
                    }
                    if (hasPermission('reports.statement')) {
                        $secondaryActions[] = '<a href="modules/reports/index.php?type=statement" class="action-btn-sm">🧾 كشف حساب</a>';
                    }
                }
                if (hasPermission('settings.backup')) {
                    $secondaryActions[] = '<a href="modules/settings/backup.php" class="action-btn-sm">💾 نسخ احتياطي</a>';
                }
                if (hasPermission('settings.manage')) {
                    $secondaryActions[] = '<a href="modules/settings/index.php" class="action-btn-sm">⚙️ إعدادات</a>';
                }
                ?>

                <?php if (!empty($primaryActions)): ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 8px;">
                    <?php echo implode("\n", $primaryActions); ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($primaryActions) && !empty($secondaryActions)): ?>
                <hr style="margin: 10px 0; border: none; border-top: 1px solid #e5e7eb;">
                <?php endif; ?>

                <?php if (!empty($secondaryActions)): ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(95px, 1fr)); gap: 8px;">
                    <?php echo implode("\n", $secondaryActions); ?>
                </div>
                <?php endif; ?>

                <?php if (empty($primaryActions) && empty($secondaryActions)): ?>
                <div style="text-align: center; padding: 20px; color: #6b7280;">
                    <div style="font-size: 1.8em; margin-bottom: 6px;">🔒</div>
                    <div>لا توجد إجراءات سريعة مصرح لك بها حالياً. تواصل مع المدير.</div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Top 3 Products (Only if permitted) -->
        <?php if ($canViewSalesStats): ?>
        <div class="card">
            <div class="card-header" style="background: linear-gradient(135deg, #f59e0b, #d97706); color: white; padding: 10px 14px; font-weight: 700;">
                🏆 أفضل 3 منتجات مبيعاً
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($topProducts)): ?>
                <div style="padding: 25px; text-align: center; color: #6b7280;">
                    <div style="font-size: 2em; margin-bottom: 5px;">📦</div>
                    <div>لا توجد مبيعات بعد</div>
                </div>
                <?php else: ?>
                <div class="top-products-list">
                    <?php foreach ($topProducts as $index => $product): ?>
                    <div class="top-product-item">
                        <div class="product-rank rank-<?php echo $index + 1; ?>">
                            <?php echo $index + 1; ?>
                        </div>
                        <div class="product-info">
                            <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                            <div class="product-code"><?php echo htmlspecialchars($product['code']); ?></div>
                        </div>
                        <div class="sold-count"><?php echo number_format($product['total_sold']); ?> قطعة</div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Charts Row (Only shown if user has relevant permissions) -->
    <?php if ($canViewSalesStats || $canViewProducts): ?>
    <div class="grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 15px; margin-top: 15px;">
        <!-- Invoice Count Chart -->
        <?php if ($canViewSalesStats): ?>
        <div class="card">
            <div class="card-header" style="background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 10px 14px; font-weight: 700;">
                📊 عدد فواتير البيع (آخر 7 أيام)
            </div>
            <div class="card-body" style="padding: 12px;">
                <div class="mini-chart">
                    <?php foreach ($invoiceData as $day): ?>
                    <div class="mini-bar-container">
                        <div class="mini-bar" style="height: <?php echo ($day['count'] / $maxInvoice) * 60; ?>px;">
                            <span class="mini-value"><?php echo $day['count']; ?></span>
                        </div>
                        <div class="mini-label"><?php echo $day['day']; ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Stock Status Chart -->
        <?php if ($canViewProducts): ?>
        <div class="card">
            <div class="card-header" style="background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; padding: 10px 14px; font-weight: 700;">
                📦 حالة المخزون (<?php echo $totalProducts; ?> صنف)
            </div>
            <div class="card-body" style="padding: 12px;">
                <div class="stock-bars">
                    <div class="stock-row">
                        <span class="stock-label">✅ مخزون كافي</span>
                        <div class="stock-bar-bg">
                            <div class="stock-bar normal" style="width: <?php echo $totalProducts ? (($normalStock['count'] / $totalProducts) * 100) : 0; ?>%;"></div>
                        </div>
                        <span class="stock-count"><?php echo $normalStock['count'] ?? 0; ?></span>
                    </div>
                    <div class="stock-row">
                        <span class="stock-label">⚠️ مخزون منخفض</span>
                        <div class="stock-bar-bg">
                            <div class="stock-bar low" style="width: <?php echo $totalProducts ? (($lowStock['count'] / $totalProducts) * 100) : 0; ?>%;"></div>
                        </div>
                        <span class="stock-count"><?php echo $lowStock['count'] ?? 0; ?></span>
                    </div>
                    <div class="stock-row">
                        <span class="stock-label">🚫 نفذ من المخزون</span>
                        <div class="stock-bar-bg">
                            <div class="stock-bar out" style="width: <?php echo $totalProducts ? (($outOfStock['count'] / $totalProducts) * 100) : 0; ?>%;"></div>
                        </div>
                        <span class="stock-count"><?php echo $outOfStock['count'] ?? 0; ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<style>
/* Compact Dashboard Styles */
.action-btn {
    display: block;
    padding: 10px 8px;
    text-align: center;
    text-decoration: none;
    color: white;
    border-radius: 8px;
    font-size: 0.85em;
    font-weight: 500;
    transition: transform 0.2s, box-shadow 0.2s;
}
.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.action-btn.sale { background: linear-gradient(135deg, #10b981, #059669); }
.action-btn.purchase { background: linear-gradient(135deg, #3b82f6, #2563eb); }
.action-btn.return { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
.action-btn.pay { background: linear-gradient(135deg, #f59e0b, #d97706); }
.action-btn.installment { background: linear-gradient(135deg, #ec4899, #db2777); }
.action-btn.secondary { background: linear-gradient(135deg, #6b7280, #4b5563); }

.action-btn-sm {
    display: block;
    padding: 8px 6px;
    text-align: center;
    text-decoration: none;
    background: #f3f4f6;
    color: #374151;
    border-radius: 6px;
    font-size: 0.8em;
    transition: background 0.2s;
}
.action-btn-sm:hover {
    background: #e5e7eb;
}

/* Top Products Styles */
.top-products-list { padding: 0; }
.top-product-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    border-bottom: 1px solid #e5e7eb;
}
.top-product-item:last-child { border-bottom: none; }
.top-product-item:hover { background: #f9fafb; }

.product-rank {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1em;
    color: white;
}
.rank-1 { background: linear-gradient(135deg, #fbbf24, #f59e0b); }
.rank-2 { background: linear-gradient(135deg, #9ca3af, #6b7280); }
.rank-3 { background: linear-gradient(135deg, #cd7f32, #a0522d); }

.product-info { flex: 1; }
.product-name { font-weight: bold; color: #1f2937; font-size: 0.95em; }
.product-code { font-size: 0.75em; color: #6b7280; }
.sold-count { font-weight: bold; color: #10b981; font-size: 0.9em; }

/* Mini Chart Styles */
.mini-chart {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    height: 80px;
    padding: 5px 0;
}
.mini-bar-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 1;
}
.mini-bar {
    width: 28px;
    background: linear-gradient(180deg, #10b981, #059669);
    border-radius: 4px 4px 0 0;
    min-height: 3px;
    position: relative;
}
.mini-value {
    position: absolute;
    top: -18px;
    font-size: 0.7em;
    color: #374151;
    font-weight: bold;
}
.mini-label {
    margin-top: 5px;
    font-size: 0.7em;
    color: #6b7280;
}

/* Stock Bars Styles */
.stock-bars { padding: 5px 0; }
.stock-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
}
.stock-row:last-child { margin-bottom: 0; }
.stock-label { font-size: 0.8em; width: 110px; }
.stock-bar-bg {
    flex: 1;
    height: 16px;
    background: #e5e7eb;
    border-radius: 8px;
    overflow: hidden;
}
.stock-bar {
    height: 100%;
    border-radius: 8px;
    transition: width 0.5s;
}
.stock-bar.normal { background: linear-gradient(90deg, #10b981, #059669); }
.stock-bar.low { background: linear-gradient(90deg, #f59e0b, #d97706); }
.stock-bar.out { background: linear-gradient(90deg, #ef4444, #dc2626); }
.stock-count {
    font-size: 0.85em;
    font-weight: bold;
    width: 35px;
    text-align: left;
}
</style>

<?php include 'includes/footer.php'; ?>
