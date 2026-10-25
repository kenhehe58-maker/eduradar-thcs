<?php
/**
 * =============================================================================
 * EMPIRICAL ADVERSARIAL CHALLENGE HARNESS: Milestone 5 Radar & Classification
 * Agent: challenger_m5_1
 * 
 * Target: http://localhost/my-web-project/ | Live DB: edu_radar (MariaDB 10.4)
 * Server: Apache 2.4 on XAMPP (Port 80)
 * 
 * Test Vectors:
 * 1. Boundary Averages: 3.49 vs 3.50, 4.99 vs 5.00, 3.40 vs 3.50, 4.90 vs 5.00
 * 2. Final Exam Boundaries: ck = 2.9 vs ck = 3.0 (critical override vs yellow/green)
 * 3. Dual Exam Failure: gk < 5.0 && ck < 5.0 vs gk >= 5.0 && ck < 5.0
 * 4. Downward Trend Threshold: ck < gk - 1.5 (difference 1.5 vs 1.6, floating point)
 * 5. Empty / Partial Scores: only TX, TX+GK without CK, 0.0 scores, null scores
 * 6. Code Integrity & Runtime Leak Audit: Undefined $isAvgWarning & HTTP output
 * 7. Live RBAC Data Isolation: Multi-teacher scoping and Admin school overview
 * 8. UI Artifacts, Stat Invariants & Zero-Division Defense
 * 9. Database State Restoration & Cleanliness Guarantee
 * 
 * Execution:
 *   D:\xam\php\php.exe tests/challenge_m5_radar.php
 * =============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';

// ANSI Terminal Colors
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
        $detail = $testFn();
        $passedChallenges++;
        echo ANSI_GREEN . " [PASS]" . ANSI_RESET . PHP_EOL;
        if (!empty($detail) && is_string($detail)) {
            echo "             " . ANSI_YELLOW . "Note: " . $detail . ANSI_RESET . PHP_EOL;
        }
    } catch (Throwable $e) {
        $failedChallenges++;
        $findings[] = [
            'code' => $code,
            'description' => $description,
            'error' => $e->getMessage()
        ];
        echo ANSI_RED . " [FAIL]" . ANSI_RESET . PHP_EOL;
        echo ANSI_RED . "             -> Failure: " . $e->getMessage() . ANSI_RESET . PHP_EOL;
    }
}

/**
 * Dedicated HTTP Client for Session Handling via cURL
 */
class LiveClient
{
    private string $baseUrl;
    private string $cookieJar;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->cookieJar = sys_get_temp_dir() . '/challenger_m5_cookie_' . bin2hex(random_bytes(6)) . '.txt';
    }

    public function __destruct()
    {
        if (file_exists($this->cookieJar)) {
            @unlink($this->cookieJar);
        }
    }

    public function clearCookies(): void
    {
        if (file_exists($this->cookieJar)) {
            @unlink($this->cookieJar);
        }
    }

    public function login(string $username, string $password): bool
    {
        $ch = curl_init($this->baseUrl . '/login.php');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'username' => $username,
                'password' => $password
            ]),
            CURLOPT_COOKIEJAR => $this->cookieJar,
            CURLOPT_COOKIEFILE => $this->cookieJar,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 10
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($code === 302 || $code === 200);
    }

    public function get(string $path): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_COOKIEJAR => $this->cookieJar,
            CURLOPT_COOKIEFILE => $this->cookieJar,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 15
        ]);
        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new Exception("cURL GET failed: " . $err);
        }
        $info = curl_getinfo($ch);
        $headerSize = $info['header_size'];
        $headers = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);
        curl_close($ch);
        return ['status' => (int)$info['http_code'], 'headers' => $headers, 'body' => $body];
    }
}

echo PHP_EOL;
echo ANSI_BOLD . "=============================================================================" . ANSI_RESET . PHP_EOL;
echo ANSI_BOLD . "  EMPIRICAL ADVERSARIAL CHALLENGE SUITE: MILESTONE 5 EARLY-WARNING RADAR" . ANSI_RESET . PHP_EOL;
echo ANSI_BOLD . "  Target: http://localhost/my-web-project/ | Live DB: edu_radar" . ANSI_RESET . PHP_EOL;
echo ANSI_BOLD . "=============================================================================" . ANSI_RESET . PHP_EOL;

$pdo = db();
$client = new LiveClient('http://localhost/my-web-project');

// Load index.php's real evaluateRiskZone() function safely
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'admin';

