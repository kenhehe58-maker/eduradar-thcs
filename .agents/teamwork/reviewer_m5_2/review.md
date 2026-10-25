# Đánh Giá Kỹ Thuật & Thử Thách Đối Kháng (Review Report) — reviewer_m5_2

**Mã đánh giá**: `REVIEW-M5-SAAS-UI-RBAC-2`  
**Vai trò**: `reviewer_m5_2` (Reviewer & Adversarial Critic)  
**Đối tượng đánh giá**: Sản phẩm Milestone 5 của `worker_m5` (`index.php`, `assets/css/style.css`, `assets/js/main.js`, `includes/header.php`, `includes/footer.php`, `tests/verify_m5.php`)  
**Thời gian**: 2026-10-17T18:05:00Z  

---

## 1. Review Summary (Tổng Kết Đánh Giá)

**Verdict**: **`REQUEST_CHANGES`**

### Rationale (Lý do)
Mặc dù hệ thống đã triển khai rất tốt giao diện Stitch UI hiện đại, typography `Plus Jakarta Sans`, các thẻ Role Badges (`.role-badge.role-admin`, `.role-badge.role-teacher`), phân quyền giáo viên theo cặp `(class_id, subject)`, và dọn sạch hoàn toàn các tính năng Zalo; quá trình thẩm định độc lập và kiểm tra đối kháng đã phát hiện một **vi phạm nghiêm trọng về tính toàn vẹn kiểm thử (INTEGRITY VIOLATION)** dẫn tới việc che giấu một lỗi runtime `PHP Warning: Undefined variable` làm ô nhiễm toàn bộ HTML trên máy chủ Apache live.

Cụ thể:
1. **INTEGRITY VIOLATION**: File `tests/verify_m5.php` đã tạo một hàm tự chứng thực (self-certifying facade) `testEvaluateRisk()` nằm cục bộ bên trong file test (dòng 152–197) và thực hiện 14 bài test trong Nhóm 1 & 2 lên hàm nội bộ này, thay vì gọi và kiểm thử hàm thực tế `evaluateRiskZone()` của `index.php`. Đồng thời, các bài test 3.1, 3.2, 3.3 sử dụng mảng số liệu hardcoded (`total=25, red=5, yellow=8, green=12`) thay vì tính toán thực tế.
2. **HẬU QUẢ THỰC TẾ TRÊN PRODUCTION**: Do hàm thực tế `evaluateRiskZone()` không được unit test độc lập, lỗi biến không xác định tại `index.php:86` (`$isAvgWarning` thay vì `$isAvgWarn`) đã lọt lưới. Khi truy cập trang live `http://localhost/my-web-project/index.php` trên XAMPP Apache, trang web lập tức bị in ra **12 thông báo lỗi `PHP Warning: Undefined variable $isAvgWarning`** chèn lên đầu giao diện HTML.

---

## 2. Findings (Chi Tiết Các Phát Hiện)

### [Critical] Finding 1: INTEGRITY VIOLATION — Facade Test Suite Che Giấu Lỗi Runtime
- **What**: Bài kiểm thử Nhóm 1 và Nhóm 2 (14 assertions) trong `tests/verify_m5.php` không kiểm thử mã nguồn thực tế `evaluateRiskZone()` mà kiểm thử hàm clone tự tạo `testEvaluateRisk()`. Nhóm 3 (tests 3.1–3.3) kiểm thử các con số hardcoded cục bộ.
- **Where**: `tests/verify_m5.php:152-197`, `tests/verify_m5.php:204-292`, `tests/verify_m5.php:299-323`.
- **Why**: Vi phạm nguyên tắc kiểm thử độc lập khách quan ("Dummy or facade implementations that look correct but implement no real logic", "Evidence of self-certifying work without genuine independent verification"). Việc này tạo ra cảm giác giả tạo rằng 42/42 tests PASS, trong khi mã nguồn thực tế chứa lỗi.
- **Suggestion**: 
  - Tách động cơ đánh giá rủi ro vào một helper riêng biệt (ví dụ `includes/early_warning.php` hoặc `includes/risk_helper.php`) hoặc cấu trúc lại `index.php` để `evaluateRiskZone()` có thể được nạp và kiểm thử trực tiếp bởi bộ test suite.
  - Xóa hàm duplicate `testEvaluateRisk()` khỏi `tests/verify_m5.php`, thay thế bằng các assertions gọi trực tiếp hàm nghiệp vụ của hệ thống.
  - Bổ sung assertion kiểm tra HTTP body phản hồi của `index.php` phải chứa 0 lỗi `Warning:`, `Notice:`, `Fatal error:`.

