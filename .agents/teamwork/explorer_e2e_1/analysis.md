# Architectural Blueprint: E2E Test Runner (`tests/run_tests.php`)
**Project**: EduRadar THCS Gradebook & Early Warning System (VnEdu & Thông tư 22/2021/TT-BGDĐT)  
**Author**: `explorer_e2e_1` (E2E Test Harness & CLI Architecture Explorer)  
**Target Runtime**: PHP 8.0.30 CLI (`D:\xam\php\php.exe`) on live XAMPP Apache (`http://localhost/my-web-project/`) & MariaDB 10.4 (`edu_radar`)  
**Scope**: High-craft CLI console UX, multi-account cURL HTTP client with persistent cookie jars & multipart `.xlsx` uploads, live PDO MySQL inspector with audit forensics, and 100% idempotent non-destructive state management.

---

## 1. Executive Summary & Design Principles

The end-to-end acceptance test suite (`tests/run_tests.php`) represents the ultimate quality gate for EduRadar THCS. It verifies that all 18 features defined in `PROJECT.md` and all 8 acceptance criteria from `ORIGINAL_REQUEST.md` operate harmoniously against the live production stack without mock stubs or facades.

### Core Architectural Principles
1. **Zero External Dependencies**: Standard PHP 8.0 built-in extensions only (`curl`, `pdo_mysql`, `zip`, `simplexml`, `json`, `mbstring`). No Composer, no PHPUnit, no Node.js test runners required.
2. **Empirical Ground Truth**: Every assertion must execute either a real HTTP network request against live Apache (`http://localhost/my-web-project/`) or a real SQL query against live MariaDB (`edu_radar`). Zero in-memory facades.
3. **Multi-Session Isolation**: Built-in support for independent, persistent cookie jars representing distinct user personas (`admin`, `gv_toan`, `gv_van`, and unauthenticated `guest`), capable of sequential and interleaved execution.
4. **Binary OpenXML Realism**: Real `.xlsx` files generated in-memory via `create_xlsx()` (ECMA-376 OpenXML standard) uploaded as genuine multipart form-data via `CURLFile`.
5. **High-Craft CLI UX**: Windows VT100-enabled colored ANSI badges, microsecond execution timers, category separators, detailed failure diagnostics, and a comprehensive summary banner terminating with exit code 0 on pass or exit code 1 on fail.
6. **Non-Destructive Idempotency**: The test suite can be executed repeatedly (1, 10, or 100 times) without manual database resets. Test fixtures are isolated via distinct namespaces, modified baseline rows are restored via state snapshots, and all temporary files and records are reaped during automated teardown.

---

## 2. High-Craft CLI Console UX Architecture

### 2.1 Terminal Compatibility & VT100 Support
On Windows, native ANSI escape sequence processing must be explicitly activated at the script entry point:
```php
if (function_exists('sapi_windows_vt100_support')) {
    sapi_windows_vt100_support(STDOUT, true);
    sapi_windows_vt100_support(STDERR, true);
}
```
If `--no-color` is detected in `$argv`, color formatting is dynamically suppressed.

### 2.2 ANSI Design Tokens
```php
final class Ansi
{
    public const RESET       = "\033[0m";
    public const BOLD        = "\033[1m";
    public const DIM         = "\033[2m";
    public const UNDERLINE   = "\033[4m";
    
    // Foreground colors
    public const FG_RED      = "\033[31m";
    public const FG_GREEN    = "\033[32m";
    public const FG_YELLOW   = "\033[33m";
    public const FG_BLUE     = "\033[34m";
    public const FG_MAGENTA  = "\033[35m";
    public const FG_CYAN     = "\033[36m";
    public const FG_WHITE    = "\033[37m";
    public const FG_GRAY     = "\033[90m";
    
    // High-intensity foreground
    public const FG_HI_RED   = "\033[91m";
    public const FG_HI_GREEN = "\033[92m";
    public const FG_HI_YELLOW= "\033[93m";
    public const FG_HI_CYAN  = "\033[96m";
    
    // Status Badges (Text on standard background or inverted)
    public const BADGE_PASS  = "\033[1;32m [ PASS ]\033[0m";
    public const BADGE_FAIL  = "\033[1;97;41m FAIL \033[0m";
    public const BADGE_SKIP  = "\033[1;33m [ SKIP ]\033[0m";
    public const BADGE_INFO  = "\033[1;36m [ INFO ]\033[0m";
}
```

