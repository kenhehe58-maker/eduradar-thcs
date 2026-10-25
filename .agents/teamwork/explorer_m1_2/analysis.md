# THIẾT KẾ TÁI CẤU TRÚC: KẾT NỐI DATABASE MYSQL THUẦN TÚY & HELPER GHI NHẬT KÝ (M1)

**Tác giả**: `explorer_m1_2`  
**Vai trò**: Điều tra viên & Thiết kế Kiến trúc (Milestone 1)  
**Mục tiêu**: Loại bỏ triệt để SQLite, thiết lập kết nối PDO MySQL thuần túy (`includes/db.php`, `includes/config.php`), chuẩn hóa hàm ghi nhật ký `log_action()`, và thiết lập quy trình xóa sạch `database.sqlite`.  
**Môi trường thực thi**: Windows, XAMPP Apache (`http://localhost/my-web-project/`), MariaDB/MySQL 10.4 (`edu_radar`), PHP 8.2+ (`D:\xam\php\php.exe`).

---

## 1. Hiện Trạng & Phân Tích Mã Nguồn Hiện Tại

### 1.1. Tệp `includes/config.php` Hiện Tại
```php
<?php
// Cấu hình Database: KẾT NỐI TRỰC TIẾP VÀO MYSQL (XAMPP / MARIADB)

define('DB_DRIVER', 'mysql'); // 'mysql' (XAMPP)

// Cấu hình MySQL
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'edu_radar');
define('DB_USER', 'root');
define('DB_PASS', '');

// File SQLite dự phòng
define('SQLITE_FILE', __DIR__ . '/../database.sqlite');

define('APP_NAME', 'EduRadar');

// Ngưỡng cảnh báo sư phạm
define('PASS_SCORE', 5.0);
define('ABSENCE_RED', 3);
```

#### Các vấn đề phát hiện:
1. **Dấu tích SQLite (`SQLITE_FILE`)**: Dòng 13 định nghĩa hằng số trỏ tới file `database.sqlite`. Theo yêu cầu R1 (`ORIGINAL_REQUEST.md`), hệ thống chuyển đổi hoàn toàn sang MySQL, cấm dùng SQLite dự phòng. Hằng số này vi phạm nguyên tắc "Zero SQLite".
2. **Hằng số `DB_DRIVER`**: Chỉ được dùng duy nhất tại `includes/db.php:10` để rẽ nhánh sang SQLite. Toàn bộ mã nguồn không còn nơi nào sử dụng. Việc giữ lại `DB_DRIVER` tạo ấn tượng sai lệch rằng hệ thống hỗ trợ đa cơ sở dữ liệu.
3. **Hằng số thừa `ABSENCE_RED`**: Dự án là sổ điểm VnEdu THCS và radar cảnh báo sớm học tập, không có chức năng điểm danh hay vắng học. Hằng số này là mã rác còn sót lại từ bản mẫu cũ.
4. **Thiếu `BASE_URL`**: Theo đặc tả `PROJECT.md:75`, `config.php` cần cung cấp `BASE_URL` (`/my-web-project/`) để các trang điều hướng và tải tài nguyên web tĩnh nhất quán khi chạy trên XAMPP Apache.
5. **Thiếu hằng số cấu hình cổng và bảng mã**: Cần bổ sung `DB_PORT` (`3306`) và `DB_CHARSET` (`utf8mb4`) để bảo đảm cấu hình tường minh.

---

### 1.2. Tệp `includes/db.php` Hiện Tại
```php
<?php
require_once __DIR__ . '/config.php';

function db(): ?PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    try {
        if (DB_DRIVER === 'sqlite') {
            $isNew = !file_exists(SQLITE_FILE);
            $pdo = new PDO('sqlite:' . SQLITE_FILE);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            if ($isNew) {
                initSQLite($pdo);
            }
        } else {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        }
    } catch (PDOException $e) {
        $GLOBALS['db_error'] = $e->getMessage();
        $pdo = null;
    }
    return $pdo;
}
...
function logAction(string $action, string $details = ''): void { ... }
function initSQLite(PDO $pdo): void { ... }
```

