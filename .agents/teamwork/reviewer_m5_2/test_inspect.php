<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once 'D:/New folder/my-web-project/includes/config.php';
require_once 'D:/New folder/my-web-project/includes/db.php';

// Test evaluateRiskZone directly from index.php
// We extract evaluateRiskZone from index.php
$indexCode = file_get_contents('D:/New folder/my-web-project/index.php');
// Extract the function evaluateRiskZone
$start = strpos($indexCode, 'function evaluateRiskZone');
$end = strpos($indexCode, 'function evaluateEarlyWarning');
$fnCode = substr($indexCode, $start, $end - $start);
eval($fnCode);

echo "Testing evaluateRiskZone with (4.0, 5.0, 5.0):\n";
$res1 = evaluateRiskZone(4.0, 5.0, 5.0);
print_r($res1);

echo "\nTesting evaluateRiskZone with (7.0, 7.0, 7.0):\n";
$res2 = evaluateRiskZone(7.0, 7.0, 7.0);
print_r($res2);

echo "\nTesting evaluateRiskZone with (6.0, 8.0, 6.0) (drop 2.0):\n";
$res3 = evaluateRiskZone(6.0, 8.0, 6.0);
print_r($res3);

echo "\nTesting evaluateRiskZone with (null, null, null):\n";
$res4 = evaluateRiskZone(null, null, null);
print_r($res4);

echo "\nTesting evaluateRiskZone with (3.2, 5.0, 5.0):\n";
$res5 = evaluateRiskZone(3.2, 5.0, 5.0);
print_r($res5);


// Test live Apache response
$cookie = __DIR__ . '/test_cookie.txt';
// Login
$ch = curl_init('http://localhost/my-web-project/login.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(['username' => 'admin', 'password' => '123456']),
    CURLOPT_COOKIEJAR => $cookie,
    CURLOPT_COOKIEFILE => $cookie,
]);
curl_exec($ch);
curl_close($ch);

// GET index.php
$ch = curl_init('http://localhost/my-web-project/index.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookie,
    CURLOPT_COOKIEFILE => $cookie,
]);
$html = curl_exec($ch);
curl_close($ch);
@unlink($cookie);

if (stripos($html, 'Warning') !== false || stripos($html, 'Notice') !== false || stripos($html, 'Fatal') !== false) {
    echo "\nFOUND ERROR IN LIVE HTML:\n";
    preg_match_all('/<b[^>]*>(Warning|Notice|Fatal error)<\/b>:.*?(<br\s*\/?>|$)/i', $html, $matches);
    print_r($matches[0]);
} else {
    echo "\nNO WARNING PRINTED IN HTML (display_errors may be off in Apache php.ini)\n";
}
