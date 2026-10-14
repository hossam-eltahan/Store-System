<?php
/**
 * Add Billiard Table - إضافة طاولة بلياردو
 * نظام كافيه عروسة النيل
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requirePermission('billiards.tables');

$pageTitle = 'إضافة طاولة بلياردو';

// Default rates come from Settings (ساعة بلياردو / سعر الجيم)
$defaultRateHour = floatval(getSetting('billiard_default_rate_hour', 40));
$defaultRateGame = floatval(getSetting('billiard_default_rate_game', 10));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $table_number = sanitize(trim($_POST['table_number'] ?? ''));
    $display_name = sanitize(trim($_POST['display_name'] ?? ''));
    $table_type = in_array(($_POST['table_type'] ?? 'pool'), ['pool', 'snooker', 'ping_pong'], true) ? $_POST['table_type'] : 'pool';
    $billing_mode = ($_POST['billing_mode'] ?? 'hourly') === 'per_game' ? 'per_game' : 'hourly';
    $rate_per_hour = max(0, floatval($_POST['rate_per_hour'] ?? 0));
    $rate_per_game = max(0, floatval($_POST['rate_per_game'] ?? 0));
    $display_order = intval($_POST['display_order'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validate inputs
    if (empty($table_number)) {
        setError('يرجى كتابة رقم الطاولة');
    } elseif (empty($display_name)) {
        setError('يرجى كتابة اسم الطاولة');
    } elseif ($rate_per_hour <= 0) {
        setError('مرفوعات بالساعة يجب أن تكون أكبر من صفر');
    } elseif ($rate_per_game <= 0) {
        setError('مرفوعات بالجيم (Game) يجب أن تكون أكبر من صفر');
    } else {
        // Check for duplicate table number or display name
        $existing = getRow(
            "SELECT id FROM billiard_tables WHERE table_number = ? OR display_name = ?",
            [$table_number, $display_name]
        );
        if ($existing) {
            setError('رقم الطاولة أو اسمها مسجل مسبقاً');
        } else {
            $tableId = insert(
                "INSERT INTO billiard_tables (table_number, display_name, table_type, billing_mode, rate_per_hour, rate_per_game, display_order, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$table_number, $display_name, $table_type, $billing_mode, $rate_per_hour, $rate_per_game, $display_order, $is_active]
            );

            if ($tableId) {
                if ($is_active) {
                    logActivity('إضافة طاولة بلياردو', "تم إضافة الطاولة: {$display_name}");
                }
                setSuccess('تم إضافة الطاولة بنجاح');
                redirect('index.php');
            } else {
                setError('حدث خطأ أثناء إضافة الطاولة');
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
    background: #10b981;
    color: white;
}

.btn-primary:hover {
    background: #059669;
}

.btn-secondary {
    background: #f1f5f9;
    color: #334155;
    border: 1px solid #cbd5e1;
}

.btn-secondary:hover {
    background: #e2e8f0;
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
    accent-color: #10b981;
}

.rate-help {
    font-size: 11.5px;
    color: #64748b;
    margin-top: 4px;
}
</style>

<div class="container">
    <div style="background: white; border-radius: 14px; border: 1px solid #e2e8f0; padding: 24px; margin-bottom: 20px;">
        <h2 style="font-size: 18px; font-weight: 800; color: #0f172a; margin: 0 0 4px 0;">🎱 إضافة طاولة بلياردو جديدة</h2>
        <div style="font-size: 13px; color: #64748b;">أدخل بيانات الطاولة وضبط مرفوعات اللعب</div>
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
                    <label class="form-label">رقم الطاولة *</label>
                    <input type="text" name="table_number"
                           class="form-control"
                           placeholder="مثال: TBL-B3"
                           value="<?php echo htmlspecialchars($_POST['table_number'] ?? ''); ?>"
                           required>
                </div>

                <div class="form-group">
                    <label class="form-label">اسم الطاولة *</label>
                    <input type="text" name="display_name"
                           class="form-control"
                           placeholder="مثال: طاولة 1, طاولة VIP..."
                           value="<?php echo htmlspecialchars($_POST['display_name'] ?? ''); ?>"
                           required>
                    <div style="font-size: 12px; color: #94a3b8; margin-top: 4px;">الاسم الذي سيظهر للكاشير (مثال: طاولة بلياردو 1)</div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">نوع الطاولة *</label>
                    <select name="table_type" class="form-control">
                        <option value="pool" <?php echo (($_POST['table_type'] ?? 'pool') === 'pool') ? 'selected' : ''; ?>>🎱 بلياردو</option>
                        <option value="snooker" <?php echo (($_POST['table_type'] ?? '') === 'snooker') ? 'selected' : ''; ?>>🎱 سنوكر</option>
                        <option value="ping_pong" <?php echo (($_POST['table_type'] ?? '') === 'ping_pong') ? 'selected' : ''; ?>>🏓 بينج بونج</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">نظام الحساب *</label>
                    <select name="billing_mode" class="form-control">
                        <option value="hourly" <?php echo (($_POST['billing_mode'] ?? 'hourly') === 'hourly') ? 'selected' : ''; ?>>⏳ بالساعة</option>
                        <option value="per_game" <?php echo (($_POST['billing_mode'] ?? '') === 'per_game') ? 'selected' : ''; ?>>🎯 بالجيم</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">مرفوعات بالساعة *</label>
                    <input type="number" name="rate_per_hour" min="0" step="0.5" class="form-control"
                           value="<?php echo htmlspecialchars($_POST['rate_per_hour'] ?? $defaultRateHour); ?>" required>
                    <div class="rate-help">ج.م/ساعة (الافتراضي من الإعدادات: <?php echo htmlspecialchars($defaultRateHour); ?>)</div>
                </div>

                <div class="form-group">
                    <label class="form-label">مرفوعات بالجيم (Game) *</label>
                    <input type="number" name="rate_per_game" min="0" step="0.5" class="form-control"
                           value="<?php echo htmlspecialchars($_POST['rate_per_game'] ?? $defaultRateGame); ?>" required>
                    <div class="rate-help">ج.م/جيم (الافتراضي من الإعدادات: <?php echo htmlspecialchars($defaultRateGame); ?>)</div>
                </div>
            </div>

            <div class="divider"></div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">ترتيب العرض (رقم) *</label>
                    <input type="number" name="display_order" min="0" class="form-control"
                           value="<?php echo htmlspecialchars($_POST['display_order'] ?? ''); ?>"
                           placeholder="ترتيب ظهور الطاولة (0 للبداية)">
                </div>

                <div class="form-group">
                    <label class="checkbox-container">
                        <input type="checkbox" name="is_active" <?php echo (($_POST['is_active'] ?? 'on') === 'on') ? 'checked' : ''; ?>>
                        <span style="font-size: 14px; color: #334155;">نشط - متوفر في القائمة</span>
                    </label>
                </div>
            </div>

            <div class="form-actions">
                <a href="index.php" class="btn btn-secondary">🔙 رجوع</a>
                <button type="submit" class="btn btn-primary">💾 حفظ الطاولة</button>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>