# EDURADAR THCS — MASTER KNOWLEDGE REPOSITORIES & TOOLING INVENTORY

> **Kho Tri Thức Toàn Diện (Master Catalog)**  
> *Đã cập nhật theo kết quả nghiên cứu 30 ngày qua (`/last30days`) — ưu tiên các repositories và công cụ có số sao cao nhất (Top Stars).*

---

## 1. KHO REPOSITORIES CHUYÊN MÔN (D:\vibe-coding-tools\knowledge-repos & D:\)

### 1.1. Hệ Sinh Thái Awesome & Ngôn Ngữ Cốt Lõi
* **`sindresorhus/awesome`** (`D:\vibe-coding-tools\knowledge-repos\awesome`): **[350k+ ⭐]**
  - Danh bạ công nghệ lớn nhất thế giới: Front-end, Back-end, Databases, Testing, Security.
* **`ziadoz/awesome-php`** (`D:\vibe-coding-tools\knowledge-repos\awesome-php`): **[32.8k ⭐]**
  - Kho tổng hợp thư viện PHP chuẩn quốc tế: PSR standards, dependency injection, date/time, xử lý chuỗi UTF-8 tiếng Việt, bảo mật và kiểm thử.
* **`chartjs/awesome`** (`D:\vibe-coding-tools\knowledge-repos\awesome-chartjs`): **[2.7k ⭐]** (Hệ sinh thái Chart.js **67.8k ⭐**)
  - Tổng hợp plugin và giải pháp biểu đồ trực quan hóa dữ liệu học tập: Biểu đồ đường tiến trình điểm, radar phân tầng học lực TT22, doughnut phân bố khá/giỏi.

### 1.2. Bảo Mật & Phòng Thủ Hệ Thống (Web Security)
* **`awesome-web-security`** (`D:\vibe-coding-tools\knowledge-repos\awesome-web-security`): **[12k+ ⭐]**
  - Phòng chống OWASP Top 10: SQL Injection, IDOR, Broken Access Control, CSRF, XSS.
  - Cấu hình HTTP Security Headers (`CSP`, `X-Frame-Options: SAMEORIGIN`, `nosniff`, `Referrer-Policy`).
* **`OWASP/CheatSheetSeries`** (`D:\vibe-coding-tools\knowledge-repos\CheatSheetSeries`): **[28k+ ⭐]**
  - Sách mẫu xử lý lỗ hổng web: Session management, Authentication, Authorization RBAC cho Admin/Giáo viên.

### 1.3. Cơ Sở Dữ Liệu & Tối Ưu Truy Vấn (Database & MySQL/MariaDB)
* **`awesome-db`** (`D:\vibe-coding-tools\knowledge-repos\awesome-db`): **[14k+ ⭐]**
  - Lý thuyết thiết kế hệ quản trị cơ sở dữ liệu, ACID, giao dịch (Transactions), cơ chế khóa hàng.
* **`awesome-mysql`** (`D:\vibe-coding-tools\knowledge-repos\awesome-mysql`): **[5k+ ⭐]**
  - Tối ưu hóa truy vấn MySQL/MariaDB, cấu trúc Index cho bảng lớn (`scores`, `students`), tránh full-table scan khi tính điểm Thông tư 22, sao lưu dự phòng an toàn.

### 1.4. Quy Tắc Lập Trình & Prompt Intelligence
* **`awesome-cursorrules`** (`D:\vibe-coding-tools\knowledge-repos\awesome-cursorrules`): **[20k+ ⭐]**
  - Tiêu chuẩn code sạch cho PHP 8+, JavaScript thuần (DOM/GSAP), CSS Architecture.

### 1.5. Kiến Trúc Hệ Thống & Chịu Tải
* **`system-design-primer`** (`D:\system-design-primer`): **[300k+ ⭐]**
  - Thiết kế hệ thống trường học quy mô lớn: Caching, Database Indexing, High Availability, giảm độ trễ response time (< 50ms).

### 1.6. Kho Tri Thức Claude & Nâng Cao (`D:\claude-top-repos` & `D:\onyx-knowledge`)
* **`diagram-design`**: Thiết kế sơ đồ kiến trúc Mermaid, flowchart, sequence diagram trực quan.
* **`humanizer`**: Tinh chỉnh văn phong sư phạm thân thiện, tự nhiên, chuyên nghiệp.
* **`Scrapling`**: Tiện ích bóc tách dữ liệu thông minh khi cần import bảng điểm từ nguồn khác.
* **`onyx-knowledge`**: Kho tài liệu xử lý nghiệp vụ, quản lý dữ liệu giáo dục.

---


### 1.7. Khoa Học Trải Nghiệm Người Dùng (UX Science) & Kiến Trúc SASS Chuyên Nghiệp
* **lexpate/awesome-design-systems** (D:\vibe-coding-tools\knowledge-repos\awesome-design-systems): **[26.3k ⭐]**
  - Tổng hợp các Design System hàng đầu thế giới (Google Material 3, Apple Human Interface, Atlassian, Shopify Polaris).
  - Chuẩn mực thiết kế Component Tokens, Accessibility (A11y), cấu trúc Spacing scale (4px, 8px, 12px, 16px, 24px) và Elevation bóng đổ.
