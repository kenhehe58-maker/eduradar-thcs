<?php
/**
 * =============================================================================
 * Automated Milestone 3 Verification Suite (EduRadar THCS)
 * Kiểm thử tự động Milestone 3:
 *   - OpenXML (.xlsx) & CSV Helpers (ZipArchive & SimpleXML)
 *   - Class Matrix & Batch Checkbox Assignment
 *   - Teacher Account Creation
 *   - Student Roster Import (.xlsx & .csv) with Database Deduplication
 *   - Audit Logging & Admin Gradebook Inspection
 * 
 * Chạy lệnh kiểm thử:
 *   D:\xam\php\php.exe tests/verify_m3.php
 * =============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';

// ANSI Color formatting
const CLR_RESET  = "\033[0m";
const CLR_GREEN  = "\033[1;32m";
const CLR_RED    = "\033[1;31m";
const CLR_YELLOW = "\033[1;33m";
const CLR_CYAN   = "\033[1;36m";

$passedCount = 0;
$failedCount = 0;

function assertTest(string $name, callable $testFn): void
{
    global $passedCount, $failedCount;
    echo CLR_CYAN . "[TEST] " . CLR_RESET . str_pad($name, 64, ".");
    try {
        $result = $testFn();
        if ($result === false) {
            throw new Exception("Assertion returned false");
        }
        $passedCount++;
        echo CLR_GREEN . " [PASS]" . CLR_RESET . PHP_EOL;
    } catch (Throwable $e) {
        $failedCount++;
        echo CLR_RED . " [FAIL]" . CLR_RESET . PHP_EOL;
        echo CLR_RED . "       -> Error: " . $e->getMessage() . CLR_RESET . PHP_EOL;
    }
}

/**
 * HTTP Client chuyên dụng hỗ trợ Session Cookies và Multipart File Upload qua cURL
 */
class TestClient
{
    private string $baseUrl;
    private string $cookieFile;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->cookieFile = sys_get_temp_dir() . '/edu_m3_cookie_' . bin2hex(random_bytes(6)) . '.txt';
    }

    public function __destruct()
    {
        if (file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    public function clearCookies(): void
    {
        if (file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    public function request(string $method, string $path, $data = [], bool $followRedirects = false): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        $ch = curl_init();

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_FOLLOWLOCATION => $followRedirects,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 15,
        ];

        if (strtoupper($method) === 'POST') {
            $options[CURLOPT_POST] = true;
            $hasCurlFile = false;
            if (is_array($data)) {
                foreach ($data as $val) {
                    if ($val instanceof CURLFile) {
                        $hasCurlFile = true;
                        break;
                    }
                }
            }
            if ($hasCurlFile) {
                $options[CURLOPT_POSTFIELDS] = $data;
            } else {
                $options[CURLOPT_POSTFIELDS] = is_array($data) ? http_build_query($data) : $data;
            }
        }

        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new Exception("cURL request to '$url' failed: $err");
        }

        $info = curl_getinfo($ch);
        $headerSize = $info['header_size'];
        $headers = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);
        curl_close($ch);

        return [
            'status'       => (int)$info['http_code'],
            'redirect_url' => (string)($info['redirect_url'] ?? ''),
            'headers'      => $headers,
            'body'         => $body,
        ];
    }

    public function get(string $path, bool $followRedirects = false): array
    {
        return $this->request('GET', $path, [], $followRedirects);
    }

    public function post(string $path, array $data = [], bool $followRedirects = false): array
    {
        return $this->request('POST', $path, $data, $followRedirects);
    }

    public function postMultipart(string $path, array $postFields, bool $followRedirects = false): array
    {
        return $this->request('POST', $path, $postFields, $followRedirects);
    }

    public function login(string $username, string $password): array
    {
        return $this->post('login.php', [
            'username' => $username,
            'password' => $password
        ], false);
    }
}

$client = new TestClient('http://localhost/my-web-project');

echo PHP_EOL . CLR_YELLOW . "============================================================" . CLR_RESET . PHP_EOL;
echo CLR_YELLOW . "  EduRadar Milestone 3: Automated Verification Suite" . CLR_RESET . PHP_EOL;
echo CLR_YELLOW . "============================================================" . CLR_RESET . PHP_EOL . PHP_EOL;

