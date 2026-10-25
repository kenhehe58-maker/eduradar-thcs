# Báo Cáo Phân Tích Kỹ Thuật: Early-Warning Radar Diagnostic Engine (`index.php`)
**Mã tài liệu**: `M5-ANALYSIS-RADAR-ENGINE`  
**Tác giả**: `explorer_m5_1` (Teamwork Explorer M5)  
**Ngày thực hiện**: 2026-10-17  
**Mục tiêu**: Điều tra toàn diện hiện trạng `index.php`, thiết kế chi tiết động cơ chẩn đoán rủi ro 3 vùng (Nguy cấp, Cần chú ý, Ổn định), hệ thống thẻ thống kê tỷ lệ phần trăm, bộ lọc tương tác client-side, cấu trúc bảng Radar đầy đủ các cột điểm VnEdu, và phân quyền RBAC nghiêm ngặt cho Giáo viên / Admin.

---

## 1. Tóm Tắt Phát Hiện Cốt Lõi (Executive Summary)

1. **Hiện trạng `index.php` bị lệch lạc nghiêm trọng so với cơ sở dữ liệu và yêu cầu nghiệp vụ**:
   - Truy vấn SQL trong `index.php` cũ vẫn dùng tên cột cũ (`m1, m2, p15_1, p15_2, t1_1, gk, ck`), trong khi cơ sở dữ liệu `scores` đã được chuẩn hóa theo Thông tư 22 (`tx1, tx2, tx3, tx4, gk, ck, avg_score`).
   - `index.php` cũ thực hiện `LEFT JOIN scores sc ON s.id = sc.student_id` mà **không lọc theo môn học (`subject`)**. Vì mỗi học sinh có nhiều dòng điểm cho các môn khác nhau (`UNIQUE(student_id, subject)`), truy vấn cũ gây ra tích Đề-các (Cartesian product), lặp học sinh hoặc hiển thị sai điểm số của môn khác.
   - Vẫn còn tồn tại nút "📋 Copy Tin Nhắn Zalo", hàm `buildFeedback()`, và phần tử thông báo `#toast` sao chép Zalo, vi phạm trực tiếp yêu cầu R4 và Feature #16.
   - Hàm `evaluateRisk()` cũ sử dụng hằng số chưa khai báo `PASS_SCORE` (gây lỗi PHP Notice/Warning) và không áp dụng đúng 3 vùng chẩn đoán theo đặc tả M5.
2. **Thiết kế Động cơ Chẩn đoán 3 Vùng (Radar Diagnostic Engine)**:
   - 🔴 **Nguy cấp (Critical)**: `avg_score < 3.5` HOẶC `ck < 3.0` HOẶC `(gk < 5.0 && ck < 5.0)`. Gắn nhãn lý do chẩn đoán tiếng Việt rõ ràng.
   - 🟡 **Cần chú ý (Warning / At Risk)**: `3.5 <= avg_score < 5.0` HOẶC sa sút mạnh cuối kỳ `(ck < gk - 1.5)`. Bắt kịp đà suy giảm học lực trước khi học sinh rơi vào nguy cấp.
   - 🟢 **Ổn định (Stable)**: `avg_score >= 5.0` và không có cờ cảnh báo nguy cấp/sa sút.
3. **Thống kê phần trăm & Lọc tương tác thời gian thực**:
   - 4 thẻ thống kê đầu trang: Tổng số học sinh (100%), 🔴 Nguy cấp (số lượng + %), 🟡 Cần chú ý (số lượng + %), 🟢 Ổn định (số lượng + %).
   - 4 nút lọc (All, Red, Yellow, Green) kết hợp thanh tìm kiếm thông minh, lọc tức thì trên DOM bảng học sinh mà không cần tải lại trang.
4. **Bảng dữ liệu Radar chuẩn VnEdu & Phân quyền RBAC**:
   - Hiển thị đầy đủ: Mã HS, Họ và tên, Ngày sinh, Lớp & Môn, Điểm thành phần (`tx1..tx4, gk, ck, avg_score`), Huy hiệu trạng thái, Thẻ lý do cảnh báo, và nút hành động trực tiếp **"Vào sổ điểm"** (`nhap_diem.php?class_id=X&subject=Y`).
   - Phân quyền giáo viên chỉ hiển thị học sinh và điểm môn học thuộc các lớp được phân công trong `teacher_classes`. Tài khoản Admin có quyền xem toàn trường và chuyển đổi bộ lọc lớp/môn linh hoạt.

