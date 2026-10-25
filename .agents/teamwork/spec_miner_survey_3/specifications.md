# TECHNICAL SPECIFICATIONS REPORT — VNEDU GRADEBOOK & EARLY WARNING RADAR

**Author**: `spec_miner_survey_3` (teamwork_preview_spec_miner)  
**Target Environment**: Windows, XAMPP Apache (`http://localhost/my-web-project/`), MariaDB/MySQL (`edu_radar`), PHP 8.2+ (`D:\xam\php\php.exe`)  
**Specification Source**: `ORIGINAL_REQUEST.md`, Vietnamese Ministry of Education & Training (MOET) Thông tư 22/2021/TT-BGDĐT & VnEdu THCS standard, existing codebase, and MySQL schema.

---

## 1. Specification: VnEdu Student Roster Import Format (.xlsx and .csv)

### 1.1 Supported File Types & MIME Specifications
- **Excel Spreadsheet (`.xlsx`)**: Office Open XML workbook (binary ZIP archive containing `[Content_Types].xml`, `xl/workbook.xml`, `xl/sharedStrings.xml`, and `xl/worksheets/sheet1.xml`). MIME: `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`.
- **Delimited Text (`.csv`)**: Comma-separated or semicolon-separated values, UTF-8 encoded (with or without UTF-8 BOM). MIME: `text/csv`, `text/plain`.

### 1.2 Header Signatures & Column Ordering
The importer supports two standard header formats:

#### Format A: Official VnEdu Student Export Header
| Col Index | Header Title (VN) | Required | Type | Example Value | Description |
|---|---|---|---|---|---|
| 0 | `STT` | No | Integer | `1` | Số thứ tự trong danh sách lớp |
| 1 | `Mã HS` / `Mã học sinh` | No | String | `HS202601` | Mã định danh học sinh trên hệ thống VnEdu |
| 2 | `Họ và tên` / `Họ tên` | **Yes** | String (UTF-8) | `Nguyễn Văn An` | Họ và tên đầy đủ của học sinh |
| 3 | `Ngày sinh` | **Yes** | Date / String | `2015-03-12` | Ngày tháng năm sinh của học sinh |
| 4 | `Giới tính` | No | String | `Nam` / `Nữ` | Giới tính học sinh |
| 5 | `SĐT phụ huynh` / `Số điện thoại phụ huynh` | No | String | `0901234567` | Số điện thoại liên lạc của phụ huynh |

#### Format B: Simple Sample CSV Header (Current Project Sample)
```csv
"Họ và tên","Ngày sinh","Số điện thoại phụ huynh"
"Nguyễn Tuấn Kiệt","2015-04-12","0912345601"
```

### 1.3 Data Normalization & Cleaning Rules
1. **Full Name (`full_name`)**:
   - Strip leading/trailing whitespace.
   - Collapse multiple consecutive internal spaces into a single space (`preg_replace('/\s+/', ' ', trim($name))`).
   - Retain full Vietnamese Unicode characters (UTF-8 mb4).
   - Validation: Must not be empty; max length 100 characters.
2. **Date of Birth (`birthday`)**:
   - Formats handled:
     - ISO format: `YYYY-MM-DD` (e.g., `2015-03-12`).
     - Vietnamese standard format: `DD/MM/YYYY` (e.g., `12/03/2015`) or `DD-MM-YYYY`. Parse and convert to `YYYY-MM-DD`.
     - Excel Serial Date Number: (e.g., `42075`): Converted via `gmdate('Y-m-d', ($serial - 25569) * 86400)`.
   - Fallback: If missing or unparseable, set to `NULL`.
3. **Parent Phone (`parent_phone`)**:
   - Strip non-digit characters except optional leading `+`: `preg_replace('/[^0-9+]/', '', $phone)`.
   - Vietnamese domestic numbers: Typically 10 digits starting with `0` (e.g., `0901234567`).

