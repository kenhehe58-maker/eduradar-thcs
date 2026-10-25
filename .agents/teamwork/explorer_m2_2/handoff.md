# Báo Cáo Handoff (Handoff Report) — explorer_m2_2

- **To**: Orchestrator (`391812a0-32ec-4263-b097-6162669cc4f7`) / Implementer
- **From**: explorer_m2_2 (teamwork_preview_explorer)
- **Working Directory**: `D:\New folder\my-web-project\.agents\teamwork\explorer_m2_2`
- **Milestone**: M2 (Auth & Strict RBAC)
- **Artifacts**: `analysis.md`, `handoff.md`
- **Date**: 2026-10-17

---

## 1. Observation (Những gì đã quan sát thực tế)

1. **Thư mục `includes/`**:
   - Lệnh `list_dir` tại `D:\New folder\my-web-project\includes` cho thấy chỉ có 2 tệp: `config.php` (837 bytes) và `db.php` (4343 bytes).
   - Tệp middleware xác thực tập trung `includes/auth.php` **chưa tồn tại**.

2. **Tệp `admin.php`**:
   - Dòng 2–16:
     ```php
     session_start();
     require_once __DIR__ . '/includes/db.php';

     // CHẶN BẢO MẬT: CHỈ DUY NHẤT TÀI KHOẢN ROLE ADMIN MỚI ĐƯỢC PHÉP TRUY CẬP TRANG NÀY!
     if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
         http_response_code(403);
         logAction('Truy cập trái phép bị chặn', 'Cố tình truy cập trang admin.php khi không có quyền.');
         die('
             <div style="font-family: sans-serif; text-align: center; padding: 50px;">
                 <h1 style="color: #dc2626;">⛔ Truy cập bị từ chối!</h1>
                 <p>Khu vực này chỉ dành riêng cho tài khoản Quản trị viên (Admin) của nhà trường.</p>
                 <a href="login.php" style="display: inline-block; padding: 10px 20px; background: #4f46e5; color: #fff; text-decoration: none; border-radius: 8px;">Đăng nhập bằng tài khoản Admin</a>
             </div>
         ');
     }
     ```
   - Dòng 23–56: Khối POST `batch_assign` xử lý phân công không có defensive guard thứ cấp. Dòng 44 truy vấn `$pdo->query("SELECT name FROM classes WHERE id = $cid")` sử dụng nối biến trực tiếp. Dòng 51 gọi hàm cũ `logAction(...)`.
   - Dòng 59–91: Khối POST `import_students` xử lý nạp file học sinh CSV không có defensive guard thứ cấp. Dòng 83 nối biến `$target_class_id`. Dòng 85 gọi `logAction(...)`.
   - Dòng 94–112: Khối GET `impersonate` mở sổ điểm giáo viên nối biến `$cid` (dòng 104) và gọi `logAction(...)`.
   - Dòng 115–131: Khối GET `delete_assign` xóa phân công dựa trên GET request nối chuỗi `$aid` (dòng 122) và gọi `logAction(...)`.

3. **Tệp `includes/db.php`**:
   - Dòng 54–91 định nghĩa hàm `log_action(?int $user_id, ?string $username, string $action, string $details = ''): void` ghi trực tiếp vào bảng `audit_logs` (`user_id, username, action, details, ip_address, created_at`) bằng prepared statement.
   - Dòng 101–106 định nghĩa wrapper tương thích cũ `logAction(string $action, string $details = ''): void`.

4. **Tệp `database.sql` & Cơ sở dữ liệu MySQL `edu_radar`**:
   - Dòng 50–61: Bảng `teacher_classes` có ràng buộc `UNIQUE KEY unique_assignment (teacher_id, class_id, subject)`.
   - Dòng 104–115: Bảng `audit_logs` có các trường `id, user_id, username, action, details, ip_address, created_at`.
   - Dòng 149–153: Dữ liệu phân công mẫu: `(1, 2, 1, 'Toán')`, `(2, 2, 10, 'Toán')`, `(3, 3, 1, 'Ngữ Văn')`.

