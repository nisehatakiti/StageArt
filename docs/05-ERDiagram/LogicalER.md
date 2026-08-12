# StageArt Blueprint

# Logical ER Diagram

Version : 4.1

---

# Purpose

Logical ER Diagramは、StageArtの各Domainおよび関連Entityの
論理構造と関係を表現する。

Conceptual ERで定義したBusiness上の関係を、
EntityおよびReferenceとして具体化する。

Database製品固有の型やインデックスなどの物理設計は、
Physical ERまたは実装設計で定義する。

Logical ERでは、
Domain間の責務とEntity間のReferenceを明確にする。

---

# Logical Model

UserAccount
    │
    └── Person
            ├── Membership
            │       └── Organization
            │
            ├── Participant
            │       └── Production
            │
            ├── ProductionDelegate
            │       ├── Production
            │       └── Role
            │
            ├── Reservation
            │
            └── RehearsalAttendance

Organization
    ├── Membership
    ├── Role
    ├── Accounting
    │       ├── AccountingPeriod
    │       ├── Account
    │       └── JournalEntry
    │               └── JournalEntryLine
    │
    ├── Equipment
    ├── Regulation
    ├── Document
    ├── Announcement
    ├── ExternalConnection
    │       ├── Service
    │       └── Credential
    │
    └── Project
            └── Production
                    ├── PrimaryManager ─── Person
                    ├── ProductionDelegate
                    │       ├── Person
                    │       └── Role
                    │
                    ├── Participant
                    │       └── Subject
                    │
                    ├── Performance
                    │       ├── Seat
                    │       └── Reservation
                    │               ├── ReservationSeat
                    │               └── Companion
                    │
                    ├── RehearsalCandidate
                    │       └── RehearsalAvailability
                    │
                    ├── Rehearsal
                    │       └── RehearsalAttendance
                    │
                    ├── Timetable
                    │       └── TimetableItem
                    │
                    ├── Budget
                    │       └── BudgetItem
                    │
                    ├── ProductionActual
                    │
                    ├── Document
                    ├── Announcement
                    └── Survey
                            └── SurveyResponse

---

# Identity Structure

## UserAccount

UserAccountはStageArtへの
Authentication Identityを表す。

主な識別子：

UserAccountId

UserAccountはPersonに関連付ける。

UserAccountはOrganizationやProductionに
直接所属しない。

基本構造：

UserAccount
    ↓
Person

---

## Person

PersonはStageArtにおける
Business Identityを表す。

主な識別子：

PersonId

PersonはUserAccountを持たなくてもよい。

Personは、

- Organizationへの所属
- Productionへの参加
- Production Scopeの管理権限
- Profile
- HistoricalActivity

などのBusiness Relationshipを持つ。

---

## Profile

ProfileはPersonに属する
プロフィール情報を表す。

Person
    ↓
Profile

---

## HistoricalActivity

HistoricalActivityはPersonに関連する
過去の活動実績を表す。

Person
    ↓
HistoricalActivity

HistoricalActivityは、
PersonのBusiness Dataの正本ではない。

---

# Organization

## Organization

OrganizationはStageArtにおけるTenantである。

主な識別子：

OrganizationId

Organizationは、

- Membership
- Role
- Project
- Accounting
- Equipment
- Regulation
- Document
- Announcement
- ExternalConnection

などを管理する。

Organizationは劇団に限定しない。

---

# Membership

MembershipはPersonとOrganizationの
所属関係を表す。

主な識別子：

MembershipId

主なReference：

PersonId
OrganizationId

基本構造：

Person
    ↓
Membership
    ↓
Organization

MembershipはOrganization Scopeにおける
Personの所属関係を表す。

Membershipに関連するRoleは、
Organization ScopeでPersonに適用される。

Role Assignmentという独立Entityは作成しない。

---

# Role

RoleはPermission Setを定義する。

主な識別子：

RoleId

RoleはOrganization ScopeとProduction Scopeの
両方で利用できる。

Role自身はScopeを保持しない。

基本構造：

Role
    ↓
Permission

Organization Scope：

Person
    ↓
Membership
    ↓
Organization
    ↓
Role
    ↓
Permission

Production Scope：

Person
    ↓
ProductionDelegate
    ↓
Production
    ↓
Role
    ↓
Permission

---

# ProductionDelegate

ProductionDelegateは、
Production ScopeにおいてPersonへRoleを適用する関係を表す。

