# StageArt Blueprint

# Domain Model : History

Version : 6.0

---

# Purpose

HistoryはStageArtにおける活動履歴を表す独立したDomainである。

HistoryはPersonやOrganizationの子Entityではなく、
Subjectを中心としてStageArt上で発生した活動履歴を管理する。

Historyは利用者が直接作成・編集するDomainではない。

ParticipantやReservationなどのDomainで発生した
Domain Eventを契機として、
History Domainが必要な履歴を生成・更新する。

本人が入力する自己紹介や、
StageArt登録前などの過去の活動実績は、
HistoryではなくProfileで管理する。

---

# Concept

Historyは、
SubjectがStageArt上で行った活動を記録する。

基本構造：

Subject
    │
    ▼
History
    │
    ├── Production
    └── Performance

SubjectはPersonまたはOrganizationを参照する。

HistoryはSubject自身の属性ではなく、
SubjectとStageArt上の活動との関係を記録する。

---

# Responsibility

Historyは以下を管理する。

- Subject
- HistoryType
- ParticipantType
- Production
- Performance
- EventDateTime

Historyは、
StageArt上で発生した活動Factの履歴を担当する。

ParticipantやReservationなど、
他DomainのBusiness Ruleは管理しない。

Profileが管理する本人入力情報を
Historyでは管理しない。

---

# Identity

HistoryはHistoryIdによって識別する。

HistoryIdは変更できない。

同一の活動を重複して記録しないよう、
生成元となるDomain Eventとの対応関係を利用して
冪等性を確保する。

Historyは、
同一のDomain Eventから複数生成されない。

---

# Subject

HistoryはSubjectを保持する。

Subjectは以下で構成される。

- SubjectType
- SubjectId

SubjectTypeは以下をサポートする。

- PERSON
- ORGANIZATION

HistoryはPersonやOrganizationを
History自身の属性として複製保持しない。

Subjectを介して活動主体を参照する。

---

# History Type

HistoryTypeは活動の種類を表す。

以下をサポートする。

- PARTICIPATION
- AUDIENCE

---

# PARTICIPATION

Productionへの参加を表す。

Participantによって発生する。

例えば、

- CAST
- STAFF
- DIRECTOR
- PRODUCER
- ORGANIZER
- SPONSOR
- SUPPORTER

などの参加実績を表現できる。

PARTICIPATION Historyは、
StageArt上でParticipantとして
Productionに参加したというFactを記録する。

---

# AUDIENCE

観客としてPerformanceを観覧した事実を表す。

Check In完了を契機として発生する。

予約しただけではAudience Historyを生成しない。

ReservationCreatedだけでは、
Audience Historyを生成しない。

ReservationUpdatedだけでは、
Audience Historyを生成しない。

ReservationCancelledだけでは、
Audience Historyを生成しない。

CheckInCompletedによって、
実際に来場したというFactを確定する。

---

# Participant Type

ParticipantTypeは、
HistoryTypeがPARTICIPATIONの場合にのみ保持する。

ParticipantTypeはProductionへの参加区分を表す。

例：

- CAST
- STAFF
- DIRECTOR
- PRODUCER
- ORGANIZER
- SPONSOR
- SUPPORTER

ParticipantTypeはParticipantで定義される値を使用する。

HistoryはParticipantTypeそのものの
Business Ruleを定義しない。

HistoryTypeがAUDIENCEの場合、
ParticipantTypeは保持しない。

---

# Production

Historyは活動対象となったProductionを保持する。

Productionは必須である。

Productionによって、
どの公演・作品に関する活動だったかを識別する。

---

# Performance

Historyは必要に応じてPerformanceを保持する。

Performanceは任意である。

Production単位の活動ではPerformanceを保持しない。

特定の公演回に紐付く活動ではPerformanceを保持する。

例えば、

出演実績：

Production
    = Production A

Performance
    = NULL

観劇履歴：

Production
    = Production A

Performance
    = Performance A

となる。

---

# Event Date Time

EventDateTimeは、
Historyが表す活動が発生した日時を記録する。

活動の性質に応じて、
ProductionまたはPerformanceに関連する日時を使用する。

PARTICIPATION Historyでは、
その活動が成立した日時を基準とする。

AUDIENCE Historyでは、
CheckInCompletedが発生した日時を基準とする。

---

# Generation

HistoryはDomain Eventを契機として
生成・更新される。

Historyを生成する元のDomainが、
Historyを直接操作することはない。

代表的な生成・更新契機は以下の通り。

---

# Participant Added

ParticipantAdded
        ↓
History

