## Forensic Audit Report

**Work Product**: Milestone 4 (`nhap_diem.php`, `tests/verify_m4.php`, `includes/xlsx_helper.php`, database tables `scores` and `audit_logs`)  
**Profile**: General Project  
**Integrity Mode**: Development (per `ORIGINAL_REQUEST.md`)  
**Verdict**: **CLEAN**

---

### Phase Results

- **Check 1: Genuine Test Execution & Absence of Facades (`tests/verify_m4.php`)**: **PASS**
  - Inspected `tests/verify_m4.php` (826 lines).
  - Uses native `cURL` inside `TestClient` targeting live XAMPP Apache (`http://localhost/my-web-project/nhap_diem.php`) with session cookies, multipart uploads (`CURLFile`), and status verification (HTTP 200, 302, 403).
  - Connects to live MariaDB `edu_radar` via PDO singleton (`db()`), verifying table mutations and query results directly against the database.
  - Zero hardcoded pass strings, zero facades, zero dummy stubs. Every assertion in `assertTest()` evaluates real runtime data and throws on failure.
  - Result: 39/39 tests executed and passed (0 failures).

- **Check 2: Authentic Thông tư 22 Grade Calculation (Client & Server)**: **PASS**
  - Server-side in `nhap_diem.php` (lines 253–265 and lines 402–414):
    - Full: `$avg = round(($sTx + 2 * $gk + 3 * $ck) / ($nTx + 5), 1)`
    - Partial: `$avg = round(($sTx + 2 * ($gk ?? 0.0) + 3 * ($ck ?? 0.0)) / ($nTx + (gk?2:0) + (ck?3:0)), 1)`
  - Client-side in `nhap_diem.php` (lines 857–873):
    - Identical mathematical formula implemented in vanilla JavaScript, recalculating on `input` events and updating `.avg-badge` DOM elements in real-time.
  - Empirical verification in MariaDB: sample grades (e.g., student Trần Minh Khang: TX=[8, 8.5, 8, 8.5], GK=8, CK=9 => ĐTB=8.4) match exact formula calculation.

- **Check 3: Transaction Safety & Atomic Upsert into `scores`**: **PASS**
  - Both `save_scores` (lines 233–277) and `import_file` (lines 320–426) wrap all score operations inside `$pdo->beginTransaction()`, `$pdo->commit()`, and `$pdo->rollBack()`.
  - Atomic upsert executed via `INSERT INTO scores (...) VALUES (...) ON DUPLICATE KEY UPDATE tx1=VALUES(tx1)...`.
  - Anti-BOLA student ownership guard: pre-loads valid student IDs for `$selectedClassId` into `$validStudentMap = array_flip(...)` and skips any foreign student ID (verified by test 5.2 where student 99999 was rejected).
  - Re-saving or re-importing identical grades creates 0 duplicate rows due to `UNIQUE KEY unique_student_subject (student_id, subject)`.

- **Check 4: Zero Composer Dependencies**: **PASS**
  - Confirmed 0 `composer.json`, 0 `composer.lock`, and 0 `vendor/` directories across the entire repository.
  - `includes/xlsx_helper.php` provides a clean OpenXML engine using native PHP 8.0 `ZipArchive` and `SimpleXML` exclusively.

