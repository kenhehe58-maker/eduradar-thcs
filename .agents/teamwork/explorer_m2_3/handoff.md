# Báo Cáo Bàn Giao (Handoff Report) — explorer_m2_3

**Người thực hiện**: `explorer_m2_3`  
**Vai trò**: Gradebook BOLA/IDOR Security Analyzer & Verification Suite Designer  
**Thời gian bàn giao**: 2026-10-17  
**Loại bàn giao**: Hard (Nhiệm vụ phân tích và thiết kế đã hoàn thành trọn vẹn)  
**Tệp đính kèm phân tích chi tiết**: `D:\New folder\my-web-project\.agents\teamwork\explorer_m2_3\analysis.md`

---

## 1. Observation (Quan sát thực tế)

1. **Quan sát mã nguồn `nhap_diem.php` (dòng 5–8, 46–53, 173–183)**:
   - File path: `D:\New folder\my-web-project\nhap_diem.php`.
   - Dòng 5–8:
     ```php
     if (!isset($_SESSION['user_id'])) {
         header('Location: login.php');
         exit;
     }
     ```
     Hệ thống chỉ kiểm tra tồn tại session mà không kiểm tra role hoặc quyền hạn của tài khoản giáo viên đối với lớp/môn học.
   - Dòng 46–53:
     ```php
     $selectedClassId = isset($_GET['class_id']) && (int)$_GET['class_id'] > 0 
         ? (int)$_GET['class_id'] 
         : (!empty($myClasses) ? $myClasses[0]['id'] : 0);

     $selectedSubject = isset($_GET['subject']) && $_GET['subject'] !== '' 
         ? trim($_GET['subject']) 
         : $defaultSubject;
     ```
     Giá trị `$selectedClassId` và `$selectedSubject` được nhận trực tiếp từ `$_GET` mà không qua bước đối chiếu với `teacher_classes`.
   - Dòng 173–182:
     ```php
     SELECT s.id, s.full_name, s.parent_phone,
            sc.m1, sc.m2, sc.p15_1, sc.p15_2, sc.t1_1, sc.gk, sc.ck, sc.avg_score
     FROM students s
     LEFT JOIN scores sc ON s.id = sc.student_id AND sc.subject = ?
     WHERE s.class_id = ?
     ```
     Dữ liệu học sinh và điểm số được truy vấn và trả về trực tiếp cho bất kỳ cặp `($selectedSubject, $selectedClassId)` nào do người dùng truyền vào.

2. **Quan sát luồng xử lý lưu điểm `save_scores` (dòng 61–108)**:
   - File path: `D:\New folder\my-web-project\nhap_diem.php`.
   - Dòng 61:
     ```php
     if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_scores')
     ```
   - Xử lý duyệt `$_POST['scores']` và cập nhật trực tiếp vào cơ sở dữ liệu `scores` mà không xác thực quyền hạn của `$_SESSION['user_id']` đối với lớp và môn đó; cũng không xác thực học sinh có thuộc lớp đó hay không.
   - Dòng 105:
     ```php
     logAction('Lưu sổ điểm', "Cập nhật bảng điểm môn [$selectedSubject] cho lớp [$curClassName] ($savedCount học sinh).");
     ```
     Ghi log hành động như một thao tác hợp lệ, không chặn đứng và không cảnh báo hành vi xâm nhập.

3. **Quan sát tham số `teacher_id` (dòng 15, 41)**:
   - Dòng 15:
     ```php
     $actingTeacherId = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : ($realUserRole === 'admin' ? 0 : $realUserId);
     ```
   - Dòng 41:
     ```php
     $stmt->execute([$actingTeacherId > 0 ? $actingTeacherId : $realUserId]);
     ```
     Người dùng thông thường chỉ cần truyền `?teacher_id=X` là có thể đổi giá trị `$actingTeacherId`, dẫn đến câu query lấy danh sách lớp của giáo viên ID X.

