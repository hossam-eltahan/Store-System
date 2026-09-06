<?php
/**
 * Supplier Payment - تسجيل دفعة للمورد
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requirePermission('payments.supplier');

$pageTitle = 'دفع مستحقات مورد';

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
        $supplierId = $_POST['supplier_id'];
        $supplierName = sanitize($_POST['supplier_name']);
        $amount = floatval($_POST['amount']);
        $paymentDate = $_POST['payment_date'];
        $paymentMethod = sanitize($_POST['payment_method'] ?? 'كاش');
        $reference = sanitize($_POST['reference'] ?? '');
        $notes = sanitize($_POST['notes'] ?? '');
        $handledBy = !empty($_POST['handled_by']) ? sanitize($_POST['handled_by']) : ($_SESSION['full_name'] ?? 'المدير');
        $userId = getCurrentUserId();
        
        if ($amount <= 0) {
            throw new Exception('المبلغ يجب أن يكون أكبر من صفر');
        }
        
        // Get supplier current balance
        $supplier = getRow("SELECT * FROM suppliers WHERE id = ?", [$supplierId]);
        if (!$supplier) {
            throw new Exception('المورد غير موجود');
        }
        
        $oldBalance = floatval($supplier['balance']);
        
        // Validate: supplier must have balance owed (positive balance)
        if ($oldBalance <= 0) {
            throw new Exception('المورد ليس له مستحقات');
        }
        
        // Validate: can't pay more than owed
        if ($amount > $oldBalance) {
            throw new Exception('المبلغ المدفوع (' . $amount . ') أكبر من المستحق (' . $oldBalance . ')');
        }
        
        $newBalance = $oldBalance - $amount; // Subtract because positive balance = we owe them
        
        // Insert payment record with user_id
        $paymentId = insert(
            "INSERT INTO payments (payment_number, type, entity_id, entity_name, amount, old_balance, new_balance, payment_date, payment_method, reference, notes, handled_by, user_id)
             VALUES (?, 'supplier', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$paymentNumber, $supplierId, $supplierName, $amount, $oldBalance, $newBalance, $paymentDate, $paymentMethod, $reference, $notes, $handledBy, $userId]
        );
        
        // Update supplier balance
        execute(
            "UPDATE suppliers SET balance = balance - ? WHERE id = ?",
            [$amount, $supplierId]
        );
        
        // Apply payment to supplier's active installment plans
        $activePlans = getRows(
            "SELECT * FROM installment_plans WHERE type = 'supplier' AND entity_id = ? AND status = 'active' ORDER BY created_at ASC",
            [$supplierId]
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
                        "UPDATE installment_payments SET status = 'paid', paid_date = CURDATE(), payment_method = ?, notes = CONCAT(IFNULL(notes, ''), ' - مدفوع من دفع مورد') WHERE id = ?",
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
        
        logActivity('دفع مستحقات مورد', "تم تسجيل دفعة للمورد {$supplierName} بمبلغ {$amount}", $handledBy);
        
        commit();
        
        if (isset($_POST['save_only'])) {
            setSuccess('تم تسجيل الدفعة بنجاح');
            header("Location: pay_supplier.php");
            exit;
        }

        header("Location: print_supplier.php?id=$paymentId");
        exit;
        
    } catch (Exception $e) {
        rollback();
        setError($e->getMessage());
    }
}

// Get suppliers with dues (positive balance = we owe them)
$suppliers = getRows("SELECT * FROM suppliers WHERE balance > 0 ORDER BY name");

// Pre-select supplier if coming from link
$preSelectedSupplierId = $_GET['supplier_id'] ?? '';
$preSelectedSupplier = null;
if ($preSelectedSupplierId) {
    $preSelectedSupplier = getRow("SELECT * FROM suppliers WHERE id = ?", [$preSelectedSupplierId]);
}

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<style>
.supplier-search-results {
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
.supplier-item {
    padding: 6px 10px;
    cursor: pointer;
    border-bottom: 1px solid #f3f4f6;
    font-size: 0.85em;
}
.supplier-item:hover {
    background: #ecfdf5;
}
.selected-supplier {
    background: linear-gradient(135deg, #d1fae5, #ecfdf5);
    border: 1px solid #10b981;
    border-radius: 8px;
    padding: 10px;
    margin-bottom: 10px;
}
.selected-supplier h3 {
    font-size: 1em;
    margin-bottom: 5px;
}
.balance-box {
    background: #fef3c7;
    border: 1px solid #f59e0b;
    border-radius: 6px;
    padding: 8px;
    text-align: center;
    margin-top: 8px;
}
.balance-box .amount {
    font-size: 1.5em;
    font-weight: bold;
    color: #92400e;
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
        <div class="card-header d-flex justify-between align-center" style="background: linear-gradient(135deg, #10b981, #059669); color: white;">
            <span>💵 دفع مستحقات مورد</span>
            <a href="../suppliers/index.php" class="btn btn-secondary">← رجوع</a>
        </div>
        
        <div class="card-body">
            <form method="POST" id="paymentForm">
                <!-- Search Supplier -->
                <div class="form-group" style="position: relative;" id="searchSection">
                    <label class="form-label required">ابحث عن المورد</label>
                    <input type="text" id="supplierSearch" class="form-control" 
                           placeholder="🔍 ابحث باسم المورد أو رقم التليفون..." autocomplete="off"
                           style="border: 2px solid #10b981;">
                    <div id="supplierResults" class="supplier-search-results"></div>
                </div>
                
                <!-- Selected Supplier Details -->
                <div id="selectedSupplier" style="display: none;">
                    <div class="selected-supplier">
                        <h3 id="supplierTitle">المورد المختار</h3>
                        <input type="hidden" name="supplier_id" id="supplierId">
                        <input type="hidden" name="supplier_name" id="supplierNameInput">
                        
                        <div class="grid grid-2" style="margin-top: 6px;">
                            <div><strong>الاسم:</strong> <span id="supplierName"></span></div>
                            <div><strong>التليفون:</strong> <span id="supplierPhone"></span></div>
                        </div>
                        
                        <div class="balance-box">
                            <div>المستحق للمورد (له عندنا)</div>
                            <div class="amount" id="supplierBalance">0.00</div>
                            <div>جنيه</div>
                        </div>
                    </div>
                    
                    <!-- Payment Details -->
                    <div class="form-group">
                        <label class="form-label required">💰 المبلغ المدفوع</label>
                        <input type="number" name="amount" id="paymentAmount" class="form-control" 
                               step="0.01" min="0.01" required placeholder="أدخل المبلغ"
                               style="font-size: 1.5em; text-align: center; border: 2px solid #10b981;">
                        <small id="maxAmountNote" style="color: #f59e0b; display: none;">⚠️ الحد الأقصى: <span id="maxAmountValue">0</span> جنيه</small>
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
                            <input type="text" name="handled_by" class="form-control" value="<?php echo htmlspecialchars($_SESSION['full_name'] ?? 'المدير'); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">ملاحظات</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="ملاحظات إضافية..."></textarea>
                    </div>
                    
                    <!-- Summary -->
                    <div class="payment-summary" id="summaryBox" style="display: none;">
                        <span id="summaryText">الباقي للمورد بعد الدفع </span>
                        <span style="font-size: 1.4em; font-weight: bold;" id="remainingBalance">0.00</span>
                        <span> جنيه</span>
                    </div>
                    
                    <div class="d-flex gap-2" style="margin-top: 10px;">
                        <button type="submit" name="save_and_print" class="btn btn-success btn-lg" style="flex: 1;">
                            🖨️ حفظ وطباعة
                        </button>
                        <button type="submit" name="save_only" class="btn btn-primary btn-lg" style="flex: 1;">
                            💾 حفظ فقط
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const suppliers = <?php echo json_encode($suppliers); ?>;
const preSelectedSupplier = <?php echo $preSelectedSupplier ? json_encode($preSelectedSupplier) : 'null'; ?>;
let currentBalance = 0;

// Auto-select supplier if coming from link
document.addEventListener('DOMContentLoaded', function() {
    if (preSelectedSupplier) {
        selectSupplier(preSelectedSupplier);
    }
});

// Supplier search
document.getElementById('supplierSearch').addEventListener('input', function() {
    const query = this.value.toLowerCase().trim();
    const results = document.getElementById('supplierResults');
    
    if (query.length < 1) {
        results.style.display = 'none';
        return;
    }
    
    const matches = suppliers.filter(s => 
        s.name.toLowerCase().includes(query) ||
        (s.phone || '').includes(query)
    ).slice(0, 10);
    
    if (matches.length > 0) {
        results.innerHTML = matches.map(s => `
            <div class="supplier-item" onclick='selectSupplier(${JSON.stringify(s)})'>
                <strong>${s.name}</strong> - ${s.phone || 'بدون تليفون'}<br>
                <small style="color: #f59e0b;">له عندنا: ${parseFloat(s.balance).toFixed(2)} جنيه</small>
            </div>
        `).join('');
        results.style.display = 'block';
    } else {
        results.innerHTML = '<div class="supplier-item">لا توجد نتائج</div>';
        results.style.display = 'block';
    }
});

function selectSupplier(supplier) {
    document.getElementById('supplierResults').style.display = 'none';
    document.getElementById('searchSection').style.display = 'none';
    document.getElementById('selectedSupplier').style.display = 'block';
    
    document.getElementById('supplierId').value = supplier.id;
    document.getElementById('supplierNameInput').value = supplier.name;
    document.getElementById('supplierName').textContent = supplier.name;
    document.getElementById('supplierPhone').textContent = supplier.phone || '-';
    document.getElementById('supplierTitle').textContent = 'المورد: ' + supplier.name;
    
    currentBalance = parseFloat(supplier.balance);
    document.getElementById('supplierBalance').textContent = currentBalance.toFixed(2);
    
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
            document.getElementById('summaryText').textContent = 'الباقي للمورد بعد الدفع ';
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
        alert('المبلغ المدفوع أكبر من المستحق للمورد!');
    }
});

// Hide dropdown on outside click
document.addEventListener('click', function(e) {
    if (!e.target.closest('.form-group')) {
        document.getElementById('supplierResults').style.display = 'none';
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
