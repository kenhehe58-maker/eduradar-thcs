# HANDOFF REPORT: Milestone 4 Adversarial Challenge & Score Calculation Verification

**Author**: `challenger_m4_1`  
**Role**: Empirical Challenger (critic / specialist)  
**Working Directory**: `D:\New folder\my-web-project\.agents\teamwork\challenger_m4_1`  
**Recipient**: `orchestrator_1_gen2` (`391812a0-32ec-4263-b097-6162669cc4f7`)  
**Type**: Hard Handoff (Challenge Complete)  
**Date**: 2026-10-17  
**Verdict**: **APPROVE**  

---

## 1. Observation

1. **Test Execution Command & Empirical Results**:
   - Executed `tests/challenge_m4_score_calc.php` via command:
     ```powershell
     & "D:\xam\php\php.exe" "D:\New folder\my-web-project\tests\challenge_m4_score_calc.php"
     ```
   - Verbatim console output:
     ```text
     =============================================================================
       EMPIRICAL ADVERSARIAL CHALLENGE SUITE: MILESTONE 4 SCORE CALCULATION
       Target: http://localhost/my-web-project/ | Live DB: edu_radar
     =============================================================================
     --- VECTOR 1: Extreme & Invalid Scores (Fuzzing & Injection) ---
     [CHALLENGE] [V1.1] Negative score -0.1 sanitized to NULL in MySQL................... [PASS]
     [CHALLENGE] [V1.2] Negative score -999.0 sanitized to NULL in MySQL................. [PASS]
     [CHALLENGE] [V1.3] Excessive score 10.1 sanitized to NULL in MySQL.................. [PASS]
     [CHALLENGE] [V1.4] Excessive score 999 and 1e5 sanitized to NULL.................... [PASS]
     [CHALLENGE] [V1.5] Non-numeric strings ('abc', 'N/A', 'null', 'NaN', 'Inf') sanitized to NULL [PASS]
     [CHALLENGE] [V1.6] SQL Injection payload in score input blocked & sanitized......... [PASS]
     [CHALLENGE] [V1.7] XSS payloads in score inputs sanitized & neutralized............. [PASS]
     [CHALLENGE] [V1.8] Malformed decimal strings ('8..5', '8,,5', '+-5') sanitized to NULL [PASS]
     --- VECTOR 2: Boundary Scores & Thresholds (0.0 to 10.0) ---
     [CHALLENGE] [V2.1] Absolute lower boundary 0.0 correctly stored (NOT NULL) and avg is 0.0 [PASS]
     [CHALLENGE] [V2.2] Absolute upper boundary 10.0 stored and avg is 10.0.............. [PASS]
     [CHALLENGE] [V2.3] Danger vs Warning boundary: exact 4.9 evaluates to avg_score 4.9. [PASS]
     [CHALLENGE] [V2.4] Rounding to boundary: mathematical 4.9375 rounds to 4.9.......... [PASS]
     [CHALLENGE] [V2.5] Rounding to boundary: mathematical 4.9625 rounds to 5.0.......... [PASS]
     [CHALLENGE] [V2.6] Good vs Excellent boundary: exact 7.9 evaluates to avg_score 7.9. [PASS]
     [CHALLENGE] [V2.7] Rounding to boundary: mathematical 7.875 rounds to 7.9........... [PASS]
     [CHALLENGE] [V2.8] Exact 8.0 boundary evaluates to avg_score 8.0.................... [PASS]
     [CHALLENGE] [V2.9] Thông tư 22 Chưa đạt boundary: exact 3.4 evaluates to 3.4.. [PASS]
     [CHALLENGE] [V2.10] Thông tư 22 boundary: exact 3.5 evaluates to 3.5.............. [PASS]
     --- VECTOR 3: Partial Scores & Incomplete Grading ---
     [CHALLENGE] [V3.1] Only 1 TX score entered evaluates to that score.................. [PASS]
     [CHALLENGE] [V3.2] Non-consecutive TX scores (TX2=8.0, TX4=6.0, others null) avg is 7.0 [PASS]
     [CHALLENGE] [V3.3] Partial entry: TX1=7.0, GK=8.0 without CK evaluates to 7.7....... [PASS]
     [CHALLENGE] [V3.4] Partial entry: Only CK=8.5 (no TX, no GK) evaluates to 8.5....... [PASS]
     [CHALLENGE] [V3.5] Partial entry: Only GK=9.0 (no TX, no CK) evaluates to 9.0....... [PASS]
     [CHALLENGE] [V3.6] Partial entry: GK=7.0 + CK=8.0 (no TX) evaluates to 7.6.......... [PASS]
     [CHALLENGE] [V3.7] Completely empty score row evaluates to NULL in avg_score........ [PASS]
     --- VECTOR 4: Weighting Verification per Thông tư 22 ---
     [CHALLENGE] [V4.1] TX has strict weight 1 (raising TX by 1 raises numerator by 1)... [PASS]
     [CHALLENGE] [V4.2] GK has strict weight 2 (raising GK by 1 raises numerator by 2)... [PASS]
     [CHALLENGE] [V4.3] CK has strict weight 3 (raising CK by 1 raises numerator by 3)... [PASS]
     [CHALLENGE] [V4.4] Weight ratio GK:TX = 2:1 and CK:TX = 3:1 verified mathematically. [PASS]
     [CHALLENGE] [V4.5] Full Thông tư 22 formula: 4 TX + GK + CK with uneven scores equals 8.4 [PASS]
     --- VECTOR 5: Comma vs Dot Decimal Separator Handling ---
     [CHALLENGE] [V5.1] Form POST with Vietnamese comma '8,5' parsed and stored as 8.50 in DB [PASS]
     [CHALLENGE] [V5.2] Comma and dot inputs produce strictly identical results in MySQL. [PASS]
     [CHALLENGE] [V5.3] CSV Import with comma decimal separator parsed correctly......... [PASS]
     --- VECTOR 6: Client JS Regex & Math Parity ---
     [CHALLENGE] [V6.1] Client-side regex exactly accepts valid scores and rejects invalid ones [PASS]
     [CHALLENGE] [V6.2] Parity between PHP backend formula and JS client formula across 50 score combinations [PASS]
     --- VECTOR 8: Excel (.xlsx) File Import Fuzzing & Boundaries ---
     [CHALLENGE] [V8.1] XLSX import with extreme and boundary scores sanitizes invalid & preserves valid [PASS]
     --- VECTOR 9: RBAC & IDOR Route Guard on Score Calculation ---
     [CHALLENGE] [V9.1] Teacher (gv_toan) attempting to POST scores to unassigned subject (Ngữ Văn) rejected with HTTP 403 [PASS]
     [CHALLENGE] [V9.2] Teacher (gv_toan) attempting to POST scores to unassigned class (Class 2) rejected with HTTP 403 [PASS]
     [CHALLENGE] [V9.3] Unauthenticated client attempting to POST scores redirected with zero DB modifications [PASS]
     --- VECTOR 7: Database Restoration & Integrity ---
     [CHALLENGE] [V7.1] Restore student 1 to baseline score state cleanly................ [PASS]
     =============================================================================
       TOTAL CHALLENGES: 40 | PASSED: 40 | FAILED: 0
     =============================================================================
     All 40 adversarial challenges PASSED with 0 vulnerabilities!
     ```

