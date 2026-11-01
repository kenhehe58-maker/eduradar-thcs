<?php
// includes/side_rail.php - Edexa University Modern Navigation Sidebar (230px Fixed Left)
// Synthesis of Dribbble Edexa University Dashboard + Apple Fluid Physics
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
require_once __DIR__ . '/icons.php';
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
if ($userRole !== 'guest' && $ctxClassId > 0 && $ctxSubject !== '' && $ctxSubject !== 'all') {
    $qp = ['class_id' => $ctxClassId, 'subject' => $ctxSubject];
    $navQuery = '?' . http_build_query($qp);
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
  border-right: var(--rule-hair, 1px) solid var(--color-rule, #e2e8f0);
  box-shadow: 2px 0 16px rgba(15, 23, 42, 0.03);
  display: flex;
  flex-direction: column;
  -webkit-user-select: none;
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
  border-bottom: var(--rule-hair, 1px) solid var(--color-rule-2, #f1f5f9);
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
  position: relative;
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  scrollbar-width: none;
  -ms-overflow-style: none;
}
.u040-nav::-webkit-scrollbar {
  display: none;
  width: 0;
  height: 0;
}
.u040-nav li {
  width: 100%;
  position: relative;
  z-index: 2;
}
/* Sliding Active & Hover Pill Indicators (Awwwards / Fluid Dock Style) */
.u040-sliding-pill {
  position: absolute;
  top: 0;
  left: 0;
  border-radius: 12px;
  background: var(--color-accent, #2563eb);
  box-shadow: var(--shadow-key-accent, 0 4px 14px rgba(37, 99, 235, 0.28));
  pointer-events: none;
  z-index: 1;
  opacity: 0;
  will-change: transform, width, height;
}
.u040-hover-pill {
  position: absolute;
  top: 0;
  left: 0;
  border-radius: 12px;
  background: var(--color-paper-2, var(--surface-hover, #f1f5f9));
  pointer-events: none;
  z-index: 0;
  opacity: 0;
  transition: transform 0.26s cubic-bezier(0.22, 1, 0.36, 1), width 0.26s cubic-bezier(0.22, 1, 0.36, 1), height 0.26s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.2s ease;
}
.u040-it {
  position: relative;
  z-index: 2;
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
  transition: background-color 0.22s cubic-bezier(0.22, 1, 0.36, 1), color 0.22s ease, transform 0.22s cubic-bezier(0.22, 1, 0.36, 1);
}
.u040-it:hover {
  background: var(--color-paper-2, var(--surface-hover, #f1f5f9));
  color: var(--color-ink, var(--text-heading, #0f172a));
  transform: translateX(2px);
}
.u040-nav.has-sliding-pill .u040-it:hover {
  background: transparent;
}
.u040-it:active {
  transform: scale(0.97);
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
  transition: stroke 0.22s ease, transform 0.22s cubic-bezier(0.22, 1, 0.36, 1);
}
.u040-it-label {
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.u040-it[aria-current="page"] {
  background: var(--color-accent, #2563eb);
  color: var(--color-accent-ink, #ffffff);
  box-shadow: var(--shadow-key-accent);
  font-weight: 700;
}
.u040-nav.has-sliding-pill .u040-it[aria-current="page"] {
  background: transparent !important;
  box-shadow: none !important;
}
.u040-it[aria-current="page"] svg {
  stroke: var(--color-accent-ink, #ffffff);
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
  border-top: var(--rule-hair, 1px) solid var(--color-rule-2, #f1f5f9);
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
  height: 44px !important;
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

@media (max-width: 900px) and (min-width: 769px) {
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
  .u040-logout-text,
  .u040-bd,
  .u040-dock-tip {
    display: none !important;
  }
  .u040-it {
    padding: 0;
    justify-content: center;
    width: 48px;
    height: 48px;
    overflow: hidden;
  }
  .u040-me {
    padding: 0;
    border: none;
    background: transparent;
    justify-content: center;
  }
  .u040-logout-it {
    width: 48px !important;
    height: 48px !important;
    padding: 0 !important;
  }
}

/* Mobile Bottom Navigation Bar (Apple Dock / Dribbble SaaS - Fitts Law >= 48px) */
@media (max-width: 768px) {
  .ux-rail-container {
    position: fixed !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    top: auto !important;
    width: 100% !important;
    height: calc(56px + env(safe-area-inset-bottom, 0px)) !important;
    min-height: calc(56px + env(safe-area-inset-bottom, 0px)) !important;
    z-index: 1050 !important;
    border-right: none !important;
    border-top: 1px solid rgba(226, 232, 240, 0.8) !important;
    background: rgba(255, 255, 255, 0.94) !important;
    backdrop-filter: blur(20px) saturate(180%) !important;
    -webkit-backdrop-filter: blur(20px) saturate(180%) !important;
    box-shadow: 0 -2px 10px rgba(15, 23, 42, 0.04) !important;
    padding-bottom: env(safe-area-inset-bottom, 0px) !important;
    box-sizing: border-box !important;
  }
  [data-theme="dark"] .ux-rail-container {
    background: rgba(15, 23, 42, 0.94) !important;
    border-top-color: #334155 !important;
    box-shadow: 0 -4px 16px rgba(0, 0, 0, 0.4) !important;
  }
  .u040-fixed {
    height: 100% !important;
    width: 100% !important;
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
  }
  .u040-rail {
    display: flex !important;
    flex-direction: row !important;
    justify-content: space-between !important;
    align-items: center !important;
    padding: 0 4px !important;
    width: 100% !important;
    height: 100% !important;
    box-sizing: border-box !important;
  }
  .u040-top,
  .u040-divider,
  .u040-me,
  .u040-brand-info,
  .u040-me-text,
  .u040-dock-tip,
  .u040-bd {
    display: none !important;
  }
  .u040-nav {
    position: relative !important;
    display: flex !important;
    flex-direction: row !important;
    justify-content: space-between !important;
    align-items: center !important;
    flex: <?= $userRole === 'admin' ? 5 : 4 ?> 1 0% !important;
    width: auto !important;
    margin: 0 !important;
    padding: 0 !important;
    list-style: none !important;
    gap: 2px !important;
    overflow: visible !important;
  }
  .u040-nav li {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: auto !important;
    flex: 1 1 0% !important;
    margin: 0 !important;
    padding: 0 !important;
    min-width: 0 !important;
  }
  .u040-footer-sec {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: auto !important;
    flex: 1 1 0% !important;
    margin: 0 !important;
    padding: 0 !important;
    border-top: none !important;
    min-width: 0 !important;
  }
  .u040-sliding-pill {
    border-radius: 10px !important;
    background: #eff6ff !important;
    box-shadow: none !important;
  }
  [data-theme="dark"] .u040-sliding-pill {
    background: rgba(59, 130, 246, 0.2) !important;
  }
  .u040-hover-pill {
    display: none !important;
  }
  /* Nav item styled: Fitts's Law touch target >= 48x48px */
  .u040-it {
    display: flex !important;
    flex-direction: column !important;
    justify-content: center !important;
    align-items: center !important;
    width: 100% !important;
    min-width: 48px !important;
    min-height: 48px !important;
    height: 48px !important;
    padding: 2px 2px !important;
    margin: 0 !important;
    border-radius: 8px !important;
    flex-shrink: 0 !important;
    gap: 2px !important;
    text-decoration: none !important;
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    transition: transform 0.12s ease, background-color 0.15s ease !important;
  }
  .u040-it:active {
    transform: scale(0.95) !important;
  }
  .u040-it svg {
    width: 18px !important;
    height: 18px !important;
    stroke: #64748b !important;
    stroke-width: 2px !important;
    fill: none !important;
    transition: stroke 0.15s ease, transform 0.15s ease !important;
  }
  .u040-it-label {
    display: block !important;
    font-size: 9px !important;
    font-weight: 600 !important;
    color: #64748b !important;
    line-height: 1 !important;
    text-align: center !important;
    white-space: nowrap !important;
    letter-spacing: -0.02em !important;
    transition: color 0.15s ease !important;
  }
  /* Active Tab: subtle rounded tint pill */
  .u040-it[aria-current="page"],
  .u040-it.active {
    background: #eff6ff !important;
  }
  [data-theme="dark"] .u040-it[aria-current="page"],
  [data-theme="dark"] .u040-it.active {
    background: rgba(59, 130, 246, 0.18) !important;
  }
  .u040-it[aria-current="page"] svg,
  .u040-it.active svg {
    stroke: #2563eb !important;
    transform: scale(1.08) !important;
  }
  [data-theme="dark"] .u040-it[aria-current="page"] svg,
  [data-theme="dark"] .u040-it.active svg {
    stroke: #60a5fa !important;
  }
  .u040-it[aria-current="page"] .u040-it-label,
  .u040-it.active .u040-it-label {
    color: #2563eb !important;
    font-weight: 800 !important;
  }
  [data-theme="dark"] .u040-it[aria-current="page"] .u040-it-label,
  [data-theme="dark"] .u040-it.active .u040-it-label {
    color: #93c5fd !important;
  }

  /* Single Logout Button on Mobile: Fitts's Law touch target >= 48x48px */
  .u040-logout-it {
    display: flex !important;
    flex-direction: column !important;
    justify-content: center !important;
    align-items: center !important;
    width: 100% !important;
    min-width: 48px !important;
    min-height: 48px !important;
    height: 48px !important;
    margin: 0 !important;
    padding: 2px 2px !important;
    border-radius: 8px !important;
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    gap: 2px !important;
    flex-shrink: 0 !important;
    transition: transform 0.12s ease !important;
  }
  .u040-logout-it:active {
    background: rgba(239, 68, 68, 0.1) !important;
    transform: scale(0.95) !important;
  }
  .u040-logout-it svg {
    stroke: #ef4444 !important;
    width: 18px !important;
    height: 18px !important;
  }
  .u040-logout-text {
    display: block !important;
    font-size: 9px !important;
    font-weight: 700 !important;
    color: #ef4444 !important;
    line-height: 1 !important;
    text-align: center !important;
    white-space: nowrap !important;
  }
}
@media (min-width: 769px) {
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
}
</style>

<aside class="ux-rail-container u208-wrapper" aria-label="Side Navigation Rail">
  <div class="u040-fixed" id="u040RailWrapper">
    <nav class="u040-rail u208-dk" role="toolbar" aria-label="Main menu">
      <div class="u040-top">
        <a href="welcome.php" class="u040-logo" title="Trang Chào Mừng & Khởi Động EduRadar" aria-label="Trang Chào Mừng EduRadar">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
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
        <div class="u040-hover-pill" id="u040HoverPill" aria-hidden="true"></div>
        <div class="u040-sliding-pill" id="u040SlidingPill" aria-hidden="true"></div>
        <li>
          <a href="welcome.php" class="u040-it" data-nav-key="welcome" <?= $activeNav === 'welcome' ? 'aria-current="page"' : '' ?> aria-label="Trang Chủ">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <span class="u040-it-label">Trang Chủ</span>
            <span class="u040-dock-tip">Trang Chủ</span>
          </a>
        </li>
        <li>
          <a href="index.php<?= $navQuery ?>" class="u040-it" data-nav-key="dashboard" <?= $activeNav === 'dashboard' ? 'aria-current="page"' : '' ?> aria-label="Cảnh Báo">
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></svg>
            <span class="u040-it-label">Cảnh Báo</span>
            <span class="u040-dock-tip">Cảnh Báo Điểm Số</span>
          </a>
        </li>
        <li>
          <a href="nhap_diem.php<?= $navQuery ?>" class="u040-it" data-nav-key="gradebook" <?= $activeNav === 'gradebook' ? 'aria-current="page"' : '' ?> aria-label="Sổ Điểm">
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="3"/><path d="M3 10h18M8 4v16M14 4v16"/></svg>
            <span class="u040-it-label">Sổ Điểm</span>
            <span class="u040-dock-tip">Sổ Điểm VnEdu</span>
          </a>
        </li>
        <li>
          <a href="quet_nhanh.php" class="u040-it" data-nav-key="guest" <?= $activeNav === 'guest' ? 'aria-current="page"' : '' ?> aria-label="Quét Nhanh">
            <svg viewBox="0 0 24 24" aria-hidden="true"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
            <span class="u040-it-label">Quét</span>
            <span class="u040-bd" aria-label="Mới">Mới</span>
            <span class="u040-dock-tip">Quét Nhanh 1 Chạm</span>
          </a>
        </li>
        <?php if ($userRole === 'admin'): ?>
          <li>
            <a href="admin.php" class="u040-it" data-nav-key="admin" <?= $activeNav === 'admin' ? 'aria-current="page"' : '' ?> aria-label="Quản Trị">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l7 4v6c0 5-3.5 9.5-7 10-3.5-.5-7-5-7-10V6l7-4z"/><circle cx="12" cy="11" r="2.5"/></svg>
              <span class="u040-it-label">Quản Trị</span>
              <span class="u040-dock-tip">Cổng Quản Trị Trường</span>
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
            <span class="u040-me-name"><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="u040-me-role"><?= htmlspecialchars($userRoleTitle, ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <span class="u040-dock-tip">
            <strong><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></strong><br>
            <small style="opacity: 0.8; font-weight: 500;"><?= htmlspecialchars($userRoleTitle, ENT_QUOTES, 'UTF-8') ?></small>
          </span>
        </div>

        <?php if (!empty($_SESSION['user_id'])): ?>
          <!-- THE ONLY LOGOUT BUTTON IN THE ENTIRE SYSTEM -->
          <a href="logout.php" class="u040-it u040-logout-it" title="Đăng Xuất Khỏi Hệ Thống" aria-label="Đăng Xuất">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
            <span class="u040-logout-text">Thoát</span>
            <span class="u040-dock-tip">Đăng Xuất</span>
          </a>
        <?php else: ?>
          <a href="login.php" class="u040-it u040-logout-it" title="Đăng Nhập" aria-label="Đăng Nhập">
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <span class="u040-logout-text">Đăng Nhập</span>
            <span class="u040-dock-tip">Đăng Nhập</span>
          </a>
        <?php endif; ?>
      </div>
    </nav>
  </div>
</aside>
<script>
(function() {
  var nav = document.getElementById('u040NavList');
  var pill = document.getElementById('u040SlidingPill');
  var hoverPill = document.getElementById('u040HoverPill');
  if (!nav || !pill) return;

  var items = Array.prototype.slice.call(nav.querySelectorAll('.u040-it'));
  var activeItem = nav.querySelector('.u040-it[aria-current="page"]');

  function getRelativeRect(el) {
    var navRect = nav.getBoundingClientRect();
    var elRect = el.getBoundingClientRect();
    return {
      x: Math.round(elRect.left - navRect.left),
      y: Math.round(elRect.top - navRect.top),
      w: Math.round(elRect.width),
      h: Math.round(elRect.height)
    };
  }

  function applyPillInstant(el, target) {
    var r = getRelativeRect(target);
    el.style.transition = 'none';
    el.style.width = r.w + 'px';
    el.style.height = r.h + 'px';
    el.style.transform = 'translate3d(' + r.x + 'px,' + r.y + 'px,0)';
    el.style.opacity = '1';
  }

  function animatePillTo(target, fromRect) {
    var toRect = getRelativeRect(target);
    nav.classList.add('has-sliding-pill');
    if (fromRect && (fromRect.x !== toRect.x || fromRect.y !== toRect.y)) {
      pill.style.transition = 'none';
      pill.style.width = fromRect.w + 'px';
      pill.style.height = fromRect.h + 'px';
      pill.style.transform = 'translate3d(' + fromRect.x + 'px,' + fromRect.y + 'px,0)';
      pill.style.opacity = '1';
      void pill.offsetWidth;
      pill.style.transition = 'transform 0.46s cubic-bezier(0.22, 1, 0.36, 1), width 0.46s cubic-bezier(0.22, 1, 0.36, 1), height 0.46s cubic-bezier(0.22, 1, 0.36, 1)';
      pill.style.width = toRect.w + 'px';
      pill.style.height = toRect.h + 'px';
      pill.style.transform = 'translate3d(' + toRect.x + 'px,' + toRect.y + 'px,0)';
    } else {
      applyPillInstant(pill, target);
    }
  }

  function initSlidingPill() {
    if (!activeItem) return;
    var prevKey = null;
    try { prevKey = sessionStorage.getItem('eduradar_prev_nav_key'); } catch (e) {}
    var currentKey = activeItem.getAttribute('data-nav-key');
    var prevEl = prevKey ? nav.querySelector('.u040-it[data-nav-key="' + prevKey + '"]') : null;
    if (prevEl && prevEl !== activeItem) {
      animatePillTo(activeItem, getRelativeRect(prevEl));
    } else {
      nav.classList.add('has-sliding-pill');
      applyPillInstant(pill, activeItem);
    }
    try { if (currentKey) sessionStorage.setItem('eduradar_prev_nav_key', currentKey); } catch (e) {}
  }

  items.forEach(function(item) {
    item.addEventListener('click', function() {
      var prevKey = activeItem ? activeItem.getAttribute('data-nav-key') : null;
      try { if (prevKey) sessionStorage.setItem('eduradar_prev_nav_key', prevKey); } catch (e) {}
      if (activeItem && item !== activeItem) {
        activeItem.removeAttribute('aria-current');
        item.setAttribute('aria-current', 'page');
        animatePillTo(item, getRelativeRect(activeItem));
        activeItem = item;
      }
    });
    if (hoverPill) {
      item.addEventListener('mouseenter', function() {
        if (window.innerWidth <= 768 || item === activeItem) {
          hoverPill.style.opacity = '0';
          return;
        }
        var r = getRelativeRect(item);
        hoverPill.style.width = r.w + 'px';
        hoverPill.style.height = r.h + 'px';
        hoverPill.style.transform = 'translate3d(' + r.x + 'px,' + r.y + 'px,0)';
        hoverPill.style.opacity = '1';
      });
    }
  });

  if (hoverPill) {
    nav.addEventListener('mouseleave', function() {
      hoverPill.style.opacity = '0';
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSlidingPill);
  } else {
    initSlidingPill();
  }
  window.addEventListener('resize', function() {
    if (activeItem) applyPillInstant(pill, activeItem);
  }, { passive: true });
})();
</script>
