<?php
/**
 * Settings Configuration
 * نظام إدارة محل أجهزة منزلية
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Timezone
date_default_timezone_set('Africa/Cairo');

// Ensure proper encoding
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Base URL (Dynamic detection for any host, port, and directory)
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $dirName = basename(dirname(__DIR__));
    $pos = strpos($scriptName, '/' . $dirName);
    if ($pos !== false) {
        $basePath = substr($scriptName, 0, $pos + strlen($dirName) + 1);
    } else {
        $basePath = '/';
    }
    define('BASE_URL', $protocol . $host . rtrim($basePath, '/') . '/');
}

// File upload settings
define('UPLOAD_DIR', __DIR__ . '/../assets/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGES', ['jpg', 'jpeg', 'png', 'gif']);

// Backup directory
define('BACKUP_DIR', __DIR__ . '/../backups/');
// Secondary backup directory (Documents folder for extra safety)
define('BACKUP_DIR_SECONDARY', 'C:/StoreBackups/');

// Get setting from database
function getSetting($key, $default = '') {
    $setting = getRow("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
    return $setting ? $setting['setting_value'] : $default;
}

// Update setting in database
function updateSetting($key, $value) {
    $exists = getRow("SELECT id FROM settings WHERE setting_key = ?", [$key]);
    
    if ($exists) {
        return execute("UPDATE settings SET setting_value = ? WHERE setting_key = ?", [$value, $key]);
    } else {
        return insert("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)", [$key, $value]);
    }
}

// Get all settings as array
function getAllSettings() {
    $settings = getRows("SELECT setting_key, setting_value FROM settings");
    $result = [];
    foreach ($settings as $setting) {
        $result[$setting['setting_key']] = $setting['setting_value'];
    }
    return $result;
}

// Arabic day names
function getArabicDayName($dayNumber = null) {
    $days = [
        0 => 'الأحد',
        1 => 'الاثنين',
        2 => 'الثلاثاء',
        3 => 'الأربعاء',
        4 => 'الخميس',
        5 => 'الجمعة',
        6 => 'السبت'
    ];
    
    if ($dayNumber === null) {
        $dayNumber = date('w');
    }
    
    return $days[$dayNumber] ?? '';
}

// Get tomorrow's day name
function getTomorrowDayName() {
    $tomorrow = date('w', strtotime('+1 day'));
    return getArabicDayName($tomorrow);
}

// Get today's day name
function getTodayDayName() {
    return getArabicDayName(date('w'));
}

// Format number with Arabic separators
function formatNumber($number, $decimals = 2) {
    return number_format($number, $decimals, '.', ',');
}

// Format currency
function formatCurrency($amount) {
    $currency = getSetting('currency', 'جنيه');
    return formatNumber((float)$amount, 2) . ' ' . $currency;
}

// Sanitize input
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Redirect function
function redirect($url) {
    header("Location: " . $url);
    exit;
}

// Success message
function setSuccess($message) {
    $_SESSION['success'] = $message;
}

// Error message
function setError($message) {
    $_SESSION['error'] = $message;
}

// Get and clear success message
function getSuccess() {
    if (isset($_SESSION['success'])) {
        $message = $_SESSION['success'];
        unset($_SESSION['success']);
        return $message;
    }
    return null;
}

// Check if success message exists
function hasSuccess() {
    return isset($_SESSION['success']) && !empty($_SESSION['success']);
}

// Get and clear error message
function getError() {
    if (isset($_SESSION['error'])) {
        $message = $_SESSION['error'];
        unset($_SESSION['error']);
        return $message;
    }
    return null;
}

// Check if error message exists
function hasError() {
    return isset($_SESSION['error']) && !empty($_SESSION['error']);
}

// Log activity (activity_log has no username column — actor is tracked via user_id)
function logActivity($action, $description, $user = null) {
    $userId = $_SESSION['user_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return insert(
        "INSERT INTO activity_log (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)",
        [$userId, $action, $description, $ip]
    );
}

// Generate invoice number
function generateInvoiceNumber($type = 'sale') {
    $prefix = $type === 'sale' ? getSetting('invoice_prefix_sale', 'INV-') : getSetting('invoice_prefix_purchase', 'PUR-');
    
    // Get last invoice number
    $lastInvoice = getRow("SELECT invoice_number FROM invoices WHERE type = ? ORDER BY id DESC LIMIT 1", [$type]);
    
    if ($lastInvoice) {
        // Extract number from last invoice
        $lastNumber = (int) str_replace($prefix, '', $lastInvoice['invoice_number']);
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    
    return $prefix . str_pad($newNumber, 5, '0', STR_PAD_LEFT);
}

// Upload image
function uploadImage($file, $directory = 'products') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    $uploadDir = UPLOAD_DIR . $directory . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($extension, ALLOWED_IMAGES)) {
        return false;
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return false;
    }
    
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $directory . '/' . $filename;
    }
    
    return false;
}

// Delete image
function deleteImage($filepath) {
    $fullPath = UPLOAD_DIR . $filepath;
    if (file_exists($fullPath)) {
        return unlink($fullPath);
    }
    return false;
}

// =============================================
// Warehouse Helper Functions (دوال إدارة المخازن)
// =============================================

/**
 * Get all active warehouses (or all)
 */
