<?php
/**
 * Warehouses Management - Edit Warehouse
 * تعديل بيانات مخزن
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';

requirePermission('warehouses.manage');

$id = (int)($_GET['id'] ?? 0);
$warehouse = getRow("SELECT * FROM warehouses WHERE id = ?", [$id]);

if (!$warehouse) {
    setError('المخزن المطلوب غير موجود');
    redirect('index.php');
}

$pageTitle = 'تعديل مخزن: ' . $warehouse['name'];
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
        $exists = getRow("SELECT id FROM warehouses WHERE code = ? AND id != ?", [$code, $id]);
        if ($exists) {
            $errors[] = 'كود المخزن مستخدم بالفعل لمخزن آخر';
        }
    }

    // Cannot deactivate if it's default
    if ($isDefault === 1 && $isActive === 0) {
        $errors[] = 'لا يمكن تعطيل المخزن إذا كان هو المخزن الافتراضي للنظام';
    }

    // Ensure at least one default warehouse exists in system
    if ((int)$warehouse['is_default'] === 1 && $isDefault === 0) {
        $otherDefault = getRow("SELECT id FROM warehouses WHERE is_default = 1 AND id != ?", [$id]);
        if (!$otherDefault) {
            $errors[] = 'يجب أن يكون هناك مخزن افتراضي واحد على الأقل في النظام. حدد مخزناً آخر كافتراضي أولاً.';
        }
    }

    if (empty($errors)) {
        // If this warehouse is marked as default, unset other defaults
        if ($isDefault === 1) {
            execute("UPDATE warehouses SET is_default = 0 WHERE id != ?", [$id]);
        }

        execute(
            "UPDATE warehouses SET name = ?, code = ?, location = ?, phone = ?, manager_name = ?, 
             is_default = ?, is_active = ?, notes = ? WHERE id = ?",
            [$name, $code, $location, $phone, $managerName, $isDefault, $isActive, $notes, $id]
        );

        logActivity('تعديل مخزن', "تم تعديل بيانات المخزن: {$name} (كود: {$code})");
        setSuccess('تم تحديث بيانات المخزن بنجاح');
        redirect('index.php');
    }
}

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <div>
            <h1 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <span>✏️</span> تعديل بيانات المخزن: <?php echo htmlspecialchars($warehouse['name']); ?>
            </h1>
            <p style="color: var(--text-secondary); margin: 5px 0 0 0; font-size: 14px;">تحديث بيانات المخزن، الحالة، والمسؤول</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="stock.php?warehouse_id=<?php echo $id; ?>" class="btn btn-info" style="display: inline-flex; align-items: center; gap: 6px;">
                <span>📦</span> رصيد المخزن
            </a>
            <a href="index.php" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px;">
                <span>🔙</span> العودة للمخازن
            </a>
        </div>
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
            🏢 تعديل بيانات المخزن #<?php echo $warehouse['id']; ?>
        </div>
        <div class="card-body" style="padding: 25px;">
            <form method="POST" action="">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 20px;">
                    <!-- Name -->
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">اسم المخزن <span style="color: red;">*</span></label>
                        <input type="text" name="name" class="form-control" required 
                               value="<?php echo htmlspecialchars($_POST['name'] ?? $warehouse['name']); ?>">
                    </div>

                    <!-- Code -->
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">كود المخزن</label>
                        <input type="text" name="code" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['code'] ?? $warehouse['code']); ?>">
                    </div>

                    <!-- Location -->
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">الموقع / العنوان</label>
                        <input type="text" name="location" class="form-control" 
                               value="<?php echo htmlspecialchars((string)($_POST['location'] ?? $warehouse['location'])); ?>">
                    </div>

                    <!-- Manager Name -->
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">مسؤول المخزن / أمين المخزن</label>
                        <input type="text" name="manager_name" class="form-control" 
                               value="<?php echo htmlspecialchars((string)($_POST['manager_name'] ?? $warehouse['manager_name'])); ?>">
                    </div>

                    <!-- Phone -->
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">رقم الهاتف</label>
                        <input type="text" name="phone" class="form-control" 
                               value="<?php echo htmlspecialchars((string)($_POST['phone'] ?? $warehouse['phone'])); ?>">
                    </div>
                </div>

                <!-- Settings & Status Checkboxes -->
                <div style="background: var(--bg-primary); padding: 15px 20px; border-radius: 10px; margin-bottom: 20px;">
                    <div style="font-weight: 700; margin-bottom: 12px;">⚙️ الإعدادات والحالة</div>
                    <div style="display: flex; gap: 30px; flex-wrap: wrap;">
                        <?php 
                            $currActive = isset($_POST['name']) ? isset($_POST['is_active']) : ((int)$warehouse['is_active'] === 1);
                            $currDefault = isset($_POST['name']) ? isset($_POST['is_default']) : ((int)$warehouse['is_default'] === 1);
                        ?>
                        <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600;">
                            <input type="checkbox" name="is_active" value="1" <?php echo $currActive ? 'checked' : ''; ?> style="width: 18px; height: 18px;">
                            <span>مخزن نشط (متاح للعمليات والفواتير)</span>
                        </label>

                        <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600;">
                            <input type="checkbox" name="is_default" value="1" <?php echo $currDefault ? 'checked' : ''; ?> style="width: 18px; height: 18px;">
                            <span>تعيين كمخزن رئيسي افتراضي للنظام ⭐</span>
                        </label>
                    </div>
                </div>

                <!-- Notes -->
                <div class="form-group" style="margin-bottom: 25px;">
                    <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">ملاحظات إضافية</label>
                    <textarea name="notes" class="form-control" rows="3"><?php echo htmlspecialchars($_POST['notes'] ?? $warehouse['notes']); ?></textarea>
                </div>

                <!-- Submit Button -->
                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <a href="index.php" class="btn btn-secondary">إلغاء</a>
                    <button type="submit" class="btn btn-primary" style="padding: 10px 30px; font-weight: 700; font-size: 15px;">
                        💾 حفظ التعديلات
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
