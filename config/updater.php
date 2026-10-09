<?php
/**
 * Store System - Offline Update Engine
 * محرك تحديث البرنامج أوفلاين
 * 
 * يقوم بـ:
 * 1. فحص وفك ضغط حزمة التحديث (ZIP)
 * 2. التحقق من التوقيع الرقمي
 * 3. نسخ احتياطي تلقائي
 * 4. تنفيذ ترحيلات قاعدة البيانات
 * 5. استبدال ملفات البرنامج
 * 6. التراجع في حالة الفشل
 */

if (!defined('APP_VERSION')) {
    require_once __DIR__ . '/version.php';
}

// المسارات
define('APP_ROOT', realpath(__DIR__ . '/..'));
define('UPDATE_TEMP_DIR', APP_ROOT . '/data/updates/temp');
define('UPDATE_BACKUP_DIR', APP_ROOT . '/data/updates/backups');
define('UPDATE_HISTORY_FILE', APP_ROOT . '/data/updates/history.json');

// الملفات المحمية التي لا يتم استبدالها أبداً
define('PROTECTED_FILES', [
    'config/database.php',
    'config/license.dat',
    'config/.dev_license',
    'data/license.dat',
    'data/used_cids.dat',
    'data/updates/history.json',
    'config/ports.ini',
]);

/**
 * فحص حزمة التحديث والتحقق من صلاحيتها
 */
function validateUpdatePackage($zipPath) {
    $result = ['valid' => false, 'manifest' => null, 'error' => ''];

    if (!file_exists($zipPath)) {
        $result['error'] = 'ملف التحديث غير موجود.';
        return $result;
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        $result['error'] = 'ملف التحديث تالف أو ليس بصيغة ZIP صحيحة.';
        return $result;
    }

    // Check for manifest.json
    $manifestJson = $zip->getFromName('manifest.json');
    if (!$manifestJson) {
        $zip->close();
        $result['error'] = 'حزمة التحديث لا تحتوي على ملف البيانات (manifest.json). تأكد من أن الملف صحيح.';
        return $result;
    }

    $manifest = json_decode($manifestJson, true);
    if (!$manifest || empty($manifest['version'])) {
        $zip->close();
        $result['error'] = 'بيانات حزمة التحديث غير صالحة.';
        return $result;
    }

    // Verify signature
    if (!empty($manifest['signature'])) {
        $sigData = $manifest;
        unset($sigData['signature']);
        $expectedSig = hash_hmac('sha256', json_encode($sigData, JSON_UNESCAPED_UNICODE), UPDATE_SIGNATURE_KEY);
        if (!hash_equals($expectedSig, $manifest['signature'])) {
            $zip->close();
            $result['error'] = 'التوقيع الرقمي لحزمة التحديث غير صحيح. قد يكون الملف معدّل.';
            return $result;
        }
    }

    // Check version compatibility
    if (!empty($manifest['min_version']) && version_compare(APP_VERSION, $manifest['min_version'], '<')) {
        $zip->close();
        $result['error'] = "هذا التحديث يتطلب الإصدار {$manifest['min_version']} كحد أدنى. الإصدار الحالي هو " . APP_VERSION . ". يجب تثبيت التحديثات السابقة أولاً.";
        return $result;
    }

    if (version_compare(APP_VERSION, $manifest['version'], '>=')) {
        $zip->close();
        $result['error'] = "الإصدار الحالي (" . APP_VERSION . ") أحدث أو مساوٍ للتحديث ({$manifest['version']}). لا حاجة للتحديث.";
        return $result;
    }

    $zip->close();
    $result['valid'] = true;
    $result['manifest'] = $manifest;
    return $result;
}

/**
 * إنشاء نسخة احتياطية قبل التحديث
 */
