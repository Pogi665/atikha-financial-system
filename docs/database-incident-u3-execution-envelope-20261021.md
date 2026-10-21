# Proposed coordinated execution: database incident closure, then U3-B-D

**Execution authorized by the user, October 21, 2026:** after the concrete full envelope was presented, the user explicitly replied "yes, i authorize." Its conditional repair/cutover/acceptance and U3-resume scope now applies, with all internal gates/exclusions intact. Earlier proposal/pending-decision language below is retained as authorization history. [Active execution record](database-incident-u3-delivery-20261021.md) distinguishes preparation from passed gates.

October 21, 2026. **Concrete proposal awaiting the user's own execution authorization. No operational step has executed under this proposal.**

The user supplied an external review approving diagnostic Stage A and a subsequent reviewer recommendation for one coordinated incident-to-U3 authorization. These are supplied reviewer recommendations, not the user's authorization to repair/promote the database. This addendum records the proposed change in authorization structure without reopening accounting requirements or authorizing a speculative fix.

## Governing scope and precedence

Use the [current crash diagnostic](database-shutdown-diagnostic-20261021.md), [root-cause plan](database-crash-root-cause-plan-20261021.md) and [approved U3-B-D plan](usability-u3-bcd-plan.md). Preserve the approved/pushed U3-A baseline and all current local work, including partially prepared B source/tests. B's backend/browser gate is incomplete; C/D are not enabled. Keep diagnostic evidence and current state separate from older recovery material.

**If the user authorizes this envelope**, its internal gates replace the root-cause plan's repeated external Stage A/B/C authorization pauses. They do not remove preservation, identity, evidence, privacy or stop requirements. It also permits automatic resumption of the already authorized combined U3 checkpoint only after the working environment acceptance gate passes. Until then, U3 remains paused.

No guaranteed repair or completion time is implied. A one-shot authorization permits continuous work through successful gates; it does not permit guessing, hiding failed checks or bypassing a hard stop.

## Ordered internal gates

### 0. Freeze and preserve today's state

Verify all relevant server processes/listeners are absent. Stop on ambiguous ownership rather than terminating anything automatically. Measure storage needs and reject unsafe/reparse/hard-link paths. Preserve the complete current stopped database directory, every effective configuration input, logs/dump, binary/plugin/dependency identities, protected upload roots and the entire current application working state plus its baseline diff. Keep sensitive files private, outside served paths, under fresh unique destinations; overwrite no earlier archive.

Require independently verified source-before/copy/source-after manifests. Record permission expectations and content hashes. Existing backups are retained as historical evidence; they do not substitute for a new copy of current state. Preserve an untouched copy and derive examination/candidate copies from it.

### 1. Analyze the existing dump offline

Before substantial symbol acquisition, record dump flags/type, stream inventory, exception, module identities, exception-thread context and captured stack-memory coverage. Use only copied evidence and matching image/dependency material. Obtain trusted official debugger/symbol material as needed: identify exact package/version/destination, verify publisher provenance and hashes, and comply with sandbox/installer approvals. No server reinstall, global debugging changes or unreviewed tool installation is implied.

Require exact `mysqld.pdb` GUID/age binding, not merely a version match. Never force symbols. Analyze only the copied dump; never launch or attach to the working server. Keep full memory/transcripts private. Incorporate the three supplied review clarifications: tool acquisition has its own applicable permissions, dump contents are checked early, and later reproduction minimizes behavior changes.

If a validated pre-handler failure path is informative, proceed to the evidence-supported candidate correction. If matching symbols or captured context are unavailable, record that limitation and proceed to gate 2 without another external review, provided preservation/isolation can be proven. Provenance failure does not authorize using untrusted artifacts; discard that acquisition path.

### 2. One isolated reproduction, only if needed

Prepare a concrete launch manifest from the root-cause plan's isolation/identity controls and function-only guard checks. Use a separate examination copy, the same verified MariaDB build/dependencies and a distinct free loopback port. Redirect every writable data/runtime/log/temp/PID/socket/replication/crash-dump path to trial storage. Keep working/preserved state, Apache and the application disconnected.

Preserve original startup behavior except changes strictly required for isolation or preventing external/application activity. In particular, do not disable automatic-repair/startup behavior merely for convenience if that could suppress the trigger. Document any necessary behavioral difference and its effect on interpretation. No speculative force-recovery, skip-grant-tables or unrelated repair flags.

Permit at most one debugger/console startup, with captured pre-handler context/output and bounded observation/capture times from the root-cause plan. No restart loop. A different failure or successful startup is evidence to assess, not permission to invent a repair. Preserve it and continue only if it supports a narrow correction or validation path. If it provides no usable basis for a correction, report that evidence limitation instead of trying another startup configuration.

### 3. Evidence-supported correction on a fresh candidate

Identify and record the exact initiating defect and proposed operation before applying it. Permitted scope is a narrowly proven damaged object/index, stale/corrupt metadata, local configuration/path inconsistency, or individually proven file/object restoration that preserves current logical application data. Apply only to a fresh isolated candidate from gate 0, with expected file/object change boundaries and before/after evidence. No blanket repair or broad experiment sequence.

A local configuration correction must be demonstrated on the candidate first and included in the cutover manifest. Preserve the original configuration; do not alter it merely to get a passing test. If evidence does not establish the defect, or the operation requires excluded/destructive changes, stop.

### 4. Validate the whole candidate