5. **Hợp đồng kỹ thuật tại `PROJECT.md` (Lines 54–58)**:
   ```
   ### includes/auth.php
   - function require_login(): void: Redirects unauthenticated requests to login.php.
   - function require_admin(): void: Requires $_SESSION['role'] === 'admin', logs unauthorized attempts and outputs HTTP 403 / redirects.
   - function verify_teacher_assignment(int $teacher_id, int $class_id, string $subject): bool: Checks teacher_classes.
   - function require_teacher_assignment(int $teacher_id, int $class_id, string $subject): void: Throws HTTP 403 if teacher is not assigned and logs to audit_logs.
   ```
   Và yêu cầu bổ sung hàm `current_user(): ?array` trong nhiệm vụ Milestone 2.

---

## 2. Logic Chain (Chuỗi suy luận từng bước)

1. **Từ Quan sát 1 & 5**: Vì `includes/auth.php` chưa tồn tại và được quy định cụ thể trong hợp đồng kỹ thuật tại `PROJECT.md`, việc tạo mới `includes/auth.php` với đầy đủ 5 hàm chuẩn (`require_login`, `require_admin`, `current_user`, `verify_teacher_assignment`, `require_teacher_assignment`) là thành phần bắt buộc để cung cấp nền tảng RBAC dùng chung cho toàn bộ dự án.
2. **Từ Quan sát 2 & 3**: Đoạn kiểm tra quyền ở đầu `admin.php` hiện đang viết inline thủ công và gọi `logAction` thiếu chi tiết. Khi thay thế bằng `require_admin()`, mọi truy cập trái phép (khách chưa đăng nhập hoặc giáo viên `role !== 'admin'`) sẽ được xử lý tập trung: thiết lập mã phản hồi `http_response_code(403)`, ghi nhật ký chuẩn vào `audit_logs` với action `'Truy cập trái phép bị chặn'` cùng các chi tiết (username, role, IP, method, URI), sau đó lập tức gọi `exit` trước khi bất kỳ truy vấn hay logic nào của trang được thực thi (đảm bảo 0 database mutations).
3. **Từ Quan sát 2 (phần POST/GET)**: Để đạt chuẩn phòng thủ chiều sâu (defense-in-depth), các khối xử lý POST (`batch_assign`, `import_students`) và GET (`impersonate`, `delete_assign`) được đặt thêm lời gọi `require_admin()`. Đồng thời, các câu lệnh SQL nối chuỗi trực tiếp `$cid`, `$target_class_id`, `$aid` được chuẩn hóa 100% sang PDO Prepared Statements (`?`), loại trừ triệt để nguy cơ SQL injection.
4. **Từ Quan sát 4 & 5**: Hàm `verify_teacher_assignment(int $teacher_id, int $class_id, string $subject): bool` truy vấn trực tiếp bảng `teacher_classes` qua prepared statement có chỉ mục `UNIQUE KEY unique_assignment (teacher_id, class_id, subject)`. Hàm `require_teacher_assignment(...)` kết hợp kiểm tra: nếu là `admin` thì cho phép toàn quyền; nếu là `teacher` thì đối chiếu `$_SESSION['user_id'] === $teacher_id` và gọi `verify_teacher_assignment()`. Nếu không hợp lệ, lập tức trả về HTTP 403, ghi nhật ký kiểm toán và dừng thực thi, ngăn chặn triệt để lỗ hổng BOLA/IDOR.

---

## 3. Caveats (Các giả định & Giới hạn khảo sát)

1. **Giới hạn quyền thực thi**: explorer_m2_2 tuân thủ nghiêm ngặt vai trò Read-Only Investigation, không sửa trực tiếp mã nguồn trong `admin.php` hay tự ý tạo file `includes/auth.php` tại thư mục gốc mã nguồn. Toàn bộ mã nguồn hoàn chỉnh và unified diff đã được cung cấp trong `analysis.md` để bàn giao cho Implementer/Worker.
2. **Kiểu phản hồi API/AJAX**: Ứng dụng EduRadar hiện tại là ứng dụng PHP đa trang truyền thống (MPA). Nếu trong các mốc tương lai có phát triển thêm API bất đồng bộ dạng JSON/Fetch, hàm `render_forbidden_page()` có thể mở rộng kiểm tra header `Accept: application/json` để trả về JSON payload `{ "error": "Forbidden", "code": 403 }`.
3. **Không có cảnh báo hay lỗ hổng phụ nào khác chưa khảo sát**: Toàn bộ cấu trúc bảo mật liên quan đến phiên làm việc, định danh vai trò và nhật ký an ninh trong phạm vi nhiệm vụ đã được bao quát đầy đủ.

