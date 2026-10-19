<?php
/**
 * Customer Accounts Engine — محرك حسابات العملاء الدائمين
 * Links orders & hall sessions to customer accounts, tracks open dues
 * per-item and settles them (single item or a consolidated batch).
 *
 * Conventions (kept from the legacy customers/payments module):
 *   - customers.balance: NEGATIVE = the customer owes the store (debt).
 *   - payments table: customer collection receipts (payment_number PAY-#####).
 *   - payment_records: per-source payment rows (used by receipt/shift reports).
 *
 * Item key format: "order:<id>" | "gs:<id>" | "bs:<id>"
 */

if (defined('CUSTOMER_ACCOUNTS_ENGINE')) return;
define('CUSTOMER_ACCOUNTS_ENGINE', true);

/**
 * Exact-name lookup; creates the customer when not found.
 * Returns the customer row or null on failure.
 */
function findOrCreateCustomer($name, $phone = null) {
    $name = trim((string)$name);
    if ($name === '' || mb_strlen($name) > 100) return null;
    $phone = trim((string)($phone ?? ''));
    if ($phone !== '' && mb_strlen($phone) > 20) $phone = mb_substr($phone, 0, 20);

    $row = getRow("SELECT * FROM customers WHERE name = ? ORDER BY id LIMIT 1", [$name]);
    if ($row) return $row;

    $newId = insert(
        "INSERT INTO customers (name, phone, balance, is_active)
         VALUES (?, ?, 0.00, 1)",
        [$name, $phone !== '' ? $phone : null]
    );
    if (!$newId) return null;
    logActivity('إضافة عميل جديد', 'عميل جديد "' . $name . '" أُضيف تلقائياً من تسجيل طلب/جلسة');
    return getRow("SELECT * FROM customers WHERE id = ?", [$newId]);
}

/** Resolve a posted customer_id + posted name/phone into a concrete customer row.
 *  Returns null when: explicit id not found, or no name posted (no customer at all). */
function resolveCustomerInput($customerId, $postedName = null, $postedPhone = null) {
    $customerId = (int)$customerId;
    if ($customerId > 0) {
        // An explicitly chosen customer that vanished never silently falls back by name
        return getRow("SELECT * FROM customers WHERE id = ? AND is_active = 1", [$customerId]);
    }
    $postedName = trim((string)$postedName);
    if ($postedName === '') return null;
    return findOrCreateCustomer($postedName, $postedPhone);
}

function customerTypeLabel($orderType) {
    return ['takeaway' => 'تيك أواي', 'dine_in' => 'طاولة صالة', 'gaming_ps' => 'بلايستيشن', 'gaming_billiard' => 'بلياردو'][$orderType] ?? $orderType;
}

/** Live remaining amount of one account item (authoritative from the source record). */
function customer_item_remaining(array $item) {
    $kind = $item['kind'] ?? null;
    if ($kind === 'order') {
        if (($item['payment_status'] ?? '') === 'refunded' || ($item['status'] ?? '') === 'cancelled') return 0.0;
    } elseif (!in_array($kind, ['gaming_session', 'billiard_session'], true)) {
        return 0.0;
    }
    $total = (float)$item['total_amount'];
    if ($total <= 0) return 0.0;
    $paid = min((float)$item['paid_amount'], $total);
    return max(0.0, round($total - $paid, 2));
}

/**
 * Full account listing for the profile page (settled + unsettled mixed).
 * Each row: kind ('order'|'gaming_session'|'billiard_session'), ref_id, ref_number,
 * item_date, item_label, total_amount, paid_amount, tax_amount, remaining_amount,
 * payment_status ('paid'|'partial'|'unpaid'|...), status.
 */
