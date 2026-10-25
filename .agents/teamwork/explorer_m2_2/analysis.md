# Phân Tích Kỹ Thuật: Thiết Kế Middleware RBAC (`includes/auth.php`) & Bảo Vệ Tuyến Quản Trị (`admin.php`)

- **Agent**: explorer_m2_2
- **Milestone**: M2 (Auth & Strict RBAC)
- **Working Directory**: `D:\New folder\my-web-project\.agents\teamwork\explorer_m2_2`
- **Date**: 2026-10-17

---

## 1. Tóm Tắt Tổng Quan (Executive Summary)

Nhiệm vụ của Milestone 2 đặt trọng tâm vào việc thiết lập lớp kiểm soát truy cập dựa trên vai trò (Role-Based Access Control - RBAC) tập trung và loại bỏ hoàn toàn các lỗ hổng bypass, BOLA/IDOR, và phân quyền lỏng lẻo.

Qua khảo sát thực tế mã nguồn:
1. **Thiếu Middleware xác thực tập trung**: Tệp `includes/auth.php` hiện **chưa tồn tại**. Các trang (`admin.php`, `nhap_diem.php`, `index.php`) đang tự viết các đoạn kiểm tra `$_SESSION` phân tán, thiếu tính nhất quán và không có cơ chế chuẩn hóa ghi nhật ký an ninh.
2. **`admin.php` kiểm tra quyền thủ công và tồn tại rủi ro**:
   - Dòng 6–16 hiện dùng khối `if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin')` cục bộ, gọi hàm wrapper cũ `logAction()` thay vì `log_action()`, thông điệp nhật ký sơ sài, thiếu ngữ cảnh chi tiết (không ghi IP, URI, method, username).
   - Các khối xử lý POST/GET (`batch_assign`, `import_students`, `delete_assign`, `impersonate`) không có lớp bảo vệ phòng thủ chiều sâu (defense-in-depth). Nếu luồng thực thi bị chia tách hoặc trang bị tái cấu trúc, các hành động ghi dữ liệu có nguy cơ bị kích hoạt ngoài ý muốn.
   - Một số truy vấn phụ trợ trong `admin.php` (dòng 44, 83, 104, 122) vẫn còn nối chuỗi trực tiếp `$cid`, `$target_class_id`, `$aid` thay vì dùng Prepared Statements chuẩn.
3. **Mục tiêu giải quyết**:
   - Thiết kế và cung cấp mã nguồn hoàn chỉnh cho `includes/auth.php` đáp ứng 100% hợp đồng kỹ thuật tại `PROJECT.md`: `require_login()`, `require_admin()`, `current_user()`, `verify_teacher_assignment()`, `require_teacher_assignment()`.
   - Chuẩn hóa cơ chế chặn HTTP 403, tự động ghi nhật ký kiểm toán vào bảng `audit_logs` với hành động `'Truy cập trái phép bị chặn'` kèm thông tin định danh đầy đủ.
   - Cung cấp bản vá chi tiết (unified diff) để làm sạch và gia cố toàn diện `admin.php`, đảm bảo người dùng chưa đăng nhập hoặc giáo viên (`gv_toan`) khi cố tình truy cập hay gửi POST đến `admin.php` đều nhận mã trạng thái **HTTP 403 Forbidden** và tuyệt đối không làm thay đổi bất kỳ bản ghi nào trong cơ sở dữ liệu.

---

## 2. Thiết Kế Chi Tiết Middleware `includes/auth.php`

### 2.1 Nguyên tắc thiết kế (Core Design Principles)
- **Tự khởi động session an toàn**: Kiểm tra `session_status() === PHP_SESSION_NONE` trước khi gọi `session_start()` để tránh warning notice trên PHP 8.0.
- **Phụ thuộc tối thiểu, đúng trách nhiệm**: Tự động import `includes/db.php` (đã bao hàm `includes/config.php`), đảm bảo kết nối PDO MySQL và hàm `log_action()` luôn sẵn sàng.
- **Fail-Safe & Zero-Mutation**: Khi phát hiện truy cập trái phép, hàm bảo vệ lập tức phát sinh HTTP 403 (hoặc chuyển hướng đăng nhập với `require_login`), ghi nhật ký an ninh và gọi `exit;` ngay lập tức để ngăn chặn triệt để mọi hành vi ghi/xóa dữ liệu.
- **Ghi nhật ký chuẩn hóa**: Mọi vi phạm quyền hạn đều kích hoạt `log_action()` với action `'Truy cập trái phép bị chặn'` và details ghi rõ: tài khoản, ID, role thực tế, URI đích, phương thức HTTP (GET/POST) và lý do từ chối.

