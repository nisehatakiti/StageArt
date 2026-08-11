# StageArt Blueprint

# Domain Model : Project

Version : 2.0

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

Rehearsal Candidateを経由して作成する場合と、
直接作成する場合の両方に対応する。

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
- ReservationはPerformance単位で管理する。
- Organization AccountingはOrganization単位で管理する。
- Project単位でAccountingを管理しない。
- Projectに関連するProductionやFactからHistoryを生成・参照できる。
- Projectは原則として物理削除しない。
- 過去のProjectは履歴として保持する。
- ProjectはOrganization Scopeに属する。

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

などはProductionを中心に管理する。

Organization AccountingはProjectから分離する。

Projectは履歴を保持するために永続化し、
過去の制作情報を失わない。

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
- ReservationはPerformance単位で管理する。
- Organization AccountingはOrganization単位で管理する。
- ProjectにAccountingを直接持たせない。
- Projectに関連するFactからHistoryを生成・参照する。
- Projectは過去の制作履歴として保持する。
- ProjectはOrganization Scopeに属する。
- Blueprintを唯一の設計基準とする。
