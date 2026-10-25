# Báo Cáo Phân Tích & Thiết Kế: Score Calculation Engine & Database Persistence
**Milestone 4 — Feature #13: VnEdu Score Calculation & Transaction Persistence**
**Explorer**: `explorer_m4_2`  
**Ngày thực hiện**: 2026-10-17  
**Phạm vi mục tiêu**: `nhap_diem.php`, `scores` table, client-side JS engine, server-side PHP engine, transaction safety, RBAC & BOLA defense.

---

## 1. TỔNG QUAN & RANH GIỚI BÀI TOÁN

Nhiệm vụ của `explorer_m4_2` là khảo sát, phân tích sâu và thiết kế toàn diện kiến trúc:
1. **Engine tính toán điểm trung bình môn học kỳ (`ĐTBmhk`)**: Chuẩn hóa công thức theo Thông tư 22/2021/TT-BGDĐT và chuẩn VnEdu cho cả Client-side (JavaScript tương tác thời gian thực) và Server-side (PHP xác thực và lưu trữ).
2. **Persistence dữ liệu vào bảng `scores`**: Thiết kế cơ chế lưu trữ chuẩn hóa trên MariaDB 10.4 / MySQL 8.0, đồng bộ hoàn toàn với cấu trúc bảng `scores` (`tx1, tx2, tx3, tx4, gk, ck, avg_score, updated_at`).
3. **An toàn giao dịch (Transaction Safety)**: Đảm bảo tính nguyên tử (Atomicity & Consistency) qua `db()->beginTransaction()`, `commit()`, và `rollBack()`, ngăn chặn tình trạng cập nhật dở dang khi có lỗi dữ liệu.
4. **Phòng chống BOLA / IDOR & Bảo vệ RBAC**: Kiểm tra nghiêm ngặt quyền phân công giảng dạy (`require_teacher_assignment`), chặn giả mạo định danh qua `teacher_id` (cả GET và POST), và kiểm tra danh sách học sinh theo lớp (`class_id`) nhằm ngăn chặn tuyệt đối lỗi tiêm mã học sinh ngoại lai (Cross-Class Student ID Injection).
5. **Nhật ký kiểm toán (Audit Logging)**: Tự động ghi nhận hành động `Lưu sổ điểm` vào bảng `audit_logs` với đầy đủ tên lớp, môn học và số lượng học sinh được cập nhật.

---

## 2. HIỆN TRẠNG MÃ NGUỒN & CÁC LỖI TỒN ĐỌNG (FORENSIC FINDINGS)

Qua kiểm tra trực tiếp mã nguồn `nhap_diem.php` và cơ sở dữ liệu `edu_radar` trên XAMPP MariaDB 10.4, chúng tôi ghi nhận các vấn đề kỹ thuật nghiêm trọng cần khắc phục:

### 2.1. Lệch pha cấu trúc cột điểm giữa PHP và Database
* **Database live MySQL (`edu_radar.scores`)**: Được hoàn thiện tại Milestone 1 với các cột chuẩn:
  ```text
  id | student_id | subject | tx1 | tx2 | tx3 | tx4 | gk | ck | avg_score | updated_at
  Khóa duy nhất: UNIQUE KEY unique_student_subject (student_id, subject)
  ```
* **Mã nguồn hiện tại của `nhap_diem.php` (Dòng 112–145 và 220–229)**: Vẫn đang sử dụng cấu trúc cũ thời kỳ thử nghiệm:
  ```php
  // Đọc dữ liệu cũ:
  SELECT s.id, s.full_name, s.parent_phone, sc.m1, sc.m2, sc.p15_1, sc.p15_2, sc.t1_1, sc.gk, sc.ck, sc.avg_score ...
  // Ghi dữ liệu cũ:
  UPDATE scores SET m1 = ?, m2 = ?, p15_1 = ?, p15_2 = ?, t1_1 = ?, gk = ?, ck = ?, avg_score = ? ...
  INSERT INTO scores (student_id, subject, m1, m2, p15_1, p15_2, t1_1, gk, ck, avg_score) ...
  ```
* **Hậu quả**: Khi người dùng nhấn nút lưu điểm, câu lệnh SQL lập tức ném ngoại lệ `PDOException: Unknown column 'm1' in 'field list'` dẫn đến hỏng toàn bộ quy trình lưu điểm.

