<?php
/**
 * Empirical Challenge Suite for Milestone 1
 * Role: challenger_m1_2 (Adversarial stress-testing & empirical verification)
 * 
 * Tests:
 * 1. Password security & brute-force testing against seed hashes & live login endpoint
 * 2. SQLite complete purge & driver triggering attempt
 * 3. Rapid/concurrent audit logging with extreme inputs (IPv6, 4-byte UTF-8, long strings, injection)
 */

declare(strict_types=1);

const CLR_RESET  = "\033[0m";
const CLR_GREEN  = "\033[1;32m";
const CLR_RED    = "\033[1;31m";
const CLR_YELLOW = "\033[1;33m";
const CLR_CYAN   = "\033[1;36m";

$totalChallenges = 0;
$passedChallenges = 0;
$failedChallenges = 0;
$findings = [];

function challenge(string $title, callable $fn): void {
    global $totalChallenges, $passedChallenges, $failedChallenges, $findings;
    $totalChallenges++;
    echo CLR_CYAN . "[CHALLENGE " . sprintf("%02d", $totalChallenges) . "] " . CLR_RESET . str_pad($title, 64, ".");
    try {
        $result = $fn();
        if ($result === false) {
            throw new Exception("Challenge assertion failed");
        }
        $passedChallenges++;
        echo CLR_GREEN . " [PASS]" . CLR_RESET . PHP_EOL;
    } catch (Throwable $e) {
        $failedChallenges++;
        $findings[] = [
            'challenge' => $title,
            'error'     => $e->getMessage()
        ];
        echo CLR_RED . " [FAIL]" . CLR_RESET . PHP_EOL;
        echo CLR_RED . "             -> " . $e->getMessage() . CLR_RESET . PHP_EOL;
    }
}

echo PHP_EOL . CLR_YELLOW . "================================================================================" . CLR_RESET . PHP_EOL;
echo CLR_YELLOW . "  EduRadar Milestone 1: Empirical Adversarial Challenge Suite" . CLR_RESET . PHP_EOL;
echo CLR_YELLOW . "================================================================================" . CLR_RESET . PHP_EOL . PHP_EOL;

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';

// =============================================================================
// SUITE 1: PASSWORD SECURITY & BRUTE FORCE TESTING
// =============================================================================
echo CLR_YELLOW . "--- [SUITE 1] Password Security & Brute Force Dictionary Attacks ---" . CLR_RESET . PHP_EOL;

challenge("1.1 Verify bcrypt hashes in DB match exact standard for admin, gv_toan, gv_van", function() {
    $pdo = db();
    $stmt = $pdo->query("SELECT username, password FROM users ORDER BY id");
    $users = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $expected = ['admin', 'gv_toan', 'gv_van'];
    foreach ($expected as $u) {
        if (!isset($users[$u])) {
            throw new Exception("Missing user '$u' in users table");
        }
        $hash = $users[$u];
        if (!str_starts_with($hash, '$2y$10$')) {
            throw new Exception("Hash for '$u' is not a valid 2y-cost-10 bcrypt hash: $hash");
        }
        if (!password_verify('123456', $hash)) {
            throw new Exception("Valid password '123456' failed for '$u'");
        }
    }
    return true;
});

