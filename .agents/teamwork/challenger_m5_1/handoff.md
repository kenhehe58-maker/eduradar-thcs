# Báo Cáo Bàn Giao Phản Biện Đối Kháng (Handoff Report) — challenger_m5_1

**Mã bàn giao**: `HANDOFF-M5-CHALLENGER-RADAR-CLASSIFICATION`  
**Vai trò**: `challenger_m5_1` (Milestone 5 Diagnostic Classification Challenger)  
**Người nhận**: `orchestrator_1_gen3` (ID: `391812a0-32ec-4263-b097-6162669cc4f7`)  
**Loại bàn giao**: **Hard Handoff** (Nhiệm vụ phản biện hoàn thành 100%, phát hiện 4 sai lệch có bằng chứng thực nghiệm trực tiếp)  
**Phán quyết (Verdict)**: **REQUEST_CHANGES**  
**Thời gian**: 2026-10-17T11:07:00Z  

---

## 1. Observation (Các Quan Sát Thực Nghiệm Trực Tiếp)

1. **Quan sát mã nguồn `index.php:71-86`**:
   - Dòng 71:
     ```php
     $isAvgWarn = ($avg !== null && $avg >= 3.5 && $avg < 5.0);
     if ($isAvgWarn) {
         $isWarning = true;
         $reasons[] = 'Điểm TB dưới chuẩn (' . number_format($avg, 1) . ')';
     }
     ```
   - Dòng 86:
     ```php
     if ($isAvgWarning || $isWarning) {
     ```
   - Biến `$isAvgWarning` không được khởi tạo hay gán giá trị ở bất kỳ đâu trong hàm `evaluateRiskZone()` hoặc toàn bộ tệp `index.php`.

2. **Quan sát thực thi kiểm thử runtime PHP**:
   - Chạy kiểm thử dưới cờ `E_ALL` với hàm `evaluateRiskZone(4.2, 5.0, 5.0)`:
     ```
     PHP Warning:  Undefined variable $isAvgWarning in D:\New folder\my-web-project\index.php on line 86
     ```
   - Cảnh báo xuất hiện lặp lại với tất cả các học sinh không rơi vào vùng Đỏ.

3. **Quan sát HTTP response body trên máy chủ Live Apache (`http://localhost/my-web-project/index.php`)**:
   - Thực hiện yêu cầu HTTP GET tới `index.php` sau khi xác thực quyền `admin`:
     ```
     HTTP Status: 200
     Warning leaked into HTTP output: YES
     Matched: <br /><b>Warning</b>:  Undefined variable $isAvgWarning in <b>D:\New folder\my-web-project\index.php</b> on line <b>86</b><br />
     ```
   - Tổng cộng **12 cảnh báo PHP Warning** được in trực tiếp vào DOM của trang web trước thẻ DOCTYPE / bên trong thân trang, làm biến dạng cấu trúc HTML và phơi bày đường dẫn vật lý trên server.

4. **Quan sát logic phân loại điểm thi định kỳ hỏng (V3.4)**:
   - Khi gọi `evaluateRiskZone(5.5, 5.0, 4.9)` (học sinh thi trượt cuối kỳ `ck = 4.9`, giữa kỳ đạt `gk = 5.0`, điểm TB học kỳ `avg = 5.5`):
     - Hàm trả về: `['level' => 'green', 'label' => 'Ổn định', 'diagnostic' => 'Đạt chuẩn, ổn định (5.5)']`.
     - Học sinh hỏng bài thi học kỳ cuối kỳ (hệ số 3) bị gắn mác "Đạt chuẩn, ổn định".

5. **Quan sát tệp kiểm thử của worker `tests/verify_m5.php:152-197`**:
   - Tệp `verify_m5.php` tự khai báo hàm `testEvaluateRisk()` riêng mà không hề `require_once 'index.php'` hay gọi hàm `evaluateRiskZone()` thực tế. Do đó bài test của worker đạt 42/42 PASS giả tạo vì không chạy trên mã nguồn thực tế.

