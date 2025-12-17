<?php
/**
 * Add Supplier
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';

$pageTitle = 'إضافة مورد جديد';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address'] ?? '');
    $companyName = sanitize($_POST['company_name'] ?? '');
    $visitDays = isset($_POST['visit_days']) ? json_encode($_POST['visit_days'], JSON_UNESCAPED_UNICODE) : '[]';
    $expectedProducts = sanitize($_POST['expected_products'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');
    
    // Check if phone exists
    $existing = getRow("SELECT id FROM suppliers WHERE phone = ?", [$phone]);
    if ($existing) {
        setError('رقم الهاتف مسجل لمورد آخر');
    } else {
        $id = insert(
            "INSERT INTO suppliers (name, phone, address, company_name, visit_days, expected_products, notes) VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$name, $phone, $address, $companyName, $visitDays, $expectedProducts, $notes]
        );
        
        if ($id) {
            logActivity('إضافة مورد', "تم إضافة المورد: $name");
            setSuccess('تم إضافة المورد بنجاح');
            redirect('index.php');
        } else {
            setError('حدث خطأ أثناء الحفظ');
        }
    }
}

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header">🚚 إضافة مورد جديد</div>
        <div class="card-body">
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">اسم المورد</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">رقم التليفون</label>
                        <input type="text" name="phone" class="form-control" placeholder="01xxxxxxxxx" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">اسم الشركة</label>
                        <input type="text" name="company_name" class="form-control" placeholder="اختياري">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">العنوان</label>
                        <input type="text" name="address" class="form-control" placeholder="اختياري">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">أيام الزيارة الأسبوعية</label>
                    <div class="d-flex gap-2" style="flex-wrap: wrap;">
                        <?php 
                        $days = ['السبت', 'الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة'];
                        foreach ($days as $day): 
                        ?>
                        <label style="display: flex; align-items: center; gap: 5px; cursor: pointer; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                            <input type="checkbox" name="visit_days[]" value="<?php echo $day; ?>">
                            <span><?php echo $day; ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">البضاعة المتوقعة</label>
                    <input type="text" name="expected_products" class="form-control" placeholder="مثال: أدوات بلاستيكية، منظفات...">
                </div>
                
                <div class="form-group">
                    <label class="form-label">ملاحظات</label>
                    <textarea name="notes" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="d-flex gap-2 mt-2">
                    <button type="submit" class="btn btn-primary btn-lg">💾 حفظ المورد</button>
                    <a href="index.php" class="btn btn-secondary btn-lg">❌ إلغاء</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
