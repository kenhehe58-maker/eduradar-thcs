# HANDOFF REPORT: VNEDU SPECIFICATIONS & TEST ARCHITECTURE

**Agent**: `spec_miner_survey_3` (teamwork_preview_spec_miner)  
**Recipient**: `orchestrator_1` / Parent Agent (`391812a0-32ec-4263-b097-6162669cc4f7`)  
**Type**: Hard Handoff (Task Complete)  
**Artifact**: `D:\New folder\my-web-project\.agents\teamwork\spec_miner_survey_3\specifications.md`

---

## 1. Observation
1. **Target Environment & Connectivity**:
   - Running command `D:\xam\php\php.exe -m` verified native support for `zip`, `SimpleXML`, `xml`, `dom`, `PDO`, `pdo_mysql`, and `mbstring`.
   - Running command `D:\xam\php\php.exe -r "echo file_get_contents('http://localhost/my-web-project/login.php');"` returned HTTP 200 with the active login page rendered by XAMPP Apache on port 80.
   - MariaDB/MySQL is active on port 3306; database `edu_radar` contains 6 tables: `audit_logs`, `classes`, `scores`, `students`, `teacher_classes`, `users`.
   - Running command `composer -v` returned error: `The term 'composer' is not recognized`. Therefore, any Excel (`.xlsx`) generation/parsing must rely on standard PHP `ZipArchive` and `SimpleXML` without Composer package dependencies.
2. **Current Codebase Deficiencies vs ORIGINAL_REQUEST.md**:
   - `login.php:16`: Contains hardcoded password bypass `|| $password === '123456'`. `ORIGINAL_REQUEST.md` line 20 explicitly requires: *"Passwords hashed with password_hash() (remove any hardcoded password bypass)"*.
   - `students` table in MySQL (`SHOW CREATE TABLE edu_radar.students`): Missing `UNIQUE KEY (class_id, full_name, birthday)`. Only has `KEY (class_id)`. `ORIGINAL_REQUEST.md` line 53 mandates `UNIQUE(class_id, full_name, birthday)`.
   - `nhap_diem.php:66-72`: Implements outdated score columns (`m1, m2, p15_1, p15_2, t1_1, gk, ck`), whereas `ORIGINAL_REQUEST.md` R3 lines 34-36 specifies modern VnEdu THCS standard: `ĐĐGtx` (cột 1, 2, 3, 4), `ĐĐGgk` (HS 2), `ĐĐGck` (HS 3), and `ĐTBmhk` = `(Tổng ĐĐGtx + ĐĐGgk*2 + ĐĐGck*3) / (Số cột ĐĐGtx có điểm + 5)` rounded to 1 decimal place.
   - `index.php:209-211`: Renders `📋 Copy Tin Nhắn Zalo` button with clipboard JavaScript and modal. `ORIGINAL_REQUEST.md` R4 line 45 strictly instructs: *"DO NOT include the Zalo copy button or parent message generator (the user explicitly asked to hold off on the Zalo button)"*.
   - `tests/run_tests.php`: Does not exist yet in the codebase.
   - `database.sqlite`: Exists in project root (size 40,960 bytes). `ORIGINAL_REQUEST.md` lines 19 and 77 require removing all SQLite files and code.

---

