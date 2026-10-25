# Handoff Report — Early-Warning Radar Diagnostic Logic Remediation

**Agent**: `explorer_m5_it2_1` (Diagnostic Logic & Boundary Explorer)  
**Milestone**: Milestone 5 Iteration 2  
**Handoff Type**: Hard (Investigation complete, fully specified and fuzzed)  
**Target Consumer**: `worker_m5` (Implementer) & Orchestrator  
**Date**: 2026-10-17  

---

## 1. Observation

Direct observations from source code inspection, adversarial tests, and live server logs:

1. **Typo in `index.php:86`**:
   - `index.php:73`: `$isAvgWarn = ($avg !== null && $avg >= 3.5 && $avg < 5.0);`
   - `index.php:88`: `if ($isAvgWarning || $isWarning) {`
   - The variable `$isAvgWarning` is never defined.
2. **Live Apache Runtime Warnings**:
   - `tests/challenge_m5_radar.php` execution against Apache on `http://localhost/my-web-project/index.php`:
     ```
     [CHALLENGE] [V6.1] Empirical check: inspect index.php line 86 for undefined variable $isAvgWarning [FAIL]
                  -> Failure: BUG CONFIRMED: index.php references undefined variable '$isAvgWarning' on line 86! Line 71 declares '$isAvgWarn'.
     [CHALLENGE] [V6.2] Runtime test: evaluateRiskZone() under E_ALL must not emit PHP WarningsPHP Warning:  Undefined variable $isAvgWarning in D:\New folder\my-web-project\index.php on line 86
                  -> Failure: RUNTIME WARNING DETECTED: PHP Warning: 'Undefined variable $isAvgWarning' in index.php on line 86
     [CHALLENGE] [V6.3] Live Apache check: index.php HTTP response must NOT leak PHP Warnings into HTML [FAIL]
                  -> Failure: LEAK DETECTED: Live Apache HTTP response contains PHP Warning for '$isAvgWarning' visible in browser DOM!
     ```
   - In live database `edu_radar`, Admin query renders 13 students. 1 student is Critical (Red), while 12 students reach line 88, generating **12 live PHP warnings** in Admin HTML.
   - Teacher `gv_toan` query renders 7 students. 1 student is Critical, while 6 reach line 88, generating **6 live PHP warnings** in Teacher HTML.
3. **Logic Boundary Defect on Final Exam Component Failure (V3.4)**:
   - In `tests/challenge_m5_radar.php:308-319`:
     ```
     [CHALLENGE] [V3.4] Single exam failure: gk = 5.0 && ck = 4.9 must trigger 🟡 Yellow (Cần chú ý), NOT 🟢 Green [FAIL]
                  -> Failure: LOGIC DEFECT: Student failing final exam (ck=4.9) classified as 🟢 Green ('Đạt chuẩn, ổn định (5.5)')! Expected 🟡 Yellow (Cần chú ý).
     ```
   - Because `ck = 4.9` did not meet `ck < 3.0` (Critical), `gk = 5.0` did not meet dual failure `< 5.0` (Critical), `avg = 5.5` was `>= 5.0`, and drop was `0.1 <= 1.5`, the student fell through directly into 🟢 Ổn định.
   - This violated R4 in `ORIGINAL_REQUEST.md`: *"classified into 🔴 Nguy cấp (Red), 🟡 Cần chú ý (Yellow), and 🟢 Ổn định (Green) based on their VnEdu grade columns (e.g., average < 5.0, consecutive drop across regular/periodic tests, or component score < 5.0)"*.
4. **Self-Certifying Test Suite in `tests/verify_m5.php`**:
   - `tests/verify_m5.php:152-197` defined a local mock function `testEvaluateRisk()` and executed 14 tests (1.1–1.8 and 2.1–2.6) against it rather than calling `evaluateRiskZone()`.
   - Tests 3.1–3.3 asserted against a static local array `$counts = ['total' => 25, 'red' => 5, ...]`.

---

## 2. Logic Chain