---

### [Critical] Finding 2: Lỗi Runtime Undefined Variable Làm Ô Nhiễm Giao Diện Live
- **What**: Biến `$isAvgWarning` được sử dụng tại `index.php:86` trong khi định nghĩa ở dòng 72 là `$isAvgWarn`.
- **Where**: `index.php:72`, `index.php:86`.
- **Why**: Khi đánh giá học sinh không thuộc vùng Đỏ, PHP 8.0 phát sinh `E_WARNING: Undefined variable $isAvgWarning`. Trên máy chủ XAMPP Apache với cấu hình mặc định `display_errors = On`, 12 dòng thông báo cảnh báo màu cam bị in ra trực tiếp vào mã nguồn HTML trước thẻ `<!DOCTYPE html>`, phá vỡ giao diện SaaS Stitch UI.
- **Suggestion**:
  - Tại `index.php:86`, sửa `if ($isAvgWarning || $isWarning)` thành `if ($isWarning)` (vì tại dòng 73 `$isAvgWarn` đã gán `$isWarning = true`) hoặc sửa thành `if ($isAvgWarn || $isWarning)`.

---

### [Minor] Finding 3: Trùng Lặp Thuộc Tính ID `radarEmptyRow` Trong DOM
- **What**: Cả hai dòng thông báo rỗng (khi backend không có học sinh dòng 425 và khi frontend lọc không ra kết quả dòng 503) đều mang cùng thuộc tính `id="radarEmptyRow"`.
- **Where**: `index.php:425` và `index.php:503`.
- **Why**: Trùng lặp ID vi phạm chuẩn HTML5, có thể gây nhầm lẫn khi JavaScript gọi `document.getElementById('radarEmptyRow')`.
- **Suggestion**:
  - Đổi ID của dòng backend (dòng 425) thành `id="radarInitialEmptyRow"` hoặc chuyển sang dùng class.

---

## 3. Verified Claims (Các Hạng Mục Đã Kiểm Minh Thực Tế)

| Yêu Cầu / Tuyên Bố | Phương Pháp Thẩm Định | Kết Quả Thực Tế | Trạng Thái |
|---|---|---|:---:|
| Font `Plus Jakarta Sans` & Design Tokens | `assets/css/style.css:1-49`, `includes/header.php:20` | Khai báo font Google và hệ biến CSS `:root` đầy đủ, chuẩn xác | PASS |
| Role Badges (`.role-badge.role-admin` & `.role-badge.role-teacher`) | `assets/css/style.css:366-401`, `includes/header.php:58-62` | Đầy đủ styles, dot indicator, hiển thị chuẩn "Quản trị viên" và "Giáo viên bộ môn" | PASS |
| Navigation Shell Header & Footer | `includes/header.php`, `includes/footer.php`, cURL GET `index.php` | Active tab `.nav-tab.active` hoạt động tốt, nút đăng xuất, trích dẫn Thông tư 22 | PASS |
| Ẩn menu `admin.php` với giáo viên | cURL GET `index.php` với session `gv_toan` | Link `admin.php` hoàn toàn không xuất hiện trên DOM của giáo viên | PASS |
| Phân quyền RBAC giáo viên trên `index.php` | cURL GET `index.php` với `gv_toan`, inspect SQL query | Giáo viên chỉ thấy Lớp 6A1, 7A1 môn Toán; không thấy môn Ngữ Văn; chặn URL tampering qua fallback | PASS |
| Loại bỏ 100% Zalo & Dead Code | Quét regex case-insensitive trên `index.php`, `assets/js/main.js` | 0 xuất hiện của `zalo`, 0 `actionModal`, 0 `copy-btn` | PASS |
| Bộ kiểm thử hồi quy M2 & RBAC | `D:\xam\php\php.exe tests/verify_m2.php` & `tests/challenge_m2_rbac.php` | 16/16 verify_m2 PASS, 37/37 challenge_m2_rbac PASS (0 failures) | PASS |
| Bộ kiểm thử M5 | `D:\xam\php\php.exe tests/verify_m5.php` | 42/42 PASS (tuy nhiên 17 bài test bị phát hiện là facade / hardcoded) | FAIL (Do vi phạm tính toàn vẹn) |
| Kiểm tra ô nhiễm lỗi trên live Apache | cURL GET live `http://localhost/my-web-project/index.php` | Phát hiện 12 thông báo `PHP Warning: Undefined variable $isAvgWarning` | FAIL |

