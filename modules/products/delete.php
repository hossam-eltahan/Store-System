<?php
/**
 * Delete Product Confirmation
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';

$pageTitle = 'تأكيد حذف الصنف';

$id = $_GET['id'] ?? 0;
$product = getRow("SELECT * FROM products WHERE id = ?", [$id]);

if (!$product) {
    setError('الصنف غير موجود');
    redirect('index.php');
}

// Handle confirmed delete
if (isset($_POST['confirm_delete'])) {
    // Delete image if exists
    if ($product['image']) {
        deleteImage($product['image']);
    }
    
    execute("DELETE FROM products WHERE id = ?", [$id]);
    logActivity('حذف منتج', "تم حذف المنتج: {$product['name']}");
    setSuccess('تم حذف الصنف بنجاح');
    redirect('index.php');
}

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header">⚠️ تأكيد الحذف</div>
        <div class="card-body">
            <div class="alert alert-warning" style="text-align: center; padding: 30px;">
                <div style="font-size: 48px; margin-bottom: 20px;">🗑️</div>
                <h2 style="margin-bottom: 20px;">هل أنت متأكد من حذف هذا الصنف؟</h2>
                <div style="background: #fff; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                    <p><strong>كود الصنف:</strong> <?php echo $product['code']; ?></p>
                    <p><strong>اسم الصنف:</strong> <?php echo $product['name']; ?></p>
                    <p><strong>الكمية في المخزون:</strong> <?php echo $product['stock_quantity']; ?> <?php echo $product['unit']; ?></p>
                </div>
                <p style="color: #dc2626; font-weight: bold;">⚠️ هذا الإجراء لا يمكن التراجع عنه!</p>
            </div>
            
            <div class="d-flex gap-2" style="justify-content: center; margin-top: 20px;">
                <form method="POST" style="display: inline;">
                    <button type="submit" name="confirm_delete" class="btn btn-danger btn-lg">🗑️ نعم، احذف الصنف</button>
                </form>
                <a href="index.php" class="btn btn-secondary btn-lg">❌ إلغاء</a>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