HistoryType
    = PARTICIPATION

ParticipantType
    = Participant.ParticipantType

ParticipantがProductionへ追加された時点で、
Participation Historyを生成する。

---

# Participant Updated

Participantの変更内容に応じて、
Historyを更新する。

ParticipantTypeの変更はHistoryへ反映する。

Role、CreditOrder、Visibilityなど、
Historyの活動内容そのものに影響しない情報の変更は
Historyを変更しない。

---

# Participant Removed

Participantが削除された場合でも、
過去に存在した活動実績を消去しない。

既に生成されたHistoryは保持する。

ParticipantRemovedは、
過去のHistoryを削除するためには使用しない。

---

# Check In Completed

CheckInCompleted
        ↓
History

HistoryType
    = AUDIENCE

ReservationのCheck Inが完了したことによって、
観客として実際に来場した事実をHistoryとして記録する。

ReservationCreatedだけではAudience Historyを生成しない。

ReservationUpdatedだけではAudience Historyを生成しない。

ReservationCancelledだけではAudience Historyを生成しない。

Check Inが完了した時点で、
観劇実績として扱う。

---

# Audience History Subject

Audience HistoryのSubjectは、
ReservationのBookerを使用する。

基本構造：

Reservation
    ↓
Booker
    ↓
Audience History.Subject

例えば、

Booker
    = Person A

の場合、

Audience History.Subject
    = Person A

となる。

Audience HistoryのSubjectは、
実際に観客として予約したBookerを表す。

---

# Handled Participant

HandledParticipantは、
Reservationにおける予約の「扱い」を表す。

HandledParticipantはParticipantを参照する。

HandledParticipantは、
予約を担当するParticipantを表すが、
それ自体は観客としての活動を意味しない。

そのため、
HandledParticipantの指定や変更だけでは
Audience Historyを生成・更新しない。

Audience HistoryのSubjectは、
Reservation.Bookerである。

---

# History and Reservation

ReservationはHistoryを管理しない。

Reservationは、
Check In完了時にCheckInCompletedを発行する。

History Domainは、
CheckInCompletedを契機として
Audience Historyを生成する。

基本構造：

Reservation
    ↓
Check In
    ↓
CheckInCompleted
    ↓
History
    ↓
Audience History

以下のEventではAudience Historyを生成しない。

- ReservationCreated
- ReservationUpdated
- ReservationCancelled

予約した事実、
予約内容を変更した事実、
予約をキャンセルした事実と、
実際に観劇した事実は
別の概念として扱う。

---

# History and Participant

ParticipantはHistoryを管理しない。

Participantは以下のDomain Eventを発行する。

- ParticipantAdded
- ParticipantUpdated
- ParticipantRemoved

History DomainはこれらのEventを契機として、
Participation Historyを生成・更新する。

ParticipantがHistoryを直接操作することはない。

---

# History and Handled Participant

HandledParticipantはReservationにおける
予約の「扱い」を表す。

HandledParticipantはParticipantを参照する。

HandledParticipantは予約を担当するParticipantを表すが、
それ自体は新しい活動履歴を意味しない。

そのため、
HandledParticipantの指定や変更だけでは
Historyを生成・更新しない。

観客としての来場履歴は、
CheckInCompletedを契機として生成される。

Audience HistoryのSubjectは、
Reservation.Bookerである。

---

# History and Created By

ReservationのCreatedByは、
Reservationを作成した主体を表す。

CreatedByは、
Audience HistoryのSubjectには使用しない。

Audience HistoryのSubjectは、
ReservationのBookerである。

例えば、

Booker
    = Person A

CreatedBy
    = Participant B

の場合でも、

Audience History.Subject
    = Person A

となる。

CreatedByは、
「誰が予約を入力したか」を表す情報であり、
「誰が観劇したか」を表す情報ではない。

---

# History and Updated By

ReservationのUpdatedByは、
Reservationを最後に変更した主体を表す。

UpdatedByは、
Audience HistoryのSubjectには使用しない。

Audience HistoryのSubjectは、
ReservationのBookerである。

ReservationUpdatedによって
UpdatedByが変更された場合でも、
Audience Historyは生成・更新しない。

---

# History and Ticket

Ticketは、
Productionにおける販売条件を表す。

Ticket自体はHistoryを生成しない。

ReservationがTicketを参照し、
そのReservationがCheck Inされた場合に、
CheckInCompletedを契機として
Audience Historyを生成する。

基本構造：

Ticket
    ↓
Reservation
    ↓
Check In
    ↓
CheckInCompleted
    ↓
Audience History

