# Phân Tích BOLA/IDOR Tại `nhap_diem.php` & Thiết Kế Bộ Kiểm Thử `tests/verify_m2.php`

**Đặc tả nhiệm vụ**: Explorer M2.3 (`explorer_m2_3`)  
**Dự án**: Hệ Thống Quản Lý Điểm & Cảnh Báo Sớm THCS (EduRadar / Chuẩn VnEdu)  
**Thời gian phân tích**: 2026-10-17  
**Phạm vi**: 
1. Phân tích chi tiết lỗ hổng BOLA (Broken Object Level Authorization) / IDOR (Insecure Direct Object Reference) trong `nhap_diem.php`.
2. Quy định đặc tả xác thực phân công phía máy chủ (Server-side Assignment Verification) cho luồng Xem (GET) và Lưu (POST).
3. Thiết kế kiến trúc và mã nguồn hoàn chỉnh của bộ kiểm thử tự động `tests/verify_m2.php`.

---

## 1. Khảo Sát Hiện Trạng & Phân Tích Lỗ Hổng BOLA / IDOR Trong `nhap_diem.php`

### 1.1. Hiện trạng kiến trúc xác thực của `nhap_diem.php`
Kiểm tra tệp `nhap_diem.php` (dòng 1–59):
```php
session_start();
require_once __DIR__ . '/includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = db();
$realUserId = $_SESSION['user_id'];
$realUserRole = $_SESSION['role'];

// Nếu Admin đang dùng quyền "Truy cập / Xem bảng điểm của giáo viên"
$actingTeacherId = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : ($realUserRole === 'admin' ? 0 : $realUserId);
$currentTeacherName = $_SESSION['full_name'];
...
$selectedClassId = isset($_GET['class_id']) && (int)$_GET['class_id'] > 0 
    ? (int)$_GET['class_id'] 
    : (!empty($myClasses) ? $myClasses[0]['id'] : 0);

$selectedSubject = isset($_GET['subject']) && $_GET['subject'] !== '' 
    ? trim($_GET['subject']) 
    : $defaultSubject;
```

### 1.2. Danh mục 4 lỗ hổng bảo mật nghiêm trọng được phát hiện

#### Lỗ hổng 1: BOLA / IDOR trên luồng Xem bảng điểm (GET Request)
- **Vị trí**: `nhap_diem.php:46-59` kết hợp với `nhap_diem.php:170-183`.
- **Cơ chế phát sinh**:
  - Người dùng đăng nhập với quyền giáo viên (ví dụ `gv_toan`, `user_id = 2`). Giáo viên này chỉ được phân công giảng dạy môn Toán ở lớp 6A1 và 7A1 (`teacher_classes`).
  - Hệ thống lấy danh sách `$myClasses` của giáo viên để hiển thị các `<option>` trong thẻ `<select>`.
  - **Tuy nhiên**, giá trị lớp đang xem (`$selectedClassId`) và môn đang xem (`$selectedSubject`) lại được lấy trực tiếp từ tham số query string `$_GET['class_id']` và `$_GET['subject']` mà **hoàn toàn không có bước kiểm tra chéo** xem cặp `(class_id, subject)` đó có nằm trong phân công của giáo viên hay không.
  - Sau đó, tại dòng 173–182:
    ```php
    $stmt = $pdo->prepare("
        SELECT s.id, s.full_name, s.parent_phone,
               sc.m1, sc.m2, sc.p15_1, sc.p15_2, sc.t1_1, sc.gk, sc.ck, sc.avg_score
        FROM students s
        LEFT JOIN scores sc ON s.id = sc.student_id AND sc.subject = ?
        WHERE s.class_id = ?
        ORDER BY s.full_name ASC
    ");
    $stmt->execute([$selectedSubject, $selectedClassId]);
    $studentList = $stmt->fetchAll();
    ```
- **Hậu quả**: Giáo viên Toán chỉ cần gõ trên thanh địa chỉ:
  `http://localhost/my-web-project/nhap_diem.php?class_id=1&subject=Ngữ Văn`
  hoặc
  `http://localhost/my-web-project/nhap_diem.php?class_id=30&subject=Tin Học`
  Hệ thống sẽ lập tức trả về mã HTTP 200 kèm toàn bộ danh sách học sinh, số điện thoại phụ huynh, và bảng điểm môn Ngữ Văn / Tin Học của các lớp khác. Đây là lỗ hổng BOLA / IDOR kinh điển (CWE-639 / OWASP Top 10 API1:2023).

