# StageArt Blueprint

# Domain Model : Rehearsal

Version : 3.1

---

# Purpose

Rehearsalは、
Productionにおける稽古・活動予定を管理するDomainである。

Rehearsalは、
稽古がまだ調整中である状態から、
確定して実施される状態まで、
一つのEntityとして管理する。

「稽古予定」と「確定した稽古」を
別のDomainとして分離しない。

RehearsalのLifecycleによって、
現在の状態を表現する。

---

# Concept

Rehearsalは、
Productionに所属する稽古・活動予定である。

基本構造：

Production
    ↓
Rehearsal
    ↓
RehearsalAttendance
    ↓
Person

一つのProductionに、
複数のRehearsalを登録できる。

Rehearsalは、

- 日程調整中
- 参加予定確認中
- 日程確定後
- 実施中
- 実施完了
- 中止

のいずれの状態であっても、
同じRehearsalとして管理する。

---

# Domain Boundary

Rehearsal Domainは、
以下を管理する。

- 稽古予定
- 稽古日時
- 稽古場所
- 稽古内容
- 稽古のLifecycle
- 稽古参加予定者
- 稽古当日の出欠
- Google Calendar連携に必要な情報

Rehearsal Domainは、
RoleやPermissionそのものを定義しない。

AuthorizationはAuthorization Domainで管理する。

Timetableの詳細な進行は、
Timetable Domainで管理する。

---

# Relationship

RehearsalはProductionに所属する。

Production
    ↓
Rehearsal

RehearsalはProjectに直接所属しない。

基本構造：

Organization
    ↓
Project
    ↓
Production
    ↓
Rehearsal

---

# Identity

RehearsalはRehearsalIdによって一意に識別する。

RehearsalIdは変更しない。

日時、
Title、
Locationなどは識別子ではない。

日時変更や内容変更によって、
RehearsalIdを変更しない。

---

# Creation

Rehearsalは、
稽古予定を作成する時点で生成する。

Rehearsal Candidateという別Domainを
経由する必要はない。

基本構造：

Production
    ↓
Rehearsal

Rehearsalを作成した時点では、
まだ日程が確定していない場合がある。

その場合も、
Rehearsal自体は存在する。

Statusによって、
現在のLifecycleを表現する。

---

# Schedule

Rehearsalは、
以下の予定情報を保持する。

- 日付
- 開始日時
- 終了日時
- タイムゾーン

必要に応じて、

- 集合時刻
- 開始予定時刻
- 終了予定時刻
- 解散予定時刻

などを保持できる。

日時変更は、
Rehearsal自身の更新として扱う。

別のCandidateを作成して、
新しいRehearsalを生成する必要はない。

---

# Title

Rehearsalには、
予定の内容を識別するためのTitleを設定できる。

例：

- 稽古
- 通し稽古
- ゲネプロ
- 小屋入り
- 場当たり
- 追加稽古
- スタッフ打ち合わせ

など。

Titleは内部管理および共有表示に利用する。

---

# Description

Rehearsalには、
予定の詳細を記載できる。

例：

- 稽古内容
- 集合場所
- 注意事項
- 持ち物
- その他連絡事項

Descriptionは、
Rehearsalの共有情報として利用する。

---

# Location

Rehearsalには、
実施場所を設定できる。

例：

- 稽古場
- 劇場
- 会議室
- オンライン
- その他

Locationは、
Google Calendar連携時にも利用する。

---

# Online Rehearsal

オンラインで実施するRehearsalにも対応する。

例えば、

- Zoom
- Google Meet
- その他オンライン会議

など。

オンラインURLを設定できる構造を持つ。

具体的なオンラインサービスとの接続は、
External ConnectionおよびInfrastructure Layerで扱う。

---

# Status

Rehearsalは以下の状態を持つ。

- DRAFT
- SCHEDULED
- CONFIRMED
- ACTIVE
- COMPLETED
- CANCELLED

Statusは、
Rehearsal自身のLifecycleを表す。

稽古予定と確定稽古を
別Domainとして管理しない。

---

# DRAFT

DRAFTは、
Rehearsalが作成されたが、
まだ参加者への予定確認を開始していない状態。

稽古管理権限を持つPersonが、
内容を編集できる。

基本的な予定情報を設定する。

---

# SCHEDULED

SCHEDULEDは、
Rehearsalの予定を提示し、
参加予定者の確認を開始している状態。

この状態では、

- 日時
- 場所
- 内容
- 参加予定者

などを確認・調整できる。

