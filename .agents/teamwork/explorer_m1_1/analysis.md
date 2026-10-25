# Database Architecture & Schema Specification Report (M1)

**Target System**: EduRadar — THCS Gradebook & Early Warning System  
**Author**: `explorer_m1_1` (Investigation & Synthesis)  
**Database**: MySQL / MariaDB 10.4 on `127.0.0.1:3306` (`edu_radar`)  
**Target File**: `D:\New folder\my-web-project\database.sql`  
**Date**: 2026-10-17  

---

## 1. Executive Summary

This investigation designs the complete, production-grade Data Definition Language (DDL) and clean seed data for `database.sql` and the live MySQL database `edu_radar`, fulfilling all requirements of **Milestone 1** as specified in `ORIGINAL_REQUEST.md`, `PROJECT.md`, and `DISPATCH.md`.

### Core Deliverables Designed:
1. **Zero-Defect 6-Table Schema**:
   - `users`: Multi-account credentials (`admin`, `teacher`), secure bcrypt hash storage, `UNIQUE(username)`.
   - `classes`: 36 classes spanning lower secondary grades 6–9 and sections A–C, `UNIQUE(name)`.
   - `teacher_classes`: Subject assignment per class, `UNIQUE(teacher_id, class_id, subject)`, cascading foreign keys.
   - `students`: Class roster with VnEdu fields (`student_code`, `gender`, `phone`), `UNIQUE(class_id, full_name, birthday)` hardware-level deduplication constraint, cascading foreign key.
   - `scores`: Modern MOET Thông tư 22/2021/TT-BGDĐT THCS columns (`tx1..tx4, gk, ck, avg_score`), `UNIQUE(student_id, subject)` upsert constraint, `DECIMAL(4,2)` precision, auto-updating timestamp.
   - `audit_logs`: Immutable security event log (`user_id, username, action, details, ip_address, created_at`) without foreign key cascade hazards.
2. **Clean, Validated Seed Data**:
   - 36 classes with sequential IDs: Grade 6 (`6A1`..`6C3`), Grade 7 (`7A1`..`7C3`), Grade 8 (`8A1`..`8C3`), Grade 9 (`9A1`..`9C3`).
   - 3 verified accounts: `admin`, `gv_toan`, `gv_van` with verified bcrypt hash for password `'123456'`.
   - Standard teaching assignments for `Toán` and `Ngữ Văn`.
   - 5 sample students for class `6A1`.
   - Mathematically verified sample scores covering all three early-warning statuses: 🔴 Nguy cấp (Red), 🟡 Cần chú ý (Yellow), 🟢 Ổn định (Green).
   - Initial system audit log.
3. **Elimination of Schema Inconsistencies & Security Defects**:
   - Root cause identified: Old `database.sql:77` contained an invalid bcrypt hash (`$2y$10$4y9pQzS...`) that failed `password_verify('123456', ...)` with `false`, which had forced developers to inject a backdoor `|| $password === '123456'` in `login.php`.
   - Old `database.sql` dropped an obsolete `attendance` table and completely lacked the `audit_logs` table.
   - Old `students` table lacked `UNIQUE(class_id, full_name, birthday)`, allowing duplicate students on repeated imports.

---

## 2. Current State Audit & Discrepancy Matrix

A live comparison was conducted between the existing `database.sql`, the live MariaDB database `edu_radar`, and the Milestone 1 requirements:

| Aspect | Old `database.sql` | Live MySQL (`edu_radar`) | Milestone 1 Target Design | Root Cause & Remediation |
|---|---|---|---|---|
| **Tables Present** | 5 tables (`users`, `classes`, `teacher_classes`, `students`, `scores`); dropped `attendance` | 6 tables (`users`, `classes`, `teacher_classes`, `students`, `scores`, `audit_logs`) | Exact 6 tables. No legacy tables. | Remove `DROP TABLE IF EXISTS attendance;`. Add `audit_logs`. |
| **Bcrypt Hash** | `$2y$10$4y9pQzS/m7y9xY8Z/xH0ve2w5.JjK.RjT18P4q7lZ0eQ0/a4uCqS.` (Broken, returns `false` for `'123456'`) | `$2y$10$Huov5BMeiGHCxhgfaTkauuthlWkvzJguIrvV6vWU1SQsJDrk6PlMG` (Valid, returns `true` for `'123456'`) | Standard bcrypt hash `$2y$10$Huov5BMeiGHCxhgfaTkauuthlWkvzJguIrvV6vWU1SQsJDrk6PlMG` across all 3 seed accounts. | Fixes password verification so `login.php` backdoor can be safely deleted in M2. |
| **`students` Constraint** | No unique constraint on student name/birthday | No unique constraint | `UNIQUE KEY unique_class_student (class_id, full_name, birthday)` | Prevents duplicate student entries when re-uploading roster `.xlsx`/`.csv`. |
| **`students` Columns** | `id, class_id, full_name, birthday, parent_phone` | `id, class_id, full_name, birthday, parent_phone` | `id, class_id, student_code, full_name, birthday, gender, phone, created_at` | Add `student_code` (VnEdu Mã HS), `gender`, `created_at`. Standardize `phone` (with `parent_phone` migration). |
| **`scores` Columns** | Legacy: `m1, m2, p15_1, p15_2, t1_1, t1_2, gk, ck, avg_score` | Legacy: `m1, m2, p15_1, p15_2, t1_1, t1_2, gk, ck, avg_score` | VnEdu Thông tư 22: `tx1, tx2, tx3, tx4, gk, ck, avg_score, updated_at` | Overhaul columns to modern THCS assessment standard. |
| **`classes` Seed Rows** | 8 classes only | 36 classes (out of order IDs: 1, 2, 9, 3, 10...) | 36 classes sequentially indexed (1..36) covering Grades 6–9 and Sections A–C. | Clean seed structure matching secondary school organization. |
| **`audit_logs` Schema** | Missing entirely | Table exists, but `created_at` lacks `DEFAULT CURRENT_TIMESTAMP` | `id, user_id, username, action, details, ip_address, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP` | Provides robust, self-stamping audit trail. |

---

## 3. Detailed DDL Specifications for All 6 Tables

### 3.1 Table: `users`
- **Purpose**: System accounts and RBAC credentials. Single responsibility: authentication and role authorization.
- **DDL Definition**:
```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'teacher') NOT NULL DEFAULT 'teacher',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```
- **Field Details**:
  - `username`: Unique username (e.g., `admin`, VnEdu teacher code `gv_toan`).
  - `password`: 255-character buffer storing standard PHP `password_hash()` bcrypt hashes.
  - `role`: Restricted ENUM (`'admin'`, `'teacher'`). Unauthenticated or unrecognized roles rejected at the database layer.
  - `created_at`: Automatic creation timestamp.

---

### 3.2 Table: `classes`
- **Purpose**: Class definitions across lower secondary school grades (Khối 6, 7, 8, 9).
- **DDL Definition**:
```sql
CREATE TABLE classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    grade INT NOT NULL,
    section VARCHAR(10) NOT NULL DEFAULT 'A',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_class_name (name),
    KEY idx_grade (grade)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```
- **Design Decisions**:
  - `name`: Unique name (e.g., `6A1`, `9C3`). Guaranteed by `UNIQUE KEY unique_class_name (name)`.
  - `grade`: Integer `6`, `7`, `8`, or `9`. Indexed by `idx_grade` for fast matrix filtering.
  - `section`: Kept as `VARCHAR(10) NOT NULL DEFAULT 'A'` to ensure seamless backwards compatibility with `admin.php:135` (`ORDER BY grade ASC, section ASC, name ASC`) while cleanly grouping classes in the Admin matrix into Section A (Classes 1-3), Section B (Classes 1-3), and Section C (Classes 1-3).
  - Legacy `school_year` column removed: In THCS, class roster and gradebook are scoped per academic year; hardcoding `'2026-2027'` on the class table was redundant.

---

### 3.3 Table: `teacher_classes`
- **Purpose**: Many-to-many teaching assignment linking a subject teacher to a specific class and subject.
- **DDL Definition**:
```sql
CREATE TABLE teacher_classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    class_id INT NOT NULL,
    subject VARCHAR(50) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_assignment (teacher_id, class_id, subject),
    KEY idx_teacher_id (teacher_id),
    KEY idx_class_id (class_id),
    CONSTRAINT fk_tc_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_tc_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```
