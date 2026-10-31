<?php
// includes/footer.php - Stitch UI & EdTech Pro Unified Footer Shell
require_once __DIR__ . '/icons.php';
?>
    <!-- BOTTOM CLEARANCE SPACER -->
    <div style="height: 32px; width: 100%; pointer-events: none;" aria-hidden="true"></div>
</main>

<!-- EDEXA & EDURADAR PRO 3-COLUMN FOOTER ARCHITECTURE -->
<footer class="eduradar-pro-footer safe-bottom-dock" role="contentinfo" style="padding-bottom: calc(5rem + env(safe-area-inset-bottom, 0px));">
    <div class="eduradar-footer-grid">
        <!-- Cột 1: Thông tin hệ thống & Căn cứ Pháp lý -->
        <div class="footer-col-main">
            <div class="footer-brand-title">
                <span class="footer-brand-badge">ER</span>
                <div>
                    <span class="footer-brand-name">EDURADAR THCS</span>
                    <span class="footer-brand-sub">Hệ Thống Giám Sát Học Lực &amp; Cảnh Báo Sớm</span>
                </div>
            </div>
            <p class="footer-legal-desc">
                Hệ thống phân tích biến thiên điểm số thường xuyên và định kỳ theo chuẩn <b>Thông tư 22/2021/TT-BGDĐT</b> của Bộ Giáo dục và Đào tạo, hỗ trợ cá nhân hóa kế hoạch phụ đạo học sinh.
            </p>
            <div class="footer-security-tags">
                <span class="footer-tag-chip"><?= get_svg_icon('lock', 13, '', 'vertical-align: -2px; margin-right: 4px;') ?>MariaDB ACID</span>
                <span class="footer-tag-chip"><?= get_svg_icon('shield-check', 13, '', 'vertical-align: -2px; margin-right: 4px;') ?>OWASP Compliant</span>
                <span class="footer-tag-chip"><?= get_svg_icon('activity', 13, '', 'vertical-align: -2px; margin-right: 4px;') ?>Fast Telemetry</span>
            </div>
        </div>

        <!-- Cột 2: Điều hướng nhanh các phân hệ chuyên môn -->
        <div class="footer-col-nav">
            <div class="footer-col-heading">Phân Hệ Làm Việc</div>
            <ul class="footer-links-list">
                <li><a href="welcome.php">Trang Chủ &amp; Tổng Quan</a></li>
                <li><a href="index.php">Bảng Cảnh Báo Sớm (Early Warning)</a></li>
                <li><a href="nhap_diem.php">Sổ Điểm Điện Tử Bộ Môn</a></li>
                <li><a href="quet_nhanh.php">Quét Nhanh Bảng Điểm 1 Chạm</a></li>
                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                    <li><a href="admin.php">Cổng Quản Trị Phân Công</a></li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Cột 3: Hỗ trợ Kỹ thuật & Đường dây nóng nhà trường -->
        <div class="footer-col-support">
            <div class="footer-col-heading">Hỗ Trợ &amp; Đường Dây Nóng</div>
            <ul class="footer-contact-list">
                <li>
                    <?= get_svg_icon('phone', 15, '', 'vertical-align: -2px; margin-right: 4px;') ?>
                    <span>Hotline: <b>0988.XXX.XXX</b> (Tổ CNTT)</span>
                </li>
                <li>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    <span>Email: <a href="mailto:hotro@eduradar-thcs.edu.vn">hotro@eduradar-thcs.edu.vn</a></span>
                </li>
                <li>
                    <?= get_svg_icon('clock', 15, '', 'vertical-align: -2px; margin-right: 4px;') ?>
                    <span>Thời gian hỗ trợ: 7:00 – 17:30 (T2 – T7)</span>
                </li>
            </ul>
            <div style="margin-top: 14px;">
                <button type="button" class="btn-open-help-drawer" onclick="toggleHelpDrawer(true)">
                    <?= get_svg_icon('bulb', 15, '', 'vertical-align: -2px; margin-right: 4px;') ?>
                    <span>Xem Bảng Phím Tắt &amp; Quy Chuẩn</span>
                </button>
            </div>
        </div>
    </div>

    <div class="footer-bottom-bar">
        <div>
            &copy; <?= date('Y') ?> <b>EduRadar THCS</b> &mdash; Bản quyền thuộc về Đội ngũ Kỹ thuật &amp; Nghiên cứu Giáo dục THCS.
        </div>
        <div class="footer-metrics">
            <span class="tech-chip">PHP 8.0</span>
            <span class="tech-chip">MariaDB 10.4</span>
            <span class="tech-chip">TT22 BGD&amp;ĐT</span>
        </div>
    </div>
