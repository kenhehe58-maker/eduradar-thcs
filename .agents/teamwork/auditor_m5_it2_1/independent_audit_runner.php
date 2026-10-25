<?php
/**
 * Independent Forensic Audit Verification Script
 * Executed independently by auditor_m5_it2_1
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$projectDir = realpath(__DIR__ . '/../../..');
require_once $projectDir . '/includes/config.php';
require_once $projectDir . '/includes/db.php';
require_once $projectDir . '/includes/radar_helper.php';

echo "============================================================\n";
echo " INDEPENDENT FORENSIC AUDIT RUNNER — auditor_m5_it2_1\n";
echo " Project root: $projectDir\n";
echo "============================================================\n\n";

$auditPassed = 0;
$auditFailed = 0;
$failures = [];

function auditCheck(string $desc, callable $fn): void {
    global $auditPassed, $auditFailed, $failures;
    echo "[AUDIT] " . str_pad($desc, 65, '.') . " ";
    try {
        $res = $fn();
        if ($res === false) {
            throw new Exception("Check returned false");
        }
        $auditPassed++;
        echo "PASS\n";
    } catch (Throwable $e) {
        $auditFailed++;
        echo "FAIL\n";
        echo "        -> ERROR: " . $e->getMessage() . "\n";
        $failures[] = "$desc: " . $e->getMessage();
    }
}

// -------------------------------------------------------------
// CHECK 1: Absence of facades, stubs, and hardcoded test logic
// -------------------------------------------------------------
auditCheck("1.1 tests/verify_m5.php contains 0 occurrences of testEvaluateRisk", function () use ($projectDir) {
    $content = file_get_contents($projectDir . '/tests/verify_m5.php');
    if (strpos($content, 'testEvaluateRisk') !== false) {
        throw new Exception("Found testEvaluateRisk in tests/verify_m5.php");
    }
    return true;
});

auditCheck("1.2 Entire codebase has 0 occurrences of testEvaluateRisk", function () use ($projectDir) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($projectDir));
    foreach ($files as $file) {
        if ($file->isFile() && in_array($file->getExtension(), ['php', 'js', 'html', 'sql'])) {
            // skip teamwork folder where old auditor reports might mention it
            if (strpos($file->getPathname(), '.agents') !== false) continue;
            $txt = file_get_contents($file->getPathname());
            if (strpos($txt, 'testEvaluateRisk') !== false) {
                throw new Exception("Found testEvaluateRisk in " . $file->getPathname());
            }
        }
    }
    return true;
});

auditCheck("1.3 tests/verify_m5.php calls evaluateRiskZone from includes/radar_helper.php", function () use ($projectDir) {
    $content = file_get_contents($projectDir . '/tests/verify_m5.php');
    if (strpos($content, 'require_once $radarHelperPath') === false && strpos($content, 'includes/radar_helper.php') === false) {
        throw new Exception("verify_m5.php does not import radar_helper.php");
    }
    if (substr_count($content, 'evaluateRiskZone(') < 10) {
        throw new Exception("verify_m5.php does not invoke evaluateRiskZone sufficient times");
    }
    return true;
});

auditCheck("1.4 tests/verify_m5.php 3.1-3.4 has zero hardcoded math arrays", function () use ($projectDir) {
    $content = file_get_contents($projectDir . '/tests/verify_m5.php');
    if (strpos($content, "\$counts = ['total' => 25") !== false) {
        throw new Exception("Found hardcoded count array in verify_m5.php");
    }
    if (strpos($content, "getEvaluatedLiveStudents()") === false) {
        throw new Exception("verify_m5.php does not query live students in Group 3");
    }
    return true;
});

// -------------------------------------------------------------
// CHECK 2: Diagnostic Logic Authenticity (R4 in ORIGINAL_REQUEST.md)
// -------------------------------------------------------------
auditCheck("2.1 evaluateRiskZone() red zone: avg < 3.5 -> red", function () {
    $r = evaluateRiskZone(3.4, 6.0, 6.0);
    if ($r['level'] !== 'red') throw new Exception("Expected red, got " . $r['level']);
    return true;
});

auditCheck("2.2 evaluateRiskZone() red zone: ck < 3.0 -> red (even if avg >= 5.0)", function () {
    $r = evaluateRiskZone(5.5, 7.0, 2.9);
    if ($r['level'] !== 'red') throw new Exception("Expected red, got " . $r['level']);
    return true;
});

auditCheck("2.3 evaluateRiskZone() red zone: gk < 5.0 && ck < 5.0 -> red", function () {
    $r = evaluateRiskZone(4.8, 4.5, 4.5);
    if ($r['level'] !== 'red') throw new Exception("Expected red, got " . $r['level']);
    return true;
});

auditCheck("2.4 evaluateRiskZone() yellow zone: 3.5 <= avg < 5.0 -> yellow", function () {
    $r = evaluateRiskZone(3.5, 5.0, 5.0);
    if ($r['level'] !== 'yellow') throw new Exception("Expected yellow, got " . $r['level']);
    $r2 = evaluateRiskZone(4.9, 5.5, 5.5);
    if ($r2['level'] !== 'yellow') throw new Exception("Expected yellow, got " . $r2['level']);
    return true;
});

auditCheck("2.5 evaluateRiskZone() yellow zone: component gk < 5.0 or ck < 5.0 when avg >= 5.0", function () {
    $rGk = evaluateRiskZone(5.5, 4.8, 6.5);
    if ($rGk['level'] !== 'yellow') throw new Exception("GK < 5 expected yellow, got " . $rGk['level']);
    $rCk = evaluateRiskZone(5.5, 6.0, 4.9);
    if ($rCk['level'] !== 'yellow') throw new Exception("CK < 5 expected yellow, got " . $rCk['level']);
    return true;
});

auditCheck("2.6 evaluateRiskZone() yellow zone: downward drop (gk - ck) > 1.5", function () {
    $r = evaluateRiskZone(6.5, 8.0, 6.4); // 8.0 - 6.4 = 1.6 > 1.5
    if ($r['level'] !== 'yellow') throw new Exception("Drop 1.6 expected yellow, got " . $r['level']);
    $rBoundary = evaluateRiskZone(6.5, 8.0, 6.5); // 8.0 - 6.5 = 1.5 (not > 1.5)
    if ($rBoundary['level'] !== 'green') throw new Exception("Drop 1.5 expected green, got " . $rBoundary['level']);
    return true;
});

auditCheck("2.7 evaluateRiskZone() green zone: avg >= 5.0, null scores handled cleanly", function () {
    $r1 = evaluateRiskZone(5.0, 5.0, 5.0);
    if ($r1['level'] !== 'green') throw new Exception("Avg 5.0 expected green, got " . $r1['level']);
    $r2 = evaluateRiskZone(8.5, 9.0, 8.5);
    if ($r2['level'] !== 'green') throw new Exception("Avg 8.5 expected green, got " . $r2['level']);
    $rNull = evaluateRiskZone(null, null, null);
    if ($rNull['level'] !== 'green') throw new Exception("Null scores expected green, got " . $rNull['level']);
    return true;
});

auditCheck("2.8 Zero PHP notices/warnings during evaluateRiskZone under E_ALL", function () {
    $warnings = [];
    set_error_handler(function ($errno, $errstr, $errfile, $errline) use (&$warnings) {
        $warnings[] = "$errstr at $errfile:$errline";
        return false;
    });
    try {
        evaluateRiskZone(null, null, null, []);
        evaluateRiskZone(0.0, 0.0, 0.0, [0.0]);
        evaluateRiskZone(3.5, 4.9, 4.9, [3.0, 4.0]);
        evaluateRiskZone(10.0, 10.0, 10.0, [10.0, 10.0]);
    } finally {
        restore_error_handler();
    }
    if (!empty($warnings)) {
        throw new Exception("Captured warnings: " . implode('; ', $warnings));
    }
    return true;
});

// -------------------------------------------------------------
// CHECK 3: Live Apache HTTP Cleanliness (Zero Warnings/Notices)
// -------------------------------------------------------------
function curlRequest(string $path, ?string $cookieFile, array $post = []): array {
    $url = 'http://localhost/my-web-project/' . ltrim($path, '/');
    $ch = curl_init();
    $opts = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => false,
    ];
    if ($cookieFile) {
        $opts[CURLOPT_COOKIEJAR] = $cookieFile;
        $opts[CURLOPT_COOKIEFILE] = $cookieFile;
    }
    if (!empty($post)) {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($post);
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new Exception("cURL failed: $err");
    }
    $info = curl_getinfo($ch);
    $headerSize = $info['header_size'];
    $headers = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);
    curl_close($ch);
    return ['status' => (int)$info['http_code'], 'headers' => $headers, 'body' => $body];
}

function assertNoPhpErrors(string $html, string $context): void {
    $bad = [
        'Warning:',
        '<b>Warning</b>',
        'Notice:',
        '<b>Notice</b>',
        'Deprecated:',
        '<b>Deprecated</b>',
        'Fatal error:',
        '<b>Fatal error</b>',
        'Parse error:',
        'Undefined variable',
        'Undefined index',
        'Undefined array key',
        '$isAvgWarning',
        'Call to undefined'
    ];
    foreach ($bad as $b) {
        if (stripos($html, $b) !== false) {
            $pos = stripos($html, $b);
            $snippet = substr($html, max(0, $pos - 30), 150);
            throw new Exception("[$context] PHP leak detected: '$b'. Snippet: " . strip_tags($snippet));
        }
    }
}

auditCheck("3.1 Unauthenticated index.php redirects with HTTP 302/301", function () {
    $res = curlRequest('index.php', null);
    if ($res['status'] !== 302 && $res['status'] !== 301) {
        throw new Exception("Expected 302, got " . $res['status']);
    }
    return true;
});

auditCheck("3.2 Admin live login and index.php has ZERO PHP warnings/notices/errors", function () {
    $cookie = tempnam(sys_get_temp_dir(), 'aud_adm_');
    try {
        $loginRes = curlRequest('login.php', $cookie, ['username' => 'admin', 'password' => '123456']);
        if ($loginRes['status'] !== 302) {
            throw new Exception("Login failed, status " . $loginRes['status']);
        }
        $indexRes = curlRequest('index.php', $cookie);
        if ($indexRes['status'] !== 200) {
            throw new Exception("index.php status " . $indexRes['status']);
        }
        assertNoPhpErrors($indexRes['body'], 'Admin index.php');
    } finally {
        @unlink($cookie);
    }
    return true;
});

auditCheck("3.3 Teacher gv_toan live login and index.php has ZERO PHP warnings/notices/errors", function () {
    $cookie = tempnam(sys_get_temp_dir(), 'aud_toan_');
    try {
        $loginRes = curlRequest('login.php', $cookie, ['username' => 'gv_toan', 'password' => '123456']);
        if ($loginRes['status'] !== 302) {
            throw new Exception("Login failed, status " . $loginRes['status']);
        }
        $indexRes = curlRequest('index.php', $cookie);
        if ($indexRes['status'] !== 200) {
            throw new Exception("index.php status " . $indexRes['status']);
        }
        assertNoPhpErrors($indexRes['body'], 'gv_toan index.php');
        if (stripos($indexRes['body'], 'Ngữ Văn') !== false) {
            throw new Exception("gv_toan sees Ngữ Văn");
        }
        if (strpos($indexRes['body'], 'admin.php') !== false) {
            throw new Exception("gv_toan sees admin.php link");
        }
    } finally {
        @unlink($cookie);
    }
    return true;
});

auditCheck("3.4 Teacher gv_van live login and index.php has ZERO PHP warnings/notices/errors", function () {
    $cookie = tempnam(sys_get_temp_dir(), 'aud_van_');
    try {
        $loginRes = curlRequest('login.php', $cookie, ['username' => 'gv_van', 'password' => '123456']);
        if ($loginRes['status'] !== 302) {
            throw new Exception("Login failed, status " . $loginRes['status']);
        }
        $indexRes = curlRequest('index.php', $cookie);
        if ($indexRes['status'] !== 200) {
            throw new Exception("index.php status " . $indexRes['status']);
        }
        assertNoPhpErrors($indexRes['body'], 'gv_van index.php');
        if (stripos($indexRes['body'], 'Toán') !== false) {
            throw new Exception("gv_van sees Toán");
        }
        if (strpos($indexRes['body'], 'admin.php') !== false) {
            throw new Exception("gv_van sees admin.php link");
        }
    } finally {
        @unlink($cookie);
    }
    return true;
});

// -------------------------------------------------------------
// CHECK 4: DOM ID Uniqueness on Live HTML
// -------------------------------------------------------------
auditCheck("4.1 Live index.php HTML has ZERO duplicate DOM IDs across all roles", function () {
    foreach (['admin', 'gv_toan', 'gv_van'] as $user) {
        $cookie = tempnam(sys_get_temp_dir(), "aud_dom_{$user}_");
        try {
            curlRequest('login.php', $cookie, ['username' => $user, 'password' => '123456']);
            $res = curlRequest('index.php', $cookie);
            preg_match_all('/\bid=["\']([^"\']+)["\']/i', $res['body'], $matches);
            $ids = $matches[1] ?? [];
            $counts = array_count_values($ids);
            foreach ($counts as $id => $cnt) {
                if ($cnt > 1) {
                    throw new Exception("Duplicate DOM ID '$id' ($cnt occurrences) found for user '$user'");
                }
            }
        } finally {
            @unlink($cookie);
        }
    }
    return true;
});

// -------------------------------------------------------------
// CHECK 5: Complete Zalo & Modal Eradication
// -------------------------------------------------------------
auditCheck("5.1 0 occurrences of 'zalo' in application code (excluding tests/agent docs)", function () use ($projectDir) {
    $appDirs = ['includes', 'assets'];
    $appFiles = ['index.php', 'login.php', 'logout.php', 'admin.php', 'nhap_diem.php', 'database.sql'];
    foreach ($appFiles as $af) {
        $path = "$projectDir/$af";
        if (file_exists($path)) {
            $txt = file_get_contents($path);
            if (stripos($txt, 'zalo') !== false) throw new Exception("Found 'zalo' in $af");
        }
    }
    foreach ($appDirs as $ad) {
        $dirPath = "$projectDir/$ad";
        if (!is_dir($dirPath)) continue;
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dirPath));
        foreach ($files as $f) {
            if ($f->isFile()) {
                $txt = file_get_contents($f->getPathname());
                if (stripos($txt, 'zalo') !== false) throw new Exception("Found 'zalo' in " . $f->getPathname());
            }
        }
    }
    return true;
});

auditCheck("5.2 0 occurrences of 'actionModal' in application code", function () use ($projectDir) {
    $appFiles = ['index.php', 'login.php', 'logout.php', 'admin.php', 'nhap_diem.php', 'assets/js/main.js', 'assets/css/style.css'];
    foreach ($appFiles as $af) {
        $path = "$projectDir/$af";
        if (file_exists($path)) {
            $txt = file_get_contents($path);
            if (stripos($txt, 'actionModal') !== false) throw new Exception("Found 'actionModal' in $af");
        }
    }
    return true;
});

auditCheck("5.3 0 occurrences of 'copy-btn' in application code", function () use ($projectDir) {
    $appFiles = ['index.php', 'assets/js/main.js', 'assets/css/style.css'];
    foreach ($appFiles as $af) {
        $path = "$projectDir/$af";
        if (file_exists($path)) {
            $txt = file_get_contents($path);
            if (stripos($txt, 'copy-btn') !== false) throw new Exception("Found 'copy-btn' in $af");
        }
    }
    return true;
});

// -------------------------------------------------------------
// CHECK 6: Database & Stats Invariants
// -------------------------------------------------------------
auditCheck("6.1 Live MySQL stats and calculateRadarStats invariant matches HTML stat cards", function () {
    $cookie = tempnam(sys_get_temp_dir(), 'aud_stat_');
    try {
        curlRequest('login.php', $cookie, ['username' => 'admin', 'password' => '123456']);
        $res = curlRequest('index.php', $cookie);
        
        $pdo = db();
        $stmt = $pdo->query("
            SELECT tc.subject, sc.tx1, sc.tx2, sc.tx3, sc.tx4, sc.gk, sc.ck, sc.avg_score
            FROM teacher_classes tc
            JOIN classes c ON tc.class_id = c.id
            JOIN students s ON s.class_id = c.id
            LEFT JOIN scores sc ON sc.student_id = s.id AND sc.subject = tc.subject
        ");
        $rows = $stmt->fetchAll();
        $evaluated = [];
        foreach ($rows as $r) {
            $avg = ($r['avg_score'] !== null && $r['avg_score'] !== '') ? (float)$r['avg_score'] : null;
            $gk  = ($r['gk'] !== null && $r['gk'] !== '') ? (float)$r['gk'] : null;
            $ck  = ($r['ck'] !== null && $r['ck'] !== '') ? (float)$r['ck'] : null;
            $tx = [];
            foreach (['tx1', 'tx2', 'tx3', 'tx4'] as $col) {
                if ($r[$col] !== null && $r[$col] !== '') $tx[] = (float)$r[$col];
            }
            $eval = evaluateRiskZone($avg, $gk, $ck, $tx);
            $evaluated[] = $eval;
        }
        $stats = calculateRadarStats($evaluated);
        
        if ($stats['total'] !== count($rows)) throw new Exception("Total mismatch");
        if ($stats['total'] !== ($stats['red'] + $stats['yellow'] + $stats['green'])) throw new Exception("Invariant violated");
        
        preg_match('/stat-card\s+total.*?<b>(\d+)<\/b>/s', $res['body'], $mTot);
        preg_match('/stat-card\s+red.*?<b>(\d+)<\/b>/s', $res['body'], $mRed);
        preg_match('/stat-card\s+yellow.*?<b>(\d+)<\/b>/s', $res['body'], $mYel);
        preg_match('/stat-card\s+green.*?<b>(\d+)<\/b>/s', $res['body'], $mGrn);
        
        if ((int)$mTot[1] !== $stats['total']) throw new Exception("HTML total {$mTot[1]} != DB {$stats['total']}");
        if ((int)$mRed[1] !== $stats['red']) throw new Exception("HTML red {$mRed[1]} != DB {$stats['red']}");
        if ((int)$mYel[1] !== $stats['yellow']) throw new Exception("HTML yellow {$mYel[1]} != DB {$stats['yellow']}");
        if ((int)$mGrn[1] !== $stats['green']) throw new Exception("HTML green {$mGrn[1]} != DB {$stats['green']}");
    } finally {
        @unlink($cookie);
    }
    return true;
});

echo "\n============================================================\n";
echo " INDEPENDENT AUDIT SUMMARY: Passed: $auditPassed | Failed: $auditFailed\n";
echo "============================================================\n";

if ($auditFailed > 0) {
    echo "Failures:\n";
    foreach ($failures as $f) {
        echo " - $f\n";
    }
    exit(1);
}
exit(0);
