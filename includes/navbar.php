<?php
/**
 * Sidebar Navigation
 * نظام إدارة محل أجهزة منزلية
 */

// Include notifications helper
require_once __DIR__ . '/notifications.php';
$notifications = getNotifications();
$notificationCount = count($notifications);

// Get current user info
$currentUser = getCurrentUser();
$currentUserAvatar = getAvatarUrl($currentUser['avatar'] ?? null);
?>

<!-- Top Header Bar -->
<header class="top-header noprint">
    <div style="display: flex; align-items: center; gap: 10px;">
        <button class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()">
            <span class="toggle-icon">☰</span>
        </button>
        <div class="header-title"><?php echo getSetting('store_name', 'نظام إدارة المحل'); ?></div>
    </div>
    
    <!-- Center Section: License Status & Expiry (وسط رأس البرنامج) -->
    <div class="header-license-center" style="margin: 0 auto; display: flex; align-items: center; gap: 8px;">
        <?php
        require_once __DIR__ . '/../config/license.php';
        $licenseDetails = function_exists('getLicenseDetails') ? getLicenseDetails() : null;
        if ($licenseDetails && $licenseDetails['status'] === 'valid'):
            if (!empty($licenseDetails['is_dev'])): ?>
                <span title="بيئة تطوير المبرمج" style="font-size: 11.5px; padding: 4px 14px; border-radius: 20px; background: rgba(59,130,246,0.12); color: #2563eb; border: 1px solid rgba(59,130,246,0.3); font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
                    <span>🛠️</span> وضع المطور (غير مقيد)
                </span>
            <?php elseif (!empty($licenseDetails['is_lifetime'])): ?>
                <span style="font-size: 11.5px; padding: 4px 14px; border-radius: 20px; background: rgba(16,185,129,0.12); color: #059669; border: 1px solid rgba(16,185,129,0.3); font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
                    <span>💎</span> ترخيص دائم مدى الحياة
                </span>
            <?php else: 
                $days = (int)$licenseDetails['days_left'];
                $mins = (int)($licenseDetails['mins_left'] ?? 0);
                $isTest = !empty($licenseDetails['is_test']) || ($mins <= 60 && $days == 0);
                $isTrial = !empty($licenseDetails['is_trial']);
                $isUrgent = ($days <= 7 || $isTest);

                $badgeBg = $isUrgent ? 'rgba(239,68,68,0.12)' : ($days <= 15 ? 'rgba(245,158,11,0.12)' : 'rgba(16,185,129,0.12)');
                $badgeColor = $isUrgent ? '#dc2626' : ($days <= 15 ? '#d97706' : '#059669');
                $badgeBorder = $isUrgent ? 'rgba(239,68,68,0.35)' : ($days <= 15 ? 'rgba(245,158,11,0.35)' : 'rgba(16,185,129,0.3)');
                
                $labelText = $isTest ? "فترة اختبار: متبقي {$mins} دقيقة" : ($isTrial ? "نسخة تجريبية: متبقي {$days} أيام" : "الاشتراك ساري: متبقي {$days} يوم");
                $expiryText = $licenseDetails['expires_at'] ?? '';
            ?>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="font-size: 11.5px; padding: 4px 14px; border-radius: 20px; background: <?php echo $badgeBg; ?>; color: <?php echo $badgeColor; ?>; border: 1px solid <?php echo $badgeBorder; ?>; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                        <span><?php echo $isUrgent ? '⚠️' : '⏱️'; ?></span>
                        <span><?php echo $labelText; ?></span>
                        <?php if (!empty($expiryText)): ?>
                            <span style="opacity: 0.8; font-size: 10.5px; margin-right: 4px;">(موعد التجديد: <?php echo $expiryText; ?>)</span>
                        <?php endif; ?>
                    </div>
                    <a href="<?php echo BASE_URL; ?>activate.php" title="تجديد الاشتراك / إدخال كود جديد" style="text-decoration: none; font-size: 11px; padding: 4px 12px; border-radius: 6px; background: #7c3aed; color: white; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 2px 6px rgba(124,58,237,0.25);">
                        <span>🔑</span> تجديد الاشتراك
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div style="display: flex; align-items: center; gap: 12px;">
        <!-- License Status Badge -->
        <?php
        $licenseDetails = function_exists('getLicenseDetails') ? getLicenseDetails() : null;
        if ($licenseDetails && $licenseDetails['status'] === 'valid'):
            if (!empty($licenseDetails['is_dev'])): ?>
                <span title="بيئة تطوير المبرمج" style="font-size: 11px; padding: 4px 10px; border-radius: 6px; background: rgba(59,130,246,0.15); color: #60a5fa; border: 1px solid rgba(59,130,246,0.3); font-weight: 700;">
                    🛠️ وضع المطور
                </span>
            <?php elseif (!empty($licenseDetails['is_lifetime'])): ?>
                <span style="font-size: 11px; padding: 4px 10px; border-radius: 6px; background: rgba(16,185,129,0.15); color: #34d399; border: 1px solid rgba(16,185,129,0.3); font-weight: 700;">
                    💎 ترخيص دائم
                </span>
            <?php else: 
                $days = (int)$licenseDetails['days_left'];
                $badgeBg = $days <= 7 ? 'rgba(239,68,68,0.15)' : ($days <= 15 ? 'rgba(245,158,11,0.15)' : 'rgba(16,185,129,0.15)');
                $badgeColor = $days <= 7 ? '#f87171' : ($days <= 15 ? '#fbbf24' : '#34d399');
                $badgeBorder = $days <= 7 ? 'rgba(239,68,68,0.3)' : ($days <= 15 ? 'rgba(245,158,11,0.3)' : 'rgba(16,185,129,0.3)');
            ?>
                <a href="<?php echo BASE_URL; ?>activate.php" title="تاريخ الانتهاء: <?php echo $licenseDetails['expires_at']; ?> - اضغط للتجديد" style="text-decoration: none; font-size: 11px; padding: 4px 10px; border-radius: 6px; background: <?php echo $badgeBg; ?>; color: <?php echo $badgeColor; ?>; border: 1px solid <?php echo $badgeBorder; ?>; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                    <span>⏱️</span>
                    <span><?php echo (!empty($licenseDetails['is_test']) || (!empty($licenseDetails['mins_left']) && $licenseDetails['mins_left'] <= 60)) ? "متبقي " . $licenseDetails['mins_left'] . " دقيقة" : (!empty($licenseDetails['is_trial']) ? "تجريبي: " . $days . " أيام" : "متبقي " . $days . " يوم"); ?></span>
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Notification Bell -->
        <div class="notification-wrapper">
            <button class="notification-bell" id="notificationBell" onclick="toggleNotifications(event)">
                <span class="bell-icon">🔔</span>
                <?php if ($notificationCount > 0): ?>
                <span class="notification-badge"><?php echo $notificationCount > 99 ? '99+' : $notificationCount; ?></span>
                <?php endif; ?>
            </button>
            
            <!-- Notification Dropdown -->
            <div class="notification-dropdown" id="notificationDropdown">
                <div class="notification-dropdown-header">
                    <span>🔔 التنبيهات</span>
                    <span class="notification-count"><?php echo $notificationCount; ?></span>
                </div>
                <div class="notification-dropdown-body">
                    <?php if (empty($notifications)): ?>
                    <div class="notification-empty">
                        ✅ لا توجد تنبيهات
                    </div>
                    <?php else: ?>
                    <?php foreach ($notifications as $notif): ?>
                    <a href="<?php echo BASE_URL . $notif['link']; ?>" class="notification-dropdown-item <?php echo $notif['type']; ?>">
                        <span class="notification-dropdown-icon"><?php echo $notif['icon']; ?></span>
                        <div class="notification-dropdown-content">
                            <div class="notification-dropdown-title"><?php echo $notif['title']; ?></div>
                            <div class="notification-dropdown-message"><?php echo $notif['message']; ?></div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <a href="<?php echo BASE_URL; ?>modules/notifications/index.php" class="notification-dropdown-footer">
                    📋 عرض جميع الإشعارات
                </a>
            </div>
        </div>

        <!-- User Menu -->
        <div class="notification-wrapper">
            <button class="user-header-btn" id="userMenuBtn" onclick="toggleUserMenu(event)" style="display: flex; align-items: center; gap: 8px; background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); border-radius: 10px; padding: 4px 12px 4px 6px; cursor: pointer; color: inherit; font-family: 'Cairo', sans-serif; transition: all 0.2s;">
                <div style="width: 30px; height: 30px; border-radius: 8px; overflow: hidden; background: linear-gradient(135deg, #3b82f6, #8b5cf6); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 0.8em; flex-shrink: 0;">
                    <?php if ($currentUserAvatar): ?>
                        <img src="<?php echo $currentUserAvatar; ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <?php echo mb_substr($currentUser['full_name'] ?? '?', 0, 1); ?>
                    <?php endif; ?>
                </div>
                <span style="font-size: 0.85em; font-weight: 600; max-width: 100px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                    <?php echo htmlspecialchars($currentUser['full_name'] ?? 'مستخدم'); ?>
                </span>
                <span style="font-size: 0.7em; opacity: 0.7;">▼</span>
            </button>

            <div class="notification-dropdown" id="userMenuDropdown" style="min-width: 200px;">
                <div class="notification-dropdown-header" style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 36px; height: 36px; border-radius: 10px; overflow: hidden; background: linear-gradient(135deg, #3b82f6, #8b5cf6); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; flex-shrink: 0;">
                        <?php if ($currentUserAvatar): ?>
                            <img src="<?php echo $currentUserAvatar; ?>" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <?php echo mb_substr($currentUser['full_name'] ?? '?', 0, 1); ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div style="font-weight: 700; font-size: 0.95em;"><?php echo htmlspecialchars($currentUser['full_name'] ?? ''); ?></div>
                        <div style="font-size: 0.75em; opacity: 0.7;">
                            <?php 
                            $roleNames = ['admin' => '👑 مدير النظام', 'cashier' => '☕ كاشير', 'barista' => '🍹 باريستا', 'employee' => '👤 موظف'];
                            echo $roleNames[$currentUser['role']] ?? '👤 مستخدم'; 
                            ?>
                        </div>
                    </div>
                </div>
                <div class="notification-dropdown-body" style="padding: 5px;">
                    <a href="<?php echo BASE_URL; ?>modules/users/profile.php" style="display: flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 8px; text-decoration: none; color: inherit; transition: background 0.2s;" onmouseover="this.style.background='rgba(59,130,246,0.1)'" onmouseout="this.style.background='transparent'">
                        <span>👤</span> <span>الملف الشخصي</span>
                    </a>
                    <?php if (hasPermission('users.manage')): ?>
                    <a href="<?php echo BASE_URL; ?>modules/users/index.php" style="display: flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 8px; text-decoration: none; color: inherit; transition: background 0.2s;" onmouseover="this.style.background='rgba(59,130,246,0.1)'" onmouseout="this.style.background='transparent'">
                        <span>🔐</span> <span>إدارة المستخدمين</span>
                    </a>
                    <?php endif; ?>
                    <hr style="border: none; border-top: 1px solid rgba(71,85,105,0.2); margin: 5px 0;">
                    <a href="<?php echo BASE_URL; ?>logout.php" style="display: flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 8px; text-decoration: none; color: #ef4444; transition: background 0.2s;" onmouseover="this.style.background='rgba(239,68,68,0.1)'" onmouseout="this.style.background='transparent'">
                        <span>🚪</span> <span>تسجيل الخروج</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>



<!-- Sidebar -->
<aside class="sidebar noprint" id="sidebar">
    <div class="sidebar-header">
        <a href="<?php echo BASE_URL; ?>" class="sidebar-brand">
            <?php if ($logo = getSetting('store_logo')): ?>
                <img src="<?php echo BASE_URL; ?>assets/uploads/<?php echo $logo; ?>" alt="Logo" class="sidebar-logo">
            <?php else: ?>
                <span class="sidebar-logo-icon">🏠</span>
            <?php endif; ?>
            <span class="sidebar-store-name"><?php echo getSetting('store_name', 'نظام إدارة المحل'); ?></span>
        </a>
    </div>
    
    <!-- Sidebar Menu -->
    <nav class="sidebar-nav">
        <ul class="sidebar-menu">
            <!-- الرئيسية -->
            <?php if (hasPermission('dashboard.view')): ?>
            <li>
                <a href="<?php echo BASE_URL; ?>index.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' && strpos($_SERVER['PHP_SELF'], 'modules') === false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">🏠</span>
                    <span class="sidebar-text">الرئيسية</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- كاشير الكافيه POS -->
            <?php if (hasPermission('pos.view')): ?>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/pos/index.php" class="sidebar-link <?php echo strpos($_SERVER['PHP_SELF'], 'pos') !== false ? 'active' : ''; ?>" style="background: rgba(234, 88, 12, 0.1); border-right: 3px solid #ea580c; font-weight: 700;">
                    <span class="sidebar-icon">⚡</span>
                    <span class="sidebar-text" style="color: #ea580c;">كاشير الكافيه POS</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- صالة البلايستيشن -->
            <?php if (hasPermission('gaming.view')): ?>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/gaming/index.php" class="sidebar-link <?php echo strpos($_SERVER['PHP_SELF'], 'gaming') !== false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">🎮</span>
                    <span class="sidebar-text">صالة البلايستيشن</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- صالة البلياردو -->
            <?php if (hasPermission('billiards.view')): ?>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/billiards/index.php" class="sidebar-link <?php echo strpos($_SERVER['PHP_SELF'], 'billiards') !== false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">🎱</span>
                    <span class="sidebar-text">طاولات البلياردو</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- طاولات الصالة -->
            <?php if (hasPermission('tables.view')): ?>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/tables/index.php" class="sidebar-link <?php echo strpos($_SERVER['PHP_SELF'], 'tables') !== false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">🪑</span>
                    <span class="sidebar-text">طاولات الصالة</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- الورديات والشيفتات -->
            <?php if (hasPermission('shifts.view')): ?>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/shifts/index.php" class="sidebar-link <?php echo strpos($_SERVER['PHP_SELF'], 'shifts') !== false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">⏱️</span>
                    <span class="sidebar-text">الورديات والخزينة</span>
                </a>
            </li>
            <?php endif; ?>

            <li class="sidebar-divider"></li>

            <!-- المنيو والأصناف -->
            <?php if (hasPermission('products.view')): ?>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/products/index.php" class="sidebar-link <?php echo strpos($_SERVER['PHP_SELF'], 'products') !== false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">☕</span>
                    <span class="sidebar-text">المنيو والأصناف</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- فئات المنيو -->
            <?php if (hasPermission('categories.view')): ?>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/categories/index.php" class="sidebar-link <?php echo strpos($_SERVER['PHP_SELF'], 'categories') !== false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">🏷️</span>
                    <span class="sidebar-text">أقسام المنيو</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- مخزون الخامات والوصفات -->
            <?php if (hasPermission('recipes.view')): ?>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/recipes/index.php" class="sidebar-link <?php echo strpos($_SERVER['PHP_SELF'], 'recipes') !== false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">🥛</span>
                    <span class="sidebar-text">الخامات والمقادير</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- المصروفات -->
            <?php if (hasPermission('expenses.view') || hasPermission('expenses.add')): ?>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/expenses/index.php" class="sidebar-link <?php echo strpos($_SERVER['PHP_SELF'], 'expenses') !== false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">💸</span>
                    <span class="sidebar-text">المصروفات</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- الموردين ومشتريات الخامات -->
            <?php if (hasPermission('suppliers.view')): ?>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/suppliers/index.php" class="sidebar-link <?php echo strpos($_SERVER['PHP_SELF'], 'suppliers') !== false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">🚚</span>
                    <span class="sidebar-text">الموردين والمشتريات</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- فواتير الشراء -->
            <?php if (hasAnyPermission(['invoices.purchase.view', 'invoices.purchase.create'])): ?>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/invoices/list.php?type=purchase" class="sidebar-link <?php echo strpos($_SERVER['PHP_SELF'], 'invoices') !== false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">📥</span>
                    <span class="sidebar-text">فواتير الشراء</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- المخزون -->
            <?php if (hasPermission('inventory.view')): ?>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/inventory/index.php" class="sidebar-link <?php echo strpos($_SERVER['PHP_SELF'], 'inventory') !== false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">🏭</span>
                    <span class="sidebar-text">المخزون</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- العملاء -->
            <?php if (hasPermission('customers.view')): ?>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/customers/index.php" class="sidebar-link <?php echo strpos($_SERVER['PHP_SELF'], 'customers') !== false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">👥</span>
                    <span class="sidebar-text">العملاء</span>
                </a>
            </li>
            <?php endif; ?>

            <li class="sidebar-divider"></li>

            <!-- التقارير -->
            <?php if (isAdmin() || hasPermission('reports.view')): ?>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/reports/index.php" class="sidebar-link <?php echo strpos($_SERVER['PHP_SELF'], 'reports') !== false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">📊</span>
                    <span class="sidebar-text">التقارير والأرباح</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- الإعدادات -->
            <?php if (hasPermission('settings.manage')): ?>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/settings/index.php" class="sidebar-link <?php echo strpos($_SERVER['PHP_SELF'], 'settings') !== false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">⚙️</span>
                    <span class="sidebar-text">الإعدادات</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- المستخدمين -->
            <?php if (hasPermission('users.manage')): ?>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/users/index.php" class="sidebar-link <?php echo strpos($_SERVER['PHP_SELF'], 'users') !== false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">🔐</span>
                    <span class="sidebar-text">المستخدمين</span>
                </a>
            </li>
            <?php endif; ?>

            <li>
                <a href="<?php echo BASE_URL; ?>modules/about/index.php" class="sidebar-link <?php echo strpos($_SERVER['PHP_SELF'], 'about') !== false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">ℹ️</span>
                    <span class="sidebar-text">حول النظام</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>

<!-- Main Content Wrapper Start -->
<!-- Main Content Wrapper Start -->
<div class="main-content" id="mainContent">

<!-- Urgent Renewal Alert Banner (Shown when <= 7 days left) -->
<?php
$licenseDetails = function_exists('getLicenseDetails') ? getLicenseDetails() : null;
if ($licenseDetails && $licenseDetails['status'] === 'valid' && empty($licenseDetails['is_dev']) && empty($licenseDetails['is_lifetime']) && !empty($licenseDetails['days_left']) && (int)$licenseDetails['days_left'] <= 7):
    $remDays = (int)$licenseDetails['days_left'];
    $remMins = (int)($licenseDetails['mins_left'] ?? 0);
    $expDate = $licenseDetails['expires_at'] ?? '';
    if (is_numeric($expDate)) {
        $expDate = date('Y-m-d H:i:s', $expDate);
    }
    $isMinTest = !empty($licenseDetails['is_test']) || ($remMins <= 60 && $remDays == 0);
    $durationNotice = $isMinTest ? "متبقي {$remMins} دقيقة فقط" : "متبقي {$remDays} أيام فقط";
?>
<div id="licenseExpiryAlertBanner" class="noprint" style="background: linear-gradient(135deg, #fff7ed, #ffedd5); border: 1px solid #f97316; border-radius: 10px; padding: 12px 20px; margin-bottom: 18px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 12px rgba(249, 115, 22, 0.12);">
    <div style="display: flex; align-items: center; gap: 12px; color: #9a3412;">
        <span style="font-size: 22px;">⚠️</span>
        <div style="font-size: 13.5px; font-weight: 600; line-height: 1.5;">
            <strong style="font-size: 14px; color: #c2410c;">تنبيه هام بقرب انتهاء الاشتراك:</strong>
            <?php echo $durationNotice; ?> (تاريخ وموعد الانتهاء: <span style="text-decoration: underline; font-weight: 700; color: #9a3412;"><?php echo $expDate; ?></span>). يرجى تجديد الاشتراك لتفادي توقف النظام وحظر العمل.
        </div>
    </div>
    <div style="display: flex; align-items: center; gap: 10px;">
        <a href="<?php echo BASE_URL; ?>activate.php" class="btn" style="background: #ea580c; color: white; font-weight: 700; padding: 6px 16px; font-size: 12.5px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 8px rgba(234, 88, 12, 0.3);">
            <span>🔑</span> تجديد الاشتراك الآن
        </a>
        <button type="button" onclick="document.getElementById('licenseExpiryAlertBanner').style.display='none'" style="background: transparent; border: none; font-size: 22px; line-height: 1; color: #9a3412; cursor: pointer; padding: 0 4px;" title="إغلاق التنبيه">&times;</button>
    </div>
</div>
<?php endif; ?>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const topHeader = document.querySelector('.top-header');
    
    sidebar.classList.toggle('collapsed');
    mainContent.classList.toggle('expanded');
    topHeader.classList.toggle('expanded');
    
    // Save state
    localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
}

function toggleNotifications(e) {
    e.stopPropagation();
    const dropdown = document.getElementById('notificationDropdown');
    // Close user menu if open
    document.getElementById('userMenuDropdown').classList.remove('show');
    dropdown.classList.toggle('show');
}

function toggleUserMenu(e) {
    e.stopPropagation();
    const dropdown = document.getElementById('userMenuDropdown');
    // Close notification dropdown if open
    document.getElementById('notificationDropdown').classList.remove('show');
    dropdown.classList.toggle('show');
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    const notifWrapper = document.querySelector('.notification-wrapper');
    document.getElementById('notificationDropdown').classList.remove('show');
    document.getElementById('userMenuDropdown').classList.remove('show');
});

// Prevent dropdown clicks from closing
document.querySelectorAll('.notification-dropdown').forEach(function(dd) {
    dd.addEventListener('click', function(e) {
        e.stopPropagation();
    });
});

// Restore sidebar state
document.addEventListener('DOMContentLoaded', function() {
    if (localStorage.getItem('sidebarCollapsed') === 'true') {
        document.getElementById('sidebar').classList.add('collapsed');
        document.getElementById('mainContent').classList.add('expanded');
        document.querySelector('.top-header').classList.add('expanded');
    }
});
</script>
