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
        'inventory' => [
            'label' => 'المخزون',
            'icon' => '🏭',
            'permissions' => [
                'inventory.view' => 'عرض المخزون وسجل الحركات',
                'inventory.adjust' => 'تسوية وضبط أرصدة المخزون',
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
        'returns' => [
            'label' => 'المرتجعات',
            'icon' => '↩️',
            'permissions' => [
                'returns.view' => 'عرض المرتجعات',
                'returns.create' => 'إنشاء مرتجع بيع أو شراء',
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
        'pos' => [
            'label' => 'كاشير الكافيه',
            'icon' => '☕',
            'permissions' => [
                'pos.view' => 'عرض شاشة الكاشير',
                'pos.create_order' => 'إنشاء وحفظ الطلبات',
                'pos.discount' => 'تطبيق خصم على الفاتورة',
                'pos.void_item' => 'إلغاء أوردر أو صنف',
            ]
        ],
        'gaming' => [
            'label' => 'صالة البلايستيشن',
            'icon' => '🎮',
            'permissions' => [
                'gaming.view' => 'عرض صالة وأجهزة البلايستيشن',
                'gaming.manage' => 'بدء وإيقاف وإنهاء جلسات اللعب',
                'gaming.devices' => 'إدارة وضبط أسعار الأجهزة',
            ]
        ],
        'billiards' => [
            'label' => 'صالة البلياردو',
            'icon' => '🎱',
            'permissions' => [
                'billiards.view' => 'عرض طاولات البلياردو',
                'billiards.manage' => 'بدء وحساب وإنهاء الجلسات',
                'billiards.tables' => 'إدارة وضبط أسعار الطاولات',
            ]
        ],
        'tables' => [
            'label' => 'طاولات الصالة',
            'icon' => '🪑',
            'permissions' => [
                'tables.view' => 'عرض طاولات الجلوس',
                'tables.manage' => 'فتح وإدارة جلسات الطاولات',
            ]
        ],
        'shifts' => [
            'label' => 'الورديات والخزينة',
            'icon' => '⏱️',
            'permissions' => [
                'shifts.view' => 'عرض الورديات وحركات النقدية',
                'shifts.open_close' => 'فتح وتقفيل الوردية',
                'shifts.cash_movement' => 'تسجيل إيداع أو سحب نقدي',
            ]
        ],
        'recipes' => [
            'label' => 'مخزون الخامات والوصفات',
            'icon' => '🥛',
            'permissions' => [
                'recipes.view' => 'عرض خامات ومخزون البوفيه',
                'recipes.manage' => 'إدارة الوصفات والمقادير',
                'recipes.stock_adjust' => 'تسوية وجرد الخامات',
            ]
        ],
        'reports' => [
            'label' => 'التقارير والأرباح',
            'icon' => '📊',
            'permissions' => [
                'reports.view' => 'عرض تقارير المبيعات والأرباح',
                'reports.statement' => 'عرض كشوف الحسابات',
            ]
        ],
        'settings' => [
            'label' => 'الإعدادات',
            'icon' => '⚙️',
            'permissions' => [
                'settings.manage' => 'إعدادات النظام والكافيه',
                'settings.backup' => 'النسخ الاحتياطي',
                'settings.reset' => 'إعادة ضبط المصنع (مسح كل البيانات)',
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
function attemptLogin($username, $password, $remember = false) {
    $user = getRow("SELECT * FROM users WHERE username = ? AND is_active = 1", [$username]);
    
    if (!$user) {
        return ['success' => false, 'message' => 'اسم المستخدم أو كلمة المرور غير صحيحة'];
    }
    
    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'اسم المستخدم أو كلمة المرور غير صحيحة'];
    }
    
    establishUserSession($user);

    if ($remember) {
        issueRememberToken((int)$user['id']);
    }
    
    return ['success' => true, 'user' => $user];
}

/**
 * Build the PHP session for an authenticated user (shared by password
 * login and remember-me cookie login).
 */
function establishUserSession($user) {
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
}

/**
 * Issue a long-lived remember-me cookie (30 days). Only the hash is stored.
 */
function issueRememberToken($userId) {
    $token = bin2hex(random_bytes(32));
    execute(
        "UPDATE users SET remember_token = ?, remember_expiry = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE id = ?",
        [hash('sha256', $token), $userId]
    );
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie('remember_me', $userId . ':' . $token, [
        'expires' => time() + 30 * 86400,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Remove the remember-me cookie and its server-side token.
 */
function clearRememberToken($userId = null) {
    if ($userId) {
        execute("UPDATE users SET remember_token = NULL, remember_expiry = NULL WHERE id = ?", [$userId]);
    }
    if (isset($_COOKIE['remember_me'])) {
        setcookie('remember_me', '', ['expires' => time() - 3600, 'path' => '/']);
        unset($_COOKIE['remember_me']);
    }
}

/**
 * Silent auto-login from a valid remember-me cookie. Runs at most once
 * per request and only when no PHP session exists.
 */
function tryRememberLogin() {
    static $tried = false;
    if ($tried) {
        return false;
    }
    $tried = true;

    if (!empty($_SESSION['user_id']) || empty($_COOKIE['remember_me'])) {
        return false;
    }
    $parts = explode(':', $_COOKIE['remember_me'], 2);
    if (count($parts) !== 2 || !ctype_digit($parts[0]) || $parts[1] === '') {
        return false;
    }
    $user = getRow(
        "SELECT * FROM users WHERE id = ? AND is_active = 1 AND remember_token IS NOT NULL AND remember_expiry > NOW()",
        [(int)$parts[0]]
    );
    if (!$user || !hash_equals((string)$user['remember_token'], hash('sha256', $parts[1]))) {
        clearRememberToken();
        return false;
    }

    establishUserSession($user);
    return true;
}

/**
 * Logout
 */
function performLogout() {
    if (isset($_SESSION['session_token']) && isset($_SESSION['user_id'])) {
        // Remove the session row (user_sessions has no status columns)
        execute(
            "DELETE FROM user_sessions WHERE user_id = ? AND session_token = ?",
            [$_SESSION['user_id'], $_SESSION['session_token']]
        );
    }
    clearRememberToken($_SESSION['user_id'] ?? null);
    
    // Destroy session
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
}

/**
 * Check if user is logged in (falls back to a valid remember-me cookie)
 */
function isLoggedIn() {
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        return true;
    }
    return tryRememberLogin();
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

    // Render inside the normal app shell (header + sidebar + footer) so a
    // denied page looks exactly like any other page. Must be called before
    // any output; mid-page callers must deny earlier instead.
    $pageTitle = 'غير مسموح - 403';
    include __DIR__ . '/../includes/header.php';
    include __DIR__ . '/../includes/navbar.php';
    ?>
    <div class="card" style="max-width:560px; margin:40px auto; border-radius:12px; border:none; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05); text-align:center; padding:40px 30px;">
        <div style="font-size:64px; margin-bottom:10px;">🚫</div>
        <h2 style="font-weight:800; margin-bottom:10px; color:#dc2626;">غير مسموح</h2>
        <p style="color:#64748b; margin-bottom:25px; line-height:1.9;">عذراً، ليس لديك صلاحية للوصول لهذه الصفحة.<br>تواصل مع المدير لمنحك الصلاحيات المطلوبة.</p>
        <a href="<?php echo getBaseUrl(); ?>index.php" style="display:inline-block; padding:10px 28px; background:#2563eb; color:#fff; text-decoration:none; border-radius:8px; font-weight:700;">🏠 العودة للرئيسية</a>
    </div>
    <?php
    include __DIR__ . '/../includes/footer.php';
    // Fail-closed: callers must not continue rendering after denial.
    exit;
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
