# Review Report — Milestone 1: Database Overhaul & Foundation

**Reviewer**: `reviewer_m1_1` (teamwork_preview_reviewer)  
**Roles**: Reviewer, Adversarial Critic  
**Date**: 2026-10-17  
**Working Directory**: `D:\New folder\my-web-project\.agents\teamwork\reviewer_m1_1`  
**Milestone Reviewed**: Milestone 1 (Database Overhaul & Data Access Layer)  
**Reviewed Artifacts**:
- `database.sql`
- `includes/config.php`
- `includes/db.php`
- `tests/verify_m1.php`
- Live MariaDB database `edu_radar`

---

## 1. Review Summary

**Verdict**: **APPROVE**

Milestone 1 satisfies all functional, architectural, and security requirements specified in `ORIGINAL_REQUEST.md` (R1, R5) and `PROJECT.md` (Milestone 1, Features #1–#4).

1. **Schema & Engine Integrity**: All 6 tables (`users`, `classes`, `teacher_classes`, `students`, `scores`, `audit_logs`) are created with `ENGINE=InnoDB` and `DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`.
2. **Business Key Deduplication**: Mandatory `UNIQUE` constraints are strictly declared and enforced:
   - `users`: `UNIQUE KEY unique_username (username)`
   - `classes`: `UNIQUE KEY unique_class_name (name)`
   - `teacher_classes`: `UNIQUE KEY unique_assignment (teacher_id, class_id, subject)`
   - `students`: `UNIQUE KEY unique_class_student (class_id, full_name, birthday)`
   - `scores`: `UNIQUE KEY unique_student_subject (student_id, subject)`
3. **Referential Integrity**: All 4 foreign keys (`fk_tc_teacher`, `fk_tc_class`, `fk_students_class`, `fk_scores_student`) declare `ON DELETE CASCADE`. Deep cascading deletions were verified live.
4. **Modern VnEdu Score Structure**: `scores` table contains exact MOET Thông tư 22 columns: `tx1`, `tx2`, `tx3`, `tx4`, `gk`, `ck`, `avg_score`, and `updated_at`.
5. **Clean Seed Data & Deduplication**: Exactly 0 duplicate rows exist across all tables in MariaDB. 36 standard THCS classes (`6A1`–`9C9`) across Grades 6–9 and Sections A–C are seeded.
6. **Pure MySQL PDO**: `includes/config.php` and `includes/db.php` provide a pure PDO MySQL singleton (`ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `EMULATE_PREPARES => false`, `utf8mb4`). `database.sqlite` is completely eliminated, and zero active SQLite code exists in the codebase.
7. **Audit Logging Contract**: `log_action(?int $user_id, ?string $username, string $action, string $details = ''): void` is implemented with parameterized queries, client IP extraction, and graceful failure isolation. The backward-compatibility wrapper `logAction()` ensures existing call sites in other pages remain functional.
8. **Automated Verification & Stress Testing**:
   - `tests/verify_m1.php`: 12/12 passed (0 failures).
   - `tests/challenge_m1.php`: 20/20 passed (0 failures).
   - `tests/challenge_m1_harness.php`: 14/14 passed (0 failures).
   - Integrity audit: 0 violations detected (no hardcoded outputs, no facades, no bypasses).

---

## 2. Integrity Audit

Under the reviewer and adversarial critic integrity protocol, the following checks were performed:

| Integrity Check Item | Assessment | Evidence |
|----------------------|------------|----------|
| Hardcoded test results or expected outputs in source code | **PASS** | `db()` establishes a live connection via `new PDO($dsn, ...)`. `log_action()` issues real `INSERT INTO audit_logs` with parameterized bindings. |
| Dummy or facade implementations | **PASS** | `database.sql` defines complete DDL with indices, types, constraints, and cascading rules matching production requirements. |
| Shortcuts bypassing intended task | **PASS** | SQLite driver references and `database.sqlite` were deleted from the repository. No SQLite fallback exists. |
| Fabricated verification outputs or logs | **PASS** | All verification runs (`verify_m1.php`, `challenge_m1.php`, `challenge_m1_harness.php`) were executed in real time via PHP CLI and inspected in MariaDB. |
| Self-certifying work without genuine verification | **PASS** | Independent direct SQL queries against `information_schema` and MariaDB engine confirmed all claims. |

**Integrity Conclusion**: No integrity violations found.

---

## 3. Findings

### [Minor] Finding 1: Handling NULL `birthday` in `unique_class_student`
- **What**: In standard SQL and MariaDB InnoDB, `NULL` values in UNIQUE indexes are treated as distinct (`NULL != NULL`).
- **Where**: `database.sql:75` (`UNIQUE KEY unique_class_student (class_id, full_name, birthday)`) and `students` table.
- **Why**: If a student is inserted with `birthday IS NULL`, MariaDB allows multiple rows with the exact same `class_id` and `full_name`.
- **Downstream Impact**: In Milestone 3 (Student Import), the application layer in `admin.php` should require `birthday` to be provided or explicitly coalesce null birthdays to ensure full deduplication during Excel/CSV imports.
- **Recommendation**: Accept risk for M1 database foundation as this is standard SQL behavior; implement non-null validation in Milestone 3 importer.

### [Minor] Finding 2: PowerShell UTF-8 CLI Import Hygiene
- **What**: When importing `.sql` files into MariaDB via Windows PowerShell 5.1, piping `Get-Content ... | mysql.exe` uses default console output encoding (often ISO-8859-1/ASCII), which can replace non-ASCII UTF-8 characters with literal `?`.
- **Where**: Migration tooling / command line documentation.
- **Why**: Vietnamese diacritics must be preserved as multi-byte UTF-8 in MariaDB.
- **Verification**: The live database `edu_radar` was verified using `HEX()` queries on `users.full_name`, `teacher_classes.subject`, `students.full_name`, and `scores.subject`. All characters are valid multi-byte UTF-8 (e.g., `Toán` is `546FC3A16E`, `Ngữ Văn` is `4E67E1BBAF2056C4836E`).
- **Recommendation**: Ensure CLI migration instructions advise using `mysql.exe -e "source path/to/database.sql"` or `$OutputEncoding = [System.Text.Encoding]::UTF8`.

---

## 4. Verified Claims

1. **SQLite Elimination**:
   - `Test-Path "D:\New folder\my-web-project\database.sqlite"` -> `False` (Verified).
   - `includes/config.php` and `includes/db.php` -> Zero SQLite constants or functions (Verified).
   - Deep grep across codebase -> Zero active SQLite driver invocations (Verified).
2. **Schema & Tables**:
   - `information_schema.TABLES` contains all 6 tables: `users`, `classes`, `teacher_classes`, `students`, `scores`, `audit_logs` (Verified).
   - All tables use `InnoDB` engine with `utf8mb4_unicode_ci` (Verified).
3. **UNIQUE Constraints**:
   - `users(username)` -> `unique_username` (Verified).
   - `classes(name)` -> `unique_class_name` (Verified).
   - `teacher_classes(teacher_id, class_id, subject)` -> `unique_assignment` (Verified).
   - `students(class_id, full_name, birthday)` -> `unique_class_student` (Verified).
   - `scores(student_id, subject)` -> `unique_student_subject` (Verified).
4. **Foreign Keys & Cascade**:
   - `teacher_classes.teacher_id` -> `users.id` (ON DELETE CASCADE) (Verified).
   - `teacher_classes.class_id` -> `classes.id` (ON DELETE CASCADE) (Verified).
   - `students.class_id` -> `classes.id` (ON DELETE CASCADE) (Verified).
   - `scores.student_id` -> `students.id` (ON DELETE CASCADE) (Verified).
5. **Zero Duplicates**:
   - Live MariaDB queries across `users`, `classes`, `teacher_classes`, `students`, `scores` confirm 0 duplicate business keys (Verified).
6. **Class Seed Count**:
   - Exactly 36 classes seeded covering Grades 6–9 and Sections A–C (Verified).
7. **Seed Account Passwords**:
   - `admin`, `gv_toan`, `gv_van` verify cleanly with `'123456'` via `password_verify()` and reject invalid passwords (Verified).
8. **Audit Logging**:
   - `log_action()` writes prepared statements to `audit_logs` capturing client IP, action, details, and timestamps (Verified).
   - `logAction()` backward-compatible wrapper operates seamlessly (Verified).

---

## 5. Adversarial Stress Test Results

| Challenge Suite | Category | Scenario / Vector | Result | Observed Behavior |
|-----------------|----------|-------------------|--------|-------------------|
| `challenge_m1.php` | Duplicate Key | Duplicate class name (`6A1`) | **PASS** | Rejected with SQLSTATE 23000 (1062 Duplicate entry) |
| `challenge_m1.php` | Duplicate Key | Duplicate student (`Nguyễn Văn An`, `2015-03-12`) | **PASS** | Rejected with SQLSTATE 23000 (1062 Duplicate entry) |
| `challenge_m1.php` | Duplicate Key | Duplicate teacher assignment (`2, 1, 'Toán'`) | **PASS** | Rejected with SQLSTATE 23000 (1062 Duplicate entry) |
| `challenge_m1.php` | Duplicate Key | Duplicate score record (`1, 'Toán'`) | **PASS** | Rejected with SQLSTATE 23000 (1062 Duplicate entry) |
| `challenge_m1.php` | Duplicate Key | Duplicate username (`admin`) | **PASS** | Rejected with SQLSTATE 23000 (1062 Duplicate entry) |
| `challenge_m1.php` | Foreign Key | Orphan student insertion with non-existent `class_id` | **PASS** | Rejected with foreign key constraint failure (errno 1452) |
| `challenge_m1.php` | Foreign Key | Orphan score insertion with non-existent `student_id` | **PASS** | Rejected with foreign key constraint failure (errno 1452) |
| `challenge_m1.php` | Cascade Delete | Delete class cascading to students, scores, and teacher assignments | **PASS** | Clean cascade across 4 tables |
| `challenge_m1.php` | Cascade Delete | Delete user cascading to teacher assignments | **PASS** | Clean cascade across teacher assignments |
| `challenge_m1.php` | Unicode Fidelity | Store/retrieve complex Vietnamese diacritics | **PASS** | Exact binary match via PDO (utf8mb4) |
| `challenge_m1.php` | Boundary / Edge | 50-character maximum string boundary | **PASS** | Stored and retrieved without truncation |
| `challenge_m1.php` | Transaction | Rollback on unique violation | **PASS** | Rollback unwound completely, 0 leaked rows |
| `challenge_m1_harness.php` | Password Security | Brute force dictionary attack (50+ vectors) | **PASS** | All non-123456 passwords strictly rejected |
| `challenge_m1_harness.php` | SQLite Purge | Filesystem audit for `.sqlite`, `.db`, `.s3db` | **PASS** | 0 SQLite files found |
| `challenge_m1_harness.php` | High Concurrency | 500 consecutive `log_action()` calls | **PASS** | 500 records inserted cleanly with sub-millisecond latency |
| `challenge_m1_harness.php` | Injection Attack | SQL injection & XSS payloads in `action`, `details` | **PASS** | Parameterized queries prevent injection; payloads stored safely |
| `challenge_m1_harness.php` | IP Spoofing | Overlong IP, comma-separated proxies, IPv6 | **PASS** | Handled and truncated to VARCHAR(45) safely |

---

## 6. Coverage Gaps & Unexplored Areas

- **Downstream Web Endpoints**: `admin.php`, `login.php`, `nhap_diem.php`, and `index.php` currently contain legacy code (e.g. `|| $password === '123456'` in `login.php:16` and lack of route guards on `admin.php`). These are explicitly scoped for Milestones 2–5 per `PROJECT.md`.
- **Risk Level**: LOW (Expected milestone separation; data access layer is fully isolated and ready).

---

## 7. Conclusion

Milestone 1 is verified, robust, and free of defects or integrity issues.
The foundation is approved for Milestone 2 (Auth & Strict Server-Side RBAC) kickoff.