RehearsalAttendanceを利用して、
参加予定者から回答を取得する。

SCHEDULEDは、
「候補日」ではない。

Rehearsalとして登録された
一つの具体的な予定である。

---

# CONFIRMED

CONFIRMEDは、
Rehearsalの予定が確定した状態。

CONFIRMEDになっても、
RehearsalAttendanceは削除しない。

それまでに登録された参加予定者を
そのまま保持する。

基本構造：

Rehearsal
    Status = CONFIRMED

    ↓

RehearsalAttendance
    ├── Person A
    ├── Person B
    ├── Person C
    └── Person D

CONFIRMED後も、
必要に応じてAttendanceの変更を許可できる。

CONFIRMEDへのStatus変更によって、
別のRehearsal Entityを生成しない。

---

# ACTIVE

ACTIVEは、
Rehearsalが実施中である状態。

ACTIVEになっても、
RehearsalAttendanceは保持する。

参加予定者の情報を消去せず、
同じRehearsalAttendanceのStatusを
実際の出欠状態へ更新する。

例えば、

Rehearsal
    Status = ACTIVE

    ↓

RehearsalAttendance

Person A
    Status = ATTENDED

Person B
    Status = LATE

Person C
    Status = ABSENT

Person D
    Status = ATTENDED

という状態になる。

---

# COMPLETED

COMPLETEDは、
Rehearsalが実施済みとなった状態。

Rehearsalは削除せず、
過去の稽古履歴として保持する。

RehearsalAttendanceも保持する。

これにより、

- 誰が参加予定だったか
- 誰が参加したか
- 誰が欠席したか
- 誰が遅刻したか

などを後から確認できる。

---

# CANCELLED

CANCELLEDは、
Rehearsalが中止された状態。

Rehearsal自体は削除しない。

既にGoogle Calendarへ連携されている場合、
Calendar Eventの更新・削除などを
External Integrationとして処理できる。

CANCELLEDになったRehearsalの
RehearsalAttendanceも履歴として保持する。

---

# Status Lifecycle

基本的なLifecycleは以下とする。

DRAFT
    ↓
SCHEDULED
    ↓
CONFIRMED
    ↓
ACTIVE
    ↓
COMPLETED

中止の場合：

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

CANCELLEDおよびCOMPLETEDから、
通常のRehearsal Lifecycleへ戻すことはしない。

Status変更によって、
別のRehearsal Entityを作成しない。

---

# RehearsalAttendance

RehearsalAttendanceは、
特定のRehearsalに対する
Personの参加状態を表す。

基本構造：

Rehearsal
    ↓
RehearsalAttendance
    ↓
Person

RehearsalAttendanceは、
Rehearsalの子Entityとして管理する。

RehearsalAttendanceを
独立したDomainとして扱わない。

---

# Attendance Purpose

RehearsalAttendanceは、
稽古予定の確認から
実際の出欠までを一つのEntityで管理する。

つまり、

「この稽古に参加する予定か」

と、

「実際にこの稽古へ参加したか」

を、

同じRehearsalAttendanceのLifecycleとして扱う。

予定確認と実出欠を、
別Domainに分離しない。

---

# Attendance Creation

Rehearsalに参加予定者を設定した時点で、
RehearsalAttendanceを作成する。

基本構造：

Rehearsal
    ↓
RehearsalAttendance
    ↓
Person

RehearsalAttendanceは、
Rehearsalの予定段階から存在する。

CONFIRMEDになった時に
初めて作成するものではない。

---

# Attendance Status

RehearsalAttendanceは、
以下のStatusを持つ。

予定確認段階：

- UNANSWERED
- ATTENDING
- NOT_ATTENDING

実施段階：

- ATTENDED
- LATE
- ABSENT

---

# UNANSWERED

UNANSWEREDは、
Personがまだ参加予定を回答していない状態。

例えば、

Rehearsal
    Status = SCHEDULED

RehearsalAttendance
    Person = A
    Status = UNANSWERED

となる。

---

# ATTENDING

ATTENDINGは、
PersonがそのRehearsalへ
参加予定であることを表す。

例えば、

Rehearsal
    Status = CONFIRMED

RehearsalAttendance
    Person = A
    Status = ATTENDING

となる。

この状態は、
当日の実績ではなく、
参加予定を表す。

---

# NOT_ATTENDING

NOT_ATTENDINGは、
PersonがそのRehearsalへ
参加しない予定であることを表す。

予定確認段階で設定できる。

---

# Attendance Update Before Rehearsal

