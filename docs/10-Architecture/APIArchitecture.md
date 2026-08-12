# StageArt Blueprint

# 10 - Architecture
# API Architecture

Version : 1.2

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
- Reservation Resolution
- Error Handling
- Idempotency
- Concurrency
- Versioning
- Public API
- Management API
- Mobile API
- Integration API
- Scope Isolation

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
- Reservation Number、Booker Name、Manual Selectionなどの受付方法も利用できる。
- Clientごとに異なるBusiness Ruleを実装しない。
- Scope外のDataをClientへ返さない。
- Resource IDを知っているだけではAccessを許可しない。
- Check InはReservationに対するBusiness Operationとして扱う。
- Issued TicketやQR CodeはCheck Inそのものではなく、Reservationを特定するための入力経路として扱う。

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

Administrative Client
↓
Management API
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

API Controllerは、
Domain Entityの内部構造を
直接Clientへ公開しない。

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
Issued Ticketを入口として
Reservationを特定する場合は、

POST /tickets/{id}/check-in

のようなOperationも検討できる。

ただし、
これらはAPI上の入口の表現であり、
Domain上のCanonical Relationshipを変更するものではない。

Canonical Relationship：

Reservation
↓
Check In

である。

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

- Management Portal
- Public Portal
- Audience Portal
- Reception Interface

などからStageArt APIを利用する。

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
Reservation Resolution
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
受付担当者が一覧から対象ReservationまたはIssued Ticketを確認し、
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
- Reservation Number

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
- Reservation Number
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
対象ReservationまたはTicketを検索できるようにする。

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
対象Reservation / Ticket選択
↓
Check In Action
↓
Reservation Resolution
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
Reservationを特定するために必要なIdentifierをAPIへ送信する。

概念的なRequest：

Check In Request

- Reservation Identifier
- Ticket Identifier
- Performance Context
- Client Context
- Idempotency Information

など。

具体的なRequest DTOは、
Implementation Specificationで定義する。

Clientが送信したPerformance、
Reservation、
Ticket情報だけを無条件に信頼しない。

Server側で、
対象Dataの整合性を検証する。

---

# 16. Web Check In Server Validation

Web ClientからCheck In Requestを受け取った場合、
Serverは、

- Authentication
- Authorization
- Reservation Existence
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
- Reservation Not Found
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
必要に応じて複数Reservation / Ticketを選択して
Check Inを実行できる。

基本Flow：

Web Client
↓
Check In List
↓
Multiple Reservation / Ticket Selection
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

各対象について、

- Business Rule
- Authorization
- Reservation
- Performance
- Check In State

を検証する。

具体的なBulk APIの採用は、
Implementation Specificationで決定する。

---

# 19. Web Multiple Check In Result

複数対象を一括処理する場合、
全件成功だけでなく、
個別結果を返せる構造とする。

例えば、

Reservation A
→ Success

Reservation B
→ Already Checked In

Reservation C
→ Invalid Ticket

Reservation D
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
Reservation X
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
Transaction、
Idempotencyなどは、
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
Ticket Identifier
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

Ticket Validation、
Reservation Resolution、
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
Reservation Resolution
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

# 26. Reservation Resolution

Check In APIでは、
最終的に対象Reservationを特定する。

Reservation Resolutionは、
Check Inそのものとは分離する。

受付入口には、

- Reservation Identifier
- Reservation Number
- Booker Name
- Issued Ticket Identifier
- QR Code
- Manual Selection

などが存在できる。

基本構造：

受付Input
↓
Reservation Resolution
↓
Reservation
↓
Check In Use Case
↓
Check In

Reservation Resolutionでは、
Authorization Scope内のReservationだけを
対象とする。

---

# 27. Reservation Number Check In

Reservation Numberを利用して、
Reservationを特定できる。

基本Flow：

Client
↓
Reservation Number
↓
Reservation Search
↓
Reservation Resolution
↓
Check In Use Case
↓
Check In
↓
CheckInCompleted

Reservation Numberは、
Check In Factそのものではない。

Reservation Numberから対象Reservationを特定し、
そのReservationに対してCheck Inを実行する。

---

# 28. Booker Name Check In

Booker Nameを利用して、
候補Reservationを検索できる。

基本Flow：

Client
↓
Booker Name Search
↓
Candidate Reservations
↓
Reservation Selection
↓
Reservation Resolution
↓
Check In Use Case
↓
Check In

Booker Nameだけでは、
対象Reservationを一意に特定できない場合がある。

その場合、
候補一覧から受付担当者が対象を確認する。

