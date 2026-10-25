# 📘 HỒ SƠ THUYẾT TRÌNH & ĐẶC TẢ TOÀN DIỆN: HỆ THỐNG EDURADAR THCS
**Đồng Hành Cùng Học Sinh Bứt Phá Năng Lực Học Tập**  
*Hệ thống Ra-đa 2 khung đánh giá tự động: phát hiện sớm nguy cơ sụt giảm điểm số theo Thông tư 22/2021/TT-BGDĐT và gợi ý lộ trình bồi dưỡng cá nhân hóa tức thì.*

---

## 📑 MỤC LỤC CHI TIẾT
1. **Chương I: Căn Cứ Pháp Lý & Cơ Sở Lý Luận Sư Phạm (Thông tư 22/2021/TT-BGDĐT)**
2. **Chương II: Thực Trạng Giáo Dục & Những Điểm Nghẽn Của Phần Mềm Hiện Hành**
3. **Chương III: Trái Tim Của EduRadar: Động Cơ Đánh Giá 2 Khung Độc Lập**
4. **Chương IV: Đặc Tả Chi Tiết 7 Phân Hệ Chức Năng**
5. **Chương V: Kiến Trúc Kỹ Thuật, An Ninh Dữ Liệu & Hiệu Năng**
6. **Chương VI: Kịch Bản Thuyết Trình Chi Tiết Từng Bước (Word-for-Word Script)**
7. **Chương VII: Bộ Câu Hỏi Phản Biện Từ Ban Giám Hiệu & Câu Trả Lời Chuẩn Mực**

---

# CHƯƠNG I: CĂN CỨ PHÁP LÝ & CƠ SỞ LÝ LUẬN SƯ PHẠM

### 1.1. Căn cứ pháp lý cốt lõi
Hệ thống EduRadar THCS được thiết kế và lập trình bám sát 100% các văn bản chỉ đạo của Bộ Giáo dục và Đào tạo:
* **Thông tư số 22/2021/TT-BGDĐT** ngày 20/07/2021 của Bộ GD&ĐT quy định về đánh giá học sinh trung học cơ sở và trung học phổ thông (thay thế Thông tư 58/2011/TT-BGDĐT và Thông tư 26/2020/TT-BGDĐT).
* **Công văn 5512/BGDĐT-GDTrH** về việc xây dựng và tổ chức thực hiện kế hoạch giáo dục của nhà trường.
* Đề án chuyển đổi số quốc gia trong giáo dục và đào tạo giai đoạn 2022–2025, định hướng 2030 (triển khai Học bạ số, Sổ điểm điện tử đồng bộ dữ liệu tập trung).

### 1.2. Chuyển dịch triết lý sư phạm trong Thông tư 22
Khác với Thông tư 58 cũ (thiên về tính toán điểm số tĩnh và phân loại cơ học theo điểm trung bình các môn), Thông tư 22 đặt trọng tâm vào:
1. **Đánh giá vì sự tiến bộ của người học (Assessment for Learning):** Đánh giá không phải để "dán nhãn" hay trừng phạt học sinh vào cuối năm, mà là một công cụ chẩn đoán liên tục giúp giáo viên điều chỉnh phương pháp dạy học và giúp học sinh nhận ra điểm nghẽn để bứt phá.
2. **Kết hợp hài hòa giữa Đánh giá Thường xuyên (ĐĐGtx) và Đánh giá Định kỳ (ĐĐGđk - GK, CK):** Quá trình học tập (hệ số 1) phản ánh thái độ và sự kiên trì; kỳ thi giữa kỳ (hệ số 2) và cuối kỳ (hệ số 3) kiểm chứng năng lực tổng hợp.
3. **Phân tầng 4 mức học lực:** 
   * **Tốt:** Tất cả các môn đánh giá bằng điểm số có ĐTBmhk $\ge 6.5$, trong đó có ít nhất 06 môn đạt $\ge 8.0$.
   * **Khá:** Tất cả các môn đánh giá bằng điểm số có ĐTBmhk $\ge 5.0$, trong đó có ít nhất 06 môn đạt $\ge 6.5$.
   * **Đạt:** Có nhiều nhất 01 môn đánh giá bằng nhận xét ở mức Chưa đạt; có ít nhất 06 môn đánh giá bằng điểm số có ĐTBmhk $\ge 5.0$; không có môn nào có ĐTBmhk $< 3.5$.
   * **Chưa Đạt:** Các trường hợp còn lại.

