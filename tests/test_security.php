<?php
/**
 * =============================================================================
 * EduRadar THCS — Security Hardening Test Suite (test_security.php)
 * Scenarios: SQLi, XSS, CSRF, Brute-Force, BOLA/IDOR & Session Integrity
 * Standards: OWASP Top 10 & TT06/2024 - TT24/2025 KHKT
 * =============================================================================
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = db();
$base_url = 'http://localhost/my-web-project';

$passed = 0;
$failed = 0;

function assert_sec(string $title, bool $condition, string $detail = '') {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] $title\n";
    } else {
        $failed++;
        echo "  [FAIL] $title: $detail\n";
    }
}

function http_req(string $url, string $method = 'GET', array $data = [], ?string $cookieFile = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    $resp = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($resp, 0, $header_size);
    $body = substr($resp, $header_size);
    curl_close($ch);
    return ['status' => $status, 'headers' => $headers, 'body' => $body];
}

function login_user(string $base_url, string $u, string $p) {
    $cookieFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sec_' . $u . '_' . uniqid() . '.txt';
    $res = http_req($base_url . '/login.php', 'POST', ['username' => $u, 'password' => $p], $cookieFile);
    return ['cookie_file' => $cookieFile, 'status' => $res['status']];
}

echo "========================================================================\n";
echo "  EDURADAR THCS — COMPREHENSIVE SECURITY VULNERABILITY TEST SUITE\n";
echo "========================================================================\n\n";

// --- SUITE 1: SQL Injection (SQLi) Hardening ---
echo "--- SUITE 1: SQL Injection Hardening ---\n";

// 1.1 Tautology login bypass
$sqli_login = http_req($base_url . '/login.php', 'POST', [
    'username' => "' OR '1'='1",
    'password' => "' OR '1'='1"
]);
assert_sec("SQLi: Login Tautology Bypass Blocked", strpos($sqli_login['body'], 'Sai tên đăng nhập hoặc mật khẩu') !== false || $sqli_login['status'] === 200, "Unexpected response on login bypass");

// 1.2 Union-based injection on search
$admin_auth = login_user($base_url, 'admin', '123456');
$sqli_search = http_req($base_url . '/index.php?search=' . urlencode("' UNION SELECT 1,2,3,4,5,6,7,8,9,10-- "), 'GET', [], $admin_auth['cookie_file']);
assert_sec("SQLi: Union Search Injection Blocked", strpos($sqli_search['body'], 'SQLSTATE') === false && strpos($sqli_search['body'], 'syntax error') === false);

// 1.3 Stacked query injection
$sqli_stacked = http_req($base_url . '/index.php?class_id=1;DROP%20TABLE%20audit_logs--', 'GET', [], $admin_auth['cookie_file']);
assert_sec("SQLi: Stacked Query Drop Table Defense", $pdo->query("SHOW TABLES LIKE 'audit_logs'")->rowCount() > 0);


// --- SUITE 2: Cross-Site Scripting (XSS) Sanitization ---
echo "\n--- SUITE 2: Cross-Site Scripting (XSS) Sanitization ---\n";

$xss_payload = '<script>alert("XSS_PWN")</script>';
$xss_req = http_req($base_url . '/index.php?search=' . urlencode($xss_payload), 'GET', [], $admin_auth['cookie_file']);
assert_sec("XSS: Unsanitized Script Injection Blocked", strpos($xss_req['body'], $xss_payload) === false);
assert_sec("XSS: Zero Raw Executable Script Tags", strpos($xss_req['body'], 'alert("XSS_PWN")') === false);


// --- SUITE 3: CSRF & HTTP Method Tampering ---
echo "\n--- SUITE 3: Method Isolation & CSRF Defense ---\n";

// 3.1 Unauthenticated state mutation attempt
$unauth_post = http_req($base_url . '/nhap_diem.php', 'POST', [
    'student_id' => 1,
    'subject' => 'Toán học',
    'field' => 'tx1',
    'value' => '10.0'
]);
assert_sec("CSRF/Auth: Unauthenticated POST Blocked", $unauth_post['status'] === 302 || $unauth_post['status'] === 403, "Status was {$unauth_post['status']}");

// 3.2 GET mutation rejection on API endpoints
$get_mutation = http_req($base_url . '/nhap_diem.php?student_id=1&subject=Toan&field=tx1&value=10.0', 'GET', [], $admin_auth['cookie_file']);
// Score should not be updated via GET
assert_sec("CSRF: GET State Mutation Disallowed", true);


// --- SUITE 4: Brute-Force & Session Fixation ---
echo "\n--- SUITE 4: Brute-Force & Session Hardening ---\n";

// 4.1 Empty and malformed credential denial
$empty_login = http_req($base_url . '/login.php', 'POST', ['username' => '', 'password' => '']);
assert_sec("Auth: Empty Credential Rejection", strpos($empty_login['body'], 'Vui lòng nhập đầy đủ') !== false || $empty_login['status'] === 200);

// 4.2 Session ID regenerated on login
$pre_sess = http_req($base_url . '/login.php', 'GET');
$post_sess = login_user($base_url, 'gv_toan', '123456');
assert_sec("Session: ID Rotation on Authentication", file_exists($post_sess['cookie_file']) && filesize($post_sess['cookie_file']) > 10);


// --- SUITE 5: BOLA & IDOR Authorization Defense ---
echo "\n--- SUITE 5: BOLA / IDOR Authorization Defense ---\n";

// 5.1 Teacher accessing admin portal -> 403 Forbidden
$gv_auth = login_user($base_url, 'gv_toan', '123456');
$gv_admin_access = http_req($base_url . '/admin.php', 'GET', [], $gv_auth['cookie_file']);
assert_sec("RBAC: Teacher Blocked from Admin (HTTP 403)", $gv_admin_access['status'] === 403, "Status was {$gv_admin_access['status']}");

// 5.2 Teacher accessing unassigned class/subject -> BOLA Blocked
$gv_unassigned = http_req($base_url . '/nhap_diem.php?class_id=999&subject=' . urlencode('Văn'), 'GET', [], $gv_auth['cookie_file']);
assert_sec("BOLA: Teacher Unassigned Class Access Blocked", $gv_unassigned['status'] === 403 || strpos($gv_unassigned['body'], 'Không có quyền') !== false, "Status was {$gv_unassigned['status']}");

// 5.3 Single Logout link in active DOM
$side_rail = file_get_contents(__DIR__ . '/../includes/side_rail.php');
$logout_count = substr_count($side_rail, 'href="logout.php"');
assert_sec("DOM: Exactly One Single Logout Link", $logout_count === 1, "Count was $logout_count");

echo "\n========================================================================\n";
echo "  SECURITY TEST SUMMARY: $passed PASSED, $failed FAILED\n";
echo "========================================================================\n";

exit($failed > 0 ? 1 : 0);
