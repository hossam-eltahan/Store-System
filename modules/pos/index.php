<?php
/**
 * Modern Fast POS Screen - شاشة كاشير كافيه عروسة النيل
 * Touch-Friendly & High-Performance
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';

requireLogin();

if (!hasPermission('pos.view')) {
    showAccessDenied();
}

$pageTitle = 'شاشة كاشير الكافيه - POS';

// جلب أقسام المنيو النشطة
$categories = getRows("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");

// جلب حالة الوردية الحالية
$currentShift = getRow("SELECT * FROM shifts WHERE user_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1", [getCurrentUserId()]);

// إدراج الهيدر
include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<link rel="stylesheet" href="../../assets/css/pos.css?v=2.0">

<style>
/* Modern POS Styles */
:root {
    --pos-primary: #ea580c;
    --pos-primary-hover: #c2410c;
    --pos-bg: #f8fafc;
    --pos-card: #ffffff;
    --pos-text: #0f172a;
    --pos-border: #e2e8f0;
}

body {
    background-color: #f1f5f9;
}

.pos-container {
    display: grid;
    grid-template-columns: 1fr 390px;
    gap: 14px;
    height: calc(100vh - 65px);
    margin: 0;
    padding: 12px 16px;
    overflow: hidden;
    box-sizing: border-box;
}

@media (max-width: 1024px) {
    .pos-container {
        grid-template-columns: 1fr;
        height: auto;
        overflow: visible;
    }
}

/* Right Panel: Products Section */
.pos-menu-panel {
    display: flex;
    flex-direction: column;
    background: var(--pos-card);
    border-radius: 14px;
    border: 1px solid var(--pos-border);
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    overflow: hidden;
}

/* Category Filter Bar */
.pos-top-bar {
    padding: 12px 16px;
    border-bottom: 1px solid var(--pos-border);
    display: flex;
    gap: 12px;
    align-items: center;
    background: #ffffff;
}

.pos-search-wrapper {
    position: relative;
    flex: 1;
}

.pos-search-input {
    width: 100%;
    padding: 10px 14px 10px 38px;
    border-radius: 10px;
    border: 1.5px solid #cbd5e1;
    font-size: 14px;
    font-family: inherit;
    transition: all 0.2s;
}

.pos-search-input:focus {
    border-color: var(--pos-primary);
    outline: none;
    box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.15);
}

.pos-search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 16px;
    opacity: 0.5;
}

.pos-categories-scroll {
    display: flex;
    gap: 8px;
    padding: 10px 16px;
    overflow-x: auto;
    background: #f8fafc;
    border-bottom: 1px solid var(--pos-border);
    white-space: nowrap;
    scrollbar-width: thin;
}

.category-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    color: #475569;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    user-select: none;
}

.category-pill:hover {
    transform: translateY(-1px);
    border-color: #cbd5e1;
    color: #0f172a;
}

.category-pill.active {
    background: var(--pos-primary);
    color: #ffffff;
    border-color: var(--pos-primary);
    box-shadow: 0 4px 10px rgba(234, 88, 12, 0.25);
}

/* Products Grid */
.pos-products-grid {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 12px;
    align-content: start;
}

.pos-product-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 12px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    user-select: none;
    min-height: 105px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}

.pos-product-card:hover {
    transform: translateY(-2px);
    border-color: var(--pos-primary);
    box-shadow: 0 6px 14px rgba(234, 88, 12, 0.12);
}

.pos-product-card:active {
    transform: scale(0.97);
}

.product-name {
    font-size: 13px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 6px;
    line-height: 1.35;
}

.product-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: auto;
}

.product-price {
    font-size: 14px;
    font-weight: 800;
    color: var(--pos-primary);
}

.variant-indicator {
    font-size: 10px;
    background: #eff6ff;
    color: #2563eb;
    border: 1px solid #bfdbfe;
    padding: 2px 6px;
    border-radius: 6px;
    font-weight: 700;
}

