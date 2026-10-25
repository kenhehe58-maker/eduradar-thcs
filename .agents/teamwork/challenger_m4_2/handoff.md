# HANDOFF REPORT: Milestone 4 Excel I/O & BOLA / IDOR Protection Challenge

**Author**: `challenger_m4_2`  
**Role**: `critic`, `specialist` (Empirical Challenger)  
**Working Directory**: `D:\New folder\my-web-project\.agents\teamwork\challenger_m4_2`  
**Recipient**: `orchestrator_1_gen2` (`391812a0-32ec-4263-b097-6162669cc4f7`)  
**Type**: Hard Handoff (Challenge Complete)  
**Verdict**: **`APPROVE`**  
**Date**: 2026-10-17  

---

## 1. Observation

1. **Test Execution of Challenge Suite `tests/challenge_m4_excel_bola.php`**:
   - Command: `& "D:\xam\php\php.exe" "D:\New folder\my-web-project\tests\challenge_m4_excel_bola.php"`
   - Output:
     ```
     ================================================================================
       ADVERSARIAL EMPIRICAL CHALLENGE SUITE: MILESTONE 4 (EXCEL I/O & BOLA/IDOR)   
     ================================================================================

     --- CATEGORY 1: Excel & CSV Bulk Import Stress Testing ---
     [IMPORT] 1.1 Reject non-zip text disguised as .xlsx without crash......... [PASS]
     [IMPORT] 1.2 Reject truncated/corrupted ZIP archive gracefully............ [PASS]
     [IMPORT] 1.3 Reject random binary garbage bytes with .xlsx extension...... [PASS]
     [IMPORT] 1.4 Handle empty .xlsx workbook (0 rows) gracefully.............. [PASS]
     [IMPORT] 1.5 Handle zero-byte .csv file gracefully........................ [PASS]
     [IMPORT] 1.6 Handle spreadsheet missing full_name column safely........... [PASS]
     [IMPORT] 1.7 Resilient header detector handles weird casing & accents..... [PASS]
     [IMPORT] 1.8 Out-of-range & malicious cell values sanitized to NULL....... [PASS]
     [IMPORT] 1.9 Formula injection (=CMD, +SUM) in student name safely escaped [PASS]
     [IMPORT] 1.10 Parse workbook with multiple sheets (sheet1 and sheet2)..... [PASS]
     [IMPORT] 1.11 Auto-detect and parse semicolon-delimited CSV with UTF-8 BOM [PASS]
     [IMPORT] 1.12 Auto-detect and parse tab-delimited (TSV) gradebook file.... [PASS]
     [IMPORT] 1.13 RFC 4180 CSV with escaped quotes and commas in cell values.. [PASS]
     [IMPORT] 1.14 Safe handling of XML special characters in Excel shared strings [PASS]

     --- CATEGORY 2: Exported .xlsx File OpenXML Conformance ---
     [EXPORT] 2.1 Download .xlsx file from nhap_diem.php?export=xlsx........... [PASS]
     [EXPORT] 2.2 Exported file is an uncorrupted, valid ZIP archive........... [PASS]
     [EXPORT] 2.3 All required ECMA-376 OpenXML parts are present in package... [PASS]
     [EXPORT] 2.4 All 7 XML documents are strictly well-formed XML without errors [PASS]
     [EXPORT] 2.5 Exported data accurately matches live students and scores in MySQL [PASS]
     [EXPORT] 2.6 Full Roundtrip Fidelity: Export -> Parse -> Re-import produces identical DB state [PASS]

     --- CATEGORY 3: Adversarial BOLA / IDOR Verification ---
     [BOLA] 3.1 Block gv_toan POST save_scores to unassigned class (6A2) -> 403 & 0 DB mod [PASS]
     [BOLA] 3.2 Block gv_toan POST save_scores to unassigned subject (Ngữ Văn) -> 403 & 0 DB mod [PASS]
     [BOLA] 3.3 Block gv_toan POST import_file to unassigned class (6A2) -> 403 & 0 DB mod [PASS]
     [BOLA] 3.4 Block gv_toan POST import_file to unauthorized subject (Ngữ Văn) -> 403 & 0 DB mod [PASS]
     [IDOR] 3.5 Block gv_toan IDOR parameter tampering ?teacher_id=3 -> HTTP 403 [PASS]
     [AUDIT] 3.6 Audit log recorded all blocked BOLA / IDOR attempts with details [PASS]
     [BOLA] 3.7 Unauthenticated POST save_scores redirects to login.php (HTTP 302) [PASS]
     [BOLA] 3.8 Unauthenticated POST import_file redirects to login.php (HTTP 302) [PASS]
     [BOLA] 3.9 Unauthenticated GET export=xlsx blocked without data leak (HTTP 302) [PASS]
     [BOLA] 3.10 SQL Injection in class_id / subject parameter blocked (HTTP 403 / safe PDO) [PASS]

     --- CATEGORY 4: Student Cross-Class Injection & Atomicity ---
     [CROSS_CLASS] 4.1 Injected student from another class is rejected (0 DB change) [PASS]
     [CROSS_CLASS] 4.2 Arbitrary non-existent student ID (999999) rejected..... [PASS]
     [CROSS_CLASS] 4.3 Mixed payload isolates class 1 student; foreign student ignored [PASS]
     [CROSS_CLASS] 4.5 Excel import with foreign class student code does NOT alter foreign class [PASS]
     [ATOMICITY] 4.6 SQL injection inside array keys of POST scores safely handled [PASS]

     ================================================================================
       TOTAL TESTS: 35 | PASSED: 35 | FAILED: 0
     ================================================================================
     VERDICT: APPROVE
     ```

