<?php
/**
 * Script to generate standard sample_gradebook.xlsx for EduRadar THCS.
 * Can be run via CLI: D:\xam\php\php.exe scripts/generate_sample_gradebook.php
 */

declare(strict_types=1);

require_once __DIR__ . '/proposed_xlsx_helper.php';

$sampleGradebookData = [
    ['STT', 'Họ và tên', 'Số điện thoại phụ huynh', 'ĐĐGtx1', 'ĐĐGtx2', 'ĐĐGtx3', 'ĐĐGtx4', 'ĐĐGgk', 'ĐĐGck'],
    [1, 'Nguyễn Văn An', '0901234567', 8.0, 7.0, 6.5, 6.0, 5.0, 5.0],
    [2, 'Trần Minh Khang', '0912345678', 6.0, 7.0, 8.0, 8.5, 8.5, 9.0],
    [3, 'Lê Thị Bích Ngọc', '0923456789', 9.0, 9.5, 9.0, 8.5, 9.0, 9.5],
    [4, 'Phạm Quốc Huy', '0934567890', 4.0, 4.5, 4.0, 3.5, 4.0, 4.0],
    [5, 'Hoàng Thu Trang', '0945678901', 7.0, 6.0, 4.5, 6.0, 6.0, 6.5]
];

$outputFile = __DIR__ . '/sample_gradebook.xlsx';
$binaryContent = create_xlsx($sampleGradebookData, 'BangDiem');
file_put_contents($outputFile, $binaryContent);

echo "Successfully generated sample_gradebook.xlsx (" . strlen($binaryContent) . " bytes)\n";

// Verify by parsing it back
$parsed = parse_xlsx($outputFile);
echo "Verified row count: " . count($parsed) . "\n";
echo "Header: " . implode(' | ', $parsed[0]) . "\n";
echo "First student: " . implode(' | ', $parsed[1]) . "\n";
