<?php
/**
 * Backup & Restore
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';

// Auto backup doesn't require user session, but direct web requests do
if (!isset($_GET['auto'])) {
    requirePermission('settings.backup');
}

$pageTitle = 'النسخ الاحتياطي والاستعادة';

// Handle backup creation
if (isset($_POST['create_backup']) || isset($_GET['auto'])) {
    require_once __DIR__ . '/../../config/backup_lib.php';
    $prefix = isset($_GET['auto']) ? 'auto_backup_' : 'backup_';
    $res = performDbBackup($prefix);

    if ($res) {
        $locations = 'المجلد الأساسي';
        if (defined('BACKUP_DIR_SECONDARY') && file_exists(BACKUP_DIR_SECONDARY . $res['filename'])) {
            $locations .= ' + المجلد الاحتياطي (C:/StoreBackups/)';
        }
        if (!isset($_GET['auto'])) {
            setSuccess('تم إنشاء النسخة الاحتياطية بنجاح (' . number_format($res['size'] / 1024, 2) . ' KB) - ' . $locations);
        } else {
            pruneAutoBackups(getSetting('auto_backup_keep', '7'));
        }
    } else {
        if (!isset($_GET['auto'])) {
            setError('فشل إنشاء النسخة الاحتياطية');
        }
    }

    redirect('backup.php');
}

// Handle backup download
if (isset($_GET['download'])) {
    $id = $_GET['download'];
    $backup = getRow("SELECT * FROM backups WHERE id = ?", [$id]);

    $dlPath = null;
    if ($backup) {
        $candidate = BACKUP_DIR . basename($backup['filename']);
        if (file_exists($candidate)) {
            $dlPath = $candidate;
        } elseif (defined('BACKUP_DIR_SECONDARY') && file_exists(BACKUP_DIR_SECONDARY . basename($backup['filename']))) {
            $dlPath = BACKUP_DIR_SECONDARY . basename($backup['filename']);
        }
    }

    if ($backup && $dlPath) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $backup['filename'] . '"');
        header('Content-Length: ' . filesize($dlPath));
        readfile($dlPath);
        exit;
    } else {
        setError('الملف غير موجود');
        redirect('backup.php');
    }
}

// Handle backup deletion
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $backup = getRow("SELECT * FROM backups WHERE id = ?", [$id]);

    if ($backup) {
        $candidate = BACKUP_DIR . basename($backup['filename']);
        if (file_exists($candidate)) {
            unlink($candidate);
        }
        if (defined('BACKUP_DIR_SECONDARY') && file_exists(BACKUP_DIR_SECONDARY . basename($backup['filename']))) {
            @unlink(BACKUP_DIR_SECONDARY . basename($backup['filename']));
        }
        execute("DELETE FROM backups WHERE id = ?", [$id]);
        logActivity('حذف نسخة احتياطية', "تم حذف النسخة: {$backup['filename']}");
        setSuccess('تم حذف النسخة الاحتياطية');
    }

    redirect('backup.php');
}

// Get all backups
$backups = getRows("SELECT * FROM backups ORDER BY created_at DESC");

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container">
    <div class="card" style="border-radius: 12px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.04);">
        <div class="card-header" style="background: linear-gradient(135deg, #10b981, #059669); color: white; display: flex; justify-content: space-between; align-items: center; padding: 12px 18px;">
            <span>💾 النسخ الاحتياطي واستعادة البيانات</span>
            <div style="display: flex; gap: 8px;">
                <a href="../../index.php" style="background: rgba(255,255,255,0.2); color: white; text-decoration: none; padding: 4px 12px; border-radius: 6px; font-size: 12px; font-weight: 700;">
                    🏠 الرئيسية
                </a>
                <a href="index.php" style="background: rgba(255,255,255,0.2); color: white; text-decoration: none; padding: 4px 12px; border-radius: 6px; font-size: 12px; font-weight: 700;">
                    ⚙️ الإعدادات
                </a>
            </div>
        </div>
        <div class="card-body" style="padding: 20px;">
            <div class="alert alert-info">
                <strong>ℹ️ معلومات هامة:</strong>
                <ul style="margin: 10px 0 0 20px;">
                    <li>يُنصح بعمل نسخة احتياطية يومياً للحفاظ على بياناتك</li>
                    <li>يتم حفظ النسخ في مجلد backups داخل النظام</li>
                    <li>يمكنك تحميل النسخة على جهازك للحفظ الخارجي</li>
                    <li>لاستعادة نسخة احتياطية، استخدم phpMyAdmin أو سطر الأوامر</li>
                </ul>
            </div>
            
            <form method="POST" class="mb-3">
                <button type="submit" name="create_backup" class="btn btn-primary btn-lg">
                    ➕ إنشاء نسخة احتياطية جديدة
                </button>
            </form>
            
            <h3>النسخ الاحتياطية المتوفرة</h3>
            <hr>
            
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>اسم الملف</th>
                            <th>التاريخ</th>
                            <th>الحجم</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($backups)): ?>
                        <tr>
                            <td colspan="4" class="text-center">لا توجد نسخ احتياطية</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($backups as $backup): ?>
                        <tr>
                            <td><strong><?php echo $backup['filename']; ?></strong></td>
                            <td><?php echo date('Y/m/d H:i', strtotime($backup['created_at'])); ?></td>
                            <td><?php echo number_format(($backup['file_size'] ?? 0) / 1024, 2); ?> KB</td>
                            <td>
                                <a href="backup.php?download=<?php echo $backup['id']; ?>" class="btn btn-success">
                                    ⬇️ تحميل
                                </a>
                                <a href="backup.php?delete=<?php echo $backup['id']; ?>" 
                                   class="btn btn-danger"
                                   onclick="return confirmAction(this, 'هل أنت متأكد من حذف هذه النسخة؟')">
                                    🗑️ حذف
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="card mt-3" style="background: #fff3cd; border: 2px solid #ffc107;">
                <div class="card-body">
                    <h4>📝 كيفية استعادة نسخة احتياطية:</h4>
                    <ol style="margin: 10px 0 0 20px;">
                        <li>افتح phpMyAdmin من XAMPP Control Panel</li>
                        <li>اختر قاعدة البيانات <code>cafe_management</code></li>
                        <li>اضغط على تبويب "استيراد" (Import)</li>
                        <li>اختر ملف النسخة الاحتياطية (.sql)</li>
                        <li>اضغط "تنفيذ" (Go)</li>
                    </ol>
                    
                    <p class="mt-2"><strong>أو عبر سطر الأوامر:</strong></p>
                    <code style="background: #000; color: #0f0; padding: 10px; display: block; border-radius: 5px;">
                        mysql -u root -p cafe_management < backup_filename.sql
                    </code>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
