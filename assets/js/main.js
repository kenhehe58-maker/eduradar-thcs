// =============================================================================
// EduRadar THCS: Master Client-side Interactions
// Features:
// 1. Collapsible Left-side Navigation Sidebar with localStorage Persistence
// 2. Real-time Multi-Dimensional Filter Chips & Instant Student Search
// 3. Expandable Student Competency Profile Modal with Score Timeline
// 4. AJAX Teacher Pedagogical Note Saver with Real-time Audit Feedback
// =============================================================================

document.addEventListener('DOMContentLoaded', function () {
    // -------------------------------------------------------------------------
    // 1. COLLAPSIBLE LEFT-SIDE NAVIGATION SIDEBAR
    // -------------------------------------------------------------------------
    const sidebar = document.getElementById('appSidebar') || document.querySelector('.sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mainContent = document.querySelector('.content');

    // Đồng bộ trạng thái ban đầu từ localStorage
    try {
        const isCollapsed = localStorage.getItem('edu_sidebar_collapsed') === '1';
        if (isCollapsed) {
            document.documentElement.classList.add('sidebar-is-collapsed');
            sidebar?.classList.add('collapsed');
            mainContent?.classList.add('sidebar-collapsed');
        }
    } catch (e) {}

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const willCollapse = !document.documentElement.classList.contains('sidebar-is-collapsed');
            if (willCollapse) {
                document.documentElement.classList.add('sidebar-is-collapsed');
                sidebar.classList.add('collapsed');
                mainContent?.classList.add('sidebar-collapsed');
                try { localStorage.setItem('edu_sidebar_collapsed', '1'); } catch (err) {}
            } else {
                document.documentElement.classList.remove('sidebar-is-collapsed');
                sidebar.classList.remove('collapsed');
                mainContent?.classList.remove('sidebar-collapsed');
                try { localStorage.setItem('edu_sidebar_collapsed', '0'); } catch (err) {}
            }
        });
    }

    // -------------------------------------------------------------------------
    // 2. RADAR DASHBOARD: REAL-TIME CLIENT MULTI-SELECT FILTER & INSTANT SEARCH
    // -------------------------------------------------------------------------
    const rows = document.querySelectorAll('tbody tr[data-level]');
    const chips = document.querySelectorAll('.chip[data-filter]');
    const search = document.getElementById('search');
    const emptyRow = document.getElementById('radarEmptyRow') || document.querySelector('.radar-filter-empty-row');
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
            return (tier === 'low');
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

        rows.forEach(function (r) {
            const name = (r.dataset.name || '').toLowerCase();
            const searchAttr = (r.dataset.search || '').toLowerCase();

            let okFilter = false;
            if (currentFilters.length === 0 || currentFilters.includes('all')) {
                okFilter = true;
            } else {
                okFilter = currentFilters.some(function(f) { return matchRowSingleFilter(r, f); });
            }

            const okSearch = !q || name.includes(q) || searchAttr.includes(q);

            if (okFilter && okSearch) {
                r.style.display = '';
                visibleCount++;
            } else {
                r.style.display = 'none';
            }
        });

        if (emptyRow) {
            emptyRow.style.display = (visibleCount === 0 && rows.length > 0) ? '' : 'none';
        }
    }

    chips.forEach(function (c) {
        c.addEventListener('click', function (e) {
            const f = this.dataset.filter || 'all';
            const allChip = document.querySelector('.chip[data-filter="all"]');

            if (f === 'all') {
                chips.forEach(function (x) { x.classList.remove('active'); });
                this.classList.add('active');
                currentFilters = ['all'];
            } else {
                if (allChip) allChip.classList.remove('active');
                this.classList.toggle('active');

                const activeChips = Array.from(document.querySelectorAll('.chip[data-filter].active'))
                    .map(function(x) { return x.dataset.filter; })
                    .filter(function(x) { return x && x !== 'all'; });

                if (activeChips.length === 0) {
                    if (allChip) allChip.classList.add('active');
                    currentFilters = ['all'];
                } else {
                    currentFilters = activeChips;
                }
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
            modalStudentBirthday.textContent = birthday ? '• 🎂 ' + birthday : '';
            modalStudentBirthday.style.display = birthday ? 'inline' : 'none';
        }
        if (modalStudentPhone) {
            modalStudentPhone.textContent = phone ? '• 📞 ' + phone : '';
            modalStudentPhone.style.display = phone ? 'inline' : 'none';
        }

        // Dual Framework Badges (Chuẩn 4 màu: ĐỎ - CAM - VÀNG - XANH)
        const mom = row.dataset.momentum || 'steady';
        let momBadgeHtml = '';
        if (mom === 'drop') {
            momBadgeHtml = '<span style="color: #dc2626; font-size: 15px; font-weight: 800;">🔴 Tuột dốc</span>';
        } else if (mom === 'down') {
            momBadgeHtml = '<span style="color: #ea580c; font-size: 15px; font-weight: 800;">🟠 Sa sút</span>';
        } else if (mom === 'steady') {
            momBadgeHtml = '<span style="color: #b45309; font-size: 15px; font-weight: 800;">🟡 Duy trì đều</span>';
        } else if (mom === 'surge') {
            momBadgeHtml = '<span style="color: #16a34a; font-size: 15px; font-weight: 800;">🟢 Bứt phá</span>';
        } else if (mom === 'up') {
            momBadgeHtml = '<span style="color: #16a34a; font-size: 15px; font-weight: 800;">🟢 Đang tiến bộ</span>';
        } else {
            momBadgeHtml = '<span style="color: #64748b; font-size: 15px; font-weight: 800;">⏳ Chờ điểm</span>';
        }
        if (modalMomentumBadge) {
            modalMomentumBadge.innerHTML = momBadgeHtml;
        }

        const tier = row.dataset.tier || '';
        const avgVal = parseFloat(row.dataset.avg || '-1');
        let tierBadgeHtml = '';
        if (tier === 'excellent' || avgVal >= 8.0) {
            tierBadgeHtml = '<span style="color: #16a34a; font-size: 15px; font-weight: 800;">🟢 Tốt / Giỏi</span>';
        } else if (tier === 'good' || (avgVal >= 6.5 && avgVal < 8.0)) {
            tierBadgeHtml = '<span style="color: #b45309; font-size: 15px; font-weight: 800;">🟡 Khá</span>';
        } else if (tier === 'average' || (avgVal >= 5.0 && avgVal < 6.5)) {
            tierBadgeHtml = '<span style="color: #ea580c; font-size: 15px; font-weight: 800;">🟠 Trung bình</span>';
        } else if (tier === 'low' || (avgVal >= 0 && avgVal < 5.0)) {
            tierBadgeHtml = '<span style="color: #dc2626; font-size: 15px; font-weight: 800;">🔴 Chưa đạt</span>';
        } else {
            tierBadgeHtml = '<span style="color: #64748b; font-size: 15px; font-weight: 800;">⏳ Chờ điểm</span>';
        }
        if (modalScoreTierBadge) {
            modalScoreTierBadge.innerHTML = tierBadgeHtml;
        }

        const riskLevel = row.dataset.level || 'green';
        if (modalRiskBadge) {
            if (riskLevel === 'red') {
                modalRiskBadge.innerHTML = '<span style="color: #dc2626; font-size: 15px; font-weight: 800;">🔴 Nguy cấp</span>';
            } else if (riskLevel === 'yellow') {
                modalRiskBadge.innerHTML = '<span style="color: #ea580c; font-size: 15px; font-weight: 800;">🟠 Cần chú ý</span>';
            } else if (riskLevel === 'pending') {
                modalRiskBadge.innerHTML = '<span style="color: #64748b; font-size: 15px; font-weight: 800;">⏳ Chờ điểm số</span>';
            } else {
                modalRiskBadge.innerHTML = '<span style="color: #16a34a; font-size: 15px; font-weight: 800;">🟢 Ổn định</span>';
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
                { name: 'ĐTB', val: row.dataset.avg, isGk: false, isCk: false, isAvg: true },
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
            modalMathTargetPrompt.textContent = `🎯 Điểm Cuối Kỳ I (CK1) tối thiểu cần đạt để hoàn thành mục tiêu học kỳ:`;
        }

        // 1. Phân tích Xu Hướng Học Tập cho Giáo Viên (chuyển hóa từ OLS Slope k)
        const slope = parseFloat(row.dataset.mathSlope || '0');
        let slopeStatusText = '';
        let slopeDescText = '';
        let slopeColor = '#16a34a';
        let slopeBg = '#f0fdf4';
        let slopeBorder = '#86efac';

        if (isNaN(slope) || row.dataset.mathSlope === undefined || row.dataset.mathSlope === '') {
            slopeStatusText = '⏳ Đang theo dõi chuỗi điểm';
            slopeDescText = 'Cần tối thiểu 2 bài kiểm tra thường xuyên để phân tích xu hướng.';
            slopeColor = '#64748b';
            slopeBg = '#f8fafc';
            slopeBorder = '#e2e8f0';
        } else if (slope <= -0.5) {
            slopeStatusText = '🔴 Đang tuột dốc liên tiếp';
            slopeColor = '#dc2626';
            slopeBg = '#fef2f2';
            slopeBorder = '#fca5a5';
            slopeDescText = 'Điểm các bài kiểm tra gần đây sụt giảm rõ rệt. Em đang có dấu hiệu hổng kiến thức ở các bài học gần đây, thầy cô cần kèm cặp và ôn tập ngay!';
        } else if (slope < -0.2) {
            slopeStatusText = '🟠 Có dấu hiệu sa sút';
            slopeColor = '#ea580c';
            slopeBg = '#fff7ed';
            slopeBorder = '#fed7aa';
            slopeDescText = 'Điểm kiểm tra bài sau có chiều hướng giảm nhẹ so với bài trước. Cần nhắc nhở tinh thần học tập và kiểm tra bài tập về nhà.';
        } else if (slope <= 0.2) {
            slopeStatusText = '🟡 Phong độ đi đều, ổn định';
            slopeColor = '#b45309';
            slopeBg = '#fefce8';
            slopeBorder = '#fde047';
            slopeDescText = 'Điểm số giữ nhịp đồng đều qua các đợt kiểm tra. Thầy cô nên động viên, giao thêm bài tập nâng cao vừa sức để em bứt phá lên mức cao hơn.';
        } else if (slope < 0.6) {
            slopeStatusText = '🟢 Đang có đà tiến bộ tốt';
            slopeColor = '#16a34a';
            slopeBg = '#f0fdf4';
            slopeBorder = '#86efac';
            slopeDescText = 'Điểm số có sự vươn lên rõ qua từng bài kiểm tra. Ý thức học tập rất tích cực, thầy cô nên biểu dương trước lớp.';
        } else {
            slopeStatusText = '🟢 Tăng tốc bứt phá xuất sắc';
            slopeColor = '#16a34a';
            slopeBg = '#f0fdf4';
            slopeBorder = '#86efac';
            slopeDescText = 'Điểm số bứt phá mạnh mẽ, em nắm chắc kiến thức và tự tin giải quyết tốt các bài kiểm tra có độ khó cao.';
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
            stabStatusText = '⏳ Đang theo dõi phong độ';
            stabDescText = 'Cần thêm bài kiểm tra để đánh giá độ ổn định phong độ.';
            stabColor = '#64748b';
            stabBg = '#f8fafc';
            stabBorder = '#e2e8f0';
        } else if (stdDev >= 1.5) {
            stabStatusText = '🔴 Rất thất thường (Chênh lệch lớn)';
            stabColor = '#dc2626';
            stabBg = '#fef2f2';
            stabBorder = '#fca5a5';
            stabDescText = 'Khoảng cách giữa bài cao nhất và bài thấp nhất chênh lệch nhiều. Tâm lý làm bài thi chưa vững, dễ chủ quan hoặc lúng túng khi gặp câu hỏi mới.';
        } else if (stdDev >= 0.8) {
            stabStatusText = '🟠 Dao động (Chưa thật vững)';
            stabColor = '#ea580c';
            stabBg = '#fff7ed';
            stabBorder = '#fed7aa';
            stabDescText = 'Điểm số có sự trồi sụt giữa các bài kiểm tra. Cần rèn luyện tính cẩn thận và thói quen soát lại bài trước khi nộp.';
        } else {
            stabStatusText = '🟢 Rất đều tay & Vững vàng';
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
                    scoreText = '✓ ĐÃ ĐẠT';
                    subText = 'Chỉ cần thi ≥ 0đ';
                } else if (t.status === 'impossible_exceeds_10' || t.status === 'impossible_ck_only') {
                    scoreText = '> 10.0';
                    subText = '⚠️ Cần gỡ thêm TX';
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

        // 4. Khuyến nghị sư phạm hành động cụ thể cho Giáo viên
        if (modalMathTargetAlert) {
            let alertMsg = (row.dataset.mathAlert || '').trim();
            alertMsg = alertMsg.replace(/^[🎯⚠️🚨🌟\s:]+/, '');
            if (alertMsg.length > 0) {
                modalMathTargetAlert.innerHTML = `🎯 <b>Kế hoạch hành động cho Thầy/Cô:</b> ${alertMsg}`;
                modalMathTargetAlert.style.display = 'block';
                modalMathTargetAlert.style.background = '#fffbeb';
                modalMathTargetAlert.style.color = '#92400e';
                modalMathTargetAlert.style.border = '1px solid #fde047';
            } else {
                modalMathTargetAlert.style.display = 'none';
            }
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

        // Show modal
        modal.style.display = 'flex';
        setTimeout(() => {
            modal.classList.add('active');
        }, 10);
    }

    function closeStudentModal() {
        if (!modal) return;
        modal.classList.remove('active');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 200);
    }

    // Attach row click listeners (both main rows and sub-rows)
    document.querySelectorAll('tbody tr[data-level]').forEach(function (r) {
        r.addEventListener('click', function (e) {
            // Không mở modal nếu nhấn trực tiếp vào link sổ điểm hoặc nút toggle subrows
            if (e.target.closest('a') || e.target.closest('.btn-goto-gradebook') || e.target.closest('.btn-toggle-subrows')) {
                return;
            }
            openStudentModal(this);
        });

        const detailBtn = r.querySelector('.btn-open-detail');
        if (detailBtn) {
            detailBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
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
                        teacherNoteStatus.textContent = '✓ ' + (data.message || 'Đã lưu nhận xét thành công!');
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
    const rmGlass = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    document.querySelectorAll('.u001').forEach(function(uRoot) {
        const btn = uRoot.querySelector('.u001-btn');
        const mag = uRoot.querySelector('.u001-mag');
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
        }
    };

    window.toggleScoreDetailColumns = function() {
        const table = document.getElementById('radarTable');
        const btn = document.getElementById('btnToggleScoreCols');
        if (!table) return;
        const isHidden = table.classList.contains('scores-hidden');
        if (isHidden) {
            table.classList.remove('scores-hidden');
            if (btn) {
                btn.classList.add('active');
                btn.innerHTML = '⚡ Thu Gọn (Chỉ Xem ĐTB)';
            }
        } else {
            table.classList.add('scores-hidden');
            if (btn) {
                btn.classList.remove('active');
                btn.innerHTML = '👁️ Hiện Điểm TX, GK, CK';
            }
        }
    };
});
