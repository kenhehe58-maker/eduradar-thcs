# BẢN KÊ KHAI MINH BẠCH SỬ DỤNG CÔNG NGHỆ SỐ VÀ TRÍ TUỆ NHÂN TẠO (AI)
**Dự án:** EduRadar THCS — Hệ Thống Radar Cảnh Báo Sớm Nguy Cơ Sa Sút Học Tập THCS  
**Lĩnh vực:** Khoa học máy tính và Hệ thống thông tin  
**Căn cứ pháp lý:** Điều 15 — Liêm chính khoa học và quy định về sử dụng Trí tuệ nhân tạo (Thông tư số 24/2025/TT-BGDĐT sửa đổi, bổ sung Thông tư số 06/2024/TT-BGDĐT của Bộ Giáo dục và Đào tạo)

---

## 1. CAM KẾT LIÊM CHÍNH KHOA HỌC CỦA HỌC SINH TÁC GIẢ
Tác giả xin cam đoan trước Hội đồng Giám khảo Cuộc thi Khoa học Kỹ thuật các cấp:
1. Ý tưởng nghiên cứu, mục tiêu giải pháp, mô hình toán học giải ngược Thông tư 22, thiết kế kiến trúc 2 khung đánh giá và toàn bộ mã nguồn nghiệp vụ của dự án EduRadar THCS xuất phát từ quá trình quan sát thực tế tại trường học và do chính tác giả trực tiếp thực hiện.
2. Dự án tuân thủ nghiêm ngặt nguyên tắc trung thực khoa học, không sao chép công trình của người khác, không ngụy tạo số liệu khảo sát, và không sử dụng trí tuệ nhân tạo để thay thế năng lực tư duy, sáng tạo độc lập của học sinh.

---

## 2. DANH MỤC CÔNG NGHỆ SỐ VÀ CÔNG CỤ AI ĐÃ SỬ DỤNG

| STT | Tên công cụ / Nền tảng | Phiên bản / Môi trường | Mục đích sử dụng cụ thể | Phạm vi và Mức độ can thiệp | Kiểm soát của học sinh |
|:---:|:---|:---|:---|:---|:---|
| 1 | **Google Tesseract OCR Engine** | v5.3 / JavaScript Wrapper | Nhận diện ký tự quang học từ ảnh chụp sổ điểm giấy để chuyển đổi thành văn bản thô. | Sử dụng như một thư viện ngoại vi (External Library). AI chỉ trích xuất text thô; toàn bộ khâu tiền xử lý ảnh và khớp dữ liệu học sinh do học sinh tự lập trình. | Học sinh viết thuật toán lọc nhiễu và thuật toán Levenshtein để sửa lỗi OCR. |
| 2 | **GitHub Copilot / AI Assistant** | Claude / Gemini 2026 | Hỗ trợ tra cứu cú pháp ngôn ngữ lập trình, gợi ý tối ưu hàm toán học và sinh các ca kiểm thử biên (Edge-case Unit Tests). | Hỗ trợ lập trình viên (Pair Programming). Học sinh là người trực tiếp ra quyết định kiến trúc, duyệt mã nguồn và sửa đổi logic nghiệp vụ. | 100% dòng lệnh được học sinh kiểm tra, giải thích được chi tiết biến và luồng thực thi. |
| 3 | **KaTeX / MathJax Engine** | v0.16 | Hiển thị các công thức toán học và biểu thức đại số trên giao diện web và tài liệu báo cáo. | Thư viện kết xuất đồ họa công thức toán học mã nguồn mở. | Học sinh trực tiếp viết mã LaTeX cho công thức Thông tư 22 và hồi quy OLS. |
| 4 | **Python Matplotlib & Benchmark Scripts** | Python 3.11 / PHP CLI | Tự động hóa đo đạc độ trễ và tỷ lệ lỗi ký tự (CER) trong 8 kịch bản thực nghiệm đa điều kiện. | Phục vụ đo kiểm khách quan hiệu năng phần mềm. | Học sinh tự thiết kế kịch bản thử nghiệm và tổng hợp ma trận số liệu. |

---

## 3. PHÂN ĐỊNH RÕ RÀNG GIỮA ĐÓNG GÓP CỦA HỌC SINH VÀ CÔNG NGHỆ HỖ TRỢ

### A. Những phần KHÔNG sử dụng AI (Đóng góp sáng tạo 100% của học sinh):
1. **Thiết kế mô hình toán học:** Xây dựng công thức giải ngược đại số Thông tư 22 để tính chính xác điểm thi Cuối kỳ mục tiêu ($\text{CK}_{\text{target}}$) và xác định điều kiện phát hiện ngưỡng vượt trần ($\text{CK} > 10.0$).
2. **Thuật toán Hậu xử lý Levenshtein tiếng Việt UTF-8:** Học sinh tự thiết kế và cài đặt hàm `mb_levenshtein()` sử dụng quy hoạch động trên mảng ký tự đa byte, khắc phục hoàn toàn nhược điểm tính byte của hàm có sẵn trong PHP.
3. **Cơ chế phân quyền RBAC & Phòng vệ an ninh:** Toàn bộ kiến trúc chống leo quyền ngang BOLA, chống giả mạo tham số IDOR và hệ thống ghi vết kiểm toán (Audit Trail) trong MariaDB.
4. **Quy trình Sư phạm 3 bước & Ma trận can thiệp 3x3:** Tự tác giả khảo sát ý kiến giáo viên và xây dựng sổ tay nhận xét cá nhân hóa gắn liền với hồ sơ từng học sinh.

### B. Những phần có sự hỗ trợ của AI:
- Tham khảo gợi ý thuật toán OLS để chuẩn hóa công thức tính hệ số góc $k$ và độ lệch chuẩn $\sigma$ trên ngôn ngữ PHP.
- Kiểm tra chính tả và ngữ pháp tiếng Anh trong bản tóm tắt Poster song ngữ.

---

## 4. XÁC NHẬN CỦA GIÁO VIÊN HƯỚNG DẪN
Tôi xác nhận học sinh đã kê khai hoàn toàn trung thực, minh bạch về việc sử dụng công nghệ số và AI trong dự án. Quá trình làm việc, viết mã nguồn và thực nghiệm diễn ra dưới sự giám sát trực tiếp của tôi, đảm bảo đúng quy định tại Điều 15 Thông tư 24/2025/TT-BGDĐT.

*Ngày 25 tháng 10 năm 2026*  
**Học sinh tác giả** *(Ký và ghi rõ họ tên)*  
**Giáo viên hướng dẫn** *(Ký và ghi rõ họ tên)*  
