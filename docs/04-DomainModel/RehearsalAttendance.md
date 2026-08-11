# StageArt Blueprint

# Domain Model : Rehearsal Attendance

Version : 1.0

---

# Purpose

Rehearsal Attendanceは、
確定したRehearsalに対するPersonの
参加予定を管理するDomainである。

Rehearsal Attendanceは、

「確定したこの稽古に参加するか」

を表す。

Rehearsal Candidateに対する日程調整用の回答である
Rehearsal Availabilityとは異なる。

---

# Concept

Rehearsalが確定した後、
対象Personに対して参加確認を行う。

基本構造：

Rehearsal
  ↓
Rehearsal Attendance
  ↓
Person

Rehearsal Attendanceは、
稽古実施前の参加予定を表す。

---

# Relationship

Rehearsal Attendanceは、
一つのRehearsalに所属する。

Rehearsal
  ↓
Rehearsal Attendance

一つのRehearsalには、
複数のPersonのAttendanceを登録できる。

---

# Subject

Attendanceの対象者はPersonである。

対象者はProductionのParticipantを基本とする。

例えば、

- キャスト
- スタッフ

など。

---

# Participant Relationship

Attendance対象者は、
Production Participantから決定できる。

基本構造：

Production
  ↓
Participant
  ↓
Person

Rehearsal
  ↓
Rehearsal Attendance
  ↓
Person

Participant Typeを利用して、
Attendance対象者を絞り込むことができる。

---

# Attendance Status

Attendanceには、
確定したRehearsalへの参加予定を設定する。

基本的な状態：

- ATTENDING
- NOT_ATTENDING
- UNDECIDED

日本語UIでは、

- ○ 参加
- × 不参加
- △ 未定

などとして表示できる。

---

# Attending

ATTENDINGは、
確定したRehearsalへ参加する予定であることを表す。

---

# Not Attending

NOT_ATTENDINGは、
確定したRehearsalへ参加しない予定であることを表す。

---

# Undecided

UNDECIDEDは、
確定したRehearsalへの参加予定がまだ決まっていない状態を表す。

必要に応じて、
管理者が未回答者として確認できる。

---

# Availability and Attendance

Rehearsal Availabilityと
Rehearsal Attendanceは完全に別のFactである。

Availability：

「候補日に参加できるか」

Attendance：

「確定したこのRehearsalに参加するか」

例えば、

Rehearsal Candidate

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

逆に、

Person B
  Availability = AVAILABLE

であっても、

Rehearsal Attendance
  = NOT_ATTENDING

となることを許可する。

---

# Timing

Rehearsal Attendanceは、
稽古実施前に確認する。

当日に初めて参加確認を行うことを
基本としない。

Rehearsal確定後、
対象者へ事前に参加確認を依頼する。

---

# Attendance Update

Rehearsal実施前であれば、
PersonはAttendanceを変更できる。

例えば、

ATTENDING
  ↓
NOT_ATTENDING

や、

NOT_ATTENDING
  ↓
ATTENDING

など。

予定変更に対応するため、
確定後も参加予定を更新できる。

---

# Response Deadline

Attendanceには、
回答期限を設定できる構造を持つ。

ただし、
回答期限そのものはRehearsal側で管理することを基本とする。

期限を過ぎた場合でも、
管理権限を持つPersonによる変更を
許可できる。

---

# Initial Attendance

RehearsalがCONFIRMEDとなった場合、
対象者にAttendance回答を依頼できる。

初期状態は、

UNDECIDED

とすることを基本とする。

ただし、
Rehearsal確定時に対象者の参加予定が
明らかな場合は、
管理者が初期値を設定できる。

---

# Target Participants

Attendanceの対象者は、
Production Participantを基本とする。

例えば、

Participant Type
  = キャスト

とすることで、
キャストを対象にAttendanceを確認できる。

スタッフを含める場合は、
スタッフも対象とする。

---

# Individual Target

Participant Typeだけではなく、
個別Personを対象に指定できる。

例えば、

- 主演
- 演出
- 音響担当
- 制作担当

など、
特定Personだけを対象にすることができる。

---

# Target Snapshot

Rehearsal確定時点の対象者情報を、
必要に応じてSnapshotとして保持できる。

これにより、
Rehearsal確定後にParticipantが追加・変更されても、
すでに設定されたAttendance対象者が
意図せず変更されることを防止できる。

具体的なSnapshot実装方法は、
Data Modelで定義する。

---

# Attendance Count

Rehearsalでは、
Attendanceを集計して表示できる。

例：

- ATTENDING 8人
- NOT_ATTENDING 2人
- UNDECIDED 3人

この集計結果は、
稽古管理者が参加予定人数を把握するために利用する。

集計値をAttendanceの正本として保存しない。

Attendanceを正本とする。

---

# Notification

Attendance回答を依頼する場合、
対象Personへ通知できる。

通知内容には、

- Production
- Rehearsal
- 日時
- 場所
- 稽古内容
- 回答期限
- Attendance回答画面への導線

などを含めることができる。

通知機能そのものはNotification Domainの責務とする。

---

# Google Calendar Relationship

Google Calendarへの登録と
Rehearsal Attendanceは別の概念である。

AttendanceがATTENDINGであることだけを理由に、
Calendarへの登録対象を限定しない。

例えば、

Attendance
  = NOT_ATTENDING

であっても、

Google Calendar
  = 登録

という状態を許可する。

逆に、

Attendance
  = ATTENDING

