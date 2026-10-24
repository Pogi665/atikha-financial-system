# 005: Linked exact reversal and corrected transaction

- Status: Active
- Selected date: Recorded by October 6, 2026 in Stage 3 explicit selections; approved plans govern exact validations.
- Source/evidence: [Legacy decisions](../history/project-decisions-legacy.md), [Stage 3 plan](../stage3-plan.md), [correction service](../../includes/journal_corrections.php).

## Decision

Keep the original; generate its exact reversal by swapping original debit/credit, not negative line amounts. Reverse-and-replace or reverse-only requires reason, validated date/preview and atomic posting. Reversal originates in GJ; replacement follows existing routing. During preview the original remains Posted; it becomes Corrected only after successful posting. Accounting reversal is not actual cash being returned.

Preserve one correction per target, chain/line mappings, original evidence and fresh manual review for eligible association reuse. Respect advance chronology/effective-settlement blockers and durable retries. Correct an eligible replacement rather than a generated reversal; never silently transfer settlements between advances.

## Reason

The business story Original Transaction -> Reversal -> Corrected Transaction must preserve the exact accounting result.

## Supersedes

Direct posted-original edits and describing reversal as a cash-return transaction are superseded interpretations.

## Does not change

Existing balances, dates/routing/eligibility, evidence ownership, privacy, frozen identities, audit atomicity or writer locks.

## Related files/docs

[Immutability](003-posted-record-immutability.md), [advances](004-cash-advance-accounting.md), [reader scopes](010-financial-reader-scopes.md).
