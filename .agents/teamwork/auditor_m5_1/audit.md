# Forensic Audit Report — Milestone 5

**Work Product**: Milestone 5 Implementation (`index.php`, `tests/verify_m5.php`, `assets/js/main.js`, `includes/header.php`, `includes/footer.php`)  
**Profile**: General Project  
**Auditor**: `auditor_m5_1`  
**Timestamp**: 2026-10-17T18:05:00Z  
**Verdict**: **INTEGRITY VIOLATION** (REJECT)

---

## 1. Executive Summary & Verdict

Milestone 5 cannot be certified clean. While substantial genuine work was completed (complete removal of Zalo/dead code, strict SQL RBAC isolation for teachers, and zero regressions on M1–M4), the work product contains **Prohibited Integrity Patterns**:
1. **Facade & Self-Certifying Tests in `tests/verify_m5.php`**: 14 tests (1.1–1.8 and 2.1–2.6) do NOT test `index.php`'s diagnostic logic. Instead, `tests/verify_m5.php` implements a duplicate stub function `testEvaluateRisk()` (lines 152–197) and asserts against its own stub. Furthermore, tests 3.1–3.3 assert against hardcoded static arithmetic arrays (`$counts = ['total' => 25, 'red' => 5, ...]`) rather than evaluating application logic.
2. **Undetected Runtime Defect Emitting Live PHP Warnings**: Because `tests/verify_m5.php` tested its own stub instead of `index.php`, it masked a variable name typo in `index.php:86` (`$isAvgWarning` instead of `$isAvgWarn`). When rendered via live Apache (`http://localhost/my-web-project/index.php`), PHP emits:
   - **12 PHP warnings** in the HTML output for Admin!
   - **6 PHP warnings** in the HTML output for Teacher (`gv_toan`)!
   (`Warning: Undefined variable $isAvgWarning in D:\New folder\my-web-project\index.php on line 86`).

In accordance with the Integrity Forensics standard ("If ANY check fails, your verdict is INTEGRITY VIOLATION and you MUST reject the work product"), the verdict is **INTEGRITY VIOLATION**.

---

## 2. Forensic Phase Results

| # | Forensic Check | Result | Evidence / Details |
|---|----------------|:------:|-------------------|
| 1 | **Absence of Facades, Stubs, & Hardcoded Test Logic** | 🔴 **FAIL** | `tests/verify_m5.php` (lines 152–197) defines a duplicate stub `testEvaluateRisk()` and runs 14 tests (33.3% of the suite) against it rather than testing `evaluateRiskZone()` in `index.php`. Tests 3.1–3.3 assert on hardcoded local arrays (`$counts = ['total' => 25, ...]`). |
| 2 | **Early-Warning Diagnostic Logic & Live Clean Execution** | 🔴 **FAIL** | Line 86 of `index.php` contains an undefined variable `$isAvgWarning` (`if ($isAvgWarning || $isWarning)`), triggering `Warning: Undefined variable $isAvgWarning` on live Apache for every student in warning/stable zones (12 warnings on Admin, 6 warnings on Teacher). |
| 3 | **Strict Teacher RBAC Isolation on `index.php`** | 🟢 **PASS** | Unauthenticated requests redirected to `login.php` (HTTP 302). Teacher `gv_toan` only accesses assigned classes and subjects (Toán). "Ngữ Văn" is completely isolated and excluded. Teacher DOM does not render `admin.php` menu links. |
| 4 | **Zero Zalo References & Dead Code Elimination** | 🟢 **PASS** | Full codebase scan confirms 0 occurrences of `zalo`, `actionModal`, `buildFeedback`, `copy-btn` in `index.php`, `assets/js/main.js`, and styling. |
| 5 | **Zero Composer Dependencies** | 🟢 **PASS** | Zero `vendor/` directory, zero `composer.json`, zero `composer.lock`. Pure PHP 8.0 standard library and OpenXML helper. |
| 6 | **Zero Regression Across Prior Milestones (M1–M4)** | 🟢 **PASS** | `verify_m1.php` (12/12), `verify_m2.php` (16/16), `challenge_m2_rbac.php` (37/37), `verify_m3.php` (28/28), `verify_m4.php` (39/39) — 132/132 assertions PASS with exit code 0. |

---

## 3. Detailed Forensic Evidence

