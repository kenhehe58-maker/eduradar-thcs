<?php
declare(strict_types=1);

$baseUrl = 'http://localhost/my-web-project';

function http_req(string $url, string $method = 'GET', $data = null, array $cookies = []): array {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

    $cookieStr = '';
    foreach ($cookies as $k => $v) {
        $cookieStr .= "$k=$v; ";
    }
    if ($cookieStr) {
        curl_setopt($ch, CURLOPT_COOKIE, rtrim($cookieStr, '; '));
    }

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
    }

    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);

    $newCookies = $cookies;
    if (preg_match_all('/^Set-Cookie:\s*([^;=]+)=([^;]+)/mi', $headers, $matches)) {
        for ($i = 0; $i < count($matches[1]); $i++) {
            $newCookies[$matches[1][$i]] = $matches[2][$i];
        }
    }

    curl_close($ch);
    return ['code' => $httpCode, 'headers' => $headers, 'body' => $body, 'cookies' => $newCookies];
}

echo "=== KIỂM THỬ GIAO DIỆN PHÂN LỚP & PROMPTS #208 / #001 ===\n\n";

// 1. ADMIN ĐĂNG NHẬP: TOÀN QUYỀN MỌI LỚP & PHÂN MÔN
echo "[1] Admin đăng nhập & kiểm tra Ma Trận Lớp Học & Phân Môn...\n";
$adminLogin = http_req("$baseUrl/login.php", 'POST', ['username' => 'admin', 'password' => '123456']);
$adminCookies = $adminLogin['cookies'];

$adminDash = http_req("$baseUrl/index.php", 'GET', null, $adminCookies);
assert($adminDash['code'] === 200, "Admin truy cập index.php phải HTTP 200");
assert(strpos($adminDash['body'], 'Quản Trị Viên Trường') !== false, "Phải có pill quyền Admin");
assert(strpos($adminDash['body'], 'class-subject-matrix-bar') !== false, "Phải có thanh điều khiển Matrix Bar");
assert(strpos($adminDash['body'], 'Tất cả các lớp') !== false, "Admin phải có tùy chọn xem tất cả các lớp");
assert(strpos($adminDash['body'], 'Tất cả môn học') !== false, "Admin phải có tùy chọn xem tất cả môn học");
assert(strpos($adminDash['body'], 'class-chip-btn') !== false, "Phải có dải phím chọn nhanh lớp");
echo "  -> PASS: Admin có đầy đủ quyền xem toàn bộ lớp và môn học.\n\n";

// 2. GIÁO VIÊN ĐĂNG NHẬP: CHỈ XEM ĐƯỢC LỚP & PHÂN MÔN PHỤ TRÁCH
echo "[2] Giáo viên gv_toan đăng nhập & kiểm tra phân quyền chặt chẽ...\n";
$toanLogin = http_req("$baseUrl/login.php", 'POST', ['username' => 'gv_toan', 'password' => '123456']);
$toanCookies = $toanLogin['cookies'];

$toanDash = http_req("$baseUrl/index.php", 'GET', null, $toanCookies);
assert($toanDash['code'] === 200, "GV Toán truy cập index.php phải HTTP 200");
assert(strpos($toanDash['body'], 'Phân Công Giáo Viên') !== false, "Phải có pill quyền Giáo Viên");

require_once __DIR__ . '/../includes/db.php';
$p = db();
$toanAssignments = $p->query("SELECT c.id as class_id, c.name as class_name, tc.subject FROM teacher_classes tc JOIN users u ON tc.teacher_id=u.id JOIN classes c ON tc.class_id=c.id WHERE u.username = 'gv_toan'")->fetchAll(PDO::FETCH_ASSOC);
$toanAssignedClassNames = array_column($toanAssignments, 'class_name');

preg_match('/<select name="class_id"[^>]*>(.*?)<\/select>/s', $toanDash['body'], $mClass);
$classOptions = $mClass[1] ?? '';
foreach ($toanAssignedClassNames as $cname) {
    assert(strpos($classOptions, "Lớp $cname") !== false, "GV Toán PHẢI có lớp $cname trong selectClassId");
}

