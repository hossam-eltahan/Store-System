<?php
/**
 * Offline License & Hardware-Bound Activation Engine
 * Store System Management
 */

// Core Configuration & Secret Salt
define('LICENSE_SECRET_KEY', 'STORE_SYS_SEC_V1_#@99_TAHAN_2026_xK7$mQ!');
define('LICENSE_IV_SALT', 'STORE_SYS_IV_SALT_2026_xP9#');
define('LICENSE_FILE_PATH', __DIR__ . '/license.dat');
define('LICENSE_BACKUP_PATH', __DIR__ . '/../data/license.dat');
define('DEV_LICENSE_FILE', __DIR__ . '/.dev_license');
define('USED_LICENSES_BACKUP_PATH', __DIR__ . '/../data/used_cids.dat');

/**
 * Get Unique Machine Fingerprint (Hardware ID)
 * Uses Windows MachineGuid + Processor/Board identifiers
 */
function getMachineFingerprint() {
    static $cachedHwid = null;
    if ($cachedHwid !== null) {
        return $cachedHwid;
    }

    $rawIds = [];

    // 1. Windows MachineGuid from Registry (Most stable across reboots)
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $regOutput = @shell_exec('REG QUERY "HKLM\SOFTWARE\Microsoft\Cryptography" /v MachineGuid 2>nul');
        if ($regOutput && preg_match('/MachineGuid\s+REG_SZ\s+([a-f0-9\-]+)/i', $regOutput, $matches)) {
            $rawIds[] = trim($matches[1]);
        }
    }

    // 2. Computer Name / Hostname
    $rawIds[] = php_uname('n') ?: gethostname() ?: 'UNKNOWN-HOST';

    // 3. User & System Drive Serial
    if (isset($_SERVER['SystemDrive'])) {
        $rawIds[] = $_SERVER['SystemDrive'];
    }

    // 4. Fallback if registry query failed
    if (count($rawIds) < 2) {
        $rawIds[] = php_uname('m') . '_' . php_uname('s') . '_' . php_uname('r');
    }

    $combined = implode('|', $rawIds);
    $hash = hash('sha256', $combined . LICENSE_SECRET_KEY);
    
    // Format into a readable 16-character Hardware ID: HWID-XXXX-XXXX-XXXX
    $sub = strtoupper(substr($hash, 0, 12));
    $cachedHwid = 'HWID-' . substr($sub, 0, 4) . '-' . substr($sub, 4, 4) . '-' . substr($sub, 8, 4);
    return $cachedHwid;
}

/**
 * Check if the current environment is a Developer Environment
 */
function isDevEnvironment() {
    if (!file_exists(DEV_LICENSE_FILE)) {
        return false;
    }

    $content = @trim(file_get_contents(DEV_LICENSE_FILE));
    if (empty($content)) {
        return false;
    }

    $expectedToken = hash_hmac('sha256', 'DEV_ENVIRONMENT_BYPASS_HOSSAM_2026', LICENSE_SECRET_KEY);
    return hash_equals($expectedToken, $content);
}

/**
 * Generate Activation Code (Used by Developer Tools & CLI)
 */
function generateActivationCode($months, $targetHwid = '', $cid = null) {
    if (!$cid) {
        $cid = substr(bin2hex(random_bytes(3)), 0, 6);
    }
    $ts = time();

    $hwidClean = strtoupper(str_replace(['HWID-', '-', ' '], '', trim($targetHwid)));
    if (empty($hwidClean) || $hwidClean === 'ANY') {
        return false;
    }

    $raw = sprintf("%s:%d:%d:%s", $cid, (int)$months, $ts, $hwidClean);

    $key = hash('sha256', LICENSE_SECRET_KEY, true);
    $iv = substr(hash('sha256', LICENSE_IV_SALT, true), 0, 16);

    $cipher = openssl_encrypt($raw, 'AES-128-CBC', substr($key, 0, 16), OPENSSL_RAW_DATA, $iv);
    if (!$cipher) {
        return false;
    }

    $hex = strtoupper(bin2hex($cipher));
    return 'SS-' . implode('-', str_split($hex, 4));
}

/**
 * Validate and Decrypt an Activation Code
 */
