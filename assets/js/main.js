/**
 * Main JavaScript
 * نظام إدارة محل أجهزة منزلية
 */

// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function () {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.animation = 'slideUp 0.3s ease';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
});

// ---- Pretty dialogs (replace native alert/confirm/prompt, no "localhost" boxes) ----
function appDialogBase() {
    var old = document.getElementById('appDialogOverlay');
    if (old) old.remove();
    var overlay = document.createElement('div');
    overlay.id = 'appDialogOverlay';
    overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.65);backdrop-filter:blur(4px);z-index:100001;display:flex;align-items:center;justify-content:center;padding:16px;';
    var box = document.createElement('div');
    box.style.cssText = 'background:#fff;width:100%;max-width:420px;border-radius:16px;box-shadow:0 20px 30px rgba(0,0,0,0.25);padding:24px 22px;text-align:center;font-family:inherit;';
    overlay.appendChild(box);
    document.body.appendChild(overlay);
    return { overlay: overlay, box: box };
}

function appDialogIcon(box, icon, bg) {
    var ic = document.createElement('div');
    ic.style.cssText = 'width:56px;height:56px;border-radius:50%;background:' + bg + ';display:flex;align-items:center;justify-content:center;font-size:28px;margin:0 auto 12px;';
    ic.textContent = icon;
    box.appendChild(ic);
}

function appDialogText(box, message) {
    var p = document.createElement('div');
    p.style.cssText = 'font-size:14px;font-weight:600;color:#0f172a;line-height:2;white-space:pre-line;margin-bottom:20px;';
    p.textContent = String(message == null ? '' : message);
    box.appendChild(p);
}

function appDialogButtons(box, buttons) {
    var row = document.createElement('div');
    row.style.cssText = 'display:flex;gap:10px;justify-content:center;';
    buttons.forEach(function (b) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = b.label;
        btn.style.cssText = 'border:none;border-radius:10px;padding:10px 26px;font-weight:800;font-size:13.5px;cursor:pointer;font-family:inherit;background:' + b.bg + ';color:' + b.color + ';';
        btn.onclick = b.onClick;
        row.appendChild(btn);
    });
    box.appendChild(row);
}

// Pretty information / error message. type: 'error' | 'success' | 'info'
function appAlert(message, type) {
    type = type || 'error';
    var conf = {
        error: { icon: '❌', bg: '#fee2e2', title: 'حدث خطأ' },
        success: { icon: '✅', bg: '#dcfce7', title: 'تم بنجاح' },
        info: { icon: '🔔', bg: '#dbeafe', title: 'تنبيه' }
    }[type] || { icon: '🔔', bg: '#dbeafe', title: 'تنبيه' };
    return new Promise(function (resolve) {
        var d = appDialogBase();
        appDialogIcon(d.box, conf.icon, conf.bg);
        var t = document.createElement('div');
        t.style.cssText = 'font-weight:800;font-size:15px;color:#0f172a;margin-bottom:6px;';
        t.textContent = conf.title;
        d.box.appendChild(t);
        appDialogText(d.box, message);
        appDialogButtons(d.box, [{
            label: 'حسناً', bg: '#2563eb', color: '#fff',
            onClick: function () { d.overlay.remove(); resolve(true); }
        }]);
    });
}

// Pretty confirmation. Resolves true (confirmed) or false.
function appConfirm(message, opts) {
    opts = opts || {};
    var okLabel = opts.okLabel || 'تأكيد';
    var cancelLabel = opts.cancelLabel || 'تراجع';
    var danger = opts.danger !== false;
    return new Promise(function (resolve) {
        var d = appDialogBase();
        appDialogIcon(d.box, '⚠️', '#fef3c7');
        var t = document.createElement('div');
        t.style.cssText = 'font-weight:800;font-size:15px;color:#0f172a;margin-bottom:6px;';
        t.textContent = 'تأكيد الإجراء';
        d.box.appendChild(t);
        appDialogText(d.box, message);
        function done(val) { d.overlay.remove(); resolve(val); }
        appDialogButtons(d.box, [
            { label: cancelLabel, bg: '#f1f5f9', color: '#334155', onClick: function () { done(false); } },
            { label: okLabel, bg: danger ? '#dc2626' : '#2563eb', color: '#fff', onClick: function () { done(true); } }
        ]);
        d.overlay.addEventListener('click', function (e) { if (e.target === d.overlay) done(false); });
    });
}