### 2.2. Thiếu an toàn giao dịch (Transaction Safety)
* Hiện tại `nhap_diem.php` xử lý vòng lặp `foreach ($_POST['scores'] as $studentId => $row)` với chế độ autocommit ngầm định.
* Nếu bản ghi thứ $k$ gặp lỗi (lỗi định dạng dữ liệu, mất kết nối cơ sở dữ liệu, v.v.), $k-1$ bản ghi trước đó đã bị ghi nhận trong khi các bản ghi sau bị hủy bỏ, dẫn tới trạng thái dữ liệu không nhất quán (inconsistent state).
* Cần phải bọc toàn bộ khối cập nhật trong một Database Transaction: `beginTransaction()`, kiểm tra toàn vẹn, `commit()`, và `rollBack()` khi có lỗi.

### 2.3. Lỗ hổng kiểm tra học sinh và hiệu năng truy vấn
* Trong vòng lặp lưu điểm hiện tại:
  ```php
  $chkStudent = $pdo->prepare("SELECT 1 FROM students WHERE id = ? AND class_id = ?");
  foreach (...) {
      $chkStudent->execute([$studentId, $selectedClassId]);
      ...
      $check = $pdo->prepare("SELECT id FROM scores WHERE student_id = ? AND subject = ?");
  }
  ```
  Nếu lớp có 45 học sinh, hệ thống thực hiện $45 \times 2 = 90$ truy vấn riêng lẻ.
* **Giải pháp tối ưu**:
  1. Đọc toàn bộ danh sách `id` hợp lệ của lớp bằng 1 truy vấn duy nhất trước vòng lặp: `SELECT id FROM students WHERE class_id = ?`.
  2. Tạo bản đồ tra cứu O(1): `$validStudentMap = array_flip($validIds)`.
  3. Dùng câu lệnh chuẩn `INSERT INTO scores (...) VALUES (...) ON DUPLICATE KEY UPDATE ...` chuẩn bị trước (`prepare`) và thực thi lặp lại, giảm tổng số truy vấn từ hàng trăm xuống chỉ 1 truy vấn tra cứu + 1 prepared statement.

### 2.4. Tính tương thích ngược với Test Suite Milestone 2
* Trong `tests/challenge_m2_rbac.php` (dòng 688 và 718), các bài kiểm thử BOLA đã gửi payload dạng:
  ```php
  'scores' => [ 99999 => ['m1' => '10.0', 'gk' => '10.0', 'ck' => '10.0'] ]
  ```
* Để tránh làm đứt gãy (regression) các test suite M2 đã pass 100%, server-side PHP cần hỗ trợ fallback:
  `$tx1 = $row['tx1'] ?? $row['m1'] ?? null;`
  `$tx2 = $row['tx2'] ?? $row['m2'] ?? null;`
  `$tx3 = $row['tx3'] ?? $row['p15_1'] ?? null;`
  `$tx4 = $row['tx4'] ?? $row['p15_2'] ?? null;`

---

## 3. CÔNG THỨC TOÁN HỌC & QUY TẮC TÍNH ĐIỂM (VNEDU / THÔNG TƯ 22)

### 3.1. Phân loại cột điểm và hệ số quy định
Theo Thông tư 22/2021/TT-BGDĐT quy định về đánh giá học sinh THCS và chuẩn bảng điểm VnEdu:
* **Điểm đánh giá thường xuyên (`ĐĐGtx`)**: Tối đa 4 cột (`tx1, tx2, tx3, tx4`). Bao gồm kiểm tra miệng, kiểm tra 15 phút, đánh giá bài tập/dự án ngắn. **Hệ số 1**.
* **Điểm đánh giá giữa kỳ (`ĐĐGgk` / `gk`)**: 1 cột. **Hệ số 2**.
* **Điểm đánh giá cuối kỳ (`ĐĐGck` / `ck`)**: 1 cột. **Hệ số 3**.
* **Điểm trung bình môn học kỳ (`ĐTBmhk`)**: Làm tròn đến 1 chữ số thập phân (`round(..., 1)`).

