<?php
/**
 * Edit Supplier
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';

$pageTitle = 'تعديل بيانات مورد';

$id = $_GET['id'] ?? 0;
$supplier = getRow("SELECT * FROM suppliers WHERE id = ?", [$id]);

if (!$supplier) {
    setError('المورد غير موجود');
    redirect('index.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address'] ?? '');
    $companyName = sanitize($_POST['company_name'] ?? '');
    $visitDays = isset($_POST['visit_days']) ? json_encode($_POST['visit_days'], JSON_UNESCAPED_UNICODE) : '[]';
    $expectedProducts = sanitize($_POST['expected_products'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');
    
    // Check if phone exists for other suppliers
    $existing = getRow("SELECT id FROM suppliers WHERE phone = ? AND id != ?", [$phone, $id]);
    if ($existing) {
        setError('رقم الهاتف مسجل لمورد آخر');
    } else {
        execute(
            "UPDATE suppliers SET name = ?, phone = ?, address = ?, company_name = ?, visit_days = ?, expected_products = ?, notes = ? WHERE id = ?",
            [$name, $phone, $address, $companyName, $visitDays, $expectedProducts, $notes, $id]
        );
        
        logActivity('تعديل مورد', "تم تعديل بيانات المورد: $name");
        setSuccess('تم تعديل بيانات المورد بنجاح');
        redirect('index.php');
    }
}

$currentDays = json_decode($supplier['visit_days'] ?? '[]', true);
if (!is_array($currentDays)) $currentDays = [];

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header">✏️ تعديل بيانات المورد: <?php echo $supplier['name']; ?></div>
        <div class="card-body">
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">اسم المورد</label>
                        <input type="text" name="name" class="form-control" value="<?php echo $supplier['name']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">رقم التليفون</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo $supplier['phone']; ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">اسم الشركة</label>
                        <input type="text" name="company_name" class="form-control" value="<?php echo $supplier['company_name'] ?? ''; ?>" placeholder="اختياري">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">العنوان</label>
                        <input type="text" name="address" class="form-control" value="<?php echo $supplier['address'] ?? ''; ?>" placeholder="اختياري">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">أيام الزيارة الأسبوعية</label>
                    <div class="d-flex gap-2" style="flex-wrap: wrap;">
                        <?php 
                        $days = ['السبت', 'الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة'];
                        foreach ($days as $day): 
                            $checked = in_array($day, $currentDays) ? 'checked' : '';
                        ?>
                        <label style="display: flex; align-items: center; gap: 5px; cursor: pointer; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                            <input type="checkbox" name="visit_days[]" value="<?php echo $day; ?>" <?php echo $checked; ?>>
                            <span><?php echo $day; ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">البضاعة المتوقعة</label>
                    <input type="text" name="expected_products" class="form-control" value="<?php echo $supplier['expected_products'] ?? ''; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">ملاحظات</label>
                    <textarea name="notes" class="form-control" rows="3"><?php echo $supplier['notes'] ?? ''; ?></textarea>
                </div>
                
                <div class="d-flex gap-2 mt-2">
                    <button type="submit" class="btn btn-primary btn-lg">💾 حفظ التعديلات</button>
                    <a href="index.php" class="btn btn-secondary btn-lg">❌ إلغاء</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
