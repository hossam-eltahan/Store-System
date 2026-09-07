<?php
/**
 * Expenses Management Dashboard
 * نظام إدارة محل أجهزة منزلية - إدارة المصروفات والتكاليف التشغيلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
require_once '../../config/expenses.php';

requireAnyPermission(['expenses.view', 'expenses.add', 'expenses.edit']);

$pageTitle = 'المصروفات والتكاليف التشغيلية';

// ----------------------------------------------------
// Handle Form Submissions (Add, Edit, Delete)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Add new expense
    if ($action === 'add') {
        requirePermission('expenses.add');

        $categoryId = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
        $title = sanitize(trim($_POST['title'] ?? ''));
        $amount = floatval($_POST['amount'] ?? 0);
        $expenseDate = sanitize(trim($_POST['expense_date'] ?? date('Y-m-d')));
        $paymentMethod = sanitize(trim($_POST['payment_method'] ?? 'نقدي'));
        $paidTo = sanitize(trim($_POST['paid_to'] ?? ''));
        $receiptNumber = sanitize(trim($_POST['receipt_number'] ?? ''));
        $notes = sanitize(trim($_POST['notes'] ?? ''));

        if (empty($title)) {
            setError('يرجى كتابة بيان أو اسم المصروف.');
        } elseif ($amount <= 0) {
            setError('يرجى إدخال مبلغ صحيح أكبر من الصفر.');
        } elseif (empty($expenseDate)) {
            setError('يرجى تحديد تاريخ المصروف.');
        } else {
            $expenseNumber = generateExpenseNumber();
            $userId = getCurrentUserId();
            $handledBy = getCurrentUserName();

            $insertId = insert("
                INSERT INTO expenses (expense_number, category_id, title, amount, expense_date, payment_method, paid_to, receipt_number, notes, user_id, handled_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [$expenseNumber, $categoryId, $title, $amount, $expenseDate, $paymentMethod, $paidTo, $receiptNumber, $notes, $userId, $handledBy]);

            if ($insertId) {
                logActivity('إضافة مصروف', "تم تسجيل مصروف رقم $expenseNumber ($title) بمبلغ " . formatCurrency($amount));
                setSuccess("تم تسجيل المصروف بنجاح برقم: $expenseNumber");
            } else {
                setError('حدث خطأ أثناء حفظ المصروف في قاعدة البيانات.');
            }
        }
        redirect('index.php');
    }

    // 2. Edit existing expense
    if ($action === 'edit') {
        requirePermission('expenses.edit');

        $expenseId = intval($_POST['expense_id'] ?? 0);
        $categoryId = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
        $title = sanitize(trim($_POST['title'] ?? ''));
        $amount = floatval($_POST['amount'] ?? 0);
        $expenseDate = sanitize(trim($_POST['expense_date'] ?? date('Y-m-d')));
        $paymentMethod = sanitize(trim($_POST['payment_method'] ?? 'نقدي'));
        $paidTo = sanitize(trim($_POST['paid_to'] ?? ''));
        $receiptNumber = sanitize(trim($_POST['receipt_number'] ?? ''));
        $notes = sanitize(trim($_POST['notes'] ?? ''));

        $existing = getRow("SELECT * FROM expenses WHERE id = ?", [$expenseId]);
        if (!$existing) {
            setError('المصروف المطلوب غير موجود.');
        } elseif (empty($title)) {
            setError('يرجى كتابة بيان أو اسم المصروف.');
        } elseif ($amount <= 0) {
            setError('يرجى إدخال مبلغ صحيح أكبر من الصفر.');
        } else {
            execute("
                UPDATE expenses 
                SET category_id = ?, title = ?, amount = ?, expense_date = ?, payment_method = ?, paid_to = ?, receipt_number = ?, notes = ?
                WHERE id = ?
            ", [$categoryId, $title, $amount, $expenseDate, $paymentMethod, $paidTo, $receiptNumber, $notes, $expenseId]);

            logActivity('تعديل مصروف', "تم تعديل المصروف رقم {$existing['expense_number']} ($title) بمبلغ " . formatCurrency($amount));
            setSuccess('تم تحديث بيانات المصروف بنجاح.');
        }
        redirect('index.php');
    }

    // 3. Delete expense
    if ($action === 'delete') {
        requirePermission('expenses.delete');

        $expenseId = intval($_POST['expense_id'] ?? 0);
        $existing = getRow("SELECT * FROM expenses WHERE id = ?", [$expenseId]);

        if ($existing) {
            execute("DELETE FROM expenses WHERE id = ?", [$expenseId]);
            logActivity('حذف مصروف', "تم حذف المصروف رقم {$existing['expense_number']} بمبلغ " . formatCurrency($existing['amount']));
            setSuccess('تم حذف المصروف بنجاح.');
        } else {
            setError('المصروف المطلوب غير موجود أو تم حذفه مسبقاً.');
        }
        redirect('index.php');
    }
}

// ----------------------------------------------------
// Filtering & Search
// ----------------------------------------------------
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$filterCategory = !empty($_GET['category_id']) ? intval($_GET['category_id']) : '';
$filterPayMethod = sanitize(trim($_GET['payment_method'] ?? ''));
$search = sanitize(trim($_GET['search'] ?? ''));

$whereConditions = ["1=1"];
$params = [];

if ($dateFrom && $dateTo) {
    $whereConditions[] = "e.expense_date BETWEEN ? AND ?";
    $params[] = $dateFrom;
    $params[] = $dateTo;
} elseif ($dateFrom) {
    $whereConditions[] = "e.expense_date >= ?";
    $params[] = $dateFrom;
} elseif ($dateTo) {
    $whereConditions[] = "e.expense_date <= ?";
    $params[] = $dateTo;
}

if ($filterCategory) {
    $whereConditions[] = "e.category_id = ?";
    $params[] = $filterCategory;
}

if ($filterPayMethod) {
    $whereConditions[] = "e.payment_method = ?";
    $params[] = $filterPayMethod;
}

if ($search !== '') {
    $whereConditions[] = "(e.expense_number LIKE ? OR e.title LIKE ? OR e.paid_to LIKE ? OR e.receipt_number LIKE ? OR e.notes LIKE ?)";
    $sParam = "%{$search}%";
    $params[] = $sParam;
    $params[] = $sParam;
    $params[] = $sParam;
    $params[] = $sParam;
    $params[] = $sParam;
}

$whereSql = implode(" AND ", $whereConditions);

// Pagination
$perPage = 15;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$countRow = getRow("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total_sum FROM expenses e WHERE {$whereSql}", $params);
$totalItems = intval($countRow['count'] ?? 0);
$filteredTotalAmount = floatval($countRow['total_sum'] ?? 0);
$totalPages = max(1, ceil($totalItems / $perPage));

$expensesList = getRows("
    SELECT e.*, c.name as category_name, c.icon as category_icon
    FROM expenses e
    LEFT JOIN expense_categories c ON e.category_id = c.id
    WHERE {$whereSql}
    ORDER BY e.expense_date DESC, e.id DESC
    LIMIT {$perPage} OFFSET {$offset}
", $params);

// Quick Statistics
$today = date('Y-m-d');
$firstDayOfMonth = date('Y-m-01');
$lastDayOfMonth = date('Y-m-t');

$todayStats = getRow("SELECT COALESCE(SUM(amount), 0) as total, COUNT(*) as cnt FROM expenses WHERE expense_date = ?", [$today]);
$monthStats = getRow("SELECT COALESCE(SUM(amount), 0) as total, COUNT(*) as cnt FROM expenses WHERE expense_date BETWEEN ? AND ?", [$firstDayOfMonth, $lastDayOfMonth]);

$todayTotal = floatval($todayStats['total'] ?? 0);
$monthTotal = floatval($monthStats['total'] ?? 0);

// Load categories list for select
$allCategories = getExpenseCategories(false);
$activeCategories = array_filter($allCategories, fn($c) => $c['is_active'] == 1);

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<style>
/* Expenses Custom Modern Styling */
.kpi-card {
    background: #ffffff;
    border-radius: 12px;
    padding: 16px 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 16px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border-right: 4px solid #3b82f6;
}
.kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 14px rgba(0,0,0,0.08);
}
.kpi-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    background: #eff6ff;
    color: #3b82f6;
    flex-shrink: 0;
}
.kpi-card.danger { border-right-color: #ef4444; }
.kpi-card.danger .kpi-icon { background: #fef2f2; color: #ef4444; }
.kpi-card.warning { border-right-color: #f59e0b; }
.kpi-card.warning .kpi-icon { background: #fffbeb; color: #f59e0b; }
.kpi-card.success { border-right-color: #10b981; }
.kpi-card.success .kpi-icon { background: #ecfdf5; color: #10b981; }

.kpi-content { flex: 1; min-width: 0; }
.kpi-label { font-size: 0.85rem; color: #64748b; margin-bottom: 4px; font-weight: 600; }
.kpi-value { font-size: 1.3rem; font-weight: 750; color: #0f172a; white-space: nowrap; }

/* Filter Pill Buttons */
.quick-date-btn {
    padding: 4px 10px;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    background: #f8fafc;
    font-size: 12px;
    cursor: pointer;
    text-decoration: none;
    color: #475569;
    transition: all 0.15s ease;
}
.quick-date-btn:hover {
    background: #3b82f6;
    color: #ffffff;
    border-color: #3b82f6;
}

/* Modal Overlay & Box */
.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.6);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 16px;
    backdrop-filter: blur(4px);
}
.modal-box {
    background: #ffffff;
    border-radius: 14px;
    width: 100%;
    max-width: 580px;
    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2), 0 10px 10px -5px rgba(0,0,0,0.04);
    overflow: hidden;
    animation: modalFadeIn 0.25s ease-out;
}
@keyframes modalFadeIn {
    from { opacity: 0; transform: scale(0.96) translateY(-10px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}
.modal-header {
    padding: 16px 22px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modal-header h3 { margin: 0; font-size: 1.15rem; color: #0f172a; font-weight: 700; }
.modal-close-btn {
    background: none;
    border: none;
    font-size: 24px;
    line-height: 1;
    color: #94a3b8;
    cursor: pointer;
    transition: color 0.15s ease;
}
.modal-close-btn:hover { color: #ef4444; }
.modal-body { padding: 22px; max-height: calc(85vh - 130px); overflow-y: auto; }
.modal-footer {
    padding: 14px 22px;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}
</style>

<div class="container">
    <!-- Header with Action Buttons -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
        <div>
            <h1 style="font-size: 1.6rem; margin: 0 0 4px 0; color: #0f172a;">💸 المصروفات والتكاليف التشغيلية</h1>
            <p style="margin: 0; color: #64748b; font-size: 0.9rem;">تسجيل ومتابعة فواتير الكهرباء والمياه والإيجار والمرتبات وخصمها التلقائي من الأرباح</p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <?php if (hasPermission('expenses.categories')): ?>
            <a href="categories.php" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px;">
                <span>🏷️</span> إدارة بنود المصروفات
            </a>
            <?php endif; ?>

            <?php if (hasPermission('expenses.add')): ?>
            <button type="button" class="btn btn-primary" onclick="openAddModal()" style="display: inline-flex; align-items: center; gap: 6px;">
                <span>➕</span> تسجيل مصروف جديد
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick KPIs -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 20px;">
        <div class="kpi-card danger">
            <div class="kpi-icon">📅</div>
            <div class="kpi-content">
                <div class="kpi-label">مصروفات اليوم (<?php echo date('d/m'); ?>)</div>
                <div class="kpi-value"><?php echo formatCurrency($todayTotal); ?></div>
            </div>
        </div>

        <div class="kpi-card warning">
            <div class="kpi-icon">📊</div>
            <div class="kpi-content">
                <div class="kpi-label">مصروفات شهر <?php echo date('m/Y'); ?></div>
                <div class="kpi-value"><?php echo formatCurrency($monthTotal); ?></div>
            </div>
        </div>

        <div class="kpi-card info">
            <div class="kpi-icon">🔍</div>
            <div class="kpi-content">
                <div class="kpi-label">إجمالي المعروض بالفترة</div>
                <div class="kpi-value"><?php echo formatCurrency($filteredTotalAmount); ?></div>
            </div>
        </div>

        <div class="kpi-card success">
            <div class="kpi-icon">🧾</div>
            <div class="kpi-content">
                <div class="kpi-label">عدد السندات بالفترة</div>
                <div class="kpi-value"><?php echo $totalItems; ?> سند</div>
            </div>
        </div>
    </div>

    <!-- Filters & Search Card -->
    <div class="card" style="margin-bottom: 20px;">
        <div class="card-body" style="padding: 16px 20px;">
            <form method="GET" id="filterForm">
                <div style="display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-end;">
                    <div style="flex: 2; min-width: 220px;">
                        <label class="form-label" style="font-size: 0.85rem; font-weight: 600;">🔍 بحث بالبيان / المستلم / رقم السند</label>
                        <input type="text" name="search" class="form-control" placeholder="اكتب للبحث..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <div style="flex: 1; min-width: 160px;">
                        <label class="form-label" style="font-size: 0.85rem; font-weight: 600;">🏷️ بند المصروف</label>
                        <select name="category_id" class="form-control">
                            <option value="">كل البنود</option>
                            <?php foreach ($allCategories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $filterCategory == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['icon'] . ' ' . $cat['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="flex: 1; min-width: 140px;">
                        <label class="form-label" style="font-size: 0.85rem; font-weight: 600;">💳 طريقة الدفع</label>
                        <select name="payment_method" class="form-control">
                            <option value="">كل الطرق</option>
                            <option value="نقدي" <?php echo $filterPayMethod === 'نقدي' ? 'selected' : ''; ?>>نقدي (كاش)</option>
                            <option value="تحويل بنكي" <?php echo $filterPayMethod === 'تحويل بنكي' ? 'selected' : ''; ?>>تحويل بنكي</option>
                            <option value="فودافون كاش" <?php echo $filterPayMethod === 'فودافون كاش' ? 'selected' : ''; ?>>فودافون كاش / محفظة</option>
                            <option value="شيك" <?php echo $filterPayMethod === 'شيك' ? 'selected' : ''; ?>>شيك</option>
                            <option value="أخرى" <?php echo $filterPayMethod === 'أخرى' ? 'selected' : ''; ?>>أخرى</option>
                        </select>
                    </div>

                    <div style="min-width: 135px;">
                        <label class="form-label" style="font-size: 0.85rem; font-weight: 600;">من تاريخ</label>
                        <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo htmlspecialchars($dateFrom); ?>">
                    </div>

                    <div style="min-width: 135px;">
                        <label class="form-label" style="font-size: 0.85rem; font-weight: 600;">إلى تاريخ</label>
                        <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo htmlspecialchars($dateTo); ?>">
                    </div>

                    <div style="display: flex; gap: 8px;">
                        <button type="submit" class="btn btn-primary" style="padding: 8px 16px;">
                            <span>تصفية</span>
                        </button>
                        <a href="index.php" class="btn btn-secondary" style="padding: 8px 14px;" title="إعادة تعيين">
                            <span>إلغاء</span>
                        </a>
                    </div>
                </div>

                <!-- Quick Date Filters -->
                <div style="display: flex; gap: 8px; margin-top: 12px; align-items: center; flex-wrap: wrap;">
                    <span style="font-size: 0.8rem; color: #64748b; font-weight: 600;">فترات سريعة:</span>
                    <button type="button" class="quick-date-btn" onclick="setQuickDate('today')">اليوم</button>
                    <button type="button" class="quick-date-btn" onclick="setQuickDate('yesterday')">أمس</button>
                    <button type="button" class="quick-date-btn" onclick="setQuickDate('this_month')">هذا الشهر</button>
                    <button type="button" class="quick-date-btn" onclick="setQuickDate('last_month')">الشهر الماضي</button>
                    <button type="button" class="quick-date-btn" onclick="setQuickDate('all')">كل الأوقات</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Table Card -->
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div style="font-weight: 700; font-size: 1.05rem;">
                📋 جدول سندات المصروفات 
                <span style="font-size: 0.85rem; font-weight: normal; color: #64748b; margin-right: 8px;">
                    (عرض <?php echo count($expensesList); ?> من أصل <?php echo $totalItems; ?> سند)
                </span>
            </div>
            <div>
                <a href="../reports/index.php?type=profit<?php echo ($dateFrom && $dateTo) ? "&date_from=$dateFrom&date_to=$dateTo" : ""; ?>" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
                    <span>📈</span> تقرير الأرباح وصافي الدخل
                </a>
            </div>
        </div>

        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table" style="margin-bottom: 0;">
                    <thead>
                        <tr>
                            <th style="width: 120px;">رقم السند</th>
                            <th style="width: 110px;">التاريخ</th>
                            <th>البند والتصنيف</th>
                            <th>بيان المصروف</th>
                            <th>المبلغ</th>
                            <th>طريقة الدفع</th>
                            <th>المدفوع له</th>
                            <th>المسؤول</th>
                            <th style="width: 130px; text-align: center;">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($expensesList)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px; color: #64748b;">
                                <div style="font-size: 32px; margin-bottom: 8px;">💸</div>
                                <div style="font-size: 1.05rem; font-weight: 600;">لا توجد مصروفات مسجلة تطابق معايير البحث</div>
                                <p style="font-size: 0.85rem; margin-top: 4px;">يمكنك البدء بالضغط على زر "تسجيل مصروف جديد" في الأعلى</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($expensesList as $exp): ?>
                        <tr>
                            <td>
                                <strong style="color: #2563eb; font-family: monospace; font-size: 0.95rem;">
                                    <?php echo htmlspecialchars($exp['expense_number']); ?>
                                </strong>
                            </td>
                            <td>
                                <span style="font-size: 0.88rem; color: #475569;">
                                    <?php echo date('Y-m-d', strtotime($exp['expense_date'])); ?>
                                </span>
                            </td>
                            <td>
                                <span style="display: inline-flex; align-items: center; gap: 5px; background: #f1f5f9; padding: 3px 10px; border-radius: 16px; font-size: 0.85rem; font-weight: 600;">
                                    <span><?php echo $exp['category_icon'] ?: '💸'; ?></span>
                                    <span><?php echo htmlspecialchars($exp['category_name'] ?: 'مصروف عام'); ?></span>
                                </span>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: #0f172a;"><?php echo htmlspecialchars($exp['title']); ?></div>
                                <?php if (!empty($exp['notes'])): ?>
                                <div style="font-size: 0.78rem; color: #64748b; margin-top: 2px;">
                                    📝 <?php echo htmlspecialchars($exp['notes']); ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color: #dc2626; font-size: 1rem;">
                                    <?php echo formatCurrency($exp['amount']); ?>
                                </strong>
                            </td>
                            <td>
                                <span style="font-size: 0.85rem; color: #334155;">
                                    <?php echo htmlspecialchars($exp['payment_method']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($exp['paid_to'])): ?>
                                <span style="font-size: 0.88rem; color: #0f172a;">
                                    <?php echo htmlspecialchars($exp['paid_to']); ?>
                                </span>
                                <?php else: ?>
                                <span style="color: #94a3b8; font-size: 0.85rem;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="font-size: 0.82rem; color: #64748b;">
                                    <?php echo htmlspecialchars($exp['handled_by'] ?: 'النظام'); ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: inline-flex; gap: 6px; align-items: center;">
                                    <!-- Print Voucher -->
                                    <a href="print.php?id=<?php echo $exp['id']; ?>" target="_blank" class="btn btn-secondary btn-sm" title="طباعة سند صرف" style="padding: 4px 8px;">
                                        <span>🖨️</span>
                                    </a>

                                    <!-- Edit -->
                                    <?php if (hasPermission('expenses.edit')): ?>
                                    <button type="button" class="btn btn-warning btn-sm" title="تعديل المصروف" style="padding: 4px 8px;"
                                        onclick='openEditModal(<?php echo json_encode($exp, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>)'>
                                        <span>✏️</span>
                                    </button>
                                    <?php endif; ?>

                                    <!-- Delete -->
                                    <?php if (hasPermission('expenses.delete')): ?>
                                    <button type="button" class="btn btn-danger btn-sm" title="حذف المصروف" style="padding: 4px 8px;"
                                        onclick="confirmDelete(<?php echo $exp['id']; ?>, '<?php echo htmlspecialchars(addslashes($exp['expense_number'] . ' - ' . $exp['title'])); ?>')">
                                        <span>🗑️</span>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 20px; border-top: 1px solid #e2e8f0; flex-wrap: wrap; gap: 10px;">
                <div style="font-size: 0.85rem; color: #64748b;">
                    صفحة <strong><?php echo $page; ?></strong> من <strong><?php echo $totalPages; ?></strong>
                </div>
                <div style="display: flex; gap: 6px;">
                    <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="btn btn-secondary btn-sm">← السابق</a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                       class="btn btn-sm <?php echo $i == $page ? 'btn-primary' : 'btn-secondary'; ?>">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="btn btn-secondary btn-sm">التالي →</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- Modal: Add / Edit Expense                                -->
<!-- ======================================================== -->
<div id="expenseModal" class="modal-overlay">
    <div class="modal-box">
        <form method="POST" id="expenseForm">
            <input type="hidden" name="action" id="modalAction" value="add">
            <input type="hidden" name="expense_id" id="modalExpenseId" value="">

            <div class="modal-header">
                <h3 id="modalTitle">➕ تسجيل مصروف جديد</h3>
                <button type="button" class="modal-close-btn" onclick="closeExpenseModal()">&times;</button>
            </div>

            <div class="modal-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label required">التاريخ</label>
                        <input type="date" name="expense_date" id="modalExpenseDate" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label required">بند / تصنيف المصروف</label>
                        <select name="category_id" id="modalCategoryId" class="form-control" required>
                            <option value="">اختر التصنيف...</option>
                            <?php foreach ($activeCategories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>">
                                <?php echo htmlspecialchars($cat['icon'] . ' ' . $cat['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label required">بيان / اسم المصروف</label>
                    <input type="text" name="title" id="modalTitleInput" class="form-control" required placeholder="مثال: فاتورة كهرباء شهر 9، إيجار المحل، أدوات نظافة...">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label required">المبلغ المنصرف (ج.م)</label>
                        <input type="number" step="0.01" min="0.01" name="amount" id="modalAmount" class="form-control" required placeholder="0.00" style="font-weight: 700; color: #dc2626;">
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label required">طريقة الدفع</label>
                        <select name="payment_method" id="modalPaymentMethod" class="form-control" required>
                            <option value="نقدي">نقدي (كاش الخزينة)</option>
                            <option value="تحويل بنكي">تحويل بنكي</option>
                            <option value="فودافون كاش">فودافون كاش / محفظة</option>
                            <option value="شيك">شيك</option>
                            <option value="أخرى">أخرى</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">الجهة أو الشخص المدفوع له</label>
                        <input type="text" name="paid_to" id="modalPaidTo" class="form-control" placeholder="اسم المستلم أو الشركة...">
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">رقم الإيصال / الفاتورة الورقية (إن وجد)</label>
                        <input type="text" name="receipt_number" id="modalReceiptNumber" class="form-control" placeholder="رقم الإيصال الخارجي للتوثيق...">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">ملاحظات وتفاصيل إضافية</label>
                    <textarea name="notes" id="modalNotes" class="form-control" rows="2" placeholder="أي تفاصيل أو ملاحظات محاسبية أخرى..."></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeExpenseModal()">إلغاء</button>
                <button type="submit" class="btn btn-primary" id="modalSubmitBtn">حفظ المصروف</button>
            </div>
        </form>
    </div>
</div>

<!-- ======================================================== -->
<!-- Modal: Delete Confirmation                              -->
<!-- ======================================================== -->
<div id="deleteModal" class="modal-overlay">
    <div class="modal-box" style="max-width: 440px;">
        <form method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="expense_id" id="deleteExpenseId" value="">

            <div class="modal-header">
                <h3 style="color: #ef4444;">⚠️ تأكيد حذف المصروف</h3>
                <button type="button" class="modal-close-btn" onclick="closeDeleteModal()">&times;</button>
            </div>

            <div class="modal-body" style="text-align: center; padding: 25px 20px;">
                <div style="font-size: 40px; margin-bottom: 10px;">🗑️</div>
                <p style="font-size: 1rem; color: #1e293b; margin-bottom: 8px;">هل أنت متأكد من رغبتك في حذف هذا المصروف؟</p>
                <div id="deleteExpenseLabel" style="font-weight: 700; color: #ef4444; background: #fef2f2; padding: 8px 14px; border-radius: 8px; display: inline-block;"></div>
                <p style="font-size: 0.8rem; color: #64748b; margin-top: 12px; margin-bottom: 0;">تنبيه: سيتم إعادة احتساب الأرباح وصافي الخزينة فور إتمام الحذف.</p>
            </div>

            <div class="modal-footer" style="justify-content: center;">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">تراجع</button>
                <button type="submit" class="btn btn-danger">تأكيد الحذف نهائياً</button>
            </div>
        </form>
    </div>
</div>

<script>
// Modal Controls
const expenseModal = document.getElementById('expenseModal');
const deleteModal = document.getElementById('deleteModal');

function openAddModal() {
    document.getElementById('modalAction').value = 'add';
    document.getElementById('modalTitle').innerHTML = '➕ تسجيل مصروف جديد';
    document.getElementById('modalSubmitBtn').innerHTML = 'حفظ المصروف';
    document.getElementById('modalExpenseId').value = '';
    document.getElementById('modalExpenseDate').value = '<?php echo date('Y-m-d'); ?>';
    document.getElementById('modalCategoryId').value = '';
    document.getElementById('modalTitleInput').value = '';
    document.getElementById('modalAmount').value = '';
    document.getElementById('modalPaymentMethod').value = 'نقدي';
    document.getElementById('modalPaidTo').value = '';
    document.getElementById('modalReceiptNumber').value = '';
    document.getElementById('modalNotes').value = '';

    expenseModal.style.display = 'flex';
    document.getElementById('modalTitleInput').focus();
}

function openEditModal(exp) {
    document.getElementById('modalAction').value = 'edit';
    document.getElementById('modalTitle').innerHTML = '✏️ تعديل المصروف: ' + exp.expense_number;
    document.getElementById('modalSubmitBtn').innerHTML = 'تحديث البيانات';
    document.getElementById('modalExpenseId').value = exp.id;
    document.getElementById('modalExpenseDate').value = exp.expense_date;
    document.getElementById('modalCategoryId').value = exp.category_id || '';
    document.getElementById('modalTitleInput').value = exp.title;
    document.getElementById('modalAmount').value = exp.amount;
    document.getElementById('modalPaymentMethod').value = exp.payment_method || 'نقدي';
    document.getElementById('modalPaidTo').value = exp.paid_to || '';
    document.getElementById('modalReceiptNumber').value = exp.receipt_number || '';
    document.getElementById('modalNotes').value = exp.notes || '';

    expenseModal.style.display = 'flex';
}

function closeExpenseModal() {
    expenseModal.style.display = 'none';
}

function confirmDelete(id, label) {
    document.getElementById('deleteExpenseId').value = id;
    document.getElementById('deleteExpenseLabel').textContent = label;
    deleteModal.style.display = 'flex';
}

function closeDeleteModal() {
    deleteModal.style.display = 'none';
}

// Close on click outside
window.onclick = function(e) {
    if (e.target === expenseModal) closeExpenseModal();
    if (e.target === deleteModal) closeDeleteModal();
};

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeExpenseModal();
        closeDeleteModal();
    }
});

// Quick Date Presets
function setQuickDate(preset) {
    const fromInput = document.getElementById('date_from');
    const toInput = document.getElementById('date_to');
    const today = new Date();
    
    function formatDate(d) {
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    if (preset === 'today') {
        const str = formatDate(today);
        fromInput.value = str;
        toInput.value = str;
    } else if (preset === 'yesterday') {
        const y = new Date(today);
        y.setDate(y.getDate() - 1);
        const str = formatDate(y);
        fromInput.value = str;
        toInput.value = str;
    } else if (preset === 'this_month') {
        const first = new Date(today.getFullYear(), today.getMonth(), 1);
        const last = new Date(today.getFullYear(), today.getMonth() + 1, 0);
        fromInput.value = formatDate(first);
        toInput.value = formatDate(last);
    } else if (preset === 'last_month') {
        const first = new Date(today.getFullYear(), today.getMonth() - 1, 1);
        const last = new Date(today.getFullYear(), today.getMonth(), 0);
        fromInput.value = formatDate(first);
        toInput.value = formatDate(last);
    } else if (preset === 'all') {
        fromInput.value = '';
        toInput.value = '';
    }

    document.getElementById('filterForm').submit();
}
</script>

<?php include '../../includes/footer.php'; ?>
