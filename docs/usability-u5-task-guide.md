# Daily accounting task guide

This guide follows the completed synthetic U5 walkthrough. It is a reference for the delivered controls, not evidence that a client has accepted the system or that normal login/MFA was tested.

## Save, review and record

**Save and close** keeps an unfinished draft in **My drafts** and leaves the form after the server confirms the save. Use **Continue** in My drafts to resume it. A draft is not a financial record. If saving cannot be confirmed, the populated form stays open; check My drafts before repeating an uncertain action.

**Review payment / Review receipt / Review entry** saves and validates the details, then shows the proposed complete journal. Review does not record it. Check the accounts, projects, amount and evidence before using the relevant **Confirm and record** button. The recorded result identifies the journal and links to its exact transaction. Browser refresh can return a saved review to entry mode; review it again before confirming.

## Payment and receipt

Start with **Record a payment** or **Record money received** in the navigation. In Quick form, supply the party, purpose, cash/bank account, amount, counterpart account and project as needed. Organization operations is the organization-wide context; select an actual project when appropriate. The date and available defaults still need checking.

Ordinary editable amounts accept correctly grouped values such as `5,000.00` as well as `5000.00`. An invalid amount remains an error. This convenience does not change evidence coverage or server accounting rules. Follow the inline validation message and preserve the existing draft when recovering.

## Cash advance

Open **Cash advances**. **Release cash advance** is the main action below the register heading. Enter the accountable employee, purpose, advance control account, funding account, amount and required liquidation due date. Review the complete journal before recording. A release creates an advance asset; supported costs are recognized through liquidation.

Open the particular advance from the register or the recorded result's **View advance** link. An eligible outstanding advance shows **Record liquidation** and **Return unused cash** near its employee and purpose. Available actions remain subject to the server's current checks.

**Record liquidation** accounts for supported spending. Review the original supporting image, select its proper document purpose, enter the supported amount and confirm the manual review. Uploading an image alone does not supply monetary coverage. Gross supported costs and net reduction of the advance can differ when a permitted liability credit is present; do not substitute the net amount for required gross support.

**Return unused cash** records money given back. Attach and manually review the return proof, then complete the separate confirmation binding that proof to this advance, amount, receiving account and accounting date before Review. This is distinct from liquidation.

The detail shows **Settlement status** and Outstanding, Released, Liquidated and Returned amounts. In the synthetic walkthrough, a 10,000 release followed by 8,000 liquidation and 2,000 return ended at Outstanding 0 and Settled. Those figures are test facts, not an Atikha accounting-policy decision. The posted operation timeline links to each exact journal.

## Find and correct a record

Open **Find a transaction (Journal History)**. It lists journal lines: a payment can appear once for its debit and again for its credit. The visible **Journal #** identifies which complete transaction each line belongs to. Use the existing filters and description search, then **View** to inspect its complete entry. This release does not add a journal-number search contract.

The History modal offers **Prepare correction draft**; the complete transaction page offers **Correct a recorded transaction**. Confirm the original journal identity first. Supply the correction reason and intended replacement, inspect the original, exact reversal and replacement previews, then confirm once. The original remains in history. A reversal is not an actual cash return. When a response is uncertain, inspect the saved draft or posted comparison before attempting another submission.

## Management inspection

Management can inspect permitted posted transactions, correction comparisons and advance balances/timelines. These are read-only projections; private evidence and authoring actions remain restricted by the server. Synthetic session setup in U5 did not verify normal production authentication.
