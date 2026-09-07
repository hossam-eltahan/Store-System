<?php
/**
 * Authentication & Authorization System
 * نظام المصادقة والصلاحيات
 */

// Ensure database and settings are loaded
if (!function_exists('getRow')) {
    require_once __DIR__ . '/database.php';
}
if (!function_exists('getSetting')) {
    require_once __DIR__ . '/settings.php';
}

// All available permissions in the system
function getAllPermissions() {
    return [
        'dashboard' => [
            'label' => 'لوحة التحكم',
            'icon' => '🏠',
            'permissions' => [
                'dashboard.view' => 'عرض لوحة التحكم والإحصائيات',
            ]
        ],
        'products' => [
            'label' => 'الأصناف',
            'icon' => '📦',
            'permissions' => [
                'products.view' => 'عرض الأصناف',
                'products.add' => 'إضافة صنف',
                'products.edit' => 'تعديل صنف',
                'products.delete' => 'حذف صنف',
            ]
        ],
        'categories' => [
            'label' => 'فئات الأصناف',
            'icon' => '🏷️',
            'permissions' => [
                'categories.view' => 'عرض فئات الأصناف',
                'categories.manage' => 'إدارة فئات الأصناف',
            ]
        ],        'customers' => [
            'label' => 'العملاء',
            'icon' => '👥',
            'permissions' => [
                'customers.view' => 'عرض العملاء',
                'customers.add' => 'إضافة عميل',
                'customers.edit' => 'تعديل عميل',
                'customers.delete' => 'حذف عميل',
            ]
        ],
        'suppliers' => [
            'label' => 'الموردين',
            'icon' => '🚚',
            'permissions' => [
                'suppliers.view' => 'عرض الموردين',
                'suppliers.add' => 'إضافة مورد',
                'suppliers.edit' => 'تعديل مورد',
                'suppliers.delete' => 'حذف مورد',
            ]
        ],
        'invoices_sale' => [
            'label' => 'فواتير البيع',
            'icon' => '🧾',
            'permissions' => [
                'invoices.sale.view' => 'عرض فواتير البيع',
                'invoices.sale.create' => 'إنشاء فاتورة بيع',
                'invoices.sale.edit' => 'تعديل فاتورة بيع',
                'invoices.sale.delete' => 'حذف فاتورة بيع',
            ]
        ],
        'invoices_purchase' => [
            'label' => 'فواتير الشراء',
            'icon' => '📥',
            'permissions' => [
                'invoices.purchase.view' => 'عرض فواتير الشراء',
                'invoices.purchase.create' => 'إنشاء فاتورة شراء',
                'invoices.purchase.edit' => 'تعديل فاتورة شراء',
                'invoices.purchase.delete' => 'حذف فاتورة شراء',
            ]
        ],
        'payments' => [
            'label' => 'المدفوعات',
            'icon' => '💰',
            'permissions' => [
                'payments.view' => 'عرض سجل المدفوعات',
                'payments.customer' => 'تحصيل من عميل',
                'payments.supplier' => 'دفع لمورد',
            ]
        ],
        'expenses' => [
            'label' => 'المصروفات والتكاليف',
            'icon' => '💸',
            'permissions' => [
                'expenses.view' => 'عرض المصروفات',
                'expenses.add' => 'تسجيل مصروف جديد',
                'expenses.edit' => 'تعديل مصروف',
                'expenses.delete' => 'حذف مصروف',
                'expenses.categories' => 'إدارة تصنيفات المصروفات',
            ]
        ],
        'installments' => [
            'label' => 'الأقساط',
            'icon' => '📅',
            'permissions' => [
                'installments.view' => 'عرض الأقساط',
                'installments.create' => 'إنشاء قسط',
                'installments.pay' => 'تسجيل سداد قسط',
            ]
        ],
        'returns' => [
            'label' => 'المرتجعات',
            'icon' => '🔄',
            'permissions' => [
                'returns.view' => 'عرض المرتجعات',
                'returns.create_customer' => 'إنشاء مرتجع عميل',
                'returns.create_supplier' => 'إنشاء مرتجع مورد',
            ]
        ],
        'reports' => [
            'label' => 'التقارير',
            'icon' => '📊',
            'permissions' => [
                'reports.view' => 'عرض تقارير ونشاط المستخدم',
                'reports.statement' => 'عرض كشف الحساب',
            ]
        ],
        'settings' => [
            'label' => 'الإعدادات',
            'icon' => '⚙️',
            'permissions' => [
                'settings.manage' => 'إعدادات النظام',
                'settings.backup' => 'النسخ الاحتياطي',
            ]
        ],
        'warehouses' => [
            'label' => 'المخازن',
            'icon' => '🏢',
            'permissions' => [
                'warehouses.view' => 'عرض المخازن والأرصدة',
                'warehouses.manage' => 'إدارة المخازن (إضافة/تعديل)',
                'warehouses.transfer' => 'تحويل مخزون بين المخازن',
            ]
        ],
        'users' => [
            'label' => 'المستخدمين',
            'icon' => '🔐',
            'permissions' => [
                'users.manage' => 'إدارة المستخدمين والصلاحيات',
            ]
        ],
    ];
}

