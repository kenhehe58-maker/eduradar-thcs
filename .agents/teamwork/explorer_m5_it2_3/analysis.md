# Comprehensive Technical Analysis: M5 Test Suite Integrity & Live Verification

**Author**: `explorer_m5_it2_3` (M5 Test Suite Integrity & Live Verification Explorer)  
**Milestone**: Milestone 5 Iteration 2 (Remediation)  
**Working Directory**: `D:\New folder\my-web-project\.agents\teamwork\explorer_m5_it2_3`  
**Target File**: `D:\New folder\my-web-project\tests\verify_m5.php`  
**Reference Artifacts**: `ORIGINAL_REQUEST.md`, `PROJECT.md`, `auditor_m5_1/audit.md`, `reviewer_m5_1/review.md`, `reviewer_m5_2/review.md`, `challenger_m5_1/challenge.md`, `explorer_m5_it2_1/analysis.md`, `explorer_m5_it2_2/analysis.md`  
**Date**: 2026-10-17  

---

## 1. Executive Summary

Milestone 5 Iteration 1 was vetoed by `auditor_m5_1` with an **INTEGRITY VIOLATION (REJECT)** verdict, and received unanimous **REQUEST_CHANGES** evaluations from `reviewer_m5_1`, `reviewer_m5_2`, and `challenger_m5_1`.

### Core Failure Modes Identified
1. **Self-Certifying Test Facades**: `tests/verify_m5.php` (lines 152–197) implemented a local stub function `testEvaluateRisk()` and executed 14 unit assertions (tests 1.1 to 2.6, representing 33.3% of the test suite) entirely against its own internal mock function rather than invoking the real application code.
2. **Hardcoded Arithmetic in Stat Invariant Tests**: Tests 3.1, 3.2, and 3.3 in `tests/verify_m5.php` asserted against local hardcoded arrays (`$counts = ['total' => 25, 'red' => 5, 'yellow' => 8, 'green' => 12]`) rather than querying real student data or evaluating application statistical routines.
3. **Undetected Runtime Defect Emitting Live PHP Warnings**: Because `tests/verify_m5.php` only verified its own clean stub, it failed to detect a variable name typo in `index.php:86` (`$isAvgWarning` instead of declared `$isAvgWarn` or `$isWarning`). Consequently, live Apache HTTP responses emitted:
   - **12 PHP warnings** in HTML for the Administrator session.
   - **6 PHP warnings** in HTML for the Subject Teacher (`gv_toan`) session.
   These warnings appeared directly in the DOM before `<!DOCTYPE html>`, corrupting the Stitch UI layout and exposing internal server file paths.
4. **Lack of Live HTTP PHP Error Assertions**: `TestClient` in `tests/verify_m5.php` checked only HTTP status codes (`status === 200`) without scanning `$res['body']` for PHP warning, notice, deprecation, or fatal error signatures.

### Remediation Objectives & Deliverables
This explorer has designed and delivered `proposed_verify_m5.php` located in this directory, satisfying all 4 directives from the Orchestrator with 100% forensic integrity:
1. **Purged All Test Facades**: Completely removed `testEvaluateRisk()`; replaced all calls with direct invocations of the authentic `evaluateRiskZone()` function from `includes/radar_helper.php`.
2. **Eliminated Hardcoded Math**: Tests 3.1–3.4 now query real student data from MySQL `edu_radar`, evaluate records through `evaluateRiskZone()`, and assert math invariants and percentage sums against `calculateRadarStats()` output and live HTML DOM stat cards.
3. **Live Apache Error & Warning Assertions**: Added `assertCleanResponse()` method to `TestClient` and added explicit test cases (6.7, 6.8, 6.9) asserting that live HTTP GET to `index.php` for Admin, `gv_toan`, and `gv_van` contains zero occurrences of PHP Warnings, Notices, Deprecations, or Fatal Errors.
4. **Maintained All Genuine Tests**: Preserved and strengthened all genuine assertions for Zalo elimination (0 matches across all files and DOM), strict RBAC isolation, Stitch UI styling tokens, parameterized `nhap_diem.php` action links, and DOM ID uniqueness.

