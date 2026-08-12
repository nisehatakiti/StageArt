# StageArt Blueprint

# Domain Model : Rehearsal

Version : 3.0

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
    └── RehearsalAttendance
            ↓
          Person

一つのProductionに、
複数のRehearsalを登録できる。

Rehearsalは、
日程調整中であっても、
確定後であっても、
実施中であっても、
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

RehearsalAttendanceは、
Production ParticipantであるPersonを
基本的な対象者とする。

---

# Attendance Target

Attendance対象者は、
Rehearsalごとに保持する。

つまり、
ProductionのParticipant全員が
常にすべてのRehearsalの対象になるとは限らない。

例えば、

Production
    ├── Actor A
    ├── Actor B
    ├── Staff A
    └── Staff B

Rehearsal A
    ├── Actor A
    ├── Actor B
    └── Staff A

Rehearsal B
    ├── Actor A
    └── Staff B

のように、
Rehearsalごとに参加予定者を設定できる。

---

# Participant Type

Participant Typeは、
RehearsalAttendanceの対象者を
選択・絞り込むために利用できる。

例えば、

- CAST
- STAFF

など。

ただし、
Participant Typeそのものが
Attendance権限を付与するわけではない。

---

# Attendance and Authorization

Rehearsalに参加するPerson自身が、
自分のRehearsalAttendanceを回答できる。

稽古管理権限を持つPersonは、
必要に応じて他のPersonのAttendanceを
確認・管理できる。

具体的な権限は、
Authorization DomainのRole / Permissionで定義する。

Rehearsal Domain自身は、
RoleやPermissionを定義しない。

---

# Rehearsal Management

稽古管理権限を持つPersonは、
必要に応じて以下を行える。

- Rehearsal作成
- Rehearsal変更
- Rehearsal予定の確定
- Rehearsalキャンセル
- Rehearsal開始
- Rehearsal完了
- Attendance確認
- Attendance管理
- Timetable管理
- Rehearsal情報共有

具体的なPermissionは、
Authorization Domainで定義する。

---

# Organization Administrator

Organizationの管理者は、
自身のOrganizationについて
適切な権限を持つ。

そのため、
自身のOrganizationに所属するProductionの
Rehearsalについても、
Authorizationのルールに従って
管理操作を行える。

Organization Administratorという権限概念を
Rehearsal Domain内で独自に定義しない。

---

# Production Delegate

Production単位の管理権限は、
ProductionDelegateによって管理する。

基本構造：

Production
    ↓
ProductionDelegate
    ├── Person
    └── Role

ProductionDelegateが、
Rehearsalに対する権限を持つかどうかは、
適用されたRole / Permissionによって判定する。

ProductionDelegate自体を
Rehearsal DomainのEntityとして定義しない。

---

# Google Calendar Integration

CONFIRMEDとなったRehearsalは、
Google Calendarへ連携できる。

基本Flow：

Rehearsal
    ↓
Google Calendar Integration
    ↓
Google Calendar Event

Google Calendar Eventは、
Rehearsalそのものではない。

RehearsalがStageArtにおける正本であり、
Google Calendar EventはExternal Artifactである。

---

# Calendar Registration Target

Google Calendarへの登録対象は、
RehearsalAttendanceの状態だけで
自動的に決定しない。

Rehearsalの共有対象となるPersonを
Calendar連携対象として指定できる。

そのため、

RehearsalAttendance
    Status = NOT_ATTENDING

であるPersonであっても、
必要に応じてGoogle Calendarへ
予定を登録できる。

Calendar登録対象と
稽古参加予定は別の概念として扱う。

---

# Calendar Integration Timing

Google Calendar連携は、
RehearsalがCONFIRMEDになった後に
実行できる。

基本Flow：

Rehearsal
    Status = CONFIRMED
        ↓
Google Calendar Integration
        ↓
Google Calendar Event

Attendance確認は、
Calendar連携とは独立して管理する。

---

# Calendar Event

StageArtは、
Google Calendar上のEventを
Rehearsalそのものとして管理しない。

Rehearsalが正本であり、
Google Calendar EventはExternal Artifactである。

基本構造：

Rehearsal
    ↓
