<?php
/**
 * Warehouses Management - Add Warehouse
 * إضافة مخزن جديد
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';

requirePermission('warehouses.manage');

$pageTitle = 'إضافة مخزن جديد';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $managerName = trim($_POST['manager_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $isDefault = isset($_POST['is_default']) ? 1 : 0;
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $notes = trim($_POST['notes'] ?? '');

    // Validation
    if (empty($name)) {
        $errors[] = 'يرجى إدخال اسم المخزن';
    }

    if (!empty($code)) {
        $exists = getRow("SELECT id FROM warehouses WHERE code = ?", [$code]);
        if ($exists) {
            $errors[] = 'كود المخزن مستخدم بالفعل لمخزن آخر';
        }
    } else {
        // Auto generate code if empty
        $lastId = getRow("SELECT MAX(id) as max_id FROM warehouses");
        $nextNum = ($lastId && $lastId['max_id']) ? ((int)$lastId['max_id'] + 1) : 1;
        $code = 'WH-' . str_pad($nextNum, 2, '0', STR_PAD_LEFT);
    }

    if (empty($errors)) {
        // If this warehouse is set as default, unset existing default
        if ($isDefault === 1) {
            execute("UPDATE warehouses SET is_default = 0");
        }

        $warehouseId = insert(
            "INSERT INTO warehouses (name, code, location, phone, manager_name, is_default, is_active, notes) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$name, $code, $location, $phone, $managerName, $isDefault, $isActive, $notes]
        );

        if ($warehouseId) {
            // Initialize zero stock for all existing products in this new warehouse
            $products = getRows("SELECT id FROM products");
            foreach ($products as $p) {
                insert(
                    "INSERT INTO warehouse_stock (warehouse_id, product_id, quantity) VALUES (?, ?, 0) 
                     ON DUPLICATE KEY UPDATE id = id",
                    [$warehouseId, $p['id']]
                );
            }

            logActivity('إضافة مخزن', "تم إضافة مخزن جديد: {$name} (كود: {$code})");
            setSuccess('تمت إضافة المخزن بنجاح');
            redirect('index.php');
        } else {
            $errors[] = 'حدث خطأ أثناء حفظ بيانات المخزن في قاعدة البيانات';
        }
    }
}

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <div>
            <h1 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <span>➕</span> إضافة مخزن جديد
            </h1>
            <p style="color: var(--text-secondary); margin: 5px 0 0 0; font-size: 14px;">أدخل بيانات المخزن الجديد لتخصيصه وإدارته في النظام</p>
        </div>
        <a href="index.php" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px;">
            <span>🔙</span> العودة للمخازن
        </a>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger" style="margin-bottom: 20px;">
        <ul style="margin: 0; padding-right: 20px;">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="card" style="max-width: 800px; margin: 0 auto;">
        <div class="card-header" style="background: linear-gradient(135deg, #0284c7, #0369a1); color: white; font-weight: 700;">
            🏢 نموذج تسجيل مخزن جديد
        </div>
        <div class="card-body" style="padding: 25px;">
            <form method="POST" action="">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 20px;">
                    <!-- Name -->
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">اسم المخزن <span style="color: red;">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="مثال: مخزن فيصل، مخزن المعادي" required 
                               value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                    </div>

                    <!-- Code -->
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">كود المخزن (اختياري)</label>
                        <input type="text" name="code" class="form-control" placeholder="مثال: WH-02 (يتم التوليد تلقائياً إن ترك فارغاً)" 
                               value="<?php echo htmlspecialchars($_POST['code'] ?? ''); ?>">
                    </div>

                    <!-- Location -->
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">الموقع / العنوان</label>
                        <input type="text" name="location" class="form-control" placeholder="مثال: 15 شارع الهرم، الجيزة" 
                               value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>">
                    </div>

                    <!-- Manager Name -->
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">مسؤول المخزن / أمين المخزن</label>
                        <input type="text" name="manager_name" class="form-control" placeholder="اسم أمين المخزن المسؤول" 
                               value="<?php echo htmlspecialchars($_POST['manager_name'] ?? ''); ?>">
                    </div>

                    <!-- Phone -->
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">رقم الهاتف</label>
                        <input type="text" name="phone" class="form-control" placeholder="رقم هاتف المخزن أو المسؤول" 
                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Settings & Status Checkboxes -->
                <div style="background: var(--bg-primary); padding: 15px 20px; border-radius: 10px; margin-bottom: 20px;">
                    <div style="font-weight: 700; margin-bottom: 12px;">⚙️ الإعدادات والحالة</div>
                    <div style="display: flex; gap: 30px; flex-wrap: wrap;">
                        <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600;">
                            <input type="checkbox" name="is_active" value="1" <?php echo (!isset($_POST['name']) || isset($_POST['is_active'])) ? 'checked' : ''; ?> style="width: 18px; height: 18px;">
                            <span>مخزن نشط (متاح للعمليات والفواتير)</span>
                        </label>

                        <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600;">
                            <input type="checkbox" name="is_default" value="1" <?php echo isset($_POST['is_default']) ? 'checked' : ''; ?> style="width: 18px; height: 18px;">
                            <span>تعيين كمخزن رئيسي افتراضي للنظام ⭐</span>
                        </label>
                    </div>
                </div>

                <!-- Notes -->
                <div class="form-group" style="margin-bottom: 25px;">
                    <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">ملاحظات إضافية</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="أي ملاحظات حول المخزن، مواعيد العمل، السعة..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                </div>

                <!-- Submit Button -->
                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <a href="index.php" class="btn btn-secondary">إلغاء</a>
                    <button type="submit" class="btn btn-primary" style="padding: 10px 30px; font-weight: 700; font-size: 15px;">
                        💾 حفظ المخزن
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
