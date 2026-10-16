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

        // Draft or immediate approval?
        $isDraft = isset($_POST['save_draft']);

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

        // Calculate totals + validate items server-side (never trust JS math)
        $items = json_decode($_POST['items'] ?? '[]', true);
        if (empty($items) || !is_array($items)) {
            throw new Exception('يرجى إضافة صنف واحد على الأقل');
        }
        $totalAmount = 0;
        $preparedItems = [];
        foreach ($items as $item) {
            $isIngredient = (($item['kind'] ?? 'product') === 'ingredient');
            $unitPrice = floatval($item['price'] ?? 0);
            if ($unitPrice < 0) {
                throw new Exception('سعر الشراء غير صالح في الفاتورة');
            }

            if ($isIngredient) {
                // ---- Raw material line ----
                $ingredientId = (int)($item['ingredient_id'] ?? $item['product_id'] ?? 0);
                $purchaseQty = (float)($item['quantity'] ?? 0);
                if ($ingredientId <= 0 || $purchaseQty <= 0) {
                    throw new Exception('بيانات خامة غير صالحة في الفاتورة');
                }
                if (!empty($item['source_movement_id'])) {
                    throw new Exception('ربط التوريد متاح لأصناف المنيو فقط');
                }
                $ing = getRow("SELECT id, sku, name, base_unit, purchase_unit, conversion_factor FROM ingredients WHERE id = ? AND is_active = 1", [$ingredientId]);
                if (!$ing) {
                    throw new Exception('مادة خام غير موجودة في الفاتورة');
                }
                $factor = isset($item['factor']) && (float)$item['factor'] > 0
                    ? (float)$item['factor']
                    : (float)($ing['conversion_factor'] ?: 1);
                if ($factor <= 0) {
                    throw new Exception('معامل التحويل غير صالح للخامة: ' . $ing['name']);
                }
                $stockQty = round($purchaseQty * $factor, 3);
                if ($stockQty <= 0) {
                    throw new Exception('ناتج التحويل غير صالح للخامة: ' . $ing['name']);
                }
                $purchaseUnit = sanitize(trim($item['purchase_unit'] ?? '')) !== ''
                    ? sanitize(trim($item['purchase_unit']))
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

            $productId = (int)($item['product_id'] ?? 0);
            $purchaseQty = (int)($item['quantity'] ?? 0);
            if ($productId <= 0 || $purchaseQty <= 0) {
                throw new Exception('بيانات صنف غير صالحة في الفاتورة');
            }
            $product = getRow("SELECT id, code, name, unit, purchase_unit, units_per_purchase FROM products WHERE id = ?", [$productId]);
            if (!$product) {
                throw new Exception('صنف غير موجود في الفاتورة');
            }
            // Factor sent by the form is a snapshot; validate and recompute here
            $factor = isset($item['factor']) && (float)$item['factor'] > 0
                ? (float)$item['factor']
                : (float)($product['units_per_purchase'] ?: 1);
            if ($factor <= 0) {
                throw new Exception('معامل التحويل غير صالح للصنف: ' . $product['name']);
            }
            $stockQty = purchaseToStockQty($purchaseQty, $factor);
            $purchaseUnit = sanitize(trim($item['purchase_unit'] ?? '')) !== ''
                ? sanitize(trim($item['purchase_unit']))
                : ($product['purchase_unit'] ?: $product['unit']);
            $totalAmount += $purchaseQty * $unitPrice;
            // Optional link to an already-applied quick supply (no double stock on approval)
            $srcMoveId = (int)($item['source_movement_id'] ?? 0);
            if ($srcMoveId > 0) {
                $srcMov = validateSupplyLink($srcMoveId, $productId);
                // A documenting line must mirror the supply exactly — locked on the form
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
        
        // Insert invoice with old_balance, user_id, warehouse_id and approval status
        $invoiceStatus = $isDraft ? 'draft' : 'approved';
        $invoiceId = insert(
            "INSERT INTO invoices (invoice_number, type, supplier_id, customer_name, customer_phone, date, total_amount, discount, paid_amount, remaining_amount, old_balance, payment_method, payment_status, handled_by, user_id, warehouse_id, notes, status)
            VALUES (?, 'purchase', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$invoiceNumber, $supplierId, $supplierName, $supplierPhone, $date, $totalAmount, $discount, $paidAmount, $remainingAmount, $supplierOldBalance, $paymentMethod, $paymentStatus, $handledBy, $userId, $warehouseId, $notes, $invoiceStatus]
        );
        if (!$invoiceId) {
            throw new Exception('تعذر حفظ الفاتورة في قاعدة البيانات');
        }

        // Insert items with unit snapshots; stock moves only on approval
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
                    // Raw materials go to ingredients stock + ledger (no warehouse split)
                    addIngredientStock($item['ingredient_id'], $item['quantity'], $item['factor'], $item['price'], 'purchase', 'purchase_invoice', $invoiceId, 'فاتورة شراء ' . $invoiceNumber);
                    continue;
                }
                // Lines linked to an applied quick supply: link only, never add stock twice
                if (!empty($item['source_movement_id'])) {
                    execute("UPDATE product_stock_movements SET linked_invoice_id = ? WHERE id = ?", [$invoiceId, (int)$item['source_movement_id']]);
                    continue;
                }
                // INCREASE stock in selected warehouse (and sync aggregate products.stock_quantity)
                $lineUnitCost = ($item['factor'] > 0) ? ((float)$item['price'] / (float)$item['factor']) : null;
                addProductStock($item['product_id'], $warehouseId, $item['stock_quantity'], 'purchase', 'purchase_invoice', $invoiceId, 'فاتورة شراء ' . $invoiceNumber, $lineUnitCost, $supplierId ?: null);
            }
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
        
        // Update supplier balance (approved invoices only — drafts touch nothing):
        // - Positive balance means we owe the supplier
        // - remainingAmount > 0 means we still owe from this invoice
        // - remainingAmount < 0 means we overpaid (can apply to old debt)
        if ($supplierId && !$isDraft) {
            // Always update balance with the remaining amount
            execute(
                "UPDATE suppliers SET balance = balance + ? WHERE id = ?",
                [$remainingAmount, $supplierId]
            );
        }

        commit();
        if ($isDraft) {
            logActivity('إنشاء مسودة شراء', "تم حفظ مسودة فاتورة شراء رقم {$invoiceNumber} (بدون حركة مخزون)", $handledBy);
            setSuccess('تم حفظ المسودة بنجاح — لم يتم تحريك المخزون. اعتمدها من القائمة لتطبيقها.');
            header("Location: list.php?type=purchase&status=draft");
            exit;
        }
        logActivity('إنشاء فاتورة شراء', "تم إنشاء فاتورة شراء رقم {$invoiceNumber}", $handledBy);
        
        if (isset($_POST['save_only'])) {
            setSuccess('تم حفظ الفاتورة بنجاح');
            header("Location: list.php?type=purchase");
            exit;
        }

        // Stay on the same page and open the thermal receipt in a modal
        // (same flow as all other receipts: طباعة / إغلاق inside the modal)
        header("Location: purchase.php?printed=$invoiceId");
        exit;
        
    } catch (Exception $e) {
        rollback();
        setError('حدث خطأ: ' . $e->getMessage());
    }
}