Booker Name検索は、
Search Operationであり、
Check In Factを直接変更しない。

---

# 29. Manual Selection Check In

Manual Selectionでは、
Performanceに紐づくReservation一覧から
対象Reservationを選択する。

基本Flow：

Client
↓
Performance
↓
Reservation List
↓
Reservation Selection
↓
Reservation Resolution
↓
Check In Use Case
↓
Check In

Manual Selectionでは、
一覧取得時のAuthorization Scopeを維持する。

Clientが任意のReservation IDを
指定してScope外Dataへアクセスすることはできない。

---

# 30. Common Check In API

Web ClientとMobile Clientは、
Check Inの入口が異なる。

Web：

Web Client
↓
Reservation / Issued Ticket List
↓
Reservation Resolution
↓
Check In API

Mobile：

Mobile Client
↓
QR Scanner
↓
Ticket Identifier
↓
Reservation Resolution
↓
Check In API

Reservation Number：

Client
↓
Reservation Number
↓
Reservation Resolution
↓
Check In API

Booker Name：

Client
↓
Booker Name
↓
Candidate Reservation
↓
Reservation Resolution
↓
Check In API

Manual Selection：

Client
↓
Performance
↓
Reservation List
↓
Reservation Resolution
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

# 31. Check In API Flow

Check Inの基本Flow：

Request
↓
Authentication
↓
Authorization
↓
Request Validation
↓
Reservation Resolution
↓
Load Reservation
↓
Load Issued Ticket if applicable
↓
Validate Performance
↓
Validate Reservation State
↓
Validate Ticket State if applicable
↓
Check In Domain Operation
↓
Persist Check In
↓
Update Required Reservation State
↓
Publish CheckInCompleted
↓
Response

Check In Business Factは、
Server Sideで確定する。

---

# 32. Check In Canonical Relationship

Check InのCanonical Relationshipは、

Reservation
↓
Check In

である。

Issued Ticketは、

Ticket
↓
Issued Ticket
↓
Reservation

というRelationshipを持つ。

QR Ticketは、

Issued Ticket
↓
QR Ticket
↓
QR Code

というArtifactとして扱う。

したがって、

QR Code
↓
Check In

を直接のDomain Relationshipとはしない。

QR Codeは、
Reservationを特定するための
入力経路である。

---

# 33. Check In Command

Check Inは、
Commandとして実行する。

概念的には、

CheckInCommand

として、

- Reservation Identifier
- Performance Context
- Source
- Client Context
- Idempotency Key

などをApplication Layerへ渡す。

具体的なCommand DTOは、
Implementation Specificationで定義する。

---

# 34. Check In Source

Check Inがどの経路から実行されたかを、
必要に応じて記録できる。

例：

- WEB_MANUAL
- WEB_SEARCH
- WEB_RESERVATION_NUMBER
- WEB_BOOKER_NAME
- MOBILE_QR
- ADMIN

Sourceは、
Business Ruleを変更するための値ではない。

Sourceは、
Audit / Operation Contextなどの
補助情報として扱う。

---

# 35. Check In Response

Check In APIは、
Check In ResultをResponse DTOとして返す。

概念的には、

- Check In Identifier
- Reservation Identifier
- Performance Identifier
- Check In Status
- Check In Time
- Result
- Message

など。

具体的なResponse DTOは、
Implementation Specificationで定義する。

Domain Entityそのものを
Responseとして返さない。

---

# 36. Already Checked In

対象Reservationが、
すでにCheck In済みの場合、
同一Business Factを二重作成しない。

Serverは、
現在のCheck In Stateを確認する。

必要に応じて、

Already Checked In

というResultを返す。

これは、
Client側で判断するものではない。

---

# 37. Check In Idempotency

Check In APIは、
Retryを考慮する。

同じRequestが複数回送信された場合でも、
Check In Factを不必要に複数作成しない。

必要に応じて、

- Idempotency Key
- Reservation Identifier
- Client Request Identifier
- Existing Check In

などを利用する。

具体的なIdempotency Strategyは、
Implementation Specificationで定義する。

---

# 38. Check In Concurrency

複数Clientが、
同一Reservationに対して
同時にCheck Inを実行する可能性を考慮する。

例えば、

Client A
↓
Check In Reservation X

Client B
↓
Check In Reservation X

が同時に発生しても、
最終的なBusiness Factは
二重作成しない。

このConsistencyは、
Server Sideで保証する。

---

# 39. Check In Transaction

Check In処理では、
必要なBusiness Factを
適切なTransaction Boundaryで確定する。