/* Left Panel: Cart Section */
.pos-cart-panel {
    background: var(--pos-card);
    border-radius: 14px;
    border: 1px solid var(--pos-border);
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.cart-header {
    padding: 12px 16px;
    border-bottom: 1px solid var(--pos-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8fafc;
}

.cart-title {
    font-size: 15px;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 8px;
    color: #0f172a;
}

/* Order Type Selector */
.order-type-tabs {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 4px;
    padding: 8px 12px;
    background: #ffffff;
    border-bottom: 1px solid var(--pos-border);
}

.order-type-btn {
    padding: 8px 4px;
    text-align: center;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    font-size: 11px;
    font-weight: 700;
    color: #64748b;
    cursor: pointer;
    transition: all 0.2s;
}

.order-type-btn:hover {
    background: #f1f5f9;
    color: #0f172a;
}

.order-type-btn.active {
    background: #0f172a;
    color: #ffffff;
    border-color: #0f172a;
}

/* Destination details (Gaming device / Table) */
.destination-picker {
    padding: 8px 12px;
    background: #fff7ed;
    border-bottom: 1px dashed #fdba74;
    display: none;
    font-size: 12px;
}

.destination-picker.show {
    display: block;
}

/* Cart Items List */
.cart-items-list {
    flex: 1;
    overflow-y: auto;
    padding: 8px 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.cart-empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #94a3b8;
    text-align: center;
    padding: 20px;
}

.cart-item-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 10px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    transition: border-color 0.2s;
}

.cart-item-card:hover {
    border-color: #cbd5e1;
}

.cart-item-row-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.cart-item-title {
    font-weight: 700;
    font-size: 13px;
    color: #1e293b;
    line-height: 1.3;
}

.cart-item-variant {
    font-size: 11px;
    color: #64748b;
    margin-right: 4px;
}

.cart-item-mods {
    font-size: 10.5px;
    color: #0284c7;
    margin-top: 2px;
}

.cart-item-row-bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 4px;
}

.qty-control {
    display: flex;
    align-items: center;
    gap: 6px;
}

.qty-btn {
    width: 26px;
    height: 26px;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    background: #f8fafc;
    color: #0f172a;
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 13px;
    transition: background 0.15s;
}

.qty-btn:hover {
    background: #e2e8f0;
}

.qty-number {
    font-size: 13px;
    font-weight: 800;
    min-width: 22px;
    text-align: center;
}

.cart-item-total {
    font-size: 14px;
    font-weight: 800;
    color: #0f172a;
}

/* Cart Footer & Totals */
.cart-footer {
    padding: 12px 14px;
    background: #f8fafc;
    border-top: 1px solid var(--pos-border);
}

.totals-breakdown {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 12px;
    font-size: 12.5px;
}

.totals-row {
    display: flex;
    justify-content: space-between;
    color: #475569;
}

.grand-total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 18px;
    font-weight: 900;
    color: #0f172a;
    padding-top: 6px;
    border-top: 1px dashed #cbd5e1;
}

.grand-total-row .amount {
    color: var(--pos-primary);
}

/* Payment Methods */
.payment-methods-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 6px;
    margin-bottom: 10px;
}

.pay-btn {
    padding: 6px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    background: #fff;
    font-size: 11px;
    font-weight: 700;
    color: #334155;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
}

.pay-btn.active {
    background: #eff6ff;
    border-color: #3b82f6;
    color: #1d4ed8;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
}

/* Action Buttons */
.cart-action-btns {
    display: flex;
    gap: 8px;
}

.btn-checkout {
    flex: 1;
    background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%);
    color: white;
    border: none;
    border-radius: 10px;
    padding: 12px;
    font-size: 15px;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(234, 88, 12, 0.3);
    transition: all 0.2s;
}

.btn-checkout:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(234, 88, 12, 0.4);
}

.btn-checkout:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

.btn-defer {
    background: #334155;
    color: white;
    border: none;
    border-radius: 10px;
    padding: 12px 14px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
}

/* Modal styles for Variants & Modifiers */
.pos-modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 16px;
}