#### Các vấn đề phát hiện:
1. **Khối rẽ nhánh SQLite**: Dòng 10–18 chứa logic kiểm tra `DB_DRIVER === 'sqlite'` và tạo kết nối SQLite.
2. **Hàm `initSQLite(PDO $pdo)`**: Dòng 63–126 chứa 64 dòng DDL và chèn dữ liệu mẫu SQLite. Toàn bộ đoạn mã này hoàn toàn vô dụng khi đã chuyển sang MySQL, gây lãng phí bộ nhớ và tiềm ẩn rủi ro tự động sinh lại file SQLite nếu ai đó đổi biến môi trường.
3. **Kiểu trả về của hàm `db()`**: Hàm hiện tại khai báo `function db(): ?PDO` và bắt `PDOException` rồi trả về `null`. Trong khi đó, `PROJECT.md:51` quy định `function db(): PDO` (trả về đối tượng PDO đơn thực thể - singleton, ném ngoại lệ nếu thất bại để tránh lỗi `Call to a member function prepare() on null` lan truyền).
4. **Hàm ghi log `logAction()` hiện tại**:
   - Chữ ký hàm hiện tại: `logAction(string $action, string $details = ''): void`
   - Hàm tự động lấy `$_SESSION['user_id']` và `$_SESSION['username']`.
   - **Hạn chế nghiêm trọng**:
     - Khi đăng nhập thất bại tại `login.php:33`, người dùng chưa đăng nhập, session chưa có hoặc đang lưu session của người khác. Khi đó, log ghi nhận sai (ví dụ: `gv_toan` thử đăng nhập mật khẩu sai tài khoản `admin` thì log ghi `user_id = 2, username = 'gv_toan'` thay vì ghi `user_id = NULL, username = 'admin'`).
     - Khi chạy kiểm thử tự động qua PHP CLI (`tests/run_tests.php`), không có session web, hàm không cho phép truyền trực tiếp `user_id` và `username`.
     - Đặc tả `PROJECT.md:52` và `DISPATCH.md` yêu cầu hàm mới:
       `function log_action(?int $user_id, ?string $username, string $action, string $details): void`
   - **Vấn đề tương thích ngược**: Các file hiện tại (`admin.php`, `login.php`, `nhap_diem.php`, `logout.php`) đang gọi `logAction($action, $details)`. Nếu xóa ngay hàm này ở Milestone 1 trước khi Milestone 2–5 được viết lại, hệ thống sẽ gặp lỗi `Fatal error: Call to undefined function logAction()`. Do đó, cần có hàm bao bọc (backward compatibility wrapper) giữ lại `logAction()` để ủy quyền cho `log_action()`.
5. **Trích xuất IP khách (`ip_address`)**:
   - Hiện tại: `$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';`
   - Khi chạy qua PHP CLI, `$_SERVER['REMOTE_ADDR']` không tồn tại. Dùng toán tử `??` giúp tránh cảnh báo `Undefined array key`, nhưng cần chuẩn hóa cắt chuỗi tối đa 45 ký tự để khớp với độ dài cột `VARCHAR(45)` của bảng `audit_logs`.

---

### 1.3. Khảo Sát Tệp `database.sqlite`
- Đường dẫn: `D:\New folder\my-web-project\database.sqlite`
- Kích thước: 40,960 bytes (40 KB).
- Loại tệp: SQLite 3 format 3.
- Trạng thái Git: Thư mục dự án không phải là Git repository cục bộ (`fatal: not a git repository`).
- Tác động khi xóa: Không ảnh hưởng đến dữ liệu MariaDB MySQL `edu_radar` vì MariaDB lưu trữ độc lập trong `D:\xam\mysql\data\edu_radar\`.

---

## 2. Thiết Kế Chi Tiết & Đề Xuất Mã Nguồn Mới

### 2.1. Đề Xuất Cho `includes/config.php`

Tệp cấu hình mới sẽ loại bỏ hoàn toàn `SQLITE_FILE`, `DB_DRIVER`, `ABSENCE_RED`, và bổ sung `BASE_URL`, `DB_PORT`, `DB_CHARSET`:

```php
<?php
// =============================================================================
// Hệ Thống Quản Lý Điểm & Cảnh Báo Sớm THCS (EduRadar / Chuẩn VnEdu)
// Cấu hình ứng dụng và kết nối MySQL (XAMPP / MariaDB 10.4)
// Không sử dụng SQLite (Pure MySQL Architecture)
// =============================================================================