### 2.2 Đặc tả các hàm trong `includes/auth.php`

#### A. `require_login(): void`
- **Mục đích**: Bảo vệ các trang nội bộ yêu cầu người dùng phải xác thực danh tính.
- **Cơ chế**:
  - Kiểm tra `isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])`.
  - Nếu chưa đăng nhập: chuyển hướng về `BASE_URL . 'login.php'` bằng header `Location:` và gọi `exit;`.
  - Nếu đã đăng nhập: tiếp tục thực thi.

#### B. `current_user(bool $refreshFromDb = false): ?array`
- **Mục đích**: Trả về thông tin người dùng hiện tại đang đăng nhập dưới dạng mảng kết hợp chuẩn `['id', 'username', 'full_name', 'role']`, hoặc `null` nếu là khách.
- **Cơ chế**:
  - Mặc định đọc trực tiếp từ `$_SESSION` với tốc độ tức thì (zero DB roundtrip).
  - Tùy chọn `$refreshFromDb = true` hỗ trợ đồng bộ dữ liệu người dùng mới nhất từ bảng `users` trong MySQL nếu cần cập nhật thay đổi tức thời.

#### C. `require_admin(): void`
- **Mục đích**: Tuyến phòng thủ nghiêm ngặt dành riêng cho quản trị viên nhà trường (`admin.php` và các API/action quản trị).
- **Cơ chế**:
  - Kiểm tra đồng thời: đã đăng nhập (`isset($_SESSION['user_id'])`) và vai trò quản trị (`$_SESSION['role'] === 'admin'`).
  - Nếu không thỏa mãn:
    1. Thiết lập mã trạng thái: `http_response_code(403);`.
    2. Trích xuất danh tính: `$userId`, `$username`, `$role`, `$method = $_SERVER['REQUEST_METHOD']`, `$uri = $_SERVER['REQUEST_URI']`.
    3. Ghi nhật ký vào `audit_logs` thông qua `log_action($userId, $username, 'Truy cập trái phép bị chặn', ...)`.
    4. Xuất giao diện cảnh báo lỗi 403 thuần tiếng Việt chuẩn UX Stitch UI và gọi `exit;`.

#### D. `verify_teacher_assignment(int $teacher_id, int $class_id, string $subject): bool`
- **Mục đích**: Hàm kiểm tra quan hệ phân công giảng dạy trong bảng `teacher_classes`.
- **Cơ chế**:
  - Kiểm tra tính hợp lệ của tham số: `$teacher_id > 0`, `$class_id > 0`, `$subject !== ''`.
  - Thực hiện truy vấn MySQL tối ưu:
    ```sql
    SELECT 1 FROM teacher_classes WHERE teacher_id = ? AND class_id = ? AND subject = ? LIMIT 1
    ```
  - Tận dụng khóa chỉ mục `UNIQUE KEY unique_assignment (teacher_id, class_id, subject)` để đạt độ trễ cực thấp.
  - Trả về `true` nếu tồn tại phân công, ngược lại trả về `false`.

#### E. `require_teacher_assignment(int $teacher_id, int $class_id, string $subject): void`
- **Mục đích**: Chặn đứng triệt để lỗ hổng BOLA/IDOR trên trang nhập điểm `nhap_diem.php`.
- **Cơ chế**:
  - Gọi `require_login()`.
  - **Đặc quyền Admin**: Nếu `$_SESSION['role'] === 'admin'`, cho phép bỏ qua kiểm tra vì Admin có toàn quyền kiểm tra/nhập điểm thay mặt bất kỳ giáo viên nào (theo yêu cầu R1, R2, R3).
  - **Giáo viên**:
    - Kiểm tra danh tính: `$teacher_id` phải khớp chính xác với `$_SESSION['user_id']` của giáo viên đang đăng nhập (chống mạo danh ID giáo viên khác).
    - Kiểm tra phân công: gọi `verify_teacher_assignment($teacher_id, $class_id, $subject)`.
    - Nếu thất bại: phát sinh HTTP 403, ghi nhật ký vào `audit_logs` với chi tiết vi phạm, hiển thị trang 403 và gọi `exit;`.

