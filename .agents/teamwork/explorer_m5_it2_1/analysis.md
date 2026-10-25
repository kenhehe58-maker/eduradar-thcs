# Comprehensive Technical Analysis: Early-Warning Radar Diagnostic Logic & Boundary Remediation

**Author**: `explorer_m5_it2_1` (Diagnostic Logic & Boundary Explorer)  
**Date**: 2026-10-17  
**Milestone**: Milestone 5 Iteration 2  
**Target Code**: `D:\New folder\my-web-project\index.php` (lines 30–125)  
**Test Suites**: `tests/challenge_m5_radar.php`, `tests/verify_m5.php`  

---

## 1. Executive Summary

Milestone 5 Iteration 1 failed certification due to an **INTEGRITY VIOLATION** veto from `auditor_m5_1` and **REQUEST_CHANGES** verdicts from `challenger_m5_1` and `reviewer_m5_1`. The root causes were twofold:
1. **Production Runtime Defect (Typo)**: At `index.php:86`, an undefined variable `$isAvgWarning` was queried instead of the declared `$isAvgWarn` or accumulator `$isWarning`. When rendered on live Apache 2.4, this emitted **12 PHP warnings** in Admin HTML and **6 PHP warnings** in Teacher HTML, leaking internal filesystem paths into the browser DOM.
2. **Logic Boundary Defect (Failing Final Exam)**: A student failing the semester final exam with `ck = 4.9 < 5.0` (while `gk = 5.0` and `avg >= 5.0`) was misclassified as **🟢 Ổn định (Stable)** with diagnostic `"Đạt chuẩn, ổn định"`. This violated requirement R4 of `ORIGINAL_REQUEST.md` and Thông tư 22/2021/TT-BGDĐT.
3. **Self-Certifying Test Suite Masking the Defect**: `tests/verify_m5.php` implemented an isolated local function `testEvaluateRisk()` and ran 14 test cases against its own mock rather than testing `index.php:evaluateRiskZone()`, while tests 3.1–3.3 asserted on hardcoded arithmetic arrays.

This report provides the exhaustive forensic proof of these defects, formulates the exact bug-free diagnostic logic for `evaluateRiskZone()`, guarantees zero PHP warnings/notices under all inputs, and defines the verification blueprint for the implementer (`worker_m5`).

---

## 2. Forensic Investigation & Evidence

### 2.1 The Undefined Variable Bug (`$isAvgWarning`)

#### Source Code Observation (`index.php:70-87`):
```php
70:     // 2. VÙNG 🟡 CẦN CHÚ Ý (WARNING / AT RISK ZONE)
71:     $isWarning = false;
72: 
73:     $isAvgWarn = ($avg !== null && $avg >= 3.5 && $avg < 5.0);
74:     if ($isAvgWarn) {
75:         $isWarning = true;
76:         $reasons[] = 'Điểm TB dưới chuẩn (' . number_format($avg, 1) . ')';
77:     }
78: 
79:     // Sa sút cuối kỳ: ck < gk - 1.5 <=> (gk - ck) > 1.5
80:     if ($gk !== null && $ck !== null) {
81:         $drop = round((float)$gk - (float)$ck, 1);
82:         if ($drop > 1.5) {
83:             $isWarning = true;
84:             $reasons[] = 'Sa sút cuối kỳ (giảm ' . number_format($drop, 1) . ' đ so với GK)';
85:         }
86:     }
87: 
88:     if ($isAvgWarning || $isWarning) { // <--- BUG ON LINE 86
89:         return [
90:             'level' => 'yellow',
91:             'label' => 'Cần chú ý',
92:             'reasons' => $reasons,
93:             'diagnostic' => implode(' · ', $reasons),
94:         ];
95:     }
```

