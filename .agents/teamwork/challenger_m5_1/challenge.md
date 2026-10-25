# BÁO CÁO PHẢN BIỆN ADVERSARIAL (CHALLENGE REPORT) — MILESTONE 5

**Mã thách thức**: `CHALLENGE-M5-RADAR-CLASSIFICATION`  
**Đặc vụ phản biện**: `challenger_m5_1` (Milestone 5 Diagnostic Classification Challenger)  
**Mục tiêu kiểm thử**: Động cơ Chẩn đoán Sớm Radar, Quy tắc Phân loại Vùng Rủi ro, và Độ An toàn Runtime trên Live Apache & MariaDB  
**Bộ khai thác tự động**: `D:\New folder\my-web-project\tests\challenge_m5_radar.php`  
**Đánh giá rủi ro tổng thể**: **HIGH / BLOCKING**  
**Phán quyết (Verdict)**: **REQUEST_CHANGES**  

---

## 1. Tóm Tắt Kết Quả Phản Biện (Challenge Summary)

Bộ kiểm thử thực nghiệm đối kháng độc lập `tests/challenge_m5_radar.php` đã thực thi **42 bài kiểm thử đối kháng** (stress test assertions) chia làm 9 vector tấn công chuyên sâu, bao gồm fuzzing biên điểm số, kiểm tra thác đổ độ ưu tiên (waterfall precedence), kiểm thử độ chính xác dấu phẩy động IEEE 754, chèn dữ liệu trực tiếp vào MariaDB 10.4 và kiểm toán mã nguồn HTML render thực tế qua máy chủ Apache 2.4.

Kết quả kiểm thử tự động:
- **Tổng số kịch bản kiểm thử**: 42
- **Số kịch bản VƯỢT QUA (PASS)**: 38 (90.5%)
- **Số kịch bản THẤT BẠI (FAIL)**: 4 (9.5%)

Các sai lệch nghiêm trọng được phát hiện thực nghiệm:
1. **Lỗi logic phân loại hỏng kỳ thi (Single Final Exam Failure Logic Defect)**: Học sinh có điểm thi cuối kỳ dưới chuẩn (`ck = 4.9 < 5.0`) nhưng giữa kỳ đạt chuẩn (`gk = 5.0`) và điểm trung bình `>= 5.0` (ví dụ `avg = 5.5`) bị xếp nhầm vào **🟢 Ổn định** ("Đạt chuẩn, ổn định (5.5)"), vi phạm trực tiếp yêu cầu R4 trong `ORIGINAL_REQUEST.md` ("component score < 5.0" -> Cảnh báo) và Thông tư 22/2021/TT-BGDĐT.
2. **Lỗi biến chưa định nghĩa (Undefined Variable Typo)**: Tại `index.php:86`, biểu thức điều kiện sử dụng `$isAvgWarning || $isWarning`, trong khi biến được khai báo ở dòng 71 là `$isAvgWarn`.
3. **Cảnh báo Runtime tràn ngập (PHP Warning Flooding)**: Dưới môi trường PHP tiêu chuẩn `E_ALL`, mỗi khi đánh giá một học sinh không thuộc vùng Đỏ, hàm `evaluateRiskZone()` lập tức phát ra `PHP Warning: Undefined variable $isAvgWarning in D:\New folder\my-web-project\index.php on line 86`.
4. **Rò rỉ cảnh báo lên giao diện người dùng trên Apache (DOM Contamination & Path Leak)**: Khi người dùng (Admin hoặc Giáo viên) truy cập `http://localhost/my-web-project/index.php` trên Apache thực tế, **12 thông báo lỗi PHP Warning** bị in trực tiếp vào thân trang HTML, phá vỡ cấu trúc giao diện và làm lộ đường dẫn nội bộ máy chủ (`D:\New folder\my-web-project\index.php`).

---

## 2. Danh Sách Các Lỗ Hổng & Thách Thức (Confirmed Challenges)

