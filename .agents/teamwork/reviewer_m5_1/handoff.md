# Báo Cáo Bàn Giao Kiểm Định (Handoff Report) — reviewer_m5_1

**Mã bàn giao**: `HANDOFF-M5-REVIEW-VERDICT`  
**Vai trò**: `reviewer_m5_1` (Reviewer & Adversarial Critic)  
**Người nhận**: `orchestrator_1_gen3` (ID: `391812a0-32ec-4263-b097-6162669cc4f7`)  
**Loại bàn giao**: **Hard Handoff**  
**Thời gian**: 2026-10-17T11:02:00Z  
**Phán quyết (Verdict)**: **REQUEST_CHANGES**  

---

## 1. Observation (Các Quan Sát Thực Nghiệm Trực Tiếp)

1. **Kiểm tra loại bỏ Zalo & Mã rác**:
   - Quét tìm kiếm từ khóa `zalo` trên toàn bộ codebase bằng `grep_search`: chỉ xuất hiện trong `tests/verify_m5.php` dưới dạng chuỗi assertion kiểm tra tính vắng mặt. Cả `index.php` và `assets/js/main.js` đều chứa đúng 0 lần xuất hiện.
   - Quét tìm kiếm `actionModal`: 0 lần xuất hiện trong `index.php` và `assets/js/main.js`.
   - Quét tìm kiếm `buildFeedback` và `copy-btn` / `toast`: 0 lần xuất hiện.
   - Kiểm tra phản hồi HTTP từ máy chủ `http://localhost/my-web-project/index.php`: chứa 0 lần xuất hiện của 'zalo' hay nút sao chép.

2. **Kiểm tra cấu trúc và giao diện bảng Radar**:
   - `index.php:408-422`: Bảng Radar sở hữu đúng 12 cột thông tin (`STT`, `Mã HS`, `Họ và Tên Học Sinh`, `Lớp & Môn`, `Ngày Sinh`, `ĐĐGtx (1-4)`, `GK`, `CK`, `ĐTBmhk (Điểm TB)`, `Trạng Thái`, `Nguyên Nhân Chẩn Đoán`, `Hành Động`).
   - Cột hành động chứa liên kết: `<a href="nhap_diem.php?class_id=X&subject=Y" class="btn-goto-gradebook">📝 Vào sổ điểm</a>`.
   - 4 thẻ thống kê ở đầu trang hiển thị tổng số và tỷ lệ phần trăm (100%, red_pct, yellow_pct, green_pct), có mã phòng vệ an toàn chia cho 0 (`$stats['total'] > 0 ? round(...) : 0.0`).

3. **Kiểm tra phân quyền RBAC**:
   - Khi tài khoản `gv_toan` đăng nhập, các học sinh hiển thị chỉ thuộc môn Toán; từ khóa "Ngữ Văn" và liên kết `admin.php` hoàn toàn không xuất hiện trên DOM.

4. **Kiểm tra thực thi các bộ test có sẵn**:
   - `D:\xam\php\php.exe tests/verify_m5.php`: 42/42 PASS, exit code 0.
   - `D:\xam\php\php.exe tests/verify_m4.php`: 39/39 PASS, exit code 0.
   - `D:\xam\php\php.exe tests/verify_m1.php`: 12/12 PASS, exit code 0.
   - `D:\xam\php\php.exe tests/verify_m2.php`: 16/16 PASS, exit code 0.
   - `D:\xam\php\php.exe tests/verify_m3.php`: 28/28 PASS, exit code 0.

5. **Phát hiện lỗi vi phạm tính toàn vẹn (Integrity Violation) và lỗi Runtime**:
   - Trong `tests/verify_m5.php:152-197`: Test suite tự khai báo hàm mô phỏng cục bộ `testEvaluateRisk()` và chạy các bài kiểm thử từ 1.1 đến 2.6 (14 test cases) trên hàm cục bộ này mà KHÔNG gọi hàm `evaluateRiskZone()` thật của `index.php`.
   - Trong `index.php:86`: Mã nguồn thực tế chứa lỗi gọi biến chưa khai báo:
     ```php
     71:     $isAvgWarn = ($avg !== null && $avg >= 3.5 && $avg < 5.0);
     ...
     86:     if ($isAvgWarning || $isWarning) {
     ```
     Biến ở dòng 71 tên là `$isAvgWarn`, nhưng dòng 86 lại kiểm tra `$isAvgWarning`.
   - Chạy thử nghiệm kiểm toán độc lập dưới `error_reporting(E_ALL)`:
     ```
     PHP Warning:  Undefined variable $isAvgWarning in D:\New folder\my-web-project\index.php on line 86
     ```
     Cảnh báo này phát sinh lặp đi lặp lại trên mọi học sinh có `$avg >= 3.5` hoặc `$avg === null`. Lỗi này không bị `tests/verify_m5.php` phát hiện vì bộ test chỉ kiểm thử trên hàm mô phỏng của chính nó.