// -------------------------------------------------------------
// NHÓM 1: OPENXML / CSV HELPER & SAMPLE TEMPLATE VERIFICATION
// -------------------------------------------------------------
assertTest("1.1 includes/xlsx_helper.php exists with required functions", function () {
    $helperPath = dirname(__DIR__) . '/includes/xlsx_helper.php';
    if (!file_exists($helperPath)) {
        throw new Exception("File includes/xlsx_helper.php does not exist");
    }
    require_once $helperPath;
    $requiredFns = ['parse_xlsx', 'create_xlsx', 'parse_csv', 'normalize_excel_date', 'normalize_vn_date'];
    foreach ($requiredFns as $fn) {
        if (!function_exists($fn)) {
            throw new Exception("Function $fn() is not defined in includes/xlsx_helper.php");
        }
    }
    return true;
});

assertTest("1.2 create_xlsx() builds valid OpenXML ZIP package", function () {
    require_once dirname(__DIR__) . '/includes/xlsx_helper.php';
    $data = [
        ['STT', 'Họ và tên', 'Ngày sinh'],
        [1, 'Nguyễn Văn Test', '2015-01-01']
    ];
    $binary = create_xlsx($data);
    if (empty($binary)) {
        throw new Exception("create_xlsx() returned empty string");
    }
    $tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.xlsx';
    file_put_contents($tempFile, $binary);

    $zip = new ZipArchive();
    $res = $zip->open($tempFile);
    if ($res !== true) {
        @unlink($tempFile);
        throw new Exception("create_xlsx() output is not a valid ZIP file");
    }
    $hasWorkbook = $zip->locateName('xl/workbook.xml') !== false;
    $hasSheet = $zip->locateName('xl/worksheets/sheet1.xml') !== false;
    $zip->close();
    @unlink($tempFile);

    if (!$hasWorkbook || !$hasSheet) {
        throw new Exception("Generated .xlsx missing xl/workbook.xml or sheet1.xml");
    }
    return true;
});

assertTest("1.3 Round-trip parse_xlsx(create_xlsx()) preserves Vietnamese text", function () {
    require_once dirname(__DIR__) . '/includes/xlsx_helper.php';
    $sampleData = [
        ['STT', 'Họ và tên', 'Ngày sinh', 'Giới tính', 'Số điện thoại'],
        ['1', 'Trần Phương Linh', '23/08/2015', 'Nữ', '0912345602'],
        ['2', 'Phạm Quốc Huy', '02/11/2015', 'Nam', '0934567890']
    ];
    $binary = create_xlsx($sampleData);
    $tempFile = sys_get_temp_dir() . '/test_roundtrip_' . uniqid() . '.xlsx';
    file_put_contents($tempFile, $binary);

    $parsed = parse_xlsx($tempFile);
    @unlink($tempFile);

    if (count($parsed) < 3) {
        throw new Exception("Expected at least 3 rows parsed, got " . count($parsed));
    }
    if ($parsed[1][1] !== 'Trần Phương Linh') {
        throw new Exception("Vietnamese name mismatch: expected 'Trần Phương Linh', got '" . ($parsed[1][1] ?? '') . "'");
    }
    return true;
});

assertTest("1.4 parse_csv() strips UTF-8 BOM and parses delimiters", function () {
    require_once dirname(__DIR__) . '/includes/xlsx_helper.php';
    $csvContent = "\xEF\xBB\xBF" . "\"Họ và tên\",\"Ngày sinh\"\n\"Vũ Đức Đam\",\"2015-01-19\"";
    $tempCsv = sys_get_temp_dir() . '/test_bom_' . uniqid() . '.csv';
    file_put_contents($tempCsv, $csvContent);

    $parsed = parse_csv($tempCsv);
    @unlink($tempCsv);

    if (empty($parsed) || count($parsed) < 2) {
        throw new Exception("parse_csv failed on BOM CSV");
    }
    if ($parsed[0][0] !== 'Họ và tên') {
        throw new Exception("BOM was not stripped from first header: '" . $parsed[0][0] . "'");
    }
    return true;
});

assertTest("1.5 sample_students.xlsx & sample_students.csv exist and HTTP 200", function () use ($client) {
    $resXlsx = $client->get('sample_students.xlsx');
    if ($resXlsx['status'] !== 200 && !file_exists(dirname(__DIR__) . '/sample_students.xlsx')) {
        throw new Exception("sample_students.xlsx missing or HTTP " . $resXlsx['status']);
    }
    $resCsv = $client->get('sample_students.csv');
    if ($resCsv['status'] !== 200) {
        throw new Exception("sample_students.csv HTTP " . $resCsv['status']);
    }
    return true;
});