// Đường dẫn gốc ứng dụng trên XAMPP Apache
define('BASE_URL', '/my-web-project/');

// Cấu hình kết nối MySQL
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'edu_radar');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Tên định danh ứng dụng
define('APP_NAME', 'EduRadar');

// Ngưỡng cảnh báo học tập theo chuẩn THCS
define('PASS_SCORE', 5.0);
```

#### Phân tích quyết định thiết kế:
- Giữ nguyên các hằng số MySQL cốt lõi (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`) để đảm bảo không phá vỡ bất kỳ câu lệnh kết nối nào.
- Xóa bỏ `SQLITE_FILE` và `DB_DRIVER` ngăn chặn mọi hành vi tham chiếu tới driver SQLite.
- Giữ lại `PASS_SCORE = 5.0` vì được sử dụng trực tiếp tại `index.php:32,37,43`.
- Bổ sung `BASE_URL = '/my-web-project/'` theo đúng hợp đồng thiết kế tại `PROJECT.md:75`.

---

### 2.2. Đề Xuất Cho `includes/db.php`

Tệp `includes/db.php` mới đảm nhiệm 2 nhiệm vụ cốt lõi:
1. Quản lý kết nối PDO MySQL đơn thực thể (Singleton Pattern) với bảng mã `utf8mb4`, chế độ báo lỗi ngoại lệ (`PDO::ERRMODE_EXCEPTION`), và kiểu lấy dữ liệu mặc định mảng kết hợp (`PDO::FETCH_ASSOC`).
2. Cung cấp helper ghi nhật ký kiểm toán `log_action()` chuẩn xác, an toàn trước SQL Injection, kèm theo wrapper `logAction()` tương thích ngược.

```php
<?php
// =============================================================================
// Hệ Thống Quản Lý Điểm & Cảnh Báo Sớm THCS (EduRadar / Chuẩn VnEdu)
// Quản trị kết nối cơ sở dữ liệu MySQL (PDO) & Ghi nhật ký hệ thống (Audit Log)
// =============================================================================

require_once __DIR__ . '/config.php';

/**
 * Trả về kết nối PDO MySQL đơn thực thể (Singleton)
 * Cấu hình bắt buộc:
 * - Charset: utf8mb4
 * - Error Mode: PDO::ERRMODE_EXCEPTION
 * - Default Fetch Mode: PDO::FETCH_ASSOC
 * - Emulate Prepares: false (chuẩn bị truy vấn thực trên server MySQL)
 *
 * @throws PDOException Khi không thể kết nối tới cơ sở dữ liệu MySQL
 * @return PDO
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci",
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        $GLOBALS['db_error'] = $e->getMessage();
        throw new PDOException("Lỗi kết nối cơ sở dữ liệu MySQL: " . $e->getMessage(), (int)$e->getCode(), $e);
    }
}

/**
 * Hàm ghi nhật ký hành động vào bảng audit_logs (MySQL)
 *
 * @param int|null    $user_id   ID người dùng thực hiện (null nếu khách hoặc đăng nhập thất bại)
 * @param string|null $username  Tên đăng nhập (tự lấy từ session hoặc fallback 'Khách' nếu để null)
 * @param string      $action    Tên hành động (Đăng nhập, Lưu sổ điểm, Phân công giáo viên...)
 * @param string      $details   Chi tiết nội dung hành động
 * @return void
 */
function log_action(?int $user_id, ?string $username, string $action, string $details = ''): void
{
    try {
        $pdo = db();

        // Tự động phân giải danh tính từ session nếu không được truyền tường minh
        if ($user_id === null && $username === null) {
            $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            $username = $_SESSION['username'] ?? 'Khách';
        } elseif ($username === null || $username === '') {
            $username = $_SESSION['username'] ?? 'Khách';
        }

        // Trích xuất địa chỉ IP an toàn (hỗ trợ cả môi trường Web và PHP CLI)
        $ip = '127.0.0.1';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwarded = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
            $forwarded = trim($forwarded);
            if (filter_var($forwarded, FILTER_VALIDATE_IP)) {
                $ip = $forwarded;
            }
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        $ip = substr($ip, 0, 45);

        $now = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, username, action, details, ip_address, created_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $username, $action, $details, $ip, $now]);
    } catch (\Throwable $e) {
        // Ghi log lỗi nội bộ PHP, không làm gián đoạn luồng nghiệp vụ chính của người dùng
        error_log('Audit log error: ' . $e->getMessage());
    }
}

/**
 * Hàm tương thích ngược với mã nguồn cũ đang gọi logAction($action, $details)
 * Tự động phân giải session và chuyển tiếp sang hàm log_action()
 *
 * @param string $action  Tên hành động
 * @param string $details Chi tiết hành động
 * @return void
 */
function logAction(string $action, string $details = ''): void
{
    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $username = $_SESSION['username'] ?? 'Khách';
    log_action($userId, $username, $action, $details);
}
```

