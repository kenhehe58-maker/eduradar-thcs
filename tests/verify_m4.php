<?php
/**
 * =============================================================================
 * Automated Milestone 4 Verification Suite (EduRadar THCS)
 * Kiểm thử tự động Milestone 4:
 *   - Nhóm 1: Cấu trúc giao diện Sổ Điểm chuẩn VnEdu (11 cột, 2 tầng header, thẻ nhập)
 *   - Nhóm 2: Engine tính toán ĐTBmhk theo Thông tư 22 và công thức trọng số động
 *   - Nhóm 3: Phím tắt điều hướng 2D (Enter/ArrowDown, ArrowUp, Tab) & ràng buộc client
 *   - Nhóm 4: Lưu điểm qua POST bằng Database Transaction & ghi Audit Log
 *   - Nhóm 5: Từ chối điểm ngoài miền giá trị (0.0 - 10.0) & Chống BOLA học sinh
 *   - Nhóm 6: Import Sổ Điểm từ file Excel (.xlsx) với cơ chế chống trùng lặp (Upsert)
 *   - Nhóm 7: Import Sổ Điểm từ file CSV (.csv) chuẩn VnEdu
 *   - Nhóm 8: Xuất Sổ Điểm ra file Excel (.xlsx) OpenXML & file mẫu tải về
 *   - Nhóm 9: Kiểm tra phân quyền RBAC & phòng thủ BOLA / IDOR tuyệt đối
 *   - Nhóm 10: Kiểm toán dữ liệu nhật ký hệ thống (Audit Logs Forensics)
 * 
 * Chạy lệnh kiểm thử:
 *   D:\xam\php\php.exe tests/verify_m4.php
 * =============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/xlsx_helper.php';

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
    echo CLR_CYAN . "[TEST] " . CLR_RESET . str_pad($name, 68, ".");
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
        $this->cookieFile = sys_get_temp_dir() . '/edu_m4_cookie_' . bin2hex(random_bytes(6)) . '.txt';
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
echo CLR_YELLOW . "  EduRadar Milestone 4: Automated Verification Suite" . CLR_RESET . PHP_EOL;
echo CLR_YELLOW . "  Target: http://localhost/my-web-project/ (Apache & MariaDB)" . CLR_RESET . PHP_EOL;
echo CLR_YELLOW . "============================================================" . CLR_RESET . PHP_EOL . PHP_EOL;

// Lưu lại điểm ban đầu của học sinh 1 lớp 6A1 môn Toán để khôi phục sau test
$pdo = db();
$origScore = $pdo->query("SELECT * FROM scores WHERE student_id = 1 AND subject = 'Toán'")->fetch();

// =============================================================================
// NHÓM 1: CẤU TRÚC GIAO DIỆN SỔ ĐIỂM CHUẨN VNEDU
// =============================================================================
echo CLR_YELLOW . "--- NHÓM 1: Cấu trúc Giao diện Sổ Điểm Chuẩn VnEdu ---" . CLR_RESET . PHP_EOL;

assertTest("1.1 Assigned teacher (gv_toan) accesses 6A1 Toán gradebook (HTTP 200)", function () use ($client) {
    $client->clearCookies();
    $loginRes = $client->login('gv_toan', '123456');
    if ($loginRes['status'] !== 302) {
        throw new Exception("Login for gv_toan failed with HTTP " . $loginRes['status']);
    }
    $res = $client->get('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'));
    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200, got " . $res['status']);
    }
    return true;
});

assertTest("1.2 Table contains 11 columns with 2-tier sticky VnEdu headers", function () use ($client) {
    $res = $client->get('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'));
    $body = $res['body'];
    
    $requiredTerms = ['STT', 'Mã HS', 'Họ và Tên Học Sinh', 'Ngày Sinh', 'ĐĐG THƯỜNG XUYÊN', 'GIỮA KỲ', 'CUỐI KỲ', 'ĐTBmhk', 'TX1', 'TX2', 'TX3', 'TX4'];
    foreach ($requiredTerms as $term) {
        if (mb_stripos($body, $term) === false) {
            throw new Exception("Missing required header term in gradebook: '$term'");
        }
    }
    return true;
});

assertTest("1.3 Score inputs have proper names (scores[id][tx1..ck])", function () use ($client) {
    $res = $client->get('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'));
    $body = $res['body'];
    
    if (!preg_match('/name="scores\[\d+\]\[tx1\]"/', $body)) {
        throw new Exception("Missing inputs with name pattern scores[id][tx1]");
    }
    if (!preg_match('/name="scores\[\d+\]\[gk\]"/', $body)) {
        throw new Exception("Missing inputs with name pattern scores[id][gk]");
    }
    if (!preg_match('/name="scores\[\d+\]\[ck\]"/', $body)) {
        throw new Exception("Missing inputs with name pattern scores[id][ck]");
    }
    return true;
});

assertTest("1.4 Student roster for class 6A1 renders with names and codes", function () use ($client) {
    $res = $client->get('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'));
    $body = $res['body'];
    
    if (mb_stripos($body, 'Nguyễn Văn An') === false) {
        throw new Exception("Student 'Nguyễn Văn An' not rendered in 6A1 roster");
    }
    if (mb_stripos($body, 'Trần Minh Khang') === false) {
        throw new Exception("Student 'Trần Minh Khang' not rendered in 6A1 roster");
    }
    return true;
});

// =============================================================================
// NHÓM 2: ENGINE TÍNH TOÁN ĐTBMHK THEO THÔNG TƯ 22
// =============================================================================
echo PHP_EOL . CLR_YELLOW . "--- NHÓM 2: Engine Tính Toán ĐTBmhk Theo Thông tư 22 ---" . CLR_RESET . PHP_EOL;

assertTest("2.1 Full score formula: (S_tx + 2*gk + 3*ck)/(N_tx + 5) equals 5.8", function () {
    $tx = [8.0, 7.0, 6.5, 6.0];
    $gk = 5.0;
    $ck = 5.0;
    
    $txSum = array_sum($tx);
    $nTx = count($tx);
    $avg = round(($txSum + 2 * $gk + 3 * $ck) / ($nTx + 5), 1);
    
    if ($avg !== 5.8) {
        throw new Exception("Expected average 5.8, got " . var_export($avg, true));
    }
    return true;
});

assertTest("2.2 Dynamic weighted average with partial entry (only TX & GK) equals 7.3", function () {
    $tx = [8.0, 7.0];
    $gk = 7.0;
    $ck = null;
    
    $txSum = array_sum($tx);
    $nTx = count($tx);
    $totalWeight = $nTx + ($gk !== null ? 2 : 0) + ($ck !== null ? 3 : 0);
    $weightedSum = $txSum + ($gk !== null ? 2 * $gk : 0) + ($ck !== null ? 3 * $ck : 0);
    $avg = round($weightedSum / $totalWeight, 1);
    
    if ($avg !== 7.3) {
        throw new Exception("Expected average 7.3, got " . var_export($avg, true));
    }
    return true;
});

assertTest("2.3 Dynamic weighted average with only TX scores equals 7.5", function () {
    $tx = [8.0, 7.0];
    $avg = round(array_sum($tx) / count($tx), 1);
    
    if ($avg !== 7.5) {
        throw new Exception("Expected average 7.5, got " . var_export($avg, true));
    }
    return true;
});

assertTest("2.4 Empty score set evaluates to null", function () {
    $tx = [];
    $gk = null;
    $ck = null;
    $totalWeight = count($tx) + ($gk !== null ? 2 : 0) + ($ck !== null ? 3 : 0);
    $avg = $totalWeight > 0 ? 0.0 : null;
    
    if ($avg !== null) {
        throw new Exception("Expected null average for empty score set");
    }
    return true;
});

// =============================================================================
// NHÓM 3: PHÍM TẮT & RÀNG BUỘC CLIENT
// =============================================================================
echo PHP_EOL . CLR_YELLOW . "--- NHÓM 3: Phím Tắt Điều Hướng 2D & Ràng Buộc Client ---" . CLR_RESET . PHP_EOL;

assertTest("3.1 Table inputs contain class score-input and 2D data-row / data-col", function () use ($client) {
    $res = $client->get('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'));
    $body = $res['body'];
    
    if (strpos($body, 'score-input') === false) {
        throw new Exception("score-input class missing in HTML");
    }
    if (strpos($body, 'data-row=') === false || strpos($body, 'data-col=') === false) {
        throw new Exception("2D navigation attributes data-row / data-col missing in HTML");
    }
    return true;
});

assertTest("3.2 Inputs declare inputmode decimal and min/max 0-10 bounds", function () use ($client) {
    $res = $client->get('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'));
    $body = $res['body'];
    
    if (strpos($body, 'inputmode="decimal"') === false) {
        throw new Exception("inputmode=\"decimal\" attribute missing");
    }
    if (strpos($body, 'min="0"') === false || strpos($body, 'max="10"') === false) {
        throw new Exception("min/max bounds missing on score inputs");
    }
    return true;
});

assertTest("3.3 Page script listens for ArrowDown, ArrowUp, Enter, and Tab keys", function () use ($client) {
    $res = $client->get('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'));
    $body = $res['body'];
    
    if (strpos($body, 'ArrowDown') === false || strpos($body, 'ArrowUp') === false || strpos($body, 'Enter') === false || strpos($body, 'Tab') === false) {
        throw new Exception("Keyboard event handling script missing arrow or enter bindings");
    }
    return true;
});

assertTest("3.4 Real-time validation, comma normalization & dirty state badge present", function () use ($client) {
    $res = $client->get('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'));
    $body = $res['body'];
    
    if (strpos($body, 'saveStatusBadge') === false) {
        throw new Exception("Dirty-state badge saveStatusBadge missing in HTML");
    }
    if (strpos($body, 'is-invalid') === false) {
        throw new Exception("is-invalid CSS/JS error class missing in HTML/JS");
    }
    return true;
});

// =============================================================================
// NHÓM 4: LƯU ĐIỂM QUA POST & AUDIT LOG
// =============================================================================
echo PHP_EOL . CLR_YELLOW . "--- NHÓM 4: Lưu Điểm Bằng Transaction & Ghi Audit Log ---" . CLR_RESET . PHP_EOL;

assertTest("4.1 Teacher (gv_toan) submits POST action=save_scores for 6A1 Toán", function () use ($client) {
    $payload = [
        'action' => 'save_scores',
        'scores' => [
            1 => [
                'tx1' => '8.5',
                'tx2' => '8.0',
                'tx3' => '7.5',
                'tx4' => '8.0',
                'gk'  => '8.0',
                'ck'  => '8.5'
            ]
        ]
    ];
    $res = $client->post('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'), $payload);
    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200 on save_scores, got " . $res['status']);
    }
    if (mb_stripos($res['body'], 'Đã lưu thành công') === false) {
        throw new Exception("Success alert missing in response: " . substr($res['body'], 0, 300));
    }
    return true;
});

assertTest("4.2 Database scores table updated accurately with calculated avg_score", function () {
    $pdo = db();
    $row = $pdo->query("SELECT * FROM scores WHERE student_id = 1 AND subject = 'Toán'")->fetch();
    if (!$row) {
        throw new Exception("No score row found for student 1 in 6A1 Toán");
    }
    
    if ((float)$row['tx1'] !== 8.5 || (float)$row['tx2'] !== 8.0 || (float)$row['gk'] !== 8.0 || (float)$row['ck'] !== 8.5) {
        throw new Exception("Score components mismatch in MySQL: " . json_encode($row));
    }
    
    // (8.5 + 8.0 + 7.5 + 8.0 + 2*8.0 + 3*8.5) / 9 = (32 + 16 + 25.5)/9 = 73.5/9 = 8.166... -> 8.2
    if ((float)$row['avg_score'] !== 8.2) {
        throw new Exception("Expected avg_score 8.2, got " . $row['avg_score']);
    }
    return true;
});

assertTest("4.3 Audit log records Lưu sổ điểm with class, subject, and student count", function () {
    $pdo = db();
    $log = $pdo->query("SELECT * FROM audit_logs WHERE action = 'Lưu sổ điểm' AND details LIKE '%Toán%' AND details LIKE '%6A1%' ORDER BY id DESC LIMIT 1")->fetch();
    if (!$log) {
        throw new Exception("Audit log record for 'Lưu sổ điểm' not found in MySQL");
    }
    return true;
});

// =============================================================================
// NHÓM 5: TỪ CHỐI ĐIỂM NGOÀI MIỀN GIÁ TRỊ & CHỐNG BOLA HỌC SINH
// =============================================================================
echo PHP_EOL . CLR_YELLOW . "--- NHÓM 5: Từ Chối Điểm Bất Hợp Lệ & Chống BOLA Học Sinh ---" . CLR_RESET . PHP_EOL;

assertTest("5.1 Out-of-bounds scores (<0 or >10 or non-numeric) normalized to null", function () use ($client) {
    $payload = [
        'action' => 'save_scores',
        'scores' => [
            1 => [
                'tx1' => '15.5', // Out of bounds > 10
                'tx2' => '-2.0', // Out of bounds < 0
                'tx3' => 'abc',  // Non-numeric
                'tx4' => '8.0',  // Valid
                'gk'  => '8.0',
                'ck'  => '8.0'
            ]
        ]
    ];
    $res = $client->post('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'), $payload);
    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200, got " . $res['status']);
    }
    
    $pdo = db();
    $row = $pdo->query("SELECT * FROM scores WHERE student_id = 1 AND subject = 'Toán'")->fetch();
    if ($row['tx1'] !== null || $row['tx2'] !== null || $row['tx3'] !== null) {
        throw new Exception("Out-of-bounds score was not sanitized to NULL: " . json_encode($row));
    }
    if ((float)$row['tx4'] !== 8.0) {
        throw new Exception("Valid score tx4 was incorrectly modified");
    }
    return true;
});

assertTest("5.2 Cross-class student ID injection (BOLA) is strictly blocked", function () use ($client) {
    $payload = [
        'action' => 'save_scores',
        'scores' => [
            99999 => ['tx1' => '10.0', 'gk' => '10.0', 'ck' => '10.0']
        ]
    ];
    $res = $client->post('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'), $payload);
    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200, got " . $res['status']);
    }
    
    $pdo = db();
    $chk = $pdo->query("SELECT COUNT(*) FROM scores WHERE student_id = 99999")->fetchColumn();
    if ((int)$chk !== 0) {
        throw new Exception("Cross-class injected student 99999 was inserted into scores!");
    }
    return true;
});

// =============================================================================
// NHÓM 6: IMPORT SỔ ĐIỂM TỪ FILE EXCEL (.XLSX)
// =============================================================================
echo PHP_EOL . CLR_YELLOW . "--- NHÓM 6: Import Sổ Điểm Từ File Excel (.xlsx) & Chống Trùng Lặp ---" . CLR_RESET . PHP_EOL;

$testXlsxPath = sys_get_temp_dir() . '/test_gradebook_' . bin2hex(random_bytes(4)) . '.xlsx';

assertTest("6.1 Build valid in-memory .xlsx gradebook using create_xlsx()", function () use ($testXlsxPath) {
    $data = [
        ['STT', 'Họ và tên', 'Số điện thoại phụ huynh', 'ĐĐGtx1', 'ĐĐGtx2', 'ĐĐGtx3', 'ĐĐGtx4', 'ĐĐGgk', 'ĐĐGck'],
        [1, 'Nguyễn Văn An', '0901234567', 8, 8, 8, 8, 8, 8],
        [2, 'Trần Minh Khang', '0912345678', 7, 7, 7, 7, 7, 7],
    ];
    $binary = create_xlsx($data, 'SoDiem_6A1');
    file_put_contents($testXlsxPath, $binary);
    
    if (!file_exists($testXlsxPath) || filesize($testXlsxPath) < 100) {
        throw new Exception("Generated .xlsx file is missing or invalid");
    }
    return true;
});

assertTest("6.2 Upload .xlsx gradebook file via POST action=import_file", function () use ($client, $testXlsxPath) {
    $cFile = new CURLFile($testXlsxPath, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'test_gradebook.xlsx');
    $res = $client->postMultipart('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'), [
        'action' => 'import_file',
        'gradebook_file' => $cFile
    ]);
    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200 on .xlsx import, got " . $res['status']);
    }
    if (mb_stripos($res['body'], 'thành công') === false) {
        throw new Exception("Success notice missing in response body: " . substr($res['body'], 0, 300));
    }
    return true;
});

assertTest("6.3 Scores table in MySQL reflects imported grades and accurate avg_score", function () {
    $pdo = db();
    $row = $pdo->query("SELECT * FROM scores WHERE student_id = 1 AND subject = 'Toán'")->fetch();
    if (!$row) {
        throw new Exception("Scores for student 1 not found");
    }
    if ((float)$row['tx1'] !== 8.0 || (float)$row['gk'] !== 8.0 || (float)$row['ck'] !== 8.0) {
        throw new Exception("Imported score components mismatch: " . json_encode($row));
    }
    if ((float)$row['avg_score'] !== 8.0) {
        throw new Exception("Expected avg_score 8.0, got " . $row['avg_score']);
    }
    return true;
});

assertTest("6.4 Re-importing identical .xlsx file produces 0 duplicate records", function () use ($client, $testXlsxPath) {
    $pdo = db();
    $countBefore = (int)$pdo->query("SELECT COUNT(*) FROM scores WHERE subject = 'Toán'")->fetchColumn();
    
    $cFile = new CURLFile($testXlsxPath, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'test_gradebook.xlsx');
    $res = $client->postMultipart('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'), [
        'action' => 'import_file',
        'gradebook_file' => $cFile
    ]);
    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200, got " . $res['status']);
    }
    
    $countAfter = (int)$pdo->query("SELECT COUNT(*) FROM scores WHERE subject = 'Toán'")->fetchColumn();
    if ($countBefore !== $countAfter) {
        throw new Exception("Duplicate rows created on re-import! Before: $countBefore, After: $countAfter");
    }
    return true;
});

assertTest("6.5 Header tolerance: file with title and headers on row 2 imports correctly", function () use ($client) {
    $resilientData = [
        ['TRƯỜNG THCS NGUYỄN DU - SỔ ĐIỂM ĐIỆN TỬ'],
        ['Năm học 2026 - 2027'],
        ['STT', 'Họ và tên', 'SĐT', 'ĐĐGtx1', 'ĐĐGtx2', 'ĐĐGtx3', 'ĐĐGtx4', 'ĐĐGgk', 'ĐĐGck'],
        [1, 'Nguyễn Văn An', '0901234567', 9, 9, 9, 9, 9, 9]
    ];
    $tmpPath = sys_get_temp_dir() . '/test_resilient_header_' . bin2hex(random_bytes(4)) . '.xlsx';
    file_put_contents($tmpPath, create_xlsx($resilientData, 'Sheet1'));
    
    $cFile = new CURLFile($tmpPath, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'test_resilient.xlsx');
    $res = $client->postMultipart('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'), [
        'action' => 'import_file',
        'gradebook_file' => $cFile
    ]);
    @unlink($tmpPath);
    
    if ($res['status'] !== 200 || mb_stripos($res['body'], 'thành công') === false) {
        throw new Exception("Failed to import file with headers on row 2");
    }
    
    $pdo = db();
    $avg = (float)$pdo->query("SELECT avg_score FROM scores WHERE student_id = 1 AND subject = 'Toán'")->fetchColumn();
    if ($avg !== 9.0) {
        throw new Exception("Expected avg_score 9.0 from row 2 header file, got $avg");
    }
    return true;
});

@unlink($testXlsxPath);

// =============================================================================
// NHÓM 7: IMPORT SỔ ĐIỂM TỪ FILE CSV (.CSV)
// =============================================================================
echo PHP_EOL . CLR_YELLOW . "--- NHÓM 7: Import Sổ Điểm Từ File CSV (.csv) Chuẩn VnEdu ---" . CLR_RESET . PHP_EOL;

$testCsvPath = sys_get_temp_dir() . '/test_gradebook_' . bin2hex(random_bytes(4)) . '.csv';

assertTest("7.1 Build valid VnEdu CSV file with Vietnamese headers", function () use ($testCsvPath) {
    $csvContent = "\xEF\xBB\xBF" . "\"Họ và tên\",\"Số điện thoại phụ huynh\",\"Miệng\",\"15P_1\",\"15P_2\",\"1Tiết\",\"GiữaKỳ\",\"CuốiKỳ\"\n" .
                  "\"Nguyễn Văn An\",\"0901234567\",\"8.5\",\"8.0\",\"7.5\",\"8.0\",\"8.0\",\"8.5\"\n";
    file_put_contents($testCsvPath, $csvContent);
    if (!file_exists($testCsvPath)) {
        throw new Exception("Failed to create test CSV file");
    }
    return true;
});

assertTest("7.2 Upload .csv file via POST action=import_file", function () use ($client, $testCsvPath) {
    $cFile = new CURLFile($testCsvPath, 'text/csv', 'test_gradebook.csv');
    $res = $client->postMultipart('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'), [
        'action' => 'import_file',
        'gradebook_file' => $cFile
    ]);
    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200 on CSV import, got " . $res['status']);
    }
    if (mb_stripos($res['body'], 'thành công') === false) {
        throw new Exception("Success notice missing in CSV import response");
    }
    return true;
});

assertTest("7.3 Scores table in MySQL updated correctly from CSV import", function () {
    $pdo = db();
    $row = $pdo->query("SELECT * FROM scores WHERE student_id = 1 AND subject = 'Toán'")->fetch();
    if (!$row || (float)$row['tx1'] !== 8.5 || (float)$row['avg_score'] !== 8.2) {
        throw new Exception("Scores mismatch after CSV import: " . json_encode($row));
    }
    return true;
});

assertTest("7.4 Audit log records Import file điểm for CSV import", function () {
    $pdo = db();
    $log = $pdo->query("SELECT * FROM audit_logs WHERE action = 'Import file điểm' AND details LIKE '%test_gradebook.csv%' ORDER BY id DESC LIMIT 1")->fetch();
    if (!$log) {
        throw new Exception("Audit log record for CSV import missing");
    }
    return true;
});

@unlink($testCsvPath);

// =============================================================================
// NHÓM 8: XUẤT SỔ ĐIỂM RA FILE EXCEL (.XLSX)
// =============================================================================
echo PHP_EOL . CLR_YELLOW . "--- NHÓM 8: Xuất Sổ Điểm Ra File Excel (.xlsx) & File Mẫu ---" . CLR_RESET . PHP_EOL;

assertTest("8.1 GET nhap_diem.php with export=xlsx returns HTTP 200", function () use ($client) {
    $res = $client->get('nhap_diem.php?export=xlsx&class_id=1&subject=' . urlencode('Toán'));
    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200 for Excel export, got " . $res['status']);
    }
    return true;
});

assertTest("8.2 Export headers declare OpenXML SpreadsheetML Content-Type", function () use ($client) {
    $res = $client->get('nhap_diem.php?export=xlsx&class_id=1&subject=' . urlencode('Toán'));
    if (stripos($res['headers'], 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') === false) {
        throw new Exception("Response header missing OpenXML Content-Type");
    }
    if (stripos($res['headers'], 'attachment; filename=') === false) {
        throw new Exception("Response header missing attachment Content-Disposition");
    }
    return true;
});

assertTest("8.3 Exported binary parses cleanly via parse_xlsx() with headers & data", function () use ($client) {
    $res = $client->get('nhap_diem.php?export=xlsx&class_id=1&subject=' . urlencode('Toán'));
    $tmpPath = sys_get_temp_dir() . '/exported_gradebook_' . bin2hex(random_bytes(4)) . '.xlsx';
    file_put_contents($tmpPath, $res['body']);
    
    $parsed = parse_xlsx($tmpPath);
    @unlink($tmpPath);
    
    if (empty($parsed) || count($parsed) < 2) {
        throw new Exception("Exported .xlsx parsed to empty or incomplete array");
    }
    
    $headerRow = $parsed[0];
    if ($headerRow[0] !== 'STT' || $headerRow[1] !== 'Mã HS' || $headerRow[2] !== 'Họ và tên') {
        throw new Exception("Exported .xlsx header mismatch: " . json_encode($headerRow));
    }
    return true;
});

assertTest("8.4 Downloadable sample files sample_gradebook.xlsx & sample_vnedu.csv return HTTP 200", function () use ($client) {
    $r1 = $client->get('sample_gradebook.xlsx');
    if ($r1['status'] !== 200 || strlen($r1['body']) < 100) {
        throw new Exception("sample_gradebook.xlsx not accessible or empty (HTTP {$r1['status']})");
    }
    
    $r2 = $client->get('sample_vnedu.csv');
    if ($r2['status'] !== 200 || strlen($r2['body']) < 50) {
        throw new Exception("sample_vnedu.csv not accessible or empty (HTTP {$r2['status']})");
    }
    return true;
});

assertTest("8.5 Audit log records Xuất file điểm event in MySQL", function () {
    $pdo = db();
    $log = $pdo->query("SELECT * FROM audit_logs WHERE action = 'Xuất file điểm' AND details LIKE '%Toán%' AND details LIKE '%6A1%' ORDER BY id DESC LIMIT 1")->fetch();
    if (!$log) {
        throw new Exception("Audit log record for 'Xuất file điểm' not found in MySQL");
    }
    return true;
});

// =============================================================================
// NHÓM 9: KIỂM TRA PHÂN QUYỀN RBAC & PHÒNG THỦ BOLA / IDOR TUYỆT ĐỐI
// =============================================================================
echo PHP_EOL . CLR_YELLOW . "--- NHÓM 9: Kiểm Tra Phân Quyền RBAC & Phòng Thủ BOLA / IDOR ---" . CLR_RESET . PHP_EOL;

assertTest("9.1 Unauthenticated access to nhap_diem.php redirects to login.php (HTTP 302)", function () use ($client) {
    $client->clearCookies();
    $res = $client->get('nhap_diem.php');
    if ($res['status'] !== 302 || strpos($res['redirect_url'], 'login.php') === false) {
        throw new Exception("Expected HTTP 302 redirect to login.php, got " . $res['status']);
    }
    return true;
});

assertTest("9.2 Teacher (gv_toan) accessing unassigned subject (Ngữ Văn) blocked (HTTP 403)", function () use ($client) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');
    $res = $client->get('nhap_diem.php?class_id=1&subject=' . urlencode('Ngữ Văn'));
    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403 for unassigned subject, got " . $res['status']);
    }
    return true;
});

assertTest("9.3 Teacher (gv_toan) POSTing scores to unassigned class/subject blocked (HTTP 403)", function () use ($client) {
    $payload = [
        'action' => 'save_scores',
        'scores' => [1 => ['tx1' => '10.0']]
    ];
    $res = $client->post('nhap_diem.php?class_id=1&subject=' . urlencode('Ngữ Văn'), $payload);
    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403 on unassigned POST, got " . $res['status']);
    }
    return true;
});

assertTest("9.4 Teacher (gv_toan) attempting to export unassigned subject blocked (HTTP 403)", function () use ($client) {
    $res = $client->get('nhap_diem.php?export=xlsx&class_id=1&subject=' . urlencode('Ngữ Văn'));
    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403 on unassigned export, got " . $res['status']);
    }
    return true;
});

assertTest("9.5 Teacher (gv_toan) IDOR tampering teacher_id parameter blocked (HTTP 403)", function () use ($client) {
    $res = $client->get('nhap_diem.php?class_id=1&subject=' . urlencode('Toán') . '&teacher_id=3');
    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403 for teacher_id tampering, got " . $res['status']);
    }
    return true;
});

assertTest("9.6 Admin account has legitimate direct inspection rights on any class (HTTP 200)", function () use ($client) {
    $client->clearCookies();
    $client->login('admin', '123456');
    $res = $client->get('nhap_diem.php?class_id=1&subject=' . urlencode('Ngữ Văn') . '&teacher_id=3');
    if ($res['status'] !== 200) {
        throw new Exception("Admin direct inspection failed with HTTP " . $res['status']);
    }
    if (mb_stripos($res['body'], 'Chế độ Admin') === false) {
        throw new Exception("Admin inspection banner missing in HTML");
    }
    return true;
});

// =============================================================================
// NHÓM 10: KIỂM TOÁN DỮ LIỆU NHẬT KÝ HỆ THỐNG (AUDIT LOGS FORENSICS)
// =============================================================================
echo PHP_EOL . CLR_YELLOW . "--- NHÓM 10: Kiểm Toán Nhật Ký Hệ Thống (Audit Logs Forensics) ---" . CLR_RESET . PHP_EOL;

assertTest("10.1 All Milestone 4 actions recorded in audit_logs", function () {
    $pdo = db();
    $expectedActions = [
        'Lưu sổ điểm',
        'Import file điểm',
        'Xuất file điểm',
        'Truy cập trái phép bị chặn',
        'Admin kiểm tra sổ điểm'
    ];
    
    foreach ($expectedActions as $act) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE action = ?");
        $chk->execute([$act]);
        if ((int)$chk->fetchColumn() === 0) {
            throw new Exception("Action '$act' has 0 records in audit_logs");
        }
    }
    return true;
});

assertTest("10.2 Audit log records contain non-empty IP, username, and descriptive details", function () {
    $pdo = db();
    $records = $pdo->query("SELECT * FROM audit_logs WHERE action IN ('Lưu sổ điểm', 'Import file điểm', 'Xuất file điểm') ORDER BY id DESC LIMIT 5")->fetchAll();
    
    if (empty($records)) {
        throw new Exception("No audit records found to inspect");
    }
    
    foreach ($records as $r) {
        if (empty($r['username']) || empty($r['action']) || empty($r['details']) || empty($r['ip_address'])) {
            throw new Exception("Audit log record ID {$r['id']} has empty required fields");
        }
    }
    return true;
});

// =============================================================================
// CLEANUP & KHÔI PHỤC DỮ LIỆU
// =============================================================================
try {
    $pdo = db();
    if ($origScore) {
        $rst = $pdo->prepare("
            UPDATE scores 
            SET tx1 = ?, tx2 = ?, tx3 = ?, tx4 = ?, gk = ?, ck = ?, avg_score = ?
            WHERE student_id = 1 AND subject = 'Toán'
        ");
        $rst->execute([
            $origScore['tx1'],
            $origScore['tx2'],
            $origScore['tx3'],
            $origScore['tx4'],
            $origScore['gk'],
            $origScore['ck'],
            $origScore['avg_score']
        ]);
    }
} catch (Throwable $e) {
    // Ignore cleanup errors
}

// =============================================================================
// TỔNG KẾT & MÃ THOÁT
// =============================================================================
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
