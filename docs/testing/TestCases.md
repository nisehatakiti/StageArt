# StageArt Web β版 Test Cases (canonical, Markdown source)

This is the **canonical, GitHub-diffable source** for StageArt Web β
test cases, per this project's decision to use a two-layer structure:
this file is the source of truth; `StageArt_WebBeta_TestCases.xlsx`
(regenerated from this file - see `docs/testing/SeedData.md` for how it
was produced) is for spreadsheet-based manual test execution/tracking.
Manual PASS/FAIL results live in `TestResults.md`, never in this file.

Seed data referenced throughout: `docs/testing/SeedData.md`.

Columns: **ID | Category | Feature | Precondition | Steps | Expected
Result | Automated?**. "Automated?" cites the actual test file/method
that covers it, or "Live curl" for this session's real-HTTP
verification, or "None" if genuinely only covered by manual browser
verification.

---

## AUTH

| ID | Feature | Precondition | Steps | Expected Result | Automated? |
|---|---|---|---|---|---|
| AUTH-01 | Email/password login | Registered account exists | Enter email+password on /login → submit | Redirected to Home, authenticated | `mobile-rn/src/app-tests/login-flow.test.tsx` |
| AUTH-02 | Login screen renders | None | Open /login | Form fields + Google sign-in entry visible | `login-screen-render.test.tsx` |
| AUTH-03 | Email/password registration | New email | Fill /register form → submit | Account created; verification/pending state shown | `register-flow.test.tsx`, `register-screen-render.test.tsx` |
| AUTH-04 | Registration pending → back to login | Just registered, unverified | Tap back-to-login on pending screen | Returns to /login | `registration-pending-back-to-login.test.tsx` |
| AUTH-05 | Google sign-in flow | Google account available | Tap Google sign-in | Authenticated via Google, reaches Home | `google-login-flow.test.tsx` |
| AUTH-06 | Google sign-in module missing (native) | RNGoogleSignin not registered | Tap Google sign-in | Clear error surfaced, not swallowed | `google-login-module-not-found.test.tsx` |
| AUTH-07 | Boot with existing session | Valid session persisted | Open app fresh | Lands authenticated on Home, no login prompt | `index-boot-authenticated.test.tsx` |
| AUTH-08 | Logout | Logged in | Home → logout button → confirm | Session cleared, returns to /login | `home-logout.test.tsx`, `home-logout-button.test.tsx` |
| AUTH-09 | Logout cancel | Logged in | Home → logout button → cancel dialog | Stays logged in | `home-logout-button-cancel.test.tsx` |

## NAVIGATION

| ID | Feature | Precondition | Steps | Expected Result | Automated? |
|---|---|---|---|---|---|
| NAV-01 | Core tab/route navigation | Logged in | Navigate between Home/Production/Schedule/Accounting/Notifications | Each route renders its own screen, state preserved appropriately | `navigation.test.tsx` |
| NAV-02 | Home → Production shell | Logged in, has a Production | Tap a Production card | Enters `/production/[id]` shell | `home-to-production.test.tsx` |
| NAV-03 | Web-only routes not exposed as extra native tabs | Web build | `expo export --platform web` | Route list matches expected screen count, no unintended auto-registered tab | Manual `expo export` output review (see completion reports; not a Jest assertion) |

## HOME

