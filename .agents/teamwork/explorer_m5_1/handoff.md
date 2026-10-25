# Báo Cáo Bàn Giao Kỹ Thuật (Handoff Report) — explorer_m5_1

**Mã bàn giao**: `HANDOFF-M5-EXPLORER-1-RADAR-ENGINE`  
**Vai trò**: `explorer_m5_1` (Early-Warning Radar Engine Explorer)  
**Người nhận**: `orchestrator_1_gen3` (ID: `391812a0-32ec-4263-b097-6162669cc4f7`)  
**Loại bàn giao**: **Hard Handoff** (Nhiệm vụ điều tra và thiết kế đã hoàn thành toàn diện)  
**Thời gian**: 2026-10-17T17:35:00Z  

---

## 1. Observation (Các Quan Sát Thực Nghiệm Trực Tiếp)

1. **Lỗi Schema & Truy vấn SQL trong `index.php` (dòng 74–82)**:
   ```php
   // index.php:74-82
   $query = "
       SELECT s.id, s.full_name, s.parent_phone, c.name AS class_name,
              sc.m1, sc.m2, sc.p15_1, sc.p15_2, sc.t1_1, sc.gk, sc.ck, sc.avg_score
       FROM students s
       JOIN classes c ON s.class_id = c.id
       LEFT JOIN scores sc ON s.id = sc.student_id
       $classFilter
       ORDER BY s.full_name ASC
   ";
   ```
   - **Quan sát trực tiếp**: Cơ sở dữ liệu bảng `scores` trong `database.sql:84-99` hiện có các cột `tx1, tx2, tx3, tx4, gk, ck, avg_score, subject`. Các cột `m1, m2, p15_1, p15_2, t1_1` hoàn toàn không tồn tại trong schema MySQL hiện hành.
   - Khi chạy truy vấn trên, MySQL báo lỗi nếu các cột cũ bị loại bỏ, hoặc `LEFT JOIN scores sc ON s.id = sc.student_id` kết hợp với bảng `scores` có nhiều môn học (`subject`) gây ra tình trạng mỗi học sinh bị nhân bản thành $N$ dòng cho $N$ môn học khác nhau.

2. **Lỗi Phân Quyền Giáo Viên (RBAC) trong `index.php` (dòng 18–21)**:
   ```php
   // index.php:18-21
   $classFilter = "WHERE s.class_id IN (SELECT class_id FROM teacher_classes WHERE teacher_id = ?)";
   $params = [$userId];
   ```
   - **Quan sát trực tiếp**: Giáo viên `gv_toan` (ID 2) được phân công dạy môn Toán tại Lớp 6A1 (`teacher_classes:149-152`). Giáo viên `gv_van` (ID 3) dạy môn Ngữ Văn tại Lớp 6A1.  
   - Với bộ lọc `$classFilter` chỉ theo `class_id`, `gv_toan` truy cập `index.php` sẽ thấy tất cả điểm số của học sinh lớp 6A1, bao gồm cả điểm môn Ngữ Văn của `gv_van`. Điều này vi phạm nghiêm trọng nguyên tắc bảo mật và phân quyền giảng dạy của Thông tư 22 và quy định R1/R4 trong `PROJECT.md`.

3. **Lỗi Động Cơ Đánh Giá Rủi Ro trong `index.php` (dòng 26–54)**:
   ```php
   // index.php:32-35
   if ($avg !== null && $avg < PASS_SCORE) {
       $level = 'red';
       $reasons[] = 'Điểm TB dưới ' . PASS_SCORE;
   }
   // index.php:37-40
   if ($n >= 2 && $scores[$n - 1] < $scores[$n - 2] && $scores[$n - 1] < PASS_SCORE) {
       $level = 'red';
       $reasons[] = 'Bài kiểm tra gần nhất sa sút';
   }
   ```
   - **Quan sát trực tiếp**: Hằng số `PASS_SCORE` không được định nghĩa trong `includes/config.php` (khiến PHP phát sinh cảnh báo `Warning: Undefined constant PASS_SCORE`).
   - Logic không kiểm tra các tiêu chí bắt buộc của M5:
     - Thiếu điều kiện `avg_score < 3.5`
     - Thiếu điều kiện `ck < 3.0`
     - Thiếu điều kiện hỏng cả 2 bài thi định kỳ `(gk < 5.0 && ck < 5.0)`
     - Thiếu điều kiện sa sút mạnh cuối kỳ `(ck < gk - 1.5)`

