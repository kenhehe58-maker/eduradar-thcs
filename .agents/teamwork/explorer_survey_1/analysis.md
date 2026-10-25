# Báo Cáo Khảo Sát & Kiểm Toán Toàn Diện Mã Nguồn (Codebase Audit Report)

**Dự án**: Hệ thống Quản Lý Điểm & Cảnh Báo Sớm Học Sinh THCS (EduRadar / VnEdu Standard)  
**Môi trường thực thi**: XAMPP Apache (`http://localhost/my-web-project/`) & MariaDB MySQL (`edu_radar`)  
**Người thực hiện**: explorer_survey_1  
**Thời gian khảo sát**: 2026-10-17T05:58:00Z  

---

## 1. Mục Tiêu & Phạm Vi Khảo Sát
Thực hiện rà soát toàn diện hiện trạng mã nguồn tại thư mục `D:\New folder\my-web-project` dựa trên yêu cầu cốt lõi trong `ORIGINAL_REQUEST.md`:
- Kiểm toán toàn bộ tập tin hiện có (mã nguồn PHP, assets CSS/JS, database scripts, SQLite files, file dữ liệu mẫu).
- Phân tích kiến trúc hệ thống, cơ chế kết nối cơ sở dữ liệu, xác thực (Authentication), phân quyền (RBAC) và kiểm soát truy cập.
- Chỉ ra các lỗ hổng bảo mật, tính năng thừa không theo yêu cầu (nút Zalo, modal, logic thừa), các thành phần SQLite cần loại bỏ.
- Đánh giá giao diện người dùng (UI/CSS/JS) trên cả 4 trang chính: `login.php`, `admin.php`, `nhap_diem.php`, `index.php`.
- Tổng hợp các điểm thiếu sót so với tiêu chuẩn VnEdu và các tiêu chí nghiệm thu (Acceptance Criteria).

---

## 2. Danh Mục Toàn Bộ Tập Tin Trong Dự Án (File Enumeration)

| STT | Đường dẫn tương đối | Kích thước | Loại | Mục đích & Hiện trạng |
|:---:|:---|:---:|:---:|:---|
| 1 | `admin.php` | 19,990 B | PHP | Trang quản trị của Admin: phân công giáo viên dạng bảng tích chọn, import học sinh CSV, xem bảng điểm giáo viên, xem 10 audit logs gần nhất. Thiếu tính năng tạo/xóa lớp, tạo tài khoản giáo viên, và import `.xlsx`. |
| 2 | `nhap_diem.php` | 19,114 B | PHP | Trang sổ điểm giáo viên: nhập điểm trực tiếp, import điểm CSV. Chứa lỗ hổng bảo mật nghiêm trọng (không kiểm tra phân công giáo viên); dùng sai cột điểm cũ (`m1, m2, p15_1, p15_2, t1_1`); thiếu hỗ trợ `.xlsx`. |
| 3 | `index.php` | 8,841 B | PHP | Trang Radar cảnh báo sớm: phân loại học sinh Đỏ - Vàng - Xanh. Chứa tính năng thừa nghiêm trọng (nút copy tin nhắn Zalo, hàm `buildFeedback`, toast Zalo); câu truy vấn SQL bị nhân bản và rò rỉ điểm các môn giáo viên không phụ trách. |
| 4 | `login.php` | 5,378 B | PHP | Trang đăng nhập hệ thống: có lỗ hổng backdoor hardcoded password bypass (`$password === '123456'`); giao diện chứa nhiều CSS inline. |
| 5 | `logout.php` | 256 B | PHP | Trang đăng xuất: hủy session, ghi log đăng xuất và redirect về `login.php`. |
| 6 | `includes/config.php` | 479 B | PHP | Cấu hình kết nối MySQL và SQLite dự phòng; định nghĩa hằng số thừa (`ABSENCE_RED = 3`). |
| 7 | `includes/db.php` | 4,076 B | PHP | Khởi tạo kết nối PDO; hàm `logAction()` ghi nhật ký; hàm `initSQLite()` khởi tạo bảng SQLite khi chạy driver SQLite. Cần loại bỏ toàn bộ code SQLite. |
| 8 | `database.sql` | 4,722 B | SQL | Kịch bản tạo cơ sở dữ liệu MySQL `edu_radar`. **Lỗi**: Hash mật khẩu mẫu sai (`verify` trả về false); thiếu định nghĩa bảng `audit_logs`; thiếu ràng buộc `UNIQUE` trên bảng `students`. |
| 9 | `database.sqlite` | 40,960 B | SQLite | File cơ sở dữ liệu SQLite cục bộ (40 KB) chứa 6 bảng. Vi phạm yêu cầu R1 ("Remove all SQLite code and files"). |
| 10 | `assets/css/style.css` | 8,312 B | CSS | Bảng kiểu CSS chính: dùng font `Plus Jakarta Sans`, biến màu cơ bản kiểu Stitch/Shadcn, style sidebar, thẻ thống kê, bảng điểm. Còn thiếu tinh gọn, nhiều kiểu dáng bị ghi đè inline trong các file PHP. |
| 11 | `assets/js/main.js` | 1,979 B | JS | Script JavaScript cho `index.php`: lọc/tìm kiếm danh sách, xử lý sao chép Zalo và **đoạn code thừa mở modal can thiệp** (`actionModal`). |
| 12 | `sample_students.csv` | 296 B | CSV | File CSV mẫu danh sách học sinh (3 cột: Họ tên, Ngày sinh, SĐT phụ huynh). Chưa đạt chuẩn mẫu VnEdu (`.xlsx` / `.csv`). |
| 13 | `sample_vnedu.csv` | 282 B | CSV | File CSV mẫu điểm học sinh theo cấu trúc cũ (Miệng, 15P_1, 15P_2, 1Tiết, GiữaKỳ, CuốiKỳ). Chưa đúng chuẩn VnEdu TT22. |
| 14 | `uploads/` | 0 B | Thư mục | Thư mục trống dùng để lưu trữ file tạm thời nếu cần. |
| 15 | `.vscode/mcp.json` | 487 B | JSON | Cấu hình MCP tools của môi trường phát triển. |
| 16 | `tests/` | Không có | - | **Chưa tồn tại**. Bộ test tự động `tests/run_tests.php` chưa được viết. |

