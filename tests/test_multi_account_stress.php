<?php
/**
 * =============================================================================
 * EduRadar THCS — Phase 4 Multi-Account Live Stress & Anti-BOLA Verification
 * Kiểm thử đồng thời 3 tài khoản (Admin, GV Toán, GV Văn) + Chế độ Khách:
 * 1. Cô lập phiên (Session Context Isolation) khi đổi tài khoản trên cùng trình duyệt
 * 2. Hoạt động song song 3 phiên độc lập (Admin, GV Toán, GV Văn) không đè state
 * 3. Chặn tức thì Anti-BOLA / IDOR (HTTP 403) khi GV Toán can thiệp lớp/môn của GV Văn
 * 4. Thu hồi quyền thời gian thực (Real-time Revocation -> 403 -> Re-grant -> 200)
 * 5. Cô lập tuyệt đối Chế độ Quét Nhanh (Guest Sandbox) với phiên Giáo viên
 * 6. Kiểm tra Slide Báo Cáo BGH (`bao_cao_slide_bgh.php`)
 * =============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

$baseUrl = 'http://localhost/my-web-project';
$pdo = db();

$passed = 0;
$failed = 0;

function assertStress(bool $cond, string $name, string $detail = ''): void
{
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  [PASS] {$name}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$name}" . ($detail !== '' ? " — {$detail}" : "") . "\n";
    }
}

function httpReq(string $url, string $cookieFile, ?array $postData = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR      => $cookieFile,
        CURLOPT_COOKIEFILE     => $cookieFile,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HEADER         => true,
    ]);
    if ($postData !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    }
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $headers = substr((string)$raw, 0, $headerSize);
    $body = substr((string)$raw, $headerSize);
    return ['status' => $status, 'headers' => $headers, 'body' => $body];
}

echo "=================================================================\n";
echo " EDURADAR THCS - MULTI-ACCOUNT & ANTI-BOLA STRESS TEST SUITE\n";
echo "=================================================================\n";

$cookieAdmin = sys_get_temp_dir() . '/eduradar_stress_admin.cookie';
$cookieToan  = sys_get_temp_dir() . '/eduradar_stress_toan.cookie';
$cookieVan   = sys_get_temp_dir() . '/eduradar_stress_van.cookie';
$cookieSharedBrowser = sys_get_temp_dir() . '/eduradar_stress_shared.cookie';
@unlink($cookieAdmin);
@unlink($cookieToan);
@unlink($cookieVan);
@unlink($cookieSharedBrowser);

// 1. Đăng nhập song song 3 tài khoản ở 3 phiên
$resAdminLogin = httpReq("$baseUrl/login.php", $cookieAdmin, ['username' => 'admin', 'password' => '123456']);
$resToanLogin  = httpReq("$baseUrl/login.php", $cookieToan,  ['username' => 'gv_toan', 'password' => '123456']);
$resVanLogin   = httpReq("$baseUrl/login.php", $cookieVan,   ['username' => 'gv_van', 'password' => '123456']);

assertStress($resAdminLogin['status'] === 302, 'S1.01 Admin đăng nhập thành công (302 -> admin.php)');
assertStress($resToanLogin['status'] === 302,  'S1.02 GV Toán đăng nhập thành công (302 -> index.php)');
assertStress($resVanLogin['status'] === 302,   'S1.03 GV Văn đăng nhập thành công (302 -> index.php)');

// Tìm lớp của GV Toán và lớp/môn của GV Văn
$toanAssign = $pdo->query("SELECT tc.teacher_id, tc.class_id, tc.subject, c.name FROM teacher_classes tc JOIN users u ON tc.teacher_id = u.id JOIN classes c ON tc.class_id = c.id WHERE u.username = 'gv_toan' AND (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id) > 0 LIMIT 1")->fetch();
$vanAssign  = $pdo->query("SELECT tc.teacher_id, tc.class_id, tc.subject, c.name FROM teacher_classes tc JOIN users u ON tc.teacher_id = u.id JOIN classes c ON tc.class_id = c.id WHERE u.username = 'gv_van' AND (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id) > 0 LIMIT 1")->fetch();

$toanClassId = (int)$toanAssign['class_id'];
$toanSubject = $toanAssign['subject'];
$vanClassId  = (int)$vanAssign['class_id'];
$vanSubject  = $vanAssign['subject'];

// 2. Kiểm tra truy cập song song đúng phân quyền
$resToanGradebook = httpReq("$baseUrl/nhap_diem.php?class_id={$toanClassId}&subject=" . urlencode($toanSubject), $cookieToan);
$resVanGradebook  = httpReq("$baseUrl/nhap_diem.php?class_id={$vanClassId}&subject=" . urlencode($vanSubject), $cookieVan);

assertStress($resToanGradebook['status'] === 200, "S2.01 GV Toán mở sổ điểm lớp {$toanAssign['name']} môn {$toanSubject} (HTTP 200)");
assertStress($resVanGradebook['status'] === 200,  "S2.02 GV Văn mở sổ điểm lớp {$vanAssign['name']} môn {$vanSubject} (HTTP 200)");

// 3. Kiểm tra Anti-BOLA / IDOR chéo giữa GV Toán và GV Văn
$resToanHackVanSub = httpReq("$baseUrl/nhap_diem.php?class_id={$toanClassId}&subject=" . urlencode($vanSubject), $cookieToan);
assertStress($resToanHackVanSub['status'] === 403, "S3.01 Anti-BOLA: Chặn GV Toán mở sổ điểm môn [{$vanSubject}] (HTTP 403)");

$studentInToanClass = (int)$pdo->query("SELECT id FROM students WHERE class_id = {$toanClassId} LIMIT 1")->fetchColumn();
$resToanAjaxHack = httpReq("$baseUrl/nhap_diem.php", $cookieToan, [
    'action'     => 'ajax_save_single_score',
    'student_id' => $studentInToanClass,
    'class_id'   => $toanClassId,
    'subject'    => $vanSubject,
    'tx1'        => '9.5'
]);
assertStress($resToanAjaxHack['status'] === 403, "S3.02 Anti-BOLA AJAX: Chặn GV Toán lưu điểm inline môn [{$vanSubject}] (HTTP 403)");

$resToanNoteHack = httpReq("$baseUrl/index.php", $cookieToan, [
    'action'     => 'save_teacher_note',
    'student_id' => $studentInToanClass,
    'subject'    => $vanSubject,
    'note'       => 'Thử ghi chú trái phép'
]);
assertStress($resToanNoteHack['status'] === 403, "S3.03 Anti-BOLA Note: Chặn GV Toán lưu nhận xét môn [{$vanSubject}] (HTTP 403)");

// 4. Kiểm tra Thu hồi quyền thời gian thực (Real-time Revocation)
$pdo->prepare("DELETE FROM teacher_classes WHERE teacher_id = ? AND class_id = ? AND subject = ?")
    ->execute([(int)$toanAssign['teacher_id'], $toanClassId, $toanSubject]);

$resAfterRevoke = httpReq("$baseUrl/nhap_diem.php", $cookieToan, [
    'action'     => 'ajax_save_single_score',
    'student_id' => $studentInToanClass,
    'class_id'   => $toanClassId,
    'subject'    => $toanSubject,
    'tx1'        => '8.0'
]);
assertStress($resAfterRevoke['status'] === 403, "S4.01 Real-time Revocation: Ngay khi Admin thu hồi lớp {$toanAssign['name']}, GV Toán bị chặn lưu điểm lập tức (HTTP 403)");

// Khôi phục lại phân công cho GV Toán
$pdo->prepare("INSERT IGNORE INTO teacher_classes (teacher_id, class_id, subject) VALUES (?, ?, ?)")
    ->execute([(int)$toanAssign['teacher_id'], $toanClassId, $toanSubject]);

// Lấy điểm hiện tại để bảo toàn dữ liệu 100%
$origScoreRow = $pdo->query("SELECT tx1, tx2, tx3, tx4, gk, ck FROM scores WHERE student_id = {$studentInToanClass} AND subject = " . $pdo->quote($toanSubject))->fetch();
$resAfterRestore = httpReq("$baseUrl/nhap_diem.php", $cookieToan, [
    'action'     => 'ajax_save_single_score',
    'student_id' => $studentInToanClass,
    'class_id'   => $toanClassId,
    'subject'    => $toanSubject,
    'tx1'        => $origScoreRow['tx1'] ?? '8.0',
    'tx2'        => $origScoreRow['tx2'] ?? '8.0',
    'tx3'        => $origScoreRow['tx3'] ?? '8.5',
    'tx4'        => $origScoreRow['tx4'] ?? '',
    'gk'         => $origScoreRow['gk']  ?? '8.0',
    'ck'         => $origScoreRow['ck']  ?? '8.5',
]);
assertStress($resAfterRestore['status'] === 200, "S4.02 Real-time Re-grant: Sau khi Admin cấp lại lớp {$toanAssign['name']}, GV Toán lưu điểm bình thường (HTTP 200)");

// 5. Kiểm tra đổi tài khoản trên cùng 1 trình duyệt (Anti-Session-Leak) & Cô lập Quét Nhanh
httpReq("$baseUrl/login.php", $cookieSharedBrowser, ['username' => 'admin', 'password' => '123456']);
httpReq("$baseUrl/index.php?class_id={$vanClassId}&subject=" . urlencode($vanSubject), $cookieSharedBrowser);
// Đăng nhập đè bằng GV Toán trên cùng trình duyệt
httpReq("$baseUrl/login.php", $cookieSharedBrowser, ['username' => 'gv_toan', 'password' => '123456']);
$resSwitchedIndex = httpReq("$baseUrl/index.php", $cookieSharedBrowser);
assertStress(
    $resSwitchedIndex['status'] === 200 && strpos($resSwitchedIndex['body'], 'Môn Ngữ Văn (Được phân công)') === false,
    "S5.01 Anti-Session-Leak: Đổi từ Admin sang GV Toán trên cùng trình duyệt tự động xóa sạch ngữ cảnh môn Ngữ Văn cũ"
);

// Mở Quét Nhanh nạp dữ liệu mẫu trên cùng trình duyệt của GV Toán
$resGuestSample = httpReq("$baseUrl/quet_nhanh.php?action=sample", $cookieSharedBrowser);
$resToanAfterGuest = httpReq("$baseUrl/index.php", $cookieSharedBrowser);
assertStress(
    $resToanAfterGuest['status'] === 200 && strpos($resToanAfterGuest['body'], 'Phân Công Giáo Viên') !== false,
    "S5.02 Sandbox Isolation: Sử dụng Quét Nhanh (Guest Sample) không làm hỏng phiên làm việc của Giáo viên"
);

// 6. Kiểm tra Slide Báo Cáo BGH (`bao_cao_slide_bgh.php`)
$resSlideAdmin = httpReq("$baseUrl/bao_cao_slide_bgh.php?class_id={$toanClassId}&subject=" . urlencode($toanSubject), $cookieAdmin);
$resSlideToan  = httpReq("$baseUrl/bao_cao_slide_bgh.php?class_id={$toanClassId}&subject=" . urlencode($toanSubject), $cookieToan);
$resSlideHack  = httpReq("$baseUrl/bao_cao_slide_bgh.php?class_id={$toanClassId}&subject=" . urlencode($vanSubject), $cookieToan);

assertStress($resSlideAdmin['status'] === 200 && strpos($resSlideAdmin['body'], 'Chart.js') !== false, "S6.01 Admin mở Slide Báo Cáo BGH tương tác thành công (HTTP 200)");
assertStress($resSlideToan['status'] === 200 && strpos($resSlideToan['body'], 'Slide 01') !== false,   "S6.02 GV Toán mở Slide Báo Cáo BGH của lớp mình thành công (HTTP 200)");
assertStress($resSlideHack['status'] === 403, "S6.03 Anti-BOLA Slide BGH: Chặn GV Toán xuất Slide môn [{$vanSubject}] (HTTP 403)");

@unlink($cookieAdmin);
@unlink($cookieToan);
@unlink($cookieVan);
@unlink($cookieSharedBrowser);

echo "-----------------------------------------------------------------\n";
echo " TỔNG KẾT STRESS TEST: {$passed} PASS / {$failed} FAIL\n";
echo "=================================================================\n";
exit($failed > 0 ? 1 : 0);
