# StageArt Blueprint

# Domain Model : RehearsalAttendance

Version : 2.0

---

# Purpose

RehearsalAttendanceは、
特定のRehearsalに対するPersonの
参加予定および参加実績を表すEntityである。

RehearsalAttendanceは、
Rehearsal Domainに属する子Entityとして扱う。

RehearsalAttendanceを独立したBusiness Domainとして扱わない。

基本構造：

Production
    ↓
Rehearsal
    ↓
RehearsalAttendance
    ↓
Person

---

# Concept

RehearsalAttendanceは、
特定のRehearsalに対して
Personが参加する予定であること、
および実際に参加した結果を管理する。

RehearsalのLifecycleに応じて、
同じRehearsalAttendanceのStatusを変更する。

稽古予定と確定稽古、
参加予定と実績を
別Entityとして作成しない。

---

# Ownership

RehearsalAttendanceは、
Rehearsalに所属する。

基本構造：

Rehearsal
    ↓
RehearsalAttendance

RehearsalAttendanceは、
Productionに直接所属するのではなく、
Rehearsalを通じてProductionに属する。

Organization Scopeについても、
Rehearsalを通じてProduction、
Project、
Organizationへ遡ることができる。

---

# Person

RehearsalAttendanceは、
対象となるPersonをReferenceする。

基本構造：

Rehearsal
    ↓
RehearsalAttendance
    ↓
Person

Personは、
RehearsalAttendanceを直接所有しない。

Personが複数のRehearsalへ参加する場合、
それぞれのRehearsalについて
RehearsalAttendanceが存在する。

---

# Rehearsal

Rehearsalは、
Productionに所属する稽古・活動予定を表す。

基本構造：

Production
    ↓
Rehearsal
    ↓
RehearsalAttendance

Rehearsalは、
稽古予定の作成から、
日程確定、
実施中、
実施完了までを
一つのEntityとして管理する。

RehearsalCandidateを使用しない。

RehearsalAvailabilityを使用しない。

---

# Rehearsal Lifecycle

RehearsalはStatusによって
Lifecycleを管理する。

主なStatus：

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

RehearsalのStatus変更によって、
RehearsalAttendanceを別Entityへ変換しない。

---

# Attendance Lifecycle

RehearsalAttendanceは、
Rehearsalの予定段階から作成・保持できる。

予定確認段階では、
以下のStatusを使用する。

- UNANSWERED
- ATTENDING
- NOT_ATTENDING

基本的な状態：

UNANSWERED
    ↓
ATTENDING

または、

UNANSWERED
    ↓
NOT_ATTENDING

Personが回答を変更する場合は、
同じRehearsalAttendanceのStatusを変更する。

例えば、

ATTENDING
    ↓
NOT_ATTENDING

のような変更を許可できる。

---

# Active Attendance

RehearsalがACTIVEになった場合、
RehearsalAttendanceは実際の参加結果を記録する。

実施段階では、
以下のStatusを使用する。

- ATTENDED
- LATE
- ABSENT

基本的な状態：

ATTENDING
    ↓
ATTENDED

または、

ATTENDING
    ↓
LATE

または、

ATTENDING
    ↓
ABSENT

実際の運用に応じて、
必要な状態変更ルールを
Rehearsal Domainで定義する。

---

# Attendance Retention

RehearsalがCONFIRMEDになっても、
RehearsalAttendanceを削除しない。

RehearsalがACTIVEになっても、
RehearsalAttendanceを削除しない。

RehearsalがCOMPLETEDになっても、
RehearsalAttendanceを削除しない。

例えば、

Rehearsal
    Status = SCHEDULED
        ↓
RehearsalAttendance
    Status = ATTENDING

から、

Rehearsal
    Status = ACTIVE
        ↓
RehearsalAttendance
    Status = ATTENDED

へ、
同じRehearsalAttendanceのStatusを変更する。

これにより、

- 参加予定者
- 不参加予定者
- 実際の参加者
- 遅刻者
- 欠席者

を同じEntityから確認できる。

---