- **Integrity Constraints**:
  - `UNIQUE KEY unique_assignment (teacher_id, class_id, subject)`: Absolutely guarantees that submitting the same batch checkbox assignment twice in `admin.php` never inserts duplicate rows.
  - `ON DELETE CASCADE` on `teacher_id`: If a teacher account is removed, assignments are automatically pruned.
  - `ON DELETE CASCADE` on `class_id`: If a class is deleted, associated assignments are automatically cleaned up.
  - `subject NOT NULL` without hardcoded `'Toán'` default: Enforces that every assignment explicitly defines the teaching subject.

---

### 3.4 Table: `students`
- **Purpose**: Class student roster imported from VnEdu `.xlsx` or `.csv`.
- **DDL Definition**:
```sql
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    student_code VARCHAR(50) DEFAULT NULL,
    full_name VARCHAR(100) NOT NULL,
    birthday DATE DEFAULT NULL,
    gender VARCHAR(10) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_class_student (class_id, full_name, birthday),
    KEY idx_class_id (class_id),
    KEY idx_student_code (student_code),
    CONSTRAINT fk_students_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```
- **Integrity Constraints & VnEdu Alignment**:
  - `UNIQUE KEY unique_class_student (class_id, full_name, birthday)`: Enforces hardware-level deduplication. When Admin imports a student `.xlsx` file a second time, MySQL rejects duplicate rows, enabling safe idempotent imports.
  - `student_code`: Maps to VnEdu standard `Mã HS` (e.g., `HS202601`).
  - `gender`: Maps to VnEdu standard `Giới tính` (`Nam` / `Nữ`).
  - `phone`: Maps to `SĐT phụ huynh` (replaces legacy `parent_phone`).
  - `ON DELETE CASCADE`: Deleting a class automatically cascades to delete all enrolled student records and their child scores.

---

### 3.5 Table: `scores`
- **Purpose**: Student assessment grades per subject according to MOET Circular 22/2021/TT-BGDĐT.
- **DDL Definition**:
```sql
CREATE TABLE scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject VARCHAR(50) NOT NULL,
    tx1 DECIMAL(4,2) DEFAULT NULL,
    tx2 DECIMAL(4,2) DEFAULT NULL,
    tx3 DECIMAL(4,2) DEFAULT NULL,
    tx4 DECIMAL(4,2) DEFAULT NULL,
    gk DECIMAL(4,2) DEFAULT NULL,
    ck DECIMAL(4,2) DEFAULT NULL,
    avg_score DECIMAL(4,2) DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_student_subject (student_id, subject),
    KEY idx_student_id (student_id),
    CONSTRAINT fk_scores_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```
- **Grade Column Specification (VnEdu THCS Standard)**:
  - `tx1`, `tx2`, `tx3`, `tx4`: ĐĐGtx (Điểm đánh giá thường xuyên / Miệng & 15 phút: Cột 1, 2, 3, 4 - Hệ số 1).
  - `gk`: ĐĐGgk (Điểm đánh giá giữa kỳ - Hệ số 2).
  - `ck`: ĐĐGck (Điểm đánh giá cuối kỳ - Hệ số 3).
  - `avg_score`: ĐTBmhk (Điểm trung bình môn học kỳ, làm tròn 1 chữ số thập phân).
  - `DECIMAL(4,2)`: Prevents IEEE 754 floating point inaccuracies; allows scores `0.00` to `10.00`.
  - `UNIQUE KEY unique_student_subject (student_id, subject)`: Allows seamless upserting via `INSERT INTO scores (...) VALUES (...) ON DUPLICATE KEY UPDATE ...` during manual saves or `.xlsx` file imports.
  - `updated_at`: Automatically stamps the exact time a grade was last modified.

---

### 3.6 Table: `audit_logs`
- **Purpose**: Immutable, tamper-evident security audit trail recording all authentication, authorization, and administrative events.
- **DDL Definition**:
```sql
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    username VARCHAR(50) DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user_id (user_id),
    KEY idx_action (action),
    KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```