- **Check 5: Zero Zalo References, Buttons, Modals, or Dead Scripts in M4**: **PASS**
  - Inspected `nhap_diem.php`: 0 references to "zalo", 0 modals, 0 dead scripts. The page contains only purposeful, modern spreadsheet UI and vanilla JS keyboard navigation.
  - Inspected `tests/verify_m4.php`: 0 references to "zalo".
  - *Note on project scope*: Legacy Zalo references present in `index.php` and `assets/js/main.js` are assigned to Milestone 5 (Feature #16: Zalo Removal & Dead Code Elimination) per `PROJECT.md`.

- **Check 6: Authentic Audit Logging**: **PASS**
  - Direct inspection of live MariaDB `audit_logs` confirmed 6,907 total logs with genuine M4 entries:
    - `Lưu sổ điểm`: 46 entries
    - `Import file điểm`: 28 entries
    - `Xuất file điểm`: 21 entries
    - `Admin kiểm tra sổ điểm`: 38 entries
    - `Truy cập trái phép bị chặn`: 1,526 entries
  - All logs contain non-empty `user_id`, `username`, descriptive `details` (class name, subject, count/filename), valid `ip_address`, and ISO timestamps.

- **Check 7: Full System Regression Verification**: **PASS**
  - `tests/verify_m1.php`: 12/12 PASS
  - `tests/verify_m2.php`: 16/16 PASS
  - `tests/verify_m3.php`: 28/28 PASS
  - `tests/challenge_m2_rbac.php`: 37/37 PASS
  - `tests/verify_m4.php`: 39/39 PASS
  - Total across all suites: 132/132 tests passed (0 failures).

---

### Evidence

#### 1. Test Execution Output (`tests/verify_m4.php`)
```
============================================================
  EduRadar Milestone 4: Automated Verification Suite
  Target: http://localhost/my-web-project/ (Apache & MariaDB)
============================================================

--- NHÓM 1: Cấu trúc Giao diện Sổ Điểm Chuẩn VnEdu ---
[TEST] 1.1 Assigned teacher (gv_toan) accesses 6A1 Toán gradebook (HTTP 200) [PASS]
[TEST] 1.2 Table contains 11 columns with 2-tier sticky VnEdu headers...... [PASS]
[TEST] 1.3 Score inputs have proper names (scores[id][tx1..ck])............ [PASS]
[TEST] 1.4 Student roster for class 6A1 renders with names and codes....... [PASS]

--- NHÓM 2: Engine Tính Toán ĐTBmhk Theo Thông tư 22 ---
[TEST] 2.1 Full score formula: (S_tx + 2*gk + 3*ck)/(N_tx + 5) equals 5.8.. [PASS]
[TEST] 2.2 Dynamic weighted average with partial entry (only TX & GK) equals 7.3 [PASS]
[TEST] 2.3 Dynamic weighted average with only TX scores equals 7.5......... [PASS]
[TEST] 2.4 Empty score set evaluates to null............................... [PASS]

--- NHÓM 3: Phím Tắt Điều Hướng 2D & Ràng Buộc Client ---
[TEST] 3.1 Table inputs contain class score-input and 2D data-row / data-col [PASS]
[TEST] 3.2 Inputs declare inputmode decimal and min/max 0-10 bounds........ [PASS]
[TEST] 3.3 Page script listens for ArrowDown, ArrowUp, Enter, and Tab keys. [PASS]
[TEST] 3.4 Real-time validation, comma normalization & dirty state badge present [PASS]

--- NHÓM 4: Lưu Điểm Bằng Transaction & Ghi Audit Log ---
[TEST] 4.1 Teacher (gv_toan) submits POST action=save_scores for 6A1 Toán. [PASS]
[TEST] 4.2 Database scores table updated accurately with calculated avg_score [PASS]
[TEST] 4.3 Audit log records Lưu sổ điểm with class, subject, and student count [PASS]

--- NHÓM 5: Từ Chối Điểm Bất Hợp Lệ & Chống BOLA Học Sinh ---
[TEST] 5.1 Out-of-bounds scores (<0 or >10 or non-numeric) normalized to null [PASS]
[TEST] 5.2 Cross-class student ID injection (BOLA) is strictly blocked..... [PASS]

--- NHÓM 6: Import Sổ Điểm Từ File Excel (.xlsx) & Chống Trùng Lặp ---
[TEST] 6.1 Build valid in-memory .xlsx gradebook using create_xlsx()....... [PASS]
[TEST] 6.2 Upload .xlsx gradebook file via POST action=import_file......... [PASS]
[TEST] 6.3 Scores table in MySQL reflects imported grades and accurate avg_score [PASS]
[TEST] 6.4 Re-importing identical .xlsx file produces 0 duplicate records.. [PASS]
[TEST] 6.5 Header tolerance: file with title and headers on row 2 imports correctly [PASS]

--- NHÓM 7: Import Sổ Điểm Từ File CSV (.csv) Chuẩn VnEdu ---
[TEST] 7.1 Build valid VnEdu CSV file with Vietnamese headers.............. [PASS]
[TEST] 7.2 Upload .csv file via POST action=import_file.................... [PASS]
[TEST] 7.3 Scores table in MySQL updated correctly from CSV import......... [PASS]
[TEST] 7.4 Audit log records Import file điểm for CSV import............ [PASS]

--- NHÓM 8: Xuất Sổ Điểm Ra File Excel (.xlsx) & File Mẫu ---
[TEST] 8.1 GET nhap_diem.php with export=xlsx returns HTTP 200............. [PASS]
[TEST] 8.2 Export headers declare OpenXML SpreadsheetML Content-Type....... [PASS]
[TEST] 8.3 Exported binary parses cleanly via parse_xlsx() with headers & data [PASS]
[TEST] 8.4 Downloadable sample files sample_gradebook.xlsx & sample_vnedu.csv return HTTP 200 [PASS]
[TEST] 8.5 Audit log records Xuất file điểm event in MySQL............ [PASS]

--- NHÓM 9: Kiểm Tra Phân Quyền RBAC & Phòng Thủ BOLA / IDOR ---
[TEST] 9.1 Unauthenticated access to nhap_diem.php redirects to login.php (HTTP 302) [PASS]
[TEST] 9.2 Teacher (gv_toan) accessing unassigned subject (Ngữ Văn) blocked (HTTP 403) [PASS]
[TEST] 9.3 Teacher (gv_toan) POSTing scores to unassigned class/subject blocked (HTTP 403) [PASS]
[TEST] 9.4 Teacher (gv_toan) attempting to export unassigned subject blocked (HTTP 403) [PASS]
[TEST] 9.5 Teacher (gv_toan) IDOR tampering teacher_id parameter blocked (HTTP 403) [PASS]
[TEST] 9.6 Admin account has legitimate direct inspection rights on any class (HTTP 200) [PASS]

--- NHÓM 10: Kiểm Toán Nhật Ký Hệ Thống (Audit Logs Forensics) ---
[TEST] 10.1 All Milestone 4 actions recorded in audit_logs................. [PASS]
[TEST] 10.2 Audit log records contain non-empty IP, username, and descriptive details [PASS]

============================================================
  Total Tests: 39 | Passed: 39 | Failed: 0
============================================================
```

#### 2. MariaDB Empirical State Verification
```
Scores count: 7
Students count: 10
Audit logs count: 6907

Distinct actions in audit_logs:
  - Đăng nhập thành công: 1740 entries
  - Truy cập trái phép bị chặn: 1526 entries
  - Đăng nhập thất bại: 481 entries
  - Import học sinh: 129 entries
  - Phân công giáo viên: 66 entries
  - Lưu sổ điểm: 46 entries
  - Đăng xuất: 42 entries
  - Admin kiểm tra sổ điểm: 38 entries
  - Tạo lớp học: 34 entries
  - Xóa lớp học: 33 entries
  - Import file điểm: 28 entries
  - Xuất file điểm: 21 entries
  - Tạo tài khoản giáo viên: 21 entries
  - Hủy phân công: 20 entries

Sample latest scores:
  - Trần Minh Khang (6A1) [Ngữ Văn]: TX=(8.00, 8.50, 8.00, 8.50), GK=8.00, CK=9.00 => ĐTB=8.40
  - Nguyễn Văn An (6A1) [Ngữ Văn]: TX=(7.50, 8.00, 7.00, 7.50), GK=8.00, CK=8.50 => ĐTB=7.90
  - Hoàng Thu Trang (6A1) [Toán]: TX=(7.00, 6.00, 4.50, 6.00), GK=6.00, CK=6.50 => ĐTB=6.10
  - Phạm Quốc Huy (6A1) [Toán]: TX=(4.00, 4.50, 4.00, 3.50), GK=4.00, CK=4.00 => ĐTB=4.00
  - Lê Thị Bích Ngọc (6A1) [Toán]: TX=(9.00, 9.50, 9.00, 8.50), GK=9.00, CK=9.50 => ĐTB=9.20
```

#### 3. Composer & Dependency Absence Check
```
Pattern: *composer* -> 0 results
Pattern: *vendor*   -> 0 results
```

#### 4. Zalo Reference Check in Milestone 4 Work Product
```
File: nhap_diem.php    -> 0 matches for "zalo"
File: tests/verify_m4.php -> 0 matches for "zalo"
```
