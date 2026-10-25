<?php
// =============================================================================
// Hệ Thống Quản Lý Điểm & Cảnh Báo Sớm THCS (EduRadar / Chuẩn VnEdu)
// Trang đăng nhập bảo mật (Authentication & Access Control)
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

            // Điều hướng theo phân quyền (RBAC): Admin vào admin.php, Giáo viên vào index.php
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập – Hệ Thống Quản Lý Điểm</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
        (function() {
            const saved = localStorage.getItem('eduradar_theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', saved);
        })();
    </script>
    <style>
        body { 
            font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
            display: flex;
            justify-content: center; 
            align-items: center; 
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #f8fafc 0%, #eff6ff 100%); 
            position: relative;
            transition: background-color 0.3s ease;
        }
        [data-theme="dark"] body {
            background: #0b0f19 !important;
        }
        .login-card {
            background: #ffffff;
            width: 440px;
            max-width: 90%;
            border-radius: 20px;
            padding: 36px 32px;
            box-shadow: 0 12px 36px rgba(37, 99, 235, 0.08), 0 1px 3px rgba(15, 23, 42, 0.04);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        [data-theme="dark"] .login-card {
            background: #131b2e !important;
            border-color: #23324d !important;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5) !important;
        }
        .login-header { text-align: center; margin-bottom: 24px; }
        .login-header .logo-badge {
            width: 54px;
            height: 54px;
            margin: 0 auto 14px auto;
            border-radius: 16px;
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: #ffffff;
            display: grid;
            place-items: center;
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.35);
        }
        .login-header h2 { font-size: 21px; font-weight: 800; color: #0f172a; letter-spacing: -0.02em; margin: 0; }
        [data-theme="dark"] .login-header h2 { color: #f8fafc !important; }
        .login-header p { font-size: 13px; color: #64748b; margin-top: 6px; }
        [data-theme="dark"] .login-header p { color: #94a3b8 !important; }
        .demo-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px 16px;
            font-size: 12px;
            margin-bottom: 20px;
            line-height: 1.7;
            color: #334155;
        }
        [data-theme="dark"] .demo-box {
            background: #1a253c !important;
            border-color: #2e3f60 !important;
            color: #cbd5e1 !important;
        }
        [data-theme="dark"] .demo-box code {
            background: #0f172a !important;
            color: #93c5fd !important;
            padding: 2px 6px;
            border-radius: 4px;
        }
        .demo-fill-btn {
            background: #ffffff;
            color: #2563eb;
            border: 1px solid #bfdbfe;
            padding: 2px 8px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 11px;
            font-weight: 700;
            font-family: var(--font-mono, monospace);
            margin-left: 6px;
            transition: all 0.15s ease;
        }
        .demo-fill-btn:hover { background: #2563eb; color: #ffffff; border-color: #2563eb; }
        [data-theme="dark"] .demo-fill-btn {
            background: #253352 !important;
            color: #93c5fd !important;
            border-color: #3b82f6 !important;
        }
        [data-theme="dark"] .demo-fill-btn:hover {
            background: #2563eb !important;
            color: #ffffff !important;
        }
        .theme-toggle-floating {
            position: absolute;
            top: 20px;
            right: 20px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            color: #475569;
            padding: 8px 14px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease;
        }
        .theme-toggle-floating:hover {
            background: #f1f5f9;
            color: #0f172a;
        }
        [data-theme="dark"] .theme-toggle-floating {
            background: #131b2e !important;
            border-color: #23324d !important;
            color: #94a3b8 !important;
        }
        [data-theme="dark"] .theme-toggle-floating:hover {
            background: #1a253c !important;
            color: #f8fafc !important;
        }
        [data-theme="dark"] .login-divider {
            border-top-color: #23324d !important;
        }
        [data-theme="dark"] .btn-guest-login {
            background: rgba(16, 185, 129, 0.12) !important;
            border-color: rgba(16, 185, 129, 0.3) !important;
            color: #34d399 !important;
        }
        [data-theme="dark"] .btn-guest-login:hover {
            background: rgba(16, 185, 129, 0.2) !important;
        }
    </style>
</head>
<body>
    <button type="button" class="theme-toggle-floating" id="themeToggleBtn" onclick="toggleLoginTheme()">
        <span id="themeToggleIcon">🌙</span> <span id="themeToggleLabel">Dark Theme</span>
    </button>
    <div class="login-card">
        <div class="login-header">
            <div class="logo-badge">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="2"></circle>
                    <path d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"></path>
                </svg>
            </div>
            <h2>EDURADAR // AUTHENTICATION</h2>
            <p>Hệ thống Đánh giá &amp; Cảnh báo sớm THCS (Chuẩn VnEdu / TT22)</p>
        </div>

        <div class="demo-box">
            <span style="font-weight: 800; display: block; margin-bottom: 2px;">TÀI KHOẢN KHẢO SÁT HỆ THỐNG (Mật khẩu: <code>123456</code>):</span>
            &bull; Quản trị viên: <code>admin</code> <button type="button" class="demo-fill-btn" onclick="fillForm('admin')">Điền Admin</button><br>
            &bull; GV Toán: <code>gv_toan</code> <button type="button" class="demo-fill-btn" onclick="fillForm('gv_toan')">Điền GV Toán</button><br>
            &bull; GV Ngữ Văn: <code>gv_van</code> <button type="button" class="demo-fill-btn" onclick="fillForm('gv_van')">Điền GV Văn</button>
        </div>

        <?php if ($error): ?>
            <div class="alert red-alert mb-3" style="background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; padding: 12px; border-radius: 10px; font-weight: 600; font-size: 13px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group mb-3">
                <label>TÀI KHOẢN (USERNAME / VNEDU)</label>
                <input id="uInput" type="text" name="username" placeholder="Nhập admin, gv_toan hoặc gv_van" required autofocus>
            </div>
            <div class="form-group mb-3">
                <label>MẬT KHẨU</label>
                <input id="pInput" type="password" name="password" placeholder="Nhập mật khẩu" required>
            </div>
            <button type="submit" class="btn primary full-width" style="padding: 13px; font-size: 15px; justify-content: center;">
                Đăng Nhập Hệ Thống
            </button>
        </form>

        <div class="login-divider" style="margin-top: 22px; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 18px;">
            <p style="font-size: 13px; color: #64748b; margin-bottom: 10px;">Chưa có tài khoản trường học hoặc muốn phân tích nhanh?</p>
            <a href="quet_nhanh.php" class="btn-guest-login" style="display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 11px 14px; border-radius: var(--radius-md); background: #f0fdf4; color: #166534; border: 1.5px solid #86efac; text-decoration: none; font-size: 13.5px; font-weight: 700; transition: all 0.2s;">
                ⚡ Trải Nghiệm Chế Độ Khách (Quét 1 Chạm VnEdu)
            </a>
        </div>
    </div>

    <script>
    function fillForm(username) {
        document.getElementById('uInput').value = username;
        document.getElementById('pInput').value = '123456';
    }

    function updateToggleBtn() {
        const current = document.documentElement.getAttribute('data-theme') || 'light';
        const icon = document.getElementById('themeToggleIcon');
        const label = document.getElementById('themeToggleLabel');
        if (current === 'dark') {
            if (icon) icon.textContent = '☀️';
            if (label) label.textContent = 'Light Theme';
        } else {
            if (icon) icon.textContent = '🌙';
            if (label) label.textContent = 'Dark Theme';
        }
    }

    function toggleLoginTheme() {
        const current = document.documentElement.getAttribute('data-theme') || 'light';
        const next = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('eduradar_theme', next);
        updateToggleBtn();
    }
    document.addEventListener('DOMContentLoaded', updateToggleBtn);
    </script>
</body>
</html>
