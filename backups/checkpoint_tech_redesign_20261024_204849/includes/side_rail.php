<?php
// includes/side_rail.php - Apple Taskbar / Dock Side Rail (Fixed Slim Width, Protruding Spring Hover & Floating Tooltips)
// MIT License (c) 2026 Seokwoooo · https://lucianlabs.dev/uxui-prompt/#040
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
<!-- Lucian UX/UI Prompt #040 · MIT License (c) 2026 Seokwoooo · https://lucianlabs.dev/uxui-prompt/#040 -->
<style>
:root{
  --ux-page:#f4f6fa;
  --ux-surface:#ffffff;
  --ux-surface-2:#f8fafc;
  --ux-surface-3:#f1f5f9;
  --ux-ink:#0f172a;
  --ux-ink-2:#475569;
  --ux-ink-3:#64748b;
  --ux-line:rgba(15,23,42,.08);
  --ux-line-2:#cbd5e1;
  --ux-accent:#4f46e5;
  --ux-accent-2:#2F8FE0;
  --ux-accent-soft:rgba(79,70,229,.12);
  --ux-accent-ink:#ffffff;
  --ux-mint:#119C72;
  --ux-warn:#D6731E;
  --ux-danger:#D93A55;
  --ux-glass:rgba(255,255,255,.8);
  --ux-glass-line:rgba(226,232,240,.8);
  --ux-shadow:rgba(15,23,42,.08);
  --ux-shadow-strong:rgba(15,23,42,.2);
  --ux-grad:linear-gradient(135deg,#6366f1,#3b82f6 55%,#10b981);
  --ux-ease:cubic-bezier(.2,.8,.2,1);
  --ux-spring:cubic-bezier(.34,1.45,.64,1);
}
@media (prefers-reduced-motion:reduce){
  .ux-rail-container,.ux-rail-container *{
    animation-duration:.001ms!important;animation-iteration-count:1!important;
    transition-duration:.001ms!important;scroll-behavior:auto!important;
  }
}

.ux-rail-container{
  position: fixed;
  left: 14px;
  top: 76px;
  bottom: 20px;
  z-index: 1010;
  pointer-events: none;
}
.u040-fixed{
  position: relative;
  display: flex;
  height: calc(100vh - 96px);
  max-height: 740px;
  pointer-events: auto;
}
.u040-rail{
  position: relative;
  flex: none;
  width: 68px;
  padding: 14px 10px 14px;
  border-radius: 22px;
  background: rgba(15, 23, 42, 0.94);
  -webkit-backdrop-filter: blur(24px) saturate(180%);
  backdrop-filter: blur(24px) saturate(180%);
  color: #f8fafc;
  box-shadow: 0 20px 40px -10px rgba(15,23,42,0.45), 0 0 0 1px rgba(255,255,255,0.1);
  display: flex;
  flex-direction: column;
  align-items: center;
}
.u040-top{
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 44px;
  margin-bottom: 18px;
  flex: none;
}
.u040-logo{
  position: relative;
  width: 44px;
  height: 44px;
  flex: none;
  border-radius: 14px;
  background: var(--ux-grad);
  display: grid;
  place-items: center;
  font-size: 20px;
  box-shadow: 0 6px 18px rgba(99, 102, 241, 0.4);
  transition: transform .3s var(--ux-spring);
  text-decoration: none;
  color: #ffffff;
}
.u040-logo:hover{
  transform: scale(1.1) rotate(5deg);
}

.u040-nav{
  position: relative;
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  width: 100%;
  flex: 1;
}

/* APPLE DOCK / TASKBAR HOVER EFFECT: PROTRUDE & FLOATING TOOLTIP */
.u040-it{
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 46px;
  height: 46px;
  border: 0;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.05);
  color: #94a3b8;
  text-decoration: none;
  cursor: pointer;
  transition: transform .3s var(--ux-spring), background-color .2s ease, color .2s ease, box-shadow .25s ease;
}
.u040-it:hover{
  color: #ffffff;
  background: rgba(255, 255, 255, 0.15);
  transform: scale(1.16) translateX(4px);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.35);
  z-index: 1020;
}
.u040-it[aria-current="page"]{
  background: linear-gradient(135deg, rgba(79, 70, 229, 0.9), rgba(59, 130, 246, 0.8));
  color: #ffffff;
  box-shadow: 0 6px 18px rgba(79, 70, 229, 0.45);
}
.u040-it[aria-current="page"]::after{
  content: "";
  position: absolute;
  left: -8px;
  top: 50%;
  transform: translateY(-50%);
  width: 4px;
  height: 18px;
  border-radius: 0 4px 4px 0;
  background: #38bdf8;
  box-shadow: 0 0 8px #38bdf8;
}
.u040-it svg{
  width: 22px;
  height: 22px;
  flex: none;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
  transition: transform .25s ease;
}
.u040-it:hover svg{
  transform: scale(1.05);
}