### [High] Thách Thức 1 (V3.4): Hỏng Điểm Thi Cuối Kỳ (`ck = 4.9`) Vẫn Được Xếp Loại "🟢 Ổn Định"
- **Giả định bị bác bỏ**: Worker cho rằng nếu học sinh không trượt cả 2 kỳ (`gk < 5.0 && ck < 5.0`), không trượt cuối kỳ dưới 3.0 (`ck < 3.0`), và điểm trung bình môn `>= 5.0` thì học sinh hoàn toàn bình thường và ổn định (`level = 'green'`).
- **Kịch bản thực nghiệm**:
  - Học sinh có điểm thường xuyên tốt (`tx1 = 8.0, tx2 = 8.0`), điểm giữa kỳ đạt chuẩn (`gk = 5.0`), nhưng thi cuối kỳ hỏng (`ck = 4.9`).
  - Điểm trung bình môn học kỳ: `avg = (8.0 + 8.0 + 5.0*2 + 4.9*3) / 7 = 40.7 / 7 = 5.8`.
  - Kết quả trả về từ `evaluateRiskZone(5.8, 5.0, 4.9)`:
    ```json
    {
      "level": "green",
      "label": "Ổn định",
      "diagnostic": "Đạt chuẩn, ổn định (5.8)"
    }
    ```
- **Tác động (Blast Radius)**:
  - Một học sinh thi hỏng bài thi học kỳ cuối kỳ quan trọng nhất (hệ số 3) lại được hệ thống chẩn đoán là "Đạt chuẩn, ổn định", khiến giáo viên bộ môn chủ quan, không đưa ra can thiệp sư phạm kịp thời.
  - Vi phạm yêu cầu R4 trong `ORIGINAL_REQUEST.md`: *"classified into 🔴 Nguy cấp (Red), 🟡 Cần chú ý (Yellow), and 🟢 Ổn định (Green) based on their VnEdu grade columns (e.g., average < 5.0, consecutive drop across regular/periodic tests, or component score < 5.0)"*.
  - Vi phạm chỉ đạo đối kháng trong DISPATCH: *"Dual exam failure: gk = 4.9 && ck = 4.9 (🔴 Nguy cấp) vs gk = 5.0 && ck = 4.9 (🟡 Cần chú ý)"*.
- **Giải pháp khắc phục kiến nghị**:
  - Bổ sung kiểm tra điểm thành phần định kỳ dưới 5.0 vào Vùng 🟡 Cần chú ý trong `evaluateRiskZone()`:
    ```php
    // Kiểm tra điểm thi định kỳ hỏng (GK hoặc CK dưới 5.0 nhưng chưa đến mức Nguy cấp)
    if (($ck !== null && $ck < 5.0) || ($gk !== null && $gk < 5.0)) {
        $isWarning = true;
        if ($ck !== null && $ck < 5.0) {
            $reasons[] = 'Thi cuối kỳ dưới trung bình (' . number_format($ck, 1) . ')';
        }
        if ($gk !== null && $gk < 5.0) {
            $reasons[] = 'Thi giữa kỳ dưới trung bình (' . number_format($gk, 1) . ')';
        }
    }
    ```

---

### [Critical] Thách Thức 2 (V6.1, V6.2, V6.3): Lỗi Biến Chưa Định Nghĩa `$isAvgWarning` Gây Rò Rỉ Lỗi PHP Ra HTML Live
- **Giả định bị bác bỏ**: Worker tuyên bố mã nguồn hoàn toàn sạch sẽ, không lỗi runtime, 42/42 tests trong `verify_m5.php` đỗ.
- **Kịch bản thực nghiệm**:
  1. Kiểm tra tĩnh dòng 71 và 86 của `index.php`:
     - Dòng 71: `$isAvgWarn = ($avg !== null && $avg >= 3.5 && $avg < 5.0);`
     - Dòng 86: `if ($isAvgWarning || $isWarning) {`
     - Biến `$isAvgWarning` hoàn toàn không tồn tại trong hàm.
  2. Kiểm tra runtime CLI:
     - Chạy `evaluateRiskZone()` dưới trình bắt lỗi PHP bắt `E_WARNING`:
       `RUNTIME WARNING DETECTED: PHP Warning: 'Undefined variable $isAvgWarning' in index.php on line 86`
  3. Kiểm tra live HTTP cURL:
     - Gửi yêu cầu HTTP GET `http://localhost/my-web-project/index.php` với cookie phiên của tài khoản `admin`:
     - Phản hồi HTTP chứa 12 đoạn HTML lỗi:
       `<br /><b>Warning</b>:  Undefined variable $isAvgWarning in <b>D:\New folder\my-web-project\index.php</b> on line <b>86</b><br />`
