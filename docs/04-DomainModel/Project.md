# StageArt Blueprint

# Domain Model : Project

Version : 2.1

---

# Purpose

Projectは、
Organizationが行う活動・制作を管理するInternal Domainである。

ProjectはOrganizationとProductionの間に存在する。

基本構造は、

Organization
  ↓
Project
  ↓
Production

とする。

利用者はProjectという内部構造を必ずしも意識する必要はない。

StageArtは利用者の「公演を作る」などの操作を起点として、
必要なProjectを生成・管理する。

---

# Concept

Projectは、
Organizationが行う一つの活動・制作をまとめる内部単位である。

Project自体は一般観客へ公開する情報ではない。

具体的な公演・活動はProductionによって表現する。

ProjectはProductionの上位に位置し、
制作全体を識別・管理するためのInternal Domainとして機能する。

---

# Identity

ProjectはProjectIdによって一意に識別される。

ProjectIdは変更できない。

Project名は識別子ではない。

Project名は内部管理上必要な場合に設定できるが、
一般利用者へProjectそのものを公開することを前提としない。

利用者はProjectIdを意識しない。

---

# Organization Relationship

Projectは必ず一つのOrganizationに所属する。

Organization
  ↓
Project

ProjectはOrganizationをまたいで共有しない。

Organizationの変更を、
Projectそのものの属性変更として扱うことはしない。

---

# Production Relationship

ProjectはProductionを保持する。

ProjectとProductionの基本構造は、

Project
  ↓
Production

とする。

一つのProjectに複数のProductionを関連付けられる構造を維持する。

Productionは、
Projectにおける具体的な公演・活動を表す。

---

# Production

ProductionはStageArtにおける具体的な公演・活動を表す。

ProductionはProjectに所属する。

Productionには、

- Category
- Genre
- Tag
- Participant
- Performance
- Ticket
- Reservation
- Rehearsal
- Timetable
- Budget
- Production Actual
- Document
- Announcement
- Survey

などのDomainが関連する。

Projectはこれら個々のDomainを直接管理するのではなく、
Productionを通じて制作全体をまとめる。

---

# Internal Domain

ProjectはInternal Domainである。

Projectそのものを、

- 公演ページ
- チケット販売ページ
- 一般観客向け画面

などで公開しない。

一般利用者には、
Productionを中心とした適切な情報を表示する。

---

# User Interaction

利用者は原則としてProjectを直接操作しない。

例えば、

「公演を作る」

という操作を行った場合、

StageArtは必要に応じて、

- Project
- Production

を生成する。

利用者は、
内部的にProjectが生成されたことを意識する必要はない。

---

# Automatically Generated

公演作成など、
Projectを必要とするBusiness Actionが実行された場合、
StageArtはProjectを自動生成できる。

基本的なFlow：

公演を作る
  ↓
Project生成
  ↓
Production生成
  ↓
Production関連Domain生成

Production関連Domainの生成タイミングは、
各DomainのBusiness Ruleに従う。

Project自体が、
すべての関連Domainを直接生成するとは限らない。

---

# Production Related Domains

Productionに関連するDomainは、
Productionを正本となる関連先として管理する。

例：

Project
  ↓
Production
  ├── Participant
  ├── Performance
  ├── Ticket
  ├── Reservation
  ├── Rehearsal
  ├── Timetable
  ├── Budget
  ├── Production Actual
  ├── Document
  ├── Announcement
  └── Survey

これらのDomainを、
Projectの直接の子Domainとして重複管理しない。

---

# Document Relationship

DocumentはProjectおよびProductionに関連付けることができる。

例えば、

Projectに関連する資料：

- 制作方針
- プロジェクト資料
- 契約資料

Productionに関連する資料：

- 台本
- 稽古資料
- 公演資料
- 当日資料

など。

Documentの実ファイルはGoogle Driveなどの外部ストレージで管理する。

---

# Project and Budget

BudgetはProduction単位で管理する。

ProjectがBudgetを直接管理することを正本としない。

基本構造：

Project
  ↓
Production
  ↓
Budget

一つのProductionに複数のBudgetを持つことができる。

例：

- A会場案
- B会場案
- 一日2公演案

Budgetの詳細はBudget Domainで定義する。

---

# Project and Rehearsal

RehearsalはProductionに関連付ける。

基本構造：

Project
  ↓
Production
  ↓
Rehearsal

RehearsalはProjectに直接所属するものとして管理しない。

