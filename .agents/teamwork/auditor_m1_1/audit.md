# Forensic Audit Report — Milestone 1

**Work Product**: Milestone 1 Deliverables (`database.sql`, `includes/config.php`, `includes/db.php`, `tests/verify_m1.php`, MariaDB database `edu_radar`, and SQLite filesystem audit)  
**Profile**: General Project  
**Integrity Mode**: Development (per `ORIGINAL_REQUEST.md` line 8)  
**Auditor**: `auditor_m1_1` (Forensic Auditor)  
**Target Milestone**: Milestone 1 (Database Overhaul & Data Access Layer)  
**Audit Date**: 2026-10-17  
**Verdict**: **CLEAN**

---

## Executive Summary

A forensic audit of Milestone 1 was conducted to evaluate genuine logic implementation versus facades, detect any hardcoded checks or backdoors, verify MariaDB `edu_radar` data integrity, and confirm the complete elimination of SQLite.

All 5 core forensic integrity criteria have been empirically verified:
1. **No Hardcoded Test Facades**: `tests/verify_m1.php` executes real dynamic PDO queries against live MariaDB `edu_radar` and verifies dynamic system state.
2. **No Dummy/Facade Implementations**: `db()`, `log_action()`, and `logAction()` in `includes/db.php` execute real PDO prepared statements against MySQL with strict error handling and connection pooling.
3. **Hardware-Level Database Constraints**: All 5 business `UNIQUE` keys (`unique_username`, `unique_class_name`, `unique_assignment`, `unique_class_student`, `unique_student_subject`) were tested with duplicate insertions and empirically reject duplicates with MySQL Error 1062 (`SQLSTATE 23000`).
4. **Real Cryptographic Hashes**: Passwords in `users` are standard bcrypt hashes (`$2y$10$...`) that verify successfully for `'123456'` and strictly reject empty strings, wrong passwords, and SQL injection strings.
5. **Complete SQLite Purge**: `database.sqlite` was removed from the filesystem, recursive file discovery found zero `.sqlite`/`.db` files, and all SQLite constants and functions were purged from `includes/config.php` and `includes/db.php`.

**Verdict**: **CLEAN**.

---

## Phase Results

| Check Name | Status | Details |
|---|:---:|---|
| **Hardcoded Output Detection** | **PASS** | No canned PASS strings or static mocks found in `tests/verify_m1.php` or `includes/db.php`. |
| **Facade Implementation Detection** | **PASS** | `db()` is an authentic PDO singleton; `log_action()` performs real parameterized inserts into `audit_logs`. |
| **Pre-populated Artifact Detection** | **PASS** | Zero pre-populated test results, fake logs, or attestation files exist in the project. |
| **Self-Certifying Test Detection** | **PASS** | Tests query `information_schema` and database tables dynamically; assertions generate random tokens (`bin2hex(random_bytes(6))`) to verify write-read roundtrips. |
| **SQLite Elimination** | **PASS** | Zero `.sqlite`, `.sqlite3`, or `.db` files exist in project tree. `SQLITE_FILE`, `DB_DRIVER`, and `initSQLite()` completely removed. |
| **MariaDB Schema & Table Integrity** | **PASS** | All 6 tables (`users`, `classes`, `teacher_classes`, `students`, `scores`, `audit_logs`) exist with InnoDB engine and `utf8mb4_unicode_ci`. Legacy `attendance` dropped. |
| **Deduplication & Unique Constraints** | **PASS** | Empirically verified with direct duplicate insertions on all 5 unique constraints: all rejected with Error 1062. Zero duplicate rows exist in live database. |
| **Foreign Key Referential Integrity** | **PASS** | 4 foreign keys verified. Orphan inserts rejected with Error 1452. Deep cascade deletion verified (`classes` -> `students`, `scores`, `teacher_classes`). |
| **Authentication & Bcrypt Verification** | **PASS** | Seed passwords for `admin`, `gv_toan`, `gv_van` verified against bcrypt hashes. Verified true for `'123456'`, false for incorrect/adversarial inputs. |
| **Audit Logging Engine** | **PASS** | Verified write-read roundtrip with client IP extraction, null session identity fallback to `'Khách'`, and backward-compatible `logAction()` delegation. |

---

## Forensic Evidence

### 1. Verification of Test Suite Authenticity (`tests/verify_m1.php`)
Execution of the test suite with `D:\xam\php\php.exe`:
```text
============================================================
  EduRadar Milestone 1: Automated Verification Suite
============================================================

[TEST] 1.1 File database.sqlite is deleted........................... [PASS]
[TEST] 1.2 includes/config.php loads cleanly without SQLite constants [PASS]
[TEST] 1.3 includes/db.php establishes pure PDO MySQL singleton...... [PASS]
[TEST] 2.1 All 6 required tables exist in edu_radar.................. [PASS]
[TEST] 2.2 UNIQUE constraints enforced on all required business keys. [PASS]
[TEST] 2.3 Foreign Keys with ON DELETE CASCADE configured correctly.. [PASS]
[TEST] 2.4 scores table has modern VnEdu columns (tx1..tx4, gk, ck, avg_score) [PASS]
[TEST] 3.1 Zero duplicate rows exist across all tables............... [PASS]
[TEST] 3.2 36 Classes seeded covering Grades 6-9 and Sections A-C.... [PASS]
[TEST] 4.1 Passwords for admin, gv_toan, gv_van verify correctly with '123456' [PASS]
[TEST] 5.1 log_action() successfully writes audit record with IP and prepared stmt [PASS]
[TEST] 5.2 Backward-compatible logAction() wrapper works seamlessly.. [PASS]

============================================================
  Total Tests: 12 | Passed: 12 | Failed: 0
============================================================
```
Exit code: `0`.

