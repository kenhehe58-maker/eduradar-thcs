<?php
$projectRoot = dirname(__DIR__, 3);
require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/includes/db.php';

// Extract evaluateRiskZone from index.php
$indexCode = file_get_contents($projectRoot . '/index.php');
if (preg_match('/function evaluateRiskZone\s*\(.*?return\s*\[\s*\'level\'\s*=>\s*\'green\'.*?\s*\];\s*\}/s', $indexCode, $match)) {
    eval($match[0]);
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    echo "Testing evaluateRiskZone:\n";
    $res1 = evaluateRiskZone(3.2, 5.0, 5.0);
    echo "3.2, 5.0, 5.0 -> level=" . $res1['level'] . ", reasons=" . json_encode($res1['reasons'], JSON_UNESCAPED_UNICODE) . "\n";
    $res2 = evaluateRiskZone(4.2, 5.5, 5.0);
    echo "4.2, 5.5, 5.0 -> level=" . $res2['level'] . ", reasons=" . json_encode($res2['reasons'], JSON_UNESCAPED_UNICODE) . "\n";
    $res3 = evaluateRiskZone(6.2, 8.0, 6.0);
    echo "6.2, 8.0, 6.0 -> level=" . $res3['level'] . ", reasons=" . json_encode($res3['reasons'], JSON_UNESCAPED_UNICODE) . "\n";
    $res4 = evaluateRiskZone(7.5, 8.0, 7.5);
    echo "7.5, 8.0, 7.5 -> level=" . $res4['level'] . ", reasons=" . json_encode($res4['reasons'], JSON_UNESCAPED_UNICODE) . "\n";
} else {
    echo "Could not extract evaluateRiskZone\n";
}
