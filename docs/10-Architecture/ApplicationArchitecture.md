# StageArt Blueprint

# 10 - Architecture
# Application Architecture

Version : 1.1

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

Web Browser
↓
StageArt API

Mobile Client
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
- QR Scanner Client

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
- Accounting
- Communication
- Document Management

Web Clientは、
UIとUser Interactionを担当する。

Business Ruleは、
Server Sideで実行する。

---

# 5. Mobile Client

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

# 6. Mobile Client Responsibilities

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

---

# 7. QR Scanner

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

# 8. QR Code Boundary

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
StageArt API

QR Codeがコピーされた場合でも、
Server SideでTicketの状態と
Authorizationを検証する。

---

# 9. QR Code Validation

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

# 10. Check In Client Flow

受付スタッフがMobile Clientで
QR Codeを読み取る場合の基本Flow：

Mobile Client
↓
Camera
↓
QR Code Scan
↓
Ticket Identifier
↓
Check In API
↓
Authentication
↓
Authorization
↓
Application Use Case
↓
Domain Validation
↓
Check In
↓
CheckInCompleted
↓
API Response
↓
Mobile Client
↓
受付結果表示

Check Inを確定するのは、
Server Sideである。

---

# 11. Check In API

Check Inは、
Business OperationとしてAPIへ公開する。

概念的なAPI：

POST /reservations/{id}/check-in

または、

POST /tickets/{id}/check-in

などのBusiness Operationを中心とした
API設計とする。

具体的なEndpoint構造は、
API Architectureで確定する。

APIは、
単純なStatus変更ではなく、
Check In Use Caseを実行する入口とする。

---

# 12. Dependency Direction

基本的な依存方向は、

Presentation
↓
API
↓
Application
↓
Domain

とする。

Infrastructureは、
Domain / Applicationが定義したInterfaceを実装する。

DomainからInfrastructureへ、
直接依存してはならない。

Applicationから、
Infrastructureの具体実装へ直接依存することも
基本的に避ける。

---

# 13. Presentation Layer

Presentation Layerは、
利用者とのInteractionを担当する。

主な責務：

- Screen
- Component
- Form
- Navigation
- User Interaction
- Client-side Validation
- Loading State
- Error State
- API Communication

Presentation Layerは、
Business Ruleを実装しない。

例えば、

「予約済みチケットをCheck Inしたら、
観劇履歴とTicket Revenueを作成する」

という処理を、
画面Component内に実装してはならない。

Presentation Layerは、
Application Use CaseをAPI経由で呼び出す。

---

# 14. API Layer

API Layerは、
外部ClientとApplication Layerの境界である。

主な責務：

- Request受付
- Request Parsing
- Authentication Context取得
- Authorization Context取得
- Request Validation
- Application Use Case呼び出し
- Response Mapping
- Error Mapping

API Controllerは、
Business Ruleを実装しない。

---

# 15. API Controller

Controllerは、
薄いAdapterとして実装する。

基本Flow：

Request
↓
Authentication
↓
Authorization
↓
Request DTO
↓
Application Use Case
↓
Result
↓
Response DTO
↓
Response

Controller内に、
複雑なBusiness Logicを記述しない。

---

# 16. Application Layer

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

# 17. Use Case

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

# 18. Use Case Naming

Use Caseは、
CRUDではなくBusiness Operationを中心に命名する。

例えば、

Create Reservation

は、
ReservationというBusiness Objectを作成するOperation。

Check In Reservation

は、
Reservationに対するBusiness Operation。

Confirm Rehearsal

は、
Rehearsalを確定するBusiness Operation。

Use Case名は、
利用者が何をするかを表現する。

---

# 19. Command

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

# 20. Query

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

Command：

Stateを変更する。

Query：

Stateを参照する。

---

# 21. Command / Query Separation

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

# 22. Domain Layer

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

# 23. Entity Operation

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

# 24. Value Object

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

# 25. Domain Service

複数EntityにまたがるBusiness Ruleで、
特定Entityへ責務を置くことが不自然な場合、
Domain Serviceを利用する。

Domain Serviceは、
Application WorkflowをOrchestrateするものではない。

Business Ruleそのものを扱う。

---

# 26. Application Service

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

# 27. Repository Interface

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
- JournalEntryRepository

