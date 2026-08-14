# StageArt Blueprint

# 10 - Architecture
# API Architecture

Version : 1.3

---

# Purpose

API Architectureは、
StageArt Applicationと
Web Client、
Mobile Client、
Public Client、
System Administrationなどの
Client / System Boundary間に存在する
API Boundaryを定義する。

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
- Mobile Reception
- Reservation Resolution
- Error Handling
- Idempotency
- Concurrency
- Versioning
- Public API
- Management API
- Mobile API
- System Administration API
- Integration Boundary
- Scope Isolation
- System Operations

を定義する。

API Architectureでは、
具体的なPHP Framework、
Database Query、
Infrastructure Implementationなどの
詳細までは定義しない。

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
- Receptionは独立Clientではなく、Mobile ClientのOperational Modeとして扱う。
- System Administrator専用の重複したBusiness Management APIを作らない。
- System AdministratorはOrganizationを選択し、Selected Organization Contextから通常のManagement APIを利用する。
- System OperationsとBusiness OperationsをAPI上でも分離する。
- Backup、Replication、Mirror、RecoveryなどをBusiness Domain APIと混在させない。

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

System Administration
↓
System Administration API
↓
Application
↓
System Operations / Organization Selection

Organization Managementについては、

System Administrator
↓
Organization Selector
↓
Selected Organization Context
↓
Management API
↓
Application
↓
Domain

という構造を利用する。

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
- Request Correlation

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

Queryの場合：

API Request
↓
Query DTO
↓
Application Query
↓
Read Model / Domain Query
↓
Query Result
↓
Response DTO
↓
API Response

API Controllerは、
Use CaseやQueryの内部処理を知らない。

---

# 5. Business Operation API

StageArt APIは、
Business Operationを中心に設計する。

例えば、

Create Reservation

Confirm Reservation

Cancel Reservation

Issue Ticket

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

ReservationのStateを直接変更するのではなく、

Confirm Reservation

Cancel Reservation

Check In

などのUse Caseを利用する。

---

# 7. API Resource and Domain Entity

API ResourceとDomain Entityは、
同一概念とは限らない。

例えば、

Reservation API Resource
≠
Reservation Domain Object

Check In API Response
≠
Check In Domain Entity

とする。

API Responseは、
Clientに必要なDataだけを
Response DTOとして返す。

---

# 8. Client Types

StageArt APIは、
以下のClient / Entry Pointから利用できる。

- Web Client
- Mobile Client
- Public Client
- System Administration

Receptionは、
独立したClientではない。

Receptionは、
Mobile ClientのOperational Modeとして提供する。

Administrative Clientという
独立したBusiness Management Clientも作らない。

System Administratorは、

System Administration
↓
Organization Selector
↓
Selected Organization Context
↓
通常Management Client

という構造を利用する。

---

# 9. Web Client API

Web Clientは、

- Management Portal
- Public Portal
- Audience Portal
- Reception Interface

などからStageArt APIを利用する。

Management APIの例：

- Organization Management
- Membership Management
- Project Management
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

GET /performances/{performanceId}/reservations

または、

GET /performances/{performanceId}/tickets

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
Reservationを特定するために必要なIdentifierを
APIへ送信する。

概念的なRequest：

Check In Request

- Reservation Identifier
- Ticket Identifier
- Performance Context
- Source
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
- Organization Scope
- Production Scope
- Performance Scope
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
- Conflict
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
Bulk Check In API
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

Mobile Clientは、
受付専用Applicationではない。

通常時には、

- Production Information
- Performance Information
- Rehearsal
- Personal Schedule
- Communication
- Other Production Information

などを利用する。

必要な場合には、
同じMobile Clientを
Reception Modeへ切り替える。

Mobile Clientは、
StageArt Databaseへ直接アクセスしない。

---

# 22. Mobile Normal Mode API

Mobile Normal Modeでは、
公演関係者が日常的に利用する
Query APIを提供する。

例：

- Today's Rehearsal
- Upcoming Rehearsal
- My Schedule
- Production Information
- Performance Information
- Communication

これらは、
Mobile専用のBusiness Ruleではない。

