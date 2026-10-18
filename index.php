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

// Financial stats of today are NOT computed here anymore: the numbers are
// masked cards revealed per-card via modules/dashboard/ajax.php after the
// user verifies their own password (see dashboard JS near the footer).
// توريدات مباشرة بلا فواتير (كلها — ليست مقيدة بيوم)
$openSuppliesCount = 0;
if (function_exists('columnExists') && columnExists('product_stock_movements', 'movement_type')) {
    $openSuppliesCount = intval(getRow(
        "SELECT COUNT(*) as c FROM product_stock_movements
         WHERE movement_type = 'supply' AND linked_invoice_id IS NULL"
    )['c'] ?? 0);
}

// أحدث 8 طلبات لليوم
$recentOrders = getRows(
    "SELECT o.*, u.full_name as cashier_name 
     FROM orders o 
     LEFT JOIN users u ON o.created_by = u.id 
     ORDER BY o.id DESC LIMIT 3"
);

// جلسات البلايستيشن النشطة حالياً
$activeSessions = getRows(
    "SELECT gs.*, gd.display_name as device_name,
            TIMESTAMPDIFF(MINUTE, gs.started_at, NOW()) as elapsed_minutes
     FROM gaming_sessions gs 
     JOIN gaming_devices gd ON gs.device_id = gd.id 
     WHERE gs.status IN ('active', 'paused') 
     ORDER BY gs.started_at DESC
     LIMIT 3"
);

$activeBilliardSessions = getRows(
    "SELECT bs.*, bt.display_name as table_name,
            TIMESTAMPDIFF(MINUTE, bs.started_at, NOW()) as elapsed_minutes
     FROM billiard_sessions bs 
     JOIN billiard_tables bt ON bs.table_id = bt.id 
     WHERE bs.status IN ('active', 'paused') 
     ORDER BY bs.started_at DESC
     LIMIT 3"
);

include 'includes/header.php';
include 'includes/navbar.php';
?>

<style>
html, body {
    overflow: hidden !important;
    height: 100%;
}
.container {
    max-height: calc(100vh - 24px);
    overflow: hidden;
}
.dashboard-quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 8px;
    margin-bottom: 12px;
}

.quick-action-card {
    background: #ffffff;
    border-radius: 14px;
    padding: 10px 14px;
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
    gap: 8px;
    margin-bottom: 12px;
}

.stat-card {
    background: #ffffff;
    border-radius: 14px;
    padding: 10px 14px;
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

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; background: #ffffff; padding: 10px 16px; border-radius: 14px; border: 1px solid #e2e8f0; box-shadow: 0 2px 6px rgba(0,0,0,0.02);">
        <div>
            <h1 style="font-size: 20px; font-weight: 800; color: #0f172a; margin: 0 0 4px 0; display: flex; align-items: center; gap: 8px;">
                <span>☕</span> لوحة تحكم كافيه وصالة ألعاب عروسة النيل
            </h1>
            <div style="font-size: 13px; color: #64748b;">
                مرحباً بك، <strong><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'المدير'); ?></strong> • متابعة مباشرة للمبيعات، جلسات البلايستيشن، طاولات البلياردو والورديات
            </div>
        </div>
        <div style="display: flex; gap: 10px;">
            </a>
            <a href="index.php" style="background: #f8fafc; color: #334155; border: 1px solid #cbd5e1; padding: 9px 14px; border-radius: 8px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                <span>🔄</span> تحديث
            </a>
        </div>
    </div>

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

    <?php if (hasPermission('inventory.view')): ?>
    <a href="<?php echo BASE_URL; ?>modules/inventory/index.php?tab=supplies" class="quick-action-card">
        <div class="quick-action-icon" style="background: #ecfdf5; color: #059669;">
            🚚
        </div>
        <div>
            <div style="font-size: 14px; font-weight: 800; color: #0f172a;">التوريد المباشر</div>
            <div style="font-size: 11.5px; color: #64748b;"><?php echo $openSuppliesCount; ?> توريد بلا فاتورة</div>
        </div>
    </a>
    <?php endif; ?>

</div>