---

## 2. Kiểm Toán Hiện Trạng Mã Nguồn (Code Audit)

### 2.1. Phân Tích Lỗi Trong `index.php` Hiện Tại

| Vị trí mã nguồn | Hiện trạng phát hiện | Hậu quả / Lỗ hổng | Hướng khắc phục chuẩn M5 |
|---|---|---|---|
| `index.php:74-82` | `SELECT s.id, s.full_name... sc.m1, sc.m2, sc.p15_1... LEFT JOIN scores sc ON s.id = sc.student_id` | Lỗi cột SQL không tồn tại; thiếu điều kiện `sc.subject = tc.subject` gây nhân bản dòng học sinh khi học sinh có điểm nhiều môn. | Sửa truy vấn: `JOIN teacher_classes tc ... LEFT JOIN scores sc ON sc.student_id = s.id AND sc.subject = tc.subject` với các cột chuẩn `tx1, tx2, tx3, tx4, gk, ck, avg_score`. |
| `index.php:14-21` | `WHERE s.class_id IN (SELECT class_id FROM teacher_classes WHERE teacher_id = ?)` | Giáo viên A dạy Toán lớp 6A1 có thể thấy học sinh của 6A1 nhưng bị ghép với điểm môn Ngữ Văn của Giáo viên B! | Lọc theo cặp `(class_id, subject)` chính xác từ `teacher_classes` của giáo viên đăng nhập. |
| `index.php:26-54` | `function evaluateRisk(?float $avg, array $scores)` gọi `PASS_SCORE` chưa định nghĩa; kiểm tra sa sút bằng `$scores[$n-1] < $scores[$n-2]` | Lỗi PHP Notice `Undefined constant PASS_SCORE`; không phân biệt điểm giữa kỳ (`gk`) và cuối kỳ (`ck`); thiếu điều kiện `avg < 3.5`, `ck < 3.0`, `gk < 5 && ck < 5`. | Viết lại thành `evaluateRiskZone(?float $avg, ?float $gk, ?float $ck, array $tx)` với quy tắc toán học chuẩn M5. |
| `index.php:56-72` | `function buildFeedback(array $s): string` | Tạo văn bản tin nhắn Zalo gửi phụ huynh. | **Xóa bỏ hoàn toàn** theo yêu cầu R4 & Feature #16. |
| `index.php:208-212` | `<button class="btn copy-btn" data-text="...">📋 Copy Tin Nhắn Zalo</button>` | Nút Zalo người dùng yêu cầu bỏ. | **Thay thế bằng nút**: `<a href="nhap_diem.php?class_id=X&subject=Y" class="btn primary">📝 Vào sổ điểm</a>`. |
| `index.php:221` | `<div id="toast" class="toast">Đã sao chép tin nhắn Zalo!</div>` | Phần tử DOM thừa phục vụ Zalo. | **Xóa bỏ hoàn toàn**. |
| `index.php:148-166` | Thẻ thống kê chỉ hiển thị số lượng nguyên (ví dụ `<b>2</b>`), không có phần trăm | Không đáp ứng yêu cầu R4/M5 ("with percentages"). | Thêm tính toán tỷ lệ %: `number_format(($count / $total) * 100, 1) . '%'`. |
| `assets/js/main.js:25-60` | Khối sự kiện clipboard Zalo và `actionModal` | Mã JavaScript chết (dead code) không sử dụng. | Loại bỏ hoàn toàn khối Zalo và modal trong `main.js`. |

---

## 3. Thiết Kế Động Cơ Chẩn Đoán (Diagnostic Radar Engine)

### 3.1. Đặc Tả Toán Học & Thứ Tự Ưu Tiên Chẩn Đoán

Hệ thống đánh giá theo **nguyên tắc phân tầng nghiêm ngặt (Strict Waterfall Priority)**:
1. **Kiểm tra mức Nguy cấp (Critical) trước tiên**. Nếu bất kỳ điều kiện nào của Nguy cấp được thỏa mãn, học sinh lập tức được xếp vào Vùng Đỏ, không xét tiếp Vùng Vàng.
2. **Kiểm tra mức Cần chú ý (Warning / At Risk)**. Nếu không thuộc Vùng Đỏ, kiểm tra các dấu hiệu đe dọa hoặc đà sa sút.
3. **Mức Ổn định (Stable)**. Dành cho các học sinh duy trì điểm trung bình từ 5.0 trở lên và không có dấu hiệu sa sút.
4. **Học sinh chưa có điểm**: Phân loại an toàn, không báo động giả.

