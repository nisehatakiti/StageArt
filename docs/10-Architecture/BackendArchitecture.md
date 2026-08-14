# StageArt Blueprint

# 10 - Architecture
# Backend Architecture

Version : 1.1

---

# Purpose

Backend Architectureは、
StageArtにおけるServer Side Applicationの
構造と責務を定義する。

Backend Architectureでは、

- API
- Application
- Domain
- Repository Interface
- Infrastructure
- Persistence
- Authentication
- Authorization
- Transaction
- Domain Event
- Event Dispatch
- Background Process
- Integration
- Error Handling
- Logging
- Check In
- History
- Accounting
- PHP Server
- WordPress Integration

を定義する。

Backend Architectureでは、
具体的なPHP Framework、
Database製品、
Cloud Providerなどの選定までは確定しない。

Backend Architectureは、
Application Architecture、
API Architecture、
Data Architecture、
Integration Architecture、
Security Architecture、
System Boundaryと整合する構造を基本とする。

---

# 1. Backend Architecture Principles

StageArt Backendは、
以下を基本原則とする。

- BackendをBusiness Operationの実行主体とする。
- ClientにBusiness Ruleを持たせない。
- API ControllerにBusiness Ruleを実装しない。
- Application LayerでUse CaseをOrchestrateする。
- Domain LayerでBusiness Ruleを管理する。
- Repository InterfaceをPersistence Boundaryとして利用する。
- Repository Implementationなどの具体的なPersistence処理をInfrastructureへ置く。
- Infrastructure DetailをDomainから分離する。
- Database SchemaをDomain Modelの代わりにしない。
- Transaction BoundaryはApplication Use Caseを基準に考える。
- Business FactはServer SideをSource of Truthとする。
- AuthenticationとAuthorizationをServer Sideで実行する。
- Web ClientとMobile ClientでBusiness Ruleを分けない。
- Client固有の操作方法をBackend Business Ruleへ持ち込まない。
- Domain Eventの生成とEvent Dispatchを分離する。
- Domain Eventによって後続Processを疎結合にできる構造とする。
- External Serviceへの依存をInfrastructureへ閉じ込める。
- Scope外Dataを取得してからClient側でFilterする設計を基本としない。
- Query段階でAuthorization Scopeを適用する。
- Cross DomainのBusiness Factを他Domainが直接更新しない。
- PHPはImplementation Technologyであり、Business Architectureそのものではない。
- WordPressを利用する場合もStageArt Business LogicとWordPress Infrastructureを分離する。
- QueueやBackground WorkerはBusiness Factの正本にならない。
- ClientはBusiness Operationを要求し、BackendがBusiness Ruleを実行する。
- DomainがBusiness Factを管理する。

---

# 2. Backend Architecture Structure

Backendは、
以下の構造を基本とする。

API
↓
Application
↓
Domain
↓
Infrastructure
↓
Persistence / External Service

Repositoryは、
Domain / ApplicationとInfrastructureの間に存在する
Persistence Boundaryとして扱う。

概念構造：

API
↓
Application
↓
Domain
↓
Repository Interface
↓
Infrastructure
↓
Persistence

または、

Application
↓
Integration Interface
↓
Infrastructure Adapter
↓
External Service

とする。

Repositoryは、
独立したBusiness Layerではない。

Repository Interfaceは、
Domain / Application側で定義できる。

Repository Implementationは、
Infrastructure側で実装する。

---

# 3. Backend Layer Responsibilities

## API

ClientとのCommunication Boundaryを担当する。

主な責務：

- HTTP Request
- Authentication Context取得
- Request Validation
- DTO Mapping
- Application Use Case呼び出し
- Response Mapping
- Error Mapping

APIはBusiness Ruleを保持しない。

---

## Application

Business OperationをUse Caseとして実行する。

主な責務：

- Use Case Orchestration
- Authorization Contextの適用
- Scope Contextの適用
- Domain Objectの組み合わせ
- Transaction Boundary
- Repository利用
- Integration Interface利用
- Domain Event生成の起点
- Application Result生成
- Background ProcessへのDispatch

Application自身をBusiness Ruleの正本としない。

---

## Domain

StageArtのBusiness RuleとBusiness Factを管理する。

主な構成：

- Entity
- Value Object
- Domain Service
- Domain Event
- Domain Rule
- Business State

DomainはFramework、
HTTP、
Database、
WordPress、
External APIなどのTechnical Detailへ依存しない。

---

## Infrastructure

Technical Implementationを担当する。

主な責務：

- Database
- Repository Implementation
- ORM / Query
- Authentication Provider
- External API
- File Storage
- Queue
- Cache
- Logging
- Mail
- Calendar
- Social Media
- WordPress
- その他Technical Service

InfrastructureはBusiness Ruleの正本にならない。

---

## Persistence

Business Factを永続化するためのTechnical Layerである。

Database Schemaは、
Domain Modelと同一ではない。

Persistenceには、

- Primary Key
- Foreign Key
- Index
- Constraint
- Audit Information

などのPersistence Concernを持たせる。

---

# 4. Dependency Direction

基本的な依存方向は、

API
↓
Application
↓
Domain

とする。

Infrastructureは、
Application / Domainが定義したInterfaceを実装する。

例えば、

Application
↓
Repository Interface

Infrastructure
↓
Repository Implementation

という関係とする。

Domainから、

- Database
- ORM
- WordPress
- HTTP Client
- External API
- File System
- Queue Product

などの具体的Implementationへ直接依存しない。

---

# 5. API Layer

API Layerは、
ClientからのRequestを受け取り、
Application Layerへ処理を委譲する。

責務：

- HTTP Request
- Authentication Context
- Request Parsing
- Input Validation
- DTO Mapping
- Application Use Case呼び出し
- Response Mapping
- Error Mapping