概念的には、

Load Reservation
↓
Validate
↓
Check Existing Check In
↓
Create Check In
↓
Update Required State
↓
Commit

という処理を行う。

具体的なTransaction、
Lock、
Isolation Levelなどは、
Implementation Specificationで定義する。

---

# 40. CheckInCompleted Event

Check Inが確定すると、

CheckInCompleted

を発生させる。

基本構造：

Check In
↓
CheckInCompleted

CheckInCompletedは、
後続Domain処理の起点として利用できる。

例えば、

CheckInCompleted
├── Audience History
└── Accounting Process

など。

---

# 41. Audience History API

Audience Historyは、
Check In Business Factを起点として
生成・参照する。

基本Flow：

Check In
↓
CheckInCompleted
↓
Audience History

Ticket購入だけでは、
Audience Historyを確定しない。

APIからAudience Historyを取得する場合も、
Authorization Scopeを適用する。

---

# 42. Accounting Integration

Check InとAccountingは、
API上でもDomainとして分離する。

基本構造：

Check In
↓
CheckInCompleted
↓
Accounting Process
↓
Ticket Revenue
↓
Journal Entry

Check In APIが、
Journal Entryの内部構造を直接操作しない。

Accounting処理は、
Accounting Application Processへ委譲する。

---

# 43. Authentication

Authenticationは、
Requestを実行しているIdentityを
確認する。

基本構造：

Request
↓
Authentication
↓
Authenticated Principal

AuthenticationとAuthorizationを
分離する。

Authenticationが成功しただけでは、
ResourceへのAccessを許可しない。

---

# 44. Authorization

Authorizationは、
Authenticated Principalが
対象Operationを実行できるかを確認する。

基本構造：

Authenticated Principal
↓
Organization Membership
↓
Role
↓
Permission
↓
Scope
↓
Resource
↓
Operation

Authorizationは、
Server Sideで必ず実行する。

---

# 45. Organization Scope

Organizationに属するDataについては、
Organization Scopeを確認する。

例えば、

User
↓
Organization A
↓
Production A

へのAccessは許可されても、

User
↓
Organization B
↓
Production B

へのAccessは、
適切なMembershipがなければ拒否する。

---

# 46. Production Scope

Production Scopeを持つDataについては、
Production Scopeを確認する。

例えば、

Person
↓
ProductionDelegate
↓
Production A

の場合、
Production AのManagement APIを
利用できる可能性がある。

ただし、
Production Bについては、
別途Authorizationが必要である。

---

# 47. Scope Isolation

Resource IDを知っているだけでは、
Accessを許可しない。

例えば、

GET /productions/{id}

において、
IDが存在することだけを理由に
Responseを返さない。

Serverは、

- Authentication
- Organization Scope
- Project Scope
- Production Scope
- Permission

などを確認する。

---

# 48. Scope-aware Query

Scope外Dataを、
Client側で非表示にするだけでは不十分とする。

Query段階で、
Authorization Scopeを適用する。

基本構造：

Request
↓
Authorization Context
↓
Scope
↓
Query
↓
Authorized Data
↓
Response

IDだけでDataを取得して、
後からAuthorizationを確認する構造を
可能な限り避ける。

---

# 49. Cross Organization Access

別OrganizationのDataへのAccessは、
明示的なAuthorizationがない限り許可しない。

例えば、

Organization A User
↓
Reservation B

がOrganization BのDataである場合、
Reservation BのIDを知っていても
取得できない。

これは、

- Web Client
- Mobile Client
- Administrative Client
- Public API

すべてに適用する。

---

# 50. Public API

Public APIは、
公開可能なDataだけを返す。

基本構造：

Internal Domain
↓
Public Projection
↓
Public DTO
↓
Public API
↓
Public Client

Internal Entityを、
そのままPublic API Responseとして返さない。

---

# 51. Public API Scope

Public APIでは、
Internal Organization Dataや
Personal Dataを不用意に公開しない。

Public Projectionでは、
公開可能な属性だけを選択する。

Public APIのAuthorization Ruleは、
Internal Management APIとは分離する。

---

# 52. Management API

Management APIは、
OrganizationやProductionの管理操作を提供する。

例：

- Organization Management
- Membership Management
- Project Management
- Production Management
- Participant Management
- Production Delegate Management
- Performance Management
- Rehearsal Management
- Ticket Management
- Reservation Management
- Check In Management
- Accounting Management

各Operationでは、
ScopeとPermissionを検証する。

---

# 53. Reservation API

Reservation APIは、
Reservation Business Operationを提供する。

