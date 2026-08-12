# StageArt Blueprint

# Domain Model : Production

Version : 4.2

---

# Purpose

Productionは、
Projectに属する具体的な公演・活動を表すDomainである。

Productionは、
StageArtにおける実際の制作・公演活動の中心となる。

Productionには、

- 公演情報
- 日程
- 出演者
- スタッフ
- Performance
- Ticket
- Reservation
- Rehearsal
- Timetable
- Budget
- Actual
- Document
- Announcement
- Survey

などのProduction関連Domainが関連する。

---

# Project Relationship

ProductionはProjectに所属する。

基本構造：

Organization
    ↓
Project
    ↓
Production

ProductionはOrganizationに直接所属しない。

Productionに関連するBusiness Dataは、
Productionを通じてOrganization Scopeに属する。

---

# Identity

ProductionはProductionIdによって一意に識別される。

ProductionIdは変更できない。

Production Nameは識別子ではない。

Production Nameは変更できる。

同一Project内で同名Productionが存在することを
許可するかどうかはBusiness Ruleで定義する。

---

# Production Information

Productionは、
具体的な公演・活動に関する基本情報を管理する。

例：

- Production Name
- Description
- Venue
- Start Date
- End Date
- Status
- Public Settings

Production固有の情報は、
Production Domainで管理する。

---

# Lifecycle

Productionは、
制作・公演活動の状態を管理する。

基本的な状態：

- DRAFT
- PLANNING
- ACTIVE
- COMPLETED
- ARCHIVED
- CANCELLED

具体的な状態遷移は、
Production Lifecycle Ruleで定義する。

ProductionがARCHIVEDまたはCANCELLEDになった場合、
新規Business Activityの作成を制限する。

過去データの参照可否は、
LifecycleおよびAuthorizationのルールに従う。

---

# Primary Manager

Productionには、
Productionを管理するPrimaryManagerが存在する。

PrimaryManagerは、
Productionに関する全管理権限を持つ。

一つのProductionには、
一人のPrimaryManagerが存在する。

PrimaryManagerはPersonによって表現する。

基本構造：

Production
    ↓
PrimaryManager
    ↓
Person

PrimaryManagerは、
OrganizationのOwnerとは異なる概念である。

Organization OwnerはOrganization Scopeの管理者であり、
PrimaryManagerはProduction Scopeの管理者である。

一人のPersonは、
複数のProductionのPrimaryManagerになることができる。

PrimaryManagerであることによって、
そのPersonが他のProductionのPrimaryManagerになることを妨げない。

---

# Production Delegate

Productionの管理業務の一部を、
他のPersonへ委任できる。

その委任をProductionDelegateによって管理する。

ProductionDelegateは、
Production ScopeにおいてPersonへRoleを適用する関係を表す。

基本構造：

Production
    ↓
ProductionDelegate
    ├── Person
    └── Role
            ↓
        Permission

ProductionDelegate自身は、
Permissionを定義しない。

適用されるPermissionは、
Roleによって定義される。

RoleはRole Domainで一元管理する。

---

# Production Delegate Scope

ProductionDelegateによって適用されたRoleは、
対象Productionに対してのみ有効である。

例えば、

Production A
    ↓
ProductionDelegate
    ├── Person A
    └── Role = Rehearsal Manager

の場合、

Person AはProduction Aについて
Rehearsal ManagerのPermissionを持つ。

Person AがProduction Bについて
同じPermissionを持つとは限らない。

ProductionDelegateによるRoleの適用は、
Organization全体の権限を変更しない。

---

# Production Delegate and Membership

ProductionDelegateとMembershipは、
異なる概念である。

Membershipは、
PersonがOrganizationに所属していることを表す。

ProductionDelegateは、
Personが特定Productionについて
Roleを適用されていることを表す。

基本構造：

Organization
    ↓
Membership
    ↓
Role

Production
    ↓
ProductionDelegate
    ↓
Role

ProductionDelegateによるRoleの適用は、
PersonのOrganization Membershipを変更しない。

