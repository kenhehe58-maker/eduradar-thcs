<?php
/**
 * SEED_MIDTERM1_GRADE9.php
 * Khởi tạo dữ liệu thực tế sau kỳ thi Giữa kì 1 (GK1).
 * Đầy đủ 37 lớp chuẩn THCS, phân công chuyên môn rõ ràng.
 * 4 lớp Khối 9 (9A1, 9A2, 9A3, 9A4) với 144 học sinh có bảng điểm Giữa kì 1.
 * Trạng thái: Đã có TX1, TX2, TX3, GK. Chưa có CK (CK = NULL).
 */

require_once dirname(__DIR__) . '/includes/db.php';
$pdo = db();

echo "=== BẮT ĐẦU RESET VÀ TẠO DỮ LIỆU CHUẨN GIỮA KÌ 1 ===\n";

$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
$pdo->exec("TRUNCATE TABLE scores");
$pdo->exec("TRUNCATE TABLE score_audit_logs");
$pdo->exec("TRUNCATE TABLE student_interventions");
$pdo->exec("TRUNCATE TABLE audit_logs");
$pdo->exec("TRUNCATE TABLE students");
$pdo->exec("TRUNCATE TABLE teacher_classes");
$pdo->exec("TRUNCATE TABLE classes");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

// 1. Tạo 37 lớp học chuẩn THCS (Khối 6-9, Ban A-C, Lớp 1-3 + 7A6)
$stmtClass = $pdo->prepare("INSERT INTO classes (id, name, grade, section) VALUES (?, ?, ?, ?)");
$classesSeed = [
    // Khối 6
    [1, '6A1', 6, 'A'], [2, '6A2', 6, 'A'], [3, '6A3', 6, 'A'],
    [4, '6B1', 6, 'B'], [5, '6B2', 6, 'B'], [6, '6B3', 6, 'B'],
    [7, '6C1', 6, 'C'], [8, '6C2', 6, 'C'], [9, '6C3', 6, 'C'],
    // Khối 7
    [10, '7A1', 7, 'A'], [11, '7A2', 7, 'A'], [12, '7A3', 7, 'A'],
    [13, '7B1', 7, 'B'], [14, '7B2', 7, 'B'], [15, '7B3', 7, 'B'],
    [16, '7C1', 7, 'C'], [17, '7C2', 7, 'C'], [18, '7C3', 7, 'C'],
    // Khối 8
    [19, '8A1', 8, 'A'], [20, '8A2', 8, 'A'], [21, '8A3', 8, 'A'],
    [22, '8B1', 8, 'B'], [23, '8B2', 8, 'B'], [24, '8B3', 8, 'B'],
    [25, '8C1', 8, 'C'], [26, '8C2', 8, 'C'], [27, '8C3', 8, 'C'],
    // Khối 9
    [28, '9A1', 9, 'A'], [29, '9A2', 9, 'A'], [30, '9A3', 9, 'A'],
    [31, '9B1', 9, 'B'], [32, '9B2', 9, 'B'], [33, '9B3', 9, 'B'],
    [34, '9C1', 9, 'C'], [35, '9C2', 9, 'C'], [36, '9C3', 9, 'C'],
    // Lớp thực tế 7A6
    [37, '7A6', 7, 'A'],
    // Thêm 9A4 cho khối 9
    [38, '9A4', 9, 'A']
];

foreach ($classesSeed as $c) {
    $stmtClass->execute($c);
}
echo "-> Đã nạp 38 lớp chuẩn THCS.\n";

// 2. Phân công giáo viên chuyên môn (đảm bảo có cả lớp được phân công và lớp KHÔNG được phân công để Anti-BOLA hoạt động)
// id 1: admin (Quản trị / Hiệu trưởng)
// id 2: gv_toan (Thầy Nguyễn Văn Giáo)
// id 3: gv_van (Cô Lê Thị Hương)
// id 169: gv_anh (Cô Trần Thị Mai Anh)
// id 170: gv_khtn (Thầy Phạm Hoàng Nam)

