# StageArt Blueprint
# Chapter 12 : Functional Structure and User Flows

Version : 1.6
Status : Confirmed business specification

---

## 1. Purpose

This document defines the top-level functional structure of StageArt and the primary user flows.

The purpose is to prevent screen-by-screen implementation from drifting away from the actual StageArt business model.

StageArt should be understood from the user's work flow first, and from internal Domain Models second.

The user should not be required to understand internal Domain Models such as Project, ProductionDelegate, Reservation, or other implementation concepts.

---

## 2. StageArt Core Business Flow

The core business structure is:

Organization
↓
Production
↓
Performance

A user creates and manages a theatre/event organization, creates productions under that organization, and creates individual performances for each production.

The major functional areas are:

1. Organization → Production → Performance
2. Production → Member Management
3. Production → Rehearsal Management
4. Production → Ticket Management
5. Performance → Ticket sales/reservation and Reception / Check-in
6. Organization → Accounting Management

Public organization and production websites are produced as a consequence of the organization/production information being managed; they are not the primary business hierarchy.

---

## 3. Major Functional Areas

### 3.1 Organization → Production → Performance

This is the main creation flow.

Organization administrator:

Create Organization
↓
Create Production
↓
Create Performance(s)

The user does not need to understand the internal Project/Production structure. The UI should present the operation as "団体を作る", "公演を作る", and "公演回を作る".

A Production can have one or more Performances.

A Performance represents an individual public performance occurrence, such as a specific date/time and venue slot.

---

### 3.2 Production → Member Management

Members are managed in the context of a Production.

Organization membership and Production participation are separate concepts.

Organization membership means:

Person
↓
Organization Membership
↓
Organization

Production participation means:

Person / Organization
↓
Production Participant
↓
Production

A person may belong to an Organization without participating in a particular Production.

A person may also participate in a Production without being a permanent member of the Production's Organization, such as a guest performer.

Production member management therefore must not be implemented as a simple copy of the Organization member list.

---

### 3.3 Production → Rehearsal Management

Rehearsals belong to a Production.

Production
↓
Rehearsal Schedule
↓
Attendance / related rehearsal management

The user experience should be expressed as "この公演の稽古を管理する" rather than as an unrelated global schedule feature.

---

### 3.4 Production → Ticket Management

Ticket types and prices are managed at the Production level and are common to the Production's Performances.

Production
↓
Ticket Management
↓
Performance-specific sales / reservation operations
↓
Reception / Check-in

Ticket Management defines the ticket types and prices for the Production. Ticket sales or reservation acceptance is operated for an individual Performance.

The Production-level Ticket Management specification is defined separately in Chapter 19. Performance-specific reception/check-in is operated against the selected Performance.

#### 3.4.1 Ticket Reception / Check-in UX

Reception is an operation performed against the ticket/reservation list of an individual Performance.

The default reception view displays the **unreceived / unchecked-in reservations or tickets** for the selected Performance as a list.

The interaction model is intentionally the same between Web and Mobile:

- The user selects a reservation/ticket by selecting its row.
- A confirmation popup displays the reservation name.
- Pressing **OK** confirms the reception and performs check-in.
- After successful check-in, the item is no longer treated as an unreceived item in the active reception list.

##### Web

1. Display the unreceived reservation/ticket list.
2. When the mouse cursor hovers over a row, visually change the row color to indicate that it is selectable.
3. Clicking the row selects that reservation/ticket.
4. Display a confirmation popup showing the reservation name.
5. Press **OK** to execute check-in.
6. After successful check-in, update/remove the item from the unreceived list.

##### Mobile

1. Display the unreceived reservation/ticket list.
2. Tapping a row selects that reservation/ticket.
3. Display a confirmation popup showing the reservation name.
4. Press **OK** to execute check-in.
5. After successful check-in, update/remove the item from the unreceived list.

The Web hover behavior is a desktop-specific visual affordance; it does not change the underlying business operation. Mobile does not require hover and uses the same row-selection operation through tapping.

