# StageArt Blueprint

# 10 - Architecture
# System Boundary

Version : 1.2

---

# Purpose

System Boundaryは、
StageArtが責任を持つ範囲と、
StageArtの外部に存在するService・Platform・Client・Infrastructureとの境界を定義する。

System Boundaryでは、

- StageArtが管理するもの
- StageArtが利用するもの
- StageArtが外部へ提供するもの
- External Serviceに委譲するもの
- StageArt側で正本として保持するもの
- ClientとServerの境界
- Organization Scopeの境界
- System Administratorの境界
- 本番運用Infrastructureの境界
- Backup / Replication / Recoveryの境界

を明確にする。

System Boundaryは、
Domain ModelとArchitectureの境界を接続する
上位Architecture Documentである。

また、
StageArtを本番運用するために必要となる
System Administration、
Backup、
Replication、
RecoveryなどのOperational Boundaryも
System Boundaryの対象とする。

---

# 1. System Overview

StageArtは、
舞台芸術活動に必要な業務を管理するApplicationである。

StageArtは、

- Organizationを中心とした業務管理
- Production管理
- Rehearsal管理
- Performance管理
- Participant管理
- Ticket管理
- Reservation管理
- Check In
- Accounting
- Communication
- Document管理
- Promotion
- Audience向け公開・利用

などを提供する。

また、
公演関係者が日常的に利用する
Mobile Clientを提供する。

Mobile Clientは、
受付専用Applicationではない。

公演関係者は通常時からMobile Clientを利用し、

- 稽古情報の確認
- 公演情報の確認
- 自分の予定確認
- 連絡事項の確認

などを行う。

公演当日など必要な場合には、
同じMobile Clientを
Reception Modeへ切り替えて利用する。

Reception Modeでは、

- QR Code Scan
- Reservation Search
- Reservation Number Search
- Booker Name Search
- Manual Selection
- Check In
- Reception Status

などを利用できる。

基本構造：

User
↓
Client
↓
StageArt API
↓
StageArt Application
↓
StageArt Domain
↓
StageArt Persistence
↓
External Integration

StageArt Applicationは、
Business RuleとBusiness Factを管理する。

External Serviceは、
StageArtが必要に応じて利用する外部機能である。

本番運用では、
StageArt Applicationを支える
Operational Infrastructureも存在する。

Primary Environment
├── Application
├── Database
└── Storage
       │
       ├── Replication → Mirror Environment
       └── Backup → Backup Storage

---

# 2. System Boundary

StageArtのSystem Boundaryは、
以下の領域で構成する。

## Inside StageArt

- Web Client Application
- Mobile Client Application
- Presentation
- API
- Application
- Domain
- Persistence
- Authorization
- Integration Interface
- StageArt Business Data
- Organization Management
- Production Management
- Rehearsal Management
- Performance Management
- Ticket Management
- Reservation Management
- Check In
- Accounting
- Communication
- Document Management
- System Administration
- Operational Management

## Outside StageArt

- Browser Runtime
- Mobile Device Hardware
- Camera
- Authentication Provider
- External Storage
- Calendar Service
- Social Media
- Email Service
- その他External Service

## Hosting / Infrastructure

StageArtは、
以下のInfrastructure上で稼働できる。

- Application Server
- Database Server
- File Storage
- Backup Storage
- Mirror Server
- Monitoring Infrastructure

Infrastructureの具体的な構成は、
Operations Architecture / Backend Architectureで定義する。

---

# 3. High Level Boundary

基本的なWeb構造：

Browser
↓
StageArt Web Client
↓
StageArt API
↓
StageArt Application
↓
StageArt Domain
↓
StageArt Persistence

基本的なMobile構造：

Mobile Device
↓
StageArt Mobile Client
↓
StageArt API
↓
StageArt Application
↓
StageArt Domain
↓
StageArt Persistence

System Administratorの基本構造：

