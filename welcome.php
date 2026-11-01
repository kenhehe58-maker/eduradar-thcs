<?php
// welcome.php - Trang Chủ & Trung Tâm Khởi Động EduRadar THCS
// Ground-up Redesign: Hallmark v2 Cobalt Theme & Observability Telemetry
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

// Thống kê phân tầng điểm toàn trường theo Thông tư 22
$scoreStats = $pdo->query("
    SELECT 
        COUNT(CASE WHEN avg_score >= 8.0 THEN 1 END) as excellent_count,
        COUNT(CASE WHEN avg_score >= 6.5 AND avg_score < 8.0 THEN 1 END) as good_count,
        COUNT(CASE WHEN avg_score >= 5.0 AND avg_score < 6.5 THEN 1 END) as average_count,
        COUNT(CASE WHEN (avg_score < 5.0 AND avg_score IS NOT NULL) OR (gk < 5.0 AND gk IS NOT NULL) THEN 1 END) as low_count,
        COUNT(CASE WHEN (avg_score < 3.5 AND avg_score IS NOT NULL) OR (ck < 3.0 AND ck IS NOT NULL) THEN 1 END) as red_count,
        COUNT(CASE WHEN ((avg_score >= 3.5 AND avg_score < 5.0) OR (gk < 5.0 AND gk >= 3.5) OR (ck < 5.0 AND ck >= 3.0)) AND NOT ((avg_score < 3.5 AND avg_score IS NOT NULL) OR (ck < 3.0 AND ck IS NOT NULL)) THEN 1 END) as yellow_count,
        COUNT(CASE WHEN avg_score IS NOT NULL OR gk IS NOT NULL THEN 1 END) as total_evaluated,
        ROUND(AVG(avg_score), 2) as school_avg
    FROM scores 
")->fetch();

$totalEval = max(1, (int)($scoreStats['total_evaluated'] ?? 0));
$pctRed = round(((int)($scoreStats['red_count'] ?? 0)) / $totalEval * 100, 1);
$pctYellow = round(((int)($scoreStats['yellow_count'] ?? 0)) / $totalEval * 100, 1);
$pctGood = round(((int)($scoreStats['excellent_count'] ?? 0) + (int)($scoreStats['good_count'] ?? 0)) / $totalEval * 100, 1);
$schoolAvg = $scoreStats['school_avg'] !== null ? number_format((float)$scoreStats['school_avg'], 1) : '--';

$pageTitle = 'Trang Chủ — EduRadar THCS';
$activeNav = 'welcome';
require_once __DIR__ . '/includes/header.php';
?>

<style>
/* =========================================================================
   HALLMARK V2 COBALT DESIGN TOKENS & SURFACES
   Exact, technical register: Cool near-white paper, blue-grey ink,
   hairlines, keycaps with 3D bottom edge, cut-corner panels.
   ========================================================================= */
:root {
    --cobalt-paper: #f8fafc;
    --cobalt-paper-2: #f1f5f9;
    --cobalt-paper-3: #e2e8f0;
    --cobalt-ink: #0f172a;
    --cobalt-ink-2: #334155;
    --cobalt-muted: #64748b;
    --cobalt-rule: rgba(203, 213, 225, 0.85);
    --cobalt-rule-2: #cbd5e1;
    --cobalt-accent: #2563eb;
    --cobalt-accent-2: #3b82f6;
    --cobalt-accent-wash: rgba(37, 99, 235, 0.06);
    --cobalt-graphite: #0f172a;
    --cobalt-graphite-ink: #f8fafc;
    --cobalt-key-face: #ffffff;
    --cobalt-key-edge: #cbd5e1;
    --cobalt-ruby: #dc2626;
    --cobalt-ruby-wash: rgba(220, 38, 38, 0.08);
    --cobalt-amber: #d97706;
    --cobalt-amber-wash: rgba(217, 119, 6, 0.08);
    --cobalt-emerald: #059669;
    --cobalt-emerald-wash: rgba(5, 150, 105, 0.08);
    --cut-radius: 12px;
    --font-display: "Familjen Grotesk", system-ui, -apple-system, sans-serif;
    --font-body: "IBM Plex Sans", "Plus Jakarta Sans", system-ui, sans-serif;
    --font-mono: "JetBrains Mono", ui-monospace, monospace;
}

[data-theme="dark"] {
    --cobalt-paper: #0f172a;
    --cobalt-paper-2: #1e293b;
    --cobalt-paper-3: #334155;
    --cobalt-ink: #f8fafc;
    --cobalt-ink-2: #cbd5e1;
    --cobalt-muted: #94a3b8;
    --cobalt-rule: rgba(51, 65, 85, 0.85);
    --cobalt-rule-2: #334155;
    --cobalt-key-face: #1e293b;
    --cobalt-key-edge: #0f172a;
    --cobalt-accent-wash: rgba(59, 130, 246, 0.12);
}

/* 1. CUT-CORNER PANELS */
.cobalt-cut {
    border: 1px solid var(--cobalt-rule);
    border-radius: var(--cut-radius);
    background: var(--cobalt-paper);
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
@supports (corner-shape: bevel) {
    .cobalt-cut {
        border-radius: 16px 4px 16px 4px;
        corner-shape: bevel round bevel round;
    }
}

/* 2. 3D KEYCAP BUTTONS */
.cobalt-key {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 44px;
    padding: 10px 20px;
    background: var(--cobalt-key-face);
    color: var(--cobalt-ink);
    border: 1px solid var(--cobalt-rule-2);
    border-radius: 6px;
    font-family: var(--font-display);
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    box-shadow: 0 3px 0 0 var(--cobalt-key-edge), 0 5px 8px -3px rgba(15, 23, 42, 0.2);
    transition: transform 0.1s ease-out, box-shadow 0.1s ease-out, background 0.1s ease;
}
.cobalt-key:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 0 0 var(--cobalt-key-edge), 0 7px 10px -3px rgba(15, 23, 42, 0.25);
    text-decoration: none;
}
.cobalt-key:active {
    transform: translateY(3px);
    box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.2);
}
.cobalt-key-primary {
    background: var(--cobalt-accent);
    color: #ffffff !important;
    border-color: #1d4ed8;
    box-shadow: 0 3px 0 0 #1d4ed8, 0 5px 8px -3px rgba(29, 78, 216, 0.4);
}
.cobalt-key-primary:hover {
    background: #1d4ed8;
    box-shadow: 0 4px 0 0 #1e40af, 0 7px 10px -3px rgba(29, 78, 216, 0.45);
}
.cobalt-key-primary:active {
    transform: translateY(3px);
    box-shadow: inset 0 1px 3px rgba(15, 23, 42, 0.4);
}

