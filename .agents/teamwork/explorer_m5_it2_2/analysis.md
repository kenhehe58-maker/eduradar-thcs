# Architectural Analysis & Remediation Design — Milestone 5 Iteration 2

**Author**: `explorer_m5_it2_2` (M5 Code Structure & Deduplication Explorer)  
**Date**: 2026-10-17  
**Scope**: `includes/radar_helper.php`, `index.php`, `assets/js/main.js`, `tests/verify_m5.php`, `tests/challenge_m5_radar.php`  
**Working Directory**: `D:\New folder\my-web-project\.agents\teamwork\explorer_m5_it2_2`  

---

## 1. Executive Summary

Milestone 5 Iteration 1 failed the Forensic Integrity Audit with an **INTEGRITY VIOLATION** verdict due to:
1. **Test Facades & Self-Certifying Work**: `tests/verify_m5.php` defined a local duplicate stub `testEvaluateRisk()` (lines 152–197) and asserted against its own stub across 14 tests (33.3% of the suite), while tests 3.1–3.3 asserted on hardcoded static arithmetic arrays rather than real system logic.
2. **Hidden Runtime Defect Leaking Live PHP Warnings**: The lack of genuine unit testing on `index.php`'s diagnostic logic masked a variable typo in `index.php:86` (`$isAvgWarning` instead of `$isAvgWarn`), emitting **12 PHP warnings** for Admin and **6 PHP warnings** for Teacher (`gv_toan`) into the live Apache HTML response under standard XAMPP configuration (`display_errors = On`).
3. **Duplicate DOM ID `radarEmptyRow`**: Both the backend initial empty row (`index.php:425`) and the dynamic client-side filter empty row (`index.php:503`) shared `id="radarEmptyRow"`. If the initial empty state rendered, `assets/js/main.js` incorrectly hid the initial empty message upon filter trigger (`visibleCount === 0 && rows.length > 0` evaluated to false).
4. **Adversarial Logic Failure on Single Exam Component**: Adversarial challenge `tests/challenge_m5_radar.php` (Vector 3.4) identified that a student failing the final exam (`ck = 4.9 < 5.0`) with a passing semester average was incorrectly categorized as 🟢 Green ("Đạt chuẩn, ổn định") instead of 🟡 Yellow (Cần chú ý), violating Requirement R4.

This report presents a clean, modular architecture:
- Extracting core diagnostic and statistical calculation into a dedicated, zero-dependency helper: `includes/radar_helper.php`.
- Deduplicating the DOM structure and making `assets/js/main.js` resilient to initial vs. filter empty states.
- Guaranteeing 0 PHP warnings/notices under `E_ALL` on live Apache.
- Eliminating all test facades, enabling direct unit and integration testing by `index.php`, `verify_m5.php`, `challenge_m5_radar.php`, and the future `tests/run_tests.php`.

---

## 2. Investigation Findings & Root Cause Analysis

### Finding 1: Test Facade and Missing Shared Helper
- **Location**: `tests/verify_m5.php:152-197`, `tests/verify_m5.php:204-293`, `tests/verify_m5.php:299-323`.
- **Root Cause**: `index.php` was written as a monolithic script containing session management, database queries, inline functions, and HTML rendering in a single file. Test suites (`verify_m5.php`, `challenge_m5_radar.php`) could not easily import `evaluateRiskZone()` without triggering database queries, session redirects, or HTML output.
- **Consequence**:
  - `verify_m5.php` resorted to copying the logic into a local stub `testEvaluateRisk()`.
  - `challenge_m5_radar.php` resorted to a hack involving `session_start()`, mocking `$_SESSION`, `ob_start()`, and `error_reporting(0)` before requiring `index.php`.
- **Remediation**: Isolate pure algorithmic logic into `includes/radar_helper.php`. Both `index.php` and all test suites will `require_once __DIR__ . '/includes/radar_helper.php'`, sharing 100% of the production implementation.

### Finding 2: Runtime Warning Leaking into HTML Response
- **Location**: `index.php:86`.
- **Root Cause**:
  ```php
  71: $isAvgWarn = ($avg !== null && $avg >= 3.5 && $avg < 5.0);
  ...
  86: if ($isAvgWarning || $isWarning) { // $isAvgWarning does not exist!
  ```
