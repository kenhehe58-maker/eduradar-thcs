# HANDOFF REPORT — Milestone 4 (Excel/CSV Engine & Automated Test Suite)

**Agent**: `explorer_m4_3`  
**Working Directory**: `D:\New folder\my-web-project\.agents\teamwork\explorer_m4_3`  
**Target Milestone**: M4 (VnEdu Gradebook & Spreadsheet — Feature #14 & Test Suite)  
**Parent Agent**: `orchestrator_1_gen2` (`391812a0-32ec-4263-b097-6162669cc4f7`)  
**Type**: Hard Handoff (Investigation & Design Complete)

---

## 1. Observation

1. **`includes/xlsx_helper.php` capabilities**:
   - Lines 118–264 define `parse_xlsx(string $filepath): array` using PHP `ZipArchive` and `SimpleXML` without Composer dependencies.
   - Lines 274–411 define `create_xlsx(array $data, string $sheet_name = 'Sheet1'): string` which constructs compliant OpenXML SpreadsheetML ZIP archives (`[Content_Types].xml`, `_rels/.rels`, `xl/workbook.xml`, `xl/styles.xml`, `xl/sharedStrings.xml`, `xl/worksheets/sheet1.xml`).
   - Lines 419–471 define `parse_csv(string $filepath): array` with automatic UTF-8 BOM stripping (`\xEF\xBB\xBF`) and delimiter detection (`,`, `;`, `\t`).
   - Lines 58–109 define `normalize_excel_date($raw): ?string` and `normalize_vn_date($raw): string` converting Excel date serials or `DD/MM/YYYY` to MySQL `YYYY-MM-DD`.

2. **`nhap_diem.php` legacy discrepancy**:
   - Lines 112–146 (`save_scores`) and lines 180–204 (`import_file`): currently use legacy column names `m1, m2, p15_1, p15_2, t1_1, gk, ck, avg_score`.
   - Lines 159–165: Form handler accepts only CSV files (`if (isset($_FILES['csv_file']))`) and uses `fgetcsv()`, lacking `.xlsx` binary support.
   - Lines 385–399: The bottom section has only a CSV file upload form (`accept=".csv, .txt"`), has no "Xuất Excel Sổ Điểm" button, and only links to `sample_vnedu.csv` (missing `sample_gradebook.xlsx`).

3. **Database Schema (`edu_radar.scores`)**:
   - Verified via `D:\xam\php\php.exe -r "require 'includes/db.php'; print_r(db()->query('DESCRIBE scores')->fetchAll());"`:
     `scores` table has fields: `id, student_id, subject, tx1, tx2, tx3, tx4, gk, ck, avg_score, updated_at`.
     Constraint: `UNIQUE KEY unique_student_subject (student_id, subject)`.

4. **Available Sample Files**:
   - `sample_gradebook.xlsx`: Row 0 header is `STT, Họ và tên, Số điện thoại phụ huynh, ĐĐGtx1, ĐĐGtx2, ĐĐGtx3, ĐĐGtx4, ĐĐGgk, ĐĐGck` followed by 5 rows of 6A1 student grades.
   - `sample_vnedu.csv`: Header is `"Họ và tên","Số điện thoại phụ huynh","Miệng","15P_1","15P_2","1Tiết","GiữaKỳ","CuốiKỳ"`.

5. **Existing Test Suite Baseline**:
   - Executed `D:\xam\php\php.exe tests/verify_m3.php`: 28/28 tests passed (0 failures).
   - `tests/verify_m4.php` does not exist yet and must be created.

---

## 2. Logic Chain

1. **Premise 1 (Schema Synchronization)**: Because `scores` in MySQL has columns `tx1..tx4, gk, ck, avg_score` (Observation 3), while `nhap_diem.php` currently references `m1, m2, p15_1...` (Observation 2), any file import or score save must read/write `tx1..tx4, gk, ck, avg_score`.
2. **Premise 2 (Zero-dependency OpenXML)**: Because `includes/xlsx_helper.php` provides functional `parse_xlsx` and `create_xlsx` methods (Observation 1), `nhap_diem.php` can import `.xlsx` via `parse_xlsx()` and export via `create_xlsx()` with zero third-party dependencies, adhering strictly to Project architecture.
3. **Premise 3 (Header Tolerance)**: Because `sample_gradebook.xlsx` uses `ĐĐGtx1..4` and `sample_vnedu.csv` uses `Miệng, 15P_1...` (Observation 4), a dynamic header detection function (`detect_gradebook_headers`) scanning rows 0–3 is required to seamlessly support both formats without user frustration.
4. **Premise 4 (Deduplication & Transaction)**: Because `scores` has `UNIQUE(student_id, subject)`, executing `INSERT ... ON DUPLICATE KEY UPDATE` wrapped in `$pdo->beginTransaction()` and `$pdo->commit()` guarantees that re-importing the same file produces 0 duplicate rows and cannot leave partial data on error.
5. **Premise 5 (RBAC & Audit)**: Because BOLA/IDOR protection was verified in M2/M3 (Observation 5), export and import actions in `nhap_diem.php` must call `require_teacher_assignment()` and record audit logs (`Import file điểm`, `Xuất file điểm`) to preserve 100% compliance with security criteria.
6. **Premise 6 (Test Suite Completeness)**: To verify all 10 DISPATCH requirements, `tests/verify_m4.php` must use the established `TestClient` pattern to test page structure, formula accuracy, keyboard bindings, score save, out-of-bounds rejection, .xlsx import, .csv import, .xlsx export, RBAC/BOLA, and audit logging.

---

## 3. Caveats

1. **Backward Compatibility with Legacy Keys in POST**: Tests from M2 (`challenge_m2_rbac.php`) may submit payloads using legacy keys (`m1`, `p15_1`, etc.). The POST handler in `nhap_diem.php` should map `$row['tx1'] ?? $row['m1'] ?? null` to avoid regressing earlier test suites.
2. **Missing Students during Grade Import**: When an imported gradebook includes students not currently in the class roster, auto-inserting them into `students` ensures full roster synchronization; however, the `class_id` must strictly match the current active class to prevent cross-class leakage.
3. **HTTP Header Delivery during Export**: The export handler must call `log_action()` before outputting binary headers and invoking `exit` to ensure the audit log row is committed to MySQL before script termination.

---

## 4. Conclusion

1. **Import Engine**: Fully designed with `detect_gradebook_headers()` (supporting rows 0–3, `.xlsx` and `.csv`), 3-step student matching, 0.0–10.0 score validation, Thông tư 22 average calculation, and atomic UPSERT in transactions.
2. **Export Engine**: Fully designed with `create_xlsx()`, producing standard ECMA-376 `.xlsx` attachments named `SoDiem_[TenLop]_[MonHoc]_[NamHoc].xlsx` protected by RBAC and logged to `audit_logs`.
3. **UI Layout**: Dual-card panel designed for the bottom of `nhap_diem.php` with direct download links for both `sample_gradebook.xlsx` and `sample_vnedu.csv`.
4. **Test Suite Specification**: Complete 10-group test plan designed for `tests/verify_m4.php` ready for immediate implementation.
5. All design blueprints and code snippets are documented in `analysis.md`.

---

## 5. Verification Method

1. **Verify Helper Functions**:
   Run:
   ```powershell
   D:\xam\php\php.exe -r "require 'includes/xlsx_helper.php'; var_dump(function_exists('parse_xlsx'), function_exists('create_xlsx'));"
   ```
   *Expected*: `bool(true)`, `bool(true)`.

2. **Verify Existing Tests Pass**:
   Run:
   ```powershell
   D:\xam\php\php.exe tests/verify_m3.php
   ```
   *Expected*: `Total Tests: 28 | Passed: 28 | Failed: 0`.

3. **Verify Implementation of M4 (once worker builds it)**:
   Run:
   ```powershell
   D:\xam\php\php.exe tests/verify_m4.php
   ```
   *Expected*: 10/10 test groups pass with exit code 0.
