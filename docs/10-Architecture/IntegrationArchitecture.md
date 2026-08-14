# StageArt Blueprint

# 10 - Architecture
# Integration Architecture

Version : 1.1

---

# Purpose

Integration Architectureは、
StageArtとExternal System / External Serviceとの
Integration Boundaryを定義する。

Integration Architectureでは、

- External System
- External Service
- Integration Interface
- Adapter
- External API
- Webhook
- Event
- Queue
- Background Process
- Authentication Provider
- Email
- Calendar
- File Storage
- Payment
- Accounting
- Notification
- Messaging
- WordPress
- External Ticketing
- Social Media
- Backup Storage
- Mirror Environment
- Monitoring
- Error Handling
- Retry
- Idempotency
- Synchronization
- Recovery

を定義する。

Integration Architectureでは、
具体的なProviderやProductの採用を
必要以上に固定しない。

---

# 1. Integration Architecture Principles

StageArt Integrationは、
以下を基本原則とする。

- External SystemをDomainへ直接依存させない。
- External APIをBusiness Ruleの正本にしない。
- IntegrationはInfrastructure Boundaryとして扱う。
- External Serviceとの接続はAdapterを介する。
- ApplicationはIntegration Interfaceを利用する。
- DomainはExternal APIを直接呼び出さない。
- External ServiceのData ModelをDomain Modelへ直接持ち込まない。
- External Service FailureがBusiness Factを不正に変更しない構造とする。
- Retry可能なIntegrationとRetry不能なIntegrationを区別する。
- 必要に応じてQueue / Background Processを利用する。
- Integration OperationではIdempotencyを考慮する。
- Webhookを利用する場合でも、受信Dataを無条件に信頼しない。
- External Systemとの同期状態とStageArtのBusiness Factを区別する。
- Integrationの成功 / 失敗をMonitoringできるようにする。
- SecretやCredentialをDomain / Clientへ公開しない。
- External Serviceを変更してもStageArt Core Business Logicへの影響を最小化する。
- External Serviceの障害によってCore Business Factを不必要に失敗させない。
- External ServiceのStateをStageArt Business Stateと同一視しない。
- External IdentifierをStageArt Business Identifierと同一視しない。
- Integration RetryによってBusiness Factを重複生成しない。
- Integrationの非同期処理と同期処理を明確に区別する。
- Integration DataとBusiness Dataを分離する。
- Integration LogとAudit Logを必要に応じて分離する。
- System OperationsとBusiness Operationsを混在させない。

---

# 2. Integration Boundary

基本構造：

StageArt
↓
Application
↓
Integration Interface
↓
Adapter
↓
External Service

External ServiceからStageArtへ
情報が入る場合：

External Service
↓
Webhook / API
↓
Integration Adapter
↓
Validation
↓
Application
↓
Domain

Integration Boundaryによって、
StageArt CoreとExternal Systemを分離する。

---

# 3. Integration Layer

Integrationは、
BackendのInfrastructure側に配置する。

基本構造：

API
↓
Application
↓
Domain
↓
Repository / Integration Interface
↓
Infrastructure
├── Database Adapter
├── External API Adapter
├── File Storage Adapter
├── Mail Adapter
├── Authentication Adapter
├── Calendar Adapter
├── Messaging Adapter
├── Payment Adapter
└── Accounting Adapter

External Service固有の実装は、
Adapter側へ閉じ込める。

---

# 4. Integration Interface

ApplicationからExternal Serviceを利用する場合、
Integration Interfaceを利用する。

例えば、

AuthenticationService
EmailService
NotificationService
CalendarService
FileStorage
PaymentService
AccountingService
MessagingService
TicketingService
SocialMediaService

など。

Applicationは、
具体的なProviderを直接知らない。

---

# 5. Adapter

Adapterは、
StageArtのIntegration Interfaceと
External Service APIの差異を吸収する。

基本構造：

Application
↓
Integration Interface
↓
Provider Adapter
↓
External API

Adapterでは、

- Request Mapping
- Response Mapping
- Authentication
- Error Mapping
- Retry
- Idempotency
- Provider Specific Logic
- External Identifier Mapping

などを扱う。

---

# 6. External Data Model

External ServiceのData Modelを、
そのままStageArt Domain Entityとして利用しない。

基本構造：

External Data
↓
Adapter
↓
Integration DTO
↓
Application
↓
Domain Data

External ServiceのField追加や変更が、
Domain Modelへ直接波及しない構造を目指す。

---

# 7. External Identifier

External Serviceが持つIdentifierは、
StageArtのBusiness Identityとは分離する。

例えば、

External User ID
External Ticket ID
External File ID
External Calendar Event ID
External Payment ID
External Message ID

など。

必要に応じて、

StageArt Entity
↓
External Reference
↓
External Identifier

というMappingを保持する。

---

# 8. External System Categories

StageArtでは、
以下のExternal SystemとのIntegrationが
発生する可能性がある。

- Authentication Provider
- WordPress
- Email Service
- Calendar Service
- File Storage
- Payment Service
- Accounting System
- Messaging Service
- Notification Service
- Social Media
- External Ticketing System
- Reporting System
- Backup Storage
- Mirror Environment
- Monitoring Service

実際に採用するServiceは、
Implementation Specificationで決定する。

---

# 9. Authentication Integration

Authentication Providerを
External Serviceとして利用できる。

基本構造：

Client
↓
Authentication Provider
↓
User Identity
↓
StageArt UserAccount
↓
Person

Authentication Provider固有のIdentityを、
StageArt Personと直接同一視しない。

Authentication Identityと
Business Identityを分離する。

---

# 10. Authentication Adapter

Authentication Providerを利用する場合、

Authentication Provider
↓
Authentication Adapter
↓
UserAccount
↓
Person

というMappingを行う。

Provider変更によって、
Domain Modelが変更されない構造を基本とする。

---

# 11. Authentication Failure

Authentication Providerが停止した場合でも、
既存SessionやStageArt Business Dataを
不正に変更しない。

Authentication Failureと
Business Operation Failureを分離する。

---

# 12. WordPress Integration

WordPressをStageArtの
Infrastructureとして利用できる。

例えば、

- Web Hosting
- Authentication
- REST API
- Plugin Runtime
- File Storage
- Database
- Public Web

