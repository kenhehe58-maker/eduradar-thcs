# Project: EduRadar THCS Gradebook & Early Warning

## Architecture
- **Web Application Stack**: Plain PHP 8.0 on Apache 2.4 (XAMPP at `D:\xam`, URL: `http://localhost/my-web-project/`).
- **Database Engine**: MySQL / MariaDB 10.4 on port 3306 (database `edu_radar`, PDO with prepared statements). Zero SQLite.
- **Excel Engine**: Pure PHP 8.0 OpenXML reader & writer (`ZipArchive` + `SimpleXML`) in `includes/xlsx_helper.php` with zero third-party/Composer dependencies.
- **Access Control & RBAC**:
  - `admin`: School administrator with full access to `admin.php`, class matrix, teacher assignment, student roster import, direct gradebook inspection, and audit viewer.
  - `teacher`: Subject teacher with access to `index.php` (assigned classes only) and `nhap_diem.php` (assigned `class_id` + `subject` pairs only). Strict 403 on unassigned pairs or admin routes.
- **Data Flow**:
  - `login.php` -> authenticates via `password_verify()` -> sets `$_SESSION['user_id']`, `$_SESSION['username']`, `$_SESSION['role']` -> logs to `audit_logs`.
  - `admin.php` -> manages classes, assignments, teacher accounts, imports VnEdu student `.xlsx`/`.csv` into `students` -> logs all actions to `audit_logs`.
  - `nhap_diem.php` -> verifies teacher assignment in `teacher_classes` -> manages scores (tx1..tx4, gk, ck, avg_score) -> supports VnEdu `.xlsx` import/export -> logs saves/imports to `audit_logs`.
  - `index.php` -> calculates early warnings (🔴 Nguy cấp, 🟡 Cần chú ý, 🟢 Ổn định) for assigned classes -> displays filterable dashboard with NO Zalo button.