API Layerは、
Business Ruleを実装しない。

---

# 6. API Controller

API Controllerは、
薄いControllerを基本とする。

基本Flow：

HTTP Request
↓
Controller
↓
Request DTO
↓
Application Use Case
↓
Application Result
↓
Response DTO
↓
HTTP Response

Controller内で、

- Database Query
- Business Rule
- Accounting Rule
- Check In Rule
- Authorization Rule

などを直接実装しない。

---

# 7. Application Layer

Application Layerは、
StageArtのBusiness Operationを
Use Caseとして実行する。

例：

- Create Organization
- Add Organization Member
- Create Project
- Create Production
- Add Participant
- Create Performance
- Create Ticket
- Create Reservation
- Confirm Reservation
- Issue Ticket
- Check In
- Record Attendance
- Create Rehearsal
- Confirm Rehearsal
- Create Journal Entry
- Create Announcement

Application Layerは、
複数DomainをまたぐBusiness Processを
Orchestrateできる。

---

# 8. Application Use Case

Use Caseは、
UserまたはSystemが実行する
Business Operationを表す。

例えばCheck Inでは、

Request Context取得
↓
Authentication
↓
Authorization
↓
Scope Resolution
↓
Ticket / Reservation取得
↓
Business Validation
↓
Domain Operation
↓
Persistence
↓
Domain Event生成
↓
Transaction Commit
↓
Event Dispatch

という構造を基本とする。

Use Caseは、
Domain Ruleそのものではない。

---

# 9. Application Command

Commandは、
Business Operationを実行するためのInputである。

例えば、

CheckInCommand

には必要に応じて、

- Ticket Identifier
- Performance Context
- Actor
- Client Context
- Idempotency Information
- Correlation ID

などを含める。

Commandは、
API Request DTOと同一とは限らない。

---

# 10. Application Query

Queryは、
Business Dataを参照する。

例：

- Get Organization
- Get Production
- List Productions
- List Performances
- List Reservations
- List Issued Tickets
- List Check In Candidates
- Get Audience History
- Get Accounting Summary

Queryは、
Business Factを変更しない。

Queryでも、
Authorization Scopeを必ず適用する。

---

# 11. Command and Query Separation

Command：

Business Factを変更する。

Query：

Business Factを参照する。

例えば、

Check In

はCommand。

Check In List

はQuery。

Web Receptionでは、

GET Check In List
↓
Display

と、

POST Check In
↓
CheckInUseCase

を分離する。

Queryは、
Commandの代わりにBusiness Factを変更しない。

---

# 12. Scope-aware Query

Queryは、
Request UserのAuthorization Scopeを考慮する。

例えば、

GET /productions

であっても、

「すべてのProduction」

を返すのではなく、

「Request UserがAccess可能なProduction」

だけを返す。

基本構造：

Request
↓
Authentication
↓
Authorization
↓
Scope Resolution
↓
Scope-aware Query
↓
Response

Client側で全Dataを取得してからFilterする方式を
Security Boundaryとして利用しない。

---

# 13. Domain Layer

Domain Layerは、
StageArtのBusiness Ruleを管理する。

Domain Layerには、

- Entity
- Value Object
- Domain Service
- Domain Event
- Domain Rule
- Business State

などを配置できる。

Domainは、
PHP FrameworkやWordPress APIなどの
Technical Detailに依存しない。

---

# 14. Domain Entity

Domain Entityは、
Business Identityを持つ。

例：

- Person
- Organization
- Project
- Production
- Participant
- Performance
- Ticket
- Reservation
- Issued Ticket
- Check In
- Rehearsal
- Journal Entry

Domain Entityの内部構造を、
API DTOやDatabase Rowと同一視しない。

Entityの状態変更は、
Business Ruleに従って行う。

---

# 15. Value Object

Value Objectは、
Identityを持たないBusiness Valueを表す。

例：

- Money
- DateRange
- TimeRange
- Ticket Identifier
- Email Address
- Person Name
- Organization Identifier
- Percentage
- Quantity

Value Objectは、
Immutableとして扱うことを基本とする。

---

# 16. Domain Service

Entity単体では表現しにくく、
複数EntityにまたがるBusiness Ruleは、
Domain Serviceとして表現できる。

ただし、
単なるUtility Functionを
Domain Serviceとして増やさない。

Domain Serviceは、
Business Meaningを持つ処理に限定する。

---

# 17. Domain Event

Domain Eventは、
Business Factが成立したことを表す。

例：

- ProductionCreated
- ReservationCreated
- TicketIssued
- CheckInCompleted
- RehearsalConfirmed
- JournalEntryPosted

Domain Eventは、

「何が起きたか」

を表す。

Domain Eventそのものを、
Business Factの正本とはしない。

正本は、
該当DomainのBusiness Factである。

---

# 18. Domain Event Creation and Dispatch

Domain Eventの、

「生成」

と、

「Dispatch」

を分離する。

Domain / Applicationでは、
Business Factが成立した際に
Domain Eventを生成できる。

しかし、
Domain自身が、

- Queue
- Email
- External API
- Worker

などへ直接Dispatchしない。

基本構造：

Business Operation
↓
Business Fact確定
↓
Domain Event生成
↓
Event記録
↓
Transaction Commit
↓
Event Dispatch
↓
Event Handler / Application Process

とする。

---

# 19. Event Dispatch

Event Dispatchは、
Domain Eventを後続Processへ渡す処理である。

Event Handler / Application Processは、
Eventを受け取って、

- History
- Accounting
- Notification
- Communication
- External Integration

などを実行できる。

Event Handlerは、
元Domainの責務を奪わない。

---

# 20. Event and Business Fact

Domain Eventは、
Business Factの代替ではない。

例えば、

Check In
→ Check In Business Fact

CheckInCompleted
→ Check Inが成立したことを示すEvent