### 2. Empirical Verification of UNIQUE Constraints (Direct MySQL Error 1062)
Direct SQL statements executed against MariaDB 10.4:

- **classes(name)**:
  ```powershell
  & "D:\xam\mysql\bin\mysql.exe" -u root -e "USE edu_radar; INSERT INTO classes (name, grade, section) VALUES ('6A1', 6, 'A');"
  ```
  *Result*: `ERROR 1062 (23000) at line 1: Duplicate entry '6A1' for key 'unique_class_name'`

- **users(username)**:
  ```powershell
  & "D:\xam\mysql\bin\mysql.exe" -u root -e "USE edu_radar; INSERT INTO users (username, password, full_name, role) VALUES ('admin', 'x', 'x', 'teacher');"
  ```
  *Result*: `ERROR 1062 (23000) at line 1: Duplicate entry 'admin' for key 'unique_username'`

- **teacher_classes(teacher_id, class_id, subject)**:
  ```powershell
  & "D:\xam\mysql\bin\mysql.exe" -u root -e "USE edu_radar; INSERT INTO teacher_classes (teacher_id, class_id, subject) VALUES (2, 1, UNHEX('546FC3A16E'));"
  ```
  *Result*: `ERROR 1062 (23000) at line 1: Duplicate entry '2-1-Toán' for key 'unique_assignment'`

- **students(class_id, full_name, birthday)**:
  ```powershell
  & "D:\xam\mysql\bin\mysql.exe" -u root -e "USE edu_radar; INSERT INTO students (class_id, student_code, full_name, birthday) VALUES (1, 'DUP_TEST', UNHEX('4E677579E1BB856E2056C4836E20416E'), '2015-03-12');"
  ```
  *Result*: `ERROR 1062 (23000) at line 1: Duplicate entry '1-Nguyễn Văn An-2015-03-12' for key 'unique_class_student'`

- **scores(student_id, subject)**:
  ```powershell
  & "D:\xam\mysql\bin\mysql.exe" -u root -e "USE edu_radar; INSERT INTO scores (student_id, subject) VALUES (1, UNHEX('546FC3A16E'));"
  ```
  *Result*: `ERROR 1062 (23000) at line 1: Duplicate entry '1-Toán' for key 'unique_student_subject'`

### 3. Empirical Verification of Foreign Keys & ON DELETE CASCADE
- **Orphan Student Rejection**:
  ```powershell
  & "D:\xam\mysql\bin\mysql.exe" -u root -e "USE edu_radar; INSERT INTO students (class_id, full_name) VALUES (9999, 'Test');"
  ```
  *Result*: `ERROR 1452 (23000) at line 1: Cannot add or update a child row: a foreign key constraint fails (CONSTRAINT fk_students_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE)`

- **Deep Cascade Deletion Test**:
  Inserted a hierarchy (`classes` id 58 -> `teacher_classes` id 19, `students` id 38 -> `scores` id 22), deleted `classes` row 58, and queried remaining rows:
  ```text
  stage         class_rem  tc_rem  student_rem  score_rem
  AFTER_DELETE  0          0       0            0
  ```
  All child and grandchild records were automatically and cleanly deleted by the InnoDB storage engine.

### 4. Zero Duplicate Rows Across Live Tables
```powershell
& "D:\xam\mysql\bin\mysql.exe" -u root -e "USE edu_radar; SELECT 'classes dup' AS t, count(*) FROM (SELECT name FROM classes GROUP BY name HAVING count(*) > 1) x UNION ALL SELECT 'users dup', count(*) FROM (SELECT username FROM users GROUP BY username HAVING count(*) > 1) x UNION ALL SELECT 'tc dup', count(*) FROM (SELECT teacher_id, class_id, subject FROM teacher_classes GROUP BY teacher_id, class_id, subject HAVING count(*) > 1) x UNION ALL SELECT 'students dup', count(*) FROM (SELECT class_id, full_name, birthday FROM students GROUP BY class_id, full_name, birthday HAVING count(*) > 1) x UNION ALL SELECT 'scores dup', count(*) FROM (SELECT student_id, subject FROM scores GROUP BY student_id, subject HAVING count(*) > 1) x;"
```
*Result*:
```text
t             count(*)
classes dup   0
users dup     0
tc dup        0
students dup  0
scores dup    0
```

### 5. SQLite Purge Verification
- Recursive filesystem scan:
  ```powershell
  Get-ChildItem -Path "D:\New folder\my-web-project" -Recurse -Filter "*sqlite*" -Force
  Get-ChildItem -Path "D:\New folder\my-web-project" -Recurse -Filter "*.db" -Force
  ```
  *Result*: 0 files returned.
- Grep code audit for `sqlite` references:
  *Result*: Only comments and test assertions verifying absence of SQLite remain.

### 6. Authentication & Bcrypt Hashes
Evaluated hash `$2y$10$Huov5BMeiGHCxhgfaTkauuthlWkvzJguIrvV6vWU1SQsJDrk6PlMG` stored in `users`:
- Positive match: `password_verify('123456', $h) === true`
- Negative match: `password_verify('', $h) === false`
- Negative match: `password_verify('admin', $h) === false`
- Negative match: `password_verify('1234567', $h) === false`
- Negative match: `password_verify("' OR 1=1 --", $h) === false`
*Result*: `ALL_HASH_TESTS_PASS`.

---

## Conclusion & Verdict

The work product delivered for Milestone 1 satisfies all integrity criteria without shortcuts, mock data, or facade implementations. The schema, data access layer, audit logging, and automated test suite are authentic and robust.

**Final Verdict**: **CLEAN**.
