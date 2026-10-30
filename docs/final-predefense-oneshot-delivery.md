# Final pre-defense one-shot delivery

October 29, 2026. Implementation of the approved [governing plan](final-predefense-oneshot-plan.md), including the authoritative V2 dataset steering, is complete in the **actual local application source**. This is an implementation/verification record, not external source approval, working deployment, provider acceptance or Atikha acceptance.

## Baseline and preservation

Branch `main`; local HEAD, cached `origin/main` and live remote main rechecked at `1bb6a074f99c42569c88bf73a3d2a54dd3f532d2`. The starting dirty layer contained 18 modified and 13 new files. It was retained and extended, with byte snapshots and its original patch; no reset, stash, clean, rebase, staging, commit or push.

The task-start manifest contains 1,125 source/artifact hashes and 1,912 protected runtime/configuration hashes. The protected runtime/configuration comparison is exact: zero changes. Existing authentication templates/carousel, authentication flow and financial engines remain byte-identical to the task-start source. The account writers/reset resolver and new export/advisory adapters are the explicitly approved exceptions to presentation-only work.

No connection to, migration on, fixture import into or financial posting to `atikha_finance` occurred. The preserved participant database `atikha_test_stage1_s5human4bd8c99474f4` and finished defense database `atikha_test_stage1_s5defense770ad398019b`, their applications/sessions/evidence/mailbox, were not used or modified. Protected working-table fingerprints were deliberately not recomputed by opening those databases; the claim is task isolation plus runtime/source preservation, not an independent recertification of live financial data.

## P0–P8 outcomes

- **P0:** current-local source, existing dirty work, protected files and original sandbox starting snapshot preserved. Original `127.0.0.1:59912` was left unchanged; it is not the final V2 demo.
- **P1:** additive 026 tested once on the owned QA database. Complete predecessor schema and restrictive designation constraints verified. Existing 52 triggers unchanged; no primary triggers added. Native PDF/XLSX dependencies locked and tested without raising the declared PHP minimum or changing global configuration.
- **P2:** shared friendly dates/explicit time basis, confirmation dialog, responsive operational tables and export controls; existing rail/flyout/persistence/mobile navigation retained. Keyboard retesting found and corrected backwards focus wrapping in confirmation dialogs.
- **P3:** existing transaction work preserved; real Payment/Receipt Save–Review–Record/exact-result round trips pass. Receipt intake integrates current immutable manual review with the genuine provider-unavailable path and General Journal handoff. Compact advances retain full lifecycle/reconciliation/coverage restrictions.
- **P4:** row-based budget allocation, blank versus zero, duplicate validation and exact revision context; reports/setup sections, native PDF/Excel and preserved CSV/Print/snapshot routes. Financial services and formulas were reused, not rewritten.
- **P5:** separate deterministic Planning & Forecasting, independent overall/granular estimates, partial-history disclosure, selected server-verified private Suggested Budget Baseline; Admin-only explainable Audit Alerts. Legacy forecast/audit AI POSTs return protected 410, without provider calls. Existing notifications reused; no 027/new persistence or GET notification writes.
- **P6:** Management & Board display wording without changing stored roles; Primary Administrator badge/protected actions; focused master/account/user editors and clearer audit changes. Email retains all delivery-state/recovery semantics. A final viewport check found that its existing action bar was a `div`, not a `footer`; the scoped layout now fixes desktop shell height and targets the real action bar, keeping it in view.
- **P7:** focused units, genuine domain/HTTP/browser mutations, negative privacy/role/CSRF, initialization race/FK/partial-state, financial oracles, binaries, responsive/keyboard/dialog/print/performance checks completed. Earlier failed checks remain in private execution evidence and are not represented as original passes.
- **P8:** actual source and disposable app hashes reconciled, populated demo reset/restart verified, review package/manifests/patch reconstruction prepared. External review and owner inspection remain next.

## Feature status

Implemented in local source: shared polish, Transactions, integrated manual OCR review, compact Cash Advances, row Budgets, Reports/setup, scoped Email, Accounting Setup, Chart of Accounts, User Management, Primary Administrator protection, Audit Trail/Alerts, deterministic Planning, explicit Suggested Budget Baseline, existing lifecycle Notifications, real PDF/XLSX and Management & Board oversight.

