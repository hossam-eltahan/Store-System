<?php
/**
 * Purchase Invoice - Buy from Suppliers
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requirePermission('invoices.purchase.create');

$pageTitle = 'فاتورة شراء جديدة';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        beginTransaction();
        
        // Generate invoice number
        $invoiceNumber = generateInvoiceNumber('purchase');
        
        // Get form data
        $supplierId = !empty($_POST['supplier_id']) ? $_POST['supplier_id'] : null;
        $supplierName = sanitize($_POST['supplier_name'] ?? '');
        $supplierPhone = sanitize($_POST['supplier_phone'] ?? '');
        $date = $_POST['date'];
        $warehouseId = (int)($_POST['warehouse_id'] ?? getCurrentWarehouseId());
        $discount = floatval($_POST['discount'] ?? 0);
        $paidAmount = floatval($_POST['paid_amount'] ?? 0);
        $paymentMethod = sanitize($_POST['payment_method'] ?? 'كاش');
        $handledBy = $_SESSION['full_name'] ?? 'المدير';
        $userId = getCurrentUserId();
        $notes = sanitize($_POST['notes'] ?? '');
        
        // Get supplier's old balance BEFORE this invoice
        $supplierOldBalance = 0;
        if ($supplierId) {
            $supplierData = getRow("SELECT balance FROM suppliers WHERE id = ?", [$supplierId]);
            $supplierOldBalance = floatval($supplierData['balance'] ?? 0);
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
            // Overpaying - check if we owe supplier (positive balance)
            $weOweSupplier = $supplierOldBalance > 0 ? $supplierOldBalance : 0;
            $maxPayment = $finalAmount + $weOweSupplier;
            
            if ($paidAmount > $maxPayment) {
                if ($weOweSupplier > 0) {
                    throw new Exception("المبلغ المدفوع ({$paidAmount}) يتجاوز قيمة الفاتورة ({$finalAmount}) + المستحق للمورد ({$weOweSupplier})");
                } else {
                    throw new Exception("المبلغ المدفوع ({$paidAmount}) لا يمكن أن يتجاوز قيمة الفاتورة ({$finalAmount}) لأنه لا يوجد حساب قديم مستحق للمورد");
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
        
        // Insert invoice with old_balance, user_id, and warehouse_id
        $invoiceId = insert(
            "INSERT INTO invoices (invoice_number, type, supplier_id, customer_name, customer_phone, date, total_amount, discount, paid_amount, remaining_amount, old_balance, payment_method, payment_status, handled_by, user_id, warehouse_id, notes) 
            VALUES (?, 'purchase', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$invoiceNumber, $supplierId, $supplierName, $supplierPhone, $date, $totalAmount, $discount, $paidAmount, $remainingAmount, $supplierOldBalance, $paymentMethod, $paymentStatus, $handledBy, $userId, $warehouseId, $notes]
        );
        
        // Insert items and INCREASE warehouse stock
        foreach ($items as $item) {
            insert(
                "INSERT INTO invoice_items (invoice_id, product_id, product_code, product_name, unit, quantity, unit_price, total) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$invoiceId, $item['product_id'], $item['code'], $item['name'], $item['unit'], $item['quantity'], $item['price'], $item['quantity'] * $item['price']]
            );
            
            // INCREASE stock in selected warehouse (and sync aggregate products.stock_quantity)
            updateWarehouseStock($warehouseId, $item['product_id'], $item['quantity'], 'add');
        }
        
        // Auto-add supplier if not registered and has phone number
        if (!$supplierId && $supplierPhone && $supplierName) {
            $existingSupplier = getRow("SELECT id, name FROM suppliers WHERE phone = ?", [$supplierPhone]);
            
            if ($existingSupplier) {
                // Phone belongs to existing supplier - throw error
                throw new Exception("رقم التليفون ({$supplierPhone}) مسجل بالفعل للمورد: " . $existingSupplier['name'] . ". اختر المورد من القائمة أو استخدم رقم مختلف.");
            } else {
                $supplierId = insert(
                    "INSERT INTO suppliers (name, phone, balance) VALUES (?, ?, 0)",
                    [$supplierName, $supplierPhone]
                );
                logActivity('إضافة مورد تلقائي', "تم إضافة المورد تلقائياً: $supplierName من فاتورة شراء", $handledBy);
            }
            
            execute("UPDATE invoices SET supplier_id = ? WHERE id = ?", [$supplierId, $invoiceId]);
        }
        
        // Update supplier balance:
        // - Positive balance means we owe the supplier
        // - remainingAmount > 0 means we still owe from this invoice
        // - remainingAmount < 0 means we overpaid (can apply to old debt)
        if ($supplierId) {
            // Always update balance with the remaining amount
            execute(
                "UPDATE suppliers SET balance = balance + ? WHERE id = ?",
                [$remainingAmount, $supplierId]
            );
        }
        
        commit();
        logActivity('إنشاء فاتورة شراء', "تم إنشاء فاتورة شراء رقم {$invoiceNumber}", $handledBy);
        
        if (isset($_POST['save_only'])) {
            setSuccess('تم حفظ الفاتورة بنجاح');
            header("Location: purchase.php");
            exit;
        }

        // Redirect to print with remaining info for installment prompt
        $redirectUrl = "print_purchase.php?id=$invoiceId";
        
        // Calculate total remaining including old debt we owe supplier
        $oldDebt = $supplierOldBalance > 0 ? $supplierOldBalance : 0;
        $totalRemaining = $remainingAmount + $oldDebt;
        
        if ($totalRemaining > 0 && $supplierId) {
            $redirectUrl .= "&remaining=$totalRemaining&new_amount=$remainingAmount&old_amount=$oldDebt&supplier_id=$supplierId&show_installment=1";
        }
        header("Location: $redirectUrl");
        exit;
        
    } catch (Exception $e) {
        rollback();
        setError('حدث خطأ: ' . $e->getMessage());
    }
}

// Get suppliers and products with balance
$suppliers = getRows("SELECT id, name, phone, balance FROM suppliers ORDER BY name");
$products = getRows("SELECT id, code, name, unit, price, stock_quantity FROM products ORDER BY name");
$warehouses = getAllWarehouses(true);
$userWarehouseId = getCurrentWarehouseId();

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
    background: linear-gradient(135deg, #10b981, #059669);
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

.supplier-section, .products-section, .totals-section {
    background: #f9fafb;
    border-radius: 6px;
    padding: 8px 10px;
    margin-bottom: 6px;
}

.supplier-section {
    background: linear-gradient(135deg, #ecfdf5, #d1fae5);
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
    background: #ecfdf5;
}

.items-table {
    width: 100%;
    border-collapse: collapse;
}

.items-table th {
    background: #059669;
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
    background: linear-gradient(135deg, #f59e0b, #d97706);
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
    background: linear-gradient(135deg, #10b981, #059669);
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
    background: linear-gradient(135deg, #059669, #047857);
}

.container {
    padding: 0px !important;
}

#productSearch {
    padding: 8px 12px !important;
    font-size: 0.9em !important;
}
</style>

<div class="container">
    <div class="invoice-container">
        <form method="POST" id="invoiceForm">
            <!-- Invoice Header -->
            <div class="invoice-header">
                <div class="invoice-title">📦 فاتورة شراء</div>
                <div style="display: flex; gap: 15px; align-items: center;">
                    <div style="text-align: right;">
                        <label style="font-size: 0.8em; font-weight: 700; color: #1e3a8a; display: block; margin-bottom: 2px;">🏢 المخزن الوجهة:</label>
                        <select name="warehouse_id" id="invoiceWarehouseSelect" style="padding: 4px 8px; border-radius: 4px; border: 1px solid #93c5fd; font-weight: 700; background: #eff6ff; color: #1e40af; font-size: 0.85em; cursor: pointer;">
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="<?php echo $wh['id']; ?>" <?php echo ((int)$wh['id'] === (int)$userWarehouseId) ? 'selected' : ''; ?>>
                                    🏢 <?php echo htmlspecialchars($wh['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <div class="invoice-number"><?php echo generateInvoiceNumber('purchase'); ?></div>
                        <input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" style="margin-top: 4px; padding: 4px 6px; border-radius: 4px; border: 1px solid #d1d5db; font-size: 0.85em;">
                    </div>
                </div>
            </div>
            
            <!-- Supplier Section -->
            <div class="supplier-section">
                <div class="section-title">🚚 بيانات المورد</div>
                <div class="form-row-inline">
                    <div class="form-group">
                        <label class="form-label">اسم المورد</label>
                        <div class="autocomplete-wrapper">
                            <input type="text" id="supplierName" name="supplier_name" class="form-control" 
                                   placeholder="اكتب اسم المورد..." autocomplete="off" required>
                            <input type="hidden" id="supplierId" name="supplier_id">
                            <div id="supplierResults" class="autocomplete-results"></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">التليفون</label>
                        <input type="text" id="supplierPhone" name="supplier_phone" class="form-control" 
                               placeholder="01xxxxxxxxx">
                    </div>
                    <div class="form-group">
                        <label class="form-label">المستلم</label>
                        <input type="text" name="handled_by" class="form-control" value="<?php echo htmlspecialchars($_SESSION['full_name'] ?? 'المدير'); ?>" readonly style="background: rgba(71,85,105,0.3); cursor: not-allowed;">
                    </div>
                </div>
                
                <!-- Old Balance Display -->
                <div id="supplierBalanceSection" style="display: none; margin-top: 15px; padding: 15px; background: #fef3c7; border: 2px solid #f59e0b; border-radius: 10px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-weight: bold;">⚠️ حساب قديم للمورد:</span>
                        <span id="oldBalanceDisplay" style="font-size: 1.3em; font-weight: bold; color: #dc2626;">0.00 جنيه</span>
                        <input type="hidden" id="oldBalance" value="0">
                    </div>
                </div>
            </div>
            
            <!-- Products Section -->
            <div class="products-section">
                <div class="section-title">📦 الأصناف المشتراة</div>
                <div class="form-group" style="margin-bottom: 8px;">
                    <div style="display: flex; gap: 8px;">
                        <div class="autocomplete-wrapper" style="flex: 1;">
                            <input type="text" id="productSearch" class="form-control" 
                                   placeholder="🔍 ابحث عن صنف..." autocomplete="off">
                            <div id="productResults" class="autocomplete-results"></div>
                        </div>
                        <button type="button" onclick="openNewProductModal()" class="btn" 
                                style="background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; padding: 8px 15px; font-size: 0.85em; border: none; border-radius: 6px; cursor: pointer;">
                            ➕ صنف جديد
                        </button>
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
                            <th style="width: 100px;">سعر الشراء</th>
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
                            <div class="total-label">المدفوع للمورد</div>
                            <input type="number" step="0.01" name="paid_amount" id="paidAmount" 
                                   style="width: 100%; text-align: center; font-size: 1.2em; padding: 8px; border: 1px solid #d1d5db; border-radius: 5px;"
                                   value="0" oninput="calculateTotals()">
                        </div>
                        <div class="total-box" id="oldBalanceBox" style="display: none;">
                            <div class="total-label">حساب قديم</div>
                            <div class="total-value" id="oldBalanceTotalDisplay" style="color: #dc2626;">0.00</div>
                        </div>
                        <div class="total-box danger" id="remainingBox">
                            <div class="total-label">إجمالي الباقي للمورد</div>
                            <div class="total-value" id="remainingDisplay">0.00</div>
                        </div>
                        <div class="total-box">
                            <div class="total-label">طريقة الدفع</div>
                            <select name="payment_method" class="form-control" style="font-size: 1em; padding: 8px;">
                                <option value="كاش">كاش</option>
                                <option value="تحويل بنكي">تحويل بنكي</option>
                                <option value="شيك">شيك</option>
                                <option value="آجل">آجل</option>
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
                <button type="submit" name="save_and_print" class="btn-submit" style="flex: 1;">
                    🖨️ حفظ وطباعة
                </button>
                <button type="submit" name="save_only" class="btn btn-success btn-lg" style="flex: 1;">
                    💾 حفظ فقط
                </button>
                <a href="list.php" class="btn btn-secondary btn-lg" style="flex: 1; text-align: center;">❌ إلغاء</a>
            </div>
        </form>
    </div>
</div>

<!-- New Product Modal -->
<div id="newProductModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 1000; justify-content: center; align-items: center;">
    <div style="background: white; border-radius: 15px; padding: 30px; width: 90%; max-width: 500px; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #e5e7eb; padding-bottom: 15px;">
            <h3 style="margin: 0; color: #1f2937;">➕ إضافة صنف جديد</h3>
            <button onclick="closeNewProductModal()" style="background: #dc2626; color: white; border: none; border-radius: 50%; width: 35px; height: 35px; cursor: pointer; font-size: 18px;">✕</button>
        </div>
        <form id="newProductForm">
            <div class="form-group" style="margin-bottom: 15px;">
                <label class="form-label" style="font-weight: bold;">اسم الصنف *</label>
                <input type="text" id="newProductName" class="form-control" required placeholder="مثال: تلفزيون سامسونج 55 بوصة">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div class="form-group">
                    <label class="form-label" style="font-weight: bold;">الكود</label>
                    <input type="text" id="newProductCode" class="form-control" placeholder="يُنشأ تلقائياً" readonly style="background: #f3f4f6;">
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-weight: bold;">الوحدة *</label>
                    <input type="text" id="newProductUnit" class="form-control" value="قطعة" required>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                <div class="form-group">
                    <label class="form-label" style="font-weight: bold;">سعر البيع</label>
                    <input type="number" id="newProductPrice" class="form-control" step="0.01" value="0" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-weight: bold;">الحد الأدنى للمخزون</label>
                    <input type="number" id="newProductMinStock" class="form-control" value="5">
                </div>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="submit" style="flex: 1; background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 12px; border: none; border-radius: 8px; font-size: 1.1em; cursor: pointer;">
                    💾 حفظ وإضافة للفاتورة
                </button>
                <button type="button" onclick="closeNewProductModal()" style="background: #6b7280; color: white; padding: 12px 20px; border: none; border-radius: 8px; cursor: pointer;">
                    إلغاء
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Supplier and Product data
const suppliers = <?php echo json_encode($suppliers); ?>;
const products = <?php echo json_encode($products); ?>;

let items = [];
let itemCounter = 1;

// Supplier Autocomplete
const supplierNameInput = document.getElementById('supplierName');
const supplierResults = document.getElementById('supplierResults');
const supplierIdInput = document.getElementById('supplierId');
const supplierPhoneInput = document.getElementById('supplierPhone');

supplierNameInput.addEventListener('input', function() {
    const query = this.value.toLowerCase().trim();
    if (query.length < 1) {
        supplierResults.style.display = 'none';
        return;
    }
    
    const matches = suppliers.filter(s => 
        s.name.toLowerCase().includes(query) || (s.phone && s.phone.includes(query))
    ).slice(0, 10);
    
    if (matches.length > 0) {
        supplierResults.innerHTML = matches.map(s => `
            <div class="autocomplete-item" onclick="selectSupplier(${s.id}, '${s.name}', '${s.phone || ''}', ${s.balance || 0})">
                <strong>${s.name}</strong> ${s.phone ? '- ' + s.phone : ''}
                ${s.balance > 0 ? '<br><small style="color: #dc2626;">حساب قديم: ' + s.balance + ' جنيه</small>' : ''}
            </div>
        `).join('');
        supplierResults.style.display = 'block';
    } else {
        supplierResults.innerHTML = '<div class="autocomplete-item" style="color: #6b7280;">مورد جديد</div>';
        supplierResults.style.display = 'block';
        supplierIdInput.value = '';
    }
});

let supplierOldBalance = 0;

function selectSupplier(id, name, phone, balance) {
    supplierNameInput.value = name;
    supplierPhoneInput.value = phone;
    supplierIdInput.value = id;
    supplierResults.style.display = 'none';
    
    // Show old balance if exists
    supplierOldBalance = parseFloat(balance) || 0;
    const balanceSection = document.getElementById('supplierBalanceSection');
    const oldBalanceBox = document.getElementById('oldBalanceBox');
    
    if (supplierOldBalance > 0) {
        document.getElementById('oldBalanceDisplay').textContent = supplierOldBalance.toFixed(2) + ' جنيه';
        document.getElementById('oldBalance').value = supplierOldBalance;
        balanceSection.style.display = 'block';
        oldBalanceBox.style.display = 'block';
        document.getElementById('oldBalanceTotalDisplay').textContent = supplierOldBalance.toFixed(2);
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
            <div class="autocomplete-item" onclick='addProduct(${JSON.stringify(p)})'>
                <strong>${p.name}</strong> (${p.code})<br>
                <small style="color: #6b7280;">المخزون الحالي: ${p.stock_quantity}</small>
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
        price: 0 // Purchase price starts at 0 - user enters it
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
            <td style="text-align: right;"><strong>${item.name}</strong></td>
            <td>${item.unit}</td>
            <td>
                <input type="number" min="1" value="${item.quantity}" 
                       onchange="updateQuantity(${item.id}, this.value)">
            </td>
            <td>
                <input type="number" step="0.01" value="${item.price}" 
                       onchange="updatePrice(${item.id}, this.value)" placeholder="سعر الشراء">
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
    const weOweSupplier = supplierOldBalance > 0 ? supplierOldBalance : 0;
    const maxPayment = total + weOweSupplier;
    const remaining = total - paid;
    const totalWithOldBalance = remaining + weOweSupplier;
    
    document.getElementById('subtotal').value = subtotal;
    document.getElementById('subtotalDisplay').textContent = subtotal.toFixed(2);
    document.getElementById('total').value = total;
    document.getElementById('totalDisplay').textContent = total.toFixed(2);
    document.getElementById('remainingDisplay').textContent = totalWithOldBalance.toFixed(2);
    
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
        if (weOweSupplier > 0) {
            warningEl.innerHTML = `⚠️ الحد الأقصى: ${maxPayment.toFixed(2)} (الفاتورة ${total.toFixed(2)} + المستحق للمورد ${weOweSupplier.toFixed(2)})`;
        } else {
            warningEl.innerHTML = `⚠️ الحد الأقصى: ${total.toFixed(2)} (لا يوجد حساب قديم مستحق للمورد)`;
        }
        warningEl.style.display = 'block';
        remainingBox.className = 'total-box danger';
        document.getElementById('remainingDisplay').textContent = '❌ خطأ';
    } else {
        paidInput.style.borderColor = '#d1d5db';
        paidInput.style.background = 'white';
        warningEl.style.display = 'none';
        
        // Update remaining box color
        if (totalWithOldBalance <= 0) {
            remainingBox.className = 'total-box highlight';
            document.getElementById('remainingDisplay').textContent = '0.00 ✓';
        } else {
            remainingBox.className = 'total-box danger';
        }
    }
}

// Hide dropdowns on click outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.autocomplete-wrapper')) {
        supplierResults.style.display = 'none';
        productResults.style.display = 'none';
    }
});

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
    const weOweSupplier = supplierOldBalance > 0 ? supplierOldBalance : 0;
    const maxPayment = total + weOweSupplier;
    
    if (paid > maxPayment && total > 0) {
        e.preventDefault();
        if (weOweSupplier > 0) {
            alert(`المبلغ المدفوع (${paid}) يتجاوز الحد الأقصى (${maxPayment.toFixed(2)})\n\nالفاتورة: ${total.toFixed(2)}\nالمستحق للمورد: ${weOweSupplier.toFixed(2)}`);
        } else {
            alert(`المبلغ المدفوع (${paid}) لا يمكن أن يتجاوز قيمة الفاتورة (${total.toFixed(2)})\n\nلا يوجد حساب قديم مستحق للمورد`);
        }
        return;
    }
    
    document.getElementById('itemsData').value = JSON.stringify(items);
});

// Focus on product search on load
document.addEventListener('DOMContentLoaded', function() {
    productSearch.focus();
});

// New Product Modal Functions
function openNewProductModal() {
    document.getElementById('newProductModal').style.display = 'flex';
    document.getElementById('newProductName').focus();
    // Generate code via AJAX
    fetch('ajax.php?action=generate_code&type=product')
        .then(r => r.json())
        .then(data => {
            if (data.code) {
                document.getElementById('newProductCode').value = data.code;
            }
        });
}

function closeNewProductModal() {
    document.getElementById('newProductModal').style.display = 'none';
    document.getElementById('newProductForm').reset();
}

// Save new product via AJAX
document.getElementById('newProductForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const productData = {
        action: 'add_product',
        name: document.getElementById('newProductName').value,
        code: document.getElementById('newProductCode').value,
        unit: document.getElementById('newProductUnit').value,
        price: document.getElementById('newProductPrice').value || 0,
        min_stock: document.getElementById('newProductMinStock').value || 5
    };
    
    fetch('ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(productData)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Add to invoice
            const newProduct = {
                id: data.product_id,
                code: productData.code,
                name: productData.name,
                unit: productData.unit,
                price: 0,
                stock_quantity: 0
            };
            addProduct(newProduct);
            
            // Add to products array for future searches
            products.push(newProduct);
            
            closeNewProductModal();
            alert('✅ تم إضافة الصنف بنجاح وإضافته للفاتورة');
        } else {
            alert('❌ خطأ: ' + (data.error || 'حدث خطأ'));
        }
    })
    .catch(() => alert('❌ حدث خطأ في الاتصال'));
});
</script>

<?php include '../../includes/footer.php'; ?>
