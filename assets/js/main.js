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

// Confirm delete actions
function confirmDelete(message = 'هل أنت متأكد من الحذف؟') {
    return confirm(message);
}

// Format number with commas
function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

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
            const searchTerm = normSearchText(this.value.trim());
            const rows = tableBody.querySelectorAll('tr');

            rows.forEach(row => {
                // Skip "no results" row
                if (row.querySelector('td[colspan]')) {
                    row.style.display = searchTerm ? 'none' : '';
                    return;
                }

                const code = normSearchText(row.cells[0]?.textContent || '');
                const name = normSearchText(row.cells[2]?.textContent || '');

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

/* ============================================================
 * UI Utilities (ported from cafe-system, framework-free)
 * ============================================================ */

// ---- Pretty dialogs (replace native alert/confirm/prompt) ----
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

// Pretty single-choice list. options: [{value, label}]
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

// Inline-handler bridge: onclick="return confirmAction(this, '...')"
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

// Inline-handler bridge: onsubmit="return confirmSubmit(this, '...')"
function confirmSubmit(form, message, opts) {
    appConfirm(message, opts).then(function (ok) {
        if (ok && form && form instanceof HTMLFormElement) HTMLFormElement.prototype.submit.call(form);
    });
    return false;
}

// ---- Toast ----
function showToast(message, type) {
    type = type || 'success';
    const existing = document.getElementById('simple-toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.id = 'simple-toast';
    toast.className = type === 'info' ? 'success' : type;
    if (type === 'info') toast.style.background = '#3b82f6';

    const icon = type === 'success' ? '✅' : (type === 'error' ? '❌' : '🔔');

    toast.innerHTML = '<span>' + icon + '</span>' +
        '<span class="toast-text"></span>' +
        '<button class="toast-close" onclick="this.parentElement.remove()">×</button>';
    toast.querySelector('.toast-text').textContent = message;

    document.body.appendChild(toast);
    setTimeout(() => {
        if (toast.parentElement) {
            toast.classList.add('hiding');
            setTimeout(() => toast.remove(), 300);
        }
    }, 10000);
}

// Resolve a relative app path against BASE_URL when available.
function resolveAppUrl(relativePath) {
    const base = (window.APP_BASE_URL && typeof window.APP_BASE_URL === 'string') ? window.APP_BASE_URL : '';
    if (base) return base + String(relativePath).replace(/^\/+/, '');
    const path = window.location.pathname || '';
    const prefix = path.includes('/modules/') ? '../../' : './';
    return prefix + String(relativePath).replace(/^\/+/, '');
}

// ---- Arabic-insensitive text normalization (client side) ----
function normSearchText(s) {
    s = String(s || '');
    s = s.replace(/[أإآٱ]/g, 'ا').replace(/ة/g, 'ه').replace(/[ىیئ]/g, 'ي').replace(/ؤ/g, 'و');
    s = s.replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); });
    s = s.replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); });
    s = s.replace(/[\u064B-\u065F\u0670]/g, '');
    return s;
}
window.normSearchText = normSearchText;

// ---- Receipt modal ----
function openReceiptModal(url, onClose) {
    closeReceiptModal();
    window.__receiptModalOnClose = typeof onClose === 'function' ? onClose : null;
    var overlay = document.createElement('div');
    overlay.id = 'receiptModalOverlay';
    overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.65);backdrop-filter:blur(4px);z-index:100000;display:flex;align-items:center;justify-content:center;padding:16px;';

    var box = document.createElement('div');
    box.style.cssText = 'background:#fff;width:100%;max-width:420px;height:92vh;border-radius:16px;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 20px 30px rgba(0,0,0,0.25);';

    var header = document.createElement('div');
    header.style.cssText = 'padding:10px 14px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;gap:8px;';

    var title = document.createElement('div');
    title.style.cssText = 'font-weight:800;font-size:13.5px;color:#0f172a;';
    title.textContent = '🧾 الإيصال';

    var actions = document.createElement('div');
    actions.style.cssText = 'display:flex;gap:8px;';

    var iframe = document.createElement('iframe');
    iframe.src = url;
    iframe.style.cssText = 'flex:1;width:100%;border:0;background:#fff;';

    var printBtn = document.createElement('button');
    printBtn.textContent = '🖨️ طباعة';
    printBtn.style.cssText = 'background:#ea580c;color:#fff;border:none;border-radius:8px;padding:7px 14px;font-weight:800;font-size:12px;cursor:pointer;font-family:inherit;';
    printBtn.onclick = function () {
        try { iframe.contentWindow.focus(); iframe.contentWindow.print(); } catch (e) {}
    };

    var closeBtn = document.createElement('button');
    closeBtn.textContent = 'إغلاق';
    closeBtn.style.cssText = 'background:#f1f5f9;color:#334155;border:1px solid #cbd5e1;border-radius:8px;padding:7px 14px;font-weight:800;font-size:12px;cursor:pointer;font-family:inherit;';
    closeBtn.onclick = closeReceiptModal;

    actions.appendChild(printBtn);
    actions.appendChild(closeBtn);
    header.appendChild(title);
    header.appendChild(actions);
    box.appendChild(header);
    box.appendChild(iframe);
    overlay.appendChild(box);
    document.body.appendChild(overlay);

    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) closeReceiptModal();
    });
    window.__receiptModalOverlay = overlay;
}

function closeReceiptModal() {
    var overlay = window.__receiptModalOverlay || document.getElementById('receiptModalOverlay');
    if (overlay) overlay.remove();
    window.__receiptModalOverlay = null;
    var cb = window.__receiptModalOnClose;
    window.__receiptModalOnClose = null;
    if (typeof cb === 'function') { try { cb(); } catch (e) {} }
}
window.openReceiptModal = openReceiptModal;
window.closeReceiptModal = closeReceiptModal;

// ---- Arabic-Indic digit input support ----
// Money fields use type="text" inputmode="decimal"; these helpers convert
// ٠-٩ / ۰-۹ to 0-9 and accept . , ٫ as decimal separator.
function normDigitInput(el) {
    if (!el) return 0;
    var v = String(el.value);
    var out = '', hasDot = false;
    for (var i = 0; i < v.length; i++) {
        var ch = v[i];
        var d = '٠١٢٣٤٥٦٧٨٩'.indexOf(ch);
        if (d < 0) d = '۰۱۲۳۴۵۶۷۸۹'.indexOf(ch);
        if (d >= 0) { out += d; }
        else if (ch === '.' || ch === ',' || ch === '\u066b') {
            if (!hasDot) { out += '.'; hasDot = true; }
        }
        else if (ch === '\u066c') { /* thousands separator - skip */ }
        else if (ch >= '0' && ch <= '9') { out += ch; }
    }
    if (out !== v) el.value = out;
    return parseFloat(out) || 0;
}
window.normDigitInput = normDigitInput;

// Auto-convert Arabic-Indic digits as the user types in any money field.
document.addEventListener('input', function (e) {
    var el = e.target;
    if (!el || !el.value) return;
    if (!/[٠-٩۰-۹]/.test(el.value)) return;
    var isMoney = el.matches('input[type="number"], input[inputmode="decimal"], input[inputmode="numeric"], .money-input');
    if (isMoney) normDigitInput(el);
}, true);
