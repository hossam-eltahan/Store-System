<?php
/**
 * Expenses Management Helper Functions
 * نظام إدارة محل أجهزة منزلية - دوال إدارة المصروفات والتكاليف
 */

if (!function_exists('getRow')) {
    require_once __DIR__ . '/database.php';
}

/**
 * Get all expense categories
 * @param bool $activeOnly
 * @return array
 */
function getExpenseCategories($activeOnly = true) {
    $where = $activeOnly ? "WHERE is_active = 1" : "";
    return getRows("SELECT * FROM expense_categories {$where} ORDER BY sort_order ASC, name ASC");
}

/**
 * Generate unique sequential expense number (e.g. EXP-00001)
 * @return string
 */
function generateExpenseNumber() {
    $lastExpense = getRow("SELECT expense_number FROM expenses ORDER BY id DESC LIMIT 1");
    if ($lastExpense && !empty($lastExpense['expense_number'])) {
        // Extract numeric portion
        $numPart = preg_replace('/[^0-9]/', '', $lastExpense['expense_number']);
        $nextNum = intval($numPart) + 1;
    } else {
        $nextNum = 1;
    }
    return 'EXP-' . str_pad($nextNum, 5, '0', STR_PAD_LEFT);
}

/**
 * Get total expenses within optional date range and category
 * @param string|null $dateFrom
 * @param string|null $dateTo
 * @param int|null $categoryId
 * @param string|null $payMethod
 * @return float
 */
function getTotalExpenses($dateFrom = null, $dateTo = null, $categoryId = null, $payMethod = null) {
    $conditions = ["1=1"];
    $params = [];

    if ($dateFrom && $dateTo) {
        $conditions[] = "expense_date BETWEEN ? AND ?";
        $params[] = $dateFrom;
        $params[] = $dateTo;
    } elseif ($dateFrom) {
        $conditions[] = "expense_date >= ?";
        $params[] = $dateFrom;
    } elseif ($dateTo) {
        $conditions[] = "expense_date <= ?";
        $params[] = $dateTo;
    }

    if ($categoryId) {
        $conditions[] = "category_id = ?";
        $params[] = $categoryId;
    }

    if ($payMethod) {
        $conditions[] = "payment_method = ?";
        $params[] = $payMethod;
    }

    $where = implode(" AND ", $conditions);
    $row = getRow("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE {$where}", $params);
    return floatval($row['total'] ?? 0);
}

/**
 * Get expenses aggregated by category for a specific period
 * @param string|null $dateFrom
 * @param string|null $dateTo
 * @return array
 */
function getExpensesSummaryByCategory($dateFrom = null, $dateTo = null) {
    $conditions = ["1=1"];
    $params = [];

    if ($dateFrom && $dateTo) {
        $conditions[] = "e.expense_date BETWEEN ? AND ?";
        $params[] = $dateFrom;
        $params[] = $dateTo;
    }

    $where = implode(" AND ", $conditions);
    return getRows("
        SELECT 
            COALESCE(c.id, 0) as category_id,
            COALESCE(c.name, 'غير مصنف') as category_name,
            COALESCE(c.icon, '💸') as category_icon,
            COUNT(e.id) as count_items,
            COALESCE(SUM(e.amount), 0) as total_amount
        FROM expenses e
        LEFT JOIN expense_categories c ON e.category_id = c.id
        WHERE {$where}
        GROUP BY c.id, c.name, c.icon
        ORDER BY total_amount DESC
    ", $params);
}
