# StageArt Blueprint

# 10 - Architecture

Version : 1.0

---

# Purpose

Architectureは、
StageArt Domain Modelを実際のソフトウェアとして実現するための
システム構造と責務分離を定義する。

Architectureは、
Domain Modelで定義されたBusiness Conceptを変更しない。

Domain Modelが、

「StageArtが何を管理するか」

を定義するのに対して、

Architectureは、

「StageArtをどのようなソフトウェア構造で実現するか」

を定義する。

---

# 1. Architecture Principles

StageArtのArchitectureは、
以下を基本原則とする。

- DomainをInfrastructureから独立させる。
- Business RuleをUIに置かない。
- Business RuleをControllerに置かない。
- 外部Serviceへの依存をDomainへ持ち込まない。
- AuthenticationとBusiness Identityを分離する。
- AuthorizationをPerson / Scope / Role / Permissionで評価する。
- Domain FactをSingle Source of Truthとする。
- ArtifactをDomain Factの代替にしない。
- Domain間の責務を明確に分離する。
- 外部IntegrationをAdapterとして分離する。
- UIはBusiness Operationを提供する。
- Domain ModelをそのままUI構造へ変換しない。
- Database SchemaをDomain Modelそのものとして扱わない。
- Infrastructureの変更によってDomain Ruleが変更されない構造を目指す。

---

# 2. System Boundary

StageArtは、
舞台芸術活動を管理するApplicationである。

StageArt自身が管理する主な領域：

- Identity
- Organization
- Project
- Production
- Participant
- Rehearsal
- Performance
- Ticket
- Reservation
- Check In
- History
- Accounting
- Communication
- Document
- Promotion
- Equipment
- Regulation
- Survey

StageArtは、
以下のExternal Serviceと連携できる。

- Authentication Provider
- External Storage
- Calendar Service
- Social Media
- Email Service
- その他External Service

External Serviceは、
StageArtのDomain Modelそのものではない。

External Serviceとの接続は、
Integration Layerで吸収する。

---

# 3. High Level Architecture

StageArtの基本構造：

UI
↓
API
↓
Application
↓
Domain
↓
Infrastructure
↓
External Service / Database / Storage

基本的な責務は以下の通り。

UI：

利用者とのInteractionを提供する。

API：

外部からのRequestを受け取り、
Applicationへ処理を委譲する。

Application：

Business Use Caseを実行する。

Domain：

Business Rule、
Entity、
Value Object、
Domain Eventなどを管理する。

Infrastructure：

Database、
External API、
File Storage、
Authentication Providerなどの
具体的な技術実装を担当する。

---

# 4. Layered Architecture

StageArtでは、
以下のLayerを基本とする。

Presentation
      ↓
Application
      ↓
Domain
      ↓
Infrastructure

ただし、
Domain LayerからInfrastructure Layerを
直接参照しない。

依存方向は、

Presentation
      ↓
Application
      ↓
Domain

を基本とする。

Infrastructureは、
Domain / Applicationが定義したInterfaceを
実装する。

---

# 5. Presentation Layer

Presentation Layerは、
利用者とのInteractionを担当する。

主な責務：

- 画面表示
- Form
- Input Validation
- User Interaction
- API Request
- API Responseの表示
- Loading State
- Error State
- Navigation

Presentation Layerに、
Domain Business Ruleを実装しない。

例えば、

「Check InしたらTicket Revenueを作成する」

というBusiness Ruleを、
UI Componentに実装してはならない。

UIはApplication Use Caseを呼び出す。

---

# 6. API Layer

API Layerは、
外部からのRequestを受け取り、
Application Layerへ処理を委譲する。

主な責務：

- Request Parsing
- Authentication Context取得
- Authorization Context取得
- Input Validation
- Application Use Case呼び出し
- Response Mapping
- Error Mapping

API Controllerは、
Business Logicを保持しない。

Controllerは、
Application Service / Use Caseを呼び出す。

---

# 7. Application Layer

Application Layerは、
利用者が行うBusiness Operationを
Use Caseとして実装する。

例：

- Create Organization
- Add Organization Member
- Create Production
- Add Participant
- Create Performance
- Create Ticket
- Create Reservation
- Check In Reservation
- Create Rehearsal Candidate
- Confirm Rehearsal
- Record Journal Entry
- Create Announcement

Application Layerは、
複数のDomainをまたぐBusiness Processを
Orchestrateする。

Application Layer自身が、
Domain Ruleの正本にならない。

