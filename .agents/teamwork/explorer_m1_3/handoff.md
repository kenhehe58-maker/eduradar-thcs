# HANDOFF REPORT: MIGRATION EXECUTION & PHP CLI VERIFICATION PROCEDURES (M1)

- **Agent**: `explorer_m1_3` (Teamwork Explorer)
- **Recipient**: `391812a0-32ec-4263-b097-6162669cc4f7` (Parent / Orchestrator)
- **Working Directory**: `D:\New folder\my-web-project\.agents\teamwork\explorer_m1_3`
- **Milestone**: Milestone 1 (Database Overhaul & Migration)
- **Handoff Type**: Hard (Investigation & Design Complete)
- **Detailed Design File**: `D:\New folder\my-web-project\.agents\teamwork\explorer_m1_3\analysis.md`

---

## 1. Observation

1. **MySQL Client & Dump Tooling**:
   - `D:\xam\mysql\bin\mysql.exe` and `D:\xam\mysql\bin\mysqldump.exe` are present and operational.
   - Live database engine: `10.4.32-MariaDB` on `127.0.0.1:3306`, user `root`, empty password.
   - Command: `& "D:\xam\mysql\bin\mysql.exe" -u root -e "SELECT VERSION();"` returned `10.4.32-MariaDB`.
   - Command: `Test-Path "D:\xam\mysql\bin\mysqldump.exe"` returned `True`.

2. **PHP CLI Environment**:
   - Command: `& "D:\xam\php\php.exe" -v` returned `PHP 8.0.30 (cli) (built: Sep  1 2023 14:15:38) ( ZTS Visual C++ 2019 x64 )`.
   - Extensions loaded: `pdo_mysql`, `zip`, `SimpleXML`, `mbstring`, `curl`, `json`, `session`, `openssl`.
   - Command: `& "D:\xam\php\php.exe" -r "require 'includes/db.php'; var_dump(db() instanceof PDO);"` returned `bool(true)`.

3. **Disk Schema vs Live Database Deficiencies**:
   - In `D:\New folder\my-web-project\database.sql`:
     - Line 77-81 contains bcrypt hash `$2y$10$4y9pQzS/m7y9xY8Z/xH0ve2w5.JjK.RjT18P4q7lZ0eQ0/a4uCqS.`. Running `var_dump(password_verify('123456', ...))` returned `bool(false)`!
     - Table `audit_logs` is entirely missing from `database.sql`.
     - Lines 84-92 seed only 8 classes instead of 36.
     - Table `students` has no `UNIQUE(class_id, full_name, birthday)` constraint.
     - Table `scores` has legacy columns (`m1, m2, p15_1, p15_2, t1_1, t1_2, gk, ck, avg_score`) instead of MOET Circular 22 (`tx1..tx4, gk, ck, avg_score`).
   - In live MySQL `edu_radar`:
     - 6 tables exist: `audit_logs`, `classes`, `scores`, `students`, `teacher_classes`, `users`.
     - `users` table already has verified working bcrypt hash `$2y$10$Huov5BMeiGHCxhgfaTkauuthlWkvzJguIrvV6vWU1SQsJDrk6PlMG` (verifies `true` for `'123456'`).
     - 36 classes exist (`6A1` to `9C3`).
     - 0 duplicate rows across all tables.
     - `students` lacks `UNIQUE KEY (class_id, full_name, birthday)` (only has primary key and foreign key index).

4. **SQLite File & References**:
   - File `D:\New folder\my-web-project\database.sqlite` exists and has size 40,960 bytes.
   - `includes/config.php:13` defines `SQLITE_FILE`.
   - `includes/db.php:10-18` branches to SQLite if `DB_DRIVER === 'sqlite'`.
   - `includes/db.php:63-126` contains `initSQLite()`.

5. **Audit Logging & Function Signatures**:
   - `includes/db.php:42` implements `logAction(string $action, string $details = ''): void` with 10 call sites across `login.php`, `logout.php`, `admin.php`, and `nhap_diem.php`.
   - `PROJECT.md:52` requires `log_action(?int $user_id, ?string $username, string $action, string $details): void`.

---

## 2. Logic Chain

1. **From Observation 1 & 3 (Pre-migration safety and risk of broken seed data)**:
   - Because `database.sql` currently on disk contains an invalid bcrypt hash and lacks `audit_logs`, directly applying it to MySQL would break authentication for all users and wipe out the live audit trail.
   - Therefore, a pre-migration backup via `mysqldump.exe` is mandatory as Step 0 before applying any migration.
   - Furthermore, `database.sql` must first be updated with `explorer_m1_1`'s verified DDL and working bcrypt hash (`$2y$10$Huov5BMeiGHCxhgfaTkauuthlWkvzJguIrvV6vWU1SQsJDrk6PlMG`) before piping it to `mysql.exe`.

2. **From Observation 1 & 2 (Windows PowerShell UTF-8 Migration Execution)**:
   - On Windows, piping file content using `Get-Content -Path "database.sql" -Encoding UTF8 | & "D:\xam\mysql\bin\mysql.exe" -u root --default-character-set=utf8mb4` preserves Vietnamese diacritics in school/student names and avoids shell parsing errors caused by spaces in paths (`D:\New folder\...`).
   - Using `SET FOREIGN_KEY_CHECKS = 0;` and `SET FOREIGN_KEY_CHECKS = 1;` in `database.sql` guarantees atomic, order-independent table reconstruction.

