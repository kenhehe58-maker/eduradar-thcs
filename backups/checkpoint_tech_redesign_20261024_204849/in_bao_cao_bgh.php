<?php
/**
 * =============================================================================
 * Hệ Thống Quản Lý Điểm & Cảnh Báo Sớm THCS (EduRadar)
 * Module: Báo Cáo Phân Tích Động Năng & Rủi Ro Học Kỳ Nộp BGH (PDF & Excel)
 * Tiêu chuẩn: Thông tư 22/2021/TT-BGDĐT
 * =============================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/radar_helper.php';
require_once __DIR__ . '/includes/xlsx_helper.php';

$pdo = db();
$classId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$subject = isset($_GET['subject']) ? trim($_GET['subject']) : '';
$format = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : 'pdf'; // 'pdf' | 'xlsx'
$isGuest = isset($_GET['guest']) && $_GET['guest'] === '1';

$className = 'Chưa xác định';
$subjectName = $subject;
$teacherName = 'Giáo viên bộ môn';
$evaluatedStudents = [];

if ($isGuest && isset($_SESSION['guest_scan_data'])) {
    $guestData = $_SESSION['guest_scan_data'];
    $className = $guestData['class_name'] ?? 'Lớp Quét Nhanh';
    $subjectName = $guestData['subject'] ?? 'Môn Học';
    $teacherName = 'Giáo viên trải nghiệm (Khách)';
    $evaluatedStudents = $guestData['students'] ?? [];
} else {
    require_login();
    $userId = (int)$_SESSION['user_id'];
    $userRole = $_SESSION['role'];
    $teacherName = $_SESSION['full_name'] ?? 'Giáo viên bộ môn';

    if ($classId <= 0 || empty($subject)) {
        die("Vui lòng chọn lớp và môn học để lập báo cáo nộp BGH.");
    }

    // Kiểm tra lớp
    $cStmt = $pdo->prepare("SELECT id, name, grade FROM classes WHERE id = ?");
    $cStmt->execute([$classId]);
    $classRow = $cStmt->fetch(PDO::FETCH_ASSOC);
    if (!$classRow) {
        die("Lớp học không tồn tại.");
    }
    $className = $classRow['name'];

    // Kiểm tra phân quyền giáo viên
    if ($userRole === 'teacher') {
        $chk = $pdo->prepare("SELECT id FROM teacher_classes WHERE teacher_id = ? AND class_id = ? AND subject = ?");
        $chk->execute([$userId, $classId, $subject]);
        if (!$chk->fetch()) {
            http_response_code(403);
            die("Bạn không được phân công giảng dạy lớp {$className} môn {$subject}.");
        }
    }

    // Lấy danh sách học sinh và điểm số
    $stmt = $pdo->prepare("
        SELECT 
            s.id AS student_id, s.student_code, s.full_name, s.birthday, s.phone,
            sc.tx1, sc.tx2, sc.tx3, sc.tx4, sc.gk, sc.ck, sc.avg_score, sc.teacher_note
        FROM students s
        LEFT JOIN scores sc ON sc.student_id = s.id AND sc.subject = ?
        WHERE s.class_id = ?
        ORDER BY s.full_name ASC
    ");
    $stmt->execute([$subject, $classId]);
    $rawList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rawList as $s) {
        $avg = ($s['avg_score'] !== null && $s['avg_score'] !== '') ? (float)$s['avg_score'] : null;
        $gk  = ($s['gk'] !== null && $s['gk'] !== '') ? (float)$s['gk'] : null;
        $ck  = ($s['ck'] !== null && $s['ck'] !== '') ? (float)$s['ck'] : null;

        $txList = [];
        foreach (['tx1', 'tx2', 'tx3', 'tx4'] as $col) {
            if ($s[$col] !== null && $s[$col] !== '') {
                $txList[] = (float)$s[$col];
            }
        }

        $evaluation = evaluateRiskZone($avg, $gk, $ck, $txList);
        $evaluatedStudents[] = array_merge($s, $evaluation, ['tx_list' => $txList]);
    }
}

// Tổng hợp thống kê 2 Khung Đánh Giá
$total = count($evaluatedStudents);
$counts = [
    'red' => 0,
    'yellow' => 0,
    'green' => 0,
    'trends' => [
        'accelerating' => 0,
        'improving'    => 0,
        'steady'       => 0,
        'declining'    => 0,
        'drop_alarm'   => 0,
        'pending'      => 0,
    ],
    'tiers' => [
        'XUAT_SAC' => 0,
        'TOT'      => 0,
        'DAT'      => 0,
        'CHUA_DAT' => 0,
        'CHO_DIEM' => 0,
    ]
];

$atRiskStudents = [];

foreach ($evaluatedStudents as $st) {
    $lvl = $st['level'] ?? 'green';
    if (isset($counts[$lvl])) {
        $counts[$lvl]++;
    }

    $trCode = $st['trend_code'] ?? 'pending';
    if (isset($counts['trends'][$trCode])) {
        $counts['trends'][$trCode]++;
    }

    $tierCode = $st['score_tier']['tier_code'] ?? 'CHO_DIEM';
    if (isset($counts['tiers'][$tierCode])) {
        $counts['tiers'][$tierCode]++;
    }

    // Xác định học sinh cần can thiệp (Rủi ro Đỏ, Vàng, hoặc Tuột dốc / Báo động tụt dốc)
    if ($lvl === 'red' || $lvl === 'yellow' || $trCode === 'declining' || $trCode === 'drop_alarm' || $tierCode === 'CHUA_DAT') {
        $atRiskStudents[] = $st;
    }
}

// Sắp xếp học sinh cần can thiệp: Đỏ lên trước, rồi Vàng
usort($atRiskStudents, function($a, $b) {
    $pMap = ['red' => 1, 'yellow' => 2, 'green' => 3];
    $pA = $pMap[$a['level'] ?? 'green'] ?? 4;
    $pB = $pMap[$b['level'] ?? 'green'] ?? 4;
    if ($pA !== $pB) {
        return $pA <=> $pB;
    }
    return ($a['avg_score'] ?? 99) <=> ($b['avg_score'] ?? 99);
});

// =============================================================================
// NẾU XUẤT ĐỊNH DẠNG EXCEL (.XLSX)
// =============================================================================
if ($format === 'xlsx') {
    $rows = [
        ['BÁO CÁO PHÂN TÍCH ĐỘNG NĂNG & NGUY CƠ HỌC TẬP HỌC KỲ'],
        ['Kính gửi: Ban Giám Hiệu & Tổ Chuyên Môn - Hệ thống EduRadar THCS'],
        [''],
        ['Lớp:', $className, 'Môn học:', $subjectName, 'Sĩ số:', $total],
        ['Giáo viên lập báo cáo:', $teacherName, 'Ngày lập:', date('d/m/Y')],
        [''],
        ['PHẦN 1: BẢNG TỔNG HỢP 2 KHUNG ĐÁNH GIÁ (HIỆU SUẤT & ĐIỂM SỐ)'],
        ['Khung Đánh Giá', 'Phân loại', 'Số lượng (HS)', 'Tỷ lệ (%)', 'Ý nghĩa sư phạm'],
        ['1. Khung Hiệu Suất (Momentum)', '🚀 Tăng tốc bức phá', $counts['trends']['accelerating'], $total > 0 ? round(($counts['trends']['accelerating'] / $total) * 100, 1) : 0, 'Điểm số liên tục tăng trưởng mạnh'],
        ['', '📈 Đang tiến bộ', $counts['trends']['improving'], $total > 0 ? round(($counts['trends']['improving'] / $total) * 100, 1) : 0, 'Có xu hướng đi lên so với trước'],
        ['', '🔄 Duy trì ổn định', $counts['trends']['steady'], $total > 0 ? round(($counts['trends']['steady'] / $total) * 100, 1) : 0, 'Điểm số giữ vững đều đặn'],
        ['', '📉 Tuột dốc liên tục', $counts['trends']['declining'], $total > 0 ? round(($counts['trends']['declining'] / $total) * 100, 1) : 0, 'Cần tìm hiểu nguyên nhân tâm lý/phương pháp'],
        ['', '⚠️ Báo động tụt dốc', $counts['trends']['drop_alarm'], $total > 0 ? round(($counts['trends']['drop_alarm'] / $total) * 100, 1) : 0, 'Giảm >= 2 điểm ở kỳ thi trọng số lớn'],
        ['', '⏳ Chờ điểm số', $counts['trends']['pending'], $total > 0 ? round(($counts['trends']['pending'] / $total) * 100, 1) : 0, 'Chưa đủ dữ liệu tối thiểu 2 cột điểm'],
        ['2. Khung Điểm Số (Học lực)', '🌟 Xuất sắc (>= 9.0)', $counts['tiers']['XUAT_SAC'], $total > 0 ? round(($counts['tiers']['XUAT_SAC'] / $total) * 100, 1) : 0, 'Bồi dưỡng học sinh giỏi'],
        ['', '🟢 Tốt (>= 6.5)', $counts['tiers']['TOT'], $total > 0 ? round(($counts['tiers']['TOT'] / $total) * 100, 1) : 0, 'Phát huy năng lực vững vàng'],
        ['', '🟡 Đạt (>= 5.0)', $counts['tiers']['DAT'], $total > 0 ? round(($counts['tiers']['DAT'] / $total) * 100, 1) : 0, 'Củng cố kiến thức cơ bản'],
        ['', '🔴 Chưa đạt (< 5.0)', $counts['tiers']['CHUA_DAT'], $total > 0 ? round(($counts['tiers']['CHUA_DAT'] / $total) * 100, 1) : 0, 'Bắt buộc lập danh sách phụ đạo'],
        ['3. Phân vùng rủi ro', '🔴 Nguy cấp (Vùng Đỏ)', $counts['red'], $total > 0 ? round(($counts['red'] / $total) * 100, 1) : 0, 'Can thiệp khẩn cấp ngay'],
        ['', '🟡 Cần chú ý (Vùng Vàng)', $counts['yellow'], $total > 0 ? round(($counts['yellow'] / $total) * 100, 1) : 0, 'Kèm cặp và kiểm tra định kỳ'],
        ['', '🟢 Ổn định (Vùng Xanh)', $counts['green'], $total > 0 ? round(($counts['green'] / $total) * 100, 1) : 0, 'Duy trì phong độ học tập'],
        [''],
        ['PHẦN 2: DANH SÁCH HỌC SINH CẦN CAN THIỆP & PHỤ ĐẠO SƯ PHẠM'],
        ['STT', 'Mã HS', 'Họ và tên', 'TX1', 'TX2', 'TX3', 'TX4', 'GK', 'CK', 'ĐTB', 'Khung Động Năng', 'Khung Học Lực', 'Mức Rủi Ro', 'Chẩn Đoán & Kế Hoạch Sư Phạm', 'Ghi Chú Của Giáo Viên']
    ];

    $stt = 1;
    foreach ($atRiskStudents as $st) {
        $tx1 = $st['tx1'] !== null ? (float)$st['tx1'] : '-';
        $tx2 = $st['tx2'] !== null ? (float)$st['tx2'] : '-';
        $tx3 = $st['tx3'] !== null ? (float)$st['tx3'] : '-';
        $tx4 = $st['tx4'] !== null ? (float)$st['tx4'] : '-';
        $gk  = $st['gk'] !== null ? (float)$st['gk'] : '-';
        $ck  = $st['ck'] !== null ? (float)$st['ck'] : '-';
        $avg = $st['avg_score'] !== null ? (float)$st['avg_score'] : '-';

        $lvlText = ($st['level'] === 'red') ? 'Nguy cấp (Đỏ)' : (($st['level'] === 'yellow') ? 'Chú ý (Vàng)' : 'Ổn định');
        $trendText = $st['trend_label'] ?? 'Duy trì';
        $tierText = $st['score_tier']['tier_name'] ?? 'Chưa đạt';

        $diag = ($st['diagnostic'] ?? '') . ' — Khuyến nghị: ' . ($st['competency']['pedagogical_advice'] ?? '');
        $note = $st['teacher_note'] ?? '';

        $rows[] = [
            $stt++,
            $st['student_code'] ?? ('HS' . $st['student_id']),
            $st['full_name'],
            $tx1, $tx2, $tx3, $tx4, $gk, $ck, $avg,
            $trendText,
            $tierText,
            $lvlText,
            $diag,
            $note
        ];
    }

    $rows[] = [''];
    $rows[] = ['Ý KIẾN CHỈ ĐẠO CỦA TỔ CHUYÊN MÔN:'];
    $rows[] = ['....................................................................................................................................'];
    $rows[] = ['Ý KIẾN PHÊ DUYỆT CỦA BAN GIÁM HIỆU:'];
    $rows[] = ['....................................................................................................................................'];
    $rows[] = [''];
    $rows[] = ['GIÁO VIÊN BỘ MÔN', 'TỔ TRƯỞNG CHUYÊN MÔN', '', '', 'BAN GIÁM HIỆU PHÊ DUYỆT'];

    $cleanClass = preg_replace('/[^a-zA-Z0-9_]/', '', str_replace(' ', '_', $className));
    $cleanSubj = preg_replace('/[^a-zA-Z0-9_]/', '', str_replace(' ', '_', $subjectName));
    $filename = "Bao_Cao_BGH_{$cleanClass}_{$cleanSubj}.xlsx";
    $xlsxBin = create_xlsx($rows, 'BaoCaoBGH');

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
    header('Content-Length: ' . strlen($xlsxBin));
    header('Cache-Control: max-age=0');
    echo $xlsxBin;
    exit;
}

// =============================================================================
// NẾU XUẤT ĐỊNH DẠNG IN ẤN A4 / PRINT-TO-PDF
// =============================================================================
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo Cáo BGH – Lớp <?= htmlspecialchars($className) ?> – Môn <?= htmlspecialchars($subjectName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
            color: #0f172a;
            background: #f1f5f9;
            padding: 24px;
            font-size: 13px;
            line-height: 1.5;
        }
        .action-bar {
            max-width: 900px;
            margin: 0 auto 20px auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #ffffff;
            padding: 12px 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            border: 1px solid #e2e8f0;
        }
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s ease;
            border: none;
        }
        .btn-print { background: #4f46e5; color: #ffffff; }
        .btn-print:hover { background: #4338ca; }
        .btn-excel { background: #10b981; color: #ffffff; }
        .btn-excel:hover { background: #059669; }
        .btn-close { background: #f1f5f9; color: #475569; }
        .btn-close:hover { background: #e2e8f0; }

        .page-a4 {
            max-width: 900px;
            margin: 0 auto;
            background: #ffffff;
            padding: 36px 44px;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
        }
        .header-grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            margin-bottom: 20px;
            align-items: flex-start;
        }
        .header-left {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            line-height: 1.4;
            color: #334155;
        }
        .header-left .sub {
            font-size: 11px;
            font-weight: 500;
            text-transform: none;
            color: #64748b;
        }
        .header-right {
            text-align: center;
            line-height: 1.4;
        }
        .header-right .country {
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.5px;
        }
        .header-right .motto {
            font-size: 11.5px;
            font-weight: 600;
            font-style: italic;
            border-bottom: 1px solid #0f172a;
            display: inline-block;
            padding-bottom: 2px;
            margin-top: 2px;
        }

        .report-title {
            text-align: center;
            margin: 16px 0 14px 0;
        }
        .report-title h1 {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .report-title .subtitle {
            font-size: 12px;
            color: #475569;
            margin-top: 4px;
        }

        .meta-box {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 12.5px;
        }
        .meta-item .label {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 700;
            margin-bottom: 2px;
        }
        .meta-item .val {
            font-weight: 700;
            color: #0f172a;
        }

        .section-header {
            font-size: 13.5px;
            font-weight: 800;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-left: 4px solid #4f46e5;
            padding-left: 10px;
            margin: 22px 0 12px 0;
        }

        /* Thống kê 2 Khung */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 16px;
        }
        .stat-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 14px;
        }
        .stat-card.red { border-left: 4px solid #ef4444; background: #fef2f2; }
        .stat-card.yellow { border-left: 4px solid #f59e0b; background: #fffbeb; }
        .stat-card.green { border-left: 4px solid #10b981; background: #ecfdf5; }
        .stat-card .card-title {
            font-size: 12px;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 6px;
        }
        .stat-item {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            padding: 3px 0;
            color: #475569;
        }
        .stat-item strong {
            color: #0f172a;
            font-weight: 700;
        }

        /* Bảng dữ liệu học sinh */
        table.radar-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            font-size: 12px;
        }
        table.radar-table th, table.radar-table td {
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
            text-align: left;
            vertical-align: middle;
        }
        table.radar-table th {
            background: #f1f5f9;
            font-weight: 800;
            color: #1e293b;
            text-transform: uppercase;
            font-size: 11px;
            text-align: center;
        }
        table.radar-table td.center { text-align: center; }
        table.radar-table td.bold { font-weight: 700; }
        table.radar-table tr:nth-child(even) { background: #f8fafc; }

        .badge-pill {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 12px;
            font-size: 10.5px;
            font-weight: 700;
            white-space: nowrap;
        }
        .badge-red { background: #fee2e2; color: #991b1b; }
        .badge-yellow { background: #fef3c7; color: #92400e; }
        .badge-green { background: #d1fae5; color: #065f46; }

        .advice-box {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 12px 16px;
            margin-top: 14px;
            font-size: 12px;
            color: #334155;
            line-height: 1.6;
        }

        .signatures-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            margin-top: 36px;
            text-align: center;
            page-break-inside: avoid;
        }
        .sig-col {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 140px;
        }
        .sig-role {
            font-weight: 800;
            font-size: 12px;
            text-transform: uppercase;
            color: #1e293b;
        }
        .sig-sub {
            font-size: 11px;
            font-style: italic;
            color: #64748b;
            margin-top: 2px;
        }
        .sig-name {
            font-weight: 700;
            font-size: 12px;
            color: #0f172a;
        }

        @media print {
            body { background: #ffffff; padding: 0; }
            .action-bar { display: none !important; }
            .page-a4 {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                max-width: 100% !important;
            }
            @page {
                size: A4 portrait;
                margin: 15mm 12mm 15mm 12mm;
            }
        }
    </style>
</head>
<body>

    <!-- THANH CÔNG CỤ XUẤT BÁO CÁO -->
    <div class="action-bar">
        <div style="display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 18px;">📊</span>
            <div>
                <strong style="font-size: 14px;">Báo Cáo Phân Tích Động Năng & Rủi Ro Lớp <?= htmlspecialchars($className) ?></strong>
                <div style="font-size: 12px; color: #64748b;">Hệ thống EduRadar THCS &bull; Khổ A4 tiêu chuẩn nộp BGH</div>
            </div>
        </div>
        <div style="display: flex; gap: 8px;">
            <button onclick="window.print()" class="btn-action btn-print">
                🖨️ In / Lưu PDF A4
            </button>
            <?php 
                $excelUrl = "in_bao_cao_bgh.php?format=xlsx" . ($isGuest ? "&guest=1" : "&class_id={$classId}&subject=" . urlencode($subject));
            ?>
            <a href="<?= htmlspecialchars($excelUrl) ?>" class="btn-action btn-excel">
                📥 Tải Báo Cáo Excel (.xlsx)
            </a>
            <button onclick="window.close()" class="btn-action btn-close">
                Đóng
            </button>
        </div>
    </div>

    <!-- NỘI DUNG TRANG IN A4 -->
    <div class="page-a4">
        <!-- QUỐC HIỆU TIÊU NGỮ & ĐƠN VỊ -->
        <div class="header-grid">
            <div class="header-left">
                TRƯỜNG THCS GIÁO DỤC TRỌNG ĐIỂM<br>
                TỔ CHUYÊN MÔN: <?= htmlspecialchars($subjectName) ?><br>
                <span class="sub">Số: ..... /BC-EDURADAR</span>
            </div>
            <div class="header-right">
                <div class="country">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</div>
                <div class="motto">Độc lập - Tự do - Hạnh phúc</div>
                <div style="font-size: 11px; color: #64748b; margin-top: 6px;">
                    ......, ngày <?= date('d') ?> tháng <?= date('m') ?> năm <?= date('Y') ?>
                </div>
            </div>
        </div>

        <!-- TIÊU ĐỀ BÁO CÁO -->
        <div class="report-title">
            <h1>BÁO CÁO PHÂN TÍCH ĐỘNG NĂNG & NGUY CƠ HỌC TẬP HỌC KỲ</h1>
            <div class="subtitle">
                (Phục vụ công tác hội chẩn sư phạm, phân loại học sinh và lập kế hoạch phụ đạo can thiệp sớm)
            </div>
        </div>

        <!-- THÔNG TIN CHUNG -->
        <div class="meta-box">
            <div class="meta-item">
                <div class="label">Lớp Giảng Dạy</div>
                <div class="val"><?= htmlspecialchars($className) ?></div>
            </div>
            <div class="meta-item">
                <div class="label">Môn Học</div>
                <div class="val"><?= htmlspecialchars($subjectName) ?></div>
            </div>
            <div class="meta-item">
                <div class="label">Sĩ Số Học Sinh</div>
                <div class="val"><?= $total ?> học sinh</div>
            </div>
            <div class="meta-item">
                <div class="label">Giáo Viên Bộ Môn</div>
                <div class="val"><?= htmlspecialchars($teacherName) ?></div>
            </div>
        </div>

        <!-- PHẦN 1: TỔNG QUAN 2 KHUNG NĂNG LỰC -->
        <div class="section-header">I. Tổng Hợp Thống Kê 2 Khung Đánh Giá (Momentum & Điểm Số)</div>
        <div class="stats-grid">
            <!-- Cột 1: Khung Hiệu Suất -->
            <div class="stat-card">
                <div class="card-title">1. Khung Hiệu Suất (Momentum)</div>
                <div class="stat-item"><span>🚀 Tăng tốc bức phá:</span> <strong><?= $counts['trends']['accelerating'] ?></strong></div>
                <div class="stat-item"><span>📈 Đang tiến bộ:</span> <strong><?= $counts['trends']['improving'] ?></strong></div>
                <div class="stat-item"><span>🔄 Duy trì ổn định:</span> <strong><?= $counts['trends']['steady'] ?></strong></div>
                <div class="stat-item"><span>📉 Tuột dốc liên tục:</span> <strong style="color: #ea580c;"><?= $counts['trends']['declining'] ?></strong></div>
                <div class="stat-item"><span>⚠️ Báo động tụt dốc:</span> <strong style="color: #dc2626;"><?= $counts['trends']['drop_alarm'] ?></strong></div>
                <div class="stat-item"><span>⏳ Chờ điểm số:</span> <strong><?= $counts['trends']['pending'] ?></strong></div>
            </div>

            <!-- Cột 2: Khung Học Lực -->
            <div class="stat-card">
                <div class="card-title">2. Khung Học Lực (Thông tư 22)</div>
                <div class="stat-item"><span>🌟 Xuất sắc (>= 9.0):</span> <strong><?= $counts['tiers']['XUAT_SAC'] ?></strong></div>
                <div class="stat-item"><span>🟢 Tốt (>= 6.5):</span> <strong><?= $counts['tiers']['TOT'] ?></strong></div>
                <div class="stat-item"><span>🟡 Đạt (>= 5.0):</span> <strong><?= $counts['tiers']['DAT'] ?></strong></div>
                <div class="stat-item"><span>🔴 Chưa đạt (&lt; 5.0):</span> <strong style="color: #dc2626;"><?= $counts['tiers']['CHUA_DAT'] ?></strong></div>
                <div class="stat-item"><span>⏳ Chờ điểm số:</span> <strong><?= $counts['tiers']['CHO_DIEM'] ?></strong></div>
            </div>

            <!-- Cột 3: Vùng Rủi Ro Radar -->
            <div class="stat-card <?= $counts['red'] > 0 ? 'red' : ($counts['yellow'] > 0 ? 'yellow' : 'green') ?>">
                <div class="card-title">3. Phân Vùng Rủi Ro Cảnh Báo</div>
                <div class="stat-item">
                    <span>🔴 Nguy cấp (Vùng Đỏ):</span> 
                    <strong style="color: #dc2626; font-size: 14px;"><?= $counts['red'] ?> HS (<?= $total > 0 ? round(($counts['red'] / $total) * 100, 1) : 0 ?>%)</strong>
                </div>
                <div class="stat-item">
                    <span>🟡 Cần chú ý (Vùng Vàng):</span> 
                    <strong style="color: #d97706; font-size: 14px;"><?= $counts['yellow'] ?> HS (<?= $total > 0 ? round(($counts['yellow'] / $total) * 100, 1) : 0 ?>%)</strong>
                </div>
                <div class="stat-item">
                    <span>🟢 An toàn (Vùng Xanh):</span> 
                    <strong style="color: #16a34a; font-size: 14px;"><?= $counts['green'] ?> HS (<?= $total > 0 ? round(($counts['green'] / $total) * 100, 1) : 0 ?>%)</strong>
                </div>
            </div>
        </div>

        <!-- PHẦN 2: DANH SÁCH HỌC SINH CẦN PHỤ ĐẠO & CAN THIỆP -->
        <div class="section-header">II. Danh Sách Học Sinh Cần Hội Chẩn Sư Phạm & Phụ Đạo (<?= count($atRiskStudents) ?> HS)</div>
        <?php if (empty($atRiskStudents)): ?>
            <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 14px; text-align: center; color: #065f46; font-weight: 700;">
                🎉 Lớp <?= htmlspecialchars($className) ?> duy trì phong độ rất tốt! Không có học sinh thuộc vùng rủi ro hoặc tuột dốc đáng báo động.
            </div>
        <?php else: ?>
            <table class="radar-table">
                <thead>
                    <tr>
                        <th style="width: 35px;">STT</th>
                        <th style="width: 75px;">Mã HS</th>
                        <th>Họ và Tên</th>
                        <th style="width: 40px;">GK</th>
                        <th style="width: 40px;">CK</th>
                        <th style="width: 45px;">ĐTB</th>
                        <th style="width: 120px;">Động Năng</th>
                        <th style="width: 85px;">Khung Điểm</th>
                        <th style="width: 90px;">Mức Rủi Ro</th>
                        <th>Chẩn Đoán Sư Phạm & Kế Hoạch</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rIdx = 1;
                    foreach ($atRiskStudents as $st): 
                        $badgeClass = ($st['level'] === 'red') ? 'badge-red' : (($st['level'] === 'yellow') ? 'badge-yellow' : 'badge-green');
                        $badgeText = ($st['level'] === 'red') ? '🔴 Nguy cấp' : (($st['level'] === 'yellow') ? '🟡 Chú ý' : '🟢 Ổn định');
                    ?>
                        <tr>
                            <td class="center"><?= $rIdx++ ?></td>
                            <td class="center" style="font-family: 'JetBrains Mono', monospace; font-size: 11px;">
                                <?= htmlspecialchars($st['student_code'] ?? ('HS' . $st['student_id'])) ?>
                            </td>
                            <td class="bold"><?= htmlspecialchars($st['full_name']) ?></td>
                            <td class="center"><?= $st['gk'] !== null ? number_format((float)$st['gk'], 1) : '-' ?></td>
                            <td class="center"><?= $st['ck'] !== null ? number_format((float)$st['ck'], 1) : '-' ?></td>
                            <td class="center bold" style="color: <?= ($st['avg_score'] !== null && (float)$st['avg_score'] < 5.0) ? '#dc2626' : '#0f172a' ?>;">
                                <?= $st['avg_score'] !== null ? number_format((float)$st['avg_score'], 1) : '-' ?>
                            </td>
                            <td>
                                <?= htmlspecialchars(($st['trend_icon'] ?? '') . ' ' . ($st['trend_label'] ?? '')) ?>
                            </td>
                            <td class="center">
                                <?= htmlspecialchars($st['score_tier']['tier_name'] ?? 'Chưa đạt') ?>
                            </td>
                            <td class="center">
                                <span class="badge-pill <?= $badgeClass ?>"><?= $badgeText ?></span>
                            </td>
                            <td style="font-size: 11.5px; line-height: 1.4;">
                                <strong><?= htmlspecialchars($st['diagnostic'] ?? '') ?></strong><br>
                                <span style="color: #475569;">Khuyến nghị: <?= htmlspecialchars($st['competency']['pedagogical_advice'] ?? '') ?></span>
                                <?php if (!empty($st['teacher_note'])): ?>
                                    <div style="margin-top: 2px; color: #4338ca; font-style: italic;">
                                        &bull; Ghi chú GV: <?= htmlspecialchars($st['teacher_note']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- ĐỀ XUẤT CỦA GIÁO VIÊN BỘ MÔN -->
        <div class="advice-box">
            <strong>ĐỀ XUẤT PHƯƠNG ÁN SƯ PHẠM VÀ PHỐI HỢP CÙNG NHÀ TRƯỜNG:</strong><br>
            1. Tổ chức phụ đạo chuyên đề ngoài giờ đối với nhóm học sinh <strong>Chưa đạt (&lt; 5.0)</strong> hoặc <strong>Tuột dốc liên tục</strong> trong vòng 2 tuần tới.<br>
            2. Giáo viên chủ nhiệm phối hợp liên hệ phụ huynh các học sinh vùng Đỏ để ký biên bản cam kết kèm cặp việc làm bài tập ở nhà.<br>
            3. Phân công cán sự học tập theo mô hình "Đôi bạn cùng tiến" kèm cặp trực tiếp trong các tiết luyện tập và bài tập nhóm.
        </div>

        <!-- KHUNG CHỮ KÝ 3 BÊN -->
        <div class="signatures-grid">
            <div class="sig-col">
                <div>
                    <div class="sig-role">GIÁO VIÊN BỘ MÔN</div>
                    <div class="sig-sub">(Ký và ghi rõ họ tên)</div>
                </div>
                <div class="sig-name"><?= htmlspecialchars($teacherName) ?></div>
            </div>
            <div class="sig-col">
                <div>
                    <div class="sig-role">TỔ TRƯỞNG CHUYÊN MÔN</div>
                    <div class="sig-sub">(Ký duyệt và chỉ đạo)</div>
                </div>
                <div class="sig-name">...................................................</div>
            </div>
            <div class="sig-col">
                <div>
                    <div class="sig-role">BAN GIÁM HIỆU PHÊ DUYỆT</div>
                    <div class="sig-sub">(Hiệu trưởng / Phó Hiệu trưởng)</div>
                </div>
                <div class="sig-name">...................................................</div>
            </div>
        </div>
    </div>

</body>
</html>
