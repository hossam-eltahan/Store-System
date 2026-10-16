<?php
/**
 * User Sessions Log
 * سجل الجلسات
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';

requirePermission('users.manage');

$pageTitle = 'سجل الجلسات';

// Filter by user
$filterUser = $_GET['user_id'] ?? '';

$where = '';
$params = [];
if ($filterUser) {
    $where = 'WHERE s.user_id = ?';
    $params[] = $filterUser;
}

// Pagination (filter-preserving)
$perPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$totalItems = (int)(getRow(
    "SELECT COUNT(*) AS c FROM user_sessions s JOIN users u ON s.user_id = u.id $where",
    $params
)['c'] ?? 0);
$totalPages = max(1, (int)ceil($totalItems / $perPage));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }

$sessions = getRows(
    "SELECT s.*, u.full_name, u.username, u.role
     FROM user_sessions s 
     JOIN users u ON s.user_id = u.id 
     $where
     ORDER BY s.created_at DESC 
     LIMIT $perPage OFFSET $offset",
    $params
);

$users = getRows("SELECT id, full_name, username FROM users ORDER BY full_name");

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>📋 سجل الجلسات</h1>
        <a href="index.php" style="color: #3b82f6; text-decoration: none;">← العودة لإدارة المستخدمين</a>
    </div>

    <!-- Filter -->
    <div class="card" style="margin-bottom: 20px;">
        <div class="card-body" style="padding: 12px;">
            <form method="GET" style="display: flex; gap: 10px; align-items: center;">
                <label style="font-weight: 600;">تصفية حسب المستخدم:</label>
                <select name="user_id" class="form-control" style="max-width: 250px;">
                    <option value="">جميع المستخدمين</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo $filterUser == $u['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($u['full_name']); ?> (<?php echo $u['username']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" style="background: #3b82f6; color: white; border: none; padding: 8px 20px; border-radius: 8px; cursor: pointer; font-family: 'Cairo', sans-serif;">🔍 عرض</button>
                <?php if ($filterUser): ?>
                    <a href="sessions.php" style="color: #6b7280; text-decoration: none;">إلغاء الفلتر</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Sessions Table -->
    <div class="card">
        <div class="card-header" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white;">
            📋 سجل الجلسات (<?php echo $totalItems; ?>)
        </div>
        <div class="card-body" style="padding: 0; overflow-x: auto;">
            <table class="table" style="margin: 0;">
                <thead>
                    <tr>
                        <th>المستخدم</th>
                        <th>الدور</th>
                        <th>وقت الدخول</th>
                        <th>وقت الخروج</th>
                        <th>IP</th>
                        <th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sessions)): ?>
                        <tr><td colspan="6" style="text-align: center; padding: 30px; color: #6b7280;">لا توجد جلسات مسجلة</td></tr>
                    <?php else: ?>
                        <?php foreach ($sessions as $session): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div style="width: 32px; height: 32px; border-radius: 8px; overflow: hidden; background: linear-gradient(135deg, #3b82f6, #8b5cf6); display: flex; align-items: center; justify-content: center; color: white; font-size: 0.8em; font-weight: bold; flex-shrink: 0;">
                                        <?php echo mb_substr($session['full_name'], 0, 1); ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600; font-size: 0.9em;"><?php echo htmlspecialchars($session['full_name']); ?></div>
                                        <div style="font-size: 0.75em; color: #6b7280;">@<?php echo $session['username']; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php echo $session['role'] === 'admin' ? '<span style="color: #f59e0b;">👑 مدير</span>' : '<span style="color: #3b82f6;">👤 موظف</span>'; ?>
                            </td>
                            <td style="font-size: 0.85em;">
                                <?php echo date('Y/m/d h:i:s A', strtotime($session['created_at'])); ?>
                            </td>
                            <td style="font-size: 0.85em;">
                                <?php if (!empty($session['expires_at'])): ?>
                                    <?php echo date('Y/m/d h:i:s A', strtotime($session['expires_at'])); ?>
                                <?php else: ?>
                                    <span style="color: #6b7280;">-</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.85em; font-family: monospace;">
                                <?php echo htmlspecialchars($session['ip_address'] ?? '-'); ?>
                            </td>
                            <td>
                                <?php if (!empty($session['expires_at']) && strtotime($session['expires_at']) > time()): ?>
                                    <span style="color: #10b981; font-weight: 600; font-size: 0.85em;">🟢 نشطة</span>
                                <?php else: ?>
                                    <span style="color: #6b7280; font-size: 0.85em;">⚫ منتهية</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div style="display: flex; gap: 6px; justify-content: center; align-items: center; padding: 12px; flex-wrap: wrap;">
            <?php if ($page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" style="padding: 6px 14px; border: 1px solid #cbd5e1; border-radius: 8px; text-decoration: none; color: #334155; background: #fff;">→ السابق</a>
            <?php endif; ?>
            <span style="font-size: 0.85em; color: #64748b;">صفحة <?php echo $page; ?> من <?php echo $totalPages; ?></span>
            <?php if ($page < $totalPages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" style="padding: 6px 14px; border: 1px solid #cbd5e1; border-radius: 8px; text-decoration: none; color: #334155; background: #fff;">التالي ←</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