ProductionDelegateによって、
Organization ScopeのRoleが自動的に変更されることもない。

---

# Production Delegate and Role

ProductionDelegateは、
Production専用のRoleを定義するものではない。

Organization ScopeとProduction Scopeでは、
同じRole Definitionを利用する。

Organization Scope：

Person
    ↓
Membership
    ↓
Role
    ↓
Permission

Production Scope：

Person
    ↓
ProductionDelegate
    ↓
Role
    ↓
Permission

DelegateRoleという別のRole体系は使用しない。

---

# Participant

Productionには、
複数のPersonまたはOrganizationが参加できる。

Productionへの参加は、
Participantによって管理する。

基本構造：

Production
    ↓
Participant
    ↓
Person / Organization

Participantは、
Productionへの参加関係を表す。

Participantは、
Production Scopeの管理権限を意味しない。

---

# Participant Type

Participantには、
Productionにおける参加区分を設定できる。

例：

- CAST
- STAFF

Participant TypeはRoleではない。

CASTであることによって、
Production管理権限を自動的に付与してはならない。

STAFFであることによって、
Production管理権限を自動的に付与してはならない。

Production管理権限が必要な場合は、
ProductionDelegateによってRoleを適用する。

---

# Participant and ProductionDelegate

ParticipantとProductionDelegateは、
異なる概念として管理する。

例えば、

Person A
    ↓
Participant
    ↓
Production A
    ↓
Participant Type = CAST

でありながら、

Person A
    ↓
ProductionDelegate
    ↓
Production A
    ↓
Role = Rehearsal Manager

という状態を許可する。

この場合、

Participantは、
Person AがProduction Aに出演者として参加していることを表す。

ProductionDelegateは、
Person AがProduction Aの稽古管理権限を持っていることを表す。

Participantであることによって、
自動的にProductionDelegateになることはない。

ProductionDelegateであることによって、
自動的にParticipantになることもない。

---

# Performance

Productionには、
複数のPerformanceを設定できる。

Performanceは、
具体的な公演回を表す。

基本構造：

Production
    ↓
Performance

Performanceには、

- Performance Date
- Start Time
- End Time
- Venue
- Capacity
- Status

などを設定できる。

TicketやReservationは、
必要に応じてPerformanceと関連付ける。

---

# Ticket

Productionでは、
Ticketを管理する。

Ticketは、
Productionにおける販売可能な券種を表す。

例：

- 一般
- 学生
- 前売
- 当日

Ticketの販売・購入・利用状況は、
Ticket Domainで管理する。

Ticket Revenueについては、
TicketおよびAccounting Domainのルールに従う。

---

# Reservation

Productionでは、
Reservationを管理する。

Reservationは、
観客によるチケット予約を表す。

ReservationはProductionに関連し、
必要に応じてPerformanceおよびTicketと関連付ける。

Reservationの詳細な状態管理は、
Reservation Domainで定義する。

---

# Check In

Performance当日の入場処理は、
CheckInによって管理する。

CheckInは、
ReservationまたはTicketに関連する入場実績を表す。

CheckInが完了した場合、
Ticket Revenueに関するBusiness Eventを発生させる。

基本的な流れ：

Reservation / Ticket
    ↓
CheckIn
    ↓
CheckInCompleted
    ↓
Ticket Revenue
    ↓
Accounting Journal Entry

会計仕訳の具体的な生成ルールは、
Accounting Domainで定義する。

---

# Ticket Revenue

Ticket Revenueは、
Ticketの販売・利用に伴う収益を表す。

CheckInCompletedを契機として、
会計側へ収益情報を連携できる。

Production Domain自身が
Journal Entryを直接生成することはしない。

Production Domainでは、
必要なBusiness Eventを発生させる。

Accounting Domainが、
そのEventを受けてJournal Entryを生成する。

---

# Rehearsal

Productionでは、
Rehearsalを管理する。

Rehearsalは、
Productionにおける稽古を表す。

基本構造：

Production
    ↓
Rehearsal

