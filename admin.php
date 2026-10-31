<?php
/**
 * =============================================================================
 * Hệ Thống Quản Lý Điểm & Cảnh Báo Sớm THCS (EduRadar / Chuẩn VnEdu)
 * Cổng Quản Trị Trung Tâm Dành Riêng Cho Quản Trị Viên Nhà Trường (Admin)
 * 
 * Tính năng chính:
 * 1. Quản lý danh sách lớp học THCS (Khối 6–9, Ban A–C, Lớp 1–9) & Ma trận lớp
 * 2. Phân công giáo viên hàng loạt bằng ma trận checkbox với chọn/bỏ chọn theo khối
 * 3. Tạo mới tài khoản giáo viên với mật khẩu băm Bcrypt chuẩn bảo mật
 * 4. Nạp danh sách học sinh theo lớp chuẩn VnEdu (.xlsx / .csv) chống trùng lặp
 * 5. Bảng phân công giảng dạy toàn trường & Giám sát trực tiếp sổ điểm từng giáo viên
 * 6. Trình theo dõi nhật ký kiểm toán hệ thống thời gian thực (Real-time Audit Logs)
 * =============================================================================
 */

require_once __DIR__ . '/includes/auth.php';

// BẢO VỆ TUYẾN QUẢN TRỊ: CHỈ DUY NHẤT ROLE ADMIN ĐƯỢC PHÉP TRUY CẬP TRANG NÀY
require_admin();

$pdo = db();
$msg = '';
$err = '';

// Nạp helper OpenXML / CSV
require_once __DIR__ . '/includes/xlsx_helper.php';

/**
 * Nhận diện linh hoạt dòng tiêu đề và vị trí các cột trong file danh sách học sinh (VnEdu).
 * Quét 5 dòng đầu tiên để tìm các từ khóa chuẩn.
 */
function detect_student_roster_headers(array $rows): array {
    $fieldKeywords = [
        'full_name'    => ['họ và tên', 'họ tên', 'họ tên học sinh', 'tên học sinh', 'họ và tên hs'],
        'birthday'     => ['ngày sinh', 'ns', 'năm sinh', 'ngày/tháng/năm sinh', 'ngaysinh'],
        'student_code' => ['mã học sinh', 'mã hs', 'mã định danh', 'mã số', 'ma hs'],
        'gender'       => ['giới tính', 'nam/nữ', 'phái', 'gioitinh'],
        'class_name'   => ['lớp', 'tên lớp', 'lớp học', 'lop'],
        'phone'        => ['số điện thoại', 'sđt', 'điện thoại', 'sđt phụ huynh', 'điện thoại phụ huynh', 'phone'],
        'stt'          => ['stt', 'số tt', 'số thứ tự']
    ];

    $headerRowIndex = -1;
    $colMap = [];

    $scanLimit = min(5, count($rows));
    for ($r = 0; $r < $scanLimit; $r++) {
        $row = $rows[$r];
        if (!is_array($row) || empty($row)) continue;

        $tempMap = [];
        foreach ($row as $colIdx => $cellVal) {
            $norm = mb_strtolower(trim((string)$cellVal), 'UTF-8');
            if ($norm === '') continue;

            foreach ($fieldKeywords as $field => $keywords) {
                if (isset($tempMap[$field])) continue;
                foreach ($keywords as $kw) {
                    if (mb_strpos($norm, $kw) !== false || $norm === $kw) {
                        $tempMap[$field] = $colIdx;
                        break;
                    }
                }
            }
        }

        // Dòng tiêu đề hợp lệ khi có họ tên, hoặc có stt và ngày sinh
        if (isset($tempMap['full_name']) || (isset($tempMap['stt']) && isset($tempMap['birthday']))) {
            $headerRowIndex = $r;
            $colMap = $tempMap;
            break;
        }
    }

    // Nếu không tìm thấy dòng tiêu đề, giả định dòng 0 là dữ liệu theo các cột mặc định
    if ($headerRowIndex === -1) {
        $headerRowIndex = -1;
        $colCount = isset($rows[0]) ? count($rows[0]) : 0;
        if ($colCount >= 5) {
            $colMap = ['stt' => 0, 'full_name' => 1, 'birthday' => 2, 'gender' => 3, 'phone' => 4];
        } elseif ($colCount === 4) {
            $colMap = ['stt' => 0, 'full_name' => 1, 'birthday' => 2, 'phone' => 3];
        } else {
            $colMap = ['full_name' => 0, 'birthday' => 1, 'phone' => 2];
        }
    }

    return [$headerRowIndex, $colMap];
}

// -----------------------------------------------------------------------------
// 1. ACTION: TẠO LỚP HỌC MỚI (CREATE CLASS / ADD CLASS)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && ($_POST['action'] === 'create_class' || $_POST['action'] === 'add_class')) {
    require_admin();
    $grade = (int)($_POST['grade'] ?? 0);
    $section = strtoupper(trim((string)($_POST['section'] ?? 'A')));
    $classNum = (int)($_POST['class_num'] ?? 1);
    
    // Hỗ trợ cả trường 'name' và 'custom_name'
    $providedName = trim((string)($_POST['name'] ?? $_POST['custom_name'] ?? ''));
    if ($providedName !== '') {
        $className = strtoupper($providedName);
    } else {
        $className = "{$grade}{$section}{$classNum}";
    }

    if ($grade < 6 || $grade > 9) {
        $err = "Khối lớp không hợp lệ! Vui lòng chọn khối từ 6 đến 9.";
    } elseif (!in_array($section, ['A', 'B', 'C', 'D'])) {
        $err = "Ban/Tổ lớp không hợp lệ! Vui lòng chọn ban A, B hoặc C.";
    } elseif (!preg_match('/^[6-9][A-Z0-9_]{1,20}$/i', $className)) {
        $err = "Tên lớp [$className] không đúng định dạng chuẩn THCS!";
    } else {
        // Kiểm tra chống trùng lặp qua UNIQUE(name)
        $chk = $pdo->prepare("SELECT id FROM classes WHERE name = ?");
        $chk->execute([$className]);
        if ($chk->fetch()) {
            $err = "Lớp học [$className] đã tồn tại trong hệ thống trường!";
        } else {
            $ins = $pdo->prepare("INSERT INTO classes (name, grade, section) VALUES (?, ?, ?)");
            $ins->execute([$className, $grade, $section]);

            log_action(
                (int)$_SESSION['user_id'], 
                $_SESSION['username'] ?? 'admin', 
                'Tạo lớp học', 
                "Admin tạo mới lớp học [$className] (Khối $grade, Ban $section)."
            );
            $msg = "Đã tạo thành công lớp học [$className]!";
        }
    }
}

// -----------------------------------------------------------------------------
// 2. ACTION: XÓA LỚP HỌC (DELETE CLASS)
// -----------------------------------------------------------------------------
if ((isset($_GET['delete_class']) && (int)$_GET['delete_class'] > 0) || 
    ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_class')) {
    require_admin();
    $cid = isset($_POST['class_id']) ? (int)$_POST['class_id'] : (int)$_GET['delete_class'];

    if ($cid > 0) {
        $stmt = $pdo->prepare("
            SELECT id, name, grade,
                   (SELECT COUNT(*) FROM students WHERE class_id = classes.id) AS student_count
            FROM classes WHERE id = ?
        ");
        $stmt->execute([$cid]);
        $classInfo = $stmt->fetch();

        if ($classInfo) {
            $pdo->prepare("DELETE FROM classes WHERE id = ?")->execute([$cid]);
            log_action(
                (int)$_SESSION['user_id'], 
                $_SESSION['username'] ?? 'admin', 
                'Xóa lớp học', 
                "Admin đã xóa lớp học [{$classInfo['name']}] (Khối {$classInfo['grade']}, gồm {$classInfo['student_count']} học sinh)."
            );
            $msg = "Đã xóa thành công lớp [{$classInfo['name']}]!";
        } else {
            $err = "Lớp học cần xóa không tồn tại!";
        }
    }
}

// -----------------------------------------------------------------------------
// 3. ACTION: TẠO TÀI KHOẢN GIÁO VIÊN MỚI (CREATE TEACHER)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_teacher') {
    require_admin();
    $username = trim((string)($_POST['username'] ?? ''));
    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $fullName === '' || $password === '') {
        $err = "Vui lòng nhập đầy đủ Tên đăng nhập, Họ và tên và Mật khẩu khởi tạo!";
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        $err = "Tên đăng nhập chỉ được chứa chữ cái, số, gạch dưới và có độ dài 3-30 ký tự!";
    } elseif (strlen($password) < 6) {
        $err = "Mật khẩu khởi tạo phải có độ dài tối thiểu 6 ký tự!";
    } else {
        // Kiểm tra trùng username
        $chk = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $chk->execute([$username]);
        if ($chk->fetch()) {
            $err = "Tên đăng nhập [$username] đã tồn tại trên hệ thống!";
        } else {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $ins = $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, 'teacher')");
            $ins->execute([$username, $hashed, $fullName]);

            log_action(
                (int)$_SESSION['user_id'], 
                $_SESSION['username'] ?? 'admin', 
                'Tạo tài khoản giáo viên', 
                "Admin tạo mới tài khoản giáo viên: [$username] - [$fullName]."
            );
            $msg = "Đã tạo thành công tài khoản giáo viên [$fullName] (Tên đăng nhập: $username)!";
        }
    }
}

