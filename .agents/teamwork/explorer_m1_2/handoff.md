# BÁO CÁO BÀN GIAO (HANDOFF REPORT) — EXPLORER_M1_2

**Mã phân nhiệm**: Milestone 1 (Database Connection Refactoring & Audit Logging Helper)  
**Tác giả**: `explorer_m1_2` (Teamwork Explorer)  
**Người nhận**: `orchestrator_1` / Kỹ sư triển khai Milestone 1  
**Tệp đính kèm phân tích chi tiết**: `D:\New folder\my-web-project\.agents\teamwork\explorer_m1_2\analysis.md`

---

## 1. Observation (Quan Sát Trực Tiếp)

1. **Tệp `includes/config.php` (tổng cộng 20 dòng)**:
   - Dòng 4: `define('DB_DRIVER', 'mysql');`
   - Dòng 7–10: Cấu hình kết nối MySQL (`DB_HOST = '127.0.0.1'`, `DB_NAME = 'edu_radar'`, `DB_USER = 'root'`, `DB_PASS = ''`).
   - Dòng 13: `define('SQLITE_FILE', __DIR__ . '/../database.sqlite');` — Định nghĩa hằng số file SQLite dự phòng.
   - Dòng 15: `define('APP_NAME', 'EduRadar');` — Được sử dụng tại `index.php:112, 122`.
   - Dòng 18: `define('PASS_SCORE', 5.0);` — Được sử dụng tại `index.php:32, 37, 43`.
   - Dòng 19: `define('ABSENCE_RED', 3);` — Không được bất kỳ tệp nào trong toàn bộ dự án tham chiếu tới (kết quả `grep_search` trả về 0 kết quả ngoài `config.php`).
   - Thiếu hằng số `BASE_URL`, `DB_PORT`, `DB_CHARSET` theo quy chuẩn `PROJECT.md:75`.

2. **Tệp `includes/db.php` (tổng cộng 127 dòng)**:
   - Dòng 4–35: Hàm `db(): ?PDO` sử dụng cấu trúc `if (DB_DRIVER === 'sqlite')` (dòng 10–18) để tự động khởi tạo kết nối SQLite và gọi `initSQLite($pdo)`.
   - Dòng 30–33: Bắt `PDOException`, gán `$GLOBALS['db_error'] = $e->getMessage();` và trả về `null` (kiểu trả về `?PDO`), trong khi hợp đồng `PROJECT.md:51` quy định `function db(): PDO`.
   - Dòng 42–61: Hàm `logAction(string $action, string $details = ''): void` chỉ nhận 2 tham số, tự động lấy `$userId = $_SESSION['user_id'] ?? null;` và `$username = $_SESSION['username'] ?? 'Khách';`.
     - Tại `login.php:33`, khi đăng nhập sai tài khoản `admin`, việc gọi `logAction('Đăng nhập thất bại', ...)` dẫn đến ghi nhận sai thông tin danh tính (lấy tên của session trước đó thay vì ghi nhận tài khoản vừa nhập).
     - Toàn bộ dự án hiện có **10 vị trí** đang gọi trực tiếp hàm `logAction($action, $details)`:
       - `login.php`: dòng 23, 33
       - `logout.php`: dòng 6
       - `admin.php`: dòng 8, 51, 85, 105, 127
       - `nhap_diem.php`: dòng 105, 164
   - Dòng 63–126: Hàm `initSQLite(PDO $pdo): void` dài 64 dòng, chứa toàn bộ DDL SQLite và chèn dữ liệu mẫu SQLite.

3. **Tệp `database.sqlite`**:
   - Vị trí vật lý: `D:\New folder\my-web-project\database.sqlite`
   - Kích thước: 40,960 bytes (40 KB).
   - Kiểm tra lệnh `git status`: Thư mục không phải là git repository (`fatal: not a git repository`).

4. **Bảng `audit_logs` trên MySQL `edu_radar` thực tế**:
   - Lệnh kiểm tra: `D:\xam\mysql\bin\mysql.exe -u root -e "DESCRIBE audit_logs;" edu_radar`
   - Cấu trúc cột quan sát được:
     - `id`: `int(11) NOT NULL PRIMARY KEY auto_increment`
     - `user_id`: `int(11) YES NULL`
     - `username`: `varchar(50) YES NULL`
     - `action`: `varchar(100) NOT NULL`
     - `details`: `text YES NULL`
     - `ip_address`: `varchar(45) YES NULL`
     - `created_at`: `datetime NOT NULL`
   - Dữ liệu thực tế có chứa các bản ghi do hệ thống cũ ghi nhận (`127.0.0.1` và `::1`).

