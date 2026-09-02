# StageArt Blueprint
# Chapter 12 : Functional Structure and User Flows

Version : 1.2
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

The five major functional areas are:

1. Organization → Production → Performance
2. Production → Member Management
3. Production → Rehearsal Management
4. Performance → Ticket Management
5. Organization → Accounting Management

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

### 3.4 Performance → Ticket Management

Tickets are managed in the context of individual Performances.

Production
↓
Performance
↓
Ticket Management

Ticket configuration and sales/entry operations must be associated with the relevant Performance.

A Production is the overall work; a Performance is the individual occurrence for which a ticket/seat/entry operation can take place.

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

Public publication is controlled by the publication settings and lifecycle defined elsewhere in the Blueprint. Creating the underlying Organization or Production does not by itself mean that the public page is immediately published.

The important business concept is that the public page is derived from the Organization/Production information rather than being an independent content-management product.

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
Configure / manage tickets for each Performance
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
├── Production Public Page
└── Production-specific management

Performance
├── Overview
├── Tickets
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

```text
Production start date/time ≤ selected date ≤ Production end date/time
```

For example, if a Production runs from September 10 through September 15, searching for September 12 includes that Production, while searching for September 16 does not.

The date search is therefore a range-containment search, not a search that requires the selected date to equal the Production start date.

Selecting a Production opens its public Production page.

The discovery screen is for finding public information and does not itself grant Production participation or management permission.

---

## 16. Implementation Rule

When an implementation screen or API appears to contradict this functional structure, do not resolve the discrepancy by inventing another business concept.

First determine whether:

1. the implementation is incomplete,
2. the UI is exposing an internal Domain concept that should be hidden,
3. the feature belongs under a different business context, or
4. the Blueprint itself needs an explicit business-rule change.

The functional structure in this document is the baseline for future screen-flow and UX specification work.

Detailed Domain Model documents remain authoritative for their respective Domain-level rules.
