# StageArt Blueprint

# 10 - Architecture
# API Architecture

Version : 1.0

---

# Purpose

API Architectureは、
StageArt Applicationと
Web Client、
Mobile Client、
Public Client、
Administrative Clientなどの
Client間に存在するAPI Boundaryを定義する。

API Architectureでは、

- API Boundary
- API Responsibility
- Authentication
- Authorization
- Request / Response
- DTO
- Business Operation
- Check In
- QR Reception
- Error Handling
- Idempotency
- Versioning
- Public API
- Management API
- Mobile API
- Integration API

を定義する。

API Architectureでは、
具体的なPHP Frameworkや
Database Queryの実装詳細までは定義しない。

---

# 1. API Architecture Principles

StageArt APIは、
以下を基本原則とする。

- APIをBusiness Operationの入口として設計する。
- Database CRUDをそのままAPIへ公開しない。
- APIからDomain Entityを直接公開しない。
- Request / ResponseはDTOとして扱う。
- AuthenticationとAuthorizationを分離する。
- AuthorizationはServer Sideで必ず実行する。
- ClientをBusiness Factの正本にしない。
- Mobile ClientとWeb ClientでBusiness Ruleを分けない。
- QR Codeの読み取りとCheck In Business Ruleを分離する。
- Check Inの確定はServer Sideで行う。
- APIはHTTPSを基本とする。
- External Service APIとStageArt APIを分離する。
- API ErrorをClient向けResponseへMappingする。
- API Versionを管理可能な構造にする。
- Retryを考慮し、重要なOperationではIdempotencyを確保する。

---

# 2. API Boundary

StageArt APIは、
ClientとApplication Layerの境界である。

基本構造：

Web Client
↓
StageArt API
↓
Application
↓
Domain

Mobile Client
↓
StageArt API
↓
Application
↓
Domain

Public Client
↓
Public API
↓
Application
↓
Domain

APIは、
Client固有のUIやDevice機能を
Business Logicへ持ち込まない。

---

# 3. API Responsibilities

API Layerの責務：

- Request受付
- Request Parsing
- Authentication Context取得
- Authorization Context取得
- Request Validation
- DTO Mapping
- Application Use Case呼び出し
- Query呼び出し
- Result Mapping
- Error Mapping
- HTTP Response生成

API Layerは、
Business Ruleを実装しない。

---

# 4. API and Application

APIは、
Application Use Caseを呼び出す。

基本構造：

API Request
↓
Request DTO
↓
Application Command
↓
Use Case
↓
Domain
↓
Application Result
↓
Response DTO
↓
API Response

API Controllerは、
Use Caseの内部処理を知らない。

---

# 5. Business Operation API

StageArt APIは、
Business Operationを中心に設計する。

例えば、

Create Reservation

Confirm Reservation

Check In

Confirm Rehearsal

Record Attendance

Create Journal Entry

など。

単純なDatabase CRUDを、
API Contractの中心にしない。

---

# 6. CRUD API Principle

単純なCRUDが適切な場合は、
CRUD型のAPIを利用してよい。

ただし、
Business Ruleを伴うOperationについては、
Business OperationとしてAPIを定義する。

例えば、

ReservationのStatusを直接変更するのではなく、

Check In Reservation

というUse Caseを利用する。

---

# 7. API Example

概念的には、

POST /reservations/{id}/check-in

のようなBusiness Operationを利用できる。

このAPIは、

Reservation.status = checked_in

という単純なData Updateではなく、

Check In Use Case

を実行する。

具体的なURL構造は、
Implementation Specificationで確定する。

---

# 8. Client Types

StageArt APIは、
以下のClientから利用できる。

- Web Client
- Mobile Client
- QR Reception Client
- Public Client
- Administrative Client

Clientの種類によって、
UIやDevice Featureは異なる。

ただし、
同じBusiness Operationについては、
同じApplication Use Caseを利用する。

---

# 9. Web Client API

Web Clientは、
Management Portal、
Public Portal、
Audience Portalなどから
StageArt APIを利用する。

例：