### 2.3 Visual Layout Specifications

#### 1. Header Banner
```
========================================================================================
  EduRadar THCS — Automated Multi-Account & Deduplication E2E Test Suite
  Specification: Thông tư 22/2021/TT-BGDĐT & Chuẩn Sổ Điểm Điện Tử VnEdu
========================================================================================
  PHP Version : 8.0.30 (cli) on Windows NT
  Target Host : http://localhost/my-web-project/ (Apache 2.4)
  Database    : MariaDB 10.4.32 / edu_radar on 127.0.0.1:3306
  Process ID  : 18452 | Timestamp: 2026-10-17 18:45:00 UTC
========================================================================================
```

#### 2. Category / Tier Separator
```
--- TIER 1: Multi-Account Authentication & Session Rotation ---
--- TIER 2: Strict Server-Side RBAC & Anti-BOLA Route Guards ---
--- TIER 3: Secondary Class Matrix & VnEdu Deduplicated Imports ---
--- TIER 4: VnEdu Spreadsheet Gradebook, OpenXML I/O & Audit Forensics ---
```

#### 3. Test Progress Line Formatting
Each test prints an aligned single-line entry:
`[Test ID] Test Name Padded .................................... (Timer) [ BADGE ]`
- Column 1: Test Identifier (e.g. `[T1.1]`, `[T2.3]`) in cyan/bold.
- Column 2: Test description padded with dots `.` to exact width (74 characters).
- Column 3: Execution duration measured with `hrtime(true)` or `microtime(true)`:
  - `< 1000ms`: formatted as `( 18ms)` in muted gray `Ansi::FG_GRAY`.
  - `>= 1.0s`: formatted as `( 1.24s)` in yellow `Ansi::FG_YELLOW`.
- Column 4: Status Badge: ` [ PASS ]` in green or ` FAIL ` in high-visibility red badge.

#### 4. Indented Failure Diagnostics Box
When a test fails, a structured diagnostic box is immediately rendered below the line:
```
  ┌── FAILURE DIAGNOSTIC: [T2.3] ──────────────────────────────────────────────
  │ Description: Teacher (gv_toan) accessing unassigned class must receive HTTP 403
  │ Expected   : HTTP 403 Forbidden
  │ Received   : HTTP 200 OK
  │ Target URL : http://localhost/my-web-project/nhap_diem.php?class_id=2&subject=Toán
  │ Active User: gv_toan (role: teacher, id: 2)
  │ Exception  : Assertion failed: Expected status 403, got 200
  │ Location   : tests/run_tests.php:412
  └────────────────────────────────────────────────────────────────────────────
```

#### 5. Summary Footer & Exit Codes
```
========================================================================================
  E2E TEST EXECUTION SUMMARY
========================================================================================
  Suites Executed : 4 Tiers (18 Functional Features Verified)
  Total Tests     : 64
  Passed          : 64 (100.0%)
  Failed          : 0 (0.0%)
  Skipped         : 0 (0.0%)
  Total Duration  : 4.62s
  Peak Memory     : 4.85 MB
========================================================================================
  [SUCCESS] ALL 18 ACCEPTANCE CRITERIA VERIFIED CLEANLY (0 FAILURES)
========================================================================================
```
- **Exit Code Protocol**:
  - `exit(0);` if `$failedCount === 0`.
  - `exit(1);` if `$failedCount > 0`.

---

## 3. Built-In cURL HTTP Client Architecture

