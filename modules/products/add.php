<?php
/**
 * Add Product
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requirePermission('products.add');

$pageTitle = 'إضافة صنف جديد';

// Generate auto product code
function generateProductCode() {
    $lastProduct = getRow("SELECT code FROM products WHERE code LIKE 'PRD-%' ORDER BY id DESC LIMIT 1");
    if ($lastProduct) {
        $lastNumber = intval(str_replace('PRD-', '', $lastProduct['code']));
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    return 'PRD-' . str_pad($newNumber, 5, '0', STR_PAD_LEFT);
}

$autoCode = generateProductCode();

// Get existing units for datalist
$existingUnits = getRows("SELECT DISTINCT unit FROM products ORDER BY unit");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = sanitize($_POST['code']);
    $name = sanitize($_POST['name']);
    $unit = sanitize($_POST['unit']);
    $price = floatval($_POST['price']);
    $wholesalePrice = !empty($_POST['wholesale_price']) ? floatval($_POST['wholesale_price']) : null;
    $costPrice = !empty($_POST['cost_price']) ? floatval($_POST['cost_price']) : null;
    $stockQuantity = intval($_POST['stock_quantity']);
    $initialWarehouseId = (int)($_POST['warehouse_id'] ?? getCurrentWarehouseId());
    $minStockLevel = intval($_POST['min_stock_level']);
    $description = sanitize($_POST['description'] ?? '');
    
    // Check if code exists
    $existing = getRow("SELECT id FROM products WHERE code = ?", [$code]);
    if ($existing) {
        setError('كود الصنف موجود مسبقاً');
    }
    
    // Check if exact name exists
    $existingName = getRow("SELECT id FROM products WHERE name = ?", [$name]);
    if ($existingName) {
        setError('يوجد صنف بنفس الاسم بالضبط، يمكنك تعديل الاسم قليلاً');
    }
    
    if (!isset($_SESSION['error'])) {
        // Handle image upload
        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $imagePath = uploadImage($_FILES['image'], 'products');
            if (!$imagePath) {
                setError('فشل رفع الصورة');
            }
        }
        
        if (!isset($_SESSION['error'])) {
            $id = insert(
                "INSERT INTO products (code, name, unit, price, wholesale_price, cost_price, stock_quantity, min_stock_level, description, image) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$code, $name, $unit, $price, $wholesalePrice, $costPrice, $stockQuantity, $minStockLevel, $description, $imagePath]
            );
            
            if ($id) {
                // Initialize stock across all warehouses
                $allWhs = getAllWarehouses(false);
                foreach ($allWhs as $wh) {
                    $qty = ((int)$wh['id'] === $initialWarehouseId) ? $stockQuantity : 0;
                    insert(
                        "INSERT INTO warehouse_stock (warehouse_id, product_id, quantity, min_stock_level) VALUES (?, ?, ?, ?) 
                         ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)",
                        [$wh['id'], $id, $qty, $minStockLevel]
                    );
                }
                syncProductTotalStock($id);

                logActivity('إضافة منتج', "تم إضافة المنتج: $name (كود: $code)");
                setSuccess('تم إضافة الصنف بنجاح');
                redirect('index.php');
            } else {
                setError('حدث خطأ أثناء الحفظ');
            }
        }
    }
}

$warehouses = getAllWarehouses(true);
$userWarehouseId = getCurrentWarehouseId();

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header">➕ إضافة صنف جديد</div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">كود الصنف</label>
                        <input type="text" name="code" class="form-control" value="<?php echo $autoCode; ?>" required readonly style="background-color: #f0f0f0;">
                        <small>كود تلقائي فريد للصنف</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">اسم الصنف</label>
                        <input type="text" name="name" class="form-control" placeholder="مثال: مقشات" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">الوحدة</label>
                        <input type="text" name="unit" class="form-control" list="unitsList" placeholder="اختر أو اكتب وحدة جديدة" required>
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
                        <input type="number" step="0.01" name="price" class="form-control" placeholder="0.00" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">سعر الجملة</label>
                        <input type="number" step="0.01" name="wholesale_price" class="form-control" placeholder="0.00">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">سعر الشراء (التكلفة)</label>
                        <input type="number" step="0.01" name="cost_price" class="form-control" placeholder="0.00">
                        <small>لحساب الأرباح</small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">الكمية في المخزون الأولي</label>
                        <input type="number" name="stock_quantity" class="form-control" value="0" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">🏢 المخزن للرصيد الأولي</label>
                        <select name="warehouse_id" class="form-control" style="font-weight: 600;">
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="<?php echo $wh['id']; ?>" <?php echo ((int)$wh['id'] === (int)$userWarehouseId) ? 'selected' : ''; ?>>
                                    🏢 <?php echo htmlspecialchars($wh['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>المخزن الذي ستضاف له هذه الكمية</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">الحد الأدنى للمخزون</label>
                        <input type="number" name="min_stock_level" class="form-control" value="5" required>
                        <small>سيتم التنبيه عند الوصول لهذا الحد</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">وصف الصنف</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="وصف تفصيلي للصنف..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">صورة الصنف</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                    <small>الحد الأقصى: 5 ميجابايت - الصيغ المدعومة: JPG, PNG, GIF</small>
                </div>
                
                <div class="d-flex gap-2 mt-2">
                    <button type="submit" class="btn btn-primary btn-lg">💾 حفظ الصنف</button>
                    <a href="index.php" class="btn btn-secondary btn-lg">❌ إلغاء</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