TicketのPrice変更や販売状態変更だけでは、
Audience Historyを生成・更新しない。

---

# History and Issued Ticket

Issued Ticketは、
Reservationに基づいて発行された
実際のチケットを表す。

Issued Ticket自体は、
観劇履歴を生成するFactではない。

基本構造：

Reservation
    ↓
Issued Ticket
    ↓
QR Ticket

Check Inによって
Reservationの来場が確定した場合に、

CheckInCompleted
    ↓
Audience History

を生成する。

---

# History and QR Ticket

QR Ticketは、
Issued Ticketを識別するための
受付用Artifactである。

QR Ticketを発行しただけでは、
Audience Historyを生成しない。

QR Ticketを再発行しても、
Audience Historyを新たに生成しない。

QR Check InによってReservationの
Check Inが完了した場合のみ、

CheckInCompleted
    ↓
Audience History

を生成する。

---

# Check In and Accounting

CheckInCompletedは、
History Domainだけでなく
Accounting Domainからも利用される。

基本構造：

                    ┌→ History
                    │
CheckInCompleted ───┤
                    │
                    └→ Accounting
                          ↓
                     Ticket Revenue

History Domainは、
CheckInCompletedを契機として
Audience Historyを生成する。

Accounting Domainは、
CheckInCompletedを契機として
Ticket Revenueを処理する。

History Domainは、
Journal Entryを管理しない。

---

# History and Profile

ProfileはPerson自身が作成・編集できる。

Profileには、
本人が入力する自己紹介情報や、
StageArt上で自動生成されない過去の活動実績を
記載できる。

例えば、

- 自己紹介
- 経歴
- StageArt登録前の出演実績
- StageArt登録前のスタッフ実績
- その他本人が紹介したい活動実績

など。

これらはHistoryでは管理しない。

---

# Profile and Historical Activity

本人が入力する過去の出演歴などは、
Profile配下のHistoricalActivityとして管理する。

基本構造：

Person
  ↓
Profile
  ↓
HistoricalActivity

HistoricalActivityは、
本人が入力・編集する過去の活動実績である。

HistoricalActivityは、
StageArt上でDomain Eventによって
自動生成されるHistoryとは異なる。

---

# Profile and History

ProfileとHistoryは、
異なる責務を持つ。

Profile：

本人が入力・編集する情報。

HistoricalActivity：

本人が入力する
StageArt登録前などの過去の活動実績。

History：

StageArt上で発生したFactから
自動的に生成される活動履歴。

基本構造：

本人入力

Person
  ↓
Profile
  ↓
HistoricalActivity

StageArt上の活動

Participant / Reservation
  ↓
Domain Event
  ↓
History

これにより、

本人が申告した過去実績と、
StageArt上で確認できる活動実績を
明確に区別する。

---

# Historical Activity

StageArt登録前など、
Historyの生成元となるFactが存在しない
過去の活動については、
HistoricalActivityで本人が入力できる。

例えば、

Profile
  ↓
HistoricalActivity

出演歴：

- 2018年「作品A」
- 2019年「作品B」
- 2020年「作品C」

など。

これらはHistoryには登録しない。

---

# Audience History and Profile

観劇履歴については、
本人が手入力するものではない。

StageArt上でチケットを予約し、
Check Inが完了した場合、

Reservation
    ↓
CheckInCompleted
    ↓
Audience History

として自動的に記録する。

本人が過去の観劇履歴を
HistoricalActivityへ入力することで
StageArt上のAudience Historyとして扱うことはしない。

StageArt上の観劇履歴は、
CheckInCompletedによって生成されたHistoryを
正本とする。

---

# Read Model

Historyは読み取りを中心としたDomainである。

Historyそのものを編集するための
公開APIは提供しない。

必要に応じて、
他のBusiness Resource APIが
Historyを集約して返す。

例えば、

Person API
    ↓
Subject = PERSON
    ↓
History[]

Organization API
    ↓
Subject = ORGANIZATION
    ↓
History[]

Historyは独立Domainとして保持されるが、
利用者からはPersonやOrganizationなどの
Business Resourceを通して参照できる。

---

# History Generation

History生成の基本原則：

StageArt上で活動Factが発生する
        ↓
Domain Event
        ↓
History Domain
        ↓
History

History Domainが、
元DomainのBusiness Ruleを直接実行することはない。

Historyは、
元となるDomain Eventの結果として
生成される活動履歴である。

---

# Idempotency

同一のDomain Eventを複数回受信した場合でも、
同じHistoryを重複生成しない。

History生成時には、
必要に応じて以下を利用して
冪等性を確保する。

