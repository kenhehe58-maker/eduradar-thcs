<?php
// includes/header.php - Edexa University Modern Layout Shell with Light / Dark Theme Support
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
require_once __DIR__ . '/algorithms.php';
$headerCalendar = get_academic_calendar_phase();
$currentUserRole = $_SESSION['role'] ?? 'teacher';
$currentUsername = $_SESSION['username'] ?? '';
$currentUserFullName = $_SESSION['full_name'] ?? $currentUsername;
$activeNav = $activeNav ?? 'dashboard'; // 'welcome' | 'dashboard' | 'gradebook' | 'admin' | 'slides'
$pageTitle = $pageTitle ?? 'EduRadar THCS';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> – <?= APP_NAME ?? 'EduRadar' ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= file_exists(__DIR__ . '/../assets/css/style.css') ? filemtime(__DIR__ . '/../assets/css/style.css') : time() ?>">
    <script src="assets/js/gsap.min.js"></script>
    <script src="assets/js/ScrollTrigger.min.js"></script>
    <script src="assets/js/ScrollToPlugin.min.js"></script>
    <script src="assets/js/Flip.min.js"></script>
    <script src="assets/js/CustomEase.min.js"></script>
    <script src="assets/js/TextPlugin.min.js"></script>
    <script>
        // Khởi tạo Theme ngay lập tức trước khi render DOM để chống giật nháy màn hình (Anti-Flicker)
        (function() {
            var savedTheme = localStorage.getItem('eduradar_theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
    <style>
        body { font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif; }
        body.liquid-glass-layout {
            display: flex !important;
            flex-direction: column !important;
            min-height: 100vh;
            width: 100%;
            margin: 0;
            padding: 0;
            background: var(--bg-main, #f8fafc);
            color: var(--text-body, #1e293b);
            transition: background-color 0.2s ease, color 0.2s ease;
        }
        /* EDEXA TOPBAR: FIXED OFFSET 230px FROM LEFT */
        .glass-topbar {
            position: sticky;
            top: 0;
            left: 230px;
            margin-left: 230px;
            width: calc(100% - 230px);
            z-index: 1000;
            background: rgba(255, 255, 255, 0.94);
            -webkit-backdrop-filter: blur(16px);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border-subtle, #e2e8f0);
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
            transition: all 0.2s ease;
        }
        .glass-topbar-inner {
            padding: 12px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            width: 100%;
        }
        /* EDEXA PERSONALIZED GREETING (AS SEEN IN DRIBBLE SHOT) */
        .edexa-welcome-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .edexa-welcome-text {
            font-size: 17px;
            font-weight: 800;
            color: var(--text-heading, #0f172a);
            letter-spacing: -0.02em;
        }
        .edexa-welcome-sub {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-subtle, #64748b);
        }
        .edexa-role-pill {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            border-radius: 9999px;
            font-size: 11.5px;
            font-weight: 700;
            background: #eff6ff;
            color: #2563eb;
            border: 1px solid #bfdbfe;
        }

        /* SYSTEM & ACADEMIC STATUS INDICATORS */
        .glass-status-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .glass-status-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 9999px;
            background: var(--card-bg, #ffffff);
            border: 1px solid var(--border-subtle, #e2e8f0);
            font-size: 12px;
            font-weight: 700;
            color: var(--text-body, #334155);
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.02);
            white-space: nowrap;
        }
        .glass-status-chip .chip-pulse {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #10b981;
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            animation: pulseGreen 2s infinite;
        }
        @keyframes pulseGreen {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 5px rgba(16, 185, 129, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }
        .glass-status-chip.chip-accent {
            background: #eff6ff;
            border-color: #bfdbfe;
            color: #1d4ed8;
        }
        /* REAL INTERACTIVE THEME TOGGLE BUTTON */
        .edexa-theme-toggle {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 14px;
            border-radius: 9999px;
            background: var(--card-bg, #ffffff);
            color: var(--text-heading, #475569);
            border: 1.5px solid var(--border-color, #cbd5e1);
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            -webkit-user-select: none;
            user-select: none;
        }
        
        /* HEADER HELP / SHORTCUT TRIGGER BUTTON */
        .edexa-help-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--card-bg, #ffffff);
            border: 1.5px solid var(--border-color, #cbd5e1);
            color: var(--primary, #2563eb);
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
            flex-shrink: 0;
            padding: 0;
            font-weight: 800;
            font-size: 13.5px;
            font-family: 'JetBrains Mono', monospace;
        }
        .edexa-help-toggle:hover {
            border-color: var(--primary, #2563eb);
            background: rgba(37, 99, 235, 0.08);
            transform: translateY(-1px);
        }
        [data-theme="dark"] .edexa-help-toggle {
            background: #1e293b;
            border-color: #334155;
            color: #93c5fd;
        }

        .edexa-theme-toggle:hover {
            border-color: var(--primary, #2563eb);
            transform: translateY(-1px);
        }
        .edexa-theme-toggle .theme-icon {
            font-size: 13px;
        }

        /* MAIN CONTENT AREA OFFSET FOR 230px SIDEBAR */
        .content.full-width {
            margin-left: 230px !important;
            width: calc(100% - 230px) !important;
            max-width: none !important;
            padding: 24px 32px 80px 32px !important;
            flex: 1 0 auto;
            box-sizing: border-box;
        }

        /* Anti-overlap scroll margin for headings and sections */
        section, .panel-box, .section-header, table {
            scroll-margin-top: 85px;
        }

        @media (max-width: 900px) and (min-width: 769px) {
            .glass-topbar {
                left: 68px;
                margin-left: 68px;
                width: calc(100% - 68px);
            }
            .content.full-width {
                margin-left: 68px !important;
                width: calc(100% - 68px) !important;
                padding: 16px 16px 70px 16px !important;
            }
        }

        /* Full Responsive Layout for Mobile Devices (<= 768px) */
        @media (max-width: 768px) {
            .glass-topbar {
                position: relative !important;
                left: 0 !important;
                margin-left: 0 !important;
                width: 100% !important;
            }
            .glass-topbar-inner {
                padding: 10px 14px !important;
                flex-direction: row !important;
                align-items: center !important;
                justify-content: space-between !important;
                gap: 8px !important;
            }
            .edexa-welcome-wrap {
                width: auto !important;
                display: flex !important;
                align-items: center !important;
                gap: 8px !important;
                min-width: 0 !important;
                flex: 1 1 auto !important;
            }
            .edexa-app-badge-m {
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                width: 28px !important;
                height: 28px !important;
                border-radius: 8px !important;
                background: linear-gradient(135deg, #2563eb, #3b82f6) !important;
                color: #ffffff !important;
                font-weight: 800 !important;
                font-size: 11px !important;
                letter-spacing: -0.5px !important;
                flex-shrink: 0 !important;
            }
            .edexa-user-m-wrap {
                display: flex !important;
                flex-direction: column !important;
                min-width: 0 !important;
            }
            .edexa-welcome-text {
                font-size: 13.5px !important;
                font-weight: 800 !important;
                color: var(--text-heading, #0f172a) !important;
                line-height: 1.25 !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                display: block !important;
                max-width: none !important;
            }
            .edexa-sub-m {
                display: block !important;
                font-size: 11px !important;
                font-weight: 600 !important;
                color: #64748b !important;
                line-height: 1.2 !important;
            }
            .edexa-role-pill {
                display: none !important;
            }
            .glass-status-group {
                width: auto !important;
                justify-content: flex-end !important;
                gap: 6px !important;
                flex-wrap: nowrap !important;
                flex-shrink: 0 !important;
            }
            .glass-status-chip {
                display: none !important;
            }
            .edexa-theme-toggle {
                padding: 6px !important;
                width: 32px !important;
                height: 32px !important;
                border-radius: 50% !important;
                justify-content: center !important;
            }
            .edexa-theme-toggle #themeLabel {
                display: none !important;
            }
            .content.full-width {
                margin-left: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                padding: 12px 14px 84px 14px !important;
                box-sizing: border-box !important;
            }
        }
        .edexa-app-badge-m, .edexa-sub-m {
            display: none;
        }
    </style>
    <?php if (!empty($extraHead)) echo $extraHead; ?>
</head>
<body class="liquid-glass-layout">

<!-- TOP EDEXA GREETING & STATUS BAR -->
<header class="glass-topbar">
    <div class="glass-topbar-inner">
        <div class="edexa-welcome-wrap">
            <span class="edexa-app-badge-m">ER</span>
            <div class="edexa-user-m-wrap">
                <a href="welcome.php" style="text-decoration: none; color: inherit;" title="Về Trang Chào Mừng & Khởi Động EduRadar">
                    <span class="edexa-welcome-text">
                        Chào <?= htmlspecialchars($currentUserFullName) ?>
                    </span>
                </a>
                <span class="edexa-sub-m">HK I &bull; NH <?= htmlspecialchars($headerCalendar['school_year']) ?></span>
            </div>
            <span class="edexa-role-pill">
                <?= htmlspecialchars(($currentUserRole === 'admin') ? 'Quản Trị Viên' : 'Giáo Viên Bộ Môn') ?>
            </span>
        </div>

        <!-- SYSTEM & ACADEMIC STATUS INDICATORS -->
        <div class="glass-status-group">
            <div class="glass-status-chip chip-accent" title="Niên khóa &amp; Giai đoạn học kỳ: <?= htmlspecialchars($headerCalendar['phase_title']) ?>">
                <span><?= get_svg_icon('calendar', 13, '', 'vertical-align: -2px; margin-right: 4px;') ?><?= htmlspecialchars($headerCalendar['school_year']) ?> &bull; <?= htmlspecialchars($headerCalendar['semester_name']) ?></span>
            </div>
            <div class="glass-status-chip chip-mobile-hide" title="Quy chuẩn đánh giá học sinh THCS theo Thông tư 22/2021/TT-BGDĐT">
                <span class="tech-indicator">SPEC</span>
                <span>Thông tư 22</span>
            </div>
                        <!-- HELP / SHORTCUT MODAL TRIGGER -->
            <button type="button" class="edexa-help-toggle" onclick="toggleHelpDrawer(true)" title="Trung tâm trợ giúp &amp; Phím tắt nhanh (?)" aria-label="Trung tâm trợ giúp">
                <span>?</span>
            </button>
            <!-- INTERACTIVE THEME TOGGLER (LIGHT THEME / DARK THEME) -->
            <button type="button" class="edexa-theme-toggle" id="themeToggleBtn" onclick="toggleAppTheme()" title="Bấm để chuyển đổi giao diện Sáng / Tối" aria-label="Chuyển đổi giao diện Sáng / Tối">
                <span class="theme-icon" id="themeIcon"><?= get_svg_icon('sun', 14) ?></span>
                <span id="themeLabel">Giao diện sáng</span>
            </button>
        </div>
    </div>
</header>

<script>
// ĐIỀU KHIỂN CHUYỂN ĐỔI THEME TOÀN ỨNG DỤNG (PERSISTENT VIA LOCALSTORAGE)
function toggleAppTheme() {
    var cur = document.documentElement.getAttribute('data-theme') || 'light';
    var next = (cur === 'dark') ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('eduradar_theme', next);
    updateThemeUI(next);
}
function updateThemeUI(theme) {
    var lbl = document.getElementById('themeLabel');
    var ico = document.getElementById('themeIcon');
    if (lbl) lbl.textContent = (theme === 'dark') ? 'Giao diện tối' : 'Giao diện sáng';
    if (ico) ico.innerHTML = (theme === 'dark') ? '<?= addslashes(get_svg_icon('moon', 14)) ?>' : '<?= addslashes(get_svg_icon('sun', 14)) ?>';
    if (theme === 'dark') {
        document.body.classList.add('dark-mode');
    } else {
        document.body.classList.remove('dark-mode');
    }
}
document.addEventListener('DOMContentLoaded', function() {
    var theme = document.documentElement.getAttribute('data-theme') || 'light';
    updateThemeUI(theme);
});
</script>

<?php include_once __DIR__ . '/side_rail.php'; ?>

<?php
// BREADCRUMB NAVIGATION & USER ORIENTATION COMPONENT
$breadcrumbs = $breadcrumbs ?? [
    ['title' => 'Trang Chủ', 'url' => 'welcome.php']
];
?>
<main class="content full-width">
<?php if (!empty($breadcrumbs) && count($breadcrumbs) > 0 && ($activeNav ?? '') !== 'welcome'): ?>
<nav class="eduradar-breadcrumb-bar" aria-label="Đường dẫn phân cấp">
    <ol class="eduradar-breadcrumb-list">
        <?php foreach ($breadcrumbs as $idx => $bc): ?>
            <?php $isLast = ($idx === count($breadcrumbs) - 1); ?>
            <li class="breadcrumb-item <?= $isLast ? 'active' : '' ?>" <?= $isLast ? 'aria-current="page"' : '' ?>>
                <?php if (!$isLast && !empty($bc['url'])): ?>
                    <a href="<?= htmlspecialchars($bc['url']) ?>" class="breadcrumb-link">
                        <?php if ($idx === 0): ?>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px; margin-right: 4px;"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                        <?php endif; ?>
                        <span><?= htmlspecialchars($bc['title']) ?></span>
                    </a>
                    <span class="breadcrumb-separator" aria-hidden="true"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg></span>
                <?php else: ?>
                    <span class="breadcrumb-current"><?= htmlspecialchars($bc['title']) ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
<?php endif; ?>
