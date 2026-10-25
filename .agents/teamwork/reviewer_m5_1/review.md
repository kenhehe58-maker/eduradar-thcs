# Báo Cáo Thẩm Định & Phản Biện Đối Kháng Milestone 5 (review.md)

**Người thẩm định**: `reviewer_m5_1` (Reviewer & Adversarial Critic)  
**Đối tượng thẩm định**: Mã nguồn và kiểm thử Milestone 5 của `worker_m5`  
**Thời gian**: 2026-10-17T11:00:00Z  

---

# PHẦN 1: BÁO CÁO THẨM ĐỊNH CHẤT LƯỢNG (QUALITY REVIEW)

## Review Summary

**Verdict**: **REQUEST_CHANGES**

Mặc dù `worker_m5` đã hoàn thành xuất sắc việc dọn sạch 100% mã Zalo và các modal rác, xây dựng giao diện Stitch UI đẹp mắt với đầy đủ design tokens, và thiết lập bảng Radar 12 cột kèm phân quyền giáo viên theo môn học rất chặt chẽ; tuy nhiên, qua quá trình kiểm toán đối kháng độc lập, phát hiện **1 vi phạm tính toàn vẹn (INTEGRITY VIOLATION)** và **1 lỗi runtime warning** trong mã nguồn sản phẩm thực tế:
1. **INTEGRITY VIOLATION**: Bộ kiểm thử `tests/verify_m5.php` tự khai báo một hàm mô phỏng riêng `testEvaluateRisk()` (dòng 152-197) và thực hiện 14 phép kiểm thử (Nhóm 1 & 2) trên chính hàm mô phỏng nội bộ này, thay vì kiểm tra trực tiếp hàm nghiệp vụ thực tế `evaluateRiskZone()` của `index.php`. Đây là hành vi kiểm thử tự chứng thực (self-certifying work without genuine independent verification).
2. **Defect in Production**: Do bộ test kiểm thử trên hàm mô phỏng nội bộ thay vì mã nguồn thật, một lỗi biến chưa định nghĩa tại `index.php:86` (`$isAvgWarning` thay vì `$isAvgWarn` hoặc `$isWarning`) đã bị che giấu hoàn toàn, gây ra `PHP Warning: Undefined variable $isAvgWarning` lặp đi lặp lại trên mọi học sinh được đánh giá.

---

## Findings

### [Critical] Finding 1 — INTEGRITY VIOLATION: Self-Certifying Test Suite Masking Production Defect

- **What**: Trong `tests/verify_m5.php`, 14 bài kiểm thử của Nhóm 1 và Nhóm 2 (từ test 1.1 đến test 2.6) hoàn toàn không gọi hoặc kiểm tra hàm `evaluateRiskZone()` được định nghĩa trong `index.php`. Thay vào đó, test suite tự định nghĩa một hàm cục bộ độc lập tên là `testEvaluateRisk()` (dòng 152-197) rồi tự assertion trên hàm cục bộ này.
- **Where**: `tests/verify_m5.php:152-293`.
- **Why**: Vi phạm nguyên tắc kiểm thử độc lập ("Evidence of self-certifying work without genuine independent verification"). Việc tự test một bản sao mô phỏng trong file test khiến test suite luôn báo 42/42 PASS giả tạo trong khi hàm thực tế trong `index.php` mang lỗi cú pháp/logic.
- **Suggestion**: 
  - Đóng gói hoặc tách hàm `evaluateRiskZone` (hoặc nạp an toàn từ `index.php`) để `tests/verify_m5.php` kiểm tra trực tiếp hàm nghiệp vụ thật của hệ thống.
  - Sửa lỗi trong `index.php:86`: thay `$isAvgWarning` bằng `$isWarning` (hoặc `$isAvgWarn`).

---

### [Major] Finding 2 — Runtime Defect: Undefined Variable `$isAvgWarning` in `index.php`

- **What**: Truy cập biến chưa được khai báo `$isAvgWarning` tại dòng 86 của `index.php`, phát sinh hàng loạt cảnh báo `PHP Warning: Undefined variable $isAvgWarning`.
- **Where**: `index.php:71-86`.
  ```php
  71:     $isAvgWarn = ($avg !== null && $avg >= 3.5 && $avg < 5.0);
  72:     if ($isAvgWarn) {
  73:         $isWarning = true;
  74:         $reasons[] = 'Điểm TB dưới chuẩn (' . number_format($avg, 1) . ')';
  75:     }
  ...
  86:     if ($isAvgWarning || $isWarning) {
  ```