The confirmation step is intentional: reception must not be completed merely by accidentally clicking/tapping a row. The reservation name is shown before the check-in is committed.

The exact popup wording, additional displayed ticket information, success/error messaging, and API persistence details are implementation/UX details to be specified separately unless explicitly confirmed.

---

### 3.5 Organization → Accounting Management

Accounting is primarily an Organization-level management function.

Organization
↓
Accounting

Production-related budgets and actuals may be associated with a Production, but the accounting management function itself belongs to the Organization scope.

Detailed accounting rules are defined by the Accounting Domain specification.

---

## 4. Public Website as a Consequence of Core Data

Public websites are not a separate top-level business branch.

### Organization

Organization information can produce the Organization public website/page.

Organization
↓
Organization Public Page
↓
団体HP

### Production

Production information can produce the Production public website/page.

Production
↓
Production Public Page
↓
公演HP

The public Production page is prepared with the relevant information/display areas in advance. Publication is controlled by the publication date assigned to each individual information item.

The public page does not require all Production information to become visible at the same time. If different information items have different publication dates, each item is displayed when its own publication date/time is reached.

Conceptually:

Production public page
↓
Pre-configured information/display areas
↓
Current date/time is evaluated by the public-page implementation
↓
Display each information item only when its configured publication date/time has been reached

Therefore, information can be released in stages without requiring separate public-page publication operations.

The important business rule is that **the publication date/time configured for each information item is the publication condition for that item itself**.

---

## 5. Initial Onboarding

Initial onboarding is a distinct phase before normal Dashboard use.

The primary flow is:

Account Registration
↓
Email verification / authentication completion
↓
Person basic information
↓
Initial onboarding
↓
Dashboard / Home

The initial onboarding determines the user's starting context.

For a user who manages a theatre/event organization, the onboarding includes organization creation.

Organization administrator onboarding:

Register account
↓
Set Person name
↓
Select that the user manages an Organization
↓
Create Organization
↓
The user becomes the initial Organization administrator/owner according to the Membership/Role rules
↓
Complete initial onboarding
↓
Dashboard / Home

The onboarding should not turn every downstream management task into one giant registration form.

After reaching Dashboard/Home, the organization administrator can continue with the normal operational flow:

Dashboard
↓
Organization
↓
Create Production
↓
Create Performance(s)
↓
Manage Production members / rehearsals / performances / tickets

The exact number of onboarding screens and which optional fields can be skipped are UX details and should be specified separately from this top-level business flow.

---

## 6. Organization Invitation and Production Invitation Are Different

Organization invitation and Production invitation are separate business operations and must not be treated as the same invitation with different labels.

### 6.1 Organization Invitation

Purpose:

Invite a Person to become a member of the Organization.

Flow:

Organization administrator
↓
Invite Person to Organization
↓
Invitation created
↓
Invitation email / invitation URL
↓
Recipient logs in or registers
↓
Recipient reviews Organization invitation
↓
Accept
↓
Organization Membership becomes ACTIVE

The result is an Organization Membership.

Organization invitation therefore changes the person's relationship with the Organization.

---

### 6.2 Production Invitation

Purpose:

Invite a Person to participate in a specific Production.

Flow:

Production manager / authorized user
↓
Invite Person to Production
↓
Production invitation created
↓
Invitation email / invitation URL
↓
Recipient logs in or registers
↓
Recipient reviews Production invitation
↓
Accept
↓
Production participation is established

The result is a Production Participant relationship.

Production invitation does not mean that the Person becomes a permanent member of the Organization.

This distinction is intentional and must be preserved in the UI, API, domain model, authorization, and lifecycle design.

---

## 7. Invitation Delivery UX

The current design must not require an administrator to display an invitation key on screen and manually communicate that key to the recipient as the normal flow.

The preferred user experience is:

1. Administrator enters the recipient's email address.
2. StageArt creates an invitation with a secure invitation token/key.
3. StageArt sends an invitation email containing an invitation URL.
4. The recipient opens the URL.
5. StageArt identifies the pending invitation.
6. The recipient logs in or creates an account if necessary.
7. StageArt displays the invitation confirmation screen.
8. The recipient accepts or rejects the invitation.
9. StageArt updates the appropriate Membership or Participant lifecycle.

The invitation key/token is an implementation/security mechanism, not the primary human-facing workflow.

---

## 8. Invitation URL

The normal invitation should be represented to the recipient as a URL rather than as a raw invitation key.

Conceptually:

Invitation
↓
secure token/key
↓
Invitation URL
↓
Recipient

The exact URL format is an implementation detail and must not be exposed as a fixed specification unless separately confirmed.

The invitation URL must be treated as a secret capability. It must not expose unnecessary personal information or internal identifiers.

---

## 9. Invitation by Email and Manual URL Sharing

Email is the primary invitation delivery method because the administrator normally knows the intended recipient's email address.

A secondary "copy invitation URL" operation may be provided for situations where the administrator wants to send the invitation through another communication channel such as LINE or another messaging service.

In either case, the recipient should use the invitation URL rather than manually entering or copying a raw invitation key.

The UI should therefore prefer:

[招待メールを送信]

and, where appropriate:

[招待URLをコピー]

rather than presenting:

[招待キー: XXXXX]

as the normal workflow.

---

## 10. Invitation Lifecycle

An invitation should have an explicit lifecycle separate from the resulting Membership or Participant.

At minimum, the specification must support the concepts of:

- Pending
- Accepted
- Rejected
- Revoked / cancelled
- Expired

The exact status names and persistence model should be defined in the corresponding Domain specification.

Invitation acceptance must create or activate the appropriate relationship without changing the identity of the underlying Person.

---

## 11. Existing User / New User Flow

The invitation flow must support both cases.

### Existing StageArt user

Invitation URL
↓
Login / existing session
↓
Invitation confirmation
↓
Accept

### New StageArt user

Invitation URL
↓
Create StageArt account
↓
Complete required identity setup / verification
↓
Invitation confirmation
↓
Accept

The invitation must survive the authentication/registration transition so that the recipient does not lose the invitation context.

---

## 12. Organization Membership vs Production Participation

The following distinction is a core rule.

Organization Membership:

"この人はこの団体のメンバーである。"

Production Participant:

"この人はこの公演に参加する。"

These are independent facts.

Example:

Person A
↓
Organization A Membership = ACTIVE

Person A
↓
Production B Participant = ACTIVE

is a normal state.

Likewise:

Person B
↓
Organization C Membership = ACTIVE

Person B
↓
Production A Participant = ACTIVE

is also possible when the Production permits external/guest participation.

The system must not silently convert a Production invitation into an Organization invitation.

---

## 13. Administrator-oriented Main Flow

For an Organization administrator, the main StageArt experience should feel like one continuous business operation:

Create Organization
↓
Dashboard
↓
Create Production
↓
Create Performance(s)
↓
Invite / manage Production members
↓
Create / manage rehearsals
↓
Configure / manage Production tickets
↓
Open ticket sales / reservation acceptance for each Performance
↓
Operate ticket reception / check-in for each Performance
↓
Manage Organization accounting
↓
Publish / operate Organization and Production public pages

The user should be able to enter the next appropriate operation from the current Organization / Production / Performance context without having to understand internal Domain boundaries.

---

## 14. Navigation Principle

Navigation should follow business ownership/context.

Preferred conceptual navigation:

Organization
├── Overview
├── Members
├── Productions
├── Accounting
└── Organization Public Page

Production
├── Overview
├── Members
├── Rehearsals
├── Performances
├── Tickets
├── Production Public Page
└── Production-specific management

Performance
├── Overview
├── Ticket sales / reservation operations
├── Reception / Check-in
└── Performance-specific operations