Domain RuleはDomain Layerに置く。

---

# 8. Domain Layer

Domain Layerは、
StageArtのBusiness Ruleを管理する。

主な構成：

- Entity
- Value Object
- Domain Service
- Domain Event
- Repository Interface
- Business Rule

Domain Layerは、
FrameworkやExternal Serviceに依存しない。

Domain Entityは、
WordPress API、
Database API、
Google APIなどを直接呼び出さない。

---

# 9. Domain Entity

Domain Entityは、
Business Identityを持つ。

例：

- Person
- Organization
- Production
- Performance
- Reservation
- Ticket
- Rehearsal
- Budget
- Journal Entry

Entityの状態変更は、
Business Ruleに従って行う。

Database RowをそのままDomain Entityとして
扱うことを基本としない。

---

# 10. Value Object

Value Objectは、
Identityを持たないBusiness Valueを表す。

例：

- Money
- DateRange
- TimeRange
- EmailAddress
- PhoneNumber
- Address
- Percentage
- Quantity

Value Objectは、
不変Immutableとして扱うことを基本とする。

---

# 11. Domain Service

複数Entityにまたがり、
特定Entityに責務を置くことが不自然な
Business RuleはDomain Serviceで管理する。

Domain Serviceは、
Application Serviceとは異なる。

Domain Service：

Business Ruleを実行する。

Application Service：

Business OperationをOrchestrateする。

---

# 12. Repository

Repositoryは、
Domain Entityの永続化に関するInterfaceを定義する。

例：

- PersonRepository
- OrganizationRepository
- ProductionRepository
- PerformanceRepository
- ReservationRepository
- TicketRepository
- RehearsalRepository
- JournalEntryRepository

Domain Layerは、
Repository Interfaceだけを知る。

具体的なDatabaseアクセスは、
Infrastructure Layerが実装する。

---

# 13. Infrastructure Layer

Infrastructure Layerは、
具体的な技術実装を担当する。

主な責務：

- Database
- ORM / Query
- External API
- File Storage
- Authentication Provider
- Email
- Calendar
- Social Media
- Cache
- Queue
- Logging

Infrastructureは、
Domain Business Ruleを定義しない。

---

# 14. Database

Databaseは、
Domain Factを永続化する。

Database Schemaは、
Domain Modelを参考に設計する。

ただし、

Domain Model
=
Database Schema

ではない。

Databaseには、

- Primary Key
- Foreign Key
- Index
- Constraint
- Audit information

など、
Persistenceのための構造を持たせる。

---

# 15. Database as Persistence

Databaseは、
Business Logicを実行する場所ではない。

Business Ruleは、
Application / Domain Layerで実行する。

Database Constraintは、
Data Integrityを保証するために利用する。

例えば、

- NOT NULL
- UNIQUE
- Foreign Key
- Check Constraint

などを利用する。

ただし、
複雑なBusiness RuleをDatabase Triggerなどへ
過度に移さない。

---

# 16. Transaction Boundary

Application Use Caseを、
基本的なTransaction Boundaryとする。

例えば、

Check In Reservation

では、

Begin Transaction
    ↓
Load Reservation
    ↓
Validate Performance
    ↓
Check In
    ↓
Persist Check In
    ↓
Publish CheckInCompleted
Commit

という処理を基本とする。

複数Domainにまたがる処理では、
Transaction Boundaryを明確に定義する。

---

# 17. Domain Event

Domain Eventは、
Business Factが発生したことを表す。

例：

- ProductionCreated
- ParticipantAdded
- ReservationCreated
- TicketIssued
- CheckInCompleted
- RehearsalConfirmed
- JournalEntryPosted

Domain Eventは、
他DomainやApplication Processを起動するために利用できる。

ただし、
Domain EventそのものをBusiness Factの正本としない。

正本は、
該当DomainのEntity / Factである。

---

# 18. CheckInCompleted

CheckInCompletedは、
StageArtにおける重要なDomain Eventである。

基本Flow：

Reservation
      ↓
Check In
      ↓
CheckInCompleted
      ├──────────────┐
      ↓              ↓
History          Accounting
      ↓              ↓
Audience        Ticket Revenue
History             ↓
                  Journal Entry

Check Inを実行した時点で、
観劇実績が確定する。

CheckInCompletedを契機として、

- Audience History
- Ticket Revenue

をそれぞれのDomainへ連携する。

---

# 19. Event Handling

