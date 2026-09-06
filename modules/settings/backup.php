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
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "backup_$timestamp.sql";
    $filepath = BACKUP_DIR . $filename;
    $filepath2 = defined('BACKUP_DIR_SECONDARY') ? BACKUP_DIR_SECONDARY . $filename : null;
    
    // Create backup directories if not exists
    if (!is_dir(BACKUP_DIR)) {
        mkdir(BACKUP_DIR, 0777, true);
    }
    if ($filepath2 && !is_dir(BACKUP_DIR_SECONDARY)) {
        @mkdir(BACKUP_DIR_SECONDARY, 0777, true);
    }
    
    try {
        // Get PDO connection
        global $pdo;
        
        // Start SQL output
        $sql = "-- Backup created: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- Database: " . DB_NAME . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
        
        // Get all tables
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            // Get create table statement
            $createTable = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
            $sql .= "DROP TABLE IF EXISTS `$table`;\n";
            $sql .= $createTable['Create Table'] . ";\n\n";
            
            // Get table data
            $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($rows) > 0) {
                $columns = array_keys($rows[0]);
                $columnList = '`' . implode('`, `', $columns) . '`';
                
                foreach ($rows as $row) {
                    $values = array_map(function($v) use ($pdo) {
                        if ($v === null) return 'NULL';
                        return $pdo->quote($v);
                    }, $row);
                    $sql .= "INSERT INTO `$table` ($columnList) VALUES (" . implode(', ', $values) . ");\n";
                }
                $sql .= "\n";
            }
        }
        
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        
        // Write to primary location
        $saved1 = file_put_contents($filepath, $sql);
        
        // Write to secondary location
        $saved2 = false;
        if ($filepath2) {
            $saved2 = @file_put_contents($filepath2, $sql);
        }
        
        if ($saved1) {
            $filesize = filesize($filepath);
            
            // Save to database
            insert(
                "INSERT INTO backups (filename, file_path, size) VALUES (?, ?, ?)",
                [$filename, $filepath, $filesize]
            );
            
            // Update last backup date for auto-backup tracking
            updateSetting('last_backup_date', date('Y-m-d'));
            
            $locations = 'المجلد الأساسي';
            if ($saved2) {
                $locations .= ' + المجلد الاحتياطي (C:/StoreBackups/)';
            }
            
            logActivity('نسخ احتياطي', "تم إنشاء نسخة احتياطية: $filename");
            if (!isset($_GET['auto'])) {
                setSuccess('تم إنشاء النسخة الاحتياطية بنجاح (' . number_format($filesize / 1024, 2) . ' KB) - ' . $locations);
            }
        } else {
            if (!isset($_GET['auto'])) {
                setError('فشل في كتابة ملف النسخة الاحتياطية');
            }
        }
    } catch (Exception $e) {
        if (!isset($_GET['auto'])) {
            setError('فشل إنشاء النسخة الاحتياطية: ' . $e->getMessage());
        }
    }
    
    redirect('backup.php');
}

// Handle backup download
if (isset($_GET['download'])) {
    $id = $_GET['download'];
    $backup = getRow("SELECT * FROM backups WHERE id = ?", [$id]);
    
    if ($backup && file_exists($backup['file_path'])) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $backup['filename'] . '"');
        header('Content-Length: ' . $backup['size']);
        readfile($backup['file_path']);
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
        if (file_exists($backup['file_path'])) {
            unlink($backup['file_path']);
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
    <div class="card">
        <div class="card-header">💾 النسخ الاحتياطي والاستعادة</div>
        <div class="card-body">
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
                            <td><?php echo number_format($backup['size'] / 1024, 2); ?> KB</td>
                            <td>
                                <a href="backup.php?download=<?php echo $backup['id']; ?>" class="btn btn-success">
                                    ⬇️ تحميل
                                </a>
                                <a href="backup.php?delete=<?php echo $backup['id']; ?>" 
                                   class="btn btn-danger"
                                   onclick="return confirmDelete('هل أنت متأكد من حذف هذه النسخة؟')">
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
                        <li>اختر قاعدة البيانات <code>store_management</code></li>
                        <li>اضغط على تبويب "استيراد" (Import)</li>
                        <li>اختر ملف النسخة الاحتياطية (.sql)</li>
                        <li>اضغط "تنفيذ" (Go)</li>
                    </ol>
                    
                    <p class="mt-2"><strong>أو عبر سطر الأوامر:</strong></p>
                    <code style="background: #000; color: #0f0; padding: 10px; display: block; border-radius: 5px;">
                        mysql -u root -p store_management < backup_filename.sql
                    </code>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