### 3.2. Công thức toán học tổng quát
Gọi:
* $T = \{tx_i \mid tx_i \in [0.0, 10.0] \text{ và } tx_i \ne \text{null}\}$, với $i \in \{1, 2, 3, 4\}$.
* $N_{tx} = |T|$ là số lượng cột điểm thường xuyên đã nhập ($0 \le N_{tx} \le 4$).
* $S_{tx} = \sum_{t \in T} t$ là tổng điểm các cột thường xuyên.
* $W_{gk} = (gk \ne \text{null} \text{ và } gk \in [0.0, 10.0]) ? 2 : 0$.
* $W_{ck} = (ck \ne \text{null} \text{ và } ck \in [0.0, 10.0]) ? 3 : 0$.

Tổng hệ số (trọng số mẫu số):
$$W_{total} = N_{tx} \times 1 + W_{gk} + W_{ck}$$

Tổng điểm có trọng số (tử số):
$$S_{total} = S_{tx} + (W_{gk} > 0 ? 2 \times gk : 0) + (W_{ck} > 0 ? 3 \times ck : 0)$$

Điểm trung bình học kỳ:
$$\text{ĐTBmhk} = \begin{cases} 
\text{round}\left(\frac{S_{total}}{W_{total}}, 1\right) & \text{nếu } W_{total} > 0 \\ 
\text{null} & \text{nếu } W_{total} = 0 
\end{cases}$$

#### Chứng minh tính nhất quán giữa công thức đủ và công thức động:
Khi học sinh đã có đầy đủ điểm Giữa kỳ ($gk \ne \text{null}$) và Cuối kỳ ($ck \ne \text{null}$):
* $W_{gk} = 2$, $W_{ck} = 3 \implies W_{total} = N_{tx} + 2 + 3 = N_{tx} + 5$.
* $S_{total} = S_{tx} + 2 \times gk + 3 \times ck$.
* Do đó:
$$\text{ĐTBmhk} = \frac{S_{tx} + 2 \times gk + 3 \times ck}{N_{tx} + 5}$$
Công thức động cho các điểm thành phần trong kỳ chính xác là trường hợp tổng quát của công thức chính thức cuối kỳ, loại bỏ hoàn toàn các trường hợp không xác định.

### 3.3. Các ca kiểm thử mẫu chuẩn xác (Ground Truth Benchmarks)
| Trường hợp | tx1 | tx2 | tx3 | tx4 | gk (HS 2) | ck (HS 3) | Tử số ($S_{total}$) | Mẫu số ($W_{total}$) | ĐTBmhk (`round(..., 1)`) | Ghi chú |
|---|---|---|---|---|---|---|---|---|---|---|
| **Đủ 4 TX + GK + CK** | 8.0 | 7.0 | 6.5 | 6.0 | 5.0 | 5.0 | $27.5 + 10 + 15 = 52.5$ | $4 + 2 + 3 = 9$ | **5.8** | Khớp seed học sinh 1 lớp 6A1 |
| **Đủ 4 TX + GK + CK** | 6.0 | 7.0 | 8.0 | 8.5 | 8.5 | 9.0 | $29.5 + 17 + 27 = 73.5$ | $4 + 2 + 3 = 9$ | **8.2** | Khớp seed học sinh 2 lớp 6A1 |
| **Chỉ có 2 điểm TX** | 8.0 | 7.0 | - | - | - | - | $8.0 + 7.0 = 15.0$ | $2 \times 1 = 2$ | **7.5** | Đầu học kỳ |
| **1 TX + GK (chưa CK)** | 8.0 | - | - | - | 7.0 | - | $8.0 + 14.0 = 22.0$ | $1 + 2 = 3$ | **7.3** ($22/3 = 7.333...$) | Giữa học kỳ |
| **TX + CK (vắng GK)** | 7.0 | 8.0 | - | - | - | 8.0 | $15.0 + 24.0 = 39.0$ | $2 + 3 = 5$ | **7.8** | Khuyết giữa kỳ |
| **Không có điểm nào** | - | - | - | - | - | - | 0 | 0 | **null / '-'** | Đầu năm học |
| **Cột điểm nhận dấu phẩy** | 8,5 | - | - | - | - | - | 8.5 | 1 | **8.5** | Chuẩn hóa `,` thành `.` |

