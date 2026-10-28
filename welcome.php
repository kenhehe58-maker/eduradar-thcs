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
$totalScores = (int)$pdo->query("SELECT COUNT(*) FROM scores WHERE avg_score IS NOT NULL OR gk IS NOT NULL OR tx1 IS NOT NULL")->fetchColumn();

// Thống kê phân tầng điểm toàn trường
$scoreStats = $pdo->query("
    SELECT 
        COUNT(CASE WHEN avg_score >= 8.0 THEN 1 END) as excellent_count,
        COUNT(CASE WHEN avg_score >= 6.5 AND avg_score < 8.0 THEN 1 END) as good_count,
        COUNT(CASE WHEN avg_score >= 5.0 AND avg_score < 6.5 THEN 1 END) as average_count,
        COUNT(CASE WHEN (avg_score < 5.0 AND avg_score IS NOT NULL) OR (gk < 5.0 AND gk IS NOT NULL) THEN 1 END) as low_count,
        COUNT(CASE WHEN avg_score IS NOT NULL OR gk IS NOT NULL THEN 1 END) as total_evaluated
    FROM scores 
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
    font-weight: 700;
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

/* MASKED TEXT REVEAL: Chữ luôn hiển thị an toàn làm fallback, GSAP trượt lên tự nhiên */
.masked-text-title {
    margin: 0 0 14px 0;
}
.masked-line {
    display: block;
    overflow: hidden;
    padding-bottom: 4px;
}
.masked-line-inner {
    display: inline-block;
    will-change: transform;
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

@media (max-width: 768px) {
    .welcome-hero {
        padding: 22px 18px !important;
        border-radius: 18px !important;
        margin-bottom: 18px !important;
    }
    .welcome-badge {
        font-size: 11px !important;
        padding: 4px 10px !important;
        margin-bottom: 10px !important;
    }
    .welcome-title {
        font-size: 20px !important;
        line-height: 1.3 !important;
        margin-bottom: 8px !important;
    }
    .welcome-desc {
        font-size: 13px !important;
        line-height: 1.55 !important;
        margin-bottom: 16px !important;
    }
    .welcome-cta-group {
        display: grid !important;
        grid-template-columns: 1fr !important;
        width: 100% !important;
        gap: 8px !important;
    }
    .welcome-cta-group .btn {
        width: 100% !important;
        justify-content: center !important;
        padding: 12px 16px !important;
        box-sizing: border-box !important;
    }
    /* Mobile 2x2 Clean Bento KPI Grid */
    .welcome-kpi-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 10px !important;
        margin-bottom: 20px !important;
    }
    .kpi-card {
        padding: 14px 12px !important;
        border-radius: 14px !important;
        gap: 10px !important;
    }
    .kpi-icon-box {
        width: 38px !important;
        height: 38px !important;
        border-radius: 10px !important;
        font-size: 18px !important;
    }
    .kpi-card span[style*="font-size: 11.5px"] {
        font-size: 10px !important;
        display: block !important;
        line-height: 1.2 !important;
    }
    .kpi-card div[style*="font-size: 26px"] {
        font-size: 20px !important;
        margin: 2px 0 !important;
    }
    .kpi-card span[style*="font-size: 12px"] {
        font-size: 11px !important;
        display: block !important;
    }
    .launchpad-grid {
        grid-template-columns: 1fr !important;
        gap: 12px !important;
    }
    .launchpad-card {
        padding: 18px 16px !important;
        border-radius: 16px !important;
    }
    .stepper-container {
        padding: 18px 16px !important;
        border-radius: 16px !important;
        margin-bottom: 20px !important;
    }
    .quick-search-box {
        padding: 18px 16px !important;
        border-radius: 16px !important;
    }
    .handbook-box {
        padding: 18px 16px !important;
        border-radius: 16px !important;
    }
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

.handbook-card.card-kha { background: #fefce8; border-color: #fef08a; }
.handbook-card.card-kha b { color: #b45309; }
.handbook-card.card-kha p { color: #92400e; }

.handbook-card.card-dat { background: #fff7ed; border-color: #fed7aa; }
.handbook-card.card-dat b { color: #c2410c; }
.handbook-card.card-dat p { color: #9a3412; }

.handbook-card.card-chuadat { background: #fef2f2; border-color: #fecaca; }
.handbook-card.card-chuadat b { color: #dc2626; }
.handbook-card.card-chuadat p { color: #b91c1c; }

[data-theme="dark"] .handbook-card.card-tot { background: rgba(16, 185, 129, 0.12) !important; border-color: rgba(16, 185, 129, 0.35) !important; }
[data-theme="dark"] .handbook-card.card-tot b { color: #34d399 !important; }
[data-theme="dark"] .handbook-card.card-tot p { color: #a7f3d0 !important; }

[data-theme="dark"] .handbook-card.card-kha { background: rgba(234, 179, 8, 0.12) !important; border-color: rgba(234, 179, 8, 0.35) !important; }
[data-theme="dark"] .handbook-card.card-kha b { color: #facc15 !important; }
[data-theme="dark"] .handbook-card.card-kha p { color: #fef08a !important; }

[data-theme="dark"] .handbook-card.card-dat { background: rgba(249, 115, 22, 0.12) !important; border-color: rgba(249, 115, 22, 0.35) !important; }
[data-theme="dark"] .handbook-card.card-dat b { color: #fb923c !important; }
[data-theme="dark"] .handbook-card.card-dat p { color: #fed7aa !important; }

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
<section class="welcome-hero fade-in-item">
    <div class="welcome-badge">
        <span>Năm học <?= htmlspecialchars($welcomeCalendar['school_year']) ?> &bull; <?= htmlspecialchars($welcomeCalendar['semester_name']) ?></span>
        <span>&bull;</span>
        <span>EduRadar THCS</span>
    </div>
    <h1 class="welcome-title masked-text-title" aria-label="Theo dõi tiến trình & Cảnh báo sớm học lực THCS">
        <div class="masked-line"><span class="masked-line-inner">Theo dõi tiến trình &amp;</span></div>
        <div class="masked-line"><span class="masked-line-inner">Cảnh báo sớm học lực THCS</span></div>
    </h1>
    <p class="welcome-desc">
        Hệ thống tự động phân tích biến thiên điểm số thường xuyên (ĐĐGtx) theo <b>Thông tư 22/2021/TT-BGDĐT</b>, hỗ trợ giáo viên bộ môn và Ban Giám hiệu nắm bắt kịp thời nguy cơ sa sút để phối hợp can thiệp sớm.
    </p>

    <div class="welcome-cta-group">
        <a href="index.php" class="btn primary" style="background: #2563eb; color: #ffffff; padding: 12px 24px; border-radius: 12px; font-weight: 700; font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3);">
            <?= get_svg_icon('radar', 18) ?>
            <span>Xem bảng cảnh báo</span>
        </a>
        <a href="nhap_diem.php" class="btn" style="background: var(--card-bg, #ffffff); color: var(--text-heading, #0f172a); border: 1.5px solid var(--border-color, #cbd5e1); padding: 11px 22px; border-radius: 12px; font-weight: 700; font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
            <?= get_svg_icon('file-text', 18) ?>
            <span>Sổ điểm bộ môn</span>
        </a>
    </div>
</section>

<!-- 2. TOÀN TRƯỜNG KPI SUMMARY CARDS (BENTO PRO) -->
<div class="welcome-kpi-grid">
    <div class="kpi-card fade-in-item">
        <div class="kpi-icon-box" style="background: #eff6ff; color: #2563eb;"><?= get_svg_icon('users', 20) ?></div>
        <div>
            <span style="font-size: 11.5px; font-weight: 700; color: var(--text-subtle, #64748b);">Tổng học sinh</span>
            <div style="font-size: 26px; font-weight: 800; color: var(--text-heading, #0f172a); font-family: 'JetBrains Mono', monospace; margin: 2px 0;">
                <?= number_format($totalStudents) ?>
            </div>
            <span style="font-size: 12px; color: #10b981; font-weight: 600;">Toàn trường <?= $totalClasses ?> lớp</span>
        </div>
    </div>
    <div class="kpi-card fade-in-item">
        <div class="kpi-icon-box" style="background: #ecfdf5; color: #059669;"><?= get_svg_icon('award', 20) ?></div>
        <div>
            <span style="font-size: 11.5px; font-weight: 700; color: var(--text-subtle, #64748b);">Tỷ lệ Khá &amp; Tốt</span>
            <div style="font-size: 26px; font-weight: 800; color: #059669; font-family: 'JetBrains Mono', monospace; margin: 2px 0;">
                <?= $pctGood ?>%
            </div>
            <span style="font-size: 12px; color: var(--text-subtle, #64748b);">Theo chuẩn TT22</span>
        </div>
    </div>
    <div class="kpi-card fade-in-item">
        <div class="kpi-icon-box" style="background: #fffbeb; color: #d97706;"><?= get_svg_icon('activity', 20) ?></div>
        <div>
            <span style="font-size: 11.5px; font-weight: 700; color: var(--text-subtle, #64748b);">Cột điểm đã nhập</span>
            <div style="font-size: 26px; font-weight: 800; color: var(--text-heading, #0f172a); font-family: 'JetBrains Mono', monospace; margin: 2px 0;">
                <?= number_format($totalScores) ?>
            </div>
            <span style="font-size: 12px; color: #3b82f6; font-weight: 600;">Dữ liệu Học kỳ I</span>
        </div>
    </div>
    <div class="kpi-card fade-in-item">
        <div class="kpi-icon-box" style="background: #fef2f2; color: #dc2626;"><?= get_svg_icon('alert-triangle', 20) ?></div>
        <div>
            <span style="font-size: 11.5px; font-weight: 700; color: var(--text-subtle, #64748b);">Cần lưu ý hỗ trợ</span>
            <div style="font-size: 26px; font-weight: 800; color: #dc2626; font-family: 'JetBrains Mono', monospace; margin: 2px 0;">
                <?= $pctLow ?>%
            </div>
            <span style="font-size: 12px; color: #dc2626; font-weight: 600;">Khoanh vùng theo dõi</span>
        </div>
    </div>
</div>

<!-- 3. PHÂN HỆ LÀM VIỆC -->
<div style="margin-bottom: 14px;">
    <h2 style="font-size: 20px; font-weight: 800; color: var(--text-heading, #0f172a); letter-spacing: -0.02em; margin-bottom: 4px; display: flex; align-items: center; gap: 8px;">
        <?= get_svg_icon('radar', 22, '', 'color: #2563eb;') ?>
        <span>Phân hệ làm việc</span>
    </h2>
    <p style="font-size: 13.5px; color: var(--text-muted, #64748b); margin: 0 0 16px 0;">
        Truy cập nhanh các chức năng nghiệp vụ của hệ thống EduRadar.
    </p>
</div>

<div class="launchpad-grid">
    <!-- PHÂN HỆ: CẢNH BÁO SỚM -->
    <a href="index.php" class="launchpad-card fade-in-item">
        <div class="launchpad-card-header">
            <div style="color: #2563eb;"><?= get_svg_icon('activity', 24) ?></div>
            <span style="font-size: 11.5px; font-weight: 700; padding: 4px 10px; border-radius: 999px; background: rgba(37, 99, 235, 0.1); color: #2563eb;">Trọng tâm</span>
        </div>
        <div class="launchpad-card-title">Cảnh báo sớm & Xu hướng</div>
        <div class="launchpad-card-desc">
            Phân tích tự động quỹ đạo tiến bộ học tập (Tiến bộ · Duy trì · Sa sút) kết hợp phân tầng Thông tư 22, tự động gợi ý định hướng sư phạm cho từng học sinh.
        </div>
        <div class="launchpad-card-footer">
            <span>Mở bảng cảnh báo sớm</span>
            <?= get_svg_icon('chevron-right', 16) ?>
        </div>
    </a>

    <!-- PHÂN HỆ: SỔ ĐIỂM BỘ MÔN -->
    <a href="nhap_diem.php" class="launchpad-card fade-in-item">
        <div class="launchpad-card-header">
            <div style="color: #059669;"><?= get_svg_icon('file-text', 24) ?></div>
            <span style="font-size: 11.5px; font-weight: 700; padding: 4px 10px; border-radius: 999px; background: rgba(5, 150, 105, 0.1); color: #059669;">Sổ điểm</span>
        </div>
        <div class="launchpad-card-title">Sổ điểm điện tử bộ môn</div>
        <div class="launchpad-card-desc">
            Ma trận điểm số ĐĐGtx (1–4), Giữa kỳ (HS 2), Cuối kỳ (HS 3). Hỗ trợ điều hướng phím mũi tên nhanh, tự tính điểm trung bình và lưu tự động.
        </div>
        <div class="launchpad-card-footer">
            <span>Mở sổ điểm bộ môn</span>
            <?= get_svg_icon('chevron-right', 16) ?>
        </div>
    </a>

    <!-- PHÂN HỆ: QUÉT NHANH BẢNG ĐIỂM -->
    <a href="quet_nhanh.php" class="launchpad-card fade-in-item">
        <div class="launchpad-card-header">
            <div style="color: #d97706;"><?= get_svg_icon('qr-code', 24) ?></div>
            <span style="font-size: 11.5px; font-weight: 700; padding: 4px 10px; border-radius: 999px; background: rgba(217, 119, 6, 0.1); color: #d97706;">Tiện ích</span>
        </div>
        <div class="launchpad-card-title">Quét nhanh bảng điểm</div>
        <div class="launchpad-card-desc">
            Dành cho giáo viên kiểm tra nhanh lớp ngoài hoặc tài khoản trải nghiệm: tải tệp Excel, CSV hoặc ảnh bảng điểm và nhận báo cáo phân tích ngay lập tức.
        </div>
        <div class="launchpad-card-footer">
            <span>Dùng thử quét nhanh</span>
            <?= get_svg_icon('chevron-right', 16) ?>
        </div>
    </a>

    <?php if ($userRole === 'admin'): ?>
    <!-- PHÂN HỆ: QUẢN TRỊ ADMIN -->
    <a href="admin.php" class="launchpad-card fade-in-item">
        <div class="launchpad-card-header">
            <div style="color: #7e22ce;"><?= get_svg_icon('settings', 24) ?></div>
            <span style="font-size: 11.5px; font-weight: 700; padding: 4px 10px; border-radius: 999px; background: rgba(126, 34, 206, 0.1); color: #7e22ce;">Quản trị</span>
        </div>
        <div class="launchpad-card-title">Quản trị nhà trường</div>
        <div class="launchpad-card-desc">
            Phân công chuyên môn theo lớp và môn học, quản lý tài khoản giáo viên, nhập danh sách học sinh và theo dõi nhật ký hoạt động hệ thống.
        </div>
        <div class="launchpad-card-footer">
            <span>Vào trang quản trị</span>
            <?= get_svg_icon('chevron-right', 16) ?>
        </div>
    </a>
    <?php endif; ?>
</div>

<!-- 4. QUY TRÌNH THEO DÕI HỌC LỰC -->
<div class="stepper-container fade-in-item">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 8px;">
        <div>
            <h3 style="font-size: 18px; font-weight: 800; color: var(--text-heading, #0f172a); margin: 0 0 4px 0; display: flex; align-items: center; gap: 8px;">
                <?= get_svg_icon('check-circle', 20, '', 'color: #2563eb;') ?>
                <span>Quy trình theo dõi học lực</span>
            </h3>
            <p style="font-size: 13px; color: var(--text-muted, #64748b); margin: 0;">
                Quy trình tinh gọn giúp Thầy/Cô hoàn tất theo dõi và hỗ trợ học sinh hiệu quả.
            </p>
        </div>
        <span class="badge handbook-badge" style="font-weight: 600; padding: 5px 12px; border-radius: 999px;">
            Dành cho GV &amp; BGH
        </span>
    </div>

    <div class="stepper-track">
        <!-- BƯỚC 1 -->
        <div class="step-card">
            <div class="step-number-badge" style="background: rgba(37, 99, 235, 0.12); color: #2563eb;">1</div>
            <b style="font-size: 14px; color: var(--text-heading); display: block; margin-bottom: 6px;">Nhập điểm định kỳ</b>
            <p style="font-size: 12.5px; color: var(--text-muted); margin: 0 0 12px 0; line-height: 1.55;">
                Vào sổ điểm, chọn lớp và môn được phân công. Nhập các cột ĐĐGtx (1–4), Giữa kỳ trực tiếp hoặc nạp từ file Excel.
            </p>
            <a href="nhap_diem.php" style="font-size: 12.5px; font-weight: 700; color: #2563eb; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">Vào sổ điểm <?= get_svg_icon('chevron-right', 14) ?></a>
        </div>

        <!-- BƯỚC 2 -->
        <div class="step-card">
            <div class="step-number-badge" style="background: rgba(16, 185, 129, 0.12); color: #059669;">2</div>
            <b style="font-size: 14px; color: var(--text-heading); display: block; margin-bottom: 6px;">Theo dõi xu hướng</b>
            <p style="font-size: 12.5px; color: var(--text-muted); margin: 0 0 12px 0; line-height: 1.55;">
                Xem tự động nhịp độ học tập, khoảng cách giữa các bài kiểm tra và phát hiện sớm nguy cơ sụt giảm điểm số.
            </p>
            <a href="index.php" style="font-size: 12.5px; font-weight: 700; color: #059669; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">Xem bảng cảnh báo <?= get_svg_icon('chevron-right', 14) ?></a>
        </div>

        <!-- BƯỚC 3 -->
        <div class="step-card">
            <div class="step-number-badge" style="background: rgba(245, 158, 11, 0.12); color: #d97706;">3</div>
            <b style="font-size: 14px; color: var(--text-heading); display: block; margin-bottom: 6px;">Kế hoạch hỗ trợ</b>
            <p style="font-size: 12.5px; color: var(--text-muted); margin: 0 0 12px 0; line-height: 1.55;">
                Lưu ghi chú chuyên môn cho từng học sinh và xuất phiếu học tập gửi phụ huynh chỉ với một thao tác.
            </p>
            <a href="index.php" style="font-size: 12.5px; font-weight: 700; color: #d97706; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">Xem kế hoạch hỗ trợ <?= get_svg_icon('chevron-right', 14) ?></a>
        </div>
    </div>
</div>

<!-- 5. TÌM KIẾM NHANH HỌC SINH VỚI INPUT MASK -->
<div class="quick-search-box fade-in-item">
    <div>
        <div style="font-size: 15px; font-weight: 800; color: var(--text-heading); margin-bottom: 4px; display: flex; align-items: center; gap: 8px;">
            <?= get_svg_icon('search', 18, '', 'color: #2563eb;') ?>
            <span>Tra cứu hồ sơ học sinh</span>
        </div>
        <div style="font-size: 12.5px; color: var(--text-muted);">
            Nhập mã học sinh chuẩn (ví dụ: <code>2404XXXXXX</code>) hoặc họ tên để mở bảng điểm ngay.
        </div>
    </div>

    <form method="GET" action="index.php" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
        <div class="search-input-mask-wrap">
            <span style="display: flex; align-items: center;"><?= get_svg_icon('search', 16, '', 'color: #64748b;') ?></span>
            <input type="text" name="search" id="quickStudentSearchInput" placeholder="Ví dụ: 2404815683 hoặc Trần Lê Ngọc..." aria-label="Mã học sinh hoặc tên học sinh">
        </div>
        <button type="submit" class="btn primary" style="background: #2563eb; color: #ffffff; padding: 9px 18px; border-radius: 12px; font-weight: 700; font-size: 13.5px; border: none; cursor: pointer;">
            Tìm kiếm
        </button>
    </form>
</div>

<!-- 6. CẨM NANG THÔNG TƯ 22 (TT22 QUICK REFERENCE) -->
<div class="handbook-box fade-in-item">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 18px;">
        <div>
            <h3 style="font-size: 18px; font-weight: 800; color: var(--text-heading, #0f172a); margin: 0 0 4px 0; display: flex; align-items: center; gap: 8px;">
                <?= get_svg_icon('book-open', 20, '', 'color: #2563eb;') ?>
                <span>Quy chuẩn đánh giá theo Thông tư 22/2021/TT-BGDĐT</span>
            </h3>
            <p style="font-size: 13px; color: var(--text-muted, #64748b); margin: 0;">
                Quy định đánh giá học sinh THCS theo phương pháp kết hợp nhận xét và điểm số.
            </p>
        </div>
        <span class="badge handbook-badge" style="font-weight: 600; padding: 6px 12px; border-radius: 999px;">
            Áp dụng Khối 6, 7, 8, 9
        </span>
    </div>

    <div class="handbook-grid">
        <div class="handbook-card card-tot">
            <b>Mức Tốt</b>
            <p>
                • ĐTBMhk ≥ 8.0<br>
                • Không có môn học nào có ĐTBMhk dưới 6.5<br>
                • Các môn đánh giá nhận xét: Đạt mức Đạt.
            </p>
        </div>
        <div class="handbook-card card-kha">
            <b>Mức Khá</b>
            <p>
                • ĐTBMhk ≥ 6.5 (chưa đạt Tốt)<br>
                • Không có môn học nào có ĐTBMhk dưới 5.0<br>
                • Các môn đánh giá nhận xét: Đạt mức Đạt.
            </p>
        </div>
        <div class="handbook-card card-dat">
            <b>Mức Đạt</b>
            <p>
                • ĐTBMhk ≥ 5.0 (chưa đạt Khá)<br>
                • Không có môn học nào có ĐTBMhk dưới 3.5<br>
                • Tối đa 1 môn nhận xét xếp loại Chưa Đạt.
            </p>
        </div>
        <div class="handbook-card card-chuadat">
            <b>Mức Chưa đạt</b>
            <p>
                • Các trường hợp còn lại<br>
                • Học sinh có ĐTBMhk &lt; 3.5 hoặc thi cuối kỳ dưới 3.0 được hệ thống đưa vào diện cần can thiệp.
            </p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