# Attendance Target

RehearsalAttendanceの対象者は、
ProductionのParticipantを基本とする。

ただし、
Production Participant全員が
すべてのRehearsalに参加するとは限らない。

そのため、
Rehearsalごとに
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

RehearsalAttendanceは、
特定Rehearsalへの参加対象を表す。

---

# Participant Relationship

Participantは、
PersonまたはOrganizationが
Productionへ参加しているFactを表す。

RehearsalAttendanceは、
特定Rehearsalに対するPersonの
参加予定・実績を表す。

したがって、

Participant
    ↓
Productionへの参加

RehearsalAttendance
    ↓
Rehearsalへの参加

という異なる責務を持つ。

Participantを削除・変更することで、
過去のRehearsalAttendanceを
削除してはならない。

過去のAttendanceは、
過去のRehearsalにおけるFactとして保持する。

---

# Organization Scope

RehearsalAttendanceは、
Rehearsalを通じてOrganization Scopeを判定する。

基本構造：

Organization
    ↓
Project
    ↓
Production
    ↓
Rehearsal
    ↓
RehearsalAttendance
    ↓
Person

RehearsalAttendance自身に
OrganizationIdを直接保持することを
必須としない。

---

# Permission

RehearsalAttendanceの参照・変更権限は、
Rehearsalの所属するProduction Scopeおよび
Organization ScopeのAuthorizationによって決定する。

例えば、

- Organization Administrator
- Rehearsal Manager
- Production PrimaryManager
- 適切なProductionDelegate

などが、
RehearsalAttendanceを管理できる。

具体的なPermissionは、
Role / Authorization Domainで定義する。

---

# Person Self Response

Person自身が
自分のRehearsalAttendanceを回答できる。

例えば、

Person
    ↓
RehearsalAttendance
    Status = UNANSWERED

から、

Person
    ↓
RehearsalAttendance
    Status = ATTENDING

または、

Person
    ↓
RehearsalAttendance
    Status = NOT_ATTENDING

へ変更できる。

Person自身が回答できる範囲は、
Authorization DomainのPermissionによって決定する。

---

# Manager Update

Rehearsalを管理する権限を持つPersonは、
必要に応じてRehearsalAttendanceを更新できる。

例えば、

- Person本人の回答を代理して登録する
- 実際の参加結果を記録する
- Attendance Statusを修正する

など。

ただし、
誰がStatusを変更したかを
監査上必要とする場合は、
Audit Informationとして記録する。

---

# Attendance Status

RehearsalAttendanceのStatusは、
予定段階と実施段階を表現する。

## Planned Status

### UNANSWERED

参加予定について、
Personからまだ回答されていない。

### ATTENDING

Personが参加予定であることを回答している。

### NOT_ATTENDING

Personが参加しない予定であることを回答している。

---

## Actual Status

### ATTENDED

Personが実際にRehearsalへ参加した。

### LATE

Personが遅刻して参加した。

### ABSENT

Personが参加予定だったが、
実際には参加しなかった。

---

# Status Transition

基本的なStatus Transitionは以下とする。

UNANSWERED
    ├──→ ATTENDING
    └──→ NOT_ATTENDING

回答変更：

ATTENDING
    └──→ NOT_ATTENDING

NOT_ATTENDING
    └──→ ATTENDING

実施：

ATTENDING
    ├──→ ATTENDED
    ├──→ LATE
    └──→ ABSENT

必要に応じて、
ManagerによるStatus修正を許可する。

Status Transitionの詳細ルールは、
Rehearsal Domainで定義する。

---

# Rehearsal Cancellation

RehearsalがCANCELLEDになった場合、
RehearsalAttendanceを削除しない。

Attendanceは、
そのRehearsalに対して登録されていた
参加予定情報として保持できる。

RehearsalのStatusがCANCELLEDであることによって、
対象Rehearsalが中止されたことを判定する。

RehearsalAttendanceのStatusを
別のCANCELLEDへ変更することを
必須としない。

---

# Rehearsal Completion

