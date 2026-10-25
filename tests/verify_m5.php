<?php
/**
 * =============================================================================
 * Automated Milestone 5 Verification Suite (EduRadar THCS) — Integrity Iteration 2
 * Kiểm thử tự động Milestone 5 (Tuân thủ tiêu chuẩn Kiểm toán Tính toàn vẹn 100%):
 *   - Nhóm 1: Thuật toán phân loại Cảnh Báo Sớm trực tiếp từ includes/radar_helper.php
 *   - Nhóm 2: Trường hợp biên & Phát hiện xu hướng sa sút & Điểm thành phần dưới 5.0
 *   - Nhóm 3: Bộ đếm thống kê & Tỷ lệ phần trăm tính toán từ dữ liệu thực tế live DB
 *   - Nhóm 4: Thẻ lọc tương tác, thuộc tính DOM bảng & Tính duy nhất của DOM ID
 *   - Nhóm 5: Kiểm toán loại bỏ hoàn toàn Zalo & Mã rác (0 'zalo', 0 'actionModal')
 *   - Nhóm 6: Phân quyền RBAC nghiêm ngặt & Kiểm toán 0 lỗi PHP trên Live Apache
 *   - Nhóm 7: Tính toàn vẹn của Design Tokens & Giao diện Stitch UI
 * 
 * Chạy lệnh kiểm thử:
 *   D:\xam\php\php.exe tests/verify_m5.php
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

// Kiểm tra sự hiện diện của helper dùng chung (nghiêm cấm mọi hình thức mock/facade)
$radarHelperPath = dirname(__DIR__) . '/includes/radar_helper.php';
if (!file_exists($radarHelperPath)) {
    echo PHP_EOL . CLR_RED . "[FATAL INTEGRITY VIOLATION] File 'includes/radar_helper.php' does not exist!" . CLR_RESET . PHP_EOL;
    echo CLR_YELLOW . "  Verification suite tests/verify_m5.php refuses to execute with mock stubs." . CLR_RESET . PHP_EOL;
    echo CLR_YELLOW . "  Please implement includes/radar_helper.php to provide genuine evaluateRiskZone() and calculateRadarStats()." . CLR_RESET . PHP_EOL . PHP_EOL;
    exit(1);
}
require_once $radarHelperPath;

if (!function_exists('evaluateRiskZone')) {
    echo PHP_EOL . CLR_RED . "[FATAL INTEGRITY VIOLATION] Function 'evaluateRiskZone()' not found in includes/radar_helper.php!" . CLR_RESET . PHP_EOL . PHP_EOL;
    exit(1);
}
if (!function_exists('calculateRadarStats')) {
    echo PHP_EOL . CLR_RED . "[FATAL INTEGRITY VIOLATION] Function 'calculateRadarStats()' not found in includes/radar_helper.php!" . CLR_RESET . PHP_EOL . PHP_EOL;
    exit(1);
}

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
 * HTTP Client chuyên dụng hỗ trợ Session Cookies, Redirects và Kiểm toán lỗi PHP qua cURL
 */
class TestClient
{
    private string $baseUrl;
    private string $cookieFile;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->cookieFile = sys_get_temp_dir() . '/edu_m5_cookie_' . bin2hex(random_bytes(6)) . '.txt';
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
            $options[CURLOPT_POSTFIELDS] = is_array($data) ? http_build_query($data) : $data;
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

    public function login(string $username, string $password): array
    {
        return $this->post('login.php', [
            'username' => $username,
            'password' => $password
        ], false);
    }

    /**
     * Kiểm toán nghiêm ngặt nội dung HTML phản hồi từ Live Apache:
     * Đảm bảo không chứa bất kỳ Warning, Notice, Deprecated hay Fatal error nào từ PHP.
     */
    public function assertCleanResponse(array $res, string $contextName): void
    {
        if ($res['status'] !== 200) {
            throw new Exception("[$contextName] Expected HTTP 200, got HTTP {$res['status']}");
        }
        $body = $res['body'];
        $forbiddenPatterns = [
            '<b>Warning</b>',
            'Warning:',
            '<b>Notice</b>',
            'Notice:',
            '<b>Deprecated</b>',
            'Deprecated:',
            'Fatal error:',
            '<b>Fatal error</b>',
            'Parse error:',
            'Undefined variable',
            'Undefined array key',
            'Undefined index',
            'Call to undefined',
            'Uncaught Error',
            'Uncaught Exception'
        ];
        foreach ($forbiddenPatterns as $pattern) {
            if (stripos($body, $pattern) !== false) {
                $pos = stripos($body, $pattern);
                $start = max(0, $pos - 40);
                $snippet = substr($body, $start, 180);
                throw new Exception("[$contextName] Live PHP error leaked into HTML! Pattern '$pattern' found. Snippet: " . strip_tags($snippet));
            }
        }
    }
}

