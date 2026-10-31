<?php
/**
 * =============================================================================
 * Hệ Thống Ra-đa Cảnh Báo Sớm Học Lực THCS (EduRadar)
 * Module: Thư Viện SVG Icon Chuẩn Hóa (Monochrome Vector Icons)
 * Thiết kế theo chuẩn Dribbble & GitHub Design, thay thế toàn bộ emoji tùy tiện.
 * =============================================================================
 */

declare(strict_types=1);

if (!function_exists('get_svg_icon')) {
    /**
     * Render SVG icon chuẩn nét, kế thừa màu currentColor hoặc custom style
     *
     * @param string $name  Tên icon
     * @param int    $size  Kích thước (px)
     * @param string $class CSS class bổ sung
     * @param string $style Inline CSS style
     * @return string
     */
    function get_svg_icon(string $name, int $size = 18, string $class = '', string $style = ''): string
    {
        $classAttr = $class !== '' ? ' class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"' : '';
        $styleAttr = $style !== '' ? ' style="' . htmlspecialchars($style, ENT_QUOTES, 'UTF-8') . '"' : '';

        $icons = [
            'radar' => '<circle cx="12" cy="12" r="10"/><path d="M12 2a10 10 0 0 1 10 10"/><path d="m12 12 7-7"/><circle cx="12" cy="12" r="2"/>',
            'trend-up' => '<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>',
            'trend-down' => '<polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/><polyline points="17 18 23 18 23 12"/>',
            'trend-steady' => '<line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>',
            'activity' => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
            'bar-chart' => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
            'alert-triangle' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
            'alert-circle' => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
            'check-circle' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
            'clock' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
            'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            'user' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
            'book-open' => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',
            'file-text' => '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/>',
            'printer' => '<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
            'shield-check' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/>',
            'qr-code' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
            'settings' => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
            'log-out' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
            'eye' => '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
            'trash' => '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>',
            'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
            'search' => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
            'filter' => '<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>',
            'award' => '<circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/>',
            'presentation' => '<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>',
            'edit' => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>',
            'chevron-right' => '<polyline points="9 18 15 12 9 6"/>',
            'chevron-down' => '<polyline points="6 9 12 15 18 9"/>',
            'git-commit' => '<circle cx="12" cy="12" r="4"/><line x1="1.05" y1="12" x2="8" y2="12"/><line x1="16" y1="12" x2="22.95" y2="12"/>',
            'database' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>',
            'lock' => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
            'unlock' => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/>',
            'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
            'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
            'save' => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>',
            'bulb' => '<line x1="9" y1="18" x2="15" y2="18"/><line x1="10" y1="22" x2="14" y2="22"/><path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 18 8 6 6 0 0 0 6 8c0 1 .23 2.23 1.5 3.5A4.61 4.61 0 0 1 8.91 14"/>',
            'pin' => '<line x1="12" y1="17" x2="12" y2="22"/><path d="M5 17h14v-1.76a2 2 0 0 0-1.11-1.79l-1.78-.89A2 2 0 0 1 15 10.77V5a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v5.77a2 2 0 0 1-1.11 1.79l-1.78.89A2 2 0 0 0 5 15.24Z"/>',
            'target' => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
            'check' => '<polyline points="20 6 9 17 4 12"/>',
            'close' => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
            'sun' => '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>',
            'moon' => '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
        ];

        $inner = $icons[$name] ?? '<circle cx="12" cy="12" r="9"/>';

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"%s%s aria-hidden="true">%s</svg>',
            $size,
            $size,
            $classAttr,
            $styleAttr,
            $inner
        );
    }
}