The exact Web/Mobile navigation layout may differ, but the underlying business hierarchy should remain consistent.

---

## 15. Screen Specifications

### 15.1 Initial Screen / Home

The Initial Screen is the first screen shown after login and is the common entry point to StageArt.

The screen is not separated into an administrator Home and a general-user Home. The same Home is used for all users, while displayed information and menu items change according to the user's relationships and management permissions.

#### Information Areas

##### Overall Notifications

Display notifications intended for the entire StageArt user base.

- Display only when a notification has been issued by a StageArt administrator.
- These notifications do not depend on Organization or Production membership.

##### Notifications

Display notifications associated with Organizations or Productions to which the user is attached.

- Display only for a user who is attached to an Organization and/or Production.
- Examples include messages from an Organization or a Production to which the user is related.
- Users with no relevant Organization/Production relationship do not see this notification area.

##### Rehearsal Schedule

Display upcoming rehearsal schedules for Productions to which the user is attached.

- Display only for a user who is attached to a Production.
- The displayed rehearsal information is based on the user's Production participation.
- Users who are not attached to a Production do not see this rehearsal area.

#### Menu

The basic menu is:

- 団体を探す
- 公演を探す
- プロフィール
- 経費精算
- アカウント管理
- ログアウト

Menu visibility is determined by the user's relationships and permissions.

##### 団体を探す

Available to all users. Opens the Organization discovery/search screen.

##### 公演を探す

Available to all users. Opens the Production discovery/search screen.

##### プロフィール

Available to all users. Opens the user's StageArt Person/profile information.

##### 経費精算

Display only when the user is attached to an Organization or Production and the relevant Organization or Production performs accounting management.

The mere fact that the user belongs to an Organization or Production is not sufficient; the accounting-management condition must also be satisfied.

##### アカウント管理

Available to all users. Manages the StageArt account itself and is separate from Person/profile information.

##### ログアウト

Available to all users. Logs the user out of the current StageArt session.

#### Management Menus

Management menus are added to the same Home menu when the user has the corresponding management authority.

##### 団体情報

Display for a user who has Organization management authority, including an Organization administrator or an authorized Organization management delegate.

##### 公演情報

Display for a user who has Production management authority, including:

- Organization administrator with authority over the Production
- Production administrator/manager
- Authorized Production management delegate

The UI should determine visibility by management authority rather than by the person's title alone. A delegate with the appropriate scope is therefore treated as having access to the corresponding management menu.

A user does not receive management access merely by being an Organization member or Production participant.

---

### 15.2 団体を探す

Purpose: discover and view publicly available Organizations.

The screen provides two discovery methods.

#### 団体名で探す

- Provide a search box for Organization name.
- Search by **partial match / fuzzy-style name matching** rather than requiring exact equality.
- Display Organizations whose names contain the entered search text.
- Exact matching is not required.

The exact search algorithm for normalization, spelling variations, kana/kanji equivalence, etc. is not fixed by this specification. Partial name matching is the confirmed business requirement.

#### 一覧から探す

- Display a list of publicly available Organizations.
- The user can select an Organization from the list.
- Selecting an Organization opens its public Organization page.

The discovery screen is for finding public information and does not itself grant Organization membership or management permission.

---

### 15.3 公演を探す

Purpose: discover and view publicly available Productions.

The screen provides three discovery methods.

#### 団体から探す

- Provide a search box for Organization name.
- Search by partial match / fuzzy-style name matching rather than requiring exact equality.
- Display Productions associated with the matching Organization(s).

#### 公演名から探す

- Provide a search box for Production name.
- Search by partial match / fuzzy-style name matching rather than requiring exact equality.
- Display Productions whose names contain the entered search text.

#### 公演日時から探す

- Provide a date-selection search field.
- The user selects a date.
- Display Productions whose **Production schedule range contains the selected date**.

Conceptually:

Production start date/time ≤ selected date ≤ Production end date/time

For example, if a Production runs from September 10 through September 15, searching for September 12 includes that Production, while searching for September 16 does not.

