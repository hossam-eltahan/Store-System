<?php
/**
 * POS System Backend Handler (AJAX)
 * كاشير كافيه عروسة النيل
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'غير مصرح بالدخول']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        // 0. جلب طلب سابق لتعديله في الكاشير (وضع التعديل)
        case 'get_order':
            if (!hasPermission('pos.create_order')) {
                echo json_encode(['success' => false, 'error' => 'ليس لديك صلاحية تعديل الطلبات']);
                exit;
            }
            $editOrderId = (int)($_GET['order_id'] ?? 0);
            $editOrder = getRow("SELECT * FROM orders WHERE id = ?", [$editOrderId]);
            if (!$editOrder) {
                echo json_encode(['success' => false, 'error' => 'الطلب غير موجود']);
                exit;
            }
            if (($editOrder['status'] ?? '') === 'cancelled') {
                echo json_encode(['success' => false, 'error' => 'لا يمكن تعديل طلب ملغي']);
                exit;
            }
            $editItems = getRows("SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC", [$editOrderId]);
            foreach ($editItems as &$eit) {
                $eit['modifiers'] = getRows("SELECT * FROM order_item_modifiers WHERE order_item_id = ?", [(int)$eit['id']]);
            }
            echo json_encode([
                'success' => true,
                'order' => [
                    'id' => (int)$editOrder['id'],
                    'order_number' => $editOrder['order_number'],
                    'order_type' => $editOrder['order_type'],
                    'status' => $editOrder['status'],
                    'discount_amount' => (float)$editOrder['discount_amount'],
                    'payment_method' => $editOrder['payment_method'],
                    'customer_name' => $editOrder['customer_name'],
                    'notes' => $editOrder['notes'],
                    'table_session_id' => $editOrder['table_session_id'],
                    'gaming_session_id' => $editOrder['gaming_session_id'],
                    'billiard_session_id' => $editOrder['billiard_session_id'],
                ],
                'items' => $editItems
            ], JSON_UNESCAPED_UNICODE);
            break;

        // 1. جلب المنتجات حسب القسم أو البحث
        case 'get_products':
            $categoryId = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int)$_GET['category_id'] : null;
            $search = trim($_GET['search'] ?? '');

            $sql = "SELECT p.*, c.name as category_name, c.color as category_color
                    FROM products p
                    LEFT JOIN categories c ON p.category_id = c.id
                    WHERE p.is_available = 1 AND p.is_direct_sale = 1";
            $params = [];

            if ($categoryId) {
                $sql .= " AND p.category_id = ?";
                $params[] = $categoryId;
            }

            if ($search !== '') {
                $sql .= " AND (p.name LIKE ? OR p.code LIKE ? OR p.barcode LIKE ?)";
                $searchParam = "%$search%";
                $params[] = $searchParam;
                $params[] = $searchParam;
                $params[] = $searchParam;
            }

            $sql .= " ORDER BY p.display_order ASC, p.name ASC";
            $products = getRows($sql, $params);

            // جلب الـ Variants لكل منتج له خيارات
            foreach ($products as &$prod) {
                if ($prod['has_variants']) {
                    $prod['variants'] = getRows(
                        "SELECT * FROM product_variants WHERE product_id = ? AND is_available = 1 ORDER BY display_order ASC",
                        [$prod['id']]
                    );
                } else {
                    $prod['variants'] = [];
                }
            }

            echo json_encode(['success' => true, 'products' => $products], JSON_UNESCAPED_UNICODE);
            break;

        // 2. جلب خيارات منتج محدد (Variants & Modifiers)
        case 'get_product_details':
            $productId = (int)($_GET['product_id'] ?? 0);
            $product = getRow("SELECT * FROM products WHERE id = ?", [$productId]);
            if (!$product) {
                echo json_encode(['success' => false, 'error' => 'المنتج غير موجود']);
                exit;
            }

            $variants = getRows(
                "SELECT * FROM product_variants WHERE product_id = ? AND is_available = 1 ORDER BY display_order ASC",
                [$productId]
            );

            // جلب الإضافات: المربوطة بالصنف نفسه أولاً، وإلا العامة/القسم (توافق قديم)
            $linkedModifiers = getRows(
                "SELECT m.* FROM product_modifiers m
                 INNER JOIN product_modifier_links l ON l.modifier_id = m.id
                 WHERE l.product_id = ? AND m.is_available = 1
                 ORDER BY l.display_order ASC, m.display_order ASC",
                [$productId]
            );
            if (!empty($linkedModifiers)) {
                $modifiers = $linkedModifiers;
            } else {
                $modifiers = getRows(
                    "SELECT * FROM product_modifiers WHERE is_available = 1 AND (applies_to_category_id IS NULL OR applies_to_category_id = ?) ORDER BY display_order ASC",
                    [$product['category_id']]
                );
            }

            echo json_encode([
                'success' => true,
                'product' => $product,
                'variants' => $variants,
                'modifiers' => $modifiers
            ], JSON_UNESCAPED_UNICODE);
            break;

        // 3. جلب الأجهزة والطاولات النشطة لربط الطلب بها
        case 'get_active_destinations':
            $gamingSessions = getRows(
                "SELECT gs.id, gs.device_id, gd.display_name, gs.session_type, gs.started_at 
                 FROM gaming_sessions gs 
                 JOIN gaming_devices gd ON gs.device_id = gd.id 
                 WHERE gs.status IN ('active', 'paused') 
                 ORDER BY gd.display_order ASC"
            );

            $billiardSessions = getRows(
                "SELECT bs.id, bs.table_id, bt.display_name, bs.billing_mode, bs.started_at 
                 FROM billiard_sessions bs 
                 JOIN billiard_tables bt ON bs.table_id = bt.id 
                 WHERE bs.status IN ('active', 'paused') 
                 ORDER BY bt.display_order ASC"
            );

            $tableSessions = getRows(
                "SELECT ts.id, ts.table_id, ct.display_name, ts.guest_count, ts.customer_name 
                 FROM table_sessions ts 
                 JOIN cafe_tables ct ON ts.table_id = ct.id 
                 WHERE ts.status = 'active' 
                 ORDER BY ct.display_order ASC"
            );

            // جلب الطاولات الشاغرة المتاحة للفتح السريع
            $availableTables = getRows(
                "SELECT id, table_number, display_name, section FROM cafe_tables WHERE status = 'available' AND is_active = 1 ORDER BY display_order ASC"
            );

            echo json_encode([
                'success' => true,
                'gaming_sessions' => $gamingSessions,
                'billiard_sessions' => $billiardSessions,
                'table_sessions' => $tableSessions,
                'available_tables' => $availableTables
            ], JSON_UNESCAPED_UNICODE);
            break;

        // 4. حفظ وتسجيل الطلب وإتمام الدفع أو تعليقه
        case 'checkout':
            if (!hasPermission('pos.create_order')) {
                echo json_encode(['success' => false, 'error' => 'ليس لديك صلاحية إنشاء طلبات']);
                exit;
            }

            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);

            if (!$data || empty($data['items'])) {
                echo json_encode(['success' => false, 'error' => 'السلة فارغة، يرجى إضافة أصناف أولاً']);
                exit;
            }

            $orderType = $data['order_type'] ?? 'takeaway';
            $gamingSessionId = !empty($data['gaming_session_id']) ? (int)$data['gaming_session_id'] : null;
            $billiardSessionId = !empty($data['billiard_session_id']) ? (int)$data['billiard_session_id'] : null;
            $tableSessionId = !empty($data['table_session_id']) ? (int)$data['table_session_id'] : null;
            $tableId = !empty($data['table_id']) ? (int)$data['table_id'] : null;
            $items = $data['items'];
            $discountAmount = max(0, floatval($data['discount_amount'] ?? 0));
            $paymentMethod = $data['payment_method'] ?? 'cash';
            $clientKey = preg_replace('/[^A-Za-z0-9\-_]/', '', (string)($data['client_key'] ?? ''));
            if ($clientKey === '') {
                $clientKey = null;
            }
            $customerName = trim($data['customer_name'] ?? '');
            $customerPhone = trim($data['customer_phone'] ?? '');
            $orderNotes = trim($data['notes'] ?? '');
            // Edit mode: update an existing order in place instead of creating one
            $editOrderId = !empty($data['edit_order_id']) ? (int)$data['edit_order_id'] : null;

            beginTransaction();

            $oldOrder = null;
            if ($editOrderId) {
                $oldOrder = getRow("SELECT * FROM orders WHERE id = ? FOR UPDATE", [$editOrderId]);
                if (!$oldOrder) {
                    throw new RuntimeException('الطلب المراد تعديله غير موجود');
                }
                if (($oldOrder['status'] ?? '') === 'cancelled') {
                    throw new RuntimeException('لا يمكن تعديل طلب ملغي');
                }
                // Destination, type and sessions stay exactly as originally stored
                $orderType = $oldOrder['order_type'];
                $gamingSessionId = !empty($oldOrder['gaming_session_id']) ? (int)$oldOrder['gaming_session_id'] : null;
                $billiardSessionId = !empty($oldOrder['billiard_session_id']) ? (int)$oldOrder['billiard_session_id'] : null;
                $tableSessionId = !empty($oldOrder['table_session_id']) ? (int)$oldOrder['table_session_id'] : null;
            }

            // Idempotency: a retried submit with the same client key returns the original order
            // without creating a second order, session, payment or stock movement.
            // Skipped in edit mode (we update by explicit id, never insert).
            if (!$editOrderId && $clientKey !== null) {
                $dup = getRow("SELECT id, order_number, total_amount, paid_amount FROM orders WHERE client_key = ?", [$clientKey]);
                if ($dup) {
                    commit();
                    echo json_encode([
                        'success' => true,
                        'order_id' => $dup['id'],
                        'order_number' => $dup['order_number'],
                        'total_amount' => (float)$dup['total_amount'],
                        'paid_amount' => (float)$dup['paid_amount'],
                        'is_deferred' => false,
                        'duplicate' => true
                    ], JSON_UNESCAPED_UNICODE);
                    break;
                }
            }

            // إذا اختار طاولة شاغرة مباشرة لطلب Dine-In نفتح جلسة طاولة تلقائياً
            // (طلبات جديدة فقط — التعديل يحتفظ بجلساته الأصلية)
            if (!$editOrderId && $orderType === 'dine_in' && !$tableSessionId && $tableId) {
                $tableSessionId = insert(
                    "INSERT INTO table_sessions (table_id, opened_at, status, opened_by, customer_name) VALUES (?, NOW(), 'active', ?, ?)",
                    [$tableId, getCurrentUserId(), $data['customer_name'] ?? 'عميل صالة']
                );
                execute("UPDATE cafe_tables SET status = 'occupied' WHERE id = ?", [$tableId]);
            }

            // فحص الشيفت النشط الحالي للمستخدم
            $currentShift = getRow("SELECT id FROM shifts WHERE user_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1", [getCurrentUserId()]);
            $shiftId = $currentShift ? $currentShift['id'] : null;

            // توليد رقم الطلب
            $prefix = getSetting('invoice_prefix_sale', 'ORD-');
            $orderNumber = $prefix . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

            // حساب الإجمالي
            $subtotal = 0;
            foreach ($items as $item) {
                $qty = max(1, (int)($item['quantity'] ?? 1));
                $price = floatval($item['unit_price'] ?? 0);
                $modsTotal = 0;
                if (!empty($item['modifiers'])) {
                    foreach ($item['modifiers'] as $m) {
                        $modsTotal += floatval($m['price'] ?? 0) * (int)($m['quantity'] ?? 1);
                    }
                }
                $subtotal += ($price * $qty) + ($modsTotal * $qty);
            }

            $discountAmount = min($discountAmount, $subtotal);

            $discountedSubtotal = max(0, $subtotal - $discountAmount);
            $vatRate = max(0, (float)getSetting('vat_rate', 0));
            // Hall service applies to everything EXCEPT takeaway
            $serviceRate = ($orderType === 'takeaway') ? 0 : max(0, (float)getSetting('service_charge', 0));
            $taxAmount = round($discountedSubtotal * (($vatRate + $serviceRate) / 100), 2);
            $totalAmount = $discountedSubtotal + $taxAmount;

            // تحديد حالة الدفع
            // إذا كان الطلب مربوطاً بجلسة بلايستيشن أو بلياردو أو طاولة مفتوحة، يمكن أن يكون الحساب معلقاً حتى إغلاق الجلسة
            $isDeferred = in_array($orderType, ['gaming_ps', 'gaming_billiard', 'dine_in']) && empty($data['pay_now']);
            $paidAmount = $isDeferred ? 0 : $totalAmount;
            $paymentStatus = $isDeferred ? 'unpaid' : 'paid';
            $orderStatus = $isDeferred ? 'preparing' : 'completed';

            if ($oldOrder) {
                // ---- Edit mode: update the order in place ----
                $orderId = (int)$oldOrder['id'];
                $orderNumber = $oldOrder['order_number'];
                $oldTotal = (float)$oldOrder['total_amount'];
                // Money already collected stays untouched; only the status follows the new total
                $paidAmount = (float)$oldOrder['paid_amount'];
                $paymentStatus = $paidAmount >= $totalAmount ? 'paid' : ($paidAmount > 0 ? 'partial' : 'unpaid');
                $isDeferred = false;

                execute(
                    "UPDATE orders SET subtotal = ?, discount_amount = ?, tax_amount = ?, total_amount = ?,
                                     payment_status = ?, customer_name = ?, customer_phone = ?, notes = ?
                     WHERE id = ?",
                    [$subtotal, $discountAmount, $taxAmount, $totalAmount, $paymentStatus, $customerName, $customerPhone, $orderNotes, $orderId]
                );

                // Reverse the old stock deduction (tracked items only), then clear old lines
                $oldItems = getRows("SELECT * FROM order_items WHERE order_id = ?", [$orderId]);
                foreach ($oldItems as $oi) {
                    $oldPid = (int)($oi['product_id'] ?? 0);
                    if ($oldPid > 0) {
                        $oldProd = getRow("SELECT id, unit, track_stock FROM products WHERE id = ? FOR UPDATE", [$oldPid]);
                        if ($oldProd && (int)$oldProd['track_stock'] === 1) {
                            updateWarehouseStock(getCurrentWarehouseId(), $oldPid, (int)$oi['quantity'], 'add');
                            recordProductMovement($oldPid, getCurrentWarehouseId(), 'reversal', (int)$oi['quantity'], 0,
                                $oldProd['unit'], 'pos_order', $orderId, 'عكس بيع قبل تعديل الطلب ' . $orderNumber);
                        }
                    }
                    execute("DELETE FROM order_item_modifiers WHERE order_item_id = ?", [(int)$oi['id']]);
                }
                execute("DELETE FROM order_items WHERE order_id = ?", [$orderId]);
            } else {
            // إنشاء الطلب
            $orderId = insert(
                "INSERT INTO orders (order_number, order_type, table_session_id, gaming_session_id, billiard_session_id,
                                     status, subtotal, discount_amount, tax_amount, total_amount, paid_amount, payment_status,
                                     payment_method, customer_name, customer_phone, notes, shift_id, created_by, client_key)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $orderNumber, $orderType, $tableSessionId, $gamingSessionId, $billiardSessionId,
                    $orderStatus, $subtotal, $discountAmount, $taxAmount, $totalAmount, $paidAmount, $paymentStatus,
                    $paymentMethod, $customerName, $customerPhone, $orderNotes, $shiftId, getCurrentUserId(), $clientKey
                ]
            );

            if (!$orderId) {
                throw new RuntimeException('تعذر إنشاء الطلب في قاعدة البيانات');
            }
            }

            // إدخال بنود الطلب
            foreach ($items as $item) {
                $qty = max(1, (int)($item['quantity'] ?? 1));
                $unitPrice = floatval($item['unit_price'] ?? 0);
                $modsTotal = 0;
                if (!empty($item['modifiers'])) {
                    foreach ($item['modifiers'] as $m) {
                        $modsTotal += floatval($m['price'] ?? 0) * (int)($m['quantity'] ?? 1);
                    }
                }
                $lineTotal = ($unitPrice * $qty) + ($modsTotal * $qty);

                $orderItemId = insert(
                    "INSERT INTO order_items (order_id, product_id, variant_id, product_name, variant_name, 
                                              unit_price, quantity, modifiers_total, line_total, item_notes)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $orderId, (int)$item['product_id'], !empty($item['variant_id']) ? (int)$item['variant_id'] : null,
                        $item['product_name'], $item['variant_name'] ?? null, $unitPrice, $qty, $modsTotal, $lineTotal,
                        $item['notes'] ?? null
                    ]
                );

                if (!$orderItemId) {
                    throw new RuntimeException('تعذر إضافة صنف الطلب في قاعدة البيانات');
                }

                // إدخال الإضافات
                if (!empty($item['modifiers'])) {
                    foreach ($item['modifiers'] as $m) {
                        insert(
                            "INSERT INTO order_item_modifiers (order_item_id, modifier_id, modifier_name, modifier_price, quantity)
                             VALUES (?, ?, ?, ?, ?)",
                            [$orderItemId, (int)$m['id'], $m['name'], floatval($m['price']), (int)($m['quantity'] ?? 1)]
                        );
                    }
                }

                // خصم المخزون للأصناف المتتبعة فقط (داخل نفس المعاملة — أي فشل يلغي كل شيء)
                $saleProductId = (int)$item['product_id'];
                if ($saleProductId > 0) {
                    $saleProduct = getRow("SELECT id, track_stock FROM products WHERE id = ? FOR UPDATE", [$saleProductId]);
                    if ($saleProduct && (int)$saleProduct['track_stock'] === 1) {
                        removeProductStock($saleProductId, getCurrentWarehouseId(), $qty, 'sale', 'pos_order', $orderId, 'بيع كاشير ' . $orderNumber);
                    }
                }
            }

            // إذا تم الدفع فوراً (الطلبات الجديدة فقط — التعديل لا يمس المدفوعات والشيفت)
            if (!$oldOrder && $paidAmount > 0) {
                $payNumber = 'PAY-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
                insert(
                    "INSERT INTO payment_records (payment_number, source_type, source_id, amount, payment_method, payment_type, shift_id, created_by)
                     VALUES (?, 'order', ?, ?, ?, 'payment', ?, ?)",
                    [$payNumber, $orderId, $paidAmount, $paymentMethod, $shiftId, getCurrentUserId()]
                );

                // تسجيل حركة نقدية في الشيفت
                if ($shiftId) {
                    insert(
                        "INSERT INTO cash_movements (shift_id, movement_type, amount, payment_method, description, reference_type, reference_id, created_by)
                         VALUES (?, 'sale', ?, ?, ?, 'order', ?, ?)",
                        [$shiftId, $paidAmount, $paymentMethod, "مبيعات طلب كاشير #$orderNumber", $orderId, getCurrentUserId()]
                    );

                    // تحديث إجماليات الشيفت
                    if ($paymentMethod === 'cash') {
                        execute("UPDATE shifts SET total_sales_cash = total_sales_cash + ? WHERE id = ?", [$paidAmount, $shiftId]);
                    } elseif ($paymentMethod === 'card') {
                        execute("UPDATE shifts SET total_sales_card = total_sales_card + ? WHERE id = ?", [$paidAmount, $shiftId]);
                    } else {
                        execute("UPDATE shifts SET total_sales_wallet = total_sales_wallet + ? WHERE id = ?", [$paidAmount, $shiftId]);
                    }
                }
            }

            // تحديث إجماليات الجلسات: طلب جديد يضيف الإجمالي، والتعديل يضيف الفرق فقط
            $sessionDelta = $oldOrder ? ($totalAmount - $oldTotal) : $totalAmount;
            // إذا كان الطلب مضافاً لجلسة بلايستيشن، نحدث إجمالي الطلبات في الجلسة
            if ($gamingSessionId) {
                execute("UPDATE gaming_sessions SET cafe_orders_total = cafe_orders_total + ? WHERE id = ?", [$sessionDelta, $gamingSessionId]);
            }

            // إذا كان الطلب مضافاً لجلسة بلياردو
            if ($billiardSessionId) {
                execute("UPDATE billiard_sessions SET cafe_orders_total = cafe_orders_total + ? WHERE id = ?", [$sessionDelta, $billiardSessionId]);
            }

            // إذا كان الطلب مضافاً لطاولة
            if ($tableSessionId) {
                execute("UPDATE table_sessions SET total_amount = total_amount + ? WHERE id = ?", [$sessionDelta, $tableSessionId]);
            }

            commit();

            echo json_encode([
                'success' => true,
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'is_deferred' => $isDeferred,
                'edited' => (bool)$oldOrder,
                'message' => $oldOrder ? 'تم تحديث الطلب بنجاح' : 'تم حفظ الطلب بنجاح'
            ], JSON_UNESCAPED_UNICODE);
            break;

        // 5. جلب آخر الطلبات لليوم
        case 'get_recent_orders':
            $orders = getRows(
                "SELECT o.*, u.full_name as cashier_name,
                        (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as items_count 
                 FROM orders o 
                 LEFT JOIN users u ON o.created_by = u.id 
                 WHERE DATE(o.created_at) = CURDATE() 
                 ORDER BY o.id DESC LIMIT 20"
            );
            echo json_encode(['success' => true, 'orders' => $orders], JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'طلب غير صالح']);
            break;
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        rollback();
    }
    echo json_encode(['success' => false, 'error' => 'حدث خطأ: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
