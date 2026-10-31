/**
 * EduRadar FX — GSAP & ScrollTrigger Choreography (Awwwards Edition)
 * Áp dụng độc quyền cho Trang chủ (welcome.php) & Quét nhanh (quet_nhanh.php).
 * Luôn chạy mượt ngay cả khi Windows tắt hiệu ứng hệ thống, hỗ trợ nút Replay (#btnReplayFx).
 */
(function () {
    'use strict';
    if (!window.gsap || !window.ScrollTrigger) return;
    gsap.registerPlugin(ScrollTrigger);

    var activeTimelines = [];
    var activeTriggers = [];

    function visible(el) {
        return el && el.offsetParent !== null;
    }

    function $$(sel, root) {
        return Array.prototype.filter.call((root || document).querySelectorAll(sel), visible);
    }

    /* Đếm số mượt mà (Counter Animation) */
    function runCounters() {
        var countEls = $$('.kpi-card div[style*="font-size: 26px"], .stat-card b, .eval-stat-value');
        countEls.forEach(function (el) {
            var raw = el.getAttribute('data-fx-raw');
            if (!raw) {
                raw = (el.textContent || '').trim();
                el.setAttribute('data-fx-raw', raw);
            }
            var m = raw.match(/^(\D*)([\d.,]+)(\D*)$/);
            if (!m) return;
            var num = parseFloat(m[2].replace(/,/g, ''));
            if (!isFinite(num) || num === 0) return;
            var dec = (m[2].split('.')[1] || '').length;
            var grouped = m[2].indexOf(',') > -1;
            var obj = { val: 0 };

            var st = ScrollTrigger.create({
                trigger: el,
                start: 'top 95%',
                once: true,
                onEnter: function () {
                    gsap.to(obj, {
                        val: num,
                        duration: 1.65,
                        ease: 'power3.out',
                        onUpdate: function () {
                            var formatted = obj.val.toFixed(dec);
                            if (grouped) {
                                formatted = Number(formatted).toLocaleString('en-US', { minimumFractionDigits: dec });
                            }
                            el.textContent = m[1] + formatted + m[3];
                        },
                        onComplete: function () {
                            el.textContent = raw;
                        }
                    });
                }
            });
            activeTriggers.push(st);
        });
    }

    /* Reset sạch sẽ toàn bộ trạng thái để chạy mới hoặc Replay */
    function resetAllAnimations() {
        activeTimelines.forEach(function (tl) { tl.kill(); });
        activeTimelines = [];
        activeTriggers.forEach(function (st) { st.kill(); });
        activeTriggers = [];

        var allTargets = [
            '.glass-topbar',
            '.u040-nav > li > .u040-it',
            '.welcome-hero',
            '.welcome-hero .welcome-badge',
            '.masked-text-title .masked-line-inner',
            '.welcome-hero .welcome-desc',
            '.welcome-hero .welcome-cta-group > *',
            '.guest-banner',
            '.kpi-card',
            '.launchpad-card',
            '.step-card',
            '.handbook-card',
            '.handbook-box',
            '.quick-search-box',
            '#u032DropzoneWrap',
            '.u032-zone',
            '.upload-form-grid',
            '.feature-item',
            '.no-scores-alert-banner',
            '.radar-stats-grid .stat-card',
            '.filter-bar',
            '.search-box-wrap',
            '.m-student-card'
        ].join(', ');

        gsap.killTweensOf(allTargets);
        gsap.set(allTargets, { clearProps: 'transform,opacity,scale,rotationX,filter,clipPath' });
    }

    /* Khởi chạy toàn bộ chuỗi hiệu ứng GSAP + ScrollTrigger */
    function startAllAnimations() {
        resetAllAnimations();

        /* 0. Thanh tiến trình cuộn (Scroll Progress Bar trên đỉnh màn hình) */
        var bar = document.getElementById('eduScrollProgressBar');
        if (!bar) {
            bar = document.createElement('div');
            bar.id = 'eduScrollProgressBar';
            bar.setAttribute('aria-hidden', 'true');
            bar.style.cssText = 'position:fixed;left:0;top:0;height:3px;width:100%;z-index:9999;pointer-events:none;' +
                'transform-origin:0 50%;background:linear-gradient(90deg,#2563eb,#8b5cf6,#10b981);transform:scaleX(0);';
            document.body.appendChild(bar);
        }
        var barTween = gsap.fromTo(bar, { scaleX: 0 }, {
            scaleX: 1,
            ease: 'none',
            scrollTrigger: {
                start: 0,
                end: 'max',
                scrub: 0.25
            }
        });
        if (barTween.scrollTrigger) activeTriggers.push(barTween.scrollTrigger);

        /* 1. HERO & TOPBAR INTRO TIMELINE (Bung mở đàn hồi chuẩn Awwwards) */
        var heroTl = gsap.timeline();

        var topbar = document.querySelector('.glass-topbar');
        if (topbar) {
            heroTl.fromTo(topbar,
                { opacity: 0, y: -24 },
                { opacity: 1, y: 0, duration: 0.7, ease: 'power3.out', clearProps: 'transform,opacity' },
                0
            );
        }

        var railItems = $$('.u040-nav > li > .u040-it');
        if (railItems.length) {
            heroTl.fromTo(railItems,
                { opacity: 0, x: -20 },
                { opacity: 1, x: 0, duration: 0.65, stagger: 0.06, ease: 'back.out(1.5)', clearProps: 'transform,opacity' },
                0.08
            );
        }

        var heroBox = document.querySelector('.welcome-hero, .guest-banner');
        if (heroBox) {
            heroTl.fromTo(heroBox,
                { opacity: 0, y: 32, scale: 0.95, rotationX: 6 },
                { opacity: 1, y: 0, scale: 1, rotationX: 0, duration: 0.85, ease: 'back.out(1.25)', clearProps: 'transform,opacity' },
                0.05
            );
        }

        var heroBadge = document.querySelector('.welcome-hero .welcome-badge');
        if (heroBadge) {
            heroTl.fromTo(heroBadge,
                { opacity: 0, y: 16, scale: 0.92 },
                { opacity: 1, y: 0, scale: 1, duration: 0.6, ease: 'back.out(1.6)', clearProps: 'transform,opacity' },
                0.2
            );
        }

        var heroLines = $$('.masked-text-title .masked-line-inner');
        if (heroLines.length) {
            gsap.set(heroLines, { transformOrigin: '0% 100%' });
            heroTl.fromTo(heroLines,
                { yPercent: 115, rotate: 3 },
                { yPercent: 0, rotate: 0, duration: 0.95, stagger: 0.12, ease: 'power4.out', clearProps: 'transform' },
                0.25
            );
        }

        var heroDesc = document.querySelector('.welcome-hero .welcome-desc, .guest-banner .guest-banner-sub');
        if (heroDesc) {
            heroTl.fromTo(heroDesc,
                { opacity: 0, y: 20 },
                { opacity: 1, y: 0, duration: 0.7, ease: 'power3.out', clearProps: 'transform,opacity' },
                0.42
            );
        }

        var heroCtas = $$('.welcome-hero .welcome-cta-group > *, .guest-banner .guest-banner-actions > *');
        if (heroCtas.length) {
            heroTl.fromTo(heroCtas,
                { opacity: 0, y: 20, scale: 0.93 },
                { opacity: 1, y: 0, scale: 1, duration: 0.65, stagger: 0.1, ease: 'back.out(1.5)', clearProps: 'transform,opacity' },
                0.52
            );
        }

        activeTimelines.push(heroTl);

        /* 2. BENTO KPI / STAT CARDS (Lộ dần theo cụm với đàn hồi back.out) */
        var kpiCards = $$('.welcome-kpi-grid .kpi-card, .radar-stats-grid .stat-card');
        if (kpiCards.length) {
            gsap.set(kpiCards, { opacity: 0, y: 40, scale: 0.92, rotationX: 10 });
            var kpiSt = ScrollTrigger.create({
                trigger: kpiCards[0].parentElement || kpiCards[0],
                start: 'top 92%',
                once: true,
                onEnter: function () {
                    var tl = gsap.to(kpiCards, {
                        opacity: 1,
                        y: 0,
                        scale: 1,
                        rotationX: 0,
                        duration: 0.78,
                        stagger: 0.1,
                        ease: 'back.out(1.35)',
                        overwrite: 'auto',
                        clearProps: 'transform,opacity'
                    });
                    activeTimelines.push(tl);
                }
            });
            activeTriggers.push(kpiSt);
        }

        /* 3. TIÊU ĐỀ PHÂN HỆ & CÁC HỘP LỘ DẦN KHI CUỘN (Individual Card Reveal) */
        var individualBoxes = $$([
            '.launchpad-card',
            '.stepper-container',
            '.step-card',
            '.quick-search-box',
            '.handbook-box',
            '.handbook-card',
            '#u032DropzoneWrap',
            '.upload-form-grid',
            '.feature-item',
            '.no-scores-alert-banner',
            '.filter-bar',
            '.m-student-card'
        ].join(', '));

        individualBoxes.forEach(function (card, idx) {
            gsap.set(card, { opacity: 0, y: 42, scale: 0.95 });
            var st = ScrollTrigger.create({
                trigger: card,
                start: 'top 91%',
                once: true,
                onEnter: function () {
                    var tl = gsap.to(card, {
                        opacity: 1,
                        y: 0,
                        scale: 1,
                        duration: 0.78,
                        delay: (idx % 3) * 0.06,
                        ease: 'back.out(1.25)',
                        overwrite: 'auto',
                        clearProps: 'transform,opacity'
                    });
                    activeTimelines.push(tl);
                }
            });
            activeTriggers.push(st);
        });

        /* 4. ĐẾM SỐ TRỰC TIẾP (Number Counter) */
        runCounters();

        /* 5. NHỊP THỞ CHO BIỂU TƯỢNG DROPZONE (.u032-orb) */
        var orb = document.querySelector('.u032-orb');
        if (orb) {
            var orbTween = gsap.to(orb, {
                y: -8,
                duration: 1.9,
                ease: 'sine.inOut',
                yoyo: true,
                repeat: -1
            });
            activeTimelines.push(orbTween);
        }

        ScrollTrigger.refresh();
    }

    /* Gắn tương tác Spotlight chuột & Magnetic Buttons + Nút Replay */
    function bindInteractions() {
        var btnReplay = document.getElementById('btnReplayFx');
        if (btnReplay && !btnReplay.dataset.fxBound) {
            btnReplay.dataset.fxBound = '1';
            btnReplay.addEventListener('click', function () {
                var icon = document.getElementById('replayFxIcon');
                if (icon) {
                    gsap.fromTo(icon, { rotation: 0 }, { rotation: -360, duration: 0.65, ease: 'back.out(1.6)' });
                }
                window.scrollTo({ top: 0, behavior: 'smooth' });
                setTimeout(function () {
                    startAllAnimations();
                }, 320);
            });
        }

        if (window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
            $$('.kpi-card, .launchpad-card, .step-card, .handbook-card, .feature-item, .stat-card').forEach(function (card) {
                if (card.dataset.spotBound) return;
                card.dataset.spotBound = '1';
                if (getComputedStyle(card).position === 'static') card.style.position = 'relative';
                card.classList.add('fx-spot');
                card.addEventListener('pointermove', function (e) {
                    var r = card.getBoundingClientRect();
                    card.style.setProperty('--mx', (e.clientX - r.left) + 'px');
                    card.style.setProperty('--my', (e.clientY - r.top) + 'px');
                });
            });

            $$('.welcome-cta-group .btn, .btn-guest-action').forEach(function (btn) {
                if (btn.dataset.magBound) return;
                btn.dataset.magBound = '1';
                var qx = gsap.quickTo(btn, 'x', { duration: 0.45, ease: 'power3' });
                var qy = gsap.quickTo(btn, 'y', { duration: 0.45, ease: 'power3' });
                btn.addEventListener('pointermove', function (e) {
                    var r = btn.getBoundingClientRect();
                    qx((e.clientX - (r.left + r.width / 2)) * 0.2);
                    qy((e.clientY - (r.top + r.height / 2)) * 0.28);
                });
                btn.addEventListener('pointerleave', function () {
                    qx(0);
                    qy(0);
                });
            });
        }
    }

    window.replayEduRadarFx = startAllAnimations;

    function boot() {
        bindInteractions();
        startAllAnimations();
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(function () { ScrollTrigger.refresh(); });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
