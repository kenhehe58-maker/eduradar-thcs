<?php
// includes/header.php - Liquid Glass Apple Full-Width Shell (Bottom Dock Replaces Sidebar)
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
$currentUserRole = $_SESSION['role'] ?? 'teacher';
$currentUsername = $_SESSION['username'] ?? '';
$currentUserFullName = $_SESSION['full_name'] ?? $currentUsername;
$activeNav = $activeNav ?? 'dashboard'; // 'dashboard' | 'gradebook' | 'admin'
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
    <style>
        body { font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif; }
        body.liquid-glass-layout {
            display: flex !important;
            flex-direction: column !important;
            min-height: 100vh;
            width: 100%;
            margin: 0;
            padding: 0;
            background: #f4f6fa;
            color: var(--text-body, #334155);
        }
        .glass-topbar {
            position: sticky;
            top: 0;
            left: 0;
            right: 0;
            width: 100%;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.85);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            backdrop-filter: blur(20px) saturate(180%);
            border-bottom: 1px solid rgba(226, 232, 240, 0.85);
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.04);
        }
        .glass-topbar-inner {
            max-width: 1540px;
            margin: 0 auto;
            padding: 12px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            width: 100%;
        }
        .glass-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: inherit;
            transition: transform 0.2s ease;
        }
        .glass-brand:hover {
            transform: translateY(-1px);
        }
        .glass-brand-icon {
            font-size: 20px;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, #4f46e5, #818cf8);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }
        .glass-brand-text {
            display: flex;
            flex-direction: column;
        }
        .glass-brand-title {
            font-size: 16px;
            font-weight: 800;
            letter-spacing: -0.4px;
            color: #0f172a;
            line-height: 1.2;
        }
        .glass-brand-sub {
            font-size: 11.5px;
            font-weight: 600;
            color: #64748b;
        }
        /* GLASS TOPBAR SYSTEM STATUS PILLS (REPLACES REDUNDANT USER PILL) */
        .glass-status-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .glass-status-chip {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.85);
            border: 1px solid rgba(203, 213, 225, 0.7);
            font-size: 12.5px;
            font-weight: 700;
            color: #334155;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
            white-space: nowrap;
        }
        .glass-status-chip .chip-pulse {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            animation: pulseGreen 2s infinite;
        }
        @keyframes pulseGreen {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }
        .glass-status-chip.chip-accent {
            background: rgba(238, 242, 255, 0.85);
            border-color: rgba(199, 210, 254, 0.8);
            color: #4338ca;
        }
        .content.full-width {
            margin-left: auto !important;
            margin-right: auto !important;
            padding: 24px 32px 90px 105px !important;
            max-width: 1540px !important;
            width: 100% !important;
            flex: 1 0 auto;
        }
        /* Anti-overlap scroll margin for headings and sections */
        section, .panel-box, .section-header, table {
            scroll-margin-top: 85px;
        }
        @media (max-width: 900px) {
            .content.full-width {
                padding: 16px 16px 85px 16px !important;
            }
        }
    </style>
    <?php if (!empty($extraHead)) echo $extraHead; ?>
</head>
<body class="liquid-glass-layout">

<!-- TOP LIQUID GLASS NAVIGATION BAR (REPLACES FIXED SIDEBAR) -->
<header class="glass-topbar">
    <div class="glass-topbar-inner">
        <a href="index.php" class="glass-brand">
            <span class="glass-brand-icon">📡</span>
            <div class="glass-brand-text">
                <span class="glass-brand-title"><?= APP_NAME ?? 'EDURADAR THCS' ?></span>
                <span class="glass-brand-sub">Hệ Thống Cảnh Báo Sớm & Sổ Điểm TT22</span>
            </div>
        </a>

        <!-- SYSTEM & ACADEMIC STATUS INDICATORS (NON-REDUNDANT APPLE BAR) -->
        <div class="glass-status-group">
            <div class="glass-status-chip chip-accent" title="Niên khóa & Học kỳ đang áp dụng">
                <span>📅</span>
                <span>Năm học 2025–2026 · Học kỳ II</span>
            </div>
            <div class="glass-status-chip" title="Trạng thái kết nối máy chủ CSDL MariaDB (Port 3306)">
                <span class="chip-pulse"></span>
                <span>MariaDB 3306: Sẵn sàng</span>
            </div>
            <div class="glass-status-chip" title="Quy chuẩn đánh giá học sinh THCS theo Thông tư 22/2021/TT-BGDĐT">
                <span>⚖️</span>
                <span>Chuẩn TT 22/BGDĐT</span>
            </div>
        </div>
    </div>
</header>

<?php include_once __DIR__ . '/side_rail.php'; ?>

<main class="content full-width">
