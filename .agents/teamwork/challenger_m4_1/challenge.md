# EMPIRICAL ADVERSARIAL CHALLENGE REPORT: MILESTONE 4 SCORE CALCULATION & VALIDATION

**Agent**: `challenger_m4_1`  
**Role**: Empirical Challenger (critic / specialist)  
**Target**: Milestone 4 Implementation (`nhap_diem.php`, `scores` table in `edu_radar`, `includes/xlsx_helper.php`)  
**Verdict**: **APPROVE**  
**Overall Risk Assessment**: **LOW**  
**Date**: 2026-10-17  

---

## 1. Challenge Summary

We conducted comprehensive adversarial stress-testing against Milestone 4 score calculation and input validation across 7 major attack vectors comprising 40 distinct automated challenges:
1. **Extreme & Invalid Scores**: Fuzzing with negative values (`-0.1`, `-999.0`), excessive values (`10.1`, `999`, `1e5`), non-numeric strings (`abc`, `null`, `NaN`, `Inf`), SQL injection payloads, and XSS attack strings.
2. **Boundary Scores & Classification Thresholds**: Testing lower boundary `0.0`, upper boundary `10.0`, danger/warning threshold `4.9` vs `5.0` (including half-way rounding `4.9375` -> `4.9` and `4.9625` -> `5.0`), good/excellent threshold `7.9` vs `8.0` (including `7.875` -> `7.9`), and Thông tư 22 "Chưa đạt" threshold `3.4` vs `3.5`.
3. **Partial Scores & Incomplete Grading**: Single TX entry, non-consecutive TX entries, TX + GK without CK, only CK, only GK, GK + CK without TX, and completely empty rows.
4. **Weighting Verification per Thông tư 22/2021/TT-BGDĐT**: Verifying strict component weighting where TX has weight 1, GK has weight 2, and CK has weight 3, proving GK impact is exactly 2x TX, and CK impact is exactly 3x TX.
5. **Comma vs Dot Input Equivalence**: Verifying Vietnamese comma decimal separator (`8,5`) equivalence with standard dot notation (`8.5`) in both HTTP POST forms and CSV/XLSX imports.
6. **Client-Side Regex & Backend Parity**: Stress-testing client JavaScript regex `/^(10(\.0{1,2})?|[0-9](\.[0-9]{1,2})?)$/` and verifying 100% mathematical parity between PHP backend calculation and JavaScript real-time calculation across 50 pseudo-random score vectors.
7. **Excel (.xlsx) Import Fuzzing & Route RBAC**: Importing OpenXML spreadsheets containing invalid values, and testing BOLA/IDOR boundaries on grade modification routes.

All 40 adversarial challenges PASSED with 0 vulnerabilities, 0 database corruptions, and 0 regression failures.

---

## 2. Adversarial Challenges & Hypotheses Tested

### [Low Risk] Challenge 1: Out-of-Bounds & Negative Fuzzing
- **Assumption challenged**: The system might accept negative scores (`-0.1`, `-999.0`) or excessive scores (`10.1`, `999`) or crash on non-numeric strings (`abc`, `NaN`, `null`).
- **Attack scenario**: Attacker sends direct HTTP POST requests with negative, excessive, or non-numeric strings to `scores[student_id][tx1..ck]`.
- **Blast radius**: If accepted, negative or invalid grades would skew academic rankings or trigger PHP fatal errors / SQL constraint violations.
- **Observed empirical behavior**: Lines 208–216 of `nhap_diem.php` utilize `$parseScore` which strictly enforces `is_numeric($s)` and `0.0 <= $f <= 10.0`. All out-of-bounds inputs evaluate to `NULL` before database insertion. Valid peer scores in the same request are preserved.
- **Verdict**: PASS (Mitigation verified).

### [Low Risk] Challenge 2: SQL Injection & Stored XSS Payloads
- **Assumption challenged**: Raw score inputs might be concatenated into SQL queries or reflected directly into HTML attributes or tooltips without escaping.
- **Attack scenario**: Submitting `1; DROP TABLE scores;--`, `1' OR '1'='1`, `<script>alert("XSS")</script>`, and `<svg/onload=alert(1)>`.
- **Blast radius**: SQL injection could lead to database loss; XSS could lead to session hijacking.
- **Observed empirical behavior**: 
  - SQL inputs fail `is_numeric()` check, evaluated to `NULL`.
  - Database operations use prepared statements with parameter binding exclusively. `scores` table remained 100% intact.
  - HTML rendering in `nhap_diem.php` casts score values via `(float)$s['tx1']` and wraps textual attributes in `htmlspecialchars()`. Zero script execution.
- **Verdict**: PASS (Mitigation verified).