---

### 2.3 Mã Nguồn Đề Xuất Hoàn Chỉnh Cho `includes/auth.php`

Dưới đây là mã nguồn đề xuất hoàn chỉnh sẵn sàng để triển khai vào `includes/auth.php`:

```php
<?php
// =============================================================================
// Hệ Thống Quản Lý Điểm & Cảnh Báo Sớm THCS (EduRadar / Chuẩn VnEdu)
// Middleware Xác Thực & Phân Quyền Truy Cập (RBAC & Route Guards)
// =============================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

/**
 * Đảm bảo người dùng đã đăng nhập hệ thống.
 * Nếu chưa đăng nhập, tự động chuyển hướng về trang login.php.
 *
 * @return void
 */
function require_login(): void
{
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        $loginUrl = defined('BASE_URL') ? BASE_URL . 'login.php' : 'login.php';
        header('Location: ' . $loginUrl);
        exit;
    }
}

/**
 * Trả về thông tin người dùng đang đăng nhập hoặc null nếu là khách.
 *
 * @param bool $refreshFromDb Có làm mới thông tin từ database MySQL hay không
 * @return array|null
 */
function current_user(bool $refreshFromDb = false): ?array
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    $userId = (int)$_SESSION['user_id'];

    if ($refreshFromDb) {
        try {
            $pdo = db();
            $stmt = $pdo->prepare("SELECT id, username, full_name, role, created_at FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            if ($user) {
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                return $user;
            }
        } catch (\Throwable $e) {
            error_log("current_user() DB refresh error: " . $e->getMessage());
        }
    }

    return [
        'id'        => $userId,
        'username'  => (string)($_SESSION['username'] ?? ''),
        'full_name' => (string)($_SESSION['full_name'] ?? ''),
        'role'      => (string)($_SESSION['role'] ?? 'teacher'),
    ];
}

/**
 * Yêu cầu quyền Quản trị viên (Admin).
 * Nếu không phải Admin: thiết lập HTTP 403, ghi nhật ký kiểm toán và dừng thực thi.
 *
 * @return void
 */
function require_admin(): void
{
    $isAdmin = isset($_SESSION['user_id']) && 
               isset($_SESSION['role']) && 
               $_SESSION['role'] === 'admin';

    if (!$isAdmin) {
        http_response_code(403);

        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $username = $_SESSION['username'] ?? 'Khách';
        $role = $_SESSION['role'] ?? 'guest';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? 'admin.php';

        log_action(
            $userId,
            $username,
            'Truy cập trái phép bị chặn',
            "Từ chối quyền Admin [$method $uri]: Tài khoản [$username] (role: [$role]) không có quyền truy cập khu vực quản trị."
        );

        render_forbidden_page("Khu vực này chỉ dành riêng cho tài khoản Quản trị viên (Admin) của nhà trường. Mọi hành vi truy cập trái phép đều được ghi nhận vào nhật ký kiểm toán.");
        exit;
    }
}

/**
 * Kiểm tra xem giáo viên có được phân công giảng dạy môn học tại lớp đã cho hay không.
 *
 * @param int    $teacher_id ID giáo viên
 * @param int    $class_id   ID lớp học
 * @param string $subject    Tên môn học
 * @return bool
 */
function verify_teacher_assignment(int $teacher_id, int $class_id, string $subject): bool
{
    $subject = trim($subject);
    if ($teacher_id <= 0 || $class_id <= 0 || $subject === '') {
        return false;
    }

    try {
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT 1 
            FROM teacher_classes 
            WHERE teacher_id = ? AND class_id = ? AND subject = ? 
            LIMIT 1
        ");
        $stmt->execute([$teacher_id, $class_id, $subject]);
        return (bool)$stmt->fetchColumn();
    } catch (\Throwable $e) {
        error_log("verify_teacher_assignment error: " . $e->getMessage());
        return false;
    }
}

/**
 * Yêu cầu quyền giáo viên phụ trách môn học và lớp tương ứng.
 * Nếu là Admin: Cho phép toàn quyền truy cập.
 * Nếu là Giáo viên: Bắt buộc phải có phân công trong teacher_classes và khớp session user_id.
 * Nếu vi phạm: Ghi log kiểm toán, trả về HTTP 403 và dừng thực thi.
 *
 * @param int    $teacher_id ID giáo viên cần xác minh
 * @param int    $class_id   ID lớp học
 * @param string $subject    Tên môn học
 * @return void
 */
function require_teacher_assignment(int $teacher_id, int $class_id, string $subject): void
{
    require_login();

    // Quản trị viên nhà trường có toàn quyền kiểm tra sổ điểm toàn trường
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        return;
    }

    $sessionUserId = (int)($_SESSION['user_id'] ?? 0);
    $subject = trim($subject);

    // Chặn hành vi cố tình giả mạo teacher_id hoặc truy cập lớp/môn chưa được phân công
    if ($teacher_id !== $sessionUserId || !verify_teacher_assignment($teacher_id, $class_id, $subject)) {
        http_response_code(403);

        $username = $_SESSION['username'] ?? 'Tài khoản ID ' . $sessionUserId;
        $role = $_SESSION['role'] ?? 'teacher';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? 'nhap_diem.php';

        log_action(
            $sessionUserId,
            $username,
            'Truy cập trái phép bị chặn',
            "Từ chối quyền giáo viên [$method $uri]: Tài khoản [$username] (ID: $sessionUserId) không được phân công dạy môn [$subject] tại lớp ID [$class_id] (yêu cầu ID: $teacher_id)."
        );

        render_forbidden_page("Bạn không được phân công phụ trách môn [{$subject}] tại lớp này. Mọi hành vi can thiệp trái phép đều được ghi nhận vào nhật ký kiểm toán.");
        exit;
    }
}

/**
 * Xuất giao diện phản hồi lỗi HTTP 403 chuẩn giao diện EduRadar Stitch UI.
 *
 * @param string $message Thông báo lỗi chi tiết hiển thị cho người dùng
 * @return void
 */
function render_forbidden_page(string $message): void
{
    $baseUrl = defined('BASE_URL') ? BASE_URL : '/my-web-project/';
    header('Content-Type: text/html; charset=UTF-8');
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>403 Forbidden – Truy Cập Bị Từ Chối</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>assets/css/style.css">
        <style>
            body {
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
                background: radial-gradient(circle at 50% 30%, #1e1b4b 0%, #090d16 100%);
                font-family: 'Plus Jakarta Sans', sans-serif;
                color: #0f172a;
            }
            .error-card {
                background: #ffffff;
                width: 480px;
                max-width: 90%;
                border-radius: 16px;
                padding: 40px 32px;
                text-align: center;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.45);
                border: 1px solid rgba(255, 255, 255, 0.1);
            }
            .error-icon {
                font-size: 52px;
                margin-bottom: 12px;
                display: inline-block;
            }
            .error-title {
                font-size: 24px;
                font-weight: 800;
                color: #dc2626;
                margin: 0 0 8px 0;
            }
            .error-badge {
                display: inline-block;
                background: #fee2e2;
                color: #991b1b;
                font-size: 12px;
                font-weight: 700;
                padding: 4px 12px;
                border-radius: 999px;
                margin-bottom: 16px;
            }
            .error-desc {
                font-size: 14px;
                line-height: 1.6;
                color: #475569;
                margin-bottom: 24px;
            }
            .error-actions {
                display: flex;
                gap: 12px;
                justify-content: center;
            }
            .btn-action {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 10px 20px;
                border-radius: 8px;
                font-size: 14px;
                font-weight: 600;
                text-decoration: none;
                transition: all .2s;
            }
            .btn-primary {
                background: #4f46e5;
                color: #ffffff;
            }
            .btn-primary:hover {
                background: #4338ca;
            }
            .btn-outline {
                background: #f8fafc;
                color: #334155;
                border: 1px solid #cbd5e1;
            }
            .btn-outline:hover {
                background: #e2e8f0;
            }
        </style>
    </head>
    <body>
        <div class="error-card">
            <span class="error-icon">⛔</span>
            <div class="error-badge">MÃ LỖI: HTTP 403 FORBIDDEN</div>
            <h1 class="error-title">Truy Cập Bị Từ Chối</h1>
            <p class="error-desc"><?= htmlspecialchars($message) ?></p>
            <div class="error-actions">
                <a href="<?= htmlspecialchars($baseUrl) ?>login.php" class="btn-action btn-primary">Đăng Nhập Lại</a>
                <a href="<?= htmlspecialchars($baseUrl) ?>index.php" class="btn-action btn-outline">Về Trang Chủ</a>
            </div>
        </div>
    </body>
    </html>
    <?php
}
```

