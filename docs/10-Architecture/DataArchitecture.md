# StageArt Blueprint

# 10 - Architecture
# Data Architecture

Version : 1.3

---

# Purpose

Data Architectureは、
StageArtがBusiness Dataをどのように管理し、
永続化し、
Domain間で関連付けるかを定義する。

Data Architectureでは、

- Data Ownership
- Source of Truth
- Entity Identity
- Relationship
- Scope
- Persistence
- Transaction
- Audit
- External Reference
- History
- Accounting Data
- Integration Data
- Read Model
- Operational Data

を定義する。

Data Architectureでは、
具体的なDatabase TableやColumnを
まだ確定しない。

Database Schemaは、
本Architectureを基準として
Implementation Specificationで定義する。

Data Architectureの最重要原則は、

「Business Factの正本と、
そのBusiness Factを表示・検索・連携するための
Projection / Artifact / Cache / External Representationを
明確に分離する」

ことである。

---

# 1. Data Architecture Principles

StageArtのData Architectureは、
以下を基本原則とする。

- Business Factを正本として管理する。
- Domain EntityとDatabase Modelを分離する。
- Domain ModelとDatabase Schemaを同一視しない。
- EntityはBusiness Identityを持つ。
- DomainごとにData Ownershipを明確にする。
- 他DomainのDataを直接書き換えない。
- Organization Scopeを主要なTenant Boundaryとして扱う。
- Project ScopeをOrganization内の上位Business Contextとして扱う。
- Production Scopeを必要に応じてAuthorization Boundaryとして扱う。
- Performance Scopeを受付・公演単位のOperation Boundaryとして扱う。
- External ServiceをBusiness Factの正本にしない。
- ArtifactとBusiness Factを分離する。
- Historyを現在状態の代替として扱わない。
- Accounting Dataを通常のBusiness Dataと混同しない。
- Audit DataとBusiness Factを分離する。
- Client側のStateをBusiness Factの正本にしない。
- Read ModelをBusiness Factの正本にしない。
- CacheをBusiness Factの正本にしない。
- Transaction BoundaryをApplication Use Caseと整合させる。
- Data IntegrityをApplicationとDatabaseの双方で保証する。
- Check InはClient固有のDataではなく、Server Sideで確定するBusiness Factとして管理する。
- Web ClientとMobile Clientは、同一のCheck In Business Factを生成する。
- QR CodeはCheck Inそのものではなく、Issued Ticketを識別するArtifactとして扱う。
- Reservationは、Performanceに対する予約というBusiness Factを所有する。
- Check Inは、Reservationに対する受付というBusiness Factを所有する。
- Issued Ticketは、発行されたTicketを表すBusiness Dataであり、Check Inそのものではない。
- ProjectをProductionの上位Business Contextとして扱う。
- Organization → Project → ProductionのScope構造を維持する。
- System AdministratorのOrganization Selectionは、Business DataそのものではなくContext Selectionとして扱う。
- System Operational DataとBusiness Dataを分離する。
- Backup DataとReplication DataをBusiness Factの別Versionとして扱わない。
- Mirror Dataを通常運用時のBusiness Factの正本としない。
- RestoreされたDataは、復旧後にStageArtの正本として利用できる状態へ検証する。

---

# 2. Data Ownership

StageArtでは、
各Domainが自分のBusiness Dataに対する
Ownershipを持つ。

基本原則：

Domain A
↓
Owns Data A

Domain B
↓
Owns Data B

Domain Aが、
Domain BのDataを直接更新しない。

他Domainに影響する処理は、

- Application Service
- Domain Event
- Application Process
- Domain Reference

などを利用する。

Data OwnershipとData Referenceを
混同しない。

他DomainのEntityを参照することはできるが、
そのEntityの内部Stateを
直接変更してはならない。

---

# 3. Source of Truth

Business Factごとに、
Source of Truthを明確にする。

主なSource of Truth：

Person
→ Person

Profile
→ Profile

Historical Activity
→ HistoricalActivity

Organization
→ Organization

Membership
→ Membership

Project
→ Project

Production
→ Production

Participant
→ Participant

Production Authorization
→ ProductionDelegate

Performance
→ Performance

Ticket
→ Ticket

Reservation
→ Reservation

Issued Ticket
→ Issued Ticket

Check In
→ Check In

Rehearsal
→ Rehearsal

Rehearsal Attendance
→ Rehearsal Attendance

Journal Entry
→ Journal Entry

Document
→ Document

Announcement
→ Announcement

Survey Response
→ Survey Response

External Service上の情報を、
これらのBusiness Factの代わりにしない。

Check Inについては、
Web ClientやMobile Clientが保持する
UI StateやLocal Stateを正本としない。

Reservationについては、
Client上の予約表示やTicket表示を
Reservation Business Factの代わりにしない。

Check In ListやDashboardなどの
Read Modelも、
Check In Business Factの正本ではない。

---

# 4. Domain Data Ownership

主要DomainのData Ownershipを以下とする。

Identity：

- UserAccount
- External Identity
- Person
- Profile
- HistoricalActivity

Organization：

- Organization
- Membership

Production：

- Project
- Production
- Participant
- ProductionDelegate

Performance：

- Performance

Rehearsal：

- Rehearsal Candidate
- Rehearsal Availability
- Rehearsal
- Rehearsal Attendance
- Timetable
- Timetable Item

Ticket：

- Ticket
- Ticket Type
- Ticket Price
- Issued Ticket
- QR Ticket

Reservation：

- Reservation

Check In：

- Check In

History：

- Audience History

Accounting：

- Account
- Accounting Period
- Journal Entry
- Journal Entry Line
- Budget
- Budget Item
- Production Actual
- Production Settlement

Communication：

- Announcement
- Announcement Recipient
- Announcement Delivery

Document：

- Document
- Document Share
- External Storage Reference

Promotion：

- Public Profile
- Public Page
- Social Post
- Social Post Reference

Equipment：

- Equipment
- Equipment History

Regulation：

- Regulation
- Regulation Version

Survey：

- Survey
- Survey Response
- Public Testimonial

System Operations：

- Audit Log
- Backup Metadata
- Replication Status
- Recovery History
- System Health Snapshot
- Operational Job Status

System Operations Dataは、
Business Domain Dataとは
別の責務として扱う。

---

# 5. Identity Data

Identityは、
StageArtにおけるBusiness Identityを管理する。

基本構造：

UserAccount
↓
Person
├── Profile
└── HistoricalActivity

UserAccountは、
ApplicationへのLogin Identityを表す。

Personは、
StageArt Business DomainにおけるPersonを表す。

Profileは、
Personに関する現在のProfile情報を表す。

HistoricalActivityは、
Personの過去の活動実績を表す。

---

# 6. UserAccount

UserAccountは、
StageArt Applicationへの
Authentication Identityを表す。

UserAccountは、

- Login Identity
- Authentication Provider Reference
- Authentication State

などを管理できる。

UserAccountとPersonは、
同一概念として扱わない。

