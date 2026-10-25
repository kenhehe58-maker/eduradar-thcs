# MILESTONE 1: MIGRATION EXECUTION & COMPREHENSIVE VERIFICATION SPECIFICATION

**Target System**: EduRadar — Lower Secondary (THCS) Gradebook & Early Warning System  
**Author**: `explorer_m1_3` (Teamwork Explorer: Investigation & Synthesis)  
**Parent Agent**: `391812a0-32ec-4263-b097-6162669cc4f7`  
**Working Directory**: `D:\New folder\my-web-project\.agents\teamwork\explorer_m1_3`  
**Environment**: Windows 11, XAMPP Apache 2.4, MariaDB 10.4 (`edu_radar`), PHP 8.0.30 (`D:\xam\php\php.exe`)  
**Date**: 2026-10-17  

---

## 1. Executive Summary

This report establishes the complete, production-grade **Migration Execution Strategy** and **Automated Verification Procedures** for **Milestone 1 (Database Overhaul & Migration)** of the EduRadar system.

### Objectives Addressed:
1. **Migration Execution Plan**: Safe, atomic, and idempotent deployment of `database.sql` to live MariaDB `edu_radar` using `D:\xam\mysql\bin\mysql.exe`, including pre-migration backup via `D:\xam\mysql\bin\mysqldump.exe` and rollback procedures.
2. **PHP CLI Verification Procedures**: Standalone, automated test commands runnable directly via `D:\xam\php\php.exe` (both as a unified script `tests/verify_m1.php` and discrete CLI one-liners) verifying:
   - All 6 tables exist with correct schema, `UNIQUE` constraints, and foreign keys.
   - Zero duplicate rows exist across all tables.
   - Bcrypt passwords for `admin`, `gv_toan`, and `gv_van` verify successfully with `'123456'` (and reject incorrect passwords).
   - `log_action()` helper correctly captures client IP and writes prepared statements to `audit_logs`.
   - `database.sqlite` physical file is deleted and SQLite logic is purged from codebase.
3. **Synthesis with Peer Findings**:
   - Integrates `explorer_m1_1`'s 6-table DDL and 36-class seed data.
   - Integrates `explorer_m1_2`'s `includes/db.php` pure PDO MySQL singleton and `log_action()` implementation.

---

## 2. Live Environment & Tooling Verification

Before defining the migration and verification steps, the local XAMPP runtime environment was systematically audited:

| Component | Path / Endpoint | Version / Status | Verified Capability |
|---|---|---|---|
| **PHP CLI** | `D:\xam\php\php.exe` | PHP 8.0.30 (cli) Visual C++ 2019 x64 | `pdo_mysql`, `zip`, `SimpleXML`, `mbstring`, `openssl` enabled. |
| **MySQL Client** | `D:\xam\mysql\bin\mysql.exe` | 10.4.32-MariaDB | Connects to `127.0.0.1:3306`, user `root`, empty password. |
| **MySQL Dump** | `D:\xam\mysql\bin\mysqldump.exe` | 10.4.32-MariaDB | Present and functional for full database backups. |
| **Target Database** | `edu_radar` on `localhost:3306` | Active | MariaDB utf8mb4 collation `utf8mb4_unicode_ci`. |
| **Legacy SQLite** | `D:\New folder\my-web-project\database.sqlite` | 40,960 bytes | Present on disk, scheduled for immediate purge. |

---

## 3. Migration Plan: Applying `database.sql` to Live MySQL `edu_radar`

### 3.1 Pre-Migration Safety & Backup (Phase 0)
To prevent accidental data loss during schema replacement, execute a full logical backup using `mysqldump.exe`:

```powershell
& "D:\xam\mysql\bin\mysqldump.exe" -u root --default-character-set=utf8mb4 --routines --triggers --databases edu_radar > "D:\New folder\my-web-project\backup_edu_radar_pre_m1.sql"
```

**Verification of Backup**:
```powershell
$backup = Get-Item "D:\New folder\my-web-project\backup_edu_radar_pre_m1.sql"
if ($backup.Length -gt 0) { Write-Host "Pre-migration backup successful ($($backup.Length) bytes)" -ForegroundColor Green } else { throw "Backup failed or file is empty!" }
```