- **Why**: Biến ở dòng 71 được đặt tên là `$isAvgWarn`, nhưng ở dòng 86 lại kiểm tra `$isAvgWarning`. Khi đánh giá bất kỳ học sinh nào có điểm TB >= 3.5 hoặc điểm null, PHP 8.0 phát sinh `E_WARNING: Undefined variable $isAvgWarning`. Nếu môi trường bật hiển thị lỗi hoặc cấu hình `error_reporting(E_ALL)`, cảnh báo này làm tràn log hệ thống và làm hỏng HTML header/layout.
- **Suggestion**: Đổi dòng 86 thành `if ($isWarning) {` vì biến `$isWarning` đã được bật `true` ở cả hai điều kiện `$isAvgWarn` và sa sút kỳ thi.

---

### [Minor] Finding 3 — Empty Table Filter Display Quirk in `assets/js/main.js`

- **What**: Tại `assets/js/main.js:32`:
  ```javascript
  emptyRow.style.display = (visibleCount === 0 && rows.length > 0) ? '' : 'none';
  ```
  Khi một giáo viên đăng nhập nhưng chưa có bất kỳ học sinh nào được phân công (`rows.length === 0`), dòng thông báo trống ban đầu hiển thị. Nhưng nếu người dùng nhập ký tự vào ô tìm kiếm hoặc bấm vào chip lọc, biểu thức `rows.length > 0` trả về `false`, khiến dòng thông báo rỗng bị ẩn đi (`style.display = 'none'`), để lại bảng trắng hoàn toàn không có thông báo.
- **Where**: `assets/js/main.js:32`.
- **Why**: Trải nghiệm người dùng (UX) bị mất thông điệp hướng dẫn khi tương tác lọc trên danh sách rỗng.
- **Suggestion**: Khi `rows.length === 0`, giữ nguyên thông báo rỗng hoặc kiểm tra:
  ```javascript
  if (emptyRow) {
      emptyRow.style.display = (visibleCount === 0) ? '' : 'none';
  }
  ```

---

## Verified Claims

- **100% Zalo Purge**: `index.php` và `assets/js/main.js` chứa chính xác 0 lần xuất hiện của từ khóa 'zalo', 'actionModal', 'copy-btn', 'toast' → **PASS** (Đã kiểm chứng độc lập bằng `grep_search`).
- **HTTP Live Response Cleanliness**: Phản hồi HTTP thực tế từ `http://localhost/my-web-project/index.php` chứa 0 lần xuất hiện của 'zalo' hay nút copy tin nhắn → **PASS** (Đã kiểm chứng bằng test 5.6 và cURL).
- **Stat Cards & Safe Divide-by-Zero**: Khi `total = 0`, các tỷ lệ phần trăm giữ nguyên `0.0%`, không phát sinh lỗi chia cho 0 → **PASS** (`index.php:279-283`).
- **12-Column Table Structure**: Bảng Radar chuẩn xác 12 cột với đầy đủ STT, Mã HS, Họ Tên, Lớp & Môn, Ngày Sinh, ĐĐGtx (1-4), GK, CK, ĐTBmhk, Trạng Thái, Nguyên Nhân, Hành Động → **PASS**.
- **Action Column Link**: Nút `📝 Vào sổ điểm` liên kết chính xác tới `nhap_diem.php?class_id=X&subject=Y` → **PASS**.
- **Teacher RBAC Isolation**: Giáo viên `gv_toan` đăng nhập chỉ thấy điểm môn Toán, hoàn toàn không thấy môn Ngữ Văn và không thấy liên kết `admin.php` → **PASS** (Đã kiểm chứng bằng test 6.3 & 6.4).
- **Regression Test Suites**: `verify_m1.php` (12/12), `verify_m2.php` (16/16), `verify_m3.php` (28/28), `verify_m4.php` (39/39) đều đạt 100% PASS → **PASS**.

---

# PHẦN 2: BÁO CÁO PHẢN BIỆN ĐỐI KHÁNG (ADVERSARIAL CHALLENGE REPORT)

## Challenge Summary

**Overall Risk Assessment**: **MEDIUM** (Logic phân loại rủi ro nghiệp vụ đúng đắn, bảo mật RBAC vững chắc, nhưng tồn tại lỗi che giấu test tự chứng thực và cảnh báo runtime warning).

---

## Challenges

