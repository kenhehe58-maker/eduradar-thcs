<?php
/**
 * Adversarial Empirical Stress Test Suite — Milestone 2 (EduRadar)
 * 
 * Tests live HTTP attacks against Apache & MariaDB:
 * - Backdoor elimination & password verification
 * - SQL injection payloads in login fields
 * - Session fixation prevention (session_regenerate_id)
 * - Post-logout session termination & invalidation
 * - Brute-force burst handling & forensic audit logging
 * - RBAC & BOLA boundary enforcement
 * 
 * Run with:
 *   D:\xam\php\php.exe tests/challenge_m2.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';

const CLR_RESET  = "\033[0m";
const CLR_GREEN  = "\033[1;32m";
const CLR_RED    = "\033[1;31m";
const CLR_YELLOW = "\033[1;33m";
const CLR_CYAN   = "\033[1;36m";
const CLR_BOLD   = "\033[1m";

$totalPassed = 0;
$totalFailed = 0;
$findings = [];

function challenge(string $category, string $title, callable $fn): void
{
    global $totalPassed, $totalFailed, $findings;
    $label = sprintf("[%s] %s", $category, $title);
    echo CLR_CYAN . str_pad($label, 72, ".") . CLR_RESET;
    try {
        $detail = $fn();
        $totalPassed++;
        echo CLR_GREEN . " [PASS]" . CLR_RESET . PHP_EOL;
        if (!empty($detail)) {
            echo "       " . CLR_YELLOW . "Observed: " . $detail . CLR_RESET . PHP_EOL;
        }
    } catch (Throwable $e) {
        $totalFailed++;
        echo CLR_RED . " [FAIL]" . CLR_RESET . PHP_EOL;
        echo "       " . CLR_RED . "Error: " . $e->getMessage() . CLR_RESET . PHP_EOL;
        $findings[] = [
            'category' => $category,
            'title'    => $title,
            'error'    => $e->getMessage()
        ];
    }
}

/**
 * Advanced HTTP Client for Adversarial Session & Auth Testing
 */
class AdversarialClient
{
    private string $baseUrl;
    private array $cookies = [];

    public function __construct(string $baseUrl = 'http://localhost/my-web-project')
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function setCookie(string $name, string $value): void
    {
        $this->cookies[$name] = $value;
    }

    public function getCookie(string $name): ?string
    {
        return $this->cookies[$name] ?? null;
    }

    public function clearCookies(): void
    {
        $this->cookies = [];
    }

    public function getCookies(): array
    {
        return $this->cookies;
    }

    public function request(string $method, string $path, array $data = [], bool $followRedirects = false): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        $ch = curl_init();

        $cookieHeader = [];
        foreach ($this->cookies as $k => $v) {
            $cookieHeader[] = "$k=$v";
        }

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => $followRedirects,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 10,
        ];

        if (!empty($cookieHeader)) {
            $options[CURLOPT_COOKIE] = implode('; ', $cookieHeader);
        }

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

        // Parse Set-Cookie headers to update internal cookie store
        preg_match_all('/^Set-Cookie:\s*([^;=]+)=([^;]*)/mi', $headers, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $cName = trim($match[1]);
            $cVal = trim($match[2]);
            if ($cVal === '' || $cVal === 'deleted') {
                unset($this->cookies[$cName]);
            } else {
                $this->cookies[$cName] = $cVal;
            }
        }

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

echo PHP_EOL . CLR_BOLD . CLR_YELLOW . "==========================================================================" . CLR_RESET . PHP_EOL;
echo CLR_BOLD . CLR_YELLOW . "  EduRadar Milestone 2: Adversarial Empirical Authentication Challenge" . CLR_RESET . PHP_EOL;
echo CLR_BOLD . CLR_YELLOW . "==========================================================================" . CLR_RESET . PHP_EOL . PHP_EOL;

$pdo = db();

// =========================================================================
// SECTION 1: BACKDOOR BYPASS & PASSWORD VERIFICATION ROBUSTNESS
// =========================================================================
echo CLR_BOLD . "--- SECTION 1: Backdoor Elimination & Password Verification ---" . CLR_RESET . PHP_EOL;

