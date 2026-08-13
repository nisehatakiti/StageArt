# StageArt Blueprint

# 10 - Architecture
# Application Architecture

Version : 1.4

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

StageArtはWeb Browserだけでなく、
SmartphoneなどのMobile Clientからも
Business Operationを実行できる構造とする。

Mobile Clientは、
受付専用Applicationではない。

公演関係者が日常的に利用する
StageArtのMobile Applicationとして構成し、

- 稽古情報の確認
- 公演情報の確認
- 自分の予定確認
- 連絡事項の確認
- その他Production関連情報の確認

などを通常利用できる。

公演当日など必要な場合には、
同じMobile Clientを
Reception Modeへ切り替えて利用できる。

Check Inについては、
Mobile Clientだけの機能とはせず、
Web ClientとMobile Clientの双方から
同一のApplication Use Caseを利用できる構造とする。

さらに、
Check Inの受付方法と
Check InそのもののBusiness Operationを分離する。

QR Code、
Reservation Number、
Booker Name、
Manual Selectionなど、
受付入口が異なっても、
最終的にはReservationを特定し、
共通のCheck In Use Caseを実行する。

System Administratorについては、
通常のOrganization Administratorとは
異なるSystem-levelの入口を持つ。

ただし、
Organizationの業務管理について
System Administrator専用の
別Management UIを作るのではなく、

System Administrator
↓
Organization List
↓
Organization Selection
↓
Selected Organization Context
↓
通常Management Client

という構造を基本とする。

Organizationを選択した後は、
そのOrganizationの
Organization Administrator相当のContextで
通常のManagement Clientを利用する。

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
- Mobile Clientを受付専用Applicationとして分離しない。
- Mobile ClientはNormal ModeとReception Modeを持つ。
- QR Codeの読み取り処理とBusiness Ruleを分離する。
- Check Inの最終的な確定処理はServer Sideで実行する。
- Client側でBusiness Factを確定しない。
- Check InはClient固有機能ではなく、共通Business Operationとして扱う。
- Web ClientとMobile ClientでCheck In Business Ruleを分けない。
- Check Inの受付方法とCheck In Business Operationを分離する。
- Reservation ResolutionをApplication Operationとして扱う。
- Issued TicketをCheck Inそのものとして扱わない。
- QR CodeをCheck In Business Factそのものとして扱わない。
- Scope外のDataをApplicationから取得できない構造とする。
- 同一Business Operationに対してClientごとに異なるBusiness Ruleを実装しない。
- System Administrator専用のBusiness Management Use Caseを重複して作らない。
- System AdministratorによるOrganization選択後は、通常のManagement Use Caseを利用する。
- Backup、Replication、Mirror、Restore、Failover、RecoveryなどのSystem OperationsをBusiness ApplicationのDomain Ruleと混在させない。

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

System Administration
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

System Administrationでは、
System-wideなOperationと
Organization Selectorを提供する。

OrganizationのBusiness Managementについては、
Organization Selection後、
通常のManagement Clientを利用する。

---

# 3. Layer Responsibilities

各Layerの責務を明確に分離する。

Presentation：

- UI
- User Interaction
- Navigation
- Device Feature
- Client State
- API Response Display
- Mode Switching

API：

- HTTP Request受付
- Authentication Context
- Authorization Context
- Request Validation
- DTO Mapping
- Application Use Case呼び出し
- Response Mapping
- Error Mapping

Application：

- Business OperationのOrchestration
- Use Case実行
- Transaction Boundary
- Authorization Contextの適用
- Selected Organization Contextの適用
- Domain Objectの組み合わせ
- Repository / Gateway利用
- Domain Event発行
- Application Result生成

Domain：

- Business Rule
- Business Entity
- Value Object
- Domain Service
- Domain Event
- Business State

Infrastructure：

- Database
- File Storage
- External Service
- Authentication Provider
- Message / Mail Service
- その他Technical Implementation

System Operations：

- Backup
- Restore
- Replication
- Mirror
- Failover
- Recovery
- Monitoring
- Logging
- Deployment

System Operationsは、
Business DomainのBusiness Ruleとは
分離する。

---

# 4. Dependency Direction

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
Application / Domainが定義した
Interfaceを実装する。

基本構造：

Application
↓
Repository Interface

Infrastructure
↓
Repository Implementation

Domainから、
DatabaseやExternal Serviceの
具体的なImplementationへ直接依存しない。

System Operationsについても、
Business Domainの内部Ruleを
直接実装する場所とはしない。

---

# 5. Client Architecture

StageArtは、
複数のClientから利用できる構造とする。

主なClient：

- Web Client
- Mobile Client
- Public Client
- System Administration

Receptionは、
独立したClientではない。

Receptionは、
Mobile ClientのOperational Modeとして提供する。

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

Public Client
↓
API
↓
Application
↓
Domain

System Administration
↓
API
↓
Application
↓
System Operation / Organization Selection

ClientごとにBusiness Ruleを
個別実装しない。

---

# 6. Web Client

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

# 7. Web Check In Client

Web Clientからも、
Check Inを実行できるものとする。

Web Check Inでは、
QR Codeを必須としない。

基本的には、

Performance
↓
Reservation / Issued Ticket List
↓
Search / Filter
↓
対象Reservation / Ticket選択
↓
Reservation Resolution
↓
Check In
↓
Check In Result

というFlowを利用する。

Web Clientでは、
受付担当者が一覧から対象者を確認し、
手動でCheck Inを実行できる。

---

# 8. Web Check In List

Web ClientのCheck In画面では、
必要に応じて以下の情報を一覧表示できる。

- Reservation
- Person
- Issued Ticket
- Ticket Type
- Performance
- Check In Status
- Check In Time
- Ticket Identifier
- Reservation Number

また、
必要に応じて以下のFilterを提供できる。

- 未受付
- 受付済み
- Reservation Status
- Ticket Type
- Person Name
- Ticket Number
- Reservation Number

具体的なUI仕様は、
Frontend Architectureで定義する。

一覧表示はQuery Operationであり、
Check In Business Factを変更しない。

---

# 9. Web Manual Check In

Web ClientからのManual Check Inも、
Mobile ClientからのQR Check Inと
同じBusiness Operationとして扱う。

基本構造：

Web Client
↓
Reservation / Ticket List
↓
対象選択
↓
Reservation Resolution
↓
Check In API
↓
Check In Use Case
↓
Check In Domain

Web Clientから直接Databaseの
Check In Statusを変更しない。

---

# 10. Mobile Client

Mobile Clientは、
SmartphoneなどのMobile Deviceから
StageArtを利用するためのClientである。

Mobile Clientは、
公演関係者が日常的に利用する
StageArt Mobile Applicationとして実装する。

Mobile Clientは、
受付専用Applicationではない。

