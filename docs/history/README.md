# Frozen project-memory history

These four legacy files preserve exact Git blob bytes from commit `4d59ff176edde0b74324093aec0a5aa90786188a` (`Redesigned`), not CRLF-transformed checkout copies:

- [README](readme-legacy.md), originally `README.md`.
- [Client context](client-context-legacy.md), originally `docs/client-context.md`.
- [Decisions](project-decisions-legacy.md), originally `docs/project-decisions.md`.
- [Status](project-status-legacy.md), originally `docs/project-status.md`.

The [manifest](memory-archive-manifest.json) records original/archive paths, commit, Git blob OIDs, byte sizes and SHA-256 over archive bytes. Verify with `git cat-file blob <oid>` or `git show <baseline>:<original-path>`; do not copy working-tree files over these archives. The narrow .gitattributes -text rule protects their bytes from conversion. Do not edit archived wording, encoding, dates or links.

Legacy relative links refer to each original path's directory and some historical destinations may be obsolete. Resolve those using the manifest and Git history; they are not new active-memory navigation links. Do not rewrite frozen links to imply later knowledge. Existing plans, reports and evidence packages elsewhere retain their own dated scope and are not relocated by this task.

Start with the [project brief](../../PROJECT-BRIEF.md), [current state](../current-state.md), [decision index](../decisions/README.md) and [curated client evidence](../client-context.md). Historical authorization, proposed/unstarted language and old 'Current' entries do not override that live handoff. This history does not confer operational permission.