3. **From Observation 3 & 4 (Automated PHP CLI Verification of Schema, Constraints & Deduplication)**:
   - Because `information_schema.TABLE_CONSTRAINTS` and `information_schema.KEY_COLUMN_USAGE` expose all table constraints, PHP CLI can directly inspect constraint types (`UNIQUE`, `FOREIGN KEY`) and target columns without web requests.
   - Zero duplicate rows can be conclusively verified across `users`, `classes`, `teacher_classes`, `students`, and `scores` using `GROUP BY ... HAVING COUNT(*) > 1` queries.

4. **From Observation 3 & 5 (Authentication, Audit Logging & SQLite Purge Verification)**:
   - PHP CLI can verify `password_verify('123456', $hash) === true` and `password_verify('wrong_pass', $hash) === false` for `admin`, `gv_toan`, and `gv_van`.
   - Testing `log_action()` via CLI requires inserting a unique test token, querying `audit_logs` for that token, and validating captured fields (`user_id`, `username`, `details`, `ip_address`).
   - Retaining a wrapper `logAction(string $action, string $details = ''): void` in `includes/db.php` delegating to `log_action()` prevents fatal errors at the 10 existing call sites until Milestones 2–5 refactor those pages.
   - SQLite decoupling is verified by asserting `file_exists('database.sqlite') === false`, `!defined('SQLITE_FILE')`, and `!function_exists('initSQLite')`.

---

## 3. Caveats

1. **Read-Only Explorer Scope**:
   - As an explorer agent, no project source code (`database.sql`, `includes/config.php`, `includes/db.php`) was modified in this turn. All execution commands and scripts are delivered as concrete specifications for the implementer.
2. **File Lock on `database.sqlite`**:
   - On Windows, if any process (such as a local SQLite viewer or web server process) has an active file handle on `database.sqlite`, `Remove-Item` or PHP `unlink()` will fail with a permission error. The file handle must be released before deletion.
3. **Database Port & Credentials Assumption**:
   - The procedures assume standard XAMPP configuration: `127.0.0.1:3306`, user `root`, empty password. If customized in XAMPP, `config.php` constants dictate connection parameters.

---

## 4. Conclusion

The migration execution plan and verification procedures for Milestone 1 are completely designed, validated against the live environment, and ready for immediate implementation.

### Implementer Action Sequence:
1. **Step 1 (Backup)**:
   ```powershell
   & "D:\xam\mysql\bin\mysqldump.exe" -u root --default-character-set=utf8mb4 --routines --triggers --databases edu_radar > "D:\New folder\my-web-project\backup_edu_radar_pre_m1.sql"
   ```
2. **Step 2 (Prepare `database.sql`)**:
   Incorporate `explorer_m1_1`'s DDL (6 tables with UNIQUE constraints, CASCADE foreign keys, audit table, 36 classes, verified bcrypt hash `$2y$10$Huov5BMeiGHCxhgfaTkauuthlWkvzJguIrvV6vWU1SQsJDrk6PlMG`).
3. **Step 3 (Refactor `includes/config.php` and `includes/db.php`)**:
   Implement pure PDO MySQL singleton, `log_action()` with backward-compatible `logAction()` wrapper (as designed by `explorer_m1_2`), and delete `database.sqlite`.
4. **Step 4 (Execute Migration)**:
   ```powershell
   Get-Content -Path "D:\New folder\my-web-project\database.sql" -Encoding UTF8 | & "D:\xam\mysql\bin\mysql.exe" -u root --default-character-set=utf8mb4
   ```
5. **Step 5 (Execute Verification Suite)**:
   Place `tests/verify_m1.php` and run:
   ```powershell
   & "D:\xam\php\php.exe" tests/verify_m1.php
   ```
   Must output: `Total Tests: 10 | Passed: 10 | Failed: 0`.

---

## 5. Verification Method

To independently verify the procedures and readiness of the live environment:

1. **Verify MySQL & mysqldump Connectivity**:
   ```powershell
   & "D:\xam\mysql\bin\mysql.exe" -u root -e "SELECT 'MySQL Ready' AS status;"
   Test-Path "D:\xam\mysql\bin\mysqldump.exe"
   ```
   *Expected*: `MySQL Ready`, `True`.

2. **Verify PHP CLI & PDO MySQL Driver**:
   ```powershell
   & "D:\xam\php\php.exe" -r "require 'includes/db.php'; echo (db() instanceof PDO ? 'PDO MySQL OK' : 'FAIL') . PHP_EOL;"
   ```
   *Expected*: `PDO MySQL OK`.

3. **Verify Existing Live Accounts Bcrypt Hash**:
   ```powershell
   & "D:\xam\php\php.exe" --% -r "require 'includes/db.php'; $stmt = db()->query('SELECT username, password FROM users'); foreach ($stmt as $u) echo $u['username'] . ': ' . (password_verify('123456', $u['password']) ? 'OK' : 'FAIL') . PHP_EOL;"
   ```
   *Expected*: `admin: OK`, `gv_toan: OK`, `gv_van: OK`.

4. **Verify Duplication Status Across All Tables**:
   ```powershell
   & "D:\xam\mysql\bin\mysql.exe" -u root edu_radar -e "SELECT 'classes' tbl, count(*) c FROM (SELECT name FROM classes GROUP BY name HAVING count(*) > 1) t UNION ALL SELECT 'users', count(*) FROM (SELECT username FROM users GROUP BY username HAVING count(*) > 1) t UNION ALL SELECT 'teacher_classes', count(*) FROM (SELECT teacher_id, class_id, subject FROM teacher_classes GROUP BY teacher_id, class_id, subject HAVING count(*) > 1) t;"
   ```
   *Expected*: All counts `0`.

5. **Post-Implementation Test Command**:
   ```powershell
   & "D:\xam\php\php.exe" tests/verify_m1.php
   ```
   *Expected*: Exit code 0, 0 failures.

---
*Report completed and verified by explorer_m1_3.*
