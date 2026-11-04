/**
 * POS - نقطة البيع
 * واجهة الكاشير (بنفس شكل كافيه) مع طبقة بيانات فواتير البيع.
 */

(function () {
    'use strict';

    var CFG = window.POS_CONFIG || {};

    // ---------------- State ----------------
    var products = [];
    var cart = [];
    var currentCategory = '';
    var currentSearch = '';
    var checkoutKey = null;
    var lastInvoice = null;
    var searchTimer = null;
    var customerMap = {};

    // ---------------- Helpers ----------------
    function $(id) { return document.getElementById(id); }

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function num(v) {
        var n = parseFloat(v);
        return isNaN(n) ? 0 : n;
    }

    function money(v) {
        return num(v).toFixed(2);
    }

    function currency() {
        return CFG.currency || 'ج.م';
    }

    function fmt(v) {
        return money(v) + ' ' + currency();
    }

    function warehouseId() {
        var el = $('posWarehouseSelect');
        return el ? (parseInt(el.value, 10) || 0) : (CFG.warehouseId || 0);
    }

    function getCheckoutKey() {
        if (!checkoutKey) {
            checkoutKey = 'ck-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10);
        }
        return checkoutKey;
    }

    function resetCheckoutKey() {
        checkoutKey = null;
    }

    function cartQtyOf(productId) {
        var pid = parseInt(productId, 10) || 0;
        return cart.reduce(function (acc, it) {
            return acc + ((parseInt(it.product_id, 10) === pid) ? (parseInt(it.quantity, 10) || 0) : 0);
        }, 0);
    }

    function productById(id) {
        var pid = parseInt(id, 10) || 0;
        for (var i = 0; i < products.length; i++) {
            if (parseInt(products[i].id, 10) === pid) return products[i];
        }
        return null;
    }

    function cartSubtotal() {
        return cart.reduce(function (acc, it) {
            return acc + (num(it.unit_price) * num(it.quantity));
        }, 0);
    }

    function ajax(action, params) {
        var url = CFG.ajaxUrl + '?action=' + encodeURIComponent(action);
        if (params) {
            Object.keys(params).forEach(function (k) {
                if (params[k] === null || params[k] === undefined || params[k] === '') return;
                url += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
            });
        }
        return fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); });
    }

    function ajaxPost(action, payload) {
        var url = CFG.ajaxUrl + (CFG.ajaxUrl.indexOf('?') === -1 ? '?' : '&') + 'action=' + encodeURIComponent(action);
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload || {})
        }).then(function (r) { return r.json(); });
    }

    // ---------------- Products ----------------
    function loadProducts() {
        var grid = $('productsGrid');
        if (!grid) return;

        if (!CFG.ready) {
            grid.innerHTML = '<div class="pos-empty-hint" style="grid-column:1/-1;">' +
                '<div class="big">⚠️</div>قاعدة البيانات تحتاج تحديث وحدة الكاشير</div>';
            return;
        }

        ajax('get_products', {
            warehouse_id: warehouseId(),
            category_id: currentCategory,
            search: currentSearch
        }).then(function (data) {
            if (!data || !data.success) {
                grid.innerHTML = '<div class="pos-empty-hint" style="grid-column:1/-1;">' +
                    '<div class="big">⚠️</div>' + escapeHtml((data && (data.error || data.message)) || 'تعذر تحميل الأصناف') + '</div>';
                return;
            }
            products = data.products || [];
            renderProductsGrid();
        }).catch(function () {
            grid.innerHTML = '<div class="pos-empty-hint" style="grid-column:1/-1;">' +
                '<div class="big">⚠️</div>تعذر الاتصال بالسيرفر</div>';
        });
    }

    function stockBadge(prod) {
        var base = parseInt(prod.stock, 10) || 0;
        var preview = base - cartQtyOf(prod.id);
        var cls = preview <= 0 ? 'out' : (preview <= 2 ? 'low' : 'ok');
        var label = preview <= 0
            ? (CFG.allowNegativeStock ? '📦 ' + preview : '🔴 نفذ')
            : '📦 ' + preview;
        return '<span class="stock-badge ' + cls + '">' + label + '</span>';
    }

    function renderProductsGrid() {
        var grid = $('productsGrid');
        if (!grid) return;

        if (!products.length) {
            grid.innerHTML = '<div class="pos-empty-hint" style="grid-column:1/-1;">' +
                '<div class="big">📦</div>لا توجد أصناف مطابقة</div>';
            return;
        }

        grid.innerHTML = products.map(function (prod) {
            var out = !CFG.allowNegativeStock && (parseInt(prod.stock, 10) || 0) - cartQtyOf(prod.id) <= 0;
            return '<div class="pos-product-card' + (out ? ' out-of-stock' : '') + '" data-product-id="' + prod.id + '">' +
                '<div class="product-name">' + escapeHtml(prod.name) + ' ' + stockBadge(prod) + '</div>' +
                '<div class="product-footer">' +
                    '<span class="product-price">' + fmt(prod.price) + '</span>' +
                    '<span class="variant-indicator">' + escapeHtml(prod.unit || '') + '</span>' +
                '</div>' +
            '</div>';
        }).join('');

        Array.prototype.forEach.call(grid.querySelectorAll('.pos-product-card'), function (card) {
            card.addEventListener('click', function () {
                var prod = productById(card.getAttribute('data-product-id'));
                if (prod) addToCart(prod);
            });
        });
    }

    // ---------------- Cart ----------------
    function addToCart(prod) {
        var available = parseInt(prod.stock, 10) || 0;
        var existing = null;
        for (var i = 0; i < cart.length; i++) {
            if (parseInt(cart[i].product_id, 10) === parseInt(prod.id, 10)) { existing = cart[i]; break; }
        }

        if (existing) {
            if (!CFG.allowNegativeStock && existing.quantity + 1 > available) {
                showToast('الكمية المتاحة من "' + prod.name + '" هي ' + available, 'error');
                return;
            }
            existing.quantity += 1;
        } else {
            if (!CFG.allowNegativeStock && available <= 0) {
                showToast('"' + prod.name + '" غير متوفر في المخزن', 'error');
                return;
            }
            cart.push({
                product_id: parseInt(prod.id, 10),
                product_name: prod.name,
                product_code: prod.code,
                unit: prod.unit || '',
                unit_price: num(prod.price),
                quantity: 1,
                stock: available
            });
        }

        renderCart();
        renderProductsGrid();
    }

    function updateQty(index, delta) {
        var item = cart[index];
        if (!item) return;
        var next = item.quantity + delta;
        if (next <= 0) {
            cart.splice(index, 1);
        } else if (!CFG.allowNegativeStock && delta > 0 && next > item.stock) {
            showToast('الكمية المتاحة ' + item.stock + ' فقط', 'error');
            return;
        } else {
            item.quantity = next;
        }
        renderCart();
        renderProductsGrid();
    }

    function removeItem(index) {
        cart.splice(index, 1);
        renderCart();
        renderProductsGrid();
    }

    function changePrice(index, value) {
        var item = cart[index];
        if (!item) return;
        var price = num(value);
        if (price < 0) price = 0;
        item.unit_price = price;
        renderCart();
    }

    function renderCart() {
        var container = $('cartItemsList');
        var badge = $('cartCountBadge');
        var checkoutBtn = $('checkoutBtn');
        if (!container) return;

        var count = cart.reduce(function (acc, it) { return acc + (parseInt(it.quantity, 10) || 0); }, 0);
        if (badge) badge.textContent = count;

        if (!cart.length) {
            container.innerHTML =
                '<div class="cart-empty-state">' +
                    '<div style="font-size:38px;margin-bottom:8px;opacity:.6;">🛒</div>' +
                    '<div style="font-weight:700;color:#475569;">السلة فارغة</div>' +
                    '<div style="font-size:11px;margin-top:4px;">اضغط على أي صنف لإضافته</div>' +
                '</div>';
            if ($('grandTotalDisplay')) $('grandTotalDisplay').textContent = '0.00 ' + currency();
            if (checkoutBtn) checkoutBtn.disabled = true;
            return;
        }

        if (checkoutBtn) checkoutBtn.disabled = !CFG.canSell;

        container.innerHTML = cart.map(function (item, index) {
            var lineTotal = num(item.unit_price) * num(item.quantity);
            var priceField = CFG.canDiscount
                ? '<input type="text" inputmode="decimal" class="cart-item-price-edit" value="' + money(item.unit_price) + '" data-index="' + index + '" title="تعديل سعر الوحدة">'
                : '<span style="font-size:11.5px;color:#64748b;">' + fmt(item.unit_price) + '</span>';

            return '<div class="cart-item-card">' +
                '<div class="cart-item-row-top">' +
                    '<div>' +
                        '<span class="cart-item-title">' + escapeHtml(item.product_name) + '</span>' +
                        (item.unit ? '<span class="cart-item-variant">(' + escapeHtml(item.unit) + ')</span>' : '') +
                    '</div>' +
                    '<button type="button" class="cart-remove-btn" data-index="' + index + '" style="background:none;border:none;color:#ef4444;font-size:14px;cursor:pointer;" title="حذف">&times;</button>' +
                '</div>' +
                '<div class="cart-item-row-bottom">' +
                    '<div class="qty-control">' +
                        '<button type="button" class="qty-btn" data-index="' + index + '" data-delta="-1">-</button>' +
                        '<span class="qty-number">' + item.quantity + '</span>' +
                        '<button type="button" class="qty-btn" data-index="' + index + '" data-delta="1">+</button>' +
                    '</div>' +
                    '<div style="display:flex;align-items:center;gap:8px;">' +
                        priceField +
                        '<div class="cart-item-total">' + fmt(lineTotal) + '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';
        }).join('');

        Array.prototype.forEach.call(container.querySelectorAll('.qty-btn'), function (btn) {
            btn.addEventListener('click', function () {
                updateQty(parseInt(btn.getAttribute('data-index'), 10), parseInt(btn.getAttribute('data-delta'), 10));
            });
        });
        Array.prototype.forEach.call(container.querySelectorAll('.cart-remove-btn'), function (btn) {
            btn.addEventListener('click', function () {
                removeItem(parseInt(btn.getAttribute('data-index'), 10));
            });
        });
        Array.prototype.forEach.call(container.querySelectorAll('.cart-item-price-edit'), function (input) {
            input.addEventListener('change', function () {
                changePrice(parseInt(input.getAttribute('data-index'), 10), input.value);
            });
        });

        if ($('grandTotalDisplay')) $('grandTotalDisplay').textContent = fmt(cartSubtotal());
    }

    // ---------------- Customer ----------------
    function validateCustomerFields(showMessage) {
        var nameEl = $('customerNameInput');
        var phoneEl = $('customerPhoneInput');
        var msgEl = $('customerValidationMsg');
        if (!nameEl || !phoneEl) return true;

        var name = (nameEl.value || '').trim();
        var phone = (phoneEl.value || '').trim();
        var error = '';
        var invalidEl = null;

        if (name && !phone) {
            error = 'اكتب رقم موبايل العميل عشان يتسجل في العملاء';
            invalidEl = phoneEl;
        } else if (!name && phone) {
            error = 'اكتب اسم العميل أولاً قبل رقم الموبايل';
            invalidEl = nameEl;
        }

        nameEl.style.borderColor = '#cbd5e1';
        phoneEl.style.borderColor = '#cbd5e1';
        if (invalidEl && showMessage) {
            invalidEl.style.borderColor = '#dc2626';
        }
        if (msgEl) {
            msgEl.textContent = error;
            msgEl.style.display = (error && showMessage) ? 'block' : 'none';
        }
        return !error;
    }

    function setupCustomerSearch() {
        var input = $('customerNameInput');
        var phone = $('customerPhoneInput');
        var list = $('posCustomersList');
        var hidden = $('customerIdInput');

        if (!input || !list) return;

        function lookup() {
            var q = (input.value || '').trim();
            if (q.length < 1) { list.innerHTML = ''; return; }
            fetch(CFG.customersUrl + '?action=search&q=' + encodeURIComponent(q), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || !data.success) return;
                    customerMap = {};
                    list.innerHTML = (data.customers || []).map(function (c) {
                        var key = (c.name || '') + '|' + (c.phone || '');
                        customerMap[key] = c;
                        return '<option value="' + escapeHtml(c.name || '') + '" label="' +
                            escapeHtml(c.phone || '') + '"></option>';
                    }).join('');
                })
                .catch(function () {});
        }

        input.addEventListener('input', function () {
            if (hidden) hidden.value = '';
            validateCustomerFields(false);
            clearTimeout(searchTimer);
            searchTimer = setTimeout(lookup, 250);
        });

        input.addEventListener('blur', function () {
            validateCustomerFields(true);
        });

        input.addEventListener('change', function () {
            var key = (input.value || '').trim() + '|' + (phone ? phone.value.trim() : '');
            var found = customerMap[key];
            if (!found) {
                Object.keys(customerMap).forEach(function (k) {
                    var c = customerMap[k];
                    if ((c.name || '') === input.value.trim() && !found) found = c;
                });
            }
            if (found) {
                if (hidden) hidden.value = found.id;
                if (phone) phone.value = found.phone || '';
                showCustomerBalance(found.balance);
            } else {
                if (hidden) hidden.value = '';
                showCustomerBalance(null);
            }
            validateCustomerFields(false);
        });

        if (phone) {
            phone.addEventListener('input', function () {
                validateCustomerFields(false);
            });

            phone.addEventListener('blur', function () {
                validateCustomerFields(true);
            });

            phone.addEventListener('change', function () {
                if (!phone.value.trim()) {
                    validateCustomerFields(false);
                    return;
                }
                fetch(CFG.customersUrl + '?action=search&q=' + encodeURIComponent(phone.value.trim()), { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data || !data.success) return;
                        var match = (data.customers || []).filter(function (c) {
                            return String(c.phone || '') === phone.value.trim();
                        })[0];
                        if (match) {
                            if (input) input.value = match.name || '';
                            if (hidden) hidden.value = match.id;
                            showCustomerBalance(match.balance);
                        }
                        validateCustomerFields(false);
                    })
                    .catch(function () {});
            });
        }
    }

    function showCustomerBalance(balance) {
        var el = $('customerBalanceBadge');
        if (!el) return;
        if (balance === null || balance === undefined) {
            el.style.display = 'none';
            el.textContent = '';
            return;
        }
        var b = num(balance);
        el.style.display = 'inline-block';
        el.textContent = b < 0 ? ('عليه ' + fmt(Math.abs(b))) : (b > 0 ? ('له ' + fmt(b)) : 'حساب سليم');
        el.style.color = b < 0 ? '#dc2626' : '#059669';
    }

    // ---------------- Checkout modal ----------------
    function openCheckoutModal() {
        if (!cart.length) return;
        if (!validateCustomerFields(true)) return;
        if (!CFG.canSell) {
            appAlert('لا تملك صلاحية البيع من الكاشير', 'error');
            return;
        }

        var subtotal = cartSubtotal();
        var body = $('checkoutModalBody');
        if (!body) return;

        body.innerHTML =
            '<div style="background:#f8fafc;border-radius:10px;padding:12px;margin-bottom:14px;font-size:12.5px;">' +
                '<div style="display:flex;justify-content:space-between;margin-bottom:6px;">' +
                    '<span>عدد الأصناف:</span><strong id="coItemCount"></strong>' +
                '</div>' +
                '<div style="display:flex;justify-content:space-between;margin-bottom:6px;">' +
                    '<span>إجمالي البيع:</span><strong style="color:#ea580c;">' + fmt(subtotal) + '</strong>' +
                '</div>' +
                '<div style="display:flex;justify-content:space-between;margin-bottom:6px;">' +
                    '<span>الخصم:</span><strong id="coDiscountDisplay">0.00 ' + currency() + '</strong>' +
                '</div>' +
                '<div style="display:flex;justify-content:space-between;margin-top:6px;border-top:1px dashed #cbd5e1;padding-top:6px;font-weight:900;font-size:16px;">' +
                    '<span>المطلوب:</span>' +
                    '<span style="color:#ea580c;" id="coFinalTotalDisplay">' + fmt(subtotal) + '</span>' +
                '</div>' +
            '</div>' +

            '<div class="pos-discount-row" style="border:1px solid #e2e8f0;border-radius:10px;margin-bottom:12px;">' +
                '<label style="font-size:12px;font-weight:700;color:#475569;">قيمة الخصم:</label>' +
                '<input type="text" inputmode="decimal" id="coDiscountInput" value="0"' + (CFG.canDiscount ? '' : ' disabled') + '>' +
            '</div>' +

            '<div style="margin-bottom:12px;">' +
                '<label style="font-size:12px;font-weight:700;color:#475569;display:block;margin-bottom:6px;">طريقة الدفع:</label>' +
                '<select id="coPaymentMethod" style="width:100%;padding:8px;border-radius:8px;border:1px solid #cbd5e1;font-size:13px;font-family:inherit;">' +
                    '<option value="كاش">💵 نقداً (كاش)</option>' +
                    '<option value="فيزا">💳 فيزا / بطاقة</option>' +
                    '<option value="انستاباي">⚡ إنستاباي</option>' +
                    '<option value="فودافون كاش">📱 فودافون كاش</option>' +
                '</select>' +
            '</div>' +

            '<div style="margin-bottom:14px;">' +
                '<label style="font-size:12px;font-weight:700;color:#475569;display:block;margin-bottom:4px;">المبلغ المدفوع:</label>' +
                '<input type="text" inputmode="decimal" id="coPaidInput" autocomplete="off" ' +
                    'style="width:100%;padding:9px 12px;border-radius:8px;border:1.5px solid #cbd5e1;font-size:15px;font-weight:800;text-align:center;">' +
                '<div id="coPaidNote" style="font-size:11.5px;font-weight:700;color:#92400e;background:#fefce8;border:1px solid #fde047;border-radius:8px;padding:7px 10px;margin-top:6px;display:none;"></div>' +
            '</div>' +

            '<button type="button" id="coSubmitBtn" style="width:100%;background:linear-gradient(135deg,#ea580c,#c2410c);color:#fff;border:none;border-radius:10px;padding:12px;font-size:15px;font-weight:800;cursor:pointer;">' +
                '🏁 تحصيل الفاتورة وطباعة الإيصال' +
            '</button>';

        $('coItemCount').textContent = cart.reduce(function (a, it) { return a + num(it.quantity); }, 0);

        var discountInput = $('coDiscountInput');
        var paidInput = $('coPaidInput');
        var methodSelect = $('coPaymentMethod');

        function updateTotals() {
            if (discountInput && typeof normDigitInput === 'function') normDigitInput(discountInput);
            var disc = Math.min(subtotal, Math.max(0, num(discountInput ? discountInput.value : 0)));
            if (discountInput) discountInput.value = disc ? money(disc) : '0';
            var net = Math.max(0, subtotal - disc);
            $('coDiscountDisplay').textContent = fmt(disc);
            $('coFinalTotalDisplay').textContent = fmt(net);
            if (paidInput && !paidInput.dataset.touched) {
                paidInput.value = money(net);
            }
            updatePaidNote();
        }

        function currentNet() {
            var disc = Math.min(subtotal, Math.max(0, num(discountInput ? discountInput.value : 0)));
            return Math.max(0, subtotal - disc);
        }

        function updatePaidNote() {
            var net = currentNet();
            var paid = paidInput && paidInput.value !== '' ? num(paidInput.value) : net;
            var diff = Math.round((paid - net) * 100) / 100;
            var note = $('coPaidNote');
            if (!note) return;

            if (Math.abs(diff) <= 0.009) {
                note.style.display = 'none';
                return;
            }

            note.style.display = 'block';
            if (diff > 0) {
                note.style.color = '#047857';
                note.style.background = '#ecfdf5';
                note.style.borderColor = '#6ee7b7';
                note.innerHTML = '💵 الباقي للعميل: <strong>' + fmt(diff) + '</strong>';
                return;
            }

            var name = ($('customerNameInput') ? $('customerNameInput').value.trim() : '');
            var phone = ($('customerPhoneInput') ? $('customerPhoneInput').value.trim() : '');
            var registered = name && phone;
            note.style.color = registered ? '#92400e' : '#dc2626';
            note.style.background = registered ? '#fefce8' : '#fef2f2';
            note.style.borderColor = registered ? '#fde047' : '#fca5a5';
            if (registered) {
                note.innerHTML = '💳 باقي <strong>' + fmt(Math.abs(diff)) + '</strong> هيتسجل <strong>على حساب العميل</strong> (' + escapeHtml(name) + ')';
            } else if (name) {
                note.innerHTML = '❌ باقي <strong>' + fmt(Math.abs(diff)) + '</strong> — اكتب <strong>رقم موبايل العميل</strong> فوق كمان';
            } else {
                note.innerHTML = '❌ باقي <strong>' + fmt(Math.abs(diff)) + '</strong> — اكتب <strong>اسم العميل ورقم الموبايل</strong> فوق';
            }
        }

        if (discountInput) discountInput.addEventListener('input', updateTotals);
        if (paidInput) {
            paidInput.addEventListener('input', function () {
                paidInput.dataset.touched = '1';
                if (typeof normDigitInput === 'function') normDigitInput(paidInput);
                updatePaidNote();
            });
        }

        var submitBtn = $('coSubmitBtn');
        if (submitBtn) {
            submitBtn.addEventListener('click', function () {
                submitCheckout({
                    discount: num(discountInput ? discountInput.value : 0),
                    method: methodSelect ? methodSelect.value : 'كاش',
                    paid: paidInput ? paidInput.value : ''
                });
            });
        }

        updateTotals();
        $('checkoutModal').style.display = 'flex';
        if (paidInput) paidInput.focus();
    }

    function closeCheckoutModal() {
        var modal = $('checkoutModal');
        if (modal) modal.style.display = 'none';
    }

    function submitCheckout(opts) {
        if (!cart.length) return;

        var subtotal = cartSubtotal();
        var discount = Math.min(subtotal, Math.max(0, num(opts.discount)));
        var net = Math.max(0, subtotal - discount);
        var paid = opts.paid === '' ? net : num(opts.paid);

        var customerName = ($('customerNameInput') ? $('customerNameInput').value.trim() : '');
        var customerPhone = ($('customerPhoneInput') ? $('customerPhoneInput').value.trim() : '');

        if (customerName && !customerPhone) {
            appAlert('لازم تكتب رقم موبايل العميل عشان يتسجل في العملاء', 'error');
            return;
        }
        if (customerPhone && !customerName) {
            appAlert('لا يمكن إدخال رقم الموبايل بدون اسم العميل — اكتب اسم العميل أولاً', 'error');
            return;
        }
        if (paid > net && opts.method !== 'كاش') {
            appAlert('الدفع الزائد مسموح في الكاش فقط', 'error');
            return;
        }
        if (paid < net - 0.009) {
            if (!customerName) {
                appAlert('البيع الآجل يتطلب اسم العميل ورقم الموبايل عشان المبلغ يتحسب على حسابه', 'error');
                return;
            }
            if (!customerPhone) {
                appAlert('البيع الآجل يتطلب رقم موبايل العميل عشان يتسجل في العملاء ويتحسب على حسابه', 'error');
                return;
            }
        }

        var btn = $('coSubmitBtn');
        if (btn) { btn.disabled = true; btn.textContent = 'جاري الحفظ...'; }

        var payload = {
            client_key: getCheckoutKey(),
            warehouse_id: warehouseId(),
            customer_id: parseInt(($('customerIdInput') || {}).value, 10) || 0,
            customer_name: ($('customerNameInput') || {}).value || '',
            customer_phone: ($('customerPhoneInput') || {}).value || '',
            discount: discount,
            payment_method: opts.method,
            paid_amount: paid,
            items: cart.map(function (it) {
                return {
                    product_id: it.product_id,
                    quantity: it.quantity,
                    unit_price: it.unit_price
                };
            })
        };

        ajaxPost('checkout', payload).then(function (data) {
            if (!data || !data.success) {
                if (btn) { btn.disabled = false; btn.textContent = '🏁 تحصيل الفاتورة وطباعة الإيصال'; }
                appAlert((data && (data.error || data.message)) || 'تعذر إتمام البيعة', 'error');
                return;
            }

            lastInvoice = data;
            closeCheckoutModal();
            resetCart();
            resetCheckoutKey();
            loadProducts();
            refreshTodaySummary();

            var printUrl = data.print_url || ('modules/invoices/print.php?id=' + data.invoice_id + '&format=thermal');
            var absoluteUrl = /^https?:/i.test(printUrl) ? printUrl : (CFG.baseUrl || '/') + printUrl.replace(/^\.\.\//, '');
            if (typeof openReceiptModal === 'function') {
                openReceiptModal(absoluteUrl);
            } else {
                window.open(absoluteUrl, '_blank');
            }
        }).catch(function () {
            if (btn) { btn.disabled = false; btn.textContent = '🏁 تحصيل الفاتورة وطباعة الإيصال'; }
            appAlert('تعذر الاتصال بالسيرفر — لم تُحفظ الفاتورة', 'error');
        });
    }

    function resetCart() {
        cart = [];
        if ($('customerNameInput')) $('customerNameInput').value = '';
        if ($('customerIdInput')) $('customerIdInput').value = '';
        if ($('customerPhoneInput')) $('customerPhoneInput').value = '';
        validateCustomerFields(false);
        showCustomerBalance(null);
        renderCart();
    }

    // ---------------- Holds ----------------
    function holdCart() {
        if (!cart.length) { showToast('السلة فارغة', 'info'); return; }
        if (!CFG.canHold) { appAlert('لا تملك صلاحية تعليق السلات', 'error'); return; }

        ajaxPost('hold', {
            warehouse_id: warehouseId(),
            customer_id: parseInt(($('customerIdInput') || {}).value, 10) || 0,
            customer_name: ($('customerNameInput') || {}).value || '',
            customer_phone: ($('customerPhoneInput') || {}).value || '',
            items: cart
        }).then(function (data) {
            if (!data || !data.success) {
                appAlert((data && (data.error || data.message)) || 'تعذر تعليق السلة', 'error');
                return;
            }
            resetCart();
            resetCheckoutKey();
            renderProductsGrid();
            showToast('تم تعليق السلة رقم ' + data.hold_number);
        }).catch(function () {
            appAlert('تعذر الاتصال بالسيرفر', 'error');
        });
    }

    function openHoldsModal() {
        var body = $('holdsModalBody');
        if (!body) return;
        body.innerHTML = '<div style="text-align:center;padding:24px;color:#64748b;">جاري التحميل...</div>';
        $('holdsModal').style.display = 'flex';

        ajax('list_holds').then(function (data) {
            var holds = (data && data.holds) || [];
            if (!holds.length) {
                body.innerHTML = '<div class="pos-empty-hint"><div class="big">💾</div>لا توجد سلات معلقة</div>';
                return;
            }
            body.innerHTML = holds.map(function (h) {
                return '<div style="border:1px solid #e2e8f0;border-radius:10px;padding:10px;margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;gap:10px;">' +
                    '<div style="font-size:12.5px;">' +
                        '<div style="font-weight:800;color:#0f172a;">' + escapeHtml(h.hold_number) + '</div>' +
                        '<div style="color:#64748b;">' + (h.customer_name ? escapeHtml(h.customer_name) + ' — ' : '') + h.items_count + ' قطعة</div>' +
                        '<div style="color:#94a3b8;font-size:11px;">' + escapeHtml(h.created_at || '') + '</div>' +
                    '</div>' +
                    '<div style="display:flex;gap:6px;">' +
                        '<button type="button" class="resume-hold" data-id="' + h.id + '" style="background:#ea580c;color:#fff;border:none;border-radius:8px;padding:7px 12px;font-weight:800;font-size:12px;cursor:pointer;">استرجاع</button>' +
                        '<button type="button" class="delete-hold" data-id="' + h.id + '" style="background:#f1f5f9;color:#dc2626;border:1px solid #e2e8f0;border-radius:8px;padding:7px 12px;font-weight:800;font-size:12px;cursor:pointer;">حذف</button>' +
                    '</div>' +
                '</div>';
            }).join('');

            Array.prototype.forEach.call(body.querySelectorAll('.resume-hold'), function (btn) {
                btn.addEventListener('click', function () { resumeHold(parseInt(btn.getAttribute('data-id'), 10)); });
            });
            Array.prototype.forEach.call(body.querySelectorAll('.delete-hold'), function (btn) {
                btn.addEventListener('click', function () {
                    var id = parseInt(btn.getAttribute('data-id'), 10);
                    appConfirm('حذف السلة المعلقة نهائياً؟', { danger: true }).then(function (ok) {
                        if (!ok) return;
                        ajaxPost('delete_hold', { id: id }).then(function () { openHoldsModal(); });
                    });
                });
            });
        }).catch(function () {
            body.innerHTML = '<div style="text-align:center;padding:24px;color:#dc2626;">تعذر تحميل السلات المعلقة</div>';
        });
    }

    function closeHoldsModal() {
        var modal = $('holdsModal');
        if (modal) modal.style.display = 'none';
    }

    function resumeHold(id) {
        ajax('resume_hold', { id: id }).then(function (data) {
            if (!data || !data.success) {
                appAlert((data && (data.error || data.message)) || 'تعذر استرجاع السلة', 'error');
                return;
            }
            var hold = data.hold;
            cart = (hold.items || []).map(function (it) {
                return {
                    product_id: parseInt(it.product_id, 10),
                    product_name: it.product_name || it.name || '',
                    product_code: it.product_code || it.code || '',
                    unit: it.unit || '',
                    unit_price: num(it.unit_price),
                    quantity: parseInt(it.quantity, 10) || 1,
                    stock: parseInt(it.stock, 10) || 999999
                };
            });
            if ($('customerNameInput')) $('customerNameInput').value = hold.customer_name || '';
            if ($('customerPhoneInput')) $('customerPhoneInput').value = hold.customer_phone || '';
            if ($('customerIdInput')) $('customerIdInput').value = hold.customer_id || '';
            validateCustomerFields(false);
            resetCheckoutKey();
            closeHoldsModal();
            renderCart();
            renderProductsGrid();
            showToast('تم استرجاع السلة ' + (hold.hold_number || ''));
        }).catch(function () {
            appAlert('تعذر الاتصال بالسيرفر', 'error');
        });
    }

    // ---------------- Today summary ----------------
    function refreshTodaySummary() {
        ajax('today_summary').then(function (data) {
            if (!data || !data.success) return;
            if ($('todayTotal')) $('todayTotal').textContent = money(data.today_total);
            var chip = $('todayChip');
            if (chip) chip.title = 'عدد فواتير الكاشير اليوم: ' + data.today_count;
        }).catch(function () {});
    }

    // ---------------- Events ----------------
    function setupEvents() {
        Array.prototype.forEach.call(document.querySelectorAll('.category-pill'), function (btn) {
            btn.addEventListener('click', function () {
                Array.prototype.forEach.call(document.querySelectorAll('.category-pill'), function (b) {
                    b.classList.remove('active');
                });
                btn.classList.add('active');
                currentCategory = btn.getAttribute('data-category-id') || '';
                loadProducts();
            });
        });

        var searchInput = $('posSearchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                currentSearch = this.value.trim();
                clearTimeout(searchTimer);
                searchTimer = setTimeout(loadProducts, 220);
            });

            // Barcode scanners end their input with Enter.
            searchInput.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter') return;
                e.preventDefault();
                var code = this.value.trim();
                if (!code) return;

                ajax('get_by_barcode', { code: code, warehouse_id: warehouseId() }).then(function (data) {
                    if (data && data.success && data.product) {
                        addToCart(data.product);
                        searchInput.value = '';
                        currentSearch = '';
                        loadProducts();
                    } else {
                        loadProducts();
                    }
                }).catch(function () {});
            });
        }

                // Global Barcode Scanner Listener for POS
        var posBarcodeBuffer = '';
        var posLastKeyTime = 0;
        document.addEventListener('keydown', function (e) {
            if (e.target === searchInput) return;
            var targetTag = e.target ? e.target.tagName : '';
            var isOtherInput = targetTag === 'INPUT' || targetTag === 'TEXTAREA' || targetTag === 'SELECT';

            var now = Date.now();
            var diff = now - posLastKeyTime;
            posLastKeyTime = now;

            if (diff > 80) {
                posBarcodeBuffer = '';
            }

            if (e.key === 'Enter') {
                if (posBarcodeBuffer.length >= 3) {
                    e.preventDefault();
                    var scanned = posBarcodeBuffer;
                    posBarcodeBuffer = '';
                    ajax('get_by_barcode', { code: scanned, warehouse_id: warehouseId() }).then(function (data) {
                        if (data && data.success && data.product) {
                            addToCart(data.product);
                        } else {
                            showToast('لا يوجد صنف بهذا الباركود', 'error');
                        }
                    }).catch(function () {});
                }
            } else if (e.key.length === 1 && !e.ctrlKey && !e.altKey && !e.metaKey) {
                if (!isOtherInput || diff < 60) {
                    posBarcodeBuffer += e.key;
                }
            }
        });

        var warehouseSelect = $('posWarehouseSelect');
        if (warehouseSelect) {
            warehouseSelect.addEventListener('change', function () {
                loadProducts();
                refreshTodaySummary();
            });
        }

        var clearBtn = $('clearCartBtn');
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                if (!cart.length) return;
                appConfirm('هل تريد مسح جميع الأصناف من السلة؟').then(function (ok) {
                    if (!ok) return;
                    resetCart();
                    resetCheckoutKey();
                    renderProductsGrid();
                });
            });
        }

        var checkoutBtn = $('checkoutBtn');
        if (checkoutBtn) checkoutBtn.addEventListener('click', openCheckoutModal);

        var holdBtn = $('holdBtn');
        if (holdBtn) {
            holdBtn.addEventListener('click', function () { holdCart(); });
            // double-click title area not needed; long-press replaced by Alt+click
        }

        var holdsLink = $('holdsBtn');
        if (holdsLink) holdsLink.addEventListener('click', openHoldsModal);

        var migrateBtn = $('applyMigrationBtn');
        if (migrateBtn) {
            migrateBtn.addEventListener('click', function () {
                migrateBtn.disabled = true;
                migrateBtn.textContent = 'جاري التحديث...';
                ajaxPost('apply_migration', {}).then(function (data) {
                    if (data && data.success) {
                        showToast('تم تحديث قاعدة البيانات، جاري إعادة التحميل...');
                        setTimeout(function () { window.location.reload(); }, 900);
                    } else {
                        migrateBtn.disabled = false;
                        migrateBtn.textContent = '⚙️ تحديث قاعدة البيانات الآن';
                        appAlert((data && (data.error || data.message)) || 'تعذر تحديث قاعدة البيانات', 'error');
                    }
                }).catch(function () {
                    migrateBtn.disabled = false;
                    migrateBtn.textContent = '⚙️ تحديث قاعدة البيانات الآن';
                    appAlert('تعذر الاتصال بالسيرفر', 'error');
                });
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        setupEvents();
        setupCustomerSearch();
        renderCart();
        loadProducts();
        refreshTodaySummary();
    });

    // Expose for inline handlers / debugging
    window.posAddToCart = addToCart;
    window.closeCheckoutModal = closeCheckoutModal;
    window.closeHoldsModal = closeHoldsModal;
    window.openHoldsModal = openHoldsModal;
})();