### [Low Risk] Challenge 3: Boundary Value 0.0 False-Null Collision
- **Assumption challenged**: Because `0` and `0.0` evaluate to falsy in PHP loosely-typed comparisons (e.g. `empty(0)` is true), a legitimate score of `0.0` might be mistakenly discarded as `NULL` or ignored in average calculations.
- **Attack scenario**: Submitting `0.0` for all components (TX1=0, TX2=0, GK=0, CK=0).
- **Blast radius**: A student scoring zero would have their grade marked as missing (`NULL`) rather than failing (`0.0`), failing to trigger early-warning alerts.
- **Observed empirical behavior**: `nhap_diem.php` uses strict null comparisons (`fn($v) => $v !== null`, `$gk !== null`, `$ck !== null`). `0.0` is recognized as a valid score, stored in MariaDB as `0.00`, and yields an average of `0.00` with badge class `level-danger`.
- **Verdict**: PASS (Mitigation verified).

### [Low Risk] Challenge 4: Mathematical Rounding at Decision Boundaries (4.9 vs 5.0, 7.9 vs 8.0, 3.4 vs 3.5)
- **Assumption challenged**: Floating-point imprecision or incorrect rounding modes could misclassify students on the boundary between academic tiers (e.g. classifying 4.95 as failing instead of 5.0).
- **Attack scenario**: Inject score sets producing fractional values around decision boundaries:
  - `4.9375` (should round to `4.9`)
  - `4.9625` (should round to `5.0`)
  - `7.875` (should round to `7.9`)
  - `3.4428` (should round to `3.4`)
  - `3.4857` (should round to `3.5`)
- **Blast radius**: Misclassification of students into Red (Danger) or Yellow (Warning) early-warning alerts.
- **Observed empirical behavior**: PHP `round($avg, 1)` uses standard half-up rounding, exactly matching Thông tư 22 requirements. All boundary fractions rounded to their exact expected decimal values.
- **Verdict**: PASS (Mitigation verified).

### [Low Risk] Challenge 5: Thông tư 22 Component Weighting Integrity
- **Assumption challenged**: The dynamic weighted average might misassign weights to GK or CK when partially entered or when all components are present.
- **Attack scenario**: Incrementing TX, GK, and CK by exactly +1.0 from identical baselines, and measuring the resulting delta in numerator and average score.
- **Blast radius**: Violation of Ministry of Education (BGD&ĐT) regulations.
- **Observed empirical behavior**:
  - Baseline `TX1=6, GK=6, CK=6` yields `6.0`.
  - Raising `TX1` to `7.0` yields `6.2` (numerator delta: +1.0).
  - Raising `GK` to `7.0` yields `6.3` (numerator delta: +2.0).
  - Raising `CK` to `7.0` yields `6.5` (numerator delta: +3.0).
  - Mathematical ratio `GK:TX = 2:1` and `CK:TX = 3:1` confirmed on live MariaDB.
- **Verdict**: PASS (Mitigation verified).

### [Low Risk] Challenge 6: Vietnamese Comma (`8,5`) Decimal Handling
- **Assumption challenged**: Vietnamese users typically type commas (`8,5`) rather than dots (`8.5`). If unhandled, this could cause rejection or truncate numbers to integers.
- **Attack scenario**: Submitting `'8,5'`, mixed dot/comma payloads, and importing CSV with quoted comma values (`"8,5"`).
- **Blast radius**: User frustration, data entry loss, or corrupted scores.
- **Observed empirical behavior**: Both client JS (`str.replace(',', '.')`) and PHP backend (`str_replace(',', '.', (string)$val)`) normalize commas to dots seamlessly. Database rows and calculated averages are strictly identical.
- **Verdict**: PASS (Mitigation verified).

---

## 3. Stress Test Results Summary