Repository Interfaceは、
Database Technologyを公開しない。

---

# 28. Repository Implementation

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

# 29. Infrastructure Layer

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

# 30. Integration Layer

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

# 31. Integration Interface

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

# 32. Transaction

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

# 33. Cross Domain Transaction

複数DomainをまたぐOperationでは、
Transaction Boundaryを明確にする。

例えばCheck Inでは、

Reservation
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

# 34. Check In Application Flow

Check Inの基本Application Flow：

Mobile Client / Web Client
↓
QR Scan または Ticket Selection
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

Check Inそのものを確定するのは、
Server Sideである。

Clientは、
受付処理の結果を表示する。

---

# 35. Check In Server Responsibility

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
Mobile Clientだけで判断しない。

---

# 36. Check In Mobile Responsibility

Mobile Clientは、
以下を担当する。

- Camera起動
- QR Code読み取り
- QR Payload取得
- Check In API呼び出し
- 通信状態表示
- Processing表示
- Success表示
- Error表示

Mobile Clientは、
以下を最終決定しない。

- Ticketが有効か
- Check In可能か
- Check In済みか
- Revenueを作成するか
- Historyを作成するか

これらはServer Sideで決定する。

---

# 37. QR Scan Result

QR Scanが成功した場合、
Mobile Clientは、
QR Payloadから必要なIdentifierを取得する。

そのIdentifierを、
Check In APIへ送信する。

基本構造：

QR Code
↓
QR Payload
↓
Ticket Identifier
↓
API Request
↓
Server Validation

QR Payloadそのものを、
Business Ruleとして扱わない。

---

# 38. QR Security

QR Codeは、
コピーされる可能性を考慮する。

したがって、
QR Codeを読み取れたことだけを
Check In許可の根拠としない。

Server Sideで、

- Ticket State
- Performance
- Reservation
- Issued Ticket
- Check In State
- Authorization

などを検証する。

必要に応じて、
QR Payloadに署名や検証用情報を含めることができる。

具体的なSecurity方式は、
Security Architecture / API Architectureで確定する。

---

# 39. Duplicate Check In

同じTicketが複数回読み取られる可能性がある。

例えば、

- 同じQRを連続して読む
- 複数端末で同時に読む
- 通信再送が発生する
- ClientがTimeout後に再送する

など。

そのため、
Check In処理はIdempotencyを考慮する。

既にCheck In済みの場合は、
Business Ruleに従った結果を返す。

---

# 40. Mobile Network Failure

Mobile Clientは、
受付会場で通信状態が悪い可能性がある。

初期Architectureでは、
Check Inの確定処理はServer Sideで行うことを基本とする。

そのため、
通信不能時にMobile Clientだけで
Check Inを確定しない。

通信失敗時は、

- Retry
- 再送
- Error表示
- Pending State

などを利用できる。

Offline Check Inを実装する場合は、
別途Security / Consistency Architectureとして定義する。

---

# 41. Multiple Check In Devices

同一Performanceで、
複数のSmartphone / Tabletを
受付端末として利用できる。

基本構造：

Device A
↓
Check In API
↓
StageArt Server
↑
Check In API
↑
Device B

すべての端末が、
同じServer Side Business Ruleを利用する。

端末ごとに、
Check In状態を管理しない。

---

# 42. Reception Staff

受付スタッフが、
Mobile Clientを利用して
Check Inを行う場合、
Mobile ClientはReception Operationの
Clientとして扱う。

基本Flow：

Reception Staff
↓
Authentication
↓
Authorization
↓
Production / Performance Scope
↓
QR Scan
↓
Check In API
↓
Check In

受付スタッフが、
どのPerformanceを受付可能かは、
Authorizationによって決定する。

---

# 43. Reception Scope

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

---

# 44. QR Reader as Client Feature

QR Readerは、
独立したBusiness Domainではない。

QR Readerは、
Mobile Clientが持つDevice Featureである。

QR Reader
→ Ticket Identifierを取得する

Check In Domain
→ Ticketを検証しCheck Inを成立させる

という責務分離を維持する。

---

# 45. Application Service and Mobile Client

Mobile Client専用のBusiness Ruleを、
Application Serviceへ別途作らない。

