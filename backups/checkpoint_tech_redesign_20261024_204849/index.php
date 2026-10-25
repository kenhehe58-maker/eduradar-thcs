<?php
/**
 * =============================================================================
 * Hệ Thống Quản Lý Điểm & Cảnh Báo Sớm THCS (EduRadar)
 * Trang Tổng Quan Radar Cảnh Báo Sớm Học Sinh
 * 
 * KIẾN TRÚC 2 KHUNG ĐÁNH GIÁ (DUAL-FRAMEWORK ASSESSMENT):
 * 1. KHUNG HIỆU SUẤT (Performance Momentum) — [ƯU TIÊN CAO HƠN & TRÌNH BÀY ĐỨNG TRƯỚC]:
 *    - Tăng (🚀 Bứt phá / 📈 Tiến bộ)
 *    - Đều (🔄 Duy trì đều): Học sinh học lực mức thấp hoặc trung bình mà đang đi đều
 *      thì ghi nhận là "Duy trì ổn định, cần tiếp sức bứt phá" vì điểm còn trong khuôn khổ chưa cao.
 *    - Giảm (📉 Sa sút / ⚠️ Tuột dốc)
 * 2. KHUNG ĐIỂM SỐ (Score Tier / Mastery Bracket):
 *    - Xuất sắc (⭐ 8.0+) | Khá (✨ 6.5-7.9) | Trung bình (⚖️ 5.0-6.4) | Thấp (⚠️ < 5.0)
 * 3. NĂNG LỰC TỔNG THỂ & KHUYẾN NGHỊ SƯ PHẠM HÀNH ĐỘNG
 * =============================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/radar_helper.php';

// Kiểm tra quyền đăng nhập
require_login();

$pdo = db();
$userId = (int)$_SESSION['user_id'];
$userRole = $_SESSION['role'];
$userNavCtx = get_user_nav_context($userId);

// -----------------------------------------------------------------------------
// AJAX ENDPOINT: LƯU GHI CHÚ / NHẬN XÉT SƯ PHẠM CỦA GIÁO VIÊN
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_teacher_note') {
    header('Content-Type: application/json; charset=utf-8');

    $studentId = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
    $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
    $teacherNote = isset($_POST['note']) ? trim($_POST['note']) : '';

    if ($studentId <= 0 || empty($subject)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Tham số học sinh hoặc môn học không hợp lệ.']);
        exit;
    }

    // Xác thực học sinh tồn tại
    $stInfo = $pdo->prepare("SELECT s.id, s.full_name, s.class_id, c.name AS class_name FROM students s JOIN classes c ON s.class_id = c.id WHERE s.id = ?");
    $stInfo->execute([$studentId]);
    $st = $stInfo->fetch();
    if (!$st) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Không tìm thấy hồ sơ học sinh.']);
        exit;
    }

    // Phân quyền: nếu là giáo viên, phải được phân công dạy môn này tại lớp này
    if ($userRole === 'teacher') {
        $chkAssign = $pdo->prepare("SELECT id FROM teacher_classes WHERE teacher_id = ? AND class_id = ? AND subject = ?");
        $chkAssign->execute([$userId, $st['class_id'], $subject]);
        if (!$chkAssign->fetch()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => "Bạn không có quyền nhận xét môn [{$subject}] tại lớp [{$st['class_name']}]."]);
            exit;
        }
    }

    try {
        $upsertNote = $pdo->prepare("
            INSERT INTO scores (student_id, subject, teacher_note, updated_at)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                teacher_note = VALUES(teacher_note),
                updated_at = CURRENT_TIMESTAMP
        ");
        $upsertNote->execute([$studentId, $subject, $teacherNote]);

        log_action(
            $userId,
            $_SESSION['username'] ?? 'gv',
            'Nhận xét sư phạm',
            "Ghi chú sư phạm cho học sinh [{$st['full_name']}] (Lớp {$st['class_name']}, Môn {$subject}): " . mb_substr($teacherNote, 0, 100)
        );

        echo json_encode([
            'success'     => true,
            'message'     => 'Đã lưu nhận xét sư phạm thành công!',
            'student_id'  => $studentId,
            'subject'     => $subject,
            'note'        => $teacherNote,
            'updated_at'  => date('d/m/Y H:i')
        ]);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Lỗi máy chủ khi lưu nhận xét: ' . $e->getMessage()]);
    }
    exit;
}

// -----------------------------------------------------------------------------
// XỬ LÝ PHÂN QUYỀN VÀ TRUY VẤN DỮ LIỆU SỔ ĐIỂM
// -----------------------------------------------------------------------------

$hasGetClass = isset($_GET['class_id']) && is_numeric($_GET['class_id']);
$rawGetClass = $hasGetClass ? (int)$_GET['class_id'] : null;

$hasGetSubject = isset($_GET['subject']) && trim((string)$_GET['subject']) !== '';
$rawGetSubject = $hasGetSubject ? trim((string)$_GET['subject']) : null;

$filterClasses = [];
$filterSubjects = [];
$classCounts = [];
$classGrades = [];

if ($userRole === 'teacher') {
    // Giáo viên: CHỈ TRUY VẤN CÁC LỚP VÀ PHÂN MÔN ĐƯỢC PHÂN CÔNG
    $assignStmt = $pdo->prepare("
        SELECT DISTINCT tc.class_id, tc.subject, c.name AS class_name, c.grade,
               (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id) as st_count
        FROM teacher_classes tc
        JOIN classes c ON tc.class_id = c.id
        WHERE tc.teacher_id = ?
        ORDER BY c.grade ASC, c.name ASC, tc.subject ASC
    ");
    $assignStmt->execute([$userId]);
    $assignments = $assignStmt->fetchAll();

    $teacherClassSubjectMap = [];
    foreach ($assignments as $a) {
        $cid = (int)$a['class_id'];
        $filterClasses[$cid] = $a['class_name'];
        $classCounts[$cid] = (int)$a['st_count'];
        $classGrades[$cid] = (int)$a['grade'];
        if (!in_array($a['subject'], $filterSubjects, true)) {
            $filterSubjects[] = $a['subject'];
        }
        if (!isset($teacherClassSubjectMap[$cid])) {
            $teacherClassSubjectMap[$cid] = [
                'name' => $a['class_name'],
                'grade' => (int)$a['grade'],
                'st_count' => (int)$a['st_count'],
                'subjects' => []
            ];
        }
        if (!in_array($a['subject'], $teacherClassSubjectMap[$cid]['subjects'], true)) {
            $teacherClassSubjectMap[$cid]['subjects'][] = $a['subject'];
        }
    }

    // 1. Xác định lớp học được chọn:
    if ($hasGetClass && isset($filterClasses[$rawGetClass])) {
        $selectedClassId = $rawGetClass;
    } elseif ($userNavCtx['class_id'] > 0 && isset($filterClasses[$userNavCtx['class_id']])) {
        $selectedClassId = $userNavCtx['class_id'];
    } else {
        // Tự động chọn lớp đầu tiên có học sinh
        $selectedClassId = 0;
        foreach ($filterClasses as $cid => $cName) {
            if (($classCounts[$cid] ?? 0) > 0) {
                $selectedClassId = $cid;
                break;
            }
        }
        if ($selectedClassId === 0 && !empty($filterClasses)) {
            $selectedClassId = (int)array_key_first($filterClasses);
        }
    }
    if ($selectedClassId > 0 && !isset($filterClasses[$selectedClassId])) {
        $selectedClassId = 0;
        foreach ($filterClasses as $cid => $cName) {
            if (($classCounts[$cid] ?? 0) > 0) {
                $selectedClassId = $cid;
                break;
            }
        }
        if ($selectedClassId === 0 && !empty($filterClasses)) {
            $selectedClassId = (int)array_key_first($filterClasses);
        }
    }

    // 2. Xác định môn học theo lớp đã chọn (Giáo viên chỉ được xem môn mình dạy ở lớp đó)
    $validSubjectsForSelectedClass = ($selectedClassId > 0 && isset($teacherClassSubjectMap[$selectedClassId])) 
        ? $teacherClassSubjectMap[$selectedClassId]['subjects'] 
        : $filterSubjects;

    if ($hasGetSubject && in_array($rawGetSubject, $validSubjectsForSelectedClass, true)) {
        $selectedSubject = $rawGetSubject;
    } elseif ($userNavCtx['subject'] !== '' && in_array($userNavCtx['subject'], $validSubjectsForSelectedClass, true)) {
        $selectedSubject = $userNavCtx['subject'];
    } else {
        $selectedSubject = !empty($validSubjectsForSelectedClass) ? $validSubjectsForSelectedClass[0] : 'Toán';
    }

    // Ghi nhớ lớp & môn vào không gian tên riêng của tài khoản
    set_user_nav_context($userId, $selectedClassId, $selectedSubject);

    $whereClauses = ["tc.teacher_id = ?"];
    $queryParams = [$userId];

    if ($selectedClassId > 0) {
        $whereClauses[] = "tc.class_id = ?";
        $queryParams[] = $selectedClassId;
    }
    if ($selectedSubject !== 'all') {
        $whereClauses[] = "tc.subject = ?";
        $queryParams[] = $selectedSubject;
    }

    $whereSql = "WHERE " . implode(" AND ", $whereClauses);
    $sql = "
        SELECT 
            s.id AS student_id,
            s.class_id,
            s.student_code,
            s.full_name,
            s.birthday,
            s.phone,
            c.name AS class_name,
            tc.subject,
            sc.tx1, sc.tx2, sc.tx3, sc.tx4, sc.gk, sc.ck, sc.avg_score, sc.teacher_note
        FROM teacher_classes tc
        JOIN classes c ON tc.class_id = c.id
        JOIN students s ON s.class_id = c.id
        LEFT JOIN scores sc ON sc.student_id = s.id AND sc.subject = tc.subject
        $whereSql
        ORDER BY c.grade ASC, c.name ASC, s.full_name ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParams);
} else {
    // Admin: TOÀN QUYỀN TRUY VẤN MỌI LỚP VÀ PHÂN MÔN TRONG TOÀN TRƯỜNG
    $classesList = $pdo->query("
        SELECT c.id, c.name, c.grade,
               (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id) as st_count
        FROM classes c 
        ORDER BY c.grade ASC, c.name ASC
    ")->fetchAll();
    foreach ($classesList as $c) {
        $filterClasses[$c['id']] = $c['name'];
        $classCounts[$c['id']] = (int)$c['st_count'];
        $classGrades[$c['id']] = (int)$c['grade'];
    }

    $subjectsList = $pdo->query("SELECT DISTINCT subject FROM teacher_classes ORDER BY subject ASC")->fetchAll();
    $filterSubjects = array_column($subjectsList, 'subject');
    if (empty($filterSubjects)) {
        $filterSubjects = ['Toán', 'Ngữ Văn', 'Tiếng Anh', 'Khoa Học Tự Nhiên', 'Lịch Sử & Địa Lí', 'Tin Học'];
    }

    // 1. Xác định môn học được chọn:
    if ($hasGetSubject) {
        $selectedSubject = $rawGetSubject;
    } elseif ($userNavCtx['subject'] !== '' && (in_array($userNavCtx['subject'], $filterSubjects, true) || $userNavCtx['subject'] === 'all')) {
        $selectedSubject = $userNavCtx['subject'];
    } else {
        $selectedSubject = 'all';
    }
    if ($selectedSubject !== 'all' && !in_array($selectedSubject, $filterSubjects, true)) {
        $selectedSubject = 'all';
    }

    // 2. Xác định lớp học được chọn:
    if ($hasGetClass) {
        $selectedClassId = $rawGetClass;
    } elseif ($userNavCtx['class_id'] > 0 && isset($filterClasses[$userNavCtx['class_id']])) {
        $selectedClassId = $userNavCtx['class_id'];
    } else {
        // Tự động chọn lớp đầu tiên có học sinh
        $selectedClassId = 0;
        foreach ($filterClasses as $cid => $cName) {
            if (($classCounts[$cid] ?? 0) > 0) {
                $selectedClassId = $cid;
                break;
            }
        }
        if ($selectedClassId === 0 && !empty($filterClasses)) {
            $selectedClassId = (int)array_key_first($filterClasses);
        }
    }
    if ($selectedClassId > 0 && !isset($filterClasses[$selectedClassId])) {
        $selectedClassId = 0;
    }

    // Ghi nhớ lớp & môn vào không gian tên riêng của tài khoản
    set_user_nav_context($userId, $selectedClassId, $selectedSubject);

    $whereClauses = [];
    $queryParams = [];

    if ($selectedClassId > 0) {
        $whereClauses[] = "tc.class_id = ?";
        $queryParams[] = $selectedClassId;
    }
    if ($selectedSubject !== 'all') {
        $whereClauses[] = "tc.subject = ?";
        $queryParams[] = $selectedSubject;
    }

    $whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";
    $sql = "
        SELECT 
            s.id AS student_id,
            s.class_id,
            s.student_code,
            s.full_name,
            s.birthday,
            s.phone,
            c.name AS class_name,
            tc.subject,
            sc.tx1, sc.tx2, sc.tx3, sc.tx4, sc.gk, sc.ck, sc.avg_score, sc.teacher_note
        FROM teacher_classes tc
        JOIN classes c ON tc.class_id = c.id
        JOIN students s ON s.class_id = c.id
        LEFT JOIN scores sc ON sc.student_id = s.id AND sc.subject = tc.subject
        $whereSql
        ORDER BY c.grade ASC, c.name ASC, s.full_name ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParams);
}

$rows = $stmt->fetchAll();

// Danh sách lớp có học sinh thực tế (lọc bỏ lớp trống 0 HS)
$filterClassesWithStudents = [];
foreach ($filterClasses as $cid => $cname) {
    if (($classCounts[$cid] ?? 0) > 0) {
        $filterClassesWithStudents[$cid] = $cname;
    }
}
if (empty($filterClassesWithStudents)) {
    $filterClassesWithStudents = $filterClasses;
}

// Đánh giá 2 khung: Khung Hiệu Suất & Khung Điểm Số
$students = [];
foreach ($rows as $r) {
    $avg = ($r['avg_score'] !== null && $r['avg_score'] !== '') ? (float)$r['avg_score'] : null;
    $gk  = ($r['gk'] !== null && $r['gk'] !== '') ? (float)$r['gk'] : null;
    $ck  = ($r['ck'] !== null && $r['ck'] !== '') ? (float)$r['ck'] : null;

    $txList = [];
    foreach (['tx1', 'tx2', 'tx3', 'tx4'] as $col) {
        if ($r[$col] !== null && $r[$col] !== '') {
            $txList[] = (float)$r[$col];
        }
    }

    $evaluation = evaluateRiskZone($avg, $gk, $ck, $txList);
    $students[] = array_merge($r, $evaluation, ['tx_list' => $txList]);
}

$stats = calculateRadarStats($students);
sortStudentsByRisk($students);

// TÍNH TOÁN HỌC LỰC CHUNG TOÀN LỚP THEO THÔNG TƯ 22 (MULTI-SUBJECT OVERALL PERFORMANCE)
$classOverallSummary = [
    'total' => 0,
    'excellent' => 0, // Tốt
    'good' => 0,      // Khá
    'average' => 0,   // Đạt
    'low' => 0,       // Chưa Đạt
    'pending' => 0    // Chờ điểm
];
$studentOverallMap = [];

if ($selectedClassId > 0) {
    $allClassScoresStmt = $pdo->prepare("
        SELECT s.id as student_id, s.full_name, sc.subject, sc.avg_score 
        FROM students s
        LEFT JOIN scores sc ON s.id = sc.student_id
        WHERE s.class_id = ?
    ");
    $allClassScoresStmt->execute([$selectedClassId]);
    $studentScoresGrouped = [];
    while ($row = $allClassScoresStmt->fetch(PDO::FETCH_ASSOC)) {
        $sid = (int)$row['student_id'];
        if (!isset($studentScoresGrouped[$sid])) {
            $studentScoresGrouped[$sid] = [
                'full_name' => $row['full_name'],
                'scores' => []
            ];
        }
        if ($row['subject'] !== null && $row['avg_score'] !== null && $row['avg_score'] !== '') {
            $studentScoresGrouped[$sid]['scores'][$row['subject']] = (float)$row['avg_score'];
        }
    }

    $classOverallSummary['total'] = count($studentScoresGrouped);
    foreach ($studentScoresGrouped as $sid => $data) {
        $subCount = count($data['scores']);
        if ($subCount === 0) {
            $classOverallSummary['pending']++;
            $studentOverallMap[$sid] = ['level' => 'pending', 'label' => 'Chờ điểm', 'badge' => '⏳ Chờ điểm', 'class' => 'tier-pending'];
            continue;
        }

        $scoresList = array_values($data['scores']);
        $minScore = min($scoresList);
        $avgAll = array_sum($scoresList) / $subCount;
        $count8 = count(array_filter($scoresList, fn($v) => $v >= 8.0));
        $count65 = count(array_filter($scoresList, fn($v) => $v >= 6.5));

        // Chuẩn đánh giá Thông tư 22
        if ($minScore >= 6.5 && ($count8 >= 1 || $avgAll >= 8.0)) {
            $classOverallSummary['excellent']++;
            $studentOverallMap[$sid] = ['level' => 'excellent', 'label' => 'Tốt', 'badge' => '🌟 Tốt', 'class' => 'tier-excellent'];
        } elseif ($minScore >= 5.0 && ($count65 >= 1 || $avgAll >= 6.5)) {
            $classOverallSummary['good']++;
            $studentOverallMap[$sid] = ['level' => 'good', 'label' => 'Khá', 'badge' => '🟢 Khá', 'class' => 'tier-good'];
        } elseif ($minScore >= 3.5 || $avgAll >= 5.0) {
            $classOverallSummary['average']++;
            $studentOverallMap[$sid] = ['level' => 'average', 'label' => 'Đạt', 'badge' => '🟡 Đạt', 'class' => 'tier-average'];
        } else {
            $classOverallSummary['low']++;
            $studentOverallMap[$sid] = ['level' => 'low', 'label' => 'Chưa Đạt', 'badge' => '🔴 Chưa Đạt', 'class' => 'tier-low'];
        }
    }
}

$activeNav = 'dashboard';
$pageTitle = 'Radar Cảnh Báo Sớm & Năng Lực Học Tập';
require_once __DIR__ . '/includes/header.php';
?>

<!-- KHỐI TIÊU ĐỀ DASHBOARD -->
<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; gap: 16px; flex-wrap: wrap;">
    <div>
        <h1 style="font-size: 24px; font-weight: 800; color: var(--text-heading); letter-spacing: -0.5px; margin-bottom: 6px;">
            📡 Radar Đánh Giá 2 Khung: Hiệu Suất & Học Lực
        </h1>
        <p class="muted small" style="margin: 0;">
            <b>Khung Hiệu Suất</b> (Tăng · Đều · Giảm) kết hợp <b>Khung Điểm Số</b> theo chuẩn Thông tư 22/2021/TT-BGDĐT
        </p>
    </div>
</div>

<!-- KHỐI ĐIỀU KHIỂN MA TRẬN LỚP HỌC & PHÂN MÔN (CLASS & SUBJECT MATRIX CONTROL BAR) -->
<div class="class-subject-matrix-bar">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <?php if ($userRole === 'admin'): ?>
            <div class="matrix-role-pill admin">
                <span>👑</span>
                <span>Quản Trị Viên Trường &bull; Toàn Quyền Lớp & Phân Môn Toàn Trường (<?= count($filterClasses) ?> Lớp &bull; <?= count($filterSubjects) ?> Môn)</span>
            </div>
        <?php else: ?>
            <div class="matrix-role-pill teacher">
                <span>👨‍🏫</span>
                <span>Phân Công Giáo Viên: <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']) ?> &bull; Giới Hạn Theo Lớp & Môn Phụ Trách (<?= count($filterClasses) ?> Lớp &bull; <?= count($filterSubjects) ?> Môn)</span>
            </div>
        <?php endif; ?>

        <div class="muted small">
            Đang hiển thị: <b><?= count($students) ?></b> hồ sơ học sinh
            <?= $selectedClassId > 0 ? " &bull; Lớp <b>" . htmlspecialchars($filterClasses[$selectedClassId] ?? '') . "</b>" : "" ?>
            <?= $selectedSubject !== 'all' ? " &bull; Môn <b>" . htmlspecialchars($selectedSubject) . "</b>" : "" ?>
        </div>
    </div>

    <form method="GET" action="index.php" id="matrixFilterForm">
        <div class="matrix-form-row">
            <!-- 1. MỤC CHỌN LỚP HỌC -->
            <div class="matrix-field-group">
                <label class="matrix-field-label" for="selectClassId">🏫 Chọn Lớp Học</label>
                <select name="class_id" id="selectClassId" class="matrix-select" onchange="this.form.submit()">
                    <?php if ($userRole === 'admin'): ?>
                        <option value="0" <?= $selectedClassId === 0 ? 'selected' : '' ?>>
                            🏢 Tất cả các lớp (<?= count($filterClasses) ?> lớp - <?= array_sum($classCounts) ?> HS)
                        </option>
                        <?php foreach ($filterClasses as $cId => $cName): ?>
                            <option value="<?= $cId ?>" <?= $selectedClassId === $cId ? 'selected' : '' ?>>
                                Lớp <?= htmlspecialchars($cName) ?> (<?= $classCounts[$cId] ?? 0 ?> HS)
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php foreach ($filterClasses as $cId => $cName): ?>
                            <?php $assignedSubs = $teacherClassSubjectMap[$cId]['subjects'] ?? []; ?>
                            <option value="<?= $cId ?>" <?= $selectedClassId === $cId ? 'selected' : '' ?>>
                                Lớp <?= htmlspecialchars($cName) ?> [Môn: <?= implode(', ', $assignedSubs) ?>] (<?= $classCounts[$cId] ?? 0 ?> HS)
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <!-- 2. MỤC CHỌN PHÂN MÔN -->
            <div class="matrix-field-group">
                <label class="matrix-field-label" for="selectSubject">📚 Phân Môn Phụ Trách</label>
                <?php if ($userRole === 'admin'): ?>
                    <select name="subject" id="selectSubject" class="matrix-select" onchange="this.form.submit()">
                        <option value="all" <?= $selectedSubject === 'all' ? 'selected' : '' ?>>
                            📖 Tất cả môn học (<?= count($filterSubjects) ?> môn)
                        </option>
                        <?php foreach ($filterSubjects as $subj): ?>
                            <option value="<?= htmlspecialchars($subj) ?>" <?= $selectedSubject === $subj ? 'selected' : '' ?>>
                                Môn <?= htmlspecialchars($subj) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <?php 
                        $curClassSubs = ($selectedClassId > 0 && isset($teacherClassSubjectMap[$selectedClassId]))
                            ? $teacherClassSubjectMap[$selectedClassId]['subjects']
                            : $filterSubjects;
                    ?>
                    <select name="subject" id="selectSubject" class="matrix-select" <?= count($curClassSubs) <= 1 ? 'disabled style="background: #f1f5f9; cursor: not-allowed; color: #1e293b; font-weight: 700;"' : 'onchange="this.form.submit()"' ?>>
                        <?php foreach ($curClassSubs as $subj): ?>
                            <option value="<?= htmlspecialchars($subj) ?>" <?= $selectedSubject === $subj ? 'selected' : '' ?>>
                                Môn <?= htmlspecialchars($subj) ?> (Được phân công)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (count($curClassSubs) <= 1): ?>
                        <input type="hidden" name="subject" value="<?= htmlspecialchars($selectedSubject) ?>">
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- 3. NÚT LIQUID GLASS (PROMPT #001) CHO LỌC DỮ LIỆU -->
            <div style="display: flex; align-items: center; gap: 10px;">
                <!-- Lucian UX/UI Prompt #001 · MIT License (c) 2026 Seokwoooo · https://lucianlabs.dev/uxui-prompt/#001 -->
                <div class="ux u001" id="u001FilterBtnWrapper" data-state="idle">
                    <div class="u001-blobs" aria-hidden="true"><i></i><i></i><i></i></div>
                    <div class="u001-mag">
                        <div class="u001-float">
                            <button class="u001-btn btn-compact" type="submit" id="btnFilterApply">
                                <span class="u001-sheen" aria-hidden="true"></span>
                                <span class="u001-txt">🔍 Áp Dụng Lọc</span>
                                <span class="u001-spin" aria-hidden="true"><svg viewBox="0 0 30 30"><circle cx="15" cy="15" r="10"/></svg></span>
                                <span class="u001-done" aria-hidden="true"><b><svg viewBox="0 0 16 16"><path d="M3.5 8.4l3 3 6-6.4"/></svg></b>Xong</span>
                            </button>
                        </div>
                    </div>
                    <span class="u001-live" aria-live="polite"></span>
                </div>

                <?php if ($selectedClassId > 0 || $selectedSubject !== 'all'): ?>
                    <a href="index.php" style="font-size: 13px; color: var(--primary); text-decoration: none; font-weight: 700; padding: 6px 10px; border-radius: 6px; background: #eef2ff;">
                        ✕ Bỏ Lọc
                    </a>
                <?php endif; ?>
            </div>

            <!-- 4. XUẤT BÁO CÁO BGH NỘP TRƯỜNG -->
            <?php 
                $reportClassId = $selectedClassId > 0 ? $selectedClassId : (!empty($filterClasses) ? array_key_first($filterClasses) : 0);
                $reportSubject = $selectedSubject !== 'all' ? $selectedSubject : (!empty($filterSubjects) ? $filterSubjects[0] : 'Toán học');
            ?>
            <?php if ($reportClassId > 0): ?>
                <div style="margin-left: auto; display: flex; gap: 8px; flex-wrap: wrap;">
                    <a href="bao_cao_slide_bgh.php?class_id=<?= $selectedClassId ?>&subject=<?= urlencode($selectedSubject) ?>" target="_blank" class="btn-action-slip" style="padding: 9px 14px; border-radius: 9px; font-weight: 700; font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; background: linear-gradient(135deg, #0f172a, #312e81); color: #ffffff; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.28);" title="Mở Slide thuyết trình báo cáo BGH tương tác (Chart.js)">
                        📊 Slide Báo Cáo BGH
                    </a>
                    <a href="in_bao_cao_bgh.php?class_id=<?= $reportClassId ?>&subject=<?= urlencode($reportSubject) ?>&format=pdf" target="_blank" class="btn-action-slip btn-slip-pdf" style="padding: 9px 14px; border-radius: 9px; font-weight: 700; font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; background: #4f46e5; color: #ffffff; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);" title="Xuất báo cáo động năng nộp BGH (Khổ A4 chuẩn)">
                        🖨️ Báo Cáo BGH (PDF A4)
                    </a>
                    <a href="in_bao_cao_bgh.php?class_id=<?= $reportClassId ?>&subject=<?= urlencode($reportSubject) ?>&format=xlsx" target="_blank" class="btn-action-slip btn-slip-excel" style="padding: 9px 14px; border-radius: 9px; font-weight: 700; font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; background: #10b981; color: #ffffff; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);" title="Xuất file Excel báo cáo động năng nộp BGH">
                        📥 Báo Cáo BGH (Excel)
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- DẢI PHÍM CHỌN NHANH LỚP (GOM THEO KHỐI & CUỘN NGANG TINH TẾ - CHỈ HIỆN LỚP CÓ HỌC SINH) -->
        <?php
            $filterClassesWithStudents = [];
            foreach ($filterClasses as $cid => $cname) {
                if (($classCounts[$cid] ?? 0) > 0) {
                    $filterClassesWithStudents[$cid] = $cname;
                }
            }
        ?>
        <div class="matrix-class-chips-container" style="margin-top: 14px; padding-top: 12px; border-top: 1px dashed var(--border-color);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; flex-wrap: wrap; gap: 8px;">
                <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                    <span style="font-size: 11.5px; font-weight: 800; color: #475569; text-transform: uppercase;">
                        ⚡ Lọc Nhanh Khối:
                    </span>
                    <button type="button" class="btn-grade-pill active" onclick="filterGradeChips('all', this)">
                        Tất cả (<?= count($filterClassesWithStudents) ?> lớp)
                    </button>
                    <?php foreach ([6, 7, 8, 9] as $g): ?>
                        <?php 
                            $gCount = 0;
                            foreach ($filterClassesWithStudents as $cid => $cname) {
                                if (($classGrades[$cid] ?? 0) === $g) $gCount++;
                            }
                            if ($gCount === 0) continue;
                        ?>
                        <button type="button" class="btn-grade-pill" onclick="filterGradeChips(<?= $g ?>, this)">
                            Khối <?= $g ?> (<?= $gCount ?>)
                        </button>
                    <?php endforeach; ?>
                </div>
                <div style="font-size: 11.5px; color: #64748b;">
                    💡 <i>Cuộn ngang để xem toàn bộ danh sách lớp</i>
                </div>
            </div>

            <div class="matrix-class-chips" id="matrixClassChipsScroll">
                <?php if ($userRole === 'admin'): ?>
                    <a href="index.php?class_id=0&subject=<?= urlencode($selectedSubject) ?>" 
                       class="class-chip-btn <?= $selectedClassId === 0 ? 'active' : '' ?>"
                       data-grade="all">
                        🏢 Tất cả lớp <span class="class-chip-badge"><?= array_sum($classCounts) ?></span>
                    </a>
                <?php endif; ?>
                <?php foreach ($filterClassesWithStudents as $cId => $cName): ?>
                    <?php 
                        $cg = $classGrades[$cId] ?? 0;
                        $chipSub = $selectedSubject;
                        if ($userRole === 'teacher' && isset($teacherClassSubjectMap[$cId])) {
                            $cSubs = $teacherClassSubjectMap[$cId]['subjects'];
                            $chipSub = in_array($selectedSubject, $cSubs, true) ? $selectedSubject : ($cSubs[0] ?? $selectedSubject);
                        }
                    ?>
                    <a href="index.php?class_id=<?= $cId ?>&subject=<?= urlencode($chipSub) ?>" 
                       class="class-chip-btn <?= $selectedClassId === $cId ? 'active' : '' ?>"
                       data-grade="<?= $cg ?>"
                       title="Xem học sinh lớp <?= htmlspecialchars($cName) ?>">
                        Lớp <?= htmlspecialchars($cName) ?>
                        <span class="class-chip-badge"><?= $classCounts[$cId] ?? 0 ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </form>
</div>

<?php if ($selectedClassId > 0 && isset($filterClasses[$selectedClassId])): ?>
    <!-- BẢNG ĐÁNH GIÁ HỌC LỰC THEO MÔN & HỌC LỰC CHUNG LỚP (CHUẨN THÔNG TƯ 22) -->
    <div class="card class-evaluation-panel" style="margin-bottom: 24px; padding: 22px 26px; border-radius: 20px; background: #ffffff; border: 1px solid rgba(226, 232, 240, 0.9); box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.05);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 14px; margin-bottom: 18px; border-bottom: 1px solid #f1f5f9; padding-bottom: 14px;">
            <div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 24px;">🏫</span>
                    <div>
                        <h2 style="font-size: 19px; font-weight: 800; color: #0f172a; margin: 0;">
                            Đánh Giá Học Lực Lớp <?= htmlspecialchars($filterClasses[$selectedClassId]) ?>
                        </h2>
                        <span class="muted small">
                            Sĩ số: <b><?= $classCounts[$selectedClassId] ?? count($students) ?></b> học sinh &bull; Niên khóa 2025–2026 &bull; Chuẩn Thông tư 22/2021/TT-BGDĐT
                        </span>
                    </div>
                </div>
            </div>

            <!-- BỘ CHỌN CÁC PHÂN MÔN CỦA LỚP NÀY ĐỂ XEM HỌC LỰC TỪNG MÔN -->
            <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                <span style="font-size: 12px; font-weight: 700; color: #64748b; margin-right: 4px;">📖 Chọn môn xem:</span>
                <?php
                    $availableClassSubs = ($userRole === 'teacher' && isset($teacherClassSubjectMap[$selectedClassId]))
                        ? $teacherClassSubjectMap[$selectedClassId]['subjects']
                        : $filterSubjects;
                ?>
                <?php if ($userRole === 'admin'): ?>
                    <a href="index.php?class_id=<?= $selectedClassId ?>&subject=all" 
                       class="btn-subject-pill <?= $selectedSubject === 'all' ? 'active' : '' ?>">
                        Tất cả môn
                    </a>
                <?php endif; ?>
                <?php foreach ($availableClassSubs as $subItem): ?>
                    <a href="index.php?class_id=<?= $selectedClassId ?>&subject=<?= urlencode($subItem) ?>" 
                       class="btn-subject-pill <?= $selectedSubject === $subItem ? 'active' : '' ?>">
                        Môn <?= htmlspecialchars($subItem) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- 2 KHỐI SO SÁNH: HỌC LỰC MÔN VÀ HỌC LỰC CHUNG (TT22) -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 18px;">
            <!-- KHỐI 1: HỌC LỰC MÔN HIỆN TẠI -->
            <div style="background: #f8fafc; border-radius: 14px; padding: 16px 18px; border: 1px solid #e2e8f0;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <b style="font-size: 14px; color: #1e293b; display: flex; align-items: center; gap: 6px;">
                        <span>📚</span> Học Lực Môn: <span style="color: #4f46e5;"><?= htmlspecialchars($selectedSubject === 'all' ? 'Tổng Hợp Môn' : $selectedSubject) ?></span>
                    </b>
                    <span class="badge" style="background: #e0e7ff; color: #4338ca; font-weight: 700;">
                        <?= $stats['total'] ?> bài đánh giá
                    </span>
                </div>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; text-align: center;">
                    <div onclick="filterByTier('tier:excellent')" title="Bấm để lọc xem danh sách học sinh Mức Tốt" style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 10px; padding: 10px 6px; cursor: pointer; transition: transform .18s ease, box-shadow .18s ease;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(16,185,129,.2)'" onmouseout="this.style.transform='none';this.style.boxShadow='none'">
                        <span style="font-size: 11px; font-weight: 700; color: #065f46; display: block;">🌟 MỨC TỐT</span>
                        <b style="font-size: 18px; color: #047857;"><?= $stats['tier_excellent'] ?></b>
                        <small style="display: block; font-size: 11px; color: #059669;"><?= round($stats['tier_excellent'] / max(1, $stats['total']) * 100, 1) ?>%</small>
                    </div>
                    <div onclick="filterByTier('tier:good')" title="Bấm để lọc xem danh sách học sinh Mức Khá" style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; padding: 10px 6px; cursor: pointer; transition: transform .18s ease, box-shadow .18s ease;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(59,130,246,.2)'" onmouseout="this.style.transform='none';this.style.boxShadow='none'">
                        <span style="font-size: 11px; font-weight: 700; color: #1e40af; display: block;">🟢 MỨC KHÁ</span>
                        <b style="font-size: 18px; color: #1d4ed8;"><?= $stats['tier_good'] ?></b>
                        <small style="display: block; font-size: 11px; color: #2563eb;"><?= round($stats['tier_good'] / max(1, $stats['total']) * 100, 1) ?>%</small>
                    </div>
                    <div onclick="filterByTier('tier:average')" title="Bấm để lọc xem danh sách học sinh Mức Đạt" style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px; padding: 10px 6px; cursor: pointer; transition: transform .18s ease, box-shadow .18s ease;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(245,158,11,.2)'" onmouseout="this.style.transform='none';this.style.boxShadow='none'">
                        <span style="font-size: 11px; font-weight: 700; color: #92400e; display: block;">🟡 MỨC ĐẠT</span>
                        <b style="font-size: 18px; color: #b45309;"><?= $stats['tier_average'] ?></b>
                        <small style="display: block; font-size: 11px; color: #d97706;"><?= round($stats['tier_average'] / max(1, $stats['total']) * 100, 1) ?>%</small>
                    </div>
                    <div onclick="filterByTier('tier:low')" title="Bấm để lọc xem danh sách học sinh Chưa Đạt" style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; padding: 10px 6px; cursor: pointer; transition: transform .18s ease, box-shadow .18s ease;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(239,68,68,.2)'" onmouseout="this.style.transform='none';this.style.boxShadow='none'">
                        <span style="font-size: 11px; font-weight: 700; color: #991b1b; display: block;">🔴 CHƯA ĐẠT</span>
                        <b style="font-size: 18px; color: #b91c1c;"><?= $stats['tier_low'] ?></b>
                        <small style="display: block; font-size: 11px; color: #dc2626;"><?= round($stats['tier_low'] / max(1, $stats['total']) * 100, 1) ?>%</small>
                    </div>
                </div>
            </div>

            <!-- KHỐI 2: HỌC LỰC CHUNG TOÀN LỚP (CHUẨN THÔNG TƯ 22) -->
            <div style="background: linear-gradient(145deg, #f8fafc, #eef2ff); border-radius: 14px; padding: 16px 18px; border: 1px solid #cbd5e1; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.04);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <b style="font-size: 14px; color: #1e293b; display: flex; align-items: center; gap: 6px;">
                        <span>⚖️</span> <b>HỌC LỰC CHUNG TOÀN LỚP (TT22)</b>
                    </b>
                    <span class="badge" style="background: #4f46e5; color: #ffffff; font-weight: 700;">
                        Tổng hợp đa môn
                    </span>
                </div>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; text-align: center;">
                    <div onclick="filterByTier('overall:excellent')" title="Bấm để lọc xem danh sách học sinh đạt Mức Tốt chung toàn lớp" style="background: #ffffff; border: 1px solid #10b981; border-radius: 10px; padding: 10px 6px; cursor: pointer; transition: transform .18s ease, box-shadow .18s ease;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(16,185,129,.2)'" onmouseout="this.style.transform='none';this.style.boxShadow='none'">
                        <span style="font-size: 11px; font-weight: 700; color: #065f46; display: block;">🌟 TỐT (CHUNG)</span>
                        <b style="font-size: 18px; color: #047857;"><?= $classOverallSummary['excellent'] ?></b>
                        <small style="display: block; font-size: 11px; color: #059669;"><?= round($classOverallSummary['excellent'] / max(1, $classOverallSummary['total']) * 100, 1) ?>%</small>
                    </div>
                    <div onclick="filterByTier('overall:good')" title="Bấm để lọc xem danh sách học sinh đạt Mức Khá chung toàn lớp" style="background: #ffffff; border: 1px solid #3b82f6; border-radius: 10px; padding: 10px 6px; cursor: pointer; transition: transform .18s ease, box-shadow .18s ease;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(59,130,246,.2)'" onmouseout="this.style.transform='none';this.style.boxShadow='none'">
                        <span style="font-size: 11px; font-weight: 700; color: #1e40af; display: block;">🟢 KHÁ (CHUNG)</span>
                        <b style="font-size: 18px; color: #1d4ed8;"><?= $classOverallSummary['good'] ?></b>
                        <small style="display: block; font-size: 11px; color: #2563eb;"><?= round($classOverallSummary['good'] / max(1, $classOverallSummary['total']) * 100, 1) ?>%</small>
                    </div>
                    <div onclick="filterByTier('overall:average')" title="Bấm để lọc xem danh sách học sinh đạt Mức Đạt chung toàn lớp" style="background: #ffffff; border: 1px solid #f59e0b; border-radius: 10px; padding: 10px 6px; cursor: pointer; transition: transform .18s ease, box-shadow .18s ease;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(245,158,11,.2)'" onmouseout="this.style.transform='none';this.style.boxShadow='none'">
                        <span style="font-size: 11px; font-weight: 700; color: #92400e; display: block;">🟡 ĐẠT (CHUNG)</span>
                        <b style="font-size: 18px; color: #b45309;"><?= $classOverallSummary['average'] ?></b>
                        <small style="display: block; font-size: 11px; color: #d97706;"><?= round($classOverallSummary['average'] / max(1, $classOverallSummary['total']) * 100, 1) ?>%</small>
                    </div>
                    <div onclick="filterByTier('overall:low')" title="Bấm để lọc xem danh sách học sinh Chưa Đạt chung toàn lớp" style="background: #ffffff; border: 1px solid #ef4444; border-radius: 10px; padding: 10px 6px; cursor: pointer; transition: transform .18s ease, box-shadow .18s ease;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(239,68,68,.2)'" onmouseout="this.style.transform='none';this.style.boxShadow='none'">
                        <span style="font-size: 11px; font-weight: 700; color: #991b1b; display: block;">🔴 CHƯA ĐẠT</span>
                        <b style="font-size: 18px; color: #b91c1c;"><?= $classOverallSummary['low'] ?></b>
                        <small style="display: block; font-size: 11px; color: #dc2626;"><?= round($classOverallSummary['low'] / max(1, $classOverallSummary['total']) * 100, 1) ?>%</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- NHÓM 1: CÁC THẺ THỐNG KÊ KHUNG HIỆU SUẤT (ĐỨNG TRƯỚC & NỔI BẬT) -->
<div style="margin-bottom: 24px;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
        <span style="font-size: 13px; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">
            ⚡ KHUNG 1: HIỆU SUẤT & ĐỘNG NĂNG HỌC TẬP (MOMENTUM)
        </span>
        <span class="muted small">Tăng trưởng · Đi đều · Sa sút · Chờ điểm</span>
    </div>
    <section class="radar-stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 16px;">
        <div class="card stat-card trend-up">
            <div class="card-header-row">
                <span class="card-title">📈 Đang Tiến Bộ / Bứt Phá</span>
                <span class="card-pct trend-up"><?= $stats['up_pct'] ?>%</span>
            </div>
            <b><?= $stats['momentum_up'] ?></b>
            <span class="card-sub">Bài sau cao hơn bài trước hoặc CK bứt phá so với GK</span>
        </div>

        <div class="card stat-card green">
            <div class="card-header-row">
                <span class="card-title">🔄 Đang Đi Đều / Duy Trì</span>
                <span class="card-pct green"><?= $stats['steady_pct'] ?>%</span>
            </div>
            <b><?= $stats['momentum_steady'] ?></b>
            <span class="card-sub">Điểm số ổn định, không bị rơi rụng (cần bồi dưỡng bứt phá)</span>
        </div>

        <div class="card stat-card trend-down">
            <div class="card-header-row">
                <span class="card-title">📉 Đang Sa Sút / Tuột Dốc</span>
                <span class="card-pct trend-down"><?= $stats['down_pct'] ?>%</span>
            </div>
            <b><?= $stats['momentum_down'] ?></b>
            <span class="card-sub">Chuỗi điểm TX tụt dốc hoặc thi CK sút giảm</span>
        </div>

        <div class="card stat-card" style="border-left: 4px solid #64748b;">
            <div class="card-header-row">
                <span class="card-title">⏳ Đang Chờ Điểm Số</span>
                <span class="card-pct" style="color: #64748b;"><?= $stats['pending_pct'] ?>%</span>
            </div>
            <b><?= $stats['momentum_pending'] ?></b>
            <span class="card-sub">Chưa có bài kiểm tra ghi nhận trong sổ điểm</span>
        </div>
    </section>
</div>

<!-- NHÓM 2: THỐNG KÊ KHUNG ĐIỂM SỐ & VÙNG RỦI RO THÔNG TƯ 22 -->
<div style="margin-bottom: 28px;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
        <span style="font-size: 13px; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">
            📊 KHUNG 2: PHÂN KHÚC ĐIỂM SỐ & CHẨN ĐOÁN RỦI RO (THÔNG TƯ 22)
        </span>
        <span class="muted small">Tổng số <?= $stats['total'] ?> hồ sơ</span>
    </div>
    <section class="radar-stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
        <div class="card stat-card total">
            <div class="card-header-row">
                <span class="card-title">👥 Tổng Hồ Sơ</span>
                <span class="card-pct total">100%</span>
            </div>
            <b><?= $stats['total'] ?></b>
            <span class="card-sub">Tổng số học sinh theo dõi</span>
        </div>

        <?php if ($stats['momentum_pending'] > 0): ?>
            <div class="card stat-card" style="border-left: 4px solid #94a3b8; background: #f8fafc;">
                <div class="card-header-row">
                    <span class="card-title" style="color: #475569;">⏳ Chờ Điểm Số</span>
                    <span class="card-pct" style="color: #64748b; font-weight: 700;"><?= round(($stats['momentum_pending'] / max(1, $stats['total'])) * 100, 1) ?>%</span>
                </div>
                <b style="color: #334155;"><?= $stats['momentum_pending'] ?></b>
                <span class="card-sub">Chưa có bài kiểm tra ghi nhận</span>
            </div>
        <?php endif; ?>

        <div class="card stat-card green">
            <div class="card-header-row">
                <span class="card-title">⭐ Giỏi & Khá</span>
                <span class="card-pct green"><?= $stats['total'] > 0 ? round((($stats['tier_excellent'] + $stats['tier_good']) / $stats['total']) * 100, 1) : 0 ?>%</span>
            </div>
            <b><?= $stats['tier_excellent'] + $stats['tier_good'] ?></b>
            <span class="card-sub">ĐTB từ 6.5 trở lên</span>
        </div>

        <div class="card stat-card yellow">
            <div class="card-header-row">
                <span class="card-title">🟡 Cần Chú Ý</span>
                <span class="card-pct yellow"><?= $stats['yellow_pct'] ?>%</span>
            </div>
            <b><?= $stats['yellow'] ?></b>
            <span class="card-sub">Đi đều mức thấp/TB hoặc có dấu hiệu sa sút</span>
        </div>

        <div class="card stat-card red">
            <div class="card-header-row">
                <span class="card-title">🔴 Nguy Cấp</span>
                <span class="card-pct red"><?= $stats['red_pct'] ?>%</span>
            </div>
            <b><?= $stats['red'] ?></b>
            <span class="card-sub">ĐTB &lt; 3.5, CK &lt; 3.0, hoặc hỏng thi</span>
        </div>
    </section>
</div>

<!-- THANH CÔNG CỤ LỌC CHIP ĐA CHIỀU & TÌM KIẾM TỨC THÌ (CẠNH BÊN PHẢI) -->
<div class="radar-toolbar">
    <div class="radar-chips">
        <button type="button" class="chip active" data-filter="all">
            <span>Tất cả</span>
            <span>(<?= $stats['total'] ?>)</span>
        </button>
        <button type="button" class="chip chip-trend-up" data-filter="trend:up">
            <span>📈 Tiến bộ</span>
            <span>(<?= $stats['momentum_up'] ?>)</span>
        </button>
        <button type="button" class="chip chip-green" data-filter="trend:steady">
            <span>🔄 Duy trì đều</span>
            <span>(<?= $stats['momentum_steady'] ?>)</span>
        </button>
        <button type="button" class="chip chip-trend-down" data-filter="trend:down">
            <span>📉 Sa sút</span>
            <span>(<?= $stats['momentum_down'] ?>)</span>
        </button>
        <button type="button" class="chip" data-filter="trend:pending" style="color: #64748b;">
            <span>⏳ Chờ điểm</span>
            <span>(<?= $stats['momentum_pending'] ?>)</span>
        </button>

        <span style="color: #cbd5e1; font-weight: 300;">|</span>

        <!-- LỌC PHÂN LOẠI HỌC LỰC (HỌC SINH GIỎI / KHÁ / TRUNG BÌNH / CẦN PHỤ ĐẠO) -->
        <button type="button" class="chip chip-green" data-filter="tier:excellent" title="Chỉ xem học sinh Giỏi / Mức Tốt (ĐTB ≥ 8.0)">
            <span>⭐ Học Sinh Giỏi</span>
            <span>(<?= $stats['tier_excellent'] ?>)</span>
        </button>
        <button type="button" class="chip" data-filter="tier:good" style="color: #1d4ed8; border-color: #bfdbfe;" title="Chỉ xem học sinh Khá (6.5 ≤ ĐTB < 8.0)">
            <span>✨ Học Sinh Khá</span>
            <span>(<?= $stats['tier_good'] ?>)</span>
        </button>
        <button type="button" class="chip" data-filter="tier:average" style="color: #b45309; border-color: #fde68a;" title="Chỉ xem học sinh Mức Đạt / Trung Bình (5.0 ≤ ĐTB < 6.5)">
            <span>⚖️ Mức Đạt (TB)</span>
            <span>(<?= $stats['tier_average'] ?>)</span>
        </button>
        <button type="button" class="chip chip-red" data-filter="tier:low" title="Chỉ xem học sinh Chưa Đạt / Cần Bồi Dưỡng (ĐTB < 5.0)">
            <span>⚠️ Cần Phụ Đạo</span>
            <span>(<?= $stats['tier_low'] ?>)</span>
        </button>

        <span style="color: #cbd5e1; font-weight: 300;">|</span>

        <button type="button" class="chip chip-red" data-filter="red">
            <span>🔴 Nguy cấp (<?= $stats['red'] ?>)</span>
        </button>
        <button type="button" class="chip chip-yellow" data-filter="yellow">
            <span>🟡 Cần chú ý (<?= $stats['yellow'] ?>)</span>
        </button>
        <button type="button" class="chip chip-green" data-filter="green">
            <span>🟢 Ổn định (<?= $stats['green'] ?>)</span>
        </button>
        <?php if ($stats['tx_warn_count'] > 0): ?>
            <button type="button" class="chip chip-tx-warn" data-filter="tx:warn">
                <span>⚠️ Cảnh báo sớm TX (<?= $stats['tx_warn_count'] ?>)</span>
            </button>
        <?php endif; ?>

        <span style="color: #cbd5e1; font-weight: 300;">|</span>

        <!-- LỌC PHÂN MÔN TRỰC TIẾP TRÊN TOOLBAR -->
        <div class="subject-quick-filter-group">
            <span class="filter-label-sub">📚 Môn:</span>
            <?php if ($userRole === 'admin'): ?>
                <a href="index.php?class_id=<?= $selectedClassId ?>&subject=all" 
                   class="sub-filter-pill <?= $selectedSubject === 'all' ? 'active' : '' ?>">
                    Tất cả
                </a>
            <?php endif; ?>
            <?php
                $toolbarSubs = ($userRole === 'teacher' && $selectedClassId > 0 && isset($teacherClassSubjectMap[$selectedClassId]))
                    ? $teacherClassSubjectMap[$selectedClassId]['subjects']
                    : $filterSubjects;
            ?>
            <?php foreach ($toolbarSubs as $subName): ?>
                <a href="index.php?class_id=<?= $selectedClassId ?>&subject=<?= urlencode($subName) ?>" 
                   class="sub-filter-pill <?= $selectedSubject === $subName ? 'active' : '' ?>">
                    <?= htmlspecialchars($subName) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- NÚT ẨN / HIỆN CỘT ĐIỂM CHI TIẾT (TX, GK, CK) -->
        <button type="button" class="btn-toggle-score-cols <?= ($selectedSubject !== 'all') ? 'active' : '' ?>" id="btnToggleScoreCols" onclick="toggleScoreDetailColumns()" title="Bật/Tắt hiển thị các cột điểm thành phần TX, GK, CK">
            <?= ($selectedSubject === 'all') ? '👁️ Hiện Điểm TX, GK, CK' : '⚡ Thu Gọn (Chỉ Xem ĐTB)' ?>
        </button>
    </div>

    <!-- Lucian UX/UI Prompt #034 · MIT License (c) 2026 Seokwoooo · https://lucianlabs.dev/uxui-prompt/#034 -->
    <style>
    .u034{position:relative;width:320px;max-width:100%;margin-left:auto;}
    .u034-box{position:relative;display:flex;align-items:center;gap:10px;height:44px;padding:0 14px;border-radius:14px;background:#ffffff !important;border:1.5px solid #cbd5e1;box-shadow:0 1px 3px rgba(0,0,0,.04);transition:border-color .2s ease, box-shadow .2s ease}
    .u034-box:focus-within{border-color:#4f46e5;box-shadow:0 0 0 3px rgba(79,70,229,.15)}
    .u034-pl{width:18px;height:18px;flex:none;fill:none;stroke:#64748b;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;transition:stroke .3s,transform .3s ease}
    .u034.is-set .u034-pl{stroke:#4f46e5;transform:translateX(2px) scale(1.05)}
    .u034-wrap{position:relative;flex:1;height:100%;min-width:0;}
    .u034-ghost,.u034-in{position:absolute;inset:0;width:100%;padding:0;border:0;font:inherit;font-size:13.5px;font-weight:600;letter-spacing:-.01em;line-height:44px;background:none;white-space:pre}
    .u034-ghost{color:#94a3b8 !important;pointer-events:none;opacity:.75}
    .u034-ghost b{font-weight:inherit;color:transparent}
    .u034-in{outline:0;color:#0f172a !important;}
    .u034-in::placeholder{color:#94a3b8;opacity:1;}
    .u034-in:focus-visible{outline:none}
    .u034-code{flex:none;padding:3px 7px;border-radius:7px;background:rgba(79,70,229,.1);color:#4f46e5;font-size:11px;font-weight:800;letter-spacing:.04em;opacity:0;transform:scale(.6);transition:opacity .2s,transform .3s ease}
    .u034.is-set .u034-code{opacity:1;transform:none}
    .u034-list{position:absolute;left:0;right:0;top:calc(100% + 8px);margin:0;padding:6px;list-style:none;border-radius:16px;background:#ffffff !important;border:1px solid #e2e8f0;box-shadow:0 12px 28px -6px rgba(0,0,0,.12),0 4px 10px -2px rgba(0,0,0,.06);max-height:280px;overflow-y:auto;z-index:999;display:none;}
    .u034-hl{position:absolute;left:6px;right:6px;top:6px;height:52px;border-radius:12px;background:#f1f5f9;transform:translateY(calc(var(--i,0)*52px));transition:transform .2s ease,opacity .2s;pointer-events:none}
    .u034-o{position:relative;display:flex;align-items:center;gap:10px;height:52px;padding:0 10px;border-radius:12px;cursor:pointer;animation:u034-in .25s ease backwards}
    @keyframes u034-in{from{opacity:0;transform:translateY(5px)}}
    .u034-o i{width:30px;height:30px;flex:none;border-radius:9px;display:grid;place-items:center;font-style:normal;font-size:10.5px;font-weight:800;color:#fff;background:var(--c,#4f46e5)}
    .u034-o b{display:block;font-size:13px;font-weight:700;color:#0f172a !important;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .u034-o b mark{background:none;color:#4f46e5;font-weight:800}
    .u034-o small{display:block;margin-top:1px;font-size:11px;color:#64748b !important}
    .u034-o em{margin-left:auto;font-style:normal;font-size:11.5px;font-weight:700;color:#334155;white-space:nowrap;font-variant-numeric:tabular-nums}
    .u034-none{padding:20px 10px;text-align:center;font-size:12.5px;color:#94a3b8}
    </style>

    <div class="ux u034" id="u034SearchContainer" style="margin-left: auto;">
        <label class="u034-lab" for="search" style="display:none;">Tìm kiếm học sinh</label>
        <div class="u034-box">
            <svg class="u034-pl" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
            <div class="u034-wrap">
                <span class="u034-ghost" id="u034Ghost" aria-hidden="true"></span>
                <input class="u034-in" type="text" id="search" role="combobox" aria-autocomplete="both" aria-expanded="false" autocomplete="off" placeholder="Tìm theo tên hoặc mã học sinh...">
            </div>
            <span class="u034-code" id="u034Code" aria-hidden="true"></span>
        </div>
        <ul class="u034-list" id="u034List" role="listbox" aria-label="Gợi ý học sinh">
            <li class="u034-hl" id="u034Highlight" role="presentation"></li>
        </ul>
    </div>
</div>

<script>
(function() {
    var root = document.getElementById('u034SearchContainer');
    if (!root) return;
    var input = document.getElementById('search');
    var ghost = document.getElementById('u034Ghost');
    var list = document.getElementById('u034List');
    var hl = document.getElementById('u034Highlight');
    var code = document.getElementById('u034Code');

    // Nạp dữ liệu học sinh hiện tại trên trang
    var rawStudents = <?= json_encode(array_map(function($s) {
        return [
            'name' => $s['full_name'],
            'code' => $s['student_code'] ?: ('HS' . $s['student_id']),
            'class' => $s['class_name'] ?? '',
            'avg' => ($s['avg_score'] !== null && $s['avg_score'] !== '') ? number_format((float)$s['avg_score'], 1) : 'Chờ',
            'level' => $s['level'] ?? 'pending',
            'grad' => ($s['level'] === 'red') ? 'linear-gradient(135deg,#ef4444,#dc2626)' : (($s['level'] === 'yellow') ? 'linear-gradient(135deg,#f59e0b,#d97706)' : (($s['level'] === 'green') ? 'linear-gradient(135deg,#10b981,#059669)' : 'linear-gradient(135deg,#64748b,#475569)'))
        ];
    }, $students), JSON_UNESCAPED_UNICODE) ?>;

    var shown = [], act = 0;
    function esc(s) { return String(s || '').replace(/[&<>]/g, function(c) { return {'&':'&amp;','<':'&lt;','>':'&gt;'}[c]; }); }
    function stripAccents(str) { return String(str || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase(); }

    function renderSuggestions() {
        var q = input.value.trim();
        var qNorm = stripAccents(q);
        if (!q) {
            list.style.display = 'none';
            ghost.innerHTML = '';
            root.classList.remove('is-set');
            return;
        }

        shown = rawStudents.filter(function(st) {
            var nNorm = stripAccents(st.name);
            var cNorm = stripAccents(st.code);
            return nNorm.indexOf(qNorm) >= 0 || cNorm.indexOf(qNorm) >= 0;
        }).slice(0, 5);

        act = 0;
        Array.prototype.slice.call(list.querySelectorAll('.u034-o,.u034-none')).forEach(function(el) { el.remove(); });

        if (!shown.length) {
            list.insertAdjacentHTML('beforeend', '<li class="u034-none">Không tìm thấy học sinh khớp với ‘' + esc(q) + '’</li>');
            list.style.display = 'block';
            hl.style.opacity = 0;
            ghost.innerHTML = '';
            return;
        }

        list.style.display = 'block';
        hl.style.opacity = 1;

        shown.forEach(function(st, i) {
            var li = document.createElement('li');
            li.className = 'u034-o';
            li.setAttribute('role', 'option');
            li.style.animationDelay = (i * 30) + 'ms';
            
            var dispName = esc(st.name);
            var idx = stripAccents(st.name).indexOf(qNorm);
            if (idx >= 0) {
                dispName = esc(st.name.substring(0, idx)) + '<mark>' + esc(st.name.substring(idx, idx + q.length)) + '</mark>' + esc(st.name.substring(idx + q.length));
            }

            var iconText = st.name.split(' ').pop().substring(0, 2).toUpperCase();
            li.innerHTML = '<i style="--c:' + st.grad + '" aria-hidden="true">' + iconText + '</i>' +
                           '<span><b>' + dispName + '</b><small>' + esc(st.class) + ' &bull; Mã: ' + esc(st.code) + '</small></span>' +
                           '<em>ĐTB: ' + esc(st.avg) + '</em>';

            li.addEventListener('pointermove', function() { if (act !== i) { act = i; mark(); } });
            li.addEventListener('mousedown', function(e) { e.preventDefault(); choose(i); });
            list.appendChild(li);
        });

        mark();
    }

    function mark() {
        hl.style.setProperty('--i', act);
        var q = input.value;
        var qNorm = stripAccents(q);
        var cur = shown[act];
        if (cur && q && stripAccents(cur.name).indexOf(qNorm) === 0 && cur.name.length > q.length) {
            ghost.innerHTML = '<b>' + esc(q) + '</b>' + esc(cur.name.slice(q.length)) + ' (' + esc(cur.class) + ')';
        } else {
            ghost.innerHTML = '';
        }
    }

    function choose(i) {
        var cur = shown[i];
        if (!cur) return;
        input.value = cur.name;
        code.textContent = cur.code;
        root.classList.add('is-set');
        ghost.innerHTML = '';
        list.style.display = 'none';
        // Kích hoạt sự kiện input để đồng bộ với bộ lọc radar
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }

    input.addEventListener('input', function() {
        root.classList.remove('is-set');
        renderSuggestions();
    });

    input.addEventListener('keydown', function(e) {
        if (e.isComposing || e.keyCode === 229) return;
        if (e.key === 'ArrowDown' && shown.length) {
            e.preventDefault();
            act = (act + 1) % shown.length;
            mark();
        } else if (e.key === 'ArrowUp' && shown.length) {
            e.preventDefault();
            act = (act - 1 + shown.length) % shown.length;
            mark();
        } else if (e.key === 'Enter') {
            if (shown.length > 0 && list.style.display !== 'none') {
                e.preventDefault();
                choose(act);
            }
        } else if (e.key === 'Tab' && ghost.textContent && !e.shiftKey) {
            if (shown.length > 0) {
                e.preventDefault();
                choose(act);
            }
        } else if (e.key === 'Escape') {
            list.style.display = 'none';
            ghost.innerHTML = '';
        }
    });

    document.addEventListener('click', function(e) {
        if (!root.contains(e.target)) {
            list.style.display = 'none';
            ghost.innerHTML = '';
        }
    });
})();
</script>

<?php if ($selectedClassId > 0 && $stats['total'] > 0 && $stats['momentum_pending'] === $stats['total']): ?>
    <!-- THÔNG BÁO HƯỚNG DẪN RÕ RÀNG KHI LỚP CHƯA CÓ ĐIỂM SỐ GHI NHẬN -->
    <div style="background: #eff6ff; border: 1.5px solid #bfdbfe; border-radius: 12px; padding: 16px 20px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <span style="font-size: 26px;">💡</span>
            <div>
                <div style="font-size: 14.5px; font-weight: 800; color: #1e40af;">
                    Lớp <?= htmlspecialchars($filterClasses[$selectedClassId] ?? '') ?>: Đang theo dõi hồ sơ học sinh, chưa có điểm số môn [<?= htmlspecialchars($selectedSubject === 'all' ? ($filterSubjects[0] ?? 'bộ môn') : $selectedSubject) ?>]
                </div>
                <div style="font-size: 12.5px; color: #3b82f6; margin-top: 2px;">
                    Dữ liệu học sinh đã sẵn sàng. Giáo viên bộ môn có thể nhập điểm trực tiếp hoặc nạp file Excel để hệ thống tự động chẩn đoán 2 khung năng lực ngay lập tức.
                </div>
            </div>
        </div>
        <a href="nhap_diem.php?class_id=<?= $selectedClassId ?>&subject=<?= urlencode($selectedSubject === 'all' ? ($filterSubjects[0] ?? 'Toán') : $selectedSubject) ?>" class="btn primary" style="padding: 8px 18px; font-size: 13px; font-weight: 700; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
            📋 Mở Sổ Điểm Nhập Điểm Ngay
        </a>
    </div>
<?php endif; ?>

<!-- BẢNG RADAR 2 KHUNG ĐÁNH GIÁ: HIỆU SUẤT ĐỨNG TRƯỚC, KHUNG ĐIỂM, CHẨN ĐOÁN & KHUYẾN NGHỊ -->
<div class="radar-table-wrap">
    <table class="radar-table <?= ($selectedSubject === 'all') ? 'scores-hidden' : '' ?>" id="radarTable">
        <thead>
            <tr>
                <th style="width: 45px; text-align: center;">STT</th>
                <th style="width: 85px;">Mã HS</th>
                <th style="min-width: 170px;">Họ và Tên Học Sinh</th>
                <th style="min-width: 125px;">Lớp & Môn</th>
                <th style="min-width: 130px; background: #1e1b4b; color: #e0e7ff;">⚡ 1. HIỆU SUẤT</th>
                <th style="min-width: 110px; background: #0f172a;">📊 2. KHUNG ĐIỂM</th>
                <th style="width: 70px; text-align: center;">ĐTB</th>
                <th class="col-detail-score" style="width: 130px; text-align: center;">ĐĐGtx (1-4)</th>
                <th class="col-detail-score" style="width: 55px; text-align: center;">GK</th>
                <th class="col-detail-score" style="width: 55px; text-align: center;">CK</th>
                <th style="width: 110px; text-align: center;">Vùng Rủi Ro</th>
                <th style="min-width: 250px;">Năng Lực Tổng Thể & Khuyến Nghị Sư Phạm</th>
                <th style="width: 105px; text-align: center;">Hành Động</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($students)): ?>
                <tr id="radarInitialEmptyRow" class="radar-initial-empty-row">
                    <td colspan="13" style="text-align: center; padding: 40px; color: var(--text-muted);">
                        Chưa có học sinh nào trong phạm vi được phân công. Hãy vào sổ điểm để cập nhật.
                    </td>
                </tr>
            <?php else: ?>
                <?php
                // GOM NHÓM THEO HỌC SINH ĐỂ LOẠI BỎ TRÙNG TÊN VÀ TẠO MŨI TÊN XỔ CÁC MÔN
                $studentsGrouped = [];
                foreach ($students as $s) {
                    $sid = (int)$s['student_id'];
                    if (!isset($studentsGrouped[$sid])) {
                        $studentsGrouped[$sid] = [
                            'student' => $s,
                            'subjects' => []
                        ];
                    }
                    $studentsGrouped[$sid]['subjects'][] = $s;
                }
                ?>
                <?php if ($selectedSubject === 'all'): ?>
                    <?php $stt = 1; foreach ($studentsGrouped as $sid => $group): ?>
                        <?php
                            $mainStudent = $group['student'];
                            $subjects = $group['subjects'];
                            $hasMulti = count($subjects) > 1;

                            // Tên và avatar
                            $nameParts = explode(' ', trim($mainStudent['full_name']));
                            $lastName = end($nameParts);
                            $initials = mb_strtoupper(mb_substr($lastName, 0, 2, 'UTF-8'), 'UTF-8');

                            // Đánh giá tổng hợp rủi ro
                            $hasRed = false; $hasYellow = false; $hasPending = false; $hasGreen = false;
                            $sumAvg = 0; $countAvg = 0;
                            foreach ($subjects as $sub) {
                                if ($sub['level'] === 'red') $hasRed = true;
                                elseif ($sub['level'] === 'yellow') $hasYellow = true;
                                elseif ($sub['level'] === 'pending') $hasPending = true;
                                else $hasGreen = true;

                                if ($sub['avg_score'] !== null && $sub['avg_score'] !== '') {
                                    $sumAvg += (float)$sub['avg_score'];
                                    $countAvg++;
                                }
                            }
                            $overallRisk = $hasRed ? 'red' : ($hasYellow ? 'yellow' : ($hasGreen ? 'green' : 'pending'));
                            $overallAvg = $countAvg > 0 ? round($sumAvg / $countAvg, 1) : null;

                            if ($overallRisk === 'red') {
                                $gradColor = 'linear-gradient(135deg, #ef4444, #dc2626)';
                                $riskLabel = 'Nguy cấp';
                                $riskTitle = 'Học sinh có môn ở mức nguy cấp';
                            } elseif ($overallRisk === 'yellow') {
                                $gradColor = 'linear-gradient(135deg, #f59e0b, #d97706)';
                                $riskLabel = 'Cần chú ý';
                                $riskTitle = 'Học sinh có môn cần chú ý hoặc sa sút';
                            } elseif ($overallRisk === 'pending') {
                                $gradColor = 'linear-gradient(135deg, #94a3b8, #64748b)';
                                $riskLabel = 'Chờ điểm';
                                $riskTitle = 'Chưa có điểm đánh giá';
                            } else {
                                $gradColor = 'linear-gradient(135deg, #10b981, #059669)';
                                $riskLabel = 'Ổn định';
                                $riskTitle = 'Học tập ổn định trên các môn';
                            }

                            $firstSub = $subjects[0];
                            $overallTierBadge = $studentOverallMap[$sid]['badge'] ?? $mainStudent['score_tier']['badge'];
                            $overallTierClass = $studentOverallMap[$sid]['class'] ?? $mainStudent['score_tier']['class'];
                            $overallTier = $studentOverallMap[$sid]['level'] ?? $mainStudent['score_tier']['tier'];
                        ?>
                        <tr class="radar-row" 
                            data-student-id="<?= $sid ?>"
                            data-student-code="<?= htmlspecialchars(!empty($mainStudent['student_code']) ? $mainStudent['student_code'] : 'HS' . $sid) ?>"
                            data-full-name="<?= htmlspecialchars($mainStudent['full_name']) ?>"
                            data-class-name="<?= htmlspecialchars($mainStudent['class_name']) ?>"
                            data-subject="<?= htmlspecialchars(implode(', ', array_column($subjects, 'subject'))) ?>"
                            data-birthday="<?= htmlspecialchars($mainStudent['birthday'] ?? '') ?>"
                            data-phone="<?= htmlspecialchars($mainStudent['phone'] ?? '') ?>"
                            data-tx1="<?= $firstSub['tx1'] !== null ? $firstSub['tx1'] : '' ?>"
                            data-tx2="<?= $firstSub['tx2'] !== null ? $firstSub['tx2'] : '' ?>"
                            data-tx3="<?= $firstSub['tx3'] !== null ? $firstSub['tx3'] : '' ?>"
                            data-tx4="<?= $firstSub['tx4'] !== null ? $firstSub['tx4'] : '' ?>"
                            data-gk="<?= $firstSub['gk'] !== null ? $firstSub['gk'] : '' ?>"
                            data-ck="<?= $firstSub['ck'] !== null ? $firstSub['ck'] : '' ?>"
                            data-avg="<?= $overallAvg !== null ? $overallAvg : '' ?>"
                            data-level="<?= $overallRisk ?>" 
                            data-trend="<?= $firstSub['trend'] ?>"
                            data-momentum="<?= $firstSub['momentum'] ?>"
                            data-momentum-label="<?= htmlspecialchars($firstSub['trend_label']) ?>"
                            data-momentum-icon="<?= htmlspecialchars($firstSub['trend_icon']) ?>"
                            data-tier="<?= $overallTier ?>"
                            data-tier-label="<?= htmlspecialchars($overallTierBadge) ?>"
                            data-tier-badge="<?= htmlspecialchars($overallTierBadge) ?>"
                            data-tier-class="<?= htmlspecialchars($overallTierClass) ?>"
                            data-overall="<?= htmlspecialchars($studentOverallMap[$sid]['level'] ?? '') ?>"
                            data-txwarn="<?= !empty($firstSub['has_tx_warning']) ? '1' : '0' ?>"
                            data-diagnostic="<?= htmlspecialchars($firstSub['diagnostic']) ?>"
                            data-advice="<?= htmlspecialchars($firstSub['competency']['pedagogical_advice'] ?? '') ?>"
                            data-competency-title="<?= htmlspecialchars($firstSub['competency']['competency_title'] ?? '') ?>"
                            data-note="<?= htmlspecialchars($firstSub['teacher_note'] ?? '') ?>"
                            data-name="<?= htmlspecialchars(mb_strtolower($mainStudent['full_name'], 'UTF-8')) ?>" 
                            data-search="<?= htmlspecialchars(mb_strtolower(($mainStudent['student_code'] ?? '') . ' ' . $mainStudent['full_name'] . ' ' . $mainStudent['class_name'] . ' ' . implode(' ', array_column($subjects, 'subject')) . ' ' . $firstSub['diagnostic'], 'UTF-8')) ?>">
                            <td style="text-align: center; color: var(--text-muted); font-weight: 600; white-space: nowrap;">
                                <?= $stt++ ?>
                                <?php if ($hasMulti): ?>
                                    <button type="button" class="btn-toggle-subrows" 
                                            onclick="event.stopPropagation(); toggleStudentSubrows(<?= $sid ?>, this)" 
                                            title="Bấm để xem các môn học khác của học sinh này">
                                        ▶
                                    </button>
                                <?php endif; ?>
                            </td>
                            <td>
                                <code class="mono-code" style="font-weight: 600; color: #4338ca;">
                                    <?= htmlspecialchars(!empty($mainStudent['student_code']) ? $mainStudent['student_code'] : 'HS' . $sid) ?>
                                </code>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div class="avatar-status-container">
                                        <div class="student-avatar-circle" style="background: <?= $gradColor ?>;">
                                            <?= $initials ?>
                                        </div>
                                        <span class="avatar-status-dot dot-<?= $overallRisk ?>" title="<?= $riskTitle ?>"></span>
                                    </div>
                                    <div>
                                        <div style="font-weight: 700; color: var(--text-heading); font-size: 14px;">
                                            <?= htmlspecialchars($mainStudent['full_name']) ?>
                                        </div>
                                        <?php if (!empty($mainStudent['phone'])): ?>
                                            <div class="muted small">📞 <?= htmlspecialchars($mainStudent['phone']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($mainStudent['teacher_note'])): ?>
                                            <span class="badge-has-note js-note-badge" title="<?= htmlspecialchars(mb_substr($mainStudent['teacher_note'], 0, 80)) ?>...">📝 Có ghi chú GV</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge-class">Lớp <?= htmlspecialchars($mainStudent['class_name']) ?></span>
                                <span class="badge-subject" style="background: #eef2ff; color: #4338ca; font-weight: 700;">
                                    📚 <?= count($subjects) ?> môn (<?= implode(', ', array_column($subjects, 'subject')) ?>)
                                </span>
                            </td>

                            <!-- KHUNG 1: HIỆU SUẤT -->
                            <td>
                                <span class="badge-momentum badge-momentum-<?= $firstSub['momentum'] ?>" title="<?= htmlspecialchars($firstSub['trend_description'] ?: $firstSub['trend_label']) ?>">
                                    <?= $firstSub['trend_icon'] ?> <?= htmlspecialchars($firstSub['trend_label']) ?>
                                </span>
                            </td>

                            <!-- KHUNG 2: KHUNG ĐIỂM SỐ (BẬC HỌC LỰC TT22) -->
                            <td>
                                <span class="badge-tier <?= $overallTierClass ?>">
                                    <?= htmlspecialchars($overallTierBadge) ?>
                                </span>
                            </td>

                            <!-- ĐIỂM TRUNG BÌNH HỌC KỲ (TBM CHUNG) -->
                            <td style="text-align: center;">
                                <?php if ($overallAvg !== null): ?>
                                    <span class="avg-score-badge avg-<?= $overallRisk ?>">
                                        <?= number_format($overallAvg, 1) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="avg-score-badge avg-empty">-</span>
                                <?php endif; ?>
                            </td>

                            <!-- ĐIỂM CHI TIẾT (ẨN MẶC ĐỊNH Ở CHẾ ĐỘ XEM CHUNG TOÀN MÔN ĐỂ BẢNG VỪA VẶN 100%) -->
                            <td class="col-detail-score" style="text-align: center; color: #94a3b8; font-size: 12px;">(Xem từng môn)</td>
                            <td class="col-detail-score" style="text-align: center; color: #94a3b8; font-size: 12px;">-</td>
                            <td class="col-detail-score" style="text-align: center; color: #94a3b8; font-size: 12px;">-</td>

                            <!-- VÙNG RỦI RO THÔNG TƯ 22 (CHẤM TRÒN GỌN GÀNG) -->
                            <td style="text-align: center;">
                                <span class="risk-dot-badge risk-<?= $overallRisk ?>">
                                    <span class="risk-point"></span>
                                    <?= $riskLabel ?>
                                </span>
                            </td>

                            <!-- NĂNG LỰC TỔNG THỂ & KHUYẾN NGHỊ SƯ PHẠM (HIỆN RÕ RÀNG KHÔNG CẦN CỘT ĐIỂM) -->
                            <td>
                                <div class="competency-cell">
                                    <div class="competency-title">
                                        <?= htmlspecialchars($mainStudent['competency']['competency_title']) ?>
                                    </div>
                                    <div class="competency-advice">
                                        <?= htmlspecialchars($mainStudent['competency']['pedagogical_advice']) ?>
                                    </div>
                                </div>
                            </td>

                            <td style="text-align: center; white-space: nowrap;">
                                <div style="display: inline-flex; align-items: center; gap: 6px;">
                                    <button type="button" class="btn-open-detail" title="Xem chi tiết & Ghi nhận xét sư phạm">
                                        👁️ Chi tiết
                                    </button>
                                    <a href="nhap_diem.php?class_id=<?= urlencode((string)$mainStudent['class_id']) ?>&subject=<?= urlencode($firstSub['subject']) ?>" 
                                       class="btn-goto-gradebook" 
                                       title="Vào sổ điểm lớp <?= htmlspecialchars($mainStudent['class_name']) ?> môn <?= htmlspecialchars($firstSub['subject']) ?>"
                                       onclick="event.stopPropagation();">
                                        📝 Sổ điểm
                                    </a>
                                </div>
                            </td>
                        </tr>

                        <!-- DÒNG MÔN HỌC CHI TIẾT XỔ XUỐNG KHI BẤM NÚT ▶ -->
                        <?php foreach ($subjects as $sub): ?>
                            <?php
                                $subTxFormatted = [];
                                for ($i = 1; $i <= 4; $i++) {
                                    $val = $sub["tx$i"];
                                    if ($val !== null && $val !== '') {
                                        $num = (float)$val;
                                        $fmt = number_format($num, 1);
                                        if ($num < 3.5) {
                                            $subTxFormatted[] = '<span class="tx-val-crit">' . $fmt . '</span>';
                                        } elseif ($num < 5.0) {
                                            $subTxFormatted[] = '<span class="tx-val-warn">' . $fmt . '</span>';
                                        } else {
                                            $subTxFormatted[] = '<span>' . $fmt . '</span>';
                                        }
                                    } else {
                                        $subTxFormatted[] = '<span class="muted">-</span>';
                                    }
                                }
                                $subRiskLabel = ($sub['level'] === 'red') ? 'Nguy cấp' : (($sub['level'] === 'yellow') ? 'Cần chú ý' : (($sub['level'] === 'pending') ? 'Chờ điểm' : 'Ổn định'));
                            ?>
                            <tr class="student-sub-row" 
                                data-parent-student="<?= $sid ?>"
                                data-student-id="<?= $sid ?>"
                                data-student-code="<?= htmlspecialchars(!empty($mainStudent['student_code']) ? $mainStudent['student_code'] : 'HS' . $sid) ?>"
                                data-full-name="<?= htmlspecialchars($mainStudent['full_name']) ?>"
                                data-class-name="<?= htmlspecialchars($sub['class_name']) ?>"
                                data-subject="<?= htmlspecialchars($sub['subject']) ?>"
                                data-birthday="<?= htmlspecialchars($mainStudent['birthday'] ?? '') ?>"
                                data-phone="<?= htmlspecialchars($mainStudent['phone'] ?? '') ?>"
                                data-tx1="<?= $sub['tx1'] !== null ? $sub['tx1'] : '' ?>"
                                data-tx2="<?= $sub['tx2'] !== null ? $sub['tx2'] : '' ?>"
                                data-tx3="<?= $sub['tx3'] !== null ? $sub['tx3'] : '' ?>"
                                data-tx4="<?= $sub['tx4'] !== null ? $sub['tx4'] : '' ?>"
                                data-gk="<?= $sub['gk'] !== null ? $sub['gk'] : '' ?>"
                                data-ck="<?= $sub['ck'] !== null ? $sub['ck'] : '' ?>"
                                data-avg="<?= $sub['avg_score'] !== null ? $sub['avg_score'] : '' ?>"
                                data-level="<?= $sub['level'] ?>" 
                                data-trend="<?= $sub['trend'] ?>"
                                data-momentum="<?= $sub['momentum'] ?>"
                                data-momentum-label="<?= htmlspecialchars($sub['trend_label']) ?>"
                                data-momentum-icon="<?= htmlspecialchars($sub['trend_icon']) ?>"
                                data-tier="<?= $sub['score_tier']['tier'] ?>"
                                data-tier-label="<?= htmlspecialchars($sub['score_tier']['label']) ?>"
                                data-tier-badge="<?= htmlspecialchars($sub['score_tier']['badge']) ?>"
                                data-tier-class="<?= htmlspecialchars($sub['score_tier']['class']) ?>"
                                data-overall="<?= htmlspecialchars($sub['level']) ?>"
                                data-txwarn="<?= !empty($sub['has_tx_warning']) ? '1' : '0' ?>"
                                data-diagnostic="<?= htmlspecialchars($sub['diagnostic']) ?>"
                                data-advice="<?= htmlspecialchars($sub['competency']['pedagogical_advice'] ?? '') ?>"
                                data-competency-title="<?= htmlspecialchars($sub['competency']['competency_title'] ?? '') ?>"
                                data-note="<?= htmlspecialchars($sub['teacher_note'] ?? '') ?>"
                                data-name="<?= htmlspecialchars(mb_strtolower($mainStudent['full_name'], 'UTF-8')) ?>" 
                                data-search="<?= htmlspecialchars(mb_strtolower(($mainStudent['student_code'] ?? '') . ' ' . $mainStudent['full_name'] . ' ' . $sub['class_name'] . ' ' . $sub['subject'], 'UTF-8')) ?>"
                                style="display: none;">
                                <td style="text-align: center;"></td>
                                <td style="text-align: right;">
                                    <span class="subrow-branch">↳</span>
                                </td>
                                <td>
                                    <span class="badge-subject" style="font-weight: 800; font-size: 12.5px; background: #f1f5f9; color: #1e293b;">
                                        📖 Môn <?= htmlspecialchars($sub['subject']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-class">Lớp <?= htmlspecialchars($sub['class_name']) ?></span>
                                </td>
                                <td>
                                    <span class="badge-momentum badge-momentum-<?= $sub['momentum'] ?>" style="font-size: 11.5px; padding: 3px 8px;">
                                        <?= $sub['trend_icon'] ?> <?= htmlspecialchars($sub['trend_label']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-tier <?= $sub['score_tier']['class'] ?>" style="font-size: 11px; padding: 2px 7px;">
                                        <?= htmlspecialchars($sub['score_tier']['badge']) ?>
                                    </span>
                                </td>
                                <td style="text-align: center; font-weight: 800;">
                                    <span class="avg-score-badge avg-<?= $sub['level'] ?>" style="font-size: 12.5px;">
                                        <?= ($sub['avg_score'] !== null && $sub['avg_score'] !== '') ? number_format((float)$sub['avg_score'], 1) : '-' ?>
                                    </span>
                                </td>
                                <td class="col-detail-score" style="text-align: center; font-size: 12px; font-family: var(--font-mono);">
                                    <?= implode(' · ', $subTxFormatted) ?>
                                </td>
                                <td class="col-detail-score" style="text-align: center; font-weight: 700; font-family: var(--font-mono); color: <?= ($sub['gk'] !== null && $sub['gk'] < 5.0) ? 'var(--danger)' : 'inherit' ?>;">
                                    <?= ($sub['gk'] !== null && $sub['gk'] !== '') ? number_format((float)$sub['gk'], 1) : '-' ?>
                                </td>
                                <td class="col-detail-score" style="text-align: center; font-weight: 700; font-family: var(--font-mono); color: <?= ($sub['ck'] !== null && $sub['ck'] < 5.0) ? 'var(--danger)' : 'inherit' ?>;">
                                    <?= ($sub['ck'] !== null && $sub['ck'] !== '') ? number_format((float)$sub['ck'], 1) : '-' ?>
                                </td>
                                <td style="text-align: center;">
                                    <span class="risk-dot-badge risk-<?= $sub['level'] ?>">
                                        <span class="risk-point"></span>
                                        <?= $subRiskLabel ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-size: 12px; color: #334155;">
                                        <b style="color: #4338ca;"><?= htmlspecialchars($sub['competency']['competency_title']) ?>:</b> 
                                        <?= htmlspecialchars($sub['competency']['pedagogical_advice']) ?>
                                    </div>
                                </td>
                                <td style="text-align: center; white-space: nowrap;">
                                    <div style="display: inline-flex; align-items: center; gap: 4px;">
                                        <button type="button" class="btn-open-detail" style="font-size: 11px; padding: 3px 7px;" title="Xem chi tiết môn <?= htmlspecialchars($sub['subject']) ?>">
                                            👁️ Chi tiết
                                        </button>
                                        <a href="nhap_diem.php?class_id=<?= urlencode((string)$sub['class_id']) ?>&subject=<?= urlencode($sub['subject']) ?>" 
                                           class="btn-goto-gradebook" 
                                           style="font-size: 11px; padding: 3px 7px;"
                                           title="Vào sổ điểm lớp <?= htmlspecialchars($sub['class_name']) ?> môn <?= htmlspecialchars($sub['subject']) ?>"
                                           onclick="event.stopPropagation();">
                                            📝 Sổ điểm
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- KHI CHỌN XEM RIÊNG TỪNG PHÂN MÔN CỤ THỂ -->
                    <?php $stt = 1; foreach ($students as $s): ?>
                        <?php
                            $nameParts = explode(' ', trim($s['full_name']));
                            $lastName = end($nameParts);
                            $initials = mb_strtoupper(mb_substr($lastName, 0, 2, 'UTF-8'), 'UTF-8');
                            $riskLevel = $s['level'];
                            if ($riskLevel === 'red') {
                                $gradColor = 'linear-gradient(135deg, #ef4444, #dc2626)';
                                $riskLabel = 'Nguy cấp';
                                $riskTitle = 'Học sinh ở mức nguy cấp';
                            } elseif ($riskLevel === 'yellow') {
                                $gradColor = 'linear-gradient(135deg, #f59e0b, #d97706)';
                                $riskLabel = 'Cần chú ý';
                                $riskTitle = 'Học sinh cần chú ý hoặc sa sút';
                            } elseif ($riskLevel === 'pending') {
                                $gradColor = 'linear-gradient(135deg, #94a3b8, #64748b)';
                                $riskLabel = 'Chờ điểm';
                                $riskTitle = 'Chưa có điểm đánh giá';
                            } else {
                                $gradColor = 'linear-gradient(135deg, #10b981, #059669)';
                                $riskLabel = 'Ổn định';
                                $riskTitle = 'Học tập ổn định';
                            }
                        ?>
                        <tr class="radar-row" 
                            data-student-id="<?= $s['student_id'] ?>"
                            data-student-code="<?= htmlspecialchars(!empty($s['student_code']) ? $s['student_code'] : 'HS' . $s['student_id']) ?>"
                            data-full-name="<?= htmlspecialchars($s['full_name']) ?>"
                            data-class-name="<?= htmlspecialchars($s['class_name']) ?>"
                            data-subject="<?= htmlspecialchars($s['subject']) ?>"
                            data-birthday="<?= htmlspecialchars($s['birthday'] ?? '') ?>"
                            data-phone="<?= htmlspecialchars($s['phone'] ?? '') ?>"
                            data-tx1="<?= $s['tx1'] !== null ? $s['tx1'] : '' ?>"
                            data-tx2="<?= $s['tx2'] !== null ? $s['tx2'] : '' ?>"
                            data-tx3="<?= $s['tx3'] !== null ? $s['tx3'] : '' ?>"
                            data-tx4="<?= $s['tx4'] !== null ? $s['tx4'] : '' ?>"
                            data-gk="<?= $s['gk'] !== null ? $s['gk'] : '' ?>"
                            data-ck="<?= $s['ck'] !== null ? $s['ck'] : '' ?>"
                            data-avg="<?= $s['avg_score'] !== null ? $s['avg_score'] : '' ?>"
                            data-level="<?= $s['level'] ?>" 
                            data-trend="<?= $s['trend'] ?>"
                            data-momentum="<?= $s['momentum'] ?>"
                            data-momentum-label="<?= htmlspecialchars($s['trend_label']) ?>"
                            data-momentum-icon="<?= htmlspecialchars($s['trend_icon']) ?>"
                            data-tier="<?= $s['score_tier']['tier'] ?>"
                            data-tier-label="<?= htmlspecialchars($s['score_tier']['label']) ?>"
                            data-tier-badge="<?= htmlspecialchars($s['score_tier']['badge']) ?>"
                            data-tier-class="<?= htmlspecialchars($s['score_tier']['class']) ?>"
                            data-overall="<?= htmlspecialchars($studentOverallMap[$s['student_id']]['level'] ?? '') ?>"
                            data-txwarn="<?= !empty($s['has_tx_warning']) ? '1' : '0' ?>"
                            data-diagnostic="<?= htmlspecialchars($s['diagnostic']) ?>"
                            data-advice="<?= htmlspecialchars($s['competency']['pedagogical_advice'] ?? '') ?>"
                            data-competency-title="<?= htmlspecialchars($s['competency']['competency_title'] ?? '') ?>"
                            data-note="<?= htmlspecialchars($s['teacher_note'] ?? '') ?>"
                            data-name="<?= htmlspecialchars(mb_strtolower($s['full_name'], 'UTF-8')) ?>" 
                            data-search="<?= htmlspecialchars(mb_strtolower(($s['student_code'] ?? '') . ' ' . $s['full_name'] . ' ' . $s['class_name'] . ' ' . $s['subject'] . ' ' . $s['diagnostic'] . ' ' . ($s['competency']['competency_title'] ?? '') . ' ' . ($s['teacher_note'] ?? ''), 'UTF-8')) ?>">
                            <td style="text-align: center; color: var(--text-muted); font-weight: 600;"><?= $stt++ ?></td>
                            <td>
                                <code class="mono-code" style="font-weight: 600; color: #4338ca;">
                                    <?= htmlspecialchars(!empty($s['student_code']) ? $s['student_code'] : 'HS' . $s['student_id']) ?>
                                </code>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div class="avatar-status-container">
                                        <div class="student-avatar-circle" style="background: <?= $gradColor ?>;">
                                            <?= $initials ?>
                                        </div>
                                        <span class="avatar-status-dot dot-<?= $riskLevel ?>" title="<?= $riskTitle ?>"></span>
                                    </div>
                                    <div>
                                        <div style="font-weight: 700; color: var(--text-heading); font-size: 14px;">
                                            <?= htmlspecialchars($s['full_name']) ?>
                                        </div>
                                        <?php if (!empty($s['phone'])): ?>
                                            <div class="muted small">📞 <?= htmlspecialchars($s['phone']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($s['teacher_note'])): ?>
                                            <span class="badge-has-note js-note-badge" title="<?= htmlspecialchars(mb_substr($s['teacher_note'], 0, 80)) ?>...">📝 Có ghi chú GV</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge-class">Lớp <?= htmlspecialchars($s['class_name']) ?></span>
                                <span class="badge-subject">Môn <?= htmlspecialchars($s['subject']) ?></span>
                            </td>

                            <!-- KHUNG 1: HIỆU SUẤT -->
                            <td>
                                <span class="badge-momentum badge-momentum-<?= $s['momentum'] ?>" title="<?= htmlspecialchars($s['trend_description'] ?: $s['trend_label']) ?>">
                                    <?= $s['trend_icon'] ?> <?= htmlspecialchars($s['trend_label']) ?>
                                </span>
                            </td>

                            <!-- KHUNG 2: KHUNG ĐIỂM SỐ -->
                            <td>
                                <span class="badge-tier <?= $s['score_tier']['class'] ?>" title="<?= htmlspecialchars($s['score_tier']['threshold']) ?>">
                                    <?= htmlspecialchars($s['score_tier']['badge']) ?>
                                </span>
                            </td>

                            <!-- ĐIỂM TRUNG BÌNH HỌC KỲ -->
                            <td style="text-align: center;">
                                <?php if ($s['avg_score'] !== null && $s['avg_score'] !== ''): ?>
                                    <span class="avg-score-badge avg-<?= $s['level'] ?>">
                                        <?= number_format((float)$s['avg_score'], 1) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="avg-score-badge avg-empty">-</span>
                                <?php endif; ?>
                            </td>

                            <!-- ĐIỂM TX (1-4) -->
                            <td class="col-detail-score" style="text-align: center; font-size: 13px; font-family: var(--font-mono);">
                                <?php
                                $txFormatted = [];
                                for ($i = 1; $i <= 4; $i++) {
                                    $val = $s["tx$i"];
                                    if ($val !== null && $val !== '') {
                                        $num = (float)$val;
                                        $formatted = number_format($num, 1);
                                        if ($num < 3.5) {
                                            $txFormatted[] = '<span class="tx-val-crit" title="Điểm TX nguy cấp (' . $formatted . ')">' . $formatted . '</span>';
                                        } elseif ($num < 5.0) {
                                            $txFormatted[] = '<span class="tx-val-warn" title="Điểm TX dưới trung bình (' . $formatted . ')">' . $formatted . '</span>';
                                        } else {
                                            $txFormatted[] = '<span>' . $formatted . '</span>';
                                        }
                                    } else {
                                        $txFormatted[] = '<span class="muted">-</span>';
                                    }
                                }
                                echo implode(' · ', $txFormatted);
                                ?>
                            </td>

                            <td class="col-detail-score" style="text-align: center; font-weight: 700; font-family: var(--font-mono); color: <?= ($s['gk'] !== null && $s['gk'] < 5.0) ? 'var(--danger)' : 'inherit' ?>;">
                                <?= ($s['gk'] !== null && $s['gk'] !== '') ? number_format((float)$s['gk'], 1) : '-' ?>
                            </td>
                            <td class="col-detail-score" style="text-align: center; font-weight: 700; font-family: var(--font-mono); color: <?= ($s['ck'] !== null && $s['ck'] < 5.0) ? 'var(--danger)' : 'inherit' ?>;">
                                <?= ($s['ck'] !== null && $s['ck'] !== '') ? number_format((float)$s['ck'], 1) : '-' ?>
                            </td>

                            <!-- VÙNG RỦI RO THÔNG TƯ 22 (CHẤM TRÒN GỌN GÀNG) -->
                            <td style="text-align: center;">
                                <span class="risk-dot-badge risk-<?= $s['level'] ?>">
                                    <span class="risk-point"></span>
                                    <?= $riskLabel ?>
                                </span>
                            </td>

                            <!-- NĂNG LỰC TỔNG THỂ & KHUYẾN NGHỊ SƯ PHẠM -->
                            <td>
                                <div class="competency-cell">
                                    <div class="competency-title">
                                        <?= htmlspecialchars($s['competency']['competency_title']) ?>
                                    </div>
                                    <div class="competency-advice">
                                        <?= htmlspecialchars($s['competency']['pedagogical_advice']) ?>
                                    </div>
                                    <?php if (!empty($s['diagnostic'])): ?>
                                        <div class="muted" style="font-size: 11px; margin-top: 2px;">
                                            🔍 <?= htmlspecialchars($s['diagnostic']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td style="text-align: center; white-space: nowrap;">
                                <div style="display: inline-flex; align-items: center; gap: 6px;">
                                    <button type="button" class="btn-open-detail" title="Xem chi tiết & Ghi nhận xét sư phạm">
                                        👁️ Chi tiết
                                    </button>
                                    <a href="nhap_diem.php?class_id=<?= urlencode((string)$s['class_id']) ?>&subject=<?= urlencode($s['subject']) ?>" 
                                       class="btn-goto-gradebook" 
                                       title="Vào sổ điểm lớp <?= htmlspecialchars($s['class_name']) ?> môn <?= htmlspecialchars($s['subject']) ?>"
                                       onclick="event.stopPropagation();">
                                        📝 Sổ điểm
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                <tr id="radarEmptyRow" class="radar-filter-empty-row" style="display: none;">
                    <td colspan="13" style="text-align: center; padding: 36px; color: var(--text-muted);">
                        Không tìm thấy học sinh nào phù hợp với bộ lọc hoặc từ khóa tìm kiếm.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- MODAL CHI TIẾT NĂNG LỰC HỌC TẬP & SỔ TAY NHẬN XÉT SƯ PHẠM (EXPANDABLE DETAIL MODAL) -->
<div id="competencyDetailModal" class="competency-modal-overlay" style="display: none;">
    <div class="competency-modal-card" role="dialog" aria-modal="true" aria-labelledby="modalStudentName">
        <!-- Header -->
        <div class="competency-modal-header">
            <div class="modal-header-profile">
                <div class="modal-avatar" id="modalStudentAvatar">HS</div>
                <div class="modal-name-group">
                    <h2 class="modal-student-name" id="modalStudentName">Họ và Tên Học Sinh</h2>
                    <div class="modal-meta-badges">
                        <span class="mono-code" id="modalStudentCode" style="font-weight: 700; color: #4338ca;">HS0001</span>
                        <span style="color: #cbd5e1;">•</span>
                        <span class="badge-class" id="modalStudentClass">Lớp Học</span>
                        <span class="badge-subject" id="modalStudentSubject">Môn Học</span>
                        <span id="modalStudentBirthday" class="muted small"></span>
                        <span id="modalStudentPhone" class="muted small"></span>
                    </div>
                </div>
            </div>
            <button type="button" class="modal-close-btn" id="modalCloseBtn" title="Đóng cửa sổ">✕</button>
        </div>

        <!-- Body -->
        <div class="competency-modal-body">
            <!-- Apple Unified Segmented Stat Bar: ⚡ Hiệu Suất | 📊 Khung Điểm | 🎯 Vùng Rủi Ro -->
            <div class="modal-apple-stat-bar">
                <div class="modal-apple-stat-item">
                    <span class="modal-apple-stat-label">⚡ 1. Khung Hiệu Suất</span>
                    <div class="modal-apple-stat-val" id="modalMomentumBadge">📈 Đang Tiến Bộ</div>
                </div>
                <div class="modal-apple-stat-item">
                    <span class="modal-apple-stat-label">📊 2. Khung Điểm Số</span>
                    <div class="modal-apple-stat-val" id="modalScoreTierBadge">⭐ Xuất Sắc</div>
                </div>
                <div class="modal-apple-stat-item">
                    <span class="modal-apple-stat-label">🎯 3. Phân Vùng Rủi Ro TT22</span>
                    <div class="modal-apple-stat-val" id="modalRiskBadge">🟢 Ổn định</div>
                </div>
            </div>

            <!-- Hành trình điểm số: TX1 -> TX2 -> TX3 -> TX4 -> GK -> CK -> ĐTB (Không cuộn ngang) -->
            <div>
                <div class="timeline-section-title">
                    <span>📈 Chuỗi Điểm Số & Động Năng Tiến Bộ</span>
                    <span class="muted small" style="text-transform: none; font-weight: 600;">Thông tư 22/2021/TT-BGDĐT</span>
                </div>
                <div class="score-timeline-ribbon" id="modalScoreTimeline">
                    <!-- Populated dynamically via JS -->
                </div>
            </div>

            <!-- Apple Unified Pedagogical Card -->
            <div class="apple-pedagogical-card">
                <div class="apple-pedagogy-section">
                    <div class="apple-pedagogy-title" style="color: #4338ca;">
                        <span>🔍 Chẩn Đoán Chi Tiết Từ Hệ Thống Radar:</span>
                    </div>
                    <div class="apple-pedagogy-content" id="modalDiagnosticText">
                        Đang tải chẩn đoán...
                    </div>
                </div>
                <div style="border-top: 1px dashed #e2e8f0;"></div>
                <div class="apple-pedagogy-section">
                    <div class="apple-pedagogy-title" style="color: #1d4ed8;">
                        <span>💡 Năng Lực Tổng Thể & Khuyến Nghị Sư Phạm:</span>
                    </div>
                    <div class="apple-pedagogy-content">
                        <b id="modalCompetencyTitle" style="color: #1e3a8a; display: block; margin-bottom: 4px;">Tiêu đề năng lực</b>
                        <div id="modalPedagogicalAdvice">Nội dung khuyến nghị sư phạm hành động...</div>
                    </div>
                </div>
                <div style="border-top: 1px dashed #e2e8f0;"></div>
                <details style="font-size: 12.5px; color: #475569; cursor: pointer;">
                    <summary style="font-weight: 800; color: #0f172a; outline: none; list-style: none; display: flex; align-items: center; justify-content: space-between; padding: 2px 0;">
                        <span>📘 Căn cứ Quy chế Thông tư 22/2021/TT-BGDĐT & Công thức ĐTBmhk</span>
                        <span style="font-size: 11px; color: #6366f1; font-weight: 700;">Xem quy tắc ▾</span>
                    </summary>
                    <div style="margin-top: 8px; padding: 10px 14px; background: #ffffff; border-radius: 10px; border: 1px solid #e2e8f0; line-height: 1.65; color: #334155;">
                        <div>• <b>Công thức ĐTBmhk (Điều 9 TT22):</b> <code>(Tổng ĐĐGtx + 2 × ĐĐGgk + 3 × ĐĐGck) ÷ (Số cột ĐĐGtx + 5)</code>.</div>
                        <div>• <b>Khung Điểm Số (Học lực môn):</b> <b>Tốt/Xuất sắc</b> (ĐTB ≥ 8.0) · <b>Khá</b> (6.5 ≤ ĐTB &lt; 8.0) · <b>Đạt/TB</b> (5.0 ≤ ĐTB &lt; 6.5) · <b>Chưa đạt/Cần phụ đạo</b> (ĐTB &lt; 5.0).</div>
                        <div>• <b>Phân vùng Cảnh báo sớm EduRadar:</b> <b style="color:#dc2626;">🔴 Nguy cấp</b> khi ĐTB &lt; 3.5, CK &lt; 3.0, hỏng cả GK &amp; CK (&lt; 5.0), hoặc có ≥ 2 cột TX &lt; 3.5; <b style="color:#d97706;">🟡 Cần chú ý</b> khi 3.5 ≤ ĐTB &lt; 5.0 hoặc chuỗi điểm đang sa sút.</div>
                    </div>
                </details>
            </div>

            <!-- Sổ tay ghi chú & Nhận xét sư phạm (Có thể sửa khi nhấn vô) -->
            <div class="teacher-note-section">
                <div class="teacher-note-header">
                    <div>
                        <div class="teacher-note-title">
                            <span>📝 Sổ Tay Nhận Xét Sư Phạm Của Giáo Viên</span>
                        </div>
                        <div class="teacher-note-sub">
                            Ghi chú cá nhân hóa, kế hoạch bồi dưỡng học sinh giỏi hoặc phụ đạo học sinh sa sút (lưu trực tiếp vào CSDL)
                        </div>
                    </div>
                    <span id="teacherNoteSavedTime" class="muted small"></span>
                </div>
                <textarea id="modalTeacherNoteInput" class="teacher-note-textarea" placeholder="Nhập ghi chú sư phạm, đánh giá quá trình học tập hoặc kế hoạch đồng hành cùng học sinh này..."></textarea>
                <div class="teacher-note-actions">
                    <span id="teacherNoteStatus" class="teacher-note-status" style="display: none;">
                        ✓ Đã lưu nhận xét vào CSDL
                    </span>
                    <div style="margin-left: auto; display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                        <!-- Lucian UX/UI Prompt #001 · MIT License (c) 2026 Seokwoooo · https://lucianlabs.dev/uxui-prompt/#001 -->
                        <div class="ux u001" id="u001ModalNoteSaver" data-state="idle">
                            <div class="u001-blobs" aria-hidden="true"><i></i><i></i><i></i></div>
                            <div class="u001-mag">
                                <div class="u001-float">
                                    <button class="u001-btn" type="button" id="btnSaveTeacherNote">
                                        <span class="u001-sheen" aria-hidden="true"></span>
                                        <span class="u001-txt">💾 Lưu Nhận Xét Sư Phạm</span>
                                        <span class="u001-spin" aria-hidden="true"><svg viewBox="0 0 30 30"><circle cx="15" cy="15" r="10"/></svg></span>
                                        <span class="u001-done" aria-hidden="true"><b><svg viewBox="0 0 16 16"><path d="M3.5 8.4l3 3 6-6.4"/></svg></b>Đã Lưu ✓</span>
                                    </button>
                                </div>
                            </div>
                            <span class="u001-live" aria-live="polite"></span>
                        </div>
                        <a href="#" target="_blank" id="btnModalPrintPdf" class="btn-action btn-print" style="padding: 7px 14px; border-radius: 8px; font-weight: 700; font-size: 12.5px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; background: #4f46e5; color: #ffffff;" title="In phiếu phụ đạo học sinh (PDF khổ A4)">
                            🖨️ In Phiếu Phụ Đạo (PDF)
                        </a>
                        <a href="#" target="_blank" id="btnModalExportXlsx" class="btn-action btn-excel" style="padding: 7px 14px; border-radius: 8px; font-weight: 700; font-size: 12.5px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; background: #10b981; color: #ffffff;" title="Tải file Excel phiếu phụ đạo học sinh">
                            📥 Tải Phiếu Excel (.xlsx)
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$extraScripts = '
<script src="assets/js/main.js?v=' . filemtime(__DIR__ . '/assets/js/main.js') . '"></script>
<script>
function filterGradeChips(grade, btn) {
    document.querySelectorAll(".btn-grade-pill").forEach(function(b) { b.classList.remove("active"); });
    if (btn) btn.classList.add("active");
    var chips = document.querySelectorAll("#matrixClassChipsScroll .class-chip-btn");
    chips.forEach(function(chip) {
        var g = chip.getAttribute("data-grade");
        if (grade === "all" || g === "all" || g == grade) {
            chip.style.display = "inline-flex";
        } else {
            chip.style.display = "none";
        }
    });
}
document.addEventListener("DOMContentLoaded", function() {
    var activeChip = document.querySelector("#matrixClassChipsScroll .class-chip-btn.active");
    if (activeChip) {
        var g = activeChip.getAttribute("data-grade");
        if (g && g !== "all") {
            var matchingBtn = Array.from(document.querySelectorAll(".btn-grade-pill")).find(function(b) {
                return b.textContent.indexOf("Khối " + g) !== -1;
            });
            if (matchingBtn) {
                filterGradeChips(parseInt(g), matchingBtn);
            }
        }
        activeChip.scrollIntoView({ behavior: "smooth", inline: "center", block: "nearest" });
    }
});
</script>
';
require_once __DIR__ . '/includes/footer.php';
?>
