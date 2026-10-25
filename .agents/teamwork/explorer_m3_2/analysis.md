# Báo Cáo Phân Tích & Thiết Kế Đại Tu `admin.php` (Milestone 3)

**Tác giả:** explorer_m3_2  
**Đối tượng:** Quản lý Lớp học THCS (Khối 6–9), Phân công Hàng loạt bằng Checkbox, Tạo Tài khoản Giáo viên, Giám sát Sổ điểm Trực tiếp, Nhật ký Kiểm toán Thời gian thực.  
**Ngày thực hiện:** 2026-10-17  
**Dự án:** EduRadar THCS Gradebook & Early Warning (`D:\New folder\my-web-project`)  

---

## 1. TỔNG QUAN & PHẠM VI NHIỆM VỤ

Trang Quản trị `admin.php` là trung tâm điều hành toàn diện của trường THCS dành riêng cho Quản trị viên (Hiệu trưởng / Phó Hiệu trưởng / Giáo vụ). Theo yêu cầu từ `ORIGINAL_REQUEST.md` (R1, R2, R5, R6) và `PROJECT.md` (Features #7, #8, #10, #11), `admin.php` phải đáp ứng trọn vẹn 6 trụ cột nghiệp vụ:

1. **Ma Trận Lớp Học Toàn Trường (Class Matrix)**:
   - Bao quát toàn bộ 4 khối lớp THCS: Khối 6, Khối 7, Khối 8, Khối 9.
   - Phân ban / Tổ lớp chuẩn THCS: Ban A, B, C và số thứ tự lớp từ 1 đến 9 (ví dụ: `6A1`..`6A3`, `7A1`..`7B2`, `8A1`..`8C3`, `9A1`..`9C3`, mở rộng linh hoạt đến `9C9`).
   - Hiển thị trực quan theo từng khối với huy hiệu sĩ số (student count badge) của từng lớp.
   - Hỗ trợ tạo mới lớp học (`create_class`) và xóa lớp học (`delete_class`) an toàn, chống trùng lặp tên lớp qua ràng buộc cơ sở dữ liệu `UNIQUE(name)`.

2. **Phân Công Giảng Dạy Hàng Loạt (Batch Checkbox Assignment)**:
   - Giao diện lưới chọn lớp trực quan theo từng khối.
   - Nút bấm điều khiển tiện ích theo từng khối: **"Chọn tất cả Khối 6"**, **"Khối 7"**, **"Khối 8"**, **"Khối 9"** và **"Bỏ chọn"** (per-grade select-all & deselect toggle).
   - Nút chọn toàn trường: **"Chọn tất cả các khối"** và **"Bỏ chọn toàn bộ"**.
   - Bộ chọn Giáo viên (tải từ `users` với `role = 'teacher'`).
   - Danh mục môn học chuẩn chương trình GDPT THCS (Toán, Ngữ Văn, Tiếng Anh, Khoa Học Tự Nhiên, Lịch Sử & Địa Lí, Tin Học, GDCD, Công Nghệ, GDTC, Nghệ Thuật, HĐTN).
   - Nạp hàng loạt vào bảng `teacher_classes` có chống trùng lặp tuyệt đối qua `UNIQUE(teacher_id, class_id, subject)` và kiểm tra trước khi insert. Báo cáo rõ số lớp đã phân công và số lớp đã bỏ qua do trùng.

3. **Danh Sách Phân Công & Hủy Quyền (Assignment Management)**:
   - Bảng tổng hợp các phân công hiện hành trong toàn trường với bộ lọc / tìm kiếm theo Giáo viên, Lớp, Môn học.
   - Thao tác Hủy phân công (`delete_assign`) an toàn, tương thích với cả `$_GET['delete_assign']` (theo bài test RBAC hiện có) và `$_POST['action'] === 'delete_assign'`.

4. **Tạo Mới Tài Khoản Giáo Viên (Teacher Account Creation)**:
   - Biểu mẫu nạp giáo viên mới trực tiếp trên giao diện Admin (Tên đăng nhập / VnEdu username, Họ và tên, Mật khẩu khởi tạo).
   - Băm mật khẩu chuẩn bảo mật `password_hash($password, PASSWORD_BCRYPT)`.
   - Chặn trùng lặp tên đăng nhập qua `UNIQUE(username)`.

5. **Nút Giám Sát Sổ Điểm Trực Tiếp (Direct Gradebook Inspection)**:
   - Trên mỗi dòng phân công, Quản trị viên có nút hành động: **"🔍 Mở Bảng Điểm Môn [X]"** liên kết trực tiếp tới `admin.php?impersonate=...` hoặc `nhap_diem.php?teacher_id=...&class_id=...&subject=...`.
   - Cơ chế ghi nhật ký kiểm toán hành động giám sát sổ điểm vào `audit_logs` trước khi chuyển hướng.
   - Quản trị viên có thanh thông báo "Chế độ Admin" và có toàn quyền kiểm tra, chỉnh sửa hoặc quay lại trang Quản trị.

6. **Trình Xem Nhật Ký Kiểm Toán Thời Gian Thực (Real-Time Audit Log Viewer)**:
   - Bảng hiển thị các hoạt động mới nhất được ghi nhận từ bảng `audit_logs`.
   - Phân loại trực quan bằng huy hiệu màu sắc (Đăng nhập, Phân công, Lưu điểm, Import học sinh, Xóa, Bị chặn).
   - Tùy chọn số lượng bản ghi hiển thị (10, 25, 50, 100 dòng), lọc theo loại hành động và tìm kiếm thời gian thực (live search).
   - Nút làm mới tức thì (instant reload).

---

## 2. HIỆN TRẠNG & ĐÁNH GIÁ KHOẢNG CÁCH (GAP ANALYSIS)

Qua khảo sát tệp `admin.php` hiện hữu (428 dòng) và đối chiếu với yêu cầu dự án:

| Hạng mục | Hiện trạng `admin.php` | Yêu cầu chuẩn | Khoảng cách cần khắc phục |
|---|---|---|---|
| **Bảo vệ tuyến Admin** | Đã có `require_admin()` ở đầu trang và trong các block POST/GET. | Chặn 100% tài khoản Teacher và Khách, trả về HTTP 403, ghi `audit_logs`. | **Đạt** (Đã kiểm chứng đạt 37/37 bài test của `challenge_m2_rbac.php`). Phải giữ nguyên cấu trúc này. |
| **Quản lý Lớp học** | Không có chức năng tạo lớp (`create_class`) hay xóa lớp (`delete_class`). Lớp chỉ được nạp sẵn qua seed. | Admin có thể tạo lớp mới (chọn Khối 6-9, Ban A-C, Lớp 1-9), xóa lớp không dùng; có chống trùng `UNIQUE(name)`. | **Thiếu hoàn toàn**. Cần bổ sung biểu mẫu tạo lớp, xử lý xóa lớp và ghi nhật ký kiểm toán. |
| **Hiển thị Ma trận Lớp** | Gom nhóm theo `grade` nhưng dạng chip checkbox đơn giản; không có sĩ số học sinh trên từng ô lớp. | Hiển thị ma trận phân theo khối, từng lớp có huy hiệu sĩ số (ví dụ: `5 HS`). | **Chưa đạt**. Cần bổ sung đếm sĩ số `COUNT(students)` trên từng chip lớp học. |
| **Chọn Checkbox Khối** | Chỉ có 1 checkbox `toggleGrade(this, 'g6')` đơn sơ trong tiêu đề khối. | Cần các nút chuyên dụng: "Chọn tất cả Khối X", "Bỏ chọn Khối X", cùng nút chọn/bỏ chọn toàn trường. | **Chưa đạt**. Cần bổ sung bộ nút điều khiển JavaScript riêng biệt, thao tác mượt mà. |
| **Danh mục Môn học** | Mới có 6 môn cứng (Toán, Ngữ Văn, Tiếng Anh, KHTN, Lịch Sử, Tin Học). | Đầy đủ các môn THCS theo Chương trình GDPT 2018 (thêm GDCD, Công Nghệ, GDTC, Nghệ Thuật, HĐTN). | **Chưa đủ**. Cần mở rộng danh sách môn học chuẩn THCS. |
| **Tạo Tài khoản Giáo viên** | Hoàn toàn không có form tạo tài khoản giáo viên trong `admin.php`. | Cho phép Admin tạo giáo viên mới (username, full_name, password bcrypt), chống trùng `username`. | **Thiếu hoàn toàn**. Cần bổ sung biểu mẫu và action `create_teacher`. |
| **Bảng Phân công** | Có bảng phân công, có nút impersonate và delete. Chưa có bộ lọc/tìm kiếm. | Bảng phân công hiển thị rõ Khối, Lớp, Sĩ số, Môn, Giáo viên, Tài khoản, nút Mở sổ điểm, nút Hủy; có bộ lọc tìm kiếm. | **Cần nâng cấp**. Bổ sung tìm kiếm nhanh bằng JS, định dạng chuẩn Stitch UI. |
| **Giám sát Sổ điểm** | Đã có `admin.php?impersonate=...` chuyển hướng sang `nhap_diem.php`. | Hoạt động trơn tru, ghi audit log rõ ràng. | **Tốt**. Cần chuẩn hóa thông điệp nhật ký kiểm toán và thêm bộ chọn nhanh "Xem sổ điểm bất kỳ lớp nào". |
| **Audit Log Viewer** | Chỉ lấy cứng `LIMIT 10`, không lọc, không phân trang, không tìm kiếm. | Hiển thị chuyên nghiệp, chọn số dòng (10, 25, 50, 100), gắn nhãn màu sắc hành động, tìm kiếm nhanh. | **Cần nâng cấp mạnh**. Tái thiết kế giao diện theo phong cách Stitch UI / Linear. |
| **Thẻ Thống kê (Stats)** | Chưa có thẻ thống kê tổng quan trường học. | Hiển thị 4 thẻ: Tổng số lớp, Tổng số giáo viên, Tổng số phân công, Tổng số học sinh. | **Thiếu**. Cần bổ sung 4 thẻ chỉ số ở đầu trang giúp Admin có cái nhìn tổng quan ngay khi truy cập. |

---

## 3. THIẾT KẾ KIẾN TRÚC DỮ LIỆU & TRUY VẤN (SQL OPTIMIZATION)

### 3.1. Ràng buộc dữ liệu & Toàn vẹn (Data Integrity)
- Bảng `classes`:
  - Ràng buộc: `UNIQUE KEY unique_class_name (name)`.
  - Khóa ngoại: `students` và `teacher_classes` đều trỏ về `classes.id` với `ON DELETE CASCADE`.
- Bảng `teacher_classes`:
  - Ràng buộc: `UNIQUE KEY unique_assignment (teacher_id, class_id, subject)`.
  - Ngăn chặn hoàn toàn việc phân công trùng lặp 1 giáo viên dạy cùng 1 môn tại 1 lớp.
- Bảng `users`:
  - Ràng buộc: `UNIQUE KEY unique_username (username)`.
  - Phân quyền: `role ENUM('admin', 'teacher')`.
- Bảng `audit_logs`:
  - Ghi nhận: `user_id, username, action, details, ip_address, created_at`.

### 3.2. Truy vấn tối ưu cho Giao diện Admin

#### A. Truy vấn Ma trận Lớp học kèm Sĩ số:
```sql
SELECT c.id, c.name, c.grade, c.section,
       (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id) AS student_count
FROM classes c
ORDER BY c.grade ASC, c.section ASC, c.name ASC
```
*Hiệu năng:* Gom tất cả 36–50 lớp trong 1 truy vấn duy nhất kèm theo số lượng học sinh thực tế, tránh N+1 query.

#### B. Truy vấn Danh sách Giáo viên:
```sql
SELECT id, username, full_name, created_at,
       (SELECT COUNT(*) FROM teacher_classes tc WHERE tc.teacher_id = users.id) AS assignment_count
FROM users 
WHERE role = 'teacher' 
ORDER BY full_name ASC
```

#### C. Truy vấn Bảng Phân công Giảng dạy Toàn trường:
```sql
SELECT tc.id, tc.teacher_id, tc.class_id, tc.subject, tc.created_at,
       u.full_name AS teacher_name, u.username,
       c.name AS class_name, c.grade, c.section,
       (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id) AS student_count
FROM teacher_classes tc
JOIN users u ON tc.teacher_id = u.id
JOIN classes c ON tc.class_id = c.id
ORDER BY c.grade ASC, c.name ASC, tc.subject ASC
```

#### D. Truy vấn Thống kê Tổng quan (Stats Counters):
```sql
SELECT 
    (SELECT COUNT(*) FROM classes) AS total_classes,
    (SELECT COUNT(*) FROM users WHERE role = 'teacher') AS total_teachers,
    (SELECT COUNT(*) FROM teacher_classes) AS total_assignments,
    (SELECT COUNT(*) FROM students) AS total_students
```

#### E. Truy vấn Nhật ký Kiểm toán (Audit Logs):
```sql
SELECT id, user_id, username, action, details, ip_address, created_at
FROM audit_logs
ORDER BY id DESC
LIMIT :limit
```

---

## 4. THIẾT KẾ CHI TIẾT CÁC MODULE NGHIỆP VỤ

### 4.1. Module 1: Thẻ Thống Kê Tổng Quan (Dashboard Stats)
Ngay dưới thanh tiêu đề `topbar`, hiển thị 4 thẻ thống kê theo ngôn ngữ thiết kế Stitch UI:
1. **Lớp Học Toàn Trường**: Tổng số lớp (ví dụ: `36 Lớp`), ghi chú "Khối 6, 7, 8, 9".
2. **Đội Ngũ Giáo Viên**: Tổng số tài khoản giáo viên (ví dụ: `2 Giáo viên`), ghi chú "Đang công tác".
3. **Phân Công Giảng Dạy**: Tổng số tiết/môn được phân công (ví dụ: `3 Lượt phân công`).
4. **Học Sinh Toàn Trường**: Tổng số học sinh trong CSDL (ví dụ: `5 Học sinh`), ghi chú "Đã định danh".

### 4.2. Module 2: Quản Lý & Ma Trận Lớp Học (Class Matrix & Management)
Gồm 2 thành phần chính:

#### A. Biểu Mẫu Tạo Lớp Mới (`create_class`):
- Giao diện nén gọn gàng:
  - Chọn Khối: `Khối 6`, `Khối 7`, `Khối 8`, `Khối 9`
  - Chọn Ban/Tổ: `A`, `B`, `C`
  - Số lớp: `1`, `2`, `3`, `4`, `5`, `6`, `7`, `8`, `9` (hoặc nhập tên tùy chỉnh)
  - Tự động gợi ý tên lớp dạng chuẩn (ví dụ: chọn Khối 6, Ban A, Số 4 -> Tạo `6A4`).
- Xử lý máy chủ:
  - Kiểm tra `require_admin()`.
  - Chuẩn hóa tên lớp (chữ hoa, không khoảng trắng, regex: `/^[6-9][A-Z][0-9]{1,2}$/`).
  - Kiểm tra `SELECT id FROM classes WHERE name = ?`. Nếu tồn tại: báo lỗi thân thiện "Lớp [$name] đã tồn tại trong hệ thống!".
  - Thực hiện `INSERT INTO classes (name, grade, section) VALUES (?, ?, ?)`.
  - Ghi nhật ký: `log_action((int)$_SESSION['user_id'], $_SESSION['username'], 'Tạo lớp học', "Admin đã tạo mới lớp học [$name] (Khối $grade, Ban $section).");`
  - Báo thành công và tự động hiển thị trong lưới lớp học.

#### B. Biểu Mẫu Xóa Lớp (`delete_class`):
- Tương thích cả yêu cầu GET (`admin.php?delete_class=ID`) và POST (`action=delete_class`).
- Kiểm tra xem lớp có học sinh hay không:
  - Nếu có học sinh: Đưa ra thông báo xác nhận rõ ràng trước khi xóa (vì xóa lớp sẽ kích hoạt cascade xóa học sinh và điểm số liên quan).
- Thực hiện: `DELETE FROM classes WHERE id = ?`.
- Ghi nhật ký: `log_action((int)$_SESSION['user_id'], $_SESSION['username'], 'Xóa lớp học', "Admin đã xóa lớp học [$className] (Khối $grade).");`

#### C. Lưới Ma Trận Lớp Học Theo Khối:
- Hiển thị 4 thẻ khối: Khối 6, Khối 7, Khối 8, Khối 9.
- Mỗi khối có tiêu đề và huy hiệu số lượng lớp, số lượng học sinh.
- Mỗi lớp học là một thẻ nhỏ hiển thị:
  - Tên lớp (ví dụ: `6A1`, `6B2`)
  - Huy hiệu sĩ số (ví dụ: `<span class="badge blue">5 HS</span>` hoặc `<span class="badge gray">0 HS</span>`)
  - Nút xóa nhanh (biểu tượng ✕ màu đỏ với xác nhận JavaScript `confirm()`).

---

### 4.3. Module 3: Phân Công Hàng Loạt Bằng Checkbox (Batch Checkbox Assignment)

#### A. Thiết kế Giao diện:
- **Hàng điều khiển trên cùng**:
  1. **Chọn Giáo Viên**: Thẻ `<select name="teacher_id" required>` chứa danh sách giáo viên kèm tên tài khoản.
  2. **Chọn Môn Học**: Thẻ `<select name="subject" required>` với danh mục chuẩn 11 môn THCS:
     - Toán
     - Ngữ Văn
     - Tiếng Anh
     - Khoa Học Tự Nhiên (KHTN)
     - Lịch Sử & Địa Lí
     - Tin Học
     - Giáo Dục Công Dân (GDCD)
     - Công Nghệ
     - Giáo Dục Thể Chất
     - Nghệ Thuật (Âm Nhạc / Mỹ Thuật)
     - Hoạt Động Trải Nghiệm & Hướng Nghiệp
- **Thanh Công Cụ Toàn Cục (Global Selection Bar)**:
  - Nút `[✓ Chọn tất cả các khối]` (Check All Global).
  - Nút `[✕ Bỏ chọn toàn bộ]` (Uncheck All Global).
  - Bộ đếm thời gian thực: `<span id="selectedClassCount">Đã chọn: 0 lớp</span>`.
- **Ma Trận Checkbox Phân Theo Từng Khối**:
  - Với mỗi Khối (6, 7, 8, 9):
    - Tiêu đề khối: `🎓 Khối 6 (9 lớp)`.
    - Cụm nút bấm tiện ích:
      - `[✓ Chọn tất cả Khối 6]` -> gọi hàm JS `selectGrade(6, true)`
      - `[✕ Bỏ chọn Khối 6]` -> gọi hàm JS `selectGrade(6, false)`
    - Lưới checkbox (CSS Grid responsive):
      ```html
      <label class="class-chip-box">
          <input type="checkbox" name="class_ids[]" value="<?= $c['id'] ?>" class="cb-class cb-grade-<?= $c['grade'] ?>" onchange="updateSelectedCount()">
          <span class="chip-name"><?= htmlspecialchars($c['name']) ?></span>
          <span class="chip-count"><?= $c['student_count'] ?> HS</span>
      </label>
      ```
- **Nút Thực Thi Phân Công**:
  - Nút bấm nổi bật: `⚡ Cấp Quyền & Phân Công Cho Giáo Viên Đã Chọn`.

#### B. Quy trình Xử lý Máy chủ (`action = 'batch_assign'`):
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'batch_assign') {
    require_admin();
    $teacher_id = (int)($_POST['teacher_id'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $selected_classes = $_POST['class_ids'] ?? [];

    if ($teacher_id > 0 && $subject !== '' && !empty($selected_classes)) {
        // 1. Xác thực giáo viên hợp lệ
        $tStmt = $pdo->prepare("SELECT full_name, username FROM users WHERE id = ? AND role = 'teacher'");
        $tStmt->execute([$teacher_id]);
        $teacher = $tStmt->fetch();

        if (!$teacher) {
            $err = "Giáo viên được chọn không hợp lệ trong hệ thống!";
        } else {
            $pdo->beginTransaction();
            $checkStmt = $pdo->prepare("SELECT id FROM teacher_classes WHERE teacher_id = ? AND class_id = ? AND subject = ?");
            $insStmt = $pdo->prepare("INSERT INTO teacher_classes (teacher_id, class_id, subject) VALUES (?, ?, ?)");
            $classStmt = $pdo->prepare("SELECT name FROM classes WHERE id = ?");

            $assignedCount = 0;
            $skippedCount = 0;
            $assignedNames = [];

            foreach ($selected_classes as $cid) {
                $cid = (int)$cid;
                if ($cid <= 0) continue;

                $checkStmt->execute([$teacher_id, $cid, $subject]);
                if ($checkStmt->fetch()) {
                    $skippedCount++;
                } else {
                    $insStmt->execute([$teacher_id, $cid, $subject]);
                    $assignedCount++;
                    $classStmt->execute([$cid]);
                    $cName = $classStmt->fetchColumn();
                    if ($cName) $assignedNames[] = $cName;
                }
            }
            $pdo->commit();

            // 2. Ghi nhật ký kiểm toán
            $classesStr = !empty($assignedNames) ? implode(', ', $assignedNames) : 'Không có';
            $details = "Admin phân công cho [{$teacher['full_name']}] ({$teacher['username']}) dạy môn [$subject] ở các lớp: [$classesStr] (thành công $assignedCount lớp, bỏ qua $skippedCount lớp đã có sẵn).";
            log_action((int)$_SESSION['user_id'], $_SESSION['username'] ?? 'admin', 'Phân công giáo viên', $details);

            $msg = "Đã phân công thành công $assignedCount lớp cho giáo viên {$teacher['full_name']} (Môn $subject)!";
            if ($skippedCount > 0) {
                $msg .= " (Đã bỏ qua $skippedCount lớp đã được phân công trước đó).";
            }
        }
    } else {
        $err = "Vui lòng chọn giáo viên, môn học và ít nhất một lớp cần phân công!";
    }
}
```

---

### 4.4. Module 4: Quản Lý & Tạo Mới Tài Khoản Giáo Viên (Teacher Account Creation)

#### A. Biểu Mẫu Tạo Tài Khoản (`create_teacher`):
- Form đặt tại khu vực riêng hoặc cạnh bảng phân công:
  - **Tên đăng nhập (VnEdu username)**: `<input type="text" name="username" placeholder="Ví dụ: gv_toan2, nguyenvana" required>`
  - **Họ và tên đầy đủ**: `<input type="text" name="full_name" placeholder="Ví dụ: Thầy Trần Quang Khải" required>`
  - **Mật khẩu khởi tạo**: `<input type="password" name="password" value="123456" required>` kèm chú thích "Mặc định: 123456"
  - Nút: `[➕ Tạo Tài Khoản Giáo Viên]`

#### B. Quy trình Xử lý Máy chủ (`action = 'create_teacher'`):
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_teacher') {
    require_admin();
    $username = trim($_POST['username'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';

    // 1. Kiểm tra tính hợp lệ
    if ($username === '' || $fullName === '' || $password === '') {
        $err = "Vui lòng điền đầy đủ Tên đăng nhập, Họ tên và Mật khẩu!";
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        $err = "Tên đăng nhập chỉ bao gồm chữ cái, số, dấu gạch dưới và từ 3 đến 30 ký tự!";
    } elseif (strlen($password) < 6) {
        $err = "Mật khẩu khởi tạo phải có độ dài tối thiểu 6 ký tự!";
    } else {
        // 2. Chống trùng lặp username
        $chk = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $chk->execute([$username]);
        if ($chk->fetch()) {
            $err = "Tên đăng nhập [$username] đã được sử dụng! Vui lòng chọn tên đăng nhập khác.";
        } else {
            // 3. Băm mật khẩu bcrypt
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $ins = $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, 'teacher')");
            $ins->execute([$username, $hashedPassword, $fullName]);

            // 4. Ghi nhật ký kiểm toán
            log_action((int)$_SESSION['user_id'], $_SESSION['username'] ?? 'admin', 'Tạo tài khoản giáo viên', "Admin tạo mới tài khoản giáo viên: [$username] - [$fullName].");
            $msg = "Đã tạo thành công tài khoản giáo viên [$fullName] (Tên đăng nhập: $username)!";
        }
    }
}
```

---

### 4.5. Module 5: Bảng Phân Công & Nút Giám Sát Sổ Điểm Trực Tiếp (Inspection & Unassign)

#### A. Cột Bảng & Tính Năng:
- Các cột:
  1. `Lớp Học`: Tên lớp (ví dụ: `6A1`), huy hiệu Khối.
  2. `Sĩ Số`: Số lượng học sinh hiện tại của lớp.
  3. `Môn Học`: Tên môn được phân công.
  4. `Giáo Viên Phụ Trách`: Họ tên giáo viên.
  5. `Tài Khoản`: Username giáo viên (font JetBrains Mono).
  6. `Giám Sát Sổ Điểm`: Nút nổi bật:
     `<a href="admin.php?impersonate=...&sub=...&cid=..." class="btn primary btn-sm">🔍 Mở Sổ Điểm</a>`
  7. `Hủy Quyền`: Nút icon xóa:
     `<a href="admin.php?delete_assign=..." class="btn outline btn-sm text-danger" onclick="return confirm('Hủy quyền giáo viên này ở lớp này?')">✕ Hủy</a>`

#### B. Tìm kiếm & Lọc Động (Vanilla JS):
- Hộp tìm kiếm theo thời gian thực: lọc đồng thời theo tên giáo viên, tên lớp, môn học hoặc username mà không cần tải lại trang.

#### C. Quy trình Xử lý Hủy Phân Công (`delete_assign`):
- Hỗ trợ cả `$_GET['delete_assign']` và `$_POST['action'] === 'delete_assign'` (đảm bảo 100% không làm hỏng bài test bảo mật `challenge_m2_rbac.php`).
- Truy vấn lấy thông tin phân công trước khi xóa để ghi nhật ký chi tiết:
  ```php
  $stmt = $pdo->prepare("
      SELECT u.full_name, c.name AS class_name, tc.subject 
      FROM teacher_classes tc 
      JOIN users u ON tc.teacher_id = u.id 
      JOIN classes c ON tc.class_id = c.id 
      WHERE tc.id = ?
  ");
  $stmt->execute([$aid]);
  $info = $stmt->fetch();
  if ($info) {
      $pdo->prepare("DELETE FROM teacher_classes WHERE id = ?")->execute([$aid]);
      log_action((int)$_SESSION['user_id'], $_SESSION['username'] ?? 'admin', 'Hủy phân công', "Admin đã hủy quyền dạy môn [{$info['subject']}] lớp [{$info['class_name']}] của giáo viên [{$info['full_name']}].");
      $msg = "Đã hủy phân công dạy môn {$info['subject']} lớp {$info['class_name']} của giáo viên {$info['full_name']}!";
  }
  ```

#### D. Quy trình Xử lý Giám sát Sổ điểm (`impersonate`):
- Giữ nguyên cơ chế bảo mật đã qua kiểm thử:
  ```php
  if (isset($_GET['impersonate'])) {
      require_admin();
      $teacherId = (int)$_GET['impersonate'];
      $sub = $_GET['sub'] ?? 'Toán';
      $cid = (int)($_GET['cid'] ?? 0);

      $tUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
      $tUser->execute([$teacherId]);
      $teacher = $tUser->fetch();

      if ($teacher) {
          $cStmt = $pdo->prepare("SELECT name FROM classes WHERE id = ?");
          $cStmt->execute([$cid]);
          $cName = $cStmt->fetchColumn() ?: "ID $cid";

          log_action((int)$_SESSION['user_id'], $_SESSION['username'] ?? 'admin', 'Admin giám sát sổ điểm', "Admin truy cập mở bảng điểm môn [$sub] lớp [$cName] của giáo viên [{$teacher['full_name']}].");

          $_SESSION['view_as_teacher_id'] = $teacher['id'];
          $_SESSION['view_as_teacher_name'] = $teacher['full_name'];
          header("Location: nhap_diem.php?teacher_id={$teacher['id']}&subject=" . urlencode($sub) . "&class_id={$cid}");
          exit;
      }
  }
  ```

---

### 4.6. Module 6: Trình Xem Nhật Ký Kiểm Toán Thời Gian Thực (Real-Time Audit Log Viewer)

#### A. Thiết kế Giao diện:
- Đặt tại khu vực cuối trang, giao diện cao cấp:
  - Thanh công cụ phía trên bảng:
    - **Chọn số lượng bản ghi hiển thị**: `<select onchange="window.location.href='admin.php?log_limit=' + this.value">` (10, 25, 50, 100 bản ghi).
    - **Ô tìm kiếm nhanh thời gian thực**: Lọc tức thì trên client-side theo từ khóa (username, hành động, nội dung, IP).
    - **Nút làm mới nhật ký**: `[🔄 Làm mới]` tải lại trang hoặc nạp lại bản ghi mới nhất.
- Bảng nhật ký với tiêu đề màu tối (`#090d16` / `#0f172a`):
  - Cột 1: `Thời Gian` (định dạng `Y-m-d H:i:s`, chữ xám nhỏ).
  - Cột 2: `Tài Khoản` (in đậm, kèm username).
  - Cột 3: `Hành Động`: Huy hiệu phân màu theo nhóm hành động:
    - 🟢 Xanh lá: `Đăng nhập thành công`, `Tạo tài khoản giáo viên`, `Tạo lớp học`.
    - 🟣 Tím/Lam: `Phân công giáo viên`, `Admin giám sát sổ điểm`, `Lưu sổ điểm`.
    - 🟡 Vàng cam: `Hủy phân công`, `Đăng xuất`, `Import học sinh`.
    - 🔴 Đỏ: `Xóa lớp học`, `Truy cập trái phép bị chặn`, `Đăng nhập thất bại`.
  - Cột 4: `Chi Tiết Nội Dung` (hiển thị trọn vẹn văn bản giải trình).
  - Cột 5: `Địa Chỉ IP` (font JetBrains Mono, ví dụ: `127.0.0.1`).

---

### 4.7. Tích Hợp Module Import Danh Sách Học Sinh (Cooperative Hook)
Để đảm bảo toàn bộ tệp `admin.php` hoạt động liền mạch với kết quả của `explorer_m3_1` (xlsx helper) và `explorer_m3_3` (student import engine):
- Khối `action = 'import_students'` trong `admin.php` sẽ:
  - Cho phép chọn lớp đích (`target_class_id`).
  - Hỗ trợ tải lên cả `.xlsx` và `.csv`.
  - Nếu có `includes/xlsx_helper.php`, gọi hàm `parse_xlsx()` hoặc `parse_csv()`.
  - Thực hiện nạp học sinh với ràng buộc `UNIQUE(class_id, full_name, birthday)` để bảo đảm không sinh bản ghi trùng lặp khi nạp lại tệp lần 2.
  - Cung cấp liên kết tải xuống cả 2 tệp mẫu: `sample_students.xlsx` và `sample_students.csv`.

---

## 5. THIẾT KẾ GIAO DIỆN & TƯƠNG TÁC JAVASCRIPT (CLIENT-SIDE SCRIPTS)

Để đạt trải nghiệm SaaS cao cấp (Stitch UI) mà không cần nạp thêm thư viện ngoài (zero-dependency vanilla JS):

```javascript
// 1. Chọn / Bỏ chọn toàn bộ một khối lớp cụ thể
function selectGrade(gradeNum, isChecked) {
    const checkboxes = document.querySelectorAll('.cb-grade-' + gradeNum);
    checkboxes.forEach(cb => {
        cb.checked = isChecked;
    });
    updateSelectedCount();
}

// 2. Chọn / Bỏ chọn toàn bộ tất cả các khối trong trường
function selectAllGrades(isChecked) {
    const checkboxes = document.querySelectorAll('.cb-class');
    checkboxes.forEach(cb => {
        cb.checked = isChecked;
    });
    updateSelectedCount();
}

// 3. Cập nhật số lượng lớp đang được chọn theo thời gian thực
function updateSelectedCount() {
    const checked = document.querySelectorAll('.cb-class:checked').length;
    const total = document.querySelectorAll('.cb-class').length;
    const el = document.getElementById('selectedClassCount');
    if (el) {
        el.innerText = `Đã chọn: ${checked} / ${total} lớp`;
        el.style.fontWeight = checked > 0 ? '700' : 'normal';
        el.style.color = checked > 0 ? 'var(--primary)' : 'var(--text-muted)';
    }
}

// 4. Lọc danh sách phân công theo thời gian thực
function filterAssignments() {
    const query = document.getElementById('assignSearchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#assignmentTable tbody tr');
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(query) ? '' : 'none';
    });
}

// 5. Lọc bảng nhật ký kiểm toán theo thời gian thực
function filterAuditLogs() {
    const query = document.getElementById('logSearchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#auditLogTable tbody tr');
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(query) ? '' : 'none';
    });
}
```

---

## 6. PHÂN TÍCH RỦI RO & BẢO VỆ CHỐNG TẤN CÔNG (ADVERSARIAL RESILIENCE)

| Kịch bản tấn công | Rủi ro tiềm ẩn | Cơ chế phòng thủ & Kiểm chứng |
|---|---|---|
| **Giáo viên truy cập trái phép** | Giáo viên gõ URL `admin.php` hoặc gửi POST/GET để phân công lớp hoặc tạo tài khoản. | Ngay dòng 5 của `admin.php` gọi `require_admin()`. Hàm này kiểm tra `$_SESSION['role'] === 'admin'`, nếu không phải lập tức ghi `Truy cập trái phép bị chặn` vào `audit_logs`, trả về HTTP 403 và ngắt `exit`. Đã kiểm chứng 100% bằng `challenge_m2_rbac.php`. |
| **SQL Injection trong Class/Teacher creation** | Chèn chuỗi `' OR '1'='1` vào tên lớp, tên đăng nhập hoặc họ tên giáo viên. | 100% các câu lệnh đều sử dụng PDO Prepared Statements với tham số `?` hoặc `:param`. Không dùng nối chuỗi trong SQL. |
| **Trùng lặp Lớp học** | Tạo trùng tên lớp (ví dụ tạo 2 lớp `6A1`). | Khóa `UNIQUE KEY unique_class_name (name)` trong MySQL chặn triệt để. Code PHP kiểm tra trước để đưa ra thông báo thân thiện. |
| **Trùng lặp Phân công** | Phân công cùng 1 giáo viên dạy cùng môn ở cùng 1 lớp nhiều lần. | Khóa `UNIQUE KEY unique_assignment (teacher_id, class_id, subject)` ngăn chặn duplicate row. Vòng lặp PHP đếm và bỏ qua các lớp đã phân công, báo rõ cho người dùng. |
| **XSS trong Audit Logs** | Tên giáo viên hoặc chi tiết nhật ký chứa thẻ `<script>` gây hại khi Admin mở xem. | Tất cả dữ liệu đầu ra từ CSDL đều được bọc qua `htmlspecialchars($str, ENT_QUOTES, 'UTF-8')`. |
| **CSRF trên Hủy phân công & Xóa lớp** | Lừa click vào link `admin.php?delete_assign=1`. | JavaScript có hộp thoại xác nhận `confirm()`. Đồng thời lưu giữ hỗ trợ cả GET lẫn POST để không phá vỡ kịch bản kiểm thử tự động hiện hữu. |

---

## 7. KẾ HOẠCH BÀN GIAO & PHỐI HỢP VỚI IMPLEMENTER (WORKER)

Tệp `analysis.md` này cung cấp toàn bộ thiết kế kiến trúc, cấu trúc dữ liệu, thuật toán xử lý và mẫu giao diện hoàn chỉnh. Để Worker có thể tiến hành viết mã trực tiếp một cách chính xác tuyệt đối mà không cần phỏng đoán, tệp kế tiếp `handoff.md` sẽ chứa:
1. Quan sát thực chứng chi tiết với đường dẫn tệp và số dòng.
2. Chuỗi suy luận logic từ quan sát đến thiết kế.
3. Các cảnh báo (caveats) quan trọng.
4. Kết luận kiến trúc.
5. Phương pháp kiểm chứng độc lập bằng lệnh CLI kiểm thử chuẩn (`verify_m2.php`, `challenge_m2_rbac.php`, và kịch bản `verify_m3.php`).
