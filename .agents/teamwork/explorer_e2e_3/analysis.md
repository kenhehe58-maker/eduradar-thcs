# E2E Adversarial Security, Anti-Facade, and Clean Fixture Architecture Report

- **Author**: `explorer_e2e_3` (E2E Adversarial Testing & Anti-Facade Explorer)
- **Target Work Product**: `tests/run_tests.php` (acceptance criteria CLI test suite)
- **Target Running Environment**: XAMPP Apache 2.4 (`http://localhost/my-web-project/`) & MySQL/MariaDB 10.4 (`edu_radar`, port 3306) via PHP 8.0 CLI (`D:\xam\php\php.exe`)
- **Date**: 2026-10-17T11:47:00Z
- **Status**: COMPLETE ANALYSIS

---

## 1. Executive Summary

Milestone 6 and the Acceptance Criteria in `ORIGINAL_REQUEST.md` require the implementation of `tests/run_tests.php`, a unified, repeatable PHP CLI test suite executed via `D:\xam\php\php.exe tests/run_tests.php`. To prevent test suite degradation, facades, or residual database pollution, this investigation establishes three architectural pillars:

1. **Anti-Facade Integrity (100% Genuine Execution)**:
   - Prohibits all mock objects, mock sessions, dummy stubs, and in-process function invocations that bypass Apache.
   - Enforces genuine HTTP cURL communication over port 80 with separate cookie jars for each tested role (`admin`, `gv_toan`, `gv_van`, and unauthenticated guest).
   - Replaces static "magic number" arrays with live database invariants and dynamic before/after delta checks.
   - Mandates a **Live HTML Error Gate** that automatically fails any test if PHP Warnings, Notices, Deprecated messages, Fatal Errors, or Uncaught Exceptions appear in the HTTP response body.

2. **Adversarial Security Test Matrix**:
   - **Forged POST Payloads**: Unauthenticated and teacher sessions attempting administrative actions (`create_class`, `create_teacher`, `batch_assign`, `delete_class`, `import_students`) must strictly yield HTTP 403 (or 302 redirect), 0 database mutations, and audit logging.
   - **BOLA / IDOR Attacks**: Teacher `gv_toan` attempting to access or save grades for unassigned classes (`6A2`, `7B1`) or unassigned subjects (`Ngữ Văn`), or tampering with `?teacher_id=3`, must receive HTTP 403 Forbidden with 0 database modifications.
   - **Cross-Class Student ID Injections**: Teacher `gv_toan` submitting a gradebook payload containing a foreign student ID from another class must be strictly filtered by the class whitelist map (`validStudentMap`), ensuring 0 modification to the foreign student.
   - **Malformed & Corrupted File Ingestion**: Non-zip plain text disguised as `.xlsx`, truncated zip archives, random binary garbage, 0-byte files, and formula injection strings (`=cmd|...`) must be handled gracefully with HTTP 200 user-friendly errors, zero PHP crashes, and sanitized database values.
   - **SQL Injection (SQLi) Defense**: SQLi payloads in login username, URL parameters (`class_id`, `subject`), and form fields must be completely neutralized by PDO prepared statements with `PDO::ATTR_EMULATE_PREPARES => false`.

3. **Deterministic Clean Fixture Lifecycle**:
   - Strictly preserves production baseline seed data (36 classes `6A1`..`9C9`, seed accounts `admin`, `gv_toan`, `gv_van`, and baseline students/scores).
   - Utilizes isolated, namespaced test fixtures (`9Z_E2E_TMP`, `e2e_teacher_tmp`, `E2E_STU_TMP`).
   - Implements a 4-phase lifecycle (Pre-flight audit -> In-test tracking -> Cascading teardown -> Invariance assertion) coupled with a PHP `register_shutdown_function()` hook to guarantee zero orphan test debris even upon unexpected test aborts.

---

## 2. Anti-Facade Integrity Guidelines

