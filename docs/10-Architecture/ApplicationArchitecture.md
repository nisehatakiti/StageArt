# StageArt Blueprint

# 10 - Architecture
# Application Architecture

Version : 1.0

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

# 3. Dependency Direction

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

# 4. Presentation Layer

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
Application Use Caseを呼び出す。

---

# 5. Presentation and API

Presentation LayerとAPI Layerを分離する。

基本構造：

Presentation
↓
API Client
↓
API
↓
Application

Frontendが、
Application ServiceやDomain Entityへ
直接アクセスしない。

---

# 6. API Layer

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

# 7. API Controller

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

# 8. Application Layer

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

# 9. Use Case

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

# 10. Use Case Naming

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

# 11. Command

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

# 12. Query

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

# 13. Command / Query Separation

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

# 14. Domain Layer

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

# 15. Entity Operation

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

# 16. Value Object

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

# 17. Domain Service

複数EntityにまたがるBusiness Ruleで、
特定Entityへ責務を置くことが不自然な場合、
Domain Serviceを利用する。

Domain Serviceは、
Application WorkflowをOrchestrateするものではない。

Business Ruleそのものを扱う。

---

# 18. Application Service

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

# 19. Repository Interface

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

# 20. Repository Implementation

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

# 21. Infrastructure Layer

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

# 22. Integration Layer

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

# 23. Integration Interface

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

# 24. Transaction

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

# 25. Cross Domain Transaction

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

# 26. Check In Application Flow

Check Inの基本Application Flow：

Request
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

Check Inそのものは、
Ticket / ReservationのBusiness Ruleに従う。

HistoryやAccountingの処理は、
CheckInCompletedを起点として連携する。

---

# 27. CheckInCompleted Handling

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

# 28. Authorization in Application Layer

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

# 29. Organization Authorization

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

# 30. Production Authorization

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

# 31. Participant and Authorization

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

# 32. Query Architecture

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

# 33. Read Model

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

# 34. Domain Entity and DTO

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

# 35. Domain Entity and Database Model

Domain EntityとDatabase Modelを分離する。

Domain Entity：

Business Conceptを表現する。

Database Model：

Persistence Structureを表現する。

DatabaseのColumn構造を、
そのままDomain EntityのInterfaceとして
公開しない。

---

# 36. Domain Module Structure

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

# 37. Identity Module

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

# 38. Organization Module

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

# 39. Production Module

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

# 40. Rehearsal Module

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

# 41. Ticket Module

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

# 42. Accounting Module

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

# 43. Communication Module

Communication Moduleは、
連絡と配信を管理する。

主なConcept：

- Announcement
- Announcement Recipient
- Announcement Delivery

EmailなどのDelivery処理は、
Integration Layerと連携する。

---

# 44. Document Module

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

# 45. Promotion Module

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

# 46. Equipment Module

Equipment Moduleは、
団体が保有・管理する備品を管理する。

主なConcept：

- Equipment
- Equipment History

Equipmentは、
資産会計そのものを担当しない。

---

# 47. Regulation Module

Regulation Moduleは、
Organization規約を管理する。

主なConcept：

- Regulation
- Regulation Version

Versioning Ruleは、
Domain Layerで管理する。

---

# 48. Survey Module

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

# 49. Module Dependency

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

# 50. Domain Event as Module Boundary

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

# 51. Shared Kernel

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

# 52. Domain Isolation

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

# 53. Error Boundary

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

# 54. Validation Boundary

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

# 55. Logging Boundary

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

# 56. Audit Boundary

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

# 57. Background Processing

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

# 58. Idempotency

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

# 59. Application State

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

# 60. Configuration Boundary

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

# 61. Feature Flag

将来的に段階的なFeature Releaseが必要になった場合、
Feature Flagを利用できる。

Feature Flagは、
Business Ruleの代替ではない。

Feature Flagによって、
一時的にFeatureのAvailabilityを制御する。

---

# 62. Application Observability

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

# 63. Performance Principle

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

# 64. Modular Monolith

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

# 65. Why Modular Monolith

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

# 66. WordPress Plugin Structure

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

# 67. WordPress Adapter

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

# 68. Application Entry Point

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

# 69. Scheduled Process

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

# 70. Internal Application Process

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

# 71. Application Consistency

Applicationは、
同じBusiness Operationについて、
どのEntry Pointから呼ばれても
同じBusiness Ruleが適用される構造を維持する。

例えばCheck Inが、

- Management Portal
- API
- QR Reader
- Administrative Tool

のいずれから実行されても、
Check In Use Caseを経由する。

---

# 72. Testing Boundary

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

---

# 73. Testability

Application Layerは、
Infrastructureから独立してTestできる構造を目指す。

Repository InterfaceやIntegration Interfaceを
利用することで、
Test Doubleを利用できるようにする。

Domain Testでは、
External Serviceを必要としない。

---

# 74. Architecture Decision Rule

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

UI
→ Presentation

Database / External API
→ Infrastructure

External Business Service
→ Integration

---

# 75. Anti Pattern

以下の構造を避ける。

## Fat Controller

ControllerにBusiness Logicを集中させる。

## Fat Component

UI ComponentにBusiness Ruleを実装する。

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

## External Service as Source of Truth

Google CalendarやSNSなどを
StageArt Business Factの正本にする。

---

# 76. Business Rule Location

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

---

# 77. Application Boundary Summary

StageArt Applicationは、

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

Application Layerは、
Business OperationをOrchestrateする。

Domain Layerは、
Business RuleとBusiness Factを管理する。

Infrastructure Layerは、
技術的な実装を提供する。

Integration Layerは、
External Serviceとの境界を提供する。

---

# 78. Architecture Principle

StageArt Application Architectureの最重要原則：

「Business Ruleを、
利用者Interfaceや技術Infrastructureから
独立させる。」

そのため、

User
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

この構造を維持することで、
UI、
WordPress、
Database、
External Service、
Infrastructure Technology

が変更されても、
StageArtのBusiness Ruleを長期的に維持できる
Application Architectureを目指す。

--- 
