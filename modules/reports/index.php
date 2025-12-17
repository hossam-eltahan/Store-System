<?php
/**
 * Reports Dashboard - تقارير شاملة
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';

$pageTitle = 'التقارير';

$reportType = $_GET['type'] ?? 'sales';
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

// Logic to fetch report data based on type
$data = [];
$summary = [];

switch ($reportType) {
    case 'stock':
        $data = getRows("SELECT * FROM products ORDER BY stock_quantity ASC");
        $summary['total_items'] = count($data);
        $summary['total_value_cost'] = 0;
        $summary['total_value_sale'] = 0;
        $summary['low_stock'] = 0;
        $summary['out_of_stock'] = 0;
        foreach ($data as $item) {
            $costPrice = floatval($item['cost_price'] ?? $item['price'] * 0.7);
            $summary['total_value_cost'] += $item['stock_quantity'] * $costPrice;
            $summary['total_value_sale'] += $item['stock_quantity'] * $item['price'];
            if ($item['stock_quantity'] == 0) $summary['out_of_stock']++;
            elseif ($item['stock_quantity'] <= $item['min_stock_level']) $summary['low_stock']++;
        }
        $summary['expected_profit'] = $summary['total_value_sale'] - $summary['total_value_cost'];
        break;

    case 'sales':
        $data = getRows(
            "SELECT i.*, c.name as customer_name 
             FROM invoices i 
             LEFT JOIN customers c ON i.customer_id = c.id
             WHERE i.type = 'sale' AND i.date BETWEEN ? AND ? 
             ORDER BY i.date DESC",
            [$dateFrom, $dateTo]
        );
        $summary['total_sales'] = 0;
        $summary['total_paid'] = 0;
        $summary['total_remaining'] = 0;
        $summary['cash_sales'] = 0;
        $summary['credit_sales'] = 0;
        $summary['count'] = count($data);
        foreach ($data as $item) {
            $summary['total_sales'] += $item['total_amount'];
            $summary['total_paid'] += $item['paid_amount'];
            $summary['total_remaining'] += $item['remaining_amount'];
            if ($item['remaining_amount'] > 0) {
                $summary['credit_sales']++;
            } else {
                $summary['cash_sales']++;
            }
        }
        $summary['avg_invoice'] = $summary['count'] > 0 ? $summary['total_sales'] / $summary['count'] : 0;
        break;
    
    case 'purchases':
        $data = getRows(
            "SELECT i.*, s.name as supplier_name 
             FROM invoices i 
             LEFT JOIN suppliers s ON i.supplier_id = s.id
             WHERE i.type = 'purchase' AND i.date BETWEEN ? AND ? 
             ORDER BY i.date DESC",
            [$dateFrom, $dateTo]
        );
        $summary['total_purchases'] = 0;
        $summary['total_paid'] = 0;
        $summary['total_remaining'] = 0;
        $summary['cash_purchases'] = 0;
        $summary['credit_purchases'] = 0;
        $summary['count'] = count($data);
        foreach ($data as $item) {
            $summary['total_purchases'] += $item['total_amount'];
            $summary['total_paid'] += $item['paid_amount'];
            $summary['total_remaining'] += $item['remaining_amount'];
            if ($item['remaining_amount'] > 0) {
                $summary['credit_purchases']++;
            } else {
                $summary['cash_purchases']++;
            }
        }
        break;
        
    case 'profit':
        // Calculate profit per item sold using unit_price
        $data = getRows(
            "SELECT 
                i.invoice_number, i.date, 
                ii.quantity, ii.unit_price as sale_price,
                (ii.unit_price * ii.quantity) as total_sale,
                p.name as product_name, 
                COALESCE(p.cost_price, 0) as cost_price,
                (ii.unit_price - COALESCE(p.cost_price, 0)) * ii.quantity as profit
             FROM invoice_items ii
             JOIN invoices i ON ii.invoice_id = i.id
             JOIN products p ON ii.product_id = p.id
             WHERE i.type = 'sale' AND i.date BETWEEN ? AND ?
             ORDER BY i.date DESC",
            [$dateFrom, $dateTo]
        );
        $summary['total_profit'] = 0;
        $summary['total_revenue'] = 0;
        $summary['total_cost'] = 0;
        $summary['items_sold'] = 0;
        foreach ($data as $item) {
            $profit = floatval($item['profit']);
            $cost = floatval($item['cost_price']) * floatval($item['quantity']);
            $revenue = floatval($item['sale_price']) * floatval($item['quantity']);
            $summary['total_profit'] += $profit;
            $summary['total_revenue'] += $revenue;
            $summary['total_cost'] += $cost;
            $summary['items_sold'] += intval($item['quantity']);
        }
        $summary['profit_margin'] = $summary['total_revenue'] > 0 ? 
            ($summary['total_profit'] / $summary['total_revenue']) * 100 : 0;
        break;
        
    case 'returns':
    case 'customer_returns':
    case 'supplier_returns':
        $typeFilter = '';
        if ($reportType == 'customer_returns') $typeFilter = " AND type = 'customer'";
        if ($reportType == 'supplier_returns') $typeFilter = " AND type = 'supplier'";
        
        $data = getRows(
            "SELECT * FROM returns WHERE return_date BETWEEN ? AND ? {$typeFilter} ORDER BY return_date DESC",
            [$dateFrom, $dateTo]
        );
        $summary['customer_returns'] = 0;
        $summary['supplier_returns'] = 0;
        $summary['customer_value'] = 0;
        $summary['supplier_value'] = 0;
        $summary['total_value'] = 0;
        $summary['cash_refund'] = 0;
        $summary['deducted'] = 0;
        $summary['count'] = count($data);
        foreach ($data as $item) {
            $summary['total_value'] += $item['total_amount'];
            $summary['cash_refund'] += $item['cash_refund'];
            $summary['deducted'] += $item['deducted_from_balance'];
            if ($item['type'] == 'customer') {
                $summary['customer_returns']++;
                $summary['customer_value'] += $item['total_amount'];
            } else {
                $summary['supplier_returns']++;
                $summary['supplier_value'] += $item['total_amount'];
            }
        }
        break;
        
    case 'installments':
    case 'customer_installments':
    case 'supplier_installments':
        $typeFilter = '';
        if ($reportType == 'customer_installments') $typeFilter = " WHERE ip.type = 'customer'";
        if ($reportType == 'supplier_installments') $typeFilter = " WHERE ip.type = 'supplier'";
        
        $data = getRows(
            "SELECT ip.*, 
                (SELECT COUNT(*) FROM installment_payments WHERE plan_id = ip.id AND status = 'paid') as paid_count,
                (SELECT COUNT(*) FROM installment_payments WHERE plan_id = ip.id AND status = 'overdue') as overdue_count
             FROM installment_plans ip {$typeFilter} ORDER BY ip.created_at DESC"
        );
        $summary['active_plans'] = 0;
        $summary['completed_plans'] = 0;
        $summary['customer_plans'] = 0;
        $summary['supplier_plans'] = 0;
        $summary['customer_amount'] = 0;
        $summary['supplier_amount'] = 0;
        $summary['total_amount'] = 0;
        $summary['total_paid'] = 0;
        $summary['total_remaining'] = 0;
        $summary['overdue_count'] = 0;
        $summary['count'] = count($data);
        foreach ($data as $item) {
            $summary['total_amount'] += $item['total_amount'];
            $summary['total_paid'] += $item['paid_amount'];
            $summary['total_remaining'] += $item['remaining_amount'];
            $summary['overdue_count'] += $item['overdue_count'];
            if ($item['status'] == 'active') {
                $summary['active_plans']++;
            } else {
                $summary['completed_plans']++;
            }
            if ($item['type'] == 'customer') {
                $summary['customer_plans']++;
                $summary['customer_amount'] += $item['remaining_amount'];
            } else {
                $summary['supplier_plans']++;
                $summary['supplier_amount'] += $item['remaining_amount'];
            }
        }
        break;
        
    case 'customers':
        $data = getRows("SELECT * FROM customers ORDER BY balance ASC");
        $summary['total_customers'] = count($data);
        $summary['customers_with_debt'] = 0;
        $summary['total_debt'] = 0;
        $summary['total_credit'] = 0;
        foreach ($data as $item) {
            if ($item['balance'] < 0) {
                $summary['customers_with_debt']++;
                $summary['total_debt'] += abs($item['balance']);
            } else {
                $summary['total_credit'] += $item['balance'];
            }
        }
        break;
        
    case 'suppliers':
        $data = getRows("SELECT * FROM suppliers ORDER BY balance DESC");
        $summary['total_suppliers'] = count($data);
        $summary['suppliers_we_owe'] = 0;
        $summary['total_we_owe'] = 0;
        $summary['total_they_owe'] = 0;
        foreach ($data as $item) {
            if ($item['balance'] > 0) {
                $summary['suppliers_we_owe']++;
                $summary['total_we_owe'] += $item['balance'];
            } else {
                $summary['total_they_owe'] += abs($item['balance']);
            }
        }
        break;
        
    case 'payments':
        // Customer payments
        $customerPayments = getRows(
            "SELECT 'customer' as type, c.name, p.amount, p.payment_method, p.payment_date, p.old_balance, p.new_balance
             FROM customer_payments p
             JOIN customers c ON p.customer_id = c.id
             WHERE p.payment_date BETWEEN ? AND ?",
            [$dateFrom, $dateTo]
        );
        // Supplier payments  
        $supplierPayments = getRows(
            "SELECT 'supplier' as type, s.name, p.amount, p.payment_method, p.payment_date, p.old_balance, p.new_balance
             FROM supplier_payments p
             JOIN suppliers s ON p.supplier_id = s.id
             WHERE p.payment_date BETWEEN ? AND ?",
            [$dateFrom, $dateTo]
        );
        $data = array_merge($customerPayments, $supplierPayments);
        // Sort by date
        usort($data, function($a, $b) {
            return strtotime($b['payment_date']) - strtotime($a['payment_date']);
        });
        $summary['customer_payments'] = 0;
        $summary['supplier_payments'] = 0;
        $summary['total_collected_from_customers'] = 0;
        $summary['total_paid_to_suppliers'] = 0;
        $summary['count'] = count($data);
        foreach ($customerPayments as $p) {
            $summary['customer_payments']++;
            $summary['total_collected_from_customers'] += $p['amount'];
        }
        foreach ($supplierPayments as $p) {
            $summary['supplier_payments']++;
            $summary['total_paid_to_suppliers'] += $p['amount'];
        }
        break;
        
    case 'daily':
        // Daily summary for the date range
        $salesData = getRow(
            "SELECT 
                COUNT(*) as sale_count,
                COALESCE(SUM(total_amount), 0) as total_sales,
                COALESCE(SUM(paid_amount), 0) as total_paid
             FROM invoices WHERE type = 'sale' AND date BETWEEN ? AND ?",
            [$dateFrom, $dateTo]
        );
        $purchasesData = getRow(
            "SELECT 
                COUNT(*) as purchase_count,
                COALESCE(SUM(total_amount), 0) as total_purchases,
                COALESCE(SUM(paid_amount), 0) as total_paid
             FROM invoices WHERE type = 'purchase' AND date BETWEEN ? AND ?",
            [$dateFrom, $dateTo]
        );
        $returnsData = getRow(
            "SELECT 
                COUNT(*) as return_count,
                COALESCE(SUM(total_amount), 0) as total_returns
             FROM returns WHERE return_date BETWEEN ? AND ?",
            [$dateFrom, $dateTo]
        );
        
        // Safe access with defaults
        $salesCount = $salesData ? ($salesData['sale_count'] ?? 0) : 0;
        $totalSales = $salesData ? ($salesData['total_sales'] ?? 0) : 0;
        $salesPaid = $salesData ? ($salesData['total_paid'] ?? 0) : 0;
        $purchasesCount = $purchasesData ? ($purchasesData['purchase_count'] ?? 0) : 0;
        $totalPurchases = $purchasesData ? ($purchasesData['total_purchases'] ?? 0) : 0;
        $purchasesPaid = $purchasesData ? ($purchasesData['total_paid'] ?? 0) : 0;
        $returnsCount = $returnsData ? ($returnsData['return_count'] ?? 0) : 0;
        $totalReturns = $returnsData ? ($returnsData['total_returns'] ?? 0) : 0;
        
        // Calculate cash in/out
        $cashIn = floatval($salesPaid);
        $cashOut = floatval($purchasesPaid);
        
        $summary = [
            'sales_count' => $salesCount,
            'total_sales' => $totalSales,
            'sales_paid' => $salesPaid,
            'purchases_count' => $purchasesCount,
            'total_purchases' => $totalPurchases,
            'purchases_paid' => $purchasesPaid,
            'returns_count' => $returnsCount,
            'total_returns' => $totalReturns,
            'payments_from_customers' => 0,
            'payments_to_suppliers' => 0,
            'installment_payments' => 0,
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'net_cash' => $cashIn - $cashOut
        ];
        $data = [];
        break;
}

// Pagination for report data
$perPage = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$totalItems = count($data);
$totalPages = ceil($totalItems / $perPage);
$offset = ($page - 1) * $perPage;

// Slice data for current page (keep original for summary calculations)
$paginatedData = array_slice($data, $offset, $perPage);

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>📈 التقارير</span>
            <button onclick="window.print()" class="btn btn-secondary noprint">🖨️ طباعة التقرير</button>
        </div>
        
        <div class="card-body">
            <!-- Filter Form -->
            <form method="GET" class="mb-3 noprint">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">نوع التقرير</label>
                        <select name="type" class="form-control" onchange="this.form.submit()">
                            <option value="daily" <?php echo $reportType == 'daily' ? 'selected' : ''; ?>>📊 ملخص يومي</option>
                            <option value="sales" <?php echo $reportType == 'sales' ? 'selected' : ''; ?>>💰 المبيعات</option>
                            <option value="purchases" <?php echo $reportType == 'purchases' ? 'selected' : ''; ?>>🛒 المشتريات</option>
                            <option value="profit" <?php echo $reportType == 'profit' ? 'selected' : ''; ?>>📈 الأرباح التقديرية</option>
                            <option value="returns" <?php echo $reportType == 'returns' ? 'selected' : ''; ?>>🔄 كل المرتجعات</option>
                            <option value="customer_returns" <?php echo $reportType == 'customer_returns' ? 'selected' : ''; ?>>👤 مرتجعات العملاء</option>
                            <option value="supplier_returns" <?php echo $reportType == 'supplier_returns' ? 'selected' : ''; ?>>🚚 مرتجعات الموردين</option>
                            <option value="installments" <?php echo $reportType == 'installments' ? 'selected' : ''; ?>>📅 كل الأقساط</option>
                            <option value="customer_installments" <?php echo $reportType == 'customer_installments' ? 'selected' : ''; ?>>👤 أقساط العملاء (لي)</option>
                            <option value="supplier_installments" <?php echo $reportType == 'supplier_installments' ? 'selected' : ''; ?>>🚚 أقساط الموردين (عليّ)</option>
                            <option value="customers" <?php echo $reportType == 'customers' ? 'selected' : ''; ?>>👥 حسابات العملاء</option>
                            <option value="suppliers" <?php echo $reportType == 'suppliers' ? 'selected' : ''; ?>>🚚 حسابات الموردين</option>
                            <option value="stock" <?php echo $reportType == 'stock' ? 'selected' : ''; ?>>📦 المخزون</option>
                        </select>
                    </div>
                    
                    <?php if (!in_array($reportType, ['stock', 'customers', 'suppliers', 'installments'])): ?>
                    <div class="form-group">
                        <label class="form-label">من تاريخ</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo $dateFrom; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">إلى تاريخ</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo $dateTo; ?>">
                    </div>
                    
                    <div class="form-group" style="align-self: flex-end;">
                        <button type="submit" class="btn btn-primary">عرض</button>
                    </div>
                    <?php endif; ?>
                </div>
            </form>
            
            <!-- Count Bar -->
            <div class="search-results-count">
                <span>📊 عدد النتائج: <strong><?php echo $totalItems; ?></strong> سجل</span>
                <div class="pagination" style="display: flex; align-items: center; gap: 8px;">
                    <?php if ($page > 1): ?>
                    <a href="?type=<?php echo urlencode($reportType); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>&page=<?php echo $page - 1; ?>" class="btn btn-secondary btn-sm">← السابق</a>
                    <?php endif; ?>
                    <span class="badge badge-info">صفحة <?php echo $page; ?> من <?php echo max(1, $totalPages); ?></span>
                    <?php if ($page < $totalPages): ?>
                    <a href="?type=<?php echo urlencode($reportType); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>&page=<?php echo $page + 1; ?>" class="btn btn-secondary btn-sm">التالي →</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- PRINTABLE CONTENT START -->
            <div class="report-print" id="report-content">
                <!-- Report Header for Print -->
                <div class="report-header">
                    <h2><?php echo getSetting('store_name'); ?></h2>
                    <p><?php echo getSetting('store_address'); ?> | <?php echo getSetting('store_phone'); ?></p>
                    <h3><?php 
                        $typeNames = [
                            'daily' => 'ملخص يومي',
                            'sales' => 'تقرير المبيعات',
                            'purchases' => 'تقرير المشتريات',
                            'profit' => 'تقرير الأرباح التقديرية',
                            'returns' => 'تقرير المرتجعات',
                            'installments' => 'تقرير الأقساط',
                            'payments' => 'تقرير التحصيلات والمدفوعات',
                            'customers' => 'تقرير حسابات العملاء',
                            'suppliers' => 'تقرير حسابات الموردين',
                            'stock' => 'تقرير المخزون (جرد)'
                        ];
                        echo $typeNames[$reportType] ?? 'تقرير';
                    ?></h3>
                    <?php if (!in_array($reportType, ['stock', 'customers', 'suppliers', 'installments'])): ?>
                    <p>من <?php echo $dateFrom; ?> إلى <?php echo $dateTo; ?></p>
                    <?php endif; ?>
                    <p>تاريخ الطباعة: <?php echo date('Y-m-d H:i'); ?></p>
                </div>
                
                <hr>
            
            <!-- Report Content Based on Type -->
            
            <?php if ($reportType == 'daily'): ?>
                <!-- Daily Summary -->
                <div class="summary-cards">
                    <div class="summary-card success">
                        <div class="summary-icon">💰</div>
                        <div class="summary-details">
                            <div class="summary-title">المبيعات</div>
                            <div class="summary-value"><?php echo formatCurrency($summary['total_sales']); ?></div>
                            <div class="summary-sub"><?php echo $summary['sales_count']; ?> فاتورة | محصل: <?php echo formatCurrency($summary['sales_paid']); ?></div>
                        </div>
                    </div>
                    
                    <div class="summary-card warning">
                        <div class="summary-icon">🛒</div>
                        <div class="summary-details">
                            <div class="summary-title">المشتريات</div>
                            <div class="summary-value"><?php echo formatCurrency($summary['total_purchases']); ?></div>
                            <div class="summary-sub"><?php echo $summary['purchases_count']; ?> فاتورة | مدفوع: <?php echo formatCurrency($summary['purchases_paid']); ?></div>
                        </div>
                    </div>
                    
                    <div class="summary-card info">
                        <div class="summary-icon">🔄</div>
                        <div class="summary-details">
                            <div class="summary-title">المرتجعات</div>
                            <div class="summary-value"><?php echo formatCurrency($summary['total_returns']); ?></div>
                            <div class="summary-sub"><?php echo $summary['returns_count']; ?> مرتجع</div>
                        </div>
                    </div>
                    
                    <div class="summary-card purple">
                        <div class="summary-icon">📅</div>
                        <div class="summary-details">
                            <div class="summary-title">أقساط محصلة</div>
                            <div class="summary-value"><?php echo formatCurrency($summary['installment_payments']); ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="cash-flow-summary">
                    <h4>💵 ملخص التدفق النقدي</h4>
                    <div class="cash-flow-grid">
                        <div class="cash-in">
                            <span class="label">📥 الداخل (مقبوضات)</span>
                            <span class="value"><?php echo formatCurrency($summary['cash_in']); ?></span>
                            <small>مبيعات: <?php echo formatCurrency($summary['sales_paid']); ?> + تحصيلات: <?php echo formatCurrency($summary['payments_from_customers']); ?> + أقساط: <?php echo formatCurrency($summary['installment_payments']); ?></small>
                        </div>
                        <div class="cash-out">
                            <span class="label">📤 الخارج (مدفوعات)</span>
                            <span class="value"><?php echo formatCurrency($summary['cash_out']); ?></span>
                            <small>مشتريات: <?php echo formatCurrency($summary['purchases_paid']); ?> + مدفوعات للموردين: <?php echo formatCurrency($summary['payments_to_suppliers']); ?></small>
                        </div>
                        <div class="net-cash <?php echo $summary['net_cash'] >= 0 ? 'positive' : 'negative'; ?>">
                            <span class="label">💵 صافي النقد</span>
                            <span class="value"><?php echo formatCurrency($summary['net_cash']); ?></span>
                        </div>
                    </div>
                </div>
                
            <?php elseif ($reportType == 'sales'): ?>
                <div class="report-summary">
                    <div class="stat-box"><span class="stat-label">عدد الفواتير</span><span class="stat-value"><?php echo $summary['count']; ?></span></div>
                    <div class="stat-box success"><span class="stat-label">إجمالي المبيعات</span><span class="stat-value"><?php echo formatCurrency($summary['total_sales']); ?></span></div>
                    <div class="stat-box info"><span class="stat-label">المحصّل</span><span class="stat-value"><?php echo formatCurrency($summary['total_paid']); ?></span></div>
                    <div class="stat-box warning"><span class="stat-label">المتبقي (آجل)</span><span class="stat-value"><?php echo formatCurrency($summary['total_remaining']); ?></span></div>
                    <div class="stat-box"><span class="stat-label">فواتير كاش</span><span class="stat-value"><?php echo $summary['cash_sales']; ?></span></div>
                    <div class="stat-box"><span class="stat-label">فواتير آجل</span><span class="stat-value"><?php echo $summary['credit_sales']; ?></span></div>
                    <div class="stat-box"><span class="stat-label">متوسط الفاتورة</span><span class="stat-value"><?php echo formatCurrency($summary['avg_invoice']); ?></span></div>
                </div>
                
                <table class="table table-bordered report-table">
                    <thead>
                        <tr>
                            <th>رقم الفاتورة</th>
                            <th>التاريخ</th>
                            <th>العميل</th>
                            <th>طريقة الدفع</th>
                            <th>الإجمالي</th>
                            <th>المدفوع</th>
                            <th>المتبقي</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paginatedData as $row): ?>
                        <tr>
                            <td><?php echo $row['invoice_number']; ?></td>
                            <td><?php echo $row['date']; ?></td>
                            <td><?php echo $row['customer_name'] ?: 'عميل نقدي'; ?></td>
                            <td><?php echo $row['payment_method']; ?></td>
                            <td><?php echo formatCurrency($row['total_amount']); ?></td>
                            <td class="text-success"><?php echo formatCurrency($row['paid_amount']); ?></td>
                            <td class="<?php echo $row['remaining_amount'] > 0 ? 'text-danger' : ''; ?>"><?php echo formatCurrency($row['remaining_amount']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
            <?php elseif ($reportType == 'purchases'): ?>
                <div class="report-summary">
                    <div class="stat-box"><span class="stat-label">عدد الفواتير</span><span class="stat-value"><?php echo $summary['count']; ?></span></div>
                    <div class="stat-box warning"><span class="stat-label">إجمالي المشتريات</span><span class="stat-value"><?php echo formatCurrency($summary['total_purchases']); ?></span></div>
                    <div class="stat-box info"><span class="stat-label">المدفوع</span><span class="stat-value"><?php echo formatCurrency($summary['total_paid']); ?></span></div>
                    <div class="stat-box danger"><span class="stat-label">المتبقي للموردين</span><span class="stat-value"><?php echo formatCurrency($summary['total_remaining']); ?></span></div>
                    <div class="stat-box"><span class="stat-label">فواتير كاش</span><span class="stat-value"><?php echo $summary['cash_purchases']; ?></span></div>
                    <div class="stat-box"><span class="stat-label">فواتير آجل</span><span class="stat-value"><?php echo $summary['credit_purchases']; ?></span></div>
                </div>
                
                <table class="table table-bordered report-table">
                    <thead>
                        <tr>
                            <th>رقم الفاتورة</th>
                            <th>التاريخ</th>
                            <th>المورد</th>
                            <th>طريقة الدفع</th>
                            <th>الإجمالي</th>
                            <th>المدفوع</th>
                            <th>المتبقي</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paginatedData as $row): ?>
                        <tr>
                            <td><?php echo $row['invoice_number']; ?></td>
                            <td><?php echo $row['date']; ?></td>
                            <td><?php echo $row['supplier_name'] ?: '-'; ?></td>
                            <td><?php echo $row['payment_method']; ?></td>
                            <td><?php echo formatCurrency($row['total_amount']); ?></td>
                            <td class="text-success"><?php echo formatCurrency($row['paid_amount']); ?></td>
                            <td class="<?php echo $row['remaining_amount'] > 0 ? 'text-danger' : ''; ?>"><?php echo formatCurrency($row['remaining_amount']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            <?php elseif ($reportType == 'profit'): ?>
                <div class="report-summary">
                    <div class="stat-box success"><span class="stat-label">📈 إجمالي الأرباح</span><span class="stat-value"><?php echo formatCurrency($summary['total_profit']); ?></span></div>
                    <div class="stat-box"><span class="stat-label">إجمالي المبيعات</span><span class="stat-value"><?php echo formatCurrency($summary['total_revenue']); ?></span></div>
                    <div class="stat-box warning"><span class="stat-label">إجمالي التكلفة</span><span class="stat-value"><?php echo formatCurrency($summary['total_cost']); ?></span></div>
                    <div class="stat-box info"><span class="stat-label">هامش الربح %</span><span class="stat-value"><?php echo number_format($summary['profit_margin'], 1); ?>%</span></div>
                    <div class="stat-box"><span class="stat-label">عدد القطع المباعة</span><span class="stat-value"><?php echo $summary['items_sold']; ?></span></div>
                </div>
                
                <p class="text-muted" style="margin-bottom:15px;">* الأرباح تحسب بناءً على (سعر البيع - سعر التكلفة الحالي) × الكمية</p>
                
                <table class="table table-bordered report-table">
                    <thead>
                        <tr>
                            <th>رقم الفاتورة</th>
                            <th>التاريخ</th>
                            <th>الصنف</th>
                            <th>الكمية</th>
                            <th>سعر البيع</th>
                            <th>سعر التكلفة</th>
                            <th>الربح/قطعة</th>
                            <th>إجمالي الربح</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paginatedData as $row): 
                            $profitPerItem = floatval($row['sale_price']) / floatval($row['quantity']) - floatval($row['cost_price']);
                        ?>
                        <tr>
                            <td><?php echo $row['invoice_number']; ?></td>
                            <td><?php echo $row['date']; ?></td>
                            <td><?php echo $row['product_name']; ?></td>
                            <td><?php echo $row['quantity']; ?></td>
                            <td><?php echo formatCurrency($row['sale_price']); ?></td>
                            <td><?php echo formatCurrency($row['cost_price']); ?></td>
                            <td><?php echo formatCurrency($profitPerItem); ?></td>
                            <td class="text-success"><strong><?php echo formatCurrency($row['profit']); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
            <?php elseif (in_array($reportType, ['returns', 'customer_returns', 'supplier_returns'])): ?>
                <div class="report-summary">
                    <div class="stat-box"><span class="stat-label">عدد المرتجعات</span><span class="stat-value"><?php echo $summary['count']; ?></span></div>
                    <div class="stat-box warning"><span class="stat-label">إجمالي قيمة المرتجعات</span><span class="stat-value"><?php echo formatCurrency($summary['total_value']); ?></span></div>
                    <div class="stat-box info"><span class="stat-label">مرتجعات عملاء</span><span class="stat-value"><?php echo $summary['customer_returns']; ?></span></div>
                    <div class="stat-box"><span class="stat-label">مرتجعات موردين</span><span class="stat-value"><?php echo $summary['supplier_returns']; ?></span></div>
                    <div class="stat-box danger"><span class="stat-label">مسترد نقداً</span><span class="stat-value"><?php echo formatCurrency($summary['cash_refund']); ?></span></div>
                    <div class="stat-box success"><span class="stat-label">مخصوم من الحساب</span><span class="stat-value"><?php echo formatCurrency($summary['deducted']); ?></span></div>
                </div>
                
                <table class="table table-bordered report-table">
                    <thead>
                        <tr>
                            <th>رقم المرتجع</th>
                            <th>التاريخ</th>
                            <th>النوع</th>
                            <th>الاسم</th>
                            <th>القيمة</th>
                            <th>مسترد نقداً</th>
                            <th>مخصوم</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paginatedData as $row): ?>
                        <tr>
                            <td><?php echo $row['return_number']; ?></td>
                            <td><?php echo $row['return_date']; ?></td>
                            <td><?php echo $row['type'] == 'customer' ? '👤 عميل' : '🚚 مورد'; ?></td>
                            <td><?php echo $row['customer_name'] ?: $row['supplier_name']; ?></td>
                            <td><?php echo formatCurrency($row['total_amount']); ?></td>
                            <td><?php echo formatCurrency($row['cash_refund']); ?></td>
                            <td><?php echo formatCurrency($row['deducted_from_balance']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
            <?php elseif (in_array($reportType, ['installments', 'customer_installments', 'supplier_installments'])): ?>
                <div class="report-summary">
                    <div class="stat-box"><span class="stat-label">عدد الخطط</span><span class="stat-value"><?php echo $summary['count']; ?></span></div>
                    <div class="stat-box success"><span class="stat-label">خطط نشطة</span><span class="stat-value"><?php echo $summary['active_plans']; ?></span></div>
                    <div class="stat-box info"><span class="stat-label">خطط مكتملة</span><span class="stat-value"><?php echo $summary['completed_plans']; ?></span></div>
                    <div class="stat-box warning"><span class="stat-label">إجمالي الأقساط</span><span class="stat-value"><?php echo formatCurrency($summary['total_amount']); ?></span></div>
                    <div class="stat-box"><span class="stat-label">المحصّل</span><span class="stat-value"><?php echo formatCurrency($summary['total_paid']); ?></span></div>
                    <div class="stat-box danger"><span class="stat-label">المتبقي</span><span class="stat-value"><?php echo formatCurrency($summary['total_remaining']); ?></span></div>
                    <div class="stat-box danger"><span class="stat-label">أقساط متأخرة</span><span class="stat-value"><?php echo $summary['overdue_count']; ?></span></div>
                </div>
                
                <table class="table table-bordered report-table">
                    <thead>
                        <tr>
                            <th>النوع</th>
                            <th>الاسم</th>
                            <th>الإجمالي</th>
                            <th>المدفوع</th>
                            <th>المتبقي</th>
                            <th>أقساط مدفوعة</th>
                            <th>متأخرة</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paginatedData as $row): ?>
                        <tr>
                            <td><?php echo $row['type'] == 'customer' ? '👤 عميل' : '🚚 مورد'; ?></td>
                            <td><?php echo $row['entity_name']; ?></td>
                            <td><?php echo formatCurrency($row['total_amount']); ?></td>
                            <td class="text-success"><?php echo formatCurrency($row['paid_amount']); ?></td>
                            <td class="text-danger"><?php echo formatCurrency($row['remaining_amount']); ?></td>
                            <td><?php echo $row['paid_count']; ?></td>
                            <td class="<?php echo $row['overdue_count'] > 0 ? 'text-danger' : ''; ?>"><?php echo $row['overdue_count']; ?></td>
                            <td>
                                <span class="badge badge-<?php echo $row['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo $row['status'] == 'active' ? 'نشط' : 'مكتمل'; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
            <?php elseif ($reportType == 'payments'): ?>
                <div class="report-summary">
                    <div class="stat-box"><span class="stat-label">عدد العمليات</span><span class="stat-value"><?php echo $summary['count']; ?></span></div>
                    <div class="stat-box success"><span class="stat-label">تحصيلات من العملاء</span><span class="stat-value"><?php echo formatCurrency($summary['total_collected_from_customers']); ?></span></div>
                    <div class="stat-box warning"><span class="stat-label">مدفوعات للموردين</span><span class="stat-value"><?php echo formatCurrency($summary['total_paid_to_suppliers']); ?></span></div>
                    <div class="stat-box info"><span class="stat-label">عدد تحصيلات العملاء</span><span class="stat-value"><?php echo $summary['customer_payments']; ?></span></div>
                    <div class="stat-box"><span class="stat-label">عدد مدفوعات الموردين</span><span class="stat-value"><?php echo $summary['supplier_payments']; ?></span></div>
                </div>
                
                <table class="table table-bordered report-table">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>النوع</th>
                            <th>الاسم</th>
                            <th>المبلغ</th>
                            <th>طريقة الدفع</th>
                            <th>الرصيد قبل</th>
                            <th>الرصيد بعد</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paginatedData as $row): ?>
                        <tr>
                            <td><?php echo $row['payment_date']; ?></td>
                            <td><?php echo $row['type'] == 'customer' ? '👤 تحصيل' : '🚚 دفع'; ?></td>
                            <td><?php echo $row['name']; ?></td>
                            <td class="<?php echo $row['type'] == 'customer' ? 'text-success' : 'text-danger'; ?>">
                                <?php echo formatCurrency($row['amount']); ?>
                            </td>
                            <td><?php echo $row['payment_method']; ?></td>
                            <td><?php echo formatCurrency($row['old_balance']); ?></td>
                            <td><?php echo formatCurrency($row['new_balance']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
            <?php elseif ($reportType == 'customers'): ?>
                <div class="report-summary">
                    <div class="stat-box"><span class="stat-label">إجمالي العملاء</span><span class="stat-value"><?php echo $summary['total_customers']; ?></span></div>
                    <div class="stat-box danger"><span class="stat-label">عملاء عليهم ديون</span><span class="stat-value"><?php echo $summary['customers_with_debt']; ?></span></div>
                    <div class="stat-box warning"><span class="stat-label">إجمالي المستحق علينا</span><span class="stat-value"><?php echo formatCurrency($summary['total_debt']); ?></span></div>
                    <div class="stat-box success"><span class="stat-label">رصيد لصالحنا</span><span class="stat-value"><?php echo formatCurrency($summary['total_credit']); ?></span></div>
                </div>
                
                <table class="table table-bordered report-table">
                    <thead>
                        <tr>
                            <th>العميل</th>
                            <th>الهاتف</th>
                            <th>العنوان</th>
                            <th>الرصيد</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paginatedData as $row): ?>
                        <tr>
                            <td><?php echo $row['name']; ?></td>
                            <td><?php echo $row['phone']; ?></td>
                            <td><?php echo $row['address']; ?></td>
                            <td class="<?php echo $row['balance'] < 0 ? 'text-danger' : 'text-success'; ?>">
                                <strong><?php echo formatCurrency(abs($row['balance'])); ?></strong>
                            </td>
                            <td>
                                <?php if ($row['balance'] < 0): ?>
                                    <span class="badge badge-danger">عليه دين</span>
                                <?php elseif ($row['balance'] > 0): ?>
                                    <span class="badge badge-success">له رصيد</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">متزن</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
            <?php elseif ($reportType == 'suppliers'): ?>
                <div class="report-summary">
                    <div class="stat-box"><span class="stat-label">إجمالي الموردين</span><span class="stat-value"><?php echo $summary['total_suppliers']; ?></span></div>
                    <div class="stat-box danger"><span class="stat-label">موردين لهم مستحقات</span><span class="stat-value"><?php echo $summary['suppliers_we_owe']; ?></span></div>
                    <div class="stat-box warning"><span class="stat-label">إجمالي المستحق لهم</span><span class="stat-value"><?php echo formatCurrency($summary['total_we_owe']); ?></span></div>
                    <div class="stat-box success"><span class="stat-label">رصيد لصالحنا</span><span class="stat-value"><?php echo formatCurrency($summary['total_they_owe']); ?></span></div>
                </div>
                
                <table class="table table-bordered report-table">
                    <thead>
                        <tr>
                            <th>المورد</th>
                            <th>الهاتف</th>
                            <th>العنوان</th>
                            <th>الرصيد</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paginatedData as $row): ?>
                        <tr>
                            <td><?php echo $row['name']; ?></td>
                            <td><?php echo $row['phone']; ?></td>
                            <td><?php echo $row['address']; ?></td>
                            <td class="<?php echo $row['balance'] > 0 ? 'text-danger' : ($row['balance'] < 0 ? 'text-success' : ''); ?>">
                                <strong><?php echo formatCurrency(abs($row['balance'])); ?></strong>
                            </td>
                            <td>
                                <?php if ($row['balance'] > 0): ?>
                                    <span class="badge badge-danger">له مستحقات</span>
                                <?php elseif ($row['balance'] < 0): ?>
                                    <span class="badge badge-success">علينا رصيد</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">متزن</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
            <?php elseif ($reportType == 'stock'): ?>
                <div class="report-summary">
                    <div class="stat-box"><span class="stat-label">عدد الأصناف</span><span class="stat-value"><?php echo $summary['total_items']; ?></span></div>
                    <div class="stat-box warning"><span class="stat-label">قيمة المخزون (تكلفة)</span><span class="stat-value"><?php echo formatCurrency($summary['total_value_cost']); ?></span></div>
                    <div class="stat-box success"><span class="stat-label">قيمة المخزون (بيع)</span><span class="stat-value"><?php echo formatCurrency($summary['total_value_sale']); ?></span></div>
                    <div class="stat-box info"><span class="stat-label">الربح المتوقع</span><span class="stat-value"><?php echo formatCurrency($summary['expected_profit']); ?></span></div>
                    <div class="stat-box danger"><span class="stat-label">نفد من المخزون</span><span class="stat-value"><?php echo $summary['out_of_stock']; ?></span></div>
                    <div class="stat-box warning"><span class="stat-label">مخزون منخفض</span><span class="stat-value"><?php echo $summary['low_stock']; ?></span></div>
                </div>
                
                <table class="table table-bordered report-table">
                    <thead>
                        <tr>
                            <th>الكود</th>
                            <th>الصنف</th>
                            <th>الكمية</th>
                            <th>سعر التكلفة</th>
                            <th>سعر البيع</th>
                            <th>قيمة المخزون</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paginatedData as $row): 
                            $costPrice = floatval($row['cost_price'] ?? $row['price'] * 0.7);
                        ?>
                        <tr>
                            <td><?php echo $row['code']; ?></td>
                            <td><?php echo $row['name']; ?></td>
                            <td>
                                <strong class="<?php echo $row['stock_quantity'] == 0 ? 'text-danger' : ($row['stock_quantity'] <= $row['min_stock_level'] ? 'text-warning' : ''); ?>">
                                    <?php echo $row['stock_quantity']; ?>
                                </strong>
                            </td>
                            <td><?php echo formatCurrency($costPrice); ?></td>
                            <td><?php echo formatCurrency($row['price']); ?></td>
                            <td><?php echo formatCurrency($row['stock_quantity'] * $costPrice); ?></td>
                            <td>
                                <?php if ($row['stock_quantity'] == 0): ?>
                                    <span class="badge badge-danger">نفد</span>
                                <?php elseif ($row['stock_quantity'] <= $row['min_stock_level']): ?>
                                    <span class="badge badge-warning">منخفض</span>
                                <?php else: ?>
                                    <span class="badge badge-success">متوفر</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            </div>
            <!-- PRINTABLE CONTENT END -->
            
        </div>
    </div>
</div>

<style>
/* Report Styles */
.report-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 25px;
    padding: 15px;
    background: #f8fafc;
    border-radius: 12px;
}

