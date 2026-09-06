<?php
/**
 * Statement of Account (كشف حساب شامل)
 */
require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requirePermission('reports.statement');

$type = $_GET['type'] ?? 'customer'; 
$id = intval($_GET['id'] ?? 0);
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

if (!$id) {
    die("لم يتم تحديد العميل أو المورد");
}

if (empty($dateFrom)) {
    if ($type === 'customer') {
        $firstInv = getRow("SELECT MIN(date) as min_date FROM invoices WHERE customer_id = ?", [$id]);
    } else {
        $firstInv = getRow("SELECT MIN(date) as min_date FROM invoices WHERE supplier_id = ?", [$id]);
    }
    $dateFrom = $firstInv['min_date'] ?? '2000-01-01';
}

$entity = null;
if ($type === 'customer') {
    $entity = getRow("SELECT * FROM customers WHERE id = ?", [$id]);
    $pageTitle = 'كشف حساب عميل: ' . $entity['name'];
} else {
    $entity = getRow("SELECT * FROM suppliers WHERE id = ?", [$id]);
    $pageTitle = 'كشف حساب مورد: ' . $entity['name'];
}

if (!$entity) {
    die("العميل أو المورد غير موجود");
}

// ==========================================
// 1. Calculate Opening Balance
// ==========================================
$openingBalance = 0;
if ($type === 'customer') {
    $invBefore = getRow("SELECT COALESCE(SUM(total_amount - discount), 0) as debit, COALESCE(SUM(paid_amount), 0) as credit FROM invoices WHERE type='sale' AND customer_id = ? AND date < ?", [$id, $dateFrom]) ?: ['debit'=>0, 'credit'=>0];
    $payBefore = getRow("SELECT COALESCE(SUM(amount), 0) as credit FROM payments WHERE type='customer' AND entity_id = ? AND payment_date < ?", [$id, $dateFrom]) ?: ['credit'=>0];
    $retBefore = getRow("SELECT COALESCE(SUM(deducted_from_balance), 0) as credit FROM returns WHERE type='customer' AND customer_id = ? AND return_date < ?", [$id, $dateFrom]) ?: ['credit'=>0];
    
    $openingDebit = floatval($invBefore['debit']);
    $openingCredit = floatval($invBefore['credit']) + floatval($payBefore['credit']) + floatval($retBefore['credit']);
    $openingBalance = $openingDebit - $openingCredit; 
} else {
    $invBefore = getRow("SELECT COALESCE(SUM(paid_amount), 0) as debit, COALESCE(SUM(total_amount - discount), 0) as credit FROM invoices WHERE type='purchase' AND supplier_id = ? AND date < ?", [$id, $dateFrom]) ?: ['debit'=>0, 'credit'=>0];
    $payBefore = getRow("SELECT COALESCE(SUM(amount), 0) as debit FROM payments WHERE type='supplier' AND entity_id = ? AND payment_date < ?", [$id, $dateFrom]) ?: ['debit'=>0];
    $retBefore = getRow("SELECT COALESCE(SUM(deducted_from_balance), 0) as debit FROM returns WHERE type='supplier' AND supplier_id = ? AND return_date < ?", [$id, $dateFrom]) ?: ['debit'=>0];
    
    $openingDebit = floatval($invBefore['debit']) + floatval($payBefore['debit']) + floatval($retBefore['debit']);
    $openingCredit = floatval($invBefore['credit']);
    $openingBalance = $openingCredit - $openingDebit;
}

// ==========================================
// 2. Fetch Transactions (Detailed)
// ==========================================
$transactions = [];
$totalPurchases = 0;
$totalPayments = 0;
$totalReturns = 0;

