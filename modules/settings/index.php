<?php
/**
 * Settings Page
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
require_once '../../config/version.php';
requirePermission('settings.manage');

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

<!-- License & Subscription Details Card -->
<?php
require_once __DIR__ . '/../../config/license.php';
$licInfo = function_exists('getFullLicenseInfo') ? getFullLicenseInfo() : (function_exists('getLicenseDetails') ? getLicenseDetails() : []);
$licStatus = $licInfo['status'] ?? 'unlicensed';
$isDev = !empty($licInfo['is_dev']);
$isLife = !empty($licInfo['is_lifetime']);
$isTrial = !empty($licInfo['is_trial']);
$isTest = !empty($licInfo['is_test']);
$hwid = $licInfo['hwid'] ?? (function_exists('getMachineFingerprint') ? getMachineFingerprint() : '');
$code = $licInfo['code'] ?? '';
$daysLeft = (int)($licInfo['days_left'] ?? 0);
$minsLeft = (int)($licInfo['mins_left'] ?? 0);
$activatedDate = !empty($licInfo['activated_at']) ? (is_numeric($licInfo['activated_at']) ? date('Y-m-d H:i', $licInfo['activated_at']) : $licInfo['activated_at']) : 'غير مسجل';
$rawExp = $licInfo['expires_at'] ?? '';
if (is_numeric($rawExp) && $rawExp > 0) {
    $expiryDate = date('Y-m-d H:i:s', $rawExp);
} elseif (!empty($rawExp)) {
    $expiryDate = $rawExp;
} else {
    $expiryDate = $isLife ? 'مدى الحياة (دائم)' : 'غير محدد';
}
$durationLabel = $licInfo['duration_label'] ?? ($isLife ? 'مدى الحياة (دائم)' : ($daysLeft ? $daysLeft . ' يوم' : 'غير محدد'));

$statusBadgeBg = $isLife ? '#ecfdf5' : ($isDev ? '#eff6ff' : ($licStatus === 'valid' ? ($daysLeft <= 7 ? '#fff7ed' : '#f0fdf4') : '#fef2f2'));
$statusBadgeColor = $isLife ? '#059669' : ($isDev ? '#2563eb' : ($licStatus === 'valid' ? ($daysLeft <= 7 ? '#ea580c' : '#16a34a') : '#dc2626'));
$statusBadgeBorder = $isLife ? '#a7f3d0' : ($isDev ? '#bfdbfe' : ($licStatus === 'valid' ? ($daysLeft <= 7 ? '#fed7aa' : '#bbf7d0') : '#fecaca'));
$statusText = $isLife ? '💎 ترخيص دائم مدى الحياة' : ($isDev ? '🛠️ وضع المطور' : ($licStatus === 'valid' ? ($isTest ? '🧪 فترة اختبار (' . $minsLeft . ' دقيقة)' : ($isTrial ? '🎁 نسخة تجريبية (' . $daysLeft . ' يوم)' : '✅ اشتراك نشط وساري')) : '❌ منتهي الصلاحية'));
?>

<div class="card" style="margin-bottom: 20px; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.04);">
    <div class="card-header" style="background: linear-gradient(135deg, #1e293b, #0f172a); color: white; display: flex; align-items: center; justify-content: space-between; padding: 12px 18px;">
        <div style="display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 1rem;">
            <span style="font-size: 18px;">🛡️</span>
            <span>بيانات ترخيص النظام والاشتراك</span>
        </div>
        <div style="display: flex; align-items: center; gap: 10px;">
            <span style="background: <?php echo $statusBadgeBg; ?>; color: <?php echo $statusBadgeColor; ?>; border: 1px solid <?php echo $statusBadgeBorder; ?>; padding: 4px 12px; border-radius: 20px; font-size: 11.5px; font-weight: 700;">
                <?php echo $statusText; ?>
            </span>
            <a href="<?php echo BASE_URL; ?>activate.php" class="btn btn-sm" style="background: linear-gradient(135deg, #7c3aed, #6d28d9); color: white; border: none; font-size: 11.5px; font-weight: 700; padding: 5px 14px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 6px rgba(124,58,237,0.3);">
                <span>🔑</span> تجديد الاشتراك / إدخال كود جديد
            </a>
        </div>
    </div>
    <div class="card-body" style="background: #ffffff; padding: 18px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px;">
            <!-- Box 1: HWID -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px;">
                <div style="font-size: 11px; color: #64748b; margin-bottom: 5px; font-weight: 600;">معرف الجهاز الخاص بك (Hardware ID):</div>
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 6px;">
                    <code style="font-family: monospace; font-size: 13px; font-weight: 700; color: #0f172a; background: #e2e8f0; padding: 4px 8px; border-radius: 4px;"><?php echo htmlspecialchars($hwid); ?></code>
                    <button type="button" onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars($hwid); ?>'); alert('تم نسخ معرف الجهاز بنجاح!');" class="btn btn-sm" style="background: #e2e8f0; color: #334155; padding: 3px 8px; font-size: 11px; border: none; border-radius: 4px; cursor: pointer;" title="نسخ المعرف">
                        📋 نسخ
                    </button>
                </div>
            </div>

            <!-- Box 2: Duration & Status -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px;">
                <div style="font-size: 11px; color: #64748b; margin-bottom: 5px; font-weight: 600;">نوع ومدة الاشتراك:</div>
                <div style="font-size: 13.5px; font-weight: 700; color: #1e293b;">
                    <?php echo htmlspecialchars($durationLabel); ?>
                </div>
            </div>

            <!-- Box 3: Expiry Date -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px;">
                <div style="font-size: 11px; color: #64748b; margin-bottom: 5px; font-weight: 600;">موعد التجديد القادم:</div>
                <div style="font-size: 13.5px; font-weight: 700; color: <?php echo ($daysLeft <= 7 && !$isLife) ? '#dc2626' : '#059669'; ?>;">
                    <?php echo htmlspecialchars($expiryDate); ?>
                    <?php if (!$isLife && $daysLeft > 0): ?>
                        <span style="font-size: 11px; font-weight: normal; color: #64748b;">(متبقي <?php echo $daysLeft; ?> يوم)</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Box 4: Current Code -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px;">
                <div style="font-size: 11px; color: #64748b; margin-bottom: 5px; font-weight: 600;">كود التفعيل المسجل:</div>
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 6px;">
                    <code style="font-family: monospace; font-size: 11px; color: #475569; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 170px;"><?php echo htmlspecialchars($code ?: 'غير متوفر'); ?></code>
                    <?php if (!empty($code)): ?>
                    <button type="button" onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars($code); ?>'); alert('تم نسخ كود التفعيل!');" class="btn btn-sm" style="background: #e2e8f0; color: #334155; padding: 3px 8px; font-size: 11px; border: none; border-radius: 4px; cursor: pointer;" title="نسخ الكود">
                        📋 نسخ
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div style="margin-top: 14px; padding-top: 12px; border-top: 1px dashed #e2e8f0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
            <div style="font-size: 12px; color: #64748b;">
                💡 لتجديد الاشتراك أو ترقية نسختك، يمكنك التواصل مع الدعم الفني وإرسال معرف جهازك لإصدار كود التجديد فوراً.
            </div>
            <div style="display: flex; gap: 8px;">
                <a href="https://wa.me/201271191616?text=<?php echo urlencode("مرحباً، أود تجديد اشتراك نظام إدارة الأجهزة.\nمعرف جهازي: " . $hwid); ?>" target="_blank" class="btn btn-sm" style="background: #25d366; color: white; font-weight: 700; text-decoration: none; padding: 6px 14px; font-size: 12px; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 6px rgba(37,211,102,0.25);">
                    <span>💬</span> تواصل لتجديد الاشتراك (واتساب)
                </a>
                <a href="<?php echo BASE_URL; ?>activate.php" class="btn btn-sm" style="background: #7c3aed; color: white; font-weight: 700; text-decoration: none; padding: 6px 14px; font-size: 12px; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 6px rgba(124,58,237,0.25);">
                    <span>🔑</span> شاشة إدخال كود التفعيل
                </a>
            </div>
        </div>
    </div>
</div>
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
                    <a href="update.php" class="btn" style="background:#0284c7; color:white; border:none; display:inline-flex; align-items:center; gap:6px;"><span>🔄</span> تحديثات البرنامج</a>
                </div>
                </div>
                </div>
            </form>
        </div>
    </div>
</div>


    <!-- بطاقة إصدار النظام والتحديثات -->
    <div class="card" style="margin-top: 20px;">
        <div class="card-header" style="background: linear-gradient(135deg, #0284c7, #0369a1); color: white; display: flex; justify-content: space-between; align-items: center;">
            <div style="font-weight: 700;">🔄 إصدار وتحديثات النظام</div>
            <span style="background: rgba(255,255,255,0.25); padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                الإصدار الحالي: v<?php echo defined('APP_VERSION') ? APP_VERSION : '1.0.0'; ?>
            </span>
        </div>
        <div class="card-body">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                <div>
                    <h4 style="margin: 0 0 6px 0; color: #1e293b; font-size: 15px;">نظام إدارة الأجهزة المنزلية</h4>
                    <div style="font-size: 13px; color: #64748b; display: flex; gap: 12px; flex-wrap: wrap;">
                        <span>📦 رقم الإصدار: <strong style="color: #0284c7;">v<?php echo defined('APP_VERSION') ? APP_VERSION : '1.0.0'; ?></strong></span>
                        <span>📅 تاريخ البناء: <strong><?php echo defined('APP_BUILD_DATE') ? APP_BUILD_DATE : '2026-09-17'; ?></strong></span>
                        <span>🗄️ هيكل قاعدة البيانات: <strong>#<?php echo defined('DB_SCHEMA_VERSION') ? DB_SCHEMA_VERSION : '1'; ?></strong></span>
                    </div>
                </div>
                <div>
                    <a href="update.php" class="btn" style="background: #0284c7; color: white; border: none; font-weight: 700; padding: 8px 18px; border-radius: 6px; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; box-shadow: 0 2px 6px rgba(2,132,199,0.3);">
                        <span>🚀</span> فتح مركز التحديثات
                    </a>
                </div>
            </div>
        </div>
    </div>

<?php include '../../includes/footer.php'; ?>
