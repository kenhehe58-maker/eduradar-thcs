<?php
// 404.php - Trang Báo Lỗi 404 Chuẩn Giao Diện EduRadar THCS
http_response_code(404);
$pageTitle = '404 &mdash; Không Tìm Thấy Trang';
$breadcrumbs = [
    ['title' => 'Trang Chủ', 'url' => 'welcome.php'],
    ['title' => 'Lỗi 404', 'url' => '']
];
require_once __DIR__ . '/includes/header.php';
?>

<div style="min-height: 55vh; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 40px 20px;">
    <div style="width: 72px; height: 72px; border-radius: 20px; background: rgba(37, 99, 235, 0.1); color: #2563eb; display: grid; place-items: center; font-size: 32px; font-weight: 800; margin-bottom: 20px;">
        404
    </div>
    <h1 style="font-size: 26px; font-weight: 800; color: var(--text-heading, #0f172a); margin: 0 0 10px 0; letter-spacing: -0.02em;">
        Không tìm thấy trang yêu cầu
    </h1>
    <p style="font-size: 14px; color: var(--text-muted, #64748b); max-width: 480px; line-height: 1.6; margin: 0 0 24px 0;">
        Đường dẫn bạn vừa truy cập không tồn tại, đã bị di dời hoặc bạn chưa được cấp quyền thao tác trên phân hệ này.
    </p>
    <div style="display: flex; gap: 12px; flex-wrap: wrap; justify-content: center;">
        <a href="welcome.php" class="btn primary" style="background: #2563eb; color: #ffffff; padding: 11px 22px; border-radius: 12px; font-weight: 700; font-size: 13.5px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3);">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <span>Quay về Trang Chủ</span>
        </a>
        <a href="index.php" class="btn" style="background: var(--card-bg, #ffffff); color: var(--text-heading, #0f172a); border: 1.5px solid var(--border-color, #cbd5e1); padding: 11px 22px; border-radius: 12px; font-weight: 700; font-size: 13.5px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            <span>Mở Bảng Cảnh Báo</span>
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
