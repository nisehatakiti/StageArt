# StageArt Blueprint

# 10 - Architecture
# Application Architecture

Version : 1.2

---

# Purpose

Application Architectureは、
StageArt内部のApplication構造と、
各Layerの責務、
依存方向、
Module構造、
Business Operationの実行方法を定義する。

System Boundaryが、

「StageArtと外部の境界」

を定義するのに対して、

Application Architectureは、

「StageArt内部をどのように構造化するか」

を定義する。

また、
StageArtはWeb Browserだけでなく、
SmartphoneなどのMobile Clientからも
Business Operationを実行できる構造とする。

Check Inについては、
Mobile Clientだけの機能とはせず、
Web ClientとMobile Clientの双方から
同一のApplication Use Caseを利用できる構造とする。

---

# 1. Application Architecture Principles

StageArt Applicationは、
以下を基本原則とする。

- PresentationとBusiness Logicを分離する。
- APIとApplication Use Caseを分離する。
- ApplicationとDomainを分離する。
- DomainとInfrastructureを分離する。
- Business RuleをDomain Layerに集約する。
- Business OperationをApplication LayerでOrchestrateする。
- Infrastructureの具体実装をDomainから隠蔽する。
- Domain間の直接依存を最小化する。
- AuthorizationをApplication Boundaryで必ず評価する。
- Transaction BoundaryをApplication Use Case単位で明確にする。
- External ServiceへのアクセスをIntegration Layerへ閉じ込める。
- UIから直接Databaseへアクセスしない。
- UIから直接External Serviceへアクセスしない。
- Domain EntityをAPI Responseとして直接公開しない。
- Web ClientとMobile Clientを同じApplication Boundaryから利用できる構造とする。
- QR Codeの読み取り処理とBusiness Ruleを分離する。
- Check Inの最終的な確定処理はServer Sideで実行する。
- Client側でBusiness Factを確定しない。
- Check InはClient固有機能ではなく、共通Business Operationとして扱う。
- Web ClientとMobile ClientでCheck In Business Ruleを分けない。

---

# 2. Application Structure

StageArt Applicationは、
以下のLayerで構成する。

Presentation
↓
API
↓
Application
↓
Domain
↓
Infrastructure

Clientは、
以下のような形でApplicationを利用する。

Web Client
↓
StageArt API

Mobile Client
↓
StageArt API

Public Client
↓
StageArt API

Infrastructureは、
必要に応じて以下へ接続する。

- Database
- File Storage
- Authentication Provider
- Calendar Service
- Social Media
- Email Service
- その他External Service

---

# 3. Client Architecture

StageArtは、
複数のClientから利用できる構造とする。

主なClient：

- Web Browser
- Smartphone / Mobile Client
- Administrative Client
- QR Reception Client

これらのClientは、
基本的にStageArt APIを通じて
Applicationを利用する。

ClientごとにBusiness Ruleを
個別実装しない。

基本構造：

Client
↓
API
↓
Application
↓
Domain

---

# 4. Web Client

Web Clientは、
Browser上でStageArtを利用する。

主な用途：

- Management Portal
- Public Portal
- Audience Portal
- Organization Management
- Production Management
- Rehearsal Management
- Ticket Management
- Reservation Management
- Check In Management
- Accounting
- Communication
- Document Management

Web Clientは、
UIとUser Interactionを担当する。

Business Ruleは、
Server Sideで実行する。

---

# 5. Web Check In Client

Web Clientからも、
Check Inを実行できるものとする。

Web Check Inでは、
QR Codeを必須としない。

基本的には、

Performance
↓
Reservation / Issued Ticket List
↓
対象Ticket検索
↓
Check In操作
↓
Check In API
↓
Check In Use Case

というFlowを利用する。

Web Clientでは、
受付担当者が一覧から対象者を確認し、
手動でCheck Inを実行できる。

---

# 6. Web Check In List

Web ClientのCheck In画面では、
必要に応じて以下の情報を一覧表示できる。

- Reservation
- Person
- Ticket
- Ticket Type
- Performance
- Check In Status
- Check In Time
- Ticket Identifier

また、
必要に応じて以下のFilterを提供できる。

- 未受付
- 受付済み
- Reservation Status
- Ticket Type
- Name
- Ticket Number

具体的なUI仕様は、
Frontend Architectureで定義する。

---

# 7. Web Manual Check In

Web ClientからのManual Check Inも、
Mobile ClientからのQR Check Inと
同じBusiness Operationとして扱う。

基本構造：

Web Client
↓
Reservation / Ticket List
↓
Check In Action
↓
Check In API
↓
Check In Use Case
↓
Check In Domain

Web Clientから直接Databaseの
Check In Statusを変更しない。

---

# 8. Mobile Client

Mobile Clientは、
SmartphoneなどのMobile Deviceから
StageArtを利用するためのClientである。

Mobile Clientは、
専用Mobile Applicationとして
実装することができる。

ただし、
Mobile Client固有のUIやDevice機能と、
StageArt Business Ruleを分離する。

基本構造：

Mobile Client
↓
HTTPS
↓
StageArt API
↓
Application
↓
Domain

Mobile Clientは、
StageArt Domainへ直接アクセスしない。

---

# 9. Mobile Client Responsibilities

Mobile Clientは、
以下のようなClient-side機能を担当する。

- User Interface
- Authentication UI
- Navigation
- QR Code Scan
- Camera Access
- User Input
- API Request
- API Response Display
- Connection State
- Loading State
- Error State

Mobile Clientは、
Business Factの最終的な正本を保持しない。