- Organization Management
- Production Management
- Participant Management
- Rehearsal Management
- Ticket Management
- Reservation Management
- Accounting
- Communication
- Document Management

Web Clientは、
APIを通じてApplicationを利用する。

---

# 10. Mobile Client API

Mobile Clientは、
Smartphoneなどから
StageArt APIを利用する。

主な用途：

- Authentication
- Production Information
- Rehearsal
- Participant Operation
- Reservation
- Check In
- QR Reception

Mobile Clientは、
StageArt Databaseへ直接アクセスしない。

---

# 11. QR Reception API

QR Receptionでは、
Mobile ClientがQR Codeを読み取る。

基本Flow：

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
Application
↓
Domain
↓
Check In Result
↓
Mobile Client

QR Codeの読み取りは、
Mobile Clientの責務。

Ticket Validationと
Check In確定は、
Server Sideの責務。

---

# 12. QR Code as Identifier

QR Codeは、
Business Factそのものではない。

QR Codeは、
Issued Ticketなどを識別するための
Artifactとして扱う。

基本構造：

Issued Ticket
↓
QR Ticket
↓
QR Code
↓
QR Scanner
↓
Ticket Identifier
↓
Check In API

QR Codeの内容を、
無条件に信頼しない。

---

# 13. QR Check In Request

Mobile Clientは、
QR Codeを読み取った後、
必要なIdentifierをAPIへ送信する。

概念的なRequest：

Check In Request
- Ticket Identifier
- Performance Context
- Client Context
- Idempotency Information

など。

具体的なRequest DTOは、
Implementation Specificationで定義する。

---

# 14. QR Check In Server Validation

Serverは、
Check In Requestを受け取った後、

- Authentication
- Authorization
- Ticket Existence
- Ticket Validity
- Performance
- Reservation
- Issued Ticket
- Current Check In State
- Business Rule

などを検証する。

Clientが送信した情報だけを、
そのままBusiness Factとして登録しない。

---

# 15. Check In API Flow

Check Inの基本Flow：

Request
↓
Authentication
↓
Authorization
↓
Request Validation
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
Publish CheckInCompleted
↓
Commit
↓
Response

---

# 16. Check In Business Rule

Check InのBusiness Ruleは、
API Controllerに実装しない。

API：

Check In Use Caseを呼び出す。

Application：

Use CaseをOrchestrateする。

Domain：

Check Inが成立する条件を判断する。

Persistence：

Check In Factを保存する。

---

# 17. Check In Response

Check In APIは、
Clientが受付結果を表示できるResponseを返す。

概念的な結果：

- Check In Success
- Already Checked In
- Invalid Ticket
- Ticket Not Found
- Performance Mismatch
- Unauthorized
- Forbidden
- Validation Error
- System Error

Responseの具体的なFormatは、
API Contractで定義する。

---

# 18. Already Checked In

同じTicketが、
複数回読み取られる可能性がある。

例えば、

- 同じQRを連続して読む
- 複数端末から読む
- Network Retry
- Client Timeout後の再送

など。

既にCheck In済みの場合は、
二重にCheck In Factを作成しない。

Server側で、
既存状態を確認する。

---

# 19. Concurrent Check In

複数の受付端末が、
同じTicketを同時に読み取る可能性がある。

例えば、

Device A
↓
Check In API
        \
         Ticket X
        /
Device B
↓
Check In API

この場合でも、
Check In Factが二重作成されないようにする。

具体的なLock、
Unique Constraint、
Transactionなどは、
Data Architecture / Implementation Specificationで定義する。

---

# 20. Idempotency

重要なBusiness Operationでは、
Idempotencyを考慮する。

対象：

- Check In
- Reservation Confirmation
- Ticket Issuance
- Journal Entry
- External Integration
- Notification Delivery

同じRequestが複数回送信されても、
Business Factが不正に重複しないようにする。

---

# 21. Idempotency Key

必要に応じて、
ClientはIdempotency Keyを送信できる。

基本構造：

Client
↓
Idempotency Key
↓
API
↓
Application
↓
Business Operation