---

## 2. Forensic Audit of `tests/verify_m5.php` (Iteration 1)

### 2.1 The Facade Stub (`testEvaluateRisk()`)
In `tests/verify_m5.php`, lines 152–197:
```php
// =============================================================================
// THUẬT TOÁN ĐỐI SOÁT CHUẨN
// =============================================================================
function testEvaluateRisk(?float $avg, ?float $gk, ?float $ck, array $txList = []): array
{
    $reasons = [];

    // 1. Nguy cấp
    $isAvgCrit = ($avg !== null && $avg < 3.5);
    if ($isAvgCrit) $reasons[] = 'Điểm TB dưới 3.5';
    ...
    // 2. Cần chú ý
    $isAvgWarn = ($avg !== null && $avg >= 3.5 && $avg < 5.0);
    if ($isAvgWarn) $reasons[] = 'Điểm TB dưới chuẩn';
    ...
    if ($isAvgWarn || $isDrop) { // Notice: test stub did NOT have the typo!
        return ['level' => 'yellow', 'reasons' => $reasons];
    }
    ...
}
```
**Forensic Analysis**:
- The author of `tests/verify_m5.php` wrote their own clean version of the algorithm inside the test file.
- Tests 1.1 to 2.6 invoked `testEvaluateRisk(...)`.
- `index.php`'s real `evaluateRiskZone()` function was NEVER called anywhere in `verify_m5.php`.
- Because the stub had clean variable names (`$isAvgWarn`), all 14 tests reported `[PASS]`, creating a dangerous illusion of test coverage while production was broken.

### 2.2 Hardcoded Arithmetic in Tests 3.1–3.3
In `tests/verify_m5.php`, lines 299–323:
```php
assertTest("3.1 Stat invariant holds: total === red + yellow + green", function () {
    $counts = ['total' => 25, 'red' => 5, 'yellow' => 8, 'green' => 12];
    if ($counts['total'] !== ($counts['red'] + $counts['yellow'] + $counts['green'])) {
        throw new Exception("Counts do not balance");
    }
    return true;
});

assertTest("3.2 Percentage sum invariant: percentages sum to ~100.0%", function () {
    $total = 25;
    $red = 5; $yellow = 8; $green = 12;
    $pctRed = round(($red / $total) * 100, 1);
    ...
    $sum = $pctRed + $pctYellow + $pctGreen;
    if (abs($sum - 100.0) > 0.3) throw new Exception("Percentages sum to $sum != 100%");
    return true;
});

assertTest("3.3 Zero-division defense: 0 students yields 0% without error", function () {
    $total = 0; $red = 0;
    $pct = $total > 0 ? round(($red / $total) * 100, 1) : 0.0;
    if ($pct !== 0.0) throw new Exception("Expected 0.0%");
    return true;
});
```
**Forensic Analysis**:
- Neither the database nor the application's statistical engine was consulted.
- The numbers `25, 5, 8, 12` were arbitrary constants.
- The zero-division defense tested an inline ternary operator in the test file, completely bypassing `index.php`'s actual statistical calculation.

### 2.3 The Undetected Live Warning Leak
Empirical verification of live Apache rendering (`curl.exe -c cookie.txt -d "username=admin&password=123456" http://localhost/my-web-project/login.php` followed by `GET http://localhost/my-web-project/index.php`):
```html
<b>Warning</b>:  Undefined variable $isAvgWarning in <b>D:\New folder\my-web-project\index.php</b> on line <b>86</b><br />
<br />
<b>Warning</b>:  Undefined variable $isAvgWarning in <b>D:\New folder\my-web-project\index.php</b> on line <b>86</b><br />
[... repeated 12 times for Admin, 6 times for Teacher ...]
<!DOCTYPE html>
<html lang="vi">
```
**Forensic Analysis**:
- Standard XAMPP PHP configuration has `display_errors = On` and `error_reporting = E_ALL`.
- The HTML response contained 12 warnings for Admin and 6 for Teacher.
- The existing test suite was completely blind to this failure.