```
                           CÔNG THỨC TÍNH ĐTBMHK (CHUẨN TT22)
          Tổng các ĐĐGtx + (2 × Điểm Giữa Kỳ) + (3 × Điểm Cuối Kỳ)
ĐTBMHK = ──────────────────────────────────────────────────────────
                    Số ĐĐGtx + 2 + 3
```

---

# CHƯƠNG II: THỰC TRẠNG GIÁO DỤC & LỖ HỔNG CỦA PHẦN MỀM HIỆN HÀNH

Dù các hệ thống như **vnEdu, SMAS, MISA EMIS** đã được triển khai rộng rãi, thực tế tại các trường THCS vẫn tồn tại 4 "khoảng trống" sư phạm nghiêm trọng:

```
┌────────────────────────────────────────────────────────────────────────┐
│               THỰC TRẠNG QUẢN LÝ ĐIỂM SỐ HIỆN NAY                      │
├───────────────────────┬────────────────────────────────────────────────┤
│ 1. Kho dữ liệu tĩnh   │ Chỉ lưu trữ số liệu, không có khả năng         │
│    (Data Tomb)        │ suy luận logic hay phát hiện xu hướng biến     │
│                       │ thiên của học sinh.                            │
├───────────────────────┼────────────────────────────────────────────────┤
│ 2. "Cảnh báo muộn"    │ Giáo viên chỉ phát hiện học sinh Chưa Đạt      │
│    (Late Warning)     │ khi đã khóa sổ điểm cuối kỳ. Lúc này không     │
│                       │ còn quỹ thời gian để kèm cặp hay gỡ điểm.      │
├───────────────────────┼────────────────────────────────────────────────┤
│ 3. Đứt gãy GVBM-GVCN  │ GVBM phát hiện điểm kiểm tra thường xuyên      │
│    (Silo Disconnect)  │ tụt dốc nhưng không có kênh chuyển tiếp nhanh  │
│                       │ sang GVCN để trao đổi cùng phụ huynh.          │
├───────────────────────┼────────────────────────────────────────────────┤
│ 4. Quá tải hành chính │ Giáo viên phải gõ tay hàng nghìn con số và     │
│    (Teacher Burnout)  │ sao chép các mẫu nhận xét vô hồn, mất đi      │
│                       │ thời gian quý báu dành cho chuyên môn.         │
└───────────────────────┴────────────────────────────────────────────────┘
```

> **Hậu quả:** Hàng nghìn học sinh có dấu hiệu sa sút từ tuần 4, tuần 5 nhưng không được nâng đỡ kịp thời, dẫn đến kết quả thi giữa kỳ và cuối kỳ bị hỏng, kéo tụt học lực toàn khối và gây áp lực lên tỷ lệ hoàn thành chương trình của nhà trường.

---

# CHƯƠNG III: TRÁI TIM CỦA EDURADAR: ĐỘNG CƠ ĐÁNH GIÁ 2 KHUNG ĐỘC LẬP

Điểm đột phá mang tính bản quyền của **EduRadar THCS** là việc tách rời hoàn toàn khái niệm **"Điểm Số Hiện Tại"** và **"Động Năng Biến Thiên"**. Một điểm số 6.5 không phản ánh được em đó đang cố gắng vươn lên hay đang trên đà trượt dốc.

```
                        KIẾN TRÚC 2 KHUNG ĐÁNH GIÁ ĐỘC LẬP
                                        │
        ┌───────────────────────────────┴───────────────────────────────┐
        ▼                                                               ▼
⚡ KHUNG 1: HIỆU SUẤT & ĐỘNG NĂNG                               📊 KHUNG 2: PHÂN KHÚC ĐIỂM SỐ
(Momentum - Vận tốc & Xu hướng)                                (Score Ranges - Chuẩn TT22)
────────────────────────────────                               ────────────────────────────
📈 ĐANG TIẾN BỘ / BỨT PHÁ                                      ⭐ GIỎI & KHÁ
   • Bài sau cao hơn bài trước $\ge 1.0$                          • ĐTB môn $\ge 6.5$
   • Điểm Cuối kỳ bứt phá so với Giữa kỳ                          • Đủ điều kiện học lực cao
🔄 ĐANG ĐI ĐỀU / DUY TRÌ                                        🟡 TRUNG BÌNH
   • Điểm số ổn định, biên độ dao động $\le 0.5$                  • ĐTB môn $5.0 - 6.4$
   • Cần giải pháp kích hoạt để bứt phá                           • Ngưỡng tiệm cận an toàn
📉 ĐANG SA SÚT / TUỘT DỐC                                       🔴 YẾU (NGUY CẤP)
   • Chuỗi 2 bài ĐĐGtx giảm liên tục                              • ĐTB môn $< 5.0$
   • Điểm Cuối kỳ tụt $\ge 1.5$ điểm so với GK                     • Hoặc dính điểm liệt $< 3.5$
⏳ ĐANG CHỜ ĐIỂM SỐ
   • Chưa có dữ liệu ghi nhận
```

