# StageArt Blueprint

# Logical ER Diagram

Version : 4.3

---

# Purpose

Logical ER Diagramは、
StageArtの各Domainおよび関連Entityの
論理構造と関係を表現する。

Conceptual ERで定義したBusiness上の関係を、
EntityおよびReferenceとして具体化する。

Database製品固有の型やインデックスなどの
物理設計は、Physical ERまたは実装設計で定義する。

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
                    └── Rehearsal

Organization
    ├── Membership
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
                    ├── Rehearsal
                    │       └── RehearsalAttendance
                    │               └── Person
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

主なReference：

PersonId

基本構造：

UserAccount
    ↓
Person

UserAccountはPersonに関連付ける。

UserAccountはOrganizationやProductionに
直接所属しない。

UserAccountはAuthenticationを担当し、
Business上の人物情報はPersonで管理する。

---

# Person

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
- Reservation

などのBusiness Relationshipを持つ。

基本構造：

Person
    ├── Membership
    ├── Participant
    ├── ProductionDelegate
    ├── Reservation
    └── RehearsalAttendance

---

# Profile

ProfileはPersonに属する
プロフィール情報を表す。

主な識別子：

ProfileId

主なReference：

PersonId

基本構造：

Person
    ↓
Profile

ProfileはPersonの公開・プロフィール情報を管理する。

---

# HistoricalActivity

HistoricalActivityはPersonに関連する
過去の活動実績を表す。

主な識別子：

HistoricalActivityId

主なReference：

PersonId

基本構造：

Person
    ↓
HistoricalActivity

HistoricalActivityは、
Personが入力する過去実績を管理する。

StageArt上で発生したBusiness Factから生成される
Historyとは区別する。

---

# Organization

OrganizationはStageArtにおけるTenantである。

主な識別子：

OrganizationId

Organizationは、

- Membership
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
RoleId

基本構造：

Person
    ↓
Membership
    ↓
Organization
    ↓
Role

MembershipはOrganization Scopeにおける
Personの所属関係を表す。

一つのMembershipは、
基本的に一つのRoleを参照する。

Membershipに関連するRoleは、
Organization ScopeでPersonに適用される。

同じPersonであっても、
OrganizationごとのMembershipによって
異なるRoleを持つことができる。

RoleAssignmentという独立Entityは作成しない。

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

DelegateRoleおよびDelegateRoleIdは使用しない。

---

# Permission

Permissionは、
Resourceに対して実行できる操作を表す。

主な識別子：

PermissionId

Roleとの関係：

Role
    ↓
Permission

PermissionはRoleによって
Personへ間接的に適用される。

Permissionの具体的な定義は、
Authorization Domainで管理する。

---

# ProductionDelegate

ProductionDelegateは、
Production ScopeにおいてPersonへRoleを適用する
関係を表す。

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

一人のPersonは、
複数のProductionのPrimaryManagerになることができる。

Productionには、

- Participant
- ProductionDelegate
- Performance
- Rehearsal
- Timetable
- Budget
- ProductionActual
- Document
- Announcement
- Survey

などが関連する。

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

一人のPersonは、
複数のProductionのPrimaryManagerになることができる。

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

主な識別子：

SubjectId

Subject Type：

PERSON
ORGANIZATION

基本構造：

Participant
    ↓
Subject
    ├── Person
    └── Organization

SubjectはPersonおよびOrganizationを
共通参照するための概念である。

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

予約状態はReservationおよび
ReservationSeatによって判断する。

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

CompanionはReservationの子Entityである。

---

# Rehearsal

RehearsalはProductionに所属する
稽古・活動予定を表す。

主な識別子：

RehearsalId

主なReference：

ProductionId

基本構造：

Production
    ↓
Rehearsal

Rehearsalは、
稽古予定の作成から、
日程確定、
実施中、
実施完了までを
一つのEntityとして管理する。

稽古予定と確定稽古を
別Entityとして管理しない。

---

# Rehearsal Status

RehearsalはStatusによって
Lifecycleを管理する。

Status：

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

# Rehearsal Schedule

Rehearsalは、
稽古の予定情報を保持する。

主な属性：

- Date
- StartAt
- EndAt
- TimeZone
- Title
- Description
- Location

必要に応じて、

- 集合時刻
- 開始予定時刻
- 終了予定時刻
- 解散予定時刻

などを保持する。

主なReference：

ProductionId

日時変更は、
Rehearsal自身の更新として扱う。

---

# RehearsalAttendance

RehearsalAttendanceは、
特定のRehearsalに対するPersonの
参加状態を表す。

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

