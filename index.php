<?php
/**
 * Cafe & Gaming Management System - Main Dashboard
 * لوحة التحكم الرئيسية - كافيه عروسة النيل
 */

require_once 'config/database.php';
require_once 'config/settings.php';
require_once 'config/auth.php';
require_once 'config/license.php';

enforceLicense();
requireLogin();

$pageTitle = 'الرئيسية - كافيه عروسة النيل';

// جلب حالة الوردية النشطة للمستخدم
$currentShift = getRow(
    "SELECT s.*, u.full_name as cashier_name 
     FROM shifts s 
     LEFT JOIN users u ON s.user_id = u.id 
     WHERE s.user_id = ? AND s.status = 'open' 
     ORDER BY s.id DESC LIMIT 1",
    [getCurrentUserId()]
);

// إحصائيات الألعاب المباشرة (Live Gaming)
$totalPsDevices = getRow("SELECT COUNT(*) as count FROM gaming_devices WHERE is_active = 1")['count'] ?? 0;
$activePsSessions = getRow("SELECT COUNT(*) as count FROM gaming_sessions WHERE status IN ('active', 'paused')")['count'] ?? 0;
$availablePs = max(0, $totalPsDevices - $activePsSessions);

$totalBilliard = getRow("SELECT COUNT(*) as count FROM billiard_tables WHERE is_active = 1")['count'] ?? 0;
$activeBilliard = getRow("SELECT COUNT(*) as count FROM billiard_sessions WHERE status IN ('active', 'paused')")['count'] ?? 0;
$availableBilliard = max(0, $totalBilliard - $activeBilliard);

$totalTables = getRow("SELECT COUNT(*) as count FROM cafe_tables WHERE is_active = 1")['count'] ?? 0;
$activeTables = getRow("SELECT COUNT(*) as count FROM table_sessions WHERE status = 'active'")['count'] ?? 0;
$availableTables = max(0, $totalTables - $activeTables);

// إحصائيات مبيعات اليوم المالية
$todaySalesRow = getRow(
    "SELECT COALESCE(SUM(total_amount), 0) as total, COUNT(*) as orders_count 
     FROM orders 
     WHERE DATE(created_at) = CURDATE() AND status != 'cancelled'"
);
$todayOrdersTotal = floatval($todaySalesRow['total'] ?? 0);
$todayOrdersCount = intval($todaySalesRow['orders_count'] ?? 0);

$todayPsRevenue = floatval(getRow(
    "SELECT COALESCE(SUM(gaming_charge), 0) as total 
     FROM gaming_sessions 
     WHERE DATE(started_at) = CURDATE() AND status = 'completed'"
)['total'] ?? 0);

$todayBilliardRevenue = floatval(getRow(
    "SELECT COALESCE(SUM(gaming_charge), 0) as total 
     FROM billiard_sessions 
     WHERE DATE(started_at) = CURDATE() AND status = 'completed'"
)['total'] ?? 0);

$todayExpenses = floatval(getRow(
    "SELECT COALESCE(SUM(amount), 0) as total 
     FROM expenses 
     WHERE date = CURDATE()"
)['total'] ?? 0);

$todayTotalRevenue = $todayOrdersTotal + $todayPsRevenue + $todayBilliardRevenue;
$todayEstimatedNet = max(0, $todayTotalRevenue - $todayExpenses);

// أحدث 8 طلبات لليوم
$recentOrders = getRows(
    "SELECT o.*, u.full_name as cashier_name 
     FROM orders o 
     LEFT JOIN users u ON o.created_by = u.id 
     ORDER BY o.id DESC LIMIT 8"
);

// جلسات البلايستيشن النشطة حالياً
$activeSessions = getRows(
    "SELECT gs.*, gd.display_name as device_name,
            TIMESTAMPDIFF(MINUTE, gs.started_at, NOW()) as elapsed_minutes
     FROM gaming_sessions gs 
     JOIN gaming_devices gd ON gs.device_id = gd.id 
     WHERE gs.status IN ('active', 'paused') 
     ORDER BY gs.started_at DESC"
);

include 'includes/header.php';
?>

<style>
.dashboard-quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
    margin-bottom: 22px;
}

.quick-action-card {
    background: #ffffff;
    border-radius: 14px;
    padding: 16px 18px;
    border: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 14px;
    text-decoration: none;
    color: inherit;
    box-shadow: 0 2px 6px rgba(0,0,0,0.02);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.quick-action-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.06);
    border-color: #cbd5e1;
}

.quick-action-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    flex-shrink: 0;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 14px;
    margin-bottom: 22px;
}

