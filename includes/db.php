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

/**
 * Ghi vết lịch sử thay đổi điểm số vào bảng score_audit_logs (Chuẩn Event Sourcing & Audit Trail)
 */
function log_score_audit(
    int $studentId,
    string $subject,
    string $scoreField,
    ?float $oldVal,
    ?float $newVal,
    ?int $userId = null,
    ?string $userName = null,
    string $actionType = 'update',
    string $note = ''
): void {
    if ($oldVal === $newVal) return; // Không thay đổi thì bỏ qua
    try {
        $pdo = db();
        if ($userId === null) {
            $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        }
        if ($userName === null || $userName === '') {
            $userName = $_SESSION['username'] ?? 'Giáo viên';
        }
        $stmt = $pdo->prepare("
            INSERT INTO score_audit_logs (student_id, subject, score_field, old_value, new_value, changed_by_user_id, changed_by_name, action_type, note, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$studentId, $subject, $scoreField, $oldVal, $newVal, $userId, $userName, $actionType, $note]);
    } catch (\Throwable $e) {
        error_log('Score audit log error: ' . $e->getMessage());
    }
}

/**
 * Truy vấn lịch sử chỉnh sửa điểm của học sinh
 */
function get_student_score_audit_logs(int $studentId, ?string $subject = null, int $limit = 20): array
{
    try {
        $pdo = db();
        if ($subject !== null && $subject !== '' && $subject !== 'all') {
            $stmt = $pdo->prepare("SELECT * FROM score_audit_logs WHERE student_id = ? AND subject = ? ORDER BY created_at DESC LIMIT ?");
            $stmt->bindValue(1, $studentId, PDO::PARAM_INT);
            $stmt->bindValue(2, $subject, PDO::PARAM_STR);
            $stmt->bindValue(3, $limit, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $stmt = $pdo->prepare("SELECT * FROM score_audit_logs WHERE student_id = ? ORDER BY created_at DESC LIMIT ?");
            $stmt->bindValue(1, $studentId, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        return [];
    }
}

/**
 * Ghi nhận hành động can thiệp sư phạm vào sổ theo dõi can thiệp
 */
function record_student_intervention(
    int $studentId,
    string $subject,
    string $riskLevel,
    string $actionType,
    float $targetScore,
    string $status,
    string $notes,
    ?int $userId = null
): bool {
    try {
        $pdo = db();
        if ($userId === null) {
            $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        }
        $stmt = $pdo->prepare("
            INSERT INTO student_interventions (student_id, subject, risk_level, action_type, target_score, status, teacher_notes, created_by_user_id, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        return $stmt->execute([$studentId, $subject, $riskLevel, $actionType, $targetScore, $status, $notes, $userId]);
    } catch (\Throwable $e) {
        error_log('Intervention record error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Lấy danh sách hồ sơ can thiệp sư phạm của học sinh
 */
function get_student_interventions(int $studentId, ?string $subject = null): array
{
    try {
        $pdo = db();
        if ($subject !== null && $subject !== '' && $subject !== 'all') {
            $stmt = $pdo->prepare("SELECT * FROM student_interventions WHERE student_id = ? AND subject = ? ORDER BY updated_at DESC");
            $stmt->execute([$studentId, $subject]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM student_interventions WHERE student_id = ? ORDER BY updated_at DESC");
            $stmt->execute([$studentId]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        return [];
    }
}