既存のApplication Query / Read Modelを
Mobile向けResponse DTOへMappingする。

---

# 23. Mobile Rehearsal API

Mobile Clientでは、
稽古情報を簡易的に確認できる。

概念的には、

GET /rehearsals

または、

GET /productions/{productionId}/rehearsals

などのQuery APIを利用できる。

必要に応じて、

- Date
- Start Time
- End Time
- Location
- Production
- Rehearsal Content
- Participation
- Notice

などを取得する。

詳細なRehearsal Managementは、
Management APIで行う。

---

# 24. Mobile Production API

Mobile Clientでは、
Userが関係するProduction情報を
簡易的に確認できる。

例えば、

- Production Name
- Production Status
- Performance
- Rehearsal
- Personal Participation
- Communication

など。

Mobile Clientは、
Production Managementの
すべての機能を提供する必要はない。

---

# 25. Mobile Performance API

Mobile Clientでは、
関係するPerformance情報を
確認できる。

例えば、

- Performance Date
- Performance Time
- Venue
- Production
- Participation
- Related Rehearsal
- Communication

など。

具体的なResponse DTOは、
Frontend ArchitectureとAPI Contractで定義する。

---

# 26. Mobile Reception Mode

Receptionは、
独立したAPI Clientではなく、
Mobile ClientのOperational Modeとして扱う。

基本Flow：

Mobile Client
↓
Performance
↓
Reception Mode
↓
QR / Search / Manual Selection
↓
Check In API

Reception Modeに入ったからといって、
Mobile Clientが別Authentication Boundaryを持つわけではない。

通常のMobile Authentication / Authorization Contextを利用する。

---

# 27. Reception Mode Activation API

Reception Modeを利用する場合、
Server SideでUserが対象Performanceの
受付Operationを実行可能か確認する。

概念的には、

GET /performances/{performanceId}/reception-context

などのQueryを利用できる。

Responseでは、
必要に応じて、

- Reception Permission
- Performance
- Available Reception Methods
- Reception Status

などを返す。

具体的なEndpointは、
API Contractで定義する。

---

# 28. Reception Authorization

Reception Modeの利用には、
適切なAuthorizationが必要である。

基本構造：

Authenticated User
↓
Organization Scope
↓
Production Scope
↓
Performance Scope
↓
Reception Permission
↓
Reception Mode

Client側でボタンを隠すだけでは不十分とする。

Check In APIでも、
Server Side Authorizationを再検証する。

---

# 29. QR Reception API

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

# 30. QR Code as Identifier

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

# 31. QR Check In Request

Mobile Clientは、
QR Codeを読み取った後、
必要なIdentifierをAPIへ送信する。

概念的なRequest：

Check In Request

- Ticket Identifier
- Performance Context
- Source
- Client Context
- Idempotency Information

など。

具体的なRequest DTOは、
Implementation Specificationで定義する。

---

# 32. QR Check In Server Validation

Serverは、
Check In Requestを受け取った後、

- Authentication
- Authorization
- Organization Scope
- Production Scope
- Performance Scope
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

# 33. Reservation Resolution

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

# 34. Reservation Number Check In

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

# 35. Booker Name Search

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

# 36. Manual Selection Check In

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

# 37. Common Check In API

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
Reception Mode
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

# 38. Check In API Flow

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
Publish CheckInCompleted
↓
Response

Check In Business Factは、
Server Sideで確定する。

---

# 39. Check In Canonical Relationship

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

# 40. Check In Command

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

# 41. Check In Source

Check Inがどの経路から実行されたかを、
必要に応じて記録できる。

例：

- WEB_MANUAL
- WEB_SEARCH
- WEB_RESERVATION_NUMBER
- WEB_BOOKER_NAME
- MOBILE_QR
- MOBILE_MANUAL
- SYSTEM_ADMIN_CONTEXT

Sourceは、
Business Ruleを変更するための値ではない。

Sourceは、
Audit / Operation Contextなどの
補助情報として扱う。

---

# 42. Check In Response

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

# 43. Already Checked In

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

# 44. Check In Idempotency

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