$client = new TestClient('http://localhost/my-web-project');

echo PHP_EOL . CLR_YELLOW . "============================================================" . CLR_RESET . PHP_EOL;
echo CLR_YELLOW . "  EduRadar Milestone 5: Authentic Verification Suite (It. 2)" . CLR_RESET . PHP_EOL;
echo CLR_YELLOW . "  Target: http://localhost/my-web-project/ (Apache & MariaDB)" . CLR_RESET . PHP_EOL;
echo CLR_YELLOW . "============================================================" . CLR_RESET . PHP_EOL . PHP_EOL;

// =============================================================================
// NHÓM 1: THUẬT TOÁN PHÂN LOẠI CẢNH BÁO SỚM (GỌI TRỰC TIẾP TỪ includes/radar_helper.php)
// =============================================================================
echo CLR_YELLOW . "--- NHÓM 1: Thuật toán Phân loại Cảnh Báo Sớm (radar_helper.php) ---" . CLR_RESET . PHP_EOL;

assertTest("1.1 Student with avg_score < 3.5 classified as 🔴 red (Nguy cấp)", function () {
    $res = evaluateRiskZone(3.2, 5.0, 5.0);
    if ($res['level'] !== 'red') throw new Exception("Expected 'red', got '{$res['level']}'");
    if ($res['label'] !== 'Nguy cấp') throw new Exception("Expected label 'Nguy cấp', got '{$res['label']}'");
    if (stripos($res['diagnostic'], 'dưới 3.5') === false) throw new Exception("Diagnostic missing 'dưới 3.5'");
    return true;
});

assertTest("1.2 Student with ck < 3.0 classified as 🔴 red (Nguy cấp) even if avg >= 5.0", function () {
    $res = evaluateRiskZone(5.2, 7.0, 2.5);
    if ($res['level'] !== 'red') throw new Exception("Expected 'red', got '{$res['level']}'");
    if (stripos($res['diagnostic'], '3.0') === false) throw new Exception("Diagnostic missing '3.0'");
    return true;
});

assertTest("1.3 Student failing both GK & CK (< 5.0) classified as 🔴 red (Nguy cấp)", function () {
    $res = evaluateRiskZone(4.8, 4.5, 4.5);
    if ($res['level'] !== 'red') throw new Exception("Expected 'red', got '{$res['level']}'");
    if (stripos($res['diagnostic'], 'Hỏng cả GK') === false && stripos($res['diagnostic'], 'cả GK và CK') === false) {
        throw new Exception("Diagnostic missing dual exam failure note: {$res['diagnostic']}");
    }
    return true;
});

assertTest("1.4 Student with multiple critical flags aggregates reasons cleanly", function () {
    $res = evaluateRiskZone(3.0, 4.0, 2.5);
    if ($res['level'] !== 'red') throw new Exception("Expected 'red'");
    if (count($res['reasons']) < 2) throw new Exception("Expected multiple diagnostic reasons");
    if (!str_contains($res['diagnostic'], ' · ')) throw new Exception("Reasons not delimited by ' · '");
    return true;
});

assertTest("1.5 Student with avg in [3.5, 5.0) classified as 🟡 yellow (Cần chú ý)", function () {
    $res = evaluateRiskZone(4.2, 5.5, 5.0);
    if ($res['level'] !== 'yellow') throw new Exception("Expected 'yellow', got '{$res['level']}'");
    if ($res['label'] !== 'Cần chú ý') throw new Exception("Expected label 'Cần chú ý', got '{$res['label']}'");
    if (stripos($res['diagnostic'], 'dưới chuẩn') === false) throw new Exception("Diagnostic missing 'dưới chuẩn'");
    return true;
});