Domain Eventを受け取る処理は、
Event Handler / Application Processとして実装する。

例えば、

CheckInCompleted

を受け取って、

History Handler
    ↓
Audience History

Accounting Handler
    ↓
Ticket Revenue
    ↓
Journal Entry

を実行する。

Event Handlerは、
元のDomainの責務を奪わない。

---

# 20. Authentication Architecture

AuthenticationとAuthorizationを分離する。

Authentication：

「誰なのか」

Authorization：

「何をしてよいのか」

を判定する。

Authentication Contextは、
UserAccountを識別する。

Business Authorizationは、
Personを起点として評価する。

---

# 21. Authorization Architecture

Authorizationは、

Person
  ↓
Scope
  ↓
Role
  ↓
Permission

で評価する。

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

PrimaryManagerは、
Production Scopeにおける全権限を持つ。

Participant Typeは、
Authorizationを決定しない。

CAST / STAFFなどの参加区分と、
Role / Permissionを分離する。

---

# 22. Authorization Evaluation

Authorizationは、
Application Use Caseの入口で評価する。

基本Flow：

Request
  ↓
Authentication
  ↓
Person
  ↓
Scope Resolution
  ↓
Role Resolution
  ↓
Permission Check
  ↓
Application Use Case

Permission Checkを、
UIだけで実行してはならない。

UIはUX上の制御を行うが、
Security BoundaryとしてのAuthorizationは、
Server Sideで必ず実行する。

---

# 23. Organization Scope

Organization Scopeでは、
Membershipを利用する。

例：

Person
  ↓
Membership
  ↓
Organization

Organization AdministratorなどのRoleは、
Membershipを通じて適用する。

Organization ScopeのPermissionは、
そのOrganization内だけで有効となる。

---

# 24. Production Scope

Production Scopeでは、
ProductionDelegateを利用する。

例：

Person
  ↓
ProductionDelegate
  ↓
Production

ProductionDelegateに、
Roleを適用する。

ProductionDelegateによって、
Production単位の管理権限を付与できる。

---

# 25. Primary Manager

PrimaryManagerは、
Productionの全管理権限を持つ。

Application Layerでは、
PrimaryManagerをProduction Scopeの
Full Accessとして評価できる。

PrimaryManagerとProductionDelegateは、
別の概念として保持する。

ProductionDelegateは、
限定されたRoleによる権限付与を表す。

---

# 26. External Integration

External Serviceとの連携は、
Integration Adapterとして分離する。

基本構造：

Application
      ↓
Integration Interface
      ↓
Infrastructure Adapter
      ↓
External Service

例：

StageArt
   ↓
Google Drive Adapter
   ↓
Google Drive

Domain Layerは、
Google Drive APIなどを直接呼び出さない。

---

# 27. Authentication Provider Integration

Authentication Providerとの接続は、
Infrastructure Layerで管理する。

Domain Layerは、
Provider固有のToken、
API Request、
OAuth Flowなどを管理しない。

External Identityを通じて、
ProviderとのIdentity Mappingを管理する。

---

# 28. External Storage

Document Domainは、
外部Storageとの連携をサポートする。

基本構造：

Domain
  ↓
Document
  ↓
External Storage Reference
  ↓
External Storage Adapter
  ↓
External Storage

実ファイルをStageArt Databaseへ
直接保存することを必須としない。

External Storageを利用する場合でも、
StageArt側でDocumentとの関連情報を管理する。

---

# 29. Calendar Integration

RehearsalやTimetableなどの情報は、
将来的にCalendar Serviceと連携できる。

基本構造：

StageArt
   ↓
Calendar Integration
   ↓
Calendar Adapter
   ↓
External Calendar

External CalendarのEvent IDなどは、
必要に応じてIntegration Referenceとして管理する。

External Calendarを、
StageArtのDomain Factの正本としない。

---

# 30. Social Media Integration

Social PostなどのPromotion Domainは、
Social Mediaと連携できる。

基本構造：

Promotion
   ↓
Social Post
   ↓
Social Media Adapter
   ↓
External SNS

SNS側のPost IDなどは、
Social Post Referenceとして管理する。

SNSをStageArtのProduction情報の正本としない。

---

# 31. Email / Notification

AnnouncementなどのCommunication Domainは、
EmailなどのNotification Serviceと連携できる。

基本構造：

Announcement
   ↓
Announcement Delivery
   ↓
Notification Adapter
   ↓
Email Service

送信履歴は、
Announcement Deliveryとして管理する。