Server側では、
同一Keyによる重複処理を防止する。

具体的なStorage方式は、
Implementation Specificationで定義する。

---

# 22. QR Retry

Mobile Clientでは、
Network Timeoutが発生する可能性がある。

例えば、

QR Scan
↓
Check In Request
↓
Server Processing
↓
Response Timeout
↓
Client Retry

というケース。

Serverが既にCheck Inを確定している場合、
Retryによって二重Check Inを作成しない。

IdempotencyまたはBusiness Stateによって、
安全に再送できる構造とする。

---

# 23. Authentication

APIへのAccessでは、
必要に応じてAuthenticationを行う。

基本構造：

Client
↓
Authentication
↓
UserAccount
↓
Person
↓
API

Authentication方式は、
利用するAuthentication Providerに応じて実装する。

API Architectureでは、
Provider固有の詳細を定義しない。

---

# 24. Authentication Context

API Requestでは、
認証されたUserに関するContextを
Applicationへ渡す。

概念的には、

Authentication
↓
UserAccount
↓
Person
↓
Request Context

というMappingを行う。

Application Use Caseは、
必要なPerson Identityを利用する。

---

# 25. Authentication and Person

Authentication Identityと
Business Identityを分離する。

Authentication：

「誰がLoginしているか」

Person：

「StageArt上で誰なのか」

基本構造：

External Identity
↓
UserAccount
↓
Person

APIは、
必要に応じてPerson Contextを
Applicationへ渡す。

---

# 26. Authorization

Authentication後、
Authorizationを実行する。

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

Authorizationは、
Client側だけで判断しない。

Server Sideで必ず検証する。

---

# 27. Organization Authorization

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
API Operationの可否を判断する。

例えば、

Organization Administrator

Production Manager

など。

---

# 28. Production Authorization

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
API Operationの可否を判断する。

PrimaryManagerは、
Production ScopeのFull Accessとして扱う。

---

# 29. Reception Authorization

QR Receptionでは、
QRを読み取れるだけでは
Check Inを許可しない。

受付担当者が、

- 適切なPersonであること
- 適切なOrganization / Production Scopeを持つこと
- Check In Permissionを持つこと

をServer側で確認する。

---

# 30. Scope Resolution

API Requestに対して、
必要なScopeを解決する。

例えば、

Check In Ticket X

の場合、

Ticket
↓
Performance
↓
Production
↓
Organization

というBusiness Contextを解決し、
Request Userが対象Scopeへ
アクセス可能か確認する。

---

# 31. Tenant Isolation

Organizationは、
主要なTenant Boundaryである。

APIは、
Request UserのOrganization Scopeを確認する。

例えば、

GET /organizations/A/productions

を実行した場合、
Organization BのUserが
Organization AのDataを取得できないようにする。

IDを知っているだけでは、
Accessを許可しない。

---

# 32. Production Isolation

Production Scopeについても、
同様にIsolationを行う。

例えば、

Production A

のManagement APIへ、

Production B

だけに権限を持つPersonが
アクセスできないようにする。

---

# 33. Public API

Public APIは、
一般観客など、
Authenticationを必要としないClientから
利用できる場合がある。

対象例：

- Organization Public Profile
- Production Public Page
- Performance Information
- Ticket Information

Public APIから、
Internal Dataを直接返さない。

---

# 34. Public API Data

Public APIは、
Public Projection / DTOを利用する。

基本構造：

Internal Domain Data
↓
Public Projection
↓
Public DTO
↓
Public API
↓
Public Client

これにより、
Internal Dataの誤公開を防ぐ。

---

# 35. Audience API

Audience向けAPIでは、
本人のDataだけを参照できることを基本とする。

例えば、

- My Reservations
- My Tickets
- My Check Ins
- My Audience History

など。

API RequestのPersonと、
取得対象DataのPersonを必ず一致させる。

---

# 36. Management API

Management APIは、
Organization / Productionの運営者向けである。

対象例：