### 3.1 Multi-Session Architecture & Cookie Jar Management
The runner requires isolated, concurrent session states for testing role boundaries. Rather than re-logging in for every single assertion, persistent cookie jars are allocated per persona:

| Persona | Username | Role | Assigned Classes / Subjects | Cookie File Path |
|---|---|---|---|---|
| `admin` | `admin` | `admin` | Full school access (all 36 classes) | `%TEMP%/eduradar_cookie_admin_<token>.txt` |
| `gv_toan` | `gv_toan` | `teacher` | Class 1 (6A1) Toán, Class 10 (7A1) Toán | `%TEMP%/eduradar_cookie_gv_toan_<token>.txt` |
| `gv_van` | `gv_van` | `teacher` | Class 1 (6A1) Ngữ Văn | `%TEMP%/eduradar_cookie_gv_van_<token>.txt` |
| `guest` | *(none)* | `unauthenticated` | None | *(no cookies sent)* |
| `ephemeral`| *(dynamic)*| `teacher` | Created during teacher creation tests | `%TEMP%/eduradar_cookie_ephem_<token>.txt` |

### 3.2 `HttpClient` & `SessionManager` Specification

```php
/**
 * Result object returned by HttpClient requests.
 */
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
}
```

```php
/**
 * Isolated cURL HTTP Client representing a single session persona.
 */
class HttpClient
{
    private string $baseUrl;
    private ?string $cookieFile;
    private array $defaultHeaders = [];

    public function __construct(string $baseUrl, ?string $cookieFile = null)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->cookieFile = $cookieFile;
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

        $mergedHeaders = array_merge($this->defaultHeaders, $headers);
        $headerList = [];
        foreach ($mergedHeaders as $k => $v) {
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
            // Detect if payload contains CURLFile (multipart upload)
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

    public function post(string $path, array $data = [], array $headers = [], bool $followRedirects = false): HttpResponse
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
```

```php
/**
 * SessionManager orchestrates multiple authenticated and guest client personas.
 */
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
```

### 3.3 Built-In Forensic Quality Gates
To ensure strict anti-facade compliance (audited in Milestone 5), every HTTP response can be passed through built-in quality assertions:
1. `assertCleanHtml(HttpResponse $res, string $context)`:
   Scans `$res->body` against PHP runtime error signatures:
   - `<b>Warning</b>`, `Warning:`, `<b>Notice</b>`, `Notice:`, `<b>Deprecated</b>`, `Deprecated:`
   - `Fatal error:`, `<b>Fatal error</b>`, `Parse error:`, `Undefined variable`, `Undefined index`, `Undefined array key`
   - `Stack trace:`
   Throws immediately if any diagnostic leaks into HTML.
2. `assertZeroZalo(HttpResponse $res)`:
   Scans `$res->body` for case-insensitive occurrences of:
   - `zalo`, `actionModal`, `copy-btn`, `copyMessage`
   Guarantees R4 and R6 compliance across all views.

---

## 4. Live PDO MySQL Database State & Audit Forensics

### 4.1 Connection & Direct State Inspector
The test runner connects directly to `edu_radar` using `includes/db.php`'s singleton `db()` or a dedicated PDO instance.

```php
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

    public function assertCount(string $table, int $expectedCount, string $where = '1=1', array $params = []): void
    {
        $actual = $this->count($table, $where, $params);
        if ($actual !== $expectedCount) {
            throw new RuntimeException("Table '{$table}' count mismatch for condition [{$where}]. Expected: {$expectedCount}, Actual: {$actual}");
        }
    }
}
```

### 4.2 Checkpoint-Based Audit Log Verification (`AuditTracker`)
Acceptance Criterion 72 mandates: *"Verifies every tested action above wrote a corresponding row to `audit_logs` in MySQL."*

A common pitfall is querying `SELECT * FROM audit_logs WHERE action = ...` without bounding by time or ID, which risks matching old records from earlier runs. 
The **Checkpoint / High-Water Mark Pattern** solves this deterministically:

