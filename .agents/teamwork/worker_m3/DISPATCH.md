# DISPATCH — 2026-10-17T09:05:00Z

## Identity
- Role: M3 Implementation Worker
- Working directory: D:\New folder\my-web-project\.agents\teamwork\worker_m3
- Parent: orchestrator_1_gen2 (391812a0-32ec-4263-b097-6162669cc4f7)

## Mission
Implement Milestone 3: Admin Class Matrix, Batch Assignment, Zero-Dependency OpenXML Helper, and VnEdu Student Roster Import with Database Deduplication.

## Mandatory Reading (Authoritative Sources)
1. `D:\New folder\my-web-project\.agents\teamwork\ORIGINAL_REQUEST.md` (Read FIRST)
2. `D:\New folder\my-web-project\.agents\teamwork\PROJECT.md` (Milestone 3, Features #7 to #11)
3. Explorer Reports:
   - `D:\New folder\my-web-project\.agents\teamwork\explorer_m3_1\handoff.md` and `proposed_xlsx_helper.php`
   - `D:\New folder\my-web-project\.agents\teamwork\explorer_m3_2\handoff.md` and `proposed_admin.php`
   - `D:\New folder\my-web-project\.agents\teamwork\explorer_m3_3\handoff.md` and `analysis.md`

## MANDATORY INTEGRITY WARNING
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A teamwork_preview_auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

## Technical Implementation Tasks
1. **Create `includes/xlsx_helper.php`**:
   - Deploy zero-dependency OpenXML (.xlsx) engine using native `ZipArchive` and `SimpleXML`.
   - Implement `parse_xlsx()`, `create_xlsx()`, `parse_csv()`, `normalize_excel_date()`.
   - Incorporate `explorer_m3_1`'s verified implementation.
2. **Generate Root Template Files**:
   - Generate `sample_students.xlsx` and `sample_gradebook.xlsx` at project root `D:\New folder\my-web-project\`.
3. **Overhaul `admin.php`**:
   - Maintain strict `require_admin()` at top and inside all action handlers.
   - Integrate Class Matrix (Grades 6–9, Sections A–C, Classes 1–9) with student count badges `[X HS]`.
   - Implement dynamic `create_class` and `delete_class` handlers.
   - Implement Batch Checkbox Assignment with per-grade select-all buttons and comprehensive THCS subjects list.
   - Implement `create_teacher` account creation with bcrypt password hash.
   - Implement direct gradebook inspection button/link (`nhap_diem.php?teacher_id=...&class_id=...&subject=...`).
   - Integrate student roster import handler using `includes/xlsx_helper.php` (.xlsx and .csv), fixing the column name to `phone` (NOT `parent_phone`), with resilient header detection (rows 0–4), date normalization (`normalize_vn_date()` with `'1970-01-01'` fallback), and dual-layer deduplication.
   - Report granular feedback: exact counts of inserted vs duplicate students.
   - Implement Real-Time Audit Log Viewer with limit selector (10, 25, 50, 100) and live filter.
   - Support both GET and POST for `delete_assign` for backward compatibility with M2 tests.
4. **Create `tests/verify_m3.php`**:
   - Implement comprehensive automated CLI test runner covering all 25 test cases specified by `explorer_m3_3` across 6 groups.
   - Execute via `& "D:\xam\php\php.exe" "D:\New folder\my-web-project\tests\verify_m3.php"` and ensure 100% pass (25/25 PASS, 0 failures).
5. **Run Regression Suites**:
   - `& "D:\xam\php\php.exe" "D:\New folder\my-web-project\tests\verify_m1.php"` (12/12 PASS)
   - `& "D:\xam\php\php.exe" "D:\New folder\my-web-project\tests\verify_m2.php"` (16/16 PASS)
   - `& "D:\xam\php\php.exe" "D:\New folder\my-web-project\tests\challenge_m2_rbac.php"` (37/37 PASS)

## Output
Write `changes.md` and `handoff.md` in your working directory. Send a message to your parent when done.

## 2026-10-17T09:05:48Z
You are worker_m3. Working directory: D:\New folder\my-web-project\.agents\teamwork\worker_m3.
Read D:\New folder\my-web-project\.agents\teamwork\ORIGINAL_REQUEST.md, PROJECT.md, and DISPATCH.md in your working directory.
Also read handoffs from explorer_m3_1, explorer_m3_2, and explorer_m3_3:
- D:\New folder\my-web-project\.agents\teamwork\explorer_m3_1\handoff.md and proposed_xlsx_helper.php
- D:\New folder\my-web-project\.agents\teamwork\explorer_m3_2\handoff.md and proposed_admin.php
- D:\New folder\my-web-project\.agents\teamwork\explorer_m3_3\handoff.md and analysis.md

MANDATORY INTEGRITY WARNING:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A teamwork_preview_auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

Implement Milestone 3:
1. Create includes/xlsx_helper.php with zero-dependency OpenXML (.xlsx) and CSV parsing/creation using native ZipArchive and SimpleXML.
2. Generate sample_students.xlsx and sample_gradebook.xlsx at project root.
3. Overhaul admin.php with Class Matrix (Grades 6–9, Sections A–C, Classes 1–9), dynamic class creation/deletion, batch checkbox assignment with per-grade select-all, teacher account creation with bcrypt, direct gradebook inspection button, student roster import (.xlsx/.csv) with phone column fix and dual-layer deduplication, and real-time audit log viewer with live search. Support both GET and POST for delete_assign for backward compatibility.
4. Create tests/verify_m3.php implementing all 25 test cases across 6 groups and run with D:\xam\php\php.exe ensuring 100% pass (0 failures).
5. Run regression tests: tests/verify_m1.php, tests/verify_m2.php, tests/challenge_m2_rbac.php.
6. Write changes.md and handoff.md in your working directory, then send a message when done.
