# Original User Request

## 2026-10-17T05:12:37Z

Audit, clean up, and rebuild an existing PHP + MySQL web app that helps Vietnamese lower-secondary (THCS) teachers manage student grades and early warnings, running directly on XAMPP Apache (`http://localhost/my-web-project/`) and MySQL (`edu_radar` via phpMyAdmin). Every requirement must strictly match what the user asked for — no unrequested features, no duplicate data, clear separation of responsibilities, and verified by automated multi-account tests.

Working directory: D:\New folder\my-web-project
Integrity mode: development

## Context & Running Environment
- **XAMPP is actively running** at `D:\xam`:
  - **Apache** is running on port 80 (`http://localhost/my-web-project/` via junction `D:\xam\htdocs\my-web-project` -> `D:\New folder\my-web-project`). Do NOT rely on a separate standalone server on port 8000; test and serve directly via XAMPP Apache (`http://localhost/my-web-project/`) using XAMPP's PHP (`D:\xam\php\php.exe`).
  - **MySQL (MariaDB 10.4)** is running on port 3306 (`127.0.0.1`, user `root`, empty password), managed via phpMyAdmin (`http://localhost/phpmyadmin/index.php?route=/database/structure&db=edu_radar`).
- **Database state (`edu_radar`)**: Currently has 6 tables (`audit_logs`, `classes`, `scores`, `students`, `teacher_classes`, `users`). Clean up any duplicate rows, enforce proper `UNIQUE` constraints and foreign keys so re-importing or re-submitting never creates duplicate data, and ensure each table has a clear, single responsibility.

## Requirements

### R1. Accounts, Roles, and Strict Access Control (MySQL Only)
- Plain PHP + MySQL (`edu_radar`), PDO with prepared statements only. Remove all SQLite code and files (`database.sqlite`).
- Two roles: `admin` (school administrator) and `teacher` (subject teacher; username can be their VnEdu username). Passwords hashed with `password_hash()` (remove any hardcoded password bypass).
- **Strict server-side access control**:
  - Only `role === 'admin'` can access `admin.php` or trigger any admin action. Teachers or unauthenticated users get HTTP 403 / redirect with zero database changes.
  - A teacher can ONLY view and edit grades for the exact `(class_id, subject)` pairs assigned to them in `teacher_classes`. Direct URL tampering or forged POSTs for unassigned classes or subjects must be rejected server-side.

### R2. Admin Page (`admin.php` — Separate Page)
- **Class Matrix & Management**: Displays all classes organized clearly by Grade (`6`, `7`, `8`, `9`), Section (`A`, `B`, `C`), and Class Number (`1`–`9`, e.g., `6A1`…`9C9`). Admin can create or delete classes without creating duplicates (`UNIQUE(name)`).
- **Batch Checkbox Assignment**: Admin selects a teacher + a subject, then ticks checkboxes across the class matrix (with per-grade select-all support) to assign classes. Duplicate assignments are prevented (`UNIQUE(teacher_id, class_id, subject)`). Admin can also view and remove existing assignments, and create new teacher accounts.
- **Student List Import (`.xlsx` & `.csv` VnEdu Standard)**: Admin selects a target class and uploads an Excel `.xlsx` (or `.csv`) file in standard VnEdu student export format (STT, Mã HS / Họ và tên, Ngày sinh, Giới tính / SĐT phụ huynh) to populate students into that class. Must deduplicate (`UNIQUE` or upsert by student code/name + birthday within the class) so importing the same file twice never duplicates students. Provide a downloadable sample `.xlsx` file.
- **Direct Gradebook Inspection**: In the assignments table, Admin has a button on each row to open and inspect/edit that specific teacher's gradebook (`nhap_diem.php`) for that class and subject.
- **Audit Log Viewer**: Displays the latest system actions recorded in `audit_logs`.

### R3. Gradebook Page (`nhap_diem.php` — Separate Page, VnEdu Standard)
- Teacher selects from their assigned `(Lớp, Môn)` pairs (Admin can switch across all classes and subjects).
- Displays a spreadsheet-like table modeled after **VnEdu**:
  - Columns: `STT`, `Họ và tên`, `Ngày sinh`, **`ĐĐGtx` (Điểm đánh giá thường xuyên / Miệng & 15 phút: cột 1, 2, 3, 4)**, **`ĐĐGgk` (Giữa kỳ - Hệ số 2)**, **`ĐĐGck` (Cuối kỳ - Hệ số 3)**, and **`ĐTBmhk` (Điểm trung bình môn học kỳ)**.
  - Automatic average calculation (`ĐTBmhk` = `(Tổng ĐĐGtx + ĐĐGgk*2 + ĐĐGck*3) / (Số cột ĐĐGtx có điểm + 5)` when GK & CK are present, or weighted average of entered columns, rounded to 1 decimal place).
  - Keyboard navigation (Tab / Arrow keys) across cells; strict validation (`0.0` to `10.0` or empty).
