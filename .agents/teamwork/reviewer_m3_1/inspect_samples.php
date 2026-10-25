<?php
$projectRoot = dirname(__DIR__, 3);
require_once $projectRoot . '/includes/xlsx_helper.php';

echo "=== CHECKING sample_students.xlsx ===\n";
$f1 = $projectRoot . '/sample_students.xlsx';
if (!file_exists($f1)) {
    die("sample_students.xlsx does not exist!\n");
}
$rows1 = parse_xlsx($f1);
echo "Rows: " . count($rows1) . "\n";
foreach ($rows1 as $idx => $r) {
    echo "Row $idx: " . implode(" | ", $r) . "\n";
}

echo "\n=== CHECKING sample_gradebook.xlsx ===\n";
$f2 = $projectRoot . '/sample_gradebook.xlsx';
if (!file_exists($f2)) {
    die("sample_gradebook.xlsx does not exist!\n");
}
$rows2 = parse_xlsx($f2);
echo "Rows: " . count($rows2) . "\n";
foreach ($rows2 as $idx => $r) {
    echo "Row $idx: " . implode(" | ", $r) . "\n";
}
