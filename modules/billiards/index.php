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
include '../../includes/navbar.php';
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

/* Expired Billiard Session Alert */
.table-felt.expired {
    background: linear-gradient(135deg, #7f1d1d, #450a0a) !important;
    color: #fca5a5 !important;
    border: 2px solid #dc2626 !important;
    animation: flashRedBilliard 1s infinite;
}

.table-felt.expired .felt-digits,
.table-felt.expired .felt-games {
    color: #fca5a5 !important;
}

.table-felt.countdown-low {
    background: linear-gradient(135deg, #78350f, #92400e) !important;
    color: #fcd34d !important;
    border: 2px solid #f59e0b !important;
}

.table-felt.countdown-low .felt-digits {
    color: #fcd34d !important;
}

@keyframes flashRedBilliard {
    0%, 100% { box-shadow: 0 0 0 0 rgba(220,38,38,0.7); }
    50% { box-shadow: 0 0 0 12px rgba(220,38,38,0); }
}
</style>

<div class="container">

<div class="billiards-header-bar">
    <div>
        <h2 style="font-size: 18px; font-weight: 800; color: #0f172a; margin: 0 0 4px 0;">🎱 صالة طاولات البلياردو والسنوكر</h2>
        <div style="font-size: 12.5px; color: #64748b;">حساب الوقت بالساعة أو بعدد الأجوام (Games) مع ربط المشروبات</div>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="../../index.php" class="btn btn-secondary" style="font-weight:700; font-size:12px; padding:8px 14px; border-radius:8px; border:1px solid #cbd5e1; background:#fff; text-decoration:none; display:inline-flex; align-items:center; gap:4px; color:#334155;">
            <span>🔙</span> الرئيسية
        </a>
        <?php if (hasPermission('billiards.tables')): ?>
        <a href="add.php" style="background:#10b981; color:white; font-weight:700; font-size:12px; padding:8px 14px; border-radius:8px; text-decoration:none; display:inline-flex; align-items:center; gap:4px;">
            <span>➕</span> إضافة طاولة
        </a>
        <?php endif; ?>
        <button onclick="loadTables()" class="btn btn-secondary" style="font-weight:700; font-size:12px; padding:8px 14px; border-radius:8px; border:1px solid #cbd5e1; background:#fff; cursor:pointer;">
            🔄 تحديث الحالة
        </button>
        <a href="../pos/index.php" class="btn" style="background:#ea580c; color:white; font-weight:700; font-size:12px; padding:8px 14px; border-radius:8px; text-decoration:none; display:inline-flex; align-items:center; gap:4px;">
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
<div class="game-modal-backdrop" id="startBilliardModal" style="position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); display:none; align-items:center; justify-content:center; z-index:9999;">
    <div class="game-modal" style="background:#fff; border-radius:16px; width:100%; max-width:400px; overflow:hidden;">
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
            <input type="text" id="startBilliardCustomer" placeholder="اسم العميل..." style="width:100%; padding:9px; border-radius:8px; border:1px solid #cbd5e1; font-size:13px; font-family:inherit; margin-bottom:14px;">

            <label style="font-size: 12.5px; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">نظام الوقت:</label>
            <select id="startBilliardTimeMode" style="width: 100%; padding: 10px; border-radius: 8px; border: 1.5px solid #cbd5e1; font-size: 13px; font-family: inherit;" onchange="toggleBilliardCountdownInput()">
                <option value="open">⏳ وقت مفتوح (حساب حسب المدة)</option>
                <option value="countdown">⏲️ وقت محدد (Countdown)</option>
            </select>

            <div id="billiardCountdownBox" style="display: none; margin-top: 12px;">
                <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">المدة بالدقائق:</label>
                <input type="number" id="startBilliardPresetMinutes" value="60" min="15" step="15" style="width: 100%; padding: 10px; border-radius: 8px; border: 1.5px solid #cbd5e1; font-size: 14px;">
            </div>
        </div>
        <div style="padding:12px 18px; background:#f8fafc; border-top:1px solid #e2e8f0; text-align:left;">
            <button onclick="submitStartBilliard()" style="background:#059669; color:white; border:none; border-radius:8px; padding:10px 22px; font-size:14px; font-weight:800; cursor:pointer;">
                ▶️ تشغيل الطاولة
            </button>
        </div>
    </div>
</div>

<!-- Modal: End Billiard Session -->
<div class="game-modal-backdrop" id="endBilliardModal" style="position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); display:none; align-items:center; justify-content:center; z-index:9999;">
    <div class="game-modal" style="background:#fff; border-radius:16px; width:100%; max-width:420px; overflow:hidden;">
        <div style="padding:14px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="font-size:15px; font-weight:800; margin:0;">محاسبة طاولة البلياردو</h3>
            <button onclick="closeModal('endBilliardModal')" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <div style="padding:18px;" id="endBilliardModalBody">
            جاري جلب تفاصيل الحساب...
        </div>
    </div>
</div>

<!-- Modal: Extend Billiard Session Time -->
<div class="game-modal-backdrop" id="extendBilliardModal" style="position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); display:none; align-items:center; justify-content:center; z-index:9999;">
    <div class="game-modal" style="background:#fff; border-radius:16px; width:100%; max-width:400px; overflow:hidden;">
        <div style="padding:14px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
            <h3 id="extendBilliardModalTitle" style="font-size:15px; font-weight:800; margin:0;">إضافة وقت للطاولة</h3>
            <button onclick="closeModal('extendBilliardModal')" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <div style="padding:18px;">
            <input type="hidden" id="extendBilliardSessionId">
            <label style="font-size: 12.5px; font-weight: 700; color: #475569; display: block; margin-bottom: 8px;">اختر مدة الإضافة:</label>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 16px;">
                <button onclick="setExtendBilliardMinutes(15)" style="background:#e0f2fe; color:#0284c7; border:1px solid #bfdbfe; border-radius:8px; padding:10px; font-weight:bold; cursor:pointer;">➕ 15 دقيقة</button>
                <button onclick="setExtendBilliardMinutes(30)" style="background:#dcfce7; color:#15803d; border:1px solid #86efac; border-radius:8px; padding:10px; font-weight:bold; cursor:pointer;">➕ 30 دقيقة</button>
                <button onclick="setExtendBilliardMinutes(60)" style="background:#fef3c7; color:#92400e; border:1px solid #fde68a; border-radius:8px; padding:10px; font-weight:bold; cursor:pointer;">➕ ساعة</button>
            </div>
            <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">أو حدد الدقائق يدوياً:</label>
            <input type="number" id="extendBilliardMinutes" value="30" min="1" style="width: 100%; padding: 10px; border-radius: 8px; border: 1.5px solid #cbd5e1; font-size: 14px; font-family: inherit; margin-bottom: 14px;">
        </div>
        <div style="padding: 12px 18px; background: #f8fafc; border-top: 1px solid #e2e8f0; text-align: left;">
            <button onclick="submitExtendBilliardSession()" style="background: #3b82f6; color: white; border: none; border-radius: 8px; padding: 10px 24px; font-size: 14px; font-weight: 800; cursor: pointer;">
                ✨ تأكيد إضافة الوقت
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
            <h3 style="font-size: 20px; font-weight: 800; margin: 0 0 12px 0; color: #0f172a;">حذف طاولة البلياردو</h3>
            <p style="font-size: 15px; color: #64748b; margin: 0 0 24px 0; line-height: 1.6;">
                هل تريد حذف <strong id="deleteTableName" style="color: #dc2626;"></strong>؟
            </p>
            <input type="hidden" id="deleteTableId">
            <div style="display: flex; gap: 12px; justify-content: center;">
                <button onclick="closeModal('deleteTableModal')" style="background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; padding: 12px 28px; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 14px;">
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
let tables = [];
let activeBilliardSessionId = null;
let expiredBilliardSessions = new Set();

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
        const isCountdown = isBusy && sess.time_mode === 'countdown';
        
        let statusBadge = isBusy ? 
            (isPaused ? '<span style="background:#fef3c7; color:#d97706; padding:3px 8px; border-radius:12px; font-size:11px; font-weight:bold;">⏸️ موقف مؤقت</span>' : 
                        `<span style="background:#d1fae5; color:#065f46; padding:3px 8px; border-radius:12px; font-size:11px; font-weight:bold;">🎱 ${isHourly ? (isCountdown ? 'وقت محدد' : 'بالساعة') : 'بالجيم (' + sess.game_count + ')'}</span>`) : 
            '<span style="background:#ecfdf5; color:#059669; padding:3px 8px; border-radius:12px; font-size:11px; font-weight:bold;">🟢 متاحة وشاغرة</span>';

        let feltHtml = '';
        let actionsHtml = '';

         if (isBusy) {
            let netSec = Math.max(0, parseInt(sess.total_seconds_from_start) - parseInt(sess.total_paused_seconds));
            let displaySec = netSec;
            if (isCountdown) {
                displaySec = Math.max(0, (parseInt(sess.preset_minutes) * 60) - netSec);
            }
            
            let feltClass = '';
            if (isCountdown) {
                if (displaySec <= 0) {
                    feltClass = 'expired';
                } else if (displaySec <= 60) {
                    feltClass = 'countdown-low';
                }
            }

            if (isHourly) {
                feltHtml = `
                    <div class="table-felt ${feltClass}">
                        <div class="felt-digits" id="billiardDigits_${tbl.id}">${formatSeconds(displaySec)}</div>
                        <div style="font-size:11.5px; opacity:0.85; margin-top:4px;">${isCountdown ? (displaySec <= 0 ? '⚠️ انتهى الوقت!' : (displaySec <= 60 ? '⏰ ينتهي خلال ' + Math.ceil(displaySec/60) + ' دقيقة' : 'وقت متبقي')) : 'وقت مستهلك'} • ${parseFloat(sess.applied_rate).toFixed(2)} ج.م/س</div>
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
                    ${isHourly && isCountdown && displaySec <= 0 ? 
                        `<button onclick="openExtendBilliardModal(${sess.id}, '${escapeHtml(tbl.display_name)}', ${sess.preset_minutes})" style="flex:1; background:#3b82f6; color:white; border:none; padding:8px; border-radius:8px; font-weight:bold; cursor:pointer;">⏰ إضافة وقت</button>` : ''
                    }
                    <button onclick="viewBilliardOrders(${sess.id})" style="flex:1; background:#f1f5f9; color:#0f172a; border:1px solid #cbd5e1; padding:8px; border-radius:8px; font-weight:bold; text-align:center; font-size:12px; cursor:pointer;">
                        🧾 الطلبات (${sess.orders ? sess.orders.length : 0})
                    </button>
                    <a href="../pos/index.php?billiard_session_id=${sess.id}" style="flex:1; background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; padding:8px; border-radius:8px; font-weight:bold; text-align:center; text-decoration:none; font-size:12px;">
                        ☕ إضافة طلب
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

        let manageButtons = '';
        <?php if (hasPermission('billiards.tables')): ?>
        manageButtons = `
            <div style="display:flex; gap:6px; margin-top:12px; padding-top:12px; border-top:1px dashed #e2e8f0;">
                <a href="edit.php?id=${tbl.id}" style="flex:1; background:#f1f5f9; color:#334155; border:1px solid #cbd5e1; padding:6px 10px; border-radius:6px; text-align:center; font-size:11px; font-weight:bold; text-decoration:none;">
                    ✏️ تعديل
                </a>
                <button onclick="deleteTable(${tbl.id}, '${escapeHtml(tbl.display_name)}')" style="flex:1; background:#fee2e2; color:#dc2626; border:1px solid #fca5a5; padding:6px 10px; border-radius:6px; font-size:11px; font-weight:bold; cursor:pointer;">
                    🗑️ حذف
                </button>
            </div>
        `;
        <?php endif; ?>

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
                ${manageButtons}
            </div>
        `;
    }).join('');
}

function viewBilliardOrders(sessionId) {
    openReceiptModal(`receipt.php?id=${sessionId}`);
}

function tickBilliardsLocally() {
    tables.forEach(tbl => {
        const sess = tbl.current_session;
        if (sess && sess.status === 'active' && sess.billing_mode === 'hourly') {
            sess.total_seconds_from_start = parseInt(sess.total_seconds_from_start) + 1;
            const netSec = Math.max(0, parseInt(sess.total_seconds_from_start) - parseInt(sess.total_paused_seconds));
            
            const isCountdown = sess.time_mode === 'countdown' && sess.preset_minutes;
            let displaySec = netSec;
            if (isCountdown) {
                displaySec = Math.max(0, (parseInt(sess.preset_minutes) * 60) - netSec);
            }

            const el = document.getElementById(`billiardDigits_${tbl.id}`);
            const box = el ? el.closest('.table-felt') : null;
            
            if (el) el.textContent = formatSeconds(displaySec);
            
            // Check for countdown expiration
            if (isCountdown && displaySec <= 0) {
                if (box) {
                    box.classList.add('expired');
                    box.classList.remove('countdown-low');
                }
                if (!expiredBilliardSessions.has(sess.id)) {
                    expiredBilliardSessions.add(sess.id);
                    playNotificationSound();
                    showToast('انتهى وقت طاولة ' + tbl.display_name + '! يرجى إنهاء الجلسة.', 'info');
                    // Mark as notified server-side to prevent duplicate 15s alerts
                    fetch('ajax.php?action=mark_notified', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'session_id=' + sess.id + '&type=billiards'
                    }).catch(() => {});
                    loadTables();
                }
            } else if (isCountdown && displaySec <= 60 && displaySec > 0) {
                if (box) box.classList.add('countdown-low');
            } else if (isCountdown && displaySec > 60) {
                if (box) { box.classList.remove('expired', 'countdown-low'); }
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

function toggleBilliardCountdownInput() {
    const mode = document.getElementById('startBilliardTimeMode').value;
    document.getElementById('billiardCountdownBox').style.display = (mode === 'countdown') ? 'block' : 'none';
}

async function submitStartBilliard() {
    const tableId = document.getElementById('startTableId').value;
    const mode = document.querySelector('input[name="billiardMode"]:checked').value;
    const cust = document.getElementById('startBilliardCustomer').value.trim();
    const timeMode = document.getElementById('startBilliardTimeMode').value;
    const presetMinutes = document.getElementById('startBilliardPresetMinutes').value;

    const res = await fetch('ajax.php?action=start_session', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            table_id: tableId, 
            billing_mode: mode, 
            customer_name: cust,
            time_mode: timeMode,
            preset_minutes: presetMinutes
        })
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
                <div style="display:flex; justify-content:space-between; margin-bottom:6px; color:#059669;">
                    <span>المدفوع مسبقاً:</span><strong>${parseFloat(data.cafe_paid).toFixed(2)} ج.م</strong>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:6px; color:#d97706;">
                    <span>المتبقي للتحصيل:</span><strong>${parseFloat(data.amount_due).toFixed(2)} ج.م</strong>
                </div>
                <div style="display:flex; justify-content:space-between; border-top:1px dashed #cbd5e1; padding-top:6px; font-size:16px; font-weight:900;">
                    <span>الإجمالي الكلي:</span><span style="color:#059669;" id="endBilliardGrandTotal">${parseFloat(data.grand_total).toFixed(2)} ج.م</span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-top:6px; color:#059669; font-weight:800;">
                    <span>المطلوب بعد الخصم:</span><span id="endBilliardNetTotal">${parseFloat(data.amount_due).toFixed(2)} ج.م</span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-top:4px; color:#475569;">
                    <span>ضريبة + خدمة (${parseFloat(data.tax_pct).toFixed(2)}%):</span><span id="endBilliardTax">${parseFloat(data.tax_amount).toFixed(2)} ج.م</span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-top:4px; font-weight:900; font-size:15px;">
                    <span>المطلوب تحصيله:</span><span style="color:#059669;" id="endBilliardFinal">${(parseFloat(data.amount_due) + parseFloat(data.tax_amount)).toFixed(2)} ج.م</span>
                </div>
            </div>
                <div style="margin-bottom:12px;">
                <label style="font-size:12px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">الخصم من المتبقي:</label>
                <input type="number" id="billiardDiscInput" value="0" min="0" max="${parseFloat(data.amount_due)}" oninput="updateBilliardNet(${data.amount_due}, ${data.cafe_tax_on_due}, ${data.tax_pct})" style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e1; font-weight:bold;">
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

function updateBilliardNet(base, cafeTax, taxPct) {
    const input = document.getElementById('billiardDiscInput');
    const disc = Math.min(base, Math.max(0, parseFloat(input.value) || 0));
    input.value = disc;
    const net = Math.max(0, base - disc);
    const tax = Math.round((Math.max(0, net - (parseFloat(cafeTax) || 0)) * (parseFloat(taxPct) || 0) / 100 + 1e-9) * 100) / 100;
    document.getElementById('endBilliardNetTotal').textContent = `${net.toFixed(2)} ج.م`;
    document.getElementById('endBilliardTax').textContent = `${tax.toFixed(2)} ج.م`;
    document.getElementById('endBilliardFinal').textContent = `${(Math.round((net + tax + 1e-9) * 100) / 100).toFixed(2)} ج.م`;
}

async function submitEndBilliard() {
    const disc = Math.max(0, parseFloat(document.getElementById('billiardDiscInput').value) || 0);
    const method = document.getElementById('billiardPayMethod').value;

    const res = await fetch('ajax.php?action=end_session', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ session_id: activeBilliardSessionId, discount_amount: disc, payment_method: method })
    });
    const data = await res.json();
    if (data.success) {
        openReceiptModal(`receipt.php?id=${data.session_id}`);
        closeModal('endBilliardModal');
        loadTables();
    } else {
        alert(data.error);
    }
}

function openExtendBilliardModal(sessionId, deviceName, currentPreset) {
    document.getElementById('extendBilliardSessionId').value = sessionId;
    document.getElementById('extendBilliardModalTitle').textContent = `إضافة وقت: ${deviceName}`;
    document.getElementById('extendBilliardMinutes').value = 30;
    document.getElementById('extendBilliardModal').style.display = 'flex';
}

function setExtendBilliardMinutes(minutes) {
    document.getElementById('extendBilliardMinutes').value = minutes;
}

async function submitExtendBilliardSession() {
    const sessionId = document.getElementById('extendBilliardSessionId').value;
    const minutes = document.getElementById('extendBilliardMinutes').value;

    const fd = new FormData();
    fd.append('session_id', sessionId);
    fd.append('add_minutes', minutes);

    const res = await fetch('ajax.php?action=extend_session', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
        closeModal('extendBilliardModal');
        expiredBilliardSessions.clear();
        loadTables();
    } else {
        alert(data.error);
    }
}

function closeModal(id) { document.getElementById(id).style.display = 'none'; }

function deleteTable(tableId, tableName) {
    document.getElementById('deleteTableId').value = tableId;
    document.getElementById('deleteTableName').textContent = tableName;
    document.getElementById('deleteTableModal').style.display = 'flex';
}

function confirmDeleteTable() {
    const tableId = document.getElementById('deleteTableId').value;
    window.location.href = 'delete.php?id=' + tableId;
}
function escapeHtml(t) { return t ? t.toString().replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;") : ''; }

// Close modals when clicking outside
window.addEventListener('click', function(e) {
    if (e.target.classList.contains('game-modal-backdrop')) {
        e.target.style.display = 'none';
    }
});
</script>
</div><!-- End container -->

<?php include '../../includes/footer.php'; ?>