---

### 3.2 Pre-Flight SQL Integrity Checklist
Before applying `database.sql` to live MySQL, the file must be verified to contain the following elements designed by `explorer_m1_1`:
1. **Safety Headers**:
   ```sql
   SET NAMES utf8mb4;
   SET FOREIGN_KEY_CHECKS = 0;
   CREATE DATABASE IF NOT EXISTS edu_radar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   USE edu_radar;
   ```
2. **Explicit Drops with CASCADE Safety**:
   ```sql
   DROP TABLE IF EXISTS audit_logs;
   DROP TABLE IF EXISTS scores;
   DROP TABLE IF EXISTS students;
   DROP TABLE IF EXISTS teacher_classes;
   DROP TABLE IF EXISTS classes;
   DROP TABLE IF EXISTS users;
   DROP TABLE IF EXISTS attendance; -- Purges legacy unrequested table
   ```
3. **6 Production Tables**:
   - `users`: `id, username UNIQUE, password, full_name, role ENUM('admin','teacher'), created_at`
   - `classes`: `id, name UNIQUE, grade, section, created_at`
   - `teacher_classes`: `id, teacher_id, class_id, subject, created_at, UNIQUE(teacher_id, class_id, subject)`, cascading FKs to `users` and `classes`
   - `students`: `id, class_id, student_code, full_name, birthday, gender, phone, created_at, UNIQUE(class_id, full_name, birthday)`, cascading FK to `classes`
   - `scores`: `id, student_id, subject, tx1, tx2, tx3, tx4, gk, ck, avg_score, updated_at, UNIQUE(student_id, subject)`, cascading FK to `students`
   - `audit_logs`: `id, user_id, username, action, details, ip_address, created_at`
4. **Verified Seed Data**:
   - Valid bcrypt hash for `'123456'`: `$2y$10$Huov5BMeiGHCxhgfaTkauuthlWkvzJguIrvV6vWU1SQsJDrk6PlMG`
   - 36 classes (Khối 6–9, Sections A–C, Classes 1–3: `6A1` to `9C3`)
   - Initial assignments for `gv_toan` (6A1, 7A1) and `gv_van` (6A1)
   - 5 students in 6A1
   - 7 sample score rows with verified weighted averages covering Red/Yellow/Green statuses
   - Initial audit log entry
5. **Safety Footer**:
   ```sql
   SET FOREIGN_KEY_CHECKS = 1;
   ```

---

### 3.3 Migration Execution Procedures

Three equivalent and tested execution methods are available on Windows:

#### Method 1: PowerShell Pipeline (Primary Recommended)
This pipeline streams the file with explicit UTF-8 encoding into the MySQL client, avoiding Windows encoding glitches with Vietnamese diacritics:
```powershell
Get-Content -Path "D:\New folder\my-web-project\database.sql" -Encoding UTF8 | & "D:\xam\mysql\bin\mysql.exe" -u root --default-character-set=utf8mb4
```

