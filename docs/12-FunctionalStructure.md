# StageArt Blueprint
# Chapter 12 : Functional Structure and User Flows

Version : 1.7
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
4. Production → Performance Management
5. Production → Ticket Management
6. Performance → Ticket sales/reservation and Reception / Check-in
7. Organization → Accounting Management

Public organization and production websites are produced as a consequence of the organization/production information being managed; they are not the primary business hierarchy.

---

### 3.4 Production → Rehearsal Management

Rehearsals belong to a Production.

Production
↓
Rehearsal Schedule
↓
Attendance / related rehearsal management

The user experience should be expressed as "この公演の稽古を管理する" rather than as an unrelated global schedule feature.

---

### 3.5 Production → Ticket Management

Ticket types, prices, and ticket-back settings are managed at the Production level and are common across the Production's Performances.

Production
↓
Ticket Management
├─ Ticket types / prices
└─ Ticket Back Settings
     ├─ Calculation method
     │    ├─ 累進方式
     │    └─ 分離方式
     └─ Multiple conditions
          ├─ 販売枚数条件
          └─ チケットバック率
↓
Performance-specific sales / reservation operations
↓
Reception / Check-in

Ticket Management defines the ticket types and prices for the Production. It also defines the Production-wide チケットバック calculation method and multiple sales-quantity conditions/rates. Ticket sales or reservation acceptance is operated for an individual Performance.

The ticket-back settings are not configured separately for each Production member or Performance. The sales quantity used for a member's ticket-back calculation is determined from reservations whose **扱い** is that Production member, and the Production's common ticket-back settings are applied to that sales count.

The Production-level Ticket Management screen specification is defined separately in Chapter 19. Production ticket-sales and ticket-back rules are defined separately in Chapter 25. Performance-specific reception/check-in is operated against the selected Performance.

---

### 3.6 Organization → Accounting Management

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
Configure / manage Production tickets and ticket-back rules
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

### 15.2 Organization Information Screen

The Organization Information screen manages Organization-level information.

Detailed screen specification is defined separately in the corresponding chapter.

---

### 15.3 Production Information Screen

The Production Information screen is the entry point for Production-level information and management.

The screen provides access to the Production's management functions, including:

- Member Management
- Rehearsal Management
- Performance Management
- Ticket Management
- Production Public Page / information management

Detailed screen specification is defined separately in the corresponding chapters.

---

### 15.4 Performance Information Screen

The Performance Information screen manages an individual Performance occurrence.

It provides access to Performance-specific operations such as ticket sales/reservation operations and reception/check-in.

Detailed screen specification is defined separately in the corresponding chapter.

---

## 16. Web and Mobile UX Principle

Web and Mobile are separate UX implementations but share the same business structure and navigation concepts.

The Web uses a desktop-oriented navigation structure. Mobile uses the same business navigation structure through an explicit menu/hamburger interaction rather than replacing the business hierarchy with a different primary navigation model.

Device-specific operations may have dedicated UX, such as QR-based ticket reception/scanning on Mobile, without changing the underlying business hierarchy.

---

## 17. Public URL Structure

Organization public page:

```text
/{organizationSlug}
```

Production public page:

```text
/{organizationSlug}/{productionSlug}
```

Legacy `/o/...` URLs remain available as 302 compatibility redirects where applicable.

The public Production page is rendered by the Mobile/React web output consuming the StageArt REST API. WordPress provides the StageArt REST API and administration infrastructure rather than serving the public Production page HTML itself.

---

## 18. Server URL / API Configuration Rule

API paths must not hardcode environment-specific absolute server URLs in application source code.

The server base URL must be supplied through environment/configuration, and API calls must use the existing API client/configuration mechanism and relative paths.

This rule exists so that changing the StageArt API server does not require rewriting application source code.

New code must follow the existing `getApiBaseUrl()` / API client mechanism rather than introducing hardcoded absolute API URLs.

---

## 19. Confirmed Business Hierarchy Summary

The confirmed user-facing hierarchy is:

```text
Organization
  ↓
Production
  ├─ Member Management
  ├─ Rehearsal Management
  ├─ Performance Management
  └─ Ticket Management
       ├─ Ticket Types / Prices
       └─ Ticket Back Settings
            ├─ Calculation Method
            │    ├─ 累進方式
            │    └─ 分離方式
            └─ Multiple Conditions
                 ├─ 販売枚数条件
                 └─ チケットバック率
       ↓
     Performance
       ├─ Ticket sales / reservation
       └─ Reception / Check-in
```

This hierarchy is the business structure to be reflected consistently across Web, Mobile, API, Domain Models, and screen specifications.