例：

- Create Reservation
- Update Reservation
- Confirm Reservation
- Cancel Reservation
- Get Reservation
- Search Reservation

Reservation APIとCheck In APIを
同一Operationとして扱わない。

Reservation：

「予約」

Check In：

「受付」

という異なるBusiness Factを管理する。

---

# 54. Ticket API

Ticket APIは、
TicketおよびIssued Ticketを管理する。

例：

- Create Ticket
- Update Ticket
- Issue Ticket
- Get Ticket
- Get Issued Ticket
- Generate QR Artifact

Ticket APIは、
Check In Business Factを直接生成するための
Generic CRUD APIとして扱わない。

Check Inは、
Check In Application Use Caseから実行する。

---

# 55. Performance API

Performance APIは、
Productionに紐づくPerformanceを管理する。

例：

- Create Performance
- Update Performance
- Get Performance
- List Performances

Check In List Queryでは、
Performance Scopeを利用して
対象Reservationを取得できる。

---

# 56. Rehearsal API

Rehearsal APIは、
Rehearsalに関するBusiness Operationを提供する。

例：

- Create Rehearsal
- Confirm Rehearsal
- Update Rehearsal
- Record Attendance
- Get Attendance

Rehearsal APIも、
Authorization Scopeを適用する。

---

# 57. Participant API

Participant APIは、
Productionへの参加関係を管理する。

例：

- Add Participant
- Update Participant
- Remove Participant
- Get Participant
- List Participants

Participantであることと、
Production Management Permissionを分離する。

Production Management Permissionは、
ProductionDelegateなどのAuthorization Modelで判定する。

---

# 58. Organization API

Organization APIは、
OrganizationおよびMembershipを管理する。

例：

- Create Organization
- Update Organization
- Get Organization
- Add Member
- Remove Member
- Update Member Role

Organization Scope外のDataへ
アクセスできない構造を維持する。

---

# 59. Project API

Project APIは、
Organization内のProjectを管理する。

例：

- Create Project
- Update Project
- Get Project
- List Projects

Projectは、
Organization Scopeの下位Contextとして扱う。

---

# 60. Accounting API

Accounting APIは、
Accounting Business Operationを提供する。

例：

- Create Journal Entry
- Get Journal Entry
- List Journal Entries
- Create Budget
- Get Budget
- Production Settlement

Accounting APIは、
Check In APIとは分離する。

Check InからAccountingへは、
Business Event / Application Processを介して連携する。

---

# 61. Document API

Document APIは、
Document Business Dataを管理する。

例：

- Create Document
- Get Document
- Update Document
- Share Document
- Archive Document

External StorageへのUpload自体は、
Infrastructure / Integration Layerの責務として扱う。

---

# 62. Communication API

Communication APIは、
Announcementなどを管理する。

例：

- Create Announcement
- Publish Announcement
- Get Announcement
- List Announcements

External Email / Messaging Serviceへの送信は、
Integration Layerへ委譲する。

---

# 63. Integration API

External ServiceとのIntegrationは、
StageArt APIのCore Business APIと分離する。

基本構造：

StageArt Application
↓
Integration Service
↓
External Service API

External ServiceのAPI Contractを、
StageArt Domainへ直接持ち込まない。

---

# 64. External Reference

External ServiceのIdentifierは、
External Referenceとして扱う。

例：

- External User ID
- External File ID
- External Calendar Event ID
- External Social Post ID
- External Message ID

External Referenceを、
StageArt Business Identityの代わりにしない。

---

# 65. Error Handling

API Errorは、
Clientが処理可能なResponseへMappingする。

概念的なCategory：

- Authentication Error
- Authorization Error
- Validation Error
- Resource Not Found
- Conflict
- Business Rule Violation
- Rate Limit
- Integration Error
- System Error

Internal Exceptionを、
そのままClientへ返さない。

---

# 66. Authentication Error

Authenticationに失敗した場合、
適切なAuthentication Errorを返す。

例：

- Missing Credential
- Invalid Credential
- Expired Credential

具体的なHTTP Status CodeとError Codeは、
API Contractで定義する。

---

# 67. Authorization Error

認証済みでも、
対象ResourceやOperationへの権限がない場合は、
Authorization Errorを返す。

例えば、

- Organization Scope外
- Production Scope外
- Permission不足

など。

Clientへ、
不要なInternal Dataを返さない。

---

# 68. Resource Not Found

対象Resourceが存在しない場合、
Resource Not Foundを返す。

ただし、
Scope外Resourceについては、
Resourceの存在そのものを
不必要に漏洩しないようにする。