Intentional coverage limits: OCR/provider success and email delivery are not demonstrated by the isolated preview. Audit Alerts do not claim unsupported failed-authentication, operating-hours or monetary-change monitoring. No new formal human study is required by the final approved plan; archived participant A01 remains paused/incomplete and is not relabelled successful.

## Actual files

The exact final-byte manifest and both patch layers are in the private review package. The production/operational files added or changed **since this task began** are:

- `accounting_drafts.php`
- `accounting_setup.php`
- `admin_accounts.php`
- `admin_users.php`
- `assets/css/accounting_workspace.css`
- `assets/css/admin_accounts.css`
- `assets/css/atikha_ui.css`
- `assets/css/audit_trail.css`
- `assets/css/budget_workspace.css`
- `assets/css/external_email.css`
- `assets/css/financial_reports.css`
- `assets/css/general_ledger.css`
- `assets/css/ocr_expense.css`
- `assets/css/planning.css`
- `assets/js/accounting_drafts.js`
- `assets/js/accounting_setup.js`
- `assets/js/admin_accounts.js`
- `assets/js/admin_users.js`
- `assets/js/atikha_ui.js`
- `assets/js/audit_trail.js`
- `assets/js/budget_workspace.js`
- `assets/js/cash_advances.js`
- `assets/js/evidence_item_review.js`
- `assets/js/general_ledger.js`
- `assets/js/ocr_expense.js`
- `assets/js/planning.js`
- `assets/js/report_setup.js`
- `assets/js/transaction_records.js`
- `audit_ai.php`
- `audit_alerts.php`
- `audit_trail.php`
- `budgets.php`
- `cash_advances.php`
- `dashboard.php`
- `external_email.php`
- `financial_export.php`
- `financial_statements.php`
- `forecast_ai.php`
- `general_ledger.php`
- `includes/accounting_entry_page.php`
- `includes/advisory_policy.php`
- `includes/audit_alerts.php`
- `includes/financial_export.php`
- `includes/nav.php`
- `includes/planning_methods.php`
- `includes/planning_query.php`
- `includes/presentation.php`
- `includes/primary_administrator.php`
- `includes/receipt_review_view.php`
- `includes/report_component_view.php`
- `includes/report_export_binary.php`
- `includes/report_export_pdf_view.php`
- `includes/user_roles.php`
- `includes/users.php`
- `management_reviews.php`
- `migrations/026_primary_administrator.sql`
- `ocr_expense.php`
- `planning.php`
- `planning_actions.php`
- `report_bundles.php`
- `report_setup.php`
- `reports.php`
- `resolve_reset.php`

New guarded fixture/test/operational scripts:

- `scripts/initialize_primary_administrator.php`
- `scripts/preflight_predefense.php`
- `scripts/seed_predefense_demo.php`
- `scripts/test_predefense_advisories.php`
- `scripts/test_predefense_binary_exports.php`
- `scripts/test_predefense_demo.php`
- `scripts/test_primary_administrator.php`

Documentation/input changes include the delivery/current-state/brief, decision index and role/presentation/report/budget/communications records, new decisions 013/014, and the user-supplied `docs/atikha-demo-dataset-v2-full.zip`. The supplied ZIP is steering evidence, not a generated review archive. Previously dirty transaction/polish files are retained in the combined baseline-to-final patch even where this task made no further edit. No historical migration, frozen delivery/package, authentication image, global configuration or financial service was normalized/replaced.

## Schema and dependencies

Owned QA database: `atikha_test_predefense_df09bc0241a3`; complete schema-only/synthetic provenance from the preserved disposable rehearsal source, zero working data copied. Applied only `026_primary_administrator.sql` after absence/predecessor guards. Result: 51 tables, 52 triggers, zero cross-schema foreign keys; Stage 1/2/3, reporting, budget and period guards pass.

Owned V2 demo: `atikha_test_predefense_demo_35d1b3549780`; schema-only copy of that verified QA schema, then explicit empty coordination records and fictional domain seeding. It did not replay a migration. Separate race-only schema target: `atikha_test_predefense_race_e589c8bd0137`. Concurrent initialization yielded exactly one designation and one audit record; identical winner retry recovered, conflicting initializer was refused. Singleton ID, referenced-user deletion and invalid FK were rejected; deliberate partial constraint state was detected and restored only on that race target.

**015, 019, 020 and 021 were not replayed. No protected database migration ran.**

