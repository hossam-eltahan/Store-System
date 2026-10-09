<?php
/**
 * Store System - 1-Click Auto Update Packager (CLI)
 * Clean English interface to avoid Windows Console Arabic BiDi reversing
 */

$appRoot = realpath(__DIR__ . '/..');
if (!$appRoot || !file_exists($appRoot . '/config/version.php')) {
    $appRoot = 'C:/xampp/htdocs/Store-System';
}

require_once $appRoot . '/config/database.php';
require_once $appRoot . '/config/version.php';

// Detect Desktop directory (supporting OneDrive redirection)
$userProfile = getenv('USERPROFILE') ?: 'C:/Users/Hossam';
$oneDriveDesktop = $userProfile . '/OneDrive - Faculty Of Engineering (Tanta University)/Desktop';
$standardDesktop = $userProfile . '/Desktop';

if (is_dir($oneDriveDesktop)) {
    $desktopDir = $oneDriveDesktop;
} elseif (is_dir($standardDesktop)) {
    $desktopDir = $standardDesktop;
} else {
    $desktopDir = $appRoot . '/data/updates';
}

echo "\n";
echo "========================================================\n";
echo "       STORE SYSTEM - 1-CLICK UPDATE BUILDER\n";
echo "========================================================\n\n";

$currentVersion = defined('APP_VERSION') ? APP_VERSION : '1.0.0';
$currentSchema = defined('DB_SCHEMA_VERSION') ? DB_SCHEMA_VERSION : 1;
$sigKey = defined('UPDATE_SIGNATURE_KEY') ? UPDATE_SIGNATURE_KEY : 'SS_UPDATE_SIG_V1_TAHAN_2026_xR8!kL3@';

// Suggest next version (e.g. 1.0.0 -> 1.0.1)
$verParts = explode('.', $currentVersion);
if (count($verParts) === 3) {
    $suggestedVersion = $verParts[0] . '.' . $verParts[1] . '.' . ((int)$verParts[2] + 1);
} else {
    $suggestedVersion = $currentVersion . '.1';
}

// 1. Version and changelog inputs
$newVersion = '';
$changelogInput = '';

if (isset($argv[1]) && !empty(trim($argv[1]))) {
    $newVersion = trim($argv[1]);
}
if (isset($argv[2]) && !empty(trim($argv[2]))) {
    $changelogInput = trim($argv[2]);
}

if (empty($newVersion)) {
    echo "Current Version   : v{$currentVersion}\n";
    echo "Suggested Version : v{$suggestedVersion}\n";
    echo "Enter New Version [Press Enter for {$suggestedVersion}]: ";
    $input = trim(fgets(STDIN));
    $newVersion = $input ?: $suggestedVersion;
}

if (empty($changelogInput)) {
    echo "Enter Release Notes / Changelog (optional, press Enter to skip): ";
    $changelogInput = trim(fgets(STDIN));
}

$changelog = [];
if (!empty($changelogInput)) {
    $changelog[] = $changelogInput;
} else {
    $changelog[] = "General performance improvements and feature updates.";
}

echo "\n[*] Checking database schema for changes (Auto-Diff)...\n";

// 2. Database Auto-Diff
function getBaselineSchema($sqlPath) {
    if (!file_exists($sqlPath)) return [];
    $sql = file_get_contents($sqlPath);
    $schema = [];
    if (preg_match_all('/CREATE TABLE `([^`]+)`\s*\((.*?)\)\s*ENGINE/s', $sql, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $tableName = $m[1];
            $body = $m[2];
            $columns = [];
            $lines = explode("\n", $body);
            foreach ($lines as $line) {
                $line = trim($line, " \t\r\n,");
                if (empty($line) || preg_match('/^(PRIMARY KEY|KEY|UNIQUE KEY|CONSTRAINT)/i', $line)) {
                    continue;
                }
                if (preg_match('/^`([^`]+)`\s+(.+)$/', $line, $colMatch)) {
                    $columns[$colMatch[1]] = $colMatch[2];
                }
            }
            $schema[$tableName] = $columns;
        }
    }
    return $schema;
}