```
                    ┌───────────────────────────────┐
                    │  Dữ liệu học sinh & điểm số   │
                    │  (tx1..tx4, gk, ck, avg_score)│
                    └───────────────┬───────────────┘
                                    │
                                    ▼
                     [ avg_score < 3.5 ]
                             HOẶC
                        [ ck < 3.0 ]
                             HOẶC
                   [ gk < 5.0 && ck < 5.0 ]
                                    │
                  ┌─────────────────┴─────────────────┐
                 ĐÚNG                                SAI
                  │                                   │
                  ▼                                   ▼
          🔴 VÙNG NGUY CẤP                 [ 3.5 <= avg_score < 5.0 ]
          - ĐTB < 3.5                                HOẶC
          - Điểm CK < 3.0                      [ ck < gk - 1.5 ]
          - Hỏng cả GK & CK                           │
                                      ┌───────────────┴───────────────┐
                                     ĐÚNG                            SAI
                                      │                               │
                                      ▼                               ▼
                             🟡 VÙNG CẦN CHÚ Ý                🟢 VÙNG ỔN ĐỊNH
                             - ĐTB dưới chuẩn (3.5-4.9)      - ĐTB >= 5.0
                             - Sa sút mạnh thi cuối kỳ       - Điểm số an toàn
```

### 3.2. Bảng Tiêu Chí Chi Tiết & Nhãn Tiếng Việt

| Vùng cảnh báo | Điều kiện Logic | Nhãn hiển thị (`label`) | Thẻ lý do chẩn đoán tiếng Việt (`reason_tag`) | Hành động sư phạm khuyến nghị |
|---|---|---|---|---|
| 🔴 **Nguy cấp** *(Critical)* | `$avg !== null && $avg < 3.5` | Nguy cấp | `Điểm TB dưới 3.5 (X.X)` | Can thiệp học tập khẩn cấp; phụ đạo kiến thức cốt lõi. |
| 🔴 **Nguy cấp** *(Critical)* | `$ck !== null && $ck < 3.0` | Nguy cấp | `Thi cuối kỳ dưới 3.0 (X.X)` | Ôn tập lại toàn bộ kiến thức trọng tâm học kỳ. |
| 🔴 **Nguy cấp** *(Critical)* | `$gk !== null && $ck !== null && $gk < 5.0 && $ck < 5.0` | Nguy cấp | `Hỏng cả GK (X.X) và CK (X.X)` | Rà soát phương pháp học tập; có nguy cơ ở lại lớp. |
| 🟡 **Cần chú ý** *(Warning)* | `$avg !== null && $avg >= 3.5 && $avg < 5.0` | Cần chú ý | `Điểm TB dưới chuẩn (X.X)` | Tăng cường bài tập rèn luyện nâng ĐTB lên trên 5.0. |
| 🟡 **Cần chú ý** *(Warning)* | `$gk !== null && $ck !== null && round($gk - $ck, 1) > 1.5` | Cần chú ý | `Sa sút cuối kỳ (CK X.X < GK X.X - 1.5)` | Động viên tâm lý, tìm nguyên nhân sa sút bất thường ở bài thi CK. |
| 🟢 **Ổn định** *(Stable)* | `$avg !== null && $avg >= 8.0` | Ổn định | `Học lực Giỏi, kết quả xuất sắc (X.X)` | Bồi dưỡng học sinh giỏi, phát huy năng lực. |
| 🟢 **Ổn định** *(Stable)* | `$avg !== null && $avg >= 6.5 && $avg < 8.0` | Ổn định | `Học lực Khá, duy trì tốt (X.X)` | Tiếp tục duy trì phong độ học tập. |
| 🟢 **Ổn định** *(Stable)* | `$avg !== null && $avg >= 5.0 && $avg < 6.5` | Ổn định | `Đạt chuẩn, ổn định (X.X)` | Củng cố các phần kiến thức còn thiếu sót. |
| 🟢 **Ổn định** *(Chưa điểm)* | `$avg === null` | Ổn định | `Chưa đủ dữ liệu điểm số` | Nhắc giáo viên bộ môn hoàn thiện sổ điểm. |

