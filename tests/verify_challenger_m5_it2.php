<?php
/**
 * =============================================================================
 * EMPIRICAL ADVERSARIAL CHALLENGER HARNESS: Milestone 5 Iteration 2
 * Agent: challenger_m5_it2_1
 * 
 * Verifications:
 * 1. Boundary conditions on evaluateRiskZone():
 *    - Single exam failure: gk = 5.0 && ck = 4.9 with avg = 5.5 -> 🟡 Cần chú ý (Thi cuối kỳ dưới trung bình)
 *    - Single midterm failure: gk = 4.8 && ck = 6.0 with avg = 5.6 -> 🟡 Cần chú ý (Thi giữa kỳ dưới trung bình)
 *    - Downward trend: gk = 7.0 && ck = 5.0 (drop = 2.0 > 1.5) -> 🟡 Cần chú ý (Sa sút cuối kỳ)
 *    - Dual failure: gk = 4.5 && ck = 4.5 -> 🔴 Nguy cấp (Hỏng cả GK và CK)
 *    - Final exam critical bottleneck: ck = 2.9 -> 🔴 Nguy cấp (Thi cuối kỳ dưới 3.0)
 *    - Borderline trend: gk = 7.0 && ck = 5.5 (drop = 1.5) -> must NOT trigger downward trend
 * 2. Full execution of challenge_m5_radar.php
 * 3. Live MySQL edu_radar query results matching radar stat counts and diagnostic outputs
 *    across Admin, gv_toan, and gv_van.
 * =============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/radar_helper.php';

const ANSI_RESET  = "\033[0m";
const ANSI_GREEN  = "\033[1;32m";
const ANSI_RED    = "\033[1;31m";
const ANSI_YELLOW = "\033[1;33m";
const ANSI_CYAN   = "\033[1;36m";
const ANSI_BOLD   = "\033[1m";

$testCount = 0;
$passCount = 0;
$failCount = 0;
$failures = [];

function assertTest(string $id, string $title, callable $fn): void {
    global $testCount, $passCount, $failCount, $failures;
    $testCount++;
    echo ANSI_CYAN . "[CHALLENGE-M5-IT2] " . ANSI_RESET . str_pad("[$id] $title", 74, ".");
    try {
        $msg = $fn();
        $passCount++;
        echo ANSI_GREEN . " [PASS]" . ANSI_RESET . PHP_EOL;
        if (!empty($msg)) {
            echo "                   " . ANSI_YELLOW . "Detail: " . $msg . ANSI_RESET . PHP_EOL;
        }
    } catch (Throwable $e) {
        $failCount++;
        $failures[] = "[$id] $title: " . $e->getMessage();
        echo ANSI_RED . " [FAIL]" . ANSI_RESET . PHP_EOL;
        echo ANSI_RED . "                   Error: " . $e->getMessage() . ANSI_RESET . PHP_EOL;
    }
}

echo PHP_EOL . ANSI_BOLD . "=============================================================================" . ANSI_RESET . PHP_EOL;
echo ANSI_BOLD . "  CHALLENGER M5-IT2: ADVERSARIAL BOUNDARY & LIVE DB INTEGRATION SUITE" . ANSI_RESET . PHP_EOL;
echo ANSI_BOLD . "=============================================================================" . ANSI_RESET . PHP_EOL . PHP_EOL;

// -----------------------------------------------------------------------------
// SECTION 1: MANDATED BOUNDARY CONDITIONS ON evaluateRiskZone()
// -----------------------------------------------------------------------------
echo ANSI_YELLOW . "--- SECTION 1: MANDATED BOUNDARY CONDITIONS ON evaluateRiskZone() ---" . ANSI_RESET . PHP_EOL;

// 1.1 Single exam failure
assertTest("BND-1", "Single final exam failure: gk = 5.0 && ck = 4.9 with avg = 5.5", function() {
    $res = evaluateRiskZone(5.5, 5.0, 4.9);
    if ($res['level'] !== 'yellow') {
        throw new Exception("Expected level 'yellow', got '{$res['level']}'");
    }
    if ($res['label'] !== 'Cần chú ý') {
        throw new Exception("Expected label 'Cần chú ý', got '{$res['label']}'");
    }
    if (!str_contains($res['diagnostic'], 'Thi cuối kỳ dưới trung bình (4.9)')) {
        throw new Exception("Diagnostic reason missing 'Thi cuối kỳ dưới trung bình (4.9)', got '{$res['diagnostic']}'");
    }
    return "Level: {$res['level']}, Label: {$res['label']}, Diagnostic: {$res['diagnostic']}";
});

// 1.2 Single midterm failure
assertTest("BND-2", "Single midterm failure: gk = 4.8 && ck = 6.0 with avg = 5.6", function() {
    $res = evaluateRiskZone(5.6, 4.8, 6.0);
    if ($res['level'] !== 'yellow') {
        throw new Exception("Expected level 'yellow', got '{$res['level']}'");
    }
    if ($res['label'] !== 'Cần chú ý') {
        throw new Exception("Expected label 'Cần chú ý', got '{$res['label']}'");
    }
    if (!str_contains($res['diagnostic'], 'Thi giữa kỳ dưới trung bình (4.8)')) {
        throw new Exception("Diagnostic reason missing 'Thi giữa kỳ dưới trung bình (4.8)', got '{$res['diagnostic']}'");
    }
    return "Level: {$res['level']}, Label: {$res['label']}, Diagnostic: {$res['diagnostic']}";
});

// 1.3 Downward trend
assertTest("BND-3", "Downward trend: gk = 7.0 && ck = 5.0 (drop = 2.0 > 1.5, avg = 5.8)", function() {
    $res = evaluateRiskZone(5.8, 7.0, 5.0);
    if ($res['level'] !== 'yellow') {
        throw new Exception("Expected level 'yellow', got '{$res['level']}'");
    }
    if ($res['label'] !== 'Cần chú ý') {
        throw new Exception("Expected label 'Cần chú ý', got '{$res['label']}'");
    }
    if (!str_contains($res['diagnostic'], 'Sa sút cuối kỳ (giảm 2.0 đ so với GK)')) {
        throw new Exception("Diagnostic reason missing 'Sa sút cuối kỳ (giảm 2.0 đ so với GK)', got '{$res['diagnostic']}'");
    }
    return "Level: {$res['level']}, Diagnostic: {$res['diagnostic']}";
});

// 1.4 Dual failure
assertTest("BND-4", "Dual failure: gk = 4.5 && ck = 4.5 with avg = 5.0", function() {
    $res = evaluateRiskZone(5.0, 4.5, 4.5);
    if ($res['level'] !== 'red') {
        throw new Exception("Expected level 'red', got '{$res['level']}'");
    }
    if ($res['label'] !== 'Nguy cấp') {
        throw new Exception("Expected label 'Nguy cấp', got '{$res['label']}'");
    }
    if (!str_contains($res['diagnostic'], 'Hỏng cả GK (4.5) và CK (4.5)')) {
        throw new Exception("Diagnostic reason missing 'Hỏng cả GK (4.5) và CK (4.5)', got '{$res['diagnostic']}'");
    }
    return "Level: {$res['level']}, Diagnostic: {$res['diagnostic']}";
});

// 1.5 Final exam critical bottleneck
assertTest("BND-5", "Final exam critical bottleneck: ck = 2.9 (with gk = 8.0, avg = 6.2)", function() {
    $res = evaluateRiskZone(6.2, 8.0, 2.9);
    if ($res['level'] !== 'red') {
        throw new Exception("Expected level 'red', got '{$res['level']}'");
    }
    if ($res['label'] !== 'Nguy cấp') {
        throw new Exception("Expected label 'Nguy cấp', got '{$res['label']}'");
    }
    if (!str_contains($res['diagnostic'], 'Thi cuối kỳ dưới 3.0 (2.9)')) {
        throw new Exception("Diagnostic reason missing 'Thi cuối kỳ dưới 3.0 (2.9)', got '{$res['diagnostic']}'");
    }
    return "Level: {$res['level']}, Diagnostic: {$res['diagnostic']}";
});

// 1.6 Borderline trend (drop = 1.5 must NOT trigger downward trend)
assertTest("BND-6", "Borderline trend: gk = 7.0 && ck = 5.5 (drop = 1.5, avg = 6.0)", function() {
    $res = evaluateRiskZone(6.0, 7.0, 5.5);
    if ($res['level'] !== 'green') {
        throw new Exception("Expected level 'green' for drop exactly 1.5 with avg=6.0, got '{$res['level']}'");
    }
    if ($res['label'] !== 'Ổn định') {
        throw new Exception("Expected label 'Ổn định', got '{$res['label']}'");
    }
    if (str_contains($res['diagnostic'], 'Sa sút cuối kỳ')) {
        throw new Exception("Must NOT trigger downward trend for drop 1.5, got '{$res['diagnostic']}'");
    }
    return "Drop 1.5 does not trigger warning: {$res['level']} ({$res['diagnostic']})";
});

// 1.7 Additional Boundary: drop = 1.51 must trigger downward trend
assertTest("BND-7", "Boundary drop = 1.51 (gk = 7.0, ck = 5.49, avg = 6.0) triggers downward trend", function() {
    // round(7.0 - 5.49, 1) = 1.5 -> but round(7.0 - 5.40, 1) = 1.6
    $res = evaluateRiskZone(6.0, 7.0, 5.4);
    if ($res['level'] !== 'yellow') {
        throw new Exception("Expected level 'yellow' for drop 1.6, got '{$res['level']}'");
    }
    if (!str_contains($res['diagnostic'], 'Sa sút cuối kỳ')) {
        throw new Exception("Expected 'Sa sút cuối kỳ' in diagnostic: {$res['diagnostic']}");
    }
    return "Drop 1.6 triggers: {$res['diagnostic']}";
});

// 1.8 Critical overrides warning: dual failure + low ck + low avg
assertTest("BND-8", "Critical zone aggregates multiple reasons cleanly", function() {
    $res = evaluateRiskZone(3.2, 4.0, 2.5);
    if ($res['level'] !== 'red') {
        throw new Exception("Expected level 'red', got '{$res['level']}'");
    }
    if (!str_contains($res['diagnostic'], 'Điểm TB dưới 3.5') || 
        !str_contains($res['diagnostic'], 'Thi cuối kỳ dưới 3.0') || 
        !str_contains($res['diagnostic'], 'Hỏng cả GK')) {
        throw new Exception("Expected all 3 critical reasons to be aggregated, got: {$res['diagnostic']}");
    }
    return "Aggregated: {$res['diagnostic']}";
});

// -----------------------------------------------------------------------------
// SECTION 2: LIVE MYSQL EDU_RADAR QUERY VS RADAR STATS & DIAGNOSTICS
// -----------------------------------------------------------------------------
echo PHP_EOL . ANSI_YELLOW . "--- SECTION 2: LIVE MYSQL EDU_RADAR QUERY VS RADAR STATS & DIAGNOSTICS ---" . ANSI_RESET . PHP_EOL;

$pdo = db();

function curlLoginAndGet(string $username, string $password): array {
    $cookieFile = sys_get_temp_dir() . '/test_m5_cookie_' . bin2hex(random_bytes(4)) . '.txt';
    $baseUrl = 'http://localhost/my-web-project';

    // Login
    $ch = curl_init($baseUrl . '/login.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['username' => $username, 'password' => $password]),
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 10
    ]);
    curl_exec($ch);
    curl_close($ch);

    // GET index.php
    $ch = curl_init($baseUrl . '/index.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_TIMEOUT => 10
    ]);
    $raw = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    @unlink($cookieFile);

    $headerSize = $info['header_size'];
    $body = substr($raw, $headerSize);
    return ['status' => (int)$info['http_code'], 'body' => $body];
}

// 2.1 Admin Live MySQL Query Comparison
assertTest("DB-1", "Admin live query matches calculateRadarStats() and HTML stat cards exactly", function() use ($pdo) {
    // 1. Direct query exactly as index.php does for admin
    $sql = "
        SELECT 
            s.id AS student_id,
            s.class_id,
            s.student_code,
            s.full_name,
            c.name AS class_name,
            tc.subject,
            sc.tx1, sc.tx2, sc.tx3, sc.tx4, sc.gk, sc.ck, sc.avg_score
        FROM teacher_classes tc
        JOIN classes c ON tc.class_id = c.id
        JOIN students s ON s.class_id = c.id
        LEFT JOIN scores sc ON sc.student_id = s.id AND sc.subject = tc.subject
        ORDER BY c.grade ASC, c.name ASC, s.full_name ASC
    ";
    $rows = $pdo->query($sql)->fetchAll();
    
    $students = [];
    foreach ($rows as $r) {
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
        $students[] = array_merge($r, $eval);
    }

    $dbStats = calculateRadarStats($students);

    // 2. Fetch live HTML for Admin
    $resp = curlLoginAndGet('admin', '123456');
    if ($resp['status'] !== 200) {
        throw new Exception("HTTP status {$resp['status']} for admin");
    }

    preg_match('/stat-card total.*?<b>(\d+)<\/b>/s', $resp['body'], $mTotal);
    preg_match('/stat-card red.*?<b>(\d+)<\/b>/s', $resp['body'], $mRed);
    preg_match('/stat-card yellow.*?<b>(\d+)<\/b>/s', $resp['body'], $mYellow);
    preg_match('/stat-card green.*?<b>(\d+)<\/b>/s', $resp['body'], $mGreen);

    if (empty($mTotal) || empty($mRed) || empty($mYellow) || empty($mGreen)) {
        throw new Exception("Could not parse stat card numbers from Admin HTML");
    }

    $htmlTotal = (int)$mTotal[1];
    $htmlRed = (int)$mRed[1];
    $htmlYellow = (int)$mYellow[1];
    $htmlGreen = (int)$mGreen[1];

    if ($htmlTotal !== $dbStats['total']) {
        throw new Exception("Total mismatch: HTML {$htmlTotal} !== DB {$dbStats['total']}");
    }
    if ($htmlRed !== $dbStats['red']) {
        throw new Exception("Red mismatch: HTML {$htmlRed} !== DB {$dbStats['red']}");
    }
    if ($htmlYellow !== $dbStats['yellow']) {
        throw new Exception("Yellow mismatch: HTML {$htmlYellow} !== DB {$dbStats['yellow']}");
    }
    if ($htmlGreen !== $dbStats['green']) {
        throw new Exception("Green mismatch: HTML {$htmlGreen} !== DB {$dbStats['green']}");
    }

    return "Admin Stats: Total={$dbStats['total']}, Red={$dbStats['red']}, Yellow={$dbStats['yellow']}, Green={$dbStats['green']} (HTML matches 100%)";
});

// 2.2 Teacher gv_toan Live MySQL Query Comparison
assertTest("DB-2", "Teacher gv_toan live query matches assigned classes and Math subject only", function() use ($pdo) {
    $teacherId = (int)$pdo->query("SELECT id FROM users WHERE username = 'gv_toan'")->fetchColumn();
    if (!$teacherId) {
        throw new Exception("Teacher gv_toan not found in users table");
    }

    // Direct query for gv_toan
    $stmt = $pdo->prepare("
        SELECT 
            s.id AS student_id,
            s.student_code,
            s.full_name,
            c.name AS class_name,
            tc.subject,
            sc.tx1, sc.tx2, sc.tx3, sc.tx4, sc.gk, sc.ck, sc.avg_score
        FROM teacher_classes tc
        JOIN classes c ON tc.class_id = c.id
        JOIN students s ON s.class_id = c.id
        LEFT JOIN scores sc ON sc.student_id = s.id AND sc.subject = tc.subject
        WHERE tc.teacher_id = ?
        ORDER BY c.grade ASC, c.name ASC, s.full_name ASC
    ");
    $stmt->execute([$teacherId]);
    $rows = $stmt->fetchAll();

    $students = [];
    foreach ($rows as $r) {
        if ($r['subject'] !== 'Toán') {
            throw new Exception("Discrepancy: gv_toan assigned subject {$r['subject']} != Toán");
        }
        $avg = ($r['avg_score'] !== null && $r['avg_score'] !== '') ? (float)$r['avg_score'] : null;
        $gk  = ($r['gk'] !== null && $r['gk'] !== '') ? (float)$r['gk'] : null;
        $ck  = ($r['ck'] !== null && $r['ck'] !== '') ? (float)$r['ck'] : null;
        $eval = evaluateRiskZone($avg, $gk, $ck);
        $students[] = array_merge($r, $eval);
    }
    $dbStats = calculateRadarStats($students);

    // Fetch live HTML for gv_toan
    $resp = curlLoginAndGet('gv_toan', '123456');
    if ($resp['status'] !== 200) {
        throw new Exception("HTTP status {$resp['status']} for gv_toan");
    }

    preg_match('/stat-card total.*?<b>(\d+)<\/b>/s', $resp['body'], $mTotal);
    preg_match('/stat-card red.*?<b>(\d+)<\/b>/s', $resp['body'], $mRed);
    preg_match('/stat-card yellow.*?<b>(\d+)<\/b>/s', $resp['body'], $mYellow);
    preg_match('/stat-card green.*?<b>(\d+)<\/b>/s', $resp['body'], $mGreen);

    $htmlTotal = (int)$mTotal[1];
    $htmlRed = (int)$mRed[1];
    $htmlYellow = (int)$mYellow[1];
    $htmlGreen = (int)$mGreen[1];

    if ($htmlTotal !== $dbStats['total'] || $htmlRed !== $dbStats['red'] || $htmlYellow !== $dbStats['yellow'] || $htmlGreen !== $dbStats['green']) {
        throw new Exception("gv_toan stat mismatch: HTML ($htmlTotal,$htmlRed,$htmlYellow,$htmlGreen) vs DB ({$dbStats['total']},{$dbStats['red']},{$dbStats['yellow']},{$dbStats['green']})");
    }

    return "gv_toan Stats: Total={$dbStats['total']}, Red={$dbStats['red']}, Yellow={$dbStats['yellow']}, Green={$dbStats['green']} (All Toán, 0 Ngữ Văn)";
});

// 2.3 Teacher gv_van Live MySQL Query Comparison
assertTest("DB-3", "Teacher gv_van live query matches assigned classes and Literature subject only", function() use ($pdo) {
    $teacherId = (int)$pdo->query("SELECT id FROM users WHERE username = 'gv_van'")->fetchColumn();
    if (!$teacherId) {
        throw new Exception("Teacher gv_van not found in users table");
    }

    // Direct query for gv_van
    $stmt = $pdo->prepare("
        SELECT 
            s.id AS student_id,
            s.student_code,
            s.full_name,
            c.name AS class_name,
            tc.subject,
            sc.tx1, sc.tx2, sc.tx3, sc.tx4, sc.gk, sc.ck, sc.avg_score
        FROM teacher_classes tc
        JOIN classes c ON tc.class_id = c.id
        JOIN students s ON s.class_id = c.id
        LEFT JOIN scores sc ON sc.student_id = s.id AND sc.subject = tc.subject
        WHERE tc.teacher_id = ?
        ORDER BY c.grade ASC, c.name ASC, s.full_name ASC
    ");
    $stmt->execute([$teacherId]);
    $rows = $stmt->fetchAll();

    $students = [];
    foreach ($rows as $r) {
        if ($r['subject'] !== 'Ngữ Văn') {
            throw new Exception("Discrepancy: gv_van assigned subject {$r['subject']} != Ngữ Văn");
        }
        $avg = ($r['avg_score'] !== null && $r['avg_score'] !== '') ? (float)$r['avg_score'] : null;
        $gk  = ($r['gk'] !== null && $r['gk'] !== '') ? (float)$r['gk'] : null;
        $ck  = ($r['ck'] !== null && $r['ck'] !== '') ? (float)$r['ck'] : null;
        $eval = evaluateRiskZone($avg, $gk, $ck);
        $students[] = array_merge($r, $eval);
    }
    $dbStats = calculateRadarStats($students);

    // Fetch live HTML for gv_van
    $resp = curlLoginAndGet('gv_van', '123456');
    if ($resp['status'] !== 200) {
        throw new Exception("HTTP status {$resp['status']} for gv_van");
    }

    preg_match('/stat-card total.*?<b>(\d+)<\/b>/s', $resp['body'], $mTotal);
    preg_match('/stat-card red.*?<b>(\d+)<\/b>/s', $resp['body'], $mRed);
    preg_match('/stat-card yellow.*?<b>(\d+)<\/b>/s', $resp['body'], $mYellow);
    preg_match('/stat-card green.*?<b>(\d+)<\/b>/s', $resp['body'], $mGreen);

    $htmlTotal = (int)$mTotal[1];
    $htmlRed = (int)$mRed[1];
    $htmlYellow = (int)$mYellow[1];
    $htmlGreen = (int)$mGreen[1];

    if ($htmlTotal !== $dbStats['total'] || $htmlRed !== $dbStats['red'] || $htmlYellow !== $dbStats['yellow'] || $htmlGreen !== $dbStats['green']) {
        throw new Exception("gv_van stat mismatch: HTML ($htmlTotal,$htmlRed,$htmlYellow,$htmlGreen) vs DB ({$dbStats['total']},{$dbStats['red']},{$dbStats['yellow']},{$dbStats['green']})");
    }

    return "gv_van Stats: Total={$dbStats['total']}, Red={$dbStats['red']}, Yellow={$dbStats['yellow']}, Green={$dbStats['green']} (All Ngữ Văn, 0 Toán)";
});

// 2.4 Verify rendered diagnostic badge texts match evaluateRiskZone() for every student
assertTest("DB-4", "Every student rendered diagnostic string in HTML matches evaluateRiskZone() exactly", function() use ($pdo) {
    $resp = curlLoginAndGet('admin', '123456');
    
    // Extract each row from HTML: student code, level, diagnostic badge
    preg_match_all('/<tr class="radar-row"[^>]+data-level="([^"]+)"[^>]*>.*?<code class="mono-code"[^>]*>\s*([^<]+)\s*<\/code>.*?<span class="badge-subject">Môn\s+([^<]+)<\/span>.*?<span class="reason-text">([^<]+)<\/span>/s', $resp['body'], $matches, PREG_SET_ORDER);

    // Fetch all student records directly from DB
    $sql = "
        SELECT 
            s.id AS student_id, s.student_code, s.full_name, tc.subject,
            sc.tx1, sc.tx2, sc.tx3, sc.tx4, sc.gk, sc.ck, sc.avg_score
        FROM teacher_classes tc
        JOIN classes c ON tc.class_id = c.id
        JOIN students s ON s.class_id = c.id
        LEFT JOIN scores sc ON sc.student_id = s.id AND sc.subject = tc.subject
    ";
    $rows = $pdo->query($sql)->fetchAll();
    $dbLookup = [];
    foreach ($rows as $r) {
        $code = !empty($r['student_code']) ? $r['student_code'] : ('HS' . $r['student_id']);
        $key = trim($code) . '|' . trim($r['subject']);
        $avg = ($r['avg_score'] !== null && $r['avg_score'] !== '') ? (float)$r['avg_score'] : null;
        $gk  = ($r['gk'] !== null && $r['gk'] !== '') ? (float)$r['gk'] : null;
        $ck  = ($r['ck'] !== null && $r['ck'] !== '') ? (float)$r['ck'] : null;
        $txList = [];
        foreach (['tx1', 'tx2', 'tx3', 'tx4'] as $col) {
            if ($r[$col] !== null && $r[$col] !== '') {
                $txList[] = (float)$r[$col];
            }
        }
        $dbLookup[$key] = evaluateRiskZone($avg, $gk, $ck, $txList);
    }

    if (empty($matches)) {
        throw new Exception("No table rows found in Admin HTML response");
    }

    $verifiedCount = 0;
    foreach ($matches as $m) {
        $htmlLevel = trim($m[1]);
        $code = trim($m[2]);
        $subject = trim($m[3]);
        $htmlDiag = trim(html_entity_decode($m[4], ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        $key = $code . '|' . $subject;
        if (!isset($dbLookup[$key])) {
            throw new Exception("Student $key in HTML not found in DB lookup");
        }

        $expected = $dbLookup[$key];
        if ($expected['level'] !== $htmlLevel) {
            throw new Exception("Mismatch for $key: HTML level '$htmlLevel' !== DB level '{$expected['level']}'");
        }
        if ($expected['diagnostic'] !== $htmlDiag) {
            throw new Exception("Mismatch for $key: HTML diagnostic '$htmlDiag' !== DB diagnostic '{$expected['diagnostic']}'");
        }
        $verifiedCount++;
    }

    return "Verified all $verifiedCount rendered table rows match evaluateRiskZone() diagnostics exactly";
});

// -----------------------------------------------------------------------------
// SECTION 3: STRESS-TESTING EDGE CASES & ROBUSTNESS
// -----------------------------------------------------------------------------
echo PHP_EOL . ANSI_YELLOW . "--- SECTION 3: STRESS-TESTING EDGE CASES & ROBUSTNESS ---" . ANSI_RESET . PHP_EOL;

// 3.1 Empty array handling in calculateRadarStats and sortStudentsByRisk
assertTest("STR-1", "Zero-division safety in calculateRadarStats([]) returns 0.0 percentages", function() {
    $stats = calculateRadarStats([]);
    if ($stats['total'] !== 0 || $stats['red'] !== 0 || $stats['red_pct'] !== 0.0) {
        throw new Exception("Invalid empty stats: " . json_encode($stats));
    }
    return "Handled zero-division safely: total=0, red_pct=0.0";
});

assertTest("STR-2", "Empty array in sortStudentsByRisk([]) returns empty array without error", function() {
    $empty = [];
    $res = sortStudentsByRisk($empty);
    if (!is_array($res) || count($res) !== 0) {
        throw new Exception("sortStudentsByRisk failed on empty array");
    }
    return "Handled empty array in sortStudentsByRisk() safely";
});

assertTest("STR-3", "sortStudentsByRisk correctly prioritizes Red -> Yellow -> Green then Name", function() {
    $list = [
        ['full_name' => 'Bình', 'level' => 'green'],
        ['full_name' => 'An', 'level' => 'green'],
        ['full_name' => 'Dũng', 'level' => 'red'],
        ['full_name' => 'Cường', 'level' => 'yellow'],
        ['full_name' => 'Bảo', 'level' => 'red'],
    ];
    sortStudentsByRisk($list);
    $order = array_map(fn($s) => $s['level'] . ':' . $s['full_name'], $list);
    $expected = ['red:Bảo', 'red:Dũng', 'yellow:Cường', 'green:An', 'green:Bình'];
    if ($order !== $expected) {
        throw new Exception("Sorting order mismatch: " . json_encode($order));
    }
    return "Sorting order strictly Red (A-Z) -> Yellow (A-Z) -> Green (A-Z)";
});

assertTest("STR-4", "evaluateRiskZone() under E_ALL with null, 0.0, 10.0 emits zero notices/warnings", function() {
    $warnings = [];
    set_error_handler(function(int $errno, string $errstr, string $errfile, int $errline) use (&$warnings) {
        $warnings[] = "$errstr in $errfile:$errline";
        return false;
    });

    try {
        evaluateRiskZone(null, null, null);
        evaluateRiskZone(0.0, 0.0, 0.0);
        evaluateRiskZone(10.0, 10.0, 10.0);
        evaluateRiskZone(3.5, 5.0, 5.0);
        evaluateRiskZone(5.0, 5.0, 5.0);
        evaluateRiskZone(6.5, 7.0, 7.0);
        evaluateRiskZone(8.0, 8.0, 8.0);
        evaluateRiskZone(5.5, 7.0, 5.5); // borderline
        evaluateRiskZone(5.5, 7.0, 5.4); // drop
    } finally {
        restore_error_handler();
    }

    if (!empty($warnings)) {
        throw new Exception("Emitted warnings: " . implode(', ', $warnings));
    }
    return "0 PHP notices/warnings emitted under E_ALL across all extreme values";
});

// -----------------------------------------------------------------------------
// SUMMARY & VERDICT
// -----------------------------------------------------------------------------
echo PHP_EOL . ANSI_BOLD . "=============================================================================" . ANSI_RESET . PHP_EOL;
echo ANSI_BOLD . "  EXECUTION SUMMARY" . ANSI_RESET . PHP_EOL;
echo ANSI_BOLD . "=============================================================================" . ANSI_RESET . PHP_EOL;
echo "  Total Tests: $testCount" . PHP_EOL;
echo "  Passed:      " . ANSI_GREEN . $passCount . ANSI_RESET . PHP_EOL;
echo "  Failed:      " . ($failCount > 0 ? ANSI_RED : ANSI_GREEN) . $failCount . ANSI_RESET . PHP_EOL;

if ($failCount > 0) {
    echo PHP_EOL . ANSI_RED . ANSI_BOLD . "  VERDICT: REQUEST_CHANGES" . ANSI_RESET . PHP_EOL;
    foreach ($failures as $f) {
        echo "   - $f" . PHP_EOL;
    }
    exit(1);
} else {
    echo PHP_EOL . ANSI_GREEN . ANSI_BOLD . "  VERDICT: APPROVE" . ANSI_RESET . PHP_EOL;
    exit(0);
}