2. **Validation Logic in Codebase**:
   - `nhap_diem.php` lines 208–216:
     ```php
     $parseScore = function($val) {
         if ($val === null) return null;
         $s = trim(str_replace(',', '.', (string)$val));
         if ($s === '') return null;
         if (!is_numeric($s)) return null;
         $f = (float)$s;
         if ($f < 0.0 || $f > 10.0) return null;
         return $f;
     };
     ```
   - `nhap_diem.php` lines 257–265:
     ```php
     $avg = null;
     if ($gk !== null && $ck !== null) {
         $avg = round(($sTx + 2 * $gk + 3 * $ck) / ($nTx + 5), 1);
     } else {
         $weight = $nTx + ($gk !== null ? 2 : 0) + ($ck !== null ? 3 : 0);
         if ($weight > 0) {
             $avg = round(($sTx + 2 * ($gk ?? 0.0) + 3 * ($ck ?? 0.0)) / $weight, 1);
         }
     }
     ```
   - Client JS in `nhap_diem.php` lines 811 & 835:
     Regex `/^(10(\.0{1,2})?|[0-9](\.[0-9]{1,2})?)$/` rejects non-numeric, negative, and excessive numbers in real time, and auto-converts commas to dots on the `input` event.

3. **Regression Test Pass Rates**:
   - `tests/verify_m4.php`: 39/39 PASS.
   - `tests/verify_m3.php`: 28/28 PASS.
   - `tests/verify_m2.php`: 16/16 PASS.
   - `tests/verify_m1.php`: 12/12 PASS.
   - `tests/challenge_m2_rbac.php`: 37/37 PASS.
   - `tests/challenge_m3.php`: 39/39 PASS.