.stat-box {
    flex: 1;
    min-width: 140px;
    background: white;
    padding: 15px;
    border-radius: 10px;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border-right: 4px solid #6b7280;
}

.stat-box.success { border-color: #10b981; }
.stat-box.warning { border-color: #f59e0b; }
.stat-box.danger { border-color: #ef4444; }
.stat-box.info { border-color: #3b82f6; }

.stat-label {
    display: block;
    color: #6b7280;
    font-size: 0.85rem;
    margin-bottom: 5px;
}

.stat-value {
    display: block;
    font-size: 1.4rem;
    font-weight: 700;
    color: #1f2937;
}

/* Summary Cards for Daily */
.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.summary-card {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    border-radius: 12px;
    color: white;
}

.summary-card.success { background: linear-gradient(135deg, #10b981, #059669); }
.summary-card.warning { background: linear-gradient(135deg, #f59e0b, #d97706); }
.summary-card.info { background: linear-gradient(135deg, #3b82f6, #2563eb); }
.summary-card.purple { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }

.summary-icon { font-size: 2.5rem; }
.summary-title { font-size: 0.9rem; opacity: 0.9; }
.summary-value { font-size: 1.8rem; font-weight: 700; }
.summary-sub { font-size: 0.8rem; opacity: 0.85; }

/* Cash Flow */
.cash-flow-summary {
    background: #f8fafc;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 25px;
}

.cash-flow-summary h4 {
    margin-bottom: 15px;
    color: #374151;
}

.cash-flow-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 15px;
}

.cash-in, .cash-out, .net-cash {
    padding: 15px;
    border-radius: 10px;
    text-align: center;
}

.cash-in {
    background: linear-gradient(135deg, #d1fae5, #a7f3d0);
    color: #065f46;
}

.cash-out {
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    color: #991b1b;
}

.net-cash.positive {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.net-cash.negative {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
}

.cash-in .label, .cash-out .label, .net-cash .label {
    display: block;
    font-size: 0.9rem;
    margin-bottom: 8px;
}

.cash-in .value, .cash-out .value, .net-cash .value {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
}

.cash-in small, .cash-out small {
    display: block;
    font-size: 0.75rem;
    margin-top: 8px;
    opacity: 0.8;
}

/* Report Table */
.report-table {
    margin-top: 20px;
}

.report-header {
    display: none;
}

/* Print Styles */
@media print {
    body * {
        visibility: visible !important;
    }
    
    .noprint, .navbar, .btn, form, hr { 
        display: none !important; 
    }
    
    .card { 
        border: none !important; 
        box-shadow: none !important; 
    }
    
    .container {
        max-width: 100% !important;
        padding: 0 !important;
    }
    
    .report-header {
        display: block !important;
        text-align: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #000;
    }
    
    .report-header h2 {
        font-size: 18pt;
        margin-bottom: 5px;
    }
    
    .report-header h3 {
        font-size: 14pt;
        margin: 10px 0;
    }
    
    .report-header p {
        font-size: 10pt;
        margin: 3px 0;
    }
    
    .report-summary, .summary-cards, .cash-flow-summary {
        page-break-inside: avoid;
    }
    
    .summary-card, .stat-box {
        background: #f0f0f0 !important;
        color: #000 !important;
        border: 1px solid #000 !important;
    }
    
    .cash-in, .cash-out, .net-cash {
        background: #f0f0f0 !important;
        color: #000 !important;
        border: 1px solid #000 !important;
    }
    
    table { 
        width: 100%; 
        border-collapse: collapse; 
    }
    
    th, td { 
        border: 1px solid #000 !important; 
        padding: 8px; 
        font-size: 10pt;
    }
    
    th {
        background: #e0e0e0 !important;
    }
    
    .badge {
        border: 1px solid #000;
        padding: 2px 5px;
    }
}
</style>

<?php include '../../includes/footer.php'; ?>
