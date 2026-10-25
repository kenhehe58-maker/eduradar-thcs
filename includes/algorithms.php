<?php
// =============================================================================
// EduRadar THCS - Hạt Nhân Toán Học & Thuật Toán Phân Tích Động Năng Sư Phạm
// Bám sát: Thông tư 22/2021/TT-BGDĐT & Chuẩn Nghiên Cứu KHKT Quốc Gia
// =============================================================================

/**
 * 1. THUẬT TOÁN HỒI QUY TUYẾN TÍNH (ORDINARY LEAST SQUARES - OLS REGRESSION)
 * Phân tích chuỗi điểm số thường xuyên theo thời gian: (x1, y1), (x2, y2), ..., (xn, yn)
 * x_i = thứ tự bài kiểm tra (1, 2, ...), y_i = điểm số (0.0 -> 10.0)
 * 
 * Mô hình toán học: y = k*x + b
 * k: Hệ số góc (Trend Slope / Learning Velocity)
 * R^2: Hệ số xác định (Coefficient of Determination)
 * 
 * @param array $scores Danh sách điểm số hợp lệ
 * @return array ['k' => float, 'b' => float, 'r2' => float, 'trend' => string, 'predicted_next' => float]
 */
function calculate_linear_regression(array $scores): array {
    $validScores = array_values(array_filter($scores, fn($s) => $s !== null && is_numeric($s)));
    $n = count($validScores);
    
    if ($n < 2) {
        $single = $n === 1 ? (float)$validScores[0] : 0.0;
        return [
            'k' => 0.0,
            'b' => $single,
            'r2' => 0.0,
            'trend' => 'insufficient_data',
            'trend_label' => 'Chưa đủ dữ liệu chuỗi',
            'predicted_next' => $single
        ];
    }
    
    $sumX = 0.0;
    $sumY = 0.0;
    $sumXY = 0.0;
    $sumX2 = 0.0;
    $sumY2 = 0.0;
    
    for ($i = 0; $i < $n; $i++) {
        $x = (float)($i + 1);
        $y = (float)$validScores[$i];
        
        $sumX += $x;
        $sumY += $y;
        $sumXY += ($x * $y);
        $sumX2 += ($x * $x);
        $sumY2 += ($y * $y);
    }
    
    $denominator = ($n * $sumX2) - ($sumX * $sumX);
    if (abs($denominator) < 1e-9) {
        $k = 0.0;
        $b = $sumY / $n;
        $r2 = 0.0;
    } else {
        $k = (($n * $sumXY) - ($sumX * $sumY)) / $denominator;
        $b = ($sumY - ($k * $sumX)) / $n;
        
        // Tính R^2
        $yMean = $sumY / $n;
        $ssTotal = 0.0;
        $ssRes = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $x = (float)($i + 1);
            $y = (float)$validScores[$i];
            $yPred = ($k * $x) + $b;
            $ssTotal += pow($y - $yMean, 2);
            $ssRes += pow($y - $yPred, 2);
        }
        $r2 = ($ssTotal > 1e-9) ? max(0.0, min(1.0, 1.0 - ($ssRes / $ssTotal))) : 1.0;
    }
    
    // Dự báo điểm bài tiếp theo (x = n + 1), kẹp trong miền điểm [0.0, 10.0]
    $nextX = (float)($n + 1);
    $predictedNext = round(max(0.0, min(10.0, ($k * $nextX) + $b)), 1);
    
    // Đánh giá xu hướng dựa trên hệ số góc k
    if ($k >= 0.35) {
        $trend = 'accelerating_up';
        $trendLabel = 'Tăng tốc bứt phá mạnh (k = +' . round($k, 2) . ')';
    } elseif ($k >= 0.1) {
        $trend = 'steady_up';
        $trendLabel = 'Tiến bộ đều (k = +' . round($k, 2) . ')';
    } elseif ($k <= -0.35) {
        $trend = 'steep_drop';
        $trendLabel = 'Sa sút nghiêm trọng (k = ' . round($k, 2) . ')';
    } elseif ($k <= -0.1) {
        $trend = 'slight_drop';
        $trendLabel = 'Có chiều hướng giảm (k = ' . round($k, 2) . ')';
    } else {
        $trend = 'plateau';
        $trendLabel = 'Đi ngang ổn định (k = ' . round($k, 2) . ')';
    }
    
    return [
        'k' => round($k, 4),
        'b' => round($b, 4),
        'r2' => round($r2, 4),
        'trend' => $trend,
        'trend_label' => $trendLabel,
        'predicted_next' => $predictedNext
    ];
}