System Administrator
↓
System Administration
↓
Organization List
↓
Organization Selection
↓
Selected Organization Context
↓
通常のManagement Client
↓
StageArt API
↓
StageArt Application
↓
StageArt Domain

System Administratorは、
全OrganizationのBusiness Dataを
常時横断操作する専用Applicationを持つわけではない。

System Administratorに与えられる特別な権限は、
主として、

「全Organizationを一覧から選択できること」

である。

Organizationを選択した後は、
選択したOrganizationの
Organization Administrator相当のContextで
通常のManagement Clientを利用する。

StageArt Applicationは、
必要に応じてExternal Integrationを利用する。

StageArt Application
↓
Integration Interface
↓
Infrastructure Adapter
↓
External Service

本番運用では、

Primary
├── Replication → Mirror
└── Backup → Backup Storage

というOperational Boundaryを持つ。

---

# 4. Web Client Boundary

Web Clientは、
BrowserからStageArtを利用するためのClientである。

Web Clientの責務：

- UI表示
- User Interaction
- Form Input
- Client-side Validation
- API Request
- API Response表示
- Navigation
- Loading State
- Error State
- Management Operation
- Check In Operation

Web Clientは、
Business Factの正本を保持しない。

Business Ruleは、
Server SideのApplication / Domainで実行する。

---

# 5. Mobile Client Boundary

Mobile Clientは、
公演関係者が日常的にStageArtを利用するための
Mobile Applicationである。

Mobile Clientは、
受付専用Applicationではない。

公演関係者は、
通常時からMobile Clientを利用する。

Mobile Clientでは、
必要に応じて以下を確認できる。

- 今日の稽古
- 次回の稽古
- 自分の予定
- Production情報
- Performance情報
- 連絡事項
- その他Production関連情報

Mobile Clientは、
現場で必要な情報を
簡易的かつ迅速に確認できることを重視する。

---

# 6. Mobile Normal Mode

Mobile Clientの通常Modeでは、
公演関係者が日常的にStageArtを利用する。

基本構造：

Mobile Client

├─ Home
├─ Rehearsal
├─ Production
├─ Performance
├─ Schedule
├─ Communication
└─ その他

Normal Modeでは、
UserのRole / Scopeに応じて
利用可能な情報を表示する。

---

# 7. Mobile Rehearsal Boundary

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

# 8. Mobile Reception Mode

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

# 9. Reception Mode Activation

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

# 10. Reception Mode Boundary

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

# 11. QR Scanner Boundary

QR Scannerは、
Mobile ClientのDevice機能を利用する。

基本構造：

Mobile Camera
↓
QR Scanner
↓
QR Payload
↓
StageArt API
↓
Reservation Resolution
↓
Check In

CameraやQR Scannerの
具体的なDevice Implementationは、
Mobile Client側の責務である。

QR Code自体は、
StageArt Business Factではない。

---

# 12. Management Client Boundary

Management Clientは、
OrganizationおよびProductionを
運営するためのClientである。

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

通常Userは、
自分が所属するOrganization / Production Scopeの範囲で
Management Clientを利用する。

---

# 13. Organization Scope

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

通常Userは、
自身が所属するOrganization以外の
Business Dataを参照できない。

---

# 14. Production Scope

Productionは、
Organization内部の具体的な
公演・活動単位である。

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

Production ScopeへのAccessは、
Organization Scopeの権限または
Production Scopeの権限によって制御する。

---

# 15. Performance Boundary

Performanceは、
Production内の具体的な
上演・本番単位である。

Performanceには、
必要に応じて、

- Ticket
- Reservation
- Check In
- Reception

などが関連する。

Reception Modeは、
特定Performanceに対する
Operationとして扱う。

---

# 16. System Administrator Boundary

System Administratorは、
通常のOrganization Administratorとは
異なるRoleである。

System Administratorに必要なのは、
StageArt全体のBusiness Dataを
別画面で管理することではない。

