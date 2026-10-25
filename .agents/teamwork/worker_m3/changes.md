# Code Changes Report — Milestone 3 (worker_m3)

## Summary
Milestone 3 implements the comprehensive Admin management portal, Secondary School Class Matrix (Grades 6–9, Sections A–C, Classes 1–9), batch checkbox teaching assignment with per-grade and global select-all, bcrypt teacher account creation, zero-dependency OpenXML (.xlsx) & CSV engine, VnEdu student roster import with multi-format date normalization and dual-layer database deduplication, direct teacher gradebook inspection, and real-time audit log viewer with live search.

---

## 1. Files Created and Modified

### 1.1 `includes/xlsx_helper.php` (Created)
- **Purpose**: Zero-dependency OpenXML (.xlsx) and CSV reader/writer complying with ECMA-376 / ISO/IEC 29500 using PHP's native `ZipArchive` and `SimpleXML` extensions.
- **Functions Implemented**:
  - `parse_xlsx(string $filepath): array`: Reads sheets into dense, 0-indexed 2D arrays, resolving shared strings (`xl/sharedStrings.xml`), cell references, and missing sparse cells.
  - `create_xlsx(array $data, string $sheet_name = 'Sheet1'): string`: Builds a compliant `.xlsx` ZIP archive containing all 7 required files (`[Content_Types].xml`, `_rels/.rels`, `xl/_rels/workbook.xml.rels`, `xl/workbook.xml`, `xl/styles.xml`, `xl/sharedStrings.xml`, `xl/worksheets/sheet1.xml`). Explicitly treats values with leading zero (e.g. phone numbers `'0912345601'`) as strings (`t="s"`).
  - `parse_csv(string $filepath): array`: Strips UTF-8 BOM (`\xEF\xBB\xBF`) and auto-detects delimiter (comma, semicolon, tab).
  - `normalize_excel_date(mixed $raw): ?string`: Parses ISO dates, Vietnamese format dates (`DD/MM/YYYY`, `DD-MM-YYYY`, `DD.MM.YYYY`), and Excel serial numbers (days since 1899-12-30) into MySQL standard `YYYY-MM-DD`.
  - `normalize_vn_date(mixed $raw): string`: Wrapper around `normalize_excel_date` with `'1970-01-01'` fallback sentinel to prevent MySQL `NULL` bypass on `UNIQUE(class_id, full_name, birthday)`.
  - `index_to_col_letter(int $index): string` & `col_letter_to_index(string $col): int`: Bi-directional Excel column letter conversions.