function getAllWarehouses($onlyActive = true) {
    if ($onlyActive) {
        return getRows("SELECT * FROM warehouses WHERE is_active = 1 ORDER BY is_default DESC, name ASC");
    }
    return getRows("SELECT * FROM warehouses ORDER BY is_default DESC, name ASC");
}

/**
 * Get default warehouse
 */
function getDefaultWarehouse() {
    $wh = getRow("SELECT * FROM warehouses WHERE is_default = 1 AND is_active = 1 LIMIT 1");
    if (!$wh) {
        $wh = getRow("SELECT * FROM warehouses WHERE is_active = 1 ORDER BY id ASC LIMIT 1");
    }
    return $wh;
}

/**
 * Get warehouse by ID
 */
function getWarehouseById($id) {
    return getRow("SELECT * FROM warehouses WHERE id = ?", [(int)$id]);
}

/**
 * Get warehouse name by ID
 */
function getWarehouseName($id) {
    $wh = getRow("SELECT name FROM warehouses WHERE id = ?", [(int)$id]);
    return $wh ? $wh['name'] : 'المخزن الرئيسي';
}

/**
 * Get stock quantity of a product in a specific warehouse
 */
function getWarehouseStock($warehouseId, $productId) {
    $stock = getRow("SELECT quantity FROM warehouse_stock WHERE warehouse_id = ? AND product_id = ?", [(int)$warehouseId, (int)$productId]);
    return $stock ? (int)$stock['quantity'] : 0;
}

/**
 * Update stock for a product in a specific warehouse
 * $quantityChange: positive integer
 * $operation: 'add' | 'subtract' | 'set'
 */
function updateWarehouseStock($warehouseId, $productId, $quantityChange, $operation = 'add') {
    $warehouseId = (int)$warehouseId;
    $productId = (int)$productId;
    $quantityChange = (int)$quantityChange;
    
    // Fallback if warehouseId is invalid
    if ($warehouseId <= 0) {
        $def = getDefaultWarehouse();
        $warehouseId = $def ? (int)$def['id'] : 1;
    }

    $existing = getRow("SELECT id, quantity FROM warehouse_stock WHERE warehouse_id = ? AND product_id = ?", [$warehouseId, $productId]);
    if (!$existing) {
        insert("INSERT INTO warehouse_stock (warehouse_id, product_id, quantity) VALUES (?, ?, 0)", [$warehouseId, $productId]);
        $currentQty = 0;
    } else {
        $currentQty = (int)$existing['quantity'];
    }

    if ($operation === 'add') {
        $newQty = $currentQty + $quantityChange;
    } elseif ($operation === 'subtract') {
        $newQty = $currentQty - $quantityChange;
    } elseif ($operation === 'set') {
        $newQty = $quantityChange;
    } else {
        $newQty = $currentQty;
    }

    execute("UPDATE warehouse_stock SET quantity = ? WHERE warehouse_id = ? AND product_id = ?", [$newQty, $warehouseId, $productId]);

    // Keep aggregate total in products table synchronized
    syncProductTotalStock($productId);

    return $newQty;
}

/**
 * Synchronize product's total stock_quantity with the sum across all warehouses
 */
function syncProductTotalStock($productId) {
    $productId = (int)$productId;
    $row = getRow("SELECT COALESCE(SUM(quantity), 0) as total FROM warehouse_stock WHERE product_id = ?", [$productId]);
    $totalQty = $row ? (int)$row['total'] : 0;
    execute("UPDATE products SET stock_quantity = ? WHERE id = ?", [$totalQty, $productId]);
    return $totalQty;
}

/* ============================================================
 * Product stock tracking engine (units + movements ledger)
 * ============================================================ */

/**
 * Append a row to the product stock movements ledger (append-only).
 * Call AFTER the warehouse quantity has been changed.
 */