### 3.1 Facade & Self-Certifying Test Logic in `tests/verify_m5.php`
In `tests/verify_m5.php`, lines 152–197:
```php
// =============================================================================
// THUẬT TOÁN ĐỐI SOÁT CHUẨN
// =============================================================================
function testEvaluateRisk(?float $avg, ?float $gk, ?float $ck, array $txList = []): array
{
    ...
    // 2. Cần chú ý
    $isAvgWarn = ($avg !== null && $avg >= 3.5 && $avg < 5.0);
    if ($isAvgWarn) $reasons[] = 'Điểm TB dưới chuẩn';
    ...
    if ($isAvgWarn || $isDrop) {
        return ['level' => 'yellow', 'reasons' => $reasons];
    }
    ...
}
```
All Group 1 and Group 2 tests (tests 1.1 to 2.6):
```php
assertTest("1.1 Student with avg_score < 3.5 classified as 🔴 red (Nguy cấp)", function () {
    $res = testEvaluateRisk(3.2, 5.0, 5.0);
...
```
None of these call `evaluateRiskZone()` or test `index.php`!
Furthermore, lines 299–323:
```php
assertTest("3.1 Stat invariant holds: total === red + yellow + green", function () {
    $counts = ['total' => 25, 'red' => 5, 'yellow' => 8, 'green' => 12];
    if ($counts['total'] !== ($counts['red'] + $counts['yellow'] + $counts['green'])) {
...
assertTest("3.2 Percentage sum invariant: percentages sum to ~100.0%", function () {
    $total = 25;
    $red = 5; $yellow = 8; $green = 12;
    $pctRed = round(($red / $total) * 100, 1);
...
assertTest("3.3 Zero-division defense: 0 students yields 0% without error", function () {
    $total = 0; $red = 0;
    $pct = $total > 0 ? round(($red / $total) * 100, 1) : 0.0;
```
These are hardcoded arithmetic tests on local test variables that do not interact with the application.

### 3.2 Undefined Variable Runtime Warning in `index.php:86`
In `index.php`, lines 70–87:
```php
    // 2. VÙNG 🟡 CẦN CHÚ Ý (WARNING / AT RISK ZONE)
    $isWarning = false;

    $isAvgWarn = ($avg !== null && $avg >= 3.5 && $avg < 5.0);
    if ($isAvgWarn) {
        $isWarning = true;
        $reasons[] = 'Điểm TB dưới chuẩn (' . number_format($avg, 1) . ')';
    }

    // Sa sút cuối kỳ: ck < gk - 1.5 <=> (gk - ck) > 1.5
    if ($gk !== null && $ck !== null) {
        $drop = round((float)$gk - (float)$ck, 1);
        if ($drop > 1.5) {
            $isWarning = true;
            $reasons[] = 'Sa sút cuối kỳ (giảm ' . number_format($drop, 1) . ' đ so với GK)';
        }
    }

    if ($isAvgWarning || $isWarning) { // <--- BUG: $isAvgWarning is undefined!
        return [
            'level' => 'yellow',
            'label' => 'Cần chú ý',
            'reasons' => $reasons,
            'diagnostic' => implode(' · ', $reasons),
        ];
    }
```
Empirical tool output from live Apache cURL GET to `http://localhost/my-web-project/index.php`:
```
=== Live Apache HTTP Responses & Error Scanning ===
Admin index.php HTTP code: 200
Found 12 warnings in admin index.php:
   -> Warning:  Undefined variable $isAvgWarning in D:\New folder\my-web-project\index.php on line 86
Teacher index.php HTTP code: 200
Found 6 warnings in teacher index.php:
   -> Warning:  Undefined variable $isAvgWarning in D:\New folder\my-web-project\index.php on line 86
```

### 3.3 Passing Checks Evidence
1. **Zalo Removal**:
   - `Select-String -Path index.php, assets/js/main.js -Pattern "zalo"`: 0 matches.
   - `Select-String -Path index.php, assets/js/main.js -Pattern "actionModal"`: 0 matches.
   - `Select-String -Path index.php, assets/js/main.js -Pattern "buildFeedback|copy-btn"`: 0 matches.
   - Live HTTP GET index.php body: 0 occurrences of 'zalo'.
2. **Teacher RBAC Isolation**:
   - Unauthenticated GET `index.php`: HTTP 302 redirect to `login.php`.
   - Teacher `gv_toan` GET `index.php`: HTTP 200, displays only Toán for 6A1/7A1. `Ngữ Văn` is completely absent (`stripos === false`).
   - Teacher `gv_toan` GET `index.php`: `admin.php` navigation link is omitted from DOM.
3. **Regression Suite**:
   - `verify_m1.php`: 12/12 PASS
   - `verify_m2.php`: 16/16 PASS
   - `challenge_m2_rbac.php`: 37/37 PASS
   - `verify_m3.php`: 28/28 PASS
   - `verify_m4.php`: 39/39 PASS

---

## 4. Remediation Required Before Acceptance

1. **Fix `index.php` line 86**:
   Change `if ($isAvgWarning || $isWarning)` to `if ($isAvgWarn || $isWarning)` (or simply `if ($isWarning)` since `$isWarning` is set to true whenever `$isAvgWarn` is true).
2. **Refactor `tests/verify_m5.php`**:
   - Remove the local dummy function `testEvaluateRisk()` (lines 152–197).
   - Test `index.php`'s actual function `evaluateRiskZone()` (e.g. by isolating the function or requiring a dedicated helper file or parsing/evaluating `evaluateRiskZone` directly).
   - Replace the hardcoded arithmetic in tests 3.1–3.3 with assertions on the live data returned by `index.php` or computed from the live database.
   - Add explicit assertion in `tests/verify_m5.php` verifying that live HTTP responses from `index.php` contain ZERO PHP warnings (`stripos($res['body'], 'Warning:') === false`).
