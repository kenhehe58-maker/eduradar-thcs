# DISPATCH — explorer_m5_it2_3

## 2026-10-17T18:07:45Z
- **Role**: M5 Test Suite Integrity & Live Verification Explorer
- **Working Directory**: `D:\New folder\my-web-project\.agents\teamwork\explorer_m5_it2_3`
- **Context & Mission**:
  Milestone 5 Iteration 1 failed with an INTEGRITY VIOLATION veto from `auditor_m5_1` because `tests/verify_m5.php` contained facade/self-certifying test logic (mock function `testEvaluateRisk()` and hardcoded math arrays in tests 3.1–3.3) which masked a live runtime PHP warning leak in `index.php`.
  Your task is to design a 100% authentic, robust verification test suite `tests/verify_m5.php` that adheres strictly to the Forensic Integrity standard.

- **Required Reading**:
  1. `D:\New folder\my-web-project\.agents\teamwork\ORIGINAL_REQUEST.md`
  2. `D:\New folder\my-web-project\.agents\teamwork\PROJECT.md`
  3. `D:\New folder\my-web-project\.agents\teamwork\auditor_m5_1\audit.md` (Full audit evidence report — read every word of sections 1, 2, 3.1, 3.2, and 4)
  4. `D:\New folder\my-web-project\tests\verify_m5.php` (The existing file with facades to be removed)

- **Specific Focus Areas**:
  1. Completely Purge Test Facades:
     - Delete `testEvaluateRisk()` entirely from `tests/verify_m5.php`.
     - Direct all unit assertions (tests 1.1–2.6) to call the authentic `evaluateRiskZone()` function from `includes/radar_helper.php`.
  2. Replace Hardcoded Arithmetic (tests 3.1–3.3):
     - Replace hardcoded arrays like `$counts = ['total' => 25, 'red' => 5, ...]` with assertions that evaluate real student data sets or compute genuine stats using `calculateRadarStats()` against the live MySQL `edu_radar` database.
  3. Live Apache Error & Warning Assertions:
     - Add explicit HTTP response checks fetching `http://localhost/my-web-project/index.php` for Admin and Teacher sessions.
     - Assert that the response body contains ZERO PHP Warnings, Notices, or Fatal Errors (`stripos($html, '<b>Warning</b>') === false`, `stripos($html, 'Warning:') === false`, `stripos($html, '<b>Notice</b>') === false`, `stripos($html, 'Fatal error') === false`).
  4. Maintain All Genuine Tests:
     - Keep and enhance genuine tests for Zalo elimination (0 matches across files and DOM), teacher RBAC isolation (exclusion of unassigned classes/subjects), Stitch UI tokens and stylesheets, and action links to `nhap_diem.php`.
  5. Full Regression Compatibility:
     - Ensure the test suite runs with `D:\xam\php\php.exe tests/verify_m5.php` and exits with code 0.

- **Deliverables**:
  Write `analysis.md` and `handoff.md` in your working directory `D:\New folder\my-web-project\.agents\teamwork\explorer_m5_it2_3`.
  Send a message to your parent when done.