など。

ただし、

WordPress Concept
≠
StageArt Domain Concept

とする。

---

# 13. WordPress API Integration

WordPress REST APIを利用する場合、

StageArt Application
↓
WordPress Adapter
↓
WordPress REST API

という構造とする。

Application / Domainから、
WordPress REST APIへ直接アクセスしない。

---

# 14. WordPress Plugin Integration

StageArtをWordPress Pluginとして
実装する場合でも、

WordPress
↓
Infrastructure

とする。

基本構造：

WordPress
↓
StageArt API Adapter
↓
Application
↓
Domain

WordPress Hookや
WordPress Objectを、
Domainへ直接渡さない。

---

# 15. WordPress User Integration

WordPress Userを
Authentication Infrastructureとして
利用できる。

基本構造：

WordPress User
↓
UserAccount
↓
Person

WordPress User IDと
Person IDを同一のBusiness Identityとして
扱わない。

---

# 16. WordPress Database Boundary

WordPress Databaseを
StageArt Persistenceとして利用する場合でも、

WordPress Database Schema
≠
StageArt Domain Model

とする。

WordPress固有のTable、
Option、
Meta、
Post Structureなどを、
Domain Logicから直接利用しない。

---

# 17. Email Integration

Email Serviceは、
Notification / Communicationの
External Integrationとして扱う。

基本構造：

Application
↓
EmailService
↓
Email Adapter
↓
External Mail Service

Domainが、
SMTPやProvider APIを直接操作しない。

---

# 18. Email Delivery

Email送信は、
必要に応じて非同期処理とする。

例えば、

Business Event
↓
Notification Process
↓
Queue
↓
Email Worker
↓
Email Service

など。

Business OperationのCritical Pathを
Email Serviceに不必要に依存させない。

---

# 19. Email Failure

Email送信に失敗しても、
Business Factを不正にRollbackしない。

例えば、

Reservation Confirmed
↓
Email Failed

の場合、

Reservation
→ Confirmed

Email
→ Pending / Retry / Failed

と分離できる構造を基本とする。

---

# 20. Notification Integration

Notificationは、
Business EventをTriggerとして
生成できる。

例えば、

ReservationConfirmed
CheckInCompleted
RehearsalChanged
PerformanceChanged
AnnouncementPublished

など。

基本構造：

Domain Event
↓
Notification Process
↓
Notification Adapter
↓
External Service

---

# 21. Messaging Integration

将来的に、

- LINE
- SMS
- Push Notification
- Chat Service
- Other Messaging Service

などを利用する可能性がある。

これらは、
Messaging Integrationとして扱う。

Applicationから、
具体的なMessaging Providerへ
直接依存しない。

---

# 22. LINE Integration

LINEなどのMessaging Serviceを利用する場合、

StageArt
↓
Messaging Interface
↓
LINE Adapter
↓
LINE API

という構造を基本とする。

LINE固有のUser IDなどを、
Person IDと直接同一視しない。

---

# 23. Messaging Identity

External Messaging Identityは、
StageArt Person Identityと分離する。

例えば、

Person
↓
Messaging Account
↓
External Messaging User ID

というMappingを利用する。

Provider変更によって、
Person Domainを変更しない。

---

# 24. Push Notification

Mobile Push Notificationを利用する場合、

StageArt
↓
Notification Interface
↓
Push Adapter
↓
Push Provider
↓
Mobile Device

という構造を利用する。

Push NotificationのDelivery Stateと、
StageArt Business Factを分離する。

Push失敗によって、
RehearsalやPerformanceなどの
Business Dataを変更しない。

---

# 25. Calendar Integration

Calendar ServiceとのIntegrationでは、

StageArt Schedule
↓
Calendar Adapter
↓
External Calendar API

という構造を利用する。

対象例：

- Rehearsal
- Performance
- Meeting
- Schedule Change

など。

---

# 26. Calendar Synchronization

Calendar Integrationでは、
StageArt Scheduleと
External Calendar Eventを
Mappingする。

基本的には、

StageArt Schedule
↓
External Calendar Event

というProjectionを作る。

External Calendarを、
StageArt Scheduleの正本としない。

---

# 27. Calendar External Changes

External Calendar側で
変更が行われる場合、
Webhookなどによって
StageArtへ通知できる。

ただし、

External Calendar Change
↓
StageArt Business Fact

と無条件に同期しない。

必要なValidation、
Authorization、
Conflict Resolutionを行う。

---

# 28. Calendar Conflict

StageArt Scheduleと
External Calendar Scheduleに
矛盾が発生した場合、
どちらを正本とするかを
明確にする。

基本原則：

StageArt Business Schedule
→ StageArtが正本

External Calendar
→ External Representation

とする。

---

# 29. File Storage Integration

Document / Fileは、
External Storageへ保存できる。

基本構造：

Application
↓
FileStorage Interface
↓
Storage Adapter
↓
External Storage

例えば、

- Object Storage
- Cloud Storage
- WordPress Media
- Local Storage

など。

---

# 30. File Storage Boundary

Domainでは、
FileそのもののStorage Detailを
扱わない。

Domain：

Document
File Reference

Infrastructure：

Storage Provider

という責務分離を行う。

---

# 31. File Upload

File Uploadでは、

Client
↓
API
↓
Application
↓
FileStorage Interface
↓
Storage Adapter
↓
Storage

という構造を基本とする。

ClientからExternal Storageへ
直接Uploadする方式を採用する場合でも、
AuthorizationとUpload Policyを
StageArt側で管理する。

---

# 32. File Download

File Downloadでは、
DocumentへのAuthorizationを確認する。

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
File

External Storage URLを、
無条件にClientへ公開しない。

---

# 33. File Storage Failure

File StorageへのUpload / Downloadに
失敗した場合でも、
既存Business Factを不正に変更しない。

Document Metadataと
File BinaryのConsistencyを
考慮する。

---

# 34. File Storage Reference

StageArtでは、

Document
↓
External Storage Reference
↓
External File

という関係を持てる。

External StorageのFile IDは、
StageArt Document Identityそのものではない。

---

# 35. Payment Integration

将来的にPayment Serviceを
利用する場合でも、

Payment Provider
≠
StageArt Payment Domain

とする。

基本構造：

