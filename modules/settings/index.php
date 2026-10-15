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
    $receiptHeader = sanitize($_POST['receipt_header'] ?? '');
    $receiptFooter = sanitize($_POST['receipt_footer'] ?? '');
    $psRateSingle = floatval($_POST['ps_default_rate_single'] ?? 30.0);
    $psRateMulti = floatval($_POST['ps_default_rate_multi'] ?? 40.0);
    $billiardRateHour = floatval($_POST['billiard_default_rate_hour'] ?? 40.0);
    $billiardRateGame = floatval($_POST['billiard_default_rate_game'] ?? 10.0);
    $vatRate = floatval($_POST['vat_rate'] ?? 0.0);
    $serviceCharge = floatval($_POST['service_charge'] ?? 0.0);
    $enableStockAlerts = isset($_POST['enable_stock_alerts']) ? '1' : '0';
    $enableSoundAlerts = isset($_POST['enable_sound_alerts']) ? '1' : '0';
    $allowEditInvoices = isset($_POST['allow_edit_invoices']) ? '1' : '0';
    $allowDeleteInvoices = isset($_POST['allow_delete_invoices']) ? '1' : '0';
    $preventNegativeStock = isset($_POST['prevent_negative_stock']) ? '1' : '0';
    
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
    updateSetting('receipt_header', $receiptHeader);
    updateSetting('receipt_footer', $receiptFooter);
    updateSetting('ps_default_rate_single', $psRateSingle);
    updateSetting('ps_default_rate_multi', $psRateMulti);
    updateSetting('billiard_default_rate_hour', $billiardRateHour);
    updateSetting('billiard_default_rate_game', $billiardRateGame);
    // Sync the new rates to ALL existing devices/tables so they follow Settings
    // (service modal rates in POS are read live from Settings via SVC_RATES)
    execute("UPDATE gaming_devices SET rate_single = ?, rate_multi = ?", [$psRateSingle, $psRateMulti]);
    execute("UPDATE billiard_tables SET rate_per_hour = ?, rate_per_game = ?", [$billiardRateHour, $billiardRateGame]);
    $devTotal = getRow("SELECT COUNT(*) AS c FROM gaming_devices");
    $tblTotal = getRow("SELECT COUNT(*) AS c FROM billiard_tables");
    $devTotal = (int)($devTotal['c'] ?? 0);
    $tblTotal = (int)($tblTotal['c'] ?? 0);
    updateSetting('vat_rate', $vatRate);
    updateSetting('service_charge', $serviceCharge);
    updateSetting('enable_stock_alerts', $enableStockAlerts);
    updateSetting('enable_sound_alerts', $enableSoundAlerts);
    updateSetting('allow_edit_invoices', $allowEditInvoices);
    updateSetting('allow_delete_invoices', $allowDeleteInvoices);
    updateSetting('prevent_negative_stock', $preventNegativeStock);

    // Automatic periodic backup schedule
    $autoBackupEnabled = isset($_POST['auto_backup_enabled']) ? '1' : '0';
    $autoBackupHours = (int)($_POST['auto_backup_interval_hours'] ?? 24);
    if (!in_array($autoBackupHours, [24, 72, 168], true)) {
        $autoBackupHours = 24;
    }
    $autoBackupKeep = (int)($_POST['auto_backup_keep'] ?? 7);
    $autoBackupKeep = max(1, min(30, $autoBackupKeep));
    updateSetting('auto_backup_enabled', $autoBackupEnabled);
    updateSetting('auto_backup_interval_hours', (string)$autoBackupHours);
    updateSetting('auto_backup_keep', (string)$autoBackupKeep);
    
    logActivity('تحديث الإعدادات', 'تم تحديث إعدادات النظام');
    setSuccess("تم حفظ الإعدادات بنجاح وتم تطبيق الأسعار على كل الأجهزة ({$devTotal}) والطاولات ({$tblTotal})");
    redirect('index.php');
}

$settings = getAllSettings();

