<?php
/**
 * Customers List
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
require_once '../../config/customer_accounts.php';
requirePermission('customers.view');

$pageTitle = 'إدارة العملاء';

// Handle delete
if (isset($_GET['delete'])) {
    requirePermission('customers.delete');
    $id = $_GET['delete'];
    $customer = getRow("SELECT * FROM customers WHERE id = ?", [$id]);

    if ($customer) {
        // Check if customer has invoices or confirmed cafe activity
        $hasInvoices = getRow("SELECT id FROM invoices WHERE customer_id = ?", [$id]);
        $hasOrders   = getRow("SELECT id FROM orders WHERE customer_id = ? LIMIT 1", [$id]);
        $hasSessions = getRow("SELECT id FROM gaming_sessions WHERE customer_id = ? UNION SELECT id FROM billiard_sessions WHERE customer_id = ? UNION SELECT id FROM table_sessions WHERE customer_id = ? LIMIT 1", [$id, $id, $id]);

        if ($hasInvoices || $hasOrders || $hasSessions) {
            setError('لا يمكن حذف العميل لوجود فواتير أو طلبات وإجازات مسجلة باسمه');
        } else {
            execute("DELETE FROM customers WHERE id = ?", [$id]);
            logActivity('حذف عميل', "تم حذف العميل: {$customer['name']}");
            setSuccess('تم حذف العميل بنجاح');
        }
    }

    redirect('index.php');
}

// Search handling
$search = trim($_GET['search'] ?? '');

// Build query
$sql = "SELECT * FROM customers WHERE 1=1";
$params = [];

if ($search !== '') {
    // بحث عربي متسامح: "احمد"= "أحمد" و"حمزه"="حمزة" — نفس التطبيع على العمود والبحث
    $norm = mb_search_normalize($search);
    $sql .= " AND (" . search_like_expr('name') . " LIKE ? OR " . search_like_expr('phone') . " LIKE ? OR " . search_like_expr('address') . " LIKE ?)";
    $searchParam = "%$norm%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Pagination
$perPage = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// Get total count
$countSql = str_replace("SELECT *", "SELECT COUNT(*) as count", $sql);
$totalItems = getRow($countSql, $params)['count'];
$totalPages = ceil($totalItems / $perPage);

// Get paginated results
$sql .= " ORDER BY name LIMIT $perPage OFFSET $offset";
$customers = getRows($sql, $params);

// Attach open-dues (unsettled rest) from the live account engine
foreach ($customers as &$cRow) {
    // دايماً: ممكن يبقى عند العميل فواتير مفتوحة مع رصيد دفتر صفر (أو دائن)
    $stats = customer_account_stats((int)$cRow['id']);
    if ($stats['open_count'] > 0 || abs($stats['total_billed']) > 0.001) {
        $cRow['open_due'] = $stats['open_total'];
        $cRow['open_count'] = $stats['open_count'];
    } else {
        $cRow['open_due'] = 0.0;
        $cRow['open_count'] = 0;
    }
}
unset($cRow);

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header d-flex justify-between align-center" style="display: flex; justify-content: space-between; align-items: center;">
            <div style="font-weight: 800; font-size: 1.1rem; display: flex; align-items: center; gap: 8px;">
                <span>👥</span> قائمة العملاء
            </div>
            <div style="display: flex; gap: 8px;">
                <a href="../../index.php" class="btn btn-secondary" style="border-radius: 8px;">🏠 الرئيسية</a>
                <?php if (hasPermission('customers.add')): ?>
                <a href="add.php" class="btn btn-primary" style="border-radius: 8px; background: #ea580c; border-color: #ea580c;">+ إضافة عميل جديد</a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card-body">
            <!-- Search -->
            <form method="GET" class="mb-1" id="searchForm">
                <div class="form-row">
                    <div class="form-group" style="flex: 3;">
                        <input type="text" name="search" id="customerSearch" class="form-control" 
                               placeholder="🔍 بحث بالاسم أو التليفون..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <?php if ($search !== ''): ?>
                    <div class="form-group" style="flex: 1;">
                        <a href="index.php" class="btn btn-secondary btn-block">✕ إلغاء</a>
                    </div>
                    <?php endif; ?>
                </div>
            </form>
            
            <!-- Count with Pagination -->
            <div class="search-results-count">
                <span>📊 إجمالي العملاء: <strong><?php echo $totalItems; ?></strong></span>
                <div class="pagination" style="display: flex; align-items: center; gap: 8px;">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-secondary btn-sm">← السابق</a>
                    <?php endif; ?>
                    <span class="badge badge-info">صفحة <?php echo $page; ?> من <?php echo max(1, $totalPages); ?></span>
                    <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-secondary btn-sm">التالي →</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Customers Table -->
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>الاسم</th>
                            <th>التليفون</th>
                            <th>الرصيد اللي عليه</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="customersTableBody">
                        <?php if (empty($customers)): ?>
                        <tr>
                            <td colspan="4" class="text-center">لا يوجد عملاء مطابقين للبحث</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><strong><?php echo $customer['name']; ?></strong></td>
                            <td><?php echo $customer['phone']; ?></td>
                            <td>
                                <?php if ($customer['open_due'] > 0.009): ?>
                                    <span class="badge badge-danger" style="direction: ltr;"><?php echo formatCurrency($customer['open_due']); ?></span>
                                    <small style="color:#6b7280;">على <?php echo $customer['open_count']; ?> عنصر غير محاسب</small>
                                <?php elseif ($customer['balance'] < 0): ?>
                                    <span class="text-danger" style="direction: ltr;"><?php echo formatCurrency(abs($customer['balance'])); ?></span>
                                <?php elseif ($customer['balance'] > 0): ?>
                                    <span class="text-success"><?php echo formatCurrency($customer['balance']); ?> (رصيد دائن)</span>
                                <?php else: ?>
                                    <span class="badge badge-success">0.00 — سليم</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="view.php?id=<?php echo $customer['id']; ?>" class="btn btn-success btn-sm" title="البروفايل والحساب">👤 بروفايل</a>
                                <?php if (hasPermission('customers.edit')): ?>
                                <a href="edit.php?id=<?php echo $customer['id']; ?>" class="btn btn-primary btn-sm" title="تعديل">✏️</a>
                                <?php endif; ?>
                                <?php if ($customer['open_due'] > 0.009 || $customer['balance'] < 0): ?>
                                <button type="button" class="btn btn-warning btn-sm btn-collect"
                                        data-id="<?php echo $customer['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($customer['name']); ?>"
                                        data-owed="<?php echo number_format((float)$customer['open_due'], 2, '.', ''); ?>"
                                        data-open-count="<?php echo (int)$customer['open_count']; ?>"
                                        data-balance="<?php echo number_format((float)($customer['balance'] ?? 0), 2, '.', ''); ?>"
                                        title="تحصيل مستحقات">💵</button>
                                <?php endif; ?>
                                <?php if (hasPermission('customers.delete')): ?>
                                <button type="button" class="btn btn-danger btn-sm btn-delete"
                                        data-name="<?php echo htmlspecialchars($customer['name']); ?>"
                                        data-url="index.php?delete=<?php echo $customer['id']; ?>" title="حذف">🗑️</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal-overlay" style="display: none;">
    <div class="modal-box">
        <div class="modal-header">
            <span style="font-size: 32px;">⚠️</span>
            <h3>تأكيد الحذف</h3>
        </div>
        <div class="modal-body">
            <p>هل أنت متأكد من حذف العميل:</p>
            <p id="deleteItemName" style="font-weight: bold; font-size: 1.2em; color: #dc2626;"></p>
            <p style="color: #666; font-size: 0.9em;">⚠️ هذا الإجراء لا يمكن التراجع عنه!</p>
        </div>
        <div class="modal-footer">
            <a id="confirmDeleteBtn" href="#" class="btn btn-danger">🗑️ نعم، احذف</a>
            <button type="button" class="btn btn-secondary" id="cancelDeleteBtn">❌ إلغاء</button>
        </div>
    </div>
</div>

<!-- Collect modal (تليفون عميل → نافذة بدل صفحة جديدة) -->
<div id="collectModal" class="modal-overlay" style="display: none;">
    <div class="modal-box" style="max-width: 430px; text-align: right; padding: 24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
            <h3 style="margin:0; font-size:15.5px; font-weight:900;" id="collectTitle">💰 تحصيل مستحقات</h3>
            <button type="button" id="collectCloseBtn" style="background:none;border:none;font-size:22px;cursor:pointer;line-height:1;">&times;</button>
        </div>
        <div id="collectInfoBox" style="border-radius:10px; padding:10px 14px; font-size:12.5px; line-height:1.9; margin-bottom:12px;"></div>
        <input type="hidden" id="collectCustomerId" value="">
        <input type="hidden" id="collectMode" value="settle">
        <div style="margin-bottom:12px;">
            <label style="font-size:12px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">المبلغ المحصل:<span id="collectHint" style="color:#dc2626; margin-right:8px;"></span></label>
            <input type="text" inputmode="decimal" id="collectAmount" min="0" autocomplete="off"
                   style="width:100%; padding:11px 14px; border-radius:10px; border:1.5px solid #cbd5e1; font-size:16px; font-weight:800; font-family:inherit; text-align:center;">
        </div>
        <div style="margin-bottom:16px;">
            <label style="font-size:12px; font-weight:700; color:#475569; display:block; margin-bottom:5px;">طريقة الدفع:</label>
            <select id="collectMethod" style="width:100%; padding:9px 12px; border-radius:10px; border:1.5px solid #cbd5e1; font-size:13px; font-family:inherit;">
                <option value="cash">💵 كاش</option>
                <option value="card">💳 فيزا / بطاقة</option>
                <option value="instapay">⚡ إنستاباي (InstaPay)</option>
                <option value="vodafone_cash">📱 فودافون كاش</option>
            </select>
        </div>
        <button type="button" id="collectSubmitBtn" style="width:100%; background:linear-gradient(135deg,#059669,#047857); color:#fff; border:none; border-radius:10px; padding:12px; font-size:15px; font-weight:900; cursor:pointer;">
            ✅ تأكيد التحصيل
        </button>
        <p style="font-size:10.5px; color:#94a3b8; text-align:center; margin-top:10px;">بعد الحفظ هتفتح نافذة الطباعة تلقائياً 🖨️</p>
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
    padding: 30px;
    max-width: 400px;
    width: 90%;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from { transform: translateY(-50px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.modal-header {
    margin-bottom: 20px;
}

.modal-header h3 {
    margin: 10px 0 0 0;
    color: #333;
}

.modal-body {
    margin-bottom: 25px;
}

.modal-body p {
    margin: 10px 0;
}

.modal-footer {
    display: flex;
    gap: 10px;
    justify-content: center;
}

.modal-footer .btn {
    min-width: 120px;
}

.btn-info {
    background: linear-gradient(135deg, #0ea5e9, #0284c7);
    color: white;
}

.btn-info:hover {
    background: linear-gradient(135deg, #0284c7, #0369a1);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-search
    var searchInput = document.getElementById('customerSearch');
    var searchForm = document.getElementById('searchForm');
    
    if (searchInput && searchForm) {
        var timeout = null;
        searchInput.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                searchForm.submit();
            }, 500);
        });
    }
    
    // Delete Modal
    var modal = document.getElementById('deleteModal');
    var confirmBtn = document.getElementById('confirmDeleteBtn');
    var cancelBtn = document.getElementById('cancelDeleteBtn');
    var itemNameSpan = document.getElementById('deleteItemName');
    
    var deleteButtons = document.querySelectorAll('.btn-delete');
    for (var j = 0; j < deleteButtons.length; j++) {
        deleteButtons[j].onclick = function(e) {
            e.preventDefault();
            var itemName = this.getAttribute('data-name');
            var deleteUrl = this.getAttribute('data-url');
            
            itemNameSpan.textContent = itemName;
            confirmBtn.href = deleteUrl;
            modal.style.display = 'flex';
        };
    }
    
    if (cancelBtn) {
        cancelBtn.onclick = function() {
            modal.style.display = 'none';
        };
    }
    
    if (modal) {
        modal.onclick = function(e) {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        };
    }
    
    document.onkeydown = function(e) {
        if (e.key === 'Escape') {
            if (modal && modal.style.display === 'flex') modal.style.display = 'none';
            if (collectModal && collectModal.style.display === 'flex') collectModal.style.display = 'none';
        }
    };
});

/* ===== نافذة تحصيل المستحقات من ليستة العملاء ===== */
var collectModal = document.getElementById('collectModal');
var collectBusy = false;

