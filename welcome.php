<?php
// welcome.php - Trang Chủ & Trung Tâm Khởi Động EduRadar THCS
// Chuẩn Dribbble EdTech Pro & UX Laws (Proximity, Stepper Progress, Actionable Labels)
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/radar_helper.php';
require_once __DIR__ . '/includes/algorithms.php';

$welcomeCalendar = get_academic_calendar_phase();

// Kiểm tra quyền đăng nhập
$userRole = $_SESSION['role'] ?? 'guest';
$userName = $_SESSION['full_name'] ?? ($_SESSION['username'] ?? 'Thầy/Cô');
$userRoleTitle = ($userRole === 'admin') ? 'Quản Trị Viên Trường' : (($userRole === 'teacher') ? 'Giáo Viên Bộ Môn' : 'Khách Dùng Thử');

$pdo = db();

// Truy vấn số liệu thống kê thực tế từ CSDL MariaDB
$totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$totalClasses = (int)$pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
$totalScores = (int)$pdo->query("SELECT COUNT(*) FROM scores WHERE avg_score IS NOT NULL")->fetchColumn();

// Thống kê phân tầng điểm toàn trường
$scoreStats = $pdo->query("
    SELECT 
        COUNT(CASE WHEN avg_score >= 8.0 THEN 1 END) as excellent_count,
        COUNT(CASE WHEN avg_score >= 6.5 AND avg_score < 8.0 THEN 1 END) as good_count,
        COUNT(CASE WHEN avg_score >= 5.0 AND avg_score < 6.5 THEN 1 END) as average_count,
        COUNT(CASE WHEN avg_score < 5.0 THEN 1 END) as low_count,
        COUNT(*) as total_evaluated
    FROM scores 
    WHERE avg_score IS NOT NULL
")->fetch();

$totalEval = max(1, (int)($scoreStats['total_evaluated'] ?? 0));
$pctGood = round(((int)($scoreStats['excellent_count'] ?? 0) + (int)($scoreStats['good_count'] ?? 0)) / $totalEval * 100, 1);
$pctLow = round(((int)($scoreStats['low_count'] ?? 0)) / $totalEval * 100, 1);

$pageTitle = 'Trang Chủ — EduRadar THCS';
$activeNav = 'welcome';
require_once __DIR__ . '/includes/header.php';
?>

<style>
/* =========================================================================
   DRIBBLE-INSPIRED HOMEPAGE & BENTO GRID SYSTEM
   ========================================================================= */
.welcome-hero {
    position: relative;
    overflow: hidden;
    background: linear-gradient(135deg, #ffffff 0%, #eff6ff 50%, #dbeafe 100%);
    border: 1px solid var(--border-subtle, #bfdbfe);
    border-radius: 24px;
    padding: 36px 40px;
    margin-bottom: 24px;
    box-shadow: 0 10px 30px rgba(37, 99, 235, 0.08);
}
[data-theme="dark"] .welcome-hero {
    background: linear-gradient(135deg, #111827 0%, #1e1b4b 60%, #0f172a 100%) !important;
    border-color: #23324d !important;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4) !important;
}

.welcome-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 16px;
    border-radius: 9999px;
    background: #dbeafe;
    color: #1d4ed8;
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 16px;
}
[data-theme="dark"] .welcome-badge {
    background: rgba(59, 130, 246, 0.2) !important;
    color: #93c5fd !important;
    border: 1px solid rgba(59, 130, 246, 0.35);
}

.welcome-title {
    font-size: 32px;
    font-weight: 800;
    color: var(--text-heading, #0f172a);
    letter-spacing: -0.03em;
    line-height: 1.25;
    margin-bottom: 12px;
    max-width: 840px;
}

.welcome-desc {
    font-size: 15px;
    color: var(--text-muted, #475569);
    line-height: 1.6;
    max-width: 780px;
    margin-bottom: 24px;
}

.welcome-cta-group {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
}

/* BENTO KPI SUMMARY GRID */
.welcome-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 16px;
    margin-bottom: 28px;
}

.kpi-card {
    background: var(--card-bg, #ffffff);
    border: 1px solid var(--border-subtle, #e2e8f0);
    border-radius: 18px;
    padding: 20px 22px;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    display: flex;
    align-items: center;
    gap: 16px;
}
.kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
}
[data-theme="dark"] .kpi-card {
    background: #131b2e !important;
    border-color: #23324d !important;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.3) !important;
}
.kpi-icon-box {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: grid;
    place-items: center;
    font-size: 22px;
    flex: none;
}
[data-theme="dark"] .kpi-icon-box {
    background: rgba(255, 255, 255, 0.05) !important;
}

/* LAUNCHPAD CORE PORTALS (DRIBBLE CARDS) */
.launchpad-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 20px;
    margin-bottom: 32px;
}

.launchpad-card {
    background: var(--card-bg, #ffffff);
    border: 1px solid var(--border-subtle, #e2e8f0);
    border-radius: 20px;
    padding: 26px;
    box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
    transition: all 0.25s ease;
    display: flex;
    flex-direction: column;
    text-decoration: none;
    color: inherit;
    position: relative;
    overflow: hidden;
}
.launchpad-card:hover {
    transform: translateY(-4px);
    border-color: #93c5fd;
    box-shadow: 0 14px 28px rgba(37, 99, 235, 0.1);
}
[data-theme="dark"] .launchpad-card {
    background: #131b2e !important;
    border-color: #23324d !important;
}
[data-theme="dark"] .launchpad-card:hover {
    border-color: #3b82f6 !important;
    box-shadow: 0 14px 28px rgba(0, 0, 0, 0.5) !important;
}

.launchpad-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}

.launchpad-card-title {
    font-size: 18px;
    font-weight: 800;
    color: var(--text-heading, #0f172a);
    margin-bottom: 6px;
    letter-spacing: -0.02em;
}

.launchpad-card-desc {
    font-size: 13.5px;
    color: var(--text-muted, #64748b);
    line-height: 1.5;
    margin-bottom: 20px;
    flex: 1;
}

.launchpad-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 13px;
    font-weight: 700;
    color: #2563eb;
    border-top: 1px solid var(--border-subtle, #f1f5f9);
    padding-top: 14px;
}
[data-theme="dark"] .launchpad-card-footer {
    border-color: #1e293d !important;
    color: #60a5fa !important;
}

/* STEPPER PROGRESS TRACK (LAW #3 & LAW #2) */
.stepper-container {
    background: var(--card-bg, #ffffff);
    border: 1px solid var(--border-subtle, #e2e8f0);
    border-radius: 20px;
    padding: 28px;
    margin-bottom: 28px;
}
[data-theme="dark"] .stepper-container {
    background: #131b2e !important;
    border-color: #23324d !important;
}

.stepper-track {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    position: relative;
    margin-top: 20px;
}
@media (max-width: 860px) {
    .stepper-track { grid-template-columns: 1fr; }
}

.step-card {
    background: var(--bg-main, #f8fafc);
    border: 1px solid var(--border-subtle, #e2e8f0);
    border-radius: 14px;
    padding: 18px 20px;
    position: relative;
    transition: transform 0.2s ease, border-color 0.2s ease;
}
.step-card:hover {
    transform: translateY(-2px);
    border-color: #3b82f6;
}
[data-theme="dark"] .step-card {
    background: #1a253c !important;
    border-color: #2e3f60 !important;
}

.step-number-badge {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    display: inline-grid;
    place-items: center;
    font-size: 13px;
    font-weight: 800;
    margin-bottom: 10px;
}

/* QUICK SEARCH WIDGET (LAW #1 INPUT MASKS & LAW #7 SEARCHABLE DROPDOWN) */
.quick-search-box {
    background: var(--card-bg, #ffffff);
    border: 1px solid var(--border-subtle, #e2e8f0);
    border-radius: 20px;
    padding: 24px 28px;
    margin-bottom: 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 20px;
}
[data-theme="dark"] .quick-search-box {
    background: #131b2e !important;
    border-color: #23324d !important;
}

.search-input-mask-wrap {
    display: flex;
    align-items: center;
    gap: 10px;
    background: var(--bg-main, #f8fafc);
    border: 1.5px solid var(--border-color, #cbd5e1);
    border-radius: 12px;
    padding: 8px 16px;
    flex: 1;
    min-width: 280px;
    max-width: 520px;
}
[data-theme="dark"] .search-input-mask-wrap {
    background: #0f172a !important;
    border-color: #2a3a5a !important;
}
.search-input-mask-wrap input {
    border: none;
    outline: none;
    background: transparent;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-heading, #0f172a);
    width: 100%;
}
[data-theme="dark"] .search-input-mask-wrap input {
    color: #f8fafc !important;
}

/* HANDBOOK BOX & CARDS */
.handbook-box {
    background: var(--card-bg, #ffffff);
    border: 1px solid var(--border-subtle, #e2e8f0);
    border-radius: 20px;
    padding: 28px;
    margin-bottom: 28px;
}
[data-theme="dark"] .handbook-box {
    background: #131b2e !important;
    border-color: #23324d !important;
}
.handbook-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 16px;
}
.handbook-card {
    border-radius: 14px;
    padding: 16px 18px;
    border: 1px solid transparent;
}
.handbook-card b {
    font-size: 14px;
    display: block;
    margin-bottom: 6px;
    font-weight: 800;
    letter-spacing: -0.01em;
}
.handbook-card p {
    font-size: 13px;
    margin: 0;
    line-height: 1.6;
}
.handbook-card.card-tot { background: #f0fdf4; border-color: #bbf7d0; }
.handbook-card.card-tot b { color: #15803d; }
.handbook-card.card-tot p { color: #166534; }

.handbook-card.card-kha { background: #eff6ff; border-color: #bfdbfe; }
.handbook-card.card-kha b { color: #1d4ed8; }
.handbook-card.card-kha p { color: #1e40af; }

.handbook-card.card-dat { background: #fffbeb; border-color: #fde68a; }
.handbook-card.card-dat b { color: #b45309; }
.handbook-card.card-dat p { color: #92400e; }

.handbook-card.card-chuadat { background: #fef2f2; border-color: #fecaca; }
.handbook-card.card-chuadat b { color: #dc2626; }
.handbook-card.card-chuadat p { color: #b91c1c; }

[data-theme="dark"] .handbook-card.card-tot { background: rgba(16, 185, 129, 0.12) !important; border-color: rgba(16, 185, 129, 0.35) !important; }
[data-theme="dark"] .handbook-card.card-tot b { color: #34d399 !important; }
[data-theme="dark"] .handbook-card.card-tot p { color: #a7f3d0 !important; }

[data-theme="dark"] .handbook-card.card-kha { background: rgba(59, 130, 246, 0.12) !important; border-color: rgba(59, 130, 246, 0.35) !important; }
[data-theme="dark"] .handbook-card.card-kha b { color: #60a5fa !important; }
[data-theme="dark"] .handbook-card.card-kha p { color: #bfdbfe !important; }

[data-theme="dark"] .handbook-card.card-dat { background: rgba(245, 158, 11, 0.12) !important; border-color: rgba(245, 158, 11, 0.35) !important; }
[data-theme="dark"] .handbook-card.card-dat b { color: #fbbf24 !important; }
[data-theme="dark"] .handbook-card.card-dat p { color: #fde68a !important; }

[data-theme="dark"] .handbook-card.card-chuadat { background: rgba(239, 68, 68, 0.12) !important; border-color: rgba(239, 68, 68, 0.35) !important; }
[data-theme="dark"] .handbook-card.card-chuadat b { color: #f87171 !important; }
[data-theme="dark"] .handbook-card.card-chuadat p { color: #fecaca !important; }

.handbook-badge {
    background: #eff6ff;
    color: #2563eb;
    border: 1px solid #bfdbfe;
}
[data-theme="dark"] .handbook-badge {
    background: rgba(59, 130, 246, 0.18) !important;
    color: #93c5fd !important;
    border-color: rgba(59, 130, 246, 0.4) !important;
}
</style>

<!-- 1. HERO SHOWCASE BANNER -->
<section class="welcome-hero">
    <div class="welcome-badge">
        <span>📅 NĂM HỌC <?= htmlspecialchars($welcomeCalendar['school_year']) ?> &bull; <?= htmlspecialchars($welcomeCalendar['semester_name']) ?></span>
        <span>&bull;</span>
        <span>EDURADAR THCS</span>
    </div>
    <h1 class="welcome-title">
        Nền Tảng Cảnh Báo Sớm Điểm Số & Động Năng Học Tập Học Sinh THCS
    </h1>
    <p class="welcome-desc">
        Chào mừng <b><?= htmlspecialchars($userName) ?></b> (<i><?= htmlspecialchars($userRoleTitle) ?></i>) đến với hệ thống. EduRadar kết hợp phân tích <b>2 Khung Đánh Giá</b>: nhận diện biến thiên điểm số thường xuyên (ĐĐGtx) và tự động chuẩn đoán nguy cơ theo <b>Thông tư 22/2021/TT-BGDĐT</b>, sẵn sàng số hóa toàn diện học bạ và báo cáo Ban Giám Hiệu.
    </p>

    <div class="welcome-cta-group">
        <a href="index.php" class="btn primary" style="background: #2563eb; color: #ffffff; padding: 12px 24px; border-radius: 12px; font-weight: 700; font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3);">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></svg>
            <span>Vào Cảnh Báo Sớm</span>
        </a>
        <a href="nhap_diem.php" class="btn" style="background: var(--card-bg, #ffffff); color: var(--text-heading, #0f172a); border: 1.5px solid var(--border-color, #cbd5e1); padding: 11px 22px; border-radius: 12px; font-weight: 700; font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="4" width="18" height="16" rx="3"/><path d="M3 10h18M8 4v16M14 4v16"/></svg>
            <span>Mở Sổ Điểm VnEdu</span>
        </a>
        <a href="bao_cao_slide_bgh.php" target="_blank" class="btn" style="background: var(--card-bg, #ffffff); color: var(--text-heading, #0f172a); border: 1.5px solid var(--border-color, #cbd5e1); padding: 11px 22px; border-radius: 12px; font-weight: 700; font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
            <span>Slide Báo Cáo BGH</span>
        </a>
    </div>
</section>

<!-- 2. TOÀN TRƯỜNG KPI SUMMARY CARDS (BENTO PRO) -->
<div class="welcome-kpi-grid">
    <div class="kpi-card">
        <div class="kpi-icon-box" style="background: #eff6ff; color: #2563eb;">👥</div>
        <div>
            <span style="font-size: 11.5px; font-weight: 800; color: var(--text-subtle, #64748b); text-transform: uppercase;">TỔNG HỌC SINH</span>
            <div style="font-size: 26px; font-weight: 800; color: var(--text-heading, #0f172a); font-family: 'JetBrains Mono', monospace; margin: 2px 0;">
                <?= number_format($totalStudents) ?>
            </div>
            <span style="font-size: 12px; color: #10b981; font-weight: 700;">Trên toàn bộ <?= $totalClasses ?> lớp</span>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon-box" style="background: #ecfdf5; color: #059669;">⭐</div>
        <div>
            <span style="font-size: 11.5px; font-weight: 800; color: var(--text-subtle, #64748b); text-transform: uppercase;">TỶ LỆ GIỎI & KHÁ</span>
            <div style="font-size: 26px; font-weight: 800; color: #059669; font-family: 'JetBrains Mono', monospace; margin: 2px 0;">
                <?= $pctGood ?>%
            </div>
            <span style="font-size: 12px; color: var(--text-subtle, #64748b);">Tổng hợp TT22</span>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon-box" style="background: #fffbeb; color: #d97706;">⚡</div>
        <div>
            <span style="font-size: 11.5px; font-weight: 800; color: var(--text-subtle, #64748b); text-transform: uppercase;">BÀI ĐÁNH GIÁ ĐÃ NẠP</span>
            <div style="font-size: 26px; font-weight: 800; color: var(--text-heading, #0f172a); font-family: 'JetBrains Mono', monospace; margin: 2px 0;">
                <?= number_format($totalScores) ?>
            </div>
            <span style="font-size: 12px; color: #3b82f6; font-weight: 700;">Hệ CSDL MariaDB</span>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon-box" style="background: #fef2f2; color: #dc2626;">🚨</div>
        <div>
            <span style="font-size: 11.5px; font-weight: 800; color: var(--text-subtle, #64748b); text-transform: uppercase;">CẦN PHỤ ĐẠO SỚM</span>
            <div style="font-size: 26px; font-weight: 800; color: #dc2626; font-family: 'JetBrains Mono', monospace; margin: 2px 0;">
                <?= $pctLow ?>%
            </div>
            <span style="font-size: 12px; color: #dc2626; font-weight: 700;">Khoanh vùng hỗ trợ</span>
        </div>
    </div>
</div>

<!-- 3. CỔNG CHỨC NĂNG CỐT LÕI (CORE PORTALS) -->
<div style="margin-bottom: 14px;">
    <h2 style="font-size: 20px; font-weight: 800; color: var(--text-heading, #0f172a); letter-spacing: -0.02em; margin-bottom: 4px;">
        🎯 Cổng Chức Năng Cốt Lõi (Core Portals)
    </h2>
    <p style="font-size: 13.5px; color: var(--text-muted, #64748b); margin: 0 0 16px 0;">
        Truy cập nhanh vào các phân hệ nghiệp vụ chính của hệ thống EduRadar THCS.
    </p>
</div>

<div class="launchpad-grid">
    <!-- CỔNG 1: CẢNH BÁO SỚM -->
    <a href="index.php" class="launchpad-card">
        <div class="launchpad-card-header">
            <span style="font-size: 28px;">📡</span>
            <span style="font-size: 11.5px; font-weight: 800; padding: 4px 10px; border-radius: 999px; background: rgba(37, 99, 235, 0.12); color: #2563eb; border: 1px solid rgba(37, 99, 235, 0.25);">Chính Thức</span>
        </div>
        <div class="launchpad-card-title">Cảnh Báo Sớm 2 Khung</div>
        <div class="launchpad-card-desc">
            Phân tích tự động biến thiên động năng học tập (Tiến bộ · Duy trì · Sa sút) kết hợp phân tầng Thông tư 22. Tự động đề xuất lộ trình sư phạm cho từng học sinh.
        </div>
        <div class="launchpad-card-footer">
            <span>Mở Bảng Cảnh Báo Sớm</span>
            <span>→</span>
        </div>
    </a>

    <!-- CỔNG 2: SỔ ĐIỂM ĐIỆN TỬ VNEDU -->
    <a href="nhap_diem.php" class="launchpad-card">
        <div class="launchpad-card-header">
            <span style="font-size: 28px;">📋</span>
            <span style="font-size: 11.5px; font-weight: 800; padding: 4px 10px; border-radius: 999px; background: rgba(5, 150, 105, 0.12); color: #059669; border: 1px solid rgba(5, 150, 105, 0.25);">Chuẩn VnEdu</span>
        </div>
        <div class="launchpad-card-title">Sổ Điểm Điện Tử VnEdu</div>
        <div class="launchpad-card-desc">
            Ma trận điểm số ĐĐGtx (1–4), Giữa kỳ (HS 2), Cuối kỳ (HS 3). Hỗ trợ điều hướng bàn phím mượt mà, tự tính ĐTBM và lưu bất đồng bộ AJAX tức thì.
        </div>
        <div class="launchpad-card-footer">
            <span>Mở Sổ Điểm Bộ Môn</span>
            <span>→</span>
        </div>
    </a>

    <!-- CỔNG 3: QUÉT NHANH AI OCR -->
    <a href="quet_nhanh.php" class="launchpad-card">
        <div class="launchpad-card-header">
            <span style="font-size: 28px;">⚡</span>
            <span style="font-size: 11.5px; font-weight: 800; padding: 4px 10px; border-radius: 999px; background: rgba(217, 119, 6, 0.12); color: #d97706; border: 1px solid rgba(217, 119, 6, 0.25);">Quét 1 Chạm</span>
        </div>
        <div class="launchpad-card-title">Quét Nhanh Bảng Điểm AI</div>
        <div class="launchpad-card-desc">
            Dành cho giáo viên muốn kiểm tra nhanh bảng điểm lớp ngoài hoặc khách dùng thử: nạp tệp Excel, CSV, hoặc ảnh bảng điểm và nhận báo cáo ngay trong 3 giây.
        </div>
        <div class="launchpad-card-footer">
            <span>Thử Nghiệm Quét Nhanh</span>
            <span>→</span>
        </div>
    </a>

    <!-- CỔNG 4: SLIDE BÁO CÁO & THỐNG KÊ BGH -->
    <a href="bao_cao_slide_bgh.php" target="_blank" class="launchpad-card">
        <div class="launchpad-card-header">
            <span style="font-size: 28px;">📊</span>
            <span style="font-size: 11.5px; font-weight: 800; padding: 4px 10px; border-radius: 999px; background: rgba(219, 39, 119, 0.12); color: #db2777; border: 1px solid rgba(219, 39, 119, 0.25);">Trực Quan</span>
        </div>
        <div class="launchpad-card-title">Báo Cáo & Thống Kê BGH</div>
        <div class="launchpad-card-desc">
            Hệ thống Slide thuyết trình HTML tương tác với đồ họa Chart.js sắc nét, phục vụ trình chiếu trong các cuộc họp giao ban, thanh tra hoặc sơ kết học kỳ.
        </div>
        <div class="launchpad-card-footer">
            <span>Trình Chiếu Slide BGH</span>
            <span>→</span>
        </div>
    </a>

    <?php if ($userRole === 'admin'): ?>
    <!-- CỔNG 5: QUẢN TRỊ ADMIN -->
    <a href="admin.php" class="launchpad-card">
        <div class="launchpad-card-header">
            <span style="font-size: 28px;">🛡️</span>
            <span style="font-size: 11.5px; font-weight: 800; padding: 4px 10px; border-radius: 999px; background: rgba(126, 34, 206, 0.12); color: #7e22ce; border: 1px solid rgba(126, 34, 206, 0.25);">Quản Trị</span>
        </div>
        <div class="launchpad-card-title">Cổng Quản Trị Nhà Trường</div>
        <div class="launchpad-card-desc">
            Phân công chuyên môn giáo viên theo lớp & môn học, quản lý tài khoản, nạp danh sách học sinh toàn trường và theo dõi nhật ký kiểm toán hệ thống.
        </div>
        <div class="launchpad-card-footer">
            <span>Cấu Hình & Quản Trị</span>
            <span>→</span>
        </div>
    </a>
    <?php endif; ?>
</div>

<!-- 4. STEPPER PROGRESS HƯỚNG DẪN 3 BƯỚC (LAW #3 MULTI-STEP PROGRESS & LAW #2 LAW OF PROXIMITY) -->
<div class="stepper-container" style="border-left: 4px solid var(--primary, #2563eb);">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 8px;">
        <div>
            <h3 style="font-size: 18px; font-weight: 800; color: var(--text-heading, #0f172a); margin: 0 0 4px 0;">
                🧭 Quy Trình Sư Phạm 3 Bước Nhanh (EduRadar Workflow)
            </h3>
            <p style="font-size: 13px; color: var(--text-muted, #64748b); margin: 0;">
                Quy chuẩn thao tác tinh gọn giúp giáo viên hoàn tất báo cáo và đồng hành cùng học sinh trong 5 phút.
            </p>
        </div>
        <span class="badge handbook-badge" style="font-weight: 700; padding: 6px 12px; border-radius: 999px;">
            Dành Cho Giáo Viên & Ban Giám Hiệu
        </span>
    </div>

    <div class="stepper-track">
        <!-- BƯỚC 1 -->
        <div class="step-card">
            <div class="step-number-badge" style="background: rgba(37, 99, 235, 0.15); color: #2563eb;">1</div>
            <b style="font-size: 14px; color: var(--text-heading); display: block; margin-bottom: 6px;">Bước 1: Chọn Lớp & Nhập Điểm</b>
            <p style="font-size: 12.5px; color: var(--text-muted); margin: 0 0 12px 0; line-height: 1.55;">
                Vào <b>Sổ Điểm</b>, chọn lớp và môn được phân công. Nhập trực tiếp điểm ĐĐGtx (1–4), Giữa kỳ, Cuối kỳ bằng bàn phím hoặc nạp file Excel.
            </p>
            <a href="nhap_diem.php" style="font-size: 12px; font-weight: 700; color: #2563eb; text-decoration: none;">Vào Sổ Điểm →</a>
        </div>

        <!-- BƯỚC 2 -->
        <div class="step-card">
            <div class="step-number-badge" style="background: rgba(16, 185, 129, 0.15); color: #059669;">2</div>
            <b style="font-size: 14px; color: var(--text-heading); display: block; margin-bottom: 6px;">Bước 2: Theo Dõi Cảnh Báo Sớm</b>
            <p style="font-size: 12.5px; color: var(--text-muted); margin: 0 0 12px 0; line-height: 1.55;">
                Vào <b>Cảnh Báo Sớm</b>, hệ thống tự động phân tích: <i>Khung 1 (Hiệu suất động năng)</i> và <i>Khung 2 (Bậc điểm TT22)</i>. Bấm <b>Chi tiết</b> để ghi nhận xét sư phạm.
            </p>
            <a href="index.php" style="font-size: 12px; font-weight: 700; color: #059669; text-decoration: none;">Xem Cảnh Báo Sớm →</a>
        </div>

        <!-- BƯỚC 3 -->
        <div class="step-card">
            <div class="step-number-badge" style="background: rgba(245, 158, 11, 0.15); color: #d97706;">3</div>
            <b style="font-size: 14px; color: var(--text-heading); display: block; margin-bottom: 6px;">Bước 3: Xuất Báo Cáo Ban Giám Hiệu</b>
            <p style="font-size: 12.5px; color: var(--text-muted); margin: 0 0 12px 0; line-height: 1.55;">
                Mở <b>Báo Cáo & Thống Kê</b> để trình chiếu Slide thuyết trình Chart.js hoặc tải báo cáo học tập định dạng PDF / Excel nộp nhà trường và gửi phụ huynh.
            </p>
            <a href="bao_cao_slide_bgh.php" target="_blank" style="font-size: 12px; font-weight: 700; color: #d97706; text-decoration: none;">Trình Chiếu Slide →</a>
        </div>
    </div>
</div>

<!-- 5. TÌM KIẾM NHANH HỌC SINH VỚI INPUT MASK (LAW #1 INPUT MASKS & LAW #7 SEARCHABLE DROPDOWN) -->
<div class="quick-search-box">
    <div>
        <div style="font-size: 15px; font-weight: 800; color: var(--text-heading); margin-bottom: 4px;">
            🔍 Tra Cứu Nhanh Hồ Sơ Học Sinh Toàn Trường
        </div>
        <div style="font-size: 12.5px; color: var(--text-muted);">
            Định dạng mã HS chuẩn: <code>2404XXXXXX</code> hoặc gõ họ tên học sinh để mở bảng điểm ngay lập tức.
        </div>
    </div>

    <form method="GET" action="index.php" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
        <div class="search-input-mask-wrap">
            <span>🔎</span>
            <input type="text" name="search" id="quickStudentSearchInput" placeholder="Ví dụ: 2404815683 hoặc Trần Lê Ngọc..." aria-label="Mã học sinh hoặc tên học sinh">
        </div>
        <button type="submit" class="btn primary" style="background: #2563eb; color: #ffffff; padding: 9px 18px; border-radius: 12px; font-weight: 700; font-size: 13.5px; border: none; cursor: pointer;">
            Tìm Kiếm
        </button>
    </form>
</div>

<!-- 6. CẨM NANG THÔNG TƯ 22 (TT22 QUICK REFERENCE) -->
<div class="handbook-box">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 18px;">
        <div>
            <h3 style="font-size: 18px; font-weight: 800; color: var(--text-heading, #0f172a); margin: 0 0 4px 0;">
                📖 Cẩm Nang Sư Phạm: Quy Chuẩn Thông Tư 22/2021/TT-BGDĐT
            </h3>
            <p style="font-size: 13px; color: var(--text-muted, #64748b); margin: 0;">
                Quy định đánh giá học sinh THCS theo phương pháp kết hợp nhận xét và điểm số.
            </p>
        </div>
        <span class="badge handbook-badge" style="font-weight: 700; padding: 6px 12px; border-radius: 999px;">
            Áp dụng Khối 6, 7, 8, 9
        </span>
    </div>

    <div class="handbook-grid">
        <div class="handbook-card card-tot">
            <b>1. MỨC TỐT (HOÀN THÀNH TỐT)</b>
            <p>
                • ĐTBMhk ≥ 8.0<br>
                • Không có môn học nào có ĐTBMhk dưới 6.5<br>
                • Các môn đánh giá nhận xét: Đạt mức Đạt.
            </p>
        </div>
        <div class="handbook-card card-kha">
            <b>2. MỨC KHÁ</b>
            <p>
                • ĐTBMhk ≥ 6.5 (chưa đạt Tốt)<br>
                • Không có môn học nào có ĐTBMhk dưới 5.0<br>
                • Các môn đánh giá nhận xét: Đạt mức Đạt.
            </p>
        </div>
        <div class="handbook-card card-dat">
            <b>3. MỨC ĐẠT (TRUNG BÌNH)</b>
            <p>
                • ĐTBMhk ≥ 5.0 (chưa đạt Khá)<br>
                • Không có môn học nào có ĐTBMhk dưới 3.5<br>
                • Tối đa 1 môn nhận xét xếp loại Chưa Đạt.
            </p>
        </div>
        <div class="handbook-card card-chuadat">
            <b>4. CHƯA ĐẠT (YẾU / CẦN PHỤ ĐẠO)</b>
            <p>
                • Các trường hợp còn lại<br>
                • Học sinh có ĐTBMhk &lt; 3.5 hoặc thi cuối kỳ bị điểm liệt &lt; 3.0 cần được đưa ngay vào diện theo dõi radar.
            </p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
