# Báo Cáo Bàn Giao Kiểm Toán Mã Nguồn (Handoff Report)

- **Agent**: explorer_survey_1
- **Working Directory**: `D:\New folder\my-web-project\.agents\teamwork\explorer_survey_1`
- **Mục tiêu**: Bàn giao kết quả khảo sát toàn diện mã nguồn hiện tại của dự án `my-web-project` cho Orchestrator và các agent triển khai.
- **Loại bàn giao**: Hard (Nhiệm vụ kiểm toán hoàn thành đầy đủ, mọi nội dung được xác minh độc lập).

---

## 1. Observation (Các Quan Sát Thực Tế Trực Tiếp)

1. **Backdoor xác thực trong `login.php` (Dòng 16)**:
   - File: `D:\New folder\my-web-project\login.php`, dòng 16:
     ```php
     if ($user && (password_verify($password, $user['password']) || $password === '123456')) {
     ```
   - Lệnh kiểm tra hash trong `database.sql` (dòng 77-81):
     ```powershell
     & 'D:\xam\php\php.exe' --% -r "var_dump(password_verify('123456', '$2y$10$4y9pQzS/m7y9xY8Z/xH0ve2w5.JjK.RjT18P4q7lZ0eQ0/a4uCqS.'));"
     ```
     Kết quả: `bool(false)`! Hash mẫu trong `database.sql` không khớp với mật khẩu `123456`.
   - Lệnh kiểm tra hash thực tế trong MariaDB `edu_radar`:
     ```powershell
     & 'D:\xam\php\php.exe' --% -r "require 'includes/db.php'; $p = db(); foreach ($p->query('SELECT username, password FROM users')->fetchAll() as $u) echo $u['username'].': '.(password_verify('123456', $u['password'])?'OK':'FAIL').PHP_EOL;"
     ```
     Kết quả: `admin: OK`, `gv_toan: OK`, `gv_van: OK` (Hash trong database là `$2y$10$Huov5BMeiGHCxhgfaTkauuthlWkvzJguIrvV6vWU1SQsJDrk6PlMG`).

2. **Lỗ hổng phân quyền nghiêm trọng (BOLA / IDOR) trong `nhap_diem.php` (Dòng 46-108)**:
   - File: `D:\New folder\my-web-project\nhap_diem.php`, dòng 46-52 và 85-100:
     Lấy `class_id` và `subject` trực tiếp từ `$_GET` và lưu điểm bằng `POST` mà **không hề có câu lệnh nào kiểm tra** xem giáo viên có được phân công cặp `(class_id, subject)` đó trong bảng `teacher_classes` hay không. Bất kỳ giáo viên nào đăng nhập đều có thể xem và sửa điểm của bất kỳ lớp/môn nào qua tham số URL.

3. **Tính năng thừa Zalo và Modal không được yêu cầu**:
   - `index.php`, dòng 56-72 (hàm `buildFeedback`), dòng 209-211 (nút `📋 Copy Tin Nhắn Zalo`), dòng 221 (thẻ `<div id="toast" class="toast">Đã sao chép tin nhắn Zalo!</div>`).
   - `assets/js/main.js`, dòng 25-41 (xử lý clipboard Zalo và hiển thị toast).
   - `assets/js/main.js`, dòng 43-60 (mã điều khiển modal can thiệp `#actionModal`, `openActionModal` - phần tử này không hề tồn tại trên HTML).

4. **Tập tin SQLite và mã nguồn SQLite tồn dư**:
   - Tồn tại file `D:\New folder\my-web-project\database.sqlite` (kích thước 40,960 bytes, chứa 6 bảng).
   - File `includes/config.php`, dòng 12-13: `define('SQLITE_FILE', __DIR__ . '/../database.sqlite');`.
   - File `includes/db.php`, dòng 10-18: nhánh `if (DB_DRIVER === 'sqlite')` và dòng 63-126: hàm `initSQLite()`.

