# StageArt Blueprint

# Domain Consistency Policy : Project

Version : 1.0

---

# Purpose

本書はProject Domainについて、現在のStageArt Canonical Domain ModelおよびDomainMapとの整合性を定義する。

既存のProject.mdに記載された基本設計を維持しつつ、後から確定したProject Budget、複数Production、Production作成時のProject選択、会計Scope等の決定事項を優先仕様として整理する。

---

# Canonical Position

ProjectはOrganizationとProductionの間に位置する企画・活動単位である。

基本構造：

Organization
    ↓
Project
    ↓
Production

ProjectはOrganizationに所属し、Productionは必ず一つのProjectに所属する。

Projectは一つ以上のProductionを持つことができる。

---

# Project Concept

Projectは、単一の公演だけでなく、複数のProductionを含む一つの企画・活動全体を表現できる。

例えば、

Project「河童ホームラン2027」
    ├─ Production「東京公演」
    ├─ Production「大阪公演」
    └─ Production「配信公演」

のように、異なる実施単位を一つのProjectで管理できる。

通常の小劇場公演では、ProjectとProductionが実質的に一対一になることも許容する。

---

# Project Visibility

Projectは原則としてInternal Domainであり、一般観客向けの公開ページを直接持たない。

一般公開される公演情報はProductionを単位として扱う。

Project名やProject内部情報を、Production Public Pageへ自動公開してはならない。

---

# Production Creation

Production作成時には、所属Projectを指定する。

選択肢：

- 既存Projectを選択
- 新規Projectを作成

新規Projectを作成した場合、そのProjectへ作成中のProductionを所属させる。

Production作成時にProjectを未指定のままProductionだけを作成する構造は基本仕様としない。

---

# Project Creation

Projectは以下の方法で作成できる。

- Production作成Wizardから新規作成
- Project管理から事前作成

Projectのみを先に作成し、Productionを後から追加することを許可する。

したがって、Project作成とProduction作成は同一操作である必要はない。

---

# Multiple Productions

一つのProjectに複数Productionを所属させることを正式に許可する。

複数Productionは、同一企画における異なる実施単位として扱う。

例：

- 東京公演
- 大阪公演
- 配信公演

それぞれを別Productionとして管理する。

各Productionは独立したVenue、Performance、Ticket、Reservation等を持つことができる。

---

# Venue Relationship

VenueはProductionに直接紐づく。

ProjectはVenueを直接所有しない。

一つのProductionは初期仕様では一つのVenueを持つことを基本とする。

複数会場を含む企画は、Project配下に複数Productionを作成して表現する。

例：

Project
    ├─ Production「東京公演」
    │    └─ Venue A
    └─ Production「大阪公演」
         └─ Venue B

PerformanceにはVenueを直接持たせない。

---

# Production Related Domains

以下のProduction固有Domainは、ProjectではなくProductionを正本となる関連先とする。

- Participant
- Performance
- Ticket
- Reservation
- CheckIn
- Rehearsal
- Timetable
- Production Budget
- Production Actual
- Production Document
- Announcement
- Survey
- ProductionDelegate

Projectはこれらを直接二重管理しない。

---

# Project Budget

ProjectはProject Budgetを持つことができる。

Project Budgetは、Project全体の企画・活動計画を表す。

一つのProjectに複数のBudgetを保持できる。

Budgetには利用者が設定するBudget Nameを持たせる。

Project Budgetの用途は、複数Productionを含むProject全体の予算および予実管理である。

Production Budgetは個別Productionの計画を表し、Project Budgetとは異なるScopeとして扱う。

---

# Budget Reuse

Project BudgetおよびProduction Budgetは、既存Budgetをコピーして新しいBudgetを作成できる。

過去ProductionのBudgetを新しいProductionへコピーすることを許可する。

同様に、Project Budgetも既存Budgetをコピーして再利用できる。

コピー後は独立したBudgetとして扱い、コピー元の変更をコピー先へ波及させない。

---

# Project Accounting

Project Accountingは、Project専用の別会計帳簿を意味しない。

Organization AccountingにおけるJournal Entryを正本として、Project ScopeからActualを集計する。

基本構造：

Organization
    ↓
Journal Entry
    ↓
Project Scope
    ↓
Project Actual

Project ActualとProject Budgetを比較して、Project全体の予実を確認する。

Project ActualをJournal Entryとは別の正本データとして二重保存しない。

---

# Production Accounting

Production Accountingは、個別Productionの決算・収支確認を行うScopeである。

Productionに関連するJournal EntryをProduction Scopeから集計してActualを算出する。

Production BudgetとProduction Actualを比較し、個別公演の計画と実績を確認する。

Production Settlementは、Production単位の最終的な収支・決算確認を表す概念であり、独立した会計帳簿ではない。

---

# Accounting Hierarchy

会計情報の表示目的は以下の通りとする。

Organization
    = 団体全体の財務状況

Project
    = 企画全体の予実管理

Production
    = 個別公演の決算・収支確認

同一のJournal Entryを各Scopeから集計することで、各階層のActualに整合性を持たせる。

同一の会計FactをOrganization / Project / Productionそれぞれへ重複入力してはならない。

---

# Project Documents

DocumentはProjectおよびProductionの双方に関連付けることができる。

Project Documentの例：

- 制作全体資料
- プロジェクト計画
- 複数Production共通資料
- 契約資料

Production Documentの例：

- 台本
- 稽古資料
- 公演資料
- 当日資料

Documentの実ファイル管理についてはDocument DomainおよびExternal Storageの仕様に従う。

---

# Project Lifecycle

ProjectのLifecycleは既存Project Domainの以下の状態を基本とする。

- DRAFT
- ACTIVE
- CLOSED
- ARCHIVED

ProductionのLifecycleとは別に管理する。

ProjectがCLOSEDまたはARCHIVEDになった場合でも、過去Productionや会計情報等の参照可能性を失わせない。

具体的な編集制限はAuthorizationおよびLifecycle Ruleに従う。

---

# Project Change

Productionが所属するProjectを変更する場合、Project Scopeに属するBudget、Document、権限等への影響を考慮する。

ProductionのProject変更は通常の編集操作と同一視せず、明示的な管理操作として扱う。

変更時に既存の会計Factを別Projectへ不正に移動させることがないよう、Accounting Scopeの整合性を維持する。

---

# Canonical Relationship Summary

```text
Organization
    │
    └── Project
          │
          ├── Project Budget
          │
          ├── Production
          │     ├── Venue
          │     ├── Performance
          │     ├── Ticket
          │     ├── Reservation
          │     ├── Participant
          │     ├── Rehearsal
          │     ├── Production Budget
          │     └── Production Document
          │
          └── Project Document
```

会計については、上記のDomain構造とは別にJournal Entryを正本とし、Organization / Project / Productionの各Scopeから集計する。

---

# Design Principle

Projectは単なる中間Entityではなく、複数のProductionを束ねる「企画・活動単位」である。

通常の単独公演ではProjectを利用者に強く意識させず、複数Productionを必要とする場合にはProjectによって企画全体を一括管理できることを基本とする。