Composer locks Dompdf 3.1.6 and SimpleXLSXGen 1.5.17 with their transitive dependencies. Feasibility used Composer platform PHP 8.0.30; actual validation runtime is PHP 8.2.12. This does not certify the complete legacy application on PHP 8.0. No ZIP extension/global runtime change or PHP-minimum increase. Required native formats were actually generated; no CSV rename or simulated PDF. Vendor files stay ignored; deployment must install the locked dependencies. Dompdf remote/PHP/JavaScript processing is disabled. Text/formula/leading-zero/large-decimal handling is independently checked. Binary limits are explicit (5,000 PDF / 50,000 Excel result rows); an oversized binary fails before sending a partial file and offers a narrower period or full CSV.

## Authoritative V2 demonstration data

Input: `ATIKHA-DEMO-V2-FULL`, supplied ZIP SHA-256 `dc9d71c038f0e5416355c5f8aa40e11e9dab3256e1c492a06822f0f01004f444`. Fictional presentation data only; no actual confidential Atikha records. Business project/account/party/reference keys map to current schema identities. Posted records use supported workspace, advance, correction, budget, reporting, immutable item-review and audit services. Direct fixture insertion is confined to synthetic user identities, local receipt/manual-attempt metadata and explicitly prepared non-delivered Email states.

Covered recognized history: **November 2025–October 2026**, with an explicitly designated October 31, 2025 fictional opening-funds journal; November 2026 approved budget plus a pending proposal for comparison. All 360 final intended expense rows are independently checked by account/project/exact amount, including final replacement identities. All twelve monthly income, recognized expense, opening/closing cash and effective approved-budget oracles match `monthly_expected_summary.csv`. All ten `volume_expectations.csv` targets are met.

Final clean-start counts:

- 572 posted journals / 1,144 journal lines; 120 incoming-funds records; 360 final recognized-expense events; 24 zero-net organization reclassifications.
- 22 advances and 15 original–reversal–replacement chains. Release is an asset; liquidation is not a second cash payment. Settled, partial, unused-cash-return, overdue and due-soon examples are present.
- 292 private watermarked fictional images / 277 immutable manual detail reviews. All 220 supplied evidence metadata keys are mapped, including the 20 advance-release support documents. There are 36 additional OCR/manual review scenarios; no successful provider reading is fabricated.
- 17 actual budget revisions / 294 stored revision allocation rows. The supplied 221 allocation rows remain represented; additional rows belong to preserved returned/superseded/pending revisions, not extra current approvals. Effective approval, superseded approval, returned revision and pending submission remain distinct.
- 14 submitted report copies / 13 actual review decisions; Reviewed, Returned and Pending Review are represented. Current dashboard attention/queue use the existing lifecycle states.
- 91 existing Notifications, 2,996 immutable audit records, 6 projects, 64 parties and 18 accounts. Four Email examples: Sent, Failed, Sending and Delivery Unconfirmed; none was externally delivered.

Projects: Migrant Reintegration & Livelihood Support; OFW Family Financial Education; Community Enterprise Development; Returnee Entrepreneurship Mentoring; Family Savings & Financial Resilience; Family & Youth Resilience Workshops. Accounts cover configured cash, employee-advance control, opening funds, five funding/income accounts and ten program/operating expense accounts. Parties include readable fictional funding sources, suppliers and accountable people.

### Forecast oracles and truthful deviations

At a trusted service-test clock of **November 1, 2026**, all twelve historical months are complete. The four independent benchmarks match: Last Month PHP 307,250.00; MA3 PHP 298,433.33; weighted MA3 PHP 301,366.67; trend6 PHP 315,486.67. Earlier-only six-fold selection chooses trend6, average absolute error PHP 1,774.44; effective November approval PHP 330,000.00. Seven FAM-YTH account/project series have only one or two observations and remain Insufficient History. Overall estimates are independent of detailed estimates; coverage is explicitly partial.

The **live October 29** UI correctly excludes incomplete October (eleven complete months); no GET time override or fake calendar completion is provided. The November benchmark is service-test evidence, not the current live figure.

Other explicit V2 adaptations:

- CA-DEMO-012 labels itself settled as of October but places liquidation/return on November 13/16. Future events were not posted early: current outstanding remains PHP 23,000.00. Historical monthly expected totals are preserved.
- Repeated budget scenario descriptions map to the same legitimate revisions rather than fabricated duplicate approvals.
- Evidence/OCR examples use original watermarked fictional images and truthful manual confirmation, not invented provider success/account suggestions.
- AUD-DEMO-01/02 arise from genuine supported edit/correction history. AUD-DEMO-03 provider failures and AUD-DEMO-05 sign-in/IP history were not fabricated in polished presentation data; manual/unconfigured failures are excluded and new-IP classification is separately tested with technical events. AUD-DEMO-04 monetary-change monitoring remains disabled because no reviewed threshold is configured. Technical/hostile fixtures stay separate.

## Verification actually performed

Current-task results, with exact commands/logs/source pins in private evidence:

- Nine focused suites: cash-flow financial oracles 23; transaction presentation 26; deterministic/advisory cases 29; primary protection 14; U2 adapter 90; U2 follow-up 52; advance adapter 58; correction adapter 41; editable-amount adapter 20. These are scoped suites, not a claimed rerun of every historical Stage 1–7 suite.
- 23 real HTTP mutation/recovery checks covering Payment, Receipt, draft, advance release, correction, budget submit/Management approval, report submit/review and reversible project setup; complete advance lifecycle/coverage additionally exercised by the V2 domain seed.
- 25 role/privacy/CSRF/Primary/flags-off/GET-notification checks; real designation writer-lock contention; separate initialization race/constraint/partial-state evidence.
- Four real close/reopen/durable-retry checks; five Suggested Budget Baseline eligibility/private-draft/retry/stale/tamper checks.
- 33 live/frozen export/immutability checks; ten saved Trial Balance deep-link/export checks; ten independently reopened actual HTTP binaries; separate hostile text/leading zero/formula/large-precision workbook and six-page PDF read-back.
- 196 full-page role/state captures at 1366×768 expanded, 1366×768 collapsed, 1920×1080 and 390×844: all HTTP 200, zero JavaScript page errors, no page-level horizontal overflow. Financial worksheet horizontal regions remain intentional. Among rendered application changes, this sweep predates only the final scoped Email correction; 24 final Email role/state/viewport retakes and the focused detail suite validate it. Non-rendering seed-deviation wording and handoff documentation were finalized later.
- 42 keyboard/dialog/dirty-editor/mobile/budget/manual-OCR checks; seven additional actual browser Payment/Receipt Save–Review–Record/exact-result checks; five detail/print/Email-action-bar checks. Original attempts and harness failures are retained. An early private test looked for optional Reference in simple entry; the test was corrected, not the product. Another private confirmation test incorrectly awaited the pending dialog promise; its wait was corrected. The genuine focus-wrap, mobile overflow, early AtikhaUI load order and Email fit issues were fixed and retested.
- Full pages and representative actual-size viewport/dialog/mobile captures visually inspected, not just selectors. Real native PDFs and eight-page ledger/four-page report (including its captured configuration) browser prints were reopened/rendered and checked for totals, repeating headers, clipping and replacement glyphs.
- PHP/JS syntax, UTF-8/mojibake search, `git diff --check`, exact core/auth/config/protected-runtime hashes, final Markdown links and patch forward/reverse reconstruction pass; final counted manifests are packaged.

The static existing Chart.js asset was permitted in the desktop screenshot matrix; all financial/provider endpoints remained local/blocked. Other focused browser runs block external resources and exercise the existing chart fallback. No browser result is described as a human usability success.

### Performance

Controlled synthetic measurements, not production SLA: three fresh PHP processes with two sequential model calls each gave Planning medians about 0.84s/0.84s on 24 QA journals and 1.08s/1.08s on 572 V2 journals. Minimum projection preserved complete-model validity/configuration checks and old/new history/estimates equivalence, reducing the controlled twelve-month model from 2,072 to 468 statements and roughly 6.88s to 1.15s; representative peak memory decreased from 14 MiB to 8 MiB (repeated paired runs peak 10 MiB).

Quiet three-request V2 HTTP medians: Ledger 0.23s, Planning 1.20s, Audit Alerts 1.98s, Reports 1.99s, native PDF 2.03s and Excel 1.78s. Small-fixture reader/export medians and raw timings are retained. HTTP downloads used a 90-second verification timeout and completed fully. Memory/query instrumentation is supplied for the planning read model, not falsely claimed for every HTTP endpoint. Screen-capture timings include network-idle/static assets/concurrent checks and are kept separate from quiet HTTP measurements.

## Preview, reset and reseed