---

## 3. Khảo Sát & Gia Cố Bảo Mật Tuyến Quản Trị `admin.php`

### 3.1 Các điểm yếu hiện hữu trong `admin.php`

| Vị trí dòng | Thực trạng hiện tại | Rủi ro an ninh & Tác động | Giải pháp khắc phục |
|---|---|---|---|
| **Dòng 2–16** | Tự kiểm tra `$_SESSION['role'] !== 'admin'` thủ công; gọi `logAction()` sơ sài không có danh tính, IP, request method | Thiếu tính nhất quán với hợp đồng M2; mã nguồn phân tán; log audit thiếu thông tin điều tra số | Thay thế bằng `require_once __DIR__ . '/includes/auth.php'; require_admin();` |
| **Dòng 23–56** (POST `batch_assign`) | Xử lý POST trực tiếp dựa vào guard trên đầu file, không có defensive guard; dòng 44 dùng `$pdo->query("SELECT name FROM classes WHERE id = $cid")` | Thiếu phòng thủ chiều sâu; có nguy cơ SQLi tiềm ẩn nếu kiểu dữ liệu bị lọt | Bổ sung `require_admin()` ở đầu handler; chuyển sang Prepared Statement `WHERE id = ?` |
| **Dòng 59–91** (POST `import_students`) | Xử lý nạp CSV không có defensive guard; dòng 83 nối chuỗi `$target_class_id` vào SQL query | Rủi ro thực thi ngoài ý muốn; SQL injection tiềm tàng | Bổ sung `require_admin()`; chuyển sang Prepared Statement `WHERE id = ?` |
| **Dòng 94–112** (GET `impersonate`) | Mở sổ điểm giáo viên dựa vào GET param; dòng 104 nối chuỗi `$cid` | Nguy cơ lạm dụng quyền giám sát nếu guard đầu file bị vô hiệu hóa | Bổ sung `require_admin()`; dùng Prepared Statement |
| **Dòng 115–131** (GET `delete_assign`) | Xóa phân công dựa vào GET request; dòng 122 nối trực tiếp `$aid` vào SQL | Rủi ro CSRF/Bypass và SQL injection; xóa quyền bằng GET request | Bổ sung `require_admin()`; dùng Prepared Statement `WHERE tc.id = ?` |
| **Toàn bộ file** | Gọi hàm legacy `logAction(...)` 4 lần | Sử dụng wrapper cũ thay vì chuẩn `log_action(?int, ?string, string, string)` | Chuẩn hóa sang `log_action()` với user_id và username tường minh |