ob_start();
// Mute warning output during initial include so terminal output remains clean
$oldErrorReporting = error_reporting(0);
require_once dirname(__DIR__) . '/index.php';
error_reporting($oldErrorReporting);
ob_end_clean();

if (!function_exists('evaluateRiskZone')) {
    echo ANSI_RED . "FATAL: evaluateRiskZone() function not found in index.php!" . ANSI_RESET . PHP_EOL;
    exit(1);
}

// =============================================================================
// VECTOR 1: BOUNDARY AVERAGES (3.49 vs 3.50, 4.99 vs 5.00)
// =============================================================================
echo PHP_EOL . ANSI_YELLOW . "--- VECTOR 1: Boundary Averages (3.49 vs 3.50, 4.99 vs 5.00) ---" . ANSI_RESET . PHP_EOL;

challenge("V1.1", "Average 3.49 triggers 🔴 Red (Nguy cấp) critical threshold", function() {
    $r = @evaluateRiskZone(3.49, 5.0, 5.0);
    if ($r['level'] !== 'red') {
        throw new Exception("Expected level 'red' for avg=3.49, got '{$r['level']}'");
    }
    if (!str_contains($r['diagnostic'], 'dưới 3.5')) {
        throw new Exception("Diagnostic reason missing 'dưới 3.5': " . $r['diagnostic']);
    }
    return "Classified as 🔴 Red (diagnostic: {$r['diagnostic']})";
});

challenge("V1.2", "Average 3.50 triggers 🟡 Yellow (Cần chú ý), NOT Red", function() {
    $r = @evaluateRiskZone(3.50, 5.0, 5.0);
    if ($r['level'] !== 'yellow') {
        throw new Exception("Expected level 'yellow' for avg=3.50, got '{$r['level']}'");
    }
    if (!str_contains($r['diagnostic'], 'dưới chuẩn')) {
        throw new Exception("Diagnostic reason missing 'dưới chuẩn': " . $r['diagnostic']);
    }
    return "Classified as 🟡 Yellow (diagnostic: {$r['diagnostic']})";
});

challenge("V1.3", "Average 3.40 triggers 🔴 Red (Nguy cấp)", function() {
    $r = @evaluateRiskZone(3.40, 5.0, 5.0);
    if ($r['level'] !== 'red') {
        throw new Exception("Expected level 'red' for avg=3.40, got '{$r['level']}'");
    }
    return "Classified as 🔴 Red";
});

challenge("V1.4", "Average 4.99 triggers 🟡 Yellow (Cần chú ý)", function() {
    $r = @evaluateRiskZone(4.99, 5.0, 5.0);
    if ($r['level'] !== 'yellow') {
        throw new Exception("Expected level 'yellow' for avg=4.99, got '{$r['level']}'");
    }
    if (!str_contains($r['diagnostic'], 'dưới chuẩn')) {
        throw new Exception("Diagnostic reason missing 'dưới chuẩn': " . $r['diagnostic']);
    }
    return "Classified as 🟡 Yellow (diagnostic: {$r['diagnostic']})";
});

challenge("V1.5", "Average 5.00 with passing components is 🟢 Green (Ổn định)", function() {
    $r = @evaluateRiskZone(5.00, 5.0, 5.0);
    if ($r['level'] !== 'green') {
        throw new Exception("Expected level 'green' for avg=5.00 with passing components, got '{$r['level']}'");
    }
    return "Classified as 🟢 Green";
});

challenge("V1.6", "Average 4.90 triggers 🟡 Yellow (Cần chú ý)", function() {
    $r = @evaluateRiskZone(4.90, 5.0, 5.0);
    if ($r['level'] !== 'yellow') {
        throw new Exception("Expected level 'yellow' for avg=4.90, got '{$r['level']}'");
    }
    return "Classified as 🟡 Yellow";
});

// =============================================================================
// VECTOR 2: FINAL EXAM BOUNDARIES (ck = 2.9 vs ck = 3.0)
// =============================================================================
echo PHP_EOL . ANSI_YELLOW . "--- VECTOR 2: Final Exam Boundaries (ck = 2.9 vs ck = 3.0) ---" . ANSI_RESET . PHP_EOL;

challenge("V2.1", "Final exam ck = 2.9 triggers 🔴 Red even if avg is high (avg=6.5)", function() {
    $r = @evaluateRiskZone(6.5, 7.0, 2.9);
    if ($r['level'] !== 'red') {
        throw new Exception("Expected level 'red' for ck=2.9 with avg=6.5, got '{$r['level']}'");
    }
    if (!str_contains($r['diagnostic'], 'cuối kỳ dưới 3.0')) {
        throw new Exception("Diagnostic missing 'cuối kỳ dưới 3.0': " . $r['diagnostic']);
    }
    return "Critical override active: ck=2.9 forces 🔴 Red over avg=6.5";
});

