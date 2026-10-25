# Audit Report: Live Environment & MySQL Database (edu_radar)

**Date**: 2026-10-17  
**Auditor**: explorer_survey_2  
**Target Project**: `D:\New folder\my-web-project`  
**Execution Environment**: XAMPP (Apache 2.4.58 on port 80, MariaDB 10.4.32 on port 3306, PHP 8.0.30 at `D:\xam\php\php.exe`)

---

## 1. Executive Summary

A comprehensive, non-destructive live audit was performed across the runtime environment (XAMPP Apache, PHP CLI, MariaDB MySQL) and the database `edu_radar`.

Key findings:
1. **Live Environment Operational**: Apache port 80, MariaDB port 3306, and PHP 8.0.30 are fully functional. The NTFS junction `D:\xam\htdocs\my-web-project` -> `D:\New folder\my-web-project` correctly maps incoming web requests to the project directory.
2. **Database Integrity & Constraints**:
   - `edu_radar` has 6 active tables: `audit_logs`, `classes`, `scores`, `students`, `teacher_classes`, `users`.
   - Currently 0 duplicate rows exist across all tables.
   - **Critical Constraint Defect**: Table `students` is missing `UNIQUE(class_id, full_name, birthday)` in both live MySQL and `database.sql`. Direct inserts or repeated imports could create duplicate students.
   - Table `classes` has `UNIQUE(name)`.
   - Table `teacher_classes` has `UNIQUE(teacher_id, class_id, subject)`.
   - Table `scores` has `UNIQUE(student_id, subject)`.
   - Table `users` has `UNIQUE(username)`.
3. **Security Vulnerabilities in Codebase**:
   - **Hardcoded Password Bypass**: `login.php:16` contains `(password_verify($password, $user['password']) || $password === '123456')`, allowing any user account to be compromised with `123456`.
   - **Authorization Bypass in Gradebook**: `nhap_diem.php` accepts `class_id` and `subject` directly from `$_GET` and processes `save_scores` via `$_POST` without verifying whether `(class_id, subject)` belongs to the logged-in teacher in `teacher_classes`.
   - **Admin Missing Actions**: Admin cannot create/delete classes or create teacher accounts from `admin.php`.
4. **Discrepancies in `database.sql`**:
   - `database.sql` completely omits the `audit_logs` table definition.
   - `database.sql` retains a legacy `DROP TABLE IF EXISTS attendance;`.
   - `database.sql` seeds only 8 classes (live DB has 36 classes covering grades 6–9, sections A–C).
   - `database.sql` seeds only 3 assignments (live DB has 14).
5. **Format & Feature Gaps**:
   - Neither `admin.php` nor `nhap_diem.php` supports `.xlsx` format (only basic `.csv` parsing with `fgetcsv`).
   - `index.php` contains an unrequested "Copy Tin Nhắn Zalo" button and toast notification, violating R4.
   - `database.sqlite` (40KB) and SQLite fallback code exist in `includes/config.php` and `includes/db.php`, violating R1.

---

## 2. Environment Verification Evidence

### 2.1 PHP Runtime
- **Command**: `& "D:\xam\php\php.exe" -v`
- **Output**:
  ```text
  PHP 8.0.30 (cli) (built: Sep  1 2023 14:15:38) ( ZTS Visual C++ 2019 x64 )
  Copyright (c) The PHP Group
  Zend Engine v4.0.30, Copyright (c) Zend Technologies
  ```
- **Loaded Modules (`php.exe -m`)**:
  `pdo_mysql`, `zip`, `SimpleXML`, `xmlreader`, `xmlwriter`, `dom`, `mbstring`, `curl`, `json`, `session`, `openssl`, `fileinfo`.
  *Verification*: Built-in `zip` (ZipArchive) and `SimpleXML` are present, enabling native `.xlsx` parsing and generation without Composer dependencies.

### 2.2 Apache Web Server
- **Server Version**: Apache/2.4.58 (Win64) OpenSSL/3.1.3 PHP/8.0.30 on port 80.
- **Junction Verification**:
  ```powershell
  Get-Item "D:\xam\htdocs\my-web-project"
  # Target: {D:\New folder\my-web-project} (LinkType: Junction)
  ```
- **HTTP Header Test**:
  - `curl -I http://localhost/my-web-project/` -> `HTTP/1.1 302 Found`, `Location: login.php`
  - `curl -I http://localhost/my-web-project/admin.php` -> `HTTP/1.1 403 Forbidden` (non-admin blocked)

