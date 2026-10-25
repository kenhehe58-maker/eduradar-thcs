<?php
/**
 * Independent Forensic Verification Script for Milestone 5
 */
$baseUrl = 'http://localhost/my-web-project';
$cookieFile = sys_get_temp_dir() . '/audit_m5_' . uniqid() . '.txt';

function makeRequest($url, $method = 'GET', $data = [], $cookieFile = null) {
    $ch = curl_init();
    $opts = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 10,
    ];
    if ($cookieFile) {
        $opts[CURLOPT_COOKIEJAR] = $cookieFile;
        $opts[CURLOPT_COOKIEFILE] = $cookieFile;
    }
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($data);
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $info = curl_getinfo($ch);
    $headerSize = $info['header_size'];
    $headers = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);
    curl_close($ch);
    return [
        'code' => $info['http_code'],
        'headers' => $headers,
        'body' => $body,
    ];
}

echo "=== 1. Live Apache HTTP Responses & Error Scanning ===\n";

// 1.1 Unauthenticated index.php
$unauth = makeRequest($baseUrl . '/index.php');
echo "Unauthenticated index.php HTTP code: " . $unauth['code'] . " (Expected: 302)\n";

// 1.2 Login as admin
$loginAdmin = makeRequest($baseUrl . '/login.php', 'POST', ['username' => 'admin', 'password' => '123456'], $cookieFile);
echo "Admin login HTTP code: " . $loginAdmin['code'] . " (Expected: 302 redirect)\n";

$adminIndex = makeRequest($baseUrl . '/index.php', 'GET', [], $cookieFile);
echo "Admin index.php HTTP code: " . $adminIndex['code'] . " (Expected: 200)\n";
if (preg_match_all('/(?:<b[^>]*>)?Warning(?:<\/b>)?:.*?(?:<br|<\/p|<\/div|\n)/i', $adminIndex['body'], $m)) {
    echo "Found " . count($m[0]) . " warnings in admin index.php:\n";
    foreach (array_unique($m[0]) as $w) {
        echo "   -> " . strip_tags($w) . "\n";
    }
} else {
    echo "No warnings found in admin index.php.\n";
}

// 1.3 Login as gv_toan
@unlink($cookieFile);
$loginTeacher = makeRequest($baseUrl . '/login.php', 'POST', ['username' => 'gv_toan', 'password' => '123456'], $cookieFile);
echo "Teacher gv_toan login HTTP code: " . $loginTeacher['code'] . " (Expected: 302 redirect)\n";

$teacherIndex = makeRequest($baseUrl . '/index.php', 'GET', [], $cookieFile);
echo "Teacher index.php HTTP code: " . $teacherIndex['code'] . " (Expected: 200)\n";
if (preg_match_all('/(?:<b[^>]*>)?Warning(?:<\/b>)?:.*?(?:<br|<\/p|<\/div|\n)/i', $teacherIndex['body'], $m)) {
    echo "Found " . count($m[0]) . " warnings in teacher index.php:\n";
    foreach (array_unique($m[0]) as $w) {
        echo "   -> " . strip_tags($w) . "\n";
    }
} else {
    echo "No warnings found in teacher index.php.\n";
}

// Check teacher isolation:
$seesNguVan = stripos($teacherIndex['body'], 'Ngữ Văn') !== false;
echo "Teacher gv_toan sees 'Ngữ Văn': " . ($seesNguVan ? "YES (LEAK!)" : "NO (STRICT ISOLATION)") . "\n";

$seesAdminLink = stripos($teacherIndex['body'], 'admin.php') !== false;
echo "Teacher gv_toan sees 'admin.php' link: " . ($seesAdminLink ? "YES (LEAK!)" : "NO (STRICT RBAC)") . "\n";

// Check Zalo in live HTML:
$seesZalo = stripos($adminIndex['body'], 'zalo') !== false || stripos($teacherIndex['body'], 'zalo') !== false;
echo "Live HTML contains 'zalo': " . ($seesZalo ? "YES (VIOLATION!)" : "NO (CLEAN)") . "\n";

@unlink($cookieFile);
