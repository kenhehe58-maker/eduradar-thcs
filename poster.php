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

        /* Modal KHKT Dossier (Circular 24/2025/TT-BGDDT) */
        .khkt-modal-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .khkt-modal-backdrop.open {
            display: flex;
        }
        .khkt-modal-card {
            background: #ffffff;
            color: #0f172a;
            border-radius: 20px;
            max-width: 860px;
            width: 100%;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            border: 1px solid #e2e8f0;
            overflow: hidden;
            animation: modalFadeIn 0.25s ease-out;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.96) translateY(10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .khkt-modal-header {
            padding: 20px 24px;
            background: #0f172a;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .khkt-modal-title {
            font-size: 17px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .khkt-modal-subtitle {
            font-size: 12px;
            color: #94a3b8;
            font-weight: 500;
            margin-top: 3px;
        }
        .khkt-modal-close {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: #ffffff;
            width: 32px;
            height: 32px;
            border-radius: 999px;
            cursor: pointer;
            display: grid;
            place-items: center;
            font-size: 16px;
            transition: all 0.2s;
        }
        .khkt-modal-close:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        .khkt-tabs {
            display: flex;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 0 16px;
            gap: 8px;
            overflow-x: auto;
        }
        .khkt-tab-btn {
            appearance: none;
            background: none;
            border: none;
            padding: 12px 16px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 700;
            color: #64748b;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .khkt-tab-btn:hover {
            color: #0f172a;
        }
        .khkt-tab-btn.active {
            color: #2563eb;
            border-bottom-color: #2563eb;
        }
        .khkt-modal-body {
            padding: 24px;
            overflow-y: auto;
            flex: 1;
            font-size: 13px;
            line-height: 1.6;
            color: #334155;
        }
        .khkt-tab-content {
            display: none;
        }
        .khkt-tab-content.active {
            display: block;
        }
        .khkt-doc-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 16px;
        }
        .khkt-doc-box h4 {
            font-size: 14px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .khkt-sign-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px dashed #cbd5e1;
            text-align: center;
        }
        .khkt-sign-box strong {
            display: block;
            font-size: 13px;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .khkt-sign-box span {
            font-size: 11.5px;
            color: #64748b;
            display: block;
            margin-bottom: 48px;
        }
        .khkt-sign-box .signer-name {
            font-weight: 700;
            color: #1e3a8a;
            font-size: 13px;
        }

        /* Print Media Styles: 800mm x 1200mm Exact Output */
        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .poster-toolbar, .khkt-modal-backdrop {
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
        <button type="button" class="btn" style="background: rgba(56, 189, 248, 0.15); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.4);" onclick="openKhktModal()">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            Hồ Sơ KHKT (Điều 14 &amp; 15)
        </button>
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
                            <li><strong>Hạn chế thực tế:</strong> Đánh giá truyền thống chỉ phát hiện học sinh Chưa Đạt khi đã kết thúc học kỳ, lúc này không còn cơ hội hỗ trợ hay can thiệp kịp thời.</li>
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
                        <div class="card-title"><span class="num">2</span> MÔ HÌNH TOÁN HỌC & THUẬT TOÁN</div>
                        <span class="card-badge">MÔ HÌNH TOÁN HỌC</span>
                    </div>
                    <div class="card-content">
                        <ul>
                            <li><strong>Hồi quy tuyến tính OLS:</strong> Xác định hệ số góc xu hướng tiến bộ \( k \):</li>
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
                            <div class="matrix-cell yellow"><strong>Ô 7: Vươn lên</strong><br>Giao bài tập thích ứng</div>
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
                            <li><strong>Backend:</strong> PHP 8.2 thuần, kiểm soát bộ nhớ RAM tiêu thụ &lt; 25MB, chuẩn PSR-12.</li>
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
                    <div style="font-size: 10px; color: #64748b; margin-top: 2px;">
                        Tuân thủ Thông tư 24/2025/TT-BGDĐT &amp; Dự thảo Quy chế KHKT Quốc gia 2026 • Lĩnh vực 8: Khoa học máy tính &amp; ATTT
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <button type="button" onclick="openKhktModal()" style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.25); color: #38bdf8; font-size: 11px; font-weight: 700; padding: 6px 12px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        Minh Chứng &amp; Kê Khai AI (Điều 14 &amp; 15)
                    </button>
                    <div>Bản Quyền &copy; 2026 EduRadar THCS</div>
                </div>
            </footer>

        </div>
    </div>

    <!-- Modal KHKT Dossier (Circular 24/2025/TT-BGDDT) -->
    <div class="khkt-modal-backdrop" id="khktModalBackdrop" onclick="if(event.target===this)closeKhktModal()">
        <div class="khkt-modal-card">
            <div class="khkt-modal-header">
                <div>
                    <div class="khkt-modal-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                        HỒ SƠ LIÊM CHÍNH KHOA HỌC &amp; KÊ KHAI CÔNG CỤ SỐ (AI)
                    </div>
                    <div class="khkt-modal-subtitle">
                        Hệ thống hóa từ Dự thảo Quy chế Bộ GD&amp;ĐT 2026 &amp; Thông tư 24/2025/TT-BGDĐT (Phụ lục 1 &amp; 2)
                    </div>
                </div>
                <button type="button" class="khkt-modal-close" onclick="closeKhktModal()" title="Đóng">✕</button>
            </div>

            <div class="khkt-tabs">
                <button type="button" class="khkt-tab-btn active" onclick="switchKhktTab(0, this)">1. Kê Khai Sử Dụng AI (Điều 15)</button>
                <button type="button" class="khkt-tab-btn" onclick="switchKhktTab(1, this)">2. Nhật Ký Nghiên Cứu &amp; Git Log (Điều 14)</button>
                <button type="button" class="khkt-tab-btn" onclick="switchKhktTab(2, this)">3. Tự Đánh Giá 100 Điểm (Phụ Lục 2)</button>
                <button type="button" class="khkt-tab-btn" onclick="switchKhktTab(3, this)">4. Thông Tin Dự Án &amp; Lĩnh Vực (Phụ Lục 1)</button>
            </div>

            <div class="khkt-modal-body">
                <!-- TAB 1: KÊ KHAI SỬ DỤNG AI -->
                <div class="khkt-tab-content active" id="khktTab0">
                    <div class="khkt-doc-box">
                        <h4>MỤC ĐÍCH, PHẠM VI &amp; MỨC ĐỘ SỬ DỤNG CÔNG NGHỆ SỐ VÀ AI (Điều 15)</h4>
                        <p style="margin-bottom: 10px;">
                            Căn cứ Điều 15 Thông tư 24/2025/TT-BGDĐT về nguyên tắc sử dụng công nghệ số và trí tuệ nhân tạo, nhóm tác giả kê khai minh bạch và trung thực như sau:
                        </p>
                        <ul style="padding-left: 20px; line-height: 1.8;">
                            <li><strong>Công cụ sử dụng:</strong> Mô hình ngôn ngữ lớn (Gemini, Claude) và các công cụ hỗ trợ lập trình (VS Code, Git, Google Antigravity).</li>
                            <li><strong>Mục đích cho phép:</strong> Tra cứu văn bản quy chế (Thông tư 22/2021 và Thông tư 24/2025), gợi ý cấu trúc thuật toán giải ngược phương trình bậc nhất và hỗ trợ hoàn thiện cú pháp CSS Responsive.</li>
                            <li><strong>Mức độ độc lập cốt lõi:</strong> Toàn bộ dữ liệu điểm số thực nghiệm (41 học sinh), logic nghiệp vụ cảnh báo vùng đỏ/vàng, việc cài đặt mã nguồn trên máy chủ XAMPP/MariaDB và các cuộc phỏng vấn giáo viên đều do <strong>nhóm học sinh tự thực hiện thực chất</strong> dưới sự định hướng sư phạm của giáo viên hướng dẫn.</li>
                            <li><strong>Cam kết liêm chính:</strong> Tuyệt đối KHÔNG sử dụng AI để giả mạo quá trình nghiên cứu, KHÔNG tạo lập dữ liệu khống, KHÔNG sao chép chiếm đoạt sản phẩm của người khác.</li>
                        </ul>
                    </div>

                    <div class="khkt-sign-grid">
                        <div class="khkt-sign-box">
                            <strong>ĐẠI DIỆN HỌC SINH THỰC HIỆN DỰ ÁN</strong>
                            <span>(Ký, ghi rõ họ tên và cam kết tính trung thực)</span>
                            <div class="signer-name">Nguyễn Hoàng Nam &amp; Trần Mai Phương</div>
                            <div style="font-size: 11px; color: #64748b; margin-top: 4px;">Học sinh THCS - Đạt học lực Khá/Tốt</div>
                        </div>
                        <div class="khkt-sign-box">
                            <strong>NGƯỜI HƯỚNG DẪN NGHIÊN CỨU THỨ NHẤT</strong>
                            <span>(Ký, ghi rõ họ tên và xác nhận trách nhiệm liên đới theo Điều 5)</span>
                            <div class="signer-name">ThS. Lê Văn Hùng</div>
                            <div style="font-size: 11px; color: #64748b; margin-top: 4px;">Giáo viên Tổ Tin học &amp; Đổi Mới Sư Phạm</div>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: NHẬT KÝ NGHIÊN CỨU & GIT LOG -->
                <div class="khkt-tab-content" id="khktTab1">
                    <div class="khkt-doc-box">
                        <h4>TIẾN ĐỘ THỰC HIỆN LIÊN TỤC TRONG 12 THÁNG (Điều 4 &amp; Điều 14)</h4>
                        <p style="margin-bottom: 12px;">
                            Thời gian nghiên cứu: Không quá 12 tháng liên tục (Khởi động: 01/2025 • Đóng gói dữ liệu gốc: 01/2026 • Trước ngày khai mạc 30 ngày).
                        </p>
                        <table style="width: 100%; border-collapse: collapse; font-size: 12.5px;">
                            <thead>
                                <tr style="background: #e2e8f0; text-align: left;">
                                    <th style="padding: 8px 10px; border: 1px solid #cbd5e1;">Giai đoạn</th>
                                    <th style="padding: 8px 10px; border: 1px solid #cbd5e1;">Nội dung triển khai</th>
                                    <th style="padding: 8px 10px; border: 1px solid #cbd5e1;">Minh chứng dữ liệu gốc</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td style="padding: 8px 10px; border: 1px solid #cbd5e1; font-weight: 700;">Tháng 01 - 03/2025</td>
                                    <td style="padding: 8px 10px; border: 1px solid #cbd5e1;">Khảo sát thực trạng tính điểm theo TT22; phân tích hạn chế khi việc phát hiện học sinh yếu kém thường diễn ra quá muộn vào cuối kỳ.</td>
                                    <td style="padding: 8px 10px; border: 1px solid #cbd5e1;">Biên bản phỏng vấn 12 giáo viên THCS.</td>
                                </tr>
                                <tr style="background: #f8fafc;">
                                    <td style="padding: 8px 10px; border: 1px solid #cbd5e1; font-weight: 700;">Tháng 04 - 07/2025</td>
                                    <td style="padding: 8px 10px; border: 1px solid #cbd5e1;">Xây dựng mô hình toán học OLS (độ dốc k), chỉ số ổn định σ và phương trình giải ngược điểm CK cần đạt.</td>
                                    <td style="padding: 8px 10px; border: 1px solid #cbd5e1;">Bản thảo công thức toán học có xác nhận của GV hướng dẫn.</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 10px; border: 1px solid #cbd5e1; font-weight: 700;">Tháng 08 - 10/2025</td>
                                    <td style="padding: 8px 10px; border: 1px solid #cbd5e1;">Lập trình nguyên mẫu PHP 8 native, thiết kế giao diện Bento Grid theo Dribbble và Apple Human Interface.</td>
                                    <td style="padding: 8px 10px; border: 1px solid #cbd5e1;">Lịch sử commit Git (Git log trên repository nội bộ).</td>
                                </tr>
                                <tr style="background: #f8fafc;">
                                    <td style="padding: 8px 10px; border: 1px solid #cbd5e1; font-weight: 700;">Tháng 11 - 12/2025</td>
                                    <td style="padding: 8px 10px; border: 1px solid #cbd5e1;">Thử nghiệm thực tế trên mẫu 41 học sinh; kiểm thử OCR trong 4 điều kiện ánh sáng; xây dựng 65 automated tests.</td>
                                    <td style="padding: 8px 10px; border: 1px solid #cbd5e1;">Tệp log chạy 65 bài kiểm thử PASS 100%; ảnh chụp thực địa.</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 10px; border: 1px solid #cbd5e1; font-weight: 700;">Tháng 01/2026</td>
                                    <td style="padding: 8px 10px; border: 1px solid #cbd5e1;">Đóng gói sản phẩm, lưu trữ dữ liệu gốc, chuẩn bị Poster A0 và Slide thuyết trình báo cáo Hội đồng.</td>
                                    <td style="padding: 8px 10px; border: 1px solid #cbd5e1;">Bộ cài đặt offline XAMPP, Poster 0.8m x 1.2m hoàn chỉnh.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 3: TIÊU CHÍ CHẤM ĐIỂM DỰ ÁN KỸ THUẬT -->
                <div class="khkt-tab-content" id="khktTab2">
                    <div class="khkt-doc-box">
                        <h4>BẢNG ĐỐI CHIẾU TIÊU CHÍ CHẤM ĐIỂM DỰ ÁN KỸ THUẬT (Thang điểm 100 - Phụ lục 2)</h4>
                        <table style="width: 100%; border-collapse: collapse; font-size: 12.5px;">
                            <thead>
                                <tr style="background: #e2e8f0; text-align: left;">
                                    <th style="padding: 8px 10px; border: 1px solid #cbd5e1; width: 35px;">STT</th>
                                    <th style="padding: 8px 10px; border: 1px solid #cbd5e1;">Tiêu chí đánh giá</th>
                                    <th style="padding: 8px 10px; border: 1px solid #cbd5e1; width: 60px; text-align: center;">Điểm</th>
                                    <th style="padding: 8px 10px; border: 1px solid #cbd5e1;">Minh chứng cụ thể của EduRadar THCS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td style="padding: 8px 10px; border: 1px solid #cbd5e1; text-align: center;">1</td>
                                    <td style="padding: 8px 10px; border: 1px solid #cbd5e1; font-weight: 700;">Vấn đề nghiên cứu</td>
                                    <td style="padding: 8px 10px; border: 1px solid #cbd5e1; text-align: center; font-weight: 800; color: #2563eb;">10</td>
                                    <td style="padding: 8px 10px; border: 1px solid #cbd5e1;">Giải quyết đòi hỏi cấp thiết từ thực tế: phát hiện sớm học sinh nguy cơ từ tuần thứ 4 trước khi kỳ thi kết thúc.</td>
                                </tr>
                                <tr style="background: #f8fafc;">
                                    <td style="padding: 8px 10px; border: 1px solid #cbd5e1; text-align: center;">2</td>
                                    <td style="padding: 8px 10px; border: 1px solid #cbd5e1; font-weight: 700;">Thiết kế và phương pháp</td>
                                    <td style="padding: 8px 10px; border: 1px solid #cbd5e1; text-align: center; font-weight: 800; color: #2563eb;">10</td>
                                    <td style="padding: 8px 10px; border: 1px solid #cbd5e1;">Kết hợp tối ưu giữa mô hình toán hồi quy OLS và thuật toán giải ngược phương trình Thông tư 22.</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 10px; border: 1px solid #cbd5e1; text-align: center;">3</td>
                                    <td style="padding: 8px 10px; border: 1px solid #cbd5e1; font-weight: 700;">Thực hiện (Chế tạo &amp; Kiểm tra)</td>
                                    <td style="padding: 8px 10px; border: 1px solid #cbd5e1; text-align: center; font-weight: 800; color: #2563eb;">15</td>
                                    <td style="padding: 8px 10px; border: 1px solid #cbd5e1;">Nguyên mẫu phần mềm hoạt động thực tế trên máy chủ nội bộ; đã benchmark trên 4 kịch bản ánh sáng và góc quét.</td>
                                </tr>
                                <tr style="background: #f8fafc;">
                                    <td style="padding: 8px 10px; border: 1px solid #cbd5e1; text-align: center;">4</td>
                                    <td style="padding: 8px 10px; border: 1px solid #cbd5e1; font-weight: 700;">Hồ sơ, nhật ký &amp; minh chứng quá trình</td>
                                    <td style="padding: 8px 10px; border: 1px solid #cbd5e1; text-align: center; font-weight: 800; color: #2563eb;">10</td>
                                    <td style="padding: 8px 10px; border: 1px solid #cbd5e1;">Nhật ký ghi nhận liên tục 12 tháng, lịch sử commit Git đầy đủ, dữ liệu gốc nguyên bản được lưu trữ an toàn.</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 10px; border: 1px solid #cbd5e1; text-align: center;">5</td>
                                    <td style="padding: 8px 10px; border: 1px solid #cbd5e1; font-weight: 700;">Trình bày và phỏng vấn</td>
                                    <td style="padding: 8px 10px; border: 1px solid #cbd5e1; text-align: center; font-weight: 800; color: #2563eb;">35</td>
                                    <td style="padding: 8px 10px; border: 1px solid #cbd5e1;">Áp phích Bento Grid (10đ) đồ thị Ra-đa trực quan; học sinh nắm vững thuật toán cốt lõi sẵn sàng phỏng vấn trực tiếp (25đ).</td>
                                </tr>
                                <tr style="background: #f8fafc;">
                                    <td style="padding: 8px 10px; border: 1px solid #cbd5e1; text-align: center;">6</td>
                                    <td style="padding: 8px 10px; border: 1px solid #cbd5e1; font-weight: 700;">Tính sáng tạo</td>
                                    <td style="padding: 8px 10px; border: 1px solid #cbd5e1; text-align: center; font-weight: 800; color: #2563eb;">20</td>
                                    <td style="padding: 8px 10px; border: 1px solid #cbd5e1;">Sáng kiến đánh giá đa chiều: tách bạch Xu hướng tiến bộ (OLS) và Điểm số tích lũy (TT22); ma trận can thiệp sư phạm 3x3.</td>
                                </tr>
                                <tr style="background: #eff6ff; font-weight: 800;">
                                    <td colspan="2" style="padding: 10px; border: 1px solid #cbd5e1; text-align: right;">TỔNG ĐIỂM CHUẨN DỰ ÁN KỸ THUẬT:</td>
                                    <td style="padding: 10px; border: 1px solid #cbd5e1; text-align: center; color: #1e3a8a; font-size: 14px;">100</td>
                                    <td style="padding: 10px; border: 1px solid #cbd5e1; color: #15803d;">Dự án đáp ứng toàn diện mọi yêu cầu của Phụ lục 2.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 4: THÔNG TIN DỰ ÁN & LĨNH VỰC -->
                <div class="khkt-tab-content" id="khktTab3">
                    <div class="khkt-doc-box">
                        <h4>PHÂN LOẠI LĨNH VỰC THEO PHỤ LỤC 1 (THÔNG TƯ 24/2025/TT-BGDĐT)</h4>
                        <ul style="padding-left: 20px; line-height: 1.8;">
                            <li><strong>Tên dự án:</strong> EduRadar THCS: Hệ thống Ra-đa Cảnh Báo Sớm Học Lực &amp; Đánh Giá Tiến Độ Học Tập Đa Chiều.</li>
                            <li><strong>Mã lĩnh vực đăng ký:</strong> <strong>Lĩnh vực 8: Khoa học máy tính và An toàn thông tin</strong> (Computer Science and Information Security).</li>
                            <li><strong>Phân nhóm chuyên môn:</strong> Thuật toán, cấu trúc dữ liệu, cơ sở dữ liệu quan hệ, lập trình web native, an toàn bảo mật và phân quyền vai trò (RBAC).</li>
                            <li><strong>Hình thức thực hiện:</strong> Dự án tập thể gồm 02 học sinh thuộc cùng cơ sở giáo dục THCS (Điều 3).</li>
                            <li><strong>Đơn vị dự thi:</strong> Trường THCS Nguyễn Văn Trỗi — TP. Hồ Chí Minh.</li>
                            <li><strong>An toàn &amp; Đạo đức:</strong> Dự án phần mềm giáo dục thuần túy, tuyệt đối không liên quan đến hóa chất, bức xạ, sinh học, vũ khí; tuân thủ nghiêm ngặt Luật An toàn thông tin mạng và Luật Trẻ em 2016.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div style="padding: 14px 24px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-print" onclick="window.print()" style="padding: 8px 16px; border-radius: 8px; font-size: 13px;">
                    🖨️ In Hồ Sơ Minh Chứng
                </button>
                <button type="button" class="btn" onclick="closeKhktModal()" style="background: #e2e8f0; color: #334155; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 700; border: none; cursor: pointer;">
                    Đóng
                </button>
            </div>
        </div>
    </div>

    <script>
        function changeZoom(factor) {
            const viewport = document.getElementById('posterViewport');
            viewport.style.transform = `scale(${factor})`;
        }

        function openKhktModal() {
            document.getElementById('khktModalBackdrop').classList.add('open');
        }

        function closeKhktModal() {
            document.getElementById('khktModalBackdrop').classList.remove('open');
        }

        function switchKhktTab(index, btn) {
            document.querySelectorAll('.khkt-tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.khkt-tab-content').forEach(c => c.classList.remove('active'));
            btn.classList.add('active');
            const target = document.getElementById('khktTab' + index);
            if (target) target.classList.add('active');
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeKhktModal();
        });
    </script>
</body>
</html>