### 3.3. Xử Lý Sai Số Dấu Phẩy Động (Floating-Point Precision)
Trong PHP, phép so sánh `$ck < $gk - 1.5` có thể gặp hiện tượng làm tròn nhị phân (e.g. `8.0 - 6.5 = 1.4999999999999996`).  
Để đảm bảo chính xác tuyệt đối theo thang điểm 0.1 của Bộ Giáo Dục:
```php
$drop = round((float)$gk - (float)$ck, 1);
$isDownwardTrend = ($drop > 1.5);
```
Cách xử lý này đảm bảo:
- Nếu GK = 8.0 và CK = 6.5: độ giảm là 1.5 -> KHÔNG coi là sa sút mạnh (> 1.5).
- Nếu GK = 8.0 và CK = 6.4: độ giảm là 1.6 (> 1.5) -> Kích hoạt cảnh báo Sa sút cuối kỳ.

---

## 4. Thiết Kế Truy Vấn SQL & Cơ Chế Phân Quyền (RBAC)

### 4.1. Ma Trận Quyền Hạn Truy Cập `index.php`

| Vai trò | Phạm vi lớp học xem được | Phạm vi môn học xem được | Thao tác trên giao diện |
|---|---|---|---|
| **Giáo viên (`teacher`)** | Chỉ các lớp được phân công trong `teacher_classes`. | Chỉ các môn được phân công giảng dạy tại lớp đó. | Xem danh sách học sinh; Lọc theo lớp/môn trong danh mục phân công; Nhấn "Vào sổ điểm" dẫn trực tiếp tới `nhap_diem.php?class_id=X&subject=Y`. |
| **Quản trị viên (`admin`)** | Toàn bộ 36 lớp trong trường (Khối 6-9). | Toàn bộ các môn học trong trường. | Chuyển đổi linh hoạt giữa các lớp và môn học; Kiểm tra toàn bộ cảnh báo học tập của nhà trường. |
| **Khách chưa đăng nhập** | Không có quyền. | Không có quyền. | Chuyển hướng ngay lập tức về `login.php` (HTTP 302). |

### 4.2. Truy Vấn Dành Cho Giáo Viên (Teacher Query)

```sql
SELECT 
    s.id AS student_id,
    s.class_id,
    s.student_code,
    s.full_name,
    s.birthday,
    s.phone,
    c.name AS class_name,
    tc.subject,
    tc.teacher_id,
    sc.tx1, sc.tx2, sc.tx3, sc.tx4, sc.gk, sc.ck, sc.avg_score
FROM teacher_classes tc
JOIN classes c ON tc.class_id = c.id
JOIN students s ON s.class_id = c.id
LEFT JOIN scores sc ON sc.student_id = s.id AND sc.subject = tc.subject
WHERE tc.teacher_id = :teacher_id
  AND (:class_id = 0 OR tc.class_id = :class_id)
  AND (:subject = 'all' OR tc.subject = :subject)
ORDER BY c.grade ASC, c.name ASC, s.full_name ASC;
```

**Ưu điểm kiến trúc**:
1. Khóa cứng điều kiện `tc.teacher_id = :teacher_id` lấy từ session `$_SESSION['user_id']`, loại bỏ hoàn toàn nguy cơ rò rỉ dữ liệu (BOLA/IDOR).
2. `LEFT JOIN scores` sử dụng đồng thời cả hai khóa `student_id = s.id AND subject = tc.subject`, đảm bảo không bao giờ bị nhân đôi dòng dữ liệu học sinh.
3. Học sinh mới thêm vào lớp chưa có điểm vẫn xuất hiện trong danh sách với các trường điểm là `NULL`.

### 4.3. Truy Vấn Dành Cho Admin (Administrator Query)

```sql
SELECT 
    s.id AS student_id,
    s.class_id,
    s.student_code,
    s.full_name,
    s.birthday,
    s.phone,
    c.name AS class_name,
    tc.subject,
    sc.tx1, sc.tx2, sc.tx3, sc.tx4, sc.gk, sc.ck, sc.avg_score
FROM teacher_classes tc
JOIN classes c ON tc.class_id = c.id
JOIN students s ON s.class_id = c.id
LEFT JOIN scores sc ON sc.student_id = s.id AND sc.subject = tc.subject
WHERE (:class_id = 0 OR tc.class_id = :class_id)
  AND (:subject = 'all' OR tc.subject = :subject)
ORDER BY c.grade ASC, c.name ASC, s.full_name ASC;
```

