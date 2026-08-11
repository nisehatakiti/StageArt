# StageArt Blueprint

# Domain Model : Rehearsal Candidate

Version : 1.0

---

# Purpose

Rehearsal Candidateは、
Productionにおける稽古候補日を管理するDomainである。

Rehearsal Candidateは、
稽古日程を確定するための日程調整を行うために使用する。

Rehearsal Candidate自体は、
確定した稽古を表さない。

---

# Concept

稽古日程を調整する場合、

Rehearsal Candidate
  ↓
Rehearsal Availability
  ↓
Rehearsal

というFlowを基本とする。

Rehearsal Candidateは、
「この日に稽古を行う候補である」
という状態を表す。

---

# Relationship

Rehearsal CandidateはProductionに所属する。

Production
  ↓
Rehearsal Candidate

一つのProductionに、
複数のRehearsal Candidateを作成できる。

---

# Candidate Date

Rehearsal Candidateは、
候補となる日時を保持する。

基本的には、

- 日付
- 開始予定時刻
- 終了予定時刻
- タイムゾーン

を管理する。

---

# Candidate Location

候補日時に加えて、
稽古場所の候補情報を設定できる。

例：

- 稽古場A
- 稽古場B
- オンライン
- 未定

場所が未確定の場合でも、
Rehearsal Candidateを作成できる。

---

# Availability

Personは、
Rehearsal Candidateに対して
参加可能状況を回答できる。

回答はRehearsal Availabilityとして管理する。

基本構造：

Rehearsal Candidate
  ↓
Rehearsal Availability
  ↓
Person

---

# Candidate Availability

Rehearsal Availabilityは、
日程調整のための回答である。

例えば、

- AVAILABLE
- CONDITIONAL
- UNAVAILABLE

などを利用する。

日本語UIでは、

- ○ 参加可能
- △ 条件付き
- × 参加不可

などとして表示できる。

---

# Availability and Attendance

Rehearsal Availabilityと
Rehearsal Attendanceは別のFactである。

Rehearsal Availability：

「この候補日に参加できるか」

Rehearsal Attendance：

「確定したこの稽古に参加するか」

Candidateで×だったPersonが、
Rehearsal確定後に予定を調整して
Attendanceを○にすることを許可する。

逆に、

Candidateで○だったPersonが、
確定後に都合が悪くなり、
Attendanceを×に変更することも許可する。

---

# Candidate Selection

日程調整の結果をもとに、
管理者または稽古管理権限を持つPersonが
候補日の中から稽古日を確定する。

基本Flow：

複数候補日
  ↓
Availability集計
  ↓
候補日選択
  ↓
Rehearsal生成
  ↓
Candidate確定

---

# Rehearsal Generation

Rehearsal Candidateから
Rehearsalを生成できる。

Rehearsal生成時には、
Candidateの情報を初期値として利用する。

例：

Rehearsal Candidate
  日付 = 8/20
  開始 = 19:00
  終了 = 22:00

↓

Rehearsal
  日付 = 8/20
  開始 = 19:00
  終了 = 22:00

ただし、
Rehearsal確定時に内容を変更することを許可する。

---

# Direct Rehearsal

Rehearsalは、
Rehearsal Candidateを経由せずに
直接作成することもできる。

例えば、

- 本番日程
- 小屋入り
- ゲネプロ
- 追加稽古
- 本番後の打ち合わせ
- その他確定済み予定

など。

その場合、

Production
  ↓
Rehearsal

として直接作成する。

---

# Candidate Status

Rehearsal Candidateは以下の状態を持つ。

- OPEN
- CLOSED
- SELECTED
- CANCELLED

---

# Open

OPENは、
日程調整中の候補日を表す。

Personは、
OPEN状態のCandidateに対して
Availabilityを回答できる。

---

# Closed

CLOSEDは、
新しいAvailability回答を受け付けない状態。

候補日の調整を一旦締め切った状態を表す。

---

# Selected

SELECTEDは、
このCandidateがRehearsal生成対象として
選択された状態。

CandidateからRehearsalが生成された場合、
CandidateはSELECTEDとなる。

---

# Cancelled

CANCELLEDは、
候補日として使用しなくなった状態。

Candidateを物理削除するのではなく、
キャンセルFactとして保持する。

---

# Multiple Candidates

一つのProductionに、
複数のRehearsal Candidateを作成できる。

例えば、

8/20
8/21
8/22
8/23

を同時に候補として提示できる。

Personは、
それぞれのCandidateに対して
Availabilityを回答する。

---

# Availability Target

Rehearsal CandidateのAvailability対象者は、
ProductionのParticipantを基本とする。

例えば、

- キャスト
- スタッフ

などを対象にできる。

必要に応じて、
特定Personのみを対象とすることもできる。

---

# Participant Type

Availability対象者を決定する際には、
Participant Typeを利用できる。

例えば、

対象：

Participant Type = キャスト

とすることで、
Productionのキャストを対象に
日程調整を行うことができる。

スタッフを含める場合は、
スタッフも対象とする。

---

# Organization Role