---

## 4. THIẾT KẾ CLIENT-SIDE JAVASCRIPT ENGINE

### 4.1. Nguyên lý vận hành & Trải nghiệm người dùng (UX)
1. **Lắng nghe sự kiện tức thời**: Sử dụng Event Delegation hoặc trực tiếp gắn listener trên các ô nhập `.score-input` với sự kiện `'input'` để tính lại ngay khi người dùng gõ phím, và sự kiện `'change'` để chuẩn hóa định dạng (chuyển dấu phẩy `,` sang dấu chấm `.`).
2. **Kiểm tra biên trực tiếp (Client-Side Validation)**:
   - Điểm hợp lệ: Rỗng (được coi là chưa có điểm) hoặc số thập phân nằm trong đoạn $[0.0, 10.0]$.
   - Điểm không hợp lệ ($< 0$, $> 10$, hoặc chứa ký tự phi số): Thêm class `.is-invalid` (viền đỏ nổi bật `#dc2626`, nền hồng nhạt `#fef2f2`), hiển thị tooltip cảnh báo "Điểm phải từ 0.0 đến 10.0".
3. **Phản hồi thị giác cho ĐTBmhk**:
   - Nếu $\text{ĐTBmhk} < 5.0$: Tô chữ màu đỏ đậm (`#dc2626`, font-weight 800) để giáo viên nhận diện học sinh cần cảnh báo sớm.
   - Nếu $\text{ĐTBmhk} \ge 5.0$: Tô chữ màu xanh đậm / đen chữ số chuẩn (`#1e293b` hoặc `#4338ca`).
   - Nếu chưa có điểm: Hiển thị ký tự gạch ngang `-` (`#94a3b8`).
4. **Theo dõi trạng thái chỉnh sửa (Dirty State)**:
   - Khi có bất kỳ ô điểm nào thay đổi, kích hoạt trạng thái dirty trên nút Lưu: đổi màu viền, hiển thị chấm tròn thông báo "Có thay đổi chưa lưu" nhằm nhắc nhở giáo viên bấm Lưu.
5. **Chặn gửi Form khi còn lỗi**:
   - Khi nhấn nút "Lưu Bảng Điểm", JavaScript duyệt toàn bộ ô nhập. Nếu tồn tại ô có class `.is-invalid`, ngăn chặn submit (`event.preventDefault()`), cuộn tới ô lỗi đầu tiên và hiển thị thông báo alert/toast: *"Vui lòng kiểm tra lại điểm số không hợp lệ (phải từ 0.0 đến 10.0)"*.

### 4.2. Mã nguồn JavaScript chuẩn hóa đề xuất