// -------------------------------------------------------------
// NHÓM 2: CLASS MANAGEMENT & SECONDARY SCHOOL CLASS MATRIX
// -------------------------------------------------------------
assertTest("2.1 Class matrix displays 36 classes grouped by Grade 6-9", function () {
    $pdo = db();
    $count = (int)$pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
    if ($count < 36) {
        throw new Exception("Expected at least 36 classes in database, found $count");
    }
    $grades = $pdo->query("SELECT DISTINCT grade FROM classes ORDER BY grade")->fetchAll(PDO::FETCH_COLUMN);
    $intGrades = array_map('intval', $grades);
    if ($intGrades !== [6, 7, 8, 9]) {
        throw new Exception("Grades 6-9 missing: " . json_encode($grades));
    }
    return true;
});

assertTest("2.2 Admin creates a new class via POST action=add_class", function () use ($client) {
    $client->clearCookies();
    $client->login('admin', '123456');

    $pdo = db();
    $testClassName = '6C99_TEST';
    $pdo->prepare("DELETE FROM classes WHERE name = ?")->execute([$testClassName]);

    $res = $client->post('admin.php', [
        'action'  => 'add_class',
        'name'    => $testClassName,
        'grade'   => 6,
        'section' => 'C'
    ]);

    $found = (bool)$pdo->query("SELECT id FROM classes WHERE name = '$testClassName'")->fetch();
    if (!$found) {
        throw new Exception("Class $testClassName was not created in database");
    }
    return true;
});

assertTest("2.3 Duplicate class name creation is rejected by UNIQUE(name)", function () use ($client) {
    $pdo = db();
    $testClassName = '6C99_TEST';
    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM classes WHERE name = '$testClassName'")->fetchColumn();

    $client->clearCookies();
    $client->login('admin', '123456');
    $client->post('admin.php', [
        'action'  => 'add_class',
        'name'    => $testClassName,
        'grade'   => 6,
        'section' => 'C'
    ]);

    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM classes WHERE name = '$testClassName'")->fetchColumn();
    if ($afterCount > $beforeCount) {
        throw new Exception("Duplicate class name was allowed in classes table!");
    }
    return true;
});

assertTest("2.4 Admin deletes test class and action is logged to audit_logs", function () use ($client) {
    $client->clearCookies();
    $client->login('admin', '123456');
    $pdo = db();
    $testClassName = '6C99_TEST';

    $classId = (int)$pdo->query("SELECT id FROM classes WHERE name = '$testClassName'")->fetchColumn();
    if ($classId) {
        $client->get("admin.php?delete_class=$classId");
        $remaining = (bool)$pdo->query("SELECT id FROM classes WHERE name = '$testClassName'")->fetch();
        if ($remaining) {
            throw new Exception("Class $testClassName was not deleted");
        }
        $log = $pdo->query("SELECT * FROM audit_logs WHERE action LIKE '%Xóa lớp%' OR details LIKE '%$testClassName%' ORDER BY id DESC LIMIT 1")->fetch();
        if (!$log) {
            throw new Exception("Class deletion was not recorded in audit_logs");
        }
    }
    return true;
});

// -------------------------------------------------------------
// NHÓM 3: TEACHER CREATION & BATCH CHECKBOX ASSIGNMENT
// -------------------------------------------------------------
assertTest("3.1 Admin creates teacher account with bcrypt hash", function () use ($client) {
    $client->clearCookies();
    $client->login('admin', '123456');
    $pdo = db();

    $pdo->prepare("DELETE FROM users WHERE username = 'gv_test_m3'")->execute();
    $res = $client->post('admin.php', [
        'action'    => 'create_teacher',
        'username'  => 'gv_test_m3',
        'full_name' => 'Thầy Giáo Test M3',
        'password'  => '123456'
    ]);

    $user = $pdo->query("SELECT * FROM users WHERE username = 'gv_test_m3'")->fetch();
    if (!$user) {
        throw new Exception("Teacher account gv_test_m3 was not created in users table");
    }
    if (!password_verify('123456', $user['password'])) {
        throw new Exception("Password hash was not generated with bcrypt password_hash()");
    }
    return true;
});

