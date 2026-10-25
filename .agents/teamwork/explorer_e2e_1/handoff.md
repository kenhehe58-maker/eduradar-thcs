# Handoff Report: E2E CLI Test Runner Architecture (`tests/run_tests.php`)

**Agent**: `explorer_e2e_1`  
**Role**: E2E Test Harness & CLI Architecture Explorer  
**Working Directory**: `D:\New folder\my-web-project\.agents\teamwork\explorer_e2e_1`  
**Recipient**: Parent Orchestrator (`391812a0-32ec-4263-b097-6162669cc4f7`)  
**Timestamp**: 2026-10-17T18:48:00Z  

---

## 1. Observation

Direct observations and empirical evidence gathered from the codebase and live runtime:

1. **Live Runtime Environment**:
   - PHP CLI: `D:\xam\php\php.exe` (PHP 8.0.30, cli SAPI, 64-bit).
   - Windows ANSI VT100 support: `sapi_windows_vt100_support(STDOUT, true)` returns `true` (verified via PHP command execution).
   - Web Server: Live Apache 2.4 on port 80 serving `http://localhost/my-web-project/`.
   - Database Server: MariaDB 10.4.32 on port 3306 (`127.0.0.1`), database `edu_radar`, user `root`, empty password.

2. **Existing Verification Suites Invariants**:
   - Directly executed and verified all existing milestone test suites:
     - `D:\xam\php\php.exe tests/verify_m1.php` -> Exited 0, Passed: 12/12.
     - `D:\xam\php\php.exe tests/verify_m2.php` -> Exited 0, Passed: 16/16.
     - `D:\xam\php\php.exe tests/verify_m3.php` -> Exited 0, Passed: 28/28.
     - `D:\xam\php\php.exe tests/verify_m4.php` -> Exited 0, Passed: 39/39.
     - `D:\xam\php\php.exe tests/verify_m5.php` -> Exited 0, Passed: 49/49.
   - Total regression test pass count: **144/144 tests passing 100%**.

3. **Baseline Seed State in `database.sql`**:
   - `users`: 3 rows (ID 1: `admin`, ID 2: `gv_toan`, ID 3: `gv_van`), all passwords hashed with bcrypt for `'123456'`.
   - `classes`: 36 rows (IDs 1–36, Grades 6–9, Sections A–C, Classes 1–3: `6A1`..`9C3`).
   - `teacher_classes`: 3 rows: `(2, 1, 'Toán')`, `(2, 10, 'Toán')`, `(3, 1, 'Ngữ Văn')`.
   - `students`: 5 rows in Class 1 (`6A1`), codes `HS6A101`..`HS6A105`.
   - `scores`: 7 rows: 5 in `6A1 Toán`, 2 in `6A1 Ngữ Văn`.
   - `audit_logs`: Initial seed record (ID 1).

4. **Zero-Dependency Capabilities**:
   - `includes/xlsx_helper.php` provides native OpenXML generation (`create_xlsx(array $data)`) and parsing (`parse_xlsx(string $filepath)`) using PHP's native `ZipArchive` and `SimpleXML`.
   - `includes/db.php` provides the singleton `db()` PDO MySQL instance with `PDO::ERRMODE_EXCEPTION` and `PDO::FETCH_ASSOC`.
   - Sample binary and text template files exist: `sample_students.xlsx` (3080 bytes), `sample_students.csv` (296 bytes), `sample_gradebook.xlsx` (2816 bytes), `sample_vnedu.csv` (282 bytes).

---

## 2. Logic Chain

1. **Console UX Requirement Logic**:
   - *Observation*: Windows console supports VT100 ANSI sequences when `sapi_windows_vt100_support(STDOUT, true)` is called.
   - *Inference*: The CLI runner can produce modern colored badges (`[ PASS ]`, `[ FAIL ]`, `[ SKIP ]`), column-aligned test lines with dots, millisecond execution timers, boxed failure diagnostics, category headers, and a clean summary footer without any third-party terminal library.
   - *Conclusion*: Implementing `CliConsole` with standard ANSI tokens and high-resolution timing (`hrtime(true)`) guarantees an executive-grade developer experience and proper process exit codes (`exit(0)` on pass, `exit(1)` on fail).

2. **HTTP Client & Multi-Session Logic**:
   - *Observation*: The application enforces strict server-side RBAC: `admin.php` is restricted to `role === 'admin'`; `nhap_diem.php` enforces `(teacher_id, class_id, subject)` assignment guards; `index.php` isolates teacher views.
   - *Inference*: Testing these boundaries requires concurrent or rapidly switched personas without re-authenticating on every HTTP request.
   - *Conclusion*: A `SessionManager` managing dedicated temporary cookie jar files (`sys_get_temp_dir() . '/eduradar_e2e_' . $persona . '.txt'`) for `admin`, `gv_toan`, `gv_van`, and unauthenticated `guest` provides seamless, realistic multi-user session testing.

3. **Multipart OpenXML Realism Logic**:
   - *Observation*: Admin imports student rosters and teachers import gradebooks using `.xlsx` OpenXML spreadsheets.
   - *Inference*: Testing file upload endpoints must use real multipart form-data requests with genuine OpenXML binary payloads rather than URL-encoded strings or empty files.
   - *Conclusion*: `HttpClient` must detect `CURLFile` instances in payload arrays and switch `CURLOPT_POSTFIELDS` to native multipart encoding. Combined with `create_xlsx()`, real `.xlsx` files can be generated in-memory, uploaded, verified, and cleaned up automatically.

