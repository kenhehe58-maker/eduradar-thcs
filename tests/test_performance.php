<?php
/**
 * =============================================================================
 * EduRadar THCS — Performance, Memory & Stress Test Suite (test_performance.php)
 * Scenarios: High-concurrency calculation, RAM peak usage, Export XLSX/PDF,
 *            Database query latency, Large dataset scalability.
 * =============================================================================
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/radar_helper.php';
require_once __DIR__ . '/../includes/algorithms.php';
require_once __DIR__ . '/../includes/xlsx_helper.php';

$pdo = db();
$passed = 0;
$failed = 0;

function assert_perf(string $title, bool $condition, string $detail = '') {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] $title\n";
    } else {
        $failed++;
        echo "  [FAIL] $title: $detail\n";
    }
}

echo "========================================================================\n";
echo "  EDURADAR THCS — SYSTEM PERFORMANCE & STRESS BENCHMARK SUITE\n";
echo "========================================================================\n\n";

// --- SUITE 1: Algorithm Throughput & Batch Calculation ---
echo "--- SUITE 1: Throughput & Mathematical Latency ---\n";

$start_time = microtime(true);
$iterations = 1000;

for ($i = 0; $i < $iterations; $i++) {
    $tx = [rand(40, 90) / 10, rand(40, 90) / 10, rand(40, 90) / 10, rand(40, 90) / 10];
    $gk = rand(40, 90) / 10;
    $ck = rand(40, 90) / 10;
    $profile = diagnose_student_mathematical_profile($tx, $gk, $ck);
}

$elapsed_ms = (microtime(true) - $start_time) * 1000;
$avg_latency_us = ($elapsed_ms / $iterations) * 1000;

assert_perf("Throughput: 1,000 Full Diagnoses in < 150ms", $elapsed_ms < 150.0, "Total: " . round($elapsed_ms, 2) . "ms");
assert_perf("Latency: Average Diagnostic Latency < 150µs", $avg_latency_us < 150.0, "Avg: " . round($avg_latency_us, 2) . "µs/student");


// --- SUITE 2: Memory Footprint & Leak Detection ---
echo "\n--- SUITE 2: Memory Footprint & Leak Detection ---\n";

$mem_start = memory_get_usage();

// Simulate full gradebook with 500 students in memory
$large_dataset = [];
for ($i = 0; $i < 500; $i++) {
    $large_dataset[] = [
        'id' => $i + 1,
        'code' => 'HS' . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
        'name' => 'Học sinh ' . ($i + 1),
        'tx1' => 7.0, 'tx2' => 7.5, 'tx3' => 8.0, 'tx4' => 8.5,
        'gk' => 8.0, 'ck' => 8.5,
        'avg' => 8.0
    ];
}

$diag_results = [];
foreach ($large_dataset as $st) {
    $diag_results[] = diagnose_student_mathematical_profile([$st['tx1'], $st['tx2'], $st['tx3'], $st['tx4']], $st['gk'], $st['ck']);
}

$mem_peak = memory_get_peak_usage() / 1024 / 1024; // MB
assert_perf("Memory: Peak RAM < 25 MB for 500 Students", $mem_peak < 25.0, "Peak: " . round($mem_peak, 2) . " MB");
assert_perf("Scalability: Batch 500 Records Retained Cleanly", count($diag_results) === 500);

unset($large_dataset, $diag_results);


// --- SUITE 3: Database Query Latency & Index Efficiency ---
echo "\n--- SUITE 3: Database Query Latency & Index Efficiency ---\n";

$db_start = microtime(true);
$stmt = $pdo->prepare("
    SELECT s.id, s.full_name, c.name as class_name, sc.subject, sc.avg_score 
    FROM students s
    JOIN classes c ON s.class_id = c.id
    LEFT JOIN scores sc ON sc.student_id = s.id
    WHERE c.grade = 9
    LIMIT 100
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$db_latency_ms = (microtime(true) - $db_start) * 1000;

assert_perf("Database: Indexed Multi-Table Join < 20ms", $db_latency_ms < 20.0, "Latency: " . round($db_latency_ms, 2) . "ms");
assert_perf("Database: Rows Returned Correctly", count($rows) > 0);


// --- SUITE 4: Export Generator Efficiency (XLSX & Remedial Slip) ---
echo "\n--- SUITE 4: Export Engine Performance ---\n";

// Test 4.1: OpenXML / XLSX stream generator
$export_rows = [
    ['Mã HS', 'Họ và Tên', 'Lớp', 'TX1', 'TX2', 'TX3', 'TX4', 'GK', 'CK', 'ĐTB']
];
for ($i = 1; $i <= 45; $i++) {
    $export_rows[] = ["HS$i", "Học sinh số $i", "9A1", 7.0, 7.5, 8.0, 8.5, 8.0, 8.5, 8.1];
}

$xlsx_start = microtime(true);
$xlsx_bytes = create_xlsx($export_rows, 'BangDiem');
$xlsx_latency_ms = (microtime(true) - $xlsx_start) * 1000;

assert_perf("XLSX Generator: 45-Student Sheet Generated in < 50ms", $xlsx_latency_ms < 50.0, "Latency: " . round($xlsx_latency_ms, 2) . "ms");
assert_perf("XLSX Binary: Size > 1 KB Valid Zip Signature", strlen($xlsx_bytes) > 1024 && substr($xlsx_bytes, 0, 4) === "PK\x03\x04", "Size: " . strlen($xlsx_bytes) . " bytes");

// Test 4.2: Remedial Slip PDF / Print Template Generation (with auth)
$cookie_file = sys_get_temp_dir() . '/perf_auth_' . uniqid() . '.txt';
$login_ch = curl_init('http://localhost/my-web-project/login.php');
curl_setopt($login_ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($login_ch, CURLOPT_POST, true);
curl_setopt($login_ch, CURLOPT_POSTFIELDS, http_build_query(['username' => 'admin', 'password' => '123456']));
curl_setopt($login_ch, CURLOPT_COOKIEJAR, $cookie_file);
curl_setopt($login_ch, CURLOPT_COOKIEFILE, $cookie_file);
curl_setopt($login_ch, CURLOPT_FOLLOWLOCATION, false);
curl_exec($login_ch);
curl_close($login_ch);

// Find first student in database
$first_st = $pdo->query("SELECT s.id, sc.subject FROM students s JOIN scores sc ON sc.student_id = s.id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$st_id = $first_st ? (int)$first_st['id'] : 1;
$sub_name = $first_st ? $first_st['subject'] : 'Toán học';

$print_url = 'http://localhost/my-web-project/in_phieu_phu_dao.php?student_id=' . $st_id . '&subject=' . urlencode($sub_name);
$ch = curl_init($print_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$print_start = microtime(true);
$print_html = curl_exec($ch);
$print_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$print_latency_ms = (microtime(true) - $print_start) * 1000;
curl_close($ch);
if (file_exists($cookie_file)) unlink($cookie_file);

assert_perf("Print Generator: Remedial Slip Render < 100ms", $print_latency_ms < 100.0, "Latency: " . round($print_latency_ms, 2) . "ms");
assert_perf("Print Generator: HTTP 200 & Non-Empty Slip", $print_code === 200 && strlen($print_html) > 500, "Code: $print_code, Length: " . strlen($print_html));

echo "\n========================================================================\n";
echo "  PERFORMANCE TEST SUMMARY: $passed PASSED, $failed FAILED\n";
echo "========================================================================\n";

exit($failed > 0 ? 1 : 0);
