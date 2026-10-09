<?php
/**
 * Store System - Developer Update Package Builder
 * أداة بناء حزم التحديثات الخاصة بالمطور
 */

define('DEVTOOL_DIR', __DIR__);
define('PACKAGES_DIR', DEVTOOL_DIR . '/packages');
define('APP_SOURCE_DIR', 'C:/xampp/htdocs/Store-System');

// Ensure packages directory exists
if (!is_dir(PACKAGES_DIR)) {
    @mkdir(PACKAGES_DIR, 0777, true);
}

// Load current app version constants if available
$currentVersion = '1.0.0';
$currentSchema = 1;
$currentBuildDate = date('Y-m-d');
$sigKey = 'SS_UPDATE_SIG_V1_TAHAN_2026_xR8!kL3@';

$versionFilePath = APP_SOURCE_DIR . '/config/version.php';
if (file_exists($versionFilePath)) {
    $vContent = file_get_contents($versionFilePath);
    if (preg_match("/define\(['\"]APP_VERSION['\"],\s*['\"]([^'\"]+)['\"]\)/", $vContent, $m)) {
        $currentVersion = $m[1];
    }
    if (preg_match("/define\(['\"]DB_SCHEMA_VERSION['\"],\s*(\d+)\)/", $vContent, $m)) {
        $currentSchema = (int)$m[1];
    }
    if (preg_match("/define\(['\"]UPDATE_SIGNATURE_KEY['\"],\s*['\"]([^'\"]+)['\"]\)/", $vContent, $m)) {
        $sigKey = $m[1];
    }
}

// Compute suggested next version (e.g., 1.0.0 -> 1.0.1)
$verParts = explode('.', $currentVersion);
if (count($verParts) === 3) {
    $suggestedVersion = $verParts[0] . '.' . $verParts[1] . '.' . ((int)$verParts[2] + 1);
} else {
    $suggestedVersion = $currentVersion . '.1';
}

$alert = null;
$buildResult = null;

// Handle New Migration Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_migration') {
    $migNumber = trim($_POST['migration_number'] ?? '');
    $migDesc = trim($_POST['migration_desc'] ?? '');
    $migSql = trim($_POST['migration_sql'] ?? '');
    
    if ($migNumber && $migSql) {
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $migDesc);
        $migFilename = sprintf('%03d', (int)$migNumber) . '_' . ($safeName ?: 'migration') . '.sql';
        $migPath = APP_SOURCE_DIR . '/database/migrations/' . $migFilename;
        
        $migHeader = "-- Migration: " . sprintf('%03d', (int)$migNumber) . "\n";
        $migHeader .= "-- Description: " . $migDesc . "\n";
        $migHeader .= "-- Created: " . date('Y-m-d H:i:s') . "\n\n";
        
        @mkdir(dirname($migPath), 0777, true);
        file_put_contents($migPath, $migHeader . $migSql . "\n");
        $alert = ['type' => 'success', 'msg' => "تم إنشاء ملف الترحيل بنجاح: {$migFilename}"];
    } else {
        $alert = ['type' => 'danger', 'msg' => "يرجى كتابة رقم الترحيل وكود الـ SQL."];
    }
}