StageArt
↓
Payment Interface
↓
Payment Adapter
↓
Payment Provider

Payment Providerの内部Data Modelを、
StageArt Domainへ直接持ち込まない。

---

# 36. Payment Webhook

Payment Providerから
Webhookを受信する場合、

Payment Provider
↓
Webhook
↓
Integration Adapter
↓
Signature Validation
↓
Application
↓
Payment Domain

という構造とする。

Webhook Payloadを、
無条件にBusiness Factとして登録しない。

---

# 37. Payment Idempotency

Payment Integrationでは、
RetryやWebhook再送を考慮する。

例えば、

Payment Provider
↓
Webhook
↓
StageArt

が複数回送信されても、
同じPayment Factを
重複作成しない。

External Transaction IDなどを利用して、
Idempotencyを確保する。

---

# 38. Payment and Reservation

PaymentがReservationと関連する場合でも、

Payment
≠
Reservation

とする。

Payment成功によって
Reservation Stateが変更される場合は、
Application Processを通して
Domain Ruleを適用する。

---

# 39. Accounting Integration

Accounting Systemと連携する場合、

StageArt
↓
Accounting Interface
↓
Accounting Adapter
↓
External Accounting System

という構造を基本とする。

StageArt Accounting Domainと
External Accounting Systemの
Data Modelを分離する。

---

# 40. Accounting Integration Source

Accounting Integrationでは、
StageArt側のAccounting Dataを
基本的なSourceとする。

例えば、

Journal Entry
↓
Accounting Adapter
↓
External Accounting System

とする。

External Accounting Systemの
内部Dataを、
StageArt Journal Entryの正本としない。

---

# 41. Check In and Accounting Integration

Check Inが成功した場合、

Check In
↓
CheckInCompleted
↓
Accounting Process
↓
Ticket Revenue
↓
Journal Entry
↓
External Accounting Integration

という流れを利用できる。

External Accounting Systemの
応答によって、
Check In Factを直接変更しない。

---

# 42. Accounting Failure

External Accounting Systemが
停止している場合でも、
Check Inそのものを
不要に失敗させない構造を基本とする。

例えば、

Check In
→ Completed

Accounting
→ Pending

External Accounting
→ Retry

など。

---

# 43. Accounting Retry

Accounting IntegrationのRetryでは、
同じJournal Entryを
重複送信しない。

必要に応じて、

- Journal Entry ID
- External Reference
- Idempotency Key
- Delivery Status

などを利用する。

---

# 44. Ticketing Integration

External Ticketing Systemを
利用する場合、

StageArt
↓
Ticketing Interface
↓
Ticketing Adapter
↓
External Ticketing System

とする。

External Ticket IDを、
StageArt Issued Ticket IDと
直接同一視しない。

---

# 45. External Ticketing and Reservation

External Ticketing Systemから
Ticket Informationを取得する場合でも、

External Ticket
↓
Adapter
↓
StageArt Ticket Context
↓
Reservation Resolution

という構造とする。

External Ticketing Systemが、
StageArt Reservation / Check In Business Ruleそのものを
所有する構造にはしない。

---

# 46. External Ticketing and Check In

External Ticketing SystemのTicketを
受付に利用する場合でも、

External Ticket
↓
External Reference
↓
Issued Ticket / Reservation Resolution
↓
Check In Use Case
↓
Check In

という構造を基本とする。

External Ticketそのものを
Check In Factとして扱わない。

---

# 47. QR and External Ticketing

External Ticketing Systemから
QR Ticketが提供される場合でも、
QR CodeをそのままCheck In Factとしない。

基本構造：

External QR
↓
External Ticket Identifier
↓
Ticketing Adapter
↓
Issued Ticket / Reservation Resolution
↓
Check In

StageArt側で必要なBusiness Ruleを
Server Sideで検証する。

---

# 48. External Ticketing Webhook

External Ticketing Systemから
Webhookを受信する場合、

Webhook
↓
Signature Validation
↓
Adapter
↓
Application
↓
Domain

という構造とする。

Webhook Eventを
無条件にTrustしない。

---

# 49. Social Media Integration

SNSへInformationをPublishする場合、

StageArt
↓
Social Media Interface
↓
Provider Adapter
↓
Social Media API

という構造を利用する。

SNS Provider固有のData Modelを、
Domainへ持ち込まない。

---

# 50. Social Media Failure

SNS Publishに失敗しても、
ProductionやPerformanceなどの
Business FactをRollbackしない。

例えば、

Announcement Published
↓
Social Media Publish Failed

の場合、

Announcement
→ Published

Social Post
→ Pending / Failed / Retry

と分離できる構造とする。

---

# 51. Reporting Integration

External Reporting Systemへ
Dataを提供する場合、

StageArt Business Fact
↓
Reporting Projection
↓
Export / API
↓
External Reporting System

という構造を利用する。

External Reporting Systemを、
Business Factの正本としない。

---

# 52. Data Export Integration

External SystemへDataをExportする場合、
Scopeを維持する。

例えば、

Organization Export
↓
Organization Scope

Production Export
↓
Production Scope

など。

Export処理では、
Authorizationを確認する。

---

# 53. Data Import Integration

External SystemからDataをImportする場合、

External Data
↓
Import Adapter
↓
Validation
↓
Mapping
↓
Application Operation
↓
Business Fact

という構造を利用する。

External Dataを、
そのままDatabaseへInsertしない。

---

# 54. Import Validation

Importでは、

- Schema Validation
- Identifier Validation
- Scope Validation
- Business Rule Validation
- Duplicate Detection
- Reference Validation

などを行う。

Invalid Dataは、
Business Factとして確定しない。

---

# 55. Webhook Boundary

Webhookは、
External ServiceからStageArtへ
Dataを送信するIntegration Boundaryである。

基本構造：

External Service
↓
Webhook Endpoint
↓
Authentication / Signature Validation
↓
Integration Adapter
↓
Application
↓
Domain

Webhook Endpointから、
Domainを直接呼び出さない。

---

# 56. Webhook Security

Webhookでは、
必要に応じて、

- Signature Validation
- Secret Validation
- Timestamp Validation
- Replay Protection
- Source Verification

などを利用する。

Webhook Payloadを、
無条件に信頼しない。

---

# 57. Webhook Idempotency

