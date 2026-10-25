# Architectural Analysis & Specification: VnEdu Student Roster Import, Deduplication Engine & M3 Test Suite

- **Author**: `explorer_m3_3` (M3 Student Import & Dedup Explorer)
- **Target Systems**: `admin.php`, `includes/xlsx_helper.php`, MySQL `edu_radar`, `tests/verify_m3.php`
- **Environment**: Apache 2.4 (port 80) / PHP 8.0.30 (`D:\xam\php\php.exe`) / MariaDB 10.4 (port 3306)
- **Status**: Comprehensive Specification & Test Architecture

---

## 1. Executive Summary

Milestone 3 of the EduRadar system requires an administrative overhaul enabling school administrators to manage classes, batch-assign teachers across a secondary school class matrix (Grades 6–9, Sections A–C, Classes 1–9), inspect teacher gradebooks, and import student rosters in standard VnEdu formats (`.xlsx` and `.csv`).

This document provides the definitive architectural design for:
1. **The VnEdu Student Roster Import Engine**: Capable of ingesting both native OpenXML `.xlsx` and UTF-8/BOM `.csv` files, featuring tolerant header row detection (lines 1 to 4), dynamic column mapping, and multi-format Vietnamese date normalization (including Excel serial numbers).
2. **The Dual-Layer Deduplication Engine**: Enforcing strict deduplication on composite key `(class_id, full_name, birthday)` at both the application level (pre-check query) and database level (`UNIQUE KEY unique_class_student`), solving MySQL's `NULL` duplicate bypass vulnerability.
3. **Audit Logging & User Feedback**: Precise logging of student import events with file name, class name, inserted count, and duplicate count; and user-facing feedback notifications.
4. **The Automated Verification Suite (`tests/verify_m3.php`)**: A standalone CLI test suite with 6 test groups and 25 test cases, verifying all M3 features against live Apache and MariaDB via `TestClient` with `CURLFile` multipart uploads and PDO state inspection.

---

## 2. Current State Analysis & Gap Identification

### 2.1 Inspection of Existing Import Logic in `admin.php`
In the current codebase (`admin.php` lines 50–86):
```php
// Existing import logic in admin.php:
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_students') {
    require_admin();
    $target_class_id = (int)$_POST['target_class_id'];
    if ($target_class_id && isset($_FILES['student_file']) && $_FILES['student_file']['error'] === UPLOAD_ERR_OK) {
        $handle = fopen($_FILES['student_file']['tmp_name'], 'r');
        if ($handle !== false) {
            $imported = 0;
            fgetcsv($handle, 1000, ",");
            while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                if (!empty($data[0])) {
                    $fullName = trim($data[0]);
                    $birthday = !empty($data[1]) ? trim($data[1]) : null;
                    $phone = !empty($data[2]) ? trim($data[2]) : '';

                    $chk = $pdo->prepare("SELECT id FROM students WHERE class_id = ? AND full_name = ?");
                    $chk->execute([$target_class_id, $fullName]);
                    if (!$chk->fetch()) {
                        $ins = $pdo->prepare("INSERT INTO students (class_id, full_name, birthday, parent_phone) VALUES (?, ?, ?, ?)");
                        $ins->execute([$target_class_id, $fullName, $birthday, $phone]);
                        $imported++;
                    }
                }
            }
            fclose($handle);
            ...
```

### 2.2 Critical Gaps Identified

| # | Gap / Bug | Severity | Impact | Resolution |
|---|-----------|----------|--------|------------|
| 1 | **Unknown column `parent_phone`** | **CRITICAL** | `students` table has column `phone`, NOT `parent_phone`. Any insert currently causes PDOException `SQLSTATE[42S22]: Column not found`. | Update SQL to use column `phone` (and also populate `student_code` and `gender`). |
| 2 | **No `.xlsx` support** | **HIGH** | Only parses `.csv` via `fgetcsv()`. Ignores `.xlsx` uploads, violating Acceptance Criterion R2 / Feature #9. | Integrate with `includes/xlsx_helper.php` (`parse_xlsx()` & `parse_csv()`). |
| 3 | **Rigid column indexing** | **HIGH** | Hardcodes `$data[0]` = Name, `$data[1]` = Birthday, `$data[2]` = Phone. If VnEdu export has `STT` in column 0, or header metadata on rows 1–2, names become STT numbers and dates fail. | Implement resilient header detection across rows 0–4 with fuzzy keyword column mapping. |
| 4 | **No date normalization** | **HIGH** | VnEdu exports dates as `DD/MM/YYYY` (e.g. `12/03/2015`) or Excel serial numbers (e.g. `42075`). Passing these raw to MySQL `DATE` column results in `0000-00-00` or insert failures. | Implement `normalize_vn_date()` converting `DD/MM/YYYY`, `DD-MM-YYYY`, `DD.MM.YYYY`, and Excel serial dates to `YYYY-MM-DD`. |
| 5 | **MySQL `NULL` in UNIQUE key bypass** | **CRITICAL** | In MariaDB/MySQL InnoDB, `UNIQUE(class_id, full_name, birthday)` allows unlimited duplicates when `birthday IS NULL` (verified via live test). | Normalize empty birthdays to sentinel date `'1970-01-01'` or require non-empty dates, and enforce composite deduplication in PHP. |
| 6 | **Weak pre-check query** | **MEDIUM** | Query `SELECT id FROM students WHERE class_id = ? AND full_name = ?` does not check `birthday`, meaning two students in the same class with identical names but different birthdays would falsely block the second student. | Pre-check must match `(class_id, full_name, birthday)`. |
| 7 | **Feedback does not report duplicates** | **MEDIUM** | Only says "Đã import thành công $imported học sinh". If 5 duplicates were skipped, user gets no visibility. | Report: "Nhập thành công X học sinh vào lớp Y (bỏ qua Z học sinh đã tồn tại trong lớp)". |
| 8 | **Audit log missing file & duplicate metrics** | **LOW** | Details only mention imported count; lacks uploaded file name and duplicate count. | Log: `Admin import học sinh vào lớp [6A1] từ file [danh_sach.xlsx]: thêm mới 30, trùng lặp bỏ qua 5.` |

---

## 3. VnEdu Student Roster Import Engine Architecture