/**
 * Attempt login
 */
function attemptLogin($username, $password) {
    $user = getRow("SELECT * FROM users WHERE username = ? AND is_active = 1", [$username]);
    
    if (!$user) {
        return ['success' => false, 'message' => 'اسم المستخدم أو كلمة المرور غير صحيحة'];
    }
    
    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'اسم المستخدم أو كلمة المرور غير صحيحة'];
    }
    
    // Load permissions
    $permissions = [];
    if ($user['role'] === 'admin') {
        // Admin gets all permissions
        foreach (getAllPermissions() as $group) {
            foreach ($group['permissions'] as $key => $label) {
                $permissions[] = $key;
            }
        }
    } else {
        $perms = getRows("SELECT permission_key FROM user_permissions WHERE user_id = ?", [$user['id']]);
        foreach ($perms as $p) {
            $permissions[] = $p['permission_key'];
        }
    }
    
    // Set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_avatar'] = $user['avatar'];
    $_SESSION['user_permissions'] = $permissions;
    $_SESSION['default_warehouse_id'] = $user['default_warehouse_id'] ?? 1;
    
    // Generate session token
    $sessionToken = bin2hex(random_bytes(32));
    $_SESSION['session_token'] = $sessionToken;
    
    // Update last login
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    execute("UPDATE users SET last_login = NOW(), last_login_ip = ? WHERE id = ?", [$ip, $user['id']]);
    
    // Log session
    insert("INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent) VALUES (?, ?, ?, ?)", [
        $user['id'],
        $sessionToken,
        $ip,
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
    
    return ['success' => true, 'user' => $user];
}

/**
 * Logout
 */
function performLogout() {
    if (isset($_SESSION['session_token']) && isset($_SESSION['user_id'])) {
        // Mark session as inactive
        execute(
            "UPDATE user_sessions SET is_active = 0, logout_at = NOW() WHERE user_id = ? AND session_token = ?",
            [$_SESSION['user_id'], $_SESSION['session_token']]
        );
    }
    
    // Destroy session
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Require login - redirect to login page if not logged in
 * Also verifies user status in database in real-time
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $currentUrl = $_SERVER['REQUEST_URI'] ?? '';
        $loginUrl = getBaseUrl() . 'login.php';
        if (!empty($currentUrl) && strpos($currentUrl, 'login.php') === false) {
            $loginUrl .= '?redirect=' . urlencode($currentUrl);
        }
        header("Location: " . $loginUrl);
        exit;
    }

    // Real-time security verification from DB (runs once per request)
    static $verified = false;
    if (!$verified && isset($_SESSION['user_id'])) {
        $user = getRow("SELECT id, username, full_name, role, is_active, avatar, default_warehouse_id FROM users WHERE id = ?", [$_SESSION['user_id']]);
        if (!$user || (int)$user['is_active'] !== 1) {
            performLogout();
            header("Location: " . getBaseUrl() . "login.php?error=" . urlencode("تم تعطيل حسابك أو حذفه، يرجى التواصل مع المدير."));
            exit;
        }

        // Sync session data with DB in real-time
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_avatar'] = $user['avatar'];
        $_SESSION['default_warehouse_id'] = $user['default_warehouse_id'] ?? 1;

        if ($user['role'] === 'admin') {
            // Admins get all permissions
            $allPerms = [];
            foreach (getAllPermissions() as $grp) {
                foreach ($grp['permissions'] as $pkey => $plbl) {
                    $allPerms[] = $pkey;
                }
            }
            $_SESSION['user_permissions'] = $allPerms;
        } else {
            // Refresh employee permissions from DB immediately
            $perms = getRows("SELECT permission_key FROM user_permissions WHERE user_id = ?", [$user['id']]);
            $_SESSION['user_permissions'] = array_column($perms, 'permission_key');
        }
        $verified = true;
    }
}

/**
 * Get base URL helper (works before BASE_URL is defined)
 */
function getBaseUrl() {
    if (defined('BASE_URL')) {
        return BASE_URL;
    }
    $dirName = basename(dirname(__DIR__));
    return '/' . $dirName . '/';
}

/**
 * Check if current user has a specific permission
 */