主な役割は、

「全Organizationを確認し、
必要なOrganizationを選択して、
そのOrganizationの管理画面へ入る」

ことである。

基本構造：

System Administrator
↓
Organization List
↓
Organization Selection
↓
Selected Organization Context
↓
通常Management Client

System Administratorは、
Organizationを選択するための
Global System Scopeを持つ。

---

# 17. Organization Selector

System Administratorが利用する
System Administrationの主要画面は、
Organization Selectorとする。

基本画面：

System Administration

Organization List

├─ Organization A
├─ Organization B
├─ Organization C
└─ Organization D

System Administratorは、
一覧から対象Organizationを選択する。

Organization Listは、
System Administratorだけが利用できる。

通常Userには、
全Organizationの一覧を表示しない。

---

# 18. Selected Organization Context

System Administratorが
Organizationを選択すると、

Selected Organization Context

を生成する。

基本構造：

System Administrator
↓
Selected Organization
↓
Organization Administrator Context
↓
Management Client

Selected Organization Contextは、
そのOrganizationについて
通常のOrganization Administrator相当の
権限を持つContextとして扱う。

これにより、
System Administrator専用の
業務管理画面を別途作成する必要をなくす。

---

# 19. System Administrator and Organization Administrator

通常のOrganization Administrator：

Person
↓
Organization Membership
↓
Organization
↓
Organization Administrator Role
↓
Management Client

System Administrator：

Person
↓
System Administrator Role
↓
Organization Selector
↓
Selected Organization
↓
Organization Administrator Context
↓
Management Client

両者は、
Organization選択までの経路が異なる。

Organization選択後の
業務管理画面は、
原則として同じものを利用する。

---

# 20. System Administrator Scope

System Administratorは、
全Organizationを常時参照する
Global Business Scopeを持つわけではない。

System Administratorが持つGlobal Scopeは、
主としてOrganization Selectorへの
Accessを可能にするために利用する。

Organizationを選択した後は、
Selected Organization Contextを利用して
そのOrganizationのBusiness Dataへアクセスする。

これにより、

- Organization Scope
- Role
- Permission
- Management UI
- Application Use Case

を通常の設計から大きく逸脱させない。

---

# 21. System Administrator and Business Data

System Administratorが
Organizationを選択した後の
Business Data操作は、
通常のManagement Clientを利用する。

基本構造：

System Administrator
↓
Organization Selector
↓
Organization A
↓
Management Client
↓
Application
↓
Domain

Databaseへの直接操作は行わない。

System Administratorであっても、
Business Ruleを迂回して
Databaseを直接変更する構造にはしない。

---

# 22. System Administration Boundary

System Administrationは、
通常のOrganization Managementとは
別の入口として扱う。

System Administrationの中心は、

- Organization Selector
- System Health
- Operational Status
- Backup Status
- Replication Status
- Recovery Status

などのSystem-wideな
Administration / Operations情報である。

Organizationの業務情報そのものについては、
Organization Selectorから
通常Management Clientへ移動して確認する。

---

# 23. System Dashboard Boundary

System Administrationでは、
必要に応じてStageArt全体の
Operational Statusを確認できる。

Dashboardの対象例：

- Organization Count
- Production Count
- User Count
- Upcoming Performance
- System Status
- Database Status
- Storage Status
- Backup Status
- Mirror Status
- Recent Error
- Pending Job

Dashboardは、
System-wide Operational Viewとして扱う。

Organization固有の業務情報については、
対象Organizationを選択して
通常Management Clientで確認する。

---

# 24. System Health Boundary

System Administrationでは、
StageArt本番環境のHealthを確認できる。

対象例：

- Application
- API
- Database
- Storage
- Queue
- Background Job
- External Integration
- Primary Server
- Mirror Server

具体的なMonitoring方式は、
Operations Architectureで定義する。

---

# 25. Audit Boundary

