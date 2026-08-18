# StageArt Blueprint

# Domain Model : Project

Version : 2.2

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

StageArtでは、Production作成時に既存Projectへ所属させるか、
新しいProjectを作成して所属させることができる。

---

# Concept

Projectは、
Organizationが行う一つの活動・制作をまとめる内部単位である。

Project自体は一般観客へ公開する情報ではない。

具体的な公演・活動はProductionによって表現する。

ProjectはProductionの上位に位置し、
制作全体を識別・管理するためのInternal Domainとして機能する。

一つのProjectに複数のProductionを関連付けることができる。

例えば、

Project「河童ホームラン2027」
  ├── Production「東京公演」
  ├── Production「大阪公演」
  └── Production「配信公演」

のように、同一企画に属する複数の実施単位をまとめて管理できる。

通常の小劇場公演のようにProductionが一つだけの場合も、
Projectを一つ設定して同じ構造で管理する。

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

一つのProjectに複数のProductionを関連付けられる。

Productionは、
Projectにおける具体的な公演・活動の実施単位を表す。

Productionは原則として一つのVenueを持つ。

東京公演・大阪公演のように会場が異なる場合は、
同一Projectに複数Productionを作成する。

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

Projectはこれら個々のProduction固有Domainを重複管理するのではなく、
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

利用者は通常のProduction管理においてProjectを過度に意識する必要はない。

Productionを作成するときは、
所属Projectを指定する。

既存Projectがある場合はそのProjectを選択できる。

新しい企画の場合は、新規Projectを作成してProductionを所属させることができる。

基本的なFlow：

公演を作る
  ↓
所属Projectを選択
  │
  ├── 既存Projectを選択
  │
  └── 新規Projectを作成
  ↓
Production生成
  ↓
Production関連Domain生成

Projectの内部構造を意識させないUIを維持しつつ、
複数Productionを同一Projectへ所属させられるようにする。

---

# Project Creation

Projectは以下のいずれかのタイミングで作成できる。

- Production作成時に新規Projectを作成する
- Project管理から事前に作成する

Production作成時に新規Projectを作成する場合、
最低限必要なProject情報を入力してProduction作成へ進める。

Projectを作成しただけでProductionを必ず作成しなければならないとはしない。

---

# Project Selection on Production Creation

Production作成時には、
所属Projectを選択する項目を設ける。

基本的な選択肢は、

- 既存Projectから選択
- 新しいProjectを作成

とする。

既存Projectを選択した場合、
新しいProductionは選択したProjectに所属する。

新しいProjectを作成した場合、
作成されたProjectへ新しいProductionを所属させる。

Production作成後にProjectを変更する機能を提供する場合は、
既存のProject Budget、Document、権限等への影響を確認した上で明示的な管理操作として扱う。

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

これらのProduction固有Domainを、
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

Budgetは、Production単位のBudgetとProject単位のBudgetを区別して扱う。

Production Budgetは個別Productionの計画を表す。

Project Budgetは、Project全体の企画・活動計画を表し、
複数Productionを含むProject全体の予実管理に利用する。

基本構造：

Organization
  ↓
Project
  ├── Project Budget
  │
  └── Production
       └── Production Budget

一つのProductionに複数のBudgetを持つことができる。

Projectにも複数のBudgetを持つことができる。

BudgetにはBudget Nameを設定できる。

例：

- 河童ホームラン2026予算 Version1
- 河童ホームラン2026予算 Version2
- 東京・大阪統合予算

Project BudgetはProject全体の予実管理を目的とし、
Production Budgetは個別公演の計画を目的とする。

ActualはJournal Entryを正本としてScopeごとに集計する。

Project Budgetの詳細はBudget Management Policyで定義する。

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

Organization Accountingは、
Organization単位の会計帳簿を正本とする。

ProjectおよびProductionは、
同じJournal Entryを異なるScopeから集計して参照する。

基本構造：

Organization
  ↓
Accounting Period
  ↓
Journal Entry
  ↓
Journal Entry Line

Journal EntryがProductionに関連する場合：

Organization
  ↓
Project
  ↓
Production
  ↓
Journal Entry

とScopeを判定できる構造とする。

ProjectがJournal EntryやAccountを直接所有することはしない。

AccountはAccounting Domainにおける会計科目として管理する。

---

# Project Accounting Scope

Project Accountingは、
Project全体の予実管理を行うための集計Scopeである。

Project AccountingはProject固有の別Journal帳簿を意味しない。

Projectに所属するProduction等に関連するJournal Entryを、
Project Scopeで集計してActualを算出する。

Project BudgetとProject Actualを比較することで、
Project全体の予実を確認できる。

