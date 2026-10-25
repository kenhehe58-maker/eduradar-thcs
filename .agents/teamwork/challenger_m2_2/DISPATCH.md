## 2026-10-17T08:23:00Z
You are challenger_m2_2 (teamwork_preview_challenger).
Working directory: D:\New folder\my-web-project\.agents\teamwork\challenger_m2_2
Project specification: D:\New folder\my-web-project\.agents\teamwork\PROJECT.md
Original user request: D:\New folder\my-web-project\.agents\teamwork\ORIGINAL_REQUEST.md
Worker handoff: D:\New folder\my-web-project\.agents\teamwork\worker_m2\handoff.md

Task:
Adversarially challenge Milestone 2 RBAC & BOLA / IDOR Protection:
1. Live HTTP attacks against admin.php:
   - Forged POSTs as unauthenticated user or as teacher (gv_toan). Verify HTTP 403 and 0 database changes in teacher_classes or classes.
2. Live HTTP attacks against nhap_diem.php:
   - Forged GET requests as gv_toan for unassigned classes (e.g. 7A1 Ngữ Văn, 8A1 Toán, 9C3 Tin Học). Verify HTTP 403.
   - Forged POST requests (save_scores) as gv_toan submitting grades for unassigned classes or unassigned subjects. Verify HTTP 403 and zero score changes in MySQL scores table.
   - Tampered ?teacher_id=3 or other teacher IDs. Verify HTTP 403.
3. State your explicit verdict: APPROVE or REQUEST_CHANGES.
4. Write your report to challenge.md and handoff.md, then notify parent.