/* NOTIFICATION BADGE */
.u040-bd{
  position: absolute;
  right: -2px;
  top: -2px;
  min-width: 17px;
  height: 17px;
  padding: 0 4px;
  border-radius: 9px;
  background: #ef4444;
  color: #fff;
  font-size: 10px;
  font-weight: 800;
  display: grid;
  place-items: center;
  font-style: normal;
  box-shadow: 0 0 0 2px #0f172a;
}

/* FLOATING APPLE TOOLTIP PILL */
.u040-dock-tip{
  position: absolute;
  left: calc(100% + 14px);
  top: 50%;
  transform: translateY(-50%) translateX(-8px);
  opacity: 0;
  pointer-events: none;
  background: rgba(15, 23, 42, 0.96);
  -webkit-backdrop-filter: blur(18px);
  backdrop-filter: blur(18px);
  color: #ffffff;
  font-size: 13px;
  font-weight: 700;
  padding: 7px 14px;
  border-radius: 10px;
  white-space: nowrap;
  box-shadow: 0 10px 25px -4px rgba(0, 0, 0, 0.45), 0 0 0 1px rgba(255, 255, 255, 0.12);
  transition: opacity .22s ease, transform .28s var(--ux-spring);
  z-index: 1050;
  line-height: 1.3;
}
.u040-dock-tip::before{
  content: "";
  position: absolute;
  right: 100%;
  top: 50%;
  transform: translateY(-50%);
  border: 6px solid transparent;
  border-right-color: rgba(15, 23, 42, 0.96);
}
.u040-it:hover .u040-dock-tip,
.u040-me:hover .u040-dock-tip{
  opacity: 1;
  transform: translateY(-50%) translateX(0);
}