/* 3. HERO OBSERVABILITY DOCK */
.hero-telemetry-dock {
    position: relative;
    overflow: hidden;
    padding: 36px 36px 32px 36px;
    margin-bottom: 24px;
    background: var(--cobalt-paper);
    border: 1px solid var(--cobalt-rule);
    border-radius: 16px;
}
@supports (corner-shape: bevel) {
    .hero-telemetry-dock {
        border-radius: 20px 4px 20px 4px;
        corner-shape: bevel round bevel round;
    }
}
.hero-telemetry-content {
    position: relative;
    z-index: 2;
}

/* 4. ASCII TELEMETRY FIELD (BACKGROUND CANVASES / GRID) */
.ascii-telemetry-canvas {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    z-index: 1;
    pointer-events: none;
    opacity: 0.05;
}
[data-theme="dark"] .ascii-telemetry-canvas {
    opacity: 0.07;
}

.hero-status-strip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 14px;
    background: var(--cobalt-accent-wash);
    border: 1px solid rgba(37, 99, 235, 0.22);
    border-radius: 999px;
    font-family: var(--font-body);
    font-size: 12.5px;
    font-weight: 700;
    color: var(--cobalt-accent);
    margin-bottom: 16px;
}
.hero-pulse-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--cobalt-accent);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
}

.hero-heading {
    font-family: var(--font-display);
    font-size: clamp(24px, 4.5vw, 40px);
    font-weight: 700;
    line-height: 1.15;
    letter-spacing: -0.025em;
    color: var(--cobalt-ink);
    margin: 0 0 14px 0;
    max-width: 880px;
}
.hero-summary {
    font-family: var(--font-body);
    font-size: 15px;
    line-height: 1.6;
    color: var(--cobalt-ink-2);
    margin: 0 0 24px 0;
    max-width: 820px;
}
.hero-actions-bar {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

/* 5. BENTO GRID KPI METRICS */
.bento-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 28px;
}
@media (max-width: 960px) {
    .bento-kpi-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 520px) {
    .bento-kpi-grid {
        grid-template-columns: 1fr;
    }
}

.bento-kpi-item {
    padding: 18px 20px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 124px;
}
.bento-kpi-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 10px;
}
.bento-kpi-title {
    font-family: var(--font-display);
    font-size: 13px;
    font-weight: 600;
    color: var(--cobalt-muted);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.bento-kpi-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 7px;
    border-radius: 4px;
    font-family: var(--font-mono);
    font-size: 11px;
    font-weight: 600;
}
.bento-kpi-value {
    font-family: var(--font-display);
    font-size: 32px;
    font-weight: 700;
    letter-spacing: -0.03em;
    line-height: 1;
    color: var(--cobalt-ink);
    margin-bottom: 6px;
}
.bento-kpi-sub {
    font-family: var(--font-body);
    font-size: 12.5px;
    color: var(--cobalt-muted);
}

/* 6. LAUNCHPAD GRID (4 PHÂN HỆ ĐIỀU HƯỚNG) */
.launchpad-section-header {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    margin-bottom: 14px;
    flex-wrap: wrap;
    gap: 8px;
}
.launchpad-section-title {
    font-family: var(--font-display);
    font-size: 20px;
    font-weight: 700;
    letter-spacing: -0.02em;
    color: var(--cobalt-ink);
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
}
.launchpad-section-desc {
    font-family: var(--font-body);
    font-size: 13px;
    color: var(--cobalt-muted);
    margin: 0;
}

.launchpad-matrix {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 16px;
    margin-bottom: 30px;
}
.launchpad-item {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 22px;
    text-decoration: none;
    color: inherit;
    position: relative;
    border: 1px solid var(--cobalt-rule);
    background: var(--cobalt-paper);
    transition: transform 0.12s ease-out, border-color 0.15s ease, box-shadow 0.15s ease;
}
.launchpad-item:hover {
    transform: translateY(-2px);
    border-color: var(--cobalt-accent);
    box-shadow: 0 8px 24px -8px rgba(37, 99, 235, 0.18);
    text-decoration: none;
}
.launchpad-item-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
}
.launchpad-icon-wrap {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--cobalt-paper-2);
    border: 1px solid var(--cobalt-rule);
}
.launchpad-item-title {
    font-family: var(--font-display);
    font-size: 17px;
    font-weight: 700;
    letter-spacing: -0.015em;
    color: var(--cobalt-ink);
    margin: 0 0 8px 0;
}
.launchpad-item-desc {
    font-family: var(--font-body);
    font-size: 13px;
    line-height: 1.55;
    color: var(--cobalt-ink-2);
    margin: 0 0 18px 0;
    flex-grow: 1;
}
.launchpad-item-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 14px;
    border-top: 1px solid var(--cobalt-rule);
    font-family: var(--font-display);
    font-size: 13px;
    font-weight: 600;
    color: var(--cobalt-accent);
}