4. **Quan sát môi trường thực thi và kiểm thử hiện hữu**:
   - Chạy lệnh `D:\xam\php\php.exe tests/verify_m1.php` qua công cụ `run_command`: Kết quả đạt `Total Tests: 12 | Passed: 12 | Failed: 0`.
   - Kiểm tra kết nối Web Server Apache: `http://localhost/my-web-project/login.php` phản hồi HTTP 200, hiển thị giao diện đăng nhập.
   - Extension `curl` trong PHP XAMPP (`D:\xam\php\php.exe`): Đã được kích hoạt sẵn (`curl_ok`).
   - CSDL `edu_radar` có 3 tài khoản mẫu: `admin` (ID 1, admin), `gv_toan` (ID 2, teacher, phân công 6A1 Toán, 7A1 Toán), `gv_van` (ID 3, teacher, phân công 6A1 Ngữ Văn). Mật khẩu ban đầu đều là `123456`.

---

## 2. Logic Chain (Chuỗi lập luận suy diễn)

1. Từ **Quan sát 1**: Vì `nhap_diem.php` chỉ kiểm tra đăng nhập bằng `!isset($_SESSION['user_id'])` và chấp nhận trực tiếp `$_GET['class_id']` cùng `$_GET['subject']` mà không kiểm tra xem giáo viên có dòng tương ứng trong bảng `teacher_classes` hay không -> Bất kỳ giáo viên nào cũng có thể đổi URL thành mã lớp và môn học của giáo viên khác để xem trộm toàn bộ danh sách học sinh, số điện thoại phụ huynh và bảng điểm (BOLA / IDOR trên GET).
2. Từ **Quan sát 2**: Vì luồng `action=save_scores` qua phương thức POST xử lý cập nhật cơ sở dữ liệu dựa trên biến `$selectedSubject` từ URL và mảng `$studentId` trong POST mà không có chốt chặn RBAC phía máy chủ -> Giáo viên không được phân công có thể tạo gói tin POST giả mạo để can thiệp, sửa đổi điểm giữa kỳ, cuối kỳ của bất kỳ học sinh nào trong trường (BOLA / IDOR trên POST).
3. Từ **Quan sát 3**: Do biến `$actingTeacherId` ưu tiên nhận `$_GET['teacher_id']` mà không ràng buộc điều kiện `$realUserRole === 'admin'` -> Giáo viên có thể lợi dụng để thăm dò phân công của giáo viên khác.
4. Từ **Quan sát 4**: Hệ thống Apache và MySQL trên môi trường thực tế đang hoạt động ổn định, PHP CLI có cURL -> Việc xây dựng bộ kiểm thử `tests/verify_m2.php` theo mô hình gửi HTTP request thật kết hợp kiểm tra dữ liệu trực tiếp trong CSDL MySQL là giải pháp tối ưu, đáp ứng chính xác tiêu chí Acceptance Criteria của dự án.

---

## 3. Caveats (Các lưu ý và giới hạn)

- **Quyền hạn explorer**: Tuân thủ nghiêm ngặt nguyên tắc chỉ đọc (read-only investigation), không trực tiếp sửa đổi các tệp mã nguồn (`nhap_diem.php`, `login.php`, `admin.php`) hay tạo mới tệp bên ngoài thư mục được chỉ định. Toàn bộ mã nguồn hoàn chỉnh đã được cung cấp sẵn sàng trong `analysis.md` để Worker agent áp dụng.
- **Tính tương thích giao diện Thông tư 22 (Milestone 4)**: Tại thời điểm M2, các cột điểm trong giao diện `nhap_diem.php` vẫn còn tên cột cũ (`m1, m2, p15_1...`). Việc đại tu bảng điểm sang chuẩn 6 cột (`tx1..tx4, gk, ck`) và tính điểm tự động thuộc phạm vi Milestone 4. Việc bảo vệ route và phòng chống BOLA trong Milestone 2 được thiết kế độc lập, không phụ thuộc vào cấu trúc cột điểm bên trong bảng HTML.