The date search is therefore a range-containment search, not a search that requires the selected date to equal the Production start date.

Selecting a Production opens its public Production page.

The discovery screen is for finding public information and does not itself grant Production participation or management permission.

---

### 15.4 団体情報

Purpose: manage the information and operations of an Organization for which the user has Organization management authority.

The same screen is used by Organization administrators and authorized Organization management delegates, subject to their granted scope.

#### Approval Notifications

Display approval-related notifications only when there is an approval requiring action.

The notification area covers at least:

- **It’s ME approval**
- **Expense reimbursement approval**

If there are no applicable approval items, this notification area is not displayed.

#### Organization Information

The following information is displayed:

- **団体名** — display only; not editable on this screen.
- **Slug** — display only; not editable on this screen.
- **団体ロゴ** — one image can be uploaded and updated.
- **団体説明** — editable.
- **所在地** — editable.
- **連絡先** — editable.
- **代表者** — editable.

#### 更新

Provide an **更新** button.

The button saves the editable Organization information and updates the Organization public page with the saved information.

This includes the uploaded Organization logo.

Conceptually:

Edit Organization information / upload logo
↓
更新
↓
Save Organization information
↓
Reflect the updated information on the public Organization page

The exact publication infrastructure and persistence/API details are implementation details and are not fixed here.

#### 公演情報

Display a list of currently active Productions belonging to the Organization.

- Display current/active Productions as a list.
- Each Production is selectable as a link.
- Selecting a Production opens the corresponding Production information/management context.

#### 過去公演情報

Display a list of past Productions belonging to the Organization.

- Display past Productions as a list.
- Each Production is selectable as a link.
- Selecting a Production opens the corresponding Production information/public context as appropriate.

The distinction between current/active and past Productions is part of the confirmed screen structure.

#### ホームに戻る

Provide a link to return to the Home screen.

#### Organization Management Menu

The Organization information screen provides the following management operations:

- **公演を作る** — create a Production under the Organization.
- **メンバー追加** — add/invite a member to the Organization.
- **メンバーへの通知** — send notifications to Organization members.
- **団体規約** — manage/view the Organization's rules/regulations.
- **会計管理** — available only when Organization accounting management is enabled.

The visibility of these operations remains subject to the user's actual management authority and scope.

---

### 15.5 公演情報

Purpose: manage the information and operations of a Production for which the user has Production management authority.

The same screen is used by Organization administrators with the relevant authority, Production administrators/managers, and authorized Production management delegates, subject to their granted scope.

#### Approval Notifications

Display approval-related notifications only when there is an approval requiring action.

The notification area covers at least:

- **It’s ME approval**
- **Expense reimbursement approval**

If there are no applicable approval items, this notification area is not displayed.

#### Production Information

The following information is displayed and managed:

- **公演名** — display only; not editable on this screen.
- **Slug** — display only; not editable on this screen.
- **公演ビジュアル（フライヤー）** — image upload is supported; up to 2 images.
- **公演説明** — editable only while the Production is active.
- **公演日程** — editable only while the Production is active; includes **情報公開日時**.
- **会場** — editable only while the Production is active; includes **情報公開日時**.
- **脚本** — editable only while the Production is active; includes **情報公開日時**.
- **演出** — editable only while the Production is active; includes **情報公開日時**.

The information-publication datetime configured for each item is used directly as that item's public display condition. There is no requirement for all Production information to be released at the same time. Different information can therefore be released incrementally according to its own configured publication datetime.

#### メンバー情報

Display the Production's member/participant information on the Production information screen.

The displayed member information is the Production participant information managed by Production Member Management. The detailed member-management operations remain in **メンバー管理**.

The public display timing of member information is controlled by the publication setting applicable to that information. Member information may therefore be withheld until its configured publication datetime and then displayed on the public Production page.

#### チケット情報

Display the Production's configured ticket information on the Production information screen.