主な識別子：

ProductionDelegateId

主なReference：

ProductionId
PersonId
RoleId

基本構造：

Production
    ↓
ProductionDelegate
    ├── Person
    └── Role

ProductionDelegateはProductionの子Entityである。

ProductionDelegateはPermissionを直接保持しない。

PermissionはRoleから決定する。

DelegateRoleおよびDelegateRoleIdは使用しない。

---

# Project

ProjectはOrganizationが行う
活動・制作の内部単位を表す。

主な識別子：

ProjectId

主なReference：

OrganizationId

基本構造：

Organization
    ↓
Project
    ↓
Production

ProjectはInternal Domainである。

---

# Production

ProductionはProjectに所属する
具体的な公演・活動を表す。

主な識別子：

ProductionId

主なReference：

ProjectId
PrimaryManagerId

基本構造：

Organization
    ↓
Project
    ↓
Production

Productionは一人のPrimaryManagerを持つ。

---

# PrimaryManager

PrimaryManagerはProductionの
管理責任者を表す。

主なReference：

Production.PrimaryManagerId
    ↓
Person.PersonId

PrimaryManagerはProductionに関する
全管理権限を持つ。

PrimaryManagerはRoleによる
ProductionDelegateではない。

---

# Participant

ParticipantはPersonまたはOrganizationが
Productionへ参加していることを表す。

主な識別子：

ParticipantId

主なReference：

ProductionId
SubjectId

基本構造：

Production
    ↓
Participant
    ↓
Subject

ParticipantはProductionへの参加Factを表す。

Participant TypeとRoleは異なる概念である。

---

# Subject

SubjectはProductionへの参加主体を
共通Referenceとして表現する。

Subject Type：

PERSON
ORGANIZATION

Subjectは独立したBusiness Entityではなく、
PersonおよびOrganizationを共通参照するための概念である。

---

# Participant Type

Participant TypeはProductionにおける
参加区分を表す。

例：

- CAST
- STAFF

Participant TypeはRoleではない。

Participant Typeによって
管理権限を自動的に付与してはならない。

---

# Performance

PerformanceはProductionにおける
個別の公演回を表す。

主な識別子：

PerformanceId

主なReference：

ProductionId

基本構造：

Production
    ↓
Performance

---

# Seat

SeatはPerformanceに属する座席を表す。

主な識別子：

SeatId

主なReference：

PerformanceId

基本構造：

Performance
    ↓
Seat

Seat自身は予約状態を保持しない。

予約状態はReservationおよびReservationSeatによって判断する。

---

# Reservation

ReservationはPerformanceに対する
予約というFactを表す。

主な識別子：

ReservationId

主なReference：

PerformanceId
BookerId
HandledParticipantId

基本構造：

Performance
    ↓
Reservation
    ├── ReservationSeat
    └── Companion

BookerIdは予約者であるPersonを参照する。

HandledParticipantIdは、
予約における「○○扱い」のParticipantを参照する。

---

# ReservationSeat

ReservationSeatはReservationに紐付く
予約座席を表す。

主な識別子：

ReservationSeatId

主なReference：

ReservationId
SeatId

基本構造：

Reservation
    ↓
ReservationSeat
    ↓
Seat

ReservationSeatはReservationの子Entityである。

---

# Companion

CompanionはReservationに属する
同行者を表す。

主な識別子：

CompanionId

主なReference：

ReservationId

基本構造：

Reservation
    ↓
Companion

---

# RehearsalCandidate

RehearsalCandidateは
稽古候補日を表す。

主な識別子：

RehearsalCandidateId

主なReference：

ProductionId

基本構造：

Production
    ↓
RehearsalCandidate

---

# RehearsalAvailability

RehearsalAvailabilityはPersonが
RehearsalCandidateに対して回答した
参加可能状況を表す。

主な識別子：

RehearsalAvailabilityId

主なReference：

RehearsalCandidateId
PersonId

基本構造：

RehearsalCandidate
    ↓
RehearsalAvailability
    ↓
Person

---

# Rehearsal

Rehearsalは確定した稽古を表す。

主な識別子：

RehearsalId

主なReference：

ProductionId

基本構造：

Production
    ↓
Rehearsal

RehearsalはRehearsalCandidateから
生成することも、直接作成することもできる。

---

# RehearsalAttendance

RehearsalAttendanceは
確定したRehearsalへの参加状況を表す。