function verifyActivationCode($code) {
    $clean = str_replace(['SS-', '-', ' '], '', strtoupper(trim($code)));
    if (empty($clean) || strlen($clean) % 2 !== 0) {
        return false;
    }

    $cipher = @hex2bin($clean);
    if (!$cipher) {
        return false;
    }

    $key = hash('sha256', LICENSE_SECRET_KEY, true);
    $iv = substr(hash('sha256', LICENSE_IV_SALT, true), 0, 16);

    $plain = openssl_decrypt($cipher, 'AES-128-CBC', substr($key, 0, 16), OPENSSL_RAW_DATA, $iv);
    if (!$plain) {
        return false;
    }

    $parts = explode(':', $plain);
    if (count($parts) < 4) {
        // Any legacy or invalid code missing strict HWID is strictly rejected
        return false;
    }

    $cid = $parts[0];
    $months = (int)$parts[1];
    $createdAt = (int)$parts[2];
    $targetHwid = strtoupper(trim($parts[3]));

    if (empty($targetHwid) || $targetHwid === 'ANY') {
        return false;
    }

    return [
        'cid' => $cid,
        'months' => $months,
        'created_at' => $createdAt,
        'target_hwid' => $targetHwid
    ];
}

/**
 * Helper to get all used CIDs history
 */
function getUsedCidsHistory() {
    $cids = [];

    // 1. Primary license.dat
    $record = readLicenseRecord();
    if (!empty($record['used_cids']) && is_array($record['used_cids'])) {
        $cids = array_merge($cids, $record['used_cids']);
    }
    if (!empty($record['code_id'])) {
        $cids[] = $record['code_id'];
    }

    // 2. Backup encrypted used_cids.dat in data/
    if (defined('USED_LICENSES_BACKUP_PATH') && file_exists(USED_LICENSES_BACKUP_PATH)) {
        $hwid = getMachineFingerprint();
        $enc = @file_get_contents(USED_LICENSES_BACKUP_PATH);
        if ($enc) {
            $key = hash('sha256', LICENSE_SECRET_KEY . $hwid, true);
            $iv = substr(hash('sha256', LICENSE_IV_SALT . $hwid, true), 0, 16);
            $dec = openssl_decrypt($enc, 'AES-256-CBC', $key, 0, $iv);
            if ($dec) {
                $saved = @json_decode($dec, true);
                if (is_array($saved)) {
                    $cids = array_merge($cids, $saved);
                }
            }
        }
    }

    return array_values(array_unique(array_filter($cids)));
}

/**
 * Record a used CID in persistent backup storage
 */
function recordUsedCidHistory($cid) {
    if (!defined('USED_LICENSES_BACKUP_PATH')) return;
    $hwid = getMachineFingerprint();
    $cids = getUsedCidsHistory();
    $cids[] = $cid;
    $cids = array_values(array_unique($cids));

    $dataDir = dirname(USED_LICENSES_BACKUP_PATH);
    if (!is_dir($dataDir)) {
        @mkdir($dataDir, 0777, true);
    }

    $key = hash('sha256', LICENSE_SECRET_KEY . $hwid, true);
    $iv = substr(hash('sha256', LICENSE_IV_SALT . $hwid, true), 0, 16);
    $encrypted = openssl_encrypt(json_encode($cids), 'AES-256-CBC', $key, 0, $iv);
    if ($encrypted) {
        @file_put_contents(USED_LICENSES_BACKUP_PATH, $encrypted);
    }
}