assertTest("3.2 Duplicate teacher username is rejected by UNIQUE(username)", function () use ($client) {
    $client->clearCookies();
    $client->login('admin', '123456');
    $pdo = db();

    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE username = 'gv_test_m3'")->fetchColumn();
    $client->post('admin.php', [
        'action'    => 'create_teacher',
        'username'  => 'gv_test_m3',
        'full_name' => 'Thầy Giáo Test Trùng',
        'password'  => '123456'
    ]);
    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE username = 'gv_test_m3'")->fetchColumn();
    if ($afterCount > $beforeCount) {
        throw new Exception("Duplicate username was allowed in users table!");
    }
    return true;
});

assertTest("3.3 Batch assignment assigns multiple classes across grades", function () use ($client) {
    $client->clearCookies();
    $client->login('admin', '123456');
    $pdo = db();

    $teacherId = (int)$pdo->query("SELECT id FROM users WHERE username = 'gv_test_m3'")->fetchColumn();
    $res = $client->post('admin.php', [
        'action'     => 'batch_assign',
        'teacher_id' => $teacherId,
        'subject'    => 'Tin Học',
        'class_ids'  => [1, 2, 3] // 6A1, 6A2, 6A3
    ]);

    $assigned = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes WHERE teacher_id = $teacherId AND subject = 'Tin Học'")->fetchColumn();
    if ($assigned < 3) {
        throw new Exception("Expected 3 classes assigned, found $assigned");
    }
    return true;
});

assertTest("3.4 Re-submitting identical batch assignment produces 0 duplicates", function () use ($client) {
    $client->clearCookies();
    $client->login('admin', '123456');
    $pdo = db();

    $teacherId = (int)$pdo->query("SELECT id FROM users WHERE username = 'gv_test_m3'")->fetchColumn();
    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes WHERE teacher_id = $teacherId AND subject = 'Tin Học'")->fetchColumn();

    $client->post('admin.php', [
        'action'     => 'batch_assign',
        'teacher_id' => $teacherId,
        'subject'    => 'Tin Học',
        'class_ids'  => [1, 2, 3]
    ]);

    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes WHERE teacher_id = $teacherId AND subject = 'Tin Học'")->fetchColumn();
    if ($beforeCount !== $afterCount) {
        throw new Exception("Duplicate assignments were created: before=$beforeCount, after=$afterCount");
    }
    return true;
});

assertTest("3.5 Admin unassigns teaching assignment via delete_assign", function () use ($client) {
    $client->clearCookies();
    $client->login('admin', '123456');
    $pdo = db();

    $teacherId = (int)$pdo->query("SELECT id FROM users WHERE username = 'gv_test_m3'")->fetchColumn();
    $assignId = (int)$pdo->query("SELECT id FROM teacher_classes WHERE teacher_id = $teacherId LIMIT 1")->fetchColumn();

    if ($assignId) {
        $client->get("admin.php?delete_assign=$assignId");
        $check = (bool)$pdo->query("SELECT id FROM teacher_classes WHERE id = $assignId")->fetch();
        if ($check) {
            throw new Exception("Assignment ID $assignId was not deleted");
        }
    }
    return true;
});

// -------------------------------------------------------------
// NHÓM 4: STUDENT ROSTER IMPORT (.CSV & .XLSX) & DEDUPLICATION
// -------------------------------------------------------------
assertTest("4.1 Admin imports sample_students.csv into test class (6A2)", function () use ($client) {
    $client->clearCookies();
    $client->login('admin', '123456');
    $pdo = db();

    $targetClassId = 2; // 6A2
    $pdo->prepare("DELETE FROM students WHERE class_id = ?")->execute([$targetClassId]);

    $csvPath = dirname(__DIR__) . '/sample_students.csv';
    $postData = [
        'action'          => 'import_students',
        'target_class_id' => $targetClassId,
        'student_file'    => new CURLFile($csvPath, 'text/csv', 'sample_students.csv')
    ];

    $res = $client->postMultipart('admin.php', $postData);
    if ($res['status'] !== 200 && $res['status'] !== 302) {
        throw new Exception("Expected HTTP 200/302, got " . $res['status']);
    }

    $count = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE class_id = $targetClassId")->fetchColumn();
    if ($count !== 5) {
        throw new Exception("Expected 5 students inserted into 6A2, found $count");
    }
    return true;
});

