# StageArt Web First Development Strategy

## Status

Confirmed development policy.

## Core policy

StageArt will adopt a **Web First** development strategy.

Future feature development will follow this order:

1. Update the Blueprint / specification.
2. Implement the feature in the Web version first.
3. Verify the complete user flow in an actual browser.
4. Adjust UX, screen structure, and functional requirements based on that verification.
5. Treat the verified Web behavior as the baseline specification.
6. Implement the corresponding Mobile experience after the Web flow is confirmed.

The purpose is to establish StageArt as a usable service on the Web as early as possible, rather than requiring iOS/Android native builds and device installation before core functionality can be verified.

## Why Web First

StageArt's primary value is centered on application and information management:

- User authentication and account management
- Organization creation and management
- Production / activity creation and management
- Public organization pages
- Public production / activity pages
- Organization and production discovery
- Membership requests and approvals
- Invitation Key / QR based membership flows
- Rehearsal scheduling and attendance
- Accounting and reimbursement workflows
- Ticket management
- Follow / Favorite
- Notifications

Most of these functions can be implemented and verified more quickly on the Web. In particular, organization administration, production administration, schedules, accounting, ticket configuration, and bulk member operations are often better suited to larger desktop screens.

## Product delivery principle

The Web version is not a temporary prototype.

It is the first production surface of the StageArt service and must be capable of providing the core StageArt experience to real users.

The initial objective is to make the following flow operational on the Web:

User registration / login
→ Home
→ Find organizations and productions / activities
→ Create an organization
→ Create a production / activity
→ Configure members, venue, schedule, ticket information, and publication timing
→ Publish public organization and production pages
→ Invite and approve members
→ Operate rehearsals and related management functions

Public URLs remain based on the confirmed structure:

- Organization: `https://stageart.top/{organization-slug}`
- Production / activity: `https://stageart.top/{organization-slug}/{production-slug}`

## Functional verification rule

A feature should not be considered functionally complete merely because its Mobile screen has been implemented or its code compiles.

The primary verification sequence is:

Blueprint
→ Web implementation
→ Browser-based end-to-end operation
→ UX / requirement adjustment
→ Specification confirmation
→ Mobile implementation

The Mobile version should reuse the confirmed domain model, API behavior, and functional requirements from the Web version wherever possible.

## Role of the Mobile version

The Mobile application is positioned as a **field-optimized StageArt client**, not simply an independent duplicate implementation.

Mobile prioritizes functions that benefit from frequent or on-site use, including:

- Today's rehearsal and upcoming rehearsal information
- Attendance responses
- Notifications
- Production information confirmation
- QR scanning for invitations / membership
- Organization and production membership flows
- Reimbursement submission
- Photo or file upload where applicable
- Ticket or event information confirmation

Functions requiring larger-scale editing or administration may remain Web-first even after Mobile support exists.

Examples include:

- Organization settings
- Production / activity creation
- Accounting input and management
- Bulk member management
- Ticket price configuration
- Complex schedule editing
- Public page information editing

## Implementation priority from now on

New core development work should prioritize the Web version before adding further Mobile-only feature screens.

The immediate direction is:

1. Establish Web authentication and the basic application shell.
2. Implement the general-user Home and confirmed common navigation.
3. Implement organization search and production / activity search.
4. Implement the confirmed organization creation onboarding flow.
5. Implement production / activity creation and public information pages.
6. Expand into membership, invitations, approvals, rehearsal management, accounting, tickets, Follow / Favorite, and notifications.
7. Verify complete end-to-end flows in the Web version.
8. Bring the confirmed flows to Mobile with mobile-specific UX optimization.

## Native-specific exception

Mobile-only or native-specific functions, such as push notification integration, camera / QR behavior, and native authentication configuration, may still require dedicated Mobile implementation and device verification.

However, these native concerns must not block the delivery and verification of StageArt's general service functionality on the Web.

## Definition of success

The near-term development goal is not merely to increase the number of implemented Mobile screens.

The goal is to make it possible for a real user to access StageArt in a browser and complete meaningful workflows from start to finish.

Once the service flow is operational and verified on the Web, the Mobile version will be expanded as the optimized companion for theatre and event operations in the field.
