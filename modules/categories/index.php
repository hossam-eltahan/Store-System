<?php
/**
 * Categories Management (إدارة أقسام المنيو)
 * نظام كافيه عروسة النيل
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
require_once '../../config/categories.php';
requirePermission('categories.view');

$pageTitle = 'إدارة أقسام وقوائم المنيو';
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
            setError('لا يمكن حذف القسم لأنه يحتوي على أصناف بالمنيو. يرجى نقل الأصناف أو تعديلها أولاً.');
        } else {
            execute("DELETE FROM categories WHERE id = ?", [$categoryId]);
            logActivity('حذف قسم', 'تم حذف قسم من المنيو');
            setSuccess('تم حذف القسم بنجاح');
        }
        redirect('index.php');
    }

    $name = sanitize(trim($_POST['name'] ?? ''));
    $icon = sanitize(trim($_POST['icon'] ?? '☕'));
    $color = sanitize(trim($_POST['color'] ?? '#6F4E37'));
    $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    $description = sanitize(trim($_POST['description'] ?? ''));
    $sortOrder = max(0, (int)($_POST['sort_order'] ?? 0));
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '') {
        setError('اسم القسم مطلوب');
    } elseif ($parentId && !getRow("SELECT id FROM categories WHERE id = ?", [$parentId])) {
        setError('القسم الرئيسي المحدد غير موجود');
    } elseif ($action === 'update' && ($parentId === $categoryId || in_array($parentId, getCategoryDescendantIds($categoryId), true))) {
        setError('لا يمكن جعل القسم تابعاً لنفسه');
    } else {
        if ($action === 'update') {
            execute(
                "UPDATE categories SET name = ?, icon = ?, color = ?, parent_id = ?, description = ?, sort_order = ?, is_active = ? WHERE id = ?",
                [$name, $icon, $color, $parentId, $description, $sortOrder, $isActive, $categoryId]
            );
            logActivity('تعديل قسم', "تم تعديل القسم: $name");
            setSuccess('تم تعديل بيانات القسم بنجاح');
            redirect('index.php');
        } else {
            insert(
                "INSERT INTO categories (name, icon, color, parent_id, description, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$name, $icon, $color, $parentId, $description, $sortOrder, $isActive]
            );
            logActivity('إضافة قسم', "تم إضافة قسم للمنيو: $name");
            setSuccess('تم إضافة القسم بنجاح');
            redirect('index.php');
        }
    }
}

// Search filter
$search = trim($_GET['search'] ?? '');
$searchWhere = "";
$params = [];
if ($search !== '') {
    $searchWhere = "WHERE c.name LIKE ?";
    $params[] = "%$search%";
}

$categoriesPerPage = 8;
$categoriesPage = max(1, (int)($_GET['page'] ?? 1));
$totalCategoriesRow = getRow("SELECT COUNT(*) AS total FROM categories c $searchWhere", $params);
$totalCategories = (int)($totalCategoriesRow['total'] ?? 0);
$totalCategoryPages = max(1, (int)ceil($totalCategories / $categoriesPerPage));
$categoriesPage = min($categoriesPage, $totalCategoryPages);
$categoriesOffset = ($categoriesPage - 1) * $categoriesPerPage;

$categoriesList = getRows("
    SELECT c.*, COUNT(p.id) AS products_count 
    FROM categories c 
    LEFT JOIN products p ON p.category_id = c.id 
    GROUP BY c.id 
    $searchWhere
    ORDER BY c.sort_order ASC, c.name ASC
    LIMIT $categoriesPerPage OFFSET $categoriesOffset
", $params);

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<style>
    .category-page { padding: 12px 20px !important; }
    .category-page .category-header { margin-bottom: 14px !important; }
    .category-page .category-header h2 { font-size: 19px; }
    .category-page .category-header p { margin-top: 2px !important; font-size: 12px; }
    .category-page .card { margin-bottom: 12px !important; box-shadow: 0 2px 5px rgba(15, 23, 42, 0.04) !important; }
    .category-page .card-body { padding: 14px !important; }
    .category-page .form-control { min-height: 35px; padding: 6px 9px; font-size: 12.5px; }
    .category-page table th, .category-page table td { padding: 8px 10px !important; font-size: 12px; }
</style>

<div class="container-fluid category-page">
    <!-- Top Header -->
    <div class="category-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <div>
            <h2 style="font-weight: 800; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 8px;">
                <span>🏷️</span> إدارة أقسام المنيو
            </h2>
            <p style="color: #64748b; margin: 5px 0 0;">إضافة وترتيب أقسام المشروبات والحلويات مع تحديد الأيقونات والألوان للظهور في الكاشير</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="../../index.php" class="btn btn-secondary" style="border-radius: 8px;">🏠 الرئيسية</a>
            <a href="../products/index.php" class="btn btn-secondary" style="border-radius: 8px;">📋 العودة للأصناف</a>
        </div>
    </div>

    <div class="row">
        <!-- Form Column -->
        <?php if (hasPermission('categories.manage')): ?>
        <div class="col-lg-4 mb-4">
            <div class="card" style="border-radius: 12px; border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                <div class="card-header" style="background: #f8fafc; font-weight: 700; border-bottom: 1px solid #e2e8f0;">
                    <?php echo $editingCategory ? '✏️ تعديل بيانات القسم' : '➕ إضافة قسم جديد للمنيو'; ?>
                </div>
                <div class="card-body" style="padding: 20px;">
                    <form method="POST">
                        <input type="hidden" name="action" value="<?php echo $editingCategory ? 'update' : 'create'; ?>">
                        <?php if ($editingCategory): ?>
                            <input type="hidden" name="category_id" value="<?php echo $editingCategory['id']; ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label" style="font-weight: 600;">اسم القسم *</label>
                            <input name="name" class="form-control" required value="<?php echo htmlspecialchars($editingCategory['name'] ?? ''); ?>" placeholder="مثال: قهوة ساخنة، سموزي، وافل...">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" style="font-weight: 600;">الأيقونة (إيموجي)</label>
                                <input type="text" name="icon" id="iconInput" class="form-control" value="<?php echo htmlspecialchars($editingCategory['icon'] ?? '☕'); ?>" style="font-size: 20px; text-align: center;">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" style="font-weight: 600;">اللون في الكاشير</label>
                                <input type="color" name="color" class="form-control form-control-color w-100" value="<?php echo htmlspecialchars($editingCategory['color'] ?? '#ea580c'); ?>" style="height: 42px;">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label style="font-size: 12px; color: #64748b; font-weight: 600;">أيقونات سريعة:</label>
                            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                <?php $quickEmojis = ['☕', '🍵', '🥤', '🍹', '🧋', '🧊', '🍰', '🧇', '🥞', '🍕', '🍟', '🍫', '🍦', '🎮', '🎱']; ?>
                                <?php foreach ($quickEmojis as $em): ?>
                                    <button type="button" class="btn btn-sm btn-light" style="font-size: 16px; padding: 3px 8px;" onclick="document.getElementById('iconInput').value='<?php echo $em; ?>';"><?php echo $em; ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" style="font-weight: 600;">ترتيب العرض</label>
                            <input type="number" min="0" name="sort_order" class="form-control" value="<?php echo (int)($editingCategory['sort_order'] ?? 0); ?>" placeholder="0">
                            <small style="color: #64748b;">الأرقام الأصغر تظهر أولاً في الكاشير</small>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input type="checkbox" name="is_active" class="form-check-input" id="isActiveCheck" value="1" <?php echo (!isset($editingCategory) || !empty($editingCategory['is_active'])) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="isActiveCheck" style="font-weight: 600;">نشط ويظهر في الكاشير</label>
                        </div>

                        <div class="d-flex gap-2">
                            <button class="btn btn-primary w-100" type="submit" style="font-weight: 700; background: #ea580c; border-color: #ea580c;">
                                <?php echo $editingCategory ? '💾 حفظ التعديلات' : '➕ إضافة القسم'; ?>
                            </button>
                            <?php if ($editingCategory): ?>
                                <a class="btn btn-secondary" href="index.php">إلغاء</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Table Column -->
        <div class="<?php echo hasPermission('categories.manage') ? 'col-lg-8' : 'col-12'; ?>">
            <div class="card" style="border-radius: 12px; border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                <div class="card-header" style="background: #f8fafc; font-weight: 700; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                    <span>📋 أقسام المنيو الحالية (<?php echo $totalCategories; ?>)</span>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="table table-hover" style="margin: 0;">
                            <thead style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                                <tr>
                                    <th style="padding: 14px 20px;">القسم</th>
                                    <th>الأيقونة واللون</th>
                                    <th>الترتيب</th>
                                    <th>عدد الأصناف</th>
                                    <th>الحالة</th>
                                    <th style="text-align: left; padding-left: 20px;">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($categoriesList)): ?>
                                    <tr><td colspan="6" class="text-center" style="padding: 30px;">لا توجد أقسام مطابقة للبحث.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($categoriesList as $cat): ?>
                                        <tr style="vertical-align: middle;">
                                            <td style="padding: 14px 20px; font-weight: 700; color: #1e293b;">
                                                <a href="../products/index.php?category_id=<?php echo $cat['id']; ?>" style="text-decoration: none; color: inherit;">
                                                    <?php echo htmlspecialchars($cat['name']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <span style="font-size: 22px;"><?php echo htmlspecialchars($cat['icon'] ?? '☕'); ?></span>
                                                    <span style="display: inline-block; width: 18px; height: 18px; border-radius: 50%; background: <?php echo htmlspecialchars($cat['color'] ?? '#ea580c'); ?>; border: 1px solid #cbd5e1;"></span>
                                                </div>
                                            </td>
                                            <td><?php echo (int)$cat['sort_order']; ?></td>
                                            <td>
                                                <span class="badge" style="background: #f1f5f9; color: #334155; font-size: 13px;">
                                                    <?php echo (int)$cat['products_count']; ?> صنف
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge" style="<?php echo $cat['is_active'] ? 'background: #dcfce7; color: #166534;' : 'background: #fee2e2; color: #991b1b;'; ?>">
                                                    <?php echo $cat['is_active'] ? 'نشط' : 'معطل'; ?>
                                                </span>
                                            </td>
                                            <td style="text-align: left; padding-left: 20px;">
                                                <?php if (hasPermission('categories.manage')): ?>
                                                    <a class="btn btn-sm btn-outline-primary" href="?edit=<?php echo $cat['id']; ?>" style="border-radius: 6px;">✏️ تعديل</a>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick='confirmDeleteCategory(<?php echo $cat['id']; ?>, <?php echo json_encode($cat['name']); ?>, <?php echo (int)$cat['products_count']; ?>)' style="border-radius: 6px;" <?php echo $cat['products_count'] > 0 ? 'disabled title="لا يمكن حذف قسم به أصناف"' : ''; ?>>
                                                        🗑️ حذف
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($totalCategoryPages > 1): ?>
                    <?php $categoryQuery = $search !== '' ? '&search=' . urlencode($search) : ''; ?>
                    <div style="display:flex; justify-content:center; align-items:center; gap:6px; flex-wrap:wrap; padding:14px 10px 4px; direction:rtl;">
                        <?php if ($categoriesPage > 1): ?>
                            <a href="?page=<?php echo $categoriesPage - 1 . $categoryQuery; ?>" class="page-link" style="font-size:12px;">السابق</a>
                        <?php endif; ?>
                        <?php for ($pageNumber = 1; $pageNumber <= $totalCategoryPages; $pageNumber++): ?>
                            <a href="?page=<?php echo $pageNumber . $categoryQuery; ?>" class="page-link <?php echo $pageNumber === $categoriesPage ? 'active' : ''; ?>" style="min-width:32px; font-size:12px;"><?php echo $pageNumber; ?></a>
                        <?php endfor; ?>
                        <?php if ($categoriesPage < $totalCategoryPages): ?>
                            <a href="?page=<?php echo $categoriesPage + 1 . $categoryQuery; ?>" class="page-link" style="font-size:12px;">التالي</a>
                        <?php endif; ?>
                    </div>
                    <div style="text-align:center; color:#64748b; font-size:11px; padding:4px 0 10px;">صفحة <?php echo $categoriesPage; ?> من <?php echo $totalCategoryPages; ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal" id="deleteCategoryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
        <div class="modal-content">
            <div class="modal-header" style="background: #fee2e2; border-bottom: 1px solid #fecaca;">
                <h5 class="modal-title" style="color: #991b1b;">⚠️ تأكيد حذف القسم</h5>
                <button type="button" class="btn-close" onclick="closeDeleteModal()"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="category_id" id="deleteCatId" value="">
                <div class="modal-body" style="text-align: center; padding: 25px;">
                    <div style="font-size: 40px; margin-bottom: 10px;">🗑️</div>
                    <div style="font-size: 15px; color: #1e293b; margin-bottom: 8px;">
                        هل أنت متأكد من حذف قسم المنيو:
                    </div>
                    <div id="deleteCatName" style="font-size: 17px; font-weight: 800; color: #dc2626; margin-bottom: 12px;"></div>
                    <div style="font-size: 12px; color: #64748b;">
                        لا يمكن التراجع عن هذا الإجراء.
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
function confirmDeleteCategory(id, name, count) {
    if (count > 0) {
        alert('لا يمكن حذف القسم لأنه يحتوي على ' + count + ' صنف. يرجى نقل الأصناف أو تعديلها أولاً.');
        return;
    }
    document.getElementById('deleteCatId').value = id;
    document.getElementById('deleteCatName').innerText = name;
    document.getElementById('deleteCategoryModal').style.display = 'flex';
}

function closeDeleteModal() {
    document.getElementById('deleteCategoryModal').style.display = 'none';
}

window.onclick = function(e) {
    if (e.target == document.getElementById('deleteCategoryModal')) {
        closeDeleteModal();
    }
}
</script>

<?php include '../../includes/footer.php'; ?>
