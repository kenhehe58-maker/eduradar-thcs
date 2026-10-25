# DISPATCH — 2026-10-17T08:45:00Z

## Identity
- Role: M3 Class Matrix & Batch Assignment Explorer
- Working directory: D:\New folder\my-web-project\.agents\teamwork\explorer_m3_2
- Parent: orchestrator_1_gen2 (391812a0-32ec-4263-b097-6162669cc4f7)

## Mission
Investigate and design the overhaul of `admin.php` for Class Matrix (Grades 6–9, Sections A–C, Classes 1–9), batch checkbox assignment with per-grade select-all, teacher creation, direct gradebook inspection button, and real-time audit log viewer.

## Required Reading (Authoritative Sources)
1. `D:\New folder\my-web-project\.agents\teamwork\ORIGINAL_REQUEST.md` (Read FIRST)
2. `D:\New folder\my-web-project\.agents\teamwork\PROJECT.md` (Features #7, #8, #10, #11)
3. `D:\New folder\my-web-project\admin.php`
4. `D:\New folder\my-web-project\database.sql`

## Technical Scope
1. Full Secondary School Class Matrix:
   - Grades 6 to 9, Sections A to C, Classes 1 to 9 (e.g. 6A1..6A3, 7A1..7B2, 8A1..8C3, 9A1..9C3).
   - Display existing classes grouped by grade with student count badge.
   - Dynamic class creation and deletion actions.
2. Batch Checkbox Assignment:
   - Clean grid matrix of classes by grade.
   - Per-grade "Select All" toggle buttons ("Chọn tất cả Khối 6", "Khối 7", "Khối 8", "Khối 9", and "Bỏ chọn").
   - Dropdown for Teacher selection (from `users` where `role = 'teacher'`).
   - Subject input/dropdown (Toán, Ngữ Văn, Tiếng Anh, Khoa Học Tự Nhiên, Lịch Sử & Địa Lí, Tin Học, GDCD...).
   - Batch insert into `teacher_classes` (`INSERT IGNORE INTO teacher_classes (teacher_id, class_id, subject)`).
3. Assignment List:
   - Table of existing assignments with search/filter, teacher name, class name, subject, and unassign/delete button.
4. Teacher Account Creation:
   - Form to create teacher accounts with bcrypt password hash.
5. Direct Gradebook Inspection:
   - Action button/link to inspect any class & subject gradebook in `nhap_diem.php?class_id=X&subject=Y`.
6. Audit Log Viewer:
   - Display recent records from `audit_logs` with action, details, IP, timestamp, and user.

## Output
Write `analysis.md` and `handoff.md` in your working directory. Send a message to your parent when done.