例えばQR Codeを読み取っても、

「Check In済み」

というBusiness Factを
Mobile Clientだけで確定してはならない。

Mobile Clientは、
Check In Business Operationへの
入力手段の一つとして扱う。

---

# 10. QR Scanner

QR Scannerは、
Mobile ClientのDevice機能を利用して
QR Codeを読み取る。

基本Flow：

Camera
↓
QR Code Scan
↓
QR Payload
↓
Check In API
↓
Application
↓
Domain

QR Scanner自体は、
TicketのBusiness Ruleを判断しない。

QR Scannerの役割は、
QR Codeから必要な情報を読み取り、
Serverへ渡すことである。

---

# 11. QR Code Boundary

QR Codeは、
Business Factそのものではない。

QR Codeは、
Issued TicketなどのBusiness Factを
識別するためのArtifactとして扱う。

基本構造：

Issued Ticket
↓
QR Ticket
↓
QR Code
↓
Mobile Scanner
↓
Ticket Identifier
↓
Check In API

QR Codeがコピーされた場合でも、
Server SideでTicketの状態と
Authorizationを検証する。

---

# 12. QR Code Validation

QR Codeを読み取っただけでは、
Check Inを成立させない。

Server Sideで、

- Ticketの存在
- Ticketの有効性
- Ticketの対象Performance
- Ticketの利用状態
- Check In済みか
- 必要なAuthorization
- その他Business Rule

を検証する。

QR Codeに含まれる情報を、
無条件に信頼しない。

---

# 13. Check In as Common Business Operation

Check Inは、
Mobile Client固有の機能ではない。

Check Inは、
StageArtにおける共通Business Operationとして扱う。

Check Inへの入口は、

- Web Client
- Mobile Client
- Administrative Client

など複数存在できる。

ただし、
すべて同じApplication Use Caseを利用する。

基本構造：

Web Client
↓
Check In API
↓
Check In Use Case

Mobile Client
↓
Check In API
↓
Check In Use Case

Administrative Client
↓
Check In API
↓
Check In Use Case

---

# 14. Check In Client Entry

Check InのClient側の入口は、
Clientによって異なってよい。

Web：

Reservation / Issued Ticket List
↓
Check In Action

Mobile：

QR Scanner
↓
Check In Action

Administrative：

Ticket / Reservation Search
↓
Check In Action

しかし、
最終的なBusiness Operationは、
同じCheck In Use Caseとする。

---

# 15. Check In Architecture

Check In全体の基本構造：

Web Client
├── Reservation / Ticket List
│
└── Check In Action
          │
          ▼
     Check In API
          ▲
          │
Mobile Client
├── QR Scanner
│
└── Check In Action
          │
          ▼
     Check In API
          │
          ▼
   Check In Use Case
          │
          ▼
    Check In Domain
          │
          ▼
      Check In
          │
          ▼
  CheckInCompleted
      ├── History
      └── Accounting

Clientによって
Check Inの入口は異なるが、
Business Ruleは共通とする。

---

# 16. Check In API

Check Inは、
Business OperationとしてAPIへ公開する。

概念的なAPI：

POST /tickets/{id}/check-in

または、

POST /reservations/{id}/check-in

などのBusiness Operationを中心とした
API設計とする。

具体的なEndpoint構造は、
API Architectureで確定する。

APIは、
単純なStatus変更ではなく、
Check In Use Caseを実行する入口とする。

---

# 17. Check In Application Flow

Check Inの基本Application Flow：

Client
↓
Check In API
↓
Authentication
↓
Authorization
↓
Load Issued Ticket
↓
Validate Ticket
↓
Check In
↓
Persist Check In
↓
Publish CheckInCompleted
↓
Commit
↓
API Response
↓
Client
↓
受付結果表示

ClientがWebでもMobileでも、
このApplication Flowは共通とする。

---

# 18. Check In Server Responsibility

Check In処理において、
Server Sideは以下を担当する。

- Ticketの存在確認
- Ticketの有効性確認
- 対象Performance確認
- Ticket利用状態確認
- Check In済み確認
- Authorization確認
- Business Rule確認
- Check In Fact作成
- CheckInCompleted発行
- Transaction管理
- Result返却

これらを、
Clientだけで判断しない。

---

# 19. Check In Client Responsibility

Clientは、
Check Inの入力と結果表示を担当する。

Web Client：

- Reservation / Ticket List表示
- Search
- Filter
- 対象Ticket選択
- Check In Action
- Result Display

Mobile Client：

- Camera起動
- QR Code読み取り
- QR Payload取得
- Check In API呼び出し
- Processing表示
- Result Display

どちらのClientも、
以下を最終決定しない。

- Ticketが有効か
- Check In可能か
- Check In済みか
- Revenueを作成するか
- Historyを作成するか

これらはServer Sideで決定する。

---

# 20. QR Reception

QR Receptionは、
Check In Business Operationの
一つの入力方法である。

QR Reception：

Mobile Client
↓
Camera
↓
QR Code
↓
Ticket Identifier
↓
Check In API
↓
Check In Use Case

QR ReceptionとCheck Inを
同一概念として扱わない。

QR Reception：

「Ticketを識別する方法」

Check In：

「Ticketを受付したというBusiness Fact」

である。

---

# 21. Web Reception

Web Receptionは、
Check In Business Operationの
もう一つの入力方法である。

Web Reception：

Web Client
↓
Performance
↓
Reservation / Issued Ticket List
↓
Ticket Selection
↓
Check In API
↓
Check In Use Case