UserAccount：

Authentication Identity

Person：

Business Identity

という責務分離を維持する。

---

# 7. Person

Personは、
StageArtにおけるBusiness Identityである。

Personは、

- Organization
- Production
- Participant
- Membership
- ProductionDelegate
- Reservation
- Check In
- History

など、
複数のDomainから参照される。

PersonのIDは、
他DomainからPersonを識別するための
Business Referenceとして利用する。

Personそのものを、
WordPress UserなどのExternal Identityと
同一視しない。

Personは、
Authentication Identityではなく、
Business DomainにおけるPerson Identityである。

---

# 8. Profile

Profileは、
Personの現在のProfile情報を管理する。

Profileは、
Personそのものとは分離する。

Profileには、
例えば、

- 自己紹介
- 現在の活動情報
- 基本的な公開情報
- その他Profile情報

などを保持できる。

Profileは、
過去の出演履歴などの
Historical Factそのものを
直接保持する場所ではない。

---

# 9. HistoricalActivity

HistoricalActivityは、
Personの過去の活動実績を管理する。

例えば、

- 出演
- 演出
- 制作
- スタッフ
- その他舞台活動

などを記録できる。

HistoricalActivityは、
Profileの単なるText Fieldとして扱わず、
独立した子Entityとして管理する。

基本構造：

Person
└── Profile

Person
└── HistoricalActivity
    ├── Activity
    ├── Activity
    └── Activity

HistoricalActivityは、
Personに紐づく過去のBusiness Factを表す。

現在のProfile情報を変更しても、
過去のHistoricalActivityを
自動的に変更しない。

---

# 10. Organization Data

Organizationは、
StageArtにおける主要なTenant Boundaryである。

Organizationに属するDataは、
原則としてOrganization Scopeを持つ。

Core Structure：

Organization
↓
Project
↓
Production

Organizationに関連するDataの例：

Organization
├── Membership
├── Project
├── Equipment
├── Regulation
├── Communication
└── Accounting

Organization IDは、
Scope判定に必要なDataとして扱う。

---

# 11. Membership

Membershipは、
PersonとOrganizationの所属関係を表す。

基本構造：

Person
↓
Membership
↓
Organization

Membershipは、

「このPersonがこのOrganizationに所属している」

というBusiness Factを表す。

Membershipと、
Organization内のRoleを分離して考える。

Membershipは、
PersonがOrganization Scopeに入るための
主要なBusiness Relationshipである。

---

# 12. Organization Role

Organization Roleは、
Organization ScopeにおけるAuthorizationを表す。

基本構造：

Person
↓
Membership
↓
Organization
↓
Role
↓
Permission

Roleは、
Personそのものの属性ではない。

同じPersonが、
複数Organizationで
異なるRoleを持つことができる。

Organization Roleによる権限は、
Membershipを介して
Organization Scopeに適用する。

---

# 13. Project Data

Projectは、
Organization内で行われる
Production活動をまとめるBusiness Contextを表す。

基本構造：

Organization
↓
Project
↓
Production

Projectは、
Organizationに属する。

Project Scopeを必要に応じて利用する。

Projectは、
複数のProductionをまとめる
上位Business Contextとして扱う。

---

# 14. Production Data

Productionは、
Project内で行われる具体的な
舞台活動単位を表す。

基本構造：

Organization
↓
Project
↓
Production
├── Participant
├── ProductionDelegate
├── Performance
├── Rehearsal
├── Ticket
├── Reservation
├── Check In
└── Accounting Reference

Productionは、
Organization Scopeに属する。

Production固有のDataは、
Production Scopeによって管理する。

---

# 15. Production Scope

Productionに関連するDataは、
必要に応じてProduction Scopeを持つ。

Production Scopeを利用する主なData：

- Participant
- ProductionDelegate
- Performance
- Rehearsal
- Ticket
- Reservation
- Check In
- Production Accounting

Production Scopeを越えてDataへアクセスする場合は、
Authorizationを必要とする。

Production IDを知っているだけでは、
Production DataへのAccessを許可しない。

---

# 16. Participant

Participantは、
PersonまたはOrganizationが
Productionに参加しているという
Business Factを表す。

基本構造：

Person
↓
Participant
↓
Production

Participantは、
Authorizationそのものではない。

Participant TypeとRoleを分離する。

例えば、

Participant Type
→ CAST

Role
→ Rehearsal Manager

というように、
参加区分と権限を独立して管理する。

---

# 17. ProductionDelegate

ProductionDelegateは、
Personが特定Productionを
管理する権限を持つことを表す。

基本構造：

Person
↓
ProductionDelegate
↓
Production
↓
Role

PrimaryManagerも、
Production Scopeの権限として扱う。

Participantであることだけでは、
Production Management Permissionを付与しない。

ProductionDelegateは、
Organization Membershipとは別の
Production Scope Authorizationとして扱う。

---

# 18. Performance

Performanceは、
Production内の具体的な公演回を表す。

基本構造：

Production
↓
Performance
├── Ticket
└── Reservation
    └── Check In

Performanceは、
TicketやReservationが
どの公演回を対象としているかを
明確にする。

Check Inは、
Performanceそのものではなく、
Performanceに対するReservationの
受付Factとして扱う。

---

# 19. Ticket Data

Ticketは、
販売可能なチケットに関するBusiness Dataを管理する。

Ticket関連のDataを分離する。

Ticket：

販売対象となるTicket情報。

Ticket Type：

一般、学生などのTicket分類。

Ticket Price：

価格情報。

Issued Ticket：

特定Reservation等に対して
実際に発行されたTicket。

QR Ticket：

Issued Ticketを識別するArtifact。

Ticket Dataは、
ReservationやCheck Inと
同一概念として扱わない。

---

# 20. Ticket

Ticketは、
Performanceに対して提供される
販売対象としてのTicketを表す。

Ticketは、
Ticket TypeやTicket Priceなどと
関連する。

Ticketは、
特定の観客によるReservationや
特定のIssued Ticketそのものとは異なる。

基本構造：

Performance
↓
Ticket
├── Ticket Type
└── Ticket Price

---

# 21. Ticket Type

Ticket Typeは、
Ticketの分類を表す。

例：

- 一般
- 学生
- 子供
- 招待
- その他

Ticket Typeは、
Ticketそのものや
Reservationそのものとは分離する。

---

# 22. Ticket Price

Ticket Priceは、
Ticketに適用される価格情報を表す。

価格は、
後からTicket Priceが変更されても、
過去のReservationやAccountingに
必要な価格情報が維持できるようにする。

必要に応じて、

Price Definition
↓
Price Snapshot

という構造を利用する。

過去のTransactionが、
現在のPrice Master変更によって
書き換わらないようにする。

---

# 23. Reservation

Reservationは、
観客がPerformanceに対して行った
予約を表す。

基本構造：

Performance
↓
Reservation
↓
Booker / Person

Reservationは、
予約というBusiness Factの
Source of Truthである。