.pos-modal {
    background: #ffffff;
    border-radius: 16px;
    width: 100%;
    max-width: 440px;
    overflow: hidden;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
    animation: modalPop 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes modalPop {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}

.modal-header {
    padding: 14px 18px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-body {
    padding: 16px 18px;
    max-height: 65vh;
    overflow-y: auto;
}

.options-group-title {
    font-size: 12px;
    font-weight: 800;
    color: #64748b;
    text-transform: uppercase;
    margin-bottom: 8px;
}

.options-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-bottom: 16px;
}

.option-item {
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    padding: 10px;
    cursor: pointer;
    transition: all 0.15s;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.option-item:hover {
    border-color: #cbd5e1;
}

.option-item.selected {
    border-color: var(--pos-primary);
    background: #fff7ed;
}

.option-name {
    font-weight: 700;
    font-size: 13px;
    color: #0f172a;
}

.option-price {
    font-size: 12px;
    font-weight: 800;
    color: var(--pos-primary);
}
</style>

<div class="pos-container">

    <!-- Right: Products & Menu -->
    <div class="pos-menu-panel">
        
        <!-- Search & Quick Filters -->
        <div class="pos-top-bar">
            <a href="../../index.php" title="العودة للوحة التحكم" style="background:#f1f5f9; color:#334155; border:1px solid #cbd5e1; padding:8px 14px; border-radius:10px; font-weight:800; font-size:12.5px; text-decoration:none; display:inline-flex; align-items:center; gap:6px; flex-shrink:0;">
                <span>🏠</span> الرئيسية
            </a>
            <div class="pos-search-wrapper">
                <span class="pos-search-icon">🔍</span>
                <input type="text" id="posSearchInput" class="pos-search-input" placeholder="بحث باسم الصنف أو الكود أو الباركود..." autofocus autocomplete="off">
            </div>
            <?php if (!$currentShift): ?>
            <a href="../shifts/index.php" style="background:#fee2e2; color:#dc2626; border:1px solid #fecaca; padding:6px 12px; border-radius:8px; font-weight:700; font-size:12px; text-decoration:none; display:inline-flex; align-items:center; gap:4px; flex-shrink:0;">
                <span>⚠️</span> الوردية مغلقة
            </a>
            <?php else: ?>
            <div style="font-size:12px; background:#ecfdf5; color:#059669; border:1px solid #a7f3d0; padding:6px 12px; border-radius:8px; font-weight:700;">
                <span>🟢 وردية نشطة: #<?php echo htmlspecialchars($currentShift['shift_number']); ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Categories Scrollable Bar -->
        <div class="pos-categories-scroll" id="categoriesBar">
            <button class="category-pill active" data-category-id="">
                <span>✨</span> الكل
            </button>
            <?php foreach ($categories as $cat): ?>
            <button class="category-pill" data-category-id="<?php echo $cat['id']; ?>">
                <span><?php echo $cat['icon'] ?: '☕'; ?></span> <?php echo htmlspecialchars($cat['name']); ?>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- Products Grid Area -->
        <div class="pos-products-grid" id="productsGrid">
            <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #64748b;">
                جاري تحميل قائمة المنيو...
            </div>
        </div>

    </div>

    <!-- Left: Cart & Payment -->
    <div class="pos-cart-panel">
        
        <!-- Cart Header -->
        <div class="cart-header">
            <div class="cart-title">
                <span>🛒</span> سلة الطلب
                <span id="cartCountBadge" style="background:#ea580c; color:#fff; font-size:11px; padding:2px 7px; border-radius:10px;">0</span>
            </div>
            <button id="clearCartBtn" style="background:none; border:none; color:#ef4444; font-size:12px; font-weight:700; cursor:pointer;" title="مسح السلة">
                🗑️ مسح
            </button>
        </div>

        <!-- Order Type Selection -->
        <div class="order-type-tabs">
            <div class="order-type-btn active" data-type="takeaway">🛍️ تيك أواي</div>
            <div class="order-type-btn" data-type="dine_in">🪑 طاولة صالة</div>
            <div class="order-type-btn" data-type="gaming_ps">🎮 بلايستيشن</div>
            <div class="order-type-btn" data-type="gaming_billiard">🎱 بلياردو</div>
        </div>

        <!-- Dynamic Destination Pickers -->
        <div class="destination-picker" id="destinationPicker">
            <!-- Will be populated dynamically based on active order type -->
        </div>

        <!-- Customer Name Input -->
        <div style="padding: 6px 12px; background: #fff; border-bottom: 1px solid var(--pos-border); display: flex; gap: 8px;">
            <input type="text" id="customerNameInput" placeholder="اسم العميل (اختياري)..." style="flex: 1; padding: 6px 10px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 12px; font-family: inherit;">
            <input type="text" id="orderNotesInput" placeholder="ملاحظات الطلب..." style="flex: 1; padding: 6px 10px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 12px; font-family: inherit;">
        </div>

        <!-- Cart Items List -->
        <div class="cart-items-list" id="cartItemsList">
            <div class="cart-empty-state">
                <div style="font-size: 38px; margin-bottom: 8px; opacity: 0.6;">☕</div>
                <div style="font-weight: 700; color: #475569;">السلة فارغة</div>
                <div style="font-size: 11px; margin-top: 4px;">اضغط على أي صنف من المنيو لإضافته هنا</div>
            </div>
        </div>

        <!-- Cart Footer & Checkout -->
        <div class="cart-footer">
            <div class="totals-breakdown">
                <div class="totals-row">
                    <span>المجموع الفرعي:</span>
                    <span id="subtotalDisplay">0.00 ج.م</span>
                </div>
                <div class="totals-row" style="color: #dc2626; display: flex; align-items: center; justify-content: space-between;">
                    <span>
                        الخصم: 
                        <button type="button" id="openDiscountBtn" style="background:#fee2e2; border:none; color:#dc2626; font-size:10px; padding:2px 6px; border-radius:4px; cursor:pointer; font-weight:bold;">تعديل</button>
                    </span>
                    <span id="discountDisplay">0.00 ج.م</span>
                </div>
                <div class="grand-total-row">
                    <span>الإجمالي:</span>
                    <span class="amount" id="grandTotalDisplay">0.00 ج.م</span>
                </div>
            </div>

            <!-- Payment Methods -->
            <div class="payment-methods-grid">
                <div class="pay-btn active" data-method="cash">💵 كاش</div>
                <div class="pay-btn" data-method="card">💳 فيزا</div>
                <div class="pay-btn" data-method="instapay">⚡ إنستاباي</div>
                <div class="pay-btn" data-method="vodafone_cash">📱 فودافون</div>
            </div>

            <!-- Checkout Buttons -->
            <div class="cart-action-btns">
                <button class="btn-checkout" id="checkoutBtn" disabled>
                    <span>⚡</span> إتمام الطلب وطباعة البون
                </button>
                <button class="btn-defer" id="deferBtn" style="display:none;" title="إضافة للحساب المعلق للجهاز / الطاولة دون دفع فوري">
                    <span>⏳</span> تعليق بالحساب
                </button>
            </div>
        </div>

    </div>

</div>

<!-- Modal: Variant & Modifiers Picker -->
<div class="pos-modal-backdrop" id="variantModal">
    <div class="pos-modal">
        <div class="modal-header">
            <div>
                <h3 id="modalProductName" style="font-size: 15px; font-weight: 800; color: #0f172a; margin: 0;">اسم المنتج</h3>
                <div id="modalProductBasePrice" style="font-size: 12px; color: var(--pos-primary); font-weight: 700;">30.00 ج.م</div>
            </div>
            <button type="button" onclick="closeVariantModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #64748b;">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Variants -->
            <div id="variantsGroupSection">
                <div class="options-group-title">اختر الحجم / النوع:</div>
                <div class="options-grid" id="modalVariantsGrid"></div>
            </div>

            <!-- Modifiers -->
            <div id="modifiersGroupSection" style="margin-top: 14px;">
                <div class="options-group-title">الإضافات والصوصات (اختياري):</div>
                <div class="options-grid" id="modalModifiersGrid"></div>
            </div>

            <!-- Item Specific Notes -->
            <div style="margin-top: 12px;">
                <div class="options-group-title">ملاحظة على هذا الصنف (مثل: سكر خفيف):</div>
                <input type="text" id="modalItemNote" placeholder="ملاحظة خاصة..." style="width: 100%; padding: 8px 10px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 12px; font-family: inherit;">
            </div>
        </div>
        <div style="padding: 12px 18px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <div style="font-size: 16px; font-weight: 900; color: var(--pos-primary);" id="modalTotalCalculated">
                0.00 ج.م
            </div>
            <button type="button" id="modalAddToCartBtn" style="background: var(--pos-primary); color: white; border: none; border-radius: 8px; padding: 10px 22px; font-weight: 800; cursor: pointer; font-size: 13.5px;">
                إضافة للسلة
            </button>
        </div>
    </div>
</div>

<!-- Modal: Discount Input -->
<div class="pos-modal-backdrop" id="discountModal">
    <div class="pos-modal" style="max-width: 340px;">
        <div class="modal-header">
            <h3 style="font-size: 15px; font-weight: 800; color: #0f172a; margin: 0;">تطبيق خصم</h3>
            <button type="button" onclick="document.getElementById('discountModal').style.display='none'" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <div class="modal-body" style="padding: 18px;">
            <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">قيمة الخصم بالجنيه:</label>
            <input type="number" id="discountAmountInput" min="0" step="1" value="0" style="width: 100%; padding: 10px; border-radius: 8px; border: 1.5px solid #cbd5e1; font-size: 16px; font-weight: bold;">
        </div>
        <div style="padding: 12px 18px; background: #f8fafc; border-top: 1px solid #e2e8f0; text-align: left;">
            <button type="button" onclick="applyDiscount()" style="background:#dc2626; color:white; border:none; border-radius:8px; padding:8px 18px; font-weight:800; cursor:pointer;">تطبيق الخصم</button>
        </div>
    </div>
</div>

<script>
// State Management
let products = [];
let cart = [];
let currentCategory = '';
let currentOrderType = 'takeaway';
let currentPaymentMethod = 'cash';
let discountAmount = 0;
let destinations = { gaming_sessions: [], billiard_sessions: [], table_sessions: [], available_tables: [] };

// Pending product for modal
let activeProductModal = null;
let selectedVariant = null;
let selectedModifiers = [];

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    loadProducts();
    loadDestinations();
    setupEventListeners();
});