- **Live Empirical Evidence**:
  ```html
  <b>Warning</b>: Undefined variable $isAvgWarning in <b>D:\New folder\my-web-project\index.php</b> on line <b>86</b><br />
  ```
  Repeated 12 times on `http://localhost/my-web-project/index.php` for Admin, and 6 times for `gv_toan`.
- **Remediation**: Since line 73 already sets `$isWarning = true` when `$isAvgWarn` is true, the condition can be simplified cleanly to `if ($isWarning)`.

### Finding 3: Component Score Failure Slipped into Green Zone (V3.4)
- **Location**: `index.php:68-112`, `tests/challenge_m5_radar.php:308-319`.
- **Root Cause**: In `index.php`, the Yellow zone only checked for `3.5 <= avg < 5.0` and `gk - ck > 1.5`. A student with `avg = 5.5, gk = 5.0, ck = 4.9` passed `avg >= 5.0` and had a drop of only 0.1, so neither condition fired. The student fell through into 🟢 Green ("Đạt chuẩn, ổn định (5.5)"), despite failing the semester final exam (`ck = 4.9 < 5.0`).
- **Specification**: Requirement R4 explicitly mandates warning flags for: "average < 5.0, consecutive drop across regular/periodic tests, or component score < 5.0". In Vietnamese THCS grading (Circular 22), the semester final exam (`ĐĐGck`, weight 3) is critical. Failing CK (`ck < 5.0`) must place the student in 🟡 Yellow ("Cần chú ý").
- **Remediation**: Add `$isCkUnder5 = ($ck !== null && $ck < 5.0);` in the Yellow zone. (Note: `ck < 3.0` and `gk < 5.0 && ck < 5.0` are already caught earlier in the Critical 🔴 Red zone).

### Finding 4: DOM ID Duplication and Empty State Confusion
- **Location**: `index.php:425` and `index.php:503`.
- **Root Cause**:
  ```html
  424: <?php if (empty($students)): ?>
  425:     <tr id="radarEmptyRow">...Chưa có học sinh nào trong phạm vi được phân công...</tr>
  426: <?php else: ?>
  ...
  503:     <tr id="radarEmptyRow" style="display: none;">...Không tìm thấy học sinh nào phù hợp...</tr>
  508: <?php endif; ?>
  ```
- **Consequence**:
  - Two distinct elements in template code share `id="radarEmptyRow"`.
  - In `assets/js/main.js`:
    ```javascript
    const emptyRow = document.getElementById('radarEmptyRow');
    ...
    if (emptyRow) {
        emptyRow.style.display = (visibleCount === 0 && rows.length > 0) ? '' : 'none';
    }
    ```
    When `$students` is empty (e.g., initial state), `rows.length` is 0. If `applyFilter()` runs, `(visibleCount === 0 && rows.length > 0)` is `false`, so `emptyRow.style.display` becomes `'none'`, hiding the initial empty message!
- **Remediation**:
  - Differentiate IDs: Line 425 becomes `id="radarInitialEmptyRow"` with class `radar-initial-empty-row`. Line 503 remains `id="radarEmptyRow"` with class `radar-filter-empty-row`.
  - In `assets/js/main.js`: `const emptyRow = document.getElementById('radarEmptyRow') || document.querySelector('.radar-filter-empty-row');`. When `$students` is empty, `emptyRow` is `null` so `applyFilter()` never hides the initial empty row.

---

## 3. Architecture Specification: `includes/radar_helper.php`

### 3.1 Design Principles
1. **Zero External Dependencies**: Pure PHP 8.0, strict types enabled (`declare(strict_types=1);`).
2. **Zero Code Duplication**: Extracted functions serve `index.php`, `verify_m5.php`, `challenge_m5_radar.php`, and `run_tests.php`.
3. **Zero Banned Keywords**: 0 occurrences of `zalo`, `actionModal`, `copy-btn`, `buildFeedback` to pass `tests/challenge_m5_zalo_ui.php`.
4. **Pure & Predictable**: Helper functions are pure algorithms with deterministic input-to-output mapping and 0 side effects.

### 3.2 Full Source Code Design

