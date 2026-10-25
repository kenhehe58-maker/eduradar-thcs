# Handoff Report — Milestone 1 Implementation

**Agent**: `worker_m1` (implementer)  
**Parent / Recipient**: `391812a0-32ec-4263-b097-6162669cc4f7` (orchestrator_1)  
**Milestone**: Milestone 1 (Database Overhaul & Data Access Layer)  
**Date**: 2026-10-17  
**Working Directory**: `D:\New folder\my-web-project\.agents\teamwork\worker_m1`  
**Handoff Type**: Hard (Task Complete)  

---

## 1. Observation

1. **Schema & Migration Application**:
   - `D:\New folder\my-web-project\database.sql` was rewritten with complete 6-table DDL and clean seed data.
   - Migration command executed:
     ```powershell
     Get-Content -Path "D:\New folder\my-web-project\database.sql" -Encoding UTF8 | & "D:\xam\mysql\bin\mysql.exe" -u root --default-character-set=utf8mb4
     ```
     Result: Exited with code `0`.
   - Table verification query:
     ```powershell
     & "D:\xam\mysql\bin\mysql.exe" -u root -e "USE edu_radar; SELECT 'users' tbl, count(*) c FROM users UNION ALL SELECT 'classes', count(*) FROM classes UNION ALL SELECT 'teacher_classes', count(*) FROM teacher_classes UNION ALL SELECT 'students', count(*) FROM students UNION ALL SELECT 'scores', count(*) FROM scores UNION ALL SELECT 'audit_logs', count(*) FROM audit_logs;"
     ```
     Result:
     ```text
     tbl              c
     users            3
     classes          36
     teacher_classes  3
     students         5
     scores           7
     audit_logs       1
     ```

2. **Constraints & Deduplication in Live MariaDB**:
   - Unique constraints query:
     ```powershell
     & "D:\xam\mysql\bin\mysql.exe" -u root -e "USE edu_radar; SELECT TABLE_NAME, CONSTRAINT_NAME, CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA='edu_radar' AND CONSTRAINT_TYPE='UNIQUE';"
     ```
     Observed output:
     - `classes`: `unique_class_name` on `(name)`
     - `scores`: `unique_student_subject` on `(student_id, subject)`
     - `students`: `unique_class_student` on `(class_id, full_name, birthday)`
     - `teacher_classes`: `unique_assignment` on `(teacher_id, class_id, subject)`
     - `users`: `unique_username` on `(username)`
   - Foreign key cascade query:
     ```powershell
     & "D:\xam\mysql\bin\mysql.exe" -u root -e "USE edu_radar; SELECT CONSTRAINT_NAME, TABLE_NAME, REFERENCED_TABLE_NAME, DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='edu_radar';"
     ```
     Observed output: All 4 foreign keys (`fk_scores_student`, `fk_students_class`, `fk_tc_class`, `fk_tc_teacher`) have `DELETE_RULE = CASCADE`.
   - Zero duplicate rows check:
     ```powershell
     & "D:\xam\mysql\bin\mysql.exe" -u root -e "USE edu_radar; SELECT 'classes dup' AS t, count(*) FROM (SELECT name FROM classes GROUP BY name HAVING count(*) > 1) x UNION ALL SELECT 'users dup', count(*) FROM (SELECT username FROM users GROUP BY username HAVING count(*) > 1) x UNION ALL SELECT 'tc dup', count(*) FROM (SELECT teacher_id, class_id, subject FROM teacher_classes GROUP BY teacher_id, class_id, subject HAVING count(*) > 1) x UNION ALL SELECT 'students dup', count(*) FROM (SELECT class_id, full_name, birthday FROM students GROUP BY class_id, full_name, birthday HAVING count(*) > 1) x UNION ALL SELECT 'scores dup', count(*) FROM (SELECT student_id, subject FROM scores GROUP BY student_id, subject HAVING count(*) > 1) x;"
     ```
     Observed output: `count(*) = 0` across all 5 checks.

