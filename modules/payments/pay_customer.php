<?php
/**
 * Customer Payment - تسجيل دفعة من عميل
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';

$pageTitle = 'دفع مستحقات عميل';

// Generate payment number
function generatePaymentNumber() {
    $lastPayment = getRow("SELECT payment_number FROM payments ORDER BY id DESC LIMIT 1");
    if ($lastPayment) {
        $num = intval(substr($lastPayment['payment_number'], 4)) + 1;
    } else {
        $num = 1;
    }
    return 'PAY-' . str_pad($num, 5, '0', STR_PAD_LEFT);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        beginTransaction();
        
        $paymentNumber = generatePaymentNumber();
        $customerId = $_POST['customer_id'];
        $customerName = sanitize($_POST['customer_name']);
        $amount = floatval($_POST['amount']);
        $paymentDate = $_POST['payment_date'];
        $paymentMethod = sanitize($_POST['payment_method'] ?? 'كاش');
        $reference = sanitize($_POST['reference'] ?? '');
        $notes = sanitize($_POST['notes'] ?? '');
        $handledBy = sanitize($_POST['handled_by'] ?? 'المدير');
        
        if ($amount <= 0) {
            throw new Exception('المبلغ يجب أن يكون أكبر من صفر');
        }
        
        // Get customer current balance
        $customer = getRow("SELECT * FROM customers WHERE id = ?", [$customerId]);
        if (!$customer) {
            throw new Exception('العميل غير موجود');
        }
        
        $oldBalance = floatval($customer['balance']);
        
        // Validate: customer must owe money (negative balance)
        if ($oldBalance >= 0) {
            throw new Exception('العميل ليس عليه مستحقات');
        }
        
        // Validate: can't pay more than owed
        $amountOwed = abs($oldBalance);
        if ($amount > $amountOwed) {
            throw new Exception('المبلغ المدفوع (' . $amount . ') أكبر من المستحق (' . $amountOwed . ')');
        }
        
        $newBalance = $oldBalance + $amount; // Add because negative balance = debt
        
        // Insert payment record
        $paymentId = insert(
            "INSERT INTO payments (payment_number, type, entity_id, entity_name, amount, old_balance, new_balance, payment_date, payment_method, reference, notes, handled_by)
             VALUES (?, 'customer', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$paymentNumber, $customerId, $customerName, $amount, $oldBalance, $newBalance, $paymentDate, $paymentMethod, $reference, $notes, $handledBy]
        );
        
        // Update customer balance
        execute(
            "UPDATE customers SET balance = balance + ? WHERE id = ?",
            [$amount, $customerId]
        );
        
        // Apply payment to customer's active installment plans
        $activePlans = getRows(
            "SELECT * FROM installment_plans WHERE type = 'customer' AND entity_id = ? AND status = 'active' ORDER BY created_at ASC",
            [$customerId]
        );
        
        $remainingPayment = $amount;
        foreach ($activePlans as $plan) {
            if ($remainingPayment <= 0) break;
            
            // Get pending payments for this plan
            $pendingPayments = getRows(
                "SELECT * FROM installment_payments WHERE plan_id = ? AND status IN ('pending', 'overdue') ORDER BY installment_number",
                [$plan['id']]
            );
            
            foreach ($pendingPayments as $payment) {
                if ($remainingPayment <= 0) break;
                
                if ($remainingPayment >= $payment['amount']) {
                    // Full payment for this installment
                    execute(
                        "UPDATE installment_payments SET status = 'paid', paid_date = CURDATE(), payment_method = ?, notes = CONCAT(IFNULL(notes, ''), ' - مدفوع من تحصيل عميل') WHERE id = ?",
                        [$paymentMethod, $payment['id']]
                    );
                    $remainingPayment -= $payment['amount'];
                } else {
                    // Partial payment - reduce amount and mark as paid, adjust remaining
                    $paidPart = $remainingPayment;
                    $newAmount = $payment['amount'] - $paidPart;
                    
                    execute(
                        "UPDATE installment_payments SET amount = ?, status = 'paid', paid_date = CURDATE(), notes = CONCAT(IFNULL(notes, ''), ' - دفعة جزئية: ', ?) WHERE id = ?",
                        [$paidPart, $amount, $payment['id']]
                    );
                    
                    // Adjust next installment if exists
                    $nextPayment = getRow(
                        "SELECT * FROM installment_payments WHERE plan_id = ? AND status = 'pending' AND installment_number > ? ORDER BY installment_number LIMIT 1",
                        [$plan['id'], $payment['installment_number']]
                    );
                    if ($nextPayment) {
                        execute("UPDATE installment_payments SET amount = amount + ? WHERE id = ?", [$newAmount, $nextPayment['id']]);
                    }
                    
                    $remainingPayment = 0;
                }
            }
            
            // Update plan totals
            $newPaidAmount = $plan['paid_amount'] + min($amount, $plan['remaining_amount']);
            $newRemainingAmount = $plan['remaining_amount'] - min($amount, $plan['remaining_amount']);
            $newStatus = $newRemainingAmount <= 0 ? 'completed' : 'active';
            
            execute(
                "UPDATE installment_plans SET paid_amount = ?, remaining_amount = ?, status = ? WHERE id = ?",
                [$newPaidAmount, max(0, $newRemainingAmount), $newStatus, $plan['id']]
            );
        }
        
        logActivity('دفع مستحقات عميل', "تم تسجيل دفعة من العميل {$customerName} بمبلغ {$amount}", $handledBy);
        
        commit();
        
        header("Location: print_customer.php?id=$paymentId");
        exit;
        
    } catch (Exception $e) {
        rollback();
        setError($e->getMessage());
    }
}

// Get customers with debts (negative balance)
$customers = getRows("SELECT * FROM customers WHERE balance < 0 ORDER BY name");

// Pre-select customer if coming from link
$preSelectedCustomerId = $_GET['customer_id'] ?? '';
$preSelectedCustomer = null;
if ($preSelectedCustomerId) {
    $preSelectedCustomer = getRow("SELECT * FROM customers WHERE id = ?", [$preSelectedCustomerId]);
}

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<style>
.customer-search-results {
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
.customer-item {
    padding: 6px 10px;
    cursor: pointer;
    border-bottom: 1px solid #f3f4f6;
    font-size: 0.85em;
}
.customer-item:hover {
    background: #eff6ff;
}
.selected-customer {
    background: linear-gradient(135deg, #fef3c7, #fef9c3);
    border: 1px solid #f59e0b;
    border-radius: 8px;
    padding: 10px;
    margin-bottom: 10px;
}
.selected-customer h3 {
    font-size: 1em;
    margin-bottom: 5px;
}
.balance-box {
    background: #fee2e2;
    border: 1px solid #dc2626;
    border-radius: 6px;
    padding: 8px;
    text-align: center;
    margin-top: 8px;
}
.balance-box .amount {
    font-size: 1.5em;
    font-weight: bold;
    color: #dc2626;
}
.payment-summary {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    padding: 8px;
    border-radius: 8px;
    text-align: center;
    margin-top: 10px;
}
.payment-summary div:nth-child(2) {
    font-size: 1.4em;
}

.container {
    padding: 0px !important;
}
.card {
    margin-bottom: 0px !important;
    border-radius: 6px !important;
}
.card-header {
    padding: 6px 10px !important;
    font-size: 0.9em;
}
.card-body {
    padding: 8px 10px !important;
}
.form-group {
    margin-bottom: 8px !important;
}
.form-label {
    font-size: 0.85em;
    margin-bottom: 3px;
}
.form-control {
    padding: 5px 8px !important;
    font-size: 0.85em !important;
}
.form-row {
    gap: 10px !important;
    margin-bottom: 8px !important;
}
.grid-2 {
    gap: 10px !important;
    font-size: 0.85em;
    margin-top: 6px !important;
}
.btn-lg {
    padding: 8px 20px !important;
    font-size: 0.9em !important;
    margin-top: 10px !important;
}
.btn-secondary {
    padding: 4px 10px !important;
    font-size: 0.8em !important;
}
textarea.form-control {
    min-height: 35px !important;
    resize: none;
}
#paymentAmount {
    font-size: 1.2em !important;
}
</style>

<div class="container">
    <div class="card">
        <div class="card-header d-flex justify-between align-center" style="background: linear-gradient(135deg, #f59e0b, #d97706); color: white;">
            <span>💵 دفع مستحقات عميل</span>
            <a href="../customers/index.php" class="btn btn-secondary">← رجوع</a>
        </div>
        
        <div class="card-body">
            <form method="POST" id="paymentForm">
                <!-- Search Customer -->
                <div class="form-group" style="position: relative;" id="searchSection">
                    <label class="form-label required">ابحث عن العميل</label>
                    <input type="text" id="customerSearch" class="form-control" 
                           placeholder="🔍 ابحث باسم العميل أو رقم التليفون..." autocomplete="off"
                           style="border: 2px solid #f59e0b;">
                    <div id="customerResults" class="customer-search-results"></div>
                </div>
                
                <!-- Selected Customer Details -->
                <div id="selectedCustomer" style="display: none;">
                    <div class="selected-customer">
                        <h3 id="customerTitle">العميل المختار</h3>
                        <input type="hidden" name="customer_id" id="customerId">
                        <input type="hidden" name="customer_name" id="customerNameInput">
                        
                        <div class="grid grid-2" style="margin-top: 6px;">
                            <div><strong>الاسم:</strong> <span id="customerName"></span></div>
                            <div><strong>التليفون:</strong> <span id="customerPhone"></span></div>
                        </div>
                        
                        <div class="balance-box">
                            <div>المستحق على العميل</div>
                            <div class="amount" id="customerBalance">0.00</div>
                            <div>جنيه</div>
                        </div>
                    </div>
                    
                    <!-- Payment Details -->
                    <div class="form-group">
                        <label class="form-label required">💰 المبلغ المدفوع</label>
                        <input type="number" name="amount" id="paymentAmount" class="form-control" 
                               step="0.01" min="0.01" required placeholder="أدخل المبلغ"
                               style="font-size: 1.5em; text-align: center; border: 2px solid #10b981;">
                        <small id="maxAmountNote" style="color: #dc2626; display: none;">⚠️ الحد الأقصى: <span id="maxAmountValue">0</span> جنيه</small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required">تاريخ الدفع</label>
                            <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">طريقة الدفع</label>
                            <select name="payment_method" class="form-control">
                                <option value="كاش">كاش</option>
                                <option value="تحويل بنكي">تحويل بنكي</option>
                                <option value="شيك">شيك</option>
                                <option value="فودافون كاش">فودافون كاش</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">رقم المرجع (شيك/تحويل)</label>
                            <input type="text" name="reference" class="form-control" placeholder="اختياري">
                        </div>
                        <div class="form-group">
                            <label class="form-label">المسؤول</label>
                            <input type="text" name="handled_by" class="form-control" value="المدير">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">ملاحظات</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="ملاحظات إضافية..."></textarea>
                    </div>
                    
                    <!-- Summary -->
                    <div class="payment-summary" id="summaryBox" style="display: none;">
                        <span id="summaryText">الباقي بعد الدفع </span>
                        <span style="font-size: 1.4em; font-weight: bold;" id="remainingBalance">0.00</span>
                        <span> جنيه</span>
                    </div>
                    
                    <button type="submit" class="btn btn-success btn-lg" style="width: 100%; margin-top: 10px;">
                        💾 تسجيل الدفعة وطباعة الإيصال
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const customers = <?php echo json_encode($customers); ?>;
const preSelectedCustomer = <?php echo $preSelectedCustomer ? json_encode($preSelectedCustomer) : 'null'; ?>;
let currentBalance = 0;

// Auto-select customer if coming from link
document.addEventListener('DOMContentLoaded', function() {
    if (preSelectedCustomer) {
        selectCustomer(preSelectedCustomer);
    }
});

// Customer search
document.getElementById('customerSearch').addEventListener('input', function() {
    const query = this.value.toLowerCase().trim();
    const results = document.getElementById('customerResults');
    
    if (query.length < 1) {
        results.style.display = 'none';
        return;
    }
    
    const matches = customers.filter(c => 
        c.name.toLowerCase().includes(query) ||
        (c.phone || '').includes(query)
    ).slice(0, 10);
    
    if (matches.length > 0) {
        results.innerHTML = matches.map(c => `
            <div class="customer-item" onclick='selectCustomer(${JSON.stringify(c)})'>
                <strong>${c.name}</strong> - ${c.phone || 'بدون تليفون'}<br>
                <small style="color: #dc2626;">المستحق: ${Math.abs(c.balance).toFixed(2)} جنيه</small>
            </div>
        `).join('');
        results.style.display = 'block';
    } else {
        results.innerHTML = '<div class="customer-item">لا توجد نتائج</div>';
        results.style.display = 'block';
    }
});

function selectCustomer(customer) {
    document.getElementById('customerResults').style.display = 'none';
    document.getElementById('searchSection').style.display = 'none';
    document.getElementById('selectedCustomer').style.display = 'block';
    
    document.getElementById('customerId').value = customer.id;
    document.getElementById('customerNameInput').value = customer.name;
    document.getElementById('customerName').textContent = customer.name;
    document.getElementById('customerPhone').textContent = customer.phone || '-';
    document.getElementById('customerTitle').textContent = 'العميل: ' + customer.name;
    
    currentBalance = Math.abs(parseFloat(customer.balance));
    document.getElementById('customerBalance').textContent = currentBalance.toFixed(2);
    
    // Set max amount
    document.getElementById('paymentAmount').max = currentBalance;
    document.getElementById('maxAmountValue').textContent = currentBalance.toFixed(2);
    document.getElementById('maxAmountNote').style.display = 'block';
    
    // Focus on amount
    document.getElementById('paymentAmount').focus();
}

// Update remaining balance on amount change
document.getElementById('paymentAmount').addEventListener('input', function() {
    const amount = parseFloat(this.value) || 0;
    const remaining = currentBalance - amount;
    
    const summaryBox = document.getElementById('summaryBox');
    const remainingEl = document.getElementById('remainingBalance');
    
    // Validate max amount
    if (amount > currentBalance) {
        this.style.borderColor = '#dc2626';
        summaryBox.style.display = 'block';
        summaryBox.style.background = 'linear-gradient(135deg, #dc2626, #b91c1c)';
        document.getElementById('summaryText').textContent = '❌ المبلغ أكبر من المستحق! ';
        remainingEl.textContent = '---';
        return;
    }
    
    this.style.borderColor = '#10b981';
    
    if (amount > 0) {
        summaryBox.style.display = 'block';
        remainingEl.textContent = remaining > 0 ? remaining.toFixed(2) : '0.00';
        
        if (remaining <= 0) {
            summaryBox.style.background = 'linear-gradient(135deg, #10b981, #059669)';
            document.getElementById('summaryText').textContent = '✅ تم سداد كامل المستحقات! ';
        } else {
            summaryBox.style.background = 'linear-gradient(135deg, #f59e0b, #d97706)';
            document.getElementById('summaryText').textContent = 'الباقي بعد الدفع ';
        }
    } else {
        summaryBox.style.display = 'none';
    }
});

// Form validation before submit
document.getElementById('paymentForm').addEventListener('submit', function(e) {
    const amount = parseFloat(document.getElementById('paymentAmount').value) || 0;
    if (amount > currentBalance) {
        e.preventDefault();
        alert('المبلغ المدفوع أكبر من المستحق على العميل!');
    }
});

// Quick amount buttons
function setAmount(amount) {
    document.getElementById('paymentAmount').value = amount;
    document.getElementById('paymentAmount').dispatchEvent(new Event('input'));
}

// Hide dropdown on outside click
document.addEventListener('click', function(e) {
    if (!e.target.closest('.form-group')) {
        document.getElementById('customerResults').style.display = 'none';
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