### 2.3 MySQL / MariaDB Server
- **Server Version**: MariaDB `10.4.32-MariaDB`
- **Port**: 3306 (127.0.0.1, user `root`, empty password)
- **Active Database**: `edu_radar` (Character set: `utf8mb4`, Collation: `utf8mb4_unicode_ci`)

---

## 3. MySQL Schema & Table Structure Analysis

### 3.1 Table Inventory & Record Counts

| Table Name | Live Row Count | Primary Key | Engine | Collation | Purpose & Responsibility |
|---|---|---|---|---|---|
| `users` | 3 | `id` (AUTO_INCREMENT) | InnoDB | `utf8mb4_unicode_ci` | User credentials, roles (`admin`, `teacher`), full names |
| `classes` | 36 | `id` (AUTO_INCREMENT) | InnoDB | `utf8mb4_unicode_ci` | Class definitions across grades 6–9, sections A–C |
| `teacher_classes` | 14 | `id` (AUTO_INCREMENT) | InnoDB | `utf8mb4_unicode_ci` | Teaching assignments mapping teacher to `(class_id, subject)` |
| `students` | 5 | `id` (AUTO_INCREMENT) | InnoDB | `utf8mb4_unicode_ci` | Student roster assigned to classes |
| `scores` | 5 | `id` (AUTO_INCREMENT) | InnoDB | `utf8mb4_unicode_ci` | VnEdu grade records per `(student_id, subject)` |
| `audit_logs` | 22 | `id` (AUTO_INCREMENT) | InnoDB | `utf8mb4_unicode_ci` | Immutable event log of system actions |

### 3.2 Detailed Schema Definitions

#### 1. `users` Table
```sql
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('admin','teacher') NOT NULL DEFAULT 'teacher',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 2. `classes` Table
```sql
CREATE TABLE `classes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `grade` int(11) NOT NULL,
  `section` varchar(10) NOT NULL,
  `school_year` varchar(20) NOT NULL DEFAULT '2026-2027',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```
- Existing classes: 36 total.
  - Grade 6: 6A1, 6A2, 6A3, 6B1, 6B2, 6B3, 6C1, 6C2, 6C3 (9 classes)
  - Grade 7: 7A1, 7A2, 7A3, 7B1, 7B2, 7B3, 7C1, 7C2, 7C3 (9 classes)
  - Grade 8: 8A1, 8A2, 8A3, 8B1, 8B2, 8B3, 8C1, 8C2, 8C3 (9 classes)
  - Grade 9: 9A1, 9A2, 9A3, 9B1, 9B2, 9B3, 9C1, 9C2, 9C3 (9 classes)

#### 3. `teacher_classes` Table
```sql
CREATE TABLE `teacher_classes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `subject` varchar(50) NOT NULL DEFAULT 'Toán',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_assignment` (`teacher_id`,`class_id`,`subject`),
  KEY `class_id` (`class_id`),
  CONSTRAINT `teacher_classes_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `teacher_classes_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 4. `students` Table
```sql
CREATE TABLE `students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `birthday` date DEFAULT NULL,
  `parent_phone` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  CONSTRAINT `students_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```
- **Defect**: Missing `UNIQUE KEY unique_student_in_class (class_id, full_name, birthday)`.

#### 5. `scores` Table
```sql
CREATE TABLE `scores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `subject` varchar(50) NOT NULL DEFAULT 'Toán',
  `m1` decimal(4,2) DEFAULT NULL,
  `m2` decimal(4,2) DEFAULT NULL,
  `p15_1` decimal(4,2) DEFAULT NULL,
  `p15_2` decimal(4,2) DEFAULT NULL,
  `t1_1` decimal(4,2) DEFAULT NULL,
  `t1_2` decimal(4,2) DEFAULT NULL,
  `gk` decimal(4,2) DEFAULT NULL,
  `ck` decimal(4,2) DEFAULT NULL,
  `avg_score` decimal(4,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_subject` (`student_id`,`subject`),
  CONSTRAINT `scores_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 6. `audit_logs` Table
```sql
CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 4. Constraint & Duplicate Row Audit