## 2. Logic Chain
1. From Observation 1, native `ZipArchive` and `SimpleXML` are functional in XAMPP's PHP, while `composer` is unavailable. Therefore, the implementation for `.xlsx` import and export can be cleanly achieved via a pure-PHP helper script that reads/writes OpenXML ZIP containers directly.
2. From Observation 2 on database constraints, duplicate student rows and duplicate score rows during file imports or re-submissions can only be guaranteed to be prevented if MySQL schema includes `UNIQUE(class_id, full_name, birthday)` on `students` and `UNIQUE(student_id, subject)` on `scores`, coupled with SQL `ON DUPLICATE KEY UPDATE` / deduplication checks.
3. From Observation 2 on grade calculation, Thông tư 22/2021/TT-BGDĐT sets regular assessment (ĐĐGtx) at coefficient 1 (up to 4 scores), mid-term (ĐĐGgk) at coefficient 2, and end-of-term (ĐĐGck) at coefficient 3. The divisor equals the number of entered regular assessments plus 5 ($2+3$). In-progress scores before GK or CK are taken are normalized by dividing by the active sum of coefficients, rounded using `round($val, 1)`.
4. From Observation 2 on early-warning rules, student risk levels are categorized into 🔴 Nguy cấp ($avg < 5.0$ or latest drop $< 5.0$ or periodic exam $< 4.0$), 🟡 Cần chú ý (any component $< 5.0$, borderline $5.0-5.4$, or sharp drop $\ge 2.0$), and 🟢 Ổn định (maintained $\ge 5.0$). Removing all Zalo buttons, scripts, and toasts satisfies R4 without impacting pedagogical risk classification.
5. From Observation 2 on audit logging, state changes across authentication, administrative management, gradebook saves, imports, and access violations must insert immutable records into `audit_logs` using prepared statements capturing `user_id`, `username`, `action`, `details`, `ip_address`, and `created_at`.
6. From Observation 2 on test requirements, a comprehensive PHP CLI test script (`tests/run_tests.php`) executing multi-account HTTP sessions against Apache and inspecting MySQL directly will provide automated verification of all 8 core acceptance criteria with 0 failures.

---

## 3. Caveats
1. **Excel Serial Date Parsing**: Excel stores dates as integer day offsets from `1899-12-30` or `1900-01-01`. While standard VnEdu exports often export dates as formatted strings (`YYYY-MM-DD` or `DD/MM/YYYY`), the parser specification accounts for both text dates and numeric serials.
2. **Nullable Birthday in MySQL**: In standard SQL, multiple rows with `NULL` birthday would not conflict under a unique index. The PHP deduplication query explicitly checks `(birthday = :b OR (birthday IS NULL AND :b IS NULL))` to ensure robustness even when birthday is missing.
3. **Subject Naming**: Standard lower-secondary subjects include `Toán`, `Ngữ Văn`, `Tiếng Anh`, `KHTN`, `Lịch Sử`, `Tin Học`. Gradebook checks must enforce case-sensitive or normalized Unicode strings to prevent mismatched assignments.

---

## 4. Conclusion
All technical specifications for:
1. VnEdu student roster import format (.xlsx & .csv) with normalization and deduplication,
2. VnEdu gradebook format (.xlsx & .csv), column weights ($tx=1, gk=2, ck=3$), dynamic formula, rounding to 1 decimal place, and upsert key,
3. Early-Warning diagnostic logic (🔴 Nguy cấp, 🟡 Cần chú ý, 🟢 Ổn định) with complete elimination of Zalo artifacts,
4. Audit logging schema and 13 distinct event triggers across all user flows,
5. Acceptance test scenarios for `tests/run_tests.php`,
have been formally documented in `specifications.md`. Implementers can execute the plan directly against these unambiguous contracts.

---

## 5. Verification Method
1. **Inspect Specifications File**:
   View `D:\New folder\my-web-project\.agents\teamwork\spec_miner_survey_3\specifications.md` to verify all required sections, formula definitions, tables, and edge cases.
2. **Verify PHP Native Capability**:
   Run `D:\xam\php\php.exe -r "var_dump(class_exists('ZipArchive'), class_exists('SimpleXMLElement'));"` to verify OpenXML parsing capability.
3. **Verify Database Consistency**:
   Run `D:\xam\mysql\bin\mysql.exe -u root -e "SHOW TABLES IN edu_radar;"` to confirm target tables.
4. **Invalidation Conditions**:
   The specifications would be invalidated if:
   - MOET grading formula deviates from the $(tx_1..4 + gk*2 + ck*3) / (n_{tx} + 5)$ specification.
   - XAMPP Apache or MySQL becomes inaccessible on default ports.