---

## 3. Phân Tích Kiến Trúc, Xác Thực & Kiểm Soát Truy Cập (Security & RBAC Audit)

### 3.1. Xác thực (Authentication) & Lỗ hổng Backdoor Bypass
- **Vị trí**: `login.php:16`
  ```php
  if ($user && (password_verify($password, $user['password']) || $password === '123456')) {
  ```
- **Phát hiện**: Lập trình viên trước đó đã chèn điều kiện `|| $password === '123456'`. Bất kỳ ai chỉ cần biết tên đăng nhập (ví dụ: `admin`, `gv_toan`) đều có thể đăng nhập thành công với mật khẩu `123456`, kể cả khi tài khoản đã đổi mật khẩu khác!
- **Nguyên nhân gốc rễ**: Chuỗi băm được chèn trong file `database.sql` gốc:
  ```sql
  -- Hash cho '123456': $2y$10$4y9pQzS/m7y9xY8Z/xH0ve2w5.JjK.RjT18P4q7lZ0eQ0/a4uCqS.
  ```
  Khi kiểm tra với `password_verify('123456', ...)` trả về **`false`**! Do hash mẫu bị hỏng, thay vì tạo lại hash chuẩn, lập trình viên đã chèn bypass trực tiếp vào mã nguồn.
- **Hiện trạng MySQL**: Trên cơ sở dữ liệu `edu_radar` đang chạy trong MariaDB, hash của các tài khoản đã được ai đó cập nhật thành `$2y$10$Huov5BMeiGHCxhgfaTkauuthlWkvzJguIrvV6vWU1SQsJDrk6PlMG` (chuỗi này xác thực thành công với `123456`). Tuy nhiên, nếu ai đó chạy lại file `database.sql`, hệ thống sẽ bị lỗi đăng nhập ngay lập tức nếu bỏ bypass.
- **Giải pháp**: Xóa bỏ hoàn toàn điều kiện `|| $password === '123456'` trong `login.php`; cập nhật lại chuỗi băm chuẩn tạo bằng `password_hash('123456', PASSWORD_DEFAULT)` vào trong `database.sql`.

