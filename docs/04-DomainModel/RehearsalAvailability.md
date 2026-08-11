# StageArt Blueprint

# Domain Model : Rehearsal Availability

Version : 1.0

---

# Purpose

Rehearsal Availabilityは、
Rehearsal Candidateに対するPersonの
日程調整用の回答を管理するDomainである。

Rehearsal Availabilityは、

「この候補日に参加できるか」

を表す。

確定したRehearsalへの参加確認ではない。

---

# Concept

Rehearsal Candidateが提示されると、
対象となるPersonは候補日に対して
Availabilityを回答できる。

基本構造：

Rehearsal Candidate
  ↓
Rehearsal Availability
  ↓
Person

---

# Relationship

Rehearsal Availabilityは、
一つのRehearsal Candidateに所属する。

Rehearsal Candidate
  ↓
Rehearsal Availability

Rehearsal Availabilityは、
一人のPersonによる一つの候補日への回答を表す。

---

# Subject

Availabilityの回答者はPersonである。

Personは、
ProductionのParticipantとして
Rehearsal Candidateの対象者となる。

基本的な対象者：

- キャスト
- スタッフ

---

# Availability Status

Availabilityには、
候補日に対する回答状態を設定する。

基本的な状態：

- AVAILABLE
- CONDITIONAL
- UNAVAILABLE

日本語UIでは、

- ○ 参加可能
- △ 条件付き
- × 参加不可

などとして表示できる。

---

# Available

AVAILABLEは、
その候補日に参加可能であることを表す。

---

# Conditional

CONDITIONALは、
条件付きで参加可能であることを表す。

例えば、

- 20時以降なら参加可能
- 21時までなら参加可能
- 少し遅れて参加可能

など。

詳細な条件は、
Availability Noteとして保持できる。

---

# Unavailable

UNAVAILABLEは、
その候補日に参加できないことを表す。

---

# Availability Note

Personは、
Availabilityに補足情報を入力できる。

例：

- 19時から参加可能
- 30分遅れる
- 22時までなら可能

Availability Noteは、
日程調整のための情報として利用する。

確定したRehearsalのAttendance Noteとは別に管理する。

---

# Candidate Scope

Availabilityは、
OPEN状態のRehearsal Candidateに対して
回答できる。

CLOSED、
SELECTED、
CANCELLEDとなったCandidateについては、
新しい回答を受け付けない。

---

# Update

Personは、
Availabilityの回答を変更できる。

例えば、

AVAILABLE
  ↓
UNAVAILABLE

や、

UNAVAILABLE
  ↓
AVAILABLE

などの変更を許可する。

CandidateがOPENである間は、
回答を変更できる。

---

# Candidate Selection

CandidateがSELECTEDとなった場合、
そのCandidateに対するAvailabilityは
日程調整用の回答として確定する。

Availability自体を
Rehearsal Attendanceへ変換しない。

確定後の参加確認は、
新しくRehearsal Attendanceを作成して行う。

---

# Availability and Attendance

Rehearsal Availabilityと
Rehearsal Attendanceは、
完全に別のFactである。

Availability：

「候補日に参加できるか」

Attendance：

「確定した稽古に参加するか」

例えば、

Candidate A

Person A
  Availability = UNAVAILABLE

↓

Candidate AがRehearsalとして確定

↓

Person Aが予定を調整

↓

Rehearsal Attendance
  = ATTENDING

という状態を許可する。

逆に、

Availability
  = AVAILABLE

であっても、

Rehearsal Attendance
  = NOT_ATTENDING

となることを許可する。

---

# Availability Collection

Rehearsal CandidateがOPENになると、
対象PersonからAvailabilityを収集できる。

基本Flow：

Candidate作成
  ↓
対象者へ通知
  ↓
Availability回答
  ↓
回答集計
  ↓
Candidate選択

---

# Target Participants

Availabilityの対象者は、
ProductionのParticipantから決定できる。

例えば、

Participant Type
  = キャスト

を指定すると、
キャストを対象にAvailabilityを収集できる。

スタッフを含める場合は、
スタッフも対象とする。

---

# Individual Target

Participant Typeだけではなく、
個別のPersonを対象に指定できる。

例えば、

- 主演
- 演出
- 音響担当
- 制作担当

など、
特定PersonのAvailabilityが重要な場合に利用できる。

---

# Target Snapshot

Candidate作成時点の対象者情報を、
必要に応じてSnapshotとして保持できる。

これにより、
Candidate作成後にParticipantが追加・変更されても、
すでに依頼したAvailabilityの対象範囲を
意図せず変更しないようにできる。

ただし、
Snapshotの具体的な実装方法は
Data Modelで定義する。

---

# Availability Count

Candidateでは、
Availabilityを集計して表示できる。

例：

- AVAILABLE 8人
- CONDITIONAL 2人
- UNAVAILABLE 3人
- 未回答 1人

この集計結果は、
Candidateの選択判断を支援するために利用する。

集計値をAvailabilityの正本として保存しない。

Availabilityを正本とする。

---

# No Response

