<?php
/**
 * Dine-in Tables Dashboard - طاولات صالة كافيه عروسة النيل
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';

requireLogin();
requirePermission('tables.view');

$pageTitle = 'طاولات الصالة';

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container">

<div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; background:#fff; padding:16px 20px; border-radius:12px; border:1px solid #e2e8f0; box-shadow: 0 2px 6px rgba(0,0,0,0.02);">
    <div>
        <h2 style="font-size: 18px; font-weight: 800; color: #0f172a; margin: 0 0 4px 0;">🪑 طاولات الصالة وجلسات الجلوس</h2>
        <div style="font-size: 12.5px; color: #64748b;">متابعة الطاولات المشغولة، فتح طاولات جديدة، وإضافة الطلبات</div>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="../../index.php" style="background:#fff; color:#334155; font-weight:700; font-size:12px; padding:8px 14px; border-radius:8px; border:1px solid #cbd5e1; text-decoration:none; display:inline-flex; align-items:center; gap:4px;">
            <span>🔙</span> الرئيسية
        </a>
        <a href="add.php" style="background:#6F4E37; color:white; font-weight:700; font-size:12px; padding:8px 14px; border-radius:8px; text-decoration:none; display:inline-flex; align-items:center; gap:4px;">
            <span>➕</span> إضافة طاولة
        </a>
        <button onclick="loadTables()" style="font-weight:700; font-size:12px; padding:8px 14px; border-radius:8px; border:1px solid #cbd5e1; background:#fff; cursor:pointer;">
            🔄 تحديث
        </button>
        <a href="../pos/index.php" style="background:#ea580c; color:white; font-weight:700; font-size:12px; padding:8px 14px; border-radius:8px; text-decoration:none; display:inline-flex; align-items:center; gap:4px;">
            <span>⚡</span> فتح كاشير POS
        </a>
    </div>
</div>

<!-- Search bar -->
<div style="margin-bottom: 16px;">
    <input type="text" id="tablesSearch" placeholder="🔍 بحث باسم الطاولة أو باسم العميل اللي قاعد..." oninput="renderTables()" autocomplete="off"
           style="width:100%; max-width:420px; display:inline-block; padding:10px 14px; border-radius:10px; border:1.5px solid #cbd5e1; font-family:inherit; font-size:13px; font-weight:600; background:#fff;">
</div>

<!-- Tables Grid -->
<div id="tablesGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px;">
    <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #64748b;">
        جاري تحميل الطاولات...
    </div>
</div>

<!-- Modal: Open Table -->
<div class="game-modal-backdrop" id="openTableModal" style="position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); display:none; align-items:center; justify-content:center; z-index:9999;">
    <div style="background:#fff; border-radius:16px; width:100%; max-width:360px; overflow:hidden;">
        <div style="padding:14px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
            <h3 id="openTableModalTitle" style="font-size:15px; font-weight:800; margin:0;">فتح طاولة</h3>
            <button onclick="document.getElementById('openTableModal').style.display='none'" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <div style="padding:18px;">
            <input type="hidden" id="openTableId">
            <label style="font-size:12px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">اسم العميل (اختياري):</label>
            <input type="text" id="openCustomerName" placeholder="اسم العميل..." style="width:100%; padding:9px; border-radius:8px; border:1px solid #cbd5e1; margin-bottom:10px;">
            <input type="hidden" id="openCustomerId" value="">
            <div id="openCustomerBalance" style="font-size:12px; display:none; margin-bottom:10px; margin-top:-6px;"></div>
            <label style="font-size:12px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">عدد الضيوف (الأفراد):</label>
            <input type="number" id="openGuestCount" value="2" min="1" max="20" style="width:100%; padding:9px; border-radius:8px; border:1px solid #cbd5e1; font-weight:bold;">
        </div>
        <div style="padding:12px 18px; background:#f8fafc; border-top:1px solid #e2e8f0; text-align:left;">
            <button onclick="submitOpenTable()" style="background:#10b981; color:white; border:none; border-radius:8px; padding:10px 22px; font-weight:bold; cursor:pointer;">
                🟢 فتح الطاولة الآن
            </button>
        </div>
    </div>
</div>

<!-- Modal: Cross-hall transfer (table -> gaming/billiards) -->
<div class="game-modal-backdrop" id="crossTransferModal" style="position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); display:none; align-items:center; justify-content:center; z-index:9999;">
    <div style="background:#fff; border-radius:16px; width:100%; max-width:360px; overflow:hidden;">
        <div style="padding:14px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
            <h3 id="crossTransferTitle" style="font-size:15px; font-weight:800; margin:0;">🔀 نقل لصالة أخرى</h3>
            <button onclick="document.getElementById('crossTransferModal').style.display='none'" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <div style="padding:18px;">
            <input type="hidden" id="crossSessionId">
            <label style="font-size:12px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">اختر الوجهة المتاحة:</label>
            <select id="crossTargetSelect" onchange="renderCrossExtra()" style="width:100%; padding:10px; border-radius:8px; border:1.5px solid #cbd5e1; font-size:13px; font-family:inherit; margin-bottom:12px;"></select>
            <div id="crossExtra"></div>
            <div style="background:#f5f3ff; border:1px solid #ddd6fe; border-radius:8px; padding:8px 12px; font-size:12px; color:#6d28d9; margin-top:12px;">
                🔀 طلبات الجلسة المفتوحة تنتقل معها، وأي رصيد مستحق يُرحل على الفاتورة الجديدة.
            </div>
        </div>
        <div style="padding:12px 18px; background:#f8fafc; border-top:1px solid #e2e8f0; text-align:left;">
            <button onclick="submitCrossTransfer()" style="background:#7c3aed; color:white; border:none; border-radius:8px; padding:10px 20px; font-size:13px; font-weight:800; cursor:pointer;">
                🔀 تأكيد النقل
            </button>
        </div>
    </div>
</div>

<style>
.game-modal-backdrop {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 16px;
}

.game-modal {
    background: #ffffff;
    border-radius: 16px;
    width: 100%;
    max-width: 440px;
    overflow: hidden;
    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2);
}
</style>

<script src="../../assets/js/customer_picker.js?v=1"></script>
<script>
let tables = [];

document.addEventListener('DOMContentLoaded', () => {
    loadTables();
    // حقل العميل في مودال فتح الطاولة + مودال الإغلاق = اختيار من الموجودين / عميل جديد / بدون
    initCustomerPicker(
        document.getElementById('openCustomerName'),
        document.getElementById('openCustomerId'),
        { balanceEl: document.getElementById('openCustomerBalance') }
    );
    initCustomerPicker(
        document.getElementById('payCustomerIdName'),
        document.getElementById('payCustomerId')
    );
});

async function loadTables() {
    try {
        const res = await fetch('ajax.php?action=get_tables');
        const data = await res.json();
        if (data.success) {
            tables = data.tables;
            renderTables();
        }
    } catch (err) {
        console.error(err);
    }
}

function renderTables() {
    const grid = document.getElementById('tablesGrid');
    const searchEl = document.getElementById('tablesSearch');
    const q = searchEl ? searchEl.value.trim().toLowerCase() : '';
    const shown = tables.filter(tbl => {
        if (!q) return true;
        const cust = (tbl.current_session && tbl.current_session.customer_name) ? String(tbl.current_session.customer_name).toLowerCase() : '';
        return String(tbl.display_name).toLowerCase().includes(q) || cust.includes(q);
    });
    if (shown.length === 0) {
        grid.innerHTML = `<div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #94a3b8; font-weight: 700;">لا توجد نتائج مطابقة للبحث</div>`;
        return;
    }
    grid.innerHTML = shown.map(tbl => {
        const sess = tbl.current_session;
        const isOccupied = !!sess;

        let borderCol = isOccupied ? '#ea580c' : '#10b981';
        let statusBadge = isOccupied ? 
            `<span style="background:#fff7ed; color:#ea580c; border:1px solid #fed7aa; padding:3px 8px; border-radius:12px; font-size:11px; font-weight:bold;">🔴 مشغولة (${sess.elapsed_minutes} د)</span>` : 
            `<span style="background:#ecfdf5; color:#059669; border:1px solid #a7f3d0; padding:3px 8px; border-radius:12px; font-size:11px; font-weight:bold;">🟢 متاحة وشاغرة</span>`;

        let ordersCount = sess && sess.orders ? sess.orders.length : 0;
        let ordersTotal = sess ? parseFloat(sess.gross_total || 0) : 0;
        let amountDue = sess ? parseFloat(sess.due_total || 0) : 0;

        return `
            <div style="background:#fff; border:2px solid ${borderCol}; border-radius:16px; padding:16px; display:flex; flex-direction:column; justify-content:space-between; box-shadow:0 4px 10px rgba(0,0,0,0.03);">
                <div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <span style="font-size:16px; font-weight:800; color:#0f172a;">🪑 ${escapeHtml(tbl.display_name)}</span>
                        ${statusBadge}
                    </div>
                    ${sess && sess.customer_name ? `<div style="display:flex; align-items:center; gap:8px; background:#fffbeb; border:1px solid #fde68a; border-radius:10px; padding:8px 12px; margin-bottom:10px;"><span style="font-size:18px;">👤</span><span style="font-size:15px; font-weight:800; color:#92400e;">${escapeHtml(sess.customer_name)}</span></div>` : ''}
                    <div style="background:#f8fafc; border-radius:10px; padding:12px; margin-bottom:14px; font-size:12.5px;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                            <span>القسم:</span>
                            <strong>${tbl.section === 'outdoor' ? 'خارجي' : 'الصالة الرئيسية'}</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                            <span>السعة:</span>
                            <strong>${tbl.capacity} أفراد</strong>
                        </div>
                        ${isOccupied ? `
                        <div style="display:flex; justify-content:space-between; margin-top:6px; border-top:1px dashed #cbd5e1; padding-top:6px; font-weight:bold;">
                            <span>المتبقي للتحصيل:</span>
                            <span style="color:#ea580c;">${amountDue.toFixed(2)} ج.م (${ordersCount} طلب)</span>
                        </div>` : ''}
                    </div>
                </div>
                <div>
                    ${isOccupied ? `
                    <div style="display:flex; gap:6px;">
                        <a href="../pos/index.php?table_session_id=${sess.id}" style="flex:1; background:#ea580c; color:white; border:none; padding:9px 6px; border-radius:8px; text-align:center; font-weight:bold; font-size:12px; text-decoration:none;">
                            ☕ إضافة طلب
                        </a>
                        <button onclick="viewOpenOrders(${sess.id})" style="flex:1; background:#f1f5f9; color:#334155; border:1px solid #cbd5e1; padding:9px 6px; border-radius:8px; font-weight:bold; font-size:12px; cursor:pointer;">
                            🧾 الطلبات (${ordersCount})
                        </button>
                    </div>
                    <div style="display:flex; gap:6px; margin-top:6px;">
                        <button onclick="transferTable(${sess.id}, ${tbl.id})" style="flex:1; background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; padding:9px 6px; border-radius:8px; font-weight:bold; font-size:12px; cursor:pointer;">
                            🔄 نقل
                        </button>
                        <button onclick="openCrossTransferModal(${sess.id})" title="نقل الجلسة إلى صالة أخرى" style="flex:1; background:#f5f3ff; color:#7c3aed; border:1px solid #ddd6fe; padding:9px 6px; border-radius:8px; font-weight:bold; font-size:12px; cursor:pointer;">
                            🔀 صالة أخرى
                        </button>
                    </div>
                    <div style="display:flex; gap:6px; margin-top:6px;">
                        <button onclick="closeTable(${sess.id}, ${amountDue}, ${ordersTotal}, ${parseFloat(sess.paid_total || 0)}, '${encodeURIComponent(tbl.display_name)}', ${ordersCount})" style="flex:1; background:#ef4444; color:white; border:none; padding:9px 6px; border-radius:8px; font-weight:800; font-size:12px; cursor:pointer;">
                            🏁 محاسبة
                        </button>
                    </div>` : `
                    <button onclick="openTableModal(${tbl.id}, '${escapeHtml(tbl.display_name)}')" style="width:100%; background:#10b981; color:white; border:none; padding:10px; border-radius:8px; font-weight:bold; font-size:13px; cursor:pointer;">
                        🟢 فتح الطاولة
                    </button>`}
                    <?php if (hasPermission('tables.manage')): ?>
                    <div style="display:flex; gap:6px; margin-top:12px; padding-top:12px; border-top:1px dashed #e2e8f0;">
                        <a href="edit.php?id=${tbl.id}" style="flex:1; background:#f1f5f9; color:#334155; border:1px solid #cbd5e1; padding:6px 10px; border-radius:6px; text-align:center; font-size:11px; font-weight:bold; text-decoration:none;">
                            ✏️ تعديل
                        </a>
                        <button onclick="deleteTable(${tbl.id}, '${escapeHtml(tbl.display_name)}')" style="flex:1; background:#fee2e2; color:#dc2626; border:1px solid #fca5a5; padding:6px 10px; border-radius:6px; font-size:11px; font-weight:bold; cursor:pointer;">
                            🗑️ حذف
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        `;
    }).join('');
}

function openTableModal(tableId, name) {
    document.getElementById('openTableId').value = tableId;
    document.getElementById('openTableModalTitle').textContent = `فتح: ${name}`;
    document.getElementById('openCustomerName').value = '';
    document.getElementById('openGuestCount').value = 2;
    const openCustId = document.getElementById('openCustomerId');
    if (openCustId) openCustId.value = '';
    document.getElementById('openTableModal').style.display = 'flex';
}

function viewOpenOrders(sessionId) {
    openReceiptModal('receipt.php?id=' + sessionId);
}

async function transferTable(sessionId, currentTableId) {
    const available = tables.filter(table => table.id !== currentTableId && table.status === 'available');
    if (available.length === 0) {
        appAlert('لا توجد طاولات شاغرة للنقل إليها', 'info');
        return;
    }

    const choice = await appPromptSelect('اختر الطاولة الجديدة لنقل الجلسة إليها:', available.map(table => ({ value: String(table.id), label: table.display_name })), { title: '🔀 نقل الجلسة' });
    if (!choice) return;

    const fd = new FormData();
    fd.append('session_id', sessionId);
    fd.append('target_table_id', choice);
    const res = await fetch('ajax.php?action=transfer_table', { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.success) {
        appAlert(data.error || 'تعذر نقل الجلسة');
        return;
    }
    loadTables();
}

async function openCrossTransferModal(sessionId) {
    const res = await fetch('ajax.php?action=cross_targets');
    const data = await res.json();
    if (!data.success) {
        appAlert(data.error);
        return;
    }
    let html = '';
    if ((data.devices || []).length) {
        html += `<optgroup label="🎮 بلايستيشن">` + data.devices.map(d => `<option value="gaming:${d.id}">${escapeHtml(d.display_name)}</option>`).join('') + `</optgroup>`;
    }
    if ((data.btables || []).length) {
        html += `<optgroup label="🎱 بلياردو">` + data.btables.map(d => `<option value="billiards:${d.id}">${escapeHtml(d.display_name)}</option>`).join('') + `</optgroup>`;
    }
    if (!html) {
        appAlert('لا توجد وجهات شاغرة في الصالات الأخرى حالياً!', 'info');
        return;
    }
    document.getElementById('crossSessionId').value = sessionId;
    document.getElementById('crossTargetSelect').innerHTML = html;
    renderCrossExtra();
    document.getElementById('crossTransferModal').style.display = 'flex';
}

function renderCrossExtra() {
    const val = document.getElementById('crossTargetSelect').value || '';
    const hall = val.split(':')[0];
    let extra = '';
    if (hall === 'gaming') {
        extra = `<label style="font-size:12px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">نوع الجلسة:</label>
           <select id="crossSessionType" style="width:100%; padding:10px; border-radius:8px; border:1.5px solid #cbd5e1; font-size:13px; font-family:inherit;"><option value="single">سنجل</option><option value="multi">مالتي</option></select>`;
    } else if (hall === 'billiards') {
        extra = `<label style="font-size:12px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">نظام الحساب:</label>
           <select id="crossBillingMode" style="width:100%; padding:10px; border-radius:8px; border:1.5px solid #cbd5e1; font-size:13px; font-family:inherit;"><option value="hourly">بالساعة</option><option value="per_game">بالجيم</option></select>`;
    }
    document.getElementById('crossExtra').innerHTML = extra;
}

async function submitCrossTransfer() {
    const sessionId = document.getElementById('crossSessionId').value;
    const val = document.getElementById('crossTargetSelect').value || '';
    const parts = val.split(':');
    if (parts.length !== 2) return;
    const hall = parts[0];
    const targetId = parts[1];
    if (!await appConfirm('سيتم نقل الجلسة وطلباتها المفتوحة، وأي رصيد مستحق سيُرحل على الفاتورة الجديدة. متابعة؟')) return;
    const fd = new FormData();
    fd.append('session_id', sessionId);
    let action = 'transfer_to_billiards';
    if (hall === 'gaming') {
        action = 'transfer_to_gaming';
        fd.append('target_device_id', targetId);
        const st = document.getElementById('crossSessionType');
        fd.append('session_type', st ? st.value : 'single');
    } else {
        fd.append('target_table_id', targetId);
        const bm = document.getElementById('crossBillingMode');
        fd.append('billing_mode', bm ? bm.value : 'hourly');
    }
    const res = await fetch('ajax.php?action=' + action, { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.success) {
        appAlert(data.error);
        return;
    }
    document.getElementById('crossTransferModal').style.display = 'none';
    const carried = parseFloat(data.carried_amount || 0);
    appAlert(carried > 0 ? `تم النقل بنجاح برصيد مرحل ${carried.toFixed(2)} ج.م` : 'تم النقل بنجاح', 'success');
    loadTables();
}

async function submitOpenTable() {
    const tableId = document.getElementById('openTableId').value;
    const cust = document.getElementById('openCustomerName').value.trim();
    const guests = document.getElementById('openGuestCount').value;

    const fd = new FormData();
    fd.append('table_id', tableId);
    fd.append('customer_name', cust);
    fd.append('customer_id', document.getElementById('openCustomerId').value || 0);
    fd.append('guest_count', guests);

    const res = await fetch('ajax.php?action=open_table', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
        document.getElementById('openTableModal').style.display = 'none';
        loadTables();
    } else {
        appAlert(data.error);
    }
}

async function closeTable(sessionId, total, grossTotal, paidTotal, tableName, ordersCount) {
    // فتح نافذة اختيار طريقة الدفع
    tableName = decodeURIComponent(tableName);
    document.getElementById('paySessionId').value = sessionId;
    document.getElementById('paySessionId').dataset.total = total.toFixed(2);
    document.getElementById('payDiscount').value = '0';
    document.getElementById('tableSettlementSummary').innerHTML = `
        <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
            <span>الطاولة:</span><strong>${escapeHtml(tableName)}</strong>
        </div>
        <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
            <span>عدد الطلبات:</span><strong>${ordersCount} طلب</strong>
        </div>
        <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
            <span>الإجمالي الكلي:</span><strong style="color:#ea580c;">${grossTotal.toFixed(2)} ج.م</strong>
        </div>
        <div style="display:flex; justify-content:space-between; margin-bottom:6px; color:#059669;">
            <span>المدفوع مسبقاً:</span><strong>${paidTotal.toFixed(2)} ج.م</strong>
        </div>
        <div style="display:flex; justify-content:space-between; color:#d97706;">
            <span>المتبقي للتحصيل:</span><strong id="tableDueDisplay">${total.toFixed(2)} ج.م</strong>
        </div>
        <div style="display:flex; justify-content:space-between; border-top:1px dashed #cbd5e1; padding-top:6px; margin-top:6px; font-size:16px; font-weight:900;">
            <span>الإجمالي الكلي:</span><strong style="color:#ea580c;">${grossTotal.toFixed(2)} ج.م</strong>
        </div>
        <div style="display:flex; justify-content:space-between; margin-top:6px; color:#059669; font-weight:800;">
            <span>المطلوب بعد الخصم:</span><strong id="tableNetDisplay">${total.toFixed(2)} ج.م</strong>
        </div>
    `;
    document.getElementById('payMethod').value = 'cash';
    // خانة عميل الإغلاق + المحصل: افتراضياً تحصيل كامل (والعميل يُلاحق من نافذة الإغلاق)
    const payPaidInput = document.getElementById('payPaidInput');
    if (payPaidInput) {
        payPaidInput.dataset.touchedOnce = '';
        payPaidInput.max = total.toFixed(2);
        payPaidInput.value = Math.max(0, total).toFixed(2);
        updateTablePaid();
    }
    const payCustName = document.getElementById('payCustomerIdName');
    if (payCustName) payCustName.value = '';
    const payCustId = document.getElementById('payCustomerId');
    if (payCustId) payCustId.value = '';
    document.getElementById('payTableModal').style.setProperty('display', 'flex', 'important');
}

function updateTableDiscount() {
    const total = parseFloat(document.getElementById('paySessionId').dataset.total || '0');
    const discount = Math.min(total, Math.max(0, parseFloat(document.getElementById('payDiscount').value) || 0));
    document.getElementById('payDiscount').value = discount;
    document.getElementById('tableNetDisplay').innerText = (total - discount).toFixed(2) + ' ج.م';
    const paid = document.getElementById('payPaidInput');
    if (paid && !paid.dataset.touchedOnce) {
        paid.max = (total - discount).toFixed(2);
        paid.value = Math.max(0, total - discount).toFixed(2);
        updateTablePaid();
    }
}

// المحصل أقل من المتبقي → يترحل على حساب العميل (أو خطأ لو مفيش عميل)
function updateTablePaid() {
    const inp = document.getElementById('payPaidInput');
    const note = document.getElementById('tablePaidNote');
    if (!inp || !note) return;
    const final = parseFloat(inp.max) || 0;
    let paidV = parseFloat(inp.value) || 0;
    if (paidV < 0) paidV = 0;
    inp.dataset.touchedOnce = '1';
    inp.value = paidV.toFixed(2);
    const remaining = Math.round((final - paidV) * 100) / 100;
    if (remaining <= 0.009) { note.style.display = 'none'; return; }
    const custName = (document.getElementById('payCustomerIdName')?.value || '').trim();
    const hasCustomer = document.getElementById('payCustomerId').value || custName;
    note.style.display = 'block';
    note.style.color = hasCustomer ? '#92400e' : '#dc2626';
    note.style.background = hasCustomer ? '#fefce8' : '#fef2f2';
    note.style.borderColor = hasCustomer ? '#fde047' : '#fca5a5';
    note.innerHTML = hasCustomer
        ? `💳 باقي <strong>${remaining.toFixed(2)}</strong> ج.م هيتسجل على حساب العميل (${custName || 'مربوط'})`
        : `❌ باقي <strong>${remaining.toFixed(2)}</strong> ج.م — لازم تسجل العميل: اكتب اسمه في خانة العميل (هيتسجل في العملاء تلقائياً)`;
}

async function submitCloseTable() {
    const sessionId = document.getElementById('paySessionId').value;
    const paymentMethod = document.getElementById('payMethod').value;
    const discountAmount = Math.max(0, parseFloat(document.getElementById('payDiscount').value) || 0);
    const paidInput = document.getElementById('payPaidInput');
    const total = parseFloat(document.getElementById('paySessionId').dataset.total || '0');
    let paidAmount = null;
    if (paidInput) {
        paidAmount = Math.max(0, parseFloat(paidInput.value) || 0);
        const afterDisc = total - discountAmount;
        if (afterDisc > 0 && paidAmount < afterDisc - 0.009) {
            const cid = document.getElementById('payCustomerId').value;
            const cname = (document.getElementById('payCustomerIdName')?.value || '').trim();
            if (!cid && cname === '') {
                appAlert('❌ لازم تسجل العميل — اكتب اسمه في خانة "العميل" لتسجيل الباقي على حسابه', 'error');
                return;
            }
        }
    }

    const fd = new FormData();
    fd.append('session_id', sessionId);
    fd.append('payment_method', paymentMethod);
    fd.append('discount_amount', discountAmount);
    if (paidAmount !== null) fd.append('paid_amount', paidAmount);
    fd.append('customer_id', document.getElementById('payCustomerId').value || 0);
    fd.append('customer_name', document.getElementById('payCustomerIdName').value || '');

    try {
        const res = await fetch('ajax.php?action=close_table', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            // إغلاق المودال
            document.getElementById('payTableModal').style.setProperty('display', 'none', 'important');
            // تحديث الطاولات
            loadTables();
            // فتح صفحة طباعة الفاتورة المجمعة
            openReceiptModal('receipt.php?id=' + data.session_id);
        } else {
            appAlert(data.error);
        }
    } catch (err) {
        console.error(err);
        appAlert('حدث خطأ أثناء الإغلاق');
    }
}

function closePayModal() {
    document.getElementById('payTableModal').style.setProperty('display', 'none', 'important');
}

function closeDeleteModal() {
    document.getElementById('deleteTableModal').style.display = 'none';
}

// إغلاق المودال بالضغط خارجه
window.addEventListener('click', function(e) {
    const payModal = document.getElementById('payTableModal');
    if (e.target === payModal) {
        closePayModal();
    }
    const openModal = document.getElementById('openTableModal');
    if (e.target === openModal) {
        openModal.style.display = 'none';
    }
    const deleteModal = document.getElementById('deleteTableModal');
    if (e.target === deleteModal) {
        closeDeleteModal();
    }
});

function escapeHtml(t) { return t ? t.toString().replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;") : ''; }
</script>
</div><!-- End container -->

<!-- Modal: اختيار طريقة الدفع عند إغلاق الطاولة -->
<div class="game-modal-backdrop" id="payTableModal" style="position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); display:none; align-items:center; justify-content:center; z-index:9999; padding:12px;">
    <div class="game-modal" style="width:100%; max-width:520px; max-height:92vh; overflow-y:auto; background:#fff; border-radius:16px; box-shadow:0 20px 50px rgba(0,0,0,0.25);">
            <div style="padding:14px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
                <h3 style="font-size:15px; font-weight:800; margin:0; color:#0f172a;">🏁 إنهاء الجلسة والمحاسبة</h3>
                <button type="button" style="background:none; border:none; font-size:20px; cursor:pointer; color:#64748b;" onclick="closePayModal()">&times;</button>
            </div>
            <div style="padding:18px;">
                <input type="hidden" id="paySessionId" value="" data-total="0">

                <div id="tableSettlementSummary" style="background:#f8fafc; border-radius:10px; padding:12px; margin-bottom:14px; font-size:12.5px;">
                    جاري جلب تفاصيل الحساب...
                </div>
                <div style="margin-bottom: 16px;">
                    <label for="payDiscount" style="font-size: 13px; font-weight: 700; color: #334155; display: block; margin-bottom: 8px;">🏷️ قيمة الخصم من المتبقي:</label>
                    <input type="number" id="payDiscount" min="0" step="0.5" value="0" oninput="updateTableDiscount()" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:16px; font-weight:700; text-align:center;">
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="font-size: 13px; font-weight: 700; color: #334155; display: block; margin-bottom: 8px;">💳 طريقة الدفع:</label>
                    <select id="payMethod" style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1; font-size:13px; font-family:inherit;">
                        <option value="cash">💵 نقداً (كاش)</option>
                        <option value="card">💳 فيزا / بطاقة</option>
                        <option value="instapay">⚡ إنستاباي</option>
                        <option value="vodafone_cash">📱 فودافون كاش</option>
                    </select>
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="font-size: 13px; font-weight: 700; color: #334155; display: block; margin-bottom: 8px;">👤 العميل (اختياري — لبقية التحصيل على الحساب):</label>
                    <div style="position:relative;">
                        <input type="text" id="payCustomerIdName" placeholder="ابحث أو اكتب اسم عميل جديد..." autocomplete="off"
                               style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px; font-family:inherit;">
                        <input type="hidden" id="payCustomerId" value="">
                    </div>
                </div>

                <div style="margin-bottom: 16px;">
                    <label for="payPaidInput" style="font-size: 13px; font-weight: 700; color: #334155; display: block; margin-bottom: 8px;">💵 المحصل الآن (اتركه بالإجمالي للمحاسبة الكاملة):</label>
                    <input type="number" id="payPaidInput" min="0" step="0.5" value="0" oninput="updateTablePaid()"
                           style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:16px; font-weight:700; text-align:center;">
                    <div id="tablePaidNote" style="font-size:11.5px; font-weight:700; border-radius:8px; padding:7px 10px; margin-top:6px; display:none;"></div>
                </div>

                <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 10px 14px; font-size: 12px; color: #92400e; line-height: 1.6;">
                    ⚠️ سيتم تحصيل المبلغ وتسجيله في الشيفت الحالي وإغلاق الطاولة مع طباعة الفاتورة المجمعة.
                </div>
            </div>
            <div style="padding:12px 18px; background:#f8fafc; border-top:1px solid #e2e8f0; display:flex; justify-content:center; gap:12px;">
                <button type="button" onclick="closePayModal()" style="background: #e2e8f0; color: #334155; border: none; border-radius: 8px; padding: 10px 24px; font-weight: 700; cursor: pointer;">إلغاء</button>
                <button type="button" onclick="submitCloseTable()" style="background: #059669; color: white; border: none; border-radius: 8px; padding: 10px 24px; font-weight: 700; cursor: pointer; min-width: 160px;">
                    🖨️ تحصيل وطباعة الفاتورة
                </button>
            </div>
    </div>
</div>

<!-- Modal: Delete Table Confirmation -->
<div class="game-modal-backdrop" id="deleteTableModal" style="position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); display:none; align-items:center; justify-content:center; z-index:9999;">
    <div class="game-modal" style="max-width: 380px;">
        <div style="padding: 20px; text-align: center;">
            <div style="width: 70px; height: 70px; background: #fee2e2; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px auto;">
                <span style="font-size: 36px;">🗑️</span>
            </div>
            <h3 style="font-size: 20px; font-weight: 800; margin: 0 0 12px 0; color: #0f172a;">حذف طاولة الصالة</h3>
            <p style="font-size: 15px; color: #64748b; margin: 0 0 24px 0; line-height: 1.6;">
                هل تريد حذف <strong id="deleteTableName" style="color: #dc2626;"></strong>؟
            </p>
            <input type="hidden" id="deleteTableId">
            <div style="display: flex; gap: 12px; justify-content: center;">
                <button onclick="closeDeleteModal()" style="background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; padding: 12px 28px; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 14px;">
                    إلغاء
                </button>
                <button onclick="confirmDeleteTable()" style="background: #dc2626; color: white; border: none; padding: 12px 28px; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 14px;">
                    حذف
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}
function escapeHtml(t) { return t ? t.toString().replace(/&amp;/g,"&amp;").replace(/&lt;/g,"&lt;").replace(/&gt;/g,"&gt;") : ''; }

function editTable(tableId) {
    window.location.href = 'edit.php?id=' + tableId;
}

function deleteTable(tableId, tableName) {
    document.getElementById('deleteTableId').value = tableId;
    document.getElementById('deleteTableName').textContent = tableName;
    document.getElementById('deleteTableModal').style.display = 'flex';
}

function confirmDeleteTable() {
    const tableId = document.getElementById('deleteTableId').value;
    window.location.href = 'delete.php?id=' + tableId;
}
</script>

<?php include '../../includes/footer.php'; ?>