```javascript
/**
 * EduRadar VnEdu Gradebook Calculator & Interactive Engine
 */
document.addEventListener('DOMContentLoaded', function () {
    const table = document.querySelector('.gradebook-table') || document.querySelector('table');
    if (!table) return;

    const saveBtn = document.getElementById('btn-save-scores') || document.querySelector('button[type="submit"]');
    const form = table.closest('form');

    // Hàm tính điểm trung bình cho 1 hàng học sinh
    function calculateRowAverage(row) {
        const txInputs = [
            row.querySelector('input[data-col="tx1"]'),
            row.querySelector('input[data-col="tx2"]'),
            row.querySelector('input[data-col="tx3"]'),
            row.querySelector('input[data-col="tx4"]')
        ];
        const gkInput = row.querySelector('input[data-col="gk"]');
        const ckInput = row.querySelector('input[data-col="ck"]');
        const avgCell = row.querySelector('.avg-score-cell');

        let sum = 0.0;
        let weight = 0;
        let hasInvalid = false;

        // Xử lý điểm Thường Xuyên (Hệ số 1)
        txInputs.forEach(input => {
            if (!input) return;
            const valStr = input.value.trim().replace(',', '.');
            if (valStr === '') {
                input.classList.remove('is-invalid');
                return;
            }
            const num = parseFloat(valStr);
            if (isNaN(num) || num < 0 || num > 10) {
                input.classList.add('is-invalid');
                hasInvalid = true;
            } else {
                input.classList.remove('is-invalid');
                sum += num * 1.0;
                weight += 1;
            }
        });

        // Xử lý điểm Giữa Kỳ (Hệ số 2)
        if (gkInput) {
            const valStr = gkInput.value.trim().replace(',', '.');
            if (valStr === '') {
                gkInput.classList.remove('is-invalid');
            } else {
                const num = parseFloat(valStr);
                if (isNaN(num) || num < 0 || num > 10) {
                    gkInput.classList.add('is-invalid');
                    hasInvalid = true;
                } else {
                    gkInput.classList.remove('is-invalid');
                    sum += num * 2.0;
                    weight += 2;
                }
            }
        }

        // Xử lý điểm Cuối Kỳ (Hệ số 3)
        if (ckInput) {
            const valStr = ckInput.value.trim().replace(',', '.');
            if (valStr === '') {
                ckInput.classList.remove('is-invalid');
            } else {
                const num = parseFloat(valStr);
                if (isNaN(num) || num < 0 || num > 10) {
                    ckInput.classList.add('is-invalid');
                    hasInvalid = true;
                } else {
                    ckInput.classList.remove('is-invalid');
                    sum += num * 3.0;
                    weight += 3;
                }
            }
        }

        // Cập nhật hiển thị ĐTBmhk
        if (avgCell) {
            if (hasInvalid) {
                avgCell.innerHTML = '<span style="color: #dc2626; font-weight: bold;">Lỗi!</span>';
            } else if (weight > 0) {
                const rawAvg = sum / weight;
                // Chuẩn hóa làm tròn 1 chữ số thập phân an toàn dấu phẩy động
                const roundedAvg = (Math.round((rawAvg + Number.EPSILON) * 10) / 10).toFixed(1);
                const isUnder5 = parseFloat(roundedAvg) < 5.0;
                avgCell.innerHTML = `<b style="font-size: 15px; color: ${isUnder5 ? '#dc2626' : '#3730a3'};">${roundedAvg}</b>`;
            } else {
                avgCell.innerHTML = '<span style="color: #94a3b8; font-weight: 500;">-</span>';
            }
        }
    }

    // Gắn sự kiện cho tất cả các ô điểm
    const scoreInputs = table.querySelectorAll('.score-input');
    scoreInputs.forEach(input => {
        // Sự kiện gõ phím tức thời: tính lại điểm
        input.addEventListener('input', function () {
            const row = this.closest('tr');
            if (row) calculateRowAverage(row);
            if (saveBtn) {
                saveBtn.dataset.dirty = "true";
                saveBtn.classList.add('btn-dirty');
            }
        });

        // Sự kiện rời ô hoặc thay đổi: chuẩn hóa dấu phẩy thành dấu chấm
        input.addEventListener('change', function () {
            let val = this.value.trim();
            if (val.includes(',')) {
                this.value = val.replace(',', '.');
            }
            const row = this.closest('tr');
            if (row) calculateRowAverage(row);
        });
    });

    // Chặn gửi Form khi còn điểm không hợp lệ
    if (form) {
        form.addEventListener('submit', function (e) {
            const invalidInputs = form.querySelectorAll('.score-input.is-invalid');
            if (invalidInputs.length > 0) {
                e.preventDefault();
                alert('Có ' + invalidInputs.length + ' ô điểm không hợp lệ (điểm phải từ 0.0 đến 10.0). Vui lòng kiểm tra lại!');
                invalidInputs[0].focus();
                invalidInputs[0].select();
            }
        });
    }
});
```

---

## 5. THIẾT KẾ SERVER-SIDE PHP PERSISTENCE & TRANSACTION ENGINE

### 5.1. Luồng xử lý an toàn (Execution Pipeline)
Quy trình xử lý `action === 'save_scores'` trên Server:
1. **Xác thực RBAC & BOLA cấp Route**:
   - `require_login();`
   - Kiểm tra vai trò: Nếu không phải `admin`, giáo viên chỉ được thao tác với lớp và môn được phân công (`require_teacher_assignment($realUserId, $selectedClassId, $selectedSubject)`).
   - Kiểm tra IDOR: Nếu giáo viên gửi tham số `teacher_id` khác với session, trả về HTTP 403 ngay lập tức.
