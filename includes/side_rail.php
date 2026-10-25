<?php
// includes/side_rail.php - Edexa University Modern Navigation Sidebar (230px Fixed Left)
// Synthesis of Dribbble Edexa University Dashboard + Apple Fluid Physics
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
$userRole = $_SESSION['role'] ?? 'guest';
$userName = $_SESSION['full_name'] ?? ($_SESSION['username'] ?? 'Khách Trải Nghiệm');
$userRoleTitle = ($userRole === 'admin') ? 'Quản Trị Viên Trường' : (($userRole === 'teacher') ? 'Giáo Viên Bộ Môn' : 'Khách Dùng Thử');
$activeNav = $activeNav ?? 'dashboard';

// Giữ ngữ cảnh lớp & môn khi chuyển đổi giữa các trang (Cô lập theo tài khoản đang đăng nhập)
$railUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$railSavedCtx = ($railUserId > 0 && function_exists('get_user_nav_context'))
    ? get_user_nav_context($railUserId)
    : ['class_id' => 0, 'subject' => ''];

$ctxClassId = (int)($_GET['class_id'] ?? $railSavedCtx['class_id'] ?? 0);
$ctxSubject = trim((string)($_GET['subject'] ?? $railSavedCtx['subject'] ?? ''));
$navQuery = '';
if ($userRole !== 'guest' && ($ctxClassId > 0 || ($ctxSubject !== '' && $ctxSubject !== 'all'))) {
    $qp = [];
    if ($ctxClassId > 0) $qp['class_id'] = $ctxClassId;
    if ($ctxSubject !== '' && $ctxSubject !== 'all') $qp['subject'] = $ctxSubject;
    if (!empty($qp)) $navQuery = '?' . http_build_query($qp);
}
?>
<style>
/* =========================================================================
   EDEXA UNIVERSITY MODERN SIDEBAR NAVIGATION (230px)
   ========================================================================= */
