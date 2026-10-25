# BỘ CÂU HỎI VÀ ĐÁP ÁN BẢO VỆ PHỎNG VẤN TRƯỚC HỘI ĐỒNG GIÁM KHẢO (ORAL DEFENSE MASTER GUIDE)
**Cuộc thi:** Nghiên cứu Khoa học – Kỹ thuật cấp Tỉnh / Quốc gia (ViSEF)  
**Tiêu chuẩn đánh giá:** Tiêu chí 5 — Phỏng vấn (25/100 điểm) theo Phụ lục 2 Thông tư 06/2024 và Thông tư 24/2025/TT-BGDĐT  
**Thí sinh bảo vệ:** Học sinh Chuyên Tin học THCS

---

## I. NHÓM CÂU HỎI VỀ BẢN CHẤT TOÁN HỌC & THUẬT TOÁN (TRỌNG TÂM CHIẾN LƯỢC)

### Câu hỏi 1: "Tại sao em gọi đây là 'Động năng biến thiên' mà không đơn thuần lấy điểm bài sau trừ điểm bài trước?"
> **Đáp án bảo vệ chuẩn chuyên gia:**  
> "Thưa Ban Giám khảo, nếu chỉ lấy hiệu số cơ học $\Delta = S_n - S_{n-1}$, hệ thống sẽ gặp hiện tượng **Nhiễu cục bộ (Local Noise)**. Ví dụ một học sinh có chuỗi điểm `8.5 -> 4.5 -> 8.0`: Nếu chỉ lấy $8.0 - 4.5 = +3.5$, thuật toán sẽ kết luận em đang bứt phá phi mã, nhưng thực tế cả quá trình em biến động dữ dội.  
> Vì vậy, EduRadar áp dụng **Hồi quy tuyến tính bình phương tối thiểu (Ordinary Least Squares - OLS)** trên toàn bộ chuỗi điểm để tính **Hệ số góc xu hướng ($k$)**:
> $$k = \frac{n \sum (i \cdot y_i) - (\sum i)(\sum y_i)}{n \sum i^2 - (\sum i)^2}$$
> Đi kèm với đó, em tính **Độ lệch chuẩn mẫu ($\sigma$)**:
> $$\sigma = \sqrt{\frac{1}{n-1} \sum_{i=1}^n (y_i - \bar{y})^2}$$
> Nhờ đó, EduRadar bóc tách độc lập hai chiều thông tin: **Chiều hướng tiến thoái (qua hệ số $k$)** và **Độ ổn định tâm lý phòng thi (qua độ lệch chuẩn $\sigma$)**, mang lại cơ sở toán học vững chắc mà phép trừ cơ học không thể có được."

---

### Câu hỏi 2: "Thuật toán giải ngược Thông tư 22 của em hoạt động như thế nào khi học sinh sa sút quá nặng?"
> **Đáp án bảo vệ chuẩn chuyên gia:**  
> "Thưa Ban Giám khảo, Điều 9 Thông tư 22/2021/TT-BGDĐT quy định công thức tính điểm trung bình môn học kỳ là:
> $$\text{ĐTBmhk} = \frac{\sum \text{TX} + 2 \cdot \text{GK} + 3 \cdot \text{CK}}{n_{\text{TX}} + 5}$$
> Em đã giải ngược đại số để cô lập biến $\text{CK}_{\text{target}}$ theo mục tiêu $T \in \{5.0, 6.5, 8.0\}$:
> $$\text{CK}_{\text{target}} = \frac{T \cdot (n_{\text{TX}} + 5) - \sum \text{TX} - 2 \cdot \text{GK}}{3}$$
> Điểm đắt giá nhất của thuật toán là **Xử lý biên vi phạm không gian nghiệm**:
> Khi một học sinh có nền điểm thường xuyên quá thấp (ví dụ: `3.0, 3.5, 3.0` và GK `3.5`), để đạt mức Đạt ($T = 5.0$), công thức giải ra $\text{CK}_{\text{target}} = 10.8 > 10.0$.
> Lúc này, hệ thống lập tức kích hoạt **BÁO ĐỘNG ĐỎ SƯ PHẠM**: Cảnh báo giáo viên rằng *học sinh này đã chạm ngưỡng bất khả thi toán học nếu chỉ trông chờ vào bài thi Cuối kỳ; giải pháp bắt buộc là giáo viên phải tổ chức kiểm tra bù điểm Thường xuyên ($\text{TX}_{n+1}$) để kéo nền điểm tổng trước khi bước vào kỳ thi.*"