An integrity query was executed across all tables:
```sql
SELECT 'classes duplicate name' AS dup_type, count(*) AS dup_count FROM (SELECT name FROM classes GROUP BY name HAVING count(*) > 1) t
UNION ALL
SELECT 'users duplicate username', count(*) FROM (SELECT username FROM users GROUP BY username HAVING count(*) > 1) t
UNION ALL
SELECT 'teacher_classes duplicate assignment', count(*) FROM (SELECT teacher_id, class_id, subject FROM teacher_classes GROUP BY teacher_id, class_id, subject HAVING count(*) > 1) t
UNION ALL
SELECT 'scores duplicate student_subject', count(*) FROM (SELECT student_id, subject FROM scores GROUP BY student_id, subject HAVING count(*) > 1) t
UNION ALL
SELECT 'students duplicate in class by name+bday', count(*) FROM (SELECT class_id, full_name, birthday FROM students GROUP BY class_id, full_name, birthday HAVING count(*) > 1) t
UNION ALL
SELECT 'students duplicate in class by name', count(*) FROM (SELECT class_id, full_name FROM students GROUP BY class_id, full_name HAVING count(*) > 1) t;
```
**Results**:
- `classes duplicate name`: 0
- `users duplicate username`: 0
- `teacher_classes duplicate assignment`: 0
- `scores duplicate student_subject`: 0
- `students duplicate in class by name+bday`: 0
- `students duplicate in class by name`: 0

**Constraint Assessment**:
1. `classes`: Protected by `UNIQUE(name)`.
2. `teacher_classes`: Protected by `UNIQUE(teacher_id, class_id, subject)`.
3. `scores`: Protected by `UNIQUE(student_id, subject)`.
4. `users`: Protected by `UNIQUE(username)`.
5. `students`: **NOT PROTECTED BY DB CONSTRAINT**. Must add `UNIQUE KEY unique_student_in_class (class_id, full_name, birthday)` so MySQL rejects duplicate student insertions automatically.

---

## 5. Users Table: Credentials, Hashing, and Roles

Live query on `users`:
```text
id | username | password                                                     | full_name            | email | role    | created_at
1  | admin    | $2y$10$Huov5BMeiGHCxhgfaTkauuthlWkvzJguIrvV6vWU1SQsJDrk6PlMG | Quản Trị Viên Trường | NULL  | admin   | 2026-10-17 10:44:51
2  | gv_toan  | $2y$10$Huov5BMeiGHCxhgfaTkauuthlWkvzJguIrvV6vWU1SQsJDrk6PlMG | Thầy Nguyễn Văn Giáo | NULL  | teacher | 2026-10-17 10:44:51
3  | gv_van   | $2y$10$Huov5BMeiGHCxhgfaTkauuthlWkvzJguIrvV6vWU1SQsJDrk6PlMG | Cô Lê Thị Hương      | NULL  | teacher | 2026-10-17 10:44:51
```

- **Password Verification**:
  Verified via PHP CLI: `password_verify('123456', '$2y$10$Huov5BMeiGHCxhgfaTkauuthlWkvzJguIrvV6vWU1SQsJDrk6PlMG')` returns `true`.
  All 3 accounts use standard bcrypt (`PASSWORD_DEFAULT`), conforming to secure storage.
- **Critical Code Vulnerability**:
  `login.php`, line 16:
  ```php
  if ($user && (password_verify($password, $user['password']) || $password === '123456'))
  ```
  The fallback `|| $password === '123456'` allows authentication even if a password is changed, completely bypassing hashing validation. This must be eliminated.

---

## 6. Comparison: `database.sql` vs. Live MySQL State

| Aspect | `database.sql` | Live MySQL (`edu_radar`) | Requirement / Recommended Fix |
|---|---|---|---|
| `audit_logs` table | Missing entirely | Table exists with 22 rows | Add `CREATE TABLE audit_logs (...)` to `database.sql` |
| `attendance` table | Drops `attendance` (leftover) | Does not exist | Remove `DROP TABLE IF EXISTS attendance;` from `database.sql` |
| `students` constraints | Lacks `UNIQUE(class_id, full_name, birthday)` | Lacks `UNIQUE(class_id, full_name, birthday)` | Add `UNIQUE KEY unique_student_in_class (class_id, full_name, birthday)` to both |
| `classes` seed rows | Only 8 classes (`6A1`, `6A2`, `6B1`, `7A1`, `7B1`, `8A1`, `9A1`, `9C1`) | 36 classes (Grades 6–9, Sections A–C, Classes 1–3) | Update `database.sql` to seed all 36 classes |
| `teacher_classes` seed rows | 3 rows | 14 rows | Keep clean seed assignments in `database.sql` matching standard curriculum |
| Password hash string | `$2y$10$4y9pQzS/m7y9xY8Z/xH0ve2w5.JjK.RjT18P4q7lZ0eQ0/a4uCqS.` | `$2y$10$Huov5BMeiGHCxhgfaTkauuthlWkvzJguIrvV6vWU1SQsJDrk6PlMG` | Both hash `'123456'`; unify on a consistent hash in `database.sql` |

