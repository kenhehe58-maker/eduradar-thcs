<?php
/**
 * =============================================================================
 * Hệ Thống Quản Lý Điểm & Cảnh Báo Sớm THCS (EduRadar)
 * Module: Động Cơ Chẩn Đoán Radar & Hệ Thống Đánh Giá Đa Chiều
 * 
 * HỆ THỐNG ĐÁNH GIÁ ĐA CHIỀU (DUAL-PERSPECTIVE ASSESSMENT):
 * 1. XU HƯỚNG TIẾN BỘ (Learning Trajectory / OLS) — [ƯU TIÊN THEO DÕI]:
 *    - Tăng (Tiến bộ rõ rệt): Bài sau cao hơn bài trước hoặc CK tiến bộ so với GK.
 *    - Đều (Duy trì đều): Điểm các bài đi đều đặn, không bị rơi rụng.
 *      * Logic cốt lõi: Khi học sinh có học lực ở mức Thấp hoặc Trung bình mà "đang đi đều"
 *        thì đây là "Duy trì ổn định, cần bồi dưỡng nâng cao" vì điểm còn trong khuôn khổ chưa cao.
 *    - Giảm (Sa sút / Cần can thiệp): Chuỗi điểm tụt dốc (như 10 -> 9 -> 8 -> 6) hoặc CK giảm sâu.
 *    - Chờ điểm: Chưa có bài kiểm tra ghi nhận, tách riêng để không tính ảo.
 *
 * 2. BẬC HỌC LỰC (Attainment / TT22 Level):
 *    - Tốt / Giỏi (ĐTB >= 8.0)
 *    - Khá (6.5 <= ĐTB < 8.0)
 *    - Đạt / Trung bình (5.0 <= ĐTB < 6.5)
 *    - Chưa Đạt / Cần hỗ trợ (ĐTB < 5.0, trong đó < 3.5 là Nguy cấp)
 *    - Chờ điểm (Chưa có điểm)
 *
 * 3. NĂNG LỰC TỔNG THỂ & KHUYẾN NGHỊ SƯ PHẠM:
 *    - Giao thoa giữa Xu Hướng Tiến Bộ và Bậc Học Lực (TT22).
 * =============================================================================
 */

declare(strict_types=1);

/**
 * Xác định Khung Điểm Số / Học Lực Tuyệt Đối (Score Tier / Mastery Bracket)
 *
 * @param float|null $avg Điểm trung bình môn học kỳ
 * @param float|null $gk  Điểm giữa kỳ
 * @param float|null $ck  Điểm cuối kỳ
 * @param array      $validTx Danh sách điểm TX hợp lệ
 * @return array
 */
function determineScoreTier(?float $avg, ?float $gk = null, ?float $ck = null, array $validTx = []): array
{
    // TT22: Đầu năm chưa có điểm thi (chưa có GK và chưa có CK) => Không tính ĐTBm, xếp loại Chờ điểm
    if ($avg === null && $gk === null && $ck === null) {
        return [
            'tier'       => 'pending',
            'label'      => 'Chờ điểm',
            'badge'      => 'Chờ điểm',
            'icon'       => 'pending',
            'class'      => 'tier-pending',
            'color'      => '#64748b',
            'bg'         => '#f1f5f9',
            'border'     => '#e2e8f0',
            'threshold'  => 'Đang cập nhật',
        ];
    }

    $score = $avg;
    if ($score === null) {
        if ($ck !== null && !empty($validTx)) {
            $sTx = array_sum($validTx);
            $nTx = count($validTx);
            $score = round(($sTx + 2 * ($gk ?? $ck) + 3 * $ck) / ($nTx + ($gk !== null ? 2 : 0) + 3), 1);
        } elseif ($gk !== null && !empty($validTx)) {
            $sTx = array_sum($validTx);
            $nTx = count($validTx);
            $score = round(($sTx + 2 * $gk) / ($nTx + 2), 1);
        }
    }

    if ($score === null) {
        return [
            'tier'       => 'pending',
            'label'      => 'Chờ điểm',
            'badge'      => 'Chờ điểm',
            'icon'       => 'pending',
            'class'      => 'tier-pending',
            'color'      => '#64748b',
            'bg'         => '#f1f5f9',
            'border'     => '#e2e8f0',
            'threshold'  => 'Đang cập nhật',
        ];
    }

    if ($score >= 8.0) {
        return [
            'tier'       => 'excellent',
            'label'      => 'Tốt',
            'badge'      => 'Tốt',
            'icon'       => '●',
            'class'      => 'tier-excellent',
            'color'      => '#16a34a',
            'bg'         => '#f0fdf4',
            'border'     => '#bbf7d0',
            'threshold'  => 'ĐTB ≥ 8.0',
        ];
    }

    if ($score >= 6.5) {
        return [
            'tier'       => 'good',
            'label'      => 'Khá',
            'badge'      => 'Khá',
            'icon'       => '●',
            'class'      => 'tier-good',
            'color'      => '#d97706',
            'bg'         => '#fefce8',
            'border'     => '#fef08a',
            'threshold'  => '6.5 ≤ ĐTB < 8.0',
        ];
    }

    if ($score >= 5.0) {
        return [
            'tier'       => 'average',
            'label'      => 'Đạt',
            'badge'      => 'Đạt',
            'icon'       => '●',
            'class'      => 'tier-average',
            'color'      => '#ea580c',
            'bg'         => '#fff7ed',
            'border'     => '#fed7aa',
            'threshold'  => '5.0 ≤ ĐTB < 6.5',
        ];
    }

    return [
        'tier'       => 'low',
        'label'      => $score < 3.5 ? 'Nguy cấp' : 'Chưa đạt',
        'badge'      => $score < 3.5 ? 'Nguy cấp' : 'Chưa đạt',
        'icon'       => '●',
        'class'      => $score < 3.5 ? 'tier-critical' : 'tier-low',
        'color'      => '#dc2626',
        'bg'         => '#fef2f2',
        'border'     => '#fecaca',
        'threshold'  => 'ĐTB < 5.0',
    ];
}