RehearsalAttendanceは、
Rehearsalの子Entityである。

RehearsalAttendanceを
独立Domainとして管理しない。

---

# RehearsalAttendance Status

RehearsalAttendanceは、
Rehearsalの予定段階から存在できる。

予定確認段階：

- UNANSWERED
- ATTENDING
- NOT_ATTENDING

実施段階：

- ATTENDED
- LATE
- ABSENT

基本的な状態変化：

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

予定段階のAttendanceを削除して、
新しい出欠Entityを作成することはしない。

---

# RehearsalAttendance Retention

RehearsalがCONFIRMEDになっても、
RehearsalAttendanceを保持する。

RehearsalがACTIVEになっても、
RehearsalAttendanceを保持する。

RehearsalがCOMPLETEDになった後も、
RehearsalAttendanceを保持する。

これにより、

- 参加予定者
- 実際の参加者
- 欠席者
- 遅刻者

を同じRehearsalAttendanceから参照できる。

---

# Rehearsal Attendance Target

RehearsalAttendanceの対象者は、
ProductionのParticipantを基本とする。

ただし、
Production Participant全員が
すべてのRehearsalへ参加するとは限らない。

Rehearsalごとに、
参加予定者を設定できる。

基本構造：

Production
    ↓
Participant
    ↓
Person

Production
    ↓
Rehearsal
    ↓
RehearsalAttendance
    ↓
Person

---

# Rehearsal and Participant

RehearsalはParticipantを直接所有しない。

ParticipantはProductionへの参加Factであり、
RehearsalAttendanceは
そのProduction Participantが
特定Rehearsalへ参加する状態を表す。

---

# Rehearsal Calendar Integration

CONFIRMEDとなったRehearsalは、
Google Calendarへ連携できる。

StageArt上のRehearsalを正本とする。

Google Calendar Eventは
External Artifactとして扱う。

Google CalendarへのAPI操作は、
Infrastructure Layerが担当する。

---

# Rehearsal Calendar Reference

必要に応じて、
RehearsalとExternal Calendar Eventとの
連携情報を保持できる。

例えば、

- ExternalServiceId
- ExternalEventId
- ExternalReference
- SynchronizationStatus

など。

これらはRehearsalそのものの属性ではなく、
External Integrationの情報として管理する。

---

# Rehearsal Calendar Target

Google Calendarへの登録対象は、
RehearsalAttendanceと完全には一致しない。

Calendar共有対象となるPersonを、
必要に応じて別途指定できる。

そのため、

RehearsalAttendance
    Status = NOT_ATTENDING

であるPersonであっても、
Calendarへ予定を登録できる。

Calendar登録対象と
RehearsalAttendanceを同一視しない。

---

# Timetable

TimetableはRehearsalなどの
Activity内の詳細な時間割・進行を表す。

主な識別子：

TimetableId

主なReference：

RehearsalId

基本構造：

Rehearsal
    ↓
Timetable
    ↓
TimetableItem

TimetableはRehearsalの詳細な進行を管理する。

Rehearsal自身のLifecycleは
Rehearsal Statusで管理する。

---

# TimetableItem

TimetableItemはTimetable内の
個別項目を表す。

主な識別子：

TimetableItemId

主なReference：

TimetableId

例：

- 集合
- 発声
- 立ち稽古
- シーン稽古
- 休憩
- 通し
- 片付け

など。

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

CheckInは公演当日の
入場受付というFactを表す。

主な識別子：

CheckInId

主なReference：

IssuedTicketId

基本構造：

IssuedTicket
    ↓
CheckIn

CheckIn完了時に、
必要に応じてCheckInCompletedを発生させる。

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

BudgetはOrganization Accountingとは
異なる目的で利用する。

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

基本構造：

JournalEntry
    ↓
JournalEntryLine
    ↓
Account

---

# Equipment

EquipmentはOrganizationに所属する
備品を表す。

主な識別子：

EquipmentId

主なReference：

OrganizationId

基本構造：

Organization
    ↓
Equipment

Equipmentは資産価値を管理するための
Asset Accounting Domainではない。

Equipmentは、

- 所在
- 所有・管理者
- 使用可能状態
- 不明状態
- 廃棄状態

などを管理する。

取得価格、
資産価値、
減価償却は管理しない。

---

# Regulation

RegulationはOrganizationに所属する
規約を表す。

主な識別子：

RegulationId

主なReference：

OrganizationId

RegulationはVersionを持つ。

基本構造：

Organization
    ↓
