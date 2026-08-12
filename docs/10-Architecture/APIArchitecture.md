# StageArt Blueprint

# 10 - Architecture
# API Architecture

Version : 1.1

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
- Web Check In
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
- Web ClientとMobile ClientのCheck Inは同一のApplication Use Caseを利用する。
- APIはHTTPSを基本とする。
- External Service APIとStageArt APIを分離する。
- API ErrorをClient向けResponseへMappingする。
- API Versionを管理可能な構造にする。
- Retryを考慮し、重要なOperationではIdempotencyを確保する。
- Web ClientからのCheck Inは一覧操作を入口とすることができる。
- Mobile ClientからのCheck InはQR Scanを入口とすることができる。
- Clientごとに異なるBusiness Ruleを実装しない。

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

Check In

というUse Caseを利用する。

---

# 7. API Example

概念的には、

POST /reservations/{id}/check-in

のようなBusiness Operationを利用できる。

また、
Issued Ticketを直接対象とする場合は、

POST /tickets/{id}/check-in

のようなOperationも利用できる。

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
- Check In Management
- Accounting
- Communication
- Document Management

Web Clientは、
APIを通じてApplicationを利用する。

---

# 10. Web Check In API

Web Clientからも、
Check Inを実行できる。

Web Check Inでは、
QR Codeを必須としない。

基本Flow：

Web Client
↓
Performance
↓
Reservation / Issued Ticket List
↓
Search / Filter
↓
Check In対象選択
↓
Check In API
↓
Application
↓
Domain
↓
Check In Result
↓
Web Client

Web Clientでは、
受付担当者が一覧から対象Ticketを確認し、
Check Inを実行できる。

---

# 11. Web Check In List API

Web Check In画面では、
Check In対象を一覧取得できるAPIを利用する。

概念的には、

GET /performances/{id}/tickets

または、

GET /performances/{id}/reservations

などのQuery APIを利用できる。

一覧では、
必要に応じて以下を取得できる。

- Person
- Reservation
- Issued Ticket
- Ticket Type
- Performance
- Check In Status
- Check In Time
- Ticket Identifier

具体的なEndpointとResponse DTOは、
API Contractで定義する。

---

# 12. Web Check In List Filtering

Web Check In一覧では、
必要に応じてFilterを利用できる。

例：

- 未受付
- 受付済み
- Person Name
- Ticket Number
- Ticket Type
- Reservation Status
- Performance

Filterは、
Request UserがAuthorization Scope内で
参照できるDataに限定する。

Clientが任意のDatabase Columnを
直接Filter条件として指定する設計は避ける。

---

# 13. Web Check In Search

受付担当者が、
対象Ticketを検索できるようにする。

例えば、

- Person Name
- Ticket Number
- Reservation Number
- Ticket Identifier

など。

Searchは、
Query APIとして提供する。

Search APIは、
Check In Factを変更しない。

検索結果からCheck Inを実行する場合は、
別途Check In Command APIを呼び出す。

---

# 14. Web Manual Check In

Web ClientからのManual Check Inは、
QR Codeを使用しない。

基本Flow：

Web Client
↓
Reservation / Issued Ticket List
↓
対象Ticket選択
↓
Check In Action
↓
Check In API
↓
Check In Use Case
↓
Check In
↓
CheckInCompleted

Web Clientが、
Check In Statusを直接変更しない。

---

# 15. Web Check In Request

Web ClientからのCheck In Requestでは、
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

Clientが送信したPerformanceや
Ticket情報だけを無条件に信頼しない。

Server側で、
対象Dataの整合性を検証する。

---

# 16. Web Check In Server Validation

Web ClientからCheck In Requestを受け取った場合、
Serverは、

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

Web Clientの一覧表示時点で
Check In可能と判断されていても、
Check In実行時に再度Server側で検証する。

---

# 17. Web Check In Result

