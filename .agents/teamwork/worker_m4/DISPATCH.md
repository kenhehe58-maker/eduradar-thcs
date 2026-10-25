# DISPATCH — 2026-10-17T10:05:00Z

## Identity
- Role: M4 Implementation Worker
- Working directory: D:\New folder\my-web-project\.agents\teamwork\worker_m4
- Parent: orchestrator_1_gen2 (391812a0-32ec-4263-b097-6162669cc4f7)

## Mission
Implement Milestone 4: Modern VnEdu Spreadsheet Gradebook in `nhap_diem.php` with 6 score columns (`tx1..tx4, gk, ck, avg_score`), 2D keyboard navigation, real-time calculation, database transactions, Excel (.xlsx) & CSV bulk import/export via `includes/xlsx_helper.php`, and create automated test suite `tests/verify_m4.php`.

## Mandatory Reading (Authoritative Sources)
1. `D:\New folder\my-web-project\.agents\teamwork\ORIGINAL_REQUEST.md` (Read FIRST)
2. `D:\New folder\my-web-project\.agents\teamwork\PROJECT.md` (Milestone 4, Features #12, #13, #14)
3. Explorer Reports:
   - `D:\New folder\my-web-project\.agents\teamwork\explorer_m4_1\handoff.md` and `analysis.md`
   - `D:\New folder\my-web-project\.agents\teamwork\explorer_m4_2\handoff.md` and `analysis.md`
   - `D:\New folder\my-web-project\.agents\teamwork\explorer_m4_3\handoff.md` and `analysis.md`

## MANDATORY INTEGRITY WARNING
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A teamwork_preview_auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

## Technical Implementation Tasks
1. **Overhaul `nhap_diem.php`**:
   - Align all database queries and schema with live MySQL `scores` table: use `tx1, tx2, tx3, tx4, gk, ck, avg_score` (completely remove legacy `m1..t1_1`, but support fallback `$row['tx1'] ?? $row['m1'] ?? null` for backward compatibility with M2 tests).
   - Display student identity: `STT`, `Mã HS` (`s.student_code`), `Họ và tên` (`s.full_name`), `Ngày sinh` (`s.birthday` formatted `d/m/Y`).
   - Implement 11-column 2-tier sticky header layout with distinct color groupings (TX Soft Sky Blue, GK Soft Amber, CK Soft Violet, ĐTBmhk Indigo).
   - Upgrade score inputs to `<input type="text" inputmode="decimal">` with `data-row` and `data-col` attributes.
   - Implement 2D keyboard ergonomics: `Enter` / `ArrowDown` moves down column to next student; `ArrowUp` moves up; `Tab` / `Shift+Tab` moves horizontally; `Focus` auto-selects.
   - Implement real-time client-side validation: decimal comma normalization (`8,5` -> `8.5`), out-of-range (`< 0` or `> 10`) red border + warning tooltip, form submission guard blocking invalid scores.
   - Implement client-side instant row average calculation matching Thông tư 22: `avg = round((sum(tx) + 2*gk + 3*ck) / (count(tx) + 5), 1)` or dynamic weighted average.
   - Implement dirty-state tracking badge ("✓ Đã lưu" vs "● Có thay đổi chưa lưu").
   - Implement server-side score saving in `save_scores` handler:
     - Wrap in `$pdo->beginTransaction()`, `$pdo->commit()`, `$pdo->rollBack()`.
     - Validate student ownership against target `$class_id` using pre-loaded whitelist to block cross-class student ID injection.
     - Atomic upsert: `INSERT INTO scores (student_id, subject, tx1, tx2, tx3, tx4, gk, ck, avg_score, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE tx1=VALUES(tx1), tx2=VALUES(tx2), tx3=VALUES(tx3), tx4=VALUES(tx4), gk=VALUES(gk), ck=VALUES(ck), avg_score=VALUES(avg_score), updated_at=NOW()`.
     - Log action `Lưu sổ điểm` in `audit_logs`.
     - Preserve `require_teacher_assignment()` on GET and POST. Non-admin teachers cannot tamper with `?teacher_id`; admin retains full inspection rights.
   - Implement bottom panel:
     - Gradebook bulk import form supporting `.xlsx` and `.csv` using `includes/xlsx_helper.php`. Resilient header detection (rows 0–3). Atomic upsert into `scores`. Feedback reporting count of updated scores.
     - Gradebook export button downloading `SoDiem_[TenLop]_[MonHoc]_[NamHoc].xlsx` generated via `create_xlsx()`.
     - Direct download links for `sample_gradebook.xlsx` and `sample_vnedu.csv`.
2. **Create `tests/verify_m4.php`**:
   - Implement comprehensive automated CLI test runner covering all 10 test groups specified by `explorer_m4_3`.
   - Run via `& "D:\xam\php\php.exe" "D:\New folder\my-web-project\tests\verify_m4.php"` and ensure 100% pass (0 failures).
3. **Execute All Regression Suites**:
   - `& "D:\xam\php\php.exe" "D:\New folder\my-web-project\tests\verify_m1.php"` (12/12 PASS)
   - `& "D:\xam\php\php.exe" "D:\New folder\my-web-project\tests\verify_m2.php"` (16/16 PASS)
   - `& "D:\xam\php\php.exe" "D:\New folder\my-web-project\tests\verify_m3.php"` (28/28 PASS)
   - `& "D:\xam\php\php.exe" "D:\New folder\my-web-project\tests\challenge_m2_rbac.php"` (37/37 PASS)

## Output
Write `changes.md` and `handoff.md` in your working directory. Send a message to your parent when done.