Rehearsalは、
稽古予定から実施完了までを一つのEntityとして管理する。

稽古予定と確定稽古を、
別Entityとして管理しない。

RehearsalのLifecycleはStatusによって管理する。

Rehearsalの参加予定者および実際の出欠は、
RehearsalAttendanceによって管理する。

RehearsalがCONFIRMEDやACTIVEへ変更されても、
RehearsalAttendanceを別Entityへ移行したり削除したりしない。

Rehearsalの詳細はRehearsal Domainで定義する。

---

# Project and Participant

ParticipantはProductionへの参加というFactを表す。

基本構造：

Project
  ↓
Production
  ↓
Participant

ProjectはParticipantを直接管理しない。

Participantの詳細はParticipant Domainで定義する。

---

# Project and Performance

PerformanceはProductionにおける個別の公演回を表す。

基本構造：

Project
  ↓
Production
  ↓
Performance

ProjectはPerformanceを直接管理しない。

---

# Project and Reservation

ReservationはPerformanceに対する予約というFactを表す。

基本構造：

Project
  ↓
Production
  ↓
Performance
  ↓
Reservation

ProjectはReservationを直接管理しない。

---

# Project and Ticket

TicketはProductionまたはPerformanceに関連する
チケット情報を表す。

基本構造：

Project
  ↓
Production
  ↓
Ticket

Performance単位で販売条件などを管理する場合は、
Performanceとの関連を持つ。

ProjectはTicketを直接管理しない。

---

# Project and Timetable

TimetableはProductionにおける
活動予定を表す。

基本構造：

Project
  ↓
Production
  ↓
Timetable

ProjectはTimetableを直接管理しない。

Timetableの詳細はTimetable Domainで定義する。

---

# Project and Accounting

Organizationの会計は、
ProjectではなくOrganization単位で管理する。

基本構造：

Organization
  ↓
Accounting Period
  ↓
Journal Entry
  ↓
Journal Entry Line

Productionの予算・実績は、
Production単位で管理する。

基本構造：

Organization
  ↓
Project
  ↓
Production
  ├── Budget
  └── Production Actual

ProjectがJournal EntryやAccountを直接管理することはしない。

AccountはAccounting Domainにおける会計科目として管理する。

---

# Project and History

Project自体を活動履歴として管理するのではなく、
Projectに関連するProductionやその他のFactから
Historyを生成・参照する。

例えば、

Production
  ↓
Participant
  ↓
History

のように、
Productionにおける活動からPersonの履歴を生成する。

Organizationの公演履歴などについても、
Project / Productionに関連するFactから生成する。

---

# Project Lifecycle

Projectは以下の状態を持つ。

- DRAFT
- ACTIVE
- CLOSED
- ARCHIVED

---

# Draft

Projectが作成されたが、
まだ活動が開始されていない状態。

---

# Active

Projectが進行中の状態。

Productionの制作、
稽古、
チケット販売、
公演などの活動が行われる。

---

# Closed

Projectに関連する主要な活動が終了した状態。

公演終了後の、

- 収支確認
- 予実確認
- アンケート
- 履歴更新

などの処理を行うことができる。

ProjectがClosedになっても、
Productionや関連Factを削除してはならない。

---

# Archived

Projectを通常の活動対象から外した状態。

過去の制作履歴として保持する。

Projectを物理削除することは原則として行わない。

---

# Lifecycle Rules

Projectは、
関連するProductionや活動の状態を考慮してLifecycleを管理する。

ProjectをClosedまたはArchivedにしても、
過去のProductionやHistoryなどのFactを削除してはならない。

ProjectのLifecycleとProductionのLifecycleは、
別々のDomainとして管理する。

ProductionがCompletedになったことと、
ProjectがClosedになることは同一ではない。

一つのProjectに複数Productionが存在する場合、
すべてのProductionの状態を考慮して
ProjectのLifecycleを管理する。

---

# Visibility

ProjectはInternal Domainである。

Projectそのものを一般公開しない。

一般利用者が閲覧するのは、

- Organization Public Profile
- Production Public Page
- Ticket Information
- その他公開対象Artifact

などである。

---

# Project Name

Projectには内部管理上の名称を設定できる。

ただし、
利用者が必ずProject名を入力する必要はない。

StageArtが公演作成時に、
必要に応じてProjectを生成する。

Projectの表示名称については、
利用者に内部構造を意識させないUIを優先する。

---

# Project Scope

ProjectはOrganization Scopeに属する。

