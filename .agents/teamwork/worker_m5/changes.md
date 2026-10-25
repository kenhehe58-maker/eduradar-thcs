# Chi Tiết Thay Đổi Mã Nguồn — Milestone 5 (changes.md)

**Worker**: `worker_m5`  
**Milestone**: M5 (Early-Warning Radar & SaaS UI Redesign)  
**Thời gian**: 2026-10-17T17:51:00Z  

---

## 1. Danh Sách Các File Đã Thay Đổi & Tạo Mới

| STT | Đường Dẫn File | Thao Tác | Mục Đích / Tóm Tắt Thay Đổi |
|:---:|---|:---:|---|
| 1 | `index.php` | Đại tu (Overhaul) | Tích hợp Động cơ Chẩn đoán Sớm 3 vùng (🔴 Nguy cấp, 🟡 Cần chú ý, 🟢 Ổn định); 4 thẻ thống kê tỷ lệ %; bảng radar 12 cột chuẩn Thông tư 22; lọc chip client-side; xóa 100% Zalo & dead code; phân quyền RBAC nghiêm ngặt (giáo viên chỉ thấy môn/lớp được phân công). |
| 2 | `assets/js/main.js` | Viết lại (Rewrite) | Xóa hoàn toàn mã clipboard Zalo (`.copy-btn`, `#toast`), xóa modal rác (`#actionModal`, `openActionModal`, `closeActionModal`); bổ sung bộ lọc tương tác thời gian thực không lỗi console. |
| 3 | `assets/css/style.css` | Nâng cấp (Enhance) | Bổ sung design tokens chuẩn Stitch UI (`--primary`, `--danger`, `--warning`, `--success`, `--shadow-*`, `--radius-*`); thêm class role badge (`.role-badge.role-admin`, `.role-badge.role-teacher`); thêm styles cho header/footer thống nhất, thẻ thống kê stat-card, filter chips và radar-table. |
| 4 | `includes/header.php` | Tạo mới (New) | Shell header chuẩn SaaS Stitch UI: logo brand, điều hướng tab với trạng thái `active` theo trang, hiển thị tên người dùng và huy hiệu vai trò phân cấp, nút đăng xuất an toàn. |
| 5 | `includes/footer.php` | Tạo mới (New) | Shell footer chuẩn SaaS Stitch UI: bản quyền, trích dẫn Thông tư 22/2021/TT-BGDĐT, chỉ báo trạng thái kết nối MariaDB 10.4. |
| 6 | `tests/verify_m5.php` | Tạo mới (New) | Bộ kiểm thử tự động 42 assertions chia làm 7 nhóm kiểm thử (Thuật toán 3 vùng, Trường hợp biên sa sút, Bộ đếm thống kê & %, Thẻ lọc tương tác DOM, Kiểm toán diệt Zalo, Phân quyền RBAC, Design tokens Stitch UI). Đạt 100% PASS (42/42). |

---

## 2. Chi Tiết Thay Đổi Từng File

### 2.1 `index.php`
- **Trước**:
  - Truy vấn dữ liệu dùng tên cột cũ thời SQLite (`m1, m2, p15_1, p15_2, t1_1, gk, ck, avg_score`).
  - Phân quyền giáo viên chỉ lọc `WHERE s.class_id IN (SELECT class_id FROM teacher_classes...)` mà không ràng buộc `tc.subject = sc.subject`, gây rò rỉ dữ liệu điểm môn khác giữa các giáo viên cùng dạy chung một lớp.
  - Chứa hàm `buildFeedback()` sinh tin nhắn Zalo, nút `📋 Copy Tin Nhắn Zalo` và phần tử `#toast`.
  - Logic phân loại rủi ro cũ gọi hằng số chưa định nghĩa `PASS_SCORE` và thiếu các điều kiện nghiệp vụ cốt lõi của M5.
- **Sau**:
  - Triển khai hàm `evaluateRiskZone(?float $avg, ?float $gk, ?float $ck, array $txScores = []): array`:
    - 🔴 **Nguy cấp**: `avg < 3.5` HOẶC `ck < 3.0` HOẶC `(gk < 5.0 && ck < 5.0)`. Gắn thẻ lý do tiếng Việt rõ ràng.
    - 🟡 **Cần chú ý**: `3.5 <= avg < 5.0` HOẶC sa sút thi cuối kỳ `ck < gk - 1.5` (`round($gk - $ck, 1) > 1.5`).
    - 🟢 **Ổn định**: `avg >= 5.0` (và các trường hợp chưa có điểm xếp vào ổn định an toàn).
  - 4 thẻ thống kê: Tổng số hồ sơ, 🔴 Nguy cấp, 🟡 Cần chú ý, 🟢 Ổn định kèm tính toán tỷ lệ % an toàn chia cho 0 (`total > 0 ? round(...) : 0.0`).
  - Bảng 12 cột chuẩn: `STT, Mã HS, Họ và Tên, Lớp & Môn, Ngày Sinh, ĐĐGtx (1-4), GK, CK, ĐTBmhk, Trạng Thái, Nguyên Nhân Chẩn Đoán, Hành Động`.
  - Cột hành động chứa nút liên kết trực tiếp: `<a href="nhap_diem.php?class_id=X&subject=Y" class="btn-goto-gradebook">📝 Vào sổ điểm</a>`.
  - Khóa chặt truy vấn SQL theo `teacher_classes tc` có kiểm tra phân quyền giáo viên theo cặp `(class_id, subject)` chính xác. Admin có thể xem toàn trường và chuyển đổi bộ lọc lớp/môn linh hoạt.
  - Xóa 100% chữ 'zalo', nút sao chép, toast và modal can thiệp.

