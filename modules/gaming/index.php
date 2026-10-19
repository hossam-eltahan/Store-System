<?php
/**
 * PlayStation Management Dashboard - إدارة صالة البلايستيشن
 * Live Timers & Interactive Device Management
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';

requireLogin();
requirePermission('gaming.view');

$pageTitle = 'صالة البلايستيشن - التحكم بالوقت والأجهزة';

include '../../includes/header.php';
include '../../includes/navbar.php';
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

/* Expired Session Alert */
.timer-box.expired {
    background: #7f1d1d !important;
    color: #fca5a5 !important;
    border: 2px solid #dc2626 !important;
    animation: flashRed 1s infinite;
}

.timer-box.expired .timer-digits {
    color: #fca5a5 !important;
    animation: blinkFast 0.3s infinite;
}

.timer-box.countdown-low {
    background: #78350f !important;
    color: #fcd34d !important;
}

.timer-box.countdown-low .timer-digits {
    color: #fcd34d !important;
}

@keyframes flashRed {
    0%, 100% { box-shadow: 0 0 0 0 rgba(220,38,38,0.7); }
    50% { box-shadow: 0 0 0 12px rgba(220,38,38,0); }
}

@keyframes blinkFast {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.3; }
}
</style>

<script src="../../assets/js/customer_picker.js?v=1"></script>
<div class="container">

<div class="gaming-header-bar">
    <div>
        <h2 style="font-size: 18px; font-weight: 800; color: #0f172a; margin: 0 0 4px 0;">🎮 صالة ألعاب البلايستيشن</h2>
        <div style="font-size: 12.5px; color: #64748b;">متابعة الوقت المباشر، التايمر، وتبديل الأجهزة وإصدار الفواتير المجمعة</div>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="../../index.php" class="btn btn-secondary" style="font-weight:700; font-size:12px; padding:8px 14px; border-radius:8px; border:1px solid #cbd5e1; background:#fff; text-decoration:none; display:inline-flex; align-items:center; gap:4px; color:#334155;">
            <span>🔙</span> الرئيسية
        </a>
        <?php if (hasPermission('gaming.devices')): ?>
        <a href="add.php" style="background:#2563eb; color:white; font-weight:700; font-size:12px; padding:8px 14px; border-radius:8px; text-decoration:none; display:inline-flex; align-items:center; gap:4px;">
            <span>➕</span> إضافة جهاز
        </a>
        <?php endif; ?>
        <button onclick="loadDevices()" class="btn btn-secondary" style="font-weight:700; font-size:12px; padding:8px 14px; border-radius:8px; border:1px solid #cbd5e1; background:#fff; cursor:pointer;">
            🔄 تحديث الحالة
        </button>
        <a href="../pos/index.php" class="btn" style="background:#ea580c; color:white; font-weight:700; font-size:12px; padding:8px 14px; border-radius:8px; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
            <span>⚡</span> فتح كاشير الكافيه POS
        </a>
    </div>
</div>

<!-- Search bar -->
<div style="margin-bottom: 16px;">
    <input type="text" id="gamingSearch" placeholder="🔍 بحث باسم الجهاز أو باسم العميل اللي قاعد..." oninput="renderDevices()" autocomplete="off"
           style="width:100%; max-width:420px; padding:10px 14px; border-radius:10px; border:1.5px solid #cbd5e1; font-family:inherit; font-size:13px; font-weight:600; background:#fff;">
</div>

<!-- Devices Grid -->
<div class="gaming-devices-grid" id="devicesGrid">
    <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #64748b;">
        جاري تحميل صالة البلايستيشن...
    </div>
</div>

<!-- Modal: Start Game -->
<div class="game-modal-backdrop" id="startGameModal" style="position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); display:none; align-items:center; justify-content:center; z-index:9999;">
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
            <input type="hidden" id="startCustomerId" value="">
            <div id="startCustomerBalance" style="font-size: 12px; display: none; margin-top: -10px; margin-bottom: 10px;"></div>

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
<div class="game-modal-backdrop" id="endSessionModal" style="position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); display:none; align-items:center; justify-content:center; z-index:9999;">
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
<div class="game-modal-backdrop" id="transferModal" style="position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); display:none; align-items:center; justify-content:center; z-index:9999;">
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

