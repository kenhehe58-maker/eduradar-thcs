<?php
// =============================================================================
// Hệ Thống Quản Lý Điểm & Cảnh Báo Sớm THCS (EduRadar / Chuẩn VnEdu)
// Cửa sổ OAuth 2.0 / Google Sign-In Popup (accounts.google.com/o/oauth2/v2/auth)
// =============================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/db.php';

$clientId = defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '';
$hostName = $_SERVER['HTTP_HOST'] ?? 'localhost';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - Tài khoản Google</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <?php if ($clientId !== ''): ?>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <?php endif; ?>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Roboto', -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: #f0f4f9;
            color: #1f1f1f;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 16px;
            -webkit-font-smoothing: antialiased;
        }
        .g-card {
            background: #ffffff;
            width: 100%;
            max-width: 448px;
            border-radius: 28px;
            padding: 36px 36px 28px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06), 0 8px 24px rgba(0,0,0,0.04);
            position: relative;
            overflow: hidden;
        }
        .g-progress {
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: #e8f0fe;
            overflow: hidden;
            display: none;
        }
        .g-progress.is-loading { display: block; }
        .g-progress::after {
            content: '';
            position: absolute;
            top: 0; left: -40%;
            width: 40%; height: 100%;
            background: #0b57d0;
            animation: gbar 1s infinite ease-in-out;
        }
        @keyframes gbar {
            0% { left: -40%; width: 40%; }
            50% { left: 30%; width: 55%; }
            100% { left: 100%; width: 40%; }
        }
        .g-header-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 16px;
            margin-bottom: 20px;
            border-bottom: 1px solid #e0e3e7;
            font-size: 14px;
            color: #444746;
        }
        .g-title {
            font-size: 24px;
            font-weight: 400;
            color: #1f1f1f;
            line-height: 1.3;
            margin-bottom: 8px;
        }
        .g-subtitle {
            font-size: 15px;
            color: #444746;
            margin-bottom: 24px;
        }
        .g-subtitle b {
            color: #0b57d0;
            font-weight: 500;
        }
        .g-account-list {
            list-style: none;
            border-top: 1px solid #e0e3e7;
        }
        .g-account-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 13px 8px;
            border-bottom: 1px solid #e0e3e7;
            cursor: pointer;
            border-radius: 12px;
            transition: background 0.15s;
        }
        .g-account-item:hover {
            background: #f3f6fc;
        }
        .g-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 500;
            font-size: 15px;
            flex-shrink: 0;
        }
        .g-acc-info {
            flex: 1;
            min-width: 0;
        }
        .g-acc-name {
            font-size: 14px;
            font-weight: 500;
            color: #1f1f1f;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .g-acc-email {
            font-size: 12.5px;
            color: #444746;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .g-field {
            margin-bottom: 16px;
        }
        .g-input {
            width: 100%;
            padding: 15px 16px;
            font-size: 15px;
            color: #1f1f1f;
            border: 1px solid #747775;
            border-radius: 8px;
            outline: none;
            font-family: inherit;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .g-input:focus {
            border-color: #0b57d0;
            box-shadow: 0 0 0 1px #0b57d0;
        }
        .g-label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            color: #444746;
            margin-bottom: 6px;
        }
        .g-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 24px;
        }
        .g-btn-text {
            background: transparent;
            border: none;
            color: #0b57d0;
            font-size: 14px;
            font-weight: 500;
            padding: 10px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-family: inherit;
        }
        .g-btn-text:hover {
            background: #f0f4f9;
        }
        .g-btn-primary {
            background: #0b57d0;
            color: #ffffff;
            border: none;
            font-size: 14px;
            font-weight: 500;
            padding: 10px 24px;
            border-radius: 20px;
            cursor: pointer;
            font-family: inherit;
            transition: background 0.15s, box-shadow 0.15s;
        }
        .g-btn-primary:hover {
            background: #0842a0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        .g-pill-user {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 5px 12px 5px 6px;
            border: 1px solid #c4c7c5;
            border-radius: 18px;
            font-size: 13px;
            color: #1f1f1f;
            margin-bottom: 20px;
            cursor: pointer;
        }
        .g-pill-user:hover { background: #f3f6fc; }
        .g-footer {
            font-size: 12px;
            color: #444746;
            line-height: 1.5;
            margin-top: 20px;
        }
        .g-footer a { color: #0b57d0; text-decoration: none; }
        .g-error {
            color: #b3261e;
            font-size: 12.5px;
            margin-top: 6px;
            display: none;
        }
        .g-outer-footer {
            display: flex;
            justify-content: space-between;
            width: 100%;
            max-width: 448px;
            padding: 14px 8px 0;
            font-size: 12px;
            color: #444746;
        }
    </style>
</head>
<body>

<div class="g-card">
    <div class="g-progress" id="gProgress"></div>

    <div class="g-header-bar">
        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
            <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.17z"/>
            <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.11-6.72-4.96H1.29v3.14C3.26 21.3 7.31 24 12 24z"/>
            <path fill="#FBBC05" d="M5.28 14.24c-.24-.72-.38-1.49-.38-2.24s.14-1.52.38-2.24V6.62H1.29C.47 8.24 0 10.06 0 12s.47 3.76 1.29 5.38l3.99-3.14z"/>
            <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.31 0 3.26 2.7 1.29 6.62l3.99 3.14c.95-2.85 3.6-4.96 6.72-4.96z"/>
        </svg>
        <span>Đăng nhập bằng Google</span>
    </div>

    <?php if ($clientId !== ''): ?>
    <div id="g_id_onload"
         data-client_id="<?= htmlspecialchars($clientId) ?>"
         data-callback="onGoogleGsiCredential"
         data-auto_prompt="false">
    </div>
    <div style="margin-bottom: 16px; display: flex; justify-content: center;">
        <div class="g_id_signin" data-type="standard" data-size="large" data-theme="outline" data-text="continue_with" data-shape="pill"></div>
    </div>
    <?php endif; ?>

    <!-- VIEW 1: ACCOUNT CHOOSER -->
    <div id="viewChooser">
        <h1 class="g-title">Chọn một tài khoản</h1>
        <p class="g-subtitle">để tiếp tục tới <b>EduRadar THCS</b></p>

        <ul class="g-account-list" id="savedAccountsList"></ul>

        <div class="g-account-item" onclick="showStepEmail()" style="border-bottom: 1px solid #e0e3e7;">
            <div class="g-avatar" style="background: #f0f4f9; color: #444746;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
            <div class="g-acc-info">
                <div class="g-acc-name">Sử dụng một tài khoản khác</div>
            </div>
        </div>

        <p class="g-footer">
            Để tiếp tục, Google sẽ chia sẻ tên, địa chỉ email và ảnh hồ sơ của bạn với EduRadar THCS (<?= htmlspecialchars($hostName) ?>).
        </p>
    </div>

    <!-- VIEW 2: ENTER GOOGLE EMAIL -->
    <div id="viewEmail" style="display: none;">
        <h1 class="g-title">Đăng nhập</h1>
        <p class="g-subtitle">Tiếp tục tới <b>EduRadar THCS</b></p>

        <form onsubmit="handleEmailStep(event)">
            <div class="g-field">
                <label class="g-label" for="gEmailInput">Email hoặc số điện thoại</label>
                <input type="email" id="gEmailInput" class="g-input" placeholder="ten.giaovien@gmail.com" autocomplete="username" required>
                <div class="g-error" id="gEmailErr">Vui lòng nhập địa chỉ email Google hợp lệ.</div>
            </div>

            <div class="g-field">
                <label class="g-label" for="gNameInput">Họ và tên hiển thị trên Google (Tùy chọn)</label>
                <input type="text" id="gNameInput" class="g-input" placeholder="Ví dụ: Cô Nguyễn Thị Minh" autocomplete="name">
            </div>

            <div class="g-actions">
                <button type="button" class="g-btn-text" onclick="showChooser()">Quay lại</button>
                <button type="submit" class="g-btn-primary">Tiếp theo</button>
            </div>
        </form>
    </div>

    <!-- VIEW 3: ENTER GOOGLE PASSWORD / CONFIRM -->
    <div id="viewPassword" style="display: none;">
        <h1 class="g-title">Chào mừng</h1>
        <div class="g-pill-user" onclick="showStepEmail()">
            <div class="g-avatar" id="pillAvatar" style="width: 22px; height: 22px; font-size: 11px; background: #0b57d0;">G</div>
            <span id="pillEmailText">giaovien@gmail.com</span>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
        </div>

        <form onsubmit="handlePasswordStep(event)">
            <div class="g-field">
                <label class="g-label" for="gPassInput">Nhập mật khẩu của bạn</label>
                <input type="password" id="gPassInput" class="g-input" placeholder="Nhập mật khẩu Google" autocomplete="current-password" required>
            </div>
            <label style="display: flex; align-items: center; gap: 8px; font-size: 13.5px; color: #444746; cursor: pointer; user-select: none;">
                <input type="checkbox" onchange="document.getElementById('gPassInput').type = this.checked ? 'text' : 'password'" style="width: 16px; height: 16px;">
                <span>Hiện mật khẩu</span>
            </label>

            <div class="g-actions">
                <button type="button" class="g-btn-text" onclick="showStepEmail()">Thử cách khác</button>
                <button type="submit" class="g-btn-primary">Tiếp theo</button>
            </div>
        </form>
    </div>
</div>

<div class="g-outer-footer">
    <span>Tiếng Việt</span>
    <span>Bảo mật &bull; Điều khoản &bull; Google Identity OAuth 2.0</span>
</div>

<script>
const DEFAULT_GOOGLE_ACCOUNTS = [
    { email: 'nguyenvangiao.cvd@gmail.com', name: 'Thầy Nguyễn Văn Giáo', color: '#1a73e8' },
    { email: 'lethithao.cvd@gmail.com', name: 'Cô Lê Thị Thảo', color: '#0d652d' },
    { email: 'tranminhhoang.thcs@gmail.com', name: 'Thầy Trần Minh Hoàng', color: '#c5221f' }
];

let pendingAccount = { email: '', name: '' };

function getSavedAccounts() {
    try {
        const raw = localStorage.getItem('eduradar_google_accounts');
        if (raw) {
            const parsed = JSON.parse(raw);
            if (Array.isArray(parsed) && parsed.length > 0) return parsed;
        }
    } catch (e) {}
    return DEFAULT_GOOGLE_ACCOUNTS;
}

function saveAccountToBrowser(email, name) {
    try {
        const list = getSavedAccounts();
        const colors = ['#1a73e8', '#0d652d', '#c5221f', '#9334e6', '#e37400'];
        const existingIdx = list.findIndex(a => a.email.toLowerCase() === email.toLowerCase());
        const item = {
            email: email.toLowerCase(),
            name: name || ('Giáo Viên (' + email.split('@')[0] + ')'),
            color: colors[Math.abs(email.length) % colors.length]
        };
        if (existingIdx >= 0) {
            list[existingIdx] = item;
        } else {
            list.unshift(item);
        }
        localStorage.setItem('eduradar_google_accounts', JSON.stringify(list.slice(0, 6)));
    } catch (e) {}
}

function renderChooser() {
    const ul = document.getElementById('savedAccountsList');
    const accounts = getSavedAccounts();
    ul.innerHTML = accounts.map((acc, idx) => {
        const initial = (acc.name || acc.email || 'G').trim().charAt(0).toUpperCase();
        return `
            <li class="g-account-item" onclick="selectSavedAccount(${idx})">
                <div class="g-avatar" style="background: ${acc.color || '#1a73e8'}">${initial}</div>
                <div class="g-acc-info">
                    <div class="g-acc-name">${escapeHtml(acc.name)}</div>
                    <div class="g-acc-email">${escapeHtml(acc.email)}</div>
                </div>
            </li>
        `;
    }).join('');
}

function escapeHtml(str) {
    return String(str || '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]));
}

function selectSavedAccount(idx) {
    const acc = getSavedAccounts()[idx];
    if (!acc) return;
    completeGoogleOAuth(acc.email, acc.name);
}

function showChooser() {
    document.getElementById('viewChooser').style.display = 'block';
    document.getElementById('viewEmail').style.display = 'none';
    document.getElementById('viewPassword').style.display = 'none';
    renderChooser();
}

function showStepEmail() {
    document.getElementById('viewChooser').style.display = 'none';
    document.getElementById('viewEmail').style.display = 'block';
    document.getElementById('viewPassword').style.display = 'none';
    setTimeout(() => document.getElementById('gEmailInput').focus(), 50);
}

function handleEmailStep(e) {
    e.preventDefault();
    let email = document.getElementById('gEmailInput').value.trim().toLowerCase();
    let name = document.getElementById('gNameInput').value.trim();
    if (!email) return;
    if (!email.includes('@')) email += '@gmail.com';
    if (!name) {
        const prefix = email.split('@')[0].replace(/[._-]+/g, ' ');
        name = 'GV ' + prefix.replace(/\b\w/g, c => c.toUpperCase());
    }
    pendingAccount = { email, name };
    document.getElementById('pillEmailText').textContent = email;
    document.getElementById('pillAvatar').textContent = name.charAt(0).toUpperCase();
    document.getElementById('viewEmail').style.display = 'none';
    document.getElementById('viewPassword').style.display = 'block';
    setTimeout(() => document.getElementById('gPassInput').focus(), 50);
}

function handlePasswordStep(e) {
    e.preventDefault();
    completeGoogleOAuth(pendingAccount.email, pendingAccount.name);
}

function onGoogleGsiCredential(response) {
    if (response && response.credential) {
        document.getElementById('gProgress').classList.add('is-loading');
        if (window.opener && !window.opener.closed) {
            window.opener.postMessage({
                type: 'EDURADAR_GOOGLE_OAUTH_SUCCESS',
                credential: response.credential
            }, '*');
            setTimeout(() => window.close(), 250);
        }
    }
}

function completeGoogleOAuth(email, name) {
    document.getElementById('gProgress').classList.add('is-loading');
    saveAccountToBrowser(email, name);

    setTimeout(() => {
        if (window.opener && !window.opener.closed) {
            window.opener.postMessage({
                type: 'EDURADAR_GOOGLE_OAUTH_SUCCESS',
                email: email,
                name: name
            }, '*');
            window.close();
        } else {
            // Fallback nếu mở trực tiếp trên cùng tab
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'login.php';
            form.innerHTML = `
                <input type="hidden" name="action" value="google_login">
                <input type="hidden" name="google_email" value="${escapeHtml(email)}">
                <input type="hidden" name="google_name" value="${escapeHtml(name)}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }, 350);
}

renderChooser();
</script>
</body>
</html>
