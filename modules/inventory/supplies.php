<?php
/**
 * Quick Supplies Log (توريد مباشر بدون فواتير)
 * Every row can be converted into a formal purchase invoice (stock-neutral).
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requirePermission('inventory.view');

$pageTitle = 'سجل التوريد المباشر';

$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$filterSupplier = (int)($_GET['supplier_id'] ?? 0);
$filterLinked = $_GET['linked'] ?? ''; // ''|yes|no

$suppliers = getRows("SELECT id, name FROM suppliers ORDER BY name");

$where = "m.movement_type = 'supply'";
$whereParams = [];
if ($dateFrom !== '') { $where .= " AND DATE(m.created_at) >= ?"; $whereParams[] = $dateFrom; }
if ($dateTo !== '') { $where .= " AND DATE(m.created_at) <= ?"; $whereParams[] = $dateTo; }
if ($filterSupplier > 0) { $where .= " AND m.supplier_id = ?"; $whereParams[] = $filterSupplier; }
if ($filterLinked === 'yes') { $where .= " AND m.linked_invoice_id IS NOT NULL"; }
elseif ($filterLinked === 'no') { $where .= " AND m.linked_invoice_id IS NULL"; }

$sql = "SELECT m.*, p.name AS product_name, p.code AS product_code,
               w.name AS warehouse_name, u.full_name AS user_name,
               COALESCE(s.name, '-') AS supplier_name,
               i.id AS linked_inv_id, i.invoice_number AS linked_inv_number
        FROM product_stock_movements m
        LEFT JOIN products p ON p.id = m.product_id
        LEFT JOIN warehouses w ON w.id = m.warehouse_id
        LEFT JOIN users u ON u.id = m.user_id
        LEFT JOIN suppliers s ON s.id = m.supplier_id
        LEFT JOIN invoices i ON i.id = m.linked_invoice_id
        WHERE {$where}";
$params = $whereParams;

$perPage = 15;
$totalItems = (int)(getRow("SELECT COUNT(*) c FROM product_stock_movements m WHERE {$where}", $whereParams)['c'] ?? 0);
$totalPages = max(1, (int)ceil($totalItems / $perPage));
$page = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
$offset = ($page - 1) * $perPage;
$sql .= " ORDER BY m.id DESC LIMIT $perPage OFFSET $offset";
$rows = getRows($sql, $params);

$canInvoice = hasPermission('invoices.purchase.create');

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>🚚 سجل التوريد المباشر (بدون فواتير)</span>
            <div>
                <a href="index.php" class="btn btn-secondary">🏭 المخزون</a>
                <?php if ($canInvoice): ?>
                <a href="../invoices/purchase.php" class="btn btn-success">+ فاتورة شراء</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div class="alert alert-info" style="margin-bottom: 15px;">
                التوريد المباشر بضاعة دخلت المخزون <strong>بدون فاتورة</strong> (من زر «🚚 توريد سريع»).
                زر <strong>«🧾 إنشاء فاتورة»</strong> يفتح فاتورة شراء جاهزة ببيانات التوريد —
                اعتمادها <strong>لا يضيف المخزون مرة أخرى</strong>، بل يوثّقها ويحدّث حساب المورد فقط.
            </div>

            <form method="GET" class="mb-1">
                <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 15px;">
                    <input type="date" name="date_from" class="form-control" style="flex: 1; min-width: 130px;" value="<?php echo htmlspecialchars($dateFrom); ?>" onchange="this.form.submit()">
                    <input type="date" name="date_to" class="form-control" style="flex: 1; min-width: 130px;" value="<?php echo htmlspecialchars($dateTo); ?>" onchange="this.form.submit()">
                    <select name="supplier_id" class="form-control" style="flex: 1; min-width: 140px;" onchange="this.form.submit()">
                        <option value="0">كل الموردين</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo $filterSupplier == $s['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="linked" class="form-control" style="flex: 1; min-width: 140px;" onchange="this.form.submit()">
                        <option value="">الكل (مفوتر + غير مفوتر)</option>
                        <option value="no" <?php echo $filterLinked === 'no' ? 'selected' : ''; ?>>بدون فاتورة</option>
                        <option value="yes" <?php echo $filterLinked === 'yes' ? 'selected' : ''; ?>>صدرت له فاتورة</option>
                    </select>
                    <a href="supplies.php" class="btn btn-secondary">↺</a>
                </div>
            </form>

            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>الصنف والكمية</th>
                            <th>المخزن</th>
                            <th>المورد</th>
                            <th>القيمة التقديرية</th>
                            <th>المستخدم</th>
                            <th>الفاتورة</th>
                            <th>إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                        <tr><td colspan="8" class="text-center">لا توجد توريدات مسجلة</td></tr>
                        <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                        <tr>
                            <td style="white-space: nowrap;"><?php echo date('Y/m/d H:i', strtotime($r['created_at'])); ?></td>
                            <td style="text-align: right;">
                                <strong><?php echo htmlspecialchars($r['product_name'] ?? ('#' . $r['product_id'])); ?></strong>
                                <br><small style="color: #059669; font-weight: 700;">+<?php echo (int)$r['qty_in']; ?> <?php echo htmlspecialchars($r['unit'] ?? ''); ?></small>
                                <br><small style="color: #6b7280;"><?php echo htmlspecialchars($r['reason'] ?? ''); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($r['warehouse_name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($r['supplier_name']); ?></td>
                            <td><?php echo formatCurrency((int)$r['qty_in'] * (float)($r['unit_cost'] ?: 0)); ?></td>
                            <td><?php echo htmlspecialchars($r['user_name'] ?? ''); ?></td>
                            <td>
                                <?php if (!empty($r['linked_inv_id'])): ?>
                                    <span class="badge badge-success">✅ <?php echo htmlspecialchars($r['linked_inv_number'] ?? ('#' . $r['linked_inv_id'])); ?></span>
                                <?php else: ?>
                                    <span class="badge badge-warning">بدون فاتورة</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (empty($r['linked_inv_id']) && $canInvoice): ?>
                                <a href="../invoices/purchase.php?from_supply=<?php echo (int)$r['id']; ?>" class="btn btn-success btn-sm">🧾 إنشاء فاتورة</a>
                                <?php elseif (!empty($r['linked_inv_id'])): ?>
                                <a href="#" onclick="openReceiptModal('../invoices/print_purchase.php?id=<?php echo (int)$r['linked_inv_id']; ?>'); return false;" class="btn btn-info btn-sm">🖨️ الفاتورة</a>
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
                'date_from' => $dateFrom, 'date_to' => $dateTo,
                'supplier_id' => $filterSupplier, 'linked' => $filterLinked,
            ]);
            ?>
            <div class="d-flex justify-between align-center mb-1" style="margin-top: 12px;">
                <span class="badge badge-primary">إجمالي التوريدات: <?php echo $totalItems; ?></span>
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