---

# 69. Conflict

Business Stateの競合が発生した場合、
Conflictとして扱う。

例えば、

- Already Checked In
- Duplicate Reservation
- Duplicate Issued Ticket
- Concurrent Update

など。

Check Inの二重受付についても、
必要に応じてConflictまたは
Business Resultとして返す。

---

# 70. Validation Error

Request DTOが不正な場合、
Validation Errorを返す。

例：

- Required Field Missing
- Invalid Format
- Invalid Identifier
- Invalid Parameter Combination

Validationは、
API LayerとDomain Layerで
責務を分ける。

---

# 71. Business Rule Error

Domain Business Ruleに違反した場合、
Business Rule Errorとして
Client向けResponseへMappingする。

例えば、

- Cancelled Reservation
- Invalid Ticket State
- Invalid Performance
- Check In Not Allowed

など。

Domain内部のException Structureを
そのままClientへ公開しない。

---

# 72. Idempotency

重要なCommand APIでは、
Idempotencyを考慮する。

対象例：

- Check In
- Reservation Confirmation
- Ticket Issuance
- Journal Entry
- Announcement Delivery
- External Integration

必要に応じて、
Idempotency KeyをRequestに含める。

---

# 73. Check In Idempotency

Check Inは、
RetryやDouble Submitが発生しやすいため、
Idempotencyを重視する。

例えば、

Client
↓
Check In Request
↓
Timeout
↓
Client Retry
↓
Same Check In Request

という状況でも、
Check In Factを不必要に二重作成しない。

Serverは、
既存Check Inおよび
Idempotency Informationを確認する。

---

# 74. API Retry

Clientは、
すべてのErrorを無条件にRetryしてはならない。

Retry可能性を、
Error Categoryによって判断する。

例えば、

Temporary System Error
→ Retry可能

Validation Error
→ Retry不要

Forbidden
→ Retry不要

Already Checked In
→ Business Resultとして処理

など。

具体的なRetry Policyは、
Implementation Specificationで定義する。

---

# 75. Rate Limiting

APIは、
必要に応じてRate Limitingを適用する。

対象例：

- Authentication
- Public API
- Search API
- Integration API

Rate Limitは、
Clientごと、
Userごと、
IPごと、
Tokenごとなど、
用途に応じて定義できる。

---

# 76. Request Validation

Request Validationでは、

- Required Fields
- Data Type
- Format
- Length
- Identifier
- Parameter Combination

などを検証する。

Validationだけで
Business Ruleを実装しない。

---

# 77. Response DTO

API Responseは、
Response DTOとして定義する。

Response DTOは、
Clientが必要とする情報だけを返す。

Domain Entityを、
そのままSerializeして返さない。

---

# 78. Request DTO

API Requestは、
Request DTOとしてApplication Layerへ渡す。

Request DTOは、
HTTP Request Formatと
Domain Entityを分離する。

例えば、

HTTP Request
↓
Request DTO
↓
Application Command

というMappingを行う。

---

# 79. Pagination

一覧APIでは、
必要に応じてPaginationを利用する。

対象例：

- Reservations
- Issued Tickets
- Participants
- Rehearsals
- Performances
- Announcements
- Accounting Records

Paginationは、
Clientが無制限Dataを取得することを防ぐ。

具体的なPagination方式は、
API Contractで定義する。

---

# 80. Sorting

List APIでは、
必要に応じてSortingを提供する。

ただし、
Clientから任意のDatabase Columnを
直接指定させる方式は避ける。

許可されたSort Fieldを、
API Contractで定義する。

---

# 81. Search and Query

Query APIは、
Business Meaningを持つ検索条件を提供する。

例えばReservation Searchでは、

- Reservation Number
- Booker Name
- Performance
- Status
- Ticket Identifier

など。

Database Columnを
そのままAPI Query Parameterへ公開しない。

---

# 82. API Versioning

APIは、
Version管理可能な構造とする。

例えば、

/api/v1/

などのVersioning方式を採用できる。

具体的なVersioning Strategyは、
Implementation Specificationで決定する。

Breaking Changeを行う場合は、
既存Clientへの影響を考慮する。

---

# 83. Backward Compatibility

API Contractを変更する場合、
既存Clientが利用できなくなる
Breaking Changeを慎重に扱う。

特に、

- Request Field
- Response Field
- Error Code
- Authentication
- Pagination
- Resource Identifier

など。

---

# 84. API Security

APIは、
HTTPSを基本とする。