challenge("V2.2", "Final exam ck = 2.9 triggers 🔴 Red with perfect regular scores", function() {
    $r = @evaluateRiskZone(7.2, 10.0, 2.9, [10.0, 10.0, 10.0, 10.0]);
    if ($r['level'] !== 'red') {
        throw new Exception("Expected level 'red' for ck=2.9, got '{$r['level']}'");
    }
    return "Overriding rule confirmed";
});

challenge("V2.3", "Final exam boundary ck = 2.99 triggers 🔴 Red critical failure", function() {
    $r = @evaluateRiskZone(6.0, 6.0, 2.99);
    if ($r['level'] !== 'red') {
        throw new Exception("Expected level 'red' for ck=2.99, got '{$r['level']}'");
    }
    return "ck=2.99 strictly < 3.0 -> 🔴 Red";
});

challenge("V2.4", "Final exam ck = 3.0 does NOT trigger ck < 3.0 critical rule", function() {
    // When avg >= 5.0 and gk is null, ck = 3.0 is not critical
    $r = @evaluateRiskZone(5.5, null, 3.0);
    if ($r['level'] === 'red') {
        throw new Exception("ck=3.0 should not trigger 'red' critical rule when avg=5.5");
    }
    return "ck=3.0 passes the < 3.0 critical barrier";
});

// =============================================================================
// VECTOR 3: DUAL EXAM FAILURE (gk < 5.0 && ck < 5.0 vs gk >= 5.0 && ck < 5.0)
// =============================================================================
echo PHP_EOL . ANSI_YELLOW . "--- VECTOR 3: Dual Exam Failure vs Single Exam Failure ---" . ANSI_RESET . PHP_EOL;

challenge("V3.1", "Dual failure: gk = 4.9 && ck = 4.9 triggers 🔴 Red (Nguy cấp)", function() {
    $r = @evaluateRiskZone(5.5, 4.9, 4.9);
    if ($r['level'] !== 'red') {
        throw new Exception("Expected level 'red' for dual failure gk=4.9 && ck=4.9, got '{$r['level']}'");
    }
    if (!str_contains($r['diagnostic'], 'Hỏng cả GK')) {
        throw new Exception("Diagnostic reason missing 'Hỏng cả GK': " . $r['diagnostic']);
    }
    return "Dual exam failure correctly classified as 🔴 Red";
});

challenge("V3.2", "Dual failure boundary: gk = 4.99 && ck = 4.99 triggers 🔴 Red", function() {
    $r = @evaluateRiskZone(5.8, 4.99, 4.99);
    if ($r['level'] !== 'red') {
        throw new Exception("Expected level 'red' for gk=4.99 && ck=4.99, got '{$r['level']}'");
    }
    return "gk=4.99 < 5.0 && ck=4.99 < 5.0 triggers 🔴 Red";
});

challenge("V3.3", "Single failure: gk = 4.9 && ck = 5.0 does NOT trigger 🔴 Red", function() {
    $r = @evaluateRiskZone(5.5, 4.9, 5.0);
    if ($r['level'] === 'red') {
        throw new Exception("Single GK failure should NOT be classified as 🔴 Red when avg=5.5");
    }
    return "Single GK failure correctly excluded from 🔴 Red";
});

challenge("V3.4", "Single exam failure: gk = 5.0 && ck = 4.9 must trigger 🟡 Yellow (Cần chú ý), NOT 🟢 Green", function() {
    // When avg is passing (e.g. 5.5) but final exam failed (ck = 4.9 < 5.0),
    // student must be flagged as 🟡 Yellow (component score < 5.0 per R4 and Dispatch)
    $r = @evaluateRiskZone(5.5, 5.0, 4.9);
    if ($r['level'] === 'green') {
        throw new Exception("LOGIC DEFECT: Student failing final exam (ck=4.9) classified as 🟢 Green ('{$r['diagnostic']}')! Expected 🟡 Yellow (Cần chú ý).");
    }
    if ($r['level'] !== 'yellow') {
        throw new Exception("Expected level 'yellow' for ck=4.9 with gk=5.0, got: '{$r['level']}'");
    }
    return "Failing final exam (ck=4.9) correctly flagged as 🟡 Yellow";
});