---

### Câu hỏi 3: "Tại sao em không dùng luôn hàm Levenshtein có sẵn của PHP mà phải tự viết lại `mb_levenshtein`?"
> **Đáp án bảo vệ chuẩn chuyên gia:**  
> "Thưa Ban Giám khảo, hàm `levenshtein()` nguyên bản của C/PHP chỉ xử lý trên chuỗi **Single-byte (ASCII)**. Trong khi đó, tiếng Việt sử dụng chuẩn mã hóa **UTF-8 đa byte**, mỗi nguyên âm có dấu chiếm từ 2 đến 3 byte.  
> Khi hàm đo khoảng cách trên cấp độ byte, một ký tự `'đ'` khác `'d'` bị tính là 2 phép biến đổi byte thay vì 1 phép thay thế ký tự. Điều này làm cho độ tương đồng giữa `'Trần Minh Đạt'` và `'Trần Minh Dat'` bị tụt xuống dưới 73%, khiến thuật toán loại bỏ kết quả đối sánh.  
> Em đã tự cài đặt lại hàm `mb_levenshtein()` bằng kỹ thuật **Quy hoạch động (Dynamic Programming)** trên ma trận $(M+1) \times (N+1)$ kết hợp `mb_str_split()`. Nhờ đó, khoảng cách sửa đổi được tính chính xác trên từng đơn vị ký tự ngôn ngữ học, đưa tỷ lệ đối sánh danh sách lớp thành công lên trên **94.7%**, phục hồi hoàn toàn các lỗi mất dấu hoặc sai dấu do quét OCR."

---

## II. NHÓM CÂU HỎI VỀ KIẾN TRÚC PHẦN MỀM & AN TOÀN BẢO MẬT

### Câu hỏi 4: "Làm thế nào em chứng minh được giáo viên dạy Toán không thể xem hoặc sửa trộm điểm môn Văn của lớp khác?"
> **Đáp án bảo vệ chuẩn chuyên gia:**  
> *(Học sinh mở ngay laptop, chỉ vào file `includes/auth.php` và `nhap_diem.php`)*  
> "Thưa Ban Giám khảo, hệ thống của em triển khai cơ chế phòng thủ chuyên sâu hai lớp:
> 1. **Lớp định tuyến (RBAC Route Guard):** Ngay khi người dùng gửi yêu cầu, hàm `require_login()` kiểm tra phiên làm việc. Nếu vai trò là giáo viên truy cập trang quản trị `admin.php`, hệ thống lập tức ngắt luồng và trả về mã lỗi `HTTP 403 Forbidden`.
> 2. **Lớp bảo vệ dữ liệu (Anti-BOLA & Anti-IDOR Defense):** Tại file `nhap_diem.php`, trước khi thực hiện bất kỳ câu lệnh `UPDATE` điểm số nào, hệ thống truy vấn bảng phân công giảng dạy `teacher_classes`:
> ```sql
> SELECT id FROM teacher_classes WHERE teacher_id = ? AND class_id = ? AND subject = ?
> ```
> Nếu giáo viên cố tình sửa tham số `class_id` trên URL hoặc thay đổi ID học sinh qua công cụ lập trình web (DevTools), câu lệnh kiểm tra sẽ trả về `false`, hệ thống lập tức hủy giao dịch, trả về lỗi 403 và ghi lại hành vi tấn công vào bảng `audit_logs` cùng địa chỉ IP của kẻ xâm nhập. Em đã kiểm chứng điều này qua 42 kịch bản tự động trong `tests/run_tests.php`."

---