assertTest("4.2 CSV Re-import: 0 new students, duplicate count reported", function () use ($client) {
    $client->clearCookies();
    $client->login('admin', '123456');
    $pdo = db();

    $targetClassId = 2;
    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE class_id = $targetClassId")->fetchColumn();

    $csvPath = dirname(__DIR__) . '/sample_students.csv';
    $postData = [
        'action'          => 'import_students',
        'target_class_id' => $targetClassId,
        'student_file'    => new CURLFile($csvPath, 'text/csv', 'sample_students.csv')
    ];

    $res = $client->postMultipart('admin.php', $postData);
    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE class_id = $targetClassId")->fetchColumn();

    if ($beforeCount !== $afterCount) {
        throw new Exception("Duplicate students were inserted! Before: $beforeCount, After: $afterCount");
    }
    // Verify user feedback message contains duplicate count
    if (strpos($res['body'], 'bỏ qua') === false && strpos($res['body'], 'đã tồn tại') === false && strpos($res['body'], 'đã có sẵn') === false) {
        throw new Exception("UI message did not report skipped duplicates");
    }
    return true;
});

assertTest("4.3 Admin imports .xlsx file with Vietnamese dates into test class (6A3)", function () use ($client) {
    require_once dirname(__DIR__) . '/includes/xlsx_helper.php';
    $client->clearCookies();
    $client->login('admin', '123456');
    $pdo = db();

    $targetClassId = 3; // 6A3
    $pdo->prepare("DELETE FROM students WHERE class_id = ?")->execute([$targetClassId]);

    // Create a temporary .xlsx file with standard VnEdu columns
    $xlsxData = [
        ['STT', 'Mã HS', 'Họ và tên', 'Ngày sinh', 'Giới tính', 'Số điện thoại'],
        ['1', 'HS6A301', 'Đỗ Minh Trí', '15/05/2015', 'Nam', '0981122334'],
        ['2', 'HS6A302', 'Bùi Phương Thảo', '20/10/2015', 'Nữ', '0982233445'],
        ['3', 'HS6A303', 'Nguyễn Hoàng Nam', '42075', 'Nam', '0983344556'] // 42075 is 2015-03-12
    ];
    $tempXlsx = sys_get_temp_dir() . '/test_import_' . uniqid() . '.xlsx';
    file_put_contents($tempXlsx, create_xlsx($xlsxData));

    $postData = [
        'action'          => 'import_students',
        'target_class_id' => $targetClassId,
        'student_file'    => new CURLFile($tempXlsx, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'students_6a3.xlsx')
    ];

    $res = $client->postMultipart('admin.php', $postData);
    @unlink($tempXlsx);

    $count = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE class_id = $targetClassId")->fetchColumn();
    if ($count !== 3) {
        throw new Exception("Expected 3 students inserted into 6A3, found $count");
    }

    // Verify date normalization: '15/05/2015' -> '2015-05-15', '42075' -> '2015-03-12'
    $s1 = $pdo->query("SELECT birthday FROM students WHERE class_id = $targetClassId AND full_name = 'Đỗ Minh Trí'")->fetchColumn();
    if ($s1 !== '2015-05-15') {
        throw new Exception("Birthday '15/05/2015' was not normalized to '2015-05-15', got '$s1'");
    }
    $s3 = $pdo->query("SELECT birthday FROM students WHERE class_id = $targetClassId AND full_name = 'Nguyễn Hoàng Nam'")->fetchColumn();
    if ($s3 !== '2015-03-12') {
        throw new Exception("Excel serial '42075' was not normalized to '2015-03-12', got '$s3'");
    }
    return true;
});

