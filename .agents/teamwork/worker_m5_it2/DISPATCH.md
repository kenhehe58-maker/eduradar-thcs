# DISPATCH — worker_m5_it2

## 2026-10-17T18:20:00Z
- **Role**: M5 Remediation Worker (Iteration 2)
- **Working Directory**: `D:\New folder\my-web-project\.agents\teamwork\worker_m5_it2`
- **Context & Mission**:
  Implement the complete Milestone 5 Iteration 2 remediation based on the syntheses from explorers `explorer_m5_it2_1`, `explorer_m5_it2_2`, and `explorer_m5_it2_3`.
  
- **MANDATORY INTEGRITY WARNING**:
  DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A teamwork_preview_auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

- **Required Reading**:
  1. `D:\New folder\my-web-project\.agents\teamwork\ORIGINAL_REQUEST.md`
  2. `D:\New folder\my-web-project\.agents\teamwork\PROJECT.md`
  3. `D:\New folder\my-web-project\.agents\teamwork\auditor_m5_1\audit.md` (Full audit evidence report)
  4. Explorer blueprints:
     - `D:\New folder\my-web-project\.agents\teamwork\explorer_m5_it2_1\analysis.md` & `handoff.md`
     - `D:\New folder\my-web-project\.agents\teamwork\explorer_m5_it2_2\analysis.md` & `handoff.md`
     - `D:\New folder\my-web-project\.agents\teamwork\explorer_m5_it2_3\analysis.md`, `handoff.md`, and `proposed_verify_m5.php`

- **Exclusive File Ownership**:
  - `includes/radar_helper.php` (NEW)
  - `index.php`
  - `assets/js/main.js`
  - `tests/verify_m5.php`

- **Concrete Implementation Tasks**:
  1. **Create `includes/radar_helper.php`**:
     - Implement `evaluateRiskZone(?float $avg, ?float $gk, ?float $ck, array $txList = []): array` per `explorer_m5_it2_1` and `explorer_m5_it2_2`.
     - Implement `calculateRadarStats(array $students): array` and `sortStudentsByRisk(array $students): array`.
     - Ensure ZERO undefined variables (fix `$isAvgWarning` -> `$isWarning` accumulator).
     - Ensure single component failures (`ck < 5.0` or `gk < 5.0`) trigger 🟡 Cần chú ý ("Thi cuối kỳ dưới trung bình", "Thi giữa kỳ dưới trung bình").
  2. **Update `index.php`**:
     - Add `require_once __DIR__ . '/includes/radar_helper.php';`.
     - Remove inline duplicate functions (`evaluateRiskZone()`, etc.) and use `includes/radar_helper.php`.
     - Resolve duplicate DOM ID at line 425: change `id="radarEmptyRow"` to `id="radarInitialEmptyRow"` so `id="radarEmptyRow"` at line 503 is uniquely owned.
  3. **Update `assets/js/main.js`**:
     - Ensure search and filter logic references the unique DOM element or defensively handles `#radarEmptyRow`.
  4. **Overhaul `tests/verify_m5.php`**:
     - Replace `tests/verify_m5.php` with `explorer_m5_it2_3/proposed_verify_m5.php`.
     - Ensure all tests 1.1–2.7 call `evaluateRiskZone()` from `includes/radar_helper.php`.
     - Ensure tests 3.1–3.4 evaluate live MySQL data and `calculateRadarStats()`.
     - Ensure tests 6.7–6.9 assert 0 PHP warnings/notices on live Apache (`http://localhost/my-web-project/index.php`).
     - Ensure test 4.7 verifies DOM ID uniqueness.

- **Verification Protocol**:
  1. Run `tests/verify_m5.php` with `D:\xam\php\php.exe tests/verify_m5.php` -> verify 49/49 PASS, 0 FAIL.
  2. Run `tests/challenge_m5_radar.php` with `D:\xam\php\php.exe tests/challenge_m5_radar.php` -> verify 42/42 PASS, 0 FAIL.
  3. Run all prior milestone regressions:
     - `D:\xam\php\php.exe tests/verify_m1.php`
     - `D:\xam\php\php.exe tests/verify_m2.php`
     - `D:\xam\php\php.exe tests/challenge_m2_rbac.php`
     - `D:\xam\php\php.exe tests/verify_m3.php`
     - `D:\xam\php\php.exe tests/verify_m4.php`
  4. Verify live Apache HTTP response contains 0 PHP warnings via curl / file_get_contents.

- **Deliverables**:
  Write `changes.md` and `handoff.md` in `D:\New folder\my-web-project\.agents\teamwork\worker_m5_it2`.
  Send a message to your parent when done.