---

## 4. Conclusion (Đánh giá kết luận & Đề xuất hành động)

1. **Về `includes/auth.php`**: Thiết kế kiến trúc đã hoàn chỉnh 100%, tuân thủ chặt chẽ hợp đồng kỹ thuật tại `PROJECT.md` và các tiêu chuẩn kiểm thử khắt khe của dự án. File đã được viết sẵn trong `analysis.md` và sẵn sàng để tạo tại `includes/auth.php`.
2. **Về `admin.php`**: Đã xác định chính xác các vị trí cần nhúng và bản vá cụ thể:
   - Dòng 1–16: Thay bằng `require_once __DIR__ . '/includes/auth.php'; require_admin();`.
   - Đầu các handler POST/GET: Bổ sung `require_admin()` để phòng thủ chiều sâu.
   - Thay thế toàn bộ truy vấn nối chuỗi sang Prepared Statements.
   - Chuẩn hóa các lệnh ghi log sang `log_action()`.
3. **Tính sẵn sàng**: Bản phân tích `analysis.md` cung cấp đầy đủ mã nguồn và diff sẵn sàng để Implementer đưa vào codebase và để `explorer_m2_3` tích hợp vào kịch bản kiểm thử tự động `tests/verify_m2.php`.

---

## 5. Verification Method (Phương pháp kiểm chứng độc lập)

Người nhận bàn giao hoặc kiểm thử viên có thể kiểm chứng độc lập bằng các bước và câu lệnh sau:

### 1. Kiểm tra cú pháp và khai báo hàm
Chạy lệnh kiểm tra cú pháp PHP:
```powershell
D:\xam\php\php.exe -l includes/auth.php
D:\xam\php\php.exe -l admin.php
```

Kiểm tra sự tồn tại của các hàm bắt buộc:
```powershell
D:\xam\php\php.exe -r "require 'includes/auth.php'; var_dump(function_exists('require_login'), function_exists('require_admin'), function_exists('current_user'), function_exists('verify_teacher_assignment'), function_exists('require_teacher_assignment'));"
```
*Điều kiện đạt*: Toàn bộ 5 hàm trả về `bool(true)`.

### 2. Kiểm thử Route Guard HTTP 403 trên `admin.php`
- **Khách chưa đăng nhập**:
  ```powershell
  curl -i http://localhost/my-web-project/admin.php
  ```
  *Kỳ vọng*: Phản hồi bắt đầu bằng `HTTP/1.1 403 Forbidden` và body chứa thông báo lỗi truy cập bị từ chối.
- **Giáo viên (`gv_toan`) truy cập `admin.php`**:
  Thực hiện đăng nhập bằng tài khoản `gv_toan` (mật khẩu `123456`) qua cookie jar, sau đó request tới `admin.php`:
  *Kỳ vọng*: Nhận HTTP 403 Forbidden.
- **Gửi POST trái phép**:
  Gửi POST request chứa payload `action=batch_assign` từ tài khoản `gv_toan` tới `admin.php`:
  *Kỳ vọng*: Nhận HTTP 403 Forbidden, không có hàng mới nào xuất hiện trong bảng `teacher_classes`.

### 3. Kiểm chứng nhật ký kiểm toán trong MySQL
Chạy truy vấn kiểm tra bảng `audit_logs`:
```sql
SELECT id, user_id, username, action, details, ip_address, created_at 
FROM audit_logs 
WHERE action = 'Truy cập trái phép bị chặn' 
ORDER BY id DESC LIMIT 5;
```
*Kỳ vọng*: Xuất hiện các bản ghi ghi nhận cố tình truy cập `admin.php` với đầy đủ thông tin tài khoản, địa chỉ IP và lý do bị chặn.
