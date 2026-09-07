<?php
require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
require_once '../../config/categories.php';
requirePermission('categories.view');

$pageTitle = 'إدارة الفئات';
$editId = (int)($_GET['edit'] ?? 0);
$editingCategory = $editId ? getRow("SELECT * FROM categories WHERE id = ?", [$editId]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePermission('categories.manage');

    $action = $_POST['action'] ?? '';
    $categoryId = (int)($_POST['category_id'] ?? 0);

    if ($action === 'delete') {
        $productCount = getRow("SELECT COUNT(*) AS count FROM products WHERE category_id = ?", [$categoryId]);
        $childCount = getRow("SELECT COUNT(*) AS count FROM categories WHERE parent_id = ?", [$categoryId]);

        if (($productCount['count'] ?? 0) > 0 || ($childCount['count'] ?? 0) > 0) {
            setError('لا يمكن حذف الفئة لأنها مرتبطة بأصناف أو بها فئات فرعية. انقل الأصناف والفئات أولاً.');
        } else {
            execute("DELETE FROM categories WHERE id = ?", [$categoryId]);
            logActivity('حذف فئة', 'تم حذف فئة أصناف');
            setSuccess('تم حذف الفئة بنجاح');
        }
        redirect('index.php');
    }

    $name = sanitize(trim($_POST['name'] ?? ''));
    $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    $description = sanitize(trim($_POST['description'] ?? ''));
    $sortOrder = max(0, (int)($_POST['sort_order'] ?? 0));
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '') {
        setError('اسم الفئة مطلوب');
    } elseif ($parentId && !getRow("SELECT id FROM categories WHERE id = ?", [$parentId])) {
        setError('الفئة الرئيسية المحددة غير موجودة');
    } elseif ($action === 'update' && ($parentId === $categoryId || in_array($parentId, getCategoryDescendantIds($categoryId), true))) {
        setError('لا يمكن جعل الفئة تابعة لنفسها أو لإحدى فئاتها الفرعية');
    } else {
        $duplicateSql = "SELECT id FROM categories WHERE name = ? AND ";
        $duplicateParams = [$name];
        if ($parentId === null) {
            $duplicateSql .= "parent_id IS NULL";
        } else {
            $duplicateSql .= "parent_id = ?";
            $duplicateParams[] = $parentId;
        }
        if ($action === 'update') {
            $duplicateSql .= " AND id != ?";
            $duplicateParams[] = $categoryId;
        }

        if (getRow($duplicateSql, $duplicateParams)) {
            setError('يوجد فئة بنفس الاسم داخل نفس المستوى');
        } elseif ($action === 'update') {
            execute("UPDATE categories SET name = ?, parent_id = ?, description = ?, sort_order = ?, is_active = ? WHERE id = ?", [$name, $parentId, $description, $sortOrder, $isActive, $categoryId]);
            logActivity('تعديل فئة', "تم تعديل الفئة: $name");
            setSuccess('تم تعديل الفئة بنجاح');
            redirect('index.php');
        } else {
            insert("INSERT INTO categories (name, parent_id, description, sort_order, is_active) VALUES (?, ?, ?, ?, ?)", [$name, $parentId, $description, $sortOrder, $isActive]);
            logActivity('إضافة فئة', "تم إضافة الفئة: $name");
            setSuccess('تم إضافة الفئة بنجاح');
            redirect('index.php');
        }
    }
}

$categories = getCategoriesForSelect(false);
$parentOptions = $editingCategory ? array_filter($categories, function ($category) use ($editId) {
    return (int)$category['id'] !== $editId && !in_array((int)$category['id'], getCategoryDescendantIds($editId), true);
}) : $categories;

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header">🏷️ <?php echo $editingCategory ? 'تعديل فئة' : 'إدارة فئات الأصناف'; ?></div>
        <div class="card-body">
            <?php if (hasPermission('categories.manage')): ?>
            <form method="POST" class="mb-3">
                <input type="hidden" name="action" value="<?php echo $editingCategory ? 'update' : 'create'; ?>">
                <?php if ($editingCategory): ?><input type="hidden" name="category_id" value="<?php echo $editingCategory['id']; ?>"><?php endif; ?>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">اسم الفئة</label>
                        <input name="name" class="form-control" required value="<?php echo htmlspecialchars($editingCategory['name'] ?? ''); ?>" placeholder="مثال: مشروبات أو كاجوال">
                    </div>
                    <div class="form-group">
                        <label class="form-label">الفئة الرئيسية</label>
                        <select name="parent_id" class="form-control">
                            <option value="">فئة رئيسية</option>
                            <?php foreach ($parentOptions as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo ((int)($editingCategory['parent_id'] ?? 0) === (int)$category['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($category['display_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">ترتيب العرض</label>
                        <input type="number" min="0" name="sort_order" class="form-control" value="<?php echo (int)($editingCategory['sort_order'] ?? 0); ?>">
                    </div>
                    <div class="form-group" style="align-self:end;">
                        <label><input type="checkbox" name="is_active" value="1" <?php echo !isset($editingCategory) || !empty($editingCategory['is_active']) ? 'checked' : ''; ?>> نشطة</label>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">وصف اختياري</label>
                    <textarea name="description" class="form-control" rows="2"><?php echo htmlspecialchars($editingCategory['description'] ?? ''); ?></textarea>
                </div>
                <button class="btn btn-primary" type="submit"><?php echo $editingCategory ? '💾 حفظ التعديلات' : '➕ إضافة فئة'; ?></button>
                <?php if ($editingCategory): ?><a class="btn btn-secondary" href="index.php">إلغاء</a><?php endif; ?>
            </form>
            <?php endif; ?>

            <div class="table-container">
                <table class="table">
                    <thead><tr><th>الفئة</th><th>الوصف</th><th>الحالة</th><th>الترتيب</th><th>الأصناف</th><th>إجراءات</th></tr></thead>
                    <tbody>
                    <?php foreach ($categories as $category): ?>
                        <?php $productCount = getRow("SELECT COUNT(*) AS count FROM products WHERE category_id = ?", [$category['id']]); ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($category['display_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($category['description'] ?? '—'); ?></td>
                            <td><span class="badge <?php echo $category['is_active'] ? 'badge-success' : 'badge-secondary'; ?>"><?php echo $category['is_active'] ? 'نشطة' : 'موقوفة'; ?></span></td>
                            <td><?php echo (int)$category['sort_order']; ?></td>
                            <td><?php echo (int)$productCount['count']; ?></td>
                            <td>
                                <?php if (hasPermission('categories.manage')): ?>
                                <a class="btn btn-primary btn-sm" href="?edit=<?php echo $category['id']; ?>">تعديل</a>
                                <form method="POST" style="display:inline" onsubmit="return confirm('حذف هذه الفئة؟');">
                                    <input type="hidden" name="action" value="delete"><input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                    <button class="btn btn-danger btn-sm" type="submit">حذف</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$categories): ?><tr><td colspan="6" class="text-center">لا توجد فئات حتى الآن. أضف أول فئة مناسبة لنشاطك.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
