# Handoff Report: VnEdu Student Roster Import, Deduplication Engine & M3 Test Suite

- **From**: `explorer_m3_3` (M3 Student Import & Dedup Explorer)
- **To**: `orchestrator_1_gen2` / `planner_m3_1` / `implementer_m3_1`
- **Working Directory**: `D:\New folder\my-web-project\.agents\teamwork\explorer_m3_3`
- **Date**: 2026-10-17T08:54:00Z
- **Milestone**: M3 (Admin Module & Student Import)

---

## 1. Observation

1. **Schema Mismatch in `admin.php`**:
   - `admin.php:68`: `$ins = $pdo->prepare("INSERT INTO students (class_id, full_name, birthday, parent_phone) VALUES (?, ?, ?, ?)");`
   - In `database.sql:66-79` and live MariaDB `DESCRIBE students`, the column name is `phone VARCHAR(20)`, NOT `parent_phone`. This currently triggers `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'parent_phone'` upon inserting students.
2. **Missing `.xlsx` and Header Handling in `admin.php`**:
   - `admin.php:54-58`: Accepts only files via `fgetcsv()`, with hardcoded column indices (`$data[0]` = name, `$data[1]` = birthday, `$data[2]` = phone).
   - If a standard VnEdu export has headers on line 2 or 3 (preceded by school/class title headers), or includes `STT`, the parser erroneously inserts header text as student names.
3. **MySQL `NULL` in Unique Key Duplicate Vulnerability**:
   - `database.sql:75`: `UNIQUE KEY unique_class_student (class_id, full_name, birthday)`.
   - Live MariaDB transaction test executed via PHP:
     ```php
     $p->exec("INSERT INTO students (class_id, full_name, birthday) VALUES (1, 'Test Null 1', NULL)");
     $p->exec("INSERT INTO students (class_id, full_name, birthday) VALUES (1, 'Test Null 1', NULL)");
     ```
     Result: **`RESULT: ALLOWED DUPLICATE NULL`**. MariaDB permitted multiple duplicate rows because `NULL != NULL`.
   - Conversely, with non-null birthday (`'2015-01-01'`):
     Result: **`SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '1-Test Not Null 1-2015-01-01' for key 'unique_class_student'`**.
4. **Excel Serial Date Math Verification**:
   - Verified that `(new DateTime('1899-12-30'))->modify('+42075 days')->format('Y-m-d')` outputs `2015-03-12`, proving the exact formula for converting Excel serial numbers to ISO `YYYY-MM-DD`.
5. **Runtime Capabilities**:
   - `D:\xam\php\php.exe`: PHP 8.0.30 CLI has `ZipArchive: YES`, `SimpleXML: YES`, `CURLFile: YES`, and PDO MySQL driver active.
6. **Audit & Feedback Gaps**:
   - `admin.php:80-81`: `log_action(...)` records only imported count, omitting uploaded filename and duplicate count. User feedback does not inform the administrator how many duplicates were skipped.

---

## 2. Logic Chain

1. **Resolution of Schema & Insert Query** *(from Obs 1)*:
   - The insert statement must be changed from `parent_phone` to `phone`, and expanded to include `student_code` and `gender`:
     `INSERT INTO students (class_id, student_code, full_name, birthday, gender, phone) VALUES (?, ?, ?, ?, ?, ?)`.
2. **Handling Dual Formats (.xlsx & .csv)** *(from Obs 2 & 5)*:
   - Because `ZipArchive` and `SimpleXML` are natively enabled in PHP 8.0.30, `admin.php` inspects file extension:
     - `.csv` files are parsed via `parse_csv($tmpPath)`.
     - `.xlsx` files are parsed via `parse_xlsx($tmpPath)`.
     - Unsupported extensions are rejected with an explicit error message.
3. **Elimination of `NULL` Duplicate Bypass** *(from Obs 3)*:
   - To prevent MySQL from allowing duplicate rows when `birthday` is absent, the date normalization pipeline `normalize_vn_date()` enforces a fallback sentinel date `'1970-01-01'`.
   - Combined with application pre-checking `SELECT id FROM students WHERE class_id = ? AND full_name = ? AND birthday = ?`, deduplication is strictly enforced across all inputs.