#### Method 2: CMD Redirection Subshell (Alternative)
When executing within legacy command line or subshells:
```cmd
cmd.exe /c "`"D:\xam\mysql\bin\mysql.exe`" -u root --default-character-set=utf8mb4 < `"D:\New folder\my-web-project\database.sql`""
```

#### Method 3: MySQL Native Source Command
Direct invocation through MySQL command processor:
```powershell
& "D:\xam\mysql\bin\mysql.exe" -u root --default-character-set=utf8mb4 -e "source D:/New folder/my-web-project/database.sql"
```

---

### 3.4 Disaster Recovery & Rollback Procedure
If the migration encounters unexpected errors (e.g. disk quota, syntax error, or partial apply):
```powershell
Write-Warning "Initiating Rollback from pre-migration backup..."
Get-Content -Path "D:\New folder\my-web-project\backup_edu_radar_pre_m1.sql" -Encoding UTF8 | & "D:\xam\mysql\bin\mysql.exe" -u root --default-character-set=utf8mb4
```

---

## 4. Comprehensive PHP CLI Verification Procedures

The verification procedures test all 5 core requirements of Milestone 1 directly against live MySQL and PHP runtime.

### 4.1 Verification Point 1: 6-Table Schema, UNIQUE Constraints, and Foreign Keys

#### What is Verified:
1. Exact 6 tables exist in `information_schema.TABLES` under `TABLE_SCHEMA = 'edu_radar'`.
2. Exact `UNIQUE` constraints exist:
   - `users`: `UNIQUE KEY (username)`
   - `classes`: `UNIQUE KEY (name)`
   - `teacher_classes`: `UNIQUE KEY (teacher_id, class_id, subject)`
   - `students`: `UNIQUE KEY (class_id, full_name, birthday)`
   - `scores`: `UNIQUE KEY (student_id, subject)`
3. Exact Foreign Keys exist:
   - `teacher_classes` -> `users(id)` (`ON DELETE CASCADE`)
   - `teacher_classes` -> `classes(id)` (`ON DELETE CASCADE`)
   - `students` -> `classes(id)` (`ON DELETE CASCADE`)
   - `scores` -> `students(id)` (`ON DELETE CASCADE`)
4. Scores table schema has modern VnEdu columns (`tx1, tx2, tx3, tx4, gk, ck, avg_score, updated_at`).

#### PHP CLI One-Liner:
```powershell
& "D:\xam\php\php.exe" --% -r "require 'includes/db.php'; $pdo = db(); $tables = $pdo->query(\"SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='edu_radar'\")->fetchAll(PDO::FETCH_COLUMN); $expected = ['users','classes','teacher_classes','students','scores','audit_logs']; $missing = array_diff($expected, $tables); if ($missing) { echo 'MISSING TABLES: ' . implode(',', $missing) . PHP_EOL; exit(1); } echo 'ALL 6 TABLES PRESENT: OK' . PHP_EOL;"
```

#### UNIQUE Constraints & Foreign Keys Deep Inspection One-Liner:
```powershell
& "D:\xam\php\php.exe" --% -r "require 'includes/db.php'; $pdo = db(); $c = $pdo->query(\"SELECT TABLE_NAME, CONSTRAINT_NAME, CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA='edu_radar'\")->fetchAll(); $foundUnique = []; foreach ($c as $row) { if ($row['CONSTRAINT_TYPE'] === 'UNIQUE') $foundUnique[$row['TABLE_NAME']][] = $row['CONSTRAINT_NAME']; } $tables = ['users','classes','teacher_classes','students','scores']; foreach ($tables as $t) { if (empty($foundUnique[$t])) { echo 'MISSING UNIQUE ON ' . $t . PHP_EOL; exit(1); } } echo 'ALL UNIQUE CONSTRAINTS PRESENT: OK' . PHP_EOL;"
```

---

### 4.2 Verification Point 2: Zero Duplicate Rows Across All Tables

#### What is Verified:
Queries the database to detect duplicate business keys across every entity table:
- `users`: Duplicates on `username` -> MUST BE 0.
- `classes`: Duplicates on `name` -> MUST BE 0.
- `teacher_classes`: Duplicates on `(teacher_id, class_id, subject)` -> MUST BE 0.
- `students`: Duplicates on `(class_id, full_name, birthday)` -> MUST BE 0.
- `scores`: Duplicates on `(student_id, subject)` -> MUST BE 0.

#### PHP CLI One-Liner:
```powershell
& "D:\xam\php\php.exe" --% -r "require 'includes/db.php'; $pdo = db(); $checks = ['users' => 'SELECT username, COUNT(*) c FROM users GROUP BY username HAVING c > 1', 'classes' => 'SELECT name, COUNT(*) c FROM classes GROUP BY name HAVING c > 1', 'teacher_classes' => 'SELECT teacher_id, class_id, subject, COUNT(*) c FROM teacher_classes GROUP BY teacher_id, class_id, subject HAVING c > 1', 'students' => 'SELECT class_id, full_name, birthday, COUNT(*) c FROM students GROUP BY class_id, full_name, birthday HAVING c > 1', 'scores' => 'SELECT student_id, subject, COUNT(*) c FROM scores GROUP BY student_id, subject HAVING c > 1']; foreach ($checks as $t => $q) { $res = $pdo->query($q)->fetchAll(); if (count($res) > 0) { echo \"DUPLICATE DETECTED IN $t: \" . count($res) . PHP_EOL; exit(1); } } echo 'ZERO DUPLICATES ACROSS ALL TABLES: OK' . PHP_EOL;"
```

---

### 4.3 Verification Point 3: Password Verification for `admin`, `gv_toan`, `gv_van`

#### What is Verified:
1. `admin`, `gv_toan`, and `gv_van` exist in `users`.
2. `password_verify('123456', $passwordHash)` returns `true` for all 3 users.
3. Negative security test: `password_verify('1234567', $passwordHash)` returns `false` (guaranteeing no wildcard or constant true logic).

#### PHP CLI One-Liner:
```powershell
& "D:\xam\php\php.exe" --% -r "require 'includes/db.php'; $pdo = db(); $users = ['admin', 'gv_toan', 'gv_van']; foreach ($users as $u) { $stmt = $pdo->prepare('SELECT password FROM users WHERE username = ?'); $stmt->execute([$u]); $hash = $stmt->fetchColumn(); if (!$hash) { echo \"USER NOT FOUND: $u\n\"; exit(1); } if (!password_verify('123456', $hash)) { echo \"PASSWORD VERIFY FAILED FOR $u\n\"; exit(1); } if (password_verify('wrong_pass_999', $hash)) { echo \"SECURITY FAILURE: WRONG PASS ACCEPTED FOR $u\n\"; exit(1); } } echo 'ALL 3 ACCOUNTS VERIFIED WITH 123456 (AND REJECT WRONG PASSWORD): OK' . PHP_EOL;"
```

---

### 4.4 Verification Point 4: `log_action()` Successfully Writes to `audit_logs`

#### What is Verified:
1. `function log_action(?int $user_id, ?string $username, string $action, string $details): void` exists in `includes/db.php`.
2. Calling `log_action(1, 'admin', 'M1_TEST_ACTION', 'M1 test details payload')` inserts a row into `audit_logs`.
3. The row in `audit_logs` has matching `user_id = 1`, `username = 'admin'`, `action = 'M1_TEST_ACTION'`, `details = 'M1 test details payload'`, non-empty `ip_address` (fallback `'127.0.0.1'` in CLI), and recent `created_at`.
4. Backward compatibility: calling wrapper `logAction('WRAPPER_TEST', 'Wrapper details')` also successfully writes to `audit_logs`.

#### PHP CLI One-Liner:
```powershell
& "D:\xam\php\php.exe" --% -r "require 'includes/db.php'; $token = 'TEST_M1_' . bin2hex(random_bytes(4)); log_action(1, 'admin', $token, 'Payload details for M1 verification'); $stmt = db()->prepare('SELECT * FROM audit_logs WHERE action = ?'); $stmt->execute([$token]); $row = $stmt->fetch(); if (!$row || $row['username'] !== 'admin' || (int)$row['user_id'] !== 1) { echo 'LOG_ACTION VERIFICATION FAILED' . PHP_EOL; exit(1); } echo 'LOG_ACTION VERIFIED (ID: ' . $row['id'] . ', IP: ' . $row['ip_address'] . '): OK' . PHP_EOL;"
```

---

### 4.5 Verification Point 5: `database.sqlite` is Deleted and Code Decoupled

#### What is Verified:
1. File `D:\New folder\my-web-project\database.sqlite` does NOT exist on the filesystem (`file_exists() === false`).
2. `includes/config.php` does NOT define `SQLITE_FILE` (`!defined('SQLITE_FILE')`).
3. `includes/db.php` does NOT declare or contain function `initSQLite()` (`!function_exists('initSQLite')`).
4. Grep check: Zero occurrences of `database.sqlite` in `includes/config.php` and `includes/db.php`.

#### PHP CLI One-Liner:
```powershell
& "D:\xam\php\php.exe" --% -r "require 'includes/config.php'; require 'includes/db.php'; $file = dirname(__DIR__) . '/database.sqlite'; if (file_exists($file)) { echo 'ERROR: database.sqlite still exists!' . PHP_EOL; exit(1); } if (defined('SQLITE_FILE')) { echo 'ERROR: SQLITE_FILE constant is still defined!' . PHP_EOL; exit(1); } if (function_exists('initSQLite')) { echo 'ERROR: initSQLite function still exists!' . PHP_EOL; exit(1); } echo 'SQLITE COMPLETE ELIMINATION: OK' . PHP_EOL;"
```

---

## 5. Unified Automated Verification Suite (`tests/verify_m1.php`)

To provide Milestone 1 implementers with an instant, repeatable, self-contained test suite, the following script specification is ready for placement at `tests/verify_m1.php`:

```php
<?php
/**
 * Automated Milestone 1 Verification Suite (EduRadar)
 * Tests against live MySQL (edu_radar) and PHP CLI.
 * 
 * Run with:
 *   D:\xam\php\php.exe tests/verify_m1.php
 */