### 3.2. Phân Quyền Admin & Giáo Viên (Role-Based Access Control)
- **`admin.php`**:
  - Dòng 6-16: Đã kiểm tra `$_SESSION['role'] !== 'admin'`, trả về HTTP 403 Forbidden và ghi log vào `audit_logs`. Đây là điểm xử lý đúng chuẩn.
- **`nhap_diem.php` - Lỗ Hổng Nghiêm Trọng (Broken Object Level Authorization / BOLA)**:
  - Dòng 46-52: Lấy `class_id` và `subject` trực tiếp từ `$_GET` mà **không kiểm tra** xem giáo viên đang đăng nhập có được phân công phụ trách cặp `(class_id, subject)` đó trong bảng `teacher_classes` hay không:
    ```php
    $selectedClassId = isset($_GET['class_id']) && (int)$_GET['class_id'] > 0 
        ? (int)$_GET['class_id'] 
        : (!empty($myClasses) ? $myClasses[0]['id'] : 0);

    $selectedSubject = isset($_GET['subject']) && $_GET['subject'] !== '' 
        ? trim($_GET['subject']) 
        : $defaultSubject;
    ```
  - Dòng 61-108: Khi submit form `POST` với `action=save_scores`, hệ thống lưu thẳng điểm vào bảng `scores` cho bất kỳ `class_id` và `subject` nào được gửi lên từ URL!
  - **Hệ quả**: Giáo viên dạy Toán lớp 6A1 có thể dễ dàng sửa URL thành `nhap_diem.php?class_id=1&subject=Ngữ Văn` hoặc `nhap_diem.php?class_id=4&subject=Tiếng Anh` để xem trộm và sửa đổi điểm của đồng nghiệp khác mà hệ thống không hề chặn lại!
  - **Yêu cầu theo R1 & R3**: Giáo viên CHỈ ĐƯỢC PHÉP xem và sửa điểm cho đúng các cặp `(class_id, subject)` được phân công trong `teacher_classes`. Mọi thao tác truy cập URL trái phép hoặc giả mạo POST phải bị từ chối ở tầng server-side (trả về 403 hoặc chuyển hướng, tuyệt đối không thay đổi cơ sở dữ liệu).

### 3.3. Kết Nối Database & Dấu Tích SQLite
- `includes/config.php`:
  - Dòng 4: `define('DB_DRIVER', 'mysql');`
  - Dòng 13: `define('SQLITE_FILE', __DIR__ . '/../database.sqlite');`
  - Dòng 19: `define('ABSENCE_RED', 3);` (Hằng số thừa về điểm danh, hệ thống không có chức năng này).
- `includes/db.php`:
  - Dòng 10-18: Nhánh xử lý `if (DB_DRIVER === 'sqlite')` gọi `initSQLite($pdo)`.
  - Dòng 63-126: Toàn bộ hàm `initSQLite()` định nghĩa schema SQLite và chèn dữ liệu mẫu SQLite.
  - File `database.sqlite` (40 KB) nằm ở thư mục gốc chứa dữ liệu cũ.
- **Yêu cầu theo R1**: Xóa hoàn toàn file `database.sqlite`, bỏ toàn bộ nhánh kiểm tra SQLite, hàm `initSQLite()` và hằng số liên quan, chuyển kết nối thành PDO MySQL thuần túy (`charset=utf8mb4`).

---

## 4. Kiểm Toán Các Tính Năng Thừa, Nút Zalo, Modal & Mã Rác (Unrequested Features)

Theo mục tiêu tinh gọn và yêu cầu cụ thể từ người dùng (`ORIGINAL_REQUEST.md`), các tính năng sau đây vi phạm yêu cầu và cần được loại bỏ triệt để:

### 4.1. Nút Sao Chép Tin Nhắn Zalo & Bộ Soạn Tin Nhắn Phụ Huynh (`index.php`)
- **Vị trí**:
  - `index.php:56-72`: Hàm `buildFeedback(array $s): string` tự sinh câu nhắc nhở phụ huynh để gửi Zalo ("Kính gửi phụ huynh em...").
  - `index.php:95`: `$item['feedback'] = buildFeedback($item);`
  - `index.php:209-211`:
    ```html
    <button class="btn copy-btn" data-text="<?= htmlspecialchars($s['feedback']) ?>">
        📋 Copy Tin Nhắn Zalo
    </button>
    ```
  - `index.php:221`:
    ```html
    <div id="toast" class="toast">Đã sao chép tin nhắn Zalo!</div>
    ```
  - `assets/js/main.js:25-41`: Sự kiện click vào `.copy-btn`, ghi nội dung vào clipboard và hiển thị toast Zalo.