### 3.2 Vị trí đặt chính xác của `require_admin()` trong `admin.php`

1. **Vị trí 1 (Bảo vệ toàn trang - Bắt buộc)**:
   - Đặt ngay tại **dòng 4** của `admin.php`, ngay sau khi nhúng `includes/auth.php`.
   - **Tác dụng**: Bất kỳ request nào (GET, POST, HEAD) từ người dùng chưa đăng nhập hoặc có vai trò `teacher` đều bị chặn ngay lập tức, trả về HTTP 403 và ngắt luồng trước khi bất kỳ truy vấn hay logic nào bên dưới được chạy.
2. **Vị trí 2 (Bảo vệ khối POST `batch_assign`)**:
   - Đặt ngay sau `if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'batch_assign') {`.
   - **Tác dụng**: Bảo đảm tính bất biến, nếu handler được tách thành API endpoint hoặc router sau này, quyền Admin vẫn được xác thực độc lập.
3. **Vị trí 3 (Bảo vệ khối POST `import_students`)**:
   - Đặt ngay sau `if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_students') {`.
   - **Tác dụng**: Ngăn chặn tuyệt đối việc tải file và ghi học sinh vào cơ sở dữ liệu nếu không có quyền Admin.
4. **Vị trí 4 (Bảo vệ khối GET `impersonate` & `delete_assign`)**:
   - Đặt ở đầu mỗi khối xử lý hành động thay đổi trạng thái hoặc giám sát.

