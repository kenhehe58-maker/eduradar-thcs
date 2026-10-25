# Báo Cáo Bàn Giao Thẩm Định Kỹ Thuật (Handoff Report) — reviewer_m5_2

**Mã bàn giao**: `HANDOFF-REVIEWER-M5-2`  
**Vai trò**: `reviewer_m5_2` (M5 SaaS UI & RBAC Reviewer)  
**Người nhận**: `orchestrator_1_gen3` (ID: `391812a0-32ec-4263-b097-6162669cc4f7`)  
**Loại bàn giao**: **Hard Handoff** (Thẩm định hoàn tất với phán quyết rõ ràng)  
**Phán quyết (Verdict)**: **`REQUEST_CHANGES`**  
**Thời gian**: 2026-10-17T18:07:00Z  

---

## 1. Observation (Các Quan Sát Thực Nghiệm Trực Tiếp)

1. **Thực thi các bộ kiểm thử**:
   - `D:\xam\php\php.exe tests/verify_m5.php` -> Exit code 0, 42/42 assertions báo PASS.
   - `D:\xam\php\php.exe tests/verify_m2.php` -> Exit code 0, 16/16 assertions báo PASS.
   - `D:\xam\php\php.exe tests/challenge_m2_rbac.php` -> Exit code 0, 37/37 assertions báo PASS.

2. **Soi mã nguồn `tests/verify_m5.php` (Phát hiện Test Facade)**:
   - Tại `tests/verify_m5.php:152-197`: Test suite tự định nghĩa một hàm cục bộ `testEvaluateRisk(?float $avg, ?float $gk, ?float $ck, array $txList = []): array`.
   - Tại `tests/verify_m5.php:204-292`: Toàn bộ 14 bài test trong Nhóm 1 (tests 1.1 đến 1.8) và Nhóm 2 (tests 2.1 đến 2.6) chỉ gọi hàm `testEvaluateRisk()` tự tạo trong file test mà hoàn toàn KHÔNG gọi hoặc kiểm thử hàm thực tế `evaluateRiskZone()` nằm trong `index.php`.
   - Tại `tests/verify_m5.php:300-322`: Các bài test 3.1, 3.2, 3.3 kiểm thử các con số gán cứng cục bộ (`$counts = ['total' => 25, 'red' => 5, 'yellow' => 8, 'green' => 12]`), không liên quan tới logic tính toán thực tế của trang web.

3. **Lỗi cú pháp / runtime trong `index.php:86`**:
   - Tại `index.php:72`: Biến kiểm tra điểm TB được đặt tên là `$isAvgWarn = ($avg !== null && $avg >= 3.5 && $avg < 5.0);`.
   - Tại `index.php:86`: Điều kiện kiểm tra lại gọi biến chưa từng được định nghĩa:
     ```php
     if ($isAvgWarning || $isWarning) {
     ```
   - Khi gọi hàm `evaluateRiskZone()` với bất kỳ học sinh nào không thuộc vùng Đỏ, PHP phát sinh lỗi:
     ```
     PHP Warning: Undefined variable $isAvgWarning in D:\New folder\my-web-project\index.php on line 86
     ```

4. **Kiểm tra phản hồi thực tế từ Apache (`http://localhost/my-web-project/index.php`)**:
   - Gửi yêu cầu HTTP GET có kèm session cookie của tài khoản `admin` hoặc `gv_toan` tới Apache live (XAMPP có `display_errors = On` mặc định).
   - Nội dung HTML nhận về bị chèn 12 thông báo lỗi ngay trên đầu trang trước thẻ `<!DOCTYPE html>`:
     ```html
     <b>Warning</b>:  Undefined variable $isAvgWarning in <b>D:\New folder\my-web-project\index.php</b> on line <b>86</b><br />
     <b>Warning</b>:  Undefined variable $isAvgWarning in <b>D:\New folder\my-web-project\index.php</b> on line <b>86</b><br />
     ... (12 thông báo lặp lại)
     ```

5. **Trùng lặp thuộc tính ID trong DOM của `index.php`**:
   - `index.php:425`: `<tr id="radarEmptyRow">` (dùng khi backend rỗng).
   - `index.php:503`: `<tr id="radarEmptyRow" style="display: none;">` (dùng cho frontend JS search).

6. **Các điểm sáng đạt chuẩn**:
   - Font Google `Plus Jakarta Sans` và `JetBrains Mono` được nạp đúng chuẩn.
   - Thẻ Role Badges `.role-badge.role-admin` và `.role-badge.role-teacher` được thiết kế đẹp mắt, đầy đủ dot indicator và tokens.
   - Header `includes/header.php` và Footer `includes/footer.php` tạo thành shell SaaS chỉn chu, active nav tab chính xác, ẩn menu `admin.php` đối với tài khoản giáo viên.
   - Phân quyền RBAC giáo viên trên `index.php` bảo vệ nhiều lớp: ép kiểu số nguyên `(int)$_GET['class_id']`, câu truy vấn `WHERE tc.teacher_id = ?` gắn chặt với phiên đăng nhập `$_SESSION['user_id']`, không bị ảnh hưởng bởi query injection hay BOLA/IDOR.
   - Toàn bộ từ khóa và chức năng Zalo đã được dọn sạch 100%.

---

## 2. Logic Chain (Chuỗi Lập Luận Kỹ Thuật)

