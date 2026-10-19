<?php
/**
 * AJAX Handler for Customers
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
require_once '../../config/customer_accounts.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_balance':
        getCustomerBalance();
        break;

    case 'search':
        searchCustomers();
        break;

    case 'get':
        getCustomer();
        break;

    case 'get_dues':
        getOpenDues();
        break;

    case 'settle':
        settleDues();
        break;

    case 'merge_add':
        // Quick partial registration from any name input (keeps engine semantics)
        quickCreate();
        break;

    case 'collect_general':
        // تحصيل عام من رصيد الدفتر (نفس منطق pay_customer.php بالظبط) داخل نافذة
        collectGeneral();
        break;

    case 'sync_balance':
        // مطابقة دفتر العميل مع المستحقات المفتوحة من الفواتير (قرار صريح من المدير)
        syncBalance();
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'إجراء غير معروف']);
}

function getCustomer() {
    $customerId = (int)($_GET['customer_id'] ?? 0);
    $c = getRow("SELECT id, name, phone, balance FROM customers WHERE id = ? AND is_active = 1", [$customerId]);
    if (!$c) {
        echo json_encode(['success' => false, 'message' => 'العميل غير موجود']);
        return;
    }
    $stats = customer_account_stats($customerId);
    $c['open_total'] = $stats['open_total'];
    $c['open_count'] = $stats['open_count'];
    echo json_encode(['success' => true, 'customer' => $c]);
}

function quickCreate() {
    requireAnyPermission(['customers.add', 'pos.create_order', 'customers.view']);
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: [];
    $name = trim($data['name'] ?? '');
    $phone = trim($data['phone'] ?? '');
    if ($name === '') {
        echo json_encode(['success' => false, 'error' => 'اكتب اسم العميل']);
        return;
    }
    $row = findOrCreateCustomer($name, $phone);
    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'تعذر حفظ العميل']);
        return;
    }
    echo json_encode(['success' => true, 'customer' => [
        'id' => (int)$row['id'], 'name' => $row['name'], 'phone' => $row['phone'], 'balance' => (float)$row['balance']
    ]]);
}

function getOpenDues() {
    $customerId = (int)($_GET['customer_id'] ?? 0);
    $open = customer_open_items($customerId);
    $total = 0.0;
    $items = [];
    foreach ($open as $it) {
        $total += $it['remaining_amount'];
        $items[] = [
            'key' => $it['kind'] === 'order' ? 'order:' . $it['ref_id'] : ($it['kind'] === 'gaming_session' ? 'gs:' . $it['ref_id'] : 'bs:' . $it['ref_id']),
            'label' => $it['item_label'],
            'ref_number' => $it['ref_number'],
            'item_date' => $it['item_date'],
            'total_amount' => (float)$it['total_amount'],
            'paid_amount' => (float)$it['paid_amount'],
            'remaining_amount' => $it['remaining_amount'],
        ];
    }
    echo json_encode(['success' => true, 'total' => round($total, 2), 'count' => count($items), 'items' => $items]);
}

function settleDues() {
    requireAnyPermission(['customers.view', 'payments.customer', 'pos.create_order']);
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: [];

    $customerId = (int)($data['customer_id'] ?? 0);
    $amount = isset($data['amount']) && $data['amount'] !== null ? (float)$data['amount'] : null;
    $method = (string)($data['method'] ?? 'cash');
    $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];
    $notes = trim((string)($data['notes'] ?? ''));

    if ($customerId <= 0) {
        echo json_encode(['success' => false, 'error' => 'عميل غير معرف']);
        return;
    }
    if ($amount !== null && $amount <= 0) {
        echo json_encode(['success' => false, 'error' => 'المبلغ المحصل يجب أن يكون أكبر من صفر']);
        return;
    }

    // items=null or [] → settle ALL open items (محاسبة الكل). Otherwise only the sent keys.
    if (empty($items)) {
        foreach (customer_open_items($customerId) as $open) {
            $items[] = $open['kind'] === 'order' ? 'order:' . $open['ref_id']
                : ($open['kind'] === 'gaming_session' ? 'gs:' . $open['ref_id'] : 'bs:' . $open['ref_id']);
        }
        if ($amount === null) $amount = null; // engine default = selected open total
    }

    list($ok, $error, $summary) = settle_customer_items($customerId, $items, $amount, $method, $notes);
    echo json_encode(
        $ok ? ['success' => true, 'summary' => $summary] : ['success' => false, 'error' => $error],
        JSON_UNESCAPED_UNICODE
    );
}

/**
 * تحصيل عام من رصيد الدفتر — نسخة AJAX من pay_customer.php بنفس خطواتها بالظبط:
 * إيصال payments باقم تسلسلي PAY-##### + رفع الرصيد + تسجيل في اللوج، بدون حركة كاش
 * (التحصيل العام قديماً متسجلش في الشيفت). يعيد payment_id لفتح إيصال الطباعة.
 */
