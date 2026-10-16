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
 * Validate that a quick-supply movement can back an invoice line:
 * must exist, be a supply, and not already linked to another invoice.
 * Returns the movement row. Throws on any problem.
 */
function validateSupplyLink($movementId, $productId, $excludeInvoiceId = 0) {
    $movementId = (int)$movementId;
    if ($movementId <= 0) {
        throw new RuntimeException('مرجع التوريد غير صالح');
    }
    $mov = getRow("SELECT * FROM product_stock_movements WHERE id = ? FOR UPDATE", [$movementId]);
    if (!$mov) {
        throw new RuntimeException('حركة التوريد المرجعية غير موجودة');
    }
    if ($mov['movement_type'] !== 'supply') {
        throw new RuntimeException('مرجع الفاتورة يجب أن يكون حركة توريد مباشر');
    }
    if ((int)$mov['product_id'] !== (int)$productId) {
        throw new RuntimeException('حركة التوريد تخص صنفاً آخر');
    }
    if (!empty($mov['linked_invoice_id'])) {
        throw new RuntimeException('هذا التوريد مرتبط بالفعل بفاتورة أخرى');
    }
    // Drafts also reserve the reference without linking the movement yet
    $used = getRow(
        "SELECT invoice_id FROM invoice_items WHERE source_movement_id = ? AND invoice_id != ?",
        [$movementId, (int)$excludeInvoiceId]
    );
    if ($used) {
        throw new RuntimeException('هذا التوريد مستخدم بالفعل في فاتورة أخرى');
    }
    return $mov;
}

/**
 * Add raw-material stock (in base units, decimals allowed) and log an
 * ingredient movement. Returns the added base quantity.
 */
function addIngredientStock($ingredientId, $purchaseQty, $factor, $unitPrice, $movementType, $referenceType = null, $referenceId = null, $reason = null) {
    $ingredientId = (int)$ingredientId;
    $purchaseQty = (float)$purchaseQty;
    $factor = (float)$factor;
    if ($purchaseQty <= 0 || $factor <= 0) {
        throw new RuntimeException('كمية الشراء ومعامل التحويل يجب أن يكونا أكبر من صفر');
    }
    $ing = getRow("SELECT * FROM ingredients WHERE id = ? FOR UPDATE", [$ingredientId]);
    if (!$ing) {
        throw new RuntimeException('المادة الخام غير موجودة');
    }
    $stockQty = round($purchaseQty * $factor, 3);
    if ($stockQty <= 0) {
        throw new RuntimeException('ناتج التحويل لوحدات المخزون غير صالح');
    }
    $newBal = round((float)$ing['current_stock'] + $stockQty, 3);
    execute("UPDATE ingredients SET current_stock = ?, last_purchase_price = ? WHERE id = ?", [$newBal, (float)$unitPrice, $ingredientId]);
    $unitCost = $factor > 0 ? round((float)$unitPrice / $factor, 4) : null;
    insert(
        "INSERT INTO ingredient_stock_movements (ingredient_id, movement_type, quantity, unit_cost, reference_type, reference_id, balance_after, reason, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$ingredientId, $movementType, $stockQty, $unitCost, $referenceType, $referenceId ? (int)$referenceId : null, $newBal, $reason,
         function_exists('getCurrentUserId') ? getCurrentUserId() : null]
    );
    return $stockQty;
}

/**
 * Remove raw-material stock (reversals) and log the movement.
 */
function removeIngredientStock($ingredientId, $stockQty, $movementType, $referenceType = null, $referenceId = null, $reason = null) {
    $ingredientId = (int)$ingredientId;
    $stockQty = round((float)$stockQty, 3);
    if ($stockQty <= 0) {
        return;
    }
    $ing = getRow("SELECT * FROM ingredients WHERE id = ? FOR UPDATE", [$ingredientId]);
    if (!$ing) {
        throw new RuntimeException('المادة الخام غير موجودة');
    }
    $newBal = round(max(0, (float)$ing['current_stock'] - $stockQty), 3);
    execute("UPDATE ingredients SET current_stock = ? WHERE id = ?", [$newBal, $ingredientId]);
    insert(
        "INSERT INTO ingredient_stock_movements (ingredient_id, movement_type, quantity, unit_cost, reference_type, reference_id, balance_after, reason, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$ingredientId, $movementType, $stockQty, null, $referenceType, $referenceId ? (int)$referenceId : null, $newBal, $reason,
         function_exists('getCurrentUserId') ? getCurrentUserId() : null]
    );
    return $newBal;
}

/**
 * Generate the next return number (e.g. RTN-00001)
 */
