# StageArt Modular Architecture

## 1. Purpose

StageArtは、舞台活動全体を支援する統合プラットフォームとして開発する。
一方で、将来的には一部の業務機能をStageArt本体から独立して提供し、既存の劇団WebサイトやWordPressサイトでも利用できる製品として販売できるようにする。

このため、StageArtは「すべての機能をCoreへ直接実装する」構造を採用しない。

今後の設計では、StageArt固有の基盤機能を **StageArt Core** とし、業務機能は可能な限り独立した **Domain Module** として境界を明確にする。

本方針は今後の実装における正式なBluePrintである。

---

## 2. Architecture Overview

```text
StageArt Platform
│
├── StageArt Core
│   ├── Identity / User
│   ├── Public Profile
│   ├── Organization
│   ├── Production / Activity
│   ├── Membership
│   ├── Role / Permission
│   ├── Notification
│   ├── Search / Public Discovery
│   └── Shared Platform Interfaces
│
└── Domain Modules
    ├── Ticket Management Module
    ├── Rehearsal Management Module
    └── Accounting Management Module
```

将来的には追加モジュールを増やすことを妨げない。

---

## 3. StageArt Core

StageArt Coreは、StageArtというプラットフォームそのものを成立させる共通基盤である。

### Coreに含めるもの

- User / Identity
- User Profile
- Organization
- Production / Activity
- Membership
- Role / Permission
- Invitation / Membership Join Flow
- Notification基盤
- Public Page / Discovery基盤
- Organization / Productionを識別するSlug等の共通識別子
- モジュールが利用する共通インターフェース

### Coreに直接密結合させないもの

以下の業務領域は、Production等のEntityへフィールドやロジックを直接追加して肥大化させない。

- Ticket Management
- Rehearsal Management
- Accounting Management

---

## 4. Domain Module Policy

各業務モジュールは、自身の業務責務を持つ。

### Ticket Management Module

例：

- Ticket Type
- Ticket Price
- Reservation
- Customer / Purchaser
- Sales
- Check-in
- Ticket Status

### Rehearsal Management Module

例：

- Rehearsal Schedule
- Rehearsal Session
- Rehearsal Location
- Attendance Requirement
- Attendance Response
- Attendance Summary
- Member Availability

### Accounting Management Module

例：

- Budget
- Expense
- Income
- Reimbursement Request
- Reimbursement Approval
- Settlement

モジュール内部の業務ルールは、可能な限りCore固有のUIや画面構造に依存しない。

---

## 5. Dependency Direction

依存方向を以下の原則とする。

```text
Application / UI
        ↓
Domain Module
        ↓
Core Interface
        ↑
StageArt Core Adapter
```

Coreが個別モジュールの内部実装を直接参照する構造は避ける。

悪い例：

```text
Production
├── rehearsalDate
├── rehearsalAttendance
├── ticketPrice
├── reservation
└── reimbursement
```

推奨例：

```text
Production
└── Production ID / Shared Context

Rehearsal Module
├── Rehearsal
├── Attendance
└── productionId / Context Provider

Ticket Module
├── Ticket
├── Reservation
└── productionId / Context Provider

Accounting Module
├── Expense
├── Reimbursement
└── productionId / Context Provider
```

Productionとの関連は必要だが、Production Entity自体を各モジュールの業務項目で肥大化させない。

---

## 6. Shared Interfaces / Ports

各モジュールは、StageArt固有Entityの内部実装へ直接依存するのではなく、必要な能力をInterface / Portとして定義することを原則とする。

概念例：

```text
ProductionContextProvider
- getProduction(id)
- getOrganization(id)
- getParticipants(productionId)
- canManageProduction(userId, productionId)
```

Rehearsal Moduleは、必要なProduction情報や参加者情報を、このような契約を通じて取得する。

StageArt本体ではStageArt Core Adapterがこれを実装する。

将来、別のホストシステムでは別Adapterを実装できる。

```text
                 Rehearsal Module
                         │
              Production Context Interface
                    ┌────┴────┐
                    │         │
          StageArt Core     WordPress Adapter
```

Ticket / Accountingについても同様の考え方を採用する。

---

## 7. WordPress Plugin Portability

将来的な販売構想として、以下の個別製品化を想定する。