/**
 * Phân tích Xu Hướng Tiến Bộ (Learning Trajectory / OLS)
 * ĐO LƯỜNG TIẾN BỘ: Tăng (Tiến bộ) | Đều (Duy trì) | Giảm (Sa sút) | Chờ điểm
 *
 * @param float|null $avg Điểm trung bình môn học kỳ
 * @param float|null $gk  Điểm giữa kỳ
 * @param float|null $ck  Điểm cuối kỳ
 * @param array      $txScores Danh sách điểm TX (tx1..tx4)
 * @return array
 */
function analyzeLearningTrend(?float $avg, ?float $gk, ?float $ck, array $txScores = []): array
{
    $validTx = [];
    foreach ($txScores as $val) {
        if ($val !== null && $val !== '' && is_numeric($val)) {
            $validTx[] = round((float)$val, 1);
        }
    }

    $txCount = count($validTx);
    $hasAnyScore = ($txCount > 0 || $gk !== null || $ck !== null || $avg !== null);

    // TRƯỜNG HỢP ĐẶC BIỆT: CHƯA CÓ ĐIỂM SỐ NÀO (Tách riêng khỏi Duy Trì Đều để số liệu trung thực)
    if (!$hasAnyScore) {
        return [
            'momentum'          => 'pending',
            'trend'             => 'pending',
            'trend_label'       => 'Chờ điểm',
            'trend_icon'        => 'pending',
            'trend_reasons'     => ['Chưa có bài kiểm tra nào'],
            'trend_description' => 'Chưa có bài kiểm tra nào',
            'tx_stats'          => [
                'count'          => 0,
                'avg'            => null,
                'delta'          => null,
                'is_declining'   => false,
                'is_improving'   => false,
                'is_steady'      => false,
                'below_5_count'  => 0,
                'below_35_count' => 0,
            ],
        ];
    }

    $avgTx = $txCount > 0 ? round(array_sum($validTx) / $txCount, 1) : null;
    $txBelow5 = 0;
    $txBelow35 = 0;
    foreach ($validTx as $val) {
        if ($val < 5.0) $txBelow5++;
        if ($val < 3.5) $txBelow35++;
    }

    $txDelta = null;
    $isTxDeclining = false;
    $isTxImproving = false;
    $isTxSteady = false;
    $txProgressText = '';

    if ($txCount >= 2) {
        $firstTx = $validTx[0];
        $lastTx = end($validTx);
        $txDelta = round($lastTx - $firstTx, 1);

        $strictlyNonIncreasing = true;
        $strictlyNonDecreasing = true;
        for ($i = 1; $i < $txCount; $i++) {
            if ($validTx[$i] > $validTx[$i - 1]) {
                $strictlyNonIncreasing = false;
            }
            if ($validTx[$i] < $validTx[$i - 1]) {
                $strictlyNonDecreasing = false;
            }
        }

        if (($strictlyNonIncreasing && $txDelta < 0) || $txDelta <= -1.5) {
            $isTxDeclining = true;
            $txProgressText = "Điểm TX tụt dốc ({$firstTx} → {$lastTx}, giảm " . abs($txDelta) . "đ)";
        } elseif (($strictlyNonDecreasing && $txDelta > 0) || $txDelta >= 1.0) {
            $isTxImproving = true;
            $txProgressText = "Điểm TX tiến bộ ({$firstTx} → {$lastTx}, tăng +{$txDelta}đ)";
        } else {
            $isTxSteady = true;
            $txProgressText = "Điểm TX đi đều ổn định (" . implode(' · ', $validTx) . ")";
        }
    } elseif ($txCount === 1) {
        $isTxSteady = true;
        $txProgressText = "Khởi đầu bài TX1: {$validTx[0]}đ";
    }

    // Phân tích điểm thi định kỳ GK vs CK
    $examDelta = null;
    $isExamDrop = false;
    $isExamSurge = false;
    $isExamSteady = false;
    $examProgressText = '';

    if ($gk !== null && $ck !== null) {
        $examDelta = round($ck - $gk, 1);
        if ($examDelta < -1.5) {
            $isExamDrop = true;
            $examProgressText = "Thi CK sa sút (giảm " . abs($examDelta) . "đ so với GK: {$gk} → {$ck})";
        } elseif ($examDelta >= 1.0) {
            $isExamSurge = true;
            $examProgressText = "Tiến bộ cuối kỳ (+{$examDelta}đ so với GK: {$gk} → {$ck})";
        } else {
            $isExamSteady = true;
            $examProgressText = "Thi định kỳ giữ vững phong độ ({$gk} → {$ck})";
        }
    } elseif ($gk !== null && $ck === null && $avgTx !== null) {
        // GIAI ĐOẠN VỪA THI GIỮA KỲ XONG (CHƯA THI CUỐI KỲ): So sánh GK với điểm trung bình TX
        $examDelta = round($gk - $avgTx, 1);
        if ($examDelta < -1.5) {
            $isExamDrop = true;
            $examProgressText = "Thi Giữa kỳ sút giảm (giảm " . abs($examDelta) . "đ so với TX: {$avgTx} → {$gk})";
        } elseif ($examDelta >= 1.0) {
            $isExamSurge = true;
            $examProgressText = "Thi Giữa kỳ bứt phá (+{$examDelta}đ so với TX: {$avgTx} → {$gk})";
        } else {
            $isExamSteady = true;
            $examProgressText = "Thi Giữa kỳ ổn định phong độ ({$avgTx} → {$gk})";
        }
    }

    // Lệch pha phong độ (TX vs Thi)
    $isDivergentDrop = false;
    $isDivergentSurge = false;
    if ($avgTx !== null && $avgTx >= 7.5 && (($gk !== null && $gk < 5.0) || ($ck !== null && $ck < 5.0))) {
        $isDivergentDrop = true;
    }
    if ($avgTx !== null && $avgTx < 5.0) {
        if ($gk !== null && $ck !== null && $gk >= 6.5 && $ck >= 6.5) {
            $isDivergentSurge = true;
        } elseif ($ck === null && $gk !== null && $gk >= 6.5) {
            $isDivergentSurge = true;
        }
    }

    // TỔNG HỢP KHUNG TIẾN BỘ (MOMENTUM)
    $trendReasons = [];
    $momentum = 'steady';
    $trend = 'stable';
    $trendLabel = 'Duy trì ổn định';
    $trendIcon = 'steady';

    if ($isTxDeclining || $isExamDrop || $isDivergentDrop) {
        $momentum = ($txDelta !== null && $txDelta <= -2.5) || ($examDelta !== null && $examDelta <= -2.5) ? 'drop' : 'down';
        $trend = 'down';
        $trendLabel = $momentum === 'drop' ? 'Báo động tụt dốc' : 'Sa sút liên tục';
        $trendIcon = 'down';
        if ($isTxDeclining) $trendReasons[] = $txProgressText;
        if ($isExamDrop) $trendReasons[] = $examProgressText;
        if ($isDivergentDrop) $trendReasons[] = "Học TX tốt (" . number_format($avgTx, 1) . ") nhưng thi cử sút giảm bất thường";
    } elseif ($isTxImproving || $isExamSurge || $isDivergentSurge) {
        $momentum = ($txDelta !== null && $txDelta >= 2.0) || ($examDelta !== null && $examDelta >= 2.0) ? 'surge' : 'up';
        $trend = 'up';
        $trendLabel = $momentum === 'surge' ? 'Tiến bộ vượt bậc' : 'Tiến bộ rõ rệt';
        $trendIcon = 'up';
        if ($isTxImproving) $trendReasons[] = $txProgressText;
        if ($isExamSurge) $trendReasons[] = $examProgressText;
        if ($isDivergentSurge) $trendReasons[] = "Nỗ lực vượt khó vươn lên ấn tượng cuối kỳ";
    } else {
        $momentum = 'steady';
        $trend = 'stable';
        $trendLabel = 'Duy trì ổn định';
        $trendIcon = 'steady';
        if (!empty($txProgressText)) {
            $trendReasons[] = $txProgressText;
        } elseif ($avg !== null && $avg >= 8.0) {
            $trendReasons[] = 'Duy trì phong độ học tập xuất sắc';
        } elseif ($avg !== null && $avg >= 6.5) {
            $trendReasons[] = 'Phong độ đồng đều, kết quả khá';
        } else {
            $trendReasons[] = 'Điểm số đi đều, ổn định';
        }
    }

    return [
        'momentum'          => $momentum,
        'trend'             => $trend,
        'trend_label'       => $trendLabel,
        'trend_icon'        => $trendIcon,
        'trend_reasons'     => $trendReasons,
        'trend_description' => implode(' · ', $trendReasons),
        'tx_stats'          => [
            'count'          => $txCount,
            'avg'            => $avgTx,
            'delta'          => $txDelta,
            'is_declining'   => $isTxDeclining,
            'is_improving'   => $isTxImproving,
            'is_steady'      => $isTxSteady,
            'below_5_count'  => $txBelow5,
            'below_35_count' => $txBelow35,
        ],
    ];
}