// Pretty single-choice list (replaces native prompt for picking an option).
// options: [{value, label}]. Resolves the chosen value or null.
function appPromptSelect(message, options, opts) {
    opts = opts || {};
    return new Promise(function (resolve) {
        var d = appDialogBase();
        appDialogIcon(d.box, '🔀', '#dbeafe');
        var t = document.createElement('div');
        t.style.cssText = 'font-weight:800;font-size:15px;color:#0f172a;margin-bottom:6px;';
        t.textContent = opts.title || 'اختيار';
        d.box.appendChild(t);
        appDialogText(d.box, message);
        var list = document.createElement('div');
        list.style.cssText = 'display:flex;flex-direction:column;gap:8px;margin-bottom:18px;max-height:240px;overflow-y:auto;';
        (options || []).forEach(function (opt) {
            var b = document.createElement('button');
            b.type = 'button';
            b.style.cssText = 'border:1px solid #cbd5e1;background:#f8fafc;border-radius:10px;padding:10px 14px;font-weight:700;font-size:13.5px;color:#0f172a;cursor:pointer;font-family:inherit;';
            b.textContent = opt.label;
            b.onmouseover = function () { b.style.background = '#e0e7ff'; };
            b.onmouseout = function () { b.style.background = '#f8fafc'; };
            b.onclick = function () { d.overlay.remove(); resolve(opt.value); };
            list.appendChild(b);
        });
        d.box.appendChild(list);
        appDialogButtons(d.box, [
            { label: 'إلغاء', bg: '#f1f5f9', color: '#334155', onClick: function () { d.overlay.remove(); resolve(null); } }
        ]);
        d.overlay.addEventListener('click', function (e) { if (e.target === d.overlay) { d.overlay.remove(); resolve(null); } });
    });
}

// Inline-handler bridge for links/buttons: onclick="return confirmAction(this, '...')"
var _appConfirmedEls = (typeof WeakSet !== 'undefined') ? new WeakSet() : null;
var _appConfirmedList = [];
function appConfirmWasArmed(el) {
    if (_appConfirmedEls) return _appConfirmedEls.has(el);
    return _appConfirmedList.indexOf(el) !== -1;
}
function appConfirmArm(el) {
    if (_appConfirmedEls) _appConfirmedEls.add(el);
    else _appConfirmedList.push(el);
}
function appConfirmDisarm(el) {
    if (_appConfirmedEls) _appConfirmedEls.delete(el);
    else { var i = _appConfirmedList.indexOf(el); if (i !== -1) _appConfirmedList.splice(i, 1); }
}
function confirmAction(el, message, opts) {
    if (appConfirmWasArmed(el)) { appConfirmDisarm(el); return true; }
    appConfirm(message, opts).then(function (ok) {
        if (!ok) return;
        appConfirmArm(el);
        if (el && typeof el.click === 'function') el.click();
        else if (el && el.href) window.location.href = el.href;
    });
    return false;
}

// Inline-handler bridge for forms: onsubmit="return confirmSubmit(this, '...')"
function confirmSubmit(form, message, opts) {
    appConfirm(message, opts).then(function (ok) {
        if (ok && form && typeof form.submit === 'function') form.submit();
    });
    return false;
}

