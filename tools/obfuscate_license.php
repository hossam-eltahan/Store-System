<?php
/**
 * Obfuscates config/license.php for client release
 */
$sourcePath = 'C:/xampp/htdocs/Store-System/config/license.php';
$targetPath = 'C:/StoreSystem_Build/portable/htdocs/Store-System/config/license.php';

if (!file_exists($sourcePath)) {
    die("Source license file not found.\n");
}

$content = file_get_contents($sourcePath);

// 1. Strip PHP tags
$code = preg_replace('/^<\?php\s*/i', '', $content);

// 2. Multi-layer encoding: Deflate + Base64
$compressed = gzdeflate($code, 9);
$encoded = base64_encode($compressed);

// 3. Build self-contained evaluator wrapper
$obfuscated = "<?php\n";
$obfuscated .= "/**\n";
$obfuscated .= " * Store System Protection Core - Encrypted Binary\n";
$obfuscated .= " * Copyright (c) 2026. All rights reserved.\n";
$obfuscated .= " */\n";
$obfuscated .= "eval(gzinflate(base64_decode('" . $encoded . "')));\n";

// Ensure destination directory exists
$destDir = dirname($targetPath);
if (!is_dir($destDir)) {
    mkdir($destDir, 0777, true);
}

file_put_contents($targetPath, $obfuscated);
echo "Obfuscated license written to: {$targetPath} (" . strlen($obfuscated) . " bytes)\n";