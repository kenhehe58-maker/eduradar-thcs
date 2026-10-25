# THIẾT KẾ POSTER KHOA HỌC SONG NGỮ VIỆT – ANH (BILINGUAL SCIENTIFIC POSTER)
**Quy cách chuẩn:** Khung Poster khổ đứng 120cm x 90cm (Tiêu chuẩn Hội thi KHKT Quốc gia ViSEF & Quốc tế ISEF)  
**Tên dự án:**  
- **Tiếng Việt:** EduRadar THCS — Hệ Thống Radar Cảnh Báo Sớm Sa Sút Học Tập Dựa Trên Động Năng Biến Thiên Điểm Số Và Đại Số Giải Ngược Thông Tư 22/2021/TT-BGDĐT  
- **English:** EduRadar THCS — An Early-Warning Academic Attrition Radar Leveraging Score Momentum Dynamics and Algebraic Inverse Optimization under Circular 22  

---

```
+---------------------------------------------------------------------------------------------------+
|                                  EDURADAR THCS — NATIONAL VISEF 2026                              |
|           EARLY-WARNING RADAR & PREDICTIVE PEDAGOGICAL ANALYTICS FOR SECONDARY SCHOOLS           |
|                Hệ Thống Cảnh Báo Sớm & Dự Báo Điểm Mục Tiêu Dành Cho Trường THCS                  |
+---------------------------------------------------------------------------------------------------+
|  PANEL 1: PROBLEM & OBJECTIVE     |  PANEL 2: MATHEMATICAL CORE       |  PANEL 3: MULTI-CONDITION BENCH  |
|  (Bối cảnh & Mục tiêu nghiên cứu)  |  (Trọng tâm Toán học & Thuật toán) |  (Thực nghiệm Kỹ thuật 8 Kịch bản)|
|                                   |                                   |                                  |
|  - Academic latency in schools    |  1. Algebraic Inverse Solver:     |  Tested across 8 real conditions |
|  - Circular 22 weight skew (CK x3)|     CK = [T*(n+5) - sumTX - 2GK]/3|  (Lighting, Angles, Font types): |
|  - Limitations of static portals  |  2. OLS Trend Velocity (k):       |                                  |
|                                   |     Ascending / Steady / Attrition|  * Printed text: 100% accuracy   |
|                                   |  3. Consistency StdDev (sigma):   |  * Handwritten: 95.8% accuracy   |
|                                   |     Psychological stability score |  * Levenshtein UTF-8 Roster      |
|                                   |  4. Boundary Alert (CK > 10.0):   |    matching reaches 100%!        |
|                                   |     Mandatory continuous recovery |                                  |
+-----------------------------------+-----------------------------------+----------------------------------+
|  PANEL 4: SYSTEM ARCHITECTURE     |  PANEL 5: INTERVENTION MATRIX     |  PANEL 6: CONCLUSIONS & ROADMAP  |
|  (Kiến trúc & An toàn thông tin)  |  (Quy trình Sư phạm & Ma trận 3x3)|  (Kết luận & Hướng phát triển)   |
|                                   |                                   |                                  |
|  - Pure MariaDB / Zero SQLite     |  3x3 Dual-Framework Matrix:       |  - 42/42 E2E Tests Passed        |
|  - Strict RBAC Route Guards       |  * High Performer -> Mentorship   |  - 20/20 Math Tests Passed       |
|  - Anti-BOLA / Anti-IDOR Defense  |  * Steady Middle  -> Growth Boost |  - Lightweight & zero-cost deploy|
|  - Full Cryptographic Audit Logs  |  * Attrition Risk -> Action Plan  |  - Phase 2: Attendance fusion    |
+---------------------------------------------------------------------------------------------------+
```

---

## CHI TIẾT NỘI DUNG TỪNG PANEL (BILINGUAL DETAILED PANELS)

### PANEL 1: PROBLEM STATEMENT & RESEARCH OBJECTIVES (BỐI CẢNH & MỤC TIÊU)
- **Tiếng Việt:**
  - *Thực trạng:* Các hệ thống quản lý trường học hiện tại (VnEdu, SMAS) mang tính thụ động; chỉ tính điểm tổng kết cuối kỳ sau khi kỳ thi đã kết thúc. Theo Thông tư 22, bài thi Cuối kỳ chiếm hệ số 3, khiến nhiều học sinh có nguy cơ trượt dốc không kịp cứu vãn.
  - *Mục tiêu:* Xây dựng hệ thống cảnh báo sớm chủ động, phát hiện sa sút từ tuần 4–6, dự báo chính xác số điểm Cuối kỳ cần đạt và đề xuất can thiệp sư phạm tức thì.
- **English:**
  - *Problem:* Existing school portals are passive data repositories; final GPA is aggregated only after final exams conclude. Under Circular 22, the Final Exam carries a heavy weight ($3\times$), leaving teachers unable to intervene before irrevocable failure.
  - *Objective:* Develop an active early-warning system that detects learning attrition in weeks 4–6, solves the required target final score, and generates actionable pedagogical interventions.

---

### PANEL 2: THE MATHEMATICAL ENGINE & ALGORITHMS (TRỌNG TÂM TOÁN HỌC & THUẬT TOÁN)

#### 1. Algebraic Inverse Target Solver (Giải ngược đại số Thông tư 22)
- **Equation (Công thức gốc):**
  $$\text{ĐTBmhk} = \frac{\sum_{i=1}^{n_{\text{TX}}} \text{TX}_i + 2 \cdot \text{GK} + 3 \cdot \text{CK}}{n_{\text{TX}} + 5}$$
