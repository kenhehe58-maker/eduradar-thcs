# Comprehensive 4-Tier Test Matrix Analysis for `tests/run_tests.php`

**Author**: `explorer_e2e_2` (E2E Feature Coverage & Scenario Explorer)  
**Date**: 2026-10-17  
**Working Directory**: `D:\New folder\my-web-project\.agents\teamwork\explorer_e2e_2`  
**Target Runner**: `tests/run_tests.php` (executable via `D:\xam\php\php.exe tests/run_tests.php`)  
**Target Environment**: Live XAMPP Apache (`http://localhost/my-web-project/`) & Live MySQL/MariaDB 10.4 (`edu_radar` on `127.0.0.1:3306`)

---

## 1. Executive Summary & Architecture of the 4-Tier Test Matrix

The E2E test runner `tests/run_tests.php` is the definitive acceptance verification gate for the EduRadar THCS Gradebook & Early-Warning Web Application. To provide absolute confidence and complete failure isolation, the test suite is structured into **4 sequential, fail-fast tiers**:

```
+---------------------------------------------------------------------------------------------------+
| TIER 1: Environment, Schema & Static Health (Pre-flight & Baselines)                              |
| -> PHP 8.0, Apache port 80, MySQL port 3306, Pure MySQL (0 SQLite), 5 UNIQUE Keys, Zero Zalo       |
+---------------------------------------------------------------------------------------------------+
                                                  | PASS
                                                  v
+---------------------------------------------------------------------------------------------------+
| TIER 2: Multi-Account Authentication, Session Security & Strict RBAC Route Guards                 |
| -> admin, gv_toan, gv_van logins, session rotation, 403 route guards, anti-BOLA/IDOR protection   |
+---------------------------------------------------------------------------------------------------+
                                                  | PASS
                                                  v
+---------------------------------------------------------------------------------------------------+
| TIER 3: Core Functional & Business Workflows (State Changes & Calculations)                       |
| -> Class matrix, batch assignment, student import, gradebook spreadsheet, formula, OpenXML, radar|
+---------------------------------------------------------------------------------------------------+
                                                  | PASS
                                                  v
+---------------------------------------------------------------------------------------------------+
| TIER 4: Deduplication, Anti-Tampering & End-to-End Forensic Audit Integrity                       |
| -> Re-import idempotency, foreign student ID injection, out-of-bounds bounds, audit log forensics|
+---------------------------------------------------------------------------------------------------+
```

### Key Design Pillars:
1. **100% Anti-Facade & Authentic Execution**: Every test executes genuine HTTP requests via cURL against live Apache and genuine PDO SQL queries against MySQL `edu_radar`. No mocks, no stubs, no simulated in-memory sessions.
2. **Fail-Fast Tier Gating**: If Tier 1 fails (e.g. database offline or missing tables), the runner aborts early with clear diagnostic instructions rather than cascading hundreds of downstream failures.
3. **Non-Destructive & Idempotent**: Uses isolated test fixtures (e.g., test classes like `6D1_TEST`, test teacher `gv_test_runner`, isolated student rosters) and cleans up gracefully, preserving baseline production seeds.
4. **Zero PHP Warning/Notice Enforcement**: Every live HTTP response is audited against 15 PHP error patterns (`Notice:`, `Warning:`, `Fatal error:`, `Deprecated:`). Any PHP error in response HTML fails the test immediately.

---

## 2. Traceability Matrix: Mapping All 18 Features to Test Cases

