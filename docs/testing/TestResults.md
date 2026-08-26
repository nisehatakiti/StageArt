# StageArt Web β版 Test Results

Manual (browser) verification results, tracked separately from
automated test results per this project's rule: **automated test
passing is never treated as "manual PASS"**, and nothing here is
pre-set to PASS.

Values: `NOT RUN` (default) | `PASS` | `FAIL` | `BLOCKED`.

Regenerated alongside `StageArt_WebBeta_TestCases.xlsx` from
`TestCases.md` - see `docs/testing/SeedData.md` for seed data setup
before running these.


## AUTH

| ID | Feature | Automated Status | Manual Result |
|---|---|---|---|
| AUTH-01 | Email/password login | Automated (see TestCases.md) | NOT RUN |
| AUTH-02 | Login screen renders | Automated (see TestCases.md) | NOT RUN |
| AUTH-03 | Email/password registration | Automated (see TestCases.md) | NOT RUN |
| AUTH-04 | Registration pending → back to login | Automated (see TestCases.md) | NOT RUN |
| AUTH-05 | Google sign-in flow | Automated (see TestCases.md) | NOT RUN |
| AUTH-06 | Google sign-in module missing (native) | Automated (see TestCases.md) | NOT RUN |
| AUTH-07 | Boot with existing session | Automated (see TestCases.md) | NOT RUN |
| AUTH-08 | Logout | Automated (see TestCases.md) | NOT RUN |
| AUTH-09 | Logout cancel | Automated (see TestCases.md) | NOT RUN |

## NAVIGATION

| ID | Feature | Automated Status | Manual Result |
|---|---|---|---|
| NAV-01 | Core tab/route navigation | Automated (see TestCases.md) | NOT RUN |
| NAV-02 | Home → Production shell | Automated (see TestCases.md) | NOT RUN |
| NAV-03 | Web-only routes not exposed as extra native tabs | Automated (see TestCases.md) | NOT RUN |

## HOME

| ID | Feature | Automated Status | Manual Result |
|---|---|---|---|
| HOME-01 | Dashboard content, normal case | Automated (see TestCases.md) | NOT RUN |
| HOME-02 | Dashboard when Production fetch errors, Organizations OK | Automated (see TestCases.md) | NOT RUN |
| HOME-03 | Dashboard when Organization fetch errors, Productions OK | Automated (see TestCases.md) | NOT RUN |
| HOME-04 | Empty Organizations state | Automated (see TestCases.md) | NOT RUN |
| HOME-05 | Empty Productions state | Automated (see TestCases.md) | NOT RUN |
| HOME-06 | Multi-organization switch | Automated (see TestCases.md) | NOT RUN |
| HOME-07 | 401 on Organizations fetch | Automated (see TestCases.md) | NOT RUN |
| HOME-08 | 403 on Organizations fetch | Automated (see TestCases.md) | NOT RUN |
| HOME-09 | Single-organization case | Automated (see TestCases.md) | NOT RUN |
| HOME-10 | Unaffiliated general user sees no false-empty-state text | Automated (see TestCases.md) | NOT RUN |
| HOME-11 | Next Rehearsal shown only when one exists | Automated (see TestCases.md) | NOT RUN |

## ORGANIZATION

| ID | Feature | Automated Status | Manual Result |
|---|---|---|---|
| ORG-01 | Create Organization | Automated (see TestCases.md) | NOT RUN |
| ORG-02 | Set Organization public info + publish | Automated (see TestCases.md) | NOT RUN |
| ORG-03 | Organization Owner without PrimaryManager role cannot manage a Production | Automated (see TestCases.md) | NOT RUN |

## PRODUCTION

| ID | Feature | Automated Status | Manual Result |
|---|---|---|---|
| PROD-01 | Create Production under an Organization | Automated (see TestCases.md) | NOT RUN |
| PROD-02 | Production lifecycle transitions use PATCH | Automated (see TestCases.md) | NOT RUN |
| PROD-03 | Publish/unpublish independent of Lifecycle status | Automated (see TestCases.md) | NOT RUN |

## PUBLIC

| ID | Feature | Automated Status | Manual Result |
|---|---|---|---|
| PUBLIC-01 | Public Organization page, published | Automated (see TestCases.md) | NOT RUN |
| PUBLIC-02 | Public Organization page, unpublished or nonexistent | Automated (see TestCases.md) | NOT RUN |
| PUBLIC-03 | Public Production page, published | Automated (see TestCases.md) | NOT RUN |
| PUBLIC-04 | Public Production page, unpublished | Automated (see TestCases.md) | NOT RUN |

## SEARCH

