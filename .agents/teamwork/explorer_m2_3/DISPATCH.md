## 2026-10-17T07:55:00Z
You are explorer_m2_3 (teamwork_preview_explorer).
Working directory: D:\New folder\my-web-project\.agents\teamwork\explorer_m2_3
Project specification: D:\New folder\my-web-project\.agents\teamwork\PROJECT.md
Original user request: D:\New folder\my-web-project\.agents\teamwork\ORIGINAL_REQUEST.md

Milestone 2 Scope: Gradebook Route Protection (nhap_diem.php) & M2 Verification Plan.
Task:
1. Inspect nhap_diem.php for BOLA / IDOR vulnerabilities:
   - Detail how a teacher can currently view or post grades for unassigned (class_id, subject) pairs.
   - Detail exact server-side validation to enforce that teachers can ONLY view and edit assigned pairs.
   - Admin can view/edit all classes and subjects.
   - Unauthorized attempts must be rejected with HTTP 403 / redirect and logged to audit_logs.
2. Design automated test suite tests/verify_m2.php testing:
   - Login verification: admin, gv_toan, gv_van succeed with '123456'.
   - Wrong passwords reject ('1234567', etc.) and no backdoor allows '123456' on other accounts.
   - Unauthenticated & teacher access to admin.php results in HTTP 403.
   - Unassigned teacher accessing nhap_diem.php (GET or POST) results in HTTP 403 / rejection.
   - Assigned teacher accessing nhap_diem.php succeeds (HTTP 200).
   - All unauthorized attempts and logins write to audit_logs.
3. Write your analysis and verification suite design to analysis.md and a handoff report to handoff.md.