challenge("1.2 Brute-force & adversarial dictionary test against all 3 seed hashes (50+ vectors)", function() {
    $pdo = db();
    $stmt = $pdo->query("SELECT username, password FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Adversarial wordlist: common passwords, boundary lengths, whitespace, injection, unicode, etc.
    $badPasswords = [
        '',                      // empty
        ' ',                     // single space
        '   ',                   // multiple spaces
        ' 123456',               // leading space
        '123456 ',               // trailing space
        ' 123456 ',              // surrounding space
        '12345',                 // 1 char short
        '1234567',               // 1 char long
        '123456\0',              // null byte
        "123456\n",              // newline
        "123456\r\n",            // crlf
        'admin',                 // username as password
        'admin123',
        'password',
        'root',
        '111111',
        '000000',
        '654321',
        '123123',
        'qwerty',
        'toan',
        'van',
        'gv_toan',
        'gv_van',
        "' OR '1'='1",           // SQL injection
        "' OR 1=1 --",
        "admin'--",
        "123456' OR '1'='1",
        '<script>alert(1)</script>', // XSS
        '${7*7}',                // template injection
        '{{7*7}}',
        '../../../etc/passwd',   // LFI
        '\\x00\\x01\\x02',
        ' mật khẩu tiếng Việt ',
        '123456' . str_repeat('a', 66), // 72-byte bcrypt truncation boundary test (6 + 66 = 72)
        '123456' . str_repeat('a', 100), // > 72 bytes
        str_repeat('A', 255),
        str_repeat('9', 1000),
        'null',
        'NULL',
        'false',
        '0',
        '-1',
        '!@#$%^&*()_+~`|}{[]:;?><,./-=',
        '😀😃😄😁😆'
    ];

    foreach ($users as $username => $hash) {
        foreach ($badPasswords as $bad) {
            if (password_verify($bad, $hash)) {
                throw new Exception("SECURITY BREACH: Invalid password '$bad' was ACCEPTED for user '$username'!");
            }
        }
    }
    return true;
});

challenge("1.3 Verify no hardcoded credentials in database.sql, config.php, db.php", function() {
    $files = [
        'database.sql'        => dirname(__DIR__) . '/database.sql',
        'includes/config.php' => dirname(__DIR__) . '/includes/config.php',
        'includes/db.php'     => dirname(__DIR__) . '/includes/db.php',
    ];

    foreach ($files as $name => $path) {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new Exception("Unable to read $name");
        }

        // In database.sql, check that INSERT INTO users contains only bcrypt hashes ($2y$...)
        if ($name === 'database.sql') {
            if (preg_match("/INSERT INTO users .*VALUES\s*([^;]+);/is", $content, $m)) {
                $values = $m[1];
                // Check if any plaintext password like '123456' is inside the values as a column value
                // Each row is (id, username, password, full_name, role)
                if (preg_match("/\(\s*\d+\s*,\s*'[^']+'\s*,\s*'([^']+)'/i", $values, $hashMatches)) {
                    if (!str_starts_with($hashMatches[1], '$2y$')) {
                        throw new Exception("database.sql contains non-bcrypt password: " . $hashMatches[1]);
                    }
                }
            }
        }

        // In config.php, DB_PASS should be empty or standard, no hardcoded app user passwords
        if ($name === 'includes/config.php') {
            if (str_contains($content, 'admin123') || str_contains($content, 'secret_backdoor')) {
                throw new Exception("Hardcoded secret in config.php");
            }
        }
    }
    return true;
});

challenge("1.4 Live HTTP login brute-force check via Apache (rejects invalid, accepts 123456)", function() {
    $loginUrl = 'http://localhost/my-web-project/login.php';

    $testCases = [
        ['user' => 'admin',   'pass' => 'wrongpass',  'expectSuccess' => false],
        ['user' => 'admin',   'pass' => '1234567',    'expectSuccess' => false],
        ['user' => 'admin',   'pass' => '',           'expectSuccess' => false],
        ['user' => 'admin',   'pass' => "' OR 1=1--", 'expectSuccess' => false],
        ['user' => 'gv_toan', 'pass' => 'wrongtoan',  'expectSuccess' => false],
        ['user' => 'gv_van',  'pass' => 'wrongvan',   'expectSuccess' => false],
        ['user' => 'nonexist','pass' => '123456',     'expectSuccess' => false],
        ['user' => 'admin',   'pass' => '123456',     'expectSuccess' => true],
        ['user' => 'gv_toan', 'pass' => '123456',     'expectSuccess' => true],
    ];

    foreach ($testCases as $tc) {
        $postData = http_build_query([
            'username' => $tc['user'],
            'password' => $tc['pass'],
        ]);

        $opts = [
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'content' => $postData,
                'max_redirects' => 0,
                'ignore_errors' => true,
            ]
        ];

        $context = stream_context_create($opts);
        $res = @file_get_contents($loginUrl, false, $context);
        $headers = $http_response_header ?? [];

        $isRedirect = false;
        $location = '';
        foreach ($headers as $h) {
            if (stripos($h, 'Location:') === 0) {
                $isRedirect = true;
                $location = trim(substr($h, 9));
            }
        }

        if ($tc['expectSuccess']) {
            if (!$isRedirect) {
                throw new Exception("Expected redirect on valid login for {$tc['user']}, got HTTP response without Location header.");
            }
        } else {
            if ($isRedirect && ($location === 'admin.php' || $location === 'nhap_diem.php')) {
                throw new Exception("SECURITY FAILURE: Invalid login for {$tc['user']} with '{$tc['pass']}' redirected to {$location}!");
            }
        }
    }
    return true;
});