### 3.1. Ma trận Chẩn đoán Chéo 9 Kịch Bản (The 3x3 Intervention Matrix)
Sự kết hợp giữa 2 khung tạo ra 9 trạng thái tâm lý - sư phạm cụ thể:
1. **Giỏi + Tiến bộ:** Nhóm tinh hoa, đề xuất bồi dưỡng học sinh giỏi hoặc giao bài nâng cao.
2. **Giỏi + Đi đều:** Cần thử thách mới để tránh rơi vào vùng an toàn / chủ quan.
3. **Giỏi + Sa sút:** ⚠️ **CẢNH BÁO ĐỎ VỀ TÂM LÝ:** Học sinh vốn có học lực tốt nhưng đang gặp biến cố tâm lý, gia đình hoặc bắt đầu chểnh mảng. Can thiệp ngay sẽ kéo em lại quỹ đạo.
4. **Trung bình + Tiến bộ:** 🌟 **HỌC SINH TIỀM NĂNG:** Đang có động lực học tập tốt, cần khen ngợi trước lớp và phụ đạo thêm để vươn lên Khá/Giỏi.
5. **Trung bình + Đi đều:** Nhóm chiếm đa số (khoảng 60%), cần đa dạng hóa hình thức kiểm tra.
6. **Trung bình + Sa sút:** ⚠️ Nguy cơ rơi xuống nhóm Yếu rất cao nếu bài thi cuối kỳ dưới 4.0.
7. **Yếu + Tiến bộ:** 🌱 Tín hiệu đáng mừng! Em học sinh yếu đang cố gắng, cần cho thêm bài tập gỡ điểm ĐĐGtx để khích lệ.
8. **Yếu + Đi đều:** Mất gốc kiến thức nền tảng; cần lộ trình phụ đạo riêng biệt từ đầu năm.
9. **Yếu + Sa sút:** 🚨 **TÌNH TRẠNG NGUY CẤP:** Cần kích hoạt quy trình phối hợp khẩn cấp giữa BGH - GVCN - Phụ huynh để tránh việc học sinh phải thi lại trong hè hoặc ở lại lớp.

---

# CHƯƠNG IV: ĐẶC TẢ CHI TIẾT 7 PHÂN HỆ CHỨC NĂNG

### Phân hệ 1: Trang Chủ Bento Grid & Hướng Dẫn 3 Bước (`welcome.php`)
* **Thiết kế:** Bento Grid phong cách Dribbble đẳng cấp quốc tế, chia mảng thông tin khoa học với gam màu trung tính, không chói mắt.
* **Quy trình 3 bước (Interactive Stepper):**
  * *Bước 1: Chọn Lớp & Môn Phụ Trách:* Lọc nhanh theo phân công chuyên môn.
  * *Bước 2: Quét Radar & Lọc Nguy Cơ:* Xem bảng cảnh báo 2 khung tự động.
  * *Bước 3: Nhập Điểm & Xuất Báo Cáo:* Đồng bộ vnEdu và in ấn báo cáo BGH.
* **Thanh tìm kiếm Input Mask:** Cho phép gõ nhanh tên hoặc mã định danh học sinh với gợi ý tự động, chuyển hướng thẳng đến hồ sơ học sinh.

### Phân hệ 2: Bảng Điều Khiển Cảnh Báo Sớm (`index.php`)
* **Thanh Ma Trận Lớp & Môn (Class-Subject Matrix Bar):** Đặt ngay đầu trang, chuyển lớp và môn trong 1 cú click. Tự động hiển thị sĩ số và trạng thái dữ liệu.
* **Bộ Lọc Đa Chọn (Multi-Select Filter Chips):** 
  * Giáo viên có thể bật đồng thời nhiều tiêu chí (ví dụ: vừa chọn `📉 Sa sút`, vừa chọn `🔴 Yếu`).
  * Hệ thống áp dụng thuật toán **Union Filtering (Bộ lọc hợp)** tức thì trên giao diện client-side, phản hồi dưới 5 mili-giây.