とする。

QueueやEventが消失しても、
Check In Business Factそのものを失わない構造とする。

---

# 21. Transaction Boundary

Transaction Boundaryは、
Application Use Caseを基本単位とする。

例えばCheck Inでは、

Begin Transaction
↓
Load Business Data
↓
Validate
↓
Check In Domain Operation
↓
Persist Check In
↓
Record Event
↓
Commit

という処理を基本とする。

Transactionの具体的な実装は、
Infrastructure / Persistenceで定義する。

---

# 22. Transaction and Event

Business Factと、
それに対応するEvent Recordを
同一Transactionで確定できる構造を基本とする。

基本構造：

Transaction
├── Business Fact
└── Event Record
        ↓
      Commit
        ↓
 Event Dispatch
        ↓
 Event Handler

これにより、

Business FactはCommitされたがEventが失われる

という状態を防ぎやすくする。

---

# 23. Outbox Pattern

Eventの信頼性が必要な場合、
Outbox Patternを利用できる。

基本構造：

Transaction
├── Business Fact
└── Outbox Event
        ↓
      COMMIT
        ↓
 Event Dispatcher
        ↓
 Queue / Worker
        ↓
 Event Handler
        ↓
 Application Process

Outboxは、
Business Factの代替ではない。

Outboxの具体的な採用方法は、
Implementation / Deployment要件に応じて決定する。

---

# 24. Background Processing

同期実行が不要、
または時間のかかる処理は、
Background Processへ切り出せる。

例：

- Email Delivery
- Notification
- External Integration
- Report Generation
- Document Processing
- Event Handling

Business OperationのCritical Pathへ、
Heavy Processingを無条件に含めない。

---

# 25. Queue

非同期処理が必要な場合、
Queueを利用できる。

基本構造：

Application
↓
Event / Job
↓
Queue
↓
Worker
↓
Application Process

Queue Messageには、
必要なIdentifierとContextを含める。

Queue自体を、
Business Factの正本としない。

---

# 26. Retry

Background Processでは、
Retry可能な処理と
Retryしても意味がない処理を区別する。

例えば、

External API Timeout
→ Retry可能

Temporary Database Failure
→ Retry可能

Invalid Business Data
→ Retryしても改善しない

など。

Retryによって、
Business Factを重複生成しない構造とする。

---

# 27. Dead Letter

Repeated Failureする
Background Jobについては、

- Dead Letter
- Failed Queue
- Failed Job Record

などを利用できる。

Operational Userが、
失敗内容を確認し、
必要に応じて再実行できる構造を検討する。

---

# 28. Check In Backend

Check Inは、
Backendにおける代表的なBusiness Operationである。

入口は、

Web Client
↓
Check In API

または、

Mobile Client
↓
QR Scanner
↓
Check In API

となる。

どちらの場合でも、
同じApplication Use Caseを利用する。

---

# 29. Reception Boundary

Receptionは、
独立したBackend Business Applicationではない。

Receptionは、
Web ClientまたはMobile Clientから
Check In Business Operationを実行するための
Operational Mode / UI Entryである。

Web：

Performance
↓
Reservation / Issued Ticket List
↓
Ticket Selection
↓
Check In API

Mobile：

Performance
↓
Reception Mode
↓
QR Scanner
↓
Ticket Identifier
↓
Check In API

Backendでは、
どちらも同じCheckInUseCaseを利用する。

---

# 30. Check In Use Case

基本構造：

Check In API
↓
CheckInUseCase
↓
Authentication
↓
Authorization
↓
Scope Resolution
↓
Load Issued Ticket
↓
Load Reservation
↓
Validate Performance
↓
Validate Ticket State
↓
Check In Domain Operation
↓
Persist Check In
↓
Record CheckInCompleted
↓
Commit
↓
Event Dispatch
↓
Response

Clientによって、
Check In Ruleを変更しない。

---

# 31. Check In Validation

CheckInUseCaseでは、
必要なBusiness Contextを確認する。

例：

- Ticket Existence
- Ticket Validity
- Reservation
- Performance
- Ticket State
- Authorization
- Current Check In State

Clientから送信されたDataだけを
無条件に信頼しない。

---

# 32. Check In Authorization

Check Inを実行するActorについて、

- Authentication
- Organization Scope
- Production Scope
- Performance Scope
- Check In Permission

などを確認する。

Authorizationは、
Frontendだけで判断しない。

Backendで必ず検証する。

---

# 33. Check In State

Check Inでは、
既存のCheck In Stateを確認する。

例えば、

Ticket
→ Not Checked In

の場合は、
Check Inを実行できる。

Ticket
→ Already Checked In

の場合は、
二重にBusiness Factを作成しない。

---

# 34. Already Checked In

既にCheck In済みの場合、

CheckInUseCase
↓
Existing Check In
↓
Already Checked In Result

などとして扱える。

必要に応じて、
既存Check In情報をResponseへ返す。

---

# 35. Check In Concurrency

同じTicketに対して、
複数Requestが同時に到達する可能性がある。

例えば、

Web Client
↓
Check In Ticket A

と同時に、

Mobile Client
↓
Check In Ticket A

が発生する。

Backendでは、
同じTicketについて
Check In Factが二重作成されないようにする。

具体的なLock、
Unique Constraint、
Isolation Levelなどは、
Persistence / Implementationで定義する。

---

# 36. Check In Idempotency

Check In Requestは、
Network Retryなどによって
複数回送信される可能性がある。

Backendでは、

- Idempotency Key
- Existing Check In
- Unique Constraint
- Transaction

などを利用し、
重複Business Factを防止する。

---

# 37. Check In Critical Path

Check Inでは、
受付結果を即時に返す必要がある。

基本Critical Path：

