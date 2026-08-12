# StageArt Blueprint

# Domain Model : Rehearsal

Version : 2.1

---

# Purpose

Rehearsalは、
Productionにおける確定した稽古・活動予定を管理するDomainである。

Rehearsalは、
実際に行われる予定そのものを表す。

Rehearsal Candidateが
「この日に稽古をする候補」
を表すのに対し、

Rehearsalは
「この日に稽古を行うことが確定した」
ことを表す。

---

# Concept

Rehearsalは、
Productionに所属する確定済みの稽古・活動予定である。

基本構造：

Production
  ↓
Rehearsal

Rehearsalは、
Rehearsal Candidateから生成することも、
Candidateを経由せず直接作成することもできる。

---

# Relationship

RehearsalはProductionに所属する。

Production
  ↓
Rehearsal

一つのProductionに、
複数のRehearsalを登録できる。

---

# Rehearsal Creation

Rehearsalには二つの作成方法がある。

## Candidateから作成

Rehearsal Candidateによる日程調整を行った場合：

Rehearsal Candidate
  ↓
Rehearsal Availability
  ↓
Candidate選択
  ↓
Rehearsal

Candidateの日時・場所などを
Rehearsal作成時の初期値として利用できる。

---

## Direct Creation

Rehearsal Candidateを経由せず、
直接Rehearsalを作成できる。

例えば、

- 本番日程
- 小屋入り
- ゲネプロ
- 追加稽古
- 特別稽古
- 打ち合わせ
- その他確定済み予定

など。

この場合、

Production
  ↓
Rehearsal

として直接作成する。

---

# Candidate Relationship

CandidateからRehearsalを生成した場合、
Rehearsalは生成元となったCandidateを参照できる。

この参照は、

「このRehearsalがどの候補日程から確定したか」

を確認するために利用する。

Candidateを経由しないRehearsalの場合、
生成元Candidateは存在しない。

---

# Identity

RehearsalはRehearsalIdによって一意に識別する。

RehearsalIdは変更しない。

日時や名称は識別子ではない。

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
- 開始時刻
- 終了予定時刻
- 解散予定時刻

などへ拡張できる。

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
Rehearsalの共有情報として利用できる。

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
Google Calendar連携時にも利用できる。

---

# Online Rehearsal

オンラインで実施するRehearsalにも対応できる。

例えば、

- Zoom
- Google Meet
- その他オンライン会議

など。

オンラインURLを設定できる構造を将来的に持つ。

---

# Status

Rehearsalは以下の状態を持つ。

- DRAFT
- CONFIRMED
- CANCELLED
- COMPLETED

---

# Draft

DRAFTは、
Rehearsalが作成されたが、
まだ確定していない状態。

稽古管理権限を持つPersonが
内容を編集できる。

---

# Confirmed

CONFIRMEDは、
Rehearsalが確定した状態。

確定後、
対象者へ参加確認を依頼できる。

また、
Google Calendar連携を実行できる。

---

# Cancelled

CANCELLEDは、
Rehearsalが中止された状態。

Rehearsal自体は削除しない。

既にGoogle Calendarへ連携されている場合、
Calendar Eventの更新・削除などを
External Integrationとして処理できる。

---

# Completed

COMPLETEDは、
Rehearsalが実施済みとなった状態。

Rehearsalは履歴として保持する。

---

# Attendance

CONFIRMEDとなったRehearsalに対して、
参加確認を行う。

基本構造：

Rehearsal
  ↓
Rehearsal Attendance
  ↓
Person

Rehearsal Attendanceは、
確定後の参加確認を表す。

Candidate段階の
Rehearsal Availabilityとは別のFactである。

---

# Availability and Attendance

Rehearsal Availability：

「候補日に参加できるか」

Rehearsal Attendance：

「確定したこのRehearsalに参加するか」

両者を自動的に同一視しない。

例えば、

Candidate

Person A
  Availability = UNAVAILABLE

↓

