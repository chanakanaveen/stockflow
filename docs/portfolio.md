# Portfolio description and video script

## Suggested title

StockFlow — Laravel Inventory Management & REST API

## Description

StockFlow is a multi-store inventory management demo built with Laravel, PHP, MySQL and React. It demonstrates session authentication, role and store access controls, stock receipts and issues, approval-based transfers, an application-enforced immutable stock ledger, responsive inventory screens and scoped CSV reporting. Transactional posting, idempotent retries and real MySQL concurrency tests protect stock integrity. The repository includes API documentation, automated checks and a reproducible fictional demo.

This is an independent portfolio project. No client deployment, commercial results, production throughput or measured speedup is claimed. Review [verification](phase6-verification.md) and [limitations](roadmap.md) before reuse. Publishing to Fiverr or another platform is a separate action.

## 90-second script

| Time | Screen and narration |
| --- | --- |
| 0–10s | Overview: “StockFlow is a Laravel, MySQL and React demo. All displayed data is fictional.” |
| 10–25s | Products and SF-001 balances: “Main has 100 units; Downtown has 30. Store access is enforced on the server.” |
| 25–40s | Manager creates and submits a transfer of 20: “Submission leaves stock unchanged. The creator cannot approve.” |
| 40–55s | Switch to the different approver: “Approval posts both stores in one transaction.” |
| 55–65s | Show 80/50 balances and -20/+20 ledger rows. |
| 65–75s | Reload posted detail, then show actual duplicate/concurrency test output labeled with the run date. |
| 75–85s | Show reconciliation and scoped reports without secrets or session headers. |
| 85–90s | GitHub and limitations: “Source, API examples, checks and setup instructions are available.” |

This is a script, not a recorded video. Use [demo instructions](demo.md) to capture real screens. Editing must preserve the distinction between creator and approver.