// -----------------------------------------------------------------------------
// 4. ACTION: PHÂN CÔNG GIẢNG DẠY HÀNG LOẠT (BATCH ASSIGN)
// Hỗ trợ chế độ Cập nhật môn (replace_subject), Làm mới toàn bộ (replace_all), và Thêm mới (append)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'batch_assign') {
    require_admin();
    $teacher_id = (int)($_POST['teacher_id'] ?? 0);
    $selected_classes = $_POST['class_ids'] ?? [];
    $assign_mode = trim((string)($_POST['assign_mode'] ?? 'replace_subject'));

    // Thu thập danh sách phân môn: môn chính + các môn thứ 2, thứ 3...
    $rawSubjects = [];
    if (!empty($_POST['subject'])) {
        $rawSubjects[] = trim((string)$_POST['subject']);
    }
    if (!empty($_POST['additional_subjects']) && is_array($_POST['additional_subjects'])) {
        foreach ($_POST['additional_subjects'] as $as) {
            $as = trim((string)$as);
            if ($as !== '' && !in_array($as, $rawSubjects, true)) {
                $rawSubjects[] = $as;
            }
        }
    }
    $is_multi_subject = !empty($_POST['is_multi_subject']) || count($rawSubjects) > 1;

    if ($teacher_id > 0 && !empty($rawSubjects) && !empty($selected_classes)) {
        $tStmt = $pdo->prepare("SELECT full_name, username FROM users WHERE id = ? AND role = 'teacher'");
        $tStmt->execute([$teacher_id]);
        $teacher = $tStmt->fetch();

        if (!$teacher) {
            $err = "Giáo viên được chọn không hợp lệ trong hệ thống!";
        } else {
            $pdo->beginTransaction();

            // Xử lý các chế độ phân công theo chỉ đạo của Admin:
            if ($assign_mode === 'replace_all') {
                // Xóa toàn bộ phân công cũ của giáo viên này để gán lại từ đầu
                $delAll = $pdo->prepare("DELETE FROM teacher_classes WHERE teacher_id = ?");
                $delAll->execute([$teacher_id]);
            } elseif ($assign_mode === 'replace_subject') {
                // Cập nhật phân môn: Xóa các lớp cũ của giáo viên này ở các môn được chọn mà KHÔNG nằm trong danh sách mới
                $cleanCids = array_map('intval', $selected_classes);
                if (!empty($cleanCids)) {
                    $inPlaceholders = implode(',', array_fill(0, count($cleanCids), '?'));
                    foreach ($rawSubjects as $subj) {
                        $delParams = array_merge([$teacher_id, $subj], $cleanCids);
                        $delSub = $pdo->prepare("DELETE FROM teacher_classes WHERE teacher_id = ? AND subject = ? AND class_id NOT IN ($inPlaceholders)");
                        $delSub->execute($delParams);
                    }
                }
            }

            $existingStmt = $pdo->prepare("SELECT id, teacher_id FROM teacher_classes WHERE class_id = ? AND subject = ?");
            $updStmt = $pdo->prepare("UPDATE teacher_classes SET teacher_id = ? WHERE id = ?");
            $insStmt = $pdo->prepare("INSERT INTO teacher_classes (teacher_id, class_id, subject) VALUES (?, ?, ?)");
            $classStmt = $pdo->prepare("SELECT name FROM classes WHERE id = ?");

            $assignedCount = 0;
            $skippedCount = 0;
            $assignedNames = [];

            foreach ($rawSubjects as $subject) {
                foreach ($selected_classes as $cid) {
                    $cid = (int)$cid;
                    if ($cid <= 0) continue;

                    $existingStmt->execute([$cid, $subject]);
                    $existing = $existingStmt->fetch();

                    if ($existing) {
                        if ((int)$existing['teacher_id'] === $teacher_id) {
                            $skippedCount++;
                        } else {
                            $updStmt->execute([$teacher_id, $existing['id']]);
                            $assignedCount++;
                            $classStmt->execute([$cid]);
                            $cName = $classStmt->fetchColumn();
                            if ($cName) $assignedNames[] = "$cName ($subject)";
                        }
                    } else {
                        $insStmt->execute([$teacher_id, $cid, $subject]);
                        $assignedCount++;
                        $classStmt->execute([$cid]);
                        $cName = $classStmt->fetchColumn();
                        if ($cName) $assignedNames[] = "$cName ($subject)";
                    }
                }
            }
            $pdo->commit();

            $classesStr = !empty($assignedNames) ? implode(', ', $assignedNames) : 'Không có';
            $subjectsStr = implode(', ', $rawSubjects);
            $modeDesc = ($assign_mode === 'replace_all') ? 'Làm mới toàn bộ' : (($assign_mode === 'replace_subject') ? 'Cập nhật phân môn' : 'Thêm mới');
            $details = "Admin phân công cho [{$teacher['full_name']}] ({$teacher['username']}) dạy môn [$subjectsStr] ở các lớp: [$classesStr] (Chế độ: $modeDesc, Thành công: $assignedCount lượt).";
            log_action((int)$_SESSION['user_id'], $_SESSION['username'] ?? 'admin', 'Phân công giáo viên', $details);

            $msg = "Đã cập nhật phân công thành công cho giáo viên {$teacher['full_name']} (Môn: $subjectsStr - Chế độ: $modeDesc)!";
            if ($skippedCount > 0) {
                $msg .= " (Đã bỏ qua $skippedCount lượt lớp đã được phân công từ trước).";
            }
        }
    } else {
        $err = "Vui lòng chọn giáo viên, môn học và tích chọn ít nhất một lớp học!";
    }
}

// -----------------------------------------------------------------------------
// 4c. ACTION: XÓA SẠCH TOÀN BỘ PHÂN CÔNG CỦA MỘT GIÁO VIÊN
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_teacher_all_assignments') {
    require_admin();
    $teacher_id = (int)($_POST['teacher_id'] ?? 0);
    if ($teacher_id > 0) {
        $tStmt = $pdo->prepare("SELECT full_name, username FROM users WHERE id = ? AND role = 'teacher'");
        $tStmt->execute([$teacher_id]);
        $teacher = $tStmt->fetch();
        if ($teacher) {
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM teacher_classes WHERE teacher_id = ?");
            $countStmt->execute([$teacher_id]);
            $delCount = (int)$countStmt->fetchColumn();

            $pdo->prepare("DELETE FROM teacher_classes WHERE teacher_id = ?")->execute([$teacher_id]);

            log_action(
                (int)$_SESSION['user_id'],
                $_SESSION['username'] ?? 'admin',
                'Hủy toàn bộ phân công',
                "Admin đã xóa sạch $delCount lớp phân công của giáo viên [{$teacher['full_name']}] ({$teacher['username']})."
            );
            $msg = "Đã xóa sạch toàn bộ $delCount lớp phân công của giáo viên {$teacher['full_name']} thành công!";
        }
    }
}

// -----------------------------------------------------------------------------
// 4b. ACTION: CHUYỂN GIAO GIÁO VIÊN BỘ MÔN TRỰC TIẾP (INLINE REASSIGN TEACHER)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_assignment_teacher') {
    require_admin();
    $assignId = (int)($_POST['assignment_id'] ?? 0);
    $newTeacherId = (int)($_POST['new_teacher_id'] ?? 0);

    if ($assignId > 0 && $newTeacherId > 0) {
        $stmt = $pdo->prepare("
            SELECT tc.id, tc.class_id, tc.subject, tc.teacher_id,
                   u.full_name AS old_teacher_name, c.name AS class_name
            FROM teacher_classes tc
            JOIN users u ON tc.teacher_id = u.id
            JOIN classes c ON tc.class_id = c.id
            WHERE tc.id = ?
        ");
        $stmt->execute([$assignId]);
        $curr = $stmt->fetch();

        if ($curr) {
            $tStmt = $pdo->prepare("SELECT id, full_name, username FROM users WHERE id = ? AND role = 'teacher'");
            $tStmt->execute([$newTeacherId]);
            $newT = $tStmt->fetch();

            if ($newT) {
                // Kiểm tra xem giáo viên mới đã được phân công môn này ở lớp này chưa
                $dupCheck = $pdo->prepare("SELECT id FROM teacher_classes WHERE teacher_id = ? AND class_id = ? AND subject = ? AND id != ?");
                $dupCheck->execute([$newTeacherId, $curr['class_id'], $curr['subject'], $assignId]);
                $dup = $dupCheck->fetch();

                if ($dup) {
                    $pdo->prepare("DELETE FROM teacher_classes WHERE id = ?")->execute([$assignId]);
                } else {
                    $pdo->prepare("UPDATE teacher_classes SET teacher_id = ? WHERE id = ?")->execute([$newTeacherId, $assignId]);
                }

                log_action(
                    (int)$_SESSION['user_id'],
                    $_SESSION['username'] ?? 'admin',
                    'Phân công giáo viên',
                    "Admin chuyển giao môn [{$curr['subject']}] lớp [{$curr['class_name']}] từ [{$curr['old_teacher_name']}] sang [{$newT['full_name']}]."
                );
                $msg = "Đã chuyển giao môn {$curr['subject']} lớp {$curr['class_name']} cho giáo viên {$newT['full_name']}!";
            }
        }
    }
}

// -----------------------------------------------------------------------------
// 5. ACTION: HỦY PHÂN CÔNG GIẢNG DẠY (DELETE ASSIGN)
// Hỗ trợ cả $_GET['delete_assign'], $_GET['action'] === 'delete_assign', và POST
// -----------------------------------------------------------------------------
if (isset($_GET['delete_assign']) || 
    (isset($_GET['action']) && $_GET['action'] === 'delete_assign' && isset($_GET['id'])) ||
    ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_assign')) {
    require_admin();
    $aid = isset($_GET['delete_assign']) 
        ? (int)$_GET['delete_assign'] 
        : (isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0));

    if ($aid > 0) {
        $stmt = $pdo->prepare("
            SELECT tc.id, u.full_name, c.name AS class_name, tc.subject 
            FROM teacher_classes tc 
            JOIN users u ON tc.teacher_id = u.id 
            JOIN classes c ON tc.class_id = c.id 
            WHERE tc.id = ?
        ");
        $stmt->execute([$aid]);
        $info = $stmt->fetch();

        if ($info) {
            $pdo->prepare("DELETE FROM teacher_classes WHERE id = ?")->execute([$aid]);
            log_action(
                (int)$_SESSION['user_id'], 
                $_SESSION['username'] ?? 'admin', 
                'Hủy phân công', 
                "Admin đã hủy quyền dạy môn [{$info['subject']}] lớp [{$info['class_name']}] của giáo viên [{$info['full_name']}]."
            );
            $msg = "Đã hủy phân công dạy môn {$info['subject']} lớp {$info['class_name']} của giáo viên {$info['full_name']}!";
        }
    }
}

// -----------------------------------------------------------------------------
// 5b. ACTION: HỦY TOÀN BỘ PHÂN CÔNG CỦA 1 MÔN CHO GIÁO VIÊN (DELETE ALL BY TEACHER & SUBJECT)
// -----------------------------------------------------------------------------
if (isset($_GET['delete_teacher_subject'])) {
    require_admin();
    $tid = (int)($_GET['teacher_id'] ?? 0);
    $sub = trim((string)($_GET['subject'] ?? ''));
    if ($tid > 0 && $sub !== '') {
        $tStmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
        $tStmt->execute([$tid]);
        $tName = $tStmt->fetchColumn() ?: "ID $tid";

        $delStmt = $pdo->prepare("DELETE FROM teacher_classes WHERE teacher_id = ? AND subject = ?");
        $delStmt->execute([$tid, $sub]);
        $delCount = $delStmt->rowCount();

        log_action(
            (int)$_SESSION['user_id'],
            $_SESSION['username'] ?? 'admin',
            'Hủy môn giáo viên',
            "Admin hủy toàn bộ $delCount lớp dạy môn [$sub] của giáo viên [$tName]."
        );
        $msg = "Đã hủy toàn bộ $delCount lớp phân công môn $sub của giáo viên $tName thành công!";
    }
}

// -----------------------------------------------------------------------------
// 6. ACTION: GIÁM SÁT / MỞ BẢNG ĐIỂM TRỰC TIẾP (IMPERSONATE / INSPECT GRADEBOOK)
// -----------------------------------------------------------------------------
if (isset($_GET['impersonate'])) {
    require_admin();
    $teacherId = (int)$_GET['impersonate'];
    $sub = $_GET['sub'] ?? 'Toán';
    $cid = (int)($_GET['cid'] ?? 0);

    $tUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $tUser->execute([$teacherId]);
    $teacher = $tUser->fetch();

    if ($teacher) {
        $cStmt = $pdo->prepare("SELECT name FROM classes WHERE id = ?");
        $cStmt->execute([$cid]);
        $cName = $cStmt->fetchColumn() ?: "ID $cid";

        log_action(
            (int)$_SESSION['user_id'], 
            $_SESSION['username'] ?? 'admin', 
            'Admin giám sát sổ điểm', 
            "Admin truy cập mở bảng điểm môn [$sub] lớp [$cName] của giáo viên [{$teacher['full_name']}]."
        );

        $_SESSION['view_as_teacher_id'] = $teacher['id'];
        $_SESSION['view_as_teacher_name'] = $teacher['full_name'];
        header("Location: nhap_diem.php?teacher_id={$teacher['id']}&subject=" . urlencode($sub) . "&class_id={$cid}");
        exit;
    }
}