External Calendar Event
    ↓
Google Calendar

Google Calendar側のEvent IDなどを、
External Integration情報として保持できる。

---

# Calendar Update

Rehearsalの日時、
場所、
Title、
Descriptionなどが変更された場合、
連携済みGoogle Calendar Eventを
更新できる。

Google Calendar側の変更を、
StageArtのRehearsalへ自動反映することを
必須とはしない。

StageArtのRehearsalを正本とする。

---

# Calendar Cancellation

RehearsalがCANCELLEDとなった場合、
連携済みGoogle Calendar Eventも
必要に応じて更新または削除する。

この処理はExternal Integrationの責務とする。

---

# Timetable Relationship

Rehearsalには、
稽古内容や進行情報を関連付けることができる。

ただし、
詳細な時間割や稽古内の進行は
Timetable Domainで管理する。

基本構造：

Production
    ↓
Rehearsal
    ↓
Timetable
    ↓
TimetableItem

Rehearsalは、
稽古そのものの予定を管理する。

Timetableは、
そのRehearsal内の詳細な進行を管理する。

---

# Rehearsal Content

Rehearsalは、
大まかな予定内容を保持できる。

例：

- 立ち稽古
- 台本読み
- 通し
- 歌稽古
- ダンス稽古
- 場当たり
- ゲネプロ

詳細な時間割は、
Timetableで管理する。

---

# Rehearsal History

COMPLETEDとなったRehearsalは、
削除せず履歴として保持する。

RehearsalAttendanceも保持する。

これにより、

- 稽古がいつ行われたか
- 誰が参加予定だったか
- 誰が参加したか
- 誰が欠席したか
- 誰が遅刻したか

などを確認できる。

History Domainを生成する必要がある場合は、
RehearsalおよびRehearsalAttendanceのFactから生成する。

Rehearsal Domain自身が、
Historyを別Entityとして保持することを前提としない。

---

# Deletion

Rehearsalは、
履歴を保持する必要があるため、
通常の運用では物理削除しない。

誤って作成したRehearsalについても、
必要に応じてLifecycle上の状態変更で管理する。

確定後に不要となったRehearsalは、
CANCELLEDなどのStatusによって
履歴を保持する。

---

# Audit Information

Rehearsalの重要な変更について、
監査情報を記録できるようにする。

基本的な監査情報として、

- CreatedBy
- CreatedAt
- UpdatedBy
- UpdatedAt

を利用する。

Attendanceについても、
必要に応じて回答者および更新日時を
監査情報として記録する。

---

# Domain Events

Rehearsalに関する主なDomain Event：

- RehearsalCreated
- RehearsalUpdated
- RehearsalScheduled
- RehearsalConfirmed
- RehearsalStarted
- RehearsalCompleted
- RehearsalCancelled
- RehearsalAttendanceCreated
- RehearsalAttendanceUpdated

Google Calendarへの連携イベントは、
External Integration Domainで扱う。

---

# Automatically Generated

Rehearsal作成時、
StageArtは必要に応じて
RehearsalAttendanceを作成する。

対象者は、
Rehearsalに設定された参加予定者とする。

RehearsalのStatus変更によって、
既存のRehearsalAttendanceを削除しない。

特に、

CONFIRMED
    ↓
ACTIVE

となった場合も、
既存のRehearsalAttendanceを保持し、
Statusを更新する。

---

# Business Rules