### 2.1 The Definition and Anatomy of Facades
Past development iterations (documented in `auditor_m5_it2_1/audit.md`) revealed specific anti-patterns where tests appeared to pass while masking underlying defects:
- **Mock / Duplicate Stubs**: Creating private test functions (such as `testEvaluateRisk()`) that replicate business logic inside the test file instead of executing production code (`includes/radar_helper.php` or `index.php`).
- **In-Process Short-Circuiting**: Invoking PHP scripts or functions via `include` or direct function call in CLI memory, thereby bypassing Apache HTTP routing, `.htaccess`, session cookies, `header()` redirection, and middleware guards (`require_login()`, `require_admin()`).
- **Hardcoded Magic Value Assertions**: Asserting static expected counts (e.g., `assert($redCount === 2)`) that match a frozen snapshot rather than verifying mathematical and referential invariants against live database state.
- **Superficial HTTP Status Checks**: Asserting only `HTTP 200` without inspecting the HTML body, allowing PHP fatal warnings, notices, or unhandled exceptions rendered inside HTML comments or tags to go undetected.
- **Unverified Audit Logging**: Assuming audit logs exist without inspecting table count deltas and the exact `user_id`, `username`, `action`, and `details` inserted.

### 2.2 Prohibited vs Mandated Implementation Matrix

| Anti-Pattern (PROHIBITED) | Authentic Practice (MANDATED for `run_tests.php`) | Rationale |
|---|---|---|
| In-process controller invocation (`include 'admin.php'`) | Live cURL HTTP request to `http://localhost/my-web-project/admin.php` | Tests real Apache 2.4 server behavior, HTTP status codes, headers, and session cookies. |
| Re-declaring production logic inside test files | Testing production endpoints or importing canonical helper libraries | Guarantees that what is verified is what real users experience in production. |
| Hardcoded expected arrays (`['red' => 2, ...]`) | Dynamic mathematical invariants (`total === red + yellow + green` & formula checks) | Adapts dynamically to live database state without false positives or brittle fixtures. |
| Single shared cookie jar or memory array | Isolated physical cookie jar files per user in `sys_get_temp_dir()` | Prevents cross-contamination between `admin`, `gv_toan`, `gv_van`, and unauthenticated sessions. |
| Checking only `curl_getinfo(..., CURLINFO_HTTP_CODE)` | Comprehensive response analysis: HTTP status + Header Inspection + HTML Error Scanning | Detects hidden PHP notices, warnings, and malformed HTML responses. |
| Direct DB manipulation to simulate test results | Triggering state changes exclusively via HTTP POST/GET, followed by DB verification | Proves that the web UI controllers and database layers integrate seamlessly. |

### 2.3 Live HTML Error Gate (Zero PHP Warning / Notice Assertion)
Every HTTP response obtained from Apache MUST pass through an automated forensic error scanner before any business assertions are evaluated.

```php
/**
 * Assert that an HTTP response body contains zero PHP errors, notices, or warnings.
 *
 * @param string $body Raw HTTP response body
 * @param string $endpoint Contextual endpoint name for reporting
 * @throws RuntimeException If any PHP error signature is detected
 */
function assertCleanHtml(string $body, string $endpoint): void
{
    $errorSignatures = [
        'Fatal error'                                  => '/\bFatal error\b/i',
        'Parse error'                                  => '/\bParse error\b/i',
        'Warning'                                      => '/\bWarning:\b/i',
        'Notice'                                       => '/\bNotice:\b/i',
        'Deprecated'                                   => '/\bDeprecated:\b/i',
        'Uncaught Exception'                           => '/\bUncaught [A-Za-z0-9_]*Exception\b/i',
        'SQLSTATE / PDO Error'                         => '/\bSQLSTATE\[[A-Z0-9]+\]/i',
        'Null Array Offset'                            => '/Trying to access array offset on value of type null/i',
        'Undefined Variable'                           => '/Undefined variable/i',
        'Undefined Array Key / Index'                  => '/Undefined (array key|index)/i',
        'Undefined Property'                           => '/Undefined property/i',
        'Call to Undefined Function'                   => '/Call to undefined (function|method)/i',
    ];

    foreach ($errorSignatures as $label => $pattern) {
        if (preg_match($pattern, $body, $matches, PREG_OFFSET_CAPTURE)) {
            $offset = $matches[0][1];
            $snippet = substr($body, max(0, $offset - 100), 250);
            throw new RuntimeException("Forensic Error Gate Triggered [$label] on endpoint [$endpoint]: ... " . strip_tags($snippet) . " ...");
        }
    }
}
```

---

## 3. Adversarial Security Test Formulations

The test runner `tests/run_tests.php` must incorporate rigorous adversarial attack vectors across 6 distinct security categories.

### Category A: Forged POST Payloads & Privilege Escalation Attacks