/**
 * Đánh giá Năng Lực Tổng Thể & Khuyến Nghị Sư Phạm Hành Động (Humanizer Pedagogical Engine)
 * Giao thoa giữa Xu Hướng Tiến Bộ và Bậc Học Lực (TT22), cá nhân hóa theo quỹ đạo điểm thực tế
 *
 * @param array      $trendData Dữ liệu từ analyzeLearningTrend()
 * @param array      $tierData  Dữ liệu từ determineScoreTier()
 * @param float|null $avg       Điểm trung bình môn học kỳ
 * @return array
 */
function evaluateOverallCompetency(array $trendData, array $tierData, ?float $avg): array
{
    $momentum = $trendData['momentum'] ?? 'steady';
    $tier = $tierData['tier'] ?? 'pending';
    $txDelta = $trendData['tx_stats']['delta'] ?? null;
    $avgFormatted = $avg !== null ? number_format($avg, 1) : '';

    // 0. Trường hợp chưa có điểm
    if ($momentum === 'pending' || $tier === 'pending') {
        return [
            'competency_title'   => 'Chưa đủ dữ liệu điểm số',
            'pedagogical_advice' => 'Em chưa có cột điểm kiểm tra nào trong học kỳ này. Thầy/cô vui lòng cập nhật điểm đánh giá thường xuyên để Radar tự động phân tích nhịp độ học tập của em.',
            'profile_tag'        => 'Đang cập nhật',
        ];
    }

    // 1. Học sinh có học lực Thấp / Trung bình nhưng ĐANG ĐI ĐỀU (Logic cốt lõi TT22)
    if ($tier === 'low' && $momentum === 'steady') {
        $scoreNote = $avgFormatted !== '' ? " (ĐTB hiện tại {$avgFormatted}đ)" : '';
        return [
            'competency_title'   => 'Duy trì ổn định — Cần bồi dưỡng nâng cao',
            'pedagogical_advice' => "Em giữ nhịp làm bài khá đều đặn, không bị rơi rụng thêm{$scoreNote}; tuy nhiên nền tảng kiến thức còn mỏng. Thầy/cô nên khích lệ sự kiên trì của em, đồng thời giao các dạng bài cơ bản vừa sức để tiếp sức cho em vượt ngưỡng 5.0.",
            'profile_tag'        => 'Đi đều mức thấp (Cần tiếp sức)',
        ];
    }

    if ($tier === 'average' && $momentum === 'steady') {
        $scoreNote = $avgFormatted !== '' ? " ({$avgFormatted}đ)" : '';
        return [
            'competency_title'   => 'Duy trì nền tảng — Cần phát huy nâng cao',
            'pedagogical_advice' => "Em nắm được kiến thức cốt lõi và giữ mạch điểm ổn định{$scoreNote}. Chỉ cần thầy/cô gợi mở thêm phương pháp tự tổng hợp sơ đồ tư duy và rèn kỹ năng trình bày bài tự luận, em hoàn toàn có thể vươn lên nhóm Khá.",
            'profile_tag'        => 'Đi đều đạt chuẩn (Phát huy)',
        ];
    }

    // 2. Học sinh có học lực Khá / Xuất sắc và ĐANG ĐI ĐỀU
    if ($tier === 'good' && $momentum === 'steady') {
        return [
            'competency_title'   => 'Phong độ vững vàng — Tiềm năng vươn lên Giỏi',
            'pedagogical_advice' => 'Em có thái độ học tập nghiêm túc, điểm số các bài kiểm tra duy trì đồng đều ở mức Khá. Thầy/cô có thể thử thách em bằng 1–2 câu hỏi vận dụng cao mỗi tuần để tạo đà chạm mốc Xuất sắc (8.0+).',
            'profile_tag'        => 'Phong độ Khá vững vàng',
        ];
    }

    if ($tier === 'excellent' && $momentum === 'steady') {
        return [
            'competency_title'   => 'Duy trì phong độ xuất sắc',
            'pedagogical_advice' => 'Em làm chủ kiến thức rất chắc chắn và giữ vững nhịp độ xuất sắc qua từng bài đánh giá. Thầy/cô nên tạo điều kiện để em hỗ trợ bạn cùng bàn (Đôi bạn cùng tiến) hoặc bồi dưỡng đội tuyển học sinh giỏi.',
            'profile_tag'        => 'Phong độ xuất sắc (Vững vàng)',
        ];
    }

    // 3. Học sinh có Xu Hướng TĂNG (Tiến bộ rõ rệt)
    if ($momentum === 'surge' || $momentum === 'up') {
        $deltaText = ($txDelta !== null && $txDelta > 0) ? " (tăng +{$txDelta}đ qua các bài TX)" : '';
        if ($tier === 'low' || $tier === 'average') {
            return [
                'competency_title'   => 'Tiến bộ vượt khó — Đà vươn lên ấn tượng',
                'pedagogical_advice' => "Sự cố gắng của em đã bắt đầu kết trái khi điểm số cải thiện rõ rệt qua từng bài{$deltaText}. Một lời khen trực tiếp trước lớp lúc này sẽ là động lực rất lớn giúp em tự tin giữ vững đà tiến bộ.",
                'profile_tag'        => 'Tiến bộ vượt khó',
            ];
        }
        return [
            'competency_title'   => 'Tiến bộ vượt bậc — Phát triển toàn diện',
            'pedagogical_advice' => "Em thể hiện sức bật rất ấn tượng{$deltaText}, tư duy sắc bén và ngày càng hoàn thiện kỹ năng làm bài. Thầy/cô hãy giao thêm các chuyên đề mở rộng để phát huy tối đa năng khiếu bộ môn của em.",
            'profile_tag'        => 'Tiến bộ vượt bậc',
        ];
    }

    // 4. Học sinh có Hiệu Suất GIẢM (Sa sút / Tuột dốc)
    if ($momentum === 'down' || $momentum === 'drop') {
        $deltaText = ($txDelta !== null && $txDelta < 0) ? " (giảm " . abs($txDelta) . "đ)" : '';
        if ($tier === 'good' || $tier === 'excellent') {
            return [
                'competency_title'   => 'Cảnh báo sa sút phong độ bất thường',
                'pedagogical_advice' => "Điểm trung bình tích lũy của em vẫn ở mức khá tốt nhưng nhịp điểm gần đây đang chững lại và đi xuống{$deltaText}. Thầy/cô nên trò chuyện riêng nhẹ nhàng để lắng nghe xem em đang vướng ở mảng kiến thức mới hay bị xao nhãng tâm lý.",
                'profile_tag'        => 'Sa sút phong độ (Cần chấn chỉnh)',
            ];
        }
        return [
            'competency_title'   => 'Báo động tuột dốc nguy cấp — Cần can thiệp khẩn cấp',
            'pedagogical_advice' => "Kết quả học tập của em đang dưới chuẩn và có chiều hướng hụt hơi qua các bài kiểm tra gần nhất{$deltaText}. Thầy/cô cần khoanh vùng ngay phần kiến thức bị hổng để kèm cặp từng bước và trao đổi sớm cùng gia đình đồng hành.",
            'profile_tag'        => 'Tuột dốc nguy cấp (Can thiệp gấp)',
        ];
    }

    return [
        'competency_title'   => 'Chưa đủ dữ liệu đánh giá toàn diện',
        'pedagogical_advice' => 'Tiếp tục cập nhật điểm kiểm tra thường xuyên và định kỳ vào sổ điểm.',
        'profile_tag'        => 'Đang theo dõi',
    ];
}