.stat-card {
    background: #ffffff;
    border-radius: 14px;
    padding: 18px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 6px rgba(0,0,0,0.02);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.stat-label {
    font-size: 12px;
    font-weight: 700;
    color: #64748b;
    margin-bottom: 6px;
}

.stat-value {
    font-size: 22px;
    font-weight: 900;
    color: #0f172a;
    line-height: 1.2;
}
</style>

<!-- Top Quick Action Hero Banner -->
<div class="dashboard-quick-actions">
    
    <a href="<?php echo BASE_URL; ?>modules/pos/index.php" class="quick-action-card" style="background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%); color:white;">
        <div class="quick-action-icon" style="background: rgba(255,255,255,0.2);">
            ☕
        </div>
        <div>
            <div style="font-size: 16px; font-weight: 800;">كاشير الكافيه POS</div>
            <div style="font-size: 11.5px; opacity: 0.9;">أوردرات سريعة بلمسة واحدة</div>
        </div>
    </a>

    <a href="<?php echo BASE_URL; ?>modules/gaming/index.php" class="quick-action-card">
        <div class="quick-action-icon" style="background: #eff6ff; color: #2563eb;">
            🎮
        </div>
        <div>
            <div style="font-size: 14px; font-weight: 800; color: #0f172a;">صالة البلايستيشن</div>
            <div style="font-size: 11.5px; color: #64748b;"><?php echo $activePsSessions; ?> مشغول • <?php echo $availablePs; ?> متاح</div>
        </div>
    </a>

    <a href="<?php echo BASE_URL; ?>modules/billiards/index.php" class="quick-action-card">
        <div class="quick-action-icon" style="background: #ecfdf5; color: #059669;">
            🎱
        </div>
        <div>
            <div style="font-size: 14px; font-weight: 800; color: #0f172a;">طاولات البلياردو</div>
            <div style="font-size: 11.5px; color: #64748b;"><?php echo $activeBilliard; ?> مشغول • <?php echo $availableBilliard; ?> متاح</div>
        </div>
    </a>

    <a href="<?php echo BASE_URL; ?>modules/tables/index.php" class="quick-action-card">
        <div class="quick-action-icon" style="background: #fef3c7; color: #d97706;">
            🪑
        </div>
        <div>
            <div style="font-size: 14px; font-weight: 800; color: #0f172a;">طاولات الصالة</div>
            <div style="font-size: 11.5px; color: #64748b;"><?php echo $activeTables; ?> مشغول • <?php echo $availableTables; ?> متاح</div>
        </div>
    </a>

    <a href="<?php echo BASE_URL; ?>modules/shifts/index.php" class="quick-action-card">
        <div class="quick-action-icon" style="background: #f3e8ff; color: #7c3aed;">
            ⏱️
        </div>
        <div>
            <div style="font-size: 14px; font-weight: 800; color: #0f172a;">الورديات والخزينة</div>
            <div style="font-size: 11.5px; color: <?php echo $currentShift ? '#059669' : '#dc2626'; ?>; font-weight:bold;">
                <?php echo $currentShift ? '🟢 وردية نشطة' : '⚠️ الوردية مغلقة'; ?>
            </div>
        </div>
    </a>

</div>

<!-- Financial Stats for Today -->
<div class="stats-grid">
    
    <div class="stat-card" style="border-top: 4px solid #ea580c;">
        <div>
            <div class="stat-label">مبيعات الكافيه لليوم</div>
            <div class="stat-value" style="color: #ea580c;"><?php echo number_format($todayOrdersTotal, 2); ?> ج.م</div>
        </div>
        <div style="font-size: 11.5px; color: #64748b; margin-top: 8px;">
            <span>📦 <?php echo $todayOrdersCount; ?> طلبات مسجلة</span>
        </div>
    </div>

    <div class="stat-card" style="border-top: 4px solid #2563eb;">
        <div>
            <div class="stat-label">إيراد ألعاب البلايستيشن</div>
            <div class="stat-value" style="color: #2563eb;"><?php echo number_format($todayPsRevenue, 2); ?> ج.م</div>
        </div>
        <div style="font-size: 11.5px; color: #64748b; margin-top: 8px;">
            <span>🎮 جلسات مكتملة ومحصلة</span>
        </div>
    </div>

    <div class="stat-card" style="border-top: 4px solid #059669;">
        <div>
            <div class="stat-label">إيراد صالة البلياردو</div>
            <div class="stat-value" style="color: #059669;"><?php echo number_format($todayBilliardRevenue, 2); ?> ج.م</div>
        </div>
        <div style="font-size: 11.5px; color: #64748b; margin-top: 8px;">
            <span>🎱 حساب وقت وأجوام</span>
        </div>
    </div>

    <div class="stat-card" style="border-top: 4px solid #dc2626;">
        <div>
            <div class="stat-label">مصروفات اليوم</div>
            <div class="stat-value" style="color: #dc2626;"><?php echo number_format($todayExpenses, 2); ?> ج.م</div>
        </div>
        <div style="font-size: 11.5px; color: #64748b; margin-top: 8px;">
            <span>💸 خامات ومشتريات ونثريات</span>
        </div>
    </div>

    <div class="stat-card" style="border-top: 4px solid #10b981; background: #f0fdf4;">
        <div>
            <div class="stat-label" style="color: #065f46;">صافي الإيراد التقديري اليوم</div>
            <div class="stat-value" style="color: #15803d;"><?php echo number_format($todayEstimatedNet, 2); ?> ج.م</div>
        </div>
        <div style="font-size: 11.5px; color: #047857; margin-top: 8px; font-weight:bold;">
            <span>إجمالي الإيرادات - المصروفات</span>
        </div>
    </div>

</div>

<!-- Active Gaming Sessions + Recent Orders Grid -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
    
    <!-- Active PlayStation Sessions -->
    <div style="background:#fff; border-radius:14px; border:1px solid #e2e8f0; padding:18px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
            <h3 style="font-size:15px; font-weight:800; color:#0f172a; margin:0;">🎮 أجهزة البلايستيشن النشطة حالياً</h3>
            <a href="modules/gaming/index.php" style="font-size:12px; color:#2563eb; text-decoration:none; font-weight:bold;">عرض الصالة &larr;</a>
        </div>

        <?php if (empty($activeSessions)): ?>
            <div style="text-align:center; padding:30px; color:#94a3b8; font-size:13px;">
                لا توجد أجهزة قيد التشغيل حالياً (جميع الأجهزة متاحة)
            </div>
        <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:8px;">
                <?php foreach ($activeSessions as $sess): ?>
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:10px 14px; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <div style="font-weight:800; font-size:13.5px; color:#0f172a;"><?php echo htmlspecialchars($sess['device_name']); ?></div>
                        <div style="font-size:11.5px; color:#64748b;">
                            <?php echo $sess['session_type'] === 'multi' ? '👥 جماعي (مالتي)' : '🎮 فردي (سنجل)'; ?> • منذ <?php echo $sess['elapsed_minutes']; ?> دقيقة
                        </div>
                    </div>
                    <div>
                        <a href="modules/gaming/index.php" style="background:#ea580c; color:white; padding:5px 12px; border-radius:6px; text-decoration:none; font-size:11.5px; font-weight:bold;">
                            تحكم
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recent Orders -->
    <div style="background:#fff; border-radius:14px; border:1px solid #e2e8f0; padding:18px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
            <h3 style="font-size:15px; font-weight:800; color:#0f172a; margin:0;">📋 أحدث طلبات الكاشير لليوم</h3>
            <a href="modules/pos/index.php" style="font-size:12px; color:#ea580c; text-decoration:none; font-weight:bold;">فتح الكاشير &larr;</a>
        </div>

        <?php if (empty($recentOrders)): ?>
            <div style="text-align:center; padding:30px; color:#94a3b8; font-size:13px;">
                لا توجد طلبات مسجلة اليوم حتى الآن
            </div>
        <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:8px;">
                <?php foreach ($recentOrders as $ord): ?>
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:10px 14px; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <div style="font-weight:800; font-size:13px; color:#0f172a;">#<?php echo htmlspecialchars($ord['order_number']); ?></div>
                        <div style="font-size:11px; color:#64748b;">
                            <?php 
                            $types = ['takeaway' => 'تيك أواي', 'dine_in' => 'طاولة صالة', 'gaming_ps' => 'بلايستيشن', 'gaming_billiard' => 'بلياردو'];
                            echo $types[$ord['order_type']] ?? $ord['order_type'];
                            ?> • <?php echo date('h:i A', strtotime($ord['created_at'])); ?>
                        </div>
                    </div>
                    <div style="text-align:left;">
                        <div style="font-weight:900; font-size:14px; color:#ea580c;"><?php echo number_format($ord['total_amount'], 2); ?> ج.م</div>
                        <div style="font-size:10.5px; color:<?php echo $ord['payment_status'] === 'paid' ? '#059669' : '#d97706'; ?>; font-weight:bold;">
                            <?php echo $ord['payment_status'] === 'paid' ? 'مدفوع' : 'معلق'; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php include 'includes/footer.php'; ?>