通常時から、

- 稽古情報
- 公演情報
- 自分の予定
- Production情報
- Performance情報
- 連絡事項

などを確認できる。

必要な場合には、
同じMobile Clientを
Reception Modeへ切り替える。

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

# 11. Mobile Client Responsibilities

Mobile Clientは、
以下のようなClient-side機能を担当する。

- User Interface
- Authentication UI
- Navigation
- Mode Switching
- QR Code Scan
- Camera Access
- User Input
- API Request
- API Response Display
- Connection State
- Loading State
- Error State
- Local Presentation State

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

# 12. Mobile Normal Mode

Mobile Clientの通常Modeを、
Normal Modeとする。

Normal Modeでは、
公演関係者が日常的にStageArtを利用する。

主な情報：

- Home
- 今日の稽古
- 次回の稽古
- Production情報
- Performance情報
- 自分の予定
- 連絡事項

など。

Normal Modeは、
PC版Management Clientの
すべての管理機能を置き換えるものではない。

Mobile Clientでは、
現場で必要な情報を
簡易的かつ迅速に確認することを重視する。

---

# 13. Mobile Rehearsal

Mobile Clientでは、
Rehearsal情報を簡易的に確認できる。

例えば、

- 今日の稽古
- 次回の稽古
- 開始時刻
- 終了時刻
- 場所
- 対象Production
- 稽古内容
- 参加予定
- 連絡事項

など。

Mobile Clientは、
Rehearsal Managementの
すべての管理機能を実装する必要はない。

詳細なRehearsal Managementは、
Web Management Clientで行う。

Mobile Clientは、
現場確認を主な目的とする。

---

# 14. Mobile Production / Performance

Mobile Clientでは、
ユーザーが関係するProductionやPerformanceについて、
必要な情報を確認できる。

例えば、

- Production Name
- Performance Date
- Performance Time
- Venue
- Related Rehearsal
- Personal Participation
- Production Communication

など。

具体的な表示項目は、
Frontend Architectureで定義する。

Application Architectureでは、
Mobile Clientが
Production / Performance情報を
取得できるApplication Queryを提供することを定義する。

---

# 15. Mobile Reception Mode

Receptionは、
独立したMobile Applicationではなく、
Mobile ClientのOperational Modeとして提供する。

基本構造：

Mobile Client
↓
Performance
↓
Reception Mode

Reception Modeでは、
必要に応じて、

- QR Code Scan
- Reservation Search
- Reservation Number Search
- Booker Name Search
- Manual Selection
- Check In
- Reception Status

などを利用できる。

Reception Modeは、
通常のMobile Clientと
同じAuthentication / Authorization Boundaryを利用する。

---

# 16. Reception Mode Activation

Reception Modeは、
適切なPerformance Scopeおよび
Reception Permissionを持つUserが利用できる。

基本構造：

Person
↓
Organization / Production Scope
↓
Performance
↓
Reception Permission
↓
Reception Mode

権限のないUserには、
Reception Modeを表示または利用させない。

Client側のUI制御だけではなく、
Server SideでもAuthorizationを検証する。

---

# 17. Reception Mode Boundary

Reception Modeは、
Client側のOperational Modeである。

Check In Business Ruleは、
Reception Modeには存在しない。

基本構造：

Mobile Client
↓
Reception Mode
↓
Check In API
↓
Application
↓
Check In Domain

Reception Modeは、
Check In Business Operationへの
UI / Device側の入口である。

Check In Business Factは、
Server Sideで確定する。

---

# 18. QR Scanner

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
Ticket Identifier
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

# 19. QR Code Boundary

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
Reservation Resolution
↓
Reservation
↓
Check In

QR Codeがコピーされた場合でも、
Server SideでTicketの状態と
Authorizationを検証する。

---

# 20. QR Code Validation

QR Codeを読み取っただけでは、
Check Inを成立させない。

Server Sideで、

- Ticketの存在
- Ticketの有効性
- Ticketの対象Performance
- Ticketの利用状態
- Reservationの存在
- Reservationとの整合性
- Check In済みか
- 必要なAuthorization
- その他Business Rule

を検証する。

QR Codeに含まれる情報を、
無条件に信頼しない。

---

# 21. Application Use Case

Application Layerでは、
Client Requestを
Business Operationとして扱う。

代表的なUse Case：

- Create Reservation
- Confirm Reservation
- Cancel Reservation
- Issue Ticket
- Check In
- Create Rehearsal
- Confirm Rehearsal
- Record Attendance
- Create Journal Entry
- Publish Announcement
- Create Document

Use Caseは、
Business Operationを
Application LayerでOrchestrateする。

---

# 22. Check In as Common Business Operation

Check Inは、
Mobile Client固有の機能ではない。

Check Inは、
StageArtにおける共通Business Operationとして扱う。

Check Inへの入口は、

- Web Client
- Mobile Client
- System AdministrationからのOrganization Context

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

System Administrator
↓
Organization Selector
↓
Selected Organization Context
↓
Management Client
↓
Check In API
↓
Check In Use Case

---

# 23. Check In Entry Methods

Check InのClient側の入口は、
Clientによって異なってよい。

利用可能な入口には、
以下を含む。

- QR Code
- Reservation Number
- Booker Name
- Manual Selection
- Reservation Identifier

それぞれの入口では、
Reservationを特定する方法が異なる。

しかし、
最終的なBusiness Operationは
共通のCheck In Use Caseとする。

---

# 24. Reservation Resolution

Reservation Resolutionは、
受付Inputから対象Reservationを
特定するApplication Operationである。

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

Reservation ResolutionへのInputには、

- Reservation Identifier
- Reservation Number
- Booker Name
- Issued Ticket Identifier
- Manual Selection

などを利用できる。

Reservation Resolutionは、
Check Inそのものとは分離する。

---

# 25. Reservation Resolution and QR

QR受付の場合は、
以下のResolutionを行う。

QR Code
↓
Ticket Identifier
↓
Issued Ticket
↓
Reservation
↓
Check In Use Case

Issued Ticketを経由して
Reservationを特定する。

Issued Ticket自体を、
Check In Business Factとはしない。

---

# 26. Reservation Number Resolution

Reservation Numberを利用する場合は、

Reservation Number
↓
Reservation Search
↓
Reservation Resolution
↓
Reservation
↓
Check In Use Case
↓
Check In

というFlowを利用する。

Reservation Numberは、
Check In Factそのものではない。

Reservation Numberから、
対象Reservationを特定するために利用する。

---

# 27. Booker Name Resolution

Booker Nameを利用する場合は、

Booker Name
↓
Reservation Search
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

というFlowを利用する。

Booker Nameだけでは、
Reservationを一意に特定できない場合がある。

その場合は、
候補一覧から受付担当者が対象を確認する。