- **Đánh giá vi phạm**: Người dùng đã nhấn mạnh trong R4:  
  > *"DO NOT include the Zalo copy button or parent message generator (the user explicitly asked to hold off on the Zalo button)."*  
  Phải xóa bỏ toàn bộ hàm `buildFeedback`, cột nút copy Zalo, thẻ toast Zalo và đoạn JS clipboard tương ứng.

### 4.2. Modal Can Thiệp Nhật Ký Bị Treo / Thừa (`assets/js/main.js`)
- **Vị trí**: `assets/js/main.js:43-60`
  ```javascript
  // 3. Modal Nhật Ký Can Thiệp (Intervention Log)
  const modal = document.getElementById('actionModal');
  const modalStudentId = document.getElementById('modalStudentId');
  const modalStudentName = document.getElementById('modalStudentName');

  function openActionModal(id, name) {
      modalStudentId.value = id;
      modalStudentName.textContent = 'Học sinh: ' + name;
      modal.classList.add('show');
  }

  function closeActionModal() {
      modal.classList.remove('show');
  }

  window.addEventListener('click', (e) => {
      if (e.target === modal) closeActionModal();
  });
  ```
- **Đánh giá vi phạm**: Trên giao diện `index.php` hiện tại thậm chí không hề có phần tử `#actionModal`. Đây là đoạn mã chết (dead code) của tính năng quản lý can thiệp chưa hoàn thiện, gây lỗi ẩn hoặc rác JS. Cần xóa sạch.

### 4.3. Bảng Điểm Danh Thừa Trong SQL (`database.sql`)
- **Vị trí**: `database.sql:10`: `DROP TABLE IF EXISTS attendance;`
- **Đánh giá**: Dự án không quản lý điểm danh (`attendance`), chỉ quản lý điểm và cảnh báo học lực. Cần làm sạch kịch bản SQL.

---

## 5. Kiểm Toán Cơ Sở Dữ Liệu MySQL (`edu_radar`) vs `database.sql`

Khảo sát trực tiếp cơ sở dữ liệu `edu_radar` trên XAMPP MySQL (MariaDB 10.4):

```
+--------------------+------------+
| Table Name         | Row Count  |
+--------------------+------------+
| audit_logs         | 23 rows    |
| classes            | 36 rows    |
| scores             | 5 rows     |
| students           | 5 rows     |
| teacher_classes    | 14 rows    |
| users              | 3 rows     |
+--------------------+------------+
```

### So Sánh Schema Thực Tế Với Yêu Cầu R5:

| Bảng | Ràng buộc yêu cầu | Hiện trạng trong MySQL | Hiện trạng trong `database.sql` | Đánh giá & Rủi ro |
|:---|:---|:---|:---|:---|
| `users` | `UNIQUE(username)` | Có `UNIQUE KEY username` | Có `UNIQUE` | OK. Cần sửa hash mật khẩu mẫu trong file `.sql`. |
| `classes` | `UNIQUE(name)` | Có `UNIQUE KEY name` | Có `UNIQUE` | OK. Đã có 36 lớp (6A1..9C9). |
| `teacher_classes` | `UNIQUE(teacher_id, class_id, subject)` | Có `UNIQUE KEY unique_assignment` | Có `UNIQUE KEY unique_assignment` | OK. Đã có foreign key CASCADE. |
| `students` | `UNIQUE(class_id, full_name, birthday)` | **CHƯA CÓ** (chỉ có key trên `class_id`) | **CHƯA CÓ** (không có unique constraint) | **RỦI RO CAO**: Khi import lại danh sách học sinh, nếu không có ràng buộc này ở database thì dễ bị nhân bản học sinh khi có lỗi code. |
| `scores` | `UNIQUE(student_id, subject)` | Có `UNIQUE KEY unique_student_subject` | Có `UNIQUE KEY unique_student_subject` | **LỆCH CỘT ĐIỂM**: Đang dùng `m1, m2, p15_1, p15_2, t1_1, t1_2, gk, ck, avg_score`. Cần chuẩn hóa theo Thông tư 22: `tx1, tx2, tx3, tx4, gk, ck, avg_score`. |
| `audit_logs` | Lưu trữ nhật ký hệ thống | Có bảng trong MySQL (23 rows) | **HOÀN TOÀN THIẾU** trong file `database.sql` | **RỦI RO CAO**: Nếu import lại `database.sql` thì mất bảng `audit_logs`, hàm `logAction()` sẽ sinh lỗi SQL! |

