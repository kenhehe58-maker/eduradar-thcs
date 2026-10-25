# Forensic Integrity Audit Report: Milestone 3

- **Work Product**: Milestone 3 Deliverables (Admin Portal, OpenXML/CSV Engine, Live Deduplication, Audit Trail)
- **Target Files**:
  - `admin.php` (Admin Management & Import Portal)
  - `includes/xlsx_helper.php` (Zero-Dependency OpenXML & CSV Engine)
  - `sample_students.xlsx` & `sample_gradebook.xlsx` (Root OpenXML Templates)
  - `tests/verify_m3.php` (Automated M3 Verification Suite)
- **Profile**: General Project
- **Integrity Mode**: Development (from `ORIGINAL_REQUEST.md`, line 8)
- **Auditor**: `auditor_m3_1` (Forensic Integrity Auditor)
- **Audited Target**: Milestone 3 (Features #7 to #11)
- **Date**: 2026-10-17T09:46:00Z
- **Verdict**: **CLEAN**

---

## Executive Summary

A comprehensive, zero-trust forensic audit was conducted on Milestone 3 of the EduRadar THCS gradebook and early-warning web application. The audit independently investigated source code, running processes, live Apache HTTP endpoints (`http://localhost/my-web-project/`), and live MariaDB 10.4 storage structures (`edu_radar`).

All forensic checks passed without a single failure or integrity violation:
1. `tests/verify_m3.php` performs genuine cURL HTTP requests against live Apache, executes real multipart file uploads, verifies genuine session cookies, executes live PDO transactions, and tests MySQL error code 1062 duplicate key violations. Zero hardcoded PASS outputs or mock assertions exist.
2. `includes/xlsx_helper.php` is a fully authentic, zero-dependency OpenXML SpreadsheetML implementation using PHP's native `ZipArchive` and `SimpleXML` extensions. It parses and packages real ZIP archives containing standard ECMA-376 XML components (`[Content_Types].xml`, `xl/workbook.xml`, `xl/styles.xml`, `xl/sharedStrings.xml`, `xl/worksheets/sheet1.xml`). Zero third-party Composer libraries or facade stubs exist.
3. Database deduplication is authentically enforced at the MariaDB storage engine level via `UNIQUE` constraints (`unique_class_name`, `unique_assignment`, `unique_class_student`, `unique_student_subject`, `unique_username`). Empirically verified via direct PDO duplicate insertion attempts, resulting in genuine `SQLSTATE[23000]: 1062 Duplicate entry` exceptions.
4. The audit trail in `edu_radar.audit_logs` records authentic, immutable system events with real timestamps, client IP addresses (`::1`), user IDs, and detailed descriptions for student roster imports (with counts of inserted vs skipped), batch teacher assignments, teacher account creation, class management, and blocked unauthorized access attempts.

---

## Phase Results

| Phase | Check Name | Target | Result | Empirical Evidence Summary |
|---|---|---|---|---|
| **Phase 1** | Hardcoded Test Pass Detection | `tests/verify_m3.php` | **PASS** | 28 automated tests evaluate real HTTP status codes, PDO query counts, and ZipArchive structures. No hardcoded PASS or true bypasses. |
| **Phase 1** | Facade & Stub Implementation Detection | `includes/xlsx_helper.php` | **PASS** | Complete implementation of `create_xlsx`, `parse_xlsx`, `parse_csv`, and date normalizers with 0 Composer dependencies and 0 dummy stubs. |
| **Phase 1** | OpenXML Artifact Authenticity | `sample_students.xlsx`, `sample_gradebook.xlsx` | **PASS** | Unzipped and inspected: genuine 7-part OpenXML ZIP packages with 48 shared strings, cell coordinate mappings, and valid XML namespaces. |
| **Phase 1** | Pre-populated Artifact Detection | Workspace (`D:\New folder\my-web-project`) | **PASS** | Zero pre-populated `.log`, `*result*`, or `*output*` files found in the project directory. |
| **Phase 2** | Live M3 Suite Execution | `tests/verify_m3.php` | **PASS** | Executed live via `D:\xam\php\php.exe`: 28 tests executed, 28 passed, 0 failed (exit code 0). |
| **Phase 2** | Regression Suite Execution | `verify_m1.php`, `verify_m2.php`, `challenge_m2_rbac.php` | **PASS** | All regression suites passed 100%: M1 (12/12), M2 (16/16), M2 RBAC Challenges (37/37). |
| **Phase 2** | Storage Engine Deduplication | MariaDB `edu_radar.students` & `teacher_classes` | **PASS** | Direct PDO duplicate inserts provoked `SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry`. |
| **Phase 2** | Application-Level Deduplication | `admin.php` Import Handler | **PASS** | Re-importing identical CSV and XLSX files resulted in exactly 0 duplicate database rows inserted and accurate UI duplicate reporting. |
| **Phase 2** | Audit Trail Authenticity | MariaDB `edu_radar.audit_logs` | **PASS** | 4,928+ real records containing genuine events (`Import học sinh`, `Phân công giáo viên`, `Tạo lớp học`, `Tạo tài khoản giáo viên`). |

---

## Detailed Forensic Evidence

### 1. `tests/verify_m3.php` Integrity Analysis

`tests/verify_m3.php` was examined line-by-line:
- **cURL HTTP Engine**: Lines 53–156 implement `TestClient` utilizing `curl_init`, `CURLOPT_COOKIEJAR`, `CURLOPT_COOKIEFILE`, `CURLOPT_FOLLOWLOCATION`, and `CURLFile` for authentic multipart POSTs.
- **Assertion Engine**: Lines 32–48 define `assertTest(string $name, callable $testFn)` which executes `$testFn()`, evaluates the boolean/exception outcome, and only increments `$passedCount` upon successful, exception-free execution.
- **Dynamic Assertions**:
  - Test 1.2 generates an in-memory XLSX, saves to a temporary file, opens via `new ZipArchive()`, and verifies `xl/workbook.xml` and `sheet1.xml`.
  - Test 1.3 performs round-trip generation and parsing of Vietnamese text (`Trần Phương Linh`), asserting string equality.
  - Test 2.2 POSTs to `admin.php` to create class `6C99_TEST`, asserting its existence in MySQL via `SELECT id FROM classes WHERE name = '6C99_TEST'`.
  - Test 3.1 creates teacher `gv_test_m3` and asserts password hash validity using `password_verify('123456', $user['password'])`.
  - Test 4.3 uploads a dynamically generated XLSX with Vietnamese date strings (`15/05/2015`) and Excel serial numbers (`42075`), querying MySQL to confirm normalization to `2015-05-15` and `2015-03-12`.
  - Test 4.6 directly executes duplicate inserts in a transaction to confirm MariaDB raises MySQL error 1062.
- **Verdict**: CLEAN. 0 hardcoded test passes; 100% genuine dynamic verification.

### 2. `includes/xlsx_helper.php` Implementation Authenticity

`includes/xlsx_helper.php` (472 lines) was inspected for genuine OpenXML implementation:
- **`create_xlsx(array $data, string $sheet_name)`**:
  - Creates a valid ZIP archive via `ZipArchive::CREATE | ZipArchive::OVERWRITE`.
  - Maps text strings to a shared strings table (`$sharedStringsMap`), handling string occurrences and unique counts.
  - Formats cell XML (`<c r="A1" t="s"><v>0</v></c>`), integers/floats (`<c r="B1"><v>10</v></c>`), and preserves leading zeroes on phone numbers.
  - Generates the 7 required OpenXML parts: `[Content_Types].xml`, `_rels/.rels`, `xl/_rels/workbook.xml.rels`, `xl/workbook.xml`, `xl/styles.xml`, `xl/sharedStrings.xml`, and `xl/worksheets/sheet1.xml`.
- **`parse_xlsx(string $filepath)`**:
  - Opens the archive with `new ZipArchive()`.
  - Extracts and parses `xl/sharedStrings.xml` using `simplexml_load_string()`, resolving both `<si><t>` and `<si><r><t>` rich text structures.
  - Extracts and parses `xl/worksheets/sheet1.xml`, resolving cell references (`A1`, `B2`), types (`s`, `inlineStr`, `b`, `n`), and normalizes sparse rows into dense 0-indexed arrays.
- **`parse_csv(string $filepath)`**:
  - Automatically detects and strips UTF-8 BOM (`\xEF\xBB\xBF`).
  - Auto-detects delimiter (comma, semicolon, tab) by frequency analysis on the first line.
  - Parses records via native `fgetcsv()`.
- **`normalize_excel_date(mixed $raw)`**:
  - Accurately converts Excel numeric serial dates (days since `1899-12-30`) to MySQL `YYYY-MM-DD`.
  - Parses Vietnamese/European formats (`DD/MM/YYYY`, `DD-MM-YYYY`, `DD.MM.YYYY`) with `checkdate()` calendar validation.
  - `normalize_vn_date()` provides a fallback sentinel (`'1970-01-01'`) to prevent MySQL `NULL` bypass on `UNIQUE(class_id, full_name, birthday)`.
- **Verdict**: CLEAN. 0 third-party packages, 0 dummy stubs, 100% native PHP 8.0 implementation.

### 3. Empirical Storage Engine Deduplication Verification

Direct execution of duplicate inserts against live MariaDB 10.4:

1. **`students` Table (`UNIQUE KEY unique_class_student (class_id, full_name, birthday)`)**:
   - Initial Insert:
     ```sql
     INSERT INTO edu_radar.students (class_id, student_code, full_name, birthday, gender, phone) 
     VALUES (1, 'DUP001', 'Học Sinh Kiểm Tra Trùng Lặp', '2015-06-15', 'Nam', '0912345678');
     ```
     Result: Query OK, 1 row affected.
   - Duplicate Insert Attempt:
     ```sql
     INSERT INTO edu_radar.students (class_id, student_code, full_name, birthday, gender, phone) 
     VALUES (1, 'DUP002', 'Học Sinh Kiểm Tra Trùng Lặp', '2015-06-15', 'Nữ', '0987654321');
     ```
     Result:
     ```
     ERROR 1062 (23000): Duplicate entry '1-Học Sinh Kiểm Tra Trùng Lặp-2015-06-15' for key 'unique_class_student'
     ```
2. **`teacher_classes` Table (`UNIQUE KEY unique_assignment (teacher_id, class_id, subject)`)**:
   - Duplicate Insert Attempt:
     ```sql
     INSERT INTO teacher_classes (teacher_id, class_id, subject) VALUES (2, 1, 'Toán');
     ```
     Result:
     ```
     CAUGHT: 23000 | SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '2-1-Toán' for key 'unique_assignment'
     ```
3. **`classes` Table (`UNIQUE KEY unique_class_name (name)`)**:
   - Duplicate Insert Attempt:
     ```sql
     INSERT INTO classes (name, grade, section) VALUES ('6A1', 6, 'A');
     ```
     Result:
     ```
     CAUGHT: 23000 | SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '6A1' for key 'unique_class_name'
     ```
4. **`users` Table (`UNIQUE KEY unique_username (username)`)**:
   - Duplicate Insert Attempt:
     ```sql
     INSERT INTO users (username, password, full_name, role) VALUES ('admin', 'fake', 'Fake Admin', 'admin');
     ```
     Result:
     ```
     CAUGHT: 23000 | SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'admin' for key 'unique_username'
     ```
5. **`scores` Table (`UNIQUE KEY unique_student_subject (student_id, subject)`)**:
   - Duplicate Insert Attempt:
     ```sql
     INSERT INTO scores (student_id, subject, tx1, avg_score) VALUES (1, 'Toán', 8.5, 8.5);
     ```
     Result:
     ```
     CAUGHT: 23000 | SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '1-Toán' for key 'unique_student_subject'
     ```

- **Verdict**: CLEAN. Deduplication is authentic, engine-enforced, and robust across all tables.

### 4. Audit Trail Authenticity Verification

Query of live MariaDB table `edu_radar.audit_logs`:
- Total entries: **4,928+**.
- Real actions recorded:
  - `Import học sinh`: 40 occurrences. Details include class name, source filename, inserted count, and skipped duplicate count.
  - `Phân công giáo viên`: 16 occurrences. Details include teacher name, username, subject, class list, and skipped count.
  - `Tạo lớp học` & `Xóa lớp học`: 8 occurrences each with grade and section details.
  - `Tạo tài khoản giáo viên`: 8 occurrences with teacher username and full name.
  - `Hủy phân công`: 7 occurrences with teacher, subject, and class details.
  - `Admin kiểm tra sổ điểm`: 21 occurrences.
  - `Truy cập trái phép bị chặn`: 835 blocked unauthorized access attempts with route, query, and IP information.
- All records feature authentic timestamps, IP addresses (`::1`), user IDs, and usernames.
- **Verdict**: CLEAN. Audit logging is authentic, comprehensive, and non-fabricated.

---

## Mode-Specific Verification (Development Mode)

Under **Development Mode** (per `ORIGINAL_REQUEST.md`, line 8):
- Hardcoded test results: **NONE (CLEAN)**
- Facade implementations: **NONE (CLEAN)**
- Fabricated verification outputs: **NONE (CLEAN)**
- Third-party core delegation: **NONE (CLEAN)** — All OpenXML generation, XML parsing, CSV parsing, and date normalization were built natively from scratch without external libraries.

---

## Final Binary Verdict

```
============================================================
  FORENSIC INTEGRITY AUDIT VERDICT: CLEAN
============================================================
  All forensic checks passed. Zero integrity violations found.
  Work product is authentic, genuine, and verified empirically.
============================================================
```
