<?php
/**
 * Invoice List with AJAX Live Search
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requireAnyPermission(['invoices.sale.view', 'invoices.purchase.view']);

$pageTitle = 'قائمة الفواتير';
$settings = getAllSettings();

$canViewSales = hasPermission('invoices.sale.view');
$canViewPurchases = hasPermission('invoices.purchase.view');

// Filters from GET
$search = trim($_GET['search'] ?? '');
$filterType = $_GET['type'] ?? '';
$filterPayment = $_GET['payment'] ?? '';
$filterWarehouse = (int)($_GET['warehouse_id'] ?? 0);
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Enforce type restriction based on permissions
if ($canViewSales && !$canViewPurchases) {
    $filterType = 'sale';
} elseif ($canViewPurchases && !$canViewSales) {
    $filterType = 'purchase';
}

$warehouses = getAllWarehouses(false);

// Build query
$sql = "SELECT i.*, 
        w.name as warehouse_name,
        COALESCE(c.name, i.customer_name) as display_name,
        s.name as supplier_name_db
 FROM invoices i
 LEFT JOIN customers c ON i.customer_id = c.id
 LEFT JOIN suppliers s ON i.supplier_id = s.id
 LEFT JOIN warehouses w ON i.warehouse_id = w.id
 WHERE 1=1";
$params = [];

// Apply type permissions to query
if ($canViewSales && !$canViewPurchases) {
    $sql .= " AND i.type = 'sale'";
} elseif ($canViewPurchases && !$canViewSales) {
    $sql .= " AND i.type = 'purchase'";
} elseif ($filterType !== '') {
    $sql .= " AND i.type = ?";
    $params[] = $filterType;
}

if ($filterPayment !== '') {
    $sql .= " AND i.payment_status = ?";
    $params[] = $filterPayment;
}

if ($filterWarehouse > 0) {
    $sql .= " AND i.warehouse_id = ?";
    $params[] = $filterWarehouse;
}

if ($dateFrom !== '') {
    $sql .= " AND i.date >= ?";
    $params[] = $dateFrom;
}

if ($dateTo !== '') {
    $sql .= " AND i.date <= ?";
    $params[] = $dateTo;
}

// Pagination
$perPage = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// Get total count
$countSql = preg_replace('/SELECT i\.\*.*?FROM invoices i/s', 'SELECT COUNT(*) as count FROM invoices i', $sql);
$totalItems = getRow($countSql, $params)['count'] ?? 0;
$totalPages = ceil($totalItems / $perPage);

// Get paginated results
$sql .= " ORDER BY i.created_at DESC LIMIT $perPage OFFSET $offset";
$invoices = getRows($sql, $params);

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
            <span>📋 قائمة الفواتير</span>
            <div>
                <?php if (hasPermission('invoices.sale.create')): ?>
                <a href="sale.php" class="btn btn-primary">+ فاتورة بيع</a>
                <?php endif; ?>
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
                            <?php if ($canViewSales && $canViewPurchases): ?>
                            <option value="">الكل</option>
                            <option value="sale" <?php echo $filterType === 'sale' ? 'selected' : ''; ?>>مبيعات</option>
                            <option value="purchase" <?php echo $filterType === 'purchase' ? 'selected' : ''; ?>>مشتريات</option>
                            <?php elseif ($canViewSales): ?>
                            <option value="sale" selected>مبيعات</option>
                            <?php elseif ($canViewPurchases): ?>
                            <option value="purchase" selected>مشتريات</option>
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
                        'warehouse_id' => $filterWarehouse,
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
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="invoicesBody">
                        <?php if (empty($invoices)): ?>
                        <tr>
                            <td colspan="10" class="text-center">لا توجد فواتير</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($invoices as $invoice): ?>
                        <tr>
                            <td><strong><?php echo $invoice['invoice_number']; ?></strong></td>
                            <td>
                                <span class="badge <?php echo $invoice['type'] === 'sale' ? 'badge-info' : 'badge-success'; ?>">
                                    <?php echo $invoice['type'] === 'sale' ? 'مبيعات' : 'مشتريات'; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge" style="background: #e0f2fe; color: #0369a1; padding: 3px 8px; border-radius: 10px; font-weight: 600; font-size: 0.85em;">
                                    🏢 <?php echo htmlspecialchars($invoice['warehouse_name'] ?? 'المخزن الرئيسي'); ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                if ($invoice['type'] === 'sale') {
                                    echo $invoice['display_name'];
                                } else {
                                    echo $invoice['supplier_name_db'] ?: $invoice['customer_name'];
                                }
                                ?>
                            </td>
                            <td><?php echo date('Y/m/d', strtotime($invoice['date'])); ?></td>
                            <td><?php echo formatCurrency($invoice['total_amount']); ?></td>
                            <td><?php echo formatCurrency($invoice['paid_amount']); ?></td>
                            <td><?php echo formatCurrency($invoice['remaining_amount']); ?></td>
                            <td>
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
                                ?>
                                <span class="badge <?php echo $statusClass[$invoice['payment_status']]; ?>">
                                    <?php echo $statusText[$invoice['payment_status']]; ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $printPage = $invoice['type'] === 'purchase' ? 'print_purchase.php' : 'print.php';
                                $returnPage = $invoice['type'] === 'purchase' ? '../returns/create_supplier.php' : '../returns/create.php';
                                $editPage = $invoice['type'] === 'purchase' ? 'edit_purchase.php' : 'edit_sale.php';

                                $canPrintThis = $invoice['type'] === 'purchase' ? hasPermission('invoices.purchase.view') : hasPermission('invoices.sale.view');
                                $canReturnThis = $invoice['type'] === 'purchase' ? hasPermission('returns.create_supplier') : hasPermission('returns.create_customer');
                                $canEditThis = ($invoice['type'] === 'purchase' ? hasPermission('invoices.purchase.edit') : hasPermission('invoices.sale.edit')) && (($settings['allow_edit_invoices'] ?? '0') === '1');
                                $canDeleteThis = ($invoice['type'] === 'purchase' ? hasPermission('invoices.purchase.delete') : hasPermission('invoices.sale.delete')) && (($settings['allow_delete_invoices'] ?? '0') === '1');
                                ?>
                                <?php if ($canPrintThis): ?>
                                <a href="<?php echo $printPage; ?>?id=<?php echo $invoice['id']; ?>" class="btn btn-primary" title="طباعة">🖨️</a>
                                <?php endif; ?>
                                <?php if ($canReturnThis): ?>
                                <a href="<?php echo $returnPage; ?>?invoice_id=<?php echo $invoice['id']; ?>" class="btn btn-warning" title="مرتجع">↩️</a>
                                <?php endif; ?>
                                <?php if ($canEditThis): ?>
                                <a href="<?php echo $editPage; ?>?id=<?php echo $invoice['id']; ?>" class="btn btn-info" title="تعديل">✏️</a>
                                <?php endif; ?>
                                <?php if ($canDeleteThis): ?>
                                <a href="delete.php?id=<?php echo $invoice['id']; ?>" class="btn btn-danger" title="مسح" onclick="return confirm('هل أنت متأكد من مسح هذه الفاتورة تماماً واسترجاع الأرصدة؟ لا يمكن التراجع عن هذه الخطوة!');">🗑️</a>
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
    if (filterWarehouse) filterWarehouse.addEventListener('change', function() { searchForm.submit(); });
    if (filterDateFrom) filterDateFrom.addEventListener('change', function() { searchForm.submit(); });
    if (filterDateTo) filterDateTo.addEventListener('change', function() { searchForm.submit(); });
});
</script>

<?php include '../../includes/footer.php'; ?>