Web Receptionでは、
QR Codeを使用しなくても
Check Inできる。

---

# 22. Reception Operation

Reception Operationは、
WebとMobileの双方から
利用できる。

Mobile：

QRによる高速受付。

Web：

一覧による検索・確認・Manual受付。

両方とも、
Server SideのCheck In Business Ruleを
利用する。

---

# 23. Reception Staff

受付スタッフが、
Web ClientまたはMobile Clientを利用して
Check Inを行う。

基本Flow：

Reception Staff
↓
Authentication
↓
Authorization
↓
Production / Performance Scope
↓
Check In
↓
Check In API
↓
Check In Use Case

受付スタッフが、
どのPerformanceを受付可能かは、
Authorizationによって決定する。

---

# 24. Reception Scope

受付スタッフは、
必要なPerformanceまたはProductionに対して
Check In権限を持つ。

基本的には、

Person
↓
Organization / Production Scope
↓
Role
↓
Permission
↓
Check In

という流れでAuthorizationする。

「QRを読み取れる端末を持っている」
だけでは、
Check In権限を与えない。

Web Clientについても同様に、
Check In権限をServer側で確認する。

---

# 25. Web and Mobile Consistency

Web ClientとMobile Clientで、
Check Inの結果が異ならないようにする。

例えば、

Web Client
→ Ticket XをCheck In

その直後に、

Mobile Client
→ Ticket XをScan

した場合、

Mobile Client側でも
「既にCheck In済み」
として扱われる。

これは、
Server SideのCheck In Factを
正本とすることで保証する。

---

# 26. Application Layer

Application Layerは、
StageArtにおけるBusiness Operationを
Use Caseとして提供する。

Application Layerの責務：

- Use Case
- Transaction Boundary
- Authorization Context
- Domain Object取得
- Domain Operation呼び出し
- 複数DomainのOrchestration
- Domain Event処理
- Integration呼び出し

Application Layerは、
Business Ruleの正本ではない。

---

# 27. Use Case

Use Caseは、
利用者がStageArt上で行う一つの
Business Operationを表す。

例：

- Create Organization
- Update Organization
- Add Member
- Create Production
- Update Production
- Add Participant
- Assign Production Role
- Create Performance
- Create Ticket
- Create Reservation
- Confirm Reservation
- Check In
- Create Rehearsal Candidate
- Submit Rehearsal Availability
- Confirm Rehearsal
- Record Attendance
- Create Budget
- Record Journal Entry
- Create Announcement
- Upload Document
- Publish Production
- Create Survey

Use Caseは、
必要に応じて複数Domainを操作する。

---

# 28. Use Case Naming

Use Caseは、
CRUDではなくBusiness Operationを中心に命名する。

例えば、

Create Reservation

は、
ReservationというBusiness Objectを作成するOperation。

Check In

は、
Issued Ticket / Reservationに対する
Business Operation。

Confirm Rehearsal

は、
Rehearsalを確定するBusiness Operation。

Use Case名は、
利用者が何をするかを表現する。

---

# 29. Command

Application Layerでは、
Use Caseへの入力をCommandとして表現できる。

Commandは、
Use Caseに必要なInputを保持する。

例：

CreateProductionCommand

CreateReservationCommand

CheckInCommand

ConfirmRehearsalCommand

Commandは、
Domain Entityそのものではない。

---

# 30. Query

参照系のOperationは、
Queryとして分離できる。

Queryは、
Business Factを変更しない。

例：

- Get Production
- List Productions
- Get Participants
- Get Performances
- Get Reservations
- Get Rehearsal Schedule
- Get Budget
- Get Accounting Summary
- Get Audience History
- Get Check In List

Command：

Stateを変更する。

Query：

Stateを参照する。

---

# 31. Command / Query Separation

CommandとQueryは、
責務を分離する。

Command：

Business Operationを実行する。

Query：

必要な情報を取得する。

Queryは、
Domain Entityの状態を変更しない。

Commandは、
必要なBusiness RuleをDomainへ委譲する。

---

# 32. Domain Layer

Domain Layerは、
StageArtのBusiness Ruleを保持する。

主な構成：

- Entity
- Value Object
- Domain Service
- Domain Event
- Repository Interface
- Business Rule

Domain Layerは、
Application Use Caseの詳細を知らない。

Domainは、

「誰がこの画面を押したか」

ではなく、

「このBusiness Operationが成立する条件は何か」

を扱う。

---

# 33. Entity Operation

Entityの状態変更は、
Application Layerから直接Propertyを書き換えるのではなく、
Domain Operationとして実行することを基本とする。

例えば、

Reservation.status = checked_in

のような直接変更ではなく、

Reservation.checkIn()

など、
Business Meaningを持つOperationを通じて状態を変更する。

具体的なMethod名は、
Implementation Specificationで定義する。

---

# 34. Value Object

Business上意味を持つ値は、
必要に応じてValue Objectとして扱う。

例：

- Money
- EmailAddress
- DateRange
- TimeRange
- Quantity
- Address

Value Objectは、
Domain Ruleを保持できる。

例えばMoneyであれば、

- Currency
- Amount
- 加算
- 比較

などをDomain側で扱える。

---

# 35. Domain Service

複数EntityにまたがるBusiness Ruleで、
特定Entityへ責務を置くことが不自然な場合、
Domain Serviceを利用する。

Domain Serviceは、
Application WorkflowをOrchestrateするものではない。

Business Ruleそのものを扱う。

---

# 36. Application Service

Application Serviceは、
Use Caseを実行する。

Application Serviceの責務：

