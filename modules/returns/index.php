<?php
/**
 * Returns List - Customer and Supplier Returns
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';

$pageTitle = 'المرتجعات';

// Filter by type
$typeFilter = $_GET['type'] ?? '';
$search = trim($_GET['search'] ?? '');
$whereConditions = [];
$params = [];

if ($typeFilter === 'customer') {
    $whereConditions[] = "(r.type = 'customer' OR r.type IS NULL)";
} elseif ($typeFilter === 'supplier') {
    $whereConditions[] = "r.type = 'supplier'";
}

// Text search
if ($search !== '') {
    $whereConditions[] = "(r.return_number LIKE ? OR c.name LIKE ? OR s.name LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Pagination
$perPage = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// Get total count
$countSql = "SELECT COUNT(*) as count FROM returns r
             LEFT JOIN customers c ON r.customer_id = c.id
             LEFT JOIN suppliers s ON r.supplier_id = s.id
             $whereClause";
$totalItems = getRow($countSql, $params)['count'];
$totalPages = ceil($totalItems / $perPage);

// Get paginated returns
$returns = getRows(
    "SELECT r.*, i.invoice_number, 
            c.name as customer_name_db,
            s.name as supplier_name_db
     FROM returns r
     LEFT JOIN invoices i ON r.original_invoice_id = i.id
     LEFT JOIN customers c ON r.customer_id = c.id
     LEFT JOIN suppliers s ON r.supplier_id = s.id
     $whereClause
     ORDER BY r.created_at DESC
     LIMIT $perPage OFFSET $offset",
    $params
);

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>🔄 المرتجعات</span>
            <div style="display: flex; gap: 10px;">
                <a href="create.php" class="btn btn-primary">+ مرتجع عميل</a>
                <a href="create_supplier.php" class="btn btn-success">+ مرتجع للمورد</a>
            </div>
        </div>
        
        <div class="card-body">
            <!-- Filters -->
            <form method="GET" id="searchForm" style="display: flex; gap: 15px; margin-bottom: 15px; flex-wrap: wrap; align-items: center;">
                <input type="text" name="search" id="searchInput" class="form-control" 
                       placeholder="🔍 بحث..." style="flex: 1; min-width: 200px;"
                       value="<?php echo htmlspecialchars($search); ?>">
                <?php if ($typeFilter): ?>
                <input type="hidden" name="type" value="<?php echo $typeFilter; ?>">
                <?php endif; ?>
                
                <div style="display: flex; gap: 5px;">
                    <a href="index.php" class="btn <?php echo !$typeFilter ? 'btn-primary' : 'btn-secondary'; ?>">الكل</a>
                    <a href="index.php?type=customer" class="btn <?php echo $typeFilter === 'customer' ? 'btn-primary' : 'btn-secondary'; ?>">مرتجعات العملاء</a>
                    <a href="index.php?type=supplier" class="btn <?php echo $typeFilter === 'supplier' ? 'btn-success' : 'btn-secondary'; ?>">مرتجعات للموردين</a>
                </div>
            </form>
            
            <!-- Count with Pagination -->
            <div class="search-results-count">
                <span>📊 عدد المرتجعات: <strong><?php echo $totalItems; ?></strong></span>
                <div class="pagination" style="display: flex; align-items: center; gap: 8px;">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&type=<?php echo urlencode($typeFilter); ?>&search=<?php echo urlencode($search); ?>" class="btn btn-secondary btn-sm">← السابق</a>
                    <?php endif; ?>
                    <span class="badge badge-info">صفحة <?php echo $page; ?> من <?php echo max(1, $totalPages); ?></span>
                    <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&type=<?php echo urlencode($typeFilter); ?>&search=<?php echo urlencode($search); ?>" class="btn btn-secondary btn-sm">التالي →</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (empty($returns)): ?>
            <div class="alert alert-info">لا توجد مرتجعات مسجلة</div>
            <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>رقم المرتجع</th>
                            <th>النوع</th>
                            <th>رقم الفاتورة</th>
                            <th>العميل/المورد</th>
                            <th>التاريخ</th>
                            <th>المبلغ</th>
                            <th>طريقة الاسترداد</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php foreach ($returns as $return): 
                            $isSupplierReturn = ($return['type'] ?? 'customer') === 'supplier';
                            $displayName = $isSupplierReturn 
                                ? ($return['supplier_name_db'] ?? $return['supplier_name'] ?? '-')
                                : ($return['customer_name_db'] ?? $return['customer_name'] ?? '-');
                            $printPage = $isSupplierReturn ? 'print_supplier.php' : 'print.php';
                            $invoicePrintPage = $isSupplierReturn ? '../invoices/print_purchase.php' : '../invoices/print.php';
                        ?>
                        <tr>
                            <td><strong><?php echo $return['return_number']; ?></strong></td>
                            <td>
                                <?php if ($isSupplierReturn): ?>
                                    <span class="badge badge-success">للمورد</span>
                                <?php else: ?>
                                    <span class="badge badge-info">من عميل</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo $invoicePrintPage; ?>?id=<?php echo $return['original_invoice_id']; ?>" target="_blank">
                                    <?php echo $return['invoice_number']; ?>
                                </a>
                            </td>
                            <td><?php echo $displayName; ?></td>
                            <td><?php echo date('Y/m/d', strtotime($return['return_date'])); ?></td>
                            <td><strong><?php echo formatCurrency($return['total_amount']); ?></strong></td>
                            <td>
                                <?php if ($return['refund_method'] === 'deducted'): ?>
                                    <span class="badge badge-info">خصم من الحساب</span>
                                <?php elseif ($return['refund_method'] === 'mixed'): ?>
                                    <span class="badge badge-warning">خصم + كاش</span>
                                <?php else: ?>
                                    <span class="badge badge-success">كاش</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="view.php?id=<?php echo $return['id']; ?>" class="btn btn-info btn-sm" title="عرض">👁️</a>
                                <a href="<?php echo $printPage; ?>?id=<?php echo $return['id']; ?>" class="btn btn-primary btn-sm" target="_blank" title="طباعة">🖨️</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var searchInput = document.getElementById('searchInput');
    var searchForm = document.getElementById('searchForm');
    
    if (searchInput && searchForm) {
        var timeout = null;
        searchInput.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                searchForm.submit();
            }, 500);
        });
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