4. **Tồn Tại Nút Zalo & Mã Thừa (dòng 56–72, 208–212, 221 trong `index.php`)**:
   - `index.php:56-72`: Hàm `buildFeedback(array $s): string` tạo lời nhắn Zalo gửi phụ huynh.
   - `index.php:208-212`:
     ```html
     <button class="btn copy-btn" data-text="<?= htmlspecialchars($s['feedback']) ?>">
         📋 Copy Tin Nhắn Zalo
     </button>
     ```
   - `index.php:221`: `<div id="toast" class="toast">Đã sao chép tin nhắn Zalo!</div>`.
   - **Quan sát trực tiếp**: `ORIGINAL_REQUEST.md:42-46` quy định tường minh: *"DO NOT include the Zalo copy button or parent message generator (the user explicitly asked to hold off on the Zalo button)"*.

5. **Thẻ Thống Kê Đầu Trang Thiếu Tỷ Lệ Phần Trăm (`index.php:148-166`)**:
   - Chỉ xuất ra số đếm tuyệt đối `<b><?= $stats['red'] ?></b>` mà không có tỷ lệ phần trăm tương đối so với tổng số học sinh đang quản lý.

6. **Bảng Học Sinh Thiếu Cột Chuẩn & Nút Hành Động Trực Tiếp**:
   - Hiện tại bảng chỉ có 6 cột: `Học sinh`, `Lớp`, `Điểm TB Môn`, `Trạng thái`, `Nguyên nhân cảnh báo`, `Nhận xét gửi phụ huynh (Zalo)`.
   - Hoàn toàn thiếu: `Mã HS`, `Ngày sinh`, điểm chi tiết các cột thường xuyên (`tx1..tx4`), điểm giữa kỳ (`gk`), điểm cuối kỳ (`ck`), và nút chuyển hướng trực tiếp **"Vào sổ điểm"**.

---

## 2. Logic Chain (Chuỗi Lập Luận Suy Diễn Kỹ Thuật)

1. **Từ Quan sát 1 & 2** $\rightarrow$ Dữ liệu sổ điểm trong cơ sở dữ liệu phân cấp theo cặp khóa ngoại `(student_id, subject)` và phân công giáo viên theo bộ 3 `(teacher_id, class_id, subject)`.  
   $\rightarrow$ Do đó, truy vấn lấy dữ liệu cảnh báo sớm bắt buộc phải khởi đầu từ `teacher_classes tc`, kết nối qua `classes c` và `students s`, sau đó kết nối chính xác tới `scores sc` theo điều kiện kép: `sc.student_id = s.id AND sc.subject = tc.subject`.  
   $\rightarrow$ Giải pháp này loại bỏ 100% rủi ro dữ liệu rác, triệt tiêu tích Đề-các và đảm bảo giáo viên chỉ thấy dữ liệu môn học mình phụ trách.