StageArtでは、
重要なOperationについて
Audit情報を保持できる。

対象例：

- Authentication
- Authorization Change
- User Change
- Organization Change
- Production Change
- Data Deletion
- Backup
- Restore
- Configuration Change
- Recovery Operation

Audit Dataは、
通常のBusiness Dataとは
別のOperational Concernとして扱う。

---

# 26. Backup Boundary

StageArtは、
本番運用を前提として
Backupを行う。

Backup対象には、
必要に応じて、

- Database
- Business Data
- Document Metadata
- Uploaded Files
- Configuration
- Operational Data

などを含める。

Backupは、
Business Dataの正本とは別の
Recovery用Copyとして扱う。

---

# 27. Backup Storage Boundary

Backupは、
Primary Serverと同一障害領域だけに
保存する構造を避ける。

基本構造：

Primary Server
↓
Backup Process
↓
Backup Storage

Backup Storageは、
Primary Serverとは独立した
障害領域に配置できる。

External Backup Storageを利用する場合は、
Storage Adapterを通して利用する。

---

# 28. Backup and Business Fact

Backupは、
Business Factそのものではない。

基本構造：

Business Fact
↓
Database
↓
Backup

Backupから復元した場合でも、
復元後のDatabaseを
StageArt Business Dataとして利用する。

Backup File自体を、
Business Domainから直接参照しない。

---

# 29. Backup Retention Boundary

Backupでは、
必要に応じて複数世代を保持する。

例えば、

- Daily Backup
- Weekly Backup
- Monthly Backup

など。

具体的なRetention Policyは、
Operations Architectureで定義する。

---

# 30. Restore Boundary

Restoreは、
BackupからStageArt Dataを
復旧するOperationである。

基本構造：

Backup
↓
Restore Process
↓
Database / Storage
↓
Consistency Check
↓
StageArt Recovery

Restoreは、
通常のBusiness Operationとは異なる
System Administration Operationである。

---

# 31. Replication Boundary

本番運用では、
Primary Serverから
Mirror ServerへDataを
Replicationできる構造を想定する。

基本構造：

Primary
↓
Replication
↓
Mirror

Replicationは、
Backupとは異なる。

Replicationは、
障害発生時に代替環境を
利用できる状態を維持することを目的とする。

---

# 32. Mirror Server Boundary

Mirror Serverは、
Primary Serverの代替環境として
利用できる構造を持つ。

Mirrorには、
必要に応じて、

- Application
- Database
- Configuration
- Storage

などを同期する。

具体的なMirror方式は、
Infrastructure / Operations Architectureで定義する。

---

# 33. Mirror and Backup Separation

MirrorとBackupは、
目的を分離する。

Mirror：

可用性・Failover

Backup：

Data Recovery

基本構造：

Primary
├── Replication → Mirror
└── Backup → Backup Storage

Mirrorは、
過去の任意時点へ戻すための
Backupとは限らない。

Backupは、
Primaryの即時代替環境とは限らない。

両者を別の目的として設計する。

---

# 34. Failover Boundary

Primary Serverに障害が発生した場合、
必要に応じてMirrorへ
Failoverできる構造を検討する。

基本Flow：

Primary Failure
↓
System Health Detection
↓
Mirror Status Check
↓
Failover Decision
↓
Mirror Activation
↓
Service Recovery

具体的なFailover方式は、
Operations Architectureで定義する。

---

# 35. Disaster Recovery Boundary

StageArtは、
本番環境の重大障害に備えて
Disaster Recoveryを考慮する。

対象例：

- Server Failure
- Database Failure
- Storage Failure
- Application Failure
- Data Corruption
- Infrastructure Failure
- External Service Failure

Recovery方式は、
障害の種類によって異なる。

---

# 36. Recovery Point

Backup / Replicationでは、
Recovery Pointを考慮する。

例えば、

Primary Database
↓
Replication
↓
Mirror