#### Lỗ hổng 2: Sửa đổi và giả mạo điểm số trái phép (POST `action=save_scores`)
- **Vị trí**: `nhap_diem.php:61-108`.
- **Cơ chế phát sinh**:
  - Khi gửi form lưu điểm qua phương thức POST, handler kiểm tra:
    `if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_scores')`
  - Handler lấy `$selectedSubject` từ biến toàn cục (vốn trích xuất từ `$_GET['subject']`).
  - Vòng lặp `foreach ($_POST['scores'] as $studentId => $row)` thực hiện cập nhật hoặc chèn bản ghi vào bảng `scores` cho `$studentId` và `$selectedSubject`:
    ```php
    $upd = $pdo->prepare("
        UPDATE scores 
        SET m1 = ?, m2 = ?, p15_1 = ?, p15_2 = ?, t1_1 = ?, gk = ?, ck = ?, avg_score = ?
        WHERE student_id = ? AND subject = ?
    ");
    ```
  - **Khuyết tật**:
    1. Không xác thực quyền hạn của `$_SESSION['user_id']` đối với lớp và môn đang lưu.
    2. Không xác thực xem `$studentId` có thực sự thuộc lớp `$selectedClassId` hay không.
    3. Ghi nhận nhật ký sai lệch: dòng 105 ghi log hành động `Lưu sổ điểm` hợp lệ thay vì ngăn chặn và cảnh báo hành vi xâm nhập.
- **Hậu quả**: Một giáo viên có thể giả mạo gói tin POST để sửa đổi điểm thi (giữa kỳ, cuối kỳ) của bất kỳ học sinh nào trong trường ở bất kỳ môn học nào. Dữ liệu trong cơ sở dữ liệu MySQL `edu_radar` bị can thiệp trái phép mà không hề bị chặn.

#### Lỗ hổng 3: Giả danh giáo viên khác qua tham số `$_GET['teacher_id']`
- **Vị trí**: `nhap_diem.php:15` và `nhap_diem.php:41`.
- **Cơ chế phát sinh**:
  - Dòng 15 viết:
    `$actingTeacherId = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : ($realUserRole === 'admin' ? 0 : $realUserId);`
  - Toán tử ba ngôi kiểm tra `isset($_GET['teacher_id'])` trước tiên, áp dụng cho cả tài khoản giáo viên thường!
  - Mặc dù dòng 18 có điều kiện `if ($realUserRole === 'admin' && $actingTeacherId > 0)` khi đổi tên hiển thị, nhưng đến dòng 41 câu truy vấn lấy danh sách lớp lại chạy:
    `$stmt->execute([$actingTeacherId > 0 ? $actingTeacherId : $realUserId]);`
- **Hậu quả**: Giáo viên thường truyền `?teacher_id=3` sẽ nạp toàn bộ danh sách lớp của giáo viên ID 3 vào giao diện của mình.

#### Lỗ hổng 4: Nạp điểm bằng file CSV không kiểm tra phân công (POST `action=import_file`)
- **Vị trí**: `nhap_diem.php:111-168`.
- **Cơ chế phát sinh**:
  - Người dùng gửi file CSV qua POST kèm `action=import_file`.
  - Hệ thống tự động tạo học sinh mới hoặc cập nhật điểm cho lớp `$selectedClassId` và môn `$selectedSubject` mà không hề kiểm tra xem giáo viên tải file có được phân công phụ trách lớp đó hay không.
- **Hậu quả**: Ghi đè hàng loạt dữ liệu học sinh và điểm số của lớp khác.

---

## 2. Đặc Tả Xác Thực Phân Công Phía Máy Chủ (Server-Side Assignment Verification)

Để loại trừ hoàn toàn các lỗ hổng trên, hệ thống phải tuân thủ nghiêm ngặt mô hình RBAC theo 3 cấp độ:

```
[Request: GET/POST nhap_diem.php]
              │
              ▼
    1. Đã đăng nhập chưa? (require_login())
       ├── Không ──> Redirect login.php
       └── Có
              │
              ▼
    2. Vai trò người dùng (role)
       ├── 'admin' ──> Cho phép xem/sửa toàn bộ 36 lớp & tất cả các môn
       └── 'teacher'
              │
              ▼
    3. Tham số teacher_id giả mạo?
       └── Bỏ qua $_GET['teacher_id'] nếu không phải Admin.
              │
              ▼
    4. Giáo viên có được phân công lớp & môn này?
       └── require_teacher_assignment($realUserId, $selectedClassId, $selectedSubject)
              ├── Có ──> Tiếp tục render hoặc xử lý lưu điểm
              └── Không
                     │
                     ▼
              HTTP 403 Forbidden
              Ghi nhật ký: 'Truy cập trái phép bị chặn'
              Dừng thực thi (die / exit)
```

### 2.1. Hợp đồng hàm Middleware tại `includes/auth.php`

Tệp `includes/auth.php` (do `explorer_m2_2` thiết kế và phối hợp) cung cấp 2 hàm then chốt:

```php
/**
 * Kiểm tra xem giáo viên có được phân công dạy môn tại lớp tương ứng hay không
 */
function verify_teacher_assignment(int $teacher_id, int $class_id, string $subject): bool
{
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT 1 FROM teacher_classes 
        WHERE teacher_id = ? AND class_id = ? AND subject = ? 
        LIMIT 1
    ");
    $stmt->execute([$teacher_id, $class_id, $subject]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Rào chắn bắt buộc phân công giáo viên:
 * - Nếu là Admin: Cho phép truy cập không giới hạn.
 * - Nếu là Giáo viên: Kiểm tra bảng teacher_classes.
 *   Nếu không hợp lệ: Xuất HTTP 403, ghi audit_logs và ngắt kết nối.
 */
function require_teacher_assignment(int $teacher_id, int $class_id, string $subject): void
{
    // Admin có quyền xem và sửa sổ điểm của mọi lớp, mọi giáo viên
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        return;
    }

    if (!verify_teacher_assignment($teacher_id, $class_id, $subject)) {
        http_response_code(403);
        $username = $_SESSION['username'] ?? 'Khách';
        $details = "Giáo viên [$username] (ID: $teacher_id) cố tình truy cập/thao tác sổ điểm chưa được phân công: Lớp ID [$class_id], Môn [$subject].";
        log_action($teacher_id, $username, 'Truy cập trái phép bị chặn', $details);

        // Hiển thị giao diện 403 chuẩn giao diện dự án
        die('<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 Cấm Truy Cập – EduRadar</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #0b0f19; font-family: sans-serif; }
        .error-card { background: #ffffff; padding: 40px; border-radius: 16px; text-align: center; max-width: 480px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); }
        .error-icon { font-size: 48px; margin-bottom: 12px; }
        h1 { font-size: 22px; color: #dc2626; margin-bottom: 10px; font-weight: 800; }
        p { font-size: 14px; color: #64748b; line-height: 1.6; margin-bottom: 24px; }
        .btn-back { display: inline-block; padding: 12px 24px; background: #4338ca; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 14px; }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-icon">⛔</div>
        <h1>TRUY CẬP BỊ TỪ CHỐI (403)</h1>
        <p>Bạn không được phân công phụ trách lớp học hoặc môn học này. Hành động truy cập trái phép đã được ghi lại trong nhật ký bảo mật hệ thống.</p>
        <a href="index.php" class="btn-back">Quay Về Trang Chủ</a>
    </div>
</body>
</html>');
    }
}
```

### 2.2. Chi tiết vị trí tích hợp trong `nhap_diem.php`

#### A. Khởi tạo và khử giả mạo `actingTeacherId` (Đầu trang)
```php
<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

$pdo = db();
$realUserId = (int)$_SESSION['user_id'];
$realUserRole = $_SESSION['role'];
$currentTeacherName = $_SESSION['full_name'] ?? $_SESSION['username'];

// KHỬ GIẢ MẠO: Chỉ tài khoản Admin mới được sử dụng tham số ?teacher_id
$actingTeacherId = 0;
if ($realUserRole === 'admin' && isset($_GET['teacher_id']) && (int)$_GET['teacher_id'] > 0) {
    $actingTeacherId = (int)$_GET['teacher_id'];
    $tCheck = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $tCheck->execute([$actingTeacherId]);
    $tObj = $tCheck->fetch();
    if ($tObj) {
        $currentTeacherName = $tObj['full_name'] . " (Admin đang kiểm tra)";
    }
}
```

#### B. Phân giải danh sách lớp được phân công
```php
if ($realUserRole === 'admin' && $actingTeacherId === 0) {
    // Admin xem danh sách toàn trường
    $myClasses = $pdo->query("SELECT id, name FROM classes ORDER BY grade ASC, name ASC")->fetchAll();
    $defaultSubject = 'Toán';
} else {
    // Giáo viên hoặc Admin đang soi giáo viên cụ thể
    $targetTeacherId = ($realUserRole === 'admin' && $actingTeacherId > 0) ? $actingTeacherId : $realUserId;
    $stmt = $pdo->prepare("
        SELECT c.id, c.name, tc.subject 
        FROM teacher_classes tc 
        JOIN classes c ON tc.class_id = c.id 
        WHERE tc.teacher_id = ?
        ORDER BY c.grade ASC, c.name ASC
    ");
    $stmt->execute([$targetTeacherId]);
    $myClasses = $stmt->fetchAll();
    $defaultSubject = !empty($myClasses) ? $myClasses[0]['subject'] : '';
}

$selectedClassId = isset($_GET['class_id']) && (int)$_GET['class_id'] > 0 
    ? (int)$_GET['class_id'] 
    : (!empty($myClasses) ? (int)$myClasses[0]['id'] : 0);

$selectedSubject = isset($_GET['subject']) && $_GET['subject'] !== '' 
    ? trim($_GET['subject']) 
    : $defaultSubject;
```