```php
class AuditTracker
{
    private PDO $pdo;
    private int $checkpointId = 0;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->checkpoint();
    }

    /**
     * Set a new high-water mark. Subsequent assertions only check logs created after this moment.
     */
    public function checkpoint(): int
    {
        $this->checkpointId = (int)$this->pdo->query("SELECT COALESCE(MAX(id), 0) FROM audit_logs")->fetchColumn();
        return $this->checkpointId;
    }

    /**
     * Assert that a matching audit log record was inserted since the last checkpoint.
     */
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
            throw new RuntimeException("Audit assertion failed: No audit log matching action [{$actionPattern}] found since ID {$this->checkpointId}.");
        }

        $latest = $rows[0];

        if ($expectedUsername !== null && $latest['username'] !== $expectedUsername) {
            throw new RuntimeException("Audit username mismatch: Expected '{$expectedUsername}', recorded '{$latest['username']}' (Action: {$latest['action']})");
        }

        if ($detailsPattern !== null && stripos($latest['details'], $detailsPattern) === false) {
            throw new RuntimeException("Audit details mismatch: Expected text '{$detailsPattern}' inside '{$latest['details']}'");
        }

        // Return matched record for further deep assertions
        return $latest;
    }
}
```

---

## 5. Non-Destructive Idempotency & State Guardian

### 5.1 Analysis of Production Baseline Data
The baseline seed in `database.sql` defines:
- **3 Users**: `admin` (ID 1), `gv_toan` (ID 2), `gv_van` (ID 3).
- **36 Classes**: Grades 6–9, Sections A–C, Classes 1–3 (IDs 1–36, `6A1`..`9C3`).
- **3 Assignments**: `(2, 1, 'Toán')`, `(2, 10, 'Toán')`, `(3, 1, 'Ngữ Văn')`.
- **5 Students**: All located in Class 1 (`6A1`), codes `HS6A101`..`HS6A105`.
- **7 Scores**: 5 in `6A1 Toán`, 2 in `6A1 Ngữ Văn`.
- **1 Audit Log**: Initial bootstrap row.

### 5.2 The 4 Isolation Strategies

| Test Requirement | Destructive Risk | Idempotent Isolation Strategy |
|---|---|---|
| **Class Creation & Deletion** | Contaminating 36-class matrix, collision on re-run | Use explicit namespaced class: `name = '6C99_E2E'`. Pre-clean prior to test, create via POST, verify `UNIQUE(name)` rejection, and delete via `admin.php?delete_class=...`. |
| **Teacher Account Creation** | User table pollution, duplicate username failure | Use explicit namespaced user: `username = 'gv_test_e2e'`. Pre-clean prior to test, create via POST, verify `UNIQUE(username)` rejection, and delete in teardown. |
| **Batch Class Assignment** | Corrupting existing teacher schedules | Assign ephemeral teacher to ephemeral/empty classes, or assign `gv_van` to unassigned `7A1 Ngữ Văn`. Verify duplicate rejection. Unassign via `admin.php?delete_assign=...`. |
| **Student Roster Import** | Altering 6A1 roster or polluting student counts | Target Class 2 (`6A2`) or Class 3 (`6A3`). These classes exist in the 36-class matrix but contain **zero baseline students**. Import `.csv` / `.xlsx`, verify duplicate re-import yields 0 new rows, then clean up: `DELETE FROM students WHERE class_id = 2`. |
| **Grade Saving & Import** | Mutating 6A1 baseline student grades | **Memory Snapshot & Rollback**: Prior to running score saving / import tests on `6A1 Toán`, snapshot all rows in `scores WHERE student_id IN (1,2,3,4,5) AND subject = 'Toán'`. Run the test, verify `scores` table updates and `avg_score` calculation, then in the `finally` block, unconditionally restore original snapshot values. |

### 5.3 `StateGuardian` Implementation Specification

