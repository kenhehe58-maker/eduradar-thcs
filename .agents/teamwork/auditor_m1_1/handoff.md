# Handoff Report — Forensic Audit of Milestone 1

**Agent**: `auditor_m1_1` (Forensic Auditor)  
**Parent / Recipient**: `391812a0-32ec-4263-b097-6162669cc4f7` (orchestrator_1)  
**Milestone**: Milestone 1 (Database Overhaul & Data Access Layer)  
**Date**: 2026-10-17  
**Working Directory**: `D:\New folder\my-web-project\.agents\teamwork\auditor_m1_1`  
**Handoff Type**: Hard (Task Complete)  
**Audit Verdict**: **CLEAN**

---

## 1. Observation

1. **Automated Verification Suite Authenticity**:
   - Inspected `D:\New folder\my-web-project\tests\verify_m1.php`:
     - Test 1.1–1.3: Verifies absence of `database.sqlite` via `file_exists()`, absence of `SQLITE_FILE`/`DB_DRIVER` in `includes/config.php`, absence of `initSQLite()` in `includes/db.php`, and singleton `PDO` connection with driver `'mysql'`.
     - Test 2.1–2.4: Queries `information_schema.TABLES`, `TABLE_CONSTRAINTS`, `KEY_COLUMN_USAGE`, `REFERENTIAL_CONSTRAINTS`, and `COLUMNS` in `edu_radar`.
     - Test 3.1–3.2: Queries duplicate rows using `GROUP BY ... HAVING count(*) > 1` on all tables, and asserts 36 classes exist.
     - Test 4.1: Executes `SELECT password FROM users WHERE username = ?` and runs `password_verify('123456', $hash)` and `password_verify('invalid_pwd_test', $hash)`.
     - Test 5.1–5.2: Calls `log_action()` and `logAction()` with dynamic random tokens (`bin2hex(random_bytes(6))`), querying `audit_logs` to verify roundtrip storage.
   - Executed `& "D:\xam\php\php.exe" "D:\New folder\my-web-project\tests\verify_m1.php"`:
     ```text
     ============================================================
       Total Tests: 12 | Passed: 12 | Failed: 0
     ============================================================
     ```
     Exit code: `0`.

2. **Empirical UNIQUE Constraint Verification via Native MySQL**:
   - Duplicate class insertion:
     ```powershell
     & "D:\xam\mysql\bin\mysql.exe" -u root -e "USE edu_radar; INSERT INTO classes (name, grade, section) VALUES ('6A1', 6, 'A');"
     ```
     Output: `ERROR 1062 (23000) at line 1: Duplicate entry '6A1' for key 'unique_class_name'`
   - Duplicate user insertion:
     ```powershell
     & "D:\xam\mysql\bin\mysql.exe" -u root -e "USE edu_radar; INSERT INTO users (username, password, full_name, role) VALUES ('admin', 'x', 'x', 'teacher');"
     ```
     Output: `ERROR 1062 (23000) at line 1: Duplicate entry 'admin' for key 'unique_username'`
   - Duplicate assignment insertion:
     ```powershell
     & "D:\xam\mysql\bin\mysql.exe" -u root -e "USE edu_radar; INSERT INTO teacher_classes (teacher_id, class_id, subject) VALUES (2, 1, UNHEX('546FC3A16E'));"
     ```
     Output: `ERROR 1062 (23000) at line 1: Duplicate entry '2-1-Toán' for key 'unique_assignment'`
   - Duplicate student insertion:
     ```powershell
     & "D:\xam\mysql\bin\mysql.exe" -u root -e "USE edu_radar; INSERT INTO students (class_id, student_code, full_name, birthday) VALUES (1, 'DUP_TEST', UNHEX('4E677579E1BB856E2056C4836E20416E'), '2015-03-12');"
     ```
     Output: `ERROR 1062 (23000) at line 1: Duplicate entry '1-Nguyễn Văn An-2015-03-12' for key 'unique_class_student'`
   - Duplicate score insertion:
     ```powershell
     & "D:\xam\mysql\bin\mysql.exe" -u root -e "USE edu_radar; INSERT INTO scores (student_id, subject) VALUES (1, UNHEX('546FC3A16E'));"
     ```
     Output: `ERROR 1062 (23000) at line 1: Duplicate entry '1-Toán' for key 'unique_student_subject'`