### 1.4 Deduplication & Upsert Specification
- **Database Constraint**: `UNIQUE KEY unique_class_student (class_id, full_name, birthday)` on `students` table.
- **Deduplication Logic**:
  Before inserting a row into `students`, query:
  ```sql
  SELECT id FROM students 
  WHERE class_id = :class_id 
    AND full_name = :full_name 
    AND (birthday = :birthday OR (birthday IS NULL AND :birthday IS NULL));
  ```
  - **Match Found**: Update `parent_phone` if the uploaded file contains phone data and existing is empty. **Do NOT insert duplicate row**.
  - **No Match**: Insert new row:
    ```sql
    INSERT INTO students (class_id, full_name, birthday, parent_phone) 
    VALUES (:class_id, :full_name, :birthday, :parent_phone);
    ```
- **Zero Duplicate Guarantee**: Importing the exact same student roster `.xlsx` or `.csv` file multiple times results in `0` duplicate rows created and preserves existing IDs.

---

## 2. Specification: VnEdu Gradebook Import/Export Format (.xlsx and .csv) & Math Engine

### 2.1 Gradebook Structure (Modeled after MOET Circular 22/2021/TT-BGDĐT & VnEdu THCS)
The spreadsheet-like gradebook contains 10 primary columns:
1. `STT`: Sequence index.
2. `Họ và tên`: Student full name.
3. `Ngày sinh`: Date of birth.
4. `ĐĐGtx 1` (Đánh giá thường xuyên 1 / Miệng & 15 phút: Cột 1, Hệ số 1).
5. `ĐĐGtx 2` (Đánh giá thường xuyên 2 / Miệng & 15 phút: Cột 2, Hệ số 1).
6. `ĐĐGtx 3` (Đánh giá thường xuyên 3 / Miệng & 15 phút: Cột 3, Hệ số 1).
7. `ĐĐGtx 4` (Đánh giá thường xuyên 4 / Miệng & 15 phút: Cột 4, Hệ số 1).
8. `ĐĐGgk` (Đánh giá giữa kỳ, Hệ số 2).
9. `ĐĐGck` (Đánh giá cuối kỳ, Hệ số 3).
10. `ĐTBmhk` (Điểm trung bình môn học kỳ, tự động tính).

### 2.2 Database Schema Alignment (`scores` table)
```sql
CREATE TABLE scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject VARCHAR(50) NOT NULL DEFAULT 'Toán',
    tx1 DECIMAL(4,2) DEFAULT NULL,   -- ĐĐGtx 1 (HS 1)
    tx2 DECIMAL(4,2) DEFAULT NULL,   -- ĐĐGtx 2 (HS 1)
    tx3 DECIMAL(4,2) DEFAULT NULL,   -- ĐĐGtx 3 (HS 1)
    tx4 DECIMAL(4,2) DEFAULT NULL,   -- ĐĐGtx 4 (HS 1)
    gk DECIMAL(4,2) DEFAULT NULL,    -- ĐĐGgk (HS 2)
    ck DECIMAL(4,2) DEFAULT NULL,    -- ĐĐGck (HS 3)
    avg_score DECIMAL(4,2) DEFAULT NULL, -- ĐTBmhk
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_subject (student_id, subject)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```
*(Note: To maintain backwards-compatibility with existing code, `m1`, `m2`, `p15_1`, `p15_2` map directly to `tx1`, `tx2`, `tx3`, `tx4`).*

### 2.3 Calculation Formula & Rounding Specifications
- **Coefficients / Weights**:
  - Regular assessment columns ($tx_1, tx_2, tx_3, tx_4$): Weight = $1$ per entered score.
  - Periodic Mid-Term ($gk$): Weight = $2$.
  - Periodic End-of-Term ($ck$): Weight = $3$.
- **Standard Formula (when GK & CK are entered)**:
  $$\text{ĐTBmhk} = \frac{\sum \text{ĐĐGtx} + \text{ĐĐGgk} \times 2 + \text{ĐĐGck} \times 3}{N_{tx} + 5}$$
  *(where $N_{tx}$ is the number of non-null regular assessment columns entered, and $5 = 2 + 3$)*.