Reservationには、
必要に応じて、

- Reservation Number
- Booker
- Guest Count
- Price Snapshot
- Status
- Issued Ticket Reference

などを関連付ける。

ReservationとIssued Ticketは、
同じ概念として扱わない。

Reservationは、
Ticketが発行される前から
Business Factとして存在できる。

---

# 24. Reservation Identity

Reservationは、
内部Identityと
必要に応じて外部利用可能な
Reservation Numberを持つ。

内部Identity：

Application / Domainが
Reservationを一意に識別するために利用する。

Reservation Number：

観客・受付担当者・管理者などが
Reservationを検索するために利用できる
Business Identifier。

Reservation Numberを知っているだけでは、
Authorization Scopeを越えた
Reservation Accessを許可しない。

---

# 25. Reservation State

Reservationは、
Business Stateを持つ。

例えば、

- Draft
- Confirmed
- Cancelled
- Completed
- Other Defined State

など。

具体的なStateと遷移条件は、
Reservation Domainで定義する。

ClientのUI Stateと、
Reservation Business Stateを
同一視しない。

---

# 26. Issued Ticket

Issued Ticketは、
実際に発行されたTicketを表す。

基本構造：

Ticket
↓
Issued Ticket
↓
Reservation

Issued Ticketは、
Reservationに対して発行された
具体的なTicketを表す。

Issued Ticketは、
Check Inを識別するための
受付情報として利用できる。

ただし、
Issued Ticketそのものを
Check In Factと同一視しない。

---

# 27. QR Ticket

QR Ticketは、
Issued Ticketを識別するための
Artifactとして扱う。

基本構造：

Issued Ticket
↓
QR Ticket
↓
QR Code

QR Codeそのものを、
Business Factの正本としない。

QR Codeが削除・再発行されても、
Issued TicketやReservationの
Business Factそのものは維持できる構造とする。

---

# 28. QR Code

QR Codeは、
受付時にIssued Ticketなどを
識別するためのArtifactである。

QR Codeには、
必要に応じて、

- Ticket Identifier
- Validation Information
- Version
- Other Verification Data

などを含めることができる。

ただし、
QR Codeの内容を
Business Factそのものとして扱わない。

QR Codeの情報を受け取った後、
Server Sideで対象Dataを解決し、
Business Ruleを検証する。

---

# 29. QR Code and Check In

QR Codeは、
Check Inそのものではない。

基本構造：

QR Code
↓
Issued Ticket
↓
Reservation
↓
Check In

QR Codeを読み取っただけでは、
Check In Business Factは生成されない。

Server Sideで、

- Ticket存在
- Ticket有効性
- Performance
- Reservation
- Check In State
- Authorization

などを検証した上で、
Check Inを確定する。

---

# 30. Check In Data

Check Inは、
Reservationに対する
受付というBusiness Factを表す。

Canonical Relationship：

Reservation
↓
Check In

Check Inは、
ClientのReception Modeに
所有されるDataではない。

Web Clientから実行しても、
Mobile Clientから実行しても、
同一のCheck In Business Factとして保存する。

---

# 31. Check In Source of Truth

Check InのSource of Truthは、
StageArt Server Sideの
Check In Dataとする。

以下を正本としない。

- Mobile Local State
- Web UI State
- QR Scanner State
- Browser Cache
- Client Cache
- Read Model
- External Service

Check Inの確定は、
Application Use CaseとDomain Ruleを通して
Server Sideで行う。

---

# 32. Check In Relationship

Check InのCanonical Relationshipは、

Reservation
↓
Check In

である。

必要に応じて、

Check In
├── Reservation
├── Performance
├── Person / Booker
└── Issued Ticket Reference

などを参照する。

Issued Ticketは、
受付経路によって利用される
識別情報であり、
Check Inそのものではない。

---

# 33. Check In and Performance

Check Inは、
対象Performanceとの整合性を持つ。

基本構造：

Production
↓
Performance
↓
Reservation
↓
Check In

Check In実行時には、
対象Reservationが
対象Performanceに属していることを
Server Sideで確認する。

異なるPerformanceのReservationを、
現在の受付対象として
Check Inできないようにする。

---

# 34. Check In and Issued Ticket

Issued Ticketを利用する受付では、

Issued Ticket
↓
Reservation
↓
Check In

というResolutionを行う。

Issued Ticketが存在していても、
以下の場合はCheck Inを許可しない。

- Invalid Ticket
- Cancelled Ticket
- Wrong Performance
- Already Checked In
- Unauthorized Operation
- Other Business Rule Violation

Issued TicketのStateと
Check InのStateを
同一視しない。

---

# 35. Check In and Reservation

Check Inは、
Reservationに対する受付Factである。

基本関係：

Reservation
↓
Check In

ReservationがCancelledなど、
Check Inを許可できないStateの場合、
Check Inを生成できない。

具体的なState Ruleは、
Reservation Domainと
Check In Domainで定義する。

---

# 36. Check In Uniqueness

同一Reservationについて、
同一Performanceに対する
Check In Business Factが
不必要に複数生成されないようにする。

Application Layerでは、
Idempotencyを考慮する。

Database Layerでは、
必要に応じてUnique Constraintなどを利用する。

Client側のDouble Submit対策だけに依存しない。

---

# 37. Check In Idempotency

Check Inは、
Timeoutや通信Retryによって
同一Operationが複数回送信される可能性がある。

そのため、

Check In Request
↓
Retry
↓
同一Operation判定
↓
既存Check In確認
↓
Duplicate Factを生成しない

という構造を考慮する。

Idempotency Keyなどの
具体的なImplementationは、
Backend / Database Architectureで定義する。

---

# 38. Check In Concurrency

複数受付端末から、
同一Reservationに対する
Check Inが同時に発生する可能性がある。

例えば、

Mobile Client
↓
QR Check In

と同時に、

Web Client
↓
Manual Check In

が同一Reservationに対して
実行される場合がある。

この場合でも、
同一Reservationに対して
不整合なCheck In Factを
複数生成しない構造とする。

Transaction、
Lock、
Unique Constraintなどの
具体的なImplementationは、
Database Architectureで定義する。

---

# 39. Check In Timestamp

Check Inには、
必要に応じて受付時刻を保持する。

Check In Timestampは、
Client端末のClockを
無条件に正本としない。

Server SideのTimestampを
基本とする。

Client Timeが必要な場合は、
補助情報として扱う。

---

# 40. Check In Actor

Check Inには、
必要に応じてOperationを実行した
Actorを記録する。

例えば、

- Staff
- Production Manager
- Reception Operator
- System Administrator Context

など。

Actor情報は、
Check In Business Factと
Audit Contextを適切に分離して管理する。

---

# 41. Check In Source

Check Inには、
必要に応じて受付経路を記録できる。

例：

- WEB_MANUAL
- WEB_SEARCH
- WEB_RESERVATION_NUMBER
- WEB_BOOKER_NAME
- MOBILE_QR
- MOBILE_MANUAL
- SYSTEM_ADMIN_CONTEXT