function hasPermission($key) {
    if (!isLoggedIn()) return false;
    if (isAdmin()) return true;
    return in_array($key, $_SESSION['user_permissions'] ?? []);
}

/**
 * Check if current user has ANY of the specified permissions
 */
function hasAnyPermission($keys) {
    if (!isLoggedIn()) return false;
    if (isAdmin()) return true;
    if (is_string($keys)) $keys = [$keys];
    $userPerms = $_SESSION['user_permissions'] ?? [];
    foreach ($keys as $key) {
        if (in_array($key, $userPerms)) {
            return true;
        }
    }
    return false;
}

/**
 * Require a specific permission - show access denied if not authorized
 */
function requirePermission($key) {
    requireLogin();
    if (!hasPermission($key)) {
        showAccessDenied();
        exit;
    }
}

/**
 * Require any of the specified permissions - show access denied if none matched
 */
function requireAnyPermission($keys) {
    requireLogin();
    if (!hasAnyPermission($keys)) {
        showAccessDenied();
        exit;
    }
}

/**
 * Check if current user is admin
 */
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Get current user data from session
 */
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'],
        'role' => $_SESSION['user_role'],
        'avatar' => $_SESSION['user_avatar'] ?? null,
        'default_warehouse_id' => $_SESSION['default_warehouse_id'] ?? 1,
    ];
}

/**
 * Get current user's display name
 */
function getCurrentUserName() {
    return $_SESSION['full_name'] ?? 'غير معروف';
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user's default warehouse ID
 */
function getCurrentWarehouseId() {
    return $_SESSION['default_warehouse_id'] ?? 1;
}

/**
 * Get user permissions from database
 */
function getUserPermissions($userId) {
    $perms = getRows("SELECT permission_key FROM user_permissions WHERE user_id = ?", [$userId]);
    return array_column($perms, 'permission_key');
}

/**
 * Set user permissions
 */
function setUserPermissions($userId, $permissions) {
    // Delete existing permissions
    execute("DELETE FROM user_permissions WHERE user_id = ?", [$userId]);
    
    // Insert new permissions
    foreach ($permissions as $key) {
        insert("INSERT INTO user_permissions (user_id, permission_key) VALUES (?, ?)", [$userId, $key]);
    }
}

/**
 * Show access denied page
 */
function showAccessDenied() {
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
           || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
    
    http_response_code(403);
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'عذراً، ليس لديك صلاحية للقيام بهذه العملية.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $baseUrl = getBaseUrl();
    echo '<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>غير مسموح - 403</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: "Cairo", sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #e2e8f0;
        }
        .denied-container {
            text-align: center;
            padding: 40px;
            max-width: 500px;
        }
        .denied-icon {
            font-size: 80px;
            margin-bottom: 20px;
            animation: shake 0.5s ease-in-out;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        .denied-title {
            font-size: 2em;
            font-weight: 700;
            margin-bottom: 10px;
            color: #f87171;
        }
        .denied-message {
            font-size: 1.1em;
            color: #94a3b8;
            margin-bottom: 30px;
            line-height: 1.8;
        }
        .denied-btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-size: 1em;
            font-weight: 600;
            font-family: "Cairo", sans-serif;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        .denied-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
        }
    </style>
</head>
<body>
    <div class="denied-container">
        <div class="denied-icon">🚫</div>
        <h1 class="denied-title">غير مسموح</h1>
        <p class="denied-message">عذراً، ليس لديك صلاحية للوصول لهذه الصفحة.<br>تواصل مع المدير لمنحك الصلاحيات المطلوبة.</p>
        <a href="' . $baseUrl . 'index.php" class="denied-btn">🏠 العودة للرئيسية</a>
    </div>
</body>
</html>';
}

/**
 * Default avatar paths (for new users)
 */
function getDefaultAvatars() {
    return [
        'avatars/avatar_1.png',
        'avatars/avatar_2.png',
        'avatars/avatar_3.png',
        'avatars/avatar_4.png',
        'avatars/avatar_5.png',
        'avatars/avatar_6.png',
        'avatars/avatar_7.png',
        'avatars/avatar_8.png',
    ];
}

/**
 * Get a random default avatar
 */
function getRandomDefaultAvatar() {
    $avatars = getDefaultAvatars();
    return $avatars[array_rand($avatars)];
}

/**
 * Get avatar URL for display
 */
function getAvatarUrl($avatar = null) {
    $baseUrl = getBaseUrl();
    if ($avatar && file_exists(__DIR__ . '/../assets/uploads/' . $avatar)) {
        return $baseUrl . 'assets/uploads/' . $avatar;
    }
    // Return a generated SVG avatar placeholder
    return null;
}
