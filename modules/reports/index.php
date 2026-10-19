<?php
/**
 * Reports Dashboard - Unified Engine with Autocomplete & Phone Auto-fill
 * نظام إدارة محل أجهزة منزلية - مركز التقارير الشامل الموحد
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
require_once '../../config/expenses.php';
requireAnyPermission(['reports.view', 'reports.statement']);

$pageTitle = 'مركز التقارير';

// Show All Handling: default to empty dates if not explicitly provided, so queries return all records
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$filterEntity = $_GET['entity_id'] ?? '';
$filterPhone = $_GET['entity_phone'] ?? '';
$filterPayMethod = $_GET['pay_method'] ?? '';
$filterStatus = $_GET['filter_status'] ?? '';
$filterSubType = $_GET['sub_type'] ?? '';
$filterUserId = $_GET['user_id'] ?? '';
$filterExpenseCat = !empty($_GET['category_id']) ? intval($_GET['category_id']) : '';
$userSubView = $_GET['user_view'] ?? 'overview';
if (!in_array($userSubView, ['overview', 'orders', 'sessions', 'collections', 'activity'], true)) {
    $userSubView = 'overview';
}

$allReportTypes = [
    'daily' => '📊 ملخص الدخل الشامل',
    'orders' => '☕ مبيعات المنيو والطلبات',
    'stock' => '🏭 المخزون والنواقص',
    'purchases' => '📥 المشتريات والموردين',
    'gaming' => '🎮 إيرادات البلايستيشن',
    'billiards' => '🎱 إيرادات البلياردو',
    'payments' => '💵 التحصيلات والمدفوعات',
    'shifts' => '⏱️ تقارير تقفيل الشيفتات',
    'expenses' => '💸 المصروفات والتكاليف',
    'profit' => '📈 الأرباح وصافي الدخل',
    'statement' => '🧾 كشف حساب',
    'users' => '👤 تقارير ونشاط المستخدمين'
];

if (isAdmin()) {
    $typeNames = $allReportTypes;
    $reportType = $_GET['type'] ?? 'daily';
} else {
    // Non-admin employee: only allowed types based on explicit permissions
    $typeNames = [];
    if (hasPermission('reports.view')) {
        $typeNames['users'] = '👤 تقارير ونشاط المستخدم';
    }
    if (hasPermission('reports.statement')) {
        $typeNames['statement'] = '🧾 كشف حساب';
    }
    if (hasPermission('inventory.view')) {
        $typeNames['stock'] = $allReportTypes['stock'];
    }
    if (hasPermission('invoices.purchase.view')) {
        $typeNames['purchases'] = $allReportTypes['purchases'];
    }
    
    // Validate requested report type
    $requestedType = $_GET['type'] ?? '';
    if (!empty($requestedType) && isset($typeNames[$requestedType])) {
        $reportType = $requestedType;
    } else {
        // Fallback to first permitted tab
        $reportType = !empty($typeNames) ? array_key_first($typeNames) : '';
        if (!$reportType) {
            showAccessDenied();
            exit;
        }
    }
    
    // Always lock user_id to self for employee
    $filterUserId = getCurrentUserId();
}

// Fallback for stale bookmarks pointing to removed report tabs
if (!isset($typeNames[$reportType])) {
    $reportType = 'daily';
}

// Fetch lists for JS Autocomplete and user filtering
$customersList = getRows("SELECT id, name, phone, balance FROM customers ORDER BY name ASC");
$suppliersList = getRows("SELECT id, name, phone, balance FROM suppliers ORDER BY name ASC");
$usersList = getRows("SELECT id, username, full_name, role FROM users ORDER BY (role='admin') DESC, full_name ASC");
$expenseCategoriesList = getExpenseCategories(false);

$columns = [];
$data = [];
$summaryCards = [];
$activeFilters = []; 

// =====================================
// DATA & REPORT CALCULATIONS ENGINE
// =====================================
switch ($reportType) {
    case 'daily':
        $activeFilters = ['date'];
        
        $dateParams = [];
        $dateCondOrd = "";
        $dateCondGam = "";
        $dateCondBil = "";
        $dateCondExp = "";
        if ($dateFrom && $dateTo) {
            $dateCondOrd = "AND DATE(o.created_at) BETWEEN ? AND ?";
            $dateCondGam = "AND DATE(gs.started_at) BETWEEN ? AND ?";
            $dateCondBil = "AND DATE(bs.started_at) BETWEEN ? AND ?";
            $dateCondExp = "AND date BETWEEN ? AND ?";
            $dateParams = [$dateFrom, $dateTo];
        }

        $ordersRow = getRow("SELECT COUNT(*) as c, COALESCE(SUM(total_amount), 0) as total, COALESCE(SUM(paid_amount), 0) as paid FROM orders o WHERE status != 'cancelled' {$dateCondOrd}", $dateParams);
        $gamingRow = getRow("SELECT COUNT(*) as c, COALESCE(SUM(gaming_charge), 0) as gaming_rev, COALESCE(SUM(total_amount), 0) as total_rev FROM gaming_sessions gs WHERE status = 'completed' {$dateCondGam}", $dateParams);
        $billiardRow = getRow("SELECT COUNT(*) as c, COALESCE(SUM(gaming_charge), 0) as billiard_rev, COALESCE(SUM(total_amount), 0) as total_rev FROM billiard_sessions bs WHERE status = 'completed' {$dateCondBil}", $dateParams);
        $expensesRow = getRow("SELECT COUNT(*) as c, COALESCE(SUM(amount), 0) as total FROM expenses WHERE 1=1 {$dateCondExp}", $dateParams);

        $cafeRev = floatval($ordersRow['paid'] ?? 0);
        $cafePending = floatval($ordersRow['total'] ?? 0) - $cafeRev;
        $psRev = floatval($gamingRow['gaming_rev'] ?? 0);
        $bilRev = floatval($billiardRow['billiard_rev'] ?? 0);
        $totalIncome = $cafeRev + $psRev + $bilRev;
        $totalExpense = floatval($expensesRow['total'] ?? 0);
        $netProfit = $totalIncome - $totalExpense;

        $summaryCards = [
            ['title'=>'☕ مبيعات المنيو والكافيه', 'value'=>formatCurrency($cafeRev), 'color'=>'primary', 'icon'=>'fa-coffee'],
            ['title'=>'🎮 إيرادات البلايستيشن', 'value'=>formatCurrency($psRev), 'color'=>'info', 'icon'=>'fa-gamepad'],
            ['title'=>'🎱 إيرادات البلياردو', 'value'=>formatCurrency($bilRev), 'color'=>'success', 'icon'=>'fa-circle'],
            ['title'=>'📈 إجمالي الإيرادات الشاملة', 'value'=>formatCurrency($totalIncome), 'color'=>'success', 'icon'=>'fa-money-bill-wave'],
            ['title'=>'⏳ مبالغ معلقة (غير محصلة)', 'value'=>formatCurrency($cafePending), 'color'=>'warning', 'icon'=>'fa-clock'],
            ['title'=>'💸 المصروفات التشغيلية', 'value'=>formatCurrency($totalExpense), 'color'=>'danger', 'icon'=>'fa-file-invoice-dollar'],
            ['title'=>'💎 صافي الربح الفعلي', 'value'=>formatCurrency($netProfit), 'color'=>($netProfit >= 0 ? 'success' : 'danger'), 'icon'=>($netProfit >= 0 ? 'fa-chart-line' : 'fa-arrow-down')],
            ['title'=>'عدد طلبات المنيو', 'value'=>$ordersRow['c'] . ' طلب', 'color'=>'secondary', 'icon'=>'fa-receipt'],
            ['title'=>'عدد جلسات اللعب', 'value'=>($gamingRow['c'] + $billiardRow['c']) . ' جلسة', 'color'=>'secondary', 'icon'=>'fa-stopwatch']
        ];
        $columns = [];
        break;

    case 'orders':
        $activeFilters = ['date'];
        $columns = [
            'created_at' => 'الوقت والتاريخ',
            'order_number' => 'رقم الطلب',
            'order_type_lbl' => 'النوع',
            'customer_name' => 'العميل',
            'items_summary' => 'الأصناف المطلوبة',
            'total_amount_lbl' => 'الإجمالي',
            'paid_amount_lbl' => 'المدفوع',
            'payment_status_lbl' => 'حالة الدفع',
            'cashier' => 'الكاشير'
        ];

        $w = "o.status != 'cancelled'"; $p = [];
        if ($dateFrom && $dateTo) { $w .= " AND DATE(o.created_at) BETWEEN ? AND ?"; $p[] = $dateFrom; $p[] = $dateTo; }

        $rawOrders = getRows("
            SELECT o.*, u.full_name as cashier_name,
                   (SELECT GROUP_CONCAT(CONCAT(oi.product_name, ' (x', oi.quantity, ')') SEPARATOR '، ') FROM order_items oi WHERE oi.order_id = o.id) as items_summary
            FROM orders o
            LEFT JOIN users u ON u.id = o.created_by
            WHERE {$w}
            ORDER BY o.id DESC
        ", $p);

        $totOrders = 0; $totPaid = 0;
        $typeNamesMap = [
            'takeaway' => '<span class="badge badge-primary">تيك أواي</span>',
            'dine_in' => '<span class="badge badge-success">صالة / طاولات</span>',
            'gaming_ps' => '<span class="badge badge-info">بلايستيشن</span>',
            'gaming_billiard' => '<span class="badge badge-secondary">بلياردو</span>'
        ];

        foreach ($rawOrders as $ord) {
            $totOrders += $ord['total_amount'];
            $totPaid += $ord['paid_amount'];
            $ord['order_type_lbl'] = $typeNamesMap[$ord['order_type']] ?? $ord['order_type'];
            $ord['total_amount_lbl'] = formatCurrency($ord['total_amount']);
            $ord['paid_amount_lbl'] = formatCurrency($ord['paid_amount']);
            $ord['payment_status_lbl'] = ($ord['payment_status'] === 'paid') ? '<span class="badge badge-success">خالص</span>' : '<span class="badge badge-warning">معلق/آجل</span>';
            $ord['cashier'] = htmlspecialchars($ord['cashier_name'] ?? 'النظام');
            $data[] = $ord;
        }

        $summaryCards = [
            ['title'=>'إجمالي مبيعات الطلبات', 'value'=>formatCurrency($totOrders), 'color'=>'primary', 'icon'=>'fa-coffee'],
            ['title'=>'المحصل نقداً / إلكتروني', 'value'=>formatCurrency($totPaid), 'color'=>'success', 'icon'=>'fa-money-bill'],
            ['title'=>'المتبقي / معلق', 'value'=>formatCurrency($totOrders - $totPaid), 'color'=>'danger', 'icon'=>'fa-clock'],
            ['title'=>'عدد الطلبات', 'value'=>count($data) . ' طلب', 'color'=>'secondary', 'icon'=>'fa-receipt']
        ];
        break;

    case 'gaming':
        $activeFilters = ['date'];
        $columns = [
            'started_at' => 'وقت البداية',
            'device_name' => 'الجهاز',
            'device_type' => 'النوع',
            'play_mode_lbl' => 'نظام اللعب',
            'billable_minutes' => 'مدة اللعب (دقيقة)',
            'gaming_charge_lbl' => 'حساب اللعب',
            'cafe_orders_lbl' => 'طلبات الكافيه',
            'total_amount_lbl' => 'إجمالي الحساب',
            'cashier' => 'الكاشير'
        ];

        $w = "gs.status = 'completed'"; $p = [];
        if ($dateFrom && $dateTo) { $w .= " AND DATE(gs.started_at) BETWEEN ? AND ?"; $p[] = $dateFrom; $p[] = $dateTo; }

        $rawGaming = getRows("
            SELECT gs.*, d.display_name as device_name, d.device_type, u.full_name as cashier_name
            FROM gaming_sessions gs
            JOIN gaming_devices d ON d.id = gs.device_id
            LEFT JOIN users u ON u.id = gs.closed_by
            WHERE {$w}
            ORDER BY gs.id DESC
        ", $p);

        $totGamingCharge = 0; $totCafeCharge = 0; $totNetGaming = 0;
        foreach ($rawGaming as $g) {
            $totGamingCharge += $g['gaming_charge'];
            $totCafeCharge += $g['cafe_orders_total'];
            $totNetGaming += $g['total_amount'];

            $g['play_mode_lbl'] = ($g['session_type'] === 'multi') ? '<span class="badge badge-warning">مالتي (Multi)</span>' : '<span class="badge badge-primary">سنجل (Single)</span>';
            $g['gaming_charge_lbl'] = formatCurrency($g['gaming_charge']);
            $g['cafe_orders_lbl'] = formatCurrency($g['cafe_orders_total']);
            $g['total_amount_lbl'] = '<strong style="color:#059669;">' . formatCurrency($g['total_amount']) . '</strong>';
            $g['cashier'] = htmlspecialchars($g['cashier_name'] ?? 'النظام');
            $data[] = $g;
        }

        $summaryCards = [
            ['title'=>'إيرادات وقت البلايستيشن', 'value'=>formatCurrency($totGamingCharge), 'color'=>'info', 'icon'=>'fa-gamepad'],
            ['title'=>'طلبات كافيه مصاحبة', 'value'=>formatCurrency($totCafeCharge), 'color'=>'primary', 'icon'=>'fa-coffee'],
            ['title'=>'إجمالي إيراد جلسات PS', 'value'=>formatCurrency($totNetGaming), 'color'=>'success', 'icon'=>'fa-coins'],
            ['title'=>'عدد الجلسات المنتهية', 'value'=>count($data) . ' جلسة', 'color'=>'secondary', 'icon'=>'fa-check-circle']
        ];
        break;

    case 'billiards':
        $activeFilters = ['date'];
        $columns = [
            'started_at' => 'وقت البداية',
            'table_name' => 'الترابيزة',
            'billing_mode_lbl' => 'نظام الحساب',
            'details' => 'المدة / عدد الأجياك',
            'gaming_charge_lbl' => 'حساب البلياردو',
            'cafe_orders_lbl' => 'طلبات الكافيه',
            'total_amount_lbl' => 'إجمالي الحساب',
            'cashier' => 'الكاشير'
        ];

        $w = "bs.status = 'completed'"; $p = [];
        if ($dateFrom && $dateTo) { $w .= " AND DATE(bs.started_at) BETWEEN ? AND ?"; $p[] = $dateFrom; $p[] = $dateTo; }

        $rawBil = getRows("
            SELECT bs.*, t.display_name as table_name, u.full_name as cashier_name
            FROM billiard_sessions bs
            JOIN billiard_tables t ON t.id = bs.table_id
            LEFT JOIN users u ON u.id = bs.closed_by
            WHERE {$w}
            ORDER BY bs.id DESC
        ", $p);

        $totBilCharge = 0; $totBilCafe = 0; $totBilNet = 0;
        foreach ($rawBil as $b) {
            $totBilCharge += $b['gaming_charge'];
            $totBilCafe += $b['cafe_orders_total'];
            $totBilNet += $b['total_amount'];

            $b['billing_mode_lbl'] = ($b['billing_mode'] === 'per_game') ? '<span class="badge badge-warning">بالجيم</span>' : '<span class="badge badge-info">بالساعة والوقت</span>';
            $b['details'] = ($b['billing_mode'] === 'per_game') ? ($b['game_count'] . ' جيم') : ($b['billable_minutes'] . ' دقيقة');
            $b['gaming_charge_lbl'] = formatCurrency($b['gaming_charge']);
            $b['cafe_orders_lbl'] = formatCurrency($b['cafe_orders_total']);
            $b['total_amount_lbl'] = '<strong style="color:#059669;">' . formatCurrency($b['total_amount']) . '</strong>';
            $b['cashier'] = htmlspecialchars($b['cashier_name'] ?? 'النظام');
            $data[] = $b;
        }

        $summaryCards = [
            ['title'=>'إيرادات لعب البلياردو', 'value'=>formatCurrency($totBilCharge), 'color'=>'success', 'icon'=>'fa-circle'],
            ['title'=>'طلبات كافيه مصاحبة', 'value'=>formatCurrency($totBilCafe), 'color'=>'primary', 'icon'=>'fa-coffee'],
            ['title'=>'إجمالي إيراد البلياردو', 'value'=>formatCurrency($totBilNet), 'color'=>'info', 'icon'=>'fa-coins'],
            ['title'=>'عدد الجلسات المنتهية', 'value'=>count($data) . ' جلسة', 'color'=>'secondary', 'icon'=>'fa-check-circle']
        ];
        break;

    case 'shifts':
        $activeFilters = ['date'];
        $columns = [
            'shift_number' => 'رقم الشيفت',
            'cashier' => 'الكاشير المسؤول',
            'opened_at' => 'وقت الفتح',
            'closed_at' => 'وقت الإغلاق',
            'opening_balance_lbl' => 'رصيد البداية',
            'expected_cash_lbl' => 'المتوقع بالدرج',
            'actual_cash_lbl' => 'الفعلي المعدود',
            'variance_lbl' => 'العجز / الزيادة',
            'status_lbl' => 'الحالة'
        ];

        $w = "1=1"; $p = [];
        if ($dateFrom && $dateTo) { $w .= " AND DATE(s.opened_at) BETWEEN ? AND ?"; $p[] = $dateFrom; $p[] = $dateTo; }

        $rawShifts = getRows("
            SELECT s.*, u.full_name as cashier_name
            FROM shifts s
            JOIN users u ON u.id = s.user_id
            WHERE {$w}
            ORDER BY s.id DESC
        ", $p);

        $totShiftsCash = 0; $totVariance = 0;
        foreach ($rawShifts as $s) {
            $totShiftsCash += floatval($s['actual_cash'] ?? $s['expected_cash'] ?? 0);
            $var = floatval($s['cash_variance'] ?? 0);
            $totVariance += $var;

            $s['cashier'] = htmlspecialchars($s['cashier_name']);
            $s['opening_balance_lbl'] = formatCurrency($s['opening_balance']);
            $s['expected_cash_lbl'] = formatCurrency($s['expected_cash'] ?? 0);
            $s['actual_cash_lbl'] = formatCurrency($s['actual_cash'] ?? 0);
            
            if ($s['status'] === 'open') {
                $s['variance_lbl'] = '<span class="badge badge-info">قيد التشغيل</span>';
                $s['status_lbl'] = '<span class="badge badge-warning">مفتوح الآن</span>';
            } else {
                if ($var < 0) {
                    $s['variance_lbl'] = '<span class="text-danger" style="font-weight:700;">عجز: ' . formatCurrency(abs($var)) . '</span>';
                } elseif ($var > 0) {
                    $s['variance_lbl'] = '<span class="text-success" style="font-weight:700;">زيادة: ' . formatCurrency($var) . '</span>';
                } else {
                    $s['variance_lbl'] = '<span class="text-success">متزن تماماً (0)</span>';
                }
                $s['status_lbl'] = '<span class="badge badge-success">مغلق ومحاسب</span>';
            }
            $data[] = $s;
        }

        $summaryCards = [
            ['title'=>'إجمالي النقدية المحصلة بالشيفتات', 'value'=>formatCurrency($totShiftsCash), 'color'=>'success', 'icon'=>'fa-vault'],
            ['title'=>'صافي فروق الخزينة (عجز/زيادة)', 'value'=>formatCurrency($totVariance), 'color'=>($totVariance >= 0 ? 'success' : 'danger'), 'icon'=>($totVariance >= 0 ? 'fa-plus' : 'fa-minus')],
            ['title'=>'عدد الشيفتات المسجلة', 'value'=>count($data) . ' شيفت', 'color'=>'primary', 'icon'=>'fa-history']
        ];
        break;

    case 'profit':
        // Café P&L: real income streams (paid cafe orders + session time charges) minus operating expenses
        $activeFilters = ['date'];
        $columns = [
            'date' => 'التاريخ',
            'cafe_lbl' => 'دخل الكافيه والمنيو',
            'ps_lbl' => 'دخل البلايستيشن',
            'bil_lbl' => 'دخل البلياردو',
            'income_lbl' => 'إجمالي الدخل',
            'expenses_lbl' => 'المصروفات',
            'net_lbl' => 'صافي اليوم'
        ];

        $dp = [];
        $dateOrd = ""; $dateGam = ""; $dateBil = ""; $dateExp = "";
        if ($dateFrom && $dateTo) {
            $dateOrd = "AND DATE(o.created_at) BETWEEN ? AND ?";
            $dateGam = "AND DATE(gs.started_at) BETWEEN ? AND ?";
            $dateBil = "AND DATE(bs.started_at) BETWEEN ? AND ?";
            $dateExp = "AND date BETWEEN ? AND ?";
            $dp = [$dateFrom, $dateTo];
        }

        $cafeByDay = getRows("SELECT DATE(o.created_at) as d, COALESCE(SUM(o.paid_amount), 0) as v FROM orders o WHERE o.status != 'cancelled' {$dateOrd} GROUP BY d", $dp);
        $psByDay = getRows("SELECT DATE(gs.started_at) as d, COALESCE(SUM(gs.gaming_charge), 0) as v FROM gaming_sessions gs WHERE gs.status = 'completed' {$dateGam} GROUP BY d", $dp);
        $bilByDay = getRows("SELECT DATE(bs.started_at) as d, COALESCE(SUM(bs.gaming_charge), 0) as v FROM billiard_sessions bs WHERE bs.status = 'completed' {$dateBil} GROUP BY d", $dp);
        $expByDay = getRows("SELECT date as d, COALESCE(SUM(amount), 0) as v FROM expenses WHERE 1=1 {$dateExp} GROUP BY date", $dp);

        $byDay = [];
        foreach ($cafeByDay as $r) { $byDay[$r['d']]['cafe'] = floatval($r['v']); }
        foreach ($psByDay as $r) { $byDay[$r['d']]['ps'] = floatval($r['v']); }
        foreach ($bilByDay as $r) { $byDay[$r['d']]['bil'] = floatval($r['v']); }
        foreach ($expByDay as $r) { $byDay[$r['d']]['exp'] = floatval($r['v']); }
        krsort($byDay);

        $totCafe = 0; $totPs = 0; $totBil = 0; $totExp = 0;
        foreach ($byDay as $d => $v) {
            $cafe = $v['cafe'] ?? 0; $ps = $v['ps'] ?? 0; $bil = $v['bil'] ?? 0; $exp = $v['exp'] ?? 0;
            $income = $cafe + $ps + $bil;
            $net = $income - $exp;
            $totCafe += $cafe; $totPs += $ps; $totBil += $bil; $totExp += $exp;
            $data[] = [
                'date' => $d,
                'cafe_lbl' => formatCurrency($cafe),
                'ps_lbl' => formatCurrency($ps),
                'bil_lbl' => formatCurrency($bil),
                'income_lbl' => '<strong>' . formatCurrency($income) . '</strong>',
                'expenses_lbl' => formatCurrency($exp),
                'net_lbl' => '<strong style="color:' . ($net >= 0 ? '#059669' : '#dc2626') . ';">' . formatCurrency($net) . '</strong>'
            ];
        }

        $totalIncome = $totCafe + $totPs + $totBil;
        $netProfitReal = $totalIncome - $totExp;
        $netMargin = ($totalIncome > 0) ? ($netProfitReal / $totalIncome) * 100 : 0;

        $summaryCards = [
            ['title'=>'☕ دخل الكافيه والمنيو (محصل)', 'value'=>formatCurrency($totCafe), 'color'=>'primary', 'icon'=>'fa-coffee'],
            ['title'=>'🎮 دخل البلايستيشن', 'value'=>formatCurrency($totPs), 'color'=>'info', 'icon'=>'fa-gamepad'],
            ['title'=>'🎱 دخل البلياردو', 'value'=>formatCurrency($totBil), 'color'=>'success', 'icon'=>'fa-circle'],
            ['title'=>'💰 إجمالي الدخل', 'value'=>formatCurrency($totalIncome), 'color'=>'success', 'icon'=>'fa-money-bill-wave'],
            ['title'=>'💸 المصروفات التشغيلية', 'value'=>formatCurrency($totExp), 'color'=>'danger', 'icon'=>'fa-file-invoice-dollar'],
            ['title'=>'💎 صافي الربح (' . number_format($netMargin, 1) . '%)', 'value'=>formatCurrency($netProfitReal), 'color'=>($netProfitReal >= 0 ? 'success' : 'danger'), 'icon'=>'fa-chart-line']
        ];
        break;

    case 'stock':
        $activeFilters = [];
        $columns = [
            'name' => 'الصنف',
            'unit_info' => 'الوحدات',
            'stock_lbl' => 'الرصيد (وحدة المخزون)',
            'min_lbl' => 'حد التنبيه',
            'status_lbl' => 'الحالة',
        ];

        $stockRows = getRows("
            SELECT p.id, p.code, p.name, p.unit, p.purchase_unit, p.units_per_purchase,
                   p.stock_quantity, p.min_stock_level, p.track_stock, p.is_direct_sale,
                   c.name AS category_name
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            ORDER BY p.track_stock DESC, p.stock_quantity ASC, p.name ASC
        ");

        $nTracked = 0; $nLow = 0; $nOut = 0;
        foreach ($stockRows as $r) {
            $qty = (int)$r['stock_quantity'];
            $min = (int)$r['min_stock_level'];
            $r['unit_info'] = htmlspecialchars($r['unit']) . ($r['purchase_unit'] ? ' ← ' . htmlspecialchars($r['purchase_unit']) . ' (×' . rtrim(rtrim(number_format((float)$r['units_per_purchase'], 4), '0'), '.') . ')' : '');
            if ((int)$r['track_stock'] !== 1) {
                $r['stock_lbl'] = '<span style="color:#94a3b8;">—</span>';
            } else {
                $r['stock_lbl'] = '<strong>' . $qty . '</strong>';
            }
            $r['min_lbl'] = $min;
            if ((int)$r['track_stock'] !== 1) {
                $r['status_lbl'] = '<span class="badge badge-secondary">التتبع متوقف</span>';
            } elseif ($qty <= 0) {
                $nOut++;
                $r['status_lbl'] = '<span class="badge badge-danger">🔴 نفذ</span>';
            } elseif ($qty <= $min) {
                $nLow++;
                $r['status_lbl'] = '<span class="badge badge-warning">⚠️ منخفض</span>';
            } else {
                $r['status_lbl'] = '<span class="badge badge-success">متاح</span>';
            }
            if ((int)$r['track_stock'] === 1) {
                $nTracked++;
            }
            $r['name'] = htmlspecialchars($r['name']) . '<br><small style="color:#6b7280;">' . htmlspecialchars($r['code']) . '</small>';
            $data[] = $r;
        }

        $summaryCards = [
            ['title'=>'أصناف متتبعة', 'value'=>$nTracked . ' صنف', 'color'=>'primary', 'icon'=>'fa-boxes'],
            ['title'=>'وصلت حد التنبيه', 'value'=>$nLow . ' صنف', 'color'=>'warning', 'icon'=>'fa-exclamation-triangle'],
            ['title'=>'نفذت من المخزون', 'value'=>$nOut . ' صنف', 'color'=>'danger', 'icon'=>'fa-times-circle'],
        ];
        break;

    case 'purchases':
        $activeFilters = ['date'];
        $columns = [
            'date' => 'التاريخ',
            'invoice_number' => 'رقم الفاتورة',
            'supplier_lbl' => 'المورد',
            'items_lbl' => 'الأصناف',
            'total_lbl' => 'الإجمالي',
            'paid_lbl' => 'المدفوع',
            'remaining_lbl' => 'الباقي',
            'status_lbl' => 'الحالة',
        ];

        $w = "i.type = 'purchase'"; $p = [];
        if ($dateFrom && $dateTo) { $w .= " AND i.date BETWEEN ? AND ?"; $p[] = $dateFrom; $p[] = $dateTo; }

        $rawPurch = getRows("
            SELECT i.*, COALESCE(s.name, i.customer_name, '-') AS supplier_name
            FROM invoices i
            LEFT JOIN suppliers s ON s.id = i.supplier_id
            WHERE {$w}
            ORDER BY i.date DESC, i.id DESC
        ", $p);

        $totBuy = 0; $totPaid = 0; $totRem = 0; $nDraft = 0;
        foreach ($rawPurch as $r) {
            $totBuy += (float)$r['total_amount'];
            $totPaid += (float)$r['paid_amount'];
            $totRem += (float)$r['remaining_amount'];
            $r['supplier_lbl'] = htmlspecialchars($r['supplier_name']);
            $r['items_lbl'] = '<a href="#" onclick="openReceiptModal(\'../invoices/print_purchase.php?id=' . (int)$r['id'] . '\'); return false;">عرض الأصناف 🖨️</a>';
            $r['total_lbl'] = formatCurrency($r['total_amount']);
            $r['paid_lbl'] = formatCurrency($r['paid_amount']);
            $r['remaining_lbl'] = formatCurrency($r['remaining_amount']);
            $st = $r['status'] ?? 'approved';
            if ($st === 'draft') {
                $nDraft++;
                $r['status_lbl'] = '<span class="badge badge-warning">📝 مسودة</span>';
            } elseif ($st === 'cancelled') {
                $r['status_lbl'] = '<span class="badge badge-danger">❌ ملغاة</span>';
            } else {
                $r['status_lbl'] = '<span class="badge badge-success">✅ معتمدة</span>';
            }
            $data[] = $r;
        }

        // Quick supplies without invoices (valued at recorded stock-unit cost)
        $wsup = "m.movement_type = 'supply'";
        $psup = [];
        if ($dateFrom && $dateTo) { $wsup .= " AND DATE(m.created_at) BETWEEN ? AND ?"; $psup[] = $dateFrom; $psup[] = $dateTo; }
        $supRows = getRows("
            SELECT m.*, p.name AS product_name, p.code AS product_code, COALESCE(s.name, '-') AS supplier_name
            FROM product_stock_movements m
            LEFT JOIN products p ON p.id = m.product_id
            LEFT JOIN suppliers s ON s.id = m.supplier_id
            WHERE {$wsup}
            ORDER BY m.id DESC
        ", $psup);

        $totSupply = 0; $nSupply = 0;
        foreach ($supRows as $s) {
            $val = (int)$s['qty_in'] * (float)($s['unit_cost'] ?: 0);
            $totSupply += $val;
            $nSupply++;
            $data[] = [
                'date' => date('Y-m-d', strtotime($s['created_at'])),
                'invoice_number' => 'توريد مباشر #' . $s['id'],
                'supplier_lbl' => htmlspecialchars($s['supplier_name']),
                'items_lbl' => htmlspecialchars($s['product_name'] ?? '') . ' × ' . (int)$s['qty_in'] . ' ' . htmlspecialchars($s['unit'] ?? ''),
                'total_lbl' => formatCurrency($val),
                'paid_lbl' => formatCurrency($val),
                'remaining_lbl' => formatCurrency(0),
                'status_lbl' => '<span class="badge badge-primary">🚚 توريد مباشر</span>',
            ];
        }

        $summaryCards = [
            ['title'=>'إجمالي المشتريات بالفترة', 'value'=>formatCurrency($totBuy), 'color'=>'primary', 'icon'=>'fa-cart-arrow-down'],
            ['title'=>'المدفوع للموردين', 'value'=>formatCurrency($totPaid), 'color'=>'success', 'icon'=>'fa-money-bill-wave'],
            ['title'=>'الباقي للموردين', 'value'=>formatCurrency($totRem), 'color'=>'danger', 'icon'=>'fa-hand-holding-usd'],
            ['title'=>'التوريد المباشر (بدون فواتير)', 'value'=>formatCurrency($totSupply), 'color'=>'info', 'icon'=>'fa-truck-loading'],
            ['title'=>'مسودات بانتظار الاعتماد', 'value'=>$nDraft . ' فاتورة', 'color'=>'warning', 'icon'=>'fa-file-signature'],
        ];
        break;

    case 'expenses':
        $activeFilters = ['date', 'expense_category'];
        $columns = [
            'date' => 'التاريخ',
            'category_name' => 'البند والتصنيف',
            'description' => 'بيان وتفاصيل المصروف',
            'amount_lbl' => 'المبلغ',
            'paid_to' => 'المدفوع له',
            'shift_info' => 'الشيفت',
            'user_name' => 'المسؤول'
        ];

        $w = "1=1"; $p = [];
        if ($dateFrom && $dateTo) { $w .= " AND e.date BETWEEN ? AND ?"; $p[] = $dateFrom; $p[] = $dateTo; }
        if ($filterExpenseCat) { $w .= " AND e.category_id = ?"; $p[] = $filterExpenseCat; }

        $rawExpenses = getRows("
            SELECT e.*, COALESCE(c.name, 'غير مصنف') as category_name, u.full_name as user_name, s.shift_number
            FROM expenses e
            LEFT JOIN expense_categories c ON e.category_id = c.id
            LEFT JOIN users u ON e.user_id = u.id
            LEFT JOIN shifts s ON e.shift_id = s.id
            WHERE {$w}
            ORDER BY e.date DESC, e.id DESC
        ", $p);

        $totalExpVal = 0;
        foreach($rawExpenses as $r) {
            $totalExpVal += $r['amount'];
            $r['amount_lbl'] = '<span class="text-danger" style="font-weight:700;">' . formatCurrency($r['amount']) . '</span>';
            $r['shift_info'] = $r['shift_number'] ? ('شيفت #' . $r['shift_number']) : 'خارج الشيفت';
            $data[] = $r;
        }

        $summaryCards = [
            ['title'=>'إجمالي المصروفات بالفترة', 'value'=>formatCurrency($totalExpVal), 'color'=>'danger', 'icon'=>'fa-file-invoice-dollar'],
            ['title'=>'عدد سندات المصروفات', 'value'=>count($data) . ' سند', 'color'=>'primary', 'icon'=>'fa-receipt']
        ];
        break;

    case 'payments':
        // Real collections from payment_records (POS + session closings)
        $activeFilters = ['date', 'pay_method'];
        $columns = [
            'created_at' => 'التاريخ والوقت',
            'payment_number' => 'رقم الإيصال',
            'source_lbl' => 'المصدر',
            'amount_lbl' => 'المبلغ',
            'method_lbl' => 'طريقة الدفع',
            'cashier' => 'الكاشير'
        ];

        $w = "pr.payment_type = 'payment'"; $p = [];
        if ($dateFrom && $dateTo) { $w .= " AND DATE(pr.created_at) BETWEEN ? AND ?"; $p[] = $dateFrom; $p[] = $dateTo; }
        if ($filterPayMethod) { $w .= " AND pr.payment_method = ?"; $p[] = $filterPayMethod; }

        $raw = getRows("SELECT pr.*, u.full_name as cashier_name FROM payment_records pr LEFT JOIN users u ON u.id = pr.created_by WHERE {$w} ORDER BY pr.id DESC", $p);

        $sourceMap = [
            'order' => 'طلب كافيه',
            'gaming_session' => 'جلسة بلايستيشن',
            'billiard_session' => 'جلسة بلياردو',
            'table_session' => 'طاولة صالة'
        ];
        $methodMap = [
            'cash' => '💵 كاش',
            'card' => '💳 فيزا',
            'instapay' => '⚡ إنستاباي',
            'vodafone_cash' => '📱 فودافون كاش',
            'wallet' => '👛 محفظة',
            'other' => 'أخرى'
        ];

        $totalCollected = 0; $cashCollected = 0; $nonCash = 0;
        foreach ($raw as $row) {
            $row['source_lbl'] = ($sourceMap[$row['source_type']] ?? $row['source_type']) . ' #' . $row['source_id'];
            $row['amount_lbl'] = formatCurrency($row['amount']);
            $row['method_lbl'] = $methodMap[$row['payment_method']] ?? $row['payment_method'];
            $row['cashier'] = htmlspecialchars($row['cashier_name'] ?? 'النظام');
            $data[] = $row;
            $totalCollected += $row['amount'];
            if ($row['payment_method'] === 'cash') { $cashCollected += $row['amount']; }
            else { $nonCash += $row['amount']; }
        }

        $summaryCards = [
            ['title'=>'إجمالي المحصل', 'value'=>formatCurrency($totalCollected), 'color'=>'success', 'icon'=>'fa-hand-holding-usd'],
            ['title'=>'💵 كاش', 'value'=>formatCurrency($cashCollected), 'color'=>'primary', 'icon'=>'fa-money-bill-wave'],
            ['title'=>'💳 إلكتروني (فيزا/محافظ)', 'value'=>formatCurrency($nonCash), 'color'=>'info', 'icon'=>'fa-credit-card'],
            ['title'=>'عدد الإيصالات', 'value'=>count($data) . ' إيصال', 'color'=>'secondary', 'icon'=>'fa-receipt']
        ];
        break;

    case 'statement':
        $stmtType = $filterSubType ?: 'customer';
        $activeFilters = ['date', 'sub_type_entity', ($stmtType == 'customer' ? 'entity_customer' : 'entity_supplier')];
        
        if ($filterEntity) {
            // Detailed ledger for selected entity
            $columns = [
                'date' => 'التاريخ', 
                'ref' => 'رقم الفاتورة / الإيصال', 
                'entity_name' => 'الاسم',
                'desc' => 'البيان والتفاصيل', 
                'debit' => 'مدين (له/عليه)', 
                'credit' => 'دائن (له/عليه)',
                'running_bal' => 'الرصيد التراكمي'
            ];
            
            $rawTx = [];
            $entityInfo = getRow("SELECT * FROM " . ($stmtType == 'customer' ? 'customers' : 'suppliers') . " WHERE id = ?", [$filterEntity]);
            $entityName = $entityInfo['name'] ?? '';
            
            // 1. Invoices
            $wInv = $stmtType == 'customer' ? "type='sale' AND customer_id = ?" : "type='purchase' AND supplier_id = ?";
            $pInv = [$filterEntity];
            if ($dateFrom && $dateTo) { $wInv .= " AND date BETWEEN ? AND ?"; $pInv[] = $dateFrom; $pInv[] = $dateTo; }
            
            $invoices = getRows("SELECT date, created_at, invoice_number as ref, (total_amount - discount) as total_amt, paid_amount, remaining_amount, payment_method FROM invoices WHERE {$wInv}", $pInv);
            foreach ($invoices as $inv) {
                if ($stmtType == 'customer') {
                    $rawTx[] = [ 'date' => $inv['date'], 'time' => $inv['created_at'], 'ref' => $inv['ref'], 'entity_name' => $entityName, 'desc' => "فاتورة بيع (" . $inv['payment_method'] . ")", 'debit' => floatval($inv['total_amt']), 'credit' => floatval($inv['paid_amount']) ];
                } else {
                    $rawTx[] = [ 'date' => $inv['date'], 'time' => $inv['created_at'], 'ref' => $inv['ref'], 'entity_name' => $entityName, 'desc' => "فاتورة شراء (" . $inv['payment_method'] . ")", 'debit' => floatval($inv['paid_amount']), 'credit' => floatval($inv['total_amt']) ];
                }
            }
            
            // 2. Payments
            $wPay = "type = ? AND entity_id = ?";
            $pPay = [$stmtType, $filterEntity];
            if ($dateFrom && $dateTo) { $wPay .= " AND payment_date BETWEEN ? AND ?"; $pPay[] = $dateFrom; $pPay[] = $dateTo; }
            
            $payments = getRows("SELECT payment_date as date, created_at, payment_number as ref, amount, payment_method, notes FROM payments WHERE {$wPay}", $pPay);
            foreach ($payments as $pay) {
                $desc = "سداد نقدي (" . $pay['payment_method'] . ")" . ($pay['notes'] ? " - " . $pay['notes'] : "");
                if ($stmtType == 'customer') {
                    $rawTx[] = [ 'date' => $pay['date'], 'time' => $pay['created_at'], 'ref' => $pay['ref'], 'entity_name' => $entityName, 'desc' => $desc, 'debit' => 0, 'credit' => floatval($pay['amount']) ];
                } else {
                    $rawTx[] = [ 'date' => $pay['date'], 'time' => $pay['created_at'], 'ref' => $pay['ref'], 'entity_name' => $entityName, 'desc' => $desc, 'debit' => floatval($pay['amount']), 'credit' => 0 ];
                }
            }
            
            // 3. Returns
            $wRet = "type = ? AND " . ($stmtType == 'customer' ? 'customer_id' : 'supplier_id') . " = ? AND deducted_from_balance > 0";
            $pRet = [$stmtType, $filterEntity];
            if ($dateFrom && $dateTo) { $wRet .= " AND return_date BETWEEN ? AND ?"; $pRet[] = $dateFrom; $pRet[] = $dateTo; }
            
            $returns = getRows("SELECT return_date as date, created_at, return_number as ref, deducted_from_balance FROM returns WHERE {$wRet}", $pRet);
            foreach ($returns as $ret) {
                if ($stmtType == 'customer') {
                    $rawTx[] = [ 'date' => $ret['date'], 'time' => $ret['created_at'], 'ref' => $ret['ref'], 'entity_name' => $entityName, 'desc' => "مرتجع مبيعات (خصم من الحساب)", 'debit' => 0, 'credit' => floatval($ret['deducted_from_balance']) ];
                } else {
                    $rawTx[] = [ 'date' => $ret['date'], 'time' => $ret['created_at'], 'ref' => $ret['ref'], 'entity_name' => $entityName, 'desc' => "مرتجع مشتريات (خصم من الحساب)", 'debit' => floatval($ret['deducted_from_balance']), 'credit' => 0 ];
                }
            }
            
            // Sort by date ASC, time ASC
            usort($rawTx, function($a, $b) {
                if ($a['date'] == $b['date']) return strtotime($a['time']) - strtotime($b['time']);
                return strtotime($a['date']) - strtotime($b['date']);
            });
            
            // Calculate running balance
            $running = 0;
            $totalDebit = 0;
            $totalCredit = 0;
            foreach ($rawTx as $row) {
                $running += ($row['debit'] - $row['credit']);
                $totalDebit += $row['debit'];
                $totalCredit += $row['credit'];
                $row['running_bal'] = formatCurrency(abs($running)) . " " . ($running < 0 ? '(دائن)' : ($running > 0 ? '(مدين)' : ''));
                $data[] = $row;
            }
            
            $curBal = floatval($entityInfo['balance'] ?? 0);
            $summaryCards = [
                ['title' => 'الاسم المحدد', 'value' => $entityName, 'color' => 'primary', 'icon' => 'fa-user'],
                ['title' => 'إجمالي الحركات المدينة', 'value' => formatCurrency($totalDebit), 'color' => 'info', 'icon' => 'fa-arrow-up'],
                ['title' => 'إجمالي الحركات الدائنة', 'value' => formatCurrency($totalCredit), 'color' => 'success', 'icon' => 'fa-arrow-down'],
                ['title' => 'الرصيد الحالي بالسيستم', 'value' => formatCurrency(abs($curBal)) . " " . ($curBal < 0 ? ($stmtType == 'customer' ? 'عليه' : 'له') : ($curBal > 0 ? ($stmtType == 'customer' ? 'له' : 'عليه') : 'متزن')), 'color' => ($curBal != 0 ? 'warning' : 'success'), 'icon' => 'fa-wallet']
            ];

        } else {
            // General invoices overview when no specific entity selected
            $columns = [
                'date' => 'التاريخ', 
                'invoice_number' => 'رقم الفاتورة', 
                'entity_name' => 'اسم العميل / المورد',
                'type_lbl' => 'النوع',
                'total_amount' => 'إجمالي الفاتورة', 
                'paid_amount' => 'المدفوع', 
                'remaining_amount' => 'المتبقي'
            ];
            
            $w = "1=1"; $p = [];
            if ($stmtType == 'customer') $w .= " AND i.type='sale'";
            else $w .= " AND i.type='purchase'";
            
            if ($dateFrom && $dateTo) { $w .= " AND i.date BETWEEN ? AND ?"; $p[] = $dateFrom; $p[] = $dateTo; }
            
            $raw = getRows("SELECT i.*, CASE WHEN i.type='sale' THEN COALESCE(c.name, i.customer_name) ELSE s.name END as entity_name FROM invoices i LEFT JOIN customers c ON i.customer_id=c.id LEFT JOIN suppliers s ON i.supplier_id=s.id WHERE {$w} ORDER BY i.date DESC, i.id DESC", $p);
            
            $totalAmt = 0; $totalPaid = 0; $totalRem = 0;
            foreach ($raw as $row) {
                $row['type_lbl'] = $row['type'] == 'sale' ? 'فاتورة بيع' : 'فاتورة شراء';
                $data[] = $row;
                $totalAmt += $row['total_amount'];
                $totalPaid += $row['paid_amount'];
                $totalRem += $row['remaining_amount'];
            }
            
            $summaryCards = [
                ['title' => 'إجمالي الفواتير', 'value' => formatCurrency($totalAmt), 'color' => 'primary', 'icon' => 'fa-file-invoice-dollar'],
                ['title' => 'إجمالي المدفوع', 'value' => formatCurrency($totalPaid), 'color' => 'success', 'icon' => 'fa-check-circle'],
                ['title' => 'إجمالي المتبقي', 'value' => formatCurrency($totalRem), 'color' => 'danger', 'icon' => 'fa-clock'],
                ['title' => 'عدد الفواتير', 'value' => count($data), 'color' => 'secondary', 'icon' => 'fa-list']
            ];
        }
        break;

    case 'users':
        $activeFilters = ['date', 'user_select'];

        // Find target user details if filtered
        $targetUser = null;
        if ($filterUserId) {
            foreach ($usersList as $u) {
                if ($u['id'] == $filterUserId) {
                    $targetUser = $u;
                    break;
                }
            }
        }
        if (!isAdmin() && !$targetUser) {
            $targetUser = getRow("SELECT id, username, full_name, role FROM users WHERE id = ?", [getCurrentUserId()]);
        }
        $uid = $targetUser ? (int)$targetUser['id'] : 0;

        // Summary KPIs from real cafe tables (orders, sessions, collections, expenses, shifts, activity)
        $ordC = ["o.status != 'cancelled'"]; $ordP = [];
        $sesGC = ["gs.status = 'completed'"]; $sesGP = [];
        $sesBC = ["bs.status = 'completed'"]; $sesBP = [];
        $payC = ["pr.payment_type = 'payment'"]; $payP = [];
        $expC = ["1=1"]; $expP = [];
        $shC = ["1=1"]; $shP = [];
        $actC = ["1=1"]; $actP = [];
        if ($uid) {
            $ordC[] = "o.created_by = ?"; $ordP[] = $uid;
            $sesGC[] = "gs.closed_by = ?"; $sesGP[] = $uid;
            $sesBC[] = "bs.closed_by = ?"; $sesBP[] = $uid;
            $payC[] = "pr.created_by = ?"; $payP[] = $uid;
            $expC[] = "e.user_id = ?"; $expP[] = $uid;
            $shC[] = "s.user_id = ?"; $shP[] = $uid;
            $actC[] = "a.user_id = ?"; $actP[] = $uid;
        }
        if ($dateFrom && $dateTo) {
            $ordC[] = "DATE(o.created_at) BETWEEN ? AND ?"; array_push($ordP, $dateFrom, $dateTo);
            $sesGC[] = "DATE(gs.started_at) BETWEEN ? AND ?"; array_push($sesGP, $dateFrom, $dateTo);
            $sesBC[] = "DATE(bs.started_at) BETWEEN ? AND ?"; array_push($sesBP, $dateFrom, $dateTo);
            $payC[] = "DATE(pr.created_at) BETWEEN ? AND ?"; array_push($payP, $dateFrom, $dateTo);
            $expC[] = "e.date BETWEEN ? AND ?"; array_push($expP, $dateFrom, $dateTo);
            $shC[] = "DATE(s.opened_at) BETWEEN ? AND ?"; array_push($shP, $dateFrom, $dateTo);
            $actC[] = "DATE(a.created_at) BETWEEN ? AND ?"; array_push($actP, $dateFrom, $dateTo);
        }

        $userOrders = getRow("SELECT COUNT(*) as cnt, COALESCE(SUM(o.total_amount), 0) as total, COALESCE(SUM(o.paid_amount), 0) as paid FROM orders o WHERE " . implode(' AND ', $ordC), $ordP) ?: ['cnt' => 0, 'total' => 0, 'paid' => 0];
        $userPs = getRow("SELECT COUNT(*) as cnt, COALESCE(SUM(gs.gaming_charge), 0) as total FROM gaming_sessions gs WHERE " . implode(' AND ', $sesGC), $sesGP) ?: ['cnt' => 0, 'total' => 0];
        $userBil = getRow("SELECT COUNT(*) as cnt, COALESCE(SUM(bs.gaming_charge), 0) as total FROM billiard_sessions bs WHERE " . implode(' AND ', $sesBC), $sesBP) ?: ['cnt' => 0, 'total' => 0];
        $userCollected = getRow("SELECT COUNT(*) as cnt, COALESCE(SUM(pr.amount), 0) as total FROM payment_records pr WHERE " . implode(' AND ', $payC), $payP) ?: ['cnt' => 0, 'total' => 0];
        $userExpenses = getRow("SELECT COUNT(*) as cnt, COALESCE(SUM(e.amount), 0) as total FROM expenses e WHERE " . implode(' AND ', $expC), $expP) ?: ['cnt' => 0, 'total' => 0];
        $userShifts = getRow("SELECT COUNT(*) as cnt FROM shifts s WHERE " . implode(' AND ', $shC), $shP) ?: ['cnt' => 0];
        $userActivity = getRow("SELECT COUNT(*) as cnt FROM activity_log a WHERE " . implode(' AND ', $actC), $actP) ?: ['cnt' => 0];

        $summaryCards = [
            ['title'=>'☕ طلبات الكافيه', 'value'=>formatCurrency($userOrders['total']) . ' (' . $userOrders['cnt'] . ' طلب)', 'color'=>'primary', 'icon'=>'fa-coffee'],
            ['title'=>'💰 محصل الطلبات', 'value'=>formatCurrency($userOrders['paid']), 'color'=>'success', 'icon'=>'fa-money-bill-wave'],
            ['title'=>'🎮 جلسات بلايستيشن مقفولة', 'value'=>formatCurrency($userPs['total']) . ' (' . $userPs['cnt'] . ' جلسة)', 'color'=>'info', 'icon'=>'fa-gamepad'],
            ['title'=>'🎱 جلسات بلياردو مقفولة', 'value'=>formatCurrency($userBil['total']) . ' (' . $userBil['cnt'] . ' جلسة)', 'color'=>'success', 'icon'=>'fa-circle'],
            ['title'=>'🧾 إجمالي المتحصلات', 'value'=>formatCurrency($userCollected['total']) . ' (' . $userCollected['cnt'] . ' إيصال)', 'color'=>'success', 'icon'=>'fa-hand-holding-usd'],
            ['title'=>'💸 مصروفات سجلها', 'value'=>formatCurrency($userExpenses['total']) . ' (' . $userExpenses['cnt'] . ' سند)', 'color'=>'danger', 'icon'=>'fa-file-invoice-dollar'],
            ['title'=>'⏱️ الشيفتات', 'value'=>$userShifts['cnt'] . ' شيفت', 'color'=>'warning', 'icon'=>'fa-history'],
            ['title'=>'سجل النشاط', 'value'=>$userActivity['cnt'] . ' حركة', 'color'=>'secondary', 'icon'=>'fa-clipboard-list']
        ];
        
        switch ($userSubView) {
            case 'orders':
                $columns = [
                    'created_at' => 'الوقت والتاريخ',
                    'order_number' => 'رقم الطلب',
                    'order_type_lbl' => 'النوع',
                    'customer_name' => 'العميل',
                    'total_amount' => 'الإجمالي',
                    'paid_amount' => 'المدفوع',
                    'payment_status_lbl' => 'الحالة',
                    'actions' => 'البون'
                ];
                $oc2 = ["o.status != 'cancelled'"]; $op2 = [];
                if ($uid) { $oc2[] = "o.created_by = ?"; $op2[] = $uid; }
                if ($dateFrom && $dateTo) { $oc2[] = "DATE(o.created_at) BETWEEN ? AND ?"; array_push($op2, $dateFrom, $dateTo); }
                $typeBadges = [
                    'takeaway' => '<span class="badge badge-primary">تيك أواي</span>',
                    'dine_in' => '<span class="badge badge-success">صالة / طاولات</span>',
                    'gaming_ps' => '<span class="badge badge-info">بلايستيشن</span>',
                    'gaming_billiard' => '<span class="badge badge-secondary">بلياردو</span>'
                ];
                $raw = getRows("SELECT o.* FROM orders o WHERE " . implode(' AND ', $oc2) . " ORDER BY o.id DESC", $op2);
                foreach ($raw as $r) {
                    $r['order_type_lbl'] = $typeBadges[$r['order_type']] ?? $r['order_type'];
                    $r['payment_status_lbl'] = ($r['payment_status'] === 'paid') ? '<span class="badge badge-success">خالص</span>' : '<span class="badge badge-warning">معلق/آجل</span>';
                    $r['actions'] = '<a href="../pos/receipt.php?id=' . $r['id'] . '" target="_blank" class="btn-submit" style="padding:4px 10px; font-size:12px; text-decoration:none;"><i class="fas fa-print"></i> البون</a>';
                    $data[] = $r;
                }
                break;

            case 'sessions':
                $columns = [
                    'started_at' => 'وقت البداية',
                    'place' => 'الجهاز / الطاولة',
                    'mode_lbl' => 'النظام',
                    'duration_lbl' => 'المدة / الأجيام',
                    'charge_lbl' => 'حساب الوقت',
                    'cafe_lbl' => 'طلبات كافيه',
                    'total_lbl' => 'الإجمالي',
                    'actions' => 'البون'
                ];
                $gc2 = ["gs.status = 'completed'"]; $gp2 = [];
                $bc2 = ["bs.status = 'completed'"]; $bp2 = [];
                if ($uid) { $gc2[] = "gs.closed_by = ?"; $gp2[] = $uid; $bc2[] = "bs.closed_by = ?"; $bp2[] = $uid; }
                if ($dateFrom && $dateTo) {
                    $gc2[] = "DATE(gs.started_at) BETWEEN ? AND ?"; array_push($gp2, $dateFrom, $dateTo);
                    $bc2[] = "DATE(bs.started_at) BETWEEN ? AND ?"; array_push($bp2, $dateFrom, $dateTo);
                }
                $rawGs = getRows("SELECT gs.id, gs.started_at, gs.session_type as mode, gs.billable_minutes, gs.game_count, gs.gaming_charge, gs.cafe_orders_total, gs.total_amount, d.display_name as place FROM gaming_sessions gs JOIN gaming_devices d ON d.id = gs.device_id WHERE " . implode(' AND ', $gc2) . " ORDER BY gs.id DESC", $gp2);
                foreach ($rawGs as $s) {
                    $data[] = [
                        'started_at' => $s['started_at'],
                        'place' => '🎮 ' . htmlspecialchars($s['place']),
                        'mode_lbl' => ($s['mode'] === 'multi') ? '<span class="badge badge-warning">مالتي</span>' : '<span class="badge badge-primary">سنجل</span>',
                        'duration_lbl' => ($s['billable_minutes'] ?? 0) . ' دقيقة',
                        'charge_lbl' => formatCurrency($s['gaming_charge']),
                        'cafe_lbl' => formatCurrency($s['cafe_orders_total']),
                        'total_lbl' => '<strong>' . formatCurrency($s['total_amount']) . '</strong>',
                        'actions' => '<a href="../gaming/receipt.php?id=' . $s['id'] . '" target="_blank" class="btn-submit" style="padding:4px 10px; font-size:12px; text-decoration:none;"><i class="fas fa-print"></i> البون</a>'
                    ];
                }
                $rawBs = getRows("SELECT bs.id, bs.started_at, bs.billing_mode as mode, bs.billable_minutes, bs.game_count, bs.gaming_charge, bs.cafe_orders_total, bs.total_amount, t.display_name as place FROM billiard_sessions bs JOIN billiard_tables t ON t.id = bs.table_id WHERE " . implode(' AND ', $bc2) . " ORDER BY bs.id DESC", $bp2);
                foreach ($rawBs as $s) {
                    $data[] = [
                        'started_at' => $s['started_at'],
                        'place' => '🎱 ' . htmlspecialchars($s['place']),
                        'mode_lbl' => ($s['mode'] === 'per_game') ? '<span class="badge badge-warning">بالجيم</span>' : '<span class="badge badge-info">بالساعة</span>',
                        'duration_lbl' => ($s['mode'] === 'per_game') ? ($s['game_count'] . ' جيم') : (($s['billable_minutes'] ?? 0) . ' دقيقة'),
                        'charge_lbl' => formatCurrency($s['gaming_charge']),
                        'cafe_lbl' => formatCurrency($s['cafe_orders_total']),
                        'total_lbl' => '<strong>' . formatCurrency($s['total_amount']) . '</strong>',
                        'actions' => '<a href="../billiards/receipt.php?id=' . $s['id'] . '" target="_blank" class="btn-submit" style="padding:4px 10px; font-size:12px; text-decoration:none;"><i class="fas fa-print"></i> البون</a>'
                    ];
                }
                break;

            case 'collections':
                $columns = [
                    'created_at' => 'التاريخ والوقت',
                    'payment_number' => 'رقم الإيصال',
                    'source_lbl' => 'المصدر',
                    'amount' => 'المبلغ',
                    'method_lbl' => 'الطريقة'
                ];
                $pc2 = ["pr.payment_type = 'payment'"]; $pp2 = [];
                if ($uid) { $pc2[] = "pr.created_by = ?"; $pp2[] = $uid; }
                if ($dateFrom && $dateTo) { $pc2[] = "DATE(pr.created_at) BETWEEN ? AND ?"; array_push($pp2, $dateFrom, $dateTo); }
                $srcMap = ['order' => 'طلب كافيه', 'gaming_session' => 'جلسة بلايستيشن', 'billiard_session' => 'جلسة بلياردو', 'table_session' => 'طاولة صالة'];
                $mMap = ['cash' => '💵 كاش', 'card' => '💳 فيزا', 'instapay' => '⚡ إنستاباي', 'vodafone_cash' => '📱 فودافون كاش', 'wallet' => '👛 محفظة', 'other' => 'أخرى'];
                $raw = getRows("SELECT pr.* FROM payment_records pr WHERE " . implode(' AND ', $pc2) . " ORDER BY pr.id DESC", $pp2);
                foreach ($raw as $r) {
                    $r['source_lbl'] = ($srcMap[$r['source_type']] ?? $r['source_type']) . ' #' . $r['source_id'];
                    $r['method_lbl'] = $mMap[$r['payment_method']] ?? $r['payment_method'];
                    $data[] = $r;
                }
                break;
                
            case 'activity':
                $columns = [
                    'created_at' => 'التاريخ والوقت',
                    'user_name' => 'المستخدم',
                    'action' => 'العملية / الإجراء',
                    'description' => 'التفاصيل والبيان',
                    'ip_address' => 'عنوان IP'
                ];
                $ac2 = ["1=1"]; $ap2 = [];
                if ($uid) { $ac2[] = "a.user_id = ?"; $ap2[] = $uid; }
                if ($dateFrom && $dateTo) { $ac2[] = "DATE(a.created_at) BETWEEN ? AND ?"; array_push($ap2, $dateFrom, $dateTo); }
                $raw = getRows("SELECT a.*, u.full_name as user_name FROM activity_log a LEFT JOIN users u ON u.id = a.user_id WHERE " . implode(' AND ', $ac2) . " ORDER BY a.created_at DESC, a.id DESC LIMIT 200", $ap2);
                foreach ($raw as $r) {
                    $r['user_name'] = htmlspecialchars($r['user_name'] ?? 'النظام');
                    $data[] = $r;
                }
                break;
                
            case 'overview':
            default:
                if (!$targetUser) {
                    // Comparative Table of ALL users (real cafe metrics)
                    $columns = [
                        'user_name' => 'اسم الموظف / المستخدم',
                        'role_label' => 'الدور',
                        'orders_count' => 'طلبات الكافيه',
                        'orders_total' => 'إجمالي الطلبات',
                        'sessions_count' => 'الجلسات المقفولة',
                        'collected_total' => 'إجمالي المتحصلات',
                        'actions' => 'التقرير التفصيلي'
                    ];

                    foreach ($usersList as $u) {
                        $uu = (int)$u['id'];
                        $dpU = ($dateFrom && $dateTo) ? [$dateFrom, $dateTo] : [];
                        $dOrdU = ($dateFrom && $dateTo) ? "AND DATE(o.created_at) BETWEEN ? AND ?" : "";
                        $dGsU = ($dateFrom && $dateTo) ? "AND DATE(gs.started_at) BETWEEN ? AND ?" : "";
                        $dBsU = ($dateFrom && $dateTo) ? "AND DATE(bs.started_at) BETWEEN ? AND ?" : "";
                        $dPrU = ($dateFrom && $dateTo) ? "AND DATE(pr.created_at) BETWEEN ? AND ?" : "";

                        $oU = getRow("SELECT COUNT(*) as cnt, COALESCE(SUM(o.total_amount), 0) as total FROM orders o WHERE o.status != 'cancelled' AND o.created_by = ? {$dOrdU}", array_merge([$uu], $dpU)) ?: ['cnt' => 0, 'total' => 0];
                        $gU = getRow("SELECT COUNT(*) as cnt FROM gaming_sessions gs WHERE gs.status = 'completed' AND gs.closed_by = ? {$dGsU}", array_merge([$uu], $dpU)) ?: ['cnt' => 0];
                        $bU = getRow("SELECT COUNT(*) as cnt FROM billiard_sessions bs WHERE bs.status = 'completed' AND bs.closed_by = ? {$dBsU}", array_merge([$uu], $dpU)) ?: ['cnt' => 0];
                        $cU = getRow("SELECT COALESCE(SUM(pr.amount), 0) as total FROM payment_records pr WHERE pr.payment_type = 'payment' AND pr.created_by = ? {$dPrU}", array_merge([$uu], $dpU)) ?: ['total' => 0];

                        $data[] = [
                            'user_name' => '<strong>' . htmlspecialchars($u['full_name']) . '</strong> <small style="color:var(--gray);">(' . htmlspecialchars($u['username']) . ')</small>',
                            'role_label' => $u['role'] === 'admin' ? '<span class="badge" style="background:#4f46e5;">👑 مدير نظام</span>' : '<span class="badge" style="background:#0284c7;">👤 موظف</span>',
                            'orders_count' => $oU['cnt'] . ' طلب',
                            'orders_total' => formatCurrency($oU['total']),
                            'sessions_count' => ($gU['cnt'] + $bU['cnt']) . ' جلسة',
                            'collected_total' => formatCurrency($cU['total']),
                            'actions' => '<a href="?type=users&user_id=' . $u['id'] . '&user_view=overview' . ($dateFrom ? "&date_from=$dateFrom&date_to=$dateTo" : "") . '" class="btn-submit" style="padding:4px 10px; font-size:12px; text-decoration:none;"><i class="fas fa-user-check"></i> عرض تقريره</a>'
                        ];
                    }
                } else {
                    // Selected user's recent operations feed (orders + sessions + collections)
                    $columns = [
                        'date_time' => 'التاريخ والوقت',
                        'type_badge' => 'نوع الحركة',
                        'ref_number' => 'المرجع / الرقم',
                        'details' => 'البيان والتفاصيل',
                        'amount_formatted' => 'المبلغ',
                        'actions' => 'تفاصيل'
                    ];

                    $feed = [];
                    $dpF = ($dateFrom && $dateTo) ? [$dateFrom, $dateTo] : [];
                    $dOrdF = ($dateFrom && $dateTo) ? "AND DATE(o.created_at) BETWEEN ? AND ?" : "";
                    $dGsF = ($dateFrom && $dateTo) ? "AND DATE(gs.started_at) BETWEEN ? AND ?" : "";
                    $dBsF = ($dateFrom && $dateTo) ? "AND DATE(bs.started_at) BETWEEN ? AND ?" : "";
                    $dPrF = ($dateFrom && $dateTo) ? "AND DATE(pr.created_at) BETWEEN ? AND ?" : "";

                    $recentOrders = getRows("SELECT o.id, o.created_at, o.order_number as ref, o.total_amount as amount FROM orders o WHERE o.status != 'cancelled' AND o.created_by = ? {$dOrdF} ORDER BY o.id DESC LIMIT 15", array_merge([$uid], $dpF));
                    foreach ($recentOrders as $s) {
                        $feed[] = [
                            'date_time' => $s['created_at'],
                            'type_badge' => '<span class="badge badge-success">طلب كافيه</span>',
                            'ref_number' => $s['ref'],
                            'details' => 'طلب كافيه #' . $s['ref'],
                            'amount_formatted' => formatCurrency($s['amount']),
                            'actions' => '<a href="../pos/receipt.php?id=' . $s['id'] . '" target="_blank" class="btn-submit" style="padding:4px 10px; font-size:12px; text-decoration:none;"><i class="fas fa-print"></i> معاينة</a>'
                        ];
                    }
                    $recentPs = getRows("SELECT gs.id, gs.started_at as created_at, gs.total_amount as amount, d.display_name FROM gaming_sessions gs JOIN gaming_devices d ON d.id = gs.device_id WHERE gs.status = 'completed' AND gs.closed_by = ? {$dGsF} ORDER BY gs.id DESC LIMIT 15", array_merge([$uid], $dpF));
                    foreach ($recentPs as $s) {
                        $feed[] = [
                            'date_time' => $s['created_at'],
                            'type_badge' => '<span class="badge badge-info">جلسة بلايستيشن</span>',
                            'ref_number' => 'PS-' . $s['id'],
                            'details' => 'جلسة ' . htmlspecialchars($s['display_name']),
                            'amount_formatted' => formatCurrency($s['amount']),
                            'actions' => '<a href="../gaming/receipt.php?id=' . $s['id'] . '" target="_blank" class="btn-submit" style="padding:4px 10px; font-size:12px; text-decoration:none;"><i class="fas fa-print"></i> معاينة</a>'
                        ];
                    }
                    $recentBil = getRows("SELECT bs.id, bs.started_at as created_at, bs.total_amount as amount, t.display_name FROM billiard_sessions bs JOIN billiard_tables t ON t.id = bs.table_id WHERE bs.status = 'completed' AND bs.closed_by = ? {$dBsF} ORDER BY bs.id DESC LIMIT 15", array_merge([$uid], $dpF));
                    foreach ($recentBil as $s) {
                        $feed[] = [
                            'date_time' => $s['created_at'],
                            'type_badge' => '<span class="badge badge-warning">جلسة بلياردو</span>',
                            'ref_number' => 'BL-' . $s['id'],
                            'details' => 'جلسة ' . htmlspecialchars($s['display_name']),
                            'amount_formatted' => formatCurrency($s['amount']),
                            'actions' => '<a href="../billiards/receipt.php?id=' . $s['id'] . '" target="_blank" class="btn-submit" style="padding:4px 10px; font-size:12px; text-decoration:none;"><i class="fas fa-print"></i> معاينة</a>'
                        ];
                    }
                    $recentPay = getRows("SELECT pr.id, pr.created_at, pr.payment_number as ref, pr.amount FROM payment_records pr WHERE pr.payment_type = 'payment' AND pr.created_by = ? {$dPrF} ORDER BY pr.id DESC LIMIT 15", array_merge([$uid], $dpF));
                    foreach ($recentPay as $s) {
                        $feed[] = [
                            'date_time' => $s['created_at'],
                            'type_badge' => '<span class="badge badge-success">تحصيل</span>',
                            'ref_number' => $s['ref'],
                            'details' => 'إيصال تحصيل ' . htmlspecialchars($s['ref']),
                            'amount_formatted' => formatCurrency($s['amount']),
                            'actions' => ''
                        ];
                    }
                    usort($feed, function ($a, $b) { return strtotime($b['date_time']) - strtotime($a['date_time']); });
                    $data = array_slice($feed, 0, 20);
                }
                break;
        }
        break;
}

// Pagination setup
$perPage = 15;
$page = max(1, intval($_GET['page'] ?? 1));
$totalItems = count($data);
$totalPages = max(1, ceil($totalItems / $perPage));
$offset = ($page - 1) * $perPage;

$paginatedData = !empty($data) ? array_slice($data, $offset, $perPage) : [];

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<style>
    /* Global Clean Report Styling */
    :root {
        --primary: #1e3a8a; --secondary: #3b82f6; --success: #10b981; 
        --danger: #ef4444; --warning: #f59e0b; --info: #0ea5e9; --dark: #1f2937; 
        --light: #f8fafc; --gray: #6b7280;
    }
    body { background-color: #f1f5f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    .reports-container { max-width: 96%; margin: 20px auto; }
    
    /* Navigation Tabs */
    .report-tabs {
        display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 20px;
        background: white; padding: 12px; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.04);
    }
    .report-tab {
        padding: 9px 14px; background: #f3f4f6; color: #374151;
        text-decoration: none; border-radius: 7px; font-weight: 600; font-size: 13.5px;
        transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 6px;
    }
    .report-tab:hover { background: #e5e7eb; color: #111827; }
    .report-tab.active { background: var(--primary); color: white; box-shadow: 0 3px 8px rgba(30,58,138,0.25); }

    /* Filters Section & Autocomplete */
    .filters-card {
        background: white; padding: 18px 22px; border-radius: 10px; margin-bottom: 20px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.04); border-right: 4px solid var(--secondary);
    }
    .filter-row { display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; }
    .filter-group { flex: 1; min-width: 180px; position: relative; }
    .filter-group label { display: block; margin-bottom: 6px; color: var(--gray); font-size: 13px; font-weight: 600; }
    .form-control { 
        width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; 
        font-family: inherit; font-size: 13.5px; box-sizing: border-box; background: #fff;
    }
    .form-control:focus { outline: none; border-color: var(--secondary); box-shadow: 0 0 0 3px rgba(59,130,246,0.15); }
    
    /* Autocomplete dropdown styling */
    .autocomplete-wrapper { position: relative; }
    .autocomplete-results {
        position: absolute; top: 100%; right: 0; left: 0; max-height: 200px; overflow-y: auto;
        background: white; border: 1px solid #cbd5e1; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 1000; display: none; margin-top: 4px;
    }
    .autocomplete-item { padding: 9px 12px; cursor: pointer; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
    .autocomplete-item:hover { background: #eff6ff; color: var(--primary); }

    .btn-submit {
        padding: 9px 22px; background: var(--primary); color: white; border: none;
        border-radius: 6px; font-weight: bold; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
    }
    .btn-submit:hover { background: #1e40af; }
    .btn-all {
        padding: 9px 22px; background: #059669; color: white; border: none;
        border-radius: 6px; font-weight: bold; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; text-decoration: none;
    }
    .btn-all:hover { background: #047857; color: white; }

    /* Summary Cards Dashboard */
    .dashboard-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 16px; margin-bottom: 20px; }
    .card-box {
        background: white; padding: 18px; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.04);
        display: flex; align-items: center; justify-content: space-between; border-right: 4px solid var(--primary);
    }
    .card-box.card-primary { border-color: var(--primary); }
    .card-box.card-success { border-color: var(--success); }
    .card-box.card-danger { border-color: var(--danger); }
    .card-box.card-warning { border-color: var(--warning); }
    .card-box.card-info { border-color: var(--info); }
    .card-box.card-secondary { border-color: var(--gray); }
    .card-info-title { color: var(--gray); font-size: 12.5px; font-weight: bold; margin-bottom: 4px; }
    .card-info-value { font-size: 20px; font-weight: 800; color: var(--dark); }
    .card-info-icon { font-size: 28px; opacity: 0.25; }

    /* Tables & Action Bar */
    .table-card { background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.04); margin-bottom: 30px; }
    .table-top-bar { padding: 14px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
    .table-top-bar h3 { margin: 0; color: var(--primary); font-size: 16.5px; }
    
    table.data-table { width: 100%; border-collapse: collapse; text-align: right; }
    table.data-table th { background: #f1f5f9; padding: 12px 16px; font-size: 13px; color: #475569; border-bottom: 1px solid #e2e8f0; }
    table.data-table td { padding: 12px 16px; font-size: 13.5px; border-bottom: 1px solid #f1f5f9; color: #334155; }
    table.data-table tbody tr:hover { background: #f8fafc; }
    
    .badge { padding: 4px 8px; border-radius: 4px; font-size: 11.5px; font-weight: bold; color: white; display: inline-block; }
    .badge-success { background: var(--success); }
    .badge-danger { background: var(--danger); }
    .badge-warning { background: var(--warning); color: #000; }
    .text-danger { color: var(--danger); font-weight: bold; }
    .text-success { color: var(--success); font-weight: bold; }

    /* Print styling */
    @media print {
        .noprint, .report-tabs, .filters-card, .header-nav, nav, header, .top-header, .sidebar { display: none !important; }
        body { background: white !important; margin: 0 !important; padding: 0 !important; }
        .reports-container, .reports-container * { visibility: visible !important; }
        .reports-container { position: absolute !important; right: 0 !important; top: 0 !important; width: 100% !important; max-width: 100% !important; margin: 0 !important; padding: 10px !important; }
        .table-card { box-shadow: none !important; border: none !important; }
        table.data-table th, table.data-table td { border: 1px solid #000 !important; color: #000 !important; }
        .print-header { display: block !important; text-align: center; margin-bottom: 15px; border-bottom: 2px solid #000; padding-bottom: 10px; }
    }
    .print-header { display: none; }
</style>

<div class="reports-container report-print">
    
    <!-- Unified Report Tabs -->
    <div class="report-tabs noprint">
        <a href="../../index.php" class="report-tab" style="background: #1e293b; color: white;">
            🏠 الرئيسية
        </a>
        <?php foreach ($typeNames as $key => $label): ?>
            <a href="?type=<?php echo $key; ?>" class="report-tab <?php echo $reportType == $key ? 'active' : ''; ?>">
                <?php echo $label; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Official Printable Header -->
    <!-- Official Printable Header -->
    <div class="print-header">
        <h2 style="margin:0;"><?php echo getSetting('store_name'); ?></h2>
        <p style="margin:3px 0;"><?php echo getSetting('store_address'); ?> | ت: <?php echo getSetting('store_phone'); ?></p>
        <h3 style="margin:5px 0; text-decoration: underline;"><?php echo $typeNames[$reportType]; ?></h3>
        <?php if($reportType === 'users' && !empty($targetUser)): ?>
            <p style="margin:3px 0; font-weight:bold; font-size:15px;">تقرير نشاط المستخدم: <?php echo htmlspecialchars($targetUser['full_name']); ?> (<?php echo $targetUser['role'] === 'admin' ? '👑 مدير' : '👤 موظف'; ?>)</p>
        <?php elseif($reportType === 'users'): ?>
            <p style="margin:3px 0; font-weight:bold; font-size:15px;">تقرير مقارنة وأداء جميع مستخدمي النظام</p>
        <?php endif; ?>
        <?php if(in_array('date', $activeFilters) && ($dateFrom || $dateTo)): ?>
            <p style="margin:0;">عن الفترة من: <?php echo $dateFrom ?: 'البداية'; ?> إلى: <?php echo $dateTo ?: 'اليوم'; ?></p>
        <?php else: ?>
            <p style="margin:0;">تقرير شامل (كافة البيانات بدون تقييد)</p>
        <?php endif; ?>
    </div>

    <!-- Dynamic Filter Engine with Autocomplete -->
    <?php if (!empty($activeFilters)): ?>
    <div class="filters-card noprint">
        <form method="GET" class="filter-row">
            <input type="hidden" name="type" value="<?php echo $reportType; ?>">
            <?php if ($reportType === 'users'): ?>
                <input type="hidden" name="user_view" value="<?php echo htmlspecialchars($userSubView); ?>">
            <?php endif; ?>
            
            <?php if(in_array('user_select', $activeFilters)): ?>
                <div class="filter-group">
                    <label>المستخدم / الموظف</label>
                    <?php if (isAdmin()): ?>
                    <select name="user_id" class="form-control" onchange="this.form.submit()">
                        <option value="">-- جميع المستخدمين (مقارنة شاملة) --</option>
                        <?php foreach ($usersList as $u): ?>
                            <option value="<?php echo $u['id']; ?>" <?php echo ($filterUserId == $u['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($u['full_name']); ?> (<?php echo $u['role'] === 'admin' ? '👑 مدير' : '👤 موظف'; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php else: ?>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($targetUser['full_name'] ?? getCurrentUserName()); ?>" readonly style="background:#f8fafc; font-weight:600;">
                    <input type="hidden" name="user_id" value="<?php echo getCurrentUserId(); ?>">
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if(in_array('sub_type_entity', $activeFilters)): ?>
                <div class="filter-group">
                    <label>نوع الحساب</label>
                    <select name="sub_type" class="form-control" onchange="this.form.submit()">
                        <option value="customer" <?php echo ($filterSubType=='customer' || !$filterSubType)?'selected':''; ?>>عميل</option>
                        <option value="supplier" <?php echo $filterSubType=='supplier'?'selected':''; ?>>مورد</option>
                    </select>
                </div>
            <?php endif; ?>

            <?php if(in_array('date', $activeFilters)): ?>
                <div class="filter-group"><label>من تاريخ</label><input type="date" name="date_from" class="form-control" value="<?php echo $dateFrom; ?>"></div>
                <div class="filter-group"><label>إلى تاريخ</label><input type="date" name="date_to" class="form-control" value="<?php echo $dateTo; ?>"></div>
            <?php endif; ?>

            <?php if(in_array('entity_customer', $activeFilters) || in_array('entity_supplier', $activeFilters)): ?>
                <input type="hidden" name="entity_id" id="filterEntityId" value="<?php echo $filterEntity; ?>">
                <div class="filter-group autocomplete-wrapper">
                    <label>🔍 ابحث بالاسم أو التليفون</label>
                    <input type="text" id="filterEntitySearch" class="form-control" placeholder="اسم أو تليفون..." autocomplete="off">
                    <div id="filterEntityResults" class="autocomplete-results"></div>
                </div>
                <div class="filter-group">
                    <label>📱 التليفون (تلقائي)</label>
                    <input type="text" name="entity_phone" id="filterEntityPhone" class="form-control" value="<?php echo $filterPhone; ?>" readonly style="background:#f8fafc;">
                </div>
            <?php endif; ?>

            <?php if(in_array('pay_method', $activeFilters)): ?>
                <div class="filter-group"><label>طريقة الدفع</label><select name="pay_method" class="form-control" onchange="this.form.submit()"><option value="">الكل</option><option value="cash" <?php echo $filterPayMethod=='cash'?'selected':''; ?>>💵 كاش</option><option value="card" <?php echo $filterPayMethod=='card'?'selected':''; ?>>💳 فيزا</option><option value="instapay" <?php echo $filterPayMethod=='instapay'?'selected':''; ?>>⚡ إنستاباي</option><option value="vodafone_cash" <?php echo $filterPayMethod=='vodafone_cash'?'selected':''; ?>>📱 فودافون كاش</option><option value="wallet" <?php echo $filterPayMethod=='wallet'?'selected':''; ?>>👛 محفظة</option><option value="other" <?php echo $filterPayMethod=='other'?'selected':''; ?>>أخرى</option></select></div>
            <?php endif; ?>

            <?php if(in_array('expense_category', $activeFilters)): ?>
                <div class="filter-group">
                    <label>🏷️ بند المصروف</label>
                    <select name="category_id" class="form-control" onchange="this.form.submit()">
                        <option value="">-- كل بنود المصروفات --</option>
                        <?php foreach ($expenseCategoriesList as $ec): ?>
                            <option value="<?php echo $ec['id']; ?>" <?php echo ($filterExpenseCat == $ec['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ec['icon'] . ' ' . $ec['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="filter-group" style="flex:0 0 auto; display:flex; gap:8px;">
                <button type="submit" class="btn-submit"><i class="fas fa-search"></i> تصفية</button>
                <a href="?type=<?php echo $reportType; ?>&all=1<?php echo ($reportType === 'users' && $filterUserId) ? '&user_id=' . $filterUserId : ''; ?>" class="btn-all" title="إلغاء قيود التواريخ وعرض كل السجلات المسجلة"><i class="fas fa-globe"></i> عرض الكل</a>
                
                <?php if ($reportType == 'statement' && $filterEntity): ?>
                    <?php 
                        $stmtLink = "statement.php?type={$stmtType}&id={$filterEntity}";
                        if ($dateFrom) $stmtLink .= "&date_from={$dateFrom}";
                        if ($dateTo) $stmtLink .= "&date_to={$dateTo}";
                    ?>
                    <a href="<?php echo $stmtLink; ?>" class="btn-submit" style="background:#1e3a8a; text-decoration:none;"><i class="fas fa-print"></i> عرض وطباعة كشف الحساب التفصيلي</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- User Report Sub-views Navigation -->
    <?php if ($reportType === 'users'): ?>
    <?php
        $baseQuery = ['type' => 'users'];
        if ($filterUserId) $baseQuery['user_id'] = $filterUserId;
        if ($dateFrom) $baseQuery['date_from'] = $dateFrom;
        if ($dateTo) $baseQuery['date_to'] = $dateTo;
    ?>
    <div class="user-subtabs noprint" style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:20px; background:white; padding:12px; border-radius:10px; box-shadow:0 2px 6px rgba(0,0,0,0.04); border-top:3px solid var(--secondary);">
        <a href="?<?php echo http_build_query(array_merge($baseQuery, ['user_view' => 'overview'])); ?>" class="report-tab <?php echo $userSubView === 'overview' ? 'active' : ''; ?>">
            <i class="fas fa-chart-pie"></i> ملخص الأداء الشامل
        </a>
        <a href="?<?php echo http_build_query(array_merge($baseQuery, ['user_view' => 'orders'])); ?>" class="report-tab <?php echo $userSubView === 'orders' ? 'active' : ''; ?>">
            <i class="fas fa-coffee"></i> طلبات الكافيه (<?php echo $userOrders['cnt'] ?? 0; ?>)
        </a>
        <a href="?<?php echo http_build_query(array_merge($baseQuery, ['user_view' => 'sessions'])); ?>" class="report-tab <?php echo $userSubView === 'sessions' ? 'active' : ''; ?>">
            <i class="fas fa-gamepad"></i> الجلسات المقفولة (<?php echo ($userPs['cnt'] ?? 0) + ($userBil['cnt'] ?? 0); ?>)
        </a>
        <a href="?<?php echo http_build_query(array_merge($baseQuery, ['user_view' => 'collections'])); ?>" class="report-tab <?php echo $userSubView === 'collections' ? 'active' : ''; ?>">
            <i class="fas fa-receipt"></i> المتحصلات (<?php echo $userCollected['cnt'] ?? 0; ?>)
        </a>
        <a href="?<?php echo http_build_query(array_merge($baseQuery, ['user_view' => 'activity'])); ?>" class="report-tab <?php echo $userSubView === 'activity' ? 'active' : ''; ?>">
            <i class="fas fa-history"></i> سجل النشاط (<?php echo $userActivity['cnt'] ?? 0; ?>)
        </a>
    </div>
    <?php endif; ?>

    <!-- Summary Dashboard Cards -->
    <?php if (!empty($summaryCards)): ?>
    <div class="dashboard-cards">
        <?php foreach($summaryCards as $card): ?>
        <div class="card-box card-<?php echo $card['color'] ?? 'primary'; ?>">
            <div>
                <div class="card-info-title"><?php echo $card['title']; ?></div>
                <div class="card-info-value"><?php echo $card['value']; ?></div>
            </div>
            <div class="card-info-icon text-<?php echo $card['color'] ?? 'primary'; ?>"><i class="fas <?php echo $card['icon'] ?? 'fa-info-circle'; ?>"></i></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Main Unified Table -->
    <?php if (!empty($columns)): ?>
    <div class="table-card">
        <div class="table-top-bar noprint">
            <h3>
                <?php 
                    if ($reportType === 'users') {
                        $viewNames = [
                            'overview' => 'ملخص الأداء والمقارنة',
                            'orders' => 'طلبات الكافيه',
                            'sessions' => 'الجلسات المقفولة',
                            'collections' => 'المتحصلات',
                            'activity' => 'سجل الحركات التفصيلي'
                        ];
                        $uTitle = ($targetUser ? htmlspecialchars($targetUser['full_name']) . ' - ' : '') . ($viewNames[$userSubView] ?? 'تقرير المستخدمين');
                        echo "👤 $uTitle";
                    } else {
                        echo $typeNames[$reportType];
                    }
                ?>
                <small style="color:var(--gray); font-size:13px;">(إجمالي السجلات: <?php echo $totalItems; ?>)</small>
            </h3>
            <div style="display:flex; gap:8px;">
                <button onclick="window.print()" class="btn-submit" style="background:var(--gray);"><i class="fas fa-print"></i> طباعة الصفحة</button>
            </div>
        </div>
        
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <?php foreach($columns as $key => $label): ?>
                            <th><?php echo $label; ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($paginatedData)): ?>
                        <tr><td colspan="<?php echo count($columns); ?>" style="text-align:center; padding: 25px; color: var(--gray);">لا توجد بيانات متاحة حسب خيارات البحث الحالية</td></tr>
                    <?php else: ?>
                        <?php foreach($paginatedData as $row): ?>
                            <tr>
                                <?php foreach($columns as $key => $label): ?>
                                    <td <?php if(in_array($key, ['total_amount','paid_amount','remaining_amount','profit','amount','sale_price','cost_price','debit','credit','sales_total','sales_cash','purchases_total','returns_total'])) echo 'style="font-family:monospace; font-weight:bold; font-size:14px;"'; ?>>
                                        <?php 
                                            if (in_array($key, ['total_amount','paid_amount','remaining_amount','profit','amount','cash_refund','deducted_from_balance','debit','credit','sales_total','sales_cash','purchases_total','returns_total']) && is_numeric($row[$key])) {
                                                echo number_format($row[$key], 2);
                                            } else {
                                                echo $row[$key] ?? ''; 
                                            }
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Bar -->
        <?php if ($totalPages > 1): ?>
        <div style="padding: 15px 20px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;" class="noprint">
            <span style="font-size: 13px; color: var(--gray);">عرض صفحة <?php echo $page; ?> من <?php echo $totalPages; ?></span>
            <div style="display:flex; gap: 8px;">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="report-tab">← السابق</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="report-tab">التالي →</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<!-- Autocomplete & Phone Auto-fill Script -->
<script>
const customers = <?php echo json_encode($customersList); ?>;
const suppliers = <?php echo json_encode($suppliersList); ?>;

// Filter Bar Entity Autocomplete
const filterEntitySearch = document.getElementById('filterEntitySearch');
const filterEntityResults = document.getElementById('filterEntityResults');
const filterEntityId = document.getElementById('filterEntityId');
const filterEntityPhone = document.getElementById('filterEntityPhone');

if (filterEntitySearch) {
    const isSupplier = <?php echo ($filterSubType == 'supplier') ? 'true' : 'false'; ?>;
    const filterList = isSupplier ? suppliers : customers;

    // Set initial text if filterEntity is present
    const currentId = "<?php echo $filterEntity; ?>";
    if (currentId) {
        const found = filterList.find(item => item.id == currentId);
        if (found) {
            filterEntitySearch.value = found.name;
            if (filterEntityPhone) filterEntityPhone.value = found.phone || '';
        }
    }

    filterEntitySearch.addEventListener('input', function() {
        const query = normSearchText(this.value.trim());
        
        if (query.length < 1) {
            filterEntityResults.style.display = 'none';
            filterEntityId.value = '';
            if (filterEntityPhone) filterEntityPhone.value = '';
            return;
        }
        
        const matches = filterList.filter(item => 
            (item.name && normSearchText(item.name).includes(query)) || 
            (item.phone && normSearchText(item.phone).includes(query))
        ).slice(0, 8);
        
        if (matches.length > 0) {
            filterEntityResults.innerHTML = matches.map(item => `
                <div class="autocomplete-item" onclick="selectFilterEntity(${item.id}, '${item.name.replace(/'/g, "\\'")}', '${item.phone || ''}')">
                    <strong>${item.name}</strong> ${item.phone ? ' - 📱 ' + item.phone : ''}
                </div>
            `).join('');
            filterEntityResults.style.display = 'block';
        } else {
            filterEntityResults.innerHTML = '<div class="autocomplete-item" style="color: #94a3b8;">لا يطابق أي نتيجة</div>';
            filterEntityResults.style.display = 'block';
        }
    });
}

function selectFilterEntity(id, name, phone) {
    filterEntitySearch.value = name;
    filterEntityId.value = id;
    if (filterEntityPhone) filterEntityPhone.value = phone || 'غير مدخل';
    filterEntityResults.style.display = 'none';
}

// Close autocomplete dropdowns when clicking outside
document.addEventListener('click', function(e) {
    if (filterEntityResults && filterEntitySearch && !filterEntitySearch.contains(e.target) && !filterEntityResults.contains(e.target)) {
        filterEntityResults.style.display = 'none';
    }
});
</script>

</body>
</html>
