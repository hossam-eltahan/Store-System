<?php
/**
 * Cafe Expenses Management (إدارة مصروفات وتشغيل الكافيه)
 * نظام كافيه عروسة النيل
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requirePermission('expenses.view');

$pageTitle = 'المصروفات والتكاليف التشغيلية';

// Check currently active shift
$activeShift = getRow("SELECT id, shift_number FROM shifts WHERE status = 'open' ORDER BY id DESC LIMIT 1");
$activeShiftId = $activeShift ? (int)$activeShift['id'] : null;

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        requirePermission('expenses.add');

        $categoryId = (int)($_POST['category_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        $expenseDate = sanitize(trim($_POST['date'] ?? date('Y-m-d')));
        $description = sanitize(trim($_POST['description'] ?? ''));
        $paidTo = sanitize(trim($_POST['paid_to'] ?? ''));
        $deductFromShift = isset($_POST['deduct_from_shift']) && $activeShiftId;

        if ($categoryId <= 0) {
            setError('يرجى اختيار بند أو تصنيف المصروف.');
        } elseif ($amount <= 0) {
            setError('يرجى إدخال مبلغ صحيح أكبر من الصفر.');
        } elseif (empty($description)) {
            setError('يرجى كتابة بيان وتفاصيل المصروف.');
        } else {
            $userId = getCurrentUserId();
            $shiftId = $deductFromShift ? $activeShiftId : null;

            $insertId = insert("
                INSERT INTO expenses (category_id, amount, date, description, paid_to, shift_id, user_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ", [$categoryId, $amount, $expenseDate, $description, $paidTo, $shiftId, $userId]);

            if ($insertId) {
                // If linked to active shift, record cash movement and update shift total
                if ($deductFromShift && $activeShiftId) {
                    insert("
                        INSERT INTO cash_movements (shift_id, movement_type, amount, payment_method, description, reference_type, reference_id, created_by)
                        VALUES (?, 'expense', ?, 'cash', ?, 'expense', ?, ?)
                    ", [$activeShiftId, $amount, "مصروف: $description ($paidTo)", $insertId, $userId]);

                    execute("UPDATE shifts SET total_expenses = total_expenses + ? WHERE id = ?", [$amount, $activeShiftId]);
                }

                logActivity('تسجيل مصروف', "تم تسجيل مصروف بمبلغ " . formatCurrency($amount) . " ($description)");
                setSuccess("تم تسجيل المصروف بنجاح بمبلغ: " . formatCurrency($amount));
            } else {
                setError('حدث خطأ أثناء حفظ المصروف.');
            }
        }
        redirect('index.php');
    }

    if ($action === 'edit') {
        requirePermission('expenses.edit');
        $id = (int)($_POST['expense_id'] ?? 0);
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        $expenseDate = sanitize(trim($_POST['date'] ?? date('Y-m-d')));
        $description = sanitize(trim($_POST['description'] ?? ''));
        $paidTo = sanitize(trim($_POST['paid_to'] ?? ''));

        if ($categoryId <= 0 || $amount <= 0 || empty($description)) {
            setError('يرجى التأكد من ملء جميع الحقول المطلوبة بشكل صحيح.');
        } else {
            execute("
                UPDATE expenses 
                SET category_id = ?, amount = ?, date = ?, description = ?, paid_to = ?
                WHERE id = ?
            ", [$categoryId, $amount, $expenseDate, $description, $paidTo, $id]);

            logActivity('تعديل مصروف', "تم تعديل مصروف رقم #$id");
            setSuccess('تم تحديث بيانات المصروف بنجاح');
        }
        redirect('index.php');
    }

    if ($action === 'delete') {
        requirePermission('expenses.delete');
        $id = (int)($_POST['expense_id'] ?? 0);
        $exp = getRow("SELECT * FROM expenses WHERE id = ?", [$id]);

        if ($exp) {
            // If linked to shift, adjust cash
            if ($exp['shift_id']) {
                execute("UPDATE shifts SET total_expenses = GREATEST(0, total_expenses - ?) WHERE id = ?", [$exp['amount'], $exp['shift_id']]);
                execute("DELETE FROM cash_movements WHERE reference_type = 'expense' AND reference_id = ?", [$id]);
            }

            execute("DELETE FROM expenses WHERE id = ?", [$id]);
            logActivity('حذف مصروف', "تم حذف مصروف رقم #$id بمبلغ " . formatCurrency($exp['amount']));
            setSuccess('تم حذف المصروف بنجاح');
        }
        redirect('index.php');
    }
}

// Fetch categories
$categories = getRows("SELECT * FROM expense_categories WHERE is_active = 1 ORDER BY id ASC");

// Filters & Search
$search = trim($_GET['search'] ?? '');
$filterCategory = (int)($_GET['category_id'] ?? 0);
$filterDateFrom = $_GET['date_from'] ?? date('Y-m-01');
$filterDateTo = $_GET['date_to'] ?? date('Y-m-d');

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

$where = ["e.date BETWEEN ? AND ?"];
$params = [$filterDateFrom, $filterDateTo];

if ($search !== '') {
    $where[] = "(" . search_like_expr('e.description') . " LIKE ? OR " . search_like_expr('e.paid_to') . " LIKE ?)";
    $params[] = '%' . mb_search_normalize($search) . '%';
    $params[] = '%' . mb_search_normalize($search) . '%';
}

if ($filterCategory > 0) {
    $where[] = "e.category_id = ?";
    $params[] = $filterCategory;
}

$whereClause = 'WHERE ' . implode(' AND ', $where);

// Total count for pagination (all filtered rows)
$totalExpenses = (int) (getRow("SELECT COUNT(*) as cnt FROM expenses e $whereClause", $params)['cnt'] ?? 0);
$totalPages = max(1, (int) ceil($totalExpenses / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// Expenses query (current page only)
$expenses = getRows("
    SELECT e.*, c.name as category_name, u.full_name as user_name, s.shift_number
    FROM expenses e
    JOIN expense_categories c ON c.id = e.category_id
    LEFT JOIN users u ON u.id = e.user_id
    LEFT JOIN shifts s ON s.id = e.shift_id
    $whereClause
    ORDER BY e.date DESC, e.id DESC
    LIMIT $perPage OFFSET $offset
", $params);

// Stats
$todayExpenses = getRow("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE date = CURDATE()")['total'] ?? 0;
$shiftExpenses = $activeShiftId ? (getRow("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE shift_id = ?", [$activeShiftId])['total'] ?? 0) : 0;
$monthExpenses = getRow("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())")['total'] ?? 0;
// Sum over the whole filtered set (independent of pagination) for accurate "total of period"
$filteredTotal = (float) (getRow("SELECT COALESCE(SUM(amount), 0) as total FROM expenses e $whereClause", $params)['total'] ?? 0);

// Pagination base query (preserve filters across page links)
$paginationBaseQuery = ($search !== '' ? '&search=' . urlencode($search) : '')
    . ($filterCategory > 0 ? '&category_id=' . $filterCategory : '')
    . '&date_from=' . urlencode($filterDateFrom)
    . '&date_to=' . urlencode($filterDateTo);

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container-fluid" style="padding: 15px 25px;">
    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
        <div>
            <h2 style="font-weight: 800; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 8px;">
                <span>💸</span> إدارة المصروفات والتكاليف التشغيلية
            </h2>
            <p style="color: #64748b; margin: 5px 0 0;">تسجيل ومتابعة جميع مصاريف الكافيه والخامات والصيانة مع الربط بدرج الكاشير</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="../../index.php" class="btn btn-secondary" style="border-radius: 8px;">🏠 الرئيسية</a>
            <?php if (hasPermission('expenses.add')): ?>
            <button class="btn btn-primary" onclick="openAddModal()" style="border-radius: 8px; font-weight: 700; background: #ea580c; border-color: #ea580c;">
                ➕ تسجيل مصروف جديد
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 20px;">
        <div style="background: white; border-radius: 12px; padding: 18px; box-shadow: 0 2px 4px rgba(0,0,0,0.04); display: flex; align-items: center; gap: 15px;">
            <div style="width: 48px; height: 48px; border-radius: 10px; background: #fee2e2; color: #dc2626; display: flex; align-items: center; justify-content: center; font-size: 24px;">📅</div>
            <div>
                <div style="font-size: 13px; color: #64748b; font-weight: 600;">مصروفات اليوم</div>
                <div style="font-size: 22px; font-weight: 800; color: #dc2626;"><?php echo formatCurrency($todayExpenses); ?></div>
            </div>
        </div>
        <div style="background: white; border-radius: 12px; padding: 18px; box-shadow: 0 2px 4px rgba(0,0,0,0.04); display: flex; align-items: center; gap: 15px;">
            <div style="width: 48px; height: 48px; border-radius: 10px; background: #fef3c7; color: #d97706; display: flex; align-items: center; justify-content: center; font-size: 24px;">⏱️</div>
            <div>
                <div style="font-size: 13px; color: #64748b; font-weight: 600;">من درج الشيفت المفتوح</div>
                <div style="font-size: 22px; font-weight: 800; color: #d97706;"><?php echo formatCurrency($shiftExpenses); ?></div>
            </div>
        </div>
        <div style="background: white; border-radius: 12px; padding: 18px; box-shadow: 0 2px 4px rgba(0,0,0,0.04); display: flex; align-items: center; gap: 15px;">
            <div style="width: 48px; height: 48px; border-radius: 10px; background: #ede9fe; color: #6d28d9; display: flex; align-items: center; justify-content: center; font-size: 24px;">📊</div>
            <div>
                <div style="font-size: 13px; color: #64748b; font-weight: 600;">إجمالي الشهر الحالي</div>
                <div style="font-size: 22px; font-weight: 800; color: #1e293b;"><?php echo formatCurrency($monthExpenses); ?></div>
            </div>
        </div>
        <div style="background: white; border-radius: 12px; padding: 18px; box-shadow: 0 2px 4px rgba(0,0,0,0.04); display: flex; align-items: center; gap: 15px;">
            <div style="width: 48px; height: 48px; border-radius: 10px; background: #f1f5f9; color: #475569; display: flex; align-items: center; justify-content: center; font-size: 24px;">🔍</div>
            <div>
                <div style="font-size: 13px; color: #64748b; font-weight: 600;">مجموع الفترة المحددة</div>
                <div style="font-size: 22px; font-weight: 800; color: #059669;"><?php echo formatCurrency($filteredTotal); ?></div>
            </div>
        </div>
    </div>

    <!-- Filter & Search Form -->
    <div class="card" style="border-radius: 12px; border: none; box-shadow: 0 2px 4px rgba(0,0,0,0.04); margin-bottom: 20px;">
        <div class="card-body" style="padding: 16px;">
            <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
                <div style="flex: 1; min-width: 220px;">
                    <label class="form-label" style="font-size: 13px; font-weight: 600;">بحث سريع</label>
                    <input type="text" name="search" class="form-control" placeholder="🔍 بحث في البيان أو اسم المستلم..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div style="width: 170px;">
                    <label class="form-label" style="font-size: 13px; font-weight: 600;">من تاريخ</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($filterDateFrom); ?>">
                </div>
                <div style="width: 170px;">
                    <label class="form-label" style="font-size: 13px; font-weight: 600;">إلى تاريخ</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($filterDateTo); ?>">
                </div>
                <div style="width: 190px;">
                    <label class="form-label" style="font-size: 13px; font-weight: 600;">بند المصروف</label>
                    <select name="category_id" class="form-select">
                        <option value="">كل البنود</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $filterCategory == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-secondary" style="border-radius: 8px;">تصفية</button>
                    <a href="index.php" class="btn btn-outline-danger" style="border-radius: 8px;">إعادة ضبط</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Expenses Table -->
    <div class="card" style="border-radius: 12px; border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table table-hover" style="margin: 0;">
                    <thead style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                        <tr>
                            <th style="padding: 14px 20px;">التاريخ</th>
                            <th>بند المصروف</th>
                            <th>البيان والتفاصيل</th>
                            <th>المبلغ</th>
                            <th>صُرف إلى</th>
                            <th>الشيفت / المسؤول</th>
                            <th style="text-align: left; padding-left: 20px;">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($expenses)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px; color: #94a3b8;">
                                    لا توجد مصروفات مسجلة تطابق معايير البحث.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($expenses as $e): ?>
                                <tr style="vertical-align: middle;">
                                    <td style="padding: 14px 20px; font-size: 13px; color: #64748b;">
                                        <?php echo htmlspecialchars($e['date']); ?>
                                    </td>
                                    <td>
                                        <span class="badge" style="background: #e0e7ff; color: #3730a3; padding: 5px 10px; border-radius: 6px;">
                                            <?php echo htmlspecialchars($e['category_name']); ?>
                                        </span>
                                    </td>
                                    <td style="font-weight: 700; color: #1e293b;">
                                        <?php echo htmlspecialchars($e['description']); ?>
                                    </td>
                                    <td style="font-weight: 800; color: #dc2626; font-size: 16px;">
                                        -<?php echo formatCurrency($e['amount']); ?>
                                    </td>
                                    <td style="color: #475569;">
                                        <?php echo htmlspecialchars($e['paid_to'] ?? '—'); ?>
                                    </td>
                                    <td style="font-size: 13px;">
                                        <?php if ($e['shift_number']): ?>
                                            <span class="badge" style="background: #fef3c7; color: #92400e; padding: 3px 6px;">
                                                شیفت: <?php echo htmlspecialchars($e['shift_number']); ?>
                                            </span>
                                        <?php endif; ?>
                                        <div style="color: #94a3b8;"><?php echo htmlspecialchars($e['user_name'] ?? ''); ?></div>
                                    </td>
                                    <td style="text-align: left; padding-left: 20px;">
                                        <button class="btn btn-sm btn-outline-primary" onclick='editExpense(<?php echo json_encode($e); ?>)' style="border-radius: 6px;">
                                            ✏️ تعديل
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick='confirmDeleteExpense(<?php echo $e['id']; ?>, <?php echo json_encode($e['description']); ?>, "<?php echo formatCurrency($e['amount']); ?>")' style="border-radius: 6px;">
                                            🗑️ حذف
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($totalPages > 1): ?>
                <div style="padding: 16px 22px; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #e2e8f0; flex-wrap: wrap; gap: 12px; background: #fafbfc;">
                    <div style="color: #64748b; font-size: 13.5px; font-weight: 600;">
                        عرض صفحة <span style="color: #0f172a; font-weight: 800;"><?php echo $page; ?></span> من إجمالي <span style="color: #0f172a; font-weight: 800;"><?php echo $totalPages; ?></span> (<?php echo $totalExpenses; ?> مصروف مسجل)
                    </div>
                    <nav aria-label="تنقل الصفحات">
                        <ul class="pagination" style="list-style: none !important; list-style-type: none !important; margin: 0 !important; padding: 0 !important; display: flex !important; align-items: center !important; gap: 5px !important;">
                            <!-- Previous Page -->
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>" style="list-style: none !important; margin: 0 !important; padding: 0 !important;">
                                <a class="page-link" href="<?php echo ($page > 1) ? '?page=' . ($page - 1) . $paginationBaseQuery : '#'; ?>"
                                   style="display: inline-flex !important; align-items: center !important; justify-content: center !important; height: 36px !important; padding: 0 14px !important; font-size: 13px !important; font-weight: 700 !important; border-radius: 8px !important; border: 1px solid #cbd5e1 !important; text-decoration: none !important; <?php echo ($page <= 1) ? 'color: #94a3b8 !important; background: #f1f5f9 !important; pointer-events: none !important;' : 'color: #334155 !important; background: #fff !important;'; ?>">
                                    ← السابق
                                </a>
                            </li>
                            <?php
                            $startPage = max(1, $page - 3);
                            $endPage = min($totalPages, $page + 3);
                            if ($startPage > 1) {
                                echo '<li style="list-style: none !important;"><a class="page-link" href="?page=1' . $paginationBaseQuery . '" style="display: inline-flex !important; align-items: center !important; justify-content: center !important; min-width: 36px !important; height: 36px !important; padding: 0 10px !important; font-weight: 700 !important; border-radius: 8px !important; border: 1px solid #cbd5e1 !important; text-decoration: none !important; background: #fff !important; color: #334155 !important;">1</a></li>';
                                if ($startPage > 2) {
                                    echo '<li style="list-style: none !important; color: #94a3b8; padding: 0 4px;">…</li>';
                                }
                            }
                            for ($i = $startPage; $i <= $endPage; $i++) {
                                $isActive = ($i == $page);
                                echo '<li style="list-style: none !important;"><a class="page-link" href="?page=' . $i . $paginationBaseQuery . '" style="display: inline-flex !important; align-items: center !important; justify-content: center !important; min-width: 36px !important; height: 36px !important; padding: 0 10px !important; font-weight: ' . ($isActive ? '800' : '700') . ' !important; border-radius: 8px !important; text-decoration: none !important; border: 1px solid ' . ($isActive ? '#ea580c' : '#cbd5e1') . ' !important; background: ' . ($isActive ? '#ea580c' : '#fff') . ' !important; color: ' . ($isActive ? '#fff' : '#334155') . ' !important;">' . $i . '</a></li>';
                            }
                            if ($endPage < $totalPages) {
                                if ($endPage < $totalPages - 1) {
                                    echo '<li style="list-style: none !important; color: #94a3b8; padding: 0 4px;">…</li>';
                                }
                                echo '<li style="list-style: none !important;"><a class="page-link" href="?page=' . $totalPages . $paginationBaseQuery . '" style="display: inline-flex !important; align-items: center !important; justify-content: center !important; min-width: 36px !important; height: 36px !important; padding: 0 10px !important; font-weight: 700 !important; border-radius: 8px !important; border: 1px solid #cbd5e1 !important; text-decoration: none !important; background: #fff !important; color: #334155 !important;">' . $totalPages . '</a></li>';
                            }
                            ?>
                            <!-- Next Page -->
                            <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>" style="list-style: none !important; margin: 0 !important; padding: 0 !important;">
                                <a class="page-link" href="<?php echo ($page < $totalPages) ? '?page=' . ($page + 1) . $paginationBaseQuery : '#'; ?>"
                                   style="display: inline-flex !important; align-items: center !important; justify-content: center !important; height: 36px !important; padding: 0 14px !important; font-size: 13px !important; font-weight: 700 !important; border-radius: 8px !important; border: 1px solid #cbd5e1 !important; text-decoration: none !important; <?php echo ($page >= $totalPages) ? 'color: #94a3b8 !important; background: #f1f5f9 !important; pointer-events: none !important;' : 'color: #334155 !important; background: #fff !important;'; ?>">
                                    التالي →
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal 1: Add/Edit Expense -->
<div class="modal" id="expenseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="expModalTitle">➕ تسجيل مصروف جديد</h5>
                <button type="button" class="btn-close" onclick="closeModal()"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" id="expAction" value="add">
                <input type="hidden" name="expense_id" id="expId" value="">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600;">بند المصروف *</label>
                            <select name="category_id" id="expCategory" class="form-select" required>
                                <option value="">-- اختر البند --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600;">المبلغ (ج.م) *</label>
                            <input type="number" step="0.5" name="amount" id="expAmount" class="form-control" placeholder="0.00" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600;">التاريخ *</label>
                            <input type="date" name="date" id="expDate" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600;">صُرف إلى / المستلم</label>
                            <input type="text" name="paid_to" id="expPaidTo" class="form-control" placeholder="اسم المستلم أو المحل">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600;">البيان والتفاصيل *</label>
                        <input type="text" name="description" id="expDesc" class="form-control" placeholder="مثال: شراء بن إضافي، صيانة ذراع PS5، فاتورة كهرباء" required>
                    </div>

                    <?php if ($activeShiftId): ?>
                        <div class="p-3" style="background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;" id="deductShiftWrapper">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" name="deduct_from_shift" id="deductShiftCheck" checked style="width: 18px; height: 18px; cursor: pointer;">
                                <label for="deductShiftCheck" style="font-weight: 700; color: #b45309; margin: 0; cursor: pointer;">
                                    خصم المبلغ مباشرة من نقدية درج الشيفت المفتوح (<?php echo htmlspecialchars($activeShift['shift_number']); ?>)
                                </label>
                            </div>
                            <div style="font-size: 12px; color: #64748b; margin-top: 6px; padding-right: 26px;">
                                سيتم خصم هذا المبلغ من النقدية المتوقعة عند إغلاق الشيفت وتسجيل حركة نقدية خارجة.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">إلغاء</button>
                    <button type="submit" class="btn btn-primary" style="background: #ea580c; border-color: #ea580c;">💾 حفظ المصروف</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: Delete Confirmation Modal -->
<div class="modal" id="deleteExpenseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
        <div class="modal-content">
            <div class="modal-header" style="background: #fee2e2; border-bottom: 1px solid #fecaca;">
                <h5 class="modal-title" style="color: #991b1b;">⚠️ تأكيد حذف المصروف</h5>
                <button type="button" class="btn-close" onclick="closeDeleteModal()"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="expense_id" id="deleteExpId" value="">
                <div class="modal-body" style="text-align: center; padding: 25px;">
                    <div style="font-size: 40px; margin-bottom: 10px;">🗑️</div>
                    <div style="font-size: 15px; color: #1e293b; margin-bottom: 8px;">
                        هل أنت متأكد من حذف هذا المصروف؟
                    </div>
                    <div id="deleteExpDesc" style="font-size: 16px; font-weight: 800; color: #dc2626; margin-bottom: 6px;"></div>
                    <div id="deleteExpAmount" style="font-size: 18px; font-weight: 900; color: #b91c1c; margin-bottom: 10px;"></div>
                    <div style="font-size: 12px; color: #64748b;">
                        إذا كان المصروف مخصوماً من شيفت حالي، سيتم إعادة ضبط الرصيد تلقائياً.
                    </div>
                </div>
                <div class="modal-footer" style="justify-content: center;">
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">إلغاء</button>
                    <button type="submit" class="btn btn-danger">🗑️ نعم، احذف</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('expModalTitle').innerText = '➕ تسجيل مصروف جديد';
    document.getElementById('expAction').value = 'add';
    document.getElementById('expId').value = '';
    document.getElementById('expCategory').value = '';
    document.getElementById('expAmount').value = '';
    document.getElementById('expDate').value = '<?php echo date('Y-m-d'); ?>';
    document.getElementById('expPaidTo').value = '';
    document.getElementById('expDesc').value = '';
    const deduct = document.getElementById('deductShiftWrapper');
    if (deduct) deduct.style.display = 'block';
    document.getElementById('expenseModal').style.display = 'flex';
}

function editExpense(e) {
    document.getElementById('expModalTitle').innerText = '✏️ تعديل المصروف #' + e.id;
    document.getElementById('expAction').value = 'edit';
    document.getElementById('expId').value = e.id;
    document.getElementById('expCategory').value = e.category_id;
    document.getElementById('expAmount').value = e.amount;
    document.getElementById('expDate').value = e.date;
    document.getElementById('expPaidTo').value = e.paid_to || '';
    document.getElementById('expDesc').value = e.description;
    const deduct = document.getElementById('deductShiftWrapper');
    if (deduct) deduct.style.display = 'none';
    document.getElementById('expenseModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('expenseModal').style.display = 'none';
}

function confirmDeleteExpense(id, desc, amount) {
    document.getElementById('deleteExpId').value = id;
    document.getElementById('deleteExpDesc').innerText = desc;
    document.getElementById('deleteExpAmount').innerText = amount;
    document.getElementById('deleteExpenseModal').style.display = 'flex';
}

function closeDeleteModal() {
    document.getElementById('deleteExpenseModal').style.display = 'none';
}

window.onclick = function(e) {
    if (e.target == document.getElementById('expenseModal')) {
        closeModal();
    }
    if (e.target == document.getElementById('deleteExpenseModal')) {
        closeDeleteModal();
    }
}
</script>

<?php include '../../includes/footer.php'; ?>
