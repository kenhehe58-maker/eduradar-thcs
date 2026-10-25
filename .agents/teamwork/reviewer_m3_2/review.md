# Quality & Adversarial Review Report: Milestone 3

**Reviewer**: `reviewer_m3_2` (Roles: reviewer, critic)  
**Date**: 2026-10-17  
**Verdict**: **APPROVE**  
**Integrity Status**: **CLEAN (Zero Integrity Violations)**

---

## 1. Executive Summary

Milestone 3 implements the comprehensive Admin Portal (`admin.php`), Secondary School Class Matrix (Grades 6–9, Sections A–C, Classes 1–9), dynamic class management, batch checkbox teaching assignment with per-grade and global select-all capabilities, Bcrypt teacher account creation, zero-dependency OpenXML (`.xlsx`) & CSV helper (`includes/xlsx_helper.php`), VnEdu student roster import with dual-layer database deduplication, direct teacher gradebook inspection button linking to `nhap_diem.php`, real-time audit log viewer with dynamic limit selector and client-side live search, and top-level RBAC route guard security.

All implementation code, database constraints, and test suites were independently inspected, executed, and stress-tested against the live XAMPP Apache web server (`http://localhost/my-web-project/`) and live MariaDB database (`edu_radar`).

---

## 2. Integrity Verification (Adversarial Audit)

The adversarial review actively audited the codebase for the five forbidden integrity violation patterns:

| Integrity Check Dimension | Findings | Status |
|---------------------------|----------|:------:|
| **Hardcoded test results / expected outputs embedded in source** | Scanned `admin.php`, `includes/xlsx_helper.php`, `includes/auth.php` for test names, test users (`gv_test_m3`, `6C99_TEST`), or test mock returns. Found 0 test artifacts. All queries and logic are dynamic and production-grade. | **PASS (CLEAN)** |
| **Dummy or facade implementations** | Inspected `includes/xlsx_helper.php` for real OpenXML ZIP/XML building and parsing. Confirmed full ECMA-376 SpreadsheetML compliance (`[Content_Types].xml`, `workbook.xml`, `sharedStrings.xml`, `styles.xml`, `sheet1.xml`). Inspected database operations in `admin.php` for real transactions, constraints, and audit logging. | **PASS (CLEAN)** |
| **Shortcuts bypassing the intended task** | Zero external libraries or Composer packages used. Pure native PHP 8.0 `ZipArchive` and `SimpleXML` implementation. Zero SQLite fallbacks; strict MariaDB PDO. | **PASS (CLEAN)** |
| **Fabricated verification outputs or logs** | Tests were independently executed directly by this reviewer via `D:\xam\php\php.exe`. All test runs are live, repeatable, and communicate over real HTTP cURL sockets and PDO connections. | **PASS (CLEAN)** |
| **Self-certifying work without genuine independent verification** | Verified test suites make real HTTP requests to Apache, verify session cookies, query the database directly for mutations, and assert HTTP 403/200/302 response codes and exact row counts. | **PASS (CLEAN)** |

**Conclusion on Integrity**: No integrity violations detected. The implementation is authentic, rigorous, and completely grounded in real code and database operations.

---

## 3. Review Dimensions & Detailed Evaluation

### 3.1 Correctness & Specification Conformance

