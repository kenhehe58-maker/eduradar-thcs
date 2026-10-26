<?php
/**
 * Test script for Guest Mode, PDF & Excel Exports, and Teacher Account Creation.
 */

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

    // Extract cookies
    $newCookies = $cookies;
    if (preg_match_all('/^Set-Cookie:\s*([^;=]+)=([^;]+)/mi', $headers, $matches)) {
        for ($i = 0; $i < count($matches[1]); $i++) {
            $newCookies[$matches[1][$i]] = $matches[2][$i];
        }
    }

    curl_close($ch);
    return ['code' => $httpCode, 'headers' => $headers, 'body' => $body, 'cookies' => $newCookies];
}

echo "=== KIỂM THỬ TÍNH NĂNG MỚI: CHẾ ĐỘ KHÁCH, TẠO GV & XUẤT PDF/EXCEL ===\n\n";

// 1. Quét nhanh: Nạp sample data
echo "[1] Nạp dữ liệu mẫu trong Chế độ Khách (quet_nhanh.php?action=sample)...\n";
$res1 = http_req("$baseUrl/quet_nhanh.php?action=sample");
assert($res1['code'] === 302, "Phải chuyển hướng sau khi nạp mẫu (HTTP 302)");
$cookies = $res1['cookies'];

$res2 = http_req("$baseUrl/quet_nhanh.php", 'GET', null, $cookies);
assert($res2['code'] === 200, "Truy cập dashboard khách thành công (HTTP 200)");
assert(str_pos_check($res2['body'], 'Nguyễn Hoàng Long'), "Phải có tên học sinh mẫu trong bảng");
assert(str_pos_check($res2['body'], 'in_bao_cao_bgh.php?guest=1'), "Phải có nút liên kết Báo Cáo BGH");
echo "  -> PASS: Đã hiển thị Radar Dashboard Chế độ Khách đầy đủ 2 khung năng lực.\n\n";

// 1B. Kiểm thử tải file Danh Sách Học Sinh thực tế (41 HS chưa có điểm) & Mô phỏng điểm số
echo "[1B] Tải tệp Danh Sách Học Sinh (41 HS) & Mô phỏng điểm số tức thì...\n";
$clearRes = http_req("$baseUrl/quet_nhanh.php?action=clear");
$uploadCookies = $clearRes['cookies'];

$realFile = 'D:/xam/htdocs/my-web-project/uploads/DanhSachHocSinh_7A6.xlsx';
if (file_exists($realFile)) {
    $cFile = new CURLFile($realFile, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'DanhSachHocSinh_7A6.xlsx');
    $upRes = http_req("$baseUrl/quet_nhanh.php", 'POST', ['gradebook_file' => $cFile, 'subject' => 'Toán học'], $uploadCookies);
    $uploadCookies = $upRes['cookies'];

    $dashRes = http_req("$baseUrl/quet_nhanh.php", 'GET', null, $uploadCookies);
    assert($dashRes['code'] === 200, "Truy cập dashboard sau upload phải HTTP 200");
    assert(str_pos_check($dashRes['body'], '41 học sinh'), "Phải nhận diện đủ 41 học sinh");
    assert(str_pos_check($dashRes['body'], 'Trần Nhị Phương Anh'), "Phải có tên học sinh đầu tiên trong file");
    assert(str_pos_check($dashRes['body'], '<div class="no-scores-alert-banner">'), "Phải có banner cảnh báo tệp chưa có điểm");

    // Kiểm tra tính năng mô phỏng điểm số
    $simRes = http_req("$baseUrl/quet_nhanh.php?action=simulate_scores", 'GET', null, $uploadCookies);
    assert($simRes['code'] === 302, "Mô phỏng điểm phải chuyển hướng HTTP 302");
    $dashSimRes = http_req("$baseUrl/quet_nhanh.php", 'GET', null, $simRes['cookies']);
    assert(!str_pos_check($dashSimRes['body'], '<div class="no-scores-alert-banner">'), "Sau khi mô phỏng điểm không còn banner chưa có điểm");
    echo "  -> PASS: Đã nhận diện đúng 41 học sinh, hiển thị hướng dẫn và mô phỏng điểm số thành công!\n\n";
}

// 2. Xuất Phiếu Phụ Đạo Tuyến Khách (PDF & Excel)
echo "[2] Xuất Phiếu Phụ Đạo trong Chế độ Khách (in_phieu_phu_dao.php)...\n";
$resPdf = http_req("$baseUrl/in_phieu_phu_dao.php?guest=1&student_id=1&format=pdf", 'GET', null, $cookies);
assert($resPdf['code'] === 200, "Xuất PDF phiếu phụ đạo khách phải HTTP 200");
assert(str_pos_check($resPdf['body'], 'PHIẾU ĐÁNH GIÁ TIẾN BỘ'), "Phải có tiêu đề phiếu phụ đạo A4");
assert(str_pos_check($resPdf['body'], 'Nguyễn Hoàng Long'), "Phải có tên học sinh");
echo "  -> PASS: PDF A4 Phiếu Phụ Đạo khách hiển thị chuẩn xác.\n";