- **VnEdu `.xlsx` File Import & Export at Bottom of Page**:
  - Teachers can upload a standard **VnEdu `.xlsx` grade sheet** (and `.csv`) at the bottom of the page to bulk-import student info and grades into the current `(class, subject)` without duplicating students or score rows (`UNIQUE(student_id, subject)` — upsert on conflict).
  - Provide a downloadable sample VnEdu `.xlsx` gradebook file so the user can test immediately.

### R4. Early-Warning Radar (`index.php` — Warning Only, NO Zalo Button)
- Displays early-warning analytics for the logged-in teacher's assigned classes/subjects (or all classes for Admin):
  - Summary counters and filterable table of students classified into **🔴 Nguy cấp (Red)**, **🟡 Cần chú ý (Yellow)**, and **🟢 Ổn định (Green)** based on their VnEdu grade columns (e.g., average < 5.0, consecutive drop across regular/periodic tests, or component score < 5.0), clearly stating the specific diagnostic reason and which subject/class.
- **DO NOT include the Zalo copy button or parent message generator** (the user explicitly asked to hold off on the Zalo button).

### R5. Complete Database Audit Logging & Data Integrity
- Every state-changing action (login success, login failure, logout, unauthorized access attempt, create/delete class, create teacher, assign/unassign teacher, import students `.xlsx`, save grades, import grades `.xlsx`, admin inspecting gradebook) must insert a record into `audit_logs` in MySQL (`user_id`, `username`, `action`, `details`, `ip_address`, `created_at`).
- Clear separation of duties and zero duplicate data in MySQL:
  - `users`: accounts & roles (`UNIQUE(username)`).
  - `classes`: grade 6–9 class definitions (`UNIQUE(name)`).
  - `teacher_classes`: which teacher teaches which subject in which class (`UNIQUE(teacher_id, class_id, subject)`).
  - `students`: student roster per class (`UNIQUE(class_id, full_name, birthday)`).
  - `scores`: VnEdu grade columns per student per subject (`UNIQUE(student_id, subject)`).
  - `audit_logs`: immutable log of every action.

### R6. Complete UI Redesign (Stitch UI Style) & Cleanup
- Redesign the entire frontend across `login.php`, `admin.php`, `nhap_diem.php`, and `index.php` with a cohesive, high-craft SaaS UI (inspired by Stitch UI / Linear / modern ed-tech dashboards): crisp typography (`Plus Jakarta Sans` / `Inter`), clear visual hierarchy, distinct role badges, responsive tables, clear navigation tabs showing only what the current role can access, and instant toast/alert feedback on every action.
- Remove all unrequested features, dead links, modals, Zalo buttons, SQLite files, and leftover test scripts.

## Acceptance Criteria

### Automated Multi-Account & Deduplication Verification
- [ ] A repeatable PHP CLI test suite (`tests/run_tests.php`, runnable via `D:\xam\php\php.exe tests/run_tests.php`) that tests against the live XAMPP Apache server (`http://localhost/my-web-project/`) and live MySQL (`edu_radar`):
  - [ ] Verifies login for `admin`, `gv_toan`, `gv_van` (password `123456`) and rejects wrong passwords (`1234567`, etc.).
  - [ ] Verifies `gv_toan` and unauthenticated sessions receive HTTP 403/redirect on `admin.php` and cannot execute admin POST actions.
  - [ ] Verifies Admin checkbox batch assignment creates rows in `teacher_classes`, and submitting the exact same assignment twice does NOT create duplicate rows.
  - [ ] Verifies Admin `.xlsx` student import populates `students`, and importing the same `.xlsx` file a second time results in 0 duplicate students.
  - [ ] Verifies `gv_toan` saving grades in `nhap_diem.php` updates `scores` in MySQL with accurate `avg_score`, and `gv_toan` cannot view or POST grades to a class or subject not assigned to `gv_toan`.
  - [ ] Verifies `.xlsx` VnEdu gradebook import in `nhap_diem.php` reads real `.xlsx` binary (ZIP/XML) files and upserts grades into MySQL without duplicating students or scores.
  - [ ] Verifies `index.php` displays accurate Red / Yellow / Green warning flags and contains NO Zalo button.
  - [ ] Verifies every tested action above wrote a corresponding row to `audit_logs` in MySQL.
  - [ ] Final test run outputs 0 failures.

### Database & Handover Cleanliness
- [ ] `database.sql` is updated to match the exact schema, constraints, and clean seed data, and is freshly imported into `edu_radar` on XAMPP MySQL.
- [ ] No `database.sqlite` file exists; no broken buttons or unrequested features exist on any page.