include '../../includes/header.php';
include '../../includes/navbar.php';
?>
<style>
.set-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 10px; flex-wrap: wrap; }
.set-head h1 { margin: 0; font-size: 1.05rem; font-weight: 800; }
.set-grid { display: grid; grid-template-columns: minmax(0,1fr) 290px; gap: 12px; align-items: start; }
@media (max-width: 920px) { .set-grid { grid-template-columns: 1fr; } }
.set-card { border-radius: 10px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.04); margin-bottom: 12px; }
.set-card .card-header { padding: 8px 14px !important; font-size: 0.85em !important; font-weight: 800; }
.set-card .card-body { padding: 12px 14px !important; }
.sec { background: #f8fafc; padding: 10px 12px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 10px; }
.sec h3 { font-size: 13px !important; font-weight: 800; margin: 0 0 8px 0 !important; }
.sec .form-group { margin-bottom: 8px !important; }
.sec .form-label { font-size: 0.8em !important; margin-bottom: 3px !important; font-weight: 700; }
.sec .form-control, .sec select.form-control, .sec input.form-control { padding: 5px 9px !important; font-size: 0.85em !important; height: auto !important; }
.f2 { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
@media (max-width: 560px) { .f2 { grid-template-columns: 1fr; } }
.chk2 { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 10px; }
.chk2 label { display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 0.8em; }
.save-row { display: flex; gap: 8px; flex-wrap: wrap; position: sticky; bottom: 0; background: #f8fafc; padding: 10px 12px; border-radius: 8px; border: 1px solid #e2e8f0; }
.save-row .btn { font-weight: 800; padding: 8px 16px; border-radius: 8px; font-size: 0.85em; }
.lic-strip { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px 14px; margin-bottom: 12px; font-size: 0.82em; box-shadow: 0 4px 12px rgba(0,0,0,0.04); }
.lic-strip details { font-size: 0.85em; }
.lic-strip summary { cursor: pointer; color: #7c3aed; font-weight: 700; }
.remove-logo-label input[type="checkbox"]:checked + .btn { background: #16a34a; }
.remove-logo-label input[type="checkbox"]:checked + .btn::before { content: '✓ '; }
</style>

<div class="container">

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

<!-- Page header -->
<div class="set-head">
    <h1>⚙️ إعدادات النظام</h1>
    <div style="display: flex; gap: 8px; align-items: center;">
        <span style="background: #e0f2fe; color: #0284c7; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700;">v<?php echo defined('APP_VERSION') ? APP_VERSION : '1.0.0'; ?></span>
        <a href="../../index.php" class="btn btn-secondary btn-sm">🏠 الرئيسية</a>
    </div>
</div>

<!-- Slim license strip -->
<div class="lic-strip">
    <span style="font-weight: 800;">🛡️ الترخيص:</span>
    <span style="background: <?php echo $statusBadgeBg; ?>; color: <?php echo $statusBadgeColor; ?>; border: 1px solid <?php echo $statusBadgeBorder; ?>; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700;"><?php echo $statusText; ?></span>
    <span style="color: #64748b;">التجديد: <strong><?php echo htmlspecialchars($expiryDate); ?></strong><?php if (!$isLife && $daysLeft > 0): ?> (متبقي <?php echo $daysLeft; ?> يوم)<?php endif; ?></span>
    <a href="<?php echo BASE_URL; ?>activate.php" class="btn btn-sm" style="background: #7c3aed; color: white; border: none; font-size: 11px; font-weight: 700; padding: 4px 12px; border-radius: 6px; text-decoration: none;">🔑 تجديد / كود جديد</a>
    <details>
        <summary>تفاصيل الجهاز والتواصل</summary>
        <div style="display: flex; gap: 8px; align-items: center; margin-top: 6px; flex-wrap: wrap;">
            <span>المعرف: <code style="font-family: monospace; font-size: 12px; font-weight: 700; background: #e2e8f0; padding: 2px 6px; border-radius: 4px;"><?php echo htmlspecialchars($hwid); ?></code></span>
            <button type="button" onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars($hwid); ?>'); alert('تم نسخ معرف الجهاز بنجاح!');" class="btn btn-sm" style="background: #e2e8f0; border: none; border-radius: 4px; cursor: pointer; font-size: 11px;">📋 نسخ</button>
            <a href="https://wa.me/201271191616?text=<?php echo urlencode("مرحباً، أود تجديد اشتراك نظام إدارة الأجهزة.\nمعرف جهازي: " . $hwid); ?>" target="_blank" class="btn btn-sm" style="background: #25d366; color: white; font-weight: 700; text-decoration: none; padding: 4px 12px; font-size: 11px; border-radius: 6px;">💬 واتساب التجديد</a>
        </div>
    </details>
</div>

<form method="POST" enctype="multipart/form-data">
<div class="set-grid">

    <!-- Main settings -->
    <div class="card set-card">
        <div class="card-header" style="background: linear-gradient(135deg, #ea580c, #c2410c); color: white;">☕ إعدادات كافيه وصالة ألعاب عروسة النيل</div>
        <div class="card-body">

            <!-- 1. معلومات الكافيه -->
            <div class="sec">
                <h3 style="color: #ea580c;">☕ معلومات الكافيه والشعار</h3>
                <div class="form-group">
                    <label class="form-label">اسم الكافيه / المحل *</label>
                    <input type="text" name="store_name" class="form-control"
                           value="<?php echo htmlspecialchars($settings['store_name'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">العنوان</label>
                    <input type="text" name="store_address" class="form-control"
                           value="<?php echo htmlspecialchars($settings['store_address'] ?? ''); ?>">
                </div>
                <div class="f2">
                    <div class="form-group">
                        <label class="form-label">رقم الهاتف الرئيسي</label>
                        <input type="text" name="store_phone" class="form-control"
                               value="<?php echo htmlspecialchars($settings['store_phone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">رقم هاتف إضافي</label>
                        <input type="text" name="store_phone2" class="form-control"
                               value="<?php echo htmlspecialchars($settings['store_phone2'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-group" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 180px;">
                        <label class="form-label">شعار الكافيه</label>
                        <input type="file" name="store_logo" class="form-control" accept="image/*">
                    </div>
                    <?php if (!empty($settings['store_logo'])): ?>
                        <img src="../../assets/uploads/<?php echo $settings['store_logo']; ?>"
                             alt="Logo" style="max-width: 52px; max-height: 42px; object-fit: contain; border: 1px solid #ddd; border-radius: 8px;">
                        <label class="remove-logo-label" style="cursor: pointer;">
                            <input type="checkbox" name="remove_logo" value="1" style="display: none;">
                            <span class="btn btn-danger btn-sm">🗑️ حذف الشعار</span>
                        </label>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 2. الأسعار والضرائب -->
            <div class="sec">
                <h3 style="color: #2563eb;">🎮 أسعار الصالة والضرائب</h3>
                <div class="f2">
                    <div class="form-group">
                        <label class="form-label">ساعة بلايستيشن فردي</label>
                        <input type="number" step="0.5" name="ps_default_rate_single" class="form-control"
                               value="<?php echo htmlspecialchars($settings['ps_default_rate_single'] ?? '30.00'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">ساعة بلايستيشن زوجي</label>
                        <input type="number" step="0.5" name="ps_default_rate_multi" class="form-control"
                               value="<?php echo htmlspecialchars($settings['ps_default_rate_multi'] ?? '40.00'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">ساعة بلياردو / سنوكر</label>
                        <input type="number" step="0.5" name="billiard_default_rate_hour" class="form-control"
                               value="<?php echo htmlspecialchars($settings['billiard_default_rate_hour'] ?? '40.00'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">سعر جيم البلياردو</label>
                        <input type="number" step="0.5" name="billiard_default_rate_game" class="form-control"
                               value="<?php echo htmlspecialchars($settings['billiard_default_rate_game'] ?? '10.00'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">الضريبة المضافة (%)</label>
                        <input type="number" step="0.1" name="vat_rate" class="form-control"
                               value="<?php echo htmlspecialchars($settings['vat_rate'] ?? '0.00'); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">خدمة الصالة (%)</label>
                        <input type="number" step="0.1" name="service_charge" class="form-control"
                               value="<?php echo htmlspecialchars($settings['service_charge'] ?? '0.00'); ?>">
                    </div>
                </div>
                <div class="form-group" style="max-width: 220px;">
                    <label class="form-label">العملة المعروضة</label>
                    <input type="text" name="currency" class="form-control"
                           value="<?php echo htmlspecialchars($settings['currency'] ?? 'ج.م'); ?>" required>
                </div>
            </div>

            <!-- 3. الفواتير والتحكم -->
            <div class="sec">
                <h3 style="color: #059669;">🧾 الفواتير والتحكم</h3>
                <div class="f2">
                    <div class="form-group">
                        <label class="form-label">ترويسة الفاتورة</label>
                        <input type="text" name="receipt_header" class="form-control"
                               value="<?php echo htmlspecialchars($settings['receipt_header'] ?? 'أهلاً بكم في كافيه عروسة النيل'); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">تذييل الفاتورة</label>
                        <input type="text" name="receipt_footer" class="form-control"
                               value="<?php echo htmlspecialchars($settings['receipt_footer'] ?? 'شكراً لزيارتكم - نتمنى لكم وقتاً ممتعاً'); ?>">
                    </div>
                </div>
                <div class="chk2">
                    <label><input type="checkbox" name="enable_stock_alerts" value="1"
                               <?php echo ($settings['enable_stock_alerts'] ?? '1') == '1' ? 'checked' : ''; ?>>
                        <span>تنبيهات نواقص المخزون</span></label>
                    <label><input type="checkbox" name="enable_sound_alerts" value="1"
                               <?php echo ($settings['enable_sound_alerts'] ?? '1') == '1' ? 'checked' : ''; ?>>
                        <span>التنبيهات الصوتية</span></label>
                    <label><input type="checkbox" name="allow_edit_invoices" value="1"
                               <?php echo ($settings['allow_edit_invoices'] ?? '0') == '1' ? 'checked' : ''; ?>>
                        <span>تعديل الطلبات السابقة</span></label>
                    <label><input type="checkbox" name="allow_delete_invoices" value="1"
                               <?php echo ($settings['allow_delete_invoices'] ?? '0') == '1' ? 'checked' : ''; ?>>
                        <span>حذف الفواتير بصلاحية إدارية</span></label>
                    <label><input type="checkbox" name="prevent_negative_stock" value="1"
                               <?php echo ($settings['prevent_negative_stock'] ?? '0') == '1' ? 'checked' : ''; ?>>
                        <span>منع البيع بأكثر من المخزون المتاح (إيقافه = تحذير فقط)</span></label>
                </div>
            </div>

            <div class="save-row">
                <button type="submit" class="btn btn-primary" style="background: #ea580c; border-color: #ea580c;">💾 حفظ كافة الإعدادات</button>
                <a href="backup.php" class="btn btn-success">💾 نسخ احتياطي</a>
                <a href="update.php" class="btn" style="background: #0284c7; color: white; border: none; text-decoration: none; display: inline-flex; align-items: center;">🔄 التحديثات</a>
            </div>

        </div>
    </div>

    <!-- Side column -->
    <div>
        <!-- النسخ التلقائي -->
        <div class="card set-card">
            <div class="card-header" style="background: linear-gradient(135deg, #10b981, #059669); color: white;">⏰ النسخ التلقائي</div>
            <div class="card-body">
                <div class="sec" style="margin-bottom: 0;">
                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 0.82em; font-weight: 700; margin-bottom: 8px;">
                        <input type="checkbox" name="auto_backup_enabled" value="1"
                               <?php echo ($settings['auto_backup_enabled'] ?? '0') == '1' ? 'checked' : ''; ?>>
                        <span>تفعيل النسخ التلقائي</span>
                    </label>
                    <div class="form-group">
                        <label class="form-label">كل فترة</label>
                        <select name="auto_backup_interval_hours" class="form-control">
                            <option value="24" <?php echo ($settings['auto_backup_interval_hours'] ?? '24') == '24' ? 'selected' : ''; ?>>يومي</option>
                            <option value="72" <?php echo ($settings['auto_backup_interval_hours'] ?? '24') == '72' ? 'selected' : ''; ?>>كل 3 أيام</option>
                            <option value="168" <?php echo ($settings['auto_backup_interval_hours'] ?? '24') == '168' ? 'selected' : ''; ?>>أسبوعي</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 4px;">
                        <label class="form-label">احتفاظ بآخر (نسخ)</label>
                        <input type="number" min="1" max="30" name="auto_backup_keep" class="form-control"
                               value="<?php echo htmlspecialchars($settings['auto_backup_keep'] ?? '7'); ?>">
                    </div>
                    <div style="font-size: 0.78em; color: #166534; margin-top: 6px;">
                        آخر نسخة: <strong><?php echo !empty($settings['last_auto_backup_ts']) ? date('Y/m/d H:i', (int)$settings['last_auto_backup_ts']) : 'لم تعمل بعد'; ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- منطقة الخطر -->
        <?php if (hasPermission('settings.reset')): ?>
        <div class="card set-card" style="border: 2px solid #ef4444;">
            <div class="card-header" style="background: linear-gradient(135deg, #ef4444, #b91c1c); color: white;">⚠️ منطقة الخطر</div>
            <div class="card-body" style="font-size: 0.82em; color: #7f1d1d;">
                <div style="margin-bottom: 8px;">مسح كل البيانات والعودة لحالة التثبيت الجديد (بعد نسخة احتياطية).</div>
                <a href="reset.php" class="btn btn-danger btn-sm" style="font-weight: 800; text-decoration: none;">🗑️ إعادة ضبط المصنع</a>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div>
</form>

</div>

<?php include '../../includes/footer.php'; ?>