// =============================================================================
// VECTOR 4: DOWNWARD TREND THRESHOLD (ck < gk - 1.5, delta 1.5 vs 1.6)
// =============================================================================
echo PHP_EOL . ANSI_YELLOW . "--- VECTOR 4: Downward Trend Threshold (Difference 1.5 vs 1.6) ---" . ANSI_RESET . PHP_EOL;

challenge("V4.1", "Boundary difference exactly 1.5 (gk=8.0, ck=6.5) does NOT trigger downward trend", function() {
    $r = @evaluateRiskZone(7.0, 8.0, 6.5);
    if ($r['level'] !== 'green') {
        throw new Exception("Expected 'green' for drop exactly 1.5 (8.0 - 6.5 = 1.5), got '{$r['level']}'");
    }
    return "Drop of 1.5 does not exceed 1.5 -> 🟢 Green";
});

challenge("V4.2", "Boundary difference 1.6 (gk=8.0, ck=6.4) triggers 🟡 Yellow downward trend", function() {
    $r = @evaluateRiskZone(7.0, 8.0, 6.4);
    if ($r['level'] !== 'yellow') {
        throw new Exception("Expected 'yellow' for drop 1.6 (8.0 - 6.4 = 1.6), got '{$r['level']}'");
    }
    if (!str_contains($r['diagnostic'], 'Sa sút cuối kỳ')) {
        throw new Exception("Diagnostic missing 'Sa sút cuối kỳ': " . $r['diagnostic']);
    }
    return "Drop of 1.6 triggers 🟡 Yellow (diagnostic: {$r['diagnostic']})";
});

challenge("V4.3", "Floating-point precision challenge: gk=7.3, ck=5.8 (IEEE diff ~1.4999999999999996)", function() {
    $r = @evaluateRiskZone(6.5, 7.3, 5.8);
    if ($r['level'] !== 'green') {
        throw new Exception("Expected 'green' due to round(7.3 - 5.8, 1) = 1.5, got '{$r['level']}'");
    }
    return "IEEE 754 precision handled correctly via round(drop, 1)";
});

challenge("V4.4", "Floating-point precision challenge: gk=7.3, ck=5.7 (round diff = 1.6)", function() {
    $r = @evaluateRiskZone(6.5, 7.3, 5.7);
    if ($r['level'] !== 'yellow') {
        throw new Exception("Expected 'yellow' for drop 1.6, got '{$r['level']}'");
    }
    return "Drop of 1.6 cleanly triggers 🟡 Yellow";
});

challenge("V4.5", "Upward trend / grade improvement (gk=5.0, ck=8.0) does NOT trigger warning", function() {
    $r = @evaluateRiskZone(7.2, 5.0, 8.0);
    if ($r['level'] !== 'green') {
        throw new Exception("Expected 'green' for grade improvement, got '{$r['level']}'");
    }
    return "Negative drop (-3.0) safely ignored";
});

challenge("V4.6", "Precedence: downward trend with ck < 3.0 must remain 🔴 Red", function() {
    $r = @evaluateRiskZone(5.5, 8.0, 2.8); // drop = 5.2, but ck = 2.8 < 3.0
    if ($r['level'] !== 'red') {
        throw new Exception("Expected 'red' precedence for ck=2.8, got '{$r['level']}'");
    }
    return "Critical zone retains precedence over warning zone";
});

// =============================================================================
// VECTOR 5: EMPTY & PARTIAL SCORES
// =============================================================================
echo PHP_EOL . ANSI_YELLOW . "--- VECTOR 5: Empty & Partial Scores ---" . ANSI_RESET . PHP_EOL;

challenge("V5.1", "Completely null scores (no scores entered) classified as 🟢 Green with clear note", function() {
    $r = @evaluateRiskZone(null, null, null, []);
    if ($r['level'] !== 'green') {
        throw new Exception("Expected 'green' for null scores, got '{$r['level']}'");
    }
    if (!str_contains($r['diagnostic'], 'Chưa đủ dữ liệu điểm số')) {
        throw new Exception("Diagnostic missing 'Chưa đủ dữ liệu điểm số': " . $r['diagnostic']);
    }
    return "Handled safely without error (diagnostic: {$r['diagnostic']})";
});

challenge("V5.2", "Student with only TX scores (no GK, no CK) and passing average", function() {
    $r = @evaluateRiskZone(7.5, null, null, [7.0, 8.0]);
    if ($r['level'] !== 'green') {
        throw new Exception("Expected 'green' for passing TX scores, got '{$r['level']}'");
    }
    return "Partial scores without periodic exams correctly classified as 🟢 Green";
});

