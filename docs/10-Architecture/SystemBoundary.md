# StageArt Blueprint

# 10 - Architecture
# System Boundary

Version : 1.0

---

# Purpose

System Boundaryは、
StageArtが責任を持つ範囲と、
StageArtの外部に存在するService・Platform・Clientとの境界を定義する。

System Boundaryでは、

- StageArtが管理するもの
- StageArtが利用するもの
- StageArtが外部へ提供するもの
- External Serviceに委譲するもの
- StageArt側で正本として保持するもの

を明確にする。

System Boundaryは、
Domain ModelとArchitectureの境界を接続するための
上位Architecture Documentである。

---

# 1. System Overview

StageArtは、
舞台芸術活動に必要な業務を管理するApplicationである。

StageArtは、
Organizationを中心とした業務管理機能と、
一般観客向けの公開・利用機能を提供する。

基本構造：

User
↓
Client
↓
StageArt Application
↓
Domain / Data
↓
External Integration

StageArt Applicationは、
Business RuleとBusiness Factを管理する。

External Serviceは、
StageArtが必要に応じて利用する外部機能である。

---

# 2. System Boundary

StageArtのSystem Boundaryは、
以下の領域で構成する。

## Inside StageArt

- Presentation
- API
- Application
- Domain
- Persistence
- Authorization
- Integration Interface
- StageArt Business Data

## Outside StageArt

- Browser
- WordPress Platform
- Authentication Provider
- External Storage
- Calendar Service
- Social Media
- Email Service
- その他External Service

ただし、
WordPressについては、
StageArtの提供環境として利用する場合がある。

その場合でも、
WordPress固有の機能とStageArt Domainを分離する。

---

# 3. High Level Boundary

基本的な構造：

Browser
↓
StageArt Presentation
↓
StageArt API
↓
StageArt Application
↓
StageArt Domain
↓
StageArt Persistence

StageArt Applicationは、
必要に応じてExternal Integrationを利用する。

StageArt Application
↓
Integration Interface
↓
Infrastructure Adapter
↓
External Service

External Serviceから取得した情報を、
必要な範囲でStageArt側のDomainまたはIntegration Referenceとして保持する。

---

# 4. Browser Boundary

Browserは、
StageArtのClientである。

Browser側の責務：

- UI表示
- User Interaction
- Form Input
- Client-side Validation
- API Request
- API Response表示
- Navigation
- Loading State
- Error State

Browserは、
Business Factの正本を保持しない。

Browser上に保持されるStateは、
UI操作のためのStateとして扱う。

Business Ruleは、
Server SideのApplication / Domainで実行する。

---

# 5. Presentation Boundary

Presentation Layerは、
BrowserなどのClientとStageArt Applicationの間に位置する。

Presentation Layerの責務：

- User Interface
- Screen Composition
- User Input
- Business Operationへの入口
- API Communication
- Permissionに応じたUI制御

Presentation Layerは、
Domain Entityをそのまま画面へ公開しない。

UIは、
利用者が理解しやすいBusiness Operationとして
Applicationを利用する。

---

# 6. API Boundary

APIは、
StageArt内部のApplicationと外部Clientとの境界である。

APIの責務：

- Request受付
- Authentication Contextの取得
- Authorization Contextの取得
- Request Validation
- Application Use Case呼び出し
- Response Mapping
- Error Mapping

APIは、
Domain Business Ruleを直接実装しない。

API Controllerは、
Application Layerへ処理を委譲する。

---

# 7. Application Boundary

Application Layerは、
外部からのBusiness Operationを受け取り、
Domainを利用して処理を実行する。

Application Layerの責務：

- Use Case
- Transaction Boundary
- Domain Objectの取得
- Domain Operationの実行
- 複数DomainにまたがるProcessのOrchestration
- Domain Eventの発行
- External Integrationの呼び出し

Application Layerは、
Business Ruleそのものの正本ではない。

Business Ruleは、
Domain Layerに保持する。

---

# 8. Domain Boundary

Domain Layerは、
StageArtのBusiness Concept、
Business Fact、
Business Ruleを管理する。

