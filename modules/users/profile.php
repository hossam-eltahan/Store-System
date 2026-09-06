<?php
/**
 * User Profile
 * الملف الشخصي
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';

requireLogin();

$pageTitle = 'الملف الشخصي';
$userId = getCurrentUserId();
$user = getRow("SELECT * FROM users WHERE id = ?", [$userId]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = sanitize($_POST['full_name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    $errors = [];
    if (empty($fullName)) $errors[] = 'الاسم الكامل مطلوب';
    
    // Password change
    if (!empty($newPassword)) {
        if (empty($currentPassword)) {
            $errors[] = 'يجب إدخال كلمة المرور الحالية';
        } elseif (!password_verify($currentPassword, $user['password'])) {
            $errors[] = 'كلمة المرور الحالية غير صحيحة';
        }
        if (strlen($newPassword) < 6) $errors[] = 'كلمة المرور الجديدة يجب أن تكون 6 أحرف على الأقل';
        if ($newPassword !== $confirmPassword) $errors[] = 'كلمة المرور الجديدة وتأكيدها غير متطابقين';
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
        
        execute(
            "UPDATE users SET full_name = ?, phone = ?, email = ?, avatar = ? WHERE id = ?",
            [$fullName, $phone ?: null, $email ?: null, $avatar, $userId]
        );
        
        // Update password if provided
        if (!empty($newPassword)) {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            execute("UPDATE users SET password = ? WHERE id = ?", [$hashedPassword, $userId]);
        }
        
        // Update session
        $_SESSION['full_name'] = $fullName;
        $_SESSION['user_avatar'] = $avatar;
        
        logActivity('تعديل الملف الشخصي', 'تم تعديل بيانات الملف الشخصي', getCurrentUserName());
        setSuccess('تم تحديث بيانات الملف الشخصي بنجاح');
        redirect('profile.php');
    }
}

// Reload user data
$user = getRow("SELECT * FROM users WHERE id = ?", [$userId]);

// Get recent activity
$recentActivity = getRows(
    "SELECT * FROM activity_log WHERE user_id = ? OR user = ? ORDER BY created_at DESC LIMIT 10",
    [$userId, $user['full_name']]
);

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container">
    <h1 style="margin-bottom: 20px;">👤 الملف الشخصي</h1>

    <?php if (!empty($errors)): ?>
        <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 10px; padding: 15px; margin-bottom: 20px; color: #fca5a5;">
            <strong>⚠️</strong>
            <ul style="margin: 8px 0 0; padding-right: 20px;">
                <?php foreach ($errors as $err): ?><li><?php echo $err; ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="grid grid-2" style="gap: 20px;">
            <!-- Profile Info -->
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #3b82f6, #2563eb); color: white;">
                    📝 البيانات الشخصية
                </div>
                <div class="card-body">
                    <!-- Avatar -->
                    <div style="text-align: center; margin-bottom: 20px;">
                        <div style="width: 100px; height: 100px; border-radius: 20px; overflow: hidden; margin: 0 auto 10px; background: linear-gradient(135deg, #3b82f6, #8b5cf6); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 2.5em; box-shadow: 0 8px 25px rgba(59, 130, 246, 0.2);">
                            <?php $avatarUrl = getAvatarUrl($user['avatar']); if ($avatarUrl): ?>
                                <img src="<?php echo $avatarUrl; ?>" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <?php echo mb_substr($user['full_name'], 0, 1); ?>
                            <?php endif; ?>
                        </div>
                        <div style="font-weight: 700; font-size: 1.2em;"><?php echo htmlspecialchars($user['full_name']); ?></div>
                        <div style="color: #6b7280; font-size: 0.9em;">
                            <?php echo $user['role'] === 'admin' ? '👑 مدير' : '👤 موظف'; ?>
                            · @<?php echo $user['username']; ?>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 5px;">الاسم الكامل *</label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 5px;">رقم الهاتف</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 5px;">البريد الإلكتروني</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" dir="ltr" style="text-align: left;">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 5px;">تغيير الصورة</label>
                        <input type="file" name="avatar" class="form-control" accept="image/*">
                    </div>
                </div>
            </div>

            <!-- Password Change -->
            <div>
                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-header" style="background: linear-gradient(135deg, #f59e0b, #d97706); color: white;">
                        🔒 تغيير كلمة المرور
                    </div>
                    <div class="card-body">
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label style="font-weight: 600; display: block; margin-bottom: 5px;">كلمة المرور الحالية</label>
                            <input type="password" name="current_password" class="form-control" placeholder="مطلوب فقط عند تغيير كلمة المرور">
                        </div>
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label style="font-weight: 600; display: block; margin-bottom: 5px;">كلمة المرور الجديدة</label>
                            <input type="password" name="new_password" class="form-control" placeholder="6 أحرف على الأقل" minlength="6">
                        </div>
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label style="font-weight: 600; display: block; margin-bottom: 5px;">تأكيد كلمة المرور</label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="أعد إدخال كلمة المرور الجديدة">
                        </div>
                    </div>
                </div>

                <!-- Account Info -->
                <div class="card">
                    <div class="card-header">ℹ️ معلومات الحساب</div>
                    <div class="card-body" style="font-size: 0.9em;">
                        <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid rgba(71,85,105,0.2);">
                            <span style="color: #6b7280;">اسم المستخدم</span>
                            <span style="font-weight: 600;"><?php echo $user['username']; ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid rgba(71,85,105,0.2);">
                            <span style="color: #6b7280;">الدور</span>
                            <span><?php echo $user['role'] === 'admin' ? '👑 مدير' : '👤 موظف'; ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid rgba(71,85,105,0.2);">
                            <span style="color: #6b7280;">تاريخ الإنشاء</span>
                            <span><?php echo date('Y/m/d', strtotime($user['created_at'])); ?></span>
                        </div>
                        <?php if ($user['last_login']): ?>
                        <div style="display: flex; justify-content: space-between; padding: 6px 0;">
                            <span style="color: #6b7280;">آخر دخول</span>
                            <span><?php echo date('Y/m/d h:i A', strtotime($user['last_login'])); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top: 20px; text-align: center;">
            <button type="submit" class="btn btn-primary" style="padding: 12px 40px; font-size: 1.05em; border-radius: 10px; background: linear-gradient(135deg, #10b981, #059669); border: none; color: white; cursor: pointer; font-family: 'Cairo', sans-serif; font-weight: 600;">
                💾 حفظ التغييرات
            </button>
        </div>
    </form>
</div>

<?php include '../../includes/footer.php'; ?>