- RehearsalはProductionに所属する。
- RehearsalはProjectに直接所属しない。
- 一つのProductionに複数のRehearsalを登録できる。
- Rehearsalは日程調整中から実施完了まで一つのEntityとして管理する。
- Rehearsal Candidateという独立Domainを作成しない。
- Rehearsal Availabilityという独立Domainを作成しない。
- Rehearsal予定と確定Rehearsalを別Domainとして管理しない。
- Rehearsalの状態はStatusによって管理する。
- RehearsalAttendanceはRehearsalの子Entityである。
- RehearsalAttendanceを独立Domainとして管理しない。
- RehearsalAttendanceは予定段階から保持できる。
- CONFIRMEDになってもRehearsalAttendanceを削除しない。
- ACTIVEになってもRehearsalAttendanceを削除しない。
- 予定参加者と実際の出欠は同じRehearsalAttendanceのStatusによって管理する。
- Attendanceの履歴を新しいEntityとして作成することを前提としない。
- RehearsalAttendanceの対象者はProduction Participantを基本とする。
- Rehearsalごとに参加予定者を設定できる。
- Participant Typeは対象者の選択・絞り込みに利用できる。
- Participant TypeはAuthorizationを意味しない。
- Personは自分のRehearsalAttendanceを回答できる。
- 稽古管理権限を持つPersonは必要に応じてAttendanceを管理できる。
- AuthorizationはRole / Permissionによって管理する。
- Rehearsal DomainはRole / Permissionを定義しない。
- ProductionDelegateの権限はRole / Permissionによって判定する。
- PrimaryManagerはAuthorization Domainのルールに従ってRehearsalを管理できる。
- Google Calendar EventはRehearsalそのものではない。
- RehearsalをStageArt側の正本とする。
- Google CalendarはExternal Artifactとして扱う。
- Calendar連携対象とRehearsalAttendanceを同一視しない。
- CANCELLEDとなったRehearsalは削除しない。
- COMPLETEDとなったRehearsalは履歴として保持する。
- RehearsalAttendanceも履歴として保持する。
- Rehearsalの重要な変更について監査情報を記録できる。
- Rehearsalの状態変更に応じてDomain Eventを発生させる。
- Blueprintを唯一の設計基準とする。

---

# Design Decisions

Rehearsalは、
稽古予定から実施完了までを管理する
単一のDomainである。

稽古予定と確定稽古を分離しない。

Rehearsal Candidateを廃止する。

Rehearsal Availabilityを廃止する。

日程調整の結果を、
RehearsalのStatusおよびScheduleとして管理する。

Rehearsalは、
DRAFT、
SCHEDULED、
CONFIRMED、
ACTIVE、
COMPLETED、
CANCELLED
のLifecycleを持つ。

RehearsalAttendanceは、
Rehearsalに属する子Entityとして管理する。

参加予定者と当日の出欠は、
同じRehearsalAttendanceを使用する。

RehearsalがACTIVEになっても、
RehearsalAttendanceを削除しない。

予定段階のAttendance Statusから、
実際の出欠Statusへ変更する。

RehearsalAttendanceは、
Rehearsalごとに参加予定者を保持する。

Production Participant全員が、
すべてのRehearsalに自動的に参加するわけではない。

TimetableはRehearsalとは別Domainとして管理する。

Google Calendar EventはExternal Artifactであり、
Rehearsalを正本とする。

Historyは必要に応じてRehearsalおよび
RehearsalAttendanceのFactから生成する。

---

# Design Principles

- RehearsalはProductionに所属する。
- Rehearsalは稽古予定から実施完了までを一つのEntityとして管理する。
- 稽古予定と確定稽古を別Domainに分けない。
- Rehearsal Candidateを使用しない。
- Rehearsal Availabilityを使用しない。
- RehearsalのLifecycleはStatusで管理する。
- RehearsalAttendanceはRehearsalの子Entityである。
- RehearsalAttendanceを独立Domainとして管理しない。
- 参加予定者はRehearsalAttendanceとして保持する。
- RehearsalがCONFIRMEDになっても参加予定者を削除しない。
- RehearsalがACTIVEになっても参加予定者を削除しない。
- 同じRehearsalAttendanceのStatusを予定状態から実績状態へ変更する。
- Rehearsalごとに参加予定者を管理する。
- Participant TypeとAttendance Statusを分離する。
- Participant TypeとAuthorizationを分離する。
- Role / PermissionはAuthorization Domainで管理する。
- ProductionDelegateはAuthorization Domainのルールによって権限を与えられる。
- TimetableはRehearsal内の詳細な進行を管理する。
- Google Calendar EventはRehearsalの正本ではない。
- RehearsalをStageArtにおける正本とする。
- CANCELLEDとなったRehearsalを削除しない。
- COMPLETEDとなったRehearsalを履歴として保持する。
- RehearsalAttendanceを履歴として保持する。
- Blueprintを唯一の設計基準とする。