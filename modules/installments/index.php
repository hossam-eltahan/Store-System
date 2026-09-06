<?php
/**
 * Installments Index - قائمة الأقساط
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requireAnyPermission(['installments.view', 'installments.create', 'installments.pay']);

$pageTitle = 'الأقساط';

// Update overdue status first
execute("
    UPDATE installment_payments 
    SET status = 'overdue' 
    WHERE status = 'pending' AND due_date < CURDATE()
");

// Filters
$filter = $_GET['filter'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'active';
$search = $_GET['search'] ?? '';

// Build base query
$whereConditions = [];
$params = [];

// Type filter
if ($filter === 'customer') {
    $whereConditions[] = "ip.type = 'customer'";
} elseif ($filter === 'supplier') {
    $whereConditions[] = "ip.type = 'supplier'";
}

// Status filter
if ($statusFilter === 'active') {
    $whereConditions[] = "ip.status = 'active'";
} elseif ($statusFilter === 'completed') {
    $whereConditions[] = "ip.status = 'completed'";
} elseif ($statusFilter === 'overdue') {
    $whereConditions[] = "ip.status = 'active'";
}

// Search
if ($search) {
    $whereConditions[] = "ip.entity_name LIKE ?";
    $params[] = "%$search%";
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Pagination
$perPage = 10;
$page = max(1, intval($_GET['page'] ?? 1));

// Get total count first
$totalItems = getRow("SELECT COUNT(*) as count FROM installment_plans ip $whereClause", $params)['count'];
$totalPages = ceil($totalItems / $perPage);
$offset = ($page - 1) * $perPage;

// Get paginated plans
$plans = getRows("SELECT * FROM installment_plans ip $whereClause ORDER BY ip.created_at DESC LIMIT $perPage OFFSET $offset", $params);

// Add counts to each plan and create missing payments
foreach ($plans as &$plan) {
    // Check if payments exist
    $paymentCountResult = getRow("SELECT COUNT(*) as cnt FROM installment_payments WHERE plan_id = ?", [$plan['id']]);
    $paymentCount = $paymentCountResult ? intval($paymentCountResult['cnt']) : 0;
    
    // Create payments if missing
    if ($paymentCount == 0) {
        $numberOfInstallments = $plan['number_of_installments'];
        $installmentAmount = $plan['installment_amount'];
        $totalAmount = $plan['total_amount'];
        
        $currentDate = new DateTime($plan['start_date']);
        
        for ($i = 1; $i <= $numberOfInstallments; $i++) {
            $paymentNumber = 'INS-' . str_pad($plan['id'], 5, '0', STR_PAD_LEFT) . '-' . $i;
            $amount = ($i == $numberOfInstallments) ? 
                ($totalAmount - ($installmentAmount * ($numberOfInstallments - 1))) : 
                $installmentAmount;
            
            $status = ($currentDate < new DateTime()) ? 'overdue' : 'pending';
            
            if ($plan['paid_amount'] >= ($installmentAmount * $i)) {
                $status = 'paid';
            }
            
            insert(
                "INSERT INTO installment_payments (plan_id, payment_number, installment_number, amount, due_date, status)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$plan['id'], $paymentNumber, $i, $amount, $currentDate->format('Y-m-d'), $status]
            );
            
            $currentDate->modify('+1 month');
        }
    }
    
    // Now get counts
    $paidResult = getRow("SELECT COUNT(*) as cnt FROM installment_payments WHERE plan_id = ? AND status = 'paid'", [$plan['id']]);
    $plan['paid_count'] = $paidResult ? intval($paidResult['cnt']) : 0;
    
    $pendingResult = getRow("SELECT COUNT(*) as cnt FROM installment_payments WHERE plan_id = ? AND status = 'pending'", [$plan['id']]);
    $plan['pending_count'] = $pendingResult ? intval($pendingResult['cnt']) : 0;
    
    $overdueCountResult = getRow("SELECT COUNT(*) as cnt FROM installment_payments WHERE plan_id = ? AND status = 'overdue'", [$plan['id']]);
    $plan['overdue_count'] = $overdueCountResult ? intval($overdueCountResult['cnt']) : 0;
    
    $nextDueResult = getRow("SELECT MIN(due_date) as next_due FROM installment_payments WHERE plan_id = ? AND status IN ('pending', 'overdue')", [$plan['id']]);
    $plan['next_due'] = ($nextDueResult && $nextDueResult['next_due']) ? $nextDueResult['next_due'] : null;
}
unset($plan);

// Filter overdue plans if needed
if ($statusFilter === 'overdue') {
    $plans = array_filter($plans, function($p) { return $p['overdue_count'] > 0; });
}

// Get counts for tabs
$activeResult = getRow("SELECT COUNT(*) as cnt FROM installment_plans WHERE status = 'active'");
$activeCount = $activeResult ? intval($activeResult['cnt']) : 0;

$completedResult = getRow("SELECT COUNT(*) as cnt FROM installment_plans WHERE status = 'completed'");
$completedCount = $completedResult ? intval($completedResult['cnt']) : 0;

$customerResult = getRow("SELECT COUNT(*) as cnt FROM installment_plans WHERE type = 'customer' AND status = 'active'");
$customerCount = $customerResult ? intval($customerResult['cnt']) : 0;

$supplierResult = getRow("SELECT COUNT(*) as cnt FROM installment_plans WHERE type = 'supplier' AND status = 'active'");
$supplierCount = $supplierResult ? intval($supplierResult['cnt']) : 0;

// Count overdue
$overdueResult = getRow("
    SELECT COUNT(DISTINCT ip.id) as cnt 
    FROM installment_plans ip
    JOIN installment_payments ipy ON ipy.plan_id = ip.id
    WHERE ip.status = 'active' AND ipy.status = 'overdue'
");
$overdueCount = $overdueResult ? intval($overdueResult['cnt']) : 0;

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<style>
.filter-tabs {
    display: flex;
    gap: 6px;
    margin-bottom: 10px;
    flex-wrap: wrap;
}
.filter-tab {
    padding: 5px 12px;
    border-radius: 15px;
    text-decoration: none;
    color: #4b5563;
    background: #f3f4f6;
    font-weight: 500;
    font-size: 0.8em;
    transition: all 0.3s;
}
.filter-tab:hover {
    background: #e5e7eb;
}
.filter-tab.active {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    color: white;
}
.filter-tab.completed {
    background: #d1fae5;
    color: #059669;
}
.filter-tab.completed.active {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}
.filter-tab.overdue {
    background: #fee2e2;
    color: #dc2626;
}
.filter-tab.overdue.active {
    background: linear-gradient(135deg, #dc2626, #b91c1c);
    color: white;
}
.badge-overdue {
    background: #dc2626;
    color: white;
    padding: 1px 6px;
    border-radius: 8px;
    font-size: 0.75em;
    margin-right: 3px;
}
.badge-completed {
    background: #10b981;
    color: white;
    padding: 1px 6px;
    border-radius: 8px;
    font-size: 0.75em;
    margin-right: 3px;
}
.progress-bar {
    width: 100%;
    height: 6px;
    background: #e5e7eb;
    border-radius: 3px;
    overflow: hidden;
}
.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #10b981, #059669);
    transition: width 0.3s;
}
.plan-card {
    background: white;
    border-radius: 6px;
    padding: 10px;
    margin-bottom: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.1);
    border-right: 3px solid #8b5cf6;
    font-size: 0.85em;
}
.plan-card.customer {
    border-right-color: #3b82f6;
}
.plan-card.supplier {
    border-right-color: #10b981;
}
.plan-card.overdue {
    border-right-color: #dc2626;
    background: #fef2f2;
}
.plan-card.completed {
    border-right-color: #10b981;
    background: #f0fdf4;
}

.container {
    padding: 0px !important;
}
.card {
    margin-bottom: 0px !important;
    border-radius: 6px !important;
}
.card-header {
    padding: 6px 10px !important;
    font-size: 0.9em;
}
.card-body {
    padding: 8px !important;
}
.form-control {
    padding: 5px 8px !important;
    font-size: 0.85em !important;
}
.btn {
    padding: 4px 10px !important;
    font-size: 0.8em !important;
}
</style>

<div class="container">
    <div class="card">
        <div class="card-header d-flex justify-between align-center" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white;">
            <span>📅 الأقساط</span>
            <div style="display: flex; gap: 8px;">
                <?php if (hasPermission('installments.view')): ?>
                <a href="invoices.php" class="btn" style="background: #f59e0b; color: white;">📋 فواتير الأقساط</a>
                <?php endif; ?>
                <?php if (hasPermission('installments.create')): ?>
                <a href="create.php" class="btn" style="background: white; color: #7c3aed;">+ إنشاء قسط جديد</a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card-body">
            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <a href="?status=active" class="filter-tab <?php echo $statusFilter === 'active' && $filter === 'all' ? 'active' : ''; ?>">
                    📊 نشطة (<?php echo $activeCount; ?>)
                </a>
                <a href="?filter=customer&status=active" class="filter-tab <?php echo $filter === 'customer' ? 'active' : ''; ?>">
                    👤 العملاء (<?php echo $customerCount; ?>)
                </a>
                <a href="?filter=supplier&status=active" class="filter-tab <?php echo $filter === 'supplier' ? 'active' : ''; ?>">
                    🚚 الموردين (<?php echo $supplierCount; ?>)
                </a>
                <a href="?status=completed" class="filter-tab completed <?php echo $statusFilter === 'completed' ? 'active' : ''; ?>">
                    ✅ مكتملة (<?php echo $completedCount; ?>)
                </a>
                <a href="?status=overdue" class="filter-tab overdue <?php echo $statusFilter === 'overdue' ? 'active' : ''; ?>">
                    ⏰ متأخرة (<?php echo $overdueCount; ?>)
                </a>
            </div>
            
            <!-- Search -->
            <div class="form-group" style="margin-bottom: 20px;">
                <form method="GET" style="display: flex; gap: 10px;">
                    <input type="hidden" name="status" value="<?php echo $statusFilter; ?>">
                    <input type="hidden" name="filter" value="<?php echo $filter; ?>">
                    <input type="text" name="search" class="form-control" placeholder="🔍 ابحث بالاسم..." 
                           value="<?php echo htmlspecialchars($search); ?>" style="max-width: 300px;">
                    <button type="submit" class="btn btn-primary">بحث</button>
                </form>
            </div>
            
            <!-- Count with Pagination -->
            <div class="search-results-count">
                <span>📊 عدد الأقساط: <strong><?php echo $totalItems; ?></strong></span>
                <div class="pagination" style="display: flex; align-items: center; gap: 8px;">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($statusFilter); ?>&filter=<?php echo urlencode($filter); ?>&search=<?php echo urlencode($search); ?>" class="btn btn-secondary btn-sm">← السابق</a>
                    <?php endif; ?>
                    <span class="badge badge-info">صفحة <?php echo $page; ?> من <?php echo max(1, $totalPages); ?></span>
                    <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($statusFilter); ?>&filter=<?php echo urlencode($filter); ?>&search=<?php echo urlencode($search); ?>" class="btn btn-secondary btn-sm">التالي →</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (empty($plans)): ?>
            <div class="alert alert-info">لا توجد خطط تقسيط</div>
            <?php else: ?>
            
            <?php foreach ($plans as $plan): 
                $progress = $plan['total_amount'] > 0 ? (($plan['paid_amount'] / $plan['total_amount']) * 100) : 0;
                $hasOverdue = $plan['overdue_count'] > 0;
                $isCompleted = $plan['status'] === 'completed';
            ?>
            <div class="plan-card <?php echo $plan['type']; ?> <?php echo $hasOverdue ? 'overdue' : ''; ?> <?php echo $isCompleted ? 'completed' : ''; ?>">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                    <div>
                        <span style="font-size: 0.85em; color: <?php echo $plan['type'] === 'customer' ? '#3b82f6' : '#10b981'; ?>;">
                            <?php echo $plan['type'] === 'customer' ? '👤 عميل' : '🚚 مورد'; ?>
                        </span>
                        <?php if ($isCompleted): ?>
                        <span class="badge-completed">✓ مكتمل</span>
                        <?php elseif ($hasOverdue): ?>
                        <span class="badge-overdue">⏰ متأخر</span>
                        <?php endif; ?>
                        <h3 style="margin: 5px 0;"><?php echo $plan['entity_name']; ?></h3>
                        <small style="color: #6b7280;">
                            بدء: <?php echo date('Y/m/d', strtotime($plan['start_date'])); ?>
                            <?php if ($plan['next_due']): ?>
                            | القادم: <?php echo date('Y/m/d', strtotime($plan['next_due'])); ?>
                            <?php endif; ?>
                        </small>
                    </div>
                    <div style="text-align: left;">
                        <div style="font-size: 1.5em; font-weight: bold; color: <?php echo $isCompleted ? '#10b981' : '#7c3aed'; ?>;">
                            <?php echo number_format($plan['remaining_amount'], 2); ?>
                        </div>
                        <small>متبقي من <?php echo number_format($plan['total_amount'], 2); ?></small>
                    </div>
                </div>
                
                <!-- Progress -->
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $progress; ?>%;"></div>
                </div>
                <div style="display: flex; justify-content: space-between; margin-top: 8px; font-size: 0.85em; color: #6b7280;">
                    <span>مدفوع: <?php echo $plan['paid_count']; ?> قسط</span>
                    <span>متبقي: <?php echo $plan['pending_count'] + $plan['overdue_count']; ?> قسط</span>
                </div>
                
                <!-- Actions -->
                <div style="margin-top: 15px; display: flex; gap: 10px;">
                    <a href="view.php?id=<?php echo $plan['id']; ?>" class="btn btn-primary btn-sm">📋 التفاصيل</a>
                    <?php if ($plan['status'] === 'active' && hasPermission('installments.pay')): ?>
                    <a href="pay.php?plan_id=<?php echo $plan['id']; ?>" class="btn btn-success btn-sm">💵 دفع</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