/* 7. STEPPER QUY TRÌNH 3 BƯỚC */
.stepper-dock {
    padding: 24px 26px;
    margin-bottom: 28px;
    background: var(--cobalt-paper);
    border: 1px solid var(--cobalt-rule);
}
.stepper-header-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 18px;
    flex-wrap: wrap;
    gap: 8px;
}
.stepper-track-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    position: relative;
}
@media (max-width: 768px) {
    .stepper-track-grid {
        grid-template-columns: 1fr;
    }
}
.step-stage-card {
    padding: 16px 18px;
    background: var(--cobalt-paper-2);
    border: 1px solid var(--cobalt-rule);
    border-radius: 6px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.step-stage-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
}
.step-stage-tag {
    font-family: var(--font-mono);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.05em;
    color: var(--cobalt-muted);
}
.step-stage-title {
    font-family: var(--font-display);
    font-size: 15px;
    font-weight: 700;
    color: var(--cobalt-ink);
    margin: 0 0 6px 0;
}
.step-stage-text {
    font-family: var(--font-body);
    font-size: 12.5px;
    line-height: 1.5;
    color: var(--cobalt-ink-2);
    margin: 0 0 12px 0;
}
.step-stage-link {
    font-family: var(--font-display);
    font-size: 12.5px;
    font-weight: 600;
    color: var(--cobalt-accent);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.step-stage-link:hover {
    text-decoration: underline;
}

/* 8. QUICK SEARCH CONTAINER */
.quick-search-dock {
    padding: 22px 26px;
    margin-bottom: 28px;
    background: var(--cobalt-paper);
    border: 1px solid var(--cobalt-rule);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
}
.quick-search-desc-wrap {
    max-width: 420px;
}
.quick-search-form-wrap {
    flex-grow: 1;
    max-width: 540px;
}
.quick-search-input-group {
    display: flex;
    align-items: center;
    gap: 8px;
}
.quick-search-input {
    width: 100%;
    min-height: 44px;
    padding: 0 14px;
    border: 1px solid var(--cobalt-rule-2);
    border-radius: 6px;
    background: var(--cobalt-paper-2);
    color: var(--cobalt-ink);
    font-family: var(--font-body);
    font-size: 14px;
    outline: none;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.quick-search-input:focus {
    border-color: var(--cobalt-accent);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.18);
    background: var(--cobalt-paper);
}

/* 9. THÔNG TƯ 22 REFERENCE */
.handbook-dock {
    padding: 24px 26px;
    margin-bottom: 24px;
    background: var(--cobalt-paper);
    border: 1px solid var(--cobalt-rule);
}
.handbook-cards-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-top: 16px;
}
@media (max-width: 960px) {
    .handbook-cards-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 520px) {
    .handbook-cards-grid {
        grid-template-columns: 1fr;
    }
}
.handbook-tier-card {
    padding: 16px 18px;
    background: var(--cobalt-paper-2);
    border: 1px solid var(--cobalt-rule);
    border-radius: 6px;
    border-top: 3px solid var(--cobalt-rule-2);
}
.handbook-tier-card.tier-tot { border-top-color: var(--cobalt-emerald); }
.handbook-tier-card.tier-kha { border-top-color: var(--cobalt-accent); }
.handbook-tier-card.tier-dat { border-top-color: var(--cobalt-amber); }
.handbook-tier-card.tier-chuadat { border-top-color: var(--cobalt-ruby); }

