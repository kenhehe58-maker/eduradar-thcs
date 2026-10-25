# Handoff Report — explorer_e2e_2: Comprehensive 4-Tier Test Matrix Design

**Agent**: `explorer_e2e_2` (E2E Feature Coverage & Scenario Explorer)  
**Recipient**: `parent` (Orchestrator conversation ID: `391812a0-32ec-4263-b097-6162669cc4f7`)  
**Date**: 2026-10-17  
**Working Directory**: `D:\New folder\my-web-project\.agents\teamwork\explorer_e2e_2`  
**Artifacts**:
- `D:\New folder\my-web-project\.agents\teamwork\explorer_e2e_2\analysis.md`
- `D:\New folder\my-web-project\.agents\teamwork\explorer_e2e_2\handoff.md`
- `D:\New folder\my-web-project\.agents\teamwork\explorer_e2e_2\BRIEFING.md`
- `D:\New folder\my-web-project\.agents\teamwork\explorer_e2e_2\progress.md`

---

## 1. Observation

Direct observations from examining the codebase, database schema, existing verification suites, and specification documents:

1. **Test Runner Gap**:
   - `tests/` currently contains milestone-specific verifications (`verify_m1.php` through `verify_m5.php` and `challenge_m*.php`), but `tests/run_tests.php` does NOT exist yet (`find_by_name` returned only directory `tests`).
2. **Database Engine & Schema Contracts (`database.sql`, `includes/db.php`)**:
   - `includes/db.php:21-43` defines singleton `db(): PDO` with strict MySQL options:
     ```php
     $options = [
         PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
         PDO::ATTR_EMULATE_PREPARES   => false,
         PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci",
     ];
     ```
   - Zero SQLite code exists; `database.sqlite` is absent.
   - 6 tables in MySQL `edu_radar`: `users, classes, teacher_classes, students, scores, audit_logs`.
   - 5 mandatory `UNIQUE` constraints in `database.sql`:
     - Line 31: `UNIQUE KEY unique_username (username)` on `users`
     - Line 43: `UNIQUE KEY unique_class_name (name)` on `classes`
     - Line 56: `UNIQUE KEY unique_assignment (teacher_id, class_id, subject)` on `teacher_classes`
     - Line 75: `UNIQUE KEY unique_class_student (class_id, full_name, birthday)` on `students`
     - Line 96: `UNIQUE KEY unique_student_subject (student_id, subject)` on `scores`
3. **Authentication & Session Rotation (`login.php`, `logout.php`)**:
   - `login.php:36` strictly uses `password_verify($password, $user['password'])` with no backdoor bypass.
   - `login.php:38` invokes `session_regenerate_id(true)` upon successful authentication.
   - `login.php:46-51` logs `'Đăng nhập thành công'` to `audit_logs`.
   - `login.php:62-67` logs `'Đăng nhập thất bại'` to `audit_logs` on bad credentials.
   - `logout.php:17-19` logs `'Đăng xuất'` to `audit_logs`, clears `$_SESSION = []`, expires session cookie, and invokes `session_destroy()`.
4. **Strict RBAC & Anti-BOLA / IDOR Route Guards (`includes/auth.php`, `nhap_diem.php`)**:
   - `includes/auth.php:77-95`: `require_admin()` sets HTTP 403, logs `'Truy cập trái phép bị chặn'` to `audit_logs`, and outputs `render_forbidden_page()`.
   - `nhap_diem.php:29-36`: Teachers attempting to pass foreign `?teacher_id=` are blocked with HTTP 403 and logged.
   - `nhap_diem.php:68-84`: Teachers accessing unassigned `class_id` or `subject` are blocked with HTTP 403 via `require_teacher_assignment()`.
   - `nhap_diem.php:204-206` & `237-240`: Anti-BOLA whitelist ignores students not enrolled in `$selectedClassId` during `action=save_scores`.
5. **Admin Class Matrix, Batch Assignment & Student Import (`admin.php`)**:
   - `admin.php:94-133`: `action=add_class` checks `UNIQUE(name)` before insert.
   - `admin.php:207-259`: `action=batch_assign` skips duplicate `teacher_classes` and reports skipped count.
   - `admin.php:333-450`: `action=import_students` auto-detects headers, normalizes dates (`YYYY-MM-DD`), and deduplicates via `UNIQUE(class_id, full_name, birthday)` / error 1062 catch.
6. **Spreadsheet Gradebook & OpenXML Engine (`nhap_diem.php`, `includes/xlsx_helper.php`)**:
   - `nhap_diem.php:198-279`: Inline save parses scores (0.0 to 10.0), calculates weighted average according to Circular 22 formula:
     ```php
     if ($gk !== null && $ck !== null) {
         $avg = round(($sTx + 2 * $gk + 3 * $ck) / ($nTx + 5), 1);
     } else {
         $weight = $nTx + ($gk !== null ? 2 : 0) + ($ck !== null ? 3 : 0);
         if ($weight > 0) {
             $avg = round(($sTx + 2 * ($gk ?? 0.0) + 3 * ($ck ?? 0.0)) / $weight, 1);
         }
     }
     ```
   - `nhap_diem.php:103-193`: `?export=xlsx` outputs native binary OpenXML created via pure PHP `ZipArchive` without Composer.