Rehearsal実施前であれば、
参加予定を変更できる。

例えば、

ATTENDING
    ↓
NOT_ATTENDING

または、

NOT_ATTENDING
    ↓
ATTENDING

など。

日程確定後にPersonの予定が変わった場合も、
同じRehearsalAttendanceを更新する。

別のAttendance Entityを作成しない。

---

# Attendance During Rehearsal

RehearsalがACTIVEになった場合、
RehearsalAttendanceは実際の出欠状態を表す。

例えば、

ATTENDING
    ↓
ATTENDED

ATTENDING
    ↓
LATE

ATTENDING
    ↓
ABSENT

など。

予定段階のAttendanceを削除して、
新しい出欠Entityを作成することはしない。

同じRehearsalAttendanceのStatusを変更する。

---

# Attendance Status Transition

基本的な状態遷移：

UNANSWERED
    ↓
ATTENDING
    ↓
ATTENDED

または、

UNANSWERED
    ↓
NOT_ATTENDING

または、

ATTENDING
    ↓
LATE

ATTENDING
    ↓
ABSENT

実際の運用に応じて、
稽古開始後の変更を許可する。

---

# Attendance and Participant

RehearsalAttendanceの対象者は、
ProductionのParticipantを基本とする。

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

Participantは、
Productionへの参加Factを表す。

RehearsalAttendanceは、
特定Rehearsalへの参加予定および出欠を表す。

したがって、
ParticipantとRehearsalAttendanceは
同じDomainではない。

---

# Attendance Target

Attendance対象者は、
Rehearsalごとに保持する。

ProductionのParticipant全員が、
常にすべてのRehearsalの対象になるとは限らない。

例えば、

Production
    ↓
Participant
    ├── Person A
    ├── Person B
    ├── Person C
    └── Person D

Rehearsal A
    ↓
RehearsalAttendance
    ├── Person A
    └── Person B

Rehearsal B
    ↓
RehearsalAttendance
    ├── Person B
    ├── Person C
    └── Person D

のように、
Rehearsalごとに対象者を設定できる。

---

# Participant as Reference

Participantは、
RehearsalAttendanceの対象者を
決定するための参照元として利用する。

Participant自身に
Rehearsalへの出欠情報を保持しない。

Participantに、

- ATTENDING
- NOT_ATTENDING
- ATTENDED
- ABSENT

などのRehearsal Attendance Statusを
追加しない。

これらはすべて、
RehearsalAttendanceで管理する。

---

# Attendance and Participant Type

RehearsalAttendanceの対象者を
Participant Typeによって
絞り込むことができる。

例えば、

Participant Type = CAST

のPersonを対象として、
RehearsalAttendanceを作成できる。

また、

Participant Type = STAFF

を対象として、
スタッフ向けのRehearsalAttendanceを
作成することもできる。

ただし、
Participant Typeそのものは
Rehearsalへの参加を意味しない。

実際にそのRehearsalの対象者であることは、
RehearsalAttendanceによって表現する。

---

# Attendance and Production Status

RehearsalAttendanceは、
ProductionのLifecycleとは独立して管理する。

ProductionがACTIVEであっても、
すべてのRehearsalがACTIVEとは限らない。

Production
    Status = ACTIVE

Rehearsal A
    Status = COMPLETED

Rehearsal B
    Status = CONFIRMED

Rehearsal C
    Status = SCHEDULED

という状態を許可する。

---

# Attendance and Rehearsal Status

Rehearsal Statusと
RehearsalAttendance Statusは、
別の概念である。

Rehearsal Status：

稽古そのもののLifecycle。

RehearsalAttendance Status：

特定Personの参加予定・出欠。

例えば、

Rehearsal
    Status = ACTIVE

RehearsalAttendance
    Person A
    Status = ATTENDED

RehearsalAttendance
    Person B
    Status = ABSENT

という状態を許可する。

---

# Attendance Preservation

RehearsalのStatusが、

- CONFIRMED
- ACTIVE
- COMPLETED
- CANCELLED

へ変更されても、
既存のRehearsalAttendanceを削除しない。

参加予定から実績への変更は、
同じRehearsalAttendanceのStatus変更として管理する。

これにより、

「誰が参加予定だったか」

と、

「実際に誰が参加したか」

の両方を一つのEntityの履歴として
追跡できる。

---

# Rehearsal Management Authorization

Rehearsalの作成・変更・確定・実施管理などは、
Authorizationによって制御する。

Rehearsal Domain自身は、
RoleやPermissionを定義しない。