assertTest("1.6 Student with avg >= 5.0 and passing components classified as 🟢 green", function () {
    $res = evaluateRiskZone(6.5, 7.0, 6.5);
    if ($res['level'] !== 'green') throw new Exception("Expected 'green', got '{$res['level']}'");
    if ($res['label'] !== 'Ổn định') throw new Exception("Expected label 'Ổn định', got '{$res['label']}'");
    return true;
});

assertTest("1.7 Student with avg >= 8.0 classified as 🟢 green (Ổn định)", function () {
    $res = evaluateRiskZone(8.5, 9.0, 8.5);
    if ($res['level'] !== 'green') throw new Exception("Expected 'green', got '{$res['level']}'");
    return true;
});

assertTest("1.8 Student with empty/null scores classified as 🟢 green without errors", function () {
    $res = evaluateRiskZone(null, null, null);
    if ($res['level'] !== 'green') throw new Exception("Expected 'green', got '{$res['level']}'");
    if (stripos($res['diagnostic'], 'Chưa đủ dữ liệu') === false && stripos($res['diagnostic'], 'Chưa đủ') === false) {
        throw new Exception("Diagnostic missing 'Chưa đủ dữ liệu': {$res['diagnostic']}");
    }
    return true;
});

// =============================================================================
// NHÓM 2: TRƯỜNG HỢP BIÊN & PHÁT HIỆN XU HƯỚNG SA SÚT & ĐIỂM THÀNH PHẦN
// =============================================================================
echo PHP_EOL . CLR_YELLOW . "--- NHÓM 2: Trường hợp Biên, Xu hướng Sa sút & Điểm Thành phần ---" . CLR_RESET . PHP_EOL;

assertTest("2.1 Downward trend ck < gk - 1.5 triggers 🟡 yellow (Cần chú ý)", function () {
    $res = evaluateRiskZone(6.2, 8.0, 6.0); // diff = 2.0 > 1.5
    if ($res['level'] !== 'yellow') throw new Exception("Expected 'yellow', got '{$res['level']}'");
    if (stripos($res['diagnostic'], 'Sa sút') === false) throw new Exception("Missing 'Sa sút' diagnostic");
    return true;
});

assertTest("2.2 Boundary difference exactly 1.5 does not trigger downward trend", function () {
    $res = evaluateRiskZone(6.2, 8.0, 6.5); // diff = 1.5 (not > 1.5)
    if ($res['level'] !== 'green') throw new Exception("Expected 'green', got '{$res['level']}'");
    return true;
});

assertTest("2.3 Boundary difference 1.6 triggers downward trend", function () {
    $res = evaluateRiskZone(6.2, 8.0, 6.4); // diff = 1.6 > 1.5
    if ($res['level'] !== 'yellow') throw new Exception("Expected 'yellow', got '{$res['level']}'");
    return true;
});

assertTest("2.4 Partial periodic scores (gk present, ck null) do not trigger false alarm", function () {
    $res = evaluateRiskZone(7.0, 7.5, null);
    if ($res['level'] !== 'green') throw new Exception("Expected 'green', got '{$res['level']}'");
    return true;
});

assertTest("2.5 Single periodic component failure (< 5.0) flags 🟡 yellow (Cần chú ý)", function () {
    // Failing only GK: gk=4.5 < 5.0, ck=6.5, avg=5.8
    $resGk = evaluateRiskZone(5.8, 4.5, 6.5);
    if ($resGk['level'] !== 'yellow') throw new Exception("Failing GK (< 5.0) expected 'yellow', got '{$resGk['level']}'");
    
    // Failing only CK: gk=5.0, ck=4.9 < 5.0, avg=5.5 (R4 requirement)
    $resCk = evaluateRiskZone(5.5, 5.0, 4.9);
    if ($resCk['level'] !== 'yellow') throw new Exception("Failing CK (< 5.0) expected 'yellow', got '{$resCk['level']}'");
    return true;
});

assertTest("2.6 Precedence rule: ck < 3.0 with downward trend remains 🔴 red", function () {
    $res = evaluateRiskZone(5.2, 8.0, 2.5); // drop = 5.5, but ck = 2.5 < 3.0
    if ($res['level'] !== 'red') throw new Exception("Critical zone ck < 3.0 must override downward trend warning");
    return true;
});

