// =============================================================================
// EduRadar THCS: Master Client-side Interactions
// Features:
// 1. Collapsible Left-side Navigation Sidebar with localStorage Persistence
// 2. Real-time Multi-Dimensional Filter Chips & Instant Student Search
// 3. Expandable Student Competency Profile Modal with Score Timeline
// 4. AJAX Teacher Pedagogical Note Saver with Real-time Audit Feedback
// =============================================================================

// Universal global object reference for browser & test VM contexts
var window = (typeof window !== 'undefined') ? window : (typeof globalThis !== 'undefined' ? globalThis : this);

// Defensive DOM query helpers compatible with both real DOM and mock test harnesses
if (typeof document !== 'undefined') {
    if (typeof document.querySelector !== 'function') {
        document.querySelector = function(sel) {
            if (typeof document.querySelectorAll === 'function') {
                var res = document.querySelectorAll(sel);
                if (res && res.length > 0) return res[0];
            }
            if (Array.isArray(document.elements)) {
                if (sel === '.chip[data-filter="all"]') {
                    return document.elements.find(function(e) {
                        return e.classList && e.classList.contains('chip') && (e.dataset.filter === 'all' || (e.attributes && e.attributes['data-filter'] === 'all'));
                    }) || null;
                }
                if (sel === '.content') {
                    return document.elements.find(function(e) { return e.classList && e.classList.contains('content'); }) || null;
                }
                if (sel === '.sidebar') {
                    return document.elements.find(function(e) { return e.classList && e.classList.contains('sidebar'); }) || null;
                }
            }
            return null;
        };
    }
}