function collectOpen(btn) {
    var id = btn.getAttribute('data-id');
    var name = btn.getAttribute('data-name');
    var owed = parseFloat(btn.getAttribute('data-owed')) || 0;
    var openCount = parseInt(btn.getAttribute('data-open-count')) || 0;
    var balance = parseFloat(btn.getAttribute('data-balance')) || 0;

    // من عليه فواتير مفتوحة → محاسبة الفواتير (طباعة إيصال المحاسبة المدمج).
    // الرصيد العتيق في الدفتر (بدون فواتير) → تحصيل عام (طباعة إيصال دفع).
    var mode = owed > 0.009 ? 'settle' : 'balance';
    document.getElementById('collectMode').value = mode;
    document.getElementById('collectCustomerId').value = id;

    var info = document.getElementById('collectInfoBox');
    var max = 0;
    if (mode === 'settle') {
        document.getElementById('collectTitle').textContent = '💰 محاسبة فواتير العميل';
        info.style.background = '#fff7ed';
        info.style.border = '1px solid #fdba74';
        info.style.color = '#7c2d12';
        max = owed;
        info.innerHTML = 'العميل: <strong>' + name + '</strong><br>محاسبة <strong>' + openCount + '</strong> عنصر مفتوح بقيمة <strong>' +
            owed.toFixed(2) + '</strong> ج.م — المتبقي (لو حصّلت أقل) يفضل مفتوح على حسابه.';
    } else {
        document.getElementById('collectTitle').textContent = '💵 تحصيل عام من رصيد الدفتر';
        info.style.background = '#fef3c7';
        info.style.border = '1px solid #fcd34d';
        info.style.color = '#92400e';
        max = Math.abs(balance);
        info.innerHTML = 'العميل: <strong>' + name + '</strong><br>مديونية قديمة/يدوية في الدفتر بقيمة <strong>' +
            max.toFixed(2) + '</strong> ج.م (مش مربوطة بفواتير — محاسبتها من نافذة بروفايل العميل).';
    }

    var input = document.getElementById('collectAmount');
    input.max = max.toFixed(2);
    input.value = max.toFixed(2);
    document.getElementById('collectHint').textContent = '';
    document.getElementById('collectMethod').value = 'cash';
    collectModal.style.display = 'flex';
    input.focus();
}

