# Changes Log: Milestone 4 Implementation

## Overview
Milestone 4 implements the modern VnEdu 6-column spreadsheet gradebook in `nhap_diem.php` aligning with live MySQL `scores` schema (`tx1..tx4, gk, ck, avg_score`), 2D keyboard ergonomics, client-side validation and real-time average calculation, database transaction persistence, student ownership validation against class ID, RBAC/BOLA protection, gradebook bulk import (.xlsx & .csv) with upsert deduplication, .xlsx export via `includes/xlsx_helper.php`, and a comprehensive automated test runner `tests/verify_m4.php`.

---

## 1. File Modifications

### `nhap_diem.php`
- **Replaced Legacy Schema with Modern VnEdu Columns**: Removed obsolete `m1, m2, p15_1, p15_2, t1_1` queries. Now queries and updates `tx1, tx2, tx3, tx4, gk, ck, avg_score` directly matching live MySQL table `scores`. Supported backward compatibility mapping (`$row['tx1'] ?? $row['m1'] ?? null`, etc.) to prevent regressions against M2 security test suites.
- **11-Column 2-Tier Sticky Header Layout**: Added structured headers for `STT`, `Mã HS` (`s.student_code`), `Họ và Tên Học Sinh` (`s.full_name`), `Ngày Sinh` (`s.birthday` formatted `d/m/Y`), `ĐĐG THƯỜNG XUYÊN` (`TX1..TX4`), `GIỮA KỲ` (HS 2), `CUỐI KỲ` (HS 3), and `ĐTBmhk` (TỰ ĐỘNG).
- **2D Keyboard Navigation**: Score inputs configured with `data-row` and `data-col`. Implemented keystroke navigation: `Enter` / `ArrowDown` moves down column to next student; `ArrowUp` / `Shift+Enter` moves up column; `Tab` / `Shift+Tab` moves across columns horizontally with row wrapping; `Focus` auto-selects cell content.
- **Client-Side Real-Time Validation & Calculation**:
  - Automatically normalizes decimal commas (`,` -> `.`).
  - Flags out-of-range scores (`< 0` or `> 10`) with `.is-invalid` red border, soft red background, and floating tooltip.
  - Automatically recalculates student semester row average in real-time according to Thông tư 22: `avg = round((S_tx + 2*gk + 3*ck)/(N_tx + 5), 1)` or dynamic weighted average.
  - Form submission guard prevents submitting invalid scores.
  - Dirty state tracking badge (`#saveStatusBadge`) toggling between `"✓ Dữ liệu đã lưu"` and `"● Có thay đổi chưa lưu"` with `beforeunload` warning.
- **Database Transaction & Anti-BOLA Student Whitelist in `save_scores`**:
  - Wrapped operations in `$pdo->beginTransaction()`, `$pdo->commit()`, and `$pdo->rollBack()`.
  - Whitelisted student IDs against target `$selectedClassId` using pre-loaded hash map (`array_flip`) to strictly block cross-class student ID injection (such as student 99999).
  - Executed atomic upsert with `INSERT INTO scores (...) VALUES (...) ON DUPLICATE KEY UPDATE ...`.
  - Logged action `'Lưu sổ điểm'` in `audit_logs` with class name, subject, and student count.
- **Gradebook Bulk Import (`import_file` / `import_gradebook`)**:
  - Supports both `.xlsx` (OpenXML binary via `parse_xlsx()`) and `.csv` via `parse_csv()`.
  - Incorporates resilient header detector `detect_gradebook_headers()` scanning rows 0 to 3.
  - 3-step student matching (`student_code`, then `full_name + birthday`, then `full_name`), auto-registering new students in class if absent.
  - Validates score bounds (0.0 to 10.0), calculates `avg_score`, and performs atomic upsert in transaction.
  - Logged action `'Import file điểm'` in `audit_logs`.
- **Gradebook Bulk Export (`export_xlsx`)**:
  - Queries active class roster and scores for selected subject.
  - Builds OpenXML SpreadsheetML package via `create_xlsx()`.
  - Serves attachment with standard Content-Type and ASCII filename `SoDiem_[TenLop]_[MonHoc]_[NamHoc].xlsx`.
  - Logged action `'Xuất file điểm'` in `audit_logs`.
- **Downloadable Sample Links**: Bottom panel provides direct download links for `sample_gradebook.xlsx` and `sample_vnedu.csv`.

### `includes/xlsx_helper.php`
- Added `detect_gradebook_headers(array $rows): array` function to detect Vietnamese THCS gradebook columns across variations (`ĐĐGtx`, `Miệng`, `15P`, `1Tiết`, `GiữaKỳ`, `CuốiKỳ`, `Mã HS`, `Họ và tên`, `Ngày sinh`, etc.) scanning rows 0 to 3 with positional fallback.

### `assets/css/style.css`
- Added spreadsheet container styles (`.spreadsheet-container`, `.spreadsheet-table`, `.col-sticky-stt`, `.col-sticky-name`, `.header-tier-1`, `.header-tier-2`).
- Added color accent themes for groups: Soft Sky Blue (`group-tx`), Soft Amber (`group-gk`), Soft Violet (`group-ck`), and Indigo (`group-avg`).
- Added `.score-input` styling with `.is-invalid` error states, focus glow, and floating tooltip `#scoreTooltip`.
- Added `.avg-badge` grading tiers (`.level-danger`, `.level-warning`, `.level-good`, `.level-excellent`, `.level-empty`).
- Added `.dirty-badge` styling with pulse animation.

---

## 2. New Test Suite Created

### `tests/verify_m4.php`
- Comprehensive automated CLI test suite implementing 10 test groups and 39 assertions:
  1. Gradebook Spreadsheet UI structure and roster display (4 tests).
  2. Score calculation engine under Thông tư 22 and partial weighted average (4 tests).
  3. Keyboard ergonomics, 2D grid attributes, and client constraints (4 tests).
  4. Score persistence via transaction and audit logging (3 tests).
  5. Score sanitization (0.0–10.0) and anti-BOLA student ownership protection (2 tests).
  6. Gradebook bulk import from Excel (.xlsx) with deduplication (5 tests).
  7. Gradebook bulk import from CSV (.csv) with audit logging (4 tests).
  8. Gradebook bulk export to Excel (.xlsx) and sample downloads (5 tests).
  9. Strict RBAC & BOLA defense against unassigned access and IDOR tampering (6 tests).
  10. Audit log forensics verifying action types and metadata completeness (2 tests).

---

## 3. Test Verification Summary
All tests executed against live XAMPP Apache (`http://localhost/my-web-project/`) and MySQL (`edu_radar`):
- `tests/verify_m4.php`: 39/39 PASS (0 failures)
- `tests/verify_m1.php`: 12/12 PASS (0 failures)
- `tests/verify_m2.php`: 16/16 PASS (0 failures)
- `tests/verify_m3.php`: 28/28 PASS (0 failures)
- `tests/challenge_m2_rbac.php`: 37/37 PASS (0 failures)
Total: 132/132 tests passed with 0 failures across the entire suite.