Webhookは、
同じEventが複数回送信される可能性を考慮する。

例えば、

External Event ID
↓
Processed Event Record
↓
Duplicate Detection

など。

同じEventを、
複数回Business Factへ反映しない。

---

# 58. Webhook Processing

Webhook処理は、
必要に応じて非同期化できる。

基本構造：

Webhook
↓
Validate
↓
Persist Integration Event
↓
Queue
↓
Integration Worker
↓
Application
↓
Domain

Webhook Requestそのものを
長時間処理しない構造を検討する。

---

# 59. Integration Event

Integration Eventは、
External Systemとの
同期状態を管理するために利用できる。

Integration Eventは、
Domain Eventとは異なる。

Domain Event：

StageArt Business Factの発生。

Integration Event：

External Systemとの通信・同期のためのEvent。

両者を混同しない。

---

# 60. Domain Event and Integration Event

例えば、

Check In
↓
CheckInCompleted
↓
Integration Process
↓
External Notification

という場合、

CheckInCompleted
→ Domain Event

External Notification Request
→ Integration Process

として扱う。

External Notificationの成功・失敗は、
Check In Business Factそのものを変更しない。

---

# 61. Queue

Integrationでは、
必要に応じてQueueを利用する。

対象例：

- Email
- Notification
- Calendar Sync
- Accounting Export
- Social Media Publish
- External Ticket Sync
- Large Data Export
- Import Processing

Queueを利用する場合、
Jobの状態を管理できる構造とする。

---

# 62. Background Worker

Background Workerは、
Queueに積まれたIntegration Jobを
処理する。

基本構造：

Queue
↓
Worker
↓
Integration Adapter
↓
External Service

Workerでは、

- Retry
- Timeout
- Error Handling
- Idempotency
- Logging

などを扱う。

---

# 63. Integration Job

Integration Jobには、
必要に応じて、

- Job ID
- Integration Type
- Business Reference
- External Reference
- Status
- Attempt Count
- Created At
- Updated At
- Last Error
- Next Retry Time

などを保持する。

Integration Jobは、
Business Factそのものではない。

---

# 64. Integration Status

Integrationには、
必要に応じてStatusを持たせる。

例えば、

- Pending
- Processing
- Succeeded
- Failed
- RetryScheduled
- Cancelled

など。

Integration Statusと
Business Entity Stateを
同一視しない。

---

# 65. Retry

Retry可能なIntegrationについては、
Retry Policyを定義する。

例えば、

Temporary Network Error
→ Retry

Timeout
→ Retry

Rate Limit
→ Backoff Retry

Authentication Failure
→ Manual Intervention

Validation Error
→ Retryしない

など。

具体的なPolicyは、
Implementation Specificationで定義する。

---

# 66. Exponential Backoff

外部Serviceが一時的に
利用できない場合は、
必要に応じてExponential Backoffを利用する。

基本的な考え方：

Failure
↓
Wait
↓
Retry
↓
Longer Wait
↓
Retry

無制限Retryは行わない。

---

# 67. Retry Limit

Retryには、
最大試行回数を設定できる。

Retry Limit到達後は、

Failed
↓
Dead Letter / Manual Retry

などの状態へ移行できる。

具体的な運用方法は、
Operations Architectureで定義する。

---

# 68. Idempotency

Integration Operationでは、
Idempotencyを考慮する。

対象例：

- Payment
- Accounting Export
- Ticket Synchronization
- Email Delivery
- Notification
- Calendar Synchronization
- Webhook Processing
- Data Import

同じRequest / Eventを
複数回処理しても、
Business Factを不必要に重複生成しない。

---

# 69. External Reference Mapping

External Systemとの連携では、

StageArt Entity
↓
External Reference
↓
External Entity

というMappingを保持できる。

External Referenceには、
必要に応じて、

- Provider
- External ID
- External Type
- Status
- Last Synced At

などを保持する。

---

# 70. Synchronization

Synchronizationでは、

StageArt
↓
External System

または、

External System
↓
StageArt

のData Flowを明確にする。

どちらがSource of Truthかを、
Integrationごとに定義する。

---

# 71. StageArt as Source of Truth

Core Business Dataについては、
原則としてStageArtをSource of Truthとする。

例えば、

- Organization
- Project
- Production
- Performance
- Reservation
- Check In
- Rehearsal
- Participant
- Accounting Data

など。

External Systemは、
これらのExternal Representationとして扱う。

---

# 72. External System as Source of Truth

一部のInfrastructure Dataについては、
External SystemがSource of Truthとなる場合がある。

例えば、

Authentication Credential
External Storage Binary
Payment Provider Transaction
External Calendar Event ID

など。

ただし、
External Source of Truthを
StageArt Domain全体へ拡張しない。

---

# 73. Check In Integration Principle

Check Inについては、
StageArt Server Sideを
Business FactのSource of Truthとする。

例えば、

Mobile QR
↓
External / QR Artifact
↓
StageArt Reservation Resolution
↓
StageArt Check In

という構造。

External Ticketing Systemや
QR ProviderのStateを、
StageArt Check In Factの正本としない。

---

# 74. Check In and External Ticketing Failure

External Ticketing Systemを
受付時に参照する場合、
外部Service障害が発生する可能性がある。

この場合、

External Ticket Validation
→ Failed

だからといって、
既存のStageArt Check In Factを
削除・Rollbackしない。

具体的な受付可否Ruleは、
Ticket / Check In Domainで定義する。

---

# 75. Web Check In Integration

Web Check Inでは、
External Integrationを
必須にしない。

基本構造：

Web Client
↓
Reservation / Issued Ticket
↓
StageArt API
↓
Check In

External Ticketing Systemが存在する場合でも、
StageArt Check In Use Caseの
外部依存を必要以上に増やさない。

---

# 76. Mobile QR Check In Integration

Mobile QR Check Inでは、

Mobile Camera
↓
QR Payload
↓
StageArt API
↓
Issued Ticket Resolution
↓
Reservation
↓
Check In

という構造を基本とする。

Camera / QR Scannerは、
Mobile ClientのInfrastructure機能である。

QR Scanner自体を
StageArt Business Domainへ持ち込まない。

---

# 77. Mobile Reception Mode and Integration