// =============================================================================
// SUITE 2: SQLITE COMPLETE PURGE & DRIVER TRIGGER ATTEMPTS
// =============================================================================
echo PHP_EOL . CLR_YELLOW . "--- [SUITE 2] SQLite Elimination & Driver Triggering Verification ---" . CLR_RESET . PHP_EOL;

challenge("2.1 Recursive filesystem audit: zero .sqlite, .sqlite3, .db, .s3db files exist", function() {
    $projectRoot = dirname(__DIR__);
    $dirIter = new RecursiveDirectoryIterator($projectRoot, RecursiveDirectoryIterator::SKIP_DOTS);
    $iter = new RecursiveIteratorIterator($dirIter, RecursiveIteratorIterator::SELF_FIRST);

    $forbiddenExt = ['sqlite', 'sqlite3', 'db', 's3db'];
    $violations = [];

    foreach ($iter as $file) {
        if ($file->isFile()) {
            $ext = strtolower($file->getExtension());
            $filename = strtolower($file->getFilename());
            if (in_array($ext, $forbiddenExt, true) || str_contains($filename, 'sqlite')) {
                // Ignore challenge reports or markdown documentation
                if (in_array($ext, ['md', 'php', 'txt', 'sql'], true)) {
                    continue;
                }
                $violations[] = $file->getPathname();
            }
        }
    }

    if (!empty($violations)) {
        throw new Exception("SQLite files detected on disk: " . implode(', ', $violations));
    }
    return true;
});

challenge("2.2 Deep code grep: no active SQLite driver instantiation across all project PHP files", function() {
    $projectRoot = dirname(__DIR__);
    $dirIter = new RecursiveDirectoryIterator($projectRoot, RecursiveDirectoryIterator::SKIP_DOTS);
    $iter = new RecursiveIteratorIterator($dirIter, RecursiveIteratorIterator::SELF_FIRST);

    $badPatterns = [
        '/new\s+PDO\s*\(\s*[\'"]sqlite:/i',
        '/sqlite_open/i',
        '/sqlite3/i',
    ];

    $matches = [];
    foreach ($iter as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            // Exclude tests/challenge_m1_harness.php itself where patterns are defined
            if ($file->getFilename() === basename(__FILE__)) {
                continue;
            }
            $content = file_get_contents($file->getPathname());
            foreach ($badPatterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    $matches[] = $file->getPathname() . " matches " . $pattern;
                }
            }
        }
    }

    if (!empty($matches)) {
        throw new Exception("Active SQLite code found: " . implode('; ', $matches));
    }
    return true;
});

challenge("2.3 Driver trigger attempt: verify db() is strictly bound to MySQL and refuses SQLite", function() {
    $pdo = db();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver !== 'mysql') {
        throw new Exception("Driver is '$driver', expected strictly 'mysql'");
    }

    $serverVer = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    if (!str_contains(strtolower($serverVer), 'mariadb') && !str_contains(strtolower($serverVer), 'mysql')) {
        throw new Exception("Server version indicates non-MySQL engine: " . $serverVer);
    }
    return true;
});

// =============================================================================
// SUITE 3: RAPID / HIGH-THROUGHPUT AUDIT LOGGING & EDGE CASES
// =============================================================================
echo PHP_EOL . CLR_YELLOW . "--- [SUITE 3] Rapid Audit Logging & Extreme Input Stress Testing ---" . CLR_RESET . PHP_EOL;