/**
 * Động Cơ Chẩn Đoán Radar Toàn Diện (Thông tư 22/2021/TT-BGDĐT)
 * Tích hợp Hệ Thống Đánh Giá Đa Chiều:
 * - XU HƯỚNG TIẾN BỘ (Ưu tiên theo dõi)
 * - BẬC HỌC LỰC (TT22)
 * - NĂNG LỰC TỔNG THỂ & KHUYẾN NGHỊ SƯ PHẠM
 *
 * @param float|null $avg Điểm trung bình môn học kỳ (ĐTBmhk)
 * @param float|null $gk  Điểm kiểm tra giữa kỳ (ĐĐGgk)
 * @param float|null $ck  Điểm kiểm tra cuối kỳ (ĐĐGck)
 * @param array      $txScores Mảng điểm thường xuyên (tx1..tx4)
 * @return array
 */
function evaluateRiskZone(?float $avg, ?float $gk, ?float $ck, array $txScores = []): array
{
    $reasons = [];

    // 1. Phân tích Xu Hướng Tiến Bộ (OLS)
    $trendData = analyzeLearningTrend($avg, $gk, $ck, $txScores);
    $txStats = $trendData['tx_stats'];

    // 2. Phân tích Khung Điểm Số (Score Tier)
    $validTx = [];
    foreach ($txScores as $val) {
        if ($val !== null && $val !== '' && is_numeric($val)) {
            $validTx[] = (float)$val;
        }
    }
    $tierData = determineScoreTier($avg, $gk, $ck, $validTx);

    // 3. Phân tích Năng Lực Tổng Thể & Lời Khuyên Sư Phạm
    $competency = evaluateOverallCompetency($trendData, $tierData, $avg);

    $hasTxEarlyWarning = false;
    $txWarningLevel = null;

    // -------------------------------------------------------------------------
    // 1. VÙNG NGUY CẤP (CRITICAL ZONE)
    // -------------------------------------------------------------------------
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

    // CẢNH BÁO SỚM TX NGUY CẤP: ĐTB TX < 3.5 hoặc có từ 2 bài TX < 3.5
    $isTxCrit = ($txStats['avg'] !== null && $txStats['avg'] < 3.5) || ($txStats['below_35_count'] >= 2);
    if ($isTxCrit) {
        $hasTxEarlyWarning = true;
        $txWarningLevel = 'red';
        if ($txStats['avg'] !== null && $txStats['avg'] < 3.5) {
            $reasons[] = 'Cảnh báo sớm TX: ĐTB TX nguy cấp (' . number_format($txStats['avg'], 1) . ')';
        } else {
            $reasons[] = 'Cảnh báo sớm TX: có ' . $txStats['below_35_count'] . ' bài TX < 3.5';
        }
    }

    if ($isAvgCrit || $isCkCrit || $isBothFailing || $isTxCrit) {
        return [
            'level'             => 'red',
            'label'             => 'Nguy cấp',
            'reasons'           => $reasons,
            'diagnostic'        => implode(' · ', $reasons),
            'trend'             => $trendData['trend'],
            'trend_label'       => $trendData['trend_label'],
            'trend_icon'        => $trendData['trend_icon'],
            'trend_reasons'     => $trendData['trend_reasons'],
            'trend_description' => $trendData['trend_description'],
            'momentum'          => $trendData['momentum'],
            'score_tier'        => $tierData,
            'competency'        => $competency,
            'tx_stats'          => $txStats,
            'has_tx_warning'    => $hasTxEarlyWarning,
            'tx_warning_level'  => $txWarningLevel,
        ];
    }

    // -------------------------------------------------------------------------
    // 2. VÙNG CẦN CHÚ Ý (WARNING / AT RISK ZONE)
    // -------------------------------------------------------------------------
    $isWarning = false;

    // 2.1 Điểm TB dưới chuẩn (3.5 <= avg < 5.0)
    $isAvgWarn = ($avg !== null && $avg >= 3.5 && $avg < 5.0);
    if ($isAvgWarn) {
        $isWarning = true;
        if ($trendData['momentum'] === 'steady') {
            $reasons[] = 'Điểm TB dưới chuẩn (' . number_format($avg, 1) . ') · Đi đều, cần phát huy';
        } else {
            $reasons[] = 'Điểm TB dưới chuẩn (' . number_format($avg, 1) . ')';
        }
    }

    // 2.2 Điểm thi thành phần định kỳ dưới trung bình (< 5.0) - Bắt buộc xếp vào Yellow
    if ($ck !== null && $ck < 5.0) {
        $isWarning = true;
        $reasons[] = 'Thi cuối kỳ dưới trung bình (' . number_format($ck, 1) . ')';
    }
    if ($gk !== null && $gk < 5.0) {
        $isWarning = true;
        $reasons[] = 'Thi giữa kỳ dưới trung bình (' . number_format($gk, 1) . ')';
    }

    // 2.3 Sa sút cuối kỳ: ck < gk - 1.5 <=> (gk - ck) > 1.5
    if ($gk !== null && $ck !== null) {
        $drop = round((float)$gk - (float)$ck, 1);
        if ($drop > 1.5) {
            $isWarning = true;
            $reasons[] = 'Sa sút cuối kỳ (giảm ' . number_format($drop, 1) . ' đ so với GK)';
        }
    }

    // 2.4 CẢNH BÁO SỚM TX (ĐTB TX < 5.0 hoặc có bài TX < 5.0)
    if ($txStats['avg'] !== null && $txStats['avg'] < 5.0) {
        $isWarning = true;
        $hasTxEarlyWarning = true;
        $txWarningLevel = 'yellow';
        if ($trendData['momentum'] === 'steady') {
            $reasons[] = 'Cảnh báo sớm TX: ĐTB TX dưới chuẩn (' . number_format($txStats['avg'], 1) . ') · Đi đều, cần phát huy';
        } else {
            $reasons[] = 'Cảnh báo sớm TX: ĐTB TX dưới chuẩn (' . number_format($txStats['avg'], 1) . ')';
        }
    } elseif ($txStats['below_5_count'] > 0) {
        $isWarning = true;
        $hasTxEarlyWarning = true;
        $txWarningLevel = 'yellow';
        $reasons[] = 'Cảnh báo sớm TX: có ' . $txStats['below_5_count'] . ' bài TX < 5.0';
    }

    // 2.5 PHÂN TÍCH XU HƯỚNG SA SÚT (GHI NHẬN TUỘT DỐC: VD 10 -> 9 -> 8 -> 6)
    if ($trendData['trend'] === 'down') {
        $isWarning = true;
        foreach ($trendData['trend_reasons'] as $tReason) {
            if (!in_array($tReason, $reasons, true)) {
                $reasons[] = $tReason;
            }
        }
    }

    if ($isWarning) {
        return [
            'level'             => 'yellow',
            'label'             => 'Cần chú ý',
            'reasons'           => $reasons,
            'diagnostic'        => implode(' · ', $reasons),
            'trend'             => $trendData['trend'],
            'trend_label'       => $trendData['trend_label'],
            'trend_icon'        => $trendData['trend_icon'],
            'trend_reasons'     => $trendData['trend_reasons'],
            'trend_description' => $trendData['trend_description'],
            'momentum'          => $trendData['momentum'],
            'score_tier'        => $tierData,
            'competency'        => $competency,
            'tx_stats'          => $txStats,
            'has_tx_warning'    => $hasTxEarlyWarning,
            'tx_warning_level'  => $txWarningLevel,
        ];
    }

    // -------------------------------------------------------------------------
    // 3. VÙNG ỔN ĐỊNH (STABLE ZONE)
    // -------------------------------------------------------------------------
    // Học sinh chưa có điểm / trống điểm: gán vào Green để bảo toàn phân hoạch kín total === red + yellow + green
    if ($avg === null && empty($validTx) && $gk === null && $ck === null) {
        $reasons[] = 'Chờ điểm / Chưa đủ dữ liệu điểm số';
        return [
            'level'             => 'green',
            'label'             => 'Ổn định',
            'reasons'           => $reasons,
            'diagnostic'        => implode(' · ', $reasons),
            'trend'             => 'pending',
            'trend_label'       => 'Chờ điểm',
            'trend_icon'        => 'pending',
            'trend_reasons'     => $reasons,
            'trend_description' => 'Chờ điểm / Chưa đủ dữ liệu điểm số',
            'momentum'          => 'pending',
            'score_tier'        => $tierData,
            'competency'        => $competency,
            'tx_stats'          => $txStats,
            'has_tx_warning'    => false,
            'tx_warning_level'  => null,
        ];
    }

    // Bảo vệ bất biến TT22: Tuyệt đối không để học sinh có CK < 5.0 hoặc GK < 5.0 lọt vào vùng Xanh
    if (($ck !== null && $ck < 5.0) || ($gk !== null && $gk < 5.0)) {
        if (empty($reasons)) {
            if ($ck !== null && $ck < 5.0) $reasons[] = 'Thi cuối kỳ dưới trung bình (' . number_format($ck, 1) . ')';
            if ($gk !== null && $gk < 5.0) $reasons[] = 'Thi giữa kỳ dưới trung bình (' . number_format($gk, 1) . ')';
        }
        return [
            'level'             => 'yellow',
            'label'             => 'Cần chú ý',
            'reasons'           => $reasons,
            'diagnostic'        => implode(' · ', $reasons),
            'trend'             => $trendData['trend'],
            'trend_label'       => $trendData['trend_label'],
            'trend_icon'        => $trendData['trend_icon'],
            'trend_reasons'     => $trendData['trend_reasons'],
            'trend_description' => $trendData['trend_description'],
            'momentum'          => $trendData['momentum'],
            'score_tier'        => $tierData,
            'competency'        => $competency,
            'tx_stats'          => $txStats,
            'has_tx_warning'    => $hasTxEarlyWarning,
            'tx_warning_level'  => $txWarningLevel,
        ];
    }

    if ($trendData['trend'] === 'up') {
        $reasons[] = $trendData['trend_description'];
        if ($avg !== null && $avg >= 8.0) {
            $reasons[] = 'Học lực Giỏi, kết quả xuất sắc (' . number_format($avg, 1) . ')';
        } elseif ($avg !== null && $avg >= 6.5) {
            $reasons[] = 'Học lực Khá (' . number_format($avg, 1) . ')';
        }
    } elseif ($avg !== null && $avg >= 8.0) {
        $reasons[] = 'Học lực Giỏi, kết quả xuất sắc (' . number_format($avg, 1) . ')';
    } elseif ($avg !== null && $avg >= 6.5) {
        $reasons[] = 'Học lực Khá, duy trì tốt (' . number_format($avg, 1) . ')';
    } elseif ($avg !== null) {
        $reasons[] = 'Đạt chuẩn, ổn định (' . number_format($avg, 1) . ')';
    } else {
        $reasons[] = 'Đang tích lũy điểm số thường xuyên';
    }

    return [
        'level'             => 'green',
        'label'             => 'Ổn định',
        'reasons'           => $reasons,
        'diagnostic'        => implode(' · ', $reasons),
        'trend'             => $trendData['trend'],
        'trend_label'       => $trendData['trend_label'],
        'trend_icon'        => $trendData['trend_icon'],
        'trend_reasons'     => $trendData['trend_reasons'],
        'trend_description' => $trendData['trend_description'],
        'momentum'          => $trendData['momentum'],
        'score_tier'        => $tierData,
        'competency'        => $competency,
        'tx_stats'          => $txStats,
        'has_tx_warning'    => false,
        'tx_warning_level'  => null,
    ];
}