Domain Layerに含まれる主な領域：

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

Domain Layerは、
External ServiceやWordPress APIに直接依存しない。

---

# 9. Persistence Boundary

Persistenceは、
StageArtのBusiness Dataを永続化する。

Persistenceの責務：

- Database
- Query
- Transaction
- Repository Implementation
- Migration
- Index
- Constraint

Databaseは、
Domain Factを保存する。

Database自身を、
Business Ruleの正本として扱わない。

---

# 10. Database Boundary

Databaseは、
StageArtが管理するBusiness Dataを保存する。

主な対象：

- Person
- Organization
- Membership
- Project
- Production
- Participant
- ProductionDelegate
- Performance
- Ticket
- Reservation
- Issued Ticket
- Check In
- Rehearsal
- Accounting
- Communication
- Document Metadata
- Promotion Data
- Equipment
- Regulation
- Survey

具体的なTable構造は、
Data Architectureで定義する。

System Boundaryでは、
DatabaseがStageArt内部のPersistenceであることだけを定義する。

---

# 11. WordPress Boundary

StageArtがWordPress上で提供される場合、
WordPressはStageArtのHost / Platformとして利用する。

基本構造：

WordPress
↓
StageArt Plugin
↓
StageArt Application

WordPressは、
StageArt Domainそのものではない。

WordPress固有機能は、
Adapter / Infrastructure Layerを通して利用する。

---

# 12. WordPress Responsibilities

WordPressは、
必要に応じて以下のPlatform機能を提供する。

- Application Hosting
- HTTP Request Handling
- Plugin Lifecycle
- User / Session Infrastructure
- Database Infrastructure
- Media Infrastructure
- HTTP Client
- Scheduling
- Administrative Environment

StageArtは、
これらを必要な範囲で利用する。

WordPressのPlatform機能と、
StageArtのBusiness Ruleを混在させない。

---

# 13. WordPress User Boundary

WordPress UserとStageArt Personは、
同一概念として扱わない。

WordPress Userは、
WordPress Platform上のIdentityである。

StageArt Personは、
StageArt Business Domain上のIdentityである。

必要に応じて、

WordPress User
↓
StageArt UserAccount
↓
StageArt Person

というMappingを行う。

StageArtのBusiness Ruleは、
WordPress User IDそのものをBusiness Identityとして利用しない。

---

# 14. Authentication Boundary

Authenticationは、
StageArtが利用者のIdentityを確認するための境界である。

Authentication Providerを利用する場合、

Client
↓
Authentication Provider
↓
Authentication Result
↓
StageArt UserAccount
↓
Person

という関係を基本とする。

Authentication Providerは、
StageArt Business Domainの正本ではない。

StageArt側では、
UserAccountとExternal Identityの関係を管理する。

---

# 15. Authentication Provider Boundary

Authentication Providerは、
以下を担当する。

- Login
- Identity Verification
- OAuth
- Credential Verification
- Session / Token

StageArtは、
Provider固有の認証処理をDomain Layerへ持ち込まない。

Providerとの接続は、
Infrastructure / Adapter Layerで実装する。

---

# 16. Business Identity Boundary

Authenticationが完了した後、
StageArt内部ではPersonをBusiness Identityとして利用する。

基本構造：

UserAccount
↓
Person

Personは、
OrganizationやProductionなどのBusiness Domainで利用される。

Authorizationも、
Personを起点として評価する。

---

# 17. Authorization Boundary

Authorizationは、
StageArt Application内で実行する。

基本構造：

Person
↓
Scope
↓
Role
↓
Permission

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

Authorizationは、
Clientだけで判断しない。

Server Sideで必ず検証する。

---

# 18. Organization Boundary

Organizationは、
StageArtの主要なTenant Boundaryである。

Organizationに関連するBusiness Dataは、
原則としてOrganization Scopeに属する。

例：

- Membership
- Project
- Production
- Organization Documents
- Organization Communication
- Organization Accounting
- Organization Equipment
- Organization Regulation

