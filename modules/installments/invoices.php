<?php
/**
 * Installment Plans & Invoices List
 * قائمة خطط وفواتير التقسيط
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requirePermission('installments.view');

$pageTitle = 'فواتير الأقساط';
$view = $_GET['view'] ?? 'invoices'; // 'invoices' or 'plans'

// Filters from GET
$search = trim($_GET['search'] ?? '');
$filterType = $_GET['type'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Pagination
$perPage = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

if ($view === 'invoices') {
    // Build query for installment payments (receipts) from the payments table
    // We filter for payments that are related to installments (notes contain 'قسط')
    $sql = "SELECT *
     FROM payments
     WHERE (notes LIKE '%قسط%' OR reference LIKE '%قسط%')";
    $params = [];
    
    if ($search !== '') {
        $sql .= " AND (payment_number LIKE ? OR entity_name LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if ($filterType !== '') {
        $sql .= " AND type = ?";
        $params[] = $filterType;
    }
    
    if ($dateFrom !== '') {
        $sql .= " AND payment_date >= ?";
        $params[] = $dateFrom;
    }
    
    if ($dateTo !== '') {
        $sql .= " AND payment_date <= ?";
        $params[] = $dateTo;
    }
    
    $countSql = preg_replace('/SELECT \*.*?FROM payments.*?WHERE/s', 'SELECT COUNT(*) as count FROM payments WHERE', $sql);
    $totalItems = getRow($countSql, $params)['count'] ?? 0;
    $totalPages = ceil($totalItems / $perPage);
    
    $sql .= " ORDER BY payment_date DESC, id DESC LIMIT $perPage OFFSET $offset";
    $records = getRows($sql, $params);
    
} else {
    $filterStatus = $_GET['status'] ?? '';
    
    // Build query for plans
    $sql = "SELECT p.*, 
            (SELECT COUNT(*) FROM installment_payments ip WHERE ip.plan_id = p.id AND ip.status = 'paid') as paid_count,
            (SELECT COUNT(*) FROM installment_payments ip WHERE ip.plan_id = p.id AND ip.status = 'overdue') as overdue_count
     FROM installment_plans p
     WHERE 1=1";
    $params = [];
    
    if ($search !== '') {
        $sql .= " AND (p.entity_name LIKE ? OR p.id LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if ($filterType !== '') {
        $sql .= " AND p.type = ?";
        $params[] = $filterType;
    }
    
    if ($filterStatus !== '') {
        $sql .= " AND p.status = ?";
        $params[] = $filterStatus;
    }
    
    if ($dateFrom !== '') {
        $sql .= " AND p.start_date >= ?";
        $params[] = $dateFrom;
    }
    
    if ($dateTo !== '') {
        $sql .= " AND p.start_date <= ?";
        $params[] = $dateTo;
    }
    
    $countSql = preg_replace('/SELECT p\.\*.*?FROM installment_plans p/s', 'SELECT COUNT(*) as count FROM installment_plans p', $sql);
    $totalItems = getRow($countSql, $params)['count'] ?? 0;
    $totalPages = ceil($totalItems / $perPage);
    
    $sql .= " ORDER BY p.created_at DESC LIMIT $perPage OFFSET $offset";
    $records = getRows($sql, $params);
}

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<style>
.nav-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    border-bottom: 2px solid #e5e7eb;
    padding-bottom: 10px;
}
.nav-tab {
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 1.1em;
    font-weight: bold;
    color: #4b5563;
    text-decoration: none;
    transition: all 0.3s;
    background: #f3f4f6;
}
.nav-tab:hover {
    background: #e5e7eb;
}
.nav-tab.active {
    background: #8b5cf6;
    color: white;
}
.nav-tab.active:hover {
    background: #7c3aed;
}

.live-search-input {
    font-size: 1.1em;
    padding: 12px 15px;
    border: 2px solid #8b5cf6;
    border-radius: 10px;
    transition: all 0.3s;
}
.live-search-input:focus {
    border-color: #7c3aed;
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2);
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
    background: #f5f3ff;
    padding: 10px 15px;
    border-radius: 8px;
    margin-bottom: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.status-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.8em;
    font-weight: bold;
}
.status-badge.active, .status-badge.partial { background: #fef3c7; color: #d97706; }
.status-badge.completed, .status-badge.paid { background: #d1fae5; color: #059669; }
.status-badge.cancelled, .status-badge.unpaid { background: #fee2e2; color: #dc2626; }

.progress-mini {
    width: 100px;
    height: 8px;
    background: #e5e7eb;
    border-radius: 4px;
    overflow: hidden;
    display: inline-block;
    vertical-align: middle;
    margin-left: 5px;
}
.progress-mini .fill {
    height: 100%;
    background: linear-gradient(90deg, #10b981, #059669);
}
</style>

<div class="container">
    <div class="nav-tabs">
        <a href="?view=invoices" class="nav-tab <?php echo $view === 'invoices' ? 'active' : ''; ?>">📋 إيصالات الدفع (فواتير الأقساط)</a>
        <a href="?view=plans" class="nav-tab <?php echo $view === 'plans' ? 'active' : ''; ?>">🗓️ خطط التقسيط</a>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-between align-center" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white;">
            <span><?php echo $view === 'invoices' ? '📋 إيصالات الدفع' : '🗓️ خطط التقسيط'; ?></span>
            <div>
                <?php if ($view === 'plans'): ?>
                <a href="create.php" class="btn" style="background: white; color: #7c3aed;">+ إنشاء خطة قسط</a>
                <?php endif; ?>
                <a href="index.php" class="btn btn-secondary">← رجوع</a>
            </div>
        </div>
        
        <div class="card-body">
            <!-- Search Form -->
            <form method="GET" id="searchForm" class="mb-1">
                <input type="hidden" name="view" value="<?php echo $view; ?>">
                
                <div style="margin-bottom: 15px;">
                    <input type="text" name="search" id="liveSearch" class="form-control live-search-input" 
                           placeholder="🔍 بحث سريع..."
                           value="<?php echo htmlspecialchars($search); ?>"
                           autocomplete="off">
                </div>
                
                <!-- Filters -->
                <div class="filter-row">
                    <div class="filter-group">
                        <label>النوع</label>
                        <select name="type" id="filterType" class="form-control">
                            <option value="">الكل</option>
                            <option value="customer" <?php echo $filterType === 'customer' ? 'selected' : ''; ?>>عميل</option>
                            <option value="supplier" <?php echo $filterType === 'supplier' ? 'selected' : ''; ?>>مورد</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>من تاريخ الدفع</label>
                        <input type="date" name="date_from" id="filterDateFrom" class="form-control" 
                               value="<?php echo htmlspecialchars($dateFrom); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label>إلى تاريخ الدفع</label>
                        <input type="date" name="date_to" id="filterDateTo" class="form-control"
                               value="<?php echo htmlspecialchars($dateTo); ?>">
                    </div>
                    
                    <?php if ($view === 'plans'): ?>
                    <div class="filter-group">
                        <label>الحالة</label>
                        <select name="status" id="filterStatus" class="form-control">
                            <option value="">الكل</option>
                            <option value="active" <?php echo (isset($filterStatus) && $filterStatus === 'active') ? 'selected' : ''; ?>>نشط</option>
                            <option value="completed" <?php echo (isset($filterStatus) && $filterStatus === 'completed') ? 'selected' : ''; ?>>مكتمل</option>
                            <option value="cancelled" <?php echo (isset($filterStatus) && $filterStatus === 'cancelled') ? 'selected' : ''; ?>>ملغي</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="filter-group" style="display: flex; align-items: flex-end;">
                        <a href="invoices.php?view=<?php echo $view; ?>" class="btn btn-secondary" style="width: 100%;">↺ إعادة تعيين</a>
                    </div>
                </div>
            </form>
            
            <!-- Results Count with Pagination -->
            <div class="search-results-count">
                <span>📊 عدد النتائج: <strong><?php echo $totalItems; ?></strong> نتيجة</span>
                <div class="pagination" style="display: flex; align-items: center; gap: 8px;">
                    <?php 
                    $queryParamsArr = [
                        'view' => $view,
                        'search' => $search,
                        'type' => $filterType,
                        'date_from' => $dateFrom,
                        'date_to' => $dateTo
                    ];
                    if ($view === 'plans') $queryParamsArr['status'] = $filterStatus ?? '';
                    
                    $queryParams = http_build_query($queryParamsArr);
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
                <table class="table">
                    <?php if ($view === 'invoices'): ?>
                    <thead>
                        <tr>
                            <th>رقم الإيصال</th>
                            <th>النوع</th>
                            <th>العميل/المورد</th>
                            <th>تاريخ الدفع</th>
                            <th>المبلغ المدفوع</th>
                            <th>طريقة الدفع</th>
                            <th>الموظف</th>
                            <th>ملاحظات</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="9" class="text-center">لا توجد إيصالات دفع (فواتير أقساط)</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($records as $payment): ?>
                        <tr>
                            <td><strong><?php echo $payment['payment_number']; ?></strong></td>
                            <td>
                                <span class="badge <?php echo $payment['type'] === 'customer' ? 'badge-info' : 'badge-success'; ?>">
                                    <?php echo $payment['type'] === 'customer' ? '👤 عميل' : '🚚 مورد'; ?>
                                </span>
                            </td>
                            <td><?php echo $payment['entity_name']; ?></td>
                            <td><?php echo date('Y/m/d', strtotime($payment['payment_date'])); ?></td>
                            <td style="color: #10b981; font-weight: bold;">
                                <?php echo formatCurrency($payment['amount']); ?>
                            </td>
                            <td><?php echo $payment['payment_method'] ?: 'كاش'; ?></td>
                            <td><?php echo $payment['handled_by'] ?: 'المدير'; ?></td>
                            <td style="font-size: 0.8em;"><?php echo $payment['notes']; ?></td>
                            <td>
                                <?php 
                                // Extract plan_id from reference e.g., 'قسط-12' or 'INS-12'
                                $planId = 0;
                                if (preg_match('/(?:قسط|INS)-(\d+)/', $payment['reference'], $matches)) {
                                    $planId = $matches[1];
                                }
                                ?>
                                <a href="print_payment.php?plan_id=<?php echo $planId; ?>&amount=<?php echo $payment['amount']; ?>" class="btn btn-primary" title="طباعة الإيصال">🖨️ طباعة</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    
                    <?php else: ?>
                    <thead>
                        <tr>
                            <th>رقم الخطة</th>
                            <th>النوع</th>
                            <th>العميل/المورد</th>
                            <th>تاريخ البدء</th>
                            <th>الإجمالي</th>
                            <th>المدفوع</th>
                            <th>المتبقي</th>
                            <th>التقدم</th>
                            <th>الحالة</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="10" class="text-center">لا توجد خطط تقسيط</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($records as $plan): ?>
                        <?php 
                        $progress = $plan['total_amount'] > 0 ? ($plan['paid_amount'] / $plan['total_amount']) * 100 : 0;
                        ?>
                        <tr>
                            <td><strong><?php echo str_pad($plan['id'], 5, '0', STR_PAD_LEFT); ?></strong></td>
                            <td>
                                <span class="badge <?php echo $plan['type'] === 'customer' ? 'badge-info' : 'badge-success'; ?>">
                                    <?php echo $plan['type'] === 'customer' ? '👤 عميل' : '🚚 مورد'; ?>
                                </span>
                            </td>
                            <td><?php echo $plan['entity_name']; ?></td>
                            <td><?php echo date('Y/m/d', strtotime($plan['start_date'])); ?></td>
                            <td><?php echo formatCurrency($plan['total_amount']); ?></td>
                            <td style="color: #10b981;"><?php echo formatCurrency($plan['paid_amount']); ?></td>
                            <td style="color: #f59e0b;"><?php echo formatCurrency($plan['remaining_amount']); ?></td>
                            <td>
                                <div class="progress-mini">
                                    <div class="fill" style="width: <?php echo $progress; ?>%;"></div>
                                </div>
                                <span style="font-size: 0.8em;"><?php echo round($progress); ?>%</span>
                            </td>
                            <td>
                                <?php
                                $statusClass = [
                                    'active' => 'active',
                                    'completed' => 'completed',
                                    'cancelled' => 'cancelled'
                                ];
                                $statusText = [
                                    'active' => 'نشط',
                                    'completed' => 'مكتمل',
                                    'cancelled' => 'ملغي'
                                ];
                                ?>
                                <span class="status-badge <?php echo $statusClass[$plan['status']] ?? 'active'; ?>">
                                    <?php 
                                    if ($plan['overdue_count'] > 0 && $plan['status'] === 'active') {
                                        echo '⏰ متأخر';
                                    } else {
                                        echo $statusText[$plan['status']] ?? 'نشط';
                                    }
                                    ?>
                                </span>
                            </td>
                            <td>
                                <a href="view.php?id=<?php echo $plan['id']; ?>" class="btn btn-primary" title="عرض">👁️</a>
                                <?php if ($plan['status'] === 'active'): ?>
                                <a href="pay.php?plan_id=<?php echo $plan['id']; ?>" class="btn btn-success" title="دفع">💵</a>
                                <?php endif; ?>
                                <a href="print_payment.php?plan_id=<?php echo $plan['id']; ?>&amount=0" class="btn btn-secondary" title="طباعة">🖨️</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php endif; ?>
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
    var filterStatus = document.getElementById('filterStatus');
    var filterPaymentStatus = document.getElementById('filterPaymentStatus');
    
    var timeout = null;
    
    if (liveSearch && searchForm) {
        liveSearch.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                searchForm.submit();
            }, 500);
        });
    }
    
    if (filterType) filterType.addEventListener('change', function() { searchForm.submit(); });
    if (filterStatus) filterStatus.addEventListener('change', function() { searchForm.submit(); });
    if (filterPaymentStatus) filterPaymentStatus.addEventListener('change', function() { searchForm.submit(); });
    if (filterDateFrom) filterDateFrom.addEventListener('change', function() { searchForm.submit(); });
    if (filterDateTo) filterDateTo.addEventListener('change', function() { searchForm.submit(); });
});
</script>

<?php include '../../includes/footer.php'; ?>