- StageArt Ticket Management for WordPress
- StageArt Rehearsal Management for WordPress
- StageArt Accounting Management for WordPress

ただし、現在のStageArtアプリケーションコードを将来そのままPHPのWordPressプラグインへ変換できることを保証するものではない。

WordPress Plugin化では、WordPress固有の以下の実装が必要になる可能性がある。

- PHP
- WordPress REST API
- WordPress User / Role連携
- WordPress Post / Custom Post Type連携
- WordPress Database / Option / Meta構造
- WordPress Admin UI

一方、現在から以下を独立させておくことで、将来の再実装・製品化コストを下げる。

- Domain Model
- Business Rule
- API Contract
- Validation Rule
- Test Case
- UX Specification
- Data Ownership

したがって「将来WordPressへコードをコピーする」ことではなく、「現在から業務モジュールの境界を独立させる」ことを目的とする。

---

## 8. Current Implementation Priority

現時点で最も重要なのは、これから実装する **Rehearsal Management** をStageArt Coreへ直接埋め込まないことである。

今後のRehearsal実装では、最低限以下を明確にする。

1. Rehearsal Moduleが所有するEntity
2. Rehearsal Moduleが所有するBusiness Rule
3. StageArt Coreから取得する情報
4. Coreとの接続Interface
5. API境界
6. Database上のデータ所有責任

Web β版では実装を過度に抽象化して開発速度を落とす必要はない。

ただし、後から切り出せない密結合を新たに作らないことを優先する。

---

## 9. Data Ownership Principle

データは可能な限り「どのモジュールが責任を持つか」を明確にする。

例：

| Data | Owner |
|---|---|
| User | StageArt Core |
| Organization | StageArt Core |
| Production / Activity | StageArt Core |
| Membership | StageArt Core |
| Rehearsal | Rehearsal Module |
| Attendance | Rehearsal Module |
| Ticket | Ticket Module |
| Reservation | Ticket Module |
| Expense | Accounting Module |
| Reimbursement | Accounting Module |

他モジュールのデータを直接更新することは避け、必要に応じてAPI / Interface / Event等の明確な境界を使用する。

---

## 10. API Policy

今後のBackend APIでは、Moduleの責務が判別できる構造を維持する。

概念例：

```text
/core/organizations
/core/productions
/core/memberships

/rehearsal/rehearsals
/rehearsal/attendance

/tickets/ticket-types
/tickets/reservations

/accounting/expenses
/accounting/reimbursements
```

実際の既存API構造を不用意に全面変更する必要はない。

ただし、新規機能ではDomain Moduleの境界を意識し、既存構造との整合性を確認する。

---

## 11. Application Structure

将来的には以下の概念的構造を目指す。

```text
/apps
├── stageart-web
└── stageart-mobile

/core
├── identity
├── organization
├── production
└── membership

/modules
├── ticket
├── rehearsal
└── accounting

/docs
├── architecture
├── modules
├── testing
└── wordpress-portability
```

ただし、現時点のGitHub構造をこの形へ即時全面リファクタリングすることは必須ではない。

既存構造を尊重しながら、今後の変更で論理的なModule Boundaryを維持する。

---

## 12. Implementation Rule for Claude / Future Developers

新機能を実装する前に、以下を確認する。

1. この機能はStageArt Coreか、Domain Moduleか。
2. 他の製品でも独立して利用される可能性があるか。
3. Core固有Entityの内部構造に直接依存していないか。
4. 必要な依存関係をInterface / API境界として表現できるか。
5. データのOwnerが明確か。
6. 将来WordPress等の別ホストへAdapterを作れるか。

特にTicket / Rehearsal / Accountingを実装する際は、このBluePrintを確認すること。

---

## 13. Final Policy

StageArtは統合プラットフォームとして機能を提供する。

同時に、Ticket / Rehearsal / Accountingは将来的な独立製品化を見据えたDomain Moduleとして設計する。

そのため、今後の正式方針を以下とする。

> **StageArt Coreは舞台活動プラットフォームの共通基盤を担い、Ticket・Rehearsal・Accountingは明確なDomain Boundaryを持つ独立可能なモジュールとして設計する。各モジュールはCoreとの契約を通じて連携し、将来的にはWordPress等の別ホスト向けAdapter / Pluginとして再実装・製品化できる構造を維持する。**
