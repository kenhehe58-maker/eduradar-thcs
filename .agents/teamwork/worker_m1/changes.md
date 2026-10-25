# Milestone 1 Code Changes Report

**Agent**: `worker_m1` (teamwork_preview_worker)  
**Milestone**: Milestone 1 (Database Overhaul & Data Access Layer)  
**Date**: 2026-10-17  
**Working Directory**: `D:\New folder\my-web-project\.agents\teamwork\worker_m1`  

---

## 1. Summary of Modifications

| File Path | Action | Description |
|---|---|---|
| `database.sql` | Modified / Overhauled | Created clean 6-table DDL (`users`, `classes`, `teacher_classes`, `students`, `scores`, `audit_logs`) in `utf8mb4_unicode_ci` with InnoDB; enforced all required `UNIQUE` constraints and cascading foreign keys; modernized `scores` columns to VnEdu / Thông tư 22 (`tx1..tx4, gk, ck, avg_score, updated_at`); seeded 36 classes (6A1..9C3), 3 verified accounts (`admin`, `gv_toan`, `gv_van`) with working bcrypt hash for `'123456'`, teaching assignments, 5 sample students, 7 sample scores with mathematically accurate averages, and initial audit log. |
| `includes/config.php` | Modified / Refactored | Configured pure MySQL constants (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET`, `BASE_URL`, `APP_NAME`, `PASS_SCORE`); removed all SQLite configuration (`SQLITE_FILE`, `DB_DRIVER`) and legacy constant (`ABSENCE_RED`). |
| `includes/db.php` | Modified / Refactored | Implemented singleton `db(): PDO` connecting to MariaDB with `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`, `PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC`, `PDO::ATTR_EMULATE_PREPARES => false`, and `utf8mb4` charset; implemented `log_action(?int $user_id, ?string $username, string $action, string $details = ''): void` with secure client IP resolution and prepared statements; added backward-compatible `logAction(string $action, string $details = ''): void` wrapper; purged all SQLite connection logic and the 64-line `initSQLite()` function. |
| `database.sqlite` | Deleted | Physically deleted file from root directory (`40,960` bytes purged). |
| `tests/verify_m1.php` | Created | Automated CLI test suite executing 12 test assertions covering configuration, SQLite elimination, schema & constraints, deduplication, 36 classes seed, bcrypt password verification, and audit logging helpers. Verified 100% pass (0 failures). |

---

## 2. Detailed Code Changes

### 2.1 `database.sql`
- **Tables Drop Order**: Wrapped table drops in `SET FOREIGN_KEY_CHECKS = 0;` and `SET FOREIGN_KEY_CHECKS = 1;` dropping `audit_logs`, `scores`, `students`, `teacher_classes`, `classes`, `users`, `attendance`.
- **`users` Table**:
  - `id INT AUTO_INCREMENT PRIMARY KEY`
  - `username VARCHAR(50) NOT NULL UNIQUE KEY unique_username`
  - `password VARCHAR(255) NOT NULL`
  - `full_name VARCHAR(100) NOT NULL`
  - `role ENUM('admin', 'teacher') NOT NULL DEFAULT 'teacher'`
  - `created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP`
- **`classes` Table**:
  - `id INT AUTO_INCREMENT PRIMARY KEY`
  - `name VARCHAR(50) NOT NULL UNIQUE KEY unique_class_name`
  - `grade INT NOT NULL KEY idx_grade`
  - `section VARCHAR(10) NOT NULL DEFAULT 'A'`
  - `created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP`
- **`teacher_classes` Table**:
  - `id INT AUTO_INCREMENT PRIMARY KEY`
  - `teacher_id INT NOT NULL`, `class_id INT NOT NULL`, `subject VARCHAR(50) NOT NULL`
  - `UNIQUE KEY unique_assignment (teacher_id, class_id, subject)`
  - `CONSTRAINT fk_tc_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE`
  - `CONSTRAINT fk_tc_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE`
- **`students` Table**:
  - `id INT AUTO_INCREMENT PRIMARY KEY`
  - `class_id INT NOT NULL`
  - `student_code VARCHAR(50) DEFAULT NULL KEY idx_student_code`
  - `full_name VARCHAR(100) NOT NULL`
  - `birthday DATE DEFAULT NULL`
  - `gender VARCHAR(10) DEFAULT NULL`
  - `phone VARCHAR(20) DEFAULT NULL`
  - `created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP`
  - `UNIQUE KEY unique_class_student (class_id, full_name, birthday)`
  - `CONSTRAINT fk_students_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE`
- **`scores` Table**:
  - `id INT AUTO_INCREMENT PRIMARY KEY`
  - `student_id INT NOT NULL KEY idx_student_id`
  - `subject VARCHAR(50) NOT NULL`
  - `tx1 DECIMAL(4,2) DEFAULT NULL`, `tx2 DECIMAL(4,2) DEFAULT NULL`, `tx3 DECIMAL(4,2) DEFAULT NULL`, `tx4 DECIMAL(4,2) DEFAULT NULL`
  - `gk DECIMAL(4,2) DEFAULT NULL`, `ck DECIMAL(4,2) DEFAULT NULL`
  - `avg_score DECIMAL(4,2) DEFAULT NULL`
  - `updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`
  - `UNIQUE KEY unique_student_subject (student_id, subject)`
  - `CONSTRAINT fk_scores_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE`
- **`audit_logs` Table**:
  - `id INT AUTO_INCREMENT PRIMARY KEY`
  - `user_id INT DEFAULT NULL KEY idx_user_id`
  - `username VARCHAR(50) DEFAULT NULL`
  - `action VARCHAR(100) NOT NULL KEY idx_action`
  - `details TEXT DEFAULT NULL`
  - `ip_address VARCHAR(45) DEFAULT NULL`
  - `created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP KEY idx_created_at`
- **Seed Data**:
  - Users: `admin`, `gv_toan`, `gv_van` with verified hash `$2y$10$Huov5BMeiGHCxhgfaTkauuthlWkvzJguIrvV6vWU1SQsJDrk6PlMG`
  - Classes: 36 rows (`6A1`..`6C3`, `7A1`..`7C3`, `8A1`..`8C3`, `9A1`..`9C3`) with sequential primary keys 1..36
  - Assignments: `gv_toan` -> 6A1 (Toán), 7A1 (Toán); `gv_van` -> 6A1 (Ngữ Văn)
  - Students: 5 students in 6A1 with VnEdu student codes (`HS6A101`..`HS6A105`), birthdays, gender, and phone numbers
  - Scores: 7 rows with verified weighted averages covering Red (Huy: avg 4.0), Yellow (Trang: avg 6.1 with tx3=4.5), and Green (An: 5.8 & 7.9, Khang: 8.2 & 8.4, Ngọc: 9.2)
  - Initial system audit log

### 2.2 `includes/config.php`
- Cleaned of all SQLite references (`SQLITE_FILE`, `DB_DRIVER`).
- Cleaned of legacy unused constant `ABSENCE_RED`.
- Added `BASE_URL = '/my-web-project/'`.
- Added `DB_PORT = 3306`, `DB_CHARSET = 'utf8mb4'`.
- Retained core MySQL constants: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `APP_NAME`, `PASS_SCORE`.

### 2.3 `includes/db.php`
- Enforced return type `db(): PDO`.
- Added `PDO::ATTR_EMULATE_PREPARES => false`.
- Set connection collation to `utf8mb4_unicode_ci`.
- Replaced `logAction()` with standardized `log_action(?int $user_id, ?string $username, string $action, string $details = ''): void`.
- Secure client IP resolution handling `HTTP_X_FORWARDED_FOR` (with `FILTER_VALIDATE_IP`), `REMOTE_ADDR`, CLI fallback `'127.0.0.1'`, and column truncation to 45 characters.
- Non-blocking error handling (`catch (\Throwable)`) inside `log_action()`.
- Added backward-compatible wrapper `logAction(string $action, string $details = ''): void` delegating to `log_action()`.
- Removed SQLite driver branching and removed `initSQLite()` completely.

### 2.4 `database.sqlite`
- Removed physical file `database.sqlite`.

### 2.5 `tests/verify_m1.php`
- Created standalone, zero-dependency PHP CLI verification test suite.
- Tests 1.1, 1.2, 1.3: SQLite deletion, config cleanliness, PDO singleton.
- Tests 2.1, 2.2, 2.3, 2.4: 6 tables, UNIQUE constraints, cascading FKs, VnEdu score columns.
- Tests 3.1, 3.2: 0 duplicates across all tables, 36 classes seed.
- Test 4.1: Password verification for all 3 seed accounts with `'123456'` and rejection of invalid passwords.
- Tests 5.1, 5.2: `log_action()` and `logAction()` execution and audit persistence.
- Output: 12 passed, 0 failed.