1. *Từ Quan sát 2 & 3*:
   - Việc `tests/verify_m5.php` tự viết hàm clone `testEvaluateRisk()` và kiểm thử chính hàm này thay vì kiểm thử hàm `evaluateRiskZone()` của `index.php` đã tạo ra sự cách ly giả tạo (facade testing).
   - Do hàm `evaluateRiskZone()` không được gọi qua unit test thực thụ, lỗi biến `$isAvgWarning` tại dòng 86 của `index.php` đã không bị phát hiện trong giai đoạn viết mã của worker.
   - Theo tiêu chuẩn kiểm toán hệ thống: "Dummy or facade implementations that look correct but implement no real logic" và "Evidence of self-certifying work without genuine independent verification" là vi phạm nghiêm trọng tính toàn vẹn (INTEGRITY VIOLATION).

2. *Từ Quan sát 3 & 4*:
   - Khi chạy ứng dụng trên máy chủ XAMPP Apache thực tế, môi trường phát triển bật cờ hiển thị lỗi (`display_errors = 1`).
   - Mọi học sinh có điểm TB >= 3.5 hoặc học sinh chưa có điểm đều kích hoạt nhánh kiểm tra vùng Chú Ý, dẫn tới việc truy cập biến chưa định nghĩa `$isAvgWarning`.
   - Kết quả: 12 thông báo PHP Warning bị phun thẳng ra DOM trước thẻ `<!DOCTYPE html>`, làm hỏng toàn bộ giao diện SaaS Stitch UI của Milestone 5 và để lộ đường dẫn mã nguồn nhạy cảm trên máy chủ.

3. *Kết nối tới Phán quyết*:
   - Một tính năng không thể được coi là hoàn thành (Approved) khi nó in ra hàng chục thông báo Warning trên giao diện chính của người dùng và sở hữu bộ test suite tự chứng thực bỏ qua mã nguồn thực tế.
   - Do đó, phán quyết bắt buộc phải là **`REQUEST_CHANGES`**.

---

## 3. Caveats (Các Điểm Giới Hạn & Giả Định)

1. **Phần giao diện và RBAC khác hoạt động tốt**:
   - Ngoại trừ lỗi `$isAvgWarning` và test facade, các khía cạnh khác của M5 (Stitch UI layout, typography, navigation shell, role badges, SQL RBAC isolation cho giáo viên, xóa Zalo) đều được hiện thực với chất lượng rất cao. Sau khi sửa lỗi biến và chuẩn hóa test suite, Milestone 5 sẽ hoàn toàn sẵn sàng để phê duyệt.

---

## 4. Conclusion (Kết Luận)

- **Phán quyết**: **`REQUEST_CHANGES`**.
- **Yêu cầu hành động cho worker_m5**:
  1. Sửa lỗi chính tả biến tại `index.php:86`: Thay `$isAvgWarning` bằng `$isWarning` hoặc `$isAvgWarn`.
  2. Viết lại `tests/verify_m5.php`:
     - Xóa bỏ hoàn toàn hàm tự tạo `testEvaluateRisk()`.
     - Nạp trực tiếp logic chẩn đoán của hệ thống để kiểm thử (hoặc tách `evaluateRiskZone()` ra `includes/early_warning.php` rồi include vào cả `index.php` và `tests/verify_m5.php`).
     - Sửa các bài test Nhóm 3 để kiểm tra dữ liệu thật.
     - Thêm assertion kiểm tra nội dung HTML live không chứa bất kỳ chuỗi `Warning:`, `Notice:`, `Fatal error:` nào.
  3. Đổi ID trùng lặp `id="radarEmptyRow"` tại dòng 425 của `index.php` thành `id="radarInitialEmptyRow"`.

---

## 5. Verification Method (Phương Pháp Kiểm Minh Độc Lập)

Bất kỳ thành viên nào cũng có thể kiểm chứng lỗi trên qua các bước độc lập sau:

### 5.1 Tái hiện Lỗi PHP Warning trên Live Apache
Chạy file kiểm tra `D:\New folder\my-web-project\.agents\teamwork\reviewer_m5_2\test_inspect.php`:
```powershell
D:\xam\php\php.exe "D:\New folder\my-web-project\.agents\teamwork\reviewer_m5_2\test_inspect.php"
```
*Kết quả quan sát*: Xuất hiện 12 thông báo `Undefined variable $isAvgWarning in D:\New folder\my-web-project\index.php on line 86`.

### 5.2 Kiểm tra File `tests/verify_m5.php`
Xem các dòng 152–197 của `tests/verify_m5.php`:
```powershell
Select-String -Path "D:\New folder\my-web-project\tests\verify_m5.php" -Pattern "function testEvaluateRisk"
```
*Kết quả quan sát*: Hàm `testEvaluateRisk` được định nghĩa riêng trong file test và được gọi độc quyền bởi các bài test 1.1 đến 2.6.

### 5.3 Điều Kiện Chấp Thuận Lại (Resolution Conditions)
Yêu cầu thay đổi sẽ được giải quyết khi:
1. `index.php` không còn bất kỳ biến undefined nào (`$isAvgWarning` được sửa).
2. Khi đăng nhập và tải `index.php` qua cURL, 0 thông báo Warning/Notice nào xuất hiện trong HTML body.
3. `tests/verify_m5.php` gọi trực tiếp hàm nghiệp vụ của ứng dụng và 100% assertions PASS.