### 3.3 Bản Vá Chi Tiết Cho `admin.php` (Unified Diff Patch)

```diff
--- admin.php.orig
+++ admin.php
@@ -1,18 +1,8 @@
 <?php
-session_start();
-require_once __DIR__ . '/includes/db.php';
+require_once __DIR__ . '/includes/auth.php';
 
-// CHẶN BẢO MẬT: CHỈ DUY NHẤT TÀI KHOẢN ROLE ADMIN MỚI ĐƯỢC PHÉP TRUY CẬP TRANG NÀY!
-if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
-    http_response_code(403);
-    logAction('Truy cập trái phép bị chặn', 'Cố tình truy cập trang admin.php khi không có quyền.');
-    die('
-        <div style="font-family: sans-serif; text-align: center; padding: 50px;">
-            <h1 style="color: #dc2626;">⛔ Truy cập bị từ chối!</h1>
-            <p>Khu vực này chỉ dành riêng cho tài khoản Quản trị viên (Admin) của nhà trường.</p>
-            <a href="login.php" style="display: inline-block; padding: 10px 20px; background: #4f46e5; color: #fff; text-decoration: none; border-radius: 8px;">Đăng nhập bằng tài khoản Admin</a>
-        </div>
-    ');
-}
+// BẢO VỆ TUYẾN QUẢN TRỊ: CHỈ DUY NHẤT ROLE ADMIN ĐƯỢC PHÉP TRUY CẬP TRANG NÀY
+require_admin();
 
 $pdo = db();
 $msg = '';
@@ -21,6 +11,7 @@
 // TÍNH NĂNG 1: ADMIN PHÂN CÔNG HÀNG LOẠT BẰNG CHECKBOX
 if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'batch_assign') {
+    require_admin();
     $teacher_id = (int)$_POST['teacher_id'];
     $subject = trim($_POST['subject']);
     $selected_classes = $_POST['class_ids'] ?? [];
@@ -41,14 +32,16 @@
                 $ins = $pdo->prepare("INSERT INTO teacher_classes (teacher_id, class_id, subject) VALUES (?, ?, ?)");
                 $ins->execute([$teacher_id, $cid, $subject]);
                 $count++;
 
-                $cName = $pdo->query("SELECT name FROM classes WHERE id = $cid")->fetchColumn();
+                $cStmt = $pdo->prepare("SELECT name FROM classes WHERE id = ?");
+                $cStmt->execute([$cid]);
+                $cName = $cStmt->fetchColumn();
                 if ($cName) $classNames[] = $cName;
             }
         }
 
         // GHI NHẬT KÝ VÀO DATABASE
         $classesStr = implode(', ', $classNames);
-        logAction('Phân công giáo viên', "Admin phân công cho [$teacherName] dạy môn [$subject] ở các lớp: [$classesStr].");
+        log_action((int)$_SESSION['user_id'], $_SESSION['username'] ?? 'admin', 'Phân công giáo viên', "Admin phân công cho [$teacherName] dạy môn [$subject] ở các lớp: [$classesStr].");
         $msg = "Đã phân công thành công $count lớp cho giáo viên!";
     } else {
         $err = "Vui lòng chọn đầy đủ giáo viên và môn giảng dạy!";
@@ -57,6 +50,7 @@
 // TÍNH NĂNG 2: ADMIN IMPORT DANH SÁCH HỌC SINH VÀO LỚP ĐƯỢC CHỌN
 if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_students') {
+    require_admin();
     $target_class_id = (int)$_POST['target_class_id'];
     if ($target_class_id && isset($_FILES['student_file']) && $_FILES['student_file']['error'] === UPLOAD_ERR_OK) {
         $handle = fopen($_FILES['student_file']['tmp_name'], 'r');
@@ -80,9 +74,11 @@
                 }
             }
             fclose($handle);
 
-            $cName = $pdo->query("SELECT name FROM classes WHERE id = $target_class_id")->fetchColumn();
+            $cStmt = $pdo->prepare("SELECT name FROM classes WHERE id = ?");
+            $cStmt->execute([$target_class_id]);
+            $cName = $cStmt->fetchColumn();
             // GHI NHẬT KÝ VÀO DATABASE
-            logAction('Import học sinh', "Admin nạp thành công $imported học sinh vào lớp [$cName].");
+            log_action((int)$_SESSION['user_id'], $_SESSION['username'] ?? 'admin', 'Import học sinh', "Admin nạp thành công $imported học sinh vào lớp [$cName].");
             $msg = "Đã import thành công $imported học sinh vào lớp!";
         }
     } else {
@@ -92,6 +88,7 @@
 // TÍNH NĂNG 3: ADMIN TRUY CẬP / XEM TRỰC TIẾP BẢNG ĐIỂM CỦA BẤT KỲ GIÁO VIÊN NÀO
 if (isset($_GET['impersonate'])) {
+    require_admin();
     $teacherId = (int)$_GET['impersonate'];
     $sub = $_GET['sub'] ?? 'Toán';
     $cid = (int)($_GET['cid'] ?? 0);
@@ -101,8 +98,10 @@
     $teacher = $tUser->fetch();
     
     if ($teacher) {
-        $cName = $pdo->query("SELECT name FROM classes WHERE id = $cid")->fetchColumn();
-        logAction('Admin giám sát sổ điểm', "Admin truy cập mở bảng điểm môn [$sub] lớp [$cName] của giáo viên [{$teacher['full_name']}].");
+        $cStmt = $pdo->prepare("SELECT name FROM classes WHERE id = ?");
+        $cStmt->execute([$cid]);
+        $cName = $cStmt->fetchColumn();
+        log_action((int)$_SESSION['user_id'], $_SESSION['username'] ?? 'admin', 'Admin giám sát sổ điểm', "Admin truy cập mở bảng điểm môn [$sub] lớp [$cName] của giáo viên [{$teacher['full_name']}].");
 
         $_SESSION['view_as_teacher_id'] = $teacher['id'];
         $_SESSION['view_as_teacher_name'] = $teacher['full_name'];
@@ -113,18 +112,20 @@
 // HỦY PHÂN CÔNG
 if (isset($_GET['delete_assign'])) {
+    require_admin();
     $aid = (int)$_GET['delete_assign'];
-    $info = $pdo->query("
+    $stmt = $pdo->prepare("
         SELECT u.full_name, c.name AS class_name, tc.subject 
         FROM teacher_classes tc 
         JOIN users u ON tc.teacher_id = u.id 
         JOIN classes c ON tc.class_id = c.id 
-        WHERE tc.id = $aid
-    ")->fetch();
+        WHERE tc.id = ?
+    ");
+    $stmt->execute([$aid]);
+    $info = $stmt->fetch();
 
     $pdo->prepare("DELETE FROM teacher_classes WHERE id = ?")->execute([$aid]);
     if ($info) {
-        logAction('Hủy phân công', "Admin đã hủy quyền dạy môn [{$info['subject']}] lớp [{$info['class_name']}] của giáo viên [{$info['full_name']}].");
+        log_action((int)$_SESSION['user_id'], $_SESSION['username'] ?? 'admin', 'Hủy phân công', "Admin đã hủy quyền dạy môn [{$info['subject']}] lớp [{$info['class_name']}] của giáo viên [{$info['full_name']}].");
     }
     header('Location: admin.php');
     exit;
```

