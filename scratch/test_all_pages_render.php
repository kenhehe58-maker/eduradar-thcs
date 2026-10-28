<?php
// Scratch script: Stress test rendering of all core pages under admin and teacher roles
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$pages = [
    'welcome.php' => ['role' => 'admin', 'user_id' => 1, 'params' => ''],
    'index.php' => ['role' => 'teacher', 'user_id' => 2, 'params' => 'class_id=28&subject=Toán'],
    'nhap_diem.php' => ['role' => 'teacher', 'user_id' => 2, 'params' => 'class_id=28&subject=Toán'],
    'admin.php' => ['role' => 'admin', 'user_id' => 1, 'params' => ''],
    'bao_cao_slide_bgh.php' => ['role' => 'admin', 'user_id' => 1, 'params' => 'class_id=28&subject=Toán'],
    'in_bao_cao_bgh.php' => ['role' => 'admin', 'user_id' => 1, 'params' => 'class_id=28&subject=Toán'],
    'in_phieu_phu_dao.php' => ['role' => 'teacher', 'user_id' => 2, 'params' => 'student_id=42&subject=Toán'],
    'quet_nhanh.php' => ['role' => 'teacher', 'user_id' => 2, 'params' => 'action=sample']
];

$baseUrl = 'http://localhost/my-web-project/';

echo "=== KIỂM TRA TỰ ĐỘNG RENDERING TOÀN BỘ CÁC TRANG ===\n";

foreach ($pages as $page => $cfg) {
    $url = $baseUrl . $page . ($cfg['params'] ? '?' . $cfg['params'] : '');
    
    // Tạo session cookie mô phỏng
    $cookieFile = tempnam(sys_get_temp_dir(), 'ck_');
    
    // Login trước
    $username = ($cfg['role'] === 'admin') ? 'admin' : 'gv_toan';
    $ch = curl_init($baseUrl . 'login.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'username' => $username,
        'password' => '123456'
    ]));
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_exec($ch);
    curl_close($ch);
    
    // Truy cập trang đích
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    @unlink($cookieFile);
    
    $hasFatal = stripos($response, 'Fatal error') !== false;
    $hasWarning = stripos($response, 'Warning:') !== false;
    $hasNotice = stripos($response, 'Notice:') !== false;
    
    $status = ($httpCode === 200 && !$hasFatal && !$hasWarning && !$hasNotice) ? '[ PASS ]' : '[ FAIL ]';
    echo sprintf("%-25s | HTTP %d | Length: %6d bytes | %s\n", $page, $httpCode, strlen($response), $status);
    
    if ($hasFatal || $hasWarning || $hasNotice) {
        echo "   -> Phát hiện lỗi PHP:\n";
        preg_match_all('/(Fatal error|Warning|Notice):.+/i', $response, $matches);
        foreach (array_slice($matches[0], 0, 3) as $m) {
            echo "      " . strip_tags($m) . "\n";
        }
    }
}
echo "=== HOÀN TẤT KIỂM TRA ===\n";
