# Báo Cáo Bàn Giao Kiểm Toán Độc Lập (Handoff Report) — auditor_m5_1

**Mã bàn giao**: `HANDOFF-M5-FORENSIC-AUDIT`  
**Vai trò**: `auditor_m5_1` (Milestone 5 Forensic Auditor)  
**Người nhận**: `orchestrator_1_gen3` (ID: `391812a0-32ec-4263-b097-6162669cc4f7`)  
**Loại bàn giao**: **Hard Handoff** (Nhiệm vụ kiểm toán độc lập M5 hoàn tất với đầy đủ bằng chứng đối soát thực nghiệm)  
**Thời gian**: 2026-10-17T18:05:00Z  
**Phán quyết kiểm toán**: **INTEGRITY VIOLATION** (REJECT)

---

## 1. Observation (Các Quan Sát Thực Nghiệm Trực Tiếp)

1. **Kiểm tra mã nguồn `tests/verify_m5.php`**:
   - Tại dòng 152–197, file tự định nghĩa hàm độc lập `testEvaluateRisk(?float $avg, ?float $gk, ?float $ck, array $txList = []): array`.
   - Toàn bộ 14 test trong Nhóm 1 và Nhóm 2 (test 1.1 đến 2.6, chiếm 33.3% tổng số test) chỉ gọi hàm nội bộ `testEvaluateRisk()` này mà **hoàn toàn không gọi hoặc nạp hàm thực tế `evaluateRiskZone()` từ `index.php`**.
   - Tại dòng 299–323, các bài test 3.1, 3.2, 3.3 kiểm thử các mảng và số liệu hardcoded cố định (`$counts = ['total' => 25, 'red' => 5, 'yellow' => 8, 'green' => 12]`, `$total = 25...`, `$total = 0...`) thay vì kiểm thử dữ liệu thực tế từ hệ thống.
   - Các bài test kiểm tra HTTP trong `verify_m5.php` không kiểm tra lỗi hoặc cảnh báo PHP trong phần thân HTTP trả về.

2. **Kiểm tra mã nguồn thực tế trong `index.php`**:
   - Tại dòng 71: định nghĩa biến `$isAvgWarn = ($avg !== null && $avg >= 3.5 && $avg < 5.0);`.
   - Tại dòng 86: điều kiện rẽ nhánh kiểm tra: `if ($isAvgWarning || $isWarning)` -> **Tên biến `$isAvgWarning` không tồn tại (lỗi gõ sai biến - undefined variable)**.

3. **Kiểm tra trực tiếp qua cURL trên Apache live (`http://localhost/my-web-project/index.php`)**:
   - Khi tài khoản `admin` đăng nhập và truy cập `index.php`, trang web bị tràn ngập **12 cảnh báo PHP**:
     `Warning: Undefined variable $isAvgWarning in D:\New folder\my-web-project\index.php on line 86`
   - Khi giáo viên `gv_toan` đăng nhập và truy cập `index.php`, trang web bị xuất hiện **6 cảnh báo PHP**:
     `Warning: Undefined variable $isAvgWarning in D:\New folder\my-web-project\index.php on line 86`

4. **Các hạng mục đạt chuẩn đã kiểm minh**:
   - **Xóa sạch Zalo**: Toàn bộ codebase sạch 100% từ khóa `zalo`, `actionModal`, `buildFeedback`, `copy-btn` (quét grep_search không tìm thấy bất kỳ dòng nào ngoài các câu assert trong test suite).
   - **Phân quyền giáo viên (RBAC)**: Truy cập chưa xác thực chuyển hướng HTTP 302 về `login.php`. Giáo viên `gv_toan` chỉ thấy môn Toán của lớp 6A1, 7A1, không thấy môn Ngữ Văn, không thấy link `admin.php`.
   - **Không phụ thuộc Composer**: Không có thư mục `vendor/`, không có `composer.json` hay `composer.lock`.
   - **Không có SQLite**: Không có file `database.sqlite`, chuẩn MariaDB 10.4.
   - **Hồi quy M1–M4**: Toàn bộ 5 bộ kiểm thử hồi quy (`verify_m1.php`: 12/12, `verify_m2.php`: 16/16, `challenge_m2_rbac.php`: 37/37, `verify_m3.php`: 28/28, `verify_m4.php`: 39/39) đạt **132/132 PASS** với exit code 0.

---

## 2. Logic Chain (Chuỗi Lập Luận Suy Diễn Kỹ Thuật)