または、

Database
↓
Periodic Backup
↓
Backup Storage

など。

どの時点までDataを復旧できるかを
Recovery Pointとして管理する。

具体的なRPO / RTOは、
Operations Architectureで定義する。

---

# 37. Operational Infrastructure Boundary

本番運用に必要なInfrastructureは、
StageArt Applicationとは
責務を分離する。

StageArt Application：

- Business Rule
- Business Fact
- Application Operation

Operational Infrastructure：

- Server
- Database Runtime
- Storage
- Backup
- Replication
- Monitoring
- Logging
- Deployment
- Recovery

Infrastructureの具体構成は、
Operations Architectureで定義する。

---

# 38. Primary Data Boundary

Primary Environmentは、
StageArt Business Dataの
通常運用上の正本を保持する。

基本構造：

Client
↓
Primary Application
↓
Primary Database
↓
Primary Storage

通常のBusiness Operationは、
Primary Environmentで実行する。

---

# 39. Mirror Data Boundary

Mirrorは、
Primary DataのReplication先として扱う。

Mirror Dataは、
Primary Dataの代替Copyである。

Mirror自体を、
通常運用時のBusiness Factの正本とはしない。

Failover時には、
運用上のPrimaryとして
切り替えることができる。

---

# 40. Storage Boundary

StageArtでは、
DatabaseとFile Storageを
別のPersistenceとして扱うことができる。

Database：

Business Data

File Storage：

Document / Media / Uploaded File

Backupでは、
必要に応じてDatabaseとFile Storageの
Consistencyを考慮する。

---

# 41. Configuration Boundary

Application Configurationは、
Business Dataとは分離する。

Configurationには、

- Environment Configuration
- External Service Configuration
- Operational Configuration
- Feature Configuration

などを含む。

SecretやCredentialは、
Source Codeや通常Databaseへ
直接保存しない。

---

# 42. External Storage Boundary

Document Domainは、
External Storageと連携できる。

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

# 43. Calendar Boundary

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
StageArt Scheduleの
External Representationとして扱う。

StageArt側のDomain Factを正本とする。

---

# 44. Social Media Boundary

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
StageArtのPromotion情報の
External Representationである。

SNS側のPostを、
ProductionやOrganizationの正本として扱わない。

---

# 45. Email Boundary

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

# 46. External Service Principle

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

# 47. External Data Principle

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

# 48. Integration Failure Boundary

External Serviceとの通信失敗は、
StageArt DomainのBusiness Ruleとは
分離する。

External Integration Failureは、
Infrastructure / Application側で処理する。

Core Business Factを、
External Serviceの一時障害だけで
不必要に失わせない。

---

# 49. Authentication Boundary

Authenticationは、
StageArtが利用者のIdentityを
確認するための境界である。

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

---

# 50. Business Identity Boundary

Authenticationが完了した後、
StageArt内部ではPersonを
Business Identityとして利用する。

基本構造：

UserAccount
↓
Person

Personは、
Organization、
Production、
Rehearsal、
Performance、
Reservationなどの
Business Domainで利用される。

---

# 51. Authorization Boundary

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

System Administrator：

Person
↓
System Administrator Role
↓
Organization Selector
↓
Selected Organization
↓
Organization Administrator Context
↓
Management Client

Authorizationは、
Clientだけで判断しない。

Server Sideで必ず検証する。

---

# 52. Scope Isolation

StageArtでは、
自身が所属するScope以外の
Business Dataを
通常Userが参照できない。

通常User：

Person
↓
Membership / Delegate
↓
Scope
↓
Authorized Data

System Administrator：

Person
↓
System Administrator Role
↓
Organization Selector
↓
Selected Organization
↓
Organization Administrator Context
↓
Authorized Data

System Administratorは、
Organization Selectorによって
対象Organizationを切り替えられる。