Request
↓
Authentication
↓
Authorization
↓
Ticket / Reservation Validation
↓
Check In Domain Operation
↓
Persist
↓
Commit
↓
Response

History、
Accounting、
Notification、
External IntegrationなどのHeavy Processを
Critical Pathへ無条件に含めない。

---

# 38. CheckInCompleted

Check Inが正常に確定した場合、

CheckInCompleted

Domain Eventを生成する。

基本構造：

Check In
↓
CheckInCompleted
├── History Process
├── Accounting Process
└── Notification / Integration Process

後続処理は、
Check In API Controllerから
直接呼び出さない。

---

# 39. History Process

CheckInCompletedを受けて、
必要に応じてHistory Processを実行する。

基本構造：

CheckInCompleted
↓
History Process
↓
Audience History

Historyの具体的なData Modelは、
Domain Model / Data Architectureで定義する。

History Processの失敗によって、
確定済みCheck In Factを不正にRollbackしない。

---

# 40. Accounting Process

CheckInCompletedを受けて、
必要に応じてAccounting Processを実行する。

基本構造：

CheckInCompleted
↓
Accounting Process
↓
Ticket Revenue
↓
Journal Entry

Accounting Ruleは、
Accounting Domain / Application側で管理する。

Accounting Processの失敗によって、
確定済みCheck In Factを不正にRollbackしない。

必要に応じて、

- Pending
- Retry
- Failed
- Rebuild

などを利用する。

---

# 41. Check In and Accounting

Clientが直接Accounting APIを呼び出して、
Accounting Factを生成する設計にはしない。

正しい構造：

Client
↓
Check In API
↓
CheckInUseCase
↓
Check In
↓
CheckInCompleted
↓
Accounting Process
↓
Journal Entry

Web / Mobile双方で、
同じAccounting Processへ到達する。

---

# 42. Check In and History

Clientが直接Historyを作成する設計にはしない。

正しい構造：

Client
↓
Check In API
↓
CheckInUseCase
↓
Check In
↓
CheckInCompleted
↓
History Process
↓
Audience History

---

# 43. Repository Interface

Repositoryは、
Domain / Applicationと
PersistenceのBoundaryである。

Repository Interfaceは、
Domain / Application側に定義できる。

例：

- PersonRepository
- OrganizationRepository
- ProjectRepository
- ProductionRepository
- ParticipantRepository
- PerformanceRepository
- TicketRepository
- ReservationRepository
- IssuedTicketRepository
- CheckInRepository
- RehearsalRepository
- JournalEntryRepository

---

# 44. Repository Responsibility

Repositoryは、
EntityやAggregateを
Persistenceから取得・保存する。

Repositoryは、
Business Ruleの中心ではない。

例えば、

CheckInRepository
→ Check Inを取得・保存する。

「Check Inしてよいか」

というBusiness Ruleは、
Domain / Application側で判断する。

---

# 45. Scope-aware Repository

Repositoryは、
必要に応じてScope Contextを受け取る。

例えば、

findAccessibleProductions(scopeContext)

findAccessibleReservations(scopeContext)

findAccessibleTickets(scopeContext)

など。

Applicationから、

findAllProductions()

してからClient側でFilterする設計を
Security Boundaryとして採用しない。

Scope Filterは、
可能な限りPersistence Queryの段階で適用する。

---

# 46. Repository and Authorization

Repositoryは、
AuthorizationそのものをDomain Ruleとして実装するものではない。

基本構造：

Request
↓
Authentication
↓
Authorization
↓
Scope Resolution
↓
Application
↓
Scope-aware Repository Query
↓
Authorized Data

Repositoryは、
Applicationから渡されたScope Contextに基づき、
取得対象を制限できる。

---

# 47. Repository Implementation

Repository Implementationは、
Infrastructure側に配置する。

基本構造：

Application / Domain
↓
Repository Interface
↓
Repository Implementation
↓
Database / Persistence

Repository Implementationは、

- SQL
- ORM
- WordPress Query
- Database API

などの具体的Technical Detailを扱える。

---

# 48. Query Repository / Read Model

Read-heavyなOperationでは、
Query専用RepositoryやRead Modelを利用できる。

例えばCheck In Listでは、

Person
+
Reservation
+
Issued Ticket
+
Check In Status

を効率的に取得するRead Modelを利用できる。

ただし、
Read ModelをBusiness Factの正本としない。

---

# 49. Web Check In List Query

Web Receptionでは、
一覧表示のためのQueryを利用する。

基本構造：

Web Client
↓
Check In List API
↓
CheckInListQuery
↓
Scope-aware Query
↓
Read Model / Repository
↓
Response DTO

一覧取得は、
Check In Operationとは分離する。

---

# 50. Bulk Check In

Web Clientでは、
複数Ticketを選択して
Check Inすることができる。

Bulk Operationを導入する場合でも、
単純なDatabase Bulk Updateにしない。

各Ticketについて、
必要なAuthorization、
Validation、
Business Ruleを適用する。

---

# 51. Bulk Check In Result

Bulk Check Inでは、
個別Resultを返せる構造とする。

例えば、

Ticket A
→ Success

Ticket B
→ Already Checked In

Ticket C
→ Invalid

Ticket D
→ Forbidden

など。

全件失敗または全件成功だけに
限定しない。

---

# 52. Authentication

Authenticationは、
Backend Security Boundaryである。

基本構造：

Request
↓
Authentication
↓
UserAccount
↓
Person
↓
Request Context

Authentication方式は、
Infrastructureで実装する。

---

# 53. Authentication Context

Applicationへ渡すContextには、
必要に応じて、

- UserAccount
- Person
- Authentication Method
- Session / Token Context
- Client Type
- Device Context

などを含める。

Clientから送信されたRoleやPermissionを、
Security Contextの正本として扱わない。

---

# 54. Person and UserAccount