function customer_all_items($customerId) {
    $customerId = (int)$customerId;

    $orders = getRows(
        "SELECT o.id, o.order_number, o.order_type, o.created_at, o.updated_at, o.status,
                o.subtotal, o.discount_amount, o.tax_amount, o.total_amount, o.paid_amount, o.payment_status
         FROM orders o
         WHERE o.customer_id = ? ORDER BY o.created_at DESC",
        [$customerId]
    );
    $items = [];
    foreach ($orders as $o) {
        $items[] = [
            'kind'            => 'order',
            'rec_source_type' => 'order',
            'ref_id'          => (int)$o['id'],
            'ref_number'      => $o['order_number'],
            'item_label'      => 'طلب ' . customerTypeLabel($o['order_type']),
            'item_date'       => $o['created_at'],
            'subtotal'        => $o['subtotal'],
            'discount_amount' => $o['discount_amount'],
            'tax_amount'      => $o['tax_amount'],
            'total_amount'    => $o['total_amount'],
            'paid_amount'     => $o['paid_amount'],
            'payment_status'  => $o['payment_status'],
            'status'          => $o['status'],
        ];
    }

    foreach (['gaming_session', 'billiard_session'] as $sessKind) {
        $rows = getRows(
            "SELECT id, created_at, ended_at, tax_amount, discount_amount, total_amount, paid_amount, status
             FROM {$sessKind}s
             WHERE customer_id = ? AND status = 'completed' AND total_amount IS NOT NULL",
            [$customerId]
        );
        foreach ($rows as $s) {
            $rem = max(0.0, round((float)$s['total_amount'] - min((float)$s['paid_amount'], (float)$s['total_amount']), 2));
            $items[] = [
                'kind'            => $sessKind,
                'rec_source_type' => $sessKind,
                'ref_id'          => (int)$s['id'],
                'ref_number'      => ($sessKind === 'gaming_session' ? 'بون بلايستيشن #' : 'بون بلياردو #') . $s['id'],
                'item_label'      => $sessKind === 'gaming_session' ? 'بون بلايستيشن' : 'بون بلياردو',
                'item_date'       => $s['ended_at'] ?: $s['created_at'],
                'subtotal'        => $s['total_amount'],
                'discount_amount' => $s['discount_amount'],
                'tax_amount'      => $s['tax_amount'],
                'total_amount'    => $s['total_amount'],
                'paid_amount'     => $s['paid_amount'],
                'payment_status'  => $rem <= 0.009 ? 'paid' : ((float)$s['paid_amount'] > 0 ? 'partial' : 'unpaid'),
                'status'          => $s['status'],
            ];
        }
    }

    usort($items, function ($a, $b) {
        return strtotime((string)$b['item_date']) <=> strtotime((string)$a['item_date']);
    });

    foreach ($items as &$it) {
        $it['remaining_amount'] = customer_item_remaining($it);
    }
    unset($it);
    return $items;
}

/** Open (unsettled) dues for a customer. */
function customer_open_items($customerId) {
    return array_values(array_filter(customer_all_items($customerId), function ($it) {
        return $it['remaining_amount'] > 0.009;
    }));
}

/** Aggregates for profile cards. */
function customer_account_stats($customerId) {
    $items = customer_all_items($customerId);
    $totalBilled = 0.0; $totalPaid = 0.0; $openTotal = 0.0; $openCount = 0;
    foreach ($items as $it) {
        $totalBilled += (float)$it['total_amount'];
        $totalPaid   += min((float)$it['paid_amount'], (float)$it['total_amount']);
        if ($it['remaining_amount'] > 0.009) {
            $openTotal += $it['remaining_amount'];
            $openCount++;
        }
    }
    return [
        'items_count'   => count($items),
        'total_billed'  => round($totalBilled, 2),
        'total_paid'    => round($totalPaid, 2),
        'open_total'    => round($openTotal, 2),
        'open_count'    => $openCount,
    ];
}

/** Sequential payment-number generator matching the payments table convention. */
function next_customer_payment_number() {
    $last = getRow("SELECT payment_number FROM payments ORDER BY id DESC LIMIT 1");
    $num = $last ? (intval(substr((string)$last['payment_number'], 4)) + 1) : 1;
    return 'PAY-' . str_pad((string)$num, 5, '0', STR_PAD_LEFT);
}

/**
 * Settle dues: collect $amount (null = open total of the selected keys) and
 * distribute it FIFO (oldest first) over the selected items. Money trail:
 *   payment_records (per source) + cash_movements + payments (customer receipt)
 *   + customer_settlements (consolidated record) + customers.balance bump.
 * Returns [ok(bool), errorString|null, summary|null].
 */