function setupEventListeners() {
    // Categories filter
    document.querySelectorAll('.category-pill').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.category-pill').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentCategory = btn.dataset.categoryId;
            loadProducts();
        });
    });

    // Search input
    let searchTimeout = null;
    document.getElementById('posSearchInput').addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            loadProducts();
        }, 200);
    });

    // Order type tabs
    document.querySelectorAll('.order-type-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.order-type-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentOrderType = btn.dataset.type;
            updateDestinationUI();
        });
    });

    // Payment methods
    document.querySelectorAll('.pay-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.pay-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentPaymentMethod = btn.dataset.method;
        });
    });

    // Clear cart
    document.getElementById('clearCartBtn').addEventListener('click', () => {
        if (cart.length === 0) return;
        if (confirm('هل تريد مسح جميع الأصناف من السلة؟')) {
            cart = [];
            discountAmount = 0;
            renderCart();
        }
    });

    // Open discount modal
    document.getElementById('openDiscountBtn').addEventListener('click', () => {
        document.getElementById('discountAmountInput').value = discountAmount;
        document.getElementById('discountModal').style.display = 'flex';
    });

    // Checkout button
    document.getElementById('checkoutBtn').addEventListener('click', () => processCheckout(false));
    
    // Defer button
    document.getElementById('deferBtn').addEventListener('click', () => processCheckout(true));
}