Authentication Identityと
Business Identityを分離する。

基本構造：

External Identity
↓
UserAccount
↓
Person

Backendでは、
Authentication Provider固有のUser Objectを
Domainへ直接渡さない。

---

# 55. Authorization

Authorizationは、
Authentication後に実行する。

基本構造：

Person
↓
Scope
↓
Role
↓
Permission
↓
Use Case

Organization Scope：

Person
↓
Membership
↓
Organization
↓
Role
↓
Permission

Production Scope：

Person
↓
ProductionDelegate
↓
Production
↓
Role
↓
Permission

---

# 56. Scope Resolution

API Requestごとに、
必要なScopeを解決する。

例えばTicketへのAccessでは、

Ticket
↓
Performance
↓
Production
↓
Project
↓
Organization

を解決する。

そのうえで、

Request Person
↓
Authorized Scope

とのAccessを確認する。

---

# 57. Tenant Isolation

Organizationは、
主要なTenant Boundaryである。

Backendでは、
Request UserのScopeを確認し、
別OrganizationのDataへ
アクセスできないようにする。

Resource IDを知っているだけでは、
Accessを許可しない。

---

# 58. Production Isolation

Productionについても、
Scope Isolationを行う。

Production Aの権限しかないActorが、
Production BのDataを
取得・変更できないようにする。

---

# 59. Domain Independence

Domain Layerは、
以下へ直接依存しないことを基本とする。

- PHP Framework
- WordPress
- HTTP
- Database
- ORM
- External API
- File System
- Queue Product
- Cache Product

これらは、
Infrastructure Boundaryの外側に置く。

---

# 60. PHP as Implementation Technology

StageArt BackendをPHPで実装できる。

ただし、

PHP
=
Architecture

ではない。

PHPは、
Backend Architectureを実装するための
Technologyとして扱う。

---

# 61. PHP Application Structure

PHP Serverでは、
概念的に以下の構造を採用できる。

src/
├── Api/
├── Application/
├── Domain/
└── Infrastructure/

Repository Interfaceは、
DomainまたはApplicationの責務に応じて配置する。

具体的なDirectory Structureは、
Implementation Specificationで決定する。

---

# 62. Framework Independence

PHP Frameworkを利用する場合でも、
Framework固有のCodeを
Domainへ持ち込まない。

例えば、

Controller
→ Framework依存

Repository Implementation
→ Framework / Database依存

Infrastructure Adapter
→ Framework / Provider依存

Domain Entity
→ Framework非依存

という境界を基本とする。

---

# 63. WordPress Integration

StageArtをWordPress Pluginとして
実装する場合でも、
WordPressをInfrastructureとして扱う。

基本構造：

WordPress
↓
StageArt API / Adapter
↓
Application
↓
Domain

WordPress固有Objectを、
Domain Layerへ直接渡さない。

---

# 64. WordPress User Integration

WordPress Userを
Authentication Infrastructureとして
利用できる。

ただし、

WordPress User
≠
Person

とする。

必要に応じて、

WordPress User
↓
UserAccount
↓
Person

へMappingする。

---

# 65. WordPress Data Access

WordPress Databaseを利用する場合でも、
Application / Domainから
直接WP_Queryなどを呼び出さない。

基本構造：

Application
↓
Repository Interface
↓
WordPress Repository Implementation
↓
WordPress Data Access
↓
Database

---

# 66. Infrastructure Layer

Infrastructure Layerは、
Technical Detailを担当する。

例えば、

- Database
- Repository Implementation
- WordPress
- HTTP Client
- Mail
- File Storage
- Authentication Provider
- Queue
- Cache
- Logging
- Calendar
- Social Media

など。

Infrastructureは、
Domain Business Ruleを実装しない。

---

# 67. Database Infrastructure

Database Accessは、
Infrastructureへ閉じ込める。

基本構造：

Repository Implementation
↓
Database Adapter
↓
Database

Database Schemaの変更が、
Domain Layerへ直接影響しない構造を目指す。

---

# 68. External Service Integration

External Serviceは、
Infrastructure Boundaryから利用する。

例えば、

- Email
- Calendar
- Storage
- Social Media
- Payment
- Authentication
- Messaging
- External Ticketing

など。

Applicationから、
Integration Interfaceを通じて利用する。

---

# 69. Integration Interface

External Serviceへの依存は、
Interfaceで抽象化できる。

例えば、

- NotificationService
- CalendarService
- FileStorage
- PaymentService
- MessagingService
- TicketingService

など。

Applicationは、
具体的なProviderを直接知らない。

---

# 70. External API Failure

External APIが失敗した場合でも、
Domain Business Ruleを壊さない。

Integration Errorは、
Application / Infrastructureで処理する。

必要に応じて、

- Retry
- Queue
- Pending
- Failed

などを利用する。

---

# 71. Error Handling

Backend Errorは、
以下を区別する。

- Authentication Error
- Authorization Error
- Validation Error
- Business Rule Error
- Not Found
- Conflict
- Integration Error
- Infrastructure Error

内部Exceptionを、
そのままClientへ返さない。

---

# 72. Domain Error

Domain Rule違反は、
Domain Errorとして表現できる。

例えば、

- Already Checked In
- Invalid Ticket
- Performance Mismatch

など。

Application Layerで、
API向けErrorへMappingする。

---

# 73. Application Error

Application Layerでは、
Use Case実行上のErrorを扱う。

例えば、

- Authorization
- Resource Missing
- Conflict
- External Dependency Failure

など。

---

# 74. Infrastructure Error

Infrastructure Errorには、

- Database Failure
- Network Failure
- Storage Failure
- External API Failure
- Queue Failure

などがある。

Technical DetailはLogへ記録し、
Clientには必要なErrorだけを返す。

---

# 75. Logging