---

## 7. Application Code Gap Analysis

### 7.1 Access Control & Authorization (R1)
- Non-admin access to `admin.php` is properly rejected with HTTP 403 (`admin.php:6-16`).
- However, `nhap_diem.php` has **zero server-side authorization check** for teachers:
  - If teacher `gv_toan` modifies the URL to `?class_id=3&subject=Ngữ Văn`, they can view all students in class 3 and edit scores for Ngữ Văn despite not being assigned to it.
  - POST requests to `nhap_diem.php` with `action=save_scores` execute `INSERT` / `UPDATE` on `scores` for whatever `$_GET['subject']` is set, without checking if `(class_id, subject)` exists in `teacher_classes` for `$_SESSION['user_id']`.
  - Fix: Add server-side check ensuring `$_SESSION['role'] === 'admin'` OR `(class_id, subject)` exists in `teacher_classes` for `$_SESSION['user_id']`. If unauthorized, return HTTP 403 or redirect without modifying DB.

### 7.2 Admin Page (`admin.php` — R2)
- Missing Class Management: Admin cannot create new classes (`name`, `grade`, `section`) or delete empty classes.
- Missing Teacher Account Creation: Admin cannot create new teacher accounts with username and password.
- Missing `.xlsx` Support: Import only parses CSV via `fgetcsv`. Need standard VnEdu `.xlsx` (and `.csv`) import with deduplication on `(class_id, full_name, birthday)`.
- Missing Sample `.xlsx` File: Only `sample_students.csv` is provided.

### 7.3 Gradebook Page (`nhap_diem.php` — R3)
- Columns: Current table renders `M.1`, `M.2`, `15' (1)`, `15' (2)`, `1T (1)`, `ĐĐGgk`, `ĐĐGck`, `ĐTB Môn`.
- Formula: Circular 22 VnEdu standard requires:
  `ĐTBmhk = (Tổng ĐĐGtx + ĐĐGgk*2 + ĐĐGck*3) / (Số cột ĐĐGtx có điểm + 5)` when GK & CK are present, or weighted average of entered columns, rounded to 1 decimal place.
- Missing `.xlsx` Support: Gradebook import form accepts only `.csv, .txt`. Must support standard VnEdu `.xlsx` binary files.
- Missing Sample `.xlsx` Gradebook File: Only `sample_vnedu.csv` is provided.

### 7.4 Early-Warning Radar (`index.php` — R4)
- Current code has lines 209–211:
  `<button class="btn copy-btn" data-text="...">📋 Copy Tin Nhắn Zalo</button>` and toast notification `Đã sao chép tin nhắn Zalo!`.
- R4 explicitly forbids the Zalo button. It must be completely removed.

### 7.5 Audit Logging (R5)
- `logAction()` in `includes/db.php` is functional, but lacks logging for:
  - Class creation/deletion
  - Teacher account creation
  - Unauthorized grade edit attempts in `nhap_diem.php`

### 7.6 Cleanup
- `database.sqlite` (40KB) must be deleted.
- SQLite code paths in `includes/config.php` and `includes/db.php` must be removed.

---

## 8. Recommendations for Implementation Plan

1. **Schema & Migration**:
   - Add `UNIQUE KEY unique_student_in_class (class_id, full_name, birthday)` to `students` table in live MySQL.
   - Update `database.sql` to include `audit_logs`, drop `attendance` reference, add the `students` unique key, and include all 36 classes in seed data.
2. **Security Hardening**:
   - Remove hardcoded `'123456'` bypass in `login.php`.
   - Implement teacher assignment check in `nhap_diem.php` (both GET and POST).
3. **Native XLSX Parser & Generator**:
   - Implement a lightweight, zero-dependency `.xlsx` reader and sample generator helper using PHP's built-in `ZipArchive` and `SimpleXML`.
   - Update `admin.php` and `nhap_diem.php` to accept both `.xlsx` and `.csv`.
   - Generate `sample_students.xlsx` and `sample_vnedu.xlsx`.
4. **UI Refactoring**:
   - Remove Zalo buttons and dead modal code from `index.php` and `main.js`.
   - Implement Class Creation / Deletion and Teacher Account Creation modals/forms in `admin.php`.
   - Ensure clean Stitch UI styling across all 4 pages.
5. **Testing**:
   - Build `tests/run_tests.php` to verify all acceptance criteria using `D:\xam\php\php.exe`.