```php
class StateGuardian
{
    private PDO $pdo;
    private array $scoresSnapshot = [];
    private array $ephemeralClasses = ['6C99_E2E', '9C99_TEST'];
    private array $ephemeralUsers   = ['gv_test_e2e', 'gv_test_m3', 'gv_temp'];
    private array $ephemeralStudentClasses = [2, 3]; // 6A2, 6A3

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Phase 0: Take snapshot and pre-clean any leftover debris from aborted runs.
     */
    public function setup(): void
    {
        // 1. Snapshot baseline scores for 6A1 (student_ids 1..5)
        $stmt = $this->pdo->query("SELECT * FROM scores WHERE student_id BETWEEN 1 AND 5");
        $this->scoresSnapshot = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Pre-clean ephemeral entities
        $this->purgeDebris();
    }

    /**
     * Phase 2: Guaranteed restoration executed via shutdown handler or finally block.
     */
    public function teardown(): void
    {
        // 1. Restore baseline scores
        if (!empty($this->scoresSnapshot)) {
            $updateStmt = $this->pdo->prepare("
                UPDATE scores 
                SET tx1 = :tx1, tx2 = :tx2, tx3 = :tx3, tx4 = :tx4, 
                    gk = :gk, ck = :ck, avg_score = :avg_score 
                WHERE student_id = :student_id AND subject = :subject
            ");
            foreach ($this->scoresSnapshot as $row) {
                $updateStmt->execute([
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

        // 2. Purge ephemeral test records
        $this->purgeDebris();
    }

    private function purgeDebris(): void
    {
        // Delete test students created in 6A2 or 6A3
        if (!empty($this->ephemeralStudentClasses)) {
            $inClasses = implode(',', $this->ephemeralStudentClasses);
            $this->pdo->exec("DELETE FROM students WHERE class_id IN ({$inClasses})");
        }

        // Delete test teachers and cascade assignments
        foreach ($this->ephemeralUsers as $username) {
            $this->pdo->prepare("DELETE FROM users WHERE username = ?")->execute([$username]);
        }

        // Delete test classes and cascade assignments
        foreach ($this->ephemeralClasses as $className) {
            $this->pdo->prepare("DELETE FROM classes WHERE name = ?")->execute([$className]);
        }
    }
}
```

---

## 6. Comprehensive Class Architecture for `tests/run_tests.php`

The entire test runner architecture fits cleanly inside `tests/run_tests.php` (or decomposed into a small `tests/harness/` module if preferred; keeping it in `tests/run_tests.php` ensures zero file-resolution friction when invoked via CLI).

### Class Diagram & Structural Collaboration
```
┌────────────────────────────────────────────────────────────────────────┐
│                          tests/run_tests.php                           │
├────────────────────────────────────────────────────────────────────────┤
│                                                                        │
│  ┌──────────────┐         ┌──────────────────┐    ┌─────────────────┐ │
│  │  CliConsole  │◄────────┤    TestRunner    ├───►│  StateGuardian  │ │
│  └──────────────┘         └─────────┬────────┘    └────────┬────────┘ │
│                                     │                      │          │
│                                     ▼                      ▼          │
│                           ┌──────────────────┐    ┌─────────────────┐ │
│                           │  SessionManager  │    │DatabaseInspector│ │
│                           └─────────┬────────┘    └────────┬────────┘ │
│                                     │                      │          │
│                                     ▼                      ▼          │
│                           ┌──────────────────┐    ┌─────────────────┐ │
│                           │    HttpClient    │    │  AuditTracker   │ │
│                           └──────────────────┘    └─────────────────┘ │
└────────────────────────────────────────────────────────────────────────┘
```

### Component Responsibility Breakdown