#### Scenario A.1: Unauthenticated Forged POST to `admin.php`
- **Objective**: Verify that an unauthenticated attacker cannot create classes, add teachers, or assign classes via raw POST.
- **Request**:
  - `POST http://localhost/my-web-project/admin.php`
  - Cookie: Empty (unauthenticated guest)
  - Payload: `action=create_class&name=9Z_HACK&grade=9&section=A`
- **Assertions**:
  1. HTTP Status: `403 Forbidden` (or `302 Found` redirecting to `login.php`).
  2. Database State: `SELECT COUNT(*) FROM classes WHERE name = '9Z_HACK'` equals `0`.
  3. Audit Log: A record exists in `audit_logs` where `action LIKE '%Truy cập trái phép%'` or `details LIKE '%admin.php%'`.

#### Scenario A.2: Teacher Forged POST to `admin.php` (Privilege Escalation)
- **Objective**: Verify that an authenticated teacher (`gv_toan`) cannot execute admin state mutations.
- **Request**:
  - `POST http://localhost/my-web-project/admin.php`
  - Cookie: Authenticated `gv_toan` session
  - Payload: `action=create_teacher&username=fake_admin&password=123456&full_name=Fake+Admin`
- **Assertions**:
  1. HTTP Status: Exactly `403 Forbidden`.
  2. HTML Body: Displays EduRadar Stitch UI 403 error page (`MÃ LỖI: HTTP 403 FORBIDDEN`).
  3. Database State: `SELECT COUNT(*) FROM users WHERE username = 'fake_admin'` equals `0`.
  4. Audit Log: A record exists in `audit_logs` logging unauthorized attempt by user `gv_toan` (ID 2).

#### Scenario A.3: Teacher Forged Batch Assignment POST
- **Objective**: Verify that a teacher cannot self-assign classes or modify `teacher_classes`.
- **Request**:
  - `POST http://localhost/my-web-project/admin.php`
  - Cookie: Authenticated `gv_toan` session
  - Payload: `action=batch_assign&teacher_id=2&subject=Toán&class_ids[]=2&class_ids[]=3`
- **Assertions**:
  1. HTTP Status: Exactly `403 Forbidden`.
  2. Database State: `SELECT COUNT(*) FROM teacher_classes WHERE teacher_id = 2 AND class_id IN (2, 3)` equals `0`.

---

### Category B: Broken Object-Level Authorization (BOLA / IDOR) Attacks

#### Scenario B.1: Unauthorized Class Grade Modification (BOLA)
- **Objective**: Verify that `gv_toan` (assigned to Class 1 `6A1` and Class 10 `7A1` for 'Toán') cannot view or save grades for Class 2 `6A2`.
- **Attack 1 (GET Inspection)**:
  - `GET http://localhost/my-web-project/nhap_diem.php?class_id=2&subject=Toán`
  - Cookie: `gv_toan` session
  - Assertions: HTTP Status `403 Forbidden`; HTML renders 403 error card; audit log recorded.
- **Attack 2 (POST Save Scores)**:
  - `POST http://localhost/my-web-project/nhap_diem.php?class_id=2&subject=Toán`
  - Cookie: `gv_toan` session
  - Payload: `action=save_scores&scores[10][tx1]=10.0`
  - Assertions: HTTP Status `403 Forbidden`; `SELECT COUNT(*) FROM scores WHERE student_id = 10 AND subject = 'Toán'` remains unchanged (0 modification).

#### Scenario B.2: Unauthorized Subject Grade Modification (BOLA)
- **Objective**: Verify that `gv_toan` cannot alter grades for unassigned subject 'Ngữ Văn' in Class 1 `6A1` (assigned to `gv_van`).
- **Request**:
  - `POST http://localhost/my-web-project/nhap_diem.php?class_id=1&subject=Ngữ+Văn`
  - Cookie: `gv_toan` session
  - Payload: `action=save_scores&scores[1][tx1]=10.0`
- **Assertions**:
  1. HTTP Status: `403 Forbidden`.
  2. Database State: Student 1's score for 'Ngữ Văn' remains unchanged at baseline (`7.5`).
  3. Audit Log: Logged with message "không được phân công dạy môn [Ngữ Văn] tại lớp ID [1]".

