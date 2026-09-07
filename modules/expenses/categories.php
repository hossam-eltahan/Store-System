<?php
/**
 * Expense Categories Management
 * نظام إدارة محل أجهزة منزلية - إدارة بنود وتصنيفات المصروفات
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
require_once '../../config/expenses.php';

requirePermission('expenses.categories');

$pageTitle = 'إدارة بنود وتصنيفات المصروفات';

$editId = intval($_GET['edit'] ?? 0);
$editingCategory = $editId ? getRow("SELECT * FROM expense_categories WHERE id = ?", [$editId]) : null;

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $categoryId = intval($_POST['category_id'] ?? 0);

    // Delete category
    if ($action === 'delete') {
        // Check if any expenses are linked to this category
        $usageCount = getRow("SELECT COUNT(*) as count FROM expenses WHERE category_id = ?", [$categoryId]);
        if (($usageCount['count'] ?? 0) > 0) {
            setError('لا يمكن حذف هذا البند لوجود مصروفات وسندات مسجلة عليه بالفعل (' . $usageCount['count'] . ' سند). يمكنك تعطيله بدلاً من حذفه.');
        } else {
            execute("DELETE FROM expense_categories WHERE id = ?", [$categoryId]);
            logActivity('حذف بند مصروف', "تم حذف بند المصروف رقم $categoryId");
            setSuccess('تم حذف البند بنجاح.');
        }
        redirect('categories.php');
    }

    // Toggle Status (Active / Inactive)
    if ($action === 'toggle_status') {
        $cat = getRow("SELECT * FROM expense_categories WHERE id = ?", [$categoryId]);
        if ($cat) {
            $newStatus = $cat['is_active'] ? 0 : 1;
            execute("UPDATE expense_categories SET is_active = ? WHERE id = ?", [$newStatus, $categoryId]);
            logActivity('تعديل حالة بند مصروف', "تم تغيير حالة بند {$cat['name']} إلى " . ($newStatus ? 'نشط' : 'معطل'));
            setSuccess('تم تحديث حالة البند بنجاح.');
        }
        redirect('categories.php');
    }

    // Create or Update
    $name = sanitize(trim($_POST['name'] ?? ''));
    $icon = sanitize(trim($_POST['icon'] ?? '💸'));
    $description = sanitize(trim($_POST['description'] ?? ''));
    $sortOrder = intval($_POST['sort_order'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if (empty($name)) {
        setError('يرجى إدخال اسم البند.');
    } else {
        // Check for duplicate name
        $checkDupSql = "SELECT id FROM expense_categories WHERE name = ?" . ($action === 'update' ? " AND id != ?" : "");
        $checkDupParams = $action === 'update' ? [$name, $categoryId] : [$name];
        
        if (getRow($checkDupSql, $checkDupParams)) {
            setError('يوجد بند مصروف آخر مسجل بنفس هذا الاسم مسبقاً.');
        } elseif ($action === 'update') {
            execute("
                UPDATE expense_categories 
                SET name = ?, icon = ?, description = ?, sort_order = ?, is_active = ?
                WHERE id = ?
            ", [$name, $icon, $description, $sortOrder, $isActive, $categoryId]);

            logActivity('تعديل بند مصروف', "تم تعديل بند المصروف: $name");
            setSuccess('تم تحديث بيانات البند بنجاح.');
            redirect('categories.php');
        } else {
            insert("
                INSERT INTO expense_categories (name, icon, description, sort_order, is_active)
                VALUES (?, ?, ?, ?, ?)
            ", [$name, $icon, $description, $sortOrder, $isActive]);

            logActivity('إضافة بند مصروف', "تمت إضافة بند مصروف جديد: $name");
            setSuccess('تمت إضافة بند المصروف الجديد بنجاح.');
            redirect('categories.php');
        }
    }
}

// Fetch all categories with count of expenses and total spent
$categories = getRows("
    SELECT c.*, 
           COUNT(e.id) as expenses_count, 
           COALESCE(SUM(e.amount), 0) as total_spent
    FROM expense_categories c
    LEFT JOIN expenses e ON c.id = e.category_id
    GROUP BY c.id
    ORDER BY c.sort_order ASC, c.name ASC
");

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
        <div>
            <h1 style="font-size: 1.5rem; margin: 0 0 4px 0; color: #0f172a;">🏷️ إدارة بنود وتصنيفات المصروفات</h1>
            <p style="margin: 0; color: #64748b; font-size: 0.88rem;">إضافة وتعديل بنود الصرف كفواتير الكهرباء والمياه والإيجار والمرتبات والنثريات</p>
        </div>
        <div>
            <a href="index.php" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px;">
                <span>←</span> العودة لسجل المصروفات
            </a>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px; align-items: start;">
        <!-- Add / Edit Form Card -->
        <div class="card">
            <div class="card-header">
                <strong><?php echo $editingCategory ? '✏️ تعديل بند مصروف' : '➕ إضافة بند مصروف جديد'; ?></strong>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="<?php echo $editingCategory ? 'update' : 'create'; ?>">
                    <?php if ($editingCategory): ?>
                    <input type="hidden" name="category_id" value="<?php echo $editingCategory['id']; ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label class="form-label required">اسم البند / التصنيف</label>
                        <input type="text" name="name" class="form-control" required 
                               value="<?php echo htmlspecialchars($editingCategory['name'] ?? ''); ?>"
                               placeholder="مثال: فاتورة الإنترنت، صيانة أجهزة، إكراميات...">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">الأيقونة التعبيرية</label>
                            <input type="text" name="icon" class="form-control" 
                                   value="<?php echo htmlspecialchars($editingCategory['icon'] ?? '💸'); ?>"
                                   placeholder="مثال: ⚡ أو 🏢 أو 👥">
                        </div>

                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">الترتيب في القائمة</label>
                            <input type="number" name="sort_order" class="form-control" 
                                   value="<?php echo intval($editingCategory['sort_order'] ?? 10); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">الوصف والملاحظات</label>
                        <textarea name="description" class="form-control" rows="2" 
                                  placeholder="وصف مختصر لطبيعة الصرف في هذا البند..."><?php echo htmlspecialchars($editingCategory['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group" style="margin-bottom: 18px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.9rem;">
                            <input type="checkbox" name="is_active" value="1" <?php echo (!isset($editingCategory) || $editingCategory['is_active']) ? 'checked' : ''; ?>>
                            <span>تفعيل البند وإتاحته في شاشة تسجيل المصروفات</span>
                        </label>
                    </div>

                    <div style="display: flex; gap: 8px;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            <?php echo $editingCategory ? 'حفظ التعديلات' : 'إضافة البند الآن'; ?>
                        </button>
                        <?php if ($editingCategory): ?>
                        <a href="categories.php" class="btn btn-secondary">إلغاء</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Categories List Table -->
        <div class="card">
            <div class="card-header">
                <strong>📋 قائمة بنود المصروفات المتاحة (<?php echo count($categories); ?>)</strong>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="table" style="margin-bottom: 0;">
                        <thead>
                            <tr>
                                <th style="width: 50px;">الأيقونة</th>
                                <th>اسم البند</th>
                                <th>الوصف</th>
                                <th style="text-align: center;">عدد السندات</th>
                                <th>إجمالي المصروف</th>
                                <th style="text-align: center;">الحالة</th>
                                <th style="width: 100px; text-align: center;">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categories)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 25px; color: #64748b;">
                                    لا توجد بنود مصروفات مسجلة حالياً
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($categories as $c): ?>
                            <tr>
                                <td style="text-align: center; font-size: 1.3rem;">
                                    <?php echo $c['icon'] ?: '💸'; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($c['name']); ?></strong>
                                </td>
                                <td>
                                    <span style="font-size: 0.82rem; color: #64748b;">
                                        <?php echo htmlspecialchars($c['description'] ?: '—'); ?>
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge badge-info" style="font-size: 0.8rem;">
                                        <?php echo $c['expenses_count']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-weight: 700; color: #dc2626; font-size: 0.9rem;">
                                        <?php echo formatCurrency($c['total_spent']); ?>
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="category_id" value="<?php echo $c['id']; ?>">
                                        <?php if ($c['is_active']): ?>
                                        <button type="submit" class="badge badge-success" style="border: none; cursor: pointer;" title="اضغط لتعطيل البند">
                                            مفعّل
                                        </button>
                                        <?php else: ?>
                                        <button type="submit" class="badge badge-danger" style="border: none; cursor: pointer;" title="اضغط لتفعيل البند">
                                            معطّل
                                        </button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                                <td style="text-align: center;">
                                    <div style="display: inline-flex; gap: 6px;">
                                        <a href="categories.php?edit=<?php echo $c['id']; ?>" class="btn btn-warning btn-sm" style="padding: 4px 8px;" title="تعديل">
                                            <span>✏️</span>
                                        </a>

                                        <?php if ($c['expenses_count'] == 0): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('هل أنت متأكد من حذف هذا البند؟');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="category_id" value="<?php echo $c['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" style="padding: 4px 8px;" title="حذف">
                                                <span>🗑️</span>
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <button type="button" class="btn btn-secondary btn-sm" style="padding: 4px 8px; opacity: 0.5; cursor: not-allowed;" title="لا يمكن حذف بند مستخدم في مصروفات">
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
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
