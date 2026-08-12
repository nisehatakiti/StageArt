# StageArt Blueprint

# 10 - Architecture
# Data Architecture

Version : 1.1

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

を定義する。

Data Architectureでは、
具体的なDatabase TableやColumnをまだ確定しない。

Database Schemaは、
本Architectureを基準として
Implementation Specificationで定義する。

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
- Organization ScopeをTenant Boundaryとして扱う。
- Production Scopeを必要に応じてAuthorization Boundaryとして扱う。
- External ServiceをBusiness Factの正本にしない。
- ArtifactとBusiness Factを分離する。
- Historyを現在状態の代替として扱わない。
- Accounting Factを通常のBusiness Dataと混同しない。
- Audit DataとBusiness Factを分離する。
- Client側のStateをBusiness Factの正本にしない。
- Transaction BoundaryをApplication Use Caseと整合させる。
- Data IntegrityをApplicationとDatabaseの双方で保証する。
- Check InはClient固有のDataではなく、Server Sideで確定するBusiness Factとして管理する。
- Web ClientとMobile Clientは、同一のCheck In Business Factを生成する。
- QR CodeはCheck Inそのものではなく、Issued Ticketを識別するArtifactとして扱う。

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

などを利用する。

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

---

# 5. Identity Data

Identityは、
StageArtにおけるBusiness Identityを管理する。

基本構造：

UserAccount
↓
Person
↓
Profile
↓
HistoricalActivity

UserAccountは、
ApplicationへのLogin Identityを表す。

Personは、
StageArt Business DomainにおけるPersonを表す。

Profileは、
Personに関する現在のProfile情報を表す。

HistoricalActivityは、
Personの過去の活動実績を表す。

---

# 6. Person

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
WordPress UserなどのExternal Identityと同一視しない。

---

# 7. Profile

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
Historical Factそのものを直接保持する場所ではない。

---

# 8. HistoricalActivity

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

HistoricalActivityの具体的な属性は、
Domain Model / Implementation Specificationで定義する。

---

# 9. Organization Data

Organizationは、
StageArtにおける主要なTenant Boundaryである。

Organizationに属するDataは、
原則としてOrganization Scopeを持つ。

例：

Organization
├── Membership
├── Project
├── Production
├── Equipment
├── Regulation
├── Communication
└── Accounting

Organization IDは、
Scope判定に必要なDataとして扱う。

---

# 10. Membership

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

---

# 11. Organization Role

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

---

# 12. Production Data

Productionは、
Organization内で行われる具体的な
舞台活動単位を表す。

基本構造：

Organization
↓
Production
├── Participant
├── ProductionDelegate
├── Performance
├── Rehearsal
├── Ticket
├── Reservation
├── Check In
└── Accounting

Productionは、
Organization Scopeに属する。

---

# 13. Production Scope

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

---

# 14. Participant

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

# 15. ProductionDelegate

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

---

# 16. Performance

Performanceは、
Production内の具体的な公演回を表す。

基本構造：

Production
↓
Performance
├── Ticket
├── Reservation
└── Check In

Performanceは、
TicketやReservationが
どの公演回を対象としているかを
明確にする。

---

# 17. Ticket Data

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

特定購入者・Reservationに対して発行されたTicket。

QR Ticket：

Issued Ticketを識別するArtifact。

---

# 18. Reservation

Reservationは、
観客がPerformanceに対して行った
予約を表す。

基本構造：

Person
↓
Reservation
↓
Performance

必要に応じて、

Reservation
↓
Issued Ticket

という関係を持つ。

ReservationとIssued Ticketは、
同じ概念として扱わない。

---

# 19. Issued Ticket

Issued Ticketは、
実際に発行されたTicketを表す。

基本構造：

Ticket
↓
Issued Ticket
↓
Reservation
↓
Person

Issued Ticketは、
Check Inの対象となる。

Ticketそのものと、
実際に発行されたTicketを分離することで、

- 販売Ticket
- 購入
- 発行
- 利用

を区別できる。

---

# 20. QR Ticket

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
Issued Ticketそのものは維持できる構造とする。

QR Codeの利用は、
Mobile Clientに限定されるものではなく、
将来的に別のClientや受付端末から
利用される可能性もある。

ただし、
QR Codeを読み取るDevice側で
Check In Factを確定してはならない。

---

# 21. Check In

Check Inは、
Issued Ticketが実際に利用されたという
Business Factを表す。

基本構造：

Issued Ticket
↓
Check In

Check Inは、

「Ticketを購入した」

とは異なる。

Check Inは、

「実際に受付された」

という事実を表す。

Check Inは、
Web ClientまたはMobile Clientなど、
複数のClientから実行できる。

ただし、
どのClientから実行された場合でも、
生成されるCheck In Business Factは同一のものとする。

---

# 22. Check In Ownership

Check In Dataは、
Check In DomainがOwnershipを持つ。