* **Famolus/awesome-sass** (D:\vibe-coding-tools\knowledge-repos\awesome-sass): **[1.9k ⭐]**
  - Chuẩn mực kiến trúc SASS 7-1 (Abstracts, Base, Components, Layout, Pages, Themes, Vendors).
  - Tối ưu hóa Mixins, biến màu sắc, nesting không vượt quá 3 cấp để tránh phình dung lượng CSS.
* **Cẩm nang Khoa Học Người Dùng (user_experience_and_sass_guide.md)**:
  - **Định luật Fitts**: Kích thước nút tối thiểu 44px, đặt ở vị trí thuận mắt (Góc phải dưới, đuôi form).
  - **Định luật Hick**: Mỗi màn hình chỉ có 1 nút Primary nổi bật nhất; áp dụng Progressive Disclosure (tiết lộ lũy tiến) để tránh làm người dùng ngợp.
  - **Định luật Jakob**: Tôn trọng thói quen cũ của thầy cô (phím mũi tên, Enter nhảy ô như VnEdu/Excel).

## 2. QUY CHUẨN KIẾN TRÚC GIAO DIỆN & NỘI DUNG (INTERDATA STANDARD)
* **Hạ tầng (Infrastructure)**: Apache/XAMPP, PHP 8.0+, MariaDB, Domain tĩnh Ngrok HTTPS.
* **Kiến trúc nội dung (Information Architecture)**: Phân cấp hình tháp (Trang chủ > Phân hệ > Sổ điểm chi tiết theo Lớp/Môn).
* **Điều hướng (Navigation)**: Topbar, Sidebar 230px, Breadcrumbs (`🏠 Trang Chủ > ...`), Footer 3 cột chuyên nghiệp.
* **Hỗ trợ người dùng (User Support)**: Floating Help Widget (`?`), Drawer chứa phím tắt và tóm tắt TT22, đầu mối hotline kỹ thuật.
* **Trang phản hồi lỗi (Error Pages)**: Trang lỗi `404.php`, `403.php` đồng bộ chuẩn UI EdTech.

---

## 3. DANH MỤC SKILLS NỘI TẠI (D:\.agents\skills & BUILTIN)

| Skill | Vị trí | Giá trị & Ứng dụng cụ thể cho EduRadar THCS |
| :--- | :--- | :--- |
| **`ponytail`** | `d:\.agents\skills\ponytail` | **Quy tắc tối thượng**: Giải quyết triệt để với diff code ngắn nhất, không boilerplate, không thêm thư viện thừa. |
| **`ui-ux-pro-max`** | `d:\.agents\skills\ui-ux-pro-max` | Bảng màu sư phạm chuẩn TT22, tương phản màu, Typography, Bento Grid, khoảng cách và hierarchy chuẩn quốc tế. |
| **`apple-design`** | `d:\.agents\skills\apple-design` | Hiệu ứng mờ đục kính (glassmorphism), chuyển động tự nhiên (fluid physics), nút chuyển theme Sáng/Tối. |
| **`impeccable`** | `d:\.agents\skills\impeccable` | Rà soát lỗi hiển thị, responsive trên điện thoại/máy tính bảng, sửa các vùng tràn ngang (overflow). |
| **`design-system`** | `d:\.agents\skills\design-system` | Quản lý CSS Variables (`--primary`, `--card-bg`, `--text-heading`), border-radius đồng nhất. |
| **`last30days`** | `d:\.agents\skills\last30days` | Nghiên cứu cập nhật công nghệ và xu hướng thiết kế web giáo dục mới nhất. |

---

## 4. MCP SERVERS KẾT NỐI HỆ THỐNG
1. **`windows-mcp`**: Thao tác hệ thống Windows, kiểm tra tiến trình, chụp màn hình kiểm thử giao diện.
2. **`canva`**: Hỗ trợ thiết kế đồ họa, banner, ấn phẩm truyền thông giáo dục.
3. **`youtube-transcript`**: Phân tích nội dung và tài liệu video bài giảng.
4. **`mcp-server-mysql` & `php-mcp/server`**: Tiêu chuẩn kết nối truy vấn an toàn và chuẩn hóa API MCP cho hệ sinh thái PHP/MySQL.

---

## 5. QUY TRÌNH THỰC THI CHUẨN MỰC (BẮT BUỘC MỌI TÁC VỤ)
1. **BƯỚC 1: XÁC ĐỊNH & BẮT BỆNH VẤN ĐỀ (PROBLEM TRIAGE FIRST)**  
   - Hiện tượng gì? Xảy ra ở file/màn hình nào? Bản chất kỹ thuật (UI, PHP, SQL, Security, Server) là gì?
2. **BƯỚC 2: ĐỐI CHIẾU KNOWLEDGE REPOSITORIES & CHỌN QUY TRÌNH**  
   - Mở đúng tài liệu trong danh mục trên để áp dụng chuẩn kỹ thuật.
3. **BƯỚC 3: THỰC THI TINH GỌN (PONYTAIL MINIMUM VIABLE DIFF)**  
   - Viết lượng code tối thiểu, bảo toàn tính đúng đắn và an toàn.
4. **BƯỚC 4: KIỂM CHỨNG & BÁO CÁO (VERIFY & REPORT)**  
   - Kiểm tra thực tế và báo cáo 1–2 câu: việc đã làm, việc đã bỏ qua và rủi ro.