function recordProductMovement($productId, $warehouseId, $movementType, $qtyIn, $qtyOut, $unit, $referenceType = null, $referenceId = null, $reason = null, $supplierId = null, $unitCost = null) {
    $productId = (int)$productId;
    $warehouseId = $warehouseId ? (int)$warehouseId : null;
    $balance = ($warehouseId !== null) ? getWarehouseStock($warehouseId, $productId) : 0;
    $userId = function_exists('getCurrentUserId') ? getCurrentUserId() : null;
    return insert(
        "INSERT INTO product_stock_movements
            (product_id, warehouse_id, movement_type, qty_in, qty_out, balance_after, unit, reference_type, reference_id, reason, user_id, supplier_id, unit_cost)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$productId, $warehouseId, $movementType, (int)$qtyIn, (int)$qtyOut, (int)$balance,
         $unit, $referenceType, $referenceId ? (int)$referenceId : null, $reason, $userId,
         $supplierId ? (int)$supplierId : null, $unitCost !== null ? (float)$unitCost : null]
    );
}

/**
 * Add stock of a product in base (stock) units and log the movement.
 * Used by purchase approval, opening balances and manual corrections.
 */
function addProductStock($productId, $warehouseId, $stockQty, $movementType, $referenceType = null, $referenceId = null, $reason = null, $unitCost = null, $supplierId = null) {
    $product = getRow("SELECT id, unit FROM products WHERE id = ?", [(int)$productId]);
    if (!$product) {
        throw new RuntimeException('الصنف غير موجود');
    }
    $stockQty = (int)$stockQty;
    if ($stockQty <= 0) {
        return getWarehouseStock((int)$warehouseId, (int)$productId);
    }
    $newBalance = updateWarehouseStock($warehouseId, $productId, $stockQty, 'add');
    recordProductMovement($productId, $warehouseId, $movementType, $stockQty, 0, $product['unit'], $referenceType, $referenceId, $reason, $supplierId, $unitCost);
    return $newBalance;
}

/**
 * Remove stock of a tracked product in base (stock) units and log the movement.
 * Returns the new balance. Throws when oversell is blocked by settings.
 */
function removeProductStock($productId, $warehouseId, $stockQty, $movementType, $referenceType = null, $referenceId = null, $reason = null) {
    $product = getRow("SELECT id, name, unit, track_stock FROM products WHERE id = ?", [(int)$productId]);
    if (!$product) {
        throw new RuntimeException('الصنف غير موجود');
    }
    $stockQty = (int)$stockQty;
    if ($stockQty <= 0) {
        return getWarehouseStock((int)$warehouseId, (int)$productId);
    }
    if ((int)$product['track_stock'] !== 1) {
        return getWarehouseStock((int)$warehouseId, (int)$productId);
    }
    $current = getWarehouseStock((int)$warehouseId, (int)$productId);
    if ($current < $stockQty && getSetting('prevent_negative_stock', '0') === '1') {
        throw new RuntimeException('الكمية المتاحة من "' . $product['name'] . '" غير كافية (المتاح: ' . $current . ' ' . $product['unit'] . ')');
    }
    $newBalance = updateWarehouseStock($warehouseId, $productId, $stockQty, 'subtract');
    recordProductMovement($productId, $warehouseId, $movementType, 0, $stockQty, $product['unit'], $referenceType, $referenceId, $reason);
    return $newBalance;
}

/**
 * Convert a purchase quantity to base stock units.
 * Throws when the factor is invalid or the result is not a whole number.
 */
function purchaseToStockQty($purchaseQty, $factor) {
    $purchaseQty = (int)$purchaseQty;
    $factor = (float)$factor;
    if ($purchaseQty <= 0) {
        throw new RuntimeException('كمية الشراء يجب أن تكون أكبر من صفر');
    }
    if ($factor <= 0) {
        throw new RuntimeException('معامل التحويل غير صالح');
    }
    $result = $purchaseQty * $factor;
    if (abs($result - round($result)) > 0.0001) {
        throw new RuntimeException('ناتج التحويل لوحدات المخزون يجب أن يكون عدداً صحيحاً');
    }
    return (int)round($result);
}

/**
 * Approve a draft purchase invoice: applies stock, supplier balance and
 * movements exactly once. Safe to retry — approving an approved invoice
 * is a no-op that reports the current status.
 *
 * This function manages its own transaction.
 *
 * @return array{success:bool, already:bool, invoice_number:string}
 */
