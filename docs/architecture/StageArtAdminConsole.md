# StageArt Admin Console / Management Dashboard

## Status

Confirmed Blueprint.

## Purpose

StageArt has two distinct UI responsibilities.

1. **StageArt Web / Mobile**: day-to-day use by audience members, organization members, production participants, and production administrators.
2. **StageArt Admin Console**: desktop-oriented administration, cross-cutting management, configuration, monitoring, and analytical dashboards.

The Admin Console is the preferred surface for information that benefits from a wide screen, aggregation, comparison, drill-down, and tabular or chart-oriented views. Ticket sales and performance analytics are representative examples.

## Relationship with WordPress

WordPress remains the host and authentication/infrastructure platform. The StageArt Admin Console may be implemented inside the WordPress administration area as StageArt-owned admin pages rather than overloading the standard WordPress Users screen.

The conceptual distinction remains:

- WordPress User: host-level authentication account.
- StageArt Person: StageArt domain identity and membership subject.

StageArt-specific operational information should primarily be managed through StageArt-owned admin pages. The standard WordPress User UI may be extended where useful, but it is not the canonical StageArt Person management surface.

## Administrative scopes

### 1. StageArt Platform Administration

For operators of the StageArt service itself.

Suggested areas:

- Platform dashboard
- Person / account overview
- Organization overview
- Production overview
- Ticket ecosystem overview
- Advertising management
- Module management
- System settings

This scope may aggregate data across multiple organizations and productions, subject to platform-level authorization.

### 2. Organization / Production Administration

For administrators responsible for a specific organization or production.

Suggested areas:

- Organization dashboard
- Production dashboard
- Member management
- Production information
- Rehearsal management
- Accounting management
- Performance session management
- Ticket management
- Production settings

This scope is always constrained by StageArt Organization / Production authorization and must not become a shortcut around domain permissions merely because the UI is hosted inside WordPress admin.

## Ticket and Performance Dashboard

The canonical Ticket Module hierarchy is:

Organization
→ Production
→ PerformanceSession
→ Ticket

The administrative dashboard should support drill-down along the same hierarchy.

Example Production Dashboard metrics:

- Total ticket sales amount
- Tickets sold
- Remaining inventory / availability where inventory is enabled
- Sales by PerformanceSession
- Sales by Ticket type
- Sales over time
- Check-in status when CheckIn is implemented

Example navigation:

Production Dashboard
├─ Production information
├─ Performance Sessions
│  ├─ Session A
│  ├─ Session B
│  └─ Session C
└─ Ticket Dashboard
   ├─ Sales
   ├─ Quantity
   ├─ Availability
   ├─ Session analysis
   └─ Ticket-type analysis

The exact metrics must follow the eventual Ticket Domain Model. This document does not invent sales, reservation, payment, or inventory rules that have not yet been formally specified.

## UI Responsibility Rule

Use StageArt Web / Mobile primarily for operational actions that participants perform frequently or while mobile:

- Join / membership actions
- Rehearsal attendance
- Participant notifications
- Everyday production participation
- Audience discovery and viewing

Use the Admin Console primarily for management and analysis:

- Aggregated sales reporting
- Cross-session comparisons
- Tables and bulk management
- System and module settings
- Platform-wide oversight

A feature may have both surfaces when appropriate. For example, ticket information may be visible in StageArt Web while detailed sales analytics are concentrated in the Admin Console.

## WordPress Plugin Productization

The Admin Console concept is compatible with independent WordPress modules.

For example, the future Performance & Ticket Module may add its own WordPress administration menu and dashboard:

Performance & Ticket
├─ Productions / Events
├─ Performance Sessions
├─ Tickets
└─ Sales Dashboard

When the module is used together with StageArt Core, it receives Production context through Core Contracts. When physically extracted as an independent WordPress plugin in the future, a host adapter may provide equivalent event/production context.

The module boundary and business rules remain authoritative; the Admin Console is a presentation and management surface, not a replacement domain model.

## Architecture Principle

The final direction is therefore:

**Operational User UI**

- StageArt Web
- StageArt Mobile

**Management UI**

- StageArt Admin Console, initially compatible with WordPress Admin

This separation is a Blueprint decision and should guide future dashboard, ticket analytics, and plugin-product design.

---

## Confirmed Platform Administration Console

System Administrator用のPlatform Administration Consoleは、通常のOrganization / Production Administrationとは別のSystem-wide UIとして扱う。

V1の左メニューは以下を基本構成とする。

- 全体通知
- アカウント管理
- 団体管理
- 公演管理
- ログ管理

Dashboardには少なくとも以下を表示する。

- アカウント数 → アカウント管理へ
- 団体数 → 団体管理へ
- 公演数 → 公演管理へ
- 最新ログ約50件をコードブロック風ログウィンドウで表示

アカウント管理では、一覧、複数選択、一括操作を扱う。

想定操作：

- アカウント削除
- アカウントブロック
- パスワードリセット

団体管理では、団体名のあいまい検索と一覧を提供し、一覧には団体名とSlugを表示する。選択した団体はSystem Administratorとして通常の団体管理画面へ移動する。

公演管理では、公演名のあいまい検索と一覧を提供し、一覧には団体名、団体Slug、公演名、公演Slugを表示する。選択した公演はSystem Administratorとして通常の公演管理画面へ移動する。

この遷移はimpersonationではない。操作主体はSystem Administrator自身であり、既存のScope Authorization DecisionがsystemAdministratorFlagにより許可される。