// Load Products from AJAX
async function loadProducts() {
    const search = document.getElementById('posSearchInput').value;
    const url = `ajax.php?action=get_products&category_id=${encodeURIComponent(currentCategory)}&search=${encodeURIComponent(search)}`;
    
    try {
        const res = await fetch(url);
        const data = await res.json();
        if (data.success) {
            products = data.products;
            renderProductsGrid();
        }
    } catch (err) {
        console.error('Failed to load products', err);
    }
}

// Render Products Grid
function renderProductsGrid() {
    const grid = document.getElementById('productsGrid');
    if (products.length === 0) {
        grid.innerHTML = `<div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #94a3b8;">لا توجد أصناف مطابقة</div>`;
        return;
    }

    grid.innerHTML = products.map(prod => `
        <div class="pos-product-card" onclick="onProductClick(${prod.id})">
            <div class="product-name">${escapeHtml(prod.name)}</div>
            <div class="product-footer">
                <span class="product-price">${parseFloat(prod.price).toFixed(2)} ج.م</span>
                ${prod.has_variants ? `<span class="variant-indicator">أحجام</span>` : ''}
            </div>
        </div>
    `).join('');
}

// Load destinations for sessions
async function loadDestinations() {
    try {
        const res = await fetch('ajax.php?action=get_active_destinations');
        const data = await res.json();
        if (data.success) {
            destinations = data;
            updateDestinationUI();
            applyLinkedDestination();
        }
    } catch (err) {
        console.error('Failed to load destinations', err);
    }
}

function applyLinkedDestination() {
    const params = new URLSearchParams(window.location.search);
    const linkedGamingSessionId = params.get('gaming_session_id');
    const linkedBilliardSessionId = params.get('billiard_session_id');
    const linkedTableSessionId = params.get('table_session_id');
    let linkedType = null;
    let linkedValue = null;

    if (linkedGamingSessionId) {
        linkedType = 'gaming_ps';
        linkedValue = linkedGamingSessionId;
    } else if (linkedBilliardSessionId) {
        linkedType = 'gaming_billiard';
        linkedValue = linkedBilliardSessionId;
    } else if (linkedTableSessionId) {
        linkedType = 'dine_in';
        linkedValue = `active_${linkedTableSessionId}`;
    }

    if (!linkedType) return;

    const typeButton = document.querySelector(`.order-type-btn[data-type="${linkedType}"]`);
    if (typeButton) typeButton.click();

    const selectorId = linkedType === 'gaming_ps'
        ? 'selectedGamingSessionId'
        : linkedType === 'gaming_billiard'
            ? 'selectedBilliardSessionId'
            : 'selectedTableTarget';
    const selector = document.getElementById(selectorId);
    if (selector) selector.value = linkedValue;
}

