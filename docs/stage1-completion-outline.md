# Stage 1 completion supplement: scope for detailed planning

Prepared October 6, 2026. This is a scoped outline for the next planning round, not authorization to implement application changes.

## Purpose and current position

Close the three UI gaps identified by comparing the Stage 1 implementation with `stage_1.md`, and bring the delivery guide up to date before Stage 2 planning.

Migration 019 has been applied to `atikha_finance`, the new schema was verified, and the workspace flag is enabled. Read-only post-migration checks confirmed preservation of the original journal, journal-line, receipt and account fields. The recorded baseline was two journal entries, four lines, and PHP 11,000.00 each in debit and credit totals. The user demonstrated saved receipt/payment drafts and balanced review previews. Those test drafts remain unposted.

The user chose direct deployment without restored-copy rehearsal and has deferred the remaining manual acceptance checks to final integration. Preserve those decisions in the documentation; do not describe restoration, rehearsal, document testing or live posting acceptance as completed.

## 1. Searchable selectors

### Required outcome

Staff can search eligible account, project and payer/payee choices without scrolling through the entire list. The detailed plan should inventory all relevant selectors in the receipt, payment and General Journal workspaces, including cash-account, counterpart-account, default-project and individual-line project choices.

### Scope and constraints

- Replace or enhance the ordinary dropdown interaction while preserving the selected record IDs and existing payload structure.
- Keep visible labels, keyboard navigation, focus behavior and clear no-match feedback. Searching must not silently select the first match or create a record.
- Preserve the distinction between an empty selection and Organization operations/no project. Preserve General Journal's optional party and receipt/payment's required party.
- Maintain current active/inactive eligibility and historical snapshot display. Searching must not make an otherwise unavailable account or master eligible for posting.
- Preserve inline project/party creation, Apply to all lines, unsaved form contents, draft restoration and posted read-only behavior.
- Reuse an existing suitable accessible selector pattern where practical. The detailed plan must choose the component approach and identify CSS/JavaScript integration before implementation.

### Focused verification

Check keyboard selection, search with no matches, selection persistence after save/reopen, inline creation, multiple allocation lines, and historical read-only display. No financial posting is needed for these checks.

## 2. My Drafts filtering by last saved date

### Required outcome

The date filters select drafts by their last saved timestamp, as promised in the original plan. They currently filter the accounting date inside the draft payload.

### Proposed behavior for detailed planning

- Retain the entry-type filter and transaction-date information in the list.
- Label the date controls explicitly as last-saved filters, so staff know which date is being searched.
- Proposed convention: display and filter last-saved dates in Asia/Manila, converting filter boundaries to UTC for comparison with the stored `updated_at`. The detailed plan should finalize this convention explicitly.
- Include the complete To day by using the next day's exclusive upper bound. Validate malformed or reversed ranges rather than silently broadening results.
- Retain owner-only access, Draft-only results and newest-update ordering. Drafts with an incomplete accounting date remain eligible when their last-saved timestamp matches.
- Review existing `from`/`to` request handling and test callers so the changed meaning is consistent between the interface and server.
- Keep the last successful list visible if a filter request fails, with an error explaining that the new results were not loaded.

### Focused verification

Use drafts whose accounting dates differ from their last-saved dates. Check inclusive date boundaries, empty accounting dates, invalid ranges, type filtering and ownership. Do not alter existing financial records to prepare fixtures.

## 3. Navigation after posting

### Required outcome

The posted result offers a link to its originating book and a separate Journal History link, matching the original Stage 1 plan.

### Scope and constraints

- For CRB/CDB entries, show both the corresponding book destination and Journal History. For General Journal entries, make the Journal History destination clear without redundant links.
- Use supported Financial Records routes and parameters. Do not invent a journal-ID filter or imply that a link opens one exact journal unless that behavior is actually supported.
- Preserve read-only posted results, historical snapshots and the Prepare another entry action.
- Apply the same navigation when recovering a successful posting retry or reopening an already-posted draft.
- Retain existing Financial Records filters, complete cash-book entries, search totals and Journal History line-filtering behavior.

### Focused verification

Verify link destinations for CRB, CDB and GJ, including recovered/reopened posted results, using existing disposable test data. Do not post the working database's test drafts solely to verify navigation.

## 4. Delivery-status documentation

Update `docs/stage1-delivery.md` so it distinguishes the initial implementation delivery from subsequent deployment:

- Record migration 019, schema verification, feature activation and configuration syntax validation as completed.
- Record the user-reported database export and successful application copy without claiming a verified restore. The SQL file was reported as 60,578 bytes; Robocopy reported 17,492 files copied with zero failures/mismatches.
- Record preservation checks and the user-demonstrated draft/review screens with their respective verification boundaries.
- Explain that restored-copy rehearsal was waived by the user for direct deployment.
- List remaining manual evidence, live posting, cash-book and access-control acceptance checks as deferred to final integration. Do not confuse previously completed disposable automated checks with these pending working-system checks.
- Add the completion supplement's results once implemented and verified. Keep implementation/deployment status separate from full acceptance status.

Retain the original recovery instructions and the prohibition on rerunning migration 015. State that migration 019 must not be rerun on the already migrated working database.

## Boundaries

This supplement does not introduce a migration, seed master records, post or delete transactions, enable advance processing, change accounting/evidence rules, or implement Stage 2. Leave the existing enabled configuration and unrelated user changes intact.

A Tailwind rebuild is not automatically required: the workspace currently loads its dedicated stylesheet. The detailed plan should identify whether its chosen UI implementation requires any asset build.

The local changes are still uncommitted/unpushed. Committing or pushing is a separate repository action, not a remaining requirement for local workspace activation.

## Next planning deliverable and completion criterion

The detailed implementation plan should name the exact files and interfaces, settle the selector and timezone choices, specify minimal meaningful checks, and order the changes so they can be reviewed together. Preserve the user's decision to defer broad manual acceptance testing while retaining focused verification of any new changes.

After the three UI gaps and documentation update are implemented and their focused checks pass, Stage 1 implementation/deployment can be recorded as complete. Full acceptance remains pending until the explicitly deferred checks are performed. Stage 2 can then be planned against that documented foundation.