| ID | Feature | Precondition | Steps | Expected Result | Automated? |
|---|---|---|---|---|---|
| HOME-01 | Dashboard content, normal case | Logged in, has Organizations/Productions | Open Home | Dashboard sections populated | `home-dashboard-content.test.tsx` |
| HOME-02 | Dashboard when Production fetch errors, Organizations OK | Simulated partial failure | Open Home | Organizations shown, Production section degrades gracefully | `home-dashboard-error-productions-ok.test.tsx` |
| HOME-03 | Dashboard when Organization fetch errors, Productions OK | Simulated partial failure | Open Home | Inverse of HOME-02 | `home-dashboard-ok-productions-error.test.tsx` |
| HOME-04 | Empty Organizations state | No Organizations | Open Home | Appropriate empty state, no crash | `home-empty-organizations.test.tsx` |
| HOME-05 | Empty Productions state | No Productions | Open Home | Appropriate empty state, no crash | `home-empty-productions.test.tsx` |
| HOME-06 | Multi-organization switch | Belongs to 2+ Organizations | Switch active Organization on Home | Dashboard content updates to the selected Organization's scope | `home-multi-org-switch.test.tsx` |
| HOME-07 | 401 on Organizations fetch | Session invalid | Open Home | Handled without crash (redirect/error state) | `home-organizations-401.test.tsx` |
| HOME-08 | 403 on Organizations fetch | Insufficient permission | Open Home | Handled without crash | `home-organizations-403.test.tsx` |
| HOME-09 | Single-organization case | Belongs to exactly 1 Organization | Open Home | No org-switcher UI shown unnecessarily | `home-single-org.test.tsx` |
| HOME-10 | Unaffiliated general user sees no false-empty-state text | Logged in, zero Memberships/Participants anywhere | Open Home | No "所属していません"/"次回稽古なし" style placeholder text; only baseline sections (団体を探す/公演を探す/お気に入り/設定) | None - manual browser check required (WB-16 in `WebBeta_NewTestCases.md`) |
| HOME-11 | Next Rehearsal shown only when one exists | Has vs. does not have an upcoming confirmed Rehearsal | Open Home in both cases | Rehearsal card appears only in the first case | None - manual browser check required (WB-15) |

## ORGANIZATION

