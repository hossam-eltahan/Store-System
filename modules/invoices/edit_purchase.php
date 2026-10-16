<?php
/**
 * Sales Invoice - Simplified Fast Version
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requirePermission('invoices.purchase.edit');

$settings = getAllSettings();
if (($settings['allow_edit_invoices'] ?? '0') !== '1') {
    setError('خاصية تعديل الفواتير غير مفعلة في الإعدادات');
    redirect('list.php');
}

$pageTitle = 'تعديل فاتورة شراء';

$invoiceId = $_GET['id'] ?? 0;
$oldInvoice = getRow("SELECT * FROM invoices WHERE id = ? AND type = 'purchase'", [$invoiceId]);

if (!$oldInvoice) {
    setError('الفاتورة غير موجودة');
    redirect('list.php');
}

// Check if installment plan exists and has payments
$plan = getRow("SELECT * FROM installment_plans WHERE invoice_id = ?", [$invoiceId]);
if ($plan) {
    $paidCountRow = getRow("SELECT COUNT(*) as count FROM installment_payments WHERE plan_id = ? AND status IN ('paid', 'partial')", [$plan['id']]);
    if ($paidCountRow['count'] > 0) {
        setError('لا يمكن تعديل الفاتورة لأنه تم سداد أقساط منها بالفعل.');
        redirect('list.php');
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        beginTransaction();
        
        $invoiceNumber = $oldInvoice['invoice_number'];
        
        // Get form data
        $supplierId = !empty($_POST['supplier_id']) ? $_POST['supplier_id'] : null;
        $supplierName = sanitize($_POST['supplier_name'] ?? '');
        $supplierPhone = sanitize($_POST['supplier_phone'] ?? '');
        $date = $_POST['date'];
        $discount = floatval($_POST['discount'] ?? 0);
        $paidAmount = floatval($_POST['paid_amount'] ?? 0);
        $paymentMethod = sanitize($_POST['payment_method'] ?? 'كاش');
        $handledBy = $_SESSION['full_name'] ?? 'المدير';
        $notes = sanitize($_POST['notes'] ?? '');
        
        // === REVERSE OLD INVOICE ===
        // Drafts never touched stock or supplier balance, so there is nothing to reverse.
        $isDraft = (($oldInvoice['status'] ?? 'approved') === 'draft');
        // 1. Reverse inventory for the old warehouse (approved invoices only)
        $oldWarehouseId = (int)($oldInvoice['warehouse_id'] ?? 1);
        $oldItems = getRows("SELECT * FROM invoice_items WHERE invoice_id = ?", [$invoiceId]);
        if (!$isDraft) {
            foreach ($oldItems as $item) {
                // Lines documenting a quick supply: their stock never came from this invoice — skip reversal
                if (!empty($item['source_movement_id'])) {
                    continue;
                }
                // Raw-material lines reverse from ingredients stock + ledger
                if (!empty($item['ingredient_id'])) {
                    $reverseQty = ($item['stock_quantity'] !== null) ? (float)$item['stock_quantity'] : (float)$item['quantity'];
                    removeIngredientStock((int)$item['ingredient_id'], $reverseQty, 'return', 'purchase_invoice', $invoiceId,
                        'عكس فاتورة شراء قبل التعديل ' . $oldInvoice['invoice_number']);
                    continue;
                }
                $reverseQty = ($item['stock_quantity'] !== null) ? (int)$item['stock_quantity'] : (int)$item['quantity'];
                updateWarehouseStock($oldWarehouseId, $item['product_id'], $reverseQty, 'subtract');
                $oldProd = getRow("SELECT unit FROM products WHERE id = ?", [(int)$item['product_id']]);
                recordProductMovement((int)$item['product_id'], $oldWarehouseId, 'reversal', 0, $reverseQty,
                    $oldProd ? $oldProd['unit'] : ($item['unit'] ?? null), 'purchase_invoice', $invoiceId,
                    'عكس فاتورة شراء قبل التعديل ' . $oldInvoice['invoice_number']);
            }

            // 2. Reverse supplier balance
            if ($oldInvoice['supplier_id']) {
                execute("UPDATE suppliers SET balance = balance - ? WHERE id = ?", [$oldInvoice['remaining_amount'], $oldInvoice['supplier_id']]);
            }
        }

        // Free any supply movements linked to this invoice (re-linked below if still present)
        execute("UPDATE product_stock_movements SET linked_invoice_id = NULL WHERE linked_invoice_id = ?", [$invoiceId]);

        // 3. Delete old items
        execute("DELETE FROM invoice_items WHERE invoice_id = ?", [$invoiceId]);
        
        // 4. Delete old plan if exists
        if ($plan) {
            execute("DELETE FROM installment_payments WHERE plan_id = ?", [$plan['id']]);
            execute("DELETE FROM installment_plans WHERE id = ?", [$plan['id']]);
        }
        // ===========================

        // Get supplier's old balance BEFORE this invoice (after reversing old invoice)
        $supplierOldBalance = 0;
        if ($supplierId) {
            $supplierData = getRow("SELECT balance FROM suppliers WHERE id = ?", [$supplierId]);
            $supplierOldBalance = floatval($supplierData['balance'] ?? 0);
        }
        
        // Calculate totals + validate items server-side with unit snapshots
        $rawItems = json_decode($_POST['items'] ?? '[]', true);
        if (empty($rawItems) || !is_array($rawItems)) {
            throw new Exception('يرجى إضافة صنف واحد على الأقل');
        }
        $totalAmount = 0;
        $preparedItems = [];
        foreach ($rawItems as $raw) {
            $isIngredient = (($raw['kind'] ?? 'product') === 'ingredient');
            $unitPrice = floatval($raw['price'] ?? 0);
            if ($unitPrice < 0) {
                throw new Exception('سعر الشراء غير صالح في الفاتورة');
            }

            if ($isIngredient) {
                $ingredientId = (int)($raw['ingredient_id'] ?? $raw['product_id'] ?? $raw['id'] ?? 0);
                $purchaseQty = (float)($raw['quantity'] ?? 0);
                if ($ingredientId <= 0 || $purchaseQty <= 0) {
                    throw new Exception('بيانات خامة غير صالحة في الفاتورة');
                }
                if (!empty($raw['source_movement_id'])) {
                    throw new Exception('ربط التوريد متاح لأصناف المنيو فقط');
                }
                $ing = getRow("SELECT id, sku, name, base_unit, purchase_unit, conversion_factor FROM ingredients WHERE id = ? AND is_active = 1", [$ingredientId]);
                if (!$ing) {
                    throw new Exception('مادة خام غير موجودة في الفاتورة');
                }
                $factor = isset($raw['factor']) && (float)$raw['factor'] > 0
                    ? (float)$raw['factor']
                    : (float)($ing['conversion_factor'] ?: 1);
                if ($factor <= 0) {
                    throw new Exception('معامل التحويل غير صالح للخامة: ' . $ing['name']);
                }
                $stockQty = round($purchaseQty * $factor, 3);
                if ($stockQty <= 0) {
                    throw new Exception('ناتج التحويل غير صالح للخامة: ' . $ing['name']);
                }
                $purchaseUnit = sanitize(trim($raw['purchase_unit'] ?? '')) !== ''
                    ? sanitize(trim($raw['purchase_unit']))
                    : ($ing['purchase_unit'] ?: $ing['base_unit']);
                $totalAmount += $purchaseQty * $unitPrice;
                $preparedItems[] = [
                    'kind' => 'ingredient',
                    'ingredient_id' => $ingredientId,
                    'product_id' => null,
                    'code' => $ing['sku'] ?: ('ING-' . $ingredientId),
                    'name' => $ing['name'],
                    'unit' => $ing['base_unit'],
                    'purchase_unit' => $purchaseUnit,
                    'factor' => $factor,
                    'quantity' => $purchaseQty,
                    'stock_quantity' => $stockQty,
                    'price' => $unitPrice,
                    'source_movement_id' => null,
                ];
                continue;
            }

            $productId = (int)($raw['product_id'] ?? $raw['id'] ?? 0);
            $purchaseQty = (int)($raw['quantity'] ?? 0);
            if ($productId <= 0 || $purchaseQty <= 0) {
                throw new Exception('بيانات صنف غير صالحة في الفاتورة');
            }
            $product = getRow("SELECT id, code, name, unit, purchase_unit, units_per_purchase FROM products WHERE id = ?", [$productId]);
            if (!$product) {
                throw new Exception('صنف غير موجود في الفاتورة');
            }
            $factor = isset($raw['factor']) && (float)$raw['factor'] > 0
                ? (float)$raw['factor']
                : (float)($product['units_per_purchase'] ?: 1);
            if ($factor <= 0) {
                throw new Exception('معامل التحويل غير صالح للصنف: ' . $product['name']);
            }
            $stockQty = purchaseToStockQty($purchaseQty, $factor);
            $purchaseUnit = sanitize(trim($raw['purchase_unit'] ?? '')) !== ''
                ? sanitize(trim($raw['purchase_unit']))
                : ($product['purchase_unit'] ?: $product['unit']);
            $totalAmount += $purchaseQty * $unitPrice;
            $srcMoveId = (int)($raw['source_movement_id'] ?? 0);
            if ($srcMoveId > 0) {
                $srcMov = validateSupplyLink($srcMoveId, $productId);
                if ($stockQty !== (int)$srcMov['qty_in']) {
                    throw new Exception('سطر التوريد لا يطابق الكمية المورّدة أصلاً للصنف: ' . $product['name']);
                }
            }
            $preparedItems[] = [
                'kind' => 'product',
                'ingredient_id' => null,
                'product_id' => $productId,
                'code' => $product['code'],
                'name' => $product['name'],
                'unit' => $product['unit'],
                'purchase_unit' => $purchaseUnit,
                'factor' => $factor,
                'quantity' => $purchaseQty,
                'stock_quantity' => $stockQty,
                'price' => $unitPrice,
                'source_movement_id' => $srcMoveId > 0 ? $srcMoveId : null,
            ];
        }

        $discount = min(max(0, $discount), $totalAmount);
        
        $finalAmount = $totalAmount - $discount;
        $remainingAmount = $finalAmount - $paidAmount;
        
        // Validate overpayment
        if ($paidAmount > $finalAmount) {
            $weOweSupplier = $supplierOldBalance > 0 ? $supplierOldBalance : 0;
            $maxPayment = $finalAmount + $weOweSupplier;
            
            if ($paidAmount > $maxPayment) {
                if ($weOweSupplier > 0) {
                    throw new Exception("المبلغ المدفوع ({$paidAmount}) يتجاوز قيمة الفاتورة ({$finalAmount}) + المستحق للمورد ({$weOweSupplier})");
                } else {
                    throw new Exception("المبلغ المدفوع ({$paidAmount}) لا يمكن أن يتجاوز قيمة الفاتورة ({$finalAmount}) لأنه لا يوجد حساب مستحق قديم للمورد");
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
        
        // Update invoice
        $userId = getCurrentUserId();
        $warehouseId = (int)($_POST['warehouse_id'] ?? $oldWarehouseId);
        execute(
            "UPDATE invoices SET supplier_id = ?, customer_name = ?, customer_phone = ?, date = ?, total_amount = ?, discount = ?, paid_amount = ?, remaining_amount = ?, old_balance = ?, payment_method = ?, payment_status = ?, handled_by = ?, user_id = COALESCE(user_id, ?), warehouse_id = ?, notes = ? WHERE id = ?",
            [$supplierId, $supplierName, $supplierPhone, $date, $totalAmount, $discount, $paidAmount, $remainingAmount, $supplierOldBalance, $paymentMethod, $paymentStatus, $handledBy, $userId, $warehouseId, $notes, $invoiceId]
        );
        
        // Insert items and update stock (stock moves only for approved invoices)
        foreach ($preparedItems as $item) {
            $lineId = insert(
                "INSERT INTO invoice_items (invoice_id, product_id, ingredient_id, product_code, product_name, item_name, unit, quantity, unit_price, total, purchase_unit, conversion_factor, stock_quantity, source_movement_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$invoiceId, $item['product_id'], $item['ingredient_id'], $item['code'], $item['name'], $item['name'], $item['unit'], $item['quantity'], $item['price'], $item['quantity'] * $item['price'],
                 $item['purchase_unit'], $item['factor'], $item['stock_quantity'], $item['source_movement_id']]
            );
            if (!$lineId) {
                throw new Exception('تعذر حفظ صنف الفاتورة: ' . $item['name']);
            }

            if (!$isDraft) {
                if (($item['kind'] ?? 'product') === 'ingredient') {
                    addIngredientStock($item['ingredient_id'], $item['quantity'], $item['factor'], $item['price'], 'purchase', 'purchase_invoice', $invoiceId, 'فاتورة شراء بعد التعديل ' . $invoiceNumber);
                    continue;
                }
                // Linked quick-supply lines: link only, never touch stock
                if (!empty($item['source_movement_id'])) {
                    execute("UPDATE product_stock_movements SET linked_invoice_id = ? WHERE id = ?", [$invoiceId, (int)$item['source_movement_id']]);
                    continue;
                }
                // Add stock to selected warehouse
                $lineUnitCost = ($item['factor'] > 0) ? ((float)$item['price'] / (float)$item['factor']) : null;
                addProductStock($item['product_id'], $warehouseId, $item['stock_quantity'], 'purchase', 'purchase_invoice', $invoiceId, 'فاتورة شراء بعد التعديل ' . $invoiceNumber, $lineUnitCost, $supplierId ?: null);
            }
        }
        
        // Auto-add supplier if not registered and has phone number
        if (!$supplierId && $supplierPhone && $supplierName) {
            $existingsupplier = getRow("SELECT id, name FROM suppliers WHERE phone = ?", [$supplierPhone]);
            
            if ($existingsupplier) {
                throw new Exception("رقم التليفون ({$supplierPhone}) مسجل بالفعل للمورد: " . $existingsupplier['name'] . ". اختر المورد من القائمة أو استخدم رقم مختلف.");
            } else {
                $supplierId = insert(
                    "INSERT INTO suppliers (name, phone, balance) VALUES (?, ?, 0)",
                    [$supplierName, $supplierPhone]
                );
                logActivity('إضافة مورد تلقائي', "تم إضافة المورد تلقائياً: $supplierName من فاتورة شراء", $handledBy);
            }
            
            execute("UPDATE invoices SET supplier_id = ? WHERE id = ?", [$supplierId, $invoiceId]);
        }
        
        // Update supplier balance (approved invoices only):
        if ($supplierId && !$isDraft) {
            execute(
                "UPDATE suppliers SET balance = balance + ? WHERE id = ?",
                [$remainingAmount, $supplierId]
            );
        }
        
        commit();
        logActivity('تعديل فاتورة', "تم تعديل فاتورة شراء رقم {$invoiceNumber}", $handledBy);
        
        if (isset($_POST['save_only'])) {
            setSuccess('تم تعديل الفاتورة بنجاح');
            header("Location: list.php?type=purchase");
            exit;
        }

        // Back to the list and open the thermal receipt in a modal
        header("Location: list.php?type=purchase&printed=$invoiceId");
        exit;
        
    } catch (Exception $e) {
        rollback();
        setError('حدث خطأ: ' . $e->getMessage());
    }
}

// Get suppliers and products for autocomplete with balance
$suppliers = getRows("SELECT id, name, phone, balance FROM suppliers ORDER BY name");
$products = getPurchaseables();
$knownUnitsEdit = getKnownUnits();
$isDraftEdit = (($oldInvoice['status'] ?? 'approved') === 'draft');
$warehouses = getAllWarehouses(true);
$currentInvoiceWarehouseId = (int)($oldInvoice['warehouse_id'] ?? 1);

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

.supplier-section, .products-section, .totals-section {
    background: #f9fafb;
    border-radius: 6px;
    padding: 8px 10px;
    margin-bottom: 6px;
}

.supplier-section {
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
                <div class="invoice-title">🧾 تعديل فاتورة شراء</div>
                <div style="display: flex; gap: 15px; align-items: center;">
                    <div style="text-align: right;">
                        <label style="font-size: 0.8em; font-weight: 700; color: #1e3a8a; display: block; margin-bottom: 2px;">🏢 المخزن الوجهة:</label>
                        <select name="warehouse_id" id="invoiceWarehouseSelect" style="padding: 4px 8px; border-radius: 4px; border: 1px solid #93c5fd; font-weight: 700; background: #eff6ff; color: #1e40af; font-size: 0.85em; cursor: pointer;">
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="<?php echo $wh['id']; ?>" <?php echo ((int)$wh['id'] === (int)$currentInvoiceWarehouseId) ? 'selected' : ''; ?>>
                                    🏢 <?php echo htmlspecialchars($wh['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <div class="invoice-number"><?php echo $oldInvoice['invoice_number']; ?></div>
                        <input type="date" name="date" value="<?php echo $oldInvoice['date']; ?>" style="margin-top: 4px; padding: 4px 6px; border-radius: 4px; border: 1px solid #d1d5db; font-size: 0.85em;">
                    </div>
                </div>
            </div>
            <?php if ($isDraftEdit): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; background: #fef3c7; border: 2px dashed #d97706; border-radius: 8px; padding: 8px 12px; margin-bottom: 8px;">
                <span style="font-weight: 700; color: #92400e;">📝 هذه مسودة — لم تحرّك المخزون ولا حساب المورد بعد</span>
                <form method="POST" action="approve_purchase.php" style="margin: 0;" onsubmit="return confirm('اعتماد هذه الفاتورة؟ سيتم إضافة الأصناف للمخزون وتحديث حساب المورد.');">
                    <input type="hidden" name="id" value="<?php echo (int)$oldInvoice['id']; ?>">
                    <button type="submit" class="btn btn-success">✅ اعتماد الفاتورة الآن</button>
                </form>
            </div>
            <?php endif; ?>
            
            <!-- supplier Section -->
            <div class="supplier-section">
                <div class="section-title">👤 بيانات المورد</div>
                <div class="form-row-inline">
                    <div class="form-group">
                        <label class="form-label">اسم المورد</label>
                        <div class="autocomplete-wrapper">
                            <input type="text" id="supplierName" name="supplier_name" class="form-control" 
                                   placeholder="اكتب اسم المورد..." autocomplete="off" required value="<?php echo htmlspecialchars($oldInvoice['customer_name'] ?? ''); ?>">
                            <input type="hidden" id="supplierId" name="supplier_id" value="<?php echo $oldInvoice['supplier_id']; ?>">
                            <div id="supplierResults" class="autocomplete-results"></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">التليفون</label>
                        <input type="text" id="supplierPhone" name="supplier_phone" class="form-control" 
                               placeholder="01xxxxxxxxx" required value="<?php echo htmlspecialchars($oldInvoice['customer_phone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">البائع</label>
                        <input type="text" name="handled_by" class="form-control" value="<?php echo htmlspecialchars($_SESSION['full_name'] ?? 'المدير'); ?>" readonly style="background: rgba(71,85,105,0.3); cursor: not-allowed;">
                    </div>
                </div>
                
                <!-- Old Balance Display -->
                <div id="supplierBalanceSection" style="display: none; margin-top: 15px; padding: 15px; background: #fee2e2; border: 2px solid #dc2626; border-radius: 10px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-weight: bold;">⚠️ حساب قديم على المورد:</span>
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
                            <th style="width: 30px;">م</th>
                            <th style="width: 70px;">الكود</th>
                            <th>الصنف</th>
                            <th style="width: 70px;">وحدة الشراء</th>
                            <th style="width: 70px;">الكمية</th>
                            <th style="width: 60px;">المعامل</th>
                            <th style="width: 90px;">الناتج بالمخزون</th>
                            <th style="width: 90px;">السعر</th>
                            <th style="width: 90px;">الإجمالي</th>
                            <th style="width: 40px;"></th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                        <tr id="emptyRow">
                            <td colspan="10" style="padding: 30px; color: #9ca3af;">ابحث عن صنف لإضافته للفاتورة</td>
                        </tr>
                    </tbody>
                </table>
                <datalist id="purchaseUnitsListEdit">
                    <?php foreach ($knownUnitsEdit as $u): ?>
                        <option value="<?php echo htmlspecialchars($u); ?>"></option>
                    <?php endforeach; ?>
                </datalist>
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
                        <input type="number" step="0.01" id="discount" name="discount" 
                               style="width: 100%; text-align: center; font-size: 0.9em; padding: 5px; border: 1px solid #d1d5db; border-radius: 4px;"
                               value="<?php echo $oldInvoice['discount']; ?>" oninput="calculateTotals()">
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
                                   value="<?php echo $oldInvoice['paid_amount']; ?>" oninput="calculateTotals()">
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
                                <option value="كاش" <?php echo $oldInvoice['payment_method'] === 'كاش' ? 'selected' : ''; ?>>كاش</option>
                                <option value="انستاباي" <?php echo $oldInvoice['payment_method'] === 'انستاباي' ? 'selected' : ''; ?>>انستاباي</option>
                                <option value="فودافون كاش" <?php echo $oldInvoice['payment_method'] === 'فودافون كاش' ? 'selected' : ''; ?>>فودافون كاش</option>
                                <option value="فيزا" <?php echo $oldInvoice['payment_method'] === 'فيزا' ? 'selected' : ''; ?>>فيزا</option>
                                <option value="أقساط" <?php echo $oldInvoice['payment_method'] === 'أقساط' ? 'selected' : ''; ?>>أقساط</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Notes -->
            <div class="form-group" style="margin-bottom: 8px;">
                <input type="text" name="notes" class="form-control" placeholder="ملاحظات..." style="font-size: 0.85em; padding: 6px;" value="<?php echo htmlspecialchars($oldInvoice['notes']); ?>">
            </div>
            
            <input type="hidden" name="items" id="itemsData">
            
            <!-- Submit -->
            <div class="d-flex gap-2">
                <button type="submit" name="save_and_print" class="btn-submit" style="flex: 1;">
                    🖨️ تعديل وطباعة
                </button>
                <button type="submit" name="save_only" class="btn btn-success btn-lg" style="flex: 1;">
                    💾 تعديل فقط
                </button>
                <a href="list.php" class="btn btn-secondary btn-lg" style="flex: 1; text-align: center;">❌ إلغاء</a>
            </div>
        </form>
    </div>
</div>

<script>
// supplier and Product data
const suppliers = <?php echo json_encode($suppliers); ?>;
const products = <?php echo json_encode($products); ?>;

let items = [];
let itemCounter = 1;

<?php
$oldItemsForJs = getRows("SELECT ii.*, p.unit AS p_unit, p.purchase_unit AS p_purchase_unit, p.units_per_purchase AS p_factor
                          FROM invoice_items ii LEFT JOIN products p ON p.id = ii.product_id
                          WHERE ii.invoice_id = ?", [$invoiceId]);
$jsItems = [];
foreach ($oldItemsForJs as $item) {
    $jsFactor = ($item['conversion_factor'] !== null && (float)$item['conversion_factor'] > 0)
        ? (float)$item['conversion_factor']
        : (float)($item['p_factor'] ?: 1);
    $jsPurchaseUnit = ($item['purchase_unit'] !== null && $item['purchase_unit'] !== '')
        ? $item['purchase_unit']
        : ($item['p_purchase_unit'] ?: ($item['p_unit'] ?: $item['unit']));
    $jsItems[] = [
        'id' => $item['product_id'],
        'ingredient_id' => $item['ingredient_id'],
        'kind' => !empty($item['ingredient_id']) ? 'ingredient' : 'product',
        'code' => $item['product_code'],
        'name' => $item['product_name'],
        'unit' => $item['p_unit'] ?: $item['unit'],
        'purchase_unit' => $jsPurchaseUnit,
        'factor' => $jsFactor,
        'price' => floatval($item['unit_price']),
        'quantity' => floatval($item['quantity']),
        'source_movement_id' => !empty($item['source_movement_id']) ? (int)$item['source_movement_id'] : null,
        'stock_quantity' => 999999
    ];
}
?>
// Initialize with old items
const initialItems = <?php echo json_encode($jsItems); ?>;
initialItems.forEach(item => {
    items.push({
        id: itemCounter++,
        product_id: item.id,
        ingredient_id: item.ingredient_id || null,
        kind: item.kind || 'product',
        code: item.code,
        name: item.name,
        unit: item.unit,
        purchase_unit: item.purchase_unit,
        factor: item.factor,
        price: item.price,
        quantity: item.quantity,
        source_movement_id: item.source_movement_id || null,
        max_qty: item.stock_quantity // bypass max qty limit for old items
    });
});

document.addEventListener('DOMContentLoaded', function() {
    renderItems();
    calculateTotals();
});

// supplier Autocomplete
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
    
    const matches = suppliers.filter(c => 
        c.name.toLowerCase().includes(query) || c.phone.includes(query)
    ).slice(0, 10);
    
    if (matches.length > 0) {
        supplierResults.innerHTML = matches.map(c => `
            <div class="autocomplete-item" onclick="selectsupplier(${c.id}, '${c.name}', '${c.phone}', ${c.balance || 0})">
                <strong>${c.name}</strong> - ${c.phone}
                ${c.balance < 0 ? '<br><small style="color: #dc2626;">عليه: ' + Math.abs(c.balance) + ' جنيه</small>' : ''}
            </div>
        `).join('');
        supplierResults.style.display = 'block';
    } else {
        supplierResults.innerHTML = '<div class="autocomplete-item" style="color: #6b7280;">عميل جديد - سيتم إضافته تلقائياً</div>';
        supplierResults.style.display = 'block';
        supplierIdInput.value = '';
    }
});

let supplierOldBalance = 0;

function selectsupplier(id, name, phone, balance) {
    supplierNameInput.value = name;
    supplierPhoneInput.value = phone;
    supplierIdInput.value = id;
    supplierResults.style.display = 'none';
    
    // Show old balance if supplier owes money (negative balance means debt)
    supplierOldBalance = parseFloat(balance) || 0;
    const balanceSection = document.getElementById('supplierBalanceSection');
    const oldBalanceBox = document.getElementById('oldBalanceBox');
    
    if (supplierOldBalance < 0) {
        const debt = Math.abs(supplierOldBalance);
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
                <strong>${p.kind === 'ingredient' ? '🧪 ' : ''}${p.name}</strong> (${p.code})
                ${p.stock_quantity <= 0 ? '<span style="color: #dc2626; font-weight: bold; margin-right: 8px;">⚠️ نفذ</span>' : ''}
                <br>
                <small style="color: #6b7280;">${p.kind === 'ingredient' ? 'خامة • ' : ''}السعر: ${p.price} جنيه - شراء: ${p.purchase_unit || p.unit} - المخزون: ${p.stock_quantity <= 0 ? '<span style="color: #dc2626;">0</span>' : p.stock_quantity}</small>
            </div>
        `).join('');
        productResults.style.display = 'block';
    } else {
        productResults.innerHTML = '<div class="autocomplete-item" style="color: #dc2626;">لا توجد نتائج</div>';
        productResults.style.display = 'block';
    }
});

function addProduct(product) {
    // Check if already added (products by product_id, ingredients by ingredient_id)
    const newPid = product.kind === 'ingredient' ? null : product.id;
    const newIid = product.kind === 'ingredient' ? (product.ingredient_id || product.id) : null;
    const existing = items.find(i => (i.product_id || null) == newPid && (i.ingredient_id || null) == newIid);
    if (existing) {
        existing.quantity++;
        renderItems();
        productSearch.value = '';
        productResults.style.display = 'none';
        return;
    }

    items.push({
        id: itemCounter++,
        product_id: newPid,
        ingredient_id: newIid,
        kind: product.kind || 'product',
        code: product.code,
        name: product.name,
        unit: product.unit,
        purchase_unit: product.purchase_unit || product.unit,
        factor: parseFloat(product.units_per_purchase) > 0 ? parseFloat(product.units_per_purchase) : 1,
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
        tbody.innerHTML = '<tr id="emptyRow"><td colspan="10" style="padding: 30px; color: #9ca3af;">ابحث عن صنف لإضافته للفاتورة</td></tr>';
        calculateTotals();
        return;
    }
    
    tbody.innerHTML = items.map((item, index) => {
        const stockOut = Math.round((parseFloat(item.quantity) || 0) * (parseFloat(item.factor) || 0));
        const locked = item.source_movement_id ? 'readonly style="width: 70px; background: #f3f4f6;"' : 'style="width: 70px;"';
        const lockedNum = item.source_movement_id ? 'readonly style="width: 60px; background: #f3f4f6;"' : 'style="width: 60px;"';
        const lockedQty = item.source_movement_id ? 'readonly style="background: #f3f4f6;"' : '';
        return `
        <tr>
            <td>${index + 1}</td>
            <td style="font-size: 0.9em;">${item.code}</td>
            <td style="text-align: center;"><strong>${item.kind === 'ingredient' ? '🧪 ' : ''}${item.name}</strong><br><small style="color:#6b7280;">وحدة المخزون: ${item.unit}</small>${item.source_movement_id ? '<br><small style="color:#2563eb; font-weight:700;">🚚 توثيق توريد — الكمية ثابتة</small>' : ''}</td>
            <td>
                <input type="text" list="purchaseUnitsListEdit" value="${item.purchase_unit || item.unit}"
                       onchange="updatePurchaseUnit(${item.id}, this.value)" ${locked}>
            </td>
            <td>
                <input type="number" min="1" value="${item.quantity}"
                       onchange="updateQuantity(${item.id}, this.value)" ${lockedQty}>
            </td>
            <td>
                <input type="number" step="any" min="0" value="${item.factor || 1}"
                       onchange="updateFactor(${item.id}, this.value)" ${lockedNum}>
            </td>
            <td style="font-weight: bold; color: #059669;">${stockOut} ${item.unit}</td>
            <td>
                <input type="number" step="0.01" value="${item.price}"
                       onchange="updatePrice(${item.id}, this.value)">
            </td>
            <td style="font-weight: bold;">${(item.quantity * item.price).toFixed(2)}</td>
            <td>
                <button type="button" class="remove-btn" onclick="removeItem(${item.id})">✕</button>
            </td>
        </tr>`;
    }).join('');

    calculateTotals();
}

function updatePurchaseUnit(id, val) {
    const item = items.find(i => i.id === id);
    if (item && !item.source_movement_id && val.trim() !== '') {
        item.purchase_unit = val.trim();
        renderItems();
    }
}

function updateFactor(id, val) {
    const item = items.find(i => i.id === id);
    if (item && !item.source_movement_id) {
        const f = parseFloat(val);
        item.factor = (f > 0) ? f : 1;
        renderItems();
    }
}

function updateQuantity(id, qty) {
    const item = items.find(i => i.id === id);
    if (item && !item.source_movement_id) {
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
    const discount = Math.min(subtotal, Math.max(0, parseFloat(document.getElementById('discount').value) || 0));
    document.getElementById('discount').value = discount;
    const total = subtotal - discount;
    const paid = parseFloat(document.getElementById('paidAmount').value) || 0;
    const oldDebt = supplierOldBalance < 0 ? Math.abs(supplierOldBalance) : 0;
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
    const oldDebt = supplierOldBalance < 0 ? Math.abs(supplierOldBalance) : 0;
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