<!-- Financial Stats for Today (masked — reveal per card with own password via the eye button) -->
<div class="stats-grid">
    
    <div class="stat-card" style="border-top: 4px solid #ea580c; position: relative;">
        <button type="button" class="dash-eye" data-key="cafe_orders" title="إظهار الرقم بكلمة المرور"
                style="position:absolute; top:10px; left:10px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; width:30px; height:30px; font-size:14px; cursor:pointer; line-height:1;">🙈</button>
        <div>
            <div class="stat-label">مبيعات الكافيه لليوم</div>
            <div class="stat-value" style="color: #ea580c;" data-val-slot="cafe_orders">🔒 ••••</div>
        </div>
        <div style="font-size: 11.5px; color: #64748b; margin-top: 8px;" data-sub-slot="cafe_orders_sub">
            <span>📦 •• طلبات مسجلة</span>
        </div>
    </div>

    <div class="stat-card" style="border-top: 4px solid #2563eb; position: relative;">
        <button type="button" class="dash-eye" data-key="ps_revenue" title="إظهار الرقم بكلمة المرور"
                style="position:absolute; top:10px; left:10px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; width:30px; height:30px; font-size:14px; cursor:pointer; line-height:1;">🙈</button>
        <div>
            <div class="stat-label">إيراد ألعاب البلايستيشن</div>
            <div class="stat-value" style="color: #2563eb;" data-val-slot="ps_revenue">🔒 ••••</div>
        </div>
        <div style="font-size: 11.5px; color: #64748b; margin-top: 8px;">
            <span>🎮 جلسات مكتملة ومحصلة</span>
        </div>
    </div>

    <div class="stat-card" style="border-top: 4px solid #059669; position: relative;">
        <button type="button" class="dash-eye" data-key="billiard_revenue" title="إظهار الرقم بكلمة المرور"
                style="position:absolute; top:10px; left:10px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; width:30px; height:30px; font-size:14px; cursor:pointer; line-height:1;">🙈</button>
        <div>
            <div class="stat-label">إيراد صالة البلياردو</div>
            <div class="stat-value" style="color: #059669;" data-val-slot="billiard_revenue">🔒 ••••</div>
        </div>
        <div style="font-size: 11.5px; color: #64748b; margin-top: 8px;">
            <span>🎱 حساب وقت وأجوام</span>
        </div>
    </div>

    <div class="stat-card" style="border-top: 4px solid #dc2626; position: relative;">
        <button type="button" class="dash-eye" data-key="expenses" title="إظهار الرقم بكلمة المرور"
                style="position:absolute; top:10px; left:10px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; width:30px; height:30px; font-size:14px; cursor:pointer; line-height:1;">🙈</button>
        <div>
            <div class="stat-label">مصروفات اليوم</div>
            <div class="stat-value" style="color: #dc2626;" data-val-slot="expenses">🔒 ••••</div>
        </div>
        <div style="font-size: 11.5px; color: #64748b; margin-top: 8px;">
            <span>💸 خامات ومشتريات ونثريات</span>
        </div>
    </div>

    <div class="stat-card" style="border-top: 4px solid #10b981; background: #f0fdf4; position: relative;">
        <button type="button" class="dash-eye" data-key="estimated_net" title="إظهار الرقم بكلمة المرور"
                style="position:absolute; top:10px; left:10px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; width:30px; height:30px; font-size:14px; cursor:pointer; line-height:1;">🙈</button>
        <div>
            <div class="stat-label" style="color: #065f46;">صافي الإيراد التقديري اليوم</div>
            <div class="stat-value" style="color: #15803d;" data-val-slot="estimated_net">🔒 ••••</div>
        </div>
        <div style="font-size: 11.5px; color: #047857; margin-top: 8px; font-weight:bold;">
            <span>إجمالي الإيرادات - المصروفات</span>
        </div>
    </div>

</div>

<!-- Active Gaming Sessions + Recent Orders Grid -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
    
    <!-- Active PlayStation Sessions -->
    <div style="background:#fff; border-radius:14px; border:1px solid #e2e8f0; padding:18px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
            <h3 style="font-size:15px; font-weight:800; color:#0f172a; margin:0;">🎮 جلسات نشطة (PS + بلياردو)</h3>
            <a href="modules/gaming/index.php" style="font-size:12px; color:#2563eb; text-decoration:none; font-weight:bold;">عرض الصالة &larr;</a>
        </div>

        <?php if (empty($activeSessions) && empty($activeBilliardSessions)): ?>
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

        <?php if (!empty($activeBilliardSessions)): ?>
            <div style="font-size:12px; font-weight:800; color:#059669; margin:10px 0 6px;">🎱 بلياردو (<?php echo count($activeBilliardSessions); ?>)</div>
            <div style="display:flex; flex-direction:column; gap:6px;">
                <?php foreach ($activeBilliardSessions as $bsess): ?>
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:8px 12px; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <div style="font-weight:800; font-size:13px; color:#0f172a;"><?php echo htmlspecialchars($bsess['table_name']); ?></div>
                        <div style="font-size:11px; color:#64748b;">
                            <?php echo $bsess['billing_mode'] === 'per_game' ? '🎱 بالجيم' : '⏱️ بالساعة'; ?> • <?php echo (int)$bsess['elapsed_minutes']; ?> دقيقة
                        </div>
                    </div>
                    <a href="modules/billiards/index.php" style="background:#059669; color:white; padding:5px 10px; border-radius:6px; text-decoration:none; font-size:11px; font-weight:bold;">
                        متابعة
                    </a>
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