// Get suppliers and products with balance (+ stock unit config for purchase lines)
$suppliers = getRows("SELECT id, name, phone, balance FROM suppliers ORDER BY name");
$products = getPurchaseables();
$knownUnits = getKnownUnits();
$warehouses = getAllWarehouses(true);
$userWarehouseId = getCurrentWarehouseId();

// Prefill from a quick-supply movement (?from_supply=ID): the invoice only
// documents the supply — approving it never adds stock twice.
$supplyPrefill = null;
$fromSupplyId = (int)($_GET['from_supply'] ?? 0);
if ($fromSupplyId > 0) {
    $supMov = getRow("SELECT * FROM product_stock_movements WHERE id = ?", [$fromSupplyId]);
    if (!$supMov || $supMov['movement_type'] !== 'supply') {
        setError('حركة التوريد المطلوبة غير موجودة');
    } elseif (!empty($supMov['linked_invoice_id'])) {
        setError('هذا التوريد مرتبط بالفعل بفاتورة أخرى');
    } else {
        $supProd = getRow("SELECT * FROM products WHERE id = ?", [(int)$supMov['product_id']]);
        if (!$supProd) {
            setError('صنف التوريد غير موجود');
        } else {
            $pf = (float)($supProd['units_per_purchase'] ?: 1);
            if ($pf <= 0) { $pf = 1; }
            $sqty = (int)$supMov['qty_in'];
            if ($sqty > 0 && $pf > 0 && fmod((float)$sqty, $pf) == 0.0) {
                $preQty = $sqty / $pf;
                $prePUnit = $supProd['purchase_unit'] ?: $supProd['unit'];
                $preFactor = $pf;
            } else {
                $preQty = $sqty;
                $prePUnit = $supProd['unit'];
                $preFactor = 1;
            }
            $supSup = null;
            if (!empty($supMov['supplier_id'])) {
                $supSup = getRow("SELECT id, name, phone FROM suppliers WHERE id = ?", [(int)$supMov['supplier_id']]);
            }
            $supplyPrefill = [
                'movement_id' => (int)$supMov['id'],
                'product' => $supProd,
                'quantity' => $preQty,
                'purchase_unit' => $prePUnit,
                'factor' => $preFactor,
                'price' => 0,
                'supplier' => $supSup,
                'reason' => $supMov['reason'],
            ];
        }
    }
}

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
                            <th style="width: 30px;">م</th>
                            <th style="width: 70px;">الكود</th>
                            <th>الصنف</th>
                            <th style="width: 70px;">وحدة الشراء</th>
                            <th style="width: 70px;">الكمية</th>
                            <th style="width: 60px;">المعامل</th>
                            <th style="width: 90px;">الناتج بالمخزون</th>
                            <th style="width: 90px;">سعر الشراء</th>
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
                <datalist id="purchaseUnitsList">
                    <?php foreach ($knownUnits as $u): ?>
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
                    🖨️ اعتماد وطباعة
                </button>
                <button type="submit" name="save_only" class="btn btn-success btn-lg" style="flex: 1;">
                    ✅ اعتماد وحفظ
                </button>
                <button type="submit" name="save_draft" class="btn btn-warning btn-lg" style="flex: 1;">
                    📝 حفظ مسودة
                </button>
                <a href="list.php" class="btn btn-secondary btn-lg" style="flex: 1; text-align: center;">❌ إلغاء</a>
            </div>
            <small style="color: #6b7280;">المسودة تُحفظ بدون تحريك المخزون أو حساب المورد — الاعتماد يتم من القائمة أو من صفحة الطباعة.</small>
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
                <input type="text" id="newProductName" class="form-control" required placeholder="مثال: بيبسي زجاجة">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div class="form-group">
                    <label class="form-label" style="font-weight: bold;">الكود</label>
                    <input type="text" id="newProductCode" class="form-control" placeholder="يُنشأ تلقائياً" readonly style="background: #f3f4f6;">
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-weight: bold;">التصنيف</label>
                    <select id="newProductCategory" class="form-control">
                        <option value="">-- بدون تصنيف --</option>
                        <?php foreach (getRows("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name") as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div class="form-group">
                    <label class="form-label" style="font-weight: bold;">وحدة المخزون / البيع *</label>
                    <input type="text" id="newProductUnit" class="form-control" list="purchaseUnitsList" value="قطعة" required>
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-weight: bold;">وحدة الشراء</label>
                    <input type="text" id="newProductPurchaseUnit" class="form-control" list="purchaseUnitsList" placeholder="كرتونة، كيلو">
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div class="form-group">
                    <label class="form-label" style="font-weight: bold;">وحدة الشراء = كم مخزون؟</label>
                    <input type="number" id="newProductFactor" class="form-control" step="any" min="0" value="1" placeholder="مثال: 20">
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-weight: bold;">الحد الأدنى للتنبيه</label>
                    <input type="number" id="newProductMinStock" class="form-control" value="5" min="0">
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div class="form-group">
                    <label class="form-label" style="font-weight: bold;">سعر الشراء (آخر سعر)</label>
                    <input type="number" id="newProductCost" class="form-control" step="0.01" value="0" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-weight: bold;">سعر البيع</label>
                    <input type="number" id="newProductPrice" class="form-control" step="0.01" value="0" placeholder="0.00">
                </div>
            </div>
            <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                <label style="font-size: 0.9em;"><input type="checkbox" id="newProductTrack" checked> 📦 تتبع المخزون</label>
                <label style="font-size: 0.9em;"><input type="checkbox" id="newProductDirectSale" checked> 🧾 بيع مباشر في الكاشير</label>
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

<?php if ($supplyPrefill): ?>
// Prefilled from a quick-supply movement (?from_supply=): stock-neutral line
document.addEventListener('DOMContentLoaded', function() {
    const sp = <?php echo json_encode([
        'movement_id' => $supplyPrefill['movement_id'],
        'product_id' => (int)$supplyPrefill['product']['id'],
        'code' => $supplyPrefill['product']['code'],
        'name' => $supplyPrefill['product']['name'],
        'unit' => $supplyPrefill['product']['unit'],
        'purchase_unit' => $supplyPrefill['purchase_unit'],
        'factor' => (float)$supplyPrefill['factor'],
        'quantity' => $supplyPrefill['quantity'],
        'price' => (float)$supplyPrefill['price'],
        'supplier' => $supplyPrefill['supplier'],
    ], JSON_UNESCAPED_UNICODE); ?>;
    items.push({
        id: itemCounter++,
        product_id: sp.product_id,
        code: sp.code,
        name: sp.name,
        unit: sp.unit,
        purchase_unit: sp.purchase_unit,
        factor: sp.factor,
        quantity: sp.quantity,
        price: sp.price,
        source_movement_id: sp.movement_id
    });
    if (sp.supplier) {
        document.getElementById('supplierName').value = sp.supplier.name || '';
        document.getElementById('supplierPhone').value = sp.supplier.phone || '';
        document.getElementById('supplierId').value = sp.supplier.id || '';
    }
    renderItems();
});
<?php endif; ?>

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
                <strong>${p.kind === 'ingredient' ? '🧪 ' : ''}${p.name}</strong> (${p.code})<br>
                <small style="color: #6b7280;">${p.kind === 'ingredient' ? 'خامة • ' : ''}شراء: ${p.purchase_unit || p.unit} | مخزون: ${p.unit} | المخزون الحالي: ${p.stock_quantity}</small>
            </div>
        `).join('');
        productResults.style.display = 'block';
    } else {
        productResults.innerHTML = '<div class="autocomplete-item" style="color: #dc2626;">لا توجد نتائج — استخدم زر «➕ صنف جديد»</div>';
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
        product_id: product.kind === 'ingredient' ? null : product.id,
        ingredient_id: product.kind === 'ingredient' ? (product.ingredient_id || product.id) : null,
        kind: product.kind || 'product',
        code: product.code,
        name: product.name,
        unit: product.unit,
        purchase_unit: product.purchase_unit || product.unit,
        factor: parseFloat(product.units_per_purchase) > 0 ? parseFloat(product.units_per_purchase) : 1,
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
            <td style="text-align: right;"><strong>${item.kind === 'ingredient' ? '🧪 ' : ''}${item.name}</strong><br><small style="color:#6b7280;">وحدة المخزون: ${item.unit}</small>${item.source_movement_id ? '<br><small style="color:#2563eb; font-weight:700;">🚚 توثيق توريد — الكمية ثابتة</small>' : ''}</td>
            <td>
                <input type="text" list="purchaseUnitsList" value="${item.purchase_unit}"
                       onchange="updatePurchaseUnit(${item.id}, this.value)" ${locked}>
            </td>
            <td>
                <input type="number" min="1" value="${item.quantity}"
                       onchange="updateQuantity(${item.id}, this.value)" ${lockedQty}>
            </td>
            <td>
                <input type="number" step="any" min="0" value="${item.factor}"
                       onchange="updateFactor(${item.id}, this.value)" ${lockedNum} title="وحدة الشراء = كم وحدة مخزون">
            </td>
            <td style="font-weight: bold; color: #059669;">${stockOut} ${item.unit}</td>
            <td>
                <input type="number" step="0.01" value="${item.price}"
                       onchange="updatePrice(${item.id}, this.value)" placeholder="سعر الشراء">
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
        appAlert('يرجى إضافة صنف واحد على الأقل');
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
            appAlert(`المبلغ المدفوع (${paid}) يتجاوز الحد الأقصى (${maxPayment.toFixed(2)})\n\nالفاتورة: ${total.toFixed(2)}\nالمستحق للمورد: ${weOweSupplier.toFixed(2)}`);
        } else {
            appAlert(`المبلغ المدفوع (${paid}) لا يمكن أن يتجاوز قيمة الفاتورة (${total.toFixed(2)})\n\nلا يوجد حساب قديم مستحق للمورد`);
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
        purchase_unit: document.getElementById('newProductPurchaseUnit').value,
        factor: document.getElementById('newProductFactor').value || 1,
        category_id: document.getElementById('newProductCategory').value,
        cost_price: document.getElementById('newProductCost').value || 0,
        price: document.getElementById('newProductPrice').value || 0,
        min_stock: document.getElementById('newProductMinStock').value || 5,
        track_stock: document.getElementById('newProductTrack').checked ? 1 : 0,
        is_direct_sale: document.getElementById('newProductDirectSale').checked ? 1 : 0
    };
    
    fetch('ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(productData)
    })
    .then(r => r.json())
    .then(async data => {
        if (data.success) {
            onProductCreated(data, productData);
        } else if (data.similar && data.similar.length) {
            const names = data.similar.map(s => '• ' + s.name + ' (' + s.code + ')').join('\n');
            if (await appConfirm('⚠️ توجد أصناف مشابهة بالفعل:\n' + names + '\n\nاضغط «موافق» لإنشاء صنف جديد على أي حال، أو «إلغاء» للرجوع واختيار الموجود.')) {
                productData.force_create = 1;
                fetch('ajax.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams(productData)
                })
                .then(r => r.json())
                .then(data2 => {
                    if (data2.success) { onProductCreated(data2, productData); }
                    else { appAlert('❌ خطأ: ' + (data2.error || 'حدث خطأ')); }
                })
                .catch(() => appAlert('❌ حدث خطأ في الاتصال'));
            }
        } else {
            appAlert('❌ خطأ: ' + (data.error || 'حدث خطأ'));
        }
    })
    .catch(() => appAlert('❌ حدث خطأ في الاتصال'));
});

function onProductCreated(data, productData) {
    // Add to invoice (carry the unit config so conversion previews work)
    const newProduct = {
        id: data.product_id,
        code: data.code || productData.code,
        name: productData.name,
        unit: productData.unit,
        purchase_unit: productData.purchase_unit || productData.unit,
        units_per_purchase: productData.factor,
        price: 0,
        stock_quantity: 0
    };
    addProduct(newProduct);

    // Add to products array for future searches
    products.push(newProduct);

    closeNewProductModal();
    appAlert('✅ تم إضافة الصنف بنجاح وإضافته للفاتورة', 'success');
}
</script>

<?php include '../../includes/footer.php'; ?>

<?php if (!empty($_GET['printed']) && (int)$_GET['printed'] > 0): ?>
<script>
// Open the just-saved purchase receipt in the standard modal (same page)
document.addEventListener('DOMContentLoaded', function() {
    if (typeof openReceiptModal === 'function') {
        openReceiptModal('print_purchase.php?id=<?php echo (int)$_GET['printed']; ?>');
    }
});
</script>
<?php endif; ?>
