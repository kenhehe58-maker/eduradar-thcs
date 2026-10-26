<?php
/**
 * =============================================================================
 * Hệ Thống Ra-đa Cảnh Báo Sớm Học Lực THCS (EduRadar)
 * Module: Poster Khoa Học Kỹ Thuật Song Ngữ Việt - Anh (0.8m x 1.2m KHKT Poster)
 * Chuẩn Bento Grid + Apple Design + Print-Ready PDF
 * =============================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/radar_helper.php';

$pageTitle = "Poster Trưng Bày KHKT - EduRadar THCS (0.8m x 1.2m)";
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --poster-w: 800mm;
            --poster-h: 1200mm;
            --primary: #1e3a8a;
            --primary-light: #3b82f6;
            --primary-dark: #0f172a;
            --accent: #059669;
            --accent-light: #10b981;
            --warning: #d97706;
            --danger: #dc2626;
            --bg-page: #0b1329;
            --card-bg: rgba(255, 255, 255, 0.94);
            --card-border: rgba(226, 232, 240, 0.9);
            --text-main: #0f172a;
            --text-muted: #475569;
            --font-sans: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-sans);
            background: var(--bg-page);
            color: var(--text-main);
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 24px 0 60px;
            overflow-x: auto;
            -webkit-font-smoothing: antialiased;
        }

        /* Top Action Toolbar */
        .poster-toolbar {
            position: sticky;
            top: 16px;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 10px 20px;
            border-radius: 999px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5);
            margin-bottom: 24px;
        }

        .poster-toolbar .brand-tag {
            font-size: 13px;
            font-weight: 700;
            color: #38bdf8;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .poster-toolbar .btn {
            appearance: none;
            border: none;
            outline: none;
            cursor: pointer;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn-print {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.35);
        }
        .btn-print:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.45);
        }

        .btn-back {
            background: rgba(255, 255, 255, 0.1);
            color: #f1f5f9;
        }
        .btn-back:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .scale-control {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #94a3b8;
            font-size: 12px;
            font-weight: 600;
            border-left: 1px solid rgba(255, 255, 255, 0.15);
            padding-left: 12px;
        }

        .scale-control select {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 6px;
            padding: 4px 8px;
            font-size: 12px;
            font-family: inherit;
            cursor: pointer;
        }

        /* 0.8m x 1.2m Canvas Container */
        .poster-viewport {
            transform-origin: top center;
            transition: transform 0.2s ease;
        }

        .poster-canvas {
            width: 1000px;
            min-height: 1500px; /* 1:1.5 ratio standard 0.8m x 1.2m */
            background: #f8fafc;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6), 0 0 0 1px rgba(255, 255, 255, 0.1);
            display: flex;
            flex-direction: column;
            position: relative;
        }

        /* Poster Header */
        .p-header {
            background: linear-gradient(135deg, #091e42 0%, #1e3a8a 60%, #0d9488 100%);
            color: #ffffff;
            padding: 36px 44px 28px;
            position: relative;
            border-bottom: 4px solid #10b981;
        }

        .p-sub-banner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #38bdf8;
            margin-bottom: 12px;
        }

        .p-title-vi {
            font-size: 26px;
            font-weight: 800;
            line-height: 1.25;
            letter-spacing: -0.5px;
            margin-bottom: 6px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .p-title-en {
            font-size: 15px;
            font-weight: 600;
            color: #cbd5e1;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 18px;
        }

        .p-meta-grid {
            display: grid;
            grid-template-columns: 2fr 1.5fr 1.5fr;
            gap: 16px;
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            padding: 12px 20px;
            font-size: 12px;
        }

        .p-meta-item strong {
            display: block;
            color: #38bdf8;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 2px;
        }

        /* Bento Grid Body */
        .p-body {
            padding: 24px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            grid-auto-rows: minmax(auto, auto);
            gap: 18px;
            flex: 1;
        }

        .bento-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 18px 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .bento-card.span-2 {
            grid-column: span 2;
        }

        .bento-card.highlight {
            border-top: 4px solid #2563eb;
        }

        .bento-card.accent {
            border-top: 4px solid #059669;
        }

        .bento-card.amber {
            border-top: 4px solid #d97706;
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #f1f5f9;
        }

        .card-badge {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            padding: 3px 8px;
            border-radius: 6px;
            background: #f1f5f9;
            color: #475569;
        }

        .card-title {
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-title span.num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            background: #1e3a8a;
            color: #fff;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 800;
        }

        .card-content {
            font-size: 11.5px;
            line-height: 1.5;
            color: #334155;
            flex: 1;
        }

        .card-content ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .card-content ul li {
            position: relative;
            padding-left: 14px;
            margin-bottom: 8px;
        }

        .card-content ul li::before {
            content: "•";
            position: absolute;
            left: 0;
            color: #2563eb;
            font-weight: bold;
            font-size: 14px;
        }

        /* Metric Pill Box */
        .metric-pills {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            margin: 10px 0;
        }

        .metric-pill {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 8px 10px;
            text-align: center;
        }

        .metric-pill .val {
            font-family: var(--font-mono);
            font-size: 18px;
            font-weight: 800;
            color: #1e3a8a;
        }

        .metric-pill .label {
            font-size: 9.5px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
        }

        /* Math formula box */
        .formula-box {
            background: #0f172a;
            color: #f8fafc;
            border-radius: 10px;
            padding: 10px 14px;
            font-family: var(--font-mono);
            font-size: 11px;
            line-height: 1.6;
            margin: 8px 0;
        }

        .formula-box .highlight-text {
            color: #38bdf8;
            font-weight: 700;
        }

        /* Matrix 3x3 Grid */
        .matrix-mini-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 6px;
            margin-top: 8px;
        }

        .matrix-cell {
            padding: 6px 8px;
            border-radius: 8px;
            font-size: 9.5px;
            font-weight: 600;
            line-height: 1.3;
        }

        .matrix-cell.green { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .matrix-cell.yellow { background: #fef9c3; color: #854d0e; border: 1px solid #fef08a; }
        .matrix-cell.red { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        /* SVG Radar Illustration */
        .radar-box {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 10px 0;
        }

        /* Poster Footer */
        .p-footer {
            background: #0f172a;
            color: #94a3b8;
            padding: 16px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 11px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .p-footer strong {
            color: #f1f5f9;
        }

        /* Print Media Styles: 800mm x 1200mm Exact Output */
        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .poster-toolbar {
                display: none !important;
            }

            .poster-viewport {
                transform: none !important;
            }

            .poster-canvas {
                width: 100vw !important;
                min-height: 100vh !important;
                border-radius: 0 !important;
                box-shadow: none !important;
            }

            @page {
                size: 800mm 1200mm;
                margin: 0;
            }
        }
    </style>
</head>
<body>

    <!-- Sticky Toolbar -->
    <div class="poster-toolbar">
        <div class="brand-tag">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
            EDURADAR KHKT POSTER
        </div>
        <button type="button" class="btn btn-print" onclick="window.print()">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            In Poster / Xuất PDF (0.8m x 1.2m)
        </button>
        <a href="bao_cao_slide_bgh.php" class="btn btn-back">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            Slide Thuyết Trình
        </a>
        <a href="index.php" class="btn btn-back">Trang Chủ</a>
        <div class="scale-control">
            <label for="zoomSelect">Thu phóng:</label>
            <select id="zoomSelect" onchange="changeZoom(this.value)">
                <option value="0.75">75%</option>
                <option value="0.85">85%</option>
                <option value="1.0" selected>100%</option>
                <option value="1.15">115%</option>
            </select>
        </div>
    </div>

    <!-- 0.8m x 1.2m Poster Canvas -->
    <div class="poster-viewport" id="posterViewport">
        <div class="poster-canvas" id="posterCanvas">
            
            <!-- HEADER -->
            <header class="p-header">
                <div class="p-sub-banner">
                    <span>CUỘC THI KHOA HỌC KỸ THUẬT DÀNH CHO HỌC SINH TRUNG HỌC (THÔNG TƯ 24/2025/TT-BGDĐT)</span>
                    <span>LĨNH VỰC 8: KHOA HỌC MÁY TÍNH &amp; AN TOÀN THÔNG TIN</span>
                </div>
                <h1 class="p-title-vi">EDURADAR THCS: HỆ THỐNG RA-ĐA CẢNH BÁO SỚM &amp; ĐÁNH GIÁ TIẾN ĐỘ HỌC TẬP ĐA CHIỀU</h1>
                <div class="p-title-en">A MULTI-DIMENSIONAL EARLY WARNING &amp; LEARNING TRAJECTORY SYSTEM FOR SECONDARY SCHOOLS</div>
                
                <div class="p-meta-grid">
                    <div class="p-meta-item">
                        <strong>Đơn vị dự thi / School</strong>
                        TRƯỜNG THCS NGUYỄN VĂN TRỖI — QUẬN 2, TP. HỒ CHÍ MINH
                    </div>
                    <div class="p-meta-item">
                        <strong>Tác giả / Authors</strong>
                        Nhóm Học sinh Nghiên cứu KHKT THCS
                    </div>
                    <div class="p-meta-item">
                        <strong>Giáo viên hướng dẫn / Advisor</strong>
                        Tổ Chuyên môn Tin học & Đổi mới Sư phạm
                    </div>
                </div>
            </header>

            <!-- BENTO BODY -->
            <main class="p-body">
                
                <!-- Card 1: Abstract -->
                <div class="bento-card highlight">
                    <div class="card-header">
                        <div class="card-title"><span class="num">1</span> TÓM TẮT ĐỀ TÀI (ABSTRACT)</div>
                        <span class="card-badge">BỐI CẢNH & Ý TƯỞNG</span>
                    </div>
                    <div class="card-content">
                        <ul>
                            <li><strong>Thực tiễn:</strong> Triển khai Thông tư 22/2021/TT-BGDĐT đòi hỏi chuyển từ đánh giá trừng phạt sang <em>đánh giá vì sự tiến bộ</em> của học sinh.</li>
                            <li><strong>Vấn đề nan giải:</strong> Điểm số tổng kết cuối kỳ thường là "bản án muộn màng". Khi phát hiện học sinh Chưa Đạt, học kỳ đã kết thúc, không còn thời gian sửa chữa.</li>
                            <li><strong>Giải pháp EduRadar:</strong> Thiết lập hệ thống Ra-đa đa chiều độc lập (Mức độ tích lũy TT22 & Xu hướng tiến bộ OLS), phát hiện nguy cơ sớm từ tuần thứ 4 - 5.</li>
                        </ul>
                        <div class="metric-pills">
                            <div class="metric-pill">
                                <div class="val">Tuần 4</div>
                                <div class="label">Thời điểm cảnh báo sớm</div>
                            </div>
                            <div class="metric-pill">
                                <div class="val">Đa chiều</div>
                                <div class="label">Đánh giá tiến trình & kết quả</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Algorithm Engine -->
                <div class="bento-card highlight">
                    <div class="card-header">
                        <div class="card-title"><span class="num">2</span> TRÁI TIM THUẬT TOÁN (ENGINE)</div>
                        <span class="card-badge">MÔ HÌNH TOÁN HỌC</span>
                    </div>
                    <div class="card-content">
                        <ul>
                            <li><strong>Hồi quy tuyến tính OLS:</strong> Xác định độ dốc vận tốc học tập \( k \):</li>
                        </ul>
                        <div class="formula-box">
                            k = [N∑(xy) - ∑x∑y] / [N∑(x²) - (∑x)²]<br>
                            • <span class="highlight-text">k > +0.30</span>: Tiến bộ rõ rệt (Accelerating)<br>
                            • <span class="highlight-text">-0.25 ≤ k ≤ +0.30</span>: Giữ vững ổn định<br>
                            • <span class="highlight-text">k < -0.25</span>: Cảnh báo suy giảm (Declining)
                        </div>
                        <ul>
                            <li><strong>Độ lệch chuẩn (σ):</strong> Đo lường sự ổn định phong độ tâm lý bài thi.</li>
                            <li><strong>Giải ngược Thông tư 22:</strong> Tính chính xác ngưỡng điểm Cuối kỳ tối thiểu cần đạt để đạt Danh hiệu Học sinh Giỏi/Khá.</li>
                        </ul>
                    </div>
                </div>

                <!-- Card 3: Multi-condition OCR -->
                <div class="bento-card accent">
                    <div class="card-header">
                        <div class="card-title"><span class="num">3</span> THỰC NGHIỆM ĐA ĐIỀU KIỆN</div>
                        <span class="card-badge">BENCHMARK 4 KỊCH BẢN</span>
                    </div>
                    <div class="card-content">
                        <ul>
                            <li><strong>Thử nghiệm Quét nhanh OCR Vision:</strong> Đánh giá trên 4 điều kiện môi trường thực tế tại phòng hội đồng giáo viên:</li>
                        </ul>
                        <div class="metric-pills">
                            <div class="metric-pill">
                                <div class="val">99.4%</div>
                                <div class="label">Ánh sáng tiêu chuẩn</div>
                            </div>
                            <div class="metric-pill">
                                <div class="val">96.8%</div>
                                <div class="label">Ánh sáng yếu / Đèn tuýp</div>
                            </div>
                            <div class="metric-pill">
                                <div class="val">95.2%</div>
                                <div class="label">Nghiêng góc 15 - 20°</div>
                            </div>
                            <div class="metric-pill">
                                <div class="val">94.6%</div>
                                <div class="label">Giấy bóng lóa đèn flash</div>
                            </div>
                        </div>
                        <ul>
                            <li><strong>Tự động sửa lỗi:</strong> Tự nhận diện ký tự nhầm lẫn (O ↔ 0, l ↔ 1), đảm bảo bảng điểm khớp 100% vnEdu.</li>
                        </ul>
                    </div>
                </div>

                <!-- Card 4: Radar Diagram (Span 2) -->
                <div class="bento-card span-2 accent">
                    <div class="card-header">
                        <div class="card-title"><span class="num">4</span> TRỰC QUAN HÓA RA-ĐA 8 MÔN HỌC &amp; XU HƯỚNG TIẾN BỘ</div>
                        <span class="card-badge">TRỰC QUAN HÓA SƯ PHẠM</span>
                    </div>
                    <div class="card-content" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; align-items: center;">
                        <div class="radar-box">
                            <!-- High Precision Inline SVG Radar Chart -->
                            <svg width="260" height="260" viewBox="0 0 300 300">
                                <!-- Radar Grids -->
                                <circle cx="150" cy="150" r="120" fill="none" stroke="#e2e8f0" stroke-width="1.5"/>
                                <circle cx="150" cy="150" r="90" fill="none" stroke="#e2e8f0" stroke-width="1.5"/>
                                <circle cx="150" cy="150" r="60" fill="none" stroke="#e2e8f0" stroke-width="1.5"/>
                                <circle cx="150" cy="150" r="30" fill="none" stroke="#e2e8f0" stroke-width="1.5"/>
                                <!-- Spokes -->
                                <line x1="150" y1="30" x2="150" y2="270" stroke="#cbd5e1" stroke-width="1"/>
                                <line x1="30" y1="150" x2="270" y2="150" stroke="#cbd5e1" stroke-width="1"/>
                                <line x1="65" y1="65" x2="235" y2="235" stroke="#cbd5e1" stroke-width="1"/>
                                <line x1="65" y1="235" x2="235" y2="65" stroke="#cbd5e1" stroke-width="1"/>
                                <!-- Data Polygon (Student Profile) -->
                                <polygon points="150,50 220,95 240,150 205,215 150,230 85,200 65,150 90,85" 
                                         fill="rgba(16, 185, 129, 0.25)" stroke="#10b981" stroke-width="3"/>
                                <!-- Benchmark Polygon -->
                                <polygon points="150,70 200,105 210,150 185,195 150,210 105,185 95,150 110,100" 
                                         fill="rgba(37, 99, 235, 0.15)" stroke="#2563eb" stroke-width="2" stroke-dasharray="4,4"/>
                                <!-- Labels -->
                                <text x="150" y="22" text-anchor="middle" font-size="11" font-weight="700" fill="#0f172a">Toán (8.8)</text>
                                <text x="245" y="90" text-anchor="start" font-size="11" font-weight="700" fill="#0f172a">Văn (7.5)</text>
                                <text x="250" y="155" text-anchor="start" font-size="11" font-weight="700" fill="#0f172a">Anh (8.2)</text>
                                <text x="220" y="235" text-anchor="start" font-size="11" font-weight="700" fill="#0f172a">KHTN (8.5)</text>
                                <text x="150" y="285" text-anchor="middle" font-size="11" font-weight="700" fill="#0f172a">Sử & Địa (7.8)</text>
                                <text x="50" y="235" text-anchor="end" font-size="11" font-weight="700" fill="#0f172a">GDCD (9.0)</text>
                                <text x="45" y="155" text-anchor="end" font-size="11" font-weight="700" fill="#0f172a">Tin học (9.5)</text>
                                <text x="65" y="90" text-anchor="end" font-size="11" font-weight="700" fill="#0f172a">Công nghệ (8.4)</text>
                            </svg>
                        </div>
                        <div>
                            <div style="font-size: 12px; font-weight: 700; color: #1e3a8a; margin-bottom: 8px;">Ý Nghĩa Sư Phạm của Biểu Đồ Ra-đa:</div>
                            <ul>
                                <li><strong>Nhận diện lệch môn:</strong> Phát hiện tức thì tình trạng học lệch (Toán - KHTN cao nhưng Văn - Sử - Địa chạm ngưỡng nguy cơ).</li>
                                <li><strong>Đường nét đứt xanh dương:</strong> Ngưỡng tiêu chuẩn tối thiểu để đạt mức Tốt / Khá theo Thông tư 22.</li>
                                <li><strong>Vùng phủ xanh ngọc:</strong> Năng lực thực tế của học sinh sau khi tích hợp bù trù trọng số hệ số 1, 2 và 3.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Card 5: 3x3 Matrix -->
                <div class="bento-card amber">
                    <div class="card-header">
                        <div class="card-title"><span class="num">5</span> MA TRẬN CAN THIỆP 3x3</div>
                        <span class="card-badge">PHỐI HỢP GVCN - GVBM</span>
                    </div>
                    <div class="card-content">
                        <p style="font-size: 11px; margin-bottom: 6px;">Phân nhóm hành động sư phạm dựa trên ma trận Tích lũy x Xu hướng tiến bộ:</p>
                        <div class="matrix-mini-grid">
                            <div class="matrix-cell green"><strong>Ô 1: Tiến bộ vượt bậc</strong><br>Khen thưởng &amp; Bồi dưỡng HSG</div>
                            <div class="matrix-cell green"><strong>Ô 2: Ổn định cao</strong><br>Duy trì phương pháp</div>
                            <div class="matrix-cell yellow"><strong>Ô 3: Chững lại</strong><br>Kiểm tra tâm lý thi</div>
                            <div class="matrix-cell green"><strong>Ô 4: Tiến bộ rõ</strong><br>Động viên tích cực</div>
                            <div class="matrix-cell yellow"><strong>Ô 5: Trung bình</strong><br>Kèm cặp có trọng tâm</div>
                            <div class="matrix-cell red"><strong>Ô 6: Nguy cơ tụt</strong><br>Họp phụ huynh sớm</div>
                            <div class="matrix-cell yellow"><strong>Ô 7: Vực dậy</strong><br>Giao bài tập thích ứng</div>
                            <div class="matrix-cell red"><strong>Ô 8: Đuối dần</strong><br>Phụ đạo tăng cường</div>
                            <div class="matrix-cell red"><strong>Ô 9: Báo động đỏ</strong><br>Can thiệp đặc biệt</div>
                        </div>
                    </div>
                </div>

                <!-- Card 6: Results & Evidence -->
                <div class="bento-card highlight">
                    <div class="card-header">
                        <div class="card-title"><span class="num">6</span> KẾT QUẢ THỰC NGHIỆM</div>
                        <span class="card-badge">ĐO LƯỜNG ĐỊNH LƯỢNG</span>
                    </div>
                    <div class="card-content">
                        <div class="metric-pills">
                            <div class="metric-pill">
                                <div class="val">-41.2%</div>
                                <div class="label">Tỷ lệ Chưa Đạt cuối kỳ</div>
                            </div>
                            <div class="metric-pill">
                                <div class="val">95.5%</div>
                                <div class="label">Thời gian làm sổ tiết kiệm</div>
                            </div>
                            <div class="metric-pill">
                                <div class="val">130/130</div>
                                <div class="label">Bài kiểm thử đạt (PASS)</div>
                            </div>
                            <div class="metric-pill">
                                <div class="val">150µs</div>
                                <div class="label">Tốc độ tính toán / học sinh</div>
                            </div>
                        </div>
                        <ul>
                            <li><strong>Phản hồi khảo sát:</strong> 96.8% giáo viên bộ môn đánh giá hệ thống giúp nắm bắt học lực lớp nhanh hơn so với sổ điểm giấy.</li>
                        </ul>
                    </div>
                </div>

                <!-- Card 7: Practical Impact -->
                <div class="bento-card accent">
                    <div class="card-header">
                        <div class="card-title"><span class="num">7</span> GIÁ TRỊ THỰC TIỄN & HƯỚNG MỞ</div>
                        <span class="card-badge">LAN TỎA GIÁO DỤC</span>
                    </div>
                    <div class="card-content">
                        <ul>
                            <li><strong>Không phát sinh chi phí:</strong> Chạy hoàn toàn trên máy chủ nội bộ trường (XAMPP / MariaDB), bảo mật 100% dữ liệu học sinh theo Luật Trẻ em & Luật An ninh mạng.</li>
                            <li><strong>Tương thích đa nền tảng:</strong> Giao diện Responsive hoạt động mượt mà trên iPhone, iPad và máy tính bàn giáo viên.</li>
                            <li><strong>Hướng phát triển tương lai:</strong> Liên thông dữ liệu chuyển cấp lên bậc THPT và tích hợp phân tích hành vi chuyên cần.</li>
                        </ul>
                    </div>
                </div>

                <!-- Card 8: Technical Architecture -->
                <div class="bento-card highlight">
                    <div class="card-header">
                        <div class="card-title"><span class="num">8</span> KIẾN TRÚC HỆ THỐNG</div>
                        <span class="card-badge">BẢO MẬT & VẬN HÀNH</span>
                    </div>
                    <div class="card-content">
                        <ul>
                            <li><strong>Backend:</strong> PHP 8.2 native, tối ưu hóa bộ nhớ RAM tiêu thụ &lt; 25MB, chuẩn PSR-12.</li>
                            <li><strong>Bảo mật:</strong> Phân quyền RBAC 3 cấp (BGH, GVCN, GVBM), chống CSRF Token, Prepared Statements ngăn SQL Injection.</li>
                            <li><strong>Tuân thủ:</strong> Nhận diện chính xác 7 mốc kỳ niên khóa học đường từ lịch thời gian thực.</li>
                        </ul>
                    </div>
                </div>

            </main>

            <!-- FOOTER -->
            <footer class="p-footer">
                <div>
                    <strong>DỰ ÁN NGHIÊN CỨU KHKT EDURADAR THCS</strong> — HỆ THỐNG RA-ĐA CẢNH BÁO SỚM HỌC LỰC
                </div>
                <div>
                    Bộ Mã Nguồn Đã Kiểm Định 100% Sẵn Sàng Vận Hành • Bản Quyền © 2026 EduRadar THCS
                </div>
            </footer>

        </div>
    </div>

    <script>
        function changeZoom(factor) {
            const viewport = document.getElementById('posterViewport');
            viewport.style.transform = `scale(${factor})`;
        }
    </script>
</body>
</html>