if (!function_exists('render_circular_gauge')) {
    /**
     * Render SVG Donut Radial Progress Gauge (Ramotion Upstream SaaS Style)
     *
     * @param float  $pct        Tỷ lệ phần trăm (0 - 100)
     * @param string $colorClass Tùy chọn class màu: 'green', 'yellow', 'red', 'blue', 'gray'
     * @return string
     */
    function render_circular_gauge(float $pct, string $colorClass = ''): string
    {
        $pctClamped = max(0.0, min(100.0, round($pct, 1)));
        $cls = $colorClass !== '' ? ' ' . htmlspecialchars($colorClass, ENT_QUOTES, 'UTF-8') : '';
        return '<div class="radial-ring-wrap' . $cls . '" title="' . $pctClamped . '%" aria-label="' . $pctClamped . '%">
            <svg class="radial-ring-svg" viewBox="0 0 36 36" aria-hidden="true">
                <path class="radial-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                <path class="radial-fill" stroke-dasharray="' . $pctClamped . ', 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
            </svg>
            <span class="radial-ring-text">' . $pctClamped . '%</span>
        </div>';
    }
}

if (!function_exists('get_simple_icon')) {
    /**
     * Tích hợp kho icon toàn cầu Simple Icons (d:\simple-icons)
     *
     * @param string $slug  Tên icon (ví dụ: 'github', 'mysql', 'dribbble', 'googlesheets')
     * @param int    $size  Kích thước (px)
     * @param string $class CSS class bổ sung
     * @param string $style Inline style (ví dụ: 'fill: #4f46e5;')
     * @return string
     */
    function get_simple_icon(string $slug, int $size = 18, string $class = '', string $style = ''): string
    {
        static $iconCache = [];
        $slug = strtolower(trim($slug));
        
        $classAttr = $class !== '' ? ' class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"' : '';
        $styleAttr = $style !== '' ? ' style="' . htmlspecialchars($style, ENT_QUOTES, 'UTF-8') . '"' : '';

        if (!isset($iconCache[$slug])) {
            $path = 'd:/simple-icons/icons/' . $slug . '.svg';
            if (file_exists($path)) {
                $raw = (string)file_get_contents($path);
                if (preg_match('/<path\s+[^>]*d="([^"]+)"/i', $raw, $m)) {
                    $iconCache[$slug] = $m[1];
                } else {
                    $iconCache[$slug] = false;
                }
            } else {
                $iconCache[$slug] = false;
            }
        }

        if ($iconCache[$slug] !== false) {
            return sprintf(
                '<svg role="img" xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 24 24" fill="currentColor"%s%s aria-hidden="true"><path d="%s"/></svg>',
                $size,
                $size,
                $classAttr,
                $styleAttr,
                $iconCache[$slug]
            );
        }

        // Fallback sang get_svg_icon thông thường
        return get_svg_icon($slug, $size, $class, $style);
    }
}

