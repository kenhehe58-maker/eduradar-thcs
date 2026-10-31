<?php
/**
 * =============================================================================
 * Hệ Thống Quản Lý Điểm & Cảnh Báo Sớm THCS (EduRadar)
 * Trang Tổng Quan Radar Cảnh Báo Sớm Học Sinh
 * 
 * HỆ THỐNG ĐÁNH GIÁ ĐA CHIỀU (DUAL-PERSPECTIVE ASSESSMENT):
 * 1. XU HƯỚNG TIẾN BỘ (Learning Trajectory / OLS) — [ƯU TIÊN THEO DÕI]:
 *    - Tăng (📈 Tiến bộ rõ rệt)
 *    - Đều (🔄 Duy trì đều): Học sinh học lực mức thấp hoặc trung bình mà đang đi đều
 *      thì ghi nhận là "Duy trì ổn định, cần bồi dưỡng nâng cao" vì điểm còn trong khuôn khổ chưa cao.
 *    - Giảm (📉 Sa sút / ⚠️ Cần can thiệp)
 * 2. BẬC HỌC LỰC (Score Tier / TT22 Bracket):
 *    - Tốt / Giỏi (⭐ 8.0+) | Khá (✨ 6.5-7.9) | Đạt / Trung bình (⚖️ 5.0-6.4) | Chưa Đạt / Thấp (⚠️ < 5.0)
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
require_once __DIR__ . '/includes/algorithms.php';

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
    } else {
        $selectedClassId = 0;
    }

    // 2. Xác định môn học theo lớp đã chọn (Giáo viên chỉ được xem môn mình dạy ở lớp đó)
    $validSubjectsForSelectedClass = ($selectedClassId > 0 && isset($teacherClassSubjectMap[$selectedClassId])) 
        ? $teacherClassSubjectMap[$selectedClassId]['subjects'] 
        : $filterSubjects;

    if ($hasGetSubject && in_array($rawGetSubject, $validSubjectsForSelectedClass, true)) {
        $selectedSubject = $rawGetSubject;
    } else {
        $selectedSubject = 'all';
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
    // 1. Xác định môn học được chọn:
    if ($hasGetSubject && (in_array($rawGetSubject, $filterSubjects, true) || $rawGetSubject === 'all')) {
        $selectedSubject = $rawGetSubject;
    } else {
        $selectedSubject = 'all';
    }

    // 2. Xác định lớp học được chọn:
    if ($hasGetClass && isset($filterClasses[$rawGetClass])) {
        $selectedClassId = $rawGetClass;
    } else {
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

// Đánh giá đa chiều: Xu hướng tiến bộ (OLS) & Bậc học lực (TT22)
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
    $mathProfile = diagnose_student_mathematical_profile($txList, $gk, $ck);
    $students[] = array_merge($r, $evaluation, ['tx_list' => $txList, 'math_profile' => $mathProfile]);
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
    if ($userRole === 'teacher') {
        $allClassScoresStmt = $pdo->prepare("
            SELECT s.id as student_id, s.full_name, sc.subject, sc.tx1, sc.tx2, sc.tx3, sc.tx4, sc.gk, sc.ck, sc.avg_score 
            FROM students s
            JOIN teacher_classes tc ON tc.class_id = s.class_id AND tc.teacher_id = ?
            JOIN scores sc ON s.id = sc.student_id AND sc.subject = tc.subject
            WHERE s.class_id = ?
        ");
        $allClassScoresStmt->execute([$userId, $selectedClassId]);
    } else {
        $allClassScoresStmt = $pdo->prepare("
            SELECT s.id as student_id, s.full_name, sc.subject, sc.tx1, sc.tx2, sc.tx3, sc.tx4, sc.gk, sc.ck, sc.avg_score 
            FROM students s
            LEFT JOIN scores sc ON s.id = sc.student_id
            WHERE s.class_id = ?
        ");
        $allClassScoresStmt->execute([$selectedClassId]);
    }
    $studentScoresGrouped = [];
    while ($row = $allClassScoresStmt->fetch(PDO::FETCH_ASSOC)) {
        $sid = (int)$row['student_id'];
        if (!isset($studentScoresGrouped[$sid])) {
            $studentScoresGrouped[$sid] = [
                'full_name' => $row['full_name'],
                'scores' => []
            ];
        }
        if ($row['subject'] !== null) {
            $subAvg = ($row['avg_score'] !== null && $row['avg_score'] !== '') ? (float)$row['avg_score'] : null;
            if ($subAvg === null) {
                $txList = array_values(array_filter([$row['tx1'], $row['tx2'], $row['tx3'], $row['tx4']], fn($v) => $v !== null && $v !== ''));
                $nTx = count($txList);
                $gk = ($row['gk'] !== null && $row['gk'] !== '') ? (float)$row['gk'] : null;
                $ck = ($row['ck'] !== null && $row['ck'] !== '') ? (float)$row['ck'] : null;
                if ($nTx > 0) {
                    if ($ck !== null) {
                        $subAvg = round((array_sum($txList) + 2 * ($gk ?? $ck) + 3 * $ck) / ($nTx + ($gk !== null ? 2 : 0) + 3), 1);
                    } elseif ($gk !== null) {
                        $subAvg = round((array_sum($txList) + 2 * $gk) / ($nTx + 2), 1);
                    }
                }
            }
            if ($subAvg !== null) {
                $studentScoresGrouped[$sid]['scores'][$row['subject']] = $subAvg;
            }
        }
    }

    $classOverallSummary['total'] = count($studentScoresGrouped);
    foreach ($studentScoresGrouped as $sid => $data) {
        $subCount = count($data['scores']);
        if ($subCount === 0) {
            $classOverallSummary['pending']++;
            $studentOverallMap[$sid] = ['level' => 'pending', 'label' => 'Chờ điểm', 'badge' => 'Chờ điểm', 'class' => 'tier-pending'];
            continue;
        }

        $scoresList = array_values($data['scores']);
        $minScore = min($scoresList);
        $avgAll = array_sum($scoresList) / $subCount;
        $count8 = count(array_filter($scoresList, fn($v) => $v >= 8.0));
        $count65 = count(array_filter($scoresList, fn($v) => $v >= 6.5));

        // Chuẩn đánh giá Thông tư 22 (Hệ 4 màu: Đỏ -> Cam -> Vàng -> Xanh)
        if ($minScore >= 6.5 && ($count8 >= 1 || $avgAll >= 8.0)) {
            $classOverallSummary['excellent']++;
            $studentOverallMap[$sid] = ['level' => 'excellent', 'label' => 'Tốt', 'badge' => 'Tốt', 'class' => 'tier-excellent', 'color' => '#16a34a'];
        } elseif ($minScore >= 5.0 && ($count65 >= 1 || $avgAll >= 6.5)) {
            $classOverallSummary['good']++;
            $studentOverallMap[$sid] = ['level' => 'good', 'label' => 'Khá', 'badge' => 'Khá', 'class' => 'tier-good', 'color' => '#d97706'];
        } elseif ($minScore >= 3.5 || $avgAll >= 5.0) {
            $classOverallSummary['average']++;
            $studentOverallMap[$sid] = ['level' => 'average', 'label' => 'Đạt', 'badge' => 'Đạt', 'class' => 'tier-average', 'color' => '#ea580c'];
        } else {
            $classOverallSummary['low']++;
            $studentOverallMap[$sid] = ['level' => 'low', 'label' => 'Chưa Đạt', 'badge' => 'Chưa Đạt', 'class' => 'tier-low', 'color' => '#dc2626'];
        }
    }
}

// =============================================================================
// =============================================================================
// PHÂN TÍCH PHỔ ĐIỂM & THẾ MẠNH SỞ TRƯỜNG TỪNG HỌC SINH (HUMANIZER PEDAGOGY)
// =============================================================================
$studentSubjectProfiles = [];
if (!empty($studentScoresGrouped)) {
    foreach ($studentScoresGrouped as $sid => $data) {
        $scores = $data['scores'];
        if (empty($scores)) {
            $studentSubjectProfiles[$sid] = [
                'scores' => [],
                'strengths' => [],
                'needs_focus' => [],
                'aptitude' => 'Chờ cập nhật điểm',
                'strengths_list' => [],
                'strengths_text' => 'Em chưa có đủ cột điểm kiểm tra để phân tích thế mạnh môn học.',
                'weaknesses_list' => [],
                'weaknesses_text' => 'Chưa ghi nhận điểm trũng.',
                'action_plan' => 'Thầy/cô vui lòng cập nhật điểm kiểm tra thường xuyên và định kỳ vào sổ điểm.',
                'profile_summary' => 'Em chưa có đủ cột điểm kiểm tra để phân tích phổ điểm các môn.'
            ];
            continue;
        }

        arsort($scores);
        $strengths = [];
        $needsFocus = [];
        foreach ($scores as $sub => $sc) {
            if ($sc >= 7.5) {
                $strengths[$sub] = $sc;
            } elseif ($sc < 6.0) {
                $needsFocus[$sub] = $sc;
            }
        }
        if (empty($strengths)) {
            $strengths = array_slice($scores, 0, min(2, count($scores)), true);
        }

        $scienceKeywords = ['toán', 'khoa học', 'khtn', 'tin', 'công nghệ'];
        $humanitiesKeywords = ['ngữ văn', 'văn', 'lịch sử', 'địa lí', 'tiếng anh', 'anh', 'gdcd'];

        $scienceScores = [];
        $humanitiesScores = [];
        foreach ($scores as $sub => $sc) {
            $subLower = mb_strtolower($sub, 'UTF-8');
            foreach ($scienceKeywords as $k) {
                if (str_contains($subLower, $k)) { $scienceScores[] = $sc; break; }
            }
            foreach ($humanitiesKeywords as $k) {
                if (str_contains($subLower, $k)) { $humanitiesScores[] = $sc; break; }
            }
        }

        $avgScience = !empty($scienceScores) ? round(array_sum($scienceScores) / count($scienceScores), 1) : null;
        $avgHumanities = !empty($humanitiesScores) ? round(array_sum($humanitiesScores) / count($humanitiesScores), 1) : null;

        $aptitude = 'Năng lực phát triển đồng đều';
        if ($avgScience !== null && $avgHumanities !== null && ($avgScience - $avgHumanities) >= 1.0) {
            $aptitude = 'Thiên hướng Tự nhiên & Tư duy Logic';
            $profileSummary = "Em có năng khiếu nổi trội ở các môn Khoa học Tự nhiên (ĐTB nhóm đạt {$avgScience}đ). Tư duy logic và giải quyết vấn đề mạch lạc; thầy/cô có thể bồi dưỡng thêm các bài tập vận dụng thực tế.";
        } elseif ($avgHumanities !== null && $avgScience !== null && ($avgHumanities - $avgScience) >= 1.0) {
            $aptitude = 'Thiên hướng Xã hội & Ngôn ngữ';
            $profileSummary = "Em có thế mạnh rõ nét ở các môn Khoa học Xã hội và Ngôn ngữ (ĐTB nhóm đạt {$avgHumanities}đ). Khả năng cảm thụ, ghi nhớ và diễn đạt tốt; thầy/cô nên khuyến khích em rèn luyện thêm kỹ năng viết và thuyết trình.";
        } elseif (min($scores) >= 8.0) {
            $aptitude = 'Năng lực xuất sắc toàn diện';
            $profileSummary = "Em nắm vững kiến thức ở tất cả các môn học, phong độ học tập rất vững vàng. Thầy/cô nên khuyến khích em tham gia câu lạc bộ học thuật hoặc bồi dưỡng học sinh giỏi.";
        } elseif (min($scores) >= 6.5) {
            $aptitude = 'Nền tảng đồng đều, học lực Khá';
            $profileSummary = "Em giữ nhịp học tập đồng đều qua các môn. Nếu chọn 1–2 môn yêu thích nhất để tập trung bồi dưỡng mũi nhọn, em hoàn toàn có khả năng bứt phá lên nhóm Tốt.";
        } else {
            $aptitude = 'Cần củng cố kiến thức nền tảng';
            $lowestSub = array_key_last($scores);
            $lowestVal = end($scores);
            $profileSummary = "Điểm số giữa các môn có sự chênh lệch. Cần ưu tiên dành thời gian củng cố môn {$lowestSub} ({$lowestVal}đ) để lấp các lỗ hổng kiến thức cốt lõi.";
        }

        // 1. Danh sách môn giỏi kèm điểm
        $strengthsList = [];
        foreach ($strengths as $stSub => $stVal) {
            $strengthsList[] = "{$stSub}: " . number_format($stVal, 1) . 'đ';
        }
        $strengthsSubjects = array_keys($strengths);

        // Đánh giá thế mạnh cụ thể
        $hasSci = false; $hasHum = false;
        foreach ($strengthsSubjects as $sSub) {
            $sLower = mb_strtolower($sSub, 'UTF-8');
            foreach ($scienceKeywords as $k) { if (str_contains($sLower, $k)) { $hasSci = true; break; } }
            foreach ($humanitiesKeywords as $k) { if (str_contains($sLower, $k)) { $hasHum = true; break; } }
        }
        if ($hasSci && $hasHum) {
            $strengthsText = 'Em có năng lực tiếp thu cân bằng tốt, vừa nhanh nhạy trong tư duy định lượng logic vừa có khả năng cảm thụ và diễn đạt ngôn ngữ mạch lạc.';
        } elseif ($hasSci) {
            $strengthsText = 'Em nổi trội về tư duy logic, phản xạ phân tích logic và khả năng giải quyết các vấn đề khoa học định lượng nhanh nhạy.';
        } elseif ($hasHum) {
            $strengthsText = 'Em có thế mạnh về cảm thụ văn học, vốn từ phong phú, ghi nhớ dữ kiện tốt và diễn đạt ý tưởng trôi chảy.';
        } else {
            $strengthsText = 'Em giữ vững phong độ làm bài ở các môn thế mạnh, chuẩn bị bài chu đáo trước khi đến lớp.';
        }

        // 2. Danh sách môn còn yếu kèm điểm
        $weaknessesList = [];
        foreach ($needsFocus as $wfSub => $wfVal) {
            $weaknessesList[] = "{$wfSub}: " . number_format($wfVal, 1) . 'đ';
        }
        if (empty($weaknessesList) && count($scores) > 2) {
            $lowestVal = min($scores);
            if ($lowestVal < 7.0) {
                foreach ($scores as $sSub => $sVal) {
                    if ($sVal == $lowestVal) {
                        $weaknessesList[] = "{$sSub}: " . number_format($sVal, 1) . 'đ';
                        break;
                    }
                }
            }
        }

        // Đánh giá điểm trũng cần lưu ý
        if (!empty($weaknessesList)) {
            $weakNames = implode(', ', $weaknessesList);
            $weaknessesText = "Điểm trũng tập trung ở: {$weakNames}. Em còn lúng túng khi gặp các dạng bài tổng hợp hoặc thiếu tự tin khi kiểm tra.";
        } else {
            $weaknessesText = 'Em không có môn nào dưới chuẩn. Phổ điểm các môn được duy trì ở mức Đạt/Khá trở lên rất an toàn.';
        }

        // 3. Kế hoạch cải thiện sư phạm hành động cụ thể (Cần cải thiện như thế nào)
        if (!empty($weaknessesList)) {
            $firstWeakName = explode(':', $weaknessesList[0])[0];
            $actionPlan = "Kế hoạch can thiệp: Giáo viên bộ môn {$firstWeakName} giao phiếu bài tập củng cố mức độ Đạt để em lấy lại căn bản; phân công bạn học khá kèm cặp 1-1; đồng thời bồi dưỡng thêm bài tập thực tế cho môn sở trường (" . implode(', ', array_slice($strengthsList, 0, 2)) . ") để khích lệ tinh thần.";
        } else {
            $actionPlan = "Định hướng phát triển: Tiếp tục giữ nhịp học tập đều đặn; giáo viên có thể bồi dưỡng các bài tập nâng cao tư duy ở môn sở trường để chuẩn bị cho các kỳ thi học sinh giỏi cấp trường.";
        }

        $studentSubjectProfiles[$sid] = [
            'scores' => $scores,
            'strengths' => $strengths,
            'needs_focus' => $needsFocus,
            'aptitude' => $aptitude,
            'strengths_list' => $strengthsList,
            'strengths_text' => $strengthsText,
            'weaknesses_list' => $weaknessesList,
            'weaknesses_text' => $weaknessesText,
            'action_plan' => $actionPlan,
            'profile_summary' => $profileSummary
        ];
    }
}

$academicCalendar = get_academic_calendar_phase();
$activeNav = 'dashboard';
$pageTitle = 'Radar Cảnh Báo Sớm & Năng Lực Học Tập';
$breadcrumbs = [
    ['title' => 'Trang Chủ', 'url' => 'welcome.php'],
    ['title' => 'Bảng Cảnh Báo Sớm', 'url' => 'index.php'],
    ['title' => 'Lớp ' . ($filterClasses[$selectedClassId] ?? 'Tất cả') . ($selectedSubject !== 'all' ? ' • Môn ' . $selectedSubject : ''), 'url' => '']
];
require_once __DIR__ . '/includes/header.php';
?>

<!-- EDEXA HERO NOTICE BOARD / WELCOME CARD (DRIBBLE SHOT INSPIRATION) -->
<div class="edexa-hero-banner" style="background: linear-gradient(135deg, #ffffff 0%, #eff6ff 100%); border: 1px solid #bfdbfe; border-radius: 20px; padding: 24px 28px; margin-bottom: 22px; box-shadow: 0 4px 20px rgba(37, 99, 235, 0.06); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
    <div style="max-width: 680px;">
        <!-- MILESTONE HEADER BANNER (DRIBBLE SAAS / APPLE CLEAN STATUS PILL) -->
        <div class="academic-milestone-banner">
            <div class="milestone-badge-row">
                <span class="milestone-dot" aria-hidden="true"></span>
                <span class="milestone-txt-year">NĂM HỌC <?= htmlspecialchars($academicCalendar['school_year']) ?></span>
                <span class="milestone-divider" aria-hidden="true">&bull;</span>
                <span class="milestone-txt-sem"><?= htmlspecialchars($academicCalendar['semester_name']) ?></span>
                <span class="milestone-phase-badge"><?= htmlspecialchars($academicCalendar['phase_title']) ?></span>
            </div>
            <div class="milestone-focus-tag">
                <span class="focus-prefix">Trọng tâm:</span>
                <span class="focus-desc"><?= htmlspecialchars($academicCalendar['calc_focus']) ?></span>
            </div>
        </div>
        <h1 style="font-size: 26px; font-weight: 800; color: var(--text-heading, #0f172a); letter-spacing: -0.03em; margin: 0 0 8px 0; line-height: 1.25;">
            Bảng theo dõi &amp; Cảnh báo sớm học lực
        </h1>
        <p style="font-size: 13.5px; color: var(--text-muted, #475569); margin: 0 0 16px 0; line-height: 1.5;">
            Theo dõi tiến trình học tập, phát hiện sớm nguy cơ sụt giảm điểm số theo <b>Thông tư 22/2021/TT-BGDĐT</b> và hỗ trợ xây dựng kế hoạch can thiệp sư phạm kịp thời.
        </p>
        <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
            <a href="nhap_diem.php<?= $navQuery ?>" class="btn primary" style="background: #2563eb; color: #ffffff; padding: 9px 20px; border-radius: 12px; font-weight: 700; font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.25);">
                <?= get_svg_icon('file-text', 16) ?>
                <span>Sổ điểm bộ môn</span>
            </a>
        </div>
    </div>
    <div style="display: flex; gap: 14px; flex-wrap: wrap;">
        <div class="hero-kpi-box" style="border-radius: 14px; padding: 14px 20px; text-align: center; min-width: 130px; box-shadow: 0 2px 6px rgba(15,23,42,0.02);">
            <span style="font-size: 11px; font-weight: 700; color: var(--text-muted, #64748b);">Tổng học sinh</span>
            <div style="font-size: 26px; font-weight: 800; color: var(--text-heading, #0f172a); font-family: 'JetBrains Mono', monospace; margin-top: 2px;">
                <?= count($students) ?>
            </div>
            <span style="font-size: 11.5px; color: #10b981; font-weight: 600;">100% Hồ sơ</span>
        </div>
        <div class="hero-kpi-box" style="border-radius: 14px; padding: 14px 20px; text-align: center; min-width: 130px; box-shadow: 0 2px 6px rgba(15,23,42,0.02);">
            <span style="font-size: 11px; font-weight: 700; color: var(--text-muted, #64748b);">Khá &amp; Tốt</span>
            <div style="font-size: 26px; font-weight: 800; color: #3b82f6; font-family: 'JetBrains Mono', monospace; margin-top: 2px;">
                <?= $stats['tier_excellent'] + $stats['tier_good'] ?>
            </div>
            <span style="font-size: 11.5px; color: #3b82f6; font-weight: 600;">
                <?= round(($stats['tier_excellent'] + $stats['tier_good']) / max(1, count($students)) * 100, 1) ?>%
            </span>
        </div>
    </div>
</div>

<!-- KHỐI TIÊU ĐỀ DASHBOARD -->
<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; gap: 16px; flex-wrap: wrap;">
    <div>
        <h2 style="font-size: 20px; font-weight: 800; color: var(--text-heading); letter-spacing: -0.4px; margin-bottom: 4px; display: flex; align-items: center; gap: 8px;">
            <?= get_svg_icon('radar', 22, '', 'color: #2563eb;') ?>
            <span>Ra-đa Giám Sát: Xu Hướng Tiến Bộ & Mức Độ Tích Lũy</span>
        </h2>
        <p class="muted small" style="margin: 0;">
            <b>Xu hướng tiến bộ</b> (Tiến bộ &bull; Duy trì &bull; Sa sút) kết hợp <b>Mức độ tích lũy</b> theo chuẩn Thông tư 22/2021/TT-BGDĐT
        </p>
    </div>
</div>

<!-- KHỐI ĐIỀU KHIỂN MA TRẬN LỚP HỌC & PHÂN MÔN (CLASS & SUBJECT MATRIX CONTROL BAR ĐƯỢC CHUYỂN LÊN ĐẦU) -->
<div class="class-subject-matrix-bar">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <?php if ($userRole === 'admin'): ?>
            <div class="matrix-role-pill admin" style="display: inline-flex; align-items: center; gap: 6px;">
                <?= get_svg_icon('settings', 15) ?>
                <span>Quản Trị Viên Trường &bull; Toàn Quyền Lớp & Phân Môn Toàn Trường (<?= count($filterClasses) ?> Lớp &bull; <?= count($filterSubjects) ?> Môn)</span>
            </div>
        <?php else: ?>
            <div class="matrix-role-pill teacher" style="display: inline-flex; align-items: center; gap: 6px;">
                <?= get_svg_icon('user', 15) ?>
                <span>Phân Công Giáo Viên: <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']) ?> &bull; Giới Hạn Theo Lớp & Môn Phụ Trách (<?= count($filterClasses) ?> Lớp &bull; <?= count($filterSubjects) ?> Môn)</span>
            </div>
        <?php endif; ?>

        <div class="muted small">
            Đang hiển thị: <b><?= count($students) ?></b> hồ sơ học sinh
            <?= $selectedClassId > 0 ? " &bull; Lớp <b>" . htmlspecialchars($filterClasses[$selectedClassId] ?? '') . "</b>" : "" ?>
            <?= $selectedSubject !== 'all' ? " &bull; Môn <b>" . htmlspecialchars($selectedSubject) . "</b>" : "" ?>
        </div>
    </div>

        <!-- 1. DẢI PHÍM CHỌN NHANH LỚP (ĐÃ CHUYỂN LÊN ĐẦU, BỎ BỚT CHỌN LỚP THỪA) -->
        <?php
            $filterClassesWithStudents = [];
            foreach ($filterClasses as $cid => $cname) {
                if (($classCounts[$cid] ?? 0) > 0) {
                    $filterClassesWithStudents[$cid] = $cname;
                }
            }
        ?>
        <div class="matrix-class-chips-container" style="margin-top: 10px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; flex-wrap: wrap; gap: 8px;">
                <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                    <span style="font-size: 11.5px; font-weight: 800; color: #475569; text-transform: uppercase; display: flex; align-items: center; gap: 4px;">
                        <?= get_svg_icon('users', 14) ?> Chọn Lớp Theo Khối:
                    </span>
                    <?php 
                        $initGrade = 'all';
                        if ($selectedClassId > 0 && isset($classGrades[$selectedClassId])) {
                            $initGrade = $classGrades[$selectedClassId];
                        }
                    ?>
                    <button type="button" class="btn-grade-pill <?= $initGrade === 'all' ? 'active' : '' ?>" onclick="filterGradeChips('all', this)">
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
                        <button type="button" class="btn-grade-pill <?= $initGrade == $g ? 'active' : '' ?>" onclick="filterGradeChips(<?= $g ?>, this)">
                            Khối <?= $g ?> (<?= $gCount ?>)
                        </button>
                    <?php endforeach; ?>
                </div>
                <div style="font-size: 11px; color: #64748b;">
                    <i>Vuốt ngang để chuyển lớp</i>
                </div>
            </div>

            <div class="matrix-class-chips" id="matrixClassChipsScroll">
                <?php if ($userRole === 'admin'): ?>
                    <a href="index.php?class_id=0&subject=<?= urlencode($selectedSubject) ?>" 
                       class="class-chip-btn <?= $selectedClassId === 0 ? 'active' : '' ?>"
                       data-grade="all">
                        Tất cả lớp <span class="class-chip-badge"><?= array_sum($classCounts) ?></span>
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
                        $isPreHidden = ($initGrade !== 'all' && $cg != $initGrade);
                    ?>
                    <a href="index.php?class_id=<?= $cId ?>&subject=<?= urlencode($chipSub) ?>" 
                       class="class-chip-btn <?= $selectedClassId === $cId ? 'active' : '' ?>"
                       data-grade="<?= $cg ?>"
                       style="<?= $isPreHidden ? 'display: none;' : '' ?>"
                       title="Xem học sinh lớp <?= htmlspecialchars($cName) ?>">
                        Lớp <?= htmlspecialchars($cName) ?>
                        <span class="class-chip-badge"><?= $classCounts[$cId] ?? 0 ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- HÀNG CHỌN PHÂN MÔN & THAO TÁC LỌC -->
        <form method="GET" action="index.php" id="matrixFilterForm" style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed var(--border-color);">
            <input type="hidden" name="class_id" value="<?= (int)$selectedClassId ?>">
            <div class="matrix-form-row" style="align-items: center; justify-content: flex-start; gap: 14px;">
                <div class="matrix-field-group" style="min-width: 220px;">
                    <label class="matrix-field-label" for="selectSubject" style="display: flex; align-items: center; gap: 6px;">
                        <?= get_svg_icon('book-open', 14) ?>
                        <span>Môn học:</span>
                    </label>
                    <?php if ($userRole === 'admin'): ?>
                        <select name="subject" id="selectSubject" class="matrix-select" onchange="this.form.submit()">
                            <option value="all" <?= $selectedSubject === 'all' ? 'selected' : '' ?>>
                                Tất cả môn học (<?= count($filterSubjects) ?> môn)
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

                <div style="display: flex; align-items: center; gap: 8px; margin-top: 18px;">
                    <button class="btn primary" type="submit" style="padding: 7px 16px; border-radius: 9px; font-weight: 700; font-size: 13px; background: #2563eb; color: #ffffff; border: none; cursor: pointer;">
                        Áp dụng
                    </button>
                    <?php if ($selectedClassId > 0 || $selectedSubject !== 'all'): ?>
                        <a href="index.php" style="font-size: 12.5px; color: var(--primary); text-decoration: none; font-weight: 700; padding: 6px 12px; border-radius: 8px; background: #eef2ff;">
                            Đặt lại bộ lọc
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
</div>

<?php if ($selectedClassId > 0 && isset($filterClasses[$selectedClassId])): ?>
    <!-- BẢNG ĐÁNH GIÁ HỌC LỰC THEO MÔN & HỌC LỰC CHUNG LỚP (CHUẨN THÔNG TƯ 22) - TECH TELEMETRY HUD -->
    <div class="card class-evaluation-panel" style="margin-bottom: 22px; padding: 18px 22px; border-radius: var(--radius-md);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 14px; margin-bottom: 16px; border-bottom: 1px solid var(--border-subtle); padding-bottom: 12px;">
            <div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span class="tech-indicator" style="font-size: 11px;">TELEMETRY</span>
                    <div>
                        <h2 style="font-size: 17px; font-weight: 800; color: var(--text-heading); margin: 0; letter-spacing: -0.02em;">
                            Đánh Giá Học Lực Lớp <?= htmlspecialchars($filterClasses[$selectedClassId]) ?>
                        </h2>
                        <span class="muted small" style="font-size: 12px;">
                            Sĩ số: <b><?= $classCounts[$selectedClassId] ?? count($students) ?></b> học sinh &bull; Niên khóa 2025–2026 &bull; Chuẩn Thông tư 22/2021/TT-BGDĐT
                        </span>
                    </div>
                </div>
            </div>

            <!-- BỘ CHỌN CÁC PHÂN MÔN CỦA LỚP NÀY ĐỂ XEM HỌC LỰC TỪNG MÔN -->
            <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                <span style="font-size: 11.5px; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">Môn xem:</span>
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
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px;">
            <!-- KHỐI 1: HỌC LỰC MÔN HIỆN TẠI -->
            <div class="eval-sub-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <b style="font-size: 13px; color: var(--text-heading); text-transform: uppercase; letter-spacing: 0.04em;">
                        Học Lực Môn: <span style="color: var(--primary);"><?= htmlspecialchars($selectedSubject === 'all' ? 'Tổng Hợp Môn' : $selectedSubject) ?></span>
                    </b>
                    <span class="badge" style="background: rgba(59, 130, 246, 0.15); color: var(--primary); font-weight: 700; border: 1px solid var(--primary-border);">
                        <?= $stats['total'] ?> bài đánh giá
                    </span>
                </div>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; text-align: center;">
                    <div onclick="filterByTier('tier:excellent')" title="Bấm để lọc xem danh sách học sinh Mức Tốt" class="eval-tier-box tier-tot">
                        <span class="eval-tier-label">MỨC TỐT</span>
                        <b class="eval-tier-val"><?= $stats['tier_excellent'] ?></b>
                        <small class="eval-tier-pct"><?= round($stats['tier_excellent'] / max(1, $stats['total']) * 100, 1) ?>%</small>
                    </div>
                    <div onclick="filterByTier('tier:good')" title="Bấm để lọc xem danh sách học sinh Mức Khá" class="eval-tier-box tier-kha">
                        <span class="eval-tier-label">MỨC KHÁ</span>
                        <b class="eval-tier-val"><?= $stats['tier_good'] ?></b>
                        <small class="eval-tier-pct"><?= round($stats['tier_good'] / max(1, $stats['total']) * 100, 1) ?>%</small>
                    </div>
                    <div onclick="filterByTier('tier:average')" title="Bấm để lọc xem danh sách học sinh Mức Đạt" class="eval-tier-box tier-dat">
                        <span class="eval-tier-label">MỨC ĐẠT</span>
                        <b class="eval-tier-val"><?= $stats['tier_average'] ?></b>
                        <small class="eval-tier-pct"><?= round($stats['tier_average'] / max(1, $stats['total']) * 100, 1) ?>%</small>
                    </div>
                    <div onclick="filterByTier('tier:low')" title="Bấm để lọc xem danh sách học sinh Chưa Đạt" class="eval-tier-box tier-chuadat">
                        <span class="eval-tier-label">CHƯA ĐẠT</span>
                        <b class="eval-tier-val"><?= $stats['tier_low'] ?></b>
                        <small class="eval-tier-pct"><?= round($stats['tier_low'] / max(1, $stats['total']) * 100, 1) ?>%</small>
                    </div>
                </div>
            </div>

            <!-- KHỐI 2: HỌC LỰC CHUNG TOÀN LỚP (CHUẨN THÔNG TƯ 22) -->
            <div class="eval-sub-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <b style="font-size: 13px; color: var(--text-heading); text-transform: uppercase; letter-spacing: 0.04em;">
                        HỌC LỰC CHUNG TOÀN LỚP (TT22)
                    </b>
                    <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; font-weight: 700; border: 1px solid rgba(16, 185, 129, 0.35);">
                        TỔNG HỢP ĐA MÔN
                    </span>
                </div>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; text-align: center;">
                    <div onclick="filterByTier('overall:excellent')" title="Bấm để lọc xem danh sách học sinh đạt Mức Tốt chung toàn lớp" class="eval-tier-box tier-tot">
                        <span class="eval-tier-label">TỐT (CHUNG)</span>
                        <b class="eval-tier-val"><?= $classOverallSummary['excellent'] ?></b>
                        <small class="eval-tier-pct"><?= round($classOverallSummary['excellent'] / max(1, $classOverallSummary['total']) * 100, 1) ?>%</small>
                    </div>
                    <div onclick="filterByTier('overall:good')" title="Bấm để lọc xem danh sách học sinh đạt Mức Khá chung toàn lớp" class="eval-tier-box tier-kha">
                        <span class="eval-tier-label">KHÁ (CHUNG)</span>
                        <b class="eval-tier-val"><?= $classOverallSummary['good'] ?></b>
                        <small class="eval-tier-pct"><?= round($classOverallSummary['good'] / max(1, $classOverallSummary['total']) * 100, 1) ?>%</small>
                    </div>
                    <div onclick="filterByTier('overall:average')" title="Bấm để lọc xem danh sách học sinh đạt Mức Đạt chung toàn lớp" class="eval-tier-box tier-dat">
                        <span class="eval-tier-label">ĐẠT (CHUNG)</span>
                        <b class="eval-tier-val"><?= $classOverallSummary['average'] ?></b>
                        <small class="eval-tier-pct"><?= round($classOverallSummary['average'] / max(1, $classOverallSummary['total']) * 100, 1) ?>%</small>
                    </div>
                    <div onclick="filterByTier('overall:low')" title="Bấm để lọc xem danh sách học sinh Chưa Đạt chung toàn lớp" class="eval-tier-box tier-chuadat">
                        <span class="eval-tier-label">CHƯA ĐẠT</span>
                        <b class="eval-tier-val"><?= $classOverallSummary['low'] ?></b>
                        <small class="eval-tier-pct"><?= round($classOverallSummary['low'] / max(1, $classOverallSummary['total']) * 100, 1) ?>%</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
// Chuẩn bị dữ liệu cho 2 vòng tròn phân đoạn SVG trên Mobile (Apple Activity Rings / Dribbble SaaS Style)
$trendSlices = [
    ['label' => 'Đang tiến bộ rõ rệt', 'count' => (int)$stats['momentum_up'], 'pct' => (float)$stats['up_pct'], 'color' => '#10b981', 'desc' => 'Điểm số có chiều hướng đi lên hoặc thi định kỳ tiến bộ hơn thường xuyên.'],
    ['label' => 'Duy trì ổn định', 'count' => (int)$stats['momentum_steady'], 'pct' => (float)$stats['steady_pct'], 'color' => '#3b82f6', 'desc' => 'Điểm số giữ nhịp đều đặn, phong độ đồng đều không có bài tụt sâu.'],
    ['label' => 'Có dấu hiệu sa sút', 'count' => (int)$stats['momentum_down'], 'pct' => (float)$stats['down_pct'], 'color' => '#ef4444', 'desc' => 'Điểm kiểm tra sụt giảm liên tục hoặc thi định kỳ sụt giảm bất thường.'],
    ['label' => 'Chờ cập nhật điểm', 'count' => (int)$stats['momentum_pending'], 'pct' => (float)$stats['pending_pct'], 'color' => '#94a3b8', 'desc' => 'Chưa có bài kiểm tra nào ghi nhận trong sổ điểm.'],
];

$goodCount = (int)($stats['tier_excellent'] + $stats['tier_good']);
$goodPctVal = $stats['total'] > 0 ? round(($goodCount / $stats['total']) * 100, 1) : 0;
$avgCount = (int)$stats['tier_average'];
$avgPctVal = $stats['total'] > 0 ? round(($avgCount / $stats['total']) * 100, 1) : 0;
$lowCount = (int)$stats['tier_low'];
$lowPctVal = $stats['total'] > 0 ? round(($lowCount / $stats['total']) * 100, 1) : 0;

$tt22Slices = [
    ['label' => 'Mức Tốt & Khá', 'count' => $goodCount, 'pct' => $goodPctVal, 'color' => '#10b981', 'desc' => 'ĐTB ≥ 6.5, phong độ vững vàng và nắm chắc kiến thức.'],
    ['label' => 'Mức Đạt (Trung bình)', 'count' => $avgCount, 'pct' => $avgPctVal, 'color' => '#f59e0b', 'desc' => '5.0 ≤ ĐTB < 6.5, đạt chuẩn kiến thức kỹ năng cơ bản.'],
    ['label' => 'Chưa Đạt (Cần hỗ trợ)', 'count' => $lowCount, 'pct' => $lowPctVal, 'color' => '#ef4444', 'desc' => 'ĐTB < 5.0 hoặc có điểm dưới ngưỡng khống chế cần bồi dưỡng.'],
];
?>

<!-- KHỐI 2 VÒNG TRÒN PHÂN ĐOẠN CHO MÀN HÌNH ĐIỆN THOẠI (DRIBBLE / APPLE ACTIVITY STYLE) -->
<div class="mobile-rings-overview">
    <?= render_segmented_donut_ring(
        $trendSlices, 
        $stats['up_pct'] . '%', 
        'Tiến Bộ', 
        'Xu Hướng Tiến Bộ', 
        'trend'
    ) ?>
    <?= render_segmented_donut_ring(
        $tt22Slices, 
        (string)$stats['total'], 
        'Học Sinh', 
        'Mức Độ Tích Lũy', 
        'tt22'
    ) ?>
</div>

<div class="desktop-stats-group">
<!-- NHÓM 1: CÁC THẺ THỐNG KÊ XU HƯỚNG TIẾN BỘ -->
<div style="margin-bottom: 24px;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
        <span style="font-size: 13px; font-weight: 700; color: #475569; display: flex; align-items: center; gap: 6px;">
            <?= get_svg_icon('trend-up', 16, '', 'color: #2563eb;') ?>
            <span>Xu hướng tiến bộ</span>
        </span>
        <span class="muted small">Tiến bộ · Duy trì · Sa sút · Chờ điểm</span>
    </div>
    <section class="radar-stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 16px;">
        <div class="card stat-card trend-up">
            <div class="card-header-row">
                <span class="card-title" style="display: flex; align-items: center; gap: 6px;">
                    <?= get_svg_icon('trend-up', 16) ?>
                    <span>Đang tiến bộ</span>
                </span>
                <?= render_circular_gauge((float)$stats['up_pct'], 'green') ?>
                <span class="card-pct trend-up"><?= $stats['up_pct'] ?>%</span>
            </div>
            <b><?= $stats['momentum_up'] ?></b>
            <span class="card-sub">Điểm số có chiều hướng đi lên qua các bài kiểm tra</span>
        </div>

        <div class="card stat-card trend-steady">
            <div class="card-header-row">
                <span class="card-title" style="display: flex; align-items: center; gap: 6px;">
                    <?= get_svg_icon('activity', 16) ?>
                    <span>Duy trì ổn định</span>
                </span>
                <?= render_circular_gauge((float)$stats['steady_pct'], 'yellow') ?>
                <span class="card-pct green"><?= $stats['steady_pct'] ?>%</span>
            </div>
            <b><?= $stats['momentum_steady'] ?></b>
            <span class="card-sub">Điểm số giữ nhịp đều đặn, không sụt giảm phong độ</span>
        </div>

        <div class="card stat-card trend-down">
            <div class="card-header-row">
                <span class="card-title" style="display: flex; align-items: center; gap: 6px;">
                    <?= get_svg_icon('trend-down', 16) ?>
                    <span>Cần chú ý sa sút</span>
                </span>
                <?= render_circular_gauge((float)$stats['down_pct'], 'red') ?>
                <span class="card-pct trend-down"><?= $stats['down_pct'] ?>%</span>
            </div>
            <b><?= $stats['momentum_down'] ?></b>
            <span class="card-sub">Điểm kiểm tra sụt giảm liên tục, cần kịp thời nhắc nhở</span>
        </div>

        <div class="card stat-card" style="border-left: 4px solid #64748b;">
            <div class="card-header-row">
                <span class="card-title" style="display: flex; align-items: center; gap: 6px;">
                    <?= get_svg_icon('clock', 16) ?>
                    <span>Chờ bổ sung điểm</span>
                </span>
                <?= render_circular_gauge((float)$stats['pending_pct'], 'gray') ?>
                <span class="card-pct" style="color: #64748b;"><?= $stats['pending_pct'] ?>%</span>
            </div>
            <b><?= $stats['momentum_pending'] ?></b>
            <span class="card-sub">Chưa có đủ cột điểm trong sổ</span>
        </div>
    </section>
</div>

<!-- NHÓM 2: THỐNG KÊ MỨC ĐỘ TÍCH LŨY HỌC LỰC (THÔNG TƯ 22) -->
<div style="margin-bottom: 28px;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
        <span style="font-size: 13px; font-weight: 700; color: #475569; display: flex; align-items: center; gap: 6px;">
            <?= get_svg_icon('bar-chart', 16, '', 'color: #059669;') ?>
            <span>Mức học lực (Thông tư 22)</span>
        </span>
        <span class="muted small">Tổng số <?= $stats['total'] ?> học sinh</span>
    </div>
    <section class="radar-stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
        <div class="card stat-card total">
            <div class="card-header-row">
                <span class="card-title" style="display: flex; align-items: center; gap: 6px;">
                    <?= get_svg_icon('users', 16) ?>
                    <span>Tổng học sinh</span>
                </span>
                <?= render_circular_gauge(100.0, 'blue') ?>
                <span class="card-pct total">100%</span>
            </div>
            <b><?= $stats['total'] ?></b>
            <span class="card-sub">Tổng số học sinh theo dõi</span>
        </div>

        <?php if ($stats['momentum_pending'] > 0): ?>
            <?php $pendingPct = round(($stats['momentum_pending'] / max(1, $stats['total'])) * 100, 1); ?>
            <div class="card stat-card" style="border-left: 4px solid #94a3b8;">
                <div class="card-header-row">
                    <span class="card-title" style="display: flex; align-items: center; gap: 6px;">
                        <?= get_svg_icon('clock', 16) ?>
                        <span>Chờ Điểm Số</span>
                    </span>
                    <?= render_circular_gauge((float)$pendingPct, 'gray') ?>
                    <span class="card-pct" style="color: #64748b; font-weight: 700;"><?= $pendingPct ?>%</span>
                </div>
                <b><?= $stats['momentum_pending'] ?></b>
                <span class="card-sub">Chưa có bài kiểm tra ghi nhận</span>
            </div>
        <?php endif; ?>

        <div class="card stat-card green">
            <div class="card-header-row">
                <span class="card-title" style="display: flex; align-items: center; gap: 6px;">
                    <?= get_svg_icon('check-circle', 16, '', 'color: #16a34a;') ?>
                    <span>Ổn định (Đạt chuẩn)</span>
                </span>
                <?= render_circular_gauge((float)$stats['green_pct'], 'green') ?>
                <span class="card-pct green"><?= $stats['green_pct'] ?>%</span>
            </div>
            <b><?= $stats['green'] ?></b>
            <span class="card-sub">Đạt chuẩn hoặc chờ điểm (ổn định)</span>
        </div>

        <div class="card stat-card yellow">
            <div class="card-header-row">
                <span class="card-title" style="display: flex; align-items: center; gap: 6px;">
                    <?= get_svg_icon('alert-circle', 16, '', 'color: #d97706;') ?>
                    <span>Cần chú ý</span>
                </span>
                <?= render_circular_gauge((float)$stats['yellow_pct'], 'yellow') ?>
                <span class="card-pct yellow"><?= $stats['yellow_pct'] ?>%</span>
            </div>
            <b><?= $stats['yellow'] ?></b>
            <span class="card-sub" title="Cần chú ý hoặc sa sút điểm">Học sinh cần theo dõi sát sao</span>
        </div>

        <div class="card stat-card red">
            <div class="card-header-row">
                <span class="card-title" style="display: flex; align-items: center; gap: 6px;">
                    <?= get_svg_icon('alert-triangle', 16, '', 'color: #dc2626;') ?>
                    <span>Nguy cấp</span>
                </span>
                <?= render_circular_gauge((float)$stats['red_pct'], 'red') ?>
                <span class="card-pct red"><?= $stats['red_pct'] ?>%</span>
            </div>
            <b><?= $stats['red'] ?></b>
            <span class="card-sub" title="Nguy cấp (ĐTB < 3.5, CK < 3.0, hoặc hỏng cả GK và CK)">Cần can thiệp sớm</span>
        </div>
    </section>
</div>
</div>

<!-- THANH CÔNG CỤ LỌC CHIP ĐA CHIỀU & TÌM KIẾM TỨC THÌ (APPLE PRO TOOLBAR) -->
<div class="radar-toolbar">
    <!-- HÀNG TRÊN: PHÂN MÔN, CHẾ ĐỘ XEM & Ô TÌM KIẾM AIRPORT SEARCH -->
    <div class="radar-toolbar-top">
        <div class="radar-toolbar-top-left">
            <!-- LỌC PHÂN MÔN TRỰC TIẾP TRÊN TOOLBAR -->
            <div class="subject-quick-filter-group">
                <span class="filter-label-sub">Môn:</span>
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
            <button type="button" class="btn-toggle-score-cols <?= ($selectedSubject !== 'all') ? 'active' : '' ?>" id="btnToggleScoreCols" onclick="toggleScoreDetailColumns()" title="Bật/Tắt hiển thị các cột điểm thành phần TX, GK, CK" style="display: inline-flex; align-items: center; gap: 6px;">
                <?= ($selectedSubject === 'all') ? get_svg_icon('eye', 14) . ' <span>Hiện Điểm TX, GK, CK</span>' : get_svg_icon('minus', 14) . ' <span>Thu Gọn (Chỉ Xem ĐTB)</span>' ?>
            </button>
        </div>

        <!-- Lucian UX/UI Prompt #034 · MIT License (c) 2026 Seokwoooo · https://lucianlabs.dev/uxui-prompt/#034 -->
        <style>
        .u034{position:relative;width:280px;max-width:100%;margin-left:auto;}
        .u034-box{position:relative;display:flex;align-items:center;gap:8px;height:38px;padding:0 12px;border-radius:12px;background:#ffffff !important;border:1.5px solid #cbd5e1;box-shadow:0 1px 3px rgba(0,0,0,.04);transition:border-color .2s ease, box-shadow .2s ease}
        .u034-box:focus-within{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.15)}
        .u034-pl{width:16px;height:16px;flex:none;fill:none;stroke:#64748b;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;transition:stroke .3s,transform .3s ease}
        .u034.is-set .u034-pl{stroke:#2563eb;transform:translateX(2px) scale(1.05)}
        .u034-wrap{position:relative;flex:1;height:100%;min-width:0;}
        .u034-ghost,.u034-in{position:absolute;inset:0;width:100%;padding:0;border:0;font:inherit;font-size:12.5px;font-weight:600;letter-spacing:-.01em;line-height:38px;background:none;white-space:pre}
        .u034-ghost{color:#94a3b8 !important;pointer-events:none;opacity:.75}
        .u034-ghost b{font-weight:inherit;color:transparent}
        .u034-in{outline:0;color:#0f172a !important;}
        .u034-in::placeholder{color:#94a3b8;opacity:1;}
        .u034-in:focus-visible{outline:none}
        .u034-code{flex:none;padding:2px 6px;border-radius:6px;background:rgba(37,99,235,.1);color:#2563eb;font-size:10.5px;font-weight:800;letter-spacing:.04em;opacity:0;transform:scale(.6);transition:opacity .2s,transform .3s ease}
        .u034.is-set .u034-code{opacity:1;transform:none}
        .u034-list{position:absolute;left:0;right:0;top:calc(100% + 6px);margin:0;padding:6px;list-style:none;border-radius:14px;background:#ffffff !important;border:1px solid #e2e8f0;box-shadow:0 12px 28px -6px rgba(0,0,0,.12),0 4px 10px -2px rgba(0,0,0,.06);max-height:280px;overflow-y:auto;z-index:999;display:none;}
        .u034-hl{position:absolute;left:6px;right:6px;top:6px;height:48px;border-radius:10px;background:#f1f5f9;transform:translateY(calc(var(--i,0)*48px));transition:transform .2s ease,opacity .2s;pointer-events:none}
        .u034-o{position:relative;display:flex;align-items:center;gap:10px;height:48px;padding:0 10px;border-radius:10px;cursor:pointer;animation:u034-in .25s ease backwards}
        @keyframes u034-in{from{opacity:0;transform:translateY(5px)}}
        .u034-o i{width:28px;height:28px;flex:none;border-radius:8px;display:grid;place-items:center;font-style:normal;font-size:10px;font-weight:800;color:#fff;background:var(--c,#2563eb)}
        .u034-o b{display:block;font-size:12.5px;font-weight:700;color:#0f172a !important;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
        .u034-o b mark{background:none;color:#2563eb;font-weight:800}
        .u034-o small{display:block;margin-top:1px;font-size:10.5px;color:#64748b !important}
        .u034-o em{margin-left:auto;font-style:normal;font-size:11px;font-weight:700;color:#334155;white-space:nowrap;font-variant-numeric:tabular-nums}
        .u034-none{padding:16px 10px;text-align:center;font-size:12px;color:#94a3b8}

        /* DARK THEME OVERRIDES FOR AIRPORT AUTOCOMPLETE */
        [data-theme="dark"] .u034-box{background:#131b2e !important;border-color:#334466 !important;box-shadow:0 1px 4px rgba(0,0,0,.3) !important;}
        [data-theme="dark"] .u034-box:focus-within{border-color:#38bdf8 !important;box-shadow:0 0 0 3px rgba(56,189,248,.25) !important;}
        [data-theme="dark"] .u034-in{color:#f8fafc !important;}
        [data-theme="dark"] .u034-in::placeholder{color:#64748b !important;}
        [data-theme="dark"] .u034-ghost{color:#64748b !important;}
        [data-theme="dark"] .u034-list{background:#131b2e !important;border-color:#23324d !important;box-shadow:0 16px 36px rgba(0,0,0,.6) !important;}
        [data-theme="dark"] .u034-o b{color:#f8fafc !important;}
        [data-theme="dark"] .u034-o small{color:#94a3b8 !important;}
        [data-theme="dark"] .u034-o em{color:#cbd5e1 !important;}
        [data-theme="dark"] .u034-hl{background:#1a253c !important;}
        </style>

        <div class="ux u034" id="u034SearchContainer">
            <label class="u034-lab" for="search" style="display:none;">Tìm kiếm học sinh</label>
            <div class="u034-box">
                <svg class="u034-pl" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
                <div class="u034-wrap">
                    <span class="u034-ghost" id="u034Ghost" aria-hidden="true"></span>
                    <input class="u034-in" type="text" id="search" aria-label="Tìm kiếm theo tên hoặc mã học sinh" role="combobox" aria-autocomplete="both" aria-expanded="false" autocomplete="off" placeholder="Tìm theo tên hoặc mã học sinh...">
                </div>
                <span class="u034-code" id="u034Code" aria-hidden="true"></span>
            </div>
            <ul class="u034-list" id="u034List" role="listbox" aria-label="Gợi ý học sinh">
                <li class="u034-hl" id="u034Highlight" role="presentation"></li>
            </ul>
        </div>
    </div>

    <!-- HÀNG DƯỚI: BỘ LỌC ĐA CHIỀU PHÂN BIỆT RÕ RÀNG XU HƯỚNG VÀ THANG ĐIỂM TT22 -->
    <div class="radar-chips" id="radarChipsBar">
        <!-- Nút Tất cả -->
        <button type="button" class="chip active chip-all" data-filter="all" title="Xem toàn bộ danh sách">
            <span>Tất cả</span>
            <span class="chip-count">(<?= $stats['total'] ?>)</span>
        </button>
        <button type="button" class="chip chip-red" data-filter="red" title="Mức Nguy cấp">
            <span class="chip-dot dot-red"></span>
            <span>Nguy cấp</span>
            <span class="chip-count">(<?= $stats['red'] ?>)</span>
        </button>
        <button type="button" class="chip chip-yellow" data-filter="yellow" title="Mức Cần chú ý">
            <span class="chip-dot dot-yellow"></span>
            <span>Cần chú ý</span>
            <span class="chip-count">(<?= $stats['yellow'] ?>)</span>
        </button>
        <button type="button" class="chip chip-green" data-filter="green" title="Mức Ổn định">
            <span class="chip-dot dot-green"></span>
            <span>Ổn định</span>
            <span class="chip-count">(<?= $stats['green'] ?>)</span>
        </button>

        <span class="toolbar-sep" aria-hidden="true"></span>

        <!-- PHÂN NHÓM 1: XU HƯỚNG TIẾN BỘ (OLS) -->
        <div class="chip-segment-group group-trend" role="group" aria-label="Lọc theo Xu hướng tiến bộ OLS">
            <span class="segment-label">Xu hướng</span>
            <button type="button" class="chip chip-trend-up" data-filter="trend:up" title="Học sinh có xu hướng điểm đi lên (k > 0)">
                <span class="chip-dot dot-trend-up"></span>
                <span>Tiến bộ</span>
                <span class="chip-count">(<?= $stats['momentum_up'] ?>)</span>
            </button>
            <button type="button" class="chip chip-trend-steady" data-filter="trend:steady" title="Học sinh duy trì phong độ đều đặn">
                <span class="chip-dot dot-trend-steady"></span>
                <span>Duy trì</span>
                <span class="chip-count">(<?= $stats['momentum_steady'] ?>)</span>
            </button>
            <button type="button" class="chip chip-trend-down" data-filter="trend:down" title="Học sinh có chiều hướng sa sút điểm (k < 0)">
                <span class="chip-dot dot-trend-down"></span>
                <span>Sa sút</span>
                <span class="chip-count">(<?= $stats['momentum_down'] ?>)</span>
            </button>
            <button type="button" class="chip chip-pending" data-filter="trend:pending" title="Chưa đủ dữ liệu chuỗi điểm để tính xu hướng">
                <span class="chip-dot dot-pending"></span>
                <span>Chờ điểm</span>
                <span class="chip-count">(<?= $stats['momentum_pending'] ?>)</span>
            </button>
        </div>

        <span class="toolbar-sep" aria-hidden="true"></span>

        <!-- PHÂN NHÓM 2: THANG ĐIỂM HỌC LỰC (THÔNG TƯ 22) -->
        <div class="chip-segment-group group-tier" role="group" aria-label="Lọc theo Thang điểm Thông tư 22">
            <span class="segment-label">Mức học lực</span>
            <button type="button" class="chip chip-tier-excellent" data-filter="tier:excellent" title="Mức Tốt (ĐTB ≥ 8.0)">
                <span class="chip-dot dot-tier-excellent"></span>
                <span>Tốt</span>
                <span class="chip-count">(<?= $stats['tier_excellent'] ?>)</span>
            </button>
            <button type="button" class="chip chip-tier-good" data-filter="tier:good" title="Mức Khá (6.5 ≤ ĐTB < 8.0)">
                <span class="chip-dot dot-tier-good"></span>
                <span>Khá</span>
                <span class="chip-count">(<?= $stats['tier_good'] ?>)</span>
            </button>
            <button type="button" class="chip chip-tier-average" data-filter="tier:average" title="Mức Đạt (5.0 ≤ ĐTB < 6.5)">
                <span class="chip-dot dot-tier-average"></span>
                <span>Đạt</span>
                <span class="chip-count">(<?= $stats['tier_average'] ?>)</span>
            </button>
            <button type="button" class="chip chip-tier-low" data-filter="tier:low" title="Mức Chưa đạt (ĐTB < 5.0)">
                <span class="chip-dot dot-tier-low"></span>
                <span>Chưa đạt</span>
                <span class="chip-count">(<?= $stats['tier_low'] ?>)</span>
            </button>
            <?php if ($stats['tier_pending'] > 0): ?>
                <button type="button" class="chip chip-pending" data-filter="tier:pending" title="Chưa có điểm tổng kết">
                    <span class="chip-dot dot-pending"></span>
                    <span>Chờ điểm</span>
                    <span class="chip-count">(<?= $stats['tier_pending'] ?>)</span>
                </button>
            <?php endif; ?>
        </div>

        <?php if ($stats['tx_warn_count'] > 0): ?>
            <span class="toolbar-sep" aria-hidden="true"></span>
            <!-- PHÂN NHÓM 3: CẢNH BÁO ĐẶC BIỆT -->
            <div class="chip-segment-group group-alert" role="group" aria-label="Cảnh báo đặc biệt">
                <button type="button" class="chip chip-tx-warn" data-filter="tx:warn" title="Có bài kiểm tra thường xuyên < 3.5 điểm">
                    <span><?= get_svg_icon('alert-triangle', 13, '', 'vertical-align: -2px; margin-right: 4px;') ?>Cảnh báo điểm TX</span>
                    <span class="chip-count">(<?= $stats['tx_warn_count'] ?>)</span>
                </button>
            </div>
        <?php endif; ?>
    </div>
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
            <div style="color: #2563eb;"><?= get_svg_icon('file-text', 26) ?></div>
            <div>
                <div style="font-size: 14.5px; font-weight: 800; color: #1e40af;">
                    Lớp <?= htmlspecialchars($filterClasses[$selectedClassId] ?? '') ?>: Đang theo dõi hồ sơ học sinh, chưa có điểm số môn [<?= htmlspecialchars($selectedSubject === 'all' ? ($filterSubjects[0] ?? 'bộ môn') : $selectedSubject) ?>]
                </div>
                <div style="font-size: 12.5px; color: #3b82f6; margin-top: 2px;">
                    Dữ liệu học sinh đã sẵn sàng. Giáo viên bộ môn có thể nhập điểm trực tiếp hoặc nạp file Excel để hệ thống tự động phân tích tiến độ học tập ngay lập tức.
                </div>
            </div>
        </div>
        <a href="nhap_diem.php?class_id=<?= $selectedClassId ?>&subject=<?= urlencode($selectedSubject === 'all' ? ($filterSubjects[0] ?? '') : $selectedSubject) ?>" class="btn primary" style="padding: 8px 18px; font-size: 13px; font-weight: 700; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
            <?= get_svg_icon('file-text', 15) ?>
            <span>Vào Sổ Điểm Nhập Điểm</span>
        </a>
    </div>
<?php endif; ?>

<!-- CHUYỂN ĐỔI CHẾ ĐỘ XEM TRÊN ĐIỆN THOẠI (THẺ HỌC SINH / BẢNG ĐIỂM) -->
<div class="mobile-view-switcher" id="mobileViewSwitcher">
    <button type="button" class="m-view-btn active" id="btnViewCards" onclick="switchMobileView('cards')">
        <?= get_svg_icon('users', 14) ?>
        <span>Dạng Thẻ Học Sinh</span>
    </button>
    <button type="button" class="m-view-btn" id="btnViewTable" onclick="switchMobileView('table')">
        <?= get_svg_icon('bar-chart', 14) ?>
        <span>Dạng Bảng Điểm</span>
    </button>
</div>

<!-- BẢNG RADAR ĐÁNH GIÁ: XU HƯỚNG TIẾN BỘ, BẬC HỌC LỰC TT22, CHẨN ĐOÁN & KHUYẾN NGHỊ -->
<div class="radar-table-wrap view-table-hidden">
    <table class="radar-table <?= ($selectedSubject === 'all') ? 'scores-hidden' : '' ?>" id="radarTable">
        <thead>
            <tr>
                <th scope="col" class="th-stt">STT</th>
                <th scope="col" class="th-code">Mã HS</th>
                <th scope="col" class="th-student">Học Sinh</th>
                <th scope="col" class="th-class">Lớp & Môn</th>
                <th scope="col" class="th-momentum">Xu hướng</th>
                <th scope="col" class="th-tier">Mức học lực</th>
                <th scope="col" class="th-score-avg">ĐTB</th>
                <th scope="col" class="col-detail-score th-score-tx">ĐĐGtx (1-4)</th>
                <th scope="col" class="col-detail-score th-score-sub">GK</th>
                <th scope="col" class="col-detail-score th-score-sub th-score-ck">CK</th>
                <th scope="col" class="th-advice">Năng Lực & Khuyến Nghị Sư Phạm</th>
                <th scope="col" class="th-actions">Thao Tác</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($students)): ?>
                <tr id="radarInitialEmptyRow" class="radar-initial-empty-row">
                    <td colspan="12" style="text-align: center; padding: 40px; color: var(--text-muted);">
                        Chưa có học sinh nào trong phạm vi được phân công. Hãy vào sổ điểm để cập nhật.
                    </td>
                </tr>
            <?php else: ?>
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
                            id="student-row-<?= $s['student_id'] ?>-<?= md5($s['subject']) ?>" data-sid="<?= $s['student_id'] ?>" data-student-id="<?= $s['student_id'] ?>"
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
                            <?= format_math_data_attributes($s) ?>
                            data-advice="<?= htmlspecialchars($s['competency']['pedagogical_advice'] ?? '') ?>"
                            data-competency-title="<?= htmlspecialchars($s['competency']['competency_title'] ?? '') ?>"
                            data-note="<?= htmlspecialchars($s['teacher_note'] ?? '') ?>"
                            data-profile='<?= htmlspecialchars(json_encode($studentSubjectProfiles[$s['student_id']] ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'
                            data-name="<?= htmlspecialchars(mb_strtolower($s['full_name'], 'UTF-8')) ?>" 
                            data-search="<?= htmlspecialchars(mb_strtolower(($s['student_code'] ?? '') . ' ' . $s['full_name'] . ' ' . $s['class_name'] . ' ' . $s['subject'] . ' ' . $s['diagnostic'] . ' ' . ($s['competency']['competency_title'] ?? '') . ' ' . ($s['teacher_note'] ?? ''), 'UTF-8')) ?>">
                            <td class="col-stt"><?= $stt++ ?></td>
                            <td class="col-code">
                                <code class="mono-code">
                                    <?= htmlspecialchars(!empty($s['student_code']) ? $s['student_code'] : 'HS' . $s['student_id']) ?>
                                </code>
                            </td>
                            <td class="col-student">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div class="avatar-status-container">
                                        <div class="student-avatar-circle">
                                            <?= $initials ?>
                                        </div>
                                        <span class="avatar-status-dot dot-<?= $riskLevel ?>" title="<?= $riskTitle ?>"></span>
                                    </div>
                                    <div style="min-width: 0; flex: 1;">
                                        <div style="font-weight: 700; color: var(--text-heading); font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($s['full_name']) ?>">
                                            <?= htmlspecialchars($s['full_name']) ?>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 4px; flex-wrap: nowrap; margin-top: 2px; overflow: hidden;">
                                            <?php if ($riskLevel === 'red'): ?>
                                                <span class="risk-pill-inline pill-red" title="Học sinh ở mức nguy cấp">Nguy cấp</span>
                                            <?php elseif ($riskLevel === 'yellow'): ?>
                                                <span class="risk-pill-inline pill-yellow" title="Học sinh cần chú ý">Cần chú ý</span>
                                            <?php endif; ?>
                                            <?php if (!empty($s['phone'])): ?>
                                                <span class="muted small"><?= htmlspecialchars($s['phone']) ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($s['teacher_note'])): ?>
                                                <span class="badge-has-note js-note-badge" title="<?= htmlspecialchars(mb_substr($s['teacher_note'], 0, 80)) ?>...">Ghi chú GV</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="col-class">
                                <div style="display: flex; flex-direction: column; gap: 2px; align-items: center;">
                                    <span class="badge-class"><?= htmlspecialchars($s['class_name']) ?></span>
                                    <span class="badge-subject">Môn <?= htmlspecialchars($s['subject']) ?></span>
                                </div>
                            </td>

                            <!-- KHUNG 1: XU HƯỚNG TIẾN BỘ -->
                            <td class="col-momentum">
                                <span class="badge-momentum badge-momentum-<?= $s['momentum'] ?>" title="<?= htmlspecialchars($s['trend_description'] ?: $s['trend_label']) ?>">
                                    <?= htmlspecialchars($s['trend_label']) ?>
                                </span>
                            </td>

                            <!-- KHUNG 2: BẬC HỌC LỰC (TT22) -->
                            <td class="col-tier">
                                <span class="badge-tier <?= $s['score_tier']['class'] ?>" title="<?= htmlspecialchars($s['score_tier']['threshold']) ?>">
                                    <?= htmlspecialchars($s['score_tier']['badge']) ?>
                                </span>
                            </td>

                            <!-- ĐIỂM TRUNG BÌNH HỌC KỲ -->
                            <td class="col-score-avg">
                                <?php if ($s['avg_score'] !== null && $s['avg_score'] !== ''): ?>
                                    <span class="avg-score-badge avg-<?= $s['level'] ?>">
                                        <?= number_format((float)$s['avg_score'], 1) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="avg-score-badge avg-empty">-</span>
                                <?php endif; ?>
                            </td>

                            <!-- ĐIỂM TX (1-4) -->
                            <td class="col-detail-score col-score-tx">
                                <div class="tx-scores-wrap">
                                <?php
                                $txFormatted = [];
                                for ($i = 1; $i <= 4; $i++) {
                                    $val = $s["tx$i"];
                                    if ($val !== null && $val !== '') {
                                        $num = (float)$val;
                                        $formatted = number_format($num, 1);
                                        if ($num < 3.5) {
                                            $txFormatted[] = '<span class="tx-num crit" title="Điểm TX nguy cấp (' . $formatted . ')">' . $formatted . '</span>';
                                        } elseif ($num < 5.0) {
                                            $txFormatted[] = '<span class="tx-num warn" title="Điểm TX dưới trung bình (' . $formatted . ')">' . $formatted . '</span>';
                                        } else {
                                            $txFormatted[] = '<span class="tx-num">' . $formatted . '</span>';
                                        }
                                    } else {
                                        $txFormatted[] = '<span class="tx-num muted">-</span>';
                                    }
                                }
                                echo implode('', $txFormatted);
                                ?>
                                </div>
                            </td>

                            <td class="col-detail-score col-score-sub" style="font-weight: 700; font-family: var(--font-mono); color: <?= ($s['gk'] !== null && $s['gk'] < 5.0) ? 'var(--danger)' : 'inherit' ?>;">
                                <?= ($s['gk'] !== null && $s['gk'] !== '') ? number_format((float)$s['gk'], 1) : '-' ?>
                            </td>
                            <td class="col-detail-score col-score-sub col-score-ck" style="font-weight: 700; font-family: var(--font-mono); color: <?= ($s['ck'] !== null && $s['ck'] < 5.0) ? 'var(--danger)' : 'inherit' ?>;">
                                <?= ($s['ck'] !== null && $s['ck'] !== '') ? number_format((float)$s['ck'], 1) : '-' ?>
                            </td>

                            <!-- NĂNG LỰC TỔNG THỂ & KHUYẾN NGHỊ SƯ PHẠM -->
                            <td class="col-advice">
                                <div class="competency-cell">
                                    <div class="competency-title">
                                        <?= htmlspecialchars($s['competency']['competency_title']) ?>
                                    </div>
                                    <div class="competency-advice">
                                        <?= htmlspecialchars($s['competency']['pedagogical_advice']) ?>
                                    </div>
                                    <?php if (!empty($s['diagnostic'])): ?>
                                        <div class="competency-diag-tag">
                                            <span class="reason-text"><?= htmlspecialchars($s['diagnostic']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td class="col-actions">
                                <div class="actions-stack">
                                    <button type="button" class="btn-open-detail" title="Xem chi tiết hồ sơ & Ghi nhận xét sư phạm" style="display: inline-flex; align-items: center; gap: 5px;">
                                        <?= get_svg_icon('eye', 13) ?>
                                        <span>Chi Tiết</span>
                                    </button>
                                    <a href="nhap_diem.php?class_id=<?= urlencode((string)$s['class_id']) ?>&subject=<?= urlencode($s['subject']) ?>" 
                                       class="btn-goto-gradebook" 
                                       title="Vào sổ điểm lớp <?= htmlspecialchars($s['class_name']) ?> môn <?= htmlspecialchars($s['subject']) ?>"
                                       onclick="event.stopPropagation();" style="display: inline-flex; align-items: center; gap: 5px;">
                                        <?= get_svg_icon('file-text', 13) ?>
                                        <span>Sổ Điểm</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <tr id="radarEmptyRow" class="radar-filter-empty-row" style="display: none;">
                    <td colspan="12" style="text-align: center; padding: 40px 16px; color: var(--text-muted);">
                        <div style="margin-bottom: 8px;"><?= get_svg_icon('search', 28, '', 'color: #94a3b8;') ?></div>
                        <div style="font-weight: 700; color: var(--text-heading); font-size: 14px; margin-bottom: 4px;">
                            Không tìm thấy học sinh nào phù hợp với bộ lọc
                        </div>
                        <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 12px;">
                            Không có học sinh nào thỏa mãn đồng thời các điều kiện đã chọn hoặc từ khóa tìm kiếm.
                        </div>
                        <button type="button" class="btn-reset-filters" onclick="window.resetAllFilters()">
                            <span>Đặt lại bộ lọc (Xem tất cả)</span>
                        </button>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- DANH SÁCH HỌC SINH TINH GỌN KÈM 2 BÓNG MÀU TRỰC QUAN CHO ĐIỆN THOẠI (APPLE MINIMALIST ROSTER) -->
<div class="mobile-student-list" id="mobileStudentList">
    <div class="mobile-list-header">
        <span class="m-list-title">Danh Sách Học Sinh (<?= count($students) ?>)</span>
        <div class="m-list-legend">
            <span class="legend-item"><span class="legend-dot dot-tier-sample"></span>Học lực</span>
            <span class="legend-item"><span class="legend-dot dot-trend-sample"></span>Tiến độ</span>
        </div>
    </div>
    <?php if (empty($students)): ?>
        <div style="text-align: center; padding: 32px 16px; background: #ffffff; border-radius: 16px; color: var(--text-muted); border: 1px solid #e2e8f0;">
            Chưa có học sinh nào trong phạm vi lớp được chọn.
        </div>
    <?php else: ?>
        <?php $mStt = 1; foreach ($students as $mItem): ?>
            <?php
                $mSid = (int)$mItem['student_id'];
                $mCode = !empty($mItem['student_code']) ? $mItem['student_code'] : ('HS' . $mSid);
                $mRisk = $mItem['level'];
                $mAvg = ($mItem['avg_score'] !== null && $mItem['avg_score'] !== '') ? (float)$mItem['avg_score'] : null;
                $mTierKey = $mItem['score_tier']['tier'] ?? 'pending';
                $mTierName = $mItem['score_tier']['label'] ?? 'Chờ điểm';
                $mTrendKey = $mItem['momentum'] ?? 'pending';
                $mTrendName = $mItem['trend_label'] ?? 'Chờ điểm';
            ?>
            <div class="m-student-row" 
                 data-sid="<?= $mSid ?>"
                 data-level="<?= $mRisk ?>"
                 data-trend="<?= $mItem['trend'] ?>"
                 data-momentum="<?= $mItem['momentum'] ?>"
                 data-tier="<?= $mTierKey ?>"
                 data-overall="<?= htmlspecialchars($studentOverallMap[$mSid]['level'] ?? '') ?>"
                 data-txwarn="<?= !empty($mItem['has_tx_warning']) ? '1' : '0' ?>"
                 data-search="<?= htmlspecialchars(mb_strtolower($mCode . ' ' . $mItem['full_name'] . ' ' . $mItem['class_name'] . ' ' . $mItem['subject'], 'UTF-8')) ?>"
                 onclick="openStudentModalFromId(<?= $mSid ?>)"
                 role="button"
                 tabindex="0"
                 title="Chạm để mở thẻ chi tiết học sinh <?= htmlspecialchars($mItem['full_name']) ?>">
                
                <span class="m-row-stt"><?= $mStt++ ?></span>
                <div class="m-row-info">
                    <div class="m-row-name"><?= htmlspecialchars($mItem['full_name']) ?></div>
                    <div class="m-row-sub">
                        <span class="m-row-code"><?= htmlspecialchars($mCode) ?></span>
                        <span>•</span>
                        <span>Lớp <?= htmlspecialchars($mItem['class_name']) ?></span>
                        <span>•</span>
                        <span>Môn <?= htmlspecialchars($mItem['subject']) ?></span>
                    </div>
                </div>

                <div class="m-row-status">
                    <!-- 2 BÓNG MÀU TRỰC QUAN (DUAL STATUS DOTS): HỌC LỰC & TIẾN ĐỘ -->
                    <div class="m-dual-dots" title="Học lực: <?= htmlspecialchars($mTierName) ?> | Tiến độ: <?= htmlspecialchars($mTrendName) ?>">
                        <span class="m-dot-pill dot-tier-<?= $mTierKey ?>" title="Học lực TT22: <?= htmlspecialchars($mTierName) ?>"></span>
                        <span class="m-dot-pill dot-trend-<?= $mTrendKey ?>" title="Xu hướng OLS: <?= htmlspecialchars($mTrendName) ?>"></span>
                    </div>
                    <span class="m-row-avg avg-<?= $mRisk ?>"><?= $mAvg !== null ? number_format($mAvg, 1) : '—' ?></span>
                    <span class="m-row-chevron">›</span>
                </div>
            </div>
        <?php endforeach; ?>
        <div id="mobileFilterEmpty" class="mobile-filter-empty" style="display: none; text-align: center; padding: 36px 16px; background: #ffffff; border-radius: 16px; border: 1px dashed #cbd5e1; margin-top: 10px;">
            <div style="margin-bottom: 8px;"><?= get_svg_icon('search', 28, '', 'color: #94a3b8;') ?></div>
            <div style="font-weight: 700; color: var(--text-heading); font-size: 14px; margin-bottom: 4px;">
                Không có học sinh phù hợp
            </div>
            <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 12px;">
                Không có học sinh nào thỏa mãn các điều kiện lọc đang chọn.
            </div>
            <button type="button" class="btn-reset-filters" onclick="window.resetAllFilters()">
                <span>Đặt lại bộ lọc (Xem tất cả)</span>
            </button>
        </div>
    <?php endif; ?>
</div>

<?php 
    $reportClassId = $selectedClassId > 0 ? $selectedClassId : (!empty($filterClasses) ? array_key_first($filterClasses) : 0);
    $reportSubject = $selectedSubject !== 'all' ? $selectedSubject : (!empty($filterSubjects) ? $filterSubjects[0] : '');
?>
<?php if ($reportClassId > 0): ?>
    <!-- KHU VỰC XUẤT BÁO CÁO NỘP BAN GIÁM HIỆU (ĐƯỢC CHUYỂN XUỐNG ĐÁY TRANG HỢP LÝ) -->
    <div class="bottom-report-export-bar" style="margin-top: 24px; margin-bottom: 24px; padding: 16px 20px; border-radius: 16px; box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
        <div>
            <div class="export-bar-title" style="font-weight: 800; font-size: 14px; display: flex; align-items: center; gap: 6px;">
                <?= get_svg_icon('printer', 16, '', 'color: #4f46e5;') ?>
                <span>Xuất Báo Cáo &amp; Dữ Liệu Nộp Ban Giám Hiệu</span>
            </div>
            <p class="export-bar-sub" style="font-size: 12px; margin: 4px 0 0 0;">
                Tải bản in A4 hoặc bảng tính Excel tổng hợp lớp <b><?= htmlspecialchars($filterClasses[$selectedClassId] ?? 'Toàn trường') ?></b>
            </p>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="in_bao_cao_bgh.php?class_id=<?= $reportClassId ?>&subject=<?= urlencode($reportSubject) ?>&format=pdf" target="_blank" class="btn-action-slip btn-slip-pdf" style="padding: 8px 14px; border-radius: 9px; font-weight: 700; font-size: 12.5px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; background: #4f46e5; color: #ffffff; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);" title="Xuất báo cáo nộp BGH (Khổ A4 chuẩn)">
                <?= get_svg_icon('printer', 14) ?>
                <span>Báo Cáo BGH (PDF)</span>
            </a>
            <a href="in_bao_cao_bgh.php?class_id=<?= $reportClassId ?>&subject=<?= urlencode($reportSubject) ?>&format=xlsx" target="_blank" class="btn-action-slip btn-slip-excel" style="padding: 8px 14px; border-radius: 9px; font-weight: 700; font-size: 12.5px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; background: #10b981; color: #ffffff; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);" title="Xuất file Excel báo cáo nộp BGH">
                <?= get_svg_icon('download', 14) ?>
                <span>Báo Cáo BGH (Excel)</span>
            </a>
        </div>
    </div>
<?php endif; ?>

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
                        <span class="mono-code modal-student-code" id="modalStudentCode">HS0001</span>
                        <span style="color: #cbd5e1;">•</span>
                        <span class="badge-class" id="modalStudentClass">Lớp Học</span>
                        <span class="badge-subject" id="modalStudentSubject">Môn Học</span>
                        <span id="modalStudentBirthday" class="muted small"></span>
                        <span id="modalStudentPhone" class="muted small"></span>
                    </div>
                </div>
            </div>
            <button type="button" class="modal-close-btn" id="modalCloseBtn" title="Đóng cửa sổ" aria-label="Đóng"><?= get_svg_icon('close', 18) ?></button>
        </div>

        <!-- Body -->
        <div class="competency-modal-body">
            <!-- Apple Unified Segmented Stat Bar: Xu Hướng | Bậc TT22 | Trạng Thái -->
            <div class="modal-apple-stat-bar">
                <div class="modal-apple-stat-item">
                    <span class="modal-apple-stat-label">Xu hướng tiến bộ</span>
                    <div class="modal-apple-stat-val" id="modalMomentumBadge">Đang tiến bộ</div>
                </div>
                <div class="modal-apple-stat-item">
                    <span class="modal-apple-stat-label">Mức học lực (TT 22)</span>
                    <div class="modal-apple-stat-val" id="modalScoreTierBadge">Mức Tốt</div>
                </div>
                <div class="modal-apple-stat-item">
                    <span class="modal-apple-stat-label">Trạng thái cảnh báo</span>
                    <div class="modal-apple-stat-val" id="modalRiskBadge">Ổn định</div>
                </div>
            </div>

            <!-- Hành trình điểm số: TX1 -> TX2 -> TX3 -> TX4 -> GK -> CK -> ĐTB (Cho phép vuốt ngang mượt mà trên điện thoại) -->
            <div>
                <div class="timeline-section-title" style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="display: flex; align-items: center; gap: 6px;">
                        <?= get_svg_icon('trend-up', 15, '', 'color: #2563eb;') ?>
                        <span>Chuỗi Điểm Số &amp; Diễn Biến Học Tập</span>
                    </span>
                    <span class="muted small" style="text-transform: none; font-weight: 600;">(Vuốt ngang để xem đủ điểm)</span>
                </div>
                <div class="score-timeline-ribbon" id="modalScoreTimeline">
                    <!-- Populated dynamically via JS -->
                </div>
            </div>

            <!-- DỰ BÁO TIẾN ĐỘ & MỤC TIÊU ĐIỂM THI SƯ PHẠM (Chuyển hóa từ OLS & Thông tư 22) -->
            <div class="apple-pedagogical-card" style="margin-top: 14px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <?= get_svg_icon('radar', 16, '', 'color: #2563eb;') ?>
                        <span class="card-heading-title" style="font-weight: 800; font-size: 13.5px;">Dự Báo Tiến Bộ &amp; Mục Tiêu Điểm Thi</span>
                        <span id="modalMathAcademicBadge" class="modal-academic-pill">Học kỳ I</span>
                    </div>
                    <span class="modal-pred-badge">Dự báo Sư phạm TT22</span>
                </div>

                <!-- 2 Hộp Phân Tích Thực Tế Dành Riêng Cho Giáo Viên: Xu Hướng & Phong Độ -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; margin-bottom: 12px;">
                    <div class="math-metric-card" id="modalSlopeCard" style="padding: 12px; border-radius: 10px;">
                        <div style="font-size: 10.5px; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 5px;">
                            <?= get_svg_icon('trend-up', 13) ?>
                            <span>Xu Hướng Học Tập</span>
                        </div>
                        <div id="modalMathSlope" style="font-size: 14px; font-weight: 800; margin-top: 4px;">Đang phân tích...</div>
                        <div id="modalMathSlopeSummary" class="math-metric-desc">Chuỗi điểm đang phân tích...</div>
                    </div>
                    <div class="math-metric-card" id="modalStabilityCard" style="padding: 12px; border-radius: 10px;">
                        <div style="font-size: 10.5px; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 5px;">
                            <?= get_svg_icon('activity', 13) ?>
                            <span>Tâm Lý &amp; Độ Ổn Định Phong Độ</span>
                        </div>
                        <div id="modalMathStability" style="font-size: 14px; font-weight: 800; margin-top: 4px;">Đang phân tích...</div>
                        <div id="modalMathStabilityDesc" class="math-metric-desc">Mức độ phân tán phong độ</div>
                    </div>
                </div>

                <!-- Target CK Projection Cards (Không bịa đặt kế hoạch hành động) -->
                <div class="math-target-card" style="padding: 12px; border-radius: 10px;">
                    <div id="modalMathTargetPrompt" style="font-size: 11.5px; font-weight: 700; margin-bottom: 8px; display: flex; align-items: center; gap: 5px;">
                        <?= get_svg_icon('radar', 13) ?>
                        <span>Điểm Cuối kỳ (CK) cần đạt để đạt mục tiêu:</span>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 8px;" id="modalTargetCardsGrid">
                        <!-- Populated dynamically via JS -->
                    </div>
                </div>
            </div>

            <!-- KHU VỰC PHÂN TÍCH PHỔ ĐIỂM & THẾ MẠNH SỞ TRƯỜNG NĂNG LỰC TỪNG MÔN -->
            <div class="apple-pedagogical-card" id="modalSubjectProfileCard" style="margin-top: 14px; display: none;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <?= get_svg_icon('award', 16, '', 'color: #4f46e5;') ?>
                        <span class="card-heading-title" style="font-weight: 800; font-size: 13.5px;">Phổ điểm môn học &amp; Năng lực nổi bật</span>
                    </div>
                    <span id="modalAptitudeBadge" class="modal-aptitude-badge">
                        Thiên hướng năng lực
                    </span>
                </div>

                <!-- Thanh phổ điểm từng môn học mini -->
                <div id="modalSubjectBarsGrid" class="modal-subject-bars-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 8px; margin-bottom: 12px;">
                    <!-- Populated dynamically via JS -->
                </div>

                <!-- Hộp nhận định thế mạnh & điểm trũng (Chỉ ghi nhận tình trạng thực tế) -->
                <div class="modal-profile-sections-wrapper" style="display: flex; flex-direction: column; gap: 10px;">
                    <!-- 1. Môn học sở trường & thế mạnh -->
                    <div class="profile-feedback-box strength-box" style="border-radius: 12px; padding: 12px 14px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 6px; margin-bottom: 6px;">
                            <div class="strength-heading" style="display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700;">
                                <?= get_svg_icon('award', 14, '', 'color: #16a34a;') ?>
                                <span>Môn có kết quả nổi bật:</span>
                            </div>
                            <div id="modalStrengthsPills" style="display: flex; gap: 4px; flex-wrap: wrap;"></div>
                        </div>
                        <p id="modalStrengthsText" class="strength-text" style="font-size: 12px; line-height: 1.5; margin: 0;"></p>
                    </div>

                    <!-- 2. Môn còn yếu & điểm trũng cần lưu ý -->
                    <div class="profile-feedback-box weakness-box" style="border-radius: 12px; padding: 12px 14px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 6px; margin-bottom: 6px;">
                            <div class="weakness-heading" style="display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700;">
                                <?= get_svg_icon('alert-triangle', 14, '', 'color: #d97706;') ?>
                                <span>Môn cần hỗ trợ thêm:</span>
                            </div>
                            <div id="modalWeaknessesPills" style="display: flex; gap: 4px; flex-wrap: wrap;"></div>
                        </div>
                        <p id="modalWeaknessesText" class="weakness-text" style="font-size: 12px; line-height: 1.5; margin: 0;"></p>
                    </div>
                </div>
            </div>

            <!-- Apple Unified Pedagogical Card (Chỉ ghi tình trạng thực tế) -->
            <div class="apple-pedagogical-card">
                <div class="apple-pedagogy-section">
                    <div class="apple-pedagogy-title" style="display: flex; align-items: center; gap: 6px;">
                        <?= get_svg_icon('search', 14) ?>
                        <span>Chẩn đoán chuyên môn:</span>
                    </div>
                    <div class="apple-pedagogy-content" id="modalDiagnosticText">
                        Đang tải chẩn đoán...
                    </div>
                </div>
                <div class="modal-section-divider"></div>
                <div class="apple-pedagogy-section">
                    <div class="apple-pedagogy-title" style="display: flex; align-items: center; gap: 6px;">
                        <?= get_svg_icon('award', 14) ?>
                        <span>Tình Trạng Năng Lực Học Tập Hiện Tại:</span>
                    </div>
                    <div class="apple-pedagogy-content">
                        <b id="modalCompetencyTitle" style="display: block; margin-bottom: 4px;">Tiêu đề năng lực</b>
                        <div id="modalPedagogicalAdvice">Tình trạng học tập...</div>
                    </div>
                </div>
            </div>

            <!-- Sổ Can Thiệp Sư Phạm & Lịch Sử Sửa Điểm (Audit Trail) -->
            <div class="apple-pedagogical-card" style="border-radius: 14px; padding: 14px 16px; margin-bottom: 16px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                    <div class="audit-trail-heading" style="display: flex; align-items: center; gap: 7px; font-size: 13px; font-weight: 800;">
                        <?= get_svg_icon('clock', 14, '', 'color: #4f46e5;') ?>
                        <span>Lịch Sử Chỉnh Sửa Điểm (Audit Trail)</span>
                    </div>
                    <span class="modal-audit-badge">Nhật ký</span>
                </div>
                <div id="modalAuditHistoryContainer" style="font-size: 12px; line-height: 1.5;">
                    Đang tải lịch sử chỉnh sửa điểm...
                </div>
            </div>

            <!-- Sổ tay ghi chú & Nhận xét sư phạm (Thầy/Cô ghi tình trạng thực tế) -->
            <div class="teacher-note-section">
                <div class="teacher-note-header">
                    <div>
                        <div class="teacher-note-title" style="display: flex; align-items: center; gap: 6px;">
                            <?= get_svg_icon('edit', 15, '', 'color: #2563eb;') ?>
                            <span>Sổ Tay Nhận Xét Sư Phạm Của Giáo Viên</span>
                        </div>
                        <div class="teacher-note-sub">
                            Ghi nhận tình trạng học tập thực tế và diễn biến điểm số của học sinh (Lưu trực tiếp vào CSDL)
                        </div>
                    </div>
                    <span id="teacherNoteSavedTime" class="muted small"></span>
                </div>
                <textarea id="modalTeacherNoteInput" class="teacher-note-textarea" aria-label="Nhập ghi chú sư phạm, đánh giá quá trình học tập hoặc kế hoạch đồng hành cùng học sinh" placeholder="Nhập ghi chú sư phạm, đánh giá quá trình học tập hoặc kế hoạch đồng hành cùng học sinh này..."></textarea>
                <div class="teacher-note-actions">
                    <span id="teacherNoteStatus" class="teacher-note-status" style="display: none;">
                        Đã lưu nhận xét
                    </span>
                    <div style="margin-left: auto; display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                        <!-- Lucian UX/UI Prompt #001 · MIT License (c) 2026 Seokwoooo · https://lucianlabs.dev/uxui-prompt/#001 -->
                        <div class="ux u001" id="u001ModalNoteSaver" data-state="idle">
                            <div class="u001-blobs" aria-hidden="true"><i></i><i></i><i></i></div>
                            <div class="u001-mag">
                                <div class="u001-float">
                                    <button class="u001-btn" type="button" id="btnSaveTeacherNote">
                                        <span class="u001-sheen" aria-hidden="true"></span>
                                        <span class="u001-txt">Lưu nhận xét</span>
                                        <span class="u001-spin" aria-hidden="true"><svg viewBox="0 0 30 30"><circle cx="15" cy="15" r="10"/></svg></span>
                                        <span class="u001-done" aria-hidden="true"><b><svg viewBox="0 0 16 16"><path d="M3.5 8.4l3 3 6-6.4"/></svg></b>Đã lưu</span>
                                    </button>
                                </div>
                            </div>
                            <span class="u001-live" aria-live="polite"></span>
                        </div>
                        <a href="#" target="_blank" id="btnModalPrintPdf" class="btn-action btn-print" style="padding: 7px 14px; border-radius: 8px; font-weight: 700; font-size: 12.5px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; background: #4f46e5; color: #ffffff;" title="In phiếu phụ đạo học sinh (PDF khổ A4)">
                            <?= get_svg_icon('printer', 14) ?>
                            <span>In phiếu PDF</span>
                        </a>
                        <a href="#" target="_blank" id="btnModalExportXlsx" class="btn-action btn-excel" style="padding: 7px 14px; border-radius: 8px; font-weight: 700; font-size: 12.5px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; background: #10b981; color: #ffffff;" title="Tải file Excel phiếu phụ đạo học sinh">
                            <?= get_svg_icon('download', 14) ?>
                            <span>Tải bảng Excel</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- BOTTOM SHEET MODAL GIẢI THÍCH MÀU SẮC (CHO ĐIỆN THOẠI) -->
<div class="ring-help-sheet-backdrop" id="ringHelpBackdrop" onclick="closeRingHelp()"></div>
<div class="ring-help-sheet" id="ringHelpSheet" role="dialog" aria-modal="true" aria-labelledby="ringHelpSheetTitle">
    <div class="ring-sheet-handle"></div>
    <div class="ring-sheet-header">
        <div>
            <h3 id="ringHelpSheetTitle" class="ring-sheet-title">Giải Thích Màu Sắc</h3>
            <p id="ringHelpSheetSub" style="margin: 2px 0 0 0; font-size: 11.5px; color: #64748b;"></p>
        </div>
        <button type="button" class="btn-close-sheet" onclick="closeRingHelp()" aria-label="Đóng popup"><?= get_svg_icon('close', 16) ?></button>
    </div>
    <div class="ring-sheet-body" id="ringHelpSheetContent">
        <!-- Nội dung giải thích màu sắc do JavaScript tự động render -->
    </div>
</div>

<script id="ringHelpData" type="application/json">
<?= json_encode([
    'trend' => [
        'title' => 'Xu hướng tiến bộ',
        'sub' => 'Đo lường nhịp độ học tập qua các cột điểm theo phương pháp OLS',
        'items' => $trendSlices
    ],
    'tt22' => [
        'title' => 'Mức học lực (Thông tư 22)',
        'sub' => 'Phân loại kết quả học tập theo Thông tư 22/2021/TT-BGDĐT',
        'items' => $tt22Slices
    ]
], JSON_UNESCAPED_UNICODE) ?>
</script>

<?php
$extraScripts = '
<script>
window.openStudentModalFromId = function(studentId) {
    const row = document.querySelector(\'tr.radar-row[data-sid="\' + studentId + \'"]\');
    if (row) {
        const btn = row.querySelector(\'.btn-open-detail\');
        if (btn) btn.click();
    }
};
</script>
<script src="assets/js/main.js?v=' . filemtime(__DIR__ . '/assets/js/main.js') . '"></script>
<script>
function filterGradeChips(grade, btn) {
    document.querySelectorAll(".btn-grade-pill").forEach(function(b) { b.classList.remove("active"); });
    if (btn) {
        btn.classList.add("active");
        if (window.gsap) {
            gsap.fromTo(btn, { scale: 0.92 }, { scale: 1, duration: 0.25, ease: "back.out(2)", clearProps: "transform" });
        }
    }
    var chips = document.querySelectorAll("#matrixClassChipsScroll .class-chip-btn");
    var visibleChips = [];
    chips.forEach(function(chip) {
        var g = chip.getAttribute("data-grade");
        if (grade === "all" || g === "all" || g == grade) {
            chip.style.display = "inline-flex";
            visibleChips.push(chip);
        } else {
            chip.style.display = "none";
        }
    });
    if (window.gsap && visibleChips.length > 0) {
        gsap.killTweensOf(visibleChips);
        gsap.fromTo(visibleChips,
            { opacity: 0, scale: 0.9, y: 6 },
            { opacity: 1, scale: 1, y: 0, duration: 0.26, stagger: 0.015, ease: "back.out(1.6)", clearProps: "transform" }
        );
    }
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