- Organization
- Membership
- Production
- Participant
- Rehearsal
- Performance
- Ticket
- Reservation
- Check In
- Accounting
- Communication
- Document

Authorizationを必須とする。

---

# 37. Mobile Reception API

Mobile Reception Client向けAPIは、
必要な機能に限定する。

主なOperation：

- Authenticate
- Get Reception Context
- Scan Ticket
- Check In
- Get Check In Result

Mobile Clientに、
不要なManagement Dataを
返さない。

---

# 38. Reception Context

Reception Clientは、
受付対象となるPerformance Contextを
取得できる。

概念的には、

Reception User
↓
Authorized Productions
↓
Authorized Performances
↓
Reception Context

という構造。

Mobile Clientは、
現在受付可能なPerformanceを
選択できる。

---

# 39. Performance Context

Check In Requestでは、
必要に応じてPerformance Contextを利用する。

例えば、

Mobile Client
↓
Performance A selected
↓
Scan QR
↓
Check In Ticket
↓
Server verifies Ticket belongs to Performance A

という構造。

Clientが指定したPerformanceを、
Serverが無条件に信頼しない。

Ticket側のBusiness ContextをServerで検証する。

---

# 40. API DTO

APIでは、
Domain Entityを直接Request / Responseに利用しない。

基本構造：

Request DTO
↓
Application Command
↓
Domain Entity

Response：

Domain Entity
↓
Application Result
↓
Response DTO

DTOは、
Client Contractのために設計する。

---

# 41. Request DTO

Request DTOは、
Clientから送信されるDataを表す。

Request DTOは、
Domain Entityの全Propertyを
そのまま公開しない。

Use Caseに必要なInputだけを
受け取る。

---

# 42. Response DTO

Response DTOは、
Clientに必要なDataだけを返す。

例えばCheck In Responseでは、

- Result
- Ticket Display Information
- Performance Information
- Check In Time
- Message

など、
受付画面に必要な情報を返す。

Internal Database Columnを、
そのまま返さない。

---

# 43. API Contract

API Contractは、
以下を定義する。

- Endpoint
- HTTP Method
- Authentication
- Authorization
- Request
- Response
- Error
- Idempotency
- Pagination
- Version
- Rate Limit

具体的なContractは、
Implementation Specificationで定義する。

---

# 44. HTTP Method

HTTP Methodは、
Operationの意味に合わせて利用する。

GET：

参照。

POST：

新規作成またはBusiness Operation。

PUT / PATCH：

Resource Update。

DELETE：

削除。

ただし、
Business Operationについては、
単純なState Updateとして表現しない。

---

# 45. Business Operation Endpoint

例えばCheck Inでは、

PATCH /tickets/{id}

のようにTicket Stateを直接書き換えるよりも、

POST /tickets/{id}/check-in

のように、
Business Operationとして表現することを基本とする。

具体的なEndpoint Namingは、
API Contractで確定する。

---

# 46. Query API

Query APIは、
Business Factを参照する。

例：

- Get Production
- List Performances
- Get Ticket
- Get Reservation
- Get Audience History
- Get Accounting Summary

Query APIは、
Domain Stateを変更しない。

---

# 47. Command API

Command APIは、
Business Operationを実行する。

例：

- Create Production
- Create Reservation
- Confirm Reservation
- Issue Ticket
- Check In
- Confirm Rehearsal
- Record Attendance
- Post Journal Entry

Command APIは、
必要なBusiness RuleをApplication / Domainへ委譲する。

---

# 48. Pagination

大量Dataを返すAPIでは、
Paginationを利用する。

対象例：

- Participants
- Reservations
- Performances
- Rehearsals
- Journal Entries
- HistoricalActivity

Paginationは、
Client PerformanceとServer Performanceのために利用する。

具体的なPagination方式は、
API Contractで定義する。

---

# 49. Filtering

List APIでは、
必要に応じてFilteringを提供する。

例えば、

Reservations

について、

- Performance
- Date
- Status
- Ticket Type

などでFilterできる。

Filter条件は、
Authorization Scopeを越えない。

---

# 50. Sorting

