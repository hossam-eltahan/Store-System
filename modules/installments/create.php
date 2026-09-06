<?php
/**
 * Create Installment Plan - إنشاء خطة تقسيط
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requirePermission('installments.create');

$pageTitle = 'إنشاء قسط جديد';
$type = $_GET['type'] ?? 'customer'; // customer or supplier
$entityId = $_GET['entity_id'] ?? '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        beginTransaction();
        
        $type = $_POST['type'];
        $entityId = $_POST['entity_id'];
        $entityName = sanitize($_POST['entity_name']);
        $totalAmount = floatval($_POST['total_amount']);
        $numberOfInstallments = intval($_POST['number_of_installments']);
        $startDate = $_POST['start_date'];
        $notes = sanitize($_POST['notes'] ?? '');
        
        // Validation
        if ($totalAmount <= 0) {
            throw new Exception('المبلغ يجب أن يكون أكبر من صفر');
        }
        if ($numberOfInstallments < 1 || $numberOfInstallments > 60) {
            throw new Exception('عدد الأقساط يجب أن يكون بين 1 و 60');
        }
        
        // Check entity exists and has balance
        if ($type === 'customer') {
            $entity = getRow("SELECT * FROM customers WHERE id = ?", [$entityId]);
            if (!$entity) throw new Exception('العميل غير موجود');
            
            $debt = abs($entity['balance'] < 0 ? $entity['balance'] : 0);
            
            // Check existing active installments for this customer
            $existingInstallments = getRow(
                "SELECT COALESCE(SUM(remaining_amount), 0) as total_remaining FROM installment_plans WHERE type = 'customer' AND entity_id = ? AND status = 'active'",
                [$entityId]
            );
            $alreadyScheduled = $existingInstallments ? floatval($existingInstallments['total_remaining']) : 0;
            $availableForInstallment = $debt - $alreadyScheduled;
            
            if ($totalAmount > $availableForInstallment) {
                if ($alreadyScheduled > 0) {
                    throw new Exception("المبلغ ({$totalAmount}) أكبر من المتاح للتقسيط ({$availableForInstallment}). يوجد أقساط نشطة بمبلغ ({$alreadyScheduled})");
                } else {
                    throw new Exception("المبلغ ({$totalAmount}) أكبر من المستحق على العميل ({$debt})");
                }
            }
        } else {
            $entity = getRow("SELECT * FROM suppliers WHERE id = ?", [$entityId]);
            if (!$entity) throw new Exception('المورد غير موجود');
            
            $weOwe = $entity['balance'] > 0 ? $entity['balance'] : 0;
            
            // Check existing active installments for this supplier
            $existingInstallments = getRow(
                "SELECT COALESCE(SUM(remaining_amount), 0) as total_remaining FROM installment_plans WHERE type = 'supplier' AND entity_id = ? AND status = 'active'",
                [$entityId]
            );
            $alreadyScheduled = $existingInstallments ? floatval($existingInstallments['total_remaining']) : 0;
            $availableForInstallment = $weOwe - $alreadyScheduled;
            
            if ($totalAmount > $availableForInstallment) {
                if ($alreadyScheduled > 0) {
                    throw new Exception("المبلغ ({$totalAmount}) أكبر من المتاح للتقسيط ({$availableForInstallment}). يوجد أقساط نشطة بمبلغ ({$alreadyScheduled})");
                } else {
                    throw new Exception("المبلغ ({$totalAmount}) أكبر من المستحق للمورد ({$weOwe})");
                }
            }
        }
        
        // Calculate installment amount
        $installmentAmount = round($totalAmount / $numberOfInstallments, 2);
        $userId = getCurrentUserId();
        $handledBy = $_SESSION['full_name'] ?? 'المدير';
        
        // Create installment plan
        $planId = insert(
            "INSERT INTO installment_plans (type, entity_id, entity_name, total_amount, remaining_amount, number_of_installments, installment_amount, start_date, notes, user_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$type, $entityId, $entityName, $totalAmount, $totalAmount, $numberOfInstallments, $installmentAmount, $startDate, $notes, $userId]
        );
        
        // Create individual installment payments
        $currentDate = new DateTime($startDate);
        for ($i = 1; $i <= $numberOfInstallments; $i++) {
            $paymentNumber = 'INS-' . str_pad($planId, 5, '0', STR_PAD_LEFT) . '-' . $i;
            $amount = ($i == $numberOfInstallments) ? 
                ($totalAmount - ($installmentAmount * ($numberOfInstallments - 1))) : 
                $installmentAmount;
            
            insert(
                "INSERT INTO installment_payments (plan_id, payment_number, installment_number, amount, due_date, status)
                 VALUES (?, ?, ?, ?, ?, 'pending')",
                [$planId, $paymentNumber, $i, $amount, $currentDate->format('Y-m-d')]
            );
            
            $currentDate->modify('+1 month');
        }
        
        $typeText = $type === 'customer' ? 'العميل' : 'المورد';
        logActivity('إنشاء خطة تقسيط', "تم إنشاء خطة تقسيط لـ{$typeText} {$entityName} بمبلغ {$totalAmount} على {$numberOfInstallments} قسط", $handledBy);
        
        commit();
        
        setSuccess("تم إنشاء خطة التقسيط بنجاح - {$numberOfInstallments} قسط بـ {$installmentAmount} جنيه");
        header("Location: index.php");
        exit;
        
    } catch (Exception $e) {
        rollback();
        setError($e->getMessage());
    }
}

// Get customers or suppliers based on type
if ($type === 'customer') {
    $entities = getRows("SELECT * FROM customers WHERE balance < 0 ORDER BY name");
    $entityLabel = 'العميل';
    $balanceLabel = 'المستحق على العميل';
} else {
    $entities = getRows("SELECT * FROM suppliers WHERE balance > 0 ORDER BY name");
    $entityLabel = 'المورد';
    $balanceLabel = 'المستحق للمورد';
}

// Pre-selected entity
$preSelectedEntity = null;
if ($entityId) {
    if ($type === 'customer') {
        $preSelectedEntity = getRow("SELECT *, ABS(balance) as debt FROM customers WHERE id = ?", [$entityId]);
    } else {
        $preSelectedEntity = getRow("SELECT *, balance as debt FROM suppliers WHERE id = ?", [$entityId]);
    }
}

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<style>
.entity-search-results {
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
.entity-item {
    padding: 6px 10px;
    cursor: pointer;
    border-bottom: 1px solid #f3f4f6;
    font-size: 0.85em;
}
.entity-item:hover {
    background: #eff6ff;
}
.selected-entity {
    background: linear-gradient(135deg, #dbeafe, #eff6ff);
    border: 1px solid #3b82f6;
    border-radius: 8px;
    padding: 10px;
    margin-bottom: 10px;
}
.selected-entity h3 {
    font-size: 1em;
    margin-bottom: 6px;
}
.installment-preview {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    padding: 10px;
    border-radius: 8px;
    text-align: center;
    margin-top: 10px;
}
.installment-preview div:first-child {
    font-size: 0.8em;
}
.installment-preview div:nth-child(2) {
    font-size: 1.6em;
}
.type-selector {
    display: flex;
    gap: 10px;
    margin-bottom: 10px;
}
.type-btn {
    flex: 1;
    padding: 10px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    background: white;
    cursor: pointer;
    text-align: center;
    font-size: 0.9em;
    transition: all 0.3s;
}
.type-btn.active {
    border-color: #3b82f6;
    background: #eff6ff;
    color: #1e40af;
}
.type-btn:hover {
    border-color: #3b82f6;
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
#totalAmount {
    font-size: 1.1em !important;
}
</style>

<div class="container">
    <div class="card">
        <div class="card-header d-flex justify-between align-center" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white;">
            <span>📅 إنشاء خطة تقسيط جديدة</span>
            <a href="index.php" class="btn btn-secondary">← رجوع</a>
        </div>
        
        <div class="card-body">
            <!-- Type Selector -->
            <div class="type-selector">
                <div class="type-btn <?php echo $type === 'customer' ? 'active' : ''; ?>" 
                     onclick="changeType('customer')">
                    👤 تقسيط لعميل
                </div>
                <div class="type-btn <?php echo $type === 'supplier' ? 'active' : ''; ?>" 
                     onclick="changeType('supplier')">
                    🚚 تقسيط لمورد
                </div>
            </div>
            
            <form method="POST" id="installmentForm">
                <input type="hidden" name="type" value="<?php echo $type; ?>">
                
                <!-- Search Entity -->
                <div class="form-group" style="position: relative;" id="searchSection">
                    <label class="form-label required">ابحث عن <?php echo $entityLabel; ?></label>
                    <input type="text" id="entitySearch" class="form-control" 
                           placeholder="🔍 ابحث بالاسم أو رقم التليفون..." autocomplete="off"
                           style="border: 2px solid #8b5cf6;">
                    <div id="entityResults" class="entity-search-results"></div>
                </div>
                
                <!-- Selected Entity Details -->
                <div id="selectedEntity" style="display: <?php echo $preSelectedEntity ? 'block' : 'none'; ?>;">
                    <div class="selected-entity">
                        <h3 id="entityTitle"><?php echo $preSelectedEntity ? $entityLabel . ': ' . $preSelectedEntity['name'] : ''; ?></h3>
                        <input type="hidden" name="entity_id" id="entityId" value="<?php echo $preSelectedEntity['id'] ?? ''; ?>">
                        <input type="hidden" name="entity_name" id="entityNameInput" value="<?php echo $preSelectedEntity['name'] ?? ''; ?>">
                        
                        <div class="grid grid-2" style="margin-top: 6px;">
                            <div><strong>الاسم:</strong> <span id="entityName"><?php echo $preSelectedEntity['name'] ?? ''; ?></span></div>
                            <div><strong>التليفون:</strong> <span id="entityPhone"><?php echo $preSelectedEntity['phone'] ?? ''; ?></span></div>
                        </div>
                        
                        <div style="background: #fef3c7; border: 1px solid #f59e0b; border-radius: 6px; padding: 8px; text-align: center; margin-top: 8px;">
                            <div><?php echo $balanceLabel; ?></div>
                            <div style="font-size: 1.5em; font-weight: bold; color: #92400e;" id="entityBalance">
                                <?php echo $preSelectedEntity ? number_format($preSelectedEntity['debt'], 2) : '0.00'; ?>
                            </div>
                            <div>جنيه</div>
                        </div>
                    </div>
                    
                    <!-- Installment Details -->
                    <div class="form-group">
                        <label class="form-label required">💰 المبلغ المراد تقسيطه</label>
                        <input type="number" name="total_amount" id="totalAmount" class="form-control" 
                               step="0.01" min="1" required placeholder="أدخل المبلغ"
                               style="font-size: 1.3em; text-align: center; border: 2px solid #8b5cf6;">
                        <small id="maxAmountNote" style="color: #f59e0b;">⚠️ الحد الأقصى: <span id="maxAmount">0</span> جنيه</small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required">📊 عدد الأقساط</label>
                            <select name="number_of_installments" id="numberOfInstallments" class="form-control" required>
                                <?php for ($i = 2; $i <= 24; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo $i == 6 ? 'selected' : ''; ?>><?php echo $i; ?> أقساط</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label required">📅 تاريخ أول قسط</label>
                            <input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">ملاحظات</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="ملاحظات إضافية..."></textarea>
                    </div>
                    
                    <!-- Installment Preview -->
                    <div class="installment-preview" id="previewBox" style="display: none;">
                        <span>كل قسط سيكون </span>
                        <span style="font-size: 1.4em; font-weight: bold;" id="installmentAmount">0.00</span>
                        <span> جنيه شهرياً</span>
                    </div>
                    
                    <button type="submit" class="btn btn-success btn-lg" style="width: 100%; margin-top: 10px;">
                        💾 إنشاء خطة التقسيط
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const entities = <?php echo json_encode($entities); ?>;
const preSelectedEntity = <?php echo $preSelectedEntity ? json_encode($preSelectedEntity) : 'null'; ?>;
let currentMaxAmount = 0;

function changeType(type) {
    window.location.href = 'create.php?type=' + type;
}

// Auto-select entity if coming from link
document.addEventListener('DOMContentLoaded', function() {
    if (preSelectedEntity) {
        currentMaxAmount = parseFloat(preSelectedEntity.debt) || 0;
        document.getElementById('maxAmount').textContent = currentMaxAmount.toFixed(2);
        document.getElementById('totalAmount').max = currentMaxAmount;
        document.getElementById('searchSection').style.display = 'none';
        updatePreview();
    }
});

// Entity search
document.getElementById('entitySearch').addEventListener('input', function() {
    const query = this.value.toLowerCase().trim();
    const results = document.getElementById('entityResults');
    
    if (query.length < 1) {
        results.style.display = 'none';
        return;
    }
    
    const matches = entities.filter(e => 
        e.name.toLowerCase().includes(query) ||
        (e.phone || '').includes(query)
    ).slice(0, 10);
    
    if (matches.length > 0) {
        results.innerHTML = matches.map(e => `
            <div class="entity-item" onclick='selectEntity(${JSON.stringify(e)})'>
                <strong>${e.name}</strong> - ${e.phone || 'بدون تليفون'}<br>
                <small style="color: #f59e0b;">المستحق: ${Math.abs(e.balance).toFixed(2)} جنيه</small>
            </div>
        `).join('');
        results.style.display = 'block';
    } else {
        results.innerHTML = '<div class="entity-item">لا توجد نتائج</div>';
        results.style.display = 'block';
    }
});

function selectEntity(entity) {
    document.getElementById('entityResults').style.display = 'none';
    document.getElementById('searchSection').style.display = 'none';
    document.getElementById('selectedEntity').style.display = 'block';
    
    document.getElementById('entityId').value = entity.id;
    document.getElementById('entityNameInput').value = entity.name;
    document.getElementById('entityName').textContent = entity.name;
    document.getElementById('entityPhone').textContent = entity.phone || '-';
    document.getElementById('entityTitle').textContent = '<?php echo $entityLabel; ?>: ' + entity.name;
    
    currentMaxAmount = Math.abs(parseFloat(entity.balance));
    document.getElementById('entityBalance').textContent = currentMaxAmount.toFixed(2);
    document.getElementById('maxAmount').textContent = currentMaxAmount.toFixed(2);
    document.getElementById('totalAmount').max = currentMaxAmount;
    
    document.getElementById('totalAmount').focus();
}

// Update preview
document.getElementById('totalAmount').addEventListener('input', updatePreview);
document.getElementById('numberOfInstallments').addEventListener('change', updatePreview);

function updatePreview() {
    const total = parseFloat(document.getElementById('totalAmount').value) || 0;
    const num = parseInt(document.getElementById('numberOfInstallments').value) || 1;
    
    if (total > 0 && num > 0) {
        const installment = (total / num).toFixed(2);
        document.getElementById('installmentAmount').textContent = installment;
        document.getElementById('previewBox').style.display = 'block';
    } else {
        document.getElementById('previewBox').style.display = 'none';
    }
}

// Hide dropdown on outside click
document.addEventListener('click', function(e) {
    if (!e.target.closest('.form-group')) {
        document.getElementById('entityResults').style.display = 'none';
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