2. **Trích xuất danh sách học sinh hợp lệ của lớp (Anti-BOLA Student Whitelist)**:
   - Truy vấn toàn bộ `id` học sinh thuộc `$selectedClassId`:
     `SELECT id FROM students WHERE class_id = ?`
   - Tạo tập hợp kiểm tra O(1): `$validStudentMap = array_flip($validStudentIds)`.
   - Nếu trong `$_POST['scores']` có bất kỳ `student_id` nào không thuộc `$validStudentMap`, bỏ qua hoặc loại bỏ ngay, ngăn chặn triệt để tiêm mã học sinh từ lớp khác.
3. **Mở Transaction an toàn**:
   - `db()->beginTransaction();`
4. **Kiểm tra và chuẩn hóa dữ liệu điểm**:
   - Hỗ trợ cả key mới (`tx1..tx4`) và key cũ (`m1, m2, p15_1, p15_2`) nhằm bảo đảm tương thích ngược 100%.
   - Nếu chuỗi rỗng `""` hoặc `null`: Giá trị lưu cơ sở dữ liệu là `null`.
   - Nếu có giá trị: Chuẩn hóa `,` thành `.`. Kiểm tra `is_numeric()` và khoảng $[0.0, 10.0]$.
   - Nếu phát hiện giá trị ngoài khoảng $[0.0, 10.0]$: Kích hoạt cờ `$hasValidationError = true`, dừng vòng lặp, thực hiện `db()->rollBack()`, và không lưu bất kỳ thay đổi nào.
5. **Tính toán ĐTBmhk trên Server**:
   - Thực hiện chính xác công thức toán học tại Mục 3.
   - Làm tròn 1 chữ số thập phân: `round($avg, 1)`.
6. **Thực thi Upsert vào bảng `scores`**:
   - Sử dụng câu lệnh tối ưu của MariaDB / MySQL:
     ```sql
     INSERT INTO scores (student_id, subject, tx1, tx2, tx3, tx4, gk, ck, avg_score)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
         tx1 = VALUES(tx1),
         tx2 = VALUES(tx2),
         tx3 = VALUES(tx3),
         tx4 = VALUES(tx4),
         gk = VALUES(gk),
         ck = VALUES(ck),
         avg_score = VALUES(avg_score)
     ```
7. **Commit Transaction & Ghi Audit Log**:
   - `db()->commit();`
   - Gọi `log_action($realUserId, $_SESSION['username'], 'Lưu sổ điểm', "Cập nhật bảng điểm môn [$selectedSubject] cho lớp [$curClassName] ($savedCount học sinh).");` sau khi commit thành công.

### 5.2. Mã nguồn PHP hoàn chỉnh đề xuất cho `nhap_diem.php`

