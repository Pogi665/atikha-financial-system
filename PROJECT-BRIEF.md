# Atikha Finance: project brief

Atikha Finance is a PHP financial-management capstone intended to support an NGO's internal accounting. Source implementation, working deployment and Atikha acceptance are separate claims. Read [current state](docs/current-state.md) for the live handoff; this brief is not a progress log.

## Architecture and roles

- Server-rendered PHP with small shared PHP/JavaScript presentation components, MariaDB/MySQL, Tailwind CSS and scoped CSS. Composer requires PHP >=8.0 and PHPMailer; this minimum is not a tested-platform certification. No frontend framework rewrite.
- Guided receipt/payment/journal, draft, advance, liquidation/return, correction and evidence workflows write through existing server services. Worksheet readers cover books, account ledger, financial reports, budget actuals and exports. Frozen submissions/revisions and period controls preserve review identity.
- Admin prepares accounting records, configuration and drafts and uses authorized administration tools. Management has a distinct dashboard/Review Queue, authorized budget/report/period decisions, financial readers and Email; it cannot author accounting entries or access Admin setup/private evidence.
- Normal dashboards have no forecasting. OCR is assistive; forecasting/provider services are dormant dependencies where retained. Email is outgoing correspondence; retired Board messaging is historical archive access.

## Accounting and integrity

Balanced journal entries/lines and exact-centavo server validation are authoritative. Cash paid/received is not automatically recognized expense/income. Advance release is an asset; settlement uses dedicated generated control lines. Posted records/evidence/audits remain immutable, with linked exact reversal/replacement corrections. Preserve writer locks, chronology, atomic audit, durable retries, role/CSRF/MFA checks, private drafts and Management evidence restrictions on every output channel. Financial reader scopes differ intentionally; see the [decision index](docs/decisions/README.md).

## Operational cautions and Codex workflow

Never automatically migrate, seed, repair or configure the working database. Never rerun destructive migration 015 or already-applied migrations;019 has hardcoded database selection and MariaDB DDL can implicitly commit. Historical 019/020/021 deployment evidence is dated, not a current health check. Working schema/flags must be established separately before any authorized rollout.

Read [AGENTS](AGENTS.md), then recover context in this order: this brief -> [current state](docs/current-state.md) -> relevant [decisions](docs/decisions/README.md) -> [client evidence](docs/client-context.md) when relevant -> task-specific plans/deliveries/history only as needed. Planning is read-only; implementation requires scope authorization. Preserve local work; no implied deployment, provider activation or commit/push. Use guarded disposable data and blocked/fake providers for authorized tests; never working records.

Rewrite current-state when truth changes. Put execution detail in delivery/history, durable choices in decisions and attributed client evidence in client-context. [Artifact policy](docs/repository-artifact-policy.md) and [history index](docs/history/README.md) explain preservation. Private Codex memories remain separate and require an explicit request to update.