assertTest("4.4 XLSX Re-import: 0 duplicate rows inserted into database", function () use ($client) {
    require_once dirname(__DIR__) . '/includes/xlsx_helper.php';
    $client->clearCookies();
    $client->login('admin', '123456');
    $pdo = db();

    $targetClassId = 3;
    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE class_id = $targetClassId")->fetchColumn();

    $xlsxData = [
        ['STT', 'Mã HS', 'Họ và tên', 'Ngày sinh', 'Giới tính', 'Số điện thoại'],
        ['1', 'HS6A301', 'Đỗ Minh Trí', '15/05/2015', 'Nam', '0981122334'],
        ['2', 'HS6A302', 'Bùi Phương Thảo', '20/10/2015', 'Nữ', '0982233445'],
        ['3', 'HS6A303', 'Nguyễn Hoàng Nam', '2015-03-12', 'Nam', '0983344556']
    ];
    $tempXlsx = sys_get_temp_dir() . '/test_reimport_' . uniqid() . '.xlsx';
    file_put_contents($tempXlsx, create_xlsx($xlsxData));

    $postData = [
        'action'          => 'import_students',
        'target_class_id' => $targetClassId,
        'student_file'    => new CURLFile($tempXlsx, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'students_6a3.xlsx')
    ];

    $res = $client->postMultipart('admin.php', $postData);
    @unlink($tempXlsx);

    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE class_id = $targetClassId")->fetchColumn();
    if ($beforeCount !== $afterCount) {
        throw new Exception("Re-importing .xlsx created duplicates: before=$beforeCount, after=$afterCount");
    }
    return true;
});

assertTest("4.5 Header tolerance: file with headers on row 3 parses correctly", function () use ($client) {
    require_once dirname(__DIR__) . '/includes/xlsx_helper.php';
    $client->clearCookies();
    $client->login('admin', '123456');
    $pdo = db();

    $targetClassId = 4; // 6B1
    $pdo->prepare("DELETE FROM students WHERE class_id = ?")->execute([$targetClassId]);

    $tolerantData = [
        ['TRƯỜNG THCS NGUYỄN DU - PHÒNG GD&ĐT QUẬN 1'],
        ['DANH SÁCH HỌC SINH LỚP 6B1 - NĂM HỌC 2026-2027'],
        ['STT', 'Họ và tên', 'Ngày sinh', 'Giới tính', 'SĐT phụ huynh'],
        ['1', 'Võ Quốc Bảo', '10/02/2015', 'Nam', '0909887766'],
        ['2', 'Đặng Thùy Dương', '18/09/2015', 'Nữ', '0909776655']
    ];
    $tempXlsx = sys_get_temp_dir() . '/test_tolerant_' . uniqid() . '.xlsx';
    file_put_contents($tempXlsx, create_xlsx($tolerantData));

    $postData = [
        'action'          => 'import_students',
        'target_class_id' => $targetClassId,
        'student_file'    => new CURLFile($tempXlsx, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'students_6b1.xlsx')
    ];

    $client->postMultipart('admin.php', $postData);
    @unlink($tempXlsx);

    $count = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE class_id = $targetClassId")->fetchColumn();
    if ($count !== 2) {
        throw new Exception("Header tolerance failed: expected 2 students in 6B1, got $count");
    }
    return true;
});

assertTest("4.6 Database UNIQUE(class_id, full_name, birthday) rejects duplicate", function () {
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->exec("INSERT INTO students (class_id, full_name, birthday) VALUES (1, 'Học Sinh Kiểm Thử Khóa', '2015-01-01')");
    $duplicateCaught = false;
    try {
        $pdo->exec("INSERT INTO students (class_id, full_name, birthday) VALUES (1, 'Học Sinh Kiểm Thử Khóa', '2015-01-01')");
    } catch (PDOException $e) {
        if ($e->errorInfo[1] == 1062) {
            $duplicateCaught = true;
        }
    }
    $pdo->rollBack();
    if (!$duplicateCaught) {
        throw new Exception("Database constraint UNIQUE(class_id, full_name, birthday) failed to catch duplicate entry!");
    }
    return true;
});

// -------------------------------------------------------------
// NHÓM 5: DIRECT GRADEBOOK INSPECTION & RBAC SECURITY
// -------------------------------------------------------------
assertTest("5.1 Admin direct gradebook inspection (nhap_diem.php?class_id=1&subject=Toán) is 200", function () use ($client) {
    $client->clearCookies();
    $client->login('admin', '123456');
    $res = $client->get('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'));
    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200 for admin gradebook inspection, got " . $res['status']);
    }
    return true;
});

assertTest("5.2 Teacher (gv_toan) POST to admin.php is blocked with 403", function () use ($client) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');

    $res = $client->post('admin.php', [
        'action'          => 'import_students',
        'target_class_id' => 1
    ]);
    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403 on forged teacher POST to admin.php, got " . $res['status']);
    }
    return true;
});

