<?php
/**
 * =============================================================================
 * Automated Milestone 2 Verification Suite (EduRadar THCS)
 * Kiểm thử tự động Milestone 2: Authentication, Password Hashing & Strict RBAC
 * 
 * Chạy lệnh kiểm thử:
 *   D:\xam\php\php.exe tests/verify_m2.php
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
    echo CLR_CYAN . "[TEST] " . CLR_RESET . str_pad($name, 62, ".");
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
 * HTTP Client chuyên dụng cho kiểm thử Web Application qua cURL
 */
class TestClient
{
    private string $baseUrl;
    private string $cookieFile;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->cookieFile = sys_get_temp_dir() . '/edu_m2_cookie_' . bin2hex(random_bytes(6)) . '.txt';
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

    public function request(string $method, string $path, array $data = [], bool $followRedirects = false): array
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
            CURLOPT_TIMEOUT => 10,
        ];

        if (strtoupper($method) === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($data);
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
}

$client = new TestClient('http://localhost/my-web-project');

echo PHP_EOL . CLR_YELLOW . "============================================================" . CLR_RESET . PHP_EOL;
echo CLR_YELLOW . "  EduRadar Milestone 2: Automated Verification Suite" . CLR_RESET . PHP_EOL;
echo CLR_YELLOW . "============================================================" . CLR_RESET . PHP_EOL . PHP_EOL;

// -------------------------------------------------------------
// NHÓM 1: KIỂM TRA MÃ NGUỒN VÀ KHỬ BACKDOOR (STATIC VERIFICATION)
// -------------------------------------------------------------
assertTest("1.1 includes/auth.php exists with required RBAC functions", function () {
    $authPath = dirname(__DIR__) . '/includes/auth.php';
    if (!file_exists($authPath)) {
        throw new Exception("File includes/auth.php does not exist");
    }
    require_once $authPath;
    $requiredFns = [
        'require_login',
        'require_admin',
        'current_user',
        'verify_teacher_assignment',
        'require_teacher_assignment'
    ];
    foreach ($requiredFns as $fn) {
        if (!function_exists($fn)) {
            throw new Exception("Function $fn() is not defined in includes/auth.php");
        }
    }
    return true;
});

assertTest("1.2 login.php backdoor bypass (|| \$password === '123456') removed", function () {
    $loginCode = file_get_contents(dirname(__DIR__) . '/login.php');
    if (strpos($loginCode, "|| \$password === '123456'") !== false ||
        strpos($loginCode, '|| $password === "123456"') !== false) {
        throw new Exception("login.php still contains backdoor bypass '|| \$password === 123456'");
    }
    return true;
});

assertTest("1.3 logout.php exists with session clearing & audit log", function () {
    $logoutPath = dirname(__DIR__) . '/logout.php';
    if (!file_exists($logoutPath)) {
        throw new Exception("File logout.php does not exist");
    }
    $code = file_get_contents($logoutPath);
    if (strpos($code, 'session_destroy') === false) {
        throw new Exception("logout.php missing session_destroy()");
    }
    return true;
});

// -------------------------------------------------------------
// NHÓM 2: XÁC THỰC ĐĂNG NHẬP, MẬT KHẨU & ĐĂNG XUẤT (HTTP API)
// -------------------------------------------------------------
assertTest("2.1 Admin login with '123456' succeeds and redirects to admin.php", function () use ($client) {
    $client->clearCookies();
    $res = $client->login('admin', '123456');
    if ($res['status'] !== 302) {
        throw new Exception("Expected HTTP 302, got " . $res['status']);
    }
    if (strpos($res['headers'], 'Location: admin.php') === false &&
        strpos($res['redirect_url'], 'admin.php') === false) {
        throw new Exception("Admin login did not redirect to admin.php");
    }

    // Verify audit_logs in MySQL
    $pdo = db();
    $log = $pdo->query("SELECT * FROM audit_logs WHERE username = 'admin' ORDER BY id DESC LIMIT 1")->fetch();
    if (!$log || strpos($log['action'], 'Đăng nhập thành công') === false) {
        throw new Exception("audit_logs does not record 'Đăng nhập thành công' for admin");
    }
    return true;
});

