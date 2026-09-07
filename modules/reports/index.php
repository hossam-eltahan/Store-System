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
$filterWarehouse = $_GET['warehouse_id'] ?? '';
$filterExpenseCat = !empty($_GET['category_id']) ? intval($_GET['category_id']) : '';
$userSubView = $_GET['user_view'] ?? 'overview';

$allReportTypes = [
    'daily' => '📊 ملخص يومي/فتري',
    'sales' => '💰 المبيعات',
    'purchases' => '🛒 المشتريات',
    'profit' => '📈 الأرباح وصافي الدخل',
    'expenses' => '💸 المصروفات والتكاليف',
    'returns' => '🔄 المرتجعات',
    'installments' => '📅 الأقساط',
    'payments' => '💵 التحصيلات والمدفوعات',
    'customers' => '👥 حسابات العملاء',
    'suppliers' => '🚚 حسابات الموردين',
    'stock' => '📦 المخزون الجردي',
    'statement' => '🧾 كشف حساب',
    'users' => '👤 تقارير ونشاط المستخدمين'
];

if (isAdmin()) {
    $typeNames = $allReportTypes;
    $reportType = $_GET['type'] ?? 'sales';
} else {
    // Non-admin employee: only allowed types based on explicit permissions
    $typeNames = [];
    if (hasPermission('reports.view')) {
        $typeNames['users'] = '👤 تقارير ونشاط المستخدم';
    }
    if (hasPermission('reports.statement')) {
        $typeNames['statement'] = '🧾 كشف حساب';
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

// Fetch lists for JS Autocomplete and user filtering
$customersList = getRows("SELECT id, name, phone, balance FROM customers ORDER BY name ASC");
$suppliersList = getRows("SELECT id, name, phone, balance FROM suppliers ORDER BY name ASC");
$usersList = getRows("SELECT id, username, full_name, role FROM users ORDER BY (role='admin') DESC, full_name ASC");
$warehousesList = getAllWarehouses();
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
        
        $dateCondInv = ($dateFrom && $dateTo) ? "AND date BETWEEN '$dateFrom' AND '$dateTo'" : "";
        $dateCondRet = ($dateFrom && $dateTo) ? "AND return_date BETWEEN '$dateFrom' AND '$dateTo'" : "";
        $dateCondPay = ($dateFrom && $dateTo) ? "AND payment_date BETWEEN '$dateFrom' AND '$dateTo'" : "";
        $dateCondExp = ($dateFrom && $dateTo) ? "AND expense_date BETWEEN '$dateFrom' AND '$dateTo'" : "";

        $salesData = getRow("SELECT COUNT(*) as c, COALESCE(SUM(total_amount - discount),0) as t, COALESCE(SUM(paid_amount),0) as p FROM invoices WHERE type='sale' {$dateCondInv}");
        $purchasesData = getRow("SELECT COUNT(*) as c, COALESCE(SUM(total_amount - discount),0) as t, COALESCE(SUM(paid_amount),0) as p FROM invoices WHERE type='purchase' {$dateCondInv}");
        $returnsData = getRow("SELECT COUNT(*) as c, COALESCE(SUM(total_amount),0) as t, COALESCE(SUM(cash_refund),0) as r FROM returns WHERE 1=1 {$dateCondRet}");
        $custPay = getRow("SELECT COALESCE(SUM(amount),0) as t FROM payments WHERE type='customer' {$dateCondPay}");
        $suppPay = getRow("SELECT COALESCE(SUM(amount),0) as t FROM payments WHERE type='supplier' {$dateCondPay}");
        $expCashData = getRow("SELECT COALESCE(SUM(amount),0) as t, COUNT(*) as c FROM expenses WHERE payment_method='نقدي' {$dateCondExp}");
        
        $sp = floatval($salesData['p'] ?? 0); 
        $pp = floatval($purchasesData['p'] ?? 0);
        $cp = floatval($custPay['t'] ?? 0); 
        $spp = floatval($suppPay['t'] ?? 0);
        $rr = floatval($returnsData['r'] ?? 0);
        $expCash = floatval($expCashData['t'] ?? 0);

        $cashIn = $sp + $cp; 
        $cashOut = $pp + $spp + $rr + $expCash;
        $netCash = $cashIn - $cashOut;

        $summaryCards = [
            ['title'=>'المبيعات النقدية', 'value'=>formatCurrency($sp), 'color'=>'primary', 'icon'=>'fa-shopping-cart'],
            ['title'=>'تحصيلات العملاء', 'value'=>formatCurrency($cp), 'color'=>'success', 'icon'=>'fa-hand-holding-usd'],
            ['title'=>'إجمالي النقد الداخل', 'value'=>formatCurrency($cashIn), 'color'=>'success', 'icon'=>'fa-arrow-down'],
            ['title'=>'المشتريات النقدية', 'value'=>formatCurrency($pp), 'color'=>'warning', 'icon'=>'fa-shopping-bag'],
            ['title'=>'مدفوعات الموردين', 'value'=>formatCurrency($spp), 'color'=>'danger', 'icon'=>'fa-money-bill-wave'],
            ['title'=>'مرتجعات نقدية مستردة', 'value'=>formatCurrency($rr), 'color'=>'danger', 'icon'=>'fa-undo'],
            ['title'=>'مصروفات تشغيلية (نقداً)', 'value'=>formatCurrency($expCash), 'color'=>'danger', 'icon'=>'fa-file-invoice-dollar'],
            ['title'=>'إجمالي النقد الخارج', 'value'=>formatCurrency($cashOut), 'color'=>'danger', 'icon'=>'fa-arrow-up'],
            ['title'=>'صافي الخزينة بالفترة', 'value'=>formatCurrency($netCash), 'color'=>($netCash>=0?'success':'danger'), 'icon'=>'fa-vault']
        ];
        $columns = [];
        break;

    case 'sales':
        $activeFilters = ['date', 'warehouse', 'entity_customer', 'pay_method'];
        $columns = [
            'date' => 'التاريخ', 
            'invoice_number' => 'رقم الفاتورة', 
            'warehouse_name' => 'المخزن',
            'customer_name' => 'اسم العميل',
            'customer_phone' => 'التليفون',
            'payment_method' => 'طريقة الدفع', 
            'total_amount' => 'إجمالي الفاتورة', 
            'paid_amount' => 'المدفوع', 
            'remaining_amount' => 'المتبقي'
        ];
        
        $w = "i.type='sale'"; 
        $p = [];
        if ($dateFrom && $dateTo) { $w .= " AND i.date BETWEEN ? AND ?"; $p[] = $dateFrom; $p[] = $dateTo; }
        if ($filterWarehouse) { $w .= " AND i.warehouse_id=?"; $p[] = $filterWarehouse; }
        if ($filterEntity) { $w .= " AND i.customer_id=?"; $p[] = $filterEntity; }
        if ($filterPayMethod) { $w .= " AND i.payment_method=?"; $p[] = $filterPayMethod; }
        
        $data = getRows("SELECT i.*, COALESCE(w.name, 'المخزن الرئيسي') as warehouse_name, COALESCE(c.name, i.customer_name) as customer_name, COALESCE(c.phone, i.customer_phone) as customer_phone FROM invoices i LEFT JOIN customers c ON i.customer_id=c.id LEFT JOIN warehouses w ON i.warehouse_id=w.id WHERE {$w} ORDER BY i.date DESC, i.id DESC", $p);
        
        $totalS = 0; $totalP = 0; $totalR = 0;
        foreach($data as $row) { $totalS += $row['total_amount']; $totalP += $row['paid_amount']; $totalR += $row['remaining_amount']; }
        
        $summaryCards = [
            ['title'=>'إجمالي المبيعات', 'value'=>formatCurrency($totalS), 'color'=>'primary', 'icon'=>'fa-chart-line'],
            ['title'=>'المدفوع (كاش/تحويل)', 'value'=>formatCurrency($totalP), 'color'=>'success', 'icon'=>'fa-money-bill'],
            ['title'=>'المتبقي (آجل/ديون)', 'value'=>formatCurrency($totalR), 'color'=>'warning', 'icon'=>'fa-clock'],
            ['title'=>'عدد الفواتير', 'value'=>count($data), 'color'=>'secondary', 'icon'=>'fa-file-invoice']
        ];
        break;

    case 'purchases':
        $activeFilters = ['date', 'warehouse', 'entity_supplier', 'pay_method'];
        $columns = [
            'date' => 'التاريخ', 
            'invoice_number' => 'رقم الفاتورة', 
            'warehouse_name' => 'المخزن',
            'supplier_name' => 'اسم المورد',
            'supplier_phone' => 'التليفون',
            'payment_method' => 'طريقة الدفع', 
            'total_amount' => 'إجمالي الفاتورة', 
            'paid_amount' => 'المدفوع', 
            'remaining_amount' => 'المتبقي'
        ];
        
        $w = "i.type='purchase'"; 
        $p = [];
        if ($dateFrom && $dateTo) { $w .= " AND i.date BETWEEN ? AND ?"; $p[] = $dateFrom; $p[] = $dateTo; }
        if ($filterWarehouse) { $w .= " AND i.warehouse_id=?"; $p[] = $filterWarehouse; }
        if ($filterEntity) { $w .= " AND i.supplier_id=?"; $p[] = $filterEntity; }
        if ($filterPayMethod) { $w .= " AND i.payment_method=?"; $p[] = $filterPayMethod; }
        
        $data = getRows("SELECT i.*, COALESCE(w.name, 'المخزن الرئيسي') as warehouse_name, s.name as supplier_name, s.phone as supplier_phone FROM invoices i LEFT JOIN suppliers s ON i.supplier_id=s.id LEFT JOIN warehouses w ON i.warehouse_id=w.id WHERE {$w} ORDER BY i.date DESC, i.id DESC", $p);
        
        $totalP = 0; $totalPaid = 0; $totalR = 0;
        foreach($data as $row) { $totalP += $row['total_amount']; $totalPaid += $row['paid_amount']; $totalR += $row['remaining_amount']; }
        
        $summaryCards = [
            ['title'=>'إجمالي المشتريات', 'value'=>formatCurrency($totalP), 'color'=>'primary', 'icon'=>'fa-shopping-cart'],
            ['title'=>'المدفوع للموردين', 'value'=>formatCurrency($totalPaid), 'color'=>'success', 'icon'=>'fa-money-bill-wave'],
            ['title'=>'المتبقي للموردين', 'value'=>formatCurrency($totalR), 'color'=>'danger', 'icon'=>'fa-clock'],
            ['title'=>'عدد الفواتير', 'value'=>count($data), 'color'=>'secondary', 'icon'=>'fa-file-invoice']
        ];
        break;

    case 'profit':
        $activeFilters = ['date', 'warehouse'];
        $columns = [
            'date' => 'التاريخ', 
            'invoice_number' => 'رقم الفاتورة', 
            'warehouse_name' => 'المخزن',
            'product_name' => 'اسم المنتج',
            'quantity' => 'الكمية', 
            'cost_price' => 'التكلفة', 
            'sale_price' => 'سعر البيع',
            'profit' => 'هامش ربح الصنف'
        ];
        
        $w = "i.type='sale'"; $p = [];
        if ($dateFrom && $dateTo) { $w .= " AND i.date BETWEEN ? AND ?"; $p[] = $dateFrom; $p[] = $dateTo; }
        if ($filterWarehouse) { $w .= " AND i.warehouse_id=?"; $p[] = $filterWarehouse; }
        
        $data = getRows("SELECT i.invoice_number, i.date, COALESCE(w.name, 'المخزن الرئيسي') as warehouse_name, ii.quantity, ii.unit_price as sale_price, p.name as product_name, COALESCE(p.cost_price,0) as cost_price, (ii.unit_price - COALESCE(p.cost_price,0)) * ii.quantity as profit FROM invoice_items ii JOIN invoices i ON ii.invoice_id=i.id JOIN products p ON ii.product_id=p.id LEFT JOIN warehouses w ON i.warehouse_id=w.id WHERE {$w} ORDER BY i.date DESC, i.id DESC", $p);
        
        $grossProfit = 0; $totalRev = 0; $totalCost = 0;
        foreach($data as $row) { 
            $grossProfit += $row['profit']; 
            $totalRev += $row['sale_price'] * $row['quantity']; 
            $totalCost += $row['cost_price'] * $row['quantity']; 
        }

        // Calculate operating expenses for the exact same filtered period
        $expCond = "1=1"; $expParams = [];
        if ($dateFrom && $dateTo) { 
            $expCond .= " AND expense_date BETWEEN ? AND ?"; 
            $expParams[] = $dateFrom; 
            $expParams[] = $dateTo; 
        }
        $expRow = getRow("SELECT COALESCE(SUM(amount), 0) as total_exp, COUNT(*) as cnt FROM expenses WHERE {$expCond}", $expParams);
        $totalExpenses = floatval($expRow['total_exp'] ?? 0);

        // Net Real Profit = Gross Profit from sales minus Operating Expenses
        $netRealProfit = $grossProfit - $totalExpenses;
        $netMargin = ($totalRev > 0) ? ($netRealProfit / $totalRev) * 100 : 0;
        
        $summaryCards = [
            ['title'=>'إجمالي الإيراد (المبيعات)', 'value'=>formatCurrency($totalRev), 'color'=>'primary', 'icon'=>'fa-chart-line'],
            ['title'=>'تكلفة البضاعة المباعة', 'value'=>formatCurrency($totalCost), 'color'=>'warning', 'icon'=>'fa-box'],
            ['title'=>'مجمل ربح البضاعة', 'value'=>formatCurrency($grossProfit), 'color'=>'info', 'icon'=>'fa-coins'],
            ['title'=>'المصروفات والتكاليف التشغيلية', 'value'=>formatCurrency($totalExpenses), 'color'=>'danger', 'icon'=>'fa-file-invoice-dollar'],
            ['title'=>'صافي الربح الفعلي النهائي', 'value'=>formatCurrency($netRealProfit), 'color'=>($netRealProfit >= 0 ? 'success' : 'danger'), 'icon'=>($netRealProfit >= 0 ? 'fa-smile' : 'fa-frown')],
            ['title'=>'نسبة هامش صافي الربح', 'value'=>number_format($netMargin, 1) . '%', 'color'=>($netMargin >= 0 ? 'info' : 'danger'), 'icon'=>'fa-percent']
        ];
        break;

    case 'expenses':
        $activeFilters = ['date', 'expense_category', 'pay_method'];
        $columns = [
            'expense_date' => 'التاريخ',
            'expense_number' => 'رقم السند',
            'category_name' => 'البند والتصنيف',
            'title' => 'بيان وتفاصيل المصروف',
            'amount' => 'المبلغ',
            'payment_method' => 'طريقة الدفع',
            'paid_to' => 'المدفوع له',
            'receipt_number' => 'رقم الإيصال الورقي',
            'handled_by' => 'المسؤول',
            'actions' => 'سند الصرف'
        ];

        $w = "1=1"; $p = [];
        if ($dateFrom && $dateTo) { $w .= " AND e.expense_date BETWEEN ? AND ?"; $p[] = $dateFrom; $p[] = $dateTo; }
        if ($filterExpenseCat) { $w .= " AND e.category_id = ?"; $p[] = $filterExpenseCat; }
        if ($filterPayMethod) { $w .= " AND e.payment_method = ?"; $p[] = $filterPayMethod; }

        $rawExpenses = getRows("
            SELECT e.*, COALESCE(c.name, 'غير مصنف') as category_name, COALESCE(c.icon, '💸') as category_icon
            FROM expenses e
            LEFT JOIN expense_categories c ON e.category_id = c.id
            WHERE {$w}
            ORDER BY e.expense_date DESC, e.id DESC
        ", $p);

        $totalExpVal = 0; $cashExpVal = 0; $otherExpVal = 0;
        foreach($rawExpenses as $r) {
            $totalExpVal += $r['amount'];
            if ($r['payment_method'] === 'نقدي') $cashExpVal += $r['amount'];
            else $otherExpVal += $r['amount'];

            $r['category_name'] = $r['category_icon'] . ' ' . htmlspecialchars($r['category_name']);
            $r['amount'] = '<span class="text-danger" style="font-weight:700;">' . formatCurrency($r['amount']) . '</span>';
            $r['actions'] = '<a href="../expenses/print.php?id=' . $r['id'] . '" target="_blank" class="btn-submit" style="padding:4px 10px; font-size:12px; text-decoration:none;"><i class="fas fa-print"></i> معاينة</a>';
            $data[] = $r;
        }

        $summaryCards = [
            ['title'=>'إجمالي المصروفات بالفترة', 'value'=>formatCurrency($totalExpVal), 'color'=>'danger', 'icon'=>'fa-file-invoice-dollar'],
            ['title'=>'المصروفات النقدية (الخزينة)', 'value'=>formatCurrency($cashExpVal), 'color'=>'warning', 'icon'=>'fa-money-bill-wave'],
            ['title'=>'طرق دفع أخرى (تحويل/شيك)', 'value'=>formatCurrency($otherExpVal), 'color'=>'info', 'icon'=>'fa-credit-card'],
            ['title'=>'عدد سندات الصرف', 'value'=>count($data) . ' سند', 'color'=>'primary', 'icon'=>'fa-receipt']
        ];
        break;

    case 'returns':
        $activeFilters = ['date', 'sub_type_entity'];
        $columns = [
            'return_date' => 'التاريخ', 
            'return_number' => 'رقم المرتجع', 
            'type_label' => 'الجهة',
            'entity_name' => 'الاسم',
            'total_amount' => 'إجمالي المرتجع', 
            'cash_refund' => 'المبلغ النفي المسترد', 
            'deducted_from_balance' => 'مخصوم من الحساب'
        ];
        
        $w = "1=1"; $p = [];
        if ($dateFrom && $dateTo) { $w .= " AND return_date BETWEEN ? AND ?"; $p[] = $dateFrom; $p[] = $dateTo; }
        if ($filterSubType) { $w .= " AND r.type=?"; $p[] = $filterSubType; }
        
        $raw = getRows("SELECT r.*, CASE WHEN r.type='customer' THEN c.name ELSE s.name END as entity_name FROM returns r LEFT JOIN customers c ON r.customer_id=c.id LEFT JOIN suppliers s ON r.supplier_id=s.id WHERE {$w} ORDER BY r.return_date DESC, r.id DESC", $p);
        
        $totalValue = 0; $totalCash = 0; $totalDed = 0;
        foreach($raw as $row) { 
            $row['type_label'] = $row['type'] == 'customer' ? 'مرتجع عميل' : 'مرتجع مورد';
            $data[] = $row;
            $totalValue += $row['total_amount']; $totalCash += $row['cash_refund']; $totalDed += $row['deducted_from_balance']; 
        }
        
        $summaryCards = [
            ['title'=>'إجمالي قيمة المرتجعات', 'value'=>formatCurrency($totalValue), 'color'=>'danger', 'icon'=>'fa-undo'],
            ['title'=>'مبالغ مستردة نقداً', 'value'=>formatCurrency($totalCash), 'color'=>'warning', 'icon'=>'fa-money-bill'],
            ['title'=>'مبالغ مخصومة من الحساب', 'value'=>formatCurrency($totalDed), 'color'=>'info', 'icon'=>'fa-balance-scale'],
            ['title'=>'عدد العمليات', 'value'=>count($data), 'color'=>'secondary', 'icon'=>'fa-list']
        ];
        break;

    case 'installments':
        $activeFilters = ['sub_type_entity', 'status_inst'];
        $columns = [
            'created_at' => 'تاريخ الخطة', 
            'entity_name' => 'اسم العميل / المورد', 
            'type_label' => 'نوع الجهة',
            'number_of_installments' => 'عدد الأقساط', 
            'total_amount' => 'إجمالي المبلغ', 
            'paid_amount' => 'المدفوع', 
            'remaining_amount' => 'المتبقي', 
            'status_label' => 'الحالة'
        ];
        
        $w = "1=1"; $p = [];
        if ($filterSubType) { $w .= " AND ip.type=?"; $p[] = $filterSubType; }
        if ($filterStatus) { $w .= " AND ip.status=?"; $p[] = $filterStatus; }
        
        $raw = getRows("SELECT ip.* FROM installment_plans ip WHERE {$w} ORDER BY ip.created_at DESC", $p);
        
        $totalAmt = 0; $totalPaid = 0; $totalRem = 0; $active = 0;
        foreach($raw as $row) { 
            $row['type_label'] = $row['type'] == 'customer' ? 'عميل' : 'مورد';
            $row['status_label'] = $row['status'] == 'active' ? '<span class="badge badge-warning">نشط</span>' : '<span class="badge badge-success">مكتمل</span>';
            $row['created_at'] = date('Y-m-d', strtotime($row['created_at']));
            $data[] = $row;
            $totalAmt += $row['total_amount']; $totalPaid += $row['paid_amount']; $totalRem += $row['remaining_amount'];
            if($row['status']=='active') $active++;
        }
        
        $summaryCards = [
            ['title'=>'إجمالي خطط التقسيط', 'value'=>formatCurrency($totalAmt), 'color'=>'primary', 'icon'=>'fa-calendar-alt'],
            ['title'=>'المدفوع من الأقساط', 'value'=>formatCurrency($totalPaid), 'color'=>'success', 'icon'=>'fa-check-circle'],
            ['title'=>'المتبقي للتحصيل', 'value'=>formatCurrency($totalRem), 'color'=>'danger', 'icon'=>'fa-clock'],
            ['title'=>'الخطط النشطة', 'value'=>$active, 'color'=>'info', 'icon'=>'fa-tasks']
        ];
        break;

    case 'payments':
        $activeFilters = ['date', 'sub_type_entity', 'pay_method_bank'];
        $columns = [
            'payment_date' => 'التاريخ', 
            'payment_number' => 'رقم الإيصال', 
            'entity_name' => 'الاسم',
            'type_label' => 'نوع الحركة', 
            'payment_method' => 'طريقة الدفع', 
            'amount' => 'المبلغ',
            'notes' => 'ملاحظات / البيان'
        ];
        
        $w = "1=1"; $p = [];
        if ($dateFrom && $dateTo) { $w .= " AND payment_date BETWEEN ? AND ?"; $p[] = $dateFrom; $p[] = $dateTo; }
        if ($filterSubType) { $w .= " AND type=?"; $p[] = $filterSubType; }
        if ($filterPayMethod) { $w .= " AND payment_method=?"; $p[] = $filterPayMethod; }
        
        $raw = getRows("SELECT * FROM payments WHERE {$w} ORDER BY payment_date DESC, id DESC", $p);
        
        $collected = 0; $paidOut = 0;
        foreach($raw as $row) {
            $row['type_label'] = $row['type'] == 'customer' ? '<span class="text-success">قبض (تحصيل عميل)</span>' : '<span class="text-danger">صرف (دفع لمورد)</span>';
            $data[] = $row;
            if($row['type'] == 'customer') $collected += $row['amount']; else $paidOut += $row['amount'];
        }
        
        $summaryCards = [
            ['title'=>'إجمالي المقبوضات (دخل)', 'value'=>formatCurrency($collected), 'color'=>'success', 'icon'=>'fa-hand-holding-usd'],
            ['title'=>'إجمالي المدفوعات (خرج)', 'value'=>formatCurrency($paidOut), 'color'=>'danger', 'icon'=>'fa-money-bill-wave'],
            ['title'=>'صافي حركة الخزينة', 'value'=>formatCurrency($collected - $paidOut), 'color'=>'primary', 'icon'=>'fa-balance-scale'],
            ['title'=>'عدد الإيصالات', 'value'=>count($data), 'color'=>'secondary', 'icon'=>'fa-receipt']
        ];
        break;

    case 'customers':
        $activeFilters = ['status_balance'];
        $columns = [
            'name' => 'اسم العميل', 
            'phone' => 'رقم التليفون', 
            'address' => 'العنوان', 
            'balance_label' => 'الرصيد الكلي الحالي'
        ];
        
        $raw = getRows("SELECT * FROM customers ORDER BY balance ASC");
        
        $debts = 0; $credits = 0;
        foreach($raw as $row) {
            if ($filterStatus == 'debt' && $row['balance'] >= 0) continue;
            if ($filterStatus == 'credit' && $row['balance'] <= 0) continue;
            if ($filterStatus == 'zero' && $row['balance'] != 0) continue;
            
            $txt = $row['balance'] < 0 ? 'مدين (عليه دين)' : ($row['balance'] > 0 ? 'دائن (له رصيد)' : 'خالص (متزن)');
            $row['balance_label'] = "<span class='" . ($row['balance'] < 0 ? 'text-danger' : ($row['balance'] > 0 ? 'text-success' : '')) . "'>" . formatCurrency(abs($row['balance'])) . " [$txt]</span>";
            $data[] = $row;
        }
        
        foreach($raw as $row) {
            if($row['balance'] < 0) $debts += abs($row['balance']);
            elseif($row['balance'] > 0) $credits += $row['balance'];
        }
        
        $summaryCards = [
            ['title'=>'إجمالي ديون العملاء (لنا)', 'value'=>formatCurrency($debts), 'color'=>'success', 'icon'=>'fa-arrow-down'],
            ['title'=>'أرصدة دائنة للعملاء (لهم)', 'value'=>formatCurrency($credits), 'color'=>'danger', 'icon'=>'fa-arrow-up'],
            ['title'=>'إجمالي المسجلين', 'value'=>count($raw), 'color'=>'primary', 'icon'=>'fa-users'],
            ['title'=>'العملاء المعروضين', 'value'=>count($data), 'color'=>'secondary', 'icon'=>'fa-filter']
        ];
        break;

    case 'suppliers':
        $activeFilters = ['status_supplier_balance'];
        $columns = [
            'name' => 'اسم المورد', 
            'phone' => 'رقم التليفون', 
            'address' => 'العنوان', 
            'balance_label' => 'الرصيد الكلي الحالي'
        ];
        
        $raw = getRows("SELECT * FROM suppliers ORDER BY balance ASC");
        
        $debts = 0; $credits = 0;
        foreach($raw as $row) {
            if ($filterStatus == 'owe' && $row['balance'] >= 0) continue;
            if ($filterStatus == 'credit' && $row['balance'] <= 0) continue;
            if ($filterStatus == 'zero' && $row['balance'] != 0) continue;
            
            $txt = $row['balance'] < 0 ? 'دائن (له مستحقات)' : ($row['balance'] > 0 ? 'مدين (عليه رصيد)' : 'خالص (متزن)');
            $row['balance_label'] = "<span class='" . ($row['balance'] < 0 ? 'text-danger' : ($row['balance'] > 0 ? 'text-success' : '')) . "'>" . formatCurrency(abs($row['balance'])) . " [$txt]</span>";
            $data[] = $row;
        }
        
        foreach($raw as $row) {
            if($row['balance'] < 0) $debts += abs($row['balance']);
            elseif($row['balance'] > 0) $credits += $row['balance'];
        }
        
        $summaryCards = [
            ['title'=>'مستحقات الموردين (علينا)', 'value'=>formatCurrency($debts), 'color'=>'danger', 'icon'=>'fa-hand-holding-usd'],
            ['title'=>'أرصدة مدائنة للموردين (لنا)', 'value'=>formatCurrency($credits), 'color'=>'success', 'icon'=>'fa-coins'],
            ['title'=>'إجمالي المسجلين', 'value'=>count($raw), 'color'=>'primary', 'icon'=>'fa-truck'],
            ['title'=>'الموردين المعروضين', 'value'=>count($data), 'color'=>'secondary', 'icon'=>'fa-filter']
        ];
        break;

    case 'stock':
        $activeFilters = ['warehouse', 'status_stock'];
        $selectedWhName = $filterWarehouse ? getWarehouseName($filterWarehouse) : '';
        $qtyColTitle = $selectedWhName ? "الكمية ($selectedWhName)" : 'الكمية (إجمالي كل المخازن)';
        $columns = [
            'code' => 'كود الصنف', 
            'name' => 'اسم المنتج', 
            'stock_quantity' => $qtyColTitle,
            'cost_price_lbl' => 'سعر التكلفة', 
            'price_lbl' => 'سعر البيع', 
            'stock_value' => 'إجمالي التكلفة', 
            'status_label' => 'حالة المخزون'
        ];
        
        if ($filterWarehouse) {
            $raw = getRows("
                SELECT p.id, p.code, p.name, p.min_stock_level, p.cost_price, p.price,
                       COALESCE(ws.quantity, 0) as stock_quantity
                FROM products p
                LEFT JOIN warehouse_stock ws ON p.id = ws.product_id AND ws.warehouse_id = ?
                ORDER BY stock_quantity ASC
            ", [$filterWarehouse]);
        } else {
            $raw = getRows("SELECT * FROM products ORDER BY stock_quantity ASC");
        }
        
        $totalCost = 0; $totalSale = 0; $items = 0;
        foreach($raw as $row) {
            $status = $row['stock_quantity'] == 0 ? 'out' : ($row['stock_quantity'] <= $row['min_stock_level'] ? 'low' : 'ok');
            if ($filterStatus && $filterStatus != $status) continue;
            
            $cPrice = floatval($row['cost_price'] ?? $row['price'] * 0.7);
            
            $lbl = $status == 'out' ? "<span class='badge badge-danger'>نفد تماماً</span>" : ($status == 'low' ? "<span class='badge badge-warning'>منخفض جداً</span>" : "<span class='badge badge-success'>متوفر</span>");
            $row['status_label'] = $lbl;
            $row['cost_price_lbl'] = formatCurrency($cPrice);
            $row['price_lbl'] = formatCurrency($row['price']);
            $row['stock_value'] = formatCurrency($row['stock_quantity'] * $cPrice);
            
            $data[] = $row;
        }
        
        foreach($raw as $row) {
            $cPrice = floatval($row['cost_price'] ?? $row['price'] * 0.7);
            $totalCost += ($row['stock_quantity'] * $cPrice);
            $totalSale += ($row['stock_quantity'] * $row['price']);
            $items += $row['stock_quantity'];
        }
        
        $whTitleSuffix = $selectedWhName ? " ($selectedWhName)" : " (إجمالي شامل)";
        $summaryCards = [
            ['title'=>'قيمة المخزون (بالتكلفة)' . $whTitleSuffix, 'value'=>formatCurrency($totalCost), 'color'=>'primary', 'icon'=>'fa-boxes'],
            ['title'=>'قيمة المخزون (بالبيع)' . $whTitleSuffix, 'value'=>formatCurrency($totalSale), 'color'=>'success', 'icon'=>'fa-tags'],
            ['title'=>'إجمالي القطع' . $whTitleSuffix, 'value'=>$items . ' قطعة', 'color'=>'info', 'icon'=>'fa-layer-group'],
            ['title'=>'إجمالي الأصناف', 'value'=>count($raw), 'color'=>'secondary', 'icon'=>'fa-list-ol']
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
        
        // Conditions
        $userWhereInv = "1=1";
        $userWherePay = "1=1";
        $userWhereRet = "1=1";
        $userWhereInst = "1=1";
        $userWhereAct = "1=1";
        $paramsUserInv = [];
        $paramsUserPay = [];
        $paramsUserRet = [];
        $paramsUserInst = [];
        $paramsUserAct = [];
        
        if ($targetUser) {
            $userWhereInv = "(i.user_id = ? OR i.handled_by = ? OR i.handled_by = ?)";
            $paramsUserInv = [$targetUser['id'], $targetUser['full_name'], $targetUser['username']];
            
            $userWherePay = "(p.user_id = ? OR p.handled_by = ? OR p.handled_by = ?)";
            $paramsUserPay = [$targetUser['id'], $targetUser['full_name'], $targetUser['username']];
            
            $userWhereRet = "(r.user_id = ? OR r.handled_by = ? OR r.handled_by = ?)";
            $paramsUserRet = [$targetUser['id'], $targetUser['full_name'], $targetUser['username']];
            
            $userWhereInst = "(ip.user_id = ? OR ip.handled_by = ? OR ip.handled_by = ?)";
            $paramsUserInst = [$targetUser['id'], $targetUser['full_name'], $targetUser['username']];
            
            $userWhereAct = "(a.user_id = ? OR a.user = ? OR a.user = ?)";
            $paramsUserAct = [$targetUser['id'], $targetUser['full_name'], $targetUser['username']];
        }
        
        // Date conditions
        $dateCondInv = "";
        $dateCondPay = "";
        $dateCondRet = "";
        $dateCondInst = "";
        $dateCondAct = "";
        if ($dateFrom && $dateTo) {
            $dateCondInv = " AND i.date BETWEEN ? AND ?";
            $dateCondPay = " AND p.payment_date BETWEEN ? AND ?";
            $dateCondRet = " AND r.return_date BETWEEN ? AND ?";
            $dateCondInst = " AND ip.paid_date BETWEEN ? AND ?";
            $dateCondAct = " AND DATE(a.created_at) BETWEEN ? AND ?";
        }
        
        // Summary KPIs (Aggregates)
        $pSales = $paramsUserInv; if ($dateFrom && $dateTo) { $pSales[] = $dateFrom; $pSales[] = $dateTo; }
        $userSales = getRow("SELECT COUNT(*) as cnt, COALESCE(SUM(i.total_amount - i.discount), 0) as total, COALESCE(SUM(i.paid_amount), 0) as paid, COALESCE(SUM(i.remaining_amount), 0) as remaining FROM invoices i WHERE i.type='sale' AND {$userWhereInv} {$dateCondInv}", $pSales);
        
        $pPur = $paramsUserInv; if ($dateFrom && $dateTo) { $pPur[] = $dateFrom; $pPur[] = $dateTo; }
        $userPurchases = getRow("SELECT COUNT(*) as cnt, COALESCE(SUM(i.total_amount - i.discount), 0) as total, COALESCE(SUM(i.paid_amount), 0) as paid, COALESCE(SUM(i.remaining_amount), 0) as remaining FROM invoices i WHERE i.type='purchase' AND {$userWhereInv} {$dateCondInv}", $pPur);
        
        $pCustPay = $paramsUserPay; if ($dateFrom && $dateTo) { $pCustPay[] = $dateFrom; $pCustPay[] = $dateTo; }
        $userCustPay = getRow("SELECT COUNT(*) as cnt, COALESCE(SUM(p.amount), 0) as total FROM payments p WHERE p.type='customer' AND {$userWherePay} {$dateCondPay}", $pCustPay);
        
        $pSuppPay = $paramsUserPay; if ($dateFrom && $dateTo) { $pSuppPay[] = $dateFrom; $pSuppPay[] = $dateTo; }
        $userSuppPay = getRow("SELECT COUNT(*) as cnt, COALESCE(SUM(p.amount), 0) as total FROM payments p WHERE p.type='supplier' AND {$userWherePay} {$dateCondPay}", $pSuppPay);
        
        $pInst = $paramsUserInst; if ($dateFrom && $dateTo) { $pInst[] = $dateFrom; $pInst[] = $dateTo; }
        $userInstPay = getRow("SELECT COUNT(*) as cnt, COALESCE(SUM(ip.paid_amount), 0) as total FROM installment_payments ip WHERE ip.status IN ('paid', 'partial') AND {$userWhereInst} {$dateCondInst}", $pInst);
        
        $pRet = $paramsUserRet; if ($dateFrom && $dateTo) { $pRet[] = $dateFrom; $pRet[] = $dateTo; }
        $userReturns = getRow("SELECT COUNT(*) as cnt, COALESCE(SUM(r.total_amount), 0) as total, COALESCE(SUM(r.cash_refund), 0) as cash_refund FROM returns r WHERE {$userWhereRet} {$dateCondRet}", $pRet);
        
        $pAct = $paramsUserAct; if ($dateFrom && $dateTo) { $pAct[] = $dateFrom; $pAct[] = $dateTo; }
        $userActivity = getRow("SELECT COUNT(*) as cnt FROM activity_log a WHERE {$userWhereAct} {$dateCondAct}", $pAct);
        
        $salesCash = floatval($userSales['paid']);
        $custCash = floatval($userCustPay['total']);
        $totalCashIn = $salesCash + $custCash;
        
        $purCash = floatval($userPurchases['paid']);
        $suppCash = floatval($userSuppPay['total']);
        $retRefund = floatval($userReturns['cash_refund']);
        $totalCashOut = $purCash + $suppCash + $retRefund;
        $netCash = $totalCashIn - $totalCashOut;
        
        $summaryCards = [
            ['title'=>'إجمالي مبيعات المستخدم', 'value'=>formatCurrency($userSales['total']) . ' (' . $userSales['cnt'] . ' فاتورة)', 'color'=>'primary', 'icon'=>'fa-shopping-cart'],
            ['title'=>'إجمالي النقد الداخل', 'value'=>formatCurrency($totalCashIn), 'color'=>'success', 'icon'=>'fa-arrow-down'],
            ['title'=>'إجمالي مشتريات المستخدم', 'value'=>formatCurrency($userPurchases['total']) . ' (' . $userPurchases['cnt'] . ' فاتورة)', 'color'=>'warning', 'icon'=>'fa-truck-loading'],
            ['title'=>'إجمالي النقد الخارج', 'value'=>formatCurrency($totalCashOut), 'color'=>'danger', 'icon'=>'fa-arrow-up'],
            ['title'=>'صافي حركة الخزينة', 'value'=>formatCurrency($netCash), 'color'=>($netCash>=0 ? 'success':'danger'), 'icon'=>'fa-vault'],
            ['title'=>'إجمالي المرتجعات', 'value'=>formatCurrency($userReturns['total']) . ' (' . $userReturns['cnt'] . ' عملية)', 'color'=>'secondary', 'icon'=>'fa-undo'],
            ['title'=>'تحصيلات الأقساط', 'value'=>formatCurrency($userInstPay['total']) . ' (' . $userInstPay['cnt'] . ' قسط)', 'color'=>'info', 'icon'=>'fa-calendar-check'],
            ['title'=>'سجل الحركات المسجلة', 'value'=>$userActivity['cnt'] . ' حركة', 'color'=>'secondary', 'icon'=>'fa-clipboard-list']
        ];
        
        switch ($userSubView) {
            case 'sales':
                $columns = [
                    'date' => 'التاريخ',
                    'invoice_number' => 'رقم الفاتورة',
                    'customer_name' => 'اسم العميل',
                    'customer_phone' => 'التليفون',
                    'payment_method' => 'طريقة الدفع',
                    'total_amount' => 'إجمالي الفاتورة',
                    'paid_amount' => 'المدفوع',
                    'remaining_amount' => 'المتبقي',
                    'actions' => 'عرض الفاتورة'
                ];
                $p = $paramsUserInv;
                if ($dateFrom && $dateTo) { $p[] = $dateFrom; $p[] = $dateTo; }
                $raw = getRows("SELECT i.*, COALESCE(c.name, i.customer_name) as customer_name, COALESCE(c.phone, i.customer_phone) as customer_phone FROM invoices i LEFT JOIN customers c ON i.customer_id = c.id WHERE i.type='sale' AND {$userWhereInv} {$dateCondInv} ORDER BY i.date DESC, i.id DESC", $p);
                foreach ($raw as $r) {
                    $r['actions'] = '<a href="../invoices/print.php?id=' . $r['id'] . '" target="_blank" class="btn-submit" style="padding:4px 10px; font-size:12px; text-decoration:none;"><i class="fas fa-print"></i> عرض الفاتورة</a>';
                    $data[] = $r;
                }
                break;
                
            case 'purchases':
                $columns = [
                    'date' => 'التاريخ',
                    'invoice_number' => 'رقم الفاتورة',
                    'supplier_name' => 'اسم المورد',
                    'supplier_phone' => 'التليفون',
                    'payment_method' => 'طريقة الدفع',
                    'total_amount' => 'إجمالي الفاتورة',
                    'paid_amount' => 'المدفوع',
                    'remaining_amount' => 'المتبقي',
                    'actions' => 'عرض الفاتورة'
                ];
                $p = $paramsUserInv;
                if ($dateFrom && $dateTo) { $p[] = $dateFrom; $p[] = $dateTo; }
                $raw = getRows("SELECT i.*, s.name as supplier_name, s.phone as supplier_phone FROM invoices i LEFT JOIN suppliers s ON i.supplier_id = s.id WHERE i.type='purchase' AND {$userWhereInv} {$dateCondInv} ORDER BY i.date DESC, i.id DESC", $p);
                foreach ($raw as $r) {
                    $r['actions'] = '<a href="../invoices/print_purchase.php?id=' . $r['id'] . '" target="_blank" class="btn-submit" style="padding:4px 10px; font-size:12px; text-decoration:none;"><i class="fas fa-print"></i> عرض الفاتورة</a>';
                    $data[] = $r;
                }
                break;
                
            case 'payments':
                $columns = [
                    'payment_date' => 'التاريخ',
                    'payment_number' => 'رقم الإيصال',
                    'type_label' => 'نوع الحركة',
                    'entity_name' => 'الجهة / الاسم',
                    'payment_method' => 'طريقة الدفع',
                    'amount' => 'المبلغ',
                    'notes' => 'البيان',
                    'actions' => 'الإيصال'
                ];
                $p = $paramsUserPay;
                if ($dateFrom && $dateTo) { $p[] = $dateFrom; $p[] = $dateTo; }
                $raw = getRows("SELECT p.* FROM payments p WHERE {$userWherePay} {$dateCondPay} ORDER BY p.payment_date DESC, p.id DESC", $p);
                foreach ($raw as $r) {
                    $r['type_label'] = $r['type'] == 'customer' ? '<span class="text-success">قبض من عميل</span>' : '<span class="text-danger">صرف لمورد</span>';
                    $printFile = $r['type'] == 'customer' ? '../payments/print_customer.php' : '../payments/print_supplier.php';
                    $r['actions'] = '<a href="' . $printFile . '?id=' . $r['id'] . '" target="_blank" class="btn-submit" style="padding:4px 10px; font-size:12px; text-decoration:none;"><i class="fas fa-print"></i> الإيصال</a>';
                    $data[] = $r;
                }
                break;
                
            case 'installments':
                $columns = [
                    'paid_date' => 'تاريخ السداد',
                    'payment_number' => 'رقم القسط',
                    'entity_name' => 'العميل / المورد',
                    'amount' => 'المبلغ المستحق',
                    'paid_amount' => 'المبلغ المسدد',
                    'payment_method' => 'طريقة الدفع',
                    'status_label' => 'الحالة',
                    'actions' => 'الخطة'
                ];
                $p = $paramsUserInst;
                if ($dateFrom && $dateTo) { $p[] = $dateFrom; $p[] = $dateTo; }
                $raw = getRows("SELECT ip.*, pl.entity_name FROM installment_payments ip JOIN installment_plans pl ON ip.plan_id = pl.id WHERE ip.status IN ('paid', 'partial') AND {$userWhereInst} {$dateCondInst} ORDER BY ip.paid_date DESC, ip.id DESC", $p);
                foreach ($raw as $r) {
                    $r['status_label'] = $r['status'] == 'paid' ? '<span class="badge badge-success">مدفوع بالكامل</span>' : '<span class="badge badge-warning">مدفوع جزئياً</span>';
                    $r['actions'] = '<a href="../installments/view.php?id=' . $r['plan_id'] . '" class="btn-submit" style="padding:4px 10px; font-size:12px; text-decoration:none;"><i class="fas fa-eye"></i> الخطة</a>';
                    $data[] = $r;
                }
                break;
                
            case 'returns':
                $columns = [
                    'return_date' => 'التاريخ',
                    'return_number' => 'رقم المرتجع',
                    'type_label' => 'نوع المرتجع',
                    'entity_name' => 'الاسم',
                    'total_amount' => 'إجمالي المرتجع',
                    'cash_refund' => 'المسترد نقداً',
                    'deducted_from_balance' => 'مخصوم من الحساب',
                    'actions' => 'معاينة'
                ];
                $p = $paramsUserRet;
                if ($dateFrom && $dateTo) { $p[] = $dateFrom; $p[] = $dateTo; }
                $raw = getRows("SELECT r.*, CASE WHEN r.type='customer' THEN COALESCE(c.name, r.customer_name) ELSE COALESCE(s.name, r.supplier_name) END as entity_name FROM returns r LEFT JOIN customers c ON r.customer_id=c.id LEFT JOIN suppliers s ON r.supplier_id=s.id WHERE {$userWhereRet} {$dateCondRet} ORDER BY r.return_date DESC, r.id DESC", $p);
                foreach ($raw as $r) {
                    $r['type_label'] = $r['type'] == 'customer' ? 'مرتجع عميل' : 'مرتجع مورد';
                    $printFile = $r['type'] == 'customer' ? '../returns/print.php' : '../returns/print_supplier.php';
                    $r['actions'] = '<a href="' . $printFile . '?id=' . $r['id'] . '" target="_blank" class="btn-submit" style="padding:4px 10px; font-size:12px; text-decoration:none;"><i class="fas fa-print"></i> معاينة</a>';
                    $data[] = $r;
                }
                break;
                
            case 'activity':
                $columns = [
                    'created_at' => 'التاريخ والوقت',
                    'user' => 'المستخدم',
                    'action' => 'العملية / الإجراء',
                    'description' => 'التفاصيل والبيان',
                    'ip_address' => 'عنوان IP'
                ];
                $p = $paramsUserAct;
                if ($dateFrom && $dateTo) { $p[] = $dateFrom; $p[] = $dateTo; }
                $raw = getRows("SELECT a.* FROM activity_log a WHERE {$userWhereAct} {$dateCondAct} ORDER BY a.created_at DESC, a.id DESC", $p);
                foreach ($raw as $r) {
                    $data[] = $r;
                }
                break;
                
            case 'overview':
            default:
                if (!$targetUser) {
                    // Comparative Table of ALL users
                    $columns = [
                        'user_name' => 'اسم الموظف / المستخدم',
                        'role_label' => 'الدور',
                        'sales_count' => 'عدد فواتير البيع',
                        'sales_total' => 'إجمالي المبيعات',
                        'sales_cash' => 'كاش المبيعات المحصل',
                        'purchases_total' => 'إجمالي المشتريات',
                        'returns_total' => 'إجمالي المرتجعات',
                        'actions_count' => 'سجل النشاط',
                        'actions' => 'التقرير التفصيلي'
                    ];
                    
                    foreach ($usersList as $u) {
                        $pU = [$u['id'], $u['full_name'], $u['username']];
                        $pSalesU = $pU; if ($dateFrom && $dateTo) { $pSalesU[] = $dateFrom; $pSalesU[] = $dateTo; }
                        $sU = getRow("SELECT COUNT(*) as cnt, COALESCE(SUM(total_amount - discount), 0) as total, COALESCE(SUM(paid_amount), 0) as paid FROM invoices i WHERE i.type='sale' AND (i.user_id = ? OR i.handled_by = ? OR i.handled_by = ?) {$dateCondInv}", $pSalesU);
                        
                        $pPurU = $pU; if ($dateFrom && $dateTo) { $pPurU[] = $dateFrom; $pPurU[] = $dateTo; }
                        $purU = getRow("SELECT COUNT(*) as cnt, COALESCE(SUM(total_amount - discount), 0) as total FROM invoices i WHERE i.type='purchase' AND (i.user_id = ? OR i.handled_by = ? OR i.handled_by = ?) {$dateCondInv}", $pPurU);
                        
                        $pRetU = $pU; if ($dateFrom && $dateTo) { $pRetU[] = $dateFrom; $pRetU[] = $dateTo; }
                        $retU = getRow("SELECT COALESCE(SUM(total_amount), 0) as total FROM returns r WHERE (r.user_id = ? OR r.handled_by = ? OR r.handled_by = ?) {$dateCondRet}", $pRetU);
                        
                        $pActU = $pU; if ($dateFrom && $dateTo) { $pActU[] = $dateFrom; $pActU[] = $dateTo; }
                        $actU = getRow("SELECT COUNT(*) as cnt FROM activity_log a WHERE (a.user_id = ? OR a.user = ? OR a.user = ?) {$dateCondAct}", $pActU);
                        
                        $data[] = [
                            'user_name' => '<strong>' . htmlspecialchars($u['full_name']) . '</strong> <small style="color:var(--gray);">(' . htmlspecialchars($u['username']) . ')</small>',
                            'role_label' => $u['role'] === 'admin' ? '<span class="badge" style="background:#4f46e5;">👑 مدير نظام</span>' : '<span class="badge" style="background:#0284c7;">👤 موظف</span>',
                            'sales_count' => $sU['cnt'] . ' فاتورة',
                            'sales_total' => formatCurrency($sU['total']),
                            'sales_cash' => formatCurrency($sU['paid']),
                            'purchases_total' => formatCurrency($purU['total']),
                            'returns_total' => formatCurrency($retU['total']),
                            'actions_count' => $actU['cnt'] . ' حركة',
                            'actions' => '<a href="?type=users&user_id=' . $u['id'] . '&user_view=overview' . ($dateFrom ? "&date_from=$dateFrom&date_to=$dateTo" : "") . '" class="btn-submit" style="padding:4px 10px; font-size:12px; text-decoration:none;"><i class="fas fa-user-check"></i> عرض تقريره</a>'
                        ];
                    }
                } else {
                    // Selected user's recent operations
                    $columns = [
                        'date_time' => 'التاريخ والوقت',
                        'type_badge' => 'نوع الحركة',
                        'ref_number' => 'المرجع / الرقم',
                        'details' => 'البيان والتفاصيل',
                        'amount_formatted' => 'المبلغ',
                        'actions' => 'تفاصيل'
                    ];
                    
                    $p = $paramsUserInv;
                    if ($dateFrom && $dateTo) { $p[] = $dateFrom; $p[] = $dateTo; }
                    $recentSales = getRows("SELECT i.date, i.created_at, i.invoice_number as ref, CONCAT('فاتورة بيع للعميل: ', COALESCE(c.name, i.customer_name)) as details, (i.total_amount - i.discount) as amount, 'sale' as itype, i.id FROM invoices i LEFT JOIN customers c ON i.customer_id = c.id WHERE i.type='sale' AND {$userWhereInv} {$dateCondInv} ORDER BY i.created_at DESC LIMIT 15", $p);
                    
                    foreach ($recentSales as $s) {
                        $data[] = [
                            'date_time' => date('Y-m-d h:i A', strtotime($s['created_at'] ?: $s['date'])),
                            'type_badge' => '<span class="badge badge-success">فاتورة بيع</span>',
                            'ref_number' => $s['ref'],
                            'details' => $s['details'],
                            'amount_formatted' => formatCurrency($s['amount']),
                            'actions' => '<a href="../invoices/print.php?id=' . $s['id'] . '" target="_blank" class="btn-submit" style="padding:4px 10px; font-size:12px; text-decoration:none;"><i class="fas fa-print"></i> معاينة</a>'
                        ];
                    }
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
        <?php if(in_array('warehouse', $activeFilters) && $filterWarehouse): ?>
            <p style="margin:2px 0; font-weight:bold; font-size:14px;">🏢 المخزن: <?php echo htmlspecialchars(getWarehouseName($filterWarehouse)); ?></p>
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

            <?php if(in_array('warehouse', $activeFilters)): ?>
                <div class="filter-group">
                    <label>🏢 المخزن</label>
                    <select name="warehouse_id" class="form-control" onchange="this.form.submit()">
                        <option value="">-- كل المخازن (إجمالي شامل) --</option>
                        <?php foreach ($warehousesList as $wh): ?>
                            <option value="<?php echo $wh['id']; ?>" <?php echo ($filterWarehouse == $wh['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($wh['name']); ?> (<?php echo htmlspecialchars($wh['code']); ?>)
                            </option>
                        <?php endforeach; ?>
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
                <div class="filter-group"><label>طريقة الدفع</label><select name="pay_method" class="form-control"><option value="">الكل</option><option value="كاش" <?php echo $filterPayMethod=='كاش'?'selected':''; ?>>كاش</option><option value="نقدي" <?php echo $filterPayMethod=='نقدي'?'selected':''; ?>>نقدي</option><option value="آجل" <?php echo $filterPayMethod=='آجل'?'selected':''; ?>>آجل</option><option value="تحويل" <?php echo $filterPayMethod=='تحويل'?'selected':''; ?>>تحويل</option><option value="تحويل بنكي" <?php echo $filterPayMethod=='تحويل بنكي'?'selected':''; ?>>تحويل بنكي</option><option value="فودافون كاش" <?php echo $filterPayMethod=='فودافون كاش'?'selected':''; ?>>فودافون كاش</option></select></div>
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

            <?php if(in_array('status_inst', $activeFilters)): ?>
                <div class="filter-group"><label>حالة الأقساط</label><select name="filter_status" class="form-control"><option value="">الكل</option><option value="active" <?php echo $filterStatus=='active'?'selected':''; ?>>نشط</option><option value="completed" <?php echo $filterStatus=='completed'?'selected':''; ?>>مكتمل</option></select></div>
            <?php endif; ?>

            <?php if(in_array('status_balance', $activeFilters)): ?>
                <div class="filter-group"><label>حالة رصيد العملاء</label><select name="filter_status" class="form-control"><option value="">الكل</option><option value="debt" <?php echo $filterStatus=='debt'?'selected':''; ?>>عليهم ديون</option><option value="credit" <?php echo $filterStatus=='credit'?'selected':''; ?>>لهم أموال</option><option value="zero" <?php echo $filterStatus=='zero'?'selected':''; ?>>متزن (خالص)</option></select></div>
            <?php endif; ?>
            
            <?php if(in_array('status_supplier_balance', $activeFilters)): ?>
                <div class="filter-group"><label>حالة رصيد الموردين</label><select name="filter_status" class="form-control"><option value="">الكل</option><option value="owe" <?php echo $filterStatus=='owe'?'selected':''; ?>>لهم مستحقات (علينا)</option><option value="credit" <?php echo $filterStatus=='credit'?'selected':''; ?>>لنا أرصدة مدائنة</option><option value="zero" <?php echo $filterStatus=='zero'?'selected':''; ?>>متزن (خالص)</option></select></div>
            <?php endif; ?>

            <?php if(in_array('status_stock', $activeFilters)): ?>
                <div class="filter-group"><label>حالة المخزون</label><select name="filter_status" class="form-control"><option value="">الكل</option><option value="out" <?php echo $filterStatus=='out'?'selected':''; ?>>نفد تماماً</option><option value="low" <?php echo $filterStatus=='low'?'selected':''; ?>>منخفض جداً</option><option value="ok" <?php echo $filterStatus=='ok'?'selected':''; ?>>متوفر بكثرة</option></select></div>
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
        <a href="?<?php echo http_build_query(array_merge($baseQuery, ['user_view' => 'sales'])); ?>" class="report-tab <?php echo $userSubView === 'sales' ? 'active' : ''; ?>">
            <i class="fas fa-shopping-cart"></i> فواتير المبيعات (<?php echo $userSales['cnt'] ?? 0; ?>)
        </a>
        <a href="?<?php echo http_build_query(array_merge($baseQuery, ['user_view' => 'purchases'])); ?>" class="report-tab <?php echo $userSubView === 'purchases' ? 'active' : ''; ?>">
            <i class="fas fa-truck-loading"></i> فواتير المشتريات (<?php echo $userPurchases['cnt'] ?? 0; ?>)
        </a>
        <a href="?<?php echo http_build_query(array_merge($baseQuery, ['user_view' => 'payments'])); ?>" class="report-tab <?php echo $userSubView === 'payments' ? 'active' : ''; ?>">
            <i class="fas fa-receipt"></i> المقبوضات والمدفوعات (<?php echo ($userCustPay['cnt'] ?? 0) + ($userSuppPay['cnt'] ?? 0); ?>)
        </a>
        <a href="?<?php echo http_build_query(array_merge($baseQuery, ['user_view' => 'installments'])); ?>" class="report-tab <?php echo $userSubView === 'installments' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-alt"></i> تحصيل الأقساط (<?php echo $userInstPay['cnt'] ?? 0; ?>)
        </a>
        <a href="?<?php echo http_build_query(array_merge($baseQuery, ['user_view' => 'returns'])); ?>" class="report-tab <?php echo $userSubView === 'returns' ? 'active' : ''; ?>">
            <i class="fas fa-undo"></i> المرتجعات (<?php echo $userReturns['cnt'] ?? 0; ?>)
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
                            'sales' => 'فواتير المبيعات الصادرة',
                            'purchases' => 'فواتير المشتريات',
                            'payments' => 'المقبوضات والمدفوعات',
                            'installments' => 'تحصيل الأقساط',
                            'returns' => 'المرتجعات',
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
        const query = this.value.toLowerCase().trim();
        
        if (query.length < 1) {
            filterEntityResults.style.display = 'none';
            filterEntityId.value = '';
            if (filterEntityPhone) filterEntityPhone.value = '';
            return;
        }
        
        const matches = filterList.filter(item => 
            (item.name && item.name.toLowerCase().includes(query)) || 
            (item.phone && item.phone.includes(query))
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
