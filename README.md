# Atikha Financial Management System

A university capstone application for Atikha's internal NGO accounting. Its intended role is the organization's regularly used finance system, while allowing projects to continue using software required by their funders.

The application uses double-entry journals for financial records, with cash receipts, cash payments, General Journal entries, supporting documents, project/party references, and employee cash advances. Stage 3 implements linked ordinary and advance corrections. Source implementation and isolated integrated verification are complete. Working migration 021 is verified and the Stage 3 configuration flag is enabled; restoration rehearsal and full working acceptance remain pending.

## Start here

- [Current project status and next action](docs/project-status.md): implementation, deployment, verification, and outstanding work.
- [Client context and corrected visit notes](docs/client-context.md): what the user/client actually described and what the supplied references establish.
- [Selected project decisions](docs/project-decisions.md): accounting/workflow choices and unresolved questions.
- [Coding-agent guidance](AGENTS.md): how to work within the authorized scope and preserve financial history.

This README is an overview, not authorization to run a migration or activate a feature. The status file and dated delivery reports distinguish implemented code from working deployment and full acceptance.

## Technology and repository layout

- Runtime: **64-bit PHP 8.1 or later**, with `mbstring`, `pdo_mysql`, `fileinfo`, and `gd`, as required by the accounting preflights. Composer's PHP 8.0 constraint describes dependency compatibility, not the application's full runtime requirement. Local development uses XAMPP PHP.
- MariaDB/MySQL through PDO; recorded local validation uses MariaDB 10.4.32 and InnoDB.
- Server-rendered PHP pages with JavaScript and scoped CSS; Tailwind CSS 3 is built using Node/npm.
- PHPMailer is installed through Composer. SMTP/MFA and optional Gemini integrations require local configuration.
- `includes/`: accounting, authorization, evidence, reporting, and shared page services.
- `assets/` and `src/`: browser code, styles, and Tailwind input.
- `migrations/`: incremental SQL migrations with installation-specific prerequisites.
- `scripts/`: read-only preflights and focused backend/browser verification suites.
- `docs/`: plans, delivery reports, client context, decisions, and current status.
- `uploads/`: protected uploaded evidence/attachments. Keep file storage and database backups coordinated.
- `.migration-private/`: private disposable test applications, sessions, logs, fixtures, screenshots, and preservation manifests; excluded from Git.

## Main entry points

- `cash_receipt.php`, `cash_disbursement.php`, `general_journal.php`: ordinary accounting workspaces.
- `accounting_drafts.php`: owner-private saved drafts; date filters use last saved dates in Asia/Manila.
- `accounting_setup.php`, `admin_accounts.php`: project/party and Chart of Accounts maintenance.
- `financial_records.php`: complete CRB/CDB originating entries and Journal History.
- `cash_advances.php`, `cash_advance_entry.php`: release, supported liquidation, unused-cash return, and advance register.
- `journal_correction.php`, `journal_corrections.php`: Stage 3 correction workspace and posted comparisons; availability depends on schema/feature gates.
- `reports.php`: existing reports and frozen Trial Balance revisions. Later report bundles and statements are not yet complete.

The legacy `funds.php`/`expenses.php` flows are not the architectural starting point for new accounting work. Read the current journal/workspace services and plans first.

## Running the existing local installation

1. Start Apache and MariaDB/MySQL using XAMPP.
2. Use the configured application URL under localhost, then open `login.php`. The local workspace is `C:\xampp\htdocs\Financial Management System`; URL aliases may differ from the folder name.
3. Keep the existing local `config.php` and database connection settings. `config.php` is gitignored; do not overwrite it with the example or print its secrets.
4. If dependencies are absent in a new checkout, install Composer dependencies using the lockfile and Node dependencies with `npm.cmd ci`. Dependency installation is separate from database provisioning.
5. If Tailwind utilities/input changed, run `npm.cmd run build:css`. Dedicated workspace CSS changes generally require no Tailwind rebuild.