3. **Foreign Key Integrity & Deep Cascade Verification**:
   - Orphan student insert with invalid `class_id = 9999`:
     ```powershell
     & "D:\xam\mysql\bin\mysql.exe" -u root -e "USE edu_radar; INSERT INTO students (class_id, full_name) VALUES (9999, 'Test');"
     ```
     Output: `ERROR 1452 (23000) at line 1: Cannot add or update a child row: a foreign key constraint fails (CONSTRAINT fk_students_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE)`
   - Deep cascade test:
     Inserted test class id 58, assignment id 19, student id 38, score id 22. Deleted class id 58. Querying remaining rows returned `class_rem: 0, tc_rem: 0, student_rem: 0, score_rem: 0`.

4. **Cryptographic Password Hash Security**:
   - Seed accounts `admin`, `gv_toan`, `gv_van` store bcrypt hash `$2y$10$Huov5BMeiGHCxhgfaTkauuthlWkvzJguIrvV6vWU1SQsJDrk6PlMG`.
   - Verified via `password_verify`: returns `true` for `'123456'`, and returns `false` for `''`, `'admin'`, `'1234567'`, `' 123456'`, and `"' OR 1=1 --"`.

5. **Complete SQLite Purge**:
   - `Get-ChildItem -Path "D:\New folder\my-web-project" -Recurse -Filter "*sqlite*" -Force` returned 0 items.
   - `Get-ChildItem -Path "D:\New folder\my-web-project" -Recurse -Filter "*.db*" -Force` returned 0 items.
   - Inspected `includes/config.php` and `includes/db.php`: all SQLite constants and code removed.

6. **Adversarial Stress Test Suites**:
   - `tests/challenge_m1.php`: 20/20 passed (Section 1-5 covering character set encoding, duplicate rejections, cascades, audit logging, boundary strings).
   - `tests/challenge_m1_harness.php`: 14/14 passed (brute force testing, SQLite driver refusal, rapid 500-iteration audit logging).

---

## 2. Logic Chain

1. **Authenticity vs Facades**:
   - Observations 1 and 6 prove that `tests/verify_m1.php` and the supporting test suites do not return canned PASS strings. Every test issues dynamic PDO/SQL queries against live MariaDB and verifies actual system responses.
   - Observation 1 shows that `db()` creates an authentic MySQL PDO singleton and `log_action()` issues real prepared SQL statements.

2. **Database Integrity & Constraints**:
   - Observation 2 demonstrates that all 5 required business `UNIQUE` constraints (`unique_class_name`, `unique_username`, `unique_assignment`, `unique_class_student`, `unique_student_subject`) are active in the MariaDB storage engine and reject duplicate writes with MySQL Error 1062.
   - Observation 3 proves that foreign key constraints with `ON DELETE CASCADE` reject invalid foreign keys with Error 1452 and cleanly prune descendant rows across 3 levels of relation.

3. **Security & Cryptography**:
   - Observation 4 proves that seed accounts do not use plaintext or trivial bypasses in the database layer. Bcrypt hashes accurately verify `'123456'` and reject invalid passwords and injection attempts.

4. **SQLite Purge**:
   - Observation 5 confirms that SQLite has been purged from both the physical storage and the code architecture, fulfilling requirement R1.

---

## 3. Caveats