PersonがAvailabilityを回答していない状態を、
別のAvailability Statusとして登録する必要はない。

Availabilityそのものが存在しない場合を
未回答として扱う。

これにより、

AVAILABLE
CONDITIONAL
UNAVAILABLE

と、

未回答

を区別できる。

---

# Deadline

Availabilityには、
回答期限を設定できる。

回答期限を過ぎた場合、
新しい回答や変更を受け付けない構造へ
拡張できる。

Version 1.0で回答期限を実装する場合は、
Candidateの回答期限として管理する。

Availability自身に
独立した期限を持たせない。

---

# Notification

Availability回答を依頼する場合、
対象Personへ通知できる。

通知内容には、

- Production
- Rehearsal Candidate
- 候補日時
- 回答期限
- 回答画面への導線

などを含めることができる。

通知機能そのものは、
Notification Domainの責務とする。

---

# Audit

Availabilityには、
変更を追跡できるよう監査情報を保持する。

基本情報：

- Created By
- Created At
- Updated By
- Updated At

---

# History

Availabilityは、
実際に稽古へ参加したというFactではない。

そのため、
AvailabilityからHistoryを生成しない。

Historyの生成元となるのは、
確定したRehearsalへの参加など、
実際の活動を表すFactである。

---

# Authorization

Availabilityの回答は、
対象Person本人が行える。

管理権限を持つPersonは、
必要に応じて対象者の回答を確認できる。

Availabilityの管理・削除などの権限は、
Rehearsal管理権限に従う。

---

# Deletion

Availabilityは、
候補日程の調整履歴として意味を持つ。

そのため、
物理削除を基本としない。

誤って作成した回答などについては、
状態変更や監査情報によって履歴を保持する。

---

# Business Rules

- Rehearsal AvailabilityはRehearsal Candidateに所属する。
- Rehearsal AvailabilityはPersonの候補日への回答を表す。
- Availabilityは日程調整用のFactである。
- Availabilityは確定後のAttendanceを表さない。
- Availability StatusはAVAILABLE / CONDITIONAL / UNAVAILABLEを基本とする。
- 未回答はAvailabilityが存在しない状態として扱う。
- Availability Noteで条件を補足できる。
- OPEN状態のCandidateに対して回答できる。
- CandidateがCLOSED / SELECTED / CANCELLEDとなった後は新規回答を受け付けない。
- OPEN状態では回答を変更できる。
- CandidateがSELECTEDとなってもAvailabilityをAttendanceへ変換しない。
- 確定後の参加確認はRehearsal Attendanceで行う。
- CandidateでUNAVAILABLEだったPersonも、確定後にAttendanceで参加可能に変更できる。
- CandidateでAVAILABLEだったPersonも、確定後にAttendanceで不参加に変更できる。
- Availability対象者はProduction Participantを基本とする。
- Participant Typeを対象者の抽出条件として利用できる。
- 個別Personを対象者として指定できる。
- Availabilityの集計結果はCandidateの選択判断に利用できる。
- 集計値はAvailabilityの正本ではない。
- 回答期限はCandidate側で管理する。
- AvailabilityはHistoryを生成しない。
- Availabilityには監査情報を保持する。
- Availabilityは原則として物理削除しない。

---

# Domain Events

Rehearsal Availabilityに関する主なDomain Event：

- RehearsalAvailabilityCreated
- RehearsalAvailabilityUpdated

Candidateの状態変更については、
Rehearsal Candidate Domainで管理する。

---

# Design Decisions

Rehearsal Availabilityは、
日程調整のためだけに存在するDomainとする。

確定後の参加確認である
Rehearsal Attendanceとは完全に分離する。

この分離により、

「候補日に参加できなかったが、
確定後に予定を調整して参加できる」

という現実の稽古運用を表現できる。

Availabilityは、
実際の稽古参加を表すFactではない。

そのため、
Availabilityから出演履歴や稽古参加履歴を生成しない。

---

# Future

将来的に以下へ対応できる。

- 回答期限
- 自動リマインド
- 条件付き回答の詳細化
- 時間帯単位の回答
- Google Calendar予定との照合
- 空き時間からの候補日提案
- 最適候補日の自動提案

ただし、
Google Calendarとの実際の連携は
Rehearsal確定後のCalendar Integrationで行う。

---

# Design Principles

- Availabilityは候補日に対する回答である。
- AvailabilityはRehearsal Candidateに所属する。
- AvailabilityとAttendanceを分離する。
- Availabilityは日程調整のFactである。
- Attendanceは確定したRehearsalへの参加確認である。
- Availabilityの回答によってAttendanceを自動確定しない。
- Candidateでの回答と確定後のAttendanceは独立して変更できる。
- Participant Typeを対象者抽出に利用できる。
- 個別Personを対象者に指定できる。
- 未回答はAvailabilityが存在しない状態で表現する。
- Availabilityは実際の稽古参加を表さない。
- AvailabilityからHistoryを生成しない。
- Availabilityの集計結果を正本としない。
- Blueprintを唯一の設計基準とする。
