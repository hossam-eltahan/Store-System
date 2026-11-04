<?php
/**
 * Barcode Label Printing
 * طباعة ملصقات الباركود
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requirePermission('products.view');

$pageTitle = 'طباعة ملصقات الباركود';

// Pure PHP Code 128 SVG generator
if (!function_exists('renderBarcodeSvg')) {
function renderBarcodeSvg($text, $barWidth = 2, $barHeight = 42) {
    $text = (string)$text;
    if ($text === '') return '';

    $patterns = [
        "212222", "222122", "222221", "121223", "121322", "131222", "122213", "122312", "132212", "221213",
        "221312", "231212", "112232", "122132", "122231", "113222", "123122", "123221", "223211", "221132",
        "221231", "213212", "223112", "312131", "311222", "321122", "321221", "312212", "322112", "322211",
        "212123", "212321", "232121", "111323", "131123", "131321", "112313", "132113", "132311", "211313",
        "231113", "231311", "112133", "112331", "132131", "113123", "113321", "133121", "313121", "211331",
        "231131", "213113", "213311", "213131", "311123", "311321", "331121", "312113", "312311", "332111",
        "314111", "221411", "431111", "111224", "111422", "121124", "121421", "141122", "141221", "112214",
        "112412", "122114", "122411", "142112", "142211", "241211", "221114", "413111", "241112", "134111",
        "111242", "121142", "121241", "114212", "124112", "124211", "411212", "421112", "421211", "212141",
        "214121", "412121", "111143", "111341", "131141", "114113", "114311", "411113", "411311", "113141",
        "114131", "311141", "411131", "211412", "211214", "211232", "2331112"
    ];

    $codes = [104]; // Start B
    $checksum = 104;

    for ($i = 0; $i < strlen($text); $i++) {
        $ascii = ord($text[$i]);
        $val = $ascii - 32;
        if ($val < 0 || $val > 95) $val = 0;
        $codes[] = $val;
        $checksum += $val * ($i + 1);
    }

    $codes[] = $checksum % 103;
    $codes[] = 106; // Stop pattern

    $barModules = [];
    foreach ($codes as $codeVal) {
        $pattern = $patterns[$codeVal] ?? $patterns[0];
        $isBar = true;
        for ($p = 0; $p < strlen($pattern); $p++) {
            $w = (int)$pattern[$p];
            for ($k = 0; $k < $w; $k++) {
                $barModules[] = $isBar ? 1 : 0;
            }
            $isBar = !$isBar;
        }
    }

    $totalModules = count($barModules);
    $quietZone = 8;
    $svgWidth = ($totalModules + ($quietZone * 2)) * $barWidth;
    $svgHeight = $barHeight;

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $svgWidth . ' ' . $svgHeight . '" width="100%" height="' . $svgHeight . '" style="display:block;margin:0 auto;max-width:100%;">';
    $svg .= '<rect width="100%" height="100%" fill="#ffffff"/>';

    $x = $quietZone * $barWidth;
    $inBar = false;
    $barStart = 0;

    for ($m = 0; $m < $totalModules; $m++) {
        if ($barModules[$m] === 1) {
            if (!$inBar) {
                $inBar = true;
                $barStart = $x;
            }
        } else {
            if ($inBar) {
                $w = $x - $barStart;
                $svg .= '<rect x="' . $barStart . '" y="0" width="' . $w . '" height="' . $barHeight . '" fill="#000000"/>';
                $inBar = false;
            }
        }
        $x += $barWidth;
    }
    if ($inBar) {
        $w = $x - $barStart;
        $svg .= '<rect x="' . $barStart . '" y="0" width="' . $w . '" height="' . $barHeight . '" fill="#000000"/>';
    }

    $svg .= '</svg>';
    return $svg;
}
}

$productId = (int)($_GET['id'] ?? 0);
$allProducts = getRows("SELECT id, code, barcode, name, price FROM products ORDER BY name ASC");

$selectedProduct = null;
if ($productId > 0) {
    $selectedProduct = getRow("SELECT * FROM products WHERE id = ?", [$productId]);
} elseif (!empty($allProducts)) {
    $selectedProduct = $allProducts[0];
    $productId = (int)$selectedProduct['id'];
}

// Auto-assign barcode if product lacks one
if ($selectedProduct && empty($selectedProduct['barcode'])) {
    $newBarcode = generateUniqueBarcode();
    execute("UPDATE products SET barcode = ? WHERE id = ?", [$newBarcode, $selectedProduct['id']]);
    $selectedProduct['barcode'] = $newBarcode;
}

$storeName = getSetting('store_name', 'محل أجهزة منزلية');
$copies = max(1, min(100, (int)($_GET['copies'] ?? 1)));
$size = $_GET['size'] ?? 'thermal_38x25';

// Handle checkboxes: if submitted, check presence in $_GET; if first load, default all to true
$formSubmitted = isset($_GET['submitted']);
if ($formSubmitted) {
    $showStore = isset($_GET['show_store']);
    $showName = isset($_GET['show_name']);
    $showPrice = isset($_GET['show_price']);
    $showBarcodeNum = isset($_GET['show_code']);
} else {
    $showStore = true;
    $showName = true;
    $showPrice = true;
    $showBarcodeNum = true;
}

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<style>
/* Screen controls */
.print-controls {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 18px 24px;
    margin: 15px auto 25px;
    max-width: 900px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.06);
}

