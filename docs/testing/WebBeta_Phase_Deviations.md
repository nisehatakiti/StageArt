# Web β版 主要機能実装フェーズ — Notes, Gaps, and Repo State Findings

Companion to this phase's completion report. Records things discovered
or deliberately deferred while implementing the join/approval,
rehearsal-management, and favorite flows, per this project's own rule
that spec deviations get written down rather than silently changed.

## 1. "公開予定" (scheduled future publish) is not implemented

`Organization.publishedAt` / `Production.publishedAt` are simple
now-or-null flags - `isPublished()` checks only "is this non-null", it
does not compare against the current time. Setting a future
`publishedAt` would make the entity publicly visible immediately, not
on the intended future date. This phase's seed data therefore only
represents two real states (published-now vs. unpublished/draft), not a
genuine scheduled-but-hidden "公開予定" state. A real "公開予定" feature
needs its own date-comparison logic in `isPublished()` (or a separate
visibility check) before it can be built - not attempted this phase, to
avoid faking a state the Backend can't actually produce.

## 2. Production Lifecycle Actions use PATCH, not POST

Pre-existing Backend design (`ProductionRestController::register_routes()`,
its own code comment: "Phase 6.1 Lifecycle Actions"): `start-planning`,
`activate`, `complete`, `archive`, `cancel` are all registered with
`'methods' => 'PATCH'`. This differs from Rehearsal's own lifecycle
actions (`confirm`/`activate`/`complete` on `/rehearsals/{id}/...`),
which use POST. Not a bug, not changed this phase - noted here only
because it is easy to assume both follow the same verb (the seed script
itself got this wrong on first pass and had to be corrected - see
`docs/testing/SeedData.md`).

## 3. QR code issuance/scanning deferred

Join Key issuance this phase is the 8-character code only. QR display
was already marked "将来拡張" (future extension) in the Join Key
Blueprint spec; QR scanning would additionally need camera permissions
and a JS QR-decode library, neither present in the Expo Web project.
Disclosed as deferred rather than shipped untested.

## 4. `docs/` on this branch is behind `origin/main`

This phase's Phase 1 step re-read `origin/main` for the latest Blueprint
spec (Join Key, Membership v2.2, multi-role Person, etc.) before
implementing, per this project's process. That content was read as
reference but this feature branch's own `docs/` tree was not merged
forward from `main` - `docs/04-DomainModel/JoinKey.md` and a dedicated
Favorite domain doc exist on `origin/main` (added in `1ad8d2d`/`b8cf537`)
but not on this branch. Separately, this branch's working tree already
had substantial **uncommitted** modifications to many `docs/`
files (`Membership.md`, `Production.md`, `Rehearsal.md`,
`Authorization.md`, and others) and untracked new domain docs
(`Expense.md`, `Notification.md`, `Payment.md`, etc.) plus changes under
the legacy `mobile/` Flutter app, present before this phase's work began
and unrelated to it. Those were deliberately left untouched (not staged,
not discarded) rather than guessed at or merged blind - worth the repo
owner's own attention to reconcile (likely an interrupted `git merge
origin/main` or similar from outside this session).

## 5. `docs/testing/StageArt_WebBeta_TestCases_v0.1.xlsx` is corrupted on `origin/main`

Retrieved via `git show origin/main:docs/testing/StageArt_WebBeta_TestCases_v0.1.xlsx`
to update it with this phase's test cases as instructed. The blob is the
same 11651 bytes either way, and `file` identifies it as "Microsoft Excel
2007+" (correct header), but the ZIP container has no End Of Central
Directory record (confirmed both with `unzip -l` and by inspecting the
raw bytes) - genuinely corrupted, not a local extraction artifact. It
cannot be opened/edited with it in this state. New test cases for this
phase are written instead as `docs/testing/WebBeta_NewTestCases.md`
(plain Markdown) - a valid `.xlsx` should be restored/re-supplied to
merge these into the official ledger.
