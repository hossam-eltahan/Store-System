<?php
/**
 * Software Updates Page
 * صفحة تحديثات البرنامج
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
require_once '../../config/version.php';
require_once '../../config/updater.php';
requirePermission('settings.manage');

$pageTitle = 'تحديثات البرنامج';
$message = '';
$messageType = '';
$updateLog = [];

// Handle update upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['update_file'])) {
    if ($_FILES['update_file']['error'] === UPLOAD_ERR_OK) {
        $tmpFile = $_FILES['update_file']['tmp_name'];
        $originalName = $_FILES['update_file']['name'];
        
        // Move to temp location
        $uploadDir = APP_ROOT . '/data/updates/temp';
        @mkdir($uploadDir, 0777, true);
        $uploadPath = $uploadDir . '/' . $originalName;
        move_uploaded_file($tmpFile, $uploadPath);
        
        // Apply update
        $result = applyUpdate($uploadPath);
        $updateLog = $result['log'] ?? [];
        
        if ($result['success']) {
            $messageType = 'success';
            $message = '🎉 تم التحديث بنجاح إلى الإصدار ' . ($result['new_version'] ?? '');
        } else {
            $messageType = 'error';
            $message = $result['error'] ?? 'حدث خطأ أثناء التحديث.';
        }
        
        // Cleanup uploaded file
        @unlink($uploadPath);
    } else {
        $messageType = 'error';
        $message = 'فشل في رفع ملف التحديث. تأكد من اختيار ملف صحيح.';
    }
}

// Handle online download and install
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install_online_url'])) {
    $dlUrl = $_POST['install_online_url'];
    $targetVersion = $_POST['target_version'] ?? '';
    
    $uploadDir = APP_ROOT . '/data/updates/temp';
    @mkdir($uploadDir, 0777, true);
    $tempZip = $uploadDir . '/online_update_' . time() . '.zip';
    
    $downloadResult = downloadUpdatePackage($dlUrl, $tempZip);
    
    if ($downloadResult['success']) {
        $result = applyUpdate($tempZip);
        $updateLog = $result['log'] ?? [];
        
        if ($result['success']) {
            $messageType = 'success';
            $message = '🎉 تم تنزيل وتثبيت التحديث بنجاح إلى الإصدار ' . ($result['new_version'] ?? $targetVersion);
        } else {
            $messageType = 'error';
            $message = $result['error'] ?? 'حدث خطأ أثناء تطبيق التحديث.';
        }
        @unlink($tempZip);
    } else {
        $messageType = 'error';
        $message = $downloadResult['error'] ?? 'فشل في تنزيل ملف التحديث من Google Drive.';
    }
}

// Handle online check
$onlineUpdate = null;
if (isset($_GET['check_online'])) {
    $onlineUpdate = checkForUpdatesOnline();
}

// Get update history
$updateHistory = getUpdateHistory();

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
?>

<div class="container">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h1 style="margin: 0;"><i class="fa-solid fa-cloud-arrow-down"></i> تحديثات البرنامج</h1>
            <p style="color:var(--text-secondary); margin:4px 0 0;">إدارة تحديثات النظام والتحقق من الإصدارات الجديدة</p>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="../../index.php" class="btn btn-secondary" style="border-radius: 8px;">
                🏠 الرئيسية
            </a>
            <a href="index.php" class="btn btn-secondary" style="border-radius: 8px;">
                ⚙️ الإعدادات
            </a>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger' ?>" style="margin-bottom:20px; padding:16px 20px; border-radius:12px; font-weight:600; display:flex; align-items:center; gap:10px; <?= $messageType === 'success' ? 'background:rgba(16,185,129,0.12); border:1px solid rgba(16,185,129,0.3); color:#34d399;' : 'background:rgba(239,68,68,0.12); border:1px solid rgba(239,68,68,0.3); color:#f87171;' ?>">
        <i class="fa-solid <?= $messageType === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <!-- Current Version Info -->
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:24px;">
        <div class="card" style="padding:20px; text-align:center;">
            <div style="font-size:2.2rem; font-weight:800; color:#60a5fa; margin-bottom:6px;"><?= APP_VERSION ?></div>
            <div style="color:var(--text-secondary); font-size:0.9rem;">الإصدار الحالي</div>
        </div>
        <div class="card" style="padding:20px; text-align:center;">
            <div style="font-size:1.3rem; font-weight:700; color:#a78bfa; margin-bottom:6px;"><?= APP_BUILD_DATE ?></div>
            <div style="color:var(--text-secondary); font-size:0.9rem;">تاريخ البناء</div>
        </div>
        <div class="card" style="padding:20px; text-align:center;">
            <div style="font-size:1.3rem; font-weight:700; color:#34d399; margin-bottom:6px;">v<?= DB_SCHEMA_VERSION ?></div>
            <div style="color:var(--text-secondary); font-size:0.9rem;">إصدار قاعدة البيانات</div>
        </div>
        <div class="card" style="padding:20px; text-align:center;">
            <div style="font-size:1.3rem; font-weight:700; color:#fbbf24; margin-bottom:6px;"><?= count($updateHistory) ?></div>
            <div style="color:var(--text-secondary); font-size:0.9rem;">تحديثات سابقة</div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
        <!-- Upload Update (Offline) -->
        <div class="card">
            <div class="card-header" style="border-bottom:1px solid rgba(255,255,255,0.06); padding-bottom:14px; margin-bottom:16px;">
                <h3 style="display:flex; align-items:center; gap:8px; margin:0; font-size:1.05rem;">
                    <i class="fa-solid fa-file-zipper" style="color:#60a5fa;"></i>
                    تحديث يدوي (أوفلاين)
                </h3>
            </div>
            <p style="color:var(--text-secondary); font-size:0.9rem; margin-bottom:16px; line-height:1.7;">
                ارفع ملف التحديث (ZIP) الذي حصلت عليه من الدعم الفني لتحديث البرنامج.
                سيتم حفظ نسخة احتياطية من بياناتك تلقائياً قبل التحديث.
            </p>
            <form method="POST" enctype="multipart/form-data" id="updateForm" onsubmit="return confirmUpdate()">
                <div style="border:2px dashed rgba(96,165,250,0.3); border-radius:12px; padding:30px; text-align:center; margin-bottom:16px; transition:all 0.2s; cursor:pointer;" 
                     id="dropZone" onclick="document.getElementById('update_file').click()">
                    <i class="fa-solid fa-cloud-arrow-up" style="font-size:2.5rem; color:#60a5fa; margin-bottom:10px; display:block;"></i>
                    <div style="font-weight:700; color:var(--text-primary); margin-bottom:4px;" id="dropText">اضغط هنا أو اسحب ملف التحديث</div>
                    <div style="font-size:0.82rem; color:var(--text-secondary);" id="dropSubtext">صيغة ZIP فقط</div>
                </div>
                <input type="file" name="update_file" id="update_file" accept=".zip" style="display:none;" onchange="onFileSelected(this)">
                <button type="submit" class="btn btn-primary" id="uploadBtn" disabled style="width:100%; padding:12px; font-size:1rem; font-weight:700;">
                    <i class="fa-solid fa-rocket"></i>
                    تطبيق التحديث الآن
                </button>
            </form>
        </div>

        <!-- Online Check -->
        <div class="card">
            <div class="card-header" style="border-bottom:1px solid rgba(255,255,255,0.06); padding-bottom:14px; margin-bottom:16px;">
                <h3 style="display:flex; align-items:center; gap:8px; margin:0; font-size:1.05rem;">
                    <i class="fa-solid fa-wifi" style="color:#34d399;"></i>
                    التحقق أونلاين
                </h3>
            </div>
            <p style="color:var(--text-secondary); font-size:0.9rem; margin-bottom:16px; line-height:1.7;">
                تحقق من وجود تحديثات جديدة عبر الإنترنت (يتطلب اتصال بالإنترنت).
            </p>

            <?php if ($onlineUpdate): ?>
                <?php if (!empty($onlineUpdate['error'])): ?>
                    <div style="background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.2); border-radius:10px; padding:14px; color:#f87171; margin-bottom:16px; font-size:0.9rem;">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <?= htmlspecialchars($onlineUpdate['error']) ?>
                    </div>
                <?php elseif ($onlineUpdate['available']): ?>
                    <div style="background:rgba(16,185,129,0.08); border:1px solid rgba(16,185,129,0.2); border-radius:10px; padding:16px; margin-bottom:16px;">
                        <div style="font-weight:700; color:#34d399; font-size:1.1rem; margin-bottom:8px;">
                            <i class="fa-solid fa-gift"></i>
                            تحديث جديد متاح! الإصدار <?= htmlspecialchars($onlineUpdate['version']) ?>
                        </div>
                        <?php if (!empty($onlineUpdate['changelog'])): ?>
                        <ul style="margin:8px 0; padding-right:20px; color:var(--text-secondary); font-size:0.88rem; line-height:1.8;">
                            <?php foreach ($onlineUpdate['changelog'] as $change): ?>
                            <li><?= htmlspecialchars($change) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                        <?php if (!empty($onlineUpdate['download_url'])): ?>
                        <form method="POST" onsubmit="return confirm('هل تريد تنزيل وتثبيت التحديث الآن؟ سيتم أخذ نسخة احتياطية من قاعدة البيانات تلقائياً.')" style="margin-top:12px;">
                            <input type="hidden" name="install_online_url" value="<?= htmlspecialchars($onlineUpdate['download_url']) ?>">
                            <input type="hidden" name="target_version" value="<?= htmlspecialchars($onlineUpdate['version']) ?>">
                            <button type="submit" class="btn btn-success" style="padding:10px 18px; font-weight:700; display:inline-flex; align-items:center; gap:8px;">
                                <i class="fa-solid fa-cloud-arrow-down"></i> تنزيل وتثبيت التحديث الآن تلقائياً
                            </button>
                            <a href="<?= htmlspecialchars($onlineUpdate['download_url']) ?>" target="_blank" class="btn btn-secondary" style="padding:10px 14px; font-size:0.85rem; margin-right:8px;">
                                <i class="fa-solid fa-download"></i> تنزيل الـ ZIP فقط
                            </a>
                        </form>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div style="background:rgba(96,165,250,0.08); border:1px solid rgba(96,165,250,0.2); border-radius:10px; padding:14px; color:#60a5fa; margin-bottom:16px; font-size:0.9rem;">
                        <i class="fa-solid fa-circle-check"></i>
                        البرنامج محدّث بالفعل. لا توجد تحديثات جديدة.
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <a href="?check_online=1" class="btn btn-secondary" style="width:100%; padding:12px; font-size:1rem; font-weight:600; text-align:center; display:block;">
                <i class="fa-solid fa-magnifying-glass"></i>
                التحقق من التحديثات
            </a>
        </div>
    </div>

    <!-- Update Log (if just updated) -->
    <?php if (!empty($updateLog)): ?>
    <div class="card" style="margin-top:20px;">
        <div class="card-header" style="border-bottom:1px solid rgba(255,255,255,0.06); padding-bottom:14px; margin-bottom:16px;">
            <h3 style="display:flex; align-items:center; gap:8px; margin:0; font-size:1.05rem;">
                <i class="fa-solid fa-terminal" style="color:#a78bfa;"></i>
                سجل عملية التحديث
            </h3>
        </div>
        <div style="background:rgba(0,0,0,0.3); border-radius:10px; padding:16px; font-family:monospace; font-size:0.85rem; max-height:300px; overflow-y:auto; direction:ltr; text-align:left;">
            <?php foreach ($updateLog as $entry): ?>
            <div style="margin-bottom:4px;">
                <span style="color:#64748b;">[<?= $entry['time'] ?>]</span>
                <span style="color:<?= strpos($entry['message'], '✅') !== false ? '#34d399' : (strpos($entry['message'], '❌') !== false ? '#f87171' : '#e2e8f0') ?>;">
                    <?= htmlspecialchars($entry['message']) ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Update History -->
    <?php if (!empty($updateHistory)): ?>
    <div class="card" style="margin-top:20px;">
        <div class="card-header" style="border-bottom:1px solid rgba(255,255,255,0.06); padding-bottom:14px; margin-bottom:16px;">
            <h3 style="display:flex; align-items:center; gap:8px; margin:0; font-size:1.05rem;">
                <i class="fa-solid fa-clock-rotate-left" style="color:#fbbf24;"></i>
                سجل التحديثات السابقة
            </h3>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الإصدار</th>
                        <th>من إصدار</th>
                        <th>تاريخ التحديث</th>
                        <th>ملاحظات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_reverse($updateHistory) as $i => $h): ?>
                    <tr>
                        <td><?= count($updateHistory) - $i ?></td>
                        <td><span class="badge badge-primary" style="font-weight:700;"><?= htmlspecialchars($h['version']) ?></span></td>
                        <td style="color:var(--text-secondary);"><?= htmlspecialchars($h['from_version'] ?? '-') ?></td>
                        <td style="color:var(--text-secondary);"><?= htmlspecialchars($h['applied_at']) ?></td>
                        <td>
                            <?php if (!empty($h['changelog'])): ?>
                            <ul style="margin:0; padding-right:16px; font-size:0.85rem; color:var(--text-secondary);">
                                <?php foreach (array_slice($h['changelog'], 0, 3) as $change): ?>
                                <li><?= htmlspecialchars($change) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php else: ?>
                            <span style="color:var(--text-muted);">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function onFileSelected(input) {
    const btn = document.getElementById('uploadBtn');
    const dropText = document.getElementById('dropText');
    const dropSubtext = document.getElementById('dropSubtext');
    const dropZone = document.getElementById('dropZone');

    if (input.files.length > 0) {
        const file = input.files[0];
        if (!file.name.toLowerCase().endsWith('.zip')) {
            alert('يرجى اختيار ملف بصيغة ZIP فقط.');
            input.value = '';
            btn.disabled = true;
            return;
        }
        dropText.textContent = file.name;
        dropSubtext.textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB';
        dropZone.style.borderColor = 'rgba(16, 185, 129, 0.5)';
        dropZone.style.background = 'rgba(16, 185, 129, 0.05)';
        btn.disabled = false;
    } else {
        btn.disabled = true;
    }
}

function confirmUpdate() {
    return confirm('⚠️ هل أنت متأكد من تحديث البرنامج؟\n\nسيتم حفظ نسخة احتياطية تلقائياً قبل التحديث.\nلا تغلق المتصفح أثناء عملية التحديث.');
}

// Drag and drop
const dropZone = document.getElementById('dropZone');
if (dropZone) {
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.style.borderColor = 'rgba(96, 165, 250, 0.6)';
        dropZone.style.background = 'rgba(96, 165, 250, 0.05)';
    });
    dropZone.addEventListener('dragleave', () => {
        dropZone.style.borderColor = 'rgba(96, 165, 250, 0.3)';
        dropZone.style.background = 'transparent';
    });
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        const fileInput = document.getElementById('update_file');
        fileInput.files = e.dataTransfer.files;
        onFileSelected(fileInput);
    });
}
</script>
</div><!-- End container -->

<?php require_once '../../includes/footer.php'; ?>