#### Scenario B.3: IDOR Parameter Tampering (`?teacher_id=...`)
- **Objective**: Verify that teachers cannot bypass access control by tampering with the `teacher_id` query parameter.
- **Attack 1 (Teacher Tampering)**:
  - `GET http://localhost/my-web-project/nhap_diem.php?teacher_id=3&class_id=1&subject=Ngữ+Văn`
  - Cookie: `gv_toan` session (user ID 2)
  - Assertions: HTTP Status `403 Forbidden`; audit log recorded: "Giáo viên [gv_toan] cố tình giả mạo tham số teacher_id=3".
- **Negative Control (Admin Legitimate Inspection)**:
  - `GET http://localhost/my-web-project/nhap_diem.php?teacher_id=2&class_id=1&subject=Toán`
  - Cookie: `admin` session (user ID 1)
  - Assertions: HTTP Status `200 OK`; HTML renders gradebook with banner "Admin đang kiểm tra"; audit log recorded: "Admin kiểm tra sổ điểm".

---

### Category C: Cross-Class Student ID Injections

#### Scenario C.1: Injected Foreign Student ID in Gradebook POST
- **Objective**: In `nhap_diem.php`, an attacker teacher authorized for Class 1 submits a payload containing a valid student ID from Class 2 (`student_id = 6`).
- **Request**:
  - Target: Authorized class (`class_id = 1`, `subject = Toán`)
  - Cookie: `gv_toan` session
  - Payload:
    ```
    action=save_scores
    scores[1][tx1]=8.5
    scores[6][tx1]=10.0   <-- Student 6 belongs to Class 2 (Foreign!)
    ```
- **Assertions**:
  1. HTTP Status: `200 OK` (Graceful handling: Class 1 student is processed, foreign student is silently filtered out).
  2. Database Verification:
     - `SELECT tx1 FROM scores WHERE student_id = 1 AND subject = 'Toán'` equals `8.5`.
     - `SELECT tx1 FROM scores WHERE student_id = 6 AND subject = 'Toán'` is `NULL` or unchanged.
  3. Invariant: Foreign class student roster and score rows remain 100% isolated.

#### Scenario C.2: Non-Existent Student ID Injection
- **Payload**: `scores[999999][tx1]=9.0`
- **Assertions**:
  1. HTTP Status: `200 OK`.
  2. Database State: No row created in `scores` for `student_id = 999999`.
  3. Clean HTML: Zero SQL or PHP array offset warnings emitted.

---

### Category D: Malformed & Malicious File Uploads

#### Scenario D.1: Non-ZIP Text Disguised as `.xlsx`
- **Attack Payload**: A file with content `"NOT A VALID ZIP ARCHIVE"` uploaded with filename `corrupt.xlsx` to `nhap_diem.php?action=import_file`.
- **Assertions**:
  1. HTTP Status: `200 OK` (No 500 Internal Server Error).
  2. Clean HTML: Contains user-friendly error notice ("Vui lòng chọn file hợp lệ" or "không có dữ liệu").
  3. Zero PHP Fatal Errors or unhandled ZipArchive warnings.

#### Scenario D.2: Truncated ZIP Archive
- **Attack Payload**: First 120 bytes of a valid `.xlsx` file, cutting off the central directory header.
- **Assertions**:
  1. Handled gracefully without crash; returns clean HTML.
  2. Zero database mutations.

#### Scenario D.3: Random Binary Garbage Upload
- **Attack Payload**: 2048 random bytes (`random_bytes(2048)`) with extension `.xlsx`.
- **Assertions**:
  1. Parsed safely without memory corruption or PHP crash.
  2. Database remains in pristine state.

#### Scenario D.4: CSV & Excel Formula Injection Defense
- **Attack Payload**: Student name set to `=cmd|'/C calc'!A0` or `+SUM(1+1)`.
- **Assertions**:
  1. Data is ingested as pure literal string.
  2. When rendered in HTML tables, output is properly escaped (`&equals;` or literal `=`) via `htmlspecialchars()`.
  3. No formula execution or client-side evaluation occurs.

#### Scenario D.5: Out-of-Bounds Grade Values
- **Attack Payload**: Gradebook file containing score values `-5.0`, `15.0`, `'NaN'`, `'test'`.
- **Assertions**:
  1. Values are sanitized to `NULL` during import.
  2. Database `scores` table contains only valid `0.0`–`10.0` decimals or `NULL`.

---

### Category E: SQL Injection (SQLi) Defense