#### Runtime Warning Mechanism & Blast Radius:
- Line 73 declared `$isAvgWarn`.
- Line 88 checked `if ($isAvgWarning || $isWarning)`. In PHP 8.0, accessing an uninitialized variable `$isAvgWarning` raises an `E_WARNING: Undefined variable $isAvgWarning`.
- Because this check occurs in the Warning Zone, **every single student evaluation that does not trigger Critical (Red) reaches line 88**.
- In the database `edu_radar`, Admin views all assigned classes (13 student score rows). Only 1 student (Student #4: `gk=4.0, ck=4.0`) is Critical. The remaining **12 students** all reach line 88, emitting exactly **12 PHP warnings** in Admin HTML.
- For Teacher `gv_toan` (Toán), there are 7 students. Only 1 is Critical. The remaining **6 students** reach line 88, emitting exactly **6 PHP warnings** in Teacher HTML.
- When rendered via Apache (`http://localhost/my-web-project/index.php`), each warning appears as:
  `<br /><b>Warning</b>:  Undefined variable $isAvgWarning in <b>D:\New folder\my-web-project\index.php</b> on line <b>86</b><br />`
  This tears the Stitch UI layout and leaks the absolute local path `D:\New folder\my-web-project\index.php`.

### 2.2 The Single Exam Failure Boundary Defect (V3.4)

#### Requirement R4 (`ORIGINAL_REQUEST.md:42-45`):
> "Summary counters and filterable table of students classified into **🔴 Nguy cấp (Red)**, **🟡 Cần chú ý (Yellow)**, and **🟢 Ổn định (Green)** based on their VnEdu grade columns (e.g., average < 5.0, consecutive drop across regular/periodic tests, or component score < 5.0), clearly stating the specific diagnostic reason and which subject/class."

#### Adversarial Challenge Finding (`challenge_m5_radar.php:308-319`):
```php
challenge("V3.4", "Single exam failure: gk = 5.0 && ck = 4.9 must trigger 🟡 Yellow (Cần chú ý), NOT 🟢 Green", function() {
    $r = @evaluateRiskZone(5.5, 5.0, 4.9);
    if ($r['level'] === 'green') {
        throw new Exception("LOGIC DEFECT: Student failing final exam (ck=4.9) classified as 🟢 Green ('{$r['diagnostic']}')! Expected 🟡 Yellow (Cần chú ý).");
    }
...
```
When `gk = 5.0`, `ck = 4.9`, and `avg = 5.5`:
- Critical zone:
  - `avg < 3.5`? 5.5 < 3.5 -> `false`.
  - `ck < 3.0`? 4.9 < 3.0 -> `false`.
  - `both failing (< 5.0)`? `gk = 5.0 < 5.0` is `false`.
  -> Not Critical.
- Warning zone (previous code):
  - `avg in [3.5, 5.0)`? 5.5 in [3.5, 5.0) -> `false`.
  - `gk - ck > 1.5`? `5.0 - 4.9 = 0.1 > 1.5` -> `false`.
  -> Warning zone did not check individual exam components!
- Fallthrough to Stable zone:
  - `avg >= 5.0` -> returned `['level' => 'green', 'label' => 'Ổn định', 'diagnostic' => 'Đạt chuẩn, ổn định (5.5)']`.
- **Impact**: A student who failed the most heavily weighted periodic exam (Final Exam / ĐĐGck with coefficient 3) was falsely given a clean bill of health ("Đạt chuẩn, ổn định"), preventing pedagogical intervention.

---

## 3. Comprehensive Diagnostic Logic & Decision Waterfall

The diagnostic engine must follow a strict **waterfall precedence** across three zones:
```
           +---------------------------------------------+
           |           Student Score Profile             |
           |     ($avg, $gk, $ck, array $txScores)       |
           +---------------------------------------------+
                                  |
                                  v
     +---------------------------------------------------------+
     |                1. VÙNG 🔴 NGUY CẤP (CRITICAL)           |
     | - $avg !== null && $avg < 3.5                           |
     | - $ck !== null && $ck < 3.0                             |
     | - $gk !== null && $ck !== null && $gk < 5.0 && $ck < 5.0|
     +---------------------------------------------------------+
                    |                               |
                   YES                              NO
                    |                               |
                    v                               v
         [ RETURN 🔴 NGUY CẤP ]    +------------------------------------+
                                   |    2. VÙNG 🟡 CẦN CHÚ Ý (WARNING)  |
                                   | - $avg in [3.5, 5.0)               |
                                   | - $ck !== null && $ck < 5.0        |
                                   | - $gk !== null && $gk < 5.0        |
                                   | - ($gk - $ck) > 1.5                |
                                   +------------------------------------+
                                                  |                  |
                                                 YES                 NO
                                                  |                  |
                                                  v                  v
                                       [ RETURN 🟡 CẦN CHÚ Ý ]    +------+
                                                                  |  3.  |
                                                                  |  🟢  |
                                                                  +------+
                                                                     |
                                                                     v
                                                            [ RETURN 🟢 ỔN ĐỊNH ]
```

### 3.1 Zone 1: 🔴 Nguy Cấp (Critical Zone)

| Rule | Trigger Expression | Diagnostic Reason Template | Rationale |
|---|---|---|---|
| **C1** | `$avg !== null && $avg < 3.5` | `Điểm TB dưới 3.5 ({avg})` | Semester average in critical failure range. |
| **C2** | `$ck !== null && $ck < 3.0` | `Thi cuối kỳ dưới 3.0 ({ck})` | Fatal bottleneck score on final exam (coefficient 3). |
| **C3** | `$gk !== null && $ck !== null && $gk < 5.0 && $ck < 5.0` | `Hỏng cả GK ({gk}) và CK ({ck})` | Dual periodic exam failure. |

- **Precedence**: If any of C1, C2, or C3 is met, **immediately terminate and return `red`**.
- **Multi-reason aggregation**: If a student triggers multiple critical conditions (e.g. `avg = 3.0`, `ck = 2.5`, `gk = 4.0`), all triggered reasons are added to `$reasons` and concatenated with ` · `.
- **Return format**:
  ```php
  return [
      'level'      => 'red',
      'label'      => 'Nguy cấp',
      'reasons'    => $reasons,
      'diagnostic' => implode(' · ', $reasons),
  ];
  ```

### 3.2 Zone 2: 🟡 Cần Chú Ý (Warning / At Risk Zone)

Execution only reaches this zone if **no Critical condition was met**.  
Therefore, by definition:
- `$avg` is either `null` or `>= 3.5`.
- `$ck` is either `null` or `>= 3.0`.
- It is impossible for both `$gk < 5.0` and `$ck < 5.0` to be true simultaneously.

| Rule | Trigger Expression | Diagnostic Reason Template | Rationale |
|---|---|---|---|
| **W1** | `$avg !== null && $avg >= 3.5 && $avg < 5.0` | `Điểm TB dưới chuẩn ({avg})` | Average below passing mark (5.0) but above critical (3.5). |
| **W2** | `$ck !== null && $ck < 5.0` | `Thi cuối kỳ dưới trung bình ({ck})` | Final exam below passing (since C2 passed, $ck is in `[3.0, 5.0)`). |
| **W3** | `$gk !== null && $gk < 5.0` | `Thi giữa kỳ dưới trung bình ({gk})` | Midterm exam below passing (since C3 passed, $ck is `>= 5.0` or null). |
| **W4** | `$gk !== null && $ck !== null && round((float)$gk - (float)$ck, 1) > 1.5` | `Sa sút cuối kỳ (giảm {drop} đ so với GK)` | Significant downward drop between midterm and final exams. |

- **State Variable**: Managed by a single local boolean `$isWarning = false;`.
- **No Undefined Variables**: Every check sets `$isWarning = true;` and appends to `$reasons[]`.
- **Termination**: If `$isWarning === true`, return `yellow`.

### 3.3 Zone 3: 🟢 Ổn Định (Stable Zone)

Execution reaches this zone only when all grades are passing with no critical failures and no warnings.

| Sub-tier | Condition | Diagnostic Reason Template |
|---|---|---|
| **S1: Missing Data** | `$avg === null` | `Chưa đủ dữ liệu điểm số` |
| **S2: Xuất Sắc** | `$avg >= 8.0` | `Học lực Giỏi, kết quả xuất sắc ({avg})` |
| **S3: Khá** | `$avg >= 6.5` | `Học lực Khá, duy trì tốt ({avg})` |
| **S4: Đạt Chuẩn** | Otherwise (`5.0 <= $avg < 6.5`) | `Đạt chuẩn, ổn định ({avg})` |

- **Return format**:
  ```php
  return [
      'level'      => 'green',
      'label'      => 'Ổn định',
      'reasons'    => $reasons,
      'diagnostic' => implode(' · ', $reasons),
  ];
  ```

---

## 4. Exact Implementation Specification

Below is the complete, drop-in replacement code for `index.php` (lines 39 to 124):

```php
/**
 * Chẩn đoán mức độ rủi ro học tập của học sinh theo chuẩn Thông tư 22
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

    // -------------------------------------------------------------------------
    // 1. VÙNG 🔴 NGUY CẤP (CRITICAL ZONE)
    // -------------------------------------------------------------------------
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
            'level'      => 'red',
            'label'      => 'Nguy cấp',
            'reasons'    => $reasons,
            'diagnostic' => implode(' · ', $reasons),
        ];
    }

    // -------------------------------------------------------------------------
    // 2. VÙNG 🟡 CẦN CHÚ Ý (WARNING / AT RISK ZONE)
    // -------------------------------------------------------------------------
    $isWarning = false;

    // 2.1 Điểm TB dưới chuẩn (3.5 <= avg < 5.0)
    $isAvgWarn = ($avg !== null && $avg >= 3.5 && $avg < 5.0);
    if ($isAvgWarn) {
        $isWarning = true;
        $reasons[] = 'Điểm TB dưới chuẩn (' . number_format($avg, 1) . ')';
    }

    // 2.2 Điểm thi thành phần định kỳ dưới trung bình (< 5.0) nhưng chưa đến mức Nguy cấp
    if ($ck !== null && $ck < 5.0) {
        $isWarning = true;
        $reasons[] = 'Thi cuối kỳ dưới trung bình (' . number_format($ck, 1) . ')';
    }
    if ($gk !== null && $gk < 5.0) {
        $isWarning = true;
        $reasons[] = 'Thi giữa kỳ dưới trung bình (' . number_format($gk, 1) . ')';
    }

    // 2.3 Sa sút cuối kỳ: ck < gk - 1.5 <=> (gk - ck) > 1.5
    if ($gk !== null && $ck !== null) {
        $drop = round((float)$gk - (float)$ck, 1);
        if ($drop > 1.5) {
            $isWarning = true;
            $reasons[] = 'Sa sút cuối kỳ (giảm ' . number_format($drop, 1) . ' đ so với GK)';
        }
    }

    if ($isWarning) {
        return [
            'level'      => 'yellow',
            'label'      => 'Cần chú ý',
            'reasons'    => $reasons,
            'diagnostic' => implode(' · ', $reasons),
        ];
    }

    // -------------------------------------------------------------------------
    // 3. VÙNG 🟢 ỔN ĐỊNH (STABLE ZONE)
    // -------------------------------------------------------------------------
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
        'level'      => 'green',
        'label'      => 'Ổn định',
        'reasons'    => $reasons,
        'diagnostic' => implode(' · ', $reasons),
    ];
}

// -----------------------------------------------------------------------------
// HÀM TƯƠNG THÍCH NGƯỢC (BACKWARD COMPATIBILITY ALIASES)
// -----------------------------------------------------------------------------
function evaluateEarlyWarning(?float $avg, ?float $gk, ?float $ck, array $txScores = []): array
{
    return evaluateRiskZone($avg, $gk, $ck, $txScores);
}

function evaluateRisk(?float $avg, array $scores = [], ?float $gk = null, ?float $ck = null): array
{
    return evaluateRiskZone($avg, $gk, $ck, $scores);
}
```

---

## 5. Verification Matrix Across All Test Vectors

The logic above was independently fuzzed and verified against all 26 boundary cases in `scratch_test.php` under PHP 8.0 with `error_reporting(E_ALL)` and a strict `ErrorException` handler:

| Vector | Scenario / Inputs | Expected Level | Expected Reason Fragment | Actual Result | Status |
|---|---|:---:|---|---|:---:|
| **V1.1** | `avg = 3.49, gk = 5.0, ck = 5.0` | 🔴 `red` | `dưới 3.5` | `red` [`Điểm TB dưới 3.5 (3.5)`] | **PASS** |
| **V1.2** | `avg = 3.50, gk = 5.0, ck = 5.0` | 🟡 `yellow` | `dưới chuẩn` | `yellow` [`Điểm TB dưới chuẩn (3.5)`] | **PASS** |
| **V1.3** | `avg = 3.40, gk = 5.0, ck = 5.0` | 🔴 `red` | `dưới 3.5` | `red` [`Điểm TB dưới 3.5 (3.4)`] | **PASS** |
| **V1.4** | `avg = 4.99, gk = 5.0, ck = 5.0` | 🟡 `yellow` | `dưới chuẩn` | `yellow` [`Điểm TB dưới chuẩn (5.0)`] | **PASS** |
| **V1.5** | `avg = 5.00, gk = 5.0, ck = 5.0` | 🟢 `green` | `Đạt chuẩn, ổn định` | `green` [`Đạt chuẩn, ổn định (5.0)`] | **PASS** |
| **V1.6** | `avg = 4.90, gk = 5.0, ck = 5.0` | 🟡 `yellow` | `dưới chuẩn` | `yellow` [`Điểm TB dưới chuẩn (4.9)`] | **PASS** |
| **V2.1** | `avg = 6.5, gk = 7.0, ck = 2.9` | 🔴 `red` | `cuối kỳ dưới 3.0` | `red` [`Thi cuối kỳ dưới 3.0 (2.9)`] | **PASS** |
| **V2.2** | `avg = 7.2, gk = 10.0, ck = 2.9, tx=[10,10,10,10]` | 🔴 `red` | `cuối kỳ dưới 3.0` | `red` [`Thi cuối kỳ dưới 3.0 (2.9)`] | **PASS** |
| **V2.3** | `avg = 6.0, gk = 6.0, ck = 2.99` | 🔴 `red` | `cuối kỳ dưới 3.0` | `red` [`Thi cuối kỳ dưới 3.0 (3.0)`] | **PASS** |
| **V2.4** | `avg = 5.5, gk = null, ck = 3.0` | 🟡 `yellow` | `Thi cuối kỳ dưới trung bình` | `yellow` [`Thi cuối kỳ dưới trung bình (3.0)`] | **PASS** |
| **V3.1** | `avg = 5.5, gk = 4.9, ck = 4.9` | 🔴 `red` | `Hỏng cả GK` | `red` [`Hỏng cả GK (4.9) và CK (4.9)`] | **PASS** |
| **V3.2** | `avg = 5.8, gk = 4.99, ck = 4.99` | 🔴 `red` | `Hỏng cả GK` | `red` [`Hỏng cả GK (5.0) và CK (5.0)`] | **PASS** |
| **V3.3** | `avg = 5.5, gk = 4.9, ck = 5.0` | 🟡 `yellow` | `Thi giữa kỳ dưới trung bình` | `yellow` [`Thi giữa kỳ dưới trung bình (4.9)`] | **PASS** |
| **V3.4** | **`avg = 5.5, gk = 5.0, ck = 4.9`** | 🟡 `yellow` | `Thi cuối kỳ dưới trung bình` | `yellow` [`Thi cuối kỳ dưới trung bình (4.9)`] | **PASS (FIXED)** |
| **V4.1** | `avg = 7.0, gk = 8.0, ck = 6.5` (drop=1.5) | 🟢 `green` | `Học lực Khá` | `green` [`Học lực Khá, duy trì tốt (7.0)`] | **PASS** |
| **V4.2** | `avg = 7.0, gk = 8.0, ck = 6.4` (drop=1.6) | 🟡 `yellow` | `Sa sút cuối kỳ` | `yellow` [`Sa sút cuối kỳ (giảm 1.6 đ so với GK)`] | **PASS** |
| **V4.3** | `avg = 6.5, gk = 7.3, ck = 5.8` (IEEE drop 1.4999..) | 🟢 `green` | `Học lực Khá` | `green` [`Học lực Khá, duy trì tốt (6.5)`] | **PASS** |
| **V4.4** | `avg = 6.5, gk = 7.3, ck = 5.7` (IEEE drop 1.6) | 🟡 `yellow` | `Sa sút cuối kỳ` | `yellow` [`Sa sút cuối kỳ (giảm 1.6 đ so với GK)`] | **PASS** |
| **V4.5** | `avg = 7.2, gk = 5.0, ck = 8.0` (improve) | 🟢 `green` | `Học lực Khá` | `green` [`Học lực Khá, duy trì tốt (7.2)`] | **PASS** |
| **V4.6** | `avg = 5.5, gk = 8.0, ck = 2.8` (drop=5.2 & ck<3) | 🔴 `red` | `cuối kỳ dưới 3.0` | `red` [`Thi cuối kỳ dưới 3.0 (2.8)`] | **PASS** |
| **V5.1** | `avg = null, gk = null, ck = null, tx=[]` | 🟢 `green` | `Chưa đủ dữ liệu điểm số` | `green` [`Chưa đủ dữ liệu điểm số`] | **PASS** |
| **V5.2** | `avg = 7.5, gk = null, ck = null, tx=[7.0, 8.0]` | 🟢 `green` | `Học lực Khá` | `green` [`Học lực Khá, duy trì tốt (7.5)`] | **PASS** |
| **V5.3** | `avg = 3.2, gk = null, ck = null, tx=[3.0, 3.5]` | 🔴 `red` | `dưới 3.5` | `red` [`Điểm TB dưới 3.5 (3.2)`] | **PASS** |
| **V5.4** | `avg = 6.8, gk = 8.0, ck = null, tx=[7.0]` | 🟢 `green` | `Học lực Khá` | `green` [`Học lực Khá, duy trì tốt (6.8)`] | **PASS** |
| **V5.5** | `avg = 0.0, gk = 0.0, ck = 0.0, tx=[0.0]` | 🔴 `red` | `dưới 3.5` & `dưới 3.0` & `Hỏng cả GK` | `red` [`Điểm TB... · Thi cuối kỳ... · Hỏng cả GK...`] | **PASS** |
| **V5.6** | `avg = 0.0, gk = null, ck = null, tx=[0.0]` | 🔴 `red` | `dưới 3.5` | `red` [`Điểm TB dưới 3.5 (0.0)`] | **PASS** |

---

## 6. Worker Implementation & Verification Remediations

To ensure Milestone 5 Iteration 2 achieves an unconditional **CLEAN** audit certification:

### 6.1 `index.php` Remediation
- Replace lines 39 to 124 of `index.php` with the exact code specified in Section 4 above.
- Verify static absence of `$isAvgWarning`:
  `grep_search` for `isAvgWarning` must return 0 results.

### 6.2 `tests/verify_m5.php` Remediation
- **Delete `testEvaluateRisk()`**: Remove lines 152–197 completely.
- **Load `index.php` directly**:
  ```php
  // Safely import evaluateRiskZone() from index.php via output buffering
  if (!function_exists('evaluateRiskZone')) {
      $_SESSION['user_id'] = 1;
      $_SESSION['username'] = 'admin';
      $_SESSION['role'] = 'admin';
      ob_start();
      require_once dirname(__DIR__) . '/index.php';
      ob_end_clean();
  }
  ```
- **Update Group 1 & 2 Tests**: Call `evaluateRiskZone()` directly instead of `testEvaluateRisk()`.
- **Update Test 3.1–3.3**: Replace hardcoded local arrays with live data assertions or live HTML card parsing.
- **Add Live Warning Assertions**:
  ```php
  $res = $client->get('index.php');
  if (str_contains($res['body'], 'Warning:') || str_contains($res['body'], 'Notice:')) {
      throw new Exception("Live Apache response contains PHP Warning/Notice!");
  }
  ```

### 6.3 Test Suite Execution Goal
- `tests/challenge_m5_radar.php`: **42/42 PASS (100%)**, 0 failures, 0 warnings.
- `tests/verify_m5.php`: **42/42 PASS (100%)**, 0 failures, 0 warnings.
- Regressions `verify_m1.php` through `verify_m4.php`: **100% PASS**.