Web Clientは、
Check In APIのResponseを利用して
受付結果を表示する。

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

一覧上の表示Stateは、
ServerのBusiness Factから取得する。

---

# 18. Web Multiple Check In

Web Clientでは、
必要に応じて複数Ticketを選択して
Check Inを実行できる。

基本Flow：

Web Client
↓
Check In List
↓
Multiple Ticket Selection
↓
Check In API
↓
Check In Use Case
↓
Individual Check In Processing
↓
Result

ただし、
複数TicketのCheck Inは、
単純なDatabase Bulk Updateとして実装しない。

各Ticketについて、
必要なBusiness Rule、
Authorization、
状態検証を行う。

具体的なBulk APIの採用は、
Implementation Specificationで決定する。

---

# 19. Web Multiple Check In Result

複数Ticketを一括処理する場合、
全件成功だけでなく、
個別結果を返せる構造とする。

例えば、

Ticket A
→ Success

Ticket B
→ Already Checked In

Ticket C
→ Invalid Ticket

Ticket D
→ Forbidden

など。

Response DTOでは、
必要に応じて個別Resultを返す。

---

# 20. Web Check In Concurrency

Web ClientからのManual Check Inと、
Mobile ClientからのQR Check Inが
同時に発生する可能性を考慮する。

例えば、

Web Client
↓
Ticket X
↓
Check In API

と同時に、

Mobile Client
↓
QR Ticket X
↓
Check In API

が実行される場合でも、
Check In Factを二重作成しない。

具体的なLock、
Unique Constraint、
Transactionなどは、
Data Architecture / Implementation Specificationで定義する。

---

# 21. Mobile Client API

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

# 22. QR Reception API

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

# 23. QR Code as Identifier

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

# 24. QR Check In Request

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

# 25. QR Check In Server Validation

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

# 26. Common Check In API

Web ClientとMobile Clientは、
Check Inの入口が異なる。

Web：

Web Client
↓
Reservation / Issued Ticket List
↓
Check In API

Mobile：

Mobile Client
↓
QR Scanner
↓
Ticket Identifier
↓
Check In API

しかし、
API以下では同じApplication Use Caseを利用する。

共通構造：

Check In API
↓
Check In Use Case
↓
Check In Domain
↓
Check In Fact

Clientによって、
Check In Business Ruleを分けない。

---

# 27. Check In API Flow

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

Web ClientとMobile Clientの
どちらから呼び出されても、
基本的なApplication Flowは共通とする。

---

# 28. Check In Business Rule

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

# 29. Check In Response

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

# 30. Already Checked In

同じTicketが、
複数回読み取られる可能性がある。

例えば、

- 同じQRを連続して読む
- 複数端末から読む
- Web ClientとMobile Clientから同時に操作する
- Network Retry
- Client Timeout後の再送

など。

既にCheck In済みの場合は、
二重にCheck In Factを作成しない。

Server側で、
既存状態を確認する。

---

# 31. Concurrent Check In

複数の受付端末やClientが、
同じTicketを同時に操作する可能性がある。

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

または、

Web Client
↓
Check In API
        \
         Ticket X
        /
Mobile Client
↓
Check In API

というケース。

この場合でも、
Check In Factが二重作成されないようにする。

具体的なLock、
Unique Constraint、
Transactionなどは、
Data Architecture / Implementation Specificationで定義する。

---

# 32. Idempotency

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

# 33. Idempotency Key

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

# 34. QR Retry

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

# 35. Web Retry

Web Clientでも、
Network TimeoutやBrowser側の通信失敗によって、
Check In Requestが再送される可能性がある。

例えば、

Check In Action
↓
Check In Request
↓
Server Processing
↓
Response Timeout
↓
User Retry
↓
Check In Request

というケース。

Serverが既にCheck Inを確定している場合、
Retryによって二重Check Inを作成しない。

IdempotencyまたはBusiness Stateによって、
安全に再送できる構造とする。