---

## 2. Logic Chain

1. **Input Sanitization & Injection Immunity (Observation 1, 2 -> Conclusion)**:
   - Because `$parseScore` in `nhap_diem.php` enforces `trim(str_replace(',', '.', (string)$val))`, `is_numeric($s)`, and range bounds `0.0 <= $f <= 10.0`, all malformed inputs (negative numbers, excessive scores, letters, SQL injections, XSS tags) evaluate to `null` before reaching SQL execution.
   - Because database execution is encapsulated in PDO prepared statements (`$upsertStmt->execute(...)`) with bound parameters, SQL injection is fundamentally neutralized.
   - Because HTML output casts numbers to `(float)` and uses `htmlspecialchars()`, stored or reflected XSS is completely neutralized.

2. **Boundary Precision & Absence of False Nulls (Observation 1, 2 -> Conclusion)**:
   - In PHP, loosely typed comparisons often evaluate `0` or `0.0` as falsy (`empty('0') === true`).
   - By using strict null comparisons (`!== null`), `0.0` is treated as a valid grade, correctly participating in the numerator and denominator, yielding `0.00` average score in MariaDB rather than disappearing as `NULL`.
   - Upper boundary `10.0` is preserved, yielding an average of `10.00`.
   - Rounding at fractional cutoffs (`4.9375` -> `4.9`, `4.9625` -> `5.0`, `7.875` -> `7.9`, `3.4428` -> `3.4`, `3.4857` -> `3.5`) matches standard educational rounding conventions (half-up to 1 decimal place).

3. **Mathematical Conformance to Thông tư 22 (Observation 1, 2 -> Conclusion)**:
   - According to Thông tư 22/2021/TT-BGDĐT: ĐĐGtx has weight 1, ĐĐGgk has weight 2, and ĐĐGck has weight 3.
   - When all assessment components are present, `ĐTBmhk = (sum(TX) + 2*GK + 3*CK) / (count(TX) + 5)`.
   - Empirically, increasing GK by 1 point yields exactly double the numerator gain of increasing TX by 1 point (+2.0 vs +1.0), and increasing CK by 1 point yields triple the gain (+3.0).
   - Incomplete semester grading (dynamic weighted average) correctly scales by the sum of weights of entered components, and completely empty rows produce `NULL`.

4. **Zero Client/Server Drift (Observation 1, 2 -> Conclusion)**:
   - Comparison across 50 pseudo-random score combinations showed 100% agreement between client-side JavaScript (`recalculateRow`) and PHP backend calculation.

---

## 3. Caveats

- **No Caveats**: All 40 adversarial challenges and 171 regression assertions pass with 0 failures on live Apache (`http://localhost/my-web-project/`) and MariaDB (`edu_radar`). Database state was restored to its exact baseline upon test completion.

---

## 4. Conclusion

The Milestone 4 score calculation engine and input validation mechanisms are **mathematically correct, resilient against hostile input fuzzing and injection attacks, compliant with Thông tư 22/2021/TT-BGDĐT, and free of regression side-effects**.

Final Verdict: **APPROVE**.

---

## 5. Verification Method

To independently reproduce the adversarial findings and verify test results:

1. **Run the Adversarial Score Calculation Test Suite**:
   ```powershell
   & "D:\xam\php\php.exe" "D:\New folder\my-web-project\tests\challenge_m4_score_calc.php"
   ```
   *Expected output*: `TOTAL CHALLENGES: 40 | PASSED: 40 | FAILED: 0`.

2. **Run Full Milestone 4 Verification Suite**:
   ```powershell
   & "D:\xam\php\php.exe" "D:\New folder\my-web-project\tests\verify_m4.php"
   ```
   *Expected output*: `Total Tests: 39 | Passed: 39 | Failed: 0`.

3. **Run Regression Suites**:
   ```powershell
   & "D:\xam\php\php.exe" "D:\New folder\my-web-project\tests\verify_m3.php"
   & "D:\xam\php\php.exe" "D:\New folder\my-web-project\tests\challenge_m3.php"
   & "D:\xam\php\php.exe" "D:\New folder\my-web-project\tests\challenge_m2_rbac.php"
   ```
   *Expected output*: 0 failures across all suites.