```php
<?php
/**
 * =============================================================================
 * Hệ Thống Quản Lý Điểm & Cảnh Báo Sớm THCS (EduRadar)
 * Module: Động Cơ Chẩn Đoán Radar & Thống Kê Rủi Ro Học Tập (Thông tư 22/2021/TT-BGDĐT)
 * File: includes/radar_helper.php
 * =============================================================================
 */

declare(strict_types=1);

/**
 * Chẩn đoán mức độ rủi ro học tập của học sinh theo chuẩn Thông tư 22/2021/TT-BGDĐT
 *
 * Phân cấp ưu tiên:
 * 1. 🔴 Vùng Nguy cấp (Critical):
 *    - ĐTBmhk < 3.5
 *    - Điểm kiểm tra cuối kỳ (ĐĐGck) < 3.0
 *    - Hỏng cả giữa kỳ và cuối kỳ (ĐĐGgk < 5.0 && ĐĐGck < 5.0)
 * 2. 🟡 Vùng Cần chú ý (Warning / At-risk):
 *    - ĐTBmhk dưới chuẩn (3.5 <= avg < 5.0)
 *    - Thi cuối kỳ không đạt (3.0 <= ck < 5.0)
 *    - Sa sút cuối kỳ nghiêm trọng (gk - ck > 1.5)
 * 3. 🟢 Vùng Ổn định (Stable):
 *    - avg >= 8.0: Giỏi / Xuất sắc
 *    - avg >= 6.5: Khá
 *    - avg >= 5.0: Đạt chuẩn
 *    - avg === null: Chưa đủ dữ liệu điểm số
 *
 * @param float|null $avg Điểm trung bình môn học kỳ (ĐTBmhk)
 * @param float|null $gk  Điểm kiểm tra giữa kỳ (ĐĐGgk)
 * @param float|null $ck  Điểm kiểm tra cuối kỳ (ĐĐGck)
 * @param array      $txScores Mảng điểm thường xuyên (tx1..tx4)
 * @return array{level: string, label: string, reasons: array<string>, diagnostic: string}
 */
function evaluateRiskZone(?float $avg, ?float $gk, ?float $ck, array $txScores = []): array
{
    $reasons = [];

    // 1. VÙNG 🔴 NGUY CẤP (CRITICAL ZONE)
    $isAvgCrit = ($avg !== null && $avg < 3.5);
    if ($isAvgCrit) {
        $reasons[] = 'Điểm TB dưới 3.5 (' . number_format($avg, 1) . ')';
    }

    $isCkCrit = ($ck !== null && $ck < 3.0);
    if ($isCkCrit) {
        $reasons[] = 'Thi cuối kỳ dưới 3.0 (' . number_format($ck, 1) . ')';
    }

    $isBothFailing = ($gk !== null && $ck !== null && $gk < 5.0 && $ck < 5.0);
    if ($isBothFailing) {
        $reasons[] = 'Hỏng cả GK (' . number_format($gk, 1) . ') và CK (' . number_format($ck, 1) . ')';
    }

    if ($isAvgCrit || $isCkCrit || $isBothFailing) {
        return [
            'level' => 'red',
            'label' => 'Nguy cấp',
            'reasons' => $reasons,
            'diagnostic' => implode(' · ', $reasons),
        ];
    }

    // 2. VÙNG 🟡 CẦN CHÚ Ý (WARNING / AT RISK ZONE)
    $isWarning = false;

    // Điểm TB dưới chuẩn (3.5 <= avg < 5.0)
    $isAvgWarn = ($avg !== null && $avg >= 3.5 && $avg < 5.0);
    if ($isAvgWarn) {
        $isWarning = true;
        $reasons[] = 'Điểm TB dưới chuẩn (' . number_format($avg, 1) . ')';
    }

    // Thi cuối kỳ dưới 5.0 (3.0 <= ck < 5.0 vì ck < 3.0 đã xử lý ở vùng đỏ)
    $isCkUnder5 = ($ck !== null && $ck < 5.0);
    if ($isCkUnder5) {
        $isWarning = true;
        $reasons[] = 'Thi cuối kỳ dưới 5.0 (' . number_format($ck, 1) . ')';
    }

    // Sa sút cuối kỳ: ck < gk - 1.5 <=> (gk - ck) > 1.5
    if ($gk !== null && $ck !== null) {
        $drop = round((float)$gk - (float)$ck, 1);
        if ($drop > 1.5) {
            $isWarning = true;
            $reasons[] = 'Sa sút cuối kỳ (giảm ' . number_format($drop, 1) . ' đ so với GK)';
        }
    }

    if ($isWarning) {
        return [
            'level' => 'yellow',
            'label' => 'Cần chú ý',
            'reasons' => $reasons,
            'diagnostic' => implode(' · ', $reasons),
        ];
    }

    // 3. VÙNG 🟢 ỔN ĐỊNH (STABLE ZONE)
    if ($avg === null) {
        $reasons[] = 'Chưa đủ dữ liệu điểm số';
    } elseif ($avg >= 8.0) {
        $reasons[] = 'Học lực Giỏi, kết quả xuất sắc (' . number_format($avg, 1) . ')';
    } elseif ($avg >= 6.5) {
        $reasons[] = 'Học lực Khá, duy trì tốt (' . number_format($avg, 1) . ')';
    } else {
        $reasons[] = 'Đạt chuẩn, ổn định (' . number_format($avg, 1) . ')';
    }

    return [
        'level' => 'green',
        'label' => 'Ổn định',
        'reasons' => $reasons,
        'diagnostic' => implode(' · ', $reasons),
    ];
}

/**
 * Tính toán bộ đếm số lượng và tỷ lệ phần trăm các vùng rủi ro (an toàn chia cho 0)
 *
 * @param array $students Danh sách học sinh đã được gắn 'level' (red, yellow, green)
 * @return array{total: int, red: int, yellow: int, green: int, red_pct: float, yellow_pct: float, green_pct: float}
 */
function calculateRadarStats(array $students): array
{
    $stats = [
        'total'      => 0,
        'red'        => 0,
        'yellow'     => 0,
        'green'      => 0,
        'red_pct'    => 0.0,
        'yellow_pct' => 0.0,
        'green_pct'  => 0.0,
    ];

    foreach ($students as $s) {
        $stats['total']++;
        $level = $s['level'] ?? 'green';
        if (isset($stats[$level])) {
            $stats[$level]++;
        }
    }

    if ($stats['total'] > 0) {
        $stats['red_pct']    = round(($stats['red'] / $stats['total']) * 100, 1);
        $stats['yellow_pct'] = round(($stats['yellow'] / $stats['total']) * 100, 1);
        $stats['green_pct']  = round(($stats['green'] / $stats['total']) * 100, 1);
    }

    return $stats;
}

/**
 * Sắp xếp danh sách học sinh theo mức độ ưu tiên rủi ro: Đỏ -> Vàng -> Xanh, sau đó theo Họ tên
 *
 * @param array $students Danh sách học sinh tham chiếu
 * @return void
 */
function sortStudentsByRisk(array &$students): void
{
    $levelPriority = ['red' => 0, 'yellow' => 1, 'green' => 2];
    usort($students, function ($a, $b) use ($levelPriority) {
        $pA = $levelPriority[$a['level'] ?? 'green'] ?? 3;
        $pB = $levelPriority[$b['level'] ?? 'green'] ?? 3;
        if ($pA !== $pB) {
            return $pA <=> $pB;
        }
        return strcasecmp($a['full_name'] ?? '', $b['full_name'] ?? '');
    });
}

// =============================================================================
// Backward compatibility aliases
// =============================================================================
if (!function_exists('evaluateEarlyWarning')) {
    function evaluateEarlyWarning(?float $avg, ?float $gk, ?float $ck, array $txScores = []): array
    {
        return evaluateRiskZone($avg, $gk, $ck, $txScores);
    }
}

if (!function_exists('evaluateRisk')) {
    function evaluateRisk(?float $avg, array $scores = [], ?float $gk = null, ?float $ck = null): array
    {
        return evaluateRiskZone($avg, $gk, $ck, $scores);
    }
}
```