List APIでは、
必要に応じてSortingを提供する。

Sortingは、
Business Meaningまたは
Technical Performanceを考慮して定義する。

Clientから任意のDatabase Columnを
そのまま指定できる設計は避ける。

---

# 51. Search

Search APIでは、
Search Projection / Indexを利用できる。

基本構造：

Business Fact
↓
Search Projection
↓
Search API
↓
Client

Search Indexを、
Business Factの正本にしない。

---

# 52. API Error Model

API Errorは、
Clientが処理可能な形で返す。

概念的なError Category：

- Authentication Error
- Authorization Error
- Validation Error
- Business Rule Error
- Resource Not Found
- Conflict
- Integration Error
- Infrastructure Error

内部Exceptionを、
そのままClientへ返さない。

---

# 53. Authentication Error

Authenticationに失敗した場合、

- 未認証
- Token Invalid
- Session Expired

などを、
API Errorとして返す。

具体的なHTTP Statusは、
API Contractで定義する。

---

# 54. Authorization Error

Authorizationに失敗した場合、

- Permission不足
- Scope外
- Production Access不可
- Organization Access不可

などを、
適切なAPI ErrorへMappingする。

Clientへ、
不要な内部Security情報を返さない。

---

# 55. Business Rule Error

Business Rule違反は、
Domain ErrorからAPI ErrorへMappingする。

例えば、

Check In済みTicket

など。

Domain Errorを、
そのまま内部Class名などで
Clientへ返さない。

---

# 56. Conflict

同じResourceに対して、
競合するOperationが発生した場合、
Conflictとして扱うことができる。

例：

- Already Checked In
- Duplicate Reservation
- Duplicate Issuance
- Concurrent Update

具体的なResponse Formatは、
API Contractで定義する。

---

# 57. Validation Error

Request Formatに問題がある場合、
Validation Errorとして返す。

例：

- Required Field Missing
- Invalid Format
- Invalid Identifier
- Invalid Date
- Invalid Quantity

Validation Errorは、
Domain Rule Errorとは区別する。

---

# 58. Infrastructure Error

DatabaseやExternal Serviceなどの
Infrastructure Errorは、
内部でLoggingする。

Clientには、
必要以上のTechnical Detailを返さない。

例えば、

Database Connection Failed

という内部情報を、
そのままClientへ返さない。

---

# 59. Integration Error

External Serviceとの通信失敗は、
Integration Errorとして扱う。

例：

- Calendar Service Unavailable
- Email Service Timeout
- Storage Failure
- SNS API Failure

必要に応じて、

- Retry
- Queue
- Pending
- Failed

などのApplication Processへ渡す。

---

# 60. HTTP Status Mapping

APIでは、
Application Errorを適切なHTTP Responseへ
Mappingする。

概念的には、

Authentication Failure
→ 4xx

Authorization Failure
→ 4xx

Validation Error
→ 4xx

Business Conflict
→ 4xx

Not Found
→ 4xx

Infrastructure Failure
→ 5xx

External Service Failure
→ 5xxまたは適切なApplication Error

とする。

具体的なStatus Codeは、
API Contractで確定する。

---

# 61. Security

APIは、
Security Boundaryとして扱う。

基本原則：

- HTTPS
- Authentication
- Authorization
- Input Validation
- Output Filtering
- Rate Limiting
- Audit
- Error Sanitization
- Secret Protection

Clientから送られたDataを、
信頼しない。

---

# 62. HTTPS

Production Environmentでは、
API通信をHTTPSで行う。

特に、

- Authentication
- Reservation
- Ticket
- Check In
- Accounting

などのDataを、
平文通信しない。

---

# 63. Rate Limiting

Public APIやAuthentication APIでは、
必要に応じてRate Limitingを行う。

対象例：

- Login
- Authentication
- Public Search
- Reservation
- Check In
- QR Scan

具体的なRate Limitは、
Deployment / Security Architectureで定義する。

---

# 64. QR Rate Limiting

QR受付では、
同一Ticketや同一Clientからの
異常な大量Requestを考慮する。

