<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/xlsx_helper.php';

require_login();

$pdo = db();
$realUserId = (int)$_SESSION['user_id'];
$realUserRole = $_SESSION['role'] ?? 'teacher';
$currentTeacherName = $_SESSION['full_name'] ?? $_SESSION['username'];

// KHỬ GIẢ MẠO: Chỉ tài khoản Admin mới được sử dụng tham số ?teacher_id
$actingTeacherId = 0;
if ($realUserRole === 'admin') {
    if (isset($_GET['teacher_id']) && (int)$_GET['teacher_id'] > 0) {
        $actingTeacherId = (int)$_GET['teacher_id'];
        $tCheck = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
        $tCheck->execute([$actingTeacherId]);
        $tObj = $tCheck->fetch();
        if ($tObj) {
            $currentTeacherName = $tObj['full_name'] . " (Admin đang kiểm tra)";
        }
    }
} else {
    // Giáo viên không được phép đổi teacher_id
    if (isset($_GET['teacher_id']) && (int)$_GET['teacher_id'] !== $realUserId) {
        http_response_code(403);
        log_action($realUserId, $_SESSION['username'], 'Truy cập trái phép bị chặn', "Giáo viên [{$_SESSION['username']}] cố tình giả mạo tham số teacher_id=" . (int)$_GET['teacher_id']);
        render_forbidden_page("Bạn không có quyền truy cập thông qua mã định danh giáo viên khác.");
        exit;
    }
}

$msg = '';
$errorMsg = '';

// Lấy danh sách lớp và môn được phân công
$targetTeacherId = ($realUserRole === 'admin' && $actingTeacherId > 0) ? $actingTeacherId : $realUserId;

if ($realUserRole === 'admin' && $actingTeacherId === 0) {
    // Admin truy cập trực tiếp: toàn quyền chọn bất kỳ lớp nào trong trường
    $classesQuery = $pdo->query("
        SELECT c.id, c.name, c.grade,
               (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id) as student_count
        FROM classes c 
        ORDER BY (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id) DESC, c.grade ASC, c.name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $assignedClasses = [];
    $teacherSubjects = [];
    foreach ($classesQuery as $c) {
        $assignedClasses[$c['id']] = [
            'id' => (int)$c['id'],
            'name' => $c['name'],
            'grade' => (int)$c['grade'],
            'student_count' => (int)$c['student_count'],
            'subjects' => ['Toán', 'Ngữ Văn', 'Tiếng Anh', 'KHTN', 'Lịch Sử', 'Tin Học']
        ];
    }
    foreach (['Toán', 'Ngữ Văn', 'Tiếng Anh', 'KHTN', 'Lịch Sử', 'Tin Học'] as $sub) {
        $teacherSubjects[$sub] = $assignedClasses;
    }
} else {
    // Giáo viên bộ môn (hoặc Admin thanh tra giáo viên): lọc chuẩn theo phân công chuyên môn VnEdu
    $stmt = $pdo->prepare("
        SELECT c.id, c.name, c.grade, tc.subject,
               (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id) as student_count
        FROM teacher_classes tc 
        JOIN classes c ON tc.class_id = c.id 
        WHERE tc.teacher_id = ?
        ORDER BY (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id) DESC, c.grade ASC, c.name ASC
    ");
    $stmt->execute([$targetTeacherId]);
    $rawAssignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $assignedClasses = [];
    $teacherSubjects = [];
    foreach ($rawAssignments as $row) {
        $cid = (int)$row['id'];
        $sub = $row['subject'];
        if (!isset($assignedClasses[$cid])) {
            $assignedClasses[$cid] = [
                'id' => $cid,
                'name' => $row['name'],
                'grade' => (int)$row['grade'],
                'student_count' => (int)$row['student_count'],
                'subjects' => []
            ];
        }
        if (!in_array($sub, $assignedClasses[$cid]['subjects'])) {
            $assignedClasses[$cid]['subjects'][] = $sub;
        }

        if (!isset($teacherSubjects[$sub])) {
            $teacherSubjects[$sub] = [];
        }
        $teacherSubjects[$sub][$cid] = [
            'id' => $cid,
            'name' => $row['name'],
            'grade' => (int)$row['grade'],
            'student_count' => (int)$row['student_count']
        ];
    }
}

// 1. Xác định môn học được chọn (Cô lập ngữ cảnh theo từng tài khoản):
$userNavCtx = get_user_nav_context($targetTeacherId);
$selectedSubject = '';
if (isset($_GET['subject']) && trim((string)$_GET['subject']) !== '') {
    $selectedSubject = trim((string)$_GET['subject']);
} elseif ($userNavCtx['subject'] !== '' && isset($teacherSubjects[$userNavCtx['subject']])) {
    $selectedSubject = $userNavCtx['subject'];
} elseif (!empty($teacherSubjects)) {
    $sessKey = 'last_selected_sub_' . $targetTeacherId;
    if (isset($_SESSION[$sessKey]) && isset($teacherSubjects[$_SESSION[$sessKey]])) {
        $selectedSubject = $_SESSION[$sessKey];
    } else {
        $selectedSubject = (string)array_key_first($teacherSubjects);
    }
} else {
    $selectedSubject = 'Toán';
}
if (!empty($selectedSubject)) {
    $_SESSION['last_selected_sub_' . $targetTeacherId] = $selectedSubject;
}

// 2. Xác định danh sách lớp thuộc môn học được chọn:
$classesInCurrentSubject = $teacherSubjects[$selectedSubject] ?? $assignedClasses;

// 3. Xác định lớp được chọn:
$selectedClassId = 0;
if (isset($_GET['class_id']) && (int)$_GET['class_id'] > 0) {
    // Nếu truyền class_id tường minh từ URL, giữ nguyên để rào chắn bảo mật BOLA kiểm tra
    $selectedClassId = (int)$_GET['class_id'];
} elseif ($userNavCtx['class_id'] > 0 && isset($classesInCurrentSubject[$userNavCtx['class_id']])) {
    $selectedClassId = $userNavCtx['class_id'];
} elseif (!empty($classesInCurrentSubject)) {
    // Ưu tiên tự động chọn lớp ĐẦU TIÊN CÓ HỌC SINH của môn này
    foreach ($classesInCurrentSubject as $cid => $cData) {
        if ($cData['student_count'] > 0) {
            $selectedClassId = $cid;
            break;
        }
    }
    if ($selectedClassId === 0) {
        $selectedClassId = (int)array_key_first($classesInCurrentSubject);
    }
} elseif (!empty($assignedClasses)) {
    foreach ($assignedClasses as $cid => $cData) {
        if ($cData['student_count'] > 0) {
            $selectedClassId = $cid;
            break;
        }
    }
    if ($selectedClassId === 0) {
        $selectedClassId = (int)array_key_first($assignedClasses);
    }
}

if ($selectedClassId > 0 && !empty($selectedSubject)) {
    set_user_nav_context($targetTeacherId, $selectedClassId, $selectedSubject);
}

// Môn học hợp lệ của lớp được chọn
$availableSubjectsForClass = $assignedClasses[$selectedClassId]['subjects'] ?? (!empty($selectedSubject) ? [$selectedSubject] : ['Toán']);
$myClasses = array_values($assignedClasses);

// RÀO CHẮN BẢO MẬT & TỰ ĐỘNG ĐỒNG BỘ PHÂN CÔNG (BOLA/IDOR PREVENTION)
if ($realUserRole !== 'admin') {
    if (empty($assignedClasses)) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            http_response_code(403);
            log_action($realUserId, $_SESSION['username'], 'Truy cập trái phép bị chặn', 'Giáo viên chưa có phân công cố gắng gửi yêu cầu POST vào sổ điểm.');
            render_forbidden_page('Tài khoản chưa được phân công giảng dạy.');
            exit;
        }
    } else {
        // Nếu có tham số GET tường minh từ URL: Kiểm tra phân quyền BOLA nghiêm ngặt
        $hasExplicitClass = isset($_GET['class_id']) && (int)$_GET['class_id'] > 0;
        $hasExplicitSubject = isset($_GET['subject']) && trim((string)$_GET['subject']) !== '';

        if ($hasExplicitClass && !isset($assignedClasses[(int)$_GET['class_id']])) {
            http_response_code(403);
            log_action($realUserId, $_SESSION['username'], 'Truy cập trái phép bị chặn', "Giáo viên cố gắng truy cập lớp không được phân công: ID [{$_GET['class_id']}].");
            render_forbidden_page('Bạn không được phân công giảng dạy lớp học này.');
            exit;
        }

        if ($hasExplicitSubject && (!isset($assignedClasses[$selectedClassId]) || !in_array(trim((string)$_GET['subject']), $assignedClasses[$selectedClassId]['subjects'], true))) {
            http_response_code(403);
            log_action($realUserId, $_SESSION['username'], 'Truy cập trái phép bị chặn', "Giáo viên cố gắng truy cập môn [{$_GET['subject']}] không được phân công tại lớp ID [{$selectedClassId}].");
            render_forbidden_page("Bạn không được phân công giảng dạy môn [{$_GET['subject']}] tại lớp học này.");
            exit;
        }

        // Nếu truy cập tự nhiên không truyền tham số: Tự động chọn lớp & môn hợp lệ
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && (!$hasExplicitClass || !$hasExplicitSubject)) {
            if ($selectedClassId <= 0 || !isset($assignedClasses[$selectedClassId])) {
                $selectedClassId = 0;
                foreach ($assignedClasses as $cid => $cData) {
                    if ($cData['student_count'] > 0) {
                        $selectedClassId = $cid;
                        break;
                    }
                }
                if ($selectedClassId === 0) {
                    $selectedClassId = (int)array_key_first($assignedClasses);
                }
            }

            $validSubjects = $assignedClasses[$selectedClassId]['subjects'] ?? [];
            if (!in_array($selectedSubject, $validSubjects, true)) {
                $selectedSubject = !empty($validSubjects) ? $validSubjects[0] : '';
            }

            set_user_nav_context($targetTeacherId, $selectedClassId, $selectedSubject);
        }

        if ($selectedClassId <= 0 || $selectedSubject === '') {
            http_response_code(403);
            log_action($realUserId, $_SESSION['username'], 'Truy cập trái phép bị chặn', 'Yêu cầu không hợp lệ (thiếu lớp hoặc môn).');
            render_forbidden_page('Yêu cầu không hợp lệ.');
            exit;
        }
        require_teacher_assignment($realUserId, $selectedClassId, $selectedSubject);
    }
} elseif ($actingTeacherId > 0 && $_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['export'])) {
    log_action($realUserId, $_SESSION['username'], 'Admin kiểm tra sổ điểm', "Admin đang thanh tra sổ điểm lớp ID [$selectedClassId] môn [$selectedSubject] của giáo viên ID [$actingTeacherId].");
}