// Update destination picker UI
function updateDestinationUI() {
    const picker = document.getElementById('destinationPicker');
    const deferBtn = document.getElementById('deferBtn');

    if (currentOrderType === 'takeaway') {
        picker.classList.remove('show');
        picker.innerHTML = '';
        deferBtn.style.display = 'none';
        return;
    }

    picker.classList.add('show');
    deferBtn.style.display = 'inline-block';

    if (currentOrderType === 'gaming_ps') {
        let options = destinations.gaming_sessions.map(s => 
            `<option value="${s.id}">${s.display_name} (${s.session_type === 'single' ? 'سنجل' : 'مالتي'})</option>`
        ).join('');
        picker.innerHTML = `
            <label style="font-weight:700; color:#9a3412; display:block; margin-bottom:4px;">اختر جهاز البلايستيشن المشغول لإضافة الطلب لحسابه:</label>
            ${destinations.gaming_sessions.length > 0 ? 
                `<select id="selectedGamingSessionId" style="width:100%; padding:6px; border-radius:6px; border:1px solid #fdba74; font-family:inherit;">${options}</select>` : 
                `<div style="color:#dc2626;">لا توجد أجهزة بلايستيشن مشغولة حالياً! يمكنك تحصيل الطلب فوراً كاش.</div>`
            }
        `;
    } else if (currentOrderType === 'gaming_billiard') {
        let options = destinations.billiard_sessions.map(s => 
            `<option value="${s.id}">${s.display_name} (${s.billing_mode === 'hourly' ? 'بالساعة' : 'بالجيم'})</option>`
        ).join('');
        picker.innerHTML = `
            <label style="font-weight:700; color:#9a3412; display:block; margin-bottom:4px;">اختر طاولة البلياردو المشغولة:</label>
            ${destinations.billiard_sessions.length > 0 ? 
                `<select id="selectedBilliardSessionId" style="width:100%; padding:6px; border-radius:6px; border:1px solid #fdba74; font-family:inherit;">${options}</select>` : 
                `<div style="color:#dc2626;">لا توجد طاولات بلياردو مشغولة حالياً!</div>`
            }
        `;
    } else if (currentOrderType === 'dine_in') {
        let activeOpts = destinations.table_sessions.map(s => `<option value="active_${s.id}">طاولة مشغولة: ${s.display_name}</option>`).join('');
        let availOpts = destinations.available_tables.map(t => `<option value="new_${t.id}">طاولة شاغرة (فتح جديد): ${t.display_name}</option>`).join('');
        picker.innerHTML = `
            <label style="font-weight:700; color:#9a3412; display:block; margin-bottom:4px;">اختر طاولة الصالة:</label>
            <select id="selectedTableTarget" style="width:100%; padding:6px; border-radius:6px; border:1px solid #fdba74; font-family:inherit;">
                ${activeOpts}
                ${availOpts}
            </select>
        `;
    }
}

// Product Click Handlers
async function onProductClick(productId) {
    const prod = products.find(p => p.id === productId);
    if (!prod) return;

    if (prod.has_variants || prod.has_modifiers) {
        // Open Variant/Modifier modal
        try {
            const res = await fetch(`ajax.php?action=get_product_details&product_id=${productId}`);
            const data = await res.json();
            if (data.success) {
                openVariantModal(data.product, data.variants, data.modifiers);
            }
        } catch (err) {
            console.error('Failed to load product details', err);
        }
    } else {
        // Direct add to cart
        addToCart(prod, null, [], '');
    }
}

// Open Variant Modal
function openVariantModal(product, variants, modifiers) {
    activeProductModal = product;
    selectedVariant = variants.length > 0 ? variants[0] : null;
    selectedModifiers = [];

    document.getElementById('modalProductName').textContent = product.name;
    document.getElementById('modalProductBasePrice').textContent = `${parseFloat(product.price).toFixed(2)} ج.م`;
    document.getElementById('modalItemNote').value = '';

    // Render Variants
    const vSection = document.getElementById('variantsGroupSection');
    const vGrid = document.getElementById('modalVariantsGrid');
    if (variants.length > 0) {
        vSection.style.display = 'block';
        vGrid.innerHTML = variants.map((v, idx) => `
            <div class="option-item ${idx === 0 ? 'selected' : ''}" onclick="selectModalVariant(this, ${v.id}, '${escapeHtml(v.variant_name)}', ${v.final_price})">
                <span class="option-name">${escapeHtml(v.variant_name)}</span>
                <span class="option-price">${parseFloat(v.final_price).toFixed(2)} ج.م</span>
            </div>
        `).join('');
    } else {
        vSection.style.display = 'none';
        vGrid.innerHTML = '';
    }

    // Render Modifiers
    const mSection = document.getElementById('modifiersGroupSection');
    const mGrid = document.getElementById('modalModifiersGrid');
    if (modifiers.length > 0) {
        mSection.style.display = 'block';
        mGrid.innerHTML = modifiers.map(m => `
            <div class="option-item" onclick="toggleModalModifier(this, ${m.id}, '${escapeHtml(m.name)}', ${m.price})">
                <span class="option-name">${escapeHtml(m.name)}</span>
                <span class="option-price">+${parseFloat(m.price).toFixed(2)}</span>
            </div>
        `).join('');
    } else {
        mSection.style.display = 'none';
        mGrid.innerHTML = '';
    }

    updateModalTotal();
    document.getElementById('variantModal').style.display = 'flex';
}