外部Email Serviceの送信結果を、
StageArtのBusiness Factとして必要な範囲で記録する。

---

# 32. Public Access

一般観客向けの公開情報と、
Organization内部情報を分離する。

一般観客は、
内部Management Portalへアクセスしない。

一般公開情報は、

- Organization Public Profile
- Production Public Page
- Performance Public Information
- Ticket Information

など、
Public Domainとして提供する。

---

# 33. Audience User

一般観客は、
必ずしもOrganization Memberではない。

Ticket購入のためにUserAccountを作成した場合、
Personと紐付けることができる。

購入者は、
自身のReservationや観劇履歴を確認できる。

観劇履歴は、
CheckInCompletedを起点として生成される。

---

# 34. Audience History Architecture

観劇履歴は、
Reservationそのものを履歴として表示するのではなく、
実際のCheck In Factを起点として管理する。

基本Flow：

Person
  ↓
Reservation
  ↓
Check In
  ↓
CheckInCompleted
  ↓
History
  ↓
Audience History

これにより、
「チケットを購入した」
と
「実際に観劇した」
を区別できる。

---

# 35. Accounting Architecture

Accountingは、
ProductionやOrganizationの会計Factを管理する。

基本構造：

Business Event
      ↓
Accounting
      ↓
Journal Entry
      ↓
Journal Entry Line

CheckInCompletedから、
Ticket Revenueを生成する。

CheckInCompleted
      ↓
Ticket Revenue
      ↓
Journal Entry

Accounting Domainは、
具体的な勘定科目・Debit / Creditを管理する。

---

# 36. Production Accounting

Production単位の収支は、
Accounting DomainとProduction Domainを
連携して管理する。

基本構造：

Production
   ├── Budget
   ├── Production Actual
   └── Production Settlement

Budgetは計画。

Production Actualは実績。

Production Settlementは、
Production単位の最終収支を表す。

---

# 37. File Architecture

Documentは、
Business DomainとFile Storageを分離する。

基本構造：

Domain
  ↓
Document
  ↓
Storage Reference
  ↓
Storage Adapter
  ↓
Storage

Databaseに、
巨大なBinary Dataを直接保存することを基本としない。

File MetadataとBusiness Relationshipを
StageArt側で管理する。

---

# 38. Caching

Cacheは、
Performance改善を目的として利用できる。

Cacheを、
Business Factの正本としない。

Cacheが消失しても、
Domain Factから再構築できることを基本とする。

---

# 39. Asynchronous Processing

外部Service連携、
通知、
大量処理など、
同期処理が適さない処理は
非同期処理へ分離できる。

例：

Domain Event
    ↓
Queue
    ↓
Worker
    ↓
External Service

非同期処理を導入した場合でも、
Business FactのTransaction Boundaryを明確にする。

---

# 40. Error Handling

Errorは、
Layerごとに責務を分ける。

Domain：

Business Rule違反を表す。

Application：

Use Case実行上のErrorを扱う。

API：

Application Errorを
HTTP ResponseなどへMappingする。

Presentation：

User向けError Messageへ変換する。

Infrastructure：

External ServiceやDatabaseの技術的Errorを扱う。

内部Exceptionを、
そのままUserへ表示しない。

---

# 41. Validation

Validationは、
複数Layerで目的を分ける。

Presentation：

入力しやすさのためのValidation。

API：

Request FormatのValidation。

Application：

Use Case入力のValidation。

Domain：

Business RuleのValidation。

Domain Validationを、
UI Validationだけで代替しない。

---

# 42. Logging

Applicationは、
重要な処理についてLoggingを行う。

Logging対象の例：

- Authentication
- Authorization Failure
- Business Operation
- External Integration
- System Error
- Background Job

Loggingには、
不要な個人情報や秘密情報を記録しない。

---

# 43. Audit

重要なBusiness Factについて、
必要に応じてAudit情報を保持する。

Auditは、

- 誰が
- いつ
- 何を
- どのScopeで

変更したかを追跡するために利用する。

Audit LogとDomain Factを混同しない。

---

# 44. Security

Securityは、
Architecture全体に適用する。

基本原則：

- Authenticationを必須とする処理を明確にする。
- AuthorizationをServer Sideで実行する。
- Scopeを必ず確認する。
- User Inputを信頼しない。
- External Tokenを安全に管理する。
- PasswordやSecretをLoggingしない。
- 不要な個人情報を保存しない。
- Public DataとInternal Dataを分離する。
- File AccessをAuthorizationする。
- API EndpointごとにPermissionを評価する。

