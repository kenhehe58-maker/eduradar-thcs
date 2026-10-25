<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Test evaluateRiskZone definition in index.php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/auth.php';

$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['username'] = 'admin';

ob_start();
include __DIR__ . '/../../../index.php';
$html = ob_get_clean();

echo "HTML generated length: " . strlen($html) . "\n";
echo "Testing evaluateRiskZone directly:\n";

// Test various student scores
$testCases = [
    [4.0, 6.0, 6.0, 'Yellow (avg 4.0)'],
    [6.0, 8.0, 6.0, 'Yellow (drop 2.0)'],
    [7.0, 8.0, 8.0, 'Green (avg 7.0)'],
    [3.0, 5.0, 5.0, 'Red (avg 3.0)'],
    [null, null, null, 'Green (null)'],
];

foreach ($testCases as $tc) {
    list($avg, $gk, $ck, $desc) = $tc;
    $res = evaluateRiskZone($avg, $gk, $ck);
    echo "$desc -> level: " . $res['level'] . " diagnostic: " . $res['diagnostic'] . "\n";
}