assertTest("2.7 Zero warnings or notices emitted under E_ALL for all boundary scores", function () {
    $capturedErrors = [];
    set_error_handler(function ($errno, $errstr, $errfile, $errline) use (&$capturedErrors) {
        $capturedErrors[] = "$errstr on $errfile:$errline";
        return false;
    });
    try {
        evaluateRiskZone(0.0, 0.0, 0.0, [0.0]);
        evaluateRiskZone(3.49, 5.0, 5.0);
        evaluateRiskZone(3.50, 5.0, 5.0);
        evaluateRiskZone(4.99, 5.0, 5.0);
        evaluateRiskZone(5.00, 5.0, 5.0);
        evaluateRiskZone(7.3, 5.8, 5.8);
        evaluateRiskZone(null, null, null, []);
    } finally {
        restore_error_handler();
    }
    if (!empty($capturedErrors)) {
        throw new Exception("PHP error captured: " . implode('; ', $capturedErrors));
    }
    return true;
});

// =============================================================================
// NHÓM 3: BỘ ĐẾM THỐNG KÊ & TỶ LỆ PHẦN TRĂM DỰA TRÊN DỮ LIỆU THẬT & calculateRadarStats()
// =============================================================================
echo PHP_EOL . CLR_YELLOW . "--- NHÓM 3: Bộ đếm Thống kê & Tỷ lệ Phần trăm (calculateRadarStats) ---" . CLR_RESET . PHP_EOL;

/**
 * Helper nạp và đánh giá danh sách học sinh thực tế từ cơ sở dữ liệu
 */
