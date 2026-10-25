<?php
// =============================================================================
// Hệ Thống Quản Lý Điểm & Cảnh Báo Sớm THCS (EduRadar / Chuẩn VnEdu)
// Middleware Xác Thực & Phân Quyền Truy Cập (RBAC & Route Guards)
// =============================================================================

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
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
        header('Location: login.php');
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
 * Quản lý ngữ cảnh điều hướng Lớp & Môn cô lập theo từng tài khoản (Anti-Session-Leak).
 * Ngăn chặn việc chuyển đổi giữa các tài khoản (Admin <-> GV Toán <-> GV Văn) trên cùng
 * trình duyệt hoặc mở tab Quét Nhanh (Guest) làm rò rỉ biến `shared_class_id` / `shared_subject`.
 *
 * @param int $userId ID người dùng hiện tại
 * @return array{class_id: int, subject: string}
 */
function get_user_nav_context(int $userId): array
{
    if ($userId <= 0) {
        return ['class_id' => 0, 'subject' => ''];
    }

    // Nếu chủ sở hữu ngữ cảnh cũ không khớp với userId đang đăng nhập, xóa sạch biến dùng chung cũ
    if (!isset($_SESSION['ctx_owner_id']) || (int)$_SESSION['ctx_owner_id'] !== $userId) {
        unset(
            $_SESSION['shared_class_id'],
            $_SESSION['shared_subject'],
            $_SESSION['last_class_id'],
            $_SESSION['last_subject']
        );
        $_SESSION['ctx_owner_id'] = $userId;
    }

    $ctx = $_SESSION['user_ctx'][$userId] ?? [];
    $classId = isset($ctx['class_id']) ? (int)$ctx['class_id'] : (int)($_SESSION['shared_class_id'] ?? $_SESSION['last_class_id'] ?? 0);
    $subject = isset($ctx['subject']) ? trim((string)$ctx['subject']) : trim((string)($_SESSION['shared_subject'] ?? $_SESSION['last_subject'] ?? ''));

    return [
        'class_id' => $classId,
        'subject'  => $subject,
    ];
}

/**
 * Lưu ngữ cảnh điều hướng Lớp & Môn theo định danh tài khoản hiện tại.
 *
 * @param int    $userId  ID người dùng hiện tại
 * @param int    $classId ID lớp học đang chọn
 * @param string $subject Môn học đang chọn
 * @return void
 */
function set_user_nav_context(int $userId, int $classId, string $subject): void
{
    if ($userId <= 0) {
        return;
    }

    if (!isset($_SESSION['user_ctx']) || !is_array($_SESSION['user_ctx'])) {
        $_SESSION['user_ctx'] = [];
    }

    $_SESSION['ctx_owner_id'] = $userId;
    $_SESSION['user_ctx'][$userId] = [
        'class_id'   => $classId,
        'subject'    => $subject,
        'updated_at' => time(),
    ];

    // Đồng bộ biến tương thích ngược cho phiên hiện tại
    $_SESSION['shared_class_id'] = $classId;
    $_SESSION['shared_subject']  = $subject;
    $_SESSION['last_class_id']   = $classId;
    $_SESSION['last_subject']    = $subject;
}


/**
 * Yêu cầu quyền Quản trị viên (Admin).
 * Nếu không phải Admin: thiết lập HTTP 403, ghi nhật ký kiểm toán và dừng thực thi.
 *
 * @return void
 */
function require_admin(): void
{
    // 1. Chưa đăng nhập -> Chuyển hướng 302 về trang login.php
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }

    // 2. Đã đăng nhập nhưng không có quyền admin -> HTTP 403 Forbidden
    $isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

    if (!$isAdmin) {
        http_response_code(403);

        $userId = (int)$_SESSION['user_id'];
        $username = $_SESSION['username'] ?? 'Khách';
        $role = $_SESSION['role'] ?? 'teacher';
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