function createPreUpdateBackup() {
    $backupDir = UPDATE_BACKUP_DIR . '/' . date('Y-m-d_H-i-s') . '_v' . APP_VERSION;
    @mkdir($backupDir, 0777, true);

    $result = ['success' => false, 'path' => $backupDir, 'error' => ''];

    // 1. Backup database
    try {
        if (!function_exists('getRow')) {
            require_once __DIR__ . '/database.php';
        }
        global $pdo;

        $dbBackupFile = $backupDir . '/database_backup.sql';
        
        // Get all tables
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $sql = "-- Store System Database Backup\n-- Version: " . APP_VERSION . "\n-- Date: " . date('Y-m-d H:i:s') . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            // Table structure
            $createTable = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
            $sql .= "DROP TABLE IF EXISTS `$table`;\n";
            $sql .= $createTable['Create Table'] . ";\n\n";

            // Table data
            $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $values = array_map(function($v) use ($pdo) {
                    return $v === null ? 'NULL' : $pdo->quote($v);
                }, $row);
                $sql .= "INSERT INTO `$table` VALUES (" . implode(',', $values) . ");\n";
            }
            $sql .= "\n";
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        file_put_contents($dbBackupFile, $sql);

    } catch (Exception $e) {
        $result['error'] = 'فشل في إنشاء نسخة احتياطية من قاعدة البيانات: ' . $e->getMessage();
        return $result;
    }

    // 2. Backup protected files
    $protectedBackupDir = $backupDir . '/protected';
    @mkdir($protectedBackupDir, 0777, true);

    foreach (PROTECTED_FILES as $file) {
        $srcFile = APP_ROOT . '/' . $file;
        if (file_exists($srcFile)) {
            $destDir = $protectedBackupDir . '/' . dirname($file);
            @mkdir($destDir, 0777, true);
            @copy($srcFile, $protectedBackupDir . '/' . $file);
        }
    }

    // 3. Backup version.php
    @copy(__DIR__ . '/version.php', $backupDir . '/version.php.bak');

    $result['success'] = true;
    return $result;
}

/**
 * تنفيذ ترحيلات قاعدة البيانات
 */
