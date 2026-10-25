# DISPATCH — challenger_m4_1 (M4 Score Calc Challenger)

## 2026-10-17T17:05:00Z
- **Role**: teamwork_preview_challenger (M4 Score Calc Challenger)
- **Working Directory**: D:\New folder\my-web-project\.agents\teamwork\challenger_m4_1
- **Files to Read**:
  - D:\New folder\my-web-project\.agents\teamwork\ORIGINAL_REQUEST.md
  - D:\New folder\my-web-project\.agents\teamwork\PROJECT.md
  - D:\New folder\my-web-project\.agents\teamwork\worker_m4\handoff.md
  - D:\New folder\my-web-project\nhap_diem.php
- **Tasks**:
  1. Adversarially challenge score calculation and input validation:
     - Extreme and invalid scores: -0.1, 10.1, 999, non-numeric strings, SQL injections, XSS payloads.
     - Boundary scores: 0.0, 10.0, 4.9, 5.0, 7.9, 8.0, 3.4, 3.5.
     - Partial scores: only TX, TX + GK without CK, only CK, empty rows.
     - Weighting verification: verify TX weight 1, GK weight 2, CK weight 3 per Thông tư 22.
     - Comma vs dot inputs: `8,5` vs `8.5`.
  2. Empirically verify calculation correctness on live database via PHP CLI test harness.
  3. Write `challenge.md` and `handoff.md` with explicit verdict `APPROVE` or `REQUEST_CHANGES`.

## 2026-10-17T10:16:47Z
[Message from parent 391812a0-32ec-4263-b097-6162669cc4f7]
Adversarially challenge Milestone 4 score calculation and input validation:
- Extreme & invalid scores: -0.1, 10.1, 999, non-numeric strings, SQL injections, XSS payloads.
- Boundary scores: 0.0, 10.0, 4.9, 5.0, 7.9, 8.0, 3.4, 3.5.
- Partial scores: only TX, TX+GK without CK, only CK, empty rows.
- Weighting verification: verify TX weight 1, GK weight 2, CK weight 3 per Thông tư 22.
- Comma vs dot inputs: '8,5' vs '8.5'.
Empirically verify calculation correctness on live database via PHP CLI test harness.
State your verdict (APPROVE or REQUEST_CHANGES) in challenge.md and handoff.md, then send a message when done.