function activateLicense($code) {
    $parsed = verifyActivationCode($code);
    if (!$parsed) {
        return [
            'success' => false,
            'message' => 'كود التفعيل غير صالح أو به خطأ في الكتابة. يرجى التأكد من الكود والمحاولة مجدداً.'
        ];
    }

    $currentHwid = getMachineFingerprint();
    $currentHwidClean = strtoupper(str_replace(['HWID-', '-', ' '], '', $currentHwid));

    // 1. HARDWARE BINDING CHECK
    $targetHwid = $parsed['target_hwid'];
    if ($targetHwid !== $currentHwidClean) {
        return [
            'success' => false,
            'message' => 'عفواً! كود التفعيل هذا مخصص لجهاز كمبيوتر آخر ولا يمكن استخدامه على هذا الجهاز.'
        ];
    }

    // 2. ANTI-REPLAY / SINGLE-USE CHECK
    $usedCids = getUsedCidsHistory();
    $cid = $parsed['cid'];
    if (in_array($cid, $usedCids)) {
        return [
            'success' => false,
            'message' => 'عفواً! كود التفعيل هذا تم استخدامه مسبقاً على هذا الجهاز ولا يمكن إعادة استخدامه مرة أخرى. يرجى إدخال كود تجديد جديد.'
        ];
    }

    $now = time();
    $months = (int)$parsed['months'];
    $isLifetime = ($months === 0);

    // If existing license is still active and valid, extend from current expiry date
    $currentInfo = getLicenseDetails();
    $baseTime = $now;
    if ($currentInfo && $currentInfo['status'] === 'valid' && !$currentInfo['is_lifetime'] && $currentInfo['expires_timestamp'] > $now) {
        $baseTime = $currentInfo['expires_timestamp'];
    }

    if ($isLifetime) {
        $expiresAt = 0;
        $label = 'مدى الحياة (دائم)';
    } elseif ($months <= -1000) {
        $mins = abs($months) - 1000;
        $expiresAt = $now + ($mins * 60);
        $label = "فترة اختبار ({$mins} دقيقة)";
    } elseif ($months < 0) {
        $days = abs($months);
        $expiresAt = $now + ($days * 86400);
        $label = "نسخة تجريبية ({$days} أيام)";
    } else {
        $expiresAt = $baseTime + ($months * 30 * 86400);
        $label = ($months == 1 ? 'شهر واحد' : ($months == 2 ? 'شهرين' : "{$months} شهور"));
    }

    // Record this CID as permanently used
    recordUsedCidHistory($cid);
    $usedCids[] = $cid;

    $licenseData = [
        'code_id' => $cid,
        'code' => trim($code),
        'hwid' => $currentHwid,
        'months' => $months,
        'is_lifetime' => $isLifetime,
        'duration_label' => $label,
        'activated_at' => $now,
        'expires_at' => $expiresAt,
        'last_run' => $now,
        'used_cids' => array_values(array_unique($usedCids)),
        'clock_drift_warning' => false
    ];

    $payloadJson = json_encode($licenseData, JSON_UNESCAPED_UNICODE);
    $signature = hash_hmac('sha256', $payloadJson, LICENSE_SECRET_KEY . $currentHwid);

    $record = [
        'data' => $payloadJson,
        'sig' => $signature
    ];

    // Encrypt the license record with hardware-bound key
    $encKey = hash('sha256', LICENSE_SECRET_KEY . $currentHwid, true);
    $iv = substr(hash('sha256', LICENSE_IV_SALT . $currentHwid, true), 0, 16);
    $encryptedFileContent = openssl_encrypt(json_encode($record), 'AES-256-CBC', $encKey, 0, $iv);

    if (!$encryptedFileContent) {
        return [
            'success' => false,
            'message' => 'تعذر تشفير بيانات الترخيص. يرجى المحاولة مرة أخرى.'
        ];
    }

    // Save to primary and backup locations
    @file_put_contents(LICENSE_FILE_PATH, $encryptedFileContent);
    if (is_dir(dirname(LICENSE_BACKUP_PATH))) {
        @file_put_contents(LICENSE_BACKUP_PATH, $encryptedFileContent);
    }

    return [
        'success' => true,
        'is_lifetime' => $isLifetime,
        'months' => $months,
        'expires_at' => $expiresAt ? date('Y-m-d H:i:s', $expiresAt) : 'مدى الحياة',
        'message' => "تم تفعيل النظام بنجاح! (" . $label . ")"
    ];
}

/**
 * Read and Decrypt Local License Record
 */
function readLicenseRecord() {
    $content = null;
    if (file_exists(LICENSE_FILE_PATH)) {
        $content = @file_get_contents(LICENSE_FILE_PATH);
    } elseif (file_exists(LICENSE_BACKUP_PATH)) {
        $content = @file_get_contents(LICENSE_BACKUP_PATH);
        @file_put_contents(LICENSE_FILE_PATH, $content);
    }

    if (empty($content)) {
        return null;
    }

    $hwid = getMachineFingerprint();
    $encKey = hash('sha256', LICENSE_SECRET_KEY . $hwid, true);
    $iv = substr(hash('sha256', LICENSE_IV_SALT . $hwid, true), 0, 16);

    $decryptedJson = openssl_decrypt($content, 'AES-256-CBC', $encKey, 0, $iv);
    if (!$decryptedJson) {
        return null;
    }

    $record = json_decode($decryptedJson, true);
    if (!$record || empty($record['data']) || empty($record['sig'])) {
        return null;
    }

    $expectedSig = hash_hmac('sha256', $record['data'], LICENSE_SECRET_KEY . $hwid);
    if (!hash_equals($expectedSig, $record['sig'])) {
        return null;
    }

    $licenseData = json_decode($record['data'], true);
    if (!$licenseData || empty($licenseData['hwid'])) {
        return null;
    }

    if ($licenseData['hwid'] !== $hwid) {
        return null;
    }

    return $licenseData;
}

/**
 * Comprehensive License Verification
 */