Booker Name Searchは、
Query Operationであり、
Check In Factを直接変更しない。

---

# 28. Manual Selection Resolution

Manual Selectionでは、
Performanceに紐づくReservation一覧から
対象Reservationを選択する。

基本Flow：

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

一覧から選択された場合でも、
Server Sideで対象Reservationを再確認する。

---

# 29. Reservation Resolution Responsibility

Reservation Resolutionでは、
以下をApplication Boundaryで扱う。

- Inputの解釈
- Reservation候補取得
- Identifier Resolution
- Scope確認
- Resource存在確認
- Reservationとの関連確認
- Performance Context確認

Reservation Resolutionは、
Client側だけで完結させない。

Clientが保持するReservation IDや
Ticket IDを無条件に信頼しない。

---

# 30. Reservation Resolution and Authorization

Reservation Resolutionでは、
Authorization Scopeを適用する。

例えば、

受付担当者
↓
Production Scope
↓
Performance Scope
↓
Reservation

というScopeを確認する。

Scope外のReservationを、
Reservation Resolutionによって
取得できない構造とする。

Resource IDを知っているだけでは、
Reservationを取得できない。

---

# 31. Check In Application Flow

Check Inの基本Application Flow：

Client
↓
Check In API
↓
Authentication
↓
Authorization
↓
Reservation Resolution
↓
Load Reservation
↓
Load Issued Ticket if applicable
↓
Validate Reservation
↓
Validate Ticket if applicable
↓
Validate Performance
↓
Check In Use Case
↓
Check In Domain
↓
Persist Check In
↓
Publish CheckInCompleted
↓
Commit
↓
Application Result
↓
API Response
↓
Client

ClientがWebでもMobileでも、
Application以下のBusiness Operationは共通とする。

---

# 32. Check In Application Use Case

Check In Use Caseは、
Check Inに必要なBusiness Operationを
Orchestrateする。

基本的な責務：

- Authorization Context確認
- Reservation Resolution
- Reservation取得
- Issued Ticket取得
- Business State確認
- Check In Domain Operation実行
- Transaction Boundary管理
- CheckInCompleted発行
- Application Result生成

Check In Use Case自身が、
Domain Business Ruleをすべて実装するわけではない。

Business Ruleは、
Domain Layerへ委譲する。

---

# 33. Check In Domain Boundary

Application Layerは、
Check In Domainへ
Business Operationを委譲する。

基本構造：

Check In Use Case
↓
Check In Domain
↓
Check In Business Rule
↓
Check In Fact

Application Layerは、
Domain Entityの内部状態を
直接書き換えない。

---

# 34. Check In Canonical Relationship

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

# 35. Issued Ticket and Check In

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

# 36. Check In and Performance

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

Check In Requestで指定された
Performance Contextと、
Reservationが紐づくPerformanceが
一致していることを確認する。

---

# 37. Check In and Reservation State

Check In実行時には、
Reservationの状態を
Server Sideで検証する。

例えば、

- Cancelled
- Invalid
- Expired
- Already Checked In

など。

具体的なState Ruleは、
Reservation / Check In Domainで定義する。

---

# 38. Check In and Ticket State

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

# 39. Check In Result

Check In Use Caseは、
Application Resultを生成する。

概念的には、

- Check In Identifier
- Reservation Identifier
- Performance Identifier
- Check In Status
- Check In Time
- Result

などを含む。

Application Resultは、
API Response DTOへMappingされる。

Domain Entityを、
そのままClientへ返さない。

---

# 40. Already Checked In

対象Reservationが、
すでにCheck In済みの場合、
同一Business Factを二重作成しない。

Application Layerでは、
Domainから返されたStateを
Application ResultへMappingする。

例えば、

Already Checked In

というResultを、
API Layerへ渡すことができる。

これは、
Client側だけで判断するものではない。

---

# 41. Check In Idempotency

Check Inは、
RetryやDouble Submitが発生しやすいため、
Idempotencyを考慮する。

基本構造：

Client
↓
Check In Request
↓
Timeout
↓
Client Retry
↓
Same Operation
↓
Existing Check In確認
↓
Duplicate Factを作成しない

Idempotencyの具体的なImplementationは、
Infrastructure / Data Architectureと連携して定義する。

---

# 42. Check In Concurrency

複数Clientが、
同一Reservationに対して
同時にCheck Inを実行する可能性を考慮する。

例えば、

Web Client
↓
Reservation A
↓
Check In

と同時に、

Mobile Client
↓
QR Ticket A
↓
Check In

が発生する場合でも、
Check In Factを二重作成しない。

Application Layerでは、
Transaction Boundaryを明確にし、
Domain / Persistence Layerと連携して
Consistencyを保証する。

---

# 43. Check In Transaction Boundary

Check In Use Caseは、
必要なBusiness Factを
一貫したTransaction Boundaryで確定する。

概念的には、

Load Reservation
↓
Resolve
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

という構造を採用する。

具体的なTransaction、
Lock、
Isolation Levelなどは、
Data Architecture / Backend Architectureで定義する。

---

# 44. CheckInCompleted

Check Inが確定すると、

CheckInCompleted

を発行する。

基本構造：

Check In
↓
CheckInCompleted

CheckInCompletedは、
後続Application Processの起点として利用できる。

例えば、

CheckInCompleted
├── Audience History
└── Accounting Process

など。

---

# 45. CheckInCompleted Boundary

CheckInCompletedは、
Check In Business Factが確定した後に
発行する。

Check Inそのものと、
後続処理を分離する。

例えば、

Check In
↓
Commit
↓
CheckInCompleted
↓
History
↓
Accounting

という構造を採用できる。

具体的なEvent Delivery Strategyは、
Integration Architecture / Backend Architectureで定義する。

---

# 46. Audience History

Audience Historyは、
Check In Business Factを起点として
生成・参照する。

基本構造：

Check In
↓
CheckInCompleted
↓
Audience History

Ticket購入だけでは、
Audience Historyを確定しない。

Audience Historyの詳細なDomain Ruleは、
History Domainで定義する。

---

# 47. Accounting Integration

Check InとAccountingは、
Application上でもDomainとして分離する。

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

Check In Use Caseが、
Journal Entryの内部構造を
直接操作しない。

Accounting処理は、
Accounting Application Processへ委譲する。

---

# 48. Application Command

Business Operationの実行には、
Commandを利用できる。

例えば、

- CreateReservationCommand
- ConfirmReservationCommand
- IssueTicketCommand
- CheckInCommand
- ConfirmRehearsalCommand
- RecordAttendanceCommand

など。

Commandは、
Client Request DTOと
Domain Entityを直接同一視しない。

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

---

# 49. CheckInCommand

Check Inでは、
CheckInCommandを利用する。

概念的には、

CheckInCommand

