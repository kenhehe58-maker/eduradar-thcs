<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__, 3);
require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/xlsx_helper.php';

$pdo = db();
$results = [];

function runStress(string $name, callable $fn) {
    global $results;
    try {
        $res = $fn();
        $results[] = ['name' => $name, 'status' => 'PASS', 'details' => is_string($res) ? $res : 'OK'];
        echo "[PASS] $name" . (is_string($res) ? " ($res)" : "") . "\n";
    } catch (Throwable $e) {
        $results[] = ['name' => $name, 'status' => 'FAIL', 'details' => $e->getMessage()];
        echo "[FAIL] $name: " . $e->getMessage() . "\n";
    }
}

// Helper cURL client
class StressClient {
    private string $cookieFile;
    public function __construct() {
        $this->cookieFile = sys_get_temp_dir() . '/stress_cookie_' . uniqid() . '.txt';
    }
    public function __destruct() {
        @unlink($this->cookieFile);
    }
    public function post(string $url, $fields, bool $isMultipart = false) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $isMultipart ? $fields : http_build_query($fields));
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['status' => $code, 'body' => $res];
    }
    public function get(string $url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['status' => $code, 'body' => $res];
    }
}

// 1. Corrupted / non-zip file to parse_xlsx
runStress("parse_xlsx handles non-existent file safely", function() {
    $res = parse_xlsx("non_existent_file_" . uniqid() . ".xlsx");
    if ($res !== []) throw new Exception("Expected empty array");
    return "Returned []";
});

runStress("parse_xlsx handles corrupted/invalid ZIP file safely", function() {
    $tmp = tempnam(sys_get_temp_dir(), 'corrupt_') . '.xlsx';
    file_put_contents($tmp, "NOT A ZIP FILE CORRUPTED DATA");
    $res = parse_xlsx($tmp);
    @unlink($tmp);
    if ($res !== []) throw new Exception("Expected empty array on corrupt file");
    return "Returned []";
});

// 2. XML special characters roundtrip in create_xlsx -> parse_xlsx
runStress("create_xlsx & parse_xlsx round-trip with XML entities (<, >, &, \", ')", function() {
    $specialData = [
        ['STT', 'Họ và tên', 'Ghi chú'],
        ['1', 'Trần & Linh <VIP>', 'Đạt giải "Nhất" \'Toán\'']
    ];
    $bin = create_xlsx($specialData);
    $tmp = tempnam(sys_get_temp_dir(), 'special_') . '.xlsx';
    file_put_contents($tmp, $bin);
    $parsed = parse_xlsx($tmp);
    @unlink($tmp);
    if (($parsed[1][1] ?? '') !== 'Trần & Linh <VIP>') {
        throw new Exception("Entity decoding failed: " . ($parsed[1][1] ?? ''));
    }
    if (($parsed[1][2] ?? '') !== 'Đạt giải "Nhất" \'Toán\'') {
        throw new Exception("Entity quotes decoding failed: " . ($parsed[1][2] ?? ''));
    }
    return "All XML entities preserved accurately";
});

// 3. Date normalizer boundary tests
runStress("normalize_excel_date edge cases", function() {
    $d1 = normalize_excel_date('1/5/2015');
    if ($d1 !== '2015-05-01') throw new Exception("1/5/2015 parsed as '$d1'");

    $d2 = normalize_excel_date('30/02/2015');
    if ($d2 !== null) throw new Exception("30/02/2015 should return null, got '$d2'");

    $d3 = normalize_excel_date('99999999');
    if ($d3 !== null) throw new Exception("Invalid serial should return null, got '$d3'");

    $d4 = normalize_excel_date('Chưa có ngày sinh');
    if ($d4 !== null) throw new Exception("Arbitrary text should return null, got '$d4'");

    $d5 = normalize_vn_date('Chưa có');
    if ($d5 !== '1970-01-01') throw new Exception("Fallback should be 1970-01-01, got '$d5'");

    return "All date normalizations passed";
});

