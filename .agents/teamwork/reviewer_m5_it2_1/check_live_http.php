<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';
require_once dirname(__DIR__, 3) . '/includes/db.php';

function checkUser($user, $pass) {
    $cookie = tempnam(sys_get_temp_dir(), 'ck_');
    $ch = curl_init('http://localhost/my-web-project/login.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['username' => $user, 'password' => $pass]),
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_FOLLOWLOCATION => false
    ]);
    curl_exec($ch);
    curl_close($ch);

    $ch = curl_init('http://localhost/my-web-project/index.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_FOLLOWLOCATION => true
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    @unlink($cookie);

    $patterns = [
        '<b>Warning</b>', 'Warning:', '<b>Notice</b>', 'Notice:',
        '<b>Deprecated</b>', 'Deprecated:', 'Fatal error:', '<b>Fatal error</b>',
        'Parse error:', 'Undefined variable', 'Undefined array key', 'Undefined index',
        'Call to undefined', 'Uncaught Error', 'Uncaught Exception'
    ];
    $found = [];
    foreach ($patterns as $p) {
        if (stripos($body, $p) !== false) {
            $found[] = $p;
        }
    }
    echo "User [{$user}]: HTTP {$code}, length=" . strlen($body) . ", Detected Errors: " . (empty($found) ? '0 (100% CLEAN)' : implode(', ', $found)) . PHP_EOL;
}

echo "=== Live Apache HTTP Verification ===" . PHP_EOL;
checkUser('admin', '123456');
checkUser('gv_toan', '123456');
checkUser('gv_van', '123456');