4. **Resilient Header Detection** *(from Obs 2)*:
   - Scanning rows 0–4 for keywords (`họ và tên`, `ngày sinh`, `stt`, `mã hs`, `giới tính`, `sđt`) dynamically locates the header row index and column positions, making the import impervious to whether headers start on row 1, 2, or 3.
5. **Date Normalization Pipeline** *(from Obs 4)*:
   - All input dates (`DD/MM/YYYY`, `DD-MM-YYYY`, `DD.MM.YYYY`, `YYYY-MM-DD`, and Excel serial numbers like `42075`) are normalized to `YYYY-MM-DD` via `normalize_vn_date()` and validated using `checkdate()`.
6. **Complete Audit Trail & Feedback** *(from Obs 6)*:
   - Calculating both `$insertedCount` and `$duplicateCount` enables accurate feedback:
     - "Đã nhập thành công X học sinh vào lớp Y (bỏ qua Z học sinh đã tồn tại trong lớp)."
     - Audit log: `Admin import học sinh vào lớp [6A1] từ file [students.xlsx]: thêm mới X, trùng lặp bỏ qua Z.`
7. **Verification Architecture**:
   - `tests/verify_m3.php` is specified with 6 test groups and 25 test cases using `TestClient` with cURL `CURLFile` multipart uploads and PDO state queries to verify live Apache and MariaDB behavior with 0 failures.

---

## 3. Caveats

1. **Parallel Milestone Tasks**:
   - `includes/xlsx_helper.php` is being designed by `explorer_m3_1`. The interface contract requires `parse_xlsx(string $filepath): array`, `create_xlsx(array $data, string $sheet_name = 'Sheet1'): string`, and `parse_csv(string $filepath): array`.
   - `admin.php` class matrix and UI layout are being overhauled by `explorer_m3_2`. The student import block should be merged into the designated panel box in `admin.php`.
2. **Sentinel Date for Absent Birthday**:
   - If a student record in a file contains no birthday at all, it is assigned `'1970-01-01'`. In Vietnamese secondary schools (THCS), birth dates are required on all official VnEdu rosters, so this serves strictly as a data-integrity fallback.
3. **No Unrequested Features**:
   - No external libraries (Composer, PhpSpreadsheet) are used. Everything relies purely on native PHP 8.0 `ZipArchive`, `SimpleXML`, and PDO.

---

## 4. Conclusion

1. **Import Handler Design**:
   - Replace the legacy CSV code in `admin.php` with the specified engine supporting `.xlsx` and `.csv`, resilient header scanning (rows 0–4), date normalization (`normalize_vn_date`), dual-layer deduplication, and granular feedback.
2. **Template Availability**:
   - Provide download buttons for both `sample_students.xlsx` and `sample_students.csv` in `admin.php`.
3. **Verification Suite**:
   - Implement `tests/verify_m3.php` as detailed in Section 7 of `analysis.md`. The suite thoroughly exercises OpenXML helpers, class matrix management, batch assignment, teacher creation, student import deduplication, audit logging, and RBAC route protection.

---

## 5. Verification Method

To independently verify the implementation once applied:

1. **Run the Automated M3 Verification Suite**:
   ```powershell
   D:\xam\php\php.exe tests/verify_m3.php
   ```
   - **Expected Outcome**: All 25 tests pass (`[PASS]`), 0 failures, exit code 0.
2. **Regression Check against M1 and M2 Suites**:
   ```powershell
   D:\xam\php\php.exe tests/verify_m1.php
   D:\xam\php\php.exe tests/verify_m2.php
   ```
   - **Expected Outcome**: 12/12 pass on M1, 16/16 pass on M2.
3. **Database State Inspection**:
   ```powershell
   D:\xam\php\php.exe -r "require 'includes/db.php'; echo 'Students: ' . db()->query('SELECT COUNT(*) FROM students')->fetchColumn() . PHP_EOL; echo 'Audit logs: ' . db()->query('SELECT COUNT(*) FROM audit_logs WHERE action = \'Import học sinh\'')->fetchColumn() . PHP_EOL;"
   ```
   - **Invalidation Condition**: Any student count increase when re-importing the same roster file, any duplicate `(class_id, full_name, birthday)` rows in `students`, or missing audit log entries.