---

# 36. Authentication

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

# 37. Authentication Context

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

# 38. Authentication and Person

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

# 39. Authorization

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

# 40. Organization Authorization

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

# 41. Production Authorization

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

# 42. Reception Authorization

QR Receptionでは、
QRを読み取れるだけでは
Check Inを許可しない。

受付担当者が、

- 適切なPersonであること
- 適切なOrganization / Production Scopeを持つこと
- Check In Permissionを持つこと

をServer側で確認する。

Web Receptionでも、
同じAuthorization Ruleを適用する。

---

# 43. Scope Resolution

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

Web ClientからのCheck Inでも、
Mobile ClientからのCheck Inでも、
同じScope Resolutionを利用する。

---

# 44. Tenant Isolation

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

# 45. Production Isolation

Production Scopeについても、
同様にIsolationを行う。

例えば、

Production A

のManagement APIへ、

Production B

だけに権限を持つPersonが
アクセスできないようにする。

---

# 46. Public API

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

# 47. Public API Data

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

# 48. Audience API

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

# 49. Management API

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

# 50. Mobile Reception API

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

# 51. Reception Context

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

Web Clientについても、
必要に応じて同じReception Contextを
利用できる。

---

# 52. Web Reception Context

Web Receptionでは、
受付対象Performanceを選択し、
対象Performanceの
Reservation / Issued Ticket Listを取得できる。

基本構造：

Reception User
↓
Authorized Productions
↓
Authorized Performances
↓
Performance
↓
Reservation / Issued Ticket List
↓
Check In

Web Clientは、
Authorization Scope内のPerformanceのみ
一覧に表示できる。

---

# 53. Performance Context

Check In Requestでは、
必要に応じてPerformance Contextを利用する。

例えば、

Web Client
↓
Performance A selected
↓
Ticket List
↓
Check In Ticket
↓
Server verifies Ticket belongs to Performance A

または、

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

# 54. API DTO

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

# 55. Request DTO

Request DTOは、
Clientから送信されるDataを表す。

Request DTOは、
Domain Entityの全Propertyを
そのまま公開しない。

Use Caseに必要なInputだけを
受け取る。

---

# 56. Response DTO

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

# 57. API Contract

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

# 58. HTTP Method

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

# 59. Business Operation Endpoint

例えばCheck Inでは、

PATCH /tickets/{id}

のようにTicket Stateを直接書き換えるよりも、

POST /tickets/{id}/check-in

のように、
Business Operationとして表現することを基本とする。

具体的なEndpoint Namingは、
API Contractで確定する。

---

# 60. Query API

Query APIは、
Business Factを参照する。

例：

- Get Production
- List Performances
- Get Ticket
- Get Reservation
- Get Audience History
- Get Accounting Summary
- Get Check In List

Query APIは、
Domain Stateを変更しない。

---

# 61. Check In List Query

Check In一覧を表示するQueryでは、
必要なBusiness Dataをまとめて取得できる。

例えば、

- Person
- Reservation
- Issued Ticket
- Ticket Type
- Check In Status
- Check In Time

など。

このQueryは、
Web Clientの受付画面で利用できる。

Queryは、
Check Inそのものを実行しない。

Check Inを実行する場合は、
別途Command APIを呼び出す。

---

# 62. Command API

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

# 63. Pagination

大量Dataを返すAPIでは、
Paginationを利用する。

対象例：

- Participants
- Reservations
- Performances
- Rehearsals
- Journal Entries
- HistoricalActivity
- Check In List

Paginationは、
Client PerformanceとServer Performanceのために利用する。

具体的なPagination方式は、
API Contractで定義する。

---

# 64. Filtering

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

Check In Listでは、

- Check In Status
- Person Name
- Ticket Number
- Ticket Type

などでFilterできる。

Filter条件は、
Authorization Scopeを越えない。

---

# 65. Sorting