ただし、
正規の受付処理を妨げないようにする。

Rate Limitは、
SecurityとOperational Requirementの
両方を考慮して設定する。

---

# 65. Input Trust

API Requestの以下の情報を、
無条件に信頼しない。

- User ID
- Person ID
- Organization ID
- Production ID
- Performance ID
- Ticket ID
- Permission
- Role
- Price
- Status

Server Sideで、
実際のDomain Dataから検証する。

---

# 66. Client Supplied Role

Clientが、

role = admin

などのAuthorization情報を
Requestへ含めても、
それをSecurity Contextとして利用しない。

Authorizationは、
Server SideでUser / Scope / Role / Permissionから
解決する。

---

# 67. Client Supplied Price

ReservationやTicket処理で、
Clientが送信したPriceを
そのままAccounting Factに利用しない。

Server側のTicket Price / Business Ruleを
正本として利用する。

---

# 68. Client Supplied Performance

Check Inなどで、
ClientがPerformance IDを送信した場合でも、
Server側でIssued TicketやReservationとの
整合性を確認する。

Client Contextは、
Validation対象であり、
Business Factそのものではない。

---

# 69. API Audit

重要なAPI Operationは、
Audit対象とする。

例：

- Login
- Authorization Failure
- Check In
- Ticket Issuance
- Reservation Confirmation
- Journal Entry
- Document Access
- Permission Change

Audit Dataは、
Business Factとは分離する。

---

# 70. API Logging

APIでは、
必要なRequest / Response情報を
Loggingできる。

ただし、

- Password
- Token
- Secret
- 不要なPersonal Data

などを、
Loggingしない。

---

# 71. API Versioning

APIは、
将来的なVersion変更を考慮する。

基本的には、

v1

などのVersion Boundaryを利用できる。

Versioningは、
Client Contractを安定させるために利用する。

---

# 72. API Version Change

API Versionを変更する場合は、

- Request Contract
- Response Contract
- Authentication
- Authorization
- Business Operation
- Client Compatibility

への影響を確認する。

Domain Modelを、
API Versionに従属させない。

---

# 73. Backward Compatibility

既存Clientを壊さないことを基本とする。

例えばMobile Clientが
旧API Versionを利用している場合、
即座に新Versionへ強制変更しない。

必要に応じて、
旧Versionを一定期間維持する。

---

# 74. Mobile API Compatibility

Mobile Applicationは、
Web Clientよりも
更新タイミングが遅れる可能性がある。

そのため、
Mobile APIでは特に、

- Backward Compatibility
- Versioning
- Deprecation
- Migration

を考慮する。

---

# 75. Mobile API Offline

初期Architectureでは、
重要なBusiness Operationを
Offlineで確定しない。

特に、

- Check In
- Reservation
- Ticket Issuance
- Accounting

など。

Offline Check Inを実装する場合は、
別途Security / Consistency Architectureを定義する。

---

# 76. Mobile API Session

Mobile Clientは、
Authentication Session / Tokenを
安全に管理する。

APIは、
Session / Tokenを検証し、
Person Contextを解決する。

Mobile Clientへ、
Database Credentialなどを
提供しない。

---

# 77. Reception Device Registration

必要に応じて、
受付端末を特定する仕組みを導入できる。

例えば、

Reception User
+
Registered Device
+
Production Scope

によって、
受付操作を管理する。

ただし、
Device Identityだけで
Business Permissionを付与しない。

---

# 78. Device and User Separation

受付端末と受付担当者を分離する。

Device：

「どの端末から操作したか」

Person：

「誰が操作したか」

Permission：

「何をしてよいか」

をそれぞれ別のConceptとして扱う。

---

# 79. Check In Audit Context

Check In APIでは、
必要に応じて、

- Person
- Device
- Production
- Performance
- Ticket
- Timestamp

などをAudit Contextとして記録する。

ただし、
Audit ContextとCheck In Factを混同しない。

---

# 80. API Transaction Boundary

API Request自体を、
Business Transactionの正本としない。

APIは、
Application Use Caseを呼び出す。