Ticket DomainやReservation Domainが、
Check In Factを直接更新しない。

Check Inを発生させるOperationは、
Application Layerから実行する。

Web ClientからのCheck In：

Web Client
↓
Reservation / Issued Ticket List
↓
対象Ticket選択
↓
Check In Use Case
↓
Check In

Mobile ClientからのCheck In：

Mobile Client
↓
QR Scanner
↓
Issued Ticket Identifier
↓
Check In Use Case
↓
Check In

どちらの場合も、
最終的なCheck In Factは
Server Sideで確定する。

---

# 23. Check In and History

Check Inが確定すると、

CheckInCompleted

を発生させる。

基本構造：

Check In
↓
CheckInCompleted
↓
Audience History

Historyは、
Check InのBusiness Factを起点として
観劇実績を記録する。

Ticket購入だけでは、
観劇履歴を確定しない。

Web ClientからのManual Check Inでも、
Mobile ClientからのQR Check Inでも、
同じCheckInCompletedを起点とする。

---

# 24. Audience History

Audience Historyは、
Personの観劇実績を管理する。

基本構造：

Person
↓
Check In
↓
Audience History

Audience Historyは、
ReservationやTicketの現在状態を
代替するものではない。

「購入した」
と
「観劇した」
を区別するためのDataとして扱う。

---

# 25. History Data Principle

Historyは、
現在状態を保存するための
Generic Logとして扱わない。

Historyは、
Business上意味のある過去のFactを
記録する。

例えばAudience Historyは、

「このPersonが、
このPerformanceを観劇した」

というFactを表す。

---

# 26. Rehearsal Data

Rehearsal関連Dataは、
候補と確定した稽古を分離する。

基本構造：

Rehearsal Candidate
↓
Rehearsal Availability
↓
Rehearsal
↓
Rehearsal Attendance

Candidate：

稽古候補。

Availability：

参加可能状況。

Rehearsal：

確定した稽古。

Attendance：

実際の参加状況。

---

# 27. Rehearsal Attendance

Rehearsal Attendanceは、
PersonがRehearsalに参加したという
Business Factを表す。

基本構造：

Person
↓
Rehearsal Attendance
↓
Rehearsal

Rehearsalへの参加予定と、
実際の参加結果を分離する。

---

# 28. Accounting Data

Accountingは、
Financial Business Factを管理する。

基本構造：

Business Event
↓
Accounting
↓
Journal Entry
↓
Journal Entry Line

Accounting Dataは、
単純なProductionの属性として
保持しない。

---

# 29. Journal Entry

Journal Entryは、
会計上のTransactionを表す。

Journal Entryは、
複数のJournal Entry Lineから構成される。

基本構造：

Journal Entry
├── Journal Entry Line
├── Journal Entry Line
└── Journal Entry Line

Debit / Creditの整合性は、
Accounting Domainで保証する。

---

# 30. Ticket Revenue

Ticket Revenueは、
Ticket販売・利用に関する
Accounting Factとして扱う。

Check Inを契機として、
必要なRevenue処理を実行する。

基本構造：

CheckInCompleted
↓
Ticket Revenue
↓
Journal Entry
↓
Journal Entry Line

具体的な勘定科目や
売上認識ルールは、
Accounting Domainで定義する。

---

# 31. Check In and Accounting

Check InとAccountingは、
同一Domainに統合しない。

Check In：

観客が実際に受付されたというFact。

Accounting：

会計上のRevenue / Journalを管理する。

連携：

Check In
↓
CheckInCompleted
↓
Accounting Process
↓
Ticket Revenue
↓
Journal Entry

この構造によって、
Check In Domainが
会計内部構造を知る必要がなくなる。

Web ClientからのCheck Inでも、
Mobile ClientからのCheck Inでも、
Accounting Processは同一とする。

---

# 32. Production Accounting

Production単位の会計情報は、
Accounting Domainで管理する。

主なData：

- Budget
- Budget Item
- Production Actual
- Budget vs Actual
- Production Settlement

Productionは、
Accounting Dataを直接所有しない。

ProductionとAccountingは、
Production IDなどのReferenceによって関連付ける。

---

# 33. Budget

Budgetは、
計画された金額を表す。

Budgetは、
Actualと分離する。

基本構造：

Production
↓
Budget
↓
Budget Item

Budgetは、
実際のJournal Entryを表すものではない。

---

# 34. Production Actual

Production Actualは、
Productionに関連する実績を表す。

Accounting Factとの関係を明確にし、

Budget
≠
Production Actual
≠
Journal Entry

として扱う。

必要な集計は、
Accounting Query / Projectionによって行う。

---

# 35. Production Settlement

Production Settlementは、
Production単位の最終的な収支を表す。

Settlementは、
複数のAccounting Factを集計した
Business Resultとして扱う。

基本構造：

Production
↓
Accounting Facts
↓
Production Settlement

Settlementの詳細な計算Ruleは、
Accounting Domainで定義する。