ただし、
選択されていないOrganizationの
Business Dataを、
通常のManagement Clientから
同時に横断表示することはしない。

---

# 53. Data Access Boundary

Clientは、
Databaseへ直接アクセスしない。

基本構造：

Client
↓
API
↓
Application
↓
Repository
↓
Persistence

Resource IDを知っているだけでは、
Scope外Dataへアクセスできない。

Scope制御は、
Server Sideで行う。

System Administratorについても、
Organization Selectorによって
Selected Organization Contextを生成し、
通常のApplication Authorizationを通して
Dataへアクセスする。

---

# 54. Business Fact Boundary

Business Factは、
StageArt Server Sideで確定する。

例えば、

- Reservation
- Ticket
- Check In
- Rehearsal Attendance
- Journal Entry

など。

Client側のLocal State、
QR Payload、
External Service Dataなどを
Business Factの正本としない。

---

# 55. Check In Boundary

Check Inは、
StageArtのBusiness Operationである。

Check Inの受付入口には、

- Mobile Reception Mode
- Web Management Client
- Reservation Number
- Booker Name
- Manual Selection
- QR Code

などが存在できる。

しかし、
Check In Business Ruleは、
ClientやReception Modeには存在しない。

基本構造：

Reception Input
↓
Reservation Resolution
↓
Check In Use Case
↓
Check In Domain
↓
Check In Business Fact

---

# 56. Check In and Mobile Boundary

Mobile Clientでは、

Mobile Client
↓
Reception Mode
↓
QR Scanner
↓
Check In API

という入口を提供できる。

ただし、
Check Inの確定は、

Check In API
↓
Application
↓
Domain

で行う。

Mobile Clientは、
Check In Business Factの
正本を保持しない。

---

# 57. Check In and Web Boundary

Web Clientでは、
Performanceに紐づく
Reservation / Issued Ticket一覧から
Check Inを実行できる。

基本構造：

Web Client
↓
Performance
↓
Reservation / Ticket List
↓
Reservation Selection
↓
Check In API
↓
Application
↓
Domain

Web Clientから
DatabaseのCheck In Statusを
直接変更しない。

---

# 58. Check In and Issued Ticket

Issued Ticketは、
Check Inそのものではない。

QR受付では、

QR Code
↓
Issued Ticket
↓
Reservation
↓
Check In

というResolutionを行う。

Reservation NumberやManual Selectionでは、
Issued Ticketを経由せずに
Reservationを特定できる。

したがって、

Issued Ticket
≠
Check In

である。

---

# 59. Domain Boundary

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

System Administrationは、
Domain Business Ruleそのものではなく、
System Operation / Administrationとして
Application / Operations側で扱う。

---

# 60. Persistence Boundary

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

# 61. Database Boundary

Databaseは、
StageArtが管理するBusiness Dataを保存する。

主な対象：

- Person
- UserAccount
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

---

# 62. Operational Data Boundary

System Administrationおよび
Operationsでは、
Business Dataとは別に
Operational Dataを扱う。

例：

- Audit Log
- Application Log
- Job Status
- Backup Status
- Replication Status
- System Health
- Recovery History

Operational Dataは、
Business Domain Dataとは
責務を分離する。

---

# 63. Operational Infrastructure Boundary

本番運用に必要なInfrastructureは、
StageArt Applicationとは
責務を分離する。

StageArt Application：

- Business Rule
- Business Fact
- Application Operation

Operational Infrastructure：

- Server
- Database Runtime
- Storage
- Backup
- Replication
- Monitoring
- Logging
- Deployment
- Recovery

Infrastructureの具体構成は、
Operations Architectureで定義する。

---

# 64. System Operations Boundary

System Operationsは、
Business Domainとは別の
Operational Concernとして扱う。

対象：

- Deployment
- Monitoring
- Backup
- Restore
- Replication
- Failover
- Recovery
- Maintenance
- Logging
- Alerting

