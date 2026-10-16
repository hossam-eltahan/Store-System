<?php
/**
 * Factory Reset - إعادة ضبط المصنع
 * Wipes all data and restores the fresh-install state.
 * Requires: settings.reset permission + current password + a RECENT backup.
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
require_once __DIR__ . '/../../config/backup_lib.php';

requirePermission('settings.reset');

$pageTitle = 'إعادة ضبط المصنع';

// A backup counts as "taken first" only if it is fresh (default: last 120 minutes)
define('RESET_BACKUP_MAX_AGE_MIN', 120);

function getLastUsableBackup() {
    $b = getRow("SELECT * FROM backups ORDER BY created_at DESC, id DESC");
    if (!$b || empty($b['filename']) || !file_exists(backupFilePath($b['filename']))) {
        return null;
    }
    return $b;
}

function isBackupFreshEnough($backup) {
    if (!$backup) {
        return false;
    }
    return (time() - strtotime($backup['created_at'])) <= RESET_BACKUP_MAX_AGE_MIN * 60;
}

// Handle "backup now" button
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['backup_now'])) {
    $res = performDbBackup('backup_');
    if ($res) {
        setSuccess('تم إنشاء نسخة احتياطية حديثة — يمكنك الآن المتابعة لإعادة الضبط');
    } else {
        setError('فشل إنشاء النسخة الاحتياطية — لن تتم إعادة الضبط');
    }
    redirect('reset.php');
}

// Handle factory reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_reset'])) {
    $password = $_POST['password'] ?? '';
    $confirmed = isset($_POST['confirm_wipe']) && $_POST['confirm_wipe'] === '1';

    $me = getRow("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id'] ?? 0]);
    if (!$me || !password_verify($password, $me['password'])) {
        setError('كلمة المرور غير صحيحة — لم يتم تنفيذ إعادة الضبط');
        redirect('reset.php');
    }

    if (!$confirmed) {
        setError('يجب تأكيد فهمك بأن كل البيانات سيتم مسحها نهائياً');
        redirect('reset.php');
    }

    $lastBackup = getLastUsableBackup();
    if (!isBackupFreshEnough($lastBackup)) {
        setError('لا توجد نسخة احتياطية حديثة (خلال آخر ' . RESET_BACKUP_MAX_AGE_MIN . ' دقيقة) — خذ نسخة احتياطية أولاً ثم أعد المحاولة');
        redirect('reset.php');
    }

    $freshFile = __DIR__ . '/../../installer_build/fresh_install.sql';
    if (!file_exists($freshFile)) {
        setError('ملف التثبيت الأساسي غير موجود — تعذر إتمام إعادة الضبط');
        redirect('reset.php');
    }

    try {
        global $pdo;
        set_time_limit(0);

        // 1. Drop every table
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $t) {
            $pdo->exec("DROP TABLE IF EXISTS `$t`");
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

        // 2. Re-import the fresh-install bundle
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        $stmts = splitSqlStatements(file_get_contents($freshFile));
        foreach ($stmts as $stmt) {
            $pdo->exec($stmt);
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

        // 3. Clear uploaded files (fresh installs ship empty uploads)
        $uploadsDir = __DIR__ . '/../../assets/uploads';
        if (is_dir($uploadsDir)) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($uploadsDir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $file) {
                if ($file->isFile() && $file->getFilename() !== '.gitkeep') {
                    @unlink($file->getPathname());
                }
            }
        }

        // 4. Log out (all sessions/users were wiped) → fresh login
        performLogout();
        redirect('../../login.php?reset=done');
    } catch (Throwable $e) {
        error_log("Factory reset failed: " . $e->getMessage());
        setError('فشل إتمام إعادة الضبط — بياناتك محفوظة في النسخة الاحتياطية الأخيرة ويمكن استعادتها');
        redirect('reset.php');
    }
}

$lastBackup = getLastUsableBackup();
$backupFresh = isBackupFreshEnough($lastBackup);

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container">
    <div class="page-header" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <h1 style="margin: 0;">🗑️ إعادة ضبط المصنع</h1>
        <a href="index.php" style="color: #3b82f6; text-decoration: none;">← العودة للإعدادات</a>
    </div>

    <div class="card" style="border: 2px solid #ef4444; margin-bottom: 20px;">
        <div class="card-header" style="background: linear-gradient(135deg, #ef4444, #b91c1c); color: white; font-weight: 700;">
            ⚠️ ماذا سيحدث؟
        </div>
        <div class="card-body" style="font-size: 0.92em; line-height: 2;">
            سيتم <strong>مسح كل شيء نهائياً</strong>: المبيعات والطلبات، الجلسات، المستخدمون (عدا حساب المدير الافتراضي)،
            المخزون والحركات، العملاء والموردون، الفواتير، والإعدادات — ويعود النظام <strong>كأنه مثبت جديد تماماً</strong>
            (الدخول بعدها: <code dir="ltr">admin / admin123</code>).
            <br>ملف الترخيص الخاص بك لن يتأثر.
        </div>
    </div>

    <div class="card" style="margin-bottom: 20px;">
        <div class="card-header" style="background: linear-gradient(135deg, #10b981, #059669); color: white; font-weight: 700;">
            💾 شرط النسخة الاحتياطية
        </div>
        <div class="card-body" style="font-size: 0.92em;">
            <?php if ($lastBackup): ?>
                <div>آخر نسخة احتياطية: <strong><?php echo htmlspecialchars($lastBackup['filename']); ?></strong>
                (<?php echo date('Y/m/d H:i', strtotime($lastBackup['created_at'])); ?>)</div>
                <?php if ($backupFresh): ?>
                    <div style="color: #15803d; font-weight: 700; margin-top: 6px;">✅ توجد نسخة حديثة — شرط الأمان متحقق</div>
                <?php else: ?>
                    <div style="color: #b45309; font-weight: 700; margin-top: 6px;">⚠️ آخر نسخة قديمة (أقدم من <?php echo RESET_BACKUP_MAX_AGE_MIN; ?> دقيقة) — خذ نسخة جديدة أولاً</div>
                <?php endif; ?>
            <?php else: ?>
                <div style="color: #b91c1c; font-weight: 700;">⛔ لا توجد أي نسخة احتياطية — لن تتم إعادة الضبط قبل أخذ واحدة</div>
            <?php endif; ?>
            <form method="POST" style="margin-top: 12px;">
                <button type="submit" name="backup_now" class="btn btn-success" style="font-weight: 700; padding: 10px 22px; border-radius: 8px;">
                    💾 إنشاء نسخة احتياطية الآن
                </button>
            </form>
        </div>
    </div>

    <div class="card" style="border: 2px solid #ef4444;">
        <div class="card-header" style="background: #7f1d1d; color: white; font-weight: 700;">
            🗑️ تنفيذ إعادة الضبط
        </div>
        <div class="card-body">
            <form method="POST" onsubmit="return confirmSubmit(this, 'تأكيد أخير: سيتم مسح كل بيانات النظام نهائياً. متابعة؟');">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label class="form-label" style="font-weight: 700;">كلمة مرور المدير (للتأكيد) *</label>
                    <input type="password" name="password" class="form-control" required placeholder="أدخل كلمة مرور حسابك">
                </div>
                <div class="form-check" style="margin-bottom: 15px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.9em; color: #7f1d1d; font-weight: 700;">
                        <input type="checkbox" name="confirm_wipe" value="1" required>
                        <span>أفهم أن كل البيانات سيتم مسحها نهائياً ولا يمكن التراجع</span>
                    </label>
                </div>
                <button type="submit" name="do_reset" class="btn btn-danger" style="font-weight: 800; padding: 12px 30px; border-radius: 8px; font-size: 1em;"
                        <?php echo $backupFresh ? '' : 'disabled title="خذ نسخة احتياطية حديثة أولاً"'; ?>>
                    🗑️ مسح كل البيانات وإعادة الضبط
                </button>
                <?php if (!$backupFresh): ?>
                    <div style="color: #b91c1c; font-size: 0.85em; margin-top: 8px;">الزر معطل حتى توجد نسخة احتياطية حديثة (خلال آخر <?php echo RESET_BACKUP_MAX_AGE_MIN; ?> دقيقة).</div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