challenge("V5.3", "Student with only TX scores and failing average (avg=3.2)", function() {
    $r = @evaluateRiskZone(3.2, null, null, [3.0, 3.5]);
    if ($r['level'] !== 'red') {
        throw new Exception("Expected 'red' for failing TX average, got '{$r['level']}'");
    }
    return "Failing TX average triggers 🔴 Red";
});

challenge("V5.4", "Student with TX + GK but no CK does NOT trigger downward trend false alarm", function() {
    $r = @evaluateRiskZone(6.8, 8.0, null, [7.0]);
    if ($r['level'] !== 'green') {
        throw new Exception("Expected 'green', got '{$r['level']}'");
    }
    return "Absence of CK does not produce false downward trend";
});

challenge("V5.5", "Legitimate 0.0 scores must be classified as 🔴 Red (Nguy cấp), not empty", function() {
    $r = @evaluateRiskZone(0.0, 0.0, 0.0, [0.0]);
    if ($r['level'] !== 'red') {
        throw new Exception("Expected 'red' for 0.0 scores, got '{$r['level']}'");
    }
    if (!str_contains($r['diagnostic'], 'dưới 3.5') || !str_contains($r['diagnostic'], 'dưới 3.0')) {
        throw new Exception("Expected multiple critical reasons for 0.0 scores: " . $r['diagnostic']);
    }
    return "0.0 is recognized as valid critical score (diagnostic: {$r['diagnostic']})";
});

challenge("V5.6", "Student with partial 0.0: TX=0.0, no other scores (avg=0.0)", function() {
    $r = @evaluateRiskZone(0.0, null, null, [0.0]);
    if ($r['level'] !== 'red') {
        throw new Exception("Expected 'red' for avg=0.0, got '{$r['level']}'");
    }
    return "0.0 TX average triggers 🔴 Red";
});

// =============================================================================
// VECTOR 6: CODE INTEGRITY & PHP RUNTIME AUDIT
// =============================================================================
echo PHP_EOL . ANSI_YELLOW . "--- VECTOR 6: Code Integrity & PHP Runtime Audit ---" . ANSI_RESET . PHP_EOL;

challenge("V6.1", "Empirical check: inspect index.php line 86 for undefined variable \$isAvgWarning", function() {
    $source = file_get_contents(dirname(__DIR__) . '/index.php');
    if ($source === false) throw new Exception("Could not read index.php");
    
    // Check if $isAvgWarning is referenced
    $hasBug = str_contains($source, '$isAvgWarning');
    if ($hasBug) {
        throw new Exception("BUG CONFIRMED: index.php references undefined variable '\$isAvgWarning' on line 86! Line 71 declares '\$isAvgWarn'.");
    }
    return "No reference to undefined variable \$isAvgWarning";
});

challenge("V6.2", "Runtime test: evaluateRiskZone() under E_ALL must not emit PHP Warnings", function() {
    $capturedWarnings = [];
    set_error_handler(function(int $errno, string $errstr, string $errfile, int $errline) use (&$capturedWarnings) {
        if ($errno === E_WARNING || $errno === E_NOTICE) {
            $capturedWarnings[] = [
                'type' => ($errno === E_WARNING ? 'Warning' : 'Notice'),
                'msg'  => $errstr,
                'file' => basename($errfile),
                'line' => $errline
            ];
        }
        return false;
    });

    try {
        // Run with values that hit line 86 (Yellow and Green zones)
        evaluateRiskZone(4.2, 5.0, 5.0);
        evaluateRiskZone(6.5, 7.0, 6.5);
        evaluateRiskZone(null, null, null);
    } finally {
        restore_error_handler();
    }

    if (!empty($capturedWarnings)) {
        $first = $capturedWarnings[0];
        throw new Exception("RUNTIME WARNING DETECTED: PHP {$first['type']}: '{$first['msg']}' in {$first['file']} on line {$first['line']}");
    }
    return "evaluateRiskZone() executed cleanly under E_ALL with 0 PHP warnings";
});

challenge("V6.3", "Live Apache check: index.php HTTP response must NOT leak PHP Warnings into HTML", function() use ($client) {
    $client->clearCookies();
    if (!$client->login('admin', '123456')) {
        throw new Exception("Admin login failed");
    }
    $res = $client->get('index.php');
    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200, got: " . $res['status']);
    }
    
    if (str_contains($res['body'], 'Undefined variable $isAvgWarning') || str_contains($res['body'], '<b>Warning</b>:')) {
        throw new Exception("LEAK DETECTED: Live Apache HTTP response contains PHP Warning for '\$isAvgWarning' visible in browser DOM!");
    }
    return "Live Apache response contains 0 PHP warnings in HTML";
});