function detectDatabaseDiff($pdo, $baselineSchema) {
    $diffSql = [];
    $currentTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($currentTables as $table) {
        if ($table === 'schema_migrations') continue;
        
        // New table
        if (!isset($baselineSchema[$table])) {
            $createRow = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
            $createSql = $createRow['Create Table'];
            $createSql = preg_replace('/CREATE TABLE/i', 'CREATE TABLE IF NOT EXISTS', $createSql, 1);
            $diffSql[] = "-- New Table: {$table}\n" . $createSql . ";\n";
            continue;
        }
        
        // Compare columns
        $baseColumns = $baselineSchema[$table];
        $currentCols = $pdo->query("SHOW FULL COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
        $prevCol = null;
        
        foreach ($currentCols as $col) {
            $colName = $col['Field'];
            if (!isset($baseColumns[$colName])) {
                $type = $col['Type'];
                $null = ($col['Null'] === 'NO') ? 'NOT NULL' : 'NULL';
                $default = '';
                if ($col['Default'] !== null) {
                    $default = "DEFAULT " . (is_numeric($col['Default']) ? $col['Default'] : "'" . addslashes($col['Default']) . "'");
                } elseif ($col['Null'] === 'YES') {
                    $default = "DEFAULT NULL";
                }
                $extra = $col['Extra'] ? ' ' . $col['Extra'] : '';
                $comment = $col['Comment'] ? " COMMENT '" . addslashes($col['Comment']) . "'" : '';
                $after = $prevCol ? " AFTER `{$prevCol}`" : " FIRST";
                
                $diffSql[] = "ALTER TABLE `{$table}` ADD COLUMN IF NOT EXISTS `{$colName}` {$type} {$null} {$default}{$extra}{$comment}{$after};";
            }
            $prevCol = $colName;
        }
    }
    return $diffSql;
}

$baselineSqlPath = 'C:/StoreSystem_Build/clean_store_system.sql';
if (!file_exists($baselineSqlPath)) {
    $baselineSqlPath = $appRoot . '/installer_build/store_system.sql';
}

$baselineSchema = getBaselineSchema($baselineSqlPath);
$dbDiff = detectDatabaseDiff($pdo, $baselineSchema);
$newSchemaVersion = $currentSchema;

if (!empty($dbDiff)) {
    $newSchemaVersion++;
    $migNumStr = sprintf('%03d', $newSchemaVersion);
    $migFileName = "{$migNumStr}_auto_update_v" . str_replace('.', '_', $newVersion) . ".sql";
    $migFilePath = $appRoot . "/database/migrations/{$migFileName}";
    
    $migHeader = "-- Migration: {$newSchemaVersion}\n";
    $migHeader .= "-- Description: Auto detected database schema updates for v{$newVersion}\n";
    $migHeader .= "-- Created: " . date('Y-m-d H:i:s') . "\n\n";
    
    @mkdir(dirname($migFilePath), 0777, true);
    file_put_contents($migFilePath, $migHeader . implode("\n", $dbDiff) . "\n");
    
    echo "    [+] Detected " . count($dbDiff) . " database change(s). Created migration: {$migFileName}\n";
    $changelog[] = "Database schema updates (" . count($dbDiff) . " change(s)).";
} else {
    echo "    [OK] Database schema is in sync (No table/column changes).\n";
}

echo "[*] Collecting application files...\n";

// 3. Collect Files
$excludePatterns = [
    '#^(\.git|installer_build|tools|data|backups|assets/uploads)#i',
    '#^(config/database\.php|config/license\.dat|config/\.dev_license)#i',
    '#\.(tmp|log|bak|dat)$#i'
];

$filesToZip = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($appRoot, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $item) {
    if ($item->isFile()) {
        $rel = str_replace('\\', '/', substr($item->getPathname(), strlen($appRoot) + 1));
        $skip = false;
        foreach ($excludePatterns as $pattern) {
            if (preg_match($pattern, $rel)) {
                $skip = true;
                break;
            }
        }
        if (!$skip) {
            $filesToZip[] = $rel;
        }
    }
}

echo "    [OK] Collected " . count($filesToZip) . " files.\n";

// 4. Build Manifest & Sign
$buildDate = date('Y-m-d');
$manifestData = [
    'version' => $newVersion,
    'min_version' => '1.0.0',
    'db_schema_version' => $newSchemaVersion,
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
    'files_count' => count($filesToZip)
];

$sigData = $manifestData;
$sig = hash_hmac('sha256', json_encode($sigData, JSON_UNESCAPED_UNICODE), $sigKey);
$manifestData['signature'] = $sig;

// 5. Create ZIP Archive
$zipName = "StoreSystem_Update_v{$newVersion}.zip";
$desktopZipPath = $desktopDir . '/' . $zipName;
$appUpdatesDir = $appRoot . '/data/updates';
@mkdir($appUpdatesDir, 0777, true);
$localZipPath = $appUpdatesDir . '/' . $zipName;

$zip = new ZipArchive();
if ($zip->open($localZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("\n[!] Error: Failed to create ZIP file.\n");
}

// Add manifest.json
$zip->addFromString('manifest.json', json_encode($manifestData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

// Add changelog.txt
$txtNotes = "=== Store System Update v{$newVersion} ===\n";
$txtNotes .= "Build Date: {$buildDate}\n\n";
$txtNotes .= "Changelog:\n";
foreach ($changelog as $idx => $c) {
    $txtNotes .= ($idx + 1) . ". {$c}\n";
}
$zip->addFromString('changelog.txt', $txtNotes);

// Add files
foreach ($filesToZip as $relPath) {
    $abs = $appRoot . '/' . $relPath;
    $zip->addFile($abs, 'files/' . $relPath);
}

// Add migrations
$migFiles = glob($appRoot . '/database/migrations/*.sql');
foreach ($migFiles as $mf) {
    $zip->addFile($mf, 'migrations/' . basename($mf));
}

$zip->close();

// Copy to Desktop
@copy($localZipPath, $desktopZipPath);
$fileSizeKb = round(filesize($desktopZipPath) / 1024, 1);

// 6. Update local version.php
$versionContent = "<?php\ndefine(\"APP_VERSION\", \"{$newVersion}\");\ndefine(\"APP_BUILD_DATE\", \"{$buildDate}\");\ndefine(\"DB_SCHEMA_VERSION\", {$newSchemaVersion});\ndefine(\"UPDATE_CHECK_URL\", \"https://drive.google.com/drive/folders/1OB88y045M4uHZxQj6XLZulIYlL5ubTLD\");\ndefine(\"UPDATE_SIGNATURE_KEY\", \"{$sigKey}\");\n";
file_put_contents($appRoot . '/config/version.php', $versionContent);

// Also sync to installer build
$installerVersionPath = 'C:/StoreSystem_Build/portable/htdocs/Store-System/config/version.php';
if (file_exists(dirname($installerVersionPath))) {
    @file_put_contents($installerVersionPath, $versionContent);
}

echo "\n========================================================\n";
echo "   SUCCESS! Update Package Built Successfully\n";
echo "========================================================\n";
echo "Version      : v{$newVersion}\n";
echo "Package Size : {$fileSizeKb} KB\n";
echo "File Location: {$desktopZipPath}\n\n";
echo "[*] Opening Google Drive folder in browser...\n";

// 7. Open Google Drive folder in browser
$gdriveUrl = "https://drive.google.com/drive/folders/1OB88y045M4uHZxQj6XLZulIYlL5ubTLD";
pclose(popen("start \"\" \"{$gdriveUrl}\"", "r"));

echo "\nNEXT STEP:\n";
echo "Just DRAG and DROP [ {$zipName} ] from your Desktop\n";
echo "into the Google Drive tab in your browser!\n";
echo "========================================================\n\n";
