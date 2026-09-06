<?php
/**
 * Add New User
 * إضافة مستخدم جديد
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';

requirePermission('users.manage');

$pageTitle = 'إضافة مستخدم جديد';
$allPermissions = getAllPermissions();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $fullName = sanitize($_POST['full_name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'employee';
    $defaultWarehouseId = (int)($_POST['default_warehouse_id'] ?? 1);
    $permissions = $_POST['permissions'] ?? [];
    
    // Validation
    $errors = [];
    if (empty($username)) $errors[] = 'اسم المستخدم مطلوب';
    if (strlen($username) < 3) $errors[] = 'اسم المستخدم يجب أن يكون 3 أحرف على الأقل';
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) $errors[] = 'اسم المستخدم يجب أن يحتوي على حروف إنجليزية وأرقام فقط';
    if (empty($password)) $errors[] = 'كلمة المرور مطلوبة';
    if (strlen($password) < 6) $errors[] = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل';
    if (empty($fullName)) $errors[] = 'الاسم الكامل مطلوب';
    
    // Check username uniqueness
    $existing = getRow("SELECT id FROM users WHERE username = ?", [$username]);
    if ($existing) $errors[] = 'اسم المستخدم مستخدم بالفعل';
    
    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Handle avatar upload
        $avatar = null;
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $avatar = uploadImage($_FILES['avatar'], 'avatars');
        }
        if (!$avatar) {
            $avatar = getRandomDefaultAvatar();
        }
        
        $userId = insert(
            "INSERT INTO users (username, password, full_name, phone, email, avatar, role, default_warehouse_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$username, $hashedPassword, $fullName, $phone ?: null, $email ?: null, $avatar, $role, $defaultWarehouseId, getCurrentUserId()]
        );
        
        if ($userId) {
            // Set permissions for employees
            if ($role === 'employee' && !empty($permissions)) {
                setUserPermissions($userId, $permissions);
            }
            
            logActivity('إضافة مستخدم', "تم إضافة المستخدم: $fullName ($username)", getCurrentUserName());
            setSuccess("تم إضافة المستخدم \"$fullName\" بنجاح");
            redirect('index.php');
        } else {
            $errors[] = 'حدث خطأ أثناء إضافة المستخدم';
        }
    }
}

$warehouses = getAllWarehouses(true);

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container">
    <div class="page-header" style="margin-bottom: 20px;">
        <h1>➕ إضافة مستخدم جديد</h1>
        <a href="index.php" style="color: #3b82f6; text-decoration: none;">← العودة لقائمة المستخدمين</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 10px; padding: 15px; margin-bottom: 20px; color: #fca5a5;">
            <strong>⚠️ يرجى تصحيح الأخطاء التالية:</strong>
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
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label class="form-label" style="font-weight: 600; margin-bottom: 5px; display: block;">الاسم الكامل *</label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required placeholder="مثال: محمد أحمد">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label class="form-label" style="font-weight: 600; margin-bottom: 5px; display: block;">اسم المستخدم * <small style="color: #6b7280;">(حروف إنجليزية وأرقام فقط)</small></label>
                        <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required placeholder="مثال: mohamed_ahmed" pattern="[a-zA-Z0-9_]+" dir="ltr" style="text-align: left;">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label class="form-label" style="font-weight: 600; margin-bottom: 5px; display: block;">كلمة المرور *</label>
                        <input type="password" name="password" class="form-control" required placeholder="6 أحرف على الأقل" minlength="6">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label class="form-label" style="font-weight: 600; margin-bottom: 5px; display: block;">رقم الهاتف</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" placeholder="اختياري">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label class="form-label" style="font-weight: 600; margin-bottom: 5px; display: block;">البريد الإلكتروني</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" placeholder="اختياري" dir="ltr" style="text-align: left;">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label class="form-label" style="font-weight: 600; margin-bottom: 5px; display: block;">صورة المستخدم</label>
                        <input type="file" name="avatar" class="form-control" accept="image/*">
                        <small style="color: #6b7280;">اختياري — سيتم استخدام صورة افتراضية إذا لم يتم رفع صورة</small>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label class="form-label" style="font-weight: 600; margin-bottom: 5px; display: block;">الدور *</label>
                        <select name="role" id="roleSelect" class="form-control" onchange="togglePermissions()">
                            <option value="employee" <?php echo ($_POST['role'] ?? '') === 'employee' ? 'selected' : ''; ?>>👤 موظف</option>
                            <option value="admin" <?php echo ($_POST['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>👑 مدير</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label class="form-label" style="font-weight: 600; margin-bottom: 5px; display: block;">🏢 المخزن الافتراضي للمستخدم *</label>
                        <select name="default_warehouse_id" class="form-control" required style="font-weight: 600;">
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="<?php echo $wh['id']; ?>" <?php echo ((int)($_POST['default_warehouse_id'] ?? 0) === (int)$wh['id'] || (!isset($_POST['default_warehouse_id']) && (int)$wh['is_default'] === 1)) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($wh['name']); ?> <?php echo ((int)$wh['is_default'] === 1) ? '(الافتراضي)' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: #6b7280;">المخزن الذي سيتم اختياره تلقائياً عند قيام هذا المستخدم بإنشاء الفواتير</small>
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
                            <?php if ($groupKey === 'users') continue; // Users management is admin-only ?>
                            <div class="perm-group" style="margin-bottom: 18px; background: rgba(30, 41, 59, 0.3); border-radius: 10px; padding: 12px;">
                                <div style="font-weight: 700; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; font-size: 0.95em;">
                                    <span><?php echo $group['icon']; ?></span>
                                    <span><?php echo $group['label']; ?></span>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px;">
                                    <?php foreach ($group['permissions'] as $permKey => $permLabel): ?>
                                        <label style="display: flex; align-items: center; gap: 6px; padding: 6px 8px; border-radius: 6px; cursor: pointer; transition: background 0.2s; font-size: 0.9em;" onmouseover="this.style.background='rgba(59,130,246,0.1)'" onmouseout="this.style.background='transparent'">
                                            <input type="checkbox" name="permissions[]" value="<?php echo $permKey; ?>" class="perm-checkbox" <?php echo in_array($permKey, $_POST['permissions'] ?? []) ? 'checked' : ''; ?>>
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
            <button type="submit" class="btn btn-primary" style="padding: 12px 40px; font-size: 1.05em; border-radius: 10px; background: linear-gradient(135deg, #10b981, #059669); border: none; color: white; cursor: pointer; font-family: 'Cairo', sans-serif; font-weight: 600;">
                ✅ إضافة المستخدم
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

function selectAll() {
    document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = true);
}

function deselectAll() {
    document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = false);
}

// Initialize on load
togglePermissions();
</script>

<?php include '../../includes/footer.php'; ?>
