<?php
/**
 * Users Management - List
 * إدارة المستخدمين - القائمة
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';

requirePermission('users.manage');

$pageTitle = 'إدارة المستخدمين';

// Get all users
$users = getRows("SELECT u.*, 
    w.name as warehouse_name,
    (SELECT COUNT(*) FROM user_permissions WHERE user_id = u.id) as perm_count,
    (SELECT full_name FROM users WHERE id = u.created_by) as created_by_name
    FROM users u 
    LEFT JOIN warehouses w ON u.default_warehouse_id = w.id
    ORDER BY u.role ASC, u.created_at DESC");

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1 style="margin: 0;">🔐 إدارة المستخدمين</h1>
        <a href="add.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
            <span>➕</span> إضافة مستخدم جديد
        </a>
    </div>

    <!-- Stats -->
    <div class="stats-grid" style="margin-bottom: 20px;">
        <div class="stat-card info">
            <div class="stat-icon">👥</div>
            <div class="stat-label">إجمالي المستخدمين</div>
            <div class="stat-value"><?php echo count($users); ?></div>
        </div>
        <div class="stat-card success">
            <div class="stat-icon">✅</div>
            <div class="stat-label">نشط</div>
            <div class="stat-value"><?php echo count(array_filter($users, fn($u) => $u['is_active'])); ?></div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon">👑</div>
            <div class="stat-label">مدراء</div>
            <div class="stat-value"><?php echo count(array_filter($users, fn($u) => $u['role'] === 'admin')); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">👤</div>
            <div class="stat-label">موظفين</div>
            <div class="stat-value"><?php echo count(array_filter($users, fn($u) => $u['role'] === 'employee')); ?></div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card">
        <div class="card-header" style="background: linear-gradient(135deg, #3b82f6, #2563eb); color: white;">
            📋 قائمة المستخدمين
        </div>
        <div class="card-body" style="padding: 0; overflow-x: auto;">
            <table class="table" style="margin: 0;">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>المستخدم</th>
                        <th>اسم المستخدم</th>
                        <th>الدور</th>
                        <th>المخزن الافتراضي</th>
                        <th>الحالة</th>
                        <th>الصلاحيات</th>
                        <th>آخر دخول</th>
                        <th style="width: 200px;">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div class="user-avatar-sm" style="width: 38px; height: 38px; border-radius: 10px; overflow: hidden; flex-shrink: 0; background: linear-gradient(135deg, #3b82f6, #8b5cf6); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 0.9em;">
                                    <?php
                                    $avatarUrl = getAvatarUrl($user['avatar']);
                                    if ($avatarUrl):
                                    ?>
                                        <img src="<?php echo $avatarUrl; ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <?php echo mb_substr($user['full_name'], 0, 1); ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                    <?php if ($user['phone']): ?>
                                        <div style="font-size: 0.8em; color: #6b7280;"><?php echo $user['phone']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><span style="display: inline-block; background: #eef2ff; color: #312e81; border: 1px solid #c7d2fe; padding: 4px 10px; border-radius: 8px; font-weight: 700; font-size: 0.9em; font-family: monospace; letter-spacing: 0.5px;"><?php echo htmlspecialchars($user['username']); ?></span></td>
                        <td>
                            <?php if ($user['role'] === 'admin'): ?>
                                <span class="badge" style="background: linear-gradient(135deg, #f59e0b, #d97706); color: white; padding: 3px 10px; border-radius: 20px; font-size: 0.8em;">👑 مدير</span>
                            <?php else: ?>
                                <span class="badge" style="background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; padding: 3px 10px; border-radius: 20px; font-size: 0.8em;">👤 موظف</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge" style="background: #e0f2fe; color: #0369a1; padding: 4px 10px; border-radius: 12px; font-weight: 600; font-size: 0.85em;">
                                🏢 <?php echo htmlspecialchars($user['warehouse_name'] ?? 'المخزن الرئيسي'); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($user['is_active']): ?>
                                <span style="color: #10b981; font-weight: 600;">● نشط</span>
                            <?php else: ?>
                                <span style="color: #ef4444; font-weight: 600;">● معطل</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($user['role'] === 'admin'): ?>
                                <span style="color: #f59e0b; font-size: 0.85em;">كل الصلاحيات</span>
                            <?php else: ?>
                                <span style="color: #3b82f6; font-size: 0.85em;"><?php echo $user['perm_count']; ?> صلاحية</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($user['last_login']): ?>
                                <div style="font-size: 0.85em;">
                                    <?php echo date('Y/m/d', strtotime($user['last_login'])); ?>
                                    <br>
                                    <span style="color: #6b7280;"><?php echo date('h:i A', strtotime($user['last_login'])); ?></span>
                                </div>
                            <?php else: ?>
                                <span style="color: #6b7280; font-size: 0.85em;">لم يسجل دخول</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                <a href="edit.php?id=<?php echo $user['id']; ?>" class="btn btn-sm" style="background: #3b82f6; color: white; padding: 4px 10px; border-radius: 6px; font-size: 0.8em; text-decoration: none;">✏️ تعديل</a>
                                
                                <?php if ($user['role'] !== 'admin' || count(array_filter($users, fn($u) => $u['role'] === 'admin')) > 1): ?>
                                    <a href="toggle.php?id=<?php echo $user['id']; ?>" class="btn btn-sm" style="background: <?php echo $user['is_active'] ? '#f59e0b' : '#10b981'; ?>; color: white; padding: 4px 10px; border-radius: 6px; font-size: 0.8em; text-decoration: none;">
                                        <?php echo $user['is_active'] ? '⏸️ تعطيل' : '▶️ تفعيل'; ?>
                                    </a>
                                    
                                    <?php if ($user['id'] != getCurrentUserId()): ?>
                                        <a href="delete.php?id=<?php echo $user['id']; ?>" class="btn btn-sm" style="background: #ef4444; color: white; padding: 4px 10px; border-radius: 6px; font-size: 0.8em; text-decoration: none;" onclick="return confirm('هل أنت متأكد من حذف هذا المستخدم؟');">🗑️ حذف</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Sessions Log Link -->
    <div style="margin-top: 15px; text-align: center;">
        <a href="sessions.php" style="color: #3b82f6; text-decoration: none; font-weight: 600;">
            📋 عرض سجل الجلسات (من دخل ومتى)
        </a>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