if ($type === 'customer') {
    $invoices = getRows("SELECT * FROM invoices WHERE type='sale' AND customer_id = ? AND date BETWEEN ? AND ?", [$id, $dateFrom, $dateTo]);
    foreach ($invoices as $inv) {
        $desc = "فاتورة بيع رقم {$inv['invoice_number']}";
        if ($inv['discount'] > 0) $desc .= " (إجمالي: {$inv['total_amount']}, خصم: {$inv['discount']})";
        else $desc .= " (إجمالي: {$inv['total_amount']})";
        if ($inv['paid_amount'] > 0) $desc .= " - دفع نقداً: {$inv['paid_amount']}";
        
        $debit = floatval($inv['total_amount'] - $inv['discount']);
        $credit = floatval($inv['paid_amount']);
        $transactions[] = [ 'date' => $inv['date'], 'time' => $inv['created_at'], 'ref' => $inv['invoice_number'], 'desc' => $desc, 'debit' => $debit, 'credit' => $credit ];
        $totalPurchases += $debit;
        $totalPayments += $credit;
    }
    
    $payments = getRows("SELECT * FROM payments WHERE type='customer' AND entity_id = ? AND payment_date BETWEEN ? AND ?", [$id, $dateFrom, $dateTo]);
    foreach ($payments as $pay) {
        $desc = "سداد نقدي ({$pay['payment_method']})";
        if (!empty($pay['notes'])) $desc .= " - {$pay['notes']}";
        
        $credit = floatval($pay['amount']);
        $transactions[] = [ 'date' => $pay['payment_date'], 'time' => $pay['created_at'], 'ref' => $pay['payment_number'], 'desc' => $desc, 'debit' => 0, 'credit' => $credit ];
        $totalPayments += $credit;
    }
    
    $returns = getRows("SELECT * FROM returns WHERE type='customer' AND customer_id = ? AND deducted_from_balance > 0 AND return_date BETWEEN ? AND ?", [$id, $dateFrom, $dateTo]);
    foreach ($returns as $ret) {
        $desc = "مرتجع بيع رقم {$ret['return_number']} (إجمالي المرتجع: {$ret['total_amount']}, رد نقدي: {$ret['cash_refund']})";
        $credit = floatval($ret['deducted_from_balance']);
        $transactions[] = [ 'date' => $ret['return_date'], 'time' => $ret['created_at'], 'ref' => $ret['return_number'], 'desc' => $desc, 'debit' => 0, 'credit' => $credit ];
        $totalReturns += $credit;
    }
} else {
    // Supplier
    $invoices = getRows("SELECT * FROM invoices WHERE type='purchase' AND supplier_id = ? AND date BETWEEN ? AND ?", [$id, $dateFrom, $dateTo]);
    foreach ($invoices as $inv) {
        $desc = "فاتورة شراء رقم {$inv['invoice_number']}";
        if ($inv['discount'] > 0) $desc .= " (إجمالي: {$inv['total_amount']}, خصم: {$inv['discount']})";
        else $desc .= " (إجمالي: {$inv['total_amount']})";
        if ($inv['paid_amount'] > 0) $desc .= " - دفعنا نقداً: {$inv['paid_amount']}";
        
        $debit = floatval($inv['paid_amount']);
        $credit = floatval($inv['total_amount'] - $inv['discount']);
        $transactions[] = [ 'date' => $inv['date'], 'time' => $inv['created_at'], 'ref' => $inv['invoice_number'], 'desc' => $desc, 'debit' => $debit, 'credit' => $credit ];
        $totalPurchases += $credit;
        $totalPayments += $debit;
    }
    
    $payments = getRows("SELECT * FROM payments WHERE type='supplier' AND entity_id = ? AND payment_date BETWEEN ? AND ?", [$id, $dateFrom, $dateTo]);
    foreach ($payments as $pay) {
        $desc = "سداد نقدي لمورد ({$pay['payment_method']})";
        if (!empty($pay['notes'])) $desc .= " - {$pay['notes']}";
        
        $debit = floatval($pay['amount']);
        $transactions[] = [ 'date' => $pay['payment_date'], 'time' => $pay['created_at'], 'ref' => $pay['payment_number'], 'desc' => $desc, 'debit' => $debit, 'credit' => 0 ];
        $totalPayments += $debit;
    }
    
    $returns = getRows("SELECT * FROM returns WHERE type='supplier' AND supplier_id = ? AND deducted_from_balance > 0 AND return_date BETWEEN ? AND ?", [$id, $dateFrom, $dateTo]);
    foreach ($returns as $ret) {
        $desc = "مرتجع شراء رقم {$ret['return_number']} (إجمالي المرتجع: {$ret['total_amount']}, استرداد نقدي: {$ret['cash_refund']})";
        $debit = floatval($ret['deducted_from_balance']);
        $transactions[] = [ 'date' => $ret['return_date'], 'time' => $ret['created_at'], 'ref' => $ret['return_number'], 'desc' => $desc, 'debit' => $debit, 'credit' => 0 ];
        $totalReturns += $debit;
    }
}