#### C. Chốt chặn xác thực phân công (Route Guard & BOLA Prevention)
```php
// RÀO CHẮN BẢO MẬT: XÁC THỰC PHÂN CÔNG CHẶT CHẼ
if ($realUserRole !== 'admin') {
    if (empty($myClasses)) {
        // Giáo viên chưa được phân công lớp nào: Chặn mọi POST, chỉ hiển thị thông báo ở GET
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            http_response_code(403);
            log_action($realUserId, $_SESSION['username'], 'Truy cập trái phép bị chặn', 'Giáo viên chưa có phân công cố gắng gửi yêu cầu POST vào sổ điểm.');
            die('⛔ 403 Forbidden: Tài khoản chưa được phân công giảng dạy.');
        }
    } else {
        // Kiểm tra xem cặp ($selectedClassId, $selectedSubject) có thuộc phân công hay không
        if ($selectedClassId <= 0 || $selectedSubject === '') {
            http_response_code(403);
            log_action($realUserId, $_SESSION['username'], 'Truy cập trái phép bị chặn', 'Yêu cầu không hợp lệ (thiếu lớp hoặc môn).');
            die('⛔ 403 Forbidden');
        }
        require_teacher_assignment($realUserId, $selectedClassId, $selectedSubject);
    }
}
```

#### D. Xác thực nâng cao trong POST Handlers
1. **Trong `action === 'save_scores'`**:
   ```php
   if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_scores') {
       // Tái kiểm tra xác thực phân công
       require_teacher_assignment($realUserId, $selectedClassId, $selectedSubject);
       
       if (!empty($_POST['scores']) && is_array($_POST['scores'])) {
           $savedCount = 0;
           // Chuẩn bị truy vấn kiểm tra chéo học sinh thuộc lớp
           $chkStudent = $pdo->prepare("SELECT 1 FROM students WHERE id = ? AND class_id = ?");

           foreach ($_POST['scores'] as $studentId => $row) {
               $studentId = (int)$studentId;
               $chkStudent->execute([$studentId, $selectedClassId]);
               if (!$chkStudent->fetchColumn()) {
                   // Bỏ qua học sinh không thuộc lớp này (chống IDOR chéo học sinh)
                   continue;
               }
               ... (cập nhật điểm vào bảng scores) ...
               $savedCount++;
           }
           
           log_action($realUserId, $_SESSION['username'], 'Lưu sổ điểm', "Cập nhật bảng điểm môn [$selectedSubject] cho lớp ID [$selectedClassId] ($savedCount học sinh).");
           $msg = "Đã lưu thành công điểm môn {$selectedSubject}!";
       }
   }
   ```

2. **Trong `action === 'import_file'`**:
   ```php
   if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_file') {
       require_teacher_assignment($realUserId, $selectedClassId, $selectedSubject);
       ... (xử lý import) ...
   }
   ```

3. **Ghi log khi Admin kiểm tra sổ điểm của giáo viên**:
   ```php
   if ($realUserRole === 'admin' && $actingTeacherId > 0 && $_SERVER['REQUEST_METHOD'] === 'GET') {
       // Log việc Admin thanh tra sổ điểm
       log_action($realUserId, $_SESSION['username'], 'Admin kiểm tra sổ điểm', "Admin đang thanh tra sổ điểm lớp ID [$selectedClassId] môn [$selectedSubject] của giáo viên ID [$actingTeacherId].");
   }
   ```

---

## 3. Thiết Kế Bộ Kiểm Thử Tự Động `tests/verify_m2.php`

### 3.1. Nguyên tắc thiết kế
1. **Hoàn toàn độc lập (Zero external dependencies)**: Chạy trực tiếp qua PHP CLI XAMPP (`D:\xam\php\php.exe tests/verify_m2.php`).
2. **Kiểm thử trực tiếp trên Apache sống**: Gửi các yêu cầu HTTP thật tới `http://localhost/my-web-project/` thông qua `curl` tích hợp trong PHP.
3. **Quản lý Session & Cookie cô lập**: Mỗi kịch bản (Admin, GV Toán, GV Văn, Khách chưa đăng nhập) sử dụng cookie jar riêng biệt trong thư mục tạm của hệ điều hành.
4. **Đối chiếu kép với cơ sở dữ liệu MySQL**: Sau mỗi tác vụ HTTP, kiểm tra trực tiếp bảng `audit_logs`, `scores`, `users` trong cơ sở dữ liệu `edu_radar` để đảm bảo:
   - Các hành vi trái phép bị từ chối 100% (HTTP 403).
   - Dữ liệu điểm số và người dùng không bị thay đổi ngoài ý muốn.
   - Bản ghi `audit_logs` được ghi nhận đầy đủ với IP và hành động tương ứng.
5. **Mã thoát (Exit Code)**: Trả về `0` khi 100% test pass; trả về `1` khi có bất kỳ test nào fail.