Regulation
    ├── Version 1
    ├── Version 2
    └── Version 3

既存Versionを上書きせず、
変更時には新しいVersionを作成する。

---

# Document

DocumentはOrganization、
Project、
Productionなどに関連する
文書情報を表す。

主な識別子：

DocumentId

主なReference：

OrganizationId
ProjectId
ProductionId

DocumentはStageArt内で
ファイルに関するMetadataおよび
外部ファイルReferenceを管理する。

実ファイルはGoogle Driveなどの
External Storageに保存できる。

---

# Announcement

AnnouncementはOrganizationまたはProductionの
関係者への内部のお知らせを表す。

主な識別子：

AnnouncementId

主なReference：

OrganizationId
ProductionId

対象者は、

- CAST
- STAFF
- 制作
- その他関係者

などから指定できる。

---

# Survey

SurveyはOrganizationまたはProductionに関連する
回答収集を表す。

主な識別子：

SurveyId

主なReference：

OrganizationId
ProductionId

基本構造：

Organization / Production
    ↓
Survey
    ↓
SurveyResponse

Rehearsalの日程確認そのものは、
RehearsalAttendanceによって管理する。

SurveyとRehearsalAttendanceを
同一Entityとして扱わない。

---

# SurveyResponse

SurveyResponseはSurveyへの
回答を表す。

主な識別子：

SurveyResponseId

主なReference：

SurveyId
PersonId

基本構造：

Survey
    ↓
SurveyResponse
    ↓
Person

---

# ExternalConnection

ExternalConnectionは、
Organizationと外部サービスとの
接続関係を表す。

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

ExternalConnectionはOrganizationの
子Entityである。

---

# Service

Serviceは外部サービスの種類を表す。

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

SNSもServiceの一種として扱う。

---

# Credential

CredentialはExternalConnectionに属する
外部サービスの認証情報を表す。

主な識別子：

CredentialId

主なReference：

ExternalConnectionId

Credentialには必要に応じて、

- OAuth Token
- Access Token
- Refresh Token
- Secret

などを保持する。

Credentialは平文保存しない。

具体的な暗号化、
Secret Storage、
Token更新などは
Infrastructure Layerで管理する。

---

# External Account

ExternalConnectionは、
外部サービス上のAccountを識別するための
Account Identifierを保持できる。

Account Identifierは、

- 外部サービスのAccount ID
- Username
- Page ID
- その他外部サービス上の識別子

などを表す。

StageArt内部のAccountとは別の概念である。

---

# History

Historyは、
StageArt上で発生した活動履歴を表す
独立Domainである。

主な識別子：

HistoryId

Historyは、
Person、
Organization、
Production、
Performance、
RehearsalなどのFactから
生成・参照できる。

Historyを各Domainの単純な子Entityとして
固定的に保持することを前提としない。

---

# History Reference

Historyは必要に応じて、

- Person
- Organization
- Production
- Performance
- Rehearsal

などへ関連付ける。

具体的な履歴生成ルールは
History Domainで定義する。

---

# Public Profile

Organizationの公開情報は、
Public Profileとして提供する。

Public Profileは、
Organizationの内部管理Entityを
そのまま公開するものではない。

公開対象として定義された情報だけを
表示する。

---

# Organization Public Information

OrganizationのPublic Informationは、
Organization内部のFactから生成・参照できる。

例：

- 団体名
- 沿革
- 代表
- メンバー
- 過去公演情報
- SNS情報

内部権限、
会計情報、
Credential、
内部Documentなどは
Public Profileへ公開しない。

---

# Scope Structure

Organization Scope：

Organization
    ├── Membership
    ├── Accounting
    ├── Equipment
    ├── Regulation
    ├── Document
    ├── Announcement
    ├── ExternalConnection
    └── Project

Production Scope：

Production
    ├── Participant
    ├── ProductionDelegate
    ├── Performance
    ├── Ticket
    ├── Reservation
    ├── CheckIn
    ├── Rehearsal
    ├── Timetable
    ├── Budget
    ├── ProductionActual
    ├── Document
    ├── Announcement
    └── Survey

Production Scopeは、
Productionを通じてOrganization Scopeに属する。

---

# Reference Rules

## Organization Reference

Organizationに直接所属するEntityは、
OrganizationIdをReferenceとして保持する。

例：

- Membership
- Equipment
- Regulation
- AccountingPeriod
- Account
- ExternalConnection
- Project

---

## Project Reference

ProjectはOrganizationIdをReferenceとして保持する。

ProductionはProjectIdをReferenceとして保持する。

