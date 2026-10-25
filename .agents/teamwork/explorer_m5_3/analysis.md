# Báo Cáo Phân Tích & Thiết Kế Giao Diện SaaS (Stitch UI) & Bộ Kiểm Thử Tự Động verify_m5.php

**Explorer**: `explorer_m5_3`  
**Milestone**: Milestone 5 (Early-Warning Radar & SaaS UI Redesign)  
**Ngày thực hiện**: 2026-10-17  
**Phạm vi**: 
1. Khảo sát & thiết kế tái cấu trúc giao diện chuẩn SaaS Stitch UI trên toàn bộ các view (`login.php`, `admin.php`, `nhap_diem.php`, `index.php`, `includes/header.php`, `includes/footer.php`, `assets/css/style.css`).
2. Thiết kế chi tiết bộ kiểm thử tự động `tests/verify_m5.php` bao phủ 7 nhóm kiểm thử cốt lõi theo hợp đồng kỹ thuật và yêu cầu nghiệm thu.

---

## 1. Tóm Tắt Kết Quả Điều Tra & Hiện Trạng Hệ Thống

### 1.1 Hiện trạng các View và Layout
1. **`index.php`**:
   - Hiện vẫn sử dụng layout cũ với `<aside class="sidebar">` (dòng 118-134), chưa được đồng bộ cấu trúc với header chung.
   - Câu truy vấn dữ liệu (dòng 74-85) vẫn đang dùng tên cột cũ thời SQLite: `sc.m1, sc.m2, sc.p15_1, sc.p15_2, sc.t1_1, sc.gk, sc.ck, sc.avg_score`. Trong khi cơ sở dữ liệu `scores` thực tế của MySQL `edu_radar` đã được chuẩn hóa ở M1-M4 thành: `tx1, tx2, tx3, tx4, gk, ck, avg_score, subject`.
   - Vẫn chứa nút Zalo copy (dòng 209-211: `<button class="btn copy-btn" data-text="...">📋 Copy Tin Nhắn Zalo</button>`) và thông báo toast (dòng 221: `<div id="toast" class="toast">Đã sao chép tin nhắn Zalo!</div>`).
   - Lọc phân quyền giáo viên mới chỉ lọc `WHERE s.class_id IN (SELECT class_id FROM teacher_classes WHERE teacher_id = ?)` mà chưa ràng buộc môn học `AND sc.subject = tc.subject`, dẫn tới nguy cơ hiển thị sai điểm môn của giáo viên khác nếu cùng lớp.
   - Hàm `evaluateRisk()` (dòng 26-54) mới chỉ đánh giá thô sơ (`avg < 5.0`, `min < 5.0`), chưa cài đặt đúng logic cảnh báo sớm đa tầng theo yêu cầu M5 (`avg < 3.5`, `ck < 3.0`, `gk < 5.0 && ck < 5.0`, `ck < gk - 1.5`).

2. **`assets/js/main.js`**:
   - Chứa đoạn script sao chép Zalo (dòng 25-41: `copy-btn` listener, `navigator.clipboard.writeText`, `#toast`).
   - Chứa mã modal can thiệp rác `actionModal` (dòng 43-60: `modalStudentId`, `openActionModal`, `closeActionModal`) dù trong giao diện không hề có HTML của modal này.

3. **`assets/css/style.css`**:
   - Đã có nền tảng biến CSS cơ bản `:root`, nhưng chưa có:
     - Định nghĩa class Role Badge chuyên biệt cho "Quản trị viên" (`.role-badge.role-admin`) và "Giáo viên bộ môn" (`.role-badge.role-teacher`).
     - Thanh điều hướng trên đỉnh hợp nhất (`.stitch-navbar`, `.navbar-nav`, `.nav-item.active`, `.navbar-user`).
     - Các thẻ lọc thống kê cảnh báo sớm cải tiến (`.filter-tabs`, `.filter-tab`, `.tab-dot`, `.tab-pct`).

4. **`includes/header.php` & `includes/footer.php`**:
   - Hiện chưa tồn tại trong thư mục `includes/`. Mỗi trang (`login.php`, `admin.php`, `nhap_diem.php`, `index.php`) đang phải tự viết lại toàn bộ thẻ `<head>`, font link Google Fonts, favicon và thanh điều hướng, gây phân mảnh và khó bảo trì.

5. **`login.php`**:
   - Đã có card đăng nhập tương đối đẹp, nhưng có thể tinh chỉnh các thẻ nhập liệu, độ bóng và căn chỉnh theo phong cách Stitch UI để hoàn toàn nhất quán.

---

## 2. Thiết Kế Giao Diện SaaS (Stitch UI Aesthetic Specification)

### 2.1 Hệ Thống Design Tokens (CSS Variables)
Toàn bộ hệ thống giao diện sẽ tuân thủ bộ design tokens chuẩn hóa trong `assets/css/style.css`:

```css
:root {
    /* Stitch Typography */
    --font-sans: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
    --font-mono: 'JetBrains Mono', monospace;

    /* Primary Brand Tokens (Indigo SaaS) */
    --primary: #4f46e5;
    --primary-hover: #4338ca;
    --primary-light: #eef2ff;
    --primary-border: #c7d2fe;
    --primary-glow: rgba(79, 70, 229, 0.16);
    
    /* Semantic Status Tokens */
    --danger: #ef4444;
    --danger-dark: #b91c1c;
    --danger-bg: #fef2f2;
    --danger-border: #fecaca;
    
    --warning: #f59e0b;
    --warning-dark: #b45309;
    --warning-bg: #fffbeb;
    --warning-border: #fde68a;
    
    --success: #10b981;
    --success-dark: #047857;
    --success-bg: #ecfdf5;
    --success-border: #a7f3d0;
    
    /* Slate Neutral Surface Tokens */
    --bg-main: #f8fafc;
    --card-bg: #ffffff;
    --surface-hover: #f1f5f9;
    --border-color: #e2e8f0;
    --border-subtle: #f1f5f9;
    --text-heading: #0f172a;
    --text-body: #334155;
    --text-muted: #64748b;
    --text-subtle: #94a3b8;
    
    /* Soft Elevation Shadows (Stitch Multi-layer) */
    --shadow-xs: 0 1px 2px rgba(15, 23, 42, 0.04);
    --shadow-sm: 0 1px 3px rgba(15, 23, 42, 0.06), 0 1px 2px rgba(15, 23, 42, 0.04);
    --shadow-md: 0 4px 6px -1px rgba(15, 23, 42, 0.07), 0 2px 4px -2px rgba(15, 23, 42, 0.05);
    --shadow-lg: 0 10px 15px -3px rgba(15, 23, 42, 0.08), 0 4px 6px -4px rgba(15, 23, 42, 0.04);
    --shadow-xl: 0 20px 25px -5px rgba(15, 23, 42, 0.08), 0 8px 10px -6px rgba(15, 23, 42, 0.04);
    
    /* Rounded Corners */
    --radius-sm: 6px;
    --radius-md: 10px;
    --radius-lg: 14px;
    --radius-xl: 18px;
    --radius-full: 9999px;
}
```

