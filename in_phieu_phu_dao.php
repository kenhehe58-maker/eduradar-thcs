<?php
/**
 * =============================================================================
 * Hệ Thống Quản Lý Điểm & Cảnh Báo Sớm THCS (EduRadar)
 * Module: Xuất Phiếu Đánh Giá Tiến Bộ & Kế Hoạch Phụ Đạo Học Sinh (PDF & Excel)
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
$studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$subject = isset($_GET['subject']) ? trim($_GET['subject']) : '';
$format = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : 'pdf'; // 'pdf' | 'xlsx'
$isGuest = isset($_GET['guest']) && $_GET['guest'] === '1';

$studentData = null;

if ($isGuest && isset($_SESSION['guest_scan_data'])) {
    // Lấy dữ liệu từ phiên làm việc khách
    $guestList = $_SESSION['guest_scan_data']['students'] ?? [];
    $studentIndex = isset($_GET['idx']) ? (int)$_GET['idx'] : -1;
    if ($studentIndex >= 0 && isset($guestList[$studentIndex])) {
        $studentData = $guestList[$studentIndex];
    } elseif ($studentId > 0) {
        foreach ($guestList as $g) {
            if ((int)($g['student_id'] ?? 0) === $studentId) {
                $studentData = $g;
                break;
            }
        }
    }
    if (!$studentData && !empty($guestList)) {
        $studentData = $guestList[0];
    }
} else {
    // Chế độ tài khoản đã đăng nhập
    require_login();
    $userId = (int)$_SESSION['user_id'];
    $userRole = $_SESSION['role'];

    if ($studentId <= 0 || empty($subject)) {
        die("Vui lòng cung cấp mã học sinh và môn học hợp lệ.");
    }

    $stmt = $pdo->prepare("
        SELECT 
            s.id AS student_id, s.class_id, s.student_code, s.full_name, s.birthday, s.phone,
            c.name AS class_name, c.grade,
            sc.subject, sc.tx1, sc.tx2, sc.tx3, sc.tx4, sc.gk, sc.ck, sc.avg_score, sc.teacher_note
        FROM students s
        JOIN classes c ON s.class_id = c.id
        LEFT JOIN scores sc ON sc.student_id = s.id AND sc.subject = ?
        WHERE s.id = ?
    ");
    $stmt->execute([$subject, $studentId]);
    $raw = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$raw) {
        die("Không tìm thấy dữ liệu học sinh.");
    }

    // Kiểm tra quyền giáo viên
    if ($userRole === 'teacher') {
        $chk = $pdo->prepare("SELECT id FROM teacher_classes WHERE teacher_id = ? AND class_id = ? AND subject = ?");
        $chk->execute([$userId, $raw['class_id'], $subject]);
        if (!$chk->fetch()) {
            http_response_code(403);
            die("Bạn không được phân công phụ trách học sinh này.");
        }
    }

    $avg = ($raw['avg_score'] !== null && $raw['avg_score'] !== '') ? (float)$raw['avg_score'] : null;
    $gk  = ($raw['gk'] !== null && $raw['gk'] !== '') ? (float)$raw['gk'] : null;
    $ck  = ($raw['ck'] !== null && $raw['ck'] !== '') ? (float)$raw['ck'] : null;

    $txList = [];
    foreach (['tx1', 'tx2', 'tx3', 'tx4'] as $col) {
        if ($raw[$col] !== null && $raw[$col] !== '') {
            $txList[] = (float)$raw[$col];
        }
    }

    $evaluation = evaluateRiskZone($avg, $gk, $ck, $txList);
    $studentData = array_merge($raw, $evaluation, ['tx_list' => $txList]);
}

if (!$studentData) {
    die("Dữ liệu hồ sơ học sinh không hợp lệ.");
}

$fullName = $studentData['full_name'] ?? 'Học sinh';
$studentCode = !empty($studentData['student_code']) ? $studentData['student_code'] : ('HS' . ($studentData['student_id'] ?? ''));
$className = $studentData['class_name'] ?? 'Chưa rõ';
$subjName = $studentData['subject'] ?? $subject;
$birthday = !empty($studentData['birthday']) ? date('d/m/Y', strtotime($studentData['birthday'])) : 'Chưa cập nhật';
$phone = $studentData['phone'] ?? 'Chưa cập nhật';
$teacherName = $_SESSION['full_name'] ?? 'Giáo viên bộ môn';

$avgScore = $studentData['avg_score'] !== null ? number_format((float)$studentData['avg_score'], 1) : '-';
$gkScore  = $studentData['gk'] !== null ? number_format((float)$studentData['gk'], 1) : '-';
$ckScore  = $studentData['ck'] !== null ? number_format((float)$studentData['ck'], 1) : '-';

$momentumLabel = ($studentData['trend_icon'] ?? '🔄') . ' ' . ($studentData['trend_label'] ?? 'Duy trì');
$scoreTierLabel = $studentData['score_tier']['badge'] ?? 'Chờ điểm';
$riskLevel = $studentData['level'] ?? 'green';
$riskLabel = $riskLevel === 'red' ? '🔴 Nguy cấp' : ($riskLevel === 'yellow' ? '🟡 Cần chú ý' : '🟢 Ổn định');

$diagnostic = $studentData['diagnostic'] ?? 'Chưa có chẩn đoán chi tiết.';
$competencyTitle = $studentData['competency']['competency_title'] ?? 'Năng lực học tập';
$advice = $studentData['competency']['pedagogical_advice'] ?? 'Chưa có khuyến nghị.';
$teacherNote = !empty($studentData['teacher_note']) ? $studentData['teacher_note'] : 'Giáo viên chưa ghi chú thêm.';

// =============================================================================
// NẾU CHỌN XUẤT FILE EXCEL (.XLSX)
// =============================================================================
if ($format === 'xlsx') {
    $rows = [
        ['PHIẾU ĐÁNH GIÁ TIẾN TRÌNH HỌC TẬP & KẾ HOẠCH PHỤ ĐẠO HỌC SINH'],
        ['Căn cứ Thông tư 22/2021/TT-BGDĐT - Hệ thống EduRadar THCS'],
        [''],
        ['Họ và tên học sinh:', $fullName, 'Mã số học sinh:', $studentCode],
        ['Lớp:', $className, 'Môn học:', $subjName],
        ['Ngày sinh:', $birthday, 'Điện thoại phụ huynh:', $phone],
        ['Giáo viên phụ trách:', $teacherName, 'Ngày xuất phiếu:', date('d/m/Y')],
        [''],
        ['CHUỖI ĐIỂM SỐ CHI TIẾT THEO THÔNG TƯ 22'],
        ['ĐĐGtx1', 'ĐĐGtx2', 'ĐĐGtx3', 'ĐĐGtx4', 'ĐĐGgk (x2)', 'ĐĐGck (x3)', 'ĐTB môn HK'],
        [
            $studentData['tx1'] !== null ? (float)$studentData['tx1'] : '-',
            $studentData['tx2'] !== null ? (float)$studentData['tx2'] : '-',
            $studentData['tx3'] !== null ? (float)$studentData['tx3'] : '-',
            $studentData['tx4'] !== null ? (float)$studentData['tx4'] : '-',
            $gkScore,
            $ckScore,
            $avgScore
        ],
        [''],
        ['KẾT QUẢ ĐÁNH GIÁ ĐA CHIỀU'],
        ['1. Xu Hướng Tiến Bộ (OLS):', $momentumLabel],
        ['2. Bậc Học Lực (TT22):', $scoreTierLabel],
        ['3. Phân vùng rủi ro:', $riskLabel],
        [''],
        ['CHẨN ĐOÁN & KẾ HOẠCH CAN THIỆP SƯ PHẠM'],
        ['Chẩn đoán hệ thống:', $diagnostic],
        ['Đánh giá năng lực tổng thể:', $competencyTitle],
        ['Khuyến nghị hành động sư phạm:', $advice],
        ['Ghi chú / Kế hoạch phụ đạo của GV:', $teacherNote],
        [''],
        ['Ý KIẾN & CHỮ KÝ XÁC NHẬN CỦA PHỤ HUYNH HỌC SINH'],
        ['(Ký, ghi rõ họ tên và ý kiến phối hợp giáo dục):'],
        [''],
        [''],
        ['XÁC NHẬN CỦA GIÁO VIÊN BỘ MÔN', '', '', 'XÁC NHẬN CỦA BAN GIÁM HIỆU']
    ];

    $cleanName = preg_replace('/[^a-zA-Z0-9_]/', '', str_replace(' ', '_', $fullName));
    $filename = "Phieu_Phu_Dao_{$cleanName}_{$className}.xlsx";
    $xlsxBin = create_xlsx($rows, 'PhieuPhuDao');

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
    header('Content-Length: ' . strlen($xlsxBin));
    header('Cache-Control: max-age=0');
    echo $xlsxBin;
    exit;
}

// =============================================================================
// NẾU CHỌN XUẤT ĐỊNH DẠNG TRANG IN CHUẨN A4 / PRINT-TO-PDF
// =============================================================================
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Phiếu Phụ Đạo – <?= htmlspecialchars($fullName) ?> (Lớp <?= htmlspecialchars($className) ?>)</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
            color: #0f172a;
            background: #f1f5f9;
            padding: 24px;
            font-size: 13.5px;
            line-height: 1.5;
        }
        .action-bar {
            max-width: 800px;
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

        /* Khổ giấy A4 chuẩn in ấn */
        .page-a4 {
            max-width: 800px;
            margin: 0 auto;
            background: #ffffff;
            padding: 40px 48px;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
        }
        .header-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            margin-bottom: 24px;
            align-items: flex-start;
        }
        .header-left {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            line-height: 1.6;
        }
        .header-right {
            text-align: center;
            font-size: 12px;
            line-height: 1.6;
        }
        .header-right b {
            font-size: 12.5px;
            text-transform: uppercase;
            display: block;
        }
        .doc-title {
            text-align: center;
            margin: 20px 0 6px 0;
            font-size: 18px;
            font-weight: 800;
            color: #1e1b4b;
            letter-spacing: -0.3px;
            text-transform: uppercase;
        }
        .doc-sub {
            text-align: center;
            font-size: 12px;
            color: #64748b;
            font-style: italic;
            margin-bottom: 24px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .info-table td {
            padding: 6px 10px;
            vertical-align: top;
        }
        .info-label {
            font-weight: 700;
            color: #475569;
            width: 22%;
        }
        .info-val {
            font-weight: 600;
            color: #0f172a;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 14px 0 20px 0;
            font-size: 13px;
        }
        .data-table th, .data-table td {
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            text-align: center;
        }
        .data-table th {
            background: #f1f5f9;
            font-weight: 800;
            color: #334155;
            font-size: 12px;
            text-transform: uppercase;
        }
        .box-section {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 14px 18px;
            margin-bottom: 16px;
        }
        .box-title {
            font-size: 13px;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .signature-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            margin-top: 36px;
            text-align: center;
            page-break-inside: avoid;
        }
        .sig-title {
            font-weight: 700;
            font-size: 13px;
            text-transform: uppercase;
            margin-bottom: 70px;
        }
        .sig-name {
            font-weight: 700;
            color: #0f172a;
        }

        /* Tối ưu hóa tuyệt đối cho máy in và xuất PDF */
        @media print {
            body { background: #ffffff; padding: 0; }
            .action-bar { display: none !important; }
            .page-a4 {
                box-shadow: none;
                border: none;
                padding: 0;
                width: 100%;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>

<!-- THANH CÔNG CỤ TÙY CHỌN IN HOẶC TẢI EXCEL -->
<div class="action-bar">
    <div>
        <b style="color: #1e1b4b; font-size: 14px;">📄 Phiếu Đánh Giá Sư Phạm & Kế Hoạch Phụ Đạo</b>
        <div style="font-size: 12px; color: #64748b;">Học sinh: <b><?= htmlspecialchars($fullName) ?></b> (Lớp <?= htmlspecialchars($className) ?> • Môn <?= htmlspecialchars($subjName) ?>)</div>
    </div>
    <div style="display: flex; gap: 8px;">
        <button type="button" class="btn-action btn-print" onclick="window.print()">
            🖨️ In / Lưu PDF (A4)
        </button>
        <a href="in_phieu_phu_dao.php?<?= http_build_query(array_merge($_GET, ['format' => 'xlsx'])) ?>" class="btn-action btn-excel">
            📥 Tải File Excel (.xlsx)
        </a>
        <button type="button" class="btn-action btn-close" onclick="window.close()">
            ✕ Đóng
        </button>
    </div>
</div>

<!-- NỘI DUNG VĂN BẢN CHUẨN A4 -->
<div class="page-a4">
    <div class="header-grid">
        <div class="header-left">
            <div>SỞ GIÁO DỤC VÀ ĐÀO TẠO</div>
            <div><?= defined('SCHOOL_NAME') ? SCHOOL_NAME : 'TRƯỜNG THCS GIẢNG VÕ' ?></div>
            <div style="font-weight: normal; font-size: 11px; color: #64748b;">Tổ Chuyên Môn THCS</div>
        </div>
        <div class="header-right">
            <b>CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</b>
            <div style="font-size: 12px; text-decoration: underline; font-weight: 700;">Độc lập – Tự do – Hạnh phúc</div>
            <div style="font-size: 11px; font-style: italic; color: #64748b; margin-top: 4px;">Hà Nội, ngày <?= date('d') ?> tháng <?= date('m') ?> năm <?= date('Y') ?></div>
        </div>
    </div>

    <h1 class="doc-title">PHIẾU ĐÁNH GIÁ TIẾN BỘ & KẾ HOẠCH PHỤ ĐẠO</h1>
    <div class="doc-sub">(Ban hành theo quy chế đánh giá học sinh THCS – Thông tư 22/2021/TT-BGDĐT)</div>

    <table class="info-table">
        <tr>
            <td class="info-label">Họ và tên học sinh:</td>
            <td class="info-val"><b style="font-size: 15px; color: #1e1b4b;"><?= htmlspecialchars($fullName) ?></b></td>
            <td class="info-label">Mã số học sinh:</td>
            <td class="info-val"><code><?= htmlspecialchars($studentCode) ?></code></td>
        </tr>
        <tr>
            <td class="info-label">Lớp học:</td>
            <td class="info-val">Lớp <?= htmlspecialchars($className) ?></td>
            <td class="info-label">Môn học phụ đạo:</td>
            <td class="info-val"><b style="color: #4338ca;">Môn <?= htmlspecialchars($subjName) ?></b></td>
        </tr>
        <tr>
            <td class="info-label">Ngày sinh:</td>
            <td class="info-val"><?= htmlspecialchars($birthday) ?></td>
            <td class="info-label">Điện thoại PHHS:</td>
            <td class="info-val"><?= htmlspecialchars($phone) ?></td>
        </tr>
        <tr>
            <td class="info-label">Giáo viên phụ trách:</td>
            <td class="info-val"><?= htmlspecialchars($teacherName) ?></td>
            <td class="info-label">Phân vùng rủi ro:</td>
            <td class="info-val"><b><?= $riskLabel ?></b></td>
        </tr>
    </table>

    <div style="font-weight: 800; font-size: 13.5px; color: #1e293b; margin-top: 10px;">
        1. BẢNG TIẾN TRÌNH ĐIỂM SỐ & DIỄN BIẾN HỌC TẬP
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>ĐĐGtx 1</th>
                <th>ĐĐGtx 2</th>
                <th>ĐĐGtx 3</th>
                <th>ĐĐGtx 4</th>
                <th>ĐĐG Giữa Kỳ (x2)</th>
                <th>ĐĐG Cuối Kỳ (x3)</th>
                <th style="background: #e0e7ff; color: #312e81;">ĐTB Môn Học Kỳ</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?= $studentData['tx1'] !== null ? number_format((float)$studentData['tx1'], 1) : '-' ?></td>
                <td><?= $studentData['tx2'] !== null ? number_format((float)$studentData['tx2'], 1) : '-' ?></td>
                <td><?= $studentData['tx3'] !== null ? number_format((float)$studentData['tx3'], 1) : '-' ?></td>
                <td><?= $studentData['tx4'] !== null ? number_format((float)$studentData['tx4'], 1) : '-' ?></td>
                <td><b><?= $gkScore ?></b></td>
                <td><b><?= $ckScore ?></b></td>
                <td style="font-weight: 800; font-size: 15px; color: #4338ca; background: #eef2ff;"><?= $avgScore ?></td>
            </tr>
        </tbody>
    </table>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
        <div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; background: #f8fafc;">
            <div style="font-size: 11px; font-weight: 800; text-transform: uppercase; color: #64748b;">⚡ 1. Xu Hướng Tiến Bộ (OLS)</div>
            <div style="font-size: 14px; font-weight: 800; color: #0f172a; margin-top: 3px;"><?= $momentumLabel ?></div>
        </div>
        <div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; background: #f8fafc;">
            <div style="font-size: 11px; font-weight: 800; text-transform: uppercase; color: #64748b;">📊 2. Bậc Học Lực (TT22)</div>
            <div style="font-size: 14px; font-weight: 800; color: #0f172a; margin-top: 3px;"><?= $scoreTierLabel ?></div>
        </div>
    </div>

    <div class="box-section">
        <div class="box-title" style="color: #4338ca;">2. Chẩn Đoán Chi Tiết Từ Hệ Thống Radar:</div>
        <div style="color: #334155; font-size: 13.5px;"><?= htmlspecialchars($diagnostic) ?></div>
    </div>

    <div class="box-section">
        <div class="box-title" style="color: #0369a1;">3. Đánh Giá Năng Lực & Kế Hoạch Can Thiệp Sư Phạm:</div>
        <div style="font-weight: 700; color: #1e3a8a; margin-bottom: 4px;"><?= htmlspecialchars($competencyTitle) ?></div>
        <div style="color: #334155; font-size: 13.5px;"><?= htmlspecialchars($advice) ?></div>
    </div>

    <div class="box-section" style="background: #ffffff; border: 1.5px solid #cbd5e1;">
        <div class="box-title" style="color: #0f172a;">4. Ghi Chú & Kế Hoạch Phụ Đạo Riêng Của Giáo Viên Bộ Môn:</div>
        <div style="color: #1e293b; font-size: 13.5px; min-height: 40px; font-style: <?= empty($studentData['teacher_note']) ? 'italic' : 'normal' ?>;">
            <?= htmlspecialchars($teacherNote) ?>
        </div>
    </div>

    <div class="box-section" style="background: #ffffff; border: 1px dashed #cbd5e1; margin-top: 14px;">
        <div class="box-title">5. Ý Kiến & Phản Hồi Phối Hợp Của Phụ Huynh Học Sinh:</div>
        <div style="color: #94a3b8; font-size: 12.5px; height: 45px; border-bottom: 1px dotted #cbd5e1; margin-bottom: 6px;"></div>
    </div>

    <div class="signature-grid">
        <div>
            <div class="sig-title">Ý KIẾN & CHỮ KÝ PHỤ HUYNH</div>
            <div style="font-size: 12px; color: #64748b; margin-top: 60px;">(Ký và ghi rõ họ tên)</div>
        </div>
        <div>
            <div class="sig-title">GIÁO VIÊN BỘ MÔN PHỤ TRÁCH</div>
            <div class="sig-name" style="margin-top: 60px;"><?= htmlspecialchars($teacherName) ?></div>
        </div>
    </div>
</div>

<script>
// Tự động mở hộp thoại in nếu người dùng bấm từ màn hình popup
if (window.location.search.includes('autoprint=1')) {
    window.addEventListener('load', () => {
        window.print();
    });
}
</script>
</body>
</html>
