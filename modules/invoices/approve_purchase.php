<?php
/**
 * Approve a draft purchase invoice (applies stock + supplier balance once)
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requireLogin();
requireAnyPermission(['invoices.purchase.create', 'invoices.purchase.edit']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('list.php?type=purchase');
}

$invoiceId = (int)($_POST['id'] ?? 0);
if ($invoiceId <= 0) {
    setError('فاتورة غير صالحة');
    redirect('list.php?type=purchase');
}

$result = approvePurchaseInvoice($invoiceId);

if (!empty($result['success'])) {
    if (!empty($result['already'])) {
        setSuccess('الفاتورة معتمدة بالفعل — لم يتم تكرار أي حركة مخزون');
    } else {
        logActivity('اعتماد فاتورة شراء', 'تم اعتماد فاتورة شراء رقم ' . ($result['invoice_number'] ?? $invoiceId), $_SESSION['full_name'] ?? '');
        setSuccess('تم اعتماد الفاتورة وإضافة الأصناف للمخزون بنجاح');
    }
    redirect('print_purchase.php?id=' . $invoiceId);
} else {
    setError('تعذر الاعتماد: ' . ($result['error'] ?? 'خطأ غير معروف'));
    redirect('list.php?type=purchase&status=draft');
}