2. **Source Code Inspection of Defensive Guards in `nhap_diem.php`**:
   - Lines 30–36: Anti-IDOR check for teachers:
     ```php
     if (isset($_GET['teacher_id']) && (int)$_GET['teacher_id'] !== $realUserId) {
         http_response_code(403);
         log_action($realUserId, $_SESSION['username'], 'Truy cập trái phép bị chặn', "Giáo viên [{$_SESSION['username']}] cố tình giả mạo tham số teacher_id=" . (int)$_GET['teacher_id']);
         render_forbidden_page("Bạn không có quyền truy cập thông qua mã định danh giáo viên khác.");
         exit;
     }
     ```
   - Lines 68–84 & 106–108: Strict assignment verification via `require_teacher_assignment($realUserId, $selectedClassId, $selectedSubject)` before GET, POST save_scores, and GET/POST export_xlsx.
   - Lines 204–206 & 238–240: Anti-BOLA student whitelist map preloaded from database for selected class (`$validStudentMap = array_flip($vStmt->fetchAll(PDO::FETCH_COLUMN))`). Any student ID not belonging to the selected class is strictly ignored with 0 database modification.
   - Lines 233–275 & 320–426: All score updates and file imports execute inside `$pdo->beginTransaction()` and `$pdo->commit()`, rolled back via `$pdo->rollBack()` on any exception.
   - Lines 387–393: Score sanitization closure:
     ```php
     $cleanScore = function($val) {
         if ($val === null || $val === '') return null;
         $s = str_replace(',', '.', trim((string)$val));
         if (!is_numeric($s)) return null;
         $f = (float)$s;
         return ($f >= 0.0 && $f <= 10.0) ? $f : null;
     };
     ```

3. **OpenXML Conformance Verification**:
   - The exported spreadsheet binary produced by `create_xlsx()` was extracted using PHP's `ZipArchive`.
   - All 7 mandatory ECMA-376 OpenXML documents (`[Content_Types].xml`, `_rels/.rels`, `xl/_rels/workbook.xml.rels`, `xl/workbook.xml`, `xl/styles.xml`, `xl/sharedStrings.xml`, `xl/worksheets/sheet1.xml`) parsed with 0 errors via `simplexml_load_string()`.
   - Full roundtrip test (Export -> Parse -> Re-import via `action=import_file`) verified 100% data preservation and 0 duplicate rows in MySQL.

4. **Regression Test Suite Verification**:
   - `& "D:\xam\php\php.exe" tests/verify_m4.php`: 39/39 PASS (0 failures)
   - `& "D:\xam\php\php.exe" tests/verify_m1.php`: 12/12 PASS (0 failures)
   - `& "D:\xam\php\php.exe" tests/verify_m2.php`: 16/16 PASS (0 failures)
   - `& "D:\xam\php\php.exe" tests/verify_m3.php`: 28/28 PASS (0 failures)
   - `& "D:\xam\php\php.exe" tests/challenge_m2_rbac.php`: 37/37 PASS (0 failures)
   - Total passing across all test suites: 167/167 tests (100% pass rate).

---

## 2. Logic Chain