$assignments = [
    // gv_toan (2) dạy Toán tại 9A1 (28), 9A2 (29), 7A6 (37), 6A1 (1) -> KHÔNG dạy 9A3, 9A4, 8A1
    [2, 28, 'Toán'],
    [2, 29, 'Toán'],
    [2, 37, 'Toán'],
    [2, 1, 'Toán'],

    // gv_van (3) dạy Ngữ Văn tại 9A1 (28), 9A2 (29), 7A6 (37)
    [3, 28, 'Ngữ Văn'],
    [3, 29, 'Ngữ Văn'],
    [3, 37, 'Ngữ Văn'],

    // gv_anh (169) dạy Tiếng Anh tại 9A1 (28), 9A3 (30)
    [169, 28, 'Tiếng Anh'],
    [169, 30, 'Tiếng Anh'],

    // gv_khtn (170) dạy KHTN tại 9A1 (28), 9A4 (38)
    [170, 28, 'KHTN'],
    [170, 38, 'KHTN'],
];

$stmtTC = $pdo->prepare("INSERT INTO teacher_classes (teacher_id, class_id, subject) VALUES (?, ?, ?)");
foreach ($assignments as $a) {
    $stmtTC->execute($a);
}
echo "-> Đã phân công chuyên môn giáo viên.\n";

// 3. Nạp 41 học sinh thật lớp 7A6 (đáp ứng tương thích kiểm thử)
$real7A6 = [
    [1, 37, '2307360827', 'Trần Nhị Phương Anh', null, 'Nữ', ''],
    [2, 37, '2200868590', 'Sơn Thị Bích Chi', null, 'Nữ', ''],
    [3, 37, '2308865877', 'Trần Trí Dỉ', null, 'Nam', ''],
    [4, 37, '2200868401', 'Lâm Ngọc Tâm Đoan', null, 'Nữ', ''],
    [5, 37, '2200868400', 'Hồng Gia Hảo', '2014-05-06', 'Nam', ''],
    [6, 37, '2307360978', 'Cao Gia Hân', null, 'Nữ', ''],
    [7, 37, '2307209553', 'Huỳnh Khả Hân', '2014-10-11', 'Nữ', ''],
    [8, 37, '2309633484', 'Huỳnh Phú Hiển', '2014-11-03', 'Nam', ''],
    [9, 37, '2308864459', 'Sơn Minh Hiếu', '2014-07-07', 'Nam', ''],
    [10, 37, '2507238307', 'Trần Quốc Huy', '2014-12-06', 'Nam', ''],
    [11, 37, '2200868554', 'Diệp Phú Hưng', null, 'Nam', ''],
    [12, 37, '2200868556', 'Hàn Tuấn Khang', null, 'Nam', ''],
    [13, 37, '2307209352', 'Ngô Lành', '2014-03-10', 'Nam', ''],
    [14, 37, '2200868563', 'Huỳnh Kim Lộc', null, 'Nam', ''],
    [15, 37, '2200868534', 'Dương Trần Tú My', '2014-12-04', 'Nữ', ''],
    [16, 37, '2200868535', 'Phạm Bảo Nguyên', null, 'Nam', ''],
    [17, 37, '2407893367', 'Trần Trọng Nhân', '2014-10-05', 'Nam', ''],
    [18, 37, '2301573400', 'La Bảo Nhi', null, 'Nữ', ''],
    [19, 37, '2308864471', 'Sơn Thị Mỹ Nhi', null, 'Nữ', ''],
    [20, 37, '2200868537', 'Dương Gia Phú', '2014-11-12', 'Nam', ''],
    [21, 37, '2200868538', 'Mã Kiến Phúc', null, 'Nam', ''],
    [22, 37, '2308864474', 'Tăng Hoàng Phúc', null, 'Nam', ''],
    [23, 37, '2200868575', 'Huỳnh Kim Quý', null, 'Nam', ''],
    [24, 37, '2200868539', 'Lưu Thị Thanh Quyên', null, 'Nữ', ''],
    [25, 37, '2308864476', 'Trần Minh Tài', '2014-03-10', 'Nam', ''],
    [26, 37, '2200868512', 'Ngô Thái Tâm', '2013-01-12', 'Nam', ''],
    [27, 37, '2200868609', 'Thạch Thị Thu Thanh', '2014-06-10', 'Nữ', ''],
    [28, 37, '2308865897', 'Lý Minh Thuận', '2014-04-04', 'Nam', ''],
    [29, 37, '2200868645', 'Thạch Minh Thuận', '2014-10-04', 'Nam', ''],
    [30, 37, '2200868613', 'Vưu Phú Thuận', '2014-05-07', 'Nam', ''],
    [31, 37, '2507238367', 'Trần Thị Thanh Thuý', '2014-10-11', 'Nữ', ''],
    [32, 37, '2200868614', 'Lương Minh Thư', '2014-09-09', 'Nữ', ''],
    [33, 37, '2200868542', 'Trần Ngọc Tiên', '2013-10-06', 'Nữ', ''],
    [34, 37, '2507240867', 'Trần Thuỳ Trang', null, 'Nữ', ''],
    [35, 37, '2200868543', 'Vương Trần Bảo Trân', null, 'Nữ', ''],
    [36, 37, '2200868617', 'Lý Hữu Trí', '2014-08-10', 'Nam', ''],
    [37, 37, '2200868544', 'Lê Ngọc Trung', '2014-10-07', 'Nam', ''],
    [38, 37, '2308864520', 'Ong Thanh Vân', null, 'Nữ', ''],
    [39, 37, '2200787382', 'Phan Ái Vy', '2014-05-12', 'Nữ', ''],
    [40, 37, '2507240987', 'Quách Thuý Vy', '2014-01-08', 'Nữ', ''],
    [41, 37, '2309520883', 'Lê Thị Yến', '2011-01-01', 'Nữ', '']
];