- **Nguyên nhân worker bỏ sót**:
  - File `tests/verify_m5.php` của worker không hề gọi `evaluateRiskZone()` từ `index.php` mà tự định nghĩa một hàm giả lập `testEvaluateRisk()` (dòng 152-197). Do đó, unit tests của worker chạy trên hàm giả lập hoàn toàn sạch, bỏ lọt lỗi thực tế trong file gốc.
  - Các bài kiểm thử cURL của worker chỉ kiểm tra `$res['status'] === 200` mà không kiểm tra tính toàn vẹn của chuỗi nội dung `$res['body']`.
- **Tác động (Blast Radius)**:
  - Khi triển khai trên XAMPP với `display_errors = 1`, giao diện người dùng bị xé rách với hàng loạt dòng Warning màu cam/đen trên đầu trang.
  - Lộ lọt cấu trúc thư mục và đường dẫn tệp mã nguồn nội bộ máy chủ (`D:\New folder\my-web-project\index.php`).
- **Giải pháp khắc phục kiến nghị**:
  - Tại dòng 86 của `index.php`, sửa `$isAvgWarning || $isWarning` thành `$isWarning` (vì biến cờ `$isWarning` đã được bật thành `true` ở dòng 73 nếu `$isAvgWarn` thỏa mãn).

---

## 3. Bảng Đối Soát Kết Quả Kiểm Thử Thực Nghiệm (Stress Test Results)