/**
 * Tính toán bộ đếm số lượng và tỷ lệ phần trăm theo cả 2 Khung Đánh Giá
 * Tách biệt rõ ràng giữa học sinh "Đang đi đều thực tế" và học sinh "Chờ điểm"
 *
 * @param array $students Danh sách học sinh
 * @return array
 */
function calculateRadarStats(array $students): array
{
    $stats = [
        'total'           => 0,
        // Vùng rủi ro TT22
        'red'             => 0,
        'yellow'          => 0,
        'green'           => 0,
        'pending_risk'    => 0,
        // Xu Hướng Tiến Bộ (OLS)
        'momentum_up'     => 0, // Tiến bộ rõ rệt
        'momentum_steady' => 0, // Đi đều / duy trì thực tế
        'momentum_down'   => 0, // Sa sút & cần hỗ trợ
        'momentum_pending'=> 0, // Chờ điểm
        // Bậc Học Lực (TT22)
        'tier_excellent'  => 0, // Giỏi / Xuất sắc (8.0+)
        'tier_good'       => 0, // Khá (6.5 - 7.9)
        'tier_average'    => 0, // Trung bình / Đạt (5.0 - 6.4)
        'tier_low'        => 0, // Thấp / Nguy cấp (< 5.0)
        'tier_pending'    => 0, // Chưa có điểm
        // Cảnh báo sớm TX
        'tx_warn_count'   => 0,
        // Tỷ lệ %
        'red_pct'         => 0.0,
        'yellow_pct'      => 0.0,
        'green_pct'       => 0.0,
        'up_pct'          => 0.0,
        'steady_pct'      => 0.0,
        'down_pct'        => 0.0,
        'pending_pct'     => 0.0,
    ];

    foreach ($students as $s) {
        $stats['total']++;
        $level = $s['level'] ?? 'green';
        if (isset($stats[$level])) {
            $stats[$level]++;
        }

        $mom = $s['momentum'] ?? 'pending';
        if ($mom === 'surge' || $mom === 'up') {
            $stats['momentum_up']++;
        } elseif ($mom === 'down' || $mom === 'drop') {
            $stats['momentum_down']++;
        } elseif ($mom === 'steady') {
            $stats['momentum_steady']++;
        } else {
            $stats['momentum_pending']++;
        }

        $tier = $s['score_tier']['tier'] ?? 'pending';
        if ($tier === 'excellent') $stats['tier_excellent']++;
        elseif ($tier === 'good') $stats['tier_good']++;
        elseif ($tier === 'average') $stats['tier_average']++;
        elseif ($tier === 'low') $stats['tier_low']++;
        else $stats['tier_pending']++;

        if (!empty($s['has_tx_warning'])) {
            $stats['tx_warn_count']++;
        }
    }

    if ($stats['total'] > 0) {
        $stats['red_pct']     = round(($stats['red'] / $stats['total']) * 100, 1);
        $stats['yellow_pct']  = round(($stats['yellow'] / $stats['total']) * 100, 1);
        $stats['green_pct']   = round(($stats['green'] / $stats['total']) * 100, 1);
        $stats['up_pct']      = round(($stats['momentum_up'] / $stats['total']) * 100, 1);
        $stats['steady_pct']  = round(($stats['momentum_steady'] / $stats['total']) * 100, 1);
        $stats['down_pct']    = round(($stats['momentum_down'] / $stats['total']) * 100, 1);
        $stats['pending_pct'] = round(($stats['momentum_pending'] / $stats['total']) * 100, 1);
    }

    // Backward compatibility keys
    $stats['trend_up'] = $stats['momentum_up'];
    $stats['trend_down'] = $stats['momentum_down'];
    $stats['trend_stable'] = $stats['momentum_steady'];

    return $stats;
}

