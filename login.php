<?php
// =============================================================================
// Hệ Thống Quản Lý Điểm & Cảnh Báo Sớm THCS (EduRadar / Chuẩn VnEdu)
// Trang đăng nhập bảo mật (Authentication & Access Control)
// Hallmark v2 Cobalt Theme Redesign
// =============================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/db.php';

// Nếu người dùng đã đăng nhập từ trước, điều hướng trực tiếp theo vai trò
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header('Location: admin.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($username !== '' && $password !== '') {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // XÁC THỰC BẢO MẬT: Bỏ hoàn toàn bypass backdoor cũ
        // Nghiêm ngặt chỉ chấp nhận mật khẩu đã hash bcrypt qua password_verify()
        if ($user && password_verify($password, $user['password'])) {
            // Ngăn ngừa tấn công Session Fixation & xóa sạch ngữ cảnh lớp/môn của tài khoản cũ trên cùng trình duyệt
            session_regenerate_id(true);
            $_SESSION = [];

            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['ctx_owner_id'] = (int)$user['id'];

            // Ghi nhật ký đăng nhập thành công vào bảng audit_logs
            log_action(
                (int)$user['id'],
                $user['username'],
                'Đăng nhập thành công',
                "Người dùng [{$user['username']}] vai trò [{$user['role']}] đã đăng nhập thành công vào hệ thống."
            );

            // Điều hướng theo phân quyền (RBAC): Admin vào admin.php, Giáo viên & Người dùng vào index.php
            if ($user['role'] === 'admin') {
                header('Location: admin.php');
            } else {
                header('Location: index.php');
            }
            exit;
        } else {
            // Ghi nhật ký đăng nhập thất bại kèm tên tài khoản đã cố gắng đăng nhập
            log_action(
                null,
                $username,
                'Đăng nhập thất bại',
                "Thử đăng nhập thất bại với tài khoản [$username]."
            );
            $error = 'Tên đăng nhập hoặc mật khẩu không chính xác!';
        }
    } else {
        $error = 'Vui lòng nhập đầy đủ thông tin!';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="color-scheme" content="dark light">
    <meta name="theme-color" content="#030712">
    <title>Đăng nhập – EduRadar THCS (Chuẩn VnEdu / TT22)</title>
    
    <!-- FONTS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    
    <!-- GSAP CORE & PLUGINS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>

    <script>
        (function() {
            const saved = localStorage.getItem('eduradar_theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', saved);
        })();
    </script>

    <style>
        :root {
            --cobalt-ink: #0f172a;
            --cobalt-ink-muted: #64748b;
            --cobalt-paper: #ffffff;
            --cobalt-paper-2: #f8fafc;
            --cobalt-paper-3: #f1f5f9;
            --cobalt-accent: #2563eb;
            --cobalt-accent-hover: #1d4ed8;
            --cobalt-accent-dark: #1e40af;
            --cobalt-rule: #e2e8f0;
            --cobalt-rule-strong: #cbd5e1;
            --cobalt-body-bg: #f8fafc;
            --cobalt-display: 'Outfit', 'Plus Jakarta Sans', sans-serif;
            --cobalt-sans: 'Plus Jakarta Sans', sans-serif;
            --cobalt-mono: 'JetBrains Mono', monospace;
            --cobalt-shadow: 0 16px 40px -12px rgba(15, 23, 42, 0.08), 0 2px 6px rgba(15, 23, 42, 0.04);
            --cobalt-cut-size: 14px;
        }

        [data-theme="dark"] {
            --cobalt-ink: #f8fafc;
            --cobalt-ink-muted: #94a3b8;
            --cobalt-paper: #0b1329;
            --cobalt-paper-2: #111e3f;
            --cobalt-paper-3: #182852;
            --cobalt-accent: #3b82f6;
            --cobalt-accent-hover: #60a5fa;
            --cobalt-accent-dark: #1d4ed8;
            --cobalt-rule: #1e293b;
            --cobalt-rule-strong: #334155;
            --cobalt-body-bg: #030712;
            --cobalt-shadow: 0 20px 50px -10px rgba(0, 0, 0, 0.6), 0 0 1px 1px rgba(255, 255, 255, 0.06);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--cobalt-sans);
            background: var(--cobalt-body-bg);
            color: var(--cobalt-ink);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            position: relative;
            overflow-x: clip;
            transition: background 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        /* HALLMARK PRELOADER VEIL */
        #loginPreloader {
            position: fixed;
            inset: 0;
            z-index: 99999;
            background: var(--cobalt-body-bg);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            pointer-events: none;
            transition: opacity 0.35s ease, visibility 0.35s ease;
        }
        #loginPreloader.is-loaded {
            opacity: 0;
            visibility: hidden;
        }
        .preloader-spinner {
            width: 36px;
            height: 36px;
            border: 3px solid var(--cobalt-rule);
            border-top-color: var(--cobalt-accent);
            border-radius: 50%;
            animation: preloaderSpin 0.7s linear infinite;
        }
        @keyframes preloaderSpin {
            to { transform: rotate(360deg); }
        }

        /* BACKGROUND TECH GRID */
        .login-grid-bg {
            position: absolute;
            inset: 0;
            background-image: 
                radial-gradient(circle at 50% 20%, rgba(37, 99, 235, 0.08), transparent 60%),
                linear-gradient(to right, var(--cobalt-rule) 1px, transparent 1px),
                linear-gradient(to bottom, var(--cobalt-rule) 1px, transparent 1px);
            background-size: 100% 100%, 40px 40px, 40px 40px;
            opacity: 0.6;
            pointer-events: none;
        }

        [data-theme="dark"] .login-grid-bg {
            opacity: 0.25;
            background-image: 
                radial-gradient(circle at 50% 20%, rgba(59, 130, 246, 0.12), transparent 70%),
                linear-gradient(to right, #1e293b 1px, transparent 1px),
                linear-gradient(to bottom, #1e293b 1px, transparent 1px);
        }

        /* FLOATING CONTROLS */
        .login-topbar {
            position: absolute;
            top: 20px;
            right: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 10;
        }

        .cobalt-theme-btn {
            background: var(--cobalt-paper);
            color: var(--cobalt-ink);
            border: 1px solid var(--cobalt-rule);
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .cobalt-theme-btn:hover {
            border-color: var(--cobalt-rule-strong);
            transform: translateY(-1px);
        }

        /* HALLMARK COBALT CUT LOGIN CARD */
        .login-card {
            background: var(--cobalt-paper);
            width: 100%;
            max-width: 440px;
            padding: 36px 32px;
            border: 1px solid var(--cobalt-rule);
            box-shadow: var(--cobalt-shadow);
            position: relative;
            z-index: 2;
            clip-path: polygon(
                var(--cobalt-cut-size) 0,
                100% 0,
                100% calc(100% - var(--cobalt-cut-size)),
                calc(100% - var(--cobalt-cut-size)) 100%,
                0 100%,
                0 var(--cobalt-cut-size)
            );
        }

        .login-header {
            text-align: center;
            margin-bottom: 24px;
        }

        .brand-badge {
            width: 52px;
            height: 52px;
            margin: 0 auto 14px auto;
            background: linear-gradient(135deg, var(--cobalt-accent), #1d4ed8);
            color: #ffffff;
            display: grid;
            place-items: center;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.35);
        }

        .brand-badge svg {
            width: 26px;
            height: 26px;
        }

        .login-title {
            font-family: var(--cobalt-display);
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: var(--cobalt-ink);
            margin-bottom: 6px;
        }

        .login-sub {
            font-size: 13px;
            color: var(--cobalt-ink-muted);
            line-height: 1.5;
        }

        /* DEMO CREDENTIALS BOX */
        .demo-box {
            background: var(--cobalt-paper-2);
            border: 1px solid var(--cobalt-rule);
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 22px;
            font-size: 12px;
            color: var(--cobalt-ink);
            line-height: 1.7;
        }

        .demo-box-title {
            font-weight: 700;
            font-family: var(--cobalt-display);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-size: 11px;
            color: var(--cobalt-accent);
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 6px;
        }

        .demo-pill-btn {
            background: var(--cobalt-paper);
            color: var(--cobalt-ink);
            border: 1px solid var(--cobalt-rule-strong);
            padding: 2px 8px;
            border-radius: 5px;
            font-size: 11px;
            font-weight: 700;
            font-family: var(--cobalt-mono);
            cursor: pointer;
            transition: all 0.15s ease;
            margin-left: 6px;
        }
        .demo-pill-btn:hover {
            background: var(--cobalt-accent);
            color: #ffffff;
            border-color: var(--cobalt-accent);
        }

        /* FORM CONTROLS */
        .form-group {
            margin-bottom: 18px;
        }

        .form-label {
            display: block;
            margin-bottom: 7px;
            font-size: 12px;
            font-weight: 700;
            color: var(--cobalt-ink-muted);
            letter-spacing: 0.02em;
        }

        .cobalt-input {
            width: 100%;
            padding: 12px 14px;
            border-radius: 8px;
            border: 1px solid var(--cobalt-rule-strong);
            background: var(--cobalt-paper);
            color: var(--cobalt-ink);
            font-size: 14px;
            font-family: var(--cobalt-sans);
            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .cobalt-input:focus {
            border-color: var(--cobalt-accent);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        /* 3D KEYCAP SUBMIT BUTTON (HALLMARK COBALT SPEC) */
        .keycap-submit {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            min-height: 48px;
            padding: 12px 20px;
            background: var(--cobalt-accent);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-family: var(--cobalt-display);
            font-size: 14.5px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 0 var(--cobalt-accent-dark), 0 8px 16px rgba(37, 99, 235, 0.25);
            transform: translateY(0);
            transition: transform 0.08s ease, box-shadow 0.08s ease, background 0.15s ease;
            margin-top: 6px;
        }
        .keycap-submit:hover {
            background: var(--cobalt-accent-hover);
        }
        .keycap-submit:active {
            transform: translateY(3px);
            box-shadow: 0 1px 0 var(--cobalt-accent-dark), 0 3px 6px rgba(37, 99, 235, 0.2);
        }

        /* GUEST / SCANNER TRAY */
        .guest-tray {
            margin-top: 22px;
            padding-top: 18px;
            border-top: 1px solid var(--cobalt-rule);
            text-align: center;
        }

        .guest-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            min-height: 44px;
            padding: 10px 16px;
            background: var(--cobalt-paper-2);
            color: var(--cobalt-ink);
            border: 1px solid var(--cobalt-rule);
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            box-shadow: 0 2px 0 var(--cobalt-rule-strong);
            transition: all 0.15s ease;
        }
        .guest-link:hover {
            border-color: var(--cobalt-accent);
            color: var(--cobalt-accent);
            background: var(--cobalt-paper-3);
        }

        /* ALERT */
        .login-alert {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.25);
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        @media (max-width: 480px) {
            body { padding: 16px; }
            .login-card { padding: 26px 20px; }
            .login-topbar { top: 12px; right: 12px; }
        }
    </style>
</head>
<body>

    <!-- HALLMARK PRELOADER VEIL -->
    <div id="loginPreloader">
        <div class="preloader-spinner"></div>
    </div>

    <!-- TECH GRID BG -->
    <div class="login-grid-bg" aria-hidden="true"></div>

    <!-- FLOATING THEME TOGGLE -->
    <div class="login-topbar">
        <button type="button" class="cobalt-theme-btn" id="themeToggleBtn" onclick="toggleLoginTheme()">
            <span id="themeToggleIcon" style="display: inline-flex; align-items: center;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
            </span>
            <span id="themeToggleLabel">Giao diện tối</span>
        </button>
    </div>

    <!-- MAIN LOGIN CARD -->
    <main class="login-card" id="loginCard">
        <div class="login-header">
            <div class="brand-badge">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="2"></circle>
                    <path d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"></path>
                </svg>
            </div>
            <h1 class="login-title">Đăng nhập EduRadar</h1>
            <p class="login-sub">Hệ thống đánh giá & cảnh báo sớm học lực THCS<br>(Chuẩn VnEdu & Thông tư 22/2021/TT-BGDĐT)</p>
        </div>

        <!-- QUICK SURVEY DEMO CREDENTIALS -->
        <div class="demo-box">
            <div class="demo-box-title">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                <span>Tài khoản khảo sát (Mật khẩu: 123456)</span>
            </div>
            <div>&bull; Quản trị viên: <code>admin</code> <button type="button" class="demo-pill-btn" onclick="fillForm('admin')">Điền Admin</button></div>
            <div>&bull; GV Toán: <code>gv_toan</code> <button type="button" class="demo-pill-btn" onclick="fillForm('gv_toan')">Điền GV Toán</button></div>
            <div>&bull; GV Văn: <code>gv_van</code> <button type="button" class="demo-pill-btn" onclick="fillForm('gv_van')">Điền GV Văn</button></div>
        </div>

        <?php if ($error): ?>
            <div class="login-alert">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- LOGIN FORM -->
        <form method="POST" action="" id="loginForm">
            <div class="form-group">
                <label for="uInput" class="form-label">TÀI KHOẢN (TÊN ĐĂNG NHẬP / VNEDU)</label>
                <input id="uInput" type="text" name="username" class="cobalt-input" placeholder="admin, gv_toan, gv_van" required autofocus autocomplete="username">
            </div>
            <div class="form-group">
                <label for="pInput" class="form-label">MẬT KHẨU</label>
                <input id="pInput" type="password" name="password" class="cobalt-input" placeholder="••••••••" required autocomplete="current-password">
            </div>
            <button type="submit" class="keycap-submit" id="btnSubmitLogin">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                <span>Đăng nhập hệ thống</span>
            </button>
        </form>

        <!-- SECONDARY GUEST SCANNER LINK -->
        <div class="guest-tray">
            <p style="font-size: 12px; color: var(--cobalt-ink-muted); margin-bottom: 10px;">Chưa có tài khoản trường hoặc cần trải nghiệm tức thì?</p>
            <a href="quet_nhanh.php" class="guest-link">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                <span>Quét 1 Chạm Bảng Điểm VnEdu (Không cần đăng nhập)</span>
            </a>
        </div>
    </main>

    <script>
    function fillForm(username) {
        document.getElementById('uInput').value = username;
        document.getElementById('pInput').value = '123456';
        if (window.gsap) {
            gsap.from('#uInput, #pInput', {
                backgroundColor: 'rgba(37, 99, 235, 0.15)',
                duration: 0.4,
                ease: 'power2.out'
            });
        }
    }

    function updateToggleBtn() {
        const current = document.documentElement.getAttribute('data-theme') || 'light';
        const icon = document.getElementById('themeToggleIcon');
        const label = document.getElementById('themeToggleLabel');
        if (current === 'dark') {
            if (icon) icon.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>';
            if (label) label.textContent = 'Giao diện sáng';
        } else {
            if (icon) icon.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>';
            if (label) label.textContent = 'Giao diện tối';
        }
    }

    function toggleLoginTheme() {
        const current = document.documentElement.getAttribute('data-theme') || 'light';
        const next = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('eduradar_theme', next);
        updateToggleBtn();
    }

    // COORDINATED PRELOADER & GSAP ENTRANCE
    window.addEventListener('load', function() {
        const preloader = document.getElementById('loginPreloader');
        if (preloader) {
            preloader.classList.add('is-loaded');
        }

        if (window.gsap) {
            gsap.from('#loginCard', {
                opacity: 0,
                y: 20,
                scale: 0.98,
                duration: 0.6,
                ease: 'power3.out'
            });
            gsap.from('.brand-badge', {
                scale: 0,
                rotation: -10,
                duration: 0.5,
                delay: 0.15,
                ease: 'back.out(1.7)'
            });
            gsap.from('.demo-box, .form-group, #btnSubmitLogin, .guest-tray', {
                opacity: 0,
                y: 12,
                duration: 0.45,
                stagger: 0.07,
                delay: 0.2,
                ease: 'power2.out'
            });
        }
    });

    document.addEventListener('DOMContentLoaded', updateToggleBtn);
    </script>
</body>
</html>
