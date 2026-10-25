# Milestone 5 Iteration 2: Code Modification Summary

**Worker**: `worker_m5_it2`  
**Date**: 2026-10-17  
**Scope**: `includes/radar_helper.php`, `index.php`, `assets/js/main.js`, `tests/verify_m5.php`

---

## 1. Overview of Modifications

Milestone 5 Iteration 2 addresses all defects cited in the Iteration 1 Forensic Audit (`auditor_m5_1`):
1. **Eliminated Test Facades**: Extracted pure diagnostic and stats logic into `includes/radar_helper.php`. `tests/verify_m5.php` now tests live production code with 0 stubs or dummy functions.
2. **Fixed Runtime Typo**: Replaced undefined variable `$isAvgWarning` with `$isWarning` accumulator in `includes/radar_helper.php` and `index.php`, eliminating all 12 PHP warnings on Admin and 6 PHP warnings on Teacher.
3. **Fixed Single Exam Failure Boundary (V3.4)**: Single exam failure (`ck < 5.0` or `gk < 5.0`) when `avg >= 5.0` now correctly triggers 🟡 Cần chú ý (Yellow zone) with `"Thi cuối kỳ dưới trung bình"` or `"Thi giữa kỳ dưới trung bình"` per Circular 22 and Requirement R4.
4. **Resolved DOM ID Duplication**: Changed initial empty row in `index.php` line 425 from `id="radarEmptyRow"` to `id="radarInitialEmptyRow"` (`class="radar-initial-empty-row"`), leaving line 503 as the unique owner of `id="radarEmptyRow"` (`class="radar-filter-empty-row"`).
5. **Defensive Client-Side Handling**: Updated `assets/js/main.js` with defensive fallback for `emptyRow` (`getElementById('radarEmptyRow') || (typeof document.querySelector === 'function' ? document.querySelector('.radar-filter-empty-row') : null)`), achieving 40/40 PASS on adversarial DOM stress testing.
6. **Live Database Integration in Test Suite**: Replaced hardcoded math arrays in `tests/verify_m5.php` with live MySQL `edu_radar` evaluations and mathematical invariant assertions. Added live Apache warning/error assertions across Admin, `gv_toan`, and `gv_van`.

---

## 2. File-by-File Changes

### 2.1 `includes/radar_helper.php` (NEW)
- **Purpose**: Centralized, zero-dependency helper for early-warning radar diagnostics and statistics.
- **Functions**:
  - `evaluateRiskZone(?float $avg, ?float $gk, ?float $ck, array $txScores = []): array`
    - Zone 1 (🔴 Nguy cấp): `avg < 3.5` OR `ck < 3.0` OR `(gk < 5.0 && ck < 5.0)`.
    - Zone 2 (🟡 Cần chú ý): `3.5 <= avg < 5.0` OR `ck < 5.0` OR `gk < 5.0` OR `(gk - ck) > 1.5`. Accumulates into `$isWarning` without undefined variables.
    - Zone 3 (🟢 Ổn định): `avg === null` (chưa đủ dữ liệu), `avg >= 8.0` (Giỏi), `avg >= 6.5` (Khá), `avg >= 5.0` (Đạt chuẩn).
  - `calculateRadarStats(array $students): array`
    - Computes `total`, `red`, `yellow`, `green` counts and `red_pct`, `yellow_pct`, `green_pct`.
    - Handles empty array without division by zero.
  - `sortStudentsByRisk(array &$students): array`
    - Sorts students by priority (Red -> Yellow -> Green) then alphabetical by full name.
  - Backward compatibility aliases: `evaluateEarlyWarning()` and `evaluateRisk()`.

### 2.2 `index.php` (MODIFIED)
- Added `require_once __DIR__ . '/includes/radar_helper.php';`.
- Removed inline functions `evaluateRiskZone()`, `evaluateEarlyWarning()`, `evaluateRisk()`.
- Replaced inline stats calculation loop and usort with calls to `calculateRadarStats($students)` and `sortStudentsByRisk($students)`.
- Changed initial empty state row `id="radarEmptyRow"` to `id="radarInitialEmptyRow"` with class `radar-initial-empty-row`.
- Added class `radar-filter-empty-row` to filter empty state row `id="radarEmptyRow"`.

### 2.3 `assets/js/main.js` (MODIFIED)
- Updated `emptyRow` initialization:
  `const emptyRow = document.getElementById('radarEmptyRow') || (typeof document.querySelector === 'function' ? document.querySelector('.radar-filter-empty-row') : null);`
- Retained defensive null checks and instant search matching across `data-name` and `data-search`.

### 2.4 `tests/verify_m5.php` (OVERHAULED)
- Replaced Iteration 1 test suite with `explorer_m5_it2_3/proposed_verify_m5.php`:
  - 0 test facades or mock stubs (`testEvaluateRisk()` completely eliminated).
  - Pre-execution guard requiring `includes/radar_helper.php` with fatal exit if missing.
  - Authentic calls to `evaluateRiskZone()` across all diagnostic boundary tests (Groups 1 and 2).
  - Tests 3.1–3.4 query real MySQL records, evaluate via `evaluateRiskZone()`, and assert math balance and DOM match against `calculateRadarStats()`.
  - Added live Apache error and warning assertions (tests 6.7–6.9) scanning HTTP payloads for 15 PHP error signatures.
  - Added unique DOM ID assertion (test 4.7) validating no duplicate IDs in rendered HTML.
  - Total test count expanded from 42 to 49 authentic assertions (49/49 PASS).

---

## 3. Verification Summary

| Test Suite | Command | Result |
|---|---|---|
| Milestone 5 Core Verification | `D:\xam\php\php.exe tests/verify_m5.php` | **49/49 PASS (100%)** |
| M5 Adversarial Radar Suite | `D:\xam\php\php.exe tests/challenge_m5_radar.php` | **42/42 PASS (100%)** |
| M5 Zalo Purge & UI Suite | `D:\xam\php\php.exe tests/challenge_m5_zalo_ui.php` | **24/24 PASS (100%)** |
| M5 Adversarial JS DOM Harness | `node tests/challenge_m5_js_dom.js` | **40/40 PASS (100%)** |
| M1 Database & Config Regression | `D:\xam\php\php.exe tests/verify_m1.php` | **12/12 PASS (100%)** |
| M2 Auth & RBAC Regression | `D:\xam\php\php.exe tests/verify_m2.php` | **16/16 PASS (100%)** |
| M2 Adversarial RBAC Regression | `D:\xam\php\php.exe tests/challenge_m2_rbac.php` | **37/37 PASS (100%)** |
| M3 Admin Module Regression | `D:\xam\php\php.exe tests/verify_m3.php` | **28/28 PASS (100%)** |
| M4 Gradebook & Calc Regression | `D:\xam\php\php.exe tests/verify_m4.php` | **39/39 PASS (100%)** |
| **Grand Total** | **9 Suites** | **287/287 PASS, 0 FAIL** |