| Mã Kiểm Thử | Tình Huống Đối Kháng (Scenario) | Hành Vi Kỳ Vọng (Expected) | Hành Vi Thực Tế (Actual) | Kết Quả |
|---|---|---|---|---|
| **V1.1** | Điểm TB biên dưới: `avg = 3.49` | Xếp loại 🔴 Nguy cấp (`red`), lý do "dưới 3.5" | `level = 'red'`, lý do "Điểm TB dưới 3.5 (3.5)" | **PASS** |
| **V1.2** | Điểm TB biên trên: `avg = 3.50` | Xếp loại 🟡 Cần chú ý (`yellow`), không phải Đỏ | `level = 'yellow'`, lý do "Điểm TB dưới chuẩn (3.5)" | **PASS** |
| **V1.3** | Điểm TB chuẩn: `avg = 3.40` | Xếp loại 🔴 Nguy cấp (`red`) | `level = 'red'` | **PASS** |
| **V1.4** | Điểm TB cận chuẩn: `avg = 4.99` | Xếp loại 🟡 Cần chú ý (`yellow`) | `level = 'yellow'`, lý do "Điểm TB dưới chuẩn (5.0)" | **PASS** |
| **V1.5** | Điểm TB đạt chuẩn: `avg = 5.00` | Xếp loại 🟢 Ổn định (`green`) | `level = 'green'`, lý do "Đạt chuẩn, ổn định (5.0)" | **PASS** |
| **V1.6** | Điểm TB chuẩn: `avg = 4.90` | Xếp loại 🟡 Cần chú ý (`yellow`) | `level = 'yellow'` | **PASS** |
| **V2.1** | Ghi đè cuối kỳ: `ck = 2.9, avg = 6.5` | Xếp loại 🔴 Nguy cấp dù điểm TB cao | `level = 'red'`, lý do "Thi cuối kỳ dưới 3.0 (2.9)" | **PASS** |
| **V2.2** | Ghi đè cuối kỳ: `ck = 2.9` điểm miệng 10 | Vẫn giữ nguyên 🔴 Nguy cấp | `level = 'red'` | **PASS** |
| **V2.3** | Điểm cuối kỳ biên: `ck = 2.99` | Xếp loại 🔴 Nguy cấp | `level = 'red'` | **PASS** |
| **V2.4** | Điểm cuối kỳ biên: `ck = 3.00` | Vượt qua ngưỡng nguy cấp (`level !== 'red'`) | `level = 'green'` | **PASS** |
| **V3.1** | Trượt cả 2 kỳ: `gk = 4.9, ck = 4.9` | Xếp loại 🔴 Nguy cấp (`red`) | `level = 'red'`, lý do "Hỏng cả GK (4.9) và CK (4.9)" | **PASS** |
| **V3.2** | Trượt cả 2 kỳ cận biên: `gk=4.99, ck=4.99` | Xếp loại 🔴 Nguy cấp (`red`) | `level = 'red'` | **PASS** |
| **V3.3** | Chỉ trượt giữa kỳ: `gk = 4.9, ck = 5.0` | Không xếp vào 🔴 Nguy cấp | `level !== 'red'` | **PASS** |
| **V3.4** | **Chỉ trượt cuối kỳ: `gk = 5.0, ck = 4.9, avg = 5.5`** | **Xếp loại 🟡 Cần chú ý (`yellow`)** | **`level = 'green'`, chẩn đoán "Đạt chuẩn, ổn định"** | **FAIL** |
| **V4.1** | Độ lệch sa sút đúng bằng `1.5` (8.0 - 6.5) | Không kích hoạt sa sút -> 🟢 Ổn định | `level = 'green'` | **PASS** |
| **V4.2** | Độ lệch sa sút bằng `1.6` (8.0 - 6.4) | Kích hoạt sa sút -> 🟡 Cần chú ý | `level = 'yellow'`, lý do "Sa sút cuối kỳ (giảm 1.6 đ)" | **PASS** |
| **V4.3** | Dấu phẩy động IEEE 754 (7.3 - 5.8 = 1.4999999996) | Làm tròn đúng 1.5 -> Không báo động giả | `level = 'green'` | **PASS** |
| **V4.4** | Dấu phẩy động IEEE 754 (7.3 - 5.7 = 1.6) | Làm tròn đúng 1.6 -> Kích hoạt cảnh báo | `level = 'yellow'` | **PASS** |
| **V4.5** | Xu hướng tiến bộ: `gk = 5.0, ck = 8.0` (drop = -3.0) | Không kích hoạt cảnh báo sa sút | `level = 'green'` | **PASS** |
| **V4.6** | Độ ưu tiên: Sa sút kèm `ck < 3.0` | Vùng Đỏ phải thắng vùng Vàng | `level = 'red'` | **PASS** |
| **V5.1** | Hồ sơ trống (chưa có điểm nào) | Xếp vào 🟢 Ổn định kèm ghi chú rõ ràng | `level = 'green'`, lý do "Chưa đủ dữ liệu điểm số" | **PASS** |
| **V5.2** | Chỉ có điểm thường xuyên TX (avg = 7.5) | Xếp loại 🟢 Ổn định, không báo động giả | `level = 'green'` | **PASS** |
| **V5.3** | Chỉ có TX nhưng điểm TB kém (avg = 3.2) | Xếp loại 🔴 Nguy cấp | `level = 'red'` | **PASS** |
| **V5.4** | Có TX + GK nhưng chưa có CK | Không kích hoạt cảnh báo sa sút giả | `level = 'green'` | **PASS** |
| **V5.5** | Điểm 0.0 hợp lệ (học sinh bị điểm 0) | Xếp loại 🔴 Nguy cấp với đầy đủ lý do | `level = 'red'`, lý do TB dưới 3.5, CK dưới 3.0, hỏng cả GK và CK | **PASS** |
| **V5.6** | Điểm thường xuyên 0.0 đơn lẻ | Xếp loại 🔴 Nguy cấp | `level = 'red'` | **PASS** |
| **V6.1** | **Quét tĩnh biến `$isAvgWarning` trong index.php** | **Không chứa biến chưa khai báo** | **Tìm thấy `$isAvgWarning` tại dòng 86** | **FAIL** |
| **V6.2** | **Thực thi `evaluateRiskZone()` dưới E_ALL** | **0 lỗi PHP Warning/Notice** | **Phát sinh `PHP Warning: Undefined variable $isAvgWarning`** | **FAIL** |
| **V6.3** | **Quét nội dung HTML Live Apache index.php** | **0 lỗi PHP rò rỉ ra trình duyệt** | **Phát hiện 12 thông báo lỗi Warning trong DOM** | **FAIL** |
| **V7.1** | Truy cập chưa đăng nhập vào index.php | Chuyển hướng HTTP 302 về login.php | HTTP 302 | **PASS** |
| **V7.2** | Giáo viên `gv_toan` đăng nhập xem index.php | Chỉ xem môn Toán, 0 dòng Ngữ Văn | HTTP 200, 0 Ngữ Văn | **PASS** |
| **V7.3** | Giáo viên `gv_van` đăng nhập xem index.php | Chỉ xem môn Ngữ Văn, 0 dòng Toán | HTTP 200, 0 Toán | **PASS** |
| **V7.4** | Tài khoản `admin` đăng nhập xem index.php | Xem toàn trường cả Toán và Ngữ Văn | HTTP 200, đầy đủ môn học | **PASS** |
| **V7.5** | Giáo viên đăng nhập xem header index.php | Không hiển thị liên kết `admin.php` | 0 liên kết admin.php | **PASS** |
| **V8.1** | Bất biến 4 thẻ thống kê: Total === Red + Yellow + Green | Tổng số khớp chính xác | `13 === 1 + 0 + 12` | **PASS** |
| **V8.2** | Thuộc tính dòng bảng `data-level` và `data-name` | Đầy đủ thuộc tính cho lọc JS | Thuộc tính có mặt đầy đủ | **PASS** |
| **V8.3** | Nút hành động dẫn trực tiếp tới `nhap_diem.php` | Định dạng URL chính xác | Có mặt liên kết `nhap_diem.php?class_id=X&subject=Y` | **PASS** |
| **V8.4** | Kiểm toán 0 Zalo trong file và live HTML | Tuyệt đối không xuất hiện từ 'zalo' | 0 kết quả | **PASS** |
| **V8.5** | Kiểm toán 0 mã modal rác (`actionModal`) | Tuyệt đối không xuất hiện 'actionModal' | 0 kết quả | **PASS** |
| **V9.1** | Chèn live DB điểm 3.49 và 3.50 cho HS 6A1 | Render đúng `data-level="red"` và `"yellow"` | Render chính xác theo DB | **PASS** |
| **V9.2** | Chèn live DB điểm `ck = 2.90, avg = 6.50` | Ghi đè lên `data-level="red"` trên DOM live | Render chính xác `data-level="red"` | **PASS** |
| **V9.3** | Phục hồi trạng thái nguyên vẹn của MariaDB | Dữ liệu kiểm thử được hoàn trả 100% | Scores = 8 dòng, Audit = nguyên vẹn | **PASS** |