Admin có thể tùy chọn:
- "Tất cả các lớp" + "Tất cả môn": Xem tổng quan radar toàn trường.
- Chọn cụ thể Lớp 6A1 + Môn Toán: Xem radar của riêng lớp 6A1 môn Toán.

---

## 5. Thiết Kế Giao Diện & Tương Tác (UI / UX Design)

### 5.1. Bố Cục Thẻ Thống Kê (Stat Counter Summary Cards)

4 thẻ thống kê đầu trang thiết kế theo chuẩn Stitch UI / SaaS Dashboard:

```html
<section class="stats">
    <!-- THẺ 1: TỔNG SỐ HỌC SINH -->
    <div class="card stat-card total">
        <div class="card-header-row">
            <span class="card-title">👥 Tổng số học sinh</span>
            <span class="card-pct badge" style="background: #e0e7ff; color: #3730a3;">100%</span>
        </div>
        <b><?= $stats['total'] ?></b>
        <span class="card-sub">Toàn bộ hồ sơ đang theo dõi</span>
    </div>

    <!-- THẺ 2: 🔴 NGUY CẤP -->
    <div class="card stat-card red">
        <div class="card-header-row">
            <span class="card-title">🚨 Nguy cấp (Điểm sa sút)</span>
            <span class="card-pct badge red"><?= number_format($stats['red_pct'], 1) ?>%</span>
        </div>
        <b><?= $stats['red'] ?></b>
        <span class="card-sub">Cần can thiệp khẩn cấp</span>
    </div>

    <!-- THẺ 3: 🟡 CẦN CHÚ Ý -->
    <div class="card stat-card yellow">
        <div class="card-header-row">
            <span class="card-title">⚠️ Cần chú ý (Dưới chuẩn/Sa sút)</span>
            <span class="card-pct badge yellow"><?= number_format($stats['yellow_pct'], 1) ?>%</span>
        </div>
        <b><?= $stats['yellow'] ?></b>
        <span class="card-sub">Nguy cơ giảm sút học lực</span>
    </div>

    <!-- THẺ 4: 🟢 ỔN ĐỊNH -->
    <div class="card stat-card green">
        <div class="card-header-row">
            <span class="card-title">✅ Ổn định / Tiến bộ</span>
            <span class="card-pct badge green"><?= number_format($stats['green_pct'], 1) ?>%</span>
        </div>
        <b><?= $stats['green'] ?></b>
        <span class="card-sub">Học tập tốt & duy trì chuẩn</span>
    </div>
</section>
```

### 5.2. Thanh Bộ Lọc Tương Tác & Tìm Kiếm Tức Thì (Client-Side Filter Chips)

```html
<div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h2 style="font-size: 20px; font-weight: 800; color: var(--text-heading); margin-bottom: 4px;">Danh Sách Học Sinh & Phân Tích Rủi Ro</h2>
        <p class="muted small" style="margin: 0;">Lọc theo nhóm nguy cơ hoặc tìm kiếm theo tên, mã học sinh</p>
    </div>
    
    <div class="filters" style="display: flex; gap: 8px; flex-wrap: wrap;">
        <button type="button" class="chip active" data-filter="all">
            <span>Tất cả</span>
            <span class="chip-count"><?= $stats['total'] ?></span>
        </button>
        <button type="button" class="chip chip-red" data-filter="red">
            <span>🔴 Nguy cấp</span>
            <span class="chip-count"><?= $stats['red'] ?></span>
            <span class="chip-pct">(<?= number_format($stats['red_pct'], 1) ?>%)</span>
        </button>
        <button type="button" class="chip chip-yellow" data-filter="yellow">
            <span>🟡 Cần chú ý</span>
            <span class="chip-count"><?= $stats['yellow'] ?></span>
            <span class="chip-pct">(<?= number_format($stats['yellow_pct'], 1) ?>%)</span>
        </button>
        <button type="button" class="chip chip-green" data-filter="green">
            <span>🟢 Ổn định</span>
            <span class="chip-count"><?= $stats['green'] ?></span>
            <span class="chip-pct">(<?= number_format($stats['green_pct'], 1) ?>%)</span>
        </button>
    </div>
</div>
```

### 5.3. Cấu Trúc Bảng Radar Điểm Số Chi Tiết (Radar Table)

Bảng Radar bao gồm 10 cột nghiệp vụ rõ ràng:

