# EduRadar THCS — Hệ Thống Cảnh Báo Sớm Học Lực & Sổ Điểm Điện Tử 2D

Hệ thống quản lý điểm số, phân tích động năng học tập và cảnh báo sớm nguy cơ sa sút học sinh THCS theo chuẩn **Thông tư 22/2021/TT-BGDĐT** và định dạng sổ điểm điện tử **VnEdu**.

---

## 1. Tổng Quan Dự Án & Giá Trị Cốt Lõi

EduRadar THCS được phát triển nhằm giải quyết bài toán cốt lõi trong quản lý giáo dục phổ thông: **phát hiện sớm học sinh có nguy cơ sa sút học lực trước các kỳ thi quan trọng**, giúp giáo viên bộ môn và Ban Giám Hiệu can thiệp sư phạm kịp thời thay vì chờ đến khi tổng kết học kỳ mới xử lý.

### Giá trị cốt lõi:
- **Chủ động cảnh báo sớm**: Phân tích đa chiều điểm kiểm tra thường xuyên (ĐĐGtx) và điểm giữa kỳ (ĐĐGgk) để dự báo trước nguy cơ trượt chuẩn hoặc rớt học lực.
- **Tối ưu hóa thao tác sư phạm**: Sổ điểm ma trận 2D tích hợp phím tắt định hướng tốc độ cao, hỗ trợ giáo viên vào điểm nhanh như thao tác trên phần mềm bảng tính chuyên nghiệp.
- **Tuân thủ tuyệt đối quy định Bộ GD&ĐT**: Thuật toán tính điểm trung bình môn học kỳ (ĐTBmhk) và xếp loại chuẩn xác theo Thông tư 22/2021/TT-BGDĐT.
- **Bảo mật và toàn vẹn dữ liệu**: Kiến trúc phân quyền RBAC nghiêm ngặt, chống tấn công BOLA/IDOR, ghi vết kiểm toán (Audit Logging) thời gian thực cho mọi biến động điểm số.
- **Thiết kế Mobile-First**: Giao diện thích ứng hoàn hảo từ màn hình hẹp di động (360px–412px) đến màn hình máy tính bàn độ phân giải cao.

---

## 2. Hệ Thống Ra-đa Cảnh Báo Sớm TT22

Ra-đa học lực EduRadar vận hành dựa trên mô hình phân vùng 3 mức độ rủi ro, bảo toàn bất biến phân hoạch kín `Tổng số học sinh = Vùng Đỏ + Vùng Vàng + Vùng Xanh`:

```
               [ Ra-đa Cảnh Báo Sớm TT22 ]
                         │
        ┌────────────────┼────────────────┐
        ▼                ▼                ▼
   [ VÙNG ĐỎ ]      [ VÙNG VÀNG ]    [ VÙNG XANH ]
    Nguy cấp          Cần chú ý         Ổn định
  ĐTBmhk < 5.0      5.0 ≤ ĐTB < 6.5   ĐTBmhk ≥ 6.5
  hoặc CK < 3.5     hoặc CK/GK < 5.0  Không sa sút
```

### 2.1. Tiêu chí phân vùng:

1. **Vùng Đỏ (Nguy cấp / High Risk)**:
   - Điểm trung bình môn học kỳ $\text{ĐTBmhk} < 5.0$.
   - Hoặc điểm Cuối kỳ $\text{CK} < 3.5$ (nguy cơ điểm liệt khống chế).
   - Hoặc điểm Giữa kỳ $\text{GK} < 3.5$.
   - Hoặc có từ 2 bài kiểm tra thường xuyên đạt điểm dưới $3.5$.

2. **Vùng Vàng (Cần chú ý / Attention Needed)**:
   - Điểm trung bình $5.0 \le \text{ĐTBmhk} < 6.5$.
   - **Bắt buộc**: Bất kỳ học sinh nào có điểm Cuối kỳ hoặc Giữa kỳ dưới trung bình ($\text{CK} < 5.0$ hoặc $\text{GK} < 5.0$) đều được xếp vào Vùng Vàng (hoặc Vùng Đỏ nếu thỏa điều kiện đỏ), tuyệt đối không xếp vào Vùng Xanh.
   - **Dấu hiệu sa sút cuối kỳ**: Điểm Giữa kỳ đạt khá/giỏi nhưng điểm Cuối kỳ sụt giảm đột ngột với chênh lệch:
     $$\Delta_{\text{drop}} = \text{round}(\text{GK} - \text{CK}, 1) > 1.5$$