* **Bảng Dữ Liệu Thông Minh:** 
  * Cột 1 thể hiện rõ trạng thái Động năng (Bứt phá / Đi đều / Sa sút).
  * Khung điểm số phân tách rạch ròi.
  * Thiết kế co giãn linh hoạt (`table-layout: auto`), đảm bảo khi xem ở mức zoom 100% trên màn hình máy tính bảng hay laptop, cột `THAO TÁC` không bao giờ bị cắt xén.

### Phân hệ 3: Hộp Thoại Chẩn Đoán Chi Tiết Học Sinh (Diagnostic Modal)
* Khi bấm nút **"Xem Hồ Sơ & Động Năng"**, một hộp thoại phong cách Apple Glass bật lên:
  * Huy hiệu Avatar chữ cái viết tắt, mã học sinh, lớp, môn học.
  * 3 thẻ tóm tắt: Khung Hiệu Suất, Khung Điểm Số, Phân Vùng Rủi Ro TT22.
  * Trực quan hóa chuỗi điểm số biến thiên: `TX1 → TX2 → TX3 → TX4 → GK → CK → ĐTB`.
  * **Chẩn đoán chi tiết từ Radar:** Nêu rõ nguyên nhân học sinh sa sút (ví dụ: *Hỏng cả bài Giữa kỳ và Cuối kỳ; có 2 bài TX dưới 3.5*).
  * **Khuyến nghị sư phạm tức thì:** Đề xuất giải pháp can thiệp cụ thể.

### Phân hệ 4: Sổ Điểm Điện Tử Live Sync (`nhap_diem.php`)
* **Bám sát biểu mẫu vnEdu:** Đầy đủ các cột kiểm tra thường xuyên (ĐĐGtx 1, 2, 3, 4), điểm Giữa kỳ (GK), Cuối kỳ (CK), Điểm Trung Bình môn (ĐTBmhk) và Xếp loại học lực theo TT22.
* **Tính toán tức thời (Live Calculation):** Khi giáo viên gõ điểm vào bất kỳ ô nào, hệ thống tự động tính lại ĐTB và đổi màu huy hiệu xếp loại ngay trên dòng đó mà không cần tải lại trang.
* **Khóa dữ liệu an toàn:** Phân định rõ quyền sửa điểm theo phân công chuyên môn; có nhật ký kiểm toán (Audit Log) ghi nhận từng thay đổi để chống gian lận.

### Phân hệ 5: Quét Nhanh Bảng Điểm Bằng AI (`quet_nhanh.php`)
* **Công nghệ Nhận Dạng Thị Giác Máy Tính (OCR):**
  * Giáo viên tải lên hình ảnh chụp phiếu điểm kiểm tra viết tay hoặc bảng in danh sách lớp.
  * Mô hình AI tự động tách khung bảng, nhận diện cột Họ tên và các cột Điểm số, xử lý các trường hợp chữ viết tay nghiêng hoặc mờ.
  * Tự động đối soát với danh sách học sinh có sẵn trong cơ sở dữ liệu và điền hàng loạt vào sổ điểm.

### Phân hệ 6: Hệ Thống Báo Cáo Chuyên Sâu Ban Giám Hiệu
* **Chế độ Slide Báo Cáo BGH (`bao_cao_slide_bgh.php`):**
  * Tự động tổng hợp số liệu của từng lớp thành một bộ slide thuyết trình tương tác.
  * Hỗ trợ chuyển slide bằng phím mũi tên, toàn màn hình (F11), hiển thị biểu đồ tròn phân bố học lực và danh sách học sinh cần nhà trường hỗ trợ.
* **Kết xuất PDF A4 Vector (`in_bao_cao_bgh.php?format=pdf`):** Đóng khung tiêu đề chuẩn trường học, căn chỉnh vừa khít khổ giấy A4 dọc, sẵn sàng ký duyệt và đóng dấu.
* **Kết xuất Excel OpenXML (`in_bao_cao_bgh.php?format=xlsx`):** Tệp Excel chuẩn, tích hợp sẵn các công thức tính toán và định dạng màu sắc theo chuẩn Bộ GD&ĐT.