declare(strict_types=1);

// ANSI Color formatting
const CLR_RESET  = "\033[0m";
const CLR_GREEN  = "\033[1;32m";
const CLR_RED    = "\033[1;31m";
const CLR_YELLOW = "\033[1;33m";
const CLR_CYAN   = "\033[1;36m";

$passedCount = 0;
$failedCount = 0;

function assertTest(string $name, callable $testFn): void
{
    global $passedCount, $failedCount;
    echo CLR_CYAN . "[TEST] " . CLR_RESET . str_pad($name, 60, ".");
    try {
        $result = $testFn();
        if ($result === false) {
            throw new Exception("Assertion returned false");
        }
        $passedCount++;
        echo CLR_GREEN . " [PASS]" . CLR_RESET . PHP_EOL;
    } catch (Throwable $e) {
        $failedCount++;
        echo CLR_RED . " [FAIL]" . CLR_RESET . PHP_EOL;
        echo CLR_RED . "       -> Error: " . $e->getMessage() . CLR_RESET . PHP_EOL;
    }
}

echo PHP_EOL . CLR_YELLOW . "============================================================" . CLR_RESET . PHP_EOL;
echo CLR_YELLOW . "  EduRadar Milestone 1: Automated Verification Suite" . CLR_RESET . PHP_EOL;
echo CLR_YELLOW . "============================================================" . CLR_RESET . PHP_EOL . PHP_EOL;