具体的な運用手順は、
Operations Architectureで定義する。

---

# 65. System Operations and Business Operations

Business Operation：

- Create Reservation
- Confirm Reservation
- Check In
- Record Attendance
- Create Journal Entry

System Operation：

- Backup
- Restore
- Failover
- Replication
- Deployment
- Recovery

両者を同一のBusiness Operationとして
扱わない。

---

# 66. System Administration and Operations

System Administrationでは、
必要に応じてSystem Operationの
状態を確認できる。

例えば、

- Backup Status
- Replication Status
- Mirror Status
- System Health
- Job Status
- Recovery Point
- Recent Error

など。

破壊的なSystem Operationについては、
権限と確認手順を設ける。

具体的な操作Policyは、
Security / Operations Architectureで定義する。

---

# 67. Availability Boundary

StageArtは、
本番運用に必要なAvailabilityを考慮する。

特に、

- Authentication
- API
- Database
- Storage
- Check In
- Reservation Query

などは、
Performance / Reception時の
Availabilityが重要となる。

InfrastructureのAvailability設計は、
Operations Architectureで定義する。

---

# 68. Recovery Boundary

障害発生時には、
以下のRecovery Pathを利用できる構造とする。

通常障害：

Primary
↓
Recovery

Primary障害：

Primary
↓
Mirror
↓
Failover

Data Corruption：

Backup
↓
Restore
↓
Consistency Check
↓
Recovery

具体的なRecovery手順は、
Operations Architectureで定義する。

---

# 69. WordPress Boundary

StageArtがWordPress上で提供される場合、
WordPressはStageArtの
Host / Platformとして利用する。

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

# 70. WordPress Responsibilities

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

# 71. WordPress User Boundary

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
WordPress User IDそのものを
Business Identityとして利用しない。

---

# 72. Public Boundary

StageArtには、
Organization内部のManagement領域と、
一般観客向けのPublic領域が存在する。

Public領域の例：

- Organization Public Profile
- Production Public Page
- Performance Information
- Ticket Information

Public Dataは、
Management Dataそのものを
直接公開するのではなく、
公開用の情報として提供する。

---

# 73. Audience Boundary

Audience Portalは、
一般観客が自身の情報を
確認・利用するための領域である。

対象情報：

- Reservation
- Issued Ticket
- Check In
- Audience History

Audienceは、
自分自身の情報だけを
参照・操作できる。

他のAudienceの情報へ
アクセスできない。

---

# 74. External Storage Boundary

Document Domainは、
External Storageと連携できる。

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

# 75. Calendar Boundary

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
StageArt Scheduleの
External Representationとして扱う。

StageArt側のDomain Factを正本とする。

---

# 76. Social Media Boundary

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
StageArtのPromotion情報の
External Representationである。

SNS側のPostを、
ProductionやOrganizationの正本として扱わない。

---

# 77. Email Boundary

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

# 78. External Service Principle

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

# 79. External Data Principle

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

# 80. Integration Failure Boundary

External Serviceとの通信失敗は、
StageArt DomainのBusiness Ruleとは
分離する。

External Integration Failureは、
Infrastructure / Application側で処理する。

Core Business Factを、
External Serviceの一時障害だけで
不必要に失わせない。

---

# 81. Configuration Boundary

Application Configurationは、
Business Dataとは分離する。

Configurationには、

- Environment Configuration
- External Service Configuration
- Operational Configuration
- Feature Configuration

などを含む。

SecretやCredentialは、
Source Codeや通常Databaseへ
直接保存しない。

---

# 82. Data Access Principle

StageArtでは、
Clientが直接Persistenceへ
アクセスしない。

基本構造：

Client
↓
API
↓
Application
↓
Repository
↓
Persistence

Business Dataへのアクセスは、
ApplicationとAuthorizationを通過する。

System Administratorについても、
Organization Selectorを経由して
Selected Organization Contextを生成し、
通常のManagement Clientと同じ
Application Boundaryを利用する。