Production ScopeのRoleを利用して、
Rehearsal管理権限を付与する。

PrimaryManagerは、
Productionに関する全管理権限を持つ。

ProductionDelegateは、
適用されたRoleのPermissionに応じて
Rehearsalを管理できる。

Participant Typeによって、
Rehearsal管理権限を自動付与しない。

---

# Google Calendar

Rehearsalは、
Google Calendarなどの外部Calendarへ
連携できる。

基本構造：

Rehearsal
    ↓
External Calendar Event

External Calendar Eventは、
外部Artifactとして扱う。

StageArt側のRehearsalを正本とする。

External Calendar側のEventを
Rehearsalそのものの正本とはしない。

---

# Calendar Synchronization

Rehearsalの変更に応じて、
External Calendar Eventを更新できる。

例えば、

Rehearsal作成
    ↓
Calendar Event作成

日時変更
    ↓
Calendar Event更新

Location変更
    ↓
Calendar Event更新

CANCELLED
    ↓
Calendar Event更新または削除

など。

外部Calendar APIへのアクセスは、
Infrastructure Layerが担当する。

Rehearsal Domainは、
特定CalendarサービスのAPIへ直接依存しない。

---

# Audit Information

Rehearsalの重要な変更について、
監査情報を保持できる。

基本的な監査情報：

- CreatedBy
- CreatedAt
- UpdatedBy
- UpdatedAt

Status変更についても、
必要に応じて履歴を保持する。

RehearsalAttendanceについても、

- 回答者
- 回答日時
- Status変更
- 出欠変更

などを監査対象とする。

---

# Business Rules

- RehearsalはProductionに所属する。
- RehearsalはProjectに直接所属しない。
- Rehearsalは一つのEntityとしてLifecycleを管理する。
- 稽古予定と確定稽古を別Domainとして管理しない。
- Rehearsal Candidateという別Domainを作成しない。
- RehearsalのStatusによってLifecycleを表現する。
- Rehearsalの日時変更によってRehearsalIdを変更しない。
- RehearsalはDRAFTからLifecycleを開始できる。
- SCHEDULEDは具体的な稽古予定を表す。
- SCHEDULEDは候補日を表さない。
- CONFIRMEDになってもRehearsalAttendanceを削除しない。
- ACTIVEになってもRehearsalAttendanceを削除しない。
- COMPLETEDになってもRehearsalAttendanceを保持する。
- CANCELLEDになってもRehearsalAttendanceを保持する。
- RehearsalAttendanceはRehearsalの子Entityである。
- RehearsalAttendanceを独立Domainとして扱わない。
- RehearsalAttendanceは予定確認から実際の出欠までを管理する。
- 予定確認と実際の出欠を別Entityに分離しない。
- RehearsalAttendanceはRehearsalの予定段階から存在できる。
- Attendance StatusはUNANSWERED / ATTENDING / NOT_ATTENDING / ATTENDED / LATE / ABSENTを基本とする。
- 参加予定の変更は同じRehearsalAttendanceのStatus変更で管理する。
- 実際の出欠への変更も同じRehearsalAttendanceのStatus変更で管理する。
- ParticipantはRehearsalAttendanceそのものではない。
- ParticipantはRehearsalAttendanceの対象者を決定する参照元として利用できる。
- ParticipantにRehearsal Attendance Statusを保持しない。
- Production Participant全員がすべてのRehearsalの対象になるとは限らない。
- RehearsalごとにAttendance対象者を設定できる。
- Participant TypeによってAttendance対象者を絞り込むことができる。
- Participant TypeそのものはRehearsalへの参加を意味しない。
- Rehearsal StatusとRehearsalAttendance Statusを混同しない。
- Rehearsal管理権限はAuthorizationによって制御する。
- Participant TypeによってRehearsal管理権限を自動付与しない。
- PrimaryManagerはProductionに関する全管理権限を持つ。
- ProductionDelegateは適用RoleのPermissionに応じてRehearsalを管理できる。
- RehearsalはExternal Calendarへ連携できる。
- StageArt側のRehearsalを正本とする。
- External Calendar Eventは外部Artifactとして扱う。
- External Calendar APIへのアクセスはInfrastructure Layerが担当する。
- Rehearsalを物理削除して過去の稽古Factを失わない。

---

# Domain Events

Rehearsalに関する主なDomain Event：

- RehearsalCreated
- RehearsalScheduled
- RehearsalConfirmed
- RehearsalStarted
- RehearsalCompleted
- RehearsalCancelled
- RehearsalUpdated

