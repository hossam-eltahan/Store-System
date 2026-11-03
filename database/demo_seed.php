<?php
/**
 * Demo data seeder (بيانات تجريبية)
 * ------------------------------------------------------------
 * يضيف بيانات تجريبية كاملة (فئات، أصناف، عملاء، موردين، فواتير بيع
 * وشراء، مرتجع، تحويل مخزون، مصروفات، مدفوعات، وخطة تقسيط) حتى يمكن
 * تجربة النظام دون لمس أي بيانات حقيقية.
 *
 * كل السجلات موسومة بـ DM- أو [تجريبي] حتى يمكن حذفها في أي وقت.
 *
 * التشغيل من المتصفح: database/demo_seed.php  (للمدير فقط)
 * التشغيل من الطرفية: php database/demo_seed.php run
 * الحذف:              php database/demo_seed.php clean
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

$isCli = (PHP_SAPI === 'cli');

if (!$isCli) {
    require_once __DIR__ . '/../config/auth.php';
    requireLogin();
    if (!isAdmin()) {
        http_response_code(403);
        die('هذه الصفحة للمدير فقط.');
    }
}

$action = $isCli
    ? ($argv[1] ?? 'run')
    : ($_POST['action'] ?? ($_GET['action'] ?? ''));

/* ------------------------------------------------------------
 * Strict helpers — throw on any SQL error so the transaction
 * rolls back instead of leaving half-written demo data.
 * ------------------------------------------------------------ */

