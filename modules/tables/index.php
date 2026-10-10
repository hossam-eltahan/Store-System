<?php
/**
 * Dine-in Tables Dashboard - طاولات صالة كافيه عروسة النيل
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';

requireLogin();

if (!hasPermission('tables.view')) {
    showAccessDenied();
}

$pageTitle = 'طاولات الصالة';

include '../../includes/header.php';
?>

<div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; background:#fff; padding:16px 20px; border-radius:12px; border:1px solid #e2e8f0;">
    <div>
        <h2 style="font-size: 18px; font-weight: 800; color: #0f172a; margin: 0 0 4px 0;">🪑 طاولات الصالة وجلسات الجلوس</h2>
        <div style="font-size: 12.5px; color: #64748b;">متابعة الطاولات المشغولة، فتح طاولات جديدة، وإضافة الطلبات</div>
    </div>
    <div style="display: flex; gap: 8px;">
        <button onclick="loadTables()" style="font-weight:700; font-size:12px; padding:8px 14px; border-radius:8px; border:1px solid #cbd5e1; background:#fff; cursor:pointer;">
            🔄 تحديث
        </button>
        <a href="../pos/index.php" style="background:#ea580c; color:white; font-weight:700; font-size:12px; padding:8px 14px; border-radius:8px; text-decoration:none;">
            <span>⚡</span> فتح كاشير POS
        </a>
    </div>
</div>

<!-- Tables Grid -->
<div id="tablesGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px;">
    <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #64748b;">
        جاري تحميل الطاولات...
    </div>
</div>

<!-- Modal: Open Table -->
<div class="game-modal-backdrop" id="openTableModal" style="position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.6); display:none; align-items:center; justify-content:center; z-index:9999;">
    <div style="background:#fff; border-radius:16px; width:100%; max-width:360px; overflow:hidden;">
        <div style="padding:14px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
            <h3 id="openTableModalTitle" style="font-size:15px; font-weight:800; margin:0;">فتح طاولة</h3>
            <button onclick="document.getElementById('openTableModal').style.display='none'" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <div style="padding:18px;">
            <input type="hidden" id="openTableId">
            <label style="font-size:12px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">اسم العميل (اختياري):</label>
            <input type="text" id="openCustomerName" placeholder="اسم العميل..." style="width:100%; padding:9px; border-radius:8px; border:1px solid #cbd5e1; margin-bottom:12px;">
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

<script>
let tables = [];

document.addEventListener('DOMContentLoaded', () => {
    loadTables();
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
    grid.innerHTML = tables.map(tbl => {
        const sess = tbl.current_session;
        const isOccupied = !!sess;

        let borderCol = isOccupied ? '#ea580c' : '#10b981';
        let statusBadge = isOccupied ? 
            `<span style="background:#fff7ed; color:#ea580c; border:1px solid #fed7aa; padding:3px 8px; border-radius:12px; font-size:11px; font-weight:bold;">🔴 مشغولة (${sess.elapsed_minutes} د)</span>` : 
            `<span style="background:#ecfdf5; color:#059669; border:1px solid #a7f3d0; padding:3px 8px; border-radius:12px; font-size:11px; font-weight:bold;">🟢 متاحة وشاغرة</span>`;

        let ordersCount = sess && sess.orders ? sess.orders.length : 0;
        let ordersTotal = 0;
        if (sess && sess.orders) {
            sess.orders.forEach(o => ordersTotal += parseFloat(o.total_amount));
        }

        return `
            <div style="background:#fff; border:2px solid ${borderCol}; border-radius:16px; padding:16px; display:flex; flex-direction:column; justify-content:space-between; box-shadow:0 4px 10px rgba(0,0,0,0.03);">
                <div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <span style="font-size:16px; font-weight:800; color:#0f172a;">🪑 ${escapeHtml(tbl.display_name)}</span>
                        ${statusBadge}
                    </div>
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
                            <span>إجمالي الطلبات:</span>
                            <span style="color:#ea580c;">${ordersTotal.toFixed(2)} ج.م (${ordersCount} طلب)</span>
                        </div>` : ''}
                    </div>
                </div>
                <div>
                    ${isOccupied ? `
                    <div style="display:flex; gap:6px;">
                        <a href="../pos/index.php" style="flex:1; background:#ea580c; color:white; padding:9px; border-radius:8px; text-align:center; font-weight:bold; font-size:12px; text-decoration:none;">
                            ☕ إضافة طلب
                        </a>
                        <button onclick="closeTable(${sess.id}, ${ordersTotal})" style="flex:1; background:#059669; color:white; border:none; padding:9px; border-radius:8px; font-weight:bold; font-size:12px; cursor:pointer;">
                            🏁 إنهاء ومحاسبة
                        </button>
                    </div>` : `
                    <button onclick="openTableModal(${tbl.id}, '${escapeHtml(tbl.display_name)}')" style="width:100%; background:#10b981; color:white; border:none; padding:10px; border-radius:8px; font-weight:bold; font-size:13px; cursor:pointer;">
                        🟢 فتح الطاولة
                    </button>`}
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
    document.getElementById('openTableModal').style.display = 'flex';
}

async function submitOpenTable() {
    const tableId = document.getElementById('openTableId').value;
    const cust = document.getElementById('openCustomerName').value.trim();
    const guests = document.getElementById('openGuestCount').value;

    const fd = new FormData();
    fd.append('table_id', tableId);
    fd.append('customer_name', cust);
    fd.append('guest_count', guests);

    const res = await fetch('ajax.php?action=open_table', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
        document.getElementById('openTableModal').style.display = 'none';
        loadTables();
    } else {
        alert(data.error);
    }
}

async function closeTable(sessionId, total) {
    if (!confirm(`هل تريد إنهاء ومحاسبة الطاولة؟ الإجمالي: ${total.toFixed(2)} ج.م`)) return;

    const fd = new FormData();
    fd.append('session_id', sessionId);
    fd.append('payment_method', 'cash');

    const res = await fetch('ajax.php?action=close_table', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
        loadTables();
        alert('تم إنهاء الطاولة وتحصيل الحساب بنجاح!');
    } else {
        alert(data.error);
    }
}

function escapeHtml(t) { return t ? t.toString().replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;") : ''; }
</script>

<?php include '../../includes/footer.php'; ?>
