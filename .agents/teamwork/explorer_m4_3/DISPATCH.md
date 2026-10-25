# DISPATCH — 2026-10-17T09:55:00Z

## Identity
- Role: M4 Excel Import/Export & Test Suite Explorer
- Working directory: D:\New folder\my-web-project\.agents\teamwork\explorer_m4_3
- Parent: orchestrator_1_gen2 (391812a0-32ec-4263-b097-6162669cc4f7)

## Mission
Investigate and design the VnEdu gradebook Excel (.xlsx) & CSV import/export engine at the bottom of `nhap_diem.php` using `includes/xlsx_helper.php`, and design the automated verification test suite `tests/verify_m4.php`.

## Mandatory Reading (Authoritative Sources)
1. `D:\New folder\my-web-project\.agents\teamwork\ORIGINAL_REQUEST.md` (Read FIRST)
2. `D:\New folder\my-web-project\.agents\teamwork\PROJECT.md` (Milestone 4, Features #12, #13, #14)
3. `D:\New folder\my-web-project\includes\xlsx_helper.php`
4. `D:\New folder\my-web-project\sample_gradebook.xlsx` and `sample_vnedu.csv`

## Technical Scope
1. **Gradebook Excel/CSV Bulk Import**:
   - Location: Dedicated card/section at bottom of `nhap_diem.php`.
   - Supports `.xlsx` and `.csv` files.
   - Header detection (tolerant to rows 0–3): detects `STT, Mã HS, Họ và tên, Ngày sinh, TX1..TX4 (or ĐĐGtx1..4), GK (ĐĐGgk), CK (ĐĐGck)`.
   - Matching student: matches by `student_code` or `(class_id, full_name, birthday)`.
   - Upsert logic: updates existing score row in `scores` (`UNIQUE(student_id, subject)`), calculates `avg_score`, and updates `updated_at`.
   - Feedback: displays exact count of imported/updated scores.
2. **Gradebook Excel Export**:
   - Button: "Xuất Excel Sổ Điểm" in `nhap_diem.php`.
   - Uses `create_xlsx()` from `includes/xlsx_helper.php`.
   - Output filename: `SoDiem_[TenLop]_[MonHoc]_[NamHoc].xlsx`.
   - Direct download button for standard `sample_gradebook.xlsx`.
3. **Specification of Automated Test Suite (`tests/verify_m4.php`)**:
   - Complete automated CLI test runner via `D:\xam\php\php.exe tests/verify_m4.php`:
     - Test 1: Gradebook page structure (6 score columns, header titles, input fields).
     - Test 2: Calculation engine (full formula with GK & CK, dynamic weighted average for partial, 1 decimal rounding).
     - Test 3: Keyboard navigation JS bindings and validation attributes.
     - Test 4: Score saving POST with transaction, audit log entry, and DB score verification.
     - Test 5: Out-of-bounds score rejection (`< 0` or `> 10`).
     - Test 6: Bulk gradebook Excel import (.xlsx) with upsert.
     - Test 7: Bulk gradebook CSV import (.csv).
     - Test 8: Gradebook Excel export (returns valid OpenXML ZIP package with matching rows).
     - Test 9: RBAC & BOLA defense: unauthenticated and unassigned teachers blocked on GET, POST, import, and export (HTTP 403 / 302). Admin retains inspection rights.
     - Test 10: Audit logging for all M4 actions.

## Output
Write `analysis.md` and `handoff.md` in your working directory. Send a message to your parent when done.