---

# 36. Communication Data

Communication Domainでは、
AnnouncementをBusiness Factとして管理する。

基本構造：

Announcement
↓
Announcement Recipient
↓
Announcement Delivery

Announcementは、
「何を伝えたか」を管理する。

Deliveryは、
「誰へ、どのChannelで、どのように送信したか」
を管理する。

---

# 37. Announcement Delivery

EmailなどのExternal Serviceへの送信結果は、
Announcement Deliveryとして記録できる。

External Email ServiceのMessage IDなどは、
Integration Referenceとして保持できる。

External ServiceのMessage自体を、
Announcementの正本にしない。

---

# 38. Document Data

Documentは、
Business ContextとFile Storageを分離する。

基本構造：

Document
↓
Storage Reference
↓
External Storage

Documentは、

- Name
- Type
- Business Context
- Owner
- Scope
- Access

などを管理する。

実ファイルそのものの保存方式は、
Infrastructureで決定する。

---

# 39. Document Share

Document Shareは、
Documentへの共有関係を管理する。

基本構造：

Document
↓
Document Share
↓
Person / Organization / Scope

Document Shareは、
通常のDocument Ownershipとは分離する。

---

# 40. External Storage Reference

External Storage Referenceは、
外部Storage上のFileと
StageArt Documentを関連付ける。

例：

Document
↓
External Storage Reference
↓
External File

External File IDやURLなどは、
Integration Dataとして扱う。

External File IDを、
StageArt Business Identityの代わりにしない。

---

# 41. Promotion Data

Promotion Domainでは、
公開用情報とExternal SNS情報を分離する。

例えば、

Organization Public Profile

Production Public Page

Social Post

などを管理する。

Social Media上のPostは、
StageArtのPromotion FactのExternal Representationである。

---

# 42. Public Data

Public Dataは、
Internal Dataとは分離して扱う。

Public APIでは、
内部Entityを直接返さない。

基本構造：

Internal Domain Data
↓
Public Projection / DTO
↓
Public API
↓
Public Client

これにより、
Internal Dataを誤って公開することを防ぐ。

---

# 43. Equipment Data

Equipmentは、
Organizationが管理する備品を表す。

基本構造：

Organization
↓
Equipment
↓
Equipment History

Equipment Historyは、
備品の過去状態・移動・利用などを記録できる。

Equipmentを、
Accounting Assetと同一概念にはしない。

---

# 44. Regulation Data

Regulationは、
Organizationの規約を管理する。

基本構造：

Organization
↓
Regulation
↓
Regulation Version

Regulation Versionによって、
過去の規約を保持できる。

現在Versionと過去Versionを
区別して管理する。

---

# 45. Survey Data

Survey Domainは、
アンケートと回答を管理する。

基本構造：

Survey
↓
Survey Response
↓
Public Testimonial

Survey Responseは、
回答そのものを表す。

Public Testimonialは、
公開可能な感想として
必要な情報だけを切り出したBusiness Dataとする。

---

# 46. External Reference

External ServiceのIDは、
必要に応じてExternal Referenceとして保持する。

例えば、

- External User ID
- External File ID
- External Calendar Event ID
- External Social Post ID
- External Message ID

など。

External Referenceは、
StageArtのBusiness Identityとは分離する。

---

# 47. External Reference Principle

External Referenceは、
External Serviceとの連携を行うための
Integration Dataである。

External Referenceを、

- Person ID
- Production ID
- Reservation ID
- Ticket ID
- Check In ID

などのStageArt Identityの代わりにしない。

---

# 48. Entity Identity

StageArtのEntityは、
Application内部で安定したIdentityを持つ。

Identityは、
外部サービスやDatabase上のPhysical Structureから
独立させる。

例えばPersonのIdentityは、

Person
→ Person ID

として管理する。

WordPress User IDやExternal Provider IDを、
Person IDとして直接利用しない。

---

# 49. Identifier Principle

Identifierは、
Business IdentityとTechnical Identityを
必要に応じて分離できる設計とする。

Database Primary Keyの具体形式は、
Implementation Specificationで決定する。

Domain Architectureでは、
Entityを一意に識別できることを要求する。

---

# 50. Relationship Principle

Entity間のRelationshipは、
Business Meaningを持つものとして扱う。

例えば、

Person
↓
Membership
↓
Organization

は、
単なるForeign Keyではなく、

「PersonがOrganizationに所属している」

というBusiness Factである。

Database Relationshipだけを見て、
Domain Relationshipを定義しない。

---

# 51. Aggregate Boundary

Domain Entityの中には、
Aggregateとして扱うものが存在する。

Aggregateは、
Business Rule上、
一貫性を維持する単位として定義する。

Aggregate Boundaryを越えた更新は、
Application ProcessやDomain Eventを利用する。

具体的なAggregate定義は、
Domain ModelとImplementation Specificationで確定する。

