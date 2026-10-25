# Handoff Report: Live Environment & MySQL Database Audit (explorer_survey_2)

## 1. Observation

Direct observations and evidence collected from the live system:

1. **PHP CLI Environment**:
   - Command: `& "D:\xam\php\php.exe" -v`
   - Output: `PHP 8.0.30 (cli) (built: Sep  1 2023 14:15:38) ( ZTS Visual C++ 2019 x64 )`
   - Command: `& "D:\xam\php\php.exe" -m`
   - Confirmed loaded extensions: `pdo_mysql`, `zip`, `dom`, `SimpleXML`, `xmlreader`, `xmlwriter`, `mbstring`, `curl`, `json`, `session`, `openssl`.

2. **Apache Server & Directory Mapping**:
   - Command: `Get-Item "D:\xam\htdocs\my-web-project" | Select-Object FullName, LinkType, Target`
   - Output: `LinkType: Junction`, `Target: {D:\New folder\my-web-project}`
   - Command: `curl.exe -I http://localhost/my-web-project/`
   - Output: `HTTP/1.1 302 Found`, `Server: Apache/2.4.58 (Win64) OpenSSL/3.1.3 PHP/8.0.30`, `Location: login.php`
   - Command: `curl.exe -I http://localhost/my-web-project/admin.php`
   - Output: `HTTP/1.1 403 Forbidden`

3. **MySQL / MariaDB Service**:
   - Command: `& "D:\xam\mysql\bin\mysql.exe" -u root -e "SELECT VERSION();"`
   - Output: `10.4.32-MariaDB` on port 3306 (host 127.0.0.1, user root, empty password).
   - Command: `& "D:\xam\mysql\bin\mysql.exe" -u root edu_radar -e "SHOW TABLES;"`
   - Output: 6 tables: `audit_logs`, `classes`, `scores`, `students`, `teacher_classes`, `users`.

4. **Table Constraints & Keys**:
   - Command: `& "D:\xam\mysql\bin\mysql.exe" -u root information_schema -e "SELECT TABLE_NAME, CONSTRAINT_NAME, CONSTRAINT_TYPE FROM TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = 'edu_radar';"`
   - Output:
     - `classes`: `PRIMARY` (id), `name` (UNIQUE)
     - `users`: `PRIMARY` (id), `username` (UNIQUE)
     - `teacher_classes`: `PRIMARY` (id), `unique_assignment` (UNIQUE: teacher_id, class_id, subject), `teacher_classes_ibfk_1` (FK), `teacher_classes_ibfk_2` (FK)
     - `scores`: `PRIMARY` (id), `unique_student_subject` (UNIQUE: student_id, subject), `scores_ibfk_1` (FK)
     - `students`: `PRIMARY` (id), `students_ibfk_1` (FK). **MISSING UNIQUE constraint on (class_id, full_name, birthday)**.
     - `audit_logs`: `PRIMARY` (id).

5. **Duplicate Rows Check**:
   - Command: `& "D:\xam\mysql\bin\mysql.exe" -u root edu_radar -e "SELECT count(*) FROM (SELECT name FROM classes GROUP BY name HAVING count(*) > 1) t;"` -> 0
   - Duplicate count across all tables (`classes`, `users`, `teacher_classes`, `scores`, `students`) is currently 0.
   - Row counts: `users` (3), `classes` (36), `teacher_classes` (14), `students` (5), `scores` (5), `audit_logs` (22).

6. **Users Table & Authentication**:
   - Command: `& "D:\xam\mysql\bin\mysql.exe" -u root edu_radar -e "SELECT id, username, full_name, role, password FROM users;"`
   - Users: `admin` (role: admin), `gv_toan` (role: teacher), `gv_van` (role: teacher).
   - Passwords stored as bcrypt hash `$2y$10$Huov5BMeiGHCxhgfaTkauuthlWkvzJguIrvV6vWU1SQsJDrk6PlMG`.
   - Command: `& "D:\xam\php\php.exe" -r "echo password_verify('123456', '...');"` returned `VALID`.
   - Inspection of `login.php:16`: `if ($user && (password_verify($password, $user['password']) || $password === '123456'))`. Verbatim hardcoded bypass present.

7. **Codebase Inspection & Gaps**:
   - `nhap_diem.php:46-53, 61-100`: Lacks verification that `(class_id, subject)` belongs to the logged-in teacher in `teacher_classes`.
   - `index.php:209-211`: Contains `<button class="btn copy-btn" data-text="...">📋 Copy Tin Nhắn Zalo</button>`.
   - `database.sql`: Missing `audit_logs` table definition; contains `DROP TABLE IF EXISTS attendance;`; lacks `UNIQUE(class_id, full_name, birthday)` on `students`; seeds only 8 classes instead of 36.
   - Project root: `database.sqlite` (40,960 bytes) present, with SQLite fallbacks in `includes/config.php` and `includes/db.php`.

---

## 2. Logic Chain

1. **From Observation 1, 2, 3 to Runtime Environment Stability**:
   - Because `D:\xam\php\php.exe` is version 8.0.30 and includes `pdo_mysql`, `zip`, and `SimpleXML`, the runtime satisfies all prerequisites for MySQL PDO queries, native `.xlsx` manipulation without external packages, and CLI test execution.
   - Because `D:\xam\htdocs\my-web-project` is an NTFS junction to `D:\New folder\my-web-project` and Apache listens on port 80, any modification in `D:\New folder\my-web-project` is immediately reflected at `http://localhost/my-web-project/`.

2. **From Observation 4 and 5 to Database Integrity**:
   - All existing tables currently have 0 duplicate rows.
   - However, because `students` in MySQL has only `KEY class_id (class_id)` without a UNIQUE constraint, re-running a student import or posting student data without prior checks will insert duplicate rows. Therefore, adding `UNIQUE KEY unique_student_in_class (class_id, full_name, birthday)` is necessary to enforce database-level deduplication.

3. **From Observation 6 to Authentication Security**:
   - Passwords in the database are properly hashed with bcrypt.
   - However, because line 16 of `login.php` allows `$password === '123456'`, any account can be logged into with `123456`, even if its password hash is different. Removing this condition restores strict `password_verify()` authentication.

4. **From Observation 7 to Application Authorization & Requirement Gaps**:
   - In `nhap_diem.php`, an authenticated teacher can view and save scores for unassigned classes by altering GET parameters `class_id` and `subject`, directly violating R1.
   - In `index.php`, the Zalo button directly violates R4.
   - In `database.sql`, the omission of `audit_logs` and the mismatch with live schema violates R5 and acceptance criteria.
   - In `database.sqlite` and `includes/db.php`, the SQLite driver and file violate R1.

---

## 3. Caveats

- **Network Mode**: The environment is isolated from external package registries (Composer/packagist). All Excel `.xlsx` reading and writing must rely entirely on PHP 8.0's built-in `ZipArchive` and `SimpleXML` rather than third-party packages like PhpSpreadsheet.
- **Null Birthdays in Unique Constraints**: In MySQL InnoDB, NULLs in unique indexes are treated as distinct values. Student birthdays should be validated or normalized during import to guarantee unique indexing behavior.
- **Live User Session State**: The audit did not modify existing table rows, schema indexes, or application files. All changes remain to be implemented during the implementation phase.

---

## 4. Conclusion

The live XAMPP environment is stable, responsive, and properly configured for serving `http://localhost/my-web-project/` via Apache port 80 and MariaDB 10.4 on port 3306.

To satisfy all requirements of `ORIGINAL_REQUEST.md`, the implementer must:
1. **Database Schema & SQL**:
   - Add `UNIQUE KEY unique_student_in_class (class_id, full_name, birthday)` to `students` table in live MySQL.
   - Rewrite `database.sql` to include `CREATE TABLE audit_logs`, drop `attendance` reference, add the student unique constraint, and seed all 36 classes (6A1–9C3) and standard teacher assignments.
2. **Security & Authorization**:
   - Remove the `|| $password === '123456'` bypass in `login.php`.
   - Enforce teacher class/subject authorization in `nhap_diem.php` (returning 403 / redirect if unauthorized).
   - Purge `database.sqlite` and all SQLite references in `includes/config.php` and `includes/db.php`.
3. **Features & Format Support**:
   - Implement native zero-dependency `.xlsx` import and downloadable sample generator for `admin.php` and `nhap_diem.php`.
   - Add Class Creation & Deletion and Teacher Account Creation to `admin.php`.
   - Remove Zalo button and toast from `index.php`.
   - Update VnEdu gradebook calculation formula in `nhap_diem.php`.
4. **Verification**:
   - Author and run `tests/run_tests.php` via `D:\xam\php\php.exe tests/run_tests.php`.

---

## 5. Verification Method

To independently verify all findings in this report, execute the following commands in PowerShell:

1. **Verify PHP CLI and modules**:
   ```powershell
   & "D:\xam\php\php.exe" -v
   & "D:\xam\php\php.exe" -m | Select-String -Pattern "pdo_mysql|zip|SimpleXML"
   ```
2. **Verify Apache web server response and junction**:
   ```powershell
   curl.exe -I http://localhost/my-web-project/
   Get-Item "D:\xam\htdocs\my-web-project"
   ```
3. **Verify MySQL connection and schema constraints**:
   ```powershell
   & "D:\xam\mysql\bin\mysql.exe" -u root edu_radar -e "SHOW TABLES; SELECT TABLE_NAME, CONSTRAINT_NAME, CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = 'edu_radar';"
   ```
4. **Verify zero duplicates in live tables**:
   ```powershell
   & "D:\xam\mysql\bin\mysql.exe" -u root edu_radar -e "SELECT name, count(*) FROM classes GROUP BY name HAVING count(*) > 1;"
   ```
5. **Verify password verification and login bypass location**:
   ```powershell
   & "D:\xam\php\php.exe" -r "echo password_verify('123456', '$2y$10$Huov5BMeiGHCxhgfaTkauuthlWkvzJguIrvV6vWU1SQsJDrk6PlMG') ? 'VALID' : 'INVALID';"
   Select-String -Path "D:\New folder\my-web-project\login.php" -Pattern "password === '123456'"
   ```
6. **Verify Zalo button presence in index.php**:
   ```powershell
   Select-String -Path "D:\New folder\my-web-project\index.php" -Pattern "Zalo"
   ```