#### Scenario E.1: Login Page SQL Injection Attempts
- **Payload 1**: `username = ' OR '1'='1` with arbitrary password.
- **Payload 2**: `username = admin' --` with arbitrary password.
- **Assertions**:
  1. HTTP Status: `200 OK` (re-rendering login page).
  2. HTML Body: Displays flash alert "Tên đăng nhập hoặc mật khẩu không chính xác!".
  3. Authentication State: Session `user_id` is NOT set.
  4. Audit Log: An entry is created in `audit_logs` with action `'Đăng nhập thất bại'` and details containing the escaped username string.
  5. Zero SQL syntax error messages in response.

#### Scenario E.2: Route Parameter SQL Injection
- **Request**: `GET http://localhost/my-web-project/nhap_diem.php?class_id=1%27%20OR%201=1--&subject=To%C3%A1n`
- **Assertions**:
  1. Parameter casting `(int)$_GET['class_id']` safely extracts integer `1` or triggers 403.
  2. Zero SQL exceptions exposed in output.

---

## 4. Deterministic Clean Fixture Lifecycle Protocol

To guarantee that `tests/run_tests.php` is non-destructive, idempotent, and re-runnable an infinite number of times without polluting MariaDB `edu_radar`, the following lifecycle protocol is established.

### 4.1 The Seed Preservation Principle
The database seed data defined in `database.sql` represents the production baseline:
- `users`: IDs 1 (`admin`), 2 (`gv_toan`), 3 (`gv_van`) with password hash for `123456`.
- `classes`: 36 classes (IDs 1..36, names `6A1`..`9C3`).
- `teacher_classes`: Assignments (ID 1: `gv_toan` on 6A1 Toán, ID 2: `gv_toan` on 7A1 Toán, ID 3: `gv_van` on 6A1 Ngữ Văn).
- `students`: 5 students in Class 1 (`6A1`).
- `scores`: Initial grades for Students 1..5.

**Rule**: The test suite MUST NEVER permanently delete, overwrite, or corrupt these baseline seed records. Any test modifying seed state must either:
1. Revert modified columns back to initial values in a `finally` block, OR
2. Perform all destructive/import/upsert tests on **dedicated namespaced test fixtures**.

### 4.2 Namespacing Scheme
All temporary entities created during test execution MUST conform to strict naming rules:

| Entity | Namespace / Pattern | Example | Rationale |
|---|---|---|---|
| Class | `9Z[0-9]_E2E` | `9Z9_E2E` | Satisfies regex `/^[6-9][A-Z0-9_]{1,20}$/i` while avoiding all 36 seed class names (`6A1`..`9C3`). |
| Teacher Account | `e2e_gv_[a-z0-9]+` | `e2e_gv_test` | Satisfies regex `/^[a-zA-Z0-9_]{3,30}$/`, distinct from `admin`, `gv_toan`, `gv_van`. |
| Student Code | `E2E_HS[0-9]+` | `E2E_HS001` | Distinct from seed codes `HS6A101`..`HS6A105`. |
| Student Name | `Nguyễn E2E Kiểm Thử [0-9]+` | `Nguyễn E2E Kiểm Thử 1` | Instantly identifiable in logs and queries. |
| Temp Files | `sys_get_temp_dir() . '/e2e_*.xlsx'` | `C:\Users\...\e2e_roster_1a2b.xlsx` | Standard OS temp storage, automatically cleanable. |
| Cookie Jars | `sys_get_temp_dir() . '/cookie_e2e_*.txt'` | `C:\Users\...\cookie_e2e_admin.txt` | Isolated session storage. |

### 4.3 Four-Phase Fixture Lifecycle Engine

```
[Phase 0: Pre-Flight Audit & Purge]
       │
       ▼
[Phase 1: In-Test Entity Registration]
       │
       ▼
[Phase 2: Execution & Invariant Assertions]
       │
       ▼
[Phase 3: Cascading Teardown & File Unlink]
       │
       ▼
[Phase 4: Post-Flight Baseline Invariance Verification]
```

#### Phase 0: Pre-Flight Audit & Residual Purge
Before executing any test:
1. Connect via PDO to MySQL `edu_radar`.
2. Clean up any leftover records from previously interrupted runs:
   ```sql
   DELETE FROM classes WHERE name LIKE '%E2E%';
   DELETE FROM users WHERE username LIKE 'e2e_%';
   DELETE FROM students WHERE full_name LIKE '%E2E%';
   ```
