<?php
/**
 * Sales Invoice - Simplified Fast Version
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';

$pageTitle = 'فاتورة بيع جديدة';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        beginTransaction();
        
        // Generate invoice number
        $invoiceNumber = generateInvoiceNumber('sale');
        
        // Get form data
        $customerId = !empty($_POST['customer_id']) ? $_POST['customer_id'] : null;
        $customerName = sanitize($_POST['customer_name'] ?? '');
        $customerPhone = sanitize($_POST['customer_phone'] ?? '');
        $date = $_POST['date'];
        $discount = floatval($_POST['discount'] ?? 0);
        $paidAmount = floatval($_POST['paid_amount'] ?? 0);
        $paymentMethod = sanitize($_POST['payment_method'] ?? 'كاش');
        $handledBy = sanitize($_POST['handled_by'] ?? 'المدير');
        $notes = sanitize($_POST['notes'] ?? '');
        
        // Get customer's old balance BEFORE this invoice
        $customerOldBalance = 0;
        if ($customerId) {
            $customerData = getRow("SELECT balance FROM customers WHERE id = ?", [$customerId]);
            $customerOldBalance = floatval($customerData['balance'] ?? 0);
        }
        
        // Calculate totals
        $items = json_decode($_POST['items'], true);
        $totalAmount = 0;
        foreach ($items as $item) {
            $totalAmount += $item['quantity'] * $item['price'];
        }
        
        $finalAmount = $totalAmount - $discount;
        $remainingAmount = $finalAmount - $paidAmount;
        
        // Validate overpayment
        if ($paidAmount > $finalAmount) {
            // Overpaying - check if customer has old debt
            $oldDebt = $customerOldBalance < 0 ? abs($customerOldBalance) : 0;
            $maxPayment = $finalAmount + $oldDebt;
            
            if ($paidAmount > $maxPayment) {
                if ($oldDebt > 0) {
                    throw new Exception("المبلغ المدفوع ({$paidAmount}) يتجاوز قيمة الفاتورة ({$finalAmount}) + الحساب القديم ({$oldDebt})");
                } else {
                    throw new Exception("المبلغ المدفوع ({$paidAmount}) لا يمكن أن يتجاوز قيمة الفاتورة ({$finalAmount}) لأنه لا يوجد حساب قديم على العميل");
                }
            }
        }
        
        // Determine payment status
        if ($paidAmount >= $finalAmount) {
            $paymentStatus = 'paid';
        } elseif ($paidAmount > 0) {
            $paymentStatus = 'partial';
        } else {
            $paymentStatus = 'unpaid';
        }
        
        // Insert invoice with old_balance
        $invoiceId = insert(
            "INSERT INTO invoices (invoice_number, type, customer_id, customer_name, customer_phone, date, total_amount, discount, paid_amount, remaining_amount, old_balance, payment_method, payment_status, handled_by, notes) 
            VALUES (?, 'sale', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$invoiceNumber, $customerId, $customerName, $customerPhone, $date, $totalAmount, $discount, $paidAmount, $remainingAmount, $customerOldBalance, $paymentMethod, $paymentStatus, $handledBy, $notes]
        );
        
        // Insert items and update stock
        foreach ($items as $item) {
            insert(
                "INSERT INTO invoice_items (invoice_id, product_id, product_code, product_name, unit, quantity, unit_price, total) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$invoiceId, $item['product_id'], $item['code'], $item['name'], $item['unit'], $item['quantity'], $item['price'], $item['quantity'] * $item['price']]
            );
            
            // Update stock (prevent going below 0)
            execute(
                "UPDATE products SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE id = ?",
                [$item['quantity'], $item['product_id']]
            );
        }
        
        // Auto-add customer if not registered and has phone number
        if (!$customerId && $customerPhone && $customerName) {
            $existingCustomer = getRow("SELECT id, name FROM customers WHERE phone = ?", [$customerPhone]);
            
            if ($existingCustomer) {
                // Phone belongs to existing customer - throw error
                throw new Exception("رقم التليفون ({$customerPhone}) مسجل بالفعل للعميل: " . $existingCustomer['name'] . ". اختر العميل من القائمة أو استخدم رقم مختلف.");
            } else {
                $customerId = insert(
                    "INSERT INTO customers (name, phone, balance) VALUES (?, ?, 0)",
                    [$customerName, $customerPhone]
                );
                logActivity('إضافة عميل تلقائي', "تم إضافة العميل تلقائياً: $customerName من فاتورة بيع", $handledBy);
            }
            
            execute("UPDATE invoices SET customer_id = ? WHERE id = ?", [$customerId, $invoiceId]);
        }
        
        // Update customer balance:
        // - Negative balance means customer owes us
        // - remainingAmount > 0 means still owes from this invoice
        // - remainingAmount < 0 means overpaid (can apply to old debt)
        if ($customerId) {
            // Always update balance with the remaining amount
            // This handles both underpayment and overpayment
            execute(
                "UPDATE customers SET balance = balance - ? WHERE id = ?",
                [$remainingAmount, $customerId]
            );
        }
        
        commit();
        logActivity('إنشاء فاتورة', "تم إنشاء فاتورة بيع رقم {$invoiceNumber}", $handledBy);
        
        // Redirect to print with remaining info for installment prompt
        $redirectUrl = "print.php?id=$invoiceId";
        
        // Calculate total remaining including old debt
        $oldDebt = $customerOldBalance < 0 ? abs($customerOldBalance) : 0;
        $totalRemaining = $remainingAmount + $oldDebt;
        
        if ($totalRemaining > 0 && $customerId) {
            $redirectUrl .= "&remaining=$totalRemaining&new_amount=$remainingAmount&old_amount=$oldDebt&customer_id=$customerId&show_installment=1";
        }
        header("Location: $redirectUrl");
        exit;
        
    } catch (Exception $e) {
        rollback();
        setError('حدث خطأ: ' . $e->getMessage());
    }
}

// Get customers and products for autocomplete with balance
$customers = getRows("SELECT id, name, phone, balance FROM customers ORDER BY name");
$products = getRows("SELECT id, code, name, unit, price, stock_quantity FROM products ORDER BY name");

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<style>
.invoice-container {
    max-width: 98%;
    margin: 0 auto;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    padding: 3px 8px;
}

.invoice-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 5px;
    border-bottom: 1px solid #e5e7eb;
    margin-bottom: 6px;
}

.invoice-title {
    font-size: 1.3em;
    font-weight: bold;
    color: #1f2937;
}

.invoice-number {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    color: white;
    padding: 6px 12px;
    border-radius: 6px;
    font-weight: bold;
    font-size: 0.9em;
}

.section-title {
    font-size: 0.9em;
    font-weight: bold;
    color: #374151;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.customer-section, .products-section, .totals-section {
    background: #f9fafb;
    border-radius: 6px;
    padding: 8px 10px;
    margin-bottom: 6px;
}

.customer-section {
    background: linear-gradient(135deg, #eff6ff, #dbeafe);
}

.autocomplete-wrapper {
    position: relative;
}

.autocomplete-results {
    position: absolute;
    width: 100%;
    max-height: 150px;
    overflow-y: auto;
    background: white;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.15);
    z-index: 100;
    display: none;
}

.autocomplete-item {
    padding: 8px 10px;
    cursor: pointer;
    border-bottom: 1px solid #f3f4f6;
    font-size: 0.85em;
}

.autocomplete-item:hover, .autocomplete-item.active {
    background: #dbeafe;
}

.items-table {
    width: 100%;
    border-collapse: collapse;
}

.items-table th {
    background: #3b82f6;
    color: white;
    padding: 6px 4px;
    text-align: center;
    font-size: 0.8em;
}

.items-table td {
    padding: 5px 4px;
    border-bottom: 1px solid #e5e7eb;
    text-align: center;
    font-size: 0.85em;
}

.items-table input {
    width: 60px;
    padding: 4px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    text-align: center;
    font-size: 0.85em;
}

.items-table .remove-btn {
    background: #dc2626;
    color: white;
    border: none;
    padding: 3px 8px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.8em;
}

.totals-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
}

.total-box {
    background: white;
    padding: 8px;
    border-radius: 6px;
    text-align: center;
}

.total-box.highlight {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.total-box.danger {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
}

.total-label {
    font-size: 0.75em;
    margin-bottom: 2px;
}

.total-value {
    font-size: 1em;
    font-weight: bold;
}

.form-row-inline {
    display: flex;
    gap: 10px;
    margin-bottom: 8px;
}

.form-row-inline .form-group {
    flex: 1;
}

.form-row-inline .form-label {
    font-size: 0.8em;
    margin-bottom: 3px;
}

.form-row-inline .form-control {
    padding: 6px 8px;
    font-size: 0.85em;
}

.btn-submit {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    color: white;
    padding: 8px 20px;
    font-size: 0.9em;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
}

.btn-submit:hover {
    background: linear-gradient(135deg, #2563eb, #1e40af);
}

#productSearch {
    padding: 8px 12px !important;
    font-size: 0.9em !important;
}

@media print {
    .no-print { display: none !important; }
    .invoice-container { box-shadow: none; }
}

.container {
    padding: 0px !important;
}
</style>

<div class="container">
    <div class="invoice-container">
        <form method="POST" id="invoiceForm">
            <!-- Invoice Header -->
            <div class="invoice-header">
                <div class="invoice-title">🧾 فاتورة بيع</div>
                <div>
                    <div class="invoice-number"><?php echo generateInvoiceNumber('sale'); ?></div>
                    <input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" style="margin-top: 4px; padding: 4px 6px; border-radius: 4px; border: 1px solid #d1d5db; font-size: 0.85em;">
                </div>
            </div>
            
            <!-- Customer Section -->
            <div class="customer-section">
                <div class="section-title">👤 بيانات العميل</div>
                <div class="form-row-inline">
                    <div class="form-group">
                        <label class="form-label">اسم العميل</label>
                        <div class="autocomplete-wrapper">
                            <input type="text" id="customerName" name="customer_name" class="form-control" 
                                   placeholder="اكتب اسم العميل..." autocomplete="off" required>
                            <input type="hidden" id="customerId" name="customer_id">
                            <div id="customerResults" class="autocomplete-results"></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">التليفون</label>
                        <input type="text" id="customerPhone" name="customer_phone" class="form-control" 
                               placeholder="01xxxxxxxxx" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">البائع</label>
                        <input type="text" name="handled_by" class="form-control" value="المدير" required>
                    </div>
                </div>
                
                <!-- Old Balance Display -->
                <div id="customerBalanceSection" style="display: none; margin-top: 15px; padding: 15px; background: #fee2e2; border: 2px solid #dc2626; border-radius: 10px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-weight: bold;">⚠️ حساب قديم على العميل:</span>
                        <span id="oldBalanceDisplay" style="font-size: 1.3em; font-weight: bold; color: #dc2626;">0.00 جنيه</span>
                        <input type="hidden" id="oldBalance" value="0">
                    </div>
                </div>
            </div>
            
            <!-- Products Section -->
            <div class="products-section">
                <div class="section-title">📦 الأصناف</div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <div class="autocomplete-wrapper">
                        <input type="text" id="productSearch" class="form-control" 
                               placeholder="🔍 ابحث عن صنف بالاسم أو الكود..." autocomplete="off"
                               style="font-size: 1.1em; padding: 15px;">
                        <div id="productResults" class="autocomplete-results"></div>
                    </div>
                </div>
                
                <table class="items-table" id="itemsTable">
                    <thead>
                        <tr>
                            <th style="width: 40px;">م</th>
                            <th style="width: 80px;">الكود</th>
                            <th>الصنف</th>
                            <th style="width: 60px;">الوحدة</th>
                            <th style="width: 80px;">الكمية</th>
                            <th style="width: 100px;">السعر</th>
                            <th style="width: 100px;">الإجمالي</th>
                            <th style="width: 50px;"></th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                        <tr id="emptyRow">
                            <td colspan="8" style="padding: 30px; color: #9ca3af;">ابحث عن صنف لإضافته للفاتورة</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Totals Section -->
            <div class="totals-section">
                <div class="section-title">💰 الحساب</div>
                <div class="totals-grid">
                    <div class="total-box">
                        <div class="total-label">الإجمالي</div>
                        <div class="total-value" id="subtotalDisplay">0.00</div>
                        <input type="hidden" id="subtotal" value="0">
                    </div>
                    <div class="total-box">
                        <div class="total-label">الخصم</div>
                        <input type="number" step="0.01" name="discount" id="discount" 
                               style="width: 100%; text-align: center; font-size: 1.2em; padding: 8px; border: 1px solid #d1d5db; border-radius: 5px;"
                               value="0" oninput="calculateTotals()">
                    </div>
                    <div class="total-box highlight">
                        <div class="total-label">الصافي</div>
                        <div class="total-value" id="totalDisplay">0.00</div>
                        <input type="hidden" id="total" value="0">
                    </div>
                </div>
                
                <div style="margin-top: 20px;">
                    <div class="totals-grid">
                        <div class="total-box">
                            <div class="total-label">المدفوع</div>
                            <input type="number" step="0.01" name="paid_amount" id="paidAmount" 
                                   style="width: 100%; text-align: center; font-size: 0.9em; padding: 5px; border: 1px solid #d1d5db; border-radius: 4px;"
                                   value="0" oninput="calculateTotals()">
                        </div>
                        <div class="total-box" id="oldBalanceBox" style="display: none;">
                            <div class="total-label">حساب قديم</div>
                            <div class="total-value" id="oldBalanceTotalDisplay" style="color: #dc2626;">0.00</div>
                        </div>
                        <div class="total-box danger" id="remainingBox">
                            <div class="total-label">الباقي</div>
                            <div class="total-value" id="remainingDisplay">0.00</div>
                        </div>
                        <div class="total-box">
                            <div class="total-label">طريقة الدفع</div>
                            <select name="payment_method" class="form-control" style="font-size: 0.85em; padding: 5px;">
                                <option value="كاش">كاش</option>
                                <option value="انستاباي">انستاباي</option>
                                <option value="فودافون كاش">فودافون كاش</option>
                                <option value="فيزا">فيزا</option>
                                <option value="أقساط">أقساط</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Notes -->
            <div class="form-group" style="margin-bottom: 8px;">
                <input type="text" name="notes" class="form-control" placeholder="ملاحظات..." style="font-size: 0.85em; padding: 6px;">
            </div>
            
            <input type="hidden" name="items" id="itemsData">
            
            <!-- Submit -->
            <div class="d-flex gap-2">
                <button type="submit" class="btn-submit">
                    💾 حفظ وطباعة الفاتورة
                </button>
                <a href="list.php" class="btn btn-secondary btn-lg">❌ إلغاء</a>
            </div>
        </form>
    </div>
</div>

<script>
// Customer and Product data
const customers = <?php echo json_encode($customers); ?>;
const products = <?php echo json_encode($products); ?>;

let items = [];
let itemCounter = 1;

// Customer Autocomplete
const customerNameInput = document.getElementById('customerName');
const customerResults = document.getElementById('customerResults');
const customerIdInput = document.getElementById('customerId');
const customerPhoneInput = document.getElementById('customerPhone');

customerNameInput.addEventListener('input', function() {
    const query = this.value.toLowerCase().trim();
    if (query.length < 1) {
        customerResults.style.display = 'none';
        return;
    }
    
    const matches = customers.filter(c => 
        c.name.toLowerCase().includes(query) || c.phone.includes(query)
    ).slice(0, 10);
    
    if (matches.length > 0) {
        customerResults.innerHTML = matches.map(c => `
            <div class="autocomplete-item" onclick="selectCustomer(${c.id}, '${c.name}', '${c.phone}', ${c.balance || 0})">
                <strong>${c.name}</strong> - ${c.phone}
                ${c.balance < 0 ? '<br><small style="color: #dc2626;">عليه: ' + Math.abs(c.balance) + ' جنيه</small>' : ''}
            </div>
        `).join('');
        customerResults.style.display = 'block';
    } else {
        customerResults.innerHTML = '<div class="autocomplete-item" style="color: #6b7280;">عميل جديد - سيتم إضافته تلقائياً</div>';
        customerResults.style.display = 'block';
        customerIdInput.value = '';
    }
});

let customerOldBalance = 0;

function selectCustomer(id, name, phone, balance) {
    customerNameInput.value = name;
    customerPhoneInput.value = phone;
    customerIdInput.value = id;
    customerResults.style.display = 'none';
    
    // Show old balance if customer owes money (negative balance means debt)
    customerOldBalance = parseFloat(balance) || 0;
    const balanceSection = document.getElementById('customerBalanceSection');
    const oldBalanceBox = document.getElementById('oldBalanceBox');
    
    if (customerOldBalance < 0) {
        const debt = Math.abs(customerOldBalance);
        document.getElementById('oldBalanceDisplay').textContent = debt.toFixed(2) + ' جنيه';
        document.getElementById('oldBalance').value = debt;
        balanceSection.style.display = 'block';
        oldBalanceBox.style.display = 'block';
        document.getElementById('oldBalanceTotalDisplay').textContent = debt.toFixed(2);
    } else {
        balanceSection.style.display = 'none';
        oldBalanceBox.style.display = 'none';
    }
    calculateTotals();
}

// Product Autocomplete
const productSearch = document.getElementById('productSearch');
const productResults = document.getElementById('productResults');

productSearch.addEventListener('input', function() {
    const query = this.value.toLowerCase().trim();
    if (query.length < 1) {
        productResults.style.display = 'none';
        return;
    }
    
    const matches = products.filter(p => 
        p.name.toLowerCase().includes(query) || p.code.toLowerCase().includes(query)
    ).slice(0, 10);
    
    if (matches.length > 0) {
        productResults.innerHTML = matches.map(p => `
            <div class="autocomplete-item" onclick='addProduct(${JSON.stringify(p)})' style="${p.stock_quantity <= 0 ? 'background: #fef2f2;' : ''}">
                <strong>${p.name}</strong> (${p.code})
                ${p.stock_quantity <= 0 ? '<span style="color: #dc2626; font-weight: bold; margin-right: 8px;">⚠️ نفذ</span>' : ''}
                <br>
                <small style="color: #6b7280;">السعر: ${p.price} جنيه - المخزون: ${p.stock_quantity <= 0 ? '<span style="color: #dc2626;">0</span>' : p.stock_quantity}</small>
            </div>
        `).join('');
        productResults.style.display = 'block';
    } else {
        productResults.innerHTML = '<div class="autocomplete-item" style="color: #dc2626;">لا توجد نتائج</div>';
        productResults.style.display = 'block';
    }
});

function addProduct(product) {
    // Check if already added
    const existing = items.find(i => i.product_id == product.id);
    if (existing) {
        existing.quantity++;
        renderItems();
        productSearch.value = '';
        productResults.style.display = 'none';
        return;
    }
    
    items.push({
        id: itemCounter++,
        product_id: product.id,
        code: product.code,
        name: product.name,
        unit: product.unit,
        quantity: 1,
        price: parseFloat(product.price),
        stock: parseInt(product.stock_quantity)
    });
    
    renderItems();
    productSearch.value = '';
    productResults.style.display = 'none';
    productSearch.focus();
}

function renderItems() {
    const tbody = document.getElementById('itemsBody');
    
    if (items.length === 0) {
        tbody.innerHTML = '<tr id="emptyRow"><td colspan="8" style="padding: 30px; color: #9ca3af;">ابحث عن صنف لإضافته للفاتورة</td></tr>';
        calculateTotals();
        return;
    }
    
    tbody.innerHTML = items.map((item, index) => `
        <tr>
            <td>${index + 1}</td>
            <td style="font-size: 0.9em;">${item.code}</td>
            <td style="text-align: center;"><strong>${item.name}</strong></td>
            <td>${item.unit}</td>
            <td>
                <input type="number" min="1" value="${item.quantity}" 
                       onchange="updateQuantity(${item.id}, this.value)">
            </td>
            <td>
                <input type="number" step="0.01" value="${item.price}" 
                       onchange="updatePrice(${item.id}, this.value)">
            </td>
            <td style="font-weight: bold;">${(item.quantity * item.price).toFixed(2)}</td>
            <td>
                <button type="button" class="remove-btn" onclick="removeItem(${item.id})">✕</button>
            </td>
        </tr>
    `).join('');
    
    calculateTotals();
}

function updateQuantity(id, qty) {
    const item = items.find(i => i.id === id);
    if (item) {
        item.quantity = parseInt(qty) || 1;
        renderItems();
    }
}

function updatePrice(id, price) {
    const item = items.find(i => i.id === id);
    if (item) {
        item.price = parseFloat(price) || 0;
        renderItems();
    }
}

function removeItem(id) {
    items = items.filter(i => i.id !== id);
    renderItems();
}

function calculateTotals() {
    const subtotal = items.reduce((sum, item) => sum + (item.quantity * item.price), 0);
    const discount = parseFloat(document.getElementById('discount').value) || 0;
    const total = subtotal - discount;
    const paid = parseFloat(document.getElementById('paidAmount').value) || 0;
    const oldDebt = customerOldBalance < 0 ? Math.abs(customerOldBalance) : 0;
    const maxPayment = total + oldDebt;
    const remaining = total - paid;
    const totalWithOldDebt = remaining + oldDebt;
    
    document.getElementById('subtotal').value = subtotal;
    document.getElementById('subtotalDisplay').textContent = subtotal.toFixed(2);
    document.getElementById('total').value = total;
    document.getElementById('totalDisplay').textContent = total.toFixed(2);
    document.getElementById('remainingDisplay').textContent = totalWithOldDebt.toFixed(2);
    
    // Overpayment validation
    const paidInput = document.getElementById('paidAmount');
    const remainingBox = document.getElementById('remainingBox');
    
    // Get or create warning element
    let warningEl = document.getElementById('overpaymentWarning');
    if (!warningEl) {
        warningEl = document.createElement('div');
        warningEl.id = 'overpaymentWarning';
        warningEl.style.cssText = 'color: #dc2626; font-size: 0.85em; margin-top: 5px; display: none;';
        paidInput.parentNode.appendChild(warningEl);
    }
    
    if (paid > maxPayment && total > 0) {
        // Overpaying more than allowed
        paidInput.style.borderColor = '#dc2626';
        paidInput.style.background = '#fee2e2';
        if (oldDebt > 0) {
            warningEl.innerHTML = `⚠️ الحد الأقصى: ${maxPayment.toFixed(2)} (الفاتورة ${total.toFixed(2)} + الحساب القديم ${oldDebt.toFixed(2)})`;
        } else {
            warningEl.innerHTML = `⚠️ الحد الأقصى: ${total.toFixed(2)} (لا يوجد حساب قديم)`;
        }
        warningEl.style.display = 'block';
        remainingBox.className = 'total-box danger';
        document.getElementById('remainingDisplay').textContent = '❌ خطأ';
    } else {
        paidInput.style.borderColor = '#d1d5db';
        paidInput.style.background = 'white';
        warningEl.style.display = 'none';
        
        // Update remaining box color
        if (totalWithOldDebt <= 0) {
            remainingBox.className = 'total-box highlight';
            document.getElementById('remainingDisplay').textContent = '0.00 ✓';
        } else {
            remainingBox.className = 'total-box danger';
        }
    }
}

// Form submit validation
document.getElementById('invoiceForm').addEventListener('submit', function(e) {
    if (items.length === 0) {
        e.preventDefault();
        alert('يرجى إضافة صنف واحد على الأقل');
        productSearch.focus();
        return;
    }
    
    const total = parseFloat(document.getElementById('total').value) || 0;
    const paid = parseFloat(document.getElementById('paidAmount').value) || 0;
    const oldDebt = customerOldBalance < 0 ? Math.abs(customerOldBalance) : 0;
    const maxPayment = total + oldDebt;
    
    if (paid > maxPayment && total > 0) {
        e.preventDefault();
        if (oldDebt > 0) {
            alert(`المبلغ المدفوع (${paid}) يتجاوز الحد الأقصى (${maxPayment.toFixed(2)})\n\nالفاتورة: ${total.toFixed(2)}\nالحساب القديم: ${oldDebt.toFixed(2)}`);
        } else {
            alert(`المبلغ المدفوع (${paid}) لا يمكن أن يتجاوز قيمة الفاتورة (${total.toFixed(2)})\n\nلا يوجد حساب قديم على العميل`);
        }
        return;
    }
    
    document.getElementById('itemsData').value = JSON.stringify(items);
});

// Focus on product search on load
document.addEventListener('DOMContentLoaded', function() {
    productSearch.focus();
});
</script>

<?php include '../../includes/footer.php'; ?>