稽古日程の管理権限は、
Organization RoleまたはProduction単位の権限によって判定する。

Participant Typeは、
管理権限を与えるものではない。

例えば、

Participant Type = キャスト

だからといって、
Rehearsal Candidateを作成・確定できるとは限らない。

---

# Rehearsal Management Permission

Rehearsal Candidateの作成・変更・確定は、
稽古管理権限を持つPersonが行う。

Organizationの管理者は、
自身のOrganizationについて
全権限を持つ。

Production Delegateとして
稽古管理権限を委任することもできる。

---

# Notification

Rehearsal Candidateを作成した場合、
対象者へ日程調整を依頼できる。

通知方法は、

- StageArt内部通知
- メール
- その他External Notification

などへ拡張できる。

Version 1.0での具体的な通知手段は、
Notification Domainで定義する。

---

# Google Calendar

Rehearsal Candidateそのものを、
Google Calendarへ登録することを必須としない。

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

---

# Candidate and Calendar

Candidateは、
日程調整のための内部Factである。

Candidate段階では、
Google Calendar Eventを生成しないことを基本とする。

確定したRehearsalを、
Google Calendarへ連携する。

---

# Audit

Rehearsal Candidateには、
変更履歴を確認できるよう
監査情報を保持する。

基本情報：

- Created By
- Created At
- Updated By
- Updated At

---

# Business Rules

- Rehearsal CandidateはProductionに所属する。
- Rehearsal Candidateは稽古候補日を表す。
- Rehearsal Candidateは確定したRehearsalを表さない。
- 一つのProductionに複数のCandidateを作成できる。
- Candidateに対してPersonがAvailabilityを回答できる。
- Availabilityは日程調整のためのFactである。
- AvailabilityとAttendanceは別のFactである。
- Candidateで×だったPersonも、確定後にAttendanceを○にできる。
- Candidateで○だったPersonも、確定後にAttendanceを×にできる。
- Availabilityの集計結果をもとにCandidateを選択できる。
- CandidateからRehearsalを生成できる。
- RehearsalはCandidateを経由せず直接作成できる。
- 本番日程などもRehearsalとして直接作成できる。
- CandidateはOPEN / CLOSED / SELECTED / CANCELLEDの状態を持つ。
- SELECTEDとなったCandidateからRehearsalを生成できる。
- Candidateを物理削除しない。
- Availability対象者はProduction Participantを基本とする。
- Participant TypeをAvailability対象者の抽出条件として利用できる。
- Participant Typeは権限を付与しない。
- Candidateの管理権限はRoleまたはProduction Delegateによって判定する。
- Google Calendar連携はRehearsal確定後に行う。
- Candidate段階ではGoogle Calendar Event生成を必須としない。
- Candidateには監査情報を保持する。

---

# Domain Events

Rehearsal Candidateに関する主なDomain Event：

- RehearsalCandidateCreated
- RehearsalCandidateUpdated
- RehearsalCandidateClosed
- RehearsalCandidateSelected
- RehearsalCandidateCancelled

Availabilityに関するEventは、
Rehearsal Availability Domainで定義する。

Rehearsal生成時には、
Rehearsal DomainのEventを発行する。

---

# Design Decisions

Rehearsal Candidateは、
稽古日程を調整するためのDomainである。

Candidateと確定したRehearsalを分離する。

また、

Rehearsal Availability

と

Rehearsal Attendance

も分離する。

これにより、

「候補日には参加できなかったが、
確定後に予定を調整して参加できるようになった」

という現実の稽古日程調整を正しく表現できる。

RehearsalはCandidate経由だけではなく、
直接作成できる。

Google Calendar連携は、
Rehearsal確定後に行う。

---

# Future

将来的に以下へ対応できる構造とする。

- 複数候補日の自動比較
- 参加可能人数の自動集計
- キャスト優先の候補日判定
- スタッフ優先の候補日判定
- 条件付き回答の詳細管理
- 回答期限
- 自動リマインド
- Google Calendar空き時間との連携
- その他Calendarサービスとの連携

ただし、
候補日調整の複雑な最適化を
Rehearsal Candidate自身へ集約しない。

---

# Design Principles

- Rehearsal Candidateは稽古候補日を表す。
- Rehearsal CandidateはProductionに所属する。
- Candidateと確定Rehearsalを分離する。
- AvailabilityとAttendanceを分離する。
- Availabilityは日程調整のためのFactである。
- Attendanceは確定したRehearsalへの参加確認である。
- Candidateでの回答と確定後のAttendanceは独立して変更できる。
- RehearsalはCandidate経由でも直接でも作成できる。
- 本番日程など候補日調整を必要としない予定もRehearsalとして管理する。
- Participant TypeをAvailability対象者の条件として利用できる。
- Participant Typeは権限を表さない。
- Rehearsal管理権限はRoleまたはProduction Delegateで管理する。
- Google Calendar連携は確定したRehearsalを対象とする。
- Candidate段階ではCalendar Eventを必須生成しない。
- Candidateは履歴として保持する。
- Blueprintを唯一の設計基準とする。