---

## 4. Kịch Bản Kiểm Thử & Phương Pháp Xác Minh Độc Lập

Để kiểm chứng tính đúng đắn và bảo mật tuyệt đối của `includes/auth.php` và `admin.php`, thiết kế kịch bản thử nghiệm đa tài khoản gồm các ca sau:

### 4.1 Ca kiểm thử 1: Truy cập khách (Unauthenticated) vào `admin.php`
- **Thực hiện**: Gửi HTTP GET và HTTP POST (kèm `action=batch_assign`) tới `http://localhost/my-web-project/admin.php` mà không gửi kèm Cookie session.
- **Kỳ vọng**:
  - Mã phản hồi HTTP: `403 Forbidden`.
  - Body: Trang HTML thông báo lỗi 403, không rò rỉ mã HTML giao diện admin.
  - Bảng `audit_logs` có bản ghi mới: `action = 'Truy cập trái phép bị chặn'`, `username = 'Khách'`, `details` chứa `admin.php`.
  - Không có bất kỳ bản ghi nào được tạo trong `teacher_classes`.

### 4.2 Ca kiểm thử 2: Truy cập giáo viên (`gv_toan`) vào `admin.php`
- **Thực hiện**: Đăng nhập bằng `gv_toan` (lấy session cookie), sau đó gửi HTTP GET và HTTP POST (`action=batch_assign`) tới `admin.php`.
- **Kỳ vọng**:
  - Mã phản hồi HTTP: `403 Forbidden`.
  - Bảng `audit_logs` có bản ghi mới: `action = 'Truy cập trái phép bị chặn'`, `username = 'gv_toan'`, `user_id = 2`.
  - Không có lớp mới nào được phân công trong cơ sở dữ liệu.

