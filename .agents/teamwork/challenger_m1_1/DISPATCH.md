## 2026-10-17T06:54:00Z
You are challenger_m1_1 (teamwork_preview_challenger).
Working directory: D:\New folder\my-web-project\.agents\teamwork\challenger_m1_1
Project specification: D:\New folder\my-web-project\.agents\teamwork\PROJECT.md
Original user request: D:\New folder\my-web-project\.agents\teamwork\ORIGINAL_REQUEST.md
Worker handoff: D:\New folder\my-web-project\.agents\teamwork\worker_m1\handoff.md

Task:
Adversarially challenge and stress-test Milestone 1 database constraints and data layer:
1. Attempt to insert duplicate rows into live MariaDB edu_radar:
   - Duplicate class name
   - Duplicate student (class_id, full_name, birthday)
   - Duplicate teacher assignment (teacher_id, class_id, subject)
   - Duplicate score (student_id, subject)
   - Duplicate username
   Verify MySQL rejects every duplicate with a unique constraint violation exception.
2. Stress test cascading deletes:
   - Create a temp class, student, and score. Delete the class, verify student and score are cascaded.
3. Test edge case data:
   - Very long strings, null values, Vietnamese Unicode characters in student names and audit logs.
4. State your explicit verdict: APPROVE or REQUEST_CHANGES.
5. Write your findings to challenge.md and handoff.md, then notify parent.
