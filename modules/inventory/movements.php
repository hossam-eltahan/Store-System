<?php
/**
 * Product Stock Movements Ledger + manual stock adjustment
 * سجل حركات المخزون (إضافة فقط — لا تعديل ولا حذف للتاريخ)
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requirePermission('inventory.view');

$pageTitle = 'سجل حركات المخزون';

// ---- Manual adjustment (append-only correction) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_adjust'])) {
    requirePermission('inventory.adjust');
    try {
        beginTransaction();
        $adjProductId = (int)($_POST['adj_product_id'] ?? 0);
        $adjWarehouseId = (int)($_POST['adj_warehouse_id'] ?? 0);
        $adjQtyRaw = trim($_POST['adj_qty'] ?? '');
        $adjReason = sanitize(trim($_POST['adj_reason'] ?? ''));
        if ($adjProductId <= 0 || $adjWarehouseId <= 0 || $adjQtyRaw === '' || !is_numeric($adjQtyRaw)) {
            throw new Exception('بيانات التسوية غير مكتملة');
        }
        if ($adjReason === '') {
            throw new Exception('يجب كتابة سبب التسوية');
        }
        $newQty = max(0, (int)$adjQtyRaw);
        $product = getRow("SELECT id, name, unit FROM products WHERE id = ?", [$adjProductId]);
        if (!$product) {
            throw new Exception('الصنف غير موجود');
        }
        $oldQty = getWarehouseStock($adjWarehouseId, $adjProductId);
        $delta = $newQty - $oldQty;
        updateWarehouseStock($adjWarehouseId, $adjProductId, $newQty, 'set');
        if ($delta !== 0) {
            recordProductMovement($adjProductId, $adjWarehouseId, 'adjustment',
                $delta > 0 ? $delta : 0, $delta < 0 ? -$delta : 0,
                $product['unit'], null, null, $adjReason);
        }
        commit();
        logActivity('تسوية مخزون', "تسوية {$product['name']}: من {$oldQty} إلى {$newQty} — {$adjReason}", $_SESSION['full_name'] ?? '');
        setSuccess('تم تسجيل التسوية كحركة جديدة بنجاح');
        redirect('movements.php?product_id=' . $adjProductId);
    } catch (Exception $e) {
        rollback();
        setError('تعذرت التسوية: ' . $e->getMessage());
    }
}

$filterProduct = (int)($_GET['product_id'] ?? 0);
$filterType = $_GET['type'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$products = getRows("SELECT id, code, name, unit FROM products ORDER BY name");
$warehouses = getAllWarehouses(false);

$sql = "SELECT m.*, p.name AS product_name, p.code AS product_code, w.name AS warehouse_name, u.full_name AS user_name
        FROM product_stock_movements m
        LEFT JOIN products p ON p.id = m.product_id
        LEFT JOIN warehouses w ON w.id = m.warehouse_id
        LEFT JOIN users u ON u.id = m.user_id
        WHERE 1=1";
$params = [];
if ($filterProduct > 0) {
    $sql .= " AND m.product_id = ?";
    $params[] = $filterProduct;
}
if ($filterType !== '') {
    $sql .= " AND m.movement_type = ?";
    $params[] = $filterType;
}
if ($dateFrom !== '') {
    $sql .= " AND DATE(m.created_at) >= ?";
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $sql .= " AND DATE(m.created_at) <= ?";
    $params[] = $dateTo;
}
$sql .= " ORDER BY m.id DESC LIMIT 200";
$moves = getRows($sql, $params);

$typeLabels = [
    'opening' => 'رصيد افتتاحي', 'purchase' => 'شراء', 'sale' => 'بيع',
    'customer_return' => 'مرتجع عميل', 'supplier_return' => 'مرتجع مورد',
    'adjustment' => 'تسوية', 'transfer_in' => 'تحويل وارد', 'transfer_out' => 'تحويل صادر',
    'reversal' => 'عكس قيد',
];

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>📜 سجل حركات المخزون</span>
            <a href="index.php" class="btn btn-secondary">🏭 المخزون</a>
        </div>
        <div class="card-body">
            <div class="alert alert-info" style="margin-bottom: 15px;">
                <strong>📌 يعني إيه الصفحة دي؟</strong>
                كل زيادة أو نقص في مخزون أي صنف بتتسجل هنا سطر بسطر:
                <strong>شراء</strong> (فاتورة معتمدة) • <strong>بيع</strong> (كاشير) •
                <strong>رصيد افتتاحي</strong> • <strong>تسوية</strong> (جرد/تالف) •
                <strong>عكس قيد</strong> (تعديل أو حذف فاتورة).
                كل سطر بيوضح الداخل والخارج والرصيد بعد الحركة والمرجع (رقم الفاتورة) والمستخدم اللي نفذها —
                والسجل للمراجعة فقط: ممنوع التعديل أو الحذف، وأي تصحيح بيتسجل كحركة جديدة من جزء التسوية تحت.
            </div>
            <form method="GET" class="mb-1">
                <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 15px;">
                    <select name="product_id" class="form-control" style="flex: 2; min-width: 180px;" onchange="this.form.submit()">
                        <option value="0">كل الأصناف</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo $filterProduct == $p['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['name']); ?> (<?php echo htmlspecialchars($p['code']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="type" class="form-control" style="flex: 1; min-width: 140px;" onchange="this.form.submit()">
                        <option value="">كل الأنواع</option>
                        <?php foreach ($typeLabels as $k => $lbl): ?>
                            <option value="<?php echo $k; ?>" <?php echo $filterType === $k ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($dateFrom); ?>" onchange="this.form.submit()">
                    <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($dateTo); ?>" onchange="this.form.submit()">
                    <a href="movements.php" class="btn btn-secondary">↺</a>
                </div>
            </form>

            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>التاريخ والوقت</th>
                            <th>الصنف</th>
                            <th>النوع</th>
                            <th>داخل</th>
                            <th>خارج</th>
                            <th>الرصيد بعد</th>
                            <th>الوحدة</th>
                            <th>المرجع</th>
                            <th>السبب / ملاحظات</th>
                            <th>المستخدم</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($moves)): ?>
                        <tr><td colspan="10" class="text-center">لا توجد حركات مسجلة</td></tr>
                        <?php else: ?>
                        <?php
                        $moveBadge = [
                            'opening' => 'badge-primary', 'purchase' => 'badge-success', 'sale' => 'badge-info',
                            'customer_return' => 'badge-warning', 'supplier_return' => 'badge-warning',
                            'adjustment' => 'badge-secondary', 'transfer_in' => 'badge-info', 'transfer_out' => 'badge-info',
                            'reversal' => 'badge-danger',
                        ];
                        ?>
                        <?php foreach ($moves as $m): ?>
                        <tr>
                            <td style="white-space: nowrap;"><?php echo date('Y/m/d H:i', strtotime($m['created_at'])); ?></td>
                            <td style="text-align: right;"><strong><?php echo htmlspecialchars($m['product_name'] ?? ('#' . $m['product_id'])); ?></strong></td>
                            <td><span class="badge <?php echo $moveBadge[$m['movement_type']] ?? 'badge-info'; ?>"><?php echo $typeLabels[$m['movement_type']] ?? $m['movement_type']; ?></span></td>
                            <td style="color: #059669; font-weight: bold;"><?php echo ((int)$m['qty_in'] > 0) ? '+' . (int)$m['qty_in'] : '—'; ?></td>
                            <td style="color: #dc2626; font-weight: bold;"><?php echo ((int)$m['qty_out'] > 0) ? '−' . (int)$m['qty_out'] : '—'; ?></td>
                            <td><strong><?php echo (int)$m['balance_after']; ?></strong></td>
                            <td><?php echo htmlspecialchars($m['unit'] ?? ''); ?></td>
                            <td>
                                <?php if ($m['reference_type'] === 'purchase_invoice'): ?>
                                    <a href="../invoices/print_purchase.php?id=<?php echo (int)$m['reference_id']; ?>">فاتورة شراء #<?php echo (int)$m['reference_id']; ?></a>
                                <?php elseif (!empty($m['reference_type'])): ?>
                                    <?php echo htmlspecialchars($m['reference_type'] . ' #' . $m['reference_id']); ?>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td style="text-align: right; max-width: 220px;"><?php echo htmlspecialchars($m['reason'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($m['user_name'] ?? ''); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (hasPermission('inventory.adjust')): ?>
            <div class="card" style="margin-top: 20px; border: 2px dashed #d97706;">
                <div class="card-header">⚖️ تسوية يدوية (تُسجل كحركة جديدة — لا تعديل على التاريخ)</div>
                <div class="card-body">
                    <form method="POST" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end;">
                        <div style="flex: 2; min-width: 180px;">
                            <label>الصنف</label>
                            <select name="adj_product_id" class="form-control" required>
                                <option value="">-- اختر --</option>
                                <?php foreach ($products as $p): ?>
                                    <option value="<?php echo $p['id']; ?>" <?php echo $filterProduct == $p['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($p['name']); ?> (<?php echo htmlspecialchars($p['unit']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="flex: 1; min-width: 140px;">
                            <label>المخزن</label>
                            <select name="adj_warehouse_id" class="form-control" required>
                                <?php foreach ($warehouses as $wh): ?>
                                    <option value="<?php echo $wh['id']; ?>"><?php echo htmlspecialchars($wh['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="flex: 1; min-width: 120px;">
                            <label>الرصيد الجديد المطلق</label>
                            <input type="number" min="0" name="adj_qty" class="form-control" required placeholder="مثال: 400">
                        </div>
                        <div style="flex: 2; min-width: 180px;">
                            <label>سبب التسوية *</label>
                            <input type="text" name="adj_reason" class="form-control" required placeholder="جرد فعلي، تالف، ...">
                        </div>
                        <button type="submit" name="do_adjust" class="btn btn-warning" onclick="return confirm('تأكيد التسوية؟ ستُسجل كحركة جديدة في السجل.');">تسجيل التسوية</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