<!-- Modal: Cross-hall transfer (gaming -> cafe table) -->
<div class="game-modal-backdrop" id="crossTransferModal" style="position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); display:none; align-items:center; justify-content:center; z-index:9999;">
    <div class="game-modal" style="max-width: 360px;">
        <div style="padding: 14px 18px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size: 15px; font-weight: 800; margin: 0; color: #0f172a;">🔀 نقل لصالة أخرى</h3>
            <button onclick="closeModal('crossTransferModal')" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <div style="padding: 18px;">
            <input type="hidden" id="crossTransferSessionId">
            <label style="font-size: 12.5px; font-weight: 700; color: #475569; display: block; margin-bottom: 8px;">اختر الوجهة المتاحة:</label>
            <select id="crossTransferTargetSelect" onchange="renderCrossExtra()" style="width: 100%; padding: 10px; border-radius: 8px; border: 1.5px solid #cbd5e1; font-size: 13px; font-family: inherit; margin-bottom:12px;"></select>
            <div id="crossExtra"></div>
            <div style="background:#f5f3ff; border:1px solid #ddd6fe; border-radius:8px; padding:8px 12px; font-size:12px; color:#6d28d9; margin-top:12px;">
                ⏱️ العدّاد يتوقف هنا، والرصيد المستحق يترحل على الفاتورة الجديدة.
            </div>
        </div>
        <div style="padding: 12px 18px; background: #f8fafc; border-top: 1px solid #e2e8f0; text-align: left;">
            <button onclick="submitCrossTransfer()" style="background: #7c3aed; color: white; border: none; border-radius: 8px; padding: 10px 20px; font-size: 13px; font-weight: 800; cursor: pointer;">
                🔀 تأكيد النقل
            </button>
        </div>
    </div>
</div>

<!-- Modal: Delete Device Confirmation -->
<div class="game-modal-backdrop" id="deleteDeviceModal" style="position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); display:none; align-items:center; justify-content:center; z-index:9999;">
    <div class="game-modal" style="max-width: 380px;">
        <div style="padding: 20px; text-align: center;">
            <div style="width: 70px; height: 70px; background: #fee2e2; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px auto;">
                <span style="font-size: 36px;">🗑️</span>
            </div>
            <h3 style="font-size: 20px; font-weight: 800; margin: 0 0 12px 0; color: #0f172a;">حذف جهاز البلايستيشن</h3>
            <p style="font-size: 15px; color: #64748b; margin: 0 0 24px 0; line-height: 1.6;">
                هل تريد حذف <strong id="deleteDeviceName" style="color: #dc2626;"></strong>؟
            </p>
            <input type="hidden" id="deleteDeviceId">
            <div style="display: flex; gap: 12px; justify-content: center;">
                <button onclick="closeModal('deleteDeviceModal')" style="background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; padding: 12px 28px; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 14px;">
                    إلغاء
                </button>
                <button onclick="confirmDeleteDevice()" style="background: #dc2626; color: white; border: none; padding: 12px 28px; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 14px;">
                    حذف
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Extend Session Time -->
<div class="game-modal-backdrop" id="extendModal" style="position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); display:none; align-items:center; justify-content:center; z-index:9999;">
    <div class="game-modal">
        <div style="padding: 14px 18px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <h3 id="extendModalTitle" style="font-size: 15px; font-weight: 800; margin: 0; color: #0f172a;">إضافة وقت للجلسة</h3>
            <button onclick="closeModal('extendModal')" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <div style="padding: 18px;">
            <input type="hidden" id="extendSessionId">
            <label style="font-size: 12.5px; font-weight: 700; color: #475569; display: block; margin-bottom: 8px;">اختر مدة الإضافة:</label>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 16px;">
                <button onclick="setExtendMinutes(15)" style="background:#e0f2fe; color:#0284c7; border:1px solid #bfdbfe; border-radius:8px; padding:10px; font-weight:bold; cursor:pointer;">➕ 15 دقيقة</button>
                <button onclick="setExtendMinutes(30)" style="background:#dcfce7; color:#15803d; border:1px solid #86efac; border-radius:8px; padding:10px; font-weight:bold; cursor:pointer;">➕ 30 دقيقة</button>
                <button onclick="setExtendMinutes(60)" style="background:#fef3c7; color:#92400e; border:1px solid #fde68a; border-radius:8px; padding:10px; font-weight:bold; cursor:pointer;">➕ ساعة</button>
            </div>
            <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">أو حدد الدقائق يدوياً:</label>
            <input type="number" id="extendMinutes" value="30" min="1" style="width: 100%; padding: 10px; border-radius: 8px; border: 1.5px solid #cbd5e1; font-size: 14px; font-family: inherit; margin-bottom: 14px;">
        </div>
        <div style="padding: 12px 18px; background: #f8fafc; border-top: 1px solid #e2e8f0; text-align: left;">
            <button onclick="submitExtendSession()" style="background: #3b82f6; color: white; border: none; border-radius: 8px; padding: 10px 24px; font-size: 14px; font-weight: 800; cursor: pointer;">
                ✨ تأكيد إضافة الوقت
            </button>
        </div>
    </div>
