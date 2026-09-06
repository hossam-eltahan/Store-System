<?php
/**
 * Create Return - Select invoice and items to return
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requirePermission('returns.create_customer');

$pageTitle = 'مرتجع عميل';

// Generate return number
function generateReturnNumber() {
    $lastReturn = getRow("SELECT return_number FROM returns ORDER BY id DESC LIMIT 1");
    if ($lastReturn) {
        $num = intval(substr($lastReturn['return_number'], 4)) + 1;
    } else {
        $num = 1;
    }
    return 'RET-' . str_pad($num, 5, '0', STR_PAD_LEFT);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        beginTransaction();
        
        $returnNumber = generateReturnNumber();
        $invoiceId = $_POST['invoice_id'];
        $customerId = $_POST['customer_id'] ?: null;
        $customerName = sanitize($_POST['customer_name']);
        $returnDate = $_POST['return_date'];
        $handledBy = !empty($_POST['handled_by']) ? sanitize($_POST['handled_by']) : ($_SESSION['full_name'] ?? 'المدير');
        $userId = getCurrentUserId();
        $notes = sanitize($_POST['notes'] ?? '');
        
        // Get items to return
        $items = json_decode($_POST['return_items'], true);
        if (empty($items)) {
            throw new Exception('يرجى اختيار أصناف للإرجاع');
        }
        
        // Validate quantities against original invoice items
        foreach ($items as $item) {
            $originalItem = getRow(
                "SELECT 
                    ii.quantity,
                    (
                        SELECT COALESCE(SUM(ri.quantity), 0) 
                        FROM return_items ri 
                        JOIN returns r ON ri.return_id = r.id 
                        WHERE r.original_invoice_id = ii.invoice_id 
                        AND ri.product_id = ii.product_id
                    ) as returned_quantity
                 FROM invoice_items ii 
                 WHERE ii.invoice_id = ? AND ii.product_id = ?",
                [$invoiceId, $item['product_id']]
            );
            if (!$originalItem) {
                throw new Exception('صنف غير موجود في الفاتورة الأصلية: ' . $item['name']);
            }
            
            $availableQuantity = $originalItem['quantity'] - $originalItem['returned_quantity'];
            
            if ($item['quantity'] > $availableQuantity) {
                throw new Exception('الكمية المرتجعة (' . $item['quantity'] . ') أكبر من الكمية المتاحة للإرجاع (' . $availableQuantity . ') للصنف: ' . $item['name']);
            }
            if ($item['quantity'] <= 0) {
                throw new Exception('الكمية يجب أن تكون أكبر من صفر للصنف: ' . $item['name']);
            }
            if ($item['price'] < 0) {
                throw new Exception('السعر لا يمكن أن يكون سالب للصنف: ' . $item['name']);
            }
        }
        
        // Calculate total
        $totalAmount = 0;
        foreach ($items as $item) {
            $totalAmount += $item['quantity'] * $item['price'];
        }
        
        // Get customer's old balance BEFORE this return
        $customerOldBalance = 0;
        if ($customerId) {
            $customerData = getRow("SELECT balance FROM customers WHERE id = ?", [$customerId]);
            $customerOldBalance = floatval($customerData['balance'] ?? 0);
        }
        
        // Get refund method from user selection
        $refundMethod = $_POST['refund_method'] ?? 'cash';
        $deductedFromBalance = 0;
        $cashRefund = $totalAmount;
        
        // If user chose to deduct from balance (fully or mixed), calculate the amount
        if (($refundMethod === 'deducted' || $refundMethod === 'mixed') && $customerId) {
            $customer = getRow("SELECT * FROM customers WHERE id = ?", [$customerId]);
            if ($customer && $customer['balance'] < 0) {
                // Deduct up to the debt amount
                $deductedFromBalance = min($totalAmount, abs($customer['balance']));
                $cashRefund = $totalAmount - $deductedFromBalance;
                
                // If full amount was deducted, it's 'deducted', otherwise 'mixed'
                if ($cashRefund > 0) {
                    $refundMethod = 'mixed';
                } else {
                    $refundMethod = 'deducted';
                }
            } else {
                // Customer has no debt, force cash
                $refundMethod = 'cash';
                $cashRefund = $totalAmount;
            }
        }
        
        // Get invoice warehouse
        $invoiceRow = getRow("SELECT warehouse_id FROM invoices WHERE id = ?", [$invoiceId]);
        $warehouseId = (int)($invoiceRow['warehouse_id'] ?? 1);

        // Insert return record with type = 'customer', user_id, and warehouse_id
        $returnId = insert(
            "INSERT INTO returns (return_number, type, original_invoice_id, customer_id, customer_name, return_date, total_amount, old_balance, deducted_from_balance, cash_refund, refund_method, handled_by, user_id, warehouse_id, notes)
             VALUES (?, 'customer', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$returnNumber, $invoiceId, $customerId, $customerName, $returnDate, $totalAmount, $customerOldBalance, $deductedFromBalance, $cashRefund, $refundMethod, $handledBy, $userId, $warehouseId, $notes]
        );
        
        // Insert return items and update stock
        foreach ($items as $item) {
            insert(
                "INSERT INTO return_items (return_id, product_id, product_code, product_name, unit, quantity, unit_price, total)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$returnId, $item['product_id'], $item['code'], $item['name'], $item['unit'], $item['quantity'], $item['price'], $item['quantity'] * $item['price']]
            );
            
            // Return stock to the invoice's warehouse
            updateWarehouseStock($warehouseId, $item['product_id'], $item['quantity'], 'add');
        }
        
        // Update customer balance if deducted
        if ($customerId && $deductedFromBalance > 0) {
            execute(
                "UPDATE customers SET balance = balance + ? WHERE id = ?",
                [$deductedFromBalance, $customerId]
            );
            
            // *** IMPORTANT: Adjust active installment plans ***
            // When customer's debt reduces due to return, we need to reduce installment plan amounts
            $activePlans = getRows(
                "SELECT * FROM installment_plans WHERE entity_id = ? AND type = 'customer' AND status = 'active' ORDER BY created_at DESC",
                [$customerId]
            );
            
            $remainingCredit = $deductedFromBalance; // Amount to deduct from installments
            
            foreach ($activePlans as $plan) {
                if ($remainingCredit <= 0) break;
                
                // Amount to deduct from this plan
                $deductFromPlan = min($remainingCredit, $plan['remaining_amount']);
                
                if ($deductFromPlan > 0) {
                    // Update plan remaining amount and mark as completed if fully paid
                    $newRemaining = $plan['remaining_amount'] - $deductFromPlan;
                    $newPaid = $plan['paid_amount'] + $deductFromPlan;
                    $newStatus = $newRemaining <= 0 ? 'completed' : 'active';
                    
                    execute(
                        "UPDATE installment_plans SET remaining_amount = ?, paid_amount = ?, status = ? WHERE id = ?",
                        [max(0, $newRemaining), $newPaid, $newStatus, $plan['id']]
                    );
                    
                    // Adjust pending payments - mark some as paid with return credit
                    $pendingPayments = getRows(
                        "SELECT * FROM installment_payments WHERE plan_id = ? AND status IN ('pending', 'overdue') ORDER BY installment_number",
                        [$plan['id']]
                    );
                    
                    $creditToApply = $deductFromPlan;
                    foreach ($pendingPayments as $payment) {
                        if ($creditToApply <= 0) break;
                        
                        if ($creditToApply >= $payment['amount']) {
                            // Fully cover this payment
                            execute(
                                "UPDATE installment_payments SET status = 'paid', paid_date = CURDATE(), notes = CONCAT(IFNULL(notes, ''), ' - مرتجع') WHERE id = ?",
                                [$payment['id']]
                            );
                            $creditToApply -= $payment['amount'];
                        } else {
                            // Partially cover - reduce the amount
                            execute(
                                "UPDATE installment_payments SET amount = amount - ? WHERE id = ?",
                                [$creditToApply, $payment['id']]
                            );
                            $creditToApply = 0;
                        }
                    }
                    
                    $remainingCredit -= $deductFromPlan;
                    
                    logActivity('تعديل أقساط', "تم خصم {$deductFromPlan} من خطة التقسيط #{$plan['id']} بسبب مرتجع", $handledBy);
                }
            }
        }
        
        logActivity('إنشاء مرتجع', "تم إنشاء مرتجع رقم {$returnNumber} بقيمة {$totalAmount}", $handledBy);
        
        commit();
        
        if (isset($_POST['save_only'])) {
            setSuccess('تم حفظ المرتجع بنجاح');
            header("Location: index.php");
            exit;
        }
        
        header("Location: print.php?id=$returnId");
        exit;
        
    } catch (Exception $e) {
        rollback();
        setError($e->getMessage());
    }
}

// Get invoices for search
$invoices = getRows(
    "SELECT i.*, c.name as customer_name_db, c.balance as customer_balance
     FROM invoices i 
     LEFT JOIN customers c ON i.customer_id = c.id 
     WHERE i.type = 'sale' 
     ORDER BY i.created_at DESC 
     LIMIT 100"
);

// Check if coming from invoice link
$preSelectedInvoiceId = $_GET['invoice_id'] ?? '';

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<style>
.invoice-search-results {
    position: absolute;
    width: 100%;
    max-height: 150px;
    overflow-y: auto;
    background: white;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    z-index: 100;
    display: none;
}

.invoice-item {
    padding: 5px 8px;
    cursor: pointer;
    border-bottom: 1px solid #f3f4f6;
    font-size: 0.8em;
}

.invoice-item:hover {
    background: #eff6ff;
}

.selected-invoice {
    background: #f0fdf4;
    border: 1px solid #10b981;
    border-radius: 6px;
    padding: 6px 8px;
    margin-bottom: 6px;
}

.selected-invoice h3 {
    font-size: 0.9em;
    margin-bottom: 4px;
}

.items-to-return {
    margin-top: 6px;
}

.items-to-return h4 {
    font-size: 0.85em;
    margin-bottom: 4px;
}

.return-item-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px;
    background: #f9fafb;
    border-radius: 6px;
    margin-bottom: 8px;
    font-size: 0.88em;
}

.return-item-row input[type="checkbox"] {
    width: 18px;
    height: 18px;
}

.return-item-row input[type="number"] {
    width: 70px;
    padding: 5px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    font-size: 0.88em;
}

.summary-box {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    color: white;
    padding: 6px;
    border-radius: 6px;
    text-align: center;
    margin-top: 6px;
}

.summary-box div:first-child {
    font-size: 0.75em;
}

.summary-box div:last-child {
    font-size: 1.2em;
}

.refund-info {
    margin-top: 4px;
    padding: 5px;
    background: #fef3c7;
    border: 1px solid #f59e0b;
    border-radius: 4px;
    font-size: 0.8em;
}

.container {
    padding: 0px !important;
}

.card {
    margin-bottom: 0px !important;
    border-radius: 6px !important;
}

.card-header {
    padding: 5px 10px !important;
    font-size: 0.9em;
}

.card-body {
    padding: 5px 8px !important;
}

.form-group {
    margin-bottom: 5px !important;
}

.form-label {
    font-size: 0.8em;
    margin-bottom: 2px;
}

.form-control {
    padding: 4px 6px !important;
    font-size: 0.8em !important;
}

.form-row {
    gap: 8px !important;
    margin-bottom: 5px !important;
}

.grid-3 {
    gap: 8px !important;
    font-size: 0.8em;
    margin-top: 4px !important;
}

.btn-lg {
    padding: 6px 15px !important;
    font-size: 0.85em !important;
}

.btn-secondary {
    padding: 4px 10px !important;
    font-size: 0.8em !important;
}

#refundMethodSection {
    margin-top: 6px !important;
}

#refundMethodSection > div {
    gap: 5px !important;
    margin-top: 4px !important;
}

#refundMethodSection label {
    padding: 5px 8px !important;
    font-size: 0.75em;
    border-radius: 6px !important;
}

#refundMethodSection input[type="radio"] {
    width: 14px !important;
    height: 14px !important;
}

#refundBreakdown {
    padding: 5px !important;
    font-size: 0.8em;
    margin-top: 4px !important;
}

textarea.form-control {
    min-height: 30px !important;
    resize: none;
}

#customerBalanceInfo {
    padding: 5px 8px !important;
    margin-top: 4px !important;
}
</style>

<div class="container">
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>🔄 إنشاء مرتجع جديد</span>
            <a href="index.php" class="btn btn-secondary">← رجوع</a>
        </div>
        
        <div class="card-body">
            <form method="POST" id="returnForm">
                <!-- Search Invoice -->
                <div class="form-group" style="position: relative;">
                    <label class="form-label required">ابحث عن الفاتورة</label>
                    <input type="text" id="invoiceSearch" class="form-control" 
                           placeholder="🔍 ابحث برقم الفاتورة أو اسم العميل..." autocomplete="off">
                    <div id="invoiceResults" class="invoice-search-results"></div>
                </div>
                
                <!-- Selected Invoice Details -->
                <div id="selectedInvoice" style="display: none;">
                    <div class="selected-invoice">
                        <h3 id="invoiceTitle">الفاتورة المختارة</h3>
                        <input type="hidden" name="invoice_id" id="invoiceId">
                        <input type="hidden" name="customer_id" id="customerId">
                        <input type="hidden" name="customer_name" id="customerNameInput">
                        
                        <div class="grid grid-3" style="margin-top: 15px;">
                            <div><strong>رقم الفاتورة:</strong> <span id="invoiceNumber"></span></div>
                            <div><strong>العميل:</strong> <span id="customerName"></span></div>
                            <div><strong>التاريخ:</strong> <span id="invoiceDate"></span></div>
                        </div>
                        
                        <div id="customerBalanceInfo" class="refund-info" style="display: none;">
                            <strong>رصيد العميل:</strong> <span id="customerBalance"></span>
                            <br><small id="refundMethodNote"></small>
                        </div>
                    </div>
                    
                    <!-- Items to Return -->
                    <div class="items-to-return">
                        <h4>📦 اختر الأصناف للإرجاع:</h4>
                        <div id="itemsList"></div>
                    </div>
                    
                    <!-- Summary -->
                    <div class="summary-box">
                        <div style="font-size: 0.9em;">إجمالي المرتجع</div>
                        <div style="font-size: 2em; font-weight: bold;" id="totalReturn">0.00</div>
                    </div>
                    
                    <!-- Refund Method Selection -->
                    <div class="form-group" id="refundMethodSection" style="margin-top: 20px;">
                        <label class="form-label required">💰 طريقة الاسترداد</label>
                        <div style="display: flex; gap: 15px; margin-top: 10px; flex-wrap: wrap;">
                            <label style="display: flex; align-items: center; gap: 8px; padding: 12px 20px; background: #d1fae5; border: 2px solid #10b981; border-radius: 10px; cursor: pointer;">
                                <input type="radio" name="refund_method" value="cash" checked style="width: 20px; height: 20px;">
                                <span>💵 كاش كامل</span>
                            </label>
                            <label id="mixedOption" style="display: flex; align-items: center; gap: 8px; padding: 12px 20px; background: #fef3c7; border: 2px solid #f59e0b; border-radius: 10px; cursor: pointer; opacity: 0.5;" title="خصم الدين + الباقي كاش">
                                <input type="radio" name="refund_method" value="mixed" id="mixedRadio" disabled style="width: 20px; height: 20px;">
                                <span>📝💵 خصم الدين + الباقي كاش</span>
                            </label>
                            <label id="deductedOption" style="display: flex; align-items: center; gap: 8px; padding: 12px 20px; background: #dbeafe; border: 2px solid #3b82f6; border-radius: 10px; cursor: pointer; opacity: 0.5;" title="خصم كامل من الحساب">
                                <input type="radio" name="refund_method" value="deducted" id="deductedRadio" disabled style="width: 20px; height: 20px;">
                                <span>📝 خصم كامل من الحساب</span>
                            </label>
                        </div>
                        <div id="refundBreakdown" style="display: none; margin-top: 15px; padding: 15px; background: #f0fdf4; border: 1px solid #10b981; border-radius: 8px;">
                        </div>
                        <small id="refundMethodHelp" style="display: block; margin-top: 10px; color: #6b7280;"></small>
                    </div>
                    
                    <!-- Other fields -->
                    <div class="form-row" style="margin-top: 20px;">
                        <div class="form-group">
                            <label class="form-label required">تاريخ المرتجع</label>
                            <input type="date" name="return_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">المسؤول</label>
                            <input type="text" name="handled_by" class="form-control" value="<?php echo htmlspecialchars($_SESSION['full_name'] ?? 'المدير'); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">ملاحظات</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                    
                    <input type="hidden" name="return_items" id="returnItemsData">
                    
                <div class="d-flex gap-2" style="width: 100%;">
                    <button type="submit" name="save_and_print" class="btn btn-primary btn-lg" style="flex: 1;">🖨️ حفظ وطباعة الإيصال</button>
                    <button type="submit" name="save_only" class="btn btn-success btn-lg" style="flex: 1;">💾 حفظ فقط</button>
                </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const invoices = <?php echo json_encode($invoices); ?>;
const preSelectedInvoiceId = <?php echo $preSelectedInvoiceId ? $preSelectedInvoiceId : 'null'; ?>;
let selectedItems = [];
let customerBalance = 0;

// Auto-select invoice if coming from link
document.addEventListener('DOMContentLoaded', function() {
    if (preSelectedInvoiceId) {
        const invoice = invoices.find(inv => inv.id == preSelectedInvoiceId);
        if (invoice) {
            selectInvoice(invoice);
        }
    }
});

// Invoice search
document.getElementById('invoiceSearch').addEventListener('input', function() {
    const query = this.value.toLowerCase().trim();
    const results = document.getElementById('invoiceResults');
    
    if (query.length < 1) {
        results.style.display = 'none';
        return;
    }
    
    const matches = invoices.filter(inv => 
        inv.invoice_number.toLowerCase().includes(query) ||
        (inv.customer_name_db || inv.customer_name || '').toLowerCase().includes(query) ||
        (inv.customer_phone || '').includes(query)
    ).slice(0, 10);
    
    if (matches.length > 0) {
        results.innerHTML = matches.map(inv => `
            <div class="invoice-item" onclick='selectInvoice(${JSON.stringify(inv)})'>
                <strong>${inv.invoice_number}</strong> - ${inv.customer_name_db || inv.customer_name || 'عميل'}<br>
                <small>التاريخ: ${inv.date} | الإجمالي: ${inv.total_amount}</small>
            </div>
        `).join('');
        results.style.display = 'block';
    } else {
        results.innerHTML = '<div class="invoice-item">لا توجد نتائج</div>';
        results.style.display = 'block';
    }
});

function selectInvoice(invoice) {
    document.getElementById('invoiceResults').style.display = 'none';
    document.getElementById('selectedInvoice').style.display = 'block';
    
    document.getElementById('invoiceId').value = invoice.id;
    document.getElementById('customerId').value = invoice.customer_id || '';
    document.getElementById('customerNameInput').value = invoice.customer_name_db || invoice.customer_name || '';
    document.getElementById('invoiceNumber').textContent = invoice.invoice_number;
    document.getElementById('customerName').textContent = invoice.customer_name_db || invoice.customer_name || 'عميل';
    document.getElementById('invoiceDate').textContent = invoice.date;
    document.getElementById('invoiceTitle').textContent = 'الفاتورة: ' + invoice.invoice_number;
    
    // Load invoice items
    loadInvoiceItems(invoice.id);
    
    // Check customer balance
    if (invoice.customer_id) {
        checkCustomerBalance(invoice.customer_id);
    }
}

function loadInvoiceItems(invoiceId) {
    fetch(`../invoices/ajax.php?action=get_items&invoice_id=${invoiceId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderItems(data.items);
            }
        });
}

function renderItems(items) {
    const container = document.getElementById('itemsList');
    container.innerHTML = items.map((item, index) => {
        const isZero = item.available_quantity <= 0;
        const opacity = isZero ? '0.5' : '1';
        const disabledAttr = isZero ? 'disabled' : '';
        const checkboxHtml = isZero ? 
            `<input type="checkbox" disabled>` : 
            `<input type="checkbox" id="item_${index}" onchange="updateSelection()">`;
        const qtyValue = isZero ? 0 : 1;
        const qtyMin = isZero ? 0 : 1;
        
        return `
        <div class="return-item-row" style="opacity: ${opacity};">
            ${checkboxHtml}
            <div style="flex: 1; font-size: 1.1em; line-height: 1.6;">
                <strong>${item.product_name}</strong> (${item.product_code})<br>
                <span style="color: #4b5563;">الوحدة: ${item.unit} | السعر الأصلي: ${item.unit_price} | إجمالي الفاتورة: ${item.quantity} | </span><span style="color: #dc2626; font-weight: bold; background: #fee2e2; padding: 2px 6px; border-radius: 4px;">المتاح للإرجاع: ${item.available_quantity}</span>
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
                <div>
                    <label>الكمية:</label>
                    <input type="number" id="qty_${index}" min="${qtyMin}" max="${item.available_quantity}" value="${qtyValue}" 
                           onchange="updateSelection()" data-item='${JSON.stringify(item)}' style="width: 70px;" ${disabledAttr}>
                </div>
                <div>
                    <label>السعر:</label>
                    <input type="number" id="price_${index}" step="0.01" value="${item.unit_price}" 
                           onchange="updateSelection()" style="width: 90px;" ${disabledAttr}>
                </div>
            </div>
        </div>
    `}).join('');
}

function updateSelection() {
    selectedItems = [];
    let total = 0;
    
    document.querySelectorAll('.return-item-row').forEach((row, index) => {
        const checkbox = document.getElementById('item_' + index);
        const qtyInput = document.getElementById('qty_' + index);
        const priceInput = document.getElementById('price_' + index);
        
        if (checkbox && checkbox.checked && qtyInput && priceInput) {
            const item = JSON.parse(qtyInput.dataset.item);
            const qty = parseInt(qtyInput.value) || 0;
            const price = parseFloat(priceInput.value) || 0;
            if (qty > 0 && price >= 0) {
                selectedItems.push({
                    product_id: item.product_id,
                    code: item.product_code,
                    name: item.product_name,
                    unit: item.unit,
                    quantity: qty,
                    price: price
                });
                total += qty * price;
            }
        }
    });
    
    document.getElementById('totalReturn').textContent = total.toFixed(2);
    document.getElementById('returnItemsData').value = JSON.stringify(selectedItems);
    
    // Update refund breakdown
    updateRefundBreakdown();
}

function checkCustomerBalance(customerId) {
    fetch(`../customers/ajax.php?action=get_balance&customer_id=${customerId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                customerBalance = parseFloat(data.balance);
                const balanceInfo = document.getElementById('customerBalanceInfo');
                const balanceSpan = document.getElementById('customerBalance');
                const deductedOption = document.getElementById('deductedOption');
                const deductedRadio = document.getElementById('deductedRadio');
                const mixedOption = document.getElementById('mixedOption');
                const mixedRadio = document.getElementById('mixedRadio');
                const helpText = document.getElementById('refundMethodHelp');
                
                if (customerBalance < 0) {
                    // Customer owes money - enable deduction options
                    balanceSpan.innerHTML = `<span style="color: #dc2626;">${Math.abs(customerBalance).toFixed(2)} جنيه (مدين)</span>`;
                    deductedOption.style.opacity = '1';
                    deductedRadio.disabled = false;
                    mixedOption.style.opacity = '1';
                    mixedRadio.disabled = false;
                    helpText.innerHTML = `<span style="color: #059669;">✅ العميل مدين بـ ${Math.abs(customerBalance).toFixed(2)} جنيه - اختر طريقة الاسترداد</span>`;
                } else {
                    // Customer has no debt - disable deduction
                    balanceSpan.innerHTML = `<span style="color: #10b981;">${customerBalance.toFixed(2)} جنيه</span>`;
                    deductedOption.style.opacity = '0.5';
                    deductedRadio.disabled = true;
                    mixedOption.style.opacity = '0.5';
                    mixedRadio.disabled = true;
                    deductedRadio.checked = false;
                    mixedRadio.checked = false;
                    document.querySelector('input[value="cash"]').checked = true;
                    helpText.innerHTML = `<span style="color: #6b7280;">ℹ️ العميل ليس مدين - سيتم تسليم المبلغ كاش</span>`;
                }
                balanceInfo.style.display = 'block';
                updateRefundBreakdown();
            }
        })
        .catch(() => {
            // If ajax fails, continue without balance check
        });
}

function updateRefundBreakdown() {
    const breakdown = document.getElementById('refundBreakdown');
    let selectedMethod = document.querySelector('input[name="refund_method"]:checked')?.value || 'cash';
    const total = parseFloat(document.getElementById('totalReturn').textContent) || 0;
    const debt = Math.abs(customerBalance);
    
    const deductedRadio = document.getElementById('deductedRadio');
    const deductedOption = document.getElementById('deductedOption');
    const mixedRadio = document.getElementById('mixedRadio');
    
    if (total <= 0) {
        breakdown.style.display = 'none';
        return;
    }
    
    // Auto-correct selection if trying to fully deduct when total > debt
    if (customerBalance < 0 && total > debt) {
        deductedRadio.disabled = true;
        deductedOption.style.opacity = '0.5';
        if (selectedMethod === 'deducted') {
            mixedRadio.checked = true;
            selectedMethod = 'mixed';
        }
    } else if (customerBalance < 0) {
        deductedRadio.disabled = false;
        deductedOption.style.opacity = '1';
    }
    
    if (selectedMethod === 'cash') {
        breakdown.innerHTML = `<strong>💵 كاش:</strong> ${total.toFixed(2)} جنيه`;
        breakdown.style.display = 'block';
    } else if (selectedMethod === 'mixed' && customerBalance < 0) {
        const deducted = Math.min(total, debt);
        const cash = total - deducted;
        breakdown.innerHTML = `
            <strong>📝 خصم من الحساب:</strong> ${deducted.toFixed(2)} جنيه<br>
            <strong>💵 كاش للعميل:</strong> ${cash.toFixed(2)} جنيه
        `;
        breakdown.style.display = 'block';
    } else if (selectedMethod === 'deducted' && customerBalance < 0) {
        const deducted = Math.min(total, debt);
        breakdown.innerHTML = `<strong>📝 خصم من الحساب:</strong> ${deducted.toFixed(2)} جنيه`;
        breakdown.style.display = 'block';
    } else {
        breakdown.style.display = 'none';
    }
}

// Listen for refund method changes
document.querySelectorAll('input[name="refund_method"]').forEach(radio => {
    radio.addEventListener('change', updateRefundBreakdown);
});

// Form submit validation
document.getElementById('returnForm').addEventListener('submit', function(e) {
    if (selectedItems.length === 0) {
        e.preventDefault();
        alert('يرجى اختيار صنف واحد على الأقل للإرجاع');
    }
});

// Hide dropdown on outside click
document.addEventListener('click', function(e) {
    if (!e.target.closest('.form-group')) {
        document.getElementById('invoiceResults').style.display = 'none';
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
