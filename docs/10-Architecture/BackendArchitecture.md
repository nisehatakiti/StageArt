# StageArt Blueprint

# 10 - Architecture
# Backend Architecture

Version : 1.0

---

# Purpose

Backend Architectureは、
StageArtにおけるServer Side Applicationの構造と責務を定義する。

Backend Architectureでは、

- API
- Application
- Domain
- Repository
- Infrastructure
- Persistence
- Authentication
- Authorization
- Transaction
- Domain Event
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

---

# 1. Backend Architecture Principles

StageArt Backendは、
以下を基本原則とする。

- BackendをBusiness Operationの実行主体とする。
- ClientにBusiness Ruleを持たせない。
- API ControllerにBusiness Ruleを実装しない。
- Application LayerでUse CaseをOrchestrateする。
- Domain LayerでBusiness Ruleを管理する。
- Repositoryを通じてPersistenceへアクセスする。
- Infrastructure DetailをDomainから分離する。
- Database SchemaをDomain Modelの代わりにしない。
- Transaction BoundaryはApplication Operationを基準に考える。
- Business FactはServer SideをSource of Truthとする。
- AuthenticationとAuthorizationをServer Sideで実行する。
- Web ClientとMobile ClientでBusiness Ruleを分けない。
- Client固有の操作方法をBackend Business Ruleへ持ち込まない。
- Domain Eventによって後続Processを疎結合にできる構造とする。
- External Serviceへの依存をInfrastructureへ閉じ込める。
- PHPはImplementation Technologyであり、Business Architectureそのものではない。
- WordPressを利用する場合もStageArt Business LogicとWordPress Infrastructureを分離する。

---

# 2. Backend Layer

Backendは、
概念的に以下のLayerへ分離する。

API
↓
Application
↓
Domain
↓
Repository
↓
Infrastructure
↓
Persistence

基本的な責務：

API
→ ClientとのCommunication

Application
→ Use CaseのOrchestration

Domain
→ Business Rule

Repository
→ Domain ObjectのPersistence Boundary

Infrastructure
→ Technical Implementation

Persistence
→ Database / Storage

---

# 3. API Layer

API Layerは、
ClientからのRequestを受け付ける。

責務：

- HTTP Request
- Authentication Context
- Authorization Context
- Request Validation
- DTO Mapping
- Application Command
- Query
- Response Mapping
- Error Mapping

API Layerは、
Business Ruleを実装しない。

---

# 4. API Controller

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
Result
↓
Response DTO
↓
HTTP Response

Controller内で、

- Database Query
- Business Rule
- Accounting Rule
- Check In Rule

などを直接実装しない。

---

# 5. Application Layer

Application Layerは、
StageArtのBusiness Operationを
Use Caseとして実行する。

例えば、

- Create Production
- Create Reservation
- Confirm Reservation
- Issue Ticket
- Check In
- Record Attendance
- Create Journal Entry

など。

Application Layerは、
Domain Objectを組み合わせて
Use Caseを実行する。

---

# 6. Application Use Case

Use Caseは、
UserまたはSystemが実行する
Business Operationを表す。

例えば、

Check In

というUse Caseは、

- Request Context取得
- Authorization確認
- Ticket取得
- Reservation確認
- Performance確認
- Domain Operation
- Persistence
- Event発行

などをOrchestrateする。

---

# 7. Application Command

Commandは、
Business Operationを実行するためのInputである。

例えばCheck Inでは、

CheckInCommand
- Ticket Identifier
- Performance Context
- Actor
- Client Context
- Idempotency Information

など。

Commandは、
API Request DTOと同一とは限らない。

---

# 8. Application Query

Queryは、
Business Dataを参照する。

例えば、

- Get Production
- List Performances
- List Reservations
- List Issued Tickets
- List Check In Candidates
- Get Audience History
- Get Accounting Summary

など。

Queryは、
Business Factを変更しない。

---

# 9. Command and Query Separation

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
Check In Use Case

を分離する。

---

# 10. Domain Layer

