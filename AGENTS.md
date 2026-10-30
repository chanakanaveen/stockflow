# StockFlow repository workflow

Only implement the stage authorized by the user. Application design and implementation are separate from the initial Git setup.

After every authorized Phase 0–6 milestone:
1. Update `docs/implementation-status.md` with completed work, pending work, blockers, and actual verification results.
2. Run the checks required for that stage. Failed required checks block the stage-completion commit. Report unavailable checks honestly; do not claim the stage is verified.
3. Review the diff and staged file list. Exclude secrets, local environment files, dependencies, generated artifacts, and unrelated changes.
4. Commit the verified stage with its checkpoint message from `docs/git-workflow.md`, then push to the configured upstream. The user has authorized both commits and pushes for completed, authorized stages.
5. Verify branch tracking, local/remote commit agreement, and working-tree status. Report the commit hash, checks, and push outcome.

Never force-push, rewrite history, or overwrite remote work. If a push fails, retain the local commit, report the failure, and retry safely after resolving the cause. Stop after the authorized stage; do not infer authorization for the next application phase.