Authentication Credential、
Token、
Secretなどを
不要にURLへ含めない。

Sensitive Dataを、
不要にResponseやLogへ出力しない。

---

# 85. API Logging

API Request / Responseについて、
必要なAudit / Operational Loggingを行う。

記録対象例：

- Request ID
- User
- Operation
- Resource
- Scope
- Result
- Timestamp
- Error Category

ただし、

- Password
- Token
- Secret
- 不要なPersonal Data

などをLogへ出力しない。

---

# 86. Request Correlation

複数LayerにまたがるRequestを
追跡できるようにする。

概念的には、

Request
↓
Request ID
↓
API
↓
Application
↓
Domain
↓
Integration

というCorrelationを利用できる。

具体的なImplementationは、
Infrastructure Architectureで定義する。

---

# 87. API and Cache

Cacheは、
API ResponseのPerformance改善に
利用できる。

ただし、
CacheをBusiness Factの正本としない。

Check In Statusなど、
Consistencyが重要な情報については、
Cache利用時のStalenessを考慮する。

---

# 88. API and Read Model

List / Search / Dashboard APIでは、
Read Model / Projectionを利用できる。

基本構造：

Domain Fact
↓
Projection
↓
Read Model
↓
Query API
↓
Client

Read Modelは、
Business Factの正本ではない。

---

# 89. Check In List Read Model

Web Check In一覧では、
必要に応じてRead Modelを利用できる。

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

を、
受付画面用Read Modelとして提供できる。

ただし、
Check In実行時には、
Server側で最新Business Factを再検証する。

一覧表示時のStateを、
Check In確定の根拠として無条件に利用しない。

---

# 90. API and Mobile

Mobile Clientは、
StageArt APIを通してBusiness Operationを実行する。

Mobile Clientは、

- Database
- WordPress Database
- Persistence Model

へ直接アクセスしない。

Mobile Clientは、
Serverから返されたBusiness Resultを
表示する。

---

# 91. API and Web

Web Clientも、
StageArt APIを通してBusiness Operationを実行する。

Web Clientは、
Databaseへ直接アクセスしない。

Web ClientからのManual Check Inも、
Mobile ClientからのQR Check Inも、
同じApplication Boundaryを利用する。

---

# 92. API and WordPress

WordPressを利用する場合でも、
WordPress Database Structureを
API Contractへ直接公開しない。

基本構造：

Client
↓
StageArt API
↓
Application
↓
Domain
↓
Repository
↓
WordPress / Database

WordPressの内部構造変更が、
Client API Contractへ
直接影響しない構造を目指す。

---

# 93. API and PHP

PHP Serverを利用する場合でも、
API Architectureの責務分離を維持する。

基本構造：

PHP API Controller
↓
Application Service
↓
Domain
↓
Repository
↓
Infrastructure

PHP Frameworkの具体的な選択は、
Implementation Architectureで定義する。

---

# 94. Modular Monolith API

初期Architectureでは、
Modular Monolithとして
StageArt Applicationを構築できる。

APIからは、

Identity
Organization
Project
Production
Performance
Ticket
Reservation
Check In
Rehearsal
History
Accounting
Communication
Document
Promotion

などのModuleを利用する。

ただし、
Module間のBusiness Ownershipを維持する。

---

# 95. Cross Module API

Module間の連携では、
他Moduleの内部Persistence Modelを
直接利用しない。

基本構造：

Module A
↓
Application Interface
↓
Module B

または、

Module A
↓
Domain Event
↓
Module B

とする。

---

# 96. API and Domain Events

API Commandによって
Business Factが確定した場合、
必要に応じてDomain Eventを発生させる。

例えば、

Check In
↓
CheckInCompleted

Reservation Confirmation
↓
ReservationConfirmed

など。

Domain Eventは、
Client API Responseとは
別の内部Application Eventとして扱う。

---

# 97. CheckInCompleted

CheckInCompletedは、
Check In Business Factの確定後に発生する。

基本構造：

Check In
↓
CheckInCompleted
├── Audience History
└── Accounting Process

Event Payloadには、
後続処理に必要なBusiness Identifierを含める。

具体的なEvent Schemaは、
Application Architecture / Implementation Specificationで定義する。

---

# 98. API Contract

API Contractでは、
少なくとも以下を定義する。

- Endpoint
- HTTP Method
- Authentication
- Authorization
- Request DTO
- Response DTO
- Error Response
- Idempotency
- Pagination
- Filtering
- Sorting
- Version
- Scope

具体的なAPI Contractは、
Implementation Specificationで定義する。

---