主な識別子：

RehearsalAttendanceId

主なReference：

RehearsalId
PersonId

基本構造：

Rehearsal
    ↓
RehearsalAttendance
    ↓
Person

---

# Timetable

TimetableはProductionにおける
日別進行・予定を表す。

主な識別子：

TimetableId

主なReference：

ProductionId

基本構造：

Production
    ↓
Timetable
    ↓
TimetableItem

---

# TimetableItem

TimetableItemはTimetable内の
個別項目を表す。

主な識別子：

TimetableItemId

主なReference：

TimetableId

---

# Ticket

TicketはProductionにおける
チケット販売・利用条件を表す。

主な識別子：

TicketId

主なReference：

ProductionId

TicketはPerformance、
Reservationなどと関連する。

---

# IssuedTicket

IssuedTicketは予約成立後などに
発行される個別のチケットを表す。

主な識別子：

IssuedTicketId

主なReference：

ReservationId

基本構造：

Reservation
    ↓
IssuedTicket

---

# CheckIn

CheckInは公演当日の入場受付という
Factを表す。

主な識別子：

CheckInId

主なReference：

IssuedTicketId

基本構造：

IssuedTicket
    ↓
CheckIn

CheckIn完了時に
CheckInCompletedを発生させる。

---

# Ticket Revenue

Ticket RevenueはTicket / CheckInに関連する
会計連携対象のRevenueを表す。

基本フロー：

CheckInCompleted
    ↓
Ticket Revenue
    ↓
Accounting

Ticket RevenueはAccountingの
Journal Entryそのものではない。

---

# Budget

BudgetはProduction単位の
予算計画を表す。

主な識別子：

BudgetId

主なReference：

ProductionId

基本構造：

Production
    ↓
Budget
    ↓
BudgetItem

---

# BudgetItem

BudgetItemはBudget内の
個別予算項目を表す。

主な識別子：

BudgetItemId

主なReference：

BudgetId

---

# ProductionActual

ProductionActualはProduction単位の
実績金額を表す。

主な識別子：

ProductionActualId

主なReference：

ProductionId

BudgetおよびProductionActualは、
Organization Accountingとは異なる。

---

# Accounting

AccountingはOrganization単位の
会計正本を管理する。

基本構造：

Organization
    ↓
Accounting
    ├── AccountingPeriod
    ├── Account
    └── JournalEntry
            └── JournalEntryLine

---

# AccountingPeriod

AccountingPeriodはOrganizationにおける
会計期間を表す。

主な識別子：

AccountingPeriodId

主なReference：

OrganizationId

---

# Account

Accountは会計上の勘定科目を表す。

主な識別子：

AccountId

主なReference：

OrganizationId

AccountはAccounting DomainのEntityである。

AccountはAuthentication Identityではない。

UserAccountとは完全に別の概念である。

---

# JournalEntry

JournalEntryは会計上の仕訳を表す。

主な識別子：

JournalEntryId

主なReference：

OrganizationId
AccountingPeriodId

JournalEntryはJournalEntryLineによって構成する。

---

# JournalEntryLine

JournalEntryLineはJournalEntryを構成する
借方・貸方の明細を表す。

主な識別子：

JournalEntryLineId

主なReference：

JournalEntryId
AccountId

---

# Equipment

EquipmentはOrganizationが管理する
備品を表す。

主な識別子：

EquipmentId

主なReference：

OrganizationId

Equipmentは資産会計のための
Accounting Assetではない。

---

# Regulation

RegulationはOrganizationの
規約を表す。

主な識別子：

RegulationId

主なReference：

OrganizationId

基本構造：

Organization
    ↓
Regulation
    ↓
RegulationVersion

---

# RegulationVersion

RegulationVersionはRegulationの
各Versionを表す。

既存Versionを上書きせず、
変更時には新しいVersionを作成する。

---

# Document

DocumentはOrganization、Project、
Productionなどに関連する
文書・ファイル情報を表す。

基本構造：

Organization
    ├── Document
    └── Project
            └── Production
                    └── Document

実ファイルはGoogle Driveなどの
外部ストレージに保存できる。

StageArtは実ファイルそのものを
正本として管理しない。

---

# Announcement

AnnouncementはOrganizationまたはProductionの
関係者へ送信する内部のお知らせを表す。

基本構造：

Organization
    ↓
Announcement

または、

Production
    ↓
Announcement

---