if (!function_exists('render_segmented_donut_ring')) {
    /**
     * Render SVG Segmented Donut Ring (Expenses / Dribbble SaaS Style)
     *
     * @param array  $slices       Mảng các phân đoạn: [['count' => 7, 'color' => '#10b981', 'label' => '...', 'short' => '...']]
     * @param string $centerNumber Con số lớn ở giữa
     * @param string $centerLabel  Nhãn phụ ở giữa (hiển thị phía trên số)
     * @param string $title        Tiêu đề thẻ
     * @param string $sheetType    Loại modal ('trend' hoặc 'tt22')
     * @return string
     */
    function render_segmented_donut_ring(array $slices, string $centerNumber, string $centerLabel, string $title, string $sheetType): string
    {
        $r = 42.0;
        $cx = 60.0;
        $cy = 60.0;
        $strokeWidth = 14.0;
        $circumference = 2 * M_PI * $r; // ~263.89

        $total = 0;
        $activeSlices = [];
        foreach ($slices as $s) {
            $cnt = max(0, (int)($s['count'] ?? 0));
            $total += $cnt;
            if ($cnt > 0) {
                $activeSlices[] = $s;
            }
        }

        $activeCount = count($activeSlices);
        $pathsSvg = '';

        if ($total === 0 || $activeCount === 0) {
            $pathsSvg .= sprintf(
                '<circle cx="%.1f" cy="%.1f" r="%.1f" fill="none" class="ring-track" stroke-width="%.1f" stroke-dasharray="4, 4" />',
                $cx, $cy, $r, $strokeWidth
            );
        } else {
            // Track nền
            $pathsSvg .= sprintf(
                '<circle cx="%.1f" cy="%.1f" r="%.1f" fill="none" class="ring-track" stroke-width="%.1f" />',
                $cx, $cy, $r, $strokeWidth
            );

            $gap = $activeCount > 1 ? 6.5 : 0.0;
            $currentOffset = 0.0;

            foreach ($activeSlices as $slice) {
                $cnt = (int)$slice['count'];
                $arc = ($cnt / $total) * $circumference;
                $color = htmlspecialchars($slice['color'] ?? '#3b82f6', ENT_QUOTES, 'UTF-8');
                $dashLength = max(2.5, $arc - $gap);
                $dashSpace = $circumference - $dashLength;
                $cap = ($arc > 12.0) ? 'round' : 'butt';

                $pathsSvg .= sprintf(
                    '<circle cx="%.1f" cy="%.1f" r="%.1f" fill="none" stroke="%s" stroke-width="%.1f" stroke-linecap="%s" stroke-dasharray="%.2f %.2f" stroke-dashoffset="%.2f" transform="rotate(-90 %.1f %.1f)" />',
                    $cx, $cy, $r, $color, $strokeWidth, $cap, $dashLength, $dashSpace, -$currentOffset, $cx, $cy
                );

                $currentOffset += $arc;
            }
        }

        $safeNum = htmlspecialchars($centerNumber, ENT_QUOTES, 'UTF-8');
        $safeLabel = htmlspecialchars(ltrim($centerLabel, '#'), ENT_QUOTES, 'UTF-8');
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeSheet = htmlspecialchars($sheetType, ENT_QUOTES, 'UTF-8');

        // Render modern legend chips (Expenses Style - Pill Badges with Color Squares)
        $legendHtml = '<div class="ring-chip-legend">';
        foreach ($slices as $sl) {
            $slColor = htmlspecialchars($sl['color'] ?? '#3b82f6', ENT_QUOTES, 'UTF-8');
            $slCnt = (int)($sl['count'] ?? 0);
            $slLbl = htmlspecialchars($sl['label'] ?? '', ENT_QUOTES, 'UTF-8');
            $slShort = htmlspecialchars($sl['short'] ?? $slLbl, ENT_QUOTES, 'UTF-8');
            $legendHtml .= sprintf(
                '<span class="ring-legend-chip" title="%s: %d"><i class="ring-legend-sq" style="background:%s;"></i><span class="chip-label">%s</span><b class="chip-count">%d</b></span>',
                $slLbl, $slCnt, $slColor, $slShort, $slCnt
            );
        }
        $legendHtml .= '</div>';

        return '<div class="segmented-ring-card" onclick="openRingHelp(\'' . $safeSheet . '\')" role="button" tabindex="0" aria-label="Bấm để xem chi tiết ' . $safeTitle . '">
            <div class="ring-card-header">
                <span class="ring-card-title">' . $safeTitle . '</span>
                <span class="ring-info-badge" title="Bấm xem giải thích chi tiết" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M12 16v-4"></path>
                        <path d="M12 8h.01"></path>
                    </svg>
                </span>
            </div>
            <div class="ring-svg-wrapper">
                <svg class="segmented-ring-svg" viewBox="0 0 120 120" width="128" height="128" aria-hidden="true">
                    ' . $pathsSvg . '
                    <text x="60" y="49" text-anchor="middle" class="ring-center-label">' . $safeLabel . '</text>
                    <text x="60" y="71" text-anchor="middle" class="ring-center-num">' . $safeNum . '</text>
                </svg>
            </div>
            ' . $legendHtml . '
        </div>';
    }
}