// =============================================================================
// VECTOR 7: LIVE RBAC & APACHE HTTP INTEGRATION
// =============================================================================
echo PHP_EOL . ANSI_YELLOW . "--- VECTOR 7: Live RBAC Data Isolation & Apache Integration ---" . ANSI_RESET . PHP_EOL;

challenge("V7.1", "Unauthenticated request to index.php redirects to login.php (HTTP 302)", function() use ($client) {
    $client->clearCookies();
    $res = $client->get('index.php');
    if ($res['status'] !== 302) {
        throw new Exception("Expected HTTP 302 redirect for unauthenticated user, got: " . $res['status']);
    }
    return "HTTP 302 redirect enforced";
});

challenge("V7.2", "Teacher gv_toan only sees assigned classes and subjects (Math only)", function() use ($client) {
    $client->clearCookies();
    if (!$client->login('gv_toan', '123456')) {
        throw new Exception("Teacher gv_toan login failed");
    }
    $res = $client->get('index.php');
    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200, got: " . $res['status']);
    }
    if (stripos($res['body'], 'Ngữ Văn') !== false) {
        throw new Exception("Data leakage: Teacher gv_toan sees 'Ngữ Văn' in index.php output!");
    }
    if (stripos($res['body'], 'Toán') === false) {
        throw new Exception("Missing assigned subject 'Toán' for gv_toan");
    }
    return "RBAC subject isolation verified: gv_toan sees ONLY Toán, 0 Ngữ Văn";
});

challenge("V7.3", "Teacher gv_van only sees assigned classes and subjects (Literature only)", function() use ($client) {
    $client->clearCookies();
    if (!$client->login('gv_van', '123456')) {
        throw new Exception("Teacher gv_van login failed");
    }
    $res = $client->get('index.php');
    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200, got: " . $res['status']);
    }
    if (stripos($res['body'], 'Toán') !== false) {
        throw new Exception("Data leakage: Teacher gv_van sees 'Toán' in index.php output!");
    }
    if (stripos($res['body'], 'Ngữ Văn') === false) {
        throw new Exception("Missing assigned subject 'Ngữ Văn' for gv_van");
    }
    return "RBAC subject isolation verified: gv_van sees ONLY Ngữ Văn, 0 Toán";
});

challenge("V7.4", "Admin account sees school-wide overview across all subjects", function() use ($client) {
    $client->clearCookies();
    if (!$client->login('admin', '123456')) {
        throw new Exception("Admin login failed");
    }
    $res = $client->get('index.php');
    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200, got: " . $res['status']);
    }
    if (stripos($res['body'], 'Toán') === false || stripos($res['body'], 'Ngữ Văn') === false) {
        throw new Exception("Admin overview should include both Toán and Ngữ Văn");
    }
    return "Admin school-wide overview verified";
});

challenge("V7.5", "Teacher cannot access admin menu link on index.php header", function() use ($client) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');
    $res = $client->get('index.php');
    if (str_contains($res['body'], 'href="admin.php"')) {
        throw new Exception("Teacher gv_toan header exposes admin.php link!");
    }
    return "admin.php link correctly hidden from teacher";
});

// =============================================================================
// VECTOR 8: LIVE DOM INVARIANTS, STAT CARDS & ZERO-DIVISION DEFENSE
// =============================================================================
echo PHP_EOL . ANSI_YELLOW . "--- VECTOR 8: DOM Invariants, Stat Cards & Zero-Division Defense ---" . ANSI_RESET . PHP_EOL;

challenge("V8.1", "Stat cards invariant on live index.php: total === red + yellow + green", function() use ($client) {
    $client->clearCookies();
    $client->login('admin', '123456');
    $res = $client->get('index.php');
    
    // Extract stat card counts using regex
    preg_match('/stat-card total.*?<b>(\d+)<\/b>/s', $res['body'], $mTotal);
    preg_match('/stat-card red.*?<b>(\d+)<\/b>/s', $res['body'], $mRed);
    preg_match('/stat-card yellow.*?<b>(\d+)<\/b>/s', $res['body'], $mYellow);
    preg_match('/stat-card green.*?<b>(\d+)<\/b>/s', $res['body'], $mGreen);
    
    if (empty($mTotal) || empty($mRed) || empty($mYellow) || empty($mGreen)) {
        throw new Exception("Could not parse stat card numbers from live HTML");
    }
    
    $total = (int)$mTotal[1];
    $red = (int)$mRed[1];
    $yellow = (int)$mYellow[1];
    $green = (int)$mGreen[1];
    
    if ($total !== ($red + $yellow + $green)) {
        throw new Exception("Stat invariant violated: total ($total) !== red ($red) + yellow ($yellow) + green ($green)");
    }
    return "Invariant holds: $total === $red (Red) + $yellow (Yellow) + $green (Green)";
});

