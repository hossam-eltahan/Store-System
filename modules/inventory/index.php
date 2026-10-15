<?php
/**
 * Warehouse / Stock Overview
 * عرض كل الأصناف والخامات وحالة مخزونها
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requirePermission('inventory.view');

$pageTitle = 'المخزون';

$search = trim($_GET['search'] ?? '');
$defaultWh = getDefaultWarehouse();
$defaultWhId = $defaultWh ? (int)$defaultWh['id'] : 1;
$filterWarehouse = (int)($_GET['warehouse_id'] ?? $defaultWhId);
if ($filterWarehouse <= 0) {
    $filterWarehouse = $defaultWhId;
}
$filterStatus = $_GET['status'] ?? ''; // all|tracked|untracked|low|out

$warehouses = getAllWarehouses(false);

$sql = "SELECT p.id, p.code, p.name, p.unit, p.purchase_unit, p.units_per_purchase,
               p.stock_quantity, p.min_stock_level, p.track_stock, p.is_direct_sale,
               c.name AS category_name,
               COALESCE(ws.quantity, 0) AS wh_qty
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN warehouse_stock ws ON ws.product_id = p.id AND ws.warehouse_id = ?
        WHERE 1=1";
$params = [$filterWarehouse];

if ($search !== '') {
    $sql .= " AND (p.name LIKE ? OR p.code LIKE ? OR p.barcode LIKE ?)";
    $like = "%$search%";
    array_push($params, $like, $like, $like);
}
if ($filterStatus === 'tracked') {
    $sql .= " AND p.track_stock = 1";
} elseif ($filterStatus === 'untracked') {
    $sql .= " AND p.track_stock = 0";
} elseif ($filterStatus === 'low') {
    $sql .= " AND p.track_stock = 1 AND p.stock_quantity > 0 AND p.stock_quantity <= p.min_stock_level";
} elseif ($filterStatus === 'out') {
    $sql .= " AND p.track_stock = 1 AND p.stock_quantity <= 0";
}
$sql .= " ORDER BY p.track_stock DESC, p.stock_quantity ASC, p.name ASC";

$countSql = "SELECT COUNT(*) AS c FROM products p WHERE 1=1";
$countParams = [];
if ($search !== '') {
    $countSql .= " AND (p.name LIKE ? OR p.code LIKE ? OR p.barcode LIKE ?)";
    array_push($countParams, $like, $like, $like);
}
if ($filterStatus === 'tracked') {
    $countSql .= " AND p.track_stock = 1";
} elseif ($filterStatus === 'untracked') {
    $countSql .= " AND p.track_stock = 0";
} elseif ($filterStatus === 'low') {
    $countSql .= " AND p.track_stock = 1 AND p.stock_quantity > 0 AND p.stock_quantity <= p.min_stock_level";
} elseif ($filterStatus === 'out') {
    $countSql .= " AND p.track_stock = 1 AND p.stock_quantity <= 0";
}
$totalItems = (int)(getRow($countSql, $countParams)['c'] ?? 0);
$perPage = 15;
$totalPages = max(1, (int)ceil($totalItems / $perPage));
$page = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
$offset = ($page - 1) * $perPage;
$sql .= " LIMIT $perPage OFFSET $offset";

$rows = getRows($sql, $params);

$statTracked = getRow("SELECT COUNT(*) c FROM products WHERE track_stock = 1")['c'] ?? 0;
$statLow = getRow("SELECT COUNT(*) c FROM products WHERE track_stock = 1 AND stock_quantity > 0 AND stock_quantity <= min_stock_level")['c'] ?? 0;
$statOut = getRow("SELECT COUNT(*) c FROM products WHERE track_stock = 1 AND stock_quantity <= 0")['c'] ?? 0;

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>🏭 المخزون — كل الأصناف والخامات</span>
            <div>
                <a href="movements.php" class="btn btn-info">📜 سجل الحركات</a>
                <?php if (hasPermission('invoices.purchase.create')): ?>
                <a href="../invoices/purchase.php" class="btn btn-success">+ فاتورة شراء</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div style="display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap;">
                <span class="badge badge-primary">أصناف متتبعة: <?php echo (int)$statTracked; ?></span>
                <span class="badge badge-warning">وصلت حد التنبيه: <?php echo (int)$statLow; ?></span>
                <span class="badge badge-danger">نفذت: <?php echo (int)$statOut; ?></span>
            </div>

            <form method="GET" class="mb-1">
                <div class="filter-row" style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 15px;">
                    <input type="text" name="search" class="form-control" style="flex: 2; min-width: 180px;" placeholder="🔍 بحث بالاسم أو الكود أو الباركود" value="<?php echo htmlspecialchars($search); ?>">
                    <select name="warehouse_id" class="form-control" style="flex: 1; min-width: 140px;" onchange="this.form.submit()">
                        <?php foreach ($warehouses as $wh): ?>
                            <option value="<?php echo $wh['id']; ?>" <?php echo $filterWarehouse == $wh['id'] ? 'selected' : ''; ?>>🏢 <?php echo htmlspecialchars($wh['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status" class="form-control" style="flex: 1; min-width: 140px;" onchange="this.form.submit()">
                        <option value="">كل الحالات</option>
                        <option value="tracked" <?php echo $filterStatus === 'tracked' ? 'selected' : ''; ?>>متتبع</option>
                        <option value="untracked" <?php echo $filterStatus === 'untracked' ? 'selected' : ''; ?>>غير متتبع</option>
                        <option value="low" <?php echo $filterStatus === 'low' ? 'selected' : ''; ?>>⚠️ منخفض</option>
                        <option value="out" <?php echo $filterStatus === 'out' ? 'selected' : ''; ?>>🔴 نفذ</option>
                    </select>
                    <button type="submit" class="btn btn-primary">بحث</button>
                </div>
            </form>

            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>الصنف</th>
                            <th>وحدة الشراء</th>
                            <th>المعامل</th>
                            <th>وحدة المخزون</th>
                            <th>رصيد المخزن</th>
                            <th>الإجمالي</th>
                            <th>حد التنبيه</th>
                            <th>الحالة</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                        <tr><td colspan="9" class="text-center">لا توجد أصناف مطابقة</td></tr>
                        <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                        <tr>
                            <td style="text-align: right;">
                                <strong><?php echo htmlspecialchars($r['name']); ?></strong>
                                <br><small style="color: #6b7280;"><?php echo htmlspecialchars($r['code']); ?><?php echo $r['category_name'] ? ' — ' . htmlspecialchars($r['category_name']) : ''; ?></small>
                                <?php if ((int)$r['is_direct_sale'] !== 1): ?><br><small style="color: #7c3aed;">🧪 خامة (لا تباع مباشرة)</small><?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($r['purchase_unit'] ?: '—'); ?></td>
                            <td><?php echo ($r['purchase_unit']) ? '×' . rtrim(rtrim(number_format((float)$r['units_per_purchase'], 4), '0'), '.') : '—'; ?></td>
                            <td><?php echo htmlspecialchars($r['unit']); ?></td>
                            <td><strong><?php echo (int)$r['wh_qty']; ?></strong></td>
                            <td><strong><?php echo (int)$r['stock_quantity']; ?></strong></td>
                            <td><?php echo (int)$r['min_stock_level']; ?></td>
                            <td>
                                <?php if ((int)$r['track_stock'] !== 1): ?>
                                    <span class="badge badge-secondary">التتبع متوقف</span>
                                <?php elseif ((int)$r['stock_quantity'] <= 0): ?>
                                    <span class="badge badge-danger">🔴 نفذ</span>
                                <?php elseif ((int)$r['stock_quantity'] <= (int)$r['min_stock_level']): ?>
                                    <span class="badge badge-warning">⚠️ منخفض</span>
                                <?php else: ?>
                                    <span class="badge badge-success">متاح</span>
                                <?php endif; ?>
                            </td>
                            <td style="white-space: nowrap;">
                                <a href="movements.php?product_id=<?php echo (int)$r['id']; ?>" class="btn btn-info btn-sm" title="سجل الحركات">📜</a>
                                <?php if (hasPermission('products.edit')): ?>
                                <a href="../products/edit.php?id=<?php echo (int)$r['id']; ?>" class="btn btn-secondary btn-sm" title="تعديل الصنف">✏️</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php
            $pagerParams = http_build_query([
                'search' => $search,
                'warehouse_id' => $filterWarehouse,
                'status' => $filterStatus,
            ]);
            ?>
            <div class="d-flex justify-between align-center mb-1" style="margin-top: 12px;">
                <span class="badge badge-primary">إجمالي الأصناف: <?php echo $totalItems; ?></span>
                <div>
                    <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&<?php echo $pagerParams; ?>" class="btn btn-secondary btn-sm">← السابق</a>
                    <?php endif; ?>
                    <span class="badge badge-info">صفحة <?php echo $page; ?> من <?php echo $totalPages; ?></span>
                    <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&<?php echo $pagerParams; ?>" class="btn btn-secondary btn-sm">التالي →</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
