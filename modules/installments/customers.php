<?php
/**
 * Customer Installments - List customers with active installments
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requirePermission('installments.view');

$pageTitle = 'سداد أقساط العملاء';

// Handle quick payment
if (isset($_POST['quick_pay'])) {
    requirePermission('installments.pay');
    try {
        beginTransaction();
        
        $customerId = $_POST['customer_id'];
        $amount = floatval($_POST['amount']);
        $installmentId = $_POST['installment_id'];
        $notes = sanitize($_POST['notes'] ?? '');
        
        // Get customer and installment info
        $installment = getRow("SELECT * FROM installments WHERE id = ?", [$installmentId]);
        $customer = getRow("SELECT * FROM customers WHERE id = ?", [$customerId]);
        
        if ($installment && $customer && $amount > 0) {
            // Find next pending payment
            $payment = getRow(
                "SELECT * FROM installment_payments WHERE installment_id = ? AND status = 'pending' ORDER BY payment_number LIMIT 1",
                [$installmentId]
            );
            
            if ($payment) {
                // Update payment record
                execute(
                    "UPDATE installment_payments SET paid_amount = ?, paid_date = CURDATE(), status = 'paid', notes = ? WHERE id = ?",
                    [$amount, $notes, $payment['id']]
                );
            }
            
            // Update installment remaining amount
            $newRemaining = max(0, $installment['remaining_amount'] - $amount);
            execute(
                "UPDATE installments SET remaining_amount = ?, paid_amount = paid_amount + ? WHERE id = ?",
                [$newRemaining, $amount, $installmentId]
            );
            
            // Update customer balance
            execute(
                "UPDATE customers SET balance = balance + ? WHERE id = ?",
                [$amount, $customerId]
            );
            
            // Check if fully paid
            if ($newRemaining <= 0) {
                execute("UPDATE installments SET status = 'completed' WHERE id = ?", [$installmentId]);
            }
            
            logActivity('سداد قسط', "تم سداد مبلغ $amount للعميل {$customer['name']}");
            
            commit();
            setSuccess('تم تسجيل الدفعة بنجاح - المبلغ: ' . formatCurrency($amount));
        }
        
    } catch (Exception $e) {
        rollback();
        setError('حدث خطأ: ' . $e->getMessage());
    }
    
    redirect('customers.php');
}

// Get all customers with active installments
$customersWithInstallments = getRows(
    "SELECT c.*, 
            COUNT(i.id) as installment_count,
            SUM(i.remaining_amount) as total_remaining,
            SUM(i.total_amount) as total_amount,
            SUM(i.paid_amount) as total_paid
     FROM customers c
     JOIN installments i ON c.id = i.customer_id AND i.status = 'active'
     GROUP BY c.id
     ORDER BY total_remaining DESC"
);

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>💰 سداد أقساط العملاء</span>
            <a href="index.php" class="btn btn-secondary">← كل الأقساط</a>
        </div>
        
        <div class="card-body">
            <!-- Search -->
            <div class="mb-2">
                <input type="text" id="customerSearch" class="form-control" placeholder="🔍 بحث بالاسم أو التليفون...">
            </div>
            
            <?php if (empty($customersWithInstallments)): ?>
            <div class="alert alert-success">🎉 لا يوجد عملاء عليهم أقساط حالياً</div>
            <?php else: ?>
            
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>العميل</th>
                            <th>التليفون</th>
                            <th>عدد الأقساط</th>
                            <th>إجمالي المبلغ</th>
                            <th>المدفوع</th>
                            <th>المتبقي</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="customersTableBody">
                        <?php foreach ($customersWithInstallments as $customer): ?>
                        <tr>
                            <td><strong><?php echo $customer['name']; ?></strong></td>
                            <td><?php echo $customer['phone']; ?></td>
                            <td><span class="badge badge-info"><?php echo $customer['installment_count']; ?></span></td>
                            <td><?php echo formatCurrency($customer['total_amount']); ?></td>
                            <td class="text-success"><?php echo formatCurrency($customer['total_paid']); ?></td>
                            <td><strong class="text-danger"><?php echo formatCurrency($customer['total_remaining']); ?></strong></td>
                            <td>
                                <button type="button" class="btn btn-success btn-sm" 
                                        onclick="showPaymentDetails(<?php echo $customer['id']; ?>, '<?php echo htmlspecialchars($customer['name']); ?>')">
                                    💰 سداد
                                </button>
                                <a href="../customers/view.php?id=<?php echo $customer['id']; ?>" class="btn btn-info btn-sm">👁️</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Customer Installments Details Modal -->
<div id="paymentModal" class="modal-overlay" style="display: none;">
    <div class="modal-box" style="max-width: 600px;">
        <div class="modal-header">
            <span style="font-size: 24px;">💰</span>
            <h3 id="modalCustomerName">سداد قسط للعميل</h3>
        </div>
        <div class="modal-body" id="modalBody">
            <div class="text-center">جاري التحميل...</div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal()">❌ إغلاق</button>
        </div>
    </div>
</div>

<style>
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    z-index: 9999;
    display: flex;
    justify-content: center;
    align-items: center;
    animation: fadeIn 0.2s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-box {
    background: white;
    border-radius: 15px;
    padding: 25px;
    width: 90%;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: slideDown 0.3s ease;
    max-height: 80vh;
    overflow-y: auto;
}

@keyframes slideDown {
    from { transform: translateY(-50px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.modal-header h3 {
    margin: 10px 0 0 0;
    color: #333;
}

.modal-body {
    margin: 20px 0;
    text-align: right;
}

.modal-footer {
    display: flex;
    gap: 10px;
    justify-content: center;
}

.installment-card {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 15px;
}

.installment-card h4 {
    margin: 0 0 10px 0;
    color: #333;
}

.btn-info {
    background: linear-gradient(135deg, #0ea5e9, #0284c7);
    color: white;
}
</style>

<script>
// Live Search
document.addEventListener('DOMContentLoaded', function() {
    var searchInput = document.getElementById('customerSearch');
    var tableBody = document.getElementById('customersTableBody');
    
    if (searchInput && tableBody) {
        searchInput.addEventListener('input', function() {
            var searchTerm = this.value.toLowerCase().trim();
            var rows = tableBody.querySelectorAll('tr');
            
            for (var i = 0; i < rows.length; i++) {
                var row = rows[i];
                var name = row.cells[0] ? row.cells[0].textContent.toLowerCase() : '';
                var phone = row.cells[1] ? row.cells[1].textContent.toLowerCase() : '';
                
                if (name.indexOf(searchTerm) !== -1 || phone.indexOf(searchTerm) !== -1) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });
    }
});

function showPaymentDetails(customerId, customerName) {
    document.getElementById('modalCustomerName').textContent = 'سداد أقساط: ' + customerName;
    document.getElementById('paymentModal').style.display = 'flex';
    
    // Load customer installments via AJAX
    fetch('ajax_installments.php?customer_id=' + customerId)
        .then(response => response.text())
        .then(html => {
            document.getElementById('modalBody').innerHTML = html;
        })
        .catch(err => {
            document.getElementById('modalBody').innerHTML = '<div class="alert alert-danger">حدث خطأ في التحميل</div>';
        });
}

function closeModal() {
    document.getElementById('paymentModal').style.display = 'none';
}

// Close modal on escape
document.onkeydown = function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
};

// Close on outside click
document.getElementById('paymentModal').onclick = function(e) {
    if (e.target === this) {
        closeModal();
    }
};
</script>

<?php include '../../includes/footer.php'; ?>