function approvePurchaseInvoice($invoiceId) {
    $invoiceId = (int)$invoiceId;
    beginTransaction();
    try {
        $invoice = getRow("SELECT * FROM invoices WHERE id = ? AND type = 'purchase' FOR UPDATE", [$invoiceId]);
        if (!$invoice) {
            throw new RuntimeException('فاتورة الشراء غير موجودة');
        }
        if ($invoice['status'] === 'approved') {
            rollback();
            return ['success' => true, 'already' => true, 'invoice_number' => $invoice['invoice_number']];
        }
        if ($invoice['status'] !== 'draft') {
            throw new RuntimeException('لا يمكن اعتماد الفاتورة في حالتها الحالية');
        }

        $warehouseId = (int)($invoice['warehouse_id'] ?: 1);
        $items = getRows("SELECT * FROM invoice_items WHERE invoice_id = ?", [$invoiceId]);
        if (empty($items)) {
            throw new RuntimeException('الفاتورة لا تحتوي على أصناف');
        }

        foreach ($items as $item) {
            $product = getRow("SELECT id, unit FROM products WHERE id = ? FOR UPDATE", [(int)$item['product_id']]);
            if (!$product) {
                throw new RuntimeException('صنف غير موجود في الفاتورة');
            }
            $factor = $item['conversion_factor'] !== null ? (float)$item['conversion_factor'] : 1;
            $stockQty = purchaseToStockQty($item['quantity'], $factor);
            execute(
                "UPDATE invoice_items SET stock_quantity = ? WHERE id = ?",
                [$stockQty, (int)$item['id']]
            );
            // Stock-unit cost for valuation in reports (purchase price ÷ factor)
            $lineUnitCost = ($factor > 0) ? ((float)$item['unit_price'] / $factor) : null;
            addProductStock((int)$item['product_id'], $warehouseId, $stockQty, 'purchase', 'purchase_invoice', $invoiceId, 'اعتماد فاتورة شراء ' . $invoice['invoice_number'], $lineUnitCost, $invoice['supplier_id'] ?: null);
        }

        if (!empty($invoice['supplier_id'])) {
            execute("UPDATE suppliers SET balance = balance + ? WHERE id = ?", [(float)$invoice['remaining_amount'], (int)$invoice['supplier_id']]);
        }

        execute("UPDATE invoices SET status = 'approved' WHERE id = ?", [$invoiceId]);
        commit();
        return ['success' => true, 'already' => false, 'invoice_number' => $invoice['invoice_number']];
    } catch (Throwable $e) {
        rollback();
        return ['success' => false, 'already' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Distinct unit names already used by products (for datalist suggestions;
 * operators may always type a brand-new unit).
 */
function getKnownUnits() {
    $rows = getRows("SELECT DISTINCT unit AS u FROM products WHERE unit IS NOT NULL AND unit <> '' ORDER BY unit");
    $out = [];
    foreach ($rows as $r) {
        $out[] = $r['u'];
    }
    $prows = getRows("SELECT DISTINCT purchase_unit AS u FROM products WHERE purchase_unit IS NOT NULL AND purchase_unit <> '' ORDER BY purchase_unit");
    foreach ($prows as $r) {
        if (!in_array($r['u'], $out, true)) {
            $out[] = $r['u'];
        }
    }
    return $out;
}

/**
 * Generate stock transfer number (e.g. TRF-00001)
 */
function generateTransferNumber() {
    $prefix = 'TRF-';
    $last = getRow("SELECT transfer_number FROM stock_transfers ORDER BY id DESC LIMIT 1");
    if ($last && !empty($last['transfer_number'])) {
        $num = (int)str_replace($prefix, '', $last['transfer_number']);
        $newNum = $num + 1;
    } else {
        $newNum = 1;
    }
    return $prefix . str_pad($newNum, 5, '0', STR_PAD_LEFT);
}



// Automatic Offline License Enforcement on Every Request
if (file_exists(__DIR__ . '/license.php')) {
    require_once __DIR__ . '/license.php';
    if (function_exists('enforceLicense')) {
        enforceLicense();
    }
}

// Automatic periodic backup (opportunistic: at most once per interval,
// on normal page loads only — never on ajax/CLI/POST requests)
if (
    PHP_SAPI !== 'cli'
    && isset($_SERVER['SCRIPT_NAME'], $_SERVER['REQUEST_METHOD'])
    && $_SERVER['REQUEST_METHOD'] === 'GET'
    && strpos($_SERVER['SCRIPT_NAME'], 'ajax.php') === false
    && strpos($_SERVER['SCRIPT_NAME'], 'backup.php') === false
    && file_exists(__DIR__ . '/backup_lib.php')
) {
    require_once __DIR__ . '/backup_lib.php';
    if (function_exists('runAutoBackupIfDue')) {
        try {
            runAutoBackupIfDue();
        } catch (Throwable $e) {
            // Silent: backups must never break page loads
        }
    }
}