// -----------------------------------------------------------------------------
// -----------------------------------------------------------------------------
// 7. ACTION: IMPORT DANH SÁCH HỌC SINH (.XLSX / .CSV CHUẨN VNEDU)
// Hỗ trợ cả nạp từng lớp hoặc tự động nạp toàn bộ các lớp theo từng sheet của Excel
// Chống trùng lặp theo UNIQUE(class_id, full_name, birthday) & chuẩn hóa ngày sinh
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_students') {
    require_admin();
    $rawTargetClass = $_POST['target_class_id'] ?? '';
    $isAllClasses = ($rawTargetClass === 'all' || $rawTargetClass === '0');
    $target_class_id = is_numeric($rawTargetClass) ? (int)$rawTargetClass : 0;

    if (($isAllClasses || $target_class_id > 0) && isset($_FILES['student_file']) && $_FILES['student_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['student_file']['tmp_name'];
        $fileName = $_FILES['student_file']['name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Chuẩn bị dữ liệu theo từng sheet
        $sheetsData = [];
        if ($fileExt === 'xlsx') {
            $sheetNames = get_xlsx_sheets($fileTmp);
            if ($isAllClasses && !empty($sheetNames)) {
                $sheetsData = parse_all_sheets_xlsx($fileTmp);
            } elseif ($target_class_id > 0) {
                // Kiểm tra xem tên lớp có trùng với tên sheet nào không
                $cNameQuery = $pdo->prepare("SELECT name FROM classes WHERE id = ?");
                $cNameQuery->execute([$target_class_id]);
                $cNameTarget = (string)$cNameQuery->fetchColumn();

                $targetSheet = null;
                if (!empty($sheetNames)) {
                    foreach ($sheetNames as $sn) {
                        if (strcasecmp($sn, $cNameTarget) === 0) {
                            $targetSheet = $sn;
                            break;
                        }
                    }
                }
                $sheetsData[$cNameTarget ?: 'default'] = parse_xlsx($fileTmp, $targetSheet);
            } else {
                $sheetsData['default'] = parse_xlsx($fileTmp);
            }
        } else {
            $sheetsData['default'] = parse_csv($fileTmp);
        }

        if (!empty($sheetsData)) {
            $pdo->beginTransaction();
            try {
                $stmtFindClass = $pdo->prepare("SELECT id FROM classes WHERE name = ?");
            $stmtInsClass = $pdo->prepare("INSERT INTO classes (name, grade, section) VALUES (?, ?, ?)");
            $chkStudent = $pdo->prepare("SELECT id FROM students WHERE class_id = ? AND full_name = ? AND birthday = ?");
            $insStudent = $pdo->prepare("INSERT INTO students (class_id, student_code, full_name, birthday, gender, phone) VALUES (?, ?, ?, ?, ?, ?)");
            $updStudent = $pdo->prepare("UPDATE students SET gender = COALESCE(NULLIF(?, ''), gender), phone = COALESCE(NULLIF(?, ''), phone), student_code = COALESCE(NULLIF(?, ''), student_code) WHERE id = ?");

            $totalImported = 0;
            $totalSkipped = 0;
            $classesProcessed = [];

            foreach ($sheetsData as $sheetKey => $rows) {
                if (empty($rows)) continue;

                // Xác định class_id cho sheet này
                $currentClassId = 0;
                $currentClassName = '';

                if (!$isAllClasses && $target_class_id > 0) {
                    $currentClassId = $target_class_id;
                    $cStmt = $pdo->prepare("SELECT name FROM classes WHERE id = ?");
                    $cStmt->execute([$currentClassId]);
                    $currentClassName = (string)$cStmt->fetchColumn() ?: "ID $currentClassId";
                } else {
                    $sheetNameClean = trim((string)$sheetKey);
                    $stmtFindClass->execute([$sheetNameClean]);
                    $foundCid = $stmtFindClass->fetchColumn();
                    if ($foundCid) {
                        $currentClassId = (int)$foundCid;
                        $currentClassName = $sheetNameClean;
                    } else {
                        // Tự động tạo lớp nếu chưa có trong DB
                        preg_match('/^(\d+)([A-Za-z]+)(\d*)$/', $sheetNameClean, $m);
                        $grade = (int)($m[1] ?? 7);
                        $section = strtoupper($m[2] ?? 'A');
                        $stmtInsClass->execute([$sheetNameClean, $grade, $section]);
                        $currentClassId = (int)$pdo->lastInsertId();
                        $currentClassName = $sheetNameClean;
                    }
                }

                if ($currentClassId <= 0) continue;
                $classesProcessed[$currentClassName] = true;

                // Nhận diện header linh hoạt
                [$headerRowIdx, $colMap] = detect_student_roster_headers($rows);
                $startRow = $headerRowIdx >= 0 ? $headerRowIdx + 1 : 0;

                for ($i = $startRow; $i < count($rows); $i++) {
                    $row = $rows[$i];
                    if (empty($row) || !is_array($row)) continue;

                    // Lấy Họ và tên
                    $fullName = '';
                    if (isset($colMap['full_name']) && isset($row[$colMap['full_name']])) {
                        $fullName = trim((string)$row[$colMap['full_name']]);
                    } elseif (isset($row[0]) && !is_numeric($row[0])) {
                        $fullName = trim((string)$row[0]);
                    } elseif (isset($row[1]) && is_numeric($row[0])) {
                        $fullName = trim((string)$row[1]);
                    }

                    // Bỏ qua dòng trống hoặc dòng ghi chú / tổng kết
                    if ($fullName === '' || mb_stripos($fullName, 'tổng số') !== false || mb_stripos($fullName, 'ghi chú') !== false) {
                        continue;
                    }

                    // Lấy và chuẩn hóa Ngày sinh
                    $rawBirthday = null;
                    if (isset($colMap['birthday']) && isset($row[$colMap['birthday']])) {
                        $rawBirthday = $row[$colMap['birthday']];
                    } elseif (isset($row[1]) && !isset($colMap['birthday'])) {
                        $rawBirthday = $row[1];
                    }
                    $normalizedBirthday = normalize_vn_date($rawBirthday);

                    // Lấy Mã học sinh
                    $studentCode = null;
                    if (isset($colMap['student_code']) && isset($row[$colMap['student_code']])) {
                        $sc = trim((string)$row[$colMap['student_code']]);
                        if ($sc !== '') $studentCode = $sc;
                    }

                    // Lấy Giới tính
                    $gender = null;
                    if (isset($colMap['gender']) && isset($row[$colMap['gender']])) {
                        $g = trim((string)$row[$colMap['gender']]);
                        if (in_array(mb_strtolower($g), ['nam', 'nữ', 'nu'])) {
                            $gender = mb_convert_case($g, MB_CASE_TITLE, "UTF-8");
                        }
                    }

                    // Lấy Số điện thoại
                    $phone = '';
                    if (isset($colMap['phone']) && isset($row[$colMap['phone']])) {
                        $p = preg_replace('/[^0-9]/', '', (string)$row[$colMap['phone']]);
                        if ($p !== '') $phone = $p;
                    } elseif (isset($row[2]) && !isset($colMap['phone'])) {
                        $p = preg_replace('/[^0-9]/', '', (string)$row[2]);
                        if ($p !== '') $phone = $p;
                    }

                    // Xác định lớp chính xác của học sinh: ưu tiên cột "Lớp" trong file nếu có
                    $studentClassId = $currentClassId;
                    if (isset($colMap['class_name']) && isset($row[$colMap['class_name']])) {
                        $rowClassRaw = trim((string)$row[$colMap['class_name']]);
                        $cleanRowClass = preg_replace('/^Lớp\s+/iu', '', $rowClassRaw);
                        if ($cleanRowClass !== '') {
                            $stmtFindClass->execute([$cleanRowClass]);
                            $foundRowCid = $stmtFindClass->fetchColumn();
                            if ($foundRowCid) {
                                $studentClassId = (int)$foundRowCid;
                                $classesProcessed[$cleanRowClass] = true;
                            } else {
                                preg_match('/^(\d+)([A-Za-z]+)(\d*)$/', $cleanRowClass, $m);
                                $grade = (int)($m[1] ?? 7);
                                $section = strtoupper($m[2] ?? 'A');
                                $stmtInsClass->execute([$cleanRowClass, $grade, $section]);
                                $studentClassId = (int)$pdo->lastInsertId();
                                $classesProcessed[$cleanRowClass] = true;
                            }
                        }
                    }

                    // Khử trùng lặp đa tầng (Pre-check & UNIQUE key constraint catch)
                    $chkStudent->execute([$studentClassId, $fullName, $normalizedBirthday]);
                    $existingId = $chkStudent->fetchColumn();

                    if ($existingId) {
                        $totalSkipped++;
                        $updStudent->execute([$gender, $phone, $studentCode, $existingId]);
                    } else {
                        try {
                            $insStudent->execute([$studentClassId, $studentCode, $fullName, $normalizedBirthday, $gender, $phone]);
                            $totalImported++;
                        } catch (PDOException $e) {
                            if ($e->errorInfo[1] == 1062) {
                                $totalSkipped++;
                            } else {
                                throw $e;
                            }
                        }
                    }
                }
            }

            $classListStr = implode(', ', array_keys($classesProcessed));
            $countClasses = count($classesProcessed);

            // Ghi nhật ký vào database
            log_action(
                (int)$_SESSION['user_id'], 
                $_SESSION['username'] ?? 'admin', 
                'Import học sinh', 
                "Admin import học sinh vào $countClasses lớp [$classListStr] từ file [$fileName]: thêm mới $totalImported, trùng lặp bỏ qua $totalSkipped."
            );

                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errorMsg = "Lỗi hệ thống khi nạp danh sách học sinh: " . $e->getMessage();
            }

            if ($totalImported > 0 && $totalSkipped > 0) {
                $msg = "Đã nhập thành công $totalImported học sinh vào $countClasses lớp ($classListStr), bỏ qua $totalSkipped học sinh đã có sẵn.";
            } elseif ($totalImported > 0 && $totalSkipped === 0) {
                $msg = "Đã nhập thành công $totalImported học sinh vào $countClasses lớp ($classListStr).";
            } elseif ($totalImported === 0 && $totalSkipped > 0) {
                $msg = "Tất cả $totalSkipped học sinh trong file đã tồn tại trên hệ thống, không có học sinh mới nào cần thêm.";
            } else {
                $err = "Không tìm thấy dữ liệu học sinh hợp lệ nào trong file tải lên.";
            }
        } else {
            $err = "Tệp dữ liệu không có nội dung hoặc không thể đọc được!";
        }
    } else {
        $err = "Vui lòng chọn lớp và tệp danh sách học sinh hợp lệ (.xlsx hoặc .csv)!";
    }
}

// -----------------------------------------------------------------------------
// TRUY VẤN DỮ LIỆU HIỂN THỊ TRÊN GIAO DIỆN
// -----------------------------------------------------------------------------

// 1. Chỉ số tổng quan trường học (Stats)
$statTotalClasses = (int)$pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
$statTotalTeachers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
$statTotalAssignments = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes")->fetchColumn();
$statTotalStudents = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();

// 2. Danh sách giáo viên
$teachers = $pdo->query("
    SELECT id, username, full_name,
           (SELECT COUNT(*) FROM teacher_classes tc WHERE tc.teacher_id = users.id) AS assignment_count
    FROM users 
    WHERE role = 'teacher' 
    ORDER BY full_name ASC
")->fetchAll();

// 3. Toàn bộ danh sách lớp học kèm sĩ số học sinh
$allClasses = $pdo->query("
    SELECT c.id, c.name, c.grade, c.section,
           (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id) AS student_count,
           (SELECT COUNT(*) FROM teacher_classes tc WHERE tc.class_id = c.id) AS tc_count
    FROM classes c 
    ORDER BY c.grade ASC, c.section ASC, c.name ASC
")->fetchAll();

$groupedClasses = [6 => [], 7 => [], 8 => [], 9 => []];
foreach ($allClasses as $c) {
    $g = (int)$c['grade'];
    if (isset($groupedClasses[$g])) {
        $groupedClasses[$g][] = $c;
    }
}

// 4. Danh sách toàn bộ phân công giảng dạy
$assignments = $pdo->query("
    SELECT tc.id, tc.teacher_id, tc.class_id, tc.subject, tc.created_at,
           u.full_name AS teacher_name, u.username,
           c.name AS class_name, c.grade, c.section,
           (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id) AS student_count
    FROM teacher_classes tc
    JOIN users u ON tc.teacher_id = u.id
    JOIN classes c ON tc.class_id = c.id
    ORDER BY c.grade ASC, c.section ASC, c.name ASC, tc.subject ASC
")->fetchAll();

$teacherAssignmentsMap = [];
foreach ($assignments as $a) {
    $tid = (int)$a['teacher_id'];
    if (!isset($teacherAssignmentsMap[$tid])) {
        $teacherAssignmentsMap[$tid] = [];
    }
    $teacherAssignmentsMap[$tid][] = [
        'id' => (int)$a['id'],
        'class_id' => (int)$a['class_id'],
        'class_name' => $a['class_name'],
        'grade' => (int)$a['grade'],
        'subject' => $a['subject'],
        'student_count' => (int)$a['student_count']
    ];
}

// 5. Nhật ký kiểm toán hệ thống (Audit Logs)
$logLimit = isset($_GET['log_limit']) ? max(10, min(100, (int)$_GET['log_limit'])) : 25;
$stmtLogs = $pdo->prepare("SELECT * FROM audit_logs ORDER BY id DESC LIMIT ?");
$stmtLogs->bindValue(1, $logLimit, PDO::PARAM_INT);
$stmtLogs->execute();
$auditLogs = $stmtLogs->fetchAll();

// Danh mục môn học chuẩn THCS theo Chương trình GDPT 2018
$thcsSubjects = [
    'Toán',
    'Ngữ Văn',
    'Tiếng Anh',
    'Khoa Học Tự Nhiên',
    'Lịch Sử & Địa Lí',
    'Tin Học',
    'Giáo Dục Công Dân',
    'Công Nghệ',
    'Giáo Dục Thể Chất',
    'Nghệ Thuật',
    'Hoạt Động Trải Nghiệm'
];
$activeNav = 'admin';
$pageTitle = 'Cổng Quản Trị Admin – Phân Công & Điều Hành Toàn Trường';
$extraHead = '<style>
    .class-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(105px, 1fr)); gap: 10px; margin-top: 12px; }
    .class-chip-box { display: flex; align-items: center; justify-content: space-between; background: #ffffff; border: 1px solid var(--border-color, #e2e8f0); padding: 8px 12px; border-radius: 8px; cursor: pointer; font-size: 13.5px; font-weight: 700; transition: all .2s cubic-bezier(0.16, 1, 0.3, 1); user-select: none; }
    .class-chip-box:hover { border-color: var(--primary); background: #eef2ff; transform: translateY(-1px); }
    .class-chip-box.selected { border-color: var(--primary); background: #e0e7ff; color: #3730a3; }
    .class-chip-box input[type="checkbox"] { width: 16px; height: 16px; accent-color: var(--primary); cursor: pointer; }
    .grade-group { background: #ffffff; border: 1px solid var(--border-color, #e2e8f0); border-radius: 14px; padding: 18px 20px; margin-bottom: 18px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04); }
    .grade-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; flex-wrap: wrap; gap: 10px; }
    .grade-title { font-size: 16px; font-weight: 800; color: var(--text-heading, #0f172a); display: flex; align-items: center; gap: 8px; }
    .grade-actions { display: flex; align-items: center; gap: 8px; }
    .btn-toggle-grade { font-size: 12px; padding: 5px 11px; border-radius: 6px; font-weight: 600; cursor: pointer; border: 1px solid #cbd5e1; background: #f8fafc; color: #334155; transition: all .15s; }
    .btn-toggle-grade:hover { background: #e2e8f0; color: #0f172a; }
    .badge-student-count { font-size: 11px; font-weight: 700; padding: 2px 6px; border-radius: 999px; background: #f1f5f9; color: #475569; }
    .badge-student-count.has-students { background: #dbeafe; color: #1e40af; }
    .log-table th { background: #090d16 !important; color: #ffffff !important; font-size: 12px; letter-spacing: 0.5px; text-transform: uppercase; }
    .action-tag { display: inline-block; font-size: 12px; font-weight: 700; padding: 3px 10px; border-radius: 6px; }
    .action-green { background: #dcfce7; color: #15803d; }
    .action-blue { background: #dbeafe; color: #1e40af; }
    .action-yellow { background: #fef3c7; color: #b45309; }
    .action-red { background: #fee2e2; color: #b91c1c; }
    .mono-code { font-family: "JetBrains Mono", monospace; font-size: 12.5px; }
    .delete-class-btn { color: #94a3b8; border: none; background: none; cursor: pointer; padding: 2px 4px; border-radius: 4px; font-size: 11px; line-height: 1; transition: color .15s; }
    .delete-class-btn:hover { color: #ef4444; background: #fee2e2; }

    /* MATRIX GRADE CARDS & PILLS */
    .matrix-grade-card { background: #f8fafc; border: 1px solid var(--border-color); border-radius: 12px; padding: 14px; }
    .matrix-grade-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; margin-bottom: 10px; }
    .matrix-grade-title { font-size: 14px; color: #1e293b; text-transform: uppercase; letter-spacing: 0.03em; font-weight: 800; }
    .matrix-grade-badge { background: #e2e8f0; color: #334155; font-size: 12px; font-family: "JetBrains Mono", monospace; font-weight: 600; padding: 3px 8px; border-radius: 6px; }
    .matrix-class-pill { display: inline-flex; align-items: center; gap: 6px; background: #ffffff; border: 1px solid #cbd5e1; padding: 5px 8px; border-radius: 6px; font-size: 12.5px; color: #0f172a; }
    .admin-create-class-form { display: flex; gap: 8px; align-items: center; background: #f8fafc; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-color); }
    .admin-assign-mode-box { background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 12px 16px; margin-bottom: 20px; }
    .admin-filter-select { padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-color); font-size: 13px; font-weight: 600; background: #ffffff; color: #0f172a; }
    .admin-assign-teacher-select { padding: 5px 8px; border-radius: 6px; font-weight: 700; font-size: 13px; border: 1px solid #cbd5e1; background: #ffffff; color: #0f172a; cursor: pointer; }

    /* DARK THEME ADMIN OVERRIDES */
    [data-theme="dark"] .matrix-grade-card { background: #131b2e !important; border-color: #23324d !important; }
    [data-theme="dark"] .matrix-grade-header { border-bottom-color: #23324d !important; }
    [data-theme="dark"] .matrix-grade-title { color: #f8fafc !important; }
    [data-theme="dark"] .matrix-grade-badge { background: #1a253c !important; color: #94a3b8 !important; }
    [data-theme="dark"] .matrix-class-pill { background: #1a253c !important; border-color: #2e3f60 !important; color: #f8fafc !important; }
    [data-theme="dark"] .admin-create-class-form { background: #131b2e !important; border-color: #23324d !important; }
    [data-theme="dark"] .admin-create-class-form select,
    [data-theme="dark"] .admin-create-class-form input { background: #1a253c !important; border-color: #2e3f60 !important; color: #f8fafc !important; }
    [data-theme="dark"] .grade-group { background: #131b2e !important; border-color: #23324d !important; }
    [data-theme="dark"] .grade-header { border-bottom-color: #23324d !important; }
    [data-theme="dark"] .grade-title { color: #f8fafc !important; }
    [data-theme="dark"] .class-chip-box { background: #1a253c !important; border-color: #2e3f60 !important; color: #cbd5e1 !important; }
    [data-theme="dark"] .class-chip-box:hover { background: #233354 !important; border-color: #3b82f6 !important; }
    [data-theme="dark"] .class-chip-box.selected { background: #2563eb !important; border-color: #3b82f6 !important; color: #ffffff !important; }
    [data-theme="dark"] .btn-toggle-grade { background: #1a253c !important; border-color: #2e3f60 !important; color: #94a3b8 !important; }
    [data-theme="dark"] .btn-toggle-grade:hover { background: #253352 !important; color: #f8fafc !important; }
    [data-theme="dark"] .admin-tab-nav { background: #131b2e !important; border-color: #23324d !important; }
    [data-theme="dark"] .btn-admin-tab { color: #94a3b8 !important; }
    [data-theme="dark"] .btn-admin-tab:hover { background: rgba(255, 255, 255, 0.05) !important; color: #f8fafc !important; }
    [data-theme="dark"] .btn-admin-tab.active { background: #2563eb !important; color: #ffffff !important; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4) !important; }
    [data-theme="dark"] .admin-assign-mode-box { background: #131b2e !important; border-color: #23324d !important; }
    [data-theme="dark"] .admin-assign-mode-box label span { color: #cbd5e1 !important; }
    [data-theme="dark"] .admin-filter-select { background: #1a253c !important; color: #f8fafc !important; border-color: #2e3f60 !important; }
    [data-theme="dark"] .admin-assign-teacher-select { background: #1a253c !important; color: #f8fafc !important; border-color: #2e3f60 !important; }
    [data-theme="dark"] #teacherCurrentInfoBox { background: #1a253c !important; border-color: #312e81 !important; }
    [data-theme="dark"] #teacherCurrentInfoText { color: #a5b4fc !important; }

    /* Lucian UX/UI Prompt #032 · MIT License (c) 2026 Seokwoooo · https://lucianlabs.dev/uxui-prompt/#032 */
    .u032{position:relative;width:100%;margin:10px 0 12px;}
    .u032-zone{position:relative;display:flex;flex-direction:column;align-items:center;justify-content:center;width:100%;height:150px;border:none !important;outline:none;padding:0;border-radius:18px;background:#ffffff !important;color:#0f172a !important;cursor:pointer;box-shadow:0 8px 24px rgba(15,23,42,.06);transition:transform .3s ease,background-color .3s ease}
    .u032-zone:focus-visible{outline:2px solid #4f46e5;outline-offset:4px}
    .u032-zone svg.u032-dash{position:absolute;left:1px;top:1px;width:calc(100% - 2px);height:calc(100% - 2px);overflow:visible;pointer-events:none}
    .u032-dash rect{fill:none;stroke:#cbd5e1;stroke-width:2;stroke-dasharray:8 7;transition:stroke .3s ease}
    .u032-zone:hover,.u032.is-over .u032-zone{background:#f8fafc !important}
    .u032-zone:hover .u032-dash rect,.u032.is-over .u032-dash rect{stroke:#4f46e5;animation:u032-march .9s linear infinite}
    @keyframes u032-march{to{stroke-dashoffset:-30}}
    .u032.is-over .u032-zone{transform:scale(1.02);background:#f1f5f9 !important}
    .u032-orb{width:46px;height:46px;border-radius:14px;display:grid;place-items:center;color:#fff;background:linear-gradient(140deg,#4f46e5,#818cf8);box-shadow:0 10px 20px -6px rgba(79,70,229,.5);transition:transform .3s ease}
    .u032-orb svg{width:22px;height:22px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
    .u032-zone:hover .u032-orb{transform:translateY(-2px)}
    .u032.is-over .u032-orb{transform:translateY(-4px) scale(1.08) rotate(-5deg)}
    .u032-zone b{margin-top:8px;font-size:13.5px;font-weight:800;letter-spacing:-.02em;color:#0f172a !important;}
    .u032-zone b em{font-style:normal;color:#4f46e5}
    .u032-zone small{margin-top:2px;font-size:11.5px;color:#64748b !important}
    .u032-file{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none}
    .u032-list{margin:10px 0 0;padding:0;list-style:none;display:flex;flex-direction:column;gap:6px}
    .u032-row{position:relative;display:flex;align-items:center;gap:10px;height:52px;padding:0 12px;border-radius:14px;background:#ffffff !important;border:1px solid #e2e8f0;box-shadow:0 4px 10px rgba(0,0,0,.03);overflow:hidden;animation:u032-in .3s ease}
    @keyframes u032-in{from{transform:translateY(-8px) scale(.96);opacity:0}}
    .u032-row.out{transition:transform .35s var(--ux-ease,cubic-bezier(.2,.8,.2,1)),opacity .3s;transform:translateX(30px);opacity:0}
    .u032-ic{width:32px;height:34px;flex:none;border-radius:7px;display:grid;place-items:end center;padding-bottom:4px;font-size:8.5px;font-weight:800;letter-spacing:.04em;color:#fff;background:var(--c,#4f46e5)}
    .u032-meta{flex:1;min-width:0}
    .u032-meta b{display:block;font-size:12.5px;font-weight:700;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .u032-meta small{display:block;margin-top:1px;font-size:11px;color:var(--ux-ink-3,#64748b);font-variant-numeric:tabular-nums}
    .u032-bar{position:absolute;left:0;bottom:0;height:3px;width:100%;background:var(--ux-accent,#4f46e5);transform-origin:0 50%;transform:scaleX(var(--p,0));transition:transform .2s linear,background-color .3s,opacity .5s .4s}
    .u032-row.done .u032-bar{background:var(--ux-mint,#10b981);opacity:0}
    .u032-row.fail .u032-bar{background:var(--ux-danger,#ef4444)}
    .u032-st{width:26px;height:26px;flex:none;border-radius:50%;display:grid;place-items:center}
    .u032-st svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:2.6;stroke-linecap:round;stroke-linejoin:round}
    .u032-row.done .u032-st{background:#10b981;color:#fff;animation:u032-pop .45s var(--ux-spring,cubic-bezier(.34,1.45,.64,1))}
    @keyframes u032-pop{from{transform:scale(.3)}}
    .u032-row.done .u032-st path{stroke-dasharray:16;stroke-dashoffset:16;animation:u032-draw .4s var(--ux-ease,cubic-bezier(.2,.8,.2,1)) .15s forwards}
    @keyframes u032-draw{to{stroke-dashoffset:0}}
    .u032-spin{width:16px;height:16px;border-radius:50%;border:2px solid #e2e8f0;border-top-color:var(--ux-accent,#4f46e5);animation:u032-rot .8s linear infinite}
    @keyframes u032-rot{to{transform:rotate(360deg)}}

    /* DARK THEME U032 OVERRIDES */
    [data-theme="dark"] .u032-zone { background: #131b2e !important; color: #f8fafc !important; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3) !important; }
    [data-theme="dark"] .u032-zone:hover, [data-theme="dark"] .u032.is-over .u032-zone { background: #1a253c !important; }
    [data-theme="dark"] .u032-dash rect { stroke: #2e3f60 !important; }
    [data-theme="dark"] .u032-zone b { color: #f8fafc !important; }
    [data-theme="dark"] .u032-zone small { color: #94a3b8 !important; }
    [data-theme="dark"] .u032-row { background: #1a253c !important; border-color: #23324d !important; }
    [data-theme="dark"] .u032-meta b { color: #f8fafc !important; }

    /* APPLE-STYLE ADMIN TABS */
    .admin-tab-nav {
        display: flex;
        gap: 8px;
        margin: 20px 0 24px 0;
        padding: 5px;
        background: rgba(241, 245, 249, 0.95);
        border: 1px solid #cbd5e1;
        border-radius: 14px;
        width: fit-content;
        max-width: 100%;
        flex-wrap: wrap;
    }
    .btn-admin-tab {
        padding: 9px 20px;
        border-radius: 10px;
        border: none;
        background: transparent;
        color: #475569;
        font-size: 13.5px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.25s cubic-bezier(0.34, 1.45, 0.64, 1);
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .btn-admin-tab:hover {
        color: #0f172a;
        background: rgba(255, 255, 255, 0.7);
    }
    .btn-admin-tab.active {
        background: #ffffff;
        color: #4f46e5;
        box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08);
    }
    .admin-tab-pane {
        display: none;
        animation: fadeInTab 0.3s ease;
    }
    .admin-tab-pane.active {
        display: block;
    }
    .stats .card {
        cursor: pointer;
        transition: transform 0.2s cubic-bezier(0.34, 1.45, 0.64, 1), box-shadow 0.2s ease;
    }
    .stats .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
    }
    @keyframes fadeInTab {
        from { opacity: 0; transform: translateY(6px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>';
$breadcrumbs = [
    ['title' => 'Trang Chủ', 'url' => 'welcome.php'],
    ['title' => 'Cổng Quản Trị Nhà Trường', 'url' => '']
];
require_once __DIR__ . '/includes/header.php';
?>

<!-- TIÊU ĐỀ TRANG QUẢN TRỊ ADMIN -->
<div style="margin-bottom: 24px;">
    <header class="topbar" style="margin-bottom: 12px;">
        <div>
            <h1>TRUNG TÂM QUẢN TRỊ NHÀ TRƯỜNG</h1>
            <p class="muted">Quản lý lớp học THCS (Khối 6–9), phân công giảng dạy, nạp danh sách học sinh và theo dõi toàn trường.</p>
        </div>
        <div class="top-actions" style="display: flex; align-items: center; gap: 12px;">
            <span class="badge" style="background: #e0e7ff; color: #3730a3; padding: 6px 14px; font-size: 12px; font-weight: 700;">
                Quản trị viên
            </span>
        </div>
    </header>

    <!-- THÔNG BÁO PHẢN HỒI HỆ THỐNG -->
    <?php if ($msg): ?>
        <div class="alert success mb-3" style="background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; padding: 14px 18px; border-radius: 10px; font-weight: 600;">
            <?= get_svg_icon('check-circle', 16, '', 'vertical-align: -3px; margin-right: 6px;') ?> <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>
    <?php if ($err): ?>
        <div class="alert mb-3" style="background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; padding: 14px 18px; border-radius: 10px; font-weight: 600;">
            <?= get_svg_icon('alert-circle', 16, '', 'vertical-align: -3px; margin-right: 6px;') ?> <?= htmlspecialchars($err) ?>
        </div>
    <?php endif; ?>

    <!-- 4 THẺ THỐNG KÊ TỔNG QUAN NHÀ TRƯỜNG (BẤM ĐỂ CHUYỂN NHANH TAB TƯƠNG ỨNG) -->
    <div class="stats">
        <div class="card" onclick="switchAdminTab('classes')" title="Bấm để xem Quản lý Lớp học">
            <span>Tổng số lớp học</span>
            <b><?= $statTotalClasses ?></b>
            <div class="muted small">Khối 6, 7, 8, 9</div>
        </div>
        <div class="card" onclick="switchAdminTab('teachers')" title="Bấm để chuyển sang Quản lý Giáo viên">
            <span>Giáo viên bộ môn</span>
            <b><?= $statTotalTeachers ?></b>
            <div class="muted small">Đang công tác</div>
        </div>
        <div class="card" onclick="switchAdminTab('teachers')" title="Bấm để xem Phân công giảng dạy">
            <span>Phân công giảng dạy</span>
            <b><?= $statTotalAssignments ?></b>
            <div class="muted small">Lượt môn / lớp</div>
        </div>
        <div class="card" onclick="switchAdminTab('classes')" title="Bấm để xem Danh sách học sinh">
            <span>Học sinh toàn trường</span>
            <b><?= $statTotalStudents ?></b>
            <div class="muted small">Đã định danh trong CSDL</div>
        </div>
    </div>

    <!-- APPLE-STYLE SEGMENTED TABS (DEDICATED SECTIONS FOR CLASSES, TEACHERS & AUDIT) -->
    <div class="admin-tab-nav" role="tablist">
        <button type="button" class="btn-admin-tab active" data-tab="classes" onclick="switchAdminTab('classes', this)" role="tab" aria-selected="true">
            Quản Lý Lớp Học & Học Sinh (<?= $statTotalClasses ?> Lớp · <?= $statTotalStudents ?> HS)
        </button>
        <button type="button" class="btn-admin-tab" data-tab="teachers" onclick="switchAdminTab('teachers', this)" role="tab" aria-selected="false">
            Quản Lý Giáo Viên & Phân Công (<?= $statTotalTeachers ?> GV · <?= $statTotalAssignments ?> Phân Công)
        </button>
        <button type="button" class="btn-admin-tab" data-tab="logs" onclick="switchAdminTab('logs', this)" role="tab" aria-selected="false">
            Nhật Ký Hoạt Động (Audit Logs)
        </button>
    </div>

    <!-- ===================================================================== -->
    <!-- TAB PANE 1: QUẢN LÝ LỚP HỌC & IMPORT HỌC SINH                         -->
    <!-- ===================================================================== -->
    <div class="admin-tab-pane active" id="pane-classes" role="tabpanel">

    <!-- ===================================================================== -->
    <!-- PHẦN 1: MA TRẬN LỚP HỌC THCS (KHỐI 6–9) & TẠO LỚP HỌC MỚI             -->
    <!-- ===================================================================== -->
    <section class="panel-box mb-3">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
            <div>
                <h3>Danh Sách Lớp Học Toàn Trường (Khối 6, 7, 8, 9)</h3>
                <p class="muted small">Xem danh sách lớp học được phân chia theo khối, theo dõi sĩ số học sinh và quản lý thêm/xóa lớp.</p>
            </div>
            <!-- Biểu mẫu tạo lớp học mới -->
            <form method="POST" class="admin-create-class-form">
                <input type="hidden" name="action" value="create_class">
                <span style="font-size: 11.5px; font-weight: 800; text-transform: uppercase;">Thêm Lớp:</span>
                <select name="grade" required style="padding: 6px 8px; font-size: 13px; border-radius: 6px;">
                    <option value="6">Khối 6</option>
                    <option value="7">Khối 7</option>
                    <option value="8">Khối 8</option>
                    <option value="9">Khối 9</option>
                </select>
                <select name="section" required style="padding: 6px 8px; font-size: 13px; border-radius: 6px;">
                    <option value="A">Ban A</option>
                    <option value="B">Ban B</option>
                    <option value="C">Ban C</option>
                </select>
                <input type="number" name="class_num" min="1" max="9" value="1" style="width: 55px; padding: 6px 8px; font-size: 13px; border-radius: 6px;" title="Số thứ tự lớp (1-9)">
                <input type="text" name="name" placeholder="Hoặc tên tùy chỉnh (6C4)" style="width: 140px; padding: 6px 8px; font-size: 13px; border-radius: 6px;">
                <button type="submit" class="btn primary" style="padding: 6px 14px; font-size: 13px; font-weight: 700;">
                    Tạo Lớp
                </button>
            </form>
        </div>

        <!-- Hiển thị lưới lớp theo từng khối -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
            <?php foreach ([6, 7, 8, 9] as $g): ?>
                <?php 
                    $classesInThisGrade = $groupedClasses[$g] ?? [];
                    $totalStudentsInGrade = array_sum(array_column($classesInThisGrade, 'student_count'));
                ?>
                <div class="matrix-grade-card">
                    <div class="matrix-grade-header">
                        <b class="matrix-grade-title">Khối <?= $g ?></b>
                        <span class="matrix-grade-badge">
                            <?= count($classesInThisGrade) ?> lớp | <?= $totalStudentsInGrade ?> HS
                        </span>
                    </div>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        <?php if (empty($classesInThisGrade)): ?>
                            <span class="muted small">Chưa có lớp nào trong khối này.</span>
                        <?php else: ?>
                            <?php foreach ($classesInThisGrade as $c): ?>
                                <div class="matrix-class-pill">
                                    <b><?= htmlspecialchars($c['name']) ?></b>
                                    <span class="badge-student-count <?= $c['student_count'] > 0 ? 'has-students' : '' ?>">
                                        <?= $c['student_count'] ?> HS
                                    </span>
                                    <a href="admin.php?delete_class=<?= $c['id'] ?>" class="delete-class-btn" title="Xóa lớp <?= htmlspecialchars($c['name']) ?>" onclick="return confirm('Bạn có chắc chắn muốn xóa lớp <?= htmlspecialchars($c['name']) ?>? Tất cả dữ liệu học sinh và điểm liên quan sẽ bị xóa theo!')" aria-label="Xóa lớp <?= htmlspecialchars($c['name']) ?>" style="display: inline-flex; align-items: center; justify-content: center;">
                                        <?= get_svg_icon('trash', 12) ?>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- FORM IMPORT DANH SÁCH HỌC SINH CHO LỚP -->
    <section class="panel-box" style="margin-bottom: 24px;">
        <h3>Import Danh Sách Học Sinh Cho Lớp (.xlsx & .csv)</h3>
        <p class="muted small mb-3">Nạp danh sách học sinh chuẩn VnEdu (.xlsx hoặc .csv) có chống trùng lặp dữ liệu.</p>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="import_students">
            <div class="form-group mb-2">
                <label>Chọn Lớp Học Đích</label>
                <select name="target_class_id" required>
                    <option value="all" selected>TỰ ĐỘNG THEO TỪNG SHEET EXCEL (Toàn bộ các lớp trong file)</option>
                    <?php foreach ($allClasses as $c): ?>
                        <option value="<?= $c['id'] ?>">Lớp <?= htmlspecialchars($c['name']) ?> (Khối <?= $c['grade'] ?> - Hiện có <?= $c['student_count'] ?> HS)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mb-3">
                <label style="font-weight: 700; display: block; margin-bottom: 6px;">Tệp Danh Sách Học Sinh (.xlsx hoặc .csv)</label>
                <!-- Lucian UX/UI Prompt #032 · MIT License (c) 2026 Seokwoooo · https://lucianlabs.dev/uxui-prompt/#032 -->
                <div class="ux u032" id="adminStudentDropzone">
                    <button class="u032-zone" type="button" id="adminStudentZoneBtn" style="height: 140px;">
                        <svg class="u032-dash" aria-hidden="true"><rect width="100%" height="100%" rx="18"/></svg>
                        <span class="u032-orb" aria-hidden="true" style="width: 44px; height: 44px; border-radius: 14px;">
                            <svg viewBox="0 0 28 28" style="width: 20px; height: 20px;"><path d="M8.5 20.5H7a5 5 0 0 1-.6-10 7 7 0 0 1 13.4-1.6A5.5 5.5 0 0 1 21 20.5h-1.5M14 13v10M10.5 16.5L14 13l3.5 3.5"/></svg>
                        </span>
                        <b style="font-size: 13.5px; margin-top: 8px;">Kéo & thả tệp danh sách học sinh vào đây hoặc <em>chọn tệp</em></b>
                        <small style="font-size: 11.5px;">Hỗ trợ <strong>.xlsx</strong>, <strong>.csv</strong> chuẩn VnEdu / SMAS</small>
                    </button>
                    <input class="u032-file" type="file" name="student_file" id="adminStudentFileInput" accept=".xlsx, .csv, .txt" required tabindex="-1" aria-hidden="true">
                    <ul class="u032-list" id="adminStudentList" aria-live="polite"></ul>
                </div>
            </div>
            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <button type="submit" class="btn primary" style="flex: 1; min-width: 160px; font-weight: 700;">
                    Import Học Sinh Vào Lớp
                </button>
                <a href="sample_students.xlsx" download class="btn outline" style="font-size: 12.5px;">
                    File mẫu .xlsx
                </a>
                <a href="sample_students.csv" download class="btn outline" style="font-size: 12.5px;">
                    File mẫu .csv
                </a>
            </div>
        </form>
    </section>
</div>

<!-- ===================================================================== -->
<!-- TAB PANE 2: QUẢN LÝ GIÁO VIÊN BỘ MÔN & PHÂN CÔNG GIẢNG DẠY             -->
<!-- ===================================================================== -->
<div class="admin-tab-pane" id="pane-teachers" role="tabpanel">
    <!-- Form Tạo tài khoản giáo viên mới -->
    <section class="panel-box mb-3">
        <h3>Tạo Tài Khoản Giáo Viên Mới</h3>
        <p class="muted small mb-3">Tạo tài khoản giáo viên THCS (mật khẩu băm Bcrypt chuẩn bảo mật).</p>
        <form method="POST">
            <input type="hidden" name="action" value="create_teacher">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 14px;">
                <div class="form-group">
                    <label>Tên Đăng Nhập (Username / VnEdu)</label>
                    <input type="text" name="username" placeholder="Ví dụ: gv_toan3, nguyen_van_a" required>
                </div>
                <div class="form-group">
                    <label>Họ và Tên Giáo Viên</label>
                    <input type="text" name="full_name" placeholder="Ví dụ: Thầy Trần Quang Khải" required>
                </div>
                <div class="form-group">
                    <label>Mật Khẩu Khởi Tạo (Mặc định: 123456)</label>
                    <input type="password" name="password" value="123456" required>
                </div>
            </div>
            <button type="submit" class="btn primary" style="padding: 10px 24px; font-weight: 700;">
                Tạo Tài Khoản Giáo Viên
            </button>
        </form>
    </section>

    <!-- PHẦN 2: BẢNG TÍCH CHỌN PHÂN CÔNG GIÁO VIÊN HÀNG LOẠT (BATCH ASSIGN)   -->
    <section class="panel-box mb-3">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
            <div>
                <h3>Phân Công Giảng Dạy Hàng Loạt Bằng Checkbox</h3>
                <p class="muted small">Chọn giáo viên, chọn môn học và <b>tích chọn vào các ô lớp học</b> thuộc khối 6, 7, 8, 9 để phân công nhanh chóng.</p>
            </div>
            <!-- Thanh công cụ chọn toàn bộ / bỏ chọn toàn bộ -->
            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                <span id="selectedClassCount" style="font-size: 13px; color: var(--text-muted); margin-right: 4px;">Đã chọn: 0 lớp</span>
                <button type="button" class="btn primary" style="font-size: 12px; padding: 6px 14px; background: #059669; border-color: #059669;" onclick="selectOnlyClassesWithStudents()">
                    Chỉ chọn các lớp có học sinh
                </button>
                <button type="button" class="btn outline" style="font-size: 12px; padding: 6px 12px;" onclick="selectAllGrades(true)">
                    Chọn tất cả các khối
                </button>
                <button type="button" class="btn outline" style="font-size: 12px; padding: 6px 12px;" onclick="selectAllGrades(false)">
                    Bỏ chọn tất cả
                </button>
            </div>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="batch_assign">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 14px;">
                <div class="form-group">
                    <label>1. Chọn Giáo Viên Phụ Trách</label>
                    <select name="teacher_id" id="teacherSelectInput" required onchange="onTeacherSelectChange(this)">
                        <option value="">-- Bấm chọn giáo viên cần phân công --</option>
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?= $t['id'] ?>">
                                <?= htmlspecialchars($t['full_name']) ?> (Tài khoản: <?= htmlspecialchars($t['username']) ?> - Hiện có <?= $t['assignment_count'] ?> lớp)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <!-- Hộp hiển thị phân công hiện tại của giáo viên -->
                    <div id="teacherCurrentInfoBox" style="display: none; background: #eef2ff; border: 1.5px solid #c7d2fe; border-radius: 8px; padding: 10px 14px; margin-top: 10px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                            <span style="font-size: 12.5px; font-weight: 700; color: #3730a3;" id="teacherCurrentInfoText"></span>
                            <button type="submit" form="formDeleteAllTeacherAssignments" class="btn outline" style="font-size: 11px; padding: 3px 8px; color: #dc2626; border-color: #fca5a5; background: #ffffff; font-weight: 700;" onclick="return confirm('CẢNH BÁO AN TOÀN: Bạn có chắc chắn muốn xóa TOÀN BỘ phân công hiện tại của giáo viên này để phân công lại từ đầu?')">
                                Xóa sạch phân công để gán lại
                            </button>
                        </div>
                    </div>
                </div>
                <div class="form-group" id="subjectFieldContainer">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <label style="margin: 0;">2. Môn Học Giảng Dạy / Phụ Trách</label>
                        <button type="button" id="btnAddSecondSubject" class="btn outline" style="font-size: 11.5px; padding: 3px 8px; color: var(--primary); border-color: var(--primary-border); font-weight: 700;" onclick="addSecondSubjectField()">
                            + Thêm môn thứ 2 (Dạy 2 môn)
                        </button>
                    </div>
                    <select name="subject" id="primarySubjectSelect" required style="width: 100%;">
                        <?php foreach ($thcsSubjects as $sub): ?>
                            <option value="<?= $sub ?>"><?= $sub ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div id="additionalSubjectsWrapper" style="margin-top: 8px; display: flex; flex-direction: column; gap: 8px;"></div>
                    <small class="muted" id="subjectHintText" style="display: block; margin-top: 6px; font-size: 11.5px;">
                        <i>Có thể chọn 1 môn hoặc bấm "+ Thêm môn thứ 2" nếu giáo viên dạy kiêm nhiệm nhiều môn.</i>
                    </small>
                </div>
            </div>

            <!-- CHẾ ĐỘ PHÂN CÔNG (CẬP NHẬT / LÀM MỚI / THÊM MỚI) -->
            <div class="admin-assign-mode-box">
                <label style="font-weight: 800; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 8px;">Chế Độ Phân Công Khi Bấm Lưu:</label>
                <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="radio" name="assign_mode" value="replace_subject" checked>
                        <span style="font-size: 13px;"><b>Cập nhật phân môn này</b> (Giáo viên sẽ chỉ dạy môn này ở các lớp được tích bên dưới)</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="radio" name="assign_mode" value="replace_all">
                        <span style="font-size: 13px; color: #b91c1c;"><b>Làm mới toàn bộ</b> (Xóa sạch mọi phân công cũ, chỉ giữ các lớp & môn vừa chọn)</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="radio" name="assign_mode" value="append">
                        <span style="font-size: 13px;"><b>Thêm lớp mới</b> (Giữ nguyên các lớp cũ và thêm các lớp vừa chọn)</span>
                    </label>
                </div>
            </div>

            <label style="font-weight: 800; font-size: 13.5px; margin-bottom: 12px; display: block; text-transform: uppercase; letter-spacing: 0.03em;">
                3. Tích Chọn Các Lớp Học Cần Cấp Quyền Phân Công:
            </label>
            
            <?php foreach ([6, 7, 8, 9] as $grade): ?>
                <?php $classesInGrade = $groupedClasses[$grade] ?? []; ?>
                <div class="grade-group">
                    <div class="grade-header">
                        <div class="grade-title">
                            <span style="text-transform: uppercase; font-weight: 800; font-size: 13.5px;">Khối <?= $grade ?></span>
                            <span class="badge" style="background: #f1f5f9; color: #475569; font-size: 12px; font-family: 'JetBrains Mono', monospace;">
                                <?= count($classesInGrade) ?> Lớp học
                            </span>
                        </div>
                        <div class="grade-actions">
                            <button type="button" class="btn-toggle-grade" onclick="selectGrade(<?= $grade ?>, true)">
                                Chọn tất cả Khối <?= $grade ?>
                            </button>
                            <button type="button" class="btn-toggle-grade" onclick="selectGrade(<?= $grade ?>, false)">
                                Bỏ chọn Khối <?= $grade ?>
                            </button>
                        </div>
                    </div>
                    <div class="class-grid g<?= $grade ?>">
                        <?php if (empty($classesInGrade)): ?>
                            <div class="muted small" style="grid-column: 1 / -1; padding: 8px 0;">Chưa có lớp nào trong khối này.</div>
                        <?php else: ?>
                            <?php foreach ($classesInGrade as $c): ?>
                                <label class="class-chip-box" data-has-students="<?= $c['student_count'] > 0 ? '1' : '0' ?>">
                                    <input type="checkbox" name="class_ids[]" value="<?= $c['id'] ?>" class="cb-class cb-grade-<?= $grade ?>" onchange="updateSelectedCount()">
                                    <span><?= htmlspecialchars($c['name']) ?></span>
                                    <span class="badge-student-count <?= $c['student_count'] > 0 ? 'has-students' : '' ?>">
                                        <?= $c['student_count'] ?> HS
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div style="margin-top: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                <p class="muted small" style="display: flex; align-items: center; gap: 6px;">
                    <?= get_svg_icon('check-circle', 14, '', 'color: #10b981;') ?>
                    <i>Hệ thống tự động bỏ qua nếu lớp học đó đã được phân công cùng giáo viên và môn học.</i>
                </p>
                <button type="submit" class="btn primary" style="padding: 12px 28px; font-size: 15px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px;">
                    <?= get_svg_icon('check-circle', 16) ?>
                    <span>Cấp Quyền & Phân Công Cho Giáo Viên Đã Chọn</span>
                </button>
            </div>
        </form>
    </section>

    <!-- FORM ẨN ĐỂ XÓA TOÀN BỘ PHÂN CÔNG CỦA MỘT GIÁO VIÊN -->
    <form method="POST" id="formDeleteAllTeacherAssignments" style="display:none;">
        <input type="hidden" name="action" value="delete_teacher_all_assignments">
        <input type="hidden" name="teacher_id" id="hiddenDeleteTeacherId" value="">
    </form>

    <!-- ===================================================================== -->
    <!-- PHẦN 4: DANH SÁCH PHÂN CÔNG & TRUY CẬP TRỰC TIẾP SỔ ĐIỂM               -->
    <!-- ===================================================================== -->
    <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
        <div>
            <h2 style="display: flex; align-items: center; gap: 8px;">
                <?= get_svg_icon('file-text', 20, '', 'color: #2563eb;') ?>
                <span>Danh Sách Phân Công Giảng Dạy & Giám Sát Sổ Điểm</span>
            </h2>
            <span class="muted small">Admin có thể chuyển giao giáo viên trực tiếp trên bảng hoặc mở trực tiếp bảng điểm của bất kỳ môn/lớp nào.</span>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
            <select id="assignSubjectFilter" onchange="filterAssignments()" class="admin-filter-select">
                <option value="">-- Lọc theo Môn Học --</option>
                <?php foreach ($thcsSubjects as $s): ?>
                    <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="assignTeacherFilter" onchange="filterAssignments()" class="admin-filter-select">
                <option value="">-- Lọc theo Giáo Viên --</option>
                <?php foreach ($teachers as $t): ?>
                    <option value="<?= htmlspecialchars($t['full_name']) ?>"><?= htmlspecialchars($t['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="assignGradeFilter" onchange="filterAssignments()" class="admin-filter-select">
                <option value="">-- Lọc theo Khối --</option>
                <option value="Khối 6">Khối 6</option>
                <option value="Khối 7">Khối 7</option>
                <option value="Khối 8">Khối 8</option>
                <option value="Khối 9">Khối 9</option>
            </select>
            <input type="text" id="assignSearchInput" placeholder="Tìm kiếm nhanh..." onkeyup="filterAssignments()" style="padding: 8px 14px; border-radius: 8px; border: 1px solid var(--border-color); font-size: 13.5px; width: 180px;">
        </div>
    </div>

    <div class="table-wrap mb-3">
        <table id="assignmentTable">
            <thead>
                <tr>
                    <th style="width: 110px;">Lớp Học</th>
                    <th style="width: 100px;">Sĩ Số</th>
                    <th style="width: 150px;">Môn Phụ Trách</th>
                    <th>Giáo Viên Giảng Dạy (Đổi Nhanh)</th>
                    <th style="width: 130px;">Tài Khoản</th>
                    <th style="width: 200px; text-align: center;">Quyền Admin: Giám Sát Sổ Điểm</th>
                    <th style="width: 70px; text-align: center;">Hủy</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($assignments)): ?>
                    <tr><td colspan="7" style="text-align: center;" class="muted">Chưa có lớp nào được phân công. Hãy tích chọn ở trên để cấp quyền phân công.</td></tr>
                <?php else: ?>
                    <?php foreach ($assignments as $a): ?>
                        <tr data-subject="<?= htmlspecialchars($a['subject']) ?>" data-teacher="<?= htmlspecialchars($a['teacher_name']) ?>" data-grade="Khối <?= $a['grade'] ?>">
                            <td>
                                <b style="font-size: 15px; color: var(--primary);">Lớp <?= htmlspecialchars($a['class_name']) ?></b>
                                <span class="badge" style="background: #f1f5f9; color: #475569; font-size: 11px; margin-left: 4px;">Khối <?= $a['grade'] ?></span>
                            </td>
                            <td>
                                <span class="badge" style="background: <?= $a['student_count'] > 0 ? '#dbeafe' : '#f1f5f9' ?>; color: <?= $a['student_count'] > 0 ? '#1e40af' : '#475569' ?>;">
                                    <?= $a['student_count'] ?> học sinh
                                </span>
                            </td>
                            <td>
                                <span class="action-tag action-blue"><b><?= htmlspecialchars($a['subject']) ?></b></span>
                            </td>
                            <td>
                                <form method="POST" style="margin: 0; display: inline-flex; align-items: center; gap: 6px;">
                                    <input type="hidden" name="action" value="update_assignment_teacher">
                                    <input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">
                                    <select name="new_teacher_id" onchange="this.form.submit()" title="Bấm chọn để chuyển giao ngay giáo viên phụ trách" class="admin-assign-teacher-select">
                                        <?php foreach ($teachers as $t): ?>
                                            <option value="<?= $t['id'] ?>" <?= $t['id'] == $a['teacher_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($t['full_name']) ?> (<?= htmlspecialchars($t['username']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </td>
                            <td><span class="mono-code"><?= htmlspecialchars($a['username']) ?></span></td>
                            <td style="text-align: center;">
                                <a href="admin.php?impersonate=<?= $a['teacher_id'] ?>&sub=<?= urlencode($a['subject']) ?>&cid=<?= $a['class_id'] ?>" 
                                   class="btn primary" style="font-size: 12px; padding: 6px 14px; display: inline-flex; align-items: center; gap: 5px;">
                                    <?= get_svg_icon('search', 13) ?>
                                    <span>Mở Bảng Điểm <?= htmlspecialchars($a['subject']) ?></span>
                                </a>
                            </td>
                            <td style="text-align: center;">
                                <a href="admin.php?delete_assign=<?= $a['id'] ?>" class="btn-action-delete" title="Hủy phân công giáo viên này ở lớp này" onclick="return confirm('Bạn có chắc chắn muốn hủy phân công giáo viên này ở lớp này?')" style="display: inline-flex; align-items: center; gap: 4px;">
                                    <?= get_svg_icon('trash', 12) ?>
                                    <span>Hủy Phân Công</span>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ===================================================================== -->
<!-- TAB PANE 3: NHẬT KÝ HOẠT ĐỘNG CSDL MARIADB & KIỂM TOÁN HỆ THỐNG        -->
<!-- ===================================================================== -->
<div class="admin-tab-pane" id="pane-logs" role="tabpanel">
    <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
        <div>
            <h2 style="display: flex; align-items: center; gap: 8px;">
                <?= get_svg_icon('shield-check', 20, '', 'color: #2563eb;') ?>
                <span>Nhật Ký Kiểm Toán Hệ Thống (Audit Logs)</span>
            </h2>
            <span class="muted small">Tự động ghi nhận mọi hoạt động đăng nhập, phân công, nạp học sinh, sửa sổ điểm và các nỗ lực truy cập trái phép.</span>
        </div>
        <div style="display: flex; align-items: center; gap: 10px;">
            <input type="text" id="logSearchInput" placeholder="Tìm kiếm trong nhật ký..." onkeyup="filterAuditLogs()" style="padding: 7px 12px; border-radius: 8px; border: 1px solid var(--border-color); font-size: 13px; width: 220px;">
            <select onchange="window.location.href='admin.php?log_limit=' + this.value" style="padding: 7px 10px; border-radius: 8px; border: 1px solid var(--border-color); font-size: 13px;">
                <option value="10" <?= $logLimit === 10 ? 'selected' : '' ?>>Hiện 10 dòng</option>
                <option value="25" <?= $logLimit === 25 ? 'selected' : '' ?>>Hiện 25 dòng</option>
                <option value="50" <?= $logLimit === 50 ? 'selected' : '' ?>>Hiện 50 dòng</option>
                <option value="100" <?= $logLimit === 100 ? 'selected' : '' ?>>Hiện 100 dòng</option>
            </select>
            <a href="admin.php" class="btn outline" style="padding: 7px 12px; font-size: 12.5px; display: inline-flex; align-items: center; gap: 5px;">
                <?= get_svg_icon('activity', 13) ?>
                <span>Làm mới</span>
            </a>
        </div>
    </div>

    <div class="table-wrap">
        <table class="log-table" id="auditLogTable">
            <thead>
                <tr>
                    <th style="width: 150px;">Thời Gian</th>
                    <th style="width: 130px;">Tài Khoản</th>
                    <th style="width: 190px;">Hành Động</th>
                    <th>Chi Tiết Nội Dung</th>
                    <th style="width: 110px;">Địa Chỉ IP</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($auditLogs)): ?>
                    <tr><td colspan="5" style="text-align: center;" class="muted">Chưa có bản ghi nhật ký nào được ghi lại.</td></tr>
                <?php else: ?>
                    <?php foreach ($auditLogs as $log): ?>
                        <?php
                            $act = $log['action'];
                            $tagClass = 'action-blue';
                            if (stripos($act, 'thành công') !== false || stripos($act, 'Tạo') !== false) {
                                $tagClass = 'action-green';
                            } elseif (stripos($act, 'chặn') !== false || stripos($act, 'Xóa') !== false || stripos($act, 'thất bại') !== false) {
                                $tagClass = 'action-red';
                            } elseif (stripos($act, 'Hủy') !== false || stripos($act, 'xuất') !== false) {
                                $tagClass = 'action-yellow';
                            }
                        ?>
                        <tr>
                            <td class="small muted"><span class="mono-code"><?= htmlspecialchars($log['created_at']) ?></span></td>
                            <td><b><?= htmlspecialchars($log['username'] ?? 'Khách') ?></b></td>
                            <td><span class="action-tag <?= $tagClass ?>"><?= htmlspecialchars($log['action']) ?></span></td>
                            <td class="small"><?= htmlspecialchars($log['details']) ?></td>
                            <td class="muted small"><span class="mono-code"><?= htmlspecialchars($log['ip_address']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</main>

<!-- JAVASCRIPT ĐIỀU KHIỂN GIAO DIỆN & TƯƠNG TÁC THỜI GIAN THỰC -->
<script>
// 1. Chọn / Bỏ chọn toàn bộ các lớp của một khối
function selectGrade(gradeNum, isChecked) {
    const checkboxes = document.querySelectorAll('.cb-grade-' + gradeNum);
    checkboxes.forEach(cb => {
        cb.checked = isChecked;
    });
    updateSelectedCount();
}

// 2. Chọn / Bỏ chọn toàn bộ tất cả các khối
function selectAllGrades(isChecked) {
    const checkboxes = document.querySelectorAll('.cb-class');
    checkboxes.forEach(cb => {
        cb.checked = isChecked;
    });
    updateSelectedCount();
}

// 2b. Chỉ chọn các lớp thực tế đã có học sinh (loại bỏ các lớp mẫu 0 học sinh)
function selectOnlyClassesWithStudents() {
    document.querySelectorAll('.class-chip-box').forEach(box => {
        const cb = box.querySelector('input[type="checkbox"]');
        const hasStudents = box.getAttribute('data-has-students') === '1';
        if (cb) {
            cb.checked = hasStudents;
        }
    });
    updateSelectedCount();
}

// 3. Cập nhật bộ đếm số lượng lớp đã chọn
function updateSelectedCount() {
    const checked = document.querySelectorAll('.cb-class:checked').length;
    const total = document.querySelectorAll('.cb-class').length;
    const el = document.getElementById('selectedClassCount');
    if (el) {
        el.innerText = `Đã chọn: ${checked} / ${total} lớp`;
        el.style.fontWeight = checked > 0 ? '700' : 'normal';
        el.style.color = checked > 0 ? 'var(--primary)' : 'var(--text-muted)';
        if (window.gsap) {
            gsap.fromTo(el, { scale: 1.08 }, { scale: 1, duration: 0.25, ease: 'back.out(2)' });
        }
    }

    // Cập nhật trạng thái hiển thị của chip box
    document.querySelectorAll('.class-chip-box').forEach(box => {
        const input = box.querySelector('input[type="checkbox"]');
        const wasSelected = box.classList.contains('selected');
        if (input && input.checked) {
            box.classList.add('selected');
            if (!wasSelected && window.gsap) {
                gsap.fromTo(box, { scale: 0.92 }, { scale: 1, duration: 0.22, ease: 'back.out(2)' });
            }
        } else {
            box.classList.remove('selected');
        }
    });
}

// 4. Lọc tìm kiếm bảng phân công theo thời gian thực (Tìm kiếm + Môn + Giáo viên + Khối)
function filterAssignments() {
    const query = (document.getElementById('assignSearchInput')?.value || '').toLowerCase().trim();
    const selSub = (document.getElementById('assignSubjectFilter')?.value || '').trim();
    const selTeacher = (document.getElementById('assignTeacherFilter')?.value || '').trim();
    const selGrade = (document.getElementById('assignGradeFilter')?.value || '').trim();

    const rows = document.querySelectorAll('#assignmentTable tbody tr');
    const visibleRows = [];
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        const rSub = row.getAttribute('data-subject') || '';
        const rTeacher = row.getAttribute('data-teacher') || '';
        const rGrade = row.getAttribute('data-grade') || '';

        const matchQuery = !query || text.includes(query);
        const matchSub = !selSub || rSub === selSub;
        const matchTeacher = !selTeacher || rTeacher.includes(selTeacher);
        const matchGrade = !selGrade || rGrade === selGrade;

        if (matchQuery && matchSub && matchTeacher && matchGrade) {
            row.style.display = '';
            visibleRows.push(row);
        } else {
            row.style.display = 'none';
        }
    });
    if (window.gsap && visibleRows.length > 0) {
        gsap.killTweensOf(visibleRows);
        gsap.fromTo(visibleRows.slice(0, 25),
            { opacity: 0, y: 6 },
            { opacity: 1, y: 0, duration: 0.25, stagger: 0.012, ease: 'power2.out', clearProps: 'transform' }
        );
    }
}

// 5. Lọc tìm kiếm bảng nhật ký kiểm toán theo thời gian thực
function filterAuditLogs() {
    const query = document.getElementById('logSearchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#auditLogTable tbody tr');
    const visibleRows = [];
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        if (text.includes(query)) {
            row.style.display = '';
            visibleRows.push(row);
        } else {
            row.style.display = 'none';
        }
    });
    if (window.gsap && visibleRows.length > 0) {
        gsap.killTweensOf(visibleRows);
        gsap.fromTo(visibleRows.slice(0, 25),
            { opacity: 0, y: 6 },
            { opacity: 1, y: 0, duration: 0.25, stagger: 0.012, ease: 'power2.out', clearProps: 'transform' }
        );
    }
}

// 6. Thêm trường môn thứ 2 (dạy kiêm nhiệm nhiều môn)
function addSecondSubjectField() {
    var wrapper = document.getElementById('additionalSubjectsWrapper');
    var flag = document.getElementById('isMultiSubjectFlag');
    var hint = document.getElementById('subjectHintText');
    if (!wrapper) return;
    flag.value = '1';

    var div = document.createElement('div');
    div.style.cssText = 'display: flex; gap: 8px; align-items: center;';

    var select = document.createElement('select');
    select.name = 'additional_subjects[]';
    select.required = true;
    select.style.cssText = 'flex: 1; padding: 8px 12px; border-radius: 8px; border: 1.5px solid var(--primary-border); font-weight: 700; font-size: 13.5px; background: #fff;';

    var subjects = <?= json_encode($thcsSubjects, JSON_UNESCAPED_UNICODE) ?>;
    subjects.forEach(function(s) {
        var opt = document.createElement('option');
        opt.value = s;
        opt.textContent = s;
        select.appendChild(opt);
    });

    var removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'btn outline';
    removeBtn.style.cssText = 'color: #dc2626; border-color: #fca5a5; padding: 6px 12px; font-size: 12px; font-weight: 700; white-space: nowrap;';
    removeBtn.textContent = 'Bỏ môn này';
    removeBtn.onclick = function() {
        wrapper.removeChild(div);
        if (wrapper.children.length === 0) {
            hint.innerHTML = '<i>Có thể chọn 1 môn hoặc bấm "+ Thêm môn thứ 2" nếu giáo viên dạy kiêm nhiệm nhiều môn.</i>';
        }
    };

    div.appendChild(select);
    div.appendChild(removeBtn);
    wrapper.appendChild(div);

    hint.innerHTML = '<span style="color: #059669; font-weight: 700;">Chế độ kiêm nhiệm nhiều môn: Hệ thống sẽ cấp quyền đồng thời các môn được chọn.</span>';
}

// 7. Đồng bộ thông tin phân công hiện tại khi chọn giáo viên
const teacherAssignmentsData = <?= json_encode($teacherAssignmentsMap, JSON_UNESCAPED_UNICODE) ?>;

function onTeacherSelectChange(selectElem) {
    const teacherId = selectElem.value;
    const box = document.getElementById('teacherCurrentInfoBox');
    const textEl = document.getElementById('teacherCurrentInfoText');
    const hiddenId = document.getElementById('hiddenDeleteTeacherId');
    if (!box || !textEl || !hiddenId) return;

    if (!teacherId) {
        box.style.display = 'none';
        hiddenId.value = '';
        return;
    }

    hiddenId.value = teacherId;
    const list = teacherAssignmentsData[teacherId] || [];
    if (list.length === 0) {
        box.style.display = 'block';
        textEl.innerHTML = 'Giáo viên này hiện <b>chưa có phân công nào</b>.';
        return;
    }

    // Thống kê phân công theo môn
    const subjectMap = {};
    list.forEach(item => {
        if (!subjectMap[item.subject]) subjectMap[item.subject] = [];
        subjectMap[item.subject].push(item.class_name);
    });

    const parts = [];
    for (const [sub, classes] of Object.entries(subjectMap)) {
        parts.push(`<b>${sub}</b>: ${classes.length} lớp (${classes.slice(0, 4).join(', ')}${classes.length > 4 ? '...' : ''})`);
    }

    textEl.innerHTML = `Phân công hiện có (${list.length} lớp): ` + parts.join(' | ');
    box.style.display = 'block';
}

// Khởi chạy đồng bộ trạng thái khi tải trang
document.addEventListener('DOMContentLoaded', () => {
    updateSelectedCount();
    const teacherSelect = document.getElementById('teacherSelectInput');
    if (teacherSelect && teacherSelect.value) {
        onTeacherSelectChange(teacherSelect);
    }
});

// 8. Lucian UX/UI Prompt #032 Drag-and-drop Interaction Handler cho Import Học Sinh
(function () {
    var root = document.getElementById('adminStudentDropzone');
    if (!root) return;
    var zone = document.getElementById('adminStudentZoneBtn');
    var pick = document.getElementById('adminStudentFileInput');
    var list = document.getElementById('adminStudentList');
    var rm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var TYPE = { xlsx: '#10b981', xls: '#10b981', csv: '#3b82f6', txt: '#64748b' };

    function formatBytes(b) {
        return b > 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB';
    }

    function run(row, bytes, fail) {
        row.className = 'u032-row';
        var st = row.querySelector('.u032-st');
        var sm = row.querySelector('small');
        if (!st || !sm) return;
        st.innerHTML = '<span class="u032-spin" aria-hidden="true"></span>';
        var p = 0, t0 = performance.now(), D = rm ? 150 : 750 + Math.random() * 350;

        (function f(now) {
            if (!root.isConnected) return;
            p = Math.min(1, (now - t0) / D);
            var shown = fail ? Math.min(p, 0.62) : p;
            row.style.setProperty('--p', shown.toFixed(3));
            sm.textContent = formatBytes(bytes * shown) + ' / ' + formatBytes(bytes) + ' · ' + Math.round(shown * 100) + '%';

            if (p < 1) {
                requestAnimationFrame(f);
            } else {
                row.classList.add('done');
                sm.textContent = formatBytes(bytes) + ' · Đã sẵn sàng nạp vào CSDL';
                st.innerHTML = '<svg viewBox="0 0 16 16" aria-hidden="true"><path d="M3.5 8.4l3 3 6-6.4"/></svg>';
            }
        })(t0);
    }

    function addFile(name, bytes, fail) {
        var ext = (name.split('.').pop() || '').toLowerCase();
        var li = document.createElement('li');
        li.className = 'u032-row';
        var badgeColor = TYPE[ext] || '#4f46e5';
        var extLabel = (ext.slice(0, 4).toUpperCase() || 'FILE');

        li.innerHTML = '<span class="u032-ic" style="--c:' + badgeColor + '" aria-hidden="true">' + extLabel + '</span>' +
                       '<span class="u032-meta"><b></b><small></small></span>' +
                       '<span class="u032-st"></span>' +
                       '<span class="u032-bar" aria-hidden="true"></span>';
        li.querySelector('b').textContent = name;
        list.insertBefore(li, list.firstChild);
        while (list.children.length > 2) list.lastElementChild.remove();
        run(li, bytes, fail);
    }

    if (zone && pick) {
        zone.addEventListener('click', function () { pick.click(); });
        pick.addEventListener('change', function () {
            if (pick.files && pick.files.length) {
                Array.prototype.forEach.call(pick.files, function (f) { addFile(f.name, f.size, false); });
            }
        });

        var depth = 0;
        zone.addEventListener('dragenter', function (e) {
            e.preventDefault(); depth++;
            root.classList.add('is-over');
        });
        zone.addEventListener('dragover', function (e) { e.preventDefault(); });
        zone.addEventListener('dragleave', function () {
            if (--depth <= 0) { depth = 0; root.classList.remove('is-over'); }
        });
        zone.addEventListener('drop', function (e) {
            e.preventDefault(); depth = 0; root.classList.remove('is-over');
            if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
                pick.files = e.dataTransfer.files;
                Array.prototype.forEach.call(e.dataTransfer.files, function (f) { addFile(f.name, f.size, false); });
            }
        });
    }
})();

// 8. Chuyển đổi qua lại giữa các Tab chức năng Admin (Apple Segmented Tabs + GSAP Motion)
function switchAdminTab(tabName, btn) {
    document.querySelectorAll('.admin-tab-pane').forEach(function(pane) {
        pane.classList.remove('active');
    });
    document.querySelectorAll('.btn-admin-tab').forEach(function(b) {
        b.classList.remove('active');
        b.setAttribute('aria-selected', 'false');
    });

    var activePane = document.getElementById('pane-' + tabName);
    if (activePane) {
        activePane.classList.add('active');
    }
    var activeBtn = btn || document.querySelector('.btn-admin-tab[data-tab="' + tabName + '"]');
    if (activeBtn) {
        activeBtn.classList.add('active');
        activeBtn.setAttribute('aria-selected', 'true');
        if (window.gsap) {
            gsap.fromTo(activeBtn, { scale: 0.96 }, { scale: 1, duration: 0.28, ease: 'back.out(2)', clearProps: 'transform' });
        }
    }
    if (activePane && window.gsap) {
        var panels = activePane.querySelectorAll('.panel-box');
        if (panels.length > 0) {
            gsap.fromTo(panels,
                { opacity: 0, y: 14 },
                { opacity: 1, y: 0, duration: 0.36, stagger: 0.06, ease: 'power2.out', clearProps: 'transform' }
            );
        }
        var subItems = activePane.querySelectorAll('.grade-card, .assign-grade-box, .class-chip-box');
        if (subItems.length > 0) {
            gsap.fromTo(Array.from(subItems).slice(0, 40),
                { opacity: 0, y: 10, scale: 0.97 },
                { opacity: 1, y: 0, scale: 1, duration: 0.3, stagger: 0.015, ease: 'power2.out', clearProps: 'transform' }
            );
        }
        var tableRows = activePane.querySelectorAll('tbody tr');
        if (tableRows.length > 0) {
            gsap.fromTo(Array.from(tableRows).slice(0, 30),
                { opacity: 0, x: -10 },
                { opacity: 1, x: 0, duration: 0.28, stagger: 0.012, ease: 'power2.out', clearProps: 'transform' }
            );
        }
    }
    try {
        var u = new URL(window.location.href);
        u.searchParams.set('tab', tabName);
        history.replaceState(null, null, u.toString());
    } catch(e) {}
}

document.addEventListener('DOMContentLoaded', function() {
    try {
        var urlParams = new URLSearchParams(window.location.search);
        var tab = urlParams.get('tab') || window.location.hash.replace('#', '');
        if (tab && (tab === 'classes' || tab === 'teachers' || tab === 'logs')) {
            switchAdminTab(tab);
        }
    } catch(e) {}
});
</script>
<?php
$extraScripts = '<script src="assets/js/main.js?v=' . filemtime(__DIR__ . '/assets/js/main.js') . '"></script>';
require_once __DIR__ . '/includes/footer.php';
?>