Web ClientからCheck Inした場合も、
Mobile ClientからCheck Inした場合も、
同じCheck In Use Caseを利用する。

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

これにより、
ClientによるBusiness Ruleの差異を防ぐ。

---

# 46. Client Specific Presentation

Clientによって、
UIや操作方法は異なってよい。

例えば、

Web：

Reservation一覧
↓
Check In Button

Mobile：

Camera
↓
QR Scan
↓
Check In Result

という違いがある。

しかし、
最終的に実行するBusiness Operationは、
同じCheck In Use Caseとする。

---

# 47. Application Service Independence

Application Serviceは、
Clientの種類を意識しないことを基本とする。

例えばCheck In Use Caseは、

「Mobileから呼ばれた」

「Webから呼ばれた」

という情報を、
Business Ruleとして必要としない。

必要なAuthorization Contextだけを受け取る。

---

# 48. API Authentication

Mobile ClientからAPIへアクセスする場合も、
Authenticationを必要とする。

基本構造：

Mobile Client
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

# 49. Mobile Session

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

# 50. API Response

Check In APIは、
Mobile Clientが受付結果を判断できる
Responseを返す。

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

# 51. API and Client Error Handling

Clientは、
API Responseに基づいてUIを表示する。

例：

Success
→ 「受付完了」

Already Checked In
→ 「このチケットは受付済みです」

Invalid Ticket
→ 「利用できないチケットです」

Forbidden
→ 「この公演の受付権限がありません」

Network Error
→ 「通信できませんでした。再試行してください」

表示文言は、
Presentation Layerで管理する。

---

# 52. Domain Layer

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

# 53. Entity Operation

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

# 54. Value Object

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

# 55. Domain Service

複数EntityにまたがるBusiness Ruleで、
特定Entityへ責務を置くことが不自然な場合、
Domain Serviceを利用する。

Domain Serviceは、
Application WorkflowをOrchestrateするものではない。

Business Ruleそのものを扱う。

---

# 56. Application Service

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

# 57. Repository Interface

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
- JournalEntryRepository

Repository Interfaceは、
Database Technologyを公開しない。

---

# 58. Repository Implementation

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

# 59. Infrastructure Layer

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

# 60. Integration Layer

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

# 61. Integration Interface

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

# 62. Transaction

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

# 63. Cross Domain Transaction

複数DomainをまたぐOperationでは、
Transaction Boundaryを明確にする。

例えばCheck Inでは、

Reservation
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

# 64. CheckInCompleted Handling

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

# 65. Authorization in Application Layer

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

# 66. Organization Authorization

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

# 67. Production Authorization

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

# 68. Participant and Authorization

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

# 69. Query Architecture

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

など、
複数Domainの情報を組み合わせる場合がある。

Query側で、
表示に必要なData ModelへMappingする。

---

# 70. Read Model

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

# 71. Domain Entity and DTO

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

# 72. Domain Entity and Database Model

Domain EntityとDatabase Modelを分離する。

Domain Entity：

Business Conceptを表現する。

Database Model：

Persistence Structureを表現する。

DatabaseのColumn構造を、
そのままDomain EntityのInterfaceとして
公開しない。

---

# 73. Domain Module Structure

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

# 74. Identity Module

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

# 75. Organization Module

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

# 76. Production Module

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

# 77. Rehearsal Module

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

# 78. Ticket Module

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
- QRTicket

Ticket販売、
Reservation、
Issued Ticket、
Check Inの責務を分離する。

---

# 79. Accounting Module

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

# 80. Communication Module

Communication Moduleは、
連絡と配信を管理する。

主なConcept：

- Announcement
- Announcement Recipient
- Announcement Delivery

EmailなどのDelivery処理は、
Integration Layerと連携する。

---

# 81. Document Module

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

# 82. Promotion Module

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

# 83. Equipment Module

Equipment Moduleは、
団体が保有・管理する備品を管理する。

主なConcept：

- Equipment
- Equipment History

Equipmentは、
資産会計そのものを担当しない。

---

# 84. Regulation Module

Regulation Moduleは、
Organization規約を管理する。

主なConcept：

- Regulation
- Regulation Version

Versioning Ruleは、
Domain Layerで管理する。

---

# 85. Survey Module

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

# 86. Module Dependency

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

# 87. Domain Event as Module Boundary

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

