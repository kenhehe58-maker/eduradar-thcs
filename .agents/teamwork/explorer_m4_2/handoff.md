# HANDOFF REPORT — M4 Score Calculation & Persistence Engine
**Agent**: `explorer_m4_2`  
**Working Directory**: `D:\New folder\my-web-project\.agents\teamwork\explorer_m4_2`  
**Handoff Type**: Hard (Task Complete)  
**Recipient**: `orchestrator_1_gen2` (Conversation ID: `391812a0-32ec-4263-b097-6162669cc4f7`)

---

## 1. OBSERVATION

1. **Live Database Schema of `scores` Table**:
   - Tool Command: `D:\xam\php\php.exe -r "require 'includes/db.php'; echo implode(',', db()->query('DESCRIBE scores')->fetchAll(PDO::FETCH_COLUMN));"`
   - Output: `id,student_id,subject,tx1,tx2,tx3,tx4,gk,ck,avg_score,updated_at`
   - Constraints from `database.sql`: `UNIQUE KEY unique_student_subject (student_id, subject)` and `CONSTRAINT fk_scores_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE`.

2. **Outdated Column References in `nhap_diem.php`**:
   - File Path: `D:\New folder\my-web-project\nhap_diem.php`
   - Lines 112–118:
     ```php
     $m1 = $row['m1'] !== '' ? (float)$row['m1'] : null;
     $m2 = $row['m2'] !== '' ? (float)$row['m2'] : null;
     $p15_1 = $row['p15_1'] !== '' ? (float)$row['p15_1'] : null;
     $p15_2 = $row['p15_2'] !== '' ? (float)$row['p15_2'] : null;
     $t1_1 = $row['t1_1'] !== '' ? (float)$row['t1_1'] : null;
     $gk = $row['gk'] !== '' ? (float)$row['gk'] : null;
     $ck = $row['ck'] !== '' ? (float)$row['ck'] : null;
     ```
   - Lines 134–146:
     ```php
     $upd = $pdo->prepare("UPDATE scores SET m1 = ?, m2 = ?, p15_1 = ?, p15_2 = ?, t1_1 = ?, gk = ?, ck = ?, avg_score = ? WHERE student_id = ? AND subject = ?");
     $ins = $pdo->prepare("INSERT INTO scores (student_id, subject, m1, m2, p15_1, p15_2, t1_1, gk, ck, avg_score) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
     ```
   - Lines 221–229:
     ```php
     SELECT s.id, s.full_name, s.parent_phone,
            sc.m1, sc.m2, sc.p15_1, sc.p15_2, sc.t1_1, sc.gk, sc.ck, sc.avg_score
     FROM students s
     LEFT JOIN scores sc ON s.id = sc.student_id AND sc.subject = ?
     WHERE s.class_id = ?
     ```
   - Verbatim SQL Error when saving: `PDOException: Unknown column 'm1' in 'field list'` because table `scores` has columns `tx1..tx4`, not `m1..t1_1`.

3. **Absence of Database Transaction in `save_scores`**:
   - In `nhap_diem.php` lines 99–154, scores are saved via a `foreach` loop without `$pdo->beginTransaction()`, `$pdo->commit()`, or `$pdo->rollBack()`. Individual rows are committed in autocommit mode, meaning a failure on row $N$ leaves rows $1..N-1$ dirty and saved.

4. **Iterative O(N) Queries in Existing Save Loop**:
   - In `nhap_diem.php` lines 103–133, each student triggers an individual query: `$chkStudent->execute([$studentId, $selectedClassId])` and `$check->execute([$studentId, $selectedSubject])`. For 45 students, this executes 90+ separate database roundtrips.

5. **Existing RBAC & BOLA Challenge Payloads**:
   - In `tests/challenge_m2_rbac.php` lines 688–693:
     ```php
     $res = $client->post('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'), [
         'action' => 'save_scores',
         'scores' => [
             99999 => ['m1' => '10.0', 'gk' => '10.0', 'ck' => '10.0']
         ]
     ]);
     ```
   - The test verifies that student 99999 is rejected by student ownership check and not inserted into `scores`.

6. **Current Test Suites Status**:
   - `D:\xam\php\php.exe tests/verify_m1.php`: Passed 12/12.
   - `D:\xam\php\php.exe tests/verify_m2.php`: Passed 16/16.
   - `D:\xam\php\php.exe tests/verify_m3.php`: Passed 28/28.

---

## 2. LOGIC CHAIN

1. **Schema Correction (Observation 1 & 2)**:
   - Because the live MySQL `scores` table has columns `tx1, tx2, tx3, tx4, gk, ck, avg_score`, any SELECT, INSERT, or UPDATE referencing `m1, m2, p15_1, p15_2, t1_1` produces a fatal PDOException.
   - Therefore, `nhap_diem.php` must be refactored to read and write `tx1, tx2, tx3, tx4, gk, ck, avg_score`.

2. **Backward Compatibility Mapping (Observation 5)**:
   - Because existing security challenge test suites (`challenge_m2_rbac.php`) send payload keys `m1, gk, ck`, the score parser must inspect `$row['tx1'] ?? $row['m1'] ?? null` (and similarly for `tx2..tx4`).
   - This ensures 0 regressions against existing test suites while supporting the modern VnEdu standard format.

