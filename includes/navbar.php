<?php
/**
 * Sidebar Navigation
 * نظام إدارة محل أجهزة منزلية
 */

// Include notifications helper
require_once __DIR__ . '/notifications.php';
$notificationCount = getNotificationCount();
$notifications = getNotifications();
?>

<!-- Top Header Bar -->
<header class="top-header noprint">
    <button class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()">
        <span class="toggle-icon">☰</span>
    </button>
    
    <div class="header-title"><?php echo getSetting('store_name', 'نظام إدارة المحل'); ?></div>
    
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
            <li>
                <a href="<?php echo BASE_URL; ?>index.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' && strpos($_SERVER['PHP_SELF'], 'modules') === false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">🏠</span>
                    <span class="sidebar-text">الرئيسية</span>
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/products/index.php" class="sidebar-link <?php echo strpos($_SERVER['PHP_SELF'], 'products') !== false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">📦</span>
                    <span class="sidebar-text">الأصناف</span>
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/suppliers/index.php" class="sidebar-link <?php echo strpos($_SERVER['PHP_SELF'], 'suppliers') !== false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">🚚</span>
                    <span class="sidebar-text">الموردين</span>
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/customers/index.php" class="sidebar-link <?php echo strpos($_SERVER['PHP_SELF'], 'customers') !== false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">👥</span>
                    <span class="sidebar-text">العملاء</span>
                </a>
            </li>
            
            <li class="sidebar-divider"></li>
            
            <li>
                <a href="<?php echo BASE_URL; ?>modules/invoices/sale.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'sale.php' ? 'active' : ''; ?>">
                    <span class="sidebar-icon">🧾</span>
                    <span class="sidebar-text">فاتورة بيع</span>
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/invoices/purchase.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'purchase.php' ? 'active' : ''; ?>">
                    <span class="sidebar-icon">📥</span>
                    <span class="sidebar-text">فاتورة شراء</span>
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/invoices/list.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'list.php' && strpos($_SERVER['PHP_SELF'], 'invoices') !== false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">📋</span>
                    <span class="sidebar-text">الفواتير</span>
                </a>
            </li>
            
            <li class="sidebar-divider"></li>
            
            <li>
                <a href="<?php echo BASE_URL; ?>modules/installments/index.php" class="sidebar-link <?php echo strpos($_SERVER['PHP_SELF'], 'installments') !== false && basename($_SERVER['PHP_SELF']) != 'create.php' ? 'active' : ''; ?>">
                    <span class="sidebar-icon">💰</span>
                    <span class="sidebar-text">الأقساط</span>
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/installments/create.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'create.php' && strpos($_SERVER['PHP_SELF'], 'installments') !== false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">➕</span>
                    <span class="sidebar-text">إنشاء قسط</span>
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/payments/pay_customer.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'pay_customer.php' ? 'active' : ''; ?>">
                    <span class="sidebar-icon">💵</span>
                    <span class="sidebar-text">تحصيل</span>
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/payments/pay_supplier.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'pay_supplier.php' ? 'active' : ''; ?>">
                    <span class="sidebar-icon">💸</span>
                    <span class="sidebar-text">دفع</span>
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/payments/index.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' && strpos($_SERVER['PHP_SELF'], 'payments') !== false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">📒</span>
                    <span class="sidebar-text">سجل المدفوعات</span>
                </a>
            </li>
            
            <li class="sidebar-divider"></li>
            
            <li>
                <a href="<?php echo BASE_URL; ?>modules/returns/index.php" class="sidebar-link <?php echo strpos($_SERVER['PHP_SELF'], 'returns') !== false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">🔄</span>
                    <span class="sidebar-text">المرتجعات</span>
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/reports/index.php" class="sidebar-link <?php echo strpos($_SERVER['PHP_SELF'], 'reports') !== false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">📊</span>
                    <span class="sidebar-text">التقارير</span>
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>modules/settings/index.php" class="sidebar-link <?php echo strpos($_SERVER['PHP_SELF'], 'settings') !== false ? 'active' : ''; ?>">
                    <span class="sidebar-icon">⚙️</span>
                    <span class="sidebar-text">الإعدادات</span>
                </a>
            </li>
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
    dropdown.classList.toggle('show');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    const wrapper = document.querySelector('.notification-wrapper');
    const dropdown = document.getElementById('notificationDropdown');
    if (wrapper && !wrapper.contains(e.target)) {
        dropdown.classList.remove('show');
    }
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