/**
 * 2. THUẬT TOÁN ĐO LƯỜNG ĐỘ ỔN ĐỊNH PHONG ĐỘ (STANDARD DEVIATION & VOLATILITY)
 * Tính Độ lệch chuẩn (sigma) và Phương sai (sigma^2) của chuỗi điểm số.
 * 
 * @param array $scores Danh sách điểm
 * @return array ['mean' => float, 'std_dev' => float, 'variance' => float, 'stability_level' => string]
 */
function calculate_stability_metrics(array $scores): array {
    $valid = array_values(array_filter($scores, fn($s) => $s !== null && is_numeric($s)));
    $n = count($valid);
    
    if ($n === 0) {
        return ['mean' => 0.0, 'std_dev' => 0.0, 'variance' => 0.0, 'stability_level' => 'unknown', 'label' => 'Chưa có điểm'];
    }
    if ($n === 1) {
        return ['mean' => round((float)$valid[0], 2), 'std_dev' => 0.0, 'variance' => 0.0, 'stability_level' => 'single_sample', 'label' => '1 điểm số duy nhất'];
    }
    
    $mean = array_sum($valid) / $n;
    $sumSqDiff = 0.0;
    foreach ($valid as $v) {
        $sumSqDiff += pow((float)$v - $mean, 2);
    }
    
    $variance = $sumSqDiff / $n;
    $stdDev = sqrt($variance);
    
    if ($stdDev <= 0.4) {
        $level = 'very_high';
        $label = 'Phong độ rất vững vàng (σ = ' . round($stdDev, 2) . ')';
    } elseif ($stdDev <= 0.85) {
        $level = 'normal';
        $label = 'Phong độ ổn định (σ = ' . round($stdDev, 2) . ')';
    } elseif ($stdDev <= 1.4) {
        $level = 'moderate_volatility';
        $label = 'Phong độ dao động vừa (σ = ' . round($stdDev, 2) . ')';
    } else {
        $level = 'high_volatility';
        $label = 'Phong độ trồi sụt thất thường (σ = ' . round($stdDev, 2) . ')';
    }
    
    return [
        'mean' => round($mean, 2),
        'std_dev' => round($stdDev, 2),
        'variance' => round($variance, 2),
        'stability_level' => $level,
        'label' => $label
    ];
}

/**
 * 3. THUẬT TOÁN GIẢI NGƯỢC ĐIỂM SỐ MỤC TIÊU THÔNG TƯ 22 (TARGET SCORE INVERSE SOLVER)
 * Phương trình Thông tư 22:
 * ĐTB = ( Tổng(TX) + 2*GK + 3*CK ) / ( n_TX + 2 + 3 )
 * 
 * Ta giải ngược tìm nghiệm CK_target để ĐTB >= T (với T = 5.0 [Đạt], 6.5 [Khá], 8.0 [Tốt]):
 * CK_target = ( T * (n_TX + 5) - Tổng(TX) - 2*GK ) / 3
 * 
 * @param array $txScores Danh sách điểm ĐĐGtx (1-4)
 * @param float|null $gk Điểm kiểm tra Giữa kỳ (hệ số 2)
 * @return array Bảng chỉ tiêu điểm Cuối kỳ cần đạt kèm khuyến nghị sư phạm
 */
