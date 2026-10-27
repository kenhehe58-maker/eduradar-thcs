<?php
/**
 * =============================================================================
 * Hệ Thống Quản Lý Điểm & Cảnh Báo Sớm THCS (EduRadar)
 * BẢN ĐỒ NHIỆT HỌC LỰC TOÀN TRƯỜNG & SỔ THEO DÕI CAN THIỆP SƯ PHẠM (HEATMAP & INTERVENTION)
 * Chuẩn Bento-Grid + Apple Design System + System Design Primer Audit Trail
 * =============================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/radar_helper.php';
require_once __DIR__ . '/includes/icons.php';

require_login();
$pdo = db();
$realUserId = (int)$_SESSION['user_id'];
$realUserRole = $_SESSION['role'] ?? 'teacher';

// Xử lý ghi nhận / cập nhật biện pháp can thiệp qua AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_intervention') {
    header('Content-Type: application/json; charset=utf-8');
    $stId = (int)($_POST['student_id'] ?? 0);
    $sub = trim((string)($_POST['subject'] ?? ''));
    $risk = trim((string)($_POST['risk_level'] ?? 'yellow'));
    $actType = trim((string)($_POST['action_type'] ?? 'Phụ đạo ngoài giờ'));
    $target = (float)($_POST['target_score'] ?? 5.0);
    $status = trim((string)($_POST['status'] ?? 'in_progress'));
    $notes = trim((string)($_POST['teacher_notes'] ?? ''));

    if ($stId <= 0 || $sub === '') {
        echo json_encode(['success' => false, 'error' => 'Dữ liệu học sinh hoặc môn học không hợp lệ.']);
        exit;
    }

    $ok = record_student_intervention($stId, $sub, $risk, $actType, $target, $status, $notes, $realUserId);
    log_action($realUserId, $_SESSION['username'], 'Ghi nhận can thiệp', "Ghi nhận kế hoạch [$actType] cho học sinh ID $stId môn $sub.");
    echo json_encode(['success' => $ok]);
    exit;
}

// Lấy danh sách toàn bộ phân môn có trong hệ thống
$subjectsList = $pdo->query("SELECT DISTINCT subject FROM teacher_classes ORDER BY subject ASC")->fetchAll(PDO::FETCH_COLUMN);
if (empty($subjectsList)) {
    $subjectsList = ['Toán', 'Ngữ Văn', 'Tiếng Anh', 'Khoa Học Tự Nhiên', 'Lịch Sử & Địa Lí', 'Tin Học'];
}

// Lấy danh sách toàn bộ lớp học phân theo khối
$classesRaw = $pdo->query("
    SELECT c.id, c.name, c.grade,
           (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id) as st_count
    FROM classes c
    ORDER BY c.grade ASC, c.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Truy vấn tổng hợp toàn bộ điểm số theo (class_id, subject)
$scoreAggStmt = $pdo->query("
    SELECT 
        s.class_id,
        sc.subject,
        COUNT(sc.id) as total_scored,
        AVG(sc.avg_score) as avg_class_score,
        SUM(CASE WHEN (sc.avg_score < 3.5 OR sc.ck < 3.0) AND sc.ck IS NOT NULL THEN 1 ELSE 0 END) as red_count,
        SUM(CASE WHEN (sc.avg_score >= 3.5 AND sc.avg_score < 5.0) AND sc.ck IS NOT NULL THEN 1 ELSE 0 END) as yellow_count,
        SUM(CASE WHEN sc.avg_score >= 5.0 AND sc.ck IS NOT NULL THEN 1 ELSE 0 END) as green_count,
        SUM(CASE WHEN sc.ck IS NULL AND (sc.tx1 IS NOT NULL OR sc.gk IS NOT NULL) THEN 1 ELSE 0 END) as pending_ck_count
    FROM scores sc
    JOIN students s ON s.id = sc.student_id
    GROUP BY s.class_id, sc.subject
");
$heatmapData = [];
while ($row = $scoreAggStmt->fetch(PDO::FETCH_ASSOC)) {
    $cid = (int)$row['class_id'];
    $sub = $row['subject'];
    $heatmapData[$cid][$sub] = $row;
}

// Truy vấn danh sách học sinh cần can thiệp sư phạm cấp bách (Vùng Đỏ và Vùng Vàng)
$atRiskStmt = $pdo->query("
    SELECT 
        s.id as student_id,
        s.student_code,
        s.full_name,
        c.name as class_name,
        c.grade,
        sc.subject,
        sc.tx1, sc.tx2, sc.tx3, sc.tx4, sc.gk, sc.ck, sc.avg_score,
        CASE 
            WHEN ((sc.avg_score < 3.5 AND sc.avg_score IS NOT NULL) OR (sc.ck < 3.0 AND sc.ck IS NOT NULL) OR (sc.gk < 3.5 AND sc.gk IS NOT NULL)) THEN 'red'
            WHEN ((sc.avg_score >= 3.5 AND sc.avg_score < 5.0 AND sc.avg_score IS NOT NULL) OR (sc.gk >= 3.5 AND sc.gk < 5.0 AND sc.gk IS NOT NULL) OR (sc.tx1 < 4.0 OR sc.tx2 < 4.0)) THEN 'yellow'
            ELSE 'yellow'
        END as risk_zone,
        (SELECT status FROM student_interventions si WHERE si.student_id = s.id AND si.subject = sc.subject ORDER BY si.id DESC LIMIT 1) as latest_status,
        (SELECT action_type FROM student_interventions si WHERE si.student_id = s.id AND si.subject = sc.subject ORDER BY si.id DESC LIMIT 1) as latest_action,
        (SELECT teacher_notes FROM student_interventions si WHERE si.student_id = s.id AND si.subject = sc.subject ORDER BY si.id DESC LIMIT 1) as latest_notes
    FROM scores sc
    JOIN students s ON s.id = sc.student_id
    JOIN classes c ON c.id = s.class_id
    WHERE (sc.avg_score < 5.0 AND sc.avg_score IS NOT NULL)
       OR (sc.ck < 3.0 AND sc.ck IS NOT NULL)
       OR (sc.gk < 4.5 AND sc.gk IS NOT NULL)
       OR (sc.tx1 < 4.0 OR sc.tx2 < 4.0)
    ORDER BY sc.gk ASC, sc.avg_score ASC, c.grade ASC, c.name ASC
    LIMIT 60
");
$atRiskStudents = $atRiskStmt->fetchAll(PDO::FETCH_ASSOC);

// Truy vấn 20 sự kiện chỉnh sửa điểm gần nhất (Score Audit Logs)
$auditLogsStmt = $pdo->query("
    SELECT 
        sal.*,
        s.full_name as student_name,
        c.name as class_name
    FROM score_audit_logs sal
    JOIN students s ON s.id = sal.student_id
    LEFT JOIN classes c ON c.id = s.class_id
    ORDER BY sal.created_at DESC
    LIMIT 20
");
$recentAuditLogs = $auditLogsStmt->fetchAll(PDO::FETCH_ASSOC);

// Thống kê toàn trường
$totalClassesWithStudents = 0;
$totalStudentsInSchool = 0;
foreach ($classesRaw as $c) {
    if ((int)$c['st_count'] > 0) {
        $totalClassesWithStudents++;
        $totalStudentsInSchool += (int)$c['st_count'];
    }
}

$pageTitle = 'Bản Đồ Nhiệt Học Lực & Sổ Can Thiệp Sư Phạm — EduRadar THCS';
$activeNav = 'heatmap';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/side_rail.php';
?>

<div class="main-content" style="padding-bottom: 80px;">
    <!-- HEADER HERO TRANG BẢN ĐỒ NHIỆT (Chuẩn Apple Design & Bento Grid) -->
    <header class="page-header" style="margin-bottom: 24px;">
        <div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
            <div>
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 10px; background: #e0e7ff; color: #4338ca;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2"/>
                            <path d="M3 9h18M3 15h18M9 3v18M15 3v18"/>
                        </svg>
                    </span>
                    <h1 style="font-size: 24px; font-weight: 800; color: var(--text-heading, #0f172a); margin: 0; letter-spacing: -0.02em;">
                        Bản Đồ Nhiệt Học Lực &amp; Sổ Can Thiệp Sư Phạm
                    </h1>
                </div>
                <p style="margin: 0; color: #64748b; font-size: 14px; line-height: 1.5;">
                    Giám sát toàn diện 49 lớp học · Phát hiện sớm điểm nóng sụt giảm phong độ · Truy vết minh bạch lịch sử điểm số (Audit Trail)
                </p>
            </div>

            <!-- CHIP THỐNG KÊ NHANH -->
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <div style="background: #ffffff; border: 1px solid #e2e8f0; padding: 6px 14px; border-radius: 12px; display: flex; align-items: center; gap: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                    <span style="width: 8px; height: 8px; border-radius: 50%; background: #10b981;"></span>
                    <span style="font-size: 13px; font-weight: 700; color: #1e293b;">Quy mô: <?= $totalClassesWithStudents ?> lớp · <?= $totalStudentsInSchool ?> học sinh</span>
                </div>
                <div style="background: #fef2f2; border: 1px solid #fca5a5; padding: 6px 14px; border-radius: 12px; display: flex; align-items: center; gap: 8px;">
                    <span style="width: 8px; height: 8px; border-radius: 50%; background: #dc2626;"></span>
                    <span style="font-size: 13px; font-weight: 700; color: #991b1b;"><?= count($atRiskStudents) ?> học sinh cần phụ đạo</span>
                </div>
            </div>
        </div>
    </header>

    <!-- BENTO GRID SECTION: TỔNG QUAN 3 KHỐI DỮ LIỆU -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 28px;">
        <!-- Card 1: Bản Đồ Nhiệt Toàn Trường -->
        <div class="card" style="padding: 18px 20px; border-radius: 16px; background: #ffffff; border: 1px solid #e2e8f0; box-shadow: 0 2px 8px rgba(0,0,0,0.03);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                <span style="font-size: 12px; font-weight: 800; color: #4f46e5; text-transform: uppercase; letter-spacing: 0.5px;">Bản Đồ Nhiệt (Heatmap)</span>
                <span style="font-size: 11px; background: #eef2ff; color: #4338ca; padding: 2px 8px; border-radius: 6px; font-weight: 700;">Thời gian thực</span>
            </div>
            <div style="font-size: 26px; font-weight: 800; color: #0f172a; margin-bottom: 4px;"><?= count($subjectsList) ?> Phân Môn</div>
            <div style="font-size: 13px; color: #64748b; line-height: 1.4;">Quét ma trận điểm số từng khối lớp, hiển thị trực quan tỷ lệ cần can thiệp.</div>
        </div>

        <!-- Card 2: Sổ Theo Dõi Can Thiệp -->
        <div class="card" style="padding: 18px 20px; border-radius: 16px; background: #ffffff; border: 1px solid #e2e8f0; box-shadow: 0 2px 8px rgba(0,0,0,0.03);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                <span style="font-size: 12px; font-weight: 800; color: #d97706; text-transform: uppercase; letter-spacing: 0.5px;">Sổ Can Thiệp Sư Phạm</span>
                <span style="font-size: 11px; background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 6px; font-weight: 700;">Kế hoạch 1-1</span>
            </div>
            <div style="font-size: 26px; font-weight: 800; color: #0f172a; margin-bottom: 4px;"><?= count($atRiskStudents) ?> Hồ Sơ</div>
            <div style="font-size: 13px; color: #64748b; line-height: 1.4;">Theo dõi biện pháp hỗ trợ từng tuần (kèm riêng, đổi chỗ ngồi, gặp phụ huynh).</div>
        </div>

        <!-- Card 3: Minh Bạch Lịch Sử Điểm Số -->
        <div class="card" style="padding: 18px 20px; border-radius: 16px; background: #ffffff; border: 1px solid #e2e8f0; box-shadow: 0 2px 8px rgba(0,0,0,0.03);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                <span style="font-size: 12px; font-weight: 800; color: #059669; text-transform: uppercase; letter-spacing: 0.5px;">Audit Trail Điểm Số</span>
                <span style="font-size: 11px; background: #ecfdf5; color: #065f46; padding: 2px 8px; border-radius: 6px; font-weight: 700;">Chống gian lận</span>
            </div>
            <div style="font-size: 26px; font-weight: 800; color: #0f172a; margin-bottom: 4px;"><?= count($recentAuditLogs) ?> Lần Sửa</div>
            <div style="font-size: 13px; color: #64748b; line-height: 1.4;">Ghi nhận ai sửa, sửa lúc nào, điểm cũ sang điểm mới theo chuẩn System Design.</div>
        </div>
    </div>

    <!-- TABS ĐIỀU HƯỚNG BENTO -->
    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px;">
        <button type="button" class="tab-btn active" onclick="switchHeatmapTab('tabHeatmapGrid')" id="btnTabHeatmapGrid" style="background: #4f46e5; color: #ffffff; border: none; padding: 8px 18px; border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M3 15h18M9 3v18M15 3v18"/></svg>
            <span>1. Bản Đồ Nhiệt Ma Trận Lớp &amp; Môn</span>
        </button>
        <button type="button" class="tab-btn" onclick="switchHeatmapTab('tabInterventions')" id="btnTabInterventions" style="background: transparent; color: #475569; border: 1px solid #cbd5e1; padding: 8px 18px; border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
            <span>2. Sổ Can Thiệp Sư Phạm (<?= count($atRiskStudents) ?>)</span>
        </button>
        <button type="button" class="tab-btn" onclick="switchHeatmapTab('tabAuditLogs')" id="btnTabAuditLogs" style="background: transparent; color: #475569; border: 1px solid #cbd5e1; padding: 8px 18px; border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            <span>3. Nhật Ký Lịch Sử Sửa Điểm (Audit Trail)</span>
        </button>
    </div>

    <!-- TAB 1: BẢN ĐỒ NHIỆT MA TRẬN LỚP X MÔN -->
    <div id="tabHeatmapGrid" class="heatmap-tab-pane">
        <div style="background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 20px; box-shadow: 0 4px 16px rgba(0,0,0,0.03); overflow-x: auto;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
                <div>
                    <h3 style="margin: 0 0 4px 0; font-size: 16px; font-weight: 800; color: #0f172a;">
                        Ma Trận Phân Bố Học Lực Theo Từng Lớp &amp; Phân Môn
                    </h3>
                    <p style="margin: 0; font-size: 13px; color: #64748b;">
                        Nhấp vào bất kỳ ô điểm nào để chuyển thẳng đến Sổ Điểm / Radar Cảnh Báo của lớp và môn đó.
                    </p>
                </div>

                <!-- CHÚ THÍCH MÃ MÀU 4 BẬC SƯ PHẠM -->
                <div style="display: flex; align-items: center; gap: 12px; font-size: 12px; font-weight: 700; flex-wrap: wrap;">
                    <span style="display: inline-flex; align-items: center; gap: 5px;">
                        <span style="width: 14px; height: 14px; border-radius: 4px; background: #dc2626;"></span>
                        <span style="color: #991b1b;">Nguy cấp (&gt;15%)</span>
                    </span>
                    <span style="display: inline-flex; align-items: center; gap: 5px;">
                        <span style="width: 14px; height: 14px; border-radius: 4px; background: #ea580c;"></span>
                        <span style="color: #c2410c;">Cần chú ý (&gt;25%)</span>
                    </span>
                    <span style="display: inline-flex; align-items: center; gap: 5px;">
                        <span style="width: 14px; height: 14px; border-radius: 4px; background: #16a34a;"></span>
                        <span style="color: #15803d;">Ổn định (&ge;80% Đạt)</span>
                    </span>
                    <span style="display: inline-flex; align-items: center; gap: 5px;">
                        <span style="width: 14px; height: 14px; border-radius: 4px; background: #94a3b8;"></span>
                        <span style="color: #64748b;">Chưa có dữ liệu</span>
                    </span>
                </div>
            </div>

            <!-- TABLE BẢN ĐỒ NHIỆT -->
            <table style="width: 100%; border-collapse: separate; border-spacing: 4px; min-width: 760px;">
                <thead>
                    <tr>
                        <th style="padding: 10px 14px; background: #f8fafc; border-radius: 8px; font-size: 13px; font-weight: 800; color: #334155; text-align: left; width: 140px;">
                            Lớp Học
                        </th>
                        <?php foreach ($subjectsList as $sName): ?>
                            <th style="padding: 10px 8px; background: #f8fafc; border-radius: 8px; font-size: 13px; font-weight: 800; color: #334155; text-align: center;">
                                <?= htmlspecialchars($sName) ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $currentGrade = 0;
                    foreach ($classesRaw as $c): 
                        if ((int)$c['st_count'] === 0) continue; // Ẩn lớp chưa có học sinh
                        $cid = (int)$c['id'];
                        $grade = (int)$c['grade'];

                        // Thêm hàng phân cách khối lớp
                        if ($grade !== $currentGrade):
                            $currentGrade = $grade;
                    ?>
                        <tr>
                            <td colspan="<?= count($subjectsList) + 1 ?>" style="padding: 12px 14px 6px 14px; font-size: 13px; font-weight: 800; color: #4338ca; text-transform: uppercase; letter-spacing: 0.5px; background: #f5f3ff; border-radius: 6px;">
                                Khối <?= $grade ?> (THCS)
                            </td>
                        </tr>
                    <?php endif; ?>

                    <tr>
                        <td style="padding: 8px 12px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; font-weight: 800; color: #0f172a;">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <span><?= htmlspecialchars($c['name']) ?></span>
                                <span style="font-size: 11px; color: #64748b; font-weight: 600;"><?= $c['st_count'] ?> HS</span>
                            </div>
                        </td>

                        <?php foreach ($subjectsList as $sName): 
                            $cell = $heatmapData[$cid][$sName] ?? null;
                            $stCount = (int)$c['st_count'];
                            
                            $cellBg = '#f8fafc';
                            $cellBorder = '#e2e8f0';
                            $cellColor = '#64748b';
                            $displayText = '-';
                            $subText = 'Chưa nhập';
                            $alertTag = '';

                            if ($cell && (int)$cell['total_scored'] > 0) {
                                $scored = (int)$cell['total_scored'];
                                $red = (int)$cell['red_count'];
                                $yellow = (int)$cell['yellow_count'];
                                $green = (int)$cell['green_count'];
                                $avgScore = $cell['avg_class_score'] !== null ? round((float)$cell['avg_class_score'], 1) : null;

                                $redPct = round(($red / max(1, $scored)) * 100);
                                $yellowPct = round(($yellow / max(1, $scored)) * 100);

                                if ($red > 0 && $redPct >= 15) {
                                    $cellBg = '#fef2f2';
                                    $cellBorder = '#fca5a5';
                                    $cellColor = '#991b1b';
                                    $alertTag = '🔴';
                                    $displayText = $avgScore !== null ? "{$avgScore} đ" : 'Nguy cấp';
                                    $subText = "{$red} HS nguy cấp ({$redPct}%)";
                                } elseif ($yellow > 0 && ($yellowPct >= 25 || $red > 0)) {
                                    $cellBg = '#fff7ed';
                                    $cellBorder = '#fed7aa';
                                    $cellColor = '#c2410c';
                                    $alertTag = '🟠';
                                    $displayText = $avgScore !== null ? "{$avgScore} đ" : 'Chú ý';
                                    $subText = "{$yellow} HS cần chú ý ({$yellowPct}%)";
                                } else {
                                    $cellBg = '#f0fdf4';
                                    $cellBorder = '#86efac';
                                    $cellColor = '#15803d';
                                    $alertTag = '🟢';
                                    $displayText = $avgScore !== null ? "{$avgScore} đ" : 'Tốt';
                                    $subText = "Ổn định ({$green}/{$scored} Đạt)";
                                }
                            }
                        ?>
                            <td style="padding: 2px;">
                                <a href="index.php?class_id=<?= $cid ?>&subject=<?= urlencode($sName) ?>" style="display: block; text-decoration: none; padding: 10px 6px; border-radius: 8px; background: <?= $cellBg ?>; border: 1.5px solid <?= $cellBorder ?>; text-align: center; color: <?= $cellColor ?>; transition: transform 0.15s ease, box-shadow 0.15s ease;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.06)';" onmouseout="this.style.transform='none'; this.style.boxShadow='none';">
                                    <div style="font-size: 13px; font-weight: 800; line-height: 1.2;">
                                        <?= $alertTag ?> <?= $displayText ?>
                                    </div>
                                    <div style="font-size: 10px; font-weight: 600; opacity: 0.85; margin-top: 2px;">
                                        <?= $subText ?>
                                    </div>
                                </a>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB 2: SỔ CAN THIỆP SƯ PHẠM (HỒ SƠ HỌC SINH NGUY CẤP & CẦN CHÚ Ý) -->
    <div id="tabInterventions" class="heatmap-tab-pane" style="display: none;">
        <div style="background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 20px; box-shadow: 0 4px 16px rgba(0,0,0,0.03);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
                <div>
                    <h3 style="margin: 0 0 4px 0; font-size: 16px; font-weight: 800; color: #0f172a;">
                        Danh Sách Học Sinh Vùng Nguy Cấp &amp; Biện Pháp Can Thiệp (<?= count($atRiskStudents) ?> Em)
                    </h3>
                    <p style="margin: 0; font-size: 13px; color: #64748b;">
                        Quy định can thiệp cá nhân hóa: Giáo viên bộ môn cập nhật tiến độ hỗ trợ hàng tuần theo định hướng sư phạm tích cực.
                    </p>
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; min-width: 800px; font-size: 13px;">
                    <thead>
                        <tr style="border-bottom: 2px solid #e2e8f0; text-align: left; color: #475569;">
                            <th style="padding: 10px 12px;">Học Sinh</th>
                            <th style="padding: 10px 12px;">Lớp</th>
                            <th style="padding: 10px 12px;">Môn</th>
                            <th style="padding: 10px 12px; text-align: center;">Điểm Hiện Tại</th>
                            <th style="padding: 10px 12px;">Mức Độ Rủi Ro</th>
                            <th style="padding: 10px 12px;">Biện Pháp Đang Áp Dụng</th>
                            <th style="padding: 10px 12px;">Trạng Thái Hỗ Trợ</th>
                            <th style="padding: 10px 12px; text-align: center;">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($atRiskStudents)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 32px; color: #16a34a; font-weight: 700;">
                                    🎉 Chúc mừng! Toàn trường hiện không có học sinh nào nằm trong vùng nguy cấp hoặc cần can thiệp.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($atRiskStudents as $st): 
                                $riskBadge = $st['risk_zone'] === 'red' 
                                    ? '<span style="background: #fef2f2; color: #dc2626; border: 1px solid #fca5a5; padding: 2px 8px; border-radius: 6px; font-weight: 800;">🔴 Nguy cấp</span>'
                                    : '<span style="background: #fff7ed; color: #ea580c; border: 1px solid #fed7aa; padding: 2px 8px; border-radius: 6px; font-weight: 800;">🟠 Cần chú ý</span>';
                                
                                $statusLabel = match($st['latest_status']) {
                                    'improved' => '<span style="color: #16a34a; font-weight: 700;">🟢 Có chuyển biến tốt</span>',
                                    'achieved' => '<span style="color: #2563eb; font-weight: 700;">✨ Đã đạt mục tiêu</span>',
                                    default => '<span style="color: #d97706; font-weight: 700;">⏳ Đang hỗ trợ</span>'
                                };
                            ?>
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 12px; font-weight: 800; color: #0f172a;">
                                        <?= htmlspecialchars($st['full_name']) ?>
                                        <div style="font-size: 11px; color: #64748b; font-weight: 500;"><?= htmlspecialchars($st['student_code']) ?></div>
                                    </td>
                                    <td style="padding: 12px; font-weight: 700; color: #334155;"><?= htmlspecialchars($st['class_name']) ?></td>
                                    <td style="padding: 12px; font-weight: 700; color: #4338ca;"><?= htmlspecialchars($st['subject']) ?></td>
                                    <td style="padding: 12px; text-align: center;">
                                        <span style="font-weight: 800; font-size: 14px; color: <?= (float)($st['avg_score'] ?? 0) < 5.0 ? '#dc2626' : '#ea580c' ?>;">
                                            <?= $st['avg_score'] !== null ? number_format((float)$st['avg_score'], 1) : '-' ?> đ
                                        </span>
                                        <div style="font-size: 10px; color: #64748b;">CK: <?= $st['ck'] !== null ? number_format((float)$st['ck'], 1) : '-' ?></div>
                                    </td>
                                    <td style="padding: 12px;"><?= $riskBadge ?></td>
                                    <td style="padding: 12px; color: #1e293b; font-weight: 600;">
                                        <?= htmlspecialchars($st['latest_action'] ?: 'Chưa lập kế hoạch') ?>
                                        <?php if (!empty($st['latest_notes'])): ?>
                                            <div style="font-size: 11px; color: #64748b; font-style: italic;">"<?= htmlspecialchars($st['latest_notes']) ?>"</div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 12px;"><?= $statusLabel ?></td>
                                    <td style="padding: 12px; text-align: center;">
                                        <button type="button" onclick="openInterventionModal(<?= $st['student_id'] ?>, '<?= htmlspecialchars($st['full_name']) ?>', '<?= htmlspecialchars($st['subject']) ?>', '<?= $st['risk_zone'] ?>', '<?= htmlspecialchars($st['latest_action'] ?? '') ?>', '<?= htmlspecialchars($st['latest_status'] ?? 'in_progress') ?>', '<?= htmlspecialchars($st['latest_notes'] ?? '') ?>')" style="background: #f1f5f9; hover:background:#e2e8f0; border: 1px solid #cbd5e1; color: #1e293b; font-size: 12px; font-weight: 700; padding: 5px 12px; border-radius: 8px; cursor: pointer;">
                                            ✏️ Ghi Nhận
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 3: NHẬT KÝ LỊCH SỬ CHỈNH SỬA ĐIỂM SỐ (AUDIT TRAIL LOGS) -->
    <div id="tabAuditLogs" class="heatmap-tab-pane" style="display: none;">
        <div style="background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 20px; box-shadow: 0 4px 16px rgba(0,0,0,0.03);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
                <div>
                    <h3 style="margin: 0 0 4px 0; font-size: 16px; font-weight: 800; color: #0f172a;">
                        Lịch Sử Chỉnh Sửa Điểm Số (Audit Trail)
                    </h3>
                    <p style="margin: 0; font-size: 13px; color: #64748b;">
                        Hệ thống tự động ghi vết mỗi khi có thao tác nhập hoặc điều chỉnh điểm số. Đảm bảo tính minh bạch và toàn vẹn dữ liệu.
                    </p>
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; min-width: 800px; font-size: 13px;">
                    <thead>
                        <tr style="border-bottom: 2px solid #e2e8f0; text-align: left; color: #475569;">
                            <th style="padding: 10px 12px;">Thời Gian</th>
                            <th style="padding: 10px 12px;">Học Sinh</th>
                            <th style="padding: 10px 12px;">Lớp</th>
                            <th style="padding: 10px 12px;">Môn</th>
                            <th style="padding: 10px 12px;">Cột Điểm</th>
                            <th style="padding: 10px 12px; text-align: center;">Thay Đổi</th>
                            <th style="padding: 10px 12px;">Người Thực Hiện</th>
                            <th style="padding: 10px 12px;">Ghi Chú</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentAuditLogs)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 32px; color: #64748b;">
                                    Chưa có thao tác chỉnh sửa điểm nào được ghi nhận trong phiên làm việc.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentAuditLogs as $log): 
                                $oldV = $log['old_value'] !== null ? number_format((float)$log['old_value'], 1) : '-';
                                $newV = $log['new_value'] !== null ? number_format((float)$log['new_value'], 1) : '-';
                                $timeStr = date('d/m/Y H:i', strtotime($log['created_at']));
                                $fLabel = strtoupper($log['score_field']);
                            ?>
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 10px 12px; color: #64748b; font-weight: 600; white-space: nowrap;"><?= $timeStr ?></td>
                                    <td style="padding: 10px 12px; font-weight: 800; color: #0f172a;"><?= htmlspecialchars($log['student_name']) ?></td>
                                    <td style="padding: 10px 12px; font-weight: 600; color: #334155;"><?= htmlspecialchars($log['class_name'] ?? 'N/A') ?></td>
                                    <td style="padding: 10px 12px; font-weight: 700; color: #4338ca;"><?= htmlspecialchars($log['subject']) ?></td>
                                    <td style="padding: 10px 12px;"><span style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px; font-weight: 700; font-size: 11px;"><?= $fLabel ?></span></td>
                                    <td style="padding: 10px 12px; text-align: center; font-weight: 800;">
                                        <span style="color: #64748b;"><?= $oldV ?></span>
                                        <span style="color: #4f46e5; margin: 0 4px;">→</span>
                                        <span style="color: #059669; font-size: 14px;"><?= $newV ?></span>
                                    </td>
                                    <td style="padding: 10px 12px; font-weight: 700; color: #1e293b;"><?= htmlspecialchars($log['changed_by_name'] ?: 'Giáo viên') ?></td>
                                    <td style="padding: 10px 12px; color: #64748b; font-size: 12px;"><?= htmlspecialchars($log['note'] ?: '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL GHI NHẬN BIỆN PHÁP CAN THIỆP SƯ PHẠM -->
<div id="modalIntervention" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.5); z-index: 2000; align-items: center; justify-content: center; padding: 16px;">
    <div style="background: #ffffff; border-radius: 18px; max-width: 500px; width: 100%; padding: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); animation: fadeIn 0.2s ease;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px;">
            <h3 style="margin: 0; font-size: 17px; font-weight: 800; color: #0f172a;" id="intervStudentTitle">
                Ghi Nhận Biện Pháp Can Thiệp
            </h3>
            <button type="button" onclick="closeInterventionModal()" style="background: none; border: none; font-size: 20px; color: #64748b; cursor: pointer;">&times;</button>
        </div>

        <form id="formSaveIntervention" onsubmit="submitIntervention(event)">
            <input type="hidden" name="action" value="save_intervention">
            <input type="hidden" name="student_id" id="inpIntervStudentId">
            <input type="hidden" name="subject" id="inpIntervSubject">
            <input type="hidden" name="risk_level" id="inpIntervRisk">

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">Biện pháp hỗ trợ chính:</label>
                <select name="action_type" id="inpIntervActionType" style="width: 100%; padding: 10px 12px; border-radius: 10px; border: 1.5px solid #cbd5e1; font-size: 13px; font-weight: 600;">
                    <option value="Phụ đạo kèm cặp ngoài giờ">Phụ đạo kèm cặp ngoài giờ (1 buổi / tuần)</option>
                    <option value="Đổi chỗ ngồi gần bàn giáo viên">Đổi chỗ ngồi gần bàn giáo viên để giám sát</option>
                    <option value="Trao đổi riêng với phụ huynh">Trao đổi riêng với phụ huynh về tinh thần học</option>
                    <option value="Giao bài tập bổ trợ mức cơ bản">Giao bài tập bổ trợ mức cơ bản (lấy lại căn bản)</option>
                    <option value="Kiểm tra củng cố lại kiến thức hổng">Kiểm tra củng cố lại bài học đã bị điểm thấp</option>
                </select>
            </div>

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">Trạng thái can thiệp:</label>
                <select name="status" id="inpIntervStatus" style="width: 100%; padding: 10px 12px; border-radius: 10px; border: 1.5px solid #cbd5e1; font-size: 13px; font-weight: 600;">
                    <option value="in_progress">⏳ Đang hỗ trợ (Chưa có bài kiểm tra mới)</option>
                    <option value="improved">🟢 Có chuyển biến tích cực (Điểm bài sau tăng)</option>
                    <option value="achieved">✨ Đã đạt mục tiêu (Đã vượt mức an toàn &ge; 5.0)</option>
                </select>
            </div>

            <div style="margin-bottom: 18px;">
                <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">Ghi chú sư phạm của giáo viên:</label>
                <textarea name="teacher_notes" id="inpIntervNotes" rows="3" placeholder="Nhập nhận xét cụ thể: thái độ học tập, sự tiến bộ sau khi kèm cặp..." style="width: 100%; padding: 10px 12px; border-radius: 10px; border: 1.5px solid #cbd5e1; font-size: 13px; font-family: inherit; resize: vertical; box-sizing: border-box;"></textarea>
            </div>

            <div style="display: flex; align-items: center; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="closeInterventionModal()" style="padding: 8px 16px; border-radius: 10px; border: 1px solid #cbd5e1; background: #ffffff; color: #475569; font-weight: 700; cursor: pointer;">
                    Hủy
                </button>
                <button type="submit" id="btnSubmitInterv" style="padding: 8px 20px; border-radius: 10px; border: none; background: #4f46e5; color: #ffffff; font-weight: 700; cursor: pointer; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);">
                    Lưu Hồ Sơ
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function switchHeatmapTab(tabId) {
    document.querySelectorAll('.heatmap-tab-pane').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.style.background = 'transparent';
        btn.style.color = '#475569';
        btn.style.borderColor = '#cbd5e1';
    });
    const pane = document.getElementById(tabId);
    if (pane) pane.style.display = 'block';

    const btnMap = {
        'tabHeatmapGrid': 'btnTabHeatmapGrid',
        'tabInterventions': 'btnTabInterventions',
        'tabAuditLogs': 'btnTabAuditLogs'
    };
    const activeBtn = document.getElementById(btnMap[tabId]);
    if (activeBtn) {
        activeBtn.style.background = '#4f46e5';
        activeBtn.style.color = '#ffffff';
        activeBtn.style.borderColor = '#4f46e5';
    }
}

function openInterventionModal(stId, name, subject, risk, action, status, notes) {
    document.getElementById('inpIntervStudentId').value = stId;
    document.getElementById('inpIntervSubject').value = subject;
    document.getElementById('inpIntervRisk').value = risk;
    document.getElementById('intervStudentTitle').textContent = `Can Thiệp: ${name} (Môn ${subject})`;
    if (action) document.getElementById('inpIntervActionType').value = action;
    if (status) document.getElementById('inpIntervStatus').value = status;
    document.getElementById('inpIntervNotes').value = notes || '';
    const modal = document.getElementById('modalIntervention');
    modal.style.display = 'flex';
}

function closeInterventionModal() {
    document.getElementById('modalIntervention').style.display = 'none';
}

function submitIntervention(e) {
    e.preventDefault();
    const form = document.getElementById('formSaveIntervention');
    const formData = new FormData(form);
    const btn = document.getElementById('btnSubmitInterv');
    btn.disabled = true;
    btn.textContent = 'Đang lưu...';

    fetch('ban_do_nhiet.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.textContent = 'Lưu Hồ Sơ';
        if (data.success) {
            alert('Đã cập nhật hồ sơ can thiệp thành công!');
            closeInterventionModal();
            location.reload();
        } else {
            alert('Lỗi: ' + (data.error || 'Không thể lưu hồ sơ.'));
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.textContent = 'Lưu Hồ Sơ';
        alert('Lỗi kết nối máy chủ: ' + err.message);
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