| Challenge ID | Scenario Description | Expected Outcome | Actual Outcome | Status |
|---|---|---|---|---|
| **V1.1** | Negative score `-0.1` | Sanitized to NULL in DB | Stored as NULL | **PASS** |
| **V1.2** | Negative score `-999.0` | Sanitized to NULL in DB | Stored as NULL | **PASS** |
| **V1.3** | Excessive score `10.1` | Sanitized to NULL in DB | Stored as NULL | **PASS** |
| **V1.4** | Excessive score `999` and `1e5` | Sanitized to NULL in DB | Stored as NULL | **PASS** |
| **V1.5** | Non-numeric strings (`abc`, `null`, `NaN`) | Sanitized to NULL in DB | Stored as NULL | **PASS** |
| **V1.6** | SQL Injection in score input | Prepared stmt blocks; DB intact | No SQL injection, table intact | **PASS** |
| **V1.7** | XSS payload in score input | Sanitized to NULL, 0 reflection | No stored XSS, 0 reflection | **PASS** |
| **V1.8** | Malformed strings (`8..5`, `8,,5`) | Sanitized to NULL in DB | Stored as NULL | **PASS** |
| **V2.1** | Absolute lower boundary `0.0` | Stored as `0.00`, avg is `0.0` | Stored as `0.00`, avg is `0.0` | **PASS** |
| **V2.2** | Absolute upper boundary `10.0` | Stored as `10.00`, avg is `10.0`| Stored as `10.00`, avg is `10.0`| **PASS** |
| **V2.3** | Exact threshold `4.9` | Avg score `4.9` | Stored as `4.90` | **PASS** |
| **V2.4** | Boundary fraction `4.9375` | Rounds down to `4.9` | Stored as `4.90` | **PASS** |
| **V2.5** | Boundary fraction `4.9625` | Rounds up to `5.0` | Stored as `5.00` | **PASS** |
| **V2.6** | Exact threshold `7.9` | Avg score `7.9` | Stored as `7.90` | **PASS** |
| **V2.7** | Boundary fraction `7.875` | Rounds up to `7.9` | Stored as `7.90` | **PASS** |
| **V2.8** | Exact threshold `8.0` | Avg score `8.0` | Stored as `8.00` | **PASS** |
| **V2.9** | Exact threshold `3.4` (Thông tư 22) | Avg score `3.4` | Stored as `3.40` | **PASS** |
| **V2.10** | Exact threshold `3.5` (Thông tư 22) | Avg score `3.5` | Stored as `3.50` | **PASS** |
| **V3.1** | Only 1 TX score entered (`7.0`) | Avg score `7.0` | Stored as `7.00` | **PASS** |
| **V3.2** | Non-consecutive TX (`TX2=8, TX4=6`) | Avg score `7.0` | Stored as `7.00` | **PASS** |
| **V3.3** | Partial: `TX1=7.0, GK=8.0`, no CK | `(7 + 16)/3 = 7.7` | Stored as `7.70` | **PASS** |
| **V3.4** | Partial: Only `CK=8.5` | `(3*8.5)/3 = 8.5` | Stored as `8.50` | **PASS** |
| **V3.5** | Partial: Only `GK=9.0` | `(2*9)/2 = 9.0` | Stored as `9.00` | **PASS** |
| **V3.6** | Partial: `GK=7.0, CK=8.0`, no TX | `(14 + 24)/5 = 7.6` | Stored as `7.60` | **PASS** |
| **V3.7** | Completely empty row | Avg score evaluates to NULL | Stored as NULL | **PASS** |
| **V4.1** | Weight TX = 1 verification | Numerator delta +1.0 | Delta confirmed (+1.0) | **PASS** |
| **V4.2** | Weight GK = 2 verification | Numerator delta +2.0 | Delta confirmed (+2.0) | **PASS** |
| **V4.3** | Weight CK = 3 verification | Numerator delta +3.0 | Delta confirmed (+3.0) | **PASS** |
| **V4.4** | Weight ratio GK:TX=2, CK:TX=3 | Isolated component test | `1.0 vs 2.0 vs 3.0` confirmed | **PASS** |
| **V4.5** | Full Thông tư 22 formula test | Complex uneven scores = `8.4`| Stored as `8.40` | **PASS** |
| **V5.1** | Comma input `'8,5'` via POST | Parsed to float `8.5` | Stored as `8.50` | **PASS** |
| **V5.2** | Comma vs Dot equivalence check | Zero drift across all scores | Identical rows & avg | **PASS** |
| **V5.3** | CSV import with quoted comma | Parsed to float `8.5` | Stored as `8.50`, avg `8.4` | **PASS** |
| **V6.1** | Client regex match/reject suite | Accepts valid, rejects invalid| 100% regex conformance | **PASS** |
| **V6.2** | PHP backend vs JS client parity | 50 random score vectors parity | 50/50 vectors 100% identical | **PASS** |
| **V8.1** | Fuzz XLSX OpenXML file import | Sanitizes invalid, keeps valid | Clean upsert, avg `6.4` | **PASS** |
| **V9.1** | Teacher POST to unassigned subject | HTTP 403, 0 DB mutations | HTTP 403, 0 mutations | **PASS** |
| **V9.2** | Teacher POST to unassigned class | HTTP 403, 0 DB mutations | HTTP 403, 0 mutations | **PASS** |
| **V9.3** | Unauthenticated POST to gradebook | Redirect / block, 0 DB changes| Redirect, 0 mutations | **PASS** |
| **V7.1** | Database state restoration | Student 1 restored to baseline| Restored to baseline | **PASS** |

**Total Challenges**: 40  
**Passed**: 40 (100%)  
**Failed**: 0  

---

## 4. Unchallenged Areas

- **Milestone 5 Warning Radar & UI Redesign**: Out of scope for Milestone 4 (covered in M5).
- **Physical mobile browser touch gestures**: Verified keyboard navigation and virtual keycodes via DOM test events; physical mobile capacitive touch gestures require real mobile devices.

---

## 5. Conclusion & Verdict

The score calculation engine and input validation mechanisms in Milestone 4 (`nhap_diem.php` and `includes/xlsx_helper.php`) are **robust, mathematically correct under Thông tư 22, and resilient against injection attacks, boundary anomalies, and decimal separator discrepancies**.

Verdict: **APPROVE**.