6. **Quan sát kết quả bộ kiểm thử đối kháng `tests/challenge_m5_radar.php`**:
   - Lệnh thực thi:
     ```powershell
     D:\xam\php\php.exe tests/challenge_m5_radar.php
     ```
   - Kết quả xuất ra nguyên văn:
     ```
     =============================================================================
       CHALLENGE SUITE EXECUTION SUMMARY
     =============================================================================
       Total Challenges: 42
       Passed:           38
       Failed:           4

       CONFIRMED DEFECTS & FINDINGS (4):
       1. [V3.4] Single exam failure: gk = 5.0 && ck = 4.9 must trigger 🟡 Yellow (Cần chú ý), NOT 🟢 Green
          -> LOGIC DEFECT: Student failing final exam (ck=4.9) classified as 🟢 Green ('Đạt chuẩn, ổn định (5.5)')! Expected 🟡 Yellow (Cần chú ý).
       2. [V6.1] Empirical check: inspect index.php line 86 for undefined variable $isAvgWarning
          -> BUG CONFIRMED: index.php references undefined variable '$isAvgWarning' on line 86! Line 71 declares '$isAvgWarn'.
       3. [V6.2] Runtime test: evaluateRiskZone() under E_ALL must not emit PHP Warnings
          -> RUNTIME WARNING DETECTED: PHP Warning: 'Undefined variable $isAvgWarning' in index.php on line 86
       4. [V6.3] Live Apache check: index.php HTTP response must NOT leak PHP Warnings into HTML
          -> LEAK DETECTED: Live Apache HTTP response contains PHP Warning for '$isAvgWarning' visible in browser DOM!

       VERDICT: REQUEST_CHANGES
     =============================================================================
     ```

---

## 2. Logic Chain (Chuỗi Lập Luận Suy Diễn Kỹ Thuật)

1. *Từ Quan sát 1 & 2 (Biến chưa định nghĩa `$isAvgWarning`)*:
   - Ở dòng 71, worker khai báo biến `$isAvgWarn`.
   - Nhưng ở dòng 86, worker lại viết `if ($isAvgWarning || $isWarning)`.
   - Trong PHP 8+, việc truy xuất biến chưa định nghĩa sẽ kích hoạt lỗi mức độ `E_WARNING` (trước PHP 8 là `E_NOTICE`).
   - Mặc dù giá trị trả về của biến chưa định nghĩa được ép kiểu thành `null` (falsy) và luồng xử lý vẫn tiếp tục dựa trên `$isWarning`, nhưng sự kiện `E_WARNING` luôn được PHP runtime tạo ra.

2. *Từ Quan sát 3 (Rò rỉ cảnh báo ra DOM trên Apache)*:
   - Môi trường phát triển XAMPP mặc định bật `display_errors = 1` và cấu hình báo cáo lỗi `error_reporting = 22527` (bao gồm `E_WARNING`).
   - Do đó, khi `index.php` duyệt qua danh sách học sinh và đánh giá rủi ro, mỗi học sinh được đánh giá ở dòng 86 sẽ làm PHP in một thẻ `<br /><b>Warning</b>...` vào bộ đệm đầu ra HTTP.
   - Kết quả: Mã HTML gửi về trình duyệt của người dùng bị nhiễm bẩn 12 cảnh báo lỗi, phá vỡ định dạng giao diện Stitch UI và để lộ thông tin đường dẫn hệ thống tệp.

3. *Từ Quan sát 4 (Lỗi nghiệp vụ phân loại rủi ro học kỳ)*:
   - Theo Thông tư 22/2021/TT-BGDĐT và yêu cầu R4 trong `ORIGINAL_REQUEST.md`: Học sinh có điểm thành phần dưới 5.0 ("component score < 5.0") phải thuộc diện cảnh báo học tập.
   - Điểm kiểm tra cuối kỳ (`ck`) có hệ số 3, là bài kiểm tra quyết định của học kỳ. Điểm `ck = 4.9` là mức dưới trung bình (hỏng thi cuối kỳ).
   - Chỉ đạo đối kháng nêu rõ: `gk = 4.9 && ck = 4.9 (🔴 Nguy cấp) vs gk = 5.0 && ck = 4.9 (🟡 Cần chú ý)`.
   - Tuy nhiên, trong `index.php:68-94`, worker chỉ định nghĩa vùng Vàng gồm hai điều kiện: `$avg >= 3.5 && $avg < 5.0` hoặc `round($gk - $ck, 1) > 1.5`. Worker hoàn toàn không kiểm tra điều kiện `ck < 5.0` hoặc `gk < 5.0`.
   - Kết quả: Khi học sinh có `ck = 4.9` nhưng điểm thường xuyên cao kéo điểm TB lên `5.5`, học sinh này bị hệ thống gắn nhãn **🟢 Ổn định** ("Đạt chuẩn, ổn định (5.5)"), tạo ra sự che giấu nguy cơ học tập nghiêm trọng.