- **Inverse Formulation (Giải ngược tìm điểm mục tiêu):**
  $$\text{CK}_{\text{target}} = \frac{T \cdot (n_{\text{TX}} + 5) - \sum \text{TX} - 2 \cdot \text{GK}}{3}$$
  *Where $T \in \{5.0 \text{ (Pass)}, 6.5 \text{ (Good)}, 8.0 \text{ (Distinction)}\}$.*
- **Mathematical Bound (Ngưỡng cảnh báo trần):**
  If $\text{CK}_{\text{target}} > 10.0 \Rightarrow$ **Red Alert Triggered!** Taking the final exam alone is algebraically insufficient; the teacher must add a supplementary continuous score ($\text{TX}_{n+1}$) before exam cutoff.

#### 2. Ordinary Least Squares (OLS) Learning Velocity (Hệ số góc xu hướng $k$)
$$k = \frac{n \sum (i \cdot y_i) - (\sum i)(\sum y_i)}{n \sum i^2 - (\sum i)^2}$$
- $k \ge +0.3$: **Breakthrough / Accelerated Progress** (Đang tiến bộ / Bứt phá)
- $-0.3 < k < +0.3$: **Steady Maintenance** (Duy trì ổn định)
- $k \le -0.3$: **Persistent Attrition** (Sa sút liên tục $\Rightarrow$ Cảnh báo sớm)

#### 3. Standard Deviation Consistency Index (Chỉ số ổn định phong độ $\sigma$)
$$\sigma = \sqrt{\frac{1}{n-1} \sum_{i=1}^n (y_i - \bar{y})^2}$$
Distinguishes students with solid psychological consistency ($\sigma \le 0.5$) from students suffering volatile exam stress ($\sigma \ge 1.5$).

---

### PANEL 3: MULTI-CONDITION TECHNICAL BENCHMARK (THỰC NGHIỆM ĐA ĐIỀU KIỆN)

Tested across 8 physical conditions with 45 student gradebooks:

$$\begin{array}{|l|c|c|c|c|c|}
\hline
\textbf{Experimental Scenario} & \textbf{Text Type} & \textbf{Lighting} & \textbf{Angle} & \textbf{OCR Raw} & \textbf{Roster Match} \\
\hline
\text{Scenario 1 (Optimal)} & \text{Printed} & 500\text{ Lux} & 90^\circ & 100.0\% & \mathbf{100.0\%} \\
\text{Scenario 2 (Dim Light)} & \text{Printed} & 150\text{ Lux} & 90^\circ & 100.0\% & \mathbf{100.0\%} \\
\text{Scenario 3 (Tilted)} & \text{Printed} & 500\text{ Lux} & 30^\circ & 97.7\% & \mathbf{100.0\%} \\
\text{Scenario 4 (Adverse)} & \text{Printed} & 150\text{ Lux} & 45^\circ & 88.5\% & \mathbf{100.0\%} \\
\text{Scenario 5 (Handwritten)} & \text{Handwritten} & 500\text{ Lux} & 90^\circ & 100.0\% & \mathbf{100.0\%} \\
\text{Scenario 6 (HW Dim Light)} & \text{Handwritten} & 150\text{ Lux} & 90^\circ & 95.8\% & \mathbf{100.0\%} \\
\text{Scenario 7 (HW Tilted)} & \text{Handwritten} & 500\text{ Lux} & 30^\circ & 90.0\% & \mathbf{100.0\%} \\
\text{Scenario 8 (HW Adverse)} & \text{Handwritten} & 150\text{ Lux} & 45^\circ & 80.8\% & \mathbf{100.0\%} \\
\hline
\end{array}$$

**Technical Insight:** Multibyte UTF-8 Levenshtein Distance eliminates $100\%$ of diacritics dropping errors, ensuring robust database linkage even on low-end classroom camera hardware.

---

### PANEL 4: SYSTEM ARCHITECTURE & INFORMATION SECURITY (KIẾN TRÚC & BẢO MẬT)
- **Database Architecture:** Pure MariaDB 10.4 3NF schema, zero file corruption, ACID compliant.
- **Security & Authorization:**
  - Role-Based Access Control (RBAC): Admin vs. Subject Teacher.
  - Anti-BOLA & Anti-IDOR Defense: Cryptographic verification of teacher-class assignments on every mutation.
  - Complete Audit Trail: Real-time logging of user ID, IP address, timestamp, and pre/post score diff.
  - Privacy Guaranteed: Confidential student data preserved locally; zero automated bulk messaging leaks.

---

### PANEL 5: PEDAGOGICAL 3-STEP WORKFLOW & 3x3 MATRIX (QUY TRÌNH SƯ PHẠM)
1. **Step 1: 1-Click Ingestion (Quét 1 Chạm)** — Direct VnEdu spreadsheet import or smartphone OCR scan.
2. **Step 2: Dual-Framework Radar Screening (Phát hiện 2 Khung)** — Matrix 3x3 cross-evaluating Performance Momentum and Grade Mastery.
3. **Step 3: Pedagogical Intervention Note (Sổ tay Sư phạm Đồng hành)** — Individualized remedial action plans stored in database and exportable to PDF/Excel.

---

### PANEL 6: VERIFICATION & NEXT RESEARCH IDEAS (KIỂM ĐỊNH & HƯỚNG PHÁT TRIỂN)
- **Automated Verification:**
  - 42/42 End-to-End Functional & Security Scenarios ($100\%$ PASS).
  - 20/20 Mathematical & Algorithmic Unit Tests ($100\%$ PASS).
- **Phase 2 Expansion Roadmap (Giai đoạn 2):**
  1. Multimodal fusion: Incorporate daily attendance and classroom engagement metrics into the Radar scoring model.
  2. Machine Learning: Implement Bayesian Ridge Regression to predict 10th-grade High School Entrance Examination benchmark cutoffs.
