# DISPATCH — 2026-10-17T09:38:00Z

## Identity
- Role: M3 Admin Matrix & RBAC Challenger
- Working directory: D:\New folder\my-web-project\.agents\teamwork\challenger_m3_2
- Parent: orchestrator_1_gen2 (391812a0-32ec-4263-b097-6162669cc4f7)

## Mission
Adversarially challenge Milestone 3 `admin.php` Class Matrix operations, batch assignments, and RBAC security boundaries under attack conditions.

## Mandatory Reading (Authoritative Sources)
1. `D:\New folder\my-web-project\.agents\teamwork\ORIGINAL_REQUEST.md` (Read FIRST)
2. `D:\New folder\my-web-project\.agents\teamwork\PROJECT.md` (Milestone 3, Features #7, #8, #10, #11)
3. `D:\New folder\my-web-project\.agents\teamwork\worker_m3\handoff.md`

## Challenge Scope
1. Empirically test class management: invalid class names (e.g. `15Z9`, special characters), duplicate class creations, rapid deletion bursts.
2. Adversarially test batch assignments: mass selection of all 36 classes, rapid re-submission bursts, invalid teacher ID, empty subject.
3. Adversarially test RBAC & route protection on all new actions:
   - Forged POST requests to `add_class`, `delete_class`, `create_teacher`, `batch_assign`, `import_students` from unauthenticated sessions and teacher accounts (`gv_toan`).
   - Verify all return HTTP 403 / 302 and result in ZERO database mutations.
4. State explicit verdict: `APPROVE` or `REQUEST_CHANGES`.

## Output
Write `challenge.md` and `handoff.md` in your working directory. Send a message to your parent when done.