3. **Vùng Xanh (Ổn định / On Track)**:
   - Học sinh có $\text{ĐTBmhk} \ge 6.5$, tất cả các điểm thi định kỳ $\text{GK} \ge 5.0$ và $\text{CK} \ge 5.0$.
   - Học sinh chưa có điểm hoặc đang trong tiến trình chờ điểm được xếp vào danh mục ổn định/chờ điểm để bảo toàn phân hoạch dữ liệu.

---

## 3. Công Thức Tính Điểm ĐTBmhk Chuẩn Thông Tư 22

Căn cứ Điều 9 Thông tư 22/2021/TT-BGDĐT ngày 20/07/2021 của Bộ Giáo dục và Đào tạo, điểm trung bình môn học kỳ được tính theo công thức:

$$\text{ĐTBmhk} = \frac{\sum_{i=1}^{N_{\text{tx}}} \text{ĐĐGtx}_i + 2 \times \text{ĐĐGgk} + 3 \times \text{ĐĐGck}}{N_{\text{tx}} + 2 + 3}$$

Trong đó:
- $\text{ĐĐGtx}_i$: Điểm đánh giá thường xuyên thứ $i$ (hệ số 1, tối đa 4 cột: TX1, TX2, TX3, TX4).
- $\text{ĐĐGgk}$: Điểm đánh giá giữa kỳ (hệ số 2).
- $\text{ĐĐGck}$: Điểm đánh giá cuối kỳ (hệ số 3).
- $N_{\text{tx}}$: Số lượng cột điểm thường xuyên thực tế đã nhập ($1 \le N_{\text{tx}} \le 4$).
- Kết quả làm tròn đến **1 chữ số thập phân** theo quy tắc toán học (`round($avg, 1)`).

*Trường hợp giai đoạn Giữa kỳ I (chưa có điểm Cuối kỳ):*
$$\text{ĐTBmhk} = \frac{\sum \text{ĐĐGtx} + 2 \times \text{ĐĐGgk}}{N_{\text{tx}} + 2}$$

---

## 4. Sổ Điểm Điện Tử 2D VnEdu Matrix

Trang nhập điểm `nhap_diem.php` được thiết kế theo cấu trúc ma trận 2 chiều (2D Gradebook Matrix) tương thích định dạng hiển thị của VnEdu:

### 4.1. Điều hướng bàn phím 2D:
- **Phím Enter**: Lưu vị trí và tự động nhảy xuống học sinh tiếp theo trong cùng cột điểm (`(row + 1, col)`). Kết hợp `Shift + Enter` để nhảy ngược lên trên (`(row - 1, col)`).
- **Phím mũi tên 4 hướng**:
  - `ArrowUp` ($\uparrow$): Di chuyển lên ô điểm dòng trên.
  - `ArrowDown` ($\downarrow$): Di chuyển xuống ô điểm dòng dưới.
  - `ArrowLeft` ($\leftarrow$): Di chuyển sang ô điểm bên trái; khi ở cột đầu tiên tự quấn lên ô cuối dòng trước.
  - `ArrowRight` ($\rightarrow$): Di chuyển sang ô điểm bên phải; khi ở cột cuối tự quấn xuống ô đầu dòng kế tiếp.
- **Phím Tab**: Di chuyển tuần tự ngang qua các cột và chuyển hàng mượt mà.
- **Hỗ trợ gõ tắt tốc độ cao**: Nhập số liền (ví dụ gõ `85` tự nhận dạng thành `8.5`, gõ `75` thành `7.5`), tự động đổi dấu phẩy `,` thành dấu chấm `.`.

### 4.2. Trải nghiệm người dùng & Tiêu chuẩn thiết kế:
- **Định luật Fitts**: Nút CTA chính **"Lưu bảng điểm"** có kích thước vùng chạm tối thiểu $\ge 48 \times 48\text{px}$, bố trí nổi bật ở thanh công cụ đầu bảng, thanh toolbar cuộn theo và chân bảng điểm.
- **Định luật Proximity (Khoảng cách thị giác)**: Nhóm 4 cột đánh giá thường xuyên (TX1–TX4) được gom cụm tinh tế, phân tách rõ ràng với nhóm cột định kỳ Giữa kỳ (HS2) và Cuối kỳ (HS3) bằng đường viền phân định và độ bão hòa màu chuyên biệt.
- **Độ tương phản Dark Mode đạt WCAG AA ($\ge 4.5:1$)**: Ô chọn tệp (`input[type="file"]`), các nút tải file mẫu và xuất báo cáo sử dụng hệ màu Slate/Zinc chuẩn công nghiệp, loại bỏ viền chói gắt.

---

## 5. Phân Quyền Truy Cập (RBAC) & Bảo Mật