</div>

<script>
let devices = [];
let timerInterval = null;
let activeEndSessionId = null;
let expiredSessions = new Set();

document.addEventListener('DOMContentLoaded', () => {
    loadDevices();
    // حقل العميل في مودال بدء الجلسة = اختيار من الموجودين / عميل جديد / بدون
    initCustomerPicker(
        document.getElementById('startCustomerName'),
        document.getElementById('startCustomerId'),
        { balanceEl: document.getElementById('startCustomerBalance') }
    );
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
    const searchEl = document.getElementById('gamingSearch');
    const q = searchEl ? searchEl.value.trim().toLowerCase() : '';
    const shown = devices.filter(dev => {
        if (!q) return true;
        const cust = (dev.current_session && dev.current_session.customer_name) ? String(dev.current_session.customer_name).toLowerCase() : '';
        return String(dev.display_name).toLowerCase().includes(q) || cust.includes(q);
    });
    if (shown.length === 0) {
        grid.innerHTML = `<div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #94a3b8; font-weight: 700;">لا توجد نتائج مطابقة للبحث</div>`;
        return;
    }
    grid.innerHTML = shown.map(dev => {
        const sess = dev.current_session;
        const isBusy = !!sess;
        const isPaused = isBusy && sess.status === 'paused';

        let cardClass = isBusy ? (isPaused ? 'paused' : 'active') : 'available';
        let statusBadge = isBusy ? 
            (isPaused ? '<span class="device-badge paused">⏸️ موقف مؤقت</span>' : `<span class="device-badge active">🎮 ${sess.session_type === 'multi' ? 'مالتي' : 'سنجل'}</span>`) : 
            '<span class="device-badge available">🟢 متاح وشاغر</span>';

        let timerHtml = '';
        let actionsHtml = '';

         if (isBusy) {
            let netSec = Math.max(0, parseInt(sess.total_seconds_from_start) - parseInt(sess.total_paused_seconds));
            let presetSec = (sess.time_mode === 'countdown' && sess.preset_minutes) ? (parseInt(sess.preset_minutes) * 60) : 0;
            let remainingSec = presetSec > 0 ? Math.max(0, presetSec - netSec) : netSec;
            let isExpired = (sess.time_mode === 'countdown' && presetSec > 0 && remainingSec <= 0);
            let isCountdownLow = (sess.time_mode === 'countdown' && presetSec > 0 && remainingSec <= 60 && remainingSec > 0);

             timerHtml = `
                <div class="timer-box ${isPaused ? 'paused' : ''} ${isExpired ? 'expired' : ''} ${isCountdownLow ? 'countdown-low' : ''}" id="timerBox_${dev.id}" data-started="${sess.started_at}" data-paused="${sess.total_paused_seconds}" data-status="${sess.status}" data-time-mode="${sess.time_mode || 'open'}">
                    <div class="timer-digits" id="timerDigits_${dev.id}">${formatSeconds(remainingSec)}</div>
                    <div class="timer-subtext">${sess.time_mode === 'countdown' ? (isExpired ? '⚠️ انتهى الوقت!' : (isCountdownLow ? `⏰ ينتهي خلال ${Math.ceil(remainingSec/60)} دقيقة` : 'باقي: ')) : 'بدأ في: '}${formatTime(sess.started_at)}${sess.time_mode === 'countdown' && !isExpired && !isCountdownLow ? ` • ${parseFloat(sess.applied_rate).toFixed(2)} ج.م/س` : ''}</div>
                </div>
            `;

             actionsHtml = `
                <div style="display:flex; gap:6px;">
                    <a href="../pos/index.php?gaming_session_id=${sess.id}" title="إضافة طلب للجلسة" style="flex:1; background:#ea580c; color:#fff; border:none; padding:9px 6px; border-radius:8px; font-weight:bold; font-size:12px; cursor:pointer; text-align:center; text-decoration:none;">
                        ☕ إضافة طلب
                    </a>
                    <button onclick="viewSessionOrders(${sess.id})" title="عرض طلبات الجلسة" style="flex:1; background:#f1f5f9; color:#334155; border:1px solid #cbd5e1; padding:9px 6px; border-radius:8px; font-weight:bold; font-size:12px; cursor:pointer;">
                        🧾 الطلبات (${sess.orders ? sess.orders.length : 0})
                    </button>
                </div>
                <div style="display:flex; gap:6px; margin-top:6px;">
                    <button onclick="openTransferModal(${sess.id})" style="flex:1; background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; padding:9px 6px; border-radius:8px; font-weight:bold; font-size:12px; cursor:pointer;">
                        🔄 نقل
                    </button>
                    <button onclick="openCrossTransferModal(${sess.id})" title="نقل الجلسة إلى صالة أخرى" style="flex:1; background:#f5f3ff; color:#7c3aed; border:1px solid #ddd6fe; padding:9px 6px; border-radius:8px; font-weight:bold; font-size:12px; cursor:pointer;">
                        🔀 صالة أخرى
                    </button>
                </div>
                <div style="display:flex; gap:6px; margin-top:6px;">
                    ${isExpired && sess.time_mode === 'countdown' ?
                        `<button style="flex:1; background:#3b82f6; color:#fff; border:none; padding:9px 6px; border-radius:8px; font-weight:bold; font-size:12px; cursor:pointer;" onclick="openExtendModal(${sess.id}, '${escapeHtml(dev.display_name)}', ${sess.preset_minutes})">
                            ⏰ إضافة وقت
                        </button>` : ''
                    }
                    ${isPaused ?
                        `<button style="flex:1; background:#3b82f6; color:#fff; border:none; padding:9px 6px; border-radius:8px; font-weight:bold; font-size:12px; cursor:pointer;" onclick="resumeSession(${sess.id})">▶️ استئناف</button>` :
                        `<button style="flex:1; background:#f59e0b; color:#fff; border:none; padding:9px 6px; border-radius:8px; font-weight:bold; font-size:12px; cursor:pointer;" onclick="pauseSession(${sess.id})">⏸️ إيقاف</button>`
                    }
                    <button onclick="switchType(${sess.id}, '${sess.session_type === 'single' ? 'multi' : 'single'}')" style="flex:1; background:#f1f5f9; color:#334155; border:1px solid #cbd5e1; padding:9px 6px; border-radius:8px; font-weight:bold; font-size:12px; cursor:pointer;">
                        🔄 ${sess.session_type === 'single' ? 'تحويل مالتي' : 'تحويل سنجل'}
                    </button>
                    <button onclick="openEndSessionModal(${sess.id})" style="flex:1; background:#ef4444; color:#fff; border:none; padding:9px 6px; border-radius:8px; font-weight:800; font-size:12px; cursor:pointer;">
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

        let manageButtons = '';
        <?php if (hasPermission('gaming.devices')): ?>
        manageButtons = `
            <div style="display:flex; gap:6px; margin-top:12px; padding-top:12px; border-top:1px dashed #e2e8f0;">
                <a href="edit.php?id=${dev.id}" style="flex:1; background:#f1f5f9; color:#334155; border:1px solid #cbd5e1; padding:6px 10px; border-radius:6px; text-align:center; font-size:11px; font-weight:bold; text-decoration:none;">
                    ✏️ تعديل
                </a>
                <button onclick="deleteDevice(${dev.id}, '${escapeHtml(dev.display_name)}')" style="flex:1; background:#fee2e2; color:#dc2626; border:1px solid #fca5a5; padding:6px 10px; border-radius:6px; font-size:11px; font-weight:bold; cursor:pointer;">
                    🗑️ حذف
                </button>
            </div>
        `;
        <?php endif; ?>

        return `
            <div class="device-card ${cardClass}" id="deviceCard_${dev.id}">
                <div>
                    <div class="device-card-header">
                        <div class="device-title">
                            <span>🎮</span> ${escapeHtml(dev.display_name)}
                        </div>
                        ${statusBadge}
                    </div>
                    ${sess && sess.customer_name ? `<div style="display:flex; align-items:center; gap:8px; background:#fffbeb; border:1px solid #fde68a; border-radius:10px; padding:8px 12px; margin-bottom:10px;"><span style="font-size:18px;">👤</span><span style="font-size:15px; font-weight:800; color:#92400e;">${escapeHtml(sess.customer_name)}</span></div>` : ''}
                    ${timerHtml}
                </div>
                ${actionsHtml}
                ${manageButtons}
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
            
            const isCountdown = sess.time_mode === 'countdown' && sess.preset_minutes;
            const presetSec = isCountdown ? (parseInt(sess.preset_minutes) * 60) : 0;
            const displaySec = isCountdown ? Math.max(0, presetSec - netSec) : netSec;
            
            const el = document.getElementById(`timerDigits_${dev.id}`);
            const box = document.getElementById(`timerBox_${dev.id}`);
            if (el) el.textContent = formatSeconds(displaySec);
            
            // Check for countdown expiration
            if (isCountdown && displaySec <= 0) {
                if (box) {
                    box.classList.add('expired');
                    box.classList.remove('countdown-low');
                }
                if (!expiredSessions.has(sess.id)) {
                    expiredSessions.add(sess.id);
                    playNotificationSound();
                    showToast('انتهى وقت جهاز ' + dev.display_name + '! يرجى إنهاء الجلسة.', 'info');
                    // Mark as notified server-side to prevent duplicate 15s alerts
                    fetch('ajax.php?action=mark_notified', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'session_id=' + sess.id + '&type=gaming'
                    }).catch(() => {});
                    // Re-render to expose extend-time option immediately
                    loadDevices();
                }
            } else if (isCountdown && displaySec <= 60 && displaySec > 0) {
                if (box) box.classList.add('countdown-low');
            } else if (box && !isCountdown) {
                box.classList.remove('expired', 'countdown-low');
            }
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
    document.getElementById('startCustomerId').value = '';
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
                customer_name: customerName,
                customer_id: document.getElementById('startCustomerId').value || 0
            })
        });
        const data = await res.json();
        if (data.success) {
            closeModal('startGameModal');
            loadDevices();
        } else {
            appAlert(data.error || 'حدث خطأ');
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
        appAlert('لا توجد أجهزة شاغرة حالياً للنقل إليها!', 'info');
        return;
    }

    select.innerHTML = available.map(d => `<option value="${d.id}">${d.display_name} (سنجل: ${d.rate_single} / مالتي: ${d.rate_multi})</option>`).join('');
    document.getElementById('transferModal').style.display = 'flex';
}

