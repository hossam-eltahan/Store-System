<?php
/**
 * Invoice List with AJAX Live Search
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requireAnyPermission(['invoices.sale.view', 'invoices.purchase.view', 'pos.view', 'returns.view']);

$pageTitle = 'الفواتير';
$settings = getAllSettings();

$canViewSales = hasPermission('invoices.sale.view') || hasPermission('pos.view');
$canViewPurchases = hasPermission('invoices.purchase.view');
$canViewReturns = hasPermission('returns.view');

// Filters from GET
$search = trim($_GET['search'] ?? '');
$filterType = $_GET['type'] ?? '';
$filterPayment = $_GET['payment'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterWarehouse = (int)($_GET['warehouse_id'] ?? 0);
$filterSupplier = (int)($_GET['supplier_id'] ?? 0);
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Enforce type restriction based on permissions
if (!$canViewSales && !$canViewPurchases && $canViewReturns) {
    $filterType = 'returns';
} elseif ($canViewSales && !$canViewPurchases && !$canViewReturns) {
    $filterType = 'sale';
} elseif ($canViewPurchases && !$canViewSales && !$canViewReturns) {
    $filterType = 'purchase';
}

$warehouses = getAllWarehouses(false);

// Unified ledger: purchase invoices + legacy sale invoices + POS orders.
// Each part exposes the same columns: id, number, kind, date, total, paid,
// remaining, pay_status, status, name, warehouse_name, created.
$parts = [];
$params = [];
$like = $search !== '' ? "%$search%" : null;

if ($canViewPurchases && $filterType !== 'sale') {
    $q = "SELECT i.id AS id, i.invoice_number AS number, 'purchase' AS kind, i.date AS date,
                 i.total_amount AS total, i.paid_amount AS paid, i.remaining_amount AS remaining,
                 i.payment_status AS pay_status, i.status AS status,
                 COALESCE(s.name, i.customer_name, '-') AS name,
                 w.name AS warehouse_name, i.created_at AS created
          FROM invoices i
          LEFT JOIN suppliers s ON s.id = i.supplier_id
          LEFT JOIN warehouses w ON w.id = i.warehouse_id
          WHERE i.type = 'purchase'";
    if ($filterType === 'purchase') {
        // no extra condition
    }
    if ($filterPayment !== '') { $q .= " AND i.payment_status = ?"; $params[] = $filterPayment; }
    if ($filterStatus !== '') { $q .= " AND i.status = ?"; $params[] = $filterStatus; }
    if ($filterSupplier > 0) { $q .= " AND i.supplier_id = ?"; $params[] = $filterSupplier; }
    if ($search !== '') {
        $q .= " AND (i.invoice_number LIKE ? OR i.customer_name LIKE ? OR i.customer_phone LIKE ? OR s.name LIKE ? OR s.phone LIKE ?)";
        array_push($params, $like, $like, $like, $like, $like);
    }
    if ($filterWarehouse > 0) { $q .= " AND i.warehouse_id = ?"; $params[] = $filterWarehouse; }
    if ($dateFrom !== '') { $q .= " AND i.date >= ?"; $params[] = $dateFrom; }
    if ($dateTo !== '') { $q .= " AND i.date <= ?"; $params[] = $dateTo; }
    $parts[] = $q;
}

if ($canViewSales && $filterType !== 'purchase' && $filterSupplier <= 0) {
    // Legacy manual sale invoices (if any exist)
    $q = "SELECT i.id AS id, i.invoice_number AS number, 'sale' AS kind, i.date AS date,
                 i.total_amount AS total, i.paid_amount AS paid, i.remaining_amount AS remaining,
                 i.payment_status AS pay_status, i.status AS status,
                 COALESCE(c.name, i.customer_name, '-') AS name,
                 w.name AS warehouse_name, i.created_at AS created
          FROM invoices i
          LEFT JOIN customers c ON c.id = i.customer_id
          LEFT JOIN warehouses w ON w.id = i.warehouse_id
          WHERE i.type = 'sale'";
    if ($filterPayment !== '') { $q .= " AND i.payment_status = ?"; $params[] = $filterPayment; }
    if ($search !== '') {
        $q .= " AND (i.invoice_number LIKE ? OR i.customer_name LIKE ? OR i.customer_phone LIKE ? OR c.name LIKE ?)";
        array_push($params, $like, $like, $like, $like);
    }
    if ($filterWarehouse > 0) { $q .= " AND i.warehouse_id = ?"; $params[] = $filterWarehouse; }
    if ($dateFrom !== '') { $q .= " AND i.date >= ?"; $params[] = $dateFrom; }
    if ($dateTo !== '') { $q .= " AND i.date <= ?"; $params[] = $dateTo; }
    $parts[] = $q;

    // POS orders (the actual café sales). No warehouse split — excluded when
    // a warehouse filter is active to keep the filter truthful.
    if ($filterWarehouse <= 0) {
        $q = "SELECT o.id AS id, o.order_number AS number, 'pos' AS kind, DATE(o.created_at) AS date,
                     o.total_amount AS total, o.paid_amount AS paid, (o.total_amount - o.paid_amount) AS remaining,
                     o.payment_status AS pay_status, o.status AS status,
                     COALESCE(NULLIF(o.customer_name, ''), '-') AS name,
                     NULL AS warehouse_name, o.created_at AS created
              FROM orders o WHERE 1=1";
        if ($filterPayment !== '') { $q .= " AND o.payment_status = ?"; $params[] = $filterPayment; }
        if ($search !== '') {
            $q .= " AND (o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_phone LIKE ?)";
            array_push($params, $like, $like, $like);
        }
        if ($dateFrom !== '') { $q .= " AND DATE(o.created_at) >= ?"; $params[] = $dateFrom; }
        if ($dateTo !== '') { $q .= " AND DATE(o.created_at) <= ?"; $params[] = $dateTo; }
        $parts[] = $q;
    }
}

// Returns ledger (all return receipts in the same list). Excluded when a
// supplier/warehouse filter is active since returns carry neither.
if ($canViewReturns && in_array($filterType, ['', 'returns'], true) && $filterSupplier <= 0 && $filterWarehouse <= 0) {
    $q = "SELECT r.id AS id, r.return_number AS number, 'return' AS kind, r.return_date AS date,
                 r.total_amount AS total, r.total_amount AS paid, 0 AS remaining,
                 'paid' AS pay_status, 'approved' AS status,
                 COALESCE(i.invoice_number, o.order_number, '-') AS name,
                 NULL AS warehouse_name, r.created_at AS created
          FROM invoice_returns r
          LEFT JOIN invoices i ON r.invoice_kind IN ('purchase', 'sale') AND i.id = r.invoice_id
          LEFT JOIN orders o ON r.invoice_kind = 'pos' AND o.id = r.invoice_id
          WHERE 1=1";
    if ($search !== '') {
        $q .= " AND (r.return_number LIKE ? OR i.invoice_number LIKE ? OR o.order_number LIKE ?)";
        array_push($params, $like, $like, $like);
    }
    if ($dateFrom !== '') { $q .= " AND r.return_date >= ?"; $params[] = $dateFrom; }
    if ($dateTo !== '') { $q .= " AND r.return_date <= ?"; $params[] = $dateTo; }
    $parts[] = $q;
}

// Pagination
$perPage = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// Get total count
if (empty($parts)) {
    $totalItems = 0;
    $invoices = [];
} else {
    $union = implode(' UNION ALL ', $parts);
    $totalItems = getRow("SELECT COUNT(*) as count FROM ($union) t", $params)['count'] ?? 0;
    // Get paginated results
    $invoices = getRows("$union ORDER BY created DESC, id DESC LIMIT $perPage OFFSET $offset", $params);
}
$totalPages = ceil($totalItems / $perPage);

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<style>
.live-search-input {
    font-size: 1.1em;
    padding: 12px 15px;
    border: 2px solid #3b82f6;
    border-radius: 10px;
    transition: all 0.3s;
}
.live-search-input:focus {
    border-color: #1d4ed8;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
    outline: none;
}
.filter-row {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}
.filter-group {
    flex: 1;
    min-width: 150px;
}
.filter-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
    color: #374151;
}
.filter-group select, .filter-group input {
    width: 100%;
    padding: 10px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
}
.search-results-count {
    background: #eff6ff;
    padding: 10px 15px;
    border-radius: 8px;
    margin-bottom: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.loading-indicator {
    display: none;
    color: #3b82f6;
    font-weight: bold;
}
.loading-indicator.active {
    display: inline;
}
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
.loading-indicator.active {
    animation: pulse 1s infinite;
}
</style>

<div class="container">
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>🧾 الفواتير (مشتريات ومبيعات)</span>
            <div>
                <?php if (hasPermission('invoices.purchase.create')): ?>
                <a href="purchase.php" class="btn btn-success">+ فاتورة شراء</a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card-body">
            <!-- Search Form -->
            <form method="GET" id="searchForm" class="mb-1">
                <div style="margin-bottom: 15px;">
                    <input type="text" name="search" id="liveSearch" class="form-control live-search-input" 
                           placeholder="🔍 بحث سريع... (رقم الفاتورة، اسم العميل، المورد)"
                           value="<?php echo htmlspecialchars($search); ?>"
                           autocomplete="off">
                </div>
                
                <!-- Filters -->
                <div class="filter-row">
                    <div class="filter-group">
                        <label>نوع الفاتورة</label>
                        <select name="type" id="filterType" class="form-control">
                            <?php $multiKind = ($canViewSales && $canViewPurchases); ?>
                            <?php if ($multiKind): ?>
                            <option value="">الكل</option>
                            <?php endif; ?>
                            <?php if ($canViewSales): ?>
                            <option value="sale" <?php echo $filterType === 'sale' ? 'selected' : ''; ?>>مبيعات (كاشير + يدوي)</option>
                            <?php endif; ?>
                            <?php if ($canViewPurchases): ?>
                            <option value="purchase" <?php echo $filterType === 'purchase' ? 'selected' : ''; ?>>مشتريات</option>
                            <?php endif; ?>
                            <?php if ($canViewReturns): ?>
                            <option value="returns" <?php echo $filterType === 'returns' ? 'selected' : ''; ?>>↩️ المرتجعات</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>من تاريخ</label>
                        <input type="date" name="date_from" id="filterDateFrom" class="form-control" 
                               value="<?php echo htmlspecialchars($dateFrom); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label>إلى تاريخ</label>
                        <input type="date" name="date_to" id="filterDateTo" class="form-control"
                               value="<?php echo htmlspecialchars($dateTo); ?>">
                    </div>

                    <div class="filter-group">
                        <label>المخزن</label>
                        <select name="warehouse_id" id="filterWarehouse" class="form-control">
                            <option value="">كل المخازن</option>
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="<?php echo $wh['id']; ?>" <?php echo $filterWarehouse == $wh['id'] ? 'selected' : ''; ?>>
                                    🏢 <?php echo htmlspecialchars($wh['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>حالة الدفع</label>
                        <select name="payment" id="filterPayment" class="form-control">
                            <option value="">الكل</option>
                            <option value="paid" <?php echo $filterPayment === 'paid' ? 'selected' : ''; ?>>مدفوع</option>
                            <option value="partial" <?php echo $filterPayment === 'partial' ? 'selected' : ''; ?>>مدفوع جزئياً</option>
                            <option value="unpaid" <?php echo $filterPayment === 'unpaid' ? 'selected' : ''; ?>>غير مدفوع</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>حالة الفاتورة</label>
                        <select name="status" id="filterStatus" class="form-control">
                            <option value="">الكل</option>
                            <option value="draft" <?php echo $filterStatus === 'draft' ? 'selected' : ''; ?>>📝 مسودة</option>
                            <option value="approved" <?php echo $filterStatus === 'approved' ? 'selected' : ''; ?>>✅ معتمدة</option>
                            <option value="cancelled" <?php echo $filterStatus === 'cancelled' ? 'selected' : ''; ?>>❌ ملغاة</option>
                        </select>
                    </div>
                    
                    <div class="filter-group" style="display: flex; align-items: flex-end;">
                        <a href="list.php" class="btn btn-secondary" style="width: 100%;">↺ إعادة تعيين</a>
                    </div>
                </div>
            </form>
            
            <!-- Pagination Info & Quick Navigation -->
            <div class="d-flex justify-between align-center mb-1">
                <div>
                    <span class="badge badge-primary">إجمالي الفواتير: <?php echo $totalItems; ?></span>
                </div>
                <div>
                    <?php 
                    $queryParams = http_build_query([
                        'search' => $search,
                        'type' => $filterType,
                        'payment' => $filterPayment,
                        'status' => $filterStatus,
                        'warehouse_id' => $filterWarehouse,
                        'supplier_id' => $filterSupplier,
                        'date_from' => $dateFrom,
                        'date_to' => $dateTo
                    ]);
                    ?>
                    <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&<?php echo $queryParams; ?>" class="btn btn-secondary btn-sm">← السابق</a>
                    <?php endif; ?>
                    <span class="badge badge-info">صفحة <?php echo $page; ?> من <?php echo max(1, $totalPages); ?></span>
                    <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&<?php echo $queryParams; ?>" class="btn btn-secondary btn-sm">التالي →</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Table -->
            <div class="table-container">
                <table class="table" id="invoicesTable">
                    <thead>
                        <tr>
                            <th>رقم الفاتورة</th>
                            <th>النوع</th>
                            <th>المخزن</th>
                            <th>العميل/المورد</th>
                            <th>التاريخ</th>
                            <th>الإجمالي</th>
                            <th>المدفوع</th>
                            <th>الباقي</th>
                            <th>حالة الدفع</th>
                            <th>الحالة</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="invoicesBody">
                        <?php if (empty($invoices)): ?>
                        <tr>
                            <td colspan="11" class="text-center">لا توجد فواتير</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($invoices as $invoice): ?>
                        <?php $kind = $invoice['kind'] ?? 'purchase'; ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($invoice['number']); ?></strong></td>
                            <td>
                                <?php if ($kind === 'purchase'): ?>
                                    <span class="badge badge-success">مشتريات</span>
                                <?php elseif ($kind === 'pos'): ?>
                                    <span class="badge badge-info">مبيعات كاشير</span>
                                <?php elseif ($kind === 'return'): ?>
                                    <span class="badge badge-warning">↩️ مرتجع</span>
                                <?php else: ?>
                                    <span class="badge badge-info">مبيعات</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($invoice['warehouse_name'])): ?>
                                <span class="badge" style="background: #e0f2fe; color: #0369a1; padding: 3px 8px; border-radius: 10px; font-weight: 600; font-size: 0.85em;">
                                    🏢 <?php echo htmlspecialchars($invoice['warehouse_name']); ?>
                                </span>
                                <?php else: ?>
                                <span style="color: #94a3b8;">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $kind === 'return' ? ('مرتجع على ' . htmlspecialchars($invoice['name'] ?? '')) : htmlspecialchars($invoice['name'] ?? '-'); ?></td>
                            <td><?php echo date('Y/m/d', strtotime($invoice['date'])); ?></td>
                            <td><?php echo formatCurrency($invoice['total']); ?></td>
                            <td><?php echo formatCurrency($invoice['paid']); ?></td>
                            <td><?php echo formatCurrency($invoice['remaining']); ?></td>
                            <td>
                                <?php if ($kind === 'return'): ?>
                                <span class="badge badge-warning">مسترد</span>
                                <?php else: ?>
                                <?php
                                $statusClass = [
                                    'paid' => 'badge-success',
                                    'partial' => 'badge-warning',
                                    'unpaid' => 'badge-danger'
                                ];
                                $statusText = [
                                    'paid' => 'مدفوع',
                                    'partial' => 'جزئي',
                                    'unpaid' => 'غير مدفوع'
                                ];
                                $paySt = $invoice['pay_status'] ?? 'unpaid';
                                ?>
                                <span class="badge <?php echo $statusClass[$paySt] ?? 'badge-secondary'; ?>">
                                    <?php echo $statusText[$paySt] ?? $paySt; ?>
                                </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($kind === 'purchase'): ?>
                                <?php
                                $invStatus = $invoice['status'] ?? 'approved';
                                $invStatusClass = ['draft' => 'badge-warning', 'approved' => 'badge-success', 'cancelled' => 'badge-danger'];
                                $invStatusText = ['draft' => '📝 مسودة', 'approved' => '✅ معتمدة', 'cancelled' => '❌ ملغاة'];
                                ?>
                                <span class="badge <?php echo $invStatusClass[$invStatus] ?? 'badge-success'; ?>">
                                    <?php echo $invStatusText[$invStatus] ?? $invStatus; ?>
                                </span>
                                <?php elseif ($kind === 'pos'): ?>
                                <?php
                                $oSt = $invoice['status'] ?? 'completed';
                                $oMap = ['completed' => ['مكتمل', 'badge-success'], 'cancelled' => ['ملغي', 'badge-danger']];
                                $oLbl = $oMap[$oSt][0] ?? 'معلق';
                                $oCls = $oMap[$oSt][1] ?? 'badge-warning';
                                ?>
                                <span class="badge <?php echo $oCls; ?>"><?php echo $oLbl; ?></span>
                                <?php elseif ($kind === 'return'): ?>
                                <span class="badge badge-secondary">↩️ مرتجع موثق</span>
                                <?php else: ?>
                                <span class="badge badge-success">✅ معتمدة</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($kind === 'purchase'): ?>
                                <?php
                                $canPrintThis = hasPermission('invoices.purchase.view');
                                $canEditThis = hasPermission('invoices.purchase.edit') && (($settings['allow_edit_invoices'] ?? '0') === '1');
                                $canDeleteThis = hasPermission('invoices.purchase.delete') && (($settings['allow_delete_invoices'] ?? '0') === '1');
                                ?>
                                <a href="#" onclick="openReceiptModal('print_purchase.php?id=<?php echo $invoice['id']; ?>'); return false;" class="btn btn-primary" title="طباعة">🖨️</a>
                                <?php if (($invoice['status'] ?? 'approved') === 'draft' && hasAnyPermission(['invoices.purchase.create', 'invoices.purchase.edit'])): ?>
                                <form method="POST" action="approve_purchase.php" style="display: inline;" onsubmit="return confirm('اعتماد هذه الفاتورة؟ سيتم إضافة الأصناف للمخزون وتحديث حساب المورد.');">
                                    <input type="hidden" name="id" value="<?php echo $invoice['id']; ?>">
                                    <button type="submit" class="btn btn-success" title="اعتماد الفاتورة">✅</button>
                                </form>
                                <?php endif; ?>
                                <?php if ($canEditThis): ?>
                                <a href="edit_purchase.php?id=<?php echo $invoice['id']; ?>" class="btn btn-info" title="تعديل">✏️</a>
                                <?php elseif (($invoice['status'] ?? 'approved') === 'draft' && hasPermission('invoices.purchase.edit')): ?>
                                <a href="edit_purchase.php?id=<?php echo $invoice['id']; ?>" class="btn btn-info" title="تعديل المسودة">✏️</a>
                                <?php endif; ?>
                                <?php if (hasPermission('returns.create')): ?>
                                <a href="returns.php?kind=purchase&id=<?php echo $invoice['id']; ?>" class="btn btn-warning" title="مرتجع">↩️</a>
                                <?php endif; ?>
                                <?php if ($canDeleteThis): ?>
                                <a href="delete.php?id=<?php echo $invoice['id']; ?>" class="btn btn-danger" title="مسح" onclick="return confirm('هل أنت متأكد من مسح هذه الفاتورة تماماً واسترجاع الأرصدة؟ لا يمكن التراجع عن هذه الخطوة!');">🗑️</a>
                                <?php endif; ?>
                                <?php elseif ($kind === 'pos'): ?>
                                <a href="#" onclick="openReceiptModal('../pos/receipt.php?id=<?php echo $invoice['id']; ?>&type=customer'); return false;" class="btn btn-primary" title="طباعة البون">🖨️</a>
                                <?php if (($invoice['status'] ?? '') !== 'cancelled' && hasPermission('pos.create_order')): ?>
                                <a href="../pos/index.php?edit_order=<?php echo $invoice['id']; ?>" class="btn btn-info" title="تعديل في الكاشير">✏️</a>
                                <?php endif; ?>
                                <?php if (($invoice['status'] ?? '') !== 'cancelled' && hasPermission('returns.create')): ?>
                                <a href="returns.php?kind=pos&id=<?php echo $invoice['id']; ?>" class="btn btn-warning" title="مرتجع بيع">↩️</a>
                                <?php endif; ?>
                                <?php else: ?>
                                <?php
                                $canPrintThis = hasPermission('invoices.sale.view');
                                $canEditThis = hasPermission('invoices.sale.edit') && (($settings['allow_edit_invoices'] ?? '0') === '1');
                                $canDeleteThis = hasPermission('invoices.sale.delete') && (($settings['allow_delete_invoices'] ?? '0') === '1');
                                ?>
                                <?php if ($canPrintThis): ?>
                                <a href="#" onclick="openReceiptModal('print.php?id=<?php echo $invoice['id']; ?>'); return false;" class="btn btn-primary" title="طباعة">🖨️</a>
                                <?php endif; ?>
                                <?php if ($canEditThis): ?>
                                <a href="edit_sale.php?id=<?php echo $invoice['id']; ?>" class="btn btn-info" title="تعديل">✏️</a>
                                <?php endif; ?>
                                <?php if ($canDeleteThis): ?>
                                <a href="delete.php?id=<?php echo $invoice['id']; ?>" class="btn btn-danger" title="مسح" onclick="return confirm('هل أنت متأكد من مسح هذه الفاتورة تماماً واسترجاع الأرصدة؟ لا يمكن التراجع عن هذه الخطوة!');">🗑️</a>
                                <?php endif; ?>
                                <?php if (hasPermission('returns.create')): ?>
                                <a href="returns.php?kind=sale&id=<?php echo $invoice['id']; ?>" class="btn btn-warning" title="مرتجع">↩️</a>
                                <?php endif; ?>
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
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var searchForm = document.getElementById('searchForm');
    var liveSearch = document.getElementById('liveSearch');
    var filterType = document.getElementById('filterType');
    var filterDateFrom = document.getElementById('filterDateFrom');
    var filterDateTo = document.getElementById('filterDateTo');
    var filterPayment = document.getElementById('filterPayment');
    var filterStatus = document.getElementById('filterStatus');
    var filterWarehouse = document.getElementById('filterWarehouse');
    
    var timeout = null;
    
    // Auto-submit on text search
    if (liveSearch && searchForm) {
        liveSearch.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                searchForm.submit();
            }, 500);
        });
    }
    
    // Instant submit on filter change
    if (filterType) filterType.addEventListener('change', function() { searchForm.submit(); });
    if (filterPayment) filterPayment.addEventListener('change', function() { searchForm.submit(); });
    if (filterStatus) filterStatus.addEventListener('change', function() { searchForm.submit(); });
    if (filterWarehouse) filterWarehouse.addEventListener('change', function() { searchForm.submit(); });
    if (filterDateFrom) filterDateFrom.addEventListener('change', function() { searchForm.submit(); });
    if (filterDateTo) filterDateTo.addEventListener('change', function() { searchForm.submit(); });
});
</script>

<?php include '../../includes/footer.php'; ?>

<?php if (!empty($_GET['printed']) && (int)$_GET['printed'] > 0): ?>
<script>
// Open the just-saved/approved purchase receipt in the standard modal
document.addEventListener('DOMContentLoaded', function() {
    if (typeof openReceiptModal === 'function') {
        openReceiptModal('print_purchase.php?id=<?php echo (int)$_GET['printed']; ?>');
    }
});
</script>
<?php endif; ?>