RehearsalAttendanceに関するEvent：

- RehearsalAttendanceCreated
- RehearsalAttendanceUpdated
- RehearsalAttendanceConfirmed
- RehearsalAttendanceChanged
- RehearsalAttendanceCompleted

具体的なEvent名は、
実装時にEvent Catalogとの整合を取る。

---

# Design Decisions

RehearsalはProductionに所属する。

基本構造：

Organization
    ↓
Project
    ↓
Production
    ↓
Rehearsal

Rehearsalは、
稽古予定から実施完了までを
一つのEntityとして管理する。

稽古予定と確定稽古を
別Entityとして管理しない。

Rehearsal Candidateという概念は採用しない。

RehearsalのLifecycleはStatusで管理する。

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

Rehearsalの中止はCANCELLEDで表現する。

RehearsalAttendanceは、
Rehearsalの子Entityとして管理する。

RehearsalAttendanceは、
稽古予定の確認から
実際の出欠までを一つのEntityで管理する。

予定確認と出欠確認を
別Domainに分離しない。

RehearsalがCONFIRMEDになっても、
RehearsalAttendanceを削除しない。

RehearsalがACTIVEになっても、
RehearsalAttendanceを削除しない。

参加予定から実際の出欠への変更は、
同じRehearsalAttendanceのStatus変更として管理する。

Participantは、
Productionへの参加Factを表す。

RehearsalAttendanceは、
特定Rehearsalへの参加予定・出欠を表す。

ParticipantとRehearsalAttendanceを混同しない。

Participantは、
RehearsalAttendanceの対象者を決定する
参照元として利用する。

ParticipantにRehearsalの出欠情報を保持しない。

Rehearsalごとに、
Attendance対象者を設定できる。

Rehearsal管理権限は、
Production ScopeのAuthorizationによって制御する。

Participant Typeは、
Rehearsal管理権限を付与しない。

External Calendarは、
Rehearsalの外部連携先であり、
StageArt側のRehearsalを正本とする。

---

# Future

将来的に必要となった場合、

- 稽古ごとのAgenda
- 稽古内容の詳細管理
- 稽古場設備情報
- オンライン会議情報
- 稽古メモ
- 稽古結果
- Attendanceへのコメント
- 遅刻・早退時刻
- 稽古ごとの役割
- 稽古資料
- 稽古写真
- Calendar連携強化

などへ拡張できる。

ただし、
稽古予定と確定稽古を別Entityへ分割することはしない。

また、
参加予定と実出欠を別Attendance Entityへ分割しない。

---

# Design Principles

- RehearsalはProductionに所属する。
- RehearsalはProjectに直接所属しない。
- Rehearsalは一つのEntityとしてLifecycleを管理する。
- 稽古予定と確定稽古を別Domainにしない。
- Rehearsal Candidateを作らない。
- RehearsalのLifecycleはStatusで管理する。
- DRAFT / SCHEDULED / CONFIRMED / ACTIVE / COMPLETED / CANCELLEDを基本Statusとする。
- RehearsalAttendanceはRehearsalの子Entityである。
- RehearsalAttendanceを独立Domainとして扱わない。
- 予定確認から実際の出欠まで同じRehearsalAttendanceで管理する。
- CONFIRMEDになってもAttendanceを削除しない。
- ACTIVEになってもAttendanceを削除しない。
- COMPLETEDになってもAttendanceを保持する。
- CANCELLEDになってもAttendanceを保持する。
- 参加予定の変更はAttendance Statusの変更で管理する。
- 実際の出欠もAttendance Statusの変更で管理する。
- ParticipantはProductionへの参加Factである。
- RehearsalAttendanceはRehearsalへの参加予定・出欠Factである。
- ParticipantとRehearsalAttendanceを分離する。
- ParticipantはAttendance対象者を決定する参照元として利用する。
- ParticipantにRehearsal出欠を保持しない。
- RehearsalごとにAttendance対象者を設定できる。
- Participant Typeによって対象者を絞り込める。
- Participant TypeをAuthorizationとして利用しない。
- Rehearsal StatusとAttendance Statusを分離する。
- Rehearsal管理権限はAuthorizationで管理する。
- PrimaryManagerはProductionに関する全管理権限を持つ。
- ProductionDelegateはRoleのPermissionに応じてRehearsalを管理する。
- External Calendar Eventは外部Artifactである。
- StageArt側のRehearsalを正本とする。
- Blueprintを唯一の設計基準とする。