// Handle Package Build
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'build_package') {
    $newVersion = trim($_POST['new_version'] ?? '');
    $minVersion = trim($_POST['min_version'] ?? '1.0.0');
    $dbSchemaVersion = (int)($_POST['db_schema_version'] ?? $currentSchema);
    $buildDate = trim($_POST['build_date'] ?? date('Y-m-d'));
    $changelogRaw = trim($_POST['changelog'] ?? '');
    $selectedFiles = $_POST['files'] ?? [];
    $selectedMigrations = $_POST['migrations'] ?? [];
    
    // Parse changelog lines
    $changelog = [];
    if ($changelogRaw) {
        $lines = preg_split("/\r\n|\n|\r/", $changelogRaw);
        foreach ($lines as $line) {
            $line = trim($line, " \t\n\r\0\x0B-•*");
            if ($line) $changelog[] = $line;
        }
    }
    
    if (empty($newVersion)) {
        $alert = ['type' => 'danger', 'msg' => 'يرجى إدخال رقم الإصدار الجديد.'];
    } elseif (empty($selectedFiles) && empty($selectedMigrations)) {
        $alert = ['type' => 'danger', 'msg' => 'يرجى اختيار ملف واحد على الأقل أو ترحيل قاعدة بيانات لتضمينه في التحديث.'];
    } else {
        // Build Manifest (without signature first)
        $manifestData = [
            'version' => $newVersion,
            'min_version' => $minVersion,
            'db_schema_version' => $dbSchemaVersion,
            'build_date' => $buildDate,
            'changelog' => $changelog,
            'protected_paths' => [
                'config/database.php',
                'config/license.dat',
                'config/.dev_license',
                'data/license.dat',
                'data/used_cids.dat',
                'data/updates/history.json',
                'config/ports.ini'
            ],
            'files_count' => count($selectedFiles),
            'migrations_count' => count($selectedMigrations)
        ];
        
        // Generate HMAC Signature
        $signature = hash_hmac('sha256', json_encode($manifestData, JSON_UNESCAPED_UNICODE), $sigKey);
        $manifestData['signature'] = $signature;
        
        // Create ZIP file
        $zipFileName = 'StoreSystem_Update_v' . $newVersion . '_' . date('Ymd_His') . '.zip';
        $zipPath = PACKAGES_DIR . '/' . $zipFileName;
        
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            // 1. Add manifest.json
            $zip->addFromString('manifest.json', json_encode($manifestData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            
            // 2. Add changelog.txt
            $txtNotes = "=== Store System Update v{$newVersion} ===\n";
            $txtNotes .= "Build Date: {$buildDate}\n";
            $txtNotes .= "Min Required Version: {$minVersion}\n\n";
            $txtNotes .= "Changelog:\n";
            foreach ($changelog as $idx => $note) {
                $txtNotes .= ($idx + 1) . ". {$note}\n";
            }
            $zip->addFromString('changelog.txt', $txtNotes);
            
            // 3. Add Selected Files into files/
            foreach ($selectedFiles as $relPath) {
                $absPath = APP_SOURCE_DIR . '/' . $relPath;
                if (file_exists($absPath) && is_file($absPath)) {
                    $zip->addFile($absPath, 'files/' . str_replace('\\', '/', $relPath));
                }
            }
            
            // 4. Add Selected Migrations into migrations/
            foreach ($selectedMigrations as $migFile) {
                $absMigPath = APP_SOURCE_DIR . '/database/migrations/' . $migFile;
                if (file_exists($absMigPath) && is_file($absMigPath)) {
                    $zip->addFile($absMigPath, 'migrations/' . basename($migFile));
                }
            }
            
            $zip->close();
            
            // Generate online check JSON (latest_version.json)
            $onlineJson = [
                'version' => $newVersion,
                'min_version' => $minVersion,
                'release_date' => $buildDate,
                'changelog' => $changelog,
                'download_url' => 'https://drive.google.com/YOUR_ZIP_SHARE_LINK_HERE',
                'file_size' => round(filesize($zipPath) / 1024, 1) . ' KB',
                'sha256' => hash_file('sha256', $zipPath)
            ];
            file_put_contents(DEVTOOL_DIR . '/latest_version.json', json_encode($onlineJson, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            
            $buildResult = [
                'filename' => $zipFileName,
                'path' => $zipPath,
                'size' => round(filesize($zipPath) / 1024, 1) . ' KB',
                'version' => $newVersion,
                'signature' => $signature,
                'sha256' => hash_file('sha256', $zipPath)
            ];
            
            $alert = ['type' => 'success', 'msg' => "تم بناء حزمة التحديث بنجاح: {$zipFileName}"];
        } else {
            $alert = ['type' => 'danger', 'msg' => 'فشل في إنشاء ملف ZIP.'];
        }
    }
}

// Handle Package Download
if (isset($_GET['download']) && !empty($_GET['download'])) {
    $dlFile = basename($_GET['download']);
    $dlPath = PACKAGES_DIR . '/' . $dlFile;
    if (file_exists($dlPath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $dlFile . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($dlPath));
        readfile($dlPath);
        exit;
    }
}

// Handle Package Delete
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $delFile = basename($_GET['delete']);
    $delPath = PACKAGES_DIR . '/' . $delFile;
    if (file_exists($delPath)) {
        @unlink($delPath);
        header('Location: index.php?msg=deleted');
        exit;
    }
}

// Scan files in Store-System for selection
function getAppFilesList($baseDir) {
    $excludePatterns = [
        '#^(\.git|installer_build|tools|data|backups|assets/uploads)#i',
        '#^(config/database\.php|config/license\.dat|config/\.dev_license)#i',
        '#\.(tmp|log|bak|dat)$#i'
    ];
    
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $item) {
        if ($item->isFile()) {
            $rel = str_replace('\\', '/', substr($item->getPathname(), strlen($baseDir) + 1));
            
            $skip = false;
            foreach ($excludePatterns as $pattern) {
                if (preg_match($pattern, $rel)) {
                    $skip = true;
                    break;
                }
            }
            if (!$skip) {
                $files[] = [
                    'path' => $rel,
                    'size' => $item->getSize(),
                    'mtime' => $item->getMTime()
                ];
            }
        }
    }
    
    // Sort recently modified first
    usort($files, function($a, $b) {
        return $b['mtime'] - $a['mtime'];
    });
    
    return $files;
}

$appFiles = is_dir(APP_SOURCE_DIR) ? getAppFilesList(APP_SOURCE_DIR) : [];

// Scan migrations
$availableMigrations = [];
$migDir = APP_SOURCE_DIR . '/database/migrations';
if (is_dir($migDir)) {
    $mFiles = scandir($migDir);
    foreach ($mFiles as $mf) {
        if (pathinfo($mf, PATHINFO_EXTENSION) === 'sql') {
            $availableMigrations[] = $mf;
        }
    }
    sort($availableMigrations);
}

// Get built packages
$builtPackages = [];
if (is_dir(PACKAGES_DIR)) {
    $pkgs = scandir(PACKAGES_DIR);
    foreach ($pkgs as $p) {
        if (pathinfo($p, PATHINFO_EXTENSION) === 'zip') {
            $builtPackages[] = [
                'name' => $p,
                'size' => round(filesize(PACKAGES_DIR . '/' . $p) / 1024, 1) . ' KB',
                'time' => date('Y-m-d H:i:s', filemtime(PACKAGES_DIR . '/' . $p))
            ];
        }
    }
    usort($builtPackages, function($a, $b) {
        return strcmp($b['name'], $a['name']);
    });
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>أداة بناء حزم التحديثات | Store System DevTools</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-main: #0f172a;
            --bg-card: #1e293b;
            --bg-card-hover: #24334a;
            --border: rgba(255, 255, 255, 0.08);
            --primary: #3b82f6;
            --primary-hover: #2563eb;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --accent: #8b5cf6;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Cairo', sans-serif; }
        body { background: var(--bg-main); color: var(--text-main); padding: 24px; min-height: 100vh; }
        .container { max-width: 1200px; margin: 0 auto; }
        
        /* Header */
        .dev-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #1e1b4b, #1e293b);
            border: 1px solid rgba(139, 92, 246, 0.3);
            border-radius: 16px;
            padding: 24px 30px;
            margin-bottom: 24px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.3);
        }
        .dev-title { display: flex; align-items: center; gap: 14px; }
        .dev-title i { font-size: 2.2rem; color: #a78bfa; }
        .dev-title h1 { font-size: 1.6rem; font-weight: 800; color: #fff; margin: 0; }
        .dev-title p { color: var(--text-muted); font-size: 0.88rem; margin-top: 4px; }
        
        .status-badges { display: flex; gap: 12px; }
        .badge {
            padding: 8px 14px;
            border-radius: 10px;
            font-size: 0.82rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .badge-ver { background: rgba(59, 130, 246, 0.15); border: 1px solid rgba(59, 130, 246, 0.4); color: #60a5fa; }
        .badge-schema { background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.4); color: #34d399; }
        
        /* Grid Layout */
        .layout-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
        
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 16px;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }
        .card-header h2 { font-size: 1.15rem; font-weight: 700; display: flex; align-items: center; gap: 8px; color: #f1f5f9; }
        
        /* Form Inputs */
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 0.88rem; font-weight: 700; margin-bottom: 6px; color: #cbd5e1; }
        .form-control {
            width: 100%;
            background: #0f172a;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 10px;
            color: #fff;
            padding: 10px 14px;
            font-size: 0.92rem;
            outline: none;
            transition: all 0.2s;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
        }
        textarea.form-control { min-height: 90px; resize: vertical; line-height: 1.6; }
        
        /* File selection table */
        .file-list-container {
            max-height: 280px;
            overflow-y: auto;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 10px;
            background: #0b1120;
            padding: 6px;
        }
        .file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 12px;
            border-radius: 6px;
            transition: background 0.15s;
            font-size: 0.85rem;
        }
        .file-item:hover { background: rgba(255,255,255,0.04); }
        .file-label { display: flex; align-items: center; gap: 10px; cursor: pointer; flex: 1; }
        .file-meta { font-size: 0.76rem; color: var(--text-muted); }
        
        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 22px;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 700;
            border: none;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-hover); transform: translateY(-1px); }
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { background: #059669; }
        .btn-sm { padding: 6px 12px; font-size: 0.8rem; border-radius: 6px; }
        .btn-danger { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
        .btn-danger:hover { background: var(--danger); color: white; }
        
        /* Alert */
        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.4); color: #34d399; }
        .alert-danger { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #f87171; }
        
        /* Built result box */
        .result-box {
            background: rgba(16, 185, 129, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 12px;
            padding: 18px;
            margin-top: 20px;
        }
        .result-box code {
            display: block;
            background: #0b1120;
            padding: 8px 12px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 0.82rem;
            color: #a78bfa;
            word-break: break-all;
            margin: 6px 0 12px 0;
        }
        
        /* Quick Filters */
        .filter-buttons { display: flex; gap: 8px; margin-bottom: 10px; flex-wrap: wrap; }
        .filter-btn {
            background: #1e293b;
            border: 1px solid rgba(255,255,255,0.1);
            color: var(--text-muted);
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.78rem;
            cursor: pointer;
        }
        .filter-btn:hover { background: #334155; color: #fff; }
    </style>
</head>
<body>

<div class="container">
    <!-- Header -->
    <div class="dev-header">
        <div class="dev-title">
            <i class="fa-solid fa-screwdriver-wrench"></i>
            <div>
                <h1>أداة بناء حزم التحديثات (Update Builder)</h1>
                <p>إنتاج حزم ZIP موقعة رقمياً ومتوافقة مع نظام التحديث الأوفلاين للعملاء</p>
            </div>
        </div>
        <div class="status-badges">
            <div class="badge badge-ver">
                <i class="fa-solid fa-code-branch"></i>
                الإصدار الحالي: v<?= htmlspecialchars($currentVersion) ?>
            </div>
            <div class="badge badge-schema">
                <i class="fa-solid fa-database"></i>
                الهيكل: #<?= htmlspecialchars($currentSchema) ?>
            </div>
        </div>
    </div>

    <?php if ($alert): ?>
    <div class="alert alert-<?= $alert['type'] ?>">
        <i class="fa-solid <?= $alert['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
        <?= htmlspecialchars($alert['msg']) ?>
    </div>
    <?php endif; ?>

    <div class="layout-grid">
        <!-- Main Column: Build Package Form -->
        <div>
            <div class="card">
                <div class="card-header">
                    <h2><i class="fa-solid fa-box-archive" style="color:#60a5fa;"></i> بناء حزمة تحديث جديدة</h2>
                    <span style="font-size:0.8rem; color:var(--text-muted);">ZIP + HMAC SHA256</span>
                </div>

                <form method="POST" id="buildForm">
                    <input type="hidden" name="action" value="build_package">

                    <!-- Version info row -->
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">رقم الإصدار الجديد (New Version) *</label>
                            <input type="text" name="new_version" class="form-control" value="<?= htmlspecialchars($suggestedVersion) ?>" required placeholder="مثال: 1.0.1">
                        </div>
                        <div class="form-group">
                            <label class="form-label">الحد الأدنى للإصدار المطلوب (Min Version) *</label>
                            <input type="text" name="min_version" class="form-control" value="<?= htmlspecialchars($currentVersion) ?>" required placeholder="مثال: 1.0.0">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">رقم إصدار قاعدة البيانات (DB Schema Version) *</label>
                            <input type="number" name="db_schema_version" class="form-control" value="<?= htmlspecialchars($currentSchema) ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">تاريخ البناء (Build Date) *</label>
                            <input type="date" name="build_date" class="form-control" value="<?= htmlspecialchars($currentBuildDate) ?>" required>
                        </div>
                    </div>

                    <!-- Changelog -->
                    <div class="form-group">
                        <label class="form-label">ملاحظات التحديث / الجديد في هذا الإصدار (سطر لكل ميزة)</label>
                        <textarea name="changelog" class="form-control" placeholder="- إضافة خاصية الضمانات الجديدة&#10;- إصلاح مشكلة طباعة الفواتير&#10;- تحسين سرعة البحث"></textarea>
                    </div>

                    <!-- Migrations selection -->
                    <div class="form-group">
                        <label class="form-label">ترحيلات قاعدة البيانات (Database Migrations)</label>
                        <div style="background:#0b1120; border:1px solid rgba(255,255,255,0.08); border-radius:10px; padding:12px;">
                            <?php if (empty($availableMigrations)): ?>
                                <div style="color:var(--text-muted); font-size:0.85rem;">لا توجد ملفات ترحيل حالياً. يمكنك إنشاء ملف جديد من القسم الجانبي.</div>
                            <?php else: ?>
                                <?php foreach ($availableMigrations as $mig): ?>
                                <label style="display:flex; align-items:center; gap:8px; margin-bottom:6px; cursor:pointer; font-size:0.88rem;">
                                    <input type="checkbox" name="migrations[]" value="<?= htmlspecialchars($mig) ?>" <?= strpos($mig, '001_') === 0 ? '' : 'checked' ?>>
                                    <span style="font-family:monospace; color:#34d399;"><?= htmlspecialchars($mig) ?></span>
                                </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Files selection -->
                    <div class="form-group">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                            <label class="form-label" style="margin:0;">الملفات المراد تضمينها في التحديث (Files to update)</label>
                            <span style="font-size:0.8rem; color:#60a5fa;" id="selectedCount">0 ملف محدد</span>
                        </div>

                        <!-- Quick Filters -->
                        <div class="filter-buttons">
                            <button type="button" class="filter-btn" onclick="selectAllFiles(true)">تحديد الكل</button>
                            <button type="button" class="filter-btn" onclick="selectAllFiles(false)">إلغاء التحديد</button>
                            <button type="button" class="filter-btn" onclick="filterByFolder('modules/')">الموديولات فقط</button>
                            <button type="button" class="filter-btn" onclick="filterByFolder('includes/')">includes فقط</button>
                            <button type="button" class="filter-btn" onclick="filterByFolder('config/')">config فقط</button>
                        </div>

                        <div class="file-list-container" id="fileListContainer">
                            <?php foreach ($appFiles as $file): ?>
                            <div class="file-item">
                                <label class="file-label">
                                    <input type="checkbox" name="files[]" value="<?= htmlspecialchars($file['path']) ?>" class="app-file-cb" onchange="updateSelectedCount()">
                                    <span style="font-family:monospace;"><?= htmlspecialchars($file['path']) ?></span>
                                </label>
                                <span class="file-meta">
                                    <?= round($file['size'] / 1024, 1) ?> KB &bull; <?= date('Y-m-d H:i', $file['mtime']) ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%; padding:14px; font-size:1.05rem; margin-top:10px;">
                        <i class="fa-solid fa-hammer"></i>
                        بناء وتوقيع حزمة التحديث الآن (Build & Sign Package)
                    </button>
                </form>

                <?php if ($buildResult): ?>
                <div class="result-box">
                    <h3 style="color:#34d399; font-size:1.1rem; margin-bottom:10px; display:flex; align-items:center; gap:8px;">
                        <i class="fa-solid fa-circle-check"></i>
                        تم إنشاء الحزمة بنجاح!
                    </h3>
                    <div style="font-size:0.88rem; color:#cbd5e1; line-height:1.8;">
                        <div>اسم الملف: <strong><?= htmlspecialchars($buildResult['filename']) ?></strong></div>
                        <div>الحجم: <strong><?= htmlspecialchars($buildResult['size']) ?></strong></div>
                        <div>توقيع HMAC: <code><?= htmlspecialchars($buildResult['signature']) ?></code></div>
                        <div>SHA256: <code><?= htmlspecialchars($buildResult['sha256']) ?></code></div>
                    </div>
                    <div style="display:flex; gap:10px; margin-top:12px;">
                        <a href="?download=<?= urlencode($buildResult['filename']) ?>" class="btn btn-success">
                            <i class="fa-solid fa-download"></i> تحميل ملف الـ ZIP
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar: Migration Creator & Built Packages -->
        <div>
            <!-- Create Migration Card -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fa-solid fa-plus" style="color:#34d399;"></i> إنشاء ملف ترحيل SQL</h2>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="create_migration">
                    
                    <div class="form-group">
                        <label class="form-label">رقم الترحيل (مثال: 2, 3...)</label>
                        <input type="number" name="migration_number" class="form-control" value="<?= $currentSchema + 1 ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">وصف التعديل (إنجليزي أو مختصر)</label>
                        <input type="text" name="migration_desc" class="form-control" placeholder="add_warranty_table" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">أوامر SQL (ALTER / CREATE / INSERT)</label>
                        <textarea name="migration_sql" class="form-control" style="font-family:monospace; min-height:110px;" placeholder="ALTER TABLE customers ADD COLUMN email VARCHAR(255) DEFAULT NULL;"></textarea>
                    </div>
                    <button type="submit" class="btn btn-success" style="width:100%;">
                        <i class="fa-solid fa-save"></i> حفظ ملف الترحيل
                    </button>
                </form>
            </div>

            <!-- Online Check JSON Preview -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fa-solid fa-cloud" style="color:#60a5fa;"></i> ملف النشر أونلاين</h2>
                </div>
                <p style="font-size:0.82rem; color:var(--text-muted); margin-bottom:10px; line-height:1.6;">
                    إذا أردت دعم التحقق التلقائي للعملاء أونلاين، ارفع ملف التحديث على Google Drive وضع الرابط هنا، ثم ارفع هذا الـ JSON إلى استضافتك أو Drive:
                </p>
                <div style="background:#0b1120; border:1px solid rgba(255,255,255,0.08); border-radius:8px; padding:10px; font-family:monospace; font-size:0.75rem; color:#a78bfa; overflow-x:auto; max-height:180px;">
                    <?php
                    $jsonSample = file_exists(DEVTOOL_DIR . '/latest_version.json') ? file_get_contents(DEVTOOL_DIR . '/latest_version.json') : "{\n  \"version\": \"{$suggestedVersion}\",\n  \"release_date\": \"{$currentBuildDate}\"\n}";
                    echo htmlspecialchars($jsonSample);
                    ?>
                </div>
            </div>

            <!-- Previously Built Packages -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fa-solid fa-history" style="color:#fbbf24;"></i> الحزم السابقة</h2>
                    <span style="font-size:0.8rem; color:var(--text-muted);"><?= count($builtPackages) ?></span>
                </div>
                <?php if (empty($builtPackages)): ?>
                    <p style="color:var(--text-muted); font-size:0.85rem;">لم يتم بناء أي حزم بعد.</p>
                <?php else: ?>
                    <div style="max-height:220px; overflow-y:auto;">
                        <?php foreach ($builtPackages as $pkg): ?>
                        <div style="display:flex; align-items:center; justify-content:space-between; padding:8px 0; border-bottom:1px solid rgba(255,255,255,0.06); font-size:0.82rem;">
                            <div>
                                <div style="font-family:monospace; font-weight:600; color:#cbd5e1;"><?= htmlspecialchars($pkg['name']) ?></div>
                                <div style="color:var(--text-muted); font-size:0.74rem;"><?= $pkg['size'] ?> &bull; <?= $pkg['time'] ?></div>
                            </div>
                            <div style="display:flex; gap:6px;">
                                <a href="?download=<?= urlencode($pkg['name']) ?>" class="btn btn-sm btn-primary" title="تحميل"><i class="fa-solid fa-download"></i></a>
                                <a href="?delete=<?= urlencode($pkg['name']) ?>" class="btn btn-sm btn-danger" title="حذف" onclick="return confirm('هل تريد حذف هذه الحزمة؟')"><i class="fa-solid fa-trash"></i></a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function updateSelectedCount() {
    const checked = document.querySelectorAll('.app-file-cb:checked').length;
    document.getElementById('selectedCount').textContent = checked + ' ملف محدد';
}

function selectAllFiles(status) {
    document.querySelectorAll('.app-file-cb').forEach(cb => cb.checked = status);
    updateSelectedCount();
}

function filterByFolder(prefix) {
    document.querySelectorAll('.app-file-cb').forEach(cb => {
        if (cb.value.startsWith(prefix)) {
            cb.checked = true;
        }
    });
    updateSelectedCount();
}
</script>

</body>
</html>