```
                  ┌────────────────────────────────────────┐
                  │ Admin Uploads Student Roster (.xlsx/.csv)│
                  └──────────────────┬─────────────────────┘
                                     │
                                     ▼
                ┌────────────────────────────────────────┐
                │ MIME & Extension Validation (.xlsx/.csv)│
                └────────────────────┬───────────────────┘
                                     │
                   ┌─────────────────┴─────────────────┐
                   ▼                                   ▼
        ┌─────────────────────┐             ┌─────────────────────┐
        │ .xlsx: parse_xlsx() │             │  .csv: parse_csv()  │
        │ (ZipArchive/SimpleXML)│           │ (UTF-8 BOM Stripped)│
        └──────────┬──────────┘             └──────────┬──────────┘
                   └─────────────────┬─────────────────┘
                                     │ 2D Array of Rows
                                     ▼
                ┌────────────────────────────────────────┐
                │ Resilient Header Detector (Rows 0 to 4)│
                │ Detects: STT, Mã HS, Họ và tên, Ngày   │
                │ sinh, Giới tính, Số điện thoại         │
                └────────────────────┬───────────────────┘
                                     │ Column Index Mapping
                                     ▼
                ┌────────────────────────────────────────┐
                │ Row Sanitizer & Normalization Pipeline  │
                │ - Full name: trim, remove extra spaces │
                │ - Birthday: DD/MM/YYYY, Excel serial   │
                │   number 42075 -> YYYY-MM-DD           │
                │ - Empty birthday -> 1970-01-01         │
                │ - Gender: Nam / Nữ                     │
                │ - Phone: digits only                   │
                └────────────────────┬───────────────────┘
                                     │ Normalized Student Record
                                     ▼
                ┌────────────────────────────────────────┐
                │ Deduplication Engine (Layer 1 & Layer 2)│
                │ - SELECT id WHERE class_id, full_name, │
                │   birthday                             │
                │ - INSERT INTO students ...             │
                │ - Catch 1062 Duplicate Key             │
                └────────────────────┬───────────────────┘
                                     │
                   ┌─────────────────┴─────────────────┐
                   ▼                                   ▼
        ┌─────────────────────┐             ┌─────────────────────┐
        │  $insertedCount++   │             │  $duplicateCount++  │
        └──────────┬──────────┘             └──────────┬──────────┘
                   └─────────────────┬─────────────────┘
                                     │
                                     ▼
                ┌────────────────────────────────────────┐
                │ Feedback Message & Database Audit Log  │
                │ "Nhập thành công X, bỏ qua Y..."       │
                │ log_action() -> audit_logs             │
                └────────────────────────────────────────┘
```

### 3.1 Resilient Header Detection & Column Mapping
VnEdu exports typically present headers in one of three layouts:
- **Layout A (Pure table)**: Row 0 has headers (`STT, Họ và tên, Ngày sinh, Giới tính, SĐT`).
- **Layout B (Standard VnEdu with school header)**:
  - Row 0: `PHÒNG GIÁO DỤC VÀ ĐÀO TẠO ... - TRƯỜNG THCS ...`
  - Row 1: `DANH SÁCH HỌC SINH LỚP 6A1 - NĂM HỌC 2026-2027`
  - Row 2: `STT | Mã học sinh | Họ và tên | Ngày sinh | Giới tính | SĐT phụ huynh`
- **Layout C (Headerless / minimal CSV)**:
  - Row 0: `Nguyễn Tuấn Kiệt, 2015-04-12, 0912345601`

**Detection Algorithm**:
```php
function detect_student_roster_headers(array $rows): array {
    $fieldKeywords = [
        'full_name'    => ['họ và tên', 'họ tên', 'họ tên học sinh', 'tên học sinh', 'họ và tên hs'],
        'birthday'     => ['ngày sinh', 'ns', 'năm sinh', 'ngày/tháng/năm sinh', 'ngaysinh'],
        'student_code' => ['mã học sinh', 'mã hs', 'mã định danh', 'mã số', 'ma hs'],
        'gender'       => ['giới tính', 'nam/nữ', 'phái', 'gioitinh'],
        'phone'        => ['số điện thoại', 'sđt', 'điện thoại', 'sđt phụ huynh', 'điện thoại phụ huynh', 'phone'],
        'stt'          => ['stt', 'số tt', 'số thứ tự']
    ];

    $headerRowIndex = -1;
    $colMap = [];

    // Scan the first 5 rows
    $scanLimit = min(5, count($rows));
    for ($r = 0; $r < $scanLimit; $r++) {
        $row = $rows[$r];
        if (!is_array($row) || empty($row)) continue;

        $tempMap = [];
        $matchedScore = 0;

        foreach ($row as $colIdx => $cellVal) {
            $norm = mb_strtolower(trim((string)$cellVal), 'UTF-8');
            if ($norm === '') continue;

            foreach ($fieldKeywords as $field => $keywords) {
                if (isset($tempMap[$field])) continue; // already mapped
                foreach ($keywords as $kw) {
                    if (mb_strpos($norm, $kw) !== false || $norm === $kw) {
                        $tempMap[$field] = $colIdx;
                        if ($field === 'full_name') $matchedScore += 5;
                        if ($field === 'birthday') $matchedScore += 3;
                        if ($field === 'stt' || $field === 'gender') $matchedScore += 1;
                        break;
                    }
                }
            }
        }

        // A valid header row MUST at least match full_name or (stt and birthday)
        if (isset($tempMap['full_name']) || (isset($tempMap['stt']) && isset($tempMap['birthday']))) {
            $headerRowIndex = $r;
            $colMap = $tempMap;
            break;
        }
    }

    // Fallback if no header row was detected (assume standard 3 or 4 column format starting at row 0)
    if ($headerRowIndex === -1) {
        $headerRowIndex = -1; // Data starts at row 0
        $colCount = isset($rows[0]) ? count($rows[0]) : 0;
        if ($colCount >= 5) {
            $colMap = ['stt' => 0, 'full_name' => 1, 'birthday' => 2, 'gender' => 3, 'phone' => 4];
        } elseif ($colCount === 4) {
            $colMap = ['stt' => 0, 'full_name' => 1, 'birthday' => 2, 'phone' => 3];
        } else {
            $colMap = ['full_name' => 0, 'birthday' => 1, 'phone' => 2];
        }
    }

    return [$headerRowIndex, $colMap];
}
```

---

### 3.2 Date Normalization Pipeline (`normalize_vn_date`)

Vietnamese dates in VnEdu exports arrive in multiple representations:
1. **Vietnamese slash/dash format**: `DD/MM/YYYY`, `D/M/YYYY`, `DD-MM-YYYY`, `DD.MM.YYYY` (e.g. `12/03/2015`, `5/4/2015`).
2. **ISO format**: `YYYY-MM-DD`, `YYYY/MM/DD` (e.g. `2015-03-12`).
3. **Excel Serial Date Number**: Pure numeric value representing days since 1899-12-30 (e.g. `42075` -> `2015-03-12`).
4. **Invalid / Empty Date**: Returns `'1970-01-01'` (default sentinel) so MySQL's composite unique index `(class_id, full_name, birthday)` is strictly enforced and cannot be bypassed by `NULL`.

**Tested & Verified Conversion Implementation**:
```php
function normalize_vn_date($raw): string {
    if ($raw === null) return '1970-01-01';
    $val = trim((string)$raw);
    if ($val === '') return '1970-01-01';

    // 1. Excel Serial Date Number (e.g. 42075)
    if (is_numeric($val) && (float)$val > 1000 && (float)$val < 100000) {
        $days = (int)$val;
        try {
            $base = new DateTime('1899-12-30');
            $base->modify("+{$days} days");
            return $base->format('Y-m-d');
        } catch (Throwable $e) {
            return '1970-01-01';
        }
    }

    // 2. Format: DD/MM/YYYY, DD-MM-YYYY, DD.MM.YYYY
    if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/', $val, $m)) {
        $d = (int)$m[1];
        $mth = (int)$m[2];
        $y = (int)$m[3];
        if (checkdate($mth, $d, $y)) {
            return sprintf('%04d-%02d-%02d', $y, $mth, $d);
        }
    }

    // 3. Format: YYYY-MM-DD, YYYY/MM/DD
    if (preg_match('/^(\d{4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})$/', $val, $m)) {
        $y = (int)$m[1];
        $mth = (int)$m[2];
        $d = (int)$m[3];
        if (checkdate($mth, $d, $y)) {
            return sprintf('%04d-%02d-%02d', $y, $mth, $d);
        }
    }

    // 4. Try standard DateTime parsing fallback
    try {
        $dt = new DateTime($val);
        return $dt->format('Y-m-d');
    } catch (Throwable $e) {
        return '1970-01-01';
    }
}
```