---

## 2. Logic Chain (Chuỗi Lập Luận)

1. **Từ Quan sát 1 & 2 $\to$ Loại bỏ hoàn toàn SQLite**:
   - `SQLITE_FILE` và nhánh `if (DB_DRIVER === 'sqlite')` cùng hàm `initSQLite()` là nguyên nhân duy nhất cho phép hệ thống sử dụng hoặc tự động sinh ra file `database.sqlite`.
   - Do yêu cầu cốt lõi R1 bắt buộc loại bỏ 100% mã SQLite, việc xóa `SQLITE_FILE`, `DB_DRIVER` khỏi `includes/config.php` và xóa nhánh rẽ cùng hàm `initSQLite()` khỏi `includes/db.php` sẽ ngắt hoàn toàn khả năng tương tác với SQLite.

2. **Từ Quan sát 2 $\to$ Chuẩn hóa `db(): PDO`**:
   - Kết nối MySQL hiện tại đã thiết lập `utf8mb4` trên DSN và `PDO::ERRMODE_EXCEPTION`, `PDO::FETCH_ASSOC`.
   - Để đạt chuẩn sản phẩm cao nhất, cần bổ sung `PDO::ATTR_EMULATE_PREPARES => false` (sử dụng prepared statement thực sự ở phía MySQL server nhằm ngăn chặn triệt để SQL Injection và bảo toàn kiểu dữ liệu) và `SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci`.
   - Về xử lý lỗi: Ném ngoại lệ `PDOException` thay vì trả về `null` đảm bảo tuân thủ kiểu trả về `PDO` của `PROJECT.md:51` và ngăn lỗi `Call to a member function prepare() on null` làm hỏng luồng nghiệp vụ không rõ nguyên nhân.

3. **Từ Quan sát 2 & 4 $\to$ Thiết kế `log_action()` và Wrapper `logAction()`**:
   - Yêu cầu đặt ra hàm mới: `log_action(?int $user_id, ?string $username, string $action, string $details): void`.
   - Khi `$user_id` và `$username` được truyền tường minh (ví dụ: đăng nhập thành công truyền `id`, đăng nhập thất bại truyền `null` và tên đăng nhập nhập vào), log phản ánh chính xác ngữ cảnh sự kiện.
   - Khi truyền `null`, hàm tự động phân giải từ `$_SESSION` để dự phòng.
   - Vì 10 vị trí mã nguồn trong `admin.php`, `login.php`, `nhap_diem.php`, `logout.php` đang gọi `logAction($action, $details)`, nếu xóa `logAction()` ngay tại Milestone 1 thì toàn bộ ứng dụng sẽ bị crash ngay lập tức.
   - Do đó, giải pháp bắt buộc là: Triển khai hàm mới `log_action()`, đồng thời cung cấp hàm wrapper `logAction(string $action, string $details = ''): void` để chuyển tiếp lời gọi sang `log_action()`. Khi các Milestone tiếp theo refactor các trang, các lời gọi cũ sẽ được nâng cấp dần sang `log_action()`.

4. **Từ Quan sát 1 & 4 $\to$ Trích xuất IP khách an toàn**:
   - Cột `ip_address` trong MySQL có độ dài `VARCHAR(45)`.
   - Cần kiểm tra `HTTP_X_FORWARDED_FOR` (có qua `filter_var(..., FILTER_VALIDATE_IP)` để tránh header injection), sau đó fallback sang `$_SERVER['REMOTE_ADDR']`, và cuối cùng fallback sang `'127.0.0.1'` khi chạy qua PHP CLI (`tests/run_tests.php`).
   - Cắt chuỗi với `substr($ip, 0, 45)` bảo đảm không bao giờ văng lỗi độ dài cột MySQL.

5. **Từ Quan sát 3 $\to$ Quy trình xóa `database.sqlite`**:
   - Cần xóa file vật lý sau khi mã nguồn đã được gỡ bỏ hết các liên kết tới SQLite, ngăn chặn mọi hành vi tái tạo file.

---

## 3. Caveats (Lưu Ý & Giới Hạn)

1. **Tương thích ngược của mã gọi `logAction`**:
   - Các file hiện tại như `login.php:33` truyền chi tiết thất bại vào nội dung text `details` qua `logAction(...)`. Việc sửa trực tiếp `login.php`, `admin.php` thuộc phạm vi của Milestone 2 và 3. Việc giữ lại wrapper `logAction()` là phương án bảo toàn trạng thái an toàn tuyệt đối cho Milestone 1.