- Source Event ID
- Source Entity ID
- History Type
- Activity Context

Historyの重複生成を防止する。

---

# History Persistence

Historyは、
生成された後も保持する。

元となったParticipantが削除された場合でも、
過去のParticipation Historyを削除しない。

元となったReservationがキャンセルされた場合でも、
それだけではAudience Historyを生成しない。

Check In済みのReservationが
後から変更・削除されることによって、
既に生成されたAudience Historyを
自動的に削除しない。

過去に発生した活動Factは、
履歴として保持する。

---

# Business Rules

- Historyは独立したDomainである。
- HistoryはPersonやOrganizationの子Entityではない。
- HistoryはSubjectを中心として活動履歴を管理する。
- SubjectはPersonまたはOrganizationである。
- HistoryはProductionを必ず参照する。
- Performanceは任意である。
- HistoryTypeはPARTICIPATIONまたはAUDIENCEである。
- PARTICIPATIONではParticipantTypeを保持する。
- AUDIENCEではParticipantTypeを保持しない。
- ParticipantAddedを契機としてParticipation Historyを生成する。
- ParticipantUpdatedに応じて必要なHistoryを更新する。
- ParticipantRemovedによって過去のHistoryを削除しない。
- ReservationCreatedだけではAudience Historyを生成しない。
- ReservationUpdatedだけではAudience Historyを生成しない。
- ReservationCancelledだけではAudience Historyを生成しない。
- CheckInCompletedによってAudience Historyを生成する。
- Audience HistoryのSubjectはReservation.Bookerである。
- HandledParticipantはAudience HistoryのSubjectにならない。
- CreatedByはAudience HistoryのSubjectにならない。
- UpdatedByはAudience HistoryのSubjectにならない。
- Ticketの作成・変更・価格変更だけではHistoryを生成しない。
- Issued Ticketの発行だけではAudience Historyを生成しない。
- QR Ticketの発行だけではAudience Historyを生成しない。
- QR Ticketの再発行だけではAudience Historyを生成しない。
- QR Check Inが完了した場合も、Manual Check Inと同じCheckInCompletedを利用する。
- CheckInCompletedを契機としてAudience Historyを生成する。
- CheckInCompletedはAccounting Domainでも利用できる。
- History DomainはJournal Entryを管理しない。
- Historyは利用者が直接作成・編集・削除しない。
- HistoryはDomain Eventを契機として生成・更新する。
- 同一Domain EventからHistoryを重複生成しない。
- 過去のHistoryは原則として削除しない。
- Profileは本人入力情報を管理する。
- HistoricalActivityは本人が入力する過去の活動実績を管理する。
- HistoricalActivityをHistoryへ自動変換しない。
- StageArt上の活動実績はHistoryで管理する。
- StageArt登録前などの本人申告による過去実績はHistoricalActivityで管理する。
- StageArt上のAudience HistoryはCheckInCompletedを正本となる生成契機とする。

---

# Domain Events

History Domain自身が
活動Factを発生させることはない。

Historyは他DomainのEventを受信して生成・更新する。

主なEvent：

- ParticipantAdded
- ParticipantUpdated
- ParticipantRemoved
- CheckInCompleted

---

# Participant Events

ParticipantAdded
    ↓
History
    ↓
HistoryType = PARTICIPATION

ParticipantUpdated
    ↓
必要に応じてHistory更新

ParticipantRemoved
    ↓
過去Historyは保持

---

# Check In Event

CheckInCompleted
    ↓
History
    ↓
HistoryType = AUDIENCE

CheckInCompletedは、
観客が実際に来場したというFactを確定する。

ReservationCreatedではなく、
CheckInCompletedをAudience History生成の
正式な契機とする。

---

# Event Consumption

CheckInCompletedは、
複数のDomainが利用できる。

基本構造：

CheckInCompleted
       │
       ├── History
       │     └── Audience History
       │
       └── Accounting
             └── Ticket Revenue

History Domainは、
CheckInCompletedを受信して
Audience Historyを生成する。

Accounting Domainは、
同じEventを受信して
Ticket Revenueを処理する。

各Domainは、
それぞれの責務を独立して処理する。

---

# Data Examples

## Participation History

{
  "historyType": "PARTICIPATION",
  "subject": {
    "type": "PERSON",
    "id": "person-001"
  },
  "participantType": "CAST",
  "productionId": "production-001",
  "performanceId": null
}

---

## Performance Participation History

{
  "historyType": "PARTICIPATION",
  "subject": {
    "type": "PERSON",
    "id": "person-001"
  },
  "participantType": "CAST",
  "productionId": "production-001",
  "performanceId": "performance-001"
}