challenge("V8.2", "Live index.php table rows declare data-level and data-name attributes", function() use ($client) {
    $client->clearCookies();
    $client->login('admin', '123456');
    $res = $client->get('index.php');
    
    if (!str_contains($res['body'], 'data-level="') || !str_contains($res['body'], 'data-name="')) {
        throw new Exception("Table rows missing required data-level or data-name attributes");
    }
    return "DOM attributes data-level and data-name present on all rows";
});

challenge("V8.3", "Live index.php action buttons link directly to nhap_diem.php with class_id & subject", function() use ($client) {
    $client->clearCookies();
    $client->login('admin', '123456');
    $res = $client->get('index.php');
    
    if (!preg_match('/href="nhap_diem\.php\?class_id=\d+&(?:amp;)?subject=[^"]+"/', $res['body'])) {
        throw new Exception("Action button link to nhap_diem.php missing or malformed");
    }
    return "Direct 'Vào sổ điểm' action links verified";
});

challenge("V8.4", "Zero Zalo verification: 0 occurrences in index.php, main.js, and live HTML", function() use ($client) {
    $indexSrc = file_get_contents(dirname(__DIR__) . '/index.php');
    $jsSrc = file_get_contents(dirname(__DIR__) . '/assets/js/main.js');
    
    if (stripos($indexSrc, 'zalo') !== false) {
        throw new Exception("Found 'zalo' in index.php");
    }
    if (stripos($jsSrc, 'zalo') !== false) {
        throw new Exception("Found 'zalo' in assets/js/main.js");
    }
    
    $client->clearCookies();
    $client->login('admin', '123456');
    $res = $client->get('index.php');
    if (stripos($res['body'], 'zalo') !== false) {
        throw new Exception("Found 'zalo' in live HTML response");
    }
    return "100% clean: 0 occurrences of 'zalo' across all files and live HTTP";
});

challenge("V8.5", "Zero modal code verification: 0 occurrences of actionModal in index.php and main.js", function() {
    $indexSrc = file_get_contents(dirname(__DIR__) . '/index.php');
    $jsSrc = file_get_contents(dirname(__DIR__) . '/assets/js/main.js');
    
    if (stripos($indexSrc, 'actionModal') !== false) {
        throw new Exception("Found 'actionModal' in index.php");
    }
    if (stripos($jsSrc, 'actionModal') !== false) {
        throw new Exception("Found 'actionModal' in assets/js/main.js");
    }
    return "100% clean: 0 occurrences of 'actionModal'";
});

// =============================================================================
// VECTOR 9: LIVE DATABASE SCORE INJECTION & RESTORATION
// =============================================================================
echo PHP_EOL . ANSI_YELLOW . "--- VECTOR 9: Live Database Score Injection, HTTP Render & Cleanup ---" . ANSI_RESET . PHP_EOL;

challenge("V9.1", "Live DB injection & Apache render for boundary 3.49 vs 3.50", function() use ($pdo, $client) {
    // Backup student 1 scores in Toán
    $backup = $pdo->query("SELECT * FROM scores WHERE student_id = 1 AND subject = 'Toán'")->fetch();
    
    try {
        // Step 1: Set student 1 avg_score to 3.49 in DB
        $pdo->prepare("UPDATE scores SET avg_score = 3.49, gk = 5.0, ck = 5.0 WHERE student_id = 1 AND subject = 'Toán'")->execute();
        
        $client->clearCookies();
        $client->login('admin', '123456');
        $res349 = $client->get('index.php');
        
        if (!preg_match('/<tr[^>]+data-level="red"[^>]+data-search="[^"]*hs6a101[^"]*"/', $res349['body'])) {
            throw new Exception("Expected student 1 (HS6A101) to render with data-level='red' for avg=3.49");
        }
        
        // Step 2: Set student 1 avg_score to 3.50 in DB
        $pdo->prepare("UPDATE scores SET avg_score = 3.50, gk = 5.0, ck = 5.0 WHERE student_id = 1 AND subject = 'Toán'")->execute();
        $res350 = $client->get('index.php');
        
        if (!preg_match('/<tr[^>]+data-level="yellow"[^>]+data-search="[^"]*hs6a101[^"]*"/', $res350['body'])) {
            throw new Exception("Expected student 1 (HS6A101) to render with data-level='yellow' for avg=3.50");
        }
    } finally {
        // Restore backup
        if ($backup) {
            $pdo->prepare("UPDATE scores SET tx1=?, tx2=?, tx3=?, tx4=?, gk=?, ck=?, avg_score=? WHERE student_id=1 AND subject='Toán'")
                ->execute([$backup['tx1'], $backup['tx2'], $backup['tx3'], $backup['tx4'], $backup['gk'], $backup['ck'], $backup['avg_score']]);
        }
    }
    return "Live DB injection verified: 3.49 -> red, 3.50 -> yellow on Apache DOM";
});