RehearsalがCOMPLETEDになった場合、
RehearsalAttendanceは
実際の参加結果を保持する。

例：

Rehearsal
    Status = COMPLETED

RehearsalAttendance
    Person A = ATTENDED
    Person B = LATE
    Person C = ABSENT

この情報を、
過去のRehearsalの参加実績として参照できる。

---

# Historical Fact

RehearsalAttendanceの実施結果は、
過去のRehearsalに関するFactとなる。

例えば、

- Person Aが参加した
- Person Bが遅刻した
- Person Cが欠席した

という情報は、
RehearsalAttendanceから参照できる。

必要に応じて、
History DomainがこれらのFactを
活動履歴として利用できる。

RehearsalAttendance自身が
Historyを直接管理することはない。

---

# Uniqueness

同一Rehearsalに対して、
同一PersonのRehearsalAttendanceは
原則として一つとする。

論理的な一意性：

RehearsalId + PersonId

これにより、

同一Rehearsal
    +
同一Person

に対して複数のAttendanceが
作成されることを防ぐ。

---

# Creation

RehearsalAttendanceは、
Rehearsalの参加対象者が設定された時点で
作成できる。

例えば、

Rehearsal
    ↓
対象Person A
対象Person B
対象Person C

に対して、

RehearsalAttendance
    ├── Person A
    ├── Person B
    └── Person C

を作成する。

初期Statusは、
通常UNANSWEREDとする。

Person本人が回答することで、
ATTENDINGまたはNOT_ATTENDINGへ変更する。

---

# Rehearsal Confirmation

RehearsalがSCHEDULEDからCONFIRMEDになった場合も、
RehearsalAttendanceは同じEntityを保持する。

例えば、

Rehearsal
    Status = SCHEDULED

Attendance
    Person A = ATTENDING
    Person B = ATTENDING
    Person C = NOT_ATTENDING

から、

Rehearsal
    Status = CONFIRMED

Attendance
    Person A = ATTENDING
    Person B = ATTENDING
    Person C = NOT_ATTENDING

のように、
RehearsalのStatusだけが変更される。

Attendanceを新しく作成し直さない。

---

# Rehearsal Activation

RehearsalがACTIVEになった場合も、
RehearsalAttendanceは保持する。

例えば、

Rehearsal
    Status = ACTIVE

Attendance
    Person A = ATTENDED
    Person B = LATE
    Person C = ABSENT

のように、
実際の参加結果を同じAttendanceへ記録する。

---

# Calendar Integration

RehearsalAttendanceと
Google Calendarの登録対象は、
同一とは限らない。

Google Calendarへの登録対象は、
RehearsalのCalendar連携ルールによって決定する。

例えば、

RehearsalAttendance
    Person A = ATTENDING

であっても、
Calendar登録対象外となる場合がある。

逆に、
RehearsalAttendanceのStatusだけを
Calendar登録可否の唯一の条件としてはならない。

Google CalendarへのAPI操作は、
Infrastructure Layerが担当する。

---

# External Calendar

Rehearsalは、
CONFIRMEDになった場合などに
Google Calendarへ連携できる。

Google Calendar Eventは、
StageArtのRehearsalの正本ではない。

StageArt側のRehearsalを正本とする。

RehearsalAttendanceは、
Google Calendar Eventそのものを
管理しない。

---

# Audit Information

RehearsalAttendanceの重要な変更について、
必要に応じて監査情報を記録できる。

例：

- CreatedBy
- CreatedAt
- UpdatedBy
- UpdatedAt
- AnsweredBy
- AnsweredAt

Status変更の履歴を保持する必要がある場合は、
Audit DomainまたはInfrastructure側の
監査機構によって管理する。

Credentialなどの認証情報を
Audit Informationとして記録しない。

---

# Domain Boundary

RehearsalAttendanceは、
Rehearsal Domainに属する子Entityである。

RehearsalAttendanceが管理するもの：

- RehearsalへのPersonの参加対象
- 参加予定
- 不参加予定
- 実参加
- 遅刻
- 欠席