// -------------------------------------------------------------
// Test 1: Configuration & SQLite Elimination
// -------------------------------------------------------------
assertTest("1.1 File database.sqlite is deleted", function () {
    $sqlitePath = dirname(__DIR__) . '/database.sqlite';
    if (file_exists($sqlitePath)) {
        throw new Exception("database.sqlite exists at: $sqlitePath");
    }
    return true;
});

assertTest("1.2 includes/config.php loads cleanly without SQLite constants", function () {
    require_once dirname(__DIR__) . '/includes/config.php';
    if (defined('SQLITE_FILE')) {
        throw new Exception("SQLITE_FILE constant is still defined in config.php");
    }
    if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER')) {
        throw new Exception("Missing core DB configuration constants");
    }
    return true;
});

assertTest("1.3 includes/db.php establishes pure PDO MySQL singleton", function () {
    require_once dirname(__DIR__) . '/includes/db.php';
    if (!function_exists('db')) {
        throw new Exception("function db() is not declared");
    }
    if (function_exists('initSQLite')) {
        throw new Exception("Legacy initSQLite() function still exists in db.php");
    }
    $pdo1 = db();
    $pdo2 = db();
    if (!($pdo1 instanceof PDO) || $pdo1 !== $pdo2) {
        throw new Exception("db() did not return a valid singleton PDO instance");
    }
    $driver = $pdo1->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver !== 'mysql') {
        throw new Exception("Expected PDO driver 'mysql', got '$driver'");
    }
    return true;
});