として、

- Reservation Identifier
- Performance Context
- Issued Ticket Identifier if applicable
- Source
- Idempotency Information
- Authorization Context

などをApplicationへ渡す。

具体的なCommand DTOは、
API Contract / Implementation Specificationで定義する。

---

# 50. Check In Source

Check Inが、
どの受付経路から実行されたかを
必要に応じてApplication Contextとして扱う。

例：

- WEB_MANUAL
- WEB_SEARCH
- WEB_RESERVATION_NUMBER
- WEB_BOOKER_NAME
- MOBILE_QR
- SYSTEM_ADMIN_CONTEXT

Sourceは、
Business Ruleそのものを変更する値ではない。

Sourceは、
Audit / Operation Contextなどの
補助情報として扱う。

---

# 51. Query and Command

Application Architectureでは、
QueryとCommandを分離する。

Query：

- Reservation Search
- Performance List
- Check In List
- Ticket Search
- Participant List
- Rehearsal List
- Personal Schedule
- Production Information

Command：

- Create Reservation
- Confirm Reservation
- Issue Ticket
- Check In
- Cancel Reservation
- Confirm Rehearsal
- Record Attendance

Queryは、
Business Factを変更しない。

Commandは、
Business Operationを実行する。

---

# 52. Check In List Query

Web Check In一覧は、
Query Operationとして扱う。

基本構造：

Web Client
↓
Check In List API
↓
Check In List Query
↓
Authorized Read Model
↓
Web Client

一覧には、

- Reservation
- Person
- Issued Ticket
- Performance
- Check In Status

などを表示できる。

ただし、
一覧表示時点のStateを
Check In確定の根拠として無条件に利用しない。

---

# 53. Check In List and Read Model

Check In一覧では、
必要に応じてRead Model / Projectionを利用できる。

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
最新Business Factを再検証する。

---

# 54. Mobile Query Operations

Mobile ClientのNormal Modeでは、
現場確認に必要なQueryを利用する。

例：

- Today's Rehearsal
- Upcoming Rehearsal
- My Schedule
- Production Information
- Performance Information
- Communication

これらは、
Mobile Client専用のBusiness Ruleではない。

既存のDomain Dataを、
Mobile向けQuery / Read Modelとして
提供する。

---

# 55. Scope-aware Application

Application Use Caseは、
Authorization Scopeを考慮して
Operationを実行する。

基本構造：

Authenticated Principal
↓
Organization Scope
↓
Project Scope
↓
Production Scope
↓
Permission
↓
Use Case
↓
Resource

Scope外Dataを、
Application Layerで取得できない構造とする。

---

# 56. Authorization Context

Application Use Caseには、
必要に応じてAuthorization Contextを渡す。

Authorization Contextには、

- Person
- Organization
- Role
- Permission
- Production Scope
- Project Scope
- Selected Organization

などの情報を含めることができる。

Applicationは、
このContextを利用して
Use Case実行可能性を判断する。

---

# 57. System Administrator Context

System Administratorは、
通常のOrganization Userとは異なる
System-level Roleを持つ。

ただし、
OrganizationのBusiness Operationについては、
System Administrator専用の
別Business Ruleを作らない。

基本構造：

System Administrator
↓
Organization Selector
↓
Selected Organization
↓
Selected Organization Context
↓
Organization Administrator相当
↓
Management Client
↓
通常のApplication Use Case

Selected Organization Contextは、
そのOrganizationに対する
Organization Administrator相当の
Authorization Contextとして扱う。

---

# 58. Organization Selector Application Operation

Organization Selectorは、
System Administratorだけが利用できる
System-level Query / Selection Operationである。

基本構造：

System Administrator
↓
Organization List Query
↓
Organization Selection
↓
Selected Organization Context

Organization Listは、
通常UserのScope制御を受ける
Business Queryとは異なる。

System Administratorだけが、
全Organizationを一覧から選択できる。

ただし、
Organization Selection後のBusiness Data操作は、
通常のOrganization Scope / Permission Boundaryを利用する。

---

# 59. Selected Organization Context

Selected Organization Contextは、
System Administratorが
選択したOrganizationについて
通常のManagement Clientを利用するためのContextである。

基本構造：

System Administrator
↓
Selected Organization
↓
Organization Administrator Context
↓
Management Client

このContextを利用することで、

- Organization Management
- Production Management
- Rehearsal Management
- Performance Management
- Ticket Management
- Reservation Management
- Check In Management
- Accounting
- Communication
- Document Management

などを、
既存のManagement Clientで利用できる。

System Administrator専用の
重複したBusiness Management UIを作らない。

---

# 60. System Administrator and Business Rule

System Administratorであることによって、
Business Ruleそのものを変更しない。

例えば、

Organization Administrator
↓
Check In

と、

System Administrator
↓
Selected Organization Context
↓
Check In

は、
同じCheck In Use Caseを利用する。

System Administratorだからといって、
Check InのBusiness Ruleを
Bypassしない。

---

# 61. Scope Isolation

Resource IDを知っているだけでは、
Application Operationを実行できない。

例えば、

Organization A User
↓
Reservation B

というRequestがあった場合、
Reservation Bが存在していても、
適切なScopeがなければ
Use Caseを実行できない。

System Administratorについては、
Organization Selectorによって
Selected Organization Contextを明示的に生成する。

Scope Isolationは、
APIだけでなくApplicationでも維持する。

---

# 62. Domain Module Structure

StageArt Domainは、
Business Capabilityごとに
Moduleを分離する。

例：

- Identity
- Organization
- Project
- Production
- Performance
- Person
- Participant
- Ticket
- Reservation
- Check In
- Rehearsal
- History
- Accounting
- Communication
- Document
- Promotion

具体的なModule境界は、
Domain Architectureで定義する。

---

# 63. Module Ownership

各Moduleは、
自身のBusiness Ruleと
Business Dataを所有する。

例えば、

Reservation Module
→ Reservation Business Rule

Ticket Module
→ Ticket Business Rule

Check In Module
→ Check In Business Rule

Accounting Module
→ Accounting Business Rule

というOwnershipを維持する。

他ModuleのPersistence Modelを
直接操作しない。

---

# 64. Cross Module Operation

複数ModuleにまたがるBusiness Operationは、
Application LayerでOrchestrateする。

例えばCheck Inでは、

Reservation
↓
Ticket
↓
Check In
↓
History
↓
Accounting

という複数Moduleの関係を扱う。

ただし、
各ModuleのBusiness Ruleは
それぞれのDomainへ委譲する。

---

# 65. Cross Module Dependency

Module間の直接依存を最小化する。

例えば、

Check In Module
↓
Reservation Module

という関係が必要な場合でも、
Reservation Moduleの内部Persistenceを
直接参照しない。