5. **Lệch cấu trúc bảng trong `database.sql` và MySQL**:
   - Bảng `audit_logs`: Tồn tại trong MySQL MariaDB (23 bản ghi), nhưng **hoàn toàn thiếu** trong file `database.sql`.
   - Bảng `students`: Thiếu ràng buộc `UNIQUE(class_id, full_name, birthday)` cả trong MySQL và trong `database.sql` (chỉ có index thường trên `class_id`).
   - Bảng `scores`: Dùng các cột cũ `m1, m2, p15_1, p15_2, t1_1, t1_2, gk, ck, avg_score` thay vì chuẩn Thông tư 22 `tx1, tx2, tx3, tx4, gk, ck, avg_score`.

6. **Thiếu sót chức năng quản trị trong `admin.php` và sổ điểm `nhap_diem.php`**:
   - `admin.php`: Thiếu chức năng tạo lớp học mới, thiếu chức năng xóa lớp học, thiếu chức năng tạo tài khoản giáo viên mới; chỉ hỗ trợ import học sinh bằng `.csv`, hoàn toàn không có hỗ trợ `.xlsx`.
   - `nhap_diem.php`: Thiếu cột Ngày sinh, thiếu hỗ trợ nạp điểm bằng `.xlsx`, thiếu nút xuất file `.xlsx`, thiếu điều hướng ô điểm bằng phím mũi tên.

7. **Bộ kiểm thử tự động `tests/run_tests.php` chưa tồn tại**:
   - Không tìm thấy bất kỳ file kiểm thử nào trong dự án (`tests/` chưa được tạo).

---

## 2. Logic Chain (Chuỗi Lập Luận Từ Quan Sát Đến Đánh Giá)

1. Từ **Quan sát 1**: Lập trình viên ban đầu tạo file `database.sql` với hash mật khẩu sai. Khi đăng nhập thử nghiệm thất bại, thay vì tạo lại hash chuẩn, lập trình viên đã thêm điều kiện `|| $password === '123456'` vào `login.php`. Điều này tạo ra lỗ hổng bỏ qua hoàn toàn cơ chế băm mật khẩu bảo mật. Khi triển khai, cần thay hash chuẩn vào `database.sql` và xóa bỏ điều kiện bypass này để đáp ứng yêu cầu R1.
2. Từ **Quan sát 2**: `nhap_diem.php` tin tưởng hoàn toàn dữ liệu từ query string và body POST. Vì vậy, giáo viên chỉ cần gõ URL với `class_id` hoặc `subject` khác là xem và sửa được dữ liệu không thuộc quyền quản lý. Điều này vi phạm trực tiếp yêu cầu R1 ("A teacher can ONLY view and edit grades for the exact (class_id, subject) pairs assigned to them..."). Cần bổ sung middleware/logic kiểm tra quyền ở đầu trang `nhap_diem.php` và trong khối xử lý POST.
3. Từ **Quan sát 3**: Các tính năng Zalo và modal can thiệp là tàn dư từ các yêu cầu cũ hoặc ý muốn chủ quan của dev trước đó, trực tiếp đi ngược lại chỉ đạo của người dùng trong R4 ("DO NOT include the Zalo copy button or parent message generator"). Việc loại bỏ chúng giúp mã nguồn sạch sẽ, không có dead code và đúng chuẩn chỉ đạo.
4. Từ **Quan sát 4**: Dự án được yêu cầu chạy trực tiếp và duy nhất trên MySQL (`edu_radar`) của XAMPP. File `database.sqlite` và các đoạn mã SQLite dự phòng gây phân tán dữ liệu và vi phạm R1. Việc xóa bỏ file và loại bỏ code SQLite là bắt buộc.
5. Từ **Quan sát 5**: Việc thiếu bảng `audit_logs` trong `database.sql` sẽ khiến việc khởi tạo lại môi trường bị lỗi. Việc thiếu `UNIQUE` trên `students` dẫn đến nguy cơ nhân bản học sinh khi import file nhiều lần. Cần đồng bộ schema chuẩn vào `database.sql` và áp dụng vào MySQL.
6. Từ **Quan sát 6 & 7**: Các yêu cầu về nghiệp vụ VnEdu (Thông tư 22, file Excel `.xlsx`, ma trận lớp học, test suite CLI) chưa được đáp ứng. Cần lập kế hoạch triển khai bổ sung cụ thể cho các giai đoạn tiếp theo.