---

# 52. Aggregate Principle

Aggregate Rootは、
外部から直接内部Entityを変更させない。

基本構造：

Aggregate Root
↓
Child Entity

Child Entityの変更は、
Aggregate Rootを通して行うことを基本とする。

ただし、
すべてのEntityを無理にAggregateへまとめない。

---

# 53. Transaction Boundary

Application Use Caseを、
基本的なTransaction Boundaryとする。

例えば、

Check In

では、

Load Ticket
↓
Validate
↓
Create Check In
↓
Persist
↓
Publish Event
↓
Commit

という一連の処理を、
必要な範囲で一貫して扱う。

---

# 54. Cross Domain Transaction

複数Domainをまたぐ処理では、
すべてを一つの巨大Transactionにしない。

例えば、

Check In
↓
CheckInCompleted
↓
History
↓
Accounting

という処理では、
Check InのBusiness Factを
最初に確定する。

その後のHistory / Accounting処理は、
Event HandlerやApplication Processで
連携できる。

---

# 55. Eventual Consistency

Domain Eventを利用する処理では、
必要に応じてEventual Consistencyを許容する。

例えば、

Check Inが確定した直後に、

History
Accounting

のProjectionや関連処理が
わずかに遅れて反映される可能性がある。

ただし、
Business上即時整合性が必要なDataについては、
同一Transaction内で確定する。

---

# 56. Check In Consistency

Check Inについては、
二重受付を防ぐため、
Server Sideで強いConsistencyを確保する。

複数端末から
同じTicketを同時に読み取った場合でも、

「Check In済み」

という状態が競合しないようにする。

Web ClientからManual Check Inを行う場合と、
Mobile ClientからQR Check Inを行う場合も、
同じConsistency Ruleを適用する。

例えば、

Web Client
↓
Ticket X
↓
Check In

と同時に、

Mobile Client
↓
QR Ticket X
↓
Check In

が実行された場合でも、
Check In Factを二重作成しない。

具体的なDatabase Lockや
Unique Constraintなどは、
Implementation Specificationで定義する。

---

# 57. Idempotency Data

同じRequestやEventが
複数回処理される可能性を考慮する。

対象：

- Check In
- Ticket Revenue
- Journal Entry
- Announcement Delivery
- Calendar Integration
- Social Media Integration

必要に応じて、
Idempotency KeyやExternal Event IDなどを
保持する。

Check Inでは、
Web ClientとMobile Clientの双方から
同一Ticketに対するRequestが送信される可能性を考慮する。

---

# 58. Audit Data

Audit Dataは、
Business Factとは分離する。

Auditは、

- Who
- What
- When
- Scope
- Target
- Result

などを記録する。

例えば、

Person A
↓
Check In Operation
↓
Performance X
↓
Timestamp

という操作履歴を記録できる。

Auditを、
Check In Factそのものとして扱わない。

Web ClientからのCheck Inでも、
Mobile ClientからのCheck Inでも、
必要に応じて同じAudit Ruleを適用する。

---

# 59. Created / Updated Information

Persistent Entityは、
必要に応じて、

- Created At
- Updated At
- Created By
- Updated By

などのMetadataを保持できる。

ただし、
すべてのEntityに同じAudit項目が必要とは限らない。

Business Meaningを持つTimestampは、
通常のUpdated Atとは分離する。

---

# 60. Business Timestamp

例えば、

- Check In Time
- Performance Start Time
- Rehearsal Start Time
- Reservation Time
- Journal Entry Date

などは、
Business Factに固有のTimestampとして管理する。

単純なUpdated Atと混同しない。

---

# 61. Soft Delete

Business Dataの削除については、
EntityごとにRuleを定義する。

すべてのDataに対して、
一律にSoft Deleteを適用しない。

特に、

- Accounting
- Check In
- HistoricalActivity
- Audit

など、
過去のFactとして残す必要があるDataについては、
物理削除を慎重に扱う。

---

# 62. Historical Data

過去のBusiness Factは、
現在状態と分離して保持する。

例えば、

Profile
≠
HistoricalActivity

Reservation
≠
Audience History

Current Regulation
≠
Regulation Version History

というように、
現在の状態と過去のFactを混同しない。

---

# 63. Data Retention

Data Retentionは、
Dataの種類ごとに定義する。

例えば、

Business Fact：

長期保存を基本とする。

Accounting：

法的・業務的要件に従って保存する。

Audit：

必要な期間保持する。

Temporary Data：

必要に応じて削除する。

具体的なRetention Periodは、
別途Policy / Compliance Architectureで定義する。

---

# 64. Personal Data

Personに関連するDataには、
個人情報が含まれる可能性がある。

Data Architectureでは、
必要以上のPersonal Dataを保存しない。

必要なDataだけを保持する。

また、

- Access Control
- Data Scope
- Logging
- Export
- Deletion
- Retention

などを考慮する。