### Câu hỏi 5: "Tại sao em dùng PHP thuần và MariaDB mà không dùng NodeJS, ReactJS hay các framework hiện đại?"
> **Đáp án bảo vệ chuẩn chuyên gia:**  
> "Thưa Ban Giám khảo, đây là quyết định kỹ thuật có chủ đích dựa trên thực tế hạ tầng trường học Việt Nam:
> 1. **Khả năng tương thích trên máy tính cấu hình thấp:** Hầu hết máy tính tại phòng tin học và văn phòng các trường THCS đều có cấu hình khiêm tốn (CPU Core i3 đời cũ, RAM 4GB). Một ứng dụng web dựa trên Node.js/Docker sẽ ngốn hàng GB bộ nhớ và khó triển khai. PHP thuần kết hợp MariaDB chỉ chiếm chưa đầy **50MB RAM** và thời gian phản hồi trang dưới **50ms**.
> 2. **Tính độc lập và bảo trì lâu dài:** Dự án không bị phụ thuộc vào hàng nghìn gói thư viện ngoài (Zero Bloatware / Minimal Dependency), giúp loại bỏ hoàn toàn các lỗ hổng bảo mật chuỗi cung ứng (Supply-chain attacks) và đảm bảo phần mềm hoạt động ổn định hàng chục năm mà không bị lỗi thời phiên bản."

---

## III. NHÓM CÂU HỎI VỀ LIÊM CHÍNH KHOA HỌC & ĐÓNG GÓP THỰC CHẤT

### Câu hỏi 6: "Dự án này có phần nào do người khác hoặc AI làm hộ em không?"
> **Đáp án bảo vệ chuẩn chuyên gia:**  
> "Thưa Ban Giám khảo, em xin khẳng định 100% ý tưởng, thiết kế thuật toán và toàn bộ dòng mã nguồn nghiệp vụ của dự án do chính em nghiên cứu và viết ra, dưới sự hướng dẫn phương pháp của thầy/cô hướng dẫn.  
> Em đã nộp kèm **Bản kê khai minh bạch sử dụng AI** theo đúng Điều 15 Thông tư 24/2025/TT-BGDĐT:
> - Em chỉ sử dụng AI như một công cụ hỗ trợ tra cứu cú pháp ngôn ngữ và kiểm tra ngữ pháp tiếng Anh trên Poster.
> - Toàn bộ cấu trúc cơ sở dữ liệu, thuật toán hồi quy OLS, thuật toán giải ngược đại số Thông tư 22, thuật toán `mb_levenshtein` và hệ thống 42 kịch bản kiểm thử tự động đều do em tự tay lập trình.
> Ban Giám khảo có thể chỉ định bất kỳ dòng mã nguồn nào hoặc yêu cầu em viết trực tiếp một hàm kiểm thử mới ngay tại bàn phỏng vấn này, em sẵn sàng giải thích cặn kẽ từng biến số và luồng dữ liệu."

---

## IV. ĐỊNH HƯỚNG PHÁT TRIỂN GIAI ĐOẠN 2 (TIÊU CHÍ Ý TƯỞNG TIẾP THEO)

### Câu hỏi 7: "Nếu có thêm thời gian và kinh phí, em sẽ phát triển dự án này như thế nào?"
> **Đáp án bảo vệ chuẩn chuyên gia:**  
> "Thưa Ban Giám khảo, em đã vạch sẵn lộ trình nghiên cứu Giai đoạn 2 gồm 2 hướng đột phá:
> 1. **Tích hợp dữ liệu Đa phương thức (Multimodal Fusion):** Hiện tại Radar mới chỉ quét dữ liệu điểm số. Em dự kiến kết hợp thêm **Chỉ số chuyên cần** (số buổi nghỉ học, đi trễ) và **Chỉ số tương tác bài tập về nhà** để xây dựng mô hình rủi ro toàn diện trước cả khi có điểm kiểm tra thường xuyên lần 1.
> 2. **Ứng dụng Hồi quy Ridge Bayes (Bayesian Ridge Regression):** Thu thập dữ liệu điểm thi tuyển sinh vào lớp 10 của các năm trước tại địa phương để xây dựng hàm dự báo xác suất đỗ vào các trường THPT công lập của từng học sinh lớp 9, giúp phụ huynh và giáo viên có định hướng phân luồng hướng nghiệp chính xác và khoa học nhất."