Domain Layerは、
StageArtのBusiness Ruleを管理する。

Domain Layerには、

- Entity
- Value Object
- Domain Service
- Domain Event
- Domain Rule

などを配置できる。

Domainは、
PHP FrameworkやWordPress APIなどの
Technical Detailに依存しない。

---

# 11. Domain Entity

Domain Entityは、
Business Identityを持つ。

例えば、

- Person
- Organization
- Production
- Performance
- Reservation
- Issued Ticket
- Check In

など。

Domain Entityの内部構造を、
API DTOやDatabase Rowと同一視しない。

---

# 12. Value Object

Value Objectは、
Business上意味を持つ値を表す。

例えば、

- Money
- Date Range
- Ticket Identifier
- Email Address
- Person Name
- Organization Identifier

など。

具体的なValue Objectは、
Domain Modelで定義する。

---

# 13. Domain Service

Entity単体では表現しにくい
Business Ruleは、
Domain Serviceとして表現できる。

ただし、
単なるUtility Functionを
Domain Serviceとして増やさない。

Domain Serviceは、
Business Meaningを持つ処理に限定する。

---

# 14. Domain Event

Business Factが成立した場合、
Domain Eventを発行できる。

例えば、

CheckInCompleted

など。

Domain Eventは、
「何が起きたか」を表す。

後続Processの具体的な実装とは分離する。

---

# 15. Check In Backend

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

# 16. Check In Use Case

基本構造：

Check In API
↓
CheckInUseCase
↓
Load Issued Ticket
↓
Load Reservation
↓
Validate Performance
↓
Validate Authorization
↓
Check In Domain Operation
↓
Persist Check In
↓
Publish CheckInCompleted
↓
Commit

Clientによって、
Check In Ruleを変更しない。

---

# 17. Web Check In Backend

Web ClientからのCheck In：

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
CheckInUseCase

Backendでは、
Web Clientがどの画面から操作したかを
Business Ruleとして扱わない。

---

# 18. Mobile QR Check In Backend

Mobile ClientからのCheck In：

Mobile Client
↓
QR Scanner
↓
Ticket Identifier
↓
Check In API
↓
CheckInUseCase

QR Codeの読み取り自体は
Mobile Clientの責務。

QR PayloadのValidationと
Check In確定はBackendの責務。

---

# 19. Common Check In Use Case

WebとMobileは、
入口が異なる。

Web：

Ticket Selection
↓
Check In API

Mobile：

QR Scan
↓
Check In API

しかし、

Check In API
↓
CheckInUseCase
↓
Check In

は共通とする。

これにより、
ClientごとにBusiness Ruleが分裂することを防ぐ。

---

# 20. Check In Validation

CheckInUseCaseでは、
必要なBusiness Contextを確認する。

例えば、

- Ticket Existence
- Ticket Validity
- Reservation
- Performance
- Ticket State
- Authorization
- Current Check In State

など。

Clientから送信されたDataだけを
無条件に信頼しない。

---

# 21. Check In Authorization

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

# 22. Check In State

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

# 23. Already Checked In

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

# 24. Check In Concurrency

同じTicketに対して、
複数のRequestが同時に到達する可能性がある。

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

具体的なLockやDatabase Constraintは、
Persistence / Implementationで定義する。

---

# 25. Check In Idempotency

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

# 26. Check In Transaction

Check InのBusiness Operationは、
必要な処理をTransaction Boundary内で
一貫して扱う。

概念的には、

Begin Transaction
↓
Validate
↓
Create Check In
↓
Persist
↓
Prepare Event
↓
Commit

とする。

具体的なTransaction方式は、
Infrastructure / Persistenceで定義する。

---

# 27. CheckInCompleted

Check Inが正常に確定した場合、

CheckInCompleted

Domain Eventを発行できる。

基本構造：

Check In
↓
CheckInCompleted
├── History
└── Accounting

後続処理は、
Check In API Controllerから
直接呼び出さない。

---

# 28. History Process

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

---

# 29. Accounting Process

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

---

# 30. Check In and Accounting