/**
 * Sắp xếp học sinh: Ưu tiên Sa sút/Tuột dốc -> Nguy cấp -> Cần chú ý -> Có điểm -> Chờ điểm
 *
 * @param array $students Danh sách học sinh tham chiếu
 * @return array
 */
function sortStudentsByRisk(array &$students): array
{
    $levelPriority = ['red' => 0, 'yellow' => 1, 'green' => 2, 'pending' => 3];
    $trendPriority = ['down' => 0, 'stable' => 1, 'up' => 2, 'pending' => 3];

    usort($students, function ($a, $b) use ($levelPriority, $trendPriority) {
        $pA = $levelPriority[$a['level'] ?? 'pending'] ?? 3;
        $pB = $levelPriority[$b['level'] ?? 'pending'] ?? 3;
        if ($pA !== $pB) {
            return $pA <=> $pB;
        }

        $tA = $trendPriority[$a['trend'] ?? 'pending'] ?? 3;
        $tB = $trendPriority[$b['trend'] ?? 'pending'] ?? 3;
        if ($tA !== $tB) {
            return $tA <=> $tB;
        }

        return strcasecmp($a['full_name'] ?? '', $b['full_name'] ?? '');
    });
    return $students;
}

// =============================================================================
// Backward compatibility aliases
// =============================================================================
if (!function_exists('evaluateEarlyWarning')) {
    function evaluateEarlyWarning(?float $avg, ?float $gk, ?float $ck, array $txScores = []): array
    {
        return evaluateRiskZone($avg, $gk, $ck, $txScores);
    }
}

if (!function_exists('evaluateRisk')) {
    function evaluateRisk(?float $avg, array $scores = [], ?float $gk = null, ?float $ck = null): array
    {
        return evaluateRiskZone($avg, $gk, $ck, $scores);
    }
}