</footer>

<!-- FLOATING QUICK SUPPORT BUTTON (BOTTOM RIGHT) -->
<div class="floating-help-widget hidden-mobile-fab" id="floatingHelpWidget">
    <button type="button" class="floating-help-btn" onclick="toggleHelpDrawer()" aria-label="Mở bảng trợ giúp và phím tắt" title="Trợ giúp & Phím tắt nhanh (?)">
        <svg class="help-btn-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="10"></circle>
            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
            <line x1="12" y1="17" x2="12.01" y2="17"></line>
        </svg>
        <span class="help-btn-pulse" aria-hidden="true"></span>
    </button>
</div>

<!-- HELP DRAWER & MODAL OVERLAY -->
<div class="help-drawer-overlay" id="helpDrawerOverlay" onclick="toggleHelpDrawer(false)" aria-hidden="true"></div>
<aside class="help-drawer" id="helpDrawer" role="dialog" aria-modal="true" aria-label="Bảng Hướng Dẫn & Trợ Giúp Nhanh">
    <div class="help-drawer-header">
        <div style="display: flex; align-items: center; gap: 10px;">
            <div class="help-drawer-icon">?</div>
            <div>
                <h3 class="help-drawer-title">Trung Tâm Trợ Giúp EduRadar</h3>
                <span class="help-drawer-sub">Phím tắt nhanh &amp; Quy chuẩn Thông tư 22</span>
            </div>
        </div>
        <button type="button" class="help-drawer-close" onclick="toggleHelpDrawer(false)" aria-label="Đóng bảng trợ giúp">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
    </div>

    <div class="help-drawer-body">
        <!-- 1. BẢNG PHÍM TẮT NHANH -->
        <div class="help-section">
            <div class="help-section-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><line x1="6" y1="8" x2="6" y2="8"/><line x1="10" y1="8" x2="10" y2="8"/><line x1="14" y1="8" x2="14" y2="8"/><line x1="18" y1="8" x2="18" y2="8"/><line x1="6" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="18" y2="12"/><line x1="9" y1="16" x2="15" y2="16"/></svg>
                <span>Phím tắt thao tác nhanh (Sổ điểm &amp; Radar)</span>
            </div>
            <div class="shortcut-list">
                <div class="shortcut-item">
                    <span class="shortcut-desc">Di chuyển giữa các ô điểm</span>
                    <span class="shortcut-keys"><kbd>&uarr;</kbd> <kbd>&darr;</kbd> <kbd>&larr;</kbd> <kbd>&rarr;</kbd></span>
                </div>
                <div class="shortcut-item">
                    <span class="shortcut-desc">Chuyển sang ô điểm tiếp theo</span>
                    <span class="shortcut-keys"><kbd>Enter</kbd> hoặc <kbd>Tab</kbd></span>
                </div>
                <div class="shortcut-item">
                    <span class="shortcut-desc">Lưu toàn bộ sổ điểm tức thì</span>
                    <span class="shortcut-keys"><kbd>Ctrl</kbd> + <kbd>S</kbd></span>
                </div>
                <div class="shortcut-item">
                    <span class="shortcut-desc">Tìm kiếm học sinh nhanh</span>
                    <span class="shortcut-keys"><kbd>/</kbd></span>
                </div>
                <div class="shortcut-item">
                    <span class="shortcut-desc">Đóng bảng trợ giúp này</span>
                    <span class="shortcut-keys"><kbd>Esc</kbd></span>
                </div>
            </div>
        </div>

        <!-- 2. TÓM TẮT XẾP LOẠI HỌC LỰC THÔNG TƯ 22 -->
        <div class="help-section">
            <div class="help-section-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                <span>Quy chuẩn phân tầng Thông tư 22/2021/TT-BGDĐT</span>
            </div>
            <div class="tt22-summary-grid">
                <div class="tt22-card card-tot">
                    <b>Mức Tốt</b>
                    <p>&bull; ĐTBMhk &ge; 8.0<br>&bull; Không môn nào &lt; 6.5</p>
                </div>
                <div class="tt22-card card-kha">
                    <b>Mức Khá</b>
                    <p>&bull; ĐTBMhk &ge; 6.5<br>&bull; Không môn nào &lt; 5.0</p>
                </div>
                <div class="tt22-card card-dat">
                    <b>Mức Đạt</b>
                    <p>&bull; ĐTBMhk &ge; 5.0<br>&bull; Không môn nào &lt; 3.5</p>
                </div>
                <div class="tt22-card card-chuadat">
                    <b>Chưa Đạt (Cần Cảnh Báo)</b>
                    <p>&bull; Các trường hợp còn lại<br>&bull; ĐTBM &lt; 3.5 cần kế hoạch phụ đạo</p>
                </div>
            </div>
        </div>

        <!-- 3. ĐẦU MỐI HỖ TRỢ TRỰC TIẾP -->
        <div class="help-section">
            <div class="help-section-title">
                <?= get_svg_icon('phone', 18, '', 'vertical-align: -2px; margin-right: 4px;') ?>
                <span>Hỗ trợ kỹ thuật &amp; Góp ý</span>
            </div>
            <div class="help-contact-box">
                <p>Thầy/Cô gặp khó khăn khi đồng bộ điểm hoặc cần xuất phiếu học tập gửi phụ huynh, vui lòng liên hệ:</p>
                <div class="help-contact-badge"><?= get_svg_icon('phone', 14, '', 'vertical-align: -2px; margin-right: 4px;') ?> Hotline: <b>0988.XXX.XXX</b> (Thầy Admin Tin Học)</div>
            </div>
        </div>
    </div>