// Format number with commas
function formatNumber(num) {
    if (num === undefined || num === null) return "0";
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

/**
 * Dynamic Toast Notification
 * @param {string} message 
 * @param {string} type 'success' or 'error' or 'info'
 */
function showToast(message, type = 'success') {
    // Remove existing toast if any
    const existing = document.getElementById('simple-toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.id = 'simple-toast';
    toast.className = type === 'info' ? 'success' : type; // reusing success styles for info
    if (type === 'info') toast.style.background = '#3b82f6';
    
    const icon = type === 'success' ? '✅' : (type === 'error' ? '❌' : '🔔');
    
    toast.innerHTML = `
        <span>${icon}</span>
        <span class="toast-text">${message}</span>
        <button class="toast-close" onclick="this.parentElement.remove()">×</button>
    `;
    
    document.body.appendChild(toast);
    
    // Auto hide after 10 seconds for timer alerts
    setTimeout(() => {
        if (toast.parentElement) {
            toast.classList.add('hiding');
            setTimeout(() => toast.remove(), 300);
        }
    }, 10000);
}

// Global Timer Checker
let timerCheckInterval = null;
let notifiedAlertKeys = new Set();

function resolveAppUrl(relativePath) {
    const base = (window.APP_BASE_URL && typeof window.APP_BASE_URL === 'string') ? window.APP_BASE_URL : '';
    if (base) return base + relativePath.replace(/^\/+/, '');
    const path = window.location.pathname || '';
    const prefix = path.includes('/modules/') ? '../../' : './';
    return prefix + relativePath.replace(/^\/+/, '');
}

function escapeHtmlNotif(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function notificationLink(link) {
    const rel = String(link || '').replace(/^\/+/, '');
    const base = (window.APP_BASE_URL && typeof window.APP_BASE_URL === 'string') ? window.APP_BASE_URL : '';
    return base + rel;
}

function updateNotificationBadge(count) {
    const bell = document.getElementById('notificationBell');
    if (!bell) return;
    let badge = bell.querySelector('.notification-badge');
    const safeCount = (typeof count === 'number' && !isNaN(count)) ? count : 0;
    if (safeCount > 0) {
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'notification-badge';
            bell.appendChild(badge);
        }
        badge.textContent = safeCount > 99 ? '99+' : safeCount;
    } else if (badge) {
        badge.remove();
    }

    const countSpan = document.querySelector('.notification-count');
    if (countSpan) countSpan.textContent = safeCount;
}

function updateNotificationDropdown(notifications) {
    const body = document.querySelector('.notification-dropdown-body');
    if (!body) return;

    const items = Array.isArray(notifications) ? notifications : [];
    if (items.length === 0) {
        body.innerHTML = '<div class="notification-empty">✔️ لا توجد إشعارات</div>';
        return;
    }

    body.innerHTML = items.map(function (notif) {
        return '<a href="' + escapeHtmlNotif(notificationLink(notif.link)) + '" class="notification-dropdown-item ' + escapeHtmlNotif(notif.type || 'info') + '">' +
            '<span class="notification-dropdown-icon">' + escapeHtmlNotif(notif.icon || '🔔') + '</span>' +
            '<div class="notification-dropdown-content">' +
                '<div class="notification-dropdown-title">' + escapeHtmlNotif(notif.title || '') + '</div>' +
                '<div class="notification-dropdown-message">' + escapeHtmlNotif(notif.message || '') + '</div>' +
            '</div>' +
        '</a>';
    }).join('');
}

async function checkTimersNow() {
    const ajaxUrl = resolveAppUrl('modules/notifications/ajax.php?action=check_timers');
    try {
        const res = await fetch(ajaxUrl, { cache: 'no-store' });
        const data = await res.json();
        if (!data || !data.success) return;

        updateNotificationBadge(data.notification_count || 0);
        if (Array.isArray(data.notifications)) {
            updateNotificationDropdown(data.notifications);
        }

        const alerts = Array.isArray(data.new_alerts) ? data.new_alerts : (Array.isArray(data.finished) ? data.finished : []);
        alerts.forEach(sess => {
            const key = `${sess.type || 'session'}_${sess.id || sess.name}`;
            if (notifiedAlertKeys.has(key)) return;
            notifiedAlertKeys.add(key);
            const message = sess.message || (sess.name ? `انتهى وقت ${sess.name}` : 'انتهى وقت جلسة');
            showToast(message, 'info');
            playNotificationSound();
            tryNotifyBrowser(message);
        });

        // If we are on the gaming or billiards page, refresh to show updated status
        if (window.location.pathname.includes('modules/gaming/index.php') ||
            window.location.pathname.includes('modules/billiards/index.php')) {
            if (typeof loadDevices === 'function') loadDevices();
            if (typeof loadTables === 'function') loadTables();
        }
    } catch (err) {
        try { console.warn('Global timer check failed:', err, ajaxUrl); } catch (e) {}
    }
}

function tryNotifyBrowser(message) {
    if (typeof Notification === 'undefined') return;
    if (Notification.permission === 'granted') {
        new Notification('كافيه عروسة النيل', { body: message, icon: '🔔' });
    }
}

function startTimerNotifications() {
    // Immediate check
    checkTimersNow();
    // Poll every 5 seconds so alerts appear quickly regardless of page
    timerCheckInterval = setInterval(checkTimersNow, 5000);
}

// Request browser notification permission on user interaction
document.addEventListener('click', function() {
    if (typeof Notification !== 'undefined' && Notification.permission === 'default') {
        Notification.requestPermission();
    }
}, { once: true });

// Initialize global features
function initTimerNotificationsOnce() {
    if (window.__timerNotificationsStarted) return;
    window.__timerNotificationsStarted = true;
    startTimerNotifications();
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTimerNotificationsOnce);
} else {
    initTimerNotificationsOnce();
}
window.addEventListener('pageshow', function () {
    // Re-check when returning to a cached page.
    checkTimersNow();
});

// Calculate totals in invoice
function calculateInvoiceTotal() {
    let subtotal = 0;
    document.querySelectorAll('.invoice-item').forEach(item => {
        const total = parseFloat(item.querySelector('.item-total').textContent || 0);
        subtotal += total;
    });

    const discount = parseFloat(document.getElementById('discount')?.value || 0);
    const total = subtotal - discount;

    if (document.getElementById('subtotal')) {
        document.getElementById('subtotal').textContent = formatNumber(subtotal.toFixed(2));
    }
    if (document.getElementById('total')) {
        document.getElementById('total').textContent = formatNumber(total.toFixed(2));
    }

    calculateRemaining();
}

// Calculate remaining amount
function calculateRemaining() {
    const total = parseFloat(document.getElementById('total')?.textContent.replace(/,/g, '') || 0);
    const paid = parseFloat(document.getElementById('paid')?.value || 0);
    const remaining = total - paid;

    if (document.getElementById('remaining')) {
        document.getElementById('remaining').textContent = formatNumber(remaining.toFixed(2));
    }
}

// Play notification sound
function playNotificationSound() {
    const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuBzvLZiTYIHmjE8+Oqa1UAAAClN5Hc8tN8Jw');
    audio.play().catch(() => { });
}

// Print function
function printInvoice() {
    window.print();
}

// Search with autocomplete
function searchProducts(input, resultsContainer) {
    const query = input.value.trim();

    if (query.length < 2) {
        resultsContainer.innerHTML = '';
        resultsContainer.style.display = 'none';
        return;
    }

    const basePath = window.location.pathname.substring(0, window.location.pathname.indexOf('/', 1) + 1) || '/';
    fetch(`${basePath}modules/products/ajax.php?action=search&q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.products.length > 0) {
                displaySearchResults(data.products, resultsContainer);
            } else {
                resultsContainer.innerHTML = '<div class="p-2">لا توجد نتائج</div>';
                resultsContainer.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Search error:', error);
        });
}

function displaySearchResults(products, container) {
    container.innerHTML = products.map(product => `
        <div class="search-result-item" onclick="selectProduct(${product.id})">
            <strong>${product.name}</strong> (${product.code})
            <br>
            <small>السعر: ${product.price} - المخزون: ${product.stock_quantity}</small>
        </div>
    `).join('');
    container.style.display = 'block';
}

// Live search for products table (filters without reload)
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('productSearch');
    const tableBody = document.getElementById('productsTableBody');

    if (searchInput && tableBody) {
        searchInput.addEventListener('input', function () {
            const searchTerm = this.value.toLowerCase().trim();
            const rows = tableBody.querySelectorAll('tr');

            rows.forEach(row => {
                // Skip "no results" row
                if (row.querySelector('td[colspan]')) {
                    row.style.display = searchTerm ? 'none' : '';
                    return;
                }

                const code = row.cells[0]?.textContent?.toLowerCase() || '';
                const name = row.cells[2]?.textContent?.toLowerCase() || '';

                if (code.includes(searchTerm) || name.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });

            // Update visible count
            const visibleRows = tableBody.querySelectorAll('tr:not([style*="display: none"]):not(:has(td[colspan]))');
            const countElement = document.querySelector('.mt-2 strong');
            if (countElement && countElement.nextSibling) {
                countElement.nextSibling.textContent = ' ' + visibleRows.length;
            }
        });
    }
});
// Receipt Modal (avoids leftover browser popup windows)
function openReceiptModal(url) {
    closeReceiptModal();
    var overlay = document.createElement('div');
    overlay.id = 'receiptModalOverlay';
    overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.65);backdrop-filter:blur(4px);z-index:100000;display:flex;align-items:center;justify-content:center;padding:16px;';

    var box = document.createElement('div');
    box.style.cssText = 'background:#fff;width:100%;max-width:420px;height:92vh;border-radius:16px;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 20px 30px rgba(0,0,0,0.25);';

    var header = document.createElement('div');
    header.style.cssText = 'padding:10px 14px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;';

    var title = document.createElement('div');
    title.style.cssText = 'font-weight:800;font-size:13.5px;color:#0f172a;';
    title.textContent = 'Receipt / Bill';

    var actions = document.createElement('div');
    actions.style.cssText = 'display:flex;gap:8px;';

    var printBtn = document.createElement('button');
    printBtn.textContent = 'Print';
    printBtn.style.cssText = 'background:#ea580c;color:#fff;border:none;border-radius:8px;padding:6px 12px;font-weight:700;font-size:12px;cursor:pointer;';
    printBtn.onclick = function() {
        try { iframe.contentWindow.focus(); iframe.contentWindow.print(); } catch (e) {}
    };

    var closeBtn = document.createElement('button');
    closeBtn.textContent = 'x';
    closeBtn.style.cssText = 'background:#f1f5f9;color:#334155;border:1px solid #cbd5e1;border-radius:8px;padding:6px 12px;font-weight:700;font-size:14px;cursor:pointer;';
    closeBtn.onclick = closeReceiptModal;

    var iframe = document.createElement('iframe');
    iframe.src = url;
    iframe.style.cssText = 'flex:1;width:100%;border:0;background:#fff;';

    actions.appendChild(printBtn);
    actions.appendChild(closeBtn);
    header.appendChild(title);
    header.appendChild(actions);
    box.appendChild(header);
    box.appendChild(iframe);
    overlay.appendChild(box);
    document.body.appendChild(overlay);

    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) closeReceiptModal();
    });
    window.__receiptModalOverlay = overlay;
}

function closeReceiptModal() {
    var overlay = window.__receiptModalOverlay || document.getElementById('receiptModalOverlay');
    if (overlay) {
        overlay.remove();
    }
    window.__receiptModalOverlay = null;
}
window.openReceiptModal = openReceiptModal;
window.closeReceiptModal = closeReceiptModal;