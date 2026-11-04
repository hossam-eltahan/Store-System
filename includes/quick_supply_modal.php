<?php
/**
 * Shared quick-supply modal (توريد سريع).
 *
 * Expected variables:
 *  - $supplyAction             Form action (relative to the including page)
 *  - $supplyReturnTo           'products' or 'stock'
 *  - $supplyWarehouses         Active warehouses
 *  - $supplySuppliers          Suppliers list
 *  - $supplyDefaultWarehouseId Preselected warehouse id
 */

$supplyAction = $supplyAction ?? 'supply.php';
$supplyReturnTo = ($supplyReturnTo ?? 'products') === 'stock' ? 'stock' : 'products';
$supplyWarehouses = $supplyWarehouses ?? getAllWarehouses(true);
$supplySuppliers = $supplySuppliers ?? [];
$supplyDefaultWarehouseId = (int)($supplyDefaultWarehouseId ?? getCurrentWarehouseId());
?>
<div id="quickSupplyModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 10050; justify-content: center; align-items: center; padding: 15px;">
    <div style="background: #fff; border-radius: 15px; padding: 25px; width: 100%; max-width: 540px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-height: 92vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 2px solid #e5e7eb; padding-bottom: 10px;">
            <h3 style="margin: 0; color: #1f2937;">🚚 توريد سريع <small style="color:#6b7280;font-weight:600;">(بدون فاتورة)</small></h3>
            <button type="button" onclick="closeQuickSupplyModal()" style="background: #dc2626; color: white; border: none; border-radius: 50%; width: 32px; height: 32px; cursor: pointer; font-size: 16px; line-height: 1;">✕</button>
        </div>

        <form method="POST" action="<?php echo htmlspecialchars($supplyAction); ?>" id="quickSupplyForm">
            <input type="hidden" name="product_id" id="qsProductId">
            <input type="hidden" name="return_to" value="<?php echo $supplyReturnTo; ?>">

            <div class="form-group" style="margin-bottom: 12px;">
                <label class="form-label" style="font-weight: bold;">الصنف</label>
                <input type="text" id="qsProductName" class="form-control" readonly style="background: #f3f4f6; font-weight: bold;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div class="form-group">
                    <label class="form-label" style="font-weight: bold;">المخزن *</label>
                    <select name="warehouse_id" id="qsWarehouseId" class="form-control" required>
                        <?php foreach ($supplyWarehouses as $wh): ?>
                            <option value="<?php echo (int)$wh['id']; ?>" <?php echo (int)$wh['id'] === $supplyDefaultWarehouseId ? 'selected' : ''; ?>>
                                🏢 <?php echo htmlspecialchars($wh['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-weight: bold;">الكمية المضافة *</label>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <input type="number" name="quantity" id="qsQuantity" class="form-control" min="1" step="1" required placeholder="مثال: 20">
                        <span id="qsUnitLabel" style="font-size: 12px; color: #64748b; white-space: nowrap; font-weight: 700;">قطعة</span>
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div class="form-group">
                    <label class="form-label" style="font-weight: bold;">سعر التكلفة للوحدة (اختياري)</label>
                    <input type="number" name="unit_cost" id="qsUnitCost" class="form-control" step="0.01" min="0" placeholder="للتقييم فقط">
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-weight: bold;">المورد (اختياري)</label>
                    <select name="supplier_id" id="qsSupplierId" class="form-control">
                        <option value="">-- بدون مورد --</option>
                        <?php foreach ($supplySuppliers as $supplier): ?>
                            <option value="<?php echo (int)$supplier['id']; ?>"><?php echo htmlspecialchars($supplier['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 12px;">
                <label class="form-label" style="font-weight: bold;">ملاحظات</label>
                <input type="text" name="notes" id="qsNotes" class="form-control" placeholder="مثال: توريد نقدي من السوق">
            </div>

            <div style="background: #f0fdf4; border-radius: 8px; padding: 9px 12px; font-size: 0.85em; color: #166534; margin-bottom: 10px; font-weight: 700;">
                ✅ تزيد هذه العملية رصيد المخزن المحدد فورًا وتُسجَّل في سجل التوريدات.
            </div>
            <div style="background: #fef3c7; border-radius: 8px; padding: 9px 12px; font-size: 0.85em; color: #92400e; margin-bottom: 15px;">
                ⚠️ لا تنشئ فاتورة شراء ولا تضيف أي مديونية على المورد.
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" style="flex: 1; background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 12px; border: none; border-radius: 8px; font-size: 1.05em; cursor: pointer; font-weight: 800;">✅ تأكيد التوريد</button>
                <button type="button" onclick="closeQuickSupplyModal()" style="background: #6b7280; color: white; padding: 12px 20px; border: none; border-radius: 8px; cursor: pointer;">إلغاء</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var modal = document.getElementById('quickSupplyModal');
    if (!modal) return;

    window.openQuickSupplyModal = function (product) {
        if (!product) return;

        document.getElementById('qsProductId').value = product.id || '';
        document.getElementById('qsProductName').value = product.name || '';
        document.getElementById('qsUnitLabel').textContent = product.unit || 'قطعة';

        var warehouseSelect = document.getElementById('qsWarehouseId');
        if (warehouseSelect && product.warehouseId) {
            warehouseSelect.value = String(product.warehouseId);
        }

        document.getElementById('qsQuantity').value = '';
        document.getElementById('qsUnitCost').value = '';
        document.getElementById('qsNotes').value = '';
        document.getElementById('qsSupplierId').value = '';

        modal.style.display = 'flex';
        setTimeout(function () {
            var qty = document.getElementById('qsQuantity');
            if (qty) qty.focus();
        }, 50);
    };

    window.closeQuickSupplyModal = function () {
        modal.style.display = 'none';
    };

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            window.closeQuickSupplyModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.style.display === 'flex') {
            window.closeQuickSupplyModal();
        }
    });
})();
</script>