.ux-rail-container {
  position: fixed;
  left: 0;
  top: 0;
  bottom: 0;
  width: 230px;
  z-index: 1020;
  background: var(--card-bg, #ffffff);
  border-right: 1px solid var(--border-subtle, #e2e8f0);
  box-shadow: 2px 0 16px rgba(15, 23, 42, 0.03);
  display: flex;
  flex-direction: column;
  user-select: none;
  transition: background-color 0.2s ease, border-color 0.2s ease;
}
.u040-fixed {
  height: 100%;
  width: 100%;
  display: flex;
  flex-direction: column;
}
.u040-rail {
  height: 100%;
  width: 100%;
  display: flex;
  flex-direction: column;
  padding: 20px 14px 18px 14px;
}
.u040-top {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 4px 6px 20px 6px;
  border-bottom: 1px solid var(--border-subtle, #f1f5f9);
  margin-bottom: 16px;
}
.u040-logo {
  width: 42px;
  height: 42px;
  flex: none;
  border-radius: 12px;
  background: linear-gradient(135deg, #2563eb, #3b82f6);
  display: grid;
  place-items: center;
  font-size: 20px;
  box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3);
  text-decoration: none;
  color: #ffffff;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.u040-logo:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 18px rgba(37, 99, 235, 0.4);
}
.u040-brand-info {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.u040-brand-title {
  font-size: 15px;
  font-weight: 800;
  color: var(--text-heading, #0f172a);
  letter-spacing: -0.02em;
  line-height: 1.2;
}
.u040-brand-sub {
  font-size: 11px;
  font-weight: 600;
  color: var(--text-subtle, #64748b);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

/* NAVIGATION MENU */
.u040-nav {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
  flex: 1;
  overflow-y: auto;
}
.u040-nav li {
  width: 100%;
}
.u040-it {
  position: relative;
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  height: 44px;
  padding: 0 14px;
  border-radius: 12px;
  font-size: 13.5px;
  font-weight: 600;
  color: var(--text-muted, #475569);
  text-decoration: none;
  transition: background-color 0.18s ease, color 0.18s ease, transform 0.18s ease;
}
.u040-it:hover {
  background: var(--surface-hover, #f1f5f9);
  color: var(--text-heading, #0f172a);
  transform: translateX(2px);
}
.u040-it svg {
  width: 20px;
  height: 20px;
  flex: none;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
  transition: stroke 0.18s ease;
}
.u040-it-label {
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.u040-it[aria-current="page"] {
  background: #2563eb;
  color: #ffffff;
  box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3);
  font-weight: 700;
}
.u040-it[aria-current="page"] svg {
  stroke: #ffffff;
}

/* BADGE COUNTER IN EDEXA STYLE */
.u040-bd {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 2px 7px;
  border-radius: 9999px;
  background: #eff6ff;
  color: #2563eb;
  font-size: 11px;
  font-weight: 800;
  line-height: 1;
}
.u040-it[aria-current="page"] .u040-bd {
  background: rgba(255, 255, 255, 0.25);
  color: #ffffff;
}

/* DOCK TOOLTIP FOR ACCESSIBILITY & TEST COMPATIBILITY */
.u040-dock-tip {
  display: none;
}

/* FOOTER SECTION: USER PROFILE & EDEXA PILL LOGOUT */
.u040-footer-sec {
  margin-top: auto;
  padding-top: 14px;
  border-top: 1px solid var(--border-subtle, #f1f5f9);
  display: flex;
  flex-direction: column;
  gap: 12px;
  width: 100%;
}
.u040-me {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
  border-radius: 12px;
  background: var(--bg-main, #f8fafc);
  border: 1px solid var(--border-subtle, #e2e8f0);
  transition: background-color 0.2s ease;
}
.u040-me:hover {
  background: var(--surface-hover, #f1f5f9);
}
.u040-me i {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: linear-gradient(135deg, #2563eb, #60a5fa);
  color: #ffffff;
  display: grid;
  place-items: center;
  font-style: normal;
  font-weight: 800;
  font-size: 13px;
  flex: none;
}
.u040-me-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
  flex: 1;
}
.u040-me-name {
  font-size: 12.5px;
  font-weight: 700;
  color: var(--text-heading, #0f172a);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.u040-me-role {
  font-size: 11px;
  font-weight: 500;
  color: var(--text-subtle, #64748b);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* EDEXA ROYAL BLUE PILL LOGOUT BUTTON (EXACTLY 1 IN SYSTEM) */
.u040-logout-it {
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  gap: 8px !important;
  width: 100% !important;
  height: 42px !important;
  border-radius: 12px !important;
  background: #2563eb !important;
  color: #ffffff !important;
  font-size: 13.5px !important;
  font-weight: 700 !important;
  box-shadow: 0 4px 14px rgba(37, 99, 235, 0.25) !important;
  transition: all 0.2s ease !important;
  text-decoration: none !important;
}
.u040-logout-it:hover {
  background: #1d4ed8 !important;
  box-shadow: 0 6px 18px rgba(29, 78, 216, 0.35) !important;
  transform: translateY(-1px) !important;
}
.u040-logout-it svg {
  stroke: #ffffff !important;
  width: 18px !important;
  height: 18px !important;
}

@media (max-width: 900px) {
  .ux-rail-container {
    width: 68px;
  }
  .u040-rail {
    padding: 14px 8px;
    align-items: center;
  }
  .u040-brand-info,
  .u040-it-label,
  .u040-me-text,
  .u040-logout-text {
    display: none !important;
  }
  .u040-it {
    padding: 0;
    justify-content: center;
    width: 44px;
    height: 44px;
  }
  .u040-me {
    padding: 0;
    border: none;
    background: transparent;
    justify-content: center;
  }
  .u040-logout-it {
    width: 44px !important;
    height: 44px !important;
    padding: 0 !important;
  }
}
.u040-logout-it {
  margin-top: 10px;
  background: rgba(239, 68, 68, 0.12) !important;
  color: #ef4444 !important;
  border: 1px solid rgba(239, 68, 68, 0.28) !important;
  font-weight: 700 !important;
}
.u040-logout-it:hover {
  background: #dc2626 !important;
  color: #ffffff !important;
  border-color: #dc2626 !important;
  box-shadow: 0 4px 14px rgba(220, 38, 38, 0.35) !important;
}
.u040-logout-it svg {
  stroke: currentColor !important;
}
[data-theme="dark"] .u040-logout-it {
  background: rgba(239, 68, 68, 0.16) !important;
  color: #f87171 !important;
  border-color: rgba(239, 68, 68, 0.35) !important;
}
[data-theme="dark"] .u040-logout-it:hover {
  background: #dc2626 !important;
  color: #ffffff !important;
  border-color: #dc2626 !important;
}
</style>

<aside class="ux-rail-container u208-wrapper" aria-label="Side Navigation Rail">
  <div class="u040-fixed" id="u040RailWrapper">
    <nav class="u040-rail u208-dk" role="toolbar" aria-label="Main menu">
      <div class="u040-top">
        <a href="welcome.php" class="u040-logo" title="Trang Chào Mừng & Khởi Động EduRadar" aria-label="Trang Chào Mừng EduRadar">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
            <path d="M6 12v5c3 3 9 3 12 0v-5"/>
          </svg>
        </a>
        <div class="u040-brand-info">
          <a href="welcome.php" style="text-decoration: none; color: inherit;">
            <span class="u040-brand-title">EDURADAR</span>
            <span class="u040-brand-sub">Hệ Thống THCS</span>
          </a>
        </div>
      </div>

      <!-- Navigation List with Edexa Active Pill Styling -->
      <ul class="u040-nav" id="u040NavList">
        <li>
          <a href="welcome.php" class="u040-it" <?= $activeNav === 'welcome' ? 'aria-current="page"' : '' ?> aria-label="Trang Chủ">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <span class="u040-it-label">Trang Chủ</span>
            <span class="u040-dock-tip">Trang Chủ</span>
          </a>
        </li>
        <li>
          <a href="index.php<?= $navQuery ?>" class="u040-it" <?= $activeNav === 'dashboard' ? 'aria-current="page"' : '' ?> aria-label="Cảnh Báo Sớm">
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></svg>
            <span class="u040-it-label">Cảnh Báo Sớm</span>
            <span class="u040-dock-tip">Cảnh Báo Sớm 2 Khung</span>
          </a>
        </li>
        <li>
          <a href="nhap_diem.php<?= $navQuery ?>" class="u040-it" <?= $activeNav === 'gradebook' ? 'aria-current="page"' : '' ?> aria-label="Sổ Điểm">
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="3"/><path d="M3 10h18M8 4v16M14 4v16"/></svg>
            <span class="u040-it-label">Sổ Điểm</span>
            <span class="u040-dock-tip">Sổ Điểm Bộ Môn VnEdu</span>
          </a>
        </li>
        <li>
          <a href="quet_nhanh.php" class="u040-it" <?= $activeNav === 'guest' ? 'aria-current="page"' : '' ?> aria-label="Quét Nhanh AI">
            <svg viewBox="0 0 24 24" aria-hidden="true"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
            <span class="u040-it-label">Quét Nhanh AI</span>
            <span class="u040-bd" aria-label="Mới">Mới</span>
            <span class="u040-dock-tip">Quét Nhanh Bảng Điểm AI</span>
          </a>
        </li>
        <li>
          <a href="bao_cao_slide_bgh.php<?= $navQuery ?>" class="u040-it" <?= $activeNav === 'slides' ? 'aria-current="page"' : '' ?> aria-label="Báo Cáo & Thống Kê">
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
            <span class="u040-it-label">Báo Cáo & Thống Kê</span>
            <span class="u040-dock-tip">Báo Cáo & Thống Kê BGH</span>
          </a>
        </li>
        <?php if ($userRole === 'admin'): ?>
          <li>
            <a href="admin.php" class="u040-it" <?= $activeNav === 'admin' ? 'aria-current="page"' : '' ?> aria-label="Cổng Quản Trị Nhà Trường">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l7 4v6c0 5-3.5 9.5-7 10-3.5-.5-7-5-7-10V6l7-4z"/><circle cx="12" cy="11" r="2.5"/></svg>
              <span class="u040-it-label">Cổng Quản Trị</span>
              <span class="u040-dock-tip">Cổng Quản Trị Admin</span>
            </a>
          </li>
        <?php endif; ?>
      </ul>

      <!-- User Profile & Single Logout Section at Bottom -->
      <div class="u040-footer-sec">
        <!-- User Avatar with Profile Info -->
        <div class="u040-me" tabindex="0" role="button" aria-label="Hồ sơ người dùng">
          <i aria-hidden="true"><?= mb_strtoupper(mb_substr($userName, 0, 1, 'UTF-8'), 'UTF-8') ?></i>
          <div class="u040-me-text">
            <span class="u040-me-name"><?= htmlspecialchars($userName) ?></span>
            <span class="u040-me-role"><?= htmlspecialchars($userRoleTitle) ?></span>
          </div>
          <span class="u040-dock-tip">
            <strong><?= htmlspecialchars($userName) ?></strong><br>
            <small style="opacity: 0.8; font-weight: 500;"><?= htmlspecialchars($userRoleTitle) ?></small>
          </span>
        </div>

        <?php if (!empty($_SESSION['user_id'])): ?>
          <!-- THE ONLY LOGOUT BUTTON IN THE ENTIRE SYSTEM -->
          <a href="logout.php" class="u040-it u040-logout-it" title="Đăng Xuất Khỏi Hệ Thống" aria-label="Đăng Xuất">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
            <span class="u040-logout-text">Đăng Xuất</span>
            <span class="u040-dock-tip">🚪 Đăng Xuất</span>
          </a>
        <?php else: ?>
          <a href="login.php" class="u040-it u040-logout-it" title="Đăng Nhập" aria-label="Đăng Nhập">
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <span class="u040-logout-text">Đăng Nhập</span>
            <span class="u040-dock-tip">🔑 Đăng Nhập</span>
          </a>
        <?php endif; ?>
      </div>
    </nav>
  </div>
</aside>