List APIでは、
必要に応じてSortingを提供する。

Sortingは、
Business Meaningまたは
Technical Performanceを考慮して定義する。

Clientから任意のDatabase Columnを
そのまま指定できる設計は避ける。

---

# 66. Search

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

# 67. API Error Model

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

# 68. Authentication Error

Authenticationに失敗した場合、

- 未認証
- Token Invalid
- Session Expired

などを、
API Errorとして返す。

具体的なHTTP Statusは、
API Contractで定義する。

---

# 69. Authorization Error

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

# 70. Business Rule Error

Business Rule違反は、
Domain ErrorからAPI ErrorへMappingする。

例えば、

Check In済みTicket

など。

Domain Errorを、
そのまま内部Class名などで
Clientへ返さない。

---

# 71. Conflict

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

# 72. Validation Error

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

# 73. Infrastructure Error

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

# 74. Integration Error

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

# 75. HTTP Status Mapping

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

# 76. Security

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

# 77. HTTPS

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

# 78. Rate Limiting

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

# 79. QR Rate Limiting

QR受付では、
同一Ticketや同一Clientからの
異常な大量Requestを考慮する。

ただし、
正規の受付処理を妨げないようにする。

Rate Limitは、
SecurityとOperational Requirementの
両方を考慮して設定する。

---

# 80. Input Trust

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

# 81. Client Supplied Role

Clientが、

role = admin

などのAuthorization情報を
Requestへ含めても、
それをSecurity Contextとして利用しない。

Authorizationは、
Server SideでUser / Scope / Role / Permissionから
解決する。

---

# 82. Client Supplied Price

ReservationやTicket処理で、
Clientが送信したPriceを
そのままAccounting Factに利用しない。

Server側のTicket Price / Business Ruleを
正本として利用する。

---

# 83. Client Supplied Performance

Check Inなどで、
ClientがPerformance IDを送信した場合でも、
Server側でIssued TicketやReservationとの
整合性を確認する。

Client Contextは、
Validation対象であり、
Business Factそのものではない。

---

# 84. API Audit

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

# 85. API Logging

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

# 86. API Versioning

APIは、
将来的なVersion変更を考慮する。

基本的には、

v1

などのVersion Boundaryを利用できる。

Versioningは、
Client Contractを安定させるために利用する。

---

# 87. API Version Change

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

# 88. Backward Compatibility

既存Clientを壊さないことを基本とする。

例えばMobile Clientが
旧API Versionを利用している場合、
即座に新Versionへ強制変更しない。

必要に応じて、
旧Versionを一定期間維持する。

---

# 89. Mobile API Compatibility

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

# 90. Mobile API Offline

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

# 91. Mobile API Session

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

# 92. Reception Device Registration

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

# 93. Device and User Separation

受付端末と受付担当者を分離する。

Device：

「どの端末から操作したか」

Person：

「誰が操作したか」

Permission：

「何をしてよいか」

をそれぞれ別のConceptとして扱う。

---

# 94. Check In Audit Context

Check In APIでは、
必要に応じて、

- Person
- Device
- Client Type
- Production
- Performance
- Ticket
- Timestamp

などをAudit Contextとして記録する。

ただし、
Audit ContextとCheck In Factを混同しない。

---

# 95. API Transaction Boundary

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

# 96. API and Domain Event

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

# 97. Event Exposure

Domain Eventを、
そのままPublic API Eventとして
公開するとは限らない。

External ClientへEventを通知する必要がある場合は、
Public Event Contractを別途定義する。

Domain EventとExternal Event Contractを
分離する。

---

# 98. Webhook

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

# 99. API and External Integration

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

# 100. API and Database

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

# 101. API and PHP

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

# 102. WordPress API Boundary

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

# 103. WordPress Authentication

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

# 104. Public Endpoint

Public Endpointでは、
Authenticationを要求しない場合がある。

ただし、

「Public」

は、