Application Interface、
Domain Service、
Domain Eventなどを利用して連携する。

---

# 66. Domain Events

Business Factの確定後に、
Domain Eventを発行できる。

例：

- ReservationConfirmed
- TicketIssued
- CheckInCompleted
- RehearsalConfirmed
- AttendanceRecorded

Domain Eventは、
Client API Responseとは異なる
内部Application Eventとして扱う。

---

# 67. Application Event Handling

Domain Eventを受けて、
後続Application Processを実行できる。

例えば、

CheckInCompleted
↓
Audience History Process

または、

CheckInCompleted
↓
Accounting Process

など。

後続Processは、
元のDomainの内部Implementationを
直接操作しない。

---

# 68. Transaction and Events

Transaction Boundaryと
Event発行タイミングを明確にする。

基本的には、

Business Fact確定
↓
Transaction Commit
↓
Event Delivery

というConsistencyを考慮する。

具体的なOutboxなどの実装方式は、
Backend Architectureで定義する。

---

# 69. Application and Repository

Application Layerは、
必要なData取得のために
Repository Interfaceを利用できる。

例えば、

- ReservationRepository
- TicketRepository
- CheckInRepository
- PerformanceRepository
- RehearsalRepository
- OrganizationRepository

など。

Repositoryの具体的なDatabase実装は、
Infrastructure Layerに置く。

---

# 70. Application and Gateway

External Serviceへのアクセスには、
Gateway / Portを利用する。

例えば、

- Mail Gateway
- Calendar Gateway
- File Storage Gateway
- Payment Gateway
- Social Media Gateway

など。

Application / Domainから、
External Serviceの具体的SDKへ
直接依存しない。

---

# 71. Integration Boundary

External ServiceとのIntegrationは、
ApplicationとInfrastructureの境界で分離する。

基本構造：

Application
↓
Integration Interface
↓
Integration Implementation
↓
External Service

External ServiceのAPI Contractを、
StageArt Domainへ直接持ち込まない。

---

# 72. Error Handling

Application Layerでは、
Infrastructure Exceptionや
Domain Errorを
Application Result / ExceptionへMappingする。

Clientへ、
Internal Exceptionを直接返さない。

例えば、

Database Exception
↓
Application Error

External API Error
↓
Integration Error

Domain Business Rule Error
↓
Business Error

というMappingを行う。

---

# 73. Business Error

Business Ruleに違反した場合、
Application Layerは
Client向けに処理可能なResultへ
Mappingできる。

例えば、

- Reservation Not Found
- Invalid Reservation
- Invalid Ticket
- Already Checked In
- Check In Not Allowed
- Forbidden
- Scope Violation

など。

---

# 74. Idempotency Boundary

Idempotencyは、
Application Use Caseの
Business Operation単位で考慮する。

特に、

- Check In
- Reservation Confirmation
- Ticket Issuance
- Journal Entry

などは、
Retryによる重複処理を防ぐ必要がある。

具体的なStorageやUnique Constraintは、
Data / Backend Architectureで定義する。

---

# 75. Concurrency Boundary

Concurrent Operationが発生する可能性のある
Business Operationでは、
Application Transaction Boundaryを明確にする。

特に、

- Check In
- Ticket Issuance
- Reservation Update
- Journal Entry

など。

Application Layerは、
Domain / Persistence Layerと連携して
Consistencyを保証する。

---

# 76. Application and Persistence

Application Layerは、
Persistenceの具体構造を知らない。

例えば、

Application
↓
ReservationRepository

とし、

ReservationRepository
↓
Database

の具体的なMappingは、
Infrastructure Layerで実装する。

Applicationは、
TableやSQLの構造を
Business Ruleとして扱わない。

---

# 77. Application and Database

Applicationは、
Databaseの直接操作を行わない。

以下のような構造を基本とする。

Application
↓
Repository
↓
Infrastructure
↓
Database

Client
↓
API
↓
Application
↓
Repository
↓
Database

ClientからDatabaseへの
Direct Accessは禁止する。

---

# 78. Application and WordPress

WordPressをInfrastructureとして
利用する場合でも、

Application
↓
Repository Interface
↓
WordPress Repository
↓
WordPress Database

というBoundaryを維持する。

WordPressの内部Database Structureを、
Application Business Ruleへ直接持ち込まない。

---

# 79. PHP Server

PHPをServer-side Technologyとして
利用する場合でも、
Application Architectureの
Layer Separationを維持する。

基本構造：

PHP API Controller
↓
Application Use Case
↓
Domain
↓
Repository
↓
Infrastructure

PHP Frameworkの具体的な選択は、
Backend Architectureで定義する。

---

# 80. Mobile and Web Common Use Case

Web ClientとMobile Clientは、
同一Business Operationについて
同一Application Use Caseを利用する。

例えばCheck Inでは、

Web
↓
Check In API
↓
Check In Use Case

Mobile
↓
Check In API
↓
Check In Use Case

とする。

Clientごとに、

WebCheckInUseCase

MobileCheckInUseCase

のような別Business Ruleを
原則として作らない。

---

# 81. Client-specific Adapter

Client固有の入力形式が必要な場合は、
Application Boundaryの手前で
Adapterを利用できる。

例えば、

QR Payload
↓
QR Adapter
↓
Ticket Identifier

または、

Booker Name Input
↓
Reservation Search Adapter
↓
Reservation Candidate

など。

ただし、
AdapterはBusiness Ruleを所有しない。

---

# 82. Check In Entry Adapter

受付方法が異なる場合でも、
最終的に共通CommandへMappingする。

QR：

QR Payload
↓
Ticket Identifier
↓
Reservation Resolution
↓
CheckInCommand

Reservation Number：

Reservation Number
↓
Reservation Resolution
↓
CheckInCommand

Booker Name：

Booker Name
↓
Candidate Reservation
↓
Reservation Resolution
↓
CheckInCommand

Manual Selection：

Reservation Selection
↓
Reservation Resolution
↓
CheckInCommand

---

# 83. Check In Source Independence

Check In Sourceによって、
Business Ruleを変更しない。

例えば、

MOBILE_QRだから許可する

WEB_MANUALだから別Ruleを適用する

という構造にしない。

Sourceは、
Audit / Reporting / Operation Contextのために
利用する補助情報である。

---

# 84. Check In Result Consistency

Check In Resultは、
Server SideのBusiness Factを基準とする。

Clientが保持する、

- Local State
- Cached State
- Previous Response

などを、
Server Business Factより優先しない。

---

# 85. Application Cache

Cacheは、
ApplicationのPerformance改善に
利用できる。

ただし、
CacheをBusiness Factの正本としない。

特にCheck In Statusなど、
Consistencyが重要なDataについては、
Cache利用時のStalenessを考慮する。

---

# 86. Read Model