Rehearsalには、

- 日付
- 開始時刻
- 終了時刻
- 場所
- 内容
- 備考
- Status

などを設定できる。

Rehearsalの参加予定者・参加実績は、
RehearsalAttendanceによって管理する。

Rehearsalの詳細なDomain Ruleは、
Rehearsal Domainで定義する。

---

# Rehearsal Lifecycle

Rehearsalは、
稽古予定から実施完了までを
一つのEntityとして管理する。

稽古予定と確定稽古を、
別Entityとして管理しない。

基本的なStatus：

- DRAFT
- SCHEDULED
- CONFIRMED
- ACTIVE
- COMPLETED
- CANCELLED

基本Lifecycle：

DRAFT
    ↓
SCHEDULED
    ↓
CONFIRMED
    ↓
ACTIVE
    ↓
COMPLETED

中止：

DRAFT
    ↓
CANCELLED

SCHEDULED
    ↓
CANCELLED

CONFIRMED
    ↓
CANCELLED

ACTIVE
    ↓
CANCELLED

Statusの変更によって、
別のRehearsal Entityを生成しない。

---

# Rehearsal Attendance

Rehearsalへの参加予定および
実際の出欠は、
RehearsalAttendanceによって管理する。

基本構造：

Production
    ↓
Rehearsal
    ↓
RehearsalAttendance
    ↓
Person

RehearsalAttendanceは、
RehearsalのLifecycle全体を通じて保持する。

RehearsalがCONFIRMEDになっても、
RehearsalAttendanceを削除しない。

RehearsalがACTIVEになっても、
RehearsalAttendanceを削除しない。

参加予定から実際の出欠への変化は、
同じRehearsalAttendanceのStatus変更として扱う。

---

# Rehearsal Attendance Status

予定段階では、

- UNANSWERED
- ATTENDING
- NOT_ATTENDING

などのStatusを使用する。

実施段階では、

- ATTENDED
- LATE
- ABSENT

などのStatusを使用する。

基本的な流れ：

UNANSWERED
    ↓
ATTENDING
    ↓
ATTENDED

または、

UNANSWERED
    ↓
NOT_ATTENDING

実施時：

ATTENDING
    ↓
ATTENDED

ATTENDING
    ↓
LATE

ATTENDING
    ↓
ABSENT

予定段階の参加者情報を削除して、
別の出欠Entityを作成することはしない。

---

# Timetable

Productionでは、
Timetableを管理できる。

Timetableは、
Productionにおける活動予定を表す。

Rehearsal、
Performance、
その他Production Activityを
時系列で管理できる。

Timetableの詳細な構造は、
Timetable Domainで定義する。

---

# Budget

Productionでは、
Production単位のBudgetを管理する。

Budgetは、
Productionの予算を表す。

Organization全体のAccountingとは、
異なる目的を持つ。

基本構造：

Production
    ↓
Budget

Budgetの詳細は、
Budget Domainで定義する。

---

# Actual

Productionでは、
Production単位のActualを管理できる。

Actualは、
Productionにおける実績金額を表す。

BudgetとActualを比較することで、
Production単位の予実管理を行う。

Accountingにおける
Organization全体のJournal Entryとは、
異なる目的を持つ。

---

# Budget vs Actual

Productionでは、
BudgetとActualを比較できる。

基本構造：

Production
    ↓
Budget
    ↓
Actual

Budgetは計画値。

Actualは実績値。

差異を確認することで、
Productionの収支状況を把握できる。

---

# Document

Productionに関連するDocumentを管理できる。

Documentの実ファイルは、
Google Driveなどの外部ストレージと連携できる。

StageArtでは、

- File Identifier
- File Name
- File Type
- External Reference
- Productionとの関連
- 共有対象

などを管理する。

実ファイルそのものは、
StageArtの正本として保持しない。

---

# Announcement

Production関係者へ、
内部Announcementを送信できる。

Announcementの対象者には、

- CAST
- STAFF
- ProductionDelegate
- その他関係者

などを指定できる。

