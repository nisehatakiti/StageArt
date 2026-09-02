# StageArt Blueprint
# Chapter 12 : Functional Structure and User Flows

Version : 1.0
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
└── Performance-specific operations

The exact Web/Mobile navigation layout may differ, but the underlying business hierarchy should remain consistent.

---

## 15. Implementation Rule

When an implementation screen or API appears to contradict this functional structure, do not resolve the discrepancy by inventing another business concept.

First determine whether:

1. the implementation is incomplete,
2. the UI is exposing an internal Domain concept that should be hidden,
3. the feature belongs under a different business context, or
4. the Blueprint itself needs an explicit business-rule change.

The functional structure in this document is the baseline for future screen-flow and UX specification work.

Detailed Domain Model documents remain authoritative for their respective Domain-level rules.