assertTest("2.2 Teacher logins (gv_toan, gv_van) succeed and write audit logs", function () use ($client) {
    $pdo = db();
    
    // Test gv_toan
    $client->clearCookies();
    $res = $client->login('gv_toan', '123456');
    if ($res['status'] !== 302) {
        throw new Exception("Expected HTTP 302 for gv_toan, got " . $res['status']);
    }
    $log = $pdo->query("SELECT * FROM audit_logs WHERE username = 'gv_toan' ORDER BY id DESC LIMIT 1")->fetch();
    if (!$log || strpos($log['action'], 'Đăng nhập thành công') === false) {
        throw new Exception("audit_logs does not record 'Đăng nhập thành công' for gv_toan");
    }

    // Test gv_van
    $client->clearCookies();
    $res = $client->login('gv_van', '123456');
    if ($res['status'] !== 302) {
        throw new Exception("Expected HTTP 302 for gv_van, got " . $res['status']);
    }
    $log = $pdo->query("SELECT * FROM audit_logs WHERE username = 'gv_van' ORDER BY id DESC LIMIT 1")->fetch();
    if (!$log || strpos($log['action'], 'Đăng nhập thành công') === false) {
        throw new Exception("audit_logs does not record 'Đăng nhập thành công' for gv_van");
    }
    return true;
});

assertTest("2.3 Wrong password and non-existent user rejected and logged", function () use ($client) {
    $pdo = db();

    // Wrong password for existing user
    $client->clearCookies();
    $res = $client->login('admin', '1234567');
    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200 (stay on login), got " . $res['status']);
    }
    $log = $pdo->query("SELECT * FROM audit_logs WHERE action LIKE '%thất bại%' ORDER BY id DESC LIMIT 1")->fetch();
    if (!$log || strpos($log['details'], 'admin') === false) {
        throw new Exception("audit_logs did not record login failure for admin");
    }

    // Non-existent user
    $client->clearCookies();
    $res = $client->login('hacker_unknown', '123456');
    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200, got " . $res['status']);
    }
    $log = $pdo->query("SELECT * FROM audit_logs WHERE action LIKE '%thất bại%' ORDER BY id DESC LIMIT 1")->fetch();
    if (!$log || strpos($log['details'], 'hacker_unknown') === false) {
        throw new Exception("audit_logs did not record login failure for hacker_unknown");
    }
    return true;
});

assertTest("2.4 Logout endpoint clears session and writes audit log", function () use ($client) {
    $client->clearCookies();
    $client->login('admin', '123456');
    $res = $client->get('logout.php');
    if ($res['status'] !== 302) {
        throw new Exception("Expected HTTP 302 redirect from logout.php, got " . $res['status']);
    }

    // Try accessing admin.php after logout -> must be blocked
    $resProtected = $client->get('admin.php');
    if ($resProtected['status'] !== 403 && $resProtected['status'] !== 302) {
        throw new Exception("Protected page still accessible after logout, got status: " . $resProtected['status']);
    }
    return true;
});

// -------------------------------------------------------------
// NHÓM 3: BẢO VỆ ROUTE QUẢN TRỊ ADMIN (admin.php)
// -------------------------------------------------------------
assertTest("3.1 Unauthenticated access to admin.php is rejected (HTTP 403/302)", function () use ($client) {
    $client->clearCookies();
    $res = $client->get('admin.php');
    if ($res['status'] !== 403 && $res['status'] !== 302) {
        throw new Exception("Expected HTTP 403 or 302, got " . $res['status']);
    }

    $pdo = db();
    $log = $pdo->query("SELECT * FROM audit_logs WHERE action = 'Truy cập trái phép bị chặn' ORDER BY id DESC LIMIT 1")->fetch();
    if (!$log) {
        throw new Exception("No audit log recorded for unauthorized admin.php access");
    }
    return true;
});

assertTest("3.2 Teacher (gv_toan) accessing GET admin.php is rejected with 403", function () use ($client) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');
    $res = $client->get('admin.php');
    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403 for teacher accessing admin.php, got " . $res['status']);
    }
    return true;
});

assertTest("3.3 Teacher (gv_toan) POST to admin.php is blocked (0 DB changes)", function () use ($client) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');

    $pdo = db();
    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();

    $res = $client->post('admin.php', [
        'action' => 'add_class',
        'name'   => 'FAKE_CLASS_99'
    ]);
    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403 on forged admin POST, got " . $res['status']);
    }

    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
    if ($beforeCount !== $afterCount) {
        throw new Exception("Forged POST by teacher modified classes table!");
    }
    return true;
});