Organization Scopeを越えたData Accessは、
明示的なAuthorizationが必要となる。

---

# 19. Production Boundary

Productionは、
Organization内部の具体的な公演・活動単位である。

Productionに関連する主なData：

- Participant
- ProductionDelegate
- Performance
- Rehearsal
- Ticket
- Reservation
- Check In
- Budget
- Production Actual
- Production Settlement
- Document
- Communication
- Survey
- Promotion

Production Scopeへのアクセスは、
Organization Scopeの権限またはProduction Scopeの権限によって制御する。

---

# 20. Public Boundary

StageArtには、
Organization内部のManagement領域と、
一般観客向けのPublic領域が存在する。

Public領域の例：

- Organization Public Profile
- Production Public Page
- Performance Information
- Ticket Information

Public Dataは、
Management Dataそのものを直接公開するのではなく、
公開用の情報として提供する。

---

# 21. Management Boundary

Management Portalは、
OrganizationおよびProductionの運営業務を行うための領域である。

主なOperation：

- Organization Management
- Member Management
- Production Management
- Participant Management
- Rehearsal Management
- Performance Management
- Ticket Management
- Reservation Management
- Check In
- Accounting
- Communication
- Document Management

各Operationは、
Authorizationによって利用可否を決定する。

---

# 22. Audience Boundary

Audience Portalは、
一般観客が自身の情報を確認・利用するための領域である。

対象情報：

- Reservation
- Issued Ticket
- Check In
- Audience History

Audienceは、
自分自身の情報だけを参照・操作できる。

他のAudienceの情報へアクセスできない。

---

# 23. External Storage Boundary

Document Domainは、
外部Storageと連携できる。

基本構造：

StageArt
↓
Document
↓
External Storage Reference
↓
Storage Adapter
↓
External Storage

External Storageは、
ファイルBinaryの保存先として利用できる。

StageArtは、
DocumentとBusiness Contextの関係を管理する。

External Storage側のFile IDだけを、
Business Factの正本として扱わない。

---

# 24. Calendar Boundary

Rehearsal、
Timetable、
その他Schedule情報は、
External Calendarと連携できる。

基本構造：

StageArt
↓
Calendar Integration
↓
Calendar Adapter
↓
External Calendar

External Calendarは、
StageArt ScheduleのExternal Representationとして扱う。

StageArtのRehearsalやTimetableを、
External CalendarのEventだけで管理しない。

StageArt側のDomain Factを正本とする。

---

# 25. Social Media Boundary

Promotion Domainは、
Social Mediaと連携できる。

基本構造：

StageArt
↓
Promotion
↓
Social Post
↓
Social Media Adapter
↓
External SNS

Social Media上のPostは、
StageArtのPromotion情報のExternal Representationである。

SNS側のPostを、
ProductionやOrganizationの正本として扱わない。

---

# 26. Email Boundary

Communication Domainは、
Email Serviceなどと連携できる。

基本構造：

StageArt
↓
Announcement
↓
Announcement Delivery
↓
Notification Adapter
↓
Email Service

Email Serviceは、
Message Deliveryを担当する。

Announcementそのものは、
StageArt側で管理する。

---

# 27. External Service Principle

External Serviceは、
StageArt Domainの外部に存在する。

External Serviceを利用する場合でも、

- Business Rule
- Business Fact
- Domain Identity
- Authorization

をExternal Serviceへ委譲しない。

External Serviceは、
必要な機能を提供するInfrastructureとして利用する。

---

# 28. External Data Principle

External Serviceから取得したDataは、
そのままStageArtのBusiness Factとはしない。

必要な場合は、

External Data
↓
Integration Mapping
↓
StageArt Domain Data

というMappingを行う。

External Service側のData Structureに、
StageArt Domain Modelを従属させない。

---

# 29. Integration Failure Boundary

External Serviceとの通信失敗は、
StageArt DomainのBusiness Ruleとは分離する。

例えば、

Google Driveが一時的に利用できない場合でも、
DocumentというBusiness Conceptそのものが存在できなくなる設計にはしない。