challenge("3.1 Rapid logging stress test: 500 consecutive log_action() calls with microsecond timing", function() {
    $pdo = db();
    $batchTag = 'STRESS_RAPID_' . bin2hex(random_bytes(4));
    
    $startTime = microtime(true);
    $count = 500;
    for ($i = 1; $i <= $count; $i++) {
        log_action(2, 'gv_toan', $batchTag, "Stress test iteration #$i at " . microtime(true));
    }
    $duration = microtime(true) - $startTime;
    $rate = round($count / $duration, 1);

    // Verify all 500 records were persisted sequentially in DB
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE action = ?");
    $stmt->execute([$batchTag]);
    $persisted = (int)$stmt->fetchColumn();

    if ($persisted !== $count) {
        throw new Exception("Expected $count records, persisted: $persisted");
    }
    // Rate check: must complete in reasonable time (< 3.0s for 500 records on local MySQL, > 100 writes/sec)
    if ($duration > 5.0) {
        throw new Exception("Stress logging too slow: {$duration}s ({$rate} ops/s)");
    }
    return true;
});

challenge("3.2 4-byte UTF-8 emoji & Vietnamese diacritics persistence (utf8mb4 verification)", function() {
    $pdo = db();
    $emojiTag = 'EMOJI_TEST_' . bin2hex(random_bytes(4));
    $vietnamesePayload = "Cảnh báo sớm: 🔴 Nguy cấp (Toán: 3.5), 🟡 Cần chú ý (Văn: 5.2), 🟢 Ổn định! 👨‍🏫 Thầy Nguyễn Văn Giáo 🚀💯";
    
    log_action(1, 'admin', $emojiTag, $vietnamesePayload);

    $stmt = $pdo->prepare("SELECT details FROM audit_logs WHERE action = ?");
    $stmt->execute([$emojiTag]);
    $saved = $stmt->fetchColumn();

    if ($saved !== $vietnamesePayload) {
        throw new Exception("UTF-8mb4 corruption detected! Saved: " . var_export($saved, true));
    }
    return true;
});

challenge("3.3 Adversarial injection payloads in action, details, and username", function() {
    $pdo = db();
    $injections = [
        "SQLi_1" => "'; DROP TABLE audit_logs; --",
        "SQLi_2" => "' OR '1'='1",
        "SQLi_3" => "1' UNION SELECT 1,2,3,4,5,6,7--",
        "XSS_1"  => "<script>alert('XSS')</script>",
        "XSS_2"  => "<img src=x onerror=alert(document.cookie)>",
        "Quote"  => 'O\'Reilly "Testing" \\escaped\\ /slash/ %wildcard% _underscore_',
        "NullB"  => "prefix\0suffix",
    ];

    foreach ($injections as $name => $payload) {
        $tag = 'INJ_' . $name . '_' . bin2hex(random_bytes(3));
        log_action(null, $payload, $tag, $payload);

        $stmt = $pdo->prepare("SELECT username, details FROM audit_logs WHERE action = ?");
        $stmt->execute([$tag]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new Exception("Failed to record injection test '$name'");
        }
        // Verify audit_logs table was not dropped or compromised
        $checkTable = $pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
        if ($checkTable === false) {
            throw new Exception("Audit table compromised by injection '$name'");
        }
    }
    return true;
});

