<?php
// =============================================================================
// EduRadar THCS - Test Suite Xác Thực Toán Học & Thuật Toán Phân Tích KHKT
// =============================================================================

require_once __DIR__ . '/../includes/algorithms.php';
require_once __DIR__ . '/../includes/ocr_pipeline.php';

$passed = 0;
$failed = 0;

function assert_test(string $name, bool $condition, string $details = '') {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] $name\n";
    } else {
        $failed++;
        echo "  [FAIL] $name: $details\n";
    }
}

echo "========================================================================\n";
echo "  EDURADAR THCS - MATHEMATICAL ALGORITHM & OCR BENCHMARK TEST SUITE\n";
echo "========================================================================\n\n";

// --- SUITE 1: HỒI QUY TUYẾN TÍNH OLS & GIA TỐC HỌC TẬP ---
echo "--- SUITE 1: Linear Regression & Learning Velocity ---\n";
// Case 1: Điểm tăng dần đều: 4, 5, 6, 7 (k = 1.0, R^2 = 1.0, next = 8.0)
$reg1 = calculate_linear_regression([4.0, 5.0, 6.0, 7.0]);
assert_test("OLS Ascending Slope", abs($reg1['k'] - 1.0) < 0.001, "k={$reg1['k']}, expected 1.0");
assert_test("OLS Perfect Fit R2", abs($reg1['r2'] - 1.0) < 0.001, "r2={$reg1['r2']}, expected 1.0");
assert_test("OLS Predict Next", abs($reg1['predicted_next'] - 8.0) < 0.001, "next={$reg1['predicted_next']}, expected 8.0");
assert_test("OLS Trend Label", $reg1['trend'] === 'accelerating_up');

// Case 2: Điểm tụt dốc: 8, 6.5, 5, 3.5 (k = -1.5, next = 2.0)
$reg2 = calculate_linear_regression([8.0, 6.5, 5.0, 3.5]);
assert_test("OLS Descending Slope", $reg2['k'] < -1.0, "k={$reg2['k']}, expected < -1.0");
assert_test("OLS Drop Trend Label", $reg2['trend'] === 'steep_drop');

// Case 3: Điểm đi ngang: 7, 7, 7, 7 (k = 0.0, next = 7.0)
$reg3 = calculate_linear_regression([7.0, 7.0, 7.0, 7.0]);
assert_test("OLS Plateau Slope", abs($reg3['k']) < 0.001, "k={$reg3['k']}, expected 0.0");
assert_test("OLS Plateau Next", abs($reg3['predicted_next'] - 7.0) < 0.001, "next={$reg3['predicted_next']}");

// --- SUITE 2: ĐỘ LỆCH CHUẨN SIGMA & ĐỘ ỔN ĐỊNH PHONG ĐỘ ---
echo "\n--- SUITE 2: Standard Deviation & Stability Metrics ---\n";
// Case 1: Ổn định tuyệt đối: 6.5, 6.5, 6.5, 6.5 (sigma = 0.0)
$stab1 = calculate_stability_metrics([6.5, 6.5, 6.5, 6.5]);
assert_test("Zero Volatility StdDev", abs($stab1['std_dev'] - 0.0) < 0.001);
assert_test("Zero Volatility Level", $stab1['stability_level'] === 'very_high');

// Case 2: Biến động mạnh: 9.0, 4.0, 8.5, 4.5 (sigma > 2.0)
$stab2 = calculate_stability_metrics([9.0, 4.0, 8.5, 4.5]);
assert_test("High Volatility StdDev", $stab2['std_dev'] > 1.8, "sigma={$stab2['std_dev']}");
assert_test("High Volatility Level", $stab2['stability_level'] === 'high_volatility');

// --- SUITE 3: GIẢI NGƯỢC ĐIỂM SỐ MỤC TIÊU THÔNG TƯ 22 ---
echo "\n--- SUITE 3: Target Score Inverse Solver (TT22 Equation) ---\n";
// Case: Học sinh có TX = [5.0, 4.0, 5.0], GK = 5.0 (Tổng TX = 14, n=3, Mẫu số = 8)
// Để ĐTB = 5.0: ĐTB * 8 = 40. 40 - 14 - 2*5 = 16. CK = 16 / 3 = 5.333 -> 5.3
$targets1 = calculate_target_scores([5.0, 4.0, 5.0], 5.0);
assert_test("Solver Success Status", $targets1['status'] === 'success');
assert_test("Target Dat Required CK", abs($targets1['targets']['dat']['required_ck'] - 5.3) <= 0.1, "CK={$targets1['targets']['dat']['required_ck']}");
assert_test("Target Dat Feasibility", $targets1['targets']['dat']['feasibility'] === 'achievable');

// Case: Học sinh quá yếu: TX = [2.0, 2.0, 2.0], GK = 2.0 (Tổng TX = 6, n=3, Mẫu số = 8)
// Để đạt 8.0 (Tốt): 8 * 8 = 64. 64 - 6 - 4 = 54. CK = 54 / 3 = 18.0 (> 10 -> Bất khả thi)
$targets2 = calculate_target_scores([2.0, 2.0, 2.0], 2.0);
assert_test("Target Tot Impossible", $targets2['targets']['tot']['feasibility'] === 'impossible_ck_only');
assert_test("Pedagogical Alert Triggered", strpos($targets2['pedagogical_advice'], 'BÁO ĐỘNG ĐỎ') !== false);

// --- SUITE 4: THUẬT TOÁN LEVENSHTEIN FUZZY MATCHING ---
echo "\n--- SUITE 4: Levenshtein Distance & Fuzzy Roster Matching ---\n";
$roster = [
    ['id' => 101, 'student_code' => 'HS001', 'full_name' => 'Nguyễn Văn An'],
    ['id' => 102, 'student_code' => 'HS002', 'full_name' => 'Trần Thị Mai Phương'],
    ['id' => 103, 'student_code' => 'HS003', 'full_name' => 'Lê Hoàng Nam']
];

// Exact Match
$match1 = match_student_fuzzy('Nguyễn Văn An', $roster);
assert_test("Fuzzy Exact Match", $match1['student_id'] === 101 && $match1['similarity_percent'] == 100.0);

// Missing Tone / OCR Typing Error: "Nguyen Van An" -> should match "Nguyễn Văn An" with high similarity
$match2 = match_student_fuzzy('Nguyen Van An', $roster);
assert_test("Fuzzy Diacritics Recovery", $match2['student_id'] === 101 && $match2['similarity_percent'] >= 80.0, "sim={$match2['similarity_percent']}%");

// Typo: "Trần Thị Mai Phượng" vs "Trần Thị Mai Phương"
$match3 = match_student_fuzzy('Trần Thị Mai Phượng', $roster);
assert_test("Fuzzy Typo Tolerance", $match3['student_id'] === 102 && $match3['similarity_percent'] >= 90.0);

// Total Output
echo "\n========================================================================\n";
echo "  TEST SUMMARY: $passed PASSED, $failed FAILED\n";
echo "========================================================================\n";
exit($failed > 0 ? 1 : 0);