Check In APIが成功したからといって、
Clientが直接Accounting APIを呼び出す設計にはしない。

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

これにより、
Web / Mobile双方で
Accounting連動を統一できる。

---

# 31. Check In and History

Check In APIが成功したからといって、
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

# 32. Repository

Repositoryは、
Domain / ApplicationとPersistenceの
Boundaryである。

例えば、

PersonRepository
ProductionRepository
PerformanceRepository
ReservationRepository
IssuedTicketRepository
CheckInRepository

など。

Repository Interfaceは、
Domain / Application側に定義できる。

---

# 33. Repository Responsibility

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

# 34. Repository and Database

Repository Implementationが、
Databaseへの具体的なアクセスを担当する。

基本構造：

Application
↓
Repository Interface
↓
Repository Implementation
↓
Database

Applicationが、
SQLやWordPress Query APIへ
直接依存しない。

---

# 35. Query Repository

Read-heavyなOperationでは、
Query専用Repository / Read Modelを
利用できる。

例えば、

Check In List

では、

Person
+
Reservation
+
Issued Ticket
+
Check In Status

を効率的に取得する
Read Modelを利用できる。

ただし、
Read ModelをBusiness Factの正本としない。

---

# 36. Web Check In List Query

Web Receptionでは、
一覧表示のためのQueryを利用する。

基本構造：

Web Client
↓
Check In List API
↓
CheckInListQuery
↓
Read Model / Repository
↓
Response DTO

一覧取得は、
Check In Operationとは分離する。

---

# 37. Bulk Check In

Web Clientでは、
複数Ticketを選択して
Check Inすることができる。

Backendでは、
Bulk Operationを導入する場合でも、
単純なDatabase Bulk Updateにしない。

各Ticketについて、
必要なValidationとBusiness Ruleを
適用する。

---

# 38. Bulk Check In Result

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

# 39. Authentication

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

# 40. Authentication Context

Applicationへ渡すContextには、
必要に応じて、

- UserAccount
- Person
- Authentication Method
- Session / Token Context

などを含める。

Applicationは、
必要なIdentity Contextを利用する。

---

# 41. Person and UserAccount

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

# 42. Authorization

Authorizationは、
Authentication後に実行する。

基本構造：

Person
↓
Organization Scope
↓
Production Scope
↓
Role
↓
Permission
↓
Use Case

Authorizationを、
Frontendだけに任せない。

---

# 43. Organization Authorization

Organization Scopeでは、

Person
↓
Membership
↓
Organization
↓
Role
↓
Permission

によって、
Operationの可否を判断する。

---

# 44. Production Authorization

Production Scopeでは、

Person
↓
ProductionDelegate
↓
Production
↓
Role
↓
Permission

によって、
Operationの可否を判断する。

---

# 45. Check In Authorization Context

Check Inでは、
TicketのBusiness Contextを解決する。

例えば、

Ticket
↓
Performance
↓
Production
↓
Organization

というScopeを確認する。

Actorが、
対象ScopeでCheck Inを実行できるかを
Server側で検証する。

---

# 46. Tenant Isolation

Organizationは、
主要なTenant Boundaryである。

Backendでは、
Request UserのScopeを確認し、
別OrganizationのDataへ
アクセスできないようにする。

IDを知っているだけでは、
Accessを許可しない。

---

# 47. Production Isolation

Productionについても、
Scope Isolationを行う。

Production Aの権限しかないActorが、
Production BのDataを
取得・変更できないようにする。

---

# 48. Domain Independence

Domain Layerは、
以下へ直接依存しないことを基本とする。

- PHP Framework
- WordPress
- HTTP
- Database
- ORM
- External API
- File System

これらは、
Infrastructure Boundaryの外側に置く。

---

# 49. PHP as Implementation Technology

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

# 50. PHP Application Structure

PHP Serverでは、
概念的に以下の構造を採用できる。

API
↓
Application
↓
Domain
↓
Infrastructure

例えば、

src/
├── Api/
├── Application/
├── Domain/
└── Infrastructure/