1. **File Parsing Robustness (Observation 1 [Tests 1.1–1.14] -> 2)**:
   - Because `parse_xlsx()` wraps `ZipArchive::open()` in strict validation checks, feeding it truncated, corrupted, or non-zip data returns an empty array rather than triggering fatal errors.
   - Because `detect_gradebook_headers()` searches multi-keyword aliases with case-folding and diacritic normalization across rows 0–3, non-standard and shifted headers are reliably mapped.
   - Because `cleanScore()` validates `is_numeric()` and bounds values within `[0.0, 10.0]`, all out-of-range numbers, strings, SQLi, and script injections in spreadsheet cells are sanitized to `NULL`.

2. **OpenXML Compliance (Observation 1 [Tests 2.1–2.6] -> 3)**:
   - Because `create_xlsx()` generates well-formed XML with escaped shared strings and minimal standard styles conforming to ISO/IEC 29500 / ECMA-376, `ZipArchive` extracts without error and XML parsers report 0 syntax errors.
   - Because cell references and row indices are mapped deterministically, the full Export -> Re-import cycle retains identical scores with zero duplicate records.

3. **BOLA / IDOR Authorization Enforcement (Observation 1 [Tests 3.1–3.10] -> 2)**:
   - Because `require_teacher_assignment()` checks the `teacher_classes` database table using the authenticated session's `user_id`, any forged POST save or import request targeting an unassigned class (e.g. 6A2) or unauthorized subject (e.g. Ngữ Văn) immediately terminates with HTTP 403 Forbidden.
   - Empirical inspection of MySQL showed 0 modified rows in `scores` or `students` for unassigned classes or subjects, and all attempts were logged to `audit_logs`.

4. **Cross-Class Student Isolation & Transaction Atomicity (Observation 1 [Tests 4.1–4.6] -> 2)**:
   - In `nhap_diem.php`, valid student IDs are pre-queried for the selected class (`$validStudentMap`). Any student ID in the POST payload not present in `$validStudentMap` is skipped with zero mutation.
   - In `import_file`, queries searching for existing students strictly include `WHERE class_id = ?`, preventing cross-class record corruption.
   - Both `save_scores` and `import_file` operate inside atomic PDO transactions, rolling back on error.

---

## 3. Caveats

- **No Caveats**: All 4 areas specified in the dispatch (corrupted/empty/missing Excel imports, exported OpenXML validity, BOLA/IDOR protection on save/import, and cross-class student injection) were empirically tested with dedicated adversarial scripts and verified on the live system with 0 failures.

---

## 4. Conclusion

**Verdict: `APPROVE`**

Milestone 4 implementation in `nhap_diem.php` and `includes/xlsx_helper.php` is robust, secure, and production-ready:
1. Excel/CSV bulk import gracefully handles corrupted archives, empty workbooks, missing columns, non-standard headers, and sanitizes out-of-range scores to `NULL`.
2. Exported `.xlsx` files conform strictly to the ECMA-376 OpenXML standard with 0 XML validation errors and exact live score matching.
3. BOLA/IDOR attacks on both `save_scores` and `import_file` are strictly defeated with HTTP 403 and zero database modification.
4. Student cross-class injection is defeated by server-side whitelist mapping and class-scoped queries with atomic transaction integrity.

---

## 5. Verification Method

To independently reproduce and verify this challenge:

1. **Run Milestone 4 Adversarial Challenge Suite**:
   ```powershell
   & "D:\xam\php\php.exe" "D:\New folder\my-web-project\tests\challenge_m4_excel_bola.php"
   ```
   *Expected*: `TOTAL TESTS: 35 | PASSED: 35 | FAILED: 0`, `VERDICT: APPROVE` (Exit code 0).

2. **Run All Milestone Verification Suites**:
   ```powershell
   & "D:\xam\php\php.exe" "D:\New folder\my-web-project\tests\verify_m4.php"
   & "D:\xam\php\php.exe" "D:\New folder\my-web-project\tests\verify_m1.php"
   & "D:\xam\php\php.exe" "D:\New folder\my-web-project\tests\verify_m2.php"
   & "D:\xam\php\php.exe" "D:\New folder\my-web-project\tests\verify_m3.php"
   & "D:\xam\php\php.exe" "D:\New folder\my-web-project\tests\challenge_m2_rbac.php"
   ```
   *Expected*: All test suites pass 100% with 0 failures.

3. **Inspect Output Reports**:
   - `D:\New folder\my-web-project\.agents\teamwork\challenger_m4_2\challenge.md`
   - `D:\New folder\my-web-project\.agents\teamwork\challenger_m4_2\handoff.md`