---

## 6. Phân Tích Chức Năng Từng Trang & Độ Lệch So Với Yêu Cầu (Gap Analysis)

### 6.1. Trang Quản Trị Admin (`admin.php` - Yêu cầu R2)
1. **Quản lý ma trận lớp học (Class Matrix & Management)**:
   - *Yêu cầu*: Hiển thị toàn bộ lớp theo Khối (6, 7, 8, 9), Ban (A, B, C) và Số thứ tự (1-9). Admin có thể tạo lớp mới và xóa lớp không dùng mà không sợ trùng (`UNIQUE(name)`).
   - *Hiện trạng*: Chỉ hiển thị danh sách lớp sẵn có trong cơ sở dữ liệu để chọn làm phân công. **Hoàn toàn không có form tạo lớp mới** và **không có nút xóa lớp**.
2. **Phân công hàng loạt dạng Checkbox (Batch Checkbox Assignment)**:
   - *Yêu cầu*: Chọn giáo viên + môn học -> tích chọn các ô lớp (hỗ trợ chọn tất cả theo khối). Ngăn chặn trùng lặp (`UNIQUE(teacher_id, class_id, subject)`). Hiển thị và hủy phân công. **Có chức năng tạo tài khoản giáo viên mới**.
   - *Hiện trạng*: Đã có bảng tích chọn và hủy phân công. **Hoàn toàn thiếu chức năng tạo tài khoản giáo viên mới**.
3. **Import danh sách học sinh bằng File (`.xlsx` & `.csv` chuẩn VnEdu)**:
   - *Yêu cầu*: Admin chọn lớp đích, tải file Excel `.xlsx` (hoặc `.csv`) chuẩn VnEdu (STT, Mã HS, Họ và tên, Ngày sinh, Giới tính, SĐT phụ huynh). Tự động khử trùng lặp (không tạo thêm học sinh trùng tên + ngày sinh trong lớp). **Có link tải file mẫu `.xlsx`**.
   - *Hiện trạng*: Chỉ hỗ trợ đọc file CSV dạng đơn giản bằng `fgetcsv()`. **Không có khả năng đọc file binary Excel `.xlsx`**; không có file mẫu `.xlsx` (chỉ có `sample_students.csv`).
4. **Kiểm tra sổ điểm trực tiếp (Direct Gradebook Inspection)**:
   - *Yêu cầu*: Trong bảng danh sách phân công, có nút mở trực tiếp sổ điểm của giáo viên tương ứng (`nhap_diem.php`).
   - *Hiện trạng*: Đã có nút "🔍 Mở Bảng Điểm Môn...", truyền tham số qua URL và lưu session. Cần đảm bảo UI đồng bộ và quay lại Admin mượt mà.
5. **Nhật ký kiểm toán (Audit Log Viewer)**:
   - *Hiện trạng*: Đã có bảng hiển thị 10 logs gần nhất. Cần đồng bộ kiểu dáng giao diện Stitch UI.

### 6.2. Trang Sổ Điểm Giáo Viên (`nhap_diem.php` - Yêu cầu R3)
1. **Lựa chọn lớp & môn được phân công**:
   - *Yêu cầu*: Giáo viên chọn từ danh sách các cặp `(Lớp, Môn)` được phân công cho mình (Admin được chuyển giữa tất cả các lớp/môn).
   - *Hiện trạng*: Dropdown môn học đang hardcode danh sách tất cả các môn, dẫn đến giáo viên có thể chọn môn mình không được phân công; thiếu kiểm tra phân quyền server-side.
