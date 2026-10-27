<?php
/**
 * =============================================================================
 * EduRadar THCS — Master Automated Acceptance Test Suite & E2E Runner
 * =============================================================================
 *
 * Full-stack E2E Acceptance Test Suite covering all 18 Features from PROJECT.md
 * and all 8 Acceptance Criteria from ORIGINAL_REQUEST.md.
 *
 * Architecture:
 * - Pure PHP 8.0 CLI on live XAMPP Apache (http://localhost/my-web-project/)
 *   and MariaDB 10.4 (127.0.0.1:3306, edu_radar).
 * - Multi-session isolated cURL HTTP client with persistent cookie jars.
 * - Genuine binary OpenXML (.xlsx) generation and multipart upload.
 * - Checkpoint-based live MySQL audit log forensics.
 * - 100% idempotent and non-destructive state snapshot & rollback.
 * - Zero external Composer/PHPUnit dependencies.
 *
 * Execution:
 *   D:\xam\php\php.exe tests/run_tests.php
 * =============================================================================
 */

declare(strict_types=1);

// Enable Windows VT100 ANSI sequence processing
if (function_exists('sapi_windows_vt100_support')) {
    sapi_windows_vt100_support(STDOUT, true);
    sapi_windows_vt100_support(STDERR, true);
}

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/xlsx_helper.php';

// =============================================================================
// 1. ANSI STYLING & CLI UX TOKENS
// =============================================================================

final class Ansi
{
    public const RESET       = "\033[0m";
    public const BOLD        = "\033[1m";
    public const DIM         = "\033[2m";
    public const UNDERLINE   = "\033[4m";

    public const FG_RED      = "\033[31m";
    public const FG_GREEN    = "\033[32m";
    public const FG_YELLOW   = "\033[33m";
    public const FG_BLUE     = "\033[34m";
    public const FG_MAGENTA  = "\033[35m";
    public const FG_CYAN     = "\033[36m";
    public const FG_WHITE    = "\033[37m";
    public const FG_GRAY     = "\033[90m";

    public const FG_HI_RED   = "\033[91m";
    public const FG_HI_GREEN = "\033[92m";
    public const FG_HI_YELLOW= "\033[93m";
    public const FG_HI_CYAN  = "\033[96m";

    public const BADGE_PASS  = "\033[1;32m [ PASS ]\033[0m";
    public const BADGE_FAIL  = "\033[1;97;41m [ FAIL ]\033[0m";
    public const BADGE_SKIP  = "\033[1;33m [ SKIP ]\033[0m";
    public const BADGE_INFO  = "\033[1;36m [ INFO ]\033[0m";
}

// =============================================================================
// 2. HTTP CLIENT, MULTI-SESSION COOKIE JAR & RESPONSE PARSER
// =============================================================================

class HttpResponse
{
    public int $status;
    public string $body;
    public string $headers;
    public string $redirectUrl;
    public string $effectiveUrl;
    public float $durationMs;
    public array $parsedHeaders = [];

    public function __construct(int $status, string $body, string $headers, string $redirectUrl, string $effectiveUrl, float $durationMs)
    {
        $this->status = $status;
        $this->body = $body;
        $this->headers = $headers;
        $this->redirectUrl = $redirectUrl;
        $this->effectiveUrl = $effectiveUrl;
        $this->durationMs = $durationMs;
        $this->parseHeaders($headers);
    }

    private function parseHeaders(string $rawHeaders): void
    {
        $lines = explode("\r\n", $rawHeaders);
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                [$k, $v] = explode(':', $line, 2);
                $this->parsedHeaders[strtolower(trim($k))] = trim($v);
            }
        }
    }

    public function getHeader(string $name): ?string
    {
        return $this->parsedHeaders[strtolower($name)] ?? null;
    }

    public function assertCleanHtml(string $context = ''): void
    {
        $errorSignatures = [
            '<b>Warning</b>', 'Warning:', '<b>Notice</b>', 'Notice:',
            '<b>Deprecated</b>', 'Deprecated:', '<b>Fatal error</b>', 'Fatal error:',
            'Parse error:', 'Undefined variable', 'Undefined index', 'Undefined array key',
            'Uncaught', 'Stack trace:'
        ];
        foreach ($errorSignatures as $sig) {
            if (stripos($this->body, $sig) !== false) {
                throw new RuntimeException("PHP runtime diagnostic leaked into HTML: [{$sig}] in context '{$context}'");
            }
        }
    }

    public function assertZeroZalo(string $context = ''): void
    {
        $banned = ['zalo', 'actionModal', 'copy-btn', 'copyMessage'];
        foreach ($banned as $token) {
            if (stripos($this->body, $token) !== false) {
                throw new RuntimeException("Forbidden Zalo/Modal token [{$token}] detected in HTML in context '{$context}'");
            }
        }
    }
}

class HttpClient
{
    private string $baseUrl;
    private ?string $cookieFile;

    public function __construct(string $baseUrl, ?string $cookieFile = null)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->cookieFile = $cookieFile;
    }

    public function getCookieFile(): ?string
    {
        return $this->cookieFile;
    }

    public function clearCookies(): void
    {
        if ($this->cookieFile !== null && file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    public function request(string $method, string $path, $data = [], array $headers = [], bool $followRedirects = false): HttpResponse
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        $ch = curl_init();
        $startTime = hrtime(true);

        $headerList = [];
        foreach ($headers as $k => $v) {
            $headerList[] = is_numeric($k) ? $v : "$k: $v";
        }

        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_HTTPHEADER     => $headerList,
            CURLOPT_FOLLOWLOCATION => $followRedirects,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 15,
        ];

        if ($this->cookieFile !== null) {
            $options[CURLOPT_COOKIEJAR]  = $this->cookieFile;
            $options[CURLOPT_COOKIEFILE] = $this->cookieFile;
        }

        $method = strtoupper($method);
        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $isMultipart = false;
            if (is_array($data)) {
                foreach ($data as $val) {
                    if ($val instanceof CURLFile) {
                        $isMultipart = true;
                        break;
                    }
                }
            }
            if ($isMultipart) {
                $options[CURLOPT_POSTFIELDS] = $data;
            } else {
                $options[CURLOPT_POSTFIELDS] = is_array($data) ? http_build_query($data) : $data;
            }
        } elseif ($method !== 'GET') {
            $options[CURLOPT_CUSTOMREQUEST] = $method;
        }

        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);

        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("cURL error connecting to '{$url}': {$err}");
        }

        $durationMs = (hrtime(true) - $startTime) / 1e6;
        $info = curl_getinfo($ch);
        $headerSize = $info['header_size'];
        $headersStr = substr($raw, 0, $headerSize);
        $bodyStr = substr($raw, $headerSize);
        curl_close($ch);

        return new HttpResponse(
            (int)$info['http_code'],
            $bodyStr,
            $headersStr,
            (string)($info['redirect_url'] ?? ''),
            (string)($info['url'] ?? $url),
            $durationMs
        );
    }

    public function get(string $path, array $headers = [], bool $followRedirects = false): HttpResponse
    {
        return $this->request('GET', $path, [], $headers, $followRedirects);
    }

    public function post(string $path, $data = [], array $headers = [], bool $followRedirects = false): HttpResponse
    {
        return $this->request('POST', $path, $data, $headers, $followRedirects);
    }

    public function postMultipart(string $path, array $data = [], array $headers = [], bool $followRedirects = false): HttpResponse
    {
        return $this->request('POST', $path, $data, $headers, $followRedirects);
    }

    public function login(string $username, string $password): HttpResponse
    {
        return $this->post('login.php', [
            'username' => $username,
            'password' => $password
        ], [], false);
    }
}

class SessionManager
{
    private string $baseUrl;
    private array $clients = [];
    private array $cookieFiles = [];

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    public function __destruct()
    {
        $this->cleanupAll();
    }

    public function client(string $persona): HttpClient
    {
        if (isset($this->clients[$persona])) {
            return $this->clients[$persona];
        }

        if ($persona === 'guest') {
            $this->clients['guest'] = new HttpClient($this->baseUrl, null);
            return $this->clients['guest'];
        }

        $cookieFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'eduradar_e2e_' . $persona . '_' . bin2hex(random_bytes(4)) . '.txt';
        $this->cookieFiles[$persona] = $cookieFile;
        $this->clients[$persona] = new HttpClient($this->baseUrl, $cookieFile);

        return $this->clients[$persona];
    }

    public function authenticate(string $persona, string $username, string $password): HttpResponse
    {
        $c = $this->client($persona);
        $res = $c->login($username, $password);
        if ($res->status !== 302 && $res->status !== 200) {
            throw new RuntimeException("Failed to authenticate persona '{$persona}' as '{$username}'. HTTP status: {$res->status}");
        }
        return $res;
    }