Reception Modeは、
Integration Boundaryではなく
Mobile ClientのOperational Modeである。

したがって、

Reception Mode
≠
External Integration

である。

Reception Modeでは、
必要に応じてQR Scanner、
Camera、
NetworkなどのDevice機能を利用する。

---

# 78. Device Integration

Mobile Deviceの、

- Camera
- QR Scanner
- Push Notification
- Local Storage
- Network

などは、
Mobile Infrastructureとして扱う。

Device APIを、
StageArt Domainへ直接持ち込まない。

---

# 79. Camera Integration

QR受付では、
Mobile ClientがCameraを起動する。

基本構造：

Mobile Application
↓
Camera API
↓
QR Scanner
↓
QR Payload
↓
StageArt API

Camera Imageそのものを、
StageArt Serverへ送信する必要はない。

必要なのは、
Business Operationに必要なIdentifierである。

---

# 80. QR Image Data

QR受付では、
原則としてCamera Imageを
Business Dataとして保存しない。

QR Imageが必要な場合は、
別途Document / Evidence Dataとして
明示的に定義する。

通常のCheck Inでは、

QR Image
≠
Check In Fact

とする。

---

# 81. Mobile Offline Consideration

受付では、
Network Failureが発生する可能性がある。

原則として、
Check In Business Factは
Server Sideで確定する。

そのため、
完全Offline状態でのCheck Inを
初期Architectureの必須要件とはしない。

Offline Supportを導入する場合は、
Conflict Resolution、
Replay Protection、
Idempotencyなどを
別途定義する。

---

# 82. Network Failure During Check In

Check In Request送信後に
Network Timeoutが発生した場合、

Client
↓
Request
↓
Server
↓
Check In Completed
↓
Response Lost

という状態が発生する可能性がある。

この場合、
Clientが単純に再送しても
二重Check Inにならないよう、
Idempotencyを利用する。

---

# 83. External Integration and Business Fact

External Integrationが
Business Operationの後段にある場合、

Business Fact
↓
Domain Event
↓
Integration

という順序を基本とする。

External Integrationの失敗によって、
すでに確定したBusiness Factを
不必要にRollbackしない。

---

# 84. Integration Transaction Boundary

External Serviceとの通信を、
StageArt Database Transactionと
無条件に同一Transactionにしない。

例えば、

Database Transaction
↓
Commit
↓
Integration Job
↓
External Service

という構造を利用できる。

Distributed Transactionを
必要以上に導入しない。

---

# 85. Outbox Pattern

必要に応じて、
Outbox Patternを利用できる。

基本構造：

Business Fact
↓
Database Transaction
├── Business Data
└── Outbox Event
↓
Commit
↓
Outbox Worker
↓
Integration
↓
External Service

これにより、
Business Factと
Integration Eventの
Consistencyを高める。

具体的な採用は、
Implementation Architectureで決定する。

---

# 86. Integration Monitoring

Integrationについて、
少なくとも以下をMonitoringできる構造とする。

- Request Count
- Success Count
- Failure Count
- Retry Count
- Timeout Count
- Latency
- Queue Length
- Job Status
- Last Successful Sync
- Last Failed Sync

---

# 87. Integration Error Logging

Integration Errorでは、
必要なTechnical Contextを記録する。

例えば、

- Provider
- Operation
- Request ID
- Job ID
- External Reference
- Error Category
- Timestamp

など。

ただし、

- Secret
- Password
- Token
- 不要なPersonal Data

などをLogへ出力しない。

---

# 88. Integration Audit

Integration Operationについて、
必要に応じてAuditを記録する。

例えば、

- External Account Link
- Payment Operation
- Ticket Synchronization
- Data Export
- Organization Export
- System Operation

など。

Integration LogとBusiness Auditを、
必要に応じて分離する。

---

# 89. Integration Security

Integration Credentialは、
Infrastructure側で管理する。

例えば、

- API Key
- Secret
- OAuth Token
- Webhook Secret
- Service Account Credential

など。

これらを、

- Domain
- Client
- API Response
- Git Repository

へ直接保存・公開しない。

---

# 90. Secret Rotation

External Service Credentialは、
必要に応じてRotationできる構造とする。

Credential変更によって、
Domain ModelやBusiness Dataを
変更しない。

---

# 91. OAuth Integration

OAuthを利用するExternal Serviceでは、

StageArt
↓
OAuth Flow
↓
External Account
↓
Access Token
↓
Integration Adapter

という構造を利用する。

Access Token自体を、
Person Business Dataとして扱わない。

---

# 92. External Account Linking

External Accountを
PersonやOrganizationにLinkする場合、

Person / Organization
↓
External Account Link
↓
External Provider Identity

というMappingを利用する。

External Account Linkは、
Authentication Identityや
Business Identityと必要に応じて分離する。

---

# 93. Organization Scope in Integration

Integration Operationでも、
Organization Scopeを維持する。

例えば、

Organization A
↓
Calendar Integration A

Organization B
↓
Calendar Integration B

というように、
OrganizationごとのExternal Account / Configurationを
分離できる構造とする。

---

# 94. Production Scope in Integration

Production単位でExternal Integrationを
持つ場合でも、
Production Scopeを維持する。

例えば、

Production A
↓
External Calendar A

Production B
↓
External Calendar B

という構造。

Production AのIntegration Credentialや
External Referenceを、
Production Bで利用しない。

---

# 95. System Administrator Integration

System Administratorが
Organizationを選択して
Business Managementを行う場合、

Selected Organization Context
↓
通常Business API
↓
Integration Process

という構造を利用する。

System Administrator専用の
別Integration Business Ruleを作らない。

---

# 96. System Operations Integration

System Operationsでは、
Backup StorageやMirror Environmentなどの
Infrastructure Integrationを扱う。

例えば、

StageArt Primary
↓
Backup Adapter
↓
Backup Storage

StageArt Primary
↓
Replication Adapter
↓
Mirror Environment

など。

これらは、
Business Domain Integrationとは分離する。

---

# 97. Backup Integration

Backupは、
Business DataをRecovery可能な形で
保存するためのIntegrationである。

基本構造：

Primary Database
↓
Backup Process
↓
Backup Storage

Primary File Storage
↓
Backup Process
↓
Backup Storage