2. **Phiên bản PHP và Driver**:
   - Môi trường là PHP 8.2 trên XAMPP (`D:\xam\php\php.exe`), extension `pdo_mysql` đã được kích hoạt sẵn. Không phụ thuộc vào bất kỳ Composer package hay extension ngoài nào.
3. **Quyền ghi file trên Windows**:
   - Lệnh xóa `database.sqlite` yêu cầu tiến trình PHP hoặc PowerShell có quyền xóa tệp trong thư mục `D:\New folder\my-web-project\`. Cần đảm bảo không có tiến trình nào (như SQLite GUI tools hoặc editor) đang lock file này.

---

## 4. Conclusion (Kết Luận & Giải Pháp Hành Động)

1. **`includes/config.php`**:
   - Thay thế toàn bộ bằng phiên bản sạch chỉ chứa: `BASE_URL` (`/my-web-project/`), `DB_HOST`, `DB_PORT` (3306), `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET` (`utf8mb4`), `APP_NAME` (`EduRadar`), và `PASS_SCORE` (5.0).
   - Xóa bỏ triệt để `SQLITE_FILE`, `DB_DRIVER`, `ABSENCE_RED`.
2. **`includes/db.php`**:
   - Thay thế toàn bộ bằng phiên bản thuần MySQL với hàm `db(): PDO` (singleton, charset utf8mb4, `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `EMULATE_PREPARES => false`).
   - Xóa bỏ hoàn toàn nhánh SQLite và 64 dòng của hàm `initSQLite()`.
   - Cài đặt hàm chuẩn `log_action(?int $user_id, ?string $username, string $action, string $details = ''): void` với chuẩn trích xuất IP an toàn và prepared statement.
   - Cài đặt hàm wrapper tương thích ngược `logAction(string $action, string $details = ''): void`.
3. **Xóa `database.sqlite`**:
   - Thực thi xóa tập tin vật lý `database.sqlite` tại thư mục gốc dự án.

Mã nguồn hoàn chỉnh được đề xuất đã được đặc tả chi tiết trong tệp `D:\New folder\my-web-project\.agents\teamwork\explorer_m1_2\analysis.md`.

---

## 5. Verification Method (Phương Pháp Kiểm Chứng Độc Lập)

Kỹ sư triển khai hoặc Sentinel có thể kiểm chứng toàn bộ giải pháp độc lập bằng các câu lệnh sau:

### Bước 1: Kiểm tra cú pháp PHP (PHP Linting)
```powershell
D:\xam\php\php.exe -l "D:\New folder\my-web-project\includes\config.php"
D:\xam\php\php.exe -l "D:\New folder\my-web-project\includes\db.php"
```
*Điều kiện đạt*: Cả hai lệnh trả về `No syntax errors detected`.

### Bước 2: Kiểm tra kết nối PDO MySQL thuần túy
```powershell
D:\xam\php\php.exe -r "require_once 'D:/New folder/my-web-project/includes/db.php'; $p = db(); echo 'Connected to: ' . $p->query('SELECT DATABASE()')->fetchColumn() . ' | Charset: ' . $p->query('SELECT @@character_set_connection')->fetchColumn() . PHP_EOL;"
```
*Điều kiện đạt*: In ra `Connected to: edu_radar | Charset: utf8mb4`.

### Bước 3: Kiểm tra helper `log_action` và wrapper `logAction`
```powershell
D:\xam\php\php.exe -r "require_once 'D:/New folder/my-web-project/includes/db.php'; log_action(1, 'admin', 'M1_TEST_LOG', 'Test helper log_action'); logAction('M1_TEST_WRAPPER', 'Test wrapper logAction');"
D:\xam\mysql\bin\mysql.exe -u root -e "SELECT id, user_id, username, action, ip_address, created_at FROM audit_logs WHERE action LIKE 'M1_TEST%' ORDER BY id DESC LIMIT 2;" edu_radar
```
*Điều kiện đạt*: Hai dòng log xuất hiện trong MySQL với `ip_address = '127.0.0.1'`, `user_id` và `username` khớp chính xác, thời gian `created_at` đúng định dạng.

### Bước 4: Kiểm tra sự biến mất của SQLite
```powershell
D:\xam\php\php.exe -r "echo file_exists('D:/New folder/my-web-project/database.sqlite') ? 'FAIL: SQLite file still exists' : 'PASS: SQLite file deleted cleanly';"
```
*Điều kiện đạt*: In ra `PASS: SQLite file deleted cleanly`.
