<?php
/**
 * POS - نقطة البيع
 * شاشة كاشير مستقلة (لا علاقة لها بصفحة الفواتير).
 * كل بيعة تُحفظ كفاتورة بيع عادية حتى تظهر في المخزون والتقارير.
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';

requireLogin();
requirePermission('pos.view');

$pageTitle = 'الكاشير - نقطة البيع';

$categories = [];
if (getRow("SHOW TABLES LIKE 'categories'")) {
    $hasCategoryCol = (bool)getRow("SHOW COLUMNS FROM products LIKE 'category_id'");
    if ($hasCategoryCol) {
        $categories = getRows("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
    }
}
$warehouses = getAllWarehouses(true);
$defaultWarehouseId = (int)getCurrentWarehouseId();
if ($defaultWarehouseId <= 0) {
    $def = getDefaultWarehouse();
    $defaultWarehouseId = $def ? (int)$def['id'] : 0;
}

// Schema readiness (additive POS columns)
$hasSource = (bool)getRow("SHOW COLUMNS FROM invoices LIKE 'source'");
$hasBarcode = (bool)getRow("SHOW COLUMNS FROM products LIKE 'barcode'");
$posReady = $hasSource && $hasBarcode;

$canSell = hasPermission('pos.sell');
$canDiscount = isAdmin() || hasPermission('pos.discount');
$canHold = hasPermission('pos.hold');
$currency = getSetting('currency', 'ج.م');

$categoryIcons = ['📦', '🔌', '💡', '🍳', '🧺', '🛠️', '📺', '🪑'];

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/pos.css?v=<?php echo file_exists(__DIR__ . '/../../assets/css/pos.css') ? filemtime(__DIR__ . '/../../assets/css/pos.css') : time(); ?>">

<?php if (!$posReady): ?>
<div style="margin:12px 16px;padding:14px 16px;background:#fef2f2;border:1px solid #fecaca;border-radius:12px;color:#991b1b;font-weight:700;font-size:13px;line-height:1.9;">
    ⚠️ قاعدة البيانات تحتاج تحديث وحدة الكاشير.<br>
    شغّل الملف <code style="background:#fee2e2;padding:2px 6px;border-radius:6px;">database/migrations/002_pos.sql</code> مرة واحدة، ثم أعد تحميل الصفحة.
    <?php if (isAdmin()): ?>
    <div style="margin-top:10px;">
        <button type="button" id="applyMigrationBtn" style="background:#dc2626;color:#fff;border:none;border-radius:8px;padding:8px 18px;font-weight:800;font-size:12.5px;cursor:pointer;font-family:inherit;">
            ⚙️ تحديث قاعدة البيانات الآن
        </button>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="pos-container" id="posApp">

    <!-- Right: products -->
    <div class="pos-menu-panel">
        <div class="pos-top-bar">
            <a href="<?php echo BASE_URL; ?>index.php" class="pos-chip info" title="العودة للوحة التحكم">
                <span>🏠</span> الرئيسية
            </a>
            <div class="pos-search-wrapper">
                <span class="pos-search-icon">🔍</span>
                <input type="text" id="posSearchInput" class="pos-search-input"
                       placeholder="بحث بالاسم أو الكود أو الباركود..."
                       autofocus autocomplete="off">
            </div>
            <span class="pos-chip" id="todayChip" title="مبيعات الكاشير اليوم">
                <span>🟢</span> اليوم: <strong id="todayTotal">0.00</strong>
            </span>
            <?php if (count($warehouses) > 1): ?>
            <select id="posWarehouseSelect" class="pos-search-input" style="max-width:170px;padding-right:12px;" title="المخزن">
                <?php foreach ($warehouses as $wh): ?>
                <option value="<?php echo (int)$wh['id']; ?>" <?php echo (int)$wh['id'] === $defaultWarehouseId ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($wh['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php else: ?>
            <input type="hidden" id="posWarehouseSelect" value="<?php echo $defaultWarehouseId; ?>">
            <?php endif; ?>
            <?php if ($canHold): ?>
            <button type="button" class="pos-chip info" id="holdsBtn" title="السلات المعلقة">
                <span>💾</span> المعلقة
            </button>
            <?php endif; ?>
        </div>

        <div class="pos-categories-scroll" id="categoriesBar">
            <button type="button" class="category-pill active" data-category-id="">
                <span>✨</span> الكل
            </button>
            <?php foreach ($categories as $i => $cat): ?>
            <button type="button" class="category-pill" data-category-id="<?php echo (int)$cat['id']; ?>">
                <span><?php echo $categoryIcons[$i % count($categoryIcons)]; ?></span>
                <?php echo htmlspecialchars($cat['name']); ?>
            </button>
            <?php endforeach; ?>
        </div>

        <div class="pos-products-grid" id="productsGrid">
            <div class="pos-empty-hint" style="grid-column:1/-1;">
                <div class="big">📦</div>
                جاري تحميل الأصناف...
            </div>
        </div>
    </div>

    <!-- Left: cart -->
    <div class="pos-cart-panel">
        <div class="cart-header">
            <div class="cart-title">
                <span>🛒</span> سلة البيع
                <span id="cartCountBadge" style="background:#ea580c;color:#fff;font-size:11px;padding:2px 7px;border-radius:10px;">0</span>
            </div>
            <button type="button" id="clearCartBtn" style="background:none;border:none;color:#ef4444;font-size:12px;font-weight:700;cursor:pointer;">
                🗑️ مسح
            </button>
        </div>

        <div style="padding:6px 12px;background:#fff;border-bottom:1px solid var(--pos-border);display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <div style="flex:1.2;min-width:140px;position:relative;">
                <input type="text" id="customerNameInput" list="posCustomersList"
                       placeholder="👤 اسم العميل (اختياري)..."
                       style="width:100%;padding:6px 10px;border-radius:6px;border:1px solid #cbd5e1;font-size:12px;font-family:inherit;">
                <datalist id="posCustomersList"></datalist>
                <input type="hidden" id="customerIdInput" value="">
            </div>
            <input type="text" id="customerPhoneInput" placeholder="📞 التليفون..."
                   style="flex:1;min-width:110px;padding:6px 10px;border-radius:6px;border:1px solid #cbd5e1;font-size:12px;font-family:inherit;">
            <span id="customerBalanceBadge" style="font-size:11px;font-weight:800;display:none;"></span>
            <div id="customerValidationMsg" style="flex-basis:100%;font-size:11px;font-weight:700;color:#dc2626;display:none;"></div>
        </div>

        <div class="cart-items-list" id="cartItemsList">
            <div class="cart-empty-state">
                <div style="font-size:38px;margin-bottom:8px;opacity:.6;">🛒</div>
                <div style="font-weight:700;color:#475569;">السلة فارغة</div>
                <div style="font-size:11px;margin-top:4px;">اضغط على أي صنف لإضافته</div>
            </div>
        </div>

        <div class="cart-footer">
            <div class="grand-total-row">
                <span>الإجمالي:</span>
                <span class="amount" id="grandTotalDisplay">0.00 <?php echo htmlspecialchars($currency); ?></span>
            </div>

            <div class="cart-action-btns" style="display:flex;gap:8px;">
                <?php if ($canHold): ?>
                <button type="button" class="btn-defer" id="holdBtn" style="flex:0 0 auto;display:inline-flex;" title="تعليق السلة واسترجاعها لاحقاً">
                    <span>💾</span> تعليق
                </button>
                <?php endif; ?>
                <button type="button" class="btn-checkout" id="checkoutBtn" disabled style="flex:1;">
                    <span>⚡</span> الدفع
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Checkout modal -->
<div class="pos-modal-backdrop" id="checkoutModal">
    <div class="pos-modal" style="max-width:430px;">
        <div class="modal-header">
            <h3 style="font-size:15px;font-weight:800;color:#0f172a;margin:0;">🧾 محاسبة البيعة</h3>
            <button type="button" onclick="closeCheckoutModal()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#64748b;">&times;</button>
        </div>
        <div class="modal-body" id="checkoutModalBody"></div>
    </div>
</div>

<!-- Holds modal -->
<div class="pos-modal-backdrop" id="holdsModal">
    <div class="pos-modal" style="max-width:460px;">
        <div class="modal-header">
            <h3 style="font-size:15px;font-weight:800;color:#0f172a;margin:0;">💾 السلات المعلقة</h3>
            <button type="button" onclick="closeHoldsModal()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#64748b;">&times;</button>
        </div>
        <div class="modal-body" id="holdsModalBody"></div>
    </div>
</div>

<script>
window.POS_CONFIG = {
    baseUrl: <?php echo json_encode(BASE_URL); ?>,
    ajaxUrl: <?php echo json_encode(BASE_URL . 'modules/pos/ajax.php'); ?>,
    customersUrl: <?php echo json_encode(BASE_URL . 'modules/customers/ajax.php'); ?>,
    warehouseId: <?php echo (int)$defaultWarehouseId; ?>,
    currency: <?php echo json_encode($currency); ?>,
    canSell: <?php echo $canSell ? 'true' : 'false'; ?>,
    canDiscount: <?php echo $canDiscount ? 'true' : 'false'; ?>,
    canHold: <?php echo $canHold ? 'true' : 'false'; ?>,
    allowNegativeStock: <?php echo getSetting('allow_negative_stock', '0') === '1' ? 'true' : 'false'; ?>,
    ready: <?php echo $posReady ? 'true' : 'false'; ?>
};
</script>
<script src="<?php echo BASE_URL; ?>assets/js/pos.js?v=<?php echo file_exists(__DIR__ . '/../../assets/js/pos.js') ? filemtime(__DIR__ . '/../../assets/js/pos.js') : time(); ?>"></script>

<?php include '../../includes/footer.php'; ?>