Sourceは、
Check In Business Ruleそのものではない。

Sourceは、
Audit、
Reporting、
Operation Contextなどに利用する。

---

# 42. Web and Mobile Check In

Web ClientとMobile Clientは、
同一のCheck In Business Factを生成する。

Web：

Web Client
↓
Reservation / Ticket
↓
Check In

Mobile：

Mobile Client
↓
Reception Mode
↓
QR / Search
↓
Reservation
↓
Check In

両者で、
別のCheck In Entityや
別のCheck In Tableを作らない。

---

# 43. Reception Mode Data Boundary

Reception Modeは、
Mobile ClientのUI / Operational Modeである。

Reception Mode自体を、
Business Data Entityとして
保存する必要はない。

必要な場合、

Reception Operation
↓
Check In Source

などのAudit Contextとして
受付Modeを記録できる。

しかし、

Reception Mode
≠
Check In

である。

---

# 44. Rehearsal Data

Rehearsalは、
Productionに関連する
稽古・活動予定を表す。

基本構造：

Production
↓
Rehearsal
├── Rehearsal Availability
├── Participant
└── Rehearsal Attendance

Rehearsalは、
Performanceとは別のBusiness Factである。

---

# 45. Rehearsal Attendance

Rehearsal Attendanceは、
PersonがRehearsalに参加したという
Business Factを表す。

基本構造：

Rehearsal
↓
Rehearsal Attendance
↓
Person / Participant

Rehearsal Attendanceは、
Check Inとは異なる。

Rehearsal Attendance
≠
Performance Check In

---

# 46. Timetable

Timetableは、
ProductionまたはRehearsalに関連する
Schedule情報を管理する。

Timetableは、
Schedule Projectionとして利用できるが、
Business Factを持つ場合は、
そのOwnershipを明確にする。

External Calendar上のEventは、
StageArt Timetableの
External Representationとして扱う。

---

# 47. Audience History

Audience Historyは、
観客の過去のStageArt利用履歴を管理する。

例えば、

- Reservation
- Ticket
- Check In
- Performance Participation

などを起点として生成・参照できる。

Audience Historyは、
ReservationやCheck Inの
代替Source of Truthではない。

基本構造：

Business Fact
↓
History Projection / Record
↓
Audience History

---

# 48. History and Current State

HistoryとCurrent Stateを分離する。

例えば、

Reservation
→ Current Reservation State

Check In
→ Current Check In Fact

Audience History
→ Past Activity

というように、
現在状態と履歴を同一Dataとして扱わない。

Historyを変更して、
現在のBusiness Factを
逆算して変更する構造にしない。

---

# 49. Accounting Data

Accountingは、
Business Operationから生成される
Financial Dataを管理する。

主なData：

- Account
- Accounting Period
- Journal Entry
- Journal Entry Line
- Budget
- Budget Item
- Production Actual
- Production Settlement

Accounting Dataは、
ReservationやCheck Inの
単純な属性として扱わない。

---

# 50. Journal Entry

Journal Entryは、
Accounting上のBusiness Factを表す。

基本構造：

Journal Entry
├── Journal Entry Line
├── Accounting Period
└── Account

Journal Entryは、
Check InやReservationの
内部Stateを直接置き換えるものではない。

---

# 51. Accounting and Check In

Check Inを起点として、
Accounting Processが実行される場合がある。

基本構造：

Check In
↓
CheckInCompleted
↓
Accounting Process
↓
Journal Entry

Check In Domainが、
Journal Entryの内部Dataを
直接更新しない。

Accounting Domainが、
Accounting Business Ruleを所有する。

---

# 52. Price Snapshot

ReservationやAccountingでは、
将来のMaster Data変更から
過去のTransactionを保護するため、
必要な価格情報をSnapshotとして保持する。

例えば、

Ticket Price
↓
Reservation
↓
Price Snapshot

という構造。

現在のTicket Priceを変更しても、
過去のReservationの
確定価格を自動変更しない。

---

# 53. Document Data

Documentは、
StageArt上のDocument Metadataと
File Referenceを管理する。

基本構造：

Document
├── Document Share
└── External Storage Reference

Documentは、
File Binaryそのものと
同一概念として扱わない。

---

# 54. External Storage Reference

External Storage Referenceは、
外部Storage上のFileを
StageArt Documentと関連付ける。

基本構造：

Document
↓
External Storage Reference
↓
External Storage

External StorageのFile IDは、
StageArtのBusiness Identityそのものではない。

External Storageを変更しても、
Document Business Contextを
失わない構造とする。

---

# 55. Communication Data

Communication Domainは、
AnnouncementとDelivery情報を管理する。

基本構造：

Announcement
↓
Announcement Recipient
↓
Announcement Delivery

Announcementは、
EmailやSNSのMessageそのものではない。

External Deliveryは、
Announcementの外部Representationとして扱う。

---

# 56. Promotion Data

Promotion Domainでは、

- Public Profile
- Public Page
- Social Post
- Social Post Reference

などを管理する。

Public PageやSocial Postは、
Production / Organizationの
公開Representationである。

SNS上のPostを、
ProductionのBusiness Factの正本としない。

---

# 57. Equipment Data

Equipmentは、
OrganizationまたはProductionで
利用する機材・備品などを管理する。

Equipment Historyは、
Equipmentの過去状態や利用履歴を表す。

Current Equipment Stateと
Equipment Historyを分離する。

---

# 58. Regulation Data

Regulationは、
Organization内の規程・ルールを管理する。

Regulation Versionは、
規程のVersionを管理する。

現在Versionと
過去Versionを分離する。

過去Versionを、
現在Versionの上書きによって
失わない構造とする。

---

# 59. Survey Data

Surveyは、
Question / Responseを管理する。

主なData：

- Survey
- Survey Response
- Public Testimonial

Survey Responseは、
PersonやAudienceのResponse Factとして扱う。

Public Testimonialは、
Public表示可能なDataとして
別のScope / Publication Stateを持たせる。

---

# 60. Scope Data

StageArtでは、
Data Scopeを明確にする。

主要Scope：

Global System Scope
↓
Organization Scope
↓
Project Scope
↓
Production Scope
↓
Performance Scope

ただし、
すべてのDataが
すべてのScopeを持つわけではない。

各EntityのScopeは、
Domain Ownershipに応じて定義する。

---

# 61. Organization Scope

Organization Scopeは、
StageArtの主要Tenant Boundaryである。

通常Userは、
自身がMembershipを持つOrganizationの
Business Dataだけを参照できる。

Organization IDは、
Scope Isolationに利用する。

Organization Scope外のDataを、
Resource IDだけで取得できない構造とする。

---

# 62. Project Scope

Projectは、
Organization内部の上位Business Contextである。

Project Scopeを持つDataは、

Organization
↓
Project
↓
Data

というRelationshipで
Scopeを確認できる。