# 45. Check In Concurrency

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

# 46. Check In Transaction

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

# 47. CheckInCompleted Event

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

# 48. Audience History API

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

# 49. Accounting Integration

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

# 50. Authentication

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

# 51. Authorization

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

# 52. Organization Scope

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

# 53. Project Scope

Project Scopeを持つDataについては、
Project Scopeを確認する。

基本構造：

Organization
↓
Project
↓
Resource

Organization Scopeを満たしていても、
Project Scope上のPermissionがなければ
Accessを許可しない。

---

# 54. Production Scope

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

Production Bについては、
別途Authorizationが必要である。

---

# 55. Performance Scope

Performanceに関連する受付Operationでは、
Performance Scopeを確認する。

基本構造：

User
↓
Production Scope
↓
Performance Scope
↓
Reception Permission
↓
Check In

Reception Operatorは、
許可されたPerformanceに対してのみ
受付Operationを実行できる。

---

# 56. Scope Isolation

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
- Performance Scope
- Permission

などを確認する。

---

# 57. Scope-aware Query

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

# 58. Cross Organization Access

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
- Public API
- Management API

すべてに適用する。

---

# 59. Public API

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

# 60. Public API Scope

Public APIでは、
Internal Organization Dataや
Personal Dataを不用意に公開しない。

Public Projectionでは、
公開可能な属性だけを選択する。

Public APIのAuthorization Ruleは、
Internal Management APIとは分離する。

---

# 61. Management API

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
- Communication Management
- Document Management

各Operationでは、
ScopeとPermissionを検証する。

---

# 62. Organization API

Organization APIは、
OrganizationおよびMembershipを管理する。

例：

- Create Organization
- Update Organization
- Get Organization
- List Organizations
- Add Member
- Remove Member
- Update Member Role

通常のOrganization APIでは、
Request Userが許可されたOrganization Scopeだけを扱う。

System Administratorについては、
Organization Selector APIを利用する。

---

# 63. Project API

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

# 64. Production API

Production APIは、
Productionに関するBusiness Operationを提供する。

例：

- Create Production
- Update Production
- Get Production
- List Productions
- Archive Production

Production APIでは、
Organization / Project / Production Scopeを確認する。

---

# 65. Performance API

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

# 66. Rehearsal API

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

# 67. Participant API

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

# 68. Reservation API

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

# 69. Ticket API

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

# 70. Accounting API

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

# 71. Document API

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

# 72. Communication API

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

# 73. System Administration API

System Administration APIは、
System Administratorだけが利用できる
System-level API Boundaryである。

主な用途：

- Organization List
- Organization Selection
- System Health
- Backup Status
- Replication Status
- Mirror Status
- Recovery Status
- Operational Job Status

System Administration APIは、
Organization Business Management APIと
混在させない。

---

# 74. Organization Selector API

System Administratorは、
全Organizationを一覧から選択できる。

概念的には、

GET /system/organizations

などのSystem-level Queryを利用できる。

このAPIは、
System Administratorだけが利用できる。

通常のOrganization Userには、
自身が所属するOrganizationだけを
通常のOrganization Queryとして返す。

---

# 75. Organization Selection API

System Administratorが、
対象Organizationを選択する。

概念的には、

POST /system/organizations/{organizationId}/select

などのOperationを利用できる。

このOperationは、
OrganizationのBusiness Dataを
直接更新するものではない。

Selected Organization Contextを生成するための
Application Operationである。

---

# 76. Selected Organization Context

Organization Selection後は、

Selected Organization
↓
Organization Administrator Context
↓
Management API

という構造を利用する。

System Administrator専用の
別Management APIを作らない。

例えば、

System Administrator
↓
Organization A Select
↓
Selected Organization Context
↓
GET /productions
↓
POST /rehearsals
↓
GET /reservations
↓
POST /check-in

というように、
通常Management APIを利用する。

---

# 77. Selected Organization Context Authorization

Selected Organization Contextでは、
選択されたOrganizationを
Authorization Contextへ適用する。

基本構造：

System Administrator
↓
Selected Organization A
↓
Organization Scope = A
↓
Organization Administrator相当
↓
Management Operation