---

## 4. Integration Blueprint Across Target Files

### 4.1 Changes to `index.php`

1. **Include Helper**:
   ```php
   // Replace lines 19-22 with:
   require_once __DIR__ . '/includes/config.php';
   require_once __DIR__ . '/includes/db.php';
   require_once __DIR__ . '/includes/auth.php';
   require_once __DIR__ . '/includes/radar_helper.php';
   ```
2. **Remove Inline Functions**:
   Delete lines 30–124 (inline definitions of `evaluateRiskZone()`, `evaluateEarlyWarning()`, `evaluateRisk()`).
3. **Refactor Risk Evaluation & Stats Calculation**:
   Replace lines 246–294 with clean calls:
   ```php
   $students = [];
   foreach ($rows as $r) {
       $avg = ($r['avg_score'] !== null && $r['avg_score'] !== '') ? (float)$r['avg_score'] : null;
       $gk  = ($r['gk'] !== null && $r['gk'] !== '') ? (float)$r['gk'] : null;
       $ck  = ($r['ck'] !== null && $r['ck'] !== '') ? (float)$r['ck'] : null;

       $txList = [];
       foreach (['tx1', 'tx2', 'tx3', 'tx4'] as $col) {
           if ($r[$col] !== null && $r[$col] !== '') {
               $txList[] = (float)$r[$col];
           }
       }

       $evaluation = evaluateRiskZone($avg, $gk, $ck, $txList);
       $students[] = array_merge($r, $evaluation, ['tx_list' => $txList]);
   }

   $stats = calculateRadarStats($students);
   sortStudentsByRisk($students);
   ```