External Integration Failureは、
Infrastructure / Application側で処理する。

必要に応じて、

- Retry
- Queue
- Failure State
- Error Logging
- Manual Retry

などを利用する。

---

# 30. Source of Truth

StageArtでは、
Business FactごとにSource of Truthを定義する。

例：

Person
→ Person

Organization Membership
→ Membership

Production Participation
→ Participant

Production Authorization
→ ProductionDelegate

Reservation
→ Reservation

Issued Ticket
→ Issued Ticket

Attendance / Visit
→ Check In

Rehearsal Attendance
→ Rehearsal Attendance

Accounting Fact
→ Journal Entry

外部Serviceの情報を、
StageArt Business Factの正本としない。

---

# 31. Fact and Artifact Boundary

Business FactとArtifactを分離する。

Business Factの例：

- Participant
- Reservation
- Issued Ticket
- Check In
- Journal Entry
- Rehearsal Attendance

Artifactの例：

- QRTicket
- Public Page
- External Storage Reference
- External Calendar Event
- Social Media Post Reference

Artifactは、
Business Factを表現・配信・参照するためのものとして扱う。

Artifactが削除されても、
元となるBusiness Factが必要以上に失われない構造を目指す。

---

# 32. Event Boundary

Domain Eventは、
StageArt内部のDomain間連携に利用する。

例えば、

CheckInCompleted

を起点として、

CheckIn
↓
CheckInCompleted
├── History
└── Accounting

という連携を行う。

Domain Eventは、
必要に応じてExternal Integrationの起点にもできる。

ただし、
Event自体をBusiness Factの正本にはしない。

---

# 33. Transaction Boundary

一つのBusiness Operationで、
一貫性が必要な処理は、
Application Transactionとして扱う。

例：

Check In

Check InのBusiness Factを保存する処理は、
必要な範囲で一つのTransactionとして扱う。

External Serviceへの通信まで、
必ずしも同一Database Transactionに含めない。

External Serviceとの処理は、
必要に応じてEvent / Queue / Retryによって分離する。

---

# 34. Data Ownership

各Domainは、
自分が責任を持つBusiness Dataを明確にする。

あるDomainが、
別DomainのDataを直接書き換えない。

例えば、

HistoryがReservationを直接更新することはしない。

Check Inが発生した場合、

CheckIn
↓
CheckInCompleted
↓
History

というDomain Event / Application Processによって、
History側の処理を実行する。

---

# 35. Cross Domain Access

Domain間の情報参照は、
Application Layerを中心に調整する。

Domain Aが、
Domain BのInfrastructure Repositoryへ
直接アクセスする構造を基本としない。

複数DomainをまたぐBusiness Operationは、
Application LayerでOrchestrateする。

必要に応じて、
Domain Serviceを利用する。

---

# 36. Security Boundary

Security Boundaryは、
以下の領域に存在する。

- Authentication
- Authorization
- Tenant Isolation
- Production Isolation
- API
- File Access
- External Integration
- Public Access

Securityを、
UIだけの問題として扱わない。

Server Sideで、
すべての重要なAccessを検証する。

---

# 37. Secret Boundary

以下の情報は、
Domain Modelへ直接保存しない。

- Password
- API Key
- OAuth Secret
- Access Token
- Refresh Token
- External Service Credential

これらは、
Infrastructure / Secure Configurationで管理する。

必要に応じて、
Domain側にはExternal Connectionなどの
Business Connection情報だけを保持する。

---

# 38. File Access Boundary

Documentへのアクセスは、
Document自体の存在だけで許可しない。

以下を確認する。

- User Identity
- Organization Scope
- Production Scope
- Role
- Permission
- Document Share

外部StorageのFile URLを知っているだけで、
アクセスできる設計にしない。

---

# 39. Public Data Boundary

Public Dataは、
明示的に公開可能と定義された情報だけを対象とする。

Internal Dataを、
UI側で隠すだけではPublic Dataとはしない。

Public APIでは、
Public Domain / Public DTOを利用し、
内部Entityを直接公開しない。

---