1. **Downstream Application Layer (Milestone 2–5)**:
   - This audit evaluated Milestone 1 (database schema, data access layer, constraints, and audit table).
   - In `login.php:16`, the legacy bypass `|| $password === '123456'` is scheduled for removal in Milestone 2 per `PROJECT.md` Feature #5.
   - In `admin.php`, server-side RBAC guard is scheduled for Milestone 2 per Feature #6.
2. **NULL Handling on Composite Key**:
   - Under standard SQL behavior, MariaDB allows multiple rows when `birthday` is `NULL` in `unique_class_student (class_id, full_name, birthday)`. As observed during stress testing, Milestone 3 (student roster import) must enforce non-null birthdays or implement application-level null coalescing.
3. **No Other Caveats**:
   - Environment is fully operational and healthy.

---

## 4. Conclusion

Milestone 1 work product is authentic, genuine, and free of any integrity violations, facades, or dummy stubs:
- The database schema is fully normalized, enforces all required UNIQUE and FK CASCADE constraints in live MariaDB, and has 0 duplicate rows.
- Data access helpers in `includes/db.php` use prepared statements with singleton PDO and client IP capture.
- SQLite is completely eliminated.
- Test suites execute authentic assertions and pass 100%.

**Auditor Verdict**: **CLEAN**. Milestone 1 is approved to proceed to Milestone 2.

---

## 5. Verification Method

To independently reproduce this forensic audit:

1. **Run Milestone 1 Verification Suite**:
   ```powershell
   & "D:\xam\php\php.exe" "D:\New folder\my-web-project\tests\verify_m1.php"
   ```
   *Expected Output*: `Total Tests: 12 | Passed: 12 | Failed: 0`, exit code `0`.

2. **Run Adversarial Stress Test Suite**:
   ```powershell
   & "D:\xam\php\php.exe" "D:\New folder\my-web-project\tests\challenge_m1.php"
   ```
   *Expected Output*: `Total 20 | Passed: 20 | Failed: 0`, exit code `0`.

3. **Verify UNIQUE Constraint Rejection (MySQL Error 1062)**:
   ```powershell
   & "D:\xam\mysql\bin\mysql.exe" -u root -e "USE edu_radar; INSERT INTO classes (name, grade, section) VALUES ('6A1', 6, 'A');"
   ```
   *Expected Output*: `ERROR 1062 (23000): Duplicate entry '6A1' for key 'unique_class_name'`.

4. **Verify Zero Duplicate Rows in Live MariaDB**:
   ```powershell
   & "D:\xam\mysql\bin\mysql.exe" -u root -e "USE edu_radar; SELECT 'classes dup' AS t, count(*) FROM (SELECT name FROM classes GROUP BY name HAVING count(*) > 1) x UNION ALL SELECT 'users dup', count(*) FROM (SELECT username FROM users GROUP BY username HAVING count(*) > 1) x UNION ALL SELECT 'tc dup', count(*) FROM (SELECT teacher_id, class_id, subject FROM teacher_classes GROUP BY teacher_id, class_id, subject HAVING count(*) > 1) x UNION ALL SELECT 'students dup', count(*) FROM (SELECT class_id, full_name, birthday FROM students GROUP BY class_id, full_name, birthday HAVING count(*) > 1) x UNION ALL SELECT 'scores dup', count(*) FROM (SELECT student_id, subject FROM scores GROUP BY student_id, subject HAVING count(*) > 1) x;"
   ```
   *Expected Output*: `count(*) = 0` across all rows.

5. **Verify Absence of SQLite**:
   ```powershell
   & "D:\xam\php\php.exe" -r "echo file_exists('D:/New folder/my-web-project/database.sqlite') ? 'FAIL' : 'PASS';"
   ```
   *Expected Output*: `PASS`.

6. **Invalidation Conditions**:
   - Any test failure in `tests/verify_m1.php` or `tests/challenge_m1.php`.
   - Any duplicate row allowed by MariaDB on the 5 business entity keys.
   - Any `.sqlite` or `.db` file present in the project workspace.