- Transaction開始
- Authorization確認
- RepositoryからEntity取得
- Domain Operation実行
- 複数Domainの連携
- Repositoryへの保存
- Domain Event処理

Application Serviceは、
Domain Ruleを再実装しない。

---

# 37. Repository Interface

Repository Interfaceは、
DomainまたはApplicationが必要とする
Persistence操作を定義する。

例：

- PersonRepository
- OrganizationRepository
- MembershipRepository
- ProductionRepository
- ParticipantRepository
- PerformanceRepository
- ReservationRepository
- TicketRepository
- RehearsalRepository
- CheckInRepository
- JournalEntryRepository

Repository Interfaceは、
Database Technologyを公開しない。

---

# 38. Repository Implementation

Repository Interfaceの具体実装は、
Infrastructure Layerに置く。

基本構造：

Application / Domain
↓
Repository Interface
↓
Infrastructure Repository
↓
Database

Applicationは、
MySQLやWordPress Database APIなどの
具体的なDatabase実装を直接知らない。

---

# 39. Infrastructure Layer

Infrastructure Layerは、
Applicationを実行するための
技術的機能を提供する。

主な責務：

- Database Access
- File Storage
- Authentication Provider
- External API
- Email
- Calendar
- Social Media
- Queue
- Cache
- Logging

Infrastructureは、
Business Ruleを定義しない。

---

# 40. Integration Layer

External Serviceとの接続は、
Integration Layerで扱う。

基本構造：

Application
↓
Integration Interface
↓
Infrastructure Adapter
↓
External Service

例えば、

Application
↓
Calendar Integration Interface
↓
Google Calendar Adapter
↓
Google Calendar

とする。

---

# 41. Integration Interface

Application Layerは、
External Service固有のAPIを直接利用しない。

必要なOperationを、
Integration Interfaceとして定義する。

例：

Calendar Integration

- Create Event
- Update Event
- Delete Event
- Find Event

実際のGoogle Calendar APIなどは、
Infrastructure Adapterで実装する。

---

# 42. Transaction

基本的なTransaction Boundaryは、
Application Use Caseとする。

例：

Create Reservation

Begin Transaction
↓
Validate Authorization
↓
Load Performance
↓
Load Ticket
↓
Validate Reservation Rule
↓
Create Reservation
↓
Persist Reservation
↓
Commit

Transaction内では、
Business Factの整合性を保つ。

---

# 43. Cross Domain Transaction

複数DomainをまたぐOperationでは、
Transaction Boundaryを明確にする。

例えばCheck Inでは、

Reservation / Issued Ticket
↓
Check In
↓
CheckInCompleted

というBusiness Factを確定する。

その後、

History
Accounting

などの処理を、
Domain Event / Application Processによって
連携できる。

---

# 44. CheckInCompleted Handling

CheckInCompletedが発生した場合、

History Handler

によって、
観劇履歴を作成・更新する。

Accounting Handler

によって、
Ticket Revenueを作成し、
必要なJournal Entryへ連携する。

基本構造：

CheckInCompleted
├── History Handler
│      ↓
│   Audience History
│
└── Accounting Handler
       ↓
    Ticket Revenue
       ↓
    Journal Entry

Handlerは、
Check In DomainのBusiness Ruleを
再実装しない。

---

# 45. Authorization in Application Layer

Authorizationは、
Application Use Caseの入口で確認する。

基本Flow：

Authentication
↓
Person
↓
Scope
↓
Role
↓
Permission
↓
Use Case

Use Case内部で、
必要に応じて追加のDomain Ruleを検証する。

---

# 46. Organization Authorization

Organization Scopeでは、
Membershipを利用する。

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

Organization AdministratorなどのRoleは、
Membershipを通じて適用する。

---

# 47. Production Authorization

Production Scopeでは、
ProductionDelegateを利用する。

基本構造：

Person
↓
ProductionDelegate
↓
Production
↓
Role
↓
Permission

PrimaryManagerは、
Production ScopeのFull Accessとして扱う。

Participant Typeは、
Authorizationを決定しない。

---

# 48. Participant and Authorization

ParticipantとAuthorizationを分離する。

Participant：

「このPerson / Organizationが
Productionへ参加している」

Role：

「このPersonが
このScopeで何を操作できる」

を表す。

CAST / STAFFなどのParticipant Typeだけでは、
Management Permissionを付与しない。

---

# 49. Query Architecture

Queryは、
利用者が必要とする情報を取得する。

Queryは、
必ずしも一つのDomain Entityだけを
取得するとは限らない。

例えばProduction Dashboardでは、

- Production
- Participant
- Performance
- Rehearsal
- Ticket
- Reservation
- Accounting Summary
- Check In Summary

など、
複数Domainの情報を組み合わせる場合がある。

Query側で、
表示に必要なData ModelへMappingする。

---

# 50. Check In List Query

Web Receptionでは、
Check In対象を一覧表示するための
Queryを利用できる。

基本構造：

Performance
↓
Reservation / Issued Ticket
↓
Check In Status
↓
Reception List

Queryは、
Check In状態を参照する。

Query自体は、
Check In Factを変更しない。

Web Clientで
「受付」ボタンが押された場合は、
QueryではなくCheck In Commandを実行する。

---

# 51. Read Model

複雑なDashboardやReportでは、
Read Modelを利用できる。

Read Modelは、
表示・検索・集計のためのModelである。

Read Modelを、
Business Factの正本として扱わない。

基本構造：

Domain Fact
↓
Query / Projection
↓
Read Model
↓
Presentation

---