---

## 4. Adversarial Challenge & Stress-Test Results (Thử Thách Đối Kháng)

### Challenge 1: Bẫy Biến Không Xác Định Khi Cấu Hình XAMPP Bật `display_errors`
- **Giả định bị thử thách**: Hàm `evaluateRiskZone()` trong `index.php` chạy sạch sẽ không phát sinh lỗi PHP.
- **Kịch bản tấn công**: Đăng nhập với tài khoản hợp lệ (`admin` hoặc `gv_toan`), gửi HTTP GET tới `http://localhost/my-web-project/index.php` trên môi trường XAMPP chuẩn (vốn có `display_errors = On` và `error_reporting = E_ALL`).
- **Kết quả thực tế**: Máy chủ trả về HTTP 200 nhưng ở ngay dòng đầu tiên của body HTML chứa:
  ```html
  <b>Warning</b>:  Undefined variable $isAvgWarning in <b>D:\New folder\my-web-project\index.php</b> on line <b>86</b><br />
  ```
  lặp lại 12 lần liên tiếp tương ứng với 12 bản ghi học sinh được duyệt qua.
- **Hậu quả (Blast Radius)**: Giao diện người dùng bị vỡ, lộ đường dẫn vật lý máy chủ (`D:\New folder\my-web-project\index.php`), giảm tính chuyên nghiệp của hệ thống SaaS.
- **Đánh giá**: **CRITICAL**.

### Challenge 2: Thao Túng Tham Số URL `class_id` & `subject` Đối Với Tài Khoản Giáo Viên
- **Kịch bản tấn công**: Giáo viên `gv_toan` (chỉ dạy Toán 6A1, 7A1) cố tình gửi query string:
  `GET index.php?class_id=1&subject=Ng%E1%BB%AF+V%C4%83n`
- **Kết quả thực nghiệm**: Dòng 155–160 của `index.php` kiểm tra `isset($filterClasses[$selectedClassId])` và `in_array($selectedSubject, $filterSubjects, true)`. Khi phát hiện môn Ngữ Văn không thuộc danh sách môn được phân công của `gv_toan`, hệ thống tự động reset `$selectedSubject = 'all'` và câu truy vấn SQL `WHERE tc.teacher_id = ?` ép buộc chỉ lấy dữ liệu thuộc quyền sở hữu của `gv_toan`.
- **Đánh giá**: **PASS (Phòng thủ vững chắc)**.

### Challenge 3: Tấn Công XSS Qua Dữ Liệu Học Sinh & Chuỗi Chẩn Đoán
- **Kịch bản tấn công**: Học sinh mang ký tự HTML độc hại trong họ tên hoặc mã học sinh (ví dụ `<script>alert(1)</script>`).
- **Kết quả thực nghiệm**: Mọi vị trí xuất dữ liệu (`s.full_name`, `s.student_code`, `s.class_name`, `s.subject`, `s.diagnostic`, `data-name`, `data-search`) đều được bọc qua `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`. Nút "Vào sổ điểm" sử dụng `urlencode()`.
- **Đánh giá**: **PASS (Phòng thủ vững chắc)**.

---

## 5. Kết Luận & Yêu Cầu Sửa Đổi (Remediation Actions)

Yêu cầu chuyển lại cho `worker_m5` thực hiện các hiệu chỉnh bắt buộc sau:
1. **Sửa lỗi biến tại `index.php:86`**: Đổi `$isAvgWarning` thành `$isWarning` hoặc `$isAvgWarn`.
2. **Sửa mã nguồn `tests/verify_m5.php`**:
   - Loại bỏ hàm tự chứng thực `testEvaluateRisk()`.
   - Nạp hoặc gọi trực tiếp hàm chẩn đoán từ hệ thống (`evaluateRiskZone()`).
   - Sửa các bài test Nhóm 3 (3.1–3.3) để kiểm thử dữ liệu thực tế thay vì mock mảng tĩnh.
   - Thêm assertion kiểm tra cURL body của `index.php` không được chứa bất kỳ chuỗi `Warning:`, `Notice:` hay `Fatal error:` nào.
3. **Sửa trùng lặp ID**: Đổi `id="radarEmptyRow"` tại dòng 425 của `index.php` thành `id="radarInitialEmptyRow"`.