4. **Deduplicate DOM ID**:
   - Change line 425:
     ```html
     <!-- Before: <tr id="radarEmptyRow"> -->
     <tr id="radarInitialEmptyRow" class="radar-initial-empty-row">
         <td colspan="12" style="text-align: center; padding: 40px; color: var(--text-muted);">
             Chưa có học sinh nào trong phạm vi được phân công. Hãy vào sổ điểm để cập nhật.
         </td>
     </tr>
     ```
   - Line 503 remains:
     ```html
     <tr id="radarEmptyRow" class="radar-filter-empty-row" style="display: none;">
         <td colspan="12" style="text-align: center; padding: 36px; color: var(--text-muted);">
             Không tìm thấy học sinh nào phù hợp với bộ lọc hoặc từ khóa tìm kiếm.
         </td>
     </tr>
     ```

### 4.2 Changes to `assets/js/main.js`

Make `main.js` defensive against the empty state:
```javascript
document.addEventListener('DOMContentLoaded', function () {
    const rows = document.querySelectorAll('tbody tr[data-level]');
    const chips = document.querySelectorAll('.chip[data-filter]');
    const search = document.getElementById('search');
    const emptyRow = document.getElementById('radarEmptyRow') || document.querySelector('.radar-filter-empty-row');
    let currentFilter = 'all';

    function applyFilter() {
        const q = (search?.value || '').toLowerCase().trim();
        let visibleCount = 0;

        rows.forEach(function (r) {
            const level = r.dataset.level || '';
            const name = (r.dataset.name || '').toLowerCase();
            const searchAttr = (r.dataset.search || '').toLowerCase();
            const okLevel = (currentFilter === 'all') || (level === currentFilter);
            const okName = !q || name.includes(q) || searchAttr.includes(q);

            if (okLevel && okName) {
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
        c.addEventListener('click', function () {
            chips.forEach(function (x) { x.classList.remove('active'); });
            this.classList.add('active');
            currentFilter = this.dataset.filter || 'all';
            applyFilter();
        });
    });

    if (search) {
        search.addEventListener('input', applyFilter);
    }
});
```

### 4.3 Changes to `tests/verify_m5.php`

1. **Include Real Helper**:
   ```php
   require_once dirname(__DIR__) . '/includes/config.php';
   require_once dirname(__DIR__) . '/includes/db.php';
   require_once dirname(__DIR__) . '/includes/radar_helper.php';
   ```
2. **Remove Local Stub**:
   Completely delete lines 152–197 (`testEvaluateRisk()`).
3. **Update Group 1 & Group 2 Tests**:
   Replace all calls to `testEvaluateRisk(...)` with `evaluateRiskZone(...)`.