4. *Từ Quan sát 5 & 6 (Hạn chế của bộ test worker)*:
   - Worker đã không kiểm thử trực tiếp hàm `evaluateRiskZone()` trong `index.php`, mà dùng hàm giả lập `testEvaluateRisk()` trong `verify_m5.php`. Điều này giải thích tại sao `verify_m5.php` báo 42/42 PASS nhưng hệ thống live lại chứa lỗi.
   - Bộ harness đối kháng `tests/challenge_m5_radar.php` đã bóc trần toàn bộ các điểm yếu này thông qua kiểm thử thực tế trên MariaDB và Apache.

---

## 3. Caveats (Các Giới Hạn & Giả Định)

1. **Các khu vực khác của Milestone 5 hoạt động tốt**:
   - Ranh giới điểm TB 3.49 (Đỏ) vs 3.50 (Vàng), 4.99 (Vàng) vs 5.00 (Xanh) hoạt động chính xác.
   - Ranh giới điểm cuối kỳ `ck = 2.9` (Đỏ) vs `ck = 3.0` (vượt qua ngưỡng nguy cấp) hoạt động chính xác.
   - Ngưỡng phát hiện sa sút cuối kỳ `ck < gk - 1.5`: độ lệch 1.5 không kích hoạt, độ lệch 1.6 kích hoạt chính xác, xử lý hoàn hảo sai số dấu phẩy động IEEE 754.
   - Xử lý hồ sơ trống (toàn bộ null) xếp an toàn vào Xanh kèm ghi chú "Chưa đủ dữ liệu điểm số".
   - Xử lý điểm 0.0 hợp lệ xếp chính xác vào Đỏ với đầy đủ lý do.
   - Đã loại bỏ 100% Zalo và mã rác modal trong cả backend và frontend.
   - Phân quyền giáo viên chỉ xem môn được phân công hoạt động hoàn hảo.
   - Giao diện CSS và design tokens tuân thủ thiết kế Stitch UI.

---

## 4. Conclusion (Kết Luận)

Milestone 5 **CHƯA ĐẠT CHUẨN ĐỂ DUYỆT (NOT READY FOR APPROVAL)** do tồn tại 2 khiếm khuyết kỹ thuật cụ thể:
1. **Lỗi cú pháp / runtime PHP**: Biến không tồn tại `$isAvgWarning` tại `index.php:86` làm rò rỉ 12 thông báo lỗi ra giao diện web trên Apache.
2. **Lỗi logic phân loại học tập**: Bỏ sót điều kiện điểm thi định kỳ hỏng (`ck < 5.0` hoặc `gk < 5.0`), khiến học sinh thi trượt cuối kỳ (`ck = 4.9`) bị xếp nhầm vào Vùng Ổn định (Xanh).

**Phán quyết**: **REQUEST_CHANGES**  
Yêu cầu `worker_m5` sửa đổi 2 điểm trên trong `index.php` và cập nhật `tests/verify_m5.php` kiểm thử trực tiếp hàm thực tế.

---

## 5. Verification Method (Phương Pháp Kiểm Minh Độc Lập)

Bất kỳ thành viên nào cũng có thể kiểm minh toàn bộ các quan sát và lập luận trên bằng lệnh:

```powershell
D:\xam\php\php.exe "D:\New folder\my-web-project\tests\challenge_m5_radar.php"
```

*Kỳ vọng thực tế hiện tại*:
- `Total Challenges: 42 | Passed: 38 | Failed: 4`
- `VERDICT: REQUEST_CHANGES`, exit code 1.

*Điều kiện để lật ngược phán quyết (Reversal Condition)*:
Khi worker sửa xong `index.php`:
1. Sửa `$isAvgWarning` thành `$isWarning` tại dòng 86.
2. Thêm kiểm tra `($ck !== null && $ck < 5.0) || ($gk !== null && $gk < 5.0)` vào Vùng Vàng.
Chạy lại lệnh trên đạt `Total Challenges: 42 | Passed: 42 | Failed: 0` và exit code 0.
