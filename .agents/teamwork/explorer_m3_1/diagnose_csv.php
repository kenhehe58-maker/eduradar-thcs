<?php
$rootDir = dirname(__DIR__, 3);
$files = ['sample_students.csv', 'sample_vnedu.csv'];
foreach ($files as $file) {
    $path = $rootDir . DIRECTORY_SEPARATOR . $file;
    if (!file_exists($path)) {
        echo "File not found: $path\n";
        continue;
    }
    $content = file_get_contents($path);
    $has_bom = (substr($content, 0, 3) === "\xEF\xBB\xBF");
    echo "=== $file ===\n";
    echo "Path: $path\n";
    echo "Size: " . strlen($content) . " bytes\n";
    echo "Has UTF-8 BOM: " . ($has_bom ? "YES" : "NO") . "\n";
    echo "First line hex: " . bin2hex(substr($content, 0, 32)) . "\n";
    $firstLine = strtok($content, "\r\n");
    echo "First line text: " . $firstLine . "\n\n";
}