Require stable readiness, authenticated SQL, exact version/datadir/effective settings, expected system/application objects, table/view accessibility, foreign-key/orphan checks, existing accounting balance invariants, advance-control reconciliation, users/grants, evidence metadata/path/hash checks and a verified graceful shutdown/restart. Record at least two clean startup/shutdown cycles and a bounded stability observation; state its duration rather than calling it long-term stability.

**Current-baseline rule:** a physical preservation hash is not a logical financial baseline. Establish trustworthy current pre-correction application data/counts/totals/schema/permission/evidence fingerprints from the preserved state wherever safely readable. Clearly distinguish capture/reproduction engine writes from repair. Compare the corrected candidate with that independently established current baseline; never generate a post-repair snapshot and label it "before," nor silently substitute an October 8/20 baseline. If equivalent preservation of current logical financial data cannot be established, stop before promotion. Document any narrowly justified physical-only change and independently validate the logical state.

A passing startup, clean checker or balanced journal alone does not satisfy this gate. Reuse recovery invariants as checks, not consumed old operational helpers or old logical expectations. No old migration is rerun. Candidate validation may require further ordinary validation starts; the single-attempt budget applies specifically to diagnostic reproduction, not an unlimited correction/reproduction loop.

### 5. One guarded working cutover

Only after all required candidate checks pass, promote the verified whole candidate using a prepared manifest. Preserve the untouched current-state backup and uniquely archive the pre-cutover working directory; delete/overwrite neither. Verify identity/hashes and configuration dependencies before and after moves. Retain clear rollback evidence; do not restore an earlier broken archive automatically.

Start the recovered working MariaDB normally with normal XAMPP configuration on 3306. Verify intended datadir, readiness and authenticated SQL. If behavior differs from the validated candidate or normal startup fails, stop; no second repair/cutover cycle is implied. Handle only clearly owned processes through the reviewed bounded procedure. Uncertain shutdown requires reporting the live PID and leaving its files untouched, not forced termination.

### 6. Bounded working application acceptance

Start Apache only when required for guarded normal authentication and read-only application checks. Allow the user to enter credentials/MFA normally; do not bypass authentication or collect secrets. Ask for the user's presence only when necessary. This dependency is not another broad plan approval.

Block forecasting, OCR, communications and all financial/draft mutation routes at both browser and server/provider boundaries. Check dashboard, Financial Records, exact journal, advance register/detail, draft list and authorized evidence access; check Management privacy using an authorized account when available. If a required role check cannot be established, record it as pending and do not waive a required acceptance gate. Do not use working drafts/receipts as synthetic fixtures or publish private screenshots.

Compare current financial/evidence/permission fingerprints before and after acceptance. Document only expected inherent login/MFA/audit effects separately, preserving prior history. No working release, liquidation, return, correction, deadline extension, upload or posting. Failure attributable to the recovered environment stops resumption of U3.

### 7. Resume B, then C, then D, then final regression

Once acceptance passes, resume preserved local source under the approved combined plan, with no additional routine authorization pause. Verify each ordered internal gate separately. Keep committed-employee-only advisories on the existing authorized read contract, generation/identity guards and truthful all-page totals. Keep frozen same-request recovery and all accounting/evidence/privacy/correction/OCR boundaries.

Use fresh guarded disposable databases and private configuration/sessions/evidence, with external calls blocked. Never run financial tests on working data. Complete focused backend/API/browser checks, real lost-response-after-commit replay for all three workflows, affected Stage 2/3 and U2 shared-controller/template checks, U3-A/readback/privacy checks, inspected screenshots and final-byte manifests. Fix in-scope defects autonomously. Stop for new accounting/schema/role/permission/feature-flag/writer/lifecycle policy or scope expansion.

### 8. Consolidated handoff and review package

Deliver separate evidence for root cause, exact correction, preservation/rollback, isolated validation, working startup/application acceptance, B, C, D and final regression. Distinguish execution evidence from external review and unverified claims. Include remaining work and limitations.

Produce one U3-B-D exact-source review ZIP with source, patches, hashes, tests/reports and sanitized inspected screenshots; exclude configurations, exports, sessions, dumps and private receipts. Stop for external source review. No commit/push, U4/U5, U-PERF-01, original Stages 4-7 or independent migration-009 diagnosis is authorized.

## Exclusions and stops remain binding

No speculative deletion/regeneration of InnoDB/Aria files; older whole-directory replacement; MySQL80-on-MariaDB use; reinstall/version upgrade; blanket repair-all; financial-table recreation; historical migration rerun; account/grant reset; unverified binary/symbol use; or undisclosed changes to current financial truth.

Stop for unstable/incomplete preservation, identity/path uncertainty, unexpected working/preserved writes, insufficient evidence for a targeted correction, inability to validate current financial data, excluded/broad repair, failed normal startup after the one validated cutover, uncertain shutdown, security/privacy regression, product/accounting scope expansion or other material authorization mismatch. Missing tools/symbols may lead to the specified safe fallback, never to bypassing provenance/isolation checks. Sandbox and installer approvals remain applicable even inside this execution envelope.

**Pending decision:** the user's own authorization of this proposed full envelope, including trusted official diagnostic-tool acquisition with applicable installation permissions, narrowly evidenced isolated corrections, conditional working cutover/read-only acceptance and conditional automatic U3 resumption. The quoted reviewer text alone does not satisfy this decision. No new external planning round is proposed after that authorization.