function collectNote() {
    var input = document.getElementById('collectAmount');
    normDigitInput(input); // keyboard Arabic digits support
    var max = parseFloat(input.max) || 0;
    var val = parseFloat(input.value) || 0;
    // ملاحظة فقط — من إعادة كتابة القيمة حتى لا يقفز المؤشر أثناء الكتابة
    document.getElementById('collectHint').textContent = (val > max + 0.009)
        ? '⚠️ أكبر من المستحق (' + max.toFixed(2) + ')' : '';
}

function collectClose() { collectModal.style.display = 'none'; }

async function collectSubmit() {
    if (collectBusy) return;
    collectBusy = true;
    var btn = document.getElementById('collectSubmitBtn');
    btn.disabled = true;
    try {
        var input = document.getElementById('collectAmount');
        var amount = Math.round((parseFloat(input.value) || 0) * 100) / 100;
        var max = Math.round((parseFloat(input.max) || 0) * 100) / 100;
        var method = document.getElementById('collectMethod').value;
        var mode = document.getElementById('collectMode').value;
        var cid = document.getElementById('collectCustomerId').value;

        if (amount <= 0) { appAlert('اكتب مبلغ أكبر من صفر', 'error'); return; }
        if (amount > max + 0.009) { appAlert('المبلغ أكبر من المستحق (' + max.toFixed(2) + ' ج.م)', 'error'); return; }

        var url, body;
        if (mode === 'settle') {
            url = 'ajax.php?action=settle';
            body = { customer_id: parseInt(cid), items: [], amount: amount, method: method };
        } else {
            url = 'ajax.php?action=collect_general';
            body = { customer_id: parseInt(cid), amount: amount, method: method };
        }
        var res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        var data = await res.json();
        if (!data.success) { appAlert(data.error || 'فشل تسجيل التحصيل', 'error'); return; }

        collectClose();
        // نافذة الطباعة بعد الدفع — الريلود بعد ما المستخدم يقفلها بنفسه
        var printUrl = (mode === 'settle')
            ? 'settlement_receipt.php?id=' + encodeURIComponent(data.summary.settlement_id)
            : '../payments/print_customer.php?id=' + encodeURIComponent(data.payment_id);
        openReceiptModal(printUrl, function() { location.reload(); });
    } catch (err) {
        console.error(err);
        appAlert('خطأ في الاتصال بالسيرفر', 'error');
    } finally {
        collectBusy = false;
        if (btn) btn.disabled = false;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    collectModal = document.getElementById('collectModal');
    var buttons = document.querySelectorAll('.btn-collect');
    for (var k = 0; k < buttons.length; k++) {
        buttons[k].onclick = function() { collectOpen(this); };
    }
    var closeBtn = document.getElementById('collectCloseBtn');
    if (closeBtn) closeBtn.onclick = collectClose;
    document.getElementById('collectAmount').oninput = collectNote;
    document.getElementById('collectSubmitBtn').onclick = collectSubmit;
    collectModal.onclick = function(e) { if (e.target === collectModal) collectClose(); };
});
</script>

<?php include '../../includes/footer.php'; ?>