# Survey

SurveyはOrganizationまたはProductionの
関係者から回答を収集する。

基本構造：

Production
    ↓
Survey
    ↓
SurveyResponse

---

# ExternalConnection

ExternalConnectionはOrganizationと
外部サービスとの接続関係を表す。

主な識別子：

ExternalConnectionId

主なReference：

OrganizationId
ServiceId
CredentialId

基本構造：

Organization
    ↓
ExternalConnection
    ├── Service
    └── Credential

ExternalConnectionはOrganizationの子Entityである。

---

# Service

ServiceはStageArtが接続可能な
外部サービスの種類を表す。

主な識別子：

ServiceId

例：

- X
- Instagram
- Facebook
- YouTube
- TikTok
- LINE
- Google
- Google Drive
- Google Calendar

SNSはServiceの一種として扱う。

---

# Credential

CredentialはExternalConnectionが
外部サービスへ接続するための
認証情報を表す。

主な識別子：

CredentialId

主なReference：

ExternalConnectionId

Secret値そのものはLogical Entityの
通常属性として平文管理しない。

具体的なSecret Storageは
Infrastructure Layerで管理する。

---

# ExternalConnection AccountIdentifier

AccountIdentifierは、
外部サービス上のAccountを識別する情報を表す。

例：

- User Name
- Account ID
- Page ID
- Channel ID

AccountIdentifierは、
StageArt内部のUserAccountとは異なる。

---

# ExternalConnection Status

ExternalConnectionは以下の状態を持つ。

- CONNECTED
- DISCONNECTED
- ERROR

Credential Statusとは別に管理する。

---

# Public Information

Public Informationは
一般利用者へ公開可能な情報を表す。

Public Informationと
Internal Informationを分離する。

UserAccount、
Credential、
Role、
Permission、
Accountingなどの内部情報を
Public Informationとして公開しない。

---

# Organization Public Profile

Organization Public Profileは
Organizationの公開情報を表示する。

基本構造：

Organization
    ↓
Public Profile

公開情報はOrganizationおよび
関連DomainのFactから生成・参照する。

---

# Production Public Page

Production Public Pageは
Productionの公開情報を表示する。

内部管理情報を公開しない。

---

# History

Historyは過去の活動履歴を表す。

HistoryはBusiness Dataの正本ではない。

現在のFactから必要に応じて
生成・参照する。

Personの過去活動については、
HistoricalActivityを利用する。

ProductionやOrganizationの活動履歴についても、
各DomainのFactを正本とする。

---

# Domain Relationship Summary

## Authentication

UserAccount
    ↓
Person

---

## Organization

Person
    ↓
Membership
    ↓
Organization

---

## Organization Role

Person
    ↓
Membership
    ↓
Organization
    ↓
Role
    ↓
Permission

---

## Production

Organization
    ↓
Project
    ↓
Production

---

## Production Role

Person
    ↓
ProductionDelegate
    ↓
Production
    ↓
Role
    ↓
Permission

ProductionDelegateはRoleを参照する。

DelegateRoleという独立Entityは存在しない。

---

## Participation

Production
    ↓
Participant
    ↓
Subject
        ├── Person
        └── Organization

Participant Typeは参加区分を表し、
Roleとは別に管理する。

---

## Ticket

Production
    ↓
Performance
    ↓
Reservation
    ↓
IssuedTicket
    ↓
CheckIn
    ↓
CheckInCompleted
    ↓
Ticket Revenue
    ↓
Accounting

---

## Rehearsal

Production
    ↓
RehearsalCandidate
    ↓
RehearsalAvailability
    ↓
Rehearsal

または、

Production
    ↓
Rehearsal

---

## Accounting

Organization
    ↓
Accounting
    ├── AccountingPeriod
    ├── Account
    └── JournalEntry
            └── JournalEntryLine

---

## External Integration

Organization
    ↓
ExternalConnection
    ├── Service
    └── Credential

---

# Domain Separation

以下の概念は明確に分離する。

UserAccount / Person

UserAccountはAuthentication Identity。

PersonはBusiness Identity。

---

Membership / Participant

MembershipはOrganizationへの所属。

ParticipantはProductionへの参加。

---

Participant Type / Role

Participant Typeは参加区分。

RoleはPermission Set。

---

ProductionDelegate / Role

ProductionDelegateはProduction Scopeにおいて
PersonへRoleを適用する関係。