「すべてのInternal Dataを返してよい」

という意味ではない。

Public Projectionを利用する。

---

# 105. Management Endpoint

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

# 106. Audience Endpoint

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

# 107. File API

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

# 108. File Download

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

# 109. API Pagination and Large Data

大量Dataを返すOperationでは、
Server側のPerformanceを考慮する。

特に、

- Journal Entry
- Audience History
- Reservation
- Participant
- HistoricalActivity
- Check In List

など。

Clientへ、
必要以上のDataを返さない。

---

# 110. API Response Stability

Response DTOは、
Domain Entityの内部構造変更から
独立させる。

Domain EntityにPropertyが追加されても、
API Contractが自動的に変わらないようにする。

---

# 111. API Request Stability

Request DTOも、
Domain Entityと分離する。

Clientから送られたDataを、
Domain EntityへそのままHydrateしない。

Application CommandへMappingし、
必要なBusiness Ruleを通す。

---

# 112. API Testing

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

# 113. Check In API Testing

Check In APIでは、
最低限以下を検証する。

- Valid Ticket
- Invalid Ticket
- Already Checked In
- Wrong Performance
- Unauthorized User
- Forbidden User
- Multiple Device
- Web Client
- Mobile Client
- Duplicate Request
- Network Retry
- Concurrent Request

---

# 114. Web Check In API Testing

Web Check Inでは、
最低限以下を検証する。

- Performance Selection
- Check In List取得
- Search
- Filtering
- Individual Check In
- Multiple Check In
- Already Checked In
- Unauthorized User
- Forbidden User
- Concurrent Check In
- Network Retry
- Duplicate Request

---

# 115. API Contract Testing

ClientとServer間のContractを、
Testで保証する。

特にMobile Clientでは、

- Request Schema
- Response Schema
- Error Schema
- Version

を確認する。

Web Clientについても、
Check In List APIと
Check In Command APIの
Contractを確認する。

---

# 116. API Security Testing

API Security Testでは、

- Unauthorized Access
- Cross Organization Access
- Cross Production Access
- ID Enumeration
- Role Manipulation
- Token Abuse
- Replay
- Duplicate Request
- Web Check In Authorization
- QR Check In Authorization

などを検証する。

---

# 117. API Architecture and Domain Model

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

# 118. API Architecture and Application Architecture

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

# 119. API Architecture and Data Architecture

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

# 120. API Architecture and Mobile Client

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

# 121. API Architecture and Web Client

Web Clientでは、

Performance
↓
Reservation / Issued Ticket List
↓
Search / Filter
↓
Ticket Selection
↓
Check In API

という流れを利用できる。

Web Clientは、
QR Codeを必須としない。

Web ClientのCheck Inも、
Mobile ClientのQR Check Inも、
同じCheck In Use Caseを利用する。

---

# 122. API Architecture and Accounting

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

Web Clientでも、
Mobile Clientでも、
Accounting APIを直接呼び出して
Ticket Revenueを作成しない。

---

# 123. API Architecture and History

Check In APIが成功すると、

CheckInCompleted
↓
History Process
↓
Audience History

というApplication Processを
起動できる。

Web Clientでも、
Mobile Clientでも、
Audience Historyを直接作成しない。

---

# 124. API Entry Point Consistency

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

# 125. Web and Mobile Check In Consistency

Web ClientとMobile Clientでは、
Check Inの操作方法は異なる。

Web：

一覧から対象Ticketを選択する。

Mobile：

QR CodeをScanしてTicketを識別する。

しかし、
Check In後に生成されるBusiness Factは
同一である。

基本構造：

Web Client
↓
Check In API
↓
Check In Use Case
↓
Check In

Mobile Client
↓
Check In API
↓
Check In Use Case
↓
Check In

どちらの場合も、

Check In
↓
CheckInCompleted
├── History
└── Accounting

という後続処理を利用する。

---

# 126. Web Reception Operation