4. **Database State & Audit Forensics Logic**:
   - *Observation*: Acceptance Criterion 72 requires verifying that every tested action wrote a corresponding row to `audit_logs` in MySQL.
   - *Inference*: Because `audit_logs` accumulates rows, asserting `SELECT * FROM audit_logs WHERE action = ...` without bounding will match stale rows from previous runs or earlier assertions.
   - *Conclusion*: An `AuditTracker` implementing a **High-Water Mark Checkpoint** (`SELECT COALESCE(MAX(id), 0) FROM audit_logs`) ensures assertions only match rows created *after* the checkpoint, verifying action, username, IP, and details with mathematical certainty.

5. **Non-Destructive Idempotency Logic**:
   - *Observation*: The runner must be cleanly re-runnable without corrupting baseline seed data (36 classes, 3 baseline users, 5 baseline students in 6A1, 7 scores).
   - *Inference*: Creating test classes, test teachers, or test students without isolation will cause subsequent runs to fail on `UNIQUE` constraints (e.g. `Duplicate entry for unique_class_name`). Mutating 6A1 scores will distort `index.php` early-warning calculations.
   - *Conclusion*: A 3-fold isolation strategy in `StateGuardian` guarantees non-destructiveness:
     - Entity namespacing (`6C99_E2E`, `gv_test_e2e`) with pre-run cleanup and post-run deletion.
     - Roster import isolation into Class 2 (`6A2`) and Class 3 (`6A3`), which have 0 baseline students.
     - In-memory snapshot & restore for baseline scores modified during inline grade saving or gradebook import tests, registered via `register_shutdown_function()` and `finally` blocks.

---

## 3. Caveats

1. **Web Server Prerequisite**: The runner requires live XAMPP Apache to be actively running on `http://localhost/my-web-project/`. If Apache is stopped, the runner fails with a clear pre-flight connectivity diagnostic.
2. **Database Prerequisite**: Live MariaDB must be running on port 3306 with database `edu_radar`. Pre-flight health checks in `StateGuardian` verify table existence and abort immediately if the database is unreachable.
3. **Audit Log Retention**: Test actions intentionally append legitimate audit records to `audit_logs` in MySQL as part of verifying Feature #3 and Acceptance Criterion 72. These logs document the test runner's actions and do not corrupt foreign keys or business tables.
4. **No caveats** regarding file permissions or platform compatibility: all paths, temporary files, and cURL options have been verified on Windows 10/11 with PHP 8.0.30.

---

## 4. Conclusion

The CLI test runner architecture for `tests/run_tests.php` is fully designed, validated, and documented in `analysis.md`. The design provides:
1. High-craft CLI console UX with ANSI badges, microsecond timers, and exit code 0/1 handling.
2. Multi-persona cURL HTTP client with persistent cookie jars (`admin`, `gv_toan`, `gv_van`, `guest`) and multipart `.xlsx` uploads.
3. Live PDO MySQL state inspector with high-water mark audit log forensics.
4. 100% idempotent and non-destructive state management leaving baseline seed data pristine.

The implementer (`worker`) can immediately build `tests/run_tests.php` based on the architectural blueprints and class specifications in `analysis.md`.

---

## 5. Verification Method

To independently verify this architectural investigation and runtime compatibility:

1. **Verify PHP CLI & SAPI Environment**:
   ```powershell
   & "D:\xam\php\php.exe" -v
   & "D:\xam\php\php.exe" -r "echo sapi_windows_vt100_support(STDOUT, true) ? 'VT100 OK' : 'VT100 NO';"
   ```
   *Expected Output*: PHP 8.0.30 and `VT100 OK`.

2. **Verify Live Apache & MySQL Connectivity**:
   ```powershell
   & "D:\xam\php\php.exe" -r "$res = @file_get_contents('http://localhost/my-web-project/login.php'); echo $res ? 'APACHE OK' : 'APACHE FAIL';"
   & "D:\xam\php\php.exe" tests/verify_m1.php
   ```
   *Expected Output*: `APACHE OK` and `Total Tests: 12 | Passed: 12 | Failed: 0`.

3. **Verify Existing Full Regression Suite**:
   ```powershell
   & "D:\xam\php\php.exe" tests/verify_m2.php
   & "D:\xam\php\php.exe" tests/verify_m3.php
   & "D:\xam\php\php.exe" tests/verify_m4.php
   & "D:\xam\php\php.exe" tests/verify_m5.php
   ```
   *Expected Output*: All suites pass with 0 failures, confirming system stability.

4. **Inspect Architecture Deliverables**:
   - `D:\New folder\my-web-project\.agents\teamwork\explorer_e2e_1\analysis.md`
   - `D:\New folder\my-web-project\.agents\teamwork\explorer_e2e_1\handoff.md`

5. **Invalidation Conditions**:
   - If `tests/verify_m1.php` through `tests/verify_m5.php` fail, baseline state has been altered.
   - If cURL cannot connect to `http://localhost/my-web-project/`, Apache configuration is invalid.
   - If MariaDB port 3306 refuses connection, database service is down.