| Component | Class Name | Primary Responsibility |
|---|---|---|
| **Console Presenter** | `CliConsole` | ANSI formatting, VT100 initialization, test padding, execution timers, boxed failure diagnostics, category headers, summary cards, and exit code resolution. |
| **Test Orchestrator** | `TestRunner` | Test registration, test suite execution, try/catch isolation, exception capture, duration tracking, aggregate counter tallies (`total`, `pass`, `fail`, `skip`). |
| **Session Registry** | `SessionManager` | Factory for `HttpClient` instances. Manages distinct cookie jar paths per persona (`admin`, `gv_toan`, `gv_van`, `guest`), handles authentication automation, and reaps temp cookie files. |
| **HTTP Transport** | `HttpClient` | cURL wrapper supporting URL-encoded and multipart `CURLFile` payloads, redirect traversal, header extraction, and built-in forensic quality assertions (`assertCleanHtml`, `assertZeroZalo`). |
| **Database Inspector**| `DatabaseInspector`| PDO MySQL query execution, row fetching, count verification, and schema constraint enforcement checks. |
| **Audit Forensics** | `AuditTracker` | High-water mark checkpoint tracking on `audit_logs` table, verifying that specific actions, usernames, and details are written to MySQL. |
| **State Guardian** | `StateGuardian` | Pre-flight cleanup, baseline score snapshotting, in-memory rollback, ephemeral fixture purging, and guaranteed non-destructive idempotency. |

---

## 7. Concrete Test Tier Mapping & Verification Scenarios

The runner organizes test cases into 4 distinct Tiers reflecting the full product lifecycle:

### Tier 1: Multi-Account Authentication & Session Lifecycle
- `[T1.1]` Admin login (`admin` / `123456`) -> HTTP 302 to `admin.php`, session cookie issued, audit log `Đăng nhập`.
- `[T1.2]` Teacher 1 login (`gv_toan` / `123456`) -> HTTP 302 to `index.php`, session cookie issued, audit log `Đăng nhập`.
- `[T1.3]` Teacher 2 login (`gv_van` / `123456`) -> HTTP 302 to `index.php`, session cookie issued, audit log `Đăng nhập`.
- `[T1.4]` Invalid credentials (`admin` / `wrongpass`) -> HTTP 200, error banner, no session cookie, audit log `Đăng nhập thất bại`.
- `[T1.5]` Non-existent user (`unknown` / `123456`) -> HTTP 200, error banner, audit log `Đăng nhập thất bại`.
- `[T1.6]` Logout endpoint (`logout.php`) -> HTTP 302 to `login.php`, session destroyed, audit log `Đăng xuất`.

### Tier 2: Strict Server-Side RBAC & Anti-BOLA Route Guards
- `[T2.1]` Unauthenticated GET `admin.php` -> HTTP 403 / redirect to `login.php`, audit log `Truy cập trái phép`.
- `[T2.2]` Teacher (`gv_toan`) GET `admin.php` -> HTTP 403 Forbidden, audit log `Truy cập trái phép`.
- `[T2.3]` Teacher (`gv_toan`) POST `admin.php` (action: `add_class`) -> HTTP 403 Forbidden, 0 database changes.
- `[T2.4]` Unauthenticated GET `nhap_diem.php` -> HTTP 302 redirect to `login.php`.
- `[T2.5]` Teacher (`gv_toan`) GET assigned class `6A1 Toán` -> HTTP 200 OK.
- `[T2.6]` Teacher (`gv_toan`) GET unassigned subject `6A1 Ngữ Văn` (BOLA) -> HTTP 403 Forbidden, audit log recorded.
- `[T2.7]` Teacher (`gv_toan`) GET unassigned class `6A2 Toán` (BOLA) -> HTTP 403 Forbidden, audit log recorded.
- `[T2.8]` Teacher (`gv_toan`) IDOR parameter tampering `?teacher_id=3` -> HTTP 403 Forbidden, audit log recorded.
- `[T2.9]` Admin account direct inspection `nhap_diem.php?class_id=1&subject=Toán` -> HTTP 200 OK.