Projectに関連するすべてのDomainは、
最終的にOrganization Scopeを判定できる構造とする。

Projectを通じてProductionに関連するDomainも、
Productionを通じてOrganization Scopeに属する。

異なるOrganizationのProjectへ、
権限なくアクセスしてはならない。

---

# Authorization

ProjectはOrganization Scopeに属する。

Projectへのアクセスは、
Projectが所属するOrganizationに対する
Authorizationによって制御する。

基本構造：

Person
  ↓
Membership
  ↓
Organization
  ↓
Project

Membershipに関連付けられたRoleによって、
Projectに対するPermissionを判定する。

Production Scopeの権限は、
Projectそのものの権限とは分離する。

Productionに対する権限は、
ProductionDelegateまたはPrimaryManagerによって判定する。

---

# Production Scope

Projectに所属するProductionは、
Production ScopeのResourceである。

基本構造：

Organization
  ↓
Project
  ↓
Production

Productionに対する権限は、
Production ScopeのAuthorizationによって管理する。

ProjectのOrganization Scope権限を持つことだけで、
Productionの管理権限を自動的に付与するとは限らない。

Productionに対する具体的な権限は、
Production DomainおよびAuthorization Domainで定義する。

---

# Production Delegate

ProductionDelegateは、
Production ScopeにおいてPersonへRoleを適用する関係である。

ProjectはProductionDelegateを直接管理しない。

基本構造：

Project
  ↓
Production
  ↓
ProductionDelegate
  ├── Person
  └── Role

ProductionDelegateの詳細はProduction Domainで定義する。

Projectは、
Productionの管理権限そのものを定義しない。

---

# Project and Production Lifecycle

ProjectとProductionは、
それぞれ独立したLifecycleを持つ。

Project：

DRAFT
  ↓
ACTIVE
  ↓
CLOSED
  ↓
ARCHIVED

Production：

DRAFT
  ↓
PLANNING
  ↓
ACTIVE
  ↓
COMPLETED
  ↓
ARCHIVED

ProjectのLifecycle変更によって、
ProductionのStatusを自動的に同じ値へ変更することはしない。

ProductionのLifecycle変更によって、
ProjectのStatusを自動的に同じ値へ変更することもしない。

ただし、
Project Lifecycle Ruleによって
関連Productionの状態を考慮することができる。

---

# Rehearsal Relationship

ProjectはRehearsalを直接管理しない。

基本構造：

Project
  ↓
Production
  ↓
Rehearsal
  ↓
RehearsalAttendance
  ↓
Person

RehearsalはProductionに属する。

Rehearsalの予定・確定・実施は、
Rehearsal自身のStatus変更によって管理する。

RehearsalAttendanceは、
RehearsalのLifecycleを通じて保持する。

---

# External Calendar

Projectに関連するProductionの予定を、
Google Calendarなどの外部Calendarへ連携できる。

External CalendarへのAPI操作は、
Infrastructure Layerが担当する。

Project Domainは、
特定CalendarサービスのAPIへ直接依存しない。

StageArt側のProductionおよびRehearsalを
正本として扱う。

External Calendar Eventは、
外部Artifactとして扱う。

---

# Document

Projectに関連するDocumentを管理できる。

Documentの実ファイルは、
Google Driveなどの外部ストレージで管理できる。

Projectに関連するDocumentと、
Productionに関連するDocumentを区別できる。

基本構造：

Project
  ↓
Document

Production
  ↓
Document

Document Domainの詳細は、
Document Domainで定義する。

---

# Audit Information

Projectの重要な管理操作について、
監査情報を記録できるようにする。

基本的な監査情報：

- CreatedBy
- CreatedAt
- UpdatedBy
- UpdatedAt

Lifecycle変更についても、
必要に応じて監査情報を保持する。

---

# Business Rules