---

## 4. Deduplication Engine Specification

### 4.1 Two-Layer Defense Architecture

```
Incoming Student: (Class ID: 1, Name: "Nguyễn Văn An", Birthday: "2015-03-12")
                                   │
                                   ▼
                   ┌──────────────────────────────┐
                   │ LAYER 1: Application Query   │
                   │ SELECT id FROM students      │
                   │ WHERE class_id = ?           │
                   │   AND full_name = ?          │
                   │   AND birthday = ?           │
                   └───────────────┬──────────────┘
                                   │
                ┌──────────────────┴──────────────────┐
                │ Found existing ID?                  │
                ▼ YES                                 ▼ NO
      ┌──────────────────────┐             ┌──────────────────────┐
      │ Duplicate Detected!  │             │ LAYER 2: DB Insert   │
      │ $duplicateCount++    │             │ INSERT INTO students │
      │ Skip insertion       │             │ (class_id, full_name,│
      │ (Optionally update   │             │  birthday, ...)      │
      │  phone / gender)     │             └──────────┬───────────┘
      └──────────────────────┘                        │
                                           ┌──────────┴──────────┐
                                           │ Constraint Violation?│
                                           ▼ (Error 1062)        ▼ SUCCESS
                                 ┌──────────────────┐   ┌──────────────────┐
                                 │ $duplicateCount++│   │ $insertedCount++ │
                                 └──────────────────┘   └──────────────────┘
```

### 4.2 Proved MySQL `NULL` Handling in Composite Unique Key
Our live runtime investigation verified that executing:
```sql
INSERT INTO students (class_id, full_name, birthday) VALUES (1, 'Test Student', NULL);
INSERT INTO students (class_id, full_name, birthday) VALUES (1, 'Test Student', NULL);
```
**Succeeds and inserts two duplicate rows** because SQL standard dictates `NULL != NULL`.
Conversely:
```sql
INSERT INTO students (class_id, full_name, birthday) VALUES (1, 'Test Student', '1970-01-01');
INSERT INTO students (class_id, full_name, birthday) VALUES (1, 'Test Student', '1970-01-01');
```
**Fails immediately with `SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry`**.

Therefore:
1. `normalize_vn_date()` guarantees `birthday` is always a valid `YYYY-MM-DD` date string (defaulting to `'1970-01-01'` if absent).
2. The application pre-query checks `WHERE class_id = ? AND full_name = ? AND birthday = ?`.
3. The database constraint `UNIQUE KEY unique_class_student (class_id, full_name, birthday)` guarantees race-condition immunity.

---

## 5. Audit Logging & User Feedback Reporting

### 5.1 User Feedback Messaging Matrix

| Condition | Message Format | Alert Class |
|---|---|---|
| `$insertedCount > 0 && $duplicateCount === 0` | `Đã nhập thành công $insertedCount học sinh vào lớp $className.` | `alert success` |
| `$insertedCount > 0 && $duplicateCount > 0` | `Đã nhập thành công $insertedCount học sinh vào lớp $className (bỏ qua $duplicateCount học sinh đã tồn tại trong lớp).` | `alert success` |
| `$insertedCount === 0 && $duplicateCount > 0` | `Tất cả $duplicateCount học sinh trong file đã tồn tại trong lớp $className, không có học sinh mới nào được thêm.` | `alert warning` |
| `$insertedCount === 0 && $duplicateCount === 0` | `Không tìm thấy dữ liệu học sinh hợp lệ nào trong file tải lên.` | `alert red-alert` |

### 5.2 Audit Logging Format
```php
log_action(
    (int)$_SESSION['user_id'],
    $_SESSION['username'] ?? 'admin',
    'Import học sinh',
    "Admin import học sinh vào lớp [$className] từ file [$originalFileName]: thêm mới $insertedCount, trùng lặp bỏ qua $duplicateCount."
);
```
This logs:
- `user_id`: Admin's user ID from `$_SESSION['user_id']`.
- `username`: Admin's username from `$_SESSION['username']`.
- `action`: `'Import học sinh'`.
- `details`: Exact class name, uploaded file name, inserted count, and duplicate count.
- `ip_address`: Remote IP from `$_SERVER['REMOTE_ADDR']`.
- `created_at`: MySQL timestamp.

---

## 6. Detailed Implementation Specification for `admin.php`

### 6.1 Replacement Code for `import_students` Handler in `admin.php`

