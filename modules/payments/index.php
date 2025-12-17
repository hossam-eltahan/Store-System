<?php
/**
 * Payments List
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';

$pageTitle = 'سجل المدفوعات';

// Filter by type and search
$typeFilter = $_GET['type'] ?? '';
$search = trim($_GET['search'] ?? '');
$whereConditions = [];
$params = [];

if ($typeFilter === 'customer') {
    $whereConditions[] = "type = 'customer'";
} elseif ($typeFilter === 'supplier') {
    $whereConditions[] = "type = 'supplier'";
}

// Text search
if ($search !== '') {
    $whereConditions[] = "(payment_number LIKE ? OR entity_name LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Pagination
$perPage = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// Get total count
$countSql = "SELECT COUNT(*) as count FROM payments $whereClause";
$totalItems = getRow($countSql, $params)['count'];
$totalPages = ceil($totalItems / $perPage);

// Get paginated payments
$payments = getRows(
    "SELECT * FROM payments
     $whereClause
     ORDER BY id DESC
     LIMIT $perPage OFFSET $offset",
    $params
);

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>💵 سجل المدفوعات</span>
            <div style="display: flex; gap: 10px;">
                <a href="pay_customer.php" class="btn btn-warning">💵 تحصيل من عميل</a>
                <a href="pay_supplier.php" class="btn btn-success">💵 دفع لمورد</a>
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
                    <a href="index.php?type=customer" class="btn <?php echo $typeFilter === 'customer' ? 'btn-warning' : 'btn-secondary'; ?>">من العملاء</a>
                    <a href="index.php?type=supplier" class="btn <?php echo $typeFilter === 'supplier' ? 'btn-success' : 'btn-secondary'; ?>">للموردين</a>
                </div>
            </form>
            
            <!-- Count with Pagination -->
            <div class="search-results-count">
                <span>📊 عدد المدفوعات: <strong><?php echo $totalItems; ?></strong></span>
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
            
            <?php if (empty($payments)): ?>
            <div class="alert alert-info">لا توجد مدفوعات مسجلة</div>
            <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>رقم الإيصال</th>
                            <th>النوع</th>
                            <th>الاسم</th>
                            <th>المبلغ</th>
                            <th>التاريخ</th>
                            <th>طريقة الدفع</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php foreach ($payments as $payment): 
                            $isSupplier = $payment['type'] === 'supplier';
                            $printPage = $isSupplier ? 'print_supplier.php' : 'print_customer.php';
                        ?>
                        <tr>
                            <td><strong><?php echo $payment['payment_number']; ?></strong></td>
                            <td>
                                <?php if ($isSupplier): ?>
                                    <span class="badge badge-success">للمورد</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">من عميل</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $payment['entity_name']; ?></td>
                            <td><strong><?php echo formatCurrency($payment['amount']); ?></strong></td>
                            <td><?php echo date('Y/m/d', strtotime($payment['payment_date'])); ?></td>
                            <td><?php echo $payment['payment_method']; ?></td>
                            <td>
                                <a href="<?php echo $printPage; ?>?id=<?php echo $payment['id']; ?>" class="btn btn-primary btn-sm" target="_blank" title="طباعة">🖨️</a>
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
