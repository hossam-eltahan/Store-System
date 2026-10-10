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

        if ($id > 0 && $amount > 0 && !empty($description)) {
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

// Filters
$filterCategory = (int)($_GET['category_id'] ?? 0);
$filterDateFrom = $_GET['date_from'] ?? date('Y-m-01');
$filterDateTo = $_GET['date_to'] ?? date('Y-m-d');

$where = ["e.date BETWEEN ? AND ?"];
$params = [$filterDateFrom, $filterDateTo];

if ($filterCategory > 0) {
    $where[] = "e.category_id = ?";
    $params[] = $filterCategory;
}

$whereClause = 'WHERE ' . implode(' AND ', $where);

// Expenses query
$expenses = getRows("
    SELECT e.*, c.name as category_name, u.full_name as user_name, s.shift_number
    FROM expenses e
    JOIN expense_categories c ON c.id = e.category_id
    LEFT JOIN users u ON u.id = e.user_id
    LEFT JOIN shifts s ON s.id = e.shift_id
    $whereClause
    ORDER BY e.date DESC, e.id DESC
", $params);

// Stats
$todayExpenses = getRow("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE date = CURDATE()")['total'] ?? 0;
$shiftExpenses = $activeShiftId ? (getRow("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE shift_id = ?", [$activeShiftId])['total'] ?? 0) : 0;
$monthExpenses = getRow("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")['total'] ?? 0;
$filteredTotal = array_sum(array_column($expenses, 'amount'));

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container-fluid" style="padding: 15px 25px;">
    <!-- Top Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
        <div>
            <h2 style="font-weight: 800; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 10px;">
                💸 المصروفات والتكاليف التشغيلية
            </h2>
            <p style="color: #64748b; margin: 4px 0 0;">تسجيل ومتابعة مصاريف الكافيه وصيانة الأجهزة والعمالة مع ربط الدرج بالشيفت المفتوح</p>
        </div>
        <div>
            <button class="btn btn-primary" onclick="openAddModal()" style="border-radius: 8px; font-weight: 700; background: #6F4E37; border-color: #6F4E37;">
                ➕ تسجيل مصروف جديد
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 25px;">
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

    <!-- Filter Form -->
    <div class="card" style="border-radius: 12px; border: none; box-shadow: 0 2px 4px rgba(0,0,0,0.04); margin-bottom: 20px;">
        <div class="card-body" style="padding: 16px;">
            <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
                <div style="flex: 1; min-width: 180px;">
                    <label class="form-label" style="font-size: 13px; font-weight: 600;">من تاريخ</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($filterDateFrom); ?>">
                </div>
                <div style="flex: 1; min-width: 180px;">
                    <label class="form-label" style="font-size: 13px; font-weight: 600;">إلى تاريخ</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($filterDateTo); ?>">
                </div>
                <div style="flex: 1; min-width: 200px;">
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
                                    لا توجد مصروفات مسجلة خلال هذه الفترة.
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
                                        <button class="btn btn-sm btn-outline-primary" onclick='editExpense(<?php echo json_encode($e); ?>)'>
                                            ✏️
                                        </button>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('هل أنت متأكد من حذف هذا المصروف؟');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="expense_id" value="<?php echo $e['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">🗑️</button>
                                        </form>
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

<!-- Modal: Add Expense -->
<div class="modal" id="expenseModal" tabindex="-1" style="display: none; background: rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header">
                <h5 class="modal-title" id="expModalTitle" style="font-weight: 700;">➕ تسجيل مصروف جديد</h5>
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
                        <div class="form-check form-switch mt-3 p-3" style="background: #f8fafc; border-radius: 8px;" id="deductShiftWrapper">
                            <input class="form-check-input" type="checkbox" name="deduct_from_shift" id="deductShiftCheck" checked>
                            <label class="form-check-label" for="deductShiftCheck" style="font-weight: 700; color: #b45309;">
                                خصم المبلغ مباشرة من نقدية درج الشيفت المفتوح (<?php echo htmlspecialchars($activeShift['shift_number']); ?>)
                            </label>
                            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">
                                سيتم خصم هذا المبلغ من النقدية المتوقعة عند إغلاق الشيفت وتسجيل حركة نقدية خارجة.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">إلغاء</button>
                    <button type="submit" class="btn btn-primary" style="background: #6F4E37; border-color: #6F4E37;">💾 حفظ المصروف</button>
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
    document.getElementById('expenseModal').style.display = 'block';
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
    document.getElementById('expenseModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('expenseModal').style.display = 'none';
}

window.onclick = function(e) {
    if (e.target == document.getElementById('expenseModal')) {
        closeModal();
    }
}
</script>

<?php include '../../includes/footer.php'; ?>