function getEvaluatedLiveStudents(): array
{
    $pdo = db();
    $stmt = $pdo->query("
        SELECT s.id AS student_id, s.class_id, s.full_name, c.name AS class_name, tc.subject,
               sc.tx1, sc.tx2, sc.tx3, sc.tx4, sc.gk, sc.ck, sc.avg_score
        FROM teacher_classes tc
        JOIN classes c ON tc.class_id = c.id
        JOIN students s ON s.class_id = c.id
        LEFT JOIN scores sc ON sc.student_id = s.id AND sc.subject = tc.subject
        ORDER BY c.grade ASC, c.name ASC, s.full_name ASC
    ");
    $rawStudents = $stmt->fetchAll();
    if (empty($rawStudents)) {
        throw new Exception("No student records found in live database edu_radar");
    }

    $evaluated = [];
    foreach ($rawStudents as $r) {
        $avg = ($r['avg_score'] !== null && $r['avg_score'] !== '') ? (float)$r['avg_score'] : null;
        $gk  = ($r['gk'] !== null && $r['gk'] !== '') ? (float)$r['gk'] : null;
        $ck  = ($r['ck'] !== null && $r['ck'] !== '') ? (float)$r['ck'] : null;
        $txList = [];
        foreach (['tx1', 'tx2', 'tx3', 'tx4'] as $col) {
            if ($r[$col] !== null && $r[$col] !== '') {
                $txList[] = (float)$r[$col];
            }
        }
        $eval = evaluateRiskZone($avg, $gk, $ck, $txList);
        $evaluated[] = array_merge($r, $eval, ['tx_list' => $txList]);
    }
    return $evaluated;
}

assertTest("3.1 calculateRadarStats() balances against real database students: total === red + yellow + green", function () {
    $realStudents = getEvaluatedLiveStudents();
    $stats = calculateRadarStats($realStudents);

    if ($stats['total'] !== count($realStudents)) {
        throw new Exception("Total mismatch: expected " . count($realStudents) . ", got {$stats['total']}");
    }
    if ($stats['total'] !== ($stats['red'] + $stats['yellow'] + $stats['green'])) {
        throw new Exception("Stat invariant violated: {$stats['total']} !== {$stats['red']} + {$stats['yellow']} + {$stats['green']}");
    }
    return true;
});

assertTest("3.2 Percentage sum invariant holds on real database dataset (~100.0%)", function () {
    $realStudents = getEvaluatedLiveStudents();
    $stats = calculateRadarStats($realStudents);

    $expectedRedPct = round(($stats['red'] / $stats['total']) * 100, 1);
    $expectedYellowPct = round(($stats['yellow'] / $stats['total']) * 100, 1);
    $expectedGreenPct = round(($stats['green'] / $stats['total']) * 100, 1);

    if ($stats['red_pct'] !== $expectedRedPct) throw new Exception("Red pct mismatch: expected $expectedRedPct, got {$stats['red_pct']}");
    if ($stats['yellow_pct'] !== $expectedYellowPct) throw new Exception("Yellow pct mismatch: expected $expectedYellowPct, got {$stats['yellow_pct']}");
    if ($stats['green_pct'] !== $expectedGreenPct) throw new Exception("Green pct mismatch: expected $expectedGreenPct, got {$stats['green_pct']}");

    $sum = $stats['red_pct'] + $stats['yellow_pct'] + $stats['green_pct'];
    if (abs($sum - 100.0) > 0.3) {
        throw new Exception("Percentages sum to $sum != 100% on live dataset");
    }
    return true;
});

assertTest("3.3 Zero-division defense in calculateRadarStats([]) yields 0% without errors", function () {
    $emptyStats = calculateRadarStats([]);
    if ($emptyStats['total'] !== 0) throw new Exception("Expected total = 0");
    if ($emptyStats['red'] !== 0 || $emptyStats['yellow'] !== 0 || $emptyStats['green'] !== 0) {
        throw new Exception("Expected all counts = 0");
    }
    if ($emptyStats['red_pct'] !== 0.0 || $emptyStats['yellow_pct'] !== 0.0 || $emptyStats['green_pct'] !== 0.0) {
        throw new Exception("Expected all pcts = 0.0");
    }
    return true;
});

assertTest("3.4 Stat cards in rendered HTML match calculateRadarStats() real output exactly", function () use ($client) {
    $realStudents = getEvaluatedLiveStudents();
    $realStats = calculateRadarStats($realStudents);

    $client->clearCookies();
    $client->login('admin', '123456');
    $indexRes = $client->get('index.php');
    $client->assertCleanResponse($indexRes, 'Admin index.php');

    // Extract stat card counts from HTML
    preg_match('/stat-card\s+total.*?<b>(\d+)<\/b>/s', $indexRes['body'], $mTotal);
    preg_match('/stat-card\s+red.*?<b>(\d+)<\/b>/s', $indexRes['body'], $mRed);
    preg_match('/stat-card\s+yellow.*?<b>(\d+)<\/b>/s', $indexRes['body'], $mYellow);
    preg_match('/stat-card\s+green.*?<b>(\d+)<\/b>/s', $indexRes['body'], $mGreen);

    if (empty($mTotal) || empty($mRed) || empty($mYellow) || empty($mGreen)) {
        throw new Exception("Could not find all 4 stat cards in HTML");
    }
    if ((int)$mTotal[1] !== $realStats['total']) {
        throw new Exception("HTML total {$mTotal[1]} != DB total {$realStats['total']}");
    }
    if ((int)$mRed[1] !== $realStats['red']) {
        throw new Exception("HTML red {$mRed[1]} != DB red {$realStats['red']}");
    }
    if ((int)$mYellow[1] !== $realStats['yellow']) {
        throw new Exception("HTML yellow {$mYellow[1]} != DB yellow {$realStats['yellow']}");
    }
    if ((int)$mGreen[1] !== $realStats['green']) {
        throw new Exception("HTML green {$mGreen[1]} != DB green {$realStats['green']}");
    }
    return true;
});

assertTest("3.5 Live database score query runs cleanly without syntax error", function () {
    $pdo = db();
    $stmt = $pdo->query("
        SELECT s.id, s.full_name, c.name AS class_name, sc.tx1, sc.gk, sc.ck, sc.avg_score
        FROM students s
        JOIN classes c ON s.class_id = c.id
        LEFT JOIN scores sc ON s.id = sc.student_id
        LIMIT 5
    ");
    $rows = $stmt->fetchAll();
    if (!is_array($rows)) throw new Exception("Query failed");
    return true;
});

// =============================================================================
// NHÓM 4: THẺ LỌC TƯƠNG TÁC, THUỘC TÍNH BẢNG & TÍNH DUY NHẤT CỦA DOM ID
// =============================================================================
echo PHP_EOL . CLR_YELLOW . "--- NHÓM 4: Thẻ Lọc Tương Tác, Thuộc Tính Bảng & Tính Duy Nhất DOM ID ---" . CLR_RESET . PHP_EOL;

assertTest("4.1 Filter tabs declare data-filter attributes for all, red, yellow, green", function () use ($client) {
    $res = $client->get('index.php');
    foreach (['all', 'red', 'yellow', 'green'] as $f) {
        if (!preg_match('/data-filter=["\']' . $f . '["\']/i', $res['body'])) {
            throw new Exception("Missing filter button for '$f'");
        }
    }
    return true;
});

assertTest("4.2 Table body rows contain data-level attributes", function () use ($client) {
    $res = $client->get('index.php');
    if (!preg_match('/<tr[^>]*data-level=["\'](red|yellow|green)["\']/i', $res['body'])) {
        throw new Exception("Missing data-level on table rows");
    }
    return true;
});

assertTest("4.3 Table body rows contain data-name attributes for instant search", function () use ($client) {
    $res = $client->get('index.php');
    if (!preg_match('/<tr[^>]*data-name=["\'][^"\']+["\']/i', $res['body'])) {
        throw new Exception("Missing data-name on table rows");
    }
    return true;
});

assertTest("4.4 Search input element exists with id search", function () use ($client) {
    $res = $client->get('index.php');
    if (!preg_match('/<input[^>]*id=["\']search["\']/i', $res['body'])) {
        throw new Exception("Missing #search input");
    }
    return true;
});

assertTest("4.5 Table renders component score columns and average badge", function () use ($client) {
    $res = $client->get('index.php');
    if (stripos($res['body'], 'avg') === false && stripos($res['body'], 'Điểm TB') === false) {
        throw new Exception("Missing average score column");
    }
    return true;
});

assertTest("4.6 Action column links directly to nhap_diem.php with parameters", function () use ($client) {
    $res = $client->get('index.php');
    if (!preg_match('/href=["\']nhap_diem\.php\?[^"\']*class_id=\d+&(?:amp;)?subject=[^"\']+["\']/i', $res['body']) &&
        !preg_match('/href=["\']nhap_diem\.php\?[^"\']*class_id=/i', $res['body'])) {
        throw new Exception("Missing parameterized link to nhap_diem.php");
    }
    return true;
});

assertTest("4.7 Table declares unique DOM identifiers without duplicate ID attributes in HTML", function () use ($client) {
    $res = $client->get('index.php');
    preg_match_all('/\bid=["\']([^"\']+)["\']/i', $res['body'], $matches);
    $ids = $matches[1] ?? [];
    $idCounts = array_count_values($ids);
    $duplicates = [];
    foreach ($idCounts as $id => $cnt) {
        if ($cnt > 1) {
            $duplicates[] = "$id ($cnt times)";
        }
    }
    if (!empty($duplicates)) {
        throw new Exception("Duplicate HTML IDs detected in index.php DOM: " . implode(', ', $duplicates));
    }
    return true;
});

// =============================================================================
// NHÓM 5: LOẠI BỎ HOÀN TOÀN ZALO & MÃ RÁC
// =============================================================================
echo PHP_EOL . CLR_YELLOW . "--- NHÓM 5: Loại Bỏ Hoàn Toàn Zalo & Mã Rác ---" . CLR_RESET . PHP_EOL;

assertTest("5.1 File index.php contains 0 occurrences of zalo (case-insensitive)", function () {
    $content = file_get_contents(dirname(__DIR__) . '/index.php');
    if (stripos($content, 'zalo') !== false) {
        throw new Exception("Found 'zalo' in index.php");
    }
    return true;
});

assertTest("5.2 File assets/js/main.js contains 0 occurrences of zalo", function () {
    $content = file_get_contents(dirname(__DIR__) . '/assets/js/main.js');
    if (stripos($content, 'zalo') !== false) {
        throw new Exception("Found 'zalo' in assets/js/main.js");
    }
    return true;
});

assertTest("5.3 File index.php contains 0 occurrences of actionModal", function () {
    $content = file_get_contents(dirname(__DIR__) . '/index.php');
    if (stripos($content, 'actionModal') !== false) {
        throw new Exception("Found 'actionModal' in index.php");
    }
    return true;
});

assertTest("5.4 File assets/js/main.js contains 0 occurrences of actionModal", function () {
    $content = file_get_contents(dirname(__DIR__) . '/assets/js/main.js');
    if (stripos($content, 'actionModal') !== false) {
        throw new Exception("Found 'actionModal' in assets/js/main.js");
    }
    return true;
});

assertTest("5.5 File assets/js/main.js contains 0 occurrences of copy-btn / copyMessage", function () {
    $content = file_get_contents(dirname(__DIR__) . '/assets/js/main.js');
    if (stripos($content, 'copy-btn') !== false || stripos($content, 'copyMessage') !== false || stripos($content, 'buildFeedback') !== false) {
        throw new Exception("Found copy-btn / copyMessage / buildFeedback in assets/js/main.js");
    }
    return true;
});

assertTest("5.6 HTTP GET index.php response for Admin contains 0 occurrences of zalo", function () use ($client) {
    $client->clearCookies();
    $client->login('admin', '123456');
    $res = $client->get('index.php');
    if (stripos($res['body'], 'zalo') !== false) {
        throw new Exception("Live Admin index.php response contains 'zalo'");
    }
    return true;
});

assertTest("5.7 HTTP GET index.php response for Teacher contains 0 occurrences of zalo", function () use ($client) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');
    $res = $client->get('index.php');
    if (stripos($res['body'], 'zalo') !== false) {
        throw new Exception("Live Teacher index.php response contains 'zalo'");
    }
    return true;
});

// =============================================================================
// NHÓM 6: PHÂN QUYỀN TRUY CẬP NGHIÊM NGẶT (RBAC) & KIỂM TOÁN KHÔNG RÒ RỈ LỖI PHP
// =============================================================================
echo PHP_EOL . CLR_YELLOW . "--- NHÓM 6: Phân Quyền RBAC & Kiểm Toán 0 Lỗi PHP trên Live Apache ---" . CLR_RESET . PHP_EOL;

assertTest("6.1 Unauthenticated access to index.php redirects to login.php (HTTP 302)", function () use ($client) {
    $client->clearCookies();
    $res = $client->get('index.php', false);
    if ($res['status'] !== 302 && $res['status'] !== 301) {
        throw new Exception("Expected redirect 302, got HTTP {$res['status']}");
    }
    return true;
});

assertTest("6.2 Teacher gv_toan accesses index.php successfully (HTTP 200)", function () use ($client) {
    $client->clearCookies();
    $login = $client->login('gv_toan', '123456');
    $res = $client->get('index.php');
    if ($res['status'] !== 200) throw new Exception("Expected HTTP 200, got {$res['status']}");
    return true;
});

assertTest("6.3 Teacher gv_toan only views assigned classes and subjects (Math only)", function () use ($client) {
    $res = $client->get('index.php');
    if (stripos($res['body'], 'Ngữ Văn') !== false) {
        throw new Exception("gv_toan unexpectedly sees unassigned subject 'Ngữ Văn'");
    }
    if (stripos($res['body'], 'Toán') === false) {
        throw new Exception("gv_toan missing assigned subject 'Toán'");
    }
    return true;
});

assertTest("6.4 Teacher gv_toan cannot access admin menu link on index.php", function () use ($client) {
    $res = $client->get('index.php');
    if (preg_match('/href=["\']admin\.php["\']/i', $res['body'])) {
        throw new Exception("admin.php link visible to teacher gv_toan on index.php");
    }
    return true;
});

assertTest("6.5 Teacher gv_van only views assigned classes and subjects (Literature only)", function () use ($client) {
    $client->clearCookies();
    $login = $client->login('gv_van', '123456');
    $res = $client->get('index.php');
    if ($res['status'] !== 200) throw new Exception("Expected HTTP 200 for gv_van");
    if (stripos($res['body'], 'Toán') !== false) {
        throw new Exception("gv_van unexpectedly sees unassigned subject 'Toán'");
    }
    if (stripos($res['body'], 'Ngữ Văn') === false) {
        throw new Exception("gv_van missing assigned subject 'Ngữ Văn'");
    }
    return true;
});

assertTest("6.6 Admin account accesses index.php with school-wide overview (HTTP 200)", function () use ($client) {
    $client->clearCookies();
    $login = $client->login('admin', '123456');
    $res = $client->get('index.php');
    if ($res['status'] !== 200) throw new Exception("Expected HTTP 200, got {$res['status']}");
    if (stripos($res['body'], 'Toán') === false || stripos($res['body'], 'Ngữ Văn') === false) {
        throw new Exception("Admin overview missing multi-subject view");
    }
    return true;
});

assertTest("6.7 Live HTTP response for Admin index.php contains 0 PHP Warnings, Notices, or Errors", function () use ($client) {
    $client->clearCookies();
    $client->login('admin', '123456');
    $res = $client->get('index.php');
    $client->assertCleanResponse($res, 'Admin index.php');
    return true;
});

assertTest("6.8 Live HTTP response for Teacher gv_toan index.php contains 0 PHP Warnings, Notices, or Errors", function () use ($client) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');
    $res = $client->get('index.php');
    $client->assertCleanResponse($res, 'Teacher gv_toan index.php');
    return true;
});