2. **Từ Quan sát 3** $\rightarrow$ Hệ thống cảnh báo học tập phải phản ánh đúng tình trạng học lực thực tế theo Thông tư 22:
   - Điểm thi Cuối kỳ (`ck`) chiếm hệ số 3 và Điểm Giữa kỳ (`gk`) chiếm hệ số 2. Một học sinh dù điểm kiểm tra thường xuyên cao nhưng nếu hỏng cả 2 bài thi định kỳ (`gk < 5.0 && ck < 5.0`) hoặc bị điểm liệt cuối kỳ (`ck < 3.0`) thì học lực thực tế đang ở mức báo động đỏ.
   - Tương tự, nếu một học sinh giữa kỳ đạt 8.5 nhưng cuối kỳ tụt dốc xuống 6.5 (`ck < gk - 1.5`), dù điểm trung bình vẫn trên 5.0 nhưng đà sa sút bất thường là chỉ báo cảnh báo sớm (Early Warning) cần được giáo viên chú ý ngay.
   - Do đó, động cơ phân loại 3 vùng phải tuân thủ thứ tự ưu tiên:
     - **Ưu tiên 1 (Nguy cấp)**: `avg < 3.5` OR `ck < 3.0` OR `(gk < 5.0 && ck < 5.0)`.
     - **Ưu tiên 2 (Cần chú ý)**: `3.5 <= avg < 5.0` OR `ck < gk - 1.5`.
     - **Ưu tiên 3 (Ổn định)**: `avg >= 5.0` không có cờ sa sút.

3. **Từ Quan sát 4 & 6** $\rightarrow$ Nhu cầu thực tế của giáo viên khi phát hiện học sinh rơi vào vùng Đỏ hoặc Vàng là kiểm tra ngay chi tiết các bài kiểm tra và điều chỉnh điểm số hoặc nhập bổ sung điểm.  
   $\rightarrow$ Việc xóa bỏ nút Zalo và thay thế bằng nút **"📝 Vào sổ điểm"** dẫn trực tiếp tới `nhap_diem.php?class_id=X&subject=Y` giải quyết trọn vẹn yêu cầu người dùng, xóa mã thừa và tăng tối đa năng suất sư phạm.

4. **Từ Quan sát 5** $\rightarrow$ Tỷ lệ phần trăm tính bằng công thức an toàn:
   $$\text{Percent} = \begin{cases} \text{round}\left(\frac{\text{Count}}{\text{Total}} \times 100, 1\right) & \text{khi } \text{Total} > 0 \\ 0.0 & \text{khi } \text{Total} = 0 \end{cases}$$
   $\rightarrow$ Ngăn chặn triệt để lỗi chia cho 0 (`DivisionByZeroError`) khi lớp học mới tạo chưa có học sinh.

---

## 3. Caveats (Các Điểm Giới Hạn & Giả Định)

1. **Dữ liệu điểm chưa đầy đủ (Incomplete Scores)**:
   - Nếu học sinh mới vào trường hoặc giáo viên chưa nhập điểm bài thi cuối kỳ (`ck === null`), điều kiện sa sút `ck < gk - 1.5` sẽ không kích hoạt. Đây là hành vi chính xác, tránh báo động sai khi chưa có dữ liệu cuối kỳ.
   - Học sinh chưa có điểm nào (`avg === null`) sẽ được xếp vào Vùng Ổn định với thẻ lý do `Chưa đủ dữ liệu điểm số`, không bị gắn cờ nguy cấp sai lầm.
2. **Sai số số thực (Floating-Point Epsilon)**:
   - Khi so sánh hiệu số `$gk - $ck > 1.5`, bắt buộc phải làm tròn 1 chữ số thập phân (`round((float)$gk - (float)$ck, 1) > 1.5`) để tránh lỗi làm tròn số thực trong ngôn ngữ PHP.
3. **Phạm vi quyền hạn của Explorer**:
   - Bản báo cáo này là tài liệu phân tích kỹ thuật và đặc tả thiết kế (Read-only Investigation). Explorer không trực tiếp chỉnh sửa mã nguồn gốc (`index.php`, `main.js`), việc hiện thực mã sẽ do Worker M5 thực hiện dựa trên đặc tả tại `analysis.md`.

---

## 4. Conclusion (Kết Luận Đánh Giá Kỹ Thuật)