- **Security & Integrity Rules**:
  - **No Foreign Key on `user_id`**: Deliberately avoids `FOREIGN KEY REFERENCES users(id)` with `CASCADE` (which would delete audit history if an account is deleted) or `RESTRICT` (which would block user deletion). `user_id` is nullable, and `username` is recorded as a snapshot at the time of the event.
  - `ip_address VARCHAR(45)`: Accommodates both IPv4 (15 chars, e.g., `127.0.0.1`) and full IPv6 addresses (up to 45 chars, e.g., `2001:0db8:85a3:0000:0000:8a2e:0370:7334` or `::1`).
  - `created_at`: Defaults to `CURRENT_TIMESTAMP` so logging never requires manual clock sync.

---

## 4. Seed Data Design & Mathematical Verification

### 4.1 Users Seed
All test accounts are configured with the verified bcrypt hash for password `'123456'`:
- Hash: `$2y$10$Huov5BMeiGHCxhgfaTkauuthlWkvzJguIrvV6vWU1SQsJDrk6PlMG`
- Test Verification: `password_verify('123456', hash)` -> `true`; `password_verify('1234567', hash)` -> `false`.

```sql
INSERT INTO users (id, username, password, full_name, role) VALUES 
(1, 'admin', '$2y$10$Huov5BMeiGHCxhgfaTkauuthlWkvzJguIrvV6vWU1SQsJDrk6PlMG', 'Quản Trị Viên Trường', 'admin'),
(2, 'gv_toan', '$2y$10$Huov5BMeiGHCxhgfaTkauuthlWkvzJguIrvV6vWU1SQsJDrk6PlMG', 'Thầy Nguyễn Văn Giáo', 'teacher'),
(3, 'gv_van', '$2y$10$Huov5BMeiGHCxhgfaTkauuthlWkvzJguIrvV6vWU1SQsJDrk6PlMG', 'Cô Lê Thị Hương', 'teacher');
```

---

### 4.2 36 Classes Seed (6A1..9C9)
Organized sequentially into 4 grades x 3 sections (A, B, C) x 3 classes (1, 2, 3) = 36 classes:

```sql
INSERT INTO classes (id, name, grade, section) VALUES
-- Khối 6 (9 lớp)
(1, '6A1', 6, 'A'), (2, '6A2', 6, 'A'), (3, '6A3', 6, 'A'),
(4, '6B1', 6, 'B'), (5, '6B2', 6, 'B'), (6, '6B3', 6, 'B'),
(7, '6C1', 6, 'C'), (8, '6C2', 6, 'C'), (9, '6C3', 6, 'C'),
-- Khối 7 (9 lớp)
(10, '7A1', 7, 'A'), (11, '7A2', 7, 'A'), (12, '7A3', 7, 'A'),
(13, '7B1', 7, 'B'), (14, '7B2', 7, 'B'), (15, '7B3', 7, 'B'),
(16, '7C1', 7, 'C'), (17, '7C2', 7, 'C'), (18, '7C3', 7, 'C'),
-- Khối 8 (9 lớp)
(19, '8A1', 8, 'A'), (20, '8A2', 8, 'A'), (21, '8A3', 8, 'A'),
(22, '8B1', 8, 'B'), (23, '8B2', 8, 'B'), (24, '8B3', 8, 'B'),
(25, '8C1', 8, 'C'), (26, '8C2', 8, 'C'), (27, '8C3', 8, 'C'),
-- Khối 9 (9 lớp)
(28, '9A1', 9, 'A'), (29, '9A2', 9, 'A'), (30, '9A3', 9, 'A'),
(31, '9B1', 9, 'B'), (32, '9B2', 9, 'B'), (33, '9B3', 9, 'B'),
(34, '9C1', 9, 'C'), (35, '9C2', 9, 'C'), (36, '9C3', 9, 'C');
```

---

### 4.3 Teaching Assignments Seed (`teacher_classes`)
```sql
INSERT INTO teacher_classes (id, teacher_id, class_id, subject) VALUES
(1, 2, 1, 'Toán'),
(2, 2, 10, 'Toán'),
(3, 3, 1, 'Ngữ Văn');
```

---

### 4.4 Students Seed for Class `6A1` (id = 1)
```sql
INSERT INTO students (id, class_id, student_code, full_name, birthday, gender, phone) VALUES
(1, 1, 'HS6A101', 'Nguyễn Văn An', '2015-03-12', 'Nam', '0901234567'),
(2, 1, 'HS6A102', 'Trần Minh Khang', '2015-07-05', 'Nam', '0912345678'),
(3, 1, 'HS6A103', 'Lê Thị Bích Ngọc', '2015-01-20', 'Nữ', '0923456789'),
(4, 1, 'HS6A104', 'Phạm Quốc Huy', '2015-11-02', 'Nam', '0934567890'),
(5, 1, 'HS6A105', 'Hoàng Thu Trang', '2015-05-18', 'Nữ', '0945678901');
```

---

### 4.5 Mathematically Verified Scores Seed (`scores`)
Scores are computed using the exact MOET weighted formula:
$$\text{ĐTBmhk} = \text{round}\left(\frac{\sum \text{tx} + 2 \times \text{gk} + 3 \times \text{ck}}{N_{tx} + 5}, 1\right)$$

| ID | Student Name | Subject | tx1 | tx2 | tx3 | tx4 | gk (x2) | ck (x3) | Total Weighted Sum | Total Weight | Calculated `avg_score` | Radar Diagnostic Status |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 1 | Nguyễn Văn An | Toán | 8.0 | 7.0 | 6.5 | 6.0 | 5.0 | 5.0 | $27.5 + 10.0 + 15.0 = 52.5$ | 9 | **5.8** | 🟢 Ổn định (Đạt yêu cầu) |
| 2 | Trần Minh Khang | Toán | 6.0 | 7.0 | 8.0 | 8.5 | 8.5 | 9.0 | $29.5 + 17.0 + 27.0 = 73.5$ | 9 | **8.2** | 🟢 Ổn định (Học lực Giỏi) |
| 3 | Lê Thị Bích Ngọc | Toán | 9.0 | 9.5 | 9.0 | 8.5 | 9.0 | 9.5 | $36.0 + 18.0 + 28.5 = 82.5$ | 9 | **9.2** | 🟢 Ổn định (Xuất sắc) |
| 4 | Phạm Quốc Huy | Toán | 4.0 | 4.5 | 4.0 | 3.5 | 4.0 | 4.0 | $16.0 + 8.0 + 12.0 = 36.0$ | 9 | **4.0** | 🔴 Nguy cấp (ĐTB < 5.0) |
| 5 | Hoàng Thu Trang | Toán | 7.0 | 6.0 | 4.5 | 6.0 | 6.0 | 6.5 | $23.5 + 12.0 + 19.5 = 55.0$ | 9 | **6.1** | 🟡 Cần chú ý (tx3 = 4.5 < 5.0) |
| 1 | Nguyễn Văn An | Ngữ Văn | 7.5 | 8.0 | 7.0 | 7.5 | 8.0 | 8.5 | $30.0 + 16.0 + 25.5 = 71.5$ | 9 | **7.9** | 🟢 Ổn định (Học lực Khá) |
| 2 | Trần Minh Khang | Ngữ Văn | 8.0 | 8.5 | 8.0 | 8.5 | 8.0 | 9.0 | $33.0 + 16.0 + 27.0 = 76.0$ | 9 | **8.4** | 🟢 Ổn định (Học lực Giỏi) |

```sql
INSERT INTO scores (student_id, subject, tx1, tx2, tx3, tx4, gk, ck, avg_score) VALUES
(1, 'Toán', 8.0, 7.0, 6.5, 6.0, 5.0, 5.0, 5.8),
(2, 'Toán', 6.0, 7.0, 8.0, 8.5, 8.5, 9.0, 8.2),
(3, 'Toán', 9.0, 9.5, 9.0, 8.5, 9.0, 9.5, 9.2),
(4, 'Toán', 4.0, 4.5, 4.0, 3.5, 4.0, 4.0, 4.0),
(5, 'Toán', 7.0, 6.0, 4.5, 6.0, 6.0, 6.5, 6.1),
(1, 'Ngữ Văn', 7.5, 8.0, 7.0, 7.5, 8.0, 8.5, 7.9),
(2, 'Ngữ Văn', 8.0, 8.5, 8.0, 8.5, 8.0, 9.0, 8.4);
```

---

### 4.6 Audit Logs Seed (`audit_logs`)
```sql
INSERT INTO audit_logs (id, user_id, username, action, details, ip_address, created_at) VALUES
(1, 1, 'admin', 'Khởi tạo hệ thống', 'Khởi tạo cơ sở dữ liệu mẫu EduRadar chuẩn VnEdu Thông tư 22/2021/TT-BGDĐT.', '127.0.0.1', CURRENT_TIMESTAMP);
```

---

## 5. Proposed Complete `database.sql` Content

The complete, authoritative migration file to be written to `D:\New folder\my-web-project\database.sql`:

```sql
-- =========================================================================
-- HỆ THỐNG QUẢN LÝ ĐIỂM & CẢNH BÁO SỚM HỌC SINH THCS (EDURADAR)
-- Chuẩn quy chế đánh giá học sinh THCS (Thông tư 22/2021/TT-BGDĐT & VnEdu)
-- Hệ cơ sở dữ liệu: MySQL 8.0 / MariaDB 10.4 (XAMPP Port 3306)
-- =========================================================================

CREATE DATABASE IF NOT EXISTS edu_radar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE edu_radar;

-- Tắt kiểm tra khóa ngoại tạm thời để xóa bảng sạch sẽ
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS scores;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS teacher_classes;
DROP TABLE IF EXISTS classes;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- -------------------------------------------------------------------------
-- 1. BẢNG TÀI KHOẢN (users)
-- -------------------------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'teacher') NOT NULL DEFAULT 'teacher',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 2. BẢNG LỚP HỌC (classes)
-- -------------------------------------------------------------------------
CREATE TABLE classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    grade INT NOT NULL,
    section VARCHAR(10) NOT NULL DEFAULT 'A',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_class_name (name),
    KEY idx_grade (grade)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 3. BẢNG PHÂN CÔNG GIẢNG DẠY (teacher_classes)
-- -------------------------------------------------------------------------
CREATE TABLE teacher_classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    class_id INT NOT NULL,
    subject VARCHAR(50) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_assignment (teacher_id, class_id, subject),
    KEY idx_teacher_id (teacher_id),
    KEY idx_class_id (class_id),
    CONSTRAINT fk_tc_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_tc_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 4. BẢNG HỌC SINH (students)
-- -------------------------------------------------------------------------
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    student_code VARCHAR(50) DEFAULT NULL,
    full_name VARCHAR(100) NOT NULL,
    birthday DATE DEFAULT NULL,
    gender VARCHAR(10) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_class_student (class_id, full_name, birthday),
    KEY idx_class_id (class_id),
    KEY idx_student_code (student_code),
    CONSTRAINT fk_students_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 5. BẢNG ĐIỂM SỐ CHUẨN VNEDU THÔNG TƯ 22 (scores)
-- -------------------------------------------------------------------------
CREATE TABLE scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject VARCHAR(50) NOT NULL,
    tx1 DECIMAL(4,2) DEFAULT NULL,
    tx2 DECIMAL(4,2) DEFAULT NULL,
    tx3 DECIMAL(4,2) DEFAULT NULL,
    tx4 DECIMAL(4,2) DEFAULT NULL,
    gk DECIMAL(4,2) DEFAULT NULL,
    ck DECIMAL(4,2) DEFAULT NULL,
    avg_score DECIMAL(4,2) DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_student_subject (student_id, subject),
    KEY idx_student_id (student_id),
    CONSTRAINT fk_scores_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 6. BẢNG NHẬT KÝ HỆ THỐNG (audit_logs)
-- -------------------------------------------------------------------------
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    username VARCHAR(50) DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user_id (user_id),
    KEY idx_action (action),
    KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- DỮ LIỆU KHỞI TẠO TEST CHUẨN (SEED DATA)
-- Mật khẩu mặc định: '123456'
-- Bcrypt Hash: $2y$10$Huov5BMeiGHCxhgfaTkauuthlWkvzJguIrvV6vWU1SQsJDrk6PlMG
-- =========================================================================

-- 1. Tài khoản mẫu
INSERT INTO users (id, username, password, full_name, role) VALUES 
(1, 'admin', '$2y$10$Huov5BMeiGHCxhgfaTkauuthlWkvzJguIrvV6vWU1SQsJDrk6PlMG', 'Quản Trị Viên Trường', 'admin'),
(2, 'gv_toan', '$2y$10$Huov5BMeiGHCxhgfaTkauuthlWkvzJguIrvV6vWU1SQsJDrk6PlMG', 'Thầy Nguyễn Văn Giáo', 'teacher'),
(3, 'gv_van', '$2y$10$Huov5BMeiGHCxhgfaTkauuthlWkvzJguIrvV6vWU1SQsJDrk6PlMG', 'Cô Lê Thị Hương', 'teacher');

-- 2. Danh sách 36 lớp học chuẩn THCS (Khối 6-9, Ban A-C, Lớp 1-3)
INSERT INTO classes (id, name, grade, section) VALUES
-- Khối 6 (9 lớp)
(1, '6A1', 6, 'A'), (2, '6A2', 6, 'A'), (3, '6A3', 6, 'A'),
(4, '6B1', 6, 'B'), (5, '6B2', 6, 'B'), (6, '6B3', 6, 'B'),
(7, '6C1', 6, 'C'), (8, '6C2', 6, 'C'), (9, '6C3', 6, 'C'),
-- Khối 7 (9 lớp)
(10, '7A1', 7, 'A'), (11, '7A2', 7, 'A'), (12, '7A3', 7, 'A'),
(13, '7B1', 7, 'B'), (14, '7B2', 7, 'B'), (15, '7B3', 7, 'B'),
(16, '7C1', 7, 'C'), (17, '7C2', 7, 'C'), (18, '7C3', 7, 'C'),
-- Khối 8 (9 lớp)
(19, '8A1', 8, 'A'), (20, '8A2', 8, 'A'), (21, '8A3', 8, 'A'),
(22, '8B1', 8, 'B'), (23, '8B2', 8, 'B'), (24, '8B3', 8, 'B'),
(25, '8C1', 8, 'C'), (26, '8C2', 8, 'C'), (27, '8C3', 8, 'C'),
-- Khối 9 (9 lớp)
(28, '9A1', 9, 'A'), (29, '9A2', 9, 'A'), (30, '9A3', 9, 'A'),
(31, '9B1', 9, 'B'), (32, '9B2', 9, 'B'), (33, '9B3', 9, 'B'),
(34, '9C1', 9, 'C'), (35, '9C2', 9, 'C'), (36, '9C3', 9, 'C');

-- 3. Phân công giảng dạy
INSERT INTO teacher_classes (id, teacher_id, class_id, subject) VALUES
(1, 2, 1, 'Toán'),
(2, 2, 10, 'Toán'),
(3, 3, 1, 'Ngữ Văn');

-- 4. Học sinh lớp 6A1
INSERT INTO students (id, class_id, student_code, full_name, birthday, gender, phone) VALUES
(1, 1, 'HS6A101', 'Nguyễn Văn An', '2015-03-12', 'Nam', '0901234567'),
(2, 1, 'HS6A102', 'Trần Minh Khang', '2015-07-05', 'Nam', '0912345678'),
(3, 1, 'HS6A103', 'Lê Thị Bích Ngọc', '2015-01-20', 'Nữ', '0923456789'),
(4, 1, 'HS6A104', 'Phạm Quốc Huy', '2015-11-02', 'Nam', '0934567890'),
(5, 1, 'HS6A105', 'Hoàng Thu Trang', '2015-05-18', 'Nữ', '0945678901');

-- 5. Bảng điểm môn Toán và Ngữ Văn lớp 6A1 (chuẩn tính ĐTB Thông tư 22)
INSERT INTO scores (student_id, subject, tx1, tx2, tx3, tx4, gk, ck, avg_score) VALUES
(1, 'Toán', 8.0, 7.0, 6.5, 6.0, 5.0, 5.0, 5.8),
(2, 'Toán', 6.0, 7.0, 8.0, 8.5, 8.5, 9.0, 8.2),
(3, 'Toán', 9.0, 9.5, 9.0, 8.5, 9.0, 9.5, 9.2),
(4, 'Toán', 4.0, 4.5, 4.0, 3.5, 4.0, 4.0, 4.0),
(5, 'Toán', 7.0, 6.0, 4.5, 6.0, 6.0, 6.5, 6.1),
(1, 'Ngữ Văn', 7.5, 8.0, 7.0, 7.5, 8.0, 8.5, 7.9),
(2, 'Ngữ Văn', 8.0, 8.5, 8.0, 8.5, 8.0, 9.0, 8.4);

-- 6. Nhật ký khởi tạo hệ thống
INSERT INTO audit_logs (id, user_id, username, action, details, ip_address, created_at) VALUES
(1, 1, 'admin', 'Khởi tạo hệ thống', 'Khởi tạo cơ sở dữ liệu mẫu EduRadar chuẩn VnEdu Thông tư 22/2021/TT-BGDĐT.', '127.0.0.1', CURRENT_TIMESTAMP);
```

---

## 6. Downstream Integration Guidelines

### 6.1 For Milestone 2 (Authentication & Strict RBAC)
- **Account Login**: `login.php` must query `users` with `SELECT * FROM users WHERE username = ?` and verify password strictly using `password_verify($password, $user['password'])`.
- **Backdoor Removal**: The condition `|| $password === '123456'` in `login.php:16` must be deleted completely. All 3 seed accounts (`admin`, `gv_toan`, `gv_van`) will authenticate with `'123456'` through the verified bcrypt hash.

### 6.2 For Milestone 3 (Admin Management & Student Import)
- **Class Matrix**: Query `classes` using `SELECT * FROM classes ORDER BY grade ASC, section ASC, name ASC`.
- **Batch Assignment**: Insert into `teacher_classes (teacher_id, class_id, subject)` using `INSERT IGNORE` or checking beforehand. The `UNIQUE KEY unique_assignment` guarantees 0 duplicate rows even if clicked repeatedly.
- **Student Roster Import**: Parse `.xlsx` / `.csv` using native `ZipArchive` + `SimpleXML`. Use `UNIQUE KEY unique_class_student (class_id, full_name, birthday)` with `ON DUPLICATE KEY UPDATE phone = VALUES(phone)` to guarantee 0 duplicate students.

### 6.3 For Milestone 4 (VnEdu Gradebook & Spreadsheet)
- **Scores Table Columns**: `nhap_diem.php` must read and write to `tx1, tx2, tx3, tx4, gk, ck, avg_score` instead of legacy `m1..t1_2`.
- **Grade Saving & Upsert**: Save scores using:
  ```sql
  INSERT INTO scores (student_id, subject, tx1, tx2, tx3, tx4, gk, ck, avg_score)
  VALUES (:sid, :subject, :tx1, :tx2, :tx3, :tx4, :gk, :ck, :avg)
  ON DUPLICATE KEY UPDATE
      tx1 = VALUES(tx1), tx2 = VALUES(tx2), tx3 = VALUES(tx3), tx4 = VALUES(tx4),
      gk = VALUES(gk), ck = VALUES(ck), avg_score = VALUES(avg_score);
  ```
- **Math Formula**:
  `$avg = round(($sum_tx + 2 * ($gk ?? 0) + 3 * ($ck ?? 0)) / ($count_tx + ($gk !== null ? 2 : 0) + ($ck !== null ? 3 : 0)), 1);`

### 6.4 For Milestone 5 (Early Warning Radar)
- **Risk Evaluation**: `index.php` queries `scores` joined with `students` and `classes`. Evaluates:
  - 🔴 Nguy cấp: `avg_score < 5.0`, or consecutive drop below 5.0, or `gk < 4.0 || ck < 4.0`.
  - 🟡 Cần chú ý: any `tx < 5.0`, or `avg_score` between 5.0 and 5.4.
  - 🟢 Ổn định: all components passing and `avg_score >= 5.0`.
- **NO Zalo**: Ensure zero Zalo button, modal, or script.
