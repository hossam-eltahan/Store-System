<?php
/**
 * Edit Product / Menu Item
 * نظام كافيه عروسة النيل
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/categories.php';
require_once '../../config/auth.php';
requirePermission('products.edit');

$pageTitle = 'تعديل صنف المنيو';

$id = (int)($_GET['id'] ?? 0);
$product = getRow("SELECT * FROM products WHERE id = ?", [$id]);

if (!$product) {
    setError('الصنف المطلوب غير موجود');
    redirect('index.php');
}

$existingVariants = getRows("SELECT * FROM product_variants WHERE product_id = ? ORDER BY display_order ASC, id ASC", [$id]);
$categories = getRows("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = sanitize(trim($_POST['code'] ?? ''));
    $name = sanitize(trim($_POST['name'] ?? ''));
    $unit = sanitize(trim($_POST['unit'] ?? 'طلب'));
    $price = floatval($_POST['price'] ?? 0);
    $costPrice = floatval($_POST['cost_price'] ?? 0);
    $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $prepTime = intval($_POST['preparation_time_min'] ?? 5);
    $isAvailable = isset($_POST['is_available']) ? 1 : 0;
    $hasVariants = isset($_POST['has_variants']) ? 1 : 0;
    $description = sanitize(trim($_POST['description'] ?? ''));

    if (empty($name)) {
        setError('يرجى كتابة اسم الصنف');
    } else {
        // Check uniqueness of code
        $codeCheck = getRow("SELECT id FROM products WHERE code = ? AND id != ?", [$code, $id]);
        if ($codeCheck) {
            setError('كود الصنف مستخدم لصنف آخر');
        } else {
            // Handle image upload
            $imagePath = $product['image'];
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $newImage = uploadImage($_FILES['image'], 'products');
                if ($newImage) {
                    if ($imagePath && file_exists('../../' . $imagePath)) {
                        @unlink('../../' . $imagePath);
                    }
                    $imagePath = $newImage;
                }
            }

            // Check variants
            $variants = [];
            if ($hasVariants && isset($_POST['variant_names']) && is_array($_POST['variant_names'])) {
                $varNames = $_POST['variant_names'];
                $varPrices = $_POST['variant_prices'] ?? [];
                $defaultIndex = intval($_POST['default_variant'] ?? 0);

                foreach ($varNames as $idx => $vName) {
                    $vNameClean = trim($vName);
                    if ($vNameClean !== '') {
                        $vPrice = floatval($varPrices[$idx] ?? 0);
                        $isDef = ($idx === $defaultIndex) ? 1 : 0;
                        $variants[] = [
                            'name' => $vNameClean,
                            'price' => $vPrice,
                            'is_default' => $isDef
                        ];
                    }
                }

                if (empty($variants)) {
                    $hasVariants = 0;
                } else {
                    foreach ($variants as $v) {
                        if ($v['is_default']) {
                            $price = $v['price'];
                            break;
                        }
                    }
                }
            }

            execute("
                UPDATE products 
                SET category_id = ?, code = ?, name = ?, unit = ?, price = ?, cost_price = ?,
                    is_available = ?, has_variants = ?, preparation_time_min = ?, description = ?, image = ?
                WHERE id = ?
            ", [$categoryId, $code, $name, $unit, $price, $costPrice, $isAvailable, $hasVariants, $prepTime, $description, $imagePath, $id]);

            // Sync variants in DB
            execute("DELETE FROM product_variants WHERE product_id = ?", [$id]);
            if ($hasVariants && !empty($variants)) {
                foreach ($variants as $idx => $v) {
                    insert("
                        INSERT INTO product_variants (product_id, variant_name, final_price, is_default, display_order)
                        VALUES (?, ?, ?, ?, ?)
                    ", [$id, $v['name'], $v['price'], $v['is_default'], $idx + 1]);
                }
            }

            logActivity('تعديل صنف', "تم تعديل بيانات صنف: $name (كود: $code)");
            setSuccess('تم تحديث بيانات الصنف بنجاح!');
            redirect('index.php');
        }
    }
}

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container" style="max-width: 900px; padding: 25px 15px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <div>
            <h2 style="font-weight: 800; color: #1e293b; margin: 0;">✏️ تعديل صنف: <?php echo htmlspecialchars($product['name']); ?></h2>
            <p style="color: #64748b; margin: 5px 0 0;">تعديل الأسعار والأحجام والتفاصيل المتاحة للكاشير</p>
        </div>
        <a href="index.php" class="btn btn-secondary">العودة للمنيو</a>
    </div>

    <form method="POST" enctype="multipart/form-data">
        <!-- Main Info Card -->
        <div class="card" style="border-radius: 12px; border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 20px;">
            <div class="card-header" style="background: #f8fafc; font-weight: 700; border-bottom: 1px solid #e2e8f0;">
                📝 المعلومات الأساسية
            </div>
            <div class="card-body" style="padding: 25px;">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label" style="font-weight: 600;">كود الصنف *</label>
                        <input type="text" name="code" class="form-control" value="<?php echo htmlspecialchars($product['code']); ?>" required>
                    </div>
                    <div class="col-md-8 mb-3">
                        <label class="form-label" style="font-weight: 600;">اسم الصنف *</label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" style="font-weight: 600;">القسم / التصنيف *</label>
                        <select name="category_id" class="form-select" required>
                            <option value="">-- اختر القسم --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo ($product['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['icon'] ?? '☕'); ?> <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label" style="font-weight: 600;">الوحدة</label>
                        <input type="text" name="unit" class="form-control" value="<?php echo htmlspecialchars($product['unit'] ?? 'طلب'); ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label" style="font-weight: 600;">وقت التحضير (دقائق)</label>
                        <input type="number" name="preparation_time_min" class="form-control" value="<?php echo htmlspecialchars($product['preparation_time_min'] ?? '5'); ?>" min="1">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" style="font-weight: 600;">سعر التكلفة التقديري (ج.م)</label>
                        <input type="number" step="0.5" name="cost_price" class="form-control" value="<?php echo htmlspecialchars($product['cost_price'] ?? '0.00'); ?>">
                    </div>
                    <div class="col-md-6 mb-3" id="singlePriceWrapper" style="<?php echo ($product['has_variants'] || !empty($existingVariants)) ? 'opacity: 0.5;' : ''; ?>">
                        <label class="form-label" style="font-weight: 600;">سعر البيع الأساسي (ج.م) *</label>
                        <input type="number" step="0.5" name="price" id="basePriceInput" class="form-control" value="<?php echo htmlspecialchars($product['price']); ?>">
                        <small style="color: #64748b;">السعر الأساسي الموحد</small>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" style="font-weight: 600;">صورة الصنف</label>
                        <?php if (!empty($product['image']) && file_exists('../../' . $product['image'])): ?>
                            <div style="margin-bottom: 8px;">
                                <img src="../../<?php echo htmlspecialchars($product['image']); ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                            </div>
                        <?php endif; ?>
                        <input type="file" name="image" class="form-control" accept="image/*">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" style="font-weight: 600;">ملاحظات / وصف</label>
                        <input type="text" name="description" class="form-control" value="<?php echo htmlspecialchars($product['description'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" name="is_available" id="isAvailableCheck" <?php echo $product['is_available'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="isAvailableCheck" style="font-weight: 600;">متوفر للبيع الآن في الكاشير</label>
                </div>
            </div>
        </div>

        <!-- Variants Section Card -->
        <?php $hasVarsInitial = ($product['has_variants'] == 1 || !empty($existingVariants)); ?>
        <div class="card" style="border-radius: 12px; border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 25px;">
            <div class="card-header" style="background: #f8fafc; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0;">
                <div style="font-weight: 700;">
                    📏 أحجام وخيارات الصنف (سنجل / دبل، صغير / وسط / كبير)
                </div>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" name="has_variants" id="hasVariantsToggle" onchange="toggleVariantsSection()" <?php echo $hasVarsInitial ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="hasVariantsToggle" style="font-weight: 600;">تفعيل الأحجام المتعددة</label>
                </div>
            </div>
            <div class="card-body" id="variantsBody" style="<?php echo $hasVarsInitial ? 'display: block;' : 'display: none;'; ?> padding: 25px;">
                <p style="color: #64748b; font-size: 13px; margin-bottom: 15px;">
                    يمكنك تعديل أسعار الأحجام أو إضافة أحجام جديدة أو حذف أحجام قديمة:
                </p>

                <div class="table-responsive">
                    <table class="table" id="variantsTable">
                        <thead>
                            <tr style="background: #f1f5f9;">
                                <th>اسم الحجم / الخيار</th>
                                <th>السعر (ج.م)</th>
                                <th style="text-align: center;">الافتراضي</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="variantsList">
                            <?php if (!empty($existingVariants)): ?>
                                <?php foreach ($existingVariants as $vIdx => $v): ?>
                                    <tr>
                                        <td><input type="text" name="variant_names[]" class="form-control" value="<?php echo htmlspecialchars($v['variant_name']); ?>" required></td>
                                        <td><input type="number" step="0.5" name="variant_prices[]" class="form-control" value="<?php echo htmlspecialchars($v['final_price']); ?>" required></td>
                                        <td style="text-align: center; vertical-align: middle;">
                                            <input type="radio" name="default_variant" value="<?php echo $vIdx; ?>" <?php echo $v['is_default'] ? 'checked' : ''; ?>>
                                        </td>
                                        <td>
                                            <?php if ($vIdx > 0): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeVariantRow(this)">✕</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td><input type="text" name="variant_names[]" class="form-control" placeholder="مثال: سنجل" value="سنجل"></td>
                                    <td><input type="number" step="0.5" name="variant_prices[]" class="form-control" placeholder="0.00" value="<?php echo htmlspecialchars($product['price']); ?>"></td>
                                    <td style="text-align: center; vertical-align: middle;">
                                        <input type="radio" name="default_variant" value="0" checked>
                                    </td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td><input type="text" name="variant_names[]" class="form-control" placeholder="مثال: دبل" value="دبل"></td>
                                    <td><input type="number" step="0.5" name="variant_prices[]" class="form-control" placeholder="0.00" value="<?php echo htmlspecialchars($product['price'] + 10); ?>"></td>
                                    <td style="text-align: center; vertical-align: middle;">
                                        <input type="radio" name="default_variant" value="1">
                                    </td>
                                    <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeVariantRow(this)">✕</button></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <button type="button" class="btn btn-outline-primary btn-sm" onclick="addVariantRow()">
                    ➕ إضافة حجم / خيار آخر
                </button>
            </div>
        </div>

        <div style="display: flex; gap: 15px; justify-content: flex-end;">
            <a href="index.php" class="btn btn-secondary btn-lg" style="padding: 10px 25px;">إلغاء</a>
            <button type="submit" class="btn btn-primary btn-lg" style="padding: 10px 35px; font-weight: 700; background: #6F4E37; border-color: #6F4E37;">
                💾 حفظ التعديلات
            </button>
        </div>
    </form>
</div>

<script>
function toggleVariantsSection() {
    const isChecked = document.getElementById('hasVariantsToggle').checked;
    const body = document.getElementById('variantsBody');
    const singlePrice = document.getElementById('singlePriceWrapper');

    if (isChecked) {
        body.style.display = 'block';
        singlePrice.style.opacity = '0.5';
    } else {
        body.style.display = 'none';
        singlePrice.style.opacity = '1';
    }
}

function addVariantRow() {
    const list = document.getElementById('variantsList');
    const count = list.children.length;
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><input type="text" name="variant_names[]" class="form-control" placeholder="اسم الحجم" required></td>
        <td><input type="number" step="0.5" name="variant_prices[]" class="form-control" placeholder="0.00" required></td>
        <td style="text-align: center; vertical-align: middle;">
            <input type="radio" name="default_variant" value="${count}">
        </td>
        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeVariantRow(this)">✕</button></td>
    `;
    list.appendChild(tr);
}

function removeVariantRow(btn) {
    const tr = btn.closest('tr');
    tr.remove();
}
</script>

<?php include '../../includes/footer.php'; ?>