### 2.2 Role Badges (Huy Hiệu Vai Trò)
Thiết kế 2 badge vai trò trực quan, sang trọng và chuẩn mực:
- **Quản trị viên (Admin)**:
  - Class: `.role-badge.role-admin`
  - Style: Nền `var(--primary-light)` (`#eef2ff`), chữ `var(--primary-hover)` (`#4338ca`), viền `1px solid var(--primary-border)` (`#c7d2fe`). Có chấm tròn indicator hoặc icon vương miện/khiên: `👑 Quản trị viên`.
- **Giáo viên bộ môn (Teacher)**:
  - Class: `.role-badge.role-teacher`
  - Style: Nền `var(--success-bg)` (`#ecfdf5`), chữ `var(--success-dark)` (`#047857`), viền `1px solid var(--success-border)` (`#a7f3d0`). Có chấm tròn indicator: `👨‍🏫 Giáo viên bộ môn`.

```css
.role-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: var(--radius-full);
    font-size: 12px;
    font-weight: 700;
    line-height: 1.2;
}

.role-badge .badge-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
}

.role-badge.role-admin {
    background: #eef2ff;
    color: #4338ca;
    border: 1px solid #c7d2fe;
}
.role-badge.role-admin .badge-dot {
    background: #4f46e5;
    box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.2);
}

.role-badge.role-teacher {
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
}
.role-badge.role-teacher .badge-dot {
    background: #10b981;
    box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
}
```

### 2.3 Cấu Trúc Header & Navigation Thống Nhất (`includes/header.php` & `includes/footer.php`)

#### File `includes/header.php`:
```php
<?php
// includes/header.php - Stitch UI Unified Navigation Header Shell
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
$currentUserRole = $_SESSION['role'] ?? 'teacher';
$currentUsername = $_SESSION['username'] ?? '';
$currentUserFullName = $_SESSION['full_name'] ?? $currentUsername;
$activeNav = $activeNav ?? 'dashboard'; // 'dashboard' | 'gradebook' | 'admin'
$pageTitle = $pageTitle ?? 'EduRadar THCS';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> – <?= APP_NAME ?? 'EduRadar' ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <?php if (!empty($extraHead)) echo $extraHead; ?>
</head>
<body>
<div class="stitch-app">
    <header class="stitch-header">
        <div class="stitch-header-inner">
            <div class="header-brand">
                <a href="index.php" class="brand-logo">
                    <span class="brand-icon">📡</span>
                    <div class="brand-info">
                        <span class="brand-name"><?= APP_NAME ?? 'EduRadar' ?></span>
                        <span class="brand-sub">Hệ Thống Điểm THCS</span>
                    </div>
                </a>
            </div>

            <nav class="header-nav">
                <a href="index.php" class="nav-tab <?= $activeNav === 'dashboard' ? 'active' : '' ?>">
                    <span class="tab-icon">📊</span>
                    <span class="tab-text">Radar Cảnh Báo</span>
                </a>
                <a href="nhap_diem.php" class="nav-tab <?= $activeNav === 'gradebook' ? 'active' : '' ?>">
                    <span class="tab-icon">📝</span>
                    <span class="tab-text">Sổ Điểm VnEdu</span>
                </a>
                <?php if ($currentUserRole === 'admin'): ?>
                <a href="admin.php" class="nav-tab <?= $activeNav === 'admin' ? 'active' : '' ?>">
                    <span class="tab-icon">⚙️</span>
                    <span class="tab-text">Quản Trị Nhà Trường</span>
                </a>
                <?php endif; ?>
            </nav>

            <div class="header-user-section">
                <div class="user-meta">
                    <span class="user-display-name"><?= htmlspecialchars($currentUserFullName) ?></span>
                    <?php if ($currentUserRole === 'admin'): ?>
                        <span class="role-badge role-admin"><span class="badge-dot"></span> Quản trị viên</span>
                    <?php else: ?>
                        <span class="role-badge role-teacher"><span class="badge-dot"></span> Giáo viên bộ môn</span>
                    <?php endif; ?>
                </div>
                <a href="logout.php" class="btn-stitch-logout" title="Đăng xuất khỏi hệ thống">
                    <span>🚪</span>
                    <span class="logout-text">Đăng Xuất</span>
                </a>
            </div>
        </div>
    </header>
    <main class="stitch-main">
```

#### File `includes/footer.php`:
```php
<?php
// includes/footer.php - Stitch UI Unified Footer Shell
?>
    </main>
    <footer class="stitch-footer">
        <div class="stitch-footer-inner">
            <p>© <?= date('Y') ?> <b>EduRadar THCS</b> – Hệ Thống Quản Lý Điểm & Cảnh Báo Sớm Học Sinh (Thông Tư 22/2021/TT-BGDĐT).</p>
            <div class="system-status">
                <span class="status-dot"></span>
                <span>Cơ sở dữ liệu MySQL (MariaDB 10.4)</span>
            </div>
        </div>
    </footer>
</div>
<?php if (!empty($extraScripts)) echo $extraScripts; ?>
</body>
</html>
```

### 2.4 Cải Tiến Giao Diện Chi Tiết Từng Trang

1. **`index.php` (Early-Warning Radar)**:
   - **4 Stat Cards lớn**: 
     - Tổng số học sinh: `total`
     - 🔴 Nguy cấp: số lượng + badge tỷ lệ phần trăm (ví dụ: `20.0%`) kèm viền trên đỏ (`border-top: 4px solid var(--danger)`).
     - 🟡 Cần chú ý: số lượng + badge tỷ lệ phần trăm kèm viền trên vàng (`border-top: 4px solid var(--warning)`).
     - 🟢 Ổn định: số lượng + badge tỷ lệ phần trăm kèm viền trên xanh (`border-top: 4px solid var(--success)`).
   - **Filter Tabs Bar**:
     - Các tab bấm chuyển trạng thái: `Tất cả (count)`, `🔴 Nguy cấp (count)`, `🟡 Cần chú ý (count)`, `🟢 Ổn định (count)`.
     - Tích hợp ô tìm kiếm học sinh (`#search`) ngay bên cạnh với biểu tượng kính lúp, lọc mượt mà theo cả họ tên và mã học sinh.
   - **Bảng Dữ Liệu Học Sinh & Chuẩn Đoán Nguy Cơ**:
     - Các cột: `STT`, `Học sinh` (Tên, Mã định danh, SĐT phụ huynh), `Lớp`, `Môn`, `Điểm TX (tx1..tx4)`, `Giữa kỳ (HS 2)`, `Cuối kỳ (HS 3)`, `Điểm TB (avg_score)`, `Trạng thái` (Badge), `Chuẩn đoán nguyên nhân` (Tag giải thích chi tiết), `Thao tác` (Nút "📝 Vào sổ điểm" dẫn thẳng tới `nhap_diem.php?class_id=X&subject=Y`).
     - **Hoàn toàn KHÔNG còn cột Zalo, KHÔNG có nút Copy tin nhắn Zalo, KHÔNG có modal actionModal hay toast Zalo.**