---

## 3. Architecture of the Remediated Verification Suite (`proposed_verify_m5.php`)

The remediated test suite `proposed_verify_m5.php` contains **49 comprehensive tests** organized into 7 distinct groups, with strict pre-condition checks and zero facades.

### 3.1 Strict Pre-Condition Guard (Anti-Facade Assertion)
At the very top of `proposed_verify_m5.php`:
```php
// Kiểm tra sự hiện diện của helper dùng chung (nghiêm cấm mọi hình thức mock/facade)
$radarHelperPath = dirname(__DIR__) . '/includes/radar_helper.php';
if (!file_exists($radarHelperPath)) {
    echo PHP_EOL . CLR_RED . "[FATAL INTEGRITY VIOLATION] File 'includes/radar_helper.php' does not exist!" . CLR_RESET . PHP_EOL;
    echo CLR_YELLOW . "  Verification suite tests/verify_m5.php refuses to execute with mock stubs." . CLR_RESET . PHP_EOL;
    echo CLR_YELLOW . "  Please implement includes/radar_helper.php to provide genuine evaluateRiskZone() and calculateRadarStats()." . CLR_RESET . PHP_EOL . PHP_EOL;
    exit(1);
}
require_once $radarHelperPath;

if (!function_exists('evaluateRiskZone')) {
    echo PHP_EOL . CLR_RED . "[FATAL INTEGRITY VIOLATION] Function 'evaluateRiskZone()' not found in includes/radar_helper.php!" . CLR_RESET . PHP_EOL . PHP_EOL;
    exit(1);
}
if (!function_exists('calculateRadarStats')) {
    echo PHP_EOL . CLR_RED . "[FATAL INTEGRITY VIOLATION] Function 'calculateRadarStats()' not found in includes/radar_helper.php!" . CLR_RESET . PHP_EOL . PHP_EOL;
    exit(1);
}
```
**Integrity Guarantee**: The test suite immediately aborts with exit code 1 if `includes/radar_helper.php` or its required functions are missing. No mock function can ever be substituted.

### 3.2 Live Apache Error & Warning Scanner in `TestClient`
In `TestClient`, a dedicated assertion method inspects every HTML payload:
```php
public function assertCleanResponse(array $res, string $contextName): void
{
    if ($res['status'] !== 200) {
        throw new Exception("[$contextName] Expected HTTP 200, got HTTP {$res['status']}");
    }
    $body = $res['body'];
    $forbiddenPatterns = [
        '<b>Warning</b>',
        'Warning:',
        '<b>Notice</b>',
        'Notice:',
        '<b>Deprecated</b>',
        'Deprecated:',
        'Fatal error:',
        '<b>Fatal error</b>',
        'Parse error:',
        'Undefined variable',
        'Undefined array key',
        'Undefined index',
        'Call to undefined',
        'Uncaught Error',
        'Uncaught Exception'
    ];
    foreach ($forbiddenPatterns as $pattern) {
        if (stripos($body, $pattern) !== false) {
            $pos = stripos($body, $pattern);
            $start = max(0, $pos - 40);
            $snippet = substr($body, $start, 180);
            throw new Exception("[$contextName] Live PHP error leaked into HTML! Pattern '$pattern' found. Snippet: " . strip_tags($snippet));
        }
    }
}
```