1. **STT**: Thứ tự dòng (1, 2, 3...)
2. **Mã HS**: Mã định danh (font JetBrains Mono, ví dụ `HS6A101`)
3. **Họ và Tên**: Tên học sinh in đậm kèm số điện thoại liên hệ phụ huynh
4. **Lớp & Môn**: Huy hiệu Lớp (`Lớp 6A1`) và huy hiệu Môn (`Môn Toán`)
5. **Ngày sinh**: Định dạng `dd/mm/yyyy`
6. **ĐĐG Thường xuyên (TX1..TX4)**: Điểm kiểm tra thường xuyên
7. **Giữa kỳ (GK)**: Điểm giữa kỳ (Hệ số 2)
8. **Cuối kỳ (CK)**: Điểm cuối kỳ (Hệ số 3)
9. **ĐTB Môn (ĐTBmhk)**: Điểm trung bình có màu sắc phân cấp rực rỡ
10. **Trạng thái**: Badge 🔴 Nguy cấp / 🟡 Cần chú ý / 🟢 Ổn định
11. **Nguyên nhân chẩn đoán**: Thẻ lý do chi tiết (`Điểm TB dưới 3.5`, `Thi cuối kỳ < 3.0`...)
12. **Hành động**: Nút trực tiếp **"Vào sổ điểm"** dẫn tới `nhap_diem.php?class_id=X&subject=Y`

```html
<table class="radar-table" id="radarTable">
    <thead>
        <tr>
            <th style="width: 45px;">STT</th>
            <th style="width: 90px;">Mã HS</th>
            <th style="min-width: 170px;">Họ và Tên Học Sinh</th>
            <th style="min-width: 130px;">Lớp & Môn</th>
            <th style="width: 95px;">Ngày Sinh</th>
            <th style="width: 140px; text-align: center;">ĐĐGtx (1-4)</th>
            <th style="width: 65px; text-align: center;">GK</th>
            <th style="width: 65px; text-align: center;">CK</th>
            <th style="width: 80px; text-align: center;">ĐTBmhk</th>
            <th style="width: 120px;">Trạng Thái</th>
            <th style="min-width: 220px;">Nguyên Nhân Chẩn Đoán</th>
            <th style="width: 120px; text-align: center;">Hành Động</th>
        </tr>
    </thead>
    <tbody>
        <!-- Các dòng học sinh render tại đây -->
    </tbody>
</table>
```

---

## 6. Đề Xuất Mã Nguồn Cụ Thể (Proposed Implementation Snippets)

### 6.1. Hàm `evaluateRiskZone()` Đặt Tại `index.php`