---

# 45. Tenant Isolation

Organizationは、
StageArtのTenant境界となる。

Organization ScopeのDataは、
別Organizationから参照できないことを基本とする。

Application Layerで、
Organization Scopeを必ず解決する。

Database Queryでも、
Tenant Boundaryを考慮する。

IDを知っているだけで、
別OrganizationのDataへアクセスできる設計にしない。

---

# 46. Production Isolation

Production Scopeについても、
権限境界を明確にする。

ProductionDelegateが存在するPersonは、
許可されたProductionだけを操作できる。

Production IDを指定しただけで、
アクセスを許可しない。

必ずAuthorizationを通して、
Production Scopeを検証する。

---

# 47. API Design Principles

APIは、
Business Operationを中心に設計する。

単純なCRUDを、
そのままAPI設計の基本としない。

例えば、

POST /reservations/{id}/check-in

は、

「ReservationのCheck In」

というBusiness Operationを表す。

単純に、

PATCH /reservations/{id}

でCheck In状態を書き換えるだけの設計にしない。

Business Ruleが必要な操作は、
Use CaseとしてAPIへ公開する。

---

# 48. API and Domain Boundary

API Request ModelとDomain Entityを
同一モデルとして扱わない。

基本構造：

API Request
   ↓
Command / DTO
   ↓
Application Use Case
   ↓
Domain Entity

Responseも、

Domain Entity
   ↓
Application Result
   ↓
Response DTO

としてMappingする。

---

# 49. Frontend Architecture

Frontendは、
Business Operationを中心に構成する。

Domain Modelを、
そのまま画面構造へ変換しない。

例えば、

Production画面では、

- 公演情報
- キャスト
- スタッフ
- 稽古
- 公演回
- チケット
- 予約
- 会計

など、
利用者の目的に応じて情報をまとめて表示する。

---

# 50. Management Portal

Management Portalは、
Organization内部の業務を管理するためのUIである。

主な利用者：

- Organization Administrator
- Production Manager
- Rehearsal Manager
- Accounting Manager
- Participant Manager
- Reservation Manager
- その他Roleを持つPerson

Permissionによって、
利用可能なOperationを制御する。

---

# 51. Public Portal

Public Portalは、
一般観客向けの公開情報を提供する。

主な情報：

- Organization Public Profile
- Production Public Page
- Performance
- Ticket
- 公演情報

Management Portalとは、
Authorization Contextを分離する。

---

# 52. Audience Portal

Audience Portalは、
Ticket購入者などの一般利用者向け機能を提供する。

一般観客は、
OrganizationのManagement Portalへ入らない。

UserAccountを作成した観客は、
自分自身の情報を管理できる。

例：

- Reservation
- Issued Ticket
- Check In Result
- Audience History

他人の情報は参照できない。

---

# 53. Deployment

Deployment Architectureは、
Environmentを分離する。

基本Environment：

- Development
- Test
- Staging
- Production

各Environmentで、

- Database
- Storage
- External Service Credentials
- Configuration

を分離する。

---

# 54. Configuration

環境固有の設定を、
Source Codeへ直接埋め込まない。

例：

- Database Connection
- API Key
- OAuth Client
- Storage Configuration
- Email Configuration

など。

Secretは、
安全なSecret StorageまたはEnvironment Configurationで管理する。

---

# 55. Testing Architecture

Testingは、
Layerごとに責務を分ける。

Domain Test：

Business Ruleを検証する。

Application Test：

Use Caseを検証する。

Integration Test：

Database / External Serviceとの連携を検証する。

API Test：

API Contractを検証する。

E2E Test：

実際のUser Operationを検証する。

---

# 56. Domain Test

Domain Testは、
Infrastructureから独立して実行できることを基本とする。

例えば、

- Reservation Check In
- Ticket Price
- Rehearsal Confirmation
- Budget Calculation
- Authorization Rule

など。

External Serviceを利用せずに、
Business Ruleを検証する。

---

# 57. Implementation Independence

Domain Modelは、
特定Frameworkに依存しない。

Architectureは、
Frameworkを利用しても、
Domain RuleをFrameworkへ埋め込まない。

Frameworkの変更があっても、
Domain Layerを大きく変更せずに済む構造を目指す。

---

# 58. WordPress Integration

StageArtがWordPress上で提供される場合、
WordPressはApplicationのHost / Platformとして利用する。