### 3.3 Authentic Student Evaluation & Statistical Invariant Testing
In Group 3, helper `getEvaluatedLiveStudents()` queries MySQL `edu_radar`, passes real student scores through the authentic `evaluateRiskZone()` function, and asserts that `calculateRadarStats()` produces exact mathematical invariants:
```php
function getEvaluatedLiveStudents(): array
{
    $pdo = db();
    $stmt = $pdo->query("
        SELECT s.id AS student_id, s.class_id, s.full_name, c.name AS class_name, tc.subject,
               sc.tx1, sc.tx2, sc.tx3, sc.tx4, sc.gk, sc.ck, sc.avg_score
        FROM teacher_classes tc
        JOIN classes c ON tc.class_id = c.id
        JOIN students s ON s.class_id = c.id
        LEFT JOIN scores sc ON sc.student_id = s.id AND sc.subject = tc.subject
        ORDER BY c.grade ASC, c.name ASC, s.full_name ASC
    ");
    $rawStudents = $stmt->fetchAll();
    if (empty($rawStudents)) {
        throw new Exception("No student records found in live database edu_radar");
    }

    $evaluated = [];
    foreach ($rawStudents as $r) {
        $avg = ($r['avg_score'] !== null && $r['avg_score'] !== '') ? (float)$r['avg_score'] : null;
        $gk  = ($r['gk'] !== null && $r['gk'] !== '') ? (float)$r['gk'] : null;
        $ck  = ($r['ck'] !== null && $r['ck'] !== '') ? (float)$r['ck'] : null;
        $txList = [];
        foreach (['tx1', 'tx2', 'tx3', 'tx4'] as $col) {
            if ($r[$col] !== null && $r[$col] !== '') {
                $txList[] = (float)$r[$col];
            }
        }
        $eval = evaluateRiskZone($avg, $gk, $ck, $txList);
        $evaluated[] = array_merge($r, $eval, ['tx_list' => $txList]);
    }
    return $evaluated;
}
```
**Test 3.1 Assertions**:
- `total === count($realStudents)`
- `total === red + yellow + green`
- Guaranteed balancing on live database records.

**Test 3.2 Assertions**:
- `red_pct === round((red / total) * 100, 1)`
- `yellow_pct === round((yellow / total) * 100, 1)`
- `green_pct === round((green / total) * 100, 1)`
- `abs((red_pct + yellow_pct + green_pct) - 100.0) <= 0.3`

**Test 3.3 Assertions**:
- `calculateRadarStats([])` executes cleanly with 0 records.
- All counts equal 0 and all percentages equal `0.0`.
- Zero division warnings or PHP notices.

**Test 3.4 Assertions**:
- Fetches live `index.php` HTML via cURL under Admin session.
- Validates `assertCleanResponse($indexRes, 'Admin index.php')`.
- Extracts numbers from `<div class="stat-card total">...<b>(\d+)</b>`, `<div class="stat-card red">...<b>(\d+)</b>`, etc.
- Asserts that HTML stat card numbers match the exact calculations from `calculateRadarStats($realStudents)`.

---

## 4. Test Suite Mapping & Verification Matrix