function closeVariantModal() {
    document.getElementById('variantModal').style.display = 'none';
    activeProductModal = null;
}

function selectModalVariant(el, id, name, price) {
    document.querySelectorAll('#modalVariantsGrid .option-item').forEach(item => item.classList.remove('selected'));
    el.classList.add('selected');
    selectedVariant = { id, variant_name: name, final_price: price };
    updateModalTotal();
}

function toggleModalModifier(el, id, name, price) {
    const idx = selectedModifiers.findIndex(m => m.id === id);
    if (idx > -1) {
        selectedModifiers.splice(idx, 1);
        el.classList.remove('selected');
    } else {
        selectedModifiers.push({ id, name, price, quantity: 1 });
        el.classList.add('selected');
    }
    updateModalTotal();
}

function updateModalTotal() {
    let base = selectedVariant ? parseFloat(selectedVariant.final_price) : parseFloat(activeProductModal.price);
    let mods = selectedModifiers.reduce((acc, m) => acc + parseFloat(m.price), 0);
    document.getElementById('modalTotalCalculated').textContent = `${(base + mods).toFixed(2)} ج.م`;
}

// Add from Modal to Cart
document.getElementById('modalAddToCartBtn').addEventListener('click', () => {
    if (!activeProductModal) return;
    const note = document.getElementById('modalItemNote').value.trim();
    addToCart(activeProductModal, selectedVariant, [...selectedModifiers], note);
    closeVariantModal();
});

// Add Item To Cart
function addToCart(product, variant, modifiers, note) {
    const unitPrice = variant ? parseFloat(variant.final_price) : parseFloat(product.price);
    const variantId = variant ? variant.id : null;
    const variantName = variant ? variant.variant_name : null;

    // Create unique key for item to group identical items together
    const modIds = modifiers.map(m => m.id).sort().join('_');
    const itemKey = `${product.id}_${variantId || 0}_${modIds}_${note}`;

    const existingIndex = cart.findIndex(c => c.itemKey === itemKey);
    if (existingIndex > -1) {
        cart[existingIndex].quantity += 1;
    } else {
        cart.push({
            itemKey,
            product_id: product.id,
            product_name: product.name,
            variant_id: variantId,
            variant_name: variantName,
            unit_price: unitPrice,
            quantity: 1,
            modifiers: modifiers,
            notes: note
        });
    }

    renderCart();
}

// Render Cart
function renderCart() {
    const container = document.getElementById('cartItemsList');
    const badge = document.getElementById('cartCountBadge');
    const checkoutBtn = document.getElementById('checkoutBtn');

    badge.textContent = cart.reduce((acc, it) => acc + it.quantity, 0);

    if (cart.length === 0) {
        container.innerHTML = `
            <div class="cart-empty-state">
                <div style="font-size: 38px; margin-bottom: 8px; opacity: 0.6;">☕</div>
                <div style="font-weight: 700; color: #475569;">السلة فارغة</div>
                <div style="font-size: 11px; margin-top: 4px;">اضغط على أي صنف من المنيو لإضافته هنا</div>
            </div>
        `;
        document.getElementById('subtotalDisplay').textContent = '0.00 ج.م';
        document.getElementById('discountDisplay').textContent = '0.00 ج.م';
        document.getElementById('grandTotalDisplay').textContent = '0.00 ج.م';
        checkoutBtn.disabled = true;
        return;
    }

    checkoutBtn.disabled = false;

    let subtotal = 0;

    container.innerHTML = cart.map((item, index) => {
        let modsTotal = item.modifiers.reduce((acc, m) => acc + (parseFloat(m.price) * (m.quantity || 1)), 0);
        let lineTotal = (item.unit_price * item.quantity) + (modsTotal * item.quantity);
        subtotal += lineTotal;

        return `
            <div class="cart-item-card">
                <div class="cart-item-row-top">
                    <div>
                        <span class="cart-item-title">${escapeHtml(item.product_name)}</span>
                        ${item.variant_name ? `<span class="cart-item-variant">(${escapeHtml(item.variant_name)})</span>` : ''}
                        ${item.modifiers.length > 0 ? 
                            `<div class="cart-item-mods">${item.modifiers.map(m => '+ ' + escapeHtml(m.name)).join(', ')}</div>` : ''}
                        ${item.notes ? `<div style="font-size:10px; color:#d97706; margin-top:2px;">📝 ${escapeHtml(item.notes)}</div>` : ''}
                    </div>
                    <button type="button" onclick="removeItem(${index})" style="background:none; border:none; color:#ef4444; font-size:14px; cursor:pointer;" title="حذف">&times;</button>
                </div>
                <div class="cart-item-row-bottom">
                    <div class="qty-control">
                        <button class="qty-btn" onclick="updateQty(${index}, -1)">-</button>
                        <span class="qty-number">${item.quantity}</span>
                        <button class="qty-btn" onclick="updateQty(${index}, 1)">+</button>
                    </div>
                    <div class="cart-item-total">${lineTotal.toFixed(2)} ج.م</div>
                </div>
            </div>
        `;
    }).join('');

    let grandTotal = Math.max(0, subtotal - discountAmount);

    document.getElementById('subtotalDisplay').textContent = `${subtotal.toFixed(2)} ج.م`;
    document.getElementById('discountDisplay').textContent = `${discountAmount.toFixed(2)} ج.م`;
    document.getElementById('grandTotalDisplay').textContent = `${grandTotal.toFixed(2)} ج.م`;
}