# 52. Domain Entity and DTO

Domain EntityとDTOを分離する。

Domain Entity：

Business Ruleを持つ。

DTO：

Layer間でDataを受け渡す。

例えば、

API Request DTO
↓
Application Command
↓
Domain Entity

Responseでは、

Domain Entity
↓
Application Result
↓
Response DTO

というMappingを行う。

---

# 53. Domain Entity and Database Model

Domain EntityとDatabase Modelを分離する。

Domain Entity：

Business Conceptを表現する。

Database Model：

Persistence Structureを表現する。

DatabaseのColumn構造を、
そのままDomain EntityのInterfaceとして
公開しない。

---

# 54. Domain Module Structure

Application内部では、
DomainごとのLogical Moduleを形成する。

例：

Identity
Organization
Project
Production
Participant
Rehearsal
Performance
Ticket
Reservation
Check In
History
Accounting
Communication
Document
Promotion
Equipment
Regulation
Survey

各Moduleは、
自分の責務を持つ。

---

# 55. Identity Module

Identity Moduleは、
AuthenticationとBusiness Identityを
StageArt Domainとして扱う。

主なConcept：

- UserAccount
- External Identity
- Person
- Profile
- HistoricalActivity

Authentication Provider固有の処理は、
Infrastructureへ分離する。

---

# 56. Organization Module

Organization Moduleは、
団体と所属関係を管理する。

主なConcept：

- Organization
- Membership
- Role
- Organization Invitation
- Organization Membership Request

Organization ScopeのAuthorizationも、
このModuleとAuthorization機構が連携する。

---

# 57. Production Module

Production Moduleは、
Project / Productionに関するCore Businessを管理する。

主なConcept：

- Project
- Production
- Participant
- Subject
- ProductionDelegate
- PrimaryManager

Production Scopeの権限は、
Authorization機構と連携する。

---

# 58. Rehearsal Module

Rehearsal Moduleは、
稽古日程と参加確認を管理する。

主なConcept：

- Rehearsal Candidate
- Rehearsal Availability
- Rehearsal
- Rehearsal Attendance
- Timetable
- Timetable Item

Candidateと確定したRehearsalを分離する。

---

# 59. Ticket Module

Ticket Moduleは、
公演チケットに関するBusiness Ruleを管理する。

主なConcept：

- Ticket
- Ticket Type
- Ticket Price
- Performance
- Reservation
- Issued Ticket
- Check In
- QR Ticket

Ticket販売、
Reservation、
Issued Ticket、
Check Inの責務を分離する。

---

# 60. Accounting Module

Accounting Moduleは、
会計FactとProduction Accountingを管理する。

主なConcept：

- Accounting Period
- Account
- Journal Entry
- Journal Entry Line
- Budget
- Budget Item
- Production Actual
- Budget vs Actual
- Production Settlement

Accounting Ruleは、
UIやTicket Moduleへ分散させない。

---

# 61. Communication Module

Communication Moduleは、
連絡と配信を管理する。

主なConcept：

- Announcement
- Announcement Recipient
- Announcement Delivery

EmailなどのDelivery処理は、
Integration Layerと連携する。

---

# 62. Document Module

Document Moduleは、
ファイルと外部Storageとの関係を管理する。

主なConcept：

- Document
- Document Share
- External Connection
- External Storage Reference

実際のFile Storage処理は、
Infrastructureへ分離する。

---

# 63. Promotion Module

Promotion Moduleは、
公開情報とPromotionを管理する。

主なConcept：

- Organization Public Profile
- Production Public Page
- Social Post
- Social Post Reference
- Category
- Genre
- Tag

SNS APIなどは、
Integration Layerへ分離する。

---

# 64. Equipment Module

Equipment Moduleは、
団体が保有・管理する備品を管理する。

主なConcept：

- Equipment
- Equipment History

Equipmentは、
資産会計そのものを担当しない。

---

# 65. Regulation Module

Regulation Moduleは、
Organization規約を管理する。

主なConcept：

- Regulation
- Regulation Version

Versioning Ruleは、
Domain Layerで管理する。

---

# 66. Survey Module

Survey Moduleは、
アンケートと公開可能な感想を管理する。

主なConcept：

- Survey
- Survey Response
- Public Testimonial

Public Testimonialは、
Survey Responseそのものを
直接公開するものではない。

---

# 67. Module Dependency

Module間の依存は、
必要最小限とする。

例えば、

Ticket
↓
Reservation
↓
Check In
↓
History / Accounting

というBusiness Flow上の依存は存在する。

ただし、
History ModuleがTicket Moduleの
内部実装へ直接依存するような構造は避ける。

Domain EventやApplication Layerを利用して、
Module間を疎結合にする。

---

# 68. Domain Event as Module Boundary

Domain Eventは、
Module間連携の重要なBoundaryとなる。

例えば、

CheckInCompleted

は、

Check In
↓
History

および、

Check In
↓
Accounting

を連携する。

Eventを利用することで、
Check In ModuleがHistoryやAccountingの
内部構造を知る必要がなくなる。

---

# 69. Shared Kernel

複数Domainで共有する必要がある
基本的なValueやInfrastructure Interfaceは、
Shared Kernelとして管理できる。

ただし、
Shared KernelへBusiness Ruleを
無制限に追加しない。

Shared Kernelは、
依存関係を増やす可能性があるため、
必要最小限にする。

---

# 70. Domain Isolation

Domain Moduleは、
以下を直接行わない。

- Database Tableへの直接アクセス
- WordPress API呼び出し
- Google API呼び出し
- Email API呼び出し
- SNS API呼び出し
- File Storage API呼び出し
- HTTP Request処理