Backendでは、
重要なOperationをLoggingする。

例えば、

- Authentication
- Authorization Failure
- Check In
- Ticket Issuance
- Reservation
- Journal Entry
- Integration Error
- Background Job Failure

など。

---

# 76. Audit

Business上重要なOperationについて、
Auditを記録する。

例えば、

- Who
- What
- When
- Target
- Scope
- Client
- Device
- Correlation ID

など。

AuditとApplication Logを
混同しない。

---

# 77. Check In Audit

Check Inでは、
必要に応じて、

- Actor
- Ticket
- Performance
- Production
- Organization
- Timestamp
- Client Type
- Device
- Correlation ID

などをAudit Contextとして記録する。

---

# 78. Correlation ID

API Requestには、
必要に応じてCorrelation IDを持たせる。

基本構造：

Client
↓
Correlation ID
↓
API
↓
Application
↓
Domain / Infrastructure
↓
Log

Background Processへ引き継ぐ場合も、
Correlation IDを可能な限り保持する。

---

# 79. Observability

Backendでは、
必要に応じて以下をMonitoringする。

- Request Count
- Response Time
- Error Rate
- Database Performance
- Queue
- Background Job
- External API
- Check In Success
- Check In Failure

Monitoring Dataを、
Business Factの正本としない。

---

# 80. Security Boundary

Backendは、
主要なSecurity Boundaryである。

Clientから送信された、

- User ID
- Person ID
- Organization ID
- Project ID
- Production ID
- Performance ID
- Ticket ID
- Role
- Permission
- Price
- Status

などを、
無条件に信頼しない。

Server側で、
Authentication Context、
Authorization Context、
Domain Dataから再検証する。

---

# 81. Input Validation

Request Inputは、
API Layerで基本Validationを行う。

さらに、
Application / Domainで
Business Validationを行う。

例えば、

API：

Identifier Format

Domain：

TicketがCheck In可能か

というように責務を分離する。

---

# 82. Output Filtering

Backendは、
Clientへ必要なDataだけを返す。

Internal Database Columnや、
Security Sensitive Dataを
Responseへ含めない。

Domain Entityを、
そのままAPI Responseとして公開しない。

---

# 83. Secret Management

BackendのSecretは、
Source CodeへHard Codeしない。

対象：

- Database Credential
- API Key
- OAuth Secret
- Encryption Key
- Mail Credential
- External Service Credential

具体的なSecret Storageは、
Deployment / Security Architectureで定義する。

---

# 84. Rate Limiting

必要に応じて、
APIへのRate Limitingを行う。

特に、

- Login
- Authentication
- Public Search
- Reservation
- Check In
- QR Reception

などを考慮する。

---

# 85. QR Reception Security

QR Payloadは、
Business Factとして信頼しない。

Backendで、

QR Payload
↓
Ticket Identifier
↓
Ticket Lookup
↓
Performance Validation
↓
Authorization
↓
Check In

という検証を行う。

QR Codeそのものを、
Check In Factとして扱わない。

---

# 86. Replay Protection

QRやCheck In Requestの
Replayを考慮する。

例えば、

- Already Checked In
- Idempotency
- Expiration
- Ticket State
- Request Uniqueness

など。

具体的なSecurity Ruleは、
Security Architectureで定義する。

---

# 87. API Versioning

Backend APIは、
Version Boundaryを持てる構造とする。

例えば、

/api/v1

など。

API Version変更によって、
Domain ModelをVersion依存にしない。

---

# 88. Backend Compatibility

Mobile Clientは、
旧VersionのAPIを利用する可能性がある。

そのため、

- Backward Compatibility
- API Version
- Deprecation
- Migration

を考慮する。

---

# 89. Configuration

Backend Configurationは、
Environmentごとに分離する。

例えば、

- Development
- Test
- Staging
- Production

など。

ConfigurationとSecretを
適切に分離する。

---

# 90. Environment

Backendでは、
Environmentによって、

- Database
- External API
- Storage
- Logging
- Feature Flag

などを切り替えられる。

ProductionとDevelopmentのDataを
混在させない。

---

# 91. Feature Flag

Feature Flagは、
段階的なFeature Releaseに利用できる。

例えば、

- Web Check In
- Mobile QR Reception
- New Accounting Process

など。

Feature Flagは、
Security Boundaryではない。

---

# 92. Backend Testing

Backendでは、
以下をTestする。

- Domain
- Application
- API
- Repository
- Integration
- Authentication
- Authorization
- Check In
- Accounting
- History
- Event Dispatch
- Background Process

---

# 93. Domain Testing

Domain Testでは、
Business Ruleを中心にTestする。

例えば、

- Check In可能条件
- Already Checked In
- Invalid Ticket
- Performance Mismatch

など。

FrameworkやDatabaseに依存しない
Testを基本とする。

---

# 94. Application Testing

Application Testでは、
Use CaseのOrchestrationをTestする。

例えばCheckInUseCaseについて、

- Authentication Context
- Authorization
- Scope Resolution
- Ticket Load
- Reservation Load
- Validation
- Check In
- Persistence
- Event Generation

などを確認する。

---

# 95. API Testing

API Testでは、

- Request
- Authentication
- Authorization
- Validation
- Response
- Error
- API Version

などを確認する。

---

# 96. Repository Testing

Repository Testでは、
PersistenceとのMappingを確認する。

例えば、

Domain Entity
↓
Database
↓
Domain Entity

のMappingが正しいことを確認する。

Scope-aware Queryについても、
Authorization ScopeがQueryへ正しく反映されることを確認する。

---

# 97. Integration Testing

Integration Testでは、

- Database
- WordPress
- External API
- Storage
- Queue
- Event Dispatcher

などとの接続を確認する。

---

# 98. Check In Testing

Check In Backendでは、
最低限以下をTestする。