function updateQty(index, delta) {
    if (!cart[index]) return;
    cart[index].quantity += delta;
    if (cart[index].quantity <= 0) {
        cart.splice(index, 1);
    }
    renderCart();
}

function removeItem(index) {
    cart.splice(index, 1);
    renderCart();
}

function applyDiscount() {
    const val = parseFloat(document.getElementById('discountAmountInput').value) || 0;
    const subtotal = cart.reduce((acc, item) => {
        const modifiersTotal = item.modifiers.reduce((sum, modifier) => sum + (parseFloat(modifier.price) * (modifier.quantity || 1)), 0);
        return acc + (item.unit_price * item.quantity) + (modifiersTotal * item.quantity);
    }, 0);
    discountAmount = Math.min(subtotal, Math.max(0, val));
    document.getElementById('discountAmountInput').value = discountAmount;
    document.getElementById('discountModal').style.display = 'none';
    renderCart();
}

// Process Checkout & Payment
async function processCheckout(isDeferred) {
    if (cart.length === 0) return;

    const checkoutBtn = document.getElementById('checkoutBtn');
    const deferBtn = document.getElementById('deferBtn');
    checkoutBtn.disabled = true;
    deferBtn.disabled = true;

    // Collect destination IDs
    let gamingSessionId = null;
    let billiardSessionId = null;
    let tableSessionId = null;
    let tableId = null;

    if (currentOrderType === 'gaming_ps') {
        const el = document.getElementById('selectedGamingSessionId');
        gamingSessionId = el ? el.value : null;
    } else if (currentOrderType === 'gaming_billiard') {
        const el = document.getElementById('selectedBilliardSessionId');
        billiardSessionId = el ? el.value : null;
    } else if (currentOrderType === 'dine_in') {
        const el = document.getElementById('selectedTableTarget');
        if (el && el.value) {
            if (el.value.startsWith('active_')) {
                tableSessionId = el.value.replace('active_', '');
            } else if (el.value.startsWith('new_')) {
                tableId = el.value.replace('new_', '');
            }
        }
    }

    const payload = {
        order_type: currentOrderType,
        gaming_session_id: gamingSessionId,
        billiard_session_id: billiardSessionId,
        table_session_id: tableSessionId,
        table_id: tableId,
        items: cart,
        discount_amount: discountAmount,
        payment_method: currentPaymentMethod,
        customer_name: document.getElementById('customerNameInput').value.trim(),
        notes: document.getElementById('orderNotesInput').value.trim(),
        pay_now: !isDeferred
    };

    try {
        const res = await fetch('ajax.php?action=checkout', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
            // Deferred orders remain attached to the session and are printed when it is closed.
            if (!isDeferred && data.is_deferred !== true && data.order_id) {
                openReceiptModal(`receipt.php?id=${data.order_id}&type=customer`);
            }

            // Reset Cart
            cart = [];
            discountAmount = 0;
            document.getElementById('customerNameInput').value = '';
            document.getElementById('orderNotesInput').value = '';
            renderCart();
            loadDestinations();

            alert(isDeferred
                ? `تم تعليق وإضافة الطلب رقم ${data.order_number || data.order_id} بنجاح لحساب الجلسة!`
                : 'تم حفظ ودفع الطلب بنجاح!');
            if (isDeferred) {
                window.location.reload();
            }
        } else {
            alert(data.error || 'فشل حفظ الطلب');
        }
    } catch (err) {
        console.error('Checkout error', err);
        alert('حدث خطأ أثناء إتمام الطلب');
    } finally {
        checkoutBtn.disabled = false;
        deferBtn.disabled = false;
    }
}

function escapeHtml(text) {
    if (!text) return '';
    return text.toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
</script>

<?php include '../../includes/footer.php'; ?>