System Administratorであることだけを理由に、
Scopeを無視してDataを取得しない。

---

# 78. System Administrator and Business API

System Administratorが
Organization Aを選択した場合、

通常のOrganization Administratorが
Organization Aで利用するAPIと
同じBusiness APIを利用する。

例えば、

Organization Administrator
↓
POST /productions

System Administrator
↓
Organization A selected
↓
POST /productions

は、
同じApplication Use Caseを利用する。

Business Ruleを二重実装しない。

---

# 79. System Administrator and Check In

System Administratorが
Organizationを選択した状態で
Check Inを実行する場合も、
通常のCheck In Use Caseを利用する。

基本構造：

System Administrator
↓
Organization Selector
↓
Selected Organization Context
↓
Performance
↓
Reservation
↓
Check In API
↓
Check In Use Case
↓
Check In

System Administratorだからといって、
Check In Business RuleをBypassしない。

---

# 80. System Operations API

System Operations APIは、
Business Operationsとは分離する。

対象例：

- Backup
- Restore
- Replication
- Mirror
- Failover
- Recovery
- System Health
- Operational Jobs

これらは、
System Administration APIの
Operational Boundaryとして扱う。

---

# 81. Backup API

Backup APIでは、
System Administratorが
Backup Statusなどを確認できる。

概念的には、

GET /system/backups

GET /system/backups/{id}

POST /system/backups

など。

具体的なEndpointは、
Operations Architectureで定義する。

Backup処理自体を、
Business Domain APIとして実装しない。

---

# 82. Replication API

Replication Statusについて、
System Administratorが確認できる。

例えば、

GET /system/replication

など。

必要に応じて、

- Primary Status
- Mirror Status
- Last Sync
- Sync Error
- Lag

などを取得する。

---

# 83. Recovery API

Recoveryに関するOperationは、
System Administrator専用とする。

例えば、

- Recovery Status
- Recovery Job
- Restore Status
- Recovery History

など。

Recovery APIは、
Business Dataを直接編集するAPIではない。

具体的なRecovery Procedureは、
Operations Architectureで定義する。

---

# 84. Integration API

External ServiceとのIntegrationは、
StageArt APIのCore Business APIと分離する。

基本構造：

StageArt Application
↓
Integration Interface
↓
Integration Layer
↓
External Service API

External ServiceのAPI Contractを、
StageArt Domainへ直接持ち込まない。

---

# 85. External Reference

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

# 86. Error Handling

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

# 87. Authentication Error

Authenticationに失敗した場合、
適切なAuthentication Errorを返す。

例：

- Missing Credential
- Invalid Credential
- Expired Credential

具体的なHTTP Status CodeとError Codeは、
API Contractで定義する。

---

# 88. Authorization Error

認証済みでも、
対象ResourceやOperationへの権限がない場合は、
Authorization Errorを返す。

例えば、

- Organization Scope外
- Project Scope外
- Production Scope外
- Performance Scope外
- Permission不足

など。

Clientへ、
不要なInternal Dataを返さない。

---

# 89. Resource Not Found

対象Resourceが存在しない場合、
Resource Not Foundを返す。

ただし、
Scope外Resourceについては、
Resourceの存在そのものを
不必要に漏洩しないようにする。

---

# 90. Conflict

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

# 91. Validation Error

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

# 92. Business Rule Error

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

# 93. Idempotency

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

# 94. Check In Idempotency

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

# 95. API Retry

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

# 96. Rate Limiting

APIは、
必要に応じてRate Limitingを適用する。

対象例：

- Authentication
- Public API
- Search API
- Integration API
- System API

Rate Limitは、
Client、
User、
IP、
Tokenなど、
用途に応じて定義できる。

---

# 97. Request Validation

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

# 98. Response DTO

API Responseは、
Response DTOとして定義する。

Response DTOは、
Clientが必要とする情報だけを返す。

Domain Entityを、
そのままSerializeして返さない。

---

# 99. Request DTO

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

# 100. Pagination

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
- Organizations