</aside>

<!-- SETTINGS DRAWER & MODAL OVERLAY -->
<div class="help-drawer-overlay" id="settingsDrawerOverlay" onclick="toggleSettingsDrawer(false)" aria-hidden="true"></div>
<aside class="help-drawer" id="settingsDrawer" role="dialog" aria-modal="true" aria-label="Cài Đặt Hệ Thống">
    <div class="help-drawer-header">
        <div style="display: flex; align-items: center; gap: 10px;">
            <div class="help-drawer-icon" style="background: rgba(37, 99, 235, 0.1); color: var(--cobalt-accent, #2563eb);">
                <?= get_svg_icon('settings', 18) ?>
            </div>
            <div>
                <h3 class="help-drawer-title" data-i18n="settings.title">Cài Đặt Hệ Thống</h3>
                <span class="help-drawer-sub" data-i18n="settings.subtitle">Tùy biến hiển thị ngôn ngữ &amp; giao diện</span>
            </div>
        </div>
        <button type="button" class="help-drawer-close" onclick="toggleSettingsDrawer(false)" aria-label="Đóng cài đặt">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
    </div>

    <div class="help-drawer-body">
        <!-- 1. CÀI ĐẶT NGÔN NGỮ -->
        <div class="help-section">
            <div class="help-section-title">
                <?= get_svg_icon('globe', 18, '', 'vertical-align: -2px; margin-right: 4px;') ?>
                <span data-i18n="settings.lang_group">Ngôn ngữ hiển thị (Language)</span>
            </div>
            <div class="settings-options-col" style="display: flex; flex-direction: column; gap: 10px; margin-top: 10px;">
                <button type="button" class="settings-choice-card" id="optLangVi" onclick="setAppLanguage('vi')">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 20px;">🇻🇳</span>
                        <div style="text-align: left;">
                            <div style="font-weight: 700; font-size: 13.5px; color: var(--text-heading, #0f172a);">Tiếng Việt</div>
                            <div style="font-size: 11.5px; color: var(--text-subtle, #64748b);">Ngôn ngữ mặc định theo Thông tư 22</div>
                        </div>
                    </div>
                    <span class="settings-check-badge" id="checkLangVi">&#10003;</span>
                </button>
                <button type="button" class="settings-choice-card" id="optLangEn" onclick="setAppLanguage('en')">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 20px;">🇬🇧</span>
                        <div style="text-align: left;">
                            <div style="font-weight: 700; font-size: 13.5px; color: var(--text-heading, #0f172a);">English</div>
                            <div style="font-size: 11.5px; color: var(--text-subtle, #64748b);">International &bull; Bilingual Interface</div>
                        </div>
                    </div>
                    <span class="settings-check-badge" id="checkLangEn">&#10003;</span>
                </button>
            </div>
        </div>

        <!-- 2. CÀI ĐẶT GIAO DIỆN SÁNG / TỐI -->
        <div class="help-section">
            <div class="help-section-title">
                <?= get_svg_icon('sun', 18, '', 'vertical-align: -2px; margin-right: 4px;') ?>
                <span data-i18n="settings.theme_group">Chế độ giao diện (Appearance)</span>
            </div>
            <div class="settings-options-col" style="display: flex; flex-direction: column; gap: 10px; margin-top: 10px;">
                <button type="button" class="settings-choice-card" id="optThemeLight" onclick="setAppThemeMode('light')">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div class="settings-icon-box" style="background: #eff6ff; color: #2563eb;">
                            <?= get_svg_icon('sun', 18) ?>
                        </div>
                        <div style="text-align: left;">
                            <div style="font-weight: 700; font-size: 13.5px; color: var(--text-heading, #0f172a);" data-i18n="settings.theme_light">Giao diện Sáng</div>
                            <div style="font-size: 11.5px; color: var(--text-subtle, #64748b);" data-i18n="settings.theme_light_desc">Nền trắng sạch, độ tương phản cao ban ngày</div>
                        </div>
                    </div>
                    <span class="settings-check-badge" id="checkThemeLight">&#10003;</span>
                </button>
                <button type="button" class="settings-choice-card" id="optThemeDark" onclick="setAppThemeMode('dark')">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div class="settings-icon-box" style="background: #1e293b; color: #60a5fa;">
                            <?= get_svg_icon('moon', 18) ?>
                        </div>
                        <div style="text-align: left;">
                            <div style="font-weight: 700; font-size: 13.5px; color: var(--text-heading, #0f172a);" data-i18n="settings.theme_dark">Giao diện Tối</div>
                            <div style="font-size: 11.5px; color: var(--text-subtle, #64748b);" data-i18n="settings.theme_dark_desc">Nền Cobalt dịu mắt, làm việc ca đêm</div>
                        </div>
                    </div>
                    <span class="settings-check-badge" id="checkThemeDark">&#10003;</span>
                </button>
                <button type="button" class="settings-choice-card" id="optThemeAuto" onclick="setAppThemeMode('auto')">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div class="settings-icon-box" style="background: #f1f5f9; color: #475569;">
                            <?= get_svg_icon('settings', 18) ?>
                        </div>
                        <div style="text-align: left;">
                            <div style="font-weight: 700; font-size: 13.5px; color: var(--text-heading, #0f172a);" data-i18n="settings.theme_auto">Tự động theo hệ thống</div>
                            <div style="font-size: 11.5px; color: var(--text-subtle, #64748b);" data-i18n="settings.theme_auto_desc">Đồng bộ chế độ Dark/Light của thiết bị</div>
                        </div>
                    </div>
                    <span class="settings-check-badge" id="checkThemeAuto">&#10003;</span>
                </button>
            </div>
        </div>

        <div style="margin-top: 18px;">
            <button type="button" class="cobalt-key cobalt-key-primary" style="width: 100%;" onclick="toggleSettingsDrawer(false)">
                <span data-i18n="settings.btn_done">Hoàn tất &amp; Đóng</span>
            </button>
        </div>
    </div>