具体的なPrivacy Policyは、
Architectureとは別に定義する。

---

# 65. Data Access Scope

Data Accessは、
Scopeによって制御する。

基本Scope：

- Public
- Person
- Organization
- Production
- System

例えば、

Public Data
→ 誰でも参照可能なData

Person Data
→ 本人中心のData

Organization Data
→ Organization Member / Authorized User

Production Data
→ Production ScopeのAuthorized User

System Data
→ System Administrator

というように、
Data ScopeとAuthorizationを連携させる。

---

# 66. Tenant Isolation

Organization ScopeのDataは、
別Organizationから参照できないことを基本とする。

例えば、

Organization A
↓
Production A
↓
Reservation A

Organization B
↓
Production B
↓
Reservation B

というDataが存在する場合、
Organization AのUserが
Reservation BをID指定だけで取得できないようにする。

---

# 67. Tenant Boundary Enforcement

Tenant Isolationは、
Application LayerとPersistence Layerの
両方で考慮する。

Application：

Authorization Scopeを確認する。

Persistence：

Organization Scopeを条件として
適切にDataを取得する。

IDだけでDataを取得して、
後からAuthorizationを確認する構造を
可能な限り避ける。

---

# 68. Production Isolation

Production Scopeについても、
Authorization Boundaryを維持する。

Production AにDelegationされたPersonが、
Production BのDataへ
自動的にアクセスできるようにはしない。

Production IDを知っているだけでは、
Accessを許可しない。

---

# 69. Public Data Isolation

Public APIからは、
Internal Dataを直接返さない。

基本構造：

Internal Entity
↓
Public Projection
↓
Public DTO
↓
Public API

Public Projectionでは、
公開可能な情報だけを選択する。

---

# 70. Client Data

Web ClientやMobile Clientは、
Business FactのCacheやUI Stateを保持できる。

ただし、
Client DataをSource of Truthとしない。

例えばMobile Clientが、

「このTicketは受付済み」

と保持していても、
Server上のCheck In Factを優先する。

Web Clientが一覧上で、

「受付済み」

と表示していても、
その表示自体をBusiness Factの正本としない。

---

# 71. Offline Data

初期Architectureでは、
Offline状態でのBusiness Fact確定を
基本的に行わない。

特に、

- Check In
- Accounting
- Reservation
- Ticket Issuance

などは、
Server Sideで確定する。

Offline Supportが必要になった場合は、
別途Consistency Architectureを定義する。

---

# 72. Cache Data

Cacheは、
Performance改善のためのTemporary Dataである。

Cacheは、
Business Factの正本ではない。

Cacheが削除されても、

Domain Fact
↓
再取得

によってApplicationを
継続できる構造を目指す。

---

# 73. Read Model

DashboardやReportなどでは、
Read Modelを利用できる。

Read Modelは、

- Search
- Aggregation
- Dashboard
- Report
- Public View

などの用途に利用する。

Read Modelは、
Domain Factから生成する。

---

# 74. Projection

Projectionは、
複数DomainのDataを
表示・検索用にまとめるために利用できる。

例えばProduction Dashboard：

Production
+
Participant
+
Performance
+
Rehearsal
+
Ticket
+
Reservation
+
Accounting

を一つのRead Modelとして
提供できる。

Projectionを、
Business Factの正本にしない。

---

# 75. Database Constraint

Databaseは、
Data Integrityを保証するために利用する。

例：

- NOT NULL
- UNIQUE
- Foreign Key
- Check Constraint

など。

ただし、
複雑なBusiness Ruleを
Database Constraintだけに依存しない。

---

# 76. Application Validation

Application Layerでは、
Use Caseに必要なValidationを行う。

例えば、

- User Permission
- Scope
- Entity Existence
- Operation Eligibility

など。

Business Ruleそのものは、
Domain Layerで検証する。

---

# 77. Domain Validation

Domain Layerでは、
Business Ruleを検証する。

例えばCheck Inなら、

- Ticketが有効か
- Check In可能な状態か
- 既にCheck Inされていないか
- 対象Performanceが正しいか

など。

具体的なRuleは、
Ticket / Reservation / Check In Domainで定義する。

Web ClientからのManual Check Inと、
Mobile ClientからのQR Check Inで、
Business Ruleを分けない。

---

# 78. Persistence Model

Persistence Modelは、
Databaseへ保存するためのModelである。

Persistence Modelは、
Domain Entityと異なる可能性がある。

例えば、

Domain Entity
↓
Repository
↓
Persistence Model
↓
Database

というMappingを行う。

---

# 79. Database Schema Independence

Database Schemaは、
Domain Modelから導出するが、
完全な1対1対応を要求しない。

例えば、

一つのDomain Entityが
複数Tableへ分割されることがある。

逆に、
複数のTechnical Dataを
一つのPersistence Structureへ
まとめる場合もある。

ただし、
Business Ownershipを曖昧にしない。

---