---

## Audience History

{
  "historyType": "AUDIENCE",
  "subject": {
    "type": "PERSON",
    "id": "person-001"
  },
  "participantType": null,
  "productionId": "production-001",
  "performanceId": "performance-001"
}

---

# Data Source

Historyのデータソースは、
History自身ではない。

PARTICIPATION：

Participant
  ↓
Participant Event
  ↓
History

AUDIENCE：

Reservation
  ↓
Check In
  ↓
CheckInCompleted
  ↓
History

Profile：

Person
  ↓
Profile
  ↓
HistoricalActivity

それぞれの情報源を混在させない。

---

# Domain Boundary

History Domainは、
以下を直接管理しない。

- Person Profile
- HistoricalActivity
- Participant
- Reservation
- Ticket
- Issued Ticket
- QR Ticket
- Check In
- Accounting
- Journal Entry

これらはそれぞれのDomainが正本として管理する。

Historyは、
それらのDomain Eventを受信し、
活動履歴として必要な情報を保持する。

---

# Design Decisions

Historyは、
StageArt上で発生した活動Factの履歴を管理する。

本人が入力する自己紹介や
StageArt登録前などの過去実績は、
Profile配下のHistoricalActivityで管理する。

HistoryとHistoricalActivityを混同しない。

Participation Historyは、
Participantに起因するDomain Eventから生成する。

Audience Historyは、
CheckInCompletedを契機として生成する。

予約しただけでは、
観劇履歴を生成しない。

Ticketを購入・予約しただけでは、
Audience Historyを生成しない。

Issued Ticketを発行しただけでは、
Audience Historyを生成しない。

QR Ticketを発行しただけでは、
Audience Historyを生成しない。

Check Inが完了した時点で、
観劇実績として扱う。

Audience HistoryのSubjectは、
Reservation.Bookerである。

HandledParticipant、
CreatedBy、
UpdatedByは、
Audience HistoryのSubjectにならない。

CheckInCompletedは、
History Domainだけでなく
Accounting Domainでも利用する。

History Domainは、
AccountingのJournal Entryを管理しない。

Historyは、
生成元となったDomainのBusiness Ruleを
直接管理しない。

Historyは、
活動Factを読み取り可能な形で保持する
履歴Domainとして機能する。

---

# Future

将来的に以下へ対応できる。

- Activity Category
- Achievement Type
- Organization Activity History
- Production Archive
- Performance Archive
- 出演回数集計
- 観劇回数集計
- 年別活動集計
- Person Timeline
- Organization Timeline
- Activity Search
- Activity Filtering
- 外部活動履歴との連携

ただし、
将来機能を追加する場合も、

StageArt上で発生したFact
    ↓
Domain Event
    ↓
History

という基本構造を維持する。

本人申告の過去実績については、

Person
  ↓
Profile
  ↓
HistoricalActivity

という構造を維持する。

---

# Design Principles

- Historyは独立したDomainである。
- HistoryはPersonやOrganizationの子Entityではない。
- HistoryはSubjectと活動の関係を記録する。
- SubjectはPersonまたはOrganizationである。
- HistoryはProductionを必ず参照する。
- Performanceは必要な場合のみ参照する。
- PARTICIPATIONとAUDIENCEをHistoryTypeで区別する。
- PARTICIPATIONではParticipantTypeを保持する。
- AUDIENCEではParticipantTypeを保持しない。
- Participant EventからParticipation Historyを生成する。
- CheckInCompletedからAudience Historyを生成する。
- ReservationCreatedからAudience Historyを生成しない。
- TicketやIssued Ticketから直接Audience Historyを生成しない。
- QR Ticketから直接Audience Historyを生成しない。
- Audience HistoryのSubjectはReservation.Bookerである。
- HandledParticipantをAudience HistoryのSubjectにしない。
- CreatedByをAudience HistoryのSubjectにしない。
- UpdatedByをAudience HistoryのSubjectにしない。
- ProfileとHistoryを分離する。
- HistoricalActivityとHistoryを分離する。
- 本人入力の過去実績はHistoricalActivityで管理する。
- StageArt上で発生した活動実績はHistoryで管理する。
- Historyを利用者が直接編集しない。
- HistoryはDomain Eventから生成する。
- 同一EventからHistoryを重複生成しない。
- 過去のHistoryを原則として削除しない。
- CheckInCompletedはHistoryとAccountingの共通Eventとして利用できる。
- History DomainはJournal Entryを管理しない。
- Blueprintを唯一の設計基準とする。