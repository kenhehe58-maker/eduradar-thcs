# Handoff Report — E2E Adversarial Security & Anti-Facade Architecture

- **Author**: `explorer_e2e_3`
- **Role**: E2E Adversarial Testing & Anti-Facade Explorer
- **Date**: 2026-10-17T11:49:00Z
- **Target Deliverable**: `tests/run_tests.php`
- **Type**: Hard Handoff (Investigation & Specification Complete)

---

## 1. Observation

Direct empirical observations gathered from inspecting the codebase, live database, and running test suites:

1. **Live Environment & Execution Speed**:
   - `D:\xam\php\php.exe tests/verify_m1.php` ran in 1.8s with `12 / 12 [PASS]`, exit code `0`.
   - `D:\xam\php\php.exe tests/verify_m2.php` ran in 2.1s with `16 / 16 [PASS]`, exit code `0`.
   - `D:\xam\php\php.exe tests/challenge_m4_excel_bola.php` ran in 3.0s with `35 / 35 [PASS]`, exit code `0`.
   - Apache is actively listening on port 80 (`http://localhost/my-web-project/`) and MariaDB 10.4 is listening on port 3306 (`127.0.0.1:3306`, DB `edu_radar`).

2. **Access Control & BOLA/IDOR Enforcement Mechanisms in Production**:
   - In `includes/auth.php:71-96`, `require_admin()` checks `$_SESSION['role'] === 'admin'`, logs unauthorized access to `audit_logs` via `log_action()`, issues HTTP 403 (`http_response_code(403)`), and renders `render_forbidden_page()`.
   - In `includes/auth.php:140-171`, `require_teacher_assignment($teacher_id, $class_id, $subject)` enforces that non-admin sessions match `teacher_id === $_SESSION['user_id']` and queries `teacher_classes`. Violations trigger HTTP 403 and `audit_logs`.
   - In `nhap_diem.php:17-36`, IDOR tampering is explicitly guarded:
     ```php
     if (isset($_GET['teacher_id']) && (int)$_GET['teacher_id'] !== $realUserId) {
         http_response_code(403);
         log_action($realUserId, $_SESSION['username'], 'Truy cập trái phép bị chặn', ...);
         render_forbidden_page(...);
         exit;
     }
     ```
   - In `nhap_diem.php:204-240`, cross-class student injection is mitigated by building an in-memory whitelist map of valid student IDs for the target class:
     ```php
     $vStmt = $pdo->prepare("SELECT id FROM students WHERE class_id = ?");
     $vStmt->execute([$selectedClassId]);
     $validStudentMap = array_flip($vStmt->fetchAll(PDO::FETCH_COLUMN));
     ...
     if (!isset($validStudentMap[$studentId])) {
         continue; // foreign students are silently ignored
     }
     ```

3. **OpenXML File Ingestion & Malformation Resiliency**:
   - In `includes/xlsx_helper.php:124-127`, `parse_xlsx()` safely wraps `ZipArchive::open()`:
     ```php
     $zip = new ZipArchive();
     if ($zip->open($filepath) !== true) {
         return [];
     }
     ```
     If the file is non-zip text, corrupted binary, or truncated, it safely returns `[]` without throwing fatal unhandled exceptions or crashing PHP.
   - In `nhap_diem.php:430-433`, when `parse_xlsx()` returns empty, the controller sets `$errorMsg = "Vui lòng chọn file hợp lệ để tải lên!"` and renders HTTP 200 with friendly feedback.

4. **Database Cascade Deletion Invariants**:
   - In `database.sql:59-61, 78, 98`:
     `classes` has `ON DELETE CASCADE` down to `teacher_classes`, `students`, and `scores`.
     Deleting a class cleanly and atomically wipes all child assignments, students, and grades.

5. **Historical Integrity Defects**:
   - In `auditor_m5_it2_1/audit.md`, Iteration 1 had mock stubs (`testEvaluateRisk()`), hardcoded math arrays, and undefined variables in live HTML (`$isAvgWarning`), which required a complete remediation in Iteration 2 to achieve a CLEAN verdict.

---

## 2. Logic Chain

1. **From Observation 1 (Live Server & Speed)**:
   - Because Apache and MariaDB respond in milliseconds over localhost, `tests/run_tests.php` has zero reason to rely on in-process mocks or simulated environments. All HTTP requests can and must be executed live via cURL over `http://localhost/my-web-project/`.