# 80. Migration

Database Schema変更は、
Migrationとして管理する。

Migrationは、
既存Dataを壊さないことを基本とする。

Schema変更時には、

- Existing Data
- Referential Integrity
- Index
- Constraint
- Rollback Strategy

を確認する。

---

# 81. Data Migration

Domain Modelの変更によって、
既存DataのMigrationが必要になる場合がある。

その場合、

Domain Model Change
↓
Data Migration Plan
↓
Database Migration
↓
Application Update

という順序を基本とする。

Data Migrationを、
Application起動時に無秩序に実行しない。

---

# 82. Import / Export

StageArtでは、
必要に応じてData Import / Exportを提供できる。

対象例：

- Person
- Organization
- Participant
- Performance
- Ticket
- Reservation
- Accounting

Import / Exportは、
Application Use Caseとして扱う。

直接DatabaseへCSVを流し込むことを、
通常のBusiness Operationとしない。

---

# 83. Data Ownership and Import

External DataをImportする場合でも、
StageArt側のBusiness Ruleを適用する。

例えばPersonをImportする場合、

External Data
↓
Mapping
↓
Validation
↓
Person Use Case
↓
Person

とする。

External Dataを、
そのままDatabaseへ投入しない。

---

# 84. External Synchronization

External ServiceとのSynchronizationでは、
StageArt側のBusiness Factを優先する。

基本構造：

StageArt Fact
↓
Integration Mapping
↓
External Representation

External ServiceからDataを取得する場合も、
必要なBusiness Ruleを通して
StageArt Dataへ反映する。

---

# 85. Synchronization State

External Integrationが必要な場合、
必要に応じてSynchronization Stateを管理する。

例：

- Pending
- Synced
- Failed
- Retry Required

Synchronization Stateは、
Business Factそのものではなく、
Integration Stateとして扱う。

---

# 86. Data Consistency Priority

Data Consistencyには、
優先順位を設ける。

最重要：

- Check In
- Reservation
- Issued Ticket
- Accounting Fact

次に、

- Production
- Participant
- Rehearsal
- Communication

Integration DataやCacheは、
必要に応じてEventual Consistencyを許容する。

---

# 87. Check In Data Flow

Check Inに関するData Flowは、
Clientによって入口が異なる。

Web Reception：

Web Client
↓
Performance
↓
Reservation / Issued Ticket List
↓
Ticket Selection
↓
Check In Use Case
↓
Issued Ticket
↓
Check In
↓
CheckInCompleted
├── Audience History
└── Accounting

Mobile QR Reception：

Mobile Client
↓
QR Scanner
↓
QR Code
↓
Issued Ticket Identifier
↓
Check In Use Case
↓
Issued Ticket
↓
Check In
↓
CheckInCompleted
├── Audience History
└── Accounting

両方とも、
同じCheck In Business Factを生成する。

QR Code自体は、
Business Factではない。

---

# 88. Accounting Data Flow

Ticket Revenueの基本Data Flow：

CheckInCompleted
↓
Accounting Process
↓
Ticket Revenue
↓
Journal Entry
↓
Journal Entry Line

Journal Entryは、
Accounting Domainの正本である。

Ticket Domainは、
Journal Entryの内部構造を管理しない。

---

# 89. History Data Flow

Audience Historyの基本Data Flow：

Person
↓
Reservation
↓
Issued Ticket
↓
Check In
↓
CheckInCompleted
↓
Audience History

購入だけでは、
Audience Historyを確定しない。

実際のCheck Inを、
観劇実績の起点とする。

Web ClientからのManual Check Inでも、
Mobile ClientからのQR Check Inでも、
同じCheckInCompletedを起点とする。

---

# 90. Data Deletion Principle

Data削除は、
Business Meaningを考慮して行う。

削除してよいTemporary Dataと、
保存すべきBusiness Factを分離する。

特に、

- Accounting
- Check In
- HistoricalActivity
- Audit
- Regulation Version

などは、
安易に削除しない。

---

# 91. Referential Integrity

Domain間のReferenceは、
Data Integrityを維持する。

例えば、

Reservation
→ Performance

Issued Ticket
→ Reservation

Check In
→ Issued Ticket

Audience History
→ Check In / Performance / Person

など。

具体的なForeign Key設計は、
Database Implementationで定義する。

---

# 92. Orphan Data

Business Entityが参照する
Parent Entityが存在しない状態を、
原則として作らない。

ただし、
External ServiceやArchiveなど、
意図的にReferenceが切れる場合は、
明示的なStateを管理する。

---

# 93. Data Archive

大量の過去DataをArchiveする必要が生じた場合でも、
Business Factの意味を失わないようにする。

Archiveは、

Active Data
↓
Archive Data

というPersistence上の最適化として扱う。

Domain Model上では、
必要なBusiness Factを維持する。

---

# 94. Data Security

Data Securityは、
Data ScopeとAuthorizationを中心に設計する。