assertTest("3.4 Admin accessing GET admin.php succeeds (HTTP 200)", function () use ($client) {
    $client->clearCookies();
    $client->login('admin', '123456');
    $res = $client->get('admin.php');
    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200 for admin on admin.php, got " . $res['status']);
    }
    return true;
});

// -------------------------------------------------------------
// NHÓM 4: BẢO VỆ ROUTE SỔ ĐIỂM (nhap_diem.php) & PHÒNG CHỐNG BOLA/IDOR
// -------------------------------------------------------------
assertTest("4.1 Unauthenticated access to nhap_diem.php redirects to login.php", function () use ($client) {
    $client->clearCookies();
    $res = $client->get('nhap_diem.php');
    if ($res['status'] !== 302 && $res['status'] !== 403) {
        throw new Exception("Expected HTTP 302 or 403, got " . $res['status']);
    }
    return true;
});

assertTest("4.2 Assigned teacher (gv_toan on 6A1 Toán) succeeds (HTTP 200)", function () use ($client) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');
    $res = $client->get('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'));
    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200 for assigned pair, got " . $res['status']);
    }
    return true;
});

assertTest("4.3 BOLA: gv_toan viewing unassigned pair (subject or class) is 403", function () use ($client) {
    $pdo = db();

    // 1. Unassigned subject (Literature)
    $client->clearCookies();
    $client->login('gv_toan', '123456');
    $res = $client->get('nhap_diem.php?class_id=1&subject=' . urlencode('Ngữ Văn'));
    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403 for unassigned subject, got " . $res['status']);
    }

    $log = $pdo->query("SELECT * FROM audit_logs WHERE action = 'Truy cập trái phép bị chặn' AND username = 'gv_toan' ORDER BY id DESC LIMIT 1")->fetch();
    if (!$log) {
        throw new Exception("Audit log did not record unauthorized gradebook inspection attempt");
    }

    // 2. Unassigned class (6A2)
    $res2 = $client->get('nhap_diem.php?class_id=2&subject=' . urlencode('Toán'));
    if ($res2['status'] !== 403) {
        throw new Exception("Expected HTTP 403 for unassigned class, got " . $res2['status']);
    }
    return true;
});

assertTest("4.4 IDOR: gv_toan tampering ?teacher_id=3 to view Văn returns 403", function () use ($client) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');
    $res = $client->get('nhap_diem.php?teacher_id=3&class_id=1&subject=' . urlencode('Ngữ Văn'));
    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403 for teacher_id tampering, got " . $res['status']);
    }
    return true;
});

assertTest("4.5 BOLA Tampering: POST blocked (403) & Admin inspects (200)", function () use ($client) {
    $pdo = db();

    // 1. Teacher attempts to forge POST save_scores to unassigned subject
    $client->clearCookies();
    $client->login('gv_toan', '123456');

    $beforeScore = $pdo->query("SELECT avg_score FROM scores WHERE student_id = 1 AND subject = 'Ngữ Văn'")->fetchColumn();

    $res = $client->post('nhap_diem.php?class_id=1&subject=' . urlencode('Ngữ Văn'), [
        'action' => 'save_scores',
        'scores' => [
            1 => ['m1' => '10', 'gk' => '10', 'ck' => '10']
        ]
    ]);

    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403 on forged score POST, got " . $res['status']);
    }

    $afterScore = $pdo->query("SELECT avg_score FROM scores WHERE student_id = 1 AND subject = 'Ngữ Văn'")->fetchColumn();
    if ($beforeScore != $afterScore) {
        throw new Exception("Forged POST by unauthorized teacher modified student score in MySQL!");
    }

    // 2. Admin direct gradebook inspection on any class/subject succeeds (HTTP 200)
    $client->clearCookies();
    $client->login('admin', '123456');
    $resAdmin = $client->get('nhap_diem.php?class_id=1&subject=' . urlencode('Ngữ Văn'));
    if ($resAdmin['status'] !== 200) {
        throw new Exception("Expected HTTP 200 for admin gradebook inspection, got " . $resAdmin['status']);
    }

    return true;
});

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