# 99. API Contract and Domain Model

API Contractは、
Domain Modelから必要なBusiness Operationを
提供する。

ただし、

Domain Entity
=
API Resource

とは限らない。

APIは、
Clientが必要とするBusiness Operationを
中心に設計する。

---

# 100. API Contract and Data Architecture

APIは、
Data Architectureで定義された
Business Ownershipを尊重する。

例えば、

Reservation
→ Reservation Domain

Check In
→ Check In Domain

Accounting
→ Accounting Domain

というOwnershipを維持する。

APIが別DomainのDataを
直接更新しない。

---

# 101. Check In API Responsibility

Check In APIの責務は、

受付Requestを受け取り、
対象Reservationを特定し、
Check In Use Caseを実行し、
結果をClientへ返すこと

である。

Check In API自身が、
Business Ruleを実装するわけではない。

基本構造：

Check In API
↓
Check In Application Use Case
↓
Check In Domain
↓
Check In Business Fact

---

# 102. Check In Entry Methods

Check Inの受付入口として、
以下をサポートできる。

- QR Code
- Reservation Number
- Booker Name
- Manual Selection
- Direct Reservation Identifier

それぞれの入口は、
Reservation Resolutionの方法が異なる。

しかし、
最終的なBusiness Operationは共通する。

---

# 103. Check In Entry Method Independence

受付入口によって、
Check In Business Ruleを変更しない。

例えば、

QR受付だからCheck Inを許可する、
Manual受付だから別Ruleを適用する、

という構造にしない。

すべて、

Reservation
↓
Check In Use Case
↓
Check In Domain

へ集約する。

---

# 104. Check In and Issued Ticket

Issued Ticketは、
Check Inの必須Entityではない。

QR受付などでは、

QR Code
↓
Issued Ticket
↓
Reservation

というResolutionを行う。

一方、

Reservation Number
↓
Reservation

Booker Name
↓
Reservation

Manual Selection
↓
Reservation

というResolutionも可能である。

したがって、

Issued Ticket
≠
Check In

である。

---

# 105. Check In and Performance

Check Inは、
対象Performanceとの整合性を確認する。

基本関係：

Production
↓
Performance
↓
Reservation
↓
Check In

Check In Requestで指定されたPerformance Contextと、
Reservationが紐づくPerformanceが
一致していることをServerで確認する。

---

# 106. Check In and Reservation State

Check In実行時には、
Reservationの状態をServer側で検証する。

例えば、

- Cancelled
- Invalid
- Expired
- Already Checked In

など。

具体的なState Ruleは、
Reservation / Check In Domainで定義する。

---

# 107. Check In and Ticket State

Issued Ticketを利用する受付方法では、
Ticket Stateも検証する。

例えば、

- Issued
- Valid
- Cancelled
- Already Used

など。

具体的なTicket State Ruleは、
Ticket Domainで定義する。

---

# 108. Check In Result Consistency

Check In Resultは、
Server側のBusiness Factを基準とする。

Clientが保持する、

- Local State
- Cached State
- Previous Response

などを、
Server Business Factより優先しない。

---

# 109. API Availability

APIは、
必要なAvailabilityを確保する。

特にReception Timeには、

- Check In API
- Reservation Query API
- Ticket Query API

などのAvailabilityが重要となる。

具体的なAvailability / Infrastructure設計は、
Infrastructure Architectureで定義する。

---

# 110. API Timeout

External Serviceや
長時間処理を伴うAPIでは、
Timeoutを考慮する。

同期APIで実行する処理と、
Background Processingへ委譲する処理を分離する。

Check Inは、
Reception Operationとして
可能な限り同期的にResultを返せる構造を目指す。

---

# 111. Background Processing

以下のような処理は、
必要に応じてBackground Processingへ
委譲できる。

- Email Delivery
- External Synchronization
- Social Media Posting
- Report Generation
- Large Export
- Accounting Projection

Check Inそのものの確定を、
Background Jobだけに依存しない。

---

# 112. API and Integration Failure

External Integrationが失敗しても、
Core Business Factを不必要に失敗させない。

例えば、

Check In
↓
CheckInCompleted
↓
Accounting Process

において、
External Serviceの一時障害によって
Check In Factそのものを
失わせない設計を検討する。

具体的なFailure Strategyは、
Integration Architectureで定義する。

---

# 113. API Observability

APIの運用状態を確認できるように、

- Request Count
- Response Time
- Error Count
- Error Category
- Integration Failure
- Check In Failure
- Authorization Failure

などを観測できる構造とする。

---

# 114. API Documentation

