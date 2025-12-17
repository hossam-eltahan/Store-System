<?php
/**
 * Products List
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';

$pageTitle = 'إدارة الأصناف';

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $product = getRow("SELECT * FROM products WHERE id = ?", [$id]);
    
    if ($product) {
        // Delete image if exists
        if ($product['image']) {
            deleteImage($product['image']);
        }
        
        execute("DELETE FROM products WHERE id = ?", [$id]);
        logActivity('حذف منتج', "تم حذف المنتج: {$product['name']}");
        setSuccess('تم حذف الصنف بنجاح');
    }
    
    redirect('index.php');
}

// Filters
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? '';

$where = [];
$params = [];

if ($search) {
    // Determine if search matches a numeric ID (for precise code search) or text
    // But since code is VARCHAR, we treat all as string
    $where[] = "(code LIKE ? OR name LIKE ? OR description LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($filter === 'low_stock') {
    $where[] = "stock_quantity <= min_stock_level";
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Pagination
$perPage = 10; // Items per page to fit screen
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// Get total count
$totalProducts = getRow("SELECT COUNT(*) as count FROM products $whereClause", $params)['count'];
$totalPages = ceil($totalProducts / $perPage);

// Get paginated products
$products = getRows(
    "SELECT * FROM products $whereClause ORDER BY name LIMIT $perPage OFFSET $offset",
    $params
);

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>📦 إدارة الأصناف</span>
            <a href="add.php" class="btn btn-primary">+ إضافة صنف جديد</a>
        </div>
        
        <div class="card-body">
            <!-- Search and Filter -->
            <form method="GET" class="mb-2" id="productFilterForm">
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <input type="text" name="search" id="productSearch" class="form-control" placeholder="🔍 بحث بالكود أو الاسم..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="form-group">
                        <select name="filter" id="productFilter" class="form-control">
                            <option value="">كل الأصناف</option>
                            <option value="low_stock" <?php echo $filter === 'low_stock' ? 'selected' : ''; ?>>مخزون منخفض</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <?php if ($search || $filter): ?>
                        <a href="index.php" class="btn btn-secondary">✕ إلغاء</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
            
            <!-- Products Count with Pagination -->
            <div class="search-results-count">
                <span>📊 إجمالي الأصناف: <strong><?php echo $totalProducts; ?></strong></span>
                <div class="pagination" style="display: flex; align-items: center; gap: 8px;">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>" class="btn btn-secondary btn-sm">← السابق</a>
                    <?php endif; ?>
                    <span class="badge badge-info">صفحة <?php echo $page; ?> من <?php echo max(1, $totalPages); ?></span>
                    <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>" class="btn btn-secondary btn-sm">التالي →</a>
                    <?php endif; ?>
                </div>
                <?php if ($filter === 'low_stock'): ?>
                <span class="badge badge-warning">⚠️ مخزون منخفض</span>
                <?php endif; ?>
            </div>
            
            <!-- Products Table -->
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>الكود</th>
                            <th>الصورة</th>
                            <th>الاسم</th>
                            <th>الوحدة</th>
                            <th>السعر</th>
                            <th>المخزون</th>
                            <th>الحالة</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="productsTableBody">
                        <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="8" class="text-center">لا توجد أصناف</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($products as $product): ?>
                        <tr>
                            <td><strong><?php echo $product['code']; ?></strong></td>
                            <td>
                                <?php if ($product['image']): ?>
                                    <img src="../../assets/uploads/<?php echo $product['image']; ?>" 
                                         alt="<?php echo $product['name']; ?>" 
                                         style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">
                                <?php else: ?>
                                    <div style="width: 50px; height: 50px; background: #f0f0f0; border-radius: 5px; display: flex; align-items: center; justify-content: center;">
                                        📦
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $product['name']; ?></td>
                            <td><?php echo $product['unit']; ?></td>
                            <td><?php echo formatCurrency($product['price']); ?></td>
                            <td>
                                <strong class="<?php echo $product['stock_quantity'] <= $product['min_stock_level'] ? 'text-danger' : 'text-success'; ?>">
                                    <?php echo $product['stock_quantity']; ?>
                                </strong>
                            </td>
                            <td>
                                <?php if ($product['stock_quantity'] <= 0): ?>
                                    <span class="badge badge-danger">نفذ</span>
                                <?php elseif ($product['stock_quantity'] <= $product['min_stock_level']): ?>
                                    <span class="badge badge-warning">منخفض</span>
                                <?php else: ?>
                                    <span class="badge badge-success">متوفر</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="edit.php?id=<?php echo $product['id']; ?>" class="btn btn-primary">✏️ تعديل</a>
                                <button type="button" class="btn btn-danger btn-delete" 
                                        data-name="<?php echo htmlspecialchars($product['name']); ?>" 
                                        data-url="index.php?delete=<?php echo $product['id']; ?>">🗑️ حذف</button>
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

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal-overlay" style="display: none;">
    <div class="modal-box">
        <div class="modal-header">
            <span style="font-size: 32px;">⚠️</span>
            <h3>تأكيد الحذف</h3>
        </div>
        <div class="modal-body">
            <p>هل أنت متأكد من حذف الصنف:</p>
            <p id="deleteProductName" style="font-weight: bold; font-size: 1.2em; color: #dc2626;"></p>
            <p style="color: #666; font-size: 0.9em;">⚠️ هذا الإجراء لا يمكن التراجع عنه!</p>
        </div>
        <div class="modal-footer">
            <a id="confirmDeleteBtn" href="#" class="btn btn-danger">🗑️ نعم، احذف</a>
            <button type="button" class="btn btn-secondary" id="cancelDeleteBtn">❌ إلغاء</button>
        </div>
    </div>
</div>

<style>
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    z-index: 9999;
    display: flex;
    justify-content: center;
    align-items: center;
    animation: fadeIn 0.2s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-box {
    background: white;
    border-radius: 15px;
    padding: 30px;
    max-width: 400px;
    width: 90%;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from { transform: translateY(-50px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.modal-header {
    margin-bottom: 20px;
}

.modal-header h3 {
    margin: 10px 0 0 0;
    color: #333;
}

.modal-body {
    margin-bottom: 25px;
}

.modal-body p {
    margin: 10px 0;
}

.modal-footer {
    display: flex;
    gap: 10px;
    justify-content: center;
}

.modal-footer .btn {
    min-width: 120px;
}

/* Pagination */
.pagination-container {
    margin-top: var(--spacing-sm);
}

.pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--spacing-sm);
}

.pagination-info {
    color: var(--text-secondary);
    font-size: 0.85rem;
}

.results-info {
    color: var(--text-secondary);
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    margin-bottom: var(--spacing-sm);
}

.mt-1 {
    margin-top: var(--spacing-sm);
}

.mb-1 {
    margin-bottom: var(--spacing-sm);
}
</style>

<script>
// البحث الفوري - Live Search
document.addEventListener('DOMContentLoaded', function() {
    var searchInput = document.getElementById('productSearch');
    var filterSelect = document.getElementById('productFilter');
    var filterForm = document.getElementById('productFilterForm');
    var tableBody = document.getElementById('productsTableBody');
    
    // Auto-submit filter on change
    if (filterSelect && filterForm) {
        filterSelect.addEventListener('change', function() {
            filterForm.submit();
        });
    }
    
    // Auto-submit search after typing
    if (searchInput && filterForm) {
        var timeout = null;
        searchInput.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                filterForm.submit();
            }, 500);
        });
    }
    
    // Delete Modal
    var modal = document.getElementById('deleteModal');
    var confirmBtn = document.getElementById('confirmDeleteBtn');
    var cancelBtn = document.getElementById('cancelDeleteBtn');
    var productNameSpan = document.getElementById('deleteProductName');
    
    // Attach click handlers to all delete buttons
    var deleteButtons = document.querySelectorAll('.btn-delete');
    for (var j = 0; j < deleteButtons.length; j++) {
        deleteButtons[j].onclick = function(e) {
            e.preventDefault();
            var productName = this.getAttribute('data-name');
            var deleteUrl = this.getAttribute('data-url');
            
            productNameSpan.textContent = productName;
            confirmBtn.href = deleteUrl;
            modal.style.display = 'flex';
        };
    }
    
    // Cancel button closes modal
    if (cancelBtn) {
        cancelBtn.onclick = function() {
            modal.style.display = 'none';
        };
    }
    
    // Click outside modal closes it
    if (modal) {
        modal.onclick = function(e) {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        };
    }
    
    // Escape key closes modal
    document.onkeydown = function(e) {
        if (e.key === 'Escape' && modal && modal.style.display === 'flex') {
            modal.style.display = 'none';
        }
    };
});
</script>

<?php include '../../includes/footer.php'; ?>
