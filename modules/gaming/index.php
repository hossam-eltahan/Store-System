<?php
/**
 * PlayStation Management Dashboard - إدارة صالة البلايستيشن
 * Live Timers & Interactive Device Management
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';

requireLogin();

if (!hasPermission('gaming.view')) {
    showAccessDenied();
}

$pageTitle = 'صالة البلايستيشن - التحكم بالوقت والأجهزة';

include '../../includes/header.php';
?>

<style>
/* Gaming Hall Custom Styles */
.gaming-header-bar {
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

.gaming-devices-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 16px;
}

.device-card {
    background: #ffffff;
    border: 2px solid #e2e8f0;
    border-radius: 16px;
    padding: 18px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-shadow: 0 4px 10px rgba(0,0,0,0.03);
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.device-card.available {
    border-color: #10b981;
}

.device-card.active {
    border-color: #ea580c;
    box-shadow: 0 6px 18px rgba(234, 88, 12, 0.15);
}

.device-card.paused {
    border-color: #f59e0b;
    background: #fffbeb;
}

.device-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.device-title {
    font-size: 17px;
    font-weight: 800;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 8px;
}

.device-badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 800;
}

.device-badge.available {
    background: #ecfdf5;
    color: #059669;
    border: 1px solid #a7f3d0;
}

.device-badge.active {
    background: #fff7ed;
    color: #ea580c;
    border: 1px solid #fed7aa;
}

.device-badge.paused {
    background: #fef3c7;
    color: #d97706;
    border: 1px solid #fde68a;
}

/* Timer Display Area */
.timer-box {
    background: #0f172a;
    color: #38bdf8;
    border-radius: 12px;
    padding: 16px 12px;
    text-align: center;
    margin-bottom: 14px;
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.3);
}

.timer-digits {
    font-family: 'Courier New', monospace;
    font-size: 32px;
    font-weight: 900;
    letter-spacing: 2px;
    color: #38bdf8;
}

.timer-box.paused .timer-digits {
    color: #fbbf24;
    animation: blink 1s infinite;
}

@keyframes blink {
    50% { opacity: 0.5; }
}

.timer-subtext {
    font-size: 11.5px;
    color: #94a3b8;
    margin-top: 4px;
}

/* Financial Running Preview */
.running-cost-preview {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 10px 12px;
    margin-bottom: 14px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 12.5px;
}

.cost-amount {
    font-size: 16px;
    font-weight: 900;
    color: #ea580c;
}

/* Action Buttons Grid */
.device-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.device-actions .btn {
    flex: 1;
    padding: 8px 10px;
    font-size: 12px;
    font-weight: 700;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    transition: all 0.15s;
    text-decoration: none;
}