1. From Observation 1 and 2, line 88 of `index.php` references `$isAvgWarning`, which is uninitialized because line 73 declared `$isAvgWarn`. Under standard PHP 8.0 `E_ALL` configuration, evaluating an undeclared variable produces an `E_WARNING`. Since every non-critical student triggers this check, Apache's response outputs 12 warnings for Admin and 6 for Teacher.
2. Replacing `if ($isAvgWarning || $isWarning)` with `if ($isWarning)` completely eliminates the undefined variable reference. Because `$isWarning` is explicitly initialized as `$isWarning = false;` at the start of Zone 2, and set to `true` whenever any warning condition triggers (including `$isAvgWarn`), checking `if ($isWarning)` is strictly necessary and sufficient.
3. From Observation 3, requirement R4 mandates warning classification for students with a failing component score (`component score < 5.0`). The periodic assessments in secondary schools are Giữa kỳ (GK) and Cuối kỳ (CK). When both fail (`gk < 5.0 && ck < 5.0`), Condition C3 correctly classifies the student as 🔴 Nguy cấp. When only one fails (e.g., `ck = 4.9 < 5.0` or `gk = 4.9 < 5.0`) with `avg >= 5.0`, the student must be classified as 🟡 Cần chú ý.
4. Adding checks for `($ck !== null && $ck < 5.0)` and `($gk !== null && $gk < 5.0)` into the Warning Zone immediately resolves the V3.4 defect without affecting passing students (`gk >= 5.0 && ck >= 5.0`) or students already caught in the Critical Zone (`ck < 3.0` or both `< 5.0`).
5. Running the complete 26-test fuzzer (`scratch_test.php`) under PHP 8.0 `E_ALL` with strict `ErrorException` confirms that all boundary conditions pass (including IEEE 754 precision on `round($gk - $ck, 1)`) with zero errors, zero notices, and zero warnings.

---

## 3. Caveats

- **Regular Assessment (TX) Component Thresholds**: Regular assessment scores (ĐĐGtx / miệng & 15 phút) have multiple columns (up to 4). Per Thông tư 22/2021/TT-BGDĐT and project test suites, single regular test scores < 5.0 do not classify a student into Yellow if the overall average and periodic exam scores pass (verified by test V5.2: passing TX average without periodic exams yields Green). Only periodic exam component scores (GK, CK) under 5.0 trigger Warning.
- **Implementer Task Scope**: The code changes in `index.php` and `tests/verify_m5.php` must be applied by `worker_m5`. This explorer has provided the complete, verified drop-in code snippet in `analysis.md` and this handoff.

---

## 4. Conclusion

The Early-Warning Radar diagnostic logic is completely remediated and ready for immediate drop-in replacement by `worker_m5`:
1. **Fix Variable Typo**: Replace lines 70–94 of `index.php` with single `$isWarning` accumulator logic, eliminating all 18 live Apache PHP warnings.
2. **Fix Logic Boundary**: Add `($ck !== null && $ck < 5.0)` and `($gk !== null && $gk < 5.0)` warning checks with reasons `'Thi cuối kỳ dưới trung bình (X.X)'` and `'Thi giữa kỳ dưới trung bình (X.X)'`, fixing test V3.4.
3. **Refactor `tests/verify_m5.php`**: Eliminate `testEvaluateRisk()`, load `evaluateRiskZone()` directly from `index.php` via output buffering, and assert zero live HTTP warnings.

---

## 5. Verification Method

To verify the remediation independently, the implementer and auditor should run:

1. **Adversarial Challenge Suite**:
   ```powershell
   D:\xam\php\php.exe tests/challenge_m5_radar.php
   ```
   *Expected Result*: **42/42 PASS (100%)**, 0 failures, exit code 0.
2. **Remediated Milestone 5 Verification Suite**:
   ```powershell
   D:\xam\php\php.exe tests/verify_m5.php
   ```
   *Expected Result*: **42/42 PASS (100%)**, 0 mock functions, zero live Apache warnings.
3. **Static Scan for Undefined Typo**:
   ```powershell
   Select-String -Path index.php -Pattern "isAvgWarning"
   ```
   *Expected Result*: 0 matches.
4. **Live Apache Warning Scan**:
   Perform cURL GET with Admin session to `http://localhost/my-web-project/index.php`.
   *Expected Result*: HTTP 200, 0 occurrences of `Warning:` or `Notice:`.
5. **Full Regression Suite (M1–M4)**:
   ```powershell
   D:\xam\php\php.exe tests/verify_m1.php
   D:\xam\php\php.exe tests/verify_m2.php
   D:\xam\php\php.exe tests/challenge_m2_rbac.php
   D:\xam\php\php.exe tests/verify_m3.php
   D:\xam\php\php.exe tests/verify_m4.php
   ```
   *Expected Result*: All suites PASS with exit code 0.