function generateReturnNumber() {
    $last = getRow("SELECT id FROM invoice_returns ORDER BY id DESC LIMIT 1");
    return 'RTN-' . str_pad(((int)($last['id'] ?? 0)) + 1, 5, '0', STR_PAD_LEFT);
}

/**
 * Create a capped return against a purchase invoice or a POS/sale order.
 *
 * $kind: 'purchase' | 'pos' | 'sale'
 * $lines: [['line_id' => <source line id>, 'quantity' => <qty>], ...]
 * Prices, names and caps always come from the server — never the client.
 * Cumulative returned qty per source line can never exceed the line qty,
 * no matter how many separate returns are made (checked + locked in one
 * transaction, plus an idempotency key against double submits).
 *
 * Stock: purchase returns subtract (supplier_return), sale/POS returns add
 * back tracked products only (customer_return). Money: purchase returns
 * reduce the supplier debt + invoice remainder; POS/sale returns move no
 * money (cashier settles cash by hand; the receipt shows the refund value).
 *
 * @return array{success:bool,already:bool,return_id:int,return_number:string,total:float,error?:string}
 */
function createInvoiceReturn($kind, $invoiceId, $lines, $notes = '', $returnKey = null) {
    $kind = in_array($kind, ['purchase', 'pos', 'sale'], true) ? $kind : null;
    $invoiceId = (int)$invoiceId;
    if (!$kind || $invoiceId <= 0 || empty($lines) || !is_array($lines)) {
        return ['success' => false, 'already' => false, 'error' => 'بيانات المرتجع غير مكتملة'];
    }

    beginTransaction();
    try {
        // Idempotency: retried submit returns the original return
        if ($returnKey) {
            $dup = getRow("SELECT id, return_number, total_amount FROM invoice_returns WHERE return_key = ?", [$returnKey]);
            if ($dup) {
                rollback();
                return ['success' => true, 'already' => true, 'return_id' => (int)$dup['id'], 'return_number' => $dup['return_number'], 'total' => (float)$dup['total_amount']];
            }
        }

        $isPurchase = ($kind === 'purchase');
        $lineTable = $isPurchase ? 'invoice_items' : null;

        // Lock the parent document
        if ($kind === 'pos') {
            $parent = getRow("SELECT * FROM orders WHERE id = ? FOR UPDATE", [$invoiceId]);
            if (!$parent) {
                throw new RuntimeException('طلب البيع غير موجود');
            }
            if (($parent['status'] ?? '') === 'cancelled') {
                throw new RuntimeException('لا يمكن الإرجاع من طلب ملغي');
            }
        } else {
            $wantType = $isPurchase ? 'purchase' : 'sale';
            $parent = getRow("SELECT * FROM invoices WHERE id = ? AND type = ? FOR UPDATE", [$invoiceId, $wantType]);
            if (!$parent) {
                throw new RuntimeException('الفاتورة غير موجودة');
            }
            if ($isPurchase && ($parent['status'] ?? 'approved') !== 'approved') {
                throw new RuntimeException('لا يمكن الإرجاع من مسودة لم تُعتمد');
            }
        }

        // Source lines with already-returned totals
        if ($kind === 'pos') {
            $srcLines = getRows(
                "SELECT oi.*,
                        COALESCE((SELECT SUM(ri.quantity) FROM invoice_return_items ri
                                  JOIN invoice_returns r ON r.id = ri.return_id
                                  WHERE r.invoice_kind = 'pos' AND r.invoice_id = ?
                                    AND ri.line_kind = 'order' AND ri.line_id = oi.id), 0) AS returned_qty
                 FROM order_items oi WHERE oi.order_id = ?",
                [$invoiceId, $invoiceId]
            );
        } else {
            $wantType = $isPurchase ? 'purchase' : 'sale';
            $srcLines = getRows(
                "SELECT ii.*,
                        COALESCE((SELECT SUM(ri.quantity) FROM invoice_return_items ri
                                  JOIN invoice_returns r ON r.id = ri.return_id
                                  WHERE r.invoice_kind = ? AND r.invoice_id = ?
                                    AND ri.line_kind = 'invoice' AND ri.line_id = ii.id), 0) AS returned_qty
                 FROM invoice_items ii WHERE ii.invoice_id = ?",
                [$kind, $invoiceId, $invoiceId]
            );
        }
        $byId = [];
        foreach ($srcLines as $sl) {
            $byId[(int)$sl['id']] = $sl;
        }

        // Validate + prepare every line (server-side truth only)
        $prepared = [];
        $total = 0;
        foreach ($lines as $ln) {
            $lineId = (int)($ln['line_id'] ?? 0);
            $qty = round((float)($ln['quantity'] ?? 0), 3);
            if (!isset($byId[$lineId])) {
                throw new RuntimeException('بند غير تابع لهذه الفاتورة');
            }
            $src = $byId[$lineId];
            $lineQty = (float)$src['quantity'];
            $already = (float)$src['returned_qty'];
            if ($qty <= 0) {
                throw new RuntimeException('كمية الإرجاع يجب أن تكون أكبر من صفر');
            }
            if ($already + $qty > $lineQty + 0.0001) {
                $srcName = $kind === 'pos' ? ($src['product_name'] ?? '') : ($src['product_name'] ?? $src['item_name'] ?? '');
                $left = max(0, $lineQty - $already);
                throw new RuntimeException('تجاوز الحد المسموح للصنف "' . $srcName . '" — المتبقي القابل للإرجاع: ' . rtrim(rtrim(number_format($left, 3), '0'), '.'));
            }
            if ($kind === 'pos') {
                $unitPrice = (float)$src['unit_price'] + (float)($src['modifiers_total'] ?? 0);
                $prepared[] = [
                    'line_kind' => 'order', 'line_id' => $lineId,
                    'product_id' => $src['product_id'] ? (int)$src['product_id'] : null,
                    'ingredient_id' => null,
                    'name' => $src['product_name'],
                    'unit' => null, 'qty' => $qty, 'price' => $unitPrice,
                ];
            } else {
                $prepared[] = [
                    'line_kind' => 'invoice', 'line_id' => $lineId,
                    'product_id' => $src['product_id'] ? (int)$src['product_id'] : null,
                    'ingredient_id' => $src['ingredient_id'] ? (int)$src['ingredient_id'] : null,
                    'name' => $src['product_name'] ?? $src['item_name'],
                    'unit' => $src['unit'],
                    'qty' => $qty, 'price' => (float)$src['unit_price'],
                ];
            }
            $total += $qty * end($prepared)['price'];
        }
        $total = round($total, 2);
        if (empty($prepared)) {
            throw new RuntimeException('لا توجد بنود صالحة للإرجاع');
        }

        $returnNumber = generateReturnNumber();
        $userId = function_exists('getCurrentUserId') ? getCurrentUserId() : null;
        $returnId = insert(
            "INSERT INTO invoice_returns (return_number, invoice_kind, invoice_id, return_date, total_amount, notes, return_key, created_by)
             VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?)",
            [$returnNumber, $kind, $invoiceId, $total, $notes ? sanitize($notes) : null, $returnKey ?: null, $userId]
        );
        if (!$returnId) {
            throw new RuntimeException('تعذر حفظ المرتجع');
        }

        $whId = $isPurchase
            ? (int)($parent['warehouse_id'] ?: (function_exists('getCurrentWarehouseId') ? getCurrentWarehouseId() : 1))
            : (function_exists('getCurrentWarehouseId') ? (int)getCurrentWarehouseId() : 1);
        $refLabel = $kind === 'pos' ? ('طلب كاشير #' . ($parent['order_number'] ?? $invoiceId)) : ('فاتورة ' . ($parent['invoice_number'] ?? $invoiceId));

        foreach ($prepared as $pl) {
            $lineTotal = round($pl['qty'] * $pl['price'], 2);
            $ri = insert(
                "INSERT INTO invoice_return_items (return_id, line_kind, line_id, product_id, ingredient_id, item_name, unit, quantity, unit_price, line_total)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$returnId, $pl['line_kind'], $pl['line_id'], $pl['product_id'], $pl['ingredient_id'], $pl['name'], $pl['unit'], $pl['qty'], $pl['price'], $lineTotal]
            );
            if (!$ri) {
                throw new RuntimeException('تعذر حفظ بند المرتجع: ' . $pl['name']);
            }

            if ($isPurchase) {
                // Supplier return: stock leaves the warehouse / ingredients
                if (!empty($pl['ingredient_id'])) {
                    removeIngredientStock($pl['ingredient_id'], $pl['qty'], 'return', 'purchase_return', $returnId, 'مرتجع مشتريات ' . $returnNumber);
                } elseif (!empty($pl['product_id'])) {
                    $pp = getRow("SELECT unit FROM products WHERE id = ? FOR UPDATE", [$pl['product_id']]);
                    $stockQty = (int)round($pl['qty']);
                    updateWarehouseStock($whId, $pl['product_id'], $stockQty, 'subtract');
                    recordProductMovement($pl['product_id'], $whId, 'supplier_return', 0, $stockQty, $pp ? $pp['unit'] : $pl['unit'], 'purchase_return', $returnId, 'مرتجع مشتريات ' . $returnNumber);
                }
            } else {
                // Customer return: tracked products go back on the shelf
                if (!empty($pl['product_id'])) {
                    $pp = getRow("SELECT unit, track_stock FROM products WHERE id = ? FOR UPDATE", [$pl['product_id']]);
                    if ($pp && (int)$pp['track_stock'] === 1) {
                        $stockQty = (int)round($pl['qty']);
                        updateWarehouseStock($whId, $pl['product_id'], $stockQty, 'add');
                        recordProductMovement($pl['product_id'], $whId, 'customer_return', $stockQty, 0, $pp['unit'], 'sale_return', $returnId, 'مرتجع مبيعات ' . $returnNumber);
                    }
                }
            }
        }

        // Money: purchase returns reduce what we owe the supplier + the invoice remainder
        if ($isPurchase && !empty($parent['supplier_id'])) {
            execute("UPDATE suppliers SET balance = balance - ? WHERE id = ?", [$total, (int)$parent['supplier_id']]);
        }
        if ($isPurchase) {
            $newRemaining = round((float)$parent['remaining_amount'] - $total, 2);
            $newPayStatus = $newRemaining <= 0 ? 'paid' : ((float)$parent['paid_amount'] > 0 ? 'partial' : 'unpaid');
            execute("UPDATE invoices SET remaining_amount = ?, payment_status = ? WHERE id = ?", [$newRemaining, $newPayStatus, $invoiceId]);
        }

        commit();
        return ['success' => true, 'already' => false, 'return_id' => (int)$returnId, 'return_number' => $returnNumber, 'total' => $total];
    } catch (Throwable $e) {
        rollback();
        return ['success' => false, 'already' => false, 'error' => $e->getMessage()];
    }
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
            // Raw-material lines go to ingredients stock + ledger
            if (!empty($item['ingredient_id'])) {
                $ing = getRow("SELECT id FROM ingredients WHERE id = ? FOR UPDATE", [(int)$item['ingredient_id']]);
                if (!$ing) {
                    throw new RuntimeException('مادة خام غير موجودة في الفاتورة');
                }
                $factor = $item['conversion_factor'] !== null ? (float)$item['conversion_factor'] : 1;
                if ($factor <= 0) {
                    throw new RuntimeException('معامل التحويل غير صالح');
                }
                $stockQty = round((float)$item['quantity'] * $factor, 3);
                if ($stockQty <= 0) {
                    throw new RuntimeException('كمية غير صالحة في الفاتورة');
                }
                execute(
                    "UPDATE invoice_items SET stock_quantity = ? WHERE id = ?",
                    [$stockQty, (int)$item['id']]
                );
                addIngredientStock((int)$item['ingredient_id'], (float)$item['quantity'], $factor, (float)$item['unit_price'], 'purchase', 'purchase_invoice', $invoiceId, 'اعتماد فاتورة شراء ' . $invoice['invoice_number']);
                continue;
            }
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
            // Lines documenting an already-applied quick supply: link only, never add stock twice
            $srcMoveId = isset($item['source_movement_id']) ? (int)$item['source_movement_id'] : 0;
            if ($srcMoveId > 0) {
                $srcMov = validateSupplyLink($srcMoveId, (int)$item['product_id'], $invoiceId);
                if ($stockQty !== (int)$srcMov['qty_in']) {
                    throw new RuntimeException('سطر التوريد لا يطابق الكمية المورّدة أصلاً');
                }
                execute("UPDATE product_stock_movements SET linked_invoice_id = ? WHERE id = ?", [$invoiceId, $srcMoveId]);
                continue;
            }
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
 * All purchasable lines for purchase invoices: products + raw materials.
 * Ingredients carry kind='ingredient' and their own unit config.
 */
function getPurchaseables() {
    $products = getRows("SELECT p.id, p.code, p.name, p.unit, p.price, p.stock_quantity, p.purchase_unit, p.units_per_purchase, p.track_stock, p.is_direct_sale, c.name AS category_name, 'product' AS kind, NULL AS ingredient_id FROM products p LEFT JOIN categories c ON c.id = p.category_id ORDER BY p.name");
    $ingredients = getRows("SELECT i.id AS ingredient_id, i.id, i.sku AS code, i.name, i.base_unit AS unit, i.last_purchase_price AS price, i.current_stock AS stock_quantity, i.purchase_unit, i.conversion_factor AS units_per_purchase, i.category AS category_name, 'ingredient' AS kind FROM ingredients i WHERE i.is_active = 1 ORDER BY i.name");
    return array_merge($products, $ingredients);
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
