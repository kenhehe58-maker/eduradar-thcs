# DISPATCH — 2026-10-17T08:45:00Z

## Identity
- Role: M3 Student Import & Dedup Explorer
- Working directory: D:\New folder\my-web-project\.agents\teamwork\explorer_m3_3
- Parent: orchestrator_1_gen2 (391812a0-32ec-4263-b097-6162669cc4f7)

## Mission
Investigate and design the VnEdu student roster import (`.xlsx` and `.csv`) with database deduplication on `(class_id, full_name, birthday)`, audit logging, feedback reporting, and design the automated verification test suite `tests/verify_m3.php`.

## Required Reading (Authoritative Sources)
1. `D:\New folder\my-web-project\.agents\teamwork\ORIGINAL_REQUEST.md` (Read FIRST)
2. `D:\New folder\my-web-project\.agents\teamwork\PROJECT.md` (Features #8, #9, #10, #11)
3. `D:\New folder\my-web-project\admin.php`
4. `D:\New folder\my-web-project\sample_students.csv`
5. `D:\New folder\my-web-project\tests\verify_m2.php` (as pattern reference for verification suites)

## Technical Scope
1. VnEdu Student Roster Upload Handling:
   - Accept `.xlsx` and `.csv` files.
   - Target class selection dropdown.
   - Header row detection (tolerant to header rows on line 1, 2, or 3).
   - Column mapping for `STT`, `Họ và tên` (or `Họ tên`), `Ngày sinh`, `Giới tính`.
   - Date format normalization (`DD/MM/YYYY`, `DD-MM-YYYY`, `YYYY-MM-DD`, or Excel serial date numbers to `YYYY-MM-DD`).
2. Deduplication Engine:
   - Database constraint: `UNIQUE(class_id, full_name, birthday)` in MySQL `students` table.
   - In MySQL, `NULL` values in composite unique keys are not treated as equal; therefore, enforce non-empty birthday or default placeholder so deduplication is strictly enforced.
   - Query existing students in target class before insert: if matching `(class_id, full_name, birthday)` exists, skip or update gender, do NOT insert duplicate row.
   - User feedback: display explicit message, e.g., "Nhập thành công X học sinh (bỏ qua Y học sinh đã tồn tại trong lớp)".
3. Audit Logging:
   - Log student import event to `audit_logs` with class name, file name, inserted count, and duplicate count.
4. Specification of `tests/verify_m3.php`:
   - Design automated CLI verification suite to be executed with `D:\xam\php\php.exe tests/verify_m3.php`:
     - Test `parse_xlsx` and `create_xlsx` with native ZipArchive/SimpleXML.
     - Test `sample_students.xlsx` download / existence.
     - Test class creation and class matrix queries.
     - Test batch checkbox assignment with select-all simulation.
     - Test teacher account creation.
     - Test student `.csv` and `.xlsx` upload to class with deduplication on re-import (verify student count remains constant and duplicate count reported).
     - Test audit log recording for M3 actions.

## Output
Write `analysis.md` and `handoff.md` in your working directory. Send a message to your parent when done.
