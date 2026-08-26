# StageArt Current State Audit — Web β Consolidation

Written during the "consolidate before real-browser debugging" pass
requested 2026-08-26. Records what was found, what was merged, and what
remains open. This is a **snapshot**, not a Blueprint - for business
rules, the docs it references remain authoritative.

---

# 1. GitHub investigation results

## Branch topology found

`feature/user-account-identity` (this branch, tracked as `origin/feature/user-account-identity`)
had diverged from `origin/main` at commit `0b89b3e`. Since that point:

- **`origin/main`** received **137 commits, all `docs/`-only** (zero
  commits touching `plugin/`, `mobile-rn/`, or `mobile/`) - a long,
  continuous Blueprint-writing history ending at `6d56977 docs: add
  StageArt Modular Architecture blueprint` (2026-08-26, merged this
  session while this task was in progress - `origin/main` moved forward
  again mid-task).
- **This branch** received all of the actual implementation: Backend
  (`plugin/src`), Web (`mobile-rn/src`), and one legacy Flutter scaffold
  commit (`mobile/`) - roughly 30 commits, zero of which touched `docs/`
  except this session's own `docs/testing/` additions.

**These two histories had never been reconciled before this task.**
`docs/` on this branch was over a year (137 commits) behind what
`origin/main` actually specifies, while `origin/main` had no code at
all. Neither branch was "wrong" - they were simply never merged.

**Action taken**: merged `origin/main` into this branch
(`647ffd4 Merge remote-tracking branch 'origin/main' into feature/user-account-identity`,
pushed). The merge was file-disjoint (docs-only changes on one side,
code-only on the other) and produced **zero conflicts**. Verified the
merge touched no file under `plugin/src`, `plugin/tests`, or
`mobile-rn/src` (`git diff --stat` between the pre-merge tip and the
merge commit for those paths is empty) - all prior test/build
verification for this branch's code remains valid.

This branch was **not** merged into `main`, and `main` was not pushed
to. That is a bigger, more consequential decision (main currently has
zero code; merging feature-branch code into it is a release-readiness
call) left for explicit user direction, not assumed here.

## Uncommitted working-tree content found (pre-existing, not this session's)

Before the merge, the working tree had substantial **uncommitted**
modifications to many `docs/04-DomainModel/*.md`, `docs/03-BusinessFlow.md`,
`docs/09-Authorization/Authorization.md`, `docs/10-Architecture/*.md`
files, plus untracked new files (`Expense.md`, `Notification.md`,
`Payment.md`, `ProductionSettlement.md`, `Receipt.md`,
`ReimbursementClaim.md`, `Revenue.md`, `ScheduleComment.md`,
`TicketBack.md`, `TicketSalesTarget.md`) and modifications under the
legacy `mobile/` Flutter directory.

Investigated its origin rather than guessing:
- It does **not** match `origin/main`'s content (spot-checked
  `Membership.md`: the uncommitted version claims **"Version : 2.3"**,
  while `origin/main`'s actual `Membership.md` is **"Version : 2.2"** -
  confirmed via `git log --all -p`, version 2.3 does not exist anywhere
  in this repository's git history, on any branch, local or remote).
- It is therefore a third, independent body of content with no
  git-historical provenance found - not attributable to any commit,
  stash, or branch in this repository.