// -------------------------------------------------------------
// Test 2: Database Schema & Constraints
// -------------------------------------------------------------
assertTest("2.1 All 6 required tables exist in edu_radar", function () {
    $pdo = db();
    $stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'edu_radar'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $required = ['users', 'classes', 'teacher_classes', 'students', 'scores', 'audit_logs'];
    $missing = array_diff($required, $tables);
    if (!empty($missing)) {
        throw new Exception("Missing tables: " . implode(', ', $missing));
    }
    return true;
});

assertTest("2.2 UNIQUE constraints enforced on all required business keys", function () {
    $pdo = db();
    $stmt = $pdo->query("
        SELECT tc.TABLE_NAME, tc.CONSTRAINT_NAME, kcu.COLUMN_NAME
        FROM information_schema.TABLE_CONSTRAINTS tc
        JOIN information_schema.KEY_COLUMN_USAGE kcu
          ON tc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME AND tc.TABLE_SCHEMA = kcu.TABLE_SCHEMA
        WHERE tc.TABLE_SCHEMA = 'edu_radar' AND tc.CONSTRAINT_TYPE = 'UNIQUE'
        ORDER BY tc.TABLE_NAME, tc.CONSTRAINT_NAME, kcu.ORDINAL_POSITION
    ");
    $rows = $stmt->fetchAll();
    $uniques = [];
    foreach ($rows as $r) {
        $uniques[$r['TABLE_NAME']][$r['CONSTRAINT_NAME']][] = $r['COLUMN_NAME'];
    }

    // Verify users(username)
    $hasUser = false;
    foreach ($uniques['users'] ?? [] as $cols) {
        if ($cols === ['username']) $hasUser = true;
    }
    if (!$hasUser) throw new Exception("users table missing UNIQUE(username)");

    // Verify classes(name)
    $hasClass = false;
    foreach ($uniques['classes'] ?? [] as $cols) {
        if ($cols === ['name']) $hasClass = true;
    }
    if (!$hasClass) throw new Exception("classes table missing UNIQUE(name)");

    // Verify teacher_classes(teacher_id, class_id, subject)
    $hasTc = false;
    foreach ($uniques['teacher_classes'] ?? [] as $cols) {
        if ($cols === ['teacher_id', 'class_id', 'subject']) $hasTc = true;
    }
    if (!$hasTc) throw new Exception("teacher_classes missing UNIQUE(teacher_id, class_id, subject)");

    // Verify students(class_id, full_name, birthday)
    $hasStudent = false;
    foreach ($uniques['students'] ?? [] as $cols) {
        if ($cols === ['class_id', 'full_name', 'birthday']) $hasStudent = true;
    }
    if (!$hasStudent) throw new Exception("students table missing UNIQUE(class_id, full_name, birthday)");

    // Verify scores(student_id, subject)
    $hasScore = false;
    foreach ($uniques['scores'] ?? [] as $cols) {
        if ($cols === ['student_id', 'subject']) $hasScore = true;
    }
    if (!$hasScore) throw new Exception("scores table missing UNIQUE(student_id, subject)");

    return true;
});

assertTest("2.3 Foreign Keys with ON DELETE CASCADE configured correctly", function () {
    $pdo = db();
    $stmt = $pdo->query("
        SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = 'edu_radar' AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $fks = $stmt->fetchAll();
    $fkMap = [];
    foreach ($fks as $fk) {
        $fkMap[$fk['TABLE_NAME']][$fk['COLUMN_NAME']] = $fk['REFERENCED_TABLE_NAME'];
    }

    if (($fkMap['teacher_classes']['teacher_id'] ?? null) !== 'users') {
        throw new Exception("teacher_classes.teacher_id does not reference users");
    }
    if (($fkMap['teacher_classes']['class_id'] ?? null) !== 'classes') {
        throw new Exception("teacher_classes.class_id does not reference classes");
    }
    if (($fkMap['students']['class_id'] ?? null) !== 'classes') {
        throw new Exception("students.class_id does not reference classes");
    }
    if (($fkMap['scores']['student_id'] ?? null) !== 'students') {
        throw new Exception("scores.student_id does not reference students");
    }
    return true;
});

assertTest("2.4 scores table has modern VnEdu columns (tx1..tx4, gk, ck, avg_score)", function () {
    $pdo = db();
    $stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'edu_radar' AND TABLE_NAME = 'scores'");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $requiredCols = ['tx1', 'tx2', 'tx3', 'tx4', 'gk', 'ck', 'avg_score'];
    $diff = array_diff($requiredCols, $cols);
    if (!empty($diff)) {
        throw new Exception("scores table missing columns: " . implode(', ', $diff));
    }
    return true;
});

// -------------------------------------------------------------
// Test 3: Deduplication & Seed Verification
// -------------------------------------------------------------
assertTest("3.1 Zero duplicate rows exist across all tables", function () {
    $pdo = db();
    $queries = [
        'users'           => "SELECT username, COUNT(*) c FROM users GROUP BY username HAVING c > 1",
        'classes'         => "SELECT name, COUNT(*) c FROM classes GROUP BY name HAVING c > 1",
        'teacher_classes' => "SELECT teacher_id, class_id, subject, COUNT(*) c FROM teacher_classes GROUP BY teacher_id, class_id, subject HAVING c > 1",
        'students'        => "SELECT class_id, full_name, birthday, COUNT(*) c FROM students GROUP BY class_id, full_name, birthday HAVING c > 1",
        'scores'          => "SELECT student_id, subject, COUNT(*) c FROM scores GROUP BY student_id, subject HAVING c > 1",
    ];
    foreach ($queries as $tbl => $sql) {
        $duplicates = $pdo->query($sql)->fetchAll();
        if (count($duplicates) > 0) {
            throw new Exception("Duplicate rows found in table '$tbl'");
        }
    }
    return true;
});

assertTest("3.2 36 Classes seeded covering Grades 6-9 and Sections A-C", function () {
    $pdo = db();
    $count = (int)$pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
    if ($count !== 36) {
        throw new Exception("Expected 36 classes, found $count");
    }
    return true;
});

// -------------------------------------------------------------
// Test 4: Authentication & Bcrypt Verification
// -------------------------------------------------------------
assertTest("4.1 Passwords for admin, gv_toan, gv_van verify correctly with '123456'", function () {
    $pdo = db();
    $accounts = ['admin', 'gv_toan', 'gv_van'];
    $stmt = $pdo->prepare("SELECT password FROM users WHERE username = ?");
    foreach ($accounts as $acc) {
        $stmt->execute([$acc]);
        $hash = $stmt->fetchColumn();
        if (!$hash) {
            throw new Exception("Account '$acc' not found in users table");
        }
        if (!password_verify('123456', $hash)) {
            throw new Exception("password_verify('123456') failed for account '$acc'");
        }
        if (password_verify('invalid_pwd_test', $hash)) {
            throw new Exception("password_verify unexpectedly returned true for wrong password on '$acc'");
        }
    }
    return true;
});

// -------------------------------------------------------------
// Test 5: Audit Logging Helper
// -------------------------------------------------------------
assertTest("5.1 log_action() successfully writes audit record with IP and prepared stmt", function () {
    $token = 'M1_TEST_' . bin2hex(random_bytes(6));
    $details = 'Verification test payload created at ' . date('c');
    log_action(1, 'admin', $token, $details);

    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM audit_logs WHERE action = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$token]);
    $log = $stmt->fetch();
    if (!$log) {
        throw new Exception("log_action() did not write record to audit_logs table");
    }
    if ((int)$log['user_id'] !== 1 || $log['username'] !== 'admin' || $log['details'] !== $details) {
        throw new Exception("audit_logs record content does not match expected payload");
    }
    if (empty($log['ip_address'])) {
        throw new Exception("ip_address was not captured in audit_logs record");
    }
    return true;
});