List / Search / Dashboardなどでは、
Read Model / Projectionを利用できる。

基本構造：

Domain Fact
↓
Projection
↓
Read Model
↓
Application Query
↓
API
↓
Client

Read Modelは、
Business Factの正本ではない。

---

# 87. Check In Read Model

Web Check In一覧では、
必要に応じて受付用Read Modelを利用できる。

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

を組み合わせたRead Modelを利用する。

ただし、
Check In実行時には、
最新Business Factを再検証する。

---

# 88. Application Security Boundary

Applicationは、
Security Boundaryの一部として扱う。

Authenticationだけでは、
Use Case実行を許可しない。

基本構造：

Authentication
↓
Authorization
↓
Scope
↓
Use Case
↓
Domain

Clientから送信されたIdentifierだけを
信頼してBusiness Operationを実行しない。

---

# 89. Personal Data

Personに関するApplication Operationでは、
必要なPersonal Dataだけを扱う。

特に、

- Person Name
- Contact Information
- Reservation Information
- Audience History

などは、
Authorization Scopeを適用する。

Public Clientへ返すDataは、
Public Projectionを利用する。

---

# 90. Public Application Boundary

Public ClientからのRequestでは、
Public Operationのみを公開する。

基本構造：

Public Client
↓
Public API
↓
Public Application Use Case
↓
Public Projection / Domain

Internal Management Use Caseを、
Public Clientから直接実行できる構造にしない。

---

# 91. Management Application Boundary

Management Clientからは、
Authorization Scope内で
Management Use Caseを実行する。

例：

- Organization Management
- Project Management
- Production Management
- Participant Management
- Ticket Management
- Reservation Management
- Check In Management
- Accounting Management
- Rehearsal Management
- Communication Management
- Document Management

Management Use Caseでも、
Domain Business Ruleを
Applicationから直接複製しない。

---

# 92. System Administration Application Boundary

System Administrationは、
Business Managementとは
異なるApplication Boundaryを持つ。

主なSystem Administration Operation：

- Organization List
- Organization Selection
- System Health
- Backup Status
- Replication Status
- Mirror Status
- Recovery Status
- Operational Log

OrganizationのBusiness Managementは、
System Administration内に
重複実装しない。

Organizationを選択した後は、

Selected Organization Context
↓
Management Client

へ移行する。

---

# 93. System Administration and Business Management

System Administration：

StageArt全体を確認し、
Organizationを選択する。

Business Management：

選択されたOrganizationについて、
通常のManagement Clientを利用する。

基本構造：

System Administration
↓
Organization Selector
↓
Selected Organization
↓
Management Client

この構造により、
System Administrator専用の
Organization / Production / Rehearsal / Reservation
などの重複UIを作らない。

---

# 94. System Operations Boundary

System Operationsは、
Business Operationsとは分離する。

Business Operations：

- Create Reservation
- Confirm Reservation
- Issue Ticket
- Check In
- Record Attendance
- Create Journal Entry

System Operations：

- Backup
- Restore
- Replication
- Failover
- Recovery
- Deployment
- Monitoring
- Maintenance

System Operationsを、
Business Domain Entityの
Business Ruleとして実装しない。

---

# 95. Backup Boundary

Backupは、
Application Business Operationとは
別のSystem Operationとして扱う。

基本構造：

Primary
↓
Backup Process
↓
Backup Storage

Backup対象には、
必要に応じて、

- Database
- Business Data
- Uploaded Files
- Configuration
- Operational Data

などを含める。

具体的なBackup Strategyは、
Operations Architectureで定義する。

---

# 96. Replication Boundary

Replicationは、
Primary Environmentから
Mirror Environmentへ
Dataを同期するSystem Operationである。

基本構造：

Primary
↓
Replication
↓
Mirror

Replicationは、
Backupとは異なる。

Replicationは、
主としてAvailability / Failoverを目的とする。

---

# 97. Mirror Boundary

Mirror Environmentは、
Primary Environmentの
代替環境として構成できる。

Mirrorには、
必要に応じて、

- Application
- Database
- Configuration
- Storage

などを同期する。

具体的なMirror方式は、
Operations Architectureで定義する。

---

# 98. Recovery Boundary

Recoveryは、
障害発生時にStageArtを
利用可能な状態へ戻すSystem Operationである。

例えば、

Primary Failure
↓
Mirror
↓
Failover
↓
Service Recovery

または、

Data Corruption
↓
Backup
↓
Restore
↓
Consistency Check
↓
Recovery

など。

具体的なRecovery Strategyは、
Operations Architectureで定義する。

---

# 99. Application Logging

Application Operationでは、
必要なOperational Contextを
Logへ記録できる。

例：

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

# 100. Application Observability

重要なApplication Use Caseについて、
以下を観測できる構造とする。

- Execution Count
- Execution Time
- Success Count
- Failure Count
- Business Error
- Authorization Error
- Integration Error

特にCheck Inについては、

- Check In Count
- Already Checked In
- Invalid Ticket
- Authorization Failure
- System Failure

などを把握できるようにする。

---

# 101. Application Testing

Application Layerでは、
Use Case単位でテストできる構造とする。

主な対象：

- Create Reservation
- Confirm Reservation
- Issue Ticket
- Check In
- Confirm Rehearsal
- Record Attendance
- Accounting Operation
- Organization Selection
- Selected Organization Context

Check Inでは特に、

- QR Check In
- Reservation Number Check In
- Booker Name Check In
- Manual Selection Check In
- Already Checked In
- Invalid Reservation
- Invalid Ticket
- Performance Mismatch
- Scope Violation
- Concurrent Check In
- Retry / Idempotency

をテスト対象とする。

System Administratorについては、

- Organization List取得
- Organization Selection
- Selected Organization Context生成
- Organization Administrator相当のAuthorization
- Selected Organization外へのAccess拒否

をテスト対象とする。

---

# 102. Check In Testing Boundary

異なる受付入口でも、
同じCheck In Use Caseに到達することを確認する。

例えば、

QR
↓
Reservation Resolution
↓
CheckInCommand
↓
Check In Use Case

Reservation Number
↓
Reservation Resolution
↓
CheckInCommand
↓
Check In Use Case

Manual Selection
↓
Reservation Resolution
↓
CheckInCommand
↓
Check In Use Case

という構造をテストする。

---

# 103. Application Architecture and API Architecture

API Architectureは、
ClientからApplicationへの
External Boundaryを定義する。

Application Architectureは、
APIからDomainまでの
Internal Boundaryを定義する。

基本構造：

Client
↓
API Architecture
↓
Application Architecture
↓
Domain Architecture
↓
Data Architecture
↓
Infrastructure

APIが、
Application Use Caseを呼び出し、
Applicationが、
Domain Business OperationをOrchestrateする。

---