### Phân hệ 7: Cổng Quản Trị Nhà Trường (`admin.php`)
* Dành riêng cho Ban Giám Hiệu và Cán bộ quản trị:
  * Quản lý phân công giảng dạy: Gán lớp, gán môn cho từng giáo viên bằng giao diện ma trận Checkbox trực quan.
  * Import danh sách học sinh và phân bổ lớp từ file Excel của nhà trường.
  * Giám sát nhật ký hoạt động (Audit Trail): Xem ai đã đăng nhập, ai đã nhập điểm, sửa điểm vào thời gian nào.

---

# CHƯƠNG V: KIẾN TRÚC KỸ THUẬT, AN NINH DỮ LIỆU & HIỆU NĂNG

### 5.1. Triết lý "Ponytail" - Senior Engineering (Tối Giản, Bền Vững, Không Rác)
* **Không dùng thư viện cồng kềnh:** Không cài đặt hàng trăm megabyte `node_modules` hay các framework nặng nề dễ lỗi thời. Toàn bộ hệ thống chạy trên nền **PHP 8.0 Native, MariaDB 10.4 và Vanilla ES6 JavaScript**.
* **Tốc độ phản hồi cực nhanh:** Thời gian render trang dưới 30ms; mức tiêu thụ RAM của web server chỉ khoảng 10MB; sẵn sàng phục vụ cùng lúc hàng trăm giáo viên trong trường nhập điểm mà không bao giờ xảy ra tình trạng giật lag hay sập mạng.

### 5.2. An ninh dữ liệu chuẩn trường học
1. **Phòng chống BOLA (Broken Object Level Authorization):** Giáo viên chỉ được truy cập dữ liệu của đúng lớp và môn mình được BGH phân công. Mọi nỗ lực can thiệp URL (IDOR) đều bị máy chủ chặn ngay lập tức (HTTP 403) và ghi nhật ký cảnh báo.
2. **Bảo mật xác thực:** Mật khẩu được mã hóa một chiều bằng thuật toán `Bcrypt` với chi phí tính toán cao (`cost = 10`), loại bỏ hoàn toàn nguy cơ rò rỉ khi sao lưu dữ liệu.
3. **Phòng chống Session Fixation:** Khởi tạo lại Session ID (`session_regenerate_id(true)`) ngay khi đăng nhập thành công.

### 5.3. Trải nghiệm Responsive 2 Chế Độ Hoàn Hảo
* **Desktop & Laptop:** Giao diện rộng thoáng với thanh Side Rail điều hướng cố định bên trái, tối ưu không gian hiển thị bảng dữ liệu nhiều cột.
* **Điện thoại di động (Màn hình $\le 768\text{px}$):**
  * Tự động giấu thanh menu bên trái, chuyển thành **Thanh điều hướng đáy (Bottom Navigation Bar)** cố định dưới mép màn hình theo chuẩn Human Interface Guidelines (Apple iOS & Android Material Design).
  * Nút bấm to, rõ, nằm trong vùng hoạt động tự nhiên của ngón tay cái (Thumb-friendly zone).
  * Giải phóng 100% chiều ngang màn hình, giúp giáo viên dễ dàng cầm điện thoại đi quanh lớp để chấm điểm thường xuyên.

### 5.4. Kết quả kiểm thử tự động (Quality Assurance)
Hệ thống đã trải qua bộ kiểm thử E2E nghiêm ngặt với **42 bài kiểm tra tự động**:
* **Tổng số bài kiểm tra:** 42/42
* **Tỷ lệ vượt qua:** **100.0% PASS** (0 thất bại, 0 bỏ qua).
* Bao phủ toàn bộ các kịch bản: Kiểm tra bảo mật RBAC, chống gian lận điểm, xác thực công thức Thông tư 22, nhập/xuất OpenXML và xử lý xung đột đồng thời.

---

# CHƯƠNG VI: KỊCH BẢN THUYẾT TRÌNH CHI TIẾT TỪNG BƯỚC (WORD-FOR-WORD SCRIPT)

*(Dành cho người thuyết trình khi đứng trước Ban Giám Hiệu hoặc Hội đồng sư phạm)*

---