Project Scopeは、
Organization Scopeの下位Scopeである。

---

# 63. Production Scope

Production Scopeは、
Productionに関連するDataを
管理するScopeである。

例えば、

- Participant
- ProductionDelegate
- Rehearsal
- Performance
- Ticket
- Reservation
- Check In

など。

Production Scope外のDataへ
通常Userがアクセスできないようにする。

---

# 64. Performance Scope

Performance Scopeは、
特定の公演回に関連する
受付・Ticket・Reservationなどを
管理するScopeである。

例えば、

Performance
↓
Reservation
↓
Check In

という構造。

Reception Operatorは、
許可されたPerformance Scopeに対してのみ
受付Operationを実行できる。

---

# 65. System Administrator Data Scope

System Administratorは、
全Organizationを選択できる
System-level Accessを持つ。

ただし、
Organization Selection自体は、
Business Factではない。

基本構造：

System Administrator
↓
Organization List
↓
Selected Organization
↓
Organization Context

Selected Organization Contextは、
Application Authorization Contextとして扱う。

Organizationを選択した後は、
そのOrganizationの
Organization Administrator相当のScopeで
Business Dataへアクセスする。

---

# 66. Selected Organization Context

Selected Organization Contextは、
System Administratorが
特定Organizationを操作対象として
選択した状態を表す。

これは、
Organization Membershipそのものを
新たに生成するBusiness Factではない。

基本構造：

System Administrator
↓
Selected Organization
↓
Organization Administrator Context
↓
Business Operation

Contextは、
Request / Session / Application Operationなどの
Runtime Contextとして管理できる。

必要な場合は、
AuditにSelected Organizationを記録する。

---

# 67. System Administrator and Data Ownership

System Administratorであっても、
Business DomainのData Ownershipを
変更しない。

System Administratorが
Organization Aを選択した場合、

Organization A
↓
通常のOrganization Scope
↓
通常のDomain Ownership

という構造を利用する。

System Administratorだからといって、
すべてのDomain Dataを
直接書き換える権限を
Data Layerに与えない。

---

# 68. Data Access Isolation

Data Accessでは、
以下のScopeを考慮する。

- Actor
- Organization
- Project
- Production
- Performance
- Role
- Permission
- Selected Organization Context

Resource IDを知っているだけでは、
Accessを許可しない。

Data AccessのScope判定は、
Application Authorizationと
Persistence Queryの双方で考慮する。

---

# 69. Cross Organization Isolation

通常Userは、
所属していないOrganizationの
Business Dataを参照できない。

例えば、

Organization A User
↓
Organization B Reservation

というAccessは拒否する。

Organization AとBの
同一人物が別Membershipを持っている場合でも、
各Membershipに基づいてScopeを判定する。

---

# 70. Cross Production Isolation

Production Scopeを必要とするDataについては、
Production AのUserが
Production BのDataへ
自動的にアクセスできない。

例えば、

Production A
↓
Reservation A

Production B
↓
Reservation B

が存在する場合、

Production A Scope
→ Reservation A

Production B Scope
→ Reservation B

と分離する。

---

# 71. Data Reference

Domain間でDataを参照する場合は、
Business Identityまたは
明示的なReferenceを利用する。

例えば、

Reservation
→ Performance ID

Reservation
→ Person ID

Check In
→ Reservation ID

Issued Ticket
→ Reservation ID

など。

他DomainのEntity内部構造を
直接埋め込まない。

---

# 72. Data Duplication

Read Performanceや
外部連携のために、
必要なDataをProjectionとして
複製することは許容する。

ただし、
ProjectionはSource of Truthではない。

基本構造：

Source Domain
↓
Domain Event
↓
Projection
↓
Read Model

Projectionの更新が遅れても、
Business Factそのものを
変更しない。

---

# 73. Read Model

Read Modelは、
QueryやList表示に適した
Data Projectionである。

例えば、

- Check In List
- Reservation Search
- Performance Dashboard
- Rehearsal Schedule
- Mobile Home
- Production Dashboard

など。

Read Modelは、
複数DomainのDataを
表示目的で組み合わせることができる。

---

# 74. Read Model and Source of Truth

Read Modelは、
Business Factの正本ではない。

例えば、

Check In List
↓
Check In Read Model

であっても、

Check In Read Model
≠
Check In Source of Truth

である。

Check In実行時には、
必要に応じて最新Business Factを
再検証する。

---

# 75. Mobile Read Model

Mobile ClientのNormal Modeでは、
現場確認に必要な情報を
Read Modelとして提供できる。

例えば、

- Today's Rehearsal
- Upcoming Rehearsal
- My Schedule
- Production Information
- Performance Information
- Communication

など。

Mobile Read Modelは、
既存Business Factを
Mobile向けにProjectionしたものとする。

---

# 76. Check In Read Model

Web Check In画面では、
受付用Read Modelを利用できる。

例えば、

Performance
+
Reservation
+
Person
+
Issued Ticket
+
Check In Status

をまとめて表示する。

ただし、
表示されたCheck In Statusを
そのままUpdateの根拠として
無条件に信頼しない。

Check In実行時に、
最新Business Factを再検証する。

---

# 77. Cache Data

Cacheは、
Performance改善のために利用できる。

Cacheは、
Business Factの正本ではない。

特に、

- Check In
- Reservation Status
- Ticket State
- Accounting State

などConsistencyが重要なDataについては、
CacheのStalenessを考慮する。

---

# 78. External Data

External Serviceから取得したDataは、
そのままStageArt Business Factとはしない。

基本構造：

External Data
↓
Integration Mapping
↓
StageArt Domain Data

External DataのIDは、
必要に応じてExternal Referenceとして
保存する。

---

# 79. External Reference

External Referenceは、
StageArt Dataと
External Service Dataの関係を表す。

例えば、

Document
↓
External Storage Reference

Person
↓
External Identity

Announcement
↓
External Delivery Reference

など。

External Referenceは、
StageArt Business Identityとは
分離する。

---

# 80. External Service Failure

External Serviceが停止しても、
StageArtのCore Business Factを
不必要に失わせない。

例えば、

Check In
↓
CheckInCompleted
↓
External Integration

の後段Integrationが失敗しても、
すでに確定したCheck In Factを
自動的に消去しない。

必要に応じて、
Retry / Recoveryを行う。

---

# 81. Audit Data

Audit Dataは、
Business Factとは分離する。

Audit対象には、

- Login
- Authorization Change
- Organization Selection
- Data Change
- Check In
- Reservation Change
- Ticket Operation
- Backup
- Restore
- Replication
- Recovery
- Configuration Change

などを含めることができる。

Auditは、
「何が起きたか」を記録するための
Operational Dataである。

---

# 82. Audit and Business Fact

Audit Logは、
Business Factの代替ではない。

例えば、

Check In
↓
Audit Log

としても、

Audit Log
≠
Check In

である。

Business Factは、
Domain Dataとして管理する。

Auditは、
Operation Contextとして管理する。

---

# 83. Audit Actor

