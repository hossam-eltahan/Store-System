<?php
/**
 * Add Customer
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';

$pageTitle = 'إضافة عميل جديد';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $notes = sanitize($_POST['notes']);
    
    // Check if phone exists
    $existing = getRow("SELECT id FROM customers WHERE phone = ?", [$phone]);
    if ($existing) {
        setError('رقم الهاتف مسجل لعميل آخر');
    } else {
        $id = insert(
            "INSERT INTO customers (name, phone, address, notes) VALUES (?, ?, ?, ?)",
            [$name, $phone, $address, $notes]
        );
        
        if ($id) {
            logActivity('إضافة عميل', "تم إضافة العميل: $name");
            setSuccess('تم إضافة العميل بنجاح');
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
        <div class="card-header">👤 إضافة عميل جديد</div>
        <div class="card-body">
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">اسم العميل</label>
                        <input type="text" name="name" class="form-control" placeholder="الاسم ثلاثي" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">رقم التليفون</label>
                        <input type="text" name="phone" class="form-control" placeholder="01xxxxxxxxx" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">العنوان</label>
                    <input type="text" name="address" class="form-control" placeholder="العنوان بالتفصيل">
                </div>
                
                <div class="form-group">
                    <label class="form-label">ملاحظات</label>
                    <textarea name="notes" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="d-flex gap-2 mt-2">
                    <button type="submit" class="btn btn-primary btn-lg">💾 حفظ العميل</button>
                    <a href="index.php" class="btn btn-secondary btn-lg">❌ إلغاء</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