</aside>

<script>
function toggleSettingsDrawer(forceState) {
    var drawer = document.getElementById('settingsDrawer');
    var overlay = document.getElementById('settingsDrawerOverlay');
    if (!drawer || !overlay) return;
    
    var isOpen = drawer.classList.contains('is-open');
    var shouldOpen = (typeof forceState === 'boolean') ? forceState : !isOpen;
    
    if (shouldOpen) {
        drawer.classList.add('is-open');
        overlay.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        syncSettingsUIState();
    } else {
        drawer.classList.remove('is-open');
        overlay.classList.remove('is-open');
        document.body.style.overflow = '';
    }
}

function syncSettingsUIState() {
    var currentLang = 'vi';
    try {
        currentLang = localStorage.getItem('edu_radar_lang') || 'vi';
    } catch (e) {}
    
    var cardVi = document.getElementById('optLangVi');
    var cardEn = document.getElementById('optLangEn');
    var chkVi = document.getElementById('checkLangVi');
    var chkEn = document.getElementById('checkLangEn');
    if (cardVi && cardEn) {
        if (currentLang === 'en') {
            cardEn.classList.add('is-active');
            cardVi.classList.remove('is-active');
            if (chkEn) chkEn.style.display = 'inline-flex';
            if (chkVi) chkVi.style.display = 'none';
        } else {
            cardVi.classList.add('is-active');
            cardEn.classList.remove('is-active');
            if (chkVi) chkVi.style.display = 'inline-flex';
            if (chkEn) chkEn.style.display = 'none';
        }
    }

    var themePref = 'light';
    try {
        themePref = localStorage.getItem('eduradar_theme_pref') || localStorage.getItem('eduradar_theme') || 'light';
    } catch (e) {}

    var cardLight = document.getElementById('optThemeLight');
    var cardDark = document.getElementById('optThemeDark');
    var cardAuto = document.getElementById('optThemeAuto');
    var chkLight = document.getElementById('checkThemeLight');
    var chkDark = document.getElementById('checkThemeDark');
    var chkAuto = document.getElementById('checkThemeAuto');

    [cardLight, cardDark, cardAuto].forEach(function(c) { if (c) c.classList.remove('is-active'); });
    [chkLight, chkDark, chkAuto].forEach(function(k) { if (k) k.style.display = 'none'; });

    if (themePref === 'auto') {
        if (cardAuto) cardAuto.classList.add('is-active');
        if (chkAuto) chkAuto.style.display = 'inline-flex';
    } else if (themePref === 'dark') {
        if (cardDark) cardDark.classList.add('is-active');
        if (chkDark) chkDark.style.display = 'inline-flex';
    } else {
        if (cardLight) cardLight.classList.add('is-active');
        if (chkLight) chkLight.style.display = 'inline-flex';
    }
}

