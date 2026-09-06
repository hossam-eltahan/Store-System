<?php
/**
 * Stock Transfer Between Warehouses
 * تحويل المخزون بين المخازن
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';

requirePermission('warehouses.transfer');

$pageTitle = 'تحويل مخزون بين المخازن';
$errors = [];

// Available active warehouses
$warehouses = getAllWarehouses(true);

if (count($warehouses) < 2) {
    // Cannot transfer if less than 2 warehouses
    $canTransfer = false;
} else {
    $canTransfer = true;
}

// Default from warehouse: from GET, or current user's default, or first warehouse
$defaultFromWh = (int)($_GET['from_warehouse_id'] ?? getCurrentWarehouseId());
$selectedProduct = (int)($_GET['product_id'] ?? 0);

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'transfer') {
    $fromWarehouseId = (int)($_POST['from_warehouse_id'] ?? 0);
    $toWarehouseId = (int)($_POST['to_warehouse_id'] ?? 0);
    $transferDate = trim($_POST['transfer_date'] ?? date('Y-m-d'));
    $notes = trim($_POST['notes'] ?? '');
    $items = $_POST['items'] ?? [];

    if ($fromWarehouseId <= 0 || $toWarehouseId <= 0) {
        $errors[] = 'يرجى تحديد مخزن المصدر ومخزن الوجهة';
    } elseif ($fromWarehouseId === $toWarehouseId) {
        $errors[] = 'لا يمكن التحويل من وإلى نفس المخزن';
    }

    if (empty($transferDate)) {
        $errors[] = 'يرجى تحديد تاريخ التحويل';
    }

    // Filter valid items
    $validItems = [];
    if (is_array($items)) {
        foreach ($items as $item) {
            $prodId = (int)($item['product_id'] ?? 0);
            $qty = (int)($item['quantity'] ?? 0);
            $itemNotes = trim($item['notes'] ?? '');
            if ($prodId > 0 && $qty > 0) {
                $validItems[] = [
                    'product_id' => $prodId,
                    'quantity' => $qty,
                    'notes' => $itemNotes
                ];
            }
        }
    }

    if (empty($validItems)) {
        $errors[] = 'يرجى اختيار صنف واحد على الأقل وتحديد كمية أكبر من الصفر للتحويل';
    } else {
        // Validate stock availability in source warehouse
        foreach ($validItems as $vItem) {
            $prod = getRow("SELECT name FROM products WHERE id = ?", [$vItem['product_id']]);
            $currentStock = getWarehouseStock($fromWarehouseId, $vItem['product_id']);
            if ($currentStock < $vItem['quantity']) {
                $pName = $prod ? $prod['name'] : "صنف #{$vItem['product_id']}";
                $errors[] = "الرصيد غير كافٍ للصنف ({$pName}) في مخزن المصدر. الرصيد المتاح: {$currentStock}، المطلوب: {$vItem['quantity']}";
            }
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $transferNumber = generateTransferNumber();
            $userId = getCurrentUserId();

            $transferId = insert(
                "INSERT INTO stock_transfers (transfer_number, from_warehouse_id, to_warehouse_id, transfer_date, status, notes, created_by) 
                 VALUES (?, ?, ?, ?, 'completed', ?, ?)",
                [$transferNumber, $fromWarehouseId, $toWarehouseId, $transferDate, $notes, $userId]
            );

            foreach ($validItems as $vItem) {
                // Deduct from source warehouse
                updateWarehouseStock($fromWarehouseId, $vItem['product_id'], $vItem['quantity'], 'subtract');
                
                // Add to destination warehouse
                updateWarehouseStock($toWarehouseId, $vItem['product_id'], $vItem['quantity'], 'add');

                // Insert transfer item row
                insert(
                    "INSERT INTO stock_transfer_items (transfer_id, product_id, quantity, notes) VALUES (?, ?, ?, ?)",
                    [$transferId, $vItem['product_id'], $vItem['quantity'], $vItem['notes']]
                );
            }

            $pdo->commit();

            $fromName = getWarehouseName($fromWarehouseId);
            $toName = getWarehouseName($toWarehouseId);
            logActivity('تحويل مخزون', "تم تحويل {$transferNumber} بعدد " . count($validItems) . " صنف من [{$fromName}] إلى [{$toName}]");

            setSuccess("تم إتمام التحويل بنجاح برقم ({$transferNumber})");
            redirect('transfer.php');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'حدث خطأ أثناء تنفيذ عملية التحويل: ' . $e->getMessage();
        }
    }
}

// Fetch products with their stock across all warehouses for the JS selector
$allProducts = getRows("SELECT p.id, p.code, p.name, p.unit FROM products p ORDER BY p.name ASC");
$stocksRaw = getRows("SELECT warehouse_id, product_id, quantity FROM warehouse_stock");
$stocksMap = [];
foreach ($stocksRaw as $sr) {
    $stocksMap[$sr['warehouse_id']][$sr['product_id']] = (int)$sr['quantity'];
}

// Fetch recent stock transfers
$recentTransfers = getRows(
    "SELECT st.*, 
            w_from.name as from_warehouse_name,
            w_to.name as to_warehouse_name,
            u.full_name as user_name,
            (SELECT COUNT(*) FROM stock_transfer_items WHERE transfer_id = st.id) as items_count,
            (SELECT SUM(quantity) FROM stock_transfer_items WHERE transfer_id = st.id) as total_units
     FROM stock_transfers st
     JOIN warehouses w_from ON st.from_warehouse_id = w_from.id
     JOIN warehouses w_to ON st.to_warehouse_id = w_to.id
     LEFT JOIN users u ON st.created_by = u.id
     ORDER BY st.id DESC LIMIT 30"
);

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
        <div>
            <h1 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <span>🔄</span> تحويل مخزون بين المخازن
            </h1>
            <p style="color: var(--text-secondary); margin: 5px 0 0 0; font-size: 14px;">نقل بضائع من مخزن إلى آخر مع تحديث أرصدة كل مخزن تلقائياً</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="index.php" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px;">
                <span>🏢</span> قائمة المخازن
            </a>
        </div>
    </div>

    <?php if (!$canTransfer): ?>
    <div class="alert alert-warning" style="margin-bottom: 25px; padding: 20px; font-size: 15px; border-radius: 10px;">
        ⚠️ <strong>تنبيه:</strong> يتطلب نظام التحويل وجود مخزنين على الأقل في النظام.
        <a href="add.php" class="btn btn-sm btn-primary" style="margin-right: 15px;">➕ إضافة مخزن إضافي الآن</a>
    </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger" style="margin-bottom: 20px;">
        <ul style="margin: 0; padding-right: 20px;">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if ($canTransfer): ?>
    <!-- Transfer Form Card -->
    <div class="card" style="margin-bottom: 30px;">
        <div class="card-header" style="background: linear-gradient(135deg, #d97706, #b45309); color: white; font-weight: 700;">
            📦 إنشاء عملية تحويل مخزون جديدة
        </div>
        <div class="card-body" style="padding: 25px;">
            <form method="POST" action="" id="transferForm">
                <input type="hidden" name="action" value="transfer">

                <!-- Warehouses & Date Row -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 25px; background: var(--bg-primary); padding: 20px; border-radius: 12px;">
                    <!-- From Warehouse -->
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 700; margin-bottom: 6px; display: block;">
                            📤 من مخزن (المصدر) <span style="color: red;">*</span>
                        </label>
                        <select name="from_warehouse_id" id="fromWarehouseSelect" class="form-control" required style="font-weight: 600;">
                            <?php foreach ($warehouses as $w): ?>
                                <option value="<?php echo $w['id']; ?>" <?php echo $w['id'] == $defaultFromWh ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($w['name']); ?> <?php echo ((int)$w['is_default'] === 1) ? '(الافتراضي)' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- To Warehouse -->
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 700; margin-bottom: 6px; display: block;">
                            📥 إلى مخزن (الوجهة) <span style="color: red;">*</span>
                        </label>
                        <select name="to_warehouse_id" id="toWarehouseSelect" class="form-control" required style="font-weight: 600;">
                            <?php foreach ($warehouses as $w): ?>
                                <option value="<?php echo $w['id']; ?>" <?php echo ($w['id'] != $defaultFromWh && !isset($selectedToWh)) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($w['name']); ?> <?php echo ((int)$w['is_default'] === 1) ? '(الافتراضي)' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Date -->
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 700; margin-bottom: 6px; display: block;">
                            📅 تاريخ التحويل <span style="color: red;">*</span>
                        </label>
                        <input type="date" name="transfer_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <!-- Notes -->
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 700; margin-bottom: 6px; display: block;">
                            📝 سبب التحويل / ملاحظات
                        </label>
                        <input type="text" name="notes" class="form-control" placeholder="مثال: تغذية فرع، طلب طلبية عميل...">
                    </div>
                </div>

                <!-- Transfer Items Section -->
                <div style="margin-bottom: 25px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <h3 style="margin: 0; font-size: 16px; font-weight: 700;">📋 أصناف البضاعة المراد تحويلها</h3>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="addItemBtn" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 600;">
                            <span>➕</span> إضافة صنف آخر
                        </button>
                    </div>

                    <div style="overflow-x: auto;">
                        <table class="table" id="itemsTable" style="margin: 0; vertical-align: middle;">
                            <thead>
                                <tr style="background: var(--bg-primary);">
                                    <th style="min-width: 250px;">المنتج</th>
                                    <th style="width: 140px;">الرصيد المتاح بالمصدر</th>
                                    <th style="width: 140px;">الكمية المحولة</th>
                                    <th>ملاحظات البند</th>
                                    <th style="width: 60px; text-align: center;">حذف</th>
                                </tr>
                            </thead>
                            <tbody id="itemsTbody">
                                <!-- Row template rendered by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div style="display: flex; justify-content: flex-end; gap: 12px;">
                    <a href="index.php" class="btn btn-secondary">إلغاء</a>
                    <button type="submit" class="btn btn-warning" style="padding: 10px 30px; font-weight: 700; font-size: 15px;">
                        🚀 تأكيد وتنفيذ التحويل
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recent Transfers History -->
    <div class="card">
        <div class="card-header" style="background: linear-gradient(135deg, #0284c7, #0369a1); color: white; display: flex; justify-content: space-between; align-items: center;">
            <span style="font-weight: 700;">📜 سجل آخر التحويلات بين المخازن</span>
            <span class="badge" style="background: rgba(255,255,255,0.2); padding: 4px 10px; border-radius: 20px;">
                <?php echo count($recentTransfers); ?> عملية تحويل
            </span>
        </div>
        <div class="card-body" style="padding: 0; overflow-x: auto;">
            <table class="table" style="margin: 0; vertical-align: middle;">
                <thead>
                    <tr style="background: var(--bg-primary);">
                        <th style="width: 120px;">رقم التحويل</th>
                        <th>من مخزن</th>
                        <th style="width: 30px; text-align: center;">⬅️</th>
                        <th>إلى مخزن</th>
                        <th>التاريخ</th>
                        <th>الأصناف والقطع</th>
                        <th>بواسطة</th>
                        <th>ملاحظات</th>
                        <th style="width: 100px; text-align: center;">تفاصيل</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentTransfers)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                            لا توجد عمليات تحويل مخزون سابقة مسجلة
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($recentTransfers as $trf): ?>
                        <tr>
                            <td>
                                <strong style="color: #0284c7;"><?php echo htmlspecialchars($trf['transfer_number']); ?></strong>
                            </td>
                            <td>
                                <span class="badge" style="background: #fee2e2; color: #b91c1c; font-size: 12px; padding: 4px 8px;">
                                    📤 <?php echo htmlspecialchars($trf['from_warehouse_name']); ?>
                                </span>
                            </td>
                            <td style="text-align: center; color: var(--text-secondary);">➔</td>
                            <td>
                                <span class="badge" style="background: #dcfce7; color: #15803d; font-size: 12px; padding: 4px 8px;">
                                    📥 <?php echo htmlspecialchars($trf['to_warehouse_name']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($trf['transfer_date']); ?></td>
                            <td>
                                <strong><?php echo $trf['items_count']; ?> صنف</strong>
                                <small style="color: var(--text-secondary);">(إجمالي <?php echo number_format($trf['total_units']); ?> قطعة)</small>
                            </td>
                            <td>👤 <?php echo htmlspecialchars($trf['user_name'] ?? 'غير معروف'); ?></td>
                            <td>
                                <small style="color: var(--text-secondary);">
                                    <?php echo htmlspecialchars($trf['notes'] ?? '—'); ?>
                                </small>
                            </td>
                            <td style="text-align: center;">
                                <button type="button" class="btn btn-sm btn-info view-details-btn" 
                                        data-transfer-id="<?php echo $trf['id']; ?>"
                                        data-transfer-num="<?php echo htmlspecialchars($trf['transfer_number']); ?>"
                                        style="padding: 4px 10px; font-size: 12px;">
                                    🔍 عرض
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal for Transfer Details -->
<div id="transferModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: center; padding: 20px;">
    <div style="background: white; border-radius: 12px; width: 100%; max-width: 600px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); overflow: hidden;">
        <div style="background: linear-gradient(135deg, #0284c7, #0369a1); color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center;">
            <h4 style="margin: 0; font-size: 16px;" id="modalTitle">تفاصيل التحويل</h4>
            <button type="button" onclick="closeModal()" style="background: none; border: none; color: white; font-size: 20px; cursor: pointer;">&times;</button>
        </div>
        <div style="padding: 20px;" id="modalBody">
            جاري التحميل...
        </div>
        <div style="padding: 12px 20px; background: var(--bg-primary); text-align: left;">
            <button type="button" class="btn btn-secondary" onclick="closeModal()">إغلاق</button>
        </div>
    </div>
</div>

<script>
// Data maps
const products = <?php echo json_encode($allProducts); ?>;
const stocksMap = <?php echo json_encode($stocksMap); ?>;
const selectedInitialProductId = <?php echo (int)$selectedProduct; ?>;

let rowIndex = 0;

function getProductStock(warehouseId, productId) {
    if (stocksMap[warehouseId] && stocksMap[warehouseId][productId] !== undefined) {
        return parseInt(stocksMap[warehouseId][productId]) || 0;
    }
    return 0;
}

function addTransferRow(preselectedProductId = 0) {
    const tbody = document.getElementById('itemsTbody');
    const tr = document.createElement('tr');
    tr.id = 'row_' + rowIndex;

    const fromWhId = document.getElementById('fromWarehouseSelect').value;

    let optionsHtml = '<option value="">-- اختر صنفاً --</option>';
    products.forEach(p => {
        const isSel = (preselectedProductId && preselectedProductId == p.id) ? 'selected' : '';
        optionsHtml += `<option value="${p.id}" ${isSel}>${p.name} (${p.code})</option>`;
    });

    tr.innerHTML = `
        <td>
            <select name="items[${rowIndex}][product_id]" class="form-control product-select" required onchange="onProductChange(${rowIndex})">
                ${optionsHtml}
            </select>
        </td>
        <td>
            <input type="text" class="form-control available-stock-input" id="avail_${rowIndex}" readonly 
                   style="background: #f1f5f9; font-weight: 700; color: #0284c7;" value="—">
        </td>
        <td>
            <input type="number" name="items[${rowIndex}][quantity]" class="form-control qty-input" id="qty_${rowIndex}" 
                   min="1" required placeholder="1" style="font-weight: 700;" onchange="validateQty(${rowIndex})">
        </td>
        <td>
            <input type="text" name="items[${rowIndex}][notes]" class="form-control" placeholder="ملاحظة اختيارية">
        </td>
        <td style="text-align: center;">
            <button type="button" class="btn btn-sm btn-danger" onclick="removeRow(${rowIndex})" style="padding: 4px 8px;">✕</button>
        </td>
    `;

    tbody.appendChild(tr);
    if (preselectedProductId) {
        onProductChange(rowIndex);
    }
    rowIndex++;
}

function removeRow(idx) {
    const tbody = document.getElementById('itemsTbody');
    const tr = document.getElementById('row_' + idx);
    if (tr) {
        if (tbody.children.length > 1) {
            tr.remove();
        } else {
            alert('يجب أن يحتوي التحويل على صنف واحد على الأقل');
        }
    }
}

function onProductChange(idx) {
    const tr = document.getElementById('row_' + idx);
    if (!tr) return;
    const prodSelect = tr.querySelector('.product-select');
    const availInput = document.getElementById('avail_' + idx);
    const qtyInput = document.getElementById('qty_' + idx);
    const fromWhId = document.getElementById('fromWarehouseSelect').value;

    const prodId = prodSelect.value;
    if (prodId) {
        const stock = getProductStock(fromWhId, prodId);
        availInput.value = stock;
        qtyInput.max = stock;
        if (stock <= 0) {
            availInput.style.color = '#dc2626';
            qtyInput.value = 0;
            qtyInput.disabled = true;
        } else {
            availInput.style.color = '#0284c7';
            qtyInput.disabled = false;
            if (!qtyInput.value || parseInt(qtyInput.value) <= 0) {
                qtyInput.value = 1;
            } else if (parseInt(qtyInput.value) > stock) {
                qtyInput.value = stock;
            }
        }
    } else {
        availInput.value = '—';
        qtyInput.value = '';
    }
}

function validateQty(idx) {
    const availInput = document.getElementById('avail_' + idx);
    const qtyInput = document.getElementById('qty_' + idx);
    const avail = parseInt(availInput.value) || 0;
    const qty = parseInt(qtyInput.value) || 0;

    if (qty > avail) {
        alert('الكمية المطلوبة أكبر من الرصيد المتاح بالمخزن!');
        qtyInput.value = avail > 0 ? avail : 0;
    }
}

// When from warehouse changes, refresh all rows' available stock
document.getElementById('fromWarehouseSelect')?.addEventListener('change', function() {
    const fromVal = this.value;
    const toSelect = document.getElementById('toWarehouseSelect');
    
    // Ensure toWarehouse is different
    if (toSelect.value === fromVal) {
        for (let opt of toSelect.options) {
            if (opt.value !== fromVal) {
                toSelect.value = opt.value;
                break;
            }
        }
    }

    // Refresh stocks on all open rows
    const tbody = document.getElementById('itemsTbody');
    for (let i = 0; i < tbody.children.length; i++) {
        const tr = tbody.children[i];
        const rowId = tr.id.replace('row_', '');
        onProductChange(rowId);
    }
});

document.getElementById('toWarehouseSelect')?.addEventListener('change', function() {
    const fromSelect = document.getElementById('fromWarehouseSelect');
    if (this.value === fromSelect.value) {
        alert('لا يمكن أن يكون مخزن الوجهة هو نفس مخزن المصدر');
        for (let opt of this.options) {
            if (opt.value !== fromSelect.value) {
                this.value = opt.value;
                break;
            }
        }
    }
});

document.getElementById('addItemBtn')?.addEventListener('click', function() {
    addTransferRow();
});

// Init first row
document.addEventListener('DOMContentLoaded', function() {
    addTransferRow(selectedInitialProductId);

    // Modal click listeners
    document.querySelectorAll('.view-details-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const trfId = this.getAttribute('data-transfer-id');
            const trfNum = this.getAttribute('data-transfer-num');
            openModal(trfId, trfNum);
        });
    });
});

function openModal(id, num) {
    const modal = document.getElementById('transferModal');
    const title = document.getElementById('modalTitle');
    const body = document.getElementById('modalBody');
    
    title.innerText = 'تفاصيل أمر التحويل: ' + num;
    body.innerHTML = '<div style="text-align:center; padding: 20px;">⏳ جاري جلب التفاصيل...</div>';
    modal.style.display = 'flex';

    fetch(`transfer_ajax.php?id=${id}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                let itemsHtml = `
                    <div style="margin-bottom: 15px; font-size: 14px;">
                        <div><strong>من:</strong> ${data.transfer.from_warehouse_name} ➔ <strong>إلى:</strong> ${data.transfer.to_warehouse_name}</div>
                        <div><strong>التاريخ:</strong> ${data.transfer.transfer_date} | <strong>المسؤول:</strong> ${data.transfer.user_name || 'غير معروف'}</div>
                        ${data.transfer.notes ? `<div><strong>الملاحظات:</strong> ${data.transfer.notes}</div>` : ''}
                    </div>
                    <table class="table" style="margin: 0; font-size: 13px;">
                        <thead>
                            <tr style="background: #f1f5f9;">
                                <th>#</th>
                                <th>اسم الصنف</th>
                                <th>الكود</th>
                                <th>الكمية</th>
                                <th>ملاحظات</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                data.items.forEach((it, i) => {
                    itemsHtml += `
                        <tr>
                            <td>${i+1}</td>
                            <td><strong>${it.product_name}</strong></td>
                            <td><code>${it.product_code}</code></td>
                            <td><strong style="color: #0284c7;">${it.quantity} ${it.unit || 'قطعة'}</strong></td>
                            <td>${it.notes || '—'}</td>
                        </tr>
                    `;
                });
                itemsHtml += '</tbody></table>';
                body.innerHTML = itemsHtml;
            } else {
                body.innerHTML = '<div class="alert alert-danger">' + (data.message || 'حدث خطأ') + '</div>';
            }
        })
        .catch(err => {
            body.innerHTML = '<div class="alert alert-danger">حدث خطأ في الاتصال بالخادم</div>';
        });
}

function closeModal() {
    document.getElementById('transferModal').style.display = 'none';
}
</script>

<?php include '../../includes/footer.php'; ?>