Paginationは、
Clientが無制限Dataを取得することを防ぐ。

具体的なPagination方式は、
API Contractで定義する。

---

# 101. Sorting

List APIでは、
必要に応じてSortingを提供する。

ただし、
Clientから任意のDatabase Columnを
直接指定させる方式は避ける。

許可されたSort Fieldを、
API Contractで定義する。

---

# 102. Search and Query

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

# 103. API Versioning

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

# 104. Backward Compatibility

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

# 105. API Security

APIは、
HTTPSを基本とする。

Authentication Credential、
Token、
Secretなどを
不要にURLへ含めない。

Sensitive Dataを、
不要にResponseやLogへ出力しない。

---

# 106. API Logging

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

# 107. Request Correlation

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

# 108. API and Cache

Cacheは、
API ResponseのPerformance改善に
利用できる。

ただし、
CacheをBusiness Factの正本としない。

Check In Statusなど、
Consistencyが重要な情報については、
Cache利用時のStalenessを考慮する。

---

# 109. API and Read Model

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

# 110. Check In List Read Model

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

# 111. Mobile Read Model

Mobile Normal Modeでは、
必要に応じてMobile向けRead Modelを利用できる。

例えば、

- Today's Rehearsal
- Upcoming Rehearsal
- My Schedule
- Production Information
- Performance Information
- Communication

など。

Mobile Read Modelは、
Business Factの正本ではない。

---

# 112. API and Mobile

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

# 113. API and Web

Web Clientも、
StageArt APIを通してBusiness Operationを実行する。

Web Clientは、
Databaseへ直接アクセスしない。

Web ClientからのManual Check Inも、
Mobile ClientからのQR Check Inも、
同じApplication Boundaryを利用する。

---

# 114. API and WordPress

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

# 115. API and PHP

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

# 116. Modular Monolith API

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

# 117. Cross Module API

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

# 118. API and Domain Events

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

# 119. CheckInCompleted

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

# 120. API Contract

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

# 121. API Contract and Domain Model

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

# 122. API Contract and Data Architecture

APIは、
Data Architectureで定義された
Business Ownershipを尊重する。

例えば、

Reservation
→ Reservation Domain

Issued Ticket
→ Ticket Domain

Check In
→ Check In Domain

Accounting
→ Accounting Domain

というOwnershipを維持する。

APIが別DomainのDataを
直接更新しない。

---

# 123. Check In API Responsibility

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

# 124. Check In Entry Methods

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

# 125. Check In Entry Method Independence

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

# 126. Check In and Issued Ticket

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

# 127. Check In and Performance

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

# 128. Check In and Reservation State

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

# 129. Check In and Ticket State

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

# 130. Check In Result Consistency

Check In Resultは、
Server側のBusiness Factを基準とする。

Clientが保持する、

- Local State
- Cached State
- Previous Response

などを、
Server Business Factより優先しない。

---

# 131. API Availability

APIは、
必要なAvailabilityを確保する。

特にReception Timeには、

- Check In API
- Reservation Query API
- Ticket Query API
- Performance Query API

などのAvailabilityが重要となる。

具体的なAvailability / Infrastructure設計は、
Deployment / Infrastructure Architectureで定義する。

---

# 132. API Timeout

External Serviceや
長時間処理を伴うAPIでは、
Timeoutを考慮する。

同期APIで実行する処理と、
Background Processingへ委譲する処理を分離する。

Check Inは、
Reception Operationとして
可能な限り同期的にResultを返せる構造を目指す。

---

# 133. Background Processing

以下のような処理は、
必要に応じてBackground Processingへ
委譲できる。

- Email Delivery
- External Synchronization
- Social Media Posting
- Report Generation
- Large Export
- Accounting Projection
- Notification Delivery

Check Inそのものの確定を、
Background Jobだけに依存しない。

---

# 134. API and Integration Failure

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

# 135. System Operations and API Boundary

System Operationsは、
Business APIとは分離する。

Business API：

- Reservation
- Ticket
- Check In
- Rehearsal
- Accounting

System API：

- Backup
- Restore
- Replication
- Mirror
- Failover
- Recovery
- System Health
- Operational Jobs