であっても、
Google Calendarを利用しないPersonが存在してよい。

---

# Calendar Registration

Google Calendar連携は、
確定したRehearsalを対象とする。

基本Flow：

Rehearsal
  ↓
Google Calendar Integration
  ↓
Google Calendar Event

Calendar Eventの登録対象は、
Rehearsalの共有対象者など、
別途定義された条件によって決定する。

Attendance自身が
Google Calendar登録を実行することはない。

---

# Attendance and Actual Participation

Rehearsal Attendanceは、
稽古実施前の参加予定を表す。

実際に稽古へ参加したという事実を
Attendanceだけで確定しない。

例えば、

Attendance
  = ATTENDING

であっても、
実際には欠席した可能性がある。

実際の参加実績を将来管理する場合は、
別のAttendance Resultまたは
Rehearsal実績Domainとして拡張する。

---

# History

Rehearsal Attendanceは、
稽古への参加予定を表すFactである。

そのため、
ATTENDINGとなった時点で
稽古参加履歴を確定させない。

実際の参加履歴が必要となった場合は、
稽古実施後のFactを利用する。

History生成の詳細はHistory Domainで定義する。

---

# Authorization

Attendanceの回答は、
対象Person本人が行える。

Rehearsal管理権限を持つPersonは、
必要に応じてAttendanceを確認・管理できる。

Participant Typeは、
権限を付与するものではない。

---

# Audit

Attendanceには、
変更を追跡できるよう監査情報を保持する。

基本情報：

- Created By
- Created At
- Updated By
- Updated At

---

# Deletion

Attendanceは、
稽古への参加予定という履歴情報を持つ。

そのため、
物理削除を基本としない。

対象者が変更された場合も、
必要に応じて状態変更や監査情報によって履歴を保持する。

---

# Business Rules

- Rehearsal AttendanceはRehearsalに所属する。
- Rehearsal AttendanceはPersonの確定稽古への参加予定を表す。
- AttendanceはRehearsal Availabilityとは別のFactである。
- Availabilityは候補日への回答である。
- Attendanceは確定したRehearsalへの参加確認である。
- CandidateでUNAVAILABLEだったPersonも、確定後にATTENDINGへ変更できる。
- CandidateでAVAILABLEだったPersonも、確定後にNOT_ATTENDINGへ変更できる。
- Attendanceは稽古実施前に確認する。
- Attendance StatusはATTENDING / NOT_ATTENDING / UNDECIDEDを基本とする。
- Rehearsal確定時の初期状態はUNDECIDEDを基本とする。
- Rehearsal実施前であればAttendanceを変更できる。
- Attendance対象者はProduction Participantを基本とする。
- Participant Typeを対象者抽出条件として利用できる。
- 個別Personを対象者として指定できる。
- Attendanceの集計値を正本としない。
- Attendanceを正本として参加予定人数を算出する。
- Google Calendar登録とAttendanceは独立した概念である。
- AttendanceがATTENDINGであることだけを理由にCalendar登録対象を限定しない。
- AttendanceがNOT_ATTENDINGでもCalendarへ登録できる。
- Attendanceだけでは実際の稽古参加を確定しない。
- Attendanceから即座にHistoryを生成しない。
- Attendanceには監査情報を保持する。
- Attendanceは原則として物理削除しない。
- Participant Typeは権限を付与しない。
- Attendanceの管理権限はRehearsal管理権限に従う。

---

# Domain Events

Rehearsal Attendanceに関する主なDomain Event：

- RehearsalAttendanceCreated
- RehearsalAttendanceUpdated

Attendance Status変更時には、
必要に応じてStatus変更Eventを発行できる。

---

# Design Decisions

Rehearsal Attendanceは、
確定したRehearsalへの参加予定を表す。

Candidate段階のAvailabilityとは完全に分離する。

この分離によって、

「候補日では参加できなかったが、
確定後に予定を調整して参加できるようになった」

というケースを自然に表現できる。

また、

「候補日では参加可能だったが、
確定後に参加できなくなった」

というケースも表現できる。

Attendanceは実施前の予定であり、
実際の参加実績とは区別する。

Google Calendar連携もAttendanceとは分離する。

これにより、
Calendar登録と実際の稽古参加予定を
独立して管理できる。

---

# Future

将来的に以下へ対応できる。

- 回答期限
- 自動リマインド
- 参加予定人数の自動集計
- Attendance変更履歴
- 実参加結果
- 遅刻
- 早退
- 実参加時間
- 稽古実績
- Attendanceからの稽古実績分析

ただし、
実際の参加実績を追加する場合も、
事前のAttendanceと混同しない。

---

# Design Principles

- Attendanceは確定したRehearsalへの参加予定である。
- AttendanceはRehearsalに所属する。
- AvailabilityとAttendanceを分離する。
- CandidateでのAvailabilityと確定後のAttendanceは独立して変更できる。
- Attendanceは稽古実施前に確認する。
- Attendanceは実際の参加実績ではない。
- Participant Typeを対象者抽出に利用できる。
- Participant Typeは権限を表さない。
- Google Calendar登録とAttendanceを分離する。
- AttendanceがATTENDINGでもCalendar登録を必須としない。
- AttendanceがNOT_ATTENDINGでもCalendar登録を妨げない。
- Attendanceから実績Historyを直接生成しない。
- Attendanceの集計値を正本としない。
- Attendanceを正本として参加予定を判断する。
- Blueprintを唯一の設計基準とする。