$stmtStudent = $pdo->prepare("INSERT INTO students (id, class_id, student_code, full_name, birthday, gender, phone) VALUES (?, ?, ?, ?, ?, ?, ?)");
foreach ($real7A6 as $st) {
    $stmtStudent->execute($st);
}
echo "-> Đã nạp 41 học sinh thật lớp 7A6.\n";

// 4. Tạo danh sách học sinh cho 4 lớp Khối 9 (9A1: id 28, 9A2: id 29, 9A3: id 30, 9A4: id 38)
// Mỗi lớp 36 em = 144 em (id từ 42 đến 185)
$ho = ['Nguyễn', 'Trần', 'Lê', 'Phạm', 'Hoàng', 'Huỳnh', 'Phan', 'Vũ', 'Võ', 'Đặng', 'Bùi', 'Đỗ', 'Hồ', 'Ngô', 'Dương'];
$demNam = ['Văn', 'Đức', 'Thành', 'Quốc', 'Minh', 'Hữu', 'Tuấn', 'Gia', 'Hoàng', 'Thanh', 'Tiến', 'Bảo'];
$tenNam = ['An', 'Bình', 'Cường', 'Dũng', 'Đạt', 'Hải', 'Hiếu', 'Huy', 'Khoa', 'Long', 'Minh', 'Nam', 'Nghĩa', 'Phong', 'Phúc', 'Quân', 'Sơn', 'Tài', 'Thắng', 'Thịnh', 'Trung', 'Tú', 'Việt'];
$demNu = ['Thị', 'Ngọc', 'Phương', 'Thanh', 'Mai', 'Khánh', 'Như', 'Thu', 'Kim', 'Bảo', 'Ánh', 'Hồng'];
$tenNu = ['Anh', 'Châu', 'Chi', 'Dung', 'Hà', 'Hân', 'Hiền', 'Hoa', 'Hương', 'Lan', 'Linh', 'Mai', 'My', 'Nga', 'Ngân', 'Nhi', 'Như', 'Phương', 'Quỳnh', 'Thảo', 'Trang', 'Trâm', 'Uyên', 'Vy', 'Yến'];

$grade9Classes = [28 => '9A1', 29 => '9A2', 30 => '9A3', 38 => '9A4'];
$studentId = 42;
$grade9Students = [];

mt_srand(20261028);

foreach ($grade9Classes as $cId => $cName) {
    for ($s = 1; $s <= 36; $s++) {
        $isBoy = ($s % 2 === 1);
        $h = $ho[array_rand($ho)];
        if ($isBoy) {
            $m = $demNam[array_rand($demNam)];
            $t = $tenNam[array_rand($tenNam)];
            $gender = 'Nam';
        } else {
            $m = $demNu[array_rand($demNu)];
            $t = $tenNu[array_rand($tenNu)];
            $gender = 'Nữ';
        }
        $name = "$h $m $t";
        $code = "202609" . str_pad($studentId, 4, '0', STR_PAD_LEFT);
        $bMonth = mt_rand(1, 12);
        $bDay = mt_rand(1, 28);
        $bYear = 2011;
        $birthday = sprintf("%04d-%02d-%02d", $bYear, $bMonth, $bDay);
        $phone = "09" . mt_rand(10000000, 99999999);

        $stmtStudent->execute([$studentId, $cId, $code, $name, $birthday, $gender, $phone]);
        
        $grade9Students[] = [
            'id' => $studentId,
            'class_id' => $cId,
            'class_name' => $cName,
            'name' => $name,
            'index' => $s
        ];
        
        $studentId++;
    }
}
echo "-> Đã nạp 144 học sinh khối 9.\n";