- ProjectはOrganizationに所属する。
- ProjectはProductionの上位Domainである。
- 基本構造はOrganization → Project → Productionとする。
- ProjectはInternal Domainである。
- Projectを一般公開しない。
- 利用者はProjectを必ずしも意識しない。
- 公演作成などのBusiness ActionからProjectを自動生成できる。
- Projectは複数のProductionを関連付けられる構造とする。
- Production関連DomainはProductionを正本となる関連先として管理する。
- BudgetはProduction単位で管理する。
- RehearsalはProduction単位で管理する。
- ParticipantはProduction単位で管理する。
- PerformanceはProduction単位で管理する。
- TicketはProductionまたはPerformanceに関連して管理する。
- ReservationはPerformance単位で管理する。
- TimetableはProduction単位で管理する。
- Organization AccountingはOrganization単位で管理する。
- Project単位でAccountingを管理しない。
- ProjectにJournal EntryやAccountを直接持たせない。
- Projectに関連するProductionやFactからHistoryを生成・参照できる。
- Projectは原則として物理削除しない。
- 過去のProjectは履歴として保持する。
- ProjectはOrganization Scopeに属する。
- ProjectとProductionは独立したLifecycleを持つ。
- ProjectのLifecycleとProductionのLifecycleを同一視しない。
- RehearsalはProductionに所属する。
- Rehearsalは一つのEntityとしてLifecycleを管理する。
- 稽古予定と確定稽古を別Entityとして管理しない。
- RehearsalのLifecycleはStatusで管理する。
- RehearsalAttendanceはRehearsalのLifecycleを通じて保持する。
- ProjectはRehearsalAttendanceを直接管理しない。
- ProductionDelegateはProductionに所属する。
- ProjectはProductionDelegateを直接管理しない。
- Production Scopeの権限とProjectのOrganization Scope権限を分離する。

---

# Domain Events

Projectに関する主なDomain Event：

- ProjectCreated
- ProjectUpdated
- ProjectActivated
- ProjectClosed
- ProjectArchived

Project生成時にProductionを同時生成する場合は、
Production DomainのEventを別途発生させる。

ProjectのLifecycle変更によって、
関連するProductionのLifecycleを自動的に変更する場合は、
各DomainのBusiness Ruleに従ってEventを発生させる。

---

# Design Decisions

Projectは、
OrganizationとProductionの間に存在するInternal Domainとする。

基本構造は、

Organization
  ↓
Project
  ↓
Production

である。

Projectは制作活動をまとめる内部単位であり、
Productionは具体的な公演・活動を表す。

Projectは利用者に内部構造を意識させない。

Projectに関連する個別のBusiness Domainを
ProjectとProductionの両方で重複管理しない。

特に、

- Rehearsal
- Participant
- Performance
- Ticket
- Reservation
- Budget
- Actual
- Timetable

などはProductionを中心に管理する。

Organization AccountingはProjectから分離する。

Projectは履歴を保持するために永続化し、
過去の制作情報を失わない。

RehearsalはProductionに所属し、
Rehearsal自身のStatusによってLifecycleを管理する。

RehearsalAttendanceは、
RehearsalのLifecycle全体を通じて保持する。

稽古予定と確定稽古を別Domainとして扱わない。

Production ScopeのAuthorizationは、
ProjectのOrganization Scope Authorizationとは分離する。

---

# Future

将来的にProject単位で、

- 制作チェックリスト
- 制作タスク
- 制作進行
- 契約管理
- 助成金管理
- 制作資料
- プロジェクト横断スケジュール

などを追加できる構造とする。

ただし、
Production固有のDomainをProjectへ移動させることはしない。

---

# Design Principles

- ProjectはOrganizationとProductionの間に位置する。
- ProjectはInternal Domainである。
- Organization → Project → Productionの階層を維持する。
- Projectは利用者に内部構造を意識させない。
- Projectは公演作成などのBusiness Actionから自動生成できる。
- Projectは複数Productionを関連付けられる構造とする。
- Production固有のDomainはProductionを中心に管理する。
- BudgetはProduction単位で管理する。
- RehearsalはProduction単位で管理する。
- ParticipantはProduction単位で管理する。
- PerformanceはProduction単位で管理する。
- TicketはProductionまたはPerformanceに関連して管理する。
- ReservationはPerformance単位で管理する。
- TimetableはProduction単位で管理する。
- Organization AccountingはOrganization単位で管理する。
- ProjectにAccountingを直接持たせない。
- ProjectにJournal EntryやAccountを直接持たせない。
- Projectに関連するFactからHistoryを生成・参照する。
- Projectは過去の制作履歴として保持する。
- ProjectはOrganization Scopeに属する。
- ProjectとProductionのLifecycleを分離する。
- Rehearsalは一つのEntityとしてLifecycleを管理する。
- 稽古予定と確定稽古を別Entityとして管理しない。
- RehearsalAttendanceはLifecycleを通じて保持する。
- ProductionDelegateはProduction Scopeで管理する。
- ProjectはProduction Scopeの権限を直接定義しない。
- Blueprintを唯一の設計基準とする。