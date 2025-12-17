<?php
/**
 * Customers List
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';

$pageTitle = 'إدارة العملاء';

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $customer = getRow("SELECT * FROM customers WHERE id = ?", [$id]);
    
    if ($customer) {
        // Check if customer has invoices
        $hasInvoices = getRow("SELECT id FROM invoices WHERE customer_id = ?", [$id]);
        
        if ($hasInvoices) {
            setError('لا يمكن حذف العميل لوجود فواتير مسجلة له');
        } else {
            execute("DELETE FROM customers WHERE id = ?", [$id]);
            logActivity('حذف عميل', "تم حذف العميل: {$customer['name']}");
            setSuccess('تم حذف العميل بنجاح');
        }
    }
    
    redirect('index.php');
}

// Search handling
$search = trim($_GET['search'] ?? '');

// Build query
$sql = "SELECT * FROM customers WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (name LIKE ? OR phone LIKE ? OR address LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Pagination
$perPage = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// Get total count
$countSql = str_replace("SELECT *", "SELECT COUNT(*) as count", $sql);
$totalItems = getRow($countSql, $params)['count'];
$totalPages = ceil($totalItems / $perPage);

// Get paginated results
$sql .= " ORDER BY name LIMIT $perPage OFFSET $offset";
$customers = getRows($sql, $params);

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>👥 إدارة العملاء</span>
            <a href="add.php" class="btn btn-primary">+ إضافة عميل جديد</a>
        </div>
        
        <div class="card-body">
            <!-- Search -->
            <form method="GET" class="mb-1" id="searchForm">
                <div class="form-row">
                    <div class="form-group" style="flex: 3;">
                        <input type="text" name="search" id="customerSearch" class="form-control" 
                               placeholder="🔍 بحث بالاسم أو التليفون..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <?php if ($search !== ''): ?>
                    <div class="form-group" style="flex: 1;">
                        <a href="index.php" class="btn btn-secondary btn-block">✕ إلغاء</a>
                    </div>
                    <?php endif; ?>
                </div>
            </form>
            
            <!-- Count with Pagination -->
            <div class="search-results-count">
                <span>📊 إجمالي العملاء: <strong><?php echo $totalItems; ?></strong></span>
                <div class="pagination" style="display: flex; align-items: center; gap: 8px;">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-secondary btn-sm">← السابق</a>
                    <?php endif; ?>
                    <span class="badge badge-info">صفحة <?php echo $page; ?> من <?php echo max(1, $totalPages); ?></span>
                    <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-secondary btn-sm">التالي →</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Customers Table -->
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>الاسم</th>
                            <th>التليفون</th>
                            <th>العنوان</th>
                            <th>الرصيد</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="customersTableBody">
                        <?php if (empty($customers)): ?>
                        <tr>
                            <td colspan="5" class="text-center">لا يوجد عملاء مطابقين للبحث</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><strong><?php echo $customer['name']; ?></strong></td>
                            <td><?php echo $customer['phone']; ?></td>
                            <td><?php echo $customer['address'] ?? ''; ?></td>
                            <td>
                                <?php if ($customer['balance'] < 0): ?>
                                    <span class="text-danger" style="direction: ltr;"><?php echo formatCurrency(abs($customer['balance'])); ?> (مدين)</span>
                                <?php elseif ($customer['balance'] > 0): ?>
                                    <span class="text-success"><?php echo formatCurrency($customer['balance']); ?> (دائن)</span>
                                <?php else: ?>
                                    0.00
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="view.php?id=<?php echo $customer['id']; ?>" class="btn btn-info btn-sm" title="تفاصيل">👁️</a>
                                <a href="../invoices/list.php?customer_id=<?php echo $customer['id']; ?>" class="btn btn-secondary btn-sm" title="الفواتير">📜</a>
                                <a href="edit.php?id=<?php echo $customer['id']; ?>" class="btn btn-primary btn-sm" title="تعديل">✏️</a>
                                <button type="button" class="btn btn-danger btn-sm btn-delete" 
                                        data-name="<?php echo htmlspecialchars($customer['name']); ?>" 
                                        data-url="index.php?delete=<?php echo $customer['id']; ?>" title="حذف">🗑️</button>
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
            <p>هل أنت متأكد من حذف العميل:</p>
            <p id="deleteItemName" style="font-weight: bold; font-size: 1.2em; color: #dc2626;"></p>
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

.btn-info {
    background: linear-gradient(135deg, #0ea5e9, #0284c7);
    color: white;
}

.btn-info:hover {
    background: linear-gradient(135deg, #0284c7, #0369a1);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-search
    var searchInput = document.getElementById('customerSearch');
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
    
    // Delete Modal
    var modal = document.getElementById('deleteModal');
    var confirmBtn = document.getElementById('confirmDeleteBtn');
    var cancelBtn = document.getElementById('cancelDeleteBtn');
    var itemNameSpan = document.getElementById('deleteItemName');
    
    var deleteButtons = document.querySelectorAll('.btn-delete');
    for (var j = 0; j < deleteButtons.length; j++) {
        deleteButtons[j].onclick = function(e) {
            e.preventDefault();
            var itemName = this.getAttribute('data-name');
            var deleteUrl = this.getAttribute('data-url');
            
            itemNameSpan.textContent = itemName;
            confirmBtn.href = deleteUrl;
            modal.style.display = 'flex';
        };
    }
    
    if (cancelBtn) {
        cancelBtn.onclick = function() {
            modal.style.display = 'none';
        };
    }
    
    if (modal) {
        modal.onclick = function(e) {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        };
    }
    
    document.onkeydown = function(e) {
        if (e.key === 'Escape' && modal && modal.style.display === 'flex') {
            modal.style.display = 'none';
        }
    };
});
</script>

<?php include '../../includes/footer.php'; ?>