### 1.2 `sample_students.xlsx` & `sample_gradebook.xlsx` (Created at Project Root)
- **`sample_students.xlsx`**: Standard VnEdu student roster template containing 10 students across columns `STT, Mã học sinh, Họ và tên, Ngày sinh, Giới tính, Số điện thoại phụ huynh`.
- **`sample_gradebook.xlsx`**: Standard VnEdu gradebook template with regular, midterm, and semester-final evaluation columns (`ĐĐGtx1..4, ĐĐGgk, ĐĐGck`).
- Both files are placed at project root (`D:\New folder\my-web-project\`) and verified HTTP accessible.

### 1.3 `admin.php` (Overhauled)
- **Top-level RBAC**: Preserved strict `require_admin()` at line 20 and in every action handler.
- **Class Matrix & Dynamic Management**:
  - Renders 36 classes grouped by Grade (6, 7, 8, 9) with section labels, student count badges (`[X HS]`), and cascading delete buttons.
  - Action `create_class` / `add_class`: Dynamically creates classes (`{$grade}{$section}{$classNum}` or custom name like `6C99_TEST`), enforces regex format `/^[6-9][A-Z0-9_]{1,20}$/i`, checks `UNIQUE(name)` to reject duplicates, and logs to `audit_logs`.
  - Action `delete_class`: Handles deletion by GET or POST, logs details with deleted class name and student count.
- **Batch Checkbox Assignment**:
  - Action `batch_assign`: Selects teacher and THCS subject (covering all 11 GDPT 2018 subjects: Toán, Ngữ Văn, Tiếng Anh, Khoa Học Tự Nhiên, Lịch Sử & Địa Lí, Tin Học, GDCD, Công Nghệ, GD Thể Chất, Nghệ Thuật, Hoạt Động Trải Nghiệm).
  - Renders class chips with checkboxes per grade group.
  - Includes per-grade toggle buttons (`selectGrade(grade, true/false)`) and global toggle buttons (`selectAllGrades(true/false)`), with real-time selection counter (`Đã chọn: X / Y lớp`).
  - Idempotent insert: checks `UNIQUE(teacher_id, class_id, subject)`, inserts new assignments in transaction, reports inserted vs skipped duplicate counts, and logs full details to `audit_logs`.
- **Teacher Account Creation**:
  - Action `create_teacher`: Creates teacher accounts with username validation, checks `UNIQUE(username)` in `users`, generates Bcrypt password hash via `password_hash($password, PASSWORD_BCRYPT)`, inserts with `role = 'teacher'`, and logs to `audit_logs`.
- **Student Roster Import & Deduplication**:
  - Action `import_students`: Ingests `.xlsx` via `parse_xlsx()` or `.csv` via `parse_csv()`.
  - Tolerant header detection (`detect_student_roster_headers()`): scans rows 0 to 4 for field keywords (`họ và tên`, `ngày sinh`, `stt`, `mã hs`, `giới tính`, `sđt`), making imports resilient to school/header metadata rows.
  - Column name fix: maps to `phone` (NOT `parent_phone`), resolving SQLSTATE 42S22.
  - Date normalization: uses `normalize_vn_date()` with `'1970-01-01'` fallback.
  - Dual-layer deduplication: checks `SELECT id FROM students WHERE class_id = ? AND full_name = ? AND birthday = ?`, updates existing record if empty, inserts new records, and catches MySQL 1062 duplicate key violations.
  - Accurate feedback: informs user of inserted and skipped duplicate counts (`"Đã nhập thành công X học sinh vào lớp Y (bỏ qua Z học sinh đã tồn tại trong lớp)"`).
  - Audit log: records `"Admin import học sinh vào lớp [X] từ file [Y]: thêm mới A, trùng lặp bỏ qua B."`
  - Downloadable template links for both `sample_students.xlsx` and `sample_students.csv`.
- **Direct Gradebook Inspection**:
  - Inspection button on each assignment row: opens `nhap_diem.php` with `teacher_id`, `class_id`, and `subject` parameters.
  - Action `impersonate`: logs `Admin giám sát sổ điểm` and redirects to `nhap_diem.php`.
- **Assignment Deletion**:
  - Action `delete_assign`: Supports `$_GET['delete_assign']`, `$_GET['action'] === 'delete_assign'`, and `POST action=delete_assign` for complete backward compatibility with M2 test suites.
- **Real-Time Audit Log Viewer**:
  - Dynamic limit selector: 10, 25, 50, 100 entries via `$_GET['log_limit']`.
  - Color-coded action badges (green for additions/success, blue for assignments/inspections, yellow for unassigns, red for blocks/deletions).
  - Client-side live search filter (`filterAuditLogs()`) filtering across timestamp, user, action, details, and IP address.

### 1.4 `tests/verify_m3.php` (Created)
- **Purpose**: Repeatable automated CLI test suite running against live XAMPP Apache (`http://localhost/my-web-project/`) and live MariaDB (`edu_radar`).
- **Coverage**: 28 automated tests across 6 groups:
  - Group 1 (5 tests): OpenXML / CSV helper functions, ZIP package generation, round-trip text preservation, BOM stripping, sample template availability.
  - Group 2 (4 tests): Class matrix grouping, dynamic class creation, duplicate class rejection, class deletion & audit log.
  - Group 3 (5 tests): Teacher creation with Bcrypt, duplicate username rejection, batch assignment across grades, re-submission duplicate prevention, assignment deletion.
  - Group 4 (6 tests): CSV import, CSV re-import deduplication, XLSX import with Vietnamese & serial dates, XLSX re-import deduplication, tolerant header parsing on row 3, DB unique constraint enforcement.
  - Group 5 (3 tests): Admin direct gradebook inspection (HTTP 200), teacher POST to admin.php blocked (HTTP 403), unauthenticated access blocked (HTTP 403/302).
  - Group 6 (5 tests): Audit logging for student import, batch assignment, teacher creation, class management, and admin UI audit table rendering.

---

## 2. Verification Summary

| Test Suite | Command | Result | Pass Rate |
|---|---|---|---|
| M3 Verification | `& "D:\xam\php\php.exe" tests/verify_m3.php` | 28 / 28 PASS | 100% |
| M1 Verification | `& "D:\xam\php\php.exe" tests/verify_m1.php` | 12 / 12 PASS | 100% |
| M2 Verification | `& "D:\xam\php\php.exe" tests/verify_m2.php` | 16 / 16 PASS | 100% |
| M2 RBAC Challenges | `& "D:\xam\php\php.exe" tests/challenge_m2_rbac.php` | 37 / 37 PASS | 100% |