Transaction Boundaryは、
Application Layerで定義する。

例えば、

POST Check In
↓
Check In Use Case
↓
Transaction
↓
Check In Fact
↓
Commit

とする。

---

# 81. API and Domain Event

APIによってBusiness Operationが成功した場合、
Domain Eventが発生する場合がある。

例えば、

Check In API
↓
Check In
↓
CheckInCompleted
├── History
└── Accounting

API Clientは、
Domain Eventを直接管理しない。

---

# 82. Event Exposure

Domain Eventを、
そのままPublic API Eventとして
公開するとは限らない。

External ClientへEventを通知する必要がある場合は、
Public Event Contractを別途定義する。

Domain EventとExternal Event Contractを
分離する。

---

# 83. Webhook

将来的に、
外部SystemへStageArtのBusiness Eventを
通知する必要がある場合、
Webhookを利用できる。

基本構造：

StageArt Event
↓
Integration Process
↓
Webhook
↓
External System

Webhookの詳細は、
Integration Architectureで定義する。

---

# 84. API and External Integration

StageArt APIとExternal Service APIを分離する。

StageArt API：

StageArt Clientの入口。

External API：

StageArtが外部Serviceを利用するための入口。

基本構造：

Client
↓
StageArt API
↓
Application
↓
Integration Interface
↓
External API

Clientが、
StageArtを経由せず
External ServiceをBusiness Ruleの正本として
直接利用する構造を基本としない。

---

# 85. API and Database

APIからDatabaseへ
直接アクセスしない。

禁止する基本構造：

Client
↓
API
↓
Database

基本構造：

Client
↓
API
↓
Application
↓
Domain / Repository
↓
Database

これにより、
Business RuleをApplication / Domainへ
集約する。

---

# 86. API and PHP

StageArt ServerをPHPで実装する場合でも、
API ArchitectureはLayered Architectureを維持する。

基本構造：

HTTP Request
↓
PHP API Controller
↓
Application Service
↓
Domain
↓
Repository
↓
Database

PHP Framework固有の処理は、
Presentation / API / Infrastructure側へ
閉じ込める。

---

# 87. WordPress API Boundary

StageArtをWordPress Pluginとして実装する場合、
WordPress REST APIなどを
API Infrastructureとして利用できる。

基本構造：

HTTP Request
↓
WordPress API Adapter
↓
StageArt API Layer
↓
Application
↓
Domain

WordPress APIのRequest Objectなどを、
Domainへ直接渡さない。

---

# 88. WordPress Authentication

WordPress Userを
Authentication Infrastructureとして
利用できる場合がある。

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

というMappingを行う。

---

# 89. Public Endpoint

Public Endpointでは、
Authenticationを要求しない場合がある。

ただし、

「Public」

は、

「すべてのInternal Dataを返してよい」

という意味ではない。

Public Projectionを利用する。

---

# 90. Management Endpoint

Management Endpointでは、
AuthenticationとAuthorizationを
原則として必須とする。

Scopeを解決し、

Person
↓
Organization / Production
↓
Role
↓
Permission
↓
Operation

を確認する。

---

# 91. Audience Endpoint

Audience Endpointでは、
本人のDataを中心に扱う。

例えば、

GET My Reservations

GET My Tickets

GET My Audience History

など。

URL上でPerson IDを指定できる場合でも、
Request UserがそのPersonへ
アクセス可能かをServer側で検証する。

---

# 92. File API

Documentに関連するFile操作も、
Authorizationを必要とする。

基本構造：

Client
↓
Document API
↓
Authorization
↓
Document
↓
Storage Adapter
↓
External Storage

External StorageのURLを、
直接公開することを基本としない。

---

# 93. File Download

File Downloadでは、
Document Scopeを確認する。

例えば、

Organization Document

Production Document

Personal Document

など。

UserがDocument IDを知っているだけで、
Downloadできる設計にしない。

---

# 94. API Pagination and Large Data

大量Dataを返すOperationでは、
Server側のPerformanceを考慮する。

特に、