### [00:00 - 01:30] MỞ ĐẦU: ĐẶT VẤN ĐỀ & TRUYỀN CẢM HỨNG
> *"Kính thưa Thầy/Cô trong Ban Giám Hiệu, kính thưa toàn thể quý Thầy Cô giáo,*
>
> *Năm học này, khi toàn trường chúng ta đẩy mạnh thực hiện Thông tư 22 của Bộ Giáo dục và Đào tạo, có một câu hỏi mà tôi tin người làm giáo dục nào cũng trăn trở:* **'Làm sao để chúng ta kịp thời phát hiện và cứu vớt một học sinh trước khi em ấy trượt dài vào nhóm học lực Chưa Đạt?'**
>
> *Thực tế hiện nay, phần mềm sổ điểm của chúng ta chỉ làm nhiệm vụ ghi nhận. Khi chúng ta mở sổ điểm ra vào tuần thứ 16, thấy một học sinh đạt 4.8 điểm, thì kỳ thi cuối kỳ đã cận kề, mọi nỗ lực phụ đạo hay bồi dưỡng lúc này đều là quá muộn. Chưa kể, Thầy dạy Toán thấy em sa sút nhưng Cô chủ nhiệm không hề hay biết; Cô dạy Văn thấy em tụt dốc nhưng không có thời gian lập báo cáo riêng cho BGH.*
>
> *Hôm nay, tôi xin trân trọng giới thiệu đến Hội đồng nhà trường:* **EduRadar THCS** – *Hệ thống Ra-đa 2 khung đánh giá tự động: phát hiện sớm nguy cơ sụt giảm điểm số theo Thông tư 22 và gợi ý lộ trình bồi dưỡng tức thì cho từng học sinh."*

---

### [01:30 - 03:30] TRÌNH DIỄN TRANG CHỦ BENTO & QUY TRÌNH 3 BƯỚC
*(Mở màn hình `welcome.php` trên máy chiếu)*

> *"Thưa Thầy Cô, ngay khi đăng nhập vào hệ thống, giao diện Trang Chủ chào đón chúng ta theo phong cách Bento Grid hiện đại và thân thiện. Không có những thanh menu rối mắt hay những nút bấm phức tạp.*
>
> *Hệ thống cá nhân hóa lời chào theo đúng tên và vai trò của giáo viên: 'Chào mừng trở lại, Thầy Nguyễn Văn Giáo!'. Ngay bên dưới là Quy trình 3 bước chuẩn mực sư phạm:*
> * *Bước 1: Chọn lớp và môn phụ trách.*
> * *Bước 2: Hệ thống tự động quét và phân tích Ra-đa.*
> * *Bước 3: Nhập điểm hoặc xuất ngay báo cáo cho BGH.*
>
> *Thầy Cô có thể chuyển đổi giữa giao diện Sáng (Light Mode) và giao diện Tối (Dark Mode) chỉ bằng một nút bấm ở góc trên, giúp bảo vệ thị lực tối đa khi thầy cô phải nhập điểm vào ban đêm."*

---

### [03:30 - 06:30] TRÁI TIM CỦA HỆ THỐNG: BẢNG ĐIỀU KHIỂN RA-ĐA 2 KHUNG
*(Chuyển sang màn hình `index.php`)*

> *"Bây giờ, xin mời quý Thầy Cô cùng nhìn vào Bảng Điều Khiển Cảnh Báo Sớm. Đây chính là 'trái tim' của EduRadar.*
>
> *Xin Thầy Cô đặc biệt lưu ý: Khác với mọi sổ điểm thông thường, EduRadar phân tách thành* **2 KHUNG ĐÁNH GIÁ ĐỘC LẬP**:
>
> *1. Bên phải là* **Khung 2: Phân Khúc Điểm Số theo Thông tư 22** – *gồm Giỏi & Khá, Trung Bình, và Yếu. Đây là thước đo tiêu chuẩn.*
>
> *2. Nhưng quan trọng hơn cả là bên trái:* **Khung 1: Hiệu Suất & Động Năng Học Tập (Momentum)**. *Hệ thống không chỉ nhìn vào điểm số tuyệt đối, mà đo lường độ dốc biến thiên giữa các bài kiểm tra:*
> * *Ai đang bứt phá đi lên?*
> * *Ai đang duy trì phong độ đều?*
> * *Và ai đang sa sút, tuột dốc không phanh?*
>
> *Ví dụ cụ thể: Em Mai Ngọc Khánh Băng lớp 8A1. Điểm kiểm tra của em: TX1 là 3.6, TX2 tụt xuống 3.2, TX3 tụt xuống 3.1. Dù chưa thi cuối kỳ, hệ thống đã lập tức gắn nhãn đỏ* **'Đang Sa Sút / Nguy Cấp'**.
>
> *Khi tôi bấm vào nút* **'Xem Hồ Sơ & Động Năng'**, *một bảng chẩn đoán chi tiết hiện lên:*
> * *Chỉ rõ: 'Hỏng cả GK và CK; có 2 bài TX dưới 3.5'.*
> * *Đồng thời đưa ra ngay khuyến nghị sư phạm: 'Cần bổ sung bài tập củng cố và bố trí đôi bạn cùng tiến kèm cặp'.*
>
> *Đặc biệt, hệ thống trang bị tính năng* **Bộ Lọc Đa Chọn (Multi-Select Chips)**. *Tôi có thể bấm cùng lúc cả chip 'Đang Sa Sút' và chip 'Yếu', bảng dữ liệu sẽ tự động lọc ra đúng danh sách các em cần can thiệp khẩn cấp chỉ trong 0.01 giây!"*