// 4. Cross-class same student name & birthday
runStress("Cross-class same student name & birthday is allowed", function() use ($pdo) {
    $pdo->beginTransaction();
    try {
        $c1 = 1;
        $c2 = 2;
        $name = "Học Sinh Trùng Tên Khác Lớp " . uniqid();
        $bday = "2015-05-15";

        $ins = $pdo->prepare("INSERT INTO students (class_id, full_name, birthday) VALUES (?, ?, ?)");
        $ins->execute([$c1, $name, $bday]);
        $ins->execute([$c2, $name, $bday]);

        $cnt = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE full_name = '$name'")->fetchColumn();
        $pdo->rollBack();

        if ($cnt !== 2) throw new Exception("Expected 2 records across different classes, found $cnt");
        return "Allowed across classes as intended";
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
});

// 5. In-file duplicate students import via admin.php
runStress("In-file duplicate students in one XLSX upload are deduplicated by admin.php", function() use ($pdo) {
    $client = new StressClient();
    $loginRes = $client->post('http://localhost/my-web-project/login.php', ['username' => 'admin', 'password' => '123456']);
    
    $targetClass = 5; // 6B2
    $dupName = 'Học Sinh Trùng Nội Bộ File ' . uniqid();
    $data = [
        ['STT', 'Họ và tên', 'Ngày sinh', 'Giới tính', 'Số điện thoại'],
        ['1', $dupName, '15/05/2015', 'Nam', '0912111222'],
        ['2', $dupName, '15/05/2015', 'Nam', '0912111333'] // duplicate of row 1
    ];
    $tmpXlsx = tempnam(sys_get_temp_dir(), 'dup_') . '.xlsx';
    file_put_contents($tmpXlsx, create_xlsx($data));

    $fields = [
        'action' => 'import_students',
        'target_class_id' => $targetClass,
        'student_file' => new CURLFile($tmpXlsx, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'dup.xlsx')
    ];
    $res = $client->post('http://localhost/my-web-project/admin.php', $fields, true);
    @unlink($tmpXlsx);

    $cnt = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE class_id = $targetClass AND full_name = '$dupName'")->fetchColumn();
    // Clean up
    $pdo->prepare("DELETE FROM students WHERE class_id = ? AND full_name = ?")->execute([$targetClass, $dupName]);

    if ($cnt !== 1) {
        throw new Exception("Expected exactly 1 student inserted from file with internal duplicates, got $cnt");
    }
    return "Exactly 1 student inserted, duplicate skipped";
});

// 6. Leading zero preservation test in create_xlsx -> parse_xlsx -> import
runStress("Phone numbers with leading zeroes preserved in XLSX round-trip", function() {
    $phone = '0987654321';
    $data = [
        ['Họ tên', 'SĐT'],
        ['Thử Nghiệm Số 0', $phone]
    ];
    $bin = create_xlsx($data);
    $tmp = tempnam(sys_get_temp_dir(), 'phone_') . '.xlsx';
    file_put_contents($tmp, $bin);
    $parsed = parse_xlsx($tmp);
    @unlink($tmp);

    $parsedPhone = (string)($parsed[1][1] ?? '');
    if ($parsedPhone !== $phone) {
        throw new Exception("Leading zero lost! Expected '$phone', got '$parsedPhone'");
    }
    return "Leading zero preserved: $parsedPhone";
});

// 7. Invalid class creation format rejection
runStress("Admin class creation rejects invalid class name format", function() use ($pdo) {
    $client = new StressClient();
    $client->post('http://localhost/my-web-project/login.php', ['username' => 'admin', 'password' => '123456']);

    $res = $client->post('http://localhost/my-web-project/admin.php', [
        'action' => 'add_class',
        'name' => '10A1_INVALID', // Grade 10 is THPT, not THCS (6-9)
        'grade' => 6,
        'section' => 'A'
    ]);

    $exists = (bool)$pdo->query("SELECT id FROM classes WHERE name = '10A1_INVALID'")->fetch();
    if ($exists) {
        $pdo->prepare("DELETE FROM classes WHERE name = '10A1_INVALID'")->execute();
        throw new Exception("Invalid class name 10A1_INVALID was accepted!");
    }
    return "Invalid class rejected";
});

// 8. SQL Injection in Class Matrix / Batch Assign
runStress("Batch assign parameters parameterized against SQL Injection", function() use ($pdo) {
    $client = new StressClient();
    $client->post('http://localhost/my-web-project/login.php', ['username' => 'admin', 'password' => '123456']);

    $res = $client->post('http://localhost/my-web-project/admin.php', [
        'action' => 'batch_assign',
        'teacher_id' => "1 OR 1=1",
        'subject' => "Toán'; DROP TABLE dummy;--",
        'class_ids' => ["1; DROP TABLE dummy;--"]
    ]);

    // Check teacher_classes table
    $found = $pdo->query("SELECT COUNT(*) FROM teacher_classes WHERE subject LIKE '%DROP TABLE%'")->fetchColumn();
    if ($found > 0) {
        throw new Exception("SQL Injection payload inserted into subject!");
    }
    return "SQLi payloads safely sanitized/parameterized";
});

// 9. Check integrity: verify no mock or bypass in xlsx_helper.php or admin.php
runStress("Integrity verification: check xlsx_helper.php and admin.php for mock artifacts", function() use ($projectRoot) {
    $xlsxCode = php_strip_whitespace($projectRoot . '/includes/xlsx_helper.php');
    $adminCode = php_strip_whitespace($projectRoot . '/admin.php');

    $forbidden = [
        'test_mode',
        'mock',
        'fake',
        'dummy',
        'bypass',
        'eval(',
        'base64_decode'
    ];

    foreach ($forbidden as $word) {
        if (stripos($xlsxCode, $word) !== false) {
            throw new Exception("Suspicious keyword '$word' in xlsx_helper.php executable code");
        }
        if (stripos($adminCode, $word) !== false) {
            throw new Exception("Suspicious keyword '$word' in admin.php executable code");
        }
    }
    return "Zero integrity violation flags found in stripped executable code";
});

echo "\n============================================\n";
echo "Adversarial Stress Test Suite Finished\n";
$fails = count(array_filter($results, fn($r) => $r['status'] === 'FAIL'));
echo "Total: " . count($results) . " | Passed: " . (count($results) - $fails) . " | Failed: $fails\n";
echo "============================================\n";

if ($fails > 0) exit(1);
exit(0);