---

## 3. Caveats (Các Điểm Giới Hạn & Giả Định)

- **Quyền hạn của Explorer**: Vai trò Explorer là chỉ đọc (Read-only), không thực hiện chỉnh sửa mã nguồn gốc tại thư mục dự án trong bước khảo sát này.
- **Thư viện Excel (.xlsx)**: Môi trường PHP XAMPP (`D:\xam\php\php.exe`) có sẵn các module `zip`, `SimpleXML`, `xml`, `dom`. Việc xử lý `.xlsx` hoàn toàn có thể xây dựng bằng PHP thuần (native ZipArchive + SimpleXML) mà không cần cài thêm Composer packages.
- **Không có dữ liệu nhạy cảm thực tế**: Toàn bộ dữ liệu trong database hiện tại là dữ liệu thử nghiệm học sinh và giáo viên lớp 6A1, có thể làm mới (re-seed) an toàn.

---

## 4. Conclusion (Kết Luận Đánh Giá)

Hệ thống hiện tại có khung sườn cơ bản kết nối XAMPP Apache và MySQL, nhưng đang tồn tại các vấn đề then chốt:
1. **Lỗ hổng bảo mật & phân quyền**: Backdoor đăng nhập trong `login.php` và hổng phân quyền sổ điểm trong `nhap_diem.php`.
2. **Tính năng thừa cần dọn dẹp**: Nút Zalo, toast Zalo, hàm tạo tin nhắn Zalo trong `index.php`, modal rác trong `assets/js/main.js`, file và code `database.sqlite`.
3. **Chưa chuẩn hóa VnEdu & Dữ liệu**: Cột điểm trong `scores` chưa theo Thông tư 22; thiếu ràng buộc `UNIQUE` trên `students`; `database.sql` thiếu bảng `audit_logs`.
4. **Tính năng còn thiếu**: Chưa có tạo/xóa lớp, tạo giáo viên, import/export `.xlsx` chuẩn VnEdu, điều hướng phím mũi tên, và bộ test tự động `tests/run_tests.php`.

Toàn bộ chi tiết kỹ thuật và phương án khắc phục đã được biên soạn đầy đủ trong `analysis.md`.

---

## 5. Verification Method (Phương Pháp Độc Lập Xác Minh Lại)

Người tiếp nhận có thể kiểm tra độc lập các phát hiện trên bằng các lệnh sau:

1. **Kiểm tra backdoor đăng nhập**:
   ```powershell
   Select-String -Path "D:\New folder\my-web-project\login.php" -Pattern "123456"
   ```
2. **Kiểm tra hash lỗi trong database.sql**:
   ```powershell
   & "D:\xam\php\php.exe" --% -r "var_dump(password_verify('123456', '$2y$10$4y9pQzS/m7y9xY8Z/xH0ve2w5.JjK.RjT18P4q7lZ0eQ0/a4uCqS.'));"
   ```
3. **Kiểm tra sự tồn tại của tính năng Zalo & Modal thừa**:
   ```powershell
   Select-String -Path "D:\New folder\my-web-project\index.php" -Pattern "Zalo"
   Select-String -Path "D:\New folder\my-web-project\assets\js\main.js" -Pattern "actionModal"
   ```
4. **Kiểm tra file SQLite tồn dư**:
   ```powershell
   Test-Path "D:\New folder\my-web-project\database.sqlite"
   ```
5. **Kiểm tra schema và số lượng bảng trong MariaDB**:
   ```powershell
   & "D:\xam\php\php.exe" --% -r "require 'includes/db.php'; foreach(db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) echo $t.PHP_EOL;"
   ```