// Lấy tên lớp đang chọn
$curClassName = $assignedClasses[$selectedClassId]['name'] ?? '';
if ($curClassName === '' && $selectedClassId > 0) {
    $cStmt = $pdo->prepare("SELECT name FROM classes WHERE id = ?");
    $cStmt->execute([$selectedClassId]);
    $curClassName = (string)$cStmt->fetchColumn() ?: "ID $selectedClassId";
}

// =============================================================================
// XỬ LÝ XUẤT EXCEL SỔ ĐIỂM (.XLSX)
// =============================================================================
if ((isset($_GET['export']) && $_GET['export'] === 'xlsx') || 
    (isset($_POST['action']) && $_POST['action'] === 'export_xlsx')) {
    
    if ($realUserRole !== 'admin') {
        require_teacher_assignment($realUserId, $selectedClassId, $selectedSubject);
    }

    $expStmt = $pdo->prepare("
        SELECT s.id, s.student_code, s.full_name, s.birthday, s.gender, s.phone,
               sc.tx1, sc.tx2, sc.tx3, sc.tx4, sc.gk, sc.ck, sc.avg_score
        FROM students s
        LEFT JOIN scores sc ON s.id = sc.student_id AND sc.subject = ?
        WHERE s.class_id = ?
        ORDER BY s.full_name ASC
    ");
    $expStmt->execute([$selectedSubject, $selectedClassId]);
    $studentRows = $expStmt->fetchAll();

    $exportData = [];
    $exportData[] = ['STT', 'Mã HS', 'Họ và tên', 'Ngày sinh', 'Giới tính', 'Số điện thoại', 'ĐĐGtx1', 'ĐĐGtx2', 'ĐĐGtx3', 'ĐĐGtx4', 'ĐĐGgk', 'ĐĐGck', 'ĐTBmhk'];

    $stt = 1;
    foreach ($studentRows as $s) {
        $sId = (int)$s['id'];
        $code = !empty($s['student_code']) ? $s['student_code'] : ('HS' . str_pad((string)$sId, 4, '0', STR_PAD_LEFT));
        $bday = !empty($s['birthday']) ? date('d/m/Y', strtotime($s['birthday'])) : '';
        $gender = $s['gender'] ?? '';
        $phone = $s['phone'] ?? '';
        $tx1 = $s['tx1'] !== null ? (float)$s['tx1'] : '';
        $tx2 = $s['tx2'] !== null ? (float)$s['tx2'] : '';
        $tx3 = $s['tx3'] !== null ? (float)$s['tx3'] : '';
        $tx4 = $s['tx4'] !== null ? (float)$s['tx4'] : '';
        $gk  = $s['gk']  !== null ? (float)$s['gk']  : '';
        $ck  = $s['ck']  !== null ? (float)$s['ck']  : '';
        $avg = $s['avg_score'] !== null ? (float)$s['avg_score'] : '';

        $exportData[] = [
            $stt++,
            $code,
            $s['full_name'],
            $bday,
            $gender,
            $phone,
            $tx1,
            $tx2,
            $tx3,
            $tx4,
            $gk,
            $ck,
            $avg
        ];
    }

    $removeAccents = function(string $str): string {
        $map = [
            'à'=>'a','á'=>'a','ả'=>'a','ã'=>'a','ạ'=>'a','ă'=>'a','ằ'=>'a','ắ'=>'a','ẳ'=>'a','ẵ'=>'a','ặ'=>'a',
            'â'=>'a','ầ'=>'a','ấ'=>'a','ẩ'=>'a','ẫ'=>'a','ậ'=>'a','đ'=>'d','è'=>'e','é'=>'e','ẻ'=>'e','ẽ'=>'e',
            'ẹ'=>'e','ê'=>'e','ề'=>'e','ế'=>'e','ể'=>'e','ễ'=>'e','ệ'=>'e','ì'=>'i','í'=>'i','ỉ'=>'i','ĩ'=>'i',
            'ị'=>'i','ò'=>'o','ó'=>'o','ỏ'=>'o','õ'=>'o','ọ'=>'o','ô'=>'o','ồ'=>'o','ố'=>'o','ổ'=>'o','ỗ'=>'o',
            'ộ'=>'o','ơ'=>'o','ờ'=>'o','ớ'=>'o','ở'=>'o','ỡ'=>'o','ợ'=>'o','ù'=>'u','ú'=>'u','ủ'=>'u','ũ'=>'u',
            'ụ'=>'u','ư'=>'u','ừ'=>'u','ứ'=>'u','ử'=>'u','ữ'=>'u','ự'=>'u','ỳ'=>'y','ý'=>'y','ỷ'=>'y','ỹ'=>'y',
            'ỵ'=>'y',
            'À'=>'A','Á'=>'A','Ả'=>'A','Ã'=>'A','Ạ'=>'A','Ă'=>'A','Ằ'=>'A','Ắ'=>'A','Ẳ'=>'A','Ẵ'=>'A','Ặ'=>'A',
            'Â'=>'A','Ầ'=>'A','Ấ'=>'A','Ẩ'=>'A','Ẫ'=>'A','Ậ'=>'A','Đ'=>'D','È'=>'E','É'=>'E','Ẻ'=>'E','Ẽ'=>'E',
            'Ẹ'=>'E','Ê'=>'E','Ề'=>'E','Ế'=>'E','Ể'=>'E','Ễ'=>'E','Ệ'=>'E','Ì'=>'I','Í'=>'I','Ỉ'=>'I','Ĩ'=>'I',
            'Ị'=>'I','Ò'=>'O','Ó'=>'O','Ỏ'=>'O','Õ'=>'O','Ọ'=>'O','Ô'=>'O','Ồ'=>'O','Ố'=>'O','Ổ'=>'O','Ỗ'=>'O',
            'Ộ'=>'O','Ơ'=>'O','Ờ'=>'O','Ớ'=>'O','Ở'=>'O','Ỡ'=>'O','Ợ'=>'O','Ù'=>'U','Ú'=>'U','Ủ'=>'U','Ũ'=>'U',
            'Ụ'=>'U','Ư'=>'U','Ừ'=>'U','Ứ'=>'U','Ử'=>'U','Ữ'=>'U','Ự'=>'U','Ỳ'=>'Y','Ý'=>'Y','Ỷ'=>'Y','Ỹ'=>'Y',
            'Ỵ'=>'Y'
        ];
        $ascii = strtr($str, $map);
        return preg_replace('/[^a-zA-Z0-9_\-]/', '', str_replace(' ', '', $ascii));
    };

    $cleanClass = $removeAccents($curClassName ?: (string)$selectedClassId);
    $cleanSubject = $removeAccents($selectedSubject);
    $filename = "SoDiem_{$cleanClass}_{$cleanSubject}_2026-2027.xlsx";

    $sheetName = "SoDiem_" . mb_substr($curClassName, 0, 20);
    $xlsxBinary = create_xlsx($exportData, $sheetName);

    log_action($realUserId, $_SESSION['username'], 'Xuất file điểm', "Xuất file Excel sổ điểm môn [$selectedSubject] lớp [$curClassName].");

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
    header('Content-Length: ' . strlen($xlsxBinary));
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    echo $xlsxBinary;
    exit;
}

// =============================================================================
// AJAX ENDPOINT: LƯU TỨC THÌ 1 Ô ĐIỂM (APPLE FLUID INLINE SAVE & ANTI-BOLA)
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajax_save_single_score') {
    header('Content-Type: application/json; charset=utf-8');

    $studentId = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
    $postClassId = isset($_POST['class_id']) ? (int)$_POST['class_id'] : $selectedClassId;
    $postSubject = isset($_POST['subject']) ? trim((string)$_POST['subject']) : $selectedSubject;

    if ($realUserRole !== 'admin') {
        if (!verify_teacher_assignment($realUserId, $postClassId, $postSubject)) {
            http_response_code(403);
            log_action($realUserId, $_SESSION['username'] ?? 'gv', 'Truy cập trái phép bị chặn', "Từ chối lưu điểm inline: GV không có quyền tại lớp ID [$postClassId] môn [$postSubject].");
            echo json_encode(['success' => false, 'error' => "Quyền truy cập môn [{$postSubject}] tại lớp này đã bị thu hồi hoặc không hợp lệ."]);
            exit;
        }
    }

    // Xác minh học sinh thuộc lớp được chọn (Anti-BOLA)
    $chkSt = $pdo->prepare("SELECT id, full_name FROM students WHERE id = ? AND class_id = ? LIMIT 1");
    $chkSt->execute([$studentId, $postClassId]);
    $stRow = $chkSt->fetch();
    if (!$stRow) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Học sinh không thuộc danh sách lớp đang phụ trách.']);
        exit;
    }

    $parseSingle = function($val) {
        if ($val === null) return null;
        $s = trim(str_replace(',', '.', (string)$val));
        if ($s === '') return null;
        if (!is_numeric($s)) return null;
        $f = round((float)$s, 1);
        return ($f >= 0.0 && $f <= 10.0) ? $f : null;
    };

    $tx1 = $parseSingle($_POST['tx1'] ?? null);
    $tx2 = $parseSingle($_POST['tx2'] ?? null);
    $tx3 = $parseSingle($_POST['tx3'] ?? null);
    $tx4 = $parseSingle($_POST['tx4'] ?? null);
    $gk  = $parseSingle($_POST['gk']  ?? null);
    $ck  = $parseSingle($_POST['ck']  ?? null);

    $txList = array_values(array_filter([$tx1, $tx2, $tx3, $tx4], fn($v) => $v !== null));
    $nTx = count($txList);
    $sTx = array_sum($txList);

    $avg = null;
    if ($gk !== null && $ck !== null) {
        $avg = round(($sTx + 2 * $gk + 3 * $ck) / ($nTx + 5), 1);
    } else {
        $weight = $nTx + ($gk !== null ? 2 : 0) + ($ck !== null ? 3 : 0);
        if ($weight > 0) {
            $avg = round(($sTx + 2 * ($gk ?? 0.0) + 3 * ($ck ?? 0.0)) / $weight, 1);
        }
    }

    try {
        $upStmt = $pdo->prepare("
            INSERT INTO scores (student_id, subject, tx1, tx2, tx3, tx4, gk, ck, avg_score, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                tx1 = VALUES(tx1),
                tx2 = VALUES(tx2),
                tx3 = VALUES(tx3),
                tx4 = VALUES(tx4),
                gk = VALUES(gk),
                ck = VALUES(ck),
                avg_score = VALUES(avg_score),
                updated_at = NOW()
        ");
        $upStmt->execute([$studentId, $postSubject, $tx1, $tx2, $tx3, $tx4, $gk, $ck, $avg]);
        echo json_encode([
            'success'    => true,
            'student_id' => $studentId,
            'avg_score'  => $avg !== null ? number_format($avg, 1) : null,
            'updated_at' => date('H:i:s')
        ]);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// =============================================================================
// XỬ LÝ LƯU ĐIỂM BẢNG VNEDU
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_scores') {
    if ($realUserRole !== 'admin') {
        require_teacher_assignment($realUserId, $selectedClassId, $selectedSubject);
    }
    if (!empty($_POST['scores']) && is_array($_POST['scores'])) {
        // Lấy danh sách ID học sinh hợp lệ của lớp (anti-BOLA whitelist)
        $vStmt = $pdo->prepare("SELECT id FROM students WHERE class_id = ?");
        $vStmt->execute([$selectedClassId]);
        $validStudentMap = array_flip($vStmt->fetchAll(PDO::FETCH_COLUMN));

        $parseScore = function($val) {
            if ($val === null) return null;
            $s = trim(str_replace(',', '.', (string)$val));
            if ($s === '') return null;
            if (!is_numeric($s)) return null;
            $f = (float)$s;
            if ($f < 0.0 || $f > 10.0) return null;
            return $f;
        };

        $upsertStmt = $pdo->prepare("
            INSERT INTO scores (student_id, subject, tx1, tx2, tx3, tx4, gk, ck, avg_score, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                tx1 = VALUES(tx1),
                tx2 = VALUES(tx2),
                tx3 = VALUES(tx3),
                tx4 = VALUES(tx4),
                gk = VALUES(gk),
                ck = VALUES(ck),
                avg_score = VALUES(avg_score),
                updated_at = NOW()
        ");

        $savedCount = 0;
        $pdo->beginTransaction();
        try {
            foreach ($_POST['scores'] as $studentId => $row) {
                $studentId = (int)$studentId;
                // Bỏ qua học sinh không thuộc lớp được chọn (chống BOLA)
                if (!isset($validStudentMap[$studentId])) {
                    continue;
                }

                // Hỗ trợ cả key chuẩn (tx1..tx4) lẫn key cũ (m1, m2, p15_1, p15_2, t1_1)
                $tx1 = $parseScore($row['tx1'] ?? $row['m1'] ?? null);
                $tx2 = $parseScore($row['tx2'] ?? $row['m2'] ?? null);
                $tx3 = $parseScore($row['tx3'] ?? $row['p15_1'] ?? null);
                $tx4 = $parseScore($row['tx4'] ?? $row['p15_2'] ?? null);
                if ($tx4 === null && isset($row['t1_1'])) {
                    $tx4 = $parseScore($row['t1_1']);
                }
                $gk  = $parseScore($row['gk'] ?? null);
                $ck  = $parseScore($row['ck'] ?? null);

                $txList = array_values(array_filter([$tx1, $tx2, $tx3, $tx4], fn($v) => $v !== null));
                $nTx = count($txList);
                $sTx = array_sum($txList);

                $avg = null;
                if ($gk !== null && $ck !== null) {
                    $avg = round(($sTx + 2 * $gk + 3 * $ck) / ($nTx + 5), 1);
                } else {
                    $weight = $nTx + ($gk !== null ? 2 : 0) + ($ck !== null ? 3 : 0);
                    if ($weight > 0) {
                        $avg = round(($sTx + 2 * ($gk ?? 0.0) + 3 * ($ck ?? 0.0)) / $weight, 1);
                    }
                }

                $upsertStmt->execute([$studentId, $selectedSubject, $tx1, $tx2, $tx3, $tx4, $gk, $ck, $avg]);
                $savedCount++;
            }

            $pdo->commit();
            log_action($realUserId, $_SESSION['username'], 'Lưu sổ điểm', "Cập nhật bảng điểm môn [$selectedSubject] cho lớp [$curClassName] ($savedCount học sinh).");
            $msg = "Đã lưu thành công điểm môn {$selectedSubject} cho {$savedCount} học sinh!";
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errorMsg = "Lỗi khi lưu điểm: " . $e->getMessage();
        }
    }
}

// =============================================================================
// XỬ LÝ NHẬP ĐIỂM BẰNG FILE TẠI TRANG SỔ ĐIỂM (.XLSX / .CSV)
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && ($_POST['action'] === 'import_file' || $_POST['action'] === 'import_gradebook')) {
    if ($realUserRole !== 'admin') {
        require_teacher_assignment($realUserId, $selectedClassId, $selectedSubject);
    }

    $uploadedFile = $_FILES['gradebook_file'] ?? $_FILES['csv_file'] ?? null;
    if ($uploadedFile && $uploadedFile['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $uploadedFile['tmp_name'];
        $fileName = $uploadedFile['name'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $rows = ($ext === 'xlsx' || $ext === 'xls') ? parse_xlsx($fileTmp) : parse_csv($fileTmp);

        if (!empty($rows)) {
            [$headerIdx, $colMap] = detect_gradebook_headers($rows);
            $startRow = $headerIdx >= 0 ? $headerIdx + 1 : 0;

            $findStudentCode = $pdo->prepare("SELECT id FROM students WHERE class_id = ? AND student_code = ?");
            $findStudentNameBday = $pdo->prepare("SELECT id FROM students WHERE class_id = ? AND full_name = ? AND birthday = ?");
            $findStudentNameOnly = $pdo->prepare("SELECT id FROM students WHERE class_id = ? AND full_name = ?");
            $insStudent = $pdo->prepare("INSERT INTO students (class_id, student_code, full_name, birthday, phone) VALUES (?, ?, ?, ?, ?)");

            $upsertScore = $pdo->prepare("
                INSERT INTO scores (student_id, subject, tx1, tx2, tx3, tx4, gk, ck, avg_score, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE
                    tx1 = VALUES(tx1),
                    tx2 = VALUES(tx2),
                    tx3 = VALUES(tx3),
                    tx4 = VALUES(tx4),
                    gk = VALUES(gk),
                    ck = VALUES(ck),
                    avg_score = VALUES(avg_score),
                    updated_at = CURRENT_TIMESTAMP
            ");

            $pdo->beginTransaction();
            $importedCount = 0;

            try {
                for ($i = $startRow; $i < count($rows); $i++) {
                    $row = $rows[$i];
                    if (empty($row) || !is_array($row)) continue;

                    // Lấy Họ tên
                    $fullName = '';
                    if (isset($colMap['full_name']) && isset($row[$colMap['full_name']])) {
                        $fullName = trim((string)$row[$colMap['full_name']]);
                    } elseif (isset($row[1]) && is_numeric($row[0])) {
                        $fullName = trim((string)$row[1]);
                    } elseif (isset($row[0])) {
                        $fullName = trim((string)$row[0]);
                    }

                    if ($fullName === '' || mb_stripos($fullName, 'tổng số') !== false) continue;

                    // Lấy Mã HS
                    $studentCode = isset($colMap['student_code']) && isset($row[$colMap['student_code']]) ? trim((string)$row[$colMap['student_code']]) : null;
                    if ($studentCode === '') $studentCode = null;

                    // Lấy Ngày sinh
                    $rawBday = isset($colMap['birthday']) && isset($row[$colMap['birthday']]) ? $row[$colMap['birthday']] : null;
                    $normBday = normalize_excel_date($rawBday);

                    // Lấy SĐT
                    $phone = '';
                    if (isset($colMap['phone']) && isset($row[$colMap['phone']])) {
                        $phone = preg_replace('/[^0-9]/', '', (string)$row[$colMap['phone']]);
                    }

                    // Khớp học sinh trong lớp đang chọn
                    $studentId = null;
                    if ($studentCode !== null) {
                        $findStudentCode->execute([$selectedClassId, $studentCode]);
                        $studentId = $findStudentCode->fetchColumn();
                    }
                    if (!$studentId && $normBday !== null) {
                        $findStudentNameBday->execute([$selectedClassId, $fullName, $normBday]);
                        $studentId = $findStudentNameBday->fetchColumn();
                    }
                    if (!$studentId) {
                        $findStudentNameOnly->execute([$selectedClassId, $fullName]);
                        $studentId = $findStudentNameOnly->fetchColumn();
                    }

                    // Tự động thêm học sinh vào lớp nếu chưa có
                    if (!$studentId) {
                        try {
                            $insStudent->execute([$selectedClassId, $studentCode, $fullName, $normBday, $phone]);
                            $studentId = (int)$pdo->lastInsertId();
                        } catch (PDOException $e) {
                            if ($e->errorInfo[1] == 1062) {
                                $findStudentNameOnly->execute([$selectedClassId, $fullName]);
                                $studentId = $findStudentNameOnly->fetchColumn();
                            } else {
                                throw $e;
                            }
                        }
                    }

                    if (!$studentId) continue;

                    // Trích xuất điểm
                    $cleanScore = function($val) {
                        if ($val === null || $val === '') return null;
                        $s = str_replace(',', '.', trim((string)$val));
                        if (!is_numeric($s)) return null;
                        $f = (float)$s;
                        return ($f >= 0.0 && $f <= 10.0) ? $f : null;
                    };

                    $tx1 = isset($colMap['tx1']) && isset($row[$colMap['tx1']]) ? $cleanScore($row[$colMap['tx1']]) : null;
                    $tx2 = isset($colMap['tx2']) && isset($row[$colMap['tx2']]) ? $cleanScore($row[$colMap['tx2']]) : null;
                    $tx3 = isset($colMap['tx3']) && isset($row[$colMap['tx3']]) ? $cleanScore($row[$colMap['tx3']]) : null;
                    $tx4 = isset($colMap['tx4']) && isset($row[$colMap['tx4']]) ? $cleanScore($row[$colMap['tx4']]) : null;
                    $gk  = isset($colMap['gk'])  && isset($row[$colMap['gk']])  ? $cleanScore($row[$colMap['gk']])  : null;
                    $ck  = isset($colMap['ck'])  && isset($row[$colMap['ck']])  ? $cleanScore($row[$colMap['ck']])  : null;

                    $txList = array_values(array_filter([$tx1, $tx2, $tx3, $tx4], fn($v) => $v !== null));
                    $nTx = count($txList);
                    $sTx = array_sum($txList);

                    $avg = null;
                    if ($gk !== null && $ck !== null) {
                        $avg = round(($sTx + 2 * $gk + 3 * $ck) / ($nTx + 5), 1);
                    } else {
                        $w = $nTx + ($gk !== null ? 2 : 0) + ($ck !== null ? 3 : 0);
                        if ($w > 0) {
                            $avg = round(($sTx + 2 * ($gk ?? 0.0) + 3 * ($ck ?? 0.0)) / $w, 1);
                        }
                    }

                    $upsertScore->execute([$studentId, $selectedSubject, $tx1, $tx2, $tx3, $tx4, $gk, $ck, $avg]);
                    $importedCount++;
                }

                $pdo->commit();
                log_action($realUserId, $_SESSION['username'], 'Import file điểm', "Nạp dữ liệu điểm từ file [$fileName] cho $importedCount học sinh lớp [$curClassName] môn [$selectedSubject].");
                $msg = "Đã nạp thành công điểm cho $importedCount học sinh từ file $fileName!";
            } catch (Throwable $e) {
                $pdo->rollBack();
                $errorMsg = "Lỗi khi nhập dữ liệu: " . $e->getMessage();
            }
        } else {
            $errorMsg = "File tải lên không có dữ liệu hoặc không đọc được!";
        }
    } else {
        $errorMsg = "Vui lòng chọn file hợp lệ để tải lên!";
    }
}

// Lấy danh sách học sinh
$studentList = [];
if ($selectedClassId) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_code, s.full_name, s.birthday, s.phone,
               sc.tx1, sc.tx2, sc.tx3, sc.tx4, sc.gk, sc.ck, sc.avg_score
        FROM students s
        LEFT JOIN scores sc ON s.id = sc.student_id AND sc.subject = ?
        WHERE s.class_id = ?
        ORDER BY s.full_name ASC
    ");
    $stmt->execute([$selectedSubject, $selectedClassId]);
    $studentList = $stmt->fetchAll();
}
?>
<?php
$pageTitle = 'Sổ Điểm Điện Tử Chuẩn VnEdu - EduRadar';
$activeNav = 'gradebook';
$extraHead = '
    <style>
        .vnedu-col { text-align: center !important; min-width: 65px; }
        .vnedu-header { background: #1e293b !important; color: #f8fafc !important; }
    </style>
';
require_once __DIR__ . '/includes/header.php';
?>
    <header class="topbar">
        <div>
            <h1>SỔ ĐIỂM BỘ MÔN // VNEDU MATRIX</h1>
            <p class="muted">
                Phụ trách: <b><?= htmlspecialchars($currentTeacherName) ?></b> | Môn: <b style="color: var(--primary);"><?= htmlspecialchars($selectedSubject) ?></b>
            </p>
        </div>
        <div class="top-actions">
            <form method="GET" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <?php if ($actingTeacherId > 0 && $realUserRole === 'admin'): ?>
                    <input type="hidden" name="teacher_id" value="<?= $actingTeacherId ?>">
                <?php endif; ?>
                <select name="class_id" onchange="this.form.submit()" style="padding: 10px 14px; border-radius: 10px; font-weight: 700; border: 1px solid var(--border-color); font-size: 13.5px; background: #fff;">
                    <?php if (empty($classesInCurrentSubject)): ?>
                        <option value="">(Chưa có lớp cho môn này)</option>
                    <?php else: ?>
                        <?php foreach ($classesInCurrentSubject as $cid => $c): ?>
                            <?php if ($c['student_count'] > 0 || $cid == $selectedClassId): ?>
                                <option value="<?= $cid ?>" <?= $cid == $selectedClassId ? 'selected' : '' ?>>
                                    Lớp <?= htmlspecialchars($c['name']) ?> (Khối <?= $c['grade'] ?> • <?= $c['student_count'] ?> HS)
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <select name="subject" onchange="this.form.class_id.value=''; this.form.submit()" style="padding: 10px 14px; border-radius: 10px; font-weight: 700; border: 1px solid var(--border-color); font-size: 13.5px; background: #fff;">
                    <?php 
                        $allSubjectsToDisplay = !empty($teacherSubjects) ? array_keys($teacherSubjects) : $availableSubjectsForClass;
                        foreach ($allSubjectsToDisplay as $sub): 
                    ?>
                        <option value="<?= $sub ?>" <?= $sub === $selectedSubject ? 'selected' : '' ?>>Môn <?= $sub ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </header>

    <?php if (!empty($teacherSubjects)): ?>
        <!-- THANH CHỌN MÔN HỌC GIẢNG DẠY (CHUẨN BỘ MÁY PHÂN QUYỀN VNEDU) -->
        <div style="display: flex; gap: 8px; align-items: center; overflow-x: auto; padding: 4px 0 10px; margin-bottom: 6px; -webkit-overflow-scrolling: touch;">
            <span style="font-size: 11.5px; font-weight: 800; color: #475569; white-space: nowrap; text-transform: uppercase; letter-spacing: 0.05em;">Môn giảng dạy:</span>
            <?php foreach ($teacherSubjects as $subName => $subClasses): ?>
                <?php 
                    $isSubActive = ($subName === $selectedSubject);
                    $tParam = ($actingTeacherId > 0 && $realUserRole === 'admin') ? "&teacher_id={$actingTeacherId}" : "";
                    $firstClassId = 0;
                    foreach ($subClasses as $cId => $cInfo) {
                        if ($cInfo['student_count'] > 0) { $firstClassId = $cId; break; }
                    }
                    if ($firstClassId === 0) $firstClassId = array_key_first($subClasses) ?: 0;
                ?>
                <a href="nhap_diem.php?class_id=<?= $firstClassId ?>&subject=<?= urlencode($subName) ?><?= $tParam ?>" 
                   style="display: inline-flex; align-items: center; gap: 8px; padding: 7px 16px; border-radius: 8px; font-size: 13px; text-decoration: none; font-weight: 700; white-space: nowrap; transition: all .15s; <?= $isSubActive ? 'background: #4338ca; color: #ffffff; box-shadow: 0 2px 8px rgba(67, 56, 202, 0.35);' : 'background: #ffffff; color: #334155; border: 1px solid #cbd5e1;' ?>">
                    <span>Môn <?= htmlspecialchars($subName) ?></span>
                    <span style="font-size: 11px; padding: 2px 8px; border-radius: 10px; <?= $isSubActive ? 'background: rgba(255,255,255,0.25); color: #fff;' : 'background: #e2e8f0; color: #475569;' ?>">
                        <?= count($subClasses) ?> lớp
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($classesInCurrentSubject) && count($classesInCurrentSubject) > 1): ?>
        <!-- THANH CHUYỂN NHANH LỚP PHÂN CÔNG THEO MÔN (CHUẨN VNEDU) -->
        <div style="display: flex; gap: 8px; align-items: center; overflow-x: auto; padding: 4px 0 14px; margin-bottom: 6px; -webkit-overflow-scrolling: touch;">
            <span style="font-size: 11.5px; font-weight: 800; color: #64748b; white-space: nowrap; text-transform: uppercase; letter-spacing: 0.05em;">Lớp phân công:</span>
            <?php foreach ($classesInCurrentSubject as $cid => $c): ?>
                <?php 
                    if ($c['student_count'] == 0 && $cid != $selectedClassId) continue;
                    $isActive = ($cid == $selectedClassId);
                    $tParam = ($actingTeacherId > 0 && $realUserRole === 'admin') ? "&teacher_id={$actingTeacherId}" : "";
                ?>
                <a href="nhap_diem.php?class_id=<?= $cid ?>&subject=<?= urlencode($selectedSubject) ?><?= $tParam ?>" 
                   style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 8px; font-size: 12.5px; text-decoration: none; font-weight: 600; white-space: nowrap; transition: all .15s; <?= $isActive ? 'background: #0284c7; color: #fff; box-shadow: 0 2px 6px rgba(2, 132, 199, 0.3);' : ($c['student_count'] > 0 ? 'background: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd;' : 'background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0;') ?>">
                    Lớp <?= htmlspecialchars($c['name']) ?>
                    <span style="font-size: 11px; padding: 2px 6px; border-radius: 10px; <?= $isActive ? 'background: rgba(255,255,255,0.25); color: #fff;' : ($c['student_count'] > 0 ? 'background: #e0f2fe; color: #0369a1;' : 'background: #e2e8f0; color: #64748b;') ?>">
                        <?= $c['student_count'] ?> HS
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($msg): ?>
        <div class="alert success mb-3" style="background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-weight: 600;">
            ✓ <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
        <div class="alert error mb-3" style="background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-weight: 600;">
            ⚠️ <?= htmlspecialchars($errorMsg) ?>
        </div>
    <?php endif; ?>

    <?php if ($realUserRole === 'admin' && $actingTeacherId > 0): ?>
        <div class="alert mb-3" style="background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;">
            👁️ <b>Chế độ Admin:</b> Bạn đang xem và có thể chỉnh sửa sổ điểm môn <b><?= htmlspecialchars($selectedSubject) ?></b> của giáo viên này.
            <a href="admin.php" style="margin-left: 10px; font-weight: bold; text-decoration: underline;">Quay lại Admin</a>
        </div>
    <?php endif; ?>

    <?php if (empty($myClasses)): ?>
        <div class="panel-box" style="text-align: center; padding: 40px; background: #fff; border-radius: 12px; border: 1px solid var(--border-color);">
            <h3>Bạn chưa được phân công lớp phụ trách</h3>
            <p class="muted mb-3">Vui lòng liên hệ tài khoản Admin trường để được tích chọn cấp quyền vào các lớp khối 6, 7, 8, 9.</p>
        </div>
    <?php else: ?>

    <?php
    $gbCountExcellent = 0;
    $gbCountGood = 0;
    $gbCountAverage = 0;
    $gbCountLow = 0;
    $gbCountPending = 0;
    foreach ($studentList as $st) {
        if ($st['avg_score'] !== null && $st['avg_score'] !== '') {
            $fa = (float)$st['avg_score'];
            if ($fa >= 8.0) $gbCountExcellent++;
            elseif ($fa >= 6.5) $gbCountGood++;
            elseif ($fa >= 5.0) $gbCountAverage++;
            else $gbCountLow++;
        } else {
            $gbCountPending++;
        }
    }
    ?>

    <!-- SPREADSHEET TOOLBAR -->
    <div class="spreadsheet-toolbar">
        <div class="toolbar-info">
            <span class="toolbar-title">Sổ Điểm Môn <?= htmlspecialchars($selectedSubject) ?> — Lớp <?= htmlspecialchars($curClassName) ?></span>
            <span class="badge" style="background: #e0e7ff; color: #3730a3; font-weight: 700; font-family: 'JetBrains Mono', monospace;">
                SĨ SỐ: <?= count($studentList) ?> HS
            </span>
        </div>
        <div class="toolbar-actions">
            <span id="saveStatusBadge" class="dirty-badge clean">✓ ĐỒNG BỘ MÁY CHỦ</span>
            <button type="submit" form="gradebookForm" id="btnSaveScores" class="btn primary" style="padding: 10px 22px; font-size: 13.5px; font-weight: 700; letter-spacing: 0.02em;">
                LƯU BẢNG ĐIỂM
            </button>
        </div>
    </div>

    <!-- THANH CÔNG CỤ LỌC PHÂN LOẠI HỌC SINH & TÌM KIẾM TỨC THÌ -->
    <div class="gradebook-filter-bar" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 14px; background: #ffffff; padding: 10px 16px; border-radius: 12px; border: 1px solid #cbd5e1; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
            <span style="font-size: 11.5px; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.05em;">
                Lọc phân loại:
            </span>
            <button type="button" class="btn-gb-filter active" data-gb-filter="all" onclick="applyGbFilter('all', this)">
                Tất cả (<?= count($studentList) ?>)
            </button>
            <button type="button" class="btn-gb-filter" data-gb-filter="excellent" onclick="applyGbFilter('excellent', this)" style="color: #15803d; border-color: #bbf7d0;">
                Giỏi (<?= $gbCountExcellent ?>)
            </button>
            <button type="button" class="btn-gb-filter" data-gb-filter="good" onclick="applyGbFilter('good', this)" style="color: #1d4ed8; border-color: #bfdbfe;">
                Khá (<?= $gbCountGood ?>)
            </button>
            <button type="button" class="btn-gb-filter" data-gb-filter="average" onclick="applyGbFilter('average', this)" style="color: #b45309; border-color: #fde68a;">
                Đạt (<?= $gbCountAverage ?>)
            </button>
            <button type="button" class="btn-gb-filter" data-gb-filter="low" onclick="applyGbFilter('low', this)" style="color: #b91c1c; border-color: #fecaca;">
                Phụ đạo (<?= $gbCountLow ?>)
            </button>
            <?php if ($gbCountPending > 0): ?>
            <button type="button" class="btn-gb-filter" data-gb-filter="pending" onclick="applyGbFilter('pending', this)" style="color: #64748b;">
                Chưa có điểm (<?= $gbCountPending ?>)
            </button>
            <?php endif; ?>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
            <input type="text" id="gbSearchInput" oninput="applyGbSearch(this.value)" placeholder="Tìm theo tên, mã HS..." style="height: 34px; padding: 0 12px; border-radius: 8px; border: 1.5px solid #cbd5e1; font-size: 13px; outline: none; width: 220px; background: #fff;">
        </div>
    </div>

    <!-- SPREADSHEET FORM -->
    <form method="POST" id="gradebookForm">
        <input type="hidden" name="action" value="save_scores">
        
        <div class="spreadsheet-container">
            <table class="spreadsheet-table" id="gradebookTable">
                <thead>
                    <!-- TIER 1 HEADER: SUPER-GROUPS -->
                    <tr class="header-tier-1">
                        <th rowspan="2" class="col-sticky-stt">STT</th>
                        <th rowspan="2" class="col-code">Mã HS</th>
                        <th rowspan="2" class="col-sticky-name">Họ và Tên Học Sinh</th>
                        <th rowspan="2" class="col-dob">Ngày Sinh</th>
                        <th colspan="4" class="group-header group-tx">
                            <span class="group-pill pill-tx">ĐĐG THƯỜNG XUYÊN (HỆ SỐ 1)</span>
                        </th>
                        <th rowspan="2" class="group-header group-gk">
                            <span class="group-pill pill-gk">GIỮA KỲ</span><br>
                            <span class="weight-tag" style="font-size: 11px; opacity: 0.85;">HS 2</span>
                        </th>
                        <th rowspan="2" class="group-header group-ck">
                            <span class="group-pill pill-ck">CUỐI KỲ</span><br>
                            <span class="weight-tag" style="font-size: 11px; opacity: 0.85;">HS 3</span>
                        </th>
                        <th rowspan="2" class="group-header group-avg">
                            <span class="group-pill pill-avg">ĐTBmhk</span><br>
                            <span class="weight-tag" style="font-size: 11px; opacity: 0.85;">TỰ ĐỘNG</span>
                        </th>
                    </tr>
                    <!-- TIER 2 HEADER: TX SUB-COLUMNS -->
                    <tr class="header-tier-2">
                        <th class="sub-tx">TX1</th>
                        <th class="sub-tx">TX2</th>
                        <th class="sub-tx">TX3</th>
                        <th class="sub-tx">TX4</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($studentList)): ?>
                        <tr>
                            <td colspan="11" style="text-align: center; padding: 48px 20px; color: #64748b; background: #fafafa;">
                                <div style="font-size: 36px; margin-bottom: 10px;">📂</div>
                                <b style="font-size: 16px; color: #1e293b; display: block; margin-bottom: 6px;">
                                    Lớp <?= htmlspecialchars($curClassName) ?> hiện chưa có danh sách học sinh (Sĩ số: 0)
                                </b>
                                <p style="margin-bottom: 18px; font-size: 13.5px; color: #64748b; max-width: 600px; margin-left: auto; margin-right: auto;">
                                    Lớp này chưa được nạp danh sách học sinh. Bạn có thể bấm chọn ngay một trong các lớp thực tế đã có học sinh bên dưới:
                                </p>
                                <div style="display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; margin-bottom: 20px;">
                                    <?php foreach ($assignedClasses as $altC): ?>
                                        <?php if ($altC['student_count'] > 0): ?>
                                            <a href="nhap_diem.php?class_id=<?= $altC['id'] ?>&subject=<?= urlencode($altC['subjects'][0] ?? 'Toán') ?><?= ($actingTeacherId > 0 && $realUserRole === 'admin') ? '&teacher_id=' . $actingTeacherId : '' ?>" class="btn primary" style="font-size: 13px; padding: 7px 16px; font-weight: 700;">
                                                👉 Lớp <?= htmlspecialchars($altC['name']) ?> (<?= $altC['student_count'] ?> HS)
                                            </a>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                                <p class="muted small">Hoặc bạn có thể sử dụng công cụ nạp file Excel phía dưới để nạp học sinh vào lớp này.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $r = 0; foreach ($studentList as $s): ?>
                            <?php 
                                $sId = (int)$s['id'];
                                $code = !empty($s['student_code']) ? htmlspecialchars($s['student_code']) : ('HS' . str_pad((string)$sId, 4, '0', STR_PAD_LEFT));
                                $dob = !empty($s['birthday']) ? date('d/m/Y', strtotime($s['birthday'])) : '—';
                                $avg = $s['avg_score'] !== null ? number_format((float)$s['avg_score'], 1) : null;
                                
                                $tier = 'pending';
                                $avgClass = 'level-empty';
                                if ($avg !== null) {
                                    $fAvg = (float)$avg;
                                    if ($fAvg < 5.0) { $avgClass = 'level-danger'; $tier = 'low'; }
                                    elseif ($fAvg < 6.5) { $avgClass = 'level-warning'; $tier = 'average'; }
                                    elseif ($fAvg < 8.0) { $avgClass = 'level-good'; $tier = 'good'; }
                                    else { $avgClass = 'level-excellent'; $tier = 'excellent'; }
                                }
                            ?>
                            <tr data-student-id="<?= $sId ?>" data-row-idx="<?= $r ?>" data-tier="<?= $tier ?>" data-name="<?= htmlspecialchars(mb_strtolower($s['full_name'], 'UTF-8')) ?>" data-code="<?= htmlspecialchars(mb_strtolower($code, 'UTF-8')) ?>">
                                <td class="col-sticky-stt"><?= $r + 1 ?></td>
                                <td class="col-code"><?= $code ?></td>
                                <td class="col-sticky-name">
                                    <div class="student-name font-semibold" style="font-weight: 700; color: var(--text-heading);"><?= htmlspecialchars($s['full_name']) ?></div>
                                    <?php if (!empty($s['phone'])): ?>
                                        <div class="student-phone muted small" style="font-size: 11.5px; color: #64748b;"><?= htmlspecialchars($s['phone']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="col-dob"><?= $dob ?></td>
                                
                                <!-- 4 CỘT TX (HỆ SỐ 1) -->
                                <td class="col-score cell-tx">
                                    <input type="text" inputmode="decimal" min="0" max="10" step="0.1" maxlength="4" class="score-input group-tx"
                                           name="scores[<?= $sId ?>][tx1]"
                                           value="<?= $s['tx1'] !== null ? (float)$s['tx1'] : '' ?>"
                                           data-student-id="<?= $sId ?>" data-field="tx1"
                                           data-row="<?= $r ?>" data-col="0" placeholder="—" autocomplete="off">
                                </td>
                                <td class="col-score cell-tx">
                                    <input type="text" inputmode="decimal" min="0" max="10" step="0.1" maxlength="4" class="score-input group-tx"
                                           name="scores[<?= $sId ?>][tx2]"
                                           value="<?= $s['tx2'] !== null ? (float)$s['tx2'] : '' ?>"
                                           data-student-id="<?= $sId ?>" data-field="tx2"
                                           data-row="<?= $r ?>" data-col="1" placeholder="—" autocomplete="off">
                                </td>
                                <td class="col-score cell-tx">
                                    <input type="text" inputmode="decimal" min="0" max="10" step="0.1" maxlength="4" class="score-input group-tx"
                                           name="scores[<?= $sId ?>][tx3]"
                                           value="<?= $s['tx3'] !== null ? (float)$s['tx3'] : '' ?>"
                                           data-student-id="<?= $sId ?>" data-field="tx3"
                                           data-row="<?= $r ?>" data-col="2" placeholder="—" autocomplete="off">
                                </td>
                                <td class="col-score cell-tx">
                                    <input type="text" inputmode="decimal" min="0" max="10" step="0.1" maxlength="4" class="score-input group-tx"
                                           name="scores[<?= $sId ?>][tx4]"
                                           value="<?= $s['tx4'] !== null ? (float)$s['tx4'] : '' ?>"
                                           data-student-id="<?= $sId ?>" data-field="tx4"
                                           data-row="<?= $r ?>" data-col="3" placeholder="—" autocomplete="off">
                                </td>
                                
                                <!-- CỘT GIỮA KỲ (HỆ SỐ 2) -->
                                <td class="col-score cell-gk">
                                    <input type="text" inputmode="decimal" min="0" max="10" step="0.1" maxlength="4" class="score-input group-gk"
                                           name="scores[<?= $sId ?>][gk]"
                                           value="<?= $s['gk'] !== null ? (float)$s['gk'] : '' ?>"
                                           data-student-id="<?= $sId ?>" data-field="gk"
                                           data-row="<?= $r ?>" data-col="4" placeholder="—" autocomplete="off">
                                </td>
                                
                                <!-- CỘT CUỐI KỲ (HỆ SỐ 3) -->
                                <td class="col-score cell-ck">
                                    <input type="text" inputmode="decimal" min="0" max="10" step="0.1" maxlength="4" class="score-input group-ck"
                                           name="scores[<?= $sId ?>][ck]"
                                           value="<?= $s['ck'] !== null ? (float)$s['ck'] : '' ?>"
                                           data-student-id="<?= $sId ?>" data-field="ck"
                                           data-row="<?= $r ?>" data-col="5" placeholder="—" autocomplete="off">
                                </td>
                                
                                <!-- CỘT ĐTB MÔN HỌC KỲ (TỰ ĐỘNG) -->
                                <td class="cell-avg">
                                    <span class="avg-badge <?= $avgClass ?>" data-avg-student="<?= $sId ?>">
                                        <?= $avg !== null ? $avg : '—' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php $r++; endforeach; ?>
                    <?php endif; ?>
                    <tr id="gbFilterEmptyRow" style="display: none;">
                        <td colspan="11" style="text-align: center; padding: 36px 20px; color: #64748b; font-weight: 600;">
                            🔍 Không tìm thấy học sinh nào phù hợp với bộ lọc hoặc từ khóa tìm kiếm.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <p class="muted small">
                <b>Phím tắt điều hướng:</b> Nhấn <code>Enter</code> hoặc <code>↓</code> để xuống dòng trong cùng cột; <code>↑</code> để lên trên; <code>Tab</code> sang ngang; tự động chọn số khi focus; nhập dấu phẩy <code>,</code> tự chuyển thành <code>.</code>
            </p>
            <button type="submit" class="btn primary" style="padding: 12px 28px; font-size: 14px; font-weight: 700; letter-spacing: 0.02em;">
                LƯU BẢNG ĐIỂM MÔN <?= htmlspecialchars($selectedSubject) ?>
            </button>
        </div>
    </form>

    <!-- FLOATING TOOLTIP FOR ERROR HINTS -->
    <div id="scoreTooltip" class="score-tooltip" style="display: none;"></div>

    <!-- BOTTOM PANEL: IMPORT & EXPORT GRADEBOOK -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 32px;">
        <!-- Khối 1: Nạp điểm bằng File -->
        <section class="panel-box" style="margin-bottom: 0; background: #fff; padding: 24px; border-radius: 12px; border: 1px solid var(--border-color);">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: #e0e7ff; color: #4338ca; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 13px;">IN</div>
                <div>
                    <h3 style="margin: 0; font-size: 15px; font-weight: 800;">Nạp Điểm Bằng File (.xlsx / .csv)</h3>
                    <p class="muted small" style="margin: 0;">Nạp tự động điểm số chuẩn VnEdu vào lớp đang chọn</p>
                </div>
            </div>
            <form method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 14px;">
                <input type="hidden" name="action" value="import_file">
                <input type="file" name="gradebook_file" accept=".xlsx, .xls, .csv, .txt" required 
                       style="padding: 10px; border: 1px dashed var(--border-color); border-radius: 8px; background: #f8fafc; font-size: 13px;">
                <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                    <button type="submit" class="btn primary" style="padding: 10px 18px; font-size: 13px; font-weight: 700;">
                        Tải Lên & Nhập Sổ Điểm
                    </button>
                    <a href="sample_gradebook.xlsx" download class="btn outline" style="padding: 9px 14px; font-size: 12px; font-weight: 600;">
                        File Mẫu Excel (.xlsx)
                    </a>
                    <a href="sample_vnedu.csv" download class="btn outline" style="padding: 9px 14px; font-size: 12px; font-weight: 600;">
                        File Mẫu CSV
                    </a>
                </div>
            </form>
        </section>

        <!-- Khối 2: Xuất sổ điểm ra Excel -->
        <section class="panel-box" style="margin-bottom: 0; background: #fff; padding: 24px; border-radius: 12px; border: 1px solid var(--border-color); display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                    <div style="width: 36px; height: 36px; border-radius: 8px; background: #dcfce7; color: #15803d; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 13px;">EX</div>
                    <div>
                        <h3 style="margin: 0; font-size: 15px; font-weight: 800;">Xuất Sổ Điểm Điện Tử (.xlsx)</h3>
                        <p class="muted small" style="margin: 0;">Tải sổ điểm lớp <?= htmlspecialchars($curClassName) ?> môn <?= htmlspecialchars($selectedSubject) ?> chuẩn ECMA-376</p>
                    </div>
                </div>
                <p class="muted small" style="line-height: 1.6; margin-top: 8px;">
                    File Excel xuất ra chứa đầy đủ danh sách học sinh, mã định danh, ngày sinh, các cột ĐĐGtx 1..4, ĐĐGgk, ĐĐGck và ĐTBmhk tính tự động.
                </p>
            </div>
            <div style="margin-top: 16px;">
                <a href="nhap_diem.php?export=xlsx&class_id=<?= (int)$selectedClassId ?>&subject=<?= urlencode($selectedSubject) ?>" 
                   class="btn outline" style="display: inline-flex; align-items: center; gap: 8px; padding: 12px 20px; font-weight: 700; background: #f0fdf4; border-color: #86efac; color: #166534; text-decoration: none; border-radius: 8px;">
                    Xuất Excel Sổ Điểm Môn <?= htmlspecialchars($selectedSubject) ?>
                </a>
            </div>
        </section>
    </div>

    <?php endif; ?>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const table = document.getElementById('gradebookTable');
    if (!table) return;

    const totalRows = document.querySelectorAll('#gradebookTable tbody tr[data-student-id]').length;
    const saveBadge = document.getElementById('saveStatusBadge');
    const form = document.getElementById('gradebookForm');
    const tooltip = document.getElementById('scoreTooltip');
    let isDirty = false;

    function setDirty(state) {
        isDirty = state;
        if (saveBadge) {
            if (state) {
                saveBadge.className = 'dirty-badge dirty';
                saveBadge.textContent = '● Có thay đổi chưa lưu';
            } else {
                saveBadge.className = 'dirty-badge clean';
                saveBadge.textContent = '✓ Dữ liệu đã lưu';
            }
        }
    }

    function showTooltip(elem, text) {
        if (!tooltip) return;
        const rect = elem.getBoundingClientRect();
        tooltip.textContent = text;
        tooltip.style.left = (rect.left + rect.width / 2 - tooltip.offsetWidth / 2) + 'px';
        tooltip.style.top = (rect.top - 8) + 'px';
        tooltip.style.display = 'block';
    }

    function hideTooltip() {
        if (tooltip) tooltip.style.display = 'none';
    }

    function focusCell(r, c) {
        const target = document.querySelector(`.score-input[data-row="${r}"][data-col="${c}"]`);
        if (target) {
            target.focus();
            target.select();
        }
    }

    function validateScoreInput(input) {
        let val = input.value.trim().replace(',', '.');
        if (val === '') {
            input.classList.remove('is-invalid');
            hideTooltip();
            return true;
        }
        if (isNaN(val) || !/^(10(\.0{1,2})?|[0-9](\.[0-9]{1,2})?)$/.test(val)) {
            input.classList.add('is-invalid');
            showTooltip(input, 'Điểm không hợp lệ: phải từ 0.0 đến 10.0');
            return false;
        }
        const num = parseFloat(val);
        if (num < 0 || num > 10) {
            input.classList.add('is-invalid');
            showTooltip(input, 'Điểm không hợp lệ: phải từ 0.0 đến 10.0');
            return false;
        }
        input.classList.remove('is-invalid');
        hideTooltip();
        return true;
    }

    function recalculateRow(studentId) {
        const inputs = document.querySelectorAll(`.score-input[data-student-id="${studentId}"]`);
        let txScores = [];
        let gkScore = null;
        let ckScore = null;
        let hasAny = false;

        inputs.forEach(inp => {
            const v = inp.value.trim().replace(',', '.');
            if (v !== '' && !isNaN(v)) {
                const num = parseFloat(v);
                if (num >= 0 && num <= 10) {
                    hasAny = true;
                    const field = inp.dataset.field;
                    if (field.startsWith('tx')) txScores.push(num);
                    else if (field === 'gk') gkScore = num;
                    else if (field === 'ck') ckScore = num;
                }
            }
        });

        const badge = document.querySelector(`.avg-badge[data-avg-student="${studentId}"]`);
        if (!badge) return;

        if (!hasAny) {
            badge.textContent = '—';
            badge.className = 'avg-badge level-empty';
            return;
        }

        const txCount = txScores.length;
        const txSum = txScores.reduce((a, b) => a + b, 0);

        let avg = null;
        if (gkScore !== null && ckScore !== null) {
            avg = (txSum + 2 * gkScore + 3 * ckScore) / (txCount + 5);
        } else {
            const totalWeight = txCount + (gkScore !== null ? 2 : 0) + (ckScore !== null ? 3 : 0);
            const weightedSum = txSum + (gkScore !== null ? 2 * gkScore : 0) + (ckScore !== null ? 3 * ckScore : 0);
            if (totalWeight > 0) {
                avg = weightedSum / totalWeight;
            }
        }

        if (avg !== null) {
            const rounded = Math.round(avg * 10) / 10;
            badge.textContent = rounded.toFixed(1);
            let cls = 'level-excellent';
            if (rounded < 5.0) cls = 'level-danger';
            else if (rounded < 6.5) cls = 'level-warning';
            else if (rounded < 8.0) cls = 'level-good';
            badge.className = 'avg-badge ' + cls;
        } else {
            badge.textContent = '—';
            badge.className = 'avg-badge level-empty';
        }
    }

    // Attach listeners to all score inputs
    const inputs = document.querySelectorAll('.score-input');
    inputs.forEach(inp => {
        inp.addEventListener('focus', function () {
            this.select();
            const tr = this.closest('tr');
            if (tr) tr.classList.add('gb-row-focused');
            if (this.classList.contains('is-invalid')) {
                showTooltip(this, 'Điểm không hợp lệ: phải từ 0.0 đến 10.0');
            }
        });

        inp.addEventListener('blur', function () {
            hideTooltip();
            const tr = this.closest('tr');
            if (tr) tr.classList.remove('gb-row-focused');
            let v = this.value.trim().replace(',', '.');
            // Hỗ trợ gõ tắt tốc độ cao chuẩn VnEdu: gõ "85" -> "8.5", "75" -> "7.5"
            if (/^[1-9][0-9]$/.test(v) && v !== '10') {
                v = (parseInt(v, 10) / 10).toFixed(1);
                this.value = v;
                validateScoreInput(this);
                recalculateRow(this.dataset.studentId);
            } else if (v !== '' && !isNaN(v)) {
                const num = parseFloat(v);
                if (num >= 0 && num <= 10) {
                    this.value = num.toString();
                }
            }
            if (!this.classList.contains('is-invalid') && this.dataset.changed === '1') {
                this.dataset.changed = '0';
                this.classList.remove('is-saved-pulse');
                void this.offsetWidth;
                this.classList.add('is-saved-pulse');
            }
        });

        inp.addEventListener('input', function () {
            let cur = this.value;
            if (cur.includes(',')) {
                this.value = cur.replace(/,/g, '.');
            }
            this.dataset.changed = '1';
            validateScoreInput(this);
            recalculateRow(this.dataset.studentId);
            setDirty(true);
        });

        inp.addEventListener('keydown', function (e) {
            const row = parseInt(this.dataset.row, 10);
            const col = parseInt(this.dataset.col, 10);

            if (e.key === 'Enter') {
                e.preventDefault();
                if (e.shiftKey) {
                    if (row > 0) focusCell(row - 1, col);
                } else {
                    if (row < totalRows - 1) focusCell(row + 1, col);
                }
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (row < totalRows - 1) focusCell(row + 1, col);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (row > 0) focusCell(row - 1, col);
            } else if (e.key === 'Tab') {
                if (e.shiftKey) {
                    if (col > 0) {
                        e.preventDefault();
                        focusCell(row, col - 1);
                    } else if (row > 0) {
                        e.preventDefault();
                        focusCell(row - 1, 5);
                    }
                } else {
                    if (col < 5) {
                        e.preventDefault();
                        focusCell(row, col + 1);
                    } else if (row < totalRows - 1) {
                        e.preventDefault();
                        focusCell(row + 1, 0);
                    }
                }
            }
        });
    });

    if (form) {
        form.addEventListener('submit', function (e) {
            const invalid = document.querySelector('.score-input.is-invalid');
            if (invalid) {
                e.preventDefault();
                invalid.focus();
                invalid.select();
                showTooltip(invalid, 'Vui lòng sửa điểm này (0.0 đến 10.0) trước khi lưu!');
                alert('Có điểm không hợp lệ trong bảng điểm (phải từ 0.0 đến 10.0). Vui lòng kiểm tra lại!');
                return false;
            }
            isDirty = false;
        });
    }

    window.addEventListener('beforeunload', function (e) {
        if (isDirty) {
            e.preventDefault();
            e.returnValue = 'Bạn có thay đổi điểm chưa được lưu. Rời khỏi trang sẽ mất dữ liệu?';
            return e.returnValue;
        }
    });

    // LỌC PHÂN LOẠI HỌC SINH THEO HỌC LỰC & TÌM KIẾM
    let currentGbFilter = 'all';
    let currentGbSearch = '';

    window.applyGbFilter = function (filterVal, btn) {
        currentGbFilter = filterVal;
        document.querySelectorAll('.btn-gb-filter').forEach(function (b) { b.classList.remove('active'); });
        if (btn) btn.classList.add('active');
        filterGradebookRows();
    };

    window.applyGbSearch = function (searchVal) {
        currentGbSearch = (searchVal || '').toLowerCase().trim();
        filterGradebookRows();
    };

    function filterGradebookRows() {
        const rows = document.querySelectorAll('#gradebookTable tbody tr[data-student-id]');
        let count = 0;
        rows.forEach(function (r) {
            const tier = r.getAttribute('data-tier') || 'pending';
            const name = (r.getAttribute('data-name') || '').toLowerCase();
            const code = (r.getAttribute('data-code') || '').toLowerCase();

            let okTier = (currentGbFilter === 'all' || tier === currentGbFilter);
            let okSearch = (!currentGbSearch || name.includes(currentGbSearch) || code.includes(currentGbSearch));

            if (okTier && okSearch) {
                r.style.display = '';
                count++;
            } else {
                r.style.display = 'none';
            }
        });

        const emptyRow = document.getElementById('gbFilterEmptyRow');
        if (emptyRow) {
            emptyRow.style.display = (count === 0 && rows.length > 0) ? '' : 'none';
        }
    }
});
</script>
<?php
$extraScripts = '<script src="assets/js/main.js?v=' . filemtime(__DIR__ . '/assets/js/main.js') . '"></script>';
require_once __DIR__ . '/includes/footer.php';
?>
