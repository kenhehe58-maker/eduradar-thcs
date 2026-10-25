<?php
require_once dirname(__DIR__, 3) . '/includes/radar_helper.php';

$cases = [
    // [name, avg, gk, ck, tx, expectedLevel, expectedLabel]
    ['Critical Avg < 3.5', 3.49, 10.0, 10.0, [], 'red', 'Nguy cấp'],
    ['Warning Avg = 3.5', 3.50, 10.0, 10.0, [], 'yellow', 'Cần chú ý'],
    ['Warning Avg = 4.99', 4.99, 10.0, 10.0, [], 'yellow', 'Cần chú ý'],
    ['Stable Avg = 5.0', 5.00, 10.0, 10.0, [], 'green', 'Ổn định'],
    ['Critical CK < 3.0 override on high avg', 8.5, 10.0, 2.99, [], 'red', 'Nguy cấp'],
    ['Warning CK = 3.0 on high avg', 8.5, 10.0, 3.00, [], 'yellow', 'Cần chú ý'],
    ['Critical Dual Exam Failure', 8.0, 4.99, 4.99, [], 'red', 'Nguy cấp'],
    ['Warning Single GK Failure', 8.0, 4.99, 5.00, [], 'yellow', 'Cần chú ý'],
    ['Warning Single CK Failure (V3.4)', 5.5, 5.00, 4.90, [], 'yellow', 'Cần chú ý'],
    ['Stable Exact Drop 1.5', 8.0, 8.0, 6.5, [], 'green', 'Ổn định'],
    ['Warning Drop 1.6', 8.0, 8.0, 6.4, [], 'yellow', 'Cần chú ý'],
    ['Stable IEEE 754 precision drop 7.3 - 5.8', 7.3, 7.3, 5.8, [], 'green', 'Ổn định'],
    ['Stable Completely Null', null, null, null, [], 'green', 'Ổn định'],
    ['Critical Null Avg but Dual Exam Fail', null, 4.0, 4.0, [], 'red', 'Nguy cấp'],
    ['Critical Null Avg but CK < 3.0', null, 8.0, 2.5, [], 'red', 'Nguy cấp'],
    ['Critical 0.0 scores', 0.0, 0.0, 0.0, [0.0], 'red', 'Nguy cấp'],
    ['Stable Perfect 10.0', 10.0, 10.0, 10.0, [10.0], 'green', 'Ổn định'],
    ['Stable Only Avg 6.5', 6.5, null, null, [], 'green', 'Ổn định'],
    ['Stable Avg 6.5 + GK 8.0 no CK', 6.5, 8.0, null, [], 'green', 'Ổn định'],
    ['Warning Avg 6.5 + CK 4.5 no GK', 6.5, null, 4.5, [], 'yellow', 'Cần chú ý'],
    ['Critical Avg 6.5 + CK 2.5 no GK', 6.5, null, 2.5, [], 'red', 'Nguy cấp'],
    ['Critical Priority over Yellow Avg', 4.5, 8.0, 2.0, [], 'red', 'Nguy cấp'],
];

$failed = 0;
foreach ($cases as $c) {
    [$name, $avg, $gk, $ck, $tx, $expLevel, $expLabel] = $c;
    $res = evaluateRiskZone($avg, $gk, $ck, $tx);
    $ok = ($res['level'] === $expLevel && $res['label'] === $expLabel);
    if (!$ok) {
        $failed++;
        echo "[FAIL] {$name}: Expected {$expLevel} ({$expLabel}), got {$res['level']} ({$res['label']}), diag: {$res['diagnostic']}" . PHP_EOL;
    } else {
        echo "[PASS] {$name}: {$res['level']} - {$res['diagnostic']}" . PHP_EOL;
    }
}

echo PHP_EOL . "Total: " . count($cases) . ", Failed: " . $failed . PHP_EOL;
exit($failed > 0 ? 1 : 0);
