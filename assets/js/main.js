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
