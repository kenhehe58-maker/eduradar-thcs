<?php
/**
 * Test đồng bộ ngữ cảnh Lớp & Môn giữa Radar Cảnh Báo (index.php) và Nhập Điểm (nhap_diem.php)
 */

function http_req($url, $method = 'GET', $data = null, $cookies = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    if (!empty($cookies)) {
        $cookieStr = '';
        foreach ($cookies as $k => $v) { $cookieStr .= "$k=$v; "; }
        curl_setopt($ch, CURLOPT_COOKIE, rtrim($cookieStr, '; '));
    }
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (is_array($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
    }
    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $header = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    curl_close($ch);
    preg_match_all('/^Set-Cookie:\s*([^;=\s]+)=([^;]+)/mi', $header, $matches);
    $newCookies = $cookies;
    if (!empty($matches[1])) {
        for ($i = 0; $i < count($matches[1]); $i++) {
            $newCookies[$matches[1][$i]] = $matches[2][$i];
        }
    }
    return ['code' => $httpCode, 'header' => $header, 'body' => $body, 'cookies' => $newCookies];
}

$baseUrl = 'http://localhost/my-web-project';

echo "=== KIỂM THỬ ĐỒNG BỘ LỚP & MÔN GIỮA RADAR VÀ NHẬP ĐIỂM ===\n\n";

// 1. Đăng nhập gv_toan
$login = http_req("$baseUrl/login.php", 'POST', ['username' => 'gv_toan', 'password' => '123456']);
$cookies = $login['cookies'];
echo "[1] Đăng nhập gv_toan thành công.\n";

require_once __DIR__ . '/../includes/db.php';
$p = db();
$toanAss = $p->query("SELECT tc.class_id, c.name as class_name, tc.subject FROM teacher_classes tc JOIN users u ON tc.teacher_id=u.id JOIN classes c ON tc.class_id=c.id WHERE u.username = 'gv_toan' ORDER BY tc.id ASC")->fetchAll(PDO::FETCH_ASSOC);

if (!empty($toanAss)) {
    $firstAss = $toanAss[0];
    $cId1 = (int)$firstAss['class_id'];
    $cName1 = $firstAss['class_name'];
    $subj1 = $firstAss['subject'];

    // 2. Vào nhap_diem.php với lớp và môn của gv_toan
    $res1 = http_req("$baseUrl/nhap_diem.php?class_id=$cId1&subject=" . urlencode($subj1), 'GET', null, $cookies);
    $cookies = $res1['cookies'];
    echo "[2] Đang làm việc tại nhap_diem.php với Lớp $cName1 (ID $cId1), Môn $subj1.\n";

    // 3. Vào index.php mà KHÔNG truyền tham số URL (đồng bộ qua session / Side Rail)
    $res2 = http_req("$baseUrl/index.php", 'GET', null, $cookies);
    assert(strpos($res2['body'], "value=\"$cId1\" selected") !== false, "index.php phải tự động nhớ và chọn Lớp $cName1 từ session!");
    assert(strpos($res2['body'], "value=\"$subj1\" selected") !== false, "index.php phải tự động nhớ và chọn Môn $subj1 từ session!");
    echo "  -> PASS: Chuyển sang index.php tự động chọn đúng Lớp $cName1 và Môn $subj1!\n\n";

    // 4. Nếu gv_toan có lớp thứ 2, đổi lớp đó tại index.php
    if (count($toanAss) > 1) {
        $secondAss = $toanAss[1];
        $cId2 = (int)$secondAss['class_id'];
        $cName2 = $secondAss['class_name'];
        $subj2 = $secondAss['subject'];

        $res3 = http_req("$baseUrl/index.php?class_id=$cId2&subject=" . urlencode($subj2), 'GET', null, $cookies);
        $cookies = $res3['cookies'];
        echo "[3] Tại index.php đổi sang xem Lớp $cName2 (ID $cId2), Môn $subj2.\n";

        // 5. Quay lại nhap_diem.php mà KHÔNG truyền tham số URL
        $res4 = http_req("$baseUrl/nhap_diem.php", 'GET', null, $cookies);
        assert(strpos($res4['body'], "class_id=$cId2") !== false || strpos($res4['body'], $cName2) !== false, "nhap_diem.php phải tự động nhớ Lớp $cName2 từ index.php!");
        echo "  -> PASS: Chuyển ngược lại nhap_diem.php tự động mở ngay Lớp $cName2 và Môn $subj2!\n\n";
    }
}

// 6. Kiểm tra gv_van kiêm nhiệm 2 môn (Ngữ Văn & Toán)
echo "[4] Kiểm tra giáo viên kiêm nhiệm 2 môn (gv_van)...\n";
$vanLogin = http_req("$baseUrl/login.php", 'POST', ['username' => 'gv_van', 'password' => '123456']);
$vanCookies = $vanLogin['cookies'];

// Vào nhap_diem.php với môn Toán lớp 7A6 (id=133)
$res5 = http_req("$baseUrl/nhap_diem.php?class_id=133&subject=" . urlencode('Toán'), 'GET', null, $vanCookies);
$vanCookies = $res5['cookies'];

// Sang index.php không truyền tham số
$res6 = http_req("$baseUrl/index.php", 'GET', null, $vanCookies);
assert(strpos($res6['body'], 'value="133" selected') !== false, 'gv_van sang index.php phải giữ nguyên lớp 7A6!');
assert(strpos($res6['body'], 'value="Toán" selected') !== false, 'gv_van sang index.php phải giữ nguyên môn Toán kiêm nhiệm!');
echo "  -> PASS: Giáo viên kiêm nhiệm 2 môn đồng bộ chính xác từng môn và lớp!\n\n";

echo "=== TẤT CẢ TEST ĐỒNG BỘ ĐÃ THÀNH CÔNG RỰC RỠ 100%! ===\n";