3. **Transaction Safety (Observation 3)**:
   - Wrapping the save operation in `$pdo->beginTransaction()`, `$pdo->commit()`, and `$pdo->rollBack()` guarantees that either all valid student scores in the class are persisted atomically, or none are persisted if an unexpected failure or validation error occurs.

4. **Anti-BOLA Student Verification & O(1) Performance (Observation 4 & 5)**:
   - Fetching all student IDs for the target class upfront via `SELECT id FROM students WHERE class_id = ?` and hashing them into a PHP lookup map (`$validMap = array_flip($validIds)`) eliminates 90+ roundtrip queries to 1 query.
   - Furthermore, checking `if (!isset($validMap[$studentId])) continue;` strictly blocks any cross-class student ID injection (such as ID 99999 or student IDs from other classes) as required by acceptance criteria.

5. **Mathematical Precision & Client/Server Synchronization**:
   - The formula $\text{ĐTBmhk} = \frac{S_{tx} + 2 \times gk + 3 \times ck}{N_{tx} + 5}$ (and its dynamic generalization $W_{total} = N_{tx} + 2 \times [gk \ne \text{null}] + 3 \times [ck \ne \text{null}]$) guarantees identical calculations between JavaScript (real-time DOM updates) and PHP (server-side verification).
   - Rounding with `round($avg, 1)` on PHP and precision-safe `Math.round((avg + Number.EPSILON) * 10) / 10` on JS prevents floating-point divergence.

6. **Audit Trail Accountability**:
   - Calling `log_action($realUserId, $_SESSION['username'], 'Lưu sổ điểm', "Cập nhật bảng điểm môn [$selectedSubject] cho lớp [$curClassName] ($savedCount học sinh).");` immediately after `$pdo->commit()` guarantees that every gradebook modification is immutably logged with the class name, subject, and student count.

---

## 3. CAVEATS

1. **File Import & Export**: The Excel/CSV bulk import/export at the bottom of `nhap_diem.php` is within the scope of peer explorer `explorer_m4_3`. However, the calculation formula and database schema designed here must be adopted identically by `explorer_m4_3` to prevent schema conflicts.
2. **Spreadsheet UI & Keyboard Controls**: Specific CSS styling, sticky headers, and arrow-key focus management are being detailed by `explorer_m4_1`. Our client-side JS calculation logic is designed as modular functions that hook cleanly into `explorer_m4_1`'s table layout via `.score-input` and `.avg-score-cell`.
3. **Partial Semester State**: If a student has no scores entered at all, `ĐTBmhk` evaluates to `null` and is persisted as SQL `NULL` with UI display `'-'`. This is by design per Vietnamese secondary school grading conventions.

---

## 4. CONCLUSION

The score calculation engine and persistence layer in `nhap_diem.php` must be upgraded with the following concrete components:
1. **Database Persistence**: Replace all legacy `m1..t1_1` columns with `tx1, tx2, tx3, tx4, gk, ck, avg_score`. Use a single prepared `INSERT ... ON DUPLICATE KEY UPDATE` query inside a transaction.
2. **Anti-BOLA Whitelist**: Pre-load `$validMap = array_flip(valid_ids)` for `$selectedClassId` and skip any non-whitelisted student IDs.
3. **Transaction Safety**: Enforce `db()->beginTransaction()`, validate $0.0 \le \text{score} \le 10.0$ for all entries, roll back on validation error, and commit on success.
4. **Audit Logging**: Call `log_action()` post-commit with action name `'Lưu sổ điểm'`.
5. **Client-Side Engine**: Real-time row recalculation on `input`, decimal comma normalization (`8,5` -> `8.5`) on `change`, out-of-range visual errors (`.is-invalid`), and form submission block on invalid scores.

The complete architecture and drop-in code snippets are documented in detail in `analysis.md`.

---

## 5. VERIFICATION METHOD

To independently verify this design:

1. **Regression Verification of Prior Milestones**:
   Execute the automated verification suites for M1, M2, and M3 to ensure 0 regressions:
   ```powershell
   D:\xam\php\php.exe tests/verify_m1.php
   D:\xam\php\php.exe tests/verify_m2.php
   D:\xam\php\php.exe tests/verify_m3.php
   ```
   All tests must return `[PASS]` with 0 failures.

2. **Schema Verification**:
   Verify that `scores` table has columns `tx1, tx2, tx3, tx4, gk, ck, avg_score`:
   ```powershell
   D:\xam\php\php.exe -r "require 'includes/db.php'; print_r(db()->query('DESCRIBE scores')->fetchAll(PDO::FETCH_COLUMN));"
   ```

3. **Formula Mathematical Verification**:
   Verify the sample cases in Section 3.3 of `analysis.md`:
   - Full scores: `tx1=8, tx2=7, tx3=6.5, tx4=6, gk=5, ck=5` -> `avg = 5.8`.
   - Partial scores: `tx1=8, tx2=7` -> `avg = 7.5`.
   - Out of bounds: `tx1=11.0` -> rejected.

4. **Integration Verification via Milestone 4 Suite (`tests/verify_m4.php`)**:
   Once implemented by the worker/implementer, run:
   ```powershell
   D:\xam\php\php.exe tests/verify_m4.php
   ```
   Verify that Test 2 (Calculation Engine), Test 4 (Score saving with transaction and audit log), Test 5 (Out-of-bounds rejection), and Test 9 (RBAC & BOLA defense) pass with 0 failures.