---

## 3. Phân Tích Logic & Giải Quyết Các Trường Hợp Biên (Edge Cases)

### 3.1. Phân Tích Trích Xuất Địa Chỉ IP Khách (`ip_address`)
- **Môi trường Web trực tiếp (Localhost XAMPP)**:
  - Apache gán `$_SERVER['REMOTE_ADDR'] = '::1'` hoặc `'127.0.0.1'`. Cả hai giá trị đều hợp lệ và được lưu trực tiếp vào trường `VARCHAR(45)`.
- **Môi trường chạy qua Proxy / Cổng đảo chiều (Reverse Proxy)**:
  - Nếu có header `HTTP_X_FORWARDED_FOR`, lấy địa chỉ IP đầu tiên trong danh sách (`explode(',', ...)[0]`).
  - **Biện pháp bảo vệ**: Dùng `filter_var($ip, FILTER_VALIDATE_IP)` để ngăn chặn tấn công giả mạo header hoặc chèn ký tự nguy hiểm qua HTTP headers.
- **Môi trường PHP CLI (`tests/run_tests.php`)**:
  - Khi thực thi lệnh bằng CLI, `$_SERVER['REMOTE_ADDR']` không được thiết lập.
  - Sử dụng fallback mặc định `'127.0.0.1'` giúp tránh cảnh báo `E_WARNING: Undefined array key "REMOTE_ADDR"`.
- **Giới hạn độ dài**: Sử dụng `substr($ip, 0, 45)` để đảm bảo không bao giờ vượt quá dung lượng cột `VARCHAR(45)` của bảng `audit_logs` trong MySQL.

### 3.2. Phân Tích Xử Lý Danh Tính Trong `log_action()`
Bảng đối chiếu các tình huống ghi nhận nhật ký:

| Tình huống thực tế | Tham số gọi vào | Kết quả `user_id` ghi vào CSDL | Kết quả `username` ghi vào CSDL | Ghi chú |
|---|---|---|---|---|
| Đăng nhập thành công (`login.php`) | `log_action($u['id'], $u['username'], 'Đăng nhập thành công', ...)` | `1` (hoặc ID tương ứng) | `'admin'` | Chính xác danh tính vừa xác thực |
| Đăng nhập thất bại do sai mật khẩu | `log_action(null, $username, 'Đăng nhập thất bại', ...)` | `NULL` | `'admin'` (tên đã nhập) | Không bị gán nhầm ID của session cũ |
| Khách chưa đăng nhập truy cập trái phép `admin.php` | `log_action(null, null, 'Truy cập trái phép bị chặn', ...)` | `NULL` | `'Khách'` | Nhận diện là khách vãng lai |
| Giáo viên (`gv_toan`) cố tình vào `admin.php` | `log_action(2, 'gv_toan', 'Truy cập trái phép bị chặn', ...)` hoặc gọi qua wrapper `logAction(...)` | `2` | `'gv_toan'` | Ghi nhận rõ giáo viên vi phạm |
| Giáo viên lưu điểm hoặc import file điểm | Gọi qua `log_action(...)` hoặc `logAction(...)` | `$_SESSION['user_id']` | `$_SESSION['username']` | Đầy đủ thông tin người thực hiện |