---

## 4. Khu Vực Chưa Được Thách Thức (Unchallenged Areas)

- Không có khu vực nào trong phạm vi Milestone 5 bị bỏ sót. Tất cả 5 khía cạnh cốt lõi được yêu cầu trong chỉ đạo, cùng với tính toàn vẹn của mã nguồn PHP và tính bảo mật giao diện người dùng trên Apache đã được thách thức và đo lường thực nghiệm toàn diện.

---

## 5. Kết Luận & Khuyến Nghị (Verdict & Recommendations)

**Phán quyết**: **REQUEST_CHANGES**

Hai lỗi kỹ thuật đã được chứng minh thực nghiệm hoàn toàn không thể chối cãi:
1. Sửa lỗi chính tả dòng 86 `index.php`: thay `$isAvgWarning || $isWarning` bằng `$isWarning`.
2. Sửa lỗi logic chẩn đoán học sinh trượt điểm thi thành phần: tại vùng 🟡 Cần chú ý trong `evaluateRiskZone()`, bổ sung điều kiện cảnh báo khi học sinh có điểm thi cuối kỳ hoặc giữa kỳ dưới 5.0 (`($ck !== null && $ck < 5.0) || ($gk !== null && $gk < 5.0)`).
3. Cập nhật `tests/verify_m5.php` của worker để kiểm thử trực tiếp hàm `evaluateRiskZone()` từ `index.php` thay vì dùng hàm giả lập `testEvaluateRisk()`.