## Feature Inventory
| # | Feature | Description | Milestone | Source |
|---|---------|-------------|-----------|--------|
| 1 | SQLite Elimination | Delete `database.sqlite` and remove SQLite driver/fallback in `includes/config.php` and `includes/db.php`. | M1 | R1, Survey |
| 2 | MySQL Schema Overhaul & Deduplication | Enforce `UNIQUE(name)` on `classes`, `UNIQUE(teacher_id, class_id, subject)` on `teacher_classes`, `UNIQUE(class_id, full_name, birthday)` on `students`, `UNIQUE(student_id, subject)` on `scores`, `UNIQUE(username)` on `users`. | M1 | R1, R5, Survey |
| 3 | Audit Logging Table & Triggers | Create `audit_logs` in `database.sql` and live MySQL with fields: `id, user_id, username, action, details, ip_address, created_at`. Implement `log_action()` helper. | M1 | R5, Survey |
| 4 | Seed Data & Migration | Update `database.sql` with clean schema, 36 classes (6A1..9C9), valid bcrypt password hashes for `admin`, `gv_toan`, `gv_van` (password `123456`). Apply cleanly to live MySQL. | M1 | R1, R5, Acceptance |
| 5 | Authentication & Backdoor Removal | Remove `|| $password === '123456'` from `login.php`. Strictly use `password_verify()`. Implement secure logout. | M2 | R1, Survey |
| 6 | Strict Server-Side RBAC & Route Guard | Enforce admin-only access on `admin.php` (HTTP 403 / redirect on unauthorized). In `nhap_diem.php`, block unassigned `(class_id, subject)` access for teachers. Log unauthorized access attempts. | M2 | R1, Acceptance |
| 7 | Admin Class Matrix & Management | Grade 6-9 matrix (Section A-C, Class 1-9 e.g. 6A1..9C9). Admin can create or delete classes without duplicate names. | M3 | R2 |
| 8 | Admin Batch Checkbox Assignment | Select teacher + subject, tick checkboxes across matrix (with per-grade select-all). Prevent duplicates (`UNIQUE(teacher_id, class_id, subject)`). View/remove assignments, create teacher accounts. | M3 | R2 |
| 9 | VnEdu Student List Import & Sample File | Admin imports `.xlsx` and `.csv` student roster into selected class. Deduplicates by `UNIQUE(class_id, full_name, birthday)` so importing twice produces 0 duplicates. Downloadable sample `.xlsx`. | M3 | R2, Acceptance |
| 10 | Admin Direct Gradebook Inspection | Admin can click button on any assignment row to open that teacher's gradebook (`nhap_diem.php?class_id=X&subject=Y`). | M3 | R2 |
| 11 | Admin Audit Log Viewer | View paginated/latest actions recorded in `audit_logs` table. | M3 | R2 |
| 12 | VnEdu Gradebook Spreadsheet Table | `nhap_diem.php`: `STT, Họ và tên, Ngày sinh, ĐĐGtx 1..4 (Miệng & 15p), ĐĐGgk (HS 2), ĐĐGck (HS 3), ĐTBmhk`. Tab/Arrow key navigation. Input validation (0.0 to 10.0 or empty). | M4 | R3 |
| 13 | Score Calculation Engine | Auto-calculate `ĐTBmhk` = `(Tổng ĐĐGtx + ĐĐGgk*2 + ĐĐGck*3) / (Số cột ĐĐGtx có điểm + 5)` when GK & CK present, or dynamic weighted average of entered columns, rounded to 1 decimal place. | M4 | R3 |
| 14 | VnEdu Gradebook `.xlsx` Import/Export | Import `.xlsx` and `.csv` gradebook at bottom of `nhap_diem.php`. Upsert on conflict (`UNIQUE(student_id, subject)`). Export current gradebook to standard `.xlsx`. Downloadable sample `.xlsx`. | M4 | R3, Acceptance |
| 15 | Early-Warning Radar Diagnostic Engine | `index.php`: Classify students into 🔴 Nguy cấp, 🟡 Cần chú ý, 🟢 Ổn định with clear Vietnamese diagnostic reasons. Filterable by status and class/subject. | M5 | R4 |
| 16 | Zalo Removal & Dead Code Elimination | Remove Zalo copy button, toast, message generator in `index.php` and `assets/js/main.js`. Remove unused `actionModal` code. | M5 | R4, R6 |
| 17 | SaaS UI Redesign (Stitch UI Style) | Cohesive modern ed-tech UI across `login.php`, `admin.php`, `nhap_diem.php`, `index.php`: `Plus Jakarta Sans`, clean cards, distinct role badges, responsive tables, clear navigation tabs. | M5 | R6 |
| 18 | Automated Multi-Account CLI Test Suite | Create `tests/run_tests.php` runnable via `D:\xam\php\php.exe tests/run_tests.php` testing live Apache (`http://localhost/my-web-project/`) and MySQL (`edu_radar`) covering all 8 criteria with 0 failures. | E2E | Acceptance Criteria |

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| M1 | Database Overhaul & Migration | Features #1, #2, #3, #4 (`database.sql`, live MySQL schema, UNIQUE constraints, audit table, clean seeds, delete `database.sqlite`) | none | DONE (Verified: 12/12 verify_m1, 20/20 challenge_m1, 14/14 security harness, CLEAN audit) |
| M2 | Auth & Strict RBAC | Features #5, #6 (`login.php`, `logout.php`, `includes/auth.php`, password hashing, route guard, BOLA prevention) | M1 | DONE (Verified: 16/16 verify_m2, 12/12 verify_m1, 17/17 challenge_m2, 37/37 challenge_m2_rbac, CLEAN audit) |
| M3 | Admin Module & Student Import | Features #7, #8, #9, #10, #11 (`admin.php`, `includes/xlsx_helper.php`, class matrix, batch assignment, student import & sample `.xlsx`, audit viewer) | M1, M2 | DONE (Verified: 28/28 verify_m3, 39/39 challenge_m3, 49/49 challenge_m3_admin_rbac, CLEAN audit) |
| M4 | VnEdu Gradebook & Spreadsheet | Features #12, #13, #14 (`nhap_diem.php`, keyboard navigation, score calculation, `.xlsx` import/export & sample file) | M1, M2, M3 | DONE (Verified: 39/39 verify_m4, 40/40 challenge_m4_score_calc, 35/35 challenge_m4_excel_bola, 181/181 regressions, CLEAN audit) |
| M5 | Early-Warning Radar & UI Redesign | Features #15, #16, #17 (`index.php`, remove Zalo, Stitch UI styling across all pages, delete dead JS) | M1, M2, M3, M4 | DONE (Verified: 49/49 verify_m5, 42/42 challenge_m5_radar, 24/24 challenge_m5_zalo_ui, 40/40 challenge_m5_js_dom, 308/308 regressions, CLEAN audit) |
| M6 | Final Verification & Audit | Feature #18 (Dual track E2E tests passing 100%, Forensic Audit CLEAN, 0 failures) | M1, M2, M3, M4, M5, E2E | IN_PROGRESS |
| E2E | E2E Testing Track | Design & implement `tests/run_tests.php` with Tiers 1-4 covering all 18 features independently | M1 (schema) | IN_PROGRESS |

