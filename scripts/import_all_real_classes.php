<?php
// scripts/import_all_real_classes.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/xlsx_helper.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = db();

echo "====================================================================\n";
echo "  EDURADAR — NẠP TOÀN BỘ 19 LỚP & 798 HỌC SINH THỰC TẾ TỪ EXCEL\n";
echo "====================================================================\n\n";

// 1. Xác định tệp nguồn
$sourceFile = 'C:/Users/Admin/Downloads/DanhSachHocSinh (1).xlsx';
if (!file_exists($sourceFile)) {
    $sourceFile = 'C:/Users/Admin/Downloads/DanhSachHocSinh.xlsx';
}
if (!file_exists($sourceFile)) {
    $sourceFile = __DIR__ . '/../uploads/DanhSachHocSinh_7A6.xlsx';
}
if (!file_exists($sourceFile)) {
    die("Lỗi: Không tìm thấy tệp danh sách học sinh trên hệ thống!\n");
}

$destDir = __DIR__ . '/../uploads';
if (!is_dir($destDir)) {
    mkdir($destDir, 0777, true);
}
$destFile = $destDir . '/DanhSachHocSinh_ToanTruong.xlsx';
copy($sourceFile, $destFile);
echo "1. Đã sao chép tệp nguồn vào: uploads/DanhSachHocSinh_ToanTruong.xlsx\n";

// 2. Lấy danh sách toàn bộ các sheet (mỗi sheet là 1 lớp học)
$sheets = get_xlsx_sheets($sourceFile);
echo "2. Tìm thấy " . count($sheets) . " lớp học trong tệp Excel: " . implode(', ', $sheets) . "\n\n";

if (empty($sheets)) {
    die("Lỗi: Không tìm thấy trang tính nào trong tệp Excel!\n");
}

// 3. Chuẩn bị bảng classes: đảm bảo tất cả các lớp trong file đều tồn tại
$stmtFindClass = $pdo->prepare("SELECT id FROM classes WHERE name = ?");
$stmtInsClass = $pdo->prepare("INSERT INTO classes (name, grade, section) VALUES (?, ?, ?)");

$classMap = []; // [className => classId]
foreach ($sheets as $sName) {
    $stmtFindClass->execute([$sName]);
    $cid = $stmtFindClass->fetchColumn();
    if (!$cid) {
        preg_match('/^(\d+)([A-Za-z]+)(\d*)$/', $sName, $m);
        $grade = (int)($m[1] ?? 7);
        $section = strtoupper($m[2] ?? 'A');
        $stmtInsClass->execute([$sName, $grade, $section]);
        $cid = (int)$pdo->lastInsertId();
        echo "   + Đã tạo mới lớp [$sName] (ID: $cid, Khối $grade, Ban $section)\n";
    } else {
        $cid = (int)$cid;
    }
    $classMap[$sName] = $cid;
}

// 4. Xóa sạch dữ liệu điểm và học sinh cũ để nạp mới toàn bộ 100% chuẩn xác
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
$deletedScores = $pdo->exec("DELETE FROM scores");
$deletedStudents = $pdo->exec("DELETE FROM students");
$pdo->exec("ALTER TABLE students AUTO_INCREMENT = 1");
$pdo->exec("ALTER TABLE scores AUTO_INCREMENT = 1");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

echo "\n3. Đã dọn sạch dữ liệu cũ (Xóa $deletedStudents học sinh, $deletedScores điểm).\n\n";

// 5. Nạp học sinh từng sheet vào lớp tương ứng
$insStudent = $pdo->prepare("
    INSERT INTO students (class_id, student_code, full_name, birthday, gender, phone)
    VALUES (?, ?, ?, ?, ?, ?)
");

$totalImported = 0;
$classReport = [];

foreach ($sheets as $sName) {
    $classId = $classMap[$sName];
    $rows = parse_xlsx($sourceFile, $sName);
    
    // Tìm dòng header
    $startRow = 1;
    if (isset($rows[0])) {
        $firstCell = mb_strtolower(trim((string)$rows[0][0]));
        if ($firstCell === 'stt' || $firstCell === 'số tt') {
            $startRow = 1;
        }
    }

    $sheetCount = 0;
    for ($i = $startRow; $i < count($rows); $i++) {
        $r = $rows[$i];
        if (empty($r)) continue;

        $code = trim((string)($r[1] ?? ''));
        $fullName = trim((string)($r[2] ?? ''));
        $gender = trim((string)($r[3] ?? ''));
        $rawDob = trim((string)($r[4] ?? ''));
        $phone = trim((string)($r[6] ?? '')); // cột email hoặc phone nếu có

        if ($fullName === '') continue;

        // Chuẩn hóa ngày sinh
        $dob = null;
        if ($rawDob !== '' && stripos($rawDob, 'invalid') === false) {
            $dob = normalize_excel_date($rawDob);
        }

        // Chuẩn hóa giới tính
        if (in_array(mb_strtolower($gender), ['nam', 'nữ', 'nu'])) {
            $gender = mb_convert_case($gender, MB_CASE_TITLE, "UTF-8");
        } else {
            $gender = null;
        }

        $insStudent->execute([$classId, $code, $fullName, $dob, $gender, '']);
        $sheetCount++;
        $totalImported++;
    }

    $classReport[$sName] = $sheetCount;
    echo "   -> Đã nạp $sheetCount học sinh vào lớp [$sName] (ID: $classId)\n";
}

echo "\n4. TỔNG CỘNG ĐÃ NẠP THÀNH CÔNG: $totalImported HỌC SINH VÀO " . count($sheets) . " LỚP HỌC!\n\n";

// 6. Phân công giảng dạy cho giáo viên bộ môn
$teacherToan = $pdo->query("SELECT id FROM users WHERE username = 'gv_toan'")->fetchColumn();
$teacherVan = $pdo->query("SELECT id FROM users WHERE username = 'gv_van'")->fetchColumn();

$assignedToan = 0;
$assignedVan = 0;

$stmtAssign = $pdo->prepare("INSERT IGNORE INTO teacher_classes (teacher_id, class_id, subject) VALUES (?, ?, ?)");

foreach ($classMap as $sName => $cId) {
    if ($teacherToan) {
        $stmtAssign->execute([$teacherToan, $cId, 'Toán']);
        $assignedToan++;
    }
    if ($teacherVan) {
        $stmtAssign->execute([$teacherVan, $cId, 'Ngữ Văn']);
        $assignedVan++;
    }
}

echo "5. Đã phân công giảng dạy:\n";
echo "   - Thầy gv_toan: Môn Toán cho $assignedToan lớp ({$sheets[0]} ... {$sheets[count($sheets)-1]})\n";
echo "   - Cô gv_van: Môn Ngữ Văn cho $assignedVan lớp ({$sheets[0]} ... {$sheets[count($sheets)-1]})\n\n";

// 7. Ghi nhật ký hệ thống
log_action(
    1,
    'admin',
    'Nạp toàn bộ học sinh các lớp',
    "Admin nạp thành công $totalImported học sinh thật vào " . count($sheets) . " lớp học (" . implode(', ', $sheets) . ") từ tệp Excel chuẩn VnEdu."
);
echo "6. Đã ghi nhận sự kiện vào audit_logs.\n";
echo "\n====================================================================\n";
echo "  HOÀN TẤT THÀNH CÔNG TOÀN BỘ TIẾN TRÌNH!\n";
echo "====================================================================\n";