// Sort
usort($transactions, function($a, $b) {
    if ($a['date'] == $b['date']) return strcmp($a['time'], $b['time']);
    return strcmp($a['date'], $b['date']);
});

// ==========================================
// 3. Installment Plans (Customers Only)
// ==========================================
$installmentPlans = [];
$totalInstValue = 0;
$totalInstPaid = 0;
$totalInstRem = 0;
$lateInstallments = 0;

if ($type === 'customer') {
    $installmentPlans = getRows("SELECT * FROM installment_plans WHERE type='customer' AND entity_id = ? ORDER BY created_at DESC", [$id]);
    foreach ($installmentPlans as $plan) {
        $totalInstValue += $plan['total_amount'];
        $totalInstPaid += $plan['paid_amount'];
        $totalInstRem += $plan['remaining_amount'];
        
        // Check for late payments
        $late = getRow("SELECT COUNT(*) as c FROM installment_payments WHERE plan_id = ? AND status = 'pending' AND due_date < CURRENT_DATE", [$plan['id']]);
        $lateInstallments += $late['c'] ?? 0;
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1e3a8a;
            --secondary: #3b82f6;
            --success: #10b981;
            --danger: #ef4444;
            --dark: #1f2937;
            --light: #f3f4f6;
            --gray: #6b7280;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8fafc;
            color: var(--dark);
            margin: 0;
            padding: 20px;
            font-size: 14px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        /* Top Actions */
        .actions-bar {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: white;
        }
        .btn-primary { background: var(--primary); }
        .btn-secondary { background: var(--gray); }
        
        /* Header */
        .header {
            text-align: center;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 { margin: 0 0 10px 0; color: var(--primary); font-size: 28px; }
        .header p { margin: 5px 0; color: var(--gray); font-size: 16px; }
        
        .info-grid {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            background: var(--light);
            padding: 15px 25px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        .info-box {
            flex: 1;
        }
        .info-box h3 { margin: 0 0 5px 0; color: var(--gray); font-size: 14px; }
        .info-box p { margin: 0; font-weight: bold; font-size: 18px; color: var(--dark); }
        
        /* Dashboard Summary */
        .dashboard {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }
        .dash-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
            position: relative;
            overflow: hidden;
        }
        .dash-card::before {
            content: '';
            position: absolute;
            top: 0; right: 0; left: 0; height: 4px;
        }
        .card-blue::before { background: var(--secondary); }
        .card-green::before { background: var(--success); }
        .card-red::before { background: var(--danger); }
        .card-dark::before { background: var(--dark); }
        
        .dash-icon { font-size: 24px; margin-bottom: 10px; opacity: 0.8; }
        .dash-title { color: var(--gray); font-size: 13px; margin-bottom: 5px; font-weight: bold; }
        .dash-value { font-size: 22px; font-weight: 900; }
        .text-danger { color: var(--danger) !important; }
        .text-success { color: var(--success) !important; }
        .text-primary { color: var(--primary) !important; }
        
        /* Table */
        .section-title {
            font-size: 18px;
            color: var(--primary);
            border-bottom: 2px solid var(--light);
            padding-bottom: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th, td {
            border: 1px solid #e5e7eb;
            padding: 12px;
            text-align: right;
        }
        th {
            background-color: var(--primary);
            color: white;
            font-weight: 600;
        }
        tr:nth-child(even) { background-color: #f9fafb; }
        
        .amount-col { font-family: monospace; font-size: 15px; font-weight: bold; }
        
        /* Installment Plans */
        .installment-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .inst-plan {
            background: white;
            border: 1px solid #e5e7eb;
            border-right: 4px solid var(--secondary);
            border-radius: 8px;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .inst-details h4 { margin: 0 0 5px 0; color: var(--primary); }
        .inst-details p { margin: 0; color: var(--gray); font-size: 13px; }
        .inst-stats {
            display: flex;
            gap: 20px;
            text-align: center;
        }
        .stat-item {
            display: flex;
            flex-direction: column;
        }
        .stat-item span:first-child { color: var(--gray); font-size: 12px; }
        .stat-item span:last-child { font-weight: bold; font-size: 16px; }
        
        /* Print Styles */
        @media print {
            body { background: white; padding: 0; font-size: 12pt; }
            .container { box-shadow: none; max-width: 100%; padding: 0; border: none; }
            .noprint { display: none !important; }
            .dash-card { border: 1px solid #000; box-shadow: none; break-inside: avoid; }
            table { break-inside: auto; }
            tr { break-inside: avoid; break-after: auto; }
            th { background-color: #f3f4f6 !important; color: #000 !important; border-bottom: 2px solid #000 !important; }
            .inst-plan { border: 1px solid #000; }
        }
    </style>
</head>
<body>

<div class="container">
    
    <!-- Actions -->
    <div class="actions-bar noprint">
        <?php 
            $backLink = "index.php?type=statement&sub_type={$type}&entity_id={$id}";
            if (!empty($_GET['date_from'])) $backLink .= "&date_from=" . urlencode($_GET['date_from']);
            if (!empty($_GET['date_to'])) $backLink .= "&date_to=" . urlencode($_GET['date_to']);
        ?>
        <a href="<?php echo $backLink; ?>" class="btn btn-secondary"><i class="fas fa-arrow-right"></i> <?php echo (isAdmin() || hasPermission('reports.view')) ? 'رجوع للتقارير' : 'رجوع لكشف الحساب'; ?></a>
        <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> طباعة كشف الحساب</button>
    </div>

    <!-- Header -->
    <div class="header">
        <h1><?php echo getSetting('store_name'); ?></h1>
        <p><?php echo getSetting('store_address'); ?> | ت: <?php echo getSetting('store_phone'); ?></p>
        <h2 style="margin: 20px 0 10px 0;"><?php echo $pageTitle; ?></h2>
        <p>الفترة من: <strong><?php echo $dateFrom; ?></strong> إلى: <strong><?php echo $dateTo; ?></strong></p>
    </div>

    <!-- Info Grid -->
    <div class="info-grid">
        <div class="info-box">
            <h3>اسم <?php echo $type == 'customer' ? 'العميل' : 'المورد'; ?></h3>
            <p><?php echo $entity['name']; ?></p>
        </div>
        <div class="info-box">
            <h3>رقم الهاتف</h3>
            <p><?php echo !empty($entity['phone']) ? $entity['phone'] : 'غير مسجل'; ?></p>
        </div>
        <div class="info-box" style="text-align: left;">
            <h3>الرصيد الكلي الحالي بالسيستم</h3>
            <?php 
                $bal = $entity['balance'];
                if ($type == 'customer') {
                    $txt = $bal < 0 ? 'مدين (عليه)' : ($bal > 0 ? 'دائن (له)' : 'خالص');
                    $color = $bal < 0 ? 'text-danger' : ($bal > 0 ? 'text-success' : '');
                } else {
                    $txt = $bal > 0 ? 'مدين (عليه/سددنا بزيادة)' : ($bal < 0 ? 'دائن (له)' : 'خالص');
                    $color = $bal > 0 ? 'text-danger' : ($bal < 0 ? 'text-success' : '');
                }
            ?>
            <p class="<?php echo $color; ?>"><?php echo formatCurrency(abs($bal)); ?> <small>[<?php echo $txt; ?>]</small></p>
        </div>
    </div>

    <!-- Dashboard Summary -->
    <div class="dashboard">
        <div class="dash-card card-blue">
            <div class="dash-icon text-primary"><i class="fas fa-shopping-cart"></i></div>
            <div class="dash-title">إجمالي <?php echo $type == 'customer' ? 'المسحوبات' : 'المشتريات'; ?></div>
            <div class="dash-value"><?php echo formatCurrency($totalPurchases); ?></div>
        </div>
        <div class="dash-card card-green">
            <div class="dash-icon text-success"><i class="fas fa-money-bill-wave"></i></div>
            <div class="dash-title">إجمالي <?php echo $type == 'customer' ? 'المدفوع/المحصل' : 'ما سددناه'; ?></div>
            <div class="dash-value"><?php echo formatCurrency($totalPayments); ?></div>
        </div>
        <div class="dash-card card-red">
            <div class="dash-icon text-danger"><i class="fas fa-undo"></i></div>
            <div class="dash-title">إجمالي المرتجعات</div>
            <div class="dash-value"><?php echo formatCurrency($totalReturns); ?></div>
        </div>
        
        <?php 
            // Calculate final balance for the Period
            // Opening Balance + (Debits - Credits)
            // But let's just use the strict accounting logic:
            $runningBal = $openingBalance;
            foreach ($transactions as $t) {
                $runningBal = $runningBal + $t['debit'] - $t['credit'];
            }
            $finalTxt = ''; $finalColor = '';
            if ($type == 'customer') {
                $finalTxt = $runningBal > 0 ? 'مدين (عليه)' : ($runningBal < 0 ? 'دائن (له)' : 'خالص');
                $finalColor = $runningBal > 0 ? 'text-danger' : ($runningBal < 0 ? 'text-success' : '');
            } else {
                $finalTxt = $runningBal < 0 ? 'دائن (له)' : ($runningBal > 0 ? 'مدين (عليه)' : 'خالص');
                $finalColor = $runningBal < 0 ? 'text-danger' : ($runningBal > 0 ? 'text-success' : '');
            }
        ?>
        <div class="dash-card card-dark">
            <div class="dash-icon"><i class="fas fa-balance-scale"></i></div>
            <div class="dash-title">رصيد نهاية الفترة</div>
            <div class="dash-value <?php echo $finalColor; ?>" style="font-size: 20px;"><?php echo formatCurrency(abs($runningBal)); ?></div>
            <div style="font-size: 11px; color: var(--gray); margin-top: 5px;"><?php echo $finalTxt; ?></div>
        </div>
    </div>

    <!-- Detailed Ledger -->
    <h3 class="section-title"><i class="fas fa-list-alt"></i> دفتر الأستاذ (تفاصيل الحركات)</h3>
    <table>
        <thead>
            <tr>
                <th width="12%">التاريخ</th>
                <th width="10%">رقم المرجع</th>
                <th width="40%">البيان التفصيلي</th>
                <th width="12%"><?php echo $type == 'customer' ? 'مدين (حسابه زاد)' : 'مدين (دفعنا له)'; ?></th>
                <th width="12%"><?php echo $type == 'customer' ? 'دائن (دفع لنا)' : 'دائن (حسابه زاد)'; ?></th>
                <th width="14%">الرصيد التراكمي</th>
            </tr>
        </thead>
        <tbody>
            <!-- Opening Balance Row -->
            <tr style="background-color: #f1f5f9; font-weight: bold;">
                <td colspan="3" style="text-align: center;">الرصيد الافتتاحي (حتى تاريخ <?php echo $dateFrom; ?>)</td>
                <td></td>
                <td></td>
                <td class="amount-col" dir="ltr"><?php echo number_format($openingBalance, 2); ?></td>
            </tr>

            <?php 
            $currentBalance = $openingBalance;
            if (empty($transactions)): 
            ?>
            <tr><td colspan="6" style="text-align: center; padding: 20px;">لا توجد حركات مالية في هذه الفترة.</td></tr>
            <?php else: ?>
                <?php foreach ($transactions as $t): 
                    $currentBalance = $currentBalance + $t['debit'] - $t['credit'];
                ?>
                <tr>
                    <td dir="ltr"><?php echo $t['date']; ?></td>
                    <td><?php echo $t['ref']; ?></td>
                    <td style="text-align: right;"><?php echo $t['desc']; ?></td>
                    <td class="amount-col text-danger"><?php echo $t['debit'] > 0 ? number_format($t['debit'], 2) : '-'; ?></td>
                    <td class="amount-col text-success"><?php echo $t['credit'] > 0 ? number_format($t['credit'], 2) : '-'; ?></td>
                    <td class="amount-col" dir="ltr" style="color: <?php echo $currentBalance > 0 ? 'var(--danger)' : ($currentBalance < 0 ? 'var(--success)' : 'inherit'); ?>">
                        <?php echo number_format(abs($currentBalance), 2); ?>
                        <span style="font-size: 10px; color: var(--gray);"><?php echo $currentBalance > 0 ? '+' : ($currentBalance < 0 ? '-' : ''); ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <!-- Final Balance Row -->
            <tr style="background-color: #e5e7eb; font-weight: bold;">
                <td colspan="3" style="text-align: left;">إجمالي الحركات والرصيد النهائي للفترة:</td>
                <td class="amount-col text-danger"><?php echo number_format($totalPurchases, 2); ?></td>
                <td class="amount-col text-success"><?php echo number_format($totalPayments + $totalReturns, 2); ?></td>
                <td class="amount-col" dir="ltr" style="font-size: 16px;"><?php echo number_format($runningBal, 2); ?></td>
            </tr>
        </tbody>
    </table>

    <!-- Installment Plans (If any) -->
    <?php if ($type == 'customer' && !empty($installmentPlans)): ?>
    <div style="page-break-before: auto;">
        <h3 class="section-title" style="margin-top: 40px;"><i class="fas fa-calendar-check"></i> ملخص خطط التقسيط النشطة</h3>
        <div class="installment-list">
            <?php foreach ($installmentPlans as $plan): ?>
            <div class="inst-plan">
                <div class="inst-details">
                    <h4>خطة تقسيط بتاريخ: <?php echo date('Y-m-d', strtotime($plan['created_at'])); ?></h4>
                    <p>عدد الأقساط: <?php echo $plan['number_of_installments']; ?> (<?php echo $plan['status'] == 'active' ? 'نشط' : 'مكتمل'; ?>)</p>
                </div>
                <div class="inst-stats">
                    <div class="stat-item">
                        <span>إجمالي التقسيط</span>
                        <span class="text-primary"><?php echo formatCurrency($plan['total_amount']); ?></span>
                    </div>
                    <div class="stat-item">
                        <span>المدفوع</span>
                        <span class="text-success"><?php echo formatCurrency($plan['paid_amount']); ?></span>
                    </div>
                    <div class="stat-item">
                        <span>المتبقي</span>
                        <span class="text-danger"><?php echo formatCurrency($plan['remaining_amount']); ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
    // Optional JS for formatting or interactions
</script>
</body>
</html>