3. Record baseline counts:
   ```php
   $baselineUsers   = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
   $baselineClasses = (int)$pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
   $baselineStudents = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
   ```

#### Phase 1: In-Test Entity Registration
Maintain a centralized `$fixtureRegistry` object:
```php
class FixtureRegistry
{
    public array $createdClassIds = [];
    public array $createdUserIds = [];
    public array $createdTempFiles = [];
    public array $cookieFiles = [];

    public function trackClass(int $id): void { $this->createdClassIds[] = $id; }
    public function trackUser(int $id): void { $this->createdUserIds[] = $id; }
    public function trackFile(string $path): void { $this->createdTempFiles[] = $path; }
    public function trackCookie(string $path): void { $this->cookieFiles[] = $path; }
}
```

#### Phase 2: Execution & Cascading Cleanup
Because foreign keys in MySQL are configured with `ON DELETE CASCADE`:
- Deleting a class automatically cascades and deletes:
  - Associated records in `teacher_classes`
  - Associated records in `students`
  - Associated records in `scores` (via student cascade)
- Thus, deleting tracked class IDs cleanly wipes all test data in one atomic query:
  ```php
  if (!empty($registry->createdClassIds)) {
      $in = implode(',', array_map('intval', $registry->createdClassIds));
      $pdo->exec("DELETE FROM classes WHERE id IN ($in)");
  }
  ```

#### Phase 3: File & Cookie Teardown
```php
foreach ($registry->createdTempFiles as $file) {
    if (file_exists($file)) @unlink($file);
}
foreach ($registry->cookieFiles as $cookie) {
    if (file_exists($cookie)) @unlink($cookie);
}
```

#### Phase 4: Post-Flight Baseline Invariance Verification
At test suite completion, verify that the database returned to its exact initial state:
```php
$finalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$finalClasses = (int)$pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();

if ($finalUsers !== $baselineUsers || $finalClasses !== $baselineClasses) {
    throw new RuntimeException("Database pollution detected! Expected baseline counts, found delta.");
}
```

#### Disaster Recovery Hook: `register_shutdown_function`
If the test runner encounters a fatal error, exit, or SIGINT interrupt, a shutdown handler ensures cleanup executes regardless:
```php
register_shutdown_function(function() use ($pdo, $registry) {
    // Perform Phase 2 and Phase 3 cleanups unconditionally
});
```

---

## 5. Architectural Blueprints for `tests/run_tests.php`

### 5.1 Authentic HTTP Client Engine Blueprint

```php
/**
 * Isolated Multi-Account HTTP Client for Live Apache Communication
 */
class LiveHttpClient
{
    private string $baseUrl;
    private string $cookiePath;
    private array $trackedCookies;

    public function __construct(string $baseUrl, array &$cookieTracker)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->cookiePath = sys_get_temp_dir() . '/e2e_cookie_' . bin2hex(random_bytes(6)) . '.txt';
        $cookieTracker[] = $this->cookiePath;
    }

    public function request(string $method, string $path, array $data = [], array $headers = [], bool $followRedirects = false): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        $ch = curl_init();

        $opts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_COOKIEJAR => $this->cookiePath,
            CURLOPT_COOKIEFILE => $this->cookiePath,
            CURLOPT_FOLLOWLOCATION => $followRedirects,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 15,
        ];

        if (!empty($headers)) {
            $opts[CURLOPT_HTTPHEADER] = $headers;
        }

        $upper = strtoupper($method);
        if ($upper === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = is_array($data) && !isset($data['_raw_multipart']) 
                ? http_build_query($data) 
                : ($data['_raw_multipart'] ?? $data);
        } elseif ($upper !== 'GET') {
            $opts[CURLOPT_CUSTOMREQUEST] = $upper;
        }

        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);

        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("cURL execution failed on [$url]: $err");
        }

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $headerStr = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);

        // Run automated Error Gate on response body
        assertCleanHtml($body, $path);

        return [
            'status'  => $status,
            'headers' => $headerStr,
            'body'    => $body,
        ];
    }

    public function get(string $path, array $headers = [], bool $followRedirects = false): array
    {
        return $this->request('GET', $path, [], $headers, $followRedirects);
    }

    public function post(string $path, array $data, array $headers = [], bool $followRedirects = false): array
    {
        return $this->request('POST', $path, $data, $headers, $followRedirects);
    }

    public function postMultipart(string $path, array $fields): array
    {
        $fields['_raw_multipart'] = $fields;
        return $this->post($path, $fields);
    }

    public function login(string $username, string $password): bool
    {
        $res = $this->post('login.php', [
            'username' => $username,
            'password' => $password
        ]);
        return ($res['status'] === 302 || $res['status'] === 200);
    }
}
```

