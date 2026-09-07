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
    return formatNumber($amount, 2) . ' ' . $currency;
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

// Get and clear error message
function getError() {
    if (isset($_SESSION['error'])) {
        $message = $_SESSION['error'];
        unset($_SESSION['error']);
        return $message;
    }
    return null;
}

// Log activity
function logActivity($action, $description, $user = null) {
    if ($user === null) {
        $user = (isset($_SESSION['full_name']) && !empty($_SESSION['full_name'])) ? $_SESSION['full_name'] : 'النظام';
    }
    $userId = $_SESSION['user_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return insert(
        "INSERT INTO activity_log (user_id, action, description, user, ip_address) VALUES (?, ?, ?, ?, ?)",
        [$userId, $action, $description, $user, $ip]
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