/* FOOTER SECTION: USER PROFILE & ONLY LOGOUT */
.u040-footer-sec{
  margin-top: auto;
  padding-top: 14px;
  border-top: 1px solid rgba(255, 255, 255, 0.12);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  width: 100%;
  flex: none;
}
.u040-me{
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 46px;
  height: 46px;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.06);
  cursor: pointer;
  transition: transform .3s var(--ux-spring), background-color .2s;
}
.u040-me:hover{
  transform: scale(1.14) translateX(4px);
  background: rgba(255, 255, 255, 0.14);
}
.u040-me i{
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: linear-gradient(135deg, #4f46e5, #06b6d4);
  display: grid;
  place-items: center;
  font-style: normal;
  font-weight: 800;
  font-size: 13px;
  color: #ffffff;
  box-shadow: 0 2px 8px rgba(79, 70, 229, 0.35);
}

/* THE ONLY LOGOUT BUTTON */
.u040-logout-it{
  color: #f87171 !important;
  background: rgba(239, 68, 68, 0.12) !important;
  border: 1px solid rgba(239, 68, 68, 0.25) !important;
}
.u040-logout-it:hover{
  background: #ef4444 !important;
  color: #ffffff !important;
  box-shadow: 0 6px 18px rgba(239, 68, 68, 0.45) !important;
}
.u040-tip-danger{
  border-color: rgba(239, 68, 68, 0.4) !important;
  color: #fecaca !important;
}

@media (max-width: 640px){
  .ux-rail-container{
    left: 10px;
    right: 10px;
    top: auto;
    bottom: 12px;
    height: 56px;
    width: auto;
  }
  .u040-fixed{
    height: 56px;
    width: 100%;
    max-height: none;
    justify-content: center;
  }
  .u040-rail{
    width: 100%;
    max-width: 460px;
    height: 56px;
    flex-direction: row;
    padding: 6px 10px;
    border-radius: 18px;
    justify-content: space-around;
  }
  .u040-top{
    display: none;
  }
  .u040-nav{
    flex-direction: row;
    gap: 6px;
    margin: 0;
  }
  .u040-footer-sec{
    margin-top: 0;
    padding-top: 0;
    border-top: none;
    border-left: 1px solid rgba(255, 255, 255, 0.12);
    padding-left: 8px;
    flex-direction: row;
    gap: 6px;
    width: auto;
  }
  .u040-dock-tip{
    display: none !important;
  }
}
</style>

<aside class="ux-rail-container u208-wrapper" aria-label="Side Navigation Rail">
  <div class="u040-fixed" id="u040RailWrapper">
    <nav class="u040-rail u208-dk" role="toolbar" aria-label="Main menu">
      <div class="u040-top">
        <a href="index.php" class="u040-logo" title="EduRadar THCS" aria-label="Trang chủ EduRadar">
          📡
        </a>
      </div>

      <!-- Navigation List with Apple Dock Hover Protrusion -->
      <ul class="u040-nav" id="u040NavList">
        <li>
          <a href="index.php<?= $navQuery ?>" class="u040-it" style="--i:0" <?= $activeNav === 'dashboard' ? 'aria-current="page"' : '' ?> aria-label="Radar Cảnh Báo Sớm">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.5"/><path d="M12 3a9 9 0 0 1 9 9"/></svg>
            <span class="u040-dock-tip">Radar Cảnh Báo Sớm</span>
          </a>
        </li>
        <li>
          <a href="nhap_diem.php<?= $navQuery ?>" class="u040-it" style="--i:1" <?= $activeNav === 'gradebook' ? 'aria-current="page"' : '' ?> aria-label="Bảng Điểm VnEdu">
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="3"/><path d="M3 10h18M8 4v16M14 4v16"/></svg>
            <span class="u040-dock-tip">Bảng Điểm VnEdu</span>
          </a>
        </li>
        <li>
          <a href="quet_nhanh.php" class="u040-it" style="--i:2" <?= $activeNav === 'guest' ? 'aria-current="page"' : '' ?> aria-label="Quét 1 Chạm">
            <svg viewBox="0 0 24 24" aria-hidden="true"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
            <em class="u040-bd" aria-label="Mới">★</em>
            <span class="u040-dock-tip">Quét 1 Chạm VnEdu</span>
          </a>
        </li>
        <?php if ($userRole === 'admin'): ?>
          <li>
            <a href="admin.php" class="u040-it" style="--i:3" <?= $activeNav === 'admin' ? 'aria-current="page"' : '' ?> aria-label="Cổng Quản Trị Nhà Trường">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l7 4v6c0 5-3.5 9.5-7 10-3.5-.5-7-5-7-10V6l7-4z"/><circle cx="12" cy="11" r="2.5"/></svg>
              <span class="u040-dock-tip">Cổng Quản Trị Admin</span>
            </a>
          </li>
        <?php endif; ?>
      </ul>

      <!-- User Profile & Single Logout Section at Bottom -->
      <div class="u040-footer-sec">
        <!-- User Avatar with floating profile card -->
        <div class="u040-me" tabindex="0" role="button" aria-label="Hồ sơ người dùng">
          <i aria-hidden="true"><?= mb_strtoupper(mb_substr($userName, 0, 1, 'UTF-8'), 'UTF-8') ?></i>
          <span class="u040-dock-tip">
            <strong><?= htmlspecialchars($userName) ?></strong><br>
            <small style="opacity: 0.8; font-weight: 500;"><?= htmlspecialchars($userRoleTitle) ?></small>
          </span>
        </div>

        <?php if (!empty($_SESSION['user_id'])): ?>
          <!-- THE ONLY LOGOUT BUTTON IN THE SYSTEM -->
          <a href="logout.php" class="u040-it u040-logout-it" style="--i:4" title="Đăng Xuất Khỏi Hệ Thống" aria-label="Đăng Xuất">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
            <span class="u040-dock-tip u040-tip-danger">🚪 Đăng Xuất</span>
          </a>
        <?php else: ?>
          <a href="login.php" class="u040-it" style="--i:4; color: #818cf8 !important; background: rgba(99, 102, 241, 0.15);" title="Đăng Nhập" aria-label="Đăng Nhập">
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <span class="u040-dock-tip">🔑 Đăng Nhập</span>
          </a>
        <?php endif; ?>
      </div>
    </nav>
  </div>
</aside>