基本構造：

OrganizationId
    ↓
ProjectId
    ↓
ProductionId

---

## Production Reference

Productionに所属するEntityは、
ProductionIdをReferenceとして保持する。

例：

- Participant
- ProductionDelegate
- Performance
- Rehearsal
- Budget
- ProductionActual
- Document
- Announcement
- Survey

---

## Rehearsal Reference

RehearsalはProductionIdをReferenceとして保持する。

RehearsalAttendanceは、

- RehearsalId
- PersonId

をReferenceとして保持する。

基本構造：

ProductionId
    ↓
RehearsalId
    ↓
RehearsalAttendanceId
    ↓
PersonId

---

## Performance Reference

PerformanceはProductionIdをReferenceとして保持する。

SeatはPerformanceIdをReferenceとして保持する。

ReservationはPerformanceIdをReferenceとして保持する。

基本構造：

ProductionId
    ↓
PerformanceId
    ↓
ReservationId

---

## Reservation Reference

Reservationは、

- PerformanceId
- BookerId
- HandledParticipantId

をReferenceとして保持する。

ReservationSeatは、

- ReservationId
- SeatId

をReferenceとして保持する。

CompanionはReservationIdをReferenceとして保持する。

---

# Rehearsal Lifecycle Model

Rehearsalは以下のStatusを持つ。

DRAFT
    ↓
SCHEDULED
    ↓
CONFIRMED
    ↓
ACTIVE
    ↓
COMPLETED

キャンセル：

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

Status変更によって、
Rehearsal Entity自体を別Entityへ変換しない。

---

# Rehearsal Attendance Model

RehearsalAttendanceは、
RehearsalのLifecycle全体を通じて保持する。

予定確認段階：

Rehearsal
    ↓
RehearsalAttendance
    ↓
Person

Status：

UNANSWERED
ATTENDING
NOT_ATTENDING

実施段階：

Rehearsal
    Status = ACTIVE
        ↓
RehearsalAttendance
        ↓
Person

Status：

ATTENDED
LATE
ABSENT

予定段階の参加者情報を削除して、
新しい出欠Entityへ移行しない。

同じRehearsalAttendanceのStatusを変更する。

---

# Rehearsal Attendance Uniqueness

同一Rehearsalに対して、
同一PersonのRehearsalAttendanceは
原則として一つとする。

論理的な一意性：

RehearsalId + PersonId

この組み合わせによって、
一人のPersonについて
一つのRehearsalAttendanceを識別する。

---

# Rehearsal Calendar Model

Rehearsal
    ↓
External Calendar Event

RehearsalはStageArt側の正本である。

Google Calendar Eventは
外部Artifactである。

RehearsalがCONFIRMEDとなった場合、
Google Calendarへ登録できる。

Rehearsalの日時、
場所、
Title、
Descriptionなどが変更された場合、
連携済みCalendar Eventを更新できる。

RehearsalがCANCELLEDとなった場合、
連携済みCalendar Eventを更新または削除できる。

具体的なAPI処理はInfrastructure Layerで実装する。

---

# Audit Information

主要Entityには、
必要に応じて以下のAudit Informationを持たせる。

- CreatedBy
- CreatedAt
- UpdatedBy
- UpdatedAt

RehearsalAttendanceについても、
必要に応じて、

- AnsweredBy
- AnsweredAt
- UpdatedBy
- UpdatedAt

などを保持できる。

Credentialそのものを
Audit Informationとして記録しない。

---

# Logical ER Design Decisions

UserAccountとPersonを分離する。

AccountとUserAccountを分離する。

AccountはAccounting Domainの勘定科目である。

OrganizationはStageArtにおけるTenantである。

PersonとOrganizationの所属関係はMembershipで管理する。

Organization ScopeのRoleはMembershipを通じて適用する。

一つのMembershipは、
基本的に一つのRoleを参照する。

RoleはPermission Setを定義する。

RoleはOrganization ScopeとProduction Scopeの
両方で利用する。

Role自身はScopeを保持しない。

RoleAssignmentという独立Entityを作成しない。

ProductionDelegateはProduction Scopeで
PersonへRoleを適用する。

DelegateRoleおよびDelegateRoleIdは使用しない。

PrimaryManagerはProductionに対する全権限を持つ。

一人のPersonは、
複数のProductionのPrimaryManagerになることができる。

ParticipantとMembershipを分離する。

Participant TypeとRoleを分離する。

ProductionはProjectに所属する。

PerformanceはProductionに所属する。

ReservationはPerformanceに所属する。