- Valid Ticket
- Invalid Ticket
- Already Checked In
- Wrong Performance
- Unauthorized
- Forbidden
- Duplicate Request
- Retry
- Concurrent Request
- Web Client
- Mobile Client
- QR Payload Validation
- Event Generation
- Event Dispatch

---

# 99. Check In and Accounting Testing

Check In成功後に、

CheckInCompleted
↓
Accounting Process
↓
Journal Entry

が正しく処理されることをTestする。

WebとMobileのどちらから
Check Inしても、
同じAccounting Processへ到達することを確認する。

Accounting Processが失敗した場合でも、
確定済みCheck In Factが不正に失われないことを確認する。

---

# 100. Check In and History Testing

Check In成功後に、

CheckInCompleted
↓
History Process
↓
Audience History

が正しく処理されることをTestする。

History Processが失敗した場合でも、
確定済みCheck In Factが不正に失われないことを確認する。

---

# 101. Event and Outbox Testing

Outboxを採用する場合、

Transaction
├── Business Fact
└── Outbox Event
        ↓
      Commit
        ↓
 Event Dispatcher

という一連の処理をTestする。

特に、

- Transaction Rollback
- Duplicate Dispatch
- Retry
- Worker Failure
- Event Handler Failure

を確認する。

---

# 102. End-to-End Backend Flow

代表的なBackend Flow：

Web：

Web Client
↓
API
↓
CheckInUseCase
↓
Check In
↓
CheckInCompleted
↓
Commit
↓
Event Dispatch
├── History Process
└── Accounting Process

Mobile：

Mobile Client
↓
QR Scanner
↓
API
↓
CheckInUseCase
↓
Check In
↓
CheckInCompleted
↓
Commit
↓
Event Dispatch
├── History Process
└── Accounting Process

WebとMobileでは、
受付方法だけが異なり、
Business Operationは共通とする。

---

# 103. Backend and Frontend

Frontendは、
Backend APIを利用する。

基本構造：

Web Client
↓
API
↓
Application
↓
Domain

Mobile Client
↓
API
↓
Application
↓
Domain

Frontendは、
Backend Domain Modelへ
直接アクセスしない。

---

# 104. Backend and Domain Model

Backendは、
Domain ModelをApplicationから利用する。

Domain Modelは、
Backend FrameworkやDatabase Schemaに
従属しない。

基本構造：

API
↓
Application
↓
Domain
↓
Repository Interface
↓
Infrastructure

---

# 105. Backend and Data Architecture

Data Architectureは、

- Business Fact
- Data Ownership
- Source of Truth
- Scope
- Persistence
- History
- Accounting Data
- Read Model

などを定義する。

Backendは、

Application
↓
Domain
↓
Repository Interface
↓
Repository Implementation
↓
Persistence

という形でDataへアクセスする。

Database Tableを、
Business APIの直接の契約としない。

---

# 106. Backend and API Architecture

API Architectureは、
ClientとのCommunication Boundaryを定義する。

Backend Architectureは、
APIの内側を定義する。

基本構造：

Client
↓
API Architecture
↓
Backend Architecture
↓
Domain / Data Architecture

---

# 107. Backend and Accounting

Accountingは、
BackendのApplication / Domain Processとして扱う。

Check InからAccountingへは、

Check In
↓
CheckInCompleted
↓
Accounting Process
↓
Journal Entry

というEvent / Application Processを利用する。

ClientがAccounting Factを直接生成しない。

---

# 108. Backend and History

Historyは、
BackendのApplication Processとして扱う。

Check InからHistoryへは、

Check In
↓
CheckInCompleted
↓
History Process
↓
Audience History

という構造を利用する。

---

# 109. Backend and Integration

External Serviceは、
Backend Integration Boundaryから利用する。

例えば、

Application
↓
Integration Interface
↓
External Service Adapter
↓
External API

とする。

Domainが、
External APIへ直接アクセスしない。

---

# 110. Backend and WordPress

WordPressを利用する場合、

WordPress
↓
Infrastructure

として扱う。

StageArt Business Logicは、

Application
↓
Domain

へ置く。

WordPress Plugin Hookや
WordPress REST API固有処理を、
Domainへ持ち込まない。

---

# 111. Backend and PHP

PHPは、
Backend実装の主要Technologyとして
利用できる。

ただし、

PHP Framework
↓
Application / Domain

という直接依存を避ける。

Framework固有処理を、
API / Infrastructureへ閉じ込める。

---

# 112. Backend Deployment Boundary

Backendは、
独立したDeployable Unitとして
構成できる。

例えば、

Client
↓
Web Server
↓
PHP Application
↓
Database

という構造。

具体的なHosting、
Container、
Serverlessなどは、
Deployment Architectureで定義する。

---

# 113. Background Worker

必要に応じて、
BackendにBackground Workerを導入する。

例えば、

Queue
↓
Worker
↓
Application Process

など。

用途：

- Notification
- Email
- External Integration
- Report
- Document Processing
- Event Handling

Workerは、
Business Factの正本を保持する場所ではない。

---

# 114. Queue and Business Fact

Queueは、
Business Factの正本ではない。

例えば、

CheckInCompleted

というEventを
Queueへ渡すことはできるが、

Queueが消えても、
Check In Factそのものを失わない構造とする。

必要に応じて、
Outboxなどを利用する。

---

# 115. Background Retry

Background Processでは、
Retryによる重複実行を考慮する。

例えば、

Event Dispatch
↓
External API
↓
Timeout
↓
Retry

が発生しても、
同一Business Factを重複生成しない。

Integration Operationには、
必要に応じてIdempotencyを適用する。

---

# 116. Backend Performance

Backendでは、
Performanceを考慮する。

特に、

- Check In
- Ticket List
- Reservation List
- Participant List
- Accounting
- History

など。