document.addEventListener('DOMContentLoaded', function () {
    // -------------------------------------------------------------------------
    // 1. COLLAPSIBLE LEFT-SIDE NAVIGATION SIDEBAR
    // -------------------------------------------------------------------------
    const sidebar = document.getElementById('appSidebar') || (typeof document.querySelector === 'function' ? document.querySelector('.sidebar') : null);
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mainContent = (typeof document.querySelector === 'function') ? document.querySelector('.content') : null;

    // Đồng bộ trạng thái ban đầu từ localStorage
    try {
        const isCollapsed = localStorage.getItem('edu_sidebar_collapsed') === '1';
        if (isCollapsed) {
            document.documentElement?.classList?.add('sidebar-is-collapsed');
            sidebar?.classList?.add('collapsed');
            mainContent?.classList?.add('sidebar-collapsed');
        }
    } catch (e) {}

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const willCollapse = !document.documentElement?.classList?.contains('sidebar-is-collapsed');
            if (willCollapse) {
                document.documentElement?.classList?.add('sidebar-is-collapsed');
                sidebar?.classList?.add('collapsed');
                mainContent?.classList?.add('sidebar-collapsed');
                try { localStorage.setItem('edu_sidebar_collapsed', '1'); } catch (err) {}
            } else {
                document.documentElement?.classList?.remove('sidebar-is-collapsed');
                sidebar?.classList?.remove('collapsed');
                mainContent?.classList?.remove('sidebar-collapsed');
                try { localStorage.setItem('edu_sidebar_collapsed', '0'); } catch (err) {}
            }
        });
    }

    // -------------------------------------------------------------------------
    // 2. RADAR DASHBOARD: REAL-TIME CLIENT MULTI-SELECT FILTER & INSTANT SEARCH
    // -------------------------------------------------------------------------
    // 2. RADAR REAL-TIME SEARCH & ADVANCED FACETED FILTER ENGINE
    // -------------------------------------------------------------------------
    const rows = (typeof document.querySelectorAll === 'function')
        ? (document.querySelectorAll('tbody tr[data-level]').length > 0 
            ? document.querySelectorAll('tbody tr[data-level]') 
            : document.querySelectorAll('tbody tr.radar-row'))
        : [];
    const chips = (typeof document.querySelectorAll === 'function') ? document.querySelectorAll('.chip[data-filter]') : [];
    const search = document.getElementById('search');
    const emptyRow = document.getElementById('radarEmptyRow') || (typeof document.querySelector === 'function' ? document.querySelector('.radar-filter-empty-row') : null);
    const mobileEmptyRow = document.getElementById('mobileFilterEmpty');
    let currentFilters = ['all'];

    function matchRowSingleFilter(r, filterVal) {
        if (!filterVal || filterVal === 'all') return true;
        const level = r.dataset.level || '';
        const trend = r.dataset.trend || '';
        const momentum = r.dataset.momentum || '';
        const tier = r.dataset.tier || '';
        const txWarn = r.dataset.txwarn || '0';

        if (filterVal === 'red' || filterVal === 'yellow' || filterVal === 'green' || filterVal === 'pending') {
            return (level === filterVal);
        } else if (filterVal === 'trend:up') {
            return (trend === 'up' || momentum === 'up' || momentum === 'surge');
        } else if (filterVal === 'trend:steady') {
            return (trend === 'stable' || momentum === 'steady');
        } else if (filterVal === 'trend:down') {
            return (trend === 'down' || momentum === 'down' || momentum === 'drop');
        } else if (filterVal === 'trend:pending') {
            return (momentum === 'pending' || trend === 'pending');
        } else if (filterVal === 'tier:excellent') {
            return (tier === 'excellent');
        } else if (filterVal === 'tier:good') {
            return (tier === 'good');
        } else if (filterVal === 'tier:high') {
            return (tier === 'excellent' || tier === 'good');
        } else if (filterVal === 'tier:average') {
            return (tier === 'average');
        } else if (filterVal === 'tier:low') {
            return (tier === 'low' || tier === 'critical');
        } else if (filterVal === 'tier:pending') {
            return (tier === 'pending');
        } else if (filterVal.startsWith('overall:')) {
            const targetOverall = filterVal.replace('overall:', '');
            return ((r.dataset.overall || '') === targetOverall);
        } else if (filterVal === 'tx:warn') {
            return (txWarn === '1');
        }
        return false;
    }

    function applyFilter() {
        const q = (search?.value || '').toLowerCase().trim();
        let visibleCount = 0;

        // Phân loại nhóm bộ lọc đa chiều (Faceted Filtering: AND giữa các nhóm, OR trong cùng nhóm)
        const trendFilters = currentFilters.filter(function(f) { return f.startsWith('trend:'); });
        const tierFilters = currentFilters.filter(function(f) { return f.startsWith('tier:'); });
        const riskFilters = currentFilters.filter(function(f) { return ['red', 'yellow', 'green', 'pending', 'tx:warn'].includes(f); });
        const overallFilters = currentFilters.filter(function(f) { return f.startsWith('overall:'); });

        function matchElementMulti(el) {
            if (currentFilters.length === 0 || currentFilters.includes('all')) {
                return true;
            }
            const matchTrend = trendFilters.length === 0 || trendFilters.some(function(f) { return matchRowSingleFilter(el, f); });
            const matchTier = tierFilters.length === 0 || tierFilters.some(function(f) { return matchRowSingleFilter(el, f); });
            const matchRisk = riskFilters.length === 0 || riskFilters.some(function(f) { return matchRowSingleFilter(el, f); });
            const matchOverall = overallFilters.length === 0 || overallFilters.some(function(f) { return matchRowSingleFilter(el, f); });

            return matchTrend && matchTier && matchRisk && matchOverall;
        }

        rows.forEach(function (r) {
            const name = (r.dataset.name || '').toLowerCase();
            const searchAttr = (r.dataset.search || '').toLowerCase();

            const okFilter = matchElementMulti(r);
            const okSearch = !q || name.includes(q) || searchAttr.includes(q);

            if (okFilter && okSearch) {
                r.style.display = '';
                visibleCount++;
            } else {
                r.style.display = 'none';
            }

            // Đồng thời ẩn sub-rows nếu dòng học sinh cha bị ẩn
            const sid = r.dataset.studentId;
            if (sid && (!okFilter || !okSearch)) {
                const subRows = document.querySelectorAll('tr.student-sub-row[data-parent-student="' + sid + '"]');
                subRows.forEach(function(sr) { sr.style.display = 'none'; });
            }
        });

        // Filter mobile student rows
        let mobileVisibleCount = 0;
        const mobileRows = document.querySelectorAll('.m-student-row');
        mobileRows.forEach(function (mRow) {
            const searchAttr = (mRow.dataset.search || '').toLowerCase();
            const okFilter = matchElementMulti(mRow);
            const okSearch = !q || searchAttr.includes(q);
            if (okFilter && okSearch) {
                mRow.style.display = 'flex';
                mobileVisibleCount++;
            } else {
                mRow.style.display = 'none';
            }
        });

        if (emptyRow) {
            emptyRow.style.display = (visibleCount === 0 && rows.length > 0) ? '' : 'none';
        }
        if (mobileEmptyRow) {
            mobileEmptyRow.style.display = (mobileVisibleCount === 0 && mobileRows.length > 0) ? 'block' : 'none';
        }

        if (window.gsap) {
            if (visibleCount > 0) {
                const newlyVisible = Array.from(rows).filter(r => r.style.display !== 'none').slice(0, 35);
                if (newlyVisible.length > 0) {
                    gsap.fromTo(newlyVisible, 
                        { opacity: 0, y: 6 }, 
                        { opacity: 1, y: 0, duration: 0.25, stagger: 0.012, ease: 'power2.out', clearProps: 'transform,opacity' }
                    );
                }
            }
            if (mobileVisibleCount > 0) {
                const newlyVisibleMobile = Array.from(mobileRows).filter(r => r.style.display !== 'none').slice(0, 25);
                if (newlyVisibleMobile.length > 0) {
                    gsap.fromTo(newlyVisibleMobile, 
                        { opacity: 0, y: 6 }, 
                        { opacity: 1, y: 0, duration: 0.25, stagger: 0.015, ease: 'power2.out', clearProps: 'transform,opacity' }
                    );
                }
            }
        }
    }

    // Global reset all filters helper
    window.resetAllFilters = function() {
        chips.forEach(function (x) { x.classList.remove('active'); });
        const allChip = document.querySelector('.chip[data-filter="all"]');
        if (allChip) allChip.classList.add('active');
        currentFilters = ['all'];
        if (search) search.value = '';
        applyFilter();
    };

    // Global helper for opening student detail modal from mobile list row
    window.openStudentModalFromId = function(studentId) {
        const row = document.querySelector('tr.radar-row[data-student-id="' + studentId + '"]');
        if (row) {
            const btn = row.querySelector('.btn-open-detail');
            if (btn) btn.click();
        }
    };

    document.addEventListener('keydown', function(e) {
        if ((e.key === 'Enter' || e.key === ' ') && e.target && e.target.classList.contains('m-student-row')) {
            e.preventDefault();
            e.target.click();
        }
    });

    // Global helper for toggling between cards and table on mobile
    window.switchMobileView = function(view) {
        const tableWrap = document.querySelector('.radar-table-wrap');
        const cardsWrap = document.getElementById('mobileStudentCards');
        const btnCards = document.getElementById('btnViewCards');
        const btnTable = document.getElementById('btnViewTable');
        if (view === 'table') {
            tableWrap?.classList.remove('view-table-hidden');
            if (cardsWrap) cardsWrap.style.display = 'none';
            btnTable?.classList.add('active');
            btnCards?.classList.remove('active');
        } else {
            tableWrap?.classList.add('view-table-hidden');
            if (cardsWrap) cardsWrap.style.display = 'flex';
            btnCards?.classList.add('active');
            btnTable?.classList.remove('active');
        }
    };

    chips.forEach(function (c) {
        c.addEventListener('click', function (e) {
            const f = this.dataset.filter || (this.attributes ? this.attributes['data-filter'] : '') || 'all';
            const allChip = (typeof document.querySelector === 'function')
                ? document.querySelector('.chip[data-filter="all"]')
                : Array.from(chips).find(function(x) { return (x.dataset && x.dataset.filter === 'all') || (x.attributes && x.attributes['data-filter'] === 'all'); });

            if (f === 'all') {
                chips.forEach(function (x) { if (x.classList) x.classList.remove('active'); });
                if (this.classList) this.classList.add('active');
                currentFilters = ['all'];
            } else {
                if (allChip && allChip.classList) allChip.classList.remove('active');
                if (this.classList) {
                    if (typeof this.classList.toggle === 'function') {
                        this.classList.toggle('active');
                    } else if (this.classList.contains('active')) {
                        this.classList.remove('active');
                    } else {
                        this.classList.add('active');
                    }
                }

                const activeChips = Array.from(chips)
                    .filter(function(x) { return x && x.classList && x.classList.contains('active'); })
                    .map(function(x) { return x.dataset ? x.dataset.filter : (x.attributes ? x.attributes['data-filter'] : ''); })
                    .filter(function(x) { return x && x !== 'all'; });

                if (activeChips.length === 0) {
                    if (allChip && allChip.classList) allChip.classList.add('active');
                    currentFilters = ['all'];
                } else {
                    currentFilters = activeChips;
                }
            }
            if (window.gsap) {
                gsap.fromTo(this, { scale: 0.92 }, { scale: 1, duration: 0.25, ease: 'back.out(2)', clearProps: 'transform' });
            }
            applyFilter();
        });
    });

    if (search) {
        search.addEventListener('input', applyFilter);
    }

    // Expose global helper to programmatically trigger filters (e.g. from metric cards)
    window.filterByTier = function (filterVal) {
        const chip = document.querySelector('.chip[data-filter="' + filterVal + '"]');
        if (chip) {
            chips.forEach(function (x) { x.classList.remove('active'); });
            chip.classList.add('active');
            currentFilters = [filterVal];
            applyFilter();
        } else {
            chips.forEach(function (x) { x.classList.remove('active'); });
            currentFilters = [filterVal];
            applyFilter();
        }
        const targetTable = document.querySelector('.radar-table') || document.querySelector('.table-container');
        if (targetTable) {
            targetTable.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };

    // -------------------------------------------------------------------------
    // 3. EXPANDABLE STUDENT COMPETENCY PROFILE MODAL
    // -------------------------------------------------------------------------
    const modal = document.getElementById('competencyDetailModal');
    const modalCloseBtn = document.getElementById('modalCloseBtn');
    const modalStudentAvatar = document.getElementById('modalStudentAvatar');
    const modalStudentName = document.getElementById('modalStudentName');
    const modalStudentCode = document.getElementById('modalStudentCode');
    const modalStudentClass = document.getElementById('modalStudentClass');
    const modalStudentSubject = document.getElementById('modalStudentSubject');
    const modalStudentBirthday = document.getElementById('modalStudentBirthday');
    const modalStudentPhone = document.getElementById('modalStudentPhone');

    const modalMomentumBadge = document.getElementById('modalMomentumBadge');
    const modalScoreTierBadge = document.getElementById('modalScoreTierBadge');
    const modalRiskBadge = document.getElementById('modalRiskBadge');

    const modalScoreTimeline = document.getElementById('modalScoreTimeline');
    const modalDiagnosticText = document.getElementById('modalDiagnosticText');
    const modalCompetencyTitle = document.getElementById('modalCompetencyTitle');
    const modalPedagogicalAdvice = document.getElementById('modalPedagogicalAdvice');

    const modalTeacherNoteInput = document.getElementById('modalTeacherNoteInput');
    const btnSaveTeacherNote = document.getElementById('btnSaveTeacherNote');
    const teacherNoteStatus = document.getElementById('teacherNoteStatus');
    const teacherNoteSavedTime = document.getElementById('teacherNoteSavedTime');

    let currentStudentId = 0;
    let currentSubject = '';
    let activeRowElement = null;

    function getInitials(fullName) {
        if (!fullName) return 'HS';
        const parts = fullName.trim().split(/\s+/);
        if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }

    function formatScoreValue(val) {
        if (val === null || val === undefined || val === '') return '-';
        const num = parseFloat(val);
        return isNaN(num) ? '-' : num.toFixed(1);
    }

    function getScoreClass(val) {
        if (val === null || val === undefined || val === '') return '';
        const num = parseFloat(val);
        if (isNaN(num)) return '';
        if (num < 3.5) return 'crit';
        if (num < 5.0) return 'warn';
        if (num >= 8.0) return 'excel';
        return 'good';
    }

    function openStudentModal(row) {
        if (!modal) return;
        activeRowElement = row;

        currentStudentId = parseInt(row.dataset.studentId || '0', 10);
        currentSubject = row.dataset.subject || '';
        const fullName = row.dataset.fullName || '';
        const studentCode = row.dataset.studentCode || '';
        const className = row.dataset.className || '';
        const birthday = row.dataset.birthday || '';
        const phone = row.dataset.phone || '';

        // Profile info
        if (modalStudentAvatar) modalStudentAvatar.textContent = getInitials(fullName);
        if (modalStudentName) modalStudentName.textContent = fullName;
        if (modalStudentCode) modalStudentCode.textContent = studentCode;
        if (modalStudentClass) modalStudentClass.textContent = 'Lớp ' + className;
        if (modalStudentSubject) {
            const subrows = document.querySelectorAll('.student-sub-row[data-parent-student="' + currentStudentId + '"]');
            if (subrows.length > 1) {
                modalStudentSubject.innerHTML = '';
                subrows.forEach(function(sub) {
                    const sName = sub.dataset.subject;
                    const pill = document.createElement('span');
                    pill.className = 'badge-subject' + (sName === currentSubject ? ' active' : '');
                    pill.style.cursor = 'pointer';
                    pill.style.marginRight = '4px';
                    pill.style.padding = '2px 8px';
                    pill.style.borderRadius = '6px';
                    if (sName === currentSubject) {
                        pill.style.background = '#4f46e5';
                        pill.style.color = '#ffffff';
                    } else {
                        pill.style.background = '#e2e8f0';
                        pill.style.color = '#334155';
                    }
                    pill.textContent = 'Môn ' + sName;
                    pill.onclick = function(e) {
                        e.stopPropagation();
                        openStudentModal(sub);
                    };
                    modalStudentSubject.appendChild(pill);
                });
            } else {
                modalStudentSubject.textContent = 'Môn ' + currentSubject;
            }
        }
        if (modalStudentBirthday) {
            modalStudentBirthday.textContent = birthday ? '· Ngày sinh: ' + birthday : '';
            modalStudentBirthday.style.display = birthday ? 'inline' : 'none';
        }
        if (modalStudentPhone) {
            modalStudentPhone.textContent = phone ? '· SĐT: ' + phone : '';
            modalStudentPhone.style.display = phone ? 'inline' : 'none';
        }

        // Dual Framework Badges (Chuẩn 4 màu: ĐỎ - CAM - VÀNG - XANH)
        const mom = row.dataset.momentum || 'steady';
        let momBadgeHtml = '';
        if (mom === 'drop') {
            momBadgeHtml = '<span style="color: #dc2626; font-size: 14px; font-weight: 700;">Giảm sút</span>';
        } else if (mom === 'down') {
            momBadgeHtml = '<span style="color: #ea580c; font-size: 14px; font-weight: 700;">Chiều hướng giảm</span>';
        } else if (mom === 'steady') {
            momBadgeHtml = '<span style="color: #b45309; font-size: 14px; font-weight: 700;">Duy trì ổn định</span>';
        } else if (mom === 'surge') {
            momBadgeHtml = '<span style="color: #16a34a; font-size: 14px; font-weight: 700;">Tiến bộ rõ</span>';
        } else if (mom === 'up') {
            momBadgeHtml = '<span style="color: #16a34a; font-size: 14px; font-weight: 700;">Đang tiến bộ</span>';
        } else {
            momBadgeHtml = '<span style="color: #64748b; font-size: 14px; font-weight: 700;">Chờ điểm</span>';
        }
        if (modalMomentumBadge) {
            modalMomentumBadge.innerHTML = momBadgeHtml;
        }

        const hasGk = row.dataset.gk !== undefined && row.dataset.gk !== null && row.dataset.gk !== '';
        const hasCk = row.dataset.ck !== undefined && row.dataset.ck !== null && row.dataset.ck !== '';
        const hasTx = [row.dataset.tx1, row.dataset.tx2, row.dataset.tx3, row.dataset.tx4].some(v => v !== undefined && v !== null && v !== '');
        // Tính ĐTBm theo 4 kỳ (GK1, CK1, GK2, CK2). Đầu năm chưa có điểm thi (không GK, không CK) thì không hiện ĐTB
        const hasExamScore = (hasGk || hasCk) && hasTx;

        const tier = row.dataset.tier || '';
        const avgVal = hasExamScore ? parseFloat(row.dataset.avg || '-1') : -1;
        let tierBadgeHtml = '';
        if (!hasExamScore || tier === 'pending' || isNaN(avgVal) || avgVal < 0) {
            tierBadgeHtml = '<span style="color: #64748b; font-size: 14px; font-weight: 700;">Chờ điểm</span>';
        } else if (tier === 'excellent' || avgVal >= 8.0) {
            tierBadgeHtml = '<span style="color: #16a34a; font-size: 14px; font-weight: 700;">Mức Tốt</span>';
        } else if (tier === 'good' || (avgVal >= 6.5 && avgVal < 8.0)) {
            tierBadgeHtml = '<span style="color: #b45309; font-size: 14px; font-weight: 700;">Mức Khá</span>';
        } else if (tier === 'average' || (avgVal >= 5.0 && avgVal < 6.5)) {
            tierBadgeHtml = '<span style="color: #ea580c; font-size: 14px; font-weight: 700;">Mức Đạt</span>';
        } else if (tier === 'low' || (avgVal >= 0 && avgVal < 5.0)) {
            tierBadgeHtml = '<span style="color: #dc2626; font-size: 14px; font-weight: 700;">Chưa đạt</span>';
        } else {
            tierBadgeHtml = '<span style="color: #64748b; font-size: 14px; font-weight: 700;">Chờ điểm</span>';
        }
        if (modalScoreTierBadge) {
            modalScoreTierBadge.innerHTML = tierBadgeHtml;
        }

        const riskLevel = row.dataset.level || 'green';
        if (modalRiskBadge) {
            if (riskLevel === 'red') {
                modalRiskBadge.innerHTML = '<span style="color: #dc2626; font-size: 14px; font-weight: 700;">Cần hỗ trợ sớm</span>';
            } else if (riskLevel === 'yellow') {
                modalRiskBadge.innerHTML = '<span style="color: #ea580c; font-size: 14px; font-weight: 700;">Cần chú ý</span>';
            } else if (riskLevel === 'pending') {
                modalRiskBadge.innerHTML = '<span style="color: #64748b; font-size: 14px; font-weight: 700;">Chờ điểm số</span>';
            } else {
                modalRiskBadge.innerHTML = '<span style="color: #16a34a; font-size: 14px; font-weight: 700;">Ổn định</span>';
            }
        }

        // Score Progression Timeline chips (Chuẩn 4 màu: ĐỎ <5.0, CAM 5.0-6.4, VÀNG 6.5-7.9, XANH >=8.0)
        if (modalScoreTimeline) {
            modalScoreTimeline.innerHTML = '';
            const scoreNodes = [
                { name: 'TX 1', val: row.dataset.tx1, isGk: false, isCk: false, isAvg: false },
                { name: 'TX 2', val: row.dataset.tx2, isGk: false, isCk: false, isAvg: false },
                { name: 'TX 3', val: row.dataset.tx3, isGk: false, isCk: false, isAvg: false },
                { name: 'TX 4', val: row.dataset.tx4, isGk: false, isCk: false, isAvg: false },
                { name: 'GK (x2)', val: row.dataset.gk, isGk: true, isCk: false, isAvg: false },
                { name: 'CK (x3)', val: row.dataset.ck, isGk: false, isCk: true, isAvg: false },
                { name: 'ĐTB', val: hasExamScore ? row.dataset.avg : null, isGk: false, isCk: false, isAvg: true },
            ];

            scoreNodes.forEach(function (node, idx) {
                const nodeDiv = document.createElement('div');
                nodeDiv.className = 'score-timeline-node' + (node.isGk ? ' node-gk' : '') + (node.isCk ? ' node-ck' : '') + (node.isAvg ? ' node-avg' : '');
                
                const valStr = formatScoreValue(node.val);
                const valNum = parseFloat(node.val);

                let textColor = '#64748b';
                let bgColor = '#f8fafc';
                let borderColor = '#e2e8f0';

                if (node.val !== null && node.val !== undefined && node.val !== '' && !isNaN(valNum)) {
                    if (valNum < 5.0) {
                        textColor = '#dc2626'; // ĐỎ: Yếu / Chưa đạt
                        bgColor = '#fef2f2';
                        borderColor = '#fca5a5';
                    } else if (valNum < 6.5) {
                        textColor = '#ea580c'; // CAM: Trung bình
                        bgColor = '#fff7ed';
                        borderColor = '#fed7aa';
                    } else if (valNum < 8.0) {
                        textColor = '#b45309'; // VÀNG: Khá
                        bgColor = '#fefce8';
                        borderColor = '#fde047';
                    } else {
                        textColor = '#16a34a'; // XANH: Giỏi / Tốt
                        bgColor = '#f0fdf4';
                        borderColor = '#86efac';
                    }
                }

                nodeDiv.style.background = bgColor;
                nodeDiv.style.borderColor = borderColor;
                nodeDiv.style.borderWidth = '1.5px';
                nodeDiv.style.borderStyle = 'solid';

                nodeDiv.innerHTML = `
                    <div class="node-label">${node.name}</div>
                    <div class="node-score" style="color: ${textColor} !important; font-weight: 800;">${valStr}</div>
                `;
                modalScoreTimeline.appendChild(nodeDiv);

                if (idx < scoreNodes.length - 1) {
                    const arrowSpan = document.createElement('span');
                    arrowSpan.className = 'timeline-separator';
                    arrowSpan.textContent = '→';
                    modalScoreTimeline.appendChild(arrowSpan);
                }
            });
        }

        // Diagnostic & Pedagogical advice
        if (modalDiagnosticText) {
            modalDiagnosticText.textContent = row.dataset.diagnostic || 'Chưa có chẩn đoán chi tiết.';
        }
        if (modalCompetencyTitle) {
            modalCompetencyTitle.textContent = row.dataset.competencyTitle || 'Chưa có tiêu đề năng lực';
        }
        if (modalPedagogicalAdvice) {
            modalPedagogicalAdvice.textContent = row.dataset.advice || 'Chưa có khuyến nghị sư phạm.';
        }

        // Mathematical Metrics Transformed into Natural Teacher Insights
        const modalMathSlope = document.getElementById('modalMathSlope');
        const modalMathSlopeSummary = document.getElementById('modalMathSlopeSummary');
        const modalMathStability = document.getElementById('modalMathStability');
        const modalMathStabilityDesc = document.getElementById('modalMathStabilityDesc');
        const modalTargetCardsGrid = document.getElementById('modalTargetCardsGrid');
        const modalMathTargetAlert = document.getElementById('modalMathTargetAlert');
        const modalMathAcademicBadge = document.getElementById('modalMathAcademicBadge');
        const modalMathTargetPrompt = document.getElementById('modalMathTargetPrompt');

        if (modalMathAcademicBadge) {
            const sem = row.dataset.academicSemester || 'Học kỳ I';
            const phase = row.dataset.academicPhase || 'Giai đoạn Giữa kỳ I';
            modalMathAcademicBadge.textContent = `${sem} · ${phase}`;
        }
        if (modalMathTargetPrompt) {
            modalMathTargetPrompt.textContent = 'Điểm Cuối kỳ (CK) cần đạt để đạt mục tiêu:';
        }

        // 1. Phân tích Xu Hướng Học Tập cho Giáo Viên (chuyển hóa từ OLS Slope k và đối chiếu điểm thi thực tế)
        const slope = parseFloat(row.dataset.mathSlope || '0');
        const gkVal = (row.dataset.gk !== undefined && row.dataset.gk !== null && row.dataset.gk !== '') ? parseFloat(row.dataset.gk) : null;
        const ckVal = (row.dataset.ck !== undefined && row.dataset.ck !== null && row.dataset.ck !== '') ? parseFloat(row.dataset.ck) : null;
        const currentAvgVal = (row.dataset.avg !== undefined && row.dataset.avg !== null && row.dataset.avg !== '') ? parseFloat(row.dataset.avg) : null;
        const currentRisk = row.dataset.level || 'green';
        const currentMom = row.dataset.momentum || 'steady';
        const currentTier = row.dataset.tier || '';

        let slopeStatusText = '';
        let slopeDescText = '';
        let slopeColor = '#16a34a';
        let slopeBg = '#f0fdf4';
        let slopeBorder = '#86efac';

        const isPeriodicExamFailing = (gkVal !== null && gkVal < 5.0) || (ckVal !== null && ckVal < 5.0);

        if (isPeriodicExamFailing) {
            // Trường hợp học sinh có bài thi định kỳ (GK hoặc CK) dưới mức đạt (< 5.0)
            if (currentMom === 'drop' || currentMom === 'down' || slope < -0.2) {
                slopeStatusText = 'Bài thi định kỳ sút giảm';
                slopeColor = '#dc2626';
                slopeBg = '#fef2f2';
                slopeBorder = '#fca5a5';
                slopeDescText = 'Điểm bài thi định kỳ (' + (gkVal !== null && gkVal < 5.0 ? 'Giữa kỳ: ' + gkVal : 'Cuối kỳ: ' + ckVal) + ') dưới mức đạt chuẩn. Thầy/cô cần khoanh vùng lỗ hổng kiến thức để kèm cặp và phụ đạo trọng tâm.';
            } else {
                slopeStatusText = 'Lệch pha: Điểm thi chưa đạt';
                slopeColor = '#ea580c';
                slopeBg = '#fff7ed';
                slopeBorder = '#fed7aa';
                slopeDescText = 'Điểm thường xuyên giữ nhịp nhưng bài thi định kỳ (' + (gkVal !== null && gkVal < 5.0 ? 'Giữa kỳ: ' + gkVal : 'Cuối kỳ: ' + ckVal) + ') chưa đạt yêu cầu. Thầy/cô cần rèn luyện thêm kỹ năng làm bài tổng hợp và tâm lý thi cử.';
            }
        } else if (currentRisk === 'yellow' || currentRisk === 'red') {
            // Học sinh ở diện cảnh báo
            if (slope <= -0.5) {
                slopeStatusText = 'Điểm số có chiều hướng giảm';
                slopeColor = '#dc2626';
                slopeBg = '#fef2f2';
                slopeBorder = '#fca5a5';
                slopeDescText = 'Điểm các bài kiểm tra gần đây có chiều hướng giảm. Thầy cô nên lưu ý củng cố lại kiến thức trọng tâm cho học sinh.';
            } else if (slope < -0.2) {
                slopeStatusText = 'Cần lưu ý bài kiểm tra gần đây';
                slopeColor = '#ea580c';
                slopeBg = '#fff7ed';
                slopeBorder = '#fed7aa';
                slopeDescText = 'Điểm kiểm tra bài sau có chiều hướng giảm nhẹ so với bài trước. Cần nhắc nhở tinh thần học tập và kiểm tra bài tập về nhà.';
            } else if (slope <= 0.2) {
                slopeStatusText = 'Cần gia cố nhịp học tập';
                slopeColor = '#b45309';
                slopeBg = '#fefce8';
                slopeBorder = '#fde047';
                slopeDescText = 'Điểm số đang đi ngang ở ngưỡng cần lưu ý. Thầy/cô nên đôn đốc việc làm bài tập và kiểm tra định kỳ để giúp học sinh bứt phá.';
            } else {
                slopeStatusText = 'Đang nỗ lực cải thiện';
                slopeColor = '#16a34a';
                slopeBg = '#f0fdf4';
                slopeBorder = '#86efac';
                slopeDescText = 'Điểm số có dấu hiệu khởi sắc vươn lên. Cần tiếp tục duy trì đà tiến bộ này trong các đợt kiểm tra tiếp theo.';
            }
        } else {
            // Học sinh ở trạng thái ổn định / tốt
            if (isNaN(slope) || row.dataset.mathSlope === undefined || row.dataset.mathSlope === '') {
                slopeStatusText = 'Đang theo dõi chuỗi điểm';
                slopeDescText = 'Cần tối thiểu 2 bài kiểm tra thường xuyên để phân tích xu hướng.';
                slopeColor = '#64748b';
                slopeBg = '#f8fafc';
                slopeBorder = '#e2e8f0';
            } else if (slope <= -0.5) {
                slopeStatusText = 'Điểm số có chiều hướng giảm';
                slopeColor = '#ea580c';
                slopeBg = '#fff7ed';
                slopeBorder = '#fed7aa';
                slopeDescText = 'Điểm các bài kiểm tra gần đây có chiều hướng giảm nhẹ. Thầy cô nên nhắc nhở học sinh ôn lại kiến thức.';
            } else if (slope < -0.2) {
                slopeStatusText = 'Giảm nhẹ bài kiểm tra gần đây';
                slopeColor = '#b45309';
                slopeBg = '#fefce8';
                slopeBorder = '#fde047';
                slopeDescText = 'Điểm kiểm tra có dấu hiệu chững lại nhẹ. Cần duy trì phong độ làm bài đều tay.';
            } else if (slope <= 0.2) {
                if (currentTier === 'excellent' || (currentAvgVal !== null && currentAvgVal >= 8.0)) {
                    slopeStatusText = 'Phong độ xuất sắc & ổn định';
                    slopeColor = '#16a34a';
                    slopeBg = '#f0fdf4';
                    slopeBorder = '#86efac';
                    slopeDescText = 'Điểm số duy trì ổn định ở mức cao qua các đợt kiểm tra. Thầy cô có thể động viên học sinh thử sức thêm các bài nâng cao.';
                } else {
                    slopeStatusText = 'Điểm số duy trì ổn định';
                    slopeColor = '#16a34a';
                    slopeBg = '#f0fdf4';
                    slopeBorder = '#86efac';
                    slopeDescText = 'Điểm số giữ nhịp đồng đều qua các đợt kiểm tra. Học sinh có nền tảng vững, cần tiếp tục phát huy trong các kỳ thi tới.';
                }
            } else if (slope < 0.6) {
                slopeStatusText = 'Đang có chiều hướng tiến bộ';
                slopeColor = '#16a34a';
                slopeBg = '#f0fdf4';
                slopeBorder = '#86efac';
                slopeDescText = 'Điểm số có sự vươn lên qua từng bài kiểm tra. Học sinh có thái độ học tập tích cực, nên kịp thời biểu dương.';
            } else {
                slopeStatusText = 'Tiến bộ rõ rệt';
                slopeColor = '#16a34a';
                slopeBg = '#f0fdf4';
                slopeBorder = '#86efac';
                slopeDescText = 'Điểm số tăng rõ rệt, học sinh nắm chắc kiến thức và tự tin giải quyết tốt các bài kiểm tra.';
            }
        }

        if (modalMathSlope) {
            modalMathSlope.innerHTML = `<span style="color: ${slopeColor}; font-weight: 800; font-size: 14px;">${slopeStatusText}</span>`;
        }
        if (modalMathSlopeSummary) {
            modalMathSlopeSummary.textContent = slopeDescText;
        }
        const modalSlopeCard = document.getElementById('modalSlopeCard');
        if (modalSlopeCard) {
            modalSlopeCard.style.background = slopeBg;
            modalSlopeCard.style.borderColor = slopeBorder;
        }

        // 2. Phân tích Tâm Lý & Phong Độ Phòng Thi (chuyển hóa từ Độ lệch chuẩn σ)
        const stdDev = parseFloat(row.dataset.mathStddev || '0');
        let stabStatusText = '';
        let stabDescText = '';
        let stabColor = '#16a34a';
        let stabBg = '#f0fdf4';
        let stabBorder = '#86efac';

        if (isNaN(stdDev) || row.dataset.mathStddev === undefined || row.dataset.mathStddev === '') {
            stabStatusText = 'Đang theo dõi phong độ';
            stabDescText = 'Cần thêm bài kiểm tra để đánh giá độ ổn định phong độ.';
            stabColor = '#64748b';
            stabBg = '#f8fafc';
            stabBorder = '#e2e8f0';
        } else if (stdDev >= 1.5 || (isPeriodicExamFailing && stdDev >= 1.0)) {
            stabStatusText = 'Biên độ dao động lớn & Chưa vững';
            stabColor = '#dc2626';
            stabBg = '#fef2f2';
            stabBorder = '#fca5a5';
            stabDescText = isPeriodicExamFailing 
                ? 'Có khoảng cách lớn giữa điểm thường xuyên và bài thi định kỳ (' + (gkVal !== null && gkVal < 5.0 ? 'Giữa kỳ: ' + gkVal : 'Cuối kỳ: ' + ckVal) + '). Cần rèn luyện tâm lý làm bài thi độc lập và tính cẩn trọng.'
                : 'Khoảng cách giữa bài cao nhất và bài thấp nhất chênh lệch nhiều. Cần rèn luyện thêm kỹ năng làm bài thi bình tĩnh, cẩn trọng.';
        } else if (stdDev >= 0.8) {
            stabStatusText = 'Điểm số có dao động';
            stabColor = '#ea580c';
            stabBg = '#fff7ed';
            stabBorder = '#fed7aa';
            stabDescText = 'Điểm số có sự trồi sụt giữa các bài kiểm tra. Cần rèn luyện tính cẩn thận và thói quen soát lại bài trước khi nộp.';
        } else {
            stabStatusText = 'Phong độ thi cử rất đều';
            stabColor = '#16a34a';
            stabBg = '#f0fdf4';
            stabBorder = '#86efac';
            stabDescText = 'Phong độ thi cử rất ổn định, làm bài đều tay, tâm lý phòng thi vững vàng và phương pháp học tập khoa học.';
        }

        if (modalMathStability) {
            modalMathStability.innerHTML = `<span style="color: ${stabColor}; font-weight: 800; font-size: 14px;">${stabStatusText}</span>`;
        }
        if (modalMathStabilityDesc) {
            modalMathStabilityDesc.textContent = stabDescText;
        }
        const modalStabilityCard = document.getElementById('modalStabilityCard');
        if (modalStabilityCard) {
            modalStabilityCard.style.background = stabBg;
            modalStabilityCard.style.borderColor = stabBorder;
        }

        // 3. Dự Báo Điểm Cuối Kỳ Cần Đạt (Thẻ Mục Tiêu chuẩn màu: CAM Đạt, VÀNG Khá, XANH Tốt)
        if (modalTargetCardsGrid) {
            modalTargetCardsGrid.innerHTML = '';
            const targets = [
                { key: 'dat', label: 'Mức Đạt (≥ 5.0)', val: row.dataset.mathTargetDat, status: row.dataset.mathStatusDat, color: '#ea580c', bg: '#fff7ed', border: '#fed7aa' },
                { key: 'kha', label: 'Mức Khá (≥ 6.5)', val: row.dataset.mathTargetKha, status: row.dataset.mathStatusKha, color: '#b45309', bg: '#fefce8', border: '#fde047' },
                { key: 'tot', label: 'Mức Tốt (≥ 8.0)', val: row.dataset.mathTargetTot, status: row.dataset.mathStatusTot, color: '#16a34a', bg: '#f0fdf4', border: '#86efac' }
            ];

            targets.forEach(function(t) {
                const card = document.createElement('div');
                card.className = 'target-card-item';
                card.style.padding = '10px 12px';
                card.style.borderRadius = '10px';
                card.style.border = `1.5px solid ${t.border}`;
                card.style.background = t.bg;
                card.style.textAlign = 'center';

                let scoreText = '-';
                let subText = 'Điểm CK tối thiểu';
                if (t.status === 'already_achieved' || t.status === 'guaranteed') {
                    scoreText = 'ĐÃ ĐẠT';
                    subText = 'Chỉ cần thi ≥ 0đ';
                } else if (t.status === 'impossible_exceeds_10' || t.status === 'impossible_ck_only') {
                    scoreText = '> 10.0';
                    subText = 'Cần gỡ thêm điểm TX';
                } else if (t.val !== undefined && t.val !== '' && !isNaN(parseFloat(t.val))) {
                    scoreText = `${parseFloat(t.val).toFixed(1)} đ`;
                    subText = 'Điểm CK tối thiểu';
                } else {
                    scoreText = 'Chờ điểm';
                    subText = 'Chưa có điểm TX';
                }

                card.innerHTML = `
                    <div style="font-size: 11px; font-weight: 800; color: ${t.color}; text-transform: uppercase;">${t.label}</div>
                    <div style="font-size: 17px; font-weight: 900; color: ${t.color}; margin: 3px 0;">${scoreText}</div>
                    <div style="font-size: 10px; color: #64748b; font-weight: 600;">${subText}</div>
                `;
                modalTargetCardsGrid.appendChild(card);
            });
        }

        // 4. Phân Tích Phổ Điểm & Thế Mạnh Nổi Trội (Chỉ ghi nhận tình trạng thực tế)
        const profileCard = document.getElementById('modalSubjectProfileCard');
        const aptitudeBadge = document.getElementById('modalAptitudeBadge');
        const barsGrid = document.getElementById('modalSubjectBarsGrid');
        const strengthsPills = document.getElementById('modalStrengthsPills');
        const strengthsText = document.getElementById('modalStrengthsText');
        const weaknessesPills = document.getElementById('modalWeaknessesPills');
        const weaknessesText = document.getElementById('modalWeaknessesText');

        if (row.dataset.profile) {
            try {
                const prof = JSON.parse(row.dataset.profile);
                if (prof && prof.scores && Object.keys(prof.scores).length > 0) {
                    if (profileCard) profileCard.style.display = 'block';
                    if (aptitudeBadge) aptitudeBadge.textContent = prof.aptitude || 'Năng lực tổng hợp';
                    
                    if (strengthsPills) {
                        strengthsPills.innerHTML = (prof.strengths_list || []).map(function(s) {
                            return '<span style="background: #dcfce7; color: #15803d; border: 1px solid #86efac; font-size: 11px; font-weight: 700; padding: 1px 8px; border-radius: 9999px;">' + s + '</span>';
                        }).join('');
                    }
                    if (strengthsText) strengthsText.textContent = prof.strengths_text || prof.profile_summary || '';

                    if (weaknessesPills) {
                        weaknessesPills.innerHTML = (prof.weaknesses_list || []).map(function(w) {
                            return '<span style="background: #fef3c7; color: #b45309; border: 1px solid #fcd34d; font-size: 11px; font-weight: 700; padding: 1px 8px; border-radius: 9999px;">' + w + '</span>';
                        }).join('');
                    }
                    if (weaknessesText) weaknessesText.textContent = prof.weaknesses_text || '';
                    
                    if (barsGrid) {
                        let barsHtml = '';
                        for (const [subName, scoreVal] of Object.entries(prof.scores)) {
                            const num = parseFloat(scoreVal);
                            const pct = Math.min(100, Math.max(0, (num / 10) * 100));
                            let color = '#3b82f6';
                            if (num >= 8.0) { color = '#16a34a'; }
                            else if (num >= 6.5) { color = '#f59e0b'; }
                            else if (num < 5.0) { color = '#dc2626'; }
                            
                            barsHtml += '<div class="modal-subject-bar-item">' +
                                '<div class="modal-sub-bar-header">' +
                                    '<span class="modal-sub-name">' + subName + '</span>' +
                                    '<span class="modal-sub-val" style="color: ' + color + ';">' + num.toFixed(1) + '</span>' +
                                '</div>' +
                                '<div class="modal-sub-progress-track">' +
                                    '<div class="modal-sub-progress-fill" style="width: ' + pct + '%; background: ' + color + ';"></div>' +
                                '</div>' +
                            '</div>';
                        }
                        barsGrid.innerHTML = barsHtml;
                    }
                } else {
                    if (profileCard) profileCard.style.display = 'none';
                }
            } catch (e) {
                if (profileCard) profileCard.style.display = 'none';
            }
        } else {
            if (profileCard) profileCard.style.display = 'none';
        }

        // Teacher note
        const note = row.dataset.note || '';
        if (modalTeacherNoteInput) {
            modalTeacherNoteInput.value = note;
        }
        if (teacherNoteStatus) {
            teacherNoteStatus.style.display = 'none';
        }
        if (teacherNoteSavedTime) {
            teacherNoteSavedTime.textContent = '';
        }

        // Cập nhật link In / Xuất Phiếu Phụ Đạo (PDF & Excel)
        const btnModalPrintPdf = document.getElementById('btnModalPrintPdf');
        const btnModalExportXlsx = document.getElementById('btnModalExportXlsx');
        if (btnModalPrintPdf) {
            btnModalPrintPdf.href = 'in_phieu_phu_dao.php?student_id=' + currentStudentId + '&subject=' + encodeURIComponent(currentSubject) + '&format=pdf';
        }
        if (btnModalExportXlsx) {
            btnModalExportXlsx.href = 'in_phieu_phu_dao.php?student_id=' + currentStudentId + '&subject=' + encodeURIComponent(currentSubject) + '&format=xlsx';
        }

        // Tải lịch sử Audit Log & Kế hoạch can thiệp (System Design Primer)
        const auditContainer = document.getElementById('modalAuditHistoryContainer');
        if (auditContainer) {
            if (currentStudentId && currentStudentId !== 'null') {
                auditContainer.innerHTML = '<span style="color: #64748b;">Đang tải dữ liệu truy vết...</span>';
                fetch(`api_student_history.php?student_id=${currentStudentId}&subject=${encodeURIComponent(currentSubject)}`)
                    .then(r => r.json())
                    .then(data => {
                        if (!data.success) {
                            auditContainer.innerHTML = '<span style="color: #64748b;">Chưa có dữ liệu lịch sử.</span>';
                            return;
                        }
                        let html = '';
                        if (data.interventions && data.interventions.length > 0) {
                            const latestInterv = data.interventions[0];
                            const statusText = latestInterv.status === 'improved' ? 'Có chuyển biến tốt' : (latestInterv.status === 'achieved' ? 'Đã đạt mục tiêu' : 'Đang can thiệp');
                            html += `
                                <div style="background: #fffbeb; border: 1px solid #fed7aa; border-radius: 8px; padding: 8px 12px; margin-bottom: 8px;">
                                    <div style="font-weight: 700; color: #92400e; font-size: 12px; display: flex; justify-content: space-between;">
                                        <span>Kế hoạch: ${latestInterv.action_type}</span>
                                        <span>${statusText}</span>
                                    </div>
                                    ${latestInterv.teacher_notes ? `<div style="font-size: 11px; color: #78350f; margin-top: 3px; font-style: italic;">"${latestInterv.teacher_notes}"</div>` : ''}
                                </div>
                            `;
                        }
                        if (data.audit_logs && data.audit_logs.length > 0) {
                            html += '<div style="font-weight: 700; color: #334155; margin-bottom: 4px; font-size: 11px; text-transform: uppercase;">Nhật Ký Chỉnh Sửa Điểm Gần Đây:</div>';
                            html += '<div style="display: flex; flex-direction: column; gap: 4px; max-height: 140px; overflow-y: auto;">';
                            data.audit_logs.forEach(log => {
                                const oldVal = log.old_value !== null ? parseFloat(log.old_value).toFixed(1) : '-';
                                const newVal = log.new_value !== null ? parseFloat(log.new_value).toFixed(1) : '-';
                                const fName = (log.score_field || '').toUpperCase();
                                html += `
                                    <div style="display: flex; align-items: center; justify-content: space-between; font-size: 11px; padding: 4px 8px; background: #f8fafc; border-radius: 6px; border: 1px solid #f1f5f9;">
                                        <div>
                                            <span style="font-weight: 700; color: #4338ca;">[${fName}]</span>
                                            <span style="color: #64748b;">${oldVal}</span> → <span style="font-weight: 800; color: #059669;">${newVal}</span>
                                            <span style="color: #94a3b8; font-size: 10px;">(${log.changed_by_name || 'GV'})</span>
                                        </div>
                                        <span style="color: #94a3b8; font-size: 10px;">${log.created_at ? log.created_at.substring(5, 16) : ''}</span>
                                    </div>
                                `;
                            });
                            html += '</div>';
                        } else if (!data.interventions || data.interventions.length === 0) {
                            html = '<span style="color: #64748b; font-style: italic;">Dữ liệu điểm số nguyên bản, chưa qua chỉnh sửa.</span>';
                        }
                        auditContainer.innerHTML = html;
                    })
                    .catch(() => {
                        auditContainer.innerHTML = '<span style="color: #64748b;">Dữ liệu điểm số nguyên bản.</span>';
                    });
            } else {
                auditContainer.innerHTML = '<span style="color: #64748b; font-style: italic;">Chế độ khách mô phỏng (không ghi log máy chủ).</span>';
            }
        }

        // Dành cho Tuyến Khách (Guest Mode)
        const guestIdx = row.dataset.studentIdx !== undefined ? row.dataset.studentIdx : '';
        modal.dataset.currentIdx = guestIdx;
        const btnGuestPrintPdf = document.getElementById('btnGuestPrintPdf');
        const btnGuestExportXlsx = document.getElementById('btnGuestExportXlsx');
        if (btnGuestPrintPdf) {
            btnGuestPrintPdf.href = 'in_phieu_phu_dao.php?guest=1&idx=' + encodeURIComponent(guestIdx) + '&format=pdf';
        }
        if (btnGuestExportXlsx) {
            btnGuestExportXlsx.href = 'in_phieu_phu_dao.php?guest=1&idx=' + encodeURIComponent(guestIdx) + '&format=xlsx';
        }

        // Show modal with GSAP Fluid Entrance
        modal.style.display = 'flex';
        modal.classList.add('active');
        if (window.gsap) {
            gsap.fromTo(modal, { opacity: 0 }, { opacity: 1, duration: 0.22, ease: 'power2.out' });
            const modalBox = modal.querySelector('.competency-modal-card');
            if (modalBox) {
                gsap.fromTo(modalBox, 
                    { opacity: 0, scale: 0.92, y: 24 }, 
                    { opacity: 1, scale: 1, y: 0, duration: 0.38, ease: 'back.out(1.4)', clearProps: 'transform' }
                );
            }
            const statItems = modal.querySelectorAll('.modal-apple-stat-item, .modal-badge-card');
            if (statItems.length > 0) {
                gsap.fromTo(statItems,
                    { opacity: 0, y: 12 },
                    { opacity: 1, y: 0, stagger: 0.04, duration: 0.35, ease: 'power2.out', clearProps: 'transform' }
                );
            }
            const timelineNodes = modal.querySelectorAll('.score-timeline-ribbon .score-timeline-node');
            if (timelineNodes.length > 0) {
                gsap.fromTo(timelineNodes, 
                    { opacity: 0, scale: 0.75, y: 10 }, 
                    { opacity: 1, scale: 1, y: 0, stagger: 0.035, duration: 0.35, ease: 'back.out(1.8)', clearProps: 'transform' }
                );
            }
            const mathCards = modal.querySelectorAll('.math-metric-card');
            if (mathCards.length > 0) {
                gsap.fromTo(mathCards,
                    { opacity: 0, y: 14, scale: 0.96 },
                    { opacity: 1, y: 0, scale: 1, stagger: 0.05, duration: 0.38, ease: 'back.out(1.4)', clearProps: 'transform' }
                );
            }
            const targetCards = modal.querySelectorAll('.target-card-item');
            if (targetCards.length > 0) {
                gsap.fromTo(targetCards, 
                    { opacity: 0, y: 14 }, 
                    { opacity: 1, y: 0, stagger: 0.05, duration: 0.38, ease: 'back.out(1.4)', clearProps: 'transform' }
                );
            }
            const pedCards = modal.querySelectorAll('.apple-pedagogical-card, .modal-info-box');
            if (pedCards.length > 0) {
                gsap.fromTo(pedCards,
                    { opacity: 0, y: 12 },
                    { opacity: 1, y: 0, stagger: 0.04, duration: 0.35, ease: 'power2.out', clearProps: 'transform' }
                );
            }
            const subjBars = modal.querySelectorAll('#modalSubjectBarsGrid > div');
            if (subjBars.length > 0) {
                gsap.fromTo(subjBars,
                    { opacity: 0, x: -10 },
                    { opacity: 1, x: 0, stagger: 0.025, duration: 0.32, ease: 'power2.out', clearProps: 'transform' }
                );
            }
        }
    }

    function closeStudentModal() {
        if (!modal) return;
        if (window.gsap) {
            const modalBox = modal.querySelector('.competency-modal-card');
            if (modalBox) {
                gsap.to(modalBox, {
                    opacity: 0, scale: 0.95, y: 14, duration: 0.18, ease: 'power2.in',
                    onComplete: function() {
                        modal.classList.remove('active');
                        modal.style.display = 'none';
                        gsap.set(modalBox, { clearProps: 'all' });
                    }
                });
            } else {
                modal.classList.remove('active');
                modal.style.display = 'none';
            }
        } else {
            modal.classList.remove('active');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 200);
        }
    }

    // Attach row click listeners (both main rows and sub-rows)
    document.querySelectorAll('tbody tr[data-level]').forEach(function (r) {
        if (!r || typeof r.addEventListener !== 'function') return;
        r.addEventListener('click', function (e) {
            // Không mở modal nếu nhấn trực tiếp vào link sổ điểm hoặc nút toggle subrows
            if (e && e.target && typeof e.target.closest === 'function') {
                if (e.target.closest('a') || e.target.closest('.btn-goto-gradebook') || e.target.closest('.btn-toggle-subrows')) {
                    return;
                }
            }
            openStudentModal(this);
        });

        const detailBtn = (typeof r.querySelector === 'function') ? r.querySelector('.btn-open-detail') : null;
        if (detailBtn && typeof detailBtn.addEventListener === 'function') {
            detailBtn.addEventListener('click', function (e) {
                if (e && typeof e.preventDefault === 'function') e.preventDefault();
                if (e && typeof e.stopPropagation === 'function') e.stopPropagation();
                openStudentModal(r);
            });
        }
    });

    if (modalCloseBtn) {
        modalCloseBtn.addEventListener('click', closeStudentModal);
    }

    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                closeStudentModal();
            }
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal && modal.classList.contains('active')) {
            closeStudentModal();
        }
    });

    // -------------------------------------------------------------------------
    // 4. AJAX LƯU NHẬN XÉT SƯ PHẠM VÀO DATABASE VỚI LIQUID GLASS BUTTON (PROMPT #001)
    // -------------------------------------------------------------------------
    const u001NoteSaver = document.getElementById('u001ModalNoteSaver');
    const u001Live = u001NoteSaver?.querySelector('.u001-live');

    if (btnSaveTeacherNote && modalTeacherNoteInput) {
        btnSaveTeacherNote.addEventListener('click', function () {
            if (currentStudentId <= 0 || !currentSubject) {
                alert('Không xác định được học sinh hoặc môn học!');
                return;
            }

            const noteVal = modalTeacherNoteInput.value.trim();

            if (u001NoteSaver) {
                u001NoteSaver.dataset.state = 'load';
                btnSaveTeacherNote.setAttribute('aria-busy', 'true');
                if (u001Live) u001Live.textContent = 'Đang lưu nhận xét...';
            }
            btnSaveTeacherNote.disabled = true;
            if (teacherNoteStatus) teacherNoteStatus.style.display = 'none';

            const formData = new FormData();
            formData.append('action', 'save_teacher_note');
            formData.append('student_id', currentStudentId);
            formData.append('subject', currentSubject);
            formData.append('note', noteVal);

            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(res => {
                if (!res.ok) {
                    throw new Error('Mã phản hồi HTTP: ' + res.status);
                }
                return res.json();
            })
            .then(data => {
                btnSaveTeacherNote.disabled = false;

                if (data.success) {
                    if (u001NoteSaver) {
                        u001NoteSaver.dataset.state = 'ok';
                        btnSaveTeacherNote.removeAttribute('aria-busy');
                        if (u001Live) u001Live.textContent = 'Đã lưu nhận xét';
                        setTimeout(function () {
                            u001NoteSaver.dataset.state = 'idle';
                            if (u001Live) u001Live.textContent = '';
                        }, 2500);
                    }

                    if (teacherNoteStatus) {
                        teacherNoteStatus.style.display = 'inline-flex';
                        teacherNoteStatus.textContent = data.message || 'Đã lưu nhận xét thành công!';
                    }
                    if (teacherNoteSavedTime && data.updated_at) {
                        teacherNoteSavedTime.textContent = 'Cập nhật lúc: ' + data.updated_at;
                    }

                    // Cập nhật ngay lập tức vào DOM dòng học sinh tương ứng
                    if (activeRowElement) {
                        activeRowElement.dataset.note = noteVal;
                        const noteBadge = activeRowElement.querySelector('.js-note-badge');
                        if (noteBadge) {
                            if (noteVal) {
                                noteBadge.style.display = 'inline-flex';
                                noteBadge.title = noteVal.substring(0, 80) + '...';
                            } else {
                                noteBadge.style.display = 'none';
                            }
                        }
                    }
                } else {
                    if (u001NoteSaver) {
                        u001NoteSaver.dataset.state = 'idle';
                        btnSaveTeacherNote.removeAttribute('aria-busy');
                    }
                    alert('Lỗi: ' + (data.error || 'Không thể lưu nhận xét'));
                }
            })
            .catch(err => {
                btnSaveTeacherNote.disabled = false;
                if (u001NoteSaver) {
                    u001NoteSaver.dataset.state = 'idle';
                    btnSaveTeacherNote.removeAttribute('aria-busy');
                }
                alert('Lỗi kết nối máy chủ: ' + err.message);
            });
        });
    }

    // -------------------------------------------------------------------------
    // 5. LIQUID GLASS BUTTON (PROMPT #001) INTERACTION & MAGNETIC HOVER PULL
    // -------------------------------------------------------------------------
    const rmGlass = (typeof window.matchMedia === 'function') ? window.matchMedia('(prefers-reduced-motion: reduce)').matches : false;
    document.querySelectorAll('.u001').forEach(function(uRoot) {
        const btn = (typeof uRoot.querySelector === 'function') ? uRoot.querySelector('.u001-btn') : null;
        const mag = (typeof uRoot.querySelector === 'function') ? uRoot.querySelector('.u001-mag') : null;
        if (!btn || !mag) return;

        uRoot.addEventListener('pointermove', function (e) {
            const r = btn.getBoundingClientRect();
            const dx = e.clientX - (r.left + r.width / 2);
            const dy = e.clientY - (r.top + r.height / 2);
            const pull = rmGlass ? 0 : Math.max(0, 1 - Math.hypot(dx, dy) / 190);
            mag.style.translate = (dx * 0.18 * pull).toFixed(1) + 'px ' + (dy * 0.18 * pull).toFixed(1) + 'px';
            btn.style.setProperty('--mx', ((e.clientX - r.left) / r.width * 100).toFixed(1) + '%');
            btn.style.setProperty('--my', ((e.clientY - r.top) / r.height * 100).toFixed(1) + '%');
        });

        uRoot.addEventListener('pointerleave', function () {
            mag.style.translate = '0px 0px';
        });
    });

    const u001FilterBtn = document.getElementById('u001FilterBtnWrapper');
    const btnFilterApply = document.getElementById('btnFilterApply');
    if (btnFilterApply && u001FilterBtn) {
        btnFilterApply.addEventListener('click', function() {
            u001FilterBtn.dataset.state = 'load';
        });
    }

    // -------------------------------------------------------------------------
    // 6. COLLAPSIBLE MULTI-SUBJECT SUB-ROWS & SCORE DETAIL COLUMN TOGGLE
    // -------------------------------------------------------------------------
    window.toggleStudentSubrows = function(studentId, btn) {
        if (!studentId) return;
        const subrows = document.querySelectorAll('.student-sub-row[data-parent-student="' + studentId + '"]');
        const isExpanded = btn.classList.contains('expanded');
        if (isExpanded) {
            btn.classList.remove('expanded');
            btn.textContent = '▶';
            subrows.forEach(function(row) {
                row.style.display = 'none';
            });
        } else {
            btn.classList.add('expanded');
            btn.textContent = '▼';
            subrows.forEach(function(row) {
                row.style.display = '';
            });
            if (window.gsap && subrows.length > 0) {
                gsap.fromTo(subrows, 
                    { opacity: 0, y: -4 }, 
                    { opacity: 1, y: 0, duration: 0.22, stagger: 0.025, ease: 'power1.out', clearProps: 'transform,opacity' }
                );
            }
        }
    };

    window.toggleScoreDetailColumns = function() {
        const table = document.getElementById('radarTable');
        const btn = document.getElementById('btnToggleScoreCols');
        if (!table) return;
        const isHidden = table.classList.contains('scores-hidden');
        if (window.gsap && btn) {
            gsap.fromTo(btn, { scale: 0.93 }, { scale: 1, duration: 0.25, ease: 'back.out(2)', clearProps: 'transform' });
        }
        if (isHidden) {
            table.classList.remove('scores-hidden');
            if (btn) {
                btn.classList.add('active');
                btn.textContent = 'Thu gọn cột điểm';
            }
            if (window.gsap) {
                const detailCells = table.querySelectorAll('tbody tr:not([style*="display: none"]) .col-detail-score');
                if (detailCells.length > 0) {
                    gsap.fromTo(Array.from(detailCells).slice(0, 120),
                        { opacity: 0, scale: 0.92 },
                        { opacity: 1, scale: 1, duration: 0.22, stagger: 0.003, ease: 'power2.out', clearProps: 'transform,opacity' }
                    );
                }
            }
        } else {
            table.classList.add('scores-hidden');
            if (btn) {
                btn.classList.remove('active');
                btn.textContent = 'Hiện tất cả điểm';
            }
        }
    };

    // -------------------------------------------------------------------------
    // 7. MOBILE RING GAUGE HELP BOTTOM-SHEET MODAL
    // -------------------------------------------------------------------------
    window.openRingHelp = function(type) {
        const backdrop = document.getElementById('ringHelpBackdrop');
        const sheet = document.getElementById('ringHelpSheet');
        const titleEl = document.getElementById('ringHelpSheetTitle');
        const subEl = document.getElementById('ringHelpSheetSub');
        const contentEl = document.getElementById('ringHelpSheetContent');
        const dataScript = document.getElementById('ringHelpData');

        if (!backdrop || !sheet || !dataScript) return;

        try {
            const allData = JSON.parse(dataScript.textContent);
            const data = allData[type];
            if (!data) return;

            if (titleEl) titleEl.textContent = data.title;
            if (subEl) subEl.textContent = data.sub;

            if (contentEl) {
                let html = '';
                data.items.forEach(function(item) {
                    const countBadge = item.count !== undefined 
                        ? '<span class="ring-sheet-item-count">' + item.count + ' HS (' + item.pct + '%)</span>' 
                        : '';
                    html += '<div class="ring-sheet-item">' +
                        '<span class="ring-sheet-dot" style="background: ' + item.color + ';"></span>' +
                        '<div class="ring-sheet-item-content">' +
                            '<div class="ring-sheet-item-header">' +
                                '<span class="ring-sheet-item-name">' + item.label + '</span>' +
                                countBadge +
                            '</div>' +
                            '<p class="ring-sheet-item-desc">' + item.desc + '</p>' +
                        '</div>' +
                    '</div>';
                });
                contentEl.innerHTML = html;
            }

            backdrop.classList.add('active');
            sheet.classList.add('active');
            document.body.style.overflow = 'hidden';

            if (window.gsap) {
                gsap.fromTo(backdrop, { opacity: 0 }, { opacity: 1, duration: 0.22, ease: 'power2.out' });
                gsap.fromTo(sheet, { y: 40, opacity: 0 }, { y: 0, opacity: 1, duration: 0.32, ease: 'back.out(1.4)', clearProps: 'transform' });
                const sheetItems = sheet.querySelectorAll('.ring-sheet-item');
                if (sheetItems.length > 0) {
                    gsap.fromTo(sheetItems,
                        { opacity: 0, y: 12 },
                        { opacity: 1, y: 0, duration: 0.28, stagger: 0.04, ease: 'power2.out', clearProps: 'transform' }
                    );
                }
            }
        } catch (e) {
            console.error('Error rendering ring help sheet:', e);
        }
    };

    window.closeRingHelp = function() {
        const backdrop = document.getElementById('ringHelpBackdrop');
        const sheet = document.getElementById('ringHelpSheet');
        if (backdrop) backdrop.classList.remove('active');
        if (sheet) sheet.classList.remove('active');
        document.body.style.overflow = '';
    };

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            window.closeRingHelp();
        }
    });

    // -------------------------------------------------------------------------
    // 7. VNEDU SCHEDULE LOCK & SCORE COLUMN SIMULATION
    // -------------------------------------------------------------------------
    window.toggleVnEduCkLock = function() {
        const lockedInputs = document.querySelectorAll('.cell-ck .score-input');
        const indicator = document.getElementById('vneduLockIndicator');
        const btnLabel = document.getElementById('btnLockLabel');
        const btnIcon = document.getElementById('btnLockIcon');
        const isCurrentlyLocked = Array.from(lockedInputs).some(inp => inp.hasAttribute('readonly'));

        if (isCurrentlyLocked) {
            // Mở khóa nhập trước
            lockedInputs.forEach(inp => {
                inp.removeAttribute('readonly');
                inp.classList.remove('is-locked-vnedu');
                inp.placeholder = '—';
            });
            if (indicator) {
                indicator.textContent = 'Đã mở sớm';
                indicator.style.background = '#dcfce7';
                indicator.style.color = '#15803d';
            }
            if (btnLabel) btnLabel.textContent = 'Khóa theo lịch VnEdu';
            if (btnIcon) btnIcon.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>';

            if (window.gsap) {
                gsap.fromTo('.btn-vnedu-toggle-lock', { scale: 0.95 }, { scale: 1, duration: 0.25, ease: 'power2.out' });
                gsap.fromTo('.cell-ck .score-input', 
                    { backgroundColor: '#fef08a' }, 
                    { backgroundColor: '#ffffff', duration: 0.4, stagger: 0.01, ease: 'power2.out', clearProps: 'backgroundColor' }
                );
            }
        } else {
            // Khóa lại theo lịch VnEdu
            lockedInputs.forEach(inp => {
                if (!inp.value || inp.value.trim() === '') {
                    inp.setAttribute('readonly', 'readonly');
                    inp.classList.add('is-locked-vnedu');
                    inp.placeholder = '—';
                }
            });
            if (indicator) {
                indicator.textContent = 'Khóa theo lịch (15/12)';
                indicator.style.background = '';
                indicator.style.color = '';
            }
            if (btnLabel) btnLabel.textContent = 'Mở khóa nhập sớm';
            if (btnIcon) btnIcon.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>';

            if (window.gsap) {
                gsap.fromTo('.btn-vnedu-toggle-lock', { scale: 0.95 }, { scale: 1, duration: 0.25, ease: 'power2.out' });
                gsap.fromTo('.cell-ck .score-input.is-locked-vnedu', 
                    { opacity: 0.6 }, 
                    { opacity: 1, duration: 0.25, stagger: 0.008, ease: 'power1.out', clearProps: 'opacity' }
                );
            }
        }
    };

    // -------------------------------------------------------------------------
    // 8. GSAP ENGINE: FULL-SITE ENTRANCE & INTERACTION SUITE (ZERO SCROLL JITTER)
    // -------------------------------------------------------------------------
    if (window.gsap) {
        const plugins = [];
        if (window.ScrollTrigger) plugins.push(window.ScrollTrigger);
        if (window.ScrollToPlugin) plugins.push(window.ScrollToPlugin);
        if (window.Flip) plugins.push(window.Flip);
        if (window.CustomEase) plugins.push(window.CustomEase);
        if (window.TextPlugin) plugins.push(window.TextPlugin);
        if (plugins.length > 0) {
            try {
                gsap.registerPlugin(...plugins);
            } catch (e) {}
        }
    }

    window.runEduRadarEntrance = function() {
        if (!window.gsap) return;

        const tl = gsap.timeline({ defaults: { ease: 'power3.out' } });

        // 1. Sidebar Navigation Items (includes/side_rail.php)
        const navItems = document.querySelectorAll('.u040-nav > li > .u040-it');
        if (navItems.length > 0) {
            tl.fromTo(navItems,
                { opacity: 0, x: -12 },
                { opacity: 1, x: 0, duration: 0.35, stagger: 0.04, clearProps: 'transform,opacity' },
                0
            );
            const activeNav = document.querySelector('.u040-it[aria-current="page"]');
            if (activeNav) {
                gsap.fromTo(activeNav,
                    { scale: 0.94 },
                    { scale: 1, duration: 0.45, ease: 'back.out(2)', clearProps: 'transform' }
                );
            }
            navItems.forEach(function(item) {
                if (item.dataset.gsapBound) return;
                item.dataset.gsapBound = '1';
                const iconSvg = item.querySelector('svg');
                const labelSpan = item.querySelector('.u040-lb');
                item.addEventListener('mouseenter', function() {
                    if (iconSvg) gsap.to(iconSvg, { scale: 1.16, rotate: -4, duration: 0.24, ease: 'back.out(2)' });
                    if (labelSpan) gsap.to(labelSpan, { x: 3, duration: 0.22, ease: 'power2.out' });
                });
                item.addEventListener('mouseleave', function() {
                    if (iconSvg) gsap.to(iconSvg, { scale: 1, rotate: 0, duration: 0.22, ease: 'power2.out', clearProps: 'transform' });
                    if (labelSpan) gsap.to(labelSpan, { x: 0, duration: 0.22, ease: 'power2.out', clearProps: 'transform' });
                });
            });
        }

        // 2. Hero, Page Headers & Top Sections across all 5 pages
        const heroSections = document.querySelectorAll(
            '.welcome-hero, .hero-banner-card, .gb-header-card, .guest-banner, header.top, .matrix-filter-card, .class-evaluation-panel, .no-scores-alert-banner'
        );
        if (heroSections.length > 0) {
            tl.fromTo(heroSections,
                { opacity: 0, y: -14 },
                { opacity: 1, y: 0, duration: 0.42, stagger: 0.06, clearProps: 'transform,opacity' },
                0.02
            );
        }

        // 2.1. Awwwards Masked Text Reveal (h1.welcome-title / .masked-text-title)
        const maskedInners = document.querySelectorAll('.masked-text-title .masked-line-inner');
        if (maskedInners.length > 0) {
            tl.fromTo(maskedInners,
                { y: '110%' },
                { y: '0%', duration: 1.0, ease: 'power4.out', stagger: 0.15, clearProps: 'transform' },
                0.1
            );
        }

        // 2.2. Awwwards Hero Subtitle & CTA Reveal
        const heroDesc = document.querySelector('.welcome-hero .welcome-desc');
        const heroBadge = document.querySelector('.welcome-hero .welcome-badge');
        const heroCta = document.querySelector('.welcome-hero .welcome-cta-group');
        if (heroBadge) {
            tl.fromTo(heroBadge, 
                { opacity: 0, y: -8, scale: 0.94 }, 
                { opacity: 1, y: 0, scale: 1, duration: 0.5, ease: 'back.out(1.5)', clearProps: 'transform,opacity' }, 
                0.05
            );
        }
        if (heroDesc) {
            tl.fromTo(heroDesc, 
                { opacity: 0, y: 12 }, 
                { opacity: 1, y: 0, duration: 0.65, ease: 'power3.out', clearProps: 'transform,opacity' }, 
                0.35
            );
        }
        if (heroCta) {
            tl.fromTo(heroCta.children, 
                { opacity: 0, y: 14, scale: 0.95 }, 
                { opacity: 1, y: 0, scale: 1, duration: 0.5, stagger: 0.08, ease: 'back.out(1.4)', clearProps: 'transform,opacity' }, 
                0.45
            );
        }

        // 3. Navigation Strips, Tabs & Filter Pills across all pages
        const stripsAndPills = document.querySelectorAll(
            '.class-chip-btn, .btn-grade-pill, .gb-chips-strip a, .btn-admin-tab, .btn-gb-filter, .chip'
        );
        if (stripsAndPills.length > 0) {
            tl.fromTo(Array.from(stripsAndPills).slice(0, 45),
                { opacity: 0, scale: 0.9, y: 8 },
                { opacity: 1, scale: 1, y: 0, duration: 0.34, stagger: 0.015, ease: 'back.out(1.6)', clearProps: 'transform,opacity' },
                0.08
            );
        }

        // 4. Cards, Panels, KPI Boxes & Feature Grid across all pages (Bao phủ toàn bộ các thẻ trên trang)
        const allCards = document.querySelectorAll(
            '.launchpad-card, .kpi-card, .hero-kpi-box, .stat-card, .stats .card, .feature-item, .panel-box, .grade-card, .assign-grade-box, .step-card, .handbook-card, .eval-stat-item, .toolbar-unified, .spreadsheet-toolbar, .gradebook-filter-bar, .upload-form-grid, .stepper-container, .quick-search-box, .handbook-box'
        );
        if (allCards.length > 0) {
            tl.fromTo(Array.from(allCards),
                { opacity: 0, y: 14 },
                { opacity: 1, y: 0, duration: 0.38, stagger: 0.02, ease: 'power2.out', clearProps: 'transform,opacity' },
                0.08
            );
        }

        // 5. GSAP Live Number Counter for KPIs & Stat Cards across all pages
        const numberEls = document.querySelectorAll(
            '.stat-card b, .stats .card b, .hero-kpi-box div[style*="font-size: 26px"], .kpi-card div[style*="font-size: 26px"], .eval-stat-value'
        );
        numberEls.forEach(function(el) {
            if (el.dataset.gsapCounted) return;
            const raw = (el.textContent || '').trim();
            if (!/^\d+(\.\d+)?%?$/.test(raw)) return;
            const isPct = raw.endsWith('%');
            const isFloat = raw.includes('.');
            const endVal = parseFloat(raw);
            if (isNaN(endVal) || endVal === 0) return;
            el.dataset.gsapCounted = '1';
            const proxy = { val: 0 };
            gsap.to(proxy, {
                val: endVal,
                duration: 0.85,
                delay: 0.12,
                ease: 'power2.out',
                onUpdate: function() {
                    const formatted = isFloat ? proxy.val.toFixed(1) : Math.round(proxy.val).toString();
                    el.textContent = formatted + (isPct ? '%' : '');
                },
                onComplete: function() {
                    el.textContent = raw;
                }
            });
        });

        // 6. GSAP SVG Circular Ring & Segmented Donut Draw (index.php)
        const radialFills = document.querySelectorAll('.radial-fill');
        radialFills.forEach(function(circle) {
            const targetDash = circle.getAttribute('stroke-dasharray');
            if (targetDash) {
                gsap.fromTo(circle,
                    { attr: { 'stroke-dasharray': '0, 100' } },
                    { attr: { 'stroke-dasharray': targetDash }, duration: 0.95, delay: 0.15, ease: 'power3.out' }
                );
            }
        });
        const donutSegs = document.querySelectorAll('.donut-segment');
        if (donutSegs.length > 0) {
            gsap.fromTo(donutSegs,
                { opacity: 0 },
                { opacity: 1, duration: 0.5, stagger: 0.08, delay: 0.18, ease: 'power2.out' }
            );
        }

        // 7. Dropzone & Floating Orb on Quét Nhanh (quet_nhanh.php) & Admin (admin.php)
        const dropzones = document.querySelectorAll('#u032DropzoneWrap, #adminStudentDropzone');
        if (dropzones.length > 0) {
            tl.fromTo(dropzones,
                { opacity: 0, scale: 0.96, y: 14 },
                { opacity: 1, scale: 1, y: 0, duration: 0.45, ease: 'back.out(1.4)', clearProps: 'transform,opacity' },
                0.12
            );
        }
        const orb = document.querySelector('.u032-orb');
        if (orb && !orb.dataset.gsapFloating) {
            orb.dataset.gsapFloating = '1';
            gsap.to(orb, {
                y: -6,
                duration: 1.8,
                repeat: -1,
                yoyo: true,
                ease: 'sine.inOut'
            });
        }

        // 8. Table Rows & Mobile Student Cards Cascade across all pages
        const tableRows = document.querySelectorAll(
            '.radar-table tbody tr.radar-row:not(#radarEmptyRow), #gradebookTable tbody tr[data-student-id], #assignmentTable tbody tr, #auditLogTable tbody tr, .m-student-row'
        );
        if (tableRows.length > 0) {
            const visibleRows = Array.from(tableRows).filter(r => r.style.display !== 'none').slice(0, 35);
            if (visibleRows.length > 0) {
                tl.fromTo(visibleRows,
                    { opacity: 0, y: 10 },
                    { opacity: 1, y: 0, duration: 0.34, stagger: 0.012, ease: 'power2.out', clearProps: 'transform,opacity' },
                    0.15
                );
            }
        }

        // 9. Class Pills inside Admin Khối/Lớp cards (admin.php)
        const classPills = document.querySelectorAll('.class-pill, .class-chip-box');
        if (classPills.length > 0) {
            tl.fromTo(Array.from(classPills).slice(0, 60),
                { opacity: 0, scale: 0.88 },
                { opacity: 1, scale: 1, duration: 0.3, stagger: 0.008, ease: 'back.out(1.5)', clearProps: 'transform,opacity' },
                0.18
            );
        }

        // 10. VnEdu Schedule Banner (nhap_diem.php)
        const banner = document.getElementById('vneduScheduleBar');
        if (banner) {
            tl.fromTo(banner,
                { opacity: 0, y: -8 },
                { opacity: 1, y: 0, duration: 0.35, ease: 'power2.out', clearProps: 'transform,opacity' },
                0.08
            );
        }

        // 11. Smooth Anchor Scrolling via ScrollToPlugin
        document.querySelectorAll('a[href^="#"]:not([href="#"])').forEach(link => {
            if (link.dataset.gsapScrollBound) return;
            link.dataset.gsapScrollBound = '1';
            link.addEventListener('click', function(e) {
                const target = document.querySelector(this.getAttribute('href'));
                if (target && window.ScrollToPlugin) {
                    e.preventDefault();
                    gsap.to(window, {
                        duration: 0.5,
                        scrollTo: { y: target, offsetY: 60 },
                        ease: 'power2.out'
                    });
                }
            });
        });

        // 12. Micro-Interactions: Nhẹ nhàng, không nghiêng lố
        // Đã gỡ bỏ 3D tilt và magnetic chuột quá mức theo yêu cầu giao diện sư phạm tối giản
    };

    // Run entrance animations smoothly
    if (window.gsap) {
        window.runEduRadarEntrance();
    }
});