assertTest("6.9 Live HTTP response for Teacher gv_van index.php contains 0 PHP Warnings, Notices, or Errors", function () use ($client) {
    $client->clearCookies();
    $client->login('gv_van', '123456');
    $res = $client->get('index.php');
    $client->assertCleanResponse($res, 'Teacher gv_van index.php');
    return true;
});

// =============================================================================
// NHÓM 7: TÍNH TOÀN VẸN CỦA DESIGN TOKENS & GIAO DIỆN STITCH UI
// =============================================================================
echo PHP_EOL . CLR_YELLOW . "--- NHÓM 7: Tính Toàn Vẹn của Design Tokens & Giao Diện Stitch UI ---" . CLR_RESET . PHP_EOL;

assertTest("7.1 assets/css/style.css imports Plus Jakarta Sans font", function () {
    $css = file_get_contents(dirname(__DIR__) . '/assets/css/style.css');
    if (stripos($css, 'Plus+Jakarta+Sans') === false && stripos($css, 'Plus Jakarta Sans') === false) {
        throw new Exception("Missing 'Plus Jakarta Sans' in style.css");
    }
    return true;
});

assertTest("7.2 assets/css/style.css defines primary brand tokens in :root", function () {
    $css = file_get_contents(dirname(__DIR__) . '/assets/css/style.css');
    if (strpos($css, '--primary:') === false || strpos($css, '#4f46e5') === false) {
        throw new Exception("Missing --primary #4f46e5 in style.css");
    }
    return true;
});

