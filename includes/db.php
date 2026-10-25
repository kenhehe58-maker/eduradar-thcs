<?php
// =============================================================================
// Hệ Thống Quản Lý Điểm & Cảnh Báo Sớm THCS (EduRadar / Chuẩn VnEdu)
// Quản trị kết nối cơ sở dữ liệu MySQL (PDO) & Ghi nhật ký hệ thống (Audit Log)
// Không sử dụng SQLite (Pure MySQL Architecture)
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