function setAppLanguage(lang) {
    try {
        localStorage.setItem('edu_radar_lang', lang);
    } catch (e) {}
    if (typeof applyLanguage === 'function') {
        applyLanguage(lang);
    } else if (window.applyLanguage) {
        window.applyLanguage(lang);
    }
    syncSettingsUIState();
}

function setAppThemeMode(mode) {
    try {
        localStorage.setItem('eduradar_theme_pref', mode);
    } catch (e) {}
    
    var appliedTheme = mode;
    if (mode === 'auto') {
        appliedTheme = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
    }
    document.documentElement.setAttribute('data-theme', appliedTheme);
    try {
        localStorage.setItem('eduradar_theme', appliedTheme);
    } catch (e) {}
    if (typeof updateThemeUI === 'function') {
        updateThemeUI(appliedTheme);
    }
    syncSettingsUIState();
}
</script>

<script>
function toggleHelpDrawer(forceState) {
    var drawer = document.getElementById('helpDrawer');
    var overlay = document.getElementById('helpDrawerOverlay');
    if (!drawer || !overlay) return;
    
    var isOpen = drawer.classList.contains('is-open');
    var shouldOpen = (typeof forceState === 'boolean') ? forceState : !isOpen;
    
    if (shouldOpen) {
        drawer.classList.add('is-open');
        overlay.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    } else {
        drawer.classList.remove('is-open');
        overlay.classList.remove('is-open');
        document.body.style.overflow = '';
    }
}

// Lắng nghe phím tắt mở trợ giúp (?) và phím Esc để đóng
document.addEventListener('keydown', function(e) {
    if (e.key === '?' || (e.shiftKey && e.key === '/')) {
        var activeTag = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
        if (activeTag !== 'input' && activeTag !== 'textarea') {
            e.preventDefault();
            toggleHelpDrawer();
        }
    } else if (e.key === 'Escape') {
        toggleHelpDrawer(false);
    }
});
</script>

<?php 
if (empty($extraScripts) || strpos($extraScripts, 'assets/js/main.js') === false) {
    echo '<script src="assets/js/main.js?v=' . (file_exists(__DIR__ . '/../assets/js/main.js') ? filemtime(__DIR__ . '/../assets/js/main.js') : time()) . '"></script>' . "\n";
}
if (!empty($extraScripts)) echo $extraScripts; 
if (!empty($fxPage)) echo '<script src="assets/js/fx.js?v=' . filemtime(__DIR__ . '/../assets/js/fx.js') . '"></script>' . "\n";
?>
</body>
</html>