.handbook-tier-name {
    font-family: var(--font-display);
    font-size: 15px;
    font-weight: 700;
    color: var(--cobalt-ink);
    margin: 0 0 8px 0;
}
.handbook-tier-body {
    font-family: var(--font-body);
    font-size: 12.5px;
    line-height: 1.55;
    color: var(--cobalt-ink-2);
    margin: 0;
}
/* 10. RESPONSIVE OPTIMIZATIONS (RWD - MOBILE FIRST) */
.welcome-shell {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    min-width: 0;
}
@media (max-width: 768px) {
    .hero-telemetry-dock {
        padding: 22px 18px 20px 18px;
    }
    .hero-heading {
        font-size: clamp(22px, 5.5vw, 30px);
        margin-bottom: 12px;
    }
    .hero-summary {
        font-size: 13.5px;
        line-height: 1.55;
        margin-bottom: 18px;
    }
    .hero-actions-bar {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }
    .hero-actions-bar .cobalt-key {
        width: 100%;
        justify-content: center;
    }
    .bento-kpi-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        margin-bottom: 20px;
    }
    .launchpad-matrix {
        grid-template-columns: 1fr;
        gap: 12px;
        margin-bottom: 22px;
    }
    .stepper-dock, .quick-search-dock, .handbook-dock {
        padding: 18px 16px;
        margin-bottom: 20px;
    }
    .quick-search-dock {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }
    .quick-search-desc-wrap, .quick-search-form-wrap {
        max-width: 100%;
        width: 100%;
    }
    .quick-search-input-group {
        flex-direction: column;
        width: 100%;
        gap: 8px;
    }
    .quick-search-input-group .cobalt-key {
        width: 100%;
        justify-content: center;
    }
    .handbook-cards-grid {
        grid-template-columns: 1fr;
        gap: 10px;
    }
}
@media (max-width: 480px) {
    .bento-kpi-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="welcome-shell">

    <!-- 1. HERO OBSERVABILITY DOCK -->
    <section class="hero-telemetry-dock cobalt-cut" aria-labelledby="welcomeHeroTitle">
        <div class="hero-telemetry-content">
            <div class="hero-status-strip">
                <span class="hero-pulse-dot" aria-hidden="true"></span>
                <span data-i18n="home.semester_phase"><?= htmlspecialchars($welcomeCalendar['phase_label'] ?? 'Học kỳ I') ?> &bull; Niên khóa <?= htmlspecialchars($welcomeCalendar['academic_year'] ?? '2026–2027') ?></span>
                <span style="opacity: 0.45;">&bull;</span>
                <span data-i18n="home.role_label"><?= htmlspecialchars($userRoleTitle) ?></span>
            </div>

            <h1 id="welcomeHeroTitle" class="hero-heading" data-i18n="home.hero_title">
                Theo dõi tiến trình &amp; Cảnh báo sớm học lực THCS
            </h1>

            <p class="hero-summary" data-i18n="home.hero_desc">
                Hệ thống quan sát dữ liệu học tập tự động hóa theo chuẩn Thông tư 22/2021/TT-BGDĐT. Nhận diện sớm nguy cơ sụt giảm phong độ qua quỹ đạo điểm số OLS và ma trận sổ điểm điện tử.
            </p>

            <div class="hero-actions-bar">
                <a href="index.php" class="cobalt-key cobalt-key-primary" data-i18n="home.cta_radar">
                    <?= get_svg_icon('activity', 18) ?>
                    <span>Xem Bảng Cảnh Báo Sớm</span>
                </a>
                <a href="nhap_diem.php" class="cobalt-key" data-i18n="home.cta_gradebook">
                    <?= get_svg_icon('file-text', 18) ?>
                    <span>Sổ Điểm Bộ Môn</span>
                </a>
                <?php if ($userRole === 'admin'): ?>
                <a href="admin.php" class="cobalt-key" data-i18n="home.cta_admin">
                    <?= get_svg_icon('settings', 18) ?>
                    <span>Quản Trị Nhà Trường</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php
        $avgFloat = (float)$schoolAvg;
        $avgBorderVar = 'var(--cobalt-emerald)';
        $avgBadgeBg = 'var(--cobalt-emerald-wash)';
        if ($avgFloat > 0 && $avgFloat < 5.0) {
            $avgBorderVar = 'var(--cobalt-ruby)';
            $avgBadgeBg = 'var(--cobalt-ruby-wash)';
        } elseif ($avgFloat > 0 && $avgFloat < 6.5) {
            $avgBorderVar = 'var(--cobalt-amber)';
            $avgBadgeBg = 'var(--cobalt-amber-wash)';
        } elseif ($avgFloat > 0 && $avgFloat < 8.0) {
            $avgBorderVar = 'var(--cobalt-accent)';
            $avgBadgeBg = 'var(--cobalt-accent-wash)';
        }
    ?>

    <!-- 2. BENTO GRID KPI METRICS -->
    <section class="bento-kpi-grid" aria-label="Thống kê tổng quan trường học">
        <!-- KPI 1: Sĩ số toàn trường (Viền trái màu xanh biển) -->
        <div class="bento-kpi-item cobalt-cut" style="border-left: 3px solid var(--cobalt-accent);">
            <div class="bento-kpi-head">
                <span class="bento-kpi-title" data-i18n="home.kpi_total_students" style="color: var(--cobalt-accent);">Sĩ số toàn trường</span>
                <span class="bento-kpi-badge" style="background: var(--cobalt-accent-wash); color: var(--cobalt-accent);"><span class="kpi-count"><?= $totalClasses ?></span> <span data-i18n="home.badge_classes">Lớp</span></span>
            </div>
            <div class="bento-kpi-value"><?= number_format($totalStudents) ?></div>
        </div>

        <!-- KPI 2: Tỷ lệ Nguy cấp (Red) -->
        <div class="bento-kpi-item cobalt-cut" style="border-left: 3px solid var(--cobalt-ruby);">
            <div class="bento-kpi-head">
                <span class="bento-kpi-title" data-i18n="home.kpi_red_rate" style="color: var(--cobalt-ruby);">Tỷ lệ nguy cấp</span>
                <span class="bento-kpi-badge" style="background: var(--cobalt-ruby-wash); color: var(--cobalt-ruby);"><span class="kpi-count"><?= (int)($scoreStats['red_count'] ?? 0) ?></span> <span data-i18n="home.badge_students">HS</span></span>
            </div>
            <div class="bento-kpi-value" style="color: var(--cobalt-ruby);"><?= $pctRed ?>%</div>
        </div>

        <!-- KPI 3: Tỷ lệ Cần chú ý (Yellow) -->
        <div class="bento-kpi-item cobalt-cut" style="border-left: 3px solid var(--cobalt-amber);">
            <div class="bento-kpi-head">
                <span class="bento-kpi-title" data-i18n="home.kpi_yellow_rate" style="color: var(--cobalt-amber);">Cần chú ý</span>
                <span class="bento-kpi-badge" style="background: var(--cobalt-amber-wash); color: var(--cobalt-amber);"><span class="kpi-count"><?= (int)($scoreStats['yellow_count'] ?? 0) ?></span> <span data-i18n="home.badge_students">HS</span></span>
            </div>
            <div class="bento-kpi-value" style="color: var(--cobalt-amber);"><?= $pctYellow ?>%</div>
        </div>

        <!-- KPI 4: Điểm TB trường (Viền trái đổi màu theo mức ĐTB toàn trường) -->
        <div class="bento-kpi-item cobalt-cut" style="border-left: 3px solid <?= $avgBorderVar ?>;">
            <div class="bento-kpi-head">
                <span class="bento-kpi-title" data-i18n="home.kpi_school_avg" style="color: <?= $avgBorderVar ?>;">Điểm TB trường</span>
                <span class="bento-kpi-badge" style="background: <?= $avgBadgeBg ?>; color: <?= $avgBorderVar ?>;"><span class="kpi-count"><?= $pctGood ?>%</span> <span data-i18n="home.badge_good">Khá Tốt</span></span>
            </div>
            <div class="bento-kpi-value" style="color: <?= $avgBorderVar ?>;"><?= $schoolAvg ?></div>
        </div>
    </section>

    <!-- 3. GIỚI THIỆU TỔNG QUAN HỆ THỐNG & PHÂN HỆ LÀM VIỆC -->
    <section aria-labelledby="launchpadHeader">
        <div class="launchpad-section-header">
            <div>
                <h2 id="launchpadHeader" class="launchpad-section-title" data-i18n="home.launchpad_title">
                    <?= get_svg_icon('radar', 20, '', 'color: var(--cobalt-accent);') ?>
                    <span>Giới thiệu tổng quan hệ thống</span>
                </h2>
                <p class="launchpad-section-desc" data-i18n="home.launchpad_desc">
                    Hệ thống quan sát học lực THCS Thông tư 22: tích hợp Sổ điểm VnEdu, cảnh báo sớm nguy cơ sa sút và định hướng can thiệp sư phạm.
                </p>
            </div>
        </div>

        <div class="launchpad-matrix">
            <!-- 1. BẢNG CẢNH BÁO SỚM -->
            <a href="index.php" class="launchpad-item cobalt-cut">
                <div>
                    <div class="launchpad-item-top">
                        <div class="launchpad-icon-wrap" style="color: var(--cobalt-accent);">
                            <?= get_svg_icon('activity', 22) ?>
                        </div>
                        <span class="bento-kpi-badge" style="background: var(--cobalt-accent-wash); color: var(--cobalt-accent);" data-i18n="home.lp1_badge">Ra-đa</span>
                    </div>
                    <h3 class="launchpad-item-title" data-i18n="home.lp1_title">Cảnh báo sớm &amp; Xu hướng OLS</h3>
                    <p class="launchpad-item-desc" data-i18n="home.lp1_desc">
                        Quan sát quỹ đạo tiến bộ học tập qua các bài kiểm tra, phân tầng cảnh báo Nguy cấp, Cần chú ý, Ổn định và đề xuất can thiệp sư phạm tức thì.
                    </p>
                </div>
                <div class="launchpad-item-footer">
                    <span data-i18n="home.lp1_action">Mở bảng cảnh báo sớm</span>
                    <?= get_svg_icon('chevron-right', 16) ?>
                </div>
            </a>

            <!-- 2. SỔ ĐIỂM BỘ MÔN -->
            <a href="nhap_diem.php" class="launchpad-item cobalt-cut">
                <div>
                    <div class="launchpad-item-top">
                        <div class="launchpad-icon-wrap" style="color: var(--cobalt-emerald);">
                            <?= get_svg_icon('file-text', 22) ?>
                        </div>
                        <span class="bento-kpi-badge" style="background: var(--cobalt-emerald-wash); color: var(--cobalt-emerald);" data-i18n="home.lp2_badge">VnEdu</span>
                    </div>
                    <h3 class="launchpad-item-title" data-i18n="home.lp2_title">Sổ điểm điện tử bộ môn</h3>
                    <p class="launchpad-item-desc" data-i18n="home.lp2_desc">
                        Ma trận điểm 2D chuẩn VnEdu với ĐĐGtx (1–4), Giữa kỳ (HS 2), Cuối kỳ (HS 3). Tự động tính ĐTBmhk, hỗ trợ phím mũi tên và xuất nhập Excel OpenXML.
                    </p>
                </div>
                <div class="launchpad-item-footer">
                    <span data-i18n="home.lp2_action">Vào sổ điểm bộ môn</span>
                    <?= get_svg_icon('chevron-right', 16) ?>
                </div>
            </a>

            <!-- 3. QUÉT NHANH BẢNG ĐIỂM -->
            <a href="quet_nhanh.php" class="launchpad-item cobalt-cut">
                <div>
                    <div class="launchpad-item-top">
                        <div class="launchpad-icon-wrap" style="color: var(--cobalt-amber);">
                            <?= get_svg_icon('qr-code', 22) ?>
                        </div>
                        <span class="bento-kpi-badge" style="background: var(--cobalt-amber-wash); color: var(--cobalt-amber);" data-i18n="home.lp3_badge">Tiện ích</span>
                    </div>
                    <h3 class="launchpad-item-title" data-i18n="home.lp3_title">Quét nhanh bảng điểm</h3>
                    <p class="launchpad-item-desc" data-i18n="home.lp3_desc">
                        Chế độ trải nghiệm không cần đăng nhập: kéo thả tệp Excel, CSV hoặc ảnh chụp sổ điểm để nhận phân tích Ra-đa và xuất phiếu học tập tức thì.
                    </p>
                </div>
                <div class="launchpad-item-footer">
                    <span data-i18n="home.lp3_action">Dùng thử quét nhanh</span>
                    <?= get_svg_icon('chevron-right', 16) ?>
                </div>
            </a>

            <?php if ($userRole === 'admin'): ?>
            <!-- 4. QUẢN TRỊ ADMIN -->
            <a href="admin.php" class="launchpad-item cobalt-cut">
                <div>
                    <div class="launchpad-item-top">
                        <div class="launchpad-icon-wrap" style="color: #475569;">
                            <?= get_svg_icon('settings', 22) ?>
                        </div>
                        <span class="bento-kpi-badge" style="background: rgba(71, 85, 105, 0.1); color: #475569;" data-i18n="home.lp4_badge">Quản trị</span>
                    </div>
                    <h3 class="launchpad-item-title" data-i18n="home.lp4_title">Quản trị nhà trường</h3>
                    <p class="launchpad-item-desc" data-i18n="home.lp4_desc">
                        Quản lý ma trận lớp học Khối 6–9, phân công chuyên môn hàng loạt, nạp danh sách học sinh toàn trường và theo dõi nhật ký kiểm toán hệ thống.
                    </p>
                </div>
                <div class="launchpad-item-footer">
                    <span data-i18n="home.lp4_action">Mở cổng quản trị</span>
                    <?= get_svg_icon('chevron-right', 16) ?>
                </div>
            </a>
            <?php endif; ?>
        </div>
    </section>

    <!-- 3B. CƠ CHẾ HOẠT ĐỘNG THEO VAI TRÒ & GIẢI THÍCH THUẬT TOÁN DỰ BÁO OLS (TT22) -->
    <section class="stepper-dock cobalt-cut" aria-labelledby="roleGuideTitle" style="margin-bottom: 28px;">
        <div class="stepper-header-row">
            <div>
                <h3 id="roleGuideTitle" class="launchpad-section-title" style="font-size: 18px;">
                    <?= get_svg_icon('users', 18, '', 'color: var(--cobalt-accent);') ?>
                    <span>Cơ chế phối hợp theo vai trò &amp; Giải nghĩa Cảnh báo sớm</span>
                </h3>
                <p class="launchpad-section-desc">
                    Mỗi tài khoản trên EduRadar được thiết kế chuyên biệt theo nhiệm vụ trong Thông tư 22/2021/TT-BGDĐT, kết nối liền mạch từ nhập điểm đến can thiệp sư phạm.
                </p>
            </div>
            <span class="hero-status-strip" style="margin: 0;">Mô hình 3 Tầng</span>
        </div>

        <div class="stepper-track-grid" style="margin-bottom: 18px;">
            <!-- VAI TRÒ 1: GIÁO VIÊN BỘ MÔN -->
            <div class="step-stage-card" style="border-top: 3px solid var(--cobalt-accent);">
                <div>
                    <div class="step-stage-meta">
                        <span class="step-stage-tag" style="color: var(--cobalt-accent);">VAI TRÒ 01</span>
                        <span class="bento-kpi-badge" style="background: var(--cobalt-accent-wash); color: var(--cobalt-accent);">GV Bộ Môn</span>
                    </div>
                    <h4 class="step-stage-title">Quản lý điểm &amp; Theo dõi môn dạy</h4>
                    <p class="step-stage-text">
                        Nhập điểm thường xuyên (TX1–TX4) và Giữa kỳ (GK1), xuất/nhập bảng điểm chuẩn VnEdu. Hệ thống tự động cảnh báo học sinh đang tụt dốc ở môn học và tính số điểm Cuối kỳ cần đạt.
                    </p>
                </div>
            </div>

            <!-- VAI TRÒ 2: GIÁO VIÊN CHỦ NHIỆM -->
            <div class="step-stage-card" style="border-top: 3px solid var(--cobalt-emerald);">
                <div>
                    <div class="step-stage-meta">
                        <span class="step-stage-tag" style="color: var(--cobalt-emerald);">VAI TRÒ 02</span>
                        <span class="bento-kpi-badge" style="background: var(--cobalt-emerald-wash); color: var(--cobalt-emerald);">GV Chủ Nhiệm</span>
                    </div>
                    <h4 class="step-stage-title">Giám sát toàn diện lớp chủ nhiệm</h4>
                    <p class="step-stage-text">
                        Xem tổng hợp điểm tất cả các môn của lớp chủ nhiệm, theo dõi xếp loại Học lực chung (Tốt, Khá, Đạt, Chưa đạt) và phát hiện sớm học sinh bị khống chế điểm liệt (&lt; 3.5).
                    </p>
                </div>
            </div>

            <!-- VAI TRÒ 3: BAN GIÁM HIỆU / ADMIN -->
            <div class="step-stage-card" style="border-top: 3px solid var(--cobalt-amber);">
                <div>
                    <div class="step-stage-meta">
                        <span class="step-stage-tag" style="color: var(--cobalt-amber);">VAI TRÒ 03</span>
                        <span class="bento-kpi-badge" style="background: var(--cobalt-amber-wash); color: var(--cobalt-amber);">Quản Trị Trường</span>
                    </div>
                    <h4 class="step-stage-title">Phân quyền qua ID &amp; Điều phối kỳ thi</h4>
                    <p class="step-stage-text">
                        Quản lý ma trận lớp Khối 6–9, dán Mã ID Giáo viên (<code>GV-XXXXXX</code>) để cấp quyền GVCN/GVBM, kiểm soát mở khóa cột điểm Cuối kỳ (CK1) và giám sát chất lượng toàn trường.
                    </p>
                </div>
            </div>
        </div>

        <!-- HỘP GIẢI NGHĨA TRỰC QUAN CÁCH TÍNH OLS & PHÂN TẦNG CẢNH BÁO -->
        <div style="padding: 14px 18px; border-radius: 8px; background: var(--cobalt-paper-2); border: 1px dashed var(--cobalt-rule-2); display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 14px; font-size: 12.5px; line-height: 1.55; color: var(--cobalt-ink-2);">
            <div>
                <b style="color: var(--cobalt-accent); display: block; margin-bottom: 3px;">📈 Dự báo Quỹ đạo OLS là gì?</b>
                Hệ thống dùng hồi quy tuyến tính (Ordinary Least Squares) qua các cột điểm TX1 &rarr; TX4 &rarr; GK1 để đo tốc độ tiến bộ (<code>slope &gt; 0</code>) hoặc đà tụt dốc (<code>slope &lt; 0</code>) của từng học sinh.
            </div>
            <div>
                <b style="color: var(--cobalt-emerald); display: block; margin-bottom: 3px;">🎯 Giải ngược điểm mục tiêu Cuối kỳ:</b>
                Từ công thức <code>ĐTBmhk = (Tổng TX + 2&times;GK + 3&times;CK) / (Số cột TX + 5)</code>, hệ thống tính chính xác số điểm Cuối kỳ học sinh cần đạt để giữ mức Đạt (5.0), Khá (6.5) hoặc Tốt (8.0).
            </div>
            <div>
                <b style="color: var(--cobalt-ruby); display: block; margin-bottom: 3px;">🚨 Khi nào học sinh vào diện Nguy cấp?</b>
                Khi ĐTB tạm tính &lt; 3.5, hoặc điểm thi định kỳ &lt; 3.0, hoặc cần điểm thi Cuối kỳ vượt quá khả năng (&gt; 8.5) mới đủ 5.0 trung bình môn.
            </div>
        </div>
    </section>

    <!-- 4. STEPPER QUY TRÌNH 3 BƯỚC -->
    <section class="stepper-dock cobalt-cut" aria-labelledby="stepperTitle">
        <div class="stepper-header-row">
            <div>
                <h3 id="stepperTitle" class="launchpad-section-title" style="font-size: 18px;" data-i18n="home.stepper_title">
                    <?= get_svg_icon('check-circle', 18, '', 'color: var(--cobalt-accent);') ?>
                    <span>Quy trình theo dõi học lực THCS</span>
                </h3>
            </div>
            <span class="hero-status-strip" style="margin: 0;" data-i18n="home.stepper_badge">Quy trình TT22</span>
        </div>

        <div class="stepper-track-grid">
            <!-- CHẶNG 01 -->
            <div class="step-stage-card">
                <div>
                    <div class="step-stage-meta">
                        <span class="step-stage-tag" data-i18n="home.step1_tag">CHẶNG 01</span>
                        <span class="bento-kpi-badge" style="background: var(--cobalt-accent-wash); color: var(--cobalt-accent);" data-i18n="home.step1_badge">Nhập liệu</span>
                    </div>
                    <h4 class="step-stage-title" data-i18n="home.step1_title">Ghi nhận điểm định kỳ</h4>
                    <p class="step-stage-text" data-i18n="home.step1_desc">
                        Vào sổ điểm bộ môn, nhập điểm kiểm tra thường xuyên (1–4), Giữa kỳ và Cuối kỳ trực tiếp hoặc qua file mẫu Excel VnEdu.
                    </p>
                </div>
                <a href="nhap_diem.php" class="step-stage-link" data-i18n="home.step1_link">
                    <span>Mở sổ điểm điện tử</span>
                    <?= get_svg_icon('chevron-right', 14) ?>
                </a>
            </div>

            <!-- CHẶNG 02 -->
            <div class="step-stage-card">
                <div>
                    <div class="step-stage-meta">
                        <span class="step-stage-tag" data-i18n="home.step2_tag">CHẶNG 02</span>
                        <span class="bento-kpi-badge" style="background: var(--cobalt-emerald-wash); color: var(--cobalt-emerald);" data-i18n="home.step2_badge">Giám sát</span>
                    </div>
                    <h4 class="step-stage-title" data-i18n="home.step2_title">Phân tích quỹ đạo OLS</h4>
                    <p class="step-stage-text" data-i18n="home.step2_desc">
                        Hệ thống tự động tính hệ số góc OLS và phát hiện xu hướng: Tiến bộ rõ rệt, Duy trì ổn định, hoặc Cần chú ý sa sút phong độ.
                    </p>
                </div>
                <a href="index.php" class="step-stage-link" style="color: var(--cobalt-emerald);" data-i18n="home.step2_link">
                    <span>Xem bảng cảnh báo</span>
                    <?= get_svg_icon('chevron-right', 14) ?>
                </a>
            </div>

            <!-- CHẶNG 03 -->
            <div class="step-stage-card">
                <div>
                    <div class="step-stage-meta">
                        <span class="step-stage-tag" data-i18n="home.step3_tag">CHẶNG 03</span>
                        <span class="bento-kpi-badge" style="background: var(--cobalt-amber-wash); color: var(--cobalt-amber);" data-i18n="home.step3_badge">Can thiệp</span>
                    </div>
                    <h4 class="step-stage-title" data-i18n="home.step3_title">Kế hoạch hỗ trợ sư phạm</h4>
                    <p class="step-stage-text" data-i18n="home.step3_desc">
                        Khoanh vùng học sinh nguy cấp và cần lưu ý, lưu nhận xét sư phạm trực tiếp và lập kế hoạch phụ đạo kịp thời trước kỳ thi.
                    </p>
                </div>
                <a href="index.php" class="step-stage-link" style="color: var(--cobalt-amber);" data-i18n="home.step3_link">
                    <span>Kế hoạch bồi dưỡng</span>
                    <?= get_svg_icon('chevron-right', 14) ?>
                </a>
            </div>
        </div>
    </section>

    <!-- 5. TÌM KIẾM NHANH HỌC SINH (HARD CONTRACT PRESERVED) -->
    <section class="quick-search-dock cobalt-cut" aria-labelledby="quickSearchTitle">
        <div class="quick-search-desc-wrap">
            <h3 id="quickSearchTitle" class="launchpad-section-title" style="font-size: 17px;" data-i18n="home.search_title">
                <?= get_svg_icon('search', 18, '', 'color: var(--cobalt-accent);') ?>
                <span>Tra cứu nhanh hồ sơ học sinh</span>
            </h3>
            <p class="launchpad-section-desc" data-i18n="home.search_desc">
                Nhập mã học sinh định danh hoặc họ tên để điều hướng thẳng đến bảng phân tích học lực.
            </p>
        </div>

        <div class="quick-search-form-wrap">
            <form method="GET" action="index.php" class="unified-search-form">
                <div class="quick-search-input-group">
                    <input type="text" name="search" id="quickStudentSearchInput" class="quick-search-input" placeholder="Ví dụ: 2404815683 hoặc Nguyễn Văn..." aria-label="Mã học sinh hoặc họ tên học sinh" autocomplete="off" data-i18n-placeholder="home.search_placeholder">
                    <button type="submit" class="cobalt-key cobalt-key-primary" style="white-space: nowrap;" data-i18n="home.search_btn">
                        <?= get_svg_icon('search', 16) ?>
                        <span>Tìm kiếm</span>
                    </button>
                </div>
            </form>
        </div>
    </section>

    <!-- 6. CẨM NANG THÔNG TƯ 22 (TT22 QUICK REFERENCE) -->
    <section class="handbook-dock cobalt-cut" aria-labelledby="handbookTitle">
        <div class="stepper-header-row" style="margin-bottom: 8px;">
            <div>
                <h3 id="handbookTitle" class="launchpad-section-title" style="font-size: 17px;" data-i18n="home.tt22_title">
                    <?= get_svg_icon('book-open', 18, '', 'color: var(--cobalt-accent);') ?>
                    <span>Khung tiêu chuẩn đánh giá Thông tư 22/2021/TT-BGDĐT</span>
                </h3>
                <p class="launchpad-section-desc" data-i18n="home.tt22_desc">
                    Quy chuẩn xếp loại kết quả học tập THCS kết hợp giữa nhận xét và điểm số đánh giá.
                </p>
            </div>
            <span class="hero-status-strip" style="margin: 0;" data-i18n="home.tt22_grades">Áp dụng Khối 6, 7, 8, 9</span>
        </div>

        <div class="handbook-cards-grid">
            <div class="handbook-tier-card tier-tot">
                <div class="handbook-tier-name" style="color: var(--cobalt-emerald);" data-i18n="home.tier_tot">Mức Tốt</div>
                <p class="handbook-tier-body" data-i18n="home.tier_tot_body">
                    &bull; ĐTBMhk &ge; 8.0<br>
                    &bull; Không có môn nào dưới 6.5<br>
                    &bull; Các môn nhận xét: Đạt
                </p>
            </div>
            <div class="handbook-tier-card tier-kha">
                <div class="handbook-tier-name" style="color: var(--cobalt-accent);" data-i18n="home.tier_kha">Mức Khá</div>
                <p class="handbook-tier-body" data-i18n="home.tier_kha_body">
                    &bull; ĐTBMhk &ge; 6.5 (chưa đạt Tốt)<br>
                    &bull; Không có môn nào dưới 5.0<br>
                    &bull; Các môn nhận xét: Đạt
                </p>
            </div>
            <div class="handbook-tier-card tier-dat">
                <div class="handbook-tier-name" style="color: var(--cobalt-amber);" data-i18n="home.tier_dat">Mức Đạt</div>
                <p class="handbook-tier-body" data-i18n="home.tier_dat_body">
                    &bull; ĐTBMhk &ge; 5.0 (chưa đạt Khá)<br>
                    &bull; Không có môn nào dưới 3.5<br>
                    &bull; Tối đa 1 môn nhận xét Chưa Đạt
                </p>
            </div>
            <div class="handbook-tier-card tier-chuadat">
                <div class="handbook-tier-name" style="color: var(--cobalt-ruby);" data-i18n="home.tier_chuadat">Mức Chưa đạt</div>
                <p class="handbook-tier-body" data-i18n="home.tier_chuadat_body">
                    &bull; Các trường hợp còn lại<br>
                    &bull; ĐTBMhk &lt; 3.5 hoặc thi cuối kỳ &lt; 3.0: Ra-đa khoanh vùng nguy cấp
                </p>
            </div>
        </div>
    </section>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