2. **Bảng điểm chuẩn VnEdu (Thông tư 22/2021/TT-BGDĐT)**:
   - *Yêu cầu*:
     - Các cột: `STT`, `Họ và tên`, `Ngày sinh`, **`ĐĐGtx` (thường xuyên: cột 1, 2, 3, 4)**, **`ĐĐGgk` (giữa kỳ - HS 2)**, **`ĐĐGck` (cuối kỳ - HS 3)**, **`ĐTBmhk`**.
     - Công thức ĐTBmhk tự động:  
       $$\text{ĐTBmhk} = \frac{\sum \text{ĐĐGtx} + \text{ĐĐGgk} \times 2 + \text{ĐĐGck} \times 3}{\text{Số cột ĐĐGtx có điểm} + 5}$$  
       (hoặc tính trung bình có trọng số của các cột đã nhập điểm, làm tròn 1 chữ số thập phân).
     - Điều hướng bàn phím: Dùng phím Tab hoặc Phím Mũi Tên (Arrow keys: Lên, Xuống, Trái, Phải) để nhảy giữa các ô điểm nhanh như Excel; validate điểm từ 0.0 đến 10.0.
   - *Hiện trạng*:
     - Bảng điểm thiếu cột `Ngày sinh`.
     - Cột điểm vẫn dùng nhãn cũ: `M.1`, `M.2`, `15' (1)`, `15' (2)`, `1T (1)`.
     - Công thức tính điểm trung bình đang chia theo trọng số kiểu cũ (`$weight` cộng dồn), không đúng chuẩn Thông tư 22.
     - Chưa có điều hướng bằng phím mũi tên.
3. **Import & Export file `.xlsx` VnEdu ở cuối trang**:
   - *Yêu cầu*: Cho phép giáo viên tải file `.xlsx` (hoặc `.csv`) để nạp điểm hàng loạt; có nút xuất file `.xlsx` bảng điểm hiện tại; có link tải file mẫu `.xlsx` VnEdu.
   - *Hiện trạng*: Chỉ hỗ trợ file `.csv`; hoàn toàn thiếu xử lý file `.xlsx` và tính năng xuất file.

### 6.3. Radar Cảnh Báo Sớm (`index.php` - Yêu cầu R4)
1. **Phân tích cảnh báo**:
   - *Yêu cầu*: Phân loại 🔴 Nguy cấp (Red), 🟡 Cần chú ý (Yellow), 🟢 Ổn định (Green) dựa trên các cột điểm VnEdu (ĐTB < 5.0, điểm kiểm tra định kỳ sa sút liên tiếp, điểm thành phần < 5.0).
   - *Hiện trạng*: Hàm `evaluateRisk()` đã có logic cơ bản nhưng đang duyệt trên mảng cột cũ (`m1`, `p15_1`, `t1_1`, ...).
2. **Lọc theo lớp & môn phụ trách của giáo viên**:
   - *Hiện trạng*: Câu lệnh SQL `LEFT JOIN scores sc ON s.id = sc.student_id` kết hợp với `WHERE s.class_id IN (SELECT class_id FROM teacher_classes WHERE teacher_id = ?)` khiến cho:
     - Nếu một học sinh có điểm ở nhiều môn (Toán, Văn), kết quả trả về nhiều dòng cho cùng 1 học sinh nhưng không ghi rõ môn nào!
     - Giáo viên dạy Toán lại nhìn thấy cả dòng điểm môn Văn của học sinh lớp đó.
   - *Yêu cầu*: Phải liên kết chính xác `scores` theo đúng môn mà giáo viên được phân công; hiển thị rõ ràng Tên Môn Học bên cạnh Tên Lớp trong bảng cảnh báo.
3. **Tuyệt đối không chứa nút Zalo**:
   - *Hiện trạng*: Đang có nút "Copy Tin Nhắn Zalo", hàm `buildFeedback` và toast Zalo. Phải loại bỏ hoàn toàn.

