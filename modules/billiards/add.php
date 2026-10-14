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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $display_name = sanitize(trim($_POST['display_name'] ?? ''));
    $rate_per_hour = max(0, floatval($_POST['rate_per_hour'] ?? 0));
    $rate_per_game = max(0, floatval($_POST['rate_per_game'] ?? 0));
    $display_order = intval($_POST['display_order'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validate inputs
    if (empty($display_name)) {
        setError('يرجى كتابة اسم الطاولة');
    } elseif ($rate_per_hour <= 0) {
        setError('مرفوعات الساعة يجب أن تكون أكبر من صفر');
    } elseif ($rate_per_game <= 0) {
        setError('مرفوعات الجيم يجب أن تكون أكبر من صفر');
    } else {
        // Check for duplicate display name
        $existing = getRow(
            "SELECT id FROM billiard_tables WHERE display_name = ?",
            [$display_name]
        );
        if ($existing) {
            setError('اسم الطاولة مسجل مسبقاً');
        } else {
            $tableId = insert(
                "INSERT INTO billiard_tables (display_name, rate_per_hour, rate_per_game, display_order, is_active)
                 VALUES (?, ?, ?, ?, ?)",
                [$display_name, $rate_per_hour, $rate_per_game, $display_order, $is_active]
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
        <div style="font-size: 13px; color: #64748b;">أدخل بيانات الطاولة وضبط مرفوعات السوق</div>
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
            <div class="form-group">
                <label class="form-label">اسم الطاولة *</label>
                <input type="text" name="display_name"
                       class="form-control"
                       placeholder="مثال: طاولة 1, طاولة VIP..."
                       value="<?php echo htmlspecialchars($_POST['display_name'] ?? $_POST['rate_per_hour'] ?? ''); ?>"
                       required>
                <div style="font-size: 12px; color: #94a3b8; margin-top: 4px;">الاسم الذي سيظهر للكاشير (مثال: طاولة بلياردو 1)</div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">مرفوعات البالساعة *</label>
                    <input type="number" name="rate_per_hour" min="0" step="0.5" class="form-control"
                           value="<?php echo htmlspecialchars($_POST['rate_per_hour'] ?? 0); ?>" required>
                    <div class="rate-help">ج.م/ساعة</div>
                </div>

                <div class="form-group">
                    <label class="form-label">مرفوعات بالجيم (Game) *</label>
                    <input type="number" name="rate_per_game" min="0" step="0.5" class="form-control"
                           value="<?php echo htmlspecialchars($_POST['rate_per_game'] ?? 0); ?>" required>
                    <div class="rate-help">ج.م/تجميع شناقة</div>
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