Backup Dataは、
通常運用時のBusiness Factの
別Source of Truthではない。

---

# 98. Backup Metadata

Backupについて、
必要なMetadataを管理する。

例えば、

- Backup ID
- Backup Type
- Created At
- Source
- Size
- Status
- Verification Status
- Retention
- Storage Reference

など。

Backup Metadataは、
System Operational Dataとして扱う。

---

# 99. Backup Verification

Backupが作成されたことと、
Recovery可能であることを
区別する。

必要に応じて、

Backup
↓
Verification
↓
Restore Test
↓
Recovery Validation

を行う。

---

# 100. Mirror Integration

Mirror Environmentは、
Primary Environmentの
Availability / Failoverを目的とする。

基本構造：

Primary
↓
Replication
↓
Mirror

Mirror Dataは、
通常運用時のBusiness Factの
独立した正本ではない。

---

# 101. Replication Failure

Replicationが停止しても、
Primary Business Dataを
不正に変更しない。

例えば、

Primary
→ Operational

Replication
→ Failed

Mirror
→ Stale

という状態を許容できる。

Replication Statusは、
System Operations Dataとして管理する。

---

# 102. Failover Integration

Primary障害時には、
必要に応じてMirrorへFailoverする。

基本構造：

Primary Failure
↓
Failover Process
↓
Mirror
↓
New Primary
↓
Service Recovery

Failover後には、
Data Consistencyを確認する。

具体的なFailover Procedureは、
Deployment / Operations Architectureで定義する。

---

# 103. Recovery Integration

Recoveryでは、

Backup
↓
Restore
↓
Consistency Check
↓
Recovered Primary

または、

Primary Failure
↓
Mirror
↓
Failover
↓
Recovered Service

という構造を利用する。

Recovery Operationは、
System Administrator専用とする。

---

# 104. Integration Availability

External ServiceのAvailabilityと、
StageArt Core Availabilityを
分離する。

例えば、

Email Service
→ Down

であっても、

Reservation
→ Available

Check In
→ Available

という状態を可能な限り維持する。

---

# 105. Critical Integration

以下のIntegrationは、
Reception / Business Operationへの
影響が大きいため、
特にAvailabilityを考慮する。

- Authentication
- Core Database
- Ticket Resolution
- Reservation Resolution
- Check In

ただし、
External Serviceに依存する場合でも、
Business RuleとIntegration Failureを
分離する。

---

# 106. Non Critical Integration

以下は、
必要に応じて非同期化できる。

- Email
- Push Notification
- Social Media
- Calendar Export
- Accounting Export
- Reporting
- Large File Processing

Core Business OperationのCritical Pathを
必要以上にExternal Serviceへ依存させない。

---

# 107. Integration Contract

Integration Interfaceでは、
少なくとも以下を定義する。

- Operation
- Request
- Response
- Authentication
- External Reference
- Error
- Retry
- Idempotency
- Timeout
- Scope
- Sync / Async
- Source of Truth

---

# 108. Integration Testing

Integrationについて、
以下をテスト対象とする。

- Authentication
- Request Mapping
- Response Mapping
- External Error
- Timeout
- Retry
- Idempotency
- Webhook
- Signature Validation
- Duplicate Event
- Scope Isolation
- Credential Failure
- External Service Downtime
- Recovery

---

# 109. External Service Contract Test

Provider Adapterについて、
External API Contractとの
Compatibilityを確認する。

例えば、

- Request Format
- Response Format
- Error Format
- Authentication
- Webhook Signature
- API Version

など。

---

# 110. Integration Mock

Development / Test環境では、
External ServiceをMockできる構造とする。

例えば、

Email Adapter
↓
Mock Email Service

Payment Adapter
↓
Mock Payment Provider

Calendar Adapter
↓
Mock Calendar Service

など。

Domain Testが、
External ServiceのAvailabilityに
依存しない構造とする。

---

# 111. Integration Environment

Integration Configurationは、
Environmentごとに分離する。

例えば、

Development
↓
Sandbox Provider

Test
↓
Test Provider

Production
↓
Production Provider

Production Credentialを
Development環境で利用しない。

---

# 112. External API Version

External APIのVersion変更に対して、
Adapter側で吸収できる構造を目指す。

例えば、

External API v1
↓
Adapter
↓
StageArt Integration Interface

External API v2
↓
Adapter
↓
StageArt Integration Interface

という構造。

Core Domainを変更せずに、
Adapterを更新できることを目指す。

---

# 113. Integration Migration

Provider変更時には、

Old Provider
↓
Adapter A

New Provider
↓
Adapter B

という構造を利用できる。

Application Interfaceは、
可能な限り維持する。

---

# 114. Provider Independence

StageArt Core Domainは、
特定Providerへ
過度に依存しない。

例えば、

Email
≠
特定Email Provider

Storage
≠
特定Storage Provider

Calendar
≠
特定Calendar Provider

Messaging
≠
LINEそのもの

とする。

---

# 115. Integration Data Retention

Integration DataのRetentionは、
Business Dataと分離して定義する。

例えば、

- Integration Job
- Webhook Event
- External Reference
- Delivery Log
- Error Log
- Retry History

など。

具体的なRetention Policyは、
Operations Architectureで定義する。

---

# 116. Integration Data Privacy

Integration Dataにも、
必要なPrivacy / Scopeを適用する。

External Serviceへ送信するDataは、
必要最小限とする。

特に、

- Person Information
- Reservation Information
- Audience Information
- Contact Information

などを、
不要にExternal Serviceへ送信しない。

---

# 117. Data Minimization

External Integrationでは、
目的に必要なDataだけを送信する。

例えば、

Email送信に必要な情報だけを
Email Providerへ送信する。

Full Person Profileや
Internal Permission Dataを、
不要にExternal Serviceへ送信しない。

---

# 118. Integration and Audit

External Integrationによって
Business Factが生成・変更される場合、
必要に応じてAuditを記録する。

例えば、

External Payment Confirmed
↓
Reservation Confirmed

など。

ただし、
External Event自体と
StageArt Business Factを
同一視しない。

---

# 119. Integration and Eventual Consistency

External Integrationでは、
Eventual Consistencyを許容する場合がある。

例えば、

StageArt
↓
Calendar Sync
↓
External Calendar

