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
    requirePermission('products.edit');
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
        execute("DELETE FROM product_modifiers WHERE id = ?", [$id]);
        setSuccess('تم حذف الإضافة بنجاح');
    }
    redirect('modifiers.php');
}

$categories = getRows("SELECT id, name FROM categories ORDER BY sort_order, name");
$modifiers = getRows("
    SELECT m.*, c.name as category_name 
    FROM product_modifiers m 
    LEFT JOIN categories c ON c.id = m.applies_to_category_id 
    ORDER BY m.display_order ASC, m.id DESC
");

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container-fluid" style="padding: 15px 25px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h2 style="font-weight: 800; color: #1e293b; margin: 0;">🍯 إدارة الإضافات والنكهات والصوصات</h2>
            <p style="color: #64748b; margin: 5px 0 0;">إضافة وتعديل صوصات ونكهات المشروبات والحلويات المتاحة في الكاشير</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="index.php" class="btn btn-secondary">📋 العودة لقائمة الأصناف</a>
            <button class="btn btn-primary" onclick="openAddModal()">➕ إضافة صوص / إضافة جديدة</button>
        </div>
    </div>

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
                                    لا توجد إضافات مسجلة حالياً. اضغط "إضافة صوص / إضافة جديدة" للبدء.
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
                                            <?php echo htmlspecialchars($mod['modifier_group']); ?>
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
                                        <button class="btn btn-sm btn-outline-primary" onclick='editModifier(<?php echo json_encode($mod); ?>)'>✏️ تعديل</button>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('هل أنت متأكد من حذف هذه الإضافة؟');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="modifier_id" value="<?php echo $mod['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">🗑️ حذف</button>
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

<!-- Modal -->
<div class="modal" id="modifierModal" tabindex="-1" style="display: none; background: rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle" style="font-weight: 700;">إضافة صوص / إضافة جديدة</h5>
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
                                <option value="extra">إضافة عامة (Extra)</option>
                                <option value="sauce">صوص (Sauce)</option>
                                <option value="flavor">نكهة سيرب (Flavor)</option>
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
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="is_available" id="modalAvailable" checked>
                        <label class="form-check-label" for="modalAvailable">متوفر للطلب في الكاشير</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">إلغاء</button>
                    <button type="submit" class="btn btn-primary">💾 حفظ التغييرات</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('modalTitle').innerText = 'إضافة صوص / إضافة جديدة';
    document.getElementById('modalAction').value = 'create';
    document.getElementById('modalModifierId').value = '';
    document.getElementById('modalName').value = '';
    document.getElementById('modalPrice').value = '5.00';
    document.getElementById('modalGroup').value = 'extra';
    document.getElementById('modalCategory').value = '';
    document.getElementById('modalAvailable').checked = true;
    document.getElementById('modifierModal').style.display = 'block';
}

function editModifier(mod) {
    document.getElementById('modalTitle').innerText = 'تعديل الإضافة';
    document.getElementById('modalAction').value = 'update';
    document.getElementById('modalModifierId').value = mod.id;
    document.getElementById('modalName').value = mod.name;
    document.getElementById('modalPrice').value = mod.price;
    document.getElementById('modalGroup').value = mod.modifier_group || 'extra';
    document.getElementById('modalCategory').value = mod.applies_to_category_id || '';
    document.getElementById('modalAvailable').checked = parseInt(mod.is_available) === 1;
    document.getElementById('modifierModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('modifierModal').style.display = 'none';
}

window.onclick = function(event) {
    if (event.target == document.getElementById('modifierModal')) {
        closeModal();
    }
}
</script>

<?php include '../../includes/footer.php'; ?>