## Interface Contracts
### `includes/db.php`
- `function db(): PDO`: Returns singleton PDO connection to MySQL `edu_radar` with `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`, `PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC`. No SQLite fallback.
- `function log_action(?int $user_id, ?string $username, string $action, string $details): void`: Inserts into `audit_logs` (`user_id, username, action, details, ip_address, created_at`).

### `includes/auth.php`
- `function require_login(): void`: Redirects unauthenticated requests to `login.php`.
- `function require_admin(): void`: Requires `$_SESSION['role'] === 'admin'`, logs unauthorized attempts and outputs HTTP 403 / redirects.
- `function verify_teacher_assignment(int $teacher_id, int $class_id, string $subject): bool`: Checks `teacher_classes`.
- `function require_teacher_assignment(int $teacher_id, int $class_id, string $subject): void`: Throws HTTP 403 if teacher is not assigned and logs to `audit_logs`.

### `includes/xlsx_helper.php`
- `function parse_xlsx(string $filepath): array`: Reads `.xlsx` sheets into 2D PHP array using `ZipArchive` and `SimpleXML`.
- `function create_xlsx(array $data, string $sheet_name = 'Sheet1'): string`: Returns binary `.xlsx` string using native `ZipArchive` containing standard OpenXML structure.
- `function parse_csv(string $filepath): array`: Reads CSV with UTF-8 BOM handling into 2D array.

### Grade Calculation Formula
- Inputs: `tx1, tx2, tx3, tx4, gk, ck` (floats or nulls).
- Regular sum: `S_tx = sum(tx_i)`, count: `N_tx = count(tx_i)`.
- If `gk !== null && ck !== null`:
  `avg = (S_tx + 2*gk + 3*ck) / (N_tx + 5)`
- If partially entered (e.g. only tx and gk):
  `avg = (S_tx + 2*(gk ?? 0) + 3*(ck ?? 0)) / (N_tx + (gk !== null ? 2 : 0) + (ck !== null ? 3 : 0))`
- Rounded to 1 decimal place: `round($avg, 1)`.

## Code Layout
- `includes/config.php`: MySQL configuration constants (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `BASE_URL`).
- `includes/db.php`: Database connection and audit logging helper.
- `includes/auth.php`: Authentication and RBAC middleware helpers.
- `includes/xlsx_helper.php`: Zero-dependency OpenXML `.xlsx` parser and builder.
- `includes/header.php`, `includes/footer.php`: Unified Stitch UI shell and navigation.
- `login.php`: Secure login page with role-based redirection.
- `logout.php`: Session destruction and audit logging.
- `admin.php`: Administrative control panel (Class matrix, batch assignments, student import, audit viewer).
- `nhap_diem.php`: VnEdu gradebook spreadsheet with inline calculation and `.xlsx` import/export.
- `index.php`: Early-Warning radar dashboard (Red/Yellow/Green warnings, NO Zalo).
- `sample_students.xlsx`, `sample_gradebook.xlsx`: Downloadable sample template files.
- `database.sql`: Clean MySQL migration script with complete schema, constraints, and seeds.
- `tests/run_tests.php`: Automated CLI multi-account test runner.