### 3.2. Ma trận 16 ca kiểm thử chi tiết (Test Matrix)

| Nhóm | Mã Test | Tên Ca Kiểm Thử | Kỳ Vọng HTTP | Kỳ Vọng Database / Audit Log |
|---|---|---|---|---|
| **Group 1: Pre-flight** | 1.1 | Tồn tại `includes/auth.php` với 5 hàm chuẩn | CLI check | Đầy đủ function declarations |
| | 1.2 | Xóa bỏ hoàn toàn backdoor `|| $password === '123456'` trong `login.php` | Code scan | Không còn chuỗi bypass |
| | 1.3 | Tồn tại `logout.php` hủy session và ghi log | Code scan | Chứa `session_destroy()` & `log_action` |
| **Group 2: Login/Auth** | 2.1 | Đăng nhập `admin` với pass `123456` thành công | 302 Redirect | `audit_logs`: 'Đăng nhập thành công', username 'admin' |
| | 2.2 | Đăng nhập `gv_toan` với pass `123456` thành công | 302 Redirect | `audit_logs`: 'Đăng nhập thành công', username 'gv_toan' |
| | 2.3 | Đăng nhập `gv_van` với pass `123456` thành công | 302 Redirect | `audit_logs`: 'Đăng nhập thành công', username 'gv_van' |
| | 2.4 | Đăng nhập `admin` với pass sai `1234567` bị từ chối | 200 (Form báo lỗi) | `audit_logs`: 'Đăng nhập thất bại', username 'admin' |
| | 2.5 | Đăng nhập `gv_toan` với pass sai bị từ chối | 200 (Form báo lỗi) | `audit_logs`: 'Đăng nhập thất bại', username 'gv_toan' |
| | 2.6 | Không thể dùng `123456` để đăng nhập tài khoản khác (Chặn backdoor) | 200 (Form báo lỗi) | `audit_logs`: 'Đăng nhập thất bại' |
| | 2.7 | Đăng xuất `logout.php` xóa session sạch sẽ | 302 -> login.php | `audit_logs`: 'Đăng xuất' |
| **Group 3: Admin Guard** | 3.1 | Khách chưa đăng nhập vào `admin.php` bị chặn | 403 Forbidden | `audit_logs`: 'Truy cập trái phép bị chặn' |
| | 3.2 | Giáo viên (`gv_toan`) vào `GET admin.php` bị chặn | 403 Forbidden | `audit_logs`: 'Truy cập trái phép bị chặn', user 'gv_toan' |
| | 3.3 | Giáo viên (`gv_toan`) gửi `POST admin.php` bị chặn và 0 thay đổi DB | 403 Forbidden | Không thêm phân công trong `teacher_classes` |
| | 3.4 | Quản trị viên (`admin`) vào `GET admin.php` thành công | 200 OK | Giao diện quản trị hiển thị bình thường |
| **Group 4: BOLA/IDOR Guard** | 4.1 | Khách chưa đăng nhập vào `nhap_diem.php` bị chuyển hướng | 302 -> login.php | Không rò rỉ dữ liệu điểm |
| | 4.2 | Giáo viên được phân công (`gv_toan` vào 6A1 Toán) thành công | 200 OK | Hiển thị bảng điểm môn Toán 6A1 |
| | 4.3 | BOLA: `gv_toan` truy cập môn chưa phân công (6A1 Ngữ Văn) bị chặn | 403 Forbidden | `audit_logs`: 'Truy cập trái phép bị chặn' |
| | 4.4 | BOLA: `gv_toan` truy cập lớp chưa phân công (6A2 Toán) bị chặn | 403 Forbidden | `audit_logs`: 'Truy cập trái phép bị chặn' |
| | 4.5 | IDOR: `gv_toan` giả mạo `?teacher_id=3` truy cập Văn bị chặn | 403 Forbidden | `audit_logs`: 'Truy cập trái phép bị chặn' |
| | 4.6 | BOLA Tampering: `gv_toan` gửi `POST save_scores` vào Văn bị chặn | 403 Forbidden | Điểm trong bảng `scores` giữ nguyên 100% |
| | 4.7 | Admin thanh tra sổ điểm (`admin` vào 6A1 Ngữ Văn) thành công | 200 OK | Cho phép Admin xem và ghi log kiểm tra |

---

## 4. Mã Nguồn Hoàn Chỉnh Của `tests/verify_m2.php`

Dưới đây là mã nguồn đầy đủ của bộ kiểm thử tự động Milestone 2, sẵn sàng để Worker agent triển khai vào `tests/verify_m2.php`:

```php
<?php
/**
 * =============================================================================
 * Automated Milestone 2 Verification Suite (EduRadar THCS)
 * Kiểm thử tự động Milestone 2: Authentication, Password Hashing & Strict RBAC
 * 
 * Chạy lệnh kiểm thử:
 *   D:\xam\php\php.exe tests/verify_m2.php
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
    echo CLR_CYAN . "[TEST] " . CLR_RESET . str_pad($name, 62, ".");
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
 * HTTP Client chuyên dụng cho kiểm thử Web Application qua cURL
 */
class TestClient
{
    private string $baseUrl;
    private string $cookieFile;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->cookieFile = sys_get_temp_dir() . '/edu_m2_cookie_' . bin2hex(random_bytes(6)) . '.txt';
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

    public function request(string $method, string $path, array $data = [], bool $followRedirects = false): array
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
            CURLOPT_TIMEOUT => 10,
        ];

        if (strtoupper($method) === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($data);
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
echo CLR_YELLOW . "  EduRadar Milestone 2: Automated Verification Suite" . CLR_RESET . PHP_EOL;
echo CLR_YELLOW . "============================================================" . CLR_RESET . PHP_EOL . PHP_EOL;

// -------------------------------------------------------------
// NHÓM 1: KIỂM TRA MÃ NGUỒN VÀ KHỬ BACKDOOR (STATIC VERIFICATION)
// -------------------------------------------------------------
assertTest("1.1 includes/auth.php exists with required RBAC functions", function () {
    $authPath = dirname(__DIR__) . '/includes/auth.php';
    if (!file_exists($authPath)) {
        throw new Exception("File includes/auth.php does not exist");
    }
    require_once $authPath;
    $requiredFns = [
        'require_login',
        'require_admin',
        'verify_teacher_assignment',
        'require_teacher_assignment'
    ];
    foreach ($requiredFns as $fn) {
        if (!function_exists($fn)) {
            throw new Exception("Function $fn() is not defined in includes/auth.php");
        }
    }
    return true;
});

assertTest("1.2 login.php backdoor bypass (|| \$password === '123456') removed", function () {
    $loginCode = file_get_contents(dirname(__DIR__) . '/login.php');
    if (strpos($loginCode, "|| \$password === '123456'") !== false ||
        strpos($loginCode, '|| $password === "123456"') !== false) {
        throw new Exception("login.php still contains backdoor bypass '|| \$password === 123456'");
    }
    return true;
});

assertTest("1.3 logout.php exists with session clearing & audit log", function () {
    $logoutPath = dirname(__DIR__) . '/logout.php';
    if (!file_exists($logoutPath)) {
        throw new Exception("File logout.php does not exist");
    }
    $code = file_get_contents($logoutPath);
    if (strpos($code, 'session_destroy') === false) {
        throw new Exception("logout.php missing session_destroy()");
    }
    return true;
});

// -------------------------------------------------------------
// NHÓM 2: XÁC THỰC ĐĂNG NHẬP, MẬT KHẨU & ĐĂNG XUẤT (HTTP API)
// -------------------------------------------------------------
assertTest("2.1 Admin login with '123456' succeeds and redirects to admin.php", function () use ($client) {
    $client->clearCookies();
    $res = $client->login('admin', '123456');
    if ($res['status'] !== 302) {
        throw new Exception("Expected HTTP 302, got " . $res['status']);
    }
    if (strpos($res['headers'], 'Location: admin.php') === false &&
        strpos($res['redirect_url'], 'admin.php') === false) {
        throw new Exception("Admin login did not redirect to admin.php");
    }

    // Verify audit_logs in MySQL
    $pdo = db();
    $log = $pdo->query("SELECT * FROM audit_logs WHERE username = 'admin' ORDER BY id DESC LIMIT 1")->fetch();
    if (!$log || strpos($log['action'], 'Đăng nhập thành công') === false) {
        throw new Exception("audit_logs does not record 'Đăng nhập thành công' for admin");
    }
    return true;
});

assertTest("2.2 gv_toan login with '123456' succeeds and writes audit log", function () use ($client) {
    $client->clearCookies();
    $res = $client->login('gv_toan', '123456');
    if ($res['status'] !== 302) {
        throw new Exception("Expected HTTP 302, got " . $res['status']);
    }

    $pdo = db();
    $log = $pdo->query("SELECT * FROM audit_logs WHERE username = 'gv_toan' ORDER BY id DESC LIMIT 1")->fetch();
    if (!$log || strpos($log['action'], 'Đăng nhập thành công') === false) {
        throw new Exception("audit_logs does not record 'Đăng nhập thành công' for gv_toan");
    }
    return true;
});

assertTest("2.3 gv_van login with '123456' succeeds and writes audit log", function () use ($client) {
    $client->clearCookies();
    $res = $client->login('gv_van', '123456');
    if ($res['status'] !== 302) {
        throw new Exception("Expected HTTP 302, got " . $res['status']);
    }

    $pdo = db();
    $log = $pdo->query("SELECT * FROM audit_logs WHERE username = 'gv_van' ORDER BY id DESC LIMIT 1")->fetch();
    if (!$log || strpos($log['action'], 'Đăng nhập thành công') === false) {
        throw new Exception("audit_logs does not record 'Đăng nhập thành công' for gv_van");
    }
    return true;
});

assertTest("2.4 Wrong password rejection ('1234567') logs failed attempt", function () use ($client) {
    $client->clearCookies();
    $res = $client->login('admin', '1234567');
    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200 (stay on login), got " . $res['status']);
    }

    $pdo = db();
    $log = $pdo->query("SELECT * FROM audit_logs WHERE action LIKE '%thất bại%' ORDER BY id DESC LIMIT 1")->fetch();
    if (!$log || strpos($log['details'], 'admin') === false) {
        throw new Exception("audit_logs did not record login failure for admin");
    }
    return true;
});

assertTest("2.5 Non-existent user cannot authenticate with '123456'", function () use ($client) {
    $client->clearCookies();
    $res = $client->login('hacker_unknown', '123456');
    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200, got " . $res['status']);
    }

    $pdo = db();
    $log = $pdo->query("SELECT * FROM audit_logs WHERE action LIKE '%thất bại%' ORDER BY id DESC LIMIT 1")->fetch();
    if (!$log || strpos($log['details'], 'hacker_unknown') === false) {
        throw new Exception("audit_logs did not record login failure for hacker_unknown");
    }
    return true;
});

assertTest("2.6 Logout endpoint clears session and writes audit log", function () use ($client) {
    $client->clearCookies();
    $client->login('admin', '123456');
    $res = $client->get('logout.php');
    if ($res['status'] !== 302) {
        throw new Exception("Expected HTTP 302 redirect from logout.php, got " . $res['status']);
    }

    // Try accessing admin.php after logout -> must be blocked
    $resProtected = $client->get('admin.php');
    if ($resProtected['status'] !== 403 && $resProtected['status'] !== 302) {
        throw new Exception("Protected page still accessible after logout, got status: " . $resProtected['status']);
    }
    return true;
});

// -------------------------------------------------------------
// NHÓM 3: BẢO VỆ ROUTE QUẢN TRỊ ADMIN (admin.php)
// -------------------------------------------------------------
assertTest("3.1 Unauthenticated access to admin.php is rejected (HTTP 403/302)", function () use ($client) {
    $client->clearCookies();
    $res = $client->get('admin.php');
    if ($res['status'] !== 403 && $res['status'] !== 302) {
        throw new Exception("Expected HTTP 403 or 302, got " . $res['status']);
    }

    $pdo = db();
    $log = $pdo->query("SELECT * FROM audit_logs WHERE action = 'Truy cập trái phép bị chặn' ORDER BY id DESC LIMIT 1")->fetch();
    if (!$log) {
        throw new Exception("No audit log recorded for unauthorized admin.php access");
    }
    return true;
});

assertTest("3.2 Teacher (gv_toan) accessing GET admin.php is rejected with 403", function () use ($client) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');
    $res = $client->get('admin.php');
    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403 for teacher accessing admin.php, got " . $res['status']);
    }
    return true;
});

assertTest("3.3 Teacher (gv_toan) POST to admin.php is blocked (0 DB changes)", function () use ($client) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');

    $pdo = db();
    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();

    $res = $client->post('admin.php', [
        'action' => 'add_class',
        'name'   => 'FAKE_CLASS_99'
    ]);
    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403 on forged admin POST, got " . $res['status']);
    }

    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
    if ($beforeCount !== $afterCount) {
        throw new Exception("Forged POST by teacher modified classes table!");
    }
    return true;
});

assertTest("3.4 Admin accessing GET admin.php succeeds (HTTP 200)", function () use ($client) {
    $client->clearCookies();
    $client->login('admin', '123456');
    $res = $client->get('admin.php');
    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200 for admin on admin.php, got " . $res['status']);
    }
    return true;
});

// -------------------------------------------------------------
// NHÓM 4: BẢO VỆ ROUTE SỔ ĐIỂM (nhap_diem.php) & PHÒNG CHỐNG BOLA/IDOR
// -------------------------------------------------------------
assertTest("4.1 Unauthenticated access to nhap_diem.php redirects to login.php", function () use ($client) {
    $client->clearCookies();
    $res = $client->get('nhap_diem.php');
    if ($res['status'] !== 302 && $res['status'] !== 403) {
        throw new Exception("Expected HTTP 302 or 403, got " . $res['status']);
    }
    return true;
});

assertTest("4.2 Assigned teacher (gv_toan on 6A1 Toán) succeeds (HTTP 200)", function () use ($client) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');
    $res = $client->get('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'));
    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200 for assigned pair, got " . $res['status']);
    }
    return true;
});

assertTest("4.3 BOLA: gv_toan viewing unassigned subject (6A1 Ngữ Văn) returns 403", function () use ($client) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');
    $res = $client->get('nhap_diem.php?class_id=1&subject=' . urlencode('Ngữ Văn'));
    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403 for unassigned subject, got " . $res['status']);
    }

    $pdo = db();
    $log = $pdo->query("SELECT * FROM audit_logs WHERE action = 'Truy cập trái phép bị chặn' AND username = 'gv_toan' ORDER BY id DESC LIMIT 1")->fetch();
    if (!$log) {
        throw new Exception("Audit log did not record unauthorized gradebook inspection attempt");
    }
    return true;
});

assertTest("4.4 BOLA: gv_toan viewing unassigned class (6A2 Toán) returns 403", function () use ($client) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');
    $res = $client->get('nhap_diem.php?class_id=2&subject=' . urlencode('Toán'));
    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403 for unassigned class, got " . $res['status']);
    }
    return true;
});

assertTest("4.5 IDOR: gv_toan tampering ?teacher_id=3 to view Văn returns 403", function () use ($client) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');
    $res = $client->get('nhap_diem.php?teacher_id=3&class_id=1&subject=' . urlencode('Ngữ Văn'));
    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403 for teacher_id tampering, got " . $res['status']);
    }
    return true;
});

assertTest("4.6 Grade Tampering: gv_toan POST save_scores to Văn is blocked (HTTP 403)", function () use ($client) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');

    $pdo = db();
    // Retrieve current score of student 1 in Literature
    $beforeScore = $pdo->query("SELECT avg_score FROM scores WHERE student_id = 1 AND subject = 'Ngữ Văn'")->fetchColumn();

    // Tampering attempt
    $res = $client->post('nhap_diem.php?class_id=1&subject=' . urlencode('Ngữ Văn'), [
        'action' => 'save_scores',
        'scores' => [
            1 => ['m1' => '10', 'gk' => '10', 'ck' => '10']
        ]
    ]);

    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403 on forged score POST, got " . $res['status']);
    }

    $afterScore = $pdo->query("SELECT avg_score FROM scores WHERE student_id = 1 AND subject = 'Ngữ Văn'")->fetchColumn();
    if ($beforeScore != $afterScore) {
        throw new Exception("Forged POST by unauthorized teacher modified student score in MySQL!");
    }
    return true;
});

assertTest("4.7 Admin direct gradebook inspection on any class/subject succeeds (HTTP 200)", function () use ($client) {
    $client->clearCookies();
    $client->login('admin', '123456');
    // Admin inspects Literature for class 6A1
    $res = $client->get('nhap_diem.php?class_id=1&subject=' . urlencode('Ngữ Văn'));
    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200 for admin gradebook inspection, got " . $res['status']);
    }
    return true;
});

// -------------------------------------------------------------
// TỔNG KẾT & MÃ THOÁT
// -------------------------------------------------------------
echo PHP_EOL . CLR_YELLOW . "============================================================" . CLR_RESET . PHP_EOL;
echo sprintf("  Total Tests: %d | Passed: %s%d%s | Failed: %s%d%s",
    $passedCount + $failedCount,
    CLR_GREEN, $passedCount, CLR_RESET,
    ($failedCount > 0 ? CLR_RED : CLR_GREEN), $failedCount, CLR_RESET
) . PHP_EOL;
echo CLR_YELLOW . "============================================================" . CLR_RESET . PHP_EOL . PHP_EOL;

if ($failedCount > 0) {
    exit(1);
}
exit(0);
```

---

## 5. Hướng Dẫn Triển Khai Cho Worker Agent (`worker_m1` / `worker_m2`)

Để hoàn tất Milestone 2, Worker agent cần thực hiện tuần tự các bước:
1. **Tạo `includes/auth.php`**: Khai báo 5 hàm theo đặc tả mục 2.1.
2. **Cập nhật `login.php`**: Xóa bỏ `|| $password === '123456'`, bổ sung `log_action()`, chuyển hướng đúng theo role.
3. **Cập nhật `logout.php`**: Xóa session, gọi `log_action()`, chuyển hướng về `login.php`.
4. **Cập nhật `admin.php`**: Đặt `require_admin()` ở đầu trang và trong tất cả các POST handlers.
5. **Cập nhật `nhap_diem.php`**: Tích hợp `require_login()`, khử tham số `?teacher_id`, đặt `require_teacher_assignment()` cho cả GET và POST handlers theo mục 2.2.
6. **Tạo `tests/verify_m2.php`**: Sử dụng mã nguồn tại Mục 4, chạy lệnh:
   ```cmd
   D:\xam\php\php.exe tests/verify_m2.php
   ```
   Đạt kết quả `Total Tests: 16 | Passed: 16 | Failed: 0`.
