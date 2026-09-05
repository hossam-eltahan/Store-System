<?php
/**
 * Settings Page
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';

$pageTitle = 'إعدادات النظام';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Handle logo removal via checkbox
    if (isset($_POST['remove_logo']) && $_POST['remove_logo'] === '1') {
        $oldLogo = getSetting('store_logo');
        if ($oldLogo) {
            deleteImage($oldLogo);
            updateSetting('store_logo', '');
            logActivity('إزالة اللوجو', 'تم إزالة شعار المحل');
        }
    }
    
    $storeName = sanitize($_POST['store_name']);
    $storeAddress = sanitize($_POST['store_address']);
    $storePhone = sanitize($_POST['store_phone']);
    $storePhone2 = sanitize($_POST['store_phone2'] ?? '');
    $currency = sanitize($_POST['currency']);
    $enableStockAlerts = isset($_POST['enable_stock_alerts']) ? '1' : '0';
    $enableSoundAlerts = isset($_POST['enable_sound_alerts']) ? '1' : '0';
    $allowEditInvoices = isset($_POST['allow_edit_invoices']) ? '1' : '0';
    $allowDeleteInvoices = isset($_POST['allow_delete_invoices']) ? '1' : '0';
    
    // Handle logo upload
    if (isset($_FILES['store_logo']) && $_FILES['store_logo']['error'] === UPLOAD_ERR_OK) {
        // Delete old logo
        $oldLogo = getSetting('store_logo');
        if ($oldLogo) {
            deleteImage($oldLogo);
        }
        
        $logoPath = uploadImage($_FILES['store_logo'], 'logo');
        if ($logoPath) {
            updateSetting('store_logo', $logoPath);
        }
    }
    
    // Update settings
    updateSetting('store_name', $storeName);
    updateSetting('store_address', $storeAddress);
    updateSetting('store_phone', $storePhone);
    updateSetting('store_phone2', $storePhone2);
    updateSetting('currency', $currency);
    updateSetting('enable_stock_alerts', $enableStockAlerts);
    updateSetting('enable_sound_alerts', $enableSoundAlerts);
    updateSetting('allow_edit_invoices', $allowEditInvoices);
    updateSetting('allow_delete_invoices', $allowDeleteInvoices);
    
    logActivity('تحديث الإعدادات', 'تم تحديث إعدادات النظام');
    setSuccess('تم حفظ الإعدادات بنجاح');
    redirect('index.php');
}

$settings = getAllSettings();

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<style>
.container { padding: 0 !important; }
.card-header { padding: 8px 12px !important; font-size: 0.95em !important; }
.card-body { padding: 12px !important; }
.form-group { margin-bottom: 8px !important; }
.form-label { font-size: 0.85em !important; margin-bottom: 3px !important; }
.form-control { padding: 6px 10px !important; font-size: 0.9em !important; }
h3 { font-size: 1em !important; margin: 0 0 5px 0 !important; color: #7c3aed; }
hr { margin: 5px 0 10px 0 !important; }
.settings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
.settings-section { background: #f9fafb; padding: 12px; border-radius: 8px; }
.remove-logo-label input[type="checkbox"]:checked + .btn {
    background: #16a34a;
}
.remove-logo-label input[type="checkbox"]:checked + .btn::before {
    content: '✓ ';
}
</style>

<div class="container">
    <div class="card">
        <div class="card-header" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white;">⚙️ إعدادات النظام</div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="settings-grid">
                <div class="settings-section">
                <h3>🏪 معلومات المحل</h3>
                <hr>
                
                <div class="form-group">
                    <label class="form-label required">اسم المحل</label>
                    <input type="text" name="store_name" class="form-control" 
                           value="<?php echo htmlspecialchars($settings['store_name'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">عنوان المحل</label>
                    <input type="text" name="store_address" class="form-control" 
                           value="<?php echo htmlspecialchars($settings['store_address'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">رقم التليفون الأول</label>
                    <input type="text" name="store_phone" class="form-control" 
                           value="<?php echo htmlspecialchars($settings['store_phone'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">رقم التليفون الثاني (اختياري)</label>
                    <input type="text" name="store_phone2" class="form-control" 
                           value="<?php echo htmlspecialchars($settings['store_phone2'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">شعار المحل</label>
                    <?php if (!empty($settings['store_logo'])): ?>
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <img src="../../assets/uploads/<?php echo $settings['store_logo']; ?>" 
                                 alt="Logo" style="max-width: 50px; max-height: 40px; object-fit: contain; border: 1px solid #ddd; border-radius: 5px;">
                            <label class="remove-logo-label" style="cursor: pointer;">
                                <input type="checkbox" name="remove_logo" value="1" style="display: none;">
                                <span class="btn btn-danger btn-sm">🗑️ حذف اللوجو</span>
                            </label>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="store_logo" class="form-control" accept="image/*">
                </div>
                </div>
                
                <!-- إعدادات عامة -->
                <div class="settings-section">
                <h3>⚙️ إعدادات عامة</h3>
                <hr>
                
                <div class="form-group">
                    <label class="form-label">العملة</label>
                    <input type="text" name="currency" class="form-control" 
                           value="<?php echo htmlspecialchars($settings['currency'] ?? 'جنيه'); ?>">
                </div>
                
                <div class="form-group" style="margin-top: 10px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.85em;">
                        <input type="checkbox" name="enable_stock_alerts" value="1" 
                               <?php echo ($settings['enable_stock_alerts'] ?? '1') == '1' ? 'checked' : ''; ?>>
                        <span>تنبيهات المخزون</span>
                    </label>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.85em;">
                        <input type="checkbox" name="enable_sound_alerts" value="1" 
                               <?php echo ($settings['enable_sound_alerts'] ?? '1') == '1' ? 'checked' : ''; ?>>
                        <span>التنبيهات الصوتية</span>
                    </label>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.85em;">
                        <input type="checkbox" name="allow_edit_invoices" value="1" 
                               <?php echo ($settings['allow_edit_invoices'] ?? '0') == '1' ? 'checked' : ''; ?>>
                        <span>تفعيل زر (تعديل) للفواتير</span>
                    </label>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.85em;">
                        <input type="checkbox" name="allow_delete_invoices" value="1" 
                               <?php echo ($settings['allow_delete_invoices'] ?? '0') == '1' ? 'checked' : ''; ?>>
                        <span>تفعيل زر (مسح) للفواتير</span>
                    </label>
                </div>
                
                <div style="margin-top: 15px; display: flex; gap: 8px;">
                    <button type="submit" class="btn btn-primary">💾 حفظ</button>
                    <a href="backup.php" class="btn btn-success">💾 نسخ احتياطي</a>
                </div>
                </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