1. **Class Matrix (Grades 6–9) & Dynamic Class Management (Feature #7, R2)**:
   - Renders 36 classes grouped by Grade (6, 7, 8, 9) with section tags, student count badges (`[X HS]`), and cascading delete buttons.
   - Dynamic class creation (`create_class` / `add_class`) validates class format with regex `/^[6-9][A-Z0-9_]{1,20}$/i`, checks `UNIQUE(name)` in `classes` to prevent duplicates, and logs every creation and deletion to `audit_logs`.
   - Verified by `tests/verify_m3.php` tests 2.1, 2.2, 2.3, 2.4.

2. **Batch Checkbox Assignment with Per-Grade Select-All (Feature #8, R2)**:
   - Form presents teacher dropdown, GDPT 2018 subject selection (all 11 subjects), and an interactive matrix of class checkboxes grouped by grade.
   - Includes per-grade toggle buttons (`selectGrade(grade, true/false)`) and global toggle buttons (`selectAllGrades(true/false)`), along with real-time dynamic counter `Đã chọn: X / Y lớp`.
   - Backend `batch_assign` executes in a database transaction, checks uniqueness per `(teacher_id, class_id, subject)`, inserts new assignments, skips duplicates cleanly without error, reports metrics to the UI, and logs complete details with class names to `audit_logs`.
   - Verified by `tests/verify_m3.php` tests 3.3, 3.4.

3. **Teacher Account Creation with Bcrypt (Feature #8, R1, R2)**:
   - Form creates teacher accounts, validates username regex `/^[a-zA-Z0-9_]{3,30}$/`, enforces minimum password length (6 characters), generates password hash via `password_hash($password, PASSWORD_BCRYPT)`, sets role `'teacher'`, and logs creation to `audit_logs`.
   - Prevents duplicate usernames via pre-check query and MariaDB `UNIQUE(username)` key.
   - Verified by `tests/verify_m3.php` tests 3.1, 3.2.

4. **VnEdu Student Roster Import & Deduplication (Feature #9, R2)**:
   - Accepts both `.xlsx` (via `parse_xlsx()`) and `.csv` (via `parse_csv()`).
   - Uses `detect_student_roster_headers()` to scan rows 0–4 for keyword matching (`họ và tên`, `ngày sinh`, `stt`, `mã hs`, `giới tính`, `sđt`), making imports resilient to arbitrary school headers and titles.
   - Maps to correct database column `phone` (not `parent_phone`), resolving SQLSTATE 42S22.
   - Date normalization parses Vietnamese formats (`DD/MM/YYYY`, `DD-MM-YYYY`), ISO formats, and Excel date serials (e.g. `42075` -> `2015-03-12`), using `'1970-01-01'` fallback sentinel to prevent MySQL NULL bypass on `UNIQUE(class_id, full_name, birthday)`.
   - Dual-layer deduplication checks pre-existing record and catches PDOException error 1062, ensuring that importing the same file twice produces exactly 0 duplicate students.
   - Downloadable sample files `sample_students.xlsx` and `sample_students.csv` are accessible at project root.
   - Verified by `tests/verify_m3.php` tests 4.1, 4.2, 4.3, 4.4, 4.5, 4.6.

5. **Direct Gradebook Inspection (Feature #10, R2)**:
   - In the assignments table, each row features a direct inspection button:
     `<a href="admin.php?impersonate=...&sub=...&cid=...">🔍 Mở Bảng Điểm Môn X</a>`.
   - Action `impersonate` logs "Admin giám sát sổ điểm" to `audit_logs` and redirects to `nhap_diem.php?teacher_id=...&subject=...&class_id=...`.
   - `nhap_diem.php` detects `admin` role and enables direct inspection with an "Admin đang kiểm tra" banner while strictly blocking non-admin teachers from tampering with `?teacher_id`.
   - Verified by `tests/verify_m3.php` test 5.1 and `challenge_m2_rbac.php` test 3.4, 3.5.

6. **Real-Time Audit Log Viewer (Feature #11, R2, R5)**:
   - Displays latest system actions from `audit_logs` table with timestamp, username, color-coded action badge (green for success/additions, blue for assignments/inspections, yellow for unassigns, red for deletions/blocks), action details, and IP address.
   - Supports dynamic limit selector: 10, 25, 50, 100 rows via `$_GET['log_limit']`.
   - Client-side live search filter (`filterAuditLogs()`) dynamically filters visible table rows across all columns in real time.
   - Verified by `tests/verify_m3.php` tests 6.1, 6.2, 6.3, 6.4, 6.5.

7. **Strict Route Guard & RBAC Security (Feature #6, R1, R2)**:
   - `require_admin()` is placed at line 20 of `admin.php` (before any action processing) and reiterated inside individual action blocks.
   - Unauthenticated users and teachers attempting to access `admin.php` via GET or forged POST requests receive HTTP 403 / redirect, produce 0 database changes, and have their attempt logged in `audit_logs`.
   - Verified by `tests/verify_m3.php` tests 5.2, 5.3 and `challenge_m2_rbac.php` Section 1 (12/12 attacks blocked).

---

## 4. Adversarial Stress-Testing & Edge Cases

| Scenario / Attack Vector | Predicted Risk | Observed System Behavior | Assessment |
|--------------------------|----------------|--------------------------|:----------:|
| **Unauthenticated forged POST to `admin.php` (`action=create_teacher`)** | Privilege escalation | HTTP 403 returned, 0 users added to MySQL, blocked attempt recorded in `audit_logs` | **SECURE** |
| **Teacher (`gv_toan`) forged POST to `admin.php` (`action=batch_assign`)** | Unauthorized assignment | HTTP 403 returned, 0 rows added to `teacher_classes`, blocked attempt recorded in `audit_logs` | **SECURE** |
| **SQLi / XSS in Class Creation (`name="6A1<script>"`)** | Stored XSS / Injection | Rejected by regex `/^[6-9][A-Z0-9_]{1,20}$/i` and PDO prepared statements | **SECURE** |
| **Duplicate Class Creation** | Data duplication | Rejected by pre-check and `UNIQUE(name)` constraint | **SECURE** |
| **Duplicate Batch Assignment** | Duplicate teaching rights | Checked and skipped gracefully, 0 duplicate rows created | **SECURE** |
| **Roster File with Header on Row 3** | Import failure | `detect_student_roster_headers()` successfully scans rows 0–4 and parses headers correctly | **ROBUST** |
| **Roster with Vietnamese Dates (`DD/MM/YYYY`) and Excel Serial Dates (`42075`)** | Malformed date in DB | `normalize_excel_date()` normalizes all formats to `YYYY-MM-DD` | **ACCURATE** |
| **Repeated Roster Import** | Student duplication | Dual-layer deduplication results in exactly 0 new rows inserted, UI reports skipped duplicate count | **IDEMPOTENT** |
| **Direct Gradebook Tampering by Teacher (`nhap_diem.php?teacher_id=3`)** | IDOR / BOLA | HTTP 403 returned, logged to `audit_logs`, peer gradebook access denied | **SECURE** |

---

## 5. Independent Test Execution Results

All test suites were executed independently via `D:\xam\php\php.exe`:

| Test Suite | File Path | Total Tests | Passed | Failed | Status |
|------------|-----------|:-----------:|:------:|:------:|:------:|
| **M3 Automated Verification** | `tests/verify_m3.php` | 28 | 28 | 0 | **PASS (100%)** |
| **M2 Auth & RBAC Verification** | `tests/verify_m2.php` | 16 | 16 | 0 | **PASS (100%)** |
| **M2 RBAC Adversarial Challenge** | `tests/challenge_m2_rbac.php` | 37 | 37 | 0 | **PASS (100%)** |

All tests completed with Exit Code `0`. Zero regressions detected.

---

## 6. Findings & Observations

### Minor / Informational Findings

1. **Batch Assignment Foreign Key Catch (Minor)**:
   - *Where*: `admin.php` lines 230–245 (`batch_assign`).
   - *Observation*: Class IDs in batch assign are cast to integers (`(int)$cid`). In ordinary usage, IDs come from the database-populated checkboxes. If an attacker tampered with POST data submitting a non-existent class ID (e.g. `999999`), the foreign key constraint `fk_tc_class` would throw a PDOException.
   - *Recommendation*: Wrapping the loop in a try/catch block inside the transaction to catch foreign key exceptions gracefully is a nice-to-have improvement for defense-in-depth, though currently invalid IDs cannot be selected from the UI and route protection blocks untrusted users.

2. **GET Parameters on Deletion Endpoints (Minor / Backward Compatibility)**:
   - *Where*: `admin.php` lines 138–165 (`delete_class`) and lines 265–295 (`delete_assign`).
   - *Observation*: Supports both `$_GET` and `$_POST` for deletion to maintain backward compatibility with existing test suites. Top-level `require_admin()` at line 20 strictly restricts access to admin sessions.
   - *Recommendation*: In future production hardening, requiring POST with CSRF tokens for state-modifying deletion requests is standard best practice.

---

## 7. Verdict

**VERDICT: APPROVE**

Milestone 3 successfully satisfies all user requirements (R1, R2, R5) and project specifications (Features #7, #8, #9, #10, #11) with zero regressions, complete RBAC enforcement, zero-dependency OpenXML capability, robust deduplication, and zero integrity violations.