function collectGeneral() {
    requireAnyPermission(['payments.customer', 'customers.view']);
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: [];

    $customerId = (int)($data['customer_id'] ?? 0);
    $amount = floatval($data['amount'] ?? 0);
    $method = trim((string)($data['method'] ?? 'كاش'));

    $methodMap = [
        'cash'          => 'كاش',
        'card'          => 'تحويل بنكي',
        'instapay'      => 'تحويل بنكي',
        'vodafone_cash' => 'فودافون كاش',
    ];
    $paymentMethod = $methodMap[$method] ?? ($method !== '' ? $method : 'كاش');

    try {
        beginTransaction();

        if ($amount <= 0) {
            throw new RuntimeException('المبلغ يجب أن يكون أكبر من صفر');
        }

        $customer = getRow("SELECT * FROM customers WHERE id = ?", [$customerId]);
        if (!$customer) {
            throw new RuntimeException('العميل غير موجود');
        }

        $oldBalance = floatval($customer['balance']);

        // نفس قواعد pay_customer.php: العميل لازم يكون مدين، والمبلغ ≤ المستحق
        if ($oldBalance >= 0) {
            throw new RuntimeException('العميل ليس عليه مستحقات');
        }
        $amountOwed = abs($oldBalance);
        if ($amount > $amountOwed) {
            throw new RuntimeException('المبلغ المدفوع (' . number_format($amount, 2) . ') أكبر من المستحق (' . number_format($amountOwed, 2) . ')');
        }

        $newBalance = $oldBalance + $amount; // negative balance = debt

        // رقم إيصال تسلسلي بنفس توليد pay_customer.php
        $lastPayment = getRow("SELECT payment_number FROM payments ORDER BY id DESC LIMIT 1");
        $num = $lastPayment ? (intval(substr($lastPayment['payment_number'], 4)) + 1) : 1;
        $paymentNumber = 'PAY-' . str_pad($num, 5, '0', STR_PAD_LEFT);

        $paymentDate = date('Y-m-d');
        $notes = trim((string)($data['notes'] ?? ''));
        if ($notes === '') $notes = 'تحصيل عام من رصيد العميل (بروفايل العميل)';
        $handledBy = $_SESSION['full_name'] ?? 'المدير';
        $userId = getCurrentUserId();

        $paymentId = insert(
            "INSERT INTO payments (payment_number, type, entity_id, entity_name, amount, old_balance, new_balance, payment_date, payment_method, reference, notes, handled_by, user_id)
             VALUES (?, 'customer', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$paymentNumber, $customerId, $customer['name'], $amount, $oldBalance, $newBalance, $paymentDate, $paymentMethod, '', $notes, $handledBy, $userId]
        );
        if (!$paymentId) {
            throw new RuntimeException('تعذر حفظ الإيصال');
        }

        execute("UPDATE customers SET balance = balance + ? WHERE id = ?", [$amount, $customerId]);

        // تطبيق الدفعة على خطط الأقساط النشطة (كما في pay_customer.php)
        $activePlans = getRows(
            "SELECT * FROM installment_plans WHERE type = 'customer' AND entity_id = ? AND status = 'active' ORDER BY created_at ASC",
            [$customerId]
        );
        $remainingPayment = $amount;
        foreach ($activePlans as $plan) {
            if ($remainingPayment <= 0) break;
            $pendingPayments = getRows(
                "SELECT * FROM installment_payments WHERE plan_id = ? AND status IN ('pending', 'overdue') ORDER BY installment_number",
                [$plan['id']]
            );
            foreach ($pendingPayments as $pInst) {
                if ($remainingPayment <= 0) break;
                if ($remainingPayment >= $pInst['amount']) {
                    execute(
                        "UPDATE installment_payments SET status = 'paid', paid_date = CURDATE(), payment_method = ?, notes = CONCAT(IFNULL(notes, ''), ' - مدفوع من تحصيل عميل') WHERE id = ?",
                        [$paymentMethod, $pInst['id']]
                    );
                    $remainingPayment -= $pInst['amount'];
                } else {
                    $paidPart = $remainingPayment;
                    $newAmount = $pInst['amount'] - $paidPart;
                    execute(
                        "UPDATE installment_payments SET amount = ?, status = 'paid', paid_date = CURDATE(), notes = CONCAT(IFNULL(notes, ''), ' - دفعة جزئية: ', ?) WHERE id = ?",
                        [$paidPart, $amount, $pInst['id']]
                    );
                    $nextPayment = getRow(
                        "SELECT * FROM installment_payments WHERE plan_id = ? AND status = 'pending' AND installment_number > ? ORDER BY installment_number LIMIT 1",
                        [$plan['id'], $pInst['installment_number']]
                    );
                    if ($nextPayment) {
                        execute("UPDATE installment_payments SET amount = amount + ? WHERE id = ?", [$newAmount, $nextPayment['id']]);
                    }
                    $remainingPayment = 0;
                }
            }
            $applyAmount = min($amount, $plan['remaining_amount']);
            $newPaidAmount = $plan['paid_amount'] + $applyAmount;
            $newRemainingAmount = $plan['remaining_amount'] - $applyAmount;
            $newStatus = $newRemainingAmount <= 0 ? 'completed' : 'active';
            execute(
                "UPDATE installment_plans SET paid_amount = ?, remaining_amount = ?, status = ? WHERE id = ?",
                [$newPaidAmount, max(0, $newRemainingAmount), $newStatus, $plan['id']]
            );
        }

        logActivity('دفع مستحقات عميل', "تم تسجيل دفعة من العميل {$customer['name']} بمبلغ {$amount}", $handledBy);

        commit();

        echo json_encode([
            'success'     => true,
            'payment_id'  => (int)$paymentId,
            'payment_number' => $paymentNumber,
            'old_balance' => $oldBalance,
            'new_balance' => $newBalance,
        ], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * مطابقة دفتر العميل: يساوي customers.balance على المستحقات المفتوحة من الفواتير
 * (-open_total). قرار يدوي من صاحب الصلاحية customers.edit، وسوابقه في اللوج + audit.
 */
function syncBalance() {
    requirePermission('customers.edit');
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: [];

    $customerId = (int)($data['customer_id'] ?? 0);
    $customer = getRow("SELECT * FROM customers WHERE id = ?", [$customerId]);
    if (!$customer) {
        echo json_encode(['success' => false, 'error' => 'العميل غير موجود']);
        return;
    }

    $stats = customer_account_stats($customerId);
    $openTotal = (float)$stats['open_total'];
    $newBalance = -1 * $openTotal;
    $oldBalance = (float)$customer['balance'];

    if (abs($newBalance - $oldBalance) <= 0.009) {
        echo json_encode(['success' => false, 'error' => 'الدفتر متطابق بالفعل مع المستحقات المفتوحة']);
        return;
    }

    execute("UPDATE customers SET balance = ? WHERE id = ?", [$newBalance, $customerId]);

    $note = "مطابقة دفتر العميل {$customer['name']}: تغير الرصيد من "
        . number_format($oldBalance, 2) . ' إلى ' . number_format($newBalance, 2)
        . " ليتوافق مع المستحقات المفتوحة (" . number_format($openTotal, 2) . " ج.م)";
    logActivity('مطابقة دفتر عميل', $note, $_SESSION['full_name'] ?? 'المدير');
    execute(
        "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, old_value, new_value, reason, ip_address)
         VALUES (?, 'balance_reconcile', 'customers', ?, ?, ?, ?, ?)",
        [getCurrentUserId(), $customerId, (string)$oldBalance, (string)$newBalance, $note, $_SERVER['REMOTE_ADDR'] ?? '']
    );

    echo json_encode([
        'success' => true,
        'old_balance' => $oldBalance,
        'new_balance' => $newBalance,
        'open_total' => $openTotal,
    ], JSON_UNESCAPED_UNICODE);
}

function getCustomerBalance() {
    $customerId = $_GET['customer_id'] ?? 0;

    $customer = getRow("SELECT balance FROM customers WHERE id = ?", [$customerId]);

    if ($customer) {
        echo json_encode([
            'success' => true,
            'balance' => floatval($customer['balance'])
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Customer not found'
        ]);
    }
}

function searchCustomers() {
    $query = $_GET['q'] ?? '';

    if (strlen($query) < 1) {
        echo json_encode(['success' => false, 'message' => 'Query too short']);
        return;
    }

    $searchTerm = "%$query%";
    $customers = getRows(
        "SELECT id, name, phone, balance FROM customers WHERE (name LIKE ? OR phone LIKE ?) AND is_active = 1 ORDER BY name LIMIT 20",
        [$searchTerm, $searchTerm]
    );

    echo json_encode([
        'success' => true,
        'customers' => $customers
    ]);
}
