<?php
/**
 * Billiards Management Dashboard - صالة البلياردو والسنوكر
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';

requireLogin();

if (!hasPermission('billiards.view')) {
    showAccessDenied();
}

$pageTitle = 'صالة البلياردو والسنوكر';

include '../../includes/header.php';
?>

<style>
.billiards-header-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    background: #ffffff;
    padding: 16px 20px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 6px rgba(0,0,0,0.03);
}

.tables-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 16px;
}

.billiard-card {
    background: #ffffff;
    border: 2px solid #e2e8f0;
    border-radius: 16px;
    padding: 18px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-shadow: 0 4px 10px rgba(0,0,0,0.03);
    transition: all 0.25s;
}

.billiard-card.available { border-color: #10b981; }
.billiard-card.active { border-color: #059669; box-shadow: 0 6px 18px rgba(16, 185, 129, 0.15); }
.billiard-card.paused { border-color: #f59e0b; background: #fffbeb; }

.table-felt {
    background: linear-gradient(135deg, #064e3b, #047857);
    color: white;
    border-radius: 12px;
    padding: 16px 12px;
    text-align: center;
    margin-bottom: 14px;
    box-shadow: inset 0 2px 6px rgba(0,0,0,0.4);
}

.felt-digits {
    font-family: 'Courier New', monospace;
    font-size: 32px;
    font-weight: 900;
    letter-spacing: 2px;
    color: #a7f3d0;
}

.felt-games {
    font-size: 26px;
    font-weight: 900;
    color: #fef08a;
}
</style>

<div class="billiards-header-bar">
    <div>
        <h2 style="font-size: 18px; font-weight: 800; color: #0f172a; margin: 0 0 4px 0;">🎱 صالة طاولات البلياردو والسنوكر</h2>
        <div style="font-size: 12.5px; color: #64748b;">حساب الوقت بالساعة أو بعدد الأجوام (Games) مع ربط المشروبات</div>
    </div>
    <div style="display: flex; gap: 8px;">
        <button onclick="loadTables()" class="btn btn-secondary" style="font-weight:700; font-size:12px; padding:8px 14px; border-radius:8px; border:1px solid #cbd5e1; background:#fff; cursor:pointer;">
            🔄 تحديث الحالة
        </button>
        <a href="../pos/index.php" class="btn" style="background:#ea580c; color:white; font-weight:700; font-size:12px; padding:8px 14px; border-radius:8px; text-decoration:none;">
            <span>⚡</span> فتح كاشير POS
        </a>
    </div>
</div>

<div class="tables-grid" id="tablesGrid">
    <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #64748b;">
        جاري تحميل صالة البلياردو...
    </div>
</div>

<!-- Modal: Start Billiard Session -->
<div class="game-modal-backdrop" id="startBilliardModal" style="position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.6); display:none; align-items:center; justify-content:center; z-index:9999;">
    <div style="background:#fff; border-radius:16px; width:100%; max-width:400px; overflow:hidden;">
        <div style="padding:14px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
            <h3 id="startTableTitle" style="font-size:15px; font-weight:800; margin:0;">تشغيل طاولة بلياردو</h3>
            <button onclick="closeModal('startBilliardModal')" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <div style="padding:18px;">
            <input type="hidden" id="startTableId">
            <label style="font-size:12.5px; font-weight:700; color:#475569; display:block; margin-bottom:8px;">نظام الحساب:</label>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:14px;">
                <label style="border:2px solid #e2e8f0; border-radius:10px; padding:12px; text-align:center; cursor:pointer;" id="labelHour">
                    <input type="radio" name="billiardMode" value="hourly" checked style="display:none;" onchange="updateBilliardModeRadio()">
                    <div style="font-size:15px; font-weight:800; color:#0f172a;">⏱️ بالساعة</div>
                    <div id="startHourRate" style="font-size:11.5px; color:#059669; font-weight:700; margin-top:3px;">40.00 ج.م / س</div>
                </label>
                <label style="border:2px solid #e2e8f0; border-radius:10px; padding:12px; text-align:center; cursor:pointer;" id="labelGame">
                    <input type="radio" name="billiardMode" value="per_game" style="display:none;" onchange="updateBilliardModeRadio()">
                    <div style="font-size:15px; font-weight:800; color:#0f172a;">🎱 بالجيم (Game)</div>
                    <div id="startGameRate" style="font-size:11.5px; color:#059669; font-weight:700; margin-top:3px;">10.00 ج.م / جيم</div>
                </label>
            </div>
            <label style="font-size:12.5px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">اسم العميل (اختياري):</label>
            <input type="text" id="startBilliardCustomer" placeholder="اسم العميل..." style="width:100%; padding:9px; border-radius:8px; border:1px solid #cbd5e1; font-size:13px; font-family:inherit;">
        </div>
        <div style="padding:12px 18px; background:#f8fafc; border-top:1px solid #e2e8f0; text-align:left;">
            <button onclick="submitStartBilliard()" style="background:#059669; color:white; border:none; border-radius:8px; padding:10px 22px; font-size:14px; font-weight:800; cursor:pointer;">
                ▶️ تشغيل الطاولة
            </button>
        </div>
    </div>
</div>

<!-- Modal: End Billiard Session -->
<div class="game-modal-backdrop" id="endBilliardModal" style="position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.6); display:none; align-items:center; justify-content:center; z-index:9999;">
    <div style="background:#fff; border-radius:16px; width:100%; max-width:420px; overflow:hidden;">
        <div style="padding:14px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="font-size:15px; font-weight:800; margin:0;">محاسبة طاولة البلياردو</h3>
            <button onclick="closeModal('endBilliardModal')" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <div style="padding:18px;" id="endBilliardModalBody">
            جاري جلب تفاصيل الحساب...
        </div>
    </div>
</div>

<script>
let tables = [];
let activeBilliardSessionId = null;

document.addEventListener('DOMContentLoaded', () => {
    loadTables();
    setInterval(tickBilliardsLocally, 1000);
    setInterval(loadTables, 15000);
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
        const isBusy = !!sess;
        const isPaused = isBusy && sess.status === 'paused';
        const isHourly = isBusy && sess.billing_mode === 'hourly';

        let cardClass = isBusy ? (isPaused ? 'paused' : 'active') : 'available';
        let statusBadge = isBusy ? 
            (isPaused ? '<span style="background:#fef3c7; color:#d97706; padding:3px 8px; border-radius:12px; font-size:11px; font-weight:bold;">⏸️ موقف مؤقت</span>' : 
                        `<span style="background:#d1fae5; color:#065f46; padding:3px 8px; border-radius:12px; font-size:11px; font-weight:bold;">🎱 ${isHourly ? 'بالساعة' : 'بالجيم (' + sess.game_count + ')'}</span>`) : 
            '<span style="background:#ecfdf5; color:#059669; padding:3px 8px; border-radius:12px; font-size:11px; font-weight:bold;">🟢 متاحة وشاغرة</span>';

        let feltHtml = '';
        let actionsHtml = '';

        if (isBusy) {
            let netSec = Math.max(0, parseInt(sess.total_seconds_from_start) - parseInt(sess.total_paused_seconds));

            if (isHourly) {
                feltHtml = `
                    <div class="table-felt">
                        <div class="felt-digits" id="billiardDigits_${tbl.id}">${formatSeconds(netSec)}</div>
                        <div style="font-size:11.5px; opacity:0.85; margin-top:4px;">حساب بالساعة • ${parseFloat(sess.applied_rate).toFixed(2)} ج.م/س</div>
                    </div>
                `;
            } else {
                feltHtml = `
                    <div class="table-felt">
                        <div class="felt-games">${sess.game_count} جيم ملعوب</div>
                        <div style="font-size:11.5px; opacity:0.85; margin-top:4px;">${parseFloat(sess.applied_rate).toFixed(2)} ج.م / للجيم</div>
                    </div>
                `;
            }

            actionsHtml = `
                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                    ${!isHourly ? `<button onclick="addGame(${sess.id})" style="flex:1; background:#047857; color:white; border:none; padding:8px; border-radius:8px; font-weight:bold; cursor:pointer;">➕ إضافة جيم</button>` : ''}
                    ${isHourly ? (isPaused ? 
                        `<button onclick="resumeBilliard(${sess.id})" style="flex:1; background:#3b82f6; color:white; border:none; padding:8px; border-radius:8px; font-weight:bold; cursor:pointer;">▶️ استئناف</button>` : 
                        `<button onclick="pauseBilliard(${sess.id})" style="flex:1; background:#f59e0b; color:white; border:none; padding:8px; border-radius:8px; font-weight:bold; cursor:pointer;">⏸️ إيقاف</button>`) : ''
                    }
                    <a href="../pos/index.php" style="flex:1; background:#f1f5f9; color:#0f172a; border:1px solid #cbd5e1; padding:8px; border-radius:8px; font-weight:bold; text-align:center; text-decoration:none; font-size:12px;">
                        ☕ طلبات (${sess.orders ? sess.orders.length : 0})
                    </a>
                    <button onclick="openEndBilliardModal(${sess.id})" style="flex:1; background:#ef4444; color:white; border:none; padding:8px; border-radius:8px; font-weight:bold; cursor:pointer;">
                        🏁 محاسبة
                    </button>
                </div>
            `;
        } else {
            feltHtml = `
                <div class="table-felt" style="background:#f8fafc; border:1px dashed #cbd5e1; color:#94a3b8;">
                    <div style="font-size:22px; font-weight:bold; padding:10px 0;">جاهزة للاستخدام</div>
                    <div style="font-size:11px;">ساعة: ${parseFloat(tbl.rate_per_hour).toFixed(2)} ج.م • جيم: ${parseFloat(tbl.rate_per_game).toFixed(2)} ج.م</div>
                </div>
            `;
            actionsHtml = `
                <button onclick="openStartBilliardModal(${tbl.id}, '${escapeHtml(tbl.display_name)}', ${tbl.rate_per_hour}, ${tbl.rate_per_game})" style="width:100%; background:#059669; color:white; border:none; padding:12px; border-radius:8px; font-size:14px; font-weight:bold; cursor:pointer;">
                    ▶️ بدء تشغيل الطاولة
                </button>
            `;
        }

        return `
            <div class="billiard-card ${cardClass}">
                <div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <span style="font-size:16px; font-weight:800; color:#0f172a;">🎱 ${escapeHtml(tbl.display_name)}</span>
                        ${statusBadge}
                    </div>
                    ${feltHtml}
                </div>
                ${actionsHtml}
            </div>
        `;
    }).join('');
}

function tickBilliardsLocally() {
    tables.forEach(tbl => {
        const sess = tbl.current_session;
        if (sess && sess.status === 'active' && sess.billing_mode === 'hourly') {
            sess.total_seconds_from_start = parseInt(sess.total_seconds_from_start) + 1;
            const netSec = Math.max(0, parseInt(sess.total_seconds_from_start) - parseInt(sess.total_paused_seconds));
            const el = document.getElementById(`billiardDigits_${tbl.id}`);
            if (el) el.textContent = formatSeconds(netSec);
        }
    });
}

function formatSeconds(totalSec) {
    let hrs = Math.floor(totalSec / 3600);
    let mins = Math.floor((totalSec % 3600) / 60);
    let secs = totalSec % 60;
    return `${hrs.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
}

function openStartBilliardModal(tableId, name, hourRate, gameRate) {
    document.getElementById('startTableId').value = tableId;
    document.getElementById('startTableTitle').textContent = `تشغيل: ${name}`;
    document.getElementById('startHourRate').textContent = `${parseFloat(hourRate).toFixed(2)} ج.م / س`;
    document.getElementById('startGameRate').textContent = `${parseFloat(gameRate).toFixed(2)} ج.م / جيم`;
    document.querySelector('input[name="billiardMode"][value="hourly"]').checked = true;
    updateBilliardModeRadio();
    document.getElementById('startBilliardModal').style.display = 'flex';
}

function updateBilliardModeRadio() {
    const val = document.querySelector('input[name="billiardMode"]:checked').value;
    document.getElementById('labelHour').style.borderColor = (val === 'hourly') ? '#059669' : '#e2e8f0';
    document.getElementById('labelGame').style.borderColor = (val === 'per_game') ? '#059669' : '#e2e8f0';
}

async function submitStartBilliard() {
    const tableId = document.getElementById('startTableId').value;
    const mode = document.querySelector('input[name="billiardMode"]:checked').value;
    const cust = document.getElementById('startBilliardCustomer').value.trim();

    const res = await fetch('ajax.php?action=start_session', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ table_id: tableId, billing_mode: mode, customer_name: cust })
    });
    const data = await res.json();
    if (data.success) {
        closeModal('startBilliardModal');
        loadTables();
    } else {
        alert(data.error);
    }
}

async function addGame(sessionId) {
    const fd = new FormData();
    fd.append('session_id', sessionId);
    await fetch('ajax.php?action=increment_game', { method: 'POST', body: fd });
    loadTables();
}

async function pauseBilliard(sessionId) {
    const fd = new FormData();
    fd.append('session_id', sessionId);
    await fetch('ajax.php?action=pause_session', { method: 'POST', body: fd });
    loadTables();
}

async function resumeBilliard(sessionId) {
    const fd = new FormData();
    fd.append('session_id', sessionId);
    await fetch('ajax.php?action=resume_session', { method: 'POST', body: fd });
    loadTables();
}

async function openEndBilliardModal(sessionId) {
    activeBilliardSessionId = sessionId;
    const modal = document.getElementById('endBilliardModal');
    const body = document.getElementById('endBilliardModalBody');
    body.innerHTML = 'جاري الحساب...';
    modal.style.display = 'flex';

    const res = await fetch(`ajax.php?action=preview_bill&session_id=${sessionId}`);
    const data = await res.json();
    if (data.success) {
        const s = data.session;
        body.innerHTML = `
            <div style="background:#f8fafc; border-radius:10px; padding:12px; margin-bottom:14px; font-size:12.5px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                    <span>الطاولة:</span><strong>${s.display_name}</strong>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                    <span>نظام الحساب:</span><strong>${s.billing_mode === 'hourly' ? data.billable_minutes + ' دقيقة' : s.game_count + ' جيم'}</strong>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                    <span>حساب الطاولة:</span><strong style="color:#059669;">${parseFloat(data.gaming_charge).toFixed(2)} ج.م</strong>
                </div>
                ${parseFloat(data.cafe_total) > 0 ? `
                <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                    <span>مشروبات كافيه:</span><strong style="color:#0284c7;">${parseFloat(data.cafe_total).toFixed(2)} ج.م</strong>
                </div>` : ''}
                <div style="display:flex; justify-content:space-between; border-top:1px dashed #cbd5e1; padding-top:6px; font-size:16px; font-weight:900;">
                    <span>الإجمالي:</span><span style="color:#059669;" id="endBilliardGrandTotal">${parseFloat(data.grand_total).toFixed(2)} ج.م</span>
                </div>
            </div>
            <div style="margin-bottom:12px;">
                <label style="font-size:12px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">الخصم:</label>
                <input type="number" id="billiardDiscInput" value="0" min="0" oninput="updateBilliardNet(${data.grand_total})" style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e1; font-weight:bold;">
            </div>
            <div style="margin-bottom:14px;">
                <label style="font-size:12px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">طريقة الدفع:</label>
                <select id="billiardPayMethod" style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e1;">
                    <option value="cash">💵 نقداً (كاش)</option>
                    <option value="card">💳 فيزا</option>
                    <option value="instapay">⚡ إنستاباي</option>
                    <option value="vodafone_cash">📱 فودافون كاش</option>
                </select>
            </div>
            <button onclick="submitEndBilliard()" style="width:100%; background:#059669; color:white; border:none; border-radius:8px; padding:12px; font-size:14px; font-weight:bold; cursor:pointer;">
                🏁 تحصيل وطباعة البون
            </button>
        `;
    }
}

function updateBilliardNet(base) {
    const disc = parseFloat(document.getElementById('billiardDiscInput').value) || 0;
    document.getElementById('endBilliardGrandTotal').textContent = `${Math.max(0, base - disc).toFixed(2)} ج.م`;
}

async function submitEndBilliard() {
    const disc = parseFloat(document.getElementById('billiardDiscInput').value) || 0;
    const method = document.getElementById('billiardPayMethod').value;

    const res = await fetch('ajax.php?action=end_session', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ session_id: activeBilliardSessionId, discount_amount: disc, payment_method: method })
    });
    const data = await res.json();
    if (data.success) {
        window.open(`receipt.php?id=${data.session_id}`, '_blank', 'width=400,height=600');
        closeModal('endBilliardModal');
        loadTables();
    } else {
        alert(data.error);
    }
}

function closeModal(id) { document.getElementById(id).style.display = 'none'; }
function escapeHtml(t) { return t ? t.toString().replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;") : ''; }
</script>

<?php include '../../includes/footer.php'; ?>