WordPress固有APIは、
Infrastructure / Adapter Layerへ閉じ込める。

Domain Layerから、

- WordPress Database API
- WordPress User API
- WordPress HTTP API
- WordPress Media API

などを直接利用しない。

---

# 59. WordPress Plugin Boundary

StageArtのApplicationは、
WordPress Pluginとして実装できる。

基本構造：

WordPress
    ↓
StageArt Plugin
    │
    ├── Presentation / Admin UI
    ├── API
    ├── Application
    ├── Domain
    └── Infrastructure

WordPressは、
StageArt Domainそのものではない。

WordPress固有機能との接続は、
Adapterによって吸収する。

---

# 60. Implementation Order

Architecture確定後の実装は、
以下の順序を基本とする。

1. Project Skeleton
2. Configuration
3. Database Infrastructure
4. Domain Core
5. Authentication
6. Authorization
7. Organization
8. Person / Profile
9. Project / Production
10. Participant
11. Performance
12. Ticket
13. Reservation
14. Check In
15. History
16. Rehearsal
17. Accounting
18. Communication
19. Document
20. Promotion
21. Equipment
22. Regulation
23. Survey
24. Public Portal
25. Audience Portal
26. Management Portal
27. External Integrations
28. Testing
29. Deployment

実際の実装順序は、
Implementation Specificationで確定する。

---

# 61. Architecture and Domain Model

Architectureは、
Domain Modelを変更しない。

Domain Model：

Business Concept
Business Fact
Business Rule

Architecture：

Software Structure
Application Boundary
Persistence
Integration
Security
Deployment

という責務分離を基本とする。

Domain Modelに存在しないBusiness Conceptを、
Architecture上の都合だけで追加しない。

必要な追加概念がある場合は、
Domain Modelへ戻って検討する。

---

# 62. Architecture and Implementation Specification

Architectureは、
実装詳細をすべて定義しない。

Architectureで定義するもの：

- Layer
- Responsibility
- Dependency
- Boundary
- Integration
- Security
- Persistence Strategy
- Deployment Strategy

Implementation Specificationで定義するもの：

- Repository構造
- Class
- Method
- API Endpoint
- DTO
- Database Table
- Migration
- Component
- File Structure
- Test Case
- Coding Convention

ArchitectureとImplementation Specificationの責務を分離する。

---

# 63. Claude Implementation Principle

ClaudeなどのAI Coding Agentに実装させる場合も、
Architectureを唯一の技術設計基準として扱う。

Claudeが、

- Domain Ruleを勝手に変更する
- Domain EntityをUI都合で変更する
- InfrastructureをDomainへ持ち込む
- AuthorizationをUIだけで実装する
- External ServiceをDomainから直接呼び出す
- Database SchemaをBusiness Ruleの代替にする

ことを避ける。

実装中にArchitectureとの矛盾が発生した場合、
勝手に実装を進めず、
Architecture / Domain Modelへ戻って確認する。

---

# 64. Architecture Change Principle

Architecture変更は、
局所的な実装変更として扱わない。

Architectureを変更する場合は、

1. Domain Modelへの影響を確認する。
2. Business Flowへの影響を確認する。
3. APIへの影響を確認する。
4. Data Architectureへの影響を確認する。
5. External Integrationへの影響を確認する。
6. Securityへの影響を確認する。
7. Implementation Specificationを更新する。

という手順を基本とする。

---

# 65. Source of Truth

StageArtでは、
設計文書ごとに責務を分ける。

Vision：

StageArtが何を目指すか。

Business Flow：

利用者が何を行うか。

Domain Model：

StageArtが何を管理するか。

Architecture：

StageArtをどのようなソフトウェア構造で実現するか。

Implementation Specification：

Architectureをどのようにコードへ落とすか。

各Documentの責務を越えて、
別Documentの内容を重複定義しない。

---

# 66. Architecture Principle

StageArt Architectureの最重要原則：

User
 ↓
Business Operation
 ↓
Application
 ↓
Domain
 ↓
Fact

UIは、
利用者がBusiness Operationを実行するための入口。

Applicationは、
Business OperationをOrchestrateする。

Domainは、
Business RuleとFactを管理する。

Infrastructureは、
DatabaseやExternal Serviceなどの
技術的詳細を提供する。

この責務分離を維持することで、
StageArtはBusiness Ruleを長期的に維持できる
Software Architectureを目指す。

---
