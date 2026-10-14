<?php
/**
 * Shifts & Cash Drawer Management - إدارة الورديات والخزينة
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';

requireLogin();

if (!hasPermission('shifts.view')) {
    showAccessDenied();
}

$pageTitle = 'الورديات وحركات الخزينة';

// جلب الوردية النشطة للمستخدم
$currentShift = getRow(
    "SELECT s.*, u.full_name as cashier_name 
     FROM shifts s 
     LEFT JOIN users u ON s.user_id = u.id 
     WHERE s.user_id = ? AND s.status = 'open' 
     ORDER BY s.id DESC LIMIT 1",
    [getCurrentUserId()]
);

// جلب آخر الورديات المغلقة
$recentShifts = getRows(
    "SELECT s.*, u.full_name as cashier_name 
     FROM shifts s 
     LEFT JOIN users u ON s.user_id = u.id 
     WHERE s.status = 'closed' 
     ORDER BY s.id DESC LIMIT 10"
);

// حساب إجماليات الوردية الحالية
$opening = $currentShift ? floatval($currentShift['opening_balance']) : 0;
$salesCash = $currentShift ? floatval($currentShift['total_sales_cash']) : 0;
$cashIn = $currentShift ? floatval($currentShift['total_cash_in']) : 0;
$cashOut = $currentShift ? floatval($currentShift['total_cash_out']) : 0;
$expenses = $currentShift ? floatval($currentShift['total_expenses']) : 0;
$expectedCashInDrawer = ($opening + $salesCash + $cashIn) - ($cashOut + $expenses);

// حركات النقدية للوردية المفتوحة
$cashMovements = [];
if ($currentShift) {
    $movementsPage = max(1, (int)($_GET['movements_page'] ?? 1));
    $movementsPerPage = 10;
    $movementsTotal = 0;
    $movementsPages = 1;
    $movementsTotalRow = getRow("SELECT COUNT(*) AS total FROM cash_movements WHERE shift_id = ?", [$currentShift['id']]);
    $movementsTotal = (int)($movementsTotalRow['total'] ?? 0);
    $movementsPages = max(1, (int)ceil($movementsTotal / $movementsPerPage));
    $movementsPage = min($movementsPage, $movementsPages);
    $movementsOffset = ($movementsPage - 1) * $movementsPerPage;
    $cashMovements = getRows(
        "SELECT * FROM cash_movements WHERE shift_id = ? ORDER BY id DESC LIMIT {$movementsPerPage} OFFSET {$movementsOffset}",
        [$currentShift['id']]
    );
}

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container">

<div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; background:#fff; padding:16px 20px; border-radius:12px; border:1px solid #e2e8f0; box-shadow: 0 2px 6px rgba(0,0,0,0.02);">
    <div>
        <h2 style="font-size: 18px; font-weight: 800; color: #0f172a; margin: 0 0 4px 0;">⏱️ إدارة الورديات والخزينة النقدية</h2>
        <div style="font-size: 12.5px; color: #64748b;">متابعة عهدة الدرج، النقدية الفعلية، تقفيل الشيفت، واكتشاف العجز أو الزيادة آلياً</div>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="../../index.php" style="background:#fff; color:#334155; font-weight:700; font-size:12px; padding:8px 14px; border-radius:8px; border:1px solid #cbd5e1; text-decoration:none; display:inline-flex; align-items:center; gap:4px;">
            <span>🔙</span> الرئيسية
        </a>
        <?php if ($currentShift): ?>
            <button onclick="document.getElementById('movementModal').style.display='flex'" style="background:#0284c7; color:white; border:none; padding:8px 14px; border-radius:8px; font-weight:bold; cursor:pointer;">
                💸 تسجيل حركة نقدية (سحب/إيداع)
            </button>
            <button onclick="document.getElementById('closeShiftModal').style.display='flex'" style="background:#dc2626; color:white; border:none; padding:8px 16px; border-radius:8px; font-weight:bold; cursor:pointer;">
                🔒 إغلاق وتقفيل الوردية
            </button>
        <?php else: ?>
            <button onclick="document.getElementById('openShiftModal').style.display='flex'" style="background:#16a34a; color:white; border:none; padding:10px 20px; border-radius:8px; font-size:14px; font-weight:bold; cursor:pointer;">
                🟢 فتح وردية جديدة الآن
            </button>
        <?php endif; ?>
    </div>
</div>

<?php if (!$currentShift): ?>
<!-- Empty State: No active shift -->
<div style="background:#fff; border-radius:16px; border:2px dashed #cbd5e1; padding:50px 20px; text-align:center; max-width:600px; margin:40px auto;">
    <div style="font-size:54px; margin-bottom:12px;">⏱️</div>
    <h3 style="font-size:20px; font-weight:800; color:#0f172a; margin-bottom:8px;">لا توجد وردية مفتوحة حالياً</h3>
    <p style="color:#64748b; font-size:13.5px; margin-bottom:20px;">
        لبدء تسجيل المبيعات وألعاب البلايستيشن وحساب النقدية بدقة، يرجى فتح وردية عمل جديدة وتحديد رصيد العهدة الافتتاحي.
    </p>
    <button onclick="document.getElementById('openShiftModal').style.display='flex'" style="background:#16a34a; color:white; border:none; border-radius:10px; padding:12px 28px; font-size:15px; font-weight:bold; cursor:pointer; box-shadow:0 4px 12px rgba(22,163,74,0.3);">
        🟢 فتح وردية عمل جديدة
    </button>
</div>
<?php else: ?>
<!-- Active Shift Dashboard -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 14px; margin-bottom: 20px;">
    
    <div style="background:#fff; border-radius:12px; padding:16px; border:1px solid #e2e8f0; border-top:4px solid #3b82f6;">
        <div style="font-size:11.5px; font-weight:700; color:#64748b;">رقم الوردية الحالية</div>
        <div style="font-size:17px; font-weight:900; color:#1e293b; margin:6px 0;">#<?php echo htmlspecialchars($currentShift['shift_number']); ?></div>
        <div style="font-size:11px; color:#10b981; font-weight:bold;">🟢 مفتوحة منذ <?php echo date('h:i A', strtotime($currentShift['opened_at'])); ?></div>
    </div>

    <div style="background:#fff; border-radius:12px; padding:16px; border:1px solid #e2e8f0; border-top:4px solid #10b981;">
        <div style="font-size:11.5px; font-weight:700; color:#64748b;">الرصيد الافتتاحي (العهدة)</div>
        <div style="font-size:20px; font-weight:900; color:#059669; margin:6px 0;"><?php echo number_format($opening, 2); ?> ج.م</div>
        <div style="font-size:11px; color:#64748b;">بداية الشيفت</div>
    </div>

    <div style="background:#fff; border-radius:12px; padding:16px; border:1px solid #e2e8f0; border-top:4px solid #ea580c;">
        <div style="font-size:11.5px; font-weight:700; color:#64748b;">مبيعات الكاش النقدية</div>
        <div style="font-size:20px; font-weight:900; color:#ea580c; margin:6px 0;"><?php echo number_format($salesCash, 2); ?> ج.م</div>
        <div style="font-size:11px; color:#64748b;">مبيعات نقدية بالدرج</div>
    </div>

    <div style="background:#fff; border-radius:12px; padding:16px; border:1px solid #e2e8f0; border-top:4px solid #8b5cf6;">
        <div style="font-size:11.5px; font-weight:700; color:#64748b;">مبيعات إلكترونية (فيزا / إنستاباي)</div>
        <div style="font-size:20px; font-weight:900; color:#7c3aed; margin:6px 0;"><?php echo number_format(floatval($currentShift['total_sales_card']) + floatval($currentShift['total_sales_wallet']), 2); ?> ج.م</div>
        <div style="font-size:11px; color:#64748b;">غير مضافة للدرج النقدي</div>
    </div>

    <div style="background:linear-gradient(135deg, #0f172a, #1e293b); color:#fff; border-radius:12px; padding:16px; border-top:4px solid #38bdf8;">
        <div style="font-size:11.5px; font-weight:700; color:#94a3b8;">النقدية المتوقعة في الدرج حالياً</div>
        <div style="font-size:22px; font-weight:900; color:#38bdf8; margin:6px 0;"><?php echo number_format($expectedCashInDrawer, 2); ?> ج.م</div>
        <div style="font-size:11px; color:#cbd5e1;">عهدة + كاش مبيعات + إيداعات - سحوبات</div>
    </div>

</div>

<!-- Recent Movements Table in active shift -->
<div style="background:#fff; border-radius:14px; border:1px solid #e2e8f0; padding:18px; margin-bottom:24px;">
    <h3 style="font-size:15px; font-weight:800; color:#0f172a; margin-bottom:12px;">سجل الحركات النقدية في الوردية الحالية</h3>
    <?php if (empty($cashMovements)): ?>
        <div style="text-align:center; padding:20px; color:#94a3b8; font-size:13px;">لا توجد حركات نقدية مسجلة حتى الآن</div>
    <?php else: ?>
    <table class="table" style="width:100%; border-collapse:collapse; font-size:13px;">
        <thead>
            <tr style="border-bottom:2px solid #e2e8f0; text-align:right;">
                <th style="padding:8px;">الوقت</th>
                <th style="padding:8px;">النوع</th>
                <th style="padding:8px;">المبلغ</th>
                <th style="padding:8px;">طريقة الدفع</th>
                <th style="padding:8px;">البيان</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($cashMovements as $mov): ?>
            <tr style="border-bottom:1px solid #f1f5f9;">
                <td style="padding:8px;"><?php echo date('h:i A', strtotime($mov['created_at'])); ?></td>
                <td style="padding:8px;">
                    <?php 
                    $typeLabels = [
                        'sale' => '<span style="color:#059669; font-weight:bold;">مبيعات</span>',
                        'cash_in' => '<span style="color:#2563eb; font-weight:bold;">إيداع نقدي</span>',
                        'cash_out' => '<span style="color:#dc2626; font-weight:bold;">سحب نقدي</span>',
                        'expense' => '<span style="color:#d97706; font-weight:bold;">مصروف</span>',
                    ];
                    echo $typeLabels[$mov['movement_type']] ?? $mov['movement_type'];
                    ?>
                </td>
                <td style="padding:8px; font-weight:bold;"><?php echo number_format($mov['amount'], 2); ?> ج.م</td>
                <td style="padding:8px;"><?php echo $mov['payment_method']; ?></td>
                <td style="padding:8px; color:#64748b;"><?php echo htmlspecialchars($mov['description']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
        <?php if ($movementsPages > 1): ?>
        <div style="display:flex; justify-content:center; align-items:center; gap:6px; flex-wrap:wrap; margin-top:14px; direction:rtl;">
            <?php if ($movementsPage > 1): ?>
                <a href="?movements_page=<?php echo $movementsPage - 1; ?>" style="padding:6px 12px; border:1px solid #cbd5e1; border-radius:7px; color:#334155; text-decoration:none; font-size:12px; font-weight:700;">السابق</a>
            <?php endif; ?>
            <?php for ($page = 1; $page <= $movementsPages; $page++): ?>
                <a href="?movements_page=<?php echo $page; ?>" style="min-width:32px; padding:6px 9px; text-align:center; border:1px solid <?php echo $page === $movementsPage ? '#0284c7' : '#cbd5e1'; ?>; border-radius:7px; background:<?php echo $page === $movementsPage ? '#0284c7' : '#fff'; ?>; color:<?php echo $page === $movementsPage ? '#fff' : '#334155'; ?>; text-decoration:none; font-size:12px; font-weight:700;"><?php echo $page; ?></a>
            <?php endfor; ?>
            <?php if ($movementsPage < $movementsPages): ?>
                <a href="?movements_page=<?php echo $movementsPage + 1; ?>" style="padding:6px 12px; border:1px solid #cbd5e1; border-radius:7px; color:#334155; text-decoration:none; font-size:12px; font-weight:700;">التالي</a>
            <?php endif; ?>
        </div>
        <div style="text-align:center; color:#64748b; font-size:11px; margin-top:7px;">صفحة <?php echo $movementsPage; ?> من <?php echo $movementsPages; ?> (إجمالي <?php echo $movementsTotal; ?> حركة)</div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Previous Closed Shifts -->
<div style="background:#fff; border-radius:14px; border:1px solid #e2e8f0; padding:18px;">
    <h3 style="font-size:15px; font-weight:800; color:#0f172a; margin-bottom:12px;">سجل الورديات السابقة المغلقة</h3>
    <?php if (empty($recentShifts)): ?>
        <div style="text-align:center; padding:20px; color:#94a3b8; font-size:13px;">لا توجد ورديات سابقة</div>
    <?php else: ?>
    <table class="table" style="width:100%; border-collapse:collapse; font-size:13px;">
        <thead>
            <tr style="border-bottom:2px solid #e2e8f0; text-align:right;">
                <th style="padding:8px;">رقم الوردية</th>
                <th style="padding:8px;">الكاشير</th>
                <th style="padding:8px;">وقت الفتح</th>
                <th style="padding:8px;">وقت الإغلاق</th>
                <th style="padding:8px;">المتوقع</th>
                <th style="padding:8px;">الفعلي</th>
                <th style="padding:8px;">الفارق</th>
                <th style="padding:8px;">طباعة</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($recentShifts as $sh): ?>
            <tr style="border-bottom:1px solid #f1f5f9;">
                <td style="padding:8px; font-weight:bold;">#<?php echo htmlspecialchars($sh['shift_number']); ?></td>
                <td style="padding:8px;"><?php echo htmlspecialchars($sh['cashier_name']); ?></td>
                <td style="padding:8px;"><?php echo date('Y-m-d h:i A', strtotime($sh['opened_at'])); ?></td>
                <td style="padding:8px;"><?php echo date('h:i A', strtotime($sh['closed_at'])); ?></td>
                <td style="padding:8px;"><?php echo number_format($sh['expected_cash'], 2); ?> ج.م</td>
                <td style="padding:8px; font-weight:bold;"><?php echo number_format($sh['actual_cash'], 2); ?> ج.م</td>
                <td style="padding:8px; font-weight:bold; color:<?php echo floatval($sh['cash_variance']) < 0 ? '#dc2626' : (floatval($sh['cash_variance']) > 0 ? '#16a34a' : '#059669'); ?>;">
                    <?php 
                    $v = floatval($sh['cash_variance']);
                    echo $v == 0 ? 'مطابق' : (($v > 0 ? '+' : '') . number_format($v, 2) . ' ج.م');
                    ?>
                </td>
                <td style="padding:8px;">
                    <a href="receipt.php?id=<?php echo $sh['id']; ?>" target="_blank" style="background:#f1f5f9; color:#0f172a; padding:4px 8px; border-radius:6px; text-decoration:none; font-weight:bold; font-size:11px;">🖨️ تقرير</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Modal: Open Shift -->
<div class="game-modal-backdrop" id="openShiftModal" style="position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.6); display:none; align-items:center; justify-content:center; z-index:9999;">
    <div style="background:#fff; border-radius:16px; width:100%; max-width:380px; overflow:hidden;">
        <div style="padding:14px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="font-size:15px; font-weight:800; margin:0;">فتح وردية عمل جديدة</h3>
            <button onclick="document.getElementById('openShiftModal').style.display='none'" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <div style="padding:18px;">
            <label style="font-size:12.5px; font-weight:700; color:#475569; display:block; margin-bottom:6px;">الرصيد الافتتاحي في الدرج (عهدة البداية):</label>
            <input type="number" id="openingBalanceInput" value="0" min="0" step="10" style="width:100%; padding:10px; border-radius:8px; border:1.5px solid #cbd5e1; font-size:16px; font-weight:bold;">
            <div style="font-size:11px; color:#64748b; margin-top:6px;">المبلغ الموجود في الدرج كفكة في بداية الوردية.</div>
        </div>
        <div style="padding:12px 18px; background:#f8fafc; border-top:1px solid #e2e8f0; text-align:left;">
            <button onclick="submitOpenShift()" style="background:#16a34a; color:white; border:none; border-radius:8px; padding:10px 22px; font-weight:bold; cursor:pointer;">
                🟢 فتح الوردية الآن
            </button>
        </div>
    </div>
</div>

<!-- Modal: Record Cash Movement -->
<div class="game-modal-backdrop" id="movementModal" style="position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.6); display:none; align-items:center; justify-content:center; z-index:9999;">
    <div style="background:#fff; border-radius:16px; width:100%; max-width:380px; overflow:hidden;">
        <div style="padding:14px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="font-size:15px; font-weight:800; margin:0;">تسجيل حركة نقدية بالدرج</h3>
            <button onclick="document.getElementById('movementModal').style.display='none'" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <div style="padding:18px;">
            <label style="font-size:12.5px; font-weight:700; color:#475569; display:block; margin-bottom:6px;">نوع الحركة:</label>
            <select id="movementTypeSelect" style="width:100%; padding:9px; border-radius:8px; border:1px solid #cbd5e1; margin-bottom:12px;">
                <option value="cash_in">📥 إيداع نقدي إضافي في الدرج</option>
                <option value="cash_out">📤 سحب نقدي من الدرج (توريد للمدير)</option>
            </select>
            <label style="font-size:12.5px; font-weight:700; color:#475569; display:block; margin-bottom:6px;">المبلغ:</label>
            <input type="number" id="movementAmountInput" value="0" min="1" style="width:100%; padding:9px; border-radius:8px; border:1px solid #cbd5e1; margin-bottom:12px; font-weight:bold;">
            <label style="font-size:12.5px; font-weight:700; color:#475569; display:block; margin-bottom:6px;">البيان / السبب:</label>
            <input type="text" id="movementDescInput" placeholder="مثال: استلام فكة من الخزينة الرئيسية..." style="width:100%; padding:9px; border-radius:8px; border:1px solid #cbd5e1; font-family:inherit;">
        </div>
        <div style="padding:12px 18px; background:#f8fafc; border-top:1px solid #e2e8f0; text-align:left;">
            <button onclick="submitCashMovement()" style="background:#0284c7; color:white; border:none; border-radius:8px; padding:10px 22px; font-weight:bold; cursor:pointer;">
                💾 حفظ الحركة
            </button>
        </div>
    </div>
</div>

<!-- Modal: Close Shift -->
<div class="game-modal-backdrop" id="closeShiftModal" style="position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.6); display:none; align-items:center; justify-content:center; z-index:9999;">
    <div style="background:#fff; border-radius:16px; width:100%; max-width:420px; overflow:hidden;">
        <div style="padding:14px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="font-size:15px; font-weight:800; margin:0; color:#dc2626;">إغلاق وتقفيل الوردية</h3>
            <button onclick="document.getElementById('closeShiftModal').style.display='none'" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <div style="padding:18px;">
            <div style="background:#f8fafc; padding:12px; border-radius:8px; margin-bottom:14px; font-size:12.5px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                    <span>النقدية المتوقعة في الدرج:</span>
                    <strong style="color:#0284c7;"><?php echo number_format($expectedCashInDrawer, 2); ?> ج.م</strong>
                </div>
            </div>

            <label style="font-size:12.5px; font-weight:700; color:#0f172a; display:block; margin-bottom:6px;">النقدية الفعلية المحصورة في الدرج (عد النقدية):</label>
            <input type="number" id="actualCashInput" value="<?php echo $expectedCashInDrawer; ?>" min="0" oninput="checkShiftVariance(<?php echo $expectedCashInDrawer; ?>)" style="width:100%; padding:10px; border-radius:8px; border:2px solid #cbd5e1; font-size:16px; font-weight:900; margin-bottom:8px;">

            <div id="varianceStatusBox" style="padding:8px 12px; border-radius:8px; font-size:12px; font-weight:bold; margin-bottom:12px; display:none;"></div>

            <div id="varianceReasonBox" style="display:none; margin-bottom:12px;">
                <label style="font-size:12px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">سبب الفارق في النقدية:</label>
                <input type="text" id="varianceReasonInput" placeholder="اكتب سبب العجز أو الزيادة..." style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e1; font-family:inherit;">
            </div>

            <label style="font-size:12px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">ملاحظات الإغلاق (اختياري):</label>
            <input type="text" id="closingNotesInput" placeholder="أي ملاحظات عامة..." style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e1; font-family:inherit;">
        </div>
        <div style="padding:12px 18px; background:#f8fafc; border-top:1px solid #e2e8f0; text-align:left;">
            <button onclick="submitCloseShift()" style="background:#dc2626; color:white; border:none; border-radius:8px; padding:10px 24px; font-size:14px; font-weight:bold; cursor:pointer;">
                🔒 إغلاق الوردية وطباعة التقرير
            </button>
        </div>
    </div>
</div>

<script>
async function submitOpenShift() {
    const bal = document.getElementById('openingBalanceInput').value;
    const fd = new FormData();
    fd.append('opening_balance', bal);

    const res = await fetch('ajax.php?action=open_shift', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
        location.reload();
    } else {
        alert(data.error);
    }
}

async function submitCashMovement() {
    const type = document.getElementById('movementTypeSelect').value;
    const amount = document.getElementById('movementAmountInput').value;
    const desc = document.getElementById('movementDescInput').value;

    const fd = new FormData();
    fd.append('movement_type', type);
    fd.append('amount', amount);
    fd.append('description', desc);

    const res = await fetch('ajax.php?action=record_cash_movement', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
        location.reload();
    } else {
        alert(data.error);
    }
}

function checkShiftVariance(expected) {
    const actual = parseFloat(document.getElementById('actualCashInput').value) || 0;
    const diff = actual - expected;
    const box = document.getElementById('varianceStatusBox');
    const reasonBox = document.getElementById('varianceReasonBox');

    box.style.display = 'block';
    if (diff === 0) {
        box.style.background = '#ecfdf5';
        box.style.color = '#059669';
        box.textContent = '✅ النقدية متطابقة تماماً مع المتوقع';
        reasonBox.style.display = 'none';
    } else if (diff < 0) {
        box.style.background = '#fee2e2';
        box.style.color = '#dc2626';
        box.textContent = `⚠️ يوجد عجز في النقدية بقيمة: ${Math.abs(diff).toFixed(2)} ج.م`;
        reasonBox.style.display = 'block';
    } else {
        box.style.background = '#eff6ff';
        box.style.color = '#2563eb';
        box.textContent = `🎉 توجد زيادة في النقدية بقيمة: ${diff.toFixed(2)} ج.م`;
        reasonBox.style.display = 'block';
    }
}

async function submitCloseShift() {
    const actual = document.getElementById('actualCashInput').value;
    const notes = document.getElementById('closingNotesInput').value;
    const reason = document.getElementById('varianceReasonInput').value;

    if (!confirm('هل أنت متأكد من إغلاق وتقفيل الوردية الحالية؟ لا يمكن التراجع عن هذه الخطوة.')) return;

    const fd = new FormData();
    fd.append('actual_cash', actual);
    fd.append('closing_notes', notes);
    fd.append('variance_reason', reason);

    const res = await fetch('ajax.php?action=close_shift', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
        openReceiptModal(`receipt.php?id=${data.shift_id}`);
        location.reload();
    } else {
        alert(data.error);
    }
}
</script>
</div><!-- End container -->

<?php include '../../includes/footer.php'; ?>