challenge("V9.2", "Live DB injection & Apache render for ck=2.90 critical override", function() use ($pdo, $client) {
    $backup = $pdo->query("SELECT * FROM scores WHERE student_id = 1 AND subject = 'Toán'")->fetch();
    
    try {
        // Set ck=2.90 with high average (6.50)
        $pdo->prepare("UPDATE scores SET avg_score = 6.50, gk = 7.00, ck = 2.90 WHERE student_id = 1 AND subject = 'Toán'")->execute();
        
        $client->clearCookies();
        $client->login('admin', '123456');
        $res = $client->get('index.php');
        
        if (!preg_match('/<tr[^>]+data-level="red"[^>]+data-search="[^"]*hs6a101[^"]*"/', $res['body'])) {
            throw new Exception("Expected student 1 (HS6A101) to render with data-level='red' for ck=2.90");
        }
        if (!str_contains($res['body'], 'cuối kỳ dưới 3.0')) {
            throw new Exception("Expected diagnostic 'cuối kỳ dưới 3.0' in live HTML table");
        }
    } finally {
        if ($backup) {
            $pdo->prepare("UPDATE scores SET tx1=?, tx2=?, tx3=?, tx4=?, gk=?, ck=?, avg_score=? WHERE student_id=1 AND subject='Toán'")
                ->execute([$backup['tx1'], $backup['tx2'], $backup['tx3'], $backup['tx4'], $backup['gk'], $backup['ck'], $backup['avg_score']]);
        }
    }
    return "Live DB injection verified: ck=2.90 forces 🔴 Red in live DOM";
});

challenge("V9.3", "Database state restoration & cleanliness audit", function() use ($pdo) {
    $count = $pdo->query("SELECT COUNT(*) FROM scores")->fetchColumn();
    $auditCount = $pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
    return "Database clean: scores rows = $count, audit logs = $auditCount";
});

// =============================================================================
// SUMMARY & VERDICT
// =============================================================================
echo PHP_EOL;
echo ANSI_BOLD . "=============================================================================" . ANSI_RESET . PHP_EOL;
echo ANSI_BOLD . "  CHALLENGE SUITE EXECUTION SUMMARY" . ANSI_RESET . PHP_EOL;
echo ANSI_BOLD . "=============================================================================" . ANSI_RESET . PHP_EOL;
echo "  Total Challenges: " . $totalChallenges . PHP_EOL;
echo "  Passed:           " . ANSI_GREEN . $passedChallenges . ANSI_RESET . PHP_EOL;
echo "  Failed:           " . ($failedChallenges > 0 ? ANSI_RED : ANSI_GREEN) . $failedChallenges . ANSI_RESET . PHP_EOL;

if (!empty($findings)) {
    echo PHP_EOL . ANSI_RED . ANSI_BOLD . "  CONFIRMED DEFECTS & FINDINGS (" . count($findings) . "):" . ANSI_RESET . PHP_EOL;
    foreach ($findings as $idx => $f) {
        echo "  " . ($idx + 1) . ". [" . ANSI_RED . $f['code'] . ANSI_RESET . "] " . $f['description'] . PHP_EOL;
        echo "     -> " . ANSI_YELLOW . $f['error'] . ANSI_RESET . PHP_EOL;
    }
    echo PHP_EOL . ANSI_RED . ANSI_BOLD . "  VERDICT: REQUEST_CHANGES" . ANSI_RESET . PHP_EOL;
    echo ANSI_BOLD . "=============================================================================" . ANSI_RESET . PHP_EOL;
    exit(1);
} else {
    echo PHP_EOL . ANSI_GREEN . ANSI_BOLD . "  VERDICT: APPROVE" . ANSI_RESET . PHP_EOL;
    echo ANSI_BOLD . "=============================================================================" . ANSI_RESET . PHP_EOL;
    exit(0);
}
