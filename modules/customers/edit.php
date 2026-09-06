<?php
/**
 * Edit Customer
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requirePermission('customers.edit');

$pageTitle = 'تعديل بيانات عميل';

$id = $_GET['id'] ?? 0;
$customer = getRow("SELECT * FROM customers WHERE id = ?", [$id]);

if (!$customer) {
    setError('العميل غير موجود');
    redirect('index.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $notes = sanitize($_POST['notes']);
    
    // Check if phone exists for other customers
    $existing = getRow("SELECT id FROM customers WHERE phone = ? AND id != ?", [$phone, $id]);
    if ($existing) {
        setError('رقم الهاتف مسجل لعميل آخر');
    } else {
        execute(
            "UPDATE customers SET name = ?, phone = ?, address = ?, notes = ? WHERE id = ?",
            [$name, $phone, $address, $notes, $id]
        );
        
        logActivity('تعديل عميل', "تم تعديل بيانات العميل: $name");
        setSuccess('تم تعديل بيانات العميل بنجاح');
        redirect('index.php');
    }
}

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header">✏️ تعديل بيانات العميل: <?php echo $customer['name']; ?></div>
        <div class="card-body">
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">اسم العميل</label>
                        <input type="text" name="name" class="form-control" value="<?php echo $customer['name']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">رقم التليفون</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo $customer['phone']; ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">العنوان</label>
                    <input type="text" name="address" class="form-control" value="<?php echo $customer['address']; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">ملاحظات</label>
                    <textarea name="notes" class="form-control" rows="3"><?php echo $customer['notes']; ?></textarea>
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