- Journal Entry
- Audience History
- Reservation
- Participant
- HistoricalActivity

など。

Clientへ、
必要以上のDataを返さない。

---

# 95. API Response Stability

Response DTOは、
Domain Entityの内部構造変更から
独立させる。

Domain EntityにPropertyが追加されても、
API Contractが自動的に変わらないようにする。

---

# 96. API Request Stability

Request DTOも、
Domain Entityと分離する。

Clientから送られたDataを、
Domain EntityへそのままHydrateしない。

Application CommandへMappingし、
必要なBusiness Ruleを通す。

---

# 97. API Testing

APIは、
以下の観点からTestする。

- Authentication
- Authorization
- Request Validation
- Business Operation
- Response Contract
- Error
- Idempotency
- Concurrency
- Tenant Isolation
- Production Isolation

---

# 98. Check In API Testing

Check In APIでは、
最低限以下を検証する。

- Valid Ticket
- Invalid Ticket
- Already Checked In
- Wrong Performance
- Unauthorized User
- Forbidden User
- Multiple Device
- Duplicate Request
- Network Retry
- Concurrent Request

---

# 99. API Contract Testing

ClientとServer間のContractを、
Testで保証する。

特にMobile Clientでは、

- Request Schema
- Response Schema
- Error Schema
- Version

を確認する。

---

# 100. API Security Testing

API Security Testでは、

- Unauthorized Access
- Cross Organization Access
- Cross Production Access
- ID Enumeration
- Role Manipulation
- Token Abuse
- Replay
- Duplicate Request

などを検証する。

---

# 101. API Architecture and Domain Model

API Architectureは、
Domain Modelを変更しない。

Domain Model：

Business Concept
Business Fact
Business Rule

API：

ClientとのCommunication Contract

である。

API都合で、
Domain Entityを変更しない。

必要な場合は、
DTO / Mappingを利用する。

---

# 102. API Architecture and Application Architecture

Application Architecture：

Application内部のLayerとUse Case。

API Architecture：

ClientとApplicationのCommunication Boundary。

基本構造：

Client
↓
API
↓
Application
↓
Domain

APIは、
Application Use Caseを公開する入口であり、
Business Ruleの実装場所ではない。

---

# 103. API Architecture and Data Architecture

Data Architecture：

Business FactとPersistence。

API Architecture：

ClientからBusiness Factを
操作・参照するためのContract。

基本構造：

API
↓
Application
↓
Domain
↓
Repository
↓
Persistence

APIは、
Database Schemaを直接公開しない。

---

# 104. API Architecture and Mobile Client

Mobile Clientでは、

Camera
↓
QR Scanner
↓
QR Payload
↓
Check In API

という流れを利用する。

しかし、

QR Scanner
↓
Database

という構造にはしない。

必ず、

QR Scanner
↓
API
↓
Application
↓
Domain

を通す。

---

# 105. API Architecture and Accounting

Check In APIが成功すると、

CheckInCompleted
↓
Accounting Process
↓
Ticket Revenue
↓
Journal Entry

というApplication Processを
起動できる。

Mobile Clientは、
Accounting APIを直接呼び出して
Ticket Revenueを作成しない。

---

# 106. API Architecture and History

Check In APIが成功すると、

CheckInCompleted
↓
History Process
↓
Audience History

というApplication Processを
起動できる。

Mobile Clientは、
Audience Historyを直接作成しない。

---

# 107. API Entry Point Consistency

同じBusiness Operationについては、
Clientが異なっても
同じApplication Use Caseを利用する。

例えば、

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

とする。

---

# 108. API Architecture Principle

StageArt APIの最重要原則：

「APIはDatabaseへの入口ではなく、
Business Operationへの入口である。」

そのため、

Client
↓
API
↓
Application
↓
Domain

という構造を維持する。

さらにQR受付では、

Camera
↓
QR Scanner
↓
Check In API
↓
Application
↓
Domain
↓
Check In
↓
CheckInCompleted
├── History
└── Accounting

という構造を維持する。

これにより、

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
API Architectureを実現する。

---