では、
一時的にExternal Calendarが
StageArtより遅れる可能性がある。

Core Business Factを優先する。

---

# 120. Integration Status and Business Status

Integration Statusと
Business Statusを分離する。

例えば、

Reservation
→ Confirmed

Email Delivery
→ Failed

Calendar Sync
→ Pending

という状態を許容する。

Integration Failureによって、
Reservationを自動的にCancelledへ
変更しない。

---

# 121. Integration Failure Classification

Integration Failureを、
少なくとも以下のように分類できる。

- Network Error
- Timeout
- Authentication Error
- Authorization Error
- Rate Limit
- Validation Error
- External Business Error
- Provider Error
- Unknown Error

Failure Categoryによって、
Retry / Manual Interventionを判断する。

---

# 122. Integration Recovery

Integration Failure後は、

Failure
↓
Retry
↓
Success

または、

Failure
↓
Retry Limit
↓
Manual Recovery

というFlowを利用する。

Manual Recoveryは、
必要な権限を持つOperatorのみが実行する。

---

# 123. Integration Manual Retry

System Administratorは、
必要に応じてIntegration Jobを
Manual Retryできる。

ただし、
Manual Retryによって
Business Factを重複生成しない。

Idempotencyを維持する。

---

# 124. Integration Cancellation

Pending Integration Jobを
必要に応じてCancelできる。

ただし、

Integration Job Cancel
≠
Business Fact Cancel

である。

例えば、

Email Delivery Cancel
≠
Reservation Cancel

とする。

---

# 125. Integration Observability

System Administratorが、
Integrationの状態を
確認できる構造とする。

例えば、

- Provider Status
- Queue Status
- Failed Jobs
- Retry Count
- Last Successful Sync
- Last Error
- Webhook Failure
- External API Latency

など。

これはSystem Operations / Management機能として扱う。

---

# 126. System Administrator Integration Context

System Administratorが
Organizationを選択している場合でも、

Selected Organization Context
↓
Organization Integration

というScopeを維持する。

System Administratorが
Organization Aを選択した状態で、
Organization BのExternal Integrationを
誤って操作できないようにする。

---

# 127. Cross Organization Integration Isolation

OrganizationごとのExternal Accountや
Integration Configurationを
必要に応じて分離する。

例えば、

Organization A
↓
LINE Account A

Organization B
↓
LINE Account B

など。

Organization AのCredentialを
Organization Bで利用しない。

---

# 128. System-wide Integration

System-wide Integrationについては、
Organization Scopeを持たない
System Integrationとして扱える。

例えば、

- Backup Storage
- Monitoring
- System Email
- Infrastructure Notification

など。

System-wide Integrationと
Organization-specific Integrationを分離する。

---

# 129. Integration Configuration

Integration Configurationは、
Business Entityとは分離する。

例えば、

Organization
↓
Integration Configuration
↓
Provider

という構造。

Configurationには、
必要に応じて、

- Provider
- Endpoint
- External Account Reference
- Enabled
- Scope
- Credential Reference

などを持つ。

Credentialそのものを、
Business Dataとして保存しない。

---

# 130. Integration Enable / Disable

Integrationは、
必要に応じてEnable / Disableできる。

例えば、

Email Integration
→ Enabled

Calendar Integration
→ Disabled

など。

IntegrationをDisableしても、
既存Business Factを
削除・変更しない。

---

# 131. Integration Configuration Security

Integration Configurationの変更は、
適切なAuthorizationを必要とする。

例えば、

Organization Administrator
→ Organization Integration Configuration

System Administrator
→ System Integration Configuration

など。

Credentialそのものを、
Clientへ返さない。

---

# 132. Integration Architecture and API

APIは、
Integrationそのものを
Clientへ直接公開しない。

基本構造：

Client
↓
API
↓
Application
↓
Integration Interface
↓
Adapter
↓
External Service

Clientは、
External API Credentialや
Provider-specific Requestを
直接扱わない。

---

# 133. Integration Architecture and Data

Data Architectureで定義した、

- Business Fact
- Source of Truth
- External Reference
- Read Model
- Audit
- Operational Data

を維持する。

External Dataは、
必要に応じてIntegration Mappingを経由して
StageArt Dataへ反映する。

---

# 134. Integration Architecture and Check In

Check Inでは、

Reservation
↓
Check In

がCanonical Relationshipである。

External TicketingやQR Providerを
利用する場合でも、

External Identifier
↓
Resolution
↓
Reservation
↓
Check In

という構造を維持する。

External Serviceを、
Check In Business Factの正本にしない。

---

# 135. Integration Architecture and Mobile Reception

Mobile Reception Modeでは、

Mobile
↓
Camera / QR Scanner
↓
QR Payload
↓
StageArt API
↓
Reservation Resolution
↓
Check In

という構造を基本とする。

CameraやQR Scannerは、
Mobile Device Integrationであり、
External Business System Integrationとは
分離する。

---

# 136. Integration Architecture and Web Reception

Web Receptionでは、

Web
↓
Reservation / Issued Ticket Query
↓
StageArt API
↓
Reservation Resolution
↓
Check In

という構造を基本とする。

External Integrationを、
Web受付の必須条件にしない。

---

# 137. Integration Architecture and Accounting

Check Inが確定した後、

Check In
↓
CheckInCompleted
↓
Accounting Process
↓
External Accounting

という非同期Integrationを
利用できる。

External AccountingのFailureは、
Check In Business Factを
不正に変更しない。

---

# 138. Integration Architecture and Backup

Backupは、
System Operations Integrationとして扱う。

Primary Data
↓
Backup Adapter
↓
Backup Storage

Backupは、
Business Domain Integrationとは
分離する。

---

# 139. Integration Architecture and Mirror

Mirrorは、
Availability / Failoverのための
Infrastructure Integrationである。

Primary
↓
Replication Adapter
↓
Mirror

Mirror Dataは、
通常運用時のBusiness Factの
別Source of Truthではない。

---

# 140. Integration Architecture and Recovery

Recoveryでは、

Backup
↓
Restore
↓
Validation
↓
Recovered Primary

または、

Primary Failure
↓
Mirror
↓
Failover
↓
Recovered Service

という構造を利用する。

Recoveryは、
System Operations Boundaryとして扱う。

---