Web Receptionでは、
受付担当者がBrowserから
受付操作を行える。

基本Flow：

Reception User
↓
Authentication
↓
Authorization
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
Check In Result

Web Clientは、
受付専用の画面を提供できる。

ただし、
Business Ruleは
Application / Domainに保持する。

---

# 127. Mobile QR Reception Operation

Mobile QR Receptionでは、
受付担当者がSmartphoneから
QR受付を行える。

基本Flow：

Reception User
↓
Authentication
↓
Authorization
↓
Performance Context
↓
Camera
↓
QR Scanner
↓
Ticket Identifier
↓
Check In API
↓
Check In Use Case
↓
Check In Result

Web Receptionと同じ
Check In Business Ruleを利用する。

---

# 128. Check In Result Consistency

Web ClientとMobile Clientで、
同じTicketを操作した場合、
Server SideのBusiness Factを正本とする。

例えば、

Web Client
→ Ticket XをCheck In

その直後に、

Mobile Client
→ Ticket XをScan

した場合、

Mobile Clientには
「Already Checked In」

などのResultを返す。

逆の場合も同様とする。

Client側の表示Stateだけで、
Check In済みかどうかを判断しない。

---

# 129. API and Accounting Timing

Check In APIが成功し、
CheckInCompletedが確定した後、

Ticket Revenue
↓
Journal Entry

へのAccounting Processを実行する。

Accountingの具体的なTiming、
Transaction、
Journal Entry Ruleは、
Accounting Architecture / Implementation Specificationで定義する。

APIは、
Accounting内部のData Structureを
直接操作しない。

---

# 130. API and History Timing

Check In APIが成功し、
CheckInCompletedが確定した後、

Audience History

へのProcessを実行する。

Historyの具体的なData Structureは、
Data Architecture / Domain Modelで定義する。

APIは、
History内部のData Structureを
直接操作しない。

---

# 131. API Architecture and PHP Server

StageArt ServerをPHPで実装する場合、
Web ClientとMobile Clientの双方から
同じPHP APIを利用できる。

基本構造：

Web Client
↓
HTTPS
↓
PHP API
↓
Application
↓
Domain
↓
Repository
↓
Database

Mobile Client
↓
HTTPS
↓
PHP API
↓
Application
↓
Domain
↓
Repository
↓
Database

Clientごとに、
別のBusiness RuleをPHP側へ実装しない。

---

# 132. API Architecture and WordPress

StageArtをWordPress Pluginとして実装する場合でも、
Web ClientとMobile Clientは、
StageArt API Boundaryを利用する。

基本構造：

Web Client
↓
WordPress / StageArt API
↓
Application
↓
Domain

Mobile Client
↓
WordPress / StageArt API
↓
Application
↓
Domain

WordPressのREST APIや
Authentication機能は、
Infrastructureとして利用できる。

---

# 133. API Architecture and Accounting

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

Web Clientも同様に、
Accounting APIを直接呼び出して
Ticket Revenueを作成しない。

---

# 134. API Architecture and History

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

Web Clientも同様に、
Audience Historyを直接作成しない。

---

# 135. API Entry Point Consistency

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

# 136. API Architecture Principle

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

Check Inについては、
WebとMobileで入口が異なっても、
API以下を共通化する。

Web：

Performance
↓
Reservation / Issued Ticket List
↓
Check In API
↓
Check In Use Case
↓
Check In

Mobile：

Performance Context
↓
QR Scanner
↓
Check In API
↓
Check In Use Case
↓
Check In

さらに、

Check In
↓
CheckInCompleted
├── History
└── Accounting

というBusiness Flowを共通化する。

これにより、

- Web Client
- Mobile Client
- QR Scanner
- PHP Server
- WordPress
- Database
- External Service

が変更されても、
StageArtのBusiness Ruleと
Business Factを一貫して管理できる
API Architectureを実現する。

---