function demoQuery($sql, $params = []) {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function demoRow($sql, $params = []) {
    $row = demoQuery($sql, $params)->fetch();
    return $row ?: null;
}

function demoRows($sql, $params = []) {
    return demoQuery($sql, $params)->fetchAll();
}

function demoInsert($sql, $params = []) {
    global $pdo;
    demoQuery($sql, $params);
    return (int)$pdo->lastInsertId();
}

function demoExec($sql, $params = []) {
    return demoQuery($sql, $params)->rowCount();
}

function demoLog(&$log, $line) {
    $log[] = $line;
}

function demoColumnExists($table, $column) {
    return (bool)demoRow(
        "SELECT 1 AS found FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
        [$table, $column]
    );
}

function demoIsService($productId) {
    static $cache = [];
    if (!array_key_exists($productId, $cache)) {
        $row = demoRow("SELECT is_service FROM products WHERE id = ?", [$productId]);
        $cache[$productId] = $row ? ((int)$row['is_service'] === 1) : false;
    }
    return $cache[$productId];
}

function demoWarehouseStockAdd($warehouseId, $productId, $qty) {
    $row = demoRow(
        "SELECT id FROM warehouse_stock WHERE warehouse_id = ? AND product_id = ?",
        [$warehouseId, $productId]
    );
    if ($row) {
        demoExec(
            "UPDATE warehouse_stock SET quantity = quantity + ? WHERE id = ?",
            [$qty, $row['id']]
        );
    } else {
        demoInsert(
            "INSERT INTO warehouse_stock (warehouse_id, product_id, quantity) VALUES (?, ?, ?)",
            [$warehouseId, $productId, $qty]
        );
    }
}

function demoNextNumber($table, $column, $prefix) {
    $last = demoRow(
        "SELECT `$column` AS num FROM `$table` WHERE `$column` LIKE ? ORDER BY id DESC LIMIT 1",
        [$prefix . '%']
    );
    $next = 1;
    if ($last && !empty($last['num'])) {
        $next = (int)preg_replace('/\D/', '', substr($last['num'], strlen($prefix))) + 1;
    }
    return $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

/** Any trace of demo data (used for run idempotency and cleanup). */
function demoHasData() {
    $markers = [
        ["SELECT id FROM products WHERE code LIKE 'DM-%'"],
        ["SELECT id FROM invoices WHERE invoice_number LIKE 'DM-%'"],
        ["SELECT id FROM customers WHERE name LIKE '[تجريبي]%'"],
        ["SELECT id FROM suppliers WHERE name LIKE '[تجريبي]%'"],
        ["SELECT id FROM payments WHERE payment_number LIKE 'DM-%'"],
        ["SELECT id FROM expenses WHERE expense_number LIKE 'DM-%'"],
        ["SELECT id FROM warehouses WHERE code = 'WH-DEMO'"],
    ];
    foreach ($markers as $m) {
        if (demoRow($m[0])) {
            return true;
        }
    }
    return false;
}

/**
 * Insert an invoice (sale or purchase) with its items and stock effect.
 * Returns [invoiceId, invoiceNumber, net, remaining].
 */
function demoInvoice($type, array $data, array $items, $warehouseId, &$log) {
    $total = 0.0;
    foreach ($items as $it) {
        $total += (float)$it['qty'] * (float)$it['price'];
    }
    $total = round($total, 2);
    $discount = round((float)($data['discount'] ?? 0), 2);
    $paid = round((float)($data['paid'] ?? 0), 2);
    $net = round($total - $discount, 2);
    $remaining = round(max(0, $net - $paid), 2);
    $status = $remaining <= 0.009 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');

    $prefix = $type === 'sale' ? 'DM-S-' : 'DM-P-';
    $number = demoNextNumber('invoices', 'invoice_number', $prefix);

    $hasSource = demoColumnExists('invoices', 'source');
    $columns = "invoice_number, type, customer_id, customer_name, customer_phone, supplier_id, date,
                total_amount, discount, paid_amount, remaining_amount, old_balance,
                payment_method, payment_status, handled_by, user_id, warehouse_id, notes";
    $values = "?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?";
    $params = [
        $number, $type,
        $data['customer_id'] ?? null,
        $data['customer_name'] ?? null,
        $data['customer_phone'] ?? null,
        $data['supplier_id'] ?? null,
        $data['date'] ?? date('Y-m-d'),
        $total, $discount, $paid, $remaining,
        $data['old_balance'] ?? 0,
        $data['payment_method'] ?? 'كاش',
        $status,
        $data['handled_by'] ?? 'بيانات تجريبية',
        1, $warehouseId,
        $data['notes'] ?? 'فاتورة تجريبية',
    ];

    if ($hasSource) {
        $columns .= ", source, client_key";
        $values .= ", ?, ?";
        $params[] = $data['source'] ?? 'manual';
        $params[] = $data['client_key'] ?? null;
    }

    $invoiceId = demoInsert("INSERT INTO invoices ($columns) VALUES ($values)", $params);

    $hasItemName = demoColumnExists('invoice_items', 'item_name');
    foreach ($items as $it) {
        $lineTotal = round((float)$it['qty'] * (float)$it['price'], 2);
        if ($hasItemName) {
            demoInsert(
                "INSERT INTO invoice_items (invoice_id, product_id, product_code, product_name, item_name, unit, quantity, unit_price, total)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$invoiceId, $it['product_id'], $it['code'], $it['name'], $it['name'], $it['unit'], $it['qty'], $it['price'], $lineTotal]
            );
        } else {
            demoInsert(
                "INSERT INTO invoice_items (invoice_id, product_id, product_code, product_name, unit, quantity, unit_price, total)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$invoiceId, $it['product_id'], $it['code'], $it['name'], $it['unit'], $it['qty'], $it['price'], $lineTotal]
            );
        }

        // Services have no inventory effect.
        if (!demoIsService($it['product_id'])) {
            if ($type === 'sale') {
                demoWarehouseStockAdd($warehouseId, $it['product_id'], -1 * (int)$it['qty']);
            } else {
                demoWarehouseStockAdd($warehouseId, $it['product_id'], (int)$it['qty']);
            }
        }
    }

    demoLog($log, "فاتورة " . ($type === 'sale' ? 'بيع' : 'شراء') . " {$number} — {$net}");

    return [$invoiceId, $number, $net, $remaining];
}

/* ------------------------------------------------------------
 * Run
 * ------------------------------------------------------------ */

function demoSeed(&$log) {
    global $pdo;

    if (demoHasData()) {
        demoLog($log, 'توجد بيانات تجريبية بالفعل — احذفها أولاً ثم أعد الإضافة.');
        return;
    }

    $pdo->beginTransaction();

    try {
        $userId = 1;
        $handledBy = 'بيانات تجريبية';

        // ---------- Warehouses ----------
        $wh1 = demoRow("SELECT id FROM warehouses WHERE is_default = 1 LIMIT 1")
            ?: demoRow("SELECT id FROM warehouses ORDER BY id ASC LIMIT 1");
        if (!$wh1) {
            $wh1Id = demoInsert(
                "INSERT INTO warehouses (name, code, location, is_default, is_active) VALUES ('المخزن الرئيسي', 'WH-MAIN', 'المقر الرئيسي', 1, 1)"
            );
        } else {
            $wh1Id = (int)$wh1['id'];
        }

        $wh2 = demoRow("SELECT id FROM warehouses WHERE code = 'WH-DEMO' LIMIT 1");
        if ($wh2) {
            $wh2Id = (int)$wh2['id'];
        } else {
            $wh2Id = demoInsert(
                "INSERT INTO warehouses (name, code, location, phone, manager_name, is_default, is_active, notes)
                 VALUES ('[تجريبي] فرع فيصل', 'WH-DEMO', 'فيصل - الجيزة', '01000000000', 'مدير تجريبي', 0, 1, 'مخزن تجريبي')"
            );
        }
        demoLog($log, "المخازن جاهزة (رئيسي #{$wh1Id} / تجريبي #{$wh2Id})");

        // ---------- Categories (لا يوجد عمود icon في هذا الجدول) ----------
        $catDefs = [
            ['[تجريبي] أجهزة كهربائية كبيرة', 'أجهزة منزلية كبيرة الحجم', 1],
            ['[تجريبي] أجهزة منزلية صغيرة', 'أجهزة مطبخ ومنزل صغيرة', 2],
            ['[تجريبي] إلكترونيات', 'شاشات وأجهزة إلكترونية', 3],
            ['[تجريبي] إضاءة', 'لمبات وكشافات', 4],
            ['[تجريبي] مستلزمات كهربائية', 'شواحن ووصلات', 5],
            ['[تجريبي] خدمات', 'خدمات التركيب والتوصيل', 6],
        ];
        $catIds = [];
        foreach ($catDefs as $c) {
            $catIds[$c[0]] = demoInsert(
                "INSERT INTO categories (name, description, sort_order, is_active) VALUES (?, ?, ?, 1)",
                [$c[0], $c[1], $c[2]]
            );
        }
        demoLog($log, 'تمت إضافة ' . count($catDefs) . ' فئة تجريبية');

        // ---------- Products ----------
        $productDefs = [
            ['DM-001', '2000000000011', 'تلفزيون سامسونج 43 بوصة سمارت', '[تجريبي] إلكترونيات', 'قطعة', 12500, 10200, 12, 3],
            ['DM-002', '2000000000028', 'تلفزيون LG 55 بوصة 4K', '[تجريبي] إلكترونيات', 'قطعة', 21000, 17500, 6, 2],
            ['DM-003', '2000000000035', 'غسالة توشيبا 8 كجم', '[تجريبي] أجهزة كهربائية كبيرة', 'قطعة', 9800, 8200, 8, 2],
            ['DM-004', '2000000000042', 'ثلاجة بيكو 400 لتر', '[تجريبي] أجهزة كهربائية كبيرة', 'قطعة', 18500, 15800, 5, 2],
            ['DM-005', '2000000000059', 'مكيف شارب 1.5 حصان', '[تجريبي] أجهزة كهربائية كبيرة', 'قطعة', 22500, 19500, 4, 1],
            ['DM-006', '2000000000066', 'مروحة تورنيدو ستاند', '[تجريبي] أجهزة منزلية صغيرة', 'قطعة', 1350, 1050, 25, 5],
            ['DM-007', '2000000000073', 'مكنسة كهربائية فيليبس', '[تجريبي] أجهزة منزلية صغيرة', 'قطعة', 3200, 2600, 10, 3],
            ['DM-008', '2000000000080', 'ميكروويف سامسونج 30 لتر', '[تجريبي] أجهزة منزلية صغيرة', 'قطعة', 5200, 4300, 9, 3],
            ['DM-009', '2000000000097', 'بوتاجاز يونيفرسال 5 شعله', '[تجريبي] أجهزة كهربائية كبيرة', 'قطعة', 7400, 6200, 7, 2],
            ['DM-010', '2000000000103', 'خلاط مولينكس 600 وات', '[تجريبي] أجهزة منزلية صغيرة', 'قطعة', 1850, 1450, 18, 5],
            ['DM-011', '2000000000110', 'كاتيل زجاج 1.7 لتر', '[تجريبي] أجهزة منزلية صغيرة', 'قطعة', 550, 390, 40, 10],
            ['DM-012', '2000000000127', 'سخان مياه أوليمبك 50 لتر', '[تجريبي] أجهزة كهربائية كبيرة', 'قطعة', 4100, 3400, 6, 2],
            ['DM-013', '2000000000134', 'مصباح LED 12 وات', '[تجريبي] إضاءة', 'قطعة', 65, 40, 150, 30],
            ['DM-014', '2000000000141', 'شاحن سريع 25 وات', '[تجريبي] مستلزمات كهربائية', 'قطعة', 320, 210, 60, 15],
            ['DM-015', '2000000000158', 'وصلة كهرباء 5 متر', '[تجريبي] مستلزمات كهربائية', 'قطعة', 180, 120, 80, 20],
            ['DM-016', '2000000000165', 'كشاف LED 50 وات', '[تجريبي] إضاءة', 'قطعة', 480, 350, 3, 5],
            ['DM-017', '2000000000172', 'مروحة حائط 16 بوصة', '[تجريبي] أجهزة منزلية صغيرة', 'قطعة', 900, 700, 0, 4],
            ['DM-018', '', 'خدمة تركيب وتوصيل', '[تجريبي] خدمات', 'خدمة', 250, 0, 0, 0],
        ];

        $products = [];
        foreach ($productDefs as $p) {
            $isService = ($p[3] === '[تجريبي] خدمات') ? 1 : 0;
            $id = demoInsert(
                "INSERT INTO products (category_id, code, name, unit, price, cost_price, stock_quantity, min_stock_level, barcode, is_service, description)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $catIds[$p[3]] ?? null, $p[0], $p[2], $p[4], $p[5], $p[6],
                    $isService ? 0 : $p[7], $p[8],
                    $p[1] !== '' ? $p[1] : null, $isService, 'صنف تجريبي',
                ]
            );
            $products[$p[0]] = [
                'id' => $id, 'name' => $p[2], 'unit' => $p[4], 'price' => $p[5],
                'is_service' => $isService,
            ];

            if (!$isService) {
                demoWarehouseStockAdd($wh1Id, $id, (int)$p[7]);
            }
        }
        demoLog($log, 'تمت إضافة ' . count($productDefs) . ' صنف تجريبي');

        // ---------- Customers (الرصيد السالب = العميل مدين لنا) ----------
        $customerDefs = [
            ['[تجريبي] أحمد محمود', '01012345678', 'القاهرة - مدينة نصر', 0],
            ['[تجريبي] سارة علي', '01198765432', 'الجيزة - المهندسين', 0],
            ['[تجريبي] محمد حسن', '01234567890', 'الإسكندرية - سموحة', 0],
            ['[تجريبي] شركة النور للأثاث', '01555667788', 'القاهرة - العبور', -120],
            ['[تجريبي] منى إبراهيم', '01099887766', 'المنصورة - المشاية', 0],
            ['[تجريبي] عميل نقدي', '01000000000', '-', 0],
        ];
        $customerIds = [];
        foreach ($customerDefs as $c) {
            $customerIds[$c[0]] = demoInsert(
                "INSERT INTO customers (name, phone, address, balance, notes) VALUES (?, ?, ?, ?, 'عميل تجريبي')",
                [$c[0], $c[1], $c[2], $c[3]]
            );
        }
        demoLog($log, 'تمت إضافة ' . count($customerDefs) . ' عميل تجريبي');

        // ---------- Suppliers (الرصيد الموجب = نحن مدينون للمورد) ----------
        $supplierDefs = [
            ['[تجريبي] شركة الأمل للتوزيع', '0223456789', 'القاهرة - وسط البلد', 0],
            ['[تجريبي] مؤسسة النصر للأجهزة', '0234567891', 'الجيزة - الدقي', 0],
            ['[تجريبي] توكيل الشرق', '0245678912', 'الإسكندرية - العطارين', 0],
        ];
        $supplierIds = [];
        foreach ($supplierDefs as $s) {
            $supplierIds[$s[0]] = demoInsert(
                "INSERT INTO suppliers (name, phone, address, company_name, balance, notes) VALUES (?, ?, ?, ?, ?, 'مورد تجريبي')",
                [$s[0], $s[1], $s[2], $s[0], $s[3]]
            );
        }
        demoLog($log, 'تمت إضافة ' . count($supplierDefs) . ' مورد تجريبي');

        // ---------- Sales invoices ----------
        [$sale1] = demoInvoice('sale', [
            'customer_id' => $customerIds['[تجريبي] عميل نقدي'],
            'customer_name' => '[تجريبي] عميل نقدي',
            'customer_phone' => '01000000000',
            'date' => date('Y-m-d'),
            'paid' => 12680,
            'payment_method' => 'كاش',
            'source' => 'pos',
            'client_key' => 'dm-pos-' . time() . '-1',
            'notes' => 'فاتورة كاشير تجريبية',
        ], [
            ['product_id' => $products['DM-001']['id'], 'code' => 'DM-001', 'name' => $products['DM-001']['name'], 'unit' => 'قطعة', 'qty' => 1, 'price' => 12680],
        ], $wh1Id, $log);

        [$sale2] = demoInvoice('sale', [
            'customer_id' => $customerIds['[تجريبي] سارة علي'],
            'customer_name' => '[تجريبي] سارة علي',
            'customer_phone' => '01198765432',
            'date' => date('Y-m-d', strtotime('-2 days')),
            'paid' => 10050,
            'payment_method' => 'فيزا',
            'notes' => 'فاتورة بيع تجريبية',
        ], [
            ['product_id' => $products['DM-003']['id'], 'code' => 'DM-003', 'name' => $products['DM-003']['name'], 'unit' => 'قطعة', 'qty' => 1, 'price' => 9800],
            ['product_id' => $products['DM-018']['id'], 'code' => 'DM-018', 'name' => $products['DM-018']['name'], 'unit' => 'خدمة', 'qty' => 1, 'price' => 250],
        ], $wh1Id, $log);

        // Partial (credit) sale: the remaining amount is owed by the customer.
        [$sale3, , , $remaining3] = demoInvoice('sale', [
            'customer_id' => $customerIds['[تجريبي] محمد حسن'],
            'customer_name' => '[تجريبي] محمد حسن',
            'customer_phone' => '01234567890',
            'date' => date('Y-m-d', strtotime('-5 days')),
            'paid' => 10000,
            'payment_method' => 'كاش',
            'notes' => 'فاتورة آجلة تجريبية',
        ], [
            ['product_id' => $products['DM-004']['id'], 'code' => 'DM-004', 'name' => $products['DM-004']['name'], 'unit' => 'قطعة', 'qty' => 1, 'price' => 18500],
        ], $wh1Id, $log);
        if ($remaining3 > 0) {
            demoExec("UPDATE customers SET balance = balance - ? WHERE id = ?", [$remaining3, $customerIds['[تجريبي] محمد حسن']]);
        }

        [$sale4] = demoInvoice('sale', [
            'customer_id' => $customerIds['[تجريبي] أحمد محمود'],
            'customer_name' => '[تجريبي] أحمد محمود',
            'customer_phone' => '01012345678',
            'date' => date('Y-m-d', strtotime('-1 day')),
            'paid' => 4550,
            'payment_method' => 'كاش',
            'notes' => 'فاتورة بيع تجريبية',
        ], [
            ['product_id' => $products['DM-006']['id'], 'code' => 'DM-006', 'name' => $products['DM-006']['name'], 'unit' => 'قطعة', 'qty' => 2, 'price' => 1350],
            ['product_id' => $products['DM-010']['id'], 'code' => 'DM-010', 'name' => $products['DM-010']['name'], 'unit' => 'قطعة', 'qty' => 1, 'price' => 1850],
        ], $wh1Id, $log);

        // ---------- Purchase invoice ----------
        [$purchase1, , $purchaseNet] = demoInvoice('purchase', [
            'supplier_id' => $supplierIds['[تجريبي] مؤسسة النصر للأجهزة'],
            'date' => date('Y-m-d'),
            'paid' => 0,
            'payment_method' => 'آجل',
            'notes' => 'فاتورة شراء تجريبية',
        ], [
            ['product_id' => $products['DM-008']['id'], 'code' => 'DM-008', 'name' => $products['DM-008']['name'], 'unit' => 'قطعة', 'qty' => 3, 'price' => 4300],
            ['product_id' => $products['DM-011']['id'], 'code' => 'DM-011', 'name' => $products['DM-011']['name'], 'unit' => 'قطعة', 'qty' => 5, 'price' => 390],
        ], $wh1Id, $log);
        if ($purchaseNet > 0) {
            demoExec("UPDATE suppliers SET balance = balance + ? WHERE id = ?", [$purchaseNet, $supplierIds['[تجريبي] مؤسسة النصر للأجهزة']]);
        }

        // ---------- Customer return (against sale #4) ----------
        $returnNumber = demoNextNumber('returns', 'return_number', 'DM-RET-');
        $returnId = demoInsert(
            "INSERT INTO returns (return_number, type, original_invoice_id, customer_id, customer_name,
                                  return_date, total_amount, old_balance, deducted_from_balance, cash_refund,
                                  refund_method, handled_by, notes, user_id, warehouse_id)
             VALUES (?, 'customer', ?, ?, ?, ?, ?, 0, 0, ?, 'cash', ?, 'مرتجع تجريبي', ?, ?)",
            [
                $returnNumber, $sale4, $customerIds['[تجريبي] أحمد محمود'], '[تجريبي] أحمد محمود',
                date('Y-m-d'), 1350, 1350, $handledBy, $userId, $wh1Id,
            ]
        );
        demoInsert(
            "INSERT INTO return_items (return_id, product_id, product_code, product_name, unit, quantity, unit_price, total)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$returnId, $products['DM-006']['id'], 'DM-006', $products['DM-006']['name'], 'قطعة', 1, 1350, 1350]
        );
        demoWarehouseStockAdd($wh1Id, $products['DM-006']['id'], 1);
        demoLog($log, "مرتجع بيع {$returnNumber} — 1350");

        // ---------- Stock transfer ----------
        $transferNumber = demoNextNumber('stock_transfers', 'transfer_number', 'DM-TRF-');
        $transferId = demoInsert(
            "INSERT INTO stock_transfers (transfer_number, from_warehouse_id, to_warehouse_id, transfer_date, status, notes, created_by)
             VALUES (?, ?, ?, ?, 'completed', 'تحويل مخزون تجريبي', ?)",
            [$transferNumber, $wh1Id, $wh2Id, date('Y-m-d'), $userId]
        );
        demoInsert(
            "INSERT INTO stock_transfer_items (transfer_id, product_id, quantity, notes) VALUES (?, ?, ?, 'تحويل تجريبي')",
            [$transferId, $products['DM-006']['id'], 5]
        );
        demoWarehouseStockAdd($wh1Id, $products['DM-006']['id'], -5);
        demoWarehouseStockAdd($wh2Id, $products['DM-006']['id'], 5);
        demoLog($log, "تحويل مخزون {$transferNumber} — 5 قطع");

        // ---------- Payments ----------
        $customerPay = 2000.00;
        $cust = demoRow("SELECT id, name, balance FROM customers WHERE id = ?", [$customerIds['[تجريبي] محمد حسن']]);
        $oldBalance = (float)$cust['balance'];
        $newBalance = $oldBalance + $customerPay;
        $payNumber = demoNextNumber('payments', 'payment_number', 'DM-PAY-');
        demoInsert(
            "INSERT INTO payments (payment_number, type, entity_id, entity_name, amount, old_balance, new_balance,
                                   payment_date, payment_method, reference, notes, handled_by, user_id)
             VALUES (?, 'customer', ?, ?, ?, ?, ?, ?, 'كاش', 'DM-REF-1', 'تحصيل تجريبي', ?, ?)",
            [$payNumber, $cust['id'], $cust['name'], $customerPay, $oldBalance, $newBalance, date('Y-m-d'), $handledBy, $userId]
        );
        demoExec("UPDATE customers SET balance = ? WHERE id = ?", [$newBalance, $cust['id']]);
        demoLog($log, "تحصيل من عميل {$payNumber} — {$customerPay}");

        $supplierPay = 5000.00;
        $sup = demoRow("SELECT id, name, balance FROM suppliers WHERE id = ?", [$supplierIds['[تجريبي] مؤسسة النصر للأجهزة']]);
        $supOld = (float)$sup['balance'];
        $supNew = $supOld - $supplierPay;
        $supPayNumber = demoNextNumber('payments', 'payment_number', 'DM-PAY-');
        demoInsert(
            "INSERT INTO payments (payment_number, type, entity_id, entity_name, amount, old_balance, new_balance,
                                   payment_date, payment_method, reference, notes, handled_by, user_id)
             VALUES (?, 'supplier', ?, ?, ?, ?, ?, ?, 'تحويل بنكي', 'DM-REF-2', 'دفعة تجريبية لمورد', ?, ?)",
            [$supPayNumber, $sup['id'], $sup['name'], $supplierPay, $supOld, $supNew, date('Y-m-d'), $handledBy, $userId]
        );
        demoExec("UPDATE suppliers SET balance = ? WHERE id = ?", [$supNew, $sup['id']]);
        demoLog($log, "دفعة لمورد {$supPayNumber} — {$supplierPay}");

        // ---------- Installment plan for the credit sale (sale3) ----------
        $planRemaining = 8500.00;
        $planId = demoInsert(
            "INSERT INTO installment_plans (type, entity_id, entity_name, invoice_id, total_amount, paid_amount,
                                            remaining_amount, number_of_installments, installment_amount,
                                            start_date, status, notes, user_id)
             VALUES ('customer', ?, ?, ?, ?, 0, ?, 4, 2125, ?, 'active', 'خطة تقسيط تجريبية', ?)",
            [
                $customerIds['[تجريبي] محمد حسن'], '[تجريبي] محمد حسن', $sale3,
                $planRemaining, $planRemaining, date('Y-m-d'), $userId,
            ]
        );
        $installments = [
            [1, 2125, date('Y-m-d', strtotime('-30 days')), date('Y-m-d', strtotime('-30 days')), 'paid'],
            [2, 2125, date('Y-m-d', strtotime('+0 days')), null, 'pending'],
            [3, 2125, date('Y-m-d', strtotime('+30 days')), null, 'pending'],
            [4, 2125, date('Y-m-d', strtotime('-60 days')), null, 'overdue'],
        ];
        foreach ($installments as $i) {
            demoInsert(
                "INSERT INTO installment_payments (plan_id, payment_number, installment_number, amount, paid_amount,
                                                   due_date, paid_date, payment_method, handled_by, user_id, status, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'قسط تجريبي')",
                [
                    $planId,
                    'DM-INST-' . str_pad((string)$i[0], 4, '0', STR_PAD_LEFT),
                    $i[0], $i[1], $i[4] === 'paid' ? $i[1] : 0,
                    $i[2], $i[3], $i[4] === 'paid' ? 'كاش' : null,
                    $handledBy, $userId, $i[4],
                ]
            );
        }
        demoLog($log, 'تمت إضافة خطة تقسيط تجريبية (4 أقساط)');

        // ---------- Expenses ----------
        $expCatDefs = [
            ['[تجريبي] إيجار', '🏠', 1],
            ['[تجريبي] كهرباء ومياه', '💡', 2],
            ['[تجريبي] مرتبات', '👷', 3],
            ['[تجريبي] صيانة', '🔧', 4],
        ];
        $expCatIds = [];
        foreach ($expCatDefs as $ec) {
            $expCatIds[$ec[0]] = demoInsert(
                "INSERT INTO expense_categories (name, icon, sort_order, is_active) VALUES (?, ?, ?, 1)",
                [$ec[0], $ec[1], $ec[2]]
            );
        }

        $expenseDefs = [
            ['إيجار المحل - شهر تجريبي', '[تجريبي] إيجار', 6000, 0, 'كاش', 'المالك'],
            ['فاتورة كهرباء', '[تجريبي] كهرباء ومياه', 850, 2, 'انستاباي', 'شركة الكهرباء'],
            ['صيانة تكييف', '[تجريبي] صيانة', 400, 4, 'كاش', 'فني الصيانة'],
            ['مرتب عامل', '[تجريبي] مرتبات', 3500, 1, 'كاش', 'محمد العامل'],
        ];
        foreach ($expenseDefs as $e) {
            $expNumber = demoNextNumber('expenses', 'expense_number', 'DM-EXP-');
            demoInsert(
                "INSERT INTO expenses (expense_number, category_id, title, amount, expense_date, payment_method,
                                       paid_to, notes, user_id, handled_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'مصروف تجريبي', ?, ?)",
                [
                    $expNumber, $expCatIds[$e[1]], $e[0], round($e[2], 2),
                    date('Y-m-d', strtotime('-' . $e[3] . ' days')), $e[4], $e[5], $userId, $handledBy,
                ]
            );
        }
        demoLog($log, 'تمت إضافة ' . count($expenseDefs) . ' مصروف تجريبي');

        // ---------- Recalculate product totals ----------
        demoExec(
            "UPDATE products p
             SET stock_quantity = COALESCE((SELECT SUM(quantity) FROM warehouse_stock ws WHERE ws.product_id = p.id), 0)
             WHERE p.code LIKE 'DM-%' AND p.is_service = 0"
        );
        demoExec("UPDATE products SET stock_quantity = 0 WHERE code LIKE 'DM-%' AND is_service = 1");

        $pdo->commit();
        demoLog($log, '✅ تمت إضافة البيانات التجريبية بنجاح.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        demoLog($log, '❌ خطأ: ' . $e->getMessage());
    }
}

