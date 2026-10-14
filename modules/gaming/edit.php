<?php
/**
 * Edit PlayStation Device - تعديل بيانات الجهاز
 * نظام كافيه عروسة النيل
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requirePermission('gaming.devices');

$pageTitle = 'تعديل بيانات جهاز البلايستيشن';

// Get device ID
$deviceId = (int)($_GET['id'] ?? 0);
$device = getRow("SELECT * FROM gaming_devices WHERE id = ?", [$deviceId]);

if (!$device) {
    setError('الجهاز غير موجود');
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $device_number = sanitize(trim($_POST['device_number'] ?? ''));
    $display_name = sanitize(trim($_POST['display_name'] ?? ''));
    $device_type = ($_POST['device_type'] ?? 'ps4') === 'ps5' ? 'ps5' : 'ps4';
    $room_type = ($_POST['room_type'] ?? 'normal') === 'vip' ? 'vip' : 'normal';
    $rate_single = max(0, floatval($_POST['rate_single'] ?? 0));
    $rate_multi = max(0, floatval($_POST['rate_multi'] ?? 0));
    $rate_vip_raw = trim($_POST['rate_vip'] ?? '');
    $rate_vip = ($rate_vip_raw === '') ? null : max(0, floatval($rate_vip_raw));
    $display_order = intval($_POST['display_order'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validate inputs
    if (empty($device_number)) {
        setError('يرجى كتابة رقم الجهاز');
    } elseif (empty($display_name)) {
        setError('يرجى كتابة اسم الجهاز');
    } elseif ($rate_single <= 0) {
        setError('مرفوعات الفردي (سنجل) يجب أن تكون أكبر من صفر');
    } elseif ($rate_multi <= 0) {
        setError('مرفوعات الجماعي (مالتي) يجب أن تكون أكبر من صفر');
    } else {
        // Check for duplicate device number or display name (excluding current device)
        $existing = getRow(
            "SELECT id FROM gaming_devices WHERE (device_number = ? OR display_name = ?) AND id != ?",
            [$device_number, $display_name, $deviceId]
        );
        if ($existing) {
            setError('رقم الجهاز أو اسمه مسجل مسبقاً');
        } else {
            $result = execute(
                "UPDATE gaming_devices SET device_number = ?, display_name = ?, device_type = ?, room_type = ?, rate_single = ?, rate_multi = ?, rate_vip = ?, display_order = ?, is_active = ?
                 WHERE id = ?",
                [$device_number, $display_name, $device_type, $room_type, $rate_single, $rate_multi, $rate_vip, $display_order, $is_active, $deviceId]
            );

            if ($result) {
                logActivity('تعديل جهاز بلايستيشن', "تم تعديل الجهاز: {$display_name}");
                setSuccess('تم تحديث بيانات الجهاز بنجاح');
                redirect('edit.php?id=' . $deviceId);
            } else {
                setError('حدث خطأ أثناء تحديث الجهاز');
            }
        }
    }
}

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<style>
.form-group {
    margin-bottom: 16px;
}

.form-label {
    display: block;
    font-size: 13px;
    font-weight: 700;
    color: #475569;
    margin-bottom: 6px;
}

.form-control {
    width: 100%;
    padding: 10px 12px;
    border-radius: 8px;
    border: 1.5px solid #cbd5e1;
    font-size: 14px;
    font-family: inherit;
    transition: border-color 0.2s;
}

.form-control:focus {
    outline: none;
    border-color: #ea580c;
    box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.1);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.alert {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 16px;
    border: 1px solid;
}

.alert-success {
    background: #ecfdf5;
    border-color: #6ee7b7;
    color: #065f46;
}

.alert-error {
    background: #fee2e2;
    border-color: #fca5a5;
    color: #991b1b;
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px dashed #e2e8f0;
}

.btn {
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
    border: none;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
}

.btn-primary {
    background: #2563eb;
    color: white;
}

.btn-primary:hover {
    background: #1d4ed8;
}

.btn-secondary {
    background: #f1f5f9;
    color: #334155;
    border: 1px solid #cbd5e1;
}

.btn-secondary:hover {
    background: #e2e8f0;
}

.btn-danger {
    background: #ef4444;
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
}

.divider {
    height: 1px;
    background: #e2e8f0;
    margin: 20px 0;
}

.checkbox-container {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.checkbox-container input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: #2563eb;
}
</style>

<div class="container">
    <div style="background: white; border-radius: 14px; border: 1px solid #e2e8f0; padding: 24px; margin-bottom: 20px;">
        <h2 style="font-size: 18px; font-weight: 800; color: #0f172a; margin: 0 0 4px 0;">🎮 تعديل بيانات جهاز البلايستيشن</h2>
        <div style="font-size: 13px; color: #64748b;">تعديل تفاصيل الجهاز #<?php echo $deviceId; ?></div>
    </div>

    <!-- Alerts -->
    <?php
    if (hasError()) {
        echo '<div class="alert alert-error">' . htmlspecialchars(getError()) . '</div>';
    }
    if (hasSuccess()) {
        echo '<div class="alert alert-success">' . htmlspecialchars(getSuccess()) . '</div>';
    }
    ?>

    <div style="background: white; border-radius: 14px; border: 1px solid #e2e8f0; padding: 24px;">
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">رقم الجهاز *</label>
                    <input type="text" name="device_number"
                           class="form-control"
                           value="<?php echo htmlspecialchars($device['device_number']); ?>"
                           required>
                </div>

                <div class="form-group">
                    <label class="form-label">اسم الجهاز *</label>
                    <input type="text" name="display_name"
                           class="form-control"
                           value="<?php echo htmlspecialchars($device['display_name']); ?>"
                           required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">نوع الجهاز *</label>
                    <select name="device_type" class="form-control">
                        <option value="ps4" <?php echo ($device['device_type'] === 'ps4') ? 'selected' : ''; ?>>🎮 PS4</option>
                        <option value="ps5" <?php echo ($device['device_type'] === 'ps5') ? 'selected' : ''; ?>>🎮 PS5</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">نوع القاعة *</label>
                    <select name="room_type" class="form-control">
                        <option value="normal" <?php echo ($device['room_type'] === 'normal') ? 'selected' : ''; ?>>عادية</option>
                        <option value="vip" <?php echo ($device['room_type'] === 'vip') ? 'selected' : ''; ?>>⭐ VIP</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">مرفوعات الفردي (سنجل) *</label>
                    <input type="number" name="rate_single" min="0" step="0.5" class="form-control"
                           value="<?php echo $device['rate_single']; ?>" required>
                    <div style="font-size: 11.5px; color: #64748b;">ج.م بالساعة</div>
                </div>

                <div class="form-group">
                    <label class="form-label">مرفوعات الجماعي (مالتي) *</label>
                    <input type="number" name="rate_multi" min="0" step="0.5" class="form-control"
                           value="<?php echo $device['rate_multi']; ?>" required>
                    <div style="font-size: 11.5px; color: #64748b;">ج.م بالساعة</div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">مرفوعات VIP (اختياري)</label>
                <input type="number" name="rate_vip" min="0" step="0.5" class="form-control"
                       placeholder="اتركه فارغاً لاستخدام سعر الفردي"
                       value="<?php echo ($device['rate_vip'] !== null && $device['rate_vip'] !== '') ? $device['rate_vip'] : ''; ?>">
                <div style="font-size: 11.5px; color: #64748b;">ج.م بالساعة — يستخدم لجلسات VIP فقط</div>
            </div>

            <div class="divider"></div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">ترتيب العرض (رقم) *</label>
                    <input type="number" name="display_order" min="0" class="form-control"
                           value="<?php echo $device['display_order']; ?>">
                </div>

                <div class="form-group">
                    <label class="checkbox-container">
                        <input type="checkbox" name="is_active" <?php echo $device['is_active'] ? 'checked' : ''; ?>>
                        <span style="font-size: 14px; color: #334155;">نشط - متوفر في القائمة</span>
                    </label>
                </div>
            </div>

            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-top: 16px;">
                <div style="font-size: 13px; color: #64748b;">
                    <strong>الحالة الحالية:</strong><br>
                    <?php echo $device['is_active'] ? '🟢 نشط' : '🔴 غير نشط'; ?>
                </div>
            </div>

            <div class="form-actions">
                <a href="index.php" class="btn btn-secondary">🔙 رجوع</a>
                <button type="submit" class="btn btn-primary">💾 حفظ التعديلات</button>
                <a href="delete.php?id=<?php echo $deviceId; ?>" class="btn btn-danger" onclick="return confirm('هل أنت متأكد من حذف هذا الجهاز؟');">🗑️ حذف</a>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>