これらは、
Application / Infrastructure / Integration Layerで処理する。

---

# 71. Error Boundary

Errorは、
Layerごとに変換する。

Domain Error：

Business Rule違反。

Application Error：

Use Case実行上のError。

Infrastructure Error：

Database / External Serviceなどの技術的Error。

API Error：

Clientへ返すResponse Error。

Presentation Error：

Userへ表示するMessage。

内部Errorを、
そのままClientへ返さない。

---

# 72. Validation Boundary

Validationは、
目的によってLayerを分ける。

Presentation：

入力支援。

API：

Request Format。

Application：

Use Case Input。

Domain：

Business Rule。

Infrastructure：

Persistence / External Service制約。

同じValidationを、
すべてのLayerへ無秩序に重複させない。

---

# 73. Logging Boundary

Loggingは、
Infrastructure / Applicationを中心に実装する。

記録対象：

- Authentication
- Authorization Failure
- Use Case
- Domain Error
- Integration
- Infrastructure Error
- Background Job

Domain Entity自身が、
直接Log Serviceへ依存することは避ける。

---

# 74. Audit Boundary

Audit情報は、
必要なBusiness Operationについて記録する。

Auditには、

- Person
- Operation
- Scope
- Timestamp
- Target
- Result

などを記録できる。

Auditは、
Domain Factそのものとは分離する。

---

# 75. Background Processing

Applicationは、
必要に応じてBackground Jobを利用できる。

対象例：

- Email Delivery
- External Calendar Synchronization
- Social Media Publishing
- File Processing
- Report Generation
- Notification

基本構造：

Domain Event
↓
Application Event Handler
↓
Queue
↓
Worker
↓
Integration

---

# 76. Idempotency

External IntegrationやBackground Jobでは、
同一処理が複数回実行されても
Business Factが重複しないようにする。

特に、

- Ticket Revenue
- Journal Entry
- Email Delivery
- Calendar Event
- Social Media Post

などは、
Idempotencyを考慮する。

CheckInなどのBusiness Operationでも、
同一Requestの再送を考慮する。

---

# 77. Check In Idempotency

Check Inは、
Web / Mobileの双方から実行できるため、
同一Ticketへの重複Requestを考慮する。

例えば、

Web Client
↓
Check In Request

と同時に、

Mobile Client
↓
QR Scan
↓
Check In Request

が発生する場合でも、
同一Ticketに対して
Check In Factを二重作成しない。

具体的なConcurrency Controlは、
Data Architecture / Implementation Specificationで定義する。

---

# 78. Application State

Application Stateは、
以下に分類する。

UI State：

画面表示のためのState。

Session State：

Authentication / Sessionに関するState。

Domain State：

Business Fact。

Integration State：

External Serviceとの同期状態。

Cache State：

Performance向上のための一時State。

これらを混同しない。

---

# 79. Configuration Boundary

Application Configurationは、
Environment Configurationとして管理する。

例：

- Database Connection
- Application URL
- External Service Endpoint
- Feature Flag
- Storage Configuration

Secret情報は、
通常のApplication Configurationと分離する。

---

# 80. Feature Flag

将来的に段階的なFeature Releaseが必要になった場合、
Feature Flagを利用できる。

Feature Flagは、
Business Ruleの代替ではない。

Feature Flagによって、
一時的にFeatureのAvailabilityを制御する。

---

# 81. Application Observability

Applicationは、
必要な範囲で以下を観測できる構造とする。

- Error
- Performance
- Request
- Use Case
- Background Job
- External Integration
- Database Operation

Observability Dataは、
Business Dataとは分離する。

---

# 82. Performance Principle

初期段階では、
単純なApplication構造を優先する。

必要以上に、

- Microservices
- Distributed Database
- Event Sourcing
- CQRS
- Complex Cache
- Message Broker

などを導入しない。

必要性が明確になった場合に、
Architectureとして追加する。

---

# 83. Modular Monolith

StageArtの初期Architectureは、
Modular Monolithを基本方針とする。

一つのApplicationとしてDeployしながら、
内部ではDomain Moduleを分離する。

基本構造：

StageArt Application
├── Identity
├── Organization
├── Production
├── Rehearsal
├── Ticket
├── Reservation
├── Check In
├── History
├── Accounting
├── Communication
├── Document
├── Promotion
├── Equipment
├── Regulation
└── Survey

Module内部では、
Domain / Application / Infrastructureの責務を
可能な限り分離する。

---

# 84. Why Modular Monolith

StageArtは、
初期段階では一つのApplicationとして
開発・Deployできることを優先する。

理由：

- 開発コストを抑えられる。
- Domain Boundaryを維持しやすい。
- Debugが容易。
- Transactionを扱いやすい。
- Deploymentが単純。
- ClaudeなどのAI Coding Agentでも
  全体構造を把握しやすい。

将来的に必要性が生じた場合、
一部Moduleを外部Serviceへ分離できる構造を目指す。

---

# 85. WordPress Plugin Structure

WordPress Pluginとして実装する場合でも、
PluginのFile Structureと
Domain Structureを混同しない。

概念構造：

StageArt Plugin
├── Presentation
├── API
├── Application
├── Domain
│   ├── Identity
│   ├── Organization
│   ├── Production
│   ├── Rehearsal
│   ├── Ticket
│   ├── Reservation
│   ├── Check In
│   ├── History
│   ├── Accounting
│   ├── Communication
│   ├── Document
│   ├── Promotion
│   ├── Equipment
│   ├── Regulation
│   └── Survey
└── Infrastructure