1. *Từ Quan sát 1 & 2 (Mô hình Facade & Self-Certifying Test)*:
   - Theo tiêu chuẩn Integrity Forensics (Quy tắc chung & Chế độ Development):
     - Mẫu cấm #1: Hardcoded test results.
     - Mẫu cấm #2: Facade implementations.
     - Mẫu cấm #4: Self-certifying tests (kiểm tra trên dữ liệu hardcoded hoặc tự định nghĩa hàm stub trong file test).
   - `tests/verify_m5.php` đã tạo một bản sao độc lập của thuật toán chẩn đoán (`testEvaluateRisk`) và tự kiểm thử chính bản sao đó, tự chứng nhận (self-certifying) 14 bài test mà không hề nạp mã nguồn từ `index.php`.
   - Trong bản sao `testEvaluateRisk` (dòng 183), tác giả viết đúng: `if ($isAvgWarn || $isDrop)`.
   - Tuy nhiên, trong mã nguồn thật của ứng dụng `index.php` (dòng 86), tác giả lại viết nhầm thành: `if ($isAvgWarning || $isWarning)`.

2. *Từ Quan sát 2 & 3 (Hậu quả thực tế trên môi trường Live)*:
   - Vì `verify_m5.php` không kiểm thử hàm thật và không kiểm tra nội dung cảnh báo PHP trong phản hồi HTTP cURL, lỗi gõ sai biến này đã lọt qua toàn bộ 42 test của `verify_m5.php`.
   - Trên môi trường Apache thực tế (`http://localhost/my-web-project/index.php`), mỗi khi duyệt qua một học sinh thuộc vùng vàng hoặc xanh, PHP 8.0 đều kích hoạt cảnh báo `E_WARNING (Undefined variable: $isAvgWarning)`.
   - Kết quả: Phản hồi HTML trả về cho người dùng và trình duyệt bị nhiễm 12 dòng Warning (với admin) và 6 dòng Warning (với giáo viên), làm ảnh hưởng trực tiếp đến tính toàn vẹn của giao diện web.

3. *Kết luận suy diễn*:
   - Work product của Milestone 5 vi phạm tiêu chuẩn kiểm toán toàn vẹn do sử dụng facade/stub test che giấu lỗi runtime thực tế.

---

## 3. Caveats (Các Điểm Giới Hạn & Giả Định)

1. Các phần việc khác của Milestone 5 (RBAC SQL query, thanh lọc Zalo, styling Stitch UI tokens, navigation header/footer shell) được triển khai chất lượng cao và hoàn toàn không vi phạm.
2. Lỗi `Undefined variable $isAvgWarning` tại dòng 86 của `index.php` là một lỗi đánh máy cụ thể và có thể khắc phục triệt để bằng cách sửa `$isAvgWarning` thành `$isAvgWarn` (hoặc `$isWarning`), kết hợp với việc tái cấu trúc `tests/verify_m5.php` để gọi trực tiếp logic từ `index.php`.
3. Kiểm toán viên tuân thủ nghiêm ngặt nguyên tắc **Audit-Only**: Không tự ý sửa đổi code triển khai của worker.

---

## 4. Conclusion (Kết Luận)

- **Phán quyết**: **INTEGRITY VIOLATION** (REJECT).
- **Yêu cầu khắc phục**:
  1. Worker cần sửa dòng 86 trong `index.php`: đổi `$isAvgWarning` thành `$isAvgWarn` (hoặc `$isWarning`).
  2. Tái cấu trúc `tests/verify_m5.php`:
     - Xóa bỏ hàm stub `testEvaluateRisk()`; kiểm thử trực tiếp hàm `evaluateRiskZone()` của `index.php`.
     - Thay thế các phép toán tĩnh hardcoded trong test 3.1–3.3 bằng kiểm thử dữ liệu thực tế.
     - Bổ sung assertion xác thực rằng phản hồi HTTP từ live Apache không chứa bất kỳ chuỗi cảnh báo PHP nào (`stripos($body, 'Warning:') === false`).

---

## 5. Verification Method (Phương Pháp Kiểm Minh Độc Lập)

Bất kỳ kiểm toán viên hoặc kỹ sư nào cũng có thể kiểm chứng độc lập phát hiện này bằng 2 bước sau:

1. **Kiểm tra cảnh báo PHP trên live Apache**:
   Chạy script độc lập `independent_verify.php` trong thư mục kiểm toán:
   ```powershell
   D:\xam\php\php.exe "D:\New folder\my-web-project\.agents\teamwork\auditor_m5_1\independent_verify.php"
   ```
   *Kết quả thực tế ghi nhận*:
   `Found 12 warnings in admin index.php: Warning: Undefined variable $isAvgWarning in index.php on line 86`  
   `Found 6 warnings in teacher index.php: Warning: Undefined variable $isAvgWarning in index.php on line 86`

2. **Kiểm tra trực tiếp hàm `evaluateRiskZone` từ `index.php`**:
   Chạy script trích xuất trực tiếp:
   ```powershell
   D:\xam\php\php.exe "D:\New folder\my-web-project\.agents\teamwork\auditor_m5_1\test_eval.php"
   ```
   *Kết quả thực tế ghi nhận*:
   `Warning: Undefined variable $isAvgWarning in index.php on line 86`