function runDatabaseMigrations($migrationsDir) {
    if (!is_dir($migrationsDir)) {
        return ['success' => true, 'applied' => 0, 'message' => 'لا توجد ترحيلات لتنفيذها.'];
    }

    if (!function_exists('getRow')) {
        require_once __DIR__ . '/database.php';
    }
    global $pdo;

    // Ensure schema_migrations table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS `schema_migrations` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `migration_number` INT NOT NULL,
        `migration_file` VARCHAR(255) NOT NULL,
        `description` VARCHAR(500) DEFAULT NULL,
        `applied_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_migration` (`migration_number`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Get already applied migrations
    $applied = $pdo->query("SELECT migration_number FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN);

    // Scan for migration files
    $files = glob($migrationsDir . '/*.sql');
    if (!$files) {
        return ['success' => true, 'applied' => 0, 'message' => 'لا توجد ملفات ترحيل.'];
    }

    sort($files);
    $appliedCount = 0;
    $errors = [];

    foreach ($files as $file) {
        $basename = basename($file);
        preg_match('/^(\d+)_/', $basename, $matches);
        if (!$matches) continue;

        $migrationNum = (int)$matches[1];

        // Skip already applied
        if (in_array($migrationNum, $applied)) continue;

        $sql = file_get_contents($file);
        if (empty(trim($sql))) continue;

        // Extract description from comment
        $desc = '';
        if (preg_match('/-- Description:\s*(.+)/i', $sql, $descMatch)) {
            $desc = trim($descMatch[1]);
        }

        try {
            // Clean SQL comments before executing
            $cleanSql = preg_replace('/--.*$/m', '', $sql);
            $cleanSql = preg_replace('/\/\*.*?\*\//s', '', $cleanSql);
            $statements = array_filter(array_map('trim', explode(';', $cleanSql)));
            foreach ($statements as $stmt) {
                if (empty($stmt)) continue;
                $pdo->exec($stmt);
            }

            // Record migration
            $ins = $pdo->prepare("INSERT IGNORE INTO schema_migrations (migration_number, migration_file, description) VALUES (?, ?, ?)");
            $ins->execute([$migrationNum, $basename, $desc]);

            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
            $appliedCount++;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = "فشل تنفيذ الترحيل {$basename}: " . $e->getMessage();
            break; // Stop on first error
        }
    }

    if (!empty($errors)) {
        return ['success' => false, 'applied' => $appliedCount, 'errors' => $errors, 'message' => implode("\n", $errors)];
    }

    return ['success' => true, 'applied' => $appliedCount, 'message' => "تم تنفيذ {$appliedCount} ترحيل بنجاح."];
}

/**
 * تطبيق حزمة التحديث
 */
function applyUpdate($zipPath) {
    $log = [];
    $log[] = ['time' => date('H:i:s'), 'step' => 'start', 'message' => 'بدء عملية التحديث...'];

    // Step 1: Validate
    $validation = validateUpdatePackage($zipPath);
    if (!$validation['valid']) {
        $log[] = ['time' => date('H:i:s'), 'step' => 'validate', 'message' => '❌ ' . $validation['error']];
        return ['success' => false, 'log' => $log, 'error' => $validation['error']];
    }
    $manifest = $validation['manifest'];
    $log[] = ['time' => date('H:i:s'), 'step' => 'validate', 'message' => "✅ حزمة التحديث صالحة. الإصدار الجديد: {$manifest['version']}"];

    // Step 2: Backup
    $log[] = ['time' => date('H:i:s'), 'step' => 'backup', 'message' => 'جاري إنشاء نسخة احتياطية...'];
    $backup = createPreUpdateBackup();
    if (!$backup['success']) {
        $log[] = ['time' => date('H:i:s'), 'step' => 'backup', 'message' => '❌ ' . $backup['error']];
        return ['success' => false, 'log' => $log, 'error' => $backup['error']];
    }
    $log[] = ['time' => date('H:i:s'), 'step' => 'backup', 'message' => '✅ تم إنشاء النسخة الاحتياطية بنجاح.'];

    // Step 3: Extract ZIP to temp
    $tempDir = UPDATE_TEMP_DIR . '/' . uniqid('update_');
    @mkdir($tempDir, 0777, true);

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        $log[] = ['time' => date('H:i:s'), 'step' => 'extract', 'message' => '❌ فشل فك ضغط ملف التحديث.'];
        return ['success' => false, 'log' => $log, 'error' => 'فشل فك ضغط ملف التحديث.'];
    }
    $zip->extractTo($tempDir);
    $zip->close();
    $log[] = ['time' => date('H:i:s'), 'step' => 'extract', 'message' => '✅ تم فك ضغط حزمة التحديث.'];

    // Step 4: Run database migrations
    $migrationsDir = $tempDir . '/migrations';
    if (is_dir($migrationsDir)) {
        $log[] = ['time' => date('H:i:s'), 'step' => 'migrations', 'message' => 'جاري تنفيذ ترحيلات قاعدة البيانات...'];
        $migResult = runDatabaseMigrations($migrationsDir);
        if (!$migResult['success']) {
            $log[] = ['time' => date('H:i:s'), 'step' => 'migrations', 'message' => '❌ ' . $migResult['message']];
            // Rollback database
            restoreBackup($backup['path']);
            cleanupTemp($tempDir);
            return ['success' => false, 'log' => $log, 'error' => 'فشل في ترحيل قاعدة البيانات. تم التراجع التلقائي.'];
        }

        // Copy migration files to app for future reference
        $appMigrationsDir = APP_ROOT . '/database/migrations';
        @mkdir($appMigrationsDir, 0777, true);
        foreach (glob($migrationsDir . '/*.sql') as $mFile) {
            @copy($mFile, $appMigrationsDir . '/' . basename($mFile));
        }

        $log[] = ['time' => date('H:i:s'), 'step' => 'migrations', 'message' => '✅ ' . $migResult['message']];
    } else {
        $log[] = ['time' => date('H:i:s'), 'step' => 'migrations', 'message' => 'ℹ️ لا توجد ترحيلات لقاعدة البيانات في هذا التحديث.'];
    }

    // Step 5: Update files
    $filesDir = $tempDir . '/files';
    if (is_dir($filesDir)) {
        $log[] = ['time' => date('H:i:s'), 'step' => 'files', 'message' => 'جاري تحديث ملفات البرنامج...'];

        $protectedPaths = PROTECTED_FILES;
        if (!empty($manifest['protected_paths'])) {
            $protectedPaths = array_merge($protectedPaths, $manifest['protected_paths']);
        }

        $updatedCount = 0;
        $skippedCount = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($filesDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relativePath = str_replace($filesDir . DIRECTORY_SEPARATOR, '', $item->getPathname());
            $relativePath = str_replace('\\', '/', $relativePath);
            $destPath = APP_ROOT . '/' . $relativePath;

            if ($item->isDir()) {
                @mkdir($destPath, 0777, true);
                continue;
            }

            // Skip protected files
            if (in_array($relativePath, $protectedPaths)) {
                $skippedCount++;
                continue;
            }

            // Create directory if needed
            $destDir = dirname($destPath);
            if (!is_dir($destDir)) {
                @mkdir($destDir, 0777, true);
            }

            // Copy file
            if (@copy($item->getPathname(), $destPath)) {
                $updatedCount++;
            }
        }

        $log[] = ['time' => date('H:i:s'), 'step' => 'files', 'message' => "✅ تم تحديث {$updatedCount} ملف. تم تخطي {$skippedCount} ملف محمي."];
    }

    // Step 6: Update version.php
    $newVersion = $manifest['version'];
    $newBuildDate = $manifest['build_date'] ?? date('Y-m-d');
    $newDbSchema = $manifest['db_schema_version'] ?? DB_SCHEMA_VERSION;
    $updateCheckUrl = defined('UPDATE_CHECK_URL') ? UPDATE_CHECK_URL : '';

    $versionContent = "<?php\ndefine(\"APP_VERSION\", \"{$newVersion}\");\ndefine(\"APP_BUILD_DATE\", \"{$newBuildDate}\");\ndefine(\"DB_SCHEMA_VERSION\", {$newDbSchema});\ndefine(\"UPDATE_CHECK_URL\", \"{$updateCheckUrl}\");\ndefine(\"UPDATE_SIGNATURE_KEY\", \"" . UPDATE_SIGNATURE_KEY . "\");\n";
    file_put_contents(APP_ROOT . '/config/version.php', $versionContent);
    $log[] = ['time' => date('H:i:s'), 'step' => 'version', 'message' => "✅ تم تحديث رقم الإصدار إلى {$newVersion}"];

    // Step 7: Save update history
    saveUpdateHistory($manifest);
    $log[] = ['time' => date('H:i:s'), 'step' => 'history', 'message' => '✅ تم تسجيل التحديث في السجل.'];

    // Step 8: Cleanup temp
    cleanupTemp($tempDir);
    $log[] = ['time' => date('H:i:s'), 'step' => 'cleanup', 'message' => '✅ تم تنظيف الملفات المؤقتة.'];
    $log[] = ['time' => date('H:i:s'), 'step' => 'done', 'message' => "🎉 تم التحديث بنجاح إلى الإصدار {$newVersion}!"];

    return ['success' => true, 'log' => $log, 'new_version' => $newVersion];
}

/**
 * استعادة النسخة الاحتياطية (Rollback)
 */
function restoreBackup($backupDir) {
    $dbFile = $backupDir . '/database_backup.sql';
    if (file_exists($dbFile)) {
        try {
            if (!function_exists('getRow')) {
                require_once __DIR__ . '/database.php';
            }
            global $pdo;
            $sql = file_get_contents($dbFile);
            $pdo->exec($sql);
        } catch (Exception $e) {
            // Log error but continue
        }
    }

    // Restore protected files
    $protectedDir = $backupDir . '/protected';
    if (is_dir($protectedDir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($protectedDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $rel = str_replace($protectedDir . DIRECTORY_SEPARATOR, '', $item->getPathname());
                $rel = str_replace('\\', '/', $rel);
                @copy($item->getPathname(), APP_ROOT . '/' . $rel);
            }
        }
    }

    // Restore version.php
    $versionBak = $backupDir . '/version.php.bak';
    if (file_exists($versionBak)) {
        @copy($versionBak, __DIR__ . '/version.php');
    }
}

/**
 * حفظ سجل التحديث
 */
function saveUpdateHistory($manifest) {
    $history = getUpdateHistory();
    $history[] = [
        'version' => $manifest['version'],
        'from_version' => APP_VERSION,
        'build_date' => $manifest['build_date'] ?? '',
        'applied_at' => date('Y-m-d H:i:s'),
        'changelog' => $manifest['changelog'] ?? [],
    ];
    @mkdir(dirname(UPDATE_HISTORY_FILE), 0777, true);
    file_put_contents(UPDATE_HISTORY_FILE, json_encode($history, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

/**
 * جلب سجل التحديثات
 */
function getUpdateHistory() {
    if (!file_exists(UPDATE_HISTORY_FILE)) return [];
    $data = @json_decode(file_get_contents(UPDATE_HISTORY_FILE), true);
    return is_array($data) ? $data : [];
}

/**
 * التحقق من التحديثات أونلاين
 */
/**
 * التحقق من وجود تحديثات أونلاين (يدعم مجلدات Google Drive والموقع الرسمي)
 */
function checkForUpdatesOnline() {
    if (!defined('UPDATE_CHECK_URL') || empty(UPDATE_CHECK_URL)) {
        return ['available' => false, 'error' => 'لم يتم تحديد رابط التحقق من التحديثات.'];
    }

    $checkUrl = UPDATE_CHECK_URL;

    // 1. فحص مجلد Google Drive إذا كان الرابط يشير إلى Drive
    if (strpos($checkUrl, 'drive.google.com') !== false) {
        $folderId = '';
        if (preg_match('/folders\/([a-zA-Z0-9_-]+)/', $checkUrl, $m)) {
            $folderId = $m[1];
        } elseif (preg_match('/id=([a-zA-Z0-9_-]+)/', $checkUrl, $m)) {
            $folderId = $m[1];
        }

        if (!$folderId) {
            return ['available' => false, 'error' => 'رابط مجلد Google Drive غير صالح.'];
        }

        $listUrl = "https://drive.google.com/embeddedfolderview?id={$folderId}#list";

        $ch = curl_init($listUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $html = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if (!$html) {
            return ['available' => false, 'error' => 'تعذر الاتصال بمجلد التحديثات على Google Drive (' . $err . '). تحقق من اتصال الإنترنت.'];
        }

        preg_match_all('/<div class="flip-entry"[^>]*id="entry-([^"]+)"[^>]*>.*?<div class="flip-entry-title">([^<]+)<\/div>/s', $html, $matches, PREG_SET_ORDER);

        if (empty($matches)) {
            return ['available' => false, 'message' => 'مجلد التحديثات على Google Drive لا يحتوي على ملفات حالياً.'];
        }

        $highestVersion = APP_VERSION;
        $bestCandidate = null;

        foreach ($matches as $entry) {
            $fId = $entry[1];
            $fName = trim($entry[2]);

            // التحقق من ملفات الـ ZIP التي تحتوي على رقم إصدار (مثال: StoreSystem_Update_v1.0.1.zip)
            if (preg_match('/v?(\d+\.\d+(\.\d+)?)/i', $fName, $vMatch) && preg_match('/\.zip$/i', $fName)) {
                $ver = $vMatch[1];
                if (version_compare($ver, $highestVersion, '>')) {
                    $highestVersion = $ver;
                    $bestCandidate = [
                        'id' => $fId,
                        'name' => $fName,
                        'version' => $ver,
                        'download_url' => "https://drive.usercontent.google.com/download?id={$fId}&export=download"
                    ];
                }
            }
        }

        if ($bestCandidate) {
            return [
                'available' => true,
                'version' => $bestCandidate['version'],
                'file_name' => $bestCandidate['name'],
                'file_id' => $bestCandidate['id'],
                'download_url' => $bestCandidate['download_url'],
                'changelog' => [
                    'تحديث متوفر على Google Drive: ' . $bestCandidate['name'],
                    'اضغط على زر التنزيل والتثبيت لبدء التحديث التلقائي.'
                ]
            ];
        }

        return ['available' => false, 'message' => 'البرنامج محدّث بالفعل لأحدث إصدار (' . APP_VERSION . ').'];
    }

    // 2. فحص موقع الشركة المستقبلي (JSON API)
    $ctx = stream_context_create([
        'http' => ['timeout' => 10, 'ignore_errors' => true],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
    ]);

    $response = @file_get_contents($checkUrl, false, $ctx);
    if ($response === false) {
        return ['available' => false, 'error' => 'لا يمكن الاتصال بخادم التحديثات. تحقق من اتصال الإنترنت.'];
    }

    $data = @json_decode($response, true);
    if (!$data || empty($data['version'])) {
        return ['available' => false, 'error' => 'بيانات التحديث غير صالحة.'];
    }

    if (version_compare(APP_VERSION, $data['version'], '<')) {
        return [
            'available' => true,
            'version' => $data['version'],
            'build_date' => $data['build_date'] ?? '',
            'changelog' => $data['changelog'] ?? [],
            'download_url' => $data['download_url'] ?? '',
            'size' => $data['size'] ?? '',
        ];
    }

    return ['available' => false, 'message' => 'البرنامج محدّث بالفعل لأحدث إصدار (' . APP_VERSION . ').'];
}

/**
 * تنزيل حزمة التحديث أونلاين
 */
function downloadUpdatePackage($downloadUrl, $savePath) {
    @mkdir(dirname($savePath), 0777, true);
    $fp = fopen($savePath, 'w+');
    if (!$fp) {
        return ['success' => false, 'error' => 'تعذر فتح ملف لحفظ التحديث.'];
    }

    $ch = curl_init($downloadUrl);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minutes max
    $success = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    fclose($fp);

    if (!$success || ($httpCode >= 400 && $httpCode < 600)) {
        @unlink($savePath);
        return ['success' => false, 'error' => 'فشل تنزيل ملف التحديث: ' . ($err ?: 'رمز الخطأ ' . $httpCode)];
    }

    return ['success' => true, 'path' => $savePath];
}

/**
 * تنظيف الملفات المؤقتة
 */
function cleanupTemp($dir) {
    if (!is_dir($dir)) return;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }
    @rmdir($dir);
}