RehearsalはProductionに所属する。

Rehearsalは稽古予定から実施完了までを
一つのEntityとして管理する。

Rehearsal Candidateを作成しない。

Rehearsal Availabilityを作成しない。

RehearsalのLifecycleはStatusで管理する。

RehearsalAttendanceはRehearsalの子Entityである。

RehearsalAttendanceは予定段階から保持する。

RehearsalがCONFIRMEDになっても
RehearsalAttendanceを削除しない。

RehearsalがACTIVEになっても
RehearsalAttendanceを削除しない。

参加予定と実際の出欠は、
同じRehearsalAttendanceのStatus変更で管理する。

同一Rehearsalに対する
同一PersonのRehearsalAttendanceは一つとする。

TimetableはRehearsal内の詳細な進行を管理する。

Google Calendar EventはRehearsalの正本ではない。

RehearsalをStageArt側の正本とする。

Calendar登録対象とRehearsalAttendanceを
同一視しない。

AccountingはOrganization単位で管理する。

BudgetおよびProductionActualは
Production単位で管理する。

Equipmentは資産会計Domainではない。

ExternalConnectionはOrganizationに所属する。

ExternalConnectionはSNS専用ではない。

CredentialはExternalConnectionに所属する。

Credentialは平文保存しない。

外部サービス固有のAPI処理は
Infrastructure Layerで実装する。

Historyは独立Domainとして管理する。

Public InformationとInternal Informationを分離する。

---

# Design Principles

- Logical ERはConceptual ERのBusiness RelationshipをEntity / Referenceへ具体化する。
- Database製品固有の物理設計はLogical ERで定義しない。
- UserAccountはAuthentication Identityである。
- PersonはBusiness Identityである。
- UserAccountとPersonを分離する。
- AccountはAccounting Domainの勘定科目である。
- AccountとUserAccountを分離する。
- OrganizationはStageArtにおけるTenantである。
- PersonとOrganizationの所属関係はMembershipで管理する。
- Organization Scopeの権限はMembershipとRoleで管理する。
- 一つのMembershipは基本的に一つのRoleを参照する。
- RoleはPermission Setを定義する。
- Role DefinitionはOrganization ScopeとProduction Scopeで共通利用する。
- Role自身はScopeを保持しない。
- RoleAssignmentという独立Entityを作成しない。
- ProductionDelegateはProduction ScopeでRoleをPersonへ適用する。
- DelegateRoleという別のRole体系を使用しない。
- PrimaryManagerはProductionに対する全権限を持つ。
- 一人のPersonは複数ProductionのPrimaryManagerになれる。
- ParticipantとMembershipを分離する。
- ParticipantとProductionDelegateを分離する。
- Participant TypeとRoleを分離する。
- ProductionはProjectに所属する。
- PerformanceはProductionに所属する。
- ReservationはPerformanceに所属する。
- RehearsalはProductionに所属する。
- Rehearsalは稽古予定から実施完了までを一つのEntityとして管理する。
- 稽古予定と確定稽古を別Entityとして管理しない。
- Rehearsal Candidateを使用しない。
- Rehearsal Availabilityを使用しない。
- RehearsalのLifecycleはStatusで管理する。
- RehearsalAttendanceはRehearsalの子Entityである。
- RehearsalAttendanceは予定段階から保持する。
- CONFIRMEDになってもRehearsalAttendanceを削除しない。
- ACTIVEになってもRehearsalAttendanceを削除しない。
- 同じRehearsalAttendanceのStatusを予定状態から実績状態へ変更する。
- Rehearsalごとに参加予定者を管理する。
- 同一Rehearsalに対する同一PersonのRehearsalAttendanceは一つとする。
- TimetableはRehearsal内の詳細な進行を管理する。
- Google Calendar EventはRehearsalの正本ではない。
- RehearsalをStageArt側の正本とする。
- Calendar登録対象とRehearsalAttendanceを同一視しない。
- AccountingはOrganization単位で管理する。
- BudgetおよびProductionActualはProduction単位で管理する。
- Equipmentは資産会計を目的としない。
- ExternalConnectionはOrganizationの子Entityである。
- ExternalConnectionはSNSに限定しない。
- Serviceは外部サービスの種類を表す。
- CredentialはExternalConnectionに属する。
- Credentialは平文保存しない。
- 外部サービス固有のAPI処理はInfrastructure Layerで実装する。
- Historyは独立Domainとして管理する。
- Public InformationとInternal Informationを分離する。
- Blueprintを唯一の設計基準とする。