### 4.3 Ca kiểm thử 3: Truy cập Admin (`admin`) vào `admin.php`
- **Thực hiện**: Đăng nhập tài khoản `admin` (lấy session cookie), gửi HTTP GET tới `admin.php`.
- **Kỳ vọng**:
  - Mã phản hồi HTTP: `200 OK`.
  - Hiển thị đầy đủ bảng phân công, khối chọn lớp khối 6–9 và nhật ký audit log.

### 4.4 Ca kiểm thử 4: Kiểm tra unit test các hàm trong `includes/auth.php`
- `verify_teacher_assignment(2, 1, 'Toán')` -> trả về `true` (do seed data có phân công giáo viên 2 dạy Toán lớp 6A1 ID 1).
- `verify_teacher_assignment(2, 1, 'Ngữ Văn')` -> trả về `false` (giáo viên 2 không dạy Ngữ Văn lớp 1).
- `verify_teacher_assignment(2, 999, 'Toán')` -> trả về `false`.
- `current_user()` trả về đúng mảng định dạng khi có session và `null` khi không có session.

---

## 5. Phối Hợp Tuyến Làm Việc (Cross-Agent Coordination)

1. **Phối hợp với `explorer_m2_1`**:
   - `explorer_m2_1` phụ trách loại bỏ backdoor và xử lý `login.php`, `logout.php`.
   - `login.php` sau khi xác thực thành công sẽ gán `$_SESSION['user_id']`, `$_SESSION['username']`, `$_SESSION['full_name']`, `$_SESSION['role']` đúng với cấu trúc mà `current_user()` và `require_admin()` dựa vào.
2. **Phối hợp với `explorer_m2_3`**:
   - `explorer_m2_3` phụ trách bảo vệ `nhap_diem.php` chống BOLA/IDOR và xây dựng bộ kiểm thử `tests/verify_m2.php`.
   - `nhap_diem.php` sẽ gọi trực tiếp `require_teacher_assignment((int)$_SESSION['user_id'], $selectedClassId, $selectedSubject)` được cung cấp bởi `includes/auth.php`.
   - Bộ kiểm thử `tests/verify_m2.php` sẽ kích hoạt kiểm thử trực tiếp cả 4 hàm RBAC và kiểm tra phản hồi HTTP 403 trên `admin.php`.