Rehearsal確定

↓

Person Aが予定を調整

↓

Rehearsal Attendance
  = ATTENDING

という状態を許可する。

---

# Attendance Timing

Rehearsal Attendanceは、
Rehearsal実施前に確認する。

当日に初めて確認するものではない。

確定したRehearsalについて、
事前に参加予定を確認する。

これにより、
稽古管理者は確定後の参加人数を
事前に把握できる。

---

# Attendance Update

Rehearsal Attendanceは、
Rehearsal実施前であれば変更できる。

例えば、

ATTENDING
  ↓
NOT_ATTENDING

や、

NOT_ATTENDING
  ↓
ATTENDING

など。

日程調整後に参加できるようになったPersonが、
AttendanceをATTENDINGへ変更できる。

---

# Attendance and Participant

Attendanceの対象者は、
ProductionのParticipantを基本とする。

例えば、

- キャスト
- スタッフ

など。

Participant Typeを利用して、
対象者を絞り込むことができる。

---

# Attendance and Authorization

Participant Typeは、
Attendanceを回答するための権限を与えるものではない。

Rehearsalに参加するPerson自身が
自分のAttendanceを回答する。

稽古管理権限を持つPersonは、
必要に応じてAttendanceを確認・管理できる。

---

# Role and Permission

Rehearsalに関する操作権限は、
Role / Permissionによって管理する。

Rehearsal Domain自身は、
RoleやPermissionの定義を管理しない。

Roleは、
Rehearsalに対して何を操作できるかを表す。

Permissionは、
具体的な操作権限を表す。

例えば、

Role
  ↓
Permission
  ↓
Rehearsal Management

という関係で、
稽古管理に必要な権限を付与する。

---

# Rehearsal Management

稽古管理権限を持つPersonは、
必要に応じて以下を行える。

- Rehearsal作成
- Rehearsal変更
- Rehearsal確定
- Rehearsalキャンセル
- Attendance確認
- Attendance管理
- Timetable管理
- Rehearsal情報共有

具体的な権限の組み合わせは、
Role / Permission Domainで定義する。

---

# Organization Administrator

Organizationの管理者は、
自身のOrganizationについて
全権限を持つ。

そのため、
自身のOrganizationに所属するProductionの
Rehearsalについても、
必要な管理操作を行える。

Organization Administratorという権限概念を
Rehearsal Domain内で独自に定義しない。

OrganizationのRole / Permissionによって
権限を判定する。

---

# Production Delegate

Production単位で、
稽古管理権限を委任できる。

委任されたPersonは、
Productionに対して必要な
Rehearsal Management Permissionを持つ。

Production Delegateという呼称は、
権限を付与されたPersonを説明するために使用できる。

Production Delegate自体を
Rehearsal Domainの独立Entityとして管理しない。

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

---

# Calendar Registration Target

Google Calendarへの登録は、
Rehearsalの参加者だけに限定しない。

Rehearsalの共有対象となるPersonが、
Google Calendar連携対象となる。

つまり、

Rehearsal Attendance
  = NOT_ATTENDING

であるPersonであっても、
Google Calendarへ予定を登録できる。

Calendar登録対象と
稽古参加予定は別の概念として扱う。

---

# Calendar Integration Timing

Google Calendar連携は、
Rehearsalが確定した後に行う。

基本Flow：

Rehearsal Candidate
  ↓
Availability
  ↓
Rehearsal確定
  ↓
Google Calendar連携
  ↓
Attendance確認
  ↓
稽古実施

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

Google Calendar側のEvent IDなどを
連携情報として保持できる。

---

# Calendar Update

Rehearsalの日時・場所・内容などが変更された場合、
連携済みGoogle Calendar Eventを
更新できる。

Google Calendar側の変更を、
StageArtのRehearsalへ自動反映することを
Version 2.1で必須とはしない。

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
Timetable Item

Rehearsalは稽古そのものの予定を管理し、
TimetableはそのRehearsal内の詳細な進行を管理する。

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