### 5.2 Deduplication Invariant Verification Method

```php
/**
 * Empirically test deduplication by submitting the exact same payload twice.
 */
function verifyDeduplicationInvariant(
    callable $submitFn,
    callable $countQueryFn,
    string $entityName
): void {
    $initialCount = $countQueryFn();

    // 1st submission
    $submitFn();
    $countAfterFirst = $countQueryFn();
    if ($countAfterFirst <= $initialCount) {
        throw new RuntimeException("Initial submission of $entityName failed to insert new records.");
    }
    $createdCount = $countAfterFirst - $initialCount;

    // 2nd identical submission
    $submitFn();
    $countAfterSecond = $countQueryFn();

    // Invariant: Second submission must yield exactly 0 additional rows
    if ($countAfterSecond !== $countAfterFirst) {
        $duplicates = $countAfterSecond - $countAfterFirst;
        throw new RuntimeException("Deduplication failure for $entityName! Second submission created $duplicates duplicate row(s).");
    }
}
```

---

## 6. Synthesis & Recommended Action Plan for Worker

When `worker_m6` implements `tests/run_tests.php`, they must follow this blueprint:

1. **Test Runner Layout**:
   - Establish CLI console visual hierarchy (colored ANSI badges `[PASS]`, `[FAIL]`, execution timers, section headers).
   - Initialize PDO MySQL connection singleton (`db()`).
   - Run Pre-flight Audit to capture baseline and clean residual test records.
   - Register shutdown handler for guaranteed cleanup.

2. **Session Matrix Setup**:
   - Create 4 instances of `LiveHttpClient`:
     1. `$adminClient` (authenticated as `admin` / `123456`)
     2. `$toanClient` (authenticated as `gv_toan` / `123456`)
     3. `$vanClient` (authenticated as `gv_van` / `123456`)
     4. `$guestClient` (unauthenticated session)

3. **Execution of the 8 Acceptance Criteria with Integrated Adversarial Tests**:
   - **T1: Multi-Account Auth & SQLi Rejection**: Verify login for `admin`, `gv_toan`, `gv_van`, rejection of wrong password `1234567`, rejection of `' OR '1'='1`, and audit log creation.
   - **T2: RBAC & BOLA Protection**: Verify `guestClient` and `toanClient` blocked on `admin.php` (403/302); verify `toanClient` blocked on unassigned class `6A2` and unassigned subject `Ngữ Văn` (403); verify `?teacher_id=3` IDOR blocked (403).
   - **T3: Admin Class Matrix & Deduplicated Batch Assignment**: Create test class `9Z9_E2E`; batch-assign `gv_toan`; repeat assignment to assert 0 duplicates.
   - **T4: Student Roster OpenXML Import**: Generate valid OpenXML `.xlsx` using `create_xlsx()`; import into `9Z9_E2E`; repeat import to assert 0 duplicate students.
   - **T5: VnEdu Gradebook Inline Saving & Cross-Class Injection**: Save grades as `gv_toan` on `9Z9_E2E`; verify formula-accurate `avg_score` in MySQL; inject foreign student ID and assert 0 foreign modification.
   - **T6: OpenXML Bulk Gradebook Import/Export**: Export gradebook to `.xlsx` and verify ZipArchive validity; import `.xlsx` with upsert; test corrupted/truncated `.xlsx` and assert HTTP 200 clean error.
   - **T7: Early-Warning Radar Diagnostics & Zero Zalo**: Verify Red, Yellow, Green status invariants; scan HTML for 0 occurrences of `zalo`, `actionModal`, and `copy-btn`.
   - **T8: Comprehensive Audit Log Forensic Verification**: Assert that all previous actions produced expected records in `audit_logs`.

4. **Teardown Execution**:
   - Execute Phase 3 teardown, unlinking temporary `.xlsx` files and cookie jars, deleting test class `9Z9_E2E` with automatic CASCADE deletion of test assignments, students, and scores.
   - Execute Phase 4 baseline invariance assertion.
   - Output summary footer: `Total Tests: N | Passed: N | Failed: 0`.

---
*Report certified by `explorer_e2e_3`.*