Announcementの作成には、
適切なProduction Scopeの権限が必要である。

---

# Survey

Productionでは、
関係者向けのSurveyを作成できる。

Surveyは、

- CAST
- STAFF
- その他Production関係者

などを対象とすることができる。

Surveyの詳細な構造は、
Survey Domainで定義する。

---

# External Calendar

Productionに関連するRehearsalなどの予定を、
Google Calendarなどの外部Calendarへ連携できる。

External CalendarへのAPI操作は、
Infrastructure Layerが担当する。

Production Domainは、
特定CalendarサービスのAPIへ直接依存しない。

RehearsalをStageArt側の正本とする。

External Calendar Eventは、
外部Artifactとして扱う。

---

# Organization Scope

ProductionはProjectを介してOrganizationに属する。

基本構造：

Organization
    ↓
Project
    ↓
Production

Production関連Domainは、
Productionを通じてOrganization Scopeに属する。

対象には、

- Participant
- Performance
- Ticket
- Reservation
- Rehearsal
- Timetable
- Budget
- Document
- Announcement
- Survey
- ProductionDelegate

などが含まれる。

---

# Authorization

Productionに対する操作は、
Production ScopeのAuthorizationによって制御する。

PrimaryManagerは、
Productionに関する全権限を持つ。

ProductionDelegateは、
適用されたRoleに含まれるPermissionのみ持つ。

ProductionDelegateのPermissionは、
対象Productionに限定される。

Organization Roleによる権限と、
Production ScopeのRoleによる権限は、
それぞれのScopeで評価する。

Authorizationの具体的なルールは、
Authorization Domainで定義する。

---

# Production Delegate

Production ScopeでRoleを適用する場合は、
ProductionDelegateを使用する。

ProductionDelegateは、

- Person
- Production
- Role
- Status

を関連付ける。

ProductionDelegateは、
Role Definitionを保持しない。

Role Definitionは、
Role Domainで管理する。

ProductionDelegateは、
Production Scopeにおいて
PersonへRoleを適用する関係を表す。

---

# Lifecycle and Delegate

Productionが、

- ACTIVE
- COMPLETED
- ARCHIVED
- CANCELLED

などへ遷移した場合、
ProductionDelegateの有効性も
Production Lifecycle Ruleに従う。

Productionが終了した場合、
ProductionDelegateを新規権限として
利用し続けない。

既存のProductionDelegate情報は、
監査および履歴のため保持できる。

---

# Automatically Generated

Production作成時、
StageArtは必要な基本情報を生成する。

例：

- ProductionId
- Default Settings
- PrimaryManager relationship

ProjectやProductionの作成に伴う
関連Domainの生成は、
各DomainのBusiness Ruleに従う。

ProductionDelegateは、
Production作成時には自動生成しない。

必要なPersonに対して、
PrimaryManagerなどの適切な権限者が
ProductionDelegateを設定する。

---

# Audit Information

Productionの重要な管理操作について、
監査情報を記録できるようにする。

基本的な監査情報：

- CreatedBy
- CreatedAt
- UpdatedBy
- UpdatedAt

ProductionDelegateのRole適用についても、
適切な監査情報を保持する。

---

# Domain Events

Productionに関する主なDomain Event：

- ProductionCreated
- ProductionUpdated
- ProductionArchived
- ProductionCancelled
- ProductionCompleted

Production関連Domainについては、
各DomainでEventを定義する。

例：

- CheckInCompleted
- ProductionDelegateAdded
- ProductionDelegateUpdated
- ProductionDelegateRemoved

Production Domainは、
これらの関連Eventを直接実装するのではなく、
必要に応じてDomain間の契約を定義する。

---

# Design Decisions

ProductionはProjectに所属する。

基本構造は、

Organization
    ↓
Project
    ↓
Production

である。

Production関連Domainは、
Productionを通じてOrganization Scopeに属する。

Productionには、
一人のPrimaryManagerが存在する。

PrimaryManagerは、
Productionに関する全管理権限を持つ。

