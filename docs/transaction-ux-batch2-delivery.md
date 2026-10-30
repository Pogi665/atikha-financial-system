# Consolidated transaction UX batch

October 28, 2026. Locally implemented, uncommitted and awaiting the user's inspection. This is source/browser engineering evidence, not human walkthrough completion or client acceptance.

## Baseline and preservation

Branch `main`, HEAD and cached `origin/main` `1bb6a074f99c42569c88bf73a3d2a54dd3f532d2`. The current working tree, including pre-existing dashboard/sidebar/launcher/draft polish, was the implementation baseline. Pre-task status, hashes and exact modified-file copies were preserved privately. No reset, stash, clean, commit or push was used.

Existing dashboard styles, shared sidebar behavior/scrollbar treatment, draft actions, authentication appearance and unrelated source remain intact. The participant environment is frozen, A01 remains paused/incomplete, and the finished practice environment is not part of this batch.

## Result

- Transactions has a concise Admin task hub. Its navigation label opens the hub; its disclosure independently opens children using the existing desktop rail/mobile pattern.
- Ordinary Payment/Receipt starts simple, without inline master creation or visible mode selection. Save and close remains the single acknowledged private save action. `advanced_accounting.php` deliberately opens `presentation=multiple` or `presentation=detailed` on the existing Payment/Receipt routes. Complex saved drafts reveal their required presentation without flattening independent amounts, projects or evidence allocations.
- Review uses neutral Ready to record language, labelled business fields, concise optional-document state and secondary complete Accounting details. Stale-review responses expose explicit regeneration; no automatic financial replay is introduced. Final actions flow below the summary on narrow screens.
- Recorded completion has one primary success heading, exact identity, authoritative available summary and explicit next actions. Exact readers use safe contextual nouns/amounts, friendly timestamps, complete accounting/evidence details and authorized secondary corrections.
- Cash books and Find a Transaction show one complete journal per result. Account/project matching, search, sorting and pagination select transactions without dropping counterpart lines. Safe ordinary cash amounts use the full projection; ambiguous/multiple cash movements, transfers and unclassified history keep truthful alternate summaries. Corrected originals remain included.
- Explicit Transaction list CSV, Detailed journal lines CSV and Print transaction list choices use full-result validated models. Detail selection retains every counterpart line and account code/name/type, projects and exact amounts. Existing detailed export URLs and line-oriented History JSON remain compatible; no existing detailed export becomes a lossy summary.
- General Journal keeps explicit debit/credit structure, optional party/default-project disclosure, balance status, transfer semantics and limits. Desktop rows are compact; narrow rows remain stacked. Posting services/controllers, formulas, evidence authority, permissions, CSRF, immutable history, audit, locks and period controls are unchanged.

## File scope

New production files:

- `transactions.php`
- `advanced_accounting.php`
- `includes/transaction_presentation.php`
- `includes/transaction_records_page.php`
- `includes/transaction_detail_page.php`
- `assets/css/transaction_workspace.css`
- `assets/js/transaction_records.js`
- `assets/js/transaction_detail.js`

Existing production files extended:

- `includes/nav.php`
- `new_transaction.php`
- `includes/accounting_entry_page.php`
- `assets/js/accounting_workspace.js`
- `financial_records.php`
- `journal_transaction.php`
- `includes/financial_export.php`
- `financial_export.php`

New focused tests: [pure projection oracles](../scripts/test_transaction_presentation.php) and [guarded reader/export/privacy checks](../scripts/test_transaction_readers.php). Current state and decisions 007/010 describe the new live presentation contract; historical deliveries are unchanged.

## Verification

- PHP/JavaScript syntax checks cover changed/new production code; `git diff --check` is part of final verification.
- Existing U2 ordinary, U2 coverage follow-up, U3 advance, U4 correction and U5 amount adapters: 90 / 52 / 58 / 41 / 20 assertions passed.
- New pure complete-journal presentation suite: 26 assertions passed, including multiple cash lines, unknown legacy labels, corrected originals, exact time conversion and missing/zero metadata.
- Guarded reader/export/privacy suite: 60 assertions passed on the disposable clone. Coverage includes whole-entry account/project selection, stable pagination, invalid filters, full-result transaction/detail CSV, retained legacy line export, literal search, Management privacy and anonymous rejection.
- Main focused Edge run: 133 assertions passed across ordinary save/resume/Review/post, stale-review recovery, advanced modes in both books, General Journal balance rejection/acceptance, Internal Transfer, exact/quick readers, exports, role boundaries, failed-search retention and 1366×768 / 1920×1080 / 390×844 layouts.
- Separate genuine controller checks passed payment/receipt duplicate recovery, draft discard, advance release, linked correction, budget submission/Management approval, report submission/Management review and reversible setup mutation. No fake success response is used for financial actions.
- Final affected run: 38 checks passed for Review/Recorded at all three sizes, optional-document upload/manual-review validation, quick preview, reduced-motion media and print styles. Visual inspection caught a narrow Review-bar overlap; it was corrected and retested. A further 13 flag-off checks preserved authoring gates and Management evidence filtering, then restored the verification-only configuration exactly. Source hashes, screenshots and commands are retained privately.

Automated harness retries are recorded separately. They included response-body lifetime after acknowledged navigation, waiting for loaded draft values and a predicate parameter typo. The verification fixture had only one eligible cash account; a second was prepared through the existing controller only in that clone for the required transfer case. No financial validation was weakened and no user preview fixture was changed to pass a presentation check.

## Isolation and limits

The current visual database/private runtime was snapshotted. Mutation-heavy checks ran in a separate complete-schema disposable clone of that synthetic snapshot, with its own app, sessions, evidence and blocked providers. No migration ran. No working client records were accessed or copied. Refreshing the visual application retains its current database, uploads and sessions and preserves guarded reset tools.

No accounting/client policy or production role was added. Board retirement and forecasting removal remain intact. General Ledger, Cash Advances, Budgets, Reports, Scan Receipt, Setup, User Management, Audit Trail, Email and Management dashboards/queues were not redesigned; existing shared compatibility calls remain dependencies.

Print-media screenshots do not establish physical pagination or cross-browser certification. Automated keyboard/reflow checks do not substitute for a real screen reader, physical zoom or the paused human walkthrough. Ambiguous journals intentionally lack an invented single cash amount; complete Accounting details remain available. Private evidence is retained locally rather than committed as large generated artifacts.

## Next action

Inspect the updated isolated visual sandbox. No further findings batch, working rollout, commit/push or human-task continuation is automatic.