Auditでは、
必要に応じて、

- Actor
- Organization
- Production
- Performance
- Operation
- Resource
- Source
- Timestamp

などを記録する。

System Administratorの場合は、
Selected Organization Contextを
Auditへ記録できる。

---

# 84. System Operational Data

System Operationsでは、
Business Dataとは別に
Operational Dataを管理する。

例えば、

- Backup Metadata
- Replication Status
- Mirror Status
- Recovery History
- Job Status
- System Health
- Application Error
- Deployment History

など。

Operational Dataは、
Business Domain Dataの
一部として扱わない。

---

# 85. Backup Data

Backupは、
Business DataのRecovery用Copyである。

基本構造：

Primary Data
↓
Backup
↓
Backup Storage

Backup Dataは、
通常運用時のBusiness Factの
別Source of Truthではない。

BackupからRestoreした後、
Consistency Checkを行い、
復旧したDatabase / Storageを
新しいPrimaryとして利用する。

---

# 86. Backup and History

Backupは、
History Dataそのものではない。

Backup：

障害時にDataを復旧するためのCopy。

History：

Business上の過去のActivityやStateを表すData。

両者を同一概念として扱わない。

---

# 87. Replication Data

Replicationは、
Primary DataをMirror Environmentへ
同期するためのData Flowである。

基本構造：

Primary
↓
Replication
↓
Mirror

ReplicationされたDataは、
Mirror Environmentの
Operationally AvailableなCopyである。

---

# 88. Mirror Data

Mirror Dataは、
Primary Dataの代替Copyである。

通常運用時は、
Primary DataをSource of Truthとする。

Failoverした場合には、
Mirrorを新しいPrimaryとして
昇格させることができる。

具体的なFailover方式は、
Operations Architectureで定義する。

---

# 89. Backup and Mirror Separation

BackupとMirrorは、
目的を分離する。

Mirror：

- Availability
- Failover
- Service Continuity

Backup：

- Data Recovery
- Point-in-Time Recovery
- Disaster Recovery

MirrorだけをBackupの代わりにしない。

BackupだけをMirrorの代わりにしない。

---

# 90. Recovery Data

Recoveryでは、
BackupまたはMirrorから
StageArt Dataを復旧する。

基本構造：

Backup
↓
Restore
↓
Consistency Check
↓
Recovered Primary

または、

Primary Failure
↓
Mirror
↓
Failover
↓
Recovered Service

Recovery後には、
Data Consistencyを確認する。

---

# 91. File Data and Database Data

StageArtでは、
Database DataとFile Dataを
必要に応じて分離する。

Database：

- Business Entity
- Relationship
- State
- Reference
- Metadata

File Storage：

- Document Binary
- Image
- Other Media

DatabaseとFile Storageの
Consistencyを考慮する。

---

# 92. Data Integrity

Data Integrityは、
ApplicationとDatabaseの双方で保証する。

Application：

- Business Rule
- Authorization
- State Transition
- Cross Domain Validation

Database：

- Primary Key
- Foreign Key
- Unique Constraint
- Not Null
- Transaction
- Referential Integrity

など。

Database Constraintだけに
Business Ruleを依存しない。

---

# 93. Referential Integrity

Domain Referenceを持つDataについては、
参照整合性を維持する。

例えば、

Reservation
→ Performance

Check In
→ Reservation

Issued Ticket
→ Reservation

など。

ただし、
Domain境界を越えた直接Foreign Keyの
具体的な採用については、
Implementation Specificationで決定する。

---

# 94. Soft Delete

Business Dataの削除では、
必要に応じてSoft Deleteや
状態変更を利用する。

特に、

- Reservation
- Ticket
- Check In
- Accounting
- Audit

など、
履歴・整合性が重要なDataについては、
物理削除を慎重に扱う。

具体的なDeletion Policyは、
Domainごとに定義する。

---

# 95. Business Fact Immutability

確定済みBusiness Factについては、
後から意味を変えるような
直接上書きを避ける。

例えば、

Journal Entry
Check In
Audit Log

など。

訂正が必要な場合は、
Domain Ruleに従って
Correction / Reversal / New Factなどを
利用する。

---

# 96. Check In Immutability

Check Inが確定した後、
そのFactをClient側から
直接書き換えない。

例えば、

Mobile Client
↓
Local State変更

によって、
Check Inを取消・変更しない。

取消やCorrectionが必要な場合は、
Server SideのBusiness Operationを
利用する。

---

# 97. Reservation and Check In Lifecycle

基本的なLifecycleは、

Performance
↓
Reservation
↓
Issued Ticket
↓
Check In

とする。

ただし、

Issued Ticketが必須でない受付経路では、

Performance
↓
Reservation
↓
Check In

も可能とする。

したがって、

Reservation
↓
Check In

がCanonical Relationshipであり、

Reservation
↓
Issued Ticket
↓
Check In

は受付方法の一つである。

---

# 98. Check In and QR Lifecycle

QR受付の場合、

Ticket
↓
Issued Ticket
↓
QR Ticket
↓
QR Code
↓
Scan
↓
Issued Ticket Resolution
↓
Reservation Resolution
↓
Check In

となる。

QR Codeは、
このLifecycleの中の
識別Artifactである。

QR Codeそのものが、
Check In Factを生成したことを
意味しない。

---

# 99. Data Transaction Boundary

Transaction Boundaryは、
Application Use Caseと整合させる。

代表例：

Check In
↓
Transaction

Reservation Confirmation
↓
Transaction

Ticket Issuance
↓
Transaction

Journal Entry
↓
Transaction

複数のBusiness Operationを
不必要に一つの巨大Transactionへ
まとめない。

---

# 100. Check In Transaction

Check Inでは、
必要なDataを一貫したTransactionで
確定する。

概念的には、

Load Reservation
↓
Validate
↓
Check Existing Check In
↓
Create Check In
↓
Persist
↓
Commit

という構造。

Concurrent Check Inへの対策は、
Application / Database双方で行う。

---

# 101. Event and Data Consistency

Business Fact確定後に、
Domain Eventを発行できる。

例えば、

Check In
↓
CheckInCompleted

CheckInCompletedを起点として、

- Audience History
- Accounting
- Notification
- Reporting

などを更新できる。

後続Projectionが遅延しても、
元のBusiness Factを変更しない。

---

# 102. Eventual Consistency

Read ModelやProjectionでは、
Eventual Consistencyを許容できる。

例えば、

Check In
↓
CheckInCompleted
↓
Check In Read Model

という場合、
一時的にRead Modelが遅れてもよい。

ただし、
受付結果など、
即時Consistencyが必要なOperationでは、
最新のSource of Truthを利用する。

---

# 103. Data Migration

Data Schema変更では、
既存Business Factを壊さない。

Migrationでは、

- Schema Migration
- Data Migration
- Backfill
- Validation
- Rollback Strategy

などを考慮する。

Migrationは、
Application Versionと
Data VersionのCompatibilityを考慮する。