// 5. Nạp bảng điểm Giữa kì 1 chuẩn Thông tư 22 (chỉ có TX1..TX3, GK; CK = NULL)
$stmtScore = $pdo->prepare("INSERT INTO scores (student_id, subject, tx1, tx2, tx3, tx4, gk, ck, avg_score) VALUES (?, ?, ?, ?, ?, NULL, ?, NULL, ?)");
$subjects = ['Toán', 'Ngữ Văn', 'Tiếng Anh', 'KHTN'];

foreach ($grade9Students as $st) {
    $sId = $st['id'];
    $idx = $st['index']; // 1..36

    foreach ($subjects as $sub) {
        $tx1 = null; $tx2 = null; $tx3 = null; $gk = null;

        if ($idx <= 8) {
            // Nhóm Tốt (ổn định cao)
            $tx1 = mt_rand(80, 100) / 10;
            $tx2 = mt_rand(85, 100) / 10;
            $tx3 = mt_rand(80, 95) / 10;
            $gk  = mt_rand(80, 100) / 10;
        } elseif ($idx <= 18) {
            // Nhóm Khá
            $tx1 = mt_rand(65, 80) / 10;
            $tx2 = mt_rand(70, 85) / 10;
            $tx3 = mt_rand(65, 80) / 10;
            $gk  = mt_rand(65, 85) / 10;
        } elseif ($idx <= 25) {
            // Nhóm Tiến bộ (Dốc OLS dương rõ rệt)
            $tx1 = mt_rand(50, 60) / 10;
            $tx2 = mt_rand(60, 70) / 10;
            $tx3 = mt_rand(70, 80) / 10;
            $gk  = mt_rand(80, 90) / 10;
        } elseif ($idx <= 31) {
            // Nhóm Sa sút (Cảnh báo đỏ của hệ thống!)
            $tx1 = mt_rand(75, 85) / 10;
            $tx2 = mt_rand(70, 80) / 10;
            $tx3 = mt_rand(65, 75) / 10;
            $gk  = mt_rand(35, 45) / 10;
        } elseif ($idx <= 35) {
            // Nhóm Yếu / Nguy cơ Chưa đạt
            $tx1 = mt_rand(35, 50) / 10;
            $tx2 = mt_rand(30, 45) / 10;
            $tx3 = mt_rand(40, 50) / 10;
            $gk  = mt_rand(30, 45) / 10;
        } else {
            // Nhóm Chờ điểm (Khuyết TX3)
            $tx1 = mt_rand(60, 80) / 10;
            $tx2 = mt_rand(60, 80) / 10;
            $tx3 = null;
            $gk  = mt_rand(65, 85) / 10;
        }

        $avg = null;
        if ($tx1 !== null && $tx2 !== null && $tx3 !== null && $gk !== null) {
            $avg = round(($tx1 + $tx2 + $tx3 + $gk * 2) / 5, 1);
        }

        $stmtScore->execute([$sId, $sub, $tx1, $tx2, $tx3, $gk, $avg]);
    }
}

// Nạp điểm cho 7A6 môn Toán và Ngữ Văn (đầy đủ cả CK cho các bài test trước đó)
for ($id7 = 1; $id7 <= 41; $id7++) {
    $stmtScore->execute([$id7, 'Toán', 8.0, 7.5, 8.5, 8.0, 8.0]);
    $stmtScore->execute([$id7, 'Ngữ Văn', 7.0, 7.5, 7.0, 7.5, 7.2]);
}

echo "-> Đã nạp bảng điểm Giữa kì 1 (576 dòng môn khối 9 + 82 dòng lớp 7A6).\n";

$pdo->exec("INSERT INTO audit_logs (user_id, username, action, details, ip_address) VALUES 
(1, 'admin', 'Khởi tạo dữ liệu Giữa kì 1', 'Thiết lập 38 lớp, phân công chuyên môn, và bảng điểm 144 học sinh khối 9 thời điểm vừa thi Giữa kì 1.', '127.0.0.1')");

echo "=== KHỞI TẠO HOÀN TẤT THÀNH CÔNG ===\n";
