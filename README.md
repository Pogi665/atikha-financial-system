# Atikha Financial Management System

Atikha Finance supports journal-based accounting, cash advances, financial reporting, monthly budgets and controlled review for an NGO. It uses PHP, MariaDB/MySQL and JavaScript with Tailwind/scoped CSS.

## Start here

1. [PROJECT-BRIEF.md](PROJECT-BRIEF.md): durable architecture, roles and safeguards.
2. [Current state](docs/current-state.md): verified baseline, acceptance boundaries and next task.
3. [Decision records](docs/decisions/README.md): read those relevant to the task.
4. [Client context](docs/client-context.md): when client/accounting/adoption evidence matters.
5. Relevant plans/deliveries or [history](docs/history/README.md) only as needed.

## Development and documentation

Follow [AGENTS.md](AGENTS.md). Dependency declarations are in [composer.json](composer.json) and [package.json](package.json); local secrets belong outside Git, following [config.example.php](config.example.php). Do not run migrations or configure working providers merely to explore the repository. `npm test` is a placeholder; use the task's documented focused checks. `npm.cmd run build:css` is needed only for a Tailwind rebuild.

See the [artifact policy](docs/repository-artifact-policy.md), [redesign delivery](docs/presentation-redesign-delivery.md), [redesign review approval](docs/presentation-redesign-review-approval.md), [fresh human protocol](docs/presentation-redesign-human-protocol.md) and [unperformed rollout procedure](docs/presentation-redesign-rollout.md). Live progress belongs in current-state, not this README. The [baseline README archive](docs/history/readme-legacy.md) preserves earlier development notes and dated execution history.