# 88. Shared Kernel

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

# 89. Domain Isolation

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

# 90. Error Boundary

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

# 91. Validation Boundary

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

# 92. Logging Boundary

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

# 93. Audit Boundary

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

# 94. Background Processing

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

# 95. Idempotency

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

CheckInCompletedなどのEvent処理では、
同一Eventの重複処理を防止する。

---

# 96. Application State

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

# 97. Configuration Boundary

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

# 98. Feature Flag

将来的に段階的なFeature Releaseが必要になった場合、
Feature Flagを利用できる。

Feature Flagは、
Business Ruleの代替ではない。

Feature Flagによって、
一時的にFeatureのAvailabilityを制御する。

---

# 99. Application Observability

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

# 100. Performance Principle

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

# 101. Modular Monolith

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

# 102. Why Modular Monolith

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

# 103. WordPress Plugin Structure

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

# 104. WordPress Adapter

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

# 105. Application Entry Point

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

# 106. Scheduled Process

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

# 107. Internal Application Process

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

# 108. Application Consistency

Applicationは、
同じBusiness Operationについて、
どのEntry Pointから実行されても
同じBusiness Ruleが適用される構造を維持する。

例えばCheck Inが、

- Management Portal
- Mobile Client
- QR Scanner
- API
- Administrative Tool

のいずれから実行されても、
同じCheck In Use Caseを経由する。

---

# 109. Client Specific Presentation

Clientによって、
UIや操作方法は異なってよい。

例えば、

Web：

Reservation一覧
↓
Check In Button

Mobile：

Camera
↓
QR Scan
↓
Check In Result

という違いがある。

しかし、
最終的に実行するBusiness Operationは、
同じCheck In Use Caseとする。

---

# 110. Application Service Independence

Application Serviceは、
Clientの種類を意識しないことを基本とする。

例えばCheck In Use Caseは、

「Mobileから呼ばれた」

「Webから呼ばれた」

という情報を、
Business Ruleとして必要としない。

必要なAuthorization Contextだけを受け取る。

---

# 111. API Authentication

Mobile ClientからAPIへアクセスする場合も、
Authenticationを必要とする。

基本構造：

Mobile Client
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

# 112. Mobile Session

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

# 113. API Response

Check In APIは、
Mobile Clientが受付結果を判断できる
Responseを返す。

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

# 114. API and Client Error Handling

Clientは、
API Responseに基づいてUIを表示する。

例：

Success
→ 「受付完了」

Already Checked In
→ 「このチケットは受付済みです」

Invalid Ticket
→ 「利用できないチケットです」

Forbidden
→ 「この公演の受付権限がありません」

Network Error
→ 「通信できませんでした。再試行してください」

表示文言は、
Presentation Layerで管理する。

---

# 115. Testing Boundary

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

---

# 116. Testability

Application Layerは、
Infrastructureから独立してTestできる構造を目指す。

Repository InterfaceやIntegration Interfaceを
利用することで、
Test Doubleを利用できるようにする。

Domain Testでは、
External Serviceを必要としない。

Mobile Client Testでは、
CameraやNetworkをMockできる構造を目指す。

---

# 117. Architecture Decision Rule

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

# 118. Anti Pattern

以下の構造を避ける。

## Fat Controller

ControllerにBusiness Logicを集中させる。

## Fat Component

UI ComponentにBusiness Ruleを実装する。

## Fat Mobile Client

Mobile ClientにCheck Inなどの
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

---

# 119. Business Rule Location

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

---

# 120. Mobile Client Architecture Summary

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

# 121. QR Reception Architecture Summary

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

この構造により、
受付端末が複数存在しても、
すべてのCheck InをStageArt Serverで
一元的に管理できる。

---

# 122. Application Boundary Summary

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
- QR Scanner Client

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

# 123. Architecture Principle

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

QRの読み取りはClientで行うが、
Check Inの確定はServer Sideで行う。

Check Inが確定した後は、
CheckInCompletedを起点として、
HistoryやAccountingなどのDomainへ連携する。

この責務分離を維持することで、
UI、
Mobile Device、
WordPress、
Database、
External Service、
Infrastructure Technology

が変更されても、
StageArtのBusiness Ruleを長期的に維持できる
Application Architectureを目指す。

---
