<?php
/**
 * Customer Profile — بروفايل العميل وحسابات كاملة
 * كل فواتيره وطلباته + محاسبة فردية/مجمعه + بحث وفلتر تاريخ (افتراضي اليوم)
 * + ترقيم + إجراءات لكل عنصر (بون/تعديل/ارجاع/محاسبه) + تسوية الكل والمحدد.
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
require_once '../../config/customer_accounts.php';
requirePermission('customers.view');

if (!function_exists('redirect')) {
    function redirect($path) { header('Location: ' . $path); exit; }
}

$id = (int)($_GET['id'] ?? 0);
$customer = getRow("SELECT * FROM customers WHERE id = ?", [$id]);

if (!$customer) {
    setError('العميل غير موجود');
    redirect('index.php');
}

$pageTitle = 'بروفايل العميل: ' . $customer['name'];
$settings = getAllSettings();

/** Payment events (settlements + general collections from balance) as timeline rows. */
function profile_payment_events($customerId) {
    $out = [];
    foreach (getRows("SELECT * FROM customer_settlements WHERE customer_id = ? ORDER BY created_at DESC", [$customerId]) as $s) {
        $out[] = [
            'kind' => 'settlement',
            'ref_id' => (int)$s['id'],
            'ref_number' => (string)$s['settlement_number'],
            'item_label' => '💰 محاسبة فواتير',
            'item_date' => (string)$s['created_at'],
            'total_amount' => (float)$s['total_amount'],
            'paid_amount' => (float)$s['paid_amount'],
            'remaining_amount' => max(0, round((float)$s['total_amount'] - (float)$s['paid_amount'], 2)),
            'event_notes' => 'تسوية مجمعة على ' . (int)$s['items_count'] . ' عنصر',
        ];
    }
    // General collections from balance (تسويات also write a payments row with reference STL-… — skip those to avoid double counting)
    foreach (getRows("SELECT * FROM payments WHERE type = 'customer' AND entity_id = ? AND (reference IS NULL OR reference = '' OR reference NOT LIKE 'STL-%') ORDER BY created_at DESC", [$customerId]) as $p) {
        $out[] = [
            'kind' => 'general_payment',
            'ref_id' => (int)$p['id'],
            'ref_number' => (string)$p['payment_number'],
            'item_label' => '💵 تحصيل عام من الرصيد',
            'item_date' => (string)$p['created_at'],
            'total_amount' => (float)$p['amount'],
            'paid_amount' => (float)$p['amount'],
            'remaining_amount' => 0.0,
            'event_notes' => 'طريقة: ' . ($p['payment_method'] ?: 'كاش'),
        ];
    }
    return $out;
}

/** Filter the account items (bills + payment events) by free text + date window. */
function customer_filtered_items($customerId, $q, $dateFrom, $dateTo) {
    $all = array_merge(customer_all_items($customerId), profile_payment_events($customerId));
    usort($all, function ($a, $b) {
        return strcmp((string)$b['item_date'], (string)$a['item_date']);
    });
    $qLower = mb_search_normalize(trim($q));
    $out = [];
    foreach ($all as $it) {
        if ($qLower !== '') {
            $hay = mb_search_normalize(($it['ref_number'] ?? '') . ' ' . ($it['item_label'] ?? '') . ' ' . ($it['event_notes'] ?? ''));
            if (mb_stripos($hay, $qLower) === false) continue;
        }
        $day = substr((string)$it['item_date'], 0, 10);
        if ($dateFrom !== null && $day < (string)$dateFrom) continue;
        if ($dateTo !== null && $day > (string)$dateTo) continue;
        $out[] = $it;
    }
    return $out;
}

$stats = customer_account_stats($id);
$balance = (float)$customer['balance'];

