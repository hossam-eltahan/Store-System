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
    <button class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()">
        <span class="toggle-icon">☰</span>
    </button>
    
    <div class="header-title"><?php echo getSetting('store_name', 'نظام إدارة المحل'); ?></div>
    
    <div style="display: flex; align-items: center; gap: 12px;">
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
                            <?php echo $currentUser['role'] === 'admin' ? '👑 مدير' : '👤 موظف'; ?>
                            <?php 
                            $userWhId = getCurrentWarehouseId();
                            if ($userWhId) {
                                echo ' • 🏢 ' . htmlspecialchars(getWarehouseName($userWhId));
                            }
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
            <?php if (hasPermission('dashboard.view')): ?>
            <li>
                <a href="<?php echo BASE_URL; ?>index.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' && strpos($_SERVER['PHP_SELF'], 'modules') === false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">🏠</span>
                    <span class="sidebar-text">الرئيسية</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasPermission('products.view')): ?>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/products/index.php" class="sidebar-link <?php echo strpos($_SERVER['PHP_SELF'], 'products') !== false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">📦</span>
                    <span class="sidebar-text">الأصناف</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasPermission('categories.view')): ?>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/categories/index.php" class="sidebar-link <?php echo strpos($_SERVER['PHP_SELF'], 'categories') !== false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">🏷️</span>
                    <span class="sidebar-text">فئات الأصناف</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if (hasPermission('warehouses.view')): ?>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/warehouses/index.php" class="sidebar-link <?php echo strpos($_SERVER['PHP_SELF'], 'warehouses') !== false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">🏢</span>
                    <span class="sidebar-text">المخازن</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasPermission('suppliers.view')): ?>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/suppliers/index.php" class="sidebar-link <?php echo strpos($_SERVER['PHP_SELF'], 'suppliers') !== false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">🚚</span>
                    <span class="sidebar-text">الموردين</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasPermission('customers.view')): ?>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/customers/index.php" class="sidebar-link <?php echo strpos($_SERVER['PHP_SELF'], 'customers') !== false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">👥</span>
                    <span class="sidebar-text">العملاء</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasPermission('invoices.sale.create') || hasPermission('invoices.purchase.create') || hasPermission('invoices.sale.view')): ?>
            <li class="sidebar-divider"></li>
            <?php endif; ?>
            
            <?php if (hasPermission('invoices.sale.create')): ?>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/invoices/sale.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'sale.php' ? 'active' : ''; ?>">
                    <span class="sidebar-icon">🧾</span>
                    <span class="sidebar-text">فاتورة بيع</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasPermission('invoices.purchase.create')): ?>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/invoices/purchase.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'purchase.php' ? 'active' : ''; ?>">
                    <span class="sidebar-icon">📥</span>
                    <span class="sidebar-text">فاتورة شراء</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasPermission('invoices.sale.view') || hasPermission('invoices.purchase.view')): ?>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/invoices/list.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'list.php' && strpos($_SERVER['PHP_SELF'], 'invoices') !== false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">📋</span>
                    <span class="sidebar-text">الفواتير</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasPermission('installments.view') || hasPermission('installments.create') || hasPermission('payments.customer') || hasPermission('payments.supplier')): ?>
            <li class="sidebar-divider"></li>
            <?php endif; ?>
            
            <?php if (hasPermission('installments.view')): ?>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/installments/index.php" class="sidebar-link <?php echo strpos($_SERVER['PHP_SELF'], 'installments') !== false && basename($_SERVER['PHP_SELF']) != 'create.php' ? 'active' : ''; ?>">
                    <span class="sidebar-icon">💰</span>
                    <span class="sidebar-text">الأقساط</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasPermission('installments.create')): ?>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/installments/create.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'create.php' && strpos($_SERVER['PHP_SELF'], 'installments') !== false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">➕</span>
                    <span class="sidebar-text">إنشاء قسط</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasPermission('payments.customer')): ?>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/payments/pay_customer.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'pay_customer.php' ? 'active' : ''; ?>">
                    <span class="sidebar-icon">💵</span>
                    <span class="sidebar-text">تحصيل</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasPermission('payments.supplier')): ?>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/payments/pay_supplier.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'pay_supplier.php' ? 'active' : ''; ?>">
                    <span class="sidebar-icon">💸</span>
                    <span class="sidebar-text">دفع</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasPermission('payments.view')): ?>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/payments/index.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' && strpos($_SERVER['PHP_SELF'], 'payments') !== false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">📒</span>
                    <span class="sidebar-text">سجل المدفوعات</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (hasPermission('expenses.view') || hasPermission('expenses.add')): ?>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/expenses/index.php" class="sidebar-link <?php echo strpos($_SERVER['PHP_SELF'], 'expenses') !== false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">💸</span>
                    <span class="sidebar-text">المصروفات</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasPermission('returns.view') || hasPermission('reports.view') || hasPermission('reports.statement') || hasPermission('settings.manage') || hasPermission('users.manage')): ?>
            <li class="sidebar-divider"></li>
            <?php endif; ?>
            
            <?php if (hasPermission('returns.view')): ?>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/returns/index.php" class="sidebar-link <?php echo strpos($_SERVER['PHP_SELF'], 'returns') !== false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">🔄</span>
                    <span class="sidebar-text">المرتجعات</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (isAdmin()): ?>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/reports/index.php" class="sidebar-link <?php echo strpos($_SERVER['PHP_SELF'], 'reports') !== false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">📊</span>
                    <span class="sidebar-text">التقارير</span>
                </a>
            </li>
            <?php else: ?>
                <?php if (hasPermission('reports.view')): ?>
                <li>
                    <a href="<?php echo BASE_URL; ?>modules/reports/index.php" class="sidebar-link <?php echo strpos($_SERVER['PHP_SELF'], 'reports') !== false && (!isset($_GET['type']) || $_GET['type'] !== 'statement') ? 'active' : ''; ?>">
                        <span class="sidebar-icon">📊</span>
                        <span class="sidebar-text">تقاريري ونشاطي</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (hasPermission('reports.statement')): ?>
                <li>
                    <a href="<?php echo BASE_URL; ?>modules/reports/index.php?type=statement" class="sidebar-link <?php echo strpos($_SERVER['PHP_SELF'], 'reports') !== false && (isset($_GET['type']) && $_GET['type'] === 'statement') ? 'active' : ''; ?>">
                        <span class="sidebar-icon">🧾</span>
                        <span class="sidebar-text">كشف حساب</span>
                    </a>
                </li>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if (hasPermission('settings.manage')): ?>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/settings/index.php" class="sidebar-link <?php echo strpos($_SERVER['PHP_SELF'], 'settings') !== false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">⚙️</span>
                    <span class="sidebar-text">الإعدادات</span>
                </a>
            </li>
            <?php endif; ?>
            
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
<div class="main-content" id="mainContent">

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