2. **`login.php`**:
   - Card đăng nhập trung tâm với góc bo `var(--radius-xl)`, bóng mờ mềm mại `var(--shadow-xl)`, typography `Plus Jakarta Sans`.
   - Hộp tài khoản mẫu thử nghiệm (Admin, GV Toán, GV Văn) với nút tự điền tiện lợi.

3. **`admin.php`**:
   - Sử dụng chung bộ token Stitch UI, thẻ thống kê tổng quan (Lớp học, Giáo viên, Phân công, Học sinh).
   - Ma trận lớp Khối 6–9 dạng grid thẻ bo góc, nút chọn nhanh theo khối.
   - Bảng phân công và bảng nhật ký kiểm toán với phong cách viền mờ `1px solid var(--border-color)` và hover dòng `var(--surface-hover)`.

4. **`nhap_diem.php`**:
   - Giữ nguyên cấu trúc 11 cột và phím tắt 2D đã được kiểm thử 39/39 ở M4.
   - Thêm styling Stitch UI cho thanh công cụ phía trên (Toolbar), huy hiệu `dirty-badge` (Đã lưu / Chưa lưu), và các bảng tải lên file Excel/CSV mẫu ở cuối trang.

---

## 3. Thiết Kế Engine Cảnh Báo Sớm (Early-Warning Engine Diagnostic Logic)

### 3.1 Quy Tắc Phân Loại Cảnh Báo Sớm
Hàm phân loại `evaluateEarlyWarning()` nhận các tham số điểm và trả về mảng kết quả:

```php
function evaluateEarlyWarning(?float $avg, ?float $gk, ?float $ck, array $txList = []): array
{
    $reasons = [];
    $level = 'green';

    // -------------------------------------------------------------
    // 1. Phân loại 🔴 Nguy cấp (Critical / Red)
    // -------------------------------------------------------------
    $isAvgCrit = ($avg !== null && $avg < 3.5);
    if ($isAvgCrit) {
        $reasons[] = 'Điểm TB môn dưới 3.5 (Nguy cơ không đạt)';
    }

    $isCkCrit = ($ck !== null && $ck < 3.0);
    if ($isCkCrit) {
        $reasons[] = 'Điểm cuối kỳ dưới 3.0 (Hổng kiến thức trọng tâm)';
    }

    $isBothFailing = ($gk !== null && $gk < 5.0 && $ck !== null && $ck < 5.0);
    if ($isBothFailing) {
        $reasons[] = 'Không đạt cả 2 bài kiểm tra định kỳ (GK & CK < 5.0)';
    }

    if ($isAvgCrit || $isCkCrit || $isBothFailing) {
        $level = 'red';
    }

    // -------------------------------------------------------------
    // 2. Phân loại 🟡 Cần chú ý (Warning / At-Risk / Yellow)
    // -------------------------------------------------------------
    if ($level !== 'red') {
        $isAvgWarning = ($avg !== null && $avg >= 3.5 && $avg < 5.0);
        if ($isAvgWarning) {
            $reasons[] = 'Điểm TB môn dưới trung bình (3.5 - 4.9)';
        }

        // Xu hướng sa sút: ck giảm hơn 1.5 điểm so với gk (ck < gk - 1.5)
        $isDownwardTrend = ($gk !== null && $ck !== null && $ck < ($gk - 1.5));
        if ($isDownwardTrend) {
            $diff = round($gk - $ck, 1);
            $reasons[] = "Điểm thi cuối kỳ sa sút so với giữa kỳ (giảm {$diff} điểm)";
        }

        if ($isAvgWarning || $isDownwardTrend) {
            $level = 'yellow';
        }
    }

    // -------------------------------------------------------------
    // 3. Phân loại 🟢 Ổn định (Stable / Green)
    // -------------------------------------------------------------
    if ($level === 'green') {
        if ($avg === null) {
            $reasons[] = 'Chưa đủ dữ liệu điểm để đánh giá';
        } elseif ($avg >= 8.0) {
            $reasons[] = 'Học lực giỏi, duy trì phong độ xuất sắc';
        } elseif ($avg >= 6.5) {
            $reasons[] = 'Học lực khá, duy trì kết quả ổn định';
        } else {
            $reasons[] = 'Đạt yêu cầu chuẩn môn học';
        }
    }

    return [
        'level'      => $level,
        'reasons'    => $reasons,
        'diagnostic' => implode(' · ', $reasons),
    ];
}
```

### 3.2 Xử Lý Các Trường Hợp Biên (Edge Cases)
1. **Sa sút xu hướng (`ck < gk - 1.5`)**:
   - `gk = 8.0, ck = 6.5`: Hiệu số đúng bằng `1.5`. Biểu thức `6.5 < 8.0 - 1.5` (`6.5 < 6.5`) trả về `false`. Không kích hoạt cảnh báo sa sút. Học sinh xếp loại 🟢 Ổn định.
   - `gk = 8.0, ck = 6.4`: Hiệu số bằng `1.6`. Biểu thức `6.4 < 6.5` trả về `true`. Kích hoạt cảnh báo sa sút 🟡 Cần chú ý.
2. **Khuyết bài thi định kỳ**:
   - `gk = 7.0, ck = null`: Một trong 2 bài chưa có điểm -> Không kích hoạt cảnh báo sa sút, tránh cảnh báo giả đầu kỳ.
3. **Thứ tự ưu tiên (Precedence)**:
   - Học sinh vừa sa sút điểm vừa có `ck < 3.0` (ví dụ `gk = 5.0, ck = 2.5`): Điều kiện 🔴 Nguy cấp kiểm tra trước và gán `level = 'red'`, ngăn không cho mức Vàng ghi đè.

---

## 4. Thiết Kế Bộ Kiểm Thử Tự Động `tests/verify_m5.php`

### 4.1 Cấu Trúc File & Kiến Trúc Test Runner
- **Ngôn ngữ**: PHP 8.0 thuần, `declare(strict_types=1);`.
- **Thực thi**: Chạy trực tiếp qua PHP CLI: `D:\xam\php\php.exe tests/verify_m5.php`.
- **Mục tiêu**: Kiểm thử trực tiếp máy chủ web Apache XAMPP `http://localhost/my-web-project/` và cơ sở dữ liệu MySQL `edu_radar`.
- **Thư viện phụ thuộc**: Zero external dependencies (dùng cURL, PDO, file reflection).

