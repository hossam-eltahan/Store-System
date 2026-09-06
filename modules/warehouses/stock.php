<?php
/**
 * Warehouse Stock / Inventory View
 * جرد ورصيد المخزن
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';

requirePermission('warehouses.view');

// Selected warehouse
$warehouseId = (int)($_GET['warehouse_id'] ?? 0);
if ($warehouseId <= 0) {
    $def = getDefaultWarehouse();
    $warehouseId = $def ? (int)$def['id'] : 1;
}

$warehouse = getWarehouseById($warehouseId);
if (!$warehouse) {
    setError('المخزن المطلوب غير موجود');
    redirect('index.php');
}

$pageTitle = 'رصيد مخزن: ' . $warehouse['name'];

// All active warehouses for selector
$allWarehouses = getAllWarehouses(false);

// Filters
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$sql = "SELECT p.*, 
        COALESCE(ws.quantity, 0) as wh_quantity,
        COALESCE(ws.min_stock_level, p.min_stock_level) as wh_min_stock
        FROM products p
        LEFT JOIN warehouse_stock ws ON ws.product_id = p.id AND ws.warehouse_id = ?
        WHERE 1=1";
$params = [$warehouseId];

if (!empty($search)) {
    $sql .= " AND (p.name LIKE ? OR p.code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($statusFilter === 'in_stock') {
    $sql .= " AND COALESCE(ws.quantity, 0) > COALESCE(ws.min_stock_level, p.min_stock_level)";
} elseif ($statusFilter === 'low_stock') {
    $sql .= " AND COALESCE(ws.quantity, 0) > 0 AND COALESCE(ws.quantity, 0) <= COALESCE(ws.min_stock_level, p.min_stock_level)";
} elseif ($statusFilter === 'out_of_stock') {
    $sql .= " AND COALESCE(ws.quantity, 0) = 0";
}

$sql .= " ORDER BY p.name ASC";
$items = getRows($sql, $params);

// Calculate warehouse stats
$allWhItems = getRows("SELECT p.id, COALESCE(ws.quantity, 0) as qty, COALESCE(ws.min_stock_level, p.min_stock_level) as min_lvl
                       FROM products p
                       LEFT JOIN warehouse_stock ws ON ws.product_id = p.id AND ws.warehouse_id = ?", [$warehouseId]);
$totalUnitsInWh = 0;
$inStockCount = 0;
$lowStockCount = 0;
$outStockCount = 0;

foreach ($allWhItems as $row) {
    $q = (int)$row['qty'];
    $m = (int)$row['min_lvl'];
    $totalUnitsInWh += $q;
    if ($q <= 0) {
        $outStockCount++;
    } elseif ($q <= $m) {
        $lowStockCount++;
    } else {
        $inStockCount++;
    }
}

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
        <div>
            <h1 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <span>📦</span> رصيد وجرد: <?php echo htmlspecialchars($warehouse['name']); ?>
                <?php if ((int)$warehouse['is_default'] === 1): ?>
                    <span class="badge" style="background: #e0f2fe; color: #0284c7; font-size: 13px;">⭐ المخزن الافتراضي</span>
                <?php endif; ?>
            </h1>
            <p style="color: var(--text-secondary); margin: 5px 0 0 0; font-size: 14px;">
                كود المخزن: <code><?php echo htmlspecialchars($warehouse['code'] ?? '—'); ?></code> | الموقع: <?php echo htmlspecialchars($warehouse['location'] ?? '—'); ?>
            </p>
        </div>

        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <!-- Warehouse Selector Switcher -->
            <form method="GET" action="" style="margin: 0;">
                <select name="warehouse_id" class="form-control" onchange="this.form.submit()" style="min-width: 200px; font-weight: 600; cursor: pointer;">
                    <?php foreach ($allWarehouses as $w): ?>
                        <option value="<?php echo $w['id']; ?>" <?php echo $w['id'] == $warehouseId ? 'selected' : ''; ?>>
                            🏢 <?php echo htmlspecialchars($w['name']); ?> <?php echo ((int)$w['is_default'] === 1) ? '(الافتراضي)' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <?php if (hasPermission('warehouses.transfer')): ?>
            <a href="transfer.php?from_warehouse_id=<?php echo $warehouseId; ?>" class="btn btn-warning" style="display: inline-flex; align-items: center; gap: 6px;">
                <span>🔄</span> تحويل بضاعة منه
            </a>
            <?php endif; ?>

            <a href="index.php" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px;">
                <span>🔙</span> المخازن
            </a>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid" style="margin-bottom: 20px;">
        <div class="stat-card primary">
            <div class="stat-icon">📊</div>
            <div class="stat-label">إجمالي القطع بالمخزن</div>
            <div class="stat-value"><?php echo number_format($totalUnitsInWh); ?></div>
        </div>
        <div class="stat-card success">
            <div class="stat-icon">✅</div>
            <div class="stat-label">أصناف متوفرة بوفرة</div>
            <div class="stat-value"><?php echo $inStockCount; ?></div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon">⚠️</div>
            <div class="stat-label">أصناف أوشكت على النفاد</div>
            <div class="stat-value"><?php echo $lowStockCount; ?></div>
        </div>
        <div class="stat-card danger">
            <div class="stat-icon">❌</div>
            <div class="stat-label">أصناف نفدت تماماً</div>
            <div class="stat-value"><?php echo $outStockCount; ?></div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card" style="margin-bottom: 20px;">
        <div class="card-body" style="padding: 15px 20px;">
            <form method="GET" action="" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap; margin: 0;">
                <input type="hidden" name="warehouse_id" value="<?php echo $warehouseId; ?>">
                
                <div style="flex: 1; min-width: 220px;">
                    <input type="text" name="search" class="form-control" placeholder="🔍 بحث باسم المنتج أو الكود..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>

                <div style="min-width: 180px;">
                    <select name="status" class="form-control">
                        <option value="">-- كل حالات الرصيد --</option>
                        <option value="in_stock" <?php echo $statusFilter === 'in_stock' ? 'selected' : ''; ?>>متوفر بكثرة</option>
                        <option value="low_stock" <?php echo $statusFilter === 'low_stock' ? 'selected' : ''; ?>>أوشك على النفاد</option>
                        <option value="out_of_stock" <?php echo $statusFilter === 'out_of_stock' ? 'selected' : ''; ?>>نفد من هذا المخزن</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary" style="padding: 8px 20px;">تصفية</button>
                <?php if (!empty($search) || !empty($statusFilter)): ?>
                    <a href="stock.php?warehouse_id=<?php echo $warehouseId; ?>" class="btn btn-secondary">إلغاء الفلتر</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Stock Table -->
    <div class="card">
        <div class="card-header" style="background: linear-gradient(135deg, #0284c7, #0369a1); color: white; display: flex; justify-content: space-between; align-items: center;">
            <span style="font-weight: 700;">📋 قائمة المنتجات في <?php echo htmlspecialchars($warehouse['name']); ?></span>
            <span class="badge" style="background: rgba(255,255,255,0.2); padding: 4px 10px; border-radius: 20px;">
                <?php echo count($items); ?> منتج
            </span>
        </div>
        <div class="card-body" style="padding: 0; overflow-x: auto;">
            <table class="table" style="margin: 0; vertical-align: middle;">
                <thead>
                    <tr style="background: var(--bg-primary);">
                        <th style="width: 50px;">#</th>
                        <th>كود المنتج</th>
                        <th>اسم المنتج</th>
                        <th>الوحدة</th>
                        <th>الرصيد بهذا المخزن</th>
                        <th>حد الطلب الأدنى</th>
                        <th>حالة التوفر بالمخزن</th>
                        <th>إجمالي الرصيد بكل الفروع</th>
                        <th style="width: 140px; text-align: center;">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                            لا توجد منتجات مطابقة لخيارات البحث
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($items as $idx => $p): ?>
                        <?php
                            $qty = (int)$p['wh_quantity'];
                            $minStock = (int)$p['wh_min_stock'];
                            $totalAllWh = (int)$p['stock_quantity'];

                            if ($qty <= 0) {
                                $badgeClass = 'background: #fee2e2; color: #b91c1c;';
                                $badgeText = '❌ نفد';
                            } elseif ($qty <= $minStock) {
                                $badgeClass = 'background: #fef3c7; color: #b45309;';
                                $badgeText = '⚠️ منخفض';
                            } else {
                                $badgeClass = 'background: #dcfce7; color: #15803d;';
                                $badgeText = '✅ متوفر';
                            }
                        ?>
                        <tr>
                            <td><?php echo $idx + 1; ?></td>
                            <td>
                                <code style="background: var(--bg-primary); padding: 2px 6px; border-radius: 4px; font-weight: 600;">
                                    <?php echo htmlspecialchars($p['code']); ?>
                                </code>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($p['name']); ?></strong>
                            </td>
                            <td><?php echo htmlspecialchars($p['unit'] ?? 'قطعة'); ?></td>
                            <td>
                                <span style="font-size: 16px; font-weight: 700; color: <?php echo $qty > 0 ? '#0284c7' : '#dc2626'; ?>;">
                                    <?php echo number_format($qty); ?>
                                </span>
                            </td>
                            <td><?php echo number_format($minStock); ?></td>
                            <td>
                                <span class="badge" style="<?php echo $badgeClass; ?> padding: 4px 10px; border-radius: 12px; font-weight: 600;">
                                    <?php echo $badgeText; ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-weight: 600; color: #475569;">
                                    <?php echo number_format($totalAllWh); ?>
                                </span>
                                <small style="color: var(--text-secondary);">قطعة</small>
                            </td>
                            <td style="text-align: center;">
                                <?php if (hasPermission('warehouses.transfer')): ?>
                                <a href="transfer.php?from_warehouse_id=<?php echo $warehouseId; ?>&product_id=<?php echo $p['id']; ?>" 
                                   class="btn btn-sm btn-outline-warning" title="تحويل هذا الصنف إلى مخزن آخر"
                                   style="display: inline-flex; align-items: center; gap: 4px; font-size: 12px;">
                                    <span>🔄</span> تحويل
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