For a fresh installation, first review the base schema and ordered migration prerequisites in the relevant delivery guides. Do not import `database.sql` or rerun all migrations into an existing working database. Configure a separate empty database deliberately; this repository is not a one-command production installer.

Feature flags in `config.example.php` default to false: `STAGE1_WORKSPACE_ENABLED`, `STAGE2_ADVANCES_ENABLED`, and `STAGE3_CORRECTIONS_ENABLED`. Enabling a flag also requires its complete schema and earlier-stage prerequisites. A disabled UI does not remove posted data or disable integrity/privacy protections.

## Plans and delivery guides

- Stage 1: [delivery and deployment](docs/stage1-delivery.md); [historical completion-supplement outline](docs/stage1-completion-outline.md).
- Stage 2: [approved plan](docs/stage2-plan.md); [delivery and deployment](docs/stage2-delivery.md).
- Stage 3: [approved specification](docs/stage3-plan.md); [Checkpoint 1](docs/stage3-checkpoint1-delivery.md), [Checkpoint 2](docs/stage3-checkpoint2-delivery.md), [Checkpoint 3](docs/stage3-checkpoint3-delivery.md), and [Checkpoint 4](docs/stage3-checkpoint4-delivery.md) delivery reports.
- Final Stage 3: [delivery, verification inventory and deployment/recovery guide](docs/stage3-delivery.md); [approved Checkpoint 5 supplement](docs/stage3-checkpoint5-plan.md). Ordinary correction entry requires Stage 1 + Stage 3 flags and complete 019/020/021; advance entry additionally requires the Stage 2 flag. Working deployment requires its applicable authorization.
- Scan Receipt: [October 6 supplement delivery](docs/scan-receipt-supplement-delivery.md), covering the refreshed layout and safe extraction-failure guidance while retaining General Journal only.

Some plans retain their original planning-only status. Later user authorization and implementation are recorded in delivery reports and the current status file. Do not treat a plan's original status as the latest progress, or its existence as permission to execute it.

Earlier named review attachments such as `plan_complete.md` and `stage_1.md` are not currently present at the repository root or in `docs/`. Do not fabricate their contents; use the available records and request the missing original only if a task depends on it.

## Verification and deployment boundaries

Read-only preflights run from the application folder:

```powershell
C:\xampp\php\php.exe scripts/preflight_stage1.php --database=atikha_finance
C:\xampp\php\php.exe scripts/preflight_stage2.php --database=atikha_finance
C:\xampp\php\php.exe scripts/preflight_stage3.php --database=atikha_finance
```

A preflight does not apply SQL or establish recovery/functional acceptance. An already-applied schema may report that it is no longer ready for its migration; inspect deployment state and problems rather than rerunning SQL.

Focused synthetic tests require new disposable database names and isolated file/session storage. Follow the relevant delivery report's exact commands. For example, Stage 3 Checkpoint 3 uses `scripts/test_stage3_checkpoint3.php` and a separate Edge browser runner using the resulting private fixture. These tests create financial records in isolation; they must never target the working database. The package's `npm test` command is currently a placeholder that exits with an error.

Before authorized working deployment, back up the current database, application/configuration, and protected files together; verify restoration and rehearse on an isolated copy. The user previously deferred restoration rehearsal and broad working acceptance for Stages 1/2; that is not evidence of a passing restore. Never rerun 015, working 019/020, or an already-applied 021. Migration 019 includes a hardcoded database selection; DDL can implicitly commit. Disabling a feature flag is a UI change, not a database rollback.

## Keeping context useful

Use `AGENTS.md` for lasting working instructions, the client/decision files for requirements and rationale, and `docs/project-status.md` for changing progress. Preserve detailed proof in dated delivery reports. These files help recover context after compaction or in a new chat; they do not preserve a verbatim conversation or eliminate the need to inspect current code/schema.

For a new session, ask the agent to read those files and the relevant stage plan/latest delivery report before continuing. Share the relevant documents explicitly with external reviewers; they do not automatically receive local files. Commit documentation with the project when repository actions are authorized, excluding secrets and private artifacts.
