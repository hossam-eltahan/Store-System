<?php
/**
 * Edit Table - تعديل بيانات الطاولة
 * نظام كافيه عروسة النيل
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requirePermission('tables.manage');

$pageTitle = 'تعديل بيانات الطاولة';

// Get table ID
$tableId = (int)($_GET['id'] ?? 0);
$table = getRow("SELECT * FROM cafe_tables WHERE id = ?", [$tableId]);

if (!$table) {
    setError('الطاولة غير موجودة');
    redirect('index.php');
}

$sections = ['main_hall', 'outdoor'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $display_name = sanitize(trim($_POST['display_name'] ?? ''));
    $section = sanitize(trim($_POST['section'] ?? 'main_hall'));
    $capacity = max(1, intval($_POST['capacity'] ?? 4));
    $display_order = intval($_POST['display_order'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validate display name
    if (empty($display_name)) {
        setError('يرجى كتابة اسم الطاولة');
    } else {
        // Check for duplicate display name in same section (excluding current table)
        $existing = getRow(
            "SELECT id FROM cafe_tables WHERE display_name = ? AND section = ? AND id != ?",
            [$display_name, $section, $tableId]
        );
        if ($existing) {
            setError('اسم الطاولة مسجل مسبقاً في نفس القسم');
        } else {
            $result = execute(
                "UPDATE cafe_tables SET display_name = ?, section = ?, capacity = ?, display_order = ?, is_active = ?
                 WHERE id = ?",
                [$display_name, $section, $capacity, $display_order, $is_active, $tableId]
            );

            if ($result) {
                logActivity('تعديل طاولة', "تم تعديل بيانات الطاولة: {$display_name}");
                setSuccess('تم تحديث بيانات الطاولة بنجاح');
                redirect('edit.php?id=' . $tableId);
            } else {
                setError('حدث خطأ أثناء تحديث الطاولة');
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

.form-select {
    cursor: pointer;
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
    background: #6F4E37;
    color: white;
}

.btn-primary:hover {
    background: #5a3d2b;
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
    accent-color: #6F4E37;
}
</style>

<div class="container">
    <div style="background: white; border-radius: 14px; border: 1px solid #e2e8f0; padding: 24px; margin-bottom: 20px;">
        <h2 style="font-size: 18px; font-weight: 800; color: #0f172a; margin: 0 0 4px 0;">🪑 تعديل بيانات الطاولة</h2>
        <div style="font-size: 13px; color: #64748b;">تعديل تفاصيل الطاولة #<?php echo $tableId; ?></div>
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
                       value="<?php echo htmlspecialchars($table['display_name']); ?>"
                       required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">القسم *</label>
                    <select name="section" class="form-select" required>
                        <option value="main_hall" <?php echo ($table['section'] === 'main_hall') ? 'selected' : ''; ?>>الصالة الرئيسية</option>
                        <option value="outdoor" <?php echo ($table['section'] === 'outdoor') ? 'selected' : ''; ?>>خارجي (ساحة)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">السعة (عدد الأفراد) *</label>
                    <input type="number" name="capacity" min="1" max="50" class="form-control"
                           value="<?php echo $table['capacity']; ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">ترتيب العرض (رقم) *</label>
                <input type="number" name="display_order" min="0" class="form-control"
                       value="<?php echo $table['display_order']; ?>">
                <div style="font-size: 12px; color: #94a3b8; margin-top: 4px;">ترتيب ظهور الطاولة في القائمة</div>
            </div>

            <div class="form-group">
                <label class="checkbox-container">
                    <input type="checkbox" name="is_active" <?php echo $table['is_active'] ? 'checked' : ''; ?>>
                    <span style="font-size: 14px; color: #334155;">نشط - الطاولة متوفرة للبيع</span>
                </label>
            </div>

            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-top: 16px;">
                <div style="font-size: 13px; color: #64748b;">
                    <strong>الحالة الحالية:</strong><br>
                    <?php echo $table['status'] === 'occupied' ? '🔴 مشغولة حالياً' : '🟢 متاحة وشاغرة'; ?>
                </div>
            </div>

            <div class="form-actions">
                <a href="index.php" class="btn btn-secondary">🔙 رجوع</a>
                <button type="submit" class="btn btn-primary">💾 حفظ التعديلات</button>
                <a href="delete.php?id=<?php echo $tableId; ?>" class="btn btn-danger" onclick="return confirmAction(this, 'هل أنت متأكد من حذف هذه الطاولة؟');">🗑️ حذف</a>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>