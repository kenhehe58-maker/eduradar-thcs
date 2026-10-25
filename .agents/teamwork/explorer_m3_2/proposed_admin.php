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

// Nạp helper Excel nếu có sẵn
if (file_exists(__DIR__ . '/includes/xlsx_helper.php')) {
    require_once __DIR__ . '/includes/xlsx_helper.php';
}

// -----------------------------------------------------------------------------
// 1. ACTION: TẠO LỚP HỌC MỚI (CREATE CLASS)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_class') {
    require_admin();
    $grade = (int)($_POST['grade'] ?? 0);
    $section = strtoupper(trim($_POST['section'] ?? 'A'));
    $classNum = (int)($_POST['class_num'] ?? 1);
    $customName = strtoupper(trim($_POST['custom_name'] ?? ''));

    // Xác định tên lớp chuẩn
    $className = $customName !== '' ? $customName : "{$grade}{$section}{$classNum}";

    if ($grade < 6 || $grade > 9) {
        $err = "Khối lớp không hợp lệ! Vui lòng chọn khối từ 6 đến 9.";
    } elseif (!in_array($section, ['A', 'B', 'C', 'D'])) {
        $err = "Ban/Tổ lớp không hợp lệ! Vui lòng chọn ban A, B hoặc C.";
    } elseif (!preg_match('/^[6-9][A-Z][0-9]{1,2}$/', $className)) {
        $err = "Tên lớp [$className] không đúng định dạng chuẩn THCS (ví dụ: 6A1, 7B2, 8C3)!";
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
    $username = trim($_POST['username'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';

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
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'batch_assign') {
    require_admin();
    $teacher_id = (int)($_POST['teacher_id'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $selected_classes = $_POST['class_ids'] ?? [];

    if ($teacher_id > 0 && $subject !== '' && !empty($selected_classes)) {
        $tStmt = $pdo->prepare("SELECT full_name, username FROM users WHERE id = ? AND role = 'teacher'");
        $tStmt->execute([$teacher_id]);
        $teacher = $tStmt->fetch();

        if (!$teacher) {
            $err = "Giáo viên được chọn không hợp lệ trong hệ thống!";
        } else {
            $pdo->beginTransaction();
            $checkStmt = $pdo->prepare("SELECT id FROM teacher_classes WHERE teacher_id = ? AND class_id = ? AND subject = ?");
            $insStmt = $pdo->prepare("INSERT INTO teacher_classes (teacher_id, class_id, subject) VALUES (?, ?, ?)");
            $classStmt = $pdo->prepare("SELECT name FROM classes WHERE id = ?");

            $assignedCount = 0;
            $skippedCount = 0;
            $assignedNames = [];

            foreach ($selected_classes as $cid) {
                $cid = (int)$cid;
                if ($cid <= 0) continue;

                $checkStmt->execute([$teacher_id, $cid, $subject]);
                if ($checkStmt->fetch()) {
                    $skippedCount++;
                } else {
                    $insStmt->execute([$teacher_id, $cid, $subject]);
                    $assignedCount++;
                    $classStmt->execute([$cid]);
                    $cName = $classStmt->fetchColumn();
                    if ($cName) $assignedNames[] = $cName;
                }
            }
            $pdo->commit();

            $classesStr = !empty($assignedNames) ? implode(', ', $assignedNames) : 'Không có';
            $details = "Admin phân công cho [{$teacher['full_name']}] ({$teacher['username']}) dạy môn [$subject] ở các lớp: [$classesStr] (Thành công: $assignedCount lớp, Bỏ qua: $skippedCount lớp đã có sẵn).";
            log_action((int)$_SESSION['user_id'], $_SESSION['username'] ?? 'admin', 'Phân công giáo viên', $details);

            $msg = "Đã phân công thành công $assignedCount lớp cho giáo viên {$teacher['full_name']} (Môn $subject)!";
            if ($skippedCount > 0) {
                $msg .= " (Đã bỏ qua $skippedCount lớp đã được phân công từ trước).";
            }
        }
    } else {
        $err = "Vui lòng chọn giáo viên, môn học và tích chọn ít nhất một lớp học!";
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
// 7. ACTION: IMPORT DANH SÁCH HỌC SINH (.XLSX / .CSV CHUẨN VNEDU)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_students') {
    require_admin();
    $target_class_id = (int)($_POST['target_class_id'] ?? 0);

    if ($target_class_id > 0 && isset($_FILES['student_file']) && $_FILES['student_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['student_file']['tmp_name'];
        $fileName = $_FILES['student_file']['name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $rows = [];
        if ($fileExt === 'xlsx' && function_exists('parse_xlsx')) {
            $rows = parse_xlsx($fileTmp);
        } else {
            // Đọc tệp CSV với nhận diện tự động BOM và dấu phân cách
            if (function_exists('parse_csv')) {
                $rows = parse_csv($fileTmp);
            } else {
                $handle = fopen($fileTmp, 'r');
                if ($handle !== false) {
                    while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                        $rows[] = $data;
                    }
                    fclose($handle);
                }
            }
        }

        if (!empty($rows)) {
            $cStmt = $pdo->prepare("SELECT name FROM classes WHERE id = ?");
            $cStmt->execute([$target_class_id]);
            $cName = $cStmt->fetchColumn() ?: "ID $target_class_id";

            $chkStudent = $pdo->prepare("SELECT id FROM students WHERE class_id = ? AND full_name = ? AND birthday = ?");
            $insStudent = $pdo->prepare("INSERT INTO students (class_id, full_name, birthday, phone) VALUES (?, ?, ?, ?)");

            $imported = 0;
            $skipped = 0;

            // Bỏ qua dòng tiêu đề nếu có chữ 'STT' hoặc 'Họ'
            $startIdx = 0;
            if (isset($rows[0][0]) && (stripos($rows[0][0], 'STT') !== false || stripos($rows[0][0], 'Họ') !== false || stripos($rows[0][1] ?? '', 'Họ') !== false)) {
                $startIdx = 1;
            }

            for ($i = $startIdx; $i < count($rows); $i++) {
                $row = $rows[$i];
                if (empty($row) || (count($row) === 1 && empty($row[0]))) continue;

                // Xác định cột Họ tên, Ngày sinh, Số điện thoại
                $fullName = '';
                $birthday = null;
                $phone = '';

                if (count($row) >= 3 && is_numeric($row[0])) {
                    // Định dạng: STT, Họ và tên, Ngày sinh, SĐT
                    $fullName = trim($row[1] ?? '');
                    $birthday = !empty($row[2]) ? trim($row[2]) : null;
                    $phone = !empty($row[3]) ? trim($row[3]) : '';
                } else {
                    // Định dạng: Họ và tên, Ngày sinh, SĐT
                    $fullName = trim($row[0] ?? '');
                    $birthday = !empty($row[1]) ? trim($row[1]) : null;
                    $phone = !empty($row[2]) ? trim($row[2]) : '';
                }

                if ($fullName === '') continue;

                // Chuẩn hóa ngày sinh sang YYYY-MM-DD
                if ($birthday) {
                    $dParts = preg_split('/[\/\-\.]/', $birthday);
                    if (count($dParts) === 3) {
                        if (strlen($dParts[0]) === 4) {
                            $birthday = "{$dParts[0]}-" . str_pad($dParts[1], 2, '0', STR_PAD_LEFT) . "-" . str_pad($dParts[2], 2, '0', STR_PAD_LEFT);
                        } else {
                            $birthday = "{$dParts[2]}-" . str_pad($dParts[1], 2, '0', STR_PAD_LEFT) . "-" . str_pad($dParts[0], 2, '0', STR_PAD_LEFT);
                        }
                    } else {
                        $birthday = null;
                    }
                }

                // Chống trùng lặp theo UNIQUE(class_id, full_name, birthday)
                $chkStudent->execute([$target_class_id, $fullName, $birthday]);
                if ($chkStudent->fetch()) {
                    $skipped++;
                } else {
                    $insStudent->execute([$target_class_id, $fullName, $birthday, $phone]);
                    $imported++;
                }
            }

            log_action(
                (int)$_SESSION['user_id'], 
                $_SESSION['username'] ?? 'admin', 
                'Import học sinh', 
                "Admin nạp danh sách học sinh vào lớp [$cName] từ tệp [$fileName]: Thêm mới $imported học sinh (Bỏ qua $skipped học sinh đã tồn tại)."
            );
            $msg = "Đã nạp thành công $imported học sinh vào lớp $cName!";
            if ($skipped > 0) {
                $msg .= " (Đã bỏ qua $skipped học sinh đã có sẵn trong lớp).";
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
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cổng Quản Trị Admin – Phân Công & Điều Hành Toàn Trường</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Tối ưu giao diện Ma trận Lớp học và Checkbox Phân công */
        .class-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(105px, 1fr));
            gap: 10px;
            margin-top: 12px;
        }
        .class-chip-box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #ffffff;
            border: 1px solid var(--border-color, #e2e8f0);
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13.5px;
            font-weight: 700;
            transition: all .2s cubic-bezier(0.16, 1, 0.3, 1);
            user-select: none;
        }
        .class-chip-box:hover {
            border-color: var(--primary);
            background: #eef2ff;
            transform: translateY(-1px);
        }
        .class-chip-box.selected {
            border-color: var(--primary);
            background: #e0e7ff;
            color: #3730a3;
        }
        .class-chip-box input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--primary);
            cursor: pointer;
        }
        .grade-group {
            background: #ffffff;
            border: 1px solid var(--border-color, #e2e8f0);
            border-radius: 14px;
            padding: 18px 20px;
            margin-bottom: 18px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        }
        .grade-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 12px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .grade-title {
            font-size: 16px;
            font-weight: 800;
            color: var(--text-heading, #0f172a);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .grade-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-toggle-grade {
            font-size: 12px;
            padding: 5px 11px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            color: #334155;
            transition: all .15s;
        }
        .btn-toggle-grade:hover {
            background: #e2e8f0;
            color: #0f172a;
        }
        .badge-student-count {
            font-size: 11px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 999px;
            background: #f1f5f9;
            color: #475569;
        }
        .badge-student-count.has-students {
            background: #dbeafe;
            color: #1e40af;
        }
        .log-table th {
            background: #090d16 !important;
            color: #ffffff !important;
            font-size: 12px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .action-tag {
            display: inline-block;
            font-size: 12px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 6px;
        }
        .action-green { background: #dcfce7; color: #15803d; }
        .action-blue { background: #dbeafe; color: #1e40af; }
        .action-yellow { background: #fef3c7; color: #b45309; }
        .action-red { background: #fee2e2; color: #b91c1c; }
        .mono-code {
            font-family: 'JetBrains Mono', monospace;
            font-size: 12.5px;
        }
        .delete-class-btn {
            color: #94a3b8;
            border: none;
            background: none;
            cursor: pointer;
            padding: 2px 4px;
            border-radius: 4px;
            font-size: 11px;
            line-height: 1;
            transition: color .15s;
        }
        .delete-class-btn:hover {
            color: #ef4444;
            background: #fee2e2;
        }
    </style>
</head>
<body>

<!-- THANH ĐIỀU HƯỚNG BÊN TRÁI (SIDEBAR) -->
<aside class="sidebar">
    <div class="logo">
        <span class="logo-icon">🛡️</span>
        <div>
            <div class="brand">EDURADAR ADMIN</div>
            <div class="tagline">Hệ Thống Quản Trị Nhà Trường</div>
        </div>
    </div>
    <nav>
        <a class="active" href="admin.php">⚙️ Quản Trị & Phân Lớp</a>
        <a href="nhap_diem.php">📝 Giám Sát Sổ Điểm</a>
        <a href="index.php">📊 Radar Cảnh Báo</a>
        <a href="logout.php" style="color: #f87171;">🚪 Đăng Xuất</a>
    </nav>
    <div class="sidebar-footer">
        <span>Phiên đăng nhập: <b><?= htmlspecialchars($_SESSION['username'] ?? 'admin') ?></b></span>
    </div>
</aside>

<!-- KHU VỰC NỘI DUNG CHÍNH (MAIN CONTENT) -->
<main class="content">
    <header class="topbar">
        <div>
            <h1>Trung Tâm Quản Trị Nhà Trường (Admin Portal)</h1>
            <p class="muted">Toàn quyền thiết lập ma trận lớp học THCS (Khối 6–9), phân công giảng dạy, nạp danh sách học sinh và giám sát sổ điểm.</p>
        </div>
        <div class="top-actions" style="display: flex; align-items: center; gap: 12px;">
            <span class="badge" style="background: #e0e7ff; color: #3730a3; padding: 6px 14px; font-size: 13px;">
                👑 QUẢN TRỊ VIÊN CAO CẤP
            </span>
            <a href="logout.php" class="btn outline">Đăng Xuất</a>
        </div>
    </header>

    <!-- THÔNG BÁO PHẢN HỒI HỆ THỐNG -->
    <?php if ($msg): ?>
        <div class="alert success mb-3" style="background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; padding: 14px 18px; border-radius: 10px; font-weight: 600;">
            ✓ <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>
    <?php if ($err): ?>
        <div class="alert mb-3" style="background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; padding: 14px 18px; border-radius: 10px; font-weight: 600;">
            ✕ <?= htmlspecialchars($err) ?>
        </div>
    <?php endif; ?>

    <!-- 4 THẺ THỐNG KÊ TỔNG QUAN NHÀ TRƯỜNG -->
    <div class="stats">
        <div class="card">
            <span>TỔNG SỐ LỚP HỌC</span>
            <b><?= $statTotalClasses ?></b>
            <div class="muted small">Khối 6, 7, 8, 9</div>
        </div>
        <div class="card">
            <span>GIÁO VIÊN BỘ MÔN</span>
            <b><?= $statTotalTeachers ?></b>
            <div class="muted small">Đang công tác</div>
        </div>
        <div class="card">
            <span>PHÂN CÔNG GIẢNG DẠY</span>
            <b><?= $statTotalAssignments ?></b>
            <div class="muted small">Lượt môn / lớp</div>
        </div>
        <div class="card">
            <span>HỌC SINH TOÀN TRƯỜNG</span>
            <b><?= $statTotalStudents ?></b>
            <div class="muted small">Đã định danh trong CSDL</div>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- PHẦN 1: MA TRẬN LỚP HỌC THCS (KHỐI 6–9) & TẠO LỚP HỌC MỚI             -->
    <!-- ===================================================================== -->
    <section class="panel-box mb-3">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
            <div>
                <h3>🏫 Ma Trận Lớp Học Toàn Trường (Khối 6, 7, 8, 9)</h3>
                <p class="muted small">Xem danh sách lớp học được phân chia theo khối, theo dõi sĩ số học sinh và quản lý thêm/xóa lớp.</p>
            </div>
            <!-- Biểu mẫu tạo lớp học mới -->
            <form method="POST" style="display: flex; gap: 8px; align-items: center; background: #f8fafc; padding: 8px 12px; border-radius: 10px; border: 1px solid var(--border-color);">
                <input type="hidden" name="action" value="create_class">
                <span style="font-size: 13px; font-weight: 700;">➕ Thêm Lớp:</span>
                <select name="grade" required style="padding: 6px 8px; font-size: 13px; border-radius: 6px; border: 1px solid #cbd5e1;">
                    <option value="6">Khối 6</option>
                    <option value="7">Khối 7</option>
                    <option value="8">Khối 8</option>
                    <option value="9">Khối 9</option>
                </select>
                <select name="section" required style="padding: 6px 8px; font-size: 13px; border-radius: 6px; border: 1px solid #cbd5e1;">
                    <option value="A">Ban A</option>
                    <option value="B">Ban B</option>
                    <option value="C">Ban C</option>
                </select>
                <input type="number" name="class_num" min="1" max="9" value="1" style="width: 55px; padding: 6px 8px; font-size: 13px; border-radius: 6px; border: 1px solid #cbd5e1;" title="Số thứ tự lớp (1-9)">
                <button type="submit" class="btn primary" style="padding: 6px 12px; font-size: 13px;">
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
                <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: 12px; padding: 14px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; margin-bottom: 10px;">
                        <b style="font-size: 15px; color: #1e293b;">🎓 Khối <?= $g ?></b>
                        <span class="badge" style="background: #e2e8f0; color: #334155; font-size: 12px;">
                            <?= count($classesInThisGrade) ?> lớp | <?= $totalStudentsInGrade ?> HS
                        </span>
                    </div>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        <?php if (empty($classesInThisGrade)): ?>
                            <span class="muted small">Chưa có lớp nào trong khối này.</span>
                        <?php else: ?>
                            <?php foreach ($classesInThisGrade as $c): ?>
                                <div style="display: inline-flex; align-items: center; gap: 6px; background: #ffffff; border: 1px solid #cbd5e1; padding: 5px 8px; border-radius: 6px; font-size: 12.5px;">
                                    <b><?= htmlspecialchars($c['name']) ?></b>
                                    <span class="badge-student-count <?= $c['student_count'] > 0 ? 'has-students' : '' ?>">
                                        <?= $c['student_count'] ?> HS
                                    </span>
                                    <a href="admin.php?delete_class=<?= $c['id'] ?>" class="delete-class-btn" title="Xóa lớp <?= htmlspecialchars($c['name']) ?>" onclick="return confirm('Bạn có chắc chắn muốn xóa lớp <?= htmlspecialchars($c['name']) ?>? Tất cả dữ liệu học sinh và điểm liên quan sẽ bị xóa theo!')">
                                        ✕
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ===================================================================== -->
    <!-- PHẦN 2: BẢNG TÍCH CHỌN PHÂN CÔNG GIÁO VIÊN HÀNG LOẠT (BATCH ASSIGN)   -->
    <!-- ===================================================================== -->
    <section class="panel-box mb-3">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
            <div>
                <h3>📋 Phân Công Giảng Dạy Hàng Loạt Bằng Checkbox</h3>
                <p class="muted small">Chọn giáo viên, chọn môn học và <b>tích chọn vào các ô lớp học</b> thuộc khối 6, 7, 8, 9 để phân công nhanh chóng.</p>
            </div>
            <!-- Thanh công cụ chọn toàn bộ / bỏ chọn toàn bộ -->
            <div style="display: flex; align-items: center; gap: 10px;">
                <span id="selectedClassCount" style="font-size: 13px; color: var(--text-muted);">Đã chọn: 0 lớp</span>
                <button type="button" class="btn outline" style="font-size: 12px; padding: 6px 12px;" onclick="selectAllGrades(true)">
                    ✓ Chọn tất cả các khối
                </button>
                <button type="button" class="btn outline" style="font-size: 12px; padding: 6px 12px;" onclick="selectAllGrades(false)">
                    ✕ Bỏ chọn tất cả
                </button>
            </div>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="batch_assign">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                <div class="form-group">
                    <label>1. Chọn Giáo Viên Phụ Trách</label>
                    <select name="teacher_id" required>
                        <option value="">-- Bấm chọn giáo viên cần phân công --</option>
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?= $t['id'] ?>">
                                <?= htmlspecialchars($t['full_name']) ?> (Tài khoản: <?= htmlspecialchars($t['username']) ?> - Hiện có <?= $t['assignment_count'] ?> lớp)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>2. Môn Học Giảng Dạy / Phụ Trách</label>
                    <select name="subject" required>
                        <?php foreach ($thcsSubjects as $sub): ?>
                            <option value="<?= $sub ?>"><?= $sub ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <label style="font-weight: 800; font-size: 14px; margin-bottom: 12px; display: block;">
                3. Tích Chọn Các Lớp Học Cần Cấp Quyền Phân Công:
            </label>
            
            <?php foreach ([6, 7, 8, 9] as $grade): ?>
                <?php $classesInGrade = $groupedClasses[$grade] ?? []; ?>
                <div class="grade-group">
                    <div class="grade-header">
                        <div class="grade-title">
                            <span>🎓 Khối <?= $grade ?></span>
                            <span class="badge" style="background: #f1f5f9; color: #475569; font-size: 12px;">
                                <?= count($classesInGrade) ?> Lớp học
                            </span>
                        </div>
                        <div class="grade-actions">
                            <button type="button" class="btn-toggle-grade" onclick="selectGrade(<?= $grade ?>, true)">
                                ✓ Chọn tất cả Khối <?= $grade ?>
                            </button>
                            <button type="button" class="btn-toggle-grade" onclick="selectGrade(<?= $grade ?>, false)">
                                ✕ Bỏ chọn Khối <?= $grade ?>
                            </button>
                        </div>
                    </div>
                    <div class="class-grid g<?= $grade ?>">
                        <?php if (empty($classesInGrade)): ?>
                            <div class="muted small" style="grid-column: 1 / -1; padding: 8px 0;">Chưa có lớp nào trong khối này.</div>
                        <?php else: ?>
                            <?php foreach ($classesInGrade as $c): ?>
                                <label class="class-chip-box">
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

            <div style="margin-top: 24px; display: flex; align-items: center; justify-content: space-between;">
                <p class="muted small">💡 <i>Hệ thống tự động bỏ qua nếu lớp học đó đã được phân công cùng giáo viên và môn học.</i></p>
                <button type="submit" class="btn primary" style="padding: 12px 28px; font-size: 15px; font-weight: 700;">
                    ⚡ Cấp Quyền & Phân Công Cho Giáo Viên Đã Chọn
                </button>
            </div>
        </form>
    </section>

    <!-- ===================================================================== -->
    <!-- PHẦN 3: TẠO TÀI KHOẢN GIÁO VIÊN MỚI & IMPORT HỌC SINH                  -->
    <!-- ===================================================================== -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
        <!-- Form Tạo tài khoản giáo viên mới -->
        <section class="panel-box" style="margin-bottom: 0;">
            <h3>👤 Tạo Tài Khoản Giáo Viên Mới</h3>
            <p class="muted small mb-3">Tạo tài khoản giáo viên THCS (mật khẩu băm Bcrypt chuẩn bảo mật).</p>
            <form method="POST">
                <input type="hidden" name="action" value="create_teacher">
                <div class="form-group mb-2">
                    <label>Tên Đăng Nhập (Username / VnEdu)</label>
                    <input type="text" name="username" placeholder="Ví dụ: gv_toan3, nguyen_van_a" required>
                </div>
                <div class="form-group mb-2">
                    <label>Họ và Tên Giáo Viên</label>
                    <input type="text" name="full_name" placeholder="Ví dụ: Thầy Trần Quang Khải" required>
                </div>
                <div class="form-group mb-3">
                    <label>Mật Khẩu Khởi Tạo (Mặc định: 123456)</label>
                    <input type="password" name="password" value="123456" required>
                </div>
                <button type="submit" class="btn primary" style="width: 100%;">
                    ➕ Tạo Tài Khoản Giáo Viên
                </button>
            </form>
        </section>

        <!-- Form Import danh sách học sinh theo lớp -->
        <section class="panel-box" style="margin-bottom: 0;">
            <h3>📥 Import Danh Sách Học Sinh Cho Lớp</h3>
            <p class="muted small mb-3">Nạp danh sách học sinh chuẩn VnEdu (.xlsx hoặc .csv) có chống trùng lặp dữ liệu.</p>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_students">
                <div class="form-group mb-2">
                    <label>Chọn Lớp Học Đích</label>
                    <select name="target_class_id" required>
                        <?php foreach ($allClasses as $c): ?>
                            <option value="<?= $c['id'] ?>">Lớp <?= htmlspecialchars($c['name']) ?> (Khối <?= $c['grade'] ?> - Hiện có <?= $c['student_count'] ?> HS)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mb-3">
                    <label>Tệp Danh Sách (.xlsx hoặc .csv)</label>
                    <input type="file" name="student_file" accept=".xlsx, .csv, .txt" required>
                </div>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <button type="submit" class="btn primary" style="flex: 1;">
                        🚀 Import Học Sinh Vào Lớp
                    </button>
                    <a href="sample_students.csv" download class="btn outline" style="font-size: 12.5px;">
                        📄 File mẫu .csv
                    </a>
                </div>
            </form>
        </section>
    </div>

    <!-- ===================================================================== -->
    <!-- PHẦN 4: DANH SÁCH PHÂN CÔNG & TRUY CẬP TRỰC TIẾP SỔ ĐIỂM               -->
    <!-- ===================================================================== -->
    <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
        <div>
            <h2>📋 Danh Sách Phân Công Giảng Dạy & Giám Sát Sổ Điểm</h2>
            <span class="muted small">Admin có thể mở trực tiếp bảng điểm của bất kỳ giáo viên nào để kiểm tra, thanh tra và chỉnh sửa điểm số.</span>
        </div>
        <div style="display: flex; gap: 8px;">
            <input type="text" id="assignSearchInput" placeholder="🔍 Tìm theo giáo viên, lớp, môn..." onkeyup="filterAssignments()" style="padding: 8px 14px; border-radius: 8px; border: 1px solid var(--border-color); font-size: 13.5px; width: 260px;">
        </div>
    </div>

    <div class="table-wrap mb-3">
        <table id="assignmentTable">
            <thead>
                <tr>
                    <th style="width: 110px;">Lớp Học</th>
                    <th style="width: 100px;">Sĩ Số</th>
                    <th style="width: 150px;">Môn Phụ Trách</th>
                    <th>Giáo Viên Giảng Dạy</th>
                    <th style="width: 140px;">Tài Khoản GV</th>
                    <th style="width: 200px; text-align: center;">Quyền Admin: Giám Sát Sổ Điểm</th>
                    <th style="width: 80px; text-align: center;">Hủy</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($assignments)): ?>
                    <tr><td colspan="7" style="text-align: center;" class="muted">Chưa có lớp nào được phân công. Hãy tích chọn ở trên để cấp quyền phân công.</td></tr>
                <?php else: ?>
                    <?php foreach ($assignments as $a): ?>
                        <tr>
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
                            <td><b><?= htmlspecialchars($a['teacher_name']) ?></b></td>
                            <td><span class="mono-code"><?= htmlspecialchars($a['username']) ?></span></td>
                            <td style="text-align: center;">
                                <a href="admin.php?impersonate=<?= $a['teacher_id'] ?>&sub=<?= urlencode($a['subject']) ?>&cid=<?= $a['class_id'] ?>" 
                                   class="btn primary" style="font-size: 12px; padding: 6px 14px;">
                                    🔍 Mở Bảng Điểm Môn <?= htmlspecialchars($a['subject']) ?>
                                </a>
                            </td>
                            <td style="text-align: center;">
                                <a href="admin.php?delete_assign=<?= $a['id'] ?>" class="btn outline" style="color: #dc2626; padding: 6px 10px;" onclick="return confirm('Bạn có chắc chắn muốn hủy phân công giáo viên này ở lớp này?')">
                                    ✕
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ===================================================================== -->
    <!-- PHẦN 5: NHẬT KÝ KIỂM TOÁN HỆ THỐNG THỜI GIAN THỰC (AUDIT LOG VIEWER)   -->
    <!-- ===================================================================== -->
    <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
        <div>
            <h2>🛡️ Nhật Ký Kiểm Toán Hệ Thống (Audit Logs)</h2>
            <span class="muted small">Tự động ghi nhận mọi hoạt động đăng nhập, phân công, nạp học sinh, sửa sổ điểm và các nỗ lực truy cập trái phép.</span>
        </div>
        <div style="display: flex; align-items: center; gap: 10px;">
            <input type="text" id="logSearchInput" placeholder="🔍 Tìm kiếm trong nhật ký..." onkeyup="filterAuditLogs()" style="padding: 7px 12px; border-radius: 8px; border: 1px solid var(--border-color); font-size: 13px; width: 220px;">
            <select onchange="window.location.href='admin.php?log_limit=' + this.value" style="padding: 7px 10px; border-radius: 8px; border: 1px solid var(--border-color); font-size: 13px;">
                <option value="10" <?= $logLimit === 10 ? 'selected' : '' ?>>Hiện 10 dòng</option>
                <option value="25" <?= $logLimit === 25 ? 'selected' : '' ?>>Hiện 25 dòng</option>
                <option value="50" <?= $logLimit === 50 ? 'selected' : '' ?>>Hiện 50 dòng</option>
                <option value="100" <?= $logLimit === 100 ? 'selected' : '' ?>>Hiện 100 dòng</option>
            </select>
            <a href="admin.php" class="btn outline" style="padding: 7px 12px; font-size: 12.5px;">🔄 Làm mới</a>
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

// 3. Cập nhật bộ đếm số lượng lớp đã chọn
function updateSelectedCount() {
    const checked = document.querySelectorAll('.cb-class:checked').length;
    const total = document.querySelectorAll('.cb-class').length;
    const el = document.getElementById('selectedClassCount');
    if (el) {
        el.innerText = `Đã chọn: ${checked} / ${total} lớp`;
        el.style.fontWeight = checked > 0 ? '700' : 'normal';
        el.style.color = checked > 0 ? 'var(--primary)' : 'var(--text-muted)';
    }

    // Cập nhật trạng thái hiển thị của chip box
    document.querySelectorAll('.class-chip-box').forEach(box => {
        const input = box.querySelector('input[type="checkbox"]');
        if (input && input.checked) {
            box.classList.add('selected');
        } else {
            box.classList.remove('selected');
        }
    });
}

// 4. Lọc tìm kiếm bảng phân công theo thời gian thực
function filterAssignments() {
    const query = document.getElementById('assignSearchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#assignmentTable tbody tr');
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(query) ? '' : 'none';
    });
}

// 5. Lọc tìm kiếm bảng nhật ký kiểm toán theo thời gian thực
function filterAuditLogs() {
    const query = document.getElementById('logSearchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#auditLogTable tbody tr');
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(query) ? '' : 'none';
    });
}

// Khởi chạy đồng bộ trạng thái khi tải trang
document.addEventListener('DOMContentLoaded', () => {
    updateSelectedCount();
});
</script>
</body>
</html>
