<?php
/**
 * Add Product / Menu Item
 * نظام كافيه عروسة النيل
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/categories.php';
require_once '../../config/auth.php';
requirePermission('products.add');

$pageTitle = 'إضافة صنف جديد للمنيو';

// Auto-generate code
function generateProductCode() {
    $lastProduct = getRow("SELECT code FROM products WHERE code LIKE 'PRD-%' ORDER BY id DESC LIMIT 1");
    if ($lastProduct) {
        $lastNumber = intval(str_replace('PRD-', '', $lastProduct['code']));
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    return 'PRD-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
}

$autoCode = generateProductCode();
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

    if (empty($code)) {
        $code = generateProductCode();
    }

    if (empty($name)) {
        setError('يرجى كتابة اسم الصنف');
    } else {
        // Check uniqueness
        $existing = getRow("SELECT id FROM products WHERE code = ?", [$code]);
        if ($existing) {
            setError('كود الصنف مسجل مسبقاً، يرجى اختيار كود آخر');
        } else {
            // Handle image upload
            $imagePath = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $imagePath = uploadImage($_FILES['image'], 'products');
            }

            // If variants enabled, check variant inputs
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
                    // Update base price from default variant
                    foreach ($variants as $v) {
                        if ($v['is_default']) {
                            $price = $v['price'];
                            break;
                        }
                    }
                }
            }

            $productId = insert("
                INSERT INTO products (category_id, code, name, unit, price, cost_price, is_available, has_variants, preparation_time_min, description, image, stock_quantity)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 999)
            ", [$categoryId, $code, $name, $unit, $price, $costPrice, $isAvailable, $hasVariants, $prepTime, $description, $imagePath]);

            if ($productId) {
                // Insert variants if any
                if ($hasVariants && !empty($variants)) {
                    foreach ($variants as $idx => $v) {
                        insert("
                            INSERT INTO product_variants (product_id, variant_name, final_price, is_default, display_order)
                            VALUES (?, ?, ?, ?, ?)
                        ", [$productId, $v['name'], $v['price'], $v['is_default'], $idx + 1]);
                    }
                }

                // Add to warehouse_stock table for compatibility
                insert("
                    INSERT INTO warehouse_stock (warehouse_id, product_id, quantity, min_stock_level)
                    VALUES (1, ?, 999, 5)
                    ON DUPLICATE KEY UPDATE quantity = 999
                ", [$productId]);

                logActivity('إضافة صنف', "تم إضافة صنف جديد للمنيو: $name (كود: $code)");
                setSuccess('تم إضافة الصنف للمنيو بنجاح!');
                redirect('index.php');
            } else {
                setError('حدث خطأ أثناء حفظ الصنف في قاعدة البيانات');
            }
        }
    }
}

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<style>
    .edit-product-page { max-width: 1120px !important; padding: 14px 20px !important; }
    .edit-product-page .edit-page-header { margin-bottom: 14px !important; padding: 10px 0; }
    .edit-product-page .edit-page-header h2 { font-size: 19px; }
    .edit-product-page .edit-page-header p { margin-top: 2px !important; font-size: 12px; }
    .edit-product-page .edit-card { margin-bottom: 12px !important; border: 1px solid #e2e8f0 !important; box-shadow: 0 2px 5px rgba(15, 23, 42, 0.04) !important; }
    .edit-form-grid { display: grid; grid-template-columns: minmax(0, 1.08fr) minmax(0, 0.92fr); gap: 14px; align-items: start; }
    .edit-product-page .edit-card .card-header { min-height: 42px; padding: 10px 14px !important; font-size: 13px; }
    .edit-product-page .edit-card .card-body { padding: 14px !important; }
    .edit-product-page .mb-3 { margin-bottom: 8px !important; }
    .edit-product-page .form-label { margin-bottom: 3px; font-size: 12px; }
    .edit-product-page .form-control, .edit-product-page .form-select { min-height: 35px; padding: 6px 9px; font-size: 12.5px; }
    .edit-product-page .form-check { font-size: 12px; }
    .edit-product-page #variantsBody > p { margin-bottom: 8px !important; font-size: 12px !important; }
    .edit-product-page #variantsTable th, .edit-product-page #variantsTable td { padding: 5px 7px; font-size: 12px; }
    .edit-product-page .edit-actions { gap: 8px !important; margin-top: 4px; }
    .edit-product-page .edit-actions .btn { padding: 8px 18px !important; font-size: 13px; }
    @media (max-width: 768px) {
        .edit-product-page { padding: 10px 12px !important; }
        .edit-form-grid { grid-template-columns: 1fr; }
        .edit-product-page .edit-page-header h2 { font-size: 16px; }
    }
</style>

<div class="container edit-product-page">
    <div class="edit-page-header" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h2 style="font-weight: 800; color: #1e293b; margin: 0;">➕ إضافة صنف جديد للمنيو</h2>
            <p style="color: #64748b; margin: 5px 0 0;">أدخل بيانات الصنف، الأسعار، وتحديد ما إذا كان يحتوي على أحجام متعددة</p>
        </div>
        <a href="index.php" class="btn btn-secondary">العودة للمنيو</a>
    </div>

    <form method="POST" enctype="multipart/form-data">
        <div class="edit-form-grid">
        <!-- Main Info Card -->
        <div class="card edit-card" style="border-radius: 12px;">
            <div class="card-header" style="background: #f8fafc; font-weight: 700; border-bottom: 1px solid #e2e8f0;">
                📝 المعلومات الأساسية
            </div>
            <div class="card-body" style="padding: 25px;">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label" style="font-weight: 600;">كود الصنف *</label>
                        <input type="text" name="code" class="form-control" value="<?php echo htmlspecialchars($_POST['code'] ?? $autoCode); ?>" required>
                    </div>
                    <div class="col-md-8 mb-3">
                        <label class="form-label" style="font-weight: 600;">اسم الصنف (كما يظهر في الكاشير) *</label>
                        <input type="text" name="name" class="form-control" placeholder="مثال: لاتيه، كابتشينو، كانز بيبسي، مولتن كيك" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required autofocus>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" style="font-weight: 600;">القسم / التصنيف *</label>
                        <select name="category_id" class="form-select" required>
                            <option value="">-- اختر القسم --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['icon'] ?? '☕'); ?> <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label" style="font-weight: 600;">الوحدة</label>
                        <input type="text" name="unit" class="form-control" value="<?php echo htmlspecialchars($_POST['unit'] ?? 'طلب'); ?>" placeholder="طلب، كوب، كانز، زجاجة">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label" style="font-weight: 600;">وقت التحضير (دقائق)</label>
                        <input type="number" name="preparation_time_min" class="form-control" value="<?php echo htmlspecialchars($_POST['preparation_time_min'] ?? '5'); ?>" min="1">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" style="font-weight: 600;">سعر التكلفة التقديري (ج.م)</label>
                        <input type="number" step="0.5" name="cost_price" class="form-control" placeholder="0.00" value="<?php echo htmlspecialchars($_POST['cost_price'] ?? ''); ?>">
                        <small style="color: #64748b;">لحساب هامش الربح والتقارير المالية</small>
                    </div>
                    <div class="col-md-6 mb-3" id="singlePriceWrapper">
                        <label class="form-label" style="font-weight: 600;">سعر البيع (ج.م) *</label>
                        <input type="number" step="0.5" name="price" id="basePriceInput" class="form-control" placeholder="0.00" value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>">
                        <small style="color: #64748b;">السعر الأساسي الموحد للصنف</small>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" style="font-weight: 600;">صورة الصنف (اختياري)</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" style="font-weight: 600;">ملاحظات / وصف</label>
                        <input type="text" name="description" class="form-control" placeholder="مكونات، أو تفاصيل إضافية للبارستا">
                    </div>
                </div>

                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" name="is_available" id="isAvailableCheck" checked>
                    <label class="form-check-label" for="isAvailableCheck" style="font-weight: 600;">متوفر للبيع الآن في الكاشير</label>
                </div>
            </div>
        </div>

        <!-- Variants Section Card -->
        <div class="card edit-card" style="border-radius: 12px;">
            <div class="card-header" style="background: #f8fafc; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0;">
                <div style="font-weight: 700;">
                    📏 أحجام وخيارات الصنف (سنجل / دبل، صغير / وسط / كبير)
                </div>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" name="has_variants" id="hasVariantsToggle" onchange="toggleVariantsSection()">
                    <label class="form-check-label" for="hasVariantsToggle" style="font-weight: 600;">تفعيل الأحجام المتعددة</label>
                </div>
            </div>
            <div class="card-body" id="variantsBody" style="display: none; padding: 25px;">
                <p style="color: #64748b; font-size: 13px; margin-bottom: 15px;">
                    أضف الأحجام المختلفة للصنف وأسعار كل حجم. عند فتح الكاشير سيطلب من الكاشير اختيار الحجم المطلوب تلقائياً.
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
                            <tr>
                                <td><input type="text" name="variant_names[]" class="form-control" placeholder="مثال: سنجل أو صغير" value="سنجل"></td>
                                <td><input type="number" step="0.5" name="variant_prices[]" class="form-control" placeholder="0.00" value="25.00"></td>
                                <td style="text-align: center; vertical-align: middle;">
                                    <input type="radio" name="default_variant" value="0" checked>
                                </td>
                                <td></td>
                            </tr>
                            <tr>
                                <td><input type="text" name="variant_names[]" class="form-control" placeholder="مثال: دبل أو كبير" value="دبل"></td>
                                <td><input type="number" step="0.5" name="variant_prices[]" class="form-control" placeholder="0.00" value="35.00"></td>
                                <td style="text-align: center; vertical-align: middle;">
                                    <input type="radio" name="default_variant" value="1">
                                </td>
                                <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeVariantRow(this)">✕</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <button type="button" class="btn btn-outline-primary btn-sm" onclick="addVariantRow()">
                    ➕ إضافة حجم / خيار آخر
                </button>
            </div>
        </div>

        </div>

        <div class="edit-actions" style="display: flex; justify-content: flex-end;">
            <a href="index.php" class="btn btn-secondary btn-lg" style="padding: 10px 25px;">إلغاء</a>
            <button type="submit" class="btn btn-primary btn-lg" style="padding: 10px 35px; font-weight: 700; background: #6F4E37; border-color: #6F4E37;">
                💾 حفظ الصنف
            </button>
        </div>
    </form>
</div>

<script>
function toggleVariantsSection() {
    const isChecked = document.getElementById('hasVariantsToggle').checked;
    const body = document.getElementById('variantsBody');
    const singlePrice = document.getElementById('singlePriceWrapper');
    const basePriceInput = document.getElementById('basePriceInput');

    if (isChecked) {
        body.style.display = 'block';
        singlePrice.style.opacity = '0.5';
        basePriceInput.required = false;
    } else {
        body.style.display = 'none';
        singlePrice.style.opacity = '1';
        basePriceInput.required = true;
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
