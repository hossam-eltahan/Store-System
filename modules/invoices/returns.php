<?php
/**
 * Create a capped return against a purchase invoice or a sale order.
 * المرتجعات — بيع وشراء بسقف تراكمي لا يتجاوز بنود الفاتورة الأصلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requireLogin();
requirePermission('returns.view');

$kind = $_GET['kind'] ?? $_POST['kind'] ?? '';
$invoiceId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if (!in_array($kind, ['purchase', 'pos', 'sale'], true) || $invoiceId <= 0) {
    setError('مرتجع غير صالح');
    redirect('list.php');
}

// ---- Load parent + lines with already-returned totals ----
if ($kind === 'pos') {
    $parent = getRow("SELECT *, order_number AS doc_number, DATE(created_at) AS doc_date FROM orders WHERE id = ?", [$invoiceId]);
    $docLabel = 'طلب كاشير';
    if (!$parent) {
        setError('الطلب غير موجود');
        redirect('list.php?type=sale');
    }
    if (orderSessionIsOpen($parent)) {
        setError('الطلب مرتبط بجلسة مفتوحة — عدّل كمياته من الكاشير (تعديل الطلب) بدل الإرجاع حتى لا تختل محاسبة الجلسة');
        redirect('list.php?type=sale');
    }
    $srcLines = getRows(
        "SELECT oi.*, oi.id AS line_id, COALESCE(p.is_service, 0) AS is_service,
                COALESCE((SELECT SUM(ri.quantity) FROM invoice_return_items ri
                          JOIN invoice_returns r ON r.id = ri.return_id
                          WHERE r.invoice_kind = 'pos' AND r.invoice_id = ?
                            AND ri.line_kind = 'order' AND ri.line_id = oi.id), 0) AS returned_qty
         FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id
         WHERE oi.order_id = ? ORDER BY oi.id ASC",
        [$invoiceId, $invoiceId]
    );
} else {
    $wantType = $kind === 'purchase' ? 'purchase' : 'sale';
    $parent = getRow("SELECT *, invoice_number AS doc_number, date AS doc_date FROM invoices WHERE id = ? AND type = ?", [$invoiceId, $wantType]);
    $docLabel = $kind === 'purchase' ? 'فاتورة شراء' : 'فاتورة بيع';
    if (!$parent) {
        setError('الفاتورة غير موجودة');
        redirect('list.php');
    }
    $srcLines = getRows(
        "SELECT ii.*, ii.id AS line_id, COALESCE(p.is_service, 0) AS is_service,
                COALESCE((SELECT SUM(ri.quantity) FROM invoice_return_items ri
                          JOIN invoice_returns r ON r.id = ri.return_id
                          WHERE r.invoice_kind = ? AND r.invoice_id = ?
                            AND ri.line_kind = 'invoice' AND ri.line_id = ii.id), 0) AS returned_qty
         FROM invoice_items ii LEFT JOIN products p ON p.id = ii.product_id
         WHERE ii.invoice_id = ? ORDER BY ii.id ASC",
        [$kind, $invoiceId, $invoiceId]
    );
}

// ---- Submit ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePermission('returns.create');
    $rawLines = json_decode($_POST['lines'] ?? '[]', true);
    $notes = trim($_POST['notes'] ?? '');
    $returnKey = preg_replace('/[^A-Za-z0-9\-_]/', '', (string)($_POST['return_key'] ?? ''));
    $clean = [];
    if (is_array($rawLines)) {
        foreach ($rawLines as $rl) {
            $q = round((float)($rl['quantity'] ?? 0), 3);
            if ((int)($rl['line_id'] ?? 0) > 0 && $q > 0) {
                $clean[] = ['line_id' => (int)$rl['line_id'], 'quantity' => $q];
            }
        }
    }
    $result = createInvoiceReturn($kind, $invoiceId, $clean, $notes, $returnKey ?: null);
    if (!empty($result['success'])) {
        logActivity('إنشاء مرتجع', "مرتجع {$result['return_number']} على {$docLabel} #{$invoiceId} بقيمة {$result['total']}", $_SESSION['full_name'] ?? '');
        setSuccess('تم حفظ المرتجع ' . $result['return_number'] . ' بنجاح');
        // Stay on the same page and open the receipt in a modal (like all invoices)
        redirect('returns.php?kind=' . urlencode($kind) . '&id=' . $invoiceId . '&printed=' . (int)$result['return_id']);
    } else {
        setError('تعذر الحفظ: ' . ($result['error'] ?? 'خطأ غير معروف'));
    }
}

$formKey = 'rt-' . date('YmdHis') . '-' . substr(md5(uniqid((string)mt_rand(), true)), 0, 8);
$history = getRows(
    "SELECT r.*, u.full_name AS user_name FROM invoice_returns r
     LEFT JOIN users u ON u.id = r.created_by
     WHERE r.invoice_kind = ? AND r.invoice_id = ? ORDER BY r.id DESC",
    [$kind, $invoiceId]
);

$pageTitle = 'مرتجع ' . ($kind === 'purchase' ? 'مشتريات' : 'مبيعات') . ' — ' . ($parent['doc_number'] ?? $invoiceId);
include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>↩️ مرتجع <?php echo $kind === 'purchase' ? 'مشتريات' : 'مبيعات'; ?> — <?php echo htmlspecialchars($docLabel . ' ' . ($parent['doc_number'] ?? '')); ?></span>
            <a href="list.php" class="btn btn-secondary">↩️ رجوع للقائمة</a>
        </div>
        <div class="card-body">
            <div class="alert alert-warning" style="margin-bottom: 15px;">
                ⚠️ لا يمكن إرجاع أي صنف بأكثر من الكمية الموجودة في الفاتورة الأصلية —
                والمرتجعات المتكررة على نفس الفاتورة تُجمع معًا ولا تتجاوز الحد أبدًا.
            </div>

            <form method="POST" id="returnForm">
                <input type="hidden" name="kind" value="<?php echo htmlspecialchars($kind); ?>">
                <input type="hidden" name="id" value="<?php echo $invoiceId; ?>">
                <input type="hidden" name="return_key" value="<?php echo $formKey; ?>">
                <input type="hidden" name="lines" id="linesData" value="[]">

                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>الصنف</th>
                                <th>كمية الفاتورة</th>
                                <th>سبق إرجاعه</th>
                                <th>المتاح للإرجاع</th>
                                <th>كمية الإرجاع</th>
                                <th>سعر الوحدة</th>
                                <th>قيمة الإرجاع</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($srcLines as $sl):
                                $lineQty = (float)$sl['quantity'];
                                $already = (float)$sl['returned_qty'];
                                $left = max(0, $lineQty - $already);
                                $nm = $kind === 'pos' ? $sl['product_name'] : ($sl['product_name'] ?? $sl['item_name']);
                                $up = $kind === 'pos' ? ((float)$sl['unit_price'] + (float)($sl['modifiers_total'] ?? 0)) : (float)$sl['unit_price'];
                            ?>
                            <tr>
                                <td style="text-align: right;"><strong><?php echo htmlspecialchars($nm); ?></strong></td>
                                <td><?php echo rtrim(rtrim(number_format($lineQty, 3), '0'), '.'); ?></td>
                                <td style="color: #b45309; font-weight: 700;"><?php echo rtrim(rtrim(number_format($already, 3), '0'), '.'); ?></td>
                                <td style="color: #059669; font-weight: 700;"><?php echo rtrim(rtrim(number_format($left, 3), '0'), '.'); ?></td>
                                <td>
                                    <?php if ((int)($sl['is_service'] ?? 0) === 1): ?>
                                    <span class="badge badge-secondary">خدمة — غير قابلة للإرجاع</span>
                                    <?php elseif ($left > 0): ?>
                                    <input type="number" step="any" min="0" max="<?php echo $left; ?>" value="0"
                                           data-line="<?php echo (int)$sl['line_id']; ?>" data-price="<?php echo $up; ?>" data-max="<?php echo $left; ?>"
                                           class="form-control ret-qty" style="width: 90px;" oninput="calcReturnTotals()">
                                    <?php else: ?>
                                    <span class="badge badge-secondary">مكتمل الإرجاع</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format($up, 2); ?></td>
                                <td class="ret-line-total" style="font-weight: bold;">0.00</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div style="display: flex; gap: 10px; align-items: flex-end; margin-top: 12px; flex-wrap: wrap;">
                    <div class="form-group" style="flex: 2; min-width: 200px;">
                        <label>ملاحظات</label>
                        <input type="text" name="notes" class="form-control" placeholder="سبب الإرجاع...">
                    </div>
                    <div class="total-box highlight" style="padding: 8px 18px;">
                        <div class="total-label">إجمالي المرتجع</div>
                        <div class="total-value" id="retTotalDisplay">0.00</div>
                    </div>
                    <?php if (hasPermission('returns.create')): ?>
                    <button type="submit" class="btn btn-warning btn-lg" id="retSubmitBtn">↩️ حفظ المرتجع</button>
                    <?php endif; ?>
                </div>
            </form>

            <?php if (!empty($history)): ?>
            <div style="margin-top: 25px;">
                <h4>📜 مرتجعات سابقة على هذه الفاتورة</h4>
                <div class="table-container">
                    <table class="table">
                        <thead><tr><th>الرقم</th><th>التاريخ</th><th>القيمة</th><th>المستخدم</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($history as $h): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($h['return_number']); ?></strong></td>
                                <td><?php echo $h['return_date']; ?></td>
                                <td><?php echo formatCurrency($h['total_amount']); ?></td>
                                <td><?php echo htmlspecialchars($h['user_name'] ?? ''); ?></td>
                                <td><a href="#" onclick="openReceiptModal('print_return.php?id=<?php echo (int)$h['id']; ?>'); return false;" class="btn btn-primary btn-sm">🖨️</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function calcReturnTotals() {
    let total = 0;
    document.querySelectorAll('.ret-qty').forEach(function(inp) {
        let q = parseFloat(inp.value) || 0;
        const max = parseFloat(inp.dataset.max) || 0;
        if (q > max) { q = max; inp.value = max; }
        if (q < 0) { q = 0; inp.value = 0; }
        const line = q * (parseFloat(inp.dataset.price) || 0);
        total += line;
        const cell = inp.closest('tr').querySelector('.ret-line-total');
        if (cell) cell.textContent = line.toFixed(2);
    });
    document.getElementById('retTotalDisplay').textContent = total.toFixed(2);
}
document.getElementById('returnForm').addEventListener('submit', function(e) {
    const lines = [];
    document.querySelectorAll('.ret-qty').forEach(function(inp) {
        const q = parseFloat(inp.value) || 0;
        if (q > 0) lines.push({line_id: parseInt(inp.dataset.line), quantity: q});
    });
    if (lines.length === 0) {
        e.preventDefault();
        alert('أدخل كمية إرجاع لصنف واحد على الأقل');
        return;
    }
    document.getElementById('linesData').value = JSON.stringify(lines);
    const btn = document.getElementById('retSubmitBtn');
    if (btn) btn.disabled = true;
});
</script>

<?php include '../../includes/footer.php'; ?>

<?php if (!empty($_GET['printed']) && (int)$_GET['printed'] > 0): ?>
<script>
// Open the just-saved return receipt in the standard modal (same page)
document.addEventListener('DOMContentLoaded', function() {
    if (typeof openReceiptModal === 'function') {
        openReceiptModal('print_return.php?id=<?php echo (int)$_GET['printed']; ?>');
    }
});
</script>
<?php endif; ?>