function verifyLicense() {
    if (isDevEnvironment()) {
        return [
            'status' => 'valid',
            'is_dev' => true,
            'is_lifetime' => true,
            'days_left' => 9999,
            'expires_at' => 'بيئة تطوير للمبرمج (غير مقيدة)',
            'message' => 'بيئة تطوير مفعلة'
        ];
    }

    $license = readLicenseRecord();
    if (!$license) {
        return [
            'status' => 'unlicensed',
            'message' => 'البرنامج غير مفعل. يرجى إدخال كود التفعيل لبدء العمل.'
        ];
    }

    $now = time();
    $lastRun = (int)($license['last_run'] ?? 0);

    $hasClockWarning = false;
    if ($now < $lastRun) {
        $driftSeconds = $lastRun - $now;
        $maxTolerance = 48 * 3600;

        if ($driftSeconds <= $maxTolerance) {
            $hasClockWarning = true;
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            $_SESSION['license_clock_warning'] = 'تنبيه: يوجد تراجع طفيف في ساعة وتاريخ الجهاز. يرجى ضبط التاريخ والوقت الصحيح لتفادي توقف النظام.';
        } else {
            return [
                'status' => 'clock_error',
                'drift_hours' => round($driftSeconds / 3600),
                'message' => 'تم إيقاف النظام مؤقتاً لوجود تراجع في تاريخ وساعة الجهاز لأكثر من 48 ساعة. يرجى إعادة ضبط تاريخ الجهاز بالوقت الصحيح.'
            ];
        }
    } else {
        if (isset($_SESSION['license_clock_warning'])) {
            unset($_SESSION['license_clock_warning']);
        }

        if ($now - $lastRun > 300) {
            $license['last_run'] = $now;
            updateLicenseDataQuietly($license);
        }
    }

    if (!empty($license['is_lifetime'])) {
        return [
            'status' => 'valid',
            'is_lifetime' => true,
            'days_left' => 9999,
            'expires_at' => 'مدى الحياة',
            'clock_warning' => $hasClockWarning,
            'message' => 'الاشتراك مفعل مدى الحياة'
        ];
    }

    $expiresAt = (int)($license['expires_at'] ?? 0);
    if ($now > $expiresAt) {
        return [
            'status' => 'expired',
            'expired_timestamp' => $expiresAt,
            'expired_at' => date('Y-m-d H:i:s', $expiresAt),
            'message' => 'انتهت فترة الاشتراك في النظام. يرجى التجديد للاستمرار.'
        ];
    }

    $diff = $expiresAt - $now;
    $daysLeft = (int)ceil($diff / 86400);
    $minsLeft = max(1, (int)ceil($diff / 60));

    $isTrial = (!empty($license['months']) && $license['months'] < 0 && $license['months'] > -1000);
    $isTest = (!empty($license['months']) && $license['months'] <= -1000);

    if ($isTest) {
        $displayMsg = "فترة تجربة واختبار (متبقي {$minsLeft} دقيقة)";
    } elseif ($isTrial) {
        $displayMsg = "نسخة تجريبية مجانية (متبقي {$daysLeft} أيام)";
    } else {
        $displayMsg = "الاشتراك نشط (متبقي {$daysLeft} يوم)";
    }

    return [
        'status' => 'valid',
        'is_lifetime' => false,
        'days_left' => $daysLeft,
        'mins_left' => $minsLeft,
        'is_trial' => $isTrial,
        'is_test' => $isTest,
        'expires_timestamp' => $expiresAt,
        'expires_at' => date('Y-m-d H:i:s', $expiresAt),
        'clock_warning' => $hasClockWarning,
        'message' => $displayMsg
    ];
}

function updateLicenseDataQuietly($licenseData) {
    $hwid = getMachineFingerprint();
    $payloadJson = json_encode($licenseData, JSON_UNESCAPED_UNICODE);
    $signature = hash_hmac('sha256', $payloadJson, LICENSE_SECRET_KEY . $hwid);
    $record = ['data' => $payloadJson, 'sig' => $signature];

    $encKey = hash('sha256', LICENSE_SECRET_KEY . $hwid, true);
    $iv = substr(hash('sha256', LICENSE_IV_SALT . $hwid, true), 0, 16);
    $encrypted = openssl_encrypt(json_encode($record), 'AES-256-CBC', $encKey, 0, $iv);

    if ($encrypted) {
        @file_put_contents(LICENSE_FILE_PATH, $encrypted);
    }
}


function getFullLicenseInfo() {
    $record = readLicenseRecord();
    $details = verifyLicense();
    $hwid = getMachineFingerprint();
    $combined = array_merge($record ?: [], $details ?: []);
    $combined['hwid'] = $hwid;
    return $combined;
}

function getLicenseDetails() {
    return verifyLicense();
}

function enforceLicense() {
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($script === 'activate.php') {
        return;
    }

    $check = verifyLicense();
    if ($check['status'] !== 'valid') {
        $reason = $check['status'];
        
        $activateUrl = 'activate.php';
        if (defined('BASE_URL')) {
            $activateUrl = BASE_URL . 'activate.php';
        } else {
            $pos = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/Store-System/');
            if ($pos !== false) {
                $activateUrl = '/Store-System/activate.php';
            }
        }
        
        $target = $activateUrl . '?reason=' . urlencode($reason);
        if (!headers_sent()) {
            header('Location: ' . $target);
        } else {
            echo "<script>window.location.href = " . json_encode($target) . ";</script>";
        }
        exit;
    }
}