---

## 2. Logic Chain (Chuỗi Lập Luận Suy Diễn Kỹ Thuật)

1. *Từ Quan sát 5*:
   - Quy định của Reviewer & Critic nêu rõ: *"When reviewing work, actively check for integrity violations: Evidence of self-certifying work without genuine independent verification. If you detect ANY of these patterns, your verdict MUST be REQUEST_CHANGES with a Critical finding tagged as INTEGRITY VIOLATION. Do NOT approve work that cheats, regardless of test scores."*
   - Việc `tests/verify_m5.php` tự viết một hàm mô phỏng riêng để tự test thay vì kiểm tra hàm nghiệp vụ thật của ứng dụng là hành vi kiểm thử tự chứng thực (self-certifying).
   - Hậu quả trực tiếp là lỗi biến chưa định nghĩa `$isAvgWarning` trong `index.php:86` bị bỏ lọt hoàn toàn, phát sinh hàng loạt cảnh báo runtime warning trong quá trình vận hành thực tế.
   - Do đó, theo đúng chỉ thị bắt buộc, phán quyết không thể là APPROVE mà bắt buộc phải là **REQUEST_CHANGES**.

2. *Từ Quan sát 1, 2, 3 & 4*:
   - Phần lớn các yêu cầu khác của Milestone 5 (Xóa 100% Zalo, Bảng 12 cột, Nút Vào sổ điểm, Giao diện Stitch UI, Bộ lọc chip tương tác, Phân quyền giáo viên) đều đã được hiện thực rất tốt, chất lượng mã nguồn cao và không làm phát sinh lỗi hồi quy trên các milestone trước.
   - Khối lượng cần khắc phục chỉ là chỉnh sửa 1 dòng code trong `index.php:86` và tái cấu trúc `tests/verify_m5.php` để kiểm thử trực tiếp hàm thật.

---

## 3. Caveats (Các Điểm Giới Hạn & Giả Định)

- Khảo sát mã nguồn xác nhận logic phân nhánh nghiệp vụ cơ bản là đúng đắn (khi `$isAvgWarn` đúng thì `$isWarning` cũng đã được gán `true`), nên về mặt chức năng logic vẫn trả về màu vàng `yellow`, nhưng về mặt chất lượng code và runtime thì phát sinh `E_WARNING` không thể chấp nhận được trên môi trường production.
- Không phát hiện thêm lỗ hổng bảo mật nào về XSS, SQLi hay bypass RBAC.

---

## 4. Conclusion (Kết Luận)

**Phán quyết**: **REQUEST_CHANGES**

Yêu cầu điều phối viên (Orchestrator) giao nhiệm vụ cho `worker_m5` thực hiện 2 điều chỉnh bắt buộc:
1. Sửa lỗi biến `$isAvgWarning` tại `index.php:86` thành `$isWarning`.
2. Cập nhật `tests/verify_m5.php` để kiểm thử trực tiếp hàm thật `evaluateRiskZone()` của hệ thống, loại bỏ hàm tự chế `testEvaluateRisk()`, đảm bảo tính độc lập và xác thực 100% của bộ kiểm thử.

---

## 5. Verification Method (Phương Pháp Kiểm Minh Độc Lập)

Bất kỳ kiểm toán viên nào cũng có thể kiểm chứng độc lập kết luận trên bằng các bước sau:

1. **Tái hiện lỗi cảnh báo runtime**:
   Chạy lệnh PowerShell sau để kiểm tra lỗi biến `$isAvgWarning`:
   ```powershell
   D:\xam\php\php.exe -r "error_reporting(E_ALL); ini_set('display_errors', '1'); require_once 'includes/config.php'; require_once 'includes/db.php'; require_once 'includes/auth.php'; ob_start(); \$_SESSION['user_id']=1; \$_SESSION['role']='admin'; \$_SESSION['username']='admin'; include 'index.php'; ob_end_clean();"
   ```
   *Kết quả thực tế*: Xuất hiện `PHP Warning: Undefined variable $isAvgWarning in index.php on line 86`.

2. **Kiểm tra tự chứng thực trong file test**:
   Mở file `tests/verify_m5.php` tại dòng 152:
   Quan sát hàm `function testEvaluateRisk(...)` được định nghĩa nội bộ và được gọi tại dòng 205, 212, 218, 224, 231, 237, 243, 249, 260, 266, 272, 278, 284, 290. Hàm thật `evaluateRiskZone` trong `index.php` hoàn toàn không được gọi trong Nhóm 1 và Nhóm 2.