| ID | Feature | Precondition | Steps | Expected Result | Automated? |
|---|---|---|---|---|---|
| ORG-01 | Create Organization | Logged in | Organizations → create → name/slug/description → save | Organization created, current user becomes Owner | Pre-Web-β Application/Use Case tests (see `plugin/tests/Application/Organization/`) |
| ORG-02 | Set Organization public info + publish | Owner of an Organization | Edit description → toggle published | `published_at` set; public page becomes reachable | Live curl this session (seed script's PUT+publish calls) |
| ORG-03 | Organization Owner without PrimaryManager role cannot manage a Production | Owner of Org, not PrimaryManager of one of its Productions | Attempt to issue a Production Join Key or run a lifecycle action on that Production | Denied (403) - Org ownership does not grant Production management | Live curl (PATCH `.../start-planning` as non-PrimaryManager) |

## PRODUCTION

| ID | Feature | Precondition | Steps | Expected Result | Automated? |
|---|---|---|---|---|---|
| PROD-01 | Create Production under an Organization | Owner, Organization exists | Create Production with name/slug | Production created in DRAFT | Pre-Web-β Use Case tests |
| PROD-02 | Production lifecycle transitions use PATCH | PrimaryManager | `PATCH /productions/{id}/start-planning` → `activate` → `complete` → `archive` | Each transition succeeds, status advances DRAFT→PLANNING→ACTIVE→COMPLETED→ARCHIVED | Live curl this session |
| PROD-03 | Publish/unpublish independent of Lifecycle status | Any Production | Toggle `published` at various Lifecycle states | `published_at` changes independently of `status` | Live curl (seed script publishes a DRAFT-lifecycle and an ARCHIVED-lifecycle Production both) |

## PUBLIC

| ID | Feature | Precondition | Steps | Expected Result | Automated? |
|---|---|---|---|---|---|
| PUBLIC-01 | Public Organization page, published | Published Organization exists | `GET /organizations/by-slug/{slug}`, unauthenticated | 200, name/description/slug returned | Live curl this session |
| PUBLIC-02 | Public Organization page, unpublished or nonexistent | Draft Organization, or a random slug | Same request | 404 identically for both - never reveals which case it was | Application-layer Use Case test |
| PUBLIC-03 | Public Production page, published | Published Production exists | `GET /productions/by-slug/{slug}`, unauthenticated | 200, includes resolved parent Organization | Live curl this session |
| PUBLIC-04 | Public Production page, unpublished | Draft Production | Same request | 404 | Application-layer Use Case test |
| PUBLIC-URL-001 | Organization official URL | Published Organization with slug `sample-theatre-company` | Browser: `/sample-theatre-company` (or `stageart.top/sample-theatre-company` once deployed there) | Organization Public Page renders (name, description, production lists) | Manual browser check required; route existence confirmed via `expo export` (36 static routes incl. `/[organizationSlug]`) |
| PUBLIC-URL-002 | Production official URL | Published Production with slug `sample-summer-show` under `sample-theatre-company` | Browser: `/sample-theatre-company/sample-summer-show` | Production Public Page renders (name, title heading, parent Organization link) | Manual browser check required; route existence confirmed via `expo export` (`/[organizationSlug]/[productionSlug]`) |
| PUBLIC-URL-003 | Unauthenticated viewing | Not logged in | Visit PUBLIC-URL-001/002's URLs with no session | Pages render fully; Follow/Favorite/Membership-request controls show a "ログインして..." prompt instead of being hidden entirely | Live curl this session (unauthenticated GET succeeds); `public-organization-login-to-follow` testID exists in code |
| PUBLIC-URL-004 | PUBLISHED is viewable | Organization/Production with `published_at` in the past | `GET /organizations/by-slug/{slug}` / `/productions/by-slug/{slug}` | 200 | Live curl this session |
| PUBLIC-URL-005 | DRAFT is not viewable | Organization/Production with `published_at = null` | Same requests | 404, identical to a nonexistent slug | `GetPublicOrganizationBySlugUseCaseTest` / `GetPublicProductionBySlugUseCaseTest` |
| PUBLIC-URL-006 | SCHEDULED before its publish date | `published_at` set to a future datetime | Same requests | 404 - not yet visible, same as DRAFT | Live curl this session (PUT with future `published_at` → GET by-slug → 404); `test_scheduled_organization_before_its_publish_date_is_not_found` / `test_scheduled_production_before_its_publish_date_is_not_found` |
| PUBLIC-URL-007 | SCHEDULED after its publish date | `published_at` set to a past/now datetime (previously future, time has passed) | Same requests | 200 - now visible, same as an immediately-published item | Live curl this session (PUT with past `published_at` → GET by-slug → 200); `test_scheduled_organization_after_its_publish_date_is_visible` / `test_scheduled_production_after_its_publish_date_is_visible` |
| PUBLIC-URL-008 | ARCHIVED Production handling | Production Lifecycle status ARCHIVED, still published | `GET /productions/by-slug/{slug}` | 200 - publication state (`published_at`) is independent of Lifecycle `status`; an ARCHIVED Production's public page keeps showing as a record, per `PublicSiteLifecycle.md`'s "公演終了後もProduction Public Siteは削除せず" | Live curl this session (seed's `sample-last-years-show`, status ARCHIVED, published, resolves 200); appears in its Organization's "過去の公演" list (`status` proxy, see `PublicOrganizationProductionSummary`) |
| PUBLIC-URL-009 | Search result → Public Page navigation | Search returns a published Organization/Production | Tap a search result on `/discover-organizations` or `/discover-productions` | Navigates to `/{organization-slug}` or `/{organization-slug}/{production-slug}` (root-level URL, not the old `/o/{slug}` prefix) | tsc/lint clean on the updated `router.push()` call sites; manual browser click-through still required |
| PUBLIC-URL-010 | Favorite from the Public Page | Logged in, viewing a published Organization/Production page | Tap お気に入りに追加/解除 | Favorite toggles; reflected in `/favorites`, whose own links now also point to the root-level URL | Live curl this session (POST/DELETE /favorites); `favorites.tsx`'s `targetHref()` updated to root-level paths |

## SEARCH

| ID | Feature | Precondition | Steps | Expected Result | Automated? |
|---|---|---|---|---|---|
| SEARCH-01 | Organization search, unauthenticated | Published demo org exists | `GET /organizations/search?q=...` | Matching published org(s) returned; unpublished never appear | Live curl this session |
| SEARCH-02 | Production search, unauthenticated | Published demo production exists | `GET /productions/search?q=...` | Matching published production(s) returned; unpublished never appear | Live curl this session |
| SEARCH-03 | Empty/whitespace query | Any | Submit search with no text | Empty result, no error | `SearchOrganizationsUseCaseTest` / `SearchProductionsUseCaseTest` |

## MEMBERSHIP

| ID | Feature | Precondition | Steps | Expected Result | Automated? |
|---|---|---|---|---|---|
| MEM-01 | Request Organization membership via search | Logged in, published Org found via search | View org public page → request membership | Membership row created, status REQUESTED | `rest_do_request` in-process E2E |
| MEM-02 | Admin approves pending request | Owner, REQUESTED Membership exists | Approve from pending-requests list | Status → ACTIVE, `joined_at` set | `rest_do_request` in-process E2E |
| MEM-03 | Admin rejects pending request | Owner, REQUESTED Membership exists | Reject from pending-requests list | Status → REJECTED | `rest_do_request` in-process E2E |
| MEM-04 | Re-request after rejection | Previously REJECTED Membership | Request again | New Membership row created (REQUESTED); old REJECTED row kept as history, no unique-constraint conflict | `MembershipTest.php` (Domain), confirms `requestMembership()`/`approve()`/`reject()` state guards |
| MEM-05 | List my memberships (real HTTP) | Logged in | `GET /me/memberships` with real Bearer token | Returns this Person's Membership rows with correct statuses | Live curl this session |
| MEM-06 | List pending requests (real HTTP) | Owner, at least one REQUESTED Membership | `GET /organizations/{id}/membership-requests` with real Bearer token | Returns the REQUESTED row | Live curl this session |

## JOIN KEY

| ID | Feature | Precondition | Steps | Expected Result | Automated? |
|---|---|---|---|---|---|
| JOIN-01 | Issue Organization Join Key | Owner | Issue a Join Key | 8-character code returned, status ACTIVE | Live curl this session |
| JOIN-02 | Issue Production Join Key | PrimaryManager | Issue a Join Key | 8-character code returned | Live curl this session |
| JOIN-03 | Resolve a Join Key | Valid code | `POST /join-keys/resolve` | Returns target type (ORGANIZATION/PRODUCTION) + target identity, does not create a Membership/Participant yet | `ResolveJoinKeyUseCase` Application test |
| JOIN-04 | Join via code → request created | Valid, usable code | Enter code on `/join`, confirm | Membership or Participant request created in the correct pending state | `rest_do_request` in-process E2E |
| JOIN-05 | Disable a Join Key | ACTIVE key exists | Disable it | Status → DISABLED; further resolve/use attempts fail; past Memberships/Participants from it are not retroactively removed | `JoinKeyTest.php` (Domain) |
| JOIN-06 | Expired/exhausted key rejected | Key past `expires_at` or at `max_uses` | Attempt to use | Rejected, `isUsable()` false | `JoinKeyTest.php` (Domain) |
| JOIN-07 | QR is a representation of Join Key, not a separate identity | N/A | (Design confirmation, not a runtime test) | QR encodes the Join Key/URL only; scanning still routes through the same resolve→confirm→request flow | `docs/04-DomainModel/JoinKey.md` §"QR Code" (Blueprint, unimplemented UI - camera/QR decode not built) |

## FAVORITE

| ID | Feature | Precondition | Steps | Expected Result | Automated? |
|---|---|---|---|---|---|
| FAV-01 | Favorite an Organization | Logged in, published org | POST /favorites (ORGANIZATION) | Row created; appears in `/me/favorites` | Live curl this session |
| FAV-02 | Favorite a Production | Logged in, published production | POST /favorites (PRODUCTION) | Row created, resolves parent org slug | Live curl this session |
| FAV-03 | Remove a Favorite | Favorite exists | DELETE /favorites?target_type=...&target_id=... | Row removed; repeat call is a harmless no-op | Live curl this session |
| FAV-04 | Cannot favorite an unpublished target | Draft Production | POST /favorites against it | Rejected (not found/not public) | `AddFavoriteUseCase` Application test |

## REHEARSAL

| ID | Feature | Precondition | Steps | Expected Result | Automated? |
|---|---|---|---|---|---|
| REH-01 | Create a Rehearsal | PrimaryManager, published Production | Fill title/date/place → save | Rehearsal created, SCHEDULED | `rest_do_request` in-process E2E |
| REH-02 | Confirm a Rehearsal | SCHEDULED Rehearsal | Confirm | Status → CONFIRMED; Phase 2 Attendance records generated for active members | `rest_do_request` in-process E2E, `Rehearsal.php` Domain guard (only SCHEDULED can confirm) |
| REH-03 | Full lifecycle: confirm → activate → complete | SCHEDULED Rehearsal | Run all 3 transitions | Status advances correctly, rejects out-of-order calls | Domain-level status-guard unit tests |
| REH-04 | Edit basic info on a non-terminal Rehearsal | SCHEDULED/CONFIRMED/ACTIVE Rehearsal | Edit title/date/location | Updated | `Rehearsal.php` Domain test |
| REH-05 | Cannot edit a COMPLETED/CANCELLED Rehearsal | Terminal-state Rehearsal | Attempt edit | Rejected | `Rehearsal.php` Domain test |

## ATTENDANCE

| ID | Feature | Precondition | Steps | Expected Result | Automated? |
|---|---|---|---|---|---|
| ATT-01 | Unconfirmed Rehearsal shows no response control | Participant, unconfirmed Rehearsal | View it | No attendance-response entry point shown | `rest_do_request` in-process E2E |
| ATT-02 | Confirmed Rehearsal shows response control | Participant, confirmed Rehearsal | View it | Attendance-response entry point appears | `rest_do_request` in-process E2E |
| ATT-03 | Manager views per-member attendance status | PrimaryManager, confirmed Rehearsal with responses | Open attendance summary | Correct per-member status list | `ListRehearsalAttendancesUseCase` Application test |
| ATT-04 | Respond to attendance | Participant, confirmed Rehearsal | Submit a response | `RehearsalAttendance` status updated | `RespondRehearsalAttendanceUseCase` Application test |

## ROLE

| ID | Feature | Precondition | Steps | Expected Result | Automated? |
|---|---|---|---|---|---|
| ROLE-01 | One Person holds multiple simultaneous roles | Seed data: `stageart_demo_production_manager` is both an Org A Member and the PrimaryManager of all 3 demo Productions | Inspect Memberships/Participants for that Person | Multiple active role rows across different scopes coexist correctly | Direct DB read of seed data (this session) |
| ROLE-02 | Organization ownership does not imply Production management | Org Owner not PrimaryManager of a given Production | Attempt a Production-management action as the Owner | Denied | Live curl this session (see ORG-03) |
| ROLE-03 | PrimaryManager-exclusive gate has no Organization-fallback path | `ProductionAuthorizationService::canManageProduction()` | Code review | Exactly two paths: PrimaryManager, then ProductionDelegate - no third branch to Organization Membership | Source review, `docs/09-Authorization/Authorization.md` |

## SEED DATA

| ID | Feature | Precondition | Steps | Expected Result | Automated? |
|---|---|---|---|---|---|
| SEED-01 | Seed script creates all 5 roles + edge states | Clean or previously-cleaned dev DB | Run `plugin/scripts/seed_web_beta.php` | 7 demo users, 2 Orgs, 3 Productions (published/draft/archived), Memberships in ACTIVE/REQUESTED/REJECTED, Rehearsals with/without confirmation, 1 past-completed | Ran this session, verified via direct DB reads - see `docs/testing/SeedData.md` |
| SEED-02 | Cleanup removes only demo data | Seed data present, plus unrelated real data | Run `seed_web_beta_cleanup.php` | Only `stageart_demo_*` users and `[Sample]`-prefixed Orgs/Productions (and their dependents) removed; other data untouched | Ran this session - confirmed a real, non-demo Membership row survived cleanup untouched |
| SEED-03 | Cleanup is a no-op on a clean DB | No demo data present | Run cleanup | Zero rows affected, no errors | Ran this session |
| SEED-04 | Re-seed after cleanup is idempotent | Just cleaned | Run seed again | Completes with no unique-constraint conflicts | Ran this session |