challenge("BACKDOOR", "1.1 Login with '123456' on non-existent accounts fails (5 varied names)", function () {
    $client = new AdversarialClient();
    $fakeUsers = ['ghost_user_1', 'admin_fake', 'teacher_unregistered', 'root', 'superadmin'];
    foreach ($fakeUsers as $fakeUser) {
        $client->clearCookies();
        $res = $client->login($fakeUser, '123456');
        if ($res['status'] !== 200) {
            throw new Exception("Expected HTTP 200 for user '$fakeUser', got " . $res['status']);
        }
        if (strpos($res['body'], 'không chính xác') === false) {
            throw new Exception("Error message missing for fake user '$fakeUser'");
        }
        if ($client->getCookie('PHPSESSID') !== null) {
            // Check if this cookie has access to admin.php
            $chk = $client->get('admin.php');
            if ($chk['status'] === 200) {
                throw new Exception("Fake user '$fakeUser' gained access to admin.php!");
            }
        }
    }
    return "All 5 non-existent accounts rejected with HTTP 200";
});

challenge("BACKDOOR", "1.2 Audit logs record exact fake usernames for all failed attempts", function () use ($pdo) {
    $stmt = $pdo->prepare("SELECT details FROM audit_logs WHERE action = 'Đăng nhập thất bại' AND details LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute(['%ghost_user_1%']);
    $log = $stmt->fetch();
    if (!$log) {
        throw new Exception("No audit log entry found for 'ghost_user_1'");
    }
    return "Audit log entry verified: " . $log['details'];
});

challenge("BACKDOOR", "1.3 User with modified/different password hash rejects '123456'", function () use ($pdo) {
    $client = new AdversarialClient();
    $testUser = 'test_hash_user_' . bin2hex(random_bytes(3));
    $customPass = 'SecretP@ssword2026!';
    $customHash = password_hash($customPass, PASSWORD_BCRYPT);

    // Insert temporary user
    $ins = $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, 'Test Challenger', 'teacher')");
    $ins->execute([$testUser, $customHash]);
    $newId = (int)$pdo->lastInsertId();

    try {
        // Attempt login with backdoor password '123456' -> MUST FAIL
        $client->clearCookies();
        $res1 = $client->login($testUser, '123456');
        if ($res1['status'] !== 200 || strpos($res1['body'], 'không chính xác') === false) {
            throw new Exception("Backdoor allowed login with '123456' on custom hash user!");
        }

        // Attempt login with wrong password -> MUST FAIL
        $res2 = $client->login($testUser, 'WrongPassword999');
        if ($res2['status'] !== 200 || strpos($res2['body'], 'không chính xác') === false) {
            throw new Exception("Wrong password allowed login!");
        }

        // Attempt login with real custom password -> MUST SUCCEED (302)
        $res3 = $client->login($testUser, $customPass);
        if ($res3['status'] !== 302) {
            throw new Exception("Legitimate custom password failed to login, got status: " . $res3['status']);
        }
    } finally {
        $del = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $del->execute([$newId]);
    }
    return "Custom hash account strictly verified: '123456' rejected, custom pass accepted";
});

challenge("BACKDOOR", "1.4 Seed accounts reject dictionary of wrong passwords", function () {
    $client = new AdversarialClient();
    $dictionary = [
        'admin'   => ['1234567', '12345', 'admin', 'password', 'Admin123', '123456 '],
        'gv_toan' => ['1234567', 'toan', 'toan123', 'toan@123', '123456_toan'],
        'gv_van'  => ['1234567', 'van', 'van123', 'nguvan', '123456_van']
    ];

    $testedCount = 0;
    foreach ($dictionary as $u => $passwords) {
        foreach ($passwords as $p) {
            $client->clearCookies();
            $res = $client->login($u, $p);
            if ($res['status'] !== 200 || strpos($res['body'], 'không chính xác') === false) {
                throw new Exception("User '$u' unexpectedly accepted wrong password '$p'!");
            }
            $testedCount++;
        }
    }
    return "All $testedCount dictionary attack attempts were rejected";
});

// =========================================================================
// SECTION 2: SQL INJECTION & INPUT FUZZING ATTACKS
// =========================================================================
echo PHP_EOL . CLR_BOLD . "--- SECTION 2: SQL Injection & Input Fuzzing against login.php ---" . CLR_RESET . PHP_EOL;

challenge("SQL_INJECTION", "2.1 Authentication bypass SQLi payloads in username field fail", function () use ($pdo) {
    $client = new AdversarialClient();
    $payloads = [
        "' OR '1'='1",
        "' OR 1=1 -- ",
        "admin' --",
        "admin' #",
        "admin'/*",
        "' OR ''='",
        "\\'",
        "admin' OR '1'='1' -- ",
        "' UNION SELECT 1, 'admin', 'hash', 'Admin', 'admin', NOW() -- "
    ];

    foreach ($payloads as $sqli) {
        $client->clearCookies();
        $res = $client->login($sqli, '123456');
        if ($res['status'] !== 200) {
            throw new Exception("SQLi payload '$sqli' returned unexpected HTTP status: " . $res['status']);
        }
        if (strpos($res['body'], 'Fatal error') !== false || strpos($res['body'], 'PDOException') !== false) {
            throw new Exception("SQLi payload '$sqli' triggered unhandled database exception in login.php!");
        }

        // Verify no session was created that can access admin.php
        $chk = $client->get('admin.php');
        if ($chk['status'] === 200) {
            throw new Exception("SQLi payload '$sqli' bypassed authentication to admin.php!");
        }
    }
    return count($payloads) . " SQL injection username payloads safely neutralized";
});

challenge("SQL_INJECTION", "2.2 SQL injection payloads in password field fail", function () {
    $client = new AdversarialClient();
    $passPayloads = [
        "' OR '1'='1",
        "123456' OR '1'='1",
        "') OR ('1'='1",
        "admin' -- ",
        "' UNION SELECT 1 --"
    ];

    foreach ($passPayloads as $p) {
        $client->clearCookies();
        $res = $client->login('admin', $p);
        if ($res['status'] !== 200 || strpos($res['body'], 'không chính xác') === false) {
            throw new Exception("Password SQLi '$p' caused unexpected behavior!");
        }
    }
    return count($passPayloads) . " SQL injection password payloads safely rejected";
});

challenge("SQL_INJECTION", "2.3 Audit log safely records SQL injection payloads via prepared statements", function () use ($pdo) {
    $testPayload = "' OR '1'='1 -- TEST_SQLI_AUDIT";
    $client = new AdversarialClient();
    $client->login($testPayload, 'dummy');

    $stmt = $pdo->prepare("SELECT username, details FROM audit_logs WHERE username = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$testPayload]);
    $log = $stmt->fetch();

    if (!$log) {
        throw new Exception("Audit log failed to record SQLi username payload");
    }
    return "Payload safely recorded without escaping corruption in audit_logs";
});

challenge("FUZZING", "2.4 Empty, whitespace-only, and 10,000-character long inputs handled cleanly", function () {
    $client = new AdversarialClient();

    // 1. Empty username & password
    $res1 = $client->login('', '');
    if ($res1['status'] !== 200 || strpos($res1['body'], 'đầy đủ thông tin') === false) {
        throw new Exception("Empty inputs did not return 'Vui lòng nhập đầy đủ thông tin!'");
    }

    // 2. Whitespace-only
    $res2 = $client->login('    ', '   ');
    if ($res2['status'] !== 200 || strpos($res2['body'], 'đầy đủ thông tin') === false) {
        throw new Exception("Whitespace-only inputs did not return validation error");
    }

    // 3. 10,000 character username
    $longUser = str_repeat('A', 10000);
    $res3 = $client->login($longUser, '123456');
    if ($res3['status'] !== 200) {
        throw new Exception("10k char username caused HTTP status: " . $res3['status']);
    }

    // 4. 10,000 character password
    $longPass = str_repeat('B', 10000);
    $res4 = $client->login('admin', $longPass);
    if ($res4['status'] !== 200) {
        throw new Exception("10k char password caused HTTP status: " . $res4['status']);
    }

    return "Extreme input lengths handled with stable HTTP 200 responses";
});

// =========================================================================
// SECTION 3: SESSION FIXATION PREVENTION
// =========================================================================
echo PHP_EOL . CLR_BOLD . "--- SECTION 3: Session Fixation Prevention ---" . CLR_RESET . PHP_EOL;

challenge("SESSION_FIXATION", "3.1 Attacker pre-set session cookie is regenerated upon admin login", function () {
    $client = new AdversarialClient();
    // Valid standard 32-character hex session ID
    $attackerSessionId = bin2hex(random_bytes(16));
    $client->setCookie('PHPSESSID', $attackerSessionId);

    // Initial check: send request with attacker session
    $initRes = $client->get('login.php');

    // Perform login with valid admin credentials while carrying the fixed session
    $loginRes = $client->login('admin', '123456');
    if ($loginRes['status'] !== 302) {
        throw new Exception("Login failed with pre-set session ID, got HTTP: " . $loginRes['status']);
    }

    $newSessionId = $client->getCookie('PHPSESSID');
    if ($newSessionId === null) {
        throw new Exception("No PHPSESSID cookie issued upon successful login!");
    }
    if ($newSessionId === $attackerSessionId) {
        throw new Exception("VULNERABILITY DETECTED: Session fixation possible! Session ID did not change after login ($attackerSessionId)");
    }

    // Now test if the OLD attacker session ID has admin access
    $attackerClient = new AdversarialClient();
    $attackerClient->setCookie('PHPSESSID', $attackerSessionId);
    $attackerCheck = $attackerClient->get('admin.php');

    if ($attackerCheck['status'] === 200) {
        throw new Exception("VULNERABILITY DETECTED: Old pre-auth session ID retained authenticated admin privileges!");
    }

    // Verify legitimate client with NEW session has full admin access
    $validCheck = $client->get('admin.php');
    if ($validCheck['status'] !== 200) {
        throw new Exception("Legitimate new session failed to access admin.php, got HTTP: " . $validCheck['status']);
    }

    return sprintf("Session ID rotated from [%s...] to [%s...], old ID blocked with HTTP %d",
        substr($attackerSessionId, 0, 16),
        substr($newSessionId, 0, 16),
        $attackerCheck['status']
    );
});

challenge("SESSION_FIXATION", "3.2 Session ID is regenerated upon teacher login (gv_toan)", function () {
    $client = new AdversarialClient();
    $preAuthSid = bin2hex(random_bytes(16));
    $client->setCookie('PHPSESSID', $preAuthSid);

    $res = $client->login('gv_toan', '123456');
    if ($res['status'] !== 302) {
        throw new Exception("Teacher login failed, got status: " . $res['status']);
    }

    $postAuthSid = $client->getCookie('PHPSESSID');
    if ($postAuthSid === $preAuthSid) {
        throw new Exception("Session ID not regenerated for teacher login!");
    }

    // Verify old SID cannot access nhap_diem.php
    $oldClient = new AdversarialClient();
    $oldClient->setCookie('PHPSESSID', $preAuthSid);
    $oldCheck = $oldClient->get('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'));

    if ($oldCheck['status'] === 200) {
        throw new Exception("Old session ID retained teacher privileges!");
    }

    return "Teacher session successfully regenerated on authentication";
});

// =========================================================================
// SECTION 4: POST-LOGOUT ACCESS & SESSION INVALIDATION
// =========================================================================
echo PHP_EOL . CLR_BOLD . "--- SECTION 4: Post-Logout Session Termination & Access Invalidation ---" . CLR_RESET . PHP_EOL;

challenge("POST_LOGOUT", "4.1 Admin post-logout: Old session cookie completely rejected from admin.php", function () use ($pdo) {
    $client = new AdversarialClient();
    // 1. Login as admin
    $client->login('admin', '123456');
    $adminSid = $client->getCookie('PHPSESSID');
    if (!$adminSid) {
        throw new Exception("Failed to obtain admin session ID");
    }

    // Verify admin access works before logout
    $preRes = $client->get('admin.php');
    if ($preRes['status'] !== 200) {
        throw new Exception("Pre-logout admin.php returned HTTP: " . $preRes['status']);
    }

    // 2. Perform logout
    $logoutRes = $client->get('logout.php');
    if ($logoutRes['status'] !== 302) {
        throw new Exception("logout.php did not return HTTP 302, got: " . $logoutRes['status']);
    }

    // 3. Construct an attacker client reusing the EXACT old admin session ID
    $snooperClient = new AdversarialClient();
    $snooperClient->setCookie('PHPSESSID', $adminSid);

    // GET admin.php
    $postGet = $snooperClient->get('admin.php');
    if ($postGet['status'] === 200) {
        throw new Exception("CRITICAL VULNERABILITY: admin.php still accessible via old session after logout!");
    }
    if ($postGet['status'] !== 403 && $postGet['status'] !== 302) {
        throw new Exception("Expected HTTP 403 or 302 on post-logout admin access, got: " . $postGet['status']);
    }

    // POST admin.php (batch assignment)
    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes")->fetchColumn();
    $postPost = $snooperClient->post('admin.php', [
        'action' => 'batch_assign',
        'teacher_id' => 2,
        'subject' => 'Toán',
        'class_ids' => [2]
    ]);
    if ($postPost['status'] === 200) {
        throw new Exception("CRITICAL VULNERABILITY: Forged POST on admin.php succeeded with post-logout session!");
    }

    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes")->fetchColumn();
    if ($beforeCount !== $afterCount) {
        throw new Exception("Post-logout POST modified database!");
    }

    return "Admin session destroyed: post-logout GET status " . $postGet['status'] . ", POST blocked with 0 DB changes";
});

challenge("POST_LOGOUT", "4.2 Teacher post-logout: Old session cookie rejected from nhap_diem.php & index.php", function () use ($pdo) {
    $client = new AdversarialClient();
    // Login as gv_toan
    $client->login('gv_toan', '123456');
    $teacherSid = $client->getCookie('PHPSESSID');

    // Access assigned gradebook
    $res1 = $client->get('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'));
    if ($res1['status'] !== 200) {
        throw new Exception("Pre-logout nhap_diem.php failed: " . $res1['status']);
    }

    // Logout
    $client->get('logout.php');

    // Replay attack using old session ID
    $replayClient = new AdversarialClient();
    $replayClient->setCookie('PHPSESSID', $teacherSid);

    $replayGet = $replayClient->get('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'));
    if ($replayGet['status'] === 200) {
        throw new Exception("CRITICAL VULNERABILITY: nhap_diem.php accessible after teacher logout!");
    }

    // Attempt score save with old session
    $beforeAvg = $pdo->query("SELECT avg_score FROM scores WHERE student_id = 1 AND subject = 'Toán'")->fetchColumn();
    $replayPost = $replayClient->post('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'), [
        'action' => 'save_scores',
        'scores' => [
            1 => ['m1' => '9.9', 'gk' => '9.9', 'ck' => '9.9']
        ]
    ]);

    $afterAvg = $pdo->query("SELECT avg_score FROM scores WHERE student_id = 1 AND subject = 'Toán'")->fetchColumn();
    if ($beforeAvg != $afterAvg) {
        throw new Exception("Post-logout POST modified scores in database!");
    }

    return "Teacher session destroyed: post-logout GET status " . $replayGet['status'] . ", scores unchanged";
});

challenge("POST_LOGOUT", "4.3 Audit logs verify logout event and subsequent post-logout unauthorized alerts", function () use ($pdo) {
    $logoutLog = $pdo->query("SELECT * FROM audit_logs WHERE action = 'Đăng xuất' ORDER BY id DESC LIMIT 1")->fetch();
    if (!$logoutLog) {
        throw new Exception("No audit log entry found for 'Đăng xuất'");
    }

    $blockedLog = $pdo->query("SELECT * FROM audit_logs WHERE action = 'Truy cập trái phép bị chặn' ORDER BY id DESC LIMIT 1")->fetch();
    if (!$blockedLog) {
        throw new Exception("No audit log entry found for blocked post-logout attempt");
    }

    return "Both logout action and subsequent blocked attempts recorded in audit_logs";
});

// =========================================================================
// SECTION 5: BRUTE-FORCE BURST & FORENSIC ACCOUNTABILITY
// =========================================================================
echo PHP_EOL . CLR_BOLD . "--- SECTION 5: Brute-Force Burst & Forensic Accountability ---" . CLR_RESET . PHP_EOL;

challenge("BRUTE_FORCE", "5.1 Rapid burst of 25 failed logins handled cleanly with 100% audit accuracy", function () use ($pdo) {
    $client = new AdversarialClient();
    $burstTag = 'burst_' . bin2hex(random_bytes(4));
    $burstCount = 25;

    $startTime = microtime(true);
    for ($i = 1; $i <= $burstCount; $i++) {
        $u = "victim_{$burstTag}_{$i}";
        $p = "bad_pass_{$i}";
        $res = $client->login($u, $p);
        if ($res['status'] !== 200) {
            throw new Exception("Request $i during burst returned HTTP " . $res['status']);
        }
    }
    $duration = round(microtime(true) - $startTime, 2);

    // Verify audit logs
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE action = 'Đăng nhập thất bại' AND details LIKE ?");
    $stmt->execute(["%{$burstTag}%"]);
    $loggedCount = (int)$stmt->fetchColumn();

    if ($loggedCount !== $burstCount) {
        throw new Exception("Audit log count mismatch: expected $burstCount, found $loggedCount");
    }

    // Verify system continues to accept legitimate login after burst
    $legitRes = $client->login('admin', '123456');
    if ($legitRes['status'] !== 302) {
        throw new Exception("Legitimate login failed immediately after brute-force burst!");
    }

    return sprintf("Executed %d failed login attempts in %ss; exactly %d audit records logged; legitimate login healthy",
        $burstCount, $duration, $loggedCount
    );
});

// =========================================================================
// SECTION 6: RBAC CROSS-ACCOUNT & PARAMETER TAMPERING
// =========================================================================
echo PHP_EOL . CLR_BOLD . "--- SECTION 6: RBAC Cross-Account & BOLA Adversarial Checks ---" . CLR_RESET . PHP_EOL;

challenge("RBAC_BOLA", "6.1 Teacher gv_toan blocked from all unassigned classes & subjects (403)", function () {
    $client = new AdversarialClient();
    $client->login('gv_toan', '123456');

    // 1. Unassigned subject (Ngữ Văn) in assigned class (6A1)
    $res1 = $client->get('nhap_diem.php?class_id=1&subject=' . urlencode('Ngữ Văn'));
    if ($res1['status'] !== 403) {
        throw new Exception("gv_toan permitted to inspect unassigned subject Ngữ Văn (HTTP " . $res1['status'] . ")");
    }

    // 2. Unassigned class (6A2) in assigned subject (Toán)
    $res2 = $client->get('nhap_diem.php?class_id=2&subject=' . urlencode('Toán'));
    if ($res2['status'] !== 403) {
        throw new Exception("gv_toan permitted to inspect unassigned class 6A2 (HTTP " . $res2['status'] . ")");
    }

    // 3. Arbitrary unassigned class ID (9999) -> must be 403
    $res3 = $client->get('nhap_diem.php?class_id=9999&subject=' . urlencode('Toán'));
    if ($res3['status'] !== 403) {
        throw new Exception("Unassigned class_id=9999 returned HTTP " . $res3['status'] . ", expected 403");
    }

    // 4. SQL injection in subject parameter -> must be 403
    $res4 = $client->get('nhap_diem.php?class_id=1&subject=' . urlencode("Toán' OR '1'='1"));
    if ($res4['status'] !== 403) {
        throw new Exception("SQLi subject parameter returned HTTP " . $res4['status'] . ", expected 403");
    }

    // 5. Negative class_id=-1 falls back to teacher's own first class (6A1 Toán), does not leak other classes
    $res5 = $client->get('nhap_diem.php?class_id=-1&subject=' . urlencode('Toán'));
    if (strpos($res5['body'], '6A2') !== false || strpos($res5['body'], 'Ngữ Văn') !== false) {
        throw new Exception("Negative class_id leaked unassigned class/subject data!");
    }

    return "All BOLA probes blocked (403 on unassigned, safe fallback on non-positive ID)";
});

challenge("RBAC_BOLA", "6.2 Teacher gv_toan blocked from admin route GET, POST, and query actions (403)", function () use ($pdo) {
    $client = new AdversarialClient();
    $client->login('gv_toan', '123456');

    // 1. GET admin.php
    $res1 = $client->get('admin.php');
    if ($res1['status'] !== 403) {
        throw new Exception("Teacher GET admin.php returned HTTP " . $res1['status']);
    }

    // 2. GET admin.php?action=delete_assign
    $res2 = $client->get('admin.php?action=delete_assign&id=1');
    if ($res2['status'] !== 403) {
        throw new Exception("Teacher GET admin delete action returned HTTP " . $res2['status']);
    }

    // 3. POST admin.php?action=import_students
    $res3 = $client->post('admin.php', ['action' => 'import_students', 'class_id' => 1]);
    if ($res3['status'] !== 403) {
        throw new Exception("Teacher POST admin import returned HTTP " . $res3['status']);
    }

    return "All admin access attempts by teacher strictly rejected with HTTP 403";
});

challenge("RBAC_BOLA", "6.3 Admin retains full legitimate inspection across all classes and subjects", function () {
    $client = new AdversarialClient();
    $client->login('admin', '123456');

    // Inspect class 1 Toán
    $res1 = $client->get('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'));
    if ($res1['status'] !== 200) {
        throw new Exception("Admin inspecting 6A1 Toán returned HTTP " . $res1['status']);
    }

    // Inspect class 1 Ngữ Văn
    $res2 = $client->get('nhap_diem.php?class_id=1&subject=' . urlencode('Ngữ Văn'));
    if ($res2['status'] !== 200) {
        throw new Exception("Admin inspecting 6A1 Ngữ Văn returned HTTP " . $res2['status']);
    }

    // Inspect with ?teacher_id=2
    $res3 = $client->get('nhap_diem.php?teacher_id=2&class_id=1&subject=' . urlencode('Toán'));
    if ($res3['status'] !== 200) {
        throw new Exception("Admin inspecting with teacher_id=2 returned HTTP " . $res3['status']);
    }

    return "Admin legitimately inspects all classes, subjects, and teachers (HTTP 200)";
});

// =========================================================================
// SUMMARY & EXIT
// =========================================================================
echo PHP_EOL . CLR_BOLD . CLR_YELLOW . "==========================================================================" . CLR_RESET . PHP_EOL;
echo sprintf("  Total Challenges: %d | Passed: %s%d%s | Failed: %s%d%s",
    $totalPassed + $totalFailed,
    CLR_GREEN, $totalPassed, CLR_RESET,
    ($totalFailed > 0 ? CLR_RED : CLR_GREEN), $totalFailed, CLR_RESET
) . PHP_EOL;
echo CLR_BOLD . CLR_YELLOW . "==========================================================================" . CLR_RESET . PHP_EOL . PHP_EOL;

if ($totalFailed > 0) {
    echo CLR_RED . "Vulnerabilities detected! Challenger verdict: REQUEST_CHANGES" . CLR_RESET . PHP_EOL . PHP_EOL;
    exit(1);
}

echo CLR_GREEN . "All adversarial challenges defeated. Challenger verdict: APPROVE" . CLR_RESET . PHP_EOL . PHP_EOL;
exit(0);