.controls-row {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: flex-end;
}

.labels-preview-area {
    background: #f1f5f9;
    padding: 25px;
    border-radius: 12px;
    margin: 0 auto;
    max-width: 950px;
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    justify-content: center;
}

/* Individual label styling */
.barcode-label {
    background: #ffffff;
    border: 1px dashed #94a3b8;
    box-sizing: border-box;
    text-align: center;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 4px 6px;
    page-break-inside: avoid;
    break-inside: avoid;
}

/* Sizes */
.size-thermal_38x25 {
    width: 38mm;
    height: 25mm;
    padding: 2mm 3mm;
}
.size-thermal_38x25 .lbl-store { font-size: 8px; font-weight: 700; line-height: 1; margin-bottom: 1px; color: #334155; }
.size-thermal_38x25 .lbl-name { font-size: 9px; font-weight: 800; line-height: 1.1; max-height: 20px; overflow: hidden; color: #0f172a; }
.size-thermal_38x25 .lbl-barcode { height: 26px; }
.size-thermal_38x25 .lbl-barcode svg { max-height: 26px; width: 100%; }
.size-thermal_38x25 .lbl-num { font-size: 8px; font-family: monospace; font-weight: 700; letter-spacing: 0.5px; }
.size-thermal_38x25 .lbl-price { font-size: 9px; font-weight: 900; color: #000; }

.size-thermal_50x30 {
    width: 50mm;
    height: 30mm;
    padding: 3mm 4mm;
}
.size-thermal_50x30 .lbl-store { font-size: 9px; font-weight: 700; margin-bottom: 2px; }
.size-thermal_50x30 .lbl-name { font-size: 11px; font-weight: 800; line-height: 1.15; max-height: 26px; overflow: hidden; }
.size-thermal_50x30 .lbl-barcode { height: 32px; }
.size-thermal_50x30 .lbl-barcode svg { max-height: 32px; width: 100%; }
.size-thermal_50x30 .lbl-num { font-size: 10px; font-family: monospace; font-weight: 700; }
.size-thermal_50x30 .lbl-price { font-size: 11px; font-weight: 900; }

.size-thermal_80mm {
    width: 72mm;
    min-height: 40mm;
    padding: 4mm 6mm;
    margin-bottom: 4mm;
}
.size-thermal_80mm .lbl-store { font-size: 11px; font-weight: 700; }
.size-thermal_80mm .lbl-name { font-size: 13px; font-weight: 800; }
.size-thermal_80mm .lbl-barcode { height: 42px; }
.size-thermal_80mm .lbl-barcode svg { max-height: 42px; width: 100%; }
.size-thermal_80mm .lbl-num { font-size: 12px; font-family: monospace; font-weight: 700; }
.size-thermal_80mm .lbl-price { font-size: 14px; font-weight: 900; }

.size-a4_grid {
    width: 63.5mm;
    height: 38.1mm;
    padding: 3mm;
}
.size-a4_grid .lbl-store { font-size: 9.5px; font-weight: 700; line-height: 1; margin-bottom: 2px; color: #334155; }
.size-a4_grid .lbl-name { font-size: 11px; font-weight: 800; line-height: 1.15; max-height: 26px; overflow: hidden; color: #0f172a; }
.size-a4_grid .lbl-barcode { height: 34px; }
.size-a4_grid .lbl-barcode svg { max-height: 34px; width: 100%; }
.size-a4_grid .lbl-num { font-size: 10px; font-family: monospace; font-weight: 700; letter-spacing: 0.5px; }
.size-a4_grid .lbl-price { font-size: 11px; font-weight: 900; color: #000; }

/* Dynamic @page sizing according to selected format */
<?php if ($size === 'a4_grid'): ?>
@page {
    size: A4 portrait;
    margin: 8mm;
}
<?php elseif ($size === 'thermal_80mm'): ?>
@page {
    size: 80mm auto;
    margin: 0;
}
<?php elseif ($size === 'thermal_50x30'): ?>
@page {
    size: 50mm 30mm;
    margin: 0;
}
<?php else: ?>
@page {
    size: 38mm 25mm;
    margin: 0;
}
<?php endif; ?>

/* Comprehensive Print Stylesheet */
@media print {
    html, body {
        background: #ffffff !important;
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        min-height: 0 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    /* CRITICAL OVERRIDE: print.css sets body * { visibility: hidden; } */
    body * {
        visibility: visible !important;
    }

    /* Completely hide navigation, sidebars, alerts, buttons */
    .print-controls,
    .print-controls *,
    .top-header,
    .top-header *,
    .sidebar,
    .sidebar *,
    nav,
    .navbar,
    header,
    footer,
    aside,
    #simple-toast,
    .no-print,
    .noprint,
    #licenseExpiryAlertBanner {
        display: none !important;
        visibility: hidden !important;
        height: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: hidden !important;
    }

    /* Main layout wrappers must have no margins or extra height */
    .main-content,
    .container {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        border: none !important;
        box-shadow: none !important;
    }

    /* Print area styling - OVERRIDE print.css position:absolute */
    .labels-preview-area,
    .invoice-print {
        position: static !important;
        right: auto !important;
        left: auto !important;
        top: auto !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        visibility: visible !important;
    }

    .labels-preview-area * {
        visibility: visible !important;
    }

    <?php if ($size === 'a4_grid'): ?>
    .labels-preview-area {
        display: flex !important;
        flex-wrap: wrap !important;
        justify-content: flex-start !important;
        gap: 2mm !important;
    }
    <?php else: ?>
    .labels-preview-area {
        display: block !important;
        gap: 0 !important;
    }
    <?php endif; ?>

    /* Keep hidden elements hidden during print */
    .lbl-store[style*="display: none"],
    .lbl-store[style*="display:none"],
    .lbl-name[style*="display: none"],
    .lbl-name[style*="display:none"],
    .lbl-price[style*="display: none"],
    .lbl-price[style*="display:none"],
    .lbl-num[style*="display: none"],
    .lbl-num[style*="display:none"] {
        display: none !important;
        visibility: hidden !important;
    }

    .barcode-label {
        background: #ffffff !important;
        box-shadow: none !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }

    /* Thermal 38x25mm single label per page */
    .size-thermal_38x25 {
        width: 38mm !important;
        height: 25mm !important;
        padding: 1.5mm 2.5mm !important;
        margin: 0 auto !important;
        border: none !important;
        page-break-after: always !important;
        break-after: page !important;
    }

    /* Thermal 50x30mm single label per page */
    .size-thermal_50x30 {
        width: 50mm !important;
        height: 30mm !important;
        padding: 2mm 3.5mm !important;
        margin: 0 auto !important;
        border: none !important;
        page-break-after: always !important;
        break-after: page !important;
    }

    /* Prevent trailing blank page on thermal rolls */
    .barcode-label:last-child {
        page-break-after: avoid !important;
        break-after: avoid !important;
    }

    /* Thermal 80mm continuous receipt */
    .size-thermal_80mm {
        width: 72mm !important;
        min-height: 38mm !important;
        padding: 3mm 4mm !important;
        margin: 0 auto 4mm auto !important;
        border: none !important;
        border-bottom: 1px dashed #94a3b8 !important;
        page-break-after: auto !important;
        break-after: auto !important;
    }

    /* A4 Sticker Sheet */
    .size-a4_grid {
        width: 63.5mm !important;
        height: 38.1mm !important;
        padding: 2.5mm !important;
        margin: 1.5mm !important;
        border: 1px dashed #cbd5e1 !important;
        page-break-after: auto !important;
        break-after: auto !important;
        display: inline-flex !important;
    }
}
</style>

<div class="container" style="max-width: 1000px; padding: 20px 10px;">
    <!-- Controls (Hidden on print) -->
    <div class="print-controls no-print">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">
            <h2 style="margin: 0; font-size: 1.3em; color: #1e293b;">🏷️ طباعة ملصقات الباركود</h2>
            <div style="display: flex; gap: 8px;">
                <button type="button" onclick="window.print()" class="btn btn-primary" style="font-weight: 800; font-size: 1em; padding: 8px 20px;">
                    🖨️ طباعة الآن
                </button>
                <a href="index.php" class="btn btn-secondary">العودة للأصناف</a>
            </div>
        </div>

        <form method="GET" id="labelForm">
            <input type="hidden" name="submitted" value="1">
            <div class="controls-row">
                <div class="form-group" style="flex: 2; min-width: 200px;">
                    <label class="form-label" style="font-weight: 700;">📦 الصنف المراد طباعته</label>
                    <select name="id" class="form-control" onchange="this.form.submit()">
                        <?php foreach ($allProducts as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo ((int)$p['id'] === $productId) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['name']); ?> (<?php echo $p['code']; ?><?php echo !empty($p['barcode']) ? ' - ' . $p['barcode'] : ''; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="width: 120px;">
                    <label class="form-label" style="font-weight: 700;">🔢 عدد النسخ</label>
                    <input type="number" name="copies" class="form-control" value="<?php echo $copies; ?>" min="1" max="100" onchange="this.form.submit()">
                </div>

                <div class="form-group" style="flex: 1.5; min-width: 180px;">
                    <label class="form-label" style="font-weight: 700;">📐 مقاس الملصق / الطابعة</label>
                    <select name="size" class="form-control" onchange="this.form.submit()">
                        <option value="thermal_38x25" <?php echo ($size === 'thermal_38x25') ? 'selected' : ''; ?>>طابعة ملصقات (38 × 25 مم)</option>
                        <option value="thermal_50x30" <?php echo ($size === 'thermal_50x30') ? 'selected' : ''; ?>>طابعة ملصقات (50 × 30 مم)</option>
                        <option value="thermal_80mm" <?php echo ($size === 'thermal_80mm') ? 'selected' : ''; ?>>رول فواتير 80 مم (مستمر)</option>
                        <option value="a4_grid" <?php echo ($size === 'a4_grid') ? 'selected' : ''; ?>>ورق ستيكر A4</option>
                    </select>
                </div>
            </div>

            <div style="display: flex; gap: 20px; margin-top: 15px; flex-wrap: wrap; background: #f8fafc; padding: 12px 16px; border-radius: 8px; border: 1px solid #e2e8f0;">
                <span style="font-weight: 700; font-size: 13px; color: #475569; align-self: center;">خيارات المحتوى:</span>
                <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; cursor: pointer; user-select: none;">
                    <input type="checkbox" name="show_store" id="chk_show_store" value="1" <?php echo $showStore ? 'checked' : ''; ?> onchange="toggleLabelField('lbl-store', this.checked)">
                    اسم المتجر
                </label>
                <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; cursor: pointer; user-select: none;">
                    <input type="checkbox" name="show_name" id="chk_show_name" value="1" <?php echo $showName ? 'checked' : ''; ?> onchange="toggleLabelField('lbl-name', this.checked)">
                    اسم الصنف
                </label>
                <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; cursor: pointer; user-select: none;">
                    <input type="checkbox" name="show_price" id="chk_show_price" value="1" <?php echo $showPrice ? 'checked' : ''; ?> onchange="toggleLabelField('lbl-price', this.checked)">
                    السعر
                </label>
                <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; cursor: pointer; user-select: none;">
                    <input type="checkbox" name="show_code" id="chk_show_code" value="1" <?php echo $showBarcodeNum ? 'checked' : ''; ?> onchange="toggleLabelField('lbl-num', this.checked)">
                    رقم الباركود
                </label>
            </div>
        </form>
    </div>

    <!-- Preview & Print Area -->
    <?php if ($selectedProduct): ?>
    <?php
        $barcodeVal = $selectedProduct['barcode'] ?: $selectedProduct['code'];
        $barHeight = ($size === 'thermal_38x25') ? 26 : (($size === 'thermal_50x30') ? 32 : 44);
        $svgMarkup = renderBarcodeSvg($barcodeVal, 2, $barHeight);
    ?>
    <div class="labels-preview-area invoice-print">
        <?php for ($i = 0; $i < $copies; $i++): ?>
        <div class="barcode-label size-<?php echo htmlspecialchars($size); ?>">
            <div class="lbl-store" style="<?php echo $showStore ? '' : 'display:none;'; ?>">
                <?php echo htmlspecialchars($storeName); ?>
            </div>

            <div class="lbl-name" style="<?php echo $showName ? '' : 'display:none;'; ?>">
                <?php echo htmlspecialchars($selectedProduct['name']); ?>
            </div>

            <div class="lbl-barcode" style="margin: 2px 0;">
                <?php echo $svgMarkup; ?>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0 4px;">
                <span class="lbl-num" style="<?php echo $showBarcodeNum ? '' : 'display:none;'; ?>"><?php echo htmlspecialchars($barcodeVal); ?></span>
                <span class="lbl-price" style="<?php echo $showPrice ? '' : 'display:none;'; ?>"><?php echo formatCurrency($selectedProduct['price']); ?></span>
            </div>
        </div>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<script>
function toggleLabelField(className, isVisible) {
    var els = document.querySelectorAll('.' + className);
    for (var i = 0; i < els.length; i++) {
        els[i].style.display = isVisible ? '' : 'none';
    }
}
</script>

<?php include '../../includes/footer.php'; ?>