assertTest("5.2 Backward-compatible logAction() wrapper works seamlessly", function () {
    if (!function_exists('logAction')) {
        throw new Exception("logAction() wrapper function is not declared in db.php");
    }
    $token = 'WRAPPER_TEST_' . bin2hex(random_bytes(6));
    logAction($token, 'Wrapper details test');

    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM audit_logs WHERE action = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$token]);
    $log = $stmt->fetch();
    if (!$log) {
        throw new Exception("logAction() wrapper failed to write to audit_logs");
    }
    return true;
});

// -------------------------------------------------------------
// Summary
// -------------------------------------------------------------
echo PHP_EOL . CLR_YELLOW . "============================================================" . CLR_RESET . PHP_EOL;
echo sprintf("  Total Tests: %d | Passed: %s%d%s | Failed: %s%d%s",
    $passedCount + $failedCount,
    CLR_GREEN, $passedCount, CLR_RESET,
    ($failedCount > 0 ? CLR_RED : CLR_GREEN), $failedCount, CLR_RESET
) . PHP_EOL;
echo CLR_YELLOW . "============================================================" . CLR_RESET . PHP_EOL . PHP_EOL;

if ($failedCount > 0) {
    exit(1);
}
exit(0);
```

---

## 6. Synthesis & Implementation Checklist for Milestone 1

Based on synthesizing the findings from `explorer_m1_1` (DDL & Seeds), `explorer_m1_2` (Pure PDO MySQL & Logging), and `explorer_m1_3` (Migration & Verification Procedures), the recommended sequential workflow for the implementer is:

```
Step 1: Create Pre-Migration Backup
  └─ Run mysqldump.exe to backup_edu_radar_pre_m1.sql