---

## 4. Conclusion (Kết luận & Đề xuất giải pháp)

1. **Khắc phục BOLA / IDOR trong `nhap_diem.php`**:
   - Tích hợp bắt buộc `require_login()` từ `includes/auth.php`.
   - Vô hiệu hóa và khử sạch tham số `?teacher_id` nếu người dùng không phải là `admin`.
   - Thiết lập chốt chặn phân công `require_teacher_assignment($realUserId, $selectedClassId, $selectedSubject)` cho cả luồng GET và POST:
     - Admin: Cho phép xem và sửa toàn bộ các lớp và các môn.
     - Giáo viên được phân công: Cho phép thao tác bình thường (HTTP 200).
     - Giáo viên chưa được phân công hoặc cố tình truy cập trái phép: Trả về HTTP 403 Forbidden, ghi bản ghi `Truy cập trái phép bị chặn` vào `audit_logs` kèm chi tiết Lớp và Môn, ngắt kết nối ngay lập tức (`die`).
     - Kiểm tra chéo `student_id` thuộc `$selectedClassId` trong vòng lặp POST để chặn giả mạo ID học sinh.
2. **Bộ kiểm thử tự động `tests/verify_m2.php`**:
   - Đã được thiết kế và viết mã hoàn chỉnh tại Mục 4 của `analysis.md`.
   - Bao gồm 16 ca kiểm thử bao phủ toàn diện 4 nhóm nghiệp vụ của Milestone 2:
     - Nhóm 1: Kiểm tra cấu trúc mã nguồn và loại bỏ backdoor.
     - Nhóm 2: Xác thực đa tài khoản (`admin`, `gv_toan`, `gv_van`), từ chối mật khẩu sai, xác nhận đăng xuất.
     - Nhóm 3: Rào chắn route quản trị `admin.php` đối với khách và giáo viên.
     - Nhóm 4: Rào chắn route sổ điểm `nhap_diem.php`, kiểm thử trực tiếp các kịch bản tấn công BOLA/IDOR (truy cập trái phép môn khác, lớp khác, giả mạo tham số `teacher_id`, gửi POST lưu điểm giả mạo).
   - Tự động đối chiếu trạng thái cơ sở dữ liệu MySQL và bản ghi nhật ký `audit_logs`.

---

## 5. Verification Method (Phương pháp kiểm chứng độc lập)

Sau khi Worker agent hoàn tất cài đặt theo thiết kế trong `analysis.md`, thực hiện các bước sau để nghiệm thu:

1. **Kiểm tra tệp và cấu hình**:
   - Xác nhận sự tồn tại của `includes/auth.php`.
   - Xác nhận `login.php` không còn chuỗi `|| $password === '123456'`.
   - Xác nhận `nhap_diem.php` đã chèn `require_teacher_assignment()`.

2. **Chạy bộ kiểm thử tự động**:
   Thực thi lệnh trong terminal tại thư mục gốc dự án:
   ```cmd
   D:\xam\php\php.exe tests/verify_m2.php
   ```
   **Điều kiện Đạt**:
   - Toàn bộ 16 ca kiểm thử hiển thị `[PASS]`.
   - Tổng kết xuất hiện: `Total Tests: 16 | Passed: 16 | Failed: 0`.
   - Mã thoát (exit code) là `0`.

3. **Điều kiện vô hiệu hóa (Invalidation conditions)**:
   - Nếu `gv_toan` truy cập `nhap_diem.php?class_id=1&subject=Ngữ Văn` mà nhận về HTTP 200 -> Cơ chế BOLA chưa được khắc phục thành công.
   - Nếu `admin` truy cập sổ điểm của giáo viên mà bị từ chối 403 -> Rào chắn bị lỗi phân quyền Admin.
   - Nếu có bất kỳ test nào báo `[FAIL]` hoặc exit code khác `0`.