| # | Feature Name | Source Req | Milestone | Primary Tier | Specific Test Cases | Verification Target |
|---|---|---|---|---|---|---|
| **1** | SQLite Elimination | R1, Survey | M1 | **Tier 1** | T1.02 | `database.sqlite` deleted; `includes/config.php` & `includes/db.php` have zero SQLite code/DSN. |
| **2** | MySQL Schema Overhaul & Deduplication | R1, R5 | M1 | **Tier 1, Tier 4** | T1.03, T4.01, T4.02, T4.03, T4.04 | 5 `UNIQUE` constraints (`unique_username`, `unique_class_name`, `unique_assignment`, `unique_class_student`, `unique_student_subject`) and Foreign Keys with `ON DELETE CASCADE`. |
| **3** | Audit Logging Table & Triggers | R5 | M1 | **Tier 1, Tier 4** | T1.03, T4.07 | Table `audit_logs` structure; `log_action()` helper; forensic proof that all tested state changes produce matching audit records. |
| **4** | Seed Data & Migration | R1, R5, Acceptance | M1 | **Tier 1** | T1.04 | 3 accounts (`admin`, `gv_toan`, `gv_van`) with Bcrypt hashes; 36 classes (6A1..9C9); initial teacher assignments and students in 6A1. |
| **5** | Authentication & Backdoor Removal | R1, Acceptance | M2 | **Tier 1, Tier 2** | T1.05, T2.01, T2.02, T2.03, T2.04 | Bypass `|| $password === '123456'` eliminated; `password_verify()` enforced; session fixation rotation `session_regenerate_id(true)`; secure logout. |
| **6** | Strict Server-Side RBAC & Route Guard | R1, Acceptance | M2 | **Tier 2, Tier 4** | T2.05, T2.06, T2.07, T2.08, T4.05 | Unauthenticated -> 302 login.php; Teacher on `admin.php` -> 403 Forbidden; Teacher on unassigned class/subject in `nhap_diem.php` -> 403 Forbidden; Anti-BOLA student whitelist. |
| **7** | Admin Class Matrix & Management | R2 | M3 | **Tier 3, Tier 4** | T3.01, T4.01 | Grade 6–9 matrix (Sections A–C, Classes 1–9); Admin creates class; Duplicate class name rejected; Admin deletes class. |
| **8** | Admin Batch Checkbox Assignment | R2 | M3 | **Tier 3, Tier 4** | T3.02, T3.03, T4.02 | Select teacher + subject, tick checkboxes across matrix; batch insert into `teacher_classes`; re-submit identical batch results in 0 duplicates; create teacher; unassign. |
| **9** | VnEdu Student List Import & Sample File | R2, Acceptance | M3 | **Tier 3, Tier 4** | T3.04, T4.03 | Admin imports `.xlsx` / `.csv` roster; header auto-detection; date normalization (DD/MM/YYYY & Excel serial); deduplication on `(class_id, full_name, birthday)`. |
| **10** | Admin Direct Gradebook Inspection | R2 | M3 | **Tier 3** | T3.05 | Impersonation link (`admin.php?impersonate=X&cid=Y&sub=Z`) opens teacher's gradebook in `nhap_diem.php` with admin inspection privileges; audit logged. |
| **11** | Admin Audit Log Viewer | R2 | M3 | **Tier 3** | T3.05 | Real-time audit log table in `admin.php` rendering recent system actions. |
| **12** | VnEdu Gradebook Spreadsheet Table | R3 | M4 | **Tier 3, Tier 4** | T3.06, T3.08, T4.05, T4.06 | 11-column spreadsheet table, 2-tier header (`ĐĐGtx1..4, ĐĐGgk, ĐĐGck, ĐTBmhk`); 2D keyboard navigation (`data-row`, `data-col`, arrow keys); inline input validation. |
| **13** | Score Calculation Engine | R3 | M4 | **Tier 3** | T3.07, T3.08 | Circular 22 weighted average: `(S_tx + 2*gk + 3*ck)/(N_tx + 5)`; partial weighting for missing periodic tests; rounding to 1 decimal place. |
| **14** | VnEdu Gradebook `.xlsx` Import/Export | R3, Acceptance | M4 | **Tier 3, Tier 4** | T3.09, T3.10, T4.04 | Import `.xlsx` gradebook file; upsert `scores` without duplicates; Export `.xlsx` via `nhap_diem.php?export=xlsx` generates valid OpenXML ZIP readable by `ZipArchive`. |
| **15** | Early-Warning Radar Diagnostic Engine | R4 | M5 | **Tier 3** | T3.11 | Classification into 🔴 Nguy cấp, 🟡 Cần chú ý, 🟢 Ổn định via `includes/radar_helper.php`; diagnostic reasons; summary counters and percentage metrics (safe division by 0). |
| **16** | Zalo Removal & Dead Code Elimination | R4, R6 | M5 | **Tier 1, Tier 3** | T1.06, T3.12 | 0 occurrences of 'zalo', 0 Zalo buttons, 0 modal popups (`actionModal`), 0 message generator scripts in HTML/JS. |
| **17** | SaaS UI Redesign (Stitch UI Style) | R6 | M5 | **Tier 1, Tier 3** | T1.06, T3.12 | Stitch UI styling: font `Plus Jakarta Sans`, cohesive color tokens, distinct role badges (`ADMIN`, `GIÁO VIÊN`), responsive navigation. |
| **18** | Automated Multi-Account CLI Test Suite | Acceptance Criteria | E2E | **All Tiers** | T1.01–T4.09 | The comprehensive CLI runner `tests/run_tests.php` executing all test cases with 100% pass rate, exit code 0, and 0 failures. |