// ---- Filters: dates default to today; "all dates" via all_dates=1 ----
$isAllDates = isset($_GET['all_dates']);
$dateFrom = trim($_GET['date_from'] ?? date('Y-m-d'));
$dateTo   = trim($_GET['date_to']   ?? date('Y-m-d'));
$searchQ  = trim($_GET['q'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 20;

$items     = customer_filtered_items($id, $searchQ, $isAllDates ? null : $dateFrom, $isAllDates ? null : $dateTo);
$totalItems = count($items);
$totalPages = max(1, (int)ceil($totalItems / $perPage));
$page = min($page, $totalPages);
$itemsPage = array_slice($items, ($page - 1) * $perPage, $perPage);
// Open dues inside the current filter window (the checkbox pool)
$windowOpenTotal = 0.0;
foreach ($items as $it) { if ($it['remaining_amount'] > 0.009) $windowOpenTotal += $it['remaining_amount']; }
$windowOpenTotal = round($windowOpenTotal, 2);

function profile_item_key(array $it) {
    if ($it['kind'] === 'order') return 'order:' . $it['ref_id'];
    if ($it['kind'] === 'gaming_session') return 'gs:' . $it['ref_id'];
    if ($it['kind'] === 'settlement') return 'stl:' . $it['ref_id'];
    if ($it['kind'] === 'general_payment') return 'pay:' . $it['ref_id'];
    return 'bs:' . $it['ref_id'];
}
function profile_kind_read_receipt(array $it) {
    if ($it['kind'] === 'order') return '../pos/receipt.php?id=' . $it['ref_id'] . '&type=customer';
    if ($it['kind'] === 'gaming_session') return '../gaming/receipt.php?id=' . $it['ref_id'];
    if ($it['kind'] === 'billiard_session') return '../billiards/receipt.php?id=' . $it['ref_id'];
    if ($it['kind'] === 'settlement') return 'settlement_receipt.php?id=' . $it['ref_id'];
    if ($it['kind'] === 'general_payment') return '../payments/print_customer.php?id=' . $it['ref_id'];
    return '../billiards/receipt.php?id=' . $it['ref_id'];
}

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<style>
.pr-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:16px 20px; }
.pr-row-open { border-color:#fdba74 !important; background:#fffbeb !important; }
.pr-row-event { border-color:#a7f3d0 !important; background:#f0fdf4 !important; }
.pr-chip { padding:4px 10px; border-radius:20px; font-size:11px; font-weight:800; display:inline-block; }
.pr-chip-done { background:#ecfdf5; color:#059669; border:1px solid #a7f3d0; }
.pr-chip-part { background:#fef9c3; color:#a16207; border:1px solid #fde047; }
.pr-chip-open { background:#ffedd5; color:#c2410c; border:1px solid #fdba74; }
.btn-mini { padding:5px 10px; border-radius:8px; font-size:11.5px; font-weight:800; border:1px solid #cbd5e1; background:#fff; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:4px; font-family:inherit; color:#334155; }
.btn-mini:hover { background:#f1f5f9; }
.btn-mini.primary { background:#ea580c; color:#fff; border-color:#ea580c; }
.btn-mini.danger  { background:#dc2626; color:#fff; border-color:#dc2626; }
.btn-mini.success { background:#059669; color:#fff; border-color:#059669; }
.btn-mini.blue    { background:#2563eb; color:#fff; border-color:#2563eb; }
.btn-mini:disabled { opacity:.45; cursor:not-allowed; }
</style>

<div class="container">

<!-- Header -->
<div class="pr-card" style="margin-bottom:16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
    <div>
        <h2 style="font-size:19px; font-weight:900; color:#0f172a; margin:0 0 3px 0;">👤 <?php echo htmlspecialchars($customer['name']); ?></h2>
        <div style="font-size:12.5px; color:#64748b;">
            📞 <?php echo htmlspecialchars($customer['phone'] ?: 'لا يوجد تليفون'); ?>
            • 🗓️ مسجل من <?php echo date('Y/m/d', strtotime($customer['created_at'])); ?>
        </div>
    </div>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <a href="../pos/index.php?customer_id=<?php echo $id; ?>" class="btn-mini primary" style="padding:8px 14px;">🧾 فاتورة بيع جديدة</a>
        <?php if ($stats['open_total'] > 0.009 || $balance < 0): ?>
        <button type="button" onclick="cstCollectOpen()" class="btn-mini" style="background:#fef3c7; border-color:#fcd34d; color:#92400e;">💵 تحصيل عام من الرصيد</button>
        <?php endif; ?>
        <?php if (hasPermission('reports.statement')): ?>
        <a href="../reports/statement.php?type=customer&id=<?php echo $id; ?>" class="btn-mini">🖨️ كشف حساب</a>
        <?php endif; ?>
        <?php if (hasPermission('customers.edit')): ?>
        <a href="edit.php?id=<?php echo $id; ?>" class="btn-mini">✏️ تعديل البيانات</a>
        <?php endif; ?>
        <a href="index.php" class="btn-mini">↩️ رجوع</a>
    </div>
</div>

<!-- Money cards -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:10px; margin-bottom:16px;">
    <div class="pr-card" style="border-top:4px solid #0284c7;">
        <div style="font-size:11.5px; color:#475569; font-weight:700;">إجمالي المفوتر</div>
        <div style="font-size:17px; font-weight:900; color:#0f172a;"><?php echo number_format($stats['total_billed'], 2); ?> ج.م</div>
        <div style="font-size:10.5px; color:#94a3b8;"><?php echo $stats['items_count']; ?> فاتورة وطلب</div>
    </div>
    <div class="pr-card" style="border-top:4px solid #10b981;">
        <div style="font-size:11.5px; color:#475569; font-weight:700;">إجمالي المحصل</div>
        <div style="font-size:17px; font-weight:900; color:#059669;"><?php echo number_format($stats['total_paid'], 2); ?> ج.م</div>
    </div>
    <div class="pr-card" style="border-top:4px solid <?php echo $stats['open_total'] > 0.009 ? '#dc2626' : '#94a3b8'; ?>;">
        <div style="font-size:11.5px; color:#475569; font-weight:700;">المتبقي عليه (غير محاسب)</div>
        <div style="font-size:17px; font-weight:900; color:#dc2626;"><?php echo number_format($stats['open_total'], 2); ?> ج.م</div>
        <div style="font-size:10.5px; color:#94a3b8;"><?php echo $stats['open_count']; ?> عنصر مفتوح</div>
    </div>
    <div class="pr-card" style="border-top:4px solid #a855f7;">
        <div style="font-size:11.5px; color:#475569; font-weight:700;">الرصيد في الدفتر</div>
        <div style="font-size:17px; font-weight:900;">
            <?php if ($balance < -0.009): ?>
                <span style="color:#dc2626;"><?php echo number_format(abs($balance), 2); ?> ج.م (مدين)</span>
            <?php elseif ($balance > 0.009): ?>
                <span style="color:#059669;"><?php echo number_format($balance, 2); ?> ج.م (دائن)</span>
            <?php else: ?>
                <span>0.00</span>
            <?php endif; ?>
        </div>
        <?php $ledgerModel = -1 * (float)$stats['open_total'];
              $ledgerDiff = round($balance - $ledgerModel, 2); ?>
        <div style="font-size:10.5px; color:#94a3b8; line-height:1.6;">
            <?php if (abs($ledgerDiff) > 0.009): ?>
                ⚠️ في رصيد قديم/يدوي في الدفتر (<strong style="color:#b45309;"><?php echo number_format(abs($ledgerDiff), 2); ?> ج.م <?php echo $ledgerDiff < 0 ? 'مدين' : 'دائن'; ?></strong>) مش مربوط بفواتير مفتوحة — الفواتير فوق محاسبتهم بالكامل.
                <?php if (hasPermission('customers.edit')): ?>
                <div><button type="button" onclick="cstSyncBalance()" class="btn-mini" style="margin-top:4px; padding:3px 8px; font-size:10.5px;">⚖️ مطابقة الدفتر مع الفواتير (<span id="cstLedgerModel"><?php echo number_format($ledgerModel, 2); ?></span>)</button></div>
                <?php endif; ?>
            <?php else: ?>
                السجل المصاحب للتحصيلات العامة (يتبع المستحقات المفتوحة).
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Orders & invoices -->
<div class="pr-card">

<div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; background:#0f172a; border-radius:12px; padding:12px 16px; margin-bottom:14px;">
    <div style="flex:1; min-width:230px;">
        <div style="color:#fbbf24; font-weight:800; font-size:14px;">💰 محاسبة الفواتير</div>
        <div style="color:#cbd5e1; font-size:11.5px; margin-top:2px;">
            حددا فواتير معينة (كل التواريخ) واضغط "محاسبة المحددد" — أو "محاسبة الكل" لكل ما تبقى مفتوح على العميل.
        </div>
    </div>
    <div style="color:#93c5fd; font-size:12px; font-weight:800;">المفتوح الكلي: <?php echo number_format($stats['open_total'], 2); ?> ج.م</div>
    <button type="button" onclick="cstSettleSelected()" class="btn-mini success" id="cstBtnSelected" disabled>✅ محاسبة المحدد (<span id="cstSelCount">0</span>)</button>
    <button type="button" onclick="cstSettleAll()" class="btn-mini success" <?php echo $stats['open_total'] <= 0.009 ? 'disabled' : ''; ?>>🗂️ محاسبة الكل</button>
</div>

<!-- Filters -->
<form method="GET" style="display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px 14px; margin-bottom:14px;">
    <input type="hidden" name="id" value="<?php echo $id; ?>">
    <input type="hidden" name="all_dates" value="<?php echo $isAllDates ? '1' : '0'; ?>">
    <div style="display:flex; flex-direction:column; gap:3px; flex:2; min-width:180px;">
        <label style="font-size:11px; font-weight:700; color:#64748b;">🔍 بحث في العناصر</label>
        <input type="text" name="q" placeholder="رقم طلب/بون أو كلمة..." value="<?php echo htmlspecialchars($searchQ); ?>" style="padding:8px 10px; border-radius:8px; border:1px solid #cbd5e1; font-family:inherit; font-size:12.5px; font-weight:600;">
    </div>
    <div style="display:flex; flex-direction:column; gap:3px;">
        <label style="font-size:11px; font-weight:700; color:#64748b;">من تاريخ</label>
        <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>" <?php echo $isAllDates ? 'disabled' : ''; ?> style="padding:8px 10px; border-radius:8px; border:1px solid #cbd5e1; font-family:inherit; font-size:12.5px;">
    </div>
    <div style="display:flex; flex-direction:column; gap:3px;">
        <label style="font-size:11px; font-weight:700; color:#64748b;">إلى تاريخ</label>
        <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>" <?php echo $isAllDates ? 'disabled' : ''; ?> style="padding:8px 10px; border-radius:8px; border:1px solid #cbd5e1; font-family:inherit; font-size:12.5px;">
    </div>
    <button type="submit" class="btn-mini blue" style="padding:9px 16px;">🎯 تصفية</button>
    <a href="view.php?id=<?php echo $id; ?>" class="btn-mini" style="padding:9px 12px;">↺ اليوم</a>
    <?php if (!$isAllDates): ?>
    <a href="view.php?id=<?php echo $id; ?>&all_dates=1<?php echo $searchQ !== '' ? '&q=' . urlencode($searchQ) : ''; ?>" class="btn-mini" style="padding:9px 12px;">📅 كل التواريخ</a>
    <?php else: ?>
    <a href="view.php?id=<?php echo $id; ?>" class="btn-mini" style="padding:9px 12px;">📅 اليوم فقط</a>
    <?php endif; ?>
</form>

<div style="font-size:11.5px; color:#64748b; margin-bottom:10px; display:flex; justify-content:space-between;">
    <span>
        <?php if ($isAllDates): ?>📄 كل التواريخ<?php else: ?>📅 من <?php echo htmlspecialchars($dateFrom); ?> إلى <?php echo htmlspecialchars($dateTo); ?><?php endif; ?>
        — المستحق المفتوح داخل الفيلتر: <strong style="color:#c2410c;"><?php echo number_format($windowOpenTotal, 2); ?> ج.م</strong>
    </span>
    <span>إجمالي العناصر: <strong><?php echo $totalItems; ?></strong></span>
</div>

<?php if (empty($itemsPage)): ?>
<div style="text-align:center; padding:30px; color:#94a3b8; font-weight:700;">لا توجد فواتير أو طلبات مطابقة</div>
<?php endif; ?>

<div style="display:flex; flex-direction:column; gap:8px;">
<?php foreach ($itemsPage as $it):
    $rem    = (float)$it['remaining_amount'];
    $isEvent = ($it['kind'] === 'settlement' || $it['kind'] === 'general_payment');
    $isOpen = !$isEvent && $rem > 0.009;
    $isPart = $isOpen && (float)$it['paid_amount'] > 0.009;
    $key    = profile_item_key($it);
    $lineClass = $isOpen ? 'pr-row-open' : ($isEvent ? 'pr-row-event' : '');
?>
    <div class="pr-card <?php echo $lineClass; ?>" data-item-key="<?php echo $key; ?>" style="padding:10px 14px; display:flex; gap:12px; align-items:center; border-radius:10px; flex-wrap:wrap;">
        <?php if ($isOpen): ?>
        <input type="checkbox" class="cst-check" data-open-total="<?php echo number_format($rem, 2, '.', ''); ?>" onchange="cstSelToggle(this)"
               style="width:17px; height:17px; accent-color:#ea580c;">
        <?php else: ?>
        <span style="width:17px; text-align:center; color:#059669; font-weight:900;">✔</span>
        <?php endif; ?>

        <div style="flex:1; min-width:180px;">
            <div style="font-weight:800; font-size:13px; color:#0f172a;"><?php echo htmlspecialchars($it['item_label']); ?> — <?php echo htmlspecialchars($it['ref_number']); ?></div>
            <div style="font-size:11px; color:#64748b;"><?php echo date('d/m/Y • h:i A', strtotime($it['item_date'])); ?></div>
            <?php if (!empty($it['event_notes'])): ?>
            <div style="font-size:10px; color:#059669; font-weight:800;"><?php echo htmlspecialchars($it['event_notes']); ?></div>
            <?php endif; ?>
        </div>

        <div style="display:flex; gap:14px; text-align:center; font-size:12px;">
            <div><div style="color:#64748b; font-size:10.5px;">الإجمالي</div><strong style="color:#334155;"><?php echo number_format((float)$it['total_amount'], 2); ?></strong></div>
            <div><div style="color:#64748b; font-size:10.5px;">المدفوع</div><strong style="color:#059669;"><?php echo number_format(min((float)$it['paid_amount'], (float)$it['total_amount']), 2); ?></strong></div>
            <div><div style="color:#64748b; font-size:10.5px;">الباقي</div><strong style="color:<?php echo $isOpen ? '#dc2626' : '#94a3b8'; ?>;"><?php echo number_format($rem, 2); ?></strong></div>
        </div>

        <div style="min-width:110px; text-align:center;">
            <?php if ($isEvent): ?>
                <?php if ($rem > 0.009): ?>
                <span class="pr-chip pr-chip-part">⚠️ جزئي</span>
                <?php elseif ($it['kind'] === 'settlement'): ?>
                <span class="pr-chip pr-chip-done">🧾 محاسبة مجمعة</span>
                <?php else: ?>
                <span class="pr-chip pr-chip-done">💵 من الرصيد</span>
                <?php endif; ?>
            <?php elseif (!$isOpen): ?>
                <span class="pr-chip pr-chip-done">✔ محاسبت بالكامل</span>
            <?php elseif ($isPart): ?>
                <span class="pr-chip pr-chip-part">⚠️ جزئي</span>
            <?php else: ?>
                <span class="pr-chip pr-chip-open">❌ غير محسبة</span>
            <?php endif; ?>
        </div>

        <div style="display:flex; gap:5px; flex-wrap:wrap; justify-content:flex-end;">
            <button type="button" class="btn-mini" title="بون" onclick='openReceiptModal(<?php echo json_encode(profile_kind_read_receipt($it)); ?>)'>🖨️ بون</button>
            <?php if ($it['kind'] === 'order' && !in_array($it['status'], ['cancelled'], true) && ($it['payment_status'] ?? '') !== 'refunded'): ?>
            <a href="../pos/index.php?edit_order=<?php echo $it['ref_id']; ?>&back=<?php echo urlencode('modules/customers/view.php?id=' . $id); ?>"
               class="btn-mini" title="تعديل أو ارجاع — صرف أو حذف أصناف من الطلب">↩️ تعديل / ارجاع</a>
            <?php endif; ?>
            <?php if ($isOpen): ?>
            <button type="button" class="btn-mini danger" onclick="cstSettleOne('<?php echo $key; ?>', <?php echo number_format($rem, 2, '.', ''); ?>)">💰 محاسبه</button>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
</div>

<?php if ($totalPages > 1): ?>
<?php
$qs = $_GET; unset($qs['page']);
$baseQ = http_build_query($qs);
?>
<div style="display:flex; justify-content:center; gap:8px; margin-top:16px;">
<?php if ($page > 1): ?><a href="view.php?<?php echo http_build_query(array_merge($qs, ['page' => $page - 1])); ?>" class="btn-mini">← السابق</a><?php endif; ?>
<span class="badge badge-info" style="padding:8px 14px;">صفحة <?php echo $page; ?> من <?php echo $totalPages; ?></span>
<?php if ($page < $totalPages): ?><a href="view.php?<?php echo http_build_query(array_merge($qs, ['page' => $page + 1])); ?>" class="btn-mini">التالي →</a><?php endif; ?>
</div>
<?php endif; ?>

</div><!-- /items card -->
</div><!-- /container -->

<!-- Settlement modal -->
<div id="cstSettleOverlay" style="position:fixed; inset:0; background:rgba(15,23,42,.65); backdrop-filter:blur(3px); display:none; align-items:center; justify-content:center; z-index:100002; padding:16px;">
    <div style="background:#fff; border-radius:16px; width:100%; max-width:440px; padding:20px;" id="cstSettleBox"></div>
</div>

<!-- General collection from balance modal -->
<div id="cstCollectOverlay" style="position:fixed; inset:0; background:rgba(15,23,42,.65); backdrop-filter:blur(3px); display:none; align-items:center; justify-content:center; z-index:100002; padding:16px;">
    <div style="background:#fff; border-radius:16px; width:100%; max-width:440px; padding:20px;" id="cstCollectBox"></div>
</div>

<script>
const cstCustomerId = <?php echo json_encode($id); ?>;
const cstSel = new Map(); // key -> open amount
let cstMode = { keys: null, openTotal: 0 }; // keys null = ALL open items

function moneyFmt(n) { return (parseFloat(n) || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}); }

function cstSelToggle(cb) {
    let total = 0;
    document.querySelectorAll('.cst-check:checked').forEach(x => total += parseFloat(x.dataset.openTotal));
    document.getElementById('cstSelCount').textContent = document.querySelectorAll('.cst-check:checked').length;
    document.getElementById('cstBtnSelected').disabled = total <= 0 && document.querySelectorAll('.cst-check:checked').length === 0;
}

/* One-item path */
function cstSettleOne(key, rem) {
    cstMode = { keys: [key], openTotal: rem, label: 'محاسبة عنصر واحد بقيمة <strong>' + moneyFmt(rem) + '</strong> ج.م' };
    cstRenderModal();
}

/* Selected path */
function cstSettleSelected() {
    const checked = Array.from(document.querySelectorAll('.cst-check:checked'));
    if (checked.length === 0) { appAlert('حدد عنصر (عناصر) للمحاسبة أولاً', 'error'); return; }
    const keys = checked.map(cb => cb.dataset.key);
    let total = 0; checked.forEach(cb => total += parseFloat(cb.dataset.openTotal));
    total = Math.round(total * 100) / 100;
    cstMode = { keys: keys, openTotal: total, label: 'محاسبة <strong>' + checked.length + '</strong> عنصر — إجماليها المفتوح <strong>' + moneyFmt(total) + '</strong> ج.م' };
    cstRenderModal();
}

/* All open path */
function cstSettleAll() {
    const openTotal = <?php echo json_encode($stats['open_total']); ?>;
    if (openTotal <= 0.009) { appAlert('لا يوجد مفتوح للتحصيل', 'error'); return; }
    cstMode = { keys: null, openTotal: openTotal, label: 'تسوية <strong>كل</strong> الفواتير المفتوحة على العميل — إجماليها <strong>' + moneyFmt(openTotal) + '</strong> ج.م (كل التواريخ)' };
    cstRenderModal();
}

function cstRenderModal() {
    const box = document.getElementById('cstSettleBox');
    document.querySelectorAll('.cst-check').forEach(cb => { cb.dataset.key = cb.closest('[data-key]').dataset.key || ''; });
    box.innerHTML = `
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
            <h3 style="font-size:15px; font-weight:900; color:#0f172a; margin:0;">💰 تسوية وحصاد حساب العميل</h3>
            <button onclick="cstCloseSettle()" style="background:none;border:none;font-size:20px;cursor:pointer;">&times;</button>
        </div>
        <div style="background:#fff7ed; border-radius:10px; padding:10px 14px; font-size:12.5px; color:#7c2d12; margin-bottom:12px; line-height:1.9;">
            ${cstMode.label}
        </div>
        <div style="margin-bottom:12px;">
            <label style="font-size:12px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">المبلغ المحصل الآن:<span id="cstHintRemain" style="color:#dc2626; margin-right:8px;"></span></label>
            <input type="text" inputmode="decimal" id="cstAmountInput" value="${cstMode.openTotal.toFixed(2)}" autocomplete="off"
                   oninput="cstUpdateRemain()"
                   style="width:100%; padding:11px 14px; border-radius:10px; border:1.5px solid #cbd5e1; font-size:16px; font-weight:800; font-family:inherit; text-align:center;">
        </div>
        <div style="margin-bottom:14px;">
            <label style="font-size:12px; font-weight:700; color:#475569; display:block; margin-bottom:5px;">طريقة الدفع:</label>
            <select id="cstMethodInput" style="width:100%; padding:9px 12px; border-radius:10px; border:1.5px solid #cbd5e1; font-size:13px; font-family:inherit;">
                <option value="cash">💵 نقداً (كاش)</option>
                <option value="card">💳 فيزا / بطاقة</option>
                <option value="instapay">⚡ إنستاباي (InstaPay)</option>
                <option value="vodafone_cash">📱 فودافون كاش</option>
            </select>
        </div>
        <button type="button" onclick="cstSubmitSettle()" id="cstSubmitBtn" style="width:100%; background:linear-gradient(135deg,#059669,#047857); color:#fff; border:none; border-radius:10px; padding:12px; font-size:15px; font-weight:900; cursor:pointer;">
            ✅ تأكيد التحصيل
        </button>
    `;
    document.getElementById('cstSettleOverlay').style.display = 'flex';
    cstUpdateRemain();
}

function cstUpdateRemain() {
    // ملاحظة فقط: منع إعادة كتابة القيمة أثناء الكتابة حتى لا يقفز المؤشر
    const max = cstMode.openTotal;
    const input = document.getElementById('cstAmountInput');
    normDigitInput(input); // keyboard Arabic digits support
    const val = parseFloat(input.value) || 0;
    document.getElementById('cstHintRemain').textContent = val < (max - 0.009)
        ? `(باقي ${moneyFmt(Math.max(0, max - val))} يفضل مفتوح على حسابه)` : '';
}

function cstCloseSettle() { document.getElementById('cstSettleOverlay').style.display = 'none'; }

let cstBusy = false;
async function cstSubmitSettle() {
    if (cstBusy) return;
    cstBusy = true;
    const btn = document.getElementById('cstSubmitBtn');
    btn.disabled = true;
    try {
        const amount = parseFloat(document.getElementById('cstAmountInput').value) || 0;
        const method = document.getElementById('cstMethodInput').value;
        const res = await fetch('ajax.php?action=settle', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                customer_id: cstCustomerId,
                items: cstMode.keys,
                amount: amount,
                method: method
            })
        });
        const data = await res.json();
        if (!data.success) { appAlert(data.error || 'فشل تسجيل التسوية', 'error'); return; }
        const stl = data.summary;
        // نافذة طباعة فيها كل عناصر المحاسبة (مثل فاتورة بون) — الريلود بعد الغلق مش بعدها مباشرة
        openReceiptModal('settlement_receipt.php?id=' + encodeURIComponent(stl.settlement_id), function() { location.reload(); });
        document.getElementById('cstSettleOverlay').style.display = 'none';
        appAlert(`تم تحصيل ${moneyFmt(stl.collected)} ج.م وتسجيلها على العميل (${stl.settlement_number})`, 'success');
    } catch (err) {
        console.error(err);
        appAlert('خطأ في الاتصال بالسيرفر', 'error');
    } finally {
        cstBusy = false; btn.disabled = false;
    }
}

/* Wire dataset keys to checkboxes (for selection across re-renders) */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.cst-check').forEach(cb => {
        const row = cb.closest('[data-item-key]');
        if (row) cb.dataset.key = row.dataset.itemKey;
    });
});

/* ===== تحصيل عام من رصيد الدفتر (نافذة بدل صفحة جديدة) ===== */
let cstCollectBusy = false;

async function cstCollectOpen() {
    const box = document.getElementById('cstCollectBox');
    box.innerHTML = '<div style="text-align:center; padding:24px; color:#64748b; font-weight:700;">جاري تحميل الرصيد…</div>';
    document.getElementById('cstCollectOverlay').style.display = 'flex';
    try {
        const res = await fetch('ajax.php?action=get_balance&customer_id=' + cstCustomerId);
        const data = await res.json();
        if (!data.success) { appAlert('تعذر جلب الرصيد', 'error'); document.getElementById('cstCollectOverlay').style.display = 'none'; return; }
        const owed = Math.max(0, -(parseFloat(data.balance) || 0));
        box.innerHTML = `
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                <h3 style="font-size:15px; font-weight:900; color:#0f172a; margin:0;">💵 تحصيل عام من رصيد العميل</h3>
                <button onclick="cstCollectClose()" style="background:none;border:none;font-size:20px;cursor:pointer;">&times;</button>
            </div>
            <div style="background:#fef3c7; border-radius:10px; padding:10px 14px; font-size:12.5px; color:#92400e; margin-bottom:12px; line-height:1.9;">
                ده تحصيل من <strong>رصيد الدفتر</strong> (${moneyFmt(owed)} ج.م مدين) — التحصيلات العامة القديمة.
                لو قصدك محاسبة فواتير معينة استخدم "محاسبة الكل" فوق.
            </div>
            <div style="margin-bottom:12px;">
                <label style="font-size:12px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">المبلغ المحصل:<span id="cstCollectHint" style="color:#dc2626; margin-right:8px;"></span></label>
                <input type="text" inputmode="decimal" id="cstCollectAmount" value="${owed.toFixed(2)}" data-max="${owed.toFixed(2)}" autocomplete="off"
                       oninput="cstCollectNote()"
                       style="width:100%; padding:11px 14px; border-radius:10px; border:1.5px solid #cbd5e1; font-size:16px; font-weight:800; font-family:inherit; text-align:center;">
            </div>
            <div style="margin-bottom:14px;">
                <label style="font-size:12px; font-weight:700; color:#475569; display:block; margin-bottom:5px;">طريقة الدفع:</label>
                <select id="cstCollectMethod" style="width:100%; padding:9px 12px; border-radius:10px; border:1.5px solid #cbd5e1; font-size:13px; font-family:inherit;">
                    <option value="cash">💵 كاش</option>
                    <option value="card">💳 فيزا / بطاقة</option>
                    <option value="instapay">⚡ إنستاباي (InstaPay)</option>
                    <option value="vodafone_cash">📱 فودافون كاش</option>
                </select>
            </div>
            <button type="button" onclick="cstCollectSubmit()" id="cstCollectSubmitBtn" style="width:100%; background:linear-gradient(135deg,#d97706,#b45309); color:#fff; border:none; border-radius:10px; padding:12px; font-size:15px; font-weight:900; cursor:pointer;">
                ✅ تأكيد التحصيل
            </button>
        `;
        cstCollectNote();
    } catch (err) {
        console.error(err);
        document.getElementById('cstCollectOverlay').style.display = 'none';
        appAlert('خطأ في الاتصال بالسيرفر', 'error');
    }
}

function cstCollectNote() {
    // ملاحظة فقط — منع إعادة كتابة القيمة حتى لا يقفز المؤشر
    const input = document.getElementById('cstCollectAmount');
    normDigitInput(input); // keyboard Arabic digits support
    const max = parseFloat(input.dataset.max) || 0;
    const val = parseFloat(input.value) || 0;
    const hint = document.getElementById('cstCollectHint');
    if (hint) hint.textContent = (val > max + 0.009) ? `⚠️ أكبر من المديونية (${moneyFmt(max)})` : '';
}

function cstCollectClose() { document.getElementById('cstCollectOverlay').style.display = 'none'; }

async function cstCollectSubmit() {
    if (cstCollectBusy) return;
    cstCollectBusy = true;
    const btn = document.getElementById('cstCollectSubmitBtn');
    btn.disabled = true;
    try {
        const input = document.getElementById('cstCollectAmount');
        const amount = Math.round((parseFloat(input.value) || 0) * 100) / 100;
        const max = Math.round((parseFloat(input.dataset.max) || 0) * 100) / 100;
        if (amount <= 0) { appAlert('اكتب مبلغ أكبر من صفر', 'error'); return; }
        if (amount > max + 0.009) { appAlert('المبلغ أكبر من مديونية العميل', 'error'); return; }
        const method = document.getElementById('cstCollectMethod').value;
        const res = await fetch('ajax.php?action=collect_general', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ customer_id: cstCustomerId, amount: amount, method: method })
        });
        const data = await res.json();
        if (!data.success) { appAlert(data.error || 'فشل تسجيل التحصيل', 'error'); return; }
        cstCollectClose();
        appAlert(`تم تحصيل ${moneyFmt(amount)} ج.م من رصيد العميل (${data.payment_number})`, 'success');
        // إيصال التحصيل في نافذة طباعة — الريلود بعد ما المستخدم يقفلها بنفسه
        openReceiptModal('../payments/print_customer.php?id=' + encodeURIComponent(data.payment_id), function() { location.reload(); });
    } catch (err) {
        console.error(err);
        appAlert('خطأ في الاتصال بالسيرفر', 'error');
    } finally {
        cstCollectBusy = false; if (btn) btn.disabled = false;
    }
}

/* ===== مطابقة الدفتر مع الفواتير ===== */
async function cstSyncBalance() {
    const data = await fetch('ajax.php?action=sync_balance', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ customer_id: cstCustomerId })
    }).then(r => r.json()).catch(() => null);
    if (!data || !data.success) { appAlert((data && data.error) || 'فشل التطابق', 'error'); return; }
    appAlert(`تمت المطابقة: الرصيد في الدفتر بقى ${moneyFmt(Math.abs(data.new_balance))} ج.م ${data.new_balance < 0 ? 'مدين' : 'دائن'} (مطابق للمستحقات المفتوحة)`, 'success');
    setTimeout(() => location.reload(), 900);
}
</script>

<?php include '../../includes/footer.php'; ?>