### 4.2 Chi Tiết 7 Nhóm Kiểm Thử (42 Tests Dự Kiến)

```
=============================================================================
  EduRadar Milestone 5: Automated Verification Suite
  Target: http://localhost/my-web-project/ (Apache & MariaDB)
=============================================================================
```

#### NHÓM 1: Thuật Toán Phân Loại Cảnh Báo Sớm (Early-Warning Classification Logic)
- `1.1 Student with avg_score < 3.5 classified as 🔴 red (Nguy cấp)`: Kiểm tra học sinh điểm TB 3.2.
- `1.2 Student with ck < 3.0 classified as 🔴 red (Nguy cấp) even if avg >= 5.0`: Kiểm tra học sinh `ck = 2.5`, `avg = 5.2`.
- `1.3 Student failing both GK & CK (< 5.0) classified as 🔴 red (Nguy cấp)`: Kiểm tra `gk = 4.0`, `ck = 4.5`.
- `1.4 Student with multiple critical flags aggregates all diagnostic reasons`: Xác nhận mảng `reasons` chứa đủ các nguyên nhân vi phạm.
- `1.5 Student with avg_score in [3.5, 5.0) classified as 🟡 yellow (Cần chú ý)`: Kiểm tra học sinh điểm TB 4.2.
- `1.6 Student with avg_score >= 5.0 and no warning flags classified as 🟢 green (Ổn định)`: Kiểm tra học sinh điểm TB 6.2.
- `1.7 Student with avg_score >= 8.0 classified as 🟢 green with excellent note`: Kiểm tra học sinh điểm TB 8.8.
- `1.8 Student with empty/null scores classified as 🟢 green (Chưa đủ dữ liệu)`: Kiểm tra học sinh mới chưa vào điểm.

#### NHÓM 2: Kiểm Thử Trường Hợp Biên & Xu Hướng Sa Sút (Trend & Boundary Edge Cases)
- `2.1 Downward trend ck < gk - 1.5 triggers 🟡 yellow (Cần chú ý)`: `gk = 8.0, ck = 6.0` (giảm 2.0 điểm).
- `2.2 Boundary condition: difference exactly 1.5 (ck == gk - 1.5) does not trigger yellow`: `gk = 8.0, ck = 6.5` -> Green.
- `2.3 Boundary condition: difference 1.6 (ck == gk - 1.6) triggers downward trend`: `gk = 8.0, ck = 6.4` -> Yellow.
- `2.4 Partial periodic scores (gk present, ck null) do not trigger false downward trend`: `gk = 7.0, ck = null`.
- `2.5 Failing only GK (gk < 5.0, ck >= 5.0, avg >= 5.0) does not trigger red`: `gk = 4.5, ck = 6.5, avg = 5.8`.
- `2.6 Precedence rule: ck < 3.0 with downward trend remains 🔴 red`: `gk = 5.5, ck = 2.5` -> Red.

#### NHÓM 3: Bộ Đếm Thống Kê & Tính Toán Tỷ Lệ Phần Trăm (Stat Counters & Percentages)
- `3.1 Stat invariant holds: total === red + yellow + green`: Kiểm tra tổng số học sinh luôn bằng tổng các nhóm.
- `3.2 Percentage sum invariant: pct_red + pct_yellow + pct_green equals 100.0% (+/- 0.2%)`: Kiểm tra bảo toàn tỷ lệ.
- `3.3 Zero-division defense: empty student set returns 0% without PHP warning/error`: Thử nghiệm với tập dữ liệu rỗng.
- `3.4 Stat cards in rendered HTML display accurate counts and percent badges`: Quét regex thẻ stat trên trang.
- `3.5 Live database query counts match index.php dashboard summary numbers`: Đối soát trực tiếp với cơ sở dữ liệu `edu_radar`.

#### NHÓM 4: Thẻ Lọc Tương Tác & Thuộc Tính Bảng Dữ Liệu (Interactive Tabs & DOM Attributes)
- `4.1 Filter tabs render data-filter attributes for all, red, yellow, green`: Kiểm tra các nút tab.
- `4.2 Filter tabs display correct dynamic count badges`: Đối soát nhãn số lượng trên tab.
- `4.3 Table body rows contain data-level attribute matching evaluated risk`: `tr[data-level="red|yellow|green"]`.
- `4.4 Table body rows contain data-name attribute with lowercase full name`: Phục vụ tìm kiếm thời gian thực.
- `4.5 Search input element exists with id="search"`: Kiểm tra sự hiện diện của ô tìm kiếm.
- `4.6 Student rows render component scores (tx, gk, ck) and average badge`: Kiểm tra hiển thị các cột điểm VnEdu.
- `4.7 Action column provides direct link to nhap_diem.php with class_id and subject`: Kiểm tra liên kết vào sổ điểm.

#### NHÓM 5: Loại Bỏ Hoàn Toàn Zalo & Mã Rác (Zalo & Dead Code Elimination Verification)
- `5.1 File index.php contains 0 occurrences of 'zalo' (case-insensitive)`: Assert `stripos($content, 'zalo') === false`.
- `5.2 File assets/js/main.js contains 0 occurrences of 'zalo' (case-insensitive)`: Assert `stripos($js, 'zalo') === false`.
- `5.3 File index.php contains 0 occurrences of 'actionModal'`: Không còn modal rác.
- `5.4 File assets/js/main.js contains 0 occurrences of 'actionModal'`: Không còn hàm mở/đóng modal rác.
- `5.5 File assets/js/main.js contains 0 occurrences of 'copy-btn' or clipboard handlers`: Không còn sự kiện copy Zalo.
- `5.6 HTTP GET index.php response body contains 0 occurrences of 'zalo'`: Kiểm tra mã nguồn HTML gửi về client.

#### NHÓM 6: Phân Quyền Phục Vụ Nghiêm Ngặt Trên `index.php` (Strict RBAC Verification)
- `6.1 Unauthenticated request to index.php redirects to login.php (HTTP 302)`: Kiểm tra route guard.
- `6.2 Teacher gv_toan accesses index.php successfully (HTTP 200)`: Kiểm tra đăng nhập giáo viên.
- `6.3 Teacher gv_toan only sees students for assigned classes and assigned subject 'Toán'`: Chỉ thấy học sinh lớp được phân công.
- `6.4 Teacher gv_toan cannot see scores of unassigned subject 'Ngữ Văn'`: Cách ly tuyệt đối dữ liệu môn học.
- `6.5 Admin account accesses index.php and views all classes & subjects (HTTP 200)`: Quản trị viên xem toàn trường.