---

# 104. Data Versioning

以下のようなDataでは、
Versionを持つことを検討する。

- Regulation
- Document
- Public Page
- Configuration
- Price
- Other Versioned Business Data

VersioningとHistoryを
同一概念として扱わない。

---

# 105. Data Export

Business Dataを
Exportする場合でも、
Scopeを維持する。

例えば、

Organization Export
↓
Organization Scope

Production Export
↓
Production Scope

Audience Export
↓
Authorized Audience Scope

など。

System Administratorが
Organizationを選択して
Exportする場合も、
Selected Organization Contextを
利用する。

---

# 106. Data Import

Data Importでは、
External Dataを
そのままBusiness Factとしない。

基本構造：

Import File
↓
Validation
↓
Mapping
↓
Domain Operation
↓
Business Fact

Import時にも、
Authorization Scopeを確認する。

---

# 107. Data Privacy

Person、
Reservation、
Audience HistoryなどのDataは、
必要なScopeでのみ参照できる。

Public Clientへ返すDataは、
Public Projectionを利用する。

Internal Management Dataを、
そのままPublic Dataとして
公開しない。

---

# 108. Personal Data Separation

PersonのPersonal Dataと、
Production / Performanceの
Business Dataを適切に分離する。

例えば、

Person
→ Identity

Reservation
→ Performanceに対する予約

Check In
→ Reservationに対する受付

というOwnershipを維持する。

Person情報を、
ReservationやCheck Inに
無制限に複製しない。

必要な場合は、
ReferenceまたはSnapshotを利用する。

---

# 109. Snapshot Data

Business Transactionに必要な
過去時点の情報については、
Snapshotを保持できる。

例えば、

- Ticket Price Snapshot
- Booker Information Snapshot
- Address Snapshot
- Other Transactional Snapshot

など。

Snapshotは、
現在のMaster Dataとは異なる。

---

# 110. Current Data and Snapshot

Current Master Data：

現在の最新情報。

Snapshot：

Transaction時点の情報。

例えば、

Person
↓
Current Profile

Reservation
↓
Booker Snapshot

という構造を利用できる。

過去Transactionの意味が、
現在Profileの変更によって
変わらないようにする。

---

# 111. Data Architecture and Application Architecture

Application Architectureでは、

Client
↓
API
↓
Application
↓
Domain
↓
Persistence

というLayerを定義する。

Data Architectureでは、
そのApplicationが扱うDataについて、

- Ownership
- Source of Truth
- Relationship
- Scope
- Integrity
- Persistence

を定義する。

Application Use Caseは、
Data Ownershipを越えて
直接Dataを書き換えない。

---

# 112. Data Architecture and Authorization

Authorizationは、
Data Scopeと連携する。

基本構造：

Person
↓
Membership
↓
Organization
↓
Project
↓
Production
↓
Performance
↓
Resource

System Administratorの場合：

System Administrator
↓
Organization Selector
↓
Selected Organization
↓
Organization Administrator Context
↓
Resource

Selected Organization Contextは、
Data Ownershipそのものではない。

---

# 113. System Administrator Data Access

System Administratorが
Organization Aを選択した場合、

Selected Organization = A

として、

Organization A
↓
Project
↓
Production
↓
Performance
↓
Reservation
↓
Check In

という通常のScopeを利用する。

Organization BのDataは、
別途Organization Bを選択しない限り、
通常のManagement Contextから
混在して表示しない。

---

# 114. System Administrator and Cross Organization View

System-wide Dashboardなど、
System Administrator専用の
集計情報を提供することはできる。

ただし、
個々のOrganization Business Dataを
無制限に横断表示することとは分離する。

System-wide View：

- Organization Count
- Production Count
- System Health
- Backup Status
- Replication Status

など。

Organization Business Data：

Organization Selector
↓
Selected Organization
↓
Management Client

という構造で扱う。

---

# 115. Operational Data Ownership

System Operational Dataは、
Business Domain Dataとは分離する。

例えば、

Backup Status
→ System Operations

Replication Status
→ System Operations

Recovery History
→ System Operations

Audit Log
→ System Operations / Audit

とする。

これらを、
Organization Business Dataの
Entityとして扱わない。

---

# 116. Operational Data and Organization Scope

Operational Dataの中には、
Organizationと関連するものが存在する。

例えば、

Organization Export Job
Organization Backup Metadata

など。

この場合でも、

System Operational Data
+
Organization Reference

として扱う。

Operational Dataが、
Organization Business Factそのものになるわけではない。

---

# 117. Database Persistence

Databaseは、
StageArt Business Dataの
主要Persistenceとして利用する。

Databaseには、

- Entity
- Relationship
- State
- Reference
- Transaction Data

などを保存する。

Database Schemaは、
Domain Modelと同一ではない。

---

# 118. File Storage Persistence

File Storageには、

- Document
- Image
- Media
- Export File
- Other Binary Data

などを保存できる。

Databaseには、
必要なMetadataと
Storage Referenceを保存する。

---

# 119. Backup Persistence

Backup Storageは、
Primary Database / File Storageとは
別のRecovery Boundaryとして
構成する。

基本構造：

Primary Database
↓
Backup
↓
Backup Storage

Primary File Storage
↓
Backup
↓
Backup Storage

DatabaseとFile Storageの
Backup Consistencyを考慮する。

---

# 120. Data Integrity and Backup

Backupが成功しただけでは、
Recovery可能性を保証したとはみなさない。

必要に応じて、

Backup
↓
Validation
↓
Restore Test
↓
Recovery Verification

を行う。

具体的な運用Policyは、
Operations Architectureで定義する。

---

# 121. Data Retention

Data Retentionは、
Domainごとに定義する。

例えば、

- Business Data
- Audit Data
- Accounting Data
- Backup Data
- Operational Log
- Temporary Data

では、
保持期間が異なる可能性がある。

Retention Policyは、
Security / Operations / Domain Policyと
整合させる。

---

# 122. Data Deletion

Data Deletionでは、

- Business Rule
- Referential Integrity
- Audit
- History
- Accounting
- Legal / Operational Retention

を考慮する。

特にAccountingやAuditについては、
単純な物理削除を行わない。

---

# 123. Data Correction

Business Factに誤りがある場合でも、
Databaseを直接修正することを
通常のBusiness Operationとしない。

基本構造：

Correction Request
↓
Application Operation
↓
Domain Rule
↓
Correction / Reversal / New Fact

System Administratorであっても、
Business Ruleを迂回した
直接Database Updateを
通常Operationとして提供しない。

---

# 124. Data Consistency Rules

以下のConsistencyを維持する。

- Reservationは正しいPerformanceに属する。
- Issued Ticketは正しいReservationに関連する。
- Check Inは正しいReservationに関連する。
- Check Inは正しいPerformance Contextを持つ。
- Organization Scopeを越えたAccessを許可しない。
- Production Scopeを越えたAccessを許可しない。
- Check Inの重複を防ぐ。
- Accounting Dataと元Business FactのReferenceを維持する。
- HistoryがCurrent Stateを勝手に変更しない。
- Read ModelがSource of Truthにならない。
- External DataがBusiness Factを上書きしない。