### 3.3. Tính Không Gây Gián Đoạn (Non-blocking Invariant)
Toàn bộ thân hàm `log_action()` được bao bọc trong `try { ... } catch (\Throwable $e)`. Nếu có lỗi kết nối đột xuất, lỗi khóa ngoại hoặc lỗi bảng, hành động nghiệp vụ chính của người dùng (ví dụ: giáo viên đang nhập điểm cho 40 học sinh) sẽ **không bị crash hay mất dữ liệu**, đảm bảo tính khả dụng cao nhất của hệ thống.

---

## 4. Kế Hoạch Xóa Sạch `database.sqlite` & Đảm Bảo Không Tái Sinh

### 4.1. Quy Trình 3 Bước Xóa Sạch:
1. **Bước 1: Cắt đứt hoàn toàn mã nguồn (Code Decoupling)**:
   - Cập nhật `includes/config.php` (xóa `SQLITE_FILE`).
   - Cập nhật `includes/db.php` (xóa nhánh `if (DB_DRIVER === 'sqlite')` và xóa hàm `initSQLite()`).
   - Đảm bảo không còn bất kỳ dòng code PHP nào có khả năng mở kết nối tới SQLite hoặc tự động tạo bảng SQLite.
2. **Bước 2: Xóa tập tin vật lý trên ổ đĩa (Physical File Deletion)**:
   - Thực thi xóa tập tin `D:\New folder\my-web-project\database.sqlite`.
   - Lệnh PowerShell:
     ```powershell
     Remove-Item -Path "D:\New folder\my-web-project\database.sqlite" -Force
     ```
   - Hoặc bằng PHP:
     ```php
     if (file_exists('D:/New folder/my-web-project/database.sqlite')) {
         unlink('D:/New folder/my-web-project/database.sqlite');
     }
     ```
3. **Bước 3: Kiểm chứng độc lập & Chặn tái sinh (Verification & Regression Guard)**:
   - Kiểm tra bằng lệnh tìm kiếm hệ thống: Không còn tập tin đuôi `.sqlite` nào trong toàn bộ thư mục dự án.
   - Bổ sung assert trong bộ kiểm thử `tests/run_tests.php`:
     ```php
     assert(!file_exists(__DIR__ . '/../database.sqlite'), 'Tập tin database.sqlite không được phép tồn tại!');
     ```

---

## 5. Quy Trình Kiểm Thử & Kiểm Chứng Độc Lập

### 5.1. Kiểm tra Cú Pháp PHP (Syntax Linting)
```powershell
D:\xam\php\php.exe -l "D:\New folder\my-web-project\includes\config.php"
D:\xam\php\php.exe -l "D:\New folder\my-web-project\includes\db.php"
```
**Kỳ vọng**: `No syntax errors detected in ...`

### 5.2. Kiểm tra Kết Nối MySQL Qua CLI
```powershell
D:\xam\php\php.exe -r "require_once 'D:/New folder/my-web-project/includes/db.php'; $pdo = db(); echo 'MySQL connected successfully! Server version: ' . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . PHP_EOL;"
```
**Kỳ vọng**: In ra phiên bản MariaDB/MySQL (ví dụ: `10.4.32-MariaDB`).

### 5.3. Kiểm tra Helper `log_action()` và Wrapper `logAction()`
```powershell
D:\xam\php\php.exe -r "require_once 'D:/New folder/my-web-project/includes/db.php'; log_action(1, 'admin', 'M1_TEST_LOG', 'Test helper log_action thuần MySQL'); logAction('M1_TEST_WRAPPER', 'Test wrapper tương thích'); echo 'Audit logs written successfully!' . PHP_EOL;"
```
Sau đó kiểm tra bản ghi trong MySQL:
```powershell
D:\xam\mysql\bin\mysql.exe -u root -e "SELECT id, user_id, username, action, ip_address, created_at FROM audit_logs WHERE action LIKE 'M1_TEST%' ORDER BY id DESC LIMIT 2;" edu_radar
```
**Kỳ vọng**: Cả hai bản ghi xuất hiện với đầy đủ `ip_address = '127.0.0.1'`, `created_at` chính xác, không có lỗi.

### 5.4. Kiểm tra Xóa Tập Tin SQLite
```powershell
D:\xam\php\php.exe -r "echo file_exists('D:/New folder/my-web-project/database.sqlite') ? 'STILL_EXISTS' : 'DELETED_CLEANLY';"
```
**Kỳ vọng**: In ra `DELETED_CLEANLY`.