具体的なDirectory構造は、
Implementation Specificationで確定する。

---

# 86. WordPress Adapter

WordPress固有機能は、
AdapterとしてApplication / Infrastructureへ接続する。

例：

- WordPress User Adapter
- WordPress Database Adapter
- WordPress Media Adapter
- WordPress HTTP Adapter
- WordPress Scheduler Adapter

Domain Layerは、
これらのAdapterを直接知らない。

---

# 87. Application Entry Point

Applicationへの入口は、
原則として以下に限定する。

- API
- Background Job
- Scheduled Job
- Internal Application Process
- CLI / Administrative Operation

どの入口から実行されても、
同じApplication Use Caseを利用する。

Business Ruleを、
Entry Pointごとに重複実装しない。

---

# 88. Scheduled Process

定期処理が必要な場合、
Scheduled ProcessとしてApplication Use Caseを呼び出す。

例：

- Reservation Reminder
- Calendar Synchronization
- Expired Reservation Processing
- Report Generation
- Notification

Scheduled Processは、
Business Ruleを独自に実装しない。

---

# 89. Internal Application Process

Domain Eventによって、
Application内部のProcessを起動できる。

例：

CheckInCompleted
↓
History Update
↓
Accounting Update

Application Processは、
必要なDomainをOrchestrateする。

---

# 90. Application Consistency

Applicationは、
同じBusiness Operationについて、
どのEntry Pointから実行されても
同じBusiness Ruleが適用される構造を維持する。

例えばCheck Inが、

- Web Management Portal
- Web Reception List
- Mobile Client
- QR Scanner
- Administrative Tool

のいずれから実行されても、
同じCheck In Use Caseを経由する。

---

# 91. Client Specific Presentation

Clientによって、
UIや操作方法は異なってよい。

例えば、

Web：

Performance選択
↓
Reservation / Ticket一覧
↓
検索 / Filter
↓
Check In Action
↓
Result

Mobile：

Performance Context
↓
Camera
↓
QR Scan
↓
Check In Action
↓
Result

という違いがある。

しかし、
最終的に実行するBusiness Operationは、
同じCheck In Use Caseとする。

---

# 92. Application Service Independence

Application Serviceは、
Clientの種類を意識しないことを基本とする。

例えばCheck In Use Caseは、

「Mobileから呼ばれた」

「Webから呼ばれた」

という情報を、
Business Ruleとして必要としない。

必要なAuthorization Contextだけを受け取る。

---

# 93. API Authentication

Mobile ClientからAPIへアクセスする場合も、
Authenticationを必要とする。

Web ClientからAPIへアクセスする場合も、
必要なAuthenticationを行う。

基本構造：

Client
↓
Authentication
↓
Access Token / Session
↓
StageArt API
↓
UserAccount
↓
Person

Authentication方式は、
具体的なTechnology選定時に確定する。

---

# 94. Mobile Session

Mobile Clientでは、
Session / Tokenの安全な管理を行う。

Mobile Clientに、

- Password
- Secret
- API Key
- Database Credential

などを埋め込まない。

Authentication Credentialは、
Secure Storageなどを利用して管理する。

具体的な方式は、
Security Architectureで定義する。

---

# 95. API Response

Check In APIは、
Web / Mobile Client双方が
受付結果を判断できるResponseを返す。

例えば、

- Success
- Already Checked In
- Invalid Ticket
- Ticket Not Found
- Unauthorized
- Forbidden
- Performance Mismatch
- System Error

など。

ただし、
内部Domain Errorをそのまま返すのではなく、
API ResponseとしてMappingする。

---

# 96. API and Client Error Handling

Clientは、
API Responseに基づいてUIを表示する。

Web：

Success
→ 「受付完了」

Already Checked In
→ 「このチケットは受付済みです」

Invalid Ticket
→ 「利用できないチケットです」

Forbidden
→ 「この公演の受付権限がありません」

Mobile：

Network Error
→ 「通信できませんでした。再試行してください」

Web：

Network Error
→ 「通信できませんでした。再試行してください」

表示文言は、
Presentation Layerで管理する。

---

# 97. Testing Boundary

Application Architectureは、
LayerごとにTesting可能であることを目指す。

Domain Test：

Business Rule。

Application Test：

Use Case。

Integration Test：

External Service / Database。

API Test：

API Contract。

E2E Test：

User Operation。

Mobile Client Test：

- QR Scan
- API Communication
- Authentication
- Error Handling
- Reception UI

Web Reception Test：

- Performance Selection
- Reservation / Ticket List
- Search
- Filter
- Check In Action
- Result Display

---

# 98. Testability

Application Layerは、
Infrastructureから独立してTestできる構造を目指す。

Repository InterfaceやIntegration Interfaceを
利用することで、
Test Doubleを利用できるようにする。

Domain Testでは、
External Serviceを必要としない。

Mobile Client Testでは、
CameraやNetworkをMockできる構造を目指す。

Web Reception Testでは、
API ResponseをMockして
一覧・検索・Check In UIを検証できる構造を目指す。

---

# 99. Architecture Decision Rule

新しいClass、
Module、
Service、
Libraryなどを追加する場合は、

「どのLayerの責務か」

を最初に判断する。

判断基準：

Business Rule
→ Domain

Business Operation
→ Application

External Request / Response
→ API

Web UI
→ Presentation

Mobile UI
→ Mobile Presentation

Device Feature
→ Client

Database / External API
→ Infrastructure

External Business Service
→ Integration