RoleはPermission Setを定義する。

---

RoleAssignment

RoleAssignmentという独立Domainは作成しない。

RoleをどのScopeで誰に適用するかは、
MembershipまたはProductionDelegateとの関係によって表現する。

---

Account / UserAccount

AccountはAccounting上の勘定科目。

UserAccountはAuthentication Identity。

両者は完全に別Domainである。

---

Budget / ProductionActual / Accounting

BudgetはProduction単位の計画。

ProductionActualはProduction単位の実績。

AccountingはOrganization単位の会計正本。

---

# Aggregate Structure

Organization
    │
    ├── Membership
    ├── Role
    ├── Accounting
    │       ├── AccountingPeriod
    │       ├── Account
    │       └── JournalEntry
    │               └── JournalEntryLine
    │
    ├── Equipment
    ├── Regulation
    ├── Document
    ├── Announcement
    ├── ExternalConnection
    │       ├── Service
    │       └── Credential
    │
    └── Project
            └── Production
                    ├── Participant
                    ├── ProductionDelegate
                    ├── Performance
                    │       ├── Seat
                    │       └── Reservation
                    │               ├── ReservationSeat
                    │               └── Companion
                    │
                    ├── RehearsalCandidate
                    ├── Rehearsal
                    ├── Timetable
                    ├── Budget
                    ├── ProductionActual
                    ├── Document
                    ├── Announcement
                    └── Survey

---

# Aggregate Root

Aggregate | Root
--- | ---
Organization | Organization
Project | Project
Production | Production
Performance | Performance
Reservation | Reservation
Rehearsal | Rehearsal
Timetable | Timetable
Budget | Budget
Accounting | Organization

---

# Design Decisions

Logical ERでは、
Domain間の責務とReferenceを明確にする。

UserAccountはAuthentication Identityとして管理する。

PersonはBusiness Identityとして管理する。

UserAccountとPersonを分離する。

OrganizationはStageArtにおけるTenantである。

PersonとOrganizationの所属関係はMembershipで管理する。

Organization Scopeの権限はRoleで管理する。

RoleはPermission Setを定義する。

Role DefinitionはOrganization Scopeと
Production Scopeで共通利用する。

RoleAssignmentという独立Domainは作成しない。

ProductionDelegateはProduction Scopeにおいて
PersonへRoleを適用する。

DelegateRoleという別のRole体系は使用しない。

DelegateRoleIdは使用しない。

Participant TypeとRoleを分離する。

ProductionはProjectに所属する。

Production関連DomainはProductionを通じて
Organization Scopeに属する。

AccountingはOrganization単位で管理する。

AccountはAccounting上の勘定科目である。

UserAccountとAccountを明確に分離する。

BudgetおよびProductionActualは
Production単位で管理する。

Public InformationとInternal Informationを分離する。

ExternalConnectionはOrganizationの子Entityである。

外部サービス固有のAPI処理はInfrastructure Layerで実装する。

Credentialは平文保存しない。

Blueprintを唯一の設計基準とする。

---

# Design Principles

- UserAccountはAuthentication Identityである。
- PersonはBusiness Identityである。
- UserAccountとPersonを分離する。
- OrganizationはStageArtにおけるTenantである。
- MembershipはPersonとOrganizationの所属関係を表す。
- RoleはPermission Setを定義する。
- RoleはOrganization ScopeとProduction Scopeで共通利用する。
- RoleAssignmentという独立Domainを作成しない。
- ProductionDelegateはProduction ScopeでPersonへRoleを適用する。
- DelegateRoleという別のRole体系を使用しない。
- DelegateRoleIdを使用しない。
- ParticipantとMembershipを分離する。
- Participant TypeとRoleを分離する。
- Organizationの活動・制作はProjectで管理する。
- Projectの下にProductionを持つ。
- Production関連DomainはProductionを通じてOrganization Scopeに属する。
- AccountingはOrganization単位で管理する。
- AccountはAccounting上の勘定科目である。
- UserAccountとAccountを混同しない。
- BudgetはProduction単位の計画である。
- ProductionActualはProduction単位の実績である。
- ProfileとHistoricalActivityを分離する。
- Public InformationとInternal Informationを分離する。
- ExternalConnectionはOrganizationの子Entityである。
- Credentialは平文保存しない。
- 外部サービス固有のAPI処理はInfrastructure Layerで実装する。
- Blueprintを唯一の設計基準とする。