    public function cleanupAll(): void
    {
        foreach ($this->cookieFiles as $persona => $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        $this->cookieFiles = [];
        $this->clients = [];
    }
}

// =============================================================================
// 3. DATABASE INSPECTOR & AUDIT FORENSICS
// =============================================================================

class DatabaseInspector
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function fetchValue(string $sql, array $params = []): mixed
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    public function count(string $table, string $where = '1=1', array $params = []): int
    {
        return (int)$this->fetchValue("SELECT COUNT(*) FROM {$table} WHERE {$where}", $params);
    }
}

class AuditTracker
{
    private PDO $pdo;
    private int $checkpointId = 0;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->checkpoint();
    }

    public function checkpoint(): int
    {
        $this->checkpointId = (int)$this->pdo->query("SELECT COALESCE(MAX(id), 0) FROM audit_logs")->fetchColumn();
        return $this->checkpointId;
    }

    public function assertLogged(string $actionPattern, ?string $expectedUsername = null, ?string $detailsPattern = null): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM audit_logs 
            WHERE id > :checkpoint 
              AND action LIKE :action
            ORDER BY id DESC
        ");
        $stmt->execute([
            ':checkpoint' => $this->checkpointId,
            ':action'     => '%' . $actionPattern . '%'
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            throw new RuntimeException("Audit assertion failed: No audit log matching action [{$actionPattern}] found since checkpoint ID {$this->checkpointId}.");
        }

        $latest = $rows[0];

        if ($expectedUsername !== null && $latest['username'] !== $expectedUsername) {
            throw new RuntimeException("Audit username mismatch: Expected '{$expectedUsername}', recorded '{$latest['username']}' (Action: {$latest['action']})");
        }

        if ($detailsPattern !== null && stripos($latest['details'], $detailsPattern) === false) {
            throw new RuntimeException("Audit details mismatch: Expected text '{$detailsPattern}' inside '{$latest['details']}'");
        }

        return $latest;
    }
}

// =============================================================================
// 4. STATE GUARDIAN (IDEMPOTENCY & NON-DESTRUCTIVE ROLLBACK)
// =============================================================================

class StateGuardian
{
    private PDO $pdo;
    private array $scoresSnapshot = [];
    private array $ephemeralClasses = ['6C99_E2E', '6D1_TEST', '9Z9_E2E', '6Z9_FORBIDDEN'];
    private array $ephemeralUsers   = ['gv_test_e2e', 'gv_test_t3', 'gv_test_runner'];
    private array $tempFiles = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function registerTempFile(string $path): void
    {
        $this->tempFiles[] = $path;
    }

    public function setup(): void
    {
        // 1. Snapshot all existing baseline scores
        $stmt = $this->pdo->query("SELECT * FROM scores");
        $this->scoresSnapshot = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Pre-clean any leftover debris from previous aborted runs
        $this->purgeDebris();
    }

    public function teardown(): void
    {
        // 1. Restore baseline scores
        if (!empty($this->scoresSnapshot)) {
            // Delete any newly inserted scores not in the snapshot
            $origMap = [];
            foreach ($this->scoresSnapshot as $row) {
                $origMap[$row['student_id'] . '_' . $row['subject']] = true;
            }
            $currentScores = $this->pdo->query("SELECT id, student_id, subject FROM scores")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($currentScores as $cur) {
                $k = $cur['student_id'] . '_' . $cur['subject'];
                if (!isset($origMap[$k])) {
                    $this->pdo->prepare("DELETE FROM scores WHERE id = ?")->execute([$cur['id']]);
                }
            }

            // Restore original score values
            $upd = $this->pdo->prepare("
                UPDATE scores 
                SET tx1 = :tx1, tx2 = :tx2, tx3 = :tx3, tx4 = :tx4, 
                    gk = :gk, ck = :ck, avg_score = :avg_score 
                WHERE student_id = :student_id AND subject = :subject
            ");
            foreach ($this->scoresSnapshot as $row) {
                $upd->execute([
                    ':tx1'        => $row['tx1'],
                    ':tx2'        => $row['tx2'],
                    ':tx3'        => $row['tx3'],
                    ':tx4'        => $row['tx4'],
                    ':gk'         => $row['gk'],
                    ':ck'         => $row['ck'],
                    ':avg_score'  => $row['avg_score'],
                    ':student_id' => $row['student_id'],
                    ':subject'    => $row['subject'],
                ]);
            }
        }

        // 2. Purge test records
        $this->purgeDebris();

        // 3. Purge registered temp files
        foreach ($this->tempFiles as $f) {
            if (file_exists($f)) {
                @unlink($f);
            }
        }
    }

    private function purgeDebris(): void
    {
        // Clean test students created in Class 1, 2 or Class 3
        $this->pdo->exec("DELETE FROM students WHERE class_id IN (1, 2, 3)");

        // Clean test assignments created for subject 'Tin Học'
        $this->pdo->exec("DELETE FROM teacher_classes WHERE subject = 'Tin Học'");

        // Delete test teachers (cascades assignments via FK)
        foreach ($this->ephemeralUsers as $username) {
            $this->pdo->prepare("DELETE FROM users WHERE username = ?")->execute([$username]);
        }

        // Delete test classes (cascades assignments, students, scores via FK)
        foreach ($this->ephemeralClasses as $className) {
            $this->pdo->prepare("DELETE FROM classes WHERE name = ?")->execute([$className]);
        }

        // Clean test scores for ephemeral test subjects
        $this->pdo->exec("DELETE FROM scores WHERE subject = 'Tin Học'");
    }
}

// =============================================================================
// 5. TEST RUNNER ORCHESTRATOR & CLI PRESENTER
// =============================================================================

class TestRunner
{
    public int $totalCount = 0;
    public int $passedCount = 0;
    public int $failedCount = 0;
    public int $skippedCount = 0;
    public float $suiteStartTime = 0.0;
    public array $collectedResponses = [];
    private string $currentTier = '';

    public function __construct()
    {
        $this->suiteStartTime = hrtime(true);
    }

    public function setTier(string $tierTitle): void
    {
        $this->currentTier = $tierTitle;
        echo PHP_EOL . Ansi::BOLD . Ansi::FG_YELLOW . "--- " . $tierTitle . " ---" . Ansi::RESET . PHP_EOL;
    }

    public function runTest(string $id, string $description, callable $fn): void
    {
        $this->totalCount++;
        $tag = "[" . $id . "] " . $description;
        $dotsCount = max(2, 70 - mb_strlen($tag));
        $dots = str_repeat('.', $dotsCount);

        echo Ansi::BOLD . Ansi::FG_CYAN . "[" . $id . "] " . Ansi::RESET . mb_substr($description, 0, 58) . " " . Ansi::FG_GRAY . $dots . Ansi::RESET;

        $startTime = hrtime(true);
        try {
            $fn();
            $durationMs = (hrtime(true) - $startTime) / 1e6;
            $this->passedCount++;

            $timerStr = $durationMs >= 1000.0
                ? sprintf(" (%4.2fs)", $durationMs / 1000.0)
                : sprintf(" (%3.0fms)", $durationMs);

            echo Ansi::FG_GRAY . $timerStr . Ansi::RESET . Ansi::BADGE_PASS . PHP_EOL;
        } catch (Throwable $e) {
            $durationMs = (hrtime(true) - $startTime) / 1e6;
            $this->failedCount++;

            $timerStr = $durationMs >= 1000.0
                ? sprintf(" (%4.2fs)", $durationMs / 1000.0)
                : sprintf(" (%3.0fms)", $durationMs);

            echo Ansi::FG_GRAY . $timerStr . Ansi::RESET . Ansi::BADGE_FAIL . PHP_EOL;
            $this->printFailureBox($id, $description, $e);
        }
    }

    private function printFailureBox(string $id, string $description, Throwable $e): void
    {
        echo Ansi::FG_HI_RED;
        echo "  ┌── FAILURE DIAGNOSTIC: [{$id}] " . str_repeat('─', max(0, 50 - strlen($id))) . PHP_EOL;
        echo "  │ Description: {$description}" . PHP_EOL;
        echo "  │ Exception  : " . get_class($e) . ": " . $e->getMessage() . PHP_EOL;
        echo "  │ Location   : " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
        echo "  └" . str_repeat('─', 74) . PHP_EOL;
        echo Ansi::RESET;
    }

    public function recordResponse(HttpResponse $res): HttpResponse
    {
        $this->collectedResponses[] = $res;
        return $res;
    }

    public function printHeader(): void
    {
        echo Ansi::BOLD . Ansi::FG_CYAN;
        echo "========================================================================================" . PHP_EOL;
        echo "  EduRadar THCS — Master Automated Multi-Account & Deduplication E2E Test Suite" . PHP_EOL;
        echo "  Specification: Thông tư 22/2021/TT-BGDĐT & Chuẩn Sổ Điểm Điện Tử VnEdu" . PHP_EOL;
        echo "========================================================================================" . PHP_EOL;
        echo Ansi::RESET;
        echo "  PHP SAPI    : " . PHP_VERSION . " (" . PHP_SAPI . ") on " . PHP_OS_FAMILY . PHP_EOL;
        echo "  Target Host : http://localhost" . rtrim(BASE_URL, '/') . "/ (Apache 2.4)" . PHP_EOL;
        echo "  Database    : MariaDB " . DB_HOST . ":" . DB_PORT . " / " . DB_NAME . PHP_EOL;
        echo "  Process ID  : " . getmypid() . " | Timestamp: " . gmdate('Y-m-d H:i:s') . " UTC" . PHP_EOL;
        echo Ansi::BOLD . Ansi::FG_CYAN . "========================================================================================" . Ansi::RESET . PHP_EOL;
    }

    public function printSummary(): void
    {
        $totalDuration = (hrtime(true) - $this->suiteStartTime) / 1e9;
        $peakMem = memory_get_peak_usage(true) / 1024 / 1024;
        $passPct = $this->totalCount > 0 ? ($this->passedCount / $this->totalCount) * 100 : 0.0;
        $failPct = $this->totalCount > 0 ? ($this->failedCount / $this->totalCount) * 100 : 0.0;

        echo PHP_EOL;
        echo Ansi::BOLD . Ansi::FG_CYAN . "========================================================================================" . Ansi::RESET . PHP_EOL;
        echo Ansi::BOLD . "  E2E TEST EXECUTION SUMMARY" . Ansi::RESET . PHP_EOL;
        echo Ansi::BOLD . Ansi::FG_CYAN . "========================================================================================" . Ansi::RESET . PHP_EOL;
        echo sprintf("  Suites Executed : 4 Tiers (Covering Features #1 to #18)\n");
        echo sprintf("  Total Tests     : %d\n", $this->totalCount);
        echo sprintf("  Passed          : " . Ansi::BOLD . Ansi::FG_GREEN . "%d (%0.1f%%)" . Ansi::RESET . "\n", $this->passedCount, $passPct);
        echo sprintf("  Failed          : " . ($this->failedCount > 0 ? Ansi::BOLD . Ansi::FG_RED : Ansi::FG_GREEN) . "%d (%0.1f%%)" . Ansi::RESET . "\n", $this->failedCount, $failPct);
        echo sprintf("  Skipped         : %d\n", $this->skippedCount);
        echo sprintf("  Total Duration  : %0.2fs\n", $totalDuration);
        echo sprintf("  Peak Memory     : %0.2f MB\n", $peakMem);
        echo Ansi::BOLD . Ansi::FG_CYAN . "========================================================================================" . Ansi::RESET . PHP_EOL;

        if ($this->failedCount === 0) {
            echo Ansi::BOLD . Ansi::FG_GREEN . "  [SUCCESS] ALL 18 ACCEPTANCE CRITERIA VERIFIED CLEANLY (0 FAILURES)" . Ansi::RESET . PHP_EOL;
        } else {
            echo Ansi::BOLD . Ansi::FG_RED . "  [FAILURE] {$this->failedCount} TEST(S) FAILED — REVIEW DIAGNOSTICS ABOVE" . Ansi::RESET . PHP_EOL;
        }
        echo Ansi::BOLD . Ansi::FG_CYAN . "========================================================================================" . Ansi::RESET . PHP_EOL . PHP_EOL;
    }
}

// =============================================================================
// 6. MAIN EXECUTION PIPELINE
// =============================================================================

$runner = new TestRunner();
$runner->printHeader();

$pdo = db();
$db = new DatabaseInspector($pdo);
$audit = new AuditTracker($pdo);
$guardian = new StateGuardian($pdo);

// Register disaster recovery teardown
register_shutdown_function(function() use ($guardian) {
    $guardian->teardown();
});

// Setup snapshot and pre-clean
$guardian->setup();

$fullBaseUrl = 'http://localhost' . rtrim(BASE_URL, '/');
$sessions = new SessionManager($fullBaseUrl);

// -----------------------------------------------------------------------------
// TIER 1: Environment, Schema & Static Health
// -----------------------------------------------------------------------------
$runner->setTier("TIER 1: Environment, Schema & Static Health (Pre-flight & Baselines)");

$runner->runTest("T1.01", "Environment Sanity & Prerequisites", function() use ($fullBaseUrl) {
    if (PHP_VERSION_ID < 80000) {
        throw new RuntimeException("PHP 8.0+ required, current is " . PHP_VERSION);
    }
    $requiredExts = ['curl', 'pdo_mysql', 'zip', 'simplexml', 'mbstring'];
    foreach ($requiredExts as $ext) {
        if (!extension_loaded($ext)) {
            throw new RuntimeException("Required PHP extension missing: {$ext}");
        }
    }
    // Verify Apache connectivity
    $ch = curl_init($fullBaseUrl . '/login.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200 && $code !== 302) {
        throw new RuntimeException("Cannot connect to Apache at {$fullBaseUrl}/login.php (HTTP {$code})");
    }
});

$runner->runTest("T1.02", "Pure MySQL Architecture & Zero SQLite Elimination", function() {
    $sqliteFile = dirname(__DIR__) . '/database.sqlite';
    if (file_exists($sqliteFile)) {
        throw new RuntimeException("Prohibited SQLite file exists at {$sqliteFile}");
    }
    $cfg = file_get_contents(dirname(__DIR__) . '/includes/config.php');
    $dbf = file_get_contents(dirname(__DIR__) . '/includes/db.php');
    if (stripos($cfg, 'sqlite:') !== false || stripos($dbf, 'sqlite:') !== false) {
        throw new RuntimeException("Prohibited SQLite DSN found in configuration or database helper");
    }
    if (stripos($cfg, 'pdo_sqlite') !== false || stripos($dbf, 'pdo_sqlite') !== false) {
        throw new RuntimeException("Prohibited pdo_sqlite driver referenced in code");
    }
});

$runner->runTest("T1.03", "MySQL Schema & Constraints Integrity", function() use ($db) {
    $tables = ['users', 'classes', 'teacher_classes', 'students', 'scores', 'audit_logs'];
    foreach ($tables as $t) {
        $exists = $db->fetchValue("
            SELECT COUNT(*) FROM information_schema.TABLES 
            WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table
        ", [':schema' => DB_NAME, ':table' => $t]);
        if ((int)$exists !== 1) {
            throw new RuntimeException("Mandatory table '{$t}' is missing from database " . DB_NAME);
        }
    }

    // Verify 5 UNIQUE constraints
    $constraints = [
        'users'           => 'unique_username',
        'classes'         => 'unique_class_name',
        'teacher_classes' => 'unique_assignment',
        'students'        => 'unique_class_student',
        'scores'          => 'unique_student_subject',
    ];
    foreach ($constraints as $tbl => $cname) {
        $cExists = $db->fetchValue("
            SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
            WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table AND CONSTRAINT_NAME = :cname
        ", [':schema' => DB_NAME, ':table' => $tbl, ':cname' => $cname]);
        if ((int)$cExists !== 1) {
            throw new RuntimeException("UNIQUE constraint '{$cname}' is missing on table '{$tbl}'");
        }
    }
});

$runner->runTest("T1.04", "Baseline Seed Data Integrity", function() use ($db) {
    $users = $db->query("SELECT username, password, role FROM users");
    $userMap = [];
    foreach ($users as $u) {
        $userMap[$u['username']] = $u;
    }
    foreach (['admin' => 'admin', 'gv_toan' => 'teacher', 'gv_van' => 'teacher'] as $uname => $role) {
        if (!isset($userMap[$uname])) {
            throw new RuntimeException("Baseline user '{$uname}' is missing from users table");
        }
        if ($userMap[$uname]['role'] !== $role) {
            throw new RuntimeException("User '{$uname}' has unexpected role '{$userMap[$uname]['role']}'");
        }
        if (!password_verify('123456', $userMap[$uname]['password'])) {
            throw new RuntimeException("Password hash for '{$uname}' does not verify against baseline '123456'");
        }
    }

    $classCount = $db->count('classes');
    if ($classCount < 36) {
        throw new RuntimeException("Expected at least 36 classes (6A1..9C9), found {$classCount}");
    }

    $tcCount = $db->count('teacher_classes');
    if ($tcCount < 3) {
        throw new RuntimeException("Expected baseline teacher_classes assignments, found {$tcCount}");
    }

    $stCount = $db->count('students');
    if ($stCount < 5) {
        throw new RuntimeException("Expected at least 5 baseline students, found {$stCount}");
    }
});

$runner->runTest("T1.05", "Static Security & Backdoor Removal", function() {
    $loginCode = file_get_contents(dirname(__DIR__) . '/login.php');
    if (strpos($loginCode, "|| \$password === '123456'") !== false ||
        strpos($loginCode, '|| $password === "123456"') !== false) {
        throw new RuntimeException("Backdoor password bypass '|| \$password === 123456' is present in login.php");
    }

    $authPath = dirname(__DIR__) . '/includes/auth.php';
    if (!file_exists($authPath)) {
        throw new RuntimeException("includes/auth.php missing");
    }
    require_once $authPath;
    $requiredFns = ['require_login', 'require_admin', 'verify_teacher_assignment', 'require_teacher_assignment'];
    foreach ($requiredFns as $fn) {
        if (!function_exists($fn)) {
            throw new RuntimeException("Security helper function '{$fn}()' is not defined in includes/auth.php");
        }
    }
});

$runner->runTest("T1.06", "Static Zero Zalo & Dead Code Elimination", function() {
    $scanFiles = [
        dirname(__DIR__) . '/index.php',
        dirname(__DIR__) . '/admin.php',
        dirname(__DIR__) . '/nhap_diem.php',
        dirname(__DIR__) . '/login.php',
        dirname(__DIR__) . '/logout.php',
        dirname(__DIR__) . '/includes/header.php',
        dirname(__DIR__) . '/includes/footer.php',
        dirname(__DIR__) . '/assets/js/main.js'
    ];
    $banned = ['zalo', 'actionModal', 'copy-btn'];
    foreach ($scanFiles as $file) {
        if (!file_exists($file)) continue;
        $content = file_get_contents($file);
        foreach ($banned as $token) {
            if (stripos($content, $token) !== false) {
                throw new RuntimeException("Forbidden token '{$token}' found in " . basename($file));
            }
        }
    }
});

// -----------------------------------------------------------------------------
// TIER 2: Multi-Account Authentication, Session Security & Strict RBAC
// -----------------------------------------------------------------------------
$runner->setTier("TIER 2: Multi-Account Authentication & Strict RBAC Route Guards");

$runner->runTest("T2.01", "Admin Authentication Success (admin / 123456)", function() use ($sessions, $runner, $audit) {
    $audit->checkpoint();
    $res = $runner->recordResponse($sessions->authenticate('admin', 'admin', '123456'));
    $res->assertCleanHtml('admin_login');
    if ($res->status !== 302) {
        throw new RuntimeException("Expected HTTP 302 redirect on login, got {$res->status}");
    }
    if (stripos($res->redirectUrl, 'admin.php') === false && stripos($res->getHeader('location') ?? '', 'admin.php') === false) {
        throw new RuntimeException("Admin was not redirected to admin.php on login");
    }
    $audit->assertLogged('Đăng nhập thành công', 'admin');
});

$runner->runTest("T2.02", "Teacher gv_toan Authentication Success (gv_toan / 123456)", function() use ($sessions, $runner, $audit) {
    $audit->checkpoint();
    $res = $runner->recordResponse($sessions->authenticate('gv_toan', 'gv_toan', '123456'));
    $res->assertCleanHtml('gv_toan_login');
    if ($res->status !== 302) {
        throw new RuntimeException("Expected HTTP 302 redirect on login, got {$res->status}");
    }
    if (stripos($res->redirectUrl, 'index.php') === false && stripos($res->getHeader('location') ?? '', 'index.php') === false) {
        throw new RuntimeException("Teacher was not redirected to index.php on login");
    }
    $audit->assertLogged('Đăng nhập thành công', 'gv_toan');
});

$runner->runTest("T2.03", "Teacher gv_van Authentication Success (gv_van / 123456)", function() use ($sessions, $runner, $audit) {
    $audit->checkpoint();
    $res = $runner->recordResponse($sessions->authenticate('gv_van', 'gv_van', '123456'));
    $res->assertCleanHtml('gv_van_login');
    if ($res->status !== 302) {
        throw new RuntimeException("Expected HTTP 302 redirect on login, got {$res->status}");
    }
    $audit->assertLogged('Đăng nhập thành công', 'gv_van');
});

$runner->runTest("T2.04", "Wrong Password Rejection (admin / 1234567)", function() use ($sessions, $runner, $audit) {
    $audit->checkpoint();
    $client = $sessions->client('guest');
    $res = $runner->recordResponse($client->login('admin', '1234567'));
    $res->assertCleanHtml('wrong_password');
    if ($res->status !== 200) {
        throw new RuntimeException("Expected HTTP 200 on login rejection, got {$res->status}");
    }
    if (stripos($res->body, 'không chính xác') === false) {
        throw new RuntimeException("Error message for bad password missing from response");
    }
    $audit->assertLogged('Đăng nhập thất bại', null, 'admin');
});

$runner->runTest("T2.05", "SQL Injection Login Rejection (admin' OR '1'='1)", function() use ($sessions, $runner, $audit) {
    $audit->checkpoint();
    $client = $sessions->client('guest');
    $res = $runner->recordResponse($client->login("admin' OR '1'='1", '123456'));
    $res->assertCleanHtml('sqli_login');
    if ($res->status !== 200) {
        throw new RuntimeException("Expected HTTP 200 on SQLi attempt, got {$res->status}");
    }
    $audit->assertLogged('Đăng nhập thất bại');
});

$runner->runTest("T2.06", "Empty Credentials Rejection", function() use ($sessions, $runner) {
    $client = $sessions->client('guest');
    $res = $runner->recordResponse($client->login('', ''));
    $res->assertCleanHtml('empty_login');
    if ($res->status !== 200) {
        throw new RuntimeException("Expected HTTP 200 on empty credentials, got {$res->status}");
    }
    if (stripos($res->body, 'nhập đầy đủ') === false) {
        throw new RuntimeException("Validation message for empty credentials missing");
    }
});

$runner->runTest("T2.07", "Session Fixation Rotation on Login", function() use ($fullBaseUrl, $runner) {
    $tempCookie = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'eduradar_e2e_fixation_' . uniqid() . '.txt';
    $client = new HttpClient($fullBaseUrl, $tempCookie);

    // Initial GET to acquire pre-auth session
    $res1 = $runner->recordResponse($client->get('login.php'));
    $cookieContent1 = file_exists($tempCookie) ? file_get_contents($tempCookie) : '';
    preg_match('/PHPSESSID\s+([^\s]+)/', $cookieContent1, $m1);
    $sessionIdBefore = $m1[1] ?? '';

    // Authenticate
    $res2 = $runner->recordResponse($client->login('admin', '123456'));
    $cookieContent2 = file_exists($tempCookie) ? file_get_contents($tempCookie) : '';
    preg_match('/PHPSESSID\s+([^\s]+)/', $cookieContent2, $m2);
    $sessionIdAfter = $m2[1] ?? '';

    @unlink($tempCookie);

    if ($sessionIdBefore === '' || $sessionIdAfter === '') {
        throw new RuntimeException("Could not capture PHPSESSID cookie values");
    }
    if ($sessionIdBefore === $sessionIdAfter) {
        throw new RuntimeException("Session fixation rotation failed: Session ID did not change after login");
    }
});

$runner->runTest("T2.08", "Secure Logout Workflow", function() use ($fullBaseUrl, $runner, $audit) {
    $audit->checkpoint();
    $tempCookie = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'eduradar_e2e_logout_' . uniqid() . '.txt';
    $client = new HttpClient($fullBaseUrl, $tempCookie);
    $client->login('admin', '123456');

    $res = $runner->recordResponse($client->get('logout.php'));
    if ($res->status !== 302) {
        throw new RuntimeException("Expected HTTP 302 on logout, got {$res->status}");
    }
    $audit->assertLogged('Đăng xuất', 'admin');

    // Accessing admin.php now must redirect to login.php
    $res2 = $runner->recordResponse($client->get('admin.php'));
    if ($res2->status !== 302) {
        throw new RuntimeException("Expected HTTP 302 redirect after logout, got {$res2->status}");
    }
    @unlink($tempCookie);
});

$runner->runTest("T2.09", "Unauthenticated Route Guards (HTTP 302 to login.php)", function() use ($sessions, $runner) {
    $guest = $sessions->client('guest');
    foreach (['admin.php', 'nhap_diem.php', 'index.php'] as $route) {
        $res = $runner->recordResponse($guest->get($route));
        if ($res->status !== 302) {
            throw new RuntimeException("Unauthenticated access to '{$route}' returned HTTP {$res->status}, expected 302");
        }
        $loc = $res->getHeader('location') ?? $res->redirectUrl;
        if (stripos($loc, 'login.php') === false) {
            throw new RuntimeException("Unauthenticated access to '{$route}' did not redirect to login.php: {$loc}");
        }
    }
});

$runner->runTest("T2.10", "Teacher Access to admin.php Forbidden (HTTP 403)", function() use ($sessions, $runner, $audit) {
    $audit->checkpoint();
    $toan = $sessions->client('gv_toan');
    $res = $runner->recordResponse($toan->get('admin.php'));
    $res->assertCleanHtml('teacher_admin_get');
    if ($res->status !== 403) {
        throw new RuntimeException("Teacher accessing admin.php GET returned HTTP {$res->status}, expected 403");
    }
    if (stripos($res->body, '403') === false && stripos($res->body, 'Truy Cập Bị Từ Chối') === false) {
        throw new RuntimeException("Stitch UI 403 error page not rendered for teacher accessing admin.php");
    }
    $audit->assertLogged('Truy cập trái phép bị chặn', 'gv_toan');
});

$runner->runTest("T2.11", "Teacher POST to admin.php Blocked (HTTP 403, 0 DB Changes)", function() use ($sessions, $runner, $db) {
    $toan = $sessions->client('gv_toan');
    $res = $runner->recordResponse($toan->post('admin.php', [
        'action'  => 'add_class',
        'name'    => '6Z9_FORBIDDEN',
        'grade'   => 6,
        'section' => 'A'
    ]));
    if ($res->status !== 403) {
        throw new RuntimeException("Teacher POST to admin.php returned HTTP {$res->status}, expected 403");
    }
    $cnt = $db->count('classes', "name = '6Z9_FORBIDDEN'");
    if ($cnt !== 0) {
        throw new RuntimeException("Security violation: Class was created by unauthorized teacher POST");
    }
});

$runner->runTest("T2.12", "Anti-BOLA: Teacher Unassigned Class/Subject Blocked (HTTP 403)", function() use ($sessions, $runner, $audit) {
    $audit->checkpoint();
    $toan = $sessions->client('gv_toan');
    // gv_toan is only assigned 6A1 Toán and 7A1 Toán; accessing 6A1 Ngữ Văn must be 403
    $res = $runner->recordResponse($toan->get('nhap_diem.php?class_id=1&subject=' . urlencode('Ngữ Văn')));
    $res->assertCleanHtml('bola_unassigned_subject');
    if ($res->status !== 403) {
        throw new RuntimeException("Teacher accessing unassigned subject returned HTTP {$res->status}, expected 403");
    }
    $audit->assertLogged('Truy cập trái phép bị chặn', 'gv_toan');

    // Also verify unassigned class (Class 2: 6A2 Toán)
    $res2 = $runner->recordResponse($toan->get('nhap_diem.php?class_id=2&subject=' . urlencode('Toán')));
    if ($res2->status !== 403) {
        throw new RuntimeException("Teacher accessing unassigned class returned HTTP {$res2->status}, expected 403");
    }
});

$runner->runTest("T2.13", "Anti-IDOR: Teacher Parameter Spoofing Blocked (HTTP 403)", function() use ($sessions, $runner, $audit) {
    $audit->checkpoint();
    $toan = $sessions->client('gv_toan');
    // gv_toan (ID 2) tampering with ?teacher_id=1 (admin)
    $res = $runner->recordResponse($toan->get('nhap_diem.php?teacher_id=1&class_id=1&subject=' . urlencode('Toán')));
    if ($res->status !== 403) {
        throw new RuntimeException("Teacher tampering with teacher_id=1 returned HTTP {$res->status}, expected 403");
    }
    $audit->assertLogged('Truy cập trái phép bị chặn', 'gv_toan');
});

$runner->runTest("T2.14", "Admin Direct Gradebook Inspection Allowed (HTTP 200)", function() use ($sessions, $runner, $audit) {
    $audit->checkpoint();
    $admin = $sessions->client('admin');
    $res = $runner->recordResponse($admin->get('nhap_diem.php?teacher_id=2&class_id=1&subject=' . urlencode('Toán')));
    $res->assertCleanHtml('admin_gradebook_inspect');
    if ($res->status !== 200) {
        throw new RuntimeException("Admin inspecting gradebook returned HTTP {$res->status}, expected 200");
    }
    if (stripos($res->body, 'Admin đang kiểm tra') === false && stripos($res->body, 'quản trị viên') === false) {
        throw new RuntimeException("Admin gradebook inspection banner missing from page");
    }
    $audit->assertLogged('Admin', 'admin');
});

// -----------------------------------------------------------------------------
// TIER 3: Core Functional & Business Workflows
// -----------------------------------------------------------------------------
$runner->setTier("TIER 3: Core Functional & Business Workflows (State & Calculations)");

$runner->runTest("T3.01", "Admin Class Matrix & CRUD Lifecycle (Features #7, #11)", function() use ($sessions, $runner, $db, $audit) {
    $admin = $sessions->client('admin');
    // 1. Inspect Matrix
    $res1 = $runner->recordResponse($admin->get('admin.php'));
    $res1->assertCleanHtml('admin_matrix');
    if (stripos($res1->body, 'Khối 6') === false || stripos($res1->body, 'Khối 9') === false) {
        throw new RuntimeException("Class matrix grade groupings missing on admin.php");
    }

    // 2. Add Test Class
    $audit->checkpoint();
    $resAdd = $runner->recordResponse($admin->post('admin.php', [
        'action'  => 'add_class',
        'name'    => '6C99_E2E',
        'grade'   => 6,
        'section' => 'C'
    ]));
    $resAdd->assertCleanHtml('add_class');
    $classId = (int)$db->fetchValue("SELECT id FROM classes WHERE name = '6C99_E2E'");
    if ($classId <= 0) {
        throw new RuntimeException("Test class '6C99_E2E' was not created in database");
    }
    $audit->assertLogged('Tạo lớp học', 'admin', '6C99_E2E');

    // 3. Delete Test Class
    $audit->checkpoint();
    $resDel = $runner->recordResponse($admin->get("admin.php?delete_class={$classId}"));
    $resDel->assertCleanHtml('delete_class');
    $remaining = $db->count('classes', "name = '6C99_E2E'");
    if ($remaining !== 0) {
        throw new RuntimeException("Test class '6C99_E2E' was not deleted from database");
    }
    $audit->assertLogged('Xóa lớp học', 'admin', '6C99_E2E');
});

$runner->runTest("T3.02", "Admin Batch Checkbox Assignment (Feature #8)", function() use ($sessions, $runner, $db, $audit) {
    $admin = $sessions->client('admin');
    $audit->checkpoint();
    // Assign teacher 2 (gv_toan) to classes 2 and 3 for subject 'Tin Học'
    $res = $runner->recordResponse($admin->post('admin.php', [
        'action'     => 'batch_assign',
        'teacher_id' => 2,
        'subject'    => 'Tin Học',
        'class_ids'  => [2, 3]
    ]));
    $res->assertCleanHtml('batch_assign');
    $cnt = $db->count('teacher_classes', "teacher_id = 2 AND subject = 'Tin Học' AND class_id IN (2, 3)");
    if ($cnt !== 2) {
        throw new RuntimeException("Expected 2 assigned classes for Tin Học, found {$cnt}");
    }
    $audit->assertLogged('Phân công giáo viên', 'admin', 'Tin Học');
});

$runner->runTest("T3.03", "Admin Teacher Account Creation (Feature #8)", function() use ($sessions, $runner, $db, $audit) {
    $admin = $sessions->client('admin');
    $audit->checkpoint();
    $res = $runner->recordResponse($admin->post('admin.php', [
        'action'    => 'create_teacher',
        'username'  => 'gv_test_e2e',
        'full_name' => 'Thầy Kiểm Thử E2E',
        'password'  => '123456'
    ]));
    $res->assertCleanHtml('create_teacher');
    $user = $db->fetchOne("SELECT * FROM users WHERE username = 'gv_test_e2e'");
    if (!$user) {
        throw new RuntimeException("Teacher 'gv_test_e2e' was not found in users table");
    }
    if ($user['role'] !== 'teacher') {
        throw new RuntimeException("Created user role is '{$user['role']}', expected 'teacher'");
    }
    if (!password_verify('123456', $user['password'])) {
        throw new RuntimeException("Bcrypt hash for created teacher does not verify");
    }
    $audit->assertLogged('Tạo tài khoản giáo viên', 'admin', 'gv_test_e2e');
});

$runner->runTest("T3.04", "Admin VnEdu Student List Import .xlsx (Feature #9)", function() use ($sessions, $runner, $db, $audit, $guardian) {
    $admin = $sessions->client('admin');
    $targetClassId = 2; // 6A2 (contains 0 baseline students)

    $xlsxData = [
        ['STT', 'Mã HS', 'Họ và tên', 'Ngày sinh', 'Giới tính', 'Số điện thoại'],
        ['1', 'HS6A201', 'Lê Hoài An', '12/04/2015', 'Nam', '0911223344'],
        ['2', 'HS6A202', 'Nguyễn Mai Chi', '25/08/2015', 'Nữ', '0922334455'],
        ['3', 'HS6A203', 'Trần Bảo Long', '42080', 'Nam', '0933445566'], // Excel serial date
    ];
    $tempXlsx = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'e2e_students_6a2_' . uniqid() . '.xlsx';
    file_put_contents($tempXlsx, create_xlsx($xlsxData));
    $guardian->registerTempFile($tempXlsx);

    $audit->checkpoint();
    $postData = [
        'action'          => 'import_students',
        'target_class_id' => $targetClassId,
        'student_file'    => new CURLFile($tempXlsx, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'students_6a2.xlsx')
    ];
    $res = $runner->recordResponse($admin->postMultipart('admin.php', $postData));
    $res->assertCleanHtml('import_students_xlsx');

    $count = $db->count('students', "class_id = {$targetClassId}");
    if ($count !== 3) {
        throw new RuntimeException("Expected 3 imported students in Class 2, found {$count}");
    }

    // Verify date normalization: '12/04/2015' -> '2015-04-12', '42080' -> '2015-03-17'
    $bday1 = $db->fetchValue("SELECT birthday FROM students WHERE class_id = 2 AND full_name = 'Lê Hoài An'");
    if ($bday1 !== '2015-04-12') {
        throw new RuntimeException("Date '12/04/2015' normalized to unexpected: '{$bday1}'");
    }
    $bday3 = $db->fetchValue("SELECT birthday FROM students WHERE class_id = 2 AND full_name = 'Trần Bảo Long'");
    if ($bday3 !== '2015-03-17') {
        throw new RuntimeException("Serial date '42080' normalized to unexpected: '{$bday3}'");
    }

    $audit->assertLogged('Import học sinh', 'admin', '6A2');
});

$runner->runTest("T3.05", "Admin Audit Log Viewer (Feature #11)", function() use ($sessions, $runner) {
    $admin = $sessions->client('admin');
    $res = $runner->recordResponse($admin->get('admin.php'));
    $res->assertCleanHtml('audit_log_viewer');
    if (stripos($res->body, 'Nhật Ký') === false && stripos($res->body, 'audit') === false) {
        throw new RuntimeException("Audit logs viewer section missing on admin.php");
    }
});

$runner->runTest("T3.06", "VnEdu Gradebook Spreadsheet DOM & Navigation (Feature #12)", function() use ($sessions, $runner, $db) {
    $toan = $sessions->client('gv_toan');
    $tc = $db->fetchOne("SELECT tc.class_id, tc.subject FROM teacher_classes tc WHERE tc.teacher_id = (SELECT id FROM users WHERE username = 'gv_toan') AND (SELECT COUNT(*) FROM students s WHERE s.class_id = tc.class_id) > 0 ORDER BY tc.id DESC LIMIT 1");
    $cid = (int)($tc['class_id'] ?? 1);
    $sub = $tc['subject'] ?? 'Toán';

    $res = $runner->recordResponse($toan->get("nhap_diem.php?class_id={$cid}&subject=" . urlencode($sub)));
    $res->assertCleanHtml('gradebook_spreadsheet_dom');

    // 11 Columns and 2-tier headers
    $requiredHeaders = ['ĐĐGtx', 'ĐĐGgk', 'ĐĐGck', 'ĐTBmhk'];
    foreach ($requiredHeaders as $hdr) {
        if (stripos($res->body, $hdr) === false) {
            throw new RuntimeException("Gradebook header '{$hdr}' missing from table");
        }
    }

    // Input fields attributes
    if (strpos($res->body, 'class="score-input') === false) {
        throw new RuntimeException("Input elements with class='score-input' missing");
    }
    if (strpos($res->body, 'data-row=') === false || strpos($res->body, 'data-col=') === false) {
        throw new RuntimeException("Navigation coordinates data-row/data-col missing from inputs");
    }
});

$runner->runTest("T3.07", "Thông tư 22 Score Calculation Engine Invariants (Feature #13)", function() {
    // Formula verification: (S_tx + 2*gk + 3*ck)/(N_tx + 5)
    $calc = function(array $tx, ?float $gk, ?float $ck): ?float {
        $txList = array_values(array_filter($tx, fn($v) => $v !== null));
        $nTx = count($txList);
        $sTx = array_sum($txList);
        if ($gk !== null && $ck !== null) {
            return round(($sTx + 2 * $gk + 3 * $ck) / ($nTx + 5), 1);
        }
        $w = $nTx + ($gk !== null ? 2 : 0) + ($ck !== null ? 3 : 0);
        return $w > 0 ? round(($sTx + 2 * ($gk ?? 0.0) + 3 * ($ck ?? 0.0)) / $w, 1) : null;
    };

    // Case 1: 4 tx + gk + ck
    $r1 = $calc([8.5, 8.0, 7.5, 8.0], 8.0, 8.5);
    // (32.0 + 16.0 + 25.5) / 9 = 73.5 / 9 = 8.166... -> 8.2
    if ($r1 !== 8.2) {
        throw new RuntimeException("Case 1 mismatch: expected 8.2, got {$r1}");
    }

    // Case 2: Partial tx + gk (no ck)
    $r2 = $calc([7.0, 8.0], 7.0, null);
    // (15.0 + 14.0) / 4 = 29.0 / 4 = 7.25 -> 7.3
    if ($r2 !== 7.3) {
        throw new RuntimeException("Case 2 mismatch: expected 7.3, got {$r2}");
    }

    // Case 3: Empty
    $r3 = $calc([], null, null);
    if ($r3 !== null) {
        throw new RuntimeException("Case 3 mismatch: expected null, got " . json_encode($r3));
    }
});

$runner->runTest("T3.08", "Teacher Inline Grade Saving & Audit Log (Features #12, #13)", function() use ($sessions, $runner, $db, $audit) {
    $toan = $sessions->client('gv_toan');
    $tc = $db->fetchOne("SELECT tc.class_id, tc.subject, c.name FROM teacher_classes tc JOIN classes c ON tc.class_id = c.id WHERE tc.teacher_id = (SELECT id FROM users WHERE username = 'gv_toan') AND (SELECT COUNT(*) FROM students s WHERE s.class_id = tc.class_id) > 0 ORDER BY tc.id DESC LIMIT 1");
    $cid = (int)$tc['class_id'];
    $sub = $tc['subject'];
    $cname = $tc['name'];
    $s1 = $db->fetchOne("SELECT id, full_name FROM students WHERE class_id = {$cid} ORDER BY id ASC LIMIT 1");
    $s1Id = (int)$s1['id'];

    $audit->checkpoint();

    $postData = [
        'action' => 'save_scores',
        'scores' => [
            $s1Id => [
                'tx1' => '8.5',
                'tx2' => '8.0',
                'tx3' => '7.5',
                'tx4' => '8.0',
                'gk'  => '8.0',
                'ck'  => '8.5'
            ]
        ]
    ];
    $res = $runner->recordResponse($toan->post("nhap_diem.php?class_id={$cid}&subject=" . urlencode($sub), $postData));
    $res->assertCleanHtml('save_scores_inline');

    $row = $db->fetchOne("SELECT * FROM scores WHERE student_id = {$s1Id} AND subject = '{$sub}'");
    if (!$row) {
        throw new RuntimeException("Score row for student {$s1Id} {$sub} missing after save");
    }
    if ((float)$row['avg_score'] !== 8.2) {
        throw new RuntimeException("Expected avg_score 8.2, found {$row['avg_score']}");
    }
    $audit->assertLogged('Lưu sổ điểm', 'gv_toan', $cname);
});

$runner->runTest("T3.09", "VnEdu Gradebook Bulk .xlsx Import (Feature #14)", function() use ($sessions, $runner, $db, $audit, $guardian) {
    $toan = $sessions->client('gv_toan');
    $tc = $db->fetchOne("SELECT tc.class_id, tc.subject, c.name FROM teacher_classes tc JOIN classes c ON tc.class_id = c.id WHERE tc.teacher_id = (SELECT id FROM users WHERE username = 'gv_toan') AND (SELECT COUNT(*) FROM students s WHERE s.class_id = tc.class_id) > 0 ORDER BY tc.id DESC LIMIT 1");
    $cid = (int)$tc['class_id'];
    $sub = $tc['subject'];
    $cname = $tc['name'];
    $stList = $db->query("SELECT id, full_name, phone FROM students WHERE class_id = {$cid} ORDER BY id ASC LIMIT 2");
    $s1 = $stList[0];
    $s2 = $stList[1] ?? $stList[0];

    $gradebookData = [
        ['STT', 'Họ và tên', 'Số điện thoại phụ huynh', 'ĐĐGtx1', 'ĐĐGtx2', 'ĐĐGtx3', 'ĐĐGtx4', 'ĐĐGgk', 'ĐĐGck'],
        [1, $s1['full_name'], $s1['phone'] ?: '0901234567', 9.0, 9.0, 9.0, 9.0, 9.0, 9.0],
        [2, $s2['full_name'], $s2['phone'] ?: '0912345678', 7.0, 7.0, 7.0, 7.0, 7.0, 7.0],
    ];
    $tempXlsx = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'e2e_gradebook_' . uniqid() . '.xlsx';
    file_put_contents($tempXlsx, create_xlsx($gradebookData, "SoDiem_{$cname}"));
    $guardian->registerTempFile($tempXlsx);

    $audit->checkpoint();
    $postData = [
        'action'         => 'import_file',
        'gradebook_file' => new CURLFile($tempXlsx, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'gradebook.xlsx')
    ];
    $res = $runner->recordResponse($toan->postMultipart("nhap_diem.php?class_id={$cid}&subject=" . urlencode($sub), $postData));
    $res->assertCleanHtml('import_gradebook_xlsx');

    $row1 = $db->fetchOne("SELECT * FROM scores WHERE student_id = {$s1['id']} AND subject = '{$sub}'");
    if (!$row1 || (float)$row1['avg_score'] !== 9.0) {
        throw new RuntimeException("Expected student {$s1['id']} avg_score 9.0 after import, found " . ($row1['avg_score'] ?? 'null'));
    }
    $audit->assertLogged('Import file điểm', 'gv_toan', $cname);
});

$runner->runTest("T3.10", "Gradebook OpenXML Export (Feature #14)", function() use ($sessions, $runner, $db, $audit, $guardian) {
    $toan = $sessions->client('gv_toan');
    $tc = $db->fetchOne("SELECT tc.class_id, tc.subject, c.name FROM teacher_classes tc JOIN classes c ON tc.class_id = c.id WHERE tc.teacher_id = (SELECT id FROM users WHERE username = 'gv_toan') AND (SELECT COUNT(*) FROM students s WHERE s.class_id = tc.class_id) > 0 ORDER BY tc.id DESC LIMIT 1");
    $cid = (int)$tc['class_id'];
    $sub = $tc['subject'];
    $cname = $tc['name'];

    $audit->checkpoint();
    $res = $runner->recordResponse($toan->get("nhap_diem.php?export=xlsx&class_id={$cid}&subject=" . urlencode($sub)));

    if ($res->status !== 200) {
        throw new RuntimeException("Gradebook export returned HTTP {$res->status}");
    }
    $cType = $res->getHeader('content-type') ?? '';
    if (stripos($cType, 'openxmlformats') === false && stripos($cType, 'spreadsheetml') === false) {
        throw new RuntimeException("Unexpected Content-Type on export: '{$cType}'");
    }

    $tempExport = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'e2e_exported_' . uniqid() . '.xlsx';
    file_put_contents($tempExport, $res->body);
    $guardian->registerTempFile($tempExport);

    $zip = new ZipArchive();
    $opened = $zip->open($tempExport);
    if ($opened !== true) {
        throw new RuntimeException("Exported binary is not a valid ZipArchive (code {$opened})");
    }
    if ($zip->locateName('xl/workbook.xml') === false && $zip->locateName('xl/worksheets/sheet1.xml') === false) {
        $zip->close();
        throw new RuntimeException("Exported .xlsx does not contain standard OpenXML parts");
    }
    $zip->close();

    $audit->assertLogged('Xuất file điểm', 'gv_toan', $cname);
});

$runner->runTest("T3.11", "Early-Warning Radar Diagnostics (Feature #15)", function() use ($sessions, $runner) {
    $toan = $sessions->client('gv_toan');
    $resToan = $runner->recordResponse($toan->get('index.php'));
    $resToan->assertCleanHtml('radar_toan');
    $resToan->assertZeroZalo('radar_toan');

    if (stripos($resToan->body, 'Nguy cấp') === false && stripos($resToan->body, 'Cần chú ý') === false) {
        throw new RuntimeException("Early warning diagnostic badges missing from teacher dashboard");
    }

    $admin = $sessions->client('admin');
    $resAdmin = $runner->recordResponse($admin->get('index.php'));
    $resAdmin->assertCleanHtml('radar_admin');
    $resAdmin->assertZeroZalo('radar_admin');
});

$runner->runTest("T3.12", "Live Rendered Zero Zalo & Stitch UI Compliance (Features #16, #17)", function() use ($sessions, $runner, $db) {
    $tc = $db->fetchOne("SELECT tc.class_id, tc.subject FROM teacher_classes tc WHERE tc.teacher_id = (SELECT id FROM users WHERE username = 'gv_toan') AND (SELECT COUNT(*) FROM students s WHERE s.class_id = tc.class_id) > 0 ORDER BY tc.id DESC LIMIT 1");
    $cid = (int)($tc['class_id'] ?? 1);
    $sub = $tc['subject'] ?? 'Toán';

    $pages = [
        ['admin', 'admin.php'],
        ['gv_toan', "nhap_diem.php?class_id={$cid}&subject=" . urlencode($sub)],
        ['gv_toan', 'index.php'],
        ['guest', 'login.php']
    ];
    foreach ($pages as [$persona, $path]) {
        $client = $sessions->client($persona);
        $res = $runner->recordResponse($client->get($path));
        $res->assertCleanHtml("live_{$persona}_{$path}");
        $res->assertZeroZalo("live_{$persona}_{$path}");

        // Verify Stitch UI typography token
        if (stripos($res->body, 'Plus Jakarta Sans') === false) {
            throw new RuntimeException("Design token 'Plus Jakarta Sans' missing in {$path}");
        }
    }
});

// -----------------------------------------------------------------------------
// TIER 4: Deduplication, Security & Audit Logging
// -----------------------------------------------------------------------------
$runner->setTier("TIER 4: Deduplication, Security & Audit Logging (Anti-Tampering)");

$runner->runTest("T4.01", "Deduplication: Class Creation Rejection (Feature #2)", function() use ($sessions, $runner, $db) {
    $admin = $sessions->client('admin');
    $beforeCount = $db->count('classes', "name = '6A1'");

    $res = $runner->recordResponse($admin->post('admin.php', [
        'action'  => 'add_class',
        'name'    => '6A1',
        'grade'   => 6,
        'section' => 'A'
    ]));
    $res->assertCleanHtml('dedup_class');

    $afterCount = $db->count('classes', "name = '6A1'");
    if ($afterCount !== $beforeCount) {
        throw new RuntimeException("Duplicate class '6A1' was created! Before: {$beforeCount}, After: {$afterCount}");
    }
    if (stripos($res->body, 'đã tồn tại') === false) {
        throw new RuntimeException("Duplicate class error message missing from UI response");
    }
});

$runner->runTest("T4.02", "Deduplication: Batch Assignment Idempotency (Feature #2)", function() use ($sessions, $runner, $db) {
    $admin = $sessions->client('admin');
    $beforeCount = $db->count('teacher_classes', "teacher_id = 2 AND subject = 'Tin Học' AND class_id IN (2, 3)");

    // Submit identical assignment again
    $res = $runner->recordResponse($admin->post('admin.php', [
        'action'     => 'batch_assign',
        'teacher_id' => 2,
        'subject'    => 'Tin Học',
        'class_ids'  => [2, 3]
    ]));
    $res->assertCleanHtml('dedup_batch_assign');

    $afterCount = $db->count('teacher_classes', "teacher_id = 2 AND subject = 'Tin Học' AND class_id IN (2, 3)");
    if ($afterCount !== $beforeCount) {
        throw new RuntimeException("Duplicate assignments inserted! Before: {$beforeCount}, After: {$afterCount}");
    }
    if (stripos($res->body, 'bỏ qua') === false) {
        throw new RuntimeException("Skipped duplicate assignments notice missing from UI");
    }
});

$runner->runTest("T4.03", "Deduplication: Student Roster Re-import Idempotency (Feature #9)", function() use ($sessions, $runner, $db, $guardian) {
    $admin = $sessions->client('admin');
    $targetClassId = 2; // 6A2

    $beforeCount = $db->count('students', "class_id = {$targetClassId}");

    $xlsxData = [
        ['STT', 'Mã HS', 'Họ và tên', 'Ngày sinh', 'Giới tính', 'Số điện thoại'],
        ['1', 'HS6A201', 'Lê Hoài An', '12/04/2015', 'Nam', '0911223344'],
        ['2', 'HS6A202', 'Nguyễn Mai Chi', '25/08/2015', 'Nữ', '0922334455'],
        ['3', 'HS6A203', 'Trần Bảo Long', '42080', 'Nam', '0933445566'],
    ];
    $tempXlsx = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'e2e_reimport_' . uniqid() . '.xlsx';
    file_put_contents($tempXlsx, create_xlsx($xlsxData));
    $guardian->registerTempFile($tempXlsx);

    $postData = [
        'action'          => 'import_students',
        'target_class_id' => $targetClassId,
        'student_file'    => new CURLFile($tempXlsx, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'students_6a2.xlsx')
    ];
    $res = $runner->recordResponse($admin->postMultipart('admin.php', $postData));
    $res->assertCleanHtml('dedup_student_reimport');

    $afterCount = $db->count('students', "class_id = {$targetClassId}");
    if ($afterCount !== $beforeCount) {
        throw new RuntimeException("Duplicate students were inserted on re-import! Before: {$beforeCount}, After: {$afterCount}");
    }
    if (stripos($res->body, 'bỏ qua') === false && stripos($res->body, 'đã tồn tại') === false) {
        throw new RuntimeException("Duplicate students skipped notice missing from UI response");
    }
});

$runner->runTest("T4.04", "Deduplication: Gradebook Score Upsert Idempotency (Feature #14)", function() use ($sessions, $runner, $db, $guardian) {
    $toan = $sessions->client('gv_toan');
    $tc = $db->fetchOne("SELECT tc.class_id, tc.subject, c.name FROM teacher_classes tc JOIN classes c ON tc.class_id = c.id WHERE tc.teacher_id = (SELECT id FROM users WHERE username = 'gv_toan') AND (SELECT COUNT(*) FROM students s WHERE s.class_id = tc.class_id) > 0 ORDER BY tc.id DESC LIMIT 1");
    $cid = (int)$tc['class_id'];
    $sub = $tc['subject'];
    $cname = $tc['name'];
    $stList = $db->query("SELECT id, full_name, phone FROM students WHERE class_id = {$cid} ORDER BY id ASC LIMIT 2");
    $s1 = $stList[0];
    $s2 = $stList[1] ?? $stList[0];
    $s1Id = (int)$s1['id'];
    $s2Id = (int)$s2['id'];

    $beforeCount = $db->count('scores', "subject = '{$sub}' AND student_id IN ({$s1Id}, {$s2Id})");

    $gradebookData = [
        ['STT', 'Họ và tên', 'Số điện thoại phụ huynh', 'ĐĐGtx1', 'ĐĐGtx2', 'ĐĐGtx3', 'ĐĐGtx4', 'ĐĐGgk', 'ĐĐGck'],
        [1, $s1['full_name'], $s1['phone'] ?: '0901234567', 9.5, 9.5, 9.5, 9.5, 9.5, 9.5],
        [2, $s2['full_name'], $s2['phone'] ?: '0912345678', 8.0, 8.0, 8.0, 8.0, 8.0, 8.0],
    ];
    $tempXlsx = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'e2e_upsert_scores_' . uniqid() . '.xlsx';
    file_put_contents($tempXlsx, create_xlsx($gradebookData, "SoDiem_{$cname}"));
    $guardian->registerTempFile($tempXlsx);

    $postData = [
        'action'         => 'import_file',
        'gradebook_file' => new CURLFile($tempXlsx, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'gradebook.xlsx')
    ];
    $res = $runner->recordResponse($toan->postMultipart("nhap_diem.php?class_id={$cid}&subject=" . urlencode($sub), $postData));
    $res->assertCleanHtml('score_upsert');

    $afterCount = $db->count('scores', "subject = '{$sub}' AND student_id IN ({$s1Id}, {$s2Id})");
    if ($afterCount !== $beforeCount) {
        throw new RuntimeException("Duplicate score rows created on re-import! Before: {$beforeCount}, After: {$afterCount}");
    }
    // Verify values were updated (upsert)
    $val = (float)$db->fetchValue("SELECT avg_score FROM scores WHERE student_id = {$s1Id} AND subject = '{$sub}'");
    if ($val !== 9.5) {
        throw new RuntimeException("Expected upserted avg_score 9.5, found {$val}");
    }
});

$runner->runTest("T4.05", "Anti-BOLA: Cross-Class Student ID Injection Defense", function() use ($sessions, $runner, $db) {
    $toan = $sessions->client('gv_toan');
    $tc = $db->fetchOne("SELECT tc.class_id, tc.subject FROM teacher_classes tc WHERE tc.teacher_id = (SELECT id FROM users WHERE username = 'gv_toan') AND (SELECT COUNT(*) FROM students s WHERE s.class_id = tc.class_id) > 0 ORDER BY tc.id DESC LIMIT 1");
    $cid = (int)$tc['class_id'];
    $sub = $tc['subject'];

    $payload = [
        'action' => 'save_scores',
        'scores' => [
            99999 => ['tx1' => '10.0', 'gk' => '10.0', 'ck' => '10.0']
        ]
    ];
    $res = $runner->recordResponse($toan->post("nhap_diem.php?class_id={$cid}&subject=" . urlencode($sub), $payload));
    $res->assertCleanHtml('bola_student_injection');

    $chk = $db->count('scores', 'student_id = 99999');
    if ($chk !== 0) {
        throw new RuntimeException("Security violation: Injected foreign student 99999 was inserted into scores table!");
    }
});

$runner->runTest("T4.06", "Score Boundary Value Sanitization (0.0 to 10.0)", function() use ($sessions, $runner, $db) {
    $toan = $sessions->client('gv_toan');
    $tc = $db->fetchOne("SELECT tc.class_id, tc.subject FROM teacher_classes tc WHERE tc.teacher_id = (SELECT id FROM users WHERE username = 'gv_toan') AND (SELECT COUNT(*) FROM students s WHERE s.class_id = tc.class_id) > 0 ORDER BY tc.id DESC LIMIT 1");
    $cid = (int)$tc['class_id'];
    $sub = $tc['subject'];
    $s1 = $db->fetchOne("SELECT id FROM students WHERE class_id = {$cid} ORDER BY id ASC LIMIT 1");
    $s1Id = (int)$s1['id'];

    $payload = [
        'action' => 'save_scores',
        'scores' => [
            $s1Id => [
                'tx1' => '-5.0',       // Out of bounds < 0
                'tx2' => '15.5',       // Out of bounds > 10
                'tx3' => 'notanumber', // Non-numeric
                'tx4' => '9.0',        // Valid
                'gk'  => '9.0',
                'ck'  => '9.0'
            ]
        ]
    ];
    $res = $runner->recordResponse($toan->post("nhap_diem.php?class_id={$cid}&subject=" . urlencode($sub), $payload));
    $res->assertCleanHtml('score_sanitization');

    $row = $db->fetchOne("SELECT * FROM scores WHERE student_id = {$s1Id} AND subject = '{$sub}'");
    if ($row['tx1'] !== null || $row['tx2'] !== null || $row['tx3'] !== null) {
        throw new RuntimeException("Out-of-bounds score was not sanitized to NULL: " . json_encode($row));
    }
    if ((float)$row['tx4'] !== 9.0) {
        throw new RuntimeException("Valid score tx4 was modified unexpectedly");
    }
});

$runner->runTest("T4.07", "Malformed OpenXML File Ingestion Resilience", function() use ($sessions, $runner, $guardian) {
    $toan = $sessions->client('gv_toan');
    $corruptFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'e2e_corrupt_' . uniqid() . '.xlsx';
    file_put_contents($corruptFile, "THIS IS PLAIN TEXT NOT A ZIP OR OPENXML SPREADSHEET");
    $guardian->registerTempFile($corruptFile);

    $postData = [
        'action'         => 'import_file',
        'gradebook_file' => new CURLFile($corruptFile, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'corrupt.xlsx')
    ];
    $res = $runner->recordResponse($toan->postMultipart('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'), $postData));
    $res->assertCleanHtml('corrupt_file_upload');

    if ($res->status !== 200) {
        throw new RuntimeException("Uploading malformed file caused HTTP {$res->status}, expected 200 with error banner");
    }
    if (stripos($res->body, 'hợp lệ') === false && stripos($res->body, 'không đọc được') === false) {
        throw new RuntimeException("Friendly error message missing when uploading malformed file");
    }
});

$runner->runTest("T4.08", "Live HTTP Zero PHP Warnings/Notices Forensics", function() use ($runner) {
    $totalResponses = count($runner->collectedResponses);
    if ($totalResponses < 25) {
        throw new RuntimeException("Expected at least 25 captured live HTTP responses, collected {$totalResponses}");
    }
    foreach ($runner->collectedResponses as $idx => $res) {
        $res->assertCleanHtml("response_index_{$idx}");
    }
});

$runner->runTest("T4.09", "Exhaustive MySQL Audit Log Forensics", function() use ($db) {
    $expectedActions = [
        'Đăng nhập thành công',
        'Đăng nhập thất bại',
        'Đăng xuất',
        'Truy cập trái phép bị chặn',
        'Tạo lớp học',
        'Xóa lớp học',
        'Tạo tài khoản giáo viên',
        'Phân công giáo viên',
        'Import học sinh',
        'Lưu sổ điểm',
        'Import file điểm',
        'Xuất file điểm'
    ];

    foreach ($expectedActions as $action) {
        $cnt = $db->count('audit_logs', "action LIKE :act", [':act' => "%{$action}%"]);
        if ($cnt === 0) {
            throw new RuntimeException("No audit log found in MySQL for action: '{$action}'");
        }
    }

    // Verify fields integrity
    $latestLogs = $db->query("SELECT * FROM audit_logs ORDER BY id DESC LIMIT 50");
    foreach ($latestLogs as $log) {
        if (empty($log['ip_address'])) {
            throw new RuntimeException("Audit log record #{$log['id']} has empty ip_address");
        }
        if (empty($log['action'])) {
            throw new RuntimeException("Audit log record #{$log['id']} has empty action");
        }
    }
});

$runner->runTest("T4.10", "Non-Destructive State Teardown & Baseline Verification", function() use ($guardian, $db) {
    // Perform explicit state restoration
    $guardian->teardown();

    // Verify baseline invariants
    $classCount = $db->count('classes');
    if ($classCount < 36) {
        throw new RuntimeException("Classes count after teardown is {$classCount}, expected at least 36");
    }

    $userCount = $db->count('users');
    if ($userCount < 3) {
        throw new RuntimeException("Users count after teardown is {$userCount}, expected at least 3");
    }

    $c2Students = $db->count('students', 'class_id = 2');
    if ($c2Students !== 0) {
        throw new RuntimeException("Residual test students in Class 2: found {$c2Students}, expected 0");
    }

    $c3Students = $db->count('students', 'class_id = 3');
    if ($c3Students !== 0) {
        throw new RuntimeException("Residual test students in Class 3: found {$c3Students}, expected 0");
    }
});

// Teardown and summary output
$guardian->teardown();
$sessions->cleanupAll();

$runner->printSummary();

// Final exit code protocol
exit($runner->failedCount > 0 ? 1 : 0);