**Action taken**: preserved, not discarded or guessed into place.
Stashed as `stash@{0}` with an explanatory message
("pre-existing uncommitted docs/mobile drift found at start of Web
Beta consolidation task..."), then the merge above was performed on a
clean tree. **This stash still exists locally and has not been
resolved** - recommend the repository owner review `git stash show -p
stash@{0}` and decide by hand whether it's an intentional in-progress
draft (e.g. a v2.3 Membership rewrite) that should be committed
properly, or discardable scratch content. Not resolved here because
resolving it would mean silently deciding StageArt's official
Membership spec version, which Section 0 of this task explicitly
prohibits guessing at.

## Legacy mobile asset found

`mobile/` (Flutter) is a single-commit scaffold
(`b9f05e1 feat(mobile): add Flutter project foundation`, 2026-08-15,
default Flutter template `README.md`, never touched again). `mobile-rn/`
(Expo/React Native) is the actively developed client, last touched
2026-08-26, with the full Web β feature set. `mobile/` is confirmed
**legacy/abandoned**, not a parallel active target. Not deleted here
(directory deletion is a bigger call than "docs organization" - flagged
for the repository owner's decision) but classified as such below.

## A–F classification

**A. 正式にmainへ入っているもの** (official, on `origin/main`, now
merged into this branch): all 137 Blueprint docs commits - `docs/03-ModularArchitecture.md`,
`docs/04-DomainModel/JoinKey.md`, `docs/04-HomeRoleBasedMenu.md`,
`docs/04-DomainModel/PublicPageUrlPolicy.md` /
`PublicPageGenerationPolicy.md` / `PublicSiteGenerationPolicy.md` /
`PublicSiteLifecycle.md`, Follow/Social domain docs, onboarding/wizard
docs, ticket/accounting policy docs, brand assets, legal docs, and
`docs/testing/StageArt_WebBeta_TestCases_v0.1.xlsx` (present on `main`
but corrupted - see §8 below).

**B. 作業ツリーにだけ存在するもの** (working-tree only, unresolved):
the stashed `stash@{0}` content described above (Membership.md "v2.3"
and the other untracked docs/mobile files).

**C. 古い設計・レガシー資産と思われるもの** (legacy): `mobile/` (Flutter
scaffold, superseded by `mobile-rn/`).

**D. 現在も有効な設計資料** (currently valid): everything under A, plus
this branch's pre-existing `docs/00-GoldenRule.md` through
`docs/11-UXDesign/` numbered structure, which `origin/main`'s docs
build directly on top of (no conflicts on merge confirms this).

**E. 実装済みだがdocsに反映されていないもの** (implemented, not yet in
docs): Favorite (Organization + Production) has **no dedicated Blueprint
doc** anywhere in the now-merged history - it exists only as this
session's own `docs/testing/*.md` notes and inline code comments citing
`Follow.md` for its design rationale. Everything else built this Web β
phase (Join Key, Membership/Participant approval states, search) turned
out to already have upstream Blueprint coverage once `origin/main` was
merged in (see §5 below) - a coincidence of timing, not something this
session verified against beforehand, since that Blueprint content did
not exist in this branch's history until today's merge.

**F. docsにはあるが未実装のもの** (documented, not implemented) - the
significant one:

### Public Page architecture gap

`docs/04-DomainModel/PublicPageUrlPolicy.md` and
`PublicSiteLifecycle.md` / `PublicSiteGenerationPolicy.md` /
`PublicPageGenerationPolicy.md` (all newly merged from `origin/main`)
specify a **statically-generated PHP public site** served at
`https://hatakiti.com/StageArt/[org-slug]/[production-slug]/`, with a
CRON-free but Generation-Request-driven publish pipeline, a defined
page structure (Header/Nav with ABOUT/MEMBERS/PRODUCTIONS, a
Next-Production hero, an SNS/Social section, a Latest-Past-Production
card, Footer/Contact via `[slug]@hatakiti.com` forwarding), and a
`Publicity Status` (PRIVATE/PUBLIC) + per-field scheduled-visibility
model.

What was actually built this Web β phase is materially simpler: a
single index page per Organization/Production, resolved **live** on
every request from `GET /organizations/by-slug/{slug}` /
`/productions/by-slug/{slug}` (no static generation, no CRON, no
Generation Request queue), reachable at an in-app Expo Router path
(`/o/{slug}`, `/o/{slug}/{slug}`) rather than the Blueprint's
`hatakiti.com/StageArt/...` URL, with no ABOUT/MEMBERS page split, no
SNS section, and no Contact forwarding.

This is not a bug - it was a disclosed, deliberate MVP simplification
made in an earlier phase, before this branch had ever seen the
`PublicPageUrlPolicy.md` doc that now supersedes it as the on-record
spec. It is now a **known, documented gap** between spec and
implementation, not silently carried forward. Closing it (a real static
PHP site generator, the full page structure, Contact forwarding) is a
substantial build - explicitly out of scope this round
(`03-ModularArchitecture.md` and this task's own §12 exclude "大規模UI
リニューアル"). Flagged for a dedicated future phase.

---

# 2. Docs normalization

`docs/` already uses a coherent numbered taxonomy
(`00-GoldenRule.md` … `11-UXDesign/`, plus `testing/`, `assets/`) that
`origin/main`'s 137 docs commits build on directly - the merge produced
zero conflicts, confirming this structure is still fit for purpose. Per
this task's own instruction not to force-break a working structure, **no
folder rename/move was performed.**

Added:
- `docs/modules/` (new folder - did not exist before) - Ticket/
  Rehearsal/Accounting Module boundary docs, §4 of this task.
- `docs/04-DomainModel/PublicationStateModel.md` (new) - publish-state
  model supplement, §6 of this task.
- `docs/CurrentStateAudit.md` (this file).

No file was marked `deprecated`/moved to an `archive/` folder this
round: `origin/main`'s own history had already de-duplicated its known
overlaps before this merge (e.g. `ea8516f docs: remove duplicate UX
blueprint`, `6139a95 docs: consolidate UX blueprint and remove
duplicate`) - so after the merge, no live duplicate/superseded doc pair
was found under `docs/`. The one clear archive candidate is the
**non-doc** `mobile/` Flutter directory (§1 above, C) - recommended for
the repository owner to formally archive or delete, not done here since
it's a code directory, not a docs reorganization.

---

# 3–4. Modular Architecture review

See `docs/modules/README.md`, `Rehearsal.md`, `Accounting.md`,
`Ticket.md`. Summary: Rehearsal Module (this phase's main addition) is
already reasonably well-isolated at the Domain/Database layer (ID-only
cross-references, separate tables, separate namespaces); the one
coupling finding is a naming-level one (Core's `ProductionAuthorizationService`
exposes `canManageRehearsals()`/`canManageAccounting()` by name) rather
than a data or business-rule leak, and was judged not severe enough to
refactor this round per the Blueprint's own "don't over-abstract Web β,
but don't add new unsplittable coupling" guidance.

---

# 5. Web β implementation: real wiring inventory

Re-confirmed still accurate after the merge (merge touched no code -
see §1). All of the following were verified via **real HTTP calls with
minted JWT Bearer tokens** against the live ConoHa dev API (not just
in-process `rest_do_request()`), in the session immediately preceding
this consolidation task:

| Feature | Backend | API | Frontend | DB | Verified via |
|---|---|---|---|---|---|
| Join Key issuance/disable | Yes | Yes | Yes | `stageart_join_keys` | Live curl (org + production) |
| Membership request/approve/reject | Yes | Yes | Yes | `stageart_memberships` | Live curl (list pending, real REQUESTED row returned) |
| Participation request/approve/reject | Yes | Yes | Yes | `stageart_participants` | In-process E2E (not re-verified via real HTTP this round) |
| Organization/Production public pages | Yes | Yes | Yes | live query | Live curl, unauthenticated |
| Organization/Production search | Yes | Yes | Yes | live query | Live curl, unauthenticated |
| Favorite add/remove/list | Yes | Yes | Yes | `stageart_favorites` | Live curl |
| Rehearsal create/confirm/manage | Yes | Yes | Yes | `stageart_rehearsals` | In-process E2E (not re-verified via real HTTP this round) |
| Multi-role Person (Org Owner + Production Manager + general Participant simultaneously) | Yes | Yes | N/A | seed data confirms | Direct DB read of seeded rows |

No mock-only UI was found for any of the above - every screen touched
this Web β phase reads/writes through its real REST endpoint. The one
UI-only stub still in the app is unrelated to this phase's work: QR
code display/scanning (see below), which the Blueprint itself marks
"将来拡張" and which this session never built a UI stub for at all (not
even a disabled button) - simply absent.

## QR Join (this task's §7)

`docs/04-DomainModel/JoinKey.md` (newly merged from `origin/main`)
already fully specifies the relationship this task asked to confirm:
QR is never a new Identity or Membership path - it is an input-
convenience encoding of the same Join Key (§"QR Code": "QRコード自体を
新しいIdentityとして作成しない...将来のQRコード導入後もDomainの中心は
Join Keyであり続ける"). The implemented Join Key flow (issue → 8-char
code → resolve → confirm target → request) already matches this
document's "Resolve and Confirm" section exactly. No new design doc was
needed - QR display/scanning itself remains unbuilt, exactly as the
Blueprint marks it "将来拡張," and requires camera permissions + a QR
decode library neither present in the Expo Web project.

---

# 6. Publication state model

See `docs/04-DomainModel/PublicationStateModel.md` (new). Confirms the
existing Blueprint (`PublicPageGenerationPolicy.md`) already specifies
a CRON-free, time-comparison-based scheduled-visibility mechanism;
documents the DRAFT/SCHEDULED/PUBLISHED/ARCHIVED mapping onto that
mechanism; states plainly that the current implementation
(`publishedAt` null-or-set, no time comparison) does not yet implement
it; not implemented this round per this task's own exclusion list.

---

# Open items for the repository owner

1. Resolve `stash@{0}` (the unexplained "Membership v2.3" and related
   uncommitted content) by hand - its provenance could not be
   determined from git history.
2. Decide whether to merge `feature/user-account-identity` into `main`
   (not done this round - `main` currently has no code).
3. Decide the fate of the legacy `mobile/` Flutter directory (archive
   or delete).
4. Decide when to schedule the Public Page architecture gap (§1, F)
   as its own phase.