3. **Data Access Layer Refactoring**:
   - `D:\New folder\my-web-project\includes\config.php`: Pure MySQL constants (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET`, `BASE_URL`, `APP_NAME`, `PASS_SCORE`). SQLite constants `SQLITE_FILE` and `DB_DRIVER` removed.
   - `D:\New folder\my-web-project\includes\db.php`: Singleton `db(): PDO` with `PDO::ATTR_EMULATE_PREPARES => false`, `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `utf8mb4`. Standardized `log_action(?int $user_id, ?string $username, string $action, string $details = ''): void` with client IP extraction and non-blocking exception handling. Included `logAction(string $action, string $details = ''): void` backward-compatibility wrapper. `initSQLite()` removed.
   - Syntax linting:
     ```powershell
     & "D:\xam\php\php.exe" -l "D:\New folder\my-web-project\includes\config.php"
     & "D:\xam\php\php.exe" -l "D:\New folder\my-web-project\includes\db.php"
     ```
     Output: `No syntax errors detected in ...` for both files.

4. **SQLite File Deletion**:
   - `D:\New folder\my-web-project\database.sqlite` was deleted using `Remove-Item -Path "database.sqlite" -Force`.
   - Command: `Test-Path "D:\New folder\my-web-project\database.sqlite"` returned `False`.

5. **Automated Verification Suite Execution**:
   - Command: `& "D:\xam\php\php.exe" "D:\New folder\my-web-project\tests\verify_m1.php"`
   - Output:
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
   - Exit code: `0`.

---

## 2. Logic Chain

1. **From Observation 1 & 2**:
   - Applying `database.sql` created the exact 6 normalized tables (`users`, `classes`, `teacher_classes`, `students`, `scores`, `audit_logs`) in MariaDB with InnoDB engine and `utf8mb4_unicode_ci`.
   - Enforcing `UNIQUE KEY unique_class_student (class_id, full_name, birthday)` on `students` and `UNIQUE KEY unique_assignment (teacher_id, class_id, subject)` on `teacher_classes` guarantees hardware-level deduplication so subsequent Excel/CSV imports in Milestone 3 cannot create duplicate rows.
   - Enforcing `UNIQUE KEY unique_student_subject (student_id, subject)` on `scores` enables idempotent upserts during grade saving and Excel gradebook imports in Milestone 4.
   - Configuring `ON DELETE CASCADE` across all foreign keys guarantees clean referential integrity when deleting teachers, classes, or students.

2. **From Observation 3 & 4**:
   - Removing `SQLITE_FILE`, `DB_DRIVER`, and `initSQLite()` while deleting `database.sqlite` decouples the codebase completely from SQLite, fulfilling Requirement R1.
   - Retaining the `logAction()` wrapper in `includes/db.php` delegating to `log_action()` prevents breaking the 10 existing call sites across `admin.php`, `login.php`, `nhap_diem.php`, and `logout.php`, while providing the required `log_action()` API contract for Milestone 2-5.

3. **From Observation 1 & 5**:
   - The verified bcrypt hash `$2y$10$Huov5BMeiGHCxhgfaTkauuthlWkvzJguIrvV6vWU1SQsJDrk6PlMG` was seeded for `admin`, `gv_toan`, and `gv_van`.
   - Test 4.1 proved that `password_verify('123456', $hash)` returns `true` and `password_verify('invalid_pwd_test', $hash)` returns `false` for all 3 users. This allows Milestone 2 to safely remove the `|| $password === '123456'` backdoor in `login.php:16` without breaking authentication.

---

## 3. Caveats

1. **Downstream Column Name Awareness**:
   - `scores` table now contains `tx1, tx2, tx3, tx4, gk, ck, avg_score, updated_at` (MOET Thông tư 22 THCS standard) instead of legacy `m1..t1_2`.
   - `students` table now contains `student_code, full_name, birthday, gender, phone, created_at` instead of legacy `parent_phone`.
   - Milestones 2–5 will write to and read from these new column names.
2. **Existing Legacy Code**:
   - `login.php` still contains the legacy backdoor `|| $password === '123456'`, and `admin.php` still lacks server-side 403 guard. These are scoped strictly for Milestone 2 (Auth & Strict RBAC) per `PROJECT.md`.
3. **No Other Caveats**:
   - MariaDB 10.4 and PHP 8.0 on XAMPP are actively running, responsive, and verified.

---

## 4. Conclusion

Milestone 1 is **100% complete**:
- `database.sql` has clean schema, UNIQUE constraints, cascade FKs, audit table, 36 classes, working bcrypt hash for `'123456'`, and clean sample seeds.
- `includes/config.php` and `includes/db.php` are refactored to pure MySQL PDO with `log_action()` and `logAction()` wrapper.
- `database.sqlite` is deleted.
- Live MariaDB `edu_radar` is updated and clean (0 duplicates).
- `tests/verify_m1.php` passes with 12/12 passed (0 failures).

The project is ready for Milestone 2 (Auth & Strict Server-Side RBAC).

---

## 5. Verification Method

To independently verify this milestone:

1. **Run Automated Test Suite**:
   ```powershell
   & "D:\xam\php\php.exe" "D:\New folder\my-web-project\tests\verify_m1.php"
   ```
   *Expected Output*: `Total Tests: 12 | Passed: 12 | Failed: 0`, exit code `0`.

2. **Verify SQLite Absence**:
   ```powershell
   & "D:\xam\php\php.exe" -r "echo file_exists('D:/New folder/my-web-project/database.sqlite') ? 'FAIL' : 'PASS';"
   ```
   *Expected Output*: `PASS`.

3. **Verify Live MariaDB Table Counts & Zero Duplicates**:
   ```powershell
   & "D:\xam\mysql\bin\mysql.exe" -u root -e "USE edu_radar; SELECT 'classes dup' AS t, count(*) FROM (SELECT name FROM classes GROUP BY name HAVING count(*) > 1) x UNION ALL SELECT 'users dup', count(*) FROM (SELECT username FROM users GROUP BY username HAVING count(*) > 1) x UNION ALL SELECT 'tc dup', count(*) FROM (SELECT teacher_id, class_id, subject FROM teacher_classes GROUP BY teacher_id, class_id, subject HAVING count(*) > 1) x UNION ALL SELECT 'students dup', count(*) FROM (SELECT class_id, full_name, birthday FROM students GROUP BY class_id, full_name, birthday HAVING count(*) > 1) x UNION ALL SELECT 'scores dup', count(*) FROM (SELECT student_id, subject FROM scores GROUP BY student_id, subject HAVING count(*) > 1) x;"
   ```
   *Expected Output*: 0 for all duplicate checks.

4. **Verify Seed Account Password Hash**:
   ```powershell
   & "D:\xam\php\php.exe" -r "require 'D:/New folder/my-web-project/includes/db.php'; foreach (['admin', 'gv_toan', 'gv_van'] as \$u) { \$s = db()->prepare('SELECT password FROM users WHERE username = ?'); \$s->execute([\$u]); \$h = \$s->fetchColumn(); echo \$u . ': ' . (password_verify('123456', \$h) && !password_verify('bad', \$h) ? 'OK' : 'FAIL') . PHP_EOL; }"
   ```
   *Expected Output*:
   ```text
   admin: OK
   gv_toan: OK
   gv_van: OK
   ```

5. **Invalidation Conditions**:
   - Any test failure in `tests/verify_m1.php`.
   - `database.sqlite` found on disk.
   - Any duplicate row found in MariaDB `edu_radar`.
   - Seed accounts failing `password_verify('123456', $hash)`.
