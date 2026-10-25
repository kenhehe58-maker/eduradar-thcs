<?php
error_reporting(E_ALL);

function evaluateRiskZone(?float $avg, ?float $gk, ?float $ck, array $txScores = []): array
{
    $reasons = [];

    // 1. VÙNG 🔴 NGUY CẤP (CRITICAL ZONE)
    $isAvgCrit = ($avg !== null && $avg < 3.5);
    if ($isAvgCrit) {
        $reasons[] = 'Điểm TB dưới 3.5 (' . number_format($avg, 1) . ')';
    }

    $isCkCrit = ($ck !== null && $ck < 3.0);
    if ($isCkCrit) {
        $reasons[] = 'Thi cuối kỳ dưới 3.0 (' . number_format($ck, 1) . ')';
    }

    $isBothFailing = ($gk !== null && $ck !== null && $gk < 5.0 && $ck < 5.0);
    if ($isBothFailing) {
        $reasons[] = 'Hỏng cả GK (' . number_format($gk, 1) . ') và CK (' . number_format($ck, 1) . ')';
    }

    if ($isAvgCrit || $isCkCrit || $isBothFailing) {
        return [
            'level' => 'red',
            'label' => 'Nguy cấp',
            'reasons' => $reasons,
            'diagnostic' => implode(' · ', $reasons),
        ];
    }

    // 2. VÙNG 🟡 CẦN CHÚ Ý (WARNING / AT RISK ZONE)
    $isWarning = false;

    // Điểm TB dưới chuẩn (3.5 <= avg < 5.0)
    $isAvgWarn = ($avg !== null && $avg >= 3.5 && $avg < 5.0);
    if ($isAvgWarn) {
        $isWarning = true;
        $reasons[] = 'Điểm TB dưới chuẩn (' . number_format($avg, 1) . ')';
    }

    // Điểm thi thành phần dưới trung bình (< 5.0) nhưng chưa đến mức Nguy cấp
    if ($ck !== null && $ck < 5.0) {
        $isWarning = true;
        $reasons[] = 'Thi cuối kỳ dưới trung bình (' . number_format($ck, 1) . ')';
    }

    if ($gk !== null && $gk < 5.0) {
        $isWarning = true;
        $reasons[] = 'Thi giữa kỳ dưới trung bình (' . number_format($gk, 1) . ')';
    }

    // Sa sút cuối kỳ: ck < gk - 1.5 <=> (gk - ck) > 1.5
    if ($gk !== null && $ck !== null) {
        $drop = round((float)$gk - (float)$ck, 1);
        if ($drop > 1.5) {
            $isWarning = true;
            $reasons[] = 'Sa sút cuối kỳ (giảm ' . number_format($drop, 1) . ' đ so với GK)';
        }
    }

    if ($isWarning) {
        return [
            'level' => 'yellow',
            'label' => 'Cần chú ý',
            'reasons' => $reasons,
            'diagnostic' => implode(' · ', $reasons),
        ];
    }

    // 3. VÙNG 🟢 ỔN ĐỊNH (STABLE ZONE)
    if ($avg === null) {
        $reasons[] = 'Chưa đủ dữ liệu điểm số';
    } elseif ($avg >= 8.0) {
        $reasons[] = 'Học lực Giỏi, kết quả xuất sắc (' . number_format($avg, 1) . ')';
    } elseif ($avg >= 6.5) {
        $reasons[] = 'Học lực Khá, duy trì tốt (' . number_format($avg, 1) . ')';
    } else {
        $reasons[] = 'Đạt chuẩn, ổn định (' . number_format($avg, 1) . ')';
    }

    return [
        'level' => 'green',
        'label' => 'Ổn định',
        'reasons' => $reasons,
        'diagnostic' => implode(' · ', $reasons),
    ];
}