### 6.4. Ghi Nhật Ký Hệ Thống Toàn Diện (`audit_logs` - Yêu cầu R5)
Mọi hành động thay đổi trạng thái hệ thống phải ghi vào `audit_logs`:
- [x] Đăng nhập thành công (`login.php`)
- [x] Đăng nhập thất bại (`login.php`)
- [x] Đăng xuất (`logout.php`)
- [x] Cố tình truy cập trái phép bị chặn (Admin 403)
- [ ] Cố tình truy cập/sửa điểm lớp không được phân công (cần bổ sung chặn và ghi log trong `nhap_diem.php`)
- [x] Phân công giáo viên (`admin.php`)
- [x] Hủy phân công (`admin.php`)
- [ ] Tạo lớp mới (cần bổ sung)
- [ ] Xóa lớp (cần bổ sung)
- [ ] Tạo tài khoản giáo viên mới (cần bổ sung)
- [x] Import danh sách học sinh (`admin.php`)
- [x] Lưu điểm sổ điểm (`nhap_diem.php`)
- [x] Import file điểm (`nhap_diem.php`)
- [x] Admin mở sổ điểm giáo viên (`admin.php`)

---

## 7. Đánh Giá Giao Diện Người Dùng (UI / CSS / JS Inspection)

1. **Phong cách thiết kế (Stitch UI / Modern SaaS)**:
   - Dự án đã cài đặt font `Plus Jakarta Sans` và có bảng màu cơ bản trong `assets/css/style.css`.
   - Tuy nhiên, tính nhất quán (cohesion) chưa cao:
     - `login.php` sử dụng inline style ghi đè toàn bộ nền `body` bằng radial gradient tím đậm `#1e1b4b`.
     - `admin.php` và `nhap_diem.php` lạm dụng rất nhiều style inline (`style="background: #e0e7ff; ..."`).
     - Thiếu các huy hiệu phân định rõ vai trò người dùng (Role Badges: `Quản Trị Viên` màu tím indigo, `Giáo Viên Bộ Môn` màu xanh lục/xanh dương).
     - Điều hướng sidebar: Cần chỉ hiển thị chính xác các mục mà vai trò hiện tại được phép truy cập. Ví dụ: Giáo viên không bao giờ nhìn thấy link Admin, Admin có badge phân biệt rõ khi đang ở chế độ xem sổ điểm.
2. **Phản hồi người dùng (Feedback & Toasts)**:
   - Các thông báo thành công / lỗi hiện tại sử dụng khối `.alert` tĩnh chèn trên đầu trang, đẩy thụt nội dung xuống dưới.
   - Cần bổ sung hệ thống Toast notification nhẹ nhàng hoặc Alert nổi bật, tự biến mất sau 3 giây, cho phản hồi tức thì khi lưu điểm hoặc import.
3. **Thư viện bên ngoài**:
   - Hiện không sử dụng CDN hay framework JS cồng kềnh (không dùng React, jQuery hay Tailwind CDN), giữ đúng tiêu chuẩn Plain PHP + CSS/JS thuần tốc độ cao.

---

## 8. Hướng Dẫn Kỹ Thuật Đọc/Ghi File `.xlsx` Bằng PHP Thuần (Pure PHP ZipArchive & XML)

Để đáp ứng yêu cầu import/export file `.xlsx` mà không cần cài đặt thư viện nặng như `phpoffice/phpspreadsheet` (do dự án không dùng Composer):
- **Cơ sở kỹ thuật**: File `.xlsx` bản chất là một file nén ZIP tuân theo định dạng OpenXML (ECMA-376).
- **Phần mở rộng khả dụng**: `D:\xam\php\php.exe -m` đã bật sẵn cả `zip`, `SimpleXML`, `xml`, `dom`, `libxml`.
- **Giải pháp thực hiện**:
  1. *Đọc file `.xlsx`*:
     - Dùng `ZipArchive::open()` để mở file tải lên.
     - Đọc file `xl/sharedStrings.xml` để lấy bảng chuỗi chung (Shared Strings table).
     - Đọc file `xl/worksheets/sheet1.xml` bằng `simplexml_load_string()`.
     - Phân tích các thẻ `<row>` và `<c>` (ô dữ liệu). Nếu thuộc tính `t="s"` thì giá trị `<v>` là index trong bảng Shared Strings; nếu không thì là giá trị số trực tiếp.
  2. *Xuất file `.xlsx`*:
     - Tạo một file nén ZIP chứa cấu trúc tối thiểu của OpenXML (`[Content_Types].xml`, `_rels/.rels`, `xl/workbook.xml`, `xl/worksheets/sheet1.xml`, `xl/styles.xml`).
     - Hoặc cung cấp file mẫu chuẩn VnEdu tải về và cho phép xuất CSV tương thích UTF-8 có BOM (`\xEF\xBB\xBF`) để Excel hiển thị tiếng Việt hoàn hảo, đồng thời hỗ trợ bộ tạo `.xlsx` native.

