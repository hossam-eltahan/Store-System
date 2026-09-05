<?php
/**
 * Invoice List with AJAX Live Search
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';

$pageTitle = 'قائمة الفواتير';
$settings = getAllSettings();

// Filters from GET
$search = trim($_GET['search'] ?? '');
$filterType = $_GET['type'] ?? '';
$filterPayment = $_GET['payment'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Build query
$sql = "SELECT i.*, 
        COALESCE(c.name, i.customer_name) as display_name,
        s.name as supplier_name_db
 FROM invoices i
 LEFT JOIN customers c ON i.customer_id = c.id
 LEFT JOIN suppliers s ON i.supplier_id = s.id
 WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (i.invoice_number LIKE ? OR c.name LIKE ? OR s.name LIKE ? OR i.customer_name LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if ($filterType !== '') {
    $sql .= " AND i.type = ?";
    $params[] = $filterType;
}

if ($filterPayment !== '') {
    $sql .= " AND i.payment_status = ?";
    $params[] = $filterPayment;
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
                <a href="sale.php" class="btn btn-primary">+ فاتورة بيع</a>
                <a href="purchase.php" class="btn btn-success">+ فاتورة شراء</a>
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
                            <option value="">الكل</option>
                            <option value="sale" <?php echo $filterType === 'sale' ? 'selected' : ''; ?>>مبيعات</option>
                            <option value="purchase" <?php echo $filterType === 'purchase' ? 'selected' : ''; ?>>مشتريات</option>
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
                        <label>حالة الدفع</label>
                        <select name="payment" id="filterPaymentStatus" class="form-control">
                            <option value="">الكل</option>
                            <option value="paid" <?php echo $filterPayment === 'paid' ? 'selected' : ''; ?>>مدفوع</option>
                            <option value="partial" <?php echo $filterPayment === 'partial' ? 'selected' : ''; ?>>دفع جزئي</option>
                            <option value="unpaid" <?php echo $filterPayment === 'unpaid' ? 'selected' : ''; ?>>غير مدفوع</option>
                        </select>
                    </div>
                    
                    <div class="filter-group" style="display: flex; align-items: flex-end;">
                        <a href="list.php" class="btn btn-secondary" style="width: 100%;">↺ إعادة تعيين</a>
                    </div>
                </div>
            </form>
            
            <!-- Results Count with Pagination -->
            <div class="search-results-count">
                <span>📊 عدد النتائج: <strong><?php echo $totalItems; ?></strong> فاتورة</span>
                <div class="pagination" style="display: flex; align-items: center; gap: 8px;">
                    <?php 
                    $queryParams = http_build_query([
                        'search' => $search,
                        'type' => $filterType,
                        'payment' => $filterPayment,
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
                            <td colspan="9" class="text-center">لا توجد فواتير</td>
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
                                ?>
                                <a href="<?php echo $printPage; ?>?id=<?php echo $invoice['id']; ?>" class="btn btn-primary" title="طباعة">🖨️</a>
                                <a href="<?php echo $returnPage; ?>?invoice_id=<?php echo $invoice['id']; ?>" class="btn btn-warning" title="مرتجع">↩️</a>
                                <?php if (($settings['allow_edit_invoices'] ?? '0') === '1'): ?>
                                <a href="<?php echo $editPage; ?>?id=<?php echo $invoice['id']; ?>" class="btn btn-info" title="تعديل">✏️</a>
                                <?php endif; ?>
                                <?php if (($settings['allow_delete_invoices'] ?? '0') === '1'): ?>
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
    var filterPaymentStatus = document.getElementById('filterPaymentStatus');
    
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
    if (filterPaymentStatus) filterPaymentStatus.addEventListener('change', function() { searchForm.submit(); });
    if (filterDateFrom) filterDateFrom.addEventListener('change', function() { searchForm.submit(); });
    if (filterDateTo) filterDateTo.addEventListener('change', function() { searchForm.submit(); });
});
</script>

<?php include '../../includes/footer.php'; ?>