function viewSessionOrders(sessionId) {
    openReceiptModal(`receipt.php?id=${sessionId}`);
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
        appAlert(data.error);
    }
}

async function openCrossTransferModal(sessionId) {
    document.getElementById('crossTransferSessionId').value = sessionId;
    const res = await fetch('ajax.php?action=transfer_tables_list');
    const data = await res.json();
    if (!data.success) {
        appAlert(data.error);
        return;
    }
    let html = '';
    if ((data.tables || []).length) {
        html += `<optgroup label="🪑 طاولات الصالة">` + data.tables.map(t => `<option value="tables:${t.id}">${t.display_name}</option>`).join('') + `</optgroup>`;
    }
    if ((data.btables || []).length) {
        html += `<optgroup label="🎱 بلياردو">` + data.btables.map(t => `<option value="billiards:${t.id}">${t.display_name}</option>`).join('') + `</optgroup>`;
    }
    if (!html) {
        appAlert('لا توجد وجهات شاغرة في الصالات الأخرى حالياً!', 'info');
        return;
    }
    document.getElementById('crossTransferTargetSelect').innerHTML = html;
    renderCrossExtra();
    document.getElementById('crossTransferModal').style.display = 'flex';
}

function renderCrossExtra() {
    const val = document.getElementById('crossTransferTargetSelect').value || '';
    const hall = val.split(':')[0];
    document.getElementById('crossExtra').innerHTML = hall === 'billiards'
        ? `<label style="font-size:12px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">نظام الحساب:</label>
           <select id="crossBillingMode" style="width:100%; padding:10px; border-radius:8px; border:1.5px solid #cbd5e1; font-size:13px; font-family:inherit;"><option value="hourly">بالساعة</option><option value="per_game">بالجيم</option></select>`
        : '';
}