Ticket information consists of the ticket types and prices configured for the Production. The detailed ticket-management operations remain in **チケット管理**.

The Production-level ticket configuration is common to the Production's Performances. Performance-specific ticket sales/reservation operations are handled separately for each Performance.

#### チケット販売開始日時

Provide **チケット販売開始日時** as the datetime at which ticket reservation/sales acceptance for the Production's Performances can begin.

This datetime is distinct from the publication datetime of ticket information.

The distinction is:

- **情報公開日時** — controls when the relevant information is displayed publicly.
- **チケット販売開始日時** — controls when ticket reservation/sales acceptance can begin.

Therefore, ticket information may be publicly visible before reservations/sales are accepted.

The detailed ticket reservation/sales flow, including the exact operations available before and after this datetime, is specified separately as part of the Ticket Flow specification.

#### 更新

Provide an **更新** button.

The button saves the editable Production information and reflects the updated information on the public Production page.

The publication-date fields above are part of the Production public-information settings. The exact publication infrastructure and persistence/API details are implementation details and are not fixed here.

#### 公開ページを見る

Provide a **公開ページを見る** link/button.

This opens the public Production page corresponding to the Production being managed, allowing the manager to verify the currently published/public-facing information.

This is a navigation/view operation and does not itself change publication settings.

#### Production Management Menu

The Production information screen provides the following management operations while the Production is active:

- **メンバー管理** — manage Production participants/members.
- **メンバーへの通知** — send notifications to Production participants/members.
- **公演回管理** — create/manage individual Performances.
- **チケット管理** — manage Production-level ticket types and prices.
- **稽古管理** — manage rehearsals for the Production.

#### 会計管理

- **会計管理** is displayed only when accounting management is being used for the relevant context.
- Its visibility remains subject to the user's actual management authority and scope.

#### 公演活動情報

Provide **公演活動情報** as the menu/operation used to end the Production activity.

Ending the Production activity moves the Production out of the active-production context and into the past-production context used by the Organization information screen.

Once the Production activity has ended, active-only editing and management operations above are no longer treated as active Production operations. The exact lifecycle/status enum and persistence rules are implementation/domain details to be specified separately.

---

### 15.6 公演を作る

Purpose: create the Production record itself before entering the detailed Production information/management screen.

The creation screen requires only:

- 公演名
- Slug

After confirmation, StageArt creates the Production and navigates the user to the Production information screen.

---

## 16. Confirmation Rule

The following are confirmed business rules and must not be changed by implementation convenience:

1. Organization membership and Production participation are separate relationships.
2. Production member management belongs to the Production context.
3. Rehearsal management belongs to the Production context.
4. Ticket types and prices are configured at the Production level and are common to its Performances.
5. Ticket sales/reservation acceptance is operated for individual Performances.
6. Ticket reception/check-in is operated for individual Performances.
7. Production public information is prepared in advance and displayed according to the publication datetime configured for each information item.
8. Different Production information items may therefore be released incrementally at different publication datetimes.
9. Ticket sales/reservation acceptance has a separate **チケット販売開始日時** and must not be treated as the same concept as information publication.
10. The Production information screen displays Production member information and ticket information as part of the Production overview, while detailed management remains in the corresponding management screens.
11. Public-page implementation details are not to be used to change these business rules.

---

## 17. Change History

### Version 1.6

- Clarified that Production-level Ticket Management defines ticket types/prices common to the Production's Performances.
- Clarified that ticket sales/reservation acceptance and reception/check-in are Performance-level operations.
- Added Production information screen display of **メンバー情報**.
- Added Production information screen display of **チケット情報**.
- Added **チケット販売開始日時** as a separate business setting from information publication.
- Clarified that each Production information item's configured **情報公開日時** directly controls its public display timing.
- Clarified that Production public information may be released incrementally at different times.
- Clarified the public-page concept as pre-configured information/display areas whose visibility is controlled by the configured publication datetime.
- Corrected terminology from 台本 to **脚本**.

---
