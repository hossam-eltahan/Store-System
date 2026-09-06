<?php
/**
 * Warehouses Management - List
 * إدارة المخازن - القائمة الرئيسية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';

requirePermission('warehouses.view');

$pageTitle = 'إدارة المخازن';

// Handle delete
if (isset($_GET['delete'])) {
    requirePermission('warehouses.manage');
    $deleteId = (int)$_GET['delete'];
    $wh = getRow("SELECT * FROM warehouses WHERE id = ?", [$deleteId]);
    
    if (!$wh) {
        setError('المخزن غير موجود');
    } elseif ((int)$wh['is_default'] === 1) {
        setError('لا يمكن حذف المخزن الرئيسي الافتراضي للنظام');
    } else {
        // Check stock
        $stockCount = getRow("SELECT COALESCE(SUM(quantity), 0) as total FROM warehouse_stock WHERE warehouse_id = ?", [$deleteId]);
        if ($stockCount && (int)$stockCount['total'] > 0) {
            setError('لا يمكن حذف المخزن لوجود بضاعة مسجلة فيه. يمكنك تحويل البضاعة لمخزن آخر أولاً');
        } else {
            // Check invoices
            $invCount = getRow("SELECT COUNT(*) as count FROM invoices WHERE warehouse_id = ?", [$deleteId]);
            if ($invCount && (int)$invCount['count'] > 0) {
                setError('لا يمكن حذف المخزن لوجود فواتير مرتبطة به. يمكنك تعطيله بدلاً من حذفه');
            } else {
                execute("DELETE FROM warehouse_stock WHERE warehouse_id = ?", [$deleteId]);
                execute("DELETE FROM warehouses WHERE id = ?", [$deleteId]);
                logActivity('حذف مخزن', "تم حذف المخزن: {$wh['name']}");
                setSuccess('تم حذف المخزن بنجاح');
            }
        }
    }
    redirect('index.php');
}

// Handle toggle status
if (isset($_GET['toggle_status'])) {
    requirePermission('warehouses.manage');
    $toggleId = (int)$_GET['toggle_status'];
    $wh = getRow("SELECT * FROM warehouses WHERE id = ?", [$toggleId]);
    if ($wh) {
        if ((int)$wh['is_default'] === 1 && (int)$wh['is_active'] === 1) {
            setError('لا يمكن تعطيل المخزن الافتراضي');
        } else {
            $newStatus = (int)$wh['is_active'] === 1 ? 0 : 1;
            execute("UPDATE warehouses SET is_active = ? WHERE id = ?", [$newStatus, $toggleId]);
            $statusText = $newStatus ? 'تفعيل' : 'تعطيل';
            logActivity('تعديل حالة مخزن', "تم {$statusText} المخزن: {$wh['name']}");
            setSuccess("تم {$statusText} المخزن بنجاح");
        }
    }
    redirect('index.php');
}

// Fetch all warehouses with stock metrics
$warehouses = getRows("SELECT w.*,
    COALESCE((SELECT COUNT(DISTINCT product_id) FROM warehouse_stock WHERE warehouse_id = w.id AND quantity > 0), 0) as distinct_products,
    COALESCE((SELECT SUM(quantity) FROM warehouse_stock WHERE warehouse_id = w.id), 0) as total_units,
    (SELECT COUNT(*) FROM users WHERE default_warehouse_id = w.id) as users_count
    FROM warehouses w 
    ORDER BY w.is_default DESC, w.name ASC");

// Totals
$totalWarehouses = count($warehouses);
$activeWarehouses = count(array_filter($warehouses, fn($w) => (int)$w['is_active'] === 1));
$totalStockUnits = array_sum(array_column($warehouses, 'total_units'));

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
        <div>
            <h1 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <span>🏢</span> إدارة المخازن
            </h1>
            <p style="color: var(--text-secondary); margin: 5px 0 0 0; font-size: 14px;">إدارة مخازن البضائع، ومتابعة الأرصدة والتحويلات بين المخازن</p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <?php if (hasPermission('warehouses.transfer')): ?>
            <a href="transfer.php" class="btn btn-warning" style="display: inline-flex; align-items: center; gap: 6px;">
                <span>🔄</span> تحويل مخزون
            </a>
            <?php endif; ?>
            <?php if (hasPermission('warehouses.manage')): ?>
            <a href="add.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
                <span>➕</span> إضافة مخزن جديد
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid" style="margin-bottom: 25px;">
        <div class="stat-card info">
            <div class="stat-icon">🏢</div>
            <div class="stat-label">إجمالي المخازن</div>
            <div class="stat-value"><?php echo $totalWarehouses; ?></div>
        </div>
        <div class="stat-card success">
            <div class="stat-icon">✅</div>
            <div class="stat-label">المخازن النشطة</div>
            <div class="stat-value"><?php echo $activeWarehouses; ?></div>
        </div>
        <div class="stat-card primary">
            <div class="stat-icon">📦</div>
            <div class="stat-label">إجمالي القطع المخزنة</div>
            <div class="stat-value"><?php echo number_format($totalStockUnits); ?></div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon">👥</div>
            <div class="stat-label">الموظفين المربوطين بمخازن</div>
            <div class="stat-value"><?php echo array_sum(array_column($warehouses, 'users_count')); ?></div>
        </div>
    </div>

    <!-- Warehouses Cards & Table -->
    <div class="card">
        <div class="card-header" style="background: linear-gradient(135deg, #0284c7, #0369a1); color: white; display: flex; justify-content: space-between; align-items: center;">
            <span style="font-weight: 700; font-size: 16px;">📋 قائمة المخازن المسجلة</span>
            <span class="badge" style="background: rgba(255,255,255,0.2); padding: 5px 12px; border-radius: 20px;"><?php echo count($warehouses); ?> مخزن</span>
        </div>
        <div class="card-body" style="padding: 0; overflow-x: auto;">
            <table class="table" style="margin: 0; vertical-align: middle;">
                <thead>
                    <tr style="background: var(--bg-primary);">
                        <th style="width: 60px;">#</th>
                        <th>اسم المخزن</th>
                        <th>الكود</th>
                        <th>الموقع / العنوان</th>
                        <th>المسؤول والاتصال</th>
                        <th>الأصناف المتوفرة</th>
                        <th>إجمالي الكمية</th>
                        <th>الحالة</th>
                        <th style="min-width: 260px; text-align: center;">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($warehouses)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                            لا توجد مخازن مسجلة حالياً
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($warehouses as $wh): ?>
                        <tr style="<?php echo (int)$wh['is_active'] === 0 ? 'opacity: 0.65; background: #f8fafc;' : ''; ?>">
                            <td><strong><?php echo $wh['id']; ?></strong></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span style="font-size: 20px;">🏢</span>
                                    <div>
                                        <div style="font-weight: 700; font-size: 15px;">
                                            <?php echo htmlspecialchars($wh['name']); ?>
                                            <?php if ((int)$wh['is_default'] === 1): ?>
                                                <span class="badge" style="background: #e0f2fe; color: #0284c7; font-size: 11px; padding: 2px 8px; border-radius: 10px; margin-right: 5px;">⭐ الافتراضي</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($wh['notes'])): ?>
                                            <small style="color: var(--text-secondary);"><?php echo htmlspecialchars($wh['notes']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <code style="background: var(--bg-primary); padding: 3px 8px; border-radius: 6px; font-weight: 600;">
                                    <?php echo htmlspecialchars($wh['code'] ?? '—'); ?>
                                </code>
                            </td>
                            <td><?php echo htmlspecialchars($wh['location'] ?? '—'); ?></td>
                            <td>
                                <?php if (!empty($wh['manager_name']) || !empty($wh['phone'])): ?>
                                    <div>👤 <?php echo htmlspecialchars($wh['manager_name'] ?? 'غير محدد'); ?></div>
                                    <?php if (!empty($wh['phone'])): ?>
                                        <small style="color: var(--text-secondary);">📞 <?php echo htmlspecialchars($wh['phone']); ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: var(--text-secondary);">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge" style="background: #f1f5f9; color: #334155; font-size: 13px; font-weight: 600; padding: 4px 10px; border-radius: 8px;">
                                    <?php echo number_format($wh['distinct_products']); ?> صنف
                                </span>
                            </td>
                            <td>
                                <span style="font-weight: 700; color: <?php echo (int)$wh['total_units'] > 0 ? '#059669' : '#dc2626'; ?>; font-size: 15px;">
                                    <?php echo number_format($wh['total_units']); ?>
                                </span>
                                <small style="color: var(--text-secondary);">قطعة</small>
                            </td>
                            <td>
                                <?php if ((int)$wh['is_active'] === 1): ?>
                                    <span class="badge" style="background: #dcfce7; color: #15803d; padding: 4px 10px; border-radius: 12px; font-weight: 600;">
                                        نشط
                                    </span>
                                <?php else: ?>
                                    <span class="badge" style="background: #fee2e2; color: #b91c1c; padding: 4px 10px; border-radius: 12px; font-weight: 600;">
                                        معطل
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: inline-flex; gap: 6px; align-items: center;">
                                    <!-- View Stock -->
                                    <a href="stock.php?warehouse_id=<?php echo $wh['id']; ?>" class="btn btn-sm btn-info" title="عرض رصيد المخزن" style="padding: 6px 12px; font-size: 12px;">
                                        📦 الرصيد
                                    </a>

                                    <?php if (hasPermission('warehouses.transfer')): ?>
                                    <!-- Transfer from this warehouse -->
                                    <a href="transfer.php?from_warehouse_id=<?php echo $wh['id']; ?>" class="btn btn-sm btn-warning" title="تحويل بضاعة من هذا المخزن" style="padding: 6px 12px; font-size: 12px;">
                                        🔄 تحويل
                                    </a>
                                    <?php endif; ?>

                                    <?php if (hasPermission('warehouses.manage')): ?>
                                    <!-- Edit -->
                                    <a href="edit.php?id=<?php echo $wh['id']; ?>" class="btn btn-sm btn-secondary" title="تعديل بيانات المخزن" style="padding: 6px 10px; font-size: 12px;">
                                        ✏️
                                    </a>

                                    <!-- Toggle Active -->
                                    <?php if ((int)$wh['is_default'] !== 1): ?>
                                    <a href="index.php?toggle_status=<?php echo $wh['id']; ?>" class="btn btn-sm <?php echo (int)$wh['is_active'] === 1 ? 'btn-outline-warning' : 'btn-outline-success'; ?>" 
                                       title="<?php echo (int)$wh['is_active'] === 1 ? 'تعطيل المخزن' : 'تفعيل المخزن'; ?>" 
                                       onclick="return confirm('هل أنت متأكد من تغيير حالة المخزن؟');"
                                       style="padding: 6px 8px; font-size: 12px;">
                                        <?php echo (int)$wh['is_active'] === 1 ? '⏸️' : '▶️'; ?>
                                    </a>

                                    <!-- Delete -->
                                    <a href="index.php?delete=<?php echo $wh['id']; ?>" class="btn btn-sm btn-danger" title="حذف المخزن" 
                                       onclick="return confirm('هل أنت متأكد من رغبتك في حذف هذا المخزن نهائياً؟');"
                                       style="padding: 6px 10px; font-size: 12px;">
                                        🗑️
                                    </a>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