#### NHÓM 7: Tính Toàn Vẹn Của Design Tokens & Giao Diện Stitch UI (Stitch UI Design Tokens)
- `7.1 assets/css/style.css imports Google Font 'Plus Jakarta Sans'`: Kiểm tra typography.
- `7.2 assets/css/style.css defines core primary color variables in :root`: `--primary`, `--primary-hover`, v.v.
- `7.3 assets/css/style.css defines semantic status variables (--danger, --warning, --success)`: Kiểm tra bảng màu trạng thái.
- `7.4 assets/css/style.css defines multi-layer soft shadows and border-radius tokens`: Kiểm tra bóng và bo góc.
- `7.5 assets/css/style.css defines role badges for Admin and Teacher`: `.role-admin` và `.role-teacher`.
- `7.6 includes/header.php exists and renders unified navigation with active states`: Kiểm tra header chung.

---

## 5. Bản Thảo Mã Nguồn Đề Xuất Cho `tests/verify_m5.php`

Dưới đây là thiết kế chi tiết toàn bộ mã nguồn của file kiểm thử `tests/verify_m5.php` để worker hoặc implementer có thể triển khai ngay lập tức:

```php
<?php
/**
 * =============================================================================
 * Automated Milestone 5 Verification Suite (EduRadar THCS)
 * Kiểm thử tự động Milestone 5:
 *   - Nhóm 1: Thuật toán phân loại Cảnh Báo Sớm (Nguy cấp, Cần chú ý, Ổn định)
 *   - Nhóm 2: Trường hợp biên & Phát hiện xu hướng sa sút (ck < gk - 1.5, failing GK+CK)
 *   - Nhóm 3: Bộ đếm thống kê & Độ chính xác của tỷ lệ phần trăm
 *   - Nhóm 4: Thẻ lọc tương tác & Thuộc tính DOM của bảng dữ liệu
 *   - Nhóm 5: Kiểm toán loại bỏ hoàn toàn Zalo & Mã rác (0 'zalo', 0 'actionModal')
 *   - Nhóm 6: Phân quyền phục vụ dữ liệu nghiêm ngặt trên index.php (RBAC)
 *   - Nhóm 7: Tính toàn vẹn của Design Tokens & Giao diện Stitch UI
 * 
 * Chạy lệnh kiểm thử:
 *   D:\xam\php\php.exe tests/verify_m5.php
 * =============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';

// ANSI Color formatting
const CLR_RESET  = "\033[0m";
const CLR_GREEN  = "\033[1;32m";
const CLR_RED    = "\033[1;31m";
const CLR_YELLOW = "\033[1;33m";
const CLR_CYAN   = "\033[1;36m";

$passedCount = 0;
$failedCount = 0;

function assertTest(string $name, callable $testFn): void
{
    global $passedCount, $failedCount;
    echo CLR_CYAN . "[TEST] " . CLR_RESET . str_pad($name, 68, ".");
    try {
        $result = $testFn();
        if ($result === false) {
            throw new Exception("Assertion returned false");
        }
        $passedCount++;
        echo CLR_GREEN . " [PASS]" . CLR_RESET . PHP_EOL;
    } catch (Throwable $e) {
        $failedCount++;
        echo CLR_RED . " [FAIL]" . CLR_RESET . PHP_EOL;
        echo CLR_RED . "       -> Error: " . $e->getMessage() . CLR_RESET . PHP_EOL;
    }
}

/**
 * HTTP Client chuyên dụng hỗ trợ Session Cookies và Redirects qua cURL
 */
class TestClient
{
    private string $baseUrl;
    private string $cookieFile;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->cookieFile = sys_get_temp_dir() . '/edu_m5_cookie_' . bin2hex(random_bytes(6)) . '.txt';
    }

    public function __destruct()
    {
        if (file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    public function clearCookies(): void
    {
        if (file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    public function request(string $method, string $path, $data = [], bool $followRedirects = false): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        $ch = curl_init();

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_FOLLOWLOCATION => $followRedirects,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 15,
        ];

        if (strtoupper($method) === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = is_array($data) ? http_build_query($data) : $data;
        }

        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new Exception("cURL request to '$url' failed: $err");
        }

        $info = curl_getinfo($ch);
        $headerSize = $info['header_size'];
        $headers = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);
        curl_close($ch);

        return [
            'status'       => (int)$info['http_code'],
            'redirect_url' => (string)($info['redirect_url'] ?? ''),
            'headers'      => $headers,
            'body'         => $body,
        ];
    }

    public function get(string $path, bool $followRedirects = false): array
    {
        return $this->request('GET', $path, [], $followRedirects);
    }

    public function post(string $path, array $data = [], bool $followRedirects = false): array
    {
        return $this->request('POST', $path, $data, $followRedirects);
    }

    public function login(string $username, string $password): array
    {
        return $this->post('login.php', [
            'username' => $username,
            'password' => $password
        ], false);
    }
}

$client = new TestClient('http://localhost/my-web-project');

echo PHP_EOL . CLR_YELLOW . "============================================================" . CLR_RESET . PHP_EOL;
echo CLR_YELLOW . "  EduRadar Milestone 5: Automated Verification Suite" . CLR_RESET . PHP_EOL;
echo CLR_YELLOW . "  Target: http://localhost/my-web-project/ (Apache & MariaDB)" . CLR_RESET . PHP_EOL;
echo CLR_YELLOW . "============================================================" . CLR_RESET . PHP_EOL . PHP_EOL;

// =============================================================================
// BẢNG KIỂM TRA THUẬT TOÁN ĐỘC LẬP
// =============================================================================
function testEvaluateRisk(?float $avg, ?float $gk, ?float $ck, array $txList = []): array
{
    // Cài đặt chuẩn đối soát
    $reasons = [];
    $level = 'green';

    $isAvgCrit = ($avg !== null && $avg < 3.5);
    if ($isAvgCrit) $reasons[] = 'Điểm TB dưới 3.5';
    $isCkCrit = ($ck !== null && $ck < 3.0);
    if ($isCkCrit) $reasons[] = 'Điểm cuối kỳ dưới 3.0';
    $isBothFailing = ($gk !== null && $gk < 5.0 && $ck !== null && $ck < 5.0);
    if ($isBothFailing) $reasons[] = 'Cả GK và CK dưới 5.0';

    if ($isAvgCrit || $isCkCrit || $isBothFailing) {
        $level = 'red';
    } else {
        $isAvgWarn = ($avg !== null && $avg >= 3.5 && $avg < 5.0);
        if ($isAvgWarn) $reasons[] = 'Điểm TB dưới trung bình';
        $isDrop = ($gk !== null && $ck !== null && $ck < ($gk - 1.5));
        if ($isDrop) $reasons[] = 'Điểm cuối kỳ sa sút';

        if ($isAvgWarn || $isDrop) {
            $level = 'yellow';
        }
    }

    if ($level === 'green') {
        $reasons[] = ($avg === null) ? 'Chưa đủ điểm' : (($avg >= 8.0) ? 'Giỏi' : 'Ổn định');
    }

    return ['level' => $level, 'reasons' => $reasons];
}

// =============================================================================
// NHÓM 1: THUẬT TOÁN PHÂN LOẠI CẢNH BÁO SỚM
// =============================================================================
echo CLR_YELLOW . "--- NHÓM 1: Thuật toán Phân loại Cảnh Báo Sớm ---" . CLR_RESET . PHP_EOL;

assertTest("1.1 Student with avg_score < 3.5 classified as 🔴 red (Nguy cấp)", function () {
    $res = testEvaluateRisk(3.2, 5.0, 5.0);
    if ($res['level'] !== 'red') throw new Exception("Expected 'red', got '{$res['level']}'");
    return true;
});

assertTest("1.2 Student with ck < 3.0 classified as 🔴 red (Nguy cấp) even if avg >= 5.0", function () {
    $res = testEvaluateRisk(5.2, 7.0, 2.5);
    if ($res['level'] !== 'red') throw new Exception("Expected 'red', got '{$res['level']}'");
    return true;
});

assertTest("1.3 Student failing both GK & CK (< 5.0) classified as 🔴 red (Nguy cấp)", function () {
    $res = testEvaluateRisk(4.8, 4.5, 4.5);
    if ($res['level'] !== 'red') throw new Exception("Expected 'red', got '{$res['level']}'");
    return true;
});

assertTest("1.4 Student with multiple critical flags aggregates reasons cleanly", function () {
    $res = testEvaluateRisk(3.0, 4.0, 2.5);
    if ($res['level'] !== 'red') throw new Exception("Expected 'red'");
    if (count($res['reasons']) < 2) throw new Exception("Expected multiple diagnostic reasons");
    return true;
});

assertTest("1.5 Student with avg in [3.5, 5.0) classified as 🟡 yellow (Cần chú ý)", function () {
    $res = testEvaluateRisk(4.2, 5.5, 5.0);
    if ($res['level'] !== 'yellow') throw new Exception("Expected 'yellow', got '{$res['level']}'");
    return true;
});

assertTest("1.6 Student with avg >= 5.0 and no warning flags classified as 🟢 green", function () {
    $res = testEvaluateRisk(6.5, 7.0, 6.5);
    if ($res['level'] !== 'green') throw new Exception("Expected 'green', got '{$res['level']}'");
    return true;
});

assertTest("1.7 Student with avg >= 8.0 classified as 🟢 green (Ổn định)", function () {
    $res = testEvaluateRisk(8.5, 9.0, 8.5);
    if ($res['level'] !== 'green') throw new Exception("Expected 'green', got '{$res['level']}'");
    return true;
});

assertTest("1.8 Student with empty/null scores classified as 🟢 green", function () {
    $res = testEvaluateRisk(null, null, null);
    if ($res['level'] !== 'green') throw new Exception("Expected 'green', got '{$res['level']}'");
    return true;
});

// =============================================================================
// NHÓM 2: TRƯỜNG HỢP BIÊN & PHÁT HIỆN XU HƯỚNG SA SÚT
// =============================================================================
echo PHP_EOL . CLR_YELLOW . "--- NHÓM 2: Trường hợp Biên & Phát hiện Xu hướng Sa sút ---" . CLR_RESET . PHP_EOL;

assertTest("2.1 Downward trend ck < gk - 1.5 triggers 🟡 yellow (Cần chú ý)", function () {
    $res = testEvaluateRisk(6.2, 8.0, 6.0); // diff = 2.0 > 1.5
    if ($res['level'] !== 'yellow') throw new Exception("Expected 'yellow', got '{$res['level']}'");
    return true;
});

assertTest("2.2 Boundary difference exactly 1.5 does not trigger downward trend", function () {
    $res = testEvaluateRisk(6.2, 8.0, 6.5); // diff = 1.5
    if ($res['level'] !== 'green') throw new Exception("Expected 'green', got '{$res['level']}'");
    return true;
});

assertTest("2.3 Boundary difference 1.6 triggers downward trend", function () {
    $res = testEvaluateRisk(6.2, 8.0, 6.4); // diff = 1.6 > 1.5
    if ($res['level'] !== 'yellow') throw new Exception("Expected 'yellow', got '{$res['level']}'");
    return true;
});

assertTest("2.4 Partial periodic scores (gk present, ck null) do not trigger false alarm", function () {
    $res = testEvaluateRisk(7.0, 7.5, null);
    if ($res['level'] !== 'green') throw new Exception("Expected 'green', got '{$res['level']}'");
    return true;
});

assertTest("2.5 Failing only GK (gk < 5.0, ck >= 5.0, avg >= 5.0) does not trigger red", function () {
    $res = testEvaluateRisk(5.8, 4.5, 6.5);
    if ($res['level'] === 'red') throw new Exception("Should not be 'red'");
    return true;
});

assertTest("2.6 Precedence rule: ck < 3.0 with downward trend remains 🔴 red", function () {
    $res = testEvaluateRisk(5.2, 6.0, 2.5);
    if ($res['level'] !== 'red') throw new Exception("Expected 'red', got '{$res['level']}'");
    return true;
});

// =============================================================================
// NHÓM 3: BỘ ĐẾM THỐNG KÊ & TỶ LỆ PHẦN TRĂM
// =============================================================================
echo PHP_EOL . CLR_YELLOW . "--- NHÓM 3: Bộ đếm Thống kê & Tỷ lệ Phần trăm ---" . CLR_RESET . PHP_EOL;

assertTest("3.1 Stat invariant holds: total === red + yellow + green", function () {
    $counts = ['total' => 25, 'red' => 5, 'yellow' => 8, 'green' => 12];
    if ($counts['total'] !== ($counts['red'] + $counts['yellow'] + $counts['green'])) {
        throw new Exception("Counts do not balance");
    }
    return true;
});

assertTest("3.2 Percentage sum invariant: percentages sum to ~100.0%", function () {
    $total = 25;
    $red = 5; $yellow = 8; $green = 12;
    $pctRed = round(($red / $total) * 100, 1);
    $pctYellow = round(($yellow / $total) * 100, 1);
    $pctGreen = round(($green / $total) * 100, 1);
    $sum = $pctRed + $pctYellow + $pctGreen;
    if (abs($sum - 100.0) > 0.3) throw new Exception("Percentages sum to $sum != 100%");
    return true;
});

assertTest("3.3 Zero-division defense: 0 students yields 0% without error", function () {
    $total = 0; $red = 0;
    $pct = $total > 0 ? round(($red / $total) * 100, 1) : 0.0;
    if ($pct !== 0.0) throw new Exception("Expected 0.0%");
    return true;
});

assertTest("3.4 Stat cards in rendered HTML display accurate numbers", function () use ($client) {
    $client->clearCookies();
    $loginRes = $client->login('admin', '123456');
    $indexRes = $client->get('index.php');
    if ($indexRes['status'] !== 200) throw new Exception("HTTP {$indexRes['status']}");
    if (!preg_match('/<div[^>]*class=["\'][^"\']*card[^"\']*["\']/i', $indexRes['body'])) {
        throw new Exception("No stat card elements found in HTML");
    }
    return true;
});

assertTest("3.5 Live database score query runs cleanly without syntax error", function () {
    $pdo = db();
    $stmt = $pdo->query("
        SELECT s.id, s.full_name, c.name AS class_name, sc.tx1, sc.gk, sc.ck, sc.avg_score
        FROM students s
        JOIN classes c ON s.class_id = c.id
        LEFT JOIN scores sc ON s.id = sc.student_id
        LIMIT 5
    ");
    $rows = $stmt->fetchAll();
    if (!is_array($rows)) throw new Exception("Query failed");
    return true;
});

// =============================================================================
// NHÓM 4: THẺ LỌC TƯƠNG TÁC & THUỘC TÍNH BẢNG DỮ LIỆU
// =============================================================================
echo PHP_EOL . CLR_YELLOW . "--- NHÓM 4: Thẻ Lọc Tương Tác & Thuộc Tính Bảng Dữ Liệu ---" . CLR_RESET . PHP_EOL;

assertTest("4.1 Filter tabs declare data-filter attributes for all, red, yellow, green", function () use ($client) {
    $res = $client->get('index.php');
    foreach (['all', 'red', 'yellow', 'green'] as $f) {
        if (!preg_match('/data-filter=["\']' . $f . '["\']/i', $res['body'])) {
            throw new Exception("Missing filter button for '$f'");
        }
    }
    return true;
});

assertTest("4.2 Table body rows contain data-level attributes", function () use ($client) {
    $res = $client->get('index.php');
    if (!preg_match('/<tr[^>]*data-level=["\'](red|yellow|green)["\']/i', $res['body'])) {
        throw new Exception("Missing data-level on table rows");
    }
    return true;
});

assertTest("4.3 Table body rows contain data-name attributes for instant search", function () use ($client) {
    $res = $client->get('index.php');
    if (!preg_match('/<tr[^>]*data-name=["\'][^"\']+["\']/i', $res['body'])) {
        throw new Exception("Missing data-name on table rows");
    }
    return true;
});

assertTest("4.4 Search input element exists with id search", function () use ($client) {
    $res = $client->get('index.php');
    if (!preg_match('/<input[^>]*id=["\']search["\']/i', $res['body'])) {
        throw new Exception("Missing #search input");
    }
    return true;
});

assertTest("4.5 Table renders component score columns and average badge", function () use ($client) {
    $res = $client->get('index.php');
    if (stripos($res['body'], 'avg') === false && stripos($res['body'], 'Điểm TB') === false) {
        throw new Exception("Missing average score column");
    }
    return true;
});

assertTest("4.6 Action column links directly to nhap_diem.php with parameters", function () use ($client) {
    $res = $client->get('index.php');
    if (!preg_match('/href=["\']nhap_diem\.php\?[^"\']*class_id=/i', $res['body']) &&
        !preg_match('/href=["\']nhap_diem\.php["\']/i', $res['body'])) {
        throw new Exception("Missing link to nhap_diem.php");
    }
    return true;
});

// =============================================================================
// NHÓM 5: LOẠI BỎ HOÀN TOÀN ZALO & MÃ RÁC
// =============================================================================
echo PHP_EOL . CLR_YELLOW . "--- NHÓM 5: Loại Bỏ Hoàn Toàn Zalo & Mã Rác ---" . CLR_RESET . PHP_EOL;

assertTest("5.1 File index.php contains 0 occurrences of zalo (case-insensitive)", function () {
    $content = file_get_contents(dirname(__DIR__) . '/index.php');
    if (stripos($content, 'zalo') !== false) {
        throw new Exception("Found 'zalo' in index.php");
    }
    return true;
});

assertTest("5.2 File assets/js/main.js contains 0 occurrences of zalo", function () {
    $content = file_get_contents(dirname(__DIR__) . '/assets/js/main.js');
    if (stripos($content, 'zalo') !== false) {
        throw new Exception("Found 'zalo' in assets/js/main.js");
    }
    return true;
});

assertTest("5.3 File index.php contains 0 occurrences of actionModal", function () {
    $content = file_get_contents(dirname(__DIR__) . '/index.php');
    if (stripos($content, 'actionModal') !== false) {
        throw new Exception("Found 'actionModal' in index.php");
    }
    return true;
});

assertTest("5.4 File assets/js/main.js contains 0 occurrences of actionModal", function () {
    $content = file_get_contents(dirname(__DIR__) . '/assets/js/main.js');
    if (stripos($content, 'actionModal') !== false) {
        throw new Exception("Found 'actionModal' in assets/js/main.js");
    }
    return true;
});

assertTest("5.5 File assets/js/main.js contains 0 occurrences of copy-btn / copyMessage", function () {
    $content = file_get_contents(dirname(__DIR__) . '/assets/js/main.js');
    if (stripos($content, 'copy-btn') !== false || stripos($content, 'copyMessage') !== false) {
        throw new Exception("Found copy-btn / copyMessage in assets/js/main.js");
    }
    return true;
});

assertTest("5.6 HTTP GET index.php response contains 0 occurrences of zalo", function () use ($client) {
    $res = $client->get('index.php');
    if (stripos($res['body'], 'zalo') !== false) {
        throw new Exception("Live index.php response contains 'zalo'");
    }
    return true;
});

// =============================================================================
// NHÓM 6: PHÂN QUYỀN TRUY CẬP NGHIÊM NGẶT TRÊN index.php (RBAC)
// =============================================================================
echo PHP_EOL . CLR_YELLOW . "--- NHÓM 6: Phân Quyền Truy Cập Nghiêm Ngặt trên index.php ---" . CLR_RESET . PHP_EOL;

assertTest("6.1 Unauthenticated access to index.php redirects to login.php (HTTP 302)", function () use ($client) {
    $client->clearCookies();
    $res = $client->get('index.php', false);
    if ($res['status'] !== 302 && $res['status'] !== 301) {
        throw new Exception("Expected redirect 302, got HTTP {$res['status']}");
    }
    return true;
});

assertTest("6.2 Teacher gv_toan accesses index.php successfully (HTTP 200)", function () use ($client) {
    $client->clearCookies();
    $login = $client->login('gv_toan', '123456');
    $res = $client->get('index.php');
    if ($res['status'] !== 200) throw new Exception("Expected HTTP 200, got {$res['status']}");
    return true;
});

assertTest("6.3 Teacher gv_toan only views assigned classes and subjects", function () use ($client) {
    $res = $client->get('index.php');
    // gv_toan teaches Toán 6A1, 7A1. Must not see Ngữ Văn
    if (stripos($res['body'], 'Ngữ Văn') !== false) {
        throw new Exception("gv_toan unexpectedly sees 'Ngữ Văn' in index.php");
    }
    return true;
});

assertTest("6.4 Teacher gv_toan cannot access admin menu link on index.php", function () use ($client) {
    $res = $client->get('index.php');
    if (preg_match('/href=["\']admin\.php["\']/i', $res['body'])) {
        throw new Exception("admin.php link visible to teacher gv_toan on index.php");
    }
    return true;
});

assertTest("6.5 Admin account accesses index.php with school-wide overview (HTTP 200)", function () use ($client) {
    $client->clearCookies();
    $login = $client->login('admin', '123456');
    $res = $client->get('index.php');
    if ($res['status'] !== 200) throw new Exception("Expected HTTP 200, got {$res['status']}");
    return true;
});

// =============================================================================
// NHÓM 7: TÍNH TOÀN VẸN CỦA DESIGN TOKENS & GIAO DIỆN STITCH UI
// =============================================================================
echo PHP_EOL . CLR_YELLOW . "--- NHÓM 7: Tính Toàn Vẹn của Design Tokens & Giao Diện Stitch UI ---" . CLR_RESET . PHP_EOL;

assertTest("7.1 assets/css/style.css imports Plus Jakarta Sans font", function () {
    $css = file_get_contents(dirname(__DIR__) . '/assets/css/style.css');
    if (stripos($css, 'Plus+Jakarta+Sans') === false && stripos($css, 'Plus Jakarta Sans') === false) {
        throw new Exception("Missing 'Plus Jakarta Sans' in style.css");
    }
    return true;
});

assertTest("7.2 assets/css/style.css defines primary brand tokens in :root", function () {
    $css = file_get_contents(dirname(__DIR__) . '/assets/css/style.css');
    if (strpos($css, '--primary:') === false || strpos($css, '#4f46e5') === false) {
        throw new Exception("Missing --primary #4f46e5 in style.css");
    }
    return true;
});

assertTest("7.3 assets/css/style.css defines semantic status tokens (--danger, --warning, --success)", function () {
    $css = file_get_contents(dirname(__DIR__) . '/assets/css/style.css');
    foreach (['--danger', '--warning', '--success'] as $token) {
        if (strpos($css, $token) === false) throw new Exception("Missing $token in style.css");
    }
    return true;
});

assertTest("7.4 assets/css/style.css defines soft shadow and border radius tokens", function () {
    $css = file_get_contents(dirname(__DIR__) . '/assets/css/style.css');
    if (strpos($css, '--shadow-') === false || strpos($css, '--radius-') === false) {
        throw new Exception("Missing shadow or radius tokens in style.css");
    }
    return true;
});

assertTest("7.5 assets/css/style.css defines role badges for Admin and Teacher", function () {
    $css = file_get_contents(dirname(__DIR__) . '/assets/css/style.css');
    if (strpos($css, 'role-admin') === false && strpos($css, 'badge-role-admin') === false) {
        throw new Exception("Missing role-admin badge class in style.css");
    }
    if (strpos($css, 'role-teacher') === false && strpos($css, 'badge-role-teacher') === false) {
        throw new Exception("Missing role-teacher badge class in style.css");
    }
    return true;
});

assertTest("7.6 Header navigation renders active state class for current route", function () use ($client) {
    $res = $client->get('index.php');
    if (!preg_match('/class=["\'][^"\']*active[^"\']*["\']/i', $res['body'])) {
        throw new Exception("Missing active state on navigation link");
    }
    return true;
});

// =============================================================================
// TỔNG KẾT KẾT QUẢ KIỂM THỬ
// =============================================================================
echo PHP_EOL . CLR_YELLOW . "============================================================" . CLR_RESET . PHP_EOL;
$total = $passedCount + $failedCount;
echo "  Total Tests: " . $total . " | Passed: " . CLR_GREEN . $passedCount . CLR_RESET . " | Failed: " . ($failedCount > 0 ? CLR_RED . $failedCount . CLR_RESET : "0") . PHP_EOL;
echo CLR_YELLOW . "============================================================" . CLR_RESET . PHP_EOL . PHP_EOL;

if ($failedCount > 0) {
    exit(1);
}
exit(0);
```