</div><!-- End container -->

<!-- Dashboard secret-figures reveal (password-gated eye) -->
<script>
let dashRevealBusy = false;

document.querySelectorAll('.dash-eye').forEach(btn => {
    btn.addEventListener('click', () => {
        const key = btn.dataset.key;
        const slot = document.querySelector(`[data-val-slot="${key}"]`);
        if (!slot) return;
        if (slot.dataset.revealed === '1') {
            // Hide again (no password to re-hide)
            slot.dataset.revealed = '0';
            slot.textContent = '🔒 ••••';
            btn.textContent = '🙈';
            const sub = document.querySelector(`[data-sub-slot="${key}_sub"]`);
            if (sub) sub.innerHTML = '<span>📦 •• طلبات مسجلة</span>';
            return;
        }
        dashRevealCard(key, btn);
    });
});

function dashPasswordDialog() {
    return new Promise(resolve => {
        const d = appDialogBase();
        appDialogIcon(d.box, '🔒', '#fee2e2');
        const t = document.createElement('div');
        t.style.cssText = 'font-weight:800;font-size:15px;color:#0f172a;margin-bottom:6px;';
        t.textContent = 'كلمة المرور مطلوبة';
        d.box.appendChild(t);
        appDialogText(d.box, 'أدخل كلمة المرور الخاصة بك لعرض رقم هذا البوكس');
        const input = document.createElement('input');
        input.type = 'password';
        input.autocomplete = 'new-password';
        input.style.cssText = 'width:100%;padding:11px 14px;border-radius:10px;border:1.5px solid #cbd5e1;font-family:inherit;font-size:15px;font-weight:700;text-align:center;letter-spacing:3px;margin-bottom:6px;';
        d.box.appendChild(input);
        d.overlay.addEventListener('click', e => { if (e.target === d.overlay) { d.overlay.remove(); resolve(null); } });
        appDialogButtons(d.box, [
            { label: 'إلغاء', bg: '#f1f5f9', color: '#334155', onClick: () => { d.overlay.remove(); resolve(null); } },
            { label: 'تأكيد الإظهار', bg: '#3b82f6', color: '#fff', onClick: () => { const v = input.value; d.overlay.remove(); resolve(v); } }
        ]);
        input.addEventListener('keydown', e => {
            if (e.key === 'Enter') { e.preventDefault(); const v = input.value; d.overlay.remove(); resolve(v); }
        });
        setTimeout(() => input.focus(), 50);
    });
}

async function dashRevealCard(key, btn) {
    if (dashRevealBusy) return;
    dashRevealBusy = true;
    try {
        const pwd = await dashPasswordDialog();
        if (!pwd) return;
        const fd = new FormData();
        fd.append('password', pwd);
        const res = await fetch('modules/dashboard/ajax.php?action=verify_and_get', { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.success) {
            appAlert(data.error || 'غير مسموح', 'error');
            return;
        }
        const slot = document.querySelector(`[data-val-slot="${key}"]`);
        if (!slot) return;
        const sub = document.querySelector(`[data-sub-slot="${key}_sub"]`);
        if (key === 'cafe_orders') {
            slot.textContent = `${parseFloat(data.orders_total).toFixed(2)} ج.م`;
            if (sub) sub.innerHTML = `<span>📦 ${data.orders_count} طلبات مسجلة</span>`;
        } else {
            const mm = {
                ps_revenue: data.ps_revenue,
                billiard_revenue: data.billiard_revenue,
                expenses: data.expenses,
                estimated_net: data.estimated_net
            };
            slot.textContent = `${parseFloat(mm[key] || 0).toFixed(2)} ج.م`;
        }
        slot.dataset.revealed = '1';
        btn.textContent = '👁';
    } catch (err) {
        console.error(err);
        appAlert('حدث خطأ في الاتصال بالخادم', 'error');
    } finally {
        dashRevealBusy = false;
    }
}
</script>

<?php include 'includes/footer.php'; ?>