---

## 3. Detailed Specification of the 4 Tiers

### TIER 1: Environment, Schema & Static Health (Sanity / Pre-flight)

**Goal**: Validate environment readiness, pure MySQL architecture, database schema, constraints, baseline seeds, and absence of deprecated code before issuing dynamic requests.

| Test ID | Test Name | Description & Preconditions | Execution & Verification | Expected Outcome |
|---|---|---|---|---|
| **T1.01** | `test_environment_sanity` | Verify PHP version >= 8.0, extensions (`curl`, `pdo_mysql`, `zip`, `simplexml`, `mbstring`) and Apache availability (`http://localhost/my-web-project/`). | Check `PHP_VERSION_ID >= 80000`, extension loaded checks, cURL GET to `BASE_URL`. | HTTP 200/302 from Apache; all required PHP extensions loaded. |
| **T1.02** | `test_pure_mysql_zero_sqlite` | Verify total absence of SQLite in filesystem and codebase (Feature #1). | Check `file_exists('database.sqlite') === false`; regex scan `includes/config.php` and `includes/db.php` for `sqlite:` or `pdo_sqlite`. | 0 SQLite files; 0 SQLite code references; PDO MySQL DSN confirmed. |
| **T1.03** | `test_mysql_schema_and_constraints` | Verify existence of all 6 tables, 5 `UNIQUE` constraints, and Foreign Keys in `edu_radar` (Features #2, #3). | Query `information_schema.TABLES`, `information_schema.TABLE_CONSTRAINTS`, `KEY_COLUMN_USAGE`. | Tables: `users, classes, teacher_classes, students, scores, audit_logs`. Constraints: `unique_username`, `unique_class_name`, `unique_assignment`, `unique_class_student`, `unique_student_subject`. FKs with CASCADE confirmed. |
| **T1.04** | `test_baseline_seed_integrity` | Verify baseline seed records in MySQL (Feature #4). | Query MySQL `users` for `admin`, `gv_toan`, `gv_van`; verify `classes` count >= 36; verify `teacher_classes` has baseline rows; verify `students` in 6A1. | 3 accounts exist with Bcrypt hash `$2y$10$...`; 36 classes (6A1..9C9); gv_toan assigned 6A1 & 7A1 Toán; gv_van assigned 6A1 Văn. |
| **T1.05** | `test_static_code_security_audit` | Verify absence of password bypass backdoor in `login.php` and presence of secure helpers (Feature #5). | Scan `login.php` for `123456` bypass strings; check `includes/auth.php` exports `require_login()`, `require_admin()`, `verify_teacher_assignment()`, `require_teacher_assignment()`. | Backdoor string absent; auth functions present and callable. |
| **T1.06** | `test_static_zero_zalo_and_dead_code` | Verify 0 occurrences of 'zalo', 0 'actionModal', 0 'copy-btn' across application code (Features #16, #17). | Grep scan across `index.php`, `admin.php`, `nhap_diem.php`, `login.php`, `logout.php`, `includes/*`, `assets/*` (excluding tests & docs). | Exactly 0 occurrences of 'zalo', 'actionModal', 'copy-btn'. |

---

### TIER 2: Multi-Account Authentication, Session Security & Strict RBAC Route Guards

**Goal**: Thoroughly verify authentication across roles (`admin`, `gv_toan`, `gv_van`), credential validation, session fixation mitigation, logout, and strict server-side access controls (RBAC, BOLA/IDOR prevention).

| Test ID | Test Name | Target Endpoint & Method | Input Payload / Headers | Expected HTTP Response | Expected Database & Audit Assertion |
|---|---|---|---|---|---|
| **T2.01** | `test_admin_auth_success` | POST `login.php` | `username=admin&password=123456` | HTTP 302; `Location: admin.php`; `Set-Cookie: PHPSESSID=...` | `audit_logs` has row: `action='Đăng nhập thành công'`, `username='admin'`. Session role is `'admin'`. |
| **T2.02** | `test_teacher_gv_toan_auth_success` | POST `login.php` | `username=gv_toan&password=123456` | HTTP 302; `Location: index.php`; `Set-Cookie: PHPSESSID=...` | `audit_logs` has row: `action='Đăng nhập thành công'`, `username='gv_toan'`. Session role is `'teacher'`. |
| **T2.03** | `test_teacher_gv_van_auth_success` | POST `login.php` | `username=gv_van&password=123456` | HTTP 302; `Location: index.php`; `Set-Cookie: PHPSESSID=...` | `audit_logs` has row: `action='Đăng nhập thành công'`, `username='gv_van'`. Session role is `'teacher'`. |
| **T2.04** | `test_wrong_password_rejection` | POST `login.php` | `username=admin&password=1234567` | HTTP 200; Response body contains `'Tên đăng nhập hoặc mật khẩu không chính xác!'` | `audit_logs` has row: `action='Đăng nhập thất bại'`, `details LIKE '%admin%'`. Zero session created. |
| **T2.05** | `test_sqli_login_rejection` | POST `login.php` | `username=admin' OR '1'='1&password=123456` | HTTP 200; Response body contains error alert; no crash. | `audit_logs` records failed attempt. Prepared statements prevent auth bypass. |
| **T2.06** | `test_empty_credentials_rejection` | POST `login.php` | `username=&password=` | HTTP 200; Response body contains `'Vui lòng nhập đầy đủ thông tin!'` | No audit log insert or benign failed notice; zero session created. |
| **T2.07** | `test_session_rotation_on_login` | GET `login.php` then POST `login.php` | `username=admin&password=123456` | Cookie PHPSESSID before login != PHPSESSID after login (`session_regenerate_id(true)`). | Mitigates Session Fixation attacks. Old session ID destroyed. |
| **T2.08** | `test_secure_logout_workflow` | GET `logout.php` (with valid session cookie) | Authenticated session cookie | HTTP 302; `Location: login.php`; Session destroyed; Cookie expired with negative time. | `audit_logs` has row: `action='Đăng xuất'`, `username='admin'`. Subsequent access to `admin.php` returns HTTP 302 to `login.php`. |
| **T2.09** | `test_unauthenticated_route_guards` | GET `admin.php`, `nhap_diem.php`, `index.php` (no cookies) | No Cookie header | HTTP 302; `Location: login.php` for all protected routes. | Zero access granted to unauthenticated users; zero database modifications. |
| **T2.10** | `test_teacher_access_admin_forbidden` | GET & POST `admin.php` (cookie: `gv_toan`) | Teacher session cookie | HTTP 403 Forbidden; Response renders Stitch UI error card: `'403 Forbidden – Truy Cập Bị Từ Chối'`. | `audit_logs` has row: `action='Truy cập trái phép bị chặn'`, `username='gv_toan'`, `details LIKE '%admin.php%'`. |
| **T2.11** | `test_teacher_unassigned_class_forbidden` | GET `nhap_diem.php?class_id=10&subject=Ngữ+Văn` (cookie: `gv_toan`) | `gv_toan` teaches Toán, accessing Ngữ Văn in 7A1 | HTTP 403 Forbidden; Response renders Stitch UI error card. | `audit_logs` records `Truy cập trái phép bị chặn` for `gv_toan` accessing unassigned subject/class. |
| **T2.12** | `test_teacher_unassigned_post_forbidden` | POST `nhap_diem.php?class_id=10&subject=Ngữ+Văn` (cookie: `gv_toan`) | `action=save_scores&scores[...]` | HTTP 403 Forbidden; zero changes to `scores` table. | Anti-BOLA route guard halts POST immediately before database touch. |
| **T2.13** | `test_teacher_spoof_teacher_id_forbidden` | GET `nhap_diem.php?teacher_id=1&class_id=1&subject=Toán` (cookie: `gv_toan`) | `gv_toan` (ID 2) supplying `teacher_id=1` (admin) | HTTP 403 Forbidden; Response renders error card. | `audit_logs` records `Truy cập trái phép bị chặn`: `'cố tình giả mạo tham số teacher_id=1'`. |
| **T2.14** | `test_admin_gradebook_inspection_allowed` | GET `nhap_diem.php?teacher_id=2&class_id=1&subject=Toán` (cookie: `admin`) | Admin session cookie | HTTP 200 OK; Response renders gradebook with banner `'Admin đang kiểm tra'`. | `audit_logs` records `Admin kiểm tra sổ điểm` for class 6A1 Toán. Full access granted. |

---

### TIER 3: Core Functional & Business Workflows (State Changes & Calculations)

**Goal**: Verify all primary end-user business workflows across Admin, Teacher Gradebook, OpenXML Import/Export, and Early-Warning Radar.

| Test ID | Test Name | Feature | Request Spec (Method, URL, Payload) | Assertions (HTTP & DB) | Audit Log Assertion |
|---|---|---|---|---|---|
| **T3.01** | `test_admin_class_crud_workflow` | F7 | 1. GET `admin.php` (verify 36-class matrix 6A1..9C9)<br>2. POST `admin.php` `action=add_class&grade=6&section=D&custom_name=6D1_TEST`<br>3. Verify in DB<br>4. GET `admin.php?delete_class=<id>` | HTTP 200 for matrix; Class `6D1_TEST` inserted into `classes`; then successfully deleted. | Rows logged: `action='Tạo lớp học'` and `action='Xóa lớp học'`. |
| **T3.02** | `test_admin_batch_assign_workflow` | F8 | POST `admin.php` `action=batch_assign&teacher_id=2&subject=Tin+Học&class_ids[]=2&class_ids[]=3` | HTTP 200; `teacher_classes` has 2 new rows for teacher 2 (Tin Học in 6A2, 6A3). | `audit_logs` has row: `action='Phân công giáo viên'`, `details LIKE '%Tin Học%'`. |
| **T3.03** | `test_admin_create_teacher_workflow` | F8 | POST `admin.php` `action=create_teacher&username=gv_test_t3&full_name=Thầy+Kiểm+Thử&password=123456` | HTTP 200; `users` has row `gv_test_t3` with role `'teacher'`; `password_verify('123456', hash)` is true. | `audit_logs` has row: `action='Tạo tài khoản giáo viên'`. |
| **T3.04** | `test_admin_import_student_roster_xlsx` | F9 | POST multipart `admin.php` `action=import_students&target_class_id=2&student_file=<sample_students.xlsx>` | HTTP 200/302; Students inserted into `students` table for class 2 (6A2). Dates normalized (`YYYY-MM-DD`). | `audit_logs` has row: `action='Import học sinh'`, `details LIKE '%6A2%'`. |
| **T3.05** | `test_admin_gradebook_inspect_and_audit_view` | F10, F11 | 1. GET `admin.php?impersonate=2&cid=1&sub=Toán`<br>2. GET `admin.php` (Audit viewer section) | 1. HTTP 302 redirect to `nhap_diem.php`<br>2. Audit table in `admin.php` displays recent actions. | `audit_logs` records `Admin giám sát sổ điểm`. |
| **T3.06** | `test_gradebook_spreadsheet_dom_and_navigation` | F12 | GET `nhap_diem.php?class_id=1&subject=Toán` (cookie: `gv_toan`) | HTTP 200; Contains 11 columns, 2-tier header (`ĐĐGtx1..4, ĐĐGgk, ĐĐGck, ĐTBmhk`); inputs have `class="score-input"`, `data-row`, `data-col`, `min="0"`, `max="10"`, `inputmode="decimal"`. | Zero PHP errors. Clean DOM rendering. |
| **T3.07** | `test_score_calculation_engine_invariants` | F13 | Calculation test against `nhap_diem.php` / formula: `(S_tx + 2*gk + 3*ck)/(N_tx + 5)` | 1. All present: tx=[8,8,8,8], gk=8, ck=8 -> 8.0.<br>2. Weighted: tx=[8.5, 8.0, 7.5, 8.0], gk=8.0, ck=8.5 -> 8.2.<br>3. Partial: tx=[7.0, 8.0], gk=7.0, ck=null -> (15 + 14)/4 = 7.3. | Formula correctly rounds to 1 decimal place. |
| **T3.08** | `test_teacher_save_scores_inline_post` | F12, F13 | POST `nhap_diem.php?class_id=1&subject=Toán` (cookie: `gv_toan`), payload `action=save_scores&scores[1][tx1]=8.5&scores[1][tx2]=8.0&scores[1][tx3]=7.5&scores[1][tx4]=8.0&scores[1][gk]=8.0&scores[1][ck]=8.5` | HTTP 200; Alert `'Đã lưu thành công'`. MySQL `scores` table has `tx1=8.5, tx2=8.0, tx3=7.5, tx4=8.0, gk=8.0, ck=8.5, avg_score=8.2`. | `audit_logs` has row: `action='Lưu sổ điểm'`, `details LIKE '%6A1%'`. |
| **T3.09** | `test_gradebook_bulk_xlsx_import` | F14 | POST multipart `nhap_diem.php?class_id=1&subject=Toán` (cookie: `gv_toan`), `action=import_file&gradebook_file=<valid_xlsx>` | HTTP 200; Notice `'thành công'`. MySQL `scores` table updated for class 1 students with accurate scores and `avg_score`. | `audit_logs` has row: `action='Import file điểm'`. |
| **T3.10** | `test_gradebook_openxml_export` | F14 | GET `nhap_diem.php?export=xlsx&class_id=1&subject=Toán` (cookie: `gv_toan`) | HTTP 200; `Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`; `Content-Disposition: attachment; filename=...`. Binary opens cleanly via `ZipArchive` with `xl/worksheets/sheet1.xml`. | `audit_logs` has row: `action='Xuất file điểm'`. |
| **T3.11** | `test_early_warning_radar_diagnostics` | F15 | GET `index.php` (cookie: `gv_toan` and cookie: `admin`) | HTTP 200; Students classified into 🔴 Nguy cấp, 🟡 Cần chú ý, 🟢 Ổn định. Diagnostic tags displayed. Summary counters match DB evaluation. | Verified against authentic `evaluateRiskZone()` in `includes/radar_helper.php`. |
| **T3.12** | `test_rendered_zero_zalo_and_stitch_ui` | F16, F17 | Live HTML inspection of `index.php`, `admin.php`, `nhap_diem.php` across all roles | HTML contains 0 'zalo', 0 'actionModal', 0 'copy-btn'; role badges `ADMIN` / `GIÁO VIÊN`; font `Plus Jakarta Sans`. | Zero dead scripts, clean modern UI. |

---

### TIER 4: Deduplication, Anti-Tampering & End-to-End Forensic Audit Integrity

**Goal**: Enforce strict data integrity guarantees: idempotency on re-submission, protection against BOLA/IDOR payload tampering, boundary value sanitization, and exhaustive audit log forensics.

| Test ID | Test Name | Attack / Integrity Scenario | Execution Method | Expected Result & Invariant |
|---|---|---|---|---|
| **T4.01** | `test_deduplication_class_creation` | Attempt creating duplicate class with name `6A1`. | POST `admin.php` `action=add_class&name=6A1&grade=6&section=A` | Rejected by `UNIQUE(name)` pre-check or exception catch; `classes` count for `6A1` remains exactly 1; error message displayed. |
| **T4.02** | `test_deduplication_batch_assignment` | Submitting identical batch assignment for teacher 2 and class 1 Toán twice. | POST `admin.php` `action=batch_assign&teacher_id=2&subject=Toán&class_ids[]=1` | Second submission inserts 0 new rows; reports skipped count; `teacher_classes` count for `(2, 1, 'Toán')` remains exactly 1. |
| **T4.03** | `test_deduplication_student_roster_reimport` | Uploading the exact same student roster `.xlsx` file twice into class 6A2. | POST multipart `admin.php` `action=import_students&target_class_id=2&student_file=<file>` twice | Second import results in exactly 0 new student rows inserted; UI reports skipped duplicate count; `UNIQUE(class_id, full_name, birthday)` enforced. |
| **T4.04** | `test_deduplication_gradebook_score_upsert` | Uploading the exact same gradebook `.xlsx` file twice into class 6A1 Toán. | POST multipart `nhap_diem.php` `action=import_file` twice | `ON DUPLICATE KEY UPDATE` upserts scores; `scores` row count does not increase; exactly 1 score row per `(student_id, 'Toán')`. |
| **T4.05** | `test_anti_bola_cross_class_student_injection` | Attacker includes student ID 99999 (or student from class 2) in POST payload while editing class 1 gradebook. | POST `nhap_diem.php?class_id=1&subject=Toán` `action=save_scores&scores[99999][tx1]=10` | Whitelist check (`SELECT id FROM students WHERE class_id = ?`) ignores/filters foreign student ID; 0 rows inserted for student 99999. |
| **T4.06** | `test_score_boundary_sanitization` | Attacker inputs out-of-range scores (`-2.5`, `15.0`, `'abc'`) in `save_scores`. | POST `nhap_diem.php?class_id=1&subject=Toán` with invalid scores | Out-of-range and non-numeric values are sanitized to `NULL`; valid scores in the same row are preserved; DB stores `NULL`. |
| **T4.07** | `test_exhaustive_audit_log_forensics` | Verify that every state-changing action in the entire test run produced a corresponding row in MySQL `audit_logs`. | Query `audit_logs` table for all expected action types: `Đăng nhập thành công`, `Đăng nhập thất bại`, `Đăng xuất`, `Truy cập trái phép bị chặn`, `Tạo lớp học`, `Xóa lớp học`, `Tạo tài khoản giáo viên`, `Phân công giáo viên`, `Hủy phân công`, `Import học sinh`, `Admin kiểm tra sổ điểm`, `Lưu sổ điểm`, `Import file điểm`, `Xuất file điểm`. | Every action type has >= 1 valid row with non-null `username`, non-empty `details`, valid `ip_address` (127.0.0.1), and valid timestamp. |
| **T4.08** | `test_live_http_zero_php_errors_forensics` | Verify that across all HTTP responses captured during test execution, 0 PHP errors/warnings were emitted. | Audit all stored response HTML against: `Fatal error:`, `Warning:`, `Notice:`, `Deprecated:`, `Parse error:`, `Uncaught`, `Stack trace:`. | Exactly 0 occurrences of PHP errors across all GET/POST responses. |
| **T4.09** | `test_clean_state_and_exit_code` | Verify test fixtures are cleaned up, leaving database in pristine baseline state, and runner exits with code 0. | Check test classes/teachers removed; verify test summary counts: `Failed: 0`; script exits with `exit(0)`. | 100% tests pass; exit code 0. |

---

## 4. Test Fixture Management & Data Hygiene Strategy

To satisfy Acceptance Criteria without polluting the live database across multiple runs:

### 1. Dedicated Test Identifiers
- Any test entities created during test execution MUST use distinctive, deterministic prefixes:
  - Test classes: `6D1_TEST` or `TEST_CLASS_...`
  - Test teachers: `gv_test_t3` or `gv_test_runner`
  - Test students: `HS_TEST_01`, `Test Student Alpha`
  - Temporary files: `sys_get_temp_dir() . '/test_edu_...'`

### 2. Isolated Cookie Jars per Actor
- Instead of sharing a single cookie file, `tests/run_tests.php` maintains independent cookie jars:
  - `cookie_admin.txt` for Admin actions
  - `cookie_toan.txt` for `gv_toan` actions
  - `cookie_van.txt` for `gv_van` actions
  - `cookie_guest.txt` (or no cookie) for unauthenticated tests
- This completely prevents session bleed-over between tests.

### 3. Automatic Pre-flight & Post-flight Cleanup
```php
function cleanup_test_fixtures(PDO $pdo): void {
    // Clean up test classes (cascades to students and scores via FKs)
    $pdo->prepare("DELETE FROM classes WHERE name LIKE '%_TEST%'")->execute();
    // Clean up test teachers (cascades to teacher_classes via FKs)
    $pdo->prepare("DELETE FROM users WHERE username LIKE 'gv_test_%'")->execute();
    // Clean temporary OpenXML files
    array_map('unlink', glob(sys_get_temp_dir() . '/test_edu_*'));
}
```
`cleanup_test_fixtures()` runs both **before** the test run (clearing any leftover debris from prior aborted runs) and **after** completion.

---

## 5. Implementation Recommendations for `tests/run_tests.php`

1. **Self-Contained Executable**: Single standalone PHP CLI script `tests/run_tests.php` runnable directly via:
   ```cmd
   D:\xam\php\php.exe tests/run_tests.php
   ```
2. **ANSI Color Console Formatting**:
   - Headers: Cyan/Yellow
   - Pass: `[PASS]` in bold green (`\033[1;32m`)
   - Fail: `[FAIL]` in bold red (`\033[1;31m`)
   - Skip: `[SKIP]` in yellow (`\033[1;33m`)
3. **Structured Summary Report**:
   ```
   ======================================================================
     EDURADAR E2E ACCEPTANCE TEST SUITE — 4-TIER MATRIX EXECUTION
     Target: http://localhost/my-web-project/ | Database: edu_radar (MySQL)
   ======================================================================
   ...
   ======================================================================
     EXECUTION SUMMARY:
     Tier 1 (Environment & Schema):  6 Passed | 0 Failed
     Tier 2 (Auth & RBAC Guards):   14 Passed | 0 Failed
     Tier 3 (Functional Workflows): 12 Passed | 0 Failed
     Tier 4 (Deduplication & Audit): 9 Passed | 0 Failed
     ------------------------------------------------------------------
     TOTAL TESTS: 41 | PASSED: 41 | FAILED: 0 (100% SUCCESS)
     EXECUTION TIME: 3.42s
   ======================================================================
   ```
4. **Exit Code Semantics**:
   - All tests pass: `exit(0);`
   - Any test fails: `exit(1);`