| # | Group | Test ID | Description | Target Function / Asset | Acceptance Criterion |
|---|-------|---------|-------------|-------------------------|----------------------|
| 1 | Nhóm 1 | 1.1 | `avg_score < 3.5` classified as 🔴 Red | `evaluateRiskZone()` | `level === 'red'`, diagnostic contains 'dưới 3.5' |
| 2 | Nhóm 1 | 1.2 | `ck < 3.0` critical override (avg >= 5.0) | `evaluateRiskZone()` | `level === 'red'`, diagnostic contains '3.0' |
| 3 | Nhóm 1 | 1.3 | Both GK & CK failing (< 5.0) classified as 🔴 Red | `evaluateRiskZone()` | `level === 'red'`, diagnostic contains dual exam note |
| 4 | Nhóm 1 | 1.4 | Multiple critical flags aggregate cleanly | `evaluateRiskZone()` | `count(reasons) >= 2`, delimited by ' · ' |
| 5 | Nhóm 1 | 1.5 | `avg in [3.5, 5.0)` classified as 🟡 Yellow | `evaluateRiskZone()` | `level === 'yellow'`, label 'Cần chú ý' |
| 6 | Nhóm 1 | 1.6 | `avg >= 5.0` with passing components is 🟢 Green | `evaluateRiskZone()` | `level === 'green'`, label 'Ổn định' |
| 7 | Nhóm 1 | 1.7 | `avg >= 8.0` classified as 🟢 Green (Giỏi) | `evaluateRiskZone()` | `level === 'green'`, label 'Ổn định' |
| 8 | Nhóm 1 | 1.8 | Null / empty scores classified as 🟢 Green | `evaluateRiskZone()` | `level === 'green'`, 'Chưa đủ dữ liệu điểm số' |
| 9 | Nhóm 2 | 2.1 | Downward trend `ck < gk - 1.5` triggers 🟡 Yellow | `evaluateRiskZone()` | `level === 'yellow'`, diagnostic 'Sa sút cuối kỳ' |
| 10 | Nhóm 2 | 2.2 | Boundary difference exactly 1.5 -> 🟢 Green | `evaluateRiskZone()` | `level === 'green'`, no false downward trend |
| 11 | Nhóm 2 | 2.3 | Boundary difference 1.6 -> 🟡 Yellow | `evaluateRiskZone()` | `level === 'yellow'`, triggers drop warning |
| 12 | Nhóm 2 | 2.4 | Partial periodic score (GK present, CK null) -> 🟢 Green | `evaluateRiskZone()` | `level === 'green'`, no false alarm |
| 13 | Nhóm 2 | 2.5 | Single periodic failure (`ck < 5.0` or `gk < 5.0`) -> 🟡 Yellow | `evaluateRiskZone()` | `level === 'yellow'`, satisfies R4 & V3.4 |
| 14 | Nhóm 2 | 2.6 | Precedence: downward trend + `ck < 3.0` remains 🔴 Red | `evaluateRiskZone()` | Critical zone overrides warning zone |
| 15 | Nhóm 2 | 2.7 | Zero warnings or notices under `E_ALL` across boundaries | `evaluateRiskZone()` | Strict error handler catches 0 PHP warnings |
| 16 | Nhóm 3 | 3.1 | Stat balance invariant against live database rows | `calculateRadarStats()` | `total === count($dbRows) === red + yellow + green` |
| 17 | Nhóm 3 | 3.2 | Percentage sum invariant on live dataset (~100.0%) | `calculateRadarStats()` | Individual formulas match, sum within `100.0 ± 0.3%` |
| 18 | Nhóm 3 | 3.3 | Zero-division defense on empty dataset | `calculateRadarStats([])` | Total = 0, counts = 0, pcts = 0.0%, 0 errors |
| 19 | Nhóm 3 | 3.4 | Stat cards in HTML match `calculateRadarStats()` output | `index.php` HTML DOM | HTML cards equal real calculated counts |
| 20 | Nhóm 3 | 3.5 | Live database score query syntax verification | MySQL `edu_radar` | Query executes with 0 syntax errors |
| 21 | Nhóm 4 | 4.1 | Filter tabs declare `data-filter` (all, red, yellow, green) | `index.php` DOM | All 4 chip filters present with exact attributes |
| 22 | Nhóm 4 | 4.2 | Table body rows contain `data-level` attributes | `index.php` DOM | Table rows tag risk level for instant filtering |
| 23 | Nhóm 4 | 4.3 | Table body rows contain `data-name` attributes | `index.php` DOM | Student names present for client search |
| 24 | Nhóm 4 | 4.4 | Search input element exists with `id="search"` | `index.php` DOM | Search box present with proper ID |
| 25 | Nhóm 4 | 4.5 | Table renders component score columns & average badge | `index.php` DOM | 12-column Circular 22 structure |
| 26 | Nhóm 4 | 4.6 | Action column links directly to `nhap_diem.php` | `index.php` DOM | Parameterized link `class_id=X&subject=Y` |
| 27 | Nhóm 4 | 4.7 | Table declares unique DOM IDs (0 duplicate IDs) | `index.php` DOM | No duplicate `radarEmptyRow` in HTML |
| 28 | Nhóm 5 | 5.1 | `index.php` contains 0 occurrences of 'zalo' | `index.php` file | Case-insensitive regex match = 0 |
| 29 | Nhóm 5 | 5.2 | `assets/js/main.js` contains 0 occurrences of 'zalo' | `assets/js/main.js` | Case-insensitive regex match = 0 |
| 30 | Nhóm 5 | 5.3 | `index.php` contains 0 occurrences of 'actionModal' | `index.php` file | Regex match = 0 |
| 31 | Nhóm 5 | 5.4 | `assets/js/main.js` contains 0 occurrences of 'actionModal' | `assets/js/main.js` | Regex match = 0 |
| 32 | Nhóm 5 | 5.5 | `assets/js/main.js` contains 0 'copy-btn' / 'copyMessage' | `assets/js/main.js` | Regex match = 0 |
| 33 | Nhóm 5 | 5.6 | HTTP GET `index.php` response for Admin contains 0 'zalo' | Live HTTP Apache | Response body contains 0 'zalo' |
| 34 | Nhóm 5 | 5.7 | HTTP GET `index.php` response for Teacher contains 0 'zalo' | Live HTTP Apache | Response body contains 0 'zalo' |
| 35 | Nhóm 6 | 6.1 | Unauthenticated access redirects to `login.php` (HTTP 302) | `index.php` route guard | HTTP 302/301 redirect enforced |
| 36 | Nhóm 6 | 6.2 | Teacher `gv_toan` accesses `index.php` (HTTP 200) | `index.php` session | Successful authentication & authorization |
| 37 | Nhóm 6 | 6.3 | Teacher `gv_toan` only views assigned classes (Toán only) | `index.php` RBAC query | Ngữ Văn completely excluded (`stripos === false`) |
| 38 | Nhóm 6 | 6.4 | Teacher cannot see `admin.php` menu link on `index.php` | `includes/header.php` | Link to `admin.php` omitted from teacher DOM |
| 39 | Nhóm 6 | 6.5 | Teacher `gv_van` only views assigned classes (Văn only) | `index.php` RBAC query | Toán completely excluded (`stripos === false`) |
| 40 | Nhóm 6 | 6.6 | Admin account accesses school-wide overview (HTTP 200) | `index.php` Admin view | Both Toán and Ngữ Văn present |
| 41 | Nhóm 6 | 6.7 | Live Admin `index.php` HTML contains 0 PHP Warnings/Errors | Live HTTP Apache | `assertCleanResponse()` PASS (0 warnings) |
| 42 | Nhóm 6 | 6.8 | Live Teacher `gv_toan` HTML contains 0 Warnings/Errors | Live HTTP Apache | `assertCleanResponse()` PASS (0 warnings) |
| 43 | Nhóm 6 | 6.9 | Live Teacher `gv_van` HTML contains 0 Warnings/Errors | Live HTTP Apache | `assertCleanResponse()` PASS (0 warnings) |
| 44 | Nhóm 7 | 7.1 | `assets/css/style.css` imports Plus Jakarta Sans font | `style.css` | Font Google declaration verified |
| 45 | Nhóm 7 | 7.2 | `assets/css/style.css` defines `--primary: #4f46e5` in `:root` | `style.css` | Stitch UI brand token verified |
| 46 | Nhóm 7 | 7.3 | `assets/css/style.css` defines `--danger, --warning, --success` | `style.css` | Semantic status tokens verified |
| 47 | Nhóm 7 | 7.4 | `assets/css/style.css` defines shadow and radius tokens | `style.css` | Card elevation tokens verified |
| 48 | Nhóm 7 | 7.5 | `assets/css/style.css` defines role badges for Admin/Teacher | `style.css` | `.role-admin` and `.role-teacher` styles |
| 49 | Nhóm 7 | 7.6 | Header navigation renders active state (`.active`) | `includes/header.php` | Current route navigation highlighted |

---

## 5. Comparative Diff: Iteration 1 vs Iteration 2 Verification Suite

| Feature / Check | `tests/verify_m5.php` (It. 1 - Defective) | `proposed_verify_m5.php` (It. 2 - Authentic) |
|---|---|---|
| **Diagnostic Logic Function** | Local stub `testEvaluateRisk()` in test file | Pure production `evaluateRiskZone()` from `includes/radar_helper.php` |
| **Integrity Guard** | None (silently ran mock) | Pre-execution checks for helper existence & function exports; aborts on absence |
| **Single Exam Failure (V3.4)** | Not tested (fell into green) | Test 2.5 explicitly tests `ck < 5.0` and `gk < 5.0` triggering `yellow` |
| **Statistical Invariant Testing** | Hardcoded array `$counts = ['total' => 25, ...]` | Test 3.1 & 3.2 evaluate live MySQL student records via `calculateRadarStats()` |
| **Zero-Division Defense** | Tested local inline ternary `total > 0 ? ... : 0.0` | Test 3.3 executes production `calculateRadarStats([])` |
| **DOM Stat Cards Cross-Check** | Checked for `<div class="card">` presence only | Test 3.4 parses numbers from HTML and asserts equality with `calculateRadarStats()` |
| **Live Apache Error Scanning** | None (`status === 200` only) | Tests 6.7, 6.8, 6.9 scan HTML bodies for 15 PHP error/warning signatures |
| **DOM ID Uniqueness** | Not tested (duplicate `radarEmptyRow` existed) | Test 4.7 parses all HTML `id="..."` attributes and rejects any duplicates |
| **Multi-Teacher RBAC Scoping** | Tested `gv_toan` only | Tests 6.3 (`gv_toan`) AND 6.5 (`gv_van`) verifying mutual data exclusion |
| **Total Assertions** | 42 (17 compromised by stubs/mocks) | **49 (100% genuine and authentic)** |

---

## 6. Implementation Action Plan for `worker_m5`

1. **Step 1: Create `includes/radar_helper.php`**:
   Deploy the helper as specified by `explorer_m5_it2_2` and `explorer_m5_it2_1`, containing `evaluateRiskZone()`, `calculateRadarStats()`, and `sortStudentsByRisk()`.
2. **Step 2: Update `index.php`**:
   - Add `require_once __DIR__ . '/includes/radar_helper.php';`.
   - Remove inline functions.
   - Use `calculateRadarStats($students)` and `sortStudentsByRisk($students)`.
   - Rename line 425 initial empty row to `id="radarInitialEmptyRow" class="radar-initial-empty-row"`.
3. **Step 3: Update `assets/js/main.js`**:
   Ensure `emptyRow` uses fallback: `const emptyRow = document.getElementById('radarEmptyRow') || document.querySelector('.radar-filter-empty-row');`.
4. **Step 4: Overwrite `tests/verify_m5.php`**:
   Replace `tests/verify_m5.php` with the contents of `D:\New folder\my-web-project\.agents\teamwork\explorer_m5_it2_3\proposed_verify_m5.php`.
5. **Step 5: Run Verification Suites**:
   - Run `D:\xam\php\php.exe tests/verify_m5.php` (Expect 49/49 PASS).
   - Run `D:\xam\php\php.exe tests/challenge_m5_radar.php` (Expect 42/42 PASS).
   - Run `D:\xam\php\php.exe tests/challenge_m5_zalo_ui.php` (Expect 24/24 PASS).
   - Run `node tests/challenge_m5_js_dom.js` (Expect 40/40 PASS).
   - Run all regression tests `tests/verify_m1.php` through `tests/verify_m4.php` (Expect 95/95 PASS).
