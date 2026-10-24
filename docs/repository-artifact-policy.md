# Repository artifact policy

Keep application source, reviewed requirements, durable decisions and concise documentation in Git. Generated browser screenshots, test output and temporary exports normally stay in ignored/private locations; never include credentials, sessions, MFA data, private receipts, working database exports or provider secrets.

Select permanent evidence deliberately, with role/state/viewport/source identity, scope/limitations and a manifest/hash. Large generated review ZIPs should not routinely be committed; use a durable accessible artifact reference plus SHA-256 and a small manifest where appropriate. A hash is not a replacement for retaining the actual evidence in an accessible authorized location. Do not upload private artifacts to a public host merely to reduce repository size.

## Existing artifacts are preserved

At the Redesigned baseline, all 176 tracked output files belong to `output/system-visual-review/`:166 PNGs, one PDF and nine metadata/index/instruction files. This is a deliberate dated consultation/evidence collection, retained unchanged with its manifest. The explicit output ignore exceptions leave the directory and descendants selectable; additions still require an intentional evidence decision, not an automatic bulk add.

The 68,257,994-byte [redesign review ZIP](presentation-redesign-review-package.zip), its [checksum](presentation-redesign-review-package.zip.sha256), earlier review packages and duplicated historical PDFs remain tracked and unchanged. This policy is prospective, not retroactive deletion permission.

The 14 tracked `scripts/__pycache__/` bytecode files are generated caches. Ignore rules prevent future untracked caches, but do not untrack these existing files. Any later targeted index cleanup needs its own explicit scope; it must preserve historical evidence and must not delete working files or rewrite history implicitly.

## Git handling and review

Source text uses LF according to .gitattributes; frozen legacy-memory files use a narrow -text exception for byte preservation. PDF/image/Office/ZIP artifacts are binary. Add attributes without repository-wide renormalization: never run `git add --renormalize .` for this cleanup. Review only intentional file changes; no blanket add, clean, reset or local Git-configuration change.

No Git LFS introduction, filter-repo, repository-history rewrite, force-push, evidence untracking/deletion or automated artifact publication is authorized here. Review-package source snapshots remain dated; do not regenerate old approved packages because documentation pointers changed.