2. **From Observation 2 & 5 (Access Control & Historical Integrity Defects)**:
   - To prevent regressions or facade testing, the test runner must systematically verify both positive and negative access control boundaries:
     - Admin access succeeds (HTTP 200).
     - Teacher attempting Admin actions is blocked (HTTP 403).
     - Teacher attempting unassigned class or unassigned subject is blocked (HTTP 403).
     - Teacher tampering with `?teacher_id=X` is blocked (HTTP 403).
     - Each of these rejections must be forensically confirmed in MySQL `audit_logs`.
   - Every live response body must be scanned for PHP Warnings, Notices, and Exceptions via the Live HTML Error Gate.

3. **From Observation 2 (Cross-Class Student Injection)**:
   - Because `nhap_diem.php` employs `validStudentMap`, submitting a forged payload with a student ID from another class tests this defense mechanism.
   - By querying the foreign student's score in MariaDB before and after the forged submission, the test runner can verify that the foreign student's score had 0 modifications.

4. **From Observation 3 (Malformed File Resiliency)**:
   - Passing truncated zips, random binary bytes, and non-zip text files via multipart cURL verifies that `parse_xlsx()` returns `[]` and the web UI renders a user-friendly error with HTTP 200, rather than crashing with an unhandled 500 error or PHP fatal error.

5. **From Observation 4 (Cascade Deletion Invariant)**:
   - Creating a single isolated test class (`9Z9_E2E`) allows all student roster imports, teacher assignments, and gradebook saves to occur within that class.
   - At the conclusion of the test run, executing `DELETE FROM classes WHERE name = '9Z9_E2E'` cleanly removes all test assignments, test students, and test scores in a single atomic database operation.
   - This ensures 100% preservation of seed data (36 classes, 3 seed users, 5 seed students) and enables infinite idempotent test re-runs.

---

## 3. Caveats

1. **Apache Port & Junction Assumption**: Assumes Apache is running on port 80 and the junction `D:\xam\htdocs\my-web-project` -> `D:\New folder\my-web-project` is intact. (Verified live during this investigation).
2. **Audit Log Volume**: Audit logs are designed to be immutable and grow with each action. The test runner should verify specific log entries generated during its run using ID boundaries (`id > $preTestMaxId`) rather than assuming static log counts.
3. **No Unrequested Features**: The test runner must strictly test only requested features from `ORIGINAL_REQUEST.md` (no Zalo button tests, no external services).

---

## 4. Conclusion

The adversarial security and anti-facade architecture for `tests/run_tests.php` is fully formulated and validated:
1. **100% Genuine Execution**: All network operations execute via live cURL against Apache port 80 with separate cookie jars; all database assertions execute via live PDO queries against MariaDB `edu_radar`. No mock stubs or hardcoded pass arrays are permitted.
2. **Robust Adversarial Coverage**:
   - Forged POSTs (unauth and teacher privilege escalation -> HTTP 403/302).
   - BOLA / IDOR attacks (unassigned class/subject tampering and `?teacher_id=3` tampering -> HTTP 403).
   - Cross-class student ID injection (foreign student score modification blocked -> verified in DB).
   - Malformed OpenXML files (corrupt zip, binary garbage, truncated file -> HTTP 200 graceful error, 0 fatal crashes).
   - SQLi attacks (login injection and route parameters -> neutralized by prepared statements).
   - Live HTML Cleanliness Gate (0 PHP Warnings, Notices, or Fatal Errors across all responses).
3. **Clean Fixture Protocol**: Isolated test class `9Z9_E2E` with foreign key cascade deletion and `register_shutdown_function()` ensures zero residual debris and 100% seed data preservation.

The design is ready for immediate implementation by `worker_m6`.

---

## 5. Verification Method

To independently verify the architecture and specifications:
1. **Review Specification Artifact**:
   - Inspect `D:\New folder\my-web-project\.agents\teamwork\explorer_e2e_3\analysis.md`.
2. **Execute Existing Ground-Truth Suites**:
   - `D:\xam\php\php.exe tests/verify_m1.php` (Passes 12/12)
   - `D:\xam\php\php.exe tests/verify_m2.php` (Passes 16/16)
   - `D:\xam\php\php.exe tests/challenge_m4_excel_bola.php` (Passes 35/35)
3. **Verify Baseline State Invariance**:
   - Run:
     ```powershell
     D:\xam\php\php.exe -r "require 'includes/db.php'; $p = db(); echo 'Users: ' . $p->query('SELECT COUNT(*) FROM users')->fetchColumn() . ', Classes: ' . $p->query('SELECT COUNT(*) FROM classes')->fetchColumn();"
     ```
   - Must output: `Users: 3, Classes: 36`.

---
*End of Handoff Report.*
