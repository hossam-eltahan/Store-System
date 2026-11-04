<?php
/**
 * Quick supplies history (سجل التوريد السريع)
 * Lists stock increases created without a purchase invoice.
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';

requirePermission('warehouses.supply');

$pageTitle = 'سجل التوريد السريع';

$warehouses = getAllWarehouses(false);
$suppliers = getRows("SELECT id, name FROM suppliers ORDER BY name ASC");

$search = trim($_GET['search'] ?? '');
$warehouseId = (int)($_GET['warehouse_id'] ?? 0);
$supplierId = (int)($_GET['supplier_id'] ?? 0);
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = '';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = '';
}

$where = ["m.movement_type = 'supply'"];
$params = [];

if ($search !== '') {
    $where[] = '(' . search_like_expr('p.name') . ' LIKE ? OR ' . search_like_expr('p.code') . ' LIKE ?)';
    $term = search_like_term($search);
    $params[] = $term;
    $params[] = $term;
}
if ($warehouseId > 0) {
    $where[] = 'm.warehouse_id = ?';
    $params[] = $warehouseId;
}
if ($supplierId > 0) {
    $where[] = 'm.supplier_id = ?';
    $params[] = $supplierId;
}
if ($dateFrom !== '') {
    $where[] = 'DATE(m.created_at) >= ?';
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $where[] = 'DATE(m.created_at) <= ?';
    $params[] = $dateTo;
}

$whereSql = implode(' AND ', $where);

$summary = getRow(
    "SELECT COUNT(*) AS total_count,
            COALESCE(SUM(m.quantity), 0) AS total_units,
            COALESCE(SUM(m.quantity * COALESCE(m.unit_cost, 0)), 0) AS total_value
     FROM product_stock_movements m
     LEFT JOIN products p ON p.id = m.product_id
     WHERE $whereSql",
    $params
);

$perPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$totalRows = (int)($summary['total_count'] ?? 0);
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$movements = getRows(
    "SELECT m.*,
            p.name AS product_name, p.code AS product_code, p.unit AS product_unit,
            w.name AS warehouse_name,
            s.name AS supplier_name,
            u.full_name AS user_name
     FROM product_stock_movements m
     LEFT JOIN products p ON p.id = m.product_id
     LEFT JOIN warehouses w ON w.id = m.warehouse_id
     LEFT JOIN suppliers s ON s.id = m.supplier_id
     LEFT JOIN users u ON u.id = m.user_id
     WHERE $whereSql
     ORDER BY m.id DESC
     LIMIT $perPage OFFSET $offset",
    $params
);

$queryParams = [
    'search' => $search,
    'warehouse_id' => $warehouseId > 0 ? $warehouseId : '',
    'supplier_id' => $supplierId > 0 ? $supplierId : '',
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
];
$queryParams = array_filter($queryParams, function ($value) {
    return $value !== '' && $value !== null;
});

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
        <div>
            <h1 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <span>🚚</span> سجل التوريد السريع
            </h1>
            <p style="color: var(--text-secondary); margin: 5px 0 0 0; font-size: 14px;">
                كل عمليات زيادة المخزون التي تمت بدون فاتورة شراء
            </p>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="../products/index.php" class="btn btn-success" style="display: inline-flex; align-items: center; gap: 5px;">
                <span>➕</span> توريد سريع جديد
            </a>
            <a href="index.php" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 5px;">
                <span>🏢</span> المخازن
            </a>
        </div>
    </div>

    <div class="stats-grid" style="margin-bottom: 20px;">
        <div class="stat-card info">
            <div class="stat-icon">🚚</div>
            <div class="stat-label">عدد عمليات التوريد</div>
            <div class="stat-value"><?php echo number_format((int)($summary['total_count'] ?? 0)); ?></div>
        </div>
        <div class="stat-card success">
            <div class="stat-icon">📦</div>
            <div class="stat-label">إجمالي الوحدات المضافة</div>
            <div class="stat-value"><?php echo number_format((int)($summary['total_units'] ?? 0)); ?></div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon">💰</div>
            <div class="stat-label">إجمالي القيمة (تقريبي)</div>
            <div class="stat-value" style="font-size: 1.2em;"><?php echo formatCurrency((float)($summary['total_value'] ?? 0)); ?></div>
        </div>
    </div>

    <div class="card" style="margin-bottom: 20px;">
        <div class="card-body" style="padding: 15px 20px;">
            <form method="GET" action="" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; margin: 0;">
                <div style="flex: 1; min-width: 200px;">
                    <label class="form-label" style="font-size: 12px; font-weight: 700; margin-bottom: 4px; display: block;">بحث</label>
                    <input type="text" name="search" class="form-control" placeholder="🔍 اسم الصنف أو الكود..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div style="min-width: 160px;">
                    <label class="form-label" style="font-size: 12px; font-weight: 700; margin-bottom: 4px; display: block;">المخزن</label>
                    <select name="warehouse_id" class="form-control">
                        <option value="">كل المخازن</option>
                        <?php foreach ($warehouses as $wh): ?>
                            <option value="<?php echo (int)$wh['id']; ?>" <?php echo $warehouseId === (int)$wh['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($wh['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="min-width: 160px;">
                    <label class="form-label" style="font-size: 12px; font-weight: 700; margin-bottom: 4px; display: block;">المورد</label>
                    <select name="supplier_id" class="form-control">
                        <option value="">كل الموردين</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?php echo (int)$supplier['id']; ?>" <?php echo $supplierId === (int)$supplier['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($supplier['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="min-width: 140px;">
                    <label class="form-label" style="font-size: 12px; font-weight: 700; margin-bottom: 4px; display: block;">من تاريخ</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($dateFrom); ?>">
                </div>
                <div style="min-width: 140px;">
                    <label class="form-label" style="font-size: 12px; font-weight: 700; margin-bottom: 4px; display: block;">إلى تاريخ</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($dateTo); ?>">
                </div>
                <div style="display: flex; gap: 8px;">
                    <button type="submit" class="btn btn-primary">تصفية</button>
                    <?php if ($queryParams): ?>
                        <a href="supplies.php" class="btn btn-secondary">إلغاء</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 10px 14px; color: #166534; font-size: 13px; font-weight: 700; margin-bottom: 15px;">
        ✅ التوريد السريع يزيد رصيد المخزن فقط — لا ينشئ فاتورة شراء ولا يضيف مديونية على المورد.
    </div>

    <div class="card">
        <div class="card-header" style="background: linear-gradient(135deg, #10b981, #059669); color: white; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
            <span style="font-weight: 700;">📋 حركات التوريد</span>
            <span class="badge" style="background: rgba(255,255,255,0.2); padding: 4px 10px; border-radius: 20px;">
                <?php echo number_format($totalRows); ?> حركة
            </span>
        </div>
        <div class="card-body" style="padding: 0; overflow-x: auto;">
            <table class="table" style="margin: 0; vertical-align: middle;">
                <thead>
                    <tr style="background: var(--bg-primary);">
                        <th style="width: 50px;">#</th>
                        <th>التاريخ</th>
                        <th>الصنف</th>
                        <th>المخزن</th>
                        <th>الكمية</th>
                        <th>قبل</th>
                        <th>بعد</th>
                        <th>تكلفة الوحدة</th>
                        <th>القيمة</th>
                        <th>المورد</th>
                        <th>بواسطة</th>
                        <th>ملاحظات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($movements)): ?>
                    <tr>
                        <td colspan="12" style="text-align: center; padding: 45px; color: var(--text-secondary);">
                            <div style="font-size: 36px; margin-bottom: 10px;">🚚</div>
                            لا توجد عمليات توريد سريع مطابقة
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($movements as $index => $movement): ?>
                        <?php
                            $unitCost = $movement['unit_cost'] !== null ? (float)$movement['unit_cost'] : 0.0;
                            $lineValue = $unitCost * (int)$movement['quantity'];
                        ?>
                        <tr>
                            <td><?php echo $offset + $index + 1; ?></td>
                            <td style="white-space: nowrap;">
                                <div style="font-weight: 700; font-size: 13px;"><?php echo date('Y-m-d', strtotime($movement['created_at'])); ?></div>
                                <small style="color: var(--text-secondary);"><?php echo date('H:i', strtotime($movement['created_at'])); ?></small>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($movement['product_name'] ?? '—'); ?></strong>
                                <?php if (!empty($movement['product_code'])): ?>
                                    <div><code style="font-size: 11px;"><?php echo htmlspecialchars($movement['product_code']); ?></code></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($movement['warehouse_name'] ?? '—'); ?></td>
                            <td>
                                <span style="font-size: 16px; font-weight: 800; color: #059669;">
                                    +<?php echo number_format((int)$movement['quantity']); ?>
                                </span>
                                <small style="color: var(--text-secondary);"><?php echo htmlspecialchars($movement['unit'] ?: ($movement['product_unit'] ?? '')); ?></small>
                            </td>
                            <td><?php echo number_format((int)$movement['balance_before']); ?></td>
                            <td><strong style="color: #0284c7;"><?php echo number_format((int)$movement['balance_after']); ?></strong></td>
                            <td><?php echo $unitCost > 0 ? formatCurrency($unitCost) : '—'; ?></td>
                            <td><?php echo $lineValue > 0 ? formatCurrency($lineValue) : '—'; ?></td>
                            <td><?php echo htmlspecialchars($movement['supplier_name'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($movement['user_name'] ?? '—'); ?></td>
                            <td style="max-width: 260px; font-size: 12px; color: var(--text-secondary);">
                                <?php echo htmlspecialchars($movement['reason'] ?? ''); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($totalPages > 1): ?>
    <div style="display: flex; justify-content: center; align-items: center; gap: 8px; margin-top: 18px; flex-wrap: wrap;">
        <?php
            $pageUrl = function ($targetPage) use ($queryParams) {
                $queryParams['page'] = $targetPage;
                return 'supplies.php?' . http_build_query($queryParams);
            };
        ?>
        <?php if ($page > 1): ?>
            <a href="<?php echo htmlspecialchars($pageUrl($page - 1)); ?>" class="btn btn-secondary btn-sm">← السابق</a>
        <?php endif; ?>
        <span class="badge badge-info">صفحة <?php echo $page; ?> من <?php echo $totalPages; ?></span>
        <?php if ($page < $totalPages): ?>
            <a href="<?php echo htmlspecialchars($pageUrl($page + 1)); ?>" class="btn btn-secondary btn-sm">التالي →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>