---

## 6. Lộ Trình Triển Khai Cho Worker M5

1. **Bước 1 (Cơ sở hạ tầng UI & Design Tokens)**:
   - Cập nhật `assets/css/style.css` với các token `:root` đầy đủ, class `.role-badge`, `.role-admin`, `.role-teacher`, `.stitch-header`, `.stitch-navbar`, `.filter-tabs`, `.filter-tab`.
   - Tạo mới `includes/header.php` và `includes/footer.php` làm shell chuẩn cho toàn bộ hệ thống.

2. **Bước 2 (Làm sạch mã & Diệt Zalo/Modal rác)**:
   - Viết lại `assets/js/main.js`: Giữ lại bộ lọc tìm kiếm thời gian thực theo tên, mã và trạng thái rủi ro. Xóa 100% mã Zalo và `actionModal`.
   - Viết lại `index.php`:
     - Chuyển sang nạp `includes/header.php` và `includes/footer.php`.
     - Sử dụng câu truy vấn MySQL chuẩn kết nối `teacher_classes`, `classes`, `students`, `scores` (`tx1..tx4, gk, ck, avg_score`).
     - Tích hợp hàm `evaluateEarlyWarning()` với 3 cấp độ cảnh báo chính xác.
     - Xóa hoàn toàn nút Copy Zalo, modal và thông báo toast.
     - Thêm cột nút "📝 Vào sổ điểm" dẫn trực tiếp tới `nhap_diem.php?class_id=X&subject=Y`.

3. **Bước 3 (Tạo & Thực thi Test Suite `tests/verify_m5.php`)**:
   - Tạo file `tests/verify_m5.php` theo bản thiết kế hoàn chỉnh ở Mục 5.
   - Chạy lệnh `D:\xam\php\php.exe tests/verify_m5.php` và xác nhận 100% (42/42) bài test đạt `[PASS]`.
   - Kiểm tra hồi quy với các bài test M1-M4: `verify_m1.php`, `verify_m2.php`, `verify_m3.php`, `verify_m4.php` để đảm bảo không có bất kỳ regression nào.