一人のPersonは、
複数のProductionのPrimaryManagerになることができる。

Production単位で管理権限を委任する場合は、
ProductionDelegateを利用する。

ProductionDelegateは、
Production ScopeにおいてPersonへRoleを適用する関係である。

ProductionDelegateは、
Permissionを直接定義しない。

RoleがPermission Setを定義する。

Role Definitionは、
Role Domainで一元管理する。

DelegateRoleというProduction専用Role体系は使用しない。

Organization ScopeとProduction Scopeでは、
同じRole Definitionを利用する。

ParticipantはProductionへの参加関係を表す。

Participant TypeはProductionにおける参加区分を表す。

CAST / STAFFはParticipant Typeであり、
Roleではない。

Participant Typeによって、
管理権限を自動的に付与しない。

ProductionDelegateによって、
Participant Typeを自動的に変更しない。

ProductionのTicketは、
Ticket Domainで管理する。

CheckInCompletedを契機として、
Ticket RevenueをAccountingへ連携できる。

Production Domain自身は、
Journal Entryを直接生成しない。

Accounting Domainが、
必要なJournal Entryを生成する。

Production BudgetとProduction Actualは、
Production単位の予実管理を目的とする。

Organization Accountingとは、
異なる目的を持つ。

Rehearsalは、
稽古予定から実施完了までを
一つのEntityとして管理する。

稽古予定と確定稽古を、
別Entityとして管理しない。

RehearsalのLifecycleはStatusで管理する。

RehearsalAttendanceは、
RehearsalのLifecycle全体を通じて保持する。

RehearsalがCONFIRMEDまたはACTIVEになっても、
RehearsalAttendanceを削除しない。

参加予定と実際の出欠は、
同じRehearsalAttendanceのStatus変更で管理する。

---

# Design Principles

- ProductionはProjectに所属する。
- ProductionはOrganizationにProjectを介して属する。
- Production関連DomainはProductionを通じてOrganization Scopeに属する。
- ProductionにはPrimaryManagerが一人存在する。
- PrimaryManagerはProductionに関する全管理権限を持つ。
- 一人のPersonは複数ProductionのPrimaryManagerになれる。
- ProductionDelegateはProduction ScopeでPersonへRoleを適用する関係である。
- ProductionDelegateはPermissionを直接定義しない。
- RoleがPermission Setを定義する。
- Role DefinitionはRole Domainで一元管理する。
- DelegateRoleという別Role体系を使用しない。
- Organization ScopeとProduction Scopeで同じRole Definitionを利用する。
- ProductionDelegateによるRoleのPermissionは対象Productionに限定される。
- ProductionDelegateはOrganization Membershipを変更しない。
- ProductionDelegateはOrganization ScopeのRoleを変更しない。
- ParticipantはProductionへの参加関係を表す。
- Participant TypeはProductionへの参加区分を表す。
- CAST / STAFFはParticipant TypeでありRoleではない。
- Participant Typeによって管理権限を自動付与しない。
- ParticipantとProductionDelegateを分離する。
- ProductionDelegateとMembershipを分離する。
- Production単位の予算はBudgetで管理する。
- Production単位の実績はActualで管理する。
- Organization全体の会計はAccountingで管理する。
- CheckInCompletedをTicket Revenue連携のBusiness Eventとして扱う。
- Production DomainはJournal Entryを直接生成しない。
- Accounting Domainが会計仕訳を生成する。
- Rehearsalは稽古予定から実施完了までを一つのEntityとして管理する。
- RehearsalのLifecycleはStatusで管理する。
- 稽古予定と確定稽古を別Entityとして管理しない。
- RehearsalAttendanceはRehearsalのLifecycle全体を通じて保持する。
- CONFIRMEDまたはACTIVEになってもRehearsalAttendanceを削除しない。
- 参加予定と実際の出欠は同じRehearsalAttendanceのStatus変更で管理する。
- Google Calendarなどの外部サービスへのAPI操作はInfrastructure Layerが担当する。
- Blueprintを唯一の設計基準とする。