# DISPATCH — challenger_m4_2 (M4 Excel & BOLA Challenger)

## 2026-10-17T17:05:00Z
- **Role**: teamwork_preview_challenger (M4 Excel & BOLA Challenger)
- **Working Directory**: D:\New folder\my-web-project\.agents\teamwork\challenger_m4_2
- **Files to Read**:
  - D:\New folder\my-web-project\.agents\teamwork\ORIGINAL_REQUEST.md
  - D:\New folder\my-web-project\.agents\teamwork\PROJECT.md
  - D:\New folder\my-web-project\.agents\teamwork\worker_m4\handoff.md
  - D:\New folder\my-web-project\nhap_diem.php
  - D:\New folder\my-web-project\includes\xlsx_helper.php
- **Tasks**:
  1. Adversarially challenge Excel/CSV gradebook import and export:
     - Corrupted zip archives, empty sheets, missing columns, non-standard headers, out-of-range scores in Excel.
     - Verify exported .xlsx file validity (openable by ZipArchive, valid XML structure, correct student names and scores).
  2. Adversarially challenge BOLA / IDOR:
     - Forge POST save and POST import requests as teacher_toan targeting another teacher's class or unauthorized subject -> verify HTTP 403 and zero DB modification.
     - Attempt student cross-class injection in POST data (injecting student from another class into payload) -> verify rejection and atomic rollback.
  3. Write `challenge.md` and `handoff.md` with explicit verdict `APPROVE` or `REQUEST_CHANGES`.

## 2026-10-17T10:16:47Z
You are challenger_m4_2. Working directory: D:\New folder\my-web-project\.agents\teamwork\challenger_m4_2.
Read D:\New folder\my-web-project\.agents\teamwork\ORIGINAL_REQUEST.md, PROJECT.md, DISPATCH.md in your working directory, and worker_m4's handoff.md in D:\New folder\my-web-project\.agents\teamwork\worker_m4.
Adversarially challenge Milestone 4 Excel I/O and BOLA / IDOR protection:
- Excel/CSV bulk import/export: corrupted zip archives, empty sheets, missing columns, non-standard headers, out-of-range scores in Excel.
- Verify exported .xlsx file validity (openable by ZipArchive, valid XML structure, correct student names and scores).
- Adversarially challenge BOLA / IDOR: forge POST save and POST import requests as teacher_toan targeting another teacher's class or unauthorized subject -> verify HTTP 403 and zero DB modification.
- Attempt student cross-class injection in POST data (injecting student from another class into payload) -> verify rejection and atomic rollback.
State your verdict (APPROVE or REQUEST_CHANGES) in challenge.md and handoff.md, then send a message when done.
