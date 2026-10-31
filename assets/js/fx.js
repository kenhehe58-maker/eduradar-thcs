/**
 * EduRadar FX — GSAP choreography cho Trang chủ (welcome.php) & Quét nhanh (quet_nhanh.php).
 * Chỉ được nạp trên 2 trang này (xem includes/header.php & footer.php).
 * Nguyên tắc: nội dung luôn hiển thị nếu JS lỗi; mọi trạng thái ẩn đều do GSAP đặt;
 * clearProps sau khi chạy để không đè transform/hover của CSS; tôn trọng prefers-reduced-motion.
 */
(function () {
    'use strict';
    if (!window.gsap || !window.ScrollTrigger) return;
    gsap.registerPlugin(ScrollTrigger);

    var EXPO = 'expo.out', QUART = 'power4.out';
    var visible = function (el) { return el && el.offsetParent !== null; };
    var $$ = function (sel, root) { return Array.prototype.filter.call((root || document).querySelectorAll(sel), visible); };

    function init() {
        var mm = gsap.matchMedia();

        mm.add('(prefers-reduced-motion: no-preference)', function () {
            /* 0. Thanh tiến trình cuộn (scrub) */
            var bar = document.createElement('div');
            bar.setAttribute('aria-hidden', 'true');
            bar.style.cssText = 'position:fixed;left:0;top:0;height:3px;width:100%;z-index:9999;pointer-events:none;' +
                'transform-origin:0 50%;background:linear-gradient(90deg,#2563eb,#8b5cf6,#10b981);';
            document.body.appendChild(bar);
            gsap.fromTo(bar, { scaleX: 0 }, {
                scaleX: 1, ease: 'none',
                scrollTrigger: { start: 0, end: 'max', scrub: 0.3 }
            });

            /* 1. Intro timeline (chạy 1 lần khi tải trang) */
            var intro = gsap.timeline({ defaults: { ease: EXPO } });
            var topbar = document.querySelector('.glass-topbar');
            var rail = $$('.u040-nav > li > .u040-it');
            if (topbar) intro.from(topbar, { y: -28, opacity: 0, duration: 0.9, clearProps: 'transform,opacity' }, 0);
            if (rail.length) intro.from(rail, { x: -18, opacity: 0, duration: 0.8, stagger: 0.05, clearProps: 'transform,opacity' }, 0.1);

            var heroLines = $$('.masked-text-title .masked-line-inner');
            var heroBadge = document.querySelector('.welcome-hero .welcome-badge');
            var heroDesc = document.querySelector('.welcome-hero .welcome-desc');
            var heroCta = document.querySelector('.welcome-hero .welcome-cta-group');
            if (heroBadge) intro.from(heroBadge, { clipPath: 'inset(0 100% 0 0)', opacity: 0, duration: 1, clearProps: 'clipPath,opacity' }, 0.2);
            if (heroLines.length) {
                gsap.set(heroLines, { transformOrigin: '0% 100%' });
                intro.from(heroLines, { yPercent: 115, rotate: 4, duration: 1.3, ease: QUART, stagger: 0.12, clearProps: 'transform' }, 0.25);
            }
            if (heroDesc) intro.from(heroDesc, { y: 26, opacity: 0, filter: 'blur(8px)', duration: 1.1, clearProps: 'transform,opacity,filter' }, 0.65);
            if (heroCta) intro.from(heroCta.children, { y: 22, opacity: 0, scale: 0.94, duration: 0.9, stagger: 0.1, clearProps: 'transform,opacity' }, 0.8);

            var guest = document.querySelector('.guest-banner');
            if (guest) intro.from(guest, { y: -20, opacity: 0, duration: 1, clearProps: 'transform,opacity' }, 0.3);

            /* 2. Reveal khi cuộn: batch + stagger (ScrollTrigger.batch) */
            var revealSel = [
                '.kpi-card', '.launchpad-card', '.step-card', '.handbook-card', '.handbook-box', '.quick-search-box',
                '.u032-zone', '.no-scores-alert-banner', '.radar-stats-grid .stat-card', '.search-box-wrap',
                '.m-student-card'
            ];
            var items = $$(revealSel.join(','));
            items.forEach(function (el) { el.setAttribute('data-fx', ''); });
            if (items.length) {
                gsap.set(items, { y: 64, opacity: 0, scale: 0.96 });
                var fold = innerHeight * 0.9;
                var inView = items.filter(function (el) { return el.getBoundingClientRect().top < fold; });
                items = items.filter(function (el) { return inView.indexOf(el) < 0; });
                /* Thẻ đã nằm trong màn hình đầu: vào cùng nhịp intro, nối đuôi hero */
                if (inView.length) intro.to(inView, {
                    y: 0, opacity: 1, scale: 1, duration: 1.15, ease: EXPO,
                    stagger: 0.09, clearProps: 'transform,opacity'
                }, 0.95);
                if (items.length) ScrollTrigger.batch(items, {
                    start: 'top 90%', once: true,
                    onEnter: function (batch) {
                        gsap.to(batch, {
                            y: 0, opacity: 1, scale: 1, duration: 1.15, ease: EXPO,
                            stagger: { each: 0.09, from: 'start' },
                            overwrite: true, clearProps: 'transform,opacity'
                        });
                    }
                });
            }

            /* 3. Đếm số khi lọt viewport */
            $$('.kpi-card div[style*="font-size: 26px"], .stat-card b, .eval-stat-value').forEach(function (el) {
                var raw = (el.textContent || '').trim();
                var m = raw.match(/^(\D*)([\d.,]+)(\D*)$/);
                if (!m) return;
                var num = parseFloat(m[2].replace(/,/g, ''));
                if (!isFinite(num) || num === 0) return;
                var dec = (m[2].split('.')[1] || '').length, grouped = m[2].indexOf(',') > -1;
                var o = { v: 0 };
                ScrollTrigger.create({
                    trigger: el, start: 'top 92%', once: true,
                    onEnter: function () {
                        gsap.to(o, {
                            v: num, duration: 1.8, ease: 'power3.out',
                            onUpdate: function () {
                                var t = o.v.toFixed(dec);
                                if (grouped) t = Number(t).toLocaleString('en-US', { minimumFractionDigits: dec });
                                el.textContent = m[1] + t + m[3];
                            },
                            onComplete: function () { el.textContent = raw; }
                        });
                    }
                });
            });

            /* 4. Parallax nền hero/banner (scrub) */
            $$('.welcome-hero, .guest-banner').forEach(function (el) {
                gsap.fromTo(el, { backgroundPositionY: '0%' }, {
                    backgroundPositionY: '60%', ease: 'none',
                    scrollTrigger: { trigger: el, start: 'top top', end: 'bottom top', scrub: true }
                });
            });
            var orb = document.querySelector('.u032-orb');
            if (orb) gsap.to(orb, { y: -7, duration: 2, ease: 'sine.inOut', yoyo: true, repeat: -1 });

            /* 5. Con trỏ: spotlight trên thẻ + nút từ tính (chỉ chuột thật) */
            if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
                $$('.kpi-card, .launchpad-card, .step-card, .handbook-card').forEach(function (card) {
                    if (getComputedStyle(card).position === 'static') card.style.position = 'relative';
                    card.classList.add('fx-spot');
                    card.addEventListener('pointermove', function (e) {
                        var r = card.getBoundingClientRect();
                        card.style.setProperty('--mx', (e.clientX - r.left) + 'px');
                        card.style.setProperty('--my', (e.clientY - r.top) + 'px');
                    });
                });
                $$('.welcome-cta-group .btn, .btn-guest-action').forEach(function (btn) {
                    var qx = gsap.quickTo(btn, 'x', { duration: 0.6, ease: 'power3' });
                    var qy = gsap.quickTo(btn, 'y', { duration: 0.6, ease: 'power3' });
                    btn.addEventListener('pointermove', function (e) {
                        var r = btn.getBoundingClientRect();
                        qx((e.clientX - (r.left + r.width / 2)) * 0.22);
                        qy((e.clientY - (r.top + r.height / 2)) * 0.32);
                    });
                    btn.addEventListener('pointerleave', function () { qx(0); qy(0); });
                });
            }

            return function () { bar.remove(); };
        });

        if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { ScrollTrigger.refresh(); });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
