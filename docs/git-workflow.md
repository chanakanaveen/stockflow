# Stage commit and push workflow

After each authorized and verified milestone, create a local commit and push it to GitHub. A stage means a complete Phase 0–6 milestone, not an individual file edit.

| Stage | Commit message |
| --- | --- |
| 0 — Approved design documentation | `docs: record StockFlow architecture and implementation plan` |
| 1 — Foundation and identity | `feat: add application foundation and authentication` |
| 2 — Masters and inventory core | `feat: add inventory masters and atomic stock posting` |
| 3 — Transfers and approvals | `feat: add transfer workflow and approval controls` |
| 4 — React dashboard | `feat: add responsive inventory management interface` |
| 5 — Reporting and hardening | `feat: add reporting and security hardening` |
| 6 — Portfolio and release documentation | `docs: finalize verified demo and release documentation` |

## Required checkpoint procedure

1. Finish only the authorized stage and update implementation status with actual checks, outcomes, and unresolved risks.
2. Run the stage's required verification. Do not create a stage-completion commit while required checks fail or remain unverified.
3. Inspect `git diff --check`, `git diff`, and `git status --short`. Stage only intended files using explicit paths, then review `git diff --cached` and `git diff --cached --check` for secrets and unintended changes.
4. Commit using the stage's message. Push using `git push` once upstream tracking exists; initial setup uses `git push -u origin main`.
5. Confirm `git status --short --branch`, `git rev-parse HEAD`, and `git ls-remote origin refs/heads/main`. The local HEAD and remote main hash must match for a successful checkpoint on main.
6. Report the hash, checks, push outcome, and any remaining working-tree changes; stop for stage review.

If remote history advances, inspect and reconcile it without overwriting anyone's changes. Never force-push. If authentication, networking, or branch protection prevents pushing, preserve the local commit and report the blocker; do not claim synchronization.

The setup commit uses `chore: configure repository and stage commit workflow`. It does not represent completion or approval of Phase 0.
