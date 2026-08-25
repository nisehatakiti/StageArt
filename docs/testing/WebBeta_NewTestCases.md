# Web β版 New Test Cases — Join/Approval, Rehearsal Management, Favorite

The official ledger (`docs/testing/StageArt_WebBeta_TestCases_v0.1.xlsx`)
is currently corrupted on `origin/main` and cannot be opened/edited (see
`docs/testing/WebBeta_Phase_Deviations.md` §5) - these cases are written
here instead, for merging into the official ledger once a valid copy is
available. **PASS/FAIL is left blank for the user's own browser
verification** - nothing here has been checked visually in a browser;
"Verified via" notes what automated/API-level check, if any, backs the
implementation.

Seed data for manually running these: `docs/testing/SeedData.md`.

| # | Feature | Precondition | Steps | Expected Result | Verified via | PASS/FAIL |
|---|---|---|---|---|---|---|
| WB-01 | Organization Join Key issuance | Logged in as an Organization Owner (`stageart_demo_org_owner`) | Go to the Organization's invite/管理 screen → issue a Join Key | An 8-character code is shown; a second issuance can disable the prior one | Live curl (POST /organizations/{id}/join-keys → 201) | |
| WB-02 | Join with a valid Organization Key | Logged in as a general user; have a valid Org Join Key code | Settings → 所属Key入力 → enter the code → confirm | A Membership is created with status REQUESTED; confirmation message shown | Live curl (POST /membership-requests {join_key_code}) | |
| WB-03 | Organization admin approves a pending request | Logged in as the Org Owner; a REQUESTED Membership exists (seed: `stageart_demo_applicant`) | Open the pending-requests list → approve | Membership status becomes ACTIVE; applicant now sees "所属しています" on the org page | rest_do_request in-process E2E | |
| WB-04 | Organization admin rejects a pending request | Same as WB-03 | Open the pending-requests list → reject | Membership status becomes REJECTED; applicant can re-request (new Membership row, old one kept as history) | rest_do_request in-process E2E | |
| WB-05 | Production Join Key issuance | Logged in as the Production's PrimaryManager (`stageart_demo_production_manager`) - NOT the Org Owner unless they are also PrimaryManager | Production invite screen → issue a Join Key | 8-character code shown | Live curl (POST /productions/{id}/join-keys → 201) | |
| WB-06 | Join a Production with a valid Key, choosing CAST/STAFF | Logged in as a general user; valid Production Join Key | Public production page or /join → enter code → choose 出演者/スタッフ → submit | A Participant row is created, status PENDING/REQUESTED with the chosen type | rest_do_request in-process E2E | |
| WB-07 | Production admin approves/rejects a participation request | Logged in as PrimaryManager; a pending Participant exists | Production invite screen → approve or reject | Participant status becomes ACTIVE or REJECTED accordingly | rest_do_request in-process E2E | |
| WB-08 | Organization Owner without PrimaryManager role cannot manage a Production | Logged in as an Organization Owner who is not the Production's PrimaryManager | Attempt to issue a Production Join Key or run a lifecycle action | Request is denied (403/ProductionAccessDenied) - Organization ownership does not grant Production management | Live curl (PATCH .../start-planning as non-PrimaryManager) | |
| WB-09 | Public Organization search | Not logged in, or logged in as any user | 団体を探す → type part of "[Sample] 劇団サンプル座" | The demo org appears in results; unpublished orgs never appear | Live curl (GET /organizations/search?q=...) | |
| WB-10 | Public Production search | Same as WB-09 | 公演・活動を探す → type part of "[Sample] 夏の公演" | The published demo production appears; the unpublished/draft one never appears | Live curl (GET /productions/search?q=...) | |
| WB-11 | Empty/whitespace search query | Any user | Search screen → submit with no text | Empty result list, no error | Application-layer Use Case test | |
| WB-12 | Rehearsal creation | Logged in as PrimaryManager; a published Production exists | Attendance screen → ＋稽古を作成する → fill title/date/place → save | A new Rehearsal is created in an unconfirmed state | rest_do_request in-process E2E | |
| WB-13 | Rehearsal confirm → attendance response becomes available | Logged in as PrimaryManager, then as a Participant | Confirm the Rehearsal (manager) → view it as a Participant | Manager: rehearsal now shows as confirmed; Participant: an attendance-response control appears where none did before | rest_do_request in-process E2E | |
| WB-14 | Unconfirmed Rehearsal shows no attendance-response entry | Logged in as a Participant; an unconfirmed Rehearsal exists (seed: "顔合わせ") | View the Rehearsal | No attendance-response control shown, only the schedule info | rest_do_request in-process E2E | |
| WB-15 | Home shows next Rehearsal only when one exists | Logged in as a Participant with an upcoming confirmed Rehearsal, vs. one with none | View Home | Home shows the next Rehearsal card only in the first case; no placeholder/empty-state text in the second | Manual browser check required (not automatable from this session) | |
| WB-16 | Unaffiliated general user never sees "not affiliated" placeholder text | Logged in as `stageart_demo_general` (no Membership/Participant anywhere) | View Home | No "所属していません" / "次回稽古なし" style text anywhere; only the baseline general-user sections (団体を探す/公演を探す/お気に入り/設定) | Manual browser check required | |
| WB-17 | Favorite an Organization | Logged in as any user; viewing a published org's public page | Tap お気に入りに追加 | Button toggles to お気に入り解除; org appears in /favorites | Live curl (POST /favorites, GET /me/favorites) | |
| WB-18 | Favorite a Production | Same as WB-17, on a published production's public page | Tap お気に入りに追加 | Same toggle behavior; appears in /favorites list with its parent org resolved | Live curl (POST /favorites, GET /me/favorites) | |
| WB-19 | Remove a Favorite | A Favorite exists (seed: general user has favorited Org A and the summer production) | /favorites → remove one | Item disappears from the list immediately; a second removal of the same item is a harmless no-op | Live curl (DELETE /favorites?target_type=...&target_id=...) | |
| WB-20 | Favoriting an unpublished target is rejected | Logged in as any user; know the ID of the unpublished demo production | Attempt POST /favorites against the draft production's id | Request is rejected (target not found/not public) | Application-layer Use Case test (`AddFavoriteUseCase` requires published) | |

## End-to-end scenarios (the two 到達目標 flows)

These are not separate rows above because they are compositions of
WB-01 through WB-19 in sequence - listed here as the two scenarios the
user should actually walk through in-browser, matching this phase's own
definition of done.

### Admin flow
団体作成 → 団体公開情報設定・公開 → 公演作成 → 公演公開情報設定・公開 →
Join Key発行 → 所属申請確認 → 承認 → 稽古日程作成 → 出欠確認オン →
出欠状況確認 (WB-01, WB-03/04, WB-05, WB-07, WB-12, WB-13, plus org/
production creation and publish, which predate this phase - see
`docs/testing` history for those cases).

### General user flow
ログイン → Home → 団体を探す (WB-09) → 団体公開ページ (WB-16 baseline
state, then WB-17) → 公演を探す (WB-10) → 公演公開ページ (WB-18) →
所属申請 (WB-02 or WB-06) → 管理者承認 (WB-03/WB-07) → Home所属表示
(WB-15) → 稽古予定確認 → 出欠回答 (WB-13/WB-14).
