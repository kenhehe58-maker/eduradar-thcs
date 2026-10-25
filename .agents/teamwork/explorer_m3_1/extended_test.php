<?php
/**
 * Test extended prototype with date normalization and sample file generation.
 */

require_once __DIR__ . '/prototype_test.php';

function normalize_excel_date($raw): ?string {
    if ($raw === null || $raw === '') {
        return null;
    }

    $raw = trim((string)$raw);

    // If it's a numeric Excel serial date (e.g. 42106 for 2015-04-12)
    if (is_numeric($raw) && (float)$raw > 1000 && (float)$raw < 100000) {
        $days = (float)$raw;
        // Excel 1900 leap year bug adjustment (day 60 is phantom 1900-02-29)
        if ($days > 60) {
            $days -= 1;
        }
        $unixTimestamp = ($days - 25568) * 86400;
        return gmdate('Y-m-d', (int)$unixTimestamp);
    }

    // DD/MM/YYYY or DD-MM-YYYY
    if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/', $raw, $m)) {
        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }

    // YYYY-MM-DD or YYYY/MM/DD
    if (preg_match('/^(\d{4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})$/', $raw, $m)) {
        return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
    }

    return null;
}

// Test Date Normalization
assert(normalize_excel_date('2015-04-12') === '2015-04-12');
assert(normalize_excel_date('12/04/2015') === '2015-04-12');
assert(normalize_excel_date('12-04-2015') === '2015-04-12');
assert(normalize_excel_date('5/7/2015') === '2015-07-05');
assert(normalize_excel_date(42106) === '2015-04-12'); // 42106 is 2015-04-12 in Excel!
echo "[PASS] Test 6: normalize_excel_date handles ISO, Vietnamese formats, and Excel serial numbers\n";

// Test Generating sample_students.xlsx content
$sampleStudentData = [
    ['STT', 'Mã học sinh', 'Họ và tên', 'Ngày sinh', 'Giới tính', 'Số điện thoại phụ huynh'],
    [1, 'HS6A101', 'Nguyễn Tuấn Kiệt', '2015-04-12', 'Nam', '0912345601'],
    [2, 'HS6A102', 'Trần Phương Linh', '2015-08-23', 'Nữ', '0912345602'],
    [3, 'HS6A103', 'Vũ Đức Đam', '2015-01-19', 'Nam', '0912345603'],
    [4, 'HS6A104', 'Lê Hồng Nhung', '2015-11-05', 'Nữ', '0912345604'],
    [5, 'HS6A105', 'Phan Gia Hưng', '2015-06-30', 'Nam', '0912345605'],
    [6, 'HS6A106', 'Đặng Minh Quân', '2015-09-14', 'Nam', '0912345606'],
    [7, 'HS6A107', 'Bùi Mai Anh', '2015-02-28', 'Nữ', '0912345607'],
    [8, 'HS6A108', 'Đỗ Hoàng Nam', '2015-12-03', 'Nam', '0912345608']
];

$xlsxContent = create_xlsx($sampleStudentData, 'DanhSachHocSinh');
$testSamplePath = __DIR__ . '/test_sample_students.xlsx';
file_put_contents($testSamplePath, $xlsxContent);

$parsedBack = parse_xlsx($testSamplePath);
assert(count($parsedBack) === 9); // Header + 8 students
assert($parsedBack[1][1] === 'HS6A101');
assert($parsedBack[1][2] === 'Nguyễn Tuấn Kiệt');
assert($parsedBack[1][3] === '2015-04-12');
assert($parsedBack[1][5] === '0912345601');
assert($parsedBack[8][2] === 'Đỗ Hoàng Nam');
echo "[PASS] Test 7: sample_students.xlsx round-trip and validation succeeded!\n";

unlink($testSamplePath);
echo "\n>>> ALL EXTENDED TESTS PASSED! <<<\n";
