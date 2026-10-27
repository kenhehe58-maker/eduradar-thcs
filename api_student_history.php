<?php
/**
 * API: Truy vấn Lịch sử Điểm số (Audit Trail) & Hồ sơ Can thiệp Sư phạm
 * Chuẩn System Design Primer & RBAC Authentication
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Chưa đăng nhập.']);
    exit;
}

$studentId = (int)($_GET['student_id'] ?? 0);
$subject = trim((string)($_GET['subject'] ?? ''));

if ($studentId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Thiếu mã định danh học sinh.']);
    exit;
}

$subParam = ($subject !== '' && $subject !== 'all' && strpos($subject, ',') === false) ? $subject : null;
$auditLogs = get_student_score_audit_logs($studentId, $subParam, 15);
$interventions = get_student_interventions($studentId, $subParam);

echo json_encode([
    'success' => true,
    'student_id' => $studentId,
    'subject' => $subject,
    'audit_logs' => $auditLogs,
    'interventions' => $interventions
]);
