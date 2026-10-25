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
    <style>
        body { 
            font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
            justify-content: center; 
            align-items: center; 
            background: radial-gradient(circle at 50% 30%, #1e1b4b 0%, #090d16 100%); 
        }
        .login-card {
            background: #ffffff;
            width: 440px;
            max-width: 90%;
            border-radius: var(--radius-lg);
            padding: 38px 34px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.45);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .login-header { text-align: center; margin-bottom: 24px; }
        .login-header .logo-badge { font-size: 38px; margin-bottom: 8px; display: inline-block; }
        .login-header h2 { font-size: 22px; font-weight: 800; color: #0f172a; }
        .login-header p { font-size: 13px; color: #64748b; margin-top: 4px; }
        .demo-box {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: var(--radius-md);
            padding: 14px 16px;
            font-size: 12.5px;
            margin-bottom: 20px;
            line-height: 1.7;
        }
        .demo-fill-btn {
            background: #eef2ff;
            color: #4338ca;
            border: 1px solid #c7d2fe;
            padding: 2px 8px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 11px;
            font-weight: 700;
            margin-left: 6px;
        }
        .demo-fill-btn:hover { background: #e0e7ff; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <span class="logo-badge">🏫</span>
            <h2>CỔNG QUẢN LÝ ĐIỂM</h2>
            <p>Trường THCS/THPT – Lưu trữ trực tiếp trên MySQL</p>
        </div>

        <div class="demo-box">
            <strong>Bấm để tự điền tài khoản thử nghiệm (Pass: <code>123456</code>):</strong><br>
            • Admin: <code>admin</code> <button type="button" class="demo-fill-btn" onclick="fillForm('admin')">Điền Admin</button><br>
            • GV Toán: <code>gv_toan</code> <button type="button" class="demo-fill-btn" onclick="fillForm('gv_toan')">Điền GV Toán</button><br>
            • GV Ngữ Văn: <code>gv_van</code> <button type="button" class="demo-fill-btn" onclick="fillForm('gv_van')">Điền GV Văn</button>
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

        <div style="margin-top: 22px; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 18px;">
            <p style="font-size: 13px; color: #64748b; margin-bottom: 10px;">Chưa có tài khoản trường học hoặc muốn phân tích nhanh?</p>
            <a href="quet_nhanh.php" style="display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 11px 14px; border-radius: var(--radius-md); background: #f0fdf4; color: #166534; border: 1.5px solid #86efac; text-decoration: none; font-size: 13.5px; font-weight: 700; transition: all 0.2s;">
                ⚡ Trải Nghiệm Chế Độ Khách (Quét 1 Chạm VnEdu)
            </a>
        </div>
    </div>

    <script>
    function fillForm(username) {
        document.getElementById('uInput').value = username;
        document.getElementById('pInput').value = '123456';
    }
    </script>
</body>
</html>
