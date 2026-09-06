<?php
/**
 * Edit User
 * تعديل مستخدم
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';

requirePermission('users.manage');

$userId = (int)($_GET['id'] ?? 0);
if (!$userId) { redirect('index.php'); }

$user = getRow("SELECT * FROM users WHERE id = ?", [$userId]);
if (!$user) { setError('المستخدم غير موجود'); redirect('index.php'); }

$pageTitle = 'تعديل المستخدم - ' . $user['full_name'];
$allPermissions = getAllPermissions();
$userPerms = getUserPermissions($userId);
$warehouses = getAllWarehouses(true);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = sanitize($_POST['full_name'] ?? '');
    $username = trim(sanitize($_POST['username'] ?? ''));
    $phone = sanitize($_POST['phone'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $role = $_POST['role'] ?? $user['role'];
    $defaultWarehouseId = (int)($_POST['default_warehouse_id'] ?? $user['default_warehouse_id'] ?? 1);
    $newPassword = $_POST['new_password'] ?? '';
    $permissions = $_POST['permissions'] ?? [];
    
    $errors = [];
    if (empty($fullName)) $errors[] = 'الاسم الكامل مطلوب';
    if (empty($username)) {
        $errors[] = 'اسم المستخدم مطلوب';
    } elseif (!preg_match('/^[a-zA-Z0-9_.-]{3,30}$/', $username)) {
        $errors[] = 'اسم المستخدم يجب أن يكون بين 3 و 30 حرفاً (أحرف إنجليزية، أرقام، أو شرطة)';
    } else {
        $existing = getRow("SELECT id FROM users WHERE username = ? AND id != ?", [$username, $userId]);
        if ($existing) {
            $errors[] = 'اسم المستخدم هذا مستخدم بالفعل، يرجى اختيار اسم آخر';
        }
    }
    if (!empty($newPassword) && strlen($newPassword) < 6) $errors[] = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل';
    
    // Prevent removing admin role from last admin
    if ($user['role'] === 'admin' && $role !== 'admin') {
        $adminCount = getRow("SELECT COUNT(*) as cnt FROM users WHERE role = 'admin' AND id != ?", [$userId]);
        if ($adminCount['cnt'] == 0) {
            $errors[] = 'لا يمكن تغيير دور آخر مدير في النظام';
        }
    }
    
    if (empty($errors)) {
        // Handle avatar upload
        $avatar = $user['avatar'];
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $newAvatar = uploadImage($_FILES['avatar'], 'avatars');
            if ($newAvatar) {
                $avatar = $newAvatar;
            }
        }
        
        // Update user
        execute(
            "UPDATE users SET username = ?, full_name = ?, phone = ?, email = ?, avatar = ?, role = ?, default_warehouse_id = ? WHERE id = ?",
            [$username, $fullName, $phone ?: null, $email ?: null, $avatar, $role, $defaultWarehouseId, $userId]
        );
        
        // Update password if provided
        if (!empty($newPassword)) {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            execute("UPDATE users SET password = ? WHERE id = ?", [$hashedPassword, $userId]);
        }
        
        // Set permissions for employees
        if ($role === 'employee') {
            setUserPermissions($userId, $permissions);
        }

        // Sync session if updating current logged-in user
        if ($userId == getCurrentUserId()) {
            $_SESSION['username'] = $username;
            $_SESSION['full_name'] = $fullName;
            $_SESSION['default_warehouse_id'] = $defaultWarehouseId;
        }
        
        logActivity('تعديل مستخدم', "تم تعديل بيانات المستخدم: {$fullName} ({$username})", getCurrentUserName());
        setSuccess("تم تعديل بيانات المستخدم \"{$fullName}\" بنجاح");
        redirect('index.php');
    }
}

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container">
    <div class="page-header" style="margin-bottom: 20px;">
        <h1>✏️ تعديل المستخدم: <?php echo htmlspecialchars($user['full_name']); ?></h1>
        <a href="index.php" style="color: #3b82f6; text-decoration: none;">← العودة لقائمة المستخدمين</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 10px; padding: 15px; margin-bottom: 20px; color: #fca5a5;">
            <strong>⚠️ يرجى تصحيح الأخطاء:</strong>
            <ul style="margin: 8px 0 0 0; padding-right: 20px;">
                <?php foreach ($errors as $err): ?>
                    <li><?php echo $err; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="grid grid-2" style="gap: 20px;">
            <!-- User Details Card -->
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #3b82f6, #2563eb); color: white;">
                    👤 بيانات المستخدم
                </div>
                <div class="card-body">
                    <!-- Current Avatar -->
                    <div style="text-align: center; margin-bottom: 15px;">
                        <div style="width: 80px; height: 80px; border-radius: 16px; overflow: hidden; margin: 0 auto 10px; background: linear-gradient(135deg, #3b82f6, #8b5cf6); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 2em;">
                            <?php
                            $avatarUrl = getAvatarUrl($user['avatar']);
                            if ($avatarUrl):
                            ?>
                                <img src="<?php echo $avatarUrl; ?>" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <?php echo mb_substr($user['full_name'], 0, 1); ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label class="form-label" style="font-weight: 600; margin-bottom: 5px; display: block;">الاسم الكامل *</label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label class="form-label" style="font-weight: 600; margin-bottom: 5px; display: block;">اسم المستخدم *</label>
                        <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($_POST['username'] ?? $user['username']); ?>" required dir="ltr" style="text-align: left;" autocomplete="username">
                        <small style="color: #6b7280;">يمكن للمدير تعديل اسم المستخدم لتسجيل الدخول</small>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label class="form-label" style="font-weight: 600; margin-bottom: 5px; display: block;">كلمة المرور الجديدة</label>
                        <input type="password" name="new_password" class="form-control" placeholder="اتركه فارغاً للإبقاء على كلمة المرور الحالية" minlength="6">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label class="form-label" style="font-weight: 600; margin-bottom: 5px; display: block;">رقم الهاتف</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label class="form-label" style="font-weight: 600; margin-bottom: 5px; display: block;">البريد الإلكتروني</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" dir="ltr" style="text-align: left;">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label class="form-label" style="font-weight: 600; margin-bottom: 5px; display: block;">تغيير صورة المستخدم</label>
                        <input type="file" name="avatar" class="form-control" accept="image/*">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label class="form-label" style="font-weight: 600; margin-bottom: 5px; display: block;">الدور *</label>
                        <select name="role" id="roleSelect" class="form-control" onchange="togglePermissions()">
                            <option value="employee" <?php echo $user['role'] === 'employee' ? 'selected' : ''; ?>>👤 موظف</option>
                            <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>👑 مدير</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label class="form-label" style="font-weight: 600; margin-bottom: 5px; display: block;">🏢 المخزن الافتراضي للمستخدم *</label>
                        <select name="default_warehouse_id" class="form-control" required style="font-weight: 600;">
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="<?php echo $wh['id']; ?>" <?php echo ((int)($_POST['default_warehouse_id'] ?? $user['default_warehouse_id']) === (int)$wh['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($wh['name']); ?> <?php echo ((int)$wh['is_default'] === 1) ? '(الافتراضي)' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: #6b7280;">المخزن الذي سيتم اختياره تلقائياً عند قيام هذا المستخدم بإنشاء الفواتير</small>
                    </div>
                    
                    <!-- User Info -->
                    <div style="background: rgba(30, 41, 59, 0.3); border-radius: 8px; padding: 10px; font-size: 0.85em; color: #6b7280;">
                        <div>📅 تاريخ الإنشاء: <?php echo date('Y/m/d h:i A', strtotime($user['created_at'])); ?></div>
                        <?php if ($user['last_login']): ?>
                            <div>🕐 آخر دخول: <?php echo date('Y/m/d h:i A', strtotime($user['last_login'])); ?></div>
                            <div>🌐 IP: <?php echo $user['last_login_ip'] ?? '-'; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Permissions Card -->
            <div class="card" id="permissionsCard">
                <div class="card-header" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; display: flex; justify-content: space-between; align-items: center;">
                    <span>🔐 الصلاحيات</span>
                    <div style="display: flex; gap: 8px;">
                        <button type="button" onclick="selectAll()" style="background: rgba(255,255,255,0.2); border: none; color: white; padding: 3px 10px; border-radius: 5px; font-size: 0.8em; cursor: pointer; font-family: 'Cairo', sans-serif;">تحديد الكل</button>
                        <button type="button" onclick="deselectAll()" style="background: rgba(255,255,255,0.2); border: none; color: white; padding: 3px 10px; border-radius: 5px; font-size: 0.8em; cursor: pointer; font-family: 'Cairo', sans-serif;">إلغاء الكل</button>
                    </div>
                </div>
                <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                    <div id="adminNote" style="display: none; background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 8px; padding: 12px; margin-bottom: 15px; color: #f59e0b; text-align: center;">
                        👑 المدير يملك جميع الصلاحيات تلقائياً
                    </div>
                    
                    <div id="permissionsContainer">
                        <?php foreach ($allPermissions as $groupKey => $group): ?>
                            <?php if ($groupKey === 'users') continue; ?>
                            <div class="perm-group" style="margin-bottom: 18px; background: rgba(30, 41, 59, 0.3); border-radius: 10px; padding: 12px;">
                                <div style="font-weight: 700; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; font-size: 0.95em;">
                                    <span><?php echo $group['icon']; ?></span>
                                    <span><?php echo $group['label']; ?></span>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px;">
                                    <?php foreach ($group['permissions'] as $permKey => $permLabel): ?>
                                        <label style="display: flex; align-items: center; gap: 6px; padding: 6px 8px; border-radius: 6px; cursor: pointer; transition: background 0.2s; font-size: 0.9em;" onmouseover="this.style.background='rgba(59,130,246,0.1)'" onmouseout="this.style.background='transparent'">
                                            <input type="checkbox" name="permissions[]" value="<?php echo $permKey; ?>" class="perm-checkbox" <?php echo in_array($permKey, $userPerms) ? 'checked' : ''; ?>>
                                            <span><?php echo $permLabel; ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top: 20px; text-align: center;">
            <button type="submit" class="btn btn-primary" style="padding: 12px 40px; font-size: 1.05em; border-radius: 10px; background: linear-gradient(135deg, #3b82f6, #2563eb); border: none; color: white; cursor: pointer; font-family: 'Cairo', sans-serif; font-weight: 600;">
                💾 حفظ التعديلات
            </button>
            <a href="index.php" style="margin-right: 15px; color: #6b7280; text-decoration: none;">إلغاء</a>
        </div>
    </form>
</div>

<script>
function togglePermissions() {
    const role = document.getElementById('roleSelect').value;
    const container = document.getElementById('permissionsContainer');
    const adminNote = document.getElementById('adminNote');
    if (role === 'admin') {
        container.style.display = 'none';
        adminNote.style.display = 'block';
    } else {
        container.style.display = 'block';
        adminNote.style.display = 'none';
    }
}
function selectAll() { document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = true); }
function deselectAll() { document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = false); }
togglePermissions();
</script>

<?php include '../../includes/footer.php'; ?>