assertTest("7.3 assets/css/style.css defines semantic status tokens (--danger, --warning, --success)", function () {
    $css = file_get_contents(dirname(__DIR__) . '/assets/css/style.css');
    foreach (['--danger', '--warning', '--success'] as $token) {
        if (strpos($css, $token) === false) throw new Exception("Missing $token in style.css");
    }
    return true;
});

assertTest("7.4 assets/css/style.css defines soft shadow and border radius tokens", function () {
    $css = file_get_contents(dirname(__DIR__) . '/assets/css/style.css');
    if (strpos($css, '--shadow-') === false || strpos($css, '--radius-') === false) {
        throw new Exception("Missing shadow or radius tokens in style.css");
    }
    return true;
});

assertTest("7.5 assets/css/style.css defines role badges for Admin and Teacher", function () {
    $css = file_get_contents(dirname(__DIR__) . '/assets/css/style.css');
    if (strpos($css, 'role-admin') === false && strpos($css, 'badge-role-admin') === false) {
        throw new Exception("Missing role-admin badge class in style.css");
    }
    if (strpos($css, 'role-teacher') === false && strpos($css, 'badge-role-teacher') === false) {
        throw new Exception("Missing role-teacher badge class in style.css");
    }
    return true;
});

assertTest("7.6 Header navigation renders active state class for current route", function () use ($client) {
    $client->clearCookies();
    $client->login('admin', '123456');
    $res = $client->get('index.php');
    if (!preg_match('/class=["\'][^"\']*active[^"\']*["\']/i', $res['body'])) {
        throw new Exception("Missing active state on navigation link");
    }
    return true;
});

// =============================================================================
// TỔNG KẾT KẾT QUẢ KIỂM THỬ
// =============================================================================
echo PHP_EOL . CLR_YELLOW . "============================================================" . CLR_RESET . PHP_EOL;
$total = $passedCount + $failedCount;
echo "  Total Tests: " . $total . " | Passed: " . CLR_GREEN . $passedCount . CLR_RESET . " | Failed: " . ($failedCount > 0 ? CLR_RED . $failedCount . CLR_RESET : "0") . PHP_EOL;
echo CLR_YELLOW . "============================================================" . CLR_RESET . PHP_EOL . PHP_EOL;

if ($failedCount > 0) {
    exit(1);
}
exit(0);