```php
// =========================================================================
// XỬ LÝ LƯU ĐIỂM BẢNG VNEDU (CHÍNH THỨC & AN TOÀN TRANSACTION)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_scores') {
    // 1. Kiểm tra quyền hạn (Admin hoặc Giáo viên được phân công)
    require_teacher_assignment($realUserId, $selectedClassId, $selectedSubject);

    $rawScores = $_POST['scores'] ?? [];
    if (!is_array($rawScores) || empty($rawScores)) {
        $msg = "Không có dữ liệu điểm để lưu.";
    } else {
        // 2. Lấy danh sách ID học sinh thuộc lớp này để chống BOLA Cross-Class Injection
        $stmtValid = $pdo->prepare("SELECT id FROM students WHERE class_id = ?");
        $stmtValid->execute([$selectedClassId]);
        $validStudentIds = $stmtValid->fetchAll(PDO::FETCH_COLUMN);
        $validMap = array_flip($validStudentIds);

        // 3. Khởi tạo Transaction
        $pdo->beginTransaction();
        try {
            $savedCount = 0;
            $hasValidationError = false;
            $validationErrorMsg = '';

            // Chuẩn bị câu lệnh Upsert một lần duy nhất
            $upsertStmt = $pdo->prepare("
                INSERT INTO scores (student_id, subject, tx1, tx2, tx3, tx4, gk, ck, avg_score)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    tx1 = VALUES(tx1),
                    tx2 = VALUES(tx2),
                    tx3 = VALUES(tx3),
                    tx4 = VALUES(tx4),
                    gk = VALUES(gk),
                    ck = VALUES(ck),
                    avg_score = VALUES(avg_score)
            ");

            // Hàm kiểm tra và chuyển đổi điểm số
            $parseScore = function ($val) use (&$hasValidationError, &$validationErrorMsg) {
                if ($val === null || $val === '') {
                    return null;
                }
                $clean = str_replace(',', '.', trim((string)$val));
                if (!is_numeric($clean)) {
                    $hasValidationError = true;
                    $validationErrorMsg = "Điểm số không hợp lệ: '{$val}'.";
                    return null;
                }
                $num = (float)$clean;
                if ($num < 0.0 || $num > 10.0) {
                    $hasValidationError = true;
                    $validationErrorMsg = "Điểm số {$num} nằm ngoài thang điểm hợp lệ (0.0 - 10.0).";
                    return null;
                }
                return round($num, 2);
            };

            foreach ($rawScores as $studentId => $row) {
                $studentId = (int)$studentId;

                // BẢO VỆ BOLA: Bỏ qua học sinh không thuộc lớp được chọn
                if (!isset($validMap[$studentId])) {
                    continue;
                }

                // Hỗ trợ cả key mới (tx1..tx4) và fallback key cũ (m1..p15_2)
                $tx1 = $parseScore($row['tx1'] ?? $row['m1'] ?? null);
                $tx2 = $parseScore($row['tx2'] ?? $row['m2'] ?? null);
                $tx3 = $parseScore($row['tx3'] ?? $row['p15_1'] ?? null);
                $tx4 = $parseScore($row['tx4'] ?? $row['p15_2'] ?? null);
                $gk  = $parseScore($row['gk'] ?? null);
                $ck  = $parseScore($row['ck'] ?? null);

                if ($hasValidationError) {
                    break;
                }

                // Tính toán ĐTBmhk theo chuẩn Thông tư 22
                $sum = 0.0;
                $weight = 0;
                foreach ([$tx1, $tx2, $tx3, $tx4] as $tx) {
                    if ($tx !== null) {
                        $sum += $tx;
                        $weight += 1;
                    }
                }
                if ($gk !== null) {
                    $sum += $gk * 2.0;
                    $weight += 2;
                }
                if ($ck !== null) {
                    $sum += $ck * 3.0;
                    $weight += 3;
                }

                $avg = $weight > 0 ? round($sum / $weight, 1) : null;

                // Thực thi ghi dữ liệu
                $upsertStmt->execute([
                    $studentId,
                    $selectedSubject,
                    $tx1,
                    $tx2,
                    $tx3,
                    $tx4,
                    $gk,
                    $ck,
                    $avg
                ]);
                $savedCount++;
            }

            if ($hasValidationError) {
                $pdo->rollBack();
                $errorMsg = $validationErrorMsg;
            } else {
                $pdo->commit();
                // 4. Ghi nhận nhật ký kiểm toán sau khi Transaction thành công
                log_action(
                    $realUserId,
                    $_SESSION['username'] ?? 'Giáo viên',
                    'Lưu sổ điểm',
                    "Cập nhật bảng điểm môn [$selectedSubject] cho lớp [$curClassName] ($savedCount học sinh)."
                );
                $msg = "Đã lưu thành công điểm môn {$selectedSubject} cho lớp {$curClassName} ({$savedCount} học sinh)!";
            }
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Lỗi lưu điểm sổ điểm: " . $e->getMessage());
            $errorMsg = "Có lỗi xảy ra khi lưu bảng điểm vào cơ sở dữ liệu: " . $e->getMessage();
        }
    }
}
```

---

## 6. THIẾT KẾ TRUY VẤN DỮ LIỆU HIỂN THỊ (STUDENT ROSTER & SCORES QUERY)

Để bảng điểm hiển thị đầy đủ thông tin chuẩn VnEdu (STT, Mã HS, Họ và tên, Ngày sinh, TX1..TX4, GK, CK, ĐTBmhk), truy vấn đọc dữ liệu tại dòng 220 của `nhap_diem.php` cần được điều chỉnh đồng bộ:

```php
// Lấy danh sách học sinh và điểm số hiện có của lớp
$studentList = [];
if ($selectedClassId > 0) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_code, s.full_name, s.birthday, s.gender, s.phone,
               sc.tx1, sc.tx2, sc.tx3, sc.tx4, sc.gk, sc.ck, sc.avg_score
        FROM students s
        LEFT JOIN scores sc ON s.id = sc.student_id AND sc.subject = ?
        WHERE s.class_id = ?
        ORDER BY s.student_code ASC, s.id ASC
    ");
    $stmt->execute([$selectedSubject, $selectedClassId]);
    $studentList = $stmt->fetchAll();
}
```

---

## 7. MA TRẬN BẢO MẬT RBAC, BOLA & IDOR

| Kịch bản đe dọa (Threat Vector) | Cơ chế phòng thủ thiết kế | Kết quả mong đợi |
|---|---|---|
| **Giáo viên chưa đăng nhập gửi POST `action=save_scores`** | `require_login()` kiểm tra session | HTTP 302 chuyển hướng về `login.php`, 0 thay đổi DB |
| **Giáo viên dạy Toán gửi POST lưu điểm môn Ngữ Văn** | `require_teacher_assignment($teacherId, $classId, 'Ngữ Văn')` | HTTP 403 Forbidden, ghi log `Truy cập trái phép bị chặn`, 0 thay đổi DB |
| **Giáo viên gửi POST tới lớp chưa được phân công** | `require_teacher_assignment` xác minh bảng `teacher_classes` | HTTP 403 Forbidden, ghi log kiểm toán, 0 thay đổi DB |
| **Giáo viên giả mạo `?teacher_id=...` hoặc `$_POST['teacher_id']`** | Kiểm tra `$reqTeacherId !== $realUserId` | HTTP 403 Forbidden, ghi log kiểm toán IDOR, 0 thay đổi DB |
| **Giáo viên tiêm `student_id = 99999` (học sinh không tồn tại)** | Kiểm tra `$validMap[$studentId]` trước khi ghi | Bỏ qua bản ghi 99999, không thêm hàng nào vào `scores` |
| **Giáo viên tiêm `student_id` của học sinh thuộc lớp khác** | Kiểm tra `students.class_id = $selectedClassId` | Bỏ qua bản ghi ngoại lai, không thể ghi điểm chéo lớp |
| **Nhập điểm âm (`-1.5`) hoặc quá thang (`12.0`)** | Validation kiểm tra $0 \le x \le 10$, `rollBack()` toàn bộ | Transaction hủy bỏ, hiển thị thông báo lỗi, 0 thay đổi DB |
| **Quản trị viên (Admin) thanh tra và lưu điểm** | `$_SESSION['role'] === 'admin'` được miễn trừ kiểm tra phân công | HTTP 200 OK, lưu thành công, ghi log kiểm toán minh bạch |

---

## 8. KẾ HOẠCH TÍCH HỢP & VERIFICATION

1. **Phối hợp với `explorer_m4_1` (UI & Keyboard Navigation)**:
   - Các ô input sẽ mang class `.score-input` và thuộc tính `data-col="tx1"`, `data-col="tx2"`, `data-col="tx3"`, `data-col="tx4"`, `data-col="gk"`, `data-col="ck"`.
   - Ô ĐTBmhk mang class `.avg-score-cell`.
   - Input có thuộc tính `type="text"` và `inputmode="decimal"` để hỗ trợ cả bàn phím số và nhập nhanh dấu phẩy.
2. **Phối hợp với `explorer_m4_3` (Excel Import/Export & Test Suite)**:
   - Phần import file CSV/Excel ở cuối trang cũng sẽ sử dụng cùng công thức tính điểm và ghi vào các cột `tx1..tx4, gk, ck, avg_score`.
   - Test suite `tests/verify_m4.php` sẽ kiểm thử:
     - Khả năng lưu điểm hợp lệ và cập nhật `avg_score` chính xác.
     - Khả năng từ chối điểm ngoài khoảng $[0.0, 10.0]$.
     - Khả năng chặn tiêm học sinh ngoại lai và từ chối giáo viên không được phân công (HTTP 403).
     - Ghi nhận `Lưu sổ điểm` vào `audit_logs`.