function calculate_target_scores(array $txScores, ?float $gk): array {
    $validTx = array_values(array_filter($txScores, fn($s) => $s !== null && is_numeric($s)));
    $n = count($validTx);
    
    if ($n === 0) {
        return [
            'status' => 'insufficient_tx',
            'message' => 'Chưa có điểm kiểm tra thường xuyên để giải ngược mục tiêu',
            'targets' => []
        ];
    }
    
    $sumTx = array_sum($validTx);
    $hasGk = ($gk !== null && is_numeric($gk));
    $effectiveGk = $hasGk ? (float)$gk : round($sumTx / $n, 1); // Ước tính GK nếu chưa thi
    
    $targetsConfig = [
        'dat' => ['threshold' => 5.0, 'name' => 'Mức Đạt', 'color' => '#10b981', 'badge' => 'ĐẠT'],
        'kha' => ['threshold' => 6.5, 'name' => 'Mức Khá', 'color' => '#3b82f6', 'badge' => 'KHÁ'],
        'tot' => ['threshold' => 8.0, 'name' => 'Mức Tốt', 'color' => '#8b5cf6', 'badge' => 'TỐT']
    ];
    
    $totalDenominator = $n + 2 + 3; // n_TX + 2 (GK) + 3 (CK)
    $results = [];
    
    foreach ($targetsConfig as $key => $cfg) {
        $targetDtbm = $cfg['threshold'];
        // Công thức giải ngược đại số chính xác:
        $requiredCk = ($targetDtbm * $totalDenominator - $sumTx - (2 * $effectiveGk)) / 3.0;
        $requiredCkRounded = round($requiredCk, 1);
        
        if ($requiredCkRounded <= 0.0) {
            $feasibility = 'guaranteed';
            $feasibleText = 'Đã nắm chắc (Chỉ cần thi không bị 0)';
            $badgeColor = '#059669';
        } elseif ($requiredCkRounded <= 10.0) {
            $feasibility = 'achievable';
            $feasibleText = 'Khả thi trong tầm tay';
            $badgeColor = '#2563eb';
        } else {
            $feasibility = 'impossible_ck_only';
            $deficit = round($requiredCkRounded - 10.0, 1);
            $feasibleText = 'Vượt quá 10 điểm (+ ' . $deficit . 'đ) — Cần gỡ điểm TX';
            $badgeColor = '#dc2626';
        }
        
        $results[$key] = [
            'target_name' => $cfg['name'],
            'threshold' => $cfg['threshold'],
            'required_ck' => max(0.0, $requiredCkRounded),
            'raw_required' => $requiredCkRounded,
            'feasibility' => $feasibility,
            'feasibility_text' => $feasibleText,
            'badge_color' => $badgeColor,
            'has_gk_actual' => $hasGk
        ];
    }
    
    // Phân tích sư phạm chuyên sâu
    $pedagogicalAdvice = '';
    if ($results['dat']['feasibility'] === 'impossible_ck_only' || $results['dat']['required_ck'] >= 9.0) {
        $pedagogicalAdvice = '🚨 BÁO ĐỘNG ĐỎ SƯ PHẠM: Học sinh cần đạt điểm Cuối kỳ rất cao (>= ' . $results['dat']['required_ck'] . 'đ) mới đủ đạt chuẩn Đạt. Giáo viên bộ môn CẦN cho học sinh kiểm tra bổ sung cột ĐĐGtx (đổi bài hoặc gỡ điểm) ngay trong tuần này trước khi khóa sổ!';
    } elseif ($results['dat']['required_ck'] >= 6.5) {
        $pedagogicalAdvice = '⚠️ CẢNH BÁO NGUY CƠ: Học sinh cần đạt tối thiểu ' . $results['dat']['required_ck'] . ' điểm Cuối kỳ để Đạt chuẩn TT22. Cần phân công bạn khá kèm cặp phần kiến thức trọng tâm.';
    } elseif ($results['kha']['feasibility'] === 'achievable') {
        $pedagogicalAdvice = '🎯 TIỀM NĂNG BỨT PHÁ: Học sinh chỉ cần đạt ' . $results['kha']['required_ck'] . ' điểm Cuối kỳ là vươn lên mức Khá. Nên khích lệ tinh thần để em nỗ lực ôn tập.';
    } else {
        $pedagogicalAdvice = '🌟 PHONG ĐỘ XUẤT SẮC: Điểm số thường xuyên và giữa kỳ rất vững vàng. Hướng dẫn em các câu hỏi phân hóa nâng cao để chinh phục mức Tốt.';
    }
    
    return [
        'status' => 'success',
        'n_tx' => $n,
        'sum_tx' => round($sumTx, 1),
        'gk_used' => $effectiveGk,
        'is_gk_estimated' => !$hasGk,
        'targets' => $results,
        'pedagogical_advice' => $pedagogicalAdvice
    ];
}

/**
 * 4. TỔNG HỢP HỒ SƠ TOÁN HỌC & CHẨN ĐOÁN TOÀN DIỆN HỌC SINH
 * Kết hợp cả 3 thuật toán trên thành 1 profile phân tích hoàn chỉnh.
 */
function diagnose_student_mathematical_profile(array $txScores, ?float $gk, ?float $ck): array {
    $allScores = array_merge($txScores, array_filter([$gk, $ck], fn($s) => $s !== null));
    
    $regression = calculate_linear_regression($txScores);
    $stability = calculate_stability_metrics($allScores);
    $targetSolver = calculate_target_scores($txScores, $gk);
    
    return [
        'regression' => $regression,
        'stability' => $stability,
        'targets' => $targetSolver,
        'computed_at' => date('Y-m-d H:i:s')
    ];
}
