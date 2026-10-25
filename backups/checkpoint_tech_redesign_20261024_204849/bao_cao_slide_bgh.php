<?php
/**
 * =============================================================================
 * Hệ Thống Quản Lý Điểm & Cảnh Báo Sớm THCS (EduRadar)
 * Module: Slide Thuyết Trình Báo Cáo Sư Phạm Nộp Ban Giám Hiệu (HTML + Chart.js)
 * Được xây dựng theo chuẩn Apple Design + Hallmark + Strategic Slides Skill
 * =============================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/radar_helper.php';

$pdo = db();
$classId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$subject = isset($_GET['subject']) ? trim((string)$_GET['subject']) : '';
$isGuest = isset($_GET['guest']) && $_GET['guest'] === '1';

$className = 'Toàn Trường';
$subjectName = $subject !== '' && $subject !== 'all' ? $subject : 'Tất Cả Bộ Môn';
$teacherName = 'Ban Giám Hiệu & Hội Đồng Sư Phạm';
$evaluatedStudents = [];

if ($isGuest && isset($_SESSION['guest_scan_data']['students'])) {
    $guestData = $_SESSION['guest_scan_data'];
    $className = $guestData['class_name'] ?? 'Lớp Quét Nhanh';
    $subjectName = $guestData['subject'] ?? 'Bộ Môn';
    $teacherName = 'Giáo Viên Bộ Môn (Chế Độ Quét Nhanh)';
    foreach ($guestData['students'] as $s) {
        $avg = ($s['avg_score'] !== null && $s['avg_score'] !== '') ? (float)$s['avg_score'] : null;
        $gk  = ($s['gk'] !== null && $s['gk'] !== '') ? (float)$s['gk'] : null;
        $ck  = ($s['ck'] !== null && $s['ck'] !== '') ? (float)$s['ck'] : null;
        $txList = [];
        foreach (['tx1', 'tx2', 'tx3', 'tx4'] as $col) {
            if (isset($s[$col]) && $s[$col] !== null && $s[$col] !== '') {
                $txList[] = (float)$s[$col];
            }
        }
        $eval = evaluateRiskZone($avg, $gk, $ck, $txList);
        $evaluatedStudents[] = array_merge($s, $eval);
    }
} else {
    require_login();
    $userId = (int)$_SESSION['user_id'];
    $userRole = $_SESSION['role'] ?? 'teacher';
    $teacherName = $_SESSION['full_name'] ?? ($_SESSION['username'] ?? 'Giáo viên');

    $navCtx = get_user_nav_context($userId);
    if ($classId <= 0 && $navCtx['class_id'] > 0) {
        $classId = $navCtx['class_id'];
    }
    if ($subject === '' && $navCtx['subject'] !== '') {
        $subject = $navCtx['subject'];
    }

    if ($userRole === 'teacher') {
        // Nếu giáo viên không truyền class_id hợp lệ, tự động lấy phân công đầu tiên có học sinh
        if ($classId <= 0 || $subject === '' || $subject === 'all' || !verify_teacher_assignment($userId, $classId, $subject)) {
            $firstAssign = $pdo->prepare("
                SELECT tc.class_id, tc.subject, c.name AS class_name
                FROM teacher_classes tc
                JOIN classes c ON tc.class_id = c.id
                WHERE tc.teacher_id = ?
                ORDER BY (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id) DESC, c.grade ASC, c.name ASC
                LIMIT 1
            ");
            $firstAssign->execute([$userId]);
            $fa = $firstAssign->fetch();
            if (!$fa) {
                http_response_code(403);
                render_forbidden_page('Tài khoản giáo viên chưa có phân công chuyên môn để xuất Slide Báo Cáo.');
                exit;
            }
            // Nếu cố tình truyền class_id không được phép trên URL -> Chặn 403 Anti-BOLA
            if (isset($_GET['class_id']) && (int)$_GET['class_id'] > 0 && !verify_teacher_assignment($userId, (int)$_GET['class_id'], $subject !== '' && $subject !== 'all' ? $subject : $fa['subject'])) {
                http_response_code(403);
                log_action($userId, $_SESSION['username'] ?? 'gv', 'Truy cập trái phép bị chặn', "Từ chối xuất Slide BGH trái phép tại lớp ID [{$_GET['class_id']}] môn [$subject].");
                render_forbidden_page("Bạn không được phân công giảng dạy tại lớp này.");
                exit;
            }
            $classId = (int)$fa['class_id'];
            $subject = $fa['subject'];
        }
    }

    if ($classId > 0) {
        $cStmt = $pdo->prepare("SELECT name FROM classes WHERE id = ?");
        $cStmt->execute([$classId]);
        $cName = $cStmt->fetchColumn();
        if ($cName) {
            $className = 'Lớp ' . $cName;
        }
    }

    $where = [];
    $params = [];
    if ($userRole === 'teacher') {
        $where[] = 'tc.teacher_id = ?';
        $params[] = $userId;
    }
    if ($classId > 0) {
        $where[] = 's.class_id = ?';
        $params[] = $classId;
    }
    if ($subject !== '' && $subject !== 'all') {
        $where[] = 'tc.subject = ?';
        $params[] = $subject;
        $subjectName = 'Môn ' . $subject;
    } else {
        $subjectName = 'Đa Bộ Môn';
    }

    $whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';
    $sql = "
        SELECT s.id AS student_id, s.student_code, s.full_name, c.name AS class_name, tc.subject,
               sc.tx1, sc.tx2, sc.tx3, sc.tx4, sc.gk, sc.ck, sc.avg_score, sc.teacher_note
        FROM teacher_classes tc
        JOIN classes c ON tc.class_id = c.id
        JOIN students s ON s.class_id = c.id
        LEFT JOIN scores sc ON sc.student_id = s.id AND sc.subject = tc.subject
        $whereSql
        ORDER BY c.grade ASC, c.name ASC, s.full_name ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as $s) {
        $avg = ($s['avg_score'] !== null && $s['avg_score'] !== '') ? (float)$s['avg_score'] : null;
        $gk  = ($s['gk'] !== null && $s['gk'] !== '') ? (float)$s['gk'] : null;
        $ck  = ($s['ck'] !== null && $s['ck'] !== '') ? (float)$s['ck'] : null;
        $txList = [];
        foreach (['tx1', 'tx2', 'tx3', 'tx4'] as $col) {
            if ($s[$col] !== null && $s[$col] !== '') {
                $txList[] = (float)$s[$col];
            }
        }
        $eval = evaluateRiskZone($avg, $gk, $ck, $txList);
        $evaluatedStudents[] = array_merge($s, $eval);
    }
}

sortStudentsByRisk($evaluatedStudents);
$stats = calculateRadarStats($evaluatedStudents);

$urgentList = array_values(array_filter($evaluatedStudents, fn($st) => ($st['level'] ?? '') === 'red' || ($st['level'] ?? '') === 'yellow'));
$starList   = array_values(array_filter($evaluatedStudents, fn($st) => ($st['momentum'] ?? '') === 'surge' || ($st['momentum'] ?? '') === 'up' || (($st['score_tier']['tier'] ?? '') === 'excellent')));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Slide Báo Cáo Sư Phạm BGH — <?= htmlspecialchars($className) ?> (<?= htmlspecialchars($subjectName) ?>)</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --bg-dark: #090d16;
            --surface-glass: rgba(15, 23, 42, 0.78);
            --border-specular: rgba(255, 255, 255, 0.12);
            --indigo: #6366f1;
            --emerald: #10b981;
            --amber: #f59e0b;
            --rose: #ef4444;
            --sky: #38bdf8;
            --spring: cubic-bezier(0.22, 1, 0.36, 1);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: radial-gradient(circle at 18% 18%, #1e1b4b 0%, #090d16 58%, #020617 100%);
            color: #f8fafc;
            min-height: 100vh;
            overflow: hidden;
            font-variant-numeric: tabular-nums;
            -webkit-font-smoothing: antialiased;
        }
        /* Top Floating Apple Glass Bar */
        .deck-topbar {
            position: fixed;
            top: 18px;
            left: 28px;
            right: 28px;
            height: 58px;
            background: rgba(15, 23, 42, 0.72);
            backdrop-filter: blur(22px) saturate(180%);
            -webkit-backdrop-filter: blur(22px) saturate(180%);
            border: 1px solid var(--border-specular);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 22px;
            z-index: 100;
            box-shadow: 0 18px 40px -12px rgba(0,0,0,0.5);
        }
        .deck-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
            font-size: 15px;
            letter-spacing: -0.02em;
        }
        .deck-logo {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: linear-gradient(135deg, #6366f1, #10b981);
            display: grid;
            place-items: center;
            font-size: 17px;
        }
        .deck-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .deck-btn {
            background: rgba(255,255,255,0.08);
            color: #f8fafc;
            border: 1px solid rgba(255,255,255,0.14);
            padding: 8px 14px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: transform 0.15s var(--spring), background 0.2s ease;
        }
        .deck-btn:hover { background: rgba(255,255,255,0.16); }
        .deck-btn:active { transform: scale(0.96); }
        .deck-btn.primary {
            background: linear-gradient(135deg, #4f46e5, #6366f1);
            border-color: rgba(255,255,255,0.25);
        }
        /* Slide Viewport */
        .slides-viewport {
            position: relative;
            width: 100vw;
            height: 100vh;
            padding: 94px 36px 76px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .slide {
            position: absolute;
            inset: 92px 36px 74px;
            max-width: 1360px;
            margin: 0 auto;
            background: rgba(15, 23, 42, 0.68);
            backdrop-filter: blur(24px);
            border: 1px solid var(--border-specular);
            border-radius: 26px;
            padding: 36px 44px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            opacity: 0;
            pointer-events: none;
            transform: translateY(18px) scale(0.985);
            transition: opacity 0.38s var(--spring), transform 0.38s var(--spring);
            box-shadow: 0 30px 60px -15px rgba(0, 0, 0, 0.65);
            overflow-y: auto;
        }
        .slide.active {
            opacity: 1;
            pointer-events: auto;
            transform: translateY(0) scale(1);
        }
        .slide-kicker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #818cf8;
            background: rgba(99, 102, 241, 0.14);
            border: 1px solid rgba(99, 102, 241, 0.3);
            padding: 5px 12px;
            border-radius: 999px;
            margin-bottom: 12px;
        }
        .slide-title {
            font-size: clamp(26px, 3.2vw, 38px);
            font-weight: 800;
            letter-spacing: -0.025em;
            line-height: 1.15;
            color: #ffffff;
            margin-bottom: 8px;
        }
        .slide-sub {
            font-size: 15px;
            color: #94a3b8;
            line-height: 1.5;
        }
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin: 24px 0;
        }
        .kpi-card {
            background: rgba(30, 41, 59, 0.65);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            padding: 22px 24px;
            position: relative;
            overflow: hidden;
        }
        .kpi-card .kpi-label {
            font-size: 13px;
            font-weight: 700;
            color: #cbd5e1;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .kpi-card .kpi-num {
            font-size: 42px;
            font-weight: 800;
            letter-spacing: -0.03em;
            margin: 10px 0 4px;
            font-variant-numeric: tabular-nums;
        }
        .kpi-card .kpi-desc {
            font-size: 12px;
            color: #94a3b8;
        }
        .charts-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            flex: 1;
            min-height: 290px;
            margin-top: 12px;
        }
        .chart-box {
            background: rgba(30, 41, 59, 0.55);
            border: 1px solid rgba(255,255,255,0.09);
            border-radius: 20px;
            padding: 20px 24px;
            display: flex;
            flex-direction: column;
        }
        .chart-box h3 {
            font-size: 15px;
            font-weight: 800;
            color: #e2e8f0;
            margin-bottom: 12px;
        }
        .chart-canvas-wrap {
            position: relative;
            flex: 1;
            min-height: 220px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .slide-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
            margin-top: 12px;
        }
        .slide-table th {
            text-align: left;
            padding: 11px 14px;
            background: rgba(30, 41, 59, 0.9);
            color: #94a3b8;
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .slide-table td {
            padding: 11px 14px;
            border-bottom: 1px solid rgba(255,255,255,0.07);
            color: #e2e8f0;
        }
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11.5px;
            font-weight: 700;
        }
        .pill.red { background: rgba(239,68,68,0.2); color: #fca5a5; border: 1px solid rgba(239,68,68,0.4); }
        .pill.yellow { background: rgba(245,158,11,0.2); color: #fcd34d; border: 1px solid rgba(245,158,11,0.4); }
        .pill.green { background: rgba(16,185,129,0.2); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.4); }
        /* Bottom Dots Bar */
        .deck-footer {
            position: fixed;
            bottom: 18px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(15, 23, 42, 0.78);
            backdrop-filter: blur(18px);
            border: 1px solid var(--border-specular);
            border-radius: 999px;
            padding: 8px 18px;
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 100;
        }
        .dot-btn {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            border: none;
            background: rgba(255,255,255,0.25);
            cursor: pointer;
            transition: all 0.25s var(--spring);
        }
        .dot-btn.active {
            width: 28px;
            background: #6366f1;
        }
        @media print {
            body { overflow: visible; background: #090d16; }
            .deck-topbar, .deck-footer { display: none !important; }
            .slides-viewport { height: auto; display: block; padding: 0; }
            .slide { position: relative; inset: auto; opacity: 1 !important; pointer-events: auto; transform: none !important; margin-bottom: 24px; page-break-after: always; }
        }
    </style>
</head>
<body>
    <header class="deck-topbar">
        <div class="deck-brand">
            <div class="deck-logo">📡</div>
            <div>
                <div>EduRadar Keynote — Báo Cáo Hội Đồng Sư Phạm &amp; BGH</div>
                <div style="font-size: 11.5px; color: #94a3b8; font-weight: 600;">
                    <?= htmlspecialchars($className) ?> • <?= htmlspecialchars($subjectName) ?> • Chuẩn Thông tư 22/2021/TT-BGDĐT
                </div>
            </div>
        </div>
        <div class="deck-controls">
            <button type="button" class="deck-btn" onclick="prevSlide()">← Slide Trước</button>
            <span id="slideCounter" style="font-size: 13px; font-weight: 800; padding: 0 8px; color: #cbd5e1;">1 / 4</span>
            <button type="button" class="deck-btn primary" onclick="nextSlide()">Slide Tiếp →</button>
            <button type="button" class="deck-btn" onclick="window.print()">🖨️ In / Lưu PDF</button>
            <a href="<?= $isGuest ? 'quet_nhanh.php' : ('index.php' . ($classId > 0 ? '?class_id=' . $classId . ($subject !== '' ? '&subject=' . urlencode($subject) : '') : '')) ?>" class="deck-btn">✕ Đóng</a>
        </div>
    </header>

    <main class="slides-viewport">
        <!-- SLIDE 1: EXECUTIVE SUMMARY -->
        <section class="slide active" data-slide="0">
            <div>
                <span class="slide-kicker">Slide 01 • Toàn Cảnh Chẩn Đoán Sớm</span>
                <h1 class="slide-title">Báo Cáo Động Năng &amp; Phân Vùng Rủi Ro Học Tập — <?= htmlspecialchars($className) ?></h1>
                <p class="slide-sub">
                    Báo cáo tổng hợp bởi <b><?= htmlspecialchars($teacherName) ?></b> • Phân môn: <b><?= htmlspecialchars($subjectName) ?></b> • Ngày trích xuất: <b><?= date('d/m/Y H:i') ?></b>
                </p>

                <div class="kpi-grid">
                    <div class="kpi-card" style="border-top: 4px solid #6366f1;">
                        <div class="kpi-label"><span>👥 TỔNG HỒ SƠ THEO DÕI</span><span>100%</span></div>
                        <div class="kpi-num" style="color: #e0e7ff;"><?= $stats['total'] ?></div>
                        <div class="kpi-desc">Học sinh trong danh sách phân tích</div>
                    </div>
                    <div class="kpi-card" style="border-top: 4px solid #10b981;">
                        <div class="kpi-label"><span>📈 ĐÀ TIẾN BỘ &amp; DUY TRÌ TỐT</span><span><?= round($stats['up_pct'] + $stats['steady_pct'], 1) ?>%</span></div>
                        <div class="kpi-num" style="color: #6ee7b7;"><?= $stats['momentum_up'] + $stats['momentum_steady'] ?></div>
                        <div class="kpi-desc"><?= $stats['momentum_up'] ?> em bứt phá • <?= $stats['momentum_steady'] ?> em đi đều</div>
                    </div>
                    <div class="kpi-card" style="border-top: 4px solid #f59e0b;">
                        <div class="kpi-label"><span>🟡 CẦN CHÚ Ý / TIẾP SỨC</span><span><?= $stats['yellow_pct'] ?>%</span></div>
                        <div class="kpi-num" style="color: #fcd34d;"><?= $stats['yellow'] ?></div>
                        <div class="kpi-desc">Đi đều mức thấp hoặc chớm sa sút</div>
                    </div>
                    <div class="kpi-card" style="border-top: 4px solid #ef4444;">
                        <div class="kpi-label"><span>🔴 BÁO ĐỘNG NGUY CẤP</span><span><?= $stats['red_pct'] ?>%</span></div>
                        <div class="kpi-num" style="color: #fca5a5;"><?= $stats['red'] ?></div>
                        <div class="kpi-desc">ĐTB &lt; 3.5, hỏng kỳ thi hoặc TX nguy cấp</div>
                    </div>
                </div>
            </div>

            <div style="background: rgba(99, 102, 241, 0.12); border: 1px solid rgba(99, 102, 241, 0.28); border-radius: 18px; padding: 18px 22px; display: flex; align-items: center; justify-content: space-between; gap: 16px;">
                <div>
                    <div style="font-size: 13px; font-weight: 800; color: #a5b4fc; text-transform: uppercase; margin-bottom: 4px;">
                        🎯 Nhận Định Trọng Tâm Trình Ban Giám Hiệu
                    </div>
                    <div style="font-size: 14px; color: #e2e8f0; line-height: 1.55;">
                        Hệ thống EduRadar áp dụng <b>Kiến trúc 2 Khung Đánh Giá (Thông tư 22/2021/TT-BGDĐT)</b>: đặt <b>Khung Hiệu Suất (Động năng tiến bộ)</b> lên trước <b>Khung Điểm Số</b>, giúp nhà trường phát hiện sớm <b><?= $stats['tx_warn_count'] ?> trường hợp cảnh báo sớm từ cột điểm TX</b> trước khi thi cuối kỳ.
                    </div>
                </div>
            </div>
        </section>

        <!-- SLIDE 2: BIỂU ĐỒ TƯƠNG TÁC CHART.JS -->
        <section class="slide" data-slide="1">
            <div>
                <span class="slide-kicker">Slide 02 • Biểu Đồ Phân Tích Đa Chiều (Chart.js)</span>
                <h2 class="slide-title">Tương Quan Khung Hiệu Suất &amp; Khung Học Lực Thông Tư 22</h2>
                <p class="slide-sub">Trực quan hóa nhịp độ vận động điểm số và phân tầng năng lực thực tế của học sinh</p>
            </div>

            <div class="charts-row">
                <div class="chart-box">
                    <h3>⚡ 1. Phân Bổ Khung Hiệu Suất (Momentum Trajectory)</h3>
                    <div class="chart-canvas-wrap">
                        <canvas id="chartMomentum"></canvas>
                    </div>
                </div>
                <div class="chart-box">
                    <h3>📊 2. Phân Tầng Học Lực Theo Điểm Số (Mastery Tiers)</h3>
                    <div class="chart-canvas-wrap">
                        <canvas id="chartTiers"></canvas>
                    </div>
                </div>
            </div>
        </section>

        <!-- SLIDE 3: DANH SÁCH ƯU TIÊN PHỤ ĐẠO & CAN THIỆP SỚM -->
        <section class="slide" data-slide="2">
            <div>
                <span class="slide-kicker">Slide 03 • Kế Hoạch Can Thiệp Sư Phạm</span>
                <h2 class="slide-title">Danh Sách Học Sinh Ưu Tiên Tiếp Sức &amp; Phụ Đạo (<?= count($urgentList) ?> HS)</h2>
                <p class="slide-sub">Các trường hợp thuộc vùng Đỏ (Nguy cấp) và vùng Vàng (Cần chú ý) kèm khuyến nghị sư phạm cá nhân hóa</p>

                <table class="slide-table">
                    <thead>
                        <tr>
                            <th>Mã HS</th>
                            <th>Họ và Tên</th>
                            <th>Lớp / Môn</th>
                            <th>1. Hiệu Suất</th>
                            <th>2. ĐTBmhk</th>
                            <th>Mức Rủi Ro</th>
                            <th>Khuyến Nghị Sư Phạm Hành Động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($urgentList)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 32px; color: #6ee7b7; font-weight: 700;">
                                    🎉 Tuyệt vời! Không có học sinh nào nằm trong vùng Nguy cấp hoặc Cần chú ý.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach (array_slice($urgentList, 0, 7) as $st): ?>
                                <tr>
                                    <td style="font-family: 'JetBrains Mono', monospace; color: #a5b4fc;"><?= htmlspecialchars($st['student_code'] ?: ('HS' . ($st['student_id'] ?? ''))) ?></td>
                                    <td style="font-weight: 700; color: #ffffff;"><?= htmlspecialchars($st['full_name']) ?></td>
                                    <td><?= htmlspecialchars(($st['class_name'] ?? $className) . ' • ' . ($st['subject'] ?? $subjectName)) ?></td>
                                    <td><?= htmlspecialchars(($st['trend_icon'] ?? '') . ' ' . ($st['trend_label'] ?? '')) ?></td>
                                    <td style="font-family: 'JetBrains Mono', monospace; font-weight: 800;"><?= $st['avg_score'] !== null && $st['avg_score'] !== '' ? number_format((float)$st['avg_score'], 1) : '—' ?></td>
                                    <td>
                                        <span class="pill <?= $st['level'] === 'red' ? 'red' : 'yellow' ?>">
                                            <?= $st['level'] === 'red' ? '🔴 Nguy cấp' : '🟡 Cần chú ý' ?>
                                        </span>
                                    </td>
                                    <td style="font-size: 12.5px; color: #cbd5e1; max-width: 420px;">
                                        <b style="color: #93c5fd;"><?= htmlspecialchars($st['competency']['competency_title'] ?? '') ?>:</b>
                                        <?= htmlspecialchars($st['competency']['pedagogical_advice'] ?? '') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div style="font-size: 12px; color: #94a3b8;">
                💡 <i>Ghi chú: Sử dụng phím mũi tên <code>←</code> <code>→</code> trên bàn phím để chuyển nhanh giữa các Slide khi báo cáo trước Hội đồng.</i>
            </div>
        </section>

        <!-- SLIDE 4: TUYÊN DƯƠNG BỨT PHÁ & ĐỀ XUẤT BGH -->
        <section class="slide" data-slide="3">
            <div>
                <span class="slide-kicker">Slide 04 • Vinh Danh Nỗ Lực &amp; Đề Xuất</span>
                <h2 class="slide-title">Gương Mặt Bứt Phá Xuất Sắc &amp; Kiến Nghị Chuyên Môn (<?= count($starList) ?> HS tiêu biểu)</h2>
                <p class="slide-sub">Tuyên dương các học sinh có đà tiến bộ vượt khó hoặc duy trì phong độ đỉnh cao</p>

                <table class="slide-table">
                    <thead>
                        <tr>
                            <th>Mã HS</th>
                            <th>Họ và Tên</th>
                            <th>Lớp / Môn</th>
                            <th>Động Năng</th>
                            <th>Học Lực</th>
                            <th>ĐTBmhk</th>
                            <th>Định Hướng Phát Triển &amp; Bồi Dưỡng</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($starList)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 28px; color: #94a3b8;">
                                    Đang tiếp tục cập nhật danh sách học sinh bứt phá trong học kỳ.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach (array_slice($starList, 0, 6) as $st): ?>
                                <tr>
                                    <td style="font-family: 'JetBrains Mono', monospace; color: #6ee7b7;"><?= htmlspecialchars($st['student_code'] ?: ('HS' . ($st['student_id'] ?? ''))) ?></td>
                                    <td style="font-weight: 700; color: #ffffff;"><?= htmlspecialchars($st['full_name']) ?></td>
                                    <td><?= htmlspecialchars(($st['class_name'] ?? $className) . ' • ' . ($st['subject'] ?? $subjectName)) ?></td>
                                    <td><span class="pill green"><?= htmlspecialchars(($st['trend_icon'] ?? '📈') . ' ' . ($st['trend_label'] ?? 'Tiến bộ')) ?></span></td>
                                    <td><?= htmlspecialchars($st['score_tier']['badge'] ?? '⭐ Xuất sắc') ?></td>
                                    <td style="font-family: 'JetBrains Mono', monospace; font-weight: 800; color: #6ee7b7;"><?= $st['avg_score'] !== null && $st['avg_score'] !== '' ? number_format((float)$st['avg_score'], 1) : '—' ?></td>
                                    <td style="font-size: 12.5px; color: #cbd5e1;">
                                        <?= htmlspecialchars($st['competency']['pedagogical_advice'] ?? '') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div style="background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 16px; padding: 14px 20px; font-size: 13.5px; color: #d1fae5;">
                ✅ <b>Đề xuất Hội đồng Sư phạm:</b> Triển khai mô hình <i>"Đôi bạn cùng tiến"</i> ghép nhóm từ <b><?= $stats['tier_excellent'] ?> học sinh Giỏi</b> hỗ trợ trực tiếp cho <b><?= $stats['red'] + $stats['yellow'] ?> học sinh vùng Nguy cấp / Cần chú ý</b> ngay trong tuần kế tiếp.
            </div>
        </section>
    </main>

    <footer class="deck-footer">
        <button type="button" class="dot-btn active" onclick="goSlide(0)" title="Slide 1"></button>
        <button type="button" class="dot-btn" onclick="goSlide(1)" title="Slide 2"></button>
        <button type="button" class="dot-btn" onclick="goSlide(2)" title="Slide 3"></button>
        <button type="button" class="dot-btn" onclick="goSlide(3)" title="Slide 4"></button>
    </footer>

    <script>
        let currentSlide = 0;
        const slides = document.querySelectorAll('.slide');
        const dots = document.querySelectorAll('.dot-btn');
        const counter = document.getElementById('slideCounter');

        function goSlide(idx) {
            currentSlide = (idx + slides.length) % slides.length;
            slides.forEach((s, i) => s.classList.toggle('active', i === currentSlide));
            dots.forEach((d, i) => d.classList.toggle('active', i === currentSlide));
            if (counter) counter.textContent = (currentSlide + 1) + ' / ' + slides.length;
        }
        function nextSlide() { goSlide(currentSlide + 1); }
        function prevSlide() { goSlide(currentSlide - 1); }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowRight' || e.key === 'PageDown' || e.key === ' ') {
                e.preventDefault();
                nextSlide();
            } else if (e.key === 'ArrowLeft' || e.key === 'PageUp') {
                e.preventDefault();
                prevSlide();
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            if (typeof Chart === 'undefined') return;
            Chart.defaults.color = '#cbd5e1';
            Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";

            const ctxMom = document.getElementById('chartMomentum');
            if (ctxMom) {
                new Chart(ctxMom, {
                    type: 'doughnut',
                    data: {
                        labels: ['📈 Tiến bộ / Bứt phá', '🔄 Duy trì đều', '📉 Sa sút / Tuột dốc', '⏳ Chờ điểm'],
                        datasets: [{
                            data: [<?= (int)$stats['momentum_up'] ?>, <?= (int)$stats['momentum_steady'] ?>, <?= (int)$stats['momentum_down'] ?>, <?= (int)$stats['momentum_pending'] ?>],
                            backgroundColor: ['#10b981', '#38bdf8', '#ef4444', '#64748b'],
                            borderColor: '#0f172a',
                            borderWidth: 3
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { padding: 16, font: { weight: '700' } } }
                        }
                    }
                });
            }

            const ctxTiers = document.getElementById('chartTiers');
            if (ctxTiers) {
                new Chart(ctxTiers, {
                    type: 'bar',
                    data: {
                        labels: ['⭐ Giỏi (≥8.0)', '✨ Khá (6.5-7.9)', '⚖️ Đạt (5.0-6.4)', '⚠️ Cần phụ đạo (<5.0)'],
                        datasets: [{
                            label: 'Số lượng học sinh',
                            data: [<?= (int)$stats['tier_excellent'] ?>, <?= (int)$stats['tier_good'] ?>, <?= (int)$stats['tier_average'] ?>, <?= (int)$stats['tier_low'] ?>],
                            backgroundColor: ['#10b981', '#6366f1', '#f59e0b', '#ef4444'],
                            borderRadius: 10
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.08)' }, ticks: { precision: 0 } },
                            x: { grid: { display: false } }
                        },
                        plugins: { legend: { display: false } }
                    }
                });
            }
        });
    </script>
</body>
</html>