---

# 100. Anti Pattern

以下の構造を避ける。

## Fat Controller

ControllerにBusiness Logicを集中させる。

## Fat Component

UI ComponentにBusiness Ruleを実装する。

## Fat Mobile Client

Mobile ClientにCheck Inなどの
Business Ruleを実装する。

## Fat Web Client

Web ClientにCheck Inなどの
Business Ruleを実装する。

## Active Record Dependency

DomainがDatabase Modelへ直接依存する。

## Service Locator

どこからでもGlobal Serviceを取得する。

## God Service

すべてのBusiness Logicを一つのServiceへ集約する。

## Domain Infrastructure Dependency

DomainからWordPress / Database / External APIを直接呼び出す。

## Permission in UI Only

UIだけでPermissionを制御する。

## Client as Source of Truth

Mobile ClientやWeb Clientの状態を、
Business Factの正本にする。

## External Service as Source of Truth

Google CalendarやSNSなどを
StageArt Business Factの正本にする。

## Client Specific Business Rule

Web ClientとMobile Clientで、
同じBusiness Operationに対して
異なるBusiness Ruleを実装する。

---

# 101. Business Rule Location

Business Ruleを、
最も意味の近いLayerへ置く。

例：

「予約は販売期間内でなければ確定できない」

→ Reservation / Ticket Domain

「このPersonはこのProductionを管理できる」

→ Authorization

「Check Inされたら観劇実績が発生する」

→ Check In Domain

「CheckInCompletedを契機に会計処理を行う」

→ Application / Accounting Integration

「QR Codeを読み取ったら受付可能か判定する」

→ Check In Application / Domain

QR Codeの読み取り自体：

→ Mobile Client

Web一覧から対象Ticketを選択すること：

→ Web Client

---

# 102. Mobile Client Architecture Summary

Mobile Clientは、
StageArt ApplicationのClientの一つである。

Mobile Client
↓
HTTPS
↓
StageArt API
↓
Application
↓
Domain

Mobile Clientは、

- Camera
- QR Scanner
- Touch UI
- Local UI State

などのDevice / Presentation機能を担当する。

StageArt Serverは、

- Authentication
- Authorization
- Ticket Validation
- Check In
- Business Rule
- History
- Accounting
- Persistence

を担当する。

---

# 103. Web Client Check In Architecture Summary

Web Clientは、
QRを利用しなくても
Check Inを実行できる。

基本構造：

Web Client
↓
Performance Selection
↓
Reservation / Issued Ticket List
↓
Search / Filter
↓
Ticket Selection
↓
Check In API
↓
Check In Use Case
↓
Check In Domain
↓
Check In
↓
CheckInCompleted
├── History
└── Accounting
↓
API Response
↓
Web Client
↓
受付結果表示

---

# 104. QR Reception Architecture Summary

QR受付の基本構造：

Reception Staff
↓
Mobile Client
↓
Camera
↓
QR Code
↓
QR Payload
↓
Check In API
↓
Authentication
↓
Authorization
↓
Check In Use Case
↓
Ticket / Reservation Validation
↓
Check In
↓
CheckInCompleted
├── History
└── Accounting
↓
API Response
↓
Mobile Client
↓
受付結果表示

---

# 105. Common Check In Architecture

Web受付とQR受付は、
入口が異なるだけで、
Check InのBusiness Operationは共通とする。

Web：

Web Client
↓
Ticket / Reservation List
↓
Check In API

Mobile：

Mobile Client
↓
QR Scanner
↓
Check In API

共通：

Check In API
↓
Check In Use Case
↓
Check In Domain
↓
Check In
↓
CheckInCompleted
├── History
└── Accounting

この構造を、
StageArtの正式なCheck In Architectureとする。

---

# 106. Application Boundary Summary

StageArt Applicationは、

Client
↓
Presentation
↓
API
↓
Application
↓
Domain
↓
Infrastructure

というLayer構造を持つ。

Clientには、

- Web Client
- Mobile Client
- QR Reception Client
- Administrative Client

が含まれる。

Application Layerは、
Business OperationをOrchestrateする。

Domain Layerは、
Business RuleとBusiness Factを管理する。

Infrastructure Layerは、
技術的な実装を提供する。

Integration Layerは、
External Serviceとの境界を提供する。

---

# 107. Architecture Principle

StageArt Application Architectureの最重要原則：

「Business Ruleを、
利用者Interfaceや技術Infrastructureから
独立させる。」

そのため、

User
↓
Client
↓
Presentation
↓
API
↓
Application
↓
Domain

というBusiness Operationの流れと、

Domain
↓
Repository / Integration Interface
↓
Infrastructure
↓
Database / External Service

という技術実装の流れを分離する。

Web ClientとMobile Clientは、
同じApplication Use Caseを利用する。

QR Codeは、
Business Factではなく、
Ticketを識別するためのArtifactとして扱う。

QRの読み取りはMobile Clientで行うが、
Check Inの確定はServer Sideで行う。

Web Clientでは、
Reservation / Issued Ticketの一覧から
Check Inを実行できる。

Web ClientとMobile Clientの
Check In結果は、
同じServer Side Business Factを
参照する。

Check Inが確定した後は、
CheckInCompletedを起点として、
HistoryやAccountingなどのDomainへ連携する。

この責務分離を維持することで、
Web Client、
Mobile Client、
QR Scanner、
PHP Server、
WordPress、
Database、
External Service

が変更されても、
StageArtのBusiness Ruleと
Business Factを一貫して管理できる
Application Architectureを目指す。

---