必要に応じて、

- Index
- Cache
- Read Model
- Pagination
- Queue

などを利用する。

---

# 117. Check In Performance

Check Inでは、
受付担当者が連続して操作するため、
低Latencyを重視する。

基本的なCritical Path：

Request
↓
Authentication
↓
Authorization
↓
Ticket Validation
↓
Check In
↓
Commit
↓
Response

Heavy Processingを、
Critical Pathへ無条件に含めない。

---

# 118. Check In Scalability

公演開始直前など、
Check In Requestが集中する可能性がある。

Backendでは、

- Concurrent Request
- Database Lock
- Connection Pool
- API Scaling
- Rate Limiting

などを考慮する。

具体的なScale Strategyは、
Deployment Architectureで定義する。

---

# 119. Bulk Check In Performance

Web Clientから
大量Ticketを一括Check Inする場合、
通常の個別Requestを
単純に大量発行するだけではなく、
必要に応じてBulk Operationを設計する。

ただし、

- Business Rule
- Authorization
- Validation
- Idempotency

を省略しない。

---

# 120. Backend Observability

Backendでは、
Operation単位で追跡可能な
Observabilityを提供する。

例えば、

- Request ID
- Correlation ID
- Actor
- Operation
- Target
- Duration
- Result
- Error
- Queue Job ID

など。

---

# 121. Health Check

Backendでは、
必要に応じてHealth Check Endpointを
提供する。

Health Checkでは、

- Application
- Database
- Queue
- External Dependency

などの状態を確認できる。

Health Checkの詳細は、
Deployment Architectureで定義する。

---

# 122. Graceful Failure

External Dependencyが停止しても、
StageArt全体が不要に停止しない構造を目指す。

例えば、

Email Service Down
↓
Check In
→ 必要に応じて継続

Email
→ Pending / Retry

など。

Business Criticalityに応じて、
Failure Strategyを定義する。

---

# 123. Data Consistency

Backendでは、
Business FactとProjection / Process Resultを区別する。

例えば、

Check In Fact
→ Source of Truth

Audience History
→ History DomainのBusiness Fact

Journal Entry
→ Accounting DomainのBusiness Fact

Read Model
→ Projection

Queue
→ Processing Infrastructure

Outbox Event
→ Event Delivery用のInfrastructure Data

とする。

---

# 124. Business Fact Ownership

Backendでは、
各Business FactのOwnerを明確にする。

例えば、

Check In
→ Check In Domain

Journal Entry
→ Accounting Domain

Audience History
→ History Domain

Reservation
→ Reservation Domain

Issued Ticket
→ Ticket Domain

など。

別Domainが、
他DomainのFactを直接書き換えない。

---

# 125. Cross Domain Operation

複数DomainにまたがるOperationは、
Application LayerでOrchestrateする。

例えば、

Check In
↓
CheckInCompleted
↓
History Process
↓
Accounting Process

のようなProcess。

Check In Domainが、
直接Accounting Databaseへ
書き込む設計にはしない。

---

# 126. Domain Boundary

Domain間の依存を
必要以上に強くしない。

例えば、

Check In Domain
→ Check In Fact

Accounting Domain
→ Journal Entry

History Domain
→ Audience History

とし、
Event / Application Processによって
連携する。

Domainから別DomainのPersistenceへ
直接アクセスしない。

---

# 127. Backend Architecture Decision

Backendでは、
以下のArchitectureを基本とする。

Client
↓
API
↓
Application
↓
Domain
↓
Repository Interface
↓
Infrastructure
↓
Persistence

External Integration：

Application
↓
Integration Interface
↓
Infrastructure Adapter
↓
External Service

Authorization：

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

Business Operation：

API
↓
Use Case
↓
Domain
↓
Persistence

Event：

Business Fact
↓
Domain Event
↓
Event Record / Outbox
↓
Commit
↓
Event Dispatch
↓
Application / Background Process

---

# 128. Backend Architecture Summary

StageArt Backendは、

Client
↓
API
↓
Application
↓
Domain
↓
Infrastructure
↓
Persistence / External Service

というLayered Architectureを基本とする。

Repositoryは、
Domain / Applicationと
PersistenceのBoundaryとして扱う。

Web ClientとMobile Clientは、
異なる操作方法を持つ。

Web：

Performance
↓
Reservation / Issued Ticket List
↓
Ticket Selection
↓
Check In API

Mobile：

Performance
↓
Reception Mode
↓
QR Scanner
↓
Ticket Identifier
↓
Check In API

しかし、
Backendでは同じ、

CheckInUseCase
↓
Check In Domain Operation

を利用する。

Check In成功後は、

Check In
↓
CheckInCompleted
↓
Commit
↓
Event Dispatch
├── History Process
├── Accounting Process
└── Notification / Integration Process

という構造を利用する。

Domain Eventの生成とDispatchを分離し、
必要に応じてOutbox、
Queue、
Background Workerを利用する。

Backendの最重要原則は、

「ClientはBusiness Operationを要求し、
BackendがBusiness Ruleを実行し、
DomainがBusiness Factを管理する。」

ことである。

また、

「RepositoryはPersistence Boundaryであり、
具体的なPersistence ImplementationはInfrastructureに置く。」

ことを原則とする。

さらに、

「Domain EventはBusiness Factそのものではなく、
Business Factが成立したことを後続Processへ伝えるための仕組みである。」

ことを原則とする。

また、

「PHPやWordPressはBackendのImplementation Detailであり、
Business Architectureそのものではない。」

ことを原則とする。

これにより、

- Web Client
- Mobile Client
- PHP Server
- WordPress
- Database
- External Service
- Queue
- Background Worker

などのTechnologyが変更されても、
StageArtのBusiness RuleとBusiness Factを
一貫して維持できるBackend Architectureを実現する。

---
