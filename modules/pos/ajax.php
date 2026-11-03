<?php
/**
 * POS AJAX Handler
 * نقطة البيع - معالجة عمليات الكاشير
 *
 * كل بيعة تُسجَّل كفاتورة بيع عادية (invoices + invoice_items) حتى تدخل
 * تلقائياً في المخزون والتقارير والمرتجعات، لكن الواجهة مستقلة تماماً عن
 * صفحة الفواتير.
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';

requireLogin();
requirePermission('pos.view');

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_products':
        posGetProducts();
        break;
    case 'get_by_barcode':
        posGetByBarcode();
        break;
    case 'checkout':
        posCheckout();
        break;
    case 'hold':
        posHoldCart();
        break;
    case 'list_holds':
        posListHolds();
        break;
    case 'resume_hold':
        posResumeHold();
        break;
    case 'delete_hold':
        posDeleteHold();
        break;
    case 'quick_customer':
        posQuickCustomer();
        break;
    case 'today_summary':
        posTodaySummary();
        break;
    case 'apply_migration':
        posApplyMigration();
        break;
    default:
        posJson(['success' => false, 'error' => 'إجراء غير معروف']);
}

/* ============================================================
 * Helpers
 * ============================================================ */

function posJson($payload, $status = 200) {
    if (!headers_sent()) {
        http_response_code($status);
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function posFail($message, $status = 400) {
    posJson(['success' => false, 'error' => $message], $status);
}

function posBody() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function posColumnExists($table, $column) {
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $row = getRow(
        "SELECT 1 AS found FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
        [$table, $column]
    );
    $cache[$key] = (bool)$row;
    return $cache[$key];
}

function posSchemaReady() {
    return posColumnExists('invoices', 'source')
        && posColumnExists('invoices', 'client_key')
        && posColumnExists('products', 'barcode');
}

function posMoney($value) {
    return round((float)$value, 2);
}

function posWarehouseId($requested = 0) {
    $id = (int)$requested;
    if ($id > 0) {
        $wh = getRow("SELECT id FROM warehouses WHERE id = ? AND is_active = 1", [$id]);
        if ($wh) {
            return $id;
        }
    }
    $fallback = getCurrentWarehouseId();
    if ($fallback && (int)$fallback > 0) {
        return (int)$fallback;
    }
    $def = getDefaultWarehouse();
    return $def ? (int)$def['id'] : 1;
}

function posGenerateNumber($prefix, $table, $column) {
    $last = getRow("SELECT `$column` AS num FROM `$table` WHERE `$column` LIKE ? ORDER BY id DESC LIMIT 1", [$prefix . '%']);
    $next = 1;
    if ($last && !empty($last['num'])) {
        $next = (int)preg_replace('/\D/', '', substr($last['num'], strlen($prefix))) + 1;
    }
    return $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
}

function methodIsCash($method) {
    return in_array($method, ['كاش', 'نقدي', 'cash', 'نقدا'], true);
}

/**
 * Stock available for a product in a warehouse.
 * Falls back to the legacy products.stock_quantity when the product was
 * never linked to warehouse_stock (older data).
 */
function posAvailableStock($warehouseId, $product) {
    $row = getRow(
        "SELECT quantity FROM warehouse_stock WHERE warehouse_id = ? AND product_id = ?",
        [(int)$warehouseId, (int)$product['id']]
    );
    if ($row) {
        return (int)$row['quantity'];
    }
    return max(0, (int)($product['stock_quantity'] ?? 0));
}

/**
 * Make sure the warehouse row exists before subtracting, so legacy products
 * do not go negative on their first POS sale.
 */
function posSeedWarehouseStock($warehouseId, $product) {
    $row = getRow(
        "SELECT id FROM warehouse_stock WHERE warehouse_id = ? AND product_id = ?",
        [(int)$warehouseId, (int)$product['id']]
    );
    if ($row) {
        return;
    }
    $legacy = max(0, (int)($product['stock_quantity'] ?? 0));
    if ($legacy > 0) {
        insert(
            "INSERT INTO warehouse_stock (warehouse_id, product_id, quantity) VALUES (?, ?, ?)",
            [(int)$warehouseId, (int)$product['id'], $legacy]
        );
    }
}

/* ============================================================
 * Catalog
 * ============================================================ */

function posGetProducts() {
    if (!posColumnExists('products', 'barcode')) {
        posFail('قاعدة البيانات تحتاج تحديث POS. شغّل database/migrations/002_pos.sql أولاً.', 409);
    }

    $warehouseId = posWarehouseId($_GET['warehouse_id'] ?? 0);
    $categoryId = (int)($_GET['category_id'] ?? 0);
    $search = trim($_GET['search'] ?? '');

    // Older installs may not have products.category_id / categories table.
    $hasCategoryCol = posColumnExists('products', 'category_id');
    $hasCategories = (bool)getRow("SHOW TABLES LIKE 'categories'");
    $catSelect = ($hasCategoryCol ? 'p.category_id' : 'NULL AS category_id');
    $catName = ($hasCategoryCol && $hasCategories) ? 'c.name AS category_name' : 'NULL AS category_name';
    $catJoin = ($hasCategoryCol && $hasCategories) ? 'LEFT JOIN categories c ON c.id = p.category_id' : '';

    $where = ['1 = 1'];
    $params = [$warehouseId];

    if ($categoryId > 0 && $hasCategoryCol) {
        $where[] = 'p.category_id = ?';
        $params[] = $categoryId;
    }

    if ($search !== '') {
        $term = search_like_term($search);
        $where[] = '(' . search_like_expr('p.name') . ' LIKE ? OR ' . search_like_expr('p.code') . ' LIKE ? OR ' . search_like_expr("COALESCE(p.barcode, '')") . ' LIKE ?)';
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $rows = getRows(
        "SELECT p.id, p.code, p.name, p.unit, p.price, p.stock_quantity, p.image, $catSelect,
                $catName,
                COALESCE(ws.quantity, p.stock_quantity) AS warehouse_qty
         FROM products p
         $catJoin
         LEFT JOIN warehouse_stock ws ON ws.product_id = p.id AND ws.warehouse_id = ?
         WHERE " . implode(' AND ', $where) . "
         ORDER BY (COALESCE(ws.quantity, p.stock_quantity) > 0) DESC, p.name ASC
         LIMIT 200",
        $params
    );

    $products = [];
    foreach ($rows as $row) {
        $products[] = [
            'id' => (int)$row['id'],
            'code' => $row['code'],
            'name' => $row['name'],
            'unit' => $row['unit'],
            'price' => (float)$row['price'],
            'stock' => (int)$row['warehouse_qty'],
            'total_stock' => (int)$row['stock_quantity'],
            'image' => $row['image'] ? BASE_URL . 'assets/uploads/' . $row['image'] : null,
            'category_id' => $row['category_id'] !== null ? (int)$row['category_id'] : null,
            'category_name' => $row['category_name'],
        ];
    }

    posJson(['success' => true, 'products' => $products, 'warehouse_id' => $warehouseId]);
}

function posGetByBarcode() {
    if (!posColumnExists('products', 'barcode')) {
        posFail('قاعدة البيانات تحتاج تحديث POS. شغّل database/migrations/002_pos.sql أولاً.', 409);
    }

    $code = trim($_GET['code'] ?? '');
    if ($code === '') {
        posFail('لم يتم إدخال باركود');
    }

    $warehouseId = posWarehouseId($_GET['warehouse_id'] ?? 0);
    $term = search_like_term($code);

    $hasCategoryCol = posColumnExists('products', 'category_id');
    $hasCategories = (bool)getRow("SHOW TABLES LIKE 'categories'");
    $catName = ($hasCategoryCol && $hasCategories) ? 'c.name AS category_name' : 'NULL AS category_name';
    $catJoin = ($hasCategoryCol && $hasCategories) ? 'LEFT JOIN categories c ON c.id = p.category_id' : '';

    $row = getRow(
        "SELECT p.id, p.code, p.name, p.unit, p.price, p.stock_quantity, p.image,
                $catName,
                COALESCE(ws.quantity, p.stock_quantity) AS warehouse_qty
         FROM products p
         $catJoin
         LEFT JOIN warehouse_stock ws ON ws.product_id = p.id AND ws.warehouse_id = ?
         WHERE p.code = ? OR p.barcode = ? OR p.code LIKE ? OR p.barcode LIKE ?
         LIMIT 1",
        [$warehouseId, $code, $code, $term, $term]
    );

    if (!$row) {
        posFail('لا يوجد صنف بهذا الباركود', 404);
    }

    posJson(['success' => true, 'product' => [
        'id' => (int)$row['id'],
        'code' => $row['code'],
        'name' => $row['name'],
        'unit' => $row['unit'],
        'price' => (float)$row['price'],
        'stock' => (int)$row['warehouse_qty'],
        'total_stock' => (int)$row['stock_quantity'],
        'image' => $row['image'] ? BASE_URL . 'assets/uploads/' . $row['image'] : null,
        'category_name' => $row['category_name'],
    ]]);
}

/* ============================================================
 * Checkout
 * ============================================================ */

function posCheckout() {
    requirePermission('pos.sell');

    if (!posSchemaReady()) {
        posFail('قاعدة البيانات تحتاج تحديث POS. شغّل database/migrations/002_pos.sql أولاً.', 409);
    }

    $data = posBody();
    $items = $data['items'] ?? [];
    if (!is_array($items) || count($items) === 0) {
        posFail('السلة فارغة');
    }

    $warehouseId = posWarehouseId($data['warehouse_id'] ?? 0);
    $discount = posMoney($data['discount'] ?? 0);
    if ($discount < 0) {
        posFail('الخصم لا يمكن أن يكون سالباً');
    }
    if ($discount > 0 && !isAdmin() && !hasPermission('pos.discount')) {
        posFail('لا تملك صلاحية تطبيق خصم', 403);
    }

    $paymentMethod = sanitize($data['payment_method'] ?? 'كاش');
    $paidInput = $data['paid_amount'] ?? null;
    $cashReceived = isset($data['cash_received']) && $data['cash_received'] !== null && $data['cash_received'] !== ''
        ? posMoney($data['cash_received'])
        : null;
    $clientKey = trim((string)($data['client_key'] ?? ''));
    if ($clientKey === '') {
        $clientKey = 'ck-' . bin2hex(random_bytes(8));
    }

    // Idempotency: a retried submit must not create a second invoice.
    $existing = getRow("SELECT id, invoice_number FROM invoices WHERE client_key = ? LIMIT 1", [$clientKey]);
    if ($existing) {
        posJson([
            'success' => true,
            'duplicate' => true,
            'invoice_id' => (int)$existing['id'],
            'invoice_number' => $existing['invoice_number'],
            'print_url' => 'modules/invoices/print.php?id=' . (int)$existing['id'] . '&format=thermal',
        ]);
    }

    $customerId = (int)($data['customer_id'] ?? 0);
    $customerName = sanitize($data['customer_name'] ?? '');
    $customerPhone = sanitize($data['customer_phone'] ?? '');
    $notes = sanitize($data['notes'] ?? '');
    $handledBy = getCurrentUserName();
    $userId = getCurrentUserId();

    try {
        beginTransaction();

        // Lock and validate every line against live warehouse stock.
        $normalized = [];
        $subtotal = 0.0;
        foreach ($items as $line) {
            $productId = (int)($line['product_id'] ?? 0);
            $qty = (int)($line['quantity'] ?? 0);
            if ($productId <= 0 || $qty <= 0) {
                throw new Exception('بند غير صالح في السلة');
            }

            $product = getRow(
                "SELECT id, code, name, unit, price, stock_quantity FROM products WHERE id = ? FOR UPDATE",
                [$productId]
            );
            if (!$product) {
                throw new Exception('صنف غير موجود في قاعدة البيانات');
            }

            $unitPrice = isset($line['unit_price']) ? posMoney($line['unit_price']) : (float)$product['price'];
            if ($unitPrice < 0) {
                throw new Exception('سعر الصنف غير صالح');
            }
            if (abs($unitPrice - (float)$product['price']) > 0.001
                && !isAdmin() && !hasPermission('pos.discount')) {
                throw new Exception('لا تملك صلاحية تعديل سعر الصنف');
            }

            $available = posAvailableStock($warehouseId, $product);
            if ($qty > $available) {
                throw new Exception('الكمية المطلوبة من "' . $product['name'] . '" أكبر من المتاح (' . $available . ')');
            }

            $lineTotal = posMoney($unitPrice * $qty);
            $subtotal += $lineTotal;

            $normalized[] = [
                'product_id' => $productId,
                'code' => $product['code'],
                'name' => $product['name'],
                'unit' => $product['unit'],
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'total' => $lineTotal,
            ];
        }

        $subtotal = posMoney($subtotal);
        if ($discount > $subtotal) {
            throw new Exception('الخصم أكبر من إجمالي الفاتورة');
        }
        $finalAmount = posMoney($subtotal - $discount);

        // Resolve customer (optional).
        $oldBalance = 0.0;
        if ($customerId > 0) {
            $customer = getRow("SELECT id, name, phone, balance FROM customers WHERE id = ?", [$customerId]);
            if (!$customer) {
                throw new Exception('العميل غير موجود');
            }
            $customerName = $customer['name'];
            $customerPhone = $customer['phone'];
            $oldBalance = (float)$customer['balance'];
        } elseif ($customerPhone !== '') {
            $existingCustomer = getRow("SELECT id, name, phone, balance FROM customers WHERE phone = ? LIMIT 1", [$customerPhone]);
            if ($existingCustomer) {
                $customerId = (int)$existingCustomer['id'];
                $customerName = $existingCustomer['name'];
                $oldBalance = (float)$existingCustomer['balance'];
            } elseif ($customerName !== '') {
                $customerId = (int)insert(
                    "INSERT INTO customers (name, phone, balance) VALUES (?, ?, 0)",
                    [$customerName, $customerPhone]
                );
                logActivity('إضافة عميل تلقائي', "تم إضافة العميل تلقائياً: $customerName من الكاشير", $handledBy);
            }
        } elseif ($customerName !== '') {
            // Name only (no phone): reuse an exact match, otherwise create.
            $existingCustomer = getRow("SELECT id, name, phone, balance FROM customers WHERE name = ? LIMIT 1", [$customerName]);
            if ($existingCustomer) {
                $customerId = (int)$existingCustomer['id'];
                $oldBalance = (float)$existingCustomer['balance'];
            } else {
                $customerId = (int)insert(
                    "INSERT INTO customers (name, phone, balance) VALUES (?, NULL, 0)",
                    [$customerName]
                );
                logActivity('إضافة عميل تلقائي', "تم إضافة العميل تلقائياً: $customerName من الكاشير", $handledBy);
            }
        }

        $paidAmount = $paidInput === null ? $finalAmount : posMoney($paidInput);
        if ($paidAmount < 0) {
            throw new Exception('المبلغ المدفوع غير صالح');
        }
        if ($paidAmount > $finalAmount) {
            if ($paymentMethod !== 'كاش' && !methodIsCash($paymentMethod)) {
                throw new Exception('الدفع الزائد مسموح في الكاش فقط');
            }
            if ($cashReceived === null) {
                $cashReceived = $paidAmount;
            }
            $paidAmount = $finalAmount; // store the settled part; the rest is change
        }

        $remaining = posMoney($finalAmount - $paidAmount);
        if ($remaining > 0 && $customerId <= 0) {
            throw new Exception('البيع الآجل يحتاج عميلاً مسجلاً أو اسم وتليفون');
        }

        if ($paidAmount >= $finalAmount && $finalAmount > 0) {
            $paymentStatus = 'paid';
        } elseif ($paidAmount > 0) {
            $paymentStatus = 'partial';
        } else {
            $paymentStatus = 'unpaid';
        }

        $invoiceNumber = posGenerateNumber('POS-', 'invoices', 'invoice_number');

        $invoiceId = (int)insert(
            "INSERT INTO invoices
                (invoice_number, type, customer_id, customer_name, customer_phone, date,
                 total_amount, discount, paid_amount, remaining_amount, old_balance,
                 payment_method, payment_status, handled_by, user_id, warehouse_id, notes,
                 source, client_key, cash_received)
             VALUES (?, 'sale', ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pos', ?, ?)",
            [
                $invoiceNumber, $customerId ?: null, $customerName ?: null, $customerPhone ?: null,
                $subtotal, $discount, $paidAmount, $remaining, $oldBalance,
                $paymentMethod, $paymentStatus, $handledBy, $userId, $warehouseId, $notes ?: null,
                $clientKey, $cashReceived,
            ]
        );

        if (!$invoiceId) {
            throw new Exception('تعذر حفظ الفاتورة');
        }

        $hasItemName = posColumnExists('invoice_items', 'item_name');

        foreach ($normalized as $line) {
            $product = getRow("SELECT id, stock_quantity FROM products WHERE id = ?", [$line['product_id']]);
            if ($product) {
                posSeedWarehouseStock($warehouseId, $product);
            }

            if ($hasItemName) {
                $saved = insert(
                    "INSERT INTO invoice_items (invoice_id, product_id, product_code, product_name, item_name, unit, quantity, unit_price, total)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$invoiceId, $line['product_id'], $line['code'], $line['name'], $line['name'], $line['unit'], $line['quantity'], $line['unit_price'], $line['total']]
                );
            } else {
                $saved = insert(
                    "INSERT INTO invoice_items (invoice_id, product_id, product_code, product_name, unit, quantity, unit_price, total)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    [$invoiceId, $line['product_id'], $line['code'], $line['name'], $line['unit'], $line['quantity'], $line['unit_price'], $line['total']]
                );
            }
            if ($saved === false) {
                throw new Exception('تعذر حفظ أصناف الفاتورة');
            }

            updateWarehouseStock($warehouseId, $line['product_id'], $line['quantity'], 'subtract');
            syncProductTotalStock($line['product_id']);
        }

        if ($customerId > 0) {
            execute("UPDATE customers SET balance = balance - ? WHERE id = ?", [$remaining, $customerId]);
        }

        commit();

        logActivity('بيع كاشير', "تم إنشاء فاتورة كاشير رقم {$invoiceNumber} بقيمة {$finalAmount}", $handledBy);

        $change = 0.0;
        if ($cashReceived !== null && methodIsCash($paymentMethod)) {
            $change = posMoney(max(0, $cashReceived - $paidAmount));
        }

        posJson([
            'success' => true,
            'duplicate' => false,
            'invoice_id' => $invoiceId,
            'invoice_number' => $invoiceNumber,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $finalAmount,
            'paid' => $paidAmount,
            'remaining' => $remaining,
            'change' => $change,
            'print_url' => 'modules/invoices/print.php?id=' . $invoiceId . '&format=thermal',
            'a4_url' => 'modules/invoices/print.php?id=' . $invoiceId . '&format=a4',
        ]);
    } catch (Exception $e) {
        rollback();
        posFail($e->getMessage(), 422);
    }
}

/* ============================================================
 * Held carts
 * ============================================================ */

function posHoldCart() {
    requirePermission('pos.hold');

    $data = posBody();
    $items = $data['items'] ?? [];
    if (!is_array($items) || count($items) === 0) {
        posFail('السلة فارغة');
    }

    $hasTable = getRow("SHOW TABLES LIKE 'pos_holds'");
    if (!$hasTable) {
        posFail('جدول التعليق غير موجود. شغّل migration 002_pos.sql', 409);
    }

    $holdNumber = posGenerateNumber('HLD-', 'pos_holds', 'hold_number');
    $id = (int)insert(
        "INSERT INTO pos_holds (hold_number, warehouse_id, customer_id, customer_name, customer_phone, cart_json, note, user_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $holdNumber,
            posWarehouseId($data['warehouse_id'] ?? 0),
            (int)($data['customer_id'] ?? 0) ?: null,
            sanitize($data['customer_name'] ?? '') ?: null,
            sanitize($data['customer_phone'] ?? '') ?: null,
            json_encode($items, JSON_UNESCAPED_UNICODE),
            sanitize($data['note'] ?? '') ?: null,
            getCurrentUserId(),
        ]
    );

    posJson(['success' => true, 'hold_id' => $id, 'hold_number' => $holdNumber]);
}

function posListHolds() {
    $hasTable = getRow("SHOW TABLES LIKE 'pos_holds'");
    if (!$hasTable) {
        posJson(['success' => true, 'holds' => []]);
    }

    $rows = getRows(
        "SELECT id, hold_number, customer_name, note, cart_json, created_at
         FROM pos_holds ORDER BY id DESC LIMIT 50"
    );

    $holds = [];
    foreach ($rows as $row) {
        $cart = json_decode($row['cart_json'], true) ?: [];
        $count = 0;
        foreach ($cart as $line) {
            $count += (int)($line['quantity'] ?? 0);
        }
        $holds[] = [
            'id' => (int)$row['id'],
            'hold_number' => $row['hold_number'],
            'customer_name' => $row['customer_name'],
            'note' => $row['note'],
            'items_count' => $count,
            'created_at' => $row['created_at'],
        ];
    }

    posJson(['success' => true, 'holds' => $holds]);
}

function posResumeHold() {
    $id = (int)($_GET['id'] ?? 0);
    $row = getRow("SELECT * FROM pos_holds WHERE id = ?", [$id]);
    if (!$row) {
        posFail('السلة المعلقة غير موجودة', 404);
    }

    posJson([
        'success' => true,
        'hold' => [
            'id' => (int)$row['id'],
            'hold_number' => $row['hold_number'],
            'warehouse_id' => (int)$row['warehouse_id'],
            'customer_id' => $row['customer_id'] !== null ? (int)$row['customer_id'] : null,
            'customer_name' => $row['customer_name'],
            'customer_phone' => $row['customer_phone'],
            'note' => $row['note'],
            'items' => json_decode($row['cart_json'], true) ?: [],
        ],
    ]);
}

function posDeleteHold() {
    requirePermission('pos.hold');
    $data = posBody();
    $id = (int)($data['id'] ?? $_POST['id'] ?? $_GET['id'] ?? 0);
    execute("DELETE FROM pos_holds WHERE id = ?", [$id]);
    posJson(['success' => true]);
}

/* ============================================================
 * Customers & summary
 * ============================================================ */

function posApplyMigration() {
    if (!isAdmin()) {
        posFail('تحديث قاعدة البيانات متاح للمدير فقط', 403);
    }

    global $pdo;

    $columns = [
        'invoices' => [
            'source' => "ALTER TABLE `invoices` ADD COLUMN `source` ENUM('manual','pos') NOT NULL DEFAULT 'manual'",
            'client_key' => "ALTER TABLE `invoices` ADD COLUMN `client_key` VARCHAR(64) DEFAULT NULL",
            'cash_received' => "ALTER TABLE `invoices` ADD COLUMN `cash_received` DECIMAL(10,2) DEFAULT NULL",
        ],
        'products' => [
            'barcode' => "ALTER TABLE `products` ADD COLUMN `barcode` VARCHAR(50) DEFAULT NULL",
            'is_service' => "ALTER TABLE `products` ADD COLUMN `is_service` TINYINT(1) NOT NULL DEFAULT 0",
        ],
    ];

    $indexes = [
        ['invoices', 'uq_invoices_client_key', "ALTER TABLE `invoices` ADD UNIQUE KEY `uq_invoices_client_key` (`client_key`)"],
        ['invoices', 'idx_invoices_source_date', "ALTER TABLE `invoices` ADD INDEX `idx_invoices_source_date` (`source`, `date`)"],
        ['products', 'idx_products_barcode', "ALTER TABLE `products` ADD INDEX `idx_products_barcode` (`barcode`)"],
    ];

    try {
        foreach ($columns as $table => $defs) {
            foreach ($defs as $column => $sql) {
                $exists = getRow(
                    "SELECT 1 FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
                    [$table, $column]
                );
                if (!$exists) {
                    $pdo->exec($sql);
                }
            }
        }

        foreach ($indexes as $def) {
            list($table, $indexName, $sql) = $def;
            $exists = getRow(
                "SELECT 1 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?",
                [$table, $indexName]
            );
            if (!$exists) {
                $pdo->exec($sql);
            }
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS `pos_holds` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `hold_number` VARCHAR(30) NOT NULL,
            `warehouse_id` INT DEFAULT NULL,
            `customer_id` INT DEFAULT NULL,
            `customer_name` VARCHAR(255) DEFAULT NULL,
            `customer_phone` VARCHAR(20) DEFAULT NULL,
            `cart_json` LONGTEXT NOT NULL,
            `note` VARCHAR(255) DEFAULT NULL,
            `user_id` INT DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_pos_holds_number` (`hold_number`),
            KEY `idx_pos_holds_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Record it so the bundled updater does not try to re-run 002_pos.sql.
        $pdo->exec("CREATE TABLE IF NOT EXISTS `schema_migrations` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `migration_number` INT NOT NULL,
            `migration_file` VARCHAR(255) NOT NULL,
            `description` VARCHAR(500) DEFAULT NULL,
            `applied_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_migration` (`migration_number`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $stmt = $pdo->prepare("INSERT IGNORE INTO schema_migrations (migration_number, migration_file, description) VALUES (2, '002_pos.sql', 'POS module: invoice source/client_key/cash_received, product barcode/is_service, held carts')");
        $stmt->execute();
    } catch (Exception $e) {
        posFail('تعذر تحديث قاعدة البيانات: ' . $e->getMessage(), 500);
    }

    posJson(['success' => true, 'message' => 'تم تحديث قاعدة البيانات لوحدة الكاشير']);
}

function posQuickCustomer() {
    requirePermission('pos.sell');

    $data = posBody();
    $name = sanitize($data['name'] ?? '');
    $phone = sanitize($data['phone'] ?? '');

    if ($name === '') {
        posFail('اكتب اسم العميل');
    }

    if ($phone !== '') {
        $existing = getRow("SELECT id, name, phone, balance FROM customers WHERE phone = ? LIMIT 1", [$phone]);
        if ($existing) {
            posJson(['success' => true, 'customer' => [
                'id' => (int)$existing['id'],
                'name' => $existing['name'],
                'phone' => $existing['phone'],
                'balance' => (float)$existing['balance'],
                'existing' => true,
            ]]);
        }
    }

    $id = (int)insert("INSERT INTO customers (name, phone, balance) VALUES (?, ?, 0)", [$name, $phone ?: null]);
    $customer = getRow("SELECT id, name, phone, balance FROM customers WHERE id = ?", [$id]);

    posJson(['success' => true, 'customer' => [
        'id' => (int)$customer['id'],
        'name' => $customer['name'],
        'phone' => $customer['phone'],
        'balance' => (float)$customer['balance'],
        'existing' => false,
    ]]);
}

function posTodaySummary() {
    $sourceFilter = posColumnExists('invoices', 'source') ? " AND source = 'pos'" : '';
    $row = getRow(
        "SELECT COUNT(*) AS invoices_count, COALESCE(SUM(total_amount - discount), 0) AS total
         FROM invoices
         WHERE type = 'sale' AND date = CURDATE()" . $sourceFilter
    );

    $lowStock = getRow("SELECT COUNT(*) AS c FROM products WHERE stock_quantity <= min_stock_level");
    $holdsCount = 0;
    if (getRow("SHOW TABLES LIKE 'pos_holds'")) {
        $holdsRow = getRow("SELECT COUNT(*) AS c FROM pos_holds");
        $holdsCount = (int)($holdsRow['c'] ?? 0);
    }

    posJson([
        'success' => true,
        'today_total' => (float)($row['total'] ?? 0),
        'today_count' => (int)($row['invoices_count'] ?? 0),
        'low_stock' => (int)($lowStock['c'] ?? 0),
        'holds' => $holdsCount,
    ]);
}