など。

具体的なDirectory Structureは、
Implementation Specificationで決定する。

---

# 51. Framework Independence

PHP Frameworkを利用する場合でも、
Framework固有のCodeを
Domainへ持ち込まない。

例えば、

Controller
→ Framework依存

Repository Implementation
→ Framework / Database依存

Domain Entity
→ Framework非依存

という境界を基本とする。

---

# 52. WordPress Integration

StageArtをWordPress Pluginとして
実装する場合でも、
WordPressをInfrastructureとして扱う。

基本構造：

WordPress REST API
↓
StageArt API Adapter
↓
Application
↓
Domain

WordPress固有Objectを、
Domain Layerへ直接渡さない。

---

# 53. WordPress User Integration

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

# 54. WordPress Data Access

WordPress Databaseを利用する場合でも、
Application / Domainから
直接WP_Queryなどを呼び出さない。

基本構造：

Application
↓
Repository Interface
↓
WordPress Repository Adapter
↓
WordPress Data Access
↓
Database

---

# 55. Infrastructure Layer

Infrastructure Layerは、
Technical Detailを担当する。

例えば、

- Database
- WordPress
- HTTP Client
- Mail
- File Storage
- Authentication Provider
- Queue
- Cache
- Logging

など。

Infrastructureは、
Domain Business Ruleを実装しない。

---

# 56. Database Infrastructure

Database Accessは、
Infrastructureへ閉じ込める。

基本構造：

Repository
↓
Database Adapter
↓
Database

Database Schemaの変更が、
Domain Layerへ直接影響しない構造を目指す。

---

# 57. External Service Integration

External Serviceは、
Infrastructure Boundaryから利用する。

例えば、

- Email
- Calendar
- Storage
- SNS
- Payment
- Authentication

など。

Applicationから、
Integration Interfaceを通じて利用する。

---

# 58. Integration Interface

External Serviceへの依存は、
Interfaceで抽象化できる。

例えば、

NotificationService
CalendarService
FileStorage
PaymentService

など。

Applicationは、
具体的なProviderを直接知らない。

---

# 59. External API Failure

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

# 60. Transaction Boundary

Transaction Boundaryは、
Business Operationを基準に設定する。

例えば、

Check In

というUse Caseでは、
Check In Factの確定を
Transactionとして扱う。

---

# 61. Transaction and Event

Transaction内でBusiness Factを確定し、
その後のEvent処理を
適切な方式で実行する。

基本的な考え方：

Business Fact
↓
Commit
↓
Event Processing

Event処理によって、
Business Factの正本を不安定にしない。

---

# 62. Outbox Pattern

将来的にEventの信頼性が必要になった場合、
Outbox Patternを利用できる。

基本構造：

Transaction
├── Business Fact
└── Outbox Event
        ↓
      Commit
        ↓
 Event Processor
        ↓
 Subscribers

具体的な採用は、
Implementation / Deployment要件に応じて決定する。

---

# 63. Background Processing

時間のかかる処理や、
同期実行が不要な処理は、
Background Processへ切り出せる。

例えば、

- Email Delivery
- Notification
- External Integration
- Report Generation
- Document Processing

など。

---

# 64. Check In Background Processing

Check Inそのものは、
受付結果を即時に返す必要がある。

そのため、

Check In
↓
Immediate Business Fact

を基本とする。

一方、

CheckInCompleted
↓
History Process
↓
Accounting Process
↓
Notification

などは、
要件に応じて同期 / 非同期を選択できる。

---

# 65. Check In Response Timing

受付業務では、
Userへ短時間で結果を返すことを重視する。

基本：

Request
↓
Check In Validation
↓
Check In Fact
↓
Response

後続のHeavy Processを、
Check In ResponseのCritical Pathへ
無条件に含めない。

---

# 66. Accounting Failure

Check In後のAccounting Processで
External / Infrastructure Errorが発生しても、
Check In Factを
不正にRollbackする設計にはしない。

基本的には、

Check In
→ Business Fact確定

その後、