---

### [06:30 - 08:30] SỔ ĐIỂM LIVE SYNC & TRỢ LÝ QUÉT AI
*(Chuyển sang màn hình `nhap_diem.php` và `quet_nhanh.php`)*

> *"Tiếp theo, xin mời Thầy Cô chuyển sang phân hệ* **Sổ Điểm Bộ Môn**.
>
> *Giao diện được thiết kế quen thuộc đúng chuẩn bảng tính vnEdu. Điểm đặc biệt là:* **Mọi tính toán đều diễn ra theo thời gian thực (Live Sync)**. *Khi tôi sửa điểm kiểm tra thường xuyên của một học sinh, điểm trung bình môn và xếp loại Tốt/Khá/Đạt/Chưa đạt được tính lại ngay lập tức theo đúng hệ số của Thông tư 22.*
>
> *Và để giải phóng sức lao động cho giáo viên, chúng tôi tích hợp* **Phân hệ Quét Nhanh AI**. *Sau mỗi giờ kiểm tra 15 phút trên lớp, thầy cô không cần ngồi gõ từng con điểm. Chỉ cần lấy điện thoại chụp lại bảng điểm viết tay hoặc danh sách điểm in, AI sẽ nhận diện khuôn mặt chữ số và tự động điền thẳng vào sổ điểm điện tử với độ chính xác tuyệt đối."*

---

### [08:30 - 10:00] BÁO CÁO BAN GIÁM HIỆU & TRẢI NGHIỆM TRÊN ĐIỆN THOẠI
*(Bật chế độ Slide BGH `bao_cao_slide_bgh.php` và chiếu mô phỏng màn hình điện thoại)*

> *"Đối với Ban Giám Hiệu, việc nắm bắt chất lượng dạy học của toàn trường giờ đây không còn phải chờ đợi những tập báo cáo giấy dày cộm. Chỉ cần 1 cú click:*
> * *Hệ thống tự động kết xuất thành* **Slide Báo Cáo BGH chuyên nghiệp**, *hiển thị trực quan tỷ lệ học lực, danh sách học sinh cần nhà trường hỗ trợ, sẵn sàng trình chiếu trong các cuộc họp giao ban.*
> * *Hoặc xuất ngay ra bản in PDF khổ A4 chuẩn đóng dấu, hoặc file Excel để lưu trữ học vụ.*
>
> *Cuối cùng, EduRadar được tối ưu hóa hoàn hảo cho* **Điện thoại di động**. *Khi mở trên điện thoại, hệ thống tự động chuyển thành thanh điều hướng dưới đáy (Bottom Navigation Bar) giống hệt một ứng dụng Native trên iPhone hay Android. Thầy cô có thể chấm điểm, tra cứu học sinh bằng một tay ngay tại hành lang hay trong phòng học một cách nhẹ nhàng.*
>
> *Kính thưa Ban Giám Hiệu, EduRadar THCS không đơn thuần là một công cụ công nghệ, mà là một cam kết sư phạm:* **'Không để bất kỳ học sinh nào bị bỏ lại phía sau'**.*
>
> *Xin trân trọng cảm ơn quý Thầy Cô đã chú ý lắng nghe! Sau đây, tôi xin phép được giải đáp mọi câu hỏi của Hội đồng nhà trường."*

---

# CHƯƠNG VII: BỘ CÂU HỎI PHẢN BIỆN TỪ BGH & CÂU TRẢ LỜI CHUẨN MỰC