4. **Update Group 3 Tests (Stats & Invariants)**:
   Test `calculateRadarStats()` directly with genuine test datasets:
   ```php
   assertTest("3.1 Stat invariant holds: calculateRadarStats() total === red + yellow + green", function () {
       $sample = [
           ['level' => 'red'],
           ['level' => 'red'],
           ['level' => 'yellow'],
           ['level' => 'yellow'],
           ['level' => 'yellow'],
           ['level' => 'green'],
       ];
       $stats = calculateRadarStats($sample);
       if ($stats['total'] !== 6 || $stats['total'] !== ($stats['red'] + $stats['yellow'] + $stats['green'])) {
           throw new Exception("Counts do not balance: " . json_encode($stats));
       }
       return true;
   });

   assertTest("3.2 Percentage calculation: percentages match expected ratio and sum to ~100.0%", function () {
       $sample = array_merge(
           array_fill(0, 5, ['level' => 'red']),
           array_fill(0, 8, ['level' => 'yellow']),
           array_fill(0, 12, ['level' => 'green'])
       );
       $stats = calculateRadarStats($sample);
       if ($stats['total'] !== 25 || $stats['red'] !== 5 || $stats['yellow'] !== 8 || $stats['green'] !== 12) {
           throw new Exception("Counts mismatch");
       }
       $sum = $stats['red_pct'] + $stats['yellow_pct'] + $stats['green_pct'];
       if (abs($sum - 100.0) > 0.3) {
           throw new Exception("Percentages sum to $sum != 100%");
       }
       return true;
   });

   assertTest("3.3 Zero-division defense: 0 students yields 0% without error", function () {
       $stats = calculateRadarStats([]);
       if ($stats['total'] !== 0 || $stats['red_pct'] !== 0.0 || $stats['yellow_pct'] !== 0.0 || $stats['green_pct'] !== 0.0) {
           throw new Exception("Expected 0.0% on empty set");
       }
       return true;
   });
   ```
5. **Add Live Output Warning Assertion**:
   Add test 6.6 in Group 6 to guarantee 0 live warnings on Apache:
   ```php
   assertTest("6.6 Live Apache response contains 0 PHP notices, warnings, or deprecations", function () use ($client) {
       $client->clearCookies();
       $client->login('admin', '123456');
       $adminRes = $client->get('index.php');
       if (stripos($adminRes['body'], 'Warning:') !== false ||
           stripos($adminRes['body'], 'Notice:') !== false ||
           stripos($adminRes['body'], 'Undefined variable') !== false) {
           throw new Exception("Live Apache Admin index.php leaked PHP warnings/notices into HTML!");
       }

       $client->clearCookies();
       $client->login('gv_toan', '123456');
       $teacherRes = $client->get('index.php');
       if (stripos($teacherRes['body'], 'Warning:') !== false ||
           stripos($teacherRes['body'], 'Notice:') !== false ||
           stripos($teacherRes['body'], 'Undefined variable') !== false) {
           throw new Exception("Live Apache Teacher index.php leaked PHP warnings/notices into HTML!");
       }
       return true;
   });
   ```

### 4.4 Changes to `tests/challenge_m5_radar.php`

Replace lines 155–167:
```php
// Before:
// session_start();
// $_SESSION['user_id'] = 1; ...
// ob_start();
// $oldErrorReporting = error_reporting(0);
// require_once dirname(__DIR__) . '/index.php';
// error_reporting($oldErrorReporting);
// ob_end_clean();

// After:
require_once dirname(__DIR__) . '/includes/radar_helper.php';
```
This removes the fragile session/buffer mock, directly imports `includes/radar_helper.php`, and runs all 42 vectors cleanly.

---

## 5. Architectural Verification Matrix

| Verification Target | Requirement | Expected Result |
|---|---|---|
| `includes/radar_helper.php` creation | Shared helper with `evaluateRiskZone()`, `calculateRadarStats()`, `sortStudentsByRisk()` | File exists, strict types, 0 Zalo keywords |
| `index.php` clean execution | Zero PHP warnings or notices under `E_ALL` | HTTP 200, 0 `Warning:` or `Notice:` in DOM |
| DOM ID Deduplication | Line 425 vs 503 distinct IDs | `radarInitialEmptyRow` vs `radarEmptyRow` |
| Client-side Filter Resilience | Empty table or filtered table does not throw | 40/40 PASS on `tests/challenge_m5_js_dom.js` |
| Real Unit Testing | `tests/verify_m5.php` zero stubs | 43/43 PASS on `tests/verify_m5.php` |
| Adversarial Challenge | `tests/challenge_m5_radar.php` | 42/42 PASS on `tests/challenge_m5_radar.php` |
| Zalo & Dead Code Elimination | `tests/challenge_m5_zalo_ui.php` | 24/24 PASS on `tests/challenge_m5_zalo_ui.php` |
| Prior Milestones Regressions | M1–M4 verification suites | 95/95 PASS across M1, M2, M3, M4 |