Accounting Process
→ Pending / Retry / Failed

などで処理できる構造を検討する。

Accountingの最終的なTransaction Policyは、
Accounting Architectureで定義する。

---

# 67. History Failure

History ProcessでErrorが発生した場合も、
Check In Factそのものを
不正に削除しない。

必要に応じて、

- Retry
- Pending
- Failed
- Rebuild

などを利用する。

---

# 68. Eventual Consistency

CheckInCompleted後の
HistoryやAccountingが
非同期処理になる場合、
一時的に、

Check In
→ Completed

History
→ Pending

Accounting
→ Pending

という状態が存在する可能性がある。

この場合、
Business FactとProjection / Process Resultを
区別する。

---

# 69. Error Handling

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

# 70. Domain Error

Domain Rule違反は、
Domain Errorとして表現できる。

例えば、

Already Checked In

など。

Application Layerで、
API向けErrorへMappingする。

---

# 71. Application Error

Application Layerでは、
Use Case実行上のErrorを扱う。

例えば、

- Authorization
- Resource Missing
- Conflict
- External Dependency

など。

---

# 72. Infrastructure Error

Infrastructure Errorには、

- Database Failure
- Network Failure
- Storage Failure
- External API Failure

などがある。

Backendでは、
Technical DetailをLogへ記録し、
Clientには必要なErrorだけを返す。

---

# 73. Logging

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

など。

---

# 74. Audit

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

など。

AuditとApplication Logを
混同しない。

---

# 75. Check In Audit

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

などをAudit Contextとして記録する。

---

# 76. Correlation ID

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

これにより、
一つのBusiness Operationを
複数LayerのLogから追跡できる。

---

# 77. Observability

Backendでは、
必要に応じて以下をMonitoringする。

- Request Count
- Response Time
- Error Rate
- Database Performance
- Queue
- External API
- Check In Success
- Check In Failure

---

# 78. Check In Monitoring

受付Operationでは、
Operational Monitoringを行える。

例えば、

- Check In Count
- Check In Error
- Already Checked In
- Network Error
- API Latency

など。

ただし、
Monitoring Dataを
Business Factの正本としない。

---

# 79. Security Boundary

Backendは、
主要なSecurity Boundaryである。

Clientから送信された、

- User ID
- Person ID
- Organization ID
- Production ID
- Ticket ID
- Role
- Permission
- Price
- Status

などを、
無条件に信頼しない。

---

# 80. Input Validation

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

# 81. Output Filtering

Backendは、
Clientへ必要なDataだけを返す。

Internal Database Columnや、
Security Sensitive Dataを
Responseへ含めない。

---

# 82. Secret Management

BackendのSecretは、
Source CodeへHard Codeしない。

対象：

- Database Credential
- API Key
- OAuth Secret
- Encryption Key
- Mail Credential

など。

具体的なSecret Storageは、
Deployment / Security Architectureで定義する。

---

# 83. Rate Limiting

必要に応じて、
APIへのRate Limitingを行う。

特に、

- Login
- Authentication
- Public Search
- Reservation
- Check In
- QR Reception

など。

---

# 84. QR Reception Security

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

---

# 85. Replay Protection

QRやCheck In Requestの
Replayを考慮する。

例えば、

- Already Checked In
- Idempotency
- Expiration
- Ticket State

など。

具体的なSecurity Ruleは、
Security Architectureで定義する。

---

# 86. API Versioning

Backend APIは、
Version Boundaryを持てる構造とする。

例えば、

/api/v1

など。

API Version変更によって、
Domain ModelをVersion依存にしない。

---

# 87. Backend Compatibility

Mobile Clientは、
旧VersionのAPIを利用する可能性がある。

そのため、

- Backward Compatibility
- API Version
- Deprecation
- Migration

を考慮する。

---

# 88. Configuration

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

# 89. Environment

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

# 90. Feature Flag

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

# 91. Backend Testing

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

---

# 92. Domain Testing

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

# 93. Application Testing

Application Testでは、
Use CaseのOrchestrationをTestする。