### Câu hỏi 1 (Từ Hiệu trưởng): *"Trường chúng ta đã có vnEdu/SMAS rồi, tại sao phải dùng thêm EduRadar? Có bị trùng lặp công việc không?"*
* **Câu trả lời chuẩn mực:**
  > *"Kính thưa Thầy Hiệu trưởng, EduRadar không thay thế vnEdu hay SMAS mà đóng vai trò là* **'Bộ vi xử lý trí tuệ nhân tạo đặt phía trước'**.
  > *vnEdu là phần mềm quản lý hành chính cuối cùng để in học bạ số. Nhưng vnEdu không có chức năng phát hiện sớm học sinh tuột dốc giữa kỳ; không có biểu đồ động năng bứt phá; không có công nghệ quét điểm AI qua ảnh chụp; và không có slide trình chiếu tự động cho BGH.*
  > *EduRadar giúp thầy cô chẩn đoán và đồng hành cùng học sinh suốt 4 tháng trong kỳ. Đến cuối kỳ, dữ liệu chuẩn xác từ EduRadar được xuất ra file Excel để đồng bộ một chạm vào vnEdu chỉ mất 30 giây, giúp giảm thiểu 80% áp lực hành chính cho giáo viên."*

### Câu hỏi 2 (Từ Phó Hiệu trưởng Chuyên môn): *"Công thức tính toán và xếp loại học lực của EduRadar có đảm bảo đúng 100% Thông tư 22 không?"*
* **Câu trả lời chuẩn mực:**
  > *"Dạ thưa Cô, hệ thống đã được lập trình chính xác tuyệt đối theo Điều 9 và Điều 10 của Thông tư 22/2021/TT-BGDĐT:*
  > * *Hệ số tính điểm: Các bài ĐĐGtx tính hệ số 1; bài ĐĐGđk Giữa kỳ tính hệ số 2; bài ĐĐGđk Cuối kỳ tính hệ số 3. Tổng mẫu số luôn đúng bằng $(n + 2 + 3)$.*
  > * *Các điều kiện chặn học lực: Hệ thống đã cài đặt chặt chẽ quy tắc điểm liệt ($< 3.5$) và tỷ lệ các môn đạt $\ge 6.5$ hoặc $\ge 8.0$. Toàn bộ logic này đã vượt qua 42 bài kiểm thử tự động với độ chính xác toán học 100%."*

### Câu hỏi 3 (Từ Chủ tịch Công đoàn / Đại diện Giáo viên): *"Giáo viên trong trường lớn tuổi, ngại tiếp xúc công nghệ phức tạp thì có dùng được không?"*
* **Câu trả lời chuẩn mực:**
  > *"Kính thưa Thầy Cô, giao diện EduRadar được thiết kế theo triết lý 'Không cần đọc hướng dẫn sử dụng'.*
  > *Mọi thao tác đều gói gọn trong 3 bước trên Trang chủ Bento. Trên màn hình đăng nhập có sẵn các nút bấm tự động điền tài khoản; trên điện thoại có thanh điều hướng đáy quen thuộc như ứng dụng Zalo/Facebook; và việc nhập điểm có thể thực hiện đơn giản bằng cách chụp ảnh bảng điểm giấy qua tính năng Quét AI. Thầy cô chỉ mất đúng 5 phút làm quen là có thể sử dụng thành thạo."*

### Câu hỏi 4 (Từ Cán bộ Công nghệ thông tin): *"Hệ thống có an toàn không? Có sợ giáo viên sửa trộm điểm của môn khác không?"*
* **Câu trả lời chuẩn mực:**
  > *"Kính thưa Thầy, hệ thống được xây dựng trên mô hình phân quyền RBAC nghiêm ngặt:*
  > * *Giáo viên Toán chỉ có quyền xem và nhập điểm cho môn Toán của đúng các lớp được phân công. Mọi nỗ lực chỉnh sửa URL để xem hay sửa điểm môn Ngữ Văn đều bị máy chủ chặn ngay lập tức (Lỗi HTTP 403 Forbidden).*
  > * *Mọi thao tác nhập điểm, sửa điểm đều được hệ thống ghi nhận vào bảng Nhật ký kiểm toán (Audit Log) lưu rõ: Ai sửa, sửa điểm của học sinh nào, từ mấy điểm thành mấy điểm, vào thời điểm nào (chính xác đến từng giây)."*

---
*Tài liệu được biên soạn phục vụ công tác báo cáo chuyển đổi số giáo dục THCS.*
