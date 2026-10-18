<?php
/**
 * Dashboard AJAX — الحسابات المالية لليوم مخفية بعد كلمة مرور المستخدم
 * Actions:
 *   verify_and_get (POST password) — يتأكد من كلمة مرور المستخدم الحالي
 *   ويرجع أرقام اليوم المالية (نفس معادلات الداشبورد القديمة).
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

requireLogin();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action !== 'verify_and_get') {
    echo json_encode(['success' => false, 'error' => 'إجراء غير معروف'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Rate limiting per session: 5 failures → 60s lock
$lockUntil = (int)($_SESSION['dash_reveal_lock_until'] ?? 0);
if ($lockUntil && time() < $lockUntil) {
    $wait = $lockUntil - time();
    echo json_encode([
        'success' => false,
        'error'   => "محاولات كثيرة خاطئة — حاول بعد {$wait} ثانية",
        'locked'  => true,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$password = (string)($_POST['password'] ?? '');
$me = getRow("SELECT id, username, password FROM users WHERE id = ? AND is_active = 1", [getCurrentUserId()]);

if (!$me || $password === '' || !password_verify($password, $me['password'])) {
    $fails = ((int)($_SESSION['dash_reveal_fail'] ?? 0)) + 1;
    if ($fails >= 5) {
        $_SESSION['dash_reveal_fail'] = 0;
        $_SESSION['dash_reveal_lock_until'] = time() + 60;
        echo json_encode([
            'success' => false,
            'error'   => 'كلمة المرور غير صحيحة (5 محاولات — الإظهار مقفول لمدة دقيقة)',
        ], JSON_UNESCAPED_UNICODE);
    } else {
        $_SESSION['dash_reveal_fail'] = $fails;
        echo json_encode([
            'success' => false,
            'error'   => 'كلمة المرور غير صحيحة (محاولة ' . $fails . ' من 5)',
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$_SESSION['dash_reveal_fail'] = 0;

// نفس معادلات إحصائيات اليوم القديمة في لوحة التحكم
$todayOrdersRow = getRow(
    "SELECT COALESCE(SUM(total_amount), 0) as total, COUNT(*) as orders_count 
     FROM orders 
     WHERE DATE(created_at) = CURDATE() AND status != 'cancelled'"
);
$ordersTotal = floatval($todayOrdersRow['total'] ?? 0);
$ordersCount = intval($todayOrdersRow['orders_count'] ?? 0);

$psRevenue = floatval(getRow(
    "SELECT COALESCE(SUM(gaming_charge), 0) as total 
     FROM gaming_sessions 
     WHERE DATE(started_at) = CURDATE() AND status = 'completed'"
)['total'] ?? 0);

$billiardRevenue = floatval(getRow(
    "SELECT COALESCE(SUM(gaming_charge), 0) as total 
     FROM billiard_sessions 
     WHERE DATE(started_at) = CURDATE() AND status = 'completed'"
)['total'] ?? 0);

$expenses = floatval(getRow(
    "SELECT COALESCE(SUM(amount), 0) as total 
     FROM expenses 
     WHERE date = CURDATE()"
)['total'] ?? 0);

$totalRevenue = $ordersTotal + $psRevenue + $billiardRevenue;
$estimatedNet = max(0, $totalRevenue - $expenses);

logActivity('كشف إحصائيات الداشبورد', 'عرض أرقام اليوم المالية بعد التحقق من كلمة المرور (' . $me['username'] . ')');

echo json_encode([
    'success'        => true,
    'orders_total'   => $ordersTotal,
    'orders_count'   => $ordersCount,
    'ps_revenue'     => $psRevenue,
    'billiard_revenue' => $billiardRevenue,
    'expenses'       => $expenses,
    'estimated_net'  => $estimatedNet,
], JSON_UNESCAPED_UNICODE);