```php
/**
 * Chẩn đoán mức độ rủi ro học tập của học sinh theo chuẩn Thông tư 22 & Radar Cảnh Báo
 *
 * @param float|null $avg Điểm trung bình môn học kỳ (ĐTBmhk)
 * @param float|null $gk  Điểm kiểm tra giữa kỳ (ĐĐGgk)
 * @param float|null $ck  Điểm kiểm tra cuối kỳ (ĐĐGck)
 * @param array      $txScores Mảng điểm thường xuyên (tx1..tx4)
 * @return array
 */
function evaluateRiskZone(?float $avg, ?float $gk, ?float $ck, array $txScores = []): array
{
    $reasons = [];

    // 1. VÙNG 🔴 NGUY CẤP (CRITICAL ZONE)
    $isCritical = false;

    // Tiêu chí C1: Điểm TB < 3.5
    if ($avg !== null && $avg < 3.5) {
        $isCritical = true;
        $reasons[] = 'Điểm TB dưới 3.5 (' . number_format($avg, 1) . ')';
    }

    // Tiêu chí C2: Điểm thi cuối kỳ < 3.0
    if ($ck !== null && $ck < 3.0) {
        $isCritical = true;
        $reasons[] = 'Thi cuối kỳ dưới 3.0 (' . number_format($ck, 1) . ')';
    }

    // Tiêu chí C3: Hỏng cả điểm giữa kỳ và cuối kỳ (gk < 5.0 && ck < 5.0)
    if ($gk !== null && $ck !== null && $gk < 5.0 && $ck < 5.0) {
        $isCritical = true;
        $reasons[] = 'Hỏng cả GK (' . number_format($gk, 1) . ') và CK (' . number_format($ck, 1) . ')';
    }

    if ($isCritical) {
        return [
            'level' => 'red',
            'label' => 'Nguy cấp',
            'reasons' => $reasons,
        ];
    }

    // 2. VÙNG 🟡 CẦN CHÚ Ý (WARNING / AT RISK ZONE)
    $isWarning = false;

    // Tiêu chí W1: Điểm TB từ 3.5 đến dưới 5.0
    if ($avg !== null && $avg >= 3.5 && $avg < 5.0) {
        $isWarning = true;
        $reasons[] = 'Điểm TB dưới chuẩn (' . number_format($avg, 1) . ')';
    }

    // Tiêu chí W2: Sa sút mạnh ở kỳ thi cuối kỳ (ck < gk - 1.5)
    if ($gk !== null && $ck !== null && round((float)$gk - (float)$ck, 1) > 1.5) {
        $isWarning = true;
        $drop = round((float)$gk - (float)$ck, 1);
        $reasons[] = 'Sa sút cuối kỳ (CK ' . number_format($ck, 1) . ' < GK ' . number_format($gk, 1) . ' - 1.5, giảm ' . number_format($drop, 1) . ' đ)';
    }

    if ($isWarning) {
        return [
            'level' => 'yellow',
            'label' => 'Cần chú ý',
            'reasons' => $reasons,
        ];
    }

    // 3. VÙNG 🟢 ỔN ĐỊNH (STABLE ZONE)
    if ($avg !== null && $avg >= 5.0) {
        if ($avg >= 8.0) {
            $reasons[] = 'Học lực Giỏi, kết quả xuất sắc (' . number_format($avg, 1) . ')';
        } elseif ($avg >= 6.5) {
            $reasons[] = 'Học lực Khá, duy trì tốt (' . number_format($avg, 1) . ')';
        } else {
            $reasons[] = 'Đạt chuẩn, ổn định (' . number_format($avg, 1) . ')';
        }
    } else {
        $reasons[] = 'Chưa đủ dữ liệu điểm số';
    }

    return [
        'level' => 'green',
        'label' => 'Ổn định',
        'reasons' => $reasons,
    ];
}
```

### 6.2. Đoạn Mã JavaScript Lọc Nhanh Tương Tác (Instant Filter & Search)

```javascript
document.addEventListener('DOMContentLoaded', function () {
    const rows = document.querySelectorAll('#radarTable tbody tr.radar-row');
    const chips = document.querySelectorAll('.chip[data-filter]');
    const searchInput = document.getElementById('search');
    const emptyRow = document.getElementById('emptyFilterRow');
    let currentFilter = 'all';

    function applyFilter() {
        const query = (searchInput?.value || '').toLowerCase().trim();
        let visibleCount = 0;

        rows.forEach(row => {
            const level = row.dataset.level || '';
            const textSearch = (row.dataset.search || '').toLowerCase();

            const matchesFilter = (currentFilter === 'all') || (level === currentFilter);
            const matchesSearch = !query || textSearch.includes(query);

            if (matchesFilter && matchesSearch) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        if (emptyRow) {
            emptyRow.style.display = (visibleCount === 0 && rows.length > 0) ? '' : 'none';
        }
    }

    chips.forEach(chip => {
        chip.addEventListener('click', function () {
            chips.forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            currentFilter = this.dataset.filter;
            applyFilter();
        });
    });

    if (searchInput) {
        searchInput.addEventListener('input', applyFilter);
    }
});
```

---

## 7. Phối Hợp Đội Ngũ (Team Coordination)

- **Với `explorer_m5_2` (Zalo Removal & Dead Code Elimination)**:
  - Bản thiết kế trên đã loại bỏ 100% các thành phần Zalo: xóa `buildFeedback()`, xóa nút copy, xóa toast, xóa modal intervention.
  - Cột thao tác trong bảng chuyển thành nút liên kết trực tiếp `Vào sổ điểm`.
- **Với `explorer_m5_3` (SaaS UI Redesign & Test Suite Design)**:
  - Bản thiết kế cung cấp các token CSS `.chip`, `.radar-table`, `.card.stat-card` tương thích hoàn toàn với hệ thống Stitch UI / Plus Jakarta Sans.
  - Cung cấp 8 ca kiểm thử mẫu cho bộ kiểm thử tự động `tests/verify_m5.php` (xem chi tiết trong `handoff.md`).

---
*Báo cáo được hoàn thành bởi `explorer_m5_1` — Sẵn sàng bàn giao cho Orchestrator và Worker M5.*