# 141. Integration Architecture Rules

Integration Architectureでは、
以下を禁止または原則禁止とする。

- DomainからExternal APIを直接呼び出すこと
- External Data ModelをそのままDomain Entityとして扱うこと
- External IdentifierをStageArt Business Identifierと同一視すること
- External ServiceをBusiness Factの正本とすること
- External FailureによってBusiness Factを不正にRollbackすること
- Webhook Payloadを無条件に信頼すること
- RetryによってBusiness Factを重複生成すること
- CredentialをClientへ公開すること
- CredentialをGit Repositoryへ保存すること
- External Service固有のLogicをDomainへ持ち込むこと
- Organization Scopeを越えてExternal Integrationを操作すること
- System IntegrationとOrganization Integrationを無条件に混在させること
- BackupをMirrorの代わりに扱うこと
- MirrorをBackupの代わりに扱うこと
- Integration JobをBusiness Factとして扱うこと
- Integration StatusをBusiness Statusと同一視すること

---

# 142. Integration Architecture Summary

StageArt Integration Architectureでは、

StageArt
↓
Application
↓
Integration Interface
↓
Adapter
↓
External Service

というBoundaryを基本とする。

External Serviceは、
StageArt Core Domainへ直接依存しない。

External ServiceとのData交換は、

Request
↓
Adapter
↓
Mapping
↓
Application
↓
Domain

または、

External Event
↓
Webhook
↓
Validation
↓
Adapter
↓
Application
↓
Domain

という構造を利用する。

Core Business Dataについては、
原則としてStageArtをSource of Truthとする。

特に、

- Organization
- Project
- Production
- Performance
- Rehearsal
- Reservation
- Issued Ticket
- Check In
- Accounting

などは、
StageArt DomainがBusiness Factを所有する。

External Serviceは、
必要に応じてExternal Representation、
External Reference、
Integration Source、
またはInfrastructure Serviceとして利用する。

Check Inについては、

Reservation
↓
Check In

をCanonical Relationshipとする。

External Ticketing System、
QR Provider、
Mobile Camera、
QR Scannerなどを利用しても、

External Identifier
↓
Reservation Resolution
↓
Check In Use Case
↓
Check In

という構造を維持する。

QR Codeそのものを
Check In Business Factとしない。

Issued Ticketを
Check Inと同一視しない。

Mobile Receptionでは、

Mobile Client
↓
Reception Mode
↓
Camera / QR Scanner
↓
StageArt API
↓
Reservation Resolution
↓
Check In

という構造を利用する。

Web Receptionでは、

Web Client
↓
Reservation / Issued Ticket List
↓
Reservation Resolution
↓
Check In

という構造を利用する。

どちらの場合も、
同じCheck In Application Use Caseを利用する。

Email、
Push Notification、
Calendar、
Social Media、
Accounting、
External Ticketingなどの
Non-Critical Integrationについては、
必要に応じて、

Business Fact
↓
Domain Event
↓
Queue
↓
Background Worker
↓
Integration Adapter
↓
External Service

という非同期構造を利用する。

External Service Failureが発生しても、
すでに確定したBusiness Factを
不必要にRollbackしない。

例えば、

Reservation
→ Confirmed

Email
→ Failed

Check In
→ Completed

Accounting
→ Pending

Calendar
→ Retry

というように、
Business StateとIntegration Stateを
分離する。

Webhookでは、

Webhook
↓
Signature Validation
↓
Replay Protection
↓
Integration Event
↓
Application
↓
Domain

という構造を基本とする。

Webhook再送やNetwork Retryによって、
同じBusiness Factを重複生成しない。

Integrationでは、

- Retry
- Idempotency
- Timeout
- Queue
- Background Worker
- Error Handling
- Monitoring
- Recovery

を考慮する。

Integration Credentialは、
Infrastructure側で安全に管理し、
Client / Domain / Git Repositoryへ
公開しない。

Organization-specific Integrationでは、
Organization Scopeを維持する。

System Administratorが
Organizationを選択している場合も、

System Administrator
↓
Selected Organization Context
↓
Organization Integration

として扱い、
選択していないOrganizationの
Integration Configurationへ
誤ってアクセスできないようにする。

System-wide Integrationについては、
Organization Scopeを持たない
System Integrationとして扱う。

例えば、

- Backup
- Replication
- Mirror
- Monitoring
- System Notification

など。

Backupについては、

Primary
↓
Backup Process
↓
Backup Storage

とし、
Backupを通常Business Dataの
Source of Truthとはしない。

Mirrorについては、

Primary
↓
Replication
↓
Mirror

とし、
Availability / Failoverを目的とする。

BackupとMirrorを、
同一目的の仕組みとして扱わない。

Recoveryでは、

Backup
↓
Restore
↓
Validation
↓
Recovered Primary

または、

Primary Failure
↓
Mirror
↓
Failover
↓
Recovered Service

という構造を利用する。

Integration Architectureの最重要原則は、

「External SystemをStageArt Domainへ直接依存させず、
Integration InterfaceとAdapterによって分離する」

ことである。

さらに、

「External Serviceの成功・失敗と
StageArt Business Factの確定・失敗を分離する」

ことを重要な原則とする。

そして、

「External Ticket、
QR Code、
Webhook、
Payment、
Calendar、
Email、
MessagingなどのExternal Representationを
StageArt Business Factそのものとしない」

ことを明確にする。

また、

「Mobile ReceptionとWeb Receptionは
異なる入口であって、
異なるBusiness Ruleではない」

ことを維持する。

さらに、

「System Administratorによる
Organization Selection後のBusiness Operationは、
通常のOrganization Scopeを通して実行し、
System Administrator専用の重複Business Logicを作らない」

ことを基本方針とする。

これにより、

Web Client
Mobile Client
WordPress
PHP Server
Authentication Provider
Email Service
Calendar
LINE
Payment Provider
Accounting System
External Ticketing
Social Media
Backup Storage
Mirror Environment

などのExternal System / Infrastructureが変更されても、

StageArtの

- Business Identity
- Business Fact
- Organization Scope
- Project Scope
- Production Scope
- Performance Scope
- Reservation
- Issued Ticket
- Check In
- Rehearsal
- Accounting

を安定して維持できるIntegration Architectureを実現する。

---