### 2.2 `assets/js/main.js`
- **Trước**:
  - Chứa 17 dòng sao chép clipboard Zalo gắn với `.copy-btn` và `#toast`.
  - Chứa 18 dòng modal can thiệp rác `actionModal`, `modalStudentId`, `openActionModal`, `closeActionModal`.
  - Lỗi console tiềm ẩn: `r.dataset.name.includes(q)` ném ngoại lệ khi dòng trống không có `data-name`.
- **Sau**:
  - Xóa 100% mã Zalo và modal rác.
  - Viết lại bộ điều khiển tìm kiếm và lọc tức thì:
    - Sử dụng `document.querySelectorAll('tbody tr[data-level]')` nhắm chính xác dòng học sinh hợp lệ.
    - Truy xuất thuộc tính phòng thủ: `const name = (r.dataset.name || '').toLowerCase()`.
    - Hỗ trợ lọc theo chip rủi ro (`data-filter="all|red|yellow|green"`) và tìm kiếm thời gian thực theo cả họ tên, mã học sinh, lớp, môn học.
    - Không phát sinh bất kỳ lỗi console nào (`0 console errors`).

### 2.3 `assets/css/style.css`
- **Trước**:
  - Thiếu class Role Badges chuyên biệt cho Admin và Teacher.
  - Thiếu layout chuẩn cho header thanh điều hướng ngang Stitch UI.
  - Thiếu styles cho 4 thẻ thống kê có tỷ lệ %, các nút chip lọc và bảng radar 12 cột.
  - Tồn tại các class `.toast` rác.
- **Sau**:
  - Bổ sung đầy đủ design tokens: `--primary-border`, `--danger-dark`, `--warning-dark`, `--success-dark`, `--shadow-*`, `--radius-*`.
  - Định nghĩa class `.role-badge.role-admin` (hoặc `.badge-role-admin`) và `.role-badge.role-teacher` (hoặc `.badge-role-teacher`) với chấm trạng thái `badge-dot`.
  - Định nghĩa hệ thống navigation header `.stitch-header`, `.nav-tab`, `.nav-tab.active`, `.btn-stitch-logout` và footer `.stitch-footer`.
  - Định nghĩa `.stat-card` với viền trên theo màu trạng thái, badge tỷ lệ phần trăm `.card-pct`.
  - Định nghĩa `.chip`, `.chip.active`, `.chip-red`, `.chip-yellow`, `.chip-green` và bảng `.radar-table`.
  - Xóa bỏ hoàn toàn định nghĩa `.toast`.

### 2.4 `includes/header.php` & `includes/footer.php`
- Tạo mới 2 template header/footer chuẩn mực theo kiến trúc SaaS Stitch UI.
- `header.php`:
  - Nạp Google Fonts `Plus Jakarta Sans` và `JetBrains Mono`.
  - Hiển thị logo EduRadar với icon vệ tinh `📡`.
  - Menu điều hướng: `Radar Cảnh Báo` (`index.php`), `Sổ Điểm VnEdu` (`nhap_diem.php`), và `Quản Trị Nhà Trường` (`admin.php` — **chỉ hiển thị khi `role === 'admin'`**, giáo viên không thấy link này).
  - Đánh dấu trạng thái `active` trên tab tương ứng theo biến `$activeNav`.
  - Khu vực thông tin người dùng: Họ tên hiển thị, badge vai trò phân biệt (`.role-admin` vs `.role-teacher`), và nút đăng xuất `Đăng Xuất`.
- `footer.php`:
  - Thông tin bản quyền, tiêu chuẩn Thông tư 22/2021/TT-BGDĐT, chỉ báo trạng thái MariaDB.

### 2.5 `tests/verify_m5.php`
- Hiện thực toàn diện 42 bài kiểm thử tự động chia làm 7 nhóm:
  - Nhóm 1 (8 tests): Thuật toán phân loại 3 vùng C1-C3, W1, S1, dữ liệu trống.
  - Nhóm 2 (6 tests): Trường hợp biên sa sút cuối kỳ (`ck < gk - 1.5`), ranh giới 1.5 vs 1.6, thứ tự ưu tiên vùng Đỏ.
  - Nhóm 3 (5 tests): Tính bất biến của bộ đếm thống kê, tổng tỷ lệ ~100%, phòng thủ chia cho 0, cấu trúc thẻ stat trên HTML thực tế, truy vấn điểm trực tiếp từ database.
  - Nhóm 4 (6 tests): Thuộc tính DOM `data-filter`, `data-level`, `data-name`, ô `#search`, cột điểm thành phần & điểm TB, liên kết vào sổ điểm `nhap_diem.php?class_id=...&subject=...`.
  - Nhóm 5 (6 tests): Kiểm toán triệt để 0 từ khóa 'zalo', 0 'actionModal', 0 'copy-btn'/'copyMessage', 0 'zalo' trong HTML phản hồi.
  - Nhóm 6 (5 tests): Phân quyền phục vụ RBAC trên `index.php` (chặn chưa đăng nhập 302, giáo viên `gv_toan` vào 200 nhưng không thấy môn Ngữ Văn và không thấy link `admin.php`, admin xem toàn trường 200).
  - Nhóm 7 (6 tests): Kiểm tra tính toàn vẹn của design tokens, font `Plus Jakarta Sans`, role badges và navigation active state.
- Kết quả chạy thực tế: **42/42 tests PASS, 0 failures**.