/* ------------------------------------------------------------
 * Clean
 * ------------------------------------------------------------ */

function demoClean(&$log) {
    global $pdo;

    if (!demoHasData()) {
        demoLog($log, 'لا توجد بيانات تجريبية للحذف.');
        return;
    }

    $pdo->beginTransaction();
    try {
        // Installment rows first (they reference plans and invoices).
        demoExec(
            "DELETE FROM installment_payments
             WHERE plan_id IN (
                 SELECT id FROM installment_plans
                 WHERE notes LIKE '%تجريبي%' OR invoice_id IN (SELECT id FROM invoices WHERE invoice_number LIKE 'DM-%')
             )"
        );
        demoExec(
            "DELETE FROM installment_plans
             WHERE notes LIKE '%تجريبي%' OR invoice_id IN (SELECT id FROM invoices WHERE invoice_number LIKE 'DM-%')"
        );

        // Invoices and their lines.
        demoExec(
            "DELETE FROM invoice_items
             WHERE invoice_id IN (SELECT id FROM invoices WHERE invoice_number LIKE 'DM-%')"
        );
        demoExec("DELETE FROM invoices WHERE invoice_number LIKE 'DM-%'");

        // Returns.
        demoExec(
            "DELETE FROM return_items
             WHERE return_id IN (SELECT id FROM returns WHERE return_number LIKE 'DM-%')"
        );
        demoExec("DELETE FROM returns WHERE return_number LIKE 'DM-%'");

        // Stock transfers.
        demoExec(
            "DELETE FROM stock_transfer_items
             WHERE transfer_id IN (SELECT id FROM stock_transfers WHERE transfer_number LIKE 'DM-%')"
        );
        demoExec("DELETE FROM stock_transfers WHERE transfer_number LIKE 'DM-%'");

        // Money records.
        demoExec("DELETE FROM payments WHERE payment_number LIKE 'DM-%'");
        demoExec("DELETE FROM expenses WHERE expense_number LIKE 'DM-%'");
        demoExec("DELETE FROM expense_categories WHERE name LIKE '[تجريبي]%'");

        // Warehouse stock and products.
        demoExec(
            "DELETE FROM warehouse_stock
             WHERE product_id IN (SELECT id FROM products WHERE code LIKE 'DM-%')"
        );
        demoExec("DELETE FROM products WHERE code LIKE 'DM-%'");

        // People and categories.
        demoExec("DELETE FROM customers WHERE name LIKE '[تجريبي]%'");
        demoExec("DELETE FROM suppliers WHERE name LIKE '[تجريبي]%'");
        demoExec("DELETE FROM categories WHERE name LIKE '[تجريبي]%'");

        // Demo warehouse only if nothing is left in it.
        $demoWh = demoRow("SELECT id FROM warehouses WHERE code = 'WH-DEMO'");
        if ($demoWh) {
            $used = demoRow("SELECT id FROM warehouse_stock WHERE warehouse_id = ? LIMIT 1", [$demoWh['id']]);
            if (!$used) {
                demoExec("DELETE FROM warehouses WHERE id = ?", [$demoWh['id']]);
            }
        }

        $pdo->commit();
        demoLog($log, '🗑️ تم حذف كل البيانات التجريبية.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        demoLog($log, '❌ خطأ أثناء الحذف: ' . $e->getMessage());
    }
}

/* ------------------------------------------------------------
 * Dispatch
 * ------------------------------------------------------------ */

$log = [];
if ($action === 'run') {
    demoSeed($log);
} elseif ($action === 'clean') {
    demoClean($log);
}

if ($isCli) {
    foreach ($log as $line) {
        echo $line . PHP_EOL;
    }
    exit;
}

$hasDemo = demoHasData();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>بيانات تجريبية</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f8fafc; color: #0f172a; padding: 24px; }
        .box { max-width: 640px; margin: 0 auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 22px; box-shadow: 0 10px 25px rgba(0,0,0,.05); }
        h1 { font-size: 18px; margin: 0 0 8px; }
        p { color: #475569; font-size: 13.5px; line-height: 1.9; }
        .btn { display: inline-block; border: none; border-radius: 10px; padding: 11px 22px; font-weight: 800; font-size: 13.5px; cursor: pointer; font-family: inherit; color: #fff; }
        .run { background: #ea580c; }
        .clean { background: #dc2626; }
        .back { background: #334155; text-decoration: none; }
        ul { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 26px; font-size: 12.5px; line-height: 2; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 16px; }
    </style>
</head>
<body>
<div class="box">
    <h1>🧪 بيانات تجريبية</h1>
    <p>إضافة/حذف مجموعة بيانات كاملة للتجربة: فئات، أصناف، عملاء، موردين، فواتير بيع وشراء، مرتجع، تحويل مخزون، مصروفات، مدفوعات، وخطة تقسيط.</p>
    <?php if ($log): ?>
    <ul>
        <?php foreach ($log as $line): ?>
        <li><?php echo htmlspecialchars($line); ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <form method="post" class="actions">
        <?php if (!$hasDemo): ?>
        <button class="btn run" name="action" value="run" type="submit">➕ إضافة البيانات التجريبية</button>
        <?php else: ?>
        <button class="btn clean" name="action" value="clean" type="submit" onclick="return confirm('حذف كل البيانات التجريبية؟');">🗑️ حذف البيانات التجريبية</button>
        <?php endif; ?>
        <a class="btn back" href="<?php echo BASE_URL; ?>modules/pos/index.php">↩️ رجوع للكاشير</a>
    </form>
</div>
</body>
</html>
