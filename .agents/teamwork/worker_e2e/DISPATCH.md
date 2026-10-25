# DISPATCH — worker_e2e

## 2026-10-17T18:49:00Z
- **Role**: E2E Acceptance Test Suite Implementation Worker
- **Working Directory**: `D:\New folder\my-web-project\.agents\teamwork\worker_e2e`
- **Context & Mission**:
  Implement the master automated acceptance test runner `tests/run_tests.php` based on the blueprints authored by `explorer_e2e_1`, `explorer_e2e_2`, and `explorer_e2e_3`.

- **MANDATORY INTEGRITY WARNING**:
  DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A teamwork_preview_auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

- **Required Reading**:
  1. `D:\New folder\my-web-project\.agents\teamwork\ORIGINAL_REQUEST.md` (Specifically Acceptance Criteria: Automated Multi-Account & Deduplication Verification)
  2. `D:\New folder\my-web-project\.agents\teamwork\PROJECT.md` (Features #1 to #18)
  3. Explorer blueprints:
     - `D:\New folder\my-web-project\.agents\teamwork\explorer_e2e_1\analysis.md` & `handoff.md` (CLI console, HttpClient, AuditTracker, StateGuardian)
     - `D:\New folder\my-web-project\.agents\teamwork\explorer_e2e_2\analysis.md` & `handoff.md` (4-Tier test matrix with 41 test cases)
     - `D:\New folder\my-web-project\.agents\teamwork\explorer_e2e_3\analysis.md` & `handoff.md` (Adversarial security, anti-facade, live error scanning)

- **Exclusive File Ownership**:
  - `tests/run_tests.php` (PRIMARY)
  - `TEST_READY.md` (publish when test suite is complete)

- **Core Implementation Requirements**:
  1. **CLI Runner UX**:
     - Runnable via `D:\xam\php\php.exe tests/run_tests.php`.
     - Windows VT100 ANSI sequences enabled for colored output.
     - Formatted status badges: `[ PASS ]` in green, `[ FAIL ]` in bold red, `[ SKIP ]` in yellow.
     - Microsecond elapsed timers per test.
     - Final summary banner: Total tests, Passed, Failed, Skipped, and total elapsed duration.
     - Terminate strictly with `exit(0)` when all pass, `exit(1)` on any failure.
  2. **4-Tier Test Suite**:
     - **Tier 1: Environment & Schema Health**: Database connection, 6 MySQL tables, 0 SQLite files, Apache live HTTP 200, zero Zalo across files.
     - **Tier 2: Multi-Account Authentication & Strict RBAC**: Login for admin, gv_toan, gv_van (password 123456); wrong password rejection; session fixation rotation; unauthenticated redirect; teacher accessing admin.php (HTTP 403 / redirect); anti-BOLA protection on nhap_diem.php (HTTP 403 for unassigned class or subject).
     - **Tier 3: Core Functional & Business Workflows**: Secondary school class matrix (Grades 6–9); admin batch assignment in teacher_classes; student roster import; gradebook spreadsheet saving with Thông tư 22 weighted average; OpenXML .xlsx bulk import/export; Early-Warning Radar classification (Red, Yellow, Green).
     - **Tier 4: Deduplication, Security & Audit Logging**: Submitting batch assignment twice yields 0 duplicates; importing same student .xlsx twice yields 0 duplicates; cross-class student ID injection blocked; audit logging verified in MySQL audit_logs for all actions; 0 PHP runtime warnings/notices on live Apache.
  3. **Strict Non-Destructiveness**:
     - Use isolated test fixtures (e.g. `6C99_E2E`, `gv_test_e2e`, Class 2/3 imports) and clean up test rows via `register_shutdown_function()`, leaving baseline database data (`database.sql` seed) intact.

- **Verification Protocol**:
  1. Execute `D:\xam\php\php.exe tests/run_tests.php` and confirm **100% pass (0 failures)**.
  2. Run twice in succession to confirm idempotency (0 failures on repeated run).
  3. Run all existing test suites to confirm zero regressions:
     - `D:\xam\php\php.exe tests/verify_m1.php`
     - `D:\xam\php\php.exe tests/verify_m2.php`
     - `D:\xam\php\php.exe tests/challenge_m2_rbac.php`
     - `D:\xam\php\php.exe tests/verify_m3.php`
     - `D:\xam\php\php.exe tests/verify_m4.php`
     - `D:\xam\php\php.exe tests/verify_m5.php`
     - `D:\xam\php\php.exe tests/challenge_m5_radar.php`
  4. Create `TEST_READY.md` at project root with runner command, test summary, and coverage checklist.

- **Deliverables**:
  Write `changes.md` and `handoff.md` in `D:\New folder\my-web-project\.agents\teamwork\worker_e2e`.
  Send a message to your parent when done.
