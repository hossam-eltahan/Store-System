<?php
/**
 * Edit Product
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/categories.php';
require_once '../../config/auth.php';
requirePermission('products.edit');

$pageTitle = 'تعديل صنف';

$id = $_GET['id'] ?? 0;
$product = getRow("SELECT * FROM products WHERE id = ?", [$id]);

if (!$product) {
    setError('الصنف غير موجود');
    redirect('index.php');
}

// Get existing units for datalist
$existingUnits = getRows("SELECT DISTINCT unit FROM products ORDER BY unit");
$categories = getCategoriesForSelect();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = sanitize($_POST['code']);
    $name = sanitize($_POST['name']);
    $unit = sanitize($_POST['unit']);
    $price = floatval($_POST['price']);
    $wholesalePrice = !empty($_POST['wholesale_price']) ? floatval($_POST['wholesale_price']) : null;
    $costPrice = !empty($_POST['cost_price']) ? floatval($_POST['cost_price']) : null;
    $stockQuantity = intval($_POST['stock_quantity']);
    $minStockLevel = intval($_POST['min_stock_level']);
    $description = sanitize($_POST['description'] ?? '');
    $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;

    // Check if code exists for other products
    $existing = getRow("SELECT id FROM products WHERE code = ? AND id != ?", [$code, $id]);
    if ($existing) {
        setError('كود الصنف مستخدم لمنتج آخر');
    } else {
        // Handle image removal
        $imagePath = $product['image'];
        if (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
            if ($product['image']) {
                deleteImage($product['image']);
            }
            $imagePath = null;
        }
        // Handle new image upload
        elseif (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            // Delete old image
            if ($product['image']) {
                deleteImage($product['image']);
            }
            $imagePath = uploadImage($_FILES['image'], 'products');
        }

        if (!isset($_SESSION['error'])) {
            execute(
                "UPDATE products SET
                 category_id = ?, code = ?, name = ?, unit = ?, price = ?, wholesale_price = ?, cost_price = ?,
                 min_stock_level = ?, description = ?, image = ?
                 WHERE id = ?",
                [$categoryId, $code, $name, $unit, $price, $wholesalePrice, $costPrice, $minStockLevel, $description, $imagePath, $id]
            );

            // Update per-warehouse stock if provided
            if (isset($_POST['warehouse_stocks']) && is_array($_POST['warehouse_stocks'])) {
                foreach ($_POST['warehouse_stocks'] as $whId => $qty) {
                    updateWarehouseStock((int)$whId, $id, max(0, (int)$qty), 'set');
                }
            } else {
                syncProductTotalStock($id);
            }

            logActivity('تعديل منتج', "تم تعديل المنتج: $name (كود: $code)");
            setSuccess('تم تعديل الصنف بنجاح');
            redirect('index.php');
        }
    }
}

// Get warehouse stock for this product
$warehouseStocks = getRows(
    "SELECT w.id as warehouse_id, w.name as warehouse_name, COALESCE(ws.quantity, 0) as quantity
     FROM warehouses w
     LEFT JOIN warehouse_stock ws ON ws.warehouse_id = w.id AND ws.product_id = ?
     WHERE w.is_active = 1
     ORDER BY w.is_default DESC, w.name ASC",
    [$id]
);

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header">✏️ تعديل صنف: <?php echo $product['name']; ?></div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">كود الصنف</label>
                        <input type="text" name="code" class="form-control" value="<?php echo $product['code']; ?>" required readonly style="background-color: #f0f0f0;">
                    </div>

                    <div class="form-group">
                        <label class="form-label required">اسم الصنف</label>
                        <input type="text" name="name" class="form-control" value="<?php echo $product['name']; ?>" required>
                    </div>


                    <div class="form-group">
                        <label class="form-label">الفئة</label>
                        <select name="category_id" class="form-control">
                            <option value="">بدون فئة</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo (int)$product['category_id'] === (int)$category['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($category['display_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">الوحدة</label>
                        <input type="text" name="unit" class="form-control" list="unitsList" value="<?php echo $product['unit']; ?>" placeholder="اختر أو اكتب وحدة جديدة" required>
                        <datalist id="unitsList">
                            <option value="قطعة">
                            <option value="طقم">
                            <option value="عبوة">
                            <option value="كرتونة">
                            <option value="كيلو">
                            <?php foreach ($existingUnits as $u): ?>
                            <?php if (!in_array($u['unit'], ['قطعة', 'طقم', 'عبوة', 'كرتونة', 'كيلو'])): ?>
                            <option value="<?php echo htmlspecialchars($u['unit']); ?>">
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </datalist>
                        <small>يمكنك اختيار وحدة موجودة أو كتابة وحدة جديدة</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">سعر البيع</label>
                        <input type="number" step="0.01" name="price" class="form-control" value="<?php echo $product['price']; ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">سعر الجملة</label>
                        <input type="number" step="0.01" name="wholesale_price" class="form-control" value="<?php echo $product['wholesale_price']; ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">سعر الشراء (التكلفة)</label>
                        <input type="number" step="0.01" name="cost_price" class="form-control" value="<?php echo $product['cost_price']; ?>">
                    </div>
                </div>

                <!-- Per-Warehouse Stock Management -->
                <div style="background: var(--bg-primary); padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #e2e8f0;">
                    <label class="form-label" style="font-weight: 700; margin-bottom: 10px; display: block; color: #1e3a8a;">🏢 رصيد الصنف في المخازن:</label>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <?php foreach ($warehouseStocks as $ws): ?>
                            <div>
                                <label style="font-size: 0.85em; font-weight: 600; display: block; margin-bottom: 4px;">
                                    🏢 <?php echo htmlspecialchars($ws['warehouse_name']); ?>:
                                </label>
                                <input type="number" name="warehouse_stocks[<?php echo $ws['warehouse_id']; ?>]" class="form-control" value="<?php echo $ws['quantity']; ?>" min="0" style="font-weight: 700;">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <small style="color: var(--text-secondary); display: block; margin-top: 10px;">
                        إجمالي رصيد الصنف الحالي: <strong><?php echo $product['stock_quantity']; ?></strong> قطعة (يتم تحديث الإجمالي تلقائياً كمجموع أرصدة المخازن)
                    </small>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">الحد الأدنى للمخزون للتنبيه</label>
                        <input type="number" name="min_stock_level" class="form-control" value="<?php echo $product['min_stock_level']; ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">وصف الصنف</label>
                    <textarea name="description" class="form-control" rows="3"><?php echo $product['description']; ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">صورة الصنف</label>
                    <?php if ($product['image']): ?>
                        <div class="image-preview-container mb-1">
                            <img src="../../assets/uploads/<?php echo $product['image']; ?>" alt="<?php echo $product['name']; ?>" style="max-height: 100px; border-radius: 5px;">
                            <label class="remove-image-label">
                                <input type="checkbox" name="remove_image" value="1" id="removeImage">
                                <span class="btn btn-danger btn-sm">🗑️ حذف الصورة</span>
                            </label>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="image" class="form-control" accept="image/*" id="newImageInput">
                    <small>اتركها فارغة إذا لم ترد تغيير الصورة</small>
                </div>

                <style>
                .image-preview-container {
                    display: flex;
                    align-items: center;
                    gap: 1rem;
                }
                .remove-image-label {
                    cursor: pointer;
                }
                .remove-image-label input[type="checkbox"] {
                    display: none;
                }
                .remove-image-label input[type="checkbox"]:checked + .btn {
                    background: #16a34a;
                }
                .remove-image-label input[type="checkbox"]:checked + .btn::before {
                    content: '✓ ';
                }
                </style>

                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var removeCheckbox = document.getElementById('removeImage');
                    var newImageInput = document.getElementById('newImageInput');
                    if (removeCheckbox && newImageInput) {
                        removeCheckbox.addEventListener('change', function() {
                            if (this.checked) {
                                newImageInput.disabled = true;
                                newImageInput.value = '';
                            } else {
                                newImageInput.disabled = false;
                            }
                        });
                    }
                });
                </script>

                <div class="d-flex gap-2 mt-2">
                    <button type="submit" class="btn btn-primary btn-lg">💾 حفظ التعديلات</button>
                    <a href="index.php" class="btn btn-secondary btn-lg">❌ إلغاء</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