詳細な時間割はTimetableで管理する。

---

# Production Relationship

RehearsalはProductionに所属する。

Projectに直接所属するものとして管理しない。

基本構造：

Organization
  ↓
Project
  ↓
Production
  ↓
Rehearsal

---

# Participant Relationship

RehearsalはParticipantを直接所有しない。

参加対象者は、
ProductionのParticipantから決定する。

基本構造：

Production
  ↓
Participant

Rehearsal
  ↓
Rehearsal Attendance
  ↓
Person

---

# Information Sharing

Rehearsalに関する情報は、
対象者へ共有できる。

対象者は、

- Participant Type
- 個別Person
- その他定義された共有条件

などから指定できる。

---

# Notification

Rehearsal確定後、
対象者へ通知できる。

通知内容には、

- Rehearsal日時
- 場所
- 内容
- Attendance回答
- Google Calendar連携

などを含めることができる。

通知機能そのものはNotification Domainの責務とする。

---

# Audit

Rehearsalには、
変更を追跡できるよう監査情報を保持する。

基本情報：

- Created By
- Created At
- Updated By
- Updated At

---

# History

Rehearsalは、
実際に行われた稽古というFactを表す。

Rehearsal自体は履歴として保持する。

Personの稽古参加履歴などを生成する場合は、
Rehearsal AttendanceなどのFactを利用する。

Attendanceの存在だけで、
必ずHistoryを生成するとは限らない。

History Domainのルールに従う。

---

# Business Rules

- RehearsalはProductionに所属する。
- Rehearsalは確定した稽古・活動予定を表す。
- RehearsalはRehearsal Candidateから生成できる。
- RehearsalはCandidateを経由せず直接作成できる。
- Candidateから生成した場合、生成元Candidateを参照できる。
- RehearsalIdは変更しない。
- Rehearsalは予定日時を保持する。
- RehearsalはTitleを保持できる。
- RehearsalはDescriptionを保持できる。
- RehearsalはLocationを保持できる。
- RehearsalはDRAFT / CONFIRMED / CANCELLED / COMPLETEDを持つ。
- CANCELLEDのRehearsalを物理削除しない。
- CONFIRMEDのRehearsalについてAttendanceを確認できる。
- Rehearsal AvailabilityとRehearsal Attendanceを別のFactとして扱う。
- Candidate段階のAvailabilityを確定後のAttendanceと自動的に同一視しない。
- AttendanceはRehearsal実施前に確認する。
- Attendanceは実施前であれば変更できる。
- Attendanceの対象者はProductionのParticipantを基本とする。
- Participant TypeをAttendance対象者の絞り込みに利用できる。
- Participant Typeは権限を付与しない。
- Rehearsalに参加するPerson自身が自分のAttendanceを回答できる。
- 稽古管理権限を持つPersonはAttendanceを確認・管理できる。
- Rehearsalの操作権限はRole / Permissionによって管理する。
- Rehearsal DomainはRole / Permissionの定義を管理しない。
- Organization Administratorは自身のOrganizationについて全権限を持つ。
- Production DelegateはRehearsal Domainの独立Entityではない。
- Production単位で稽古管理権限を委任できる。
- CONFIRMEDのRehearsalはGoogle Calendarへ連携できる。
- Google Calendar EventはRehearsalの正本ではない。
- Rehearsalが正本であり、Google Calendar EventはExternal Artifactである。
- Google Calendar側の変更をRehearsalへ自動反映することを必須としない。
- CANCELLED時のCalendar処理はExternal Integrationの責務とする。
- Rehearsalの詳細な時間割はTimetable Domainで管理する。
- Timetableの基本的な親はRehearsalである。
- RehearsalはParticipantを直接所有しない。
- Rehearsalの参加対象者はProductionのParticipantから決定する。
- Rehearsal情報の共有対象とAttendance対象者は同一である必要はない。
- 通知処理はNotification Domainの責務とする。
- Rehearsalには監査情報を保持する。
- Rehearsalは履歴として保持する。

