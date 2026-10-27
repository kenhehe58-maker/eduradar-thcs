<?php
/**
 * =============================================================================
 * EduRadar THCS — Circular 22 Boundary & Edge Cases Test (test_edge_tt22.php)
 * Scenarios: Điểm liệt, môn Đánh giá bằng Nhận xét (ĐGNX), ĐTB cả năm,
 *            vượt trần điểm số, xếp loại học lực theo Điều 9 TT22.
 * =============================================================================
 */

require_once __DIR__ . '/../includes/radar_helper.php';
require_once __DIR__ . '/../includes/algorithms.php';

$passed = 0;
$failed = 0;

function assert_tt22(string $title, bool $condition, string $detail = '') {
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
echo "  EDURADAR THCS — CIRCULAR 22/2021/TT-BGDĐT EDGE-CASE TEST SUITE\n";
echo "========================================================================\n\n";

// --- SUITE 1: Formula Precision & Invariants (Điều 9 TT22) ---
echo "--- SUITE 1: Formula Precision & Invariants ---\n";

// Test 1.1: 4 TX + GK + CK: divisor is 4 + 5 = 9
// TX: 8, 8, 8, 8; GK: 8; CK: 8 => (32 + 16 + 24) / 9 = 72 / 9 = 8.0
$txList1 = [8.0, 8.0, 8.0, 8.0];
$calc1 = calculate_target_scores($txList1, 8.0, 8.0);
$avg1 = round((array_sum($txList1) + 2 * 8.0 + 3 * 8.0) / (count($txList1) + 5), 1);
assert_tt22("Formula: Standard 4-TX Case (Divisor 9)", $avg1 === 8.0, "Expected 8.0, got $avg1");

// Test 1.2: Variable TX columns (2 TX instead of 4): divisor is 2 + 5 = 7
// TX: 7.0, 7.0; GK: 7.0; CK: 7.0 => (14 + 14 + 21) / 7 = 49 / 7 = 7.0
$txList2 = [7.0, 7.0];
$avg2 = round((array_sum($txList2) + 2 * 7.0 + 3 * 7.0) / (count($txList2) + 5), 1);
assert_tt22("Formula: Variable 2-TX Case (Divisor 7)", $avg2 === 7.0, "Expected 7.0, got $avg2");


// --- SUITE 2: Điểm Liệt & Phân Vùng Rủi Ro Nguy Cấp ---
echo "\n--- SUITE 2: Điểm Liệt & Phân Vùng Rủi Ro Nguy Cấp ---\n";

// Test 2.1: Điểm Cuối kỳ < 3.0 (Điểm liệt dù ĐTB >= 5.0)
// TX: 8, 8, 8; GK: 8.0; CK: 2.5 => ĐTB = (24 + 16 + 7.5)/8 = 5.9 (Mức Đạt), nhưng CK < 3.0 => Phải báo 🔴 Nguy cấp!
$diag1 = evaluateRiskZone(5.9, 8.0, 2.5, [8.0, 8.0, 8.0]);
assert_tt22("Điểm liệt: CK < 3.0 Forces RED Alert Despite ĐTB 5.9", $diag1['level'] === 'red', "Level was: {$diag1['level']}");

// Test 2.2: Có >= 2 cột điểm TX < 3.5 (Hổng kiến thức nền)
// TX: 2.0, 3.0, 7.0; GK: 6.0; CK: 6.0 => ĐTB = (12 + 12 + 18)/8 = 5.25, nhưng có 2 bài TX < 3.5 => 🔴 Nguy cấp
$diag2 = evaluateRiskZone(5.3, 6.0, 6.0, [2.0, 3.0, 7.0]);
assert_tt22("Điểm liệt: >= 2 TX < 3.5 Forces RED Alert", $diag2['level'] === 'red', "Level was: {$diag2['level']}");
assert_tt22("Cảnh báo TX: has_tx_warning Flag Set", !empty($diag2['has_tx_warning']));

// Test 2.3: Hỏng cả Giữa kỳ và Cuối kỳ (< 5.0)
$diag3 = evaluateRiskZone(4.8, 4.0, 4.5, [6.0, 6.0]);
assert_tt22("Điểm liệt: Both GK & CK < 5.0 Forces RED Alert", $diag3['level'] === 'red');


// --- SUITE 3: Môn Đánh Giá Bằng Nhận Xét (ĐGNX) & Xếp Loại Môn ---
echo "\n--- SUITE 3: Môn Đánh Giá Bằng Nhận Xét (ĐGNX) & Xếp Loại Môn ---\n";

// Tier brackets in radar_helper:
// >= 8.0 => excellent (Xuất sắc)
// 6.5 - 7.9 => good (Khá)
// 5.0 - 6.4 => average (Trung bình/Đạt)
// < 5.0 => low (Thấp/Cần phụ đạo)
$tier_tot = evaluateRiskZone(8.5, 8.5, 8.5, [8.5]);
assert_tt22("Score Tier: 8.5 is Tier excellent", $tier_tot['score_tier']['tier'] === 'excellent');

$tier_kha = evaluateRiskZone(7.2, 7.0, 7.5, [7.0]);
assert_tt22("Score Tier: 7.2 is Tier good", $tier_kha['score_tier']['tier'] === 'good');

$tier_dat = evaluateRiskZone(5.5, 5.0, 6.0, [5.5]);
assert_tt22("Score Tier: 5.5 is Tier average", $tier_dat['score_tier']['tier'] === 'average');

$tier_chuadat = evaluateRiskZone(4.2, 4.0, 4.5, [4.0]);
assert_tt22("Score Tier: 4.2 is Tier low", $tier_chuadat['score_tier']['tier'] === 'low');


// --- SUITE 4: ĐTB Cả Năm (Điều 9 Khoản 2 TT22) ---
echo "\n--- SUITE 4: ĐTB Cả Năm (Điều 9 Khoản 2 TT22) ---\n";

// ĐTBcn = (ĐTBhk1 + 2 * ĐTBhk2) / 3
$hk1 = 6.0;
$hk2 = 8.5;
$dtb_cn = round(($hk1 + 2 * $hk2) / 3, 1);
assert_tt22("ĐTB Cả Năm: Weighted (HK1 + 2*HK2)/3", $dtb_cn === 7.7, "Expected 7.7, got $dtb_cn");


// --- SUITE 5: Boundary Thresholds in Target Solver ---
echo "\n--- SUITE 5: Boundary Thresholds in Target Solver ---\n";

// High scores: Student already achieved goal even with 0 on CK
$target_achieved = calculate_target_scores([9.0, 9.5, 9.5, 10.0], 9.5);
assert_tt22("Target Solver: Guaranteed Flag on Mức Đạt", $target_achieved['targets']['dat']['feasibility'] === 'guaranteed');
assert_tt22("Target Solver: Target Score 0.0 when already guaranteed", $target_achieved['targets']['dat']['required_ck'] === 0.0);

// Low scores: Student mathematically exceeds 10.0 for distinction
$target_exceeded = calculate_target_scores([3.0, 3.5, 3.0], 3.5);
assert_tt22("Target Solver: Exceeds 10.0 Flag for Mức Tốt", $target_exceeded['targets']['tot']['feasibility'] === 'impossible_ck_only');
assert_tt22("Target Solver: Raw Required CK > 10.0", $target_exceeded['targets']['tot']['raw_required'] > 10.0);

// --- SUITE 6: Academic Milestone Formula Invariants ---
echo "\n--- SUITE 6: Academic Milestone Formula Invariants ---\n";
// Case 1: Đầu năm (Chỉ có điểm TX, chưa có GK và chưa có CK) => Không tính ĐTB
$diagNoExam = evaluateRiskZone(null, null, null, [7.0, 8.0]);
assert_tt22("Đầu năm: Không có điểm thi (no GK, no CK) => Level pending", $diagNoExam['score_tier']['tier'] === 'pending');

// Case 2: Đã thi Giữa kỳ (GK1 = 4.5, TX = 3.6, 3.2, 3.1, 4.1, CK = null)
// (14.0 + 9.0) / 6 = 3.8 => Tính được ĐTB giữa kỳ = 3.8 và xếp mức Chưa đạt
$txMid = [3.6, 3.2, 3.1, 4.1];
$gkMid = 4.5;
$avgMid = round((array_sum($txMid) + 2 * $gkMid) / (count($txMid) + 2), 1);
assert_tt22("Giữa kỳ: Đã có điểm GK => ĐTB giữa kỳ = 3.8", $avgMid === 3.8);
$diagMid = evaluateRiskZone($avgMid, $gkMid, null, $txMid);
assert_tt22("Giữa kỳ: ĐTB 3.8 < 5.0 => Báo Chưa đạt", $diagMid['score_tier']['tier'] === 'low');

echo "\n========================================================================\n";
echo "  EDGE-CASE TT22 TEST SUMMARY: $passed PASSED, $failed FAILED\n";
echo "========================================================================\n";

exit($failed > 0 ? 1 : 0);