Hệ thống thiết lập hàng rào bảo mật nhiều lớp ngăn chặn triệt để các lỗ hổng phổ biến:

| Vai Trò | Phạm Vi Quyền Hạn | Cơ Chế Bảo Vệ |
|---|---|---|
| **Quản trị viên (Admin)** | Toàn quyền quản trị trường học: Tạo/xóa lớp (Khối 6–9), phân công giảng dạy theo khối, nạp học sinh toàn trường qua Excel nhiều sheet, giám sát sổ điểm mọi môn/lớp, xem Audit Logs. | Bắt buộc kiểm tra `require_admin()`, chặn truy cập nếu session không phải admin. |
| **Giáo viên (Teacher)** | Chỉ xem danh sách lớp và nhập điểm các môn được phân công giảng dạy theo bảng `teacher_classes`. | Kiểm tra phân công `require_teacher_assignment()`, chống BOLA (truy cập lớp không phân công) và chống IDOR (giả mạo tham số `teacher_id`). |
| **Khách (Guest)** | Sử dụng công cụ Quét nhanh VnEdu một chạm (`quet_nhanh.php`) để phân tích bảng điểm không cần lưu tài khoản. | Chạy sandbox cục bộ, không ghi đè dữ liệu trường học. |

### Tính năng bảo mật bổ sung:
- Khử backdoor bypass mật khẩu; 100% mật khẩu được băm Bcrypt chuẩn qua `password_hash()` và xác thực qua `password_verify()`.
- Chống Session Fixation: Thực hiện `session_regenerate_id(true)` và xóa sạch ngữ cảnh phiên cũ khi đăng nhập.
- Ghi nhật ký kiểm toán (Audit Trail) chi tiết từng ô điểm thay đổi (giá trị cũ, giá trị mới, giáo viên thao tác, thời gian, IP).

---

## 6. Thiết Kế Thích Ứng Mobile-First (360px–412px)

Giao diện EduRadar được tinh chỉnh theo phương châm Mobile-First:
- **Độ phân giải siêu nhỏ (360px–412px)**: Form tạo lớp học trên trang quản trị tự động co giãn theo lưới 2 cột, nút **"Tạo lớp"** luôn hiển thị đầy đủ, không bị tràn viền hay che khuất.
- **Thanh lọc Khối lớp cuộn ngang mượt**: Các chip Khối 6, 7, 8, 9 cuộn mượt (`overflow-x: auto`, `flex-nowrap`, ẩn thanh cuộn thô trên thiết bị cảm ứng).
- **Chống va chạm Bottom Dock**: Toàn bộ nội dung trang chính và footer được đệm khoảng an toàn:
  ```css
  padding-bottom: calc(5rem + env(safe-area-inset-bottom));
  ```
- **Khử sạch AI Slop**: 100% icon trên hệ thống là vector SVG đơn sắc (Lucide / Simple Icons), loại bỏ toàn bộ emoji tùy tiện và các dải gradient tím neon rập khuôn.

---

## 7. Ngăn Xếp Công Nghệ (Tech Stack)

- **Backend**: PHP 8.0+ (Tương thích PHP 8.0 – 8.3, chế độ Native Strict Type, PDO MariaDB/MySQL).
- **Cơ sở dữ liệu**: MariaDB 10.4+ / MySQL 8.0 (Bảng mã `utf8mb4_unicode_ci`, khóa ngoại toàn vẹn dữ liệu).
- **Frontend Architecture**:
  - Semantic HTML5 (Phân vùng 4 khối: `<header>` & `<nav>`, Hero Telemetry, `<main>`, `<footer>`).
  - Design Tokens 3 tầng (Primitive &rarr; Semantic &rarr; Component) với CSS Custom Properties.
  - Vanilla JavaScript ES6+ (Zero Framework nặng, hiệu ứng chuyển động vật lý qua GSAP).
- **Xử lý tệp tin học vụ**: Thư viện OpenXML ECMA-376 thuần (`includes/xlsx_helper.php`) hỗ trợ nạp và xuất file Excel (.xlsx) chuẩn không cần cài thêm extension ngoài.

---

## 8. Cấu Trúc Thư Mục Chuẩn