---

# 125. Canonical Relationship Summary

StageArtの主要Relationshipは、

Organization
↓
Project
↓
Production
↓
Performance
↓
Reservation
↓
Check In

を基本とする。

Ticket系では、

Performance
↓
Ticket
↓
Issued Ticket
↓
Reservation

という関係を持つ。

QR受付では、

Issued Ticket
↓
QR Ticket
↓
QR Code

というArtifact Relationshipを持つ。

したがって、

Reservation
↓
Check In

がCheck InのCanonical Relationshipであり、

QR Code
↓
Check In

という直接のDomain Relationshipは持たない。

---

# 126. Check In Data Model Summary

Check Inの基本Data構造：

Reservation
↓
Check In

必要に応じて、

Check In
├── Performance Reference
├── Person / Booker Reference
├── Issued Ticket Reference
├── Actor Reference
├── Source
└── Timestamp

などを持つ。

Issued Ticketは、
Check Inの入力経路として利用できるが、
Check Inそのものではない。

---

# 127. Check In and Mobile Architecture

Mobile Clientでは、

Mobile
↓
Reception Mode
↓
QR Scan
↓
Issued Ticket Resolution
↓
Reservation Resolution
↓
Check In

というData Flowを利用する。

Mobile Local Stateは、
Check In Business Factの正本ではない。

---

# 128. Check In and Web Architecture

Web Clientでは、

Web
↓
Performance
↓
Reservation List
↓
Reservation Selection
↓
Reservation Resolution
↓
Check In

というData Flowを利用する。

Web Read Modelは、
Check In Business Factの正本ではない。

---

# 129. Data Flow Principle

StageArtのData Flowでは、

Input
↓
Resolution
↓
Authorization
↓
Business Operation
↓
Business Fact
↓
Projection / Event / Integration

という順序を基本とする。

Client Inputを
そのままBusiness Factとして保存しない。

---

# 130. Data Architecture Rules

Data Architectureでは、
以下を禁止または原則禁止とする。

- Client StateをBusiness Factの正本とすること
- QR CodeをCheck In Factとして扱うこと
- Issued TicketをCheck Inと同一視すること
- Read ModelをSource of Truthとして扱うこと
- CacheをBusiness Factの正本とすること
- External Service DataをBusiness Factの正本とすること
- Domain AがDomain BのDataを直接更新すること
- Resource IDだけでScope外Dataへアクセスできる構造
- System Administratorだからという理由だけでDatabaseを直接更新すること
- Backup Dataを通常Business Dataと同一視すること
- Mirror Dataを常時Primary Dataとして扱うこと
- HistoryをCurrent Stateの代替として扱うこと
- Audit LogをBusiness Factの代替として扱うこと
- Accounting DataをReservation / Check Inの単純な属性として扱うこと
- Clientごとに別のCheck In Business Factを持つこと
- Web Check InとMobile Check Inで異なるCheck In Data Modelを作ること

---

# 131. Data Architecture Summary

StageArtのData Architectureでは、

Business Fact
↓
Domain Ownership
↓
Persistence

という構造を基本とする。

主要なBusiness Scopeは、

Organization
↓
Project
↓
Production
↓
Performance

である。

通常Userは、
自身が所属するOrganization、
および許可されたProject / Production /
Performance ScopeのDataだけを
参照・操作できる。

System Administratorは、
全Organizationを選択できる。

ただし、
System Administratorによる
Organization Selectionは、
Business Data Ownershipを変更するものではない。

System Administratorは、

System Administration
↓
Organization List
↓
Organization Selection
↓
Selected Organization Context
↓
Organization Administrator相当
↓
通常Management Client

というFlowを利用する。

選択されたOrganizationのBusiness Dataは、
通常のOrganization Scope、
Production Scope、
Performance Scopeを通して管理する。

Check Inについては、

Reservation
↓
Check In

をCanonical Relationshipとする。

Check Inは、
Performanceに対するReservationの
受付というBusiness Factである。

Issued Ticketは、
発行されたTicketを表すBusiness Dataであり、
Check Inそのものではない。

QR Ticket / QR Codeは、
Issued Ticketを識別するための
受付Artifactである。

QR受付では、

QR Code
↓
Issued Ticket
↓
Reservation
↓
Check In

というResolutionを行う。

Web受付では、

Reservation Search / Manual Selection
↓
Reservation
↓
Check In

というResolutionを行う。

Mobile ClientとWeb Clientは、
異なる入口を持つが、
最終的には同一のCheck In Business Factを生成する。

Mobile Clientは、
受付専用Applicationではない。

公演関係者が日常的に利用する
Mobile Applicationとして、

- Rehearsal
- Production
- Performance
- Schedule
- Communication

などの情報を扱う。

必要な場合のみ、

Mobile Client
↓
Reception Mode
↓
Check In

として利用する。

Read Model、
Cache、
External Data、
Audit、
History、
Backup、
Mirrorなどは、
Business Factの正本とは分離する。

Read Modelは、
検索・一覧・Dashboard・Mobile表示などの
Query用途に利用する。

Cacheは、
Performance向上のために利用する。

Auditは、
Operationの記録として利用する。

Historyは、
過去のBusiness Activityを管理する。

Backupは、
RecoveryのためのCopyである。

Mirrorは、
Availability / FailoverのためのCopyである。

External Dataは、
必要に応じてMappingして
StageArt Business Dataへ取り込む。

Accounting Dataは、
Accounting DomainがOwnershipを持つ。

Document Binaryは、
必要に応じてExternal Storageへ保存し、
StageArt側ではDocument Metadataと
External Storage Referenceを管理する。

Data Integrityは、

Application
+
Database

の双方で保証する。

特に、

- Organization Scope
- Production Scope
- Performance Scope
- Reservation Integrity
- Ticket Integrity
- Check In Uniqueness
- Accounting Integrity

を重要なConsistency Boundaryとする。

Data Architectureの最重要原則は、

「Business Factの正本をDomainに保持し、
Client、
Read Model、
Cache、
External Service、
Artifact、
History、
Audit、
Backup、
Mirrorなどの
周辺Dataと明確に分離する」

ことである。

また、

「Reservation → Check Inを
Check InのCanonical Relationshipとし、
Issued TicketやQR Codeを
Check Inそのものとしない」

ことを、
StageArtの受付Data Modelにおける
基本原則とする。

さらに、

「Web ClientとMobile Clientは
同一のCheck In Business Factを生成し、
受付方法だけを異なる入口として扱う」

ことを、
Data Architectureの基本方針とする。

そして、

「System Administratorは全Organizationを選択できるが、
選択後のBusiness Dataは通常の
Organization / Production / Performance Scopeを通して扱う」

ことを、
System Administrationにおける
Data Scopeの基本方針とする。

---
