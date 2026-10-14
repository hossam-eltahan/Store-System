<?php
/**
 * Product Modifiers (إدارة الإضافات والصوصات)
 * نظام كافيه عروسة النيل
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
require_once '../../config/categories.php';
requirePermission('products.view');

$pageTitle = 'إدارة الإضافات والصوصات';

// Handle Add / Edit / Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $name = sanitize(trim($_POST['name'] ?? ''));
        $price = floatval($_POST['price'] ?? 0);
        $modifierGroup = sanitize(trim($_POST['modifier_group'] ?? 'extra'));
        $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $isAvailable = isset($_POST['is_available']) ? 1 : 0;

        if (empty($name)) {
            setError('يرجى إدخال اسم الإضافة.');
        } else {
            if ($action === 'create') {
                insert(
                    "INSERT INTO product_modifiers (name, modifier_group, price, applies_to_category_id, is_available) VALUES (?, ?, ?, ?, ?)",
                    [$name, $modifierGroup, $price, $categoryId, $isAvailable]
                );
                logActivity('إضافة تعديل', "تم إضافة الإضافة: $name");
                setSuccess('تم إضافة الإضافة/الصوص بنجاح');
            } else {
                $id = (int)($_POST['modifier_id'] ?? 0);
                execute(
                    "UPDATE product_modifiers SET name = ?, modifier_group = ?, price = ?, applies_to_category_id = ?, is_available = ? WHERE id = ?",
                    [$name, $modifierGroup, $price, $categoryId, $isAvailable, $id]
                );
                logActivity('تعديل إضافة', "تم تعديل الإضافة: $name");
                setSuccess('تم تحديث الإضافة بنجاح');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['modifier_id'] ?? 0);
        if ($id > 0) {
            // Check if referenced in order_item_modifiers
            $isUsed = getRow("SELECT id FROM order_item_modifiers WHERE modifier_id = ? LIMIT 1", [$id]);
            if ($isUsed) {
                // If referenced in previous orders, soft-disable it instead of foreign key failure
                execute("UPDATE product_modifiers SET is_available = 0 WHERE id = ?", [$id]);
                setSuccess('تم إلغاء تفعيل الإضافة وحجبها من الكاشير لوجود فواتير سابقة مرتبطة بها.');
            } else {
                execute("DELETE FROM product_modifiers WHERE id = ?", [$id]);
                setSuccess('تم حذف الإضافة بنجاح');
            }
        }
    }
    redirect('modifiers.php');
}

// Search and filter handling
$search = trim($_GET['search'] ?? '');
$filterGroup = trim($_GET['group'] ?? '');
$filterCategory = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

$whereClause = "WHERE 1=1";
$params = [];

if ($search !== '') {
    $whereClause .= " AND m.name LIKE ?";
    $params[] = "%$search%";
}

if ($filterGroup !== '') {
    $whereClause .= " AND m.modifier_group = ?";
    $params[] = $filterGroup;
}

if ($filterCategory > 0) {
    $whereClause .= " AND (m.applies_to_category_id = ? OR m.applies_to_category_id IS NULL)";
    $params[] = $filterCategory;
}

$categories = getRows("SELECT id, name FROM categories ORDER BY sort_order, name");
$modifiers = getRows("
    SELECT m.*, c.name as category_name 
    FROM product_modifiers m 
    LEFT JOIN categories c ON c.id = m.applies_to_category_id 
    $whereClause
    ORDER BY m.display_order ASC, m.id DESC
", $params);

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container-fluid" style="padding: 15px 25px;">
    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
        <div>
            <h2 style="font-weight: 800; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 8px;">
                <span>🍯</span> إدارة الإضافات والنكهات والصوصات
            </h2>
            <p style="color: #64748b; margin: 5px 0 0;">إضافة وتعديل صوصات ونكهات المشروبات والحلويات المتاحة في الكاشير</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="../../index.php" class="btn btn-secondary" style="border-radius: 8px;">🏠 الرئيسية</a>
            <a href="index.php" class="btn btn-secondary" style="border-radius: 8px;">📋 العودة للأصناف</a>
            <button class="btn btn-primary" onclick="openAddModal()" style="border-radius: 8px; font-weight: 700; background: #ea580c; border-color: #ea580c;">
                ➕ إضافة صوص / نكهة جديدة
            </button>
        </div>
    </div>

    <!-- Filter & Search Form -->
    <div class="card" style="border-radius: 12px; border: none; box-shadow: 0 2px 4px rgba(0,0,0,0.04); margin-bottom: 20px;">
        <div class="card-body" style="padding: 16px;">
            <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
                <div style="flex: 1; min-width: 250px;">
                    <input type="text" name="search" class="form-control" placeholder="🔍 بحث باسم الصوص أو النكهة..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div style="width: 180px;">
                    <select name="group" class="form-select">
                        <option value="">كل الأنواع</option>
                        <option value="sauce" <?php echo $filterGroup === 'sauce' ? 'selected' : ''; ?>>صوص (Sauce)</option>
                        <option value="flavor" <?php echo $filterGroup === 'flavor' ? 'selected' : ''; ?>>نكهة سيرب (Flavor)</option>
                        <option value="extra" <?php echo $filterGroup === 'extra' ? 'selected' : ''; ?>>إضافة عامة (Extra)</option>
                        <option value="topping" <?php echo $filterGroup === 'topping' ? 'selected' : ''; ?>>توبينج (Topping)</option>
                    </select>
                </div>
                <div style="width: 200px;">
                    <select name="category_id" class="form-select">
                        <option value="">كل الأقسام</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $filterCategory == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-secondary" style="border-radius: 8px;">تصفية</button>
                    <?php if ($search || $filterGroup || $filterCategory): ?>
                        <a href="modifiers.php" class="btn btn-outline-danger" style="border-radius: 8px;">إلغاء الفلتر</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Modifiers Table -->
    <div class="card" style="border-radius: 12px; border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table table-hover" style="margin: 0;">
                    <thead style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                        <tr>
                            <th style="padding: 14px 20px;">اسم الإضافة / الصوص</th>
                            <th>النوع</th>
                            <th>السعر الإضافي</th>
                            <th>مخصص لقسم</th>
                            <th>الحالة</th>
                            <th style="text-align: left; padding-left: 20px;">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($modifiers)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; color: #94a3b8;">
                                    لا توجد إضافات تطابق معايير البحث. اضغط "➕ إضافة صوص / نكهة جديدة" للبدء.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($modifiers as $mod): ?>
                                <tr style="vertical-align: middle;">
                                    <td style="padding: 14px 20px; font-weight: 700; color: #1e293b;">
                                        ✨ <?php echo htmlspecialchars($mod['name']); ?>
                                    </td>
                                    <td>
                                        <span class="badge" style="background: #e0e7ff; color: #3730a3; padding: 4px 10px; border-radius: 6px;">
                                            <?php 
                                            $grpNames = ['sauce' => 'صوص', 'flavor' => 'سيرب/نكهة', 'extra' => 'إضافة عامة', 'topping' => 'توبينج'];
                                            echo $grpNames[$mod['modifier_group']] ?? htmlspecialchars($mod['modifier_group']); 
                                            ?>
                                        </span>
                                    </td>
                                    <td style="font-weight: 800; color: #059669;">
                                        + <?php echo formatCurrency($mod['price']); ?>
                                    </td>
                                    <td>
                                        <?php if ($mod['category_name']): ?>
                                            <span class="badge" style="background: #fef3c7; color: #92400e; padding: 4px 10px; border-radius: 6px;">
                                                <?php echo htmlspecialchars($mod['category_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #64748b; font-size: 13px;">لكل الأصناف</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($mod['is_available']): ?>
                                            <span class="badge" style="background: #dcfce7; color: #166534; padding: 4px 8px; border-radius: 6px;">متوفر</span>
                                        <?php else: ?>
                                            <span class="badge" style="background: #fee2e2; color: #991b1b; padding: 4px 8px; border-radius: 6px;">غير متوفر</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: left; padding-left: 20px;">
                                        <button class="btn btn-sm btn-outline-primary" onclick='editModifier(<?php echo json_encode($mod); ?>)' style="border-radius: 6px;">
                                            ✏️ تعديل
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick='confirmDeleteModifier(<?php echo $mod['id']; ?>, <?php echo json_encode($mod['name']); ?>)' style="border-radius: 6px;">
                                            🗑️ حذف
                                        </button>
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

<!-- Modal 1: Add/Edit Modifier -->
<div class="modal" id="modifierModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">إضافة صوص / إضافة جديدة</h5>
                <button type="button" class="btn-close" onclick="closeModal()"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" id="modalAction" value="create">
                <input type="hidden" name="modifier_id" id="modalModifierId" value="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600;">اسم الإضافة / النكهة / الصوص *</label>
                        <input type="text" name="name" id="modalName" class="form-control" placeholder="مثال: صوص لوتس، زيادة حليب، نكهة كراميل" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600;">السعر الإضافي (ج.م) *</label>
                            <input type="number" step="0.5" name="price" id="modalPrice" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600;">النوع</label>
                            <select name="modifier_group" id="modalGroup" class="form-select">
                                <option value="sauce">صوص (Sauce)</option>
                                <option value="flavor">نكهة سيرب (Flavor)</option>
                                <option value="extra">إضافة عامة (Extra)</option>
                                <option value="topping">توبينج (Topping)</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600;">مخصصة لقسم معين (اختياري)</label>
                        <select name="category_id" id="modalCategory" class="form-select">
                            <option value="">كل الأقسام</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" name="is_available" id="modalAvailable" value="1" checked style="width: 18px; height: 18px; cursor: pointer;">
                        <label for="modalAvailable" style="margin: 0; font-weight: 600; cursor: pointer;">متوفر للطلب في الكاشير</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">إلغاء</button>
                    <button type="submit" class="btn btn-primary" style="background: #ea580c; border-color: #ea580c;">💾 حفظ التغييرات</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: Delete Confirmation Modal -->
<div class="modal" id="deleteModifierModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
        <div class="modal-content">
            <div class="modal-header" style="background: #fee2e2; border-bottom: 1px solid #fecaca;">
                <h5 class="modal-title" style="color: #991b1b;">⚠️ تأكيد الحذف</h5>
                <button type="button" class="btn-close" onclick="closeDeleteModal()"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="modifier_id" id="deleteModId" value="">
                <div class="modal-body" style="text-align: center; padding: 25px;">
                    <div style="font-size: 40px; margin-bottom: 10px;">🗑️</div>
                    <div style="font-size: 15px; color: #1e293b; margin-bottom: 8px;">
                        هل أنت متأكد من حذف الصوص / الإضافة:
                    </div>
                    <div id="deleteModName" style="font-size: 17px; font-weight: 800; color: #dc2626; margin-bottom: 12px;"></div>
                    <div style="font-size: 12px; color: #64748b;">
                        إذا كانت هذه الإضافة مستخدمة في فواتير سابقة، سيتم تعطيلها تلقائياً بدلاً من حذفها للحفاظ على سلامة الحسابات.
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
    document.getElementById('modalTitle').innerText = '➕ إضافة صوص / نكهة جديدة';
    document.getElementById('modalAction').value = 'create';
    document.getElementById('modalModifierId').value = '';
    document.getElementById('modalName').value = '';
    document.getElementById('modalPrice').value = '5.00';
    document.getElementById('modalGroup').value = 'sauce';
    document.getElementById('modalCategory').value = '';
    document.getElementById('modalAvailable').checked = true;
    document.getElementById('modifierModal').style.display = 'flex';
}

function editModifier(mod) {
    document.getElementById('modalTitle').innerText = '✏️ تعديل الإضافة: ' + mod.name;
    document.getElementById('modalAction').value = 'update';
    document.getElementById('modalModifierId').value = mod.id;
    document.getElementById('modalName').value = mod.name;
    document.getElementById('modalPrice').value = mod.price;
    document.getElementById('modalGroup').value = mod.modifier_group || 'sauce';
    document.getElementById('modalCategory').value = mod.applies_to_category_id || '';
    document.getElementById('modalAvailable').checked = parseInt(mod.is_available) === 1;
    document.getElementById('modifierModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('modifierModal').style.display = 'none';
}

function confirmDeleteModifier(id, name) {
    document.getElementById('deleteModId').value = id;
    document.getElementById('deleteModName').innerText = name;
    document.getElementById('deleteModifierModal').style.display = 'flex';
}

function closeDeleteModal() {
    document.getElementById('deleteModifierModal').style.display = 'none';
}

window.onclick = function(event) {
    if (event.target == document.getElementById('modifierModal')) {
        closeModal();
    }
    if (event.target == document.getElementById('deleteModifierModal')) {
        closeDeleteModal();
    }
}
</script>

<?php include '../../includes/footer.php'; ?>