重要なData：

- Personal Data
- Reservation
- Ticket
- Accounting
- Documents
- Organization Internal Data

について、
適切なAccess Controlを適用する。

---

# 95. Sensitive Data

Password、
Token、
Secret、
API KeyなどのCredential Dataは、
通常のBusiness Dataとは分離する。

Databaseに保存する必要がある場合も、
適切なSecret Management / Encryptionを利用する。

Domain Entityに、
Secretそのものを持たせない。

---

# 96. Data Encryption

必要に応じて、
保存時・通信時のEncryptionを利用する。

特に、

- Authentication Credential
- Sensitive Personal Data
- External Token

など。

具体的なEncryption方式は、
Security Architecture / Infrastructure Architectureで定義する。

---

# 97. Backup

Business Factを復旧できることを優先する。

重要Data：

- Person
- Organization
- Membership
- Production
- Participant
- Performance
- Reservation
- Issued Ticket
- Check In
- Rehearsal
- Accounting

CacheやExternal Artifactは、
必要に応じて再構築できることを基本とする。

---

# 98. Recovery

障害発生時には、
Business Factを優先して復旧する。

復旧対象の優先順位は、
業務上の重要度に応じて定義する。

特に、

Check In
Reservation
Accounting

については、
Data Integrityを最優先する。

---

# 99. Data Observability

Data関連の問題を調査できるように、

- Data Access
- Migration
- Integration
- Background Processing
- Failed Transaction
- Data Integrity Error

などを適切にLoggingする。

ただし、
Personal DataやSecretを
不要にLogへ出力しない。

---

# 100. Data Architecture and API

APIは、
Database Schemaを直接公開しない。

基本構造：

Database
↓
Persistence Model
↓
Domain Entity
↓
Application Result
↓
Response DTO
↓
API

API Contractは、
Business OperationとClient Requirementを
基準として定義する。

Check In APIは、
Web ClientとMobile Clientの双方から
利用できる共通Application Boundaryとする。

---

# 101. Data Architecture and Mobile Client

Mobile Clientは、
StageArt Dataの一部を取得・表示できる。

ただし、

Mobile Client
≠
Data Source of Truth

とする。

例えばQR受付では、

Mobile Client
↓
QR Identifier
↓
API
↓
Server Data
↓
Check In
↓
CheckInCompleted

という構造を維持する。

Mobile Clientが、
Check In済みという状態を
独自に確定しない。

---

# 102. Data Architecture and Web Client

Web Clientは、
QR Codeを利用せず、
Reservation / Issued Ticketの一覧から
Check Inを実行できる。

基本構造：

Web Client
↓
Performance
↓
Reservation / Issued Ticket List
↓
Search / Filter
↓
Ticket Selection
↓
Check In API
↓
Check In
↓
CheckInCompleted

Web Client上の一覧表示は、
Query / Read Modelを利用できる。

ただし、
一覧上のCheck In Statusを
Business Factの正本としない。

Check Inの確定は、
Server SideのCheck In Use Caseで行う。

---

# 103. Data Architecture and WordPress

WordPress Databaseを利用する場合でも、
WordPress Database Structureと
StageArt Domain Modelを同一視しない。

WordPressは、
Persistence Infrastructureとして利用できる。

StageArt Domain
↓
Repository
↓
WordPress Database Adapter
↓
Database

という境界を維持する。

---

# 104. Data Architecture and PHP

PHP Server側では、
Application / Domain / Infrastructureの
責務を分離する。

PHPでDatabaseへアクセスする場合も、

Application
↓
Repository Interface
↓
Infrastructure
↓
Database

という構造を基本とする。

PHPであること自体は、
Domain Modelの構造を変更しない。

---

# 105. Data Architecture and Modular Monolith

Modular Monolithでは、
Databaseを一つにまとめることができる。

ただし、
Databaseが一つだからといって、
Domain Ownershipまで共有しない。

Logical Ownership：

Identity
Organization
Production
Ticket
Reservation
Check In
History
Accounting
etc.

を維持する。

---

# 106. Cross Module Data Access

Module間でDataを参照する場合は、
必要な情報だけを取得する。

他Moduleの内部Persistence Modelを
直接利用しない。

例えばHistory Moduleが、

Ticket Database Table

を直接Queryして
Business Logicを実行することを避ける。

必要なBusiness Informationを、
Application / Domain Interfaceを通して取得する。

---

# 107. Reporting Data

Reportは、
Business Factから生成する。

例えば、

売上Report：

Journal Entry
↓
Accounting Query
↓
Revenue Report

観劇者Report：

Check In
↓
History Query
↓
Audience Report

など。

Report自体を、
Business Factの正本にしない。

---

# 108. Dashboard Data

Dashboardは、
複数DomainのRead Modelを
利用できる。

例えばProduction Dashboard：

Production
+
Participant
+
Rehearsal
+
Performance
+
Ticket
+
Reservation
+
Accounting