assertTest("5.3 Unauthenticated request to admin.php is blocked (HTTP 403/302)", function () use ($client) {
    $client->clearCookies();
    $res = $client->get('admin.php');
    if ($res['status'] !== 403 && $res['status'] !== 302) {
        throw new Exception("Expected HTTP 403/302 for unauthenticated access, got " . $res['status']);
    }
    return true;
});

// -------------------------------------------------------------
// NHÓM 6: COMPLETE AUDIT LOGGING FOR ALL M3 OPERATIONS
// -------------------------------------------------------------
assertTest("6.1 Student import is recorded in audit_logs with class name & counts", function () {
    $pdo = db();
    $log = $pdo->query("SELECT * FROM audit_logs WHERE action = 'Import học sinh' ORDER BY id DESC LIMIT 1")->fetch();
    if (!$log) {
        throw new Exception("No audit log found for 'Import học sinh'");
    }
    if (strpos($log['details'], 'thêm mới') === false || strpos($log['details'], 'trùng lặp') === false) {
        throw new Exception("Audit log details missing metrics: " . $log['details']);
    }
    return true;
});

assertTest("6.2 Batch assignment is recorded in audit_logs with teacher & subject", function () {
    $pdo = db();
    $log = $pdo->query("SELECT * FROM audit_logs WHERE action = 'Phân công giáo viên' ORDER BY id DESC LIMIT 1")->fetch();
    if (!$log) {
        throw new Exception("No audit log found for 'Phân công giáo viên'");
    }
    return true;
});

assertTest("6.3 Teacher account creation is recorded in audit_logs", function () {
    $pdo = db();
    $log = $pdo->query("SELECT * FROM audit_logs WHERE action LIKE '%giáo viên%' OR details LIKE '%gv_test_m3%' ORDER BY id DESC LIMIT 1")->fetch();
    if (!$log) {
        throw new Exception("Teacher creation was not found in audit_logs");
    }
    return true;
});

assertTest("6.4 Class management events are recorded in audit_logs", function () {
    $pdo = db();
    $log = $pdo->query("SELECT * FROM audit_logs WHERE action LIKE '%lớp%' ORDER BY id DESC LIMIT 1")->fetch();
    if (!$log) {
        throw new Exception("Class management action was not recorded in audit_logs");
    }
    return true;
});

assertTest("6.5 admin.php audit viewer table displays log records", function () use ($client) {
    $client->clearCookies();
    $client->login('admin', '123456');
    $res = $client->get('admin.php');
    if (strpos($res['body'], 'audit_logs') === false && strpos($res['body'], 'Nhật Ký') === false) {
        throw new Exception("admin.php does not contain Audit Logs section");
    }
    if (strpos($res['body'], 'Import học sinh') === false && strpos($res['body'], 'Phân công') === false) {
        throw new Exception("Audit log actions not rendered in admin.php table");
    }
    return true;
});

// -------------------------------------------------------------
// CLEANUP TEST ARTIFACTS
// -------------------------------------------------------------
try {
    $pdo = db();
    $pdo->prepare("DELETE FROM users WHERE username = 'gv_test_m3'")->execute();
    $pdo->prepare("DELETE FROM classes WHERE name = '6C99_TEST'")->execute();
    $pdo->prepare("DELETE FROM students WHERE class_id IN (2, 3, 4) AND full_name IN ('Đỗ Minh Trí', 'Bùi Phương Thảo', 'Nguyễn Hoàng Nam', 'Võ Quốc Bảo', 'Đặng Thùy Dương')")->execute();
} catch (Throwable $e) {
    // Ignore cleanup errors
}

// -------------------------------------------------------------
// TỔNG KẾT & MÃ THOÁT
// -------------------------------------------------------------
echo PHP_EOL . CLR_YELLOW . "============================================================" . CLR_RESET . PHP_EOL;
echo sprintf("  Total Tests: %d | Passed: %s%d%s | Failed: %s%d%s",
    $passedCount + $failedCount,
    CLR_GREEN, $passedCount, CLR_RESET,
    ($failedCount > 0 ? CLR_RED : CLR_GREEN), $failedCount, CLR_RESET
) . PHP_EOL;
echo CLR_YELLOW . "============================================================" . CLR_RESET . PHP_EOL . PHP_EOL;

if ($failedCount > 0) {
    exit(1);
}
exit(0);