API Contractは、
実装前に明確化する。

最低限、

- Endpoint
- Method
- Authentication
- Authorization
- Request
- Response
- Error
- Scope
- Idempotency

を定義する。

具体的なOpenAPI Specificationなどは、
Implementation Specificationで作成する。

---

# 115. API Testing

APIについては、
少なくとも以下をテスト対象とする。

- Authentication
- Authorization
- Scope Isolation
- Request Validation
- Business Operation
- Error Handling
- Idempotency
- Concurrency
- Response DTO
- Integration Failure

Check Inでは、
特に、

- Web Manual Check In
- QR Check In
- Reservation Number Check In
- Booker Name Check In
- Manual Selection
- Duplicate Check In
- Concurrent Check In
- Scope Violation

をテストする。

---

# 116. API Security Boundary

APIは、
Security Boundaryとして扱う。

Clientが信頼できる環境であっても、
Server側で、

- Authentication
- Authorization
- Validation
- Scope
- Business Rule

を再確認する。

---

# 117. API Data Minimization

API Responseは、
Clientに必要なDataだけを返す。

不要な、

- Internal ID
- Internal Permission
- Database Structure
- Secret
- Internal Exception
- Internal Technical Data

などを公開しない。

---

# 118. API and Personal Data

Personに関連するAPIでは、
必要なPersonal Dataだけを返す。

特に、

- Person Name
- Contact Information
- Reservation Information
- Audience History

などについて、
Authorization Scopeを適用する。

Public APIでは、
Public Dataとして許可された情報だけを返す。

---

# 119. API Scope Enforcement Summary

API ResourceへのAccessは、

Authentication
↓
Authorization
↓
Organization Scope
↓
Project Scope
↓
Production Scope
↓
Resource
↓
Operation

の順序で判断する。

Resource IDだけを指定して、
Scope外Dataを取得できる設計にしない。

---

# 120. API Architecture Summary

StageArt API Architectureでは、

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
Persistence

というBoundaryを維持する。

APIは、
Database CRUDの単なるProxyではない。

Business Operationを中心に設計する。

特にCheck Inでは、

受付Input
↓
Reservation Resolution
↓
Check In Use Case
↓
Check In
↓
CheckInCompleted

という共通構造を採用する。

受付Inputには、

- QR Code
- Reservation Number
- Booker Name
- Manual Selection
- Direct Reservation Identifier

などを利用できる。

QR受付では、

Mobile Client
↓
QR Scanner
↓
QR Code
↓
Issued Ticket
↓
Reservation
↓
Check In

となる。

Web受付では、

Web Client
↓
Performance
↓
Reservation / Issued Ticket List
↓
Search / Filter
↓
Reservation Selection
↓
Check In

となる。

Reservation Number受付では、

Reservation Number
↓
Reservation Resolution
↓
Check In

となる。

Booker Name受付では、

Booker Name
↓
Candidate Reservation
↓
Reservation Resolution
↓
Check In

となる。

Manual Selectionでは、

Performance
↓
Reservation List
↓
Reservation Selection
↓
Check In

となる。

すべての入口において、
最終的なCheck In Business Ruleは同一とする。

Check InのCanonical Relationshipは、

Reservation
↓
Check In

である。

Issued Ticketは、
Reservationを特定するための
Business Dataであり、
Check Inそのものではない。

QR Codeは、
Issued Ticketを識別するArtifactであり、
Check In Business Factではない。

Check Inが確定すると、

Check In
↓
CheckInCompleted
├── Audience History
└── Accounting Process

という後続処理を実行できる。

また、

Person
↓
Membership
↓
Organization
↓
Role
↓
Permission

および、

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
API Access Scopeを決定する。

Scope外Dataは、
Client側で隠すだけではなく、
Server Sideで取得できない構造を基本とする。

Web Client、
Mobile Client、
Administrative Client、
Public Clientなど、
Clientの種類が異なっても、
同じBusiness Operationについては
同じApplication Use Caseを利用する。

API Architectureの最重要原則は、

「APIをBusiness Operationの入口とし、
ClientやDatabaseをBusiness Factの正本にしない」

ことである。

また、

「Check Inの受付方法と、
Check In Business Ruleを分離する」

ことを明確にする。

これにより、

Web Client
Mobile Client
QR Scanner
PHP Server
WordPress
Database
External Service

などのTechnologyやInterfaceが変更されても、
StageArtのBusiness Rule、
Business Identity、
Authorization Scope、
Check In Business Fact

を一貫して維持できるAPI Architectureを実現する。

---