Động cơ Chẩn đoán Sớm (Early-Warning Radar Diagnostic Engine) đã được thiết kế hoàn chỉnh, đáp ứng 100% 6 nhiệm vụ của Milestone 5:
1. **Phân loại 3 vùng chính xác**: Định nghĩa rõ ràng 5 điều kiện toán học và nhãn tiếng Việt tương ứng.
2. **Thẻ thống kê tỷ lệ %**: Tính toán chính xác 4 chỉ số và tỷ lệ phần trăm tương đối, hiển thị trực quan theo phong cách Stitch UI.
3. **Bộ lọc tức thì Client-Side**: 4 nút chip lọc trạng thái và ô tìm kiếm đa năng không cần tải lại trang.
4. **Bảng dữ liệu Radar toàn diện**: 12 cột thông tin chuẩn VnEdu với liên kết "Vào sổ điểm" trực tiếp.
5. **Xóa sổ 100% Zalo & Dead Code**: Loại bỏ hoàn toàn nút Zalo, hàm tạo tin nhắn, toast notification và modal không dùng.
6. **Bảo mật RBAC tuyệt đối**: Khóa chặt truy vấn theo bảng phân công `teacher_classes`, loại bỏ triệt để rủi ro rò rỉ dữ liệu chéo giữa các giáo viên.

---

## 5. Verification Method (Phương Pháp Kiểm Minh Độc Lập)

Để kiểm minh độc lập các phát hiện và thiết kế trên, Worker và Reviewer có thể thực hiện:

### 5.1. Lệnh Kiểm Tra Hệ Thống
Chạy test suite hồi quy hiện tại bằng PHP CLI của XAMPP:
```powershell
D:\xam\php\php.exe tests/verify_m4.php
```
Kết quả mong đợi: `39/39 tests PASS, 0 failures`.

### 5.2. Bộ Test Case Đề Xuất Cho `tests/verify_m5.php`

| Test Case | Đầu vào (Input Profile) | Kết quả kỳ vọng (Expected) | Ý nghĩa kiểm chứng |
|---|---|---|---|
| **TC1** | `avg=3.2, gk=4.0, ck=4.0` | 🔴 `red` ("Điểm TB dưới 3.5") | Kiểm tra tiêu chí C1 (`avg < 3.5`) |
| **TC2** | `avg=5.2, gk=7.0, ck=2.5` | 🔴 `red` ("Thi cuối kỳ dưới 3.0") | Kiểm tra tiêu chí C2 (`ck < 3.0`) |
| **TC3** | `avg=4.2, gk=4.5, ck=4.0` | 🔴 `red` ("Hỏng cả GK và CK") | Kiểm tra tiêu chí C3 (`gk < 5 && ck < 5`) |
| **TC4** | `avg=4.2, gk=6.0, ck=5.0` | 🟡 `yellow` ("Điểm TB dưới chuẩn") | Kiểm tra tiêu chí W1 (`3.5 <= avg < 5.0`) |
| **TC5** | `avg=6.5, gk=8.5, ck=6.5` | 🟡 `yellow` ("Sa sút cuối kỳ") | Kiểm tra tiêu chí W2 (`ck < gk - 1.5`, giảm 2.0 đ) |
| **TC6** | `avg=8.2, gk=8.5, ck=9.0` | 🟢 `green` ("Học lực Giỏi, kết quả xuất sắc") | Kiểm tra tiêu chí S1 (`avg >= 8.0`) |
| **TC7** | `avg=null, gk=null, ck=null` | 🟢 `green` ("Chưa đủ dữ liệu điểm số") | Kiểm tra học sinh chưa nhập điểm |
| **TC8** | Lọc DOM & Zalo Scan | `stripos($html, 'zalo') === false` | Khẳng định 0 vết tích Zalo trên trang |
| **TC9** | Giáo viên `gv_toan` truy cập | Chỉ thấy lớp 6A1 môn Toán, 0 dòng Ngữ Văn | Khẳng định tính toàn vẹn của RBAC |

---
*Tài liệu bàn giao đầy đủ (Hard Handoff) — Hoàn tất trách nhiệm điều tra của `explorer_m5_1`.*