を表示する。

Dashboard Dataは、
Presentation / Query用Dataであり、
Business Factの正本ではない。

---

# 109. Search Data

検索機能では、
複数DomainのDataを検索できる。

ただし、
Search IndexをBusiness Factの正本にしない。

基本構造：

Business Fact
↓
Search Projection
↓
Search Index
↓
Search Result

Indexが失われても、
Business Factから再構築できることを目指す。

---

# 110. Data Versioning

Version管理が必要なBusiness Dataは、
明示的にVersionを管理する。

例：

- Regulation
- Document
- Public Page
- Production Information

Versionは、
単なるUpdated Atとは異なる。

VersioningがBusiness Meaningを持つ場合、
Domain Entityとして扱う。

---

# 111. Immutable Fact

変更してはいけないBusiness Factは、
Immutableとして扱う。

特に、

- Journal Entry
- Check In
- HistoricalActivity
- Audit

などは、
現在状態を上書きして過去を消す設計を避ける。

訂正が必要な場合は、
Correction / Reversalなどの
Business Operationを利用する。

---

# 112. Accounting Immutability

Journal Entryは、
原則として過去の会計Factを
直接上書きしない。

訂正が必要な場合は、

Original Journal Entry
↓
Correction / Reversal
↓
New Journal Entry

などの方法を利用する。

具体的なAccounting Ruleは、
Accounting Domainで定義する。

---

# 113. Check In Immutability

Check Inは、
「いつ、どのTicketが受付されたか」
というBusiness Factを表す。

必要な場合でも、
過去のCheck In Factを直接書き換えず、
Correction Operationを利用する。

具体的なCorrection Ruleは、
Check In Domainで定義する。

---

# 114. HistoricalActivity Immutability

HistoricalActivityは、
過去の活動実績を表す。

現在のProfile情報を変更しても、
過去のHistoricalActivityを
自動的に変更しない。

HistoricalActivityの修正は、
明示的なBusiness Operationとして扱う。

---

# 115. Data Lifecycle

Business Dataは、
Lifecycleを持つ場合がある。

例：

Reservation

Created
↓
Confirmed
↓
Issued
↓
Checked In

Rehearsal

Candidate
↓
Confirmed
↓
Completed

Document

Created
↓
Published
↓
Archived

Lifecycle Stateは、
Domain Ruleとして管理する。

---

# 116. State and History

Current StateとHistoryを分離する。

例えばReservationのState：

Current State
→ Confirmed

Check In：

Historical Fact
→ Checked In at specific time

Current Stateだけから、
必要な過去Factを推測しない。

---

# 117. Data State Machine

Stateを持つEntityについては、
許可されるState Transitionを
Domainで管理する。

例えば、

Reservation
Created
↓
Confirmed
↓
Cancelled

など。

不正なState Transitionを、
Databaseへの直接Updateで許可しない。

---

# 118. Data Architecture Summary

StageArt Data Architectureでは、

Business Fact
↓
Domain Ownership
↓
Repository
↓
Persistence

という構造を基本とする。

Domain間連携は、

Business Event
↓
Application Process
↓
Related Domain

を基本とする。

External Serviceとの連携は、

StageArt Data
↓
Integration Mapping
↓
External Reference

とする。

Check Inについては、

Web Client
↓
Reservation / Issued Ticket List
↓
Check In Use Case

または、

Mobile Client
↓
QR Scanner
↓
Issued Ticket Identifier
↓
Check In Use Case

という複数の入口を持つ。

しかし、
どの入口から実行されても、

Check In
↓
CheckInCompleted

という同一のBusiness Factを生成する。

---

# 119. Core Data Flow

StageArtの主要なData Flow：

Person
↓
Organization
↓
Production
↓
Performance
↓
Ticket
↓
Reservation
↓
Issued Ticket
↓
Check In
↓
CheckInCompleted
├── Audience History
└── Accounting
       ↓
    Journal Entry

Check Inの入口は、

Web Client
または
Mobile Client

のいずれでもよい。

ただし、
Check In Business FactのSource of Truthは
Server SideのCheck In Domainとする。

このFlowにおいて、
各Business Factは、
それぞれのDomainがOwnershipを持つ。

---

# 120. Data Architecture Principle

StageArt Data Architectureの最重要原則：

「Business Factを正本として管理し、
Presentation、
Client、
Cache、
External Service、
Database Structure

をBusiness Factの代替にしない。」

また、

「現在の状態と過去のFactを分離し、
DomainごとのData Ownershipを維持する。」

さらに、

「Check Inの入口がWebでもMobileでも、
同一のCheck In Business FactをServer Sideで確定する。」

これにより、

- Web Client
- Mobile Client
- QR Scanner
- PHP Application
- WordPress
- Database
- External Service

のいずれかが変更されても、
StageArtのBusiness Dataを
長期的に維持できる構造を目指す。

---