### Tier 3: Secondary Class Matrix & VnEdu Deduplicated Imports
- `[T3.1]` Matrix structure verification: 36 classes grouped across Grades 6, 7, 8, 9 and Sections A, B, C.
- `[T3.2]` Admin creates class `6C99_E2E` via POST -> HTTP 302, class created in `classes`, audit log recorded.
- `[T3.3]` Admin attempts duplicate class `6C99_E2E` creation -> rejected by `UNIQUE(name)`, count unchanged.
- `[T3.4]` Admin creates teacher `gv_test_e2e` via POST -> HTTP 302, user created in `users`, audit log recorded.
- `[T3.5]` Admin attempts duplicate teacher `gv_test_e2e` -> rejected by `UNIQUE(username)`, count unchanged.
- `[T3.6]` Admin batch assignment across classes -> rows inserted into `teacher_classes`.
- `[T3.7]` Re-submitting identical batch assignment -> 0 duplicates created (`UNIQUE(teacher_id, class_id, subject)`).
- `[T3.8]` Admin imports student roster `.csv` into Class 2 (`6A2`) -> 5 students created.
- `[T3.9]` Re-importing identical `.csv` into Class 2 -> 0 duplicate students created (`UNIQUE(class_id, full_name, birthday)`).
- `[T3.10]` Admin imports real binary OpenXML `.xlsx` into Class 3 (`6A3`) with Vietnamese date formatting -> students created.
- `[T3.11]` Re-importing identical `.xlsx` -> 0 duplicate students created.
- `[T3.12]` Admin deletes class `6C99_E2E` and unassigns test assignments -> database updated, audit log recorded.

### Tier 4: VnEdu Spreadsheet Gradebook, OpenXML I/O & Audit Forensics
- `[T4.1]` Gradebook UI inspection: 11 columns, 2-tier sticky VnEdu headers, `score-input` elements with 2D navigation attributes.
- `[T4.2]` Circular 22 calculation engine verification: weighted formula `(S_tx + 2*gk + 3*ck)/(N_tx + 5)`.
- `[T4.3]` Teacher (`gv_toan`) saves grades via POST `action=save_scores` -> `scores` updated, accurate `avg_score`, audit log recorded.
- `[T4.4]` Out-of-bounds score validation: `< 0` or `> 10` rejected or sanitized to null.
- `[T4.5]` Cross-class student ID injection in POST payload -> rejected, foreign student scores unmodified.
- `[T4.6]` Real OpenXML `.xlsx` gradebook import via POST `action=import_file` with `CURLFile` -> grades upserted.
- `[T4.7]` Re-importing identical `.xlsx` gradebook file -> 0 duplicate records, scores updated cleanly.
- `[T4.8]` Export gradebook as `.xlsx` -> HTTP 200, valid `application/vnd.openxmlformats...` MIME, valid ZIP archive containing `xl/workbook.xml`.
- `[T4.9]` Early-Warning Radar (`index.php`): accurate counts for 🔴 Nguy cấp, 🟡 Cần chú ý, 🟢 Ổn định.
- `[T4.10]` Zero Zalo verification: 0 occurrences of `zalo`, 0 buttons, 0 modals across all views.
- `[T4.11]` Forensic Audit Logs integrity: every state-changing action verified with non-empty IP, username, and description.

---

## 8. Verification & Execution Guide

### 8.1 Invocation Command
The test suite is executed directly using XAMPP's PHP binary:
```powershell
& "D:\xam\php\php.exe" tests/run_tests.php
```

### 8.2 Expected Invariants
1. **Exit Code**: Must return `0` on 100% test success.
2. **Execution Timing**: Whole suite completes in `< 6.0` seconds against live local Apache and MariaDB.
3. **Database Cleanliness**: At completion, running `tests/verify_m1.php` through `tests/verify_m5.php` must immediately pass 100% without needing a database reset, proving complete idempotency.
4. **Temporary File Cleanliness**: Zero `.txt` cookie jars or `.xlsx` files left in `sys_get_temp_dir()`.

---

## 9. Conclusion
This architecture provides a complete, robust, self-contained blueprint for `tests/run_tests.php`. It solves every UX, networking, database inspection, and state isolation challenge, empowering the implementer to build the definitive test runner that fulfills all 18 features and 8 acceptance criteria.
