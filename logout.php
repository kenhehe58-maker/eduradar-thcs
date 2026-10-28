<?php
// =============================================================================
// Hệ Thống Quản Lý Điểm & Cảnh Báo Sớm THCS (EduRadar / Chuẩn VnEdu)
// Xử lý đăng xuất an toàn (Secure Session Destruction & Audit Logging)
// =============================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/db.php';

// Trích xuất danh tính trước khi giải phóng bộ nhớ session
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$username = $_SESSION['username'] ?? null;

// Ghi nhật ký đăng xuất vào bảng audit_logs
if ($username !== null) {
    log_action($userId, $username, 'Đăng xuất', "Người dùng [$username] đã đăng xuất khỏi hệ thống.");
}

// Xóa sạch toàn bộ biến session trong bộ nhớ runtime
$_SESSION = [];

// Hủy cookie session phía client trình duyệt nếu cookie được sử dụng
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Hủy dữ liệu session lưu trên máy chủ
session_destroy();

// Chuyển hướng an toàn về trang chủ
header('Location: welcome.php');
exit;