---

## 9. Danh Sách Các Hạng Mục Cần Thực Hiện Cho Đội Ngũ Triển Khai (Actionable Plan)

1. **Cơ sở dữ liệu (`database.sql` & MariaDB)**:
   - Bổ sung bảng `audit_logs` vào `database.sql`.
   - Thêm ràng buộc `UNIQUE(class_id, full_name, birthday)` cho bảng `students`.
   - Cập nhật cấu trúc bảng `scores` thành `tx1, tx2, tx3, tx4, gk, ck, avg_score` và `UNIQUE(student_id, subject)`.
   - Thay thế chuỗi băm mật khẩu mẫu bằng hash hợp lệ của `'123456'`.
   - Xóa bỏ file `database.sqlite` khỏi thư mục gốc.
2. **Bảo mật & Xác thực (`login.php`, `includes/db.php`, `includes/config.php`)**:
   - Xóa hoàn toàn bypass `|| $password === '123456'` trong `login.php`.
   - Xóa bỏ các định nghĩa SQLite trong `config.php` và `db.php`.
3. **Phân quyền & Kiểm soát truy cập (`nhap_diem.php`)**:
   - Thêm kiểm tra nghiêm ngặt: Nếu user là giáo viên, phải xác thực `(class_id, subject)` có nằm trong `teacher_classes` của giáo viên đó hay không. Nếu không, từ chối với HTTP 403, ghi nhật ký `audit_logs` và chuyển hướng.
   - Khi `POST` lưu điểm, chỉ cho phép cập nhật đúng lớp và môn được cấp quyền.
4. **Trang Quản Trị (`admin.php`)**:
   - Bổ sung form tạo lớp học mới và nút xóa lớp (ngăn trùng lặp).
   - Bổ sung form tạo tài khoản giáo viên mới.
   - Thêm bộ đọc file `.xlsx` và `.csv` chuẩn VnEdu cho danh sách học sinh.
   - Cung cấp file mẫu `.xlsx` và `.csv` chuẩn VnEdu để tải về.
5. **Trang Sổ Điểm (`nhap_diem.php`)**:
   - Cập nhật các cột bảng điểm theo chuẩn Thông tư 22 (ĐĐGtx 1..4, ĐĐGgk, ĐĐGck, ĐTBmhk, thêm cột Ngày sinh).
   - Cập nhật công thức tính điểm trung bình chính xác.
   - Bổ sung điều hướng bàn phím bằng phím mũi tên.
   - Bổ sung bộ nạp điểm từ file `.xlsx` và nút xuất bảng điểm `.xlsx`.
6. **Radar Cảnh Báo (`index.php`)**:
   - Xóa bỏ nút copy Zalo, thẻ toast Zalo và hàm `buildFeedback()`.
   - Sửa câu lệnh SQL để kết hợp chính xác môn học giáo viên phụ trách, hiển thị rõ cột môn học và lý do cảnh báo cụ thể.
7. **Giao diện & Dọn dẹp (`assets/css/style.css`, `assets/js/main.js`)**:
   - Dọn sạch mã JS thừa (xóa code modal `#actionModal` và Zalo clipboard).
   - Hoàn thiện giao diện Stitch UI nhất quán, tinh tế, loại bỏ style inline rác.
8. **Kiểm thử tự động (`tests/run_tests.php`)**:
   - Viết kịch bản test CLI bằng PHP, tự động chạy qua `D:\xam\php\php.exe tests/run_tests.php`, kiểm thử trực tiếp lên Apache port 80 và MySQL MariaDB `edu_radar`.
   - Kiểm tra đầy đủ 9 trường hợp kiểm thử trong Acceptance Criteria.