基本構造：

Project Budget
      ↓
Project Planned Amount

Journal Entry
      ↓
Project Actual Amount

Project Variance
  = Project Actual - Project Planned

Project Actualを別の正本データとして二重保存しない。

---

# Production Accounting Scope

Production Accountingは、
個別Productionの収支・決算を確認するための集計Scopeである。

Production Budgetと、
Productionに関連するJournal Entryから集計したProduction Actualを比較できる。

Production Settlementは、
Productionに関連する未収金・未払金等の精算を含む個別公演の決算確認を表す。

Production Accountingも別Journal帳簿ではない。

---

# Organization / Project / Production Accounting

会計情報の見せ方は、以下の役割分担を基本とする。

Organization
  = 団体全体の財務状況

Project
  = 企画全体の予実管理

Production
  = 個別公演の決算・収支確認

同一のJournal Entryを各Scopeで集計するため、
Organization / Project / ProductionのActualは会計上整合する必要がある。

同一の会計FactをScopeごとに二重入力してはならない。

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

利用者は通常の単独Production管理でProject名を強く意識する必要はない。

Projectを新規作成する場合には、
管理上分かりやすい名称を設定できる。

Projectの表示名称については、
利用者に内部構造を過度に意識させないUIを優先する。

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
- Production作成時は既存Projectを選択するか、新規Projectを作成して所属させる。
- Projectは複数のProductionを関連付けられる。
- Production関連DomainはProductionを正本となる関連先として管理する。
- Production固有のDomainをProjectとProductionの両方で重複管理しない。
- Project BudgetとProduction Budgetを区別して管理する。
- Projectは複数のBudgetを保持できる。
- Productionは複数のBudgetを保持できる。
- Project BudgetはProject全体の予実管理に利用する。
- Production Budgetは個別Productionの計画に利用する。
- Project ActualはJournal EntryからProject Scopeで集計する。
- Production ActualはJournal EntryからProduction Scopeで集計する。
- Actualを別の正本データとして二重管理しない。
- RehearsalはProduction単位で管理する。
- ParticipantはProduction単位で管理する。
- PerformanceはProduction単位で管理する。
- TicketはProductionまたはPerformanceに関連して管理する。
- ReservationはPerformance単位で管理する。
- TimetableはProduction単位で管理する。
- Organization AccountingはOrganization単位で管理する。
- ProjectはJournal EntryやAccountを直接所有しない。
- Organization / Project / ProductionのAccounting Scopeは同一Journal Entryを基礎として整合させる。
- Projectに関連するProductionやFactからHistoryを生成・参照できる。
- Projectは原則として物理削除しない。
- 過去のProjectは履歴として保持する。
- ProjectはOrganization Scopeに属する。
- ProjectとProductionは独立したLifecycleを持つ。
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
Productionは具体的な公演・活動を表す実施単位である。

Projectは複数Productionを束ねることができる。

例えば東京公演・大阪公演など、
同一企画に属する複数のProductionを一つのProjectから管理できる。

Projectは利用者に内部構造を過度に意識させない。

Production作成時には、
既存Projectを選択するか新規Projectを作成する。

Projectに関連する個別のProduction固有Business Domainを
ProjectとProductionの両方で重複管理しない。

特に、

- Rehearsal
- Participant
- Performance
- Ticket
- Reservation
- Production Budget
- Production Actual
- Timetable

などはProductionを中心に管理する。

一方、Project Budgetは複数Productionを含む企画全体の計画を管理する。

Organization AccountingはProjectから分離した単一の会計正本とし、
ProjectおよびProductionはJournal EntryをScope別に集計して予実・決算を表示する。

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
- Production作成時は既存Projectを選択するか、新規Projectを作成する。
- Projectは複数Productionを関連付けられる構造とする。
- Production固有のDomainはProductionを中心に管理する。
- Project BudgetはProject全体の予実管理に利用する。
- Production Budgetは個別Productionの計画に利用する。
- RehearsalはProduction単位で管理する。
- ParticipantはProduction単位で管理する。
- PerformanceはProduction単位で管理する。
- TicketはProductionまたはPerformanceに関連して管理する。
- ReservationはPerformance単位で管理する。
- TimetableはProduction単位で管理する。
- Organization AccountingはOrganization単位で管理する。
- Project Accountingは別帳簿ではなくProject Scopeの集計である。
- Production Accountingは別帳簿ではなくProduction Scopeの集計である。
- ProjectにJournal EntryやAccountを直接持たせない。
- Organization / Project / Productionで同一Journal Entryを基礎として会計整合性を保つ。
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
