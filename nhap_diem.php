<?php
/**
 * =============================================================================
 * Hệ Thống Quản Lý Điểm & Cảnh Báo Sớm THCS (EduRadar)
 * Module: Sổ Điểm Điện Tử Bộ Môn 2D Matrix (Chuẩn VnEdu & Thông tư 22)
 * Thiết kế: Ground-Up Redesign — Ngôn ngữ Hallmark v2 Cobalt Technical Telemetry
 * =============================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/xlsx_helper.php';
require_once __DIR__ . '/includes/algorithms.php';

require_login();

// 1. Nhận diện tiến độ năm học chuẩn VnEdu: thời điểm Giữa kì 1 (Cuối kỳ 1 khóa theo lịch)
$academicCalendar = get_academic_calendar_phase();
$isHk1Mid = ($academicCalendar['semester'] === 'HK1' && $academicCalendar['phase_key'] !== 'hk1_finals');

$pdo = db();
$realUserId = (int)$_SESSION['user_id'];
$realUserRole = $_SESSION['role'] ?? 'teacher';
$currentTeacherName = $_SESSION['full_name'] ?? $_SESSION['username'];

// 2. KHỬ GIẢ MẠO: Chỉ tài khoản Admin mới được sử dụng tham số ?teacher_id
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

// 3. Lấy danh sách lớp và môn được phân công
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
        if (!in_array($sub, $assignedClasses[$cid]['subjects'], true)) {
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

// 4. Xác định môn học được chọn (Cô lập ngữ cảnh theo từng tài khoản):
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

// 5. Xác định danh sách lớp thuộc môn học được chọn:
$classesInCurrentSubject = $teacherSubjects[$selectedSubject] ?? $assignedClasses;

// 6. Xác định lớp được chọn:
$selectedClassId = 0;
if (isset($_GET['class_id']) && (int)$_GET['class_id'] > 0) {
    $selectedClassId = (int)$_GET['class_id'];
} elseif ($userNavCtx['class_id'] > 0 && isset($classesInCurrentSubject[$userNavCtx['class_id']])) {
    $selectedClassId = $userNavCtx['class_id'];
} elseif (!empty($classesInCurrentSubject)) {
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

// 7. RÀO CHẮN BẢO MẬT & TỰ ĐỘNG ĐỒNG BỘ PHÂN CÔNG (BOLA/IDOR PREVENTION)
if ($realUserRole !== 'admin') {
    if (empty($assignedClasses)) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            http_response_code(403);
            log_action($realUserId, $_SESSION['username'], 'Truy cập trái phép bị chặn', 'Giáo viên chưa có phân công cố gắng gửi yêu cầu POST vào sổ điểm.');
            render_forbidden_page('Tài khoản chưa được phân công giảng dạy.');
            exit;
        }
    } else {
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
    if ($nTx > 0) {
        if ($ck !== null) {
            $avg = round(($sTx + 2 * ($gk ?? $ck) + 3 * $ck) / ($nTx + ($gk !== null ? 2 : 0) + 3), 1);
        } elseif ($gk !== null) {
            $avg = round(($sTx + 2 * $gk) / ($nTx + 2), 1);
        }
    }

    try {
        $oldScoreStmt = $pdo->prepare("SELECT tx1, tx2, tx3, tx4, gk, ck, avg_score FROM scores WHERE student_id = ? AND subject = ?");
        $oldScoreStmt->execute([$studentId, $postSubject]);
        $oldScores = $oldScoreStmt->fetch(PDO::FETCH_ASSOC);

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

        $fieldsToCheck = ['tx1' => $tx1, 'tx2' => $tx2, 'tx3' => $tx3, 'tx4' => $tx4, 'gk' => $gk, 'ck' => $ck];
        foreach ($fieldsToCheck as $fKey => $newFVal) {
            $oldFVal = isset($oldScores[$fKey]) && $oldScores[$fKey] !== null ? (float)$oldScores[$fKey] : null;
            if ($oldFVal !== $newFVal) {
                log_score_audit(
                    $studentId,
                    $postSubject,
                    $fKey,
                    $oldFVal,
                    $newFVal,
                    $realUserId,
                    $_SESSION['username'] ?? 'Giáo viên',
                    $oldScores ? 'update' : 'insert',
                    'Cập nhật điểm trực tiếp (AJAX)'
                );
            }
        }

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
            $existingScoreMap = [];
            if (!empty($validStudentMap)) {
                $idList = implode(',', array_map('intval', array_keys($validStudentMap)));
                $exQ = $pdo->query("SELECT student_id, tx1, tx2, tx3, tx4, gk, ck, avg_score FROM scores WHERE subject = " . $pdo->quote($selectedSubject) . " AND student_id IN ($idList)");
                foreach ($exQ->fetchAll(PDO::FETCH_ASSOC) as $exRow) {
                    $existingScoreMap[(int)$exRow['student_id']] = $exRow;
                }
            }

            foreach ($_POST['scores'] as $studentId => $row) {
                $studentId = (int)$studentId;
                if (!isset($validStudentMap[$studentId])) {
                    continue;
                }

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
                if ($nTx > 0) {
                    if ($ck !== null) {
                        $avg = round(($sTx + 2 * ($gk ?? $ck) + 3 * $ck) / ($nTx + ($gk !== null ? 2 : 0) + 3), 1);
                    } elseif ($gk !== null) {
                        $avg = round(($sTx + 2 * $gk) / ($nTx + 2), 1);
                    }
                }

                $upsertStmt->execute([$studentId, $selectedSubject, $tx1, $tx2, $tx3, $tx4, $gk, $ck, $avg]);
                $savedCount++;

                $exStudent = $existingScoreMap[$studentId] ?? null;
                $chkFields = ['tx1' => $tx1, 'tx2' => $tx2, 'tx3' => $tx3, 'tx4' => $tx4, 'gk' => $gk, 'ck' => $ck];
                foreach ($chkFields as $fK => $nVal) {
                    $oVal = isset($exStudent[$fK]) && $exStudent[$fK] !== null ? (float)$exStudent[$fK] : null;
                    if ($oVal !== $nVal) {
                        log_score_audit(
                            $studentId,
                            $selectedSubject,
                            $fK,
                            $oVal,
                            $nVal,
                            $realUserId,
                            $_SESSION['username'] ?? 'Giáo viên',
                            $exStudent ? 'update' : 'insert',
                            'Lưu bảng điểm lớp'
                        );
                    }
                }
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

                    $fullName = '';
                    if (isset($colMap['full_name']) && isset($row[$colMap['full_name']])) {
                        $fullName = trim((string)$row[$colMap['full_name']]);
                    } elseif (isset($row[1]) && is_numeric($row[0])) {
                        $fullName = trim((string)$row[1]);
                    } elseif (isset($row[0])) {
                        $fullName = trim((string)$row[0]);
                    }

                    if ($fullName === '' || mb_stripos($fullName, 'tổng số') !== false) continue;

                    $studentCode = isset($colMap['student_code']) && isset($row[$colMap['student_code']]) ? trim((string)$row[$colMap['student_code']]) : null;
                    if ($studentCode === '') $studentCode = null;

                    $rawBday = isset($colMap['birthday']) && isset($row[$colMap['birthday']]) ? $row[$colMap['birthday']] : null;
                    $normBday = normalize_excel_date($rawBday);

                    $phone = '';
                    if (isset($colMap['phone']) && isset($row[$colMap['phone']])) {
                        $phone = preg_replace('/[^0-9]/', '', (string)$row[$colMap['phone']]);
                    }

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
                    if ($nTx > 0) {
                        if ($ck !== null) {
                            $avg = round(($sTx + 2 * ($gk ?? $ck) + 3 * $ck) / ($nTx + ($gk !== null ? 2 : 0) + 3), 1);
                        } elseif ($gk !== null) {
                            $avg = round(($sTx + 2 * $gk) / ($nTx + 2), 1);
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

// 8. Lấy danh sách học sinh
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

$pageTitle = 'Sổ Điểm Điện Tử Chuẩn VnEdu - EduRadar';
$activeNav = 'gradebook';

// =============================================================================
// HALLMARK COBALT THEME STYLES & PRECISION KEYCAPS FOR 2D GRADEBOOK
// =============================================================================
$extraHead = '
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Familjen+Grotesk:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* --- COBALT TELEMETRY TOKENS --- */
        :root {
            --cobalt-paper: #f8fafc;
            --cobalt-surface: #ffffff;
            --cobalt-ink: #0f172a;
            --cobalt-ink-muted: #475569;
            --cobalt-rule: #e2e8f0;
            --cobalt-rule-strong: #cbd5e1;
            --cobalt-accent: #2563eb;
            --cobalt-accent-edge: #1d4ed8;
            --cobalt-accent-ink: #ffffff;
            --cobalt-key-face: #ffffff;
            --cobalt-key-edge: #cbd5e1;
            --cobalt-key-ink: #0f172a;
            --cobalt-mono: "JetBrains Mono", ui-monospace, monospace;
            --cobalt-display: "Familjen Grotesk", var(--font-sans), sans-serif;
        }
        [data-theme="dark"], body.dark-mode {
            --cobalt-paper: #0b1120;
            --cobalt-surface: #121a2d;
            --cobalt-ink: #f8fafc;
            --cobalt-ink-muted: #94a3b8;
            --cobalt-rule: #1e2c47;
            --cobalt-rule-strong: #334466;
            --cobalt-accent: #3b82f6;
            --cobalt-accent-edge: #1d4ed8;
            --cobalt-accent-ink: #ffffff;
            --cobalt-key-face: #1a253c;
            --cobalt-key-edge: #2e3e5c;
            --cobalt-key-ink: #f8fafc;
        }

        /* --- 3D KEYCAP BUTTONS (FITTS-COMPLIANT >= 48x48px) --- */
        .key {
            border: 1px solid var(--cobalt-rule-strong);
            border-radius: 8px;
            padding: 0 18px;
            min-height: 48px;
            min-width: 48px;
            background: var(--cobalt-key-face);
            color: var(--cobalt-key-ink);
            font-family: var(--cobalt-display);
            font-weight: 600;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            box-shadow: 0 3px 0 0 var(--cobalt-key-edge), 0 6px 10px -4px rgba(15,23,42,0.12), inset 0 1px 0 rgba(255,255,255,0.8);
            transition: transform 90ms cubic-bezier(0.2, 0.8, 0.2, 1), box-shadow 90ms cubic-bezier(0.2, 0.8, 0.2, 1);
            text-decoration: none;
            user-select: none;
            box-sizing: border-box;
        }
        .key:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 0 0 var(--cobalt-key-edge), 0 8px 14px -4px rgba(15,23,42,0.16);
            color: var(--cobalt-key-ink);
        }
        .key:active {
            transform: translateY(3px);
            box-shadow: inset 0 2px 3px rgba(15,23,42,0.18);
        }
        .key--primary {
            background: var(--cobalt-accent);
            border-color: var(--cobalt-accent-edge);
            color: var(--cobalt-accent-ink) !important;
            box-shadow: 0 3px 0 0 var(--cobalt-accent-edge), 0 6px 12px -3px rgba(37,99,235,0.38), inset 0 1px 0 rgba(255,255,255,0.25);
        }
        .key--primary:hover {
            background: #1d4ed8;
            box-shadow: 0 4px 0 0 #1e40af, 0 8px 16px -3px rgba(37,99,235,0.45);
        }
        .key--primary:active {
            transform: translateY(3px);
            box-shadow: inset 0 2px 4px rgba(15,23,42,0.3);
        }
        .key--sm {
            min-height: 38px;
            padding: 0 12px;
            font-size: 13px;
        }

        /* --- CUT-CORNER PANELS --- */
        .cut-card {
            background: var(--cobalt-surface);
            border: 1px solid var(--cobalt-rule);
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(15,23,42,0.04);
            transition: border-color 0.2s ease;
        }
        @supports (corner-shape: bevel) {
            .cut-card {
                border-radius: 14px 4px 14px 4px;
                corner-shape: bevel round bevel round;
            }
        }

        /* --- CONTROL STRIP --- */
        .telemetry-strip {
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .telemetry-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        .telemetry-pills {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .telemetry-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--cobalt-paper);
            border: 1px solid var(--cobalt-rule);
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            color: var(--cobalt-ink-muted);
            font-family: "IBM Plex Sans", system-ui, sans-serif;
        }
        .telemetry-pill strong {
            color: var(--cobalt-ink);
            font-weight: 600;
        }
        .telemetry-pill.pill-accent {
            background: rgba(37,99,235,0.08);
            border-color: rgba(37,99,235,0.3);
            color: var(--cobalt-accent);
        }
        .telemetry-pill.pill-success {
            background: rgba(16,185,129,0.08);
            border-color: rgba(16,185,129,0.3);
            color: #059669;
        }

        /* --- SELECTORS SUNKEN TRAY --- */
        .selector-tray {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .cobalt-select {
            min-height: 44px;
            padding: 0 14px;
            border-radius: 8px;
            border: 1px solid var(--cobalt-rule-strong);
            background: var(--cobalt-key-face);
            color: var(--cobalt-ink);
            font-family: var(--cobalt-display);
            font-weight: 600;
            font-size: 13.5px;
            cursor: pointer;
            outline: none;
            box-shadow: 0 2px 0 0 var(--cobalt-key-edge);
        }
        .cobalt-select:focus {
            border-color: var(--cobalt-accent);
            box-shadow: 0 0 0 2px rgba(37,99,235,0.25);
        }

        /* --- SCHEDULE & PROGRESS HAIRLINE DOCK --- */
        .schedule-hairline {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 18px;
            border-radius: 10px;
            border: 1px solid var(--cobalt-rule);
            background: var(--cobalt-paper);
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .schedule-info {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: var(--cobalt-ink);
        }

        /* --- TOOLBAR & FILTER TRAY --- */
        .gradebook-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 18px;
            margin-bottom: 14px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .gradebook-filter-tray {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        .tray-key {
            border: 1px solid var(--cobalt-rule);
            background: var(--cobalt-key-face);
            color: var(--cobalt-ink-muted);
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 12.5px;
            font-weight: 600;
            font-family: var(--cobalt-display);
            cursor: pointer;
            box-shadow: 0 2px 0 0 var(--cobalt-key-edge);
            transition: all 90ms ease;
        }
        .tray-key:hover {
            color: var(--cobalt-ink);
            border-color: var(--cobalt-rule-strong);
        }
        .tray-key.active {
            background: var(--cobalt-accent);
            border-color: var(--cobalt-accent-edge);
            color: #ffffff;
            box-shadow: 0 2px 0 0 var(--cobalt-accent-edge);
        }
        .cobalt-search-input {
            min-height: 40px;
            padding: 0 14px;
            border-radius: 6px;
            border: 1px solid var(--cobalt-rule-strong);
            background: var(--cobalt-key-face);
            color: var(--cobalt-ink);
            font-family: "IBM Plex Sans", system-ui, sans-serif;
            font-size: 13px;
            outline: none;
            width: 230px;
            box-shadow: inset 0 1px 2px rgba(15,23,42,0.06);
        }
        .cobalt-search-input:focus {
            border-color: var(--cobalt-accent);
            box-shadow: 0 0 0 2px rgba(37,99,235,0.25);
        }

        /* --- 2D MATRIX SPREADSHEET TABLE --- */
        .spreadsheet-matrix-wrap {
            border: 1px solid var(--cobalt-rule);
            border-radius: 12px;
            overflow-x: auto;
            background: var(--cobalt-surface);
            box-shadow: 0 2px 6px rgba(15,23,42,0.04);
            margin-bottom: 20px;
        }
        .spreadsheet-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-family: "IBM Plex Sans", system-ui, sans-serif;
            font-size: 13.5px;
        }
        .spreadsheet-table th {
            background: var(--cobalt-paper);
            color: var(--cobalt-ink);
            font-family: var(--cobalt-display);
            font-weight: 600;
            font-size: 12px;
            letter-spacing: -0.01em;
            text-transform: uppercase;
            padding: 10px 8px;
            border-bottom: 1px solid var(--cobalt-rule-strong);
            border-right: 1px solid var(--cobalt-rule);
            text-align: center;
            vertical-align: middle;
            white-space: nowrap;
        }
        .spreadsheet-table td {
            padding: 6px 8px;
            border-bottom: 1px solid var(--cobalt-rule);
            border-right: 1px solid var(--cobalt-rule);
            vertical-align: middle;
            background: var(--cobalt-surface);
        }
        .spreadsheet-table tr:hover td {
            background: rgba(37,99,235,0.025);
        }
        .spreadsheet-table tr.gb-row-focused td {
            background: rgba(37,99,235,0.04);
        }

        /* Sticky Columns */
        .col-sticky-stt {
            position: sticky;
            left: 0;
            z-index: 3;
            background: var(--cobalt-surface) !important;
            width: 44px;
            text-align: center;
            font-family: var(--cobalt-mono);
            font-weight: 600;
            color: var(--cobalt-ink-muted);
        }
        .spreadsheet-table th.col-sticky-stt {
            background: var(--cobalt-paper) !important;
            z-index: 4;
        }
        .col-code {
            font-family: var(--cobalt-mono);
            font-size: 12.5px;
            font-weight: 600;
            color: var(--cobalt-accent);
            text-align: center;
            width: 90px;
        }
        .col-sticky-name {
            position: sticky;
            left: 44px;
            z-index: 3;
            background: var(--cobalt-surface) !important;
            min-width: 180px;
            border-right: 2px solid var(--cobalt-rule-strong) !important;
        }
        .spreadsheet-table th.col-sticky-name {
            background: var(--cobalt-paper) !important;
            z-index: 4;
            border-right: 2px solid var(--cobalt-rule-strong) !important;
        }
        .col-dob {
            text-align: center;
            width: 95px;
            font-size: 12.5px;
            color: var(--cobalt-ink-muted);
        }

        /* Score Groups Styling */
        .group-tx {
            background: rgba(37,99,235,0.03) !important;
        }
        .group-gk {
            background: rgba(245,158,11,0.04) !important;
            border-left: 2px solid var(--cobalt-rule-strong) !important;
        }
        .group-ck {
            background: rgba(239,68,68,0.04) !important;
            border-left: 1px dashed var(--cobalt-rule-strong) !important;
        }
        .group-avg {
            background: rgba(16,185,129,0.05) !important;
            border-left: 2px solid var(--cobalt-rule-strong) !important;
            min-width: 80px;
        }

        /* --- MONOSPACE SCORE INPUTS (TABULAR FIGURES) --- */
        .score-input {
            width: 100%;
            min-width: 52px;
            height: 38px;
            text-align: center;
            font-family: var(--cobalt-mono) !important;
            font-size: 15px !important;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
            color: var(--cobalt-ink);
            background: var(--cobalt-surface);
            border: 1px solid var(--cobalt-rule);
            border-radius: 6px;
            outline: none;
            padding: 0 4px;
            transition: all 120ms ease;
            box-sizing: border-box;
        }
        .score-input:hover {
            border-color: var(--cobalt-rule-strong);
        }
        .score-input:focus {
            background: var(--cobalt-paper);
            border-color: var(--cobalt-accent);
            box-shadow: 0 0 0 2px rgba(37,99,235,0.3);
            color: var(--cobalt-accent);
        }
        .score-input.is-invalid {
            border-color: #ef4444 !important;
            background: #fef2f2 !important;
            color: #dc2626 !important;
        }
        .score-input.is-locked-vnedu {
            background: var(--cobalt-paper);
            color: var(--cobalt-ink-muted);
            cursor: not-allowed;
            border-style: dashed;
        }
        .score-input.is-saved-pulse {
            animation: pulse-saved 0.6s ease;
        }
        @keyframes pulse-saved {
            0% { background-color: rgba(16,185,129,0.25); }
            100% { background-color: var(--cobalt-surface); }
        }

        /* Average Badge */
        .avg-badge {
            display: inline-block;
            font-family: var(--cobalt-mono);
            font-weight: 700;
            font-size: 14.5px;
            font-variant-numeric: tabular-nums;
            padding: 4px 10px;
            border-radius: 6px;
            text-align: center;
            min-width: 44px;
        }
        .avg-badge.level-excellent {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #86efac;
        }
        .avg-badge.level-good {
            background: #e0f2fe;
            color: #0369a1;
            border: 1px solid #7dd3fc;
        }
        .avg-badge.level-warning {
            background: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
        }
        .avg-badge.level-danger {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fca5a5;
        }
        .avg-badge.level-empty {
            background: var(--cobalt-paper);
            color: var(--cobalt-ink-muted);
            border: 1px solid var(--cobalt-rule);
        }

        /* Dirty Save Badge */
        .dirty-badge {
            font-family: var(--cobalt-display);
            font-size: 12.5px;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .dirty-badge.clean {
            background: rgba(16,185,129,0.1);
            color: #059669;
            border: 1px solid rgba(16,185,129,0.25);
        }
        .dirty-badge.dirty {
            background: rgba(245,158,11,0.12);
            color: #d97706;
            border: 1px solid rgba(245,158,11,0.3);
            animation: pulse-dirty 1.5s infinite alternate;
        }
        @keyframes pulse-dirty {
            from { opacity: 0.8; }
            to { opacity: 1; }
        }

        /* Tooltip */
        .score-tooltip {
            position: fixed;
            z-index: 1000;
            background: #0f172a;
            color: #ffffff;
            font-size: 12px;
            font-family: "IBM Plex Sans", system-ui, sans-serif;
            padding: 6px 12px;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            pointer-events: none;
            transform: translateY(-100%);
        }

        /* --- UTILITY PANELS (IMPORT & EXPORT) --- */
        .utility-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 20px;
            margin-top: 24px;
        }
        .utility-panel {
            padding: 22px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .panel-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
        }
        .panel-icon-wrap {
            width: 42px;
            height: 42px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--cobalt-paper);
            border: 1px solid var(--cobalt-rule);
            color: var(--cobalt-accent);
        }
    </style>
';

$breadcrumbs = [
    ['title' => 'Trang Chủ', 'url' => 'welcome.php'],
    ['title' => 'Sổ Điểm Điện Tử', 'url' => 'nhap_diem.php'],
    ['title' => 'Lớp ' . ($curClassName ?? '') . ' • Môn ' . ($selectedSubject ?? ''), 'url' => '']
];
require_once __DIR__ . '/includes/header.php';
?>

    <!-- 1. TELEMETRY & NAVIGATION STRIP (COBALT CUT-CARD) -->
    <section class="cut-card telemetry-strip" aria-label="Thanh điều khiển chuyên môn và lớp học">
        <div class="telemetry-row">
            <div class="telemetry-pills">
                <div class="telemetry-pill pill-accent">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                    <span data-i18n="gradebook.title">Sổ Điểm Điện Tử 2D</span>
                </div>
                <div class="telemetry-pill">
                    <span data-i18n="gradebook.class_label">Lớp:</span>
                    <strong><?= htmlspecialchars($curClassName) ?></strong>
                    <span style="opacity: 0.75;">(Khối <?= htmlspecialchars((string)($classesInCurrentSubject[$selectedClassId]['grade'] ?? '')) ?>)</span>
                </div>
                <div class="telemetry-pill">
                    <span data-i18n="gradebook.subject_label">Môn học:</span>
                    <strong style="color: var(--cobalt-accent);"><?= htmlspecialchars($selectedSubject) ?></strong>
                    <span style="opacity: 0.75;">• <?= htmlspecialchars($headerCalendar['semester_name'] ?? 'Học kỳ I') ?></span>
                </div>
                <div class="telemetry-pill">
                    <span data-i18n="gradebook.teacher_label">Giáo viên:</span>
                    <strong><?= htmlspecialchars($currentTeacherName) ?></strong>
                </div>
                <div class="telemetry-pill pill-success">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    <span data-i18n="gradebook.permission_status">Quyền hạn: Có thể nhập điểm</span>
                </div>
            </div>

            <!-- SELECTORS FORM -->
            <div class="selector-tray">
                <form method="GET" style="display: flex; gap: 8px; align-items: center; margin: 0;">
                    <?php if ($actingTeacherId > 0 && $realUserRole === 'admin'): ?>
                        <input type="hidden" name="teacher_id" value="<?= $actingTeacherId ?>">
                    <?php endif; ?>
                    <select name="class_id" onchange="this.form.submit()" class="cobalt-select" aria-label="Chọn lớp học" title="Chọn lớp học">
                        <?php if (empty($classesInCurrentSubject)): ?>
                            <option value="">(Chưa có lớp cho môn này)</option>
                        <?php else: ?>
                            <?php foreach ($classesInCurrentSubject as $cid => $c): ?>
                                <?php if ($c['student_count'] > 0 || $cid == $selectedClassId): ?>
                                    <option value="<?= $cid ?>" <?= $cid == $selectedClassId ? 'selected' : '' ?>>
                                        Lớp <?= htmlspecialchars($c['name']) ?> (<?= $c['student_count'] ?> HS)
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <select name="subject" onchange="this.form.class_id.value=''; this.form.submit()" class="cobalt-select" aria-label="Chọn phân môn phụ trách" title="Chọn phân môn phụ trách">
                        <?php 
                            $allSubjectsToDisplay = !empty($teacherSubjects) ? array_keys($teacherSubjects) : $availableSubjectsForClass;
                            foreach ($allSubjectsToDisplay as $sub): 
                        ?>
                            <option value="<?= $sub ?>" <?= $sub === $selectedSubject ? 'selected' : '' ?>>Môn <?= $sub ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>

        <!-- QUICK LINKS & ACTIONS -->
        <div class="telemetry-row" style="border-top: 1px solid var(--cobalt-rule); padding-top: 12px;">
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <button type="button" onclick="document.getElementById('btnSaveScores').click();" class="key key--primary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    <span data-i18n="gradebook.save_button">Lưu bảng điểm</span>
                </button>
                <a href="sample_gradebook.xlsx" download class="key">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <span data-i18n="gradebook.sample_excel">Tải file mẫu Excel</span>
                </a>
                <a href="nhap_diem.php?class_id=<?= $selectedClassId ?>&subject=<?= urlencode($selectedSubject) ?>" class="key">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                    <span data-i18n="gradebook.reload_button">Làm mới</span>
                </a>
            </div>

            <div style="display: flex; align-items: center; gap: 10px;">
                <span class="dirty-badge clean" id="saveStatusBadgeTop" data-i18n="gradebook.server_saved_badge">Đã lưu trên máy chủ</span>
            </div>
        </div>
    </section>

    <!-- ALERTS -->
    <?php if ($msg): ?>
        <div class="alert success mb-3" style="background: rgba(16,185,129,0.12); color: #065f46; border: 1px solid #a7f3d0; padding: 12px 18px; border-radius: 8px; margin-bottom: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
            <?= get_svg_icon('check-circle', 16) ?>
            <span><?= htmlspecialchars($msg) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
        <div class="alert error mb-3" style="background: rgba(239,68,68,0.12); color: #991b1b; border: 1px solid #fecaca; padding: 12px 18px; border-radius: 8px; margin-bottom: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
            <?= get_svg_icon('alert-triangle', 16) ?>
            <span><?= htmlspecialchars($errorMsg) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($realUserRole === 'admin' && $actingTeacherId > 0): ?>
        <div class="alert mb-3" style="background: rgba(37,99,235,0.1); color: #1e40af; border: 1px solid #bfdbfe; padding: 12px 18px; border-radius: 8px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
            <?= get_svg_icon('shield-check', 16) ?>
            <span><b>Chế độ Admin:</b> Bạn đang kiểm tra và có thể chỉnh sửa sổ điểm môn <b><?= htmlspecialchars($selectedSubject) ?></b> của giáo viên này.</span>
            <a href="admin.php" style="margin-left: 10px; font-weight: bold; text-decoration: underline;">Quay lại Admin</a>
        </div>
    <?php endif; ?>

    <?php if (empty($myClasses)): ?>
        <div class="cut-card" style="text-align: center; padding: 48px 24px; margin-bottom: 24px;">
            <h3 style="font-family: var(--cobalt-display); font-size: 18px; font-weight: 700; color: var(--cobalt-ink);">Bạn chưa được phân công lớp phụ trách</h3>
            <p style="color: var(--cobalt-ink-muted); margin-top: 6px; font-size: 14px;">Vui lòng liên hệ tài khoản Admin trường để được tích chọn cấp quyền vào các lớp khối 6, 7, 8, 9.</p>
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

    <!-- 2. VNEDU SCHEDULE TIMELINE BAR (HAIRLINE PRECISION) -->
    <?php if ($isHk1Mid): ?>
    <div class="schedule-hairline" id="vneduScheduleBar">
        <div class="schedule-info">
            <span style="color: #d97706;"><?= get_svg_icon('lock', 16) ?></span>
            <div>
                <b data-i18n="gradebook.schedule_title">Khung Thời Gian Nhập Điểm:</b>
                <span data-i18n="gradebook.schedule_desc">Hiện đang trong đợt Giữa kỳ I. Cột điểm <b>Cuối kỳ I (CK1)</b> tạm thời khóa theo tiến độ VnEdu (dự kiến mở chính thức từ 15/12).</span>
            </div>
        </div>
        <button type="button" class="key key--sm" id="btnVneduToggleLock" onclick="toggleVnEduCkLock()" title="Bấm để mở khóa nhập sớm nếu nhà trường tổ chức thi sớm">
            <span id="btnLockIcon"><?= get_svg_icon('unlock', 14) ?></span>
            <span id="btnLockLabel" data-i18n="gradebook.unlock_early">Mở khóa nhập sớm</span>
        </button>
    </div>
    <?php endif; ?>

    <!-- 3. GRADEBOOK TOOLBAR WITH COBALT KEYCAPS & INSTANT SEARCH -->
    <div class="cut-card gradebook-toolbar">
        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            <div style="font-family: var(--cobalt-display); font-weight: 700; font-size: 16px; color: var(--cobalt-ink);">
                <span>Sổ Điểm Môn <?= htmlspecialchars($selectedSubject) ?> — Lớp <?= htmlspecialchars($curClassName) ?></span>
            </div>
            <span style="font-family: var(--cobalt-mono); font-size: 12px; font-weight: 700; background: rgba(37,99,235,0.1); color: var(--cobalt-accent); padding: 4px 10px; border-radius: 6px; border: 1px solid rgba(37,99,235,0.25);">
                SĨ SỐ: <?= count($studentList) ?> HS
            </span>
        </div>

        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            <span id="saveStatusBadge" class="dirty-badge clean" data-i18n="gradebook.server_saved_badge">Đã lưu trên máy chủ</span>
            <!-- HARD CONTRACT: id="btnSaveScores" and text contains "Lưu bảng điểm" -->
            <button type="submit" form="gradebookForm" id="btnSaveScores" class="key key--primary" style="font-size: 14px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                <span data-i18n="gradebook.save_button">Lưu bảng điểm</span>
            </button>
        </div>
    </div>

    <!-- 4. FILTER PILLS & INSTANT SEARCH TRAY -->
    <div class="cut-card" style="padding: 12px 18px; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div class="gradebook-filter-tray">
            <span style="font-size: 11.5px; font-weight: 700; font-family: var(--cobalt-display); text-transform: uppercase; letter-spacing: 0.05em; color: var(--cobalt-ink-muted);" data-i18n="gradebook.filter_label">
                Lọc phân loại:
            </span>
            <button type="button" class="tray-key active" data-gb-filter="all" onclick="applyGbFilter('all', this)">
                <span data-i18n="gradebook.filter_all">Tất cả</span> (<?= count($studentList) ?>)
            </button>
            <button type="button" class="tray-key" data-gb-filter="excellent" onclick="applyGbFilter('excellent', this)">
                <span data-i18n="gradebook.filter_excellent">Giỏi</span> (<?= $gbCountExcellent ?>)
            </button>
            <button type="button" class="tray-key" data-gb-filter="good" onclick="applyGbFilter('good', this)">
                <span data-i18n="gradebook.filter_good">Khá</span> (<?= $gbCountGood ?>)
            </button>
            <button type="button" class="tray-key" data-gb-filter="average" onclick="applyGbFilter('average', this)">
                <span data-i18n="gradebook.filter_average">Đạt</span> (<?= $gbCountAverage ?>)
            </button>
            <button type="button" class="tray-key" data-gb-filter="low" onclick="applyGbFilter('low', this)">
                <span data-i18n="gradebook.filter_low">Phụ đạo</span> (<?= $gbCountLow ?>)
            </button>
            <?php if ($gbCountPending > 0): ?>
            <button type="button" class="tray-key" data-gb-filter="pending" onclick="applyGbFilter('pending', this)">
                <span data-i18n="gradebook.filter_pending">Chưa có điểm</span> (<?= $gbCountPending ?>)
            </button>
            <?php endif; ?>
        </div>

        <div>
            <input type="text" id="gbSearchInput" oninput="applyGbSearch(this.value)" class="cobalt-search-input" placeholder="Tìm tên, mã HS..." title="Tìm kiếm học sinh theo tên hoặc mã số" aria-label="Tìm kiếm học sinh theo tên hoặc mã số" data-i18n-placeholder="gradebook.search_placeholder">
        </div>
    </div>

    <!-- 5. 2D GRADEBOOK MATRIX SPREADSHEET FORM -->
    <form method="POST" id="gradebookForm">
        <input type="hidden" name="action" value="save_scores">
        
        <div class="spreadsheet-matrix-wrap">
            <table class="spreadsheet-table" id="gradebookTable">
                <thead>
                    <!-- TIER 1 HEADER: SUPER-GROUPS (HARD CONTRACT FOR TESTS: 'ĐĐGtx', 'ĐĐGgk', 'ĐĐGck', 'ĐTBmhk') -->
                    <tr>
                        <th rowspan="2" class="col-sticky-stt" data-i18n="gradebook.th_stt">STT</th>
                        <th rowspan="2" class="col-code" data-i18n="gradebook.th_code">Mã HS</th>
                        <th rowspan="2" class="col-sticky-name" data-i18n="gradebook.th_name">Họ và Tên Học Sinh</th>
                        <th rowspan="2" class="col-dob" data-i18n="gradebook.th_dob">Ngày Sinh</th>
                        <th colspan="4" class="group-tx">
                            <span data-i18n="gradebook.th_tx">ĐĐG THƯỜNG XUYÊN (HS 1)</span>
                        </th>
                        <th rowspan="2" class="group-gk">
                            <span data-i18n="gradebook.th_gk">GIỮA KỲ</span><br>
                            <span style="font-size: 11px; opacity: 0.8; font-weight: 500;">HS 2</span>
                        </th>
                        <th rowspan="2" class="group-ck" id="thCkHeader">
                            <span data-i18n="gradebook.th_ck">CUỐI KỲ</span><br>
                            <span style="font-size: 11px; opacity: 0.8; font-weight: 500;">HS 3</span>
                            <?php if ($isHk1Mid): ?>
                                <span id="vneduLockIndicator" style="display: block; font-size: 10px; color: #d97706; margin-top: 2px;">
                                    <?= get_svg_icon('lock', 11) ?> Mở 15/12
                                </span>
                            <?php endif; ?>
                        </th>
                        <th rowspan="2" class="group-avg">
                            <span data-i18n="gradebook.th_avg">ĐTBmhk</span><br>
                            <span style="font-size: 11px; opacity: 0.8; font-weight: 500;" data-i18n="gradebook.th_auto">Tự động</span>
                        </th>
                    </tr>
                    <!-- TIER 2 HEADER: TX SUB-COLUMNS -->
                    <tr>
                        <th class="group-tx" style="font-family: var(--cobalt-mono); font-size: 11.5px;">TX1</th>
                        <th class="group-tx" style="font-family: var(--cobalt-mono); font-size: 11.5px;">TX2</th>
                        <th class="group-tx" style="font-family: var(--cobalt-mono); font-size: 11.5px;">TX3</th>
                        <th class="group-tx" style="font-family: var(--cobalt-mono); font-size: 11.5px;">TX4</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($studentList)): ?>
                        <tr>
                            <td colspan="11" style="text-align: center; padding: 48px 20px; color: var(--cobalt-ink-muted);">
                                <div style="margin-bottom: 10px;"><?= get_svg_icon('users', 36) ?></div>
                                <b style="font-size: 16px; color: var(--cobalt-ink); display: block; margin-bottom: 6px;">
                                    Lớp <?= htmlspecialchars($curClassName) ?> hiện chưa có danh sách học sinh (Sĩ số: 0)
                                </b>
                                <p style="margin-bottom: 18px; font-size: 13.5px; max-width: 600px; margin-left: auto; margin-right: auto;">
                                    Lớp này chưa có danh sách học sinh. Bạn có thể chọn lớp khác đã có dữ liệu bên dưới:
                                </p>
                                <div style="display: flex; gap: 8px; justify-content: center; flex-wrap: wrap;">
                                    <?php foreach ($assignedClasses as $altC): ?>
                                        <?php if ($altC['student_count'] > 0): ?>
                                            <a href="nhap_diem.php?class_id=<?= $altC['id'] ?>&subject=<?= urlencode($altC['subjects'][0] ?? 'Toán') ?><?= ($actingTeacherId > 0 && $realUserRole === 'admin') ? '&teacher_id=' . $actingTeacherId : '' ?>" class="key key--sm">
                                                <?= get_svg_icon('arrow-right', 13) ?>
                                                <span>Lớp <?= htmlspecialchars($altC['name']) ?> (<?= $altC['student_count'] ?> HS)</span>
                                            </a>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
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
                                    <div style="font-weight: 700; color: var(--cobalt-ink);"><?= htmlspecialchars($s['full_name']) ?></div>
                                    <?php if (!empty($s['phone'])): ?>
                                        <div style="font-size: 11.5px; color: var(--cobalt-ink-muted); font-family: var(--cobalt-mono);"><?= htmlspecialchars($s['phone']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="col-dob"><?= $dob ?></td>
                                
                                <!-- 4 CỘT TX (HỆ SỐ 1) -->
                                <td class="group-tx">
                                    <input type="text" inputmode="decimal" min="0" max="10" step="0.1" maxlength="4" class="score-input group-tx"
                                           name="scores[<?= $sId ?>][tx1]"
                                           value="<?= $s['tx1'] !== null ? (float)$s['tx1'] : '' ?>"
                                           title="Điểm TX1 của <?= htmlspecialchars($s['full_name']) ?>"
                                           aria-label="Điểm TX1 của <?= htmlspecialchars($s['full_name']) ?>"
                                           data-student-id="<?= $sId ?>" data-field="tx1"
                                           data-row="<?= $r ?>" data-col="0" placeholder="—" autocomplete="off">
                                </td>
                                <td class="group-tx">
                                    <input type="text" inputmode="decimal" min="0" max="10" step="0.1" maxlength="4" class="score-input group-tx"
                                           name="scores[<?= $sId ?>][tx2]"
                                           value="<?= $s['tx2'] !== null ? (float)$s['tx2'] : '' ?>"
                                           title="Điểm TX2 của <?= htmlspecialchars($s['full_name']) ?>"
                                           aria-label="Điểm TX2 của <?= htmlspecialchars($s['full_name']) ?>"
                                           data-student-id="<?= $sId ?>" data-field="tx2"
                                           data-row="<?= $r ?>" data-col="1" placeholder="—" autocomplete="off">
                                </td>
                                <td class="group-tx">
                                    <input type="text" inputmode="decimal" min="0" max="10" step="0.1" maxlength="4" class="score-input group-tx"
                                           name="scores[<?= $sId ?>][tx3]"
                                           value="<?= $s['tx3'] !== null ? (float)$s['tx3'] : '' ?>"
                                           title="Điểm TX3 của <?= htmlspecialchars($s['full_name']) ?>"
                                           aria-label="Điểm TX3 của <?= htmlspecialchars($s['full_name']) ?>"
                                           data-student-id="<?= $sId ?>" data-field="tx3"
                                           data-row="<?= $r ?>" data-col="2" placeholder="—" autocomplete="off">
                                </td>
                                <td class="group-tx">
                                    <input type="text" inputmode="decimal" min="0" max="10" step="0.1" maxlength="4" class="score-input group-tx"
                                           name="scores[<?= $sId ?>][tx4]"
                                           value="<?= $s['tx4'] !== null ? (float)$s['tx4'] : '' ?>"
                                           title="Điểm TX4 của <?= htmlspecialchars($s['full_name']) ?>"
                                           aria-label="Điểm TX4 của <?= htmlspecialchars($s['full_name']) ?>"
                                           data-student-id="<?= $sId ?>" data-field="tx4"
                                           data-row="<?= $r ?>" data-col="3" placeholder="—" autocomplete="off">
                                </td>
                                
                                <!-- CỘT GIỮA KỲ (HỆ SỐ 2) -->
                                <td class="group-gk">
                                    <input type="text" inputmode="decimal" min="0" max="10" step="0.1" maxlength="4" class="score-input group-gk"
                                           name="scores[<?= $sId ?>][gk]"
                                           value="<?= $s['gk'] !== null ? (float)$s['gk'] : '' ?>"
                                           title="Điểm Giữa kỳ của <?= htmlspecialchars($s['full_name']) ?>"
                                           aria-label="Điểm Giữa kỳ của <?= htmlspecialchars($s['full_name']) ?>"
                                           data-student-id="<?= $sId ?>" data-field="gk"
                                           data-row="<?= $r ?>" data-col="4" placeholder="—" autocomplete="off">
                                </td>
                                
                                <!-- CỘT CUỐI KỲ (HỆ SỐ 3) -->
                                <td class="group-ck">
                                    <input type="text" inputmode="decimal" min="0" max="10" step="0.1" maxlength="4" 
                                           class="score-input group-ck <?= ($isHk1Mid && $s['ck'] === null) ? 'is-locked-vnedu' : '' ?>"
                                           name="scores[<?= $sId ?>][ck]"
                                           value="<?= $s['ck'] !== null ? (float)$s['ck'] : '' ?>"
                                           title="<?= ($isHk1Mid && $s['ck'] === null) ? 'Cột Cuối kỳ I khóa theo lịch VnEdu (Mở từ 15/12). Bấm Mở khóa nhập sớm nếu cần nhập trước.' : 'Điểm Cuối kỳ của ' . htmlspecialchars($s['full_name']) ?>"
                                           aria-label="Điểm Cuối kỳ của <?= htmlspecialchars($s['full_name']) ?>"
                                           data-student-id="<?= $sId ?>" data-field="ck"
                                           data-row="<?= $r ?>" data-col="5" 
                                           placeholder="—" 
                                           autocomplete="off"
                                           <?= ($isHk1Mid && $s['ck'] === null) ? 'readonly' : '' ?>>
                                </td>
                                
                                <!-- CỘT ĐTB MÔN HỌC KỲ (TỰ ĐỘNG) -->
                                <td class="group-avg" style="text-align: center;">
                                    <span class="avg-badge <?= $avgClass ?>" data-avg-student="<?= $sId ?>">
                                        <?= $avg !== null ? $avg : '—' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php $r++; endforeach; ?>
                    <?php endif; ?>
                    <tr id="gbFilterEmptyRow" style="display: none;">
                        <td colspan="11" style="text-align: center; padding: 36px 20px; color: var(--cobalt-ink-muted); font-weight: 600;">
                            <div style="display: inline-flex; align-items: center; gap: 6px;">
                                <?= get_svg_icon('search', 16) ?>
                                <span data-i18n="gradebook.filter_empty">Không tìm thấy học sinh nào phù hợp với bộ lọc hoặc từ khóa tìm kiếm.</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 14px;">
            <p style="margin: 0; line-height: 1.6; font-size: 13px; color: var(--cobalt-ink-muted);" data-i18n="gradebook.keyboard_guide">
                <b>Phím tắt điều hướng:</b> Nhấn <code>Enter</code> hoặc <code>↓</code> để xuống dòng trong cùng cột; <code>↑</code> để lên trên; <code>←</code> <code>→</code> hoặc <code>Tab</code> sang ngang; tự động chọn số khi focus; nhập dấu phẩy <code>,</code> tự chuyển thành <code>.</code>
            </p>
            <button type="submit" class="key key--primary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                <span>Lưu bảng điểm môn <?= htmlspecialchars($selectedSubject) ?></span>
            </button>
        </div>
    </form>

    <!-- FLOATING TOOLTIP FOR ERROR HINTS -->
    <div id="scoreTooltip" class="score-tooltip" style="display: none;"></div>

    <!-- 6. UTILITY PANELS: IMPORT & EXPORT GRADEBOOK -->
    <div class="utility-grid">
        <!-- Khối 1: Nạp điểm bằng File -->
        <section class="cut-card utility-panel">
            <div>
                <div class="panel-header">
                    <div class="panel-icon-wrap">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    </div>
                    <div>
                        <h3 style="margin: 0; font-family: var(--cobalt-display); font-size: 15px; font-weight: 700; color: var(--cobalt-ink);" data-i18n="gradebook.import_title">
                            Nạp điểm bằng file (.xlsx, .csv)
                        </h3>
                        <p style="margin: 0; font-size: 12.5px; color: var(--cobalt-ink-muted);" data-i18n="gradebook.import_desc">
                            Nạp tự động điểm số chuẩn VnEdu vào lớp đang chọn
                        </p>
                    </div>
                </div>
                <form method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 14px;">
                    <input type="hidden" name="action" value="import_file">
                    <input type="file" name="gradebook_file" accept=".xlsx, .xls, .csv, .txt" required 
                           title="Chọn tệp bảng điểm Excel hoặc CSV" aria-label="Chọn tệp bảng điểm Excel hoặc CSV"
                           style="padding: 10px; border: 1px dashed var(--cobalt-rule-strong); border-radius: 8px; font-size: 13px; min-height: 48px; background: var(--cobalt-paper); color: var(--cobalt-ink);">
                    <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                        <button type="submit" class="key key--primary">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            <span data-i18n="gradebook.btn_upload_import">Tải lên & nhập sổ điểm</span>
                        </button>
                        <a href="sample_gradebook.xlsx" download class="key key--sm">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            <span data-i18n="gradebook.btn_sample_xlsx">File mẫu Excel (.xlsx)</span>
                        </a>
                        <a href="sample_vnedu.csv" download class="key key--sm">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            <span data-i18n="gradebook.btn_sample_csv">File mẫu CSV</span>
                        </a>
                    </div>
                </form>
            </div>
        </section>

        <!-- Khối 2: Xuất sổ điểm ra Excel -->
        <section class="cut-card utility-panel">
            <div>
                <div class="panel-header">
                    <div class="panel-icon-wrap" style="color: #059669; background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.25);">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    </div>
                    <div>
                        <h3 style="margin: 0; font-family: var(--cobalt-display); font-size: 15px; font-weight: 700; color: var(--cobalt-ink);" data-i18n="gradebook.export_title">
                            Xuất sổ điểm điện tử (.xlsx)
                        </h3>
                        <p style="margin: 0; font-size: 12.5px; color: var(--cobalt-ink-muted);" data-i18n="gradebook.export_desc">
                            Tải sổ điểm lớp <?= htmlspecialchars($curClassName) ?> môn <?= htmlspecialchars($selectedSubject) ?> chuẩn ECMA-376
                        </p>
                    </div>
                </div>
                <p style="font-size: 13px; color: var(--cobalt-ink-muted); line-height: 1.6; margin-top: 8px;" data-i18n="gradebook.export_info">
                    File Excel xuất ra chứa đầy đủ danh sách học sinh, mã định danh, ngày sinh, các cột ĐĐGtx 1..4, ĐĐGgk, ĐĐGck và ĐTBmhk tính tự động chuẩn Thông tư 22.
                </p>
            </div>
            <div style="margin-top: 18px;">
                <a href="nhap_diem.php?export=xlsx&class_id=<?= (int)$selectedClassId ?>&subject=<?= urlencode($selectedSubject) ?>" 
                   class="key key--primary" style="background: #059669; border-color: #047857;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <span>Xuất Excel sổ điểm môn <?= htmlspecialchars($selectedSubject) ?></span>
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
    const saveBadgeTop = document.getElementById('saveStatusBadgeTop');
    const form = document.getElementById('gradebookForm');
    const tooltip = document.getElementById('scoreTooltip');
    let isDirty = false;

    function setDirty(state) {
        isDirty = state;
        const badges = [saveBadge, saveBadgeTop].filter(Boolean);
        badges.forEach(b => {
            if (state) {
                b.className = 'dirty-badge dirty';
                b.textContent = '● Có thay đổi chưa lưu';
            } else {
                b.className = 'dirty-badge clean';
                b.textContent = 'Đã lưu trên máy chủ';
            }
        });
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
            if (window.gsap) {
                gsap.fromTo(badge, { scale: 1.15 }, { scale: 1, duration: 0.25, ease: 'back.out(2)' });
            }
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
            } else if (e.key === 'ArrowLeft') {
                e.preventDefault();
                if (col > 0) {
                    focusCell(row, col - 1);
                } else if (row > 0) {
                    focusCell(row - 1, 5);
                }
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                if (col < 5) {
                    focusCell(row, col + 1);
                } else if (row < totalRows - 1) {
                    focusCell(row + 1, 0);
                }
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

    // Toggle VnEdu CK Lock
    window.toggleVnEduCkLock = function () {
        const lockedInputs = document.querySelectorAll('.score-input.group-ck');
        const btnLabel = document.getElementById('btnLockLabel');
        const lockIndicator = document.getElementById('vneduLockIndicator');
        let isCurrentlyLocked = false;

        lockedInputs.forEach(inp => {
            if (inp.hasAttribute('readonly')) {
                isCurrentlyLocked = true;
                inp.removeAttribute('readonly');
                inp.classList.remove('is-locked-vnedu');
            } else {
                inp.setAttribute('readonly', 'readonly');
                inp.classList.add('is-locked-vnedu');
            }
        });

        if (btnLabel) {
            btnLabel.textContent = isCurrentlyLocked ? 'Khóa lại theo lịch' : 'Mở khóa nhập sớm';
        }
        if (lockIndicator) {
            lockIndicator.style.display = isCurrentlyLocked ? 'none' : 'block';
        }
    };

    // Filter by Tier and Search
    let currentGbFilter = 'all';
    let currentGbSearch = '';

    window.applyGbFilter = function (filterVal, btn) {
        currentGbFilter = filterVal;
        document.querySelectorAll('.tray-key').forEach(function (b) { b.classList.remove('active'); });
        if (btn) {
            btn.classList.add('active');
            if (window.gsap) {
                gsap.fromTo(btn, { scale: 0.95 }, { scale: 1, duration: 0.2, ease: 'back.out(2)', clearProps: 'transform' });
            }
        }
        filterGradebookRows();
    };

    window.applyGbSearch = function (searchVal) {
        currentGbSearch = (searchVal || '').toLowerCase().trim();
        filterGradebookRows();
    };

    function filterGradebookRows() {
        const rows = document.querySelectorAll('#gradebookTable tbody tr[data-student-id]');
        let count = 0;
        const visibleRows = [];
        rows.forEach(function (r) {
            const tier = r.getAttribute('data-tier') || 'pending';
            const name = (r.getAttribute('data-name') || '').toLowerCase();
            const code = (r.getAttribute('data-code') || '').toLowerCase();

            let okTier = (currentGbFilter === 'all' || tier === currentGbFilter);
            let okSearch = (!currentGbSearch || name.includes(currentGbSearch) || code.includes(currentGbSearch));

            if (okTier && okSearch) {
                r.style.display = '';
                count++;
                visibleRows.push(r);
            } else {
                r.style.display = 'none';
            }
        });

        if (window.gsap && visibleRows.length > 0) {
            gsap.killTweensOf(visibleRows);
            gsap.fromTo(visibleRows.slice(0, 30),
                { opacity: 0, y: 6 },
                { opacity: 1, y: 0, duration: 0.22, stagger: 0.01, ease: 'power2.out', clearProps: 'transform' }
            );
        }

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