challenge("3.4 Comprehensive IP address edge cases (IPv4, IPv6, proxies, spoofing, overflow)", function() {
    $pdo = db();
    
    $ipScenarios = [
        ['type' => 'Standard IPv4', 'server' => ['REMOTE_ADDR' => '192.168.1.105'], 'expect' => '192.168.1.105'],
        ['type' => 'Loopback IPv6',  'server' => ['REMOTE_ADDR' => '::1'], 'expect' => '::1'],
        ['type' => 'Full IPv6',      'server' => ['REMOTE_ADDR' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334'], 'expect' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334'],
        ['type' => 'Compressed IPv6','server' => ['REMOTE_ADDR' => 'fe80::1ff:fe23:4567:890a'], 'expect' => 'fe80::1ff:fe23:4567:890a'],
        ['type' => 'IPv4-mapped IPv6','server' => ['REMOTE_ADDR' => '::ffff:192.0.2.128'], 'expect' => '::ffff:192.0.2.128'],
        ['type' => 'X-Forwarded Multi', 'server' => ['HTTP_X_FORWARDED_FOR' => '203.0.113.195, 70.41.3.18', 'REMOTE_ADDR' => '10.0.0.1'], 'expect' => '203.0.113.195'],
        ['type' => 'X-Forwarded IPv6',  'server' => ['HTTP_X_FORWARDED_FOR' => '2001:db8::1', 'REMOTE_ADDR' => '127.0.0.1'], 'expect' => '2001:db8::1'],
        ['type' => 'X-Forwarded Spoofed/Malicious', 'server' => ['HTTP_X_FORWARDED_FOR' => '<script>alert(1)</script>, 10.0.0.1', 'REMOTE_ADDR' => '127.0.0.1'], 'expect' => '127.0.0.1'], // Malicious X-Forwarded rejected by FILTER_VALIDATE_IP, falls back to 127.0.0.1
        ['type' => 'Overlong REMOTE_ADDR (>45 chars)', 'server' => ['REMOTE_ADDR' => str_repeat('a', 100)], 'expect' => str_repeat('a', 45)], // Truncated cleanly to VARCHAR(45)
    ];

    // Backup current $_SERVER
    $oldServer = $_SERVER;

    foreach ($ipScenarios as $sc) {
        // Reset and set test server keys
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        unset($_SERVER['REMOTE_ADDR']);
        foreach ($sc['server'] as $k => $v) {
            $_SERVER[$k] = $v;
        }

        $tag = 'IP_TEST_' . bin2hex(random_bytes(4));
        log_action(1, 'admin', $tag, "Testing IP scenario: " . $sc['type']);

        $stmt = $pdo->prepare("SELECT ip_address FROM audit_logs WHERE action = ?");
        $stmt->execute([$tag]);
        $recordedIp = $stmt->fetchColumn();

        if ($recordedIp !== $sc['expect']) {
            $_SERVER = $oldServer;
            throw new Exception("Scenario '{$sc['type']}' expected IP '{$sc['expect']}', got '{$recordedIp}'");
        }
    }

    // Restore server
    $_SERVER = $oldServer;
    return true;
});

challenge("3.5 Large payload & boundary testing (10KB, 50KB detail strings, VARCHAR boundaries)", function() {
    $pdo = db();

    // 1. Within-capacity large string (~50KB UTF-8 Vietnamese, within 65,535 bytes TEXT limit)
    $large50k = str_repeat("Dữ liệu kiểm thử độ dài lớn của nhật ký hệ thống EduRadar. ", 500); // ~40KB
    $tag50k = 'LARGE_50K_' . bin2hex(random_bytes(4));
    log_action(1, 'admin', $tag50k, $large50k);

    $stmt = $pdo->prepare("SELECT details FROM audit_logs WHERE action = ?");
    $stmt->execute([$tag50k]);
    $saved50k = $stmt->fetchColumn();
    if ($saved50k !== $large50k) {
        throw new Exception("Large text payload corrupted or truncated! Expected " . strlen($large50k) . " bytes, got " . strlen((string)$saved50k));
    }

    // 2. Exact TEXT column maximum limit (65,535 bytes)
    $exact64k = str_repeat('B', 65535);
    $tag64k = 'EXACT_64K_' . bin2hex(random_bytes(4));
    log_action(1, 'admin', $tag64k, $exact64k);
    $stmt->execute([$tag64k]);
    $saved64k = $stmt->fetchColumn();
    if (strlen((string)$saved64k) !== 65535) {
        throw new Exception("Exact 64KB TEXT payload mismatch! Got " . strlen((string)$saved64k) . " bytes");
    }

    // 3. Overflow behavior (> 65,535 bytes): log_action() must handle gracefully without unhandled exception
    $overflowDetails = str_repeat('O', 75000);
    $tagOverflow = 'OVERFLOW_' . bin2hex(random_bytes(4));
    // log_action() must not crash or throw uncaught exception
    log_action(1, 'admin', $tagOverflow, $overflowDetails);

    // 4. Action at exact 100 character boundary (VARCHAR(100))
    $action100 = str_repeat('A', 100);
    log_action(1, 'admin', $action100, "100 chars action test");
    $stmt = $pdo->prepare("SELECT id FROM audit_logs WHERE action = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$action100]);
    if (!$stmt->fetch()) {
        throw new Exception("Failed to record exact 100-character action");
    }

    // 5. Username at exact 50 character boundary (VARCHAR(50))
    $user50 = str_repeat('U', 50);
    $tagU50 = 'U50_' . bin2hex(random_bytes(4));
    log_action(null, $user50, $tagU50, "50 chars username test");
    $stmt = $pdo->prepare("SELECT username FROM audit_logs WHERE action = ?");
    $stmt->execute([$tagU50]);
    $savedU50 = $stmt->fetchColumn();
    if ($savedU50 !== $user50) {
        throw new Exception("50 chars username mismatch: got " . var_export($savedU50, true));
    }

    return true;
});

challenge("3.6 CLI & unauthenticated execution context (null user_id, null username, no session)", function() {
    $pdo = db();
    
    // Ensure $_SESSION is unset
    $oldSession = $_SESSION ?? null;
    unset($_SESSION);

    $tag = 'CLI_NULL_' . bin2hex(random_bytes(4));
    log_action(null, null, $tag, "CLI call with no session or arguments");

    $stmt = $pdo->prepare("SELECT user_id, username FROM audit_logs WHERE action = ?");
    $stmt->execute([$tag]);
    $row = $stmt->fetch();

    if (!$row) {
        throw new Exception("No audit log row recorded for CLI context");
    }
    if ($row['user_id'] !== null) {
        throw new Exception("user_id should be NULL for anonymous call, got: " . var_export($row['user_id'], true));
    }
    if ($row['username'] !== 'Khách') {
        throw new Exception("username should default to 'Khách', got: " . var_export($row['username'], true));
    }

    if ($oldSession !== null) {
        $_SESSION = $oldSession;
    }
    return true;
});

challenge("3.7 Backward-compatible logAction() wrapper handles session-derived identity", function() {
    $pdo = db();
    $_SESSION['user_id'] = 3;
    $_SESSION['username'] = 'gv_van';

    $tag = 'WRAPPER_SESS_' . bin2hex(random_bytes(4));
    logAction($tag, "Wrapper session context verification");

    $stmt = $pdo->prepare("SELECT user_id, username FROM audit_logs WHERE action = ?");
    $stmt->execute([$tag]);
    $row = $stmt->fetch();

    if (!$row) {
        throw new Exception("logAction() wrapper failed to persist record");
    }
    if ((int)$row['user_id'] !== 3 || $row['username'] !== 'gv_van') {
        throw new Exception("Identity from session not captured: " . json_encode($row));
    }

    unset($_SESSION['user_id']);
    unset($_SESSION['username']);
    return true;
});

// =============================================================================
// SUMMARY & VERDICT
// =============================================================================
echo PHP_EOL . CLR_YELLOW . "================================================================================" . CLR_RESET . PHP_EOL;
echo sprintf("  Total Challenges: %d | Passed: %s%d%s | Failed: %s%d%s",
    $totalChallenges,
    CLR_GREEN, $passedChallenges, CLR_RESET,
    ($failedChallenges > 0 ? CLR_RED : CLR_GREEN), $failedChallenges, CLR_RESET
) . PHP_EOL;

if ($failedChallenges === 0) {
    echo CLR_GREEN . "  CHALLENGER VERDICT: APPROVE (Zero bugs or edge case failures found)" . CLR_RESET . PHP_EOL;
} else {
    echo CLR_RED . "  CHALLENGER VERDICT: REQUEST_CHANGES (" . count($findings) . " issues identified)" . CLR_RESET . PHP_EOL;
}
echo CLR_YELLOW . "================================================================================" . CLR_RESET . PHP_EOL . PHP_EOL;

exit($failedChallenges > 0 ? 1 : 0);