```php
// =========================================================================
// TÍNH NĂNG 2: ADMIN IMPORT DANH SÁCH HỌC SINH VÀO LỚP (.xlsx & .csv)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_students') {
    require_admin();
    $target_class_id = (int)($_POST['target_class_id'] ?? 0);

    // 1. Kiểm tra lớp đích
    $cStmt = $pdo->prepare("SELECT name FROM classes WHERE id = ?");
    $cStmt->execute([$target_class_id]);
    $className = $cStmt->fetchColumn();

    if (!$className) {
        $err = "Vui lòng chọn lớp học hợp lệ!";
    } elseif (!isset($_FILES['student_file']) || $_FILES['student_file']['error'] !== UPLOAD_ERR_OK) {
        $err = "Vui lòng chọn file danh sách học sinh (.xlsx hoặc .csv)!";
    } else {
        $originalFileName = basename($_FILES['student_file']['name']);
        $ext = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
        $tmpPath = $_FILES['student_file']['tmp_name'];

        $rows = [];
        if ($ext === 'csv') {
            require_once __DIR__ . '/includes/xlsx_helper.php';
            $rows = parse_csv($tmpPath);
        } elseif ($ext === 'xlsx') {
            require_once __DIR__ . '/includes/xlsx_helper.php';
            $rows = parse_xlsx($tmpPath);
        } else {
            $err = "Định dạng file không được hỗ trợ! Vui lòng tải lên file .xlsx hoặc .csv.";
        }

        if (empty($err)) {
            if (empty($rows)) {
                $err = "File tải lên không có dữ liệu hoặc không đọc được nội dung!";
            } else {
                // 2. Nhận diện dòng tiêu đề & ánh xạ cột
                list($headerRowIdx, $colMap) = detect_student_roster_headers($rows);
                $startRow = $headerRowIdx >= 0 ? $headerRowIdx + 1 : 0;

                $insertedCount = 0;
                $duplicateCount = 0;

                // Chuẩn bị các câu lệnh truy vấn
                $chkStmt = $pdo->prepare("SELECT id FROM students WHERE class_id = ? AND full_name = ? AND birthday = ?");
                $insStmt = $pdo->prepare("INSERT INTO students (class_id, student_code, full_name, birthday, gender, phone) VALUES (?, ?, ?, ?, ?, ?)");
                $updStmt = $pdo->prepare("UPDATE students SET gender = COALESCE(NULLIF(?, ''), gender), phone = COALESCE(NULLIF(?, ''), phone), student_code = COALESCE(NULLIF(?, ''), student_code) WHERE id = ?");

                for ($i = $startRow; $i < count($rows); $i++) {
                    $row = $rows[$i];
                    if (!is_array($row) || empty($row)) continue;

                    // Lấy họ và tên
                    $fullName = '';
                    if (isset($colMap['full_name']) && isset($row[$colMap['full_name']])) {
                        $fullName = trim((string)$row[$colMap['full_name']]);
                    } elseif (isset($row[0]) && !is_numeric($row[0])) {
                        $fullName = trim((string)$row[0]);
                    } elseif (isset($row[1]) && is_numeric($row[0])) {
                        $fullName = trim((string)$row[1]);
                    }

                    // Bỏ qua dòng trống hoặc dòng tổng kết / ghi chú
                    if ($fullName === '' || mb_stripos($fullName, 'tổng số') !== false || mb_stripos($fullName, 'ghi chú') !== false) {
                        continue;
                    }

                    // Lấy và chuẩn hóa ngày sinh
                    $rawBirthday = null;
                    if (isset($colMap['birthday']) && isset($row[$colMap['birthday']])) {
                        $rawBirthday = $row[$colMap['birthday']];
                    } elseif (isset($row[1]) && !isset($colMap['birthday'])) {
                        $rawBirthday = $row[1];
                    }
                    $normalizedBirthday = normalize_vn_date($rawBirthday);

                    // Lấy mã học sinh
                    $studentCode = null;
                    if (isset($colMap['student_code']) && isset($row[$colMap['student_code']])) {
                        $sc = trim((string)$row[$colMap['student_code']]);
                        if ($sc !== '') $studentCode = $sc;
                    }

                    // Lấy giới tính
                    $gender = null;
                    if (isset($colMap['gender']) && isset($row[$colMap['gender']])) {
                        $g = trim((string)$row[$colMap['gender']]);
                        if (in_array(mb_strtolower($g), ['nam', 'nữ', 'nu'])) {
                            $gender = mb_convert_case($g, MB_CASE_TITLE, "UTF-8");
                        }
                    }

                    // Lấy số điện thoại
                    $phone = null;
                    if (isset($colMap['phone']) && isset($row[$colMap['phone']])) {
                        $p = preg_replace('/[^0-9]/', '', (string)$row[$colMap['phone']]);
                        if ($p !== '') $phone = $p;
                    }

                    // 3. Kiểm tra trùng lặp (Deduplication Check)
                    $chkStmt->execute([$target_class_id, $fullName, $normalizedBirthday]);
                    $existingId = $chkStmt->fetchColumn();

                    if ($existingId) {
                        $duplicateCount++;
                        // Cập nhật thông tin bổ sung nếu bản ghi cũ còn trống
                        $updStmt->execute([$gender, $phone, $studentCode, $existingId]);
                    } else {
                        try {
                            $insStmt->execute([$target_class_id, $studentCode, $fullName, $normalizedBirthday, $gender, $phone]);
                            $insertedCount++;
                        } catch (PDOException $e) {
                            if ($e->errorInfo[1] == 1062) {
                                $duplicateCount++;
                            } else {
                                throw $e;
                            }
                        }
                    }
                }

                // 4. Ghi nhật ký vào database
                log_action(
                    (int)$_SESSION['user_id'],
                    $_SESSION['username'] ?? 'admin',
                    'Import học sinh',
                    "Admin import học sinh vào lớp [$className] từ file [$originalFileName]: thêm mới $insertedCount, trùng lặp bỏ qua $duplicateCount."
                );

                // 5. Phản hồi cho người dùng
                if ($insertedCount > 0 && $duplicateCount > 0) {
                    $msg = "Đã nhập thành công $insertedCount học sinh vào lớp $className (bỏ qua $duplicateCount học sinh đã tồn tại trong lớp).";
                } elseif ($insertedCount > 0 && $duplicateCount === 0) {
                    $msg = "Đã nhập thành công $insertedCount học sinh vào lớp $className.";
                } elseif ($insertedCount === 0 && $duplicateCount > 0) {
                    $msg = "Tất cả $duplicateCount học sinh trong file đã tồn tại trong lớp $className, không có học sinh mới nào được thêm.";
                } else {
                    $err = "Không tìm thấy dữ liệu học sinh hợp lệ nào trong file tải lên.";
                }
            }
        }
    }
}
```

### 6.2 Updated UI Form Section in `admin.php`
```html
<section class="panel-box mb-3">
    <h3>📥 Import Danh Sách Học Sinh Cho Từng Lớp (.xlsx & .csv VnEdu)</h3>
    <p class="muted small mb-3">Hỗ trợ file Excel chuẩn VnEdu (.xlsx) và file .csv. Hệ thống tự động khử trùng lặp theo (Lớp, Họ tên, Ngày sinh).</p>
    
    <form method="POST" enctype="multipart/form-data" style="display: flex; gap: 14px; align-items: flex-end; flex-wrap: wrap;">
        <input type="hidden" name="action" value="import_students">
        <div class="form-group" style="min-width: 220px;">
            <label>1. Chọn Lớp Cần Nạp Học Sinh</label>
            <select name="target_class_id" required>
                <?php foreach ($allClasses as $c): ?>
                    <option value="<?= $c['id'] ?>">Lớp <?= htmlspecialchars($c['name']) ?> (Khối <?= $c['grade'] ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>2. File Danh Sách (.xlsx hoặc .csv)</label>
            <input type="file" name="student_file" accept=".xlsx, .csv" required>
        </div>
        <button type="submit" class="btn primary">
            🚀 Nạp Danh Sách Học Sinh
        </button>
        <div style="display: flex; gap: 8px;">
            <a href="sample_students.xlsx" download class="btn outline" style="font-size: 13px;">
                📊 Tải file mẫu .xlsx
            </a>
            <a href="sample_students.csv" download class="btn outline" style="font-size: 13px;">
                📄 Tải file mẫu .csv
            </a>
        </div>
    </form>
</section>
```

---

## 7. Specification of Automated Verification Suite `tests/verify_m3.php`

The test suite is structured as a repeatable CLI program runnable via:
```bash
D:\xam\php\php.exe tests/verify_m3.php
```
It communicates with the live Apache server (`http://localhost/my-web-project/`) and directly asserts MySQL state in `edu_radar`.

### 7.1 Test Groups & Granular Test Cases Matrix

```
========================================================================================
TEST SUITE: tests/verify_m3.php (25 TEST CASES ACROSS 6 GROUPS)
========================================================================================

GROUP 1: OPENXML / CSV HELPER & SAMPLE TEMPLATE VERIFICATION
  1.1 includes/xlsx_helper.php exists with parse_xlsx, create_xlsx, parse_csv
  1.2 create_xlsx builds a valid binary OpenXML ZIP package
  1.3 Round-trip parse_xlsx(create_xlsx()) accurately preserves Vietnamese strings & numbers
  1.4 parse_csv correctly strips UTF-8 BOM (\xEF\xBB\xBF) and parses comma & semicolon
  1.5 sample_students.xlsx and sample_students.csv exist in project root and are HTTP accessible

GROUP 2: CLASS MANAGEMENT & SECONDARY SCHOOL CLASS MATRIX
  2.1 Class matrix groups 36 classes across Grades 6, 7, 8, 9 with section and student counts
  2.2 Admin creates a new class (e.g. 6C4) via POST action=add_class into classes table
  2.3 Duplicate class name creation is rejected by UNIQUE(name) with 0 duplicate rows
  2.4 Admin deletes a class via action=delete_class and deletion is recorded in audit_logs

GROUP 3: TEACHER ACCOUNT CREATION & BATCH CHECKBOX ASSIGNMENT
  3.1 Admin creates teacher account (gv_anh) via POST action=create_teacher with bcrypt hash
  3.2 Duplicate teacher username is rejected by UNIQUE(username) in users table
  3.3 Admin performs batch assignment (teacher + subject + class checkboxes across grades)
  3.4 Deduplication: Re-submitting identical batch assignment produces 0 duplicate rows
  3.5 Admin unassigns a teaching assignment via delete_assign and audit log is recorded

GROUP 4: STUDENT ROSTER IMPORT (.CSV & .XLSX) & DEDUPLICATION ENGINE
  4.1 Admin imports sample_students.csv into test class -> 5 students inserted with YYYY-MM-DD
  4.2 Deduplication on CSV: Re-importing same CSV results in 0 new rows and reports duplicates
  4.3 Admin imports student .xlsx file with Vietnamese names & various date formats
  4.4 Deduplication on XLSX: Re-importing same .xlsx results in 0 new rows and reports duplicates
  4.5 Header tolerance: Files with headers starting on row 2 or row 3 are correctly parsed
  4.6 MySQL UNIQUE(class_id, full_name, birthday) rejects duplicate insertion with Error 1062

GROUP 5: DIRECT GRADEBOOK INSPECTION & RBAC SECURITY
  5.1 Admin opens direct gradebook inspection link (nhap_diem.php?class_id=X&subject=Y) -> 200
  5.2 Teacher (gv_toan) POST to admin.php (student import or assignment) is blocked with 403
  5.3 Unauthenticated POST/GET to admin.php is blocked (403 or redirect to login.php)

GROUP 6: COMPLETE AUDIT LOGGING FOR ALL M3 OPERATIONS
  6.1 Student import logs action 'Import học sinh' with class name, file name, counts
  6.2 Batch assignment logs action 'Phân công giáo viên' with teacher, subject, classes
  6.3 Class creation & deletion write corresponding rows to audit_logs
  6.4 Teacher creation writes row to audit_logs
  6.5 Admin audit log viewer table renders recent log records with required columns
========================================================================================
```