function settle_customer_items($customerId, array $itemKeys, $amount = null, $method = 'cash', $notes = '') {
    $customerId = (int)$customerId;
    if ($customerId <= 0) return [false, 'عميل غير معرف', null];
    if (!in_array($method, ['cash', 'card', 'instapay', 'vodafone_cash', 'other'], true)) $method = 'cash';

    beginTransaction();
    try {
        $customer = getRow("SELECT id, name, balance FROM customers WHERE id = ? FOR UPDATE", [$customerId]);
        if (!$customer) throw new RuntimeException('العميل غير موجود');

        // 1) Parse keys, then load + lock each item live (skip vanished ones)
        $parsed = [];
        foreach ($itemKeys as $key) {
            if (preg_match('/^(order|gs|bs):(\d+)$/', (string)$key, $m)) $parsed[$m[1] . ':' . $m[2]] = $m;
        }
        $typeMap = ['order' => 'order', 'gs' => 'gaming_session', 'bs' => 'billiard_session'];
        $loaded = [];
        foreach ($parsed as $key => $parts) {
            $short = $parts[1];
            $kind = $typeMap[$short];
            $id = (int)$parts[2];
            if ($kind === 'order') {
                $row = getRow(
                    "SELECT o.*, 'order' AS kind, 'order' AS rec_source_type
                     FROM orders o
                     WHERE o.id = ? AND o.customer_id = ?
                       AND o.status IN ('pending','preparing','ready','served','completed')",
                    [$id, $customerId]
                );
                if ($row) {
                    $row['ref_number'] = $row['order_number'];
                    $row['item_label'] = 'طلب ' . customerTypeLabel($row['order_type']);
                }
            } else {
                $recType = $kind; // payment_records source_type matches exactly
                $label = $kind === 'gaming_session' ? 'بون بلايستيشن' : 'بون بلياردو';
                $row = getRow(
                    "SELECT id, '$kind' AS kind, '$recType' AS rec_source_type,
                            CONCAT('$label #', id) AS ref_number, '$label' AS item_label,
                            created_at, ended_at, discount_amount, tax_amount,
                            total_amount, paid_amount, status
                     FROM {$kind}s
                     WHERE id = ? AND customer_id = ? AND status = 'completed' AND total_amount IS NOT NULL",
                    [$id, $customerId]
                );
                if ($row) {
                    $row = array_merge(
                        getRow("SELECT * FROM {$kind}s WHERE id = ? FOR UPDATE", [$id]) ?: [],
                        $row
                    );
                }
            }
            if (!$row) continue;
            $rem = customer_item_remaining($row);
            if ($rem > 0.009) {
                $loaded[] = ['row' => $row, 'remaining' => $rem];
            }
        }

        if (empty($loaded)) throw new RuntimeException('لا توجد مبالغ متبقية على العناصر المختارة');

        // FIFO oldest first
        usort($loaded, function ($a, $b) {
            $ta = strtotime((string)($a['row']['created_at'] ?? 'now'));
            $tb = strtotime((string)($b['row']['created_at'] ?? 'now'));
            return $ta <=> $tb;
        });

        $openTotal = 0.0;
        foreach ($loaded as $e) $openTotal += $e['remaining'];

        $amount = ($amount === null) ? round($openTotal, 2) : round((float)$amount, 2);
        if ($amount <= 0) throw new RuntimeException('المبلغ المحصل يجب أن يكون أكبر من صفر');
        if ($amount - $openTotal > 0.009) {
            throw new RuntimeException('المبلغ (' . number_format($amount, 2) . ') أكبر من إجمالي المتبقي المختار (' . number_format($openTotal, 2) . ')');
        }

        // 2) Distribute FIFO + per-source payment records
        $left = $amount;
        $applied = [];
        foreach ($loaded as $e) {
            if ($left <= 0) break;
            $take = round(min($e['remaining'], $left), 2);
            if ($take <= 0) continue;
            $row = $e['row'];
            $kind = $row['kind'];

            $newPaid = round((float)$row['paid_amount'] + $take, 2);
            if ($kind === 'order') {
                $newStatus = ($newPaid >= round((float)$row['total_amount'] - 0.009, 2)) ? 'paid' : 'partial';
                execute("UPDATE orders SET paid_amount = ?, payment_status = ? WHERE id = ?", [$newPaid, $newStatus, (int)$row['id']]);
            } else {
                execute("UPDATE {$kind}s SET paid_amount = ? WHERE id = ?", [$newPaid, (int)$row['id']]);
            }

            $payNumber = ($kind === 'order' ? 'PAY-' : ($kind === 'gaming_session' ? 'PAY-PS-' : 'PAY-BS-')) . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
            insert(
                "INSERT INTO payment_records (payment_number, source_type, source_id, amount, payment_method, payment_type, shift_id, created_by)
                 VALUES (?, ?, ?, ?, ?, 'payment', NULL, ?)",
                [$payNumber, $row['rec_source_type'], (int)$row['id'], $take, $method, getCurrentUserId()]
            );

            $applied[] = [
                'key'        => $kind === 'order' ? 'order:' . $row['id'] : ($kind === 'gaming_session' ? 'gs:' . $row['id'] : 'bs:' . $row['id']),
                'kind'       => $kind,
                'source_id'  => (int)$row['id'],
                'ref_number' => $row['ref_number'],
                'label'      => $row['item_label'],
                'item_date'  => (string)($row['item_date'] ?? ($kind === 'order' ? $row['created_at'] : ($row['ended_at'] ?: $row['created_at']))),
                'total_amount' => (float)$row['total_amount'],
                'amount_due_on_item' => $e['remaining'],
                'applied'    => $take,
                'left_after' => round($e['remaining'] - $take, 2),
            ];
            $left = round($left - $take, 2);
        }

        // 3) One collective cash movement + shift totals (same pattern as POS)
        $collected = round($amount, 2);
        $currentShift = getRow("SELECT id FROM shifts WHERE user_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1", [getCurrentUserId()]);
        $shiftId = $currentShift ? $currentShift['id'] : null;
        if ($shiftId) {
            insert(
                "INSERT INTO cash_movements (shift_id, movement_type, amount, payment_method, description, reference_type, reference_id, created_by)
                 VALUES (?, 'sale', ?, ?, ?, 'customer', ?, ?)",
                [$shiftId, $collected, $method, 'تحصيل حساب عميل: ' . $customer['name'], $customerId, getCurrentUserId()]
            );
            if ($method === 'cash') {
                execute("UPDATE shifts SET total_sales_cash = total_sales_cash + ? WHERE id = ?", [$collected, $shiftId]);
            } elseif ($method === 'card') {
                execute("UPDATE shifts SET total_sales_card = total_sales_card + ? WHERE id = ?", [$collected, $shiftId]);
            } else {
                execute("UPDATE shifts SET total_sales_wallet = total_sales_wallet + ? WHERE id = ?", [$collected, $shiftId]);
            }
        }

        // 4) Customer receipt + balance bump (negative = debt)
        $oldBalance = (float)$customer['balance'];
        $newBalance = round($oldBalance + $collected, 2);
        execute("UPDATE customers SET balance = ? WHERE id = ?", [$newBalance, $customerId]);
        insert(
            "INSERT INTO payments (payment_number, type, entity_id, entity_name, amount, old_balance, new_balance, payment_date, payment_method, reference, notes, handled_by, user_id)
             VALUES (?, 'customer', ?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?)",
            [
                next_customer_payment_number(), $customerId, $customer['name'], $collected,
                $oldBalance, $newBalance, $method, 'STL-' . date('Ymd'),
                $notes !== '' ? $notes : 'تسوية فواتير حساب العميل',
                ($_SESSION['full_name'] ?? 'المدير'), getCurrentUserId()
            ]
        );

        // 5) Consolidated settlement record
        $stlNumber = 'STL-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
        $settlementId = insert(
            "INSERT INTO customer_settlements (settlement_number, customer_id, items_count, total_amount, paid_amount, per_item, notes, shift_id, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $stlNumber, $customerId, count($applied), $openTotal, $collected,
                json_encode($applied, JSON_UNESCAPED_UNICODE),
                $notes !== '' ? $notes : null, $shiftId, getCurrentUserId()
            ]
        );
        if (!$settlementId) throw new RuntimeException('تعذر تسجيل التسوية — لم يتم خصم أي مبلغ');
        $stlNumber = $stlNumber;

        commit();

        logActivity('تسوية حساب عميل', 'تحصيل ' . number_format($collected, 2) . ' ج.م من ' . $customer['name'] . ' على ' . count($applied) . ' عنصر (' . $stlNumber . ')');

        return [true, null, [
            'settlement_id'     => (int)$settlementId,
            'settlement_number' => $stlNumber,
            'collected'         => $collected,
            'open_total'        => round($openTotal, 2),
            'applied'           => $applied,
            'old_balance'       => $oldBalance,
            'new_balance'       => $newBalance,
        ]];
    } catch (Throwable $e) {
        rollback();
        $msg = $e instanceof RuntimeException ? $e->getMessage() : 'فشل تسجيل التسوية — لم يتم خصم أي مبلغ، حاول مرة أخرى';
        if (!($e instanceof RuntimeException)) error_log('customer settle error: ' . $e->getMessage());
        return [false, $msg, null];
    }
}
