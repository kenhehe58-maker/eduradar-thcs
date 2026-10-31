# CẨM NANG KHOA HỌC TRẢI NGHIỆM NGƯỜI DÙNG (UX SCIENCE), SẮP ĐẶT NÚT BẤM & KIẾN TRÚC SASS

> **Bộ Quy Chuẩn Khoa Học Trải Nghiệm & Bố Cục Chức Năng (Master UX/SASS Guide)**  
> *Được tổng hợp từ `Laws of UX`, `awesome-design-systems` (26.3k ⭐), `awesome-sass` (1.9k ⭐) và hệ tri thức Apple Design & Impeccable.*

---

## 1. CÁC QUY LUẬT TÂM LÝ HỌC & KHOA HỌC NGƯỜI DÙNG (LAWS OF UX)

### 1. Định luật Fitts (Fitts's Law) — Bố Trí Nút Bấm & Vùng Chạm
* **Nguyên lý**: Thời gian để người dùng di chuyển chuột hoặc ngón tay đến một mục tiêu phụ thuộc vào **khoảng cách** và **kích thước** của mục tiêu đó.
* **Quy chuẩn bố trí nút trên EduRadar**:
  - **Nút hành động chính (Primary Action - CTA)**: Luôn có kích thước tối thiểu **44px x 44px** trên cảm ứng điện thoại (Tap Target standard), độ tương phản cao nhất (ví dụ nút "Lưu Bảng Điểm", "Xem Báo Cáo").
  - **Vị trí thuận tự nhiên**: Đặt ở các mép màn hình, góc dưới bên phải hoặc ngay sát tầm mắt kết thúc quy trình đọc (F-Pattern / Z-Pattern). Đó là lý do Nút Trợ Giúp Nhanh (?) được đặt cố định ở góc phải dưới (Floating FAB).

### 2. Định luật Hick (Hick's Law) — Giảm Thiểu Sự Lúng Túng
* **Nguyên lý**: Thời gian đưa ra quyết định tăng theo cấp số nhân với số lượng và độ phức tạp của các lựa chọn.
* **Quy chuẩn chức năng**:
  - Không bao giờ bày ra 15 nút cùng lúc trên màn hình sổ điểm.
  - Áp dụng nguyên lý **Phân cấp lũy tiến (Progressive Disclosure)**:
    - Mức 1 (Trực quan ngay): Nút "Lưu bảng điểm" (Primary), "Xuất Excel" (Secondary), "Làm mới" (Ghost).
    - Mức 2 (Ẩn sâu hơn): Các chức năng cấu hình chuyên môn, chuyển lớp, chuyển môn gom vào dropdown lọc có nhãn rõ ràng.

### 3. Định luật Proximity (Quy Luật Khoảng Cách Gần)
* **Nguyên lý**: Các đối tượng nằm gần nhau được não bộ tự động hiểu là có liên quan mật thiết với nhau.
* **Ứng dụng**:
  - Ô nhập điểm ĐĐGtx 1, 2, 3, 4 phải nằm trong cụm cột "Thường xuyên". Cột Giữa kỳ và Cuối kỳ có đường viền ngăn cách (divider) và màu nền phân biệt nhẹ để giáo viên không nhập nhầm.
  - Nút lưu điểm đặt cạnh chỉ số trạng thái "Đã lưu / Chưa lưu".

### 4. Định luật Jakob (Jakob's Law) — Tôn Trọng Thói Quen Cũ
* **Nguyên lý**: Người dùng dành phần lớn thời gian trên các hệ thống khác (VnEdu, SMAS, Excel), họ muốn EduRadar hoạt động theo cách tương tự mà họ đã quen thuộc.
* **Ứng dụng**:
  - Phím tắt điều hướng ô điểm bằng **4 phím mũi tên** $\uparrow \downarrow \leftarrow \rightarrow$ và phím `Enter` nhảy dòng y hệt phần mềm VnEdu / Microsoft Excel.
  - Phím `Ctrl + S` lưu tức thì.

---

## 2. HỆ THỐNG PHÂN CẤP NÚT BẤM (BUTTON HIERARCHY SYSTEM)

Mỗi màn hình chỉ có **DUY NHẤT 1 NÚT CHÍNH (PRIMARY)** để người dùng không bao giờ phải suy nghĩ xem cần bấm vào đâu trước.