例えば、

CheckInUseCase

について、

- Authorization
- Ticket Load
- Validation
- Check In
- Persistence
- Event

など。

---

# 94. API Testing

API Testでは、

- Request
- Authentication
- Authorization
- Validation
- Response
- Error

などを確認する。

---

# 95. Repository Testing

Repository Testでは、
PersistenceとのMappingを確認する。

例えば、

Domain Entity
↓
Database
↓
Domain Entity

のMappingが正しいことを確認する。

---

# 96. Integration Testing

Integration Testでは、

- Database
- WordPress
- External API
- Storage
- Queue

などとの接続を確認する。

---

# 97. Check In Testing

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

---

# 98. Check In and Accounting Testing

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

---

# 99. Check In and History Testing

Check In成功後に、

CheckInCompleted
↓
History Process
↓
Audience History

が正しく処理されることをTestする。

---

# 100. End-to-End Backend Flow

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
├── History
└── Accounting

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
├── History
└── Accounting

---

# 101. Backend and Frontend

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

# 102. Backend and Domain Model

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
Repository

---

# 103. Backend and Data Architecture

Data Architectureは、
PersistenceとBusiness Factを定義する。

Backendは、

Application
↓
Domain
↓
Repository
↓
Persistence

という形でDataへアクセスする。

Database Tableを、
Business APIの直接の契約としない。

---

# 104. Backend and API Architecture

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

# 105. Backend and Accounting

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

# 106. Backend and History

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

# 107. Backend and Integration

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

# 108. Backend and WordPress

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

# 109. Backend and PHP

PHPは、
Backend実装の主要Technologyとして
利用できる。

ただし、

PHP Framework
↓
Application / Domain

という直接依存を避け、
Framework固有処理を
Infrastructure / APIへ閉じ込める。

---

# 110. Backend Deployment Boundary

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

# 111. Background Worker

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

---

# 112. Queue

非同期処理が必要な場合、
Queueを利用できる。

基本構造：

Application
↓
Queue
↓
Worker
↓
Application Process

Queue Messageには、
必要なIdentifierとContextだけを
含める。

---

# 113. Queue and Business Fact

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

# 114. Retry

Background Processでは、
Retry可能な処理と
Retry不能な処理を区別する。

例えば、

External API Timeout
→ Retry可能

Invalid Business Data
→ Retryしても改善しない

など。

---

# 115. Dead Letter

Repeated Failureする
Background Jobについては、
Dead Letter / Failed Queueを
利用できる。

Operational Userが、
失敗を確認・再実行できる仕組みを
必要に応じて設ける。

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
Business RuleとAuthorizationを
省略しない。

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
Business FactとProjectionを区別する。

例えば、

Check In Fact
→ Source of Truth

History
→ Derived / Processed Data

Accounting
→ Accounting Fact

など。

具体的なConsistency Ruleは、
Data / Accounting Architectureで定義する。

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
History
↓
Accounting

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

---

# 127. Backend Architecture Decision

Backendでは、
以下のArchitectureを基本とする。

API
↓
Application
↓
Domain
↓
Repository
↓
Infrastructure
↓
Persistence

Client：

Web / Mobile
↓
API

Business Operation：

API
↓
Use Case
↓
Domain

Event：

Business Fact
↓
Domain Event
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
Repository
↓
Infrastructure
↓
Persistence

というLayered Architectureを基本とする。

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
QR Scanner
↓
Ticket Identifier
↓
Check In API

しかし、
Backendでは同じ、

CheckInUseCase
↓
Check In

を利用する。

Check In成功後は、

Check In
↓
CheckInCompleted
├── History Process
└── Accounting Process

という構造を利用する。

Backendの最重要原則は、

「ClientはBusiness Operationを要求し、
BackendがBusiness Ruleを実行し、
DomainがBusiness Factを管理する。」

ことである。

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
- Background Worker

などのTechnologyが変更されても、
StageArtのBusiness RuleとBusiness Factを
一貫して維持できるBackend Architectureを実現する。

---