| ID | Feature | Automated Status | Manual Result |
|---|---|---|---|
| SEARCH-01 | Organization search, unauthenticated | Automated (see TestCases.md) | NOT RUN |
| SEARCH-02 | Production search, unauthenticated | Automated (see TestCases.md) | NOT RUN |
| SEARCH-03 | Empty/whitespace query | Automated (see TestCases.md) | NOT RUN |

## MEMBERSHIP

| ID | Feature | Automated Status | Manual Result |
|---|---|---|---|
| MEM-01 | Request Organization membership via search | Automated (see TestCases.md) | NOT RUN |
| MEM-02 | Admin approves pending request | Automated (see TestCases.md) | NOT RUN |
| MEM-03 | Admin rejects pending request | Automated (see TestCases.md) | NOT RUN |
| MEM-04 | Re-request after rejection | Automated (see TestCases.md) | NOT RUN |
| MEM-05 | List my memberships (real HTTP) | Automated (see TestCases.md) | NOT RUN |
| MEM-06 | List pending requests (real HTTP) | Automated (see TestCases.md) | NOT RUN |

## JOIN KEY

| ID | Feature | Automated Status | Manual Result |
|---|---|---|---|
| JOIN-01 | Issue Organization Join Key | Automated (see TestCases.md) | NOT RUN |
| JOIN-02 | Issue Production Join Key | Automated (see TestCases.md) | NOT RUN |
| JOIN-03 | Resolve a Join Key | Automated (see TestCases.md) | NOT RUN |
| JOIN-04 | Join via code → request created | Automated (see TestCases.md) | NOT RUN |
| JOIN-05 | Disable a Join Key | Automated (see TestCases.md) | NOT RUN |
| JOIN-06 | Expired/exhausted key rejected | Automated (see TestCases.md) | NOT RUN |
| JOIN-07 | QR is a representation of Join Key, not a separate identity | Automated (see TestCases.md) | NOT RUN |

## FAVORITE

| ID | Feature | Automated Status | Manual Result |
|---|---|---|---|
| FAV-01 | Favorite an Organization | Automated (see TestCases.md) | NOT RUN |
| FAV-02 | Favorite a Production | Automated (see TestCases.md) | NOT RUN |
| FAV-03 | Remove a Favorite | Automated (see TestCases.md) | NOT RUN |
| FAV-04 | Cannot favorite an unpublished target | Automated (see TestCases.md) | NOT RUN |

## REHEARSAL

| ID | Feature | Automated Status | Manual Result |
|---|---|---|---|
| REH-01 | Create a Rehearsal | Automated (see TestCases.md) | NOT RUN |
| REH-02 | Confirm a Rehearsal | Automated (see TestCases.md) | NOT RUN |
| REH-03 | Full lifecycle: confirm → activate → complete | Automated (see TestCases.md) | NOT RUN |
| REH-04 | Edit basic info on a non-terminal Rehearsal | Automated (see TestCases.md) | NOT RUN |
| REH-05 | Cannot edit a COMPLETED/CANCELLED Rehearsal | Automated (see TestCases.md) | NOT RUN |

## ATTENDANCE

| ID | Feature | Automated Status | Manual Result |
|---|---|---|---|
| ATT-01 | Unconfirmed Rehearsal shows no response control | Automated (see TestCases.md) | NOT RUN |
| ATT-02 | Confirmed Rehearsal shows response control | Automated (see TestCases.md) | NOT RUN |
| ATT-03 | Manager views per-member attendance status | Automated (see TestCases.md) | NOT RUN |
| ATT-04 | Respond to attendance | Automated (see TestCases.md) | NOT RUN |

## ROLE

| ID | Feature | Automated Status | Manual Result |
|---|---|---|---|
| ROLE-01 | One Person holds multiple simultaneous roles | Automated (see TestCases.md) | NOT RUN |
| ROLE-02 | Organization ownership does not imply Production management | Automated (see TestCases.md) | NOT RUN |
| ROLE-03 | PrimaryManager-exclusive gate has no Organization-fallback path | Automated (see TestCases.md) | NOT RUN |

## SEED DATA

| ID | Feature | Automated Status | Manual Result |
|---|---|---|---|
| SEED-01 | Seed script creates all 5 roles + edge states | Automated (see TestCases.md) | NOT RUN |
| SEED-02 | Cleanup removes only demo data | Automated (see TestCases.md) | NOT RUN |
| SEED-03 | Cleanup is a no-op on a clean DB | Automated (see TestCases.md) | NOT RUN |
| SEED-04 | Re-seed after cleanup is idempotent | Automated (see TestCases.md) | NOT RUN |