# 104. Application Architecture and Data Architecture

Application Architectureは、
Data Architectureで定義された
Business Ownershipを尊重する。

例えば、

Person
→ Person Domain

Reservation
→ Reservation Domain

Issued Ticket
→ Ticket Domain

Check In
→ Check In Domain

Accounting
→ Accounting Domain

というOwnershipを維持する。

Applicationが、
他DomainのDataを
直接更新しない。

---

# 105. Application Architecture and Backend Architecture

Application Architectureは、
Business Operationと
Layer Boundaryを定義する。

Backend Architectureは、
そのApplicationを
Server-sideでどのように実装するかを定義する。

例えば、

Application Architecture：

Check In Use Case
↓
Check In Domain

Backend Architecture：

Controller
↓
Application Service
↓
Domain Service
↓
Repository
↓
Database

という関係になる。

---

# 106. Application Architecture and Frontend Architecture

Frontend Architectureは、
Client UIとUser Interactionを定義する。

Application Architectureは、
Clientから呼び出される
Business Operationを定義する。

例えばWeb Check Inでは、

Frontend：

Performance選択
↓
Reservation List
↓
Filter
↓
Check In Button

Application：

Reservation Resolution
↓
Check In Use Case
↓
Check In Domain

という分担になる。

Mobileでは、

Frontend：

Mobile Home
↓
Rehearsal
↓
Performance
↓
Reception Mode

Application：

Query
↓
Application Use Case
↓
Domain / Read Model

という分担になる。

---

# 107. Application Architecture and Integration Architecture

Applicationが
External Serviceを必要とする場合は、

Application
↓
Integration Interface
↓
Integration Layer
↓
External Service

というBoundaryを利用する。

External Serviceの障害によって、
Core Business Factを
不必要に失わせない。

---

# 108. Check In and Integration

Check InとExternal Integrationは、
Application上でも分離する。

基本構造：

Check In
↓
CheckInCompleted
↓
Integration / History / Accounting

External Serviceが失敗しても、
Check In Business Factそのものを
不必要にRollbackしない設計を検討する。

具体的なFailure Strategyは、
Integration Architectureで定義する。

---

# 109. Application Transaction Boundary

Application Use Caseは、
Business Operation単位で
Transaction Boundaryを持つ。

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

Organization Selection
↓
Context Operation

複数のBusiness Use Caseを、
不必要に一つの巨大Transactionへ
まとめない。

---

# 110. Application Background Processing

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

ただし、
Check InそのもののBusiness Fact確定を
Background Jobだけに依存しない。

---

# 111. Synchronous Business Operation

Receptionなど、
即時結果が必要なOperationは、
可能な限り同期的にResultを返す。

Check In：

Client
↓
Check In API
↓
Check In Use Case
↓
Check In
↓
Result

というFlowを基本とする。

---

# 112. Application Timeout

External Serviceや
長時間処理を伴うOperationでは、
Timeoutを考慮する。

長時間処理は、
必要に応じてBackground Processingへ
委譲する。

Check Inについては、
受付担当者がその場で結果を確認できるよう、
同期Operationとして扱うことを基本とする。

---

# 113. Application Availability

Reception時間帯では、
以下のApplication Operationの
Availabilityが重要となる。

- Reservation Query
- Ticket Query
- Check In
- Check In Result

特にCheck Inについては、
External Serviceの障害によって
Core Reception Operationが
不必要に停止しない構造を目指す。

Infrastructure / Backend側で
Availability Strategyを定義する。

---

# 114. Application Module Evolution

Application Moduleは、
将来的に必要に応じて
独立Serviceへ分離できる構造を目指す。

ただし初期段階では、
Modular Monolithを基本とする。

基本構造：

StageArt Application
├── Identity
├── Organization
├── Project
├── Production
├── Performance
├── Person
├── Participant
├── Ticket
├── Reservation
├── Check In
├── Rehearsal
├── History
├── Accounting
├── Communication
├── Document
└── Promotion

Module間のBoundaryを維持することで、
将来的な分離可能性を確保する。

---

# 115. Modular Monolith

初期Architectureでは、
Modular Monolithとして
StageArt Applicationを構築できる。

同一Application内に
複数Domain Moduleを配置するが、

- Business Ownership
- Repository Boundary
- Application Boundary
- Domain Boundary
- Event Boundary

を維持する。

「同じApplicationに存在する」
ことと、

「何でも直接参照できる」

ことは同義ではない。

---

# 116. Application Operation Naming

Application Use Caseは、
Business Operationを表す名前とする。

例えば、

CreateReservation

ConfirmReservation

CancelReservation

IssueTicket

CheckIn

ConfirmRehearsal

RecordAttendance

CreateJournalEntry

など。

単純な、

UpdateStatus

UpdateRecord

ProcessData

などのTechnical Operationを
Business Use Caseの中心にしない。

---

# 117. Check In Operation Naming

Check InのApplication Use Caseは、

CheckIn

をCanonical Operationとして扱う。

受付方法によって、

QRCheckIn

WebCheckIn

ManualCheckIn

などの別Business Ruleを
作らない。

必要な場合でも、

受付入口
↓
Reservation Resolution
↓
CheckIn

という構造を維持する。

---

# 118. Application Result

Application Use Caseは、
Domain Entityそのものではなく、
Application Resultを返す。

基本構造：

Domain Operation
↓
Application Result
↓
API Response DTO

Application Resultは、
Clientが必要とするBusiness Resultを
表現する。

---

# 119. Check In Result Mapping

Check In Use CaseのResultは、
API LayerでResponse DTOへMappingする。

例えば、

Check In Success
Already Checked In
Invalid Reservation
Invalid Ticket
Performance Mismatch
Forbidden

など。

Domain Exceptionや
Infrastructure Exceptionを
そのままClientへ公開しない。

---

# 120. Application API Independence

Application Use Caseは、
特定のClient UIに依存しない。

例えばCheck In Use Caseは、

Web Button

Mobile QR

Management Screen

のどれから呼ばれても、
同じBusiness Operationとして動作する。

Application Use Caseが、
BrowserやMobile Deviceを
直接操作しない。

---

# 121. Device Feature Boundary

Camera、
QR Scanner、
Notification、
Local Storageなどの
Device Featureは、
Client側の責務とする。

Applicationは、
Device Featureの具体Implementationを知らない。

例えば、

Camera
↓
QR Payload
↓
Application

であり、

Application
↓
Camera

という依存を作らない。

---

# 122. API Request to Application Command

API Requestは、
Application CommandへMappingする。

例えば、

Web Request
↓
CheckInRequestDTO
↓
CheckInCommand
↓
CheckInUseCase

Mobile Request
↓
CheckInRequestDTO
↓
CheckInCommand
↓
CheckInUseCase

とする。