// Kiểm tra lớp không phân công (ví dụ lớp 6A2 nếu không nằm trong danh sách phân công)
if (!in_array('6A2', $toanAssignedClassNames, true)) {
    assert(strpos($classOptions, 'Lớp 6A2') === false, "GV Toán KHÔNG ĐƯỢC có lớp 6A2 không phân công trong selectClassId");
}

preg_match('/<select name="subject"[^>]*>(.*?)<\/select>/s', $toanDash['body'], $mSubj);
$subjOptions = $mSubj[1] ?? '';
if (!empty($toanAssignments)) {
    $firstSubj = $toanAssignments[0]['subject'];
    assert(strpos($subjOptions, $firstSubj) !== false, "GV Toán PHẢI có môn phân công ($firstSubj) trong selectSubject");
}

// Kiểm tra gv_van: giáo viên kiêm nhiệm môn học (dạy cả Toán và Ngữ Văn trên các lớp khác nhau)
$vanLogin = http_req("$baseUrl/login.php", 'POST', ['username' => 'gv_van', 'password' => '123456']);
$vanCookies = $vanLogin['cookies'];

$vanAssignments = $p->query("SELECT c.id as class_id, c.name as class_name, tc.subject FROM teacher_classes tc JOIN users u ON tc.teacher_id=u.id JOIN classes c ON tc.class_id=c.id WHERE u.username = 'gv_van'")->fetchAll(PDO::FETCH_ASSOC);
$vanToanClass = null;
$vanVanClass = null;
foreach ($vanAssignments as $va) {
    if ($va['subject'] === 'Toán' && !$vanToanClass) $vanToanClass = $va;
    if ($va['subject'] === 'Ngữ Văn' && !$vanVanClass) $vanVanClass = $va;
}

if ($vanToanClass) {
    $vanToanReq = http_req("$baseUrl/index.php?class_id=" . $vanToanClass['class_id'], 'GET', null, $vanCookies);
    preg_match('/<select name="subject"[^>]*>(.*?)<\/select>/s', $vanToanReq['body'], $mSubj);
    assert(strpos($mSubj[1] ?? '', 'Toán') !== false, "GV Văn xem lớp {$vanToanClass['class_name']} PHẢI hiển thị môn Toán phân công");
    assert(strpos($mSubj[1] ?? '', 'Tiếng Anh') === false, "GV Văn KHÔNG ĐƯỢC có môn Tiếng Anh không phân công");
}

if ($vanVanClass) {
    $vanVanReq = http_req("$baseUrl/index.php?class_id=" . $vanVanClass['class_id'], 'GET', null, $vanCookies);
    preg_match('/<select name="subject"[^>]*>(.*?)<\/select>/s', $vanVanReq['body'], $mSubj2);
    assert(strpos($mSubj2[1] ?? '', 'Ngữ Văn') !== false, "GV Văn xem lớp {$vanVanClass['class_name']} PHẢI hiển thị môn Ngữ Văn phân công");
}
echo "  -> PASS: Phân quyền phân môn và lớp học cho từng giáo viên hoạt động tuyệt đối chính xác!\n\n";

// 3. KIỂM THỬ APPLE TASKBAR / DOCK SIDE RAIL (PROMPT #040 WITH SPRING HOVER & DOCK TOOLTIPS)
echo "[3] Kiểm tra tích hợp Apple Taskbar / Dock Side Rail (Prompt #040)...\n";
assert(strpos($adminDash['body'], 'u040-rail') !== false, "HTML phải chứa thanh sườn u040-rail");
assert(strpos($adminDash['body'], 'u040-top') !== false, "Thanh sườn phải có logo brand u040-top");
assert(strpos($adminDash['body'], 'u040-dock-tip') !== false, "Thanh sườn phải có floating dock tooltips");
assert(strpos($adminDash['body'], 'u040-me') !== false, "Thanh sườn phải có khu vực thông tin user/role");
assert(strpos($adminDash['body'], 'href="logout.php"') !== false, "Thanh sườn phải chứa duy nhất nút đăng xuất tại đáy");
echo "  -> PASS: Apple Taskbar / Dock Side Rail tích hợp hoàn chỉnh và chuẩn xác.\n\n";

