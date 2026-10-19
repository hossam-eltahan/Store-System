/**
 * Customer Picker — حقل اختيار/إضافة عميل من أي مكان
 * initCustomerPicker(textInput, hiddenInput, opts)
 *   opts.balanceEl   : عنصر بضع فيه حالة الرصيد بعد الاختيار (اختياري)
 *   opts.onSelect    : callback(selectedOrNull)
 *   opts.allowEmpty  : إظهار صف "بدون ربط" (الافتراضي true)
 *
 * الضربات:
 * - كتابة أي اسم وعدم الاختيار → السيرفر بيسجل عميل جديد تلقائياً عند الحفظ
 * → اختيار من القائمة/الجديد → hiddenInput ياخد الـid (ربط صريح على الحساب مباشرة)
 */
function initCustomerPicker(textInput, hiddenInput, opts) {
    opts = opts || {};
    let items = null;
    let debounceTimer = null;
    let listEl = null;
    let wrapper = null;
    let current = null;

    // Wrap the input in a relative container so the dropdown anchors to it
    wrapper = textInput.parentNode;
    const cs = getComputedStyle(wrapper);
    if (cs.position === 'static') wrapper.style.position = 'relative';
    textInput.setAttribute('autocomplete', 'off');

    function closeList() {
        if (listEl) { listEl.remove(); listEl = null; }
    }

    function ensureList() {
        if (listEl) return listEl;
        listEl = document.createElement('div');
        listEl.style.cssText = 'position:absolute; top:100%; margin-top:4px; left:0; right:0; background:#fff; border:1px solid #cbd5e1; border-radius:10px; box-shadow:0 12px 25px rgba(0,0,0,.15); z-index:100000; max-height:260px; overflow-y:auto; display:none;';
        wrapper.appendChild(listEl);
        return listEl;
    }

    function rowHTML(icon, titleHtml, sub, attrs) {
        return `<div ${attrs} style="padding:9px 12px; border-bottom:1px solid #f1f5f9; cursor:pointer; display:flex; gap:8px; align-items:center; font-family:inherit;">
            <span style="font-size:15px;">${icon}</span>
            <div style="flex:1;">
                <div style="font-weight:800; font-size:12.5px; color:#0f172a;">${titleHtml}</div>
                ${sub ? `<div style="font-size:10.5px; color:#64748b;">${sub}</div>` : ''}
            </div>
        </div>`;
    }

    function bindRow(el, fn) {
        el.addEventListener('mousedown', function (e) { e.preventDefault(); fn(); });
    }

    function detachedNote() {
        return `<div style="padding:7px 12px; background:#fefce8; color:#a16207; font-size:10.5px; border-bottom:1px solid #fef08a;">
            كتابة اسم جديد بدون اختيار = هيتسجل عميل جديد تلقائياً عند الحفظ
        </div>`;
    }

    function render(query, results) {
        const list = ensureList();
        let html = '';
        html += detachedNote();
        const q = (query || '').trim();

        if (q !== '') {
            html += rowHTML('➕', `عميل جديد: <strong>${escapeHtmlStr(q)}</strong>`, 'تسجيله في العملاء فوراً',
                'data-act="new" approved-cursor style="background:#f0fdf4;"');
        }
        (results || []).forEach(c => {
            const bal = parseFloat(c.balance || 0);
            const balHtml = bal < -0.009
                ? `<span style="color:#dc2626; font-weight:800;">مدين ${Math.abs(bal).toFixed(2)}</span>`
                : '<span style="color:#059669;">سليم</span>';
            html += rowHTML('👤', escapeHtmlStr(c.name), (c.phone || '') + ' — ' + balHtml,
                `data-act="pick" data-id="${c.id}" data-name="${escapeHtmlStr(c.name)}" data-balance="${c.balance}"`);
        });
        if (!results || results.length === 0) {
            html += `<div style="padding:8px 12px; color:#94a3b8; font-size:11px;">لا يوجد عملاء مطابقين</div>`;
        }
        if (opts.allowEmpty !== false && (hiddenInput.value !== '' || q !== '')) {
            html += rowHTML('🚫', 'بدون ربط بحساب', 'يتم البيع عادي بدون تسجيل العميل', 'data-act="clear" style="background:#f8fafc;"');
        }

        list.innerHTML = html;
        list.style.display = 'block';

        list.querySelectorAll('[data-act]').forEach(el => {
            const act = el.dataset.act;
            if (act === 'pick') {
                bindRow(el, () => {
                    selectCustomer({ id: el.dataset.id, name: el.dataset.name, balance: el.dataset.balance });
                    closeList();
                });
            } else if (act === 'new') {
                bindRow(el, () => {
                    createCustomer(q);
                    closeList();
                });
            } else if (act === 'clear') {
                bindRow(el, () => {
                    selectCustomer(null);
                    closeList();
                });
            }
        });
    }

    // The real search network call
    function doSearch(q) {
        const url = (typeof PICKER_AJAX_URL === 'string' && PICKER_AJAX_URL) ? PICKER_AJAX_URL : '../../modules/customers/ajax.php';
        fetch(url.replace(/\/$/, '') + '?action=search&q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'picker' } })
            .then(r => r.json())
            .then(d => {
                if (d && d.success) { render(q, d.customers); }
                else render(q, []);
            })
            .catch(() => render(q, []));
    }

    textInput.addEventListener('input', () => {
        hiddenInput.value = '';
        current = null;
        showBalance(null);
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            const q = textInput.value.trim();
            if (q === '') { render('', []); return; }
            doSearch(q);
        }, 220);
    });

    textInput.addEventListener('focus', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => doSearch(textInput.value.trim()), 180);
    });

    textInput.addEventListener('blur', () => {
        setTimeout(closeList, 180);
    });

    function selectCustomer(c) {
        if (c) {
            hiddenInput.value = c.id;
            textInput.value = c.name;
            showBalance(parseFloat(c.balance || 0), c.name);
        } else {
            hiddenInput.value = '';
            showBalance(null);
        }
        current = c;
        if (typeof opts.onSelect === 'function') opts.onSelect(c);
    }

    function createCustomer(name) {
        const url = (typeof PICKER_AJAX_URL === 'string' && PICKER_AJAX_URL) ? PICKER_AJAX_URL : '../../modules/customers/ajax.php';
        fetch(url.replace(/\/$/, '') + '?action=merge_add', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: name })
        }).then(r => r.json()).then(d => {
            if (d && d.success) {
                selectCustomer(d.customer);
                appAlert('تم تسجيل عميل جديد: ' + d.customer.name, 'success');
            } else {
                appAlert((d && d.error) || 'تعذر تسجيل العميل', 'error');
            }
        }).catch(() => appAlert('خطأ في الاتصال بالسيرفر', 'error'));
    }

    function showBalance(balance, name) {
        if (!opts.balanceEl) return;
        if (balance === null || (name && hiddenInput.value === '')) {
            opts.balanceEl.textContent = '';
            opts.balanceEl.style.display = 'none';
            return;
        }
        opts.balanceEl.style.display = 'inline';
        if (balance < -0.009) {
            opts.balanceEl.textContent = ' ⚠ مدين ' + Math.abs(balance).toFixed(2) + ' ج.م';
            opts.balanceEl.style.color = '#dc2626';
        } else {
            opts.balanceEl.textContent = ' ✓ سليم';
            opts.balanceEl.style.color = '#059669';
        }
    }

    function escapeHtmlStr(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    }

    return {
        set: selectCustomer,
        get: () => current,
        doSearch: (q) => doSearch(typeof q === 'string' ? q : textInput.value.trim())
    };
}