---

# Domain Events

Rehearsalに関する主なDomain Event：

- RehearsalCreated
- RehearsalUpdated
- RehearsalConfirmed
- RehearsalCancelled
- RehearsalCompleted
- RehearsalAttendanceUpdated

これらのEventを契機として、
必要に応じて、

- Notification
- External Calendar Integration
- History

などの関連Domainが処理を行う。

Rehearsal Domain自身が、
NotificationやGoogle Calendarの処理を直接管理することはない。

---

# Design Decisions

Rehearsalは、
Productionにおける確定した稽古・活動予定を管理する。

Rehearsal Candidateは候補日程を表し、
Rehearsalは確定した予定を表す。

CandidateからRehearsalを生成することも、
Candidateを経由せず直接Rehearsalを作成することもできる。

RehearsalはProductionに所属する。

Rehearsalは、
稽古予定の基本情報を管理する。

Timetableは、
Rehearsal内の詳細な時間割・進行を管理する。

基本構造：

Production
  ↓
Rehearsal
  ↓
Timetable
  ↓
Timetable Item

Participant Typeは、
Attendance対象者や情報共有対象者を
指定するために利用できる。

Participant Typeは、
権限を付与するものではない。

Rehearsalに関する操作権限は、
Role / Permissionによって管理する。

稽古管理権限を持つPersonを
Rehearsal Managerと呼ぶことはできるが、
Rehearsal Managerを独立したEntityとして管理しない。

Organization Administratorは、
Role / Permissionによって
自身のOrganizationについて全権限を持つ。

Production単位で、
稽古管理権限を委任できる。

Google CalendarはExternal Artifactであり、
Rehearsalを正本とする。

Rehearsalの日時・場所・内容などを
必要に応じてGoogle Calendarへ反映する。

---

# Future

将来的に以下へ対応できる。

- Attendance Reminder
- 自動リマインド
- Attendance集計
- 出欠率集計
- Rehearsal Template
- Rehearsal Copy
- Online Meeting URL
- Google Calendar双方向連携
- その他Calendar Service連携
- 稽古実績集計
- Person別稽古履歴
- 本番進行専用Activity
- 小屋入り専用Activity
- 場当たり管理
- ゲネプロ管理

ただし、
将来機能を追加する場合も、

Production
  ↓
Rehearsal
  ↓
Timetable

という基本構造を維持する。

権限については、
Rehearsal Domainに独自のRole体系を作らず、
Role / Permission Domainを正本とする。

---

# Design Principles

- RehearsalはProductionに所属する。
- Rehearsalは確定した稽古・活動予定を表す。
- Rehearsal CandidateとRehearsalを分離する。
- CandidateからRehearsalを生成できる。
- Candidateを経由せずRehearsalを直接作成できる。
- Rehearsal AvailabilityとRehearsal Attendanceを分離する。
- Attendanceは確定したRehearsalへの参加予定を表す。
- Participant Typeは対象者指定に利用する。
- Participant Typeは権限を付与しない。
- Roleは操作権限を管理する。
- Permissionは具体的な操作権限を表す。
- Rehearsal DomainはRole / Permissionを定義しない。
- Organization Administratorは自身のOrganizationについて全権限を持つ。
- Production単位で稽古管理権限を委任できる。
- Rehearsal Managerを独立Entityとして管理しない。
- RehearsalはParticipantを直接所有しない。
- AttendanceはRehearsal Attendanceとして管理する。
- TimetableはRehearsalの詳細な進行を管理する。
- Timetableの基本的な親はRehearsalである。
- Google Calendar EventはRehearsalの正本ではない。
- Rehearsalを予定情報の正本とする。
- NotificationはNotification Domainの責務とする。
- External Calendar IntegrationはRehearsal Domainの責務と分離する。
- Rehearsalは履歴として保持する。
- Blueprintを唯一の設計基準とする。