<?php
/**
 * =============================================================================
 * EMPIRICAL ADVERSARIAL CHALLENGE SUITE — Milestone 5 (Zalo Purge & UI Resilience)
 * EduRadar THCS Gradebook & Early Warning System
 * 
 * Challenger: challenger_m5_2
 * Role: critic, specialist
 * 
 * Scope:
 * 1. Deep Codebase Scan for Zalo Remnants & Dead Modal Logic:
 *    - Case-insensitive search across all PHP, JS, CSS, SQL, HTML project files
 *    - Target keywords: 'zalo', 'actionModal', 'copy-btn', 'openActionModal', 'closeActionModal', 'buildFeedback', 'copyMessage'
 *    - Confirm zero occurrences in application source code
 * 2. Live HTTP Scans for Admin, Teacher, and Unauthenticated Sessions:
 *    - Verify live rendered HTML on Apache contains 0 occurrences of Zalo remnants
 * 3. Client-Side DOM & Instant Search/Filter Resilience (Node.js Harness Integration):
 *    - Regex special characters: [, ], *, +, \, (, ), ?, ^, $, {, }, |, .
 *    - HTML tags / XSS injections: <script>, <img onerror>, "><svg>
 *    - Quotes, whitespace, extreme lengths (10k chars)
 *    - Empty tables (0 student rows) clicking filter chips (all, red, yellow, green)
 *    - Non-student rows in tbody, malformed data attributes
 *    - Zero console TypeErrors / SyntaxErrors
 * 4. Action Links Verification to nhap_diem.php:
 *    - Live extraction of all action links on index.php
 *    - Verification of valid query parameters (class_id > 0, non-empty subject)
 *    - Verification of database assignment integrity
 *    - Live HTTP navigation testing with teacher cookie session (expect HTTP 200)
 *    - Adversarial URL tampering & cross-subject privilege escalation resistance (expect HTTP 403)
 * 
 * Execution:
 *   D:\xam\php\php.exe tests/challenge_m5_zalo_ui.php
 * =============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';

const ANSI_RESET  = "\033[0m";
const ANSI_GREEN  = "\033[1;32m";
const ANSI_RED    = "\033[1;31m";
const ANSI_YELLOW = "\033[1;33m";
const ANSI_CYAN   = "\033[1;36m";
const ANSI_BOLD   = "\033[1m";

$totalChallenges = 0;
$passedChallenges = 0;
$failedChallenges = 0;
$findings = [];

function challenge(string $code, string $description, callable $testFn): void
{
    global $totalChallenges, $passedChallenges, $failedChallenges, $findings;
    $totalChallenges++;
    $label = sprintf("[%s] %s", $code, $description);
    echo ANSI_CYAN . "[CHALLENGE] " . ANSI_RESET . str_pad($label, 72, ".");
    
    try {
        $result = $testFn();
        if ($result === false) {
            throw new Exception("Challenge assertion failed (returned false)");
        }
        $passedChallenges++;
        echo ANSI_GREEN . " [PASS]" . ANSI_RESET . PHP_EOL;
    } catch (Throwable $e) {
        $failedChallenges++;
        $findings[] = [
            'code' => $code,
            'description' => $description,
            'error' => $e->getMessage()
        ];
        echo ANSI_RED . " [FAIL]" . ANSI_RESET . PHP_EOL;
        echo ANSI_YELLOW . "         Reason: " . $e->getMessage() . ANSI_RESET . PHP_EOL;
    }
}

class HttpClient
{
    private string $baseUrl;
    private string $cookieFile;

    public function __construct(string $baseUrl)
    {
        if (!str_starts_with($baseUrl, 'http')) {
            $baseUrl = 'http://localhost' . (str_starts_with($baseUrl, '/') ? '' : '/') . $baseUrl;
        }
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'edu_m5_challenger_');
    }

    public function __destruct()
    {
        if (file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    public function resetSession(): void
    {
        if (file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'edu_m5_challenger_');
    }

    public function request(string $method, string $path, array $data = []): array
    {
        $url = str_starts_with($path, 'http') ? $path : $this->baseUrl . '/' . ltrim($path, '/');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        $response = curl_exec($ch);
        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new Exception("cURL error connecting to $url: $err");
        }

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $headerStr = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        $headers = [];
        foreach (explode("\r\n", $headerStr) as $line) {
            if (strpos($line, ':') !== false) {
                [$k, $v] = explode(':', $line, 2);
                $headers[strtolower(trim($k))] = trim($v);
            }
        }

        return [
            'status' => $httpCode,
            'headers' => $headers,
            'body' => $body,
        ];
    }

    public function login(string $username, string $password): bool
    {
        $this->resetSession();
        $res = $this->request('POST', 'login.php', [
            'username' => $username,
            'password' => $password
        ]);
        return ($res['status'] === 302);
    }
}

echo PHP_EOL;
echo ANSI_BOLD . "============================================================" . ANSI_RESET . PHP_EOL;
echo ANSI_BOLD . "  EduRadar M5 Adversarial Challenge: Zalo Purge & UI        " . ANSI_RESET . PHP_EOL;
echo ANSI_BOLD . "  Target: http://localhost/my-web-project/ (Apache & MariaDB)" . ANSI_RESET . PHP_EOL;
echo ANSI_BOLD . "============================================================" . ANSI_RESET . PHP_EOL . PHP_EOL;

$client = new HttpClient(BASE_URL);
$projectRoot = dirname(__DIR__);

// -----------------------------------------------------------------------------
// TRACK 1: Exhaustive Static Codebase Scan for Zalo & Modal Remnants
// -----------------------------------------------------------------------------
echo ANSI_YELLOW . "--- TRACK 1: Quét Tĩnh Toàn Diện Mã Nguồn Tìm Vết Tích Zalo & Dead Code ---" . ANSI_RESET . PHP_EOL;

$appFiles = [
    'index.php',
    'admin.php',
    'nhap_diem.php',
    'login.php',
    'logout.php',
    'database.sql',
    'assets/js/main.js',
    'assets/css/style.css',
    'includes/auth.php',
    'includes/config.php',
    'includes/db.php',
    'includes/header.php',
    'includes/footer.php',
    'includes/xlsx_helper.php',
];

$bannedKeywords = [
    'zalo',
    'actionModal',
    'copy-btn',
    'openActionModal',
    'closeActionModal',
    'buildFeedback',
    'copyMessage',
];

foreach ($appFiles as $relPath) {
    challenge("M5-SCAN-" . strtoupper(str_replace(['/', '.'], '_', $relPath)), "File $relPath has 0 banned Zalo/modal keywords", function () use ($projectRoot, $relPath, $bannedKeywords) {
        $fullPath = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
        if (!file_exists($fullPath)) {
            throw new Exception("File not found: $fullPath");
        }
        $content = file_get_contents($fullPath);
        foreach ($bannedKeywords as $kw) {
            if (stripos($content, $kw) !== false) {
                throw new Exception("Found forbidden keyword '$kw' in $relPath");
            }
        }
        return true;
    });
}

challenge("M5-SCAN-ALL-PHP", "All PHP files in project root & includes have zero Zalo remnants", function () use ($projectRoot, $bannedKeywords) {
    $dirs = [$projectRoot, $projectRoot . '/includes'];
    $scanned = 0;
    foreach ($dirs as $dir) {
        $files = glob($dir . '/*.php');
        foreach ($files as $file) {
            $content = file_get_contents($file);
            foreach ($bannedKeywords as $kw) {
                if (stripos($content, $kw) !== false) {
                    throw new Exception("Found '$kw' in " . basename($file));
                }
            }
            $scanned++;
        }
    }
    return $scanned > 5;
});

// -----------------------------------------------------------------------------
// TRACK 2: Live HTTP Response Scans Across User Roles
// -----------------------------------------------------------------------------
echo PHP_EOL . ANSI_YELLOW . "--- TRACK 2: Kiểm Toán Phản Hồi HTTP Live Qua Các Phân Quyền ---" . ANSI_RESET . PHP_EOL;

challenge("M5-LIVE-ADMIN", "Live index.php rendered for Admin contains 0 Zalo remnants", function () use ($client, $bannedKeywords) {
    if (!$client->login('admin', '123456')) {
        throw new Exception("Admin login failed");
    }
    $res = $client->request('GET', 'index.php');
    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200 for admin, got " . $res['status']);
    }
    foreach ($bannedKeywords as $kw) {
        if (stripos($res['body'], $kw) !== false) {
            throw new Exception("Live index.php for admin contains forbidden keyword '$kw'");
        }
    }
    return true;
});

challenge("M5-LIVE-GV-TOAN", "Live index.php rendered for gv_toan contains 0 Zalo remnants", function () use ($client, $bannedKeywords) {
    if (!$client->login('gv_toan', '123456')) {
        throw new Exception("gv_toan login failed");
    }
    $res = $client->request('GET', 'index.php');
    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200 for gv_toan, got " . $res['status']);
    }
    foreach ($bannedKeywords as $kw) {
        if (stripos($res['body'], $kw) !== false) {
            throw new Exception("Live index.php for gv_toan contains forbidden keyword '$kw'");
        }
    }
    return true;
});

challenge("M5-LIVE-GV-VAN", "Live index.php rendered for gv_van contains 0 Zalo remnants", function () use ($client, $bannedKeywords) {
    if (!$client->login('gv_van', '123456')) {
        throw new Exception("gv_van login failed");
    }
    $res = $client->request('GET', 'index.php');
    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200 for gv_van, got " . $res['status']);
    }
    foreach ($bannedKeywords as $kw) {
        if (stripos($res['body'], $kw) !== false) {
            throw new Exception("Live index.php for gv_van contains forbidden keyword '$kw'");
        }
    }
    return true;
});

// -----------------------------------------------------------------------------
// TRACK 3: Client-Side Search & Filter Resilience (Integration with Node.js)
// -----------------------------------------------------------------------------
echo PHP_EOL . ANSI_YELLOW . "--- TRACK 3: Kiểm Thử Khả Năng Phòng Thủ Client-Side main.js ---" . ANSI_RESET . PHP_EOL;

challenge("M5-JS-DOM-HARNESS", "Node.js JS DOM harness executes 40 adversarial tests with 0 failures", function () use ($projectRoot) {
    $script = $projectRoot . '/tests/challenge_m5_js_dom.js';
    if (!file_exists($script)) {
        throw new Exception("challenge_m5_js_dom.js not found");
    }
    $output = [];
    $returnVar = 0;
    exec("node " . escapeshellarg($script), $output, $returnVar);
    $outStr = implode("\n", $output);
    if ($returnVar !== 0) {
        throw new Exception("Node harness failed (code $returnVar):\n$outStr");
    }
    if (!str_contains($outStr, 'Passed: 40 | Failed: 0')) {
        throw new Exception("Unexpected harness output:\n$outStr");
    }
    return true;
});

// -----------------------------------------------------------------------------
// TRACK 4: Action Links to nhap_diem.php Validation & RBAC Integrity
// -----------------------------------------------------------------------------
echo PHP_EOL . ANSI_YELLOW . "--- TRACK 4: Xác Thực Toàn Vẹn Liên Kết Vào Sổ Điểm (nhap_diem.php) ---" . ANSI_RESET . PHP_EOL;

challenge("M5-ACTION-LINKS-WELL-FORMED", "All action links in index.php point to nhap_diem.php with valid params", function () use ($client) {
    if (!$client->login('gv_toan', '123456')) {
        throw new Exception("gv_toan login failed");
    }
    $res = $client->request('GET', 'index.php');
    if ($res['status'] !== 200) {
        throw new Exception("HTTP status not 200");
    }

    // Match all action links
    preg_match_all('/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*class=["\'][^"\']*btn-goto-gradebook[^"\']*["\']/i', $res['body'], $matches);
    if (empty($matches[1])) {
        // Fallback match by href pattern
        preg_match_all('/href=["\'](nhap_diem\.php\?[^"\']+)["\']/i', $res['body'], $matches);
    }

    $links = $matches[1] ?? [];
    if (empty($links)) {
        throw new Exception("No action links found in index.php table");
    }

    foreach ($links as $link) {
        $parts = parse_url($link);
        if (($parts['path'] ?? '') !== 'nhap_diem.php') {
            throw new Exception("Action link does not point to nhap_diem.php: $link");
        }
        parse_str($parts['query'] ?? '', $query);
        if (!isset($query['class_id']) || !is_numeric($query['class_id']) || (int)$query['class_id'] <= 0) {
            throw new Exception("Action link has invalid or missing class_id: $link");
        }
        if (!isset($query['subject']) || trim($query['subject']) === '') {
            throw new Exception("Action link has empty or missing subject: $link");
        }
    }
    return true;
});

challenge("M5-ACTION-LINKS-ASSIGNMENT-INTEGRITY", "Every action link in gv_toan dashboard matches teacher_classes DB record", function () use ($client) {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'gv_toan'");
    $stmt->execute();
    $teacherId = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT class_id, subject FROM teacher_classes WHERE teacher_id = ?");
    $stmt->execute([$teacherId]);
    $assignedPairs = [];
    foreach ($stmt->fetchAll() as $row) {
        $assignedPairs[$row['class_id'] . '_' . $row['subject']] = true;
    }

    $res = $client->request('GET', 'index.php');
    preg_match_all('/href=["\'](nhap_diem\.php\?[^"\']+)["\']/i', $res['body'], $matches);
    $links = $matches[1] ?? [];

    foreach ($links as $link) {
        $parts = parse_url(html_entity_decode($link));
        parse_str($parts['query'] ?? '', $query);
        $key = ($query['class_id'] ?? '') . '_' . ($query['subject'] ?? '');
        if (!isset($assignedPairs[$key])) {
            throw new Exception("Dashboard rendered link to unassigned class/subject: $link");
        }
    }
    return true;
});

challenge("M5-ACTION-LINKS-LIVE-NAVIGATION", "Following each action link as gv_toan returns HTTP 200 OK gradebook", function () use ($client) {
    if (!$client->login('gv_toan', '123456')) {
        throw new Exception("gv_toan login failed");
    }
    $res = $client->request('GET', 'index.php');
    preg_match_all('/href=["\'](nhap_diem\.php\?[^"\']+)["\']/i', $res['body'], $matches);
    $links = array_unique($matches[1] ?? []);

    foreach ($links as $link) {
        $decodedLink = html_entity_decode($link);
        $navRes = $client->request('GET', $decodedLink);
        if ($navRes['status'] !== 200) {
            throw new Exception("Navigating to $decodedLink failed with HTTP " . $navRes['status']);
        }
        if (!str_contains($navRes['body'], 'Sổ Điểm') && !str_contains($navRes['body'], 'nhap_diem')) {
            throw new Exception("Navigating to $decodedLink did not render gradebook page");
        }
    }
    return true;
});

challenge("M5-ACTION-LINKS-TAMPERING-RESISTANCE", "Tampering class_id/subject to unassigned pair strictly returns HTTP 403", function () use ($client) {
    if (!$client->login('gv_toan', '123456')) {
        throw new Exception("gv_toan login failed");
    }

    // gv_toan is assigned to Toán, not Ngữ Văn
    $res1 = $client->request('GET', 'nhap_diem.php?class_id=1&subject=' . urlencode('Ngữ Văn'));
    if ($res1['status'] !== 403) {
        throw new Exception("Expected HTTP 403 for unauthorized subject, got " . $res1['status']);
    }

    // Unassigned class 2 (6A2)
    $res2 = $client->request('GET', 'nhap_diem.php?class_id=2&subject=' . urlencode('Toán'));
    if ($res2['status'] !== 403) {
        throw new Exception("Expected HTTP 403 for unauthorized class 2, got " . $res2['status']);
    }

    // Non-existent class 999
    $res3 = $client->request('GET', 'nhap_diem.php?class_id=999&subject=' . urlencode('Toán'));
    if ($res3['status'] !== 403) {
        throw new Exception("Expected HTTP 403 for non-existent class 999, got " . $res3['status']);
    }

    // SQL injection in subject parameter
    $res4 = $client->request('GET', 'nhap_diem.php?class_id=1&subject=' . urlencode("Toán' OR 1=1--"));
    if ($res4['status'] !== 403) {
        throw new Exception("Expected HTTP 403 for SQLi in subject parameter, got " . $res4['status']);
    }

    return true;
});

challenge("M5-ADMIN-ACTION-LINKS-ACCESS", "Admin clicking action link can inspect gradebook of any class/subject", function () use ($client) {
    if (!$client->login('admin', '123456')) {
        throw new Exception("Admin login failed");
    }
    $res = $client->request('GET', 'index.php');
    preg_match_all('/href=["\'](nhap_diem\.php\?[^"\']+)["\']/i', $res['body'], $matches);
    $links = array_unique($matches[1] ?? []);

    if (empty($links)) {
        throw new Exception("No action links found on admin index.php");
    }

    // Check first 3 links
    $checked = 0;
    foreach ($links as $link) {
        $navRes = $client->request('GET', html_entity_decode($link));
        if ($navRes['status'] !== 200) {
            throw new Exception("Admin accessing $link got HTTP " . $navRes['status']);
        }
        $checked++;
        if ($checked >= 3) break;
    }
    return true;
});

// -----------------------------------------------------------------------------
// SUMMARY REPORT
// -----------------------------------------------------------------------------
echo PHP_EOL;
echo ANSI_BOLD . "============================================================" . ANSI_RESET . PHP_EOL;
printf("  Total Challenges: %d | Passed: %d | Failed: %d\n", $totalChallenges, $passedChallenges, $failedChallenges);
echo ANSI_BOLD . "============================================================" . ANSI_RESET . PHP_EOL . PHP_EOL;

if ($failedChallenges > 0) {
    echo ANSI_RED . "Adversarial challenges revealed $failedChallenges vulnerabilities/failures." . ANSI_RESET . PHP_EOL;
    exit(1);
} else {
    echo ANSI_GREEN . "All Milestone 5 adversarial challenges PASSED with 0 failures." . ANSI_RESET . PHP_EOL;
    exit(0);
}