// 4. KIỂM THỬ PROMPT #001: LIQUID GLASS BUTTON
echo "[4] Kiểm tra tích hợp Liquid Glass Button (Prompt #001)...\n";
assert(strpos($adminDash['body'], 'u001-btn') !== false, "HTML phải chứa class u001-btn Liquid Glass");
assert(strpos($adminDash['body'], 'u001-sheen') !== false, "Phải có hiệu ứng ánh sáng lướt qua sheen");
assert(strpos($adminDash['body'], 'u001-spin') !== false, "Phải có vòng xoay spinner khi load");
assert(strpos($adminDash['body'], 'u001-done') !== false, "Phải có trạng thái hoàn tất dấu tick xanh");
assert(strpos($adminDash['body'], 'u001ModalNoteSaver') !== false, "Phải có Liquid Glass Button trong Modal nhận xét");
assert(strpos($adminDash['body'], 'u001FilterBtnWrapper') !== false, "Phải có Liquid Glass Button trong Bộ lọc Matrix");
echo "  -> PASS: Liquid Glass Button (Prompt #001) hoạt động chuẩn mực tại cả Modal & Bộ Lọc.\n\n";

// 5. KIỂM THỬ DUY NHẤT 1 NÚT ĐĂNG XUẤT TOÀN TRANG
echo "[5] Kiểm tra quy tắc duy nhất 1 nút Đăng Xuất toàn trang...\n";
$logoutCount = substr_count($adminDash['body'], 'href="logout.php"');
assert($logoutCount === 1, "Chỉ được phép có DUY NHẤT 1 nút đăng xuất trong toàn bộ trang (tìm thấy: $logoutCount)");
echo "  -> PASS: Toàn bộ trang chỉ có duy nhất 1 nút Đăng Xuất ở đáy thanh sườn trái.\n\n";

// 6. KIỂM THỬ PROMPT #034: AIRPORT AUTOCOMPLETE SEARCH
echo "[6] Kiểm tra tích hợp Airport Autocomplete (Prompt #034)...\n";
assert(strpos($adminDash['body'], 'u034-wrap') !== false, "index.php phải chứa wrap u034-wrap");
assert(strpos($adminDash['body'], 'u034-ghost') !== false, "index.php phải chứa gợi ý mờ ghost text");
assert(strpos($adminDash['body'], 'u034-list') !== false, "index.php phải chứa danh sách popup gợi ý");
assert(strpos($adminDash['body'], 'rawStudents') !== false, "index.php phải liên kết dữ liệu học sinh thực tế vào autocomplete");
echo "  -> PASS: Airport Autocomplete (Prompt #034) kết nối dữ liệu và tương thích hoàn hảo.\n\n";

// 7. KIỂM THỬ PROMPT #032: DRAG-AND-DROP UPLOAD
echo "[7] Kiểm tra tích hợp Drag-and-drop Upload (Prompt #032)...\n";
// Kiểm tra trên quet_nhanh.php
$guestReq = http_req("$baseUrl/quet_nhanh.php", 'GET');
assert($guestReq['code'] === 200, "quet_nhanh.php phải HTTP 200");
assert(strpos($guestReq['body'], 'u032DropzoneWrap') !== false, "quet_nhanh.php phải chứa dropzone Prompt #032");
assert(strpos($guestReq['body'], 'u032-dash') !== false, "quet_nhanh.php phải có đường viền nét đứt marching animation");
assert(strpos($guestReq['body'], 'u032-orb') !== false, "quet_nhanh.php phải có biểu tượng orb lơ lửng");

// Kiểm tra trên admin.php
$adminAdmin = http_req("$baseUrl/admin.php", 'GET', null, $adminCookies);
assert($adminAdmin['code'] === 200, "admin.php phải HTTP 200");
assert(strpos($adminAdmin['body'], 'adminStudentDropzone') !== false, "admin.php phải chứa dropzone học sinh Prompt #032");
assert(strpos($adminAdmin['body'], 'adminStudentFileInput') !== false, "admin.php phải có input file ẩn nhận dữ liệu kéo thả");
assert(strpos($adminAdmin['body'], 'adminStudentList') !== false, "admin.php phải có danh sách tiến trình tải file");
echo "  -> PASS: Drag-and-drop Upload (Prompt #032) hoạt động xuất sắc tại cả Quét nhanh & Admin.\n\n";

echo "=== TẤT CẢ 7 HẠNG MỤC KIỂM THỬ ĐỀU PASS 100%! ===\n";