.btn-start {
    background: #10b981;
    color: white;
}
.btn-start:hover { background: #059669; }

.btn-pause {
    background: #f59e0b;
    color: white;
}
.btn-pause:hover { background: #d97706; }

.btn-resume {
    background: #3b82f6;
    color: white;
}
.btn-resume:hover { background: #2563eb; }

.btn-end {
    background: #ef4444;
    color: white;
    font-weight: 800;
}
.btn-end:hover { background: #dc2626; }

.btn-drinks {
    background: #f1f5f9;
    color: #0f172a;
    border: 1px solid #cbd5e1 !important;
}
.btn-drinks:hover { background: #e2e8f0; }

.btn-transfer {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #cbd5e1 !important;
}

/* Modals */
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

<div class="gaming-header-bar">
    <div>
        <h2 style="font-size: 18px; font-weight: 800; color: #0f172a; margin: 0 0 4px 0;">🎮 صالة ألعاب البلايستيشن</h2>
        <div style="font-size: 12.5px; color: #64748b;">متابعة الوقت المباشر، التايمر، وتبديل الأجهزة وإصدار الفواتير المجمعة</div>
    </div>
    <div style="display: flex; gap: 8px;">
        <button onclick="loadDevices()" class="btn btn-secondary" style="font-weight:700; font-size:12px; padding:8px 14px; border-radius:8px; border:1px solid #cbd5e1; background:#fff; cursor:pointer;">
            🔄 تحديث الحالة
        </button>
        <a href="../pos/index.php" class="btn" style="background:#ea580c; color:white; font-weight:700; font-size:12px; padding:8px 14px; border-radius:8px; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
            <span>⚡</span> فتح كاشير الكافيه POS
        </a>
    </div>
</div>

<!-- Devices Grid -->
<div class="gaming-devices-grid" id="devicesGrid">
    <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #64748b;">
        جاري تحميل صالة البلايستيشن...
    </div>
</div>

<!-- Modal: Start Game -->
<div class="game-modal-backdrop" id="startGameModal">
    <div class="game-modal">
        <div style="padding: 14px 18px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <h3 id="startModalDeviceTitle" style="font-size: 15px; font-weight: 800; margin: 0; color: #0f172a;">بدء جلسة لعب جديدة</h3>
            <button onclick="closeModal('startGameModal')" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <div style="padding: 18px;">
            <input type="hidden" id="startModalDeviceId">
            
            <label style="font-size: 12.5px; font-weight: 700; color: #475569; display: block; margin-bottom: 8px;">نوع اللعب:</label>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 16px;">
                <label style="border: 2px solid #e2e8f0; border-radius: 10px; padding: 12px; text-align: center; cursor: pointer;" id="labelSingle">
                    <input type="radio" name="startSessionType" value="single" checked style="display:none;" onchange="updateTypeRadio()">
                    <div style="font-size: 16px; font-weight: 800; color: #0f172a;">🎮 فردي (سنجل)</div>
                    <div id="startSingleRate" style="font-size: 12px; color: #ea580c; font-weight: 700; margin-top: 4px;">30.00 ج.م / ساعة</div>
                </label>
                <label style="border: 2px solid #e2e8f0; border-radius: 10px; padding: 12px; text-align: center; cursor: pointer;" id="labelMulti">
                    <input type="radio" name="startSessionType" value="multi" style="display:none;" onchange="updateTypeRadio()">
                    <div style="font-size: 16px; font-weight: 800; color: #0f172a;">👥 جماعي (مالتي)</div>
                    <div id="startMultiRate" style="font-size: 12px; color: #ea580c; font-weight: 700; margin-top: 4px;">40.00 ج.م / ساعة</div>
                </label>
            </div>

            <label style="font-size: 12.5px; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">اسم العميل (اختياري):</label>
            <input type="text" id="startCustomerName" placeholder="مثال: أحمد محمد..." style="width: 100%; padding: 10px; border-radius: 8px; border: 1.5px solid #cbd5e1; font-size: 13px; font-family: inherit; margin-bottom: 14px;">

            <label style="font-size: 12.5px; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">نظام الوقت:</label>
            <select id="startTimeMode" style="width: 100%; padding: 10px; border-radius: 8px; border: 1.5px solid #cbd5e1; font-size: 13px; font-family: inherit;" onchange="toggleCountdownInput()">
                <option value="open">⏳ وقت مفتوح (حساب حسب المدة المنقضية)</option>
                <option value="countdown">⏲️ وقت محدد (Countdown مسبق)</option>
            </select>

            <div id="countdownBox" style="display: none; margin-top: 12px;">
                <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">المدة بالدقائق:</label>
                <input type="number" id="startPresetMinutes" value="60" min="15" step="15" style="width: 100%; padding: 10px; border-radius: 8px; border: 1.5px solid #cbd5e1; font-size: 14px;">
            </div>
        </div>
        <div style="padding: 12px 18px; background: #f8fafc; border-top: 1px solid #e2e8f0; text-align: left;">
            <button onclick="submitStartSession()" style="background: #10b981; color: white; border: none; border-radius: 8px; padding: 10px 24px; font-size: 14px; font-weight: 800; cursor: pointer;">
                ▶️ تشغيل العداد الآن
            </button>
        </div>
    </div>
</div>

<!-- Modal: End Session & Checkout -->
<div class="game-modal-backdrop" id="endSessionModal">
    <div class="game-modal">
        <div style="padding: 14px 18px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <h3 id="endModalTitle" style="font-size: 15px; font-weight: 800; margin: 0; color: #0f172a;">إنهاء الجلسة والمحاسبة</h3>
            <button onclick="closeModal('endSessionModal')" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <div style="padding: 18px;" id="endModalBody">
            جاري جلب تفاصيل الحساب...
        </div>
    </div>
</div>

<!-- Modal: Transfer Device -->
<div class="game-modal-backdrop" id="transferModal">
    <div class="game-modal" style="max-width: 360px;">
        <div style="padding: 14px 18px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size: 15px; font-weight: 800; margin: 0; color: #0f172a;">نقل الجلسة لجهاز آخر</h3>
            <button onclick="closeModal('transferModal')" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <div style="padding: 18px;">
            <input type="hidden" id="transferSessionId">
            <label style="font-size: 12.5px; font-weight: 700; color: #475569; display: block; margin-bottom: 8px;">اختر الجهاز المتاح لنقل الجلسة إليه:</label>
            <select id="transferTargetSelect" style="width: 100%; padding: 10px; border-radius: 8px; border: 1.5px solid #cbd5e1; font-size: 13px; font-family: inherit;"></select>
        </div>
        <div style="padding: 12px 18px; background: #f8fafc; border-top: 1px solid #e2e8f0; text-align: left;">
            <button onclick="submitTransfer()" style="background: #3b82f6; color: white; border: none; border-radius: 8px; padding: 10px 20px; font-size: 13px; font-weight: 800; cursor: pointer;">
                🔄 نقل الجلسة
            </button>
        </div>
    </div>
</div>

<script>
let devices = [];
let timerInterval = null;
let activeEndSessionId = null;

document.addEventListener('DOMContentLoaded', () => {
    loadDevices();
    // Auto refresh every second for local timer & every 15s from server
    setInterval(tickTimersLocally, 1000);
    setInterval(loadDevices, 15000);
});

async function loadDevices() {
    try {
        const res = await fetch('ajax.php?action=get_devices');
        const data = await res.json();
        if (data.success) {
            devices = data.devices;
            renderDevices();
        }
    } catch (err) {
        console.error('Failed to load devices', err);
    }
}

function renderDevices() {
    const grid = document.getElementById('devicesGrid');
    grid.innerHTML = devices.map(dev => {
        const sess = dev.current_session;
        const isBusy = !!sess;
        const isPaused = isBusy && sess.status === 'paused';

        let cardClass = isBusy ? (isPaused ? 'paused' : 'active') : 'available';
        let statusBadge = isBusy ? 
            (isPaused ? '<span class="device-badge paused">⏸️ موقف مؤقت</span>' : `<span class="device-badge active">🎮 ${sess.session_type === 'multi' ? 'مالتي' : 'سنجل'}</span>`) : 
            '<span class="device-badge available">🟢 متاح وشاغر</span>';

        let timerHtml = '';
        let costHtml = '';
        let actionsHtml = '';

        if (isBusy) {
            let netSec = Math.max(0, parseInt(sess.total_seconds_from_start) - parseInt(sess.total_paused_seconds));
            let billMin = Math.max(parseInt(dev.min_billable_minutes), Math.ceil(netSec / 60));
            let timeCost = ((billMin / 60) * parseFloat(sess.applied_rate)).toFixed(2);
            let ordersTotal = parseFloat(sess.cafe_orders_total || 0).toFixed(2);
            let totalRunning = (parseFloat(timeCost) + parseFloat(ordersTotal)).toFixed(2);

            timerHtml = `
                <div class="timer-box ${isPaused ? 'paused' : ''}" id="timerBox_${dev.id}" data-started="${sess.started_at}" data-paused="${sess.total_paused_seconds}" data-status="${sess.status}">
                    <div class="timer-digits" id="timerDigits_${dev.id}">${formatSeconds(netSec)}</div>
                    <div class="timer-subtext">بدأ في: ${formatTime(sess.started_at)} • بمعدل ${parseFloat(sess.applied_rate).toFixed(2)} ج.م/س</div>
                </div>
            `;

            costHtml = `
                <div class="running-cost-preview">
                    <div>
                        <span style="font-weight:700;">وقت: ${timeCost} ج.م</span>
                        ${parseFloat(ordersTotal) > 0 ? `<span style="color:#0284c7; margin-right:6px;">• طلبات: ${ordersTotal} ج.م</span>` : ''}
                    </div>
                    <div>
                        <span style="font-size:11px; color:#64748b;">الإجمالي الحالي:</span>
                        <span class="cost-amount" id="runningCost_${dev.id}">${totalRunning} ج.م</span>
                    </div>
                </div>
            `;

            actionsHtml = `
                <div class="device-actions">
                    ${isPaused ? 
                        `<button class="btn btn-resume" onclick="resumeSession(${sess.id})">▶️ استئناف</button>` : 
                        `<button class="btn btn-pause" onclick="pauseSession(${sess.id})">⏸️ إيقاف</button>`
                    }
                    <button class="btn btn-drinks" onclick="switchType(${sess.id}, '${sess.session_type === 'single' ? 'multi' : 'single'}')">
                        🔄 ${sess.session_type === 'single' ? 'تحويل مالتي' : 'تحويل سنجل'}
                    </button>
                    <button class="btn btn-transfer" onclick="openTransferModal(${sess.id})">
                        📲 نقل
                    </button>
                    <a href="../pos/index.php" class="btn btn-drinks" title="إضافة مشروبات">
                        ☕ طلبات (${sess.orders ? sess.orders.length : 0})
                    </a>
                    <button class="btn btn-end" onclick="openEndSessionModal(${sess.id})">
                        🏁 محاسبة
                    </button>
                </div>
            `;
        } else {
            timerHtml = `
                <div class="timer-box" style="background:#f8fafc; border:1px dashed #cbd5e1;">
                    <div class="timer-digits" style="color:#94a3b8; font-size:24px;">00:00:00</div>
                    <div class="timer-subtext">سنجل: ${parseFloat(dev.rate_single).toFixed(2)} ج.م • مالتي: ${parseFloat(dev.rate_multi).toFixed(2)} ج.م</div>
                </div>
            `;

            actionsHtml = `
                <div class="device-actions">
                    <button class="btn btn-start" style="padding:12px; font-size:14px;" onclick="openStartModal(${dev.id}, '${escapeHtml(dev.display_name)}', ${dev.rate_single}, ${dev.rate_multi})">
                        ▶️ بدء تشغيل الجهاز
                    </button>
                </div>
            `;
        }

        return `
            <div class="device-card ${cardClass}" id="deviceCard_${dev.id}">
                <div>
                    <div class="device-card-header">
                        <div class="device-title">
                            <span>🎮</span> ${escapeHtml(dev.display_name)}
                        </div>
                        ${statusBadge}
                    </div>
                    ${timerHtml}
                    ${costHtml}
                </div>
                ${actionsHtml}
            </div>
        `;
    }).join('');
}

// Local tick timer every second
function tickTimersLocally() {
    devices.forEach(dev => {
        const sess = dev.current_session;
        if (sess && sess.status === 'active') {
            sess.total_seconds_from_start = parseInt(sess.total_seconds_from_start) + 1;
            const netSec = Math.max(0, parseInt(sess.total_seconds_from_start) - parseInt(sess.total_paused_seconds));
            const el = document.getElementById(`timerDigits_${dev.id}`);
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

function formatTime(dtStr) {
    if (!dtStr) return '';
    const d = new Date(dtStr);
    return d.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' });
}

// Open Start Modal
function openStartModal(deviceId, name, singleRate, multiRate) {
    document.getElementById('startModalDeviceId').value = deviceId;
    document.getElementById('startModalDeviceTitle').textContent = `تشغيل: ${name}`;
    document.getElementById('startSingleRate').textContent = `${parseFloat(singleRate).toFixed(2)} ج.م / ساعة`;
    document.getElementById('startMultiRate').textContent = `${parseFloat(multiRate).toFixed(2)} ج.م / ساعة`;
    document.getElementById('startCustomerName').value = '';
    document.querySelector('input[name="startSessionType"][value="single"]').checked = true;
    updateTypeRadio();
    document.getElementById('startGameModal').style.display = 'flex';
}

function updateTypeRadio() {
    const val = document.querySelector('input[name="startSessionType"]:checked').value;
    document.getElementById('labelSingle').style.borderColor = (val === 'single') ? '#ea580c' : '#e2e8f0';
    document.getElementById('labelSingle').style.background = (val === 'single') ? '#fff7ed' : '#ffffff';
    document.getElementById('labelMulti').style.borderColor = (val === 'multi') ? '#ea580c' : '#e2e8f0';
    document.getElementById('labelMulti').style.background = (val === 'multi') ? '#fff7ed' : '#ffffff';
}

function toggleCountdownInput() {
    const mode = document.getElementById('startTimeMode').value;
    document.getElementById('countdownBox').style.display = (mode === 'countdown') ? 'block' : 'none';
}

async function submitStartSession() {
    const deviceId = document.getElementById('startModalDeviceId').value;
    const sessionType = document.querySelector('input[name="startSessionType"]:checked').value;
    const timeMode = document.getElementById('startTimeMode').value;
    const presetMinutes = document.getElementById('startPresetMinutes').value;
    const customerName = document.getElementById('startCustomerName').value.trim();

    try {
        const res = await fetch('ajax.php?action=start_session', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                device_id: deviceId,
                session_type: sessionType,
                time_mode: timeMode,
                preset_minutes: presetMinutes,
                customer_name: customerName
            })
        });
        const data = await res.json();
        if (data.success) {
            closeModal('startGameModal');
            loadDevices();
        } else {
            alert(data.error || 'حدث خطأ');
        }
    } catch (err) {
        console.error(err);
    }
}

// Pause Session
async function pauseSession(sessionId) {
    const fd = new FormData();
    fd.append('session_id', sessionId);
    await fetch('ajax.php?action=pause_session', { method: 'POST', body: fd });
    loadDevices();
}

// Resume Session
async function resumeSession(sessionId) {
    const fd = new FormData();
    fd.append('session_id', sessionId);
    await fetch('ajax.php?action=resume_session', { method: 'POST', body: fd });
    loadDevices();
}

// Switch Single <-> Multi
async function switchType(sessionId, newType) {
    const fd = new FormData();
    fd.append('session_id', sessionId);
    fd.append('new_type', newType);
    await fetch('ajax.php?action=switch_type', { method: 'POST', body: fd });
    loadDevices();
}

// Open Transfer Modal
function openTransferModal(sessionId) {
    document.getElementById('transferSessionId').value = sessionId;
    const select = document.getElementById('transferTargetSelect');
    const available = devices.filter(d => !d.current_session);

    if (available.length === 0) {
        alert('لا توجد أجهزة شاغرة حالياً للنقل إليها!');
        return;
    }

    select.innerHTML = available.map(d => `<option value="${d.id}">${d.display_name} (سنجل: ${d.rate_single} / مالتي: ${d.rate_multi})</option>`).join('');
    document.getElementById('transferModal').style.display = 'flex';
}

async function submitTransfer() {
    const sessionId = document.getElementById('transferSessionId').value;
    const targetId = document.getElementById('transferTargetSelect').value;

    const fd = new FormData();
    fd.append('session_id', sessionId);
    fd.append('target_device_id', targetId);

    const res = await fetch('ajax.php?action=transfer_device', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
        closeModal('transferModal');
        loadDevices();
    } else {
        alert(data.error);
    }
}

// Open End Session Modal
async function openEndSessionModal(sessionId) {
    activeEndSessionId = sessionId;
    const modal = document.getElementById('endSessionModal');
    const body = document.getElementById('endModalBody');
    body.innerHTML = 'جاري حساب الفاتورة...';
    modal.style.display = 'flex';

    try {
        const res = await fetch(`ajax.php?action=preview_bill&session_id=${sessionId}`);
        const data = await res.json();
        if (data.success) {
            renderEndModalDetails(data);
        } else {
            body.innerHTML = `<div style="color:red;">${data.error}</div>`;
        }
    } catch (err) {
        console.error(err);
    }
}

function renderEndModalDetails(data) {
    const s = data.session;
    const body = document.getElementById('endModalBody');

    body.innerHTML = `
        <div style="background:#f8fafc; border-radius:10px; padding:12px; margin-bottom:14px; font-size:12.5px;">
            <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                <span>الجهاز:</span>
                <strong>${s.display_name}</strong>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                <span>مدة اللعب:</span>
                <strong>${data.billable_minutes} دقيقة</strong>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                <span>حساب وقت اللعب:</span>
                <strong style="color:#ea580c;">${parseFloat(data.time_charge).toFixed(2)} ج.م</strong>
            </div>
            ${parseFloat(data.cafe_total) > 0 ? `
            <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                <span>مشروبات وطلبات كافيه:</span>
                <strong style="color:#0284c7;">${parseFloat(data.cafe_total).toFixed(2)} ج.م</strong>
            </div>` : ''}
            <div style="display:flex; justify-content:space-between; border-top:1px dashed #cbd5e1; padding-top:6px; font-size:16px; font-weight:900;">
                <span>الإجمالي:</span>
                <span style="color:#ea580c;" id="endGrandTotalDisplay">${parseFloat(data.grand_total).toFixed(2)} ج.م</span>
            </div>
        </div>

        <div style="margin-bottom:12px;">
            <label style="font-size:12px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">قيمة الخصم (إن وجد):</label>
            <input type="number" id="endDiscountInput" value="0" min="0" step="1" oninput="updateEndNetTotal(${data.grand_total})" style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e1; font-size:14px; font-weight:bold;">
        </div>

        <div style="margin-bottom:14px;">
            <label style="font-size:12px; font-weight:700; color:#475569; display:block; margin-bottom:6px;">طريقة الدفع:</label>
            <select id="endPaymentMethod" style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e1; font-size:13px; font-family:inherit;">
                <option value="cash">💵 نقداً (كاش)</option>
                <option value="card">💳 فيزا / بطاقة</option>
                <option value="instapay">⚡ إنستاباي (InstaPay)</option>
                <option value="vodafone_cash">📱 فودافون كاش</option>
            </select>
        </div>

        <button onclick="submitEndSession()" style="width:100%; background:linear-gradient(135deg, #ea580c, #c2410c); color:white; border:none; border-radius:10px; padding:12px; font-size:15px; font-weight:800; cursor:pointer;">
            🏁 تحصيل الفاتورة وطباعة البون
        </button>
    `;
}

function updateEndNetTotal(baseTotal) {
    const disc = parseFloat(document.getElementById('endDiscountInput').value) || 0;
    const net = Math.max(0, baseTotal - disc);
    document.getElementById('endGrandTotalDisplay').textContent = `${net.toFixed(2)} ج.م`;
}

async function submitEndSession() {
    const disc = parseFloat(document.getElementById('endDiscountInput').value) || 0;
    const method = document.getElementById('endPaymentMethod').value;

    try {
        const res = await fetch('ajax.php?action=end_session', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                session_id: activeEndSessionId,
                discount_amount: disc,
                payment_method: method
            })
        });
        const data = await res.json();
        if (data.success) {
            // Open print receipt
            window.open(`receipt.php?id=${data.session_id}`, '_blank', 'width=400,height=600');
            closeModal('endSessionModal');
            loadDevices();
        } else {
            alert(data.error);
        }
    } catch (err) {
        console.error(err);
    }
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

function escapeHtml(text) {
    if (!text) return '';
    return text.toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
</script>

<?php include '../../includes/footer.php'; ?>