---

# 83. Client and Business Rule Separation

Clientは、
Business Ruleの正本ではない。

Web Client、
Mobile Client、
Reception Mode、
System Administrationなど、
入口が異なっても、
Business RuleはServer Sideで管理する。

特に、

- Reservation
- Ticket
- Check In
- Accounting
- Rehearsal

などのBusiness Factは、
Server Sideで確定する。

---

# 84. Mobile and Web Common Business Operation

Web ClientとMobile Clientは、
同じBusiness Operationについて
共通のApplication Use Caseを利用する。

例えばCheck Inでは、

Web Client
↓
Check In API
↓
Check In Use Case

Mobile Client
↓
Reception Mode
↓
Check In API
↓
Check In Use Case

とする。

Reception Modeを、
独立したBusiness Domainとして扱わない。

---

# 85. System Boundary Summary

StageArtは、

- Web Client
- Mobile Client
- Management Client
- System Administration
- API
- Application
- Domain
- Persistence
- Authorization
- Operational Infrastructure

を内部責任範囲として持つ。

Mobile Clientは、
受付専用Applicationではない。

公演関係者が日常的に利用する
StageArt Mobile Applicationであり、

Normal Mode
↓
Rehearsal / Production / Schedule / Communication

と、

Reception Mode
↓
QR / Reservation Search / Check In

を同一Application内で提供する。

System Administratorは、
StageArt全体のBusiness Dataを
別の専用Management UIで横断操作するのではない。

System Administratorは、

System Administration
↓
Organization List
↓
Organization Selection
↓
Selected Organization Context
↓
通常Management Client

というFlowを利用する。

Organizationを選択した後は、
そのOrganizationの
Organization Administrator相当の
権限Contextで、
通常のManagement Clientを利用する。

これにより、

- Organization Management
- Production Management
- Rehearsal Management
- Performance Management
- Ticket Management
- Reservation Management
- Check In
- Accounting
- Communication
- Document Management

などの業務画面を、
System Administrator専用に重複して作る必要がない。

System Administratorだけが
Global Scopeを利用できるのは、
主としてOrganization Selectorと
System-wide Operational Informationである。

通常Userは、
自身が所属するOrganization / Production Scopeの
Business Dataだけを参照・操作できる。

本番運用では、

Primary
├── Replication → Mirror
└── Backup → Backup Storage

という構造を基本方針とする。

Mirrorは、
可用性・Failoverを目的とする。

Backupは、
Data Recoveryを目的とする。

両者を同一目的として扱わない。

また、

Business Operation
≠
System Operation

とし、

Check In
Reservation
Ticket
Rehearsal
Accounting

などのBusiness Operationと、

Backup
Restore
Failover
Replication
Recovery
Deployment

などのSystem Operationを分離する。

StageArtのBusiness RuleとBusiness Factは、
StageArt Server Sideで管理する。

External Service、
Client、
Mirror、
Backup、
Cacheなどは、
Business Factの正本ではない。

System Boundaryの最重要原則は、

「StageArtが管理するBusinessと、
それを提供・運用するTechnology、
External Service、
Client、
Infrastructureの責任範囲を明確に分離する」

ことである。

また、

「公演関係者が日常的に利用するMobile Applicationの中にReception Modeを持たせ、
受付を独立Applicationとして分離しない」

ことをMobile Architectureの基本方針とする。

さらに、

「System Administratorは全Organizationを選択できるが、
Organizationを選択した後は通常のManagement Clientを利用し、
そのOrganizationのOrganization Administrator相当のContextで業務を確認・管理する」

ことをSystem Administrationの基本方針とする。

そして、

「本番運用ではBackup、Replication、Mirror、Recoveryを別々の責務として設計し、
障害時にもStageArt Business Dataを復旧可能な構造とする」

ことをOperational Boundary上の基本方針とする。

---