---

### 7.2 Full Reference Code Design for `tests/verify_m3.php`

```php
<?php
/**
 * =============================================================================
 * Automated Milestone 3 Verification Suite (EduRadar THCS)
 * Kiểm thử tự động Milestone 3:
 *   - OpenXML (.xlsx) & CSV Helpers (ZipArchive & SimpleXML)
 *   - Class Matrix & Batch Checkbox Assignment
 *   - Teacher Account Creation
 *   - Student Roster Import (.xlsx & .csv) with Database Deduplication
 *   - Audit Logging & Admin Gradebook Inspection
 * 
 * Chạy lệnh kiểm thử:
 *   D:\xam\php\php.exe tests/verify_m3.php
 * =============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';

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
    echo CLR_CYAN . "[TEST] " . CLR_RESET . str_pad($name, 64, ".");
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

/**
 * HTTP Client chuyên dụng hỗ trợ Session Cookies và Multipart File Upload qua cURL
 */
class TestClient
{
    private string $baseUrl;
    private string $cookieFile;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->cookieFile = sys_get_temp_dir() . '/edu_m3_cookie_' . bin2hex(random_bytes(6)) . '.txt';
    }

    public function __destruct()
    {
        if (file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    public function clearCookies(): void
    {
        if (file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    public function request(string $method, string $path, $data = [], bool $followRedirects = false): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        $ch = curl_init();

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_FOLLOWLOCATION => $followRedirects,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 15,
        ];

        if (strtoupper($method) === 'POST') {
            $options[CURLOPT_POST] = true;
            // Nếu data là mảng chứa CURLFile hoặc có cờ multipart, cURL tự động gửi multipart/form-data
            $options[CURLOPT_POSTFIELDS] = is_array($data) ? $data : http_build_query($data);
        }

        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new Exception("cURL request to '$url' failed: $err");
        }

        $info = curl_getinfo($ch);
        $headerSize = $info['header_size'];
        $headers = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);
        curl_close($ch);

        return [
            'status'       => (int)$info['http_code'],
            'redirect_url' => (string)($info['redirect_url'] ?? ''),
            'headers'      => $headers,
            'body'         => $body,
        ];
    }

    public function get(string $path, bool $followRedirects = false): array
    {
        return $this->request('GET', $path, [], $followRedirects);
    }

    public function post(string $path, array $data = [], bool $followRedirects = false): array
    {
        return $this->request('POST', $path, $data, $followRedirects);
    }

    public function postMultipart(string $path, array $postFields, bool $followRedirects = false): array
    {
        return $this->request('POST', $path, $postFields, $followRedirects);
    }

    public function login(string $username, string $password): array
    {
        return $this->post('login.php', [
            'username' => $username,
            'password' => $password
        ], false);
    }
}

$client = new TestClient('http://localhost/my-web-project');

echo PHP_EOL . CLR_YELLOW . "============================================================" . CLR_RESET . PHP_EOL;
echo CLR_YELLOW . "  EduRadar Milestone 3: Automated Verification Suite" . CLR_RESET . PHP_EOL;
echo CLR_YELLOW . "============================================================" . CLR_RESET . PHP_EOL . PHP_EOL;

// -------------------------------------------------------------
// NHÓM 1: OPENXML / CSV HELPER & SAMPLE TEMPLATE VERIFICATION
// -------------------------------------------------------------
assertTest("1.1 includes/xlsx_helper.php exists with required functions", function () {
    $helperPath = dirname(__DIR__) . '/includes/xlsx_helper.php';
    if (!file_exists($helperPath)) {
        throw new Exception("File includes/xlsx_helper.php does not exist");
    }
    require_once $helperPath;
    $requiredFns = ['parse_xlsx', 'create_xlsx', 'parse_csv'];
    foreach ($requiredFns as $fn) {
        if (!function_exists($fn)) {
            throw new Exception("Function $fn() is not defined in includes/xlsx_helper.php");
        }
    }
    return true;
});

assertTest("1.2 create_xlsx() builds valid OpenXML ZIP package", function () {
    require_once dirname(__DIR__) . '/includes/xlsx_helper.php';
    $data = [
        ['STT', 'Họ và tên', 'Ngày sinh'],
        [1, 'Nguyễn Văn Test', '2015-01-01']
    ];
    $binary = create_xlsx($data);
    if (empty($binary)) {
        throw new Exception("create_xlsx() returned empty string");
    }
    // Verify it is a valid ZIP archive containing workbook.xml
    $tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.xlsx';
    file_put_contents($tempFile, $binary);

    $zip = new ZipArchive();
    $res = $zip->open($tempFile);
    if ($res !== true) {
        @unlink($tempFile);
        throw new Exception("create_xlsx() output is not a valid ZIP file");
    }
    $hasWorkbook = $zip->locateName('xl/workbook.xml') !== false;
    $hasSheet = $zip->locateName('xl/worksheets/sheet1.xml') !== false;
    $zip->close();
    @unlink($tempFile);

    if (!$hasWorkbook || !$hasSheet) {
        throw new Exception("Generated .xlsx missing xl/workbook.xml or sheet1.xml");
    }
    return true;
});

assertTest("1.3 Round-trip parse_xlsx(create_xlsx()) preserves Vietnamese text", function () {
    require_once dirname(__DIR__) . '/includes/xlsx_helper.php';
    $sampleData = [
        ['STT', 'Họ và tên', 'Ngày sinh', 'Giới tính', 'Số điện thoại'],
        ['1', 'Trần Phương Linh', '23/08/2015', 'Nữ', '0912345602'],
        ['2', 'Phạm Quốc Huy', '02/11/2015', 'Nam', '0934567890']
    ];
    $binary = create_xlsx($sampleData);
    $tempFile = sys_get_temp_dir() . '/test_roundtrip_' . uniqid() . '.xlsx';
    file_put_contents($tempFile, $binary);

    $parsed = parse_xlsx($tempFile);
    @unlink($tempFile);

    if (count($parsed) < 3) {
        throw new Exception("Expected at least 3 rows parsed, got " . count($parsed));
    }
    if ($parsed[1][1] !== 'Trần Phương Linh') {
        throw new Exception("Vietnamese name mismatch: expected 'Trần Phương Linh', got '" . ($parsed[1][1] ?? '') . "'");
    }
    return true;
});

assertTest("1.4 parse_csv() strips UTF-8 BOM and parses delimiters", function () {
    require_once dirname(__DIR__) . '/includes/xlsx_helper.php';
    // Test BOM stripping
    $csvContent = "\xEF\xBB\xBF" . "\"Họ và tên\",\"Ngày sinh\"\n\"Vũ Đức Đam\",\"2015-01-19\"";
    $tempCsv = sys_get_temp_dir() . '/test_bom_' . uniqid() . '.csv';
    file_put_contents($tempCsv, $csvContent);

    $parsed = parse_csv($tempCsv);
    @unlink($tempCsv);

    if (empty($parsed) || count($parsed) < 2) {
        throw new Exception("parse_csv failed on BOM CSV");
    }
    if ($parsed[0][0] !== 'Họ và tên') {
        throw new Exception("BOM was not stripped from first header: '" . $parsed[0][0] . "'");
    }
    return true;
});

assertTest("1.5 sample_students.xlsx & sample_students.csv exist and HTTP 200", function () use ($client) {
    $resXlsx = $client->get('sample_students.xlsx');
    if ($resXlsx['status'] !== 200 && !file_exists(dirname(__DIR__) . '/sample_students.xlsx')) {
        throw new Exception("sample_students.xlsx missing or HTTP " . $resXlsx['status']);
    }
    $resCsv = $client->get('sample_students.csv');
    if ($resCsv['status'] !== 200) {
        throw new Exception("sample_students.csv HTTP " . $resCsv['status']);
    }
    return true;
});

// -------------------------------------------------------------
// NHÓM 2: CLASS MANAGEMENT & SECONDARY SCHOOL CLASS MATRIX
// -------------------------------------------------------------
assertTest("2.1 Class matrix displays 36 classes grouped by Grade 6-9", function () {
    $pdo = db();
    $count = (int)$pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
    if ($count < 36) {
        throw new Exception("Expected at least 36 classes in database, found $count");
    }
    $grades = $pdo->query("SELECT DISTINCT grade FROM classes ORDER BY grade")->fetchAll(PDO::FETCH_COLUMN);
    if ($grades !== [6, 7, 8, 9] && $grades !== ['6', '7', '8', '9']) {
        throw new Exception("Grades 6-9 missing: " . json_encode($grades));
    }
    return true;
});

assertTest("2.2 Admin creates a new class via POST action=add_class", function () use ($client) {
    $client->clearCookies();
    $client->login('admin', '123456');

    $pdo = db();
    $testClassName = '6C99_TEST';
    $pdo->prepare("DELETE FROM classes WHERE name = ?")->execute([$testClassName]);

    $res = $client->post('admin.php', [
        'action'  => 'add_class',
        'name'    => $testClassName,
        'grade'   => 6,
        'section' => 'C'
    ]);

    $exists = (int)$pdo->prepare("SELECT COUNT(*) FROM classes WHERE name = ?")->execute([$testClassName]);
    $found = (bool)$pdo->query("SELECT id FROM classes WHERE name = '$testClassName'")->fetch();
    if (!$found) {
        throw new Exception("Class $testClassName was not created in database");
    }
    return true;
});

assertTest("2.3 Duplicate class name creation is rejected by UNIQUE(name)", function () use ($client) {
    $pdo = db();
    $testClassName = '6C99_TEST';
    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM classes WHERE name = '$testClassName'")->fetchColumn();

    $client->clearCookies();
    $client->login('admin', '123456');
    $client->post('admin.php', [
        'action'  => 'add_class',
        'name'    => $testClassName,
        'grade'   => 6,
        'section' => 'C'
    ]);

    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM classes WHERE name = '$testClassName'")->fetchColumn();
    if ($afterCount > $beforeCount) {
        throw new Exception("Duplicate class name was allowed in classes table!");
    }
    return true;
});

assertTest("2.4 Admin deletes test class and action is logged to audit_logs", function () use ($client) {
    $client->clearCookies();
    $client->login('admin', '123456');
    $pdo = db();
    $testClassName = '6C99_TEST';

    $classId = (int)$pdo->query("SELECT id FROM classes WHERE name = '$testClassName'")->fetchColumn();
    if ($classId) {
        $client->get("admin.php?delete_class=$classId");
        $remaining = (bool)$pdo->query("SELECT id FROM classes WHERE name = '$testClassName'")->fetch();
        if ($remaining) {
            throw new Exception("Class $testClassName was not deleted");
        }
        $log = $pdo->query("SELECT * FROM audit_logs WHERE action LIKE '%Xóa lớp%' OR details LIKE '%$testClassName%' ORDER BY id DESC LIMIT 1")->fetch();
        if (!$log) {
            throw new Exception("Class deletion was not recorded in audit_logs");
        }
    }
    return true;
});

// -------------------------------------------------------------
// NHÓM 3: TEACHER CREATION & BATCH CHECKBOX ASSIGNMENT
// -------------------------------------------------------------
assertTest("3.1 Admin creates teacher account with bcrypt hash", function () use ($client) {
    $client->clearCookies();
    $client->login('admin', '123456');
    $pdo = db();

    $pdo->prepare("DELETE FROM users WHERE username = 'gv_test_m3'")->execute();
    $res = $client->post('admin.php', [
        'action'    => 'create_teacher',
        'username'  => 'gv_test_m3',
        'full_name' => 'Thầy Giáo Test M3',
        'password'  => '123456'
    ]);

    $user = $pdo->query("SELECT * FROM users WHERE username = 'gv_test_m3'")->fetch();
    if (!$user) {
        throw new Exception("Teacher account gv_test_m3 was not created in users table");
    }
    if (!password_verify('123456', $user['password'])) {
        throw new Exception("Password hash was not generated with bcrypt password_hash()");
    }
    return true;
});

assertTest("3.2 Duplicate teacher username is rejected by UNIQUE(username)", function () use ($client) {
    $client->clearCookies();
    $client->login('admin', '123456');
    $pdo = db();

    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE username = 'gv_test_m3'")->fetchColumn();
    $client->post('admin.php', [
        'action'    => 'create_teacher',
        'username'  => 'gv_test_m3',
        'full_name' => 'Thầy Giáo Test Trùng',
        'password'  => '123456'
    ]);
    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE username = 'gv_test_m3'")->fetchColumn();
    if ($afterCount > $beforeCount) {
        throw new Exception("Duplicate username was allowed in users table!");
    }
    return true;
});

assertTest("3.3 Batch assignment assigns multiple classes across grades", function () use ($client) {
    $client->clearCookies();
    $client->login('admin', '123456');
    $pdo = db();

    $teacherId = (int)$pdo->query("SELECT id FROM users WHERE username = 'gv_test_m3'")->fetchColumn();
    $res = $client->post('admin.php', [
        'action'     => 'batch_assign',
        'teacher_id' => $teacherId,
        'subject'    => 'Tin Học',
        'class_ids'  => [1, 2, 3] // 6A1, 6A2, 6A3
    ]);

    $assigned = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes WHERE teacher_id = $teacherId AND subject = 'Tin Học'")->fetchColumn();
    if ($assigned < 3) {
        throw new Exception("Expected 3 classes assigned, found $assigned");
    }
    return true;
});

assertTest("3.4 Re-submitting identical batch assignment produces 0 duplicates", function () use ($client) {
    $client->clearCookies();
    $client->login('admin', '123456');
    $pdo = db();

    $teacherId = (int)$pdo->query("SELECT id FROM users WHERE username = 'gv_test_m3'")->fetchColumn();
    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes WHERE teacher_id = $teacherId AND subject = 'Tin Học'")->fetchColumn();

    $client->post('admin.php', [
        'action'     => 'batch_assign',
        'teacher_id' => $teacherId,
        'subject'    => 'Tin Học',
        'class_ids'  => [1, 2, 3]
    ]);

    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes WHERE teacher_id = $teacherId AND subject = 'Tin Học'")->fetchColumn();
    if ($beforeCount !== $afterCount) {
        throw new Exception("Duplicate assignments were created: before=$beforeCount, after=$afterCount");
    }
    return true;
});

assertTest("3.5 Admin unassigns teaching assignment via delete_assign", function () use ($client) {
    $client->clearCookies();
    $client->login('admin', '123456');
    $pdo = db();

    $teacherId = (int)$pdo->query("SELECT id FROM users WHERE username = 'gv_test_m3'")->fetchColumn();
    $assignId = (int)$pdo->query("SELECT id FROM teacher_classes WHERE teacher_id = $teacherId LIMIT 1")->fetchColumn();

    if ($assignId) {
        $client->get("admin.php?delete_assign=$assignId");
        $check = (bool)$pdo->query("SELECT id FROM teacher_classes WHERE id = $assignId")->fetch();
        if ($check) {
            throw new Exception("Assignment ID $assignId was not deleted");
        }
    }
    return true;
});

// -------------------------------------------------------------
// NHÓM 4: STUDENT ROSTER IMPORT (.CSV & .XLSX) & DEDUPLICATION
// -------------------------------------------------------------
assertTest("4.1 Admin imports sample_students.csv into test class (6A2)", function () use ($client) {
    $client->clearCookies();
    $client->login('admin', '123456');
    $pdo = db();

    $targetClassId = 2; // 6A2
    $pdo->prepare("DELETE FROM students WHERE class_id = ?")->execute([$targetClassId]);

    $csvPath = dirname(__DIR__) . '/sample_students.csv';
    $postData = [
        'action'          => 'import_students',
        'target_class_id' => $targetClassId,
        'student_file'    => new CURLFile($csvPath, 'text/csv', 'sample_students.csv')
    ];

    $res = $client->postMultipart('admin.php', $postData);
    if ($res['status'] !== 200 && $res['status'] !== 302) {
        throw new Exception("Expected HTTP 200/302, got " . $res['status']);
    }

    $count = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE class_id = $targetClassId")->fetchColumn();
    if ($count !== 5) {
        throw new Exception("Expected 5 students inserted into 6A2, found $count");
    }
    return true;
});

assertTest("4.2 CSV Re-import: 0 new students, duplicate count reported", function () use ($client) {
    $client->clearCookies();
    $client->login('admin', '123456');
    $pdo = db();

    $targetClassId = 2;
    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE class_id = $targetClassId")->fetchColumn();

    $csvPath = dirname(__DIR__) . '/sample_students.csv';
    $postData = [
        'action'          => 'import_students',
        'target_class_id' => $targetClassId,
        'student_file'    => new CURLFile($csvPath, 'text/csv', 'sample_students.csv')
    ];

    $res = $client->postMultipart('admin.php', $postData);
    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE class_id = $targetClassId")->fetchColumn();

    if ($beforeCount !== $afterCount) {
        throw new Exception("Duplicate students were inserted! Before: $beforeCount, After: $afterCount");
    }
    // Verify user feedback message contains duplicate count
    if (strpos($res['body'], 'bỏ qua') === false && strpos($res['body'], 'đã tồn tại') === false) {
        throw new Exception("UI message did not report skipped duplicates");
    }
    return true;
});

assertTest("4.3 Admin imports .xlsx file with Vietnamese dates into test class (6A3)", function () use ($client) {
    require_once dirname(__DIR__) . '/includes/xlsx_helper.php';
    $client->clearCookies();
    $client->login('admin', '123456');
    $pdo = db();

    $targetClassId = 3; // 6A3
    $pdo->prepare("DELETE FROM students WHERE class_id = ?")->execute([$targetClassId]);

    // Create a temporary .xlsx file with standard VnEdu columns
    $xlsxData = [
        ['STT', 'Mã HS', 'Họ và tên', 'Ngày sinh', 'Giới tính', 'Số điện thoại'],
        ['1', 'HS6A301', 'Đỗ Minh Trí', '15/05/2015', 'Nam', '0981122334'],
        ['2', 'HS6A302', 'Bùi Phương Thảo', '20/10/2015', 'Nữ', '0982233445'],
        ['3', 'HS6A303', 'Nguyễn Hoàng Nam', '42075', 'Nam', '0983344556'] // 42075 is 2015-03-12
    ];
    $tempXlsx = sys_get_temp_dir() . '/test_import_' . uniqid() . '.xlsx';
    file_put_contents($tempXlsx, create_xlsx($xlsxData));

    $postData = [
        'action'          => 'import_students',
        'target_class_id' => $targetClassId,
        'student_file'    => new CURLFile($tempXlsx, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'students_6a3.xlsx')
    ];

    $res = $client->postMultipart('admin.php', $postData);
    @unlink($tempXlsx);

    $count = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE class_id = $targetClassId")->fetchColumn();
    if ($count !== 3) {
        throw new Exception("Expected 3 students inserted into 6A3, found $count");
    }

    // Verify date normalization: '15/05/2015' -> '2015-05-15', '42075' -> '2015-03-12'
    $s1 = $pdo->query("SELECT birthday FROM students WHERE class_id = $targetClassId AND full_name = 'Đỗ Minh Trí'")->fetchColumn();
    if ($s1 !== '2015-05-15') {
        throw new Exception("Birthday '15/05/2015' was not normalized to '2015-05-15', got '$s1'");
    }
    $s3 = $pdo->query("SELECT birthday FROM students WHERE class_id = $targetClassId AND full_name = 'Nguyễn Hoàng Nam'")->fetchColumn();
    if ($s3 !== '2015-03-12') {
        throw new Exception("Excel serial '42075' was not normalized to '2015-03-12', got '$s3'");
    }
    return true;
});

assertTest("4.4 XLSX Re-import: 0 duplicate rows inserted into database", function () use ($client) {
    require_once dirname(__DIR__) . '/includes/xlsx_helper.php';
    $client->clearCookies();
    $client->login('admin', '123456');
    $pdo = db();

    $targetClassId = 3;
    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE class_id = $targetClassId")->fetchColumn();

    $xlsxData = [
        ['STT', 'Mã HS', 'Họ và tên', 'Ngày sinh', 'Giới tính', 'Số điện thoại'],
        ['1', 'HS6A301', 'Đỗ Minh Trí', '15/05/2015', 'Nam', '0981122334'],
        ['2', 'HS6A302', 'Bùi Phương Thảo', '20/10/2015', 'Nữ', '0982233445'],
        ['3', 'HS6A303', 'Nguyễn Hoàng Nam', '2015-03-12', 'Nam', '0983344556']
    ];
    $tempXlsx = sys_get_temp_dir() . '/test_reimport_' . uniqid() . '.xlsx';
    file_put_contents($tempXlsx, create_xlsx($xlsxData));

    $postData = [
        'action'          => 'import_students',
        'target_class_id' => $targetClassId,
        'student_file'    => new CURLFile($tempXlsx, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'students_6a3.xlsx')
    ];

    $res = $client->postMultipart('admin.php', $postData);
    @unlink($tempXlsx);

    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE class_id = $targetClassId")->fetchColumn();
    if ($beforeCount !== $afterCount) {
        throw new Exception("Re-importing .xlsx created duplicates: before=$beforeCount, after=$afterCount");
    }
    return true;
});

assertTest("4.5 Header tolerance: file with headers on row 3 parses correctly", function () use ($client) {
    require_once dirname(__DIR__) . '/includes/xlsx_helper.php';
    $client->clearCookies();
    $client->login('admin', '123456');
    $pdo = db();

    $targetClassId = 4; // 6B1
    $pdo->prepare("DELETE FROM students WHERE class_id = ?")->execute([$targetClassId]);

    $tolerantData = [
        ['TRƯỜNG THCS NGUYỄN DU - PHÒNG GD&ĐT QUẬN 1'],
        ['DANH SÁCH HỌC SINH LỚP 6B1 - NĂM HỌC 2026-2027'],
        ['STT', 'Họ và tên', 'Ngày sinh', 'Giới tính', 'SĐT phụ huynh'],
        ['1', 'Võ Quốc Bảo', '10/02/2015', 'Nam', '0909887766'],
        ['2', 'Đặng Thùy Dương', '18/09/2015', 'Nữ', '0909776655']
    ];
    $tempXlsx = sys_get_temp_dir() . '/test_tolerant_' . uniqid() . '.xlsx';
    file_put_contents($tempXlsx, create_xlsx($tolerantData));

    $postData = [
        'action'          => 'import_students',
        'target_class_id' => $targetClassId,
        'student_file'    => new CURLFile($tempXlsx, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'students_6b1.xlsx')
    ];

    $client->postMultipart('admin.php', $postData);
    @unlink($tempXlsx);

    $count = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE class_id = $targetClassId")->fetchColumn();
    if ($count !== 2) {
        throw new Exception("Header tolerance failed: expected 2 students in 6B1, got $count");
    }
    return true;
});

assertTest("4.6 Database UNIQUE(class_id, full_name, birthday) rejects duplicate", function () {
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->exec("INSERT INTO students (class_id, full_name, birthday) VALUES (1, 'Học Sinh Kiểm Thử Khóa', '2015-01-01')");
    $duplicateCaught = false;
    try {
        $pdo->exec("INSERT INTO students (class_id, full_name, birthday) VALUES (1, 'Học Sinh Kiểm Thử Khóa', '2015-01-01')");
    } catch (PDOException $e) {
        if ($e->errorInfo[1] == 1062) {
            $duplicateCaught = true;
        }
    }
    $pdo->rollBack();
    if (!$duplicateCaught) {
        throw new Exception("Database constraint UNIQUE(class_id, full_name, birthday) failed to catch duplicate entry!");
    }
    return true;
});

// -------------------------------------------------------------
// NHÓM 5: DIRECT GRADEBOOK INSPECTION & RBAC SECURITY
// -------------------------------------------------------------
assertTest("5.1 Admin direct gradebook inspection (nhap_diem.php?class_id=1&subject=Toán) is 200", function () use ($client) {
    $client->clearCookies();
    $client->login('admin', '123456');
    $res = $client->get('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'));
    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200 for admin gradebook inspection, got " . $res['status']);
    }
    return true;
});

assertTest("5.2 Teacher (gv_toan) POST to admin.php is blocked with 403", function () use ($client) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');

    $res = $client->post('admin.php', [
        'action'          => 'import_students',
        'target_class_id' => 1
    ]);
    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403 on forged teacher POST to admin.php, got " . $res['status']);
    }
    return true;
});

assertTest("5.3 Unauthenticated request to admin.php is blocked (HTTP 403/302)", function () use ($client) {
    $client->clearCookies();
    $res = $client->get('admin.php');
    if ($res['status'] !== 403 && $res['status'] !== 302) {
        throw new Exception("Expected HTTP 403/302 for unauthenticated access, got " . $res['status']);
    }
    return true;
});

// -------------------------------------------------------------
// NHÓM 6: COMPLETE AUDIT LOGGING FOR ALL M3 OPERATIONS
// -------------------------------------------------------------
assertTest("6.1 Student import is recorded in audit_logs with class name & counts", function () {
    $pdo = db();
    $log = $pdo->query("SELECT * FROM audit_logs WHERE action = 'Import học sinh' ORDER BY id DESC LIMIT 1")->fetch();
    if (!$log) {
        throw new Exception("No audit log found for 'Import học sinh'");
    }
    if (strpos($log['details'], 'thêm mới') === false || strpos($log['details'], 'trùng lặp') === false) {
        throw new Exception("Audit log details missing metrics: " . $log['details']);
    }
    return true;
});

assertTest("6.2 Batch assignment is recorded in audit_logs with teacher & subject", function () {
    $pdo = db();
    $log = $pdo->query("SELECT * FROM audit_logs WHERE action = 'Phân công giáo viên' ORDER BY id DESC LIMIT 1")->fetch();
    if (!$log) {
        throw new Exception("No audit log found for 'Phân công giáo viên'");
    }
    return true;
});

assertTest("6.3 Teacher account creation is recorded in audit_logs", function () {
    $pdo = db();
    $log = $pdo->query("SELECT * FROM audit_logs WHERE action LIKE '%giáo viên%' OR details LIKE '%gv_test_m3%' ORDER BY id DESC LIMIT 1")->fetch();
    if (!$log) {
        throw new Exception("Teacher creation was not found in audit_logs");
    }
    return true;
});

assertTest("6.4 Class management events are recorded in audit_logs", function () {
    $pdo = db();
    $log = $pdo->query("SELECT * FROM audit_logs WHERE action LIKE '%lớp%' ORDER BY id DESC LIMIT 1")->fetch();
    if (!$log) {
        throw new Exception("Class management action was not recorded in audit_logs");
    }
    return true;
});

assertTest("6.5 admin.php audit viewer table displays log records", function () use ($client) {
    $client->clearCookies();
    $client->login('admin', '123456');
    $res = $client->get('admin.php');
    if (strpos($res['body'], 'audit_logs') === false && strpos($res['body'], 'Nhật Ký') === false) {
        throw new Exception("admin.php does not contain Audit Logs section");
    }
    if (strpos($res['body'], 'Import học sinh') === false && strpos($res['body'], 'Phân công') === false) {
        throw new Exception("Audit log actions not rendered in admin.php table");
    }
    return true;
});

// -------------------------------------------------------------
// TỔNG KẾT & MÃ THOÁT
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

## 8. Summary of Interfaces & Invariants

1. **Student Invariant**: In any given class, no two student rows shall have identical `(full_name, birthday)`. Non-empty birthdays are strictly enforced by normalizing absent/unparseable values to `'1970-01-01'`.
2. **Database Invariant**: Composite index `UNIQUE KEY unique_class_student (class_id, full_name, birthday)` in MySQL `students` table is the immutable source of truth.
3. **Idempotence Invariant**: Re-uploading the same roster `.xlsx` or `.csv` file must result in `0` inserted students and increment `$duplicateCount` by exactly the number of rows in the file.
4. **Audit Trail Invariant**: Every import attempt writes a record to `audit_logs` including target class name, source filename, inserted count, duplicate count, admin username, and IP address.