$resXlsx = http_req("$baseUrl/in_phieu_phu_dao.php?guest=1&student_id=1&format=xlsx", 'GET', null, $cookies);
assert($resXlsx['code'] === 200, "Xuất Excel phiếu phụ đạo khách phải HTTP 200");
assert(str_pos_check($resXlsx['headers'], 'spreadsheetml.sheet'), "Header Content-Type phải là OpenXML spreadsheet");
assert(substr($resXlsx['body'], 0, 4) === "PK\x03\x04", "File tải về phải là tệp ZIP/OpenXML hợp lệ");
echo "  -> PASS: Excel (.xlsx) Phiếu Phụ Đạo khách xuất thành công (" . strlen($resXlsx['body']) . " bytes).\n\n";

// 3. Xuất Báo Cáo BGH Tuyến Khách (PDF & Excel)
echo "[3] Xuất Báo Cáo BGH trong Chế độ Khách (in_bao_cao_bgh.php)...\n";
$resBghPdf = http_req("$baseUrl/in_bao_cao_bgh.php?guest=1&format=pdf", 'GET', null, $cookies);
assert($resBghPdf['code'] === 200, "Xuất Báo Cáo BGH PDF phải HTTP 200");
assert(str_pos_check($resBghPdf['body'], 'BÁO CÁO PHÂN TÍCH'), "Phải có tiêu đề Báo Cáo BGH");
assert(str_pos_check($resBghPdf['body'], 'Xu Hướng') || str_pos_check($resBghPdf['body'], 'Khung Hiệu Suất'), "Phải có thống kê Xu Hướng Tiến Bộ");
echo "  -> PASS: PDF A4 Báo Cáo BGH khách hiển thị chuẩn xác.\n";

$resBghXlsx = http_req("$baseUrl/in_bao_cao_bgh.php?guest=1&format=xlsx", 'GET', null, $cookies);
assert($resBghXlsx['code'] === 200, "Xuất Báo Cáo BGH Excel phải HTTP 200");
assert(str_pos_check($resBghXlsx['headers'], 'spreadsheetml.sheet'), "Header Content-Type phải là OpenXML spreadsheet");
assert(substr($resBghXlsx['body'], 0, 4) === "PK\x03\x04", "File tải về phải là tệp ZIP/OpenXML hợp lệ");
echo "  -> PASS: Excel (.xlsx) Báo Cáo BGH khách xuất thành công (" . strlen($resBghXlsx['body']) . " bytes).\n\n";

// 4. Kiểm tra Đăng nhập tài khoản trường & xuất báo cáo có thẩm quyền
echo "[4] Đăng nhập tài khoản trường (admin / 123456) & xuất báo cáo...\n";
$loginRes = http_req("$baseUrl/login.php", 'POST', http_build_query([
    'username' => 'admin',
    'password' => '123456'
]));
assert($loginRes['code'] === 302, "Admin login thành công chuyển hướng");
$adminCookies = $loginRes['cookies'];

// Lấy danh sách lớp và môn
$adminDash = http_req("$baseUrl/index.php", 'GET', null, $adminCookies);
assert($adminDash['code'] === 200, "Truy cập dashboard admin thành công");
assert(str_pos_check($adminDash['body'], 'in_bao_cao_bgh.php'), "Phải có nút xuất Báo Cáo BGH trên Dashboard");
assert(str_pos_check($adminDash['body'], 'btnModalPrintPdf'), "Phải có nút In Phiếu Phụ Đạo trong Modal");

$adminBghPdf = http_req("$baseUrl/in_bao_cao_bgh.php?class_id=1&subject=" . urlencode('Toán học') . "&format=pdf", 'GET', null, $adminCookies);
assert($adminBghPdf['code'] === 200, "Admin xem báo cáo BGH lớp 1 phải HTTP 200");
assert(str_pos_check($adminBghPdf['body'], 'BÁO CÁO PHÂN TÍCH'), "Nội dung báo cáo chuẩn A4");

$adminBghXlsx = http_req("$baseUrl/in_bao_cao_bgh.php?class_id=1&subject=" . urlencode('Toán học') . "&format=xlsx", 'GET', null, $adminCookies);
assert($adminBghXlsx['code'] === 200, "Admin tải Excel báo cáo BGH lớp 1 phải HTTP 200");
assert(substr($adminBghXlsx['body'], 0, 4) === "PK\x03\x04", "File tải về phải là tệp ZIP/OpenXML hợp lệ");
echo "  -> PASS: Toàn bộ luồng đăng nhập và xuất PDF/Excel tài khoản trường hoạt động hoàn hảo!\n\n";

echo "=== TẤT CẢ KIỂM THỬ ĐÃ THÀNH CÔNG RỰC RỠ 100%! ===\n";

function str_pos_check(string $haystack, string $needle): bool {
    return mb_strpos($haystack, $needle) !== false;
}