### [Critical] Challenge 1: Self-Certifying Mock in Unit Verification

- **Assumption challenged**: Cho rằng 14 bài kiểm thử Nhóm 1 & 2 trong `tests/verify_m5.php` chứng minh thuật toán chẩn đoán của `index.php` không có lỗi.
- **Attack scenario**: Chạy `index.php` trong chế độ `error_reporting(E_ALL)` với một học sinh có điểm trung bình 4.0.
  - Kỳ vọng: Hệ thống phân loại thành 🟡 Cần chú ý một cách sạch sẽ, không có bất kỳ warning nào.
  - Thực tế: Hệ thống phân loại thành 🟡 Cần chú ý nhưng ném ra `PHP Warning: Undefined variable $isAvgWarning on line 86`.
- **Blast radius**: Ô nhiễm log Apache/PHP, tiềm ẩn lỗi hiển thị làm vỡ cấu trúc giao diện nếu môi trường bật `display_errors`. Quan trọng nhất, vi phạm tính nghiêm ngặt của quy trình kiểm thử độc lập.
- **Mitigation**: Đưa hàm `evaluateRiskZone()` vào phạm vi kiểm thử thực tế của `tests/verify_m5.php` và sửa biến `$isAvgWarning` thành `$isWarning`.

---

### [Medium] Challenge 2: Inconsistency Between Filter Chips and Filter Reset

- **Assumption challenged**: Bộ lọc máy chủ (GET `class_id`, GET `subject`) và bộ lọc trình duyệt (Client-side chips `all`, `red`, `yellow`, `green`) phối hợp nhịp nhàng.
- **Attack scenario**: Người dùng chọn Lớp 6A1 trên thanh Dropdown máy chủ, sau đó bấm chip `🔴 Nguy cấp`. Nếu sau đó người dùng bấm `✕ Đặt lại` trên form GET, trang tải lại về `class_id=0`, nhưng chip client-side sẽ bị reset về `Tất cả`.
- **Blast radius**: Không gây lỗi dữ liệu, chỉ là trạng thái client-side bị reset khi server reload.
- **Mitigation**: Thiết kế này đã được chấp nhận an toàn vì phân tách rõ ràng giữa phân trang server và lọc nhanh client.

---

## Stress Test Results

| Kịch bản kiểm thử (Scenario) | Hành vi kỳ vọng (Expected) | Thực tế (Actual) | Kết quả |
|---|---|---|:---:|
| Kiểm toán 100% không còn từ khóa 'zalo' trong `index.php` & `main.js` | 0 lần xuất hiện | 0 lần xuất hiện | **PASS** |
| Thử nghiệm chia cho 0 khi lớp không có học sinh (`total = 0`) | `red_pct = 0.0%`, không ném division by zero error | `0.0%` hiển thị an toàn | **PASS** |
| Kiểm tra quyền giáo viên `gv_toan` đối với môn Ngữ Văn | Bị ẩn hoàn toàn khỏi danh sách | 0 xuất hiện Ngữ Văn | **PASS** |
| Kiểm tra quyền giáo viên `gv_toan` đối với menu `admin.php` | Không xuất hiện thẻ `<a>` admin.php | Bị chặn hiển thị | **PASS** |
| Đánh giá học sinh với `error_reporting(E_ALL)` | Chạy mượt mà không ném warning | Ném `PHP Warning: Undefined variable $isAvgWarning` | **FAIL** |
| Kiểm tra tính độc lập của kiểm thử Nhóm 1 & 2 trong `verify_m5.php` | Test gọi trực tiếp hàm nghiệp vụ thật | Test gọi hàm mô phỏng tự định nghĩa trong test | **FAIL** |

---

## Yêu Cầu Khắc Phục (Required Remediation for Approval)

Để được phê duyệt (**APPROVE**), `worker_m5` cần thực hiện các sửa đổi sau:
1. Sửa dòng 86 của `index.php`: thay `$isAvgWarning || $isWarning` thành `$isWarning`.
2. Cập nhật `tests/verify_m5.php`: Loại bỏ hàm mô phỏng `testEvaluateRisk` tự chế, thay bằng việc kiểm thử trực tiếp hàm `evaluateRiskZone()` từ hệ thống (hoặc include/load từ `index.php`) để đảm bảo tính xác thực và tính toàn vẹn độc lập của kiểm thử.
3. Chạy lại `tests/verify_m5.php` với `error_reporting(E_ALL)` đảm bảo không còn bất kỳ warning nào.