RehearsalAttendanceが管理しないもの：

- Rehearsal自体の日時
- Rehearsal自体のLifecycle
- Production
- Participantそのもの
- Role
- Permission
- Google Calendar Event
- History

Rehearsal自体のLifecycleは、
Rehearsal Domainで管理する。

Productionへの参加関係は、
Participant Domainで管理する。

権限は、
Role / Authorization Domainで管理する。

外部Calendar連携は、
ExternalConnection / Infrastructure Layerで管理する。

活動履歴は、
History Domainで管理する。

---

# No Candidate

RehearsalAttendanceでは、
RehearsalCandidateというEntityを使用しない。

RehearsalCandidateは、
稽古候補日を管理するための旧概念である。

現在のStageArtでは、
Rehearsal自身を作成し、
Rehearsal Statusによって
Lifecycleを管理する。

---

# No Availability

RehearsalAttendanceでは、
RehearsalAvailabilityというEntityを使用しない。

参加可能日確認のために
別Entityを作成しない。

Personの参加予定は、
RehearsalAttendanceのStatusによって管理する。

---

# Design Decisions

RehearsalAttendanceは、
Rehearsal Domainに属する子Entityである。

RehearsalAttendanceを独立Domainとして扱わない。

RehearsalCandidateは使用しない。

RehearsalAvailabilityは使用しない。

稽古予定と確定稽古を別Entityに分けない。

RehearsalのLifecycleはStatusで管理する。

RehearsalAttendanceは、
Rehearsalの予定段階から作成・保持できる。

RehearsalがCONFIRMEDになっても、
RehearsalAttendanceを削除しない。

RehearsalがACTIVEになっても、
RehearsalAttendanceを削除しない。

RehearsalがCOMPLETEDになっても、
RehearsalAttendanceを削除しない。

参加予定と実参加は、
同じRehearsalAttendanceのStatus変更で管理する。

同一Rehearsalに対する
同一PersonのRehearsalAttendanceは一つとする。

RehearsalAttendanceは、
Rehearsalを通じてProductionに属する。

RehearsalAttendanceは、
Rehearsalを通じてOrganization Scopeを判定する。

Productionへの参加関係はParticipantで管理する。

Rehearsalへの参加関係はRehearsalAttendanceで管理する。

ParticipantとRehearsalAttendanceを同一Entityとして扱わない。

RoleとPermissionはRehearsalAttendanceの責務ではない。

Google Calendar EventはRehearsalAttendanceの正本ではない。

StageArt上のRehearsalを正本とする。

HistoryはRehearsalAttendanceの責務ではない。

---

# Design Principles

- RehearsalAttendanceはRehearsal Domainに属する。
- RehearsalAttendanceはRehearsalの子Entityである。
- RehearsalAttendanceは独立Domainとして扱わない。
- RehearsalCandidateを使用しない。
- RehearsalAvailabilityを使用しない。
- 稽古予定と確定稽古を別Entityに分けない。
- RehearsalのLifecycleはStatusで管理する。
- RehearsalAttendanceは予定段階から保持する。
- CONFIRMEDになってもAttendanceを削除しない。
- ACTIVEになってもAttendanceを削除しない。
- COMPLETEDになってもAttendanceを削除しない。
- 参加予定と実参加は同じAttendanceのStatus変更で管理する。
- 同一Rehearsal × PersonにつきAttendanceは原則1件とする。
- 参加予定者はRehearsalAttendanceによって管理する。
- 実参加者もRehearsalAttendanceによって管理する。
- ParticipantはProductionへの参加Factを表す。
- RehearsalAttendanceはRehearsalへの参加Factを表す。
- ParticipantとRehearsalAttendanceを混同しない。
- Roleは権限を表し、Attendanceを管理しない。
- PermissionはAuthorization Domainで管理する。
- Calendar登録対象とAttendanceを同一視しない。
- Google Calendar Eventは外部Artifactである。
- StageArt側のRehearsalを正本とする。
- HistoryはHistory Domainで管理する。
- Blueprintを唯一の設計基準とする。