- **General In-Progress Formula (for partial score entries across the semester)**:
  $$\text{ĐTBmhk} = \frac{\sum_{i \in \text{valid } tx} tx_i \times 1 + (gk \neq \text{null} ? gk \times 2 : 0) + (ck \neq \text{null} ? ck \times 3 : 0)}{N_{tx} + (gk \neq \text{null} ? 2 : 0) + (ck \neq \text{null} ? 3 : 0)}$$
  If total weight is $0$, $\text{ĐTBmhk} = \text{null}$.
- **Rounding Rule**:
  - Rounded to **1 decimal place** using standard arithmetic half-up rounding (`round($value, 1)` in PHP).
  - Examples:
    - Values $7.8333... \to 7.8$
    - Values $5.65 \to 5.7$
    - Values $4.94 \to 4.9$
    - Values $4.95 \to 5.0$

### 2.4 Validation & Cell Navigation
- Cell Score Range: `0.0` to `10.0` (inclusive). Valid step: `0.1` or `0.25` or `0.5`. Empty string indicates no score.
- Rejection: Values $< 0$ or $> 10.0$ or non-numeric strings must be rejected with an alert.
- Keyboard Navigation: Tab and Arrow keys (`ArrowUp`, `ArrowDown`, `ArrowLeft`, `ArrowRight`) seamlessly shift focus between grid cells.

### 2.5 File Import & Export at Bottom of `nhap_diem.php`
- **File Upload (`.xlsx` and `.csv`)**:
  - Accepts standard VnEdu grade sheet files.
  - Matches student by `full_name` within the selected class. If a student is not yet in the class, creates the student record.
  - **Upsert on Conflict**: Uses `UNIQUE(student_id, subject)` via MySQL `ON DUPLICATE KEY UPDATE` to overwrite/update scores without creating duplicate rows.
- **Export & Sample Downloads**:
  - `sample_vnedu_gradebook.xlsx`: Downloadable pre-formatted template with standard headers and dummy rows.
  - "Xuất File Excel (.xlsx)": Exports the active class gradebook with all entered scores and calculated `ĐTBmhk`.

---

## 3. Specification: Early-Warning Diagnostic Engine (`index.php`)

### 3.1 Strict Prohibition: Zero Zalo
- **Prohibited Elements**:
  - NO "Copy Tin Nhắn Zalo" button.
  - NO Zalo icon, badge, or clipboard script.
  - NO parent message text generator modal or template.
  - NO toast notification for Zalo copying.
- **Replacement UI**:
  - Replace the action column with **"Đề xuất sư phạm & Chi tiết cảnh báo"** (actionable pedagogical recommendations: e.g. "Kèm thêm chuyên đề hình học", "Cần kiểm tra lại kiến thức cơ bản", "Duy trì bồi dưỡng học sinh giỏi").

### 3.2 Diagnostic Rules, Thresholds & Vietnamese Explanations

Each student is evaluated against three status levels in prioritized order:

#### Level 1: 🔴 Nguy cấp (Red / High Risk)
Triggered if ANY of the following conditions are met:
1. **Rule R1 (Failing Average)**:
   - Condition: `avg_score !== null && avg_score < 5.0`
   - Vietnamese Diagnostic Message: `Điểm trung bình học kỳ dưới 5.0 ({avg_score}) - Nguy cơ học lực Chưa đạt / thi lại`
2. **Rule R2 (Recent Severe Downward Drop)**:
   - Condition: Chronologically across entered scores (tx1..tx4, gk, ck), student has at least 2 scores, the most recent score is lower than the previous score, AND the most recent score is below pass mark ($< 5.0$):
     `count(scores) >= 2 && latest < previous && latest < 5.0`
   - Vietnamese Diagnostic Message: `Bài kiểm tra gần nhất sa sút dưới trung bình ({latest} < {previous})`
3. **Rule R3 (Critical Periodic Exam Failure)**:
   - Condition: Final exam or mid-term exam is critically low ($< 4.0$):
     `(ck !== null && ck < 4.0) || (gk !== null && gk < 4.0)`
   - Vietnamese Diagnostic Message: `Điểm kiểm tra định kỳ (Giữa kỳ/Cuối kỳ) rất thấp (< 4.0)`

#### Level 2: 🟡 Cần chú ý (Yellow / Warning Risk)
Triggered if student is NOT Red, but meets ANY of the following:
1. **Rule Y1 (Component Score Below Pass Mark)**:
   - Condition: Any component score is below 5.0:
     `count(scores) > 0 && min(scores) < 5.0`
   - Vietnamese Diagnostic Message: `Có bài kiểm tra thành phần dưới trung bình (thấp nhất: {min_score})`
2. **Rule Y2 (Borderline Average)**:
   - Condition: Average is barely passing:
     `avg_score !== null && avg_score >= 5.0 && avg_score < 5.5`
   - Vietnamese Diagnostic Message: `Điểm trung bình mấp mé ngưỡng nguy hiểm (5.0 - 5.4)`
3. **Rule Y3 (Sharp Drop Between Consecutive Tests)**:
   - Condition: A steep drop of $2.0$ points or more between consecutive tests even if still above 5.0:
     `count(scores) >= 2 && (previous - latest) >= 2.0`
   - Vietnamese Diagnostic Message: `Điểm số có dấu hiệu giảm mạnh so với bài trước (giảm {diff} điểm)`

#### Level 3: 🟢 Ổn định (Green / Safe & Stable)
Triggered if student does NOT trigger any Red or Yellow conditions:
1. **Rule G1 (Excellent)**:
   - Condition: `avg_score !== null && avg_score >= 8.0`
   - Vietnamese Diagnostic Message: `Học lực giỏi, kết quả học tập xuất sắc và ổn định`
2. **Rule G2 (Good)**:
   - Condition: `avg_score !== null && avg_score >= 6.5`
   - Vietnamese Diagnostic Message: `Học lực khá, nắm vững kiến thức và tiến bộ đều`
3. **Rule G3 (Satisfactory)**:
   - Condition: `avg_score !== null && avg_score >= 5.0`
   - Vietnamese Diagnostic Message: `Học lực trung bình khá, duy trì kết quả đạt yêu cầu`
4. **Rule G4 (No Scores Yet)**:
   - Condition: `avg_score === null`
   - Vietnamese Diagnostic Message: `Chưa có điểm kiểm tra đánh giá`

### 3.3 Access Scope
- **Teacher (`role === 'teacher'`)**:
  Can only see early warnings for classes and subjects assigned to them in `teacher_classes`.
- **Admin (`role === 'admin'`)**:
  Can see early warnings across the entire school, with filter controls for Grade (6, 7, 8, 9), Class, and Subject.

---

## 4. Specification: Audit Logging Architecture, Schema & Event Triggers

### 4.1 Database Table Schema (`audit_logs`)
```sql
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    username VARCHAR(50) DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4.2 Comprehensive Event Trigger Matrix Across All Flows

| Flow / Endpoint | Action Name (`action`) | State / Condition | `user_id` & `username` | Exact Details String Format (`details`) |
|---|---|---|---|---|
| `login.php` | `Đăng nhập thành công` | Password verified successfully | Auth user ID & username | `Người dùng [{username}] vai trò [{role}] đã đăng nhập vào hệ thống.` |
| `login.php` | `Đăng nhập thất bại` | Wrong password or invalid user | `NULL`, submitted username | `Thử đăng nhập sai mật khẩu với tài khoản [{username}].` |
| `logout.php` | `Đăng xuất` | Session destroyed | Auth user ID & username | `Tài khoản [{username}] đã đăng xuất khỏi hệ thống.` |
| `admin.php` | `Truy cập trái phép bị chặn` | Teacher or guest hits `admin.php` | Session ID & username (or `NULL`, `'Khách'`) | `Cố tình truy cập trang admin.php khi không có quyền.` |
| `admin.php` | `Tạo lớp học` | Admin adds a new class | Admin ID & username | `Tạo mới lớp [{class_name}] thuộc khối [{grade}].` |
| `admin.php` | `Xóa lớp học` | Admin deletes an existing class | Admin ID & username | `Xóa lớp [{class_name}] khỏi hệ thống.` |
| `admin.php` | `Tạo tài khoản giáo viên`| Admin creates teacher user | Admin ID & username | `Tạo tài khoản giáo viên mới [{new_username}] cho [{full_name}].` |
| `admin.php` | `Phân công giáo viên` | Admin submits batch checkbox assignments | Admin ID & username | `Admin phân công cho [{teacher_name}] dạy môn [{subject}] ở các lớp: [{class_list}].` |
| `admin.php` | `Hủy phân công` | Admin removes assignment row | Admin ID & username | `Admin đã hủy quyền dạy môn [{subject}] lớp [{class_name}] của giáo viên [{teacher_name}].` |
| `admin.php` | `Import học sinh` | Admin uploads student `.xlsx`/`.csv` | Admin ID & username | `Admin nạp thành công {count} học sinh vào lớp [{class_name}] từ file [{file_name}].` |
| `admin.php` | `Admin giám sát sổ điểm` | Admin clicks "Mở Bảng Điểm" row button | Admin ID & username | `Admin truy cập mở bảng điểm môn [{subject}] lớp [{class_name}] của giáo viên [{teacher_name}].` |
| `nhap_diem.php`| `Truy cập trái phép bị chặn`| Teacher tries unassigned class/subject | Teacher ID & username | `Giáo viên [{username}] cố tình truy cập lớp [{class_name}] môn [{subject}] không được phân công.` |
| `nhap_diem.php`| `Lưu sổ điểm` | Teacher/Admin submits score form | User ID & username | `Cập nhật bảng điểm môn [{subject}] cho lớp [{class_name}] ({count} học sinh).` |
| `nhap_diem.php`| `Import file điểm` | Teacher/Admin uploads grade `.xlsx`/`.csv` | User ID & username | `Nạp dữ liệu điểm từ file [{file_name}] cho {count} học sinh lớp [{class_name}] môn [{subject}].` |

### 4.3 Logging Invariant Rules
- Non-blocking execution: Audit logging failures must be wrapped in `try ... catch` to prevent disrupting primary user operations.
- Direct MySQL insertion via PDO prepared statements.
- `ip_address`: Read from `$_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'`.
- `created_at`: Formatted as `Y-m-d H:i:s` in local/server time.

---

## 5. Specification: Acceptance Criteria & CLI Test Suite (`tests/run_tests.php`)

### 5.1 Test Execution Architecture
- Script: `tests/run_tests.php`.
- Execution command: `D:\xam\php\php.exe tests/run_tests.php`.
- Execution target: Live XAMPP Apache on port 80 (`http://localhost/my-web-project/`) and live MySQL (`edu_radar`).
- HTTP mechanism: cURL or native stream contexts with persistent cookie jars across multi-turn sessions for distinct users (`admin`, `gv_toan`, `gv_van`, and unauthenticated guest).
- Direct DB validation: Parallel PDO connection to MySQL `edu_radar` to inspect row counts, constraints, values, and audit log entries directly.

### 5.2 Test Scenarios Specification

```
+------------------------------------------------------------------------------------------------------------------+
| TEST SUITE SCENARIOS (tests/run_tests.php)                                                                       |
+----+----------------------------------------------+-------------------------------------------------------------+
| #  | Scenario                                     | Verification Checks                                         |
+----+----------------------------------------------+-------------------------------------------------------------+
| 1  | Multi-Account Authentication                 | - Login 'admin' / '123456' succeeds (HTTP 302 to admin.php) |
|    | & Password Security                          | - Login 'gv_toan' / '123456' succeeds (HTTP 302 to nhap_diem)|
|    |                                              | - Login 'gv_van' / '123456' succeeds (HTTP 302 to nhap_diem) |
|    |                                              | - Rejects wrong password '1234567' (HTTP 200 with error)    |
|    |                                              | - Confirms NO hardcoded password bypass in login.php        |
|    |                                              | - Verifies audit_logs records 'Đăng nhập thành công/thất bại'|
+----+----------------------------------------------+-------------------------------------------------------------+
| 2  | RBAC & Server-Side Access Control            | - Unauthenticated GET/POST admin.php -> HTTP 403 / redirect |
|    |                                              | - Teacher 'gv_toan' GET/POST admin.php -> HTTP 403 / redirect|
|    |                                              | - Teacher POSTing batch_assign -> Rejected, 0 rows added    |
|    |                                              | - Teacher accessing unassigned class in nhap_diem -> Denied |
|    |                                              | - Verifies audit_logs records 'Truy cập trái phép bị chặn'  |
+----+----------------------------------------------+-------------------------------------------------------------+
| 3  | Batch Assignment & Deduplication             | - Admin batch assigns gv_toan to classes [1, 2] for 'Toán'  |
|    |                                              | - teacher_classes rows verified in MySQL                    |
|    |                                              | - Submitting identical batch assignment twice results in    |
|    |                                              |   EXACTLY 0 duplicate rows (UNIQUE constraint enforced)     |
|    |                                              | - Verifies audit_logs records 'Phân công giáo viên'         |
+----+----------------------------------------------+-------------------------------------------------------------+
| 4  | Student Roster Import (.xlsx/.csv)           | - Admin uploads binary .xlsx student file to target class   |
|    | & Deduplication                              | - Verifies students table populated in MySQL                |
|    |                                              | - Uploading the exact same file a 2nd time results in       |
|    |                                              |   0 duplicate students in that class                        |
|    |                                              | - Verifies audit_logs records 'Import học sinh'             |
+----+----------------------------------------------+-------------------------------------------------------------+
| 5  | Gradebook Manual Save & Calculation          | - gv_toan saves scores (tx1=8, tx2=7, tx3=6.5, tx4=6,       |
|    |                                              |   gk=8, ck=9) for assigned class                            |
|    |                                              | - Computes expected avg_score = round(70.5 / 9, 1) = 7.8    |
|    |                                              | - Verifies MySQL scores row updated with avg_score = 7.80   |
|    |                                              | - Verifies audit_logs records 'Lưu sổ điểm'                 |
+----+----------------------------------------------+-------------------------------------------------------------+
| 6  | VnEdu Gradebook .xlsx Import & Upsert        | - Uploads valid binary .xlsx gradebook file in nhap_diem.php|
|    |                                              | - Verifies scores upserted with accurate avg_score          |
|    |                                              | - Re-uploading file upserts in place; 0 duplicates created  |
|    |                                              | - Verifies audit_logs records 'Import file điểm'            |
+----+----------------------------------------------+-------------------------------------------------------------+
| 7  | Early-Warning Radar & Zalo Check             | - gv_toan opens index.php                                   |
|    |                                              | - Verifies Red, Yellow, Green status classification         |
|    |                                              | - Verifies HTML contains 0 instances of 'Copy Tin Nhắn Zalo'|
|    |                                              | - Verifies HTML contains 0 Zalo scripts, buttons, or toasts |
+----+----------------------------------------------+-------------------------------------------------------------+
| 8  | Complete Audit Trail Invariant               | - Queries MySQL audit_logs for all 7 tested operations      |
|    |                                              | - Ensures user_id, username, action, ip_address, created_at|
|    |                                              |   are non-null and accurately stamped                       |
+----+----------------------------------------------+-------------------------------------------------------------+
| 9  | Test Suite Success & Exit Code               | - Summary output displays total passed tests                |
|    |                                              | - Zero test failures (Failures: 0)                          |
|    |                                              | - Process exits with code 0                                 |
+----+----------------------------------------------+-------------------------------------------------------------+
```

---

## Features Discovered
| # | Category | Feature | Description | Inputs | Outputs | Error Behavior | Discovered Via |
|---|----------|---------|-------------|--------|---------|----------------|----------------|
| 1 | Auth | Multi-Account RBAC | Session-based role separation for Admin and Subject Teacher | Username & Password | Session variables (`user_id`, `role`, `username`) | HTTP 403 on forbidden pages; redirect to `login.php` on missing session | `ORIGINAL_REQUEST.md` R1, `login.php` |
| 2 | Auth | Password Verification | Strict `password_verify()` against stored bcrypt hash | Plaintext password | Boolean match | No bypass permitted; rejects incorrect passwords | `ORIGINAL_REQUEST.md` R1, `login.php:16` |
| 3 | Admin | Class Matrix Management | Organization of classes by Grade (6-9) and Section (A-C) | Grade, Section, Number | HTML class matrix with toggle checkboxes | Reject duplicate class name via `UNIQUE(name)` | `ORIGINAL_REQUEST.md` R2, `admin.php:135` |
| 4 | Admin | Batch Checkbox Assignment | Bulk assignment of a teacher + subject to selected classes | Teacher ID, Subject, array of Class IDs | Rows in `teacher_classes` | Ignore duplicate assignment via `UNIQUE(teacher_id, class_id, subject)` | `ORIGINAL_REQUEST.md` R2, `admin.php:23` |
| 5 | Admin | Student Roster Import | Import student list into a specific class via `.xlsx` or `.csv` | Target Class ID, File (`.xlsx`/`.csv`) | Rows in `students` table | Upsert/skip existing to avoid duplicates | `ORIGINAL_REQUEST.md` R2, `admin.php:59` |
| 6 | Admin | Gradebook Impersonation/Inspection | Admin directly inspects and edits any teacher's assigned gradebook | Teacher ID, Subject, Class ID | Redirect to `nhap_diem.php` in supervisory mode | Return 403 or redirect if not admin | `ORIGINAL_REQUEST.md` R2, `admin.php:94` |
| 7 | Admin | Audit Log Viewer | Tabular view of the latest system audit events | None (GET) | Table of audit events (Time, User, Action, Details, IP) | Empty table message if no logs | `ORIGINAL_REQUEST.md` R2, `admin.php:154` |
| 8 | Gradebook | VnEdu Grid View | Spreadsheet grid showing STT, Name, Birthday, ĐĐGtx (1-4), ĐĐGgk, ĐĐGck, ĐTBmhk | Class ID, Subject | Rendered HTML table with inputs | 403 if teacher is not assigned to class/subject | `ORIGINAL_REQUEST.md` R3, `nhap_diem.php:278` |
| 9 | Gradebook | Automatic Average Calculation | Real-time calculation of `ĐTBmhk` using weighted formula rounded to 1 decimal | Scores array (`tx1..4, gk, ck`) | `ĐTBmhk` numeric value | `null` if no scores entered | `ORIGINAL_REQUEST.md` R3, `nhap_diem.php:74` |
| 10 | Gradebook | Spreadsheet Grade Saving | Manual update of scores in `scores` table | Nested array of student scores | Updated rows in `scores` table | Flash error if validation fails | `ORIGINAL_REQUEST.md` R3, `nhap_diem.php:61` |
| 11 | Gradebook | VnEdu Grade Sheet File Import | Bulk import of grades from `.xlsx` or `.csv` file into current class/subject | File upload (`.xlsx`/`.csv`) | Upserted records in `scores` | Flash error if file format invalid | `ORIGINAL_REQUEST.md` R3, `nhap_diem.php:111` |
| 12 | Gradebook | Sample File Download | Download sample `.xlsx` gradebook template | GET request | Binary `.xlsx` file download | N/A | `ORIGINAL_REQUEST.md` R3 |
| 13 | Gradebook | Export Gradebook to Excel | Export active class/subject gradebook as `.xlsx` | GET request with class & subject | Binary `.xlsx` file download | 403 if unassigned teacher | `ORIGINAL_REQUEST.md` R3 |
| 14 | Radar | Early-Warning Status Classifier | Pedagogical evaluation classifying students into Red, Yellow, Green | Scores and `avg_score` | Status tag and diagnostic reason | Default to Green with "Chưa có điểm" if empty | `ORIGINAL_REQUEST.md` R4, `index.php:26` |
| 15 | Radar | Strict Zalo Button Removal | Total removal of Zalo copy button, script, and modal | N/A | Clean pedagogical recommendation text | Alert on presence of Zalo artifacts | `ORIGINAL_REQUEST.md` R4, `index.php:209` |
| 16 | Audit | Comprehensive Event Logging | Automated recording of state changes in `audit_logs` | User ID, username, action, details, IP | Inserted row in `audit_logs` | Graceful catch (non-blocking) | `ORIGINAL_REQUEST.md` R5, `includes/db.php:42` |
| 17 | Test | Repeatable CLI Acceptance Suite | Automated test runner validating full system integrity | CLI command | Detailed test report with 0 failures | Non-zero exit code on failure | `ORIGINAL_REQUEST.md` Line 64, `tests/run_tests.php` |

---

## Edge Cases
| # | Feature | Input | Observed Behavior |
|---|---------|-------|-------------------|
| 1 | Student Roster Import | Re-uploading identical `.xlsx` file twice | Deduplication logic matches existing `(class_id, full_name, birthday)`: inserts 0 rows on 2nd upload. |
| 2 | Student Roster Import | Student row with empty birthday (`NULL`) | Birthday stored as `NULL`; deduplication checks `(birthday = ? OR (birthday IS NULL AND ? IS NULL))`. |
| 3 | Student Roster Import | Full name with multiple spaces: `"  Nguyễn   Văn   An  "` | Normalized to `"Nguyễn Văn An"` before database uniqueness check. |
| 4 | Student Roster Import | Phone number with dots or spaces: `"090.123 4567"` | Normalized to `"0901234567"` before storage. |
| 5 | Gradebook Calculation | Only regular scores entered ($tx_1=8, tx_2=6$); no GK or CK | Average calculated as $(8+6)/2 = 7.0$ (weight = 2). Does not divide by 0 or 7. |
| 6 | Gradebook Calculation | Student has scores $tx_1=7, tx_2=8, tx_3=6, tx_4=7, gk=8, ck=9$ | Sum = $28 + 16 + 27 = 71$; Weight = $4 + 2 + 3 = 9$; $71/9 = 7.888... \to 7.9$. |
| 7 | Gradebook Calculation | Score string with comma as decimal separator: `"8,5"` | Normalized to float `8.5` before computation and SQL storage. |
| 8 | Gradebook Calculation | Invalid score: `"-1.0"` or `"10.5"` or `"abc"` | Validation rejects cell input; displays error without corrupting database. |
| 9 | Gradebook Import | File contains a student name not currently in the class | Student is automatically created in `students` for the class, then score record is inserted. |
| 10 | Gradebook Import | File uploaded with changed scores for existing students | `ON DUPLICATE KEY UPDATE` updates scores in place without duplicating `scores` rows. |
| 11 | Early Warning | Student with `avg_score = 5.0` but latest score drops to `4.0` | Rule R2 triggers 🔴 Nguy cấp due to recent test drop below pass threshold. |
| 12 | Early Warning | Student with `avg_score = 7.2` but one component score is `4.5` | Rule Y1 triggers 🟡 Cần chú ý ("Có bài kiểm tra thành phần dưới trung bình"). |
| 13 | Early Warning | Student with no entered scores at all | Evaluated as 🟢 Ổn định with message `"Chưa có điểm kiểm tra đánh giá"`. |
| 14 | Security | Teacher attempts POST to `admin.php?action=batch_assign` | Blocked server-side with HTTP 403; event logged to `audit_logs` as `Truy cập trái phép bị chặn`. |
| 15 | Security | Teacher tampers URL to edit unassigned `nhap_diem.php?class_id=9C1&subject=Văn` | Blocked server-side with HTTP 403; 0 rows modified; audit log records unauthorized attempt. |
| 16 | Security | User attempts login with password `'123456'` when hash in DB is different | Denied; bypass `|| $password === '123456'` removed so only valid `password_verify()` passes. |
| 17 | Audit Logging | Anonymous guest triggers login failure | `audit_logs` records `user_id = NULL`, `username = 'submitted_username'`, `action = 'Đăng nhập thất bại'`. |

---
*Report generated and validated for implementation handoff.*