async function submitCrossTransfer() {
    const sessionId = document.getElementById('crossTransferSessionId').value;
    const val = document.getElementById('crossTransferTargetSelect').value || '';
    const parts = val.split(':');
    if (parts.length !== 2) return;
    const hall = parts[0];
    const targetId = parts[1];
    if (!await appConfirm('سيتم إيقاف العدّاد وترحيل الرصيد المستحق على الفاتورة الجديدة. متابعة؟')) return;
    const payload = { session_id: sessionId };
    let action = 'transfer_to_table';
    if (hall === 'billiards') {
        action = 'transfer_to_billiards';
        payload.target_table_id = targetId;
        const bm = document.getElementById('crossBillingMode');
        payload.billing_mode = bm ? bm.value : 'hourly';
    } else {
        payload.target_table_id = targetId;
    }
    const res = await fetch('ajax.php?action=' + action, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (!data.success) {
        appAlert(data.error);
        return;
    }
    closeModal('crossTransferModal');
    const carried = parseFloat(data.carried_amount || 0);
    appAlert(carried > 0 ? `تم النقل برصيد مرحل ${carried.toFixed(2)} ج.م` : 'تم النقل بنجاح', 'success');
    loadDevices();
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
            ${parseFloat(data.transferred_amount) > 0 ? `
            <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                <span>رصيد منقول من جلسة سابقة:</span>
                <strong style="color:#7c3aed;">${parseFloat(data.transferred_amount).toFixed(2)} ج.م</strong>
            </div>` : ''}
            <div style="display:flex; justify-content:space-between; margin-bottom:6px; color:#059669;">
                <span>المدفوع مسبقاً:</span>
                <strong>${parseFloat(data.cafe_paid).toFixed(2)} ج.م</strong>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:6px; color:#d97706;">
                <span>المتبقي للتحصيل:</span>
                <strong>${parseFloat(data.amount_due).toFixed(2)} ج.م</strong>
            </div>
            <div style="display:flex; justify-content:space-between; border-top:1px dashed #cbd5e1; padding-top:6px; font-size:16px; font-weight:900;">
                <span>الإجمالي الكلي:</span>
                <span style="color:#ea580c;" id="endGrandTotalDisplay">${parseFloat(data.grand_total).toFixed(2)} ج.م</span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-top:6px; color:#059669; font-weight:800;">
                <span>المطلوب بعد الخصم:</span>
                <span id="endNetTotalDisplay">${parseFloat(data.amount_due).toFixed(2)} ج.م</span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-top:4px; color:#475569;">
                <span>ضريبة + خدمة (${parseFloat(data.tax_pct).toFixed(2)}%):</span>
                <span id="endTaxDisplay">${parseFloat(data.tax_amount).toFixed(2)} ج.م</span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-top:4px; font-weight:900; font-size:15px;">
                <span>المطلوب تحصيله:</span>
                <span style="color:#ea580c;" id="endFinalDisplay">${(parseFloat(data.amount_due) + parseFloat(data.tax_amount)).toFixed(2)} ج.م</span>
            </div>
        </div>

        <div style="margin-bottom:12px;">
            <label style="font-size:12px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">قيمة الخصم من المتبقي:</label>
            <input type="number" id="endDiscountInput" value="0" min="0" max="${parseFloat(data.amount_due)}" step="1" oninput="updateEndNetTotal(${data.amount_due}, ${data.cafe_tax_on_due}, ${data.tax_pct})" style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e1; font-size:14px; font-weight:bold;">
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

        <div style="margin-bottom:12px;">
            <label style="font-size:12px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">👤 العميل (اختياري — الواجهة للتحصيل الجزئي):</label>
            <div style="position: relative;">
                <input type="text" id="endCustomerNameInput" placeholder="ابحث أو اكتب اسم عميل جديد..." autocomplete="off"
                       style="width:100%; padding:8px 10px; border-radius:8px; border:1px solid #cbd5e1; font-size:13px; font-family:inherit;">
                <input type="hidden" id="endCustomerId" value="">
            </div>
        </div>

        <div style="margin-bottom:14px;">
            <label style="font-size:12px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">المحصل الآن (اتركه بالإجمالي للمحاسبة الكاملة):</label>
            <input type="number" id="endPaidInput" value="" min="0" max="${(parseFloat(data.amount_due) + parseFloat(data.tax_amount)).toFixed(2)}" step="1" oninput="updateEndPaid()"
                   style="width:100%; padding:9px 12px; border-radius:8px; border:1.5px solid #cbd5e1; font-size:15px; font-weight:800; text-align:center;">
            <div id="endPaidNote" style="font-size:11.5px; font-weight:700; border-radius:8px; padding:7px 10px; margin-top:6px; display:none;"></div>
        </div>

        <button onclick="submitEndSession()" style="width:100%; background:linear-gradient(135deg, #ea580c, #c2410c); color:white; border:none; border-radius:10px; padding:12px; font-size:15px; font-weight:800; cursor:pointer;">
            🏁 تحصيل الفاتورة وطباعة البون
        </button>
    `;
    const endPaidInput = document.getElementById('endPaidInput');
    if (endPaidInput) {
        endPaidInput.value = (parseFloat(data.amount_due) + parseFloat(data.tax_amount)).toFixed(2);
        updateEndPaid();
    }
    // عميل القفل: الّعناصر اللي مسجلة على العميل من البداية؟ نعم افتراضياً
    if (data.session && data.session.customer_id) {
        fetch('../../modules/customers/ajax.php?action=get&customer_id=' + data.session.customer_id)
            .then(r => r.json())
            .then(d => { if (d.success) initCustomerPicker(document.getElementById('endCustomerNameInput'), document.getElementById('endCustomerId')).set(d.customer); })
            .catch(() => {});
    } else {
        initCustomerPicker(document.getElementById('endCustomerNameInput'), document.getElementById('endCustomerId'));
    }
}

// المحصل أقل من الإجمالي → يترسل على حساب العميل (أو خطأ لو مفيش عميل)
function updateEndPaid() {
    const inp = document.getElementById('endPaidInput');
    const note = document.getElementById('endPaidNote');
    if (!inp || !note) return;
    const final = parseFloat(inp.max) || 0;
    let paid = parseFloat(inp.value) || 0;
    if (paid < 0) paid = 0;
    inp.value = paid.toFixed(2);
    const remaining = Math.round((final - paid) * 100) / 100;
    if (remaining <= 0.009) { note.style.display = 'none'; return; }
    const custName = (document.getElementById('endCustomerNameInput') || {}).value;
    const hasCustomer = (document.getElementById('endCustomerId') && document.getElementById('endCustomerId').value) || (custName && custName.trim());
    note.style.display = 'block';
    note.style.color = hasCustomer ? '#92400e' : '#dc2626';
    note.style.background = hasCustomer ? '#fefce8' : '#fef2f2';
    note.style.borderColor = hasCustomer ? '#fde047' : '#fca5a5';
    note.innerHTML = hasCustomer
        ? `💳 باقي <strong>${remaining.toFixed(2)}</strong> ج.م هيتسجل على حساب العميل (${(custName || '').trim() || 'مربوط'})`
        : `❌ باقي <strong>${remaining.toFixed(2)}</strong> ج.م — لازم تسجل العميل: اكتب اسمه في خانة العميل هنا (هيتسجل في العملاء تلقائياً)`;
}

function updateEndNetTotal(baseTotal, cafeTax, taxPct) {
    const input = document.getElementById('endDiscountInput');
    const disc = Math.min(baseTotal, Math.max(0, parseFloat(input.value) || 0));
    input.value = disc;
    const net = Math.max(0, baseTotal - disc);
    const tax = Math.round((Math.max(0, net - (parseFloat(cafeTax) || 0)) * (parseFloat(taxPct) || 0) / 100 + 1e-9) * 100) / 100;
    document.getElementById('endNetTotalDisplay').textContent = `${net.toFixed(2)} ج.م`;
    document.getElementById('endTaxDisplay').textContent = `${tax.toFixed(2)} ج.م`;
    document.getElementById('endFinalDisplay').textContent = `${(Math.round((net + tax + 1e-9) * 100) / 100).toFixed(2)} ج.م`;
}

function updateEndNetTotal(baseTotal, cafeTax, taxPct) {
    const input = document.getElementById('endDiscountInput');
    const disc = Math.min(baseTotal, Math.max(0, parseFloat(input.value) || 0));
    input.value = disc;
    const net = Math.max(0, baseTotal - disc);
    const tax = Math.round((Math.max(0, net - (parseFloat(cafeTax) || 0)) * (parseFloat(taxPct) || 0) / 100 + 1e-9) * 100) / 100;
    document.getElementById('endNetTotalDisplay').textContent = `${net.toFixed(2)} ج.م`;
    document.getElementById('endTaxDisplay').textContent = `${tax.toFixed(2)} ج.م`;
    const newFinal = Math.round((net + tax + 1e-9) * 100) / 100;
    document.getElementById('endFinalDisplay').textContent = `${newFinal.toFixed(2)} ج.م`;
    // الدفع الافتراضي يمشي مع الإجمالي الجديد (إلا لو الكاشير عدل المحصل بنفسه)
    const inp = document.getElementById('endPaidInput');
    if (inp && !inp.dataset.touchedOnce) {
        inp.max = newFinal.toFixed(2);
        inp.value = newFinal.toFixed(2);
        updateEndPaid();
    }
}

async function submitEndSession() {
    const disc = Math.max(0, parseFloat(document.getElementById('endDiscountInput').value) || 0);
    const method = document.getElementById('endPaymentMethod').value;
    let paidAmount = null;
    const inp = document.getElementById('endPaidInput');
    const finalStr = (document.getElementById('endFinalDisplay')?.textContent || '0').replace(/[^\d.]/g, '');
    const final = parseFloat(finalStr) || 0;
    if (inp) {
        paidAmount = Math.max(0, parseFloat(inp.value) || 0);
        inp.dataset.touchedOnce = '1';
        if (final > 0 && paidAmount < final - 0.009) {
            const cid = document.getElementById('endCustomerId').value;
            const cname = (document.getElementById('endCustomerNameInput')?.value || '').trim();
            if (!cid && cname === '') {
                appAlert('❌ لازم تسجل العميل — اكتب اسمه في خانة "العميل"لتسجيل باقي الفاتورة على حسابه', 'error');
                return;
            }
        }
    }

    try {
        const res = await fetch('ajax.php?action=end_session', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                session_id: activeEndSessionId,
                discount_amount: disc,
                paid_amount: paidAmount,
                customer_id: (document.getElementById('endCustomerId') || {}).value || 0,
                customer_name: (document.getElementById('endCustomerNameInput') || {}).value || '',
                payment_method: method
            })
        });
        const data = await res.json();
        if (data.success) {
            // Open print receipt
            openReceiptModal(`receipt.php?id=${data.session_id}`);
            closeModal('endSessionModal');
            loadDevices();
        } else {
            appAlert(data.error);
        }
    } catch (err) {
        console.error(err);
    }
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

function deleteDevice(deviceId, deviceName) {
    document.getElementById('deleteDeviceId').value = deviceId;
    document.getElementById('deleteDeviceName').textContent = deviceName;
    document.getElementById('deleteDeviceModal').style.display = 'flex';
}

function confirmDeleteDevice() {
    const deviceId = document.getElementById('deleteDeviceId').value;
    window.location.href = 'delete.php?id=' + deviceId;
}

function openExtendModal(sessionId, deviceName, currentPreset) {
    document.getElementById('extendSessionId').value = sessionId;
    document.getElementById('extendModalTitle').textContent = `إضافة وقت: ${deviceName}`;
    document.getElementById('extendMinutes').value = 30;
    document.getElementById('extendModal').style.display = 'flex';
}

function setExtendMinutes(minutes) {
    document.getElementById('extendMinutes').value = minutes;
}

async function submitExtendSession() {
    const sessionId = document.getElementById('extendSessionId').value;
    const minutes = document.getElementById('extendMinutes').value;

    const fd = new FormData();
    fd.append('session_id', sessionId);
    fd.append('add_minutes', minutes);

    const res = await fetch('ajax.php?action=extend_session', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
        closeModal('extendModal');
        expiredSessions.clear();
        loadDevices();
    } else {
        appAlert(data.error);
    }
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

// Close modals when clicking outside
window.addEventListener('click', function(e) {
    if (e.target.classList.contains('game-modal-backdrop')) {
        e.target.style.display = 'none';
    }
});
</script>
</div><!-- End container -->

<?php include '../../includes/footer.php'; ?>