```
d:/xam/htdocs/my-web-project/
├── assets/
│   ├── css/
│   │   └── style.css            # Hệ thống Design Tokens, WCAG Dark Mode & RWD Utilities
│   └── js/
│       └── main.js              # Xử lý tương tác giao diện, chuyển theme, lọc bảng điểm
├── database/
│   └── database.sql             # Cấu trúc CSDL chuẩn MariaDB và dữ liệu khởi tạo
├── includes/
│   ├── algorithms.php           # Động năng học tập, gợi ý can thiệp sư phạm
│   ├── auth.php                 # Tuyến phòng thủ xác thực, bảo vệ RBAC, chống BOLA/IDOR
│   ├── db.php                   # Kết nối CSDL PDO tập trung
│   ├── footer.php               # Chân trang ngữ nghĩa HTML5 có đệm an toàn Safe Area
│   ├── header.php               # Đầu trang HTML5, SEO động, Open Graph vi_VN, SVG icons
│   ├── icons.php                # Thư viện SVG Vector đơn sắc chuẩn hóa (thay thế emoji)
│   ├── radar_helper.php         # Bộ máy phân loại vùng cảnh báo sớm TT22
│   └── xlsx_helper.php          # Engine xử lý file Excel OpenXML (.xlsx / .csv)
├── tests/
│   ├── challenge_m5_radar.php   # Test đối kháng bộ máy Ra-đa TT22
│   ├── run_tests.php            # Test suite tự động E2E 4 Tier (42 kịch bản)
│   ├── verify_m5.php            # Bộ xác minh toàn vẹn giao diện và logic
│   └── verify_challenger_m5_it2.php
├── admin.php                    # Cổng quản trị trung tâm nhà trường (Bento Grid)
├── index.php                    # Bảng điều khiển Ra-đa cảnh báo sớm học lực toàn trường
├── login.php                    # Trang đăng nhập bảo mật (RBAC redirect chuẩn)
├── logout.php                   # Xử lý đăng xuất an toàn, thu hồi session
├── nhap_diem.php                # Sổ điểm điện tử 2D Matrix chuẩn VnEdu
├── quet_nhanh.php               # Chế độ khách quét nhanh dữ liệu học vụ
├── welcome.php                  # Trang chào đón và giới thiệu quy trình sư phạm
└── README.md                    # Tài liệu kỹ thuật dự án
```

---

## 9. Hướng Dẫn Cài Đặt & Vận Hành Trên XAMPP

### Bước 1: Yêu cầu môi trường
- Hệ điều hành: Windows 10/11, Linux hoặc macOS.
- Môi trường máy chủ: XAMPP với **PHP 8.0+** và **MariaDB 10.4+**.
- Trình duyệt: Google Chrome, Microsoft Edge, Mozilla Firefox hoặc Safari bản mới nhất.

### Bước 2: Thiết lập mã nguồn
1. Sao chép toàn bộ thư mục dự án vào thư mục gốc web server:
   - Đường dẫn mặc định: `d:\xam\htdocs\my-web-project\` (hoặc `C:\xampp\htdocs\my-web-project\`).
2. Khởi động dịch vụ **Apache** và **MySQL** trên bảng điều khiển XAMPP Control Panel.

### Bước 3: Cài đặt Cơ sở dữ liệu
1. Truy cập phpMyAdmin tại: `http://localhost/phpmyadmin/`.
2. Tạo mới cơ sở dữ liệu có tên: `edu_radar` với bảng mã (Collation): `utf8mb4_unicode_ci`.
3. Bấm vào tab **Import** (Nhập), chọn tệp `database/database.sql` (hoặc `database.sql` tại thư mục gốc) và bấm **Go** (Thực hiện).

### Bước 4: Đăng nhập và trải nghiệm
Truy cập địa chỉ: `http://localhost/my-web-project/login.php`

**Tài khoản khảo sát hệ thống (Mật khẩu mặc định: `123456`):**
- **Quản trị viên (Admin)**: `admin` &rarr; Tự động chuyển hướng vào `admin.php`.
- **Giáo viên môn Toán**: `gv_toan` &rarr; Tự động chuyển hướng vào `index.php`.
- **Giáo viên Ngữ văn**: `gv_van` &rarr; Tự động chuyển hướng vào `index.php`.

### Bước 5: Chạy bộ kiểm thử tự động (Verification)
Mở cửa sổ dòng lệnh (Terminal / PowerShell) và thực thi:

```bash
& "d:\xam\php\php.exe" "d:\xam\htdocs\my-web-project\tests\run_tests.php"
```

Kết quả mong đợi: **42/42 test cases PASS 100%**, không phát sinh cảnh báo PHP Warning hay vi phạm toàn vẹn dữ liệu.

---

## 10. Bản Quyền & Giấy Phép

Dự án được xây dựng phục vụ chuyển đổi số và nâng cao chất lượng giáo dục Trung học Cơ sở tại Việt Nam, tuân thủ Thông tư 22/2021/TT-BGDĐT của Bộ Giáo dục và Đào tạo.