Final interactive V2 preview: **http://127.0.0.1:54317/**, only database `atikha_test_predefense_demo_35d1b3549780`. All current schema-ready internal features are enabled with normal authorization, review, posting, evidence, revision, audit and retry rules. Runtime `SELECT DATABASE()` and independent schema/foreign-key guards agree. Current source hashes match the actual local PHP/JS/CSS; it is not an old GitHub checkout.

Private Admin/Management/Primary entry links and local run details are in `.migration-private/final-predefense-20261029/OWNER-PREVIEW.md`; they are excluded from the review package and public documentation. This is a facilitated synthetic UI sandbox, not authentication/MFA acceptance. The old 59912 preview and finished defense system were not replaced.

From repository root:

```powershell
python .migration-private/final-predefense-20261029/demo_control.py status
python .migration-private/final-predefense-20261029/demo_control.py stop
python .migration-private/final-predefense-20261029/demo_control.py start
python .migration-private/final-predefense-20261029/demo_control.py reset --confirm=atikha_test_predefense_demo_35d1b3549780
```

Reset restores the verified **populated** starting SQL/private files, rather than reseeding/consuming participant state. It validates every backup hash, exact loopback/app/process/database identity and absence of cross-schema dependencies; protected/unknown targets are refused. It stops only its owned PHP process and restarts the same URL. All 51 starting table fingerprints are independently compared after restoration. A fresh private entry link may be needed after session reset.

Repeatable full seed mechanism: `scripts/seed_predefense_demo.php --context=<owned-private-demo-context>`, requiring the exact verified V2 bundle, an empty complete disposable schema and uninitialized designation. Already-started/nonempty/wrong targets are refused. The private empty-reset helper and explicit seed invocation were actually exercised when correcting the missing release-support adapter; the final ready-state reset is preferable for normal inspection. A full rebuild requires stopping that demo, archiving its existing ready snapshot, using its guarded empty schema reset, executing the seed and refreshing facilitator IDs/source pins plus a new ready snapshot. Do not point the seeder at working, participant or defense databases.

External effects remain blocked: private config has no active SMTP/Gemini credentials, PHP provider/network functions are disabled, and Email writes receive truthful local isolation feedback. Scan Receipt upload/manual review remains real. No real MFA/email/provider activation or global Apache/MySQL restart was used.

## Unperformed working rollout and acceptance

Working rollout is **not performed**. A later authorization must verify backups/recovery, deployed schema/source/provider configuration, dependency installation, an explicit custodian identity and maintenance window. Apply 026 only if absent after predecessor checks; never replay existing migrations. Initialize using the reviewed designation service with explicitly selected active accounts, then verify supported user/reset writers and rollback/recovery procedures. The included initializer intentionally refuses working targets; do not loosen its guard ad hoc. Absent/partial/uninitialized designation deliberately blocks user/reset changes, so copying source without this deployment preparation is not an approved rollout. No real mappings, opening funds, accounts, budgets or demo rows may be imported as client policy.

Real SMTP/Gemini/MFA acceptance, physical-device/cross-browser testing, physical browser zoom, real screen-reader pass, accessibility certification and Atikha/client acceptance remain unperformed/separate. Keyboard/ARIA, reduced-motion and 200%-equivalent CSS viewport/reflow evidence do not substitute for those checks. Firefox was not available. No source-level blocker was found in the executed checks; external source/browser review and the owner's short inspection remain pending.

## Short owner confirmation

Use only the owned V2 preview. Check the laptop dashboard/sidebar, record one Payment or Receipt and open its result, inspect one manual receipt, ledger/cash-book transaction, advance, budget and report. Open the already-validated PDF/Excel/CSV/Print examples, Planning/coverage, one Management & Board submitted item, Primary badge/locked actions and one explained audit alert. Use recorded responsive evidence for exhaustive viewport coverage. This is a short owner smoke check, not a new formal study and not authorization to resume preserved participant A01 or deploy working data.

## Review artifacts and Git

Private package: `.migration-private/final-predefense-20261029/final-predefense-review.zip`. It contains final source, actual inventories, exact byte/hash manifest, committed-baseline and preserved-dirty-layer patches/reconstruction proof, sanitized checks, selected role/state/viewport screenshots, native/print export samples and review index/verifier. Passwords, private entry/session keys, database dumps, private contexts and working evidence are excluded. Full raw private execution evidence remains separately local.

Final status: source implementation delivered for review; owner acceptance/working rollout unperformed. **Nothing staged, committed or pushed.**