7. **Early-Warning Radar & Zero Zalo (`index.php`, `includes/radar_helper.php`)**:
   - `includes/radar_helper.php:39-129`: Authentic `evaluateRiskZone()` classifies into 🔴 Nguy cấp, 🟡 Cần chú ý, 🟢 Ổn định.
   - `auditor_m5_it2_1/audit.md:18`: Exhaustive grep confirmed **0 occurrences of 'zalo', 'actionModal', 'copy-btn'** across application files.

---

## 2. Logic Chain

1. **From Observation 1**: Milestone 6 / Feature #18 mandates a single unified CLI test suite `tests/run_tests.php` runnable via `D:\xam\php\php.exe tests/run_tests.php` to prove acceptance criteria across all 18 features.
2. **From Observation 2 & 7**: Testing must not rely on mocks. A 4-Tier hierarchy provides strict fail-fast gating:
   - **Tier 1** verifies fundamental infrastructure (PHP extensions, Apache live response, MySQL tables, constraints, baseline seeds, zero SQLite, zero Zalo). If this fails, running functional flows would yield misleading cascading errors.
   - **Tier 2** verifies authentication and security boundaries across all 3 roles (`admin`, `gv_toan`, `gv_van`), session rotation, and HTTP 403 route guards before testing state-changing actions.
   - **Tier 3** tests stateful end-to-end workflows (Admin Matrix, Batch Checkbox Assignment, Student Import, Spreadsheet Gradebook, Inline Saving, Bulk Import, OpenXML Export, Early-Warning Radar).
   - **Tier 4** tests edge-case integrity (deduplication on re-submission, BOLA cross-class student injection, score out-of-range sanitization, full forensic audit trail validation, and zero PHP warnings).
3. **From Observation 4 & 5**: Deduplication is a core acceptance criterion across 4 distinct domains: class names (`UNIQUE(name)`), teacher assignments (`UNIQUE(teacher_id, class_id, subject)`), student roster imports (`UNIQUE(class_id, full_name, birthday)`), and score rows (`UNIQUE(student_id, subject)`). Therefore, Tier 4 must perform explicit double-submissions and verify that database counts do not increase and skipped counters are displayed.
4. **From Observation 3 & 6**: Audit logging is mandatory for every state change. The test matrix must assert that each tested operation (login, bad login, logout, unauthorized attempt, create class, delete class, create teacher, assign, unassign, import students, save scores, import scores, export scores) inserts an exact row into `audit_logs` in MySQL.

---

## 3. Caveats

1. **Live XAMPP Requirement**: `tests/run_tests.php` requires live XAMPP Apache on port 80 and live MySQL on port 3306. It will not pass in an offline/mock environment (this is an intentional design constraint to ensure anti-facade integrity).
2. **Production Baseline Preservation**: Tests that create new entities (e.g. test class `6D1_TEST` or test teacher `gv_test_t3`) must clean up after themselves to ensure the test suite is 100% idempotent and can be run consecutively without database corruption.
3. **Password Hash Runtime**: Running multiple Bcrypt verifications during CLI execution introduces a brief CPU overhead (~50-100ms per auth). With 41 tests, total runtime is estimated at ~2-4 seconds, well within CLI timeouts.

---

## 4. Conclusion

The 4-Tier test matrix specified in `analysis.md` completely covers all 18 features from `PROJECT.md` and all 8 Acceptance Criteria from `ORIGINAL_REQUEST.md`:
- **41 Concrete Test Cases** across 4 distinct tiers:
  - **Tier 1 (Environment, Schema & Static Health)**: 6 test cases (T1.01–T1.06)
  - **Tier 2 (Multi-Account Authentication & Strict RBAC)**: 14 test cases (T2.01–T2.14)
  - **Tier 3 (Core Functional & Business Workflows)**: 12 test cases (T3.01–T3.12)
  - **Tier 4 (Deduplication, Anti-Tampering & Audit Forensics)**: 9 test cases (T4.01–T4.09)
- **Zero Mocks / Genuine HTTP & MySQL**: Implements independent cURL cookie jars per role and genuine PDO queries.
- **Traceability**: Every feature has at least one dedicated test assertion, with full parameter specs, payload definitions, expected HTTP codes, and database assertions detailed in `analysis.md`.

---

## 5. Verification Method

To verify this test matrix design against the existing codebase and environment:

1. **Review Detailed Specification**:
   Inspect `D:\New folder\my-web-project\.agents\teamwork\explorer_e2e_2\analysis.md` for full test parameters and assertions.
2. **Execute Current Milestone Verification Suites**:
   Run existing verification suites to confirm all baseline behaviors match the matrix expectations:
   ```cmd
   D:\xam\php\php.exe tests/verify_m1.php
   D:\xam\php\php.exe tests/verify_m2.php
   D:\xam\php\php.exe tests/verify_m3.php
   D:\xam\php\php.exe tests/verify_m4.php
   D:\xam\php\php.exe tests/verify_m5.php
   ```
   All existing suites pass with 0 failures.
3. **Invalidation Conditions**:
   The test matrix is invalidated if:
   - Any of the 18 features lacks test coverage in `analysis.md`.
   - Any test relies on simulated/mocked classes rather than genuine HTTP/PDO.
   - The runner leaves residual dirty test records that cause subsequent runs to fail.