# 40. Administrative Boundary

Organization Administratorなどの
管理権限を持つPersonであっても、
別OrganizationのDataへアクセスできない。

Administratorの権限は、
Scopeに限定される。

基本原則：

Administrator
≠
Global Administrator

StageArt全体を管理するSystem Administratorが必要な場合は、
Organization Administratorとは別のArchitectureとして定義する。

---

# 41. System Administration

StageArtのSystem Administrationと、
Organization Managementは分離する。

Organization Administrator：

自身のOrganizationを管理する。

Production Manager：

許可されたProductionを管理する。

System Administrator：

StageArt Application自体の運用を管理する。

System Administratorの詳細権限は、
通常のOrganization Roleとは別に定義する。

---

# 42. Operational Boundary

Applicationの運用に必要な機能は、
Business Domainから分離する。

例：

- Logging
- Monitoring
- Error Tracking
- Backup
- Deployment
- Configuration
- Queue Processing

これらは、
Operational Infrastructureとして扱う。

Business Domainに、
Operational Infrastructureの責務を持ち込まない。

---

# 43. Backup Boundary

Backupは、
StageArtのPersistenceを保護するためのInfrastructure機能である。

Backup対象：

- Database
- 必要なFile Metadata
- 必要なApplication Configuration
- 必要なStorage Data

Backupを、
Business DomainのFeatureとして扱わない。

---

# 44. Recovery Boundary

障害発生時には、
Business Factを復旧できることを優先する。

特に重要なData：

- Person
- Organization
- Membership
- Production
- Participant
- Performance
- Reservation
- Issued Ticket
- Check In
- Rehearsal
- Accounting

CacheやExternal Artifactは、
必要に応じて再構築できることを基本とする。

---

# 45. Scalability Boundary

StageArtのArchitectureは、
初期段階では過度な分散化を行わない。

基本構造は、
一つのApplicationとして構築できる。

必要になった場合に、

- Queue
- Worker
- Cache
- External Service Adapter
- 非同期処理

などを追加する。

初期段階からMicroservices化することを前提としない。

---

# 46. Modular Boundary

Application内部では、
Domain単位の責務を明確にする。

例えば、

Identity
Organization
Production
Rehearsal
Ticket
Reservation
Accounting
Communication
Document
Promotion

などを、
Logical Moduleとして分離できる構造を目指す。

Physical Deploymentは、
必要に応じて一つのApplicationでもよい。

---

# 47. WordPress as Platform

WordPressは、
StageArtのBusiness Domainを定義するものではない。

WordPressは、

- Hosting Platform
- HTTP Platform
- User Infrastructure
- Database Infrastructure
- Media Infrastructure
- Plugin Runtime

などを提供する。

StageArtは、
WordPress上で動作するApplicationとして
Business Domainを管理する。

---

# 48. Platform Independence

可能な限り、
Domain LayerをWordPressから独立させる。

将来的にPlatformが変更された場合でも、
Domain Ruleを再利用できる構造を目指す。

ただし、
初期実装ではWordPressをPlatformとして利用する。

---

# 49. System Boundary Summary

StageArtの境界をまとめると、

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
Persistence

という内部構造を持つ。

外部連携は、

Application
↓
Integration Interface
↓
Adapter
↓
External Service

という構造を持つ。

Authenticationは、

External Identity
↓
UserAccount
↓
Person

というMappingを行う。

Authorizationは、

Person
↓
Scope
↓
Role
↓
Permission

によって評価する。

---

# 50. Architecture Boundary Principle

StageArtのSystem Boundaryにおける
最重要原則：

「StageArtがBusiness Factを所有し、
外部Serviceは必要な機能を提供する。」

外部Serviceに依存しても、
StageArtのBusiness Modelを外部Serviceへ
従属させない。

WordPressを利用しても、
StageArt DomainをWordPress Domainへ
従属させない。

UIを変更しても、
Domain Ruleを変更しない。

External Serviceを変更しても、
Business Factを失わない。

この境界を維持することを、
StageArt Architectureの基本方針とする。

---
