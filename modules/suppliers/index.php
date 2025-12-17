<?php
/**
 * Suppliers List
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';

$pageTitle = 'إدارة الموردين';

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $supplier = getRow("SELECT * FROM suppliers WHERE id = ?", [$id]);
    
    if ($supplier) {
        // Check if supplier has invoices
        $hasInvoices = getRow("SELECT id FROM invoices WHERE supplier_id = ?", [$id]);
        
        if ($hasInvoices) {
            setError('لا يمكن حذف المورد لوجود فواتير مسجلة له');
        } else {
            execute("DELETE FROM suppliers WHERE id = ?", [$id]);
            logActivity('حذف مورد', "تم حذف المورد: {$supplier['name']}");
            setSuccess('تم حذف المورد بنجاح');
        }
    }
    
    redirect('index.php');
}

// Search and filter handling
$search = trim($_GET['search'] ?? '');
$dayFilter = $_GET['day'] ?? '';
$todayDay = getTodayDayName();

// Arabic day names for the dropdown
$arabicDays = ['السبت', 'الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة'];

// Build query
$sql = "SELECT * FROM suppliers WHERE 1=1";
$params = [];

// Text search filter
if ($search !== '') {
    $sql .= " AND (name LIKE ? OR phone LIKE ? OR expected_products LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Day filter
if ($dayFilter !== '') {
    if ($dayFilter === 'today') {
        $sql .= " AND visit_days LIKE ?";
        $params[] = "%$todayDay%";
        $pageTitle = 'موردين قادمين اليوم';
    } else {
        $sql .= " AND visit_days LIKE ?";
        $params[] = "%$dayFilter%";
        $pageTitle = "موردين قادمين يوم $dayFilter";
    }
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
$suppliers = getRows($sql, $params);

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>🚚 إدارة الموردين</span>
            <a href="add.php" class="btn btn-primary">+ إضافة مورد جديد</a>
        </div>
        
        <div class="card-body">
            <!-- Search Form -->
            <form method="GET" class="search-form mb-1" id="searchForm">
                <div class="search-row">
                    <div class="search-group">
                        <input type="text" name="search" id="supplierSearch" class="form-control" 
                               placeholder="🔍 بحث بالاسم أو التليفون أو البضاعة..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="search-group">
                        <select name="day" id="dayFilter" class="form-control">
                            <option value="">كل الأيام</option>
                            <option value="today" <?php echo $dayFilter === 'today' ? 'selected' : ''; ?>>📍 قادمين اليوم (<?php echo $todayDay; ?>)</option>
                            <?php foreach ($arabicDays as $day): ?>
                            <option value="<?php echo $day; ?>" <?php echo $dayFilter === $day ? 'selected' : ''; ?>><?php echo $day; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="search-actions">
                        <?php if ($search !== '' || $dayFilter !== ''): ?>
                        <a href="index.php" class="btn btn-secondary">✕ إلغاء</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
            
            <!-- Results Count with Pagination -->
            <div class="search-results-count">
                <span>📊 عدد الموردين: <strong><?php echo $totalItems; ?></strong></span>
                <div class="pagination" style="display: flex; align-items: center; gap: 8px;">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&day=<?php echo urlencode($dayFilter); ?>" class="btn btn-secondary btn-sm">← السابق</a>
                    <?php endif; ?>
                    <span class="badge badge-info">صفحة <?php echo $page; ?> من <?php echo max(1, $totalPages); ?></span>
                    <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&day=<?php echo urlencode($dayFilter); ?>" class="btn btn-secondary btn-sm">التالي →</a>
                    <?php endif; ?>
                </div>
                <?php if ($dayFilter === 'today'): ?>
                <span class="badge badge-success">📍 اليوم <?php echo $todayDay; ?></span>
                <?php elseif ($dayFilter !== ''): ?>
                <span class="badge badge-info">📅 <?php echo $dayFilter; ?></span>
                <?php endif; ?>
            </div>
            
            <!-- Suppliers Table -->
            <div class="table-container">
                <table class="table" id="suppliersTable">
                    <thead>
                        <tr>
                            <th>الاسم</th>
                            <th>التليفون</th>
                            <th>أيام الزيارة</th>
                            <th>البضاعة المتوقعة</th>
                            <th>الرصيد</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($suppliers)): ?>
                        <tr>
                            <td colspan="6" class="text-center">لا يوجد موردين مطابقين للبحث</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($suppliers as $supplier): ?>
                        <tr>
                            <td><strong><?php echo $supplier['name']; ?></strong></td>
                            <td><?php echo $supplier['phone']; ?></td>
                            <td>
                                <?php 
                                $days = json_decode($supplier['visit_days'] ?? '[]');
                                if ($days && is_array($days)) {
                                    foreach ($days as $day) {
                                        $isToday = ($day === $todayDay);
                                        $badgeClass = $isToday ? 'badge-success' : 'badge-info';
                                        echo "<span class='badge $badgeClass' style='margin-left: 5px;'>$day</span>";
                                    }
                                }
                                ?>
                            </td>
                            <td><?php echo $supplier['expected_products']; ?></td>
                            <td><?php echo formatCurrency($supplier['balance']); ?></td>
                            <td>
                                <a href="view.php?id=<?php echo $supplier['id']; ?>" class="btn btn-info btn-sm" title="تفاصيل">👁️</a>
                                <a href="../invoices/list.php?supplier_id=<?php echo $supplier['id']; ?>" class="btn btn-secondary btn-sm" title="الفواتير">📜</a>
                                <a href="edit.php?id=<?php echo $supplier['id']; ?>" class="btn btn-primary btn-sm" title="تعديل">✏️</a>
                                <button type="button" class="btn btn-danger btn-sm btn-delete" 
                                        data-name="<?php echo htmlspecialchars($supplier['name']); ?>" 
                                        data-url="index.php?delete=<?php echo $supplier['id']; ?>" title="حذف">🗑️</button>
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

<style>
.search-form {
    background: var(--bg-color);
    padding: var(--spacing-sm);
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-md);
}

.search-row {
    display: flex;
    gap: var(--spacing-sm);
    flex-wrap: wrap;
    align-items: center;
}

.search-group {
    flex: 1;
    min-width: 180px;
}

.search-group input,
.search-group select {
    width: 100%;
}

.search-actions {
    display: flex;
    gap: var(--spacing-xs);
}

.results-info {
    color: var(--text-secondary);
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
}

.mb-1 {
    margin-bottom: var(--spacing-sm);
}
</style>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal-overlay" style="display: none;">
    <div class="modal-box">
        <div class="modal-header">
            <span style="font-size: 32px;">⚠️</span>
            <h3>تأكيد الحذف</h3>
        </div>
        <div class="modal-body">
            <p>هل أنت متأكد من حذف المورد:</p>
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
    // Auto-search on input
    var searchInput = document.getElementById('supplierSearch');
    var dayFilter = document.getElementById('dayFilter');
    var searchForm = document.getElementById('searchForm');
    
    // Text search - live filter on input
    if (searchInput) {
        var timeout = null;
        searchInput.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                searchForm.submit();
            }, 500); // Submit after 500ms of no typing
        });
    }
    
    // Day filter - submit immediately on change
    if (dayFilter) {
        dayFilter.addEventListener('change', function() {
            searchForm.submit();
        });
    }
    
    // Delete Modal
    var modal = document.getElementById('deleteModal');
    var confirmBtn = document.getElementById('confirmDeleteBtn');
    var cancelBtn = document.getElementById('cancelDeleteBtn');
    var itemNameSpan = document.getElementById('deleteItemName');
    
    // Attach click handlers to all delete buttons
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