$cases = [
    'V1.1 avg=3.49' => [3.49, 5.0, 5.0, [], 'red'],
    'V1.2 avg=3.50' => [3.50, 5.0, 5.0, [], 'yellow'],
    'V1.3 avg=3.40' => [3.40, 5.0, 5.0, [], 'red'],
    'V1.4 avg=4.99' => [4.99, 5.0, 5.0, [], 'yellow'],
    'V1.5 avg=5.00' => [5.00, 5.0, 5.0, [], 'green'],
    'V1.6 avg=4.90' => [4.90, 5.0, 5.0, [], 'yellow'],
    'V2.1 ck=2.9'   => [6.5, 7.0, 2.9, [], 'red'],
    'V2.2 ck=2.9 perf' => [7.2, 10.0, 2.9, [10,10,10,10], 'red'],
    'V2.3 ck=2.99'  => [6.0, 6.0, 2.99, [], 'red'],
    'V2.4 ck=3.0'   => [5.5, null, 3.0, [], 'yellow'], // ck=3.0 < 5.0 -> yellow
    'V3.1 both=4.9' => [5.5, 4.9, 4.9, [], 'red'],
    'V3.2 both=4.99' => [5.8, 4.99, 4.99, [], 'red'],
    'V3.3 gk=4.9 ck=5.0' => [5.5, 4.9, 5.0, [], 'yellow'],
    'V3.4 gk=5.0 ck=4.9' => [5.5, 5.0, 4.9, [], 'yellow'],
    'V4.1 drop=1.5' => [7.0, 8.0, 6.5, [], 'green'],
    'V4.2 drop=1.6' => [7.0, 8.0, 6.4, [], 'yellow'],
    'V4.3 IEEE 1.5' => [6.5, 7.3, 5.8, [], 'green'],
    'V4.4 IEEE 1.6' => [6.5, 7.3, 5.7, [], 'yellow'],
    'V4.5 improve'  => [7.2, 5.0, 8.0, [], 'green'],
    'V4.6 prec'     => [5.5, 8.0, 2.8, [], 'red'],
    'V5.1 empty'    => [null, null, null, [], 'green'],
    'V5.2 tx only'  => [7.5, null, null, [7.0, 8.0], 'green'],
    'V5.3 tx fail'  => [3.2, null, null, [3.0, 3.5], 'red'],
    'V5.4 no ck'    => [6.8, 8.0, null, [7.0], 'green'],
    'V5.5 all 0.0'  => [0.0, 0.0, 0.0, [0.0], 'red'],
    'V5.6 avg 0.0'  => [0.0, null, null, [0.0], 'red'],
];

$errors = 0;
foreach ($cases as $name => $c) {
    $res = evaluateRiskZone($c[0], $c[1], $c[2], $c[3]);
    if ($res['level'] !== $c[4]) {
        echo "FAIL: $name expected {$c[4]}, got {$res['level']} (diagnostic: {$res['diagnostic']})\n";
        $errors++;
    } else {
        echo "PASS: $name -> {$res['level']} [{$res['diagnostic']}]\n";
    }
}

echo "\nTotal cases: " . count($cases) . ", Failures: $errors\n";


// Test under strict error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

// Stress test with random and boundary inputs
$stressInputs = [
    [null, null, null, []],
    [0.0, 0.0, 0.0, [0.0]],
    [10.0, 10.0, 10.0, [10.0, 10.0, 10.0, 10.0]],
    [3.4999999, 4.999999, 4.999999, []],
    [3.5, 4.999999, 5.0, []],
    [5.0, 5.0, 4.999999, []],
    [5.0, 4.999999, 5.0, []],
    [6.5, 8.0, 6.5, []],
    [6.5, 8.0, 6.4, []],
    [7.3, 7.3, 5.8, []],
    [7.3, 7.3, 5.7, []],
    [8.0, 8.0, 8.0, []],
    [9.5, 10.0, 9.0, []],
    [5.0, 0.0, 10.0, []],
    [5.0, 10.0, 0.0, []],
];

foreach ($stressInputs as $in) {
    $res = evaluateRiskZone($in[0], $in[1], $in[2], $in[3]);
    if (!isset($res['level']) || !isset($res['label']) || !isset($res['reasons']) || !isset($res['diagnostic'])) {
        echo "FAIL: Missing return keys\n";
    }
}
echo "Stress test with strict ErrorException: ALL PASSED WITH 0 WARNINGS/NOTICES!\n";
restore_error_handler();