System APIから、
Business Dataを直接CRUDする設計にはしない。

---

# 136. API Observability

APIの運用状態を確認できるように、

- Request Count
- Response Time
- Error Count
- Error Category
- Integration Failure
- Check In Failure
- Authorization Failure
- Rate Limit
- Timeout

などを観測できる構造とする。

---

# 137. API Documentation

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
- Pagination
- Version

を定義する。

具体的なOpenAPI Specificationなどは、
Implementation Specificationで作成する。

---

# 138. API Testing

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
- Rate Limiting
- Version Compatibility

Check Inでは、
特に、

- Web Manual Check In
- Web List Check In
- Web Multiple Check In
- QR Check In
- Reservation Number Check In
- Booker Name Search
- Manual Selection
- Duplicate Check In
- Concurrent Check In
- Scope Violation
- Performance Mismatch
- Invalid Ticket
- Cancelled Reservation

をテストする。

System Administratorでは、

- Organization List
- Organization Selection
- Selected Organization Context
- Organization Administrator相当のAccess
- Scope Isolation
- Selected Organization外のData拒否

をテストする。

---

# 139. API Security Boundary

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

# 140. API Data Minimization

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

# 141. API and Personal Data

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

# 142. API Scope Enforcement Summary

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
Performance Scope
↓
Resource
↓
Operation

の順序で判断する。

Resource IDだけを指定して、
Scope外Dataを取得できる設計にしない。

System Administratorの場合は、

System Administrator
↓
Organization Selector
↓
Selected Organization Context
↓
通常Management API

という追加Contextを利用する。

---

# 143. API Architecture Summary

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
Reception Mode
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

Mobile Clientは、
受付専用Clientではない。

通常時には、

Mobile Client
↓
Normal Mode
├── Rehearsal
├── Production
├── Performance
├── Personal Schedule
└── Communication

として利用する。

必要な場合には、

Mobile Client
↓
Reception Mode
↓
Check In

として利用する。

System Administratorについては、

System Administration
↓
Organization List
↓
Organization Selection
↓
Selected Organization Context
↓
通常Management API

という構造を採用する。

System Administrator専用の
Organization Management、
Production Management、
Rehearsal Management、
Reservation Management、
Check In Managementなどの
重複APIを作らない。

Selected Organization Contextでは、
選択されたOrganizationを
通常のOrganization Scopeとして扱い、
Organization Administrator相当の
Management Operationを利用する。

ただし、
System Administratorであることによって
Business RuleをBypassしない。

System Operationsについては、

System Administration
↓
System Operations API
├── Backup
├── Restore
├── Replication
├── Mirror
├── Failover
├── Recovery
└── System Health

というBoundaryを設ける。

これらを、

Reservation
Ticket
Check In
Rehearsal
Accounting

などのBusiness APIと混在させない。

API Architectureの最重要原則は、

「APIをBusiness Operationの入口とし、
ClientやDatabaseをBusiness Factの正本にしない」

ことである。

また、

「Check Inの受付方法と、
Check In Business Ruleを分離する」

ことを明確にする。

さらに、

「Web ClientとMobile Clientは
同一のCheck In Application Use Caseを利用し、
受付方法だけを異なる入口として扱う」

ことを基本とする。

そして、

「Mobile Clientは受付専用Applicationではなく、
公演関係者が日常的に利用するApplicationとし、
必要な場合だけReception Modeへ切り替える」

ことをMobile APIの基本方針とする。

また、

「System Administratorは全Organizationを選択できるが、
選択後はSelected Organization Contextを利用して
通常のManagement APIを実行する」

ことをSystem Administration APIの基本方針とする。

これにより、

Web Client
Mobile Client
Public Client
System Administration
QR Scanner
PHP Server
WordPress
Database
External Service

などのTechnologyやInterfaceが変更されても、

StageArtの

- Business Rule
- Business Identity
- Authorization Scope
- Organization Scope
- Production Scope
- Performance Scope
- Reservation
- Issued Ticket
- Check In Business Fact

を一貫して維持できるAPI Architectureを実現する。

---