Clientごとに、
異なるBusiness Commandを
原則として作らない。

---

# 123. Query Model

Queryは、
必要に応じてRead Modelへ
直接アクセスできる。

例えば、

Check In List
↓
CheckInReadModel

Reservation Search
↓
ReservationReadModel

Performance List
↓
PerformanceReadModel

Rehearsal List
↓
RehearsalReadModel

など。

Read Modelは、
Write Modelと分離できる。

---

# 124. Write Model

Business Operationの確定には、
Domain Modelを利用する。

例えば、

Check In
↓
Check In Domain
↓
Check In Fact

Reservation Confirmation
↓
Reservation Domain
↓
Reservation State

など。

Query用Read Modelを、
Business FactのWrite Sourceとして利用しない。

---

# 125. Application Consistency

Applicationは、
Business FactのConsistencyを
Server Sideで保証する。

Client側の、

- Cache
- Local State
- UI State
- Previous Response

を、
Business Factの正本としない。

---

# 126. Application Audit Context

重要なBusiness Operationでは、
必要に応じてAudit Contextを保持する。

例えば、

- Actor
- Organization
- Production
- Operation
- Resource
- Source
- Timestamp

など。

特にCheck Inでは、
Web / Mobile / System Administration Contextなどの
受付Sourceを記録できる。

---

# 127. Check In Audit

Check Inについて、
必要に応じて以下をAudit情報として扱う。

- Check In Actor
- Reservation
- Performance
- Source
- Client
- Timestamp
- Result

Audit情報は、
Check In Business Factそのものとは
分離して扱う。

---

# 128. Application Architecture Rules

Application Architectureでは、
以下を禁止または原則禁止とする。

- ClientからDatabaseへの直接アクセス
- ClientからDomainへの直接アクセス
- API ControllerへのBusiness Rule実装
- UIへのBusiness Rule実装
- DomainからDatabase SDKへの直接依存
- DomainからExternal Service SDKへの直接依存
- Module間のPersistence直接操作
- Scope外Dataの取得
- Client側だけでのCheck In確定
- QR Codeだけを根拠にしたCheck In確定
- Issued TicketだけをCheck In Business Factとして扱うこと
- WebとMobileで別々のCheck In Business Ruleを実装すること
- Receptionを独立Business Domainとして扱うこと
- System Administrator専用の重複したOrganization Management Use Caseを作ること
- Selected Organization Contextを無視したBusiness Data Access
- System OperationsをBusiness Domain Ruleへ混在させること

---

# 129. Application Architecture Summary

StageArt Application Architectureでは、

Client
↓
API
↓
Application
↓
Domain
↓
Infrastructure

というLayer Boundaryを維持する。

Web Client、
Mobile Client、
Public Client、
System Administrationなど、
Client / Entry Pointが異なっても、
Business RuleをClient側へ分散させない。

Mobile Clientは、
受付専用Applicationではない。

公演関係者が日常的に利用する
StageArt Mobile Applicationとして、

Normal Mode
↓
Rehearsal / Production / Performance / Schedule / Communication

と、

Reception Mode
↓
QR / Reservation Search / Check In

を同一Application内で提供する。

Receptionは、
独立したClientやBusiness Domainではなく、
Mobile ClientのOperational Modeとして扱う。

Check Inでは、

受付Input
↓
Reservation Resolution
↓
Check In Use Case
↓
Check In Domain
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
- Reservation Identifier

などを利用できる。

QR受付では、

QR Code
↓
Issued Ticket
↓
Reservation
↓
Check In

というResolutionを行う。

Reservation Number受付では、

Reservation Number
↓
Reservation
↓
Check In

となる。

Booker Name受付では、

Booker Name
↓
Candidate Reservations
↓
Reservation Selection
↓
Reservation
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
Reservation
↓
Check In

となる。

すべての受付方法において、
最終的なBusiness Operationは、

Check In

として共通化する。

Check InのCanonical Relationshipは、

Reservation
↓
Check In

である。

Issued Ticketは、
QRなどの受付経路で
Reservationを特定するために利用する。

したがって、

Issued Ticket
≠
Check In

である。

また、

QR Code
≠
Check In

である。

QR Codeは、
Issued Ticketなどを識別するための
Artifactとして扱う。

Check Inが確定すると、

Check In
↓
CheckInCompleted
├── Audience History
└── Accounting Process

という後続処理を実行できる。

Application Layerでは、

- Use Case
- Command
- Query
- Authorization Context
- Selected Organization Context
- Reservation Resolution
- Transaction Boundary
- Application Result
- Domain Event

を利用して、
Business OperationをOrchestrateする。

Domain Layerでは、
Business Ruleを保持する。

Infrastructure Layerでは、
Database、
File Storage、
External Serviceなどの
Technical Implementationを担当する。

System Administratorについては、

System Administrator
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

という構造を採用する。

System Administratorは、
全Organizationを選択できる。

ただし、
全OrganizationのBusiness Dataを
専用の横断Management UIで
直接操作する構造にはしない。

Organizationを選択した後は、
通常のManagement Clientを利用する。

そのため、

- Organization Management
- Production Management
- Rehearsal Management
- Performance Management
- Ticket Management
- Reservation Management
- Check In Management
- Accounting
- Communication
- Document Management

などのBusiness Management画面を、
System Administrator専用に
重複して作る必要はない。

System Administratorの特別なGlobal Scopeは、
主として、

- Organization Selector
- System-wide Operational Information

に利用する。

System Operationsについては、

- Backup
- Restore
- Replication
- Mirror
- Failover
- Recovery
- Monitoring
- Logging
- Deployment

などをBusiness Operationsとは分離する。

Business Operation：

Check In
Reservation
Ticket
Rehearsal
Accounting

System Operation：

Backup
Restore
Replication
Failover
Recovery
Deployment

という責務分離を維持する。

Application Architectureの最重要原則は、

「ClientやAPIをBusiness Ruleの実装場所にせず、
Application Use CaseをBusiness Operationの入口とし、
DomainをBusiness Ruleの正本とする」

ことである。

また、

「受付方法とBusiness Operationを分離し、
どの受付方法からでも共通のCheck In Use Caseへ到達できる」

ことを、
StageArtのCheck In Architectureにおける
重要な原則とする。

さらに、

「Mobile Clientは受付専用Applicationではなく、
公演関係者が日常的に利用するApplicationとし、
必要な場合だけReception Modeへ切り替える」

ことを、
StageArt Mobile Architectureの基本方針とする。

そして、

「System Administratorは全Organizationを選択できるが、
Organizationを選択した後は通常のManagement Clientを利用し、
そのOrganizationのOrganization Administrator相当のContextで
Business Operationを実行する」

ことを、
StageArt System Administrationの
Application Architecture上の基本方針とする。

---