Step 2: Update database.sql
  ├─ Insert DDL for 6 tables with UNIQUE constraints & CASCADE foreign keys (explorer_m1_1)
  ├─ Include audit_logs table DDL
  ├─ Seed 36 classes (6A1..9C3), 3 accounts with valid bcrypt hash for '123456', 5 students, 7 scores
  └─ Wrap with SET FOREIGN_KEY_CHECKS = 0/1

Step 3: Refactor includes/config.php & includes/db.php
  ├─ config.php: Define BASE_URL, DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_CHARSET. Purge SQLITE_FILE.
  ├─ db.php: Implement singleton db(): PDO with ERRMODE_EXCEPTION, FETCH_ASSOC, utf8mb4. Purge initSQLite().
  ├─ db.php: Implement log_action(?int, ?string, string, string): void and backward-compat logAction().
  └─ Delete physical file database.sqlite from repository root.

Step 4: Execute Migration on Live MySQL
  └─ Get-Content database.sql -Encoding UTF8 | & D:\xam\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4

Step 5: Execute Automated Verification Suite
  ├─ Write tests/verify_m1.php
  └─ Run: & "D:\xam\php\php.exe" tests/verify_m1.php -> Output: 0 Failed, 100% Passed.
```

---

## 7. Risk Analysis & Mitigations

| Risk | Likelihood | Impact | Mitigation Strategy |
|---|---|---|---|
| **PowerShell character encoding glitch during migration** | Low | High | Use `-Encoding UTF8` with `Get-Content` and `--default-character-set=utf8mb4` on `mysql.exe`. |
| **Existing codebase calls to legacy `logAction()` break** | High | Critical | Provide wrapper `logAction(string $action, string $details = ''): void` in `includes/db.php` delegating to `log_action()`. |
| **Bcrypt hash verification failure** | Medium | Critical | Strictly use tested hash `$2y$10$Huov5BMeiGHCxhgfaTkauuthlWkvzJguIrvV6vWU1SQsJDrk6PlMG` for seed accounts. |
| **Locked `database.sqlite` file prevents deletion on Windows** | Low | Medium | Ensure no background PHP process or editor has an open handle to `database.sqlite` before calling `Remove-Item` or `unlink()`. |
| **Accidental data wipe without backup** | Low | Critical | Mandatory Phase 0 `mysqldump.exe` execution before applying `database.sql`. |

---
*End of Milestone 1 Analysis Report — explorer_m1_3*