```
┌────────────────────────────────────────────────────────┐
│ 1. PRIMARY BUTTON (Hành động quyết định / Lưu trữ)     │
│    - Nền: Màu thương hiệu chính (--primary: #2563eb)   │
│    - Chữ: Trắng #ffffff, Font-weight: 700              │
│    - Shadow: Có đổ bóng nhẹ nâng cao tầm nhìn          │
│    - Ví dụ: [💾 Lưu bảng điểm], [📊 Xem cảnh báo]       │
├────────────────────────────────────────────────────────┤
│ 2. SECONDARY BUTTON (Hành động phụ trợ / Xuất dữ liệu) │
│    - Nền: Trắng / Xám nhạt, Viền mỏng 1px #cbd5e1      │
│    - Chữ: Màu nội dung đậm #0f172a                     │
│    - Ví dụ: [📥 Xuất Excel mẫu], [🔄 Làm mới]           │
├────────────────────────────────────────────────────────┤
│ 3. TERTIARY / GHOST BUTTON (Hành động tra cứu / Hủy)   │
│    - Nền: Trong suốt (Transparent)                     │
│    - Hover: Đổi màu nền siêu nhẹ                       │
│    - Ví dụ: [Chi tiết học sinh], [Đóng]                │
├────────────────────────────────────────────────────────┤
│ 4. DESTRUCTIVE BUTTON (Hành động xóa / Nguy hiểm)      │
│    - Màu đỏ cảnh báo (#dc2626), đòi hỏi xác nhận modal │
│    - Ví dụ: [🗑️ Xóa lớp học], [Thoát phiên]            │
└────────────────────────────────────────────────────────┘
```

---

## 3. KIẾN TRÚC SASS CHUYÊN NGHIỆP (SASS 7-1 PATTERN)

Theo quy chuẩn từ `awesome-sass` và `KittyGiraudel/sass-guidelines`:
Để duy trì giao diện lớn không bị rối code, mã nguồn CSS/SCSS được tổ chức theo mô hình **7 thư mục - 1 file chính**:

```
assets/scss/
├── abstracts/          # Biến số toàn cục, mixins, functions (Theme tokens, breakpoints)
│   ├── _variables.scss # $primary, $card-bg, $border-color, $font-main
│   ├── _mixins.scss    # @mixin flex-center, @mixin button-base
│   └── _functions.scss # Chuyển đổi px sang rem
├── base/               # Thiết lập nền tảng, reset CSS, typography
│   ├── _reset.scss
│   └── _typography.scss
├── components/         # Từng khối giao diện độc lập (tái sử dụng cao)
│   ├── _buttons.scss   # Chuẩn phân cấp nút bấm Primary/Secondary/Ghost
│   ├── _breadcrumbs.scss # Đường dẫn phân cấp
│   ├── _cards.scss     # Bento Card, KPI Card, Handbook Card
│   ├── _drawer.scss    # Drawer Trợ giúp nhanh (?)
│   └── _tables.scss    # Bảng ma trận điểm VnEdu
├── layout/             # Bố cục khung sườn của website
│   ├── _header.scss    # Glass Topbar
│   ├── _sidebar.scss   # Rail Navigation 230px
│   └── _footer.scss    # Footer EdTech 3 cột
├── pages/              # Style chuyên biệt cho từng trang đơn lẻ
│   ├── _welcome.scss
│   ├── _gradebook.scss
│   └── _radar.scss
└── style.scss          # File chủ nạp toàn bộ các thành phần trên
```

---

## 4. BẢNG KIỂM TRA (CHECKLIST) TRẢI NGHIỆM NGƯỜI DÙNG CHO MỖI TÍNH NĂNG MỚI
Trước khi phát hành bất kỳ màn hình nào:
- [x] Người dùng có biết ngay mình đang ở đâu không? (Đã có **Breadcrumbs**).
- [x] Nút quan trọng nhất có nổi bật nhất không? (Quy tắc **1 Primary Button**).
- [x] Có phản hồi ngay khi bấm không? (Hiệu ứng Ripple / State Dirty "Chưa lưu" $\rightarrow$ "Đã lưu").
- [x] Nếu gặp khó khăn, tìm trợ giúp ở đâu? (Có ngay **Floating FAB ?** góc phải dưới).
- [x] Trên màn hình điện thoại có bị che lấp hay vỡ không? (Kích thước bấm $\ge 44\text{px}$, không overflow-x).
