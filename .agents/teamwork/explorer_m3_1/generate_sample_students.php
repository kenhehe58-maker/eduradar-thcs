<?php
/**
 * Script to generate standard sample_students.xlsx for EduRadar THCS.
 * Can be run via CLI: D:\xam\php\php.exe scripts/generate_sample_students.php
 */

declare(strict_types=1);

require_once __DIR__ . '/proposed_xlsx_helper.php';

$sampleData = [
    ['STT', 'Mã học sinh', 'Họ và tên', 'Ngày sinh', 'Giới tính', 'Số điện thoại phụ huynh'],
    [1, 'HS6A101', 'Nguyễn Tuấn Kiệt', '2015-04-12', 'Nam', '0912345601'],
    [2, 'HS6A102', 'Trần Phương Linh', '2015-08-23', 'Nữ', '0912345602'],
    [3, 'HS6A103', 'Vũ Đức Đam', '2015-01-19', 'Nam', '0912345603'],
    [4, 'HS6A104', 'Lê Hồng Nhung', '2015-11-05', 'Nữ', '0912345604'],
    [5, 'HS6A105', 'Phan Gia Hưng', '2015-06-30', 'Nam', '0912345605'],
    [6, 'HS6A106', 'Đặng Minh Quân', '2015-09-14', 'Nam', '0912345606'],
    [7, 'HS6A107', 'Bùi Mai Anh', '2015-02-28', 'Nữ', '0912345607'],
    [8, 'HS6A108', 'Đỗ Hoàng Nam', '2015-12-03', 'Nam', '0912345608'],
    [9, 'HS6A109', 'Phạm Quỳnh Nga', '2015-05-15', 'Nữ', '0912345609'],
    [10, 'HS6A110', 'Ngô Quang Hải', '2015-10-22', 'Nam', '0912345610']
];

$outputFile = __DIR__ . '/sample_students.xlsx';
$binaryContent = create_xlsx($sampleData, 'DanhSachHocSinh');
file_put_contents($outputFile, $binaryContent);

echo "Successfully generated sample_students.xlsx (" . strlen($binaryContent) . " bytes)\n";

// Verify by parsing it back
$parsed = parse_xlsx($outputFile);
echo "Verified row count: " . count($parsed) . "\n";
echo "Header: " . implode(' | ', $parsed[0]) . "\n";
echo "First student: " . implode(' | ', $parsed[1]) . "\n";
