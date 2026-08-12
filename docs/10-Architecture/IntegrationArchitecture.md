# StageArt Blueprint

# 10 - Architecture
# Integration Architecture

Version : 1.0

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
- WordPress
- LINEなどのMessaging Service
- Error Handling
- Retry
- Idempotency
- Integration Monitoring

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
- External Serviceを変更してもStageArt Core Business Logicへ影響を最小化する。

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
└── Authentication Adapter

External Service固有の実装は、
Adapter側へ閉じ込める。

---

# 4. Integration Interface

ApplicationからExternal Serviceを利用する場合、
Integration Interfaceを利用する。

例えば、

NotificationService
EmailService
CalendarService
FileStorage
PaymentService
AccountingService

など。

Applicationは、
具体的なProviderを直接知らない。

---

# 5. Adapter

Adapterは、
StageArtのInterfaceと
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
- Provider Specific Logic

などを扱う。

---

# 6. External Data Model

External ServiceのData Modelを、
そのままStageArt Domain Entityとして利用しない。

例えば、

External Ticket
↓
Adapter
↓
StageArt Ticket Context

など。

External ServiceのField追加や変更が、
Domain Modelへ直接波及しない構造を目指す。

---

# 7. External System Categories

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

実際に採用するServiceは、
別途Implementation Specificationで決定する。

---

# 8. Authentication Integration

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

---

# 9. Authentication Adapter

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

# 10. WordPress Integration

WordPressをStageArtの
Infrastructureとして利用できる。

例えば、

- Web Hosting
- Authentication
- REST API
- Plugin Runtime
- File Storage
- Database

など。

ただし、
WordPress固有のConceptと
StageArt Domain Conceptを分離する。

---

# 11. WordPress API Integration

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

# 12. WordPress Plugin Integration

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

# 13. WordPress User Integration

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

# 14. Email Integration

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

# 15. Email Delivery

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
Email Serviceに依存させない。

---

# 16. Email Failure

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

# 17. Notification Integration

Notificationは、
Business EventをTriggerとして
生成できる。

例えば、

ReservationConfirmed
CheckInCompleted
RehearsalChanged
PerformanceChanged

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

# 18. Messaging Integration

将来的に、

- LINE
- SMS
- Push Notification
- Chat Service

などを利用する可能性がある。

これらは、
Messaging Integrationとして扱う。

Applicationから、
具体的なMessaging Providerへ
直接依存しない。

---

# 19. LINE Integration

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

# 20. Messaging Identity

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

# 21. Calendar Integration

Calendar ServiceとのIntegrationでは、

StageArt Event
↓
Calendar Adapter
↓
External Calendar API

という構造を利用する。

例えば、

- Rehearsal
- Performance
- Meeting
- Schedule Change

など。

---

# 22. Calendar Synchronization

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

# 23. Calendar External Changes

External Calendar側で変更が行われる場合、
Webhookなどによって
StageArtへ通知できる。

ただし、

External Calendar Change
↓
StageArt Business Fact

と無条件に同期しない。

必要なValidationとAuthorizationを行う。

---

# 24. File Storage Integration

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

# 25. File Storage Boundary

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

# 26. File Upload

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

Clientから、
External Storageへ直接Uploadする方式を
採用する場合でも、
AuthorizationとUpload Policyを
StageArt側で管理する。

---

# 27. File Download

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

# 28. Payment Integration

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

# 29. Payment Webhook

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

# 30. Payment Idempotency

Payment Integrationでは、
RetryやWebhook再送を考慮する。

例えば、

Payment Provider
↓
Webhook
↓
StageArt

が複数回送信されても、
同じPayment Factを重複作成しない。

External Transaction IDなどを利用して、
Idempotencyを確保する。

---

# 31. Accounting Integration

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

# 32. Check In and Accounting Integration

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

# 33. Accounting Failure

External Accounting Systemが
停止している場合でも、
Check Inそのものを
不要に失敗させない構造を検討する。

例えば、

Check In
→ Completed

Accounting
→ Pending

External Accounting
→ Retry

など。

具体的なAccounting Consistency Ruleは、
Accounting Architectureで定義する。

---

# 34. Ticketing Integration

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

# 35. External Ticketing and Check In

External Ticketing Systemから
Ticket Informationを取得する場合でも、

External Ticket
↓
Adapter
↓
StageArt Ticket Context
↓
Check In Use Case

という構造とする。

External Systemが
Check In Business Ruleそのものを
所有する構造にはしない。

---

# 36. External Ticketing Webhook

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

# 37. Social Media Integration

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

# 38. Social Media Failure

SNS Publishに失敗しても、
ProductionやPerformanceなどの
Business FactをRollbackしない。

Social Publishは、
必要に応じて、

- Pending
- Retry
- Failed

として管理する。

---

# 39. External API Authentication

External APIへのAccessでは、
必要に応じて、

- API Key
- OAuth
- Access Token
- Service Account

などを利用する。

Credentialは、
Source CodeへHard Codeしない。

具体的なSecret Managementは、
Security / Deployment Architectureで定義する。

---

# 40. External API Timeout

External APIには、
Timeoutを設定する。

External APIのResponseを
無期限に待たない。

Timeout発生時には、

- Retry
- Queue
- Failed
- Pending

など、
Operationの性質に応じた処理を行う。

---

# 41. External API Retry

Retryは、
すべてのErrorに対して
無条件に実行しない。

例えば、

Network Timeout
→ Retry可能

Rate Limit
→ Backoff後Retry

Authentication Failure
→ Credential確認

Invalid Request
→ Retry不要

など。

---

# 42. Exponential Backoff

External API Retryでは、
必要に応じてExponential Backoffを利用する。

基本：

Retry 1
↓
Retry 2
↓
Retry 3
↓
Failed

具体的なRetry回数と
Backoff時間は、
Implementation Specificationで定義する。

---

# 43. Circuit Breaker

External Serviceが
継続的にFailureしている場合、
Circuit Breakerを利用できる。

例えば、

StageArt
↓
External Service
↓
Repeated Failure
↓
Circuit Open

とする。

External Serviceの障害によって、
StageArt全体が不要に
連鎖Failureしないようにする。

---

# 44. Queue Integration

External Integrationでは、
必要に応じてQueueを利用する。

基本構造：

Application
↓
Queue
↓
Integration Worker
↓
External API

用途：

- Email
- Notification
- Calendar
- Accounting
- SNS
- File Processing

など。

---

# 45. Queue Message

Queue Messageには、
必要なIdentifierとContextだけを
含める。

例えば、

- Event ID
- Entity ID
- Operation
- Timestamp
- Correlation ID

など。

大きなDomain Entityそのものを、
無条件にMessageへ詰め込まない。

---

# 46. Queue Idempotency

Queue Messageは、
複数回Deliveryされる可能性を考慮する。

同じMessageを複数回処理しても、
External Side Effectが
不正に重複しないようにする。

---

# 47. Queue Failure

Queue処理が失敗した場合、

- Retry
- Dead Letter
- Manual Retry
- Alert

などを利用できる。

Business Fact自体を
不正に削除しない。

---

# 48. Domain Event and Integration

Domain Eventは、
External IntegrationのTriggerとして
利用できる。

例えば、

CheckInCompleted
↓
Notification
↓
Email

など。

Domain Event自体は、
External ProviderのAPI Contractではない。

---

# 49. Event Mapping

Domain Eventを
External EventへMappingする。

例えば、

CheckInCompleted
↓
Notification Event
↓
Email Adapter

というように、
Domain EventとProvider Payloadを分離する。

---

# 50. Webhook Reception

Webhookを受信する場合、
専用のIntegration Endpointを設ける。

基本構造：

External Service
↓
Webhook Endpoint
↓
Signature Verification
↓
Event Mapping
↓
Application
↓
Domain

Webhook Controllerに、
Business Ruleを実装しない。

---

# 51. Webhook Security

Webhookでは、
必要に応じて、

- Signature Verification
- Shared Secret
- Timestamp
- Replay Protection
- Source Validation

などを行う。

Webhook Payloadを、
無条件に信頼しない。

---

# 52. Webhook Idempotency

Webhook Providerが
同じEventを複数回送信する可能性を考慮する。

例えば、

External Event ID
↓
Processed Event Check
↓
Already Processed
または
Process

とする。

---

# 53. Webhook Processing

Webhookの基本Flow：

Webhook
↓
Authentication / Signature
↓
Payload Validation
↓
Event ID Validation
↓
Mapping
↓
Application Use Case
↓
Domain Operation
↓
Result

External Payloadを、
直接DatabaseへInsertしない。

---

# 54. External Event Ordering

External Eventの到着順序が
保証されない可能性を考慮する。

例えば、

Update A
Update B

が逆順に到着する場合。

Event Timestamp / Version / Sequenceなどを
必要に応じて利用する。

---

# 55. External System as Source of Truth

Integrationでは、
External SystemがSource of Truthとなる場合がある。

例えば、

Payment Provider
→ Payment Transaction

など。

一方、

StageArt Business Fact
→ StageArt Domain

とする。

Source of Truthを
Integration単位で明確にする。

---

# 56. Source of Truth Matrix

Integrationごとに、
Source of Truthを明確にする。

例：

Person
→ StageArt

Production
→ StageArt

Performance
→ StageArt

Check In
→ StageArt

Journal Entry
→ StageArt Accounting

Payment Transaction
→ Payment Provider / StageArt Payment Integration
必要に応じて定義

Calendar Event
→ StageArt Scheduleを基本とする

File Binary
→ Storage Provider

など。

具体的な最終定義は、
各Domain / Integration Specificationで確定する。

---

# 57. Synchronization Model

External Systemとの同期には、
以下の方式を利用できる。

- Request / Response
- Polling
- Webhook
- Queue
- Batch
- Scheduled Job

Operationの性質に応じて選択する。

---

# 58. Synchronous Integration

即時Responseが必要な場合、
Synchronous Integrationを利用する。

例えば、

StageArt
↓
Payment API
↓
Immediate Result

など。

ただし、
External Service Failureが
User Operationへ直接影響するため、
Critical Pathへ入れるIntegrationは慎重に設計する。

---

# 59. Asynchronous Integration

即時Responseが不要な場合、
Asynchronous Integrationを利用する。

例えば、

CheckInCompleted
↓
Queue
↓
Email

など。

Asynchronous Integrationでは、
Pending / Failed / Retryを管理できる構造とする。

---

# 60. Batch Integration

大量Dataの連携では、
Batch Integrationを利用できる。

例えば、

- Accounting Export
- Reporting
- Historical Data
- Bulk Synchronization

など。

Batch処理は、
通常のUser Requestから分離する。

---

# 61. Scheduled Integration

定期的なExternal Syncでは、
Scheduled Jobを利用できる。

例えば、

Daily
↓
External Calendar Sync

など。

Scheduled Jobでも、
IdempotencyとRetryを考慮する。

---

# 62. Integration Status

必要に応じて、
External Integrationの状態を管理する。

例えば、

- Pending
- Processing
- Succeeded
- Failed
- Retry Scheduled
- Cancelled

など。

Integration Statusと
Business Fact Statusを混同しない。

---

# 63. Integration Record

重要なExternal Operationについて、
Integration Recordを保持できる。

例えば、

- Integration ID
- Provider
- Operation
- External ID
- Status
- Created At
- Updated At
- Retry Count
- Last Error

など。

---

# 64. External ID Mapping

External SystemのIdentifierは、
StageArt Identifierと分離する。

例えば、

StageArt Ticket ID
+
External Ticket ID

というMappingを保持できる。

Provider変更時にも、
StageArt Identityを維持する。

---

# 65. External Reference

Domain Entityに必要な場合、
External Referenceを保持できる。

例えば、

Production
↓
External Calendar Event ID

など。

ただし、
External IDをDomain Identityそのものとしない。

---

# 66. Integration Consistency

Integration処理が遅延しても、
Core Business Factが壊れないようにする。

例えば、

Production Created
↓
Calendar Sync Pending

という状態を許容する。

Production自体を、
Calendar APIのSuccessに依存させない。

---

# 67. Check In Integration Principle

Check Inは、
External Serviceの状態に
直接依存しないことを基本とする。

基本：

Web / Mobile
↓
Check In API
↓
Check In
↓
CheckInCompleted

その後、

CheckInCompleted
├── History
├── Accounting
├── Notification
└── External Integration

とする。

---

# 68. Check In and Notification

Check In成功後に、
Notificationを送る場合、

Check In
↓
CheckInCompleted
↓
Notification Process
↓
Messaging Adapter
↓
External Service

とする。

Notification Failureによって、
Check InをRollbackしない。

---

# 69. Check In and Calendar

通常、
Check Inそのものを
Calendar Serviceへ同期する必要はない。

将来的に必要になった場合でも、

CheckInCompleted
↓
Calendar Integration

という非同期Integrationを基本とする。

---

# 70. Check In and External Accounting

Check InによるTicket Revenueを
External Accounting Systemへ
連携する場合、

Check In
↓
CheckInCompleted
↓
Accounting Process
↓
Journal Entry
↓
Accounting Adapter
↓
External Accounting System

という構造とする。

---

# 71. Check In and External Ticketing

External Ticketing Systemを
利用する場合でも、
Check In Business Ruleは
StageArt側で管理する。

External Ticketing：

Ticket Identifier / Ticket Status

StageArt：

Check In Fact

という責務分離を基本とする。

---

# 72. Integration and Audit

重要なExternal Integrationについて、
Audit / Integration Logを保持できる。

例えば、

- Provider
- Operation
- External ID
- Actor
- Timestamp
- Result
- Error

など。

---

# 73. Integration Logging

Integration Logでは、
Technical Detailを記録する。

例えば、

- Endpoint
- Status Code
- Duration
- Retry
- Error
- Correlation ID

など。

ただし、

- Token
- Password
- Secret
- 不要なPersonal Data

をLogへ出さない。

---

# 74. Integration Monitoring

Integrationでは、
以下をMonitoringする。

- Success Rate
- Failure Rate
- Latency
- Retry Count
- Queue Size
- Dead Letter
- Webhook Failure
- External API Availability

---

# 75. Integration Alert

重要なIntegration Failureについて、
Alertを発生させることができる。

例えば、

- Payment Failure
- Accounting Failure
- Email Failure
- Storage Failure
- External API Down

など。

Alertは、
Business User向けとTechnical Operator向けを
必要に応じて分ける。

---

# 76. Integration Security

External Integrationでは、

- Credential Protection
- TLS
- Signature Validation
- OAuth
- API Key Protection
- IP Restriction
- Replay Protection

などを考慮する。

---

# 77. Credential Management

External Service Credentialは、
Environment Configuration / Secret Managementで
管理する。

Source Codeへ、
CredentialをHard Codeしない。

Clientへ、
External Service Credentialを
配布しない。

---

# 78. External API Rate Limit

External Service側に
Rate Limitがある場合、
StageArt側でも考慮する。

例えば、

- Queue
- Throttling
- Backoff
- Batch

などを利用する。

---

# 79. External API Contract Change

External ProviderのAPI変更を
StageArt Coreへ直接波及させない。

Provider API変更時は、

External Adapter
↓
Mapping

を修正することを基本とする。

Domain / Application Contractは、
必要以上に変更しない。

---

# 80. Provider Replacement

Integration Architectureでは、
Provider変更可能性を考慮する。

例えば、

Email Provider A
↓
Email Interface
↓
Email Provider B

のように、
Adapterを差し替えられる構造を目指す。

---

# 81. Multi Provider

必要に応じて、
複数Providerを利用できる。

例えば、

Primary Email Provider
↓
Fallback Email Provider

など。

ただし、
Provider切り替えロジックを
Domainへ持ち込まない。

---

# 82. Integration Testing

Integrationでは、
以下をTestする。

- Request Mapping
- Response Mapping
- Authentication
- Error
- Retry
- Idempotency
- Timeout
- Webhook
- Signature Validation
- Provider Failure

---

# 83. Adapter Testing

Adapterは、
Provider API Contractとの
MappingをTestする。

例えば、

StageArt Request
↓
Provider Request

Provider Response
↓
StageArt Result

が正しく変換されることを確認する。

---

# 84. Webhook Testing

Webhookでは、

- Valid Signature
- Invalid Signature
- Duplicate Event
- Invalid Payload
- Replay
- Out-of-order Event
- Provider Failure

などをTestする。

---

# 85. Integration Failure Testing

External Service停止時に、

- Retry
- Queue
- Pending
- Failed
- Alert

などが期待通り動くことをTestする。

---

# 86. Check In Integration Testing

Check In後のIntegrationでは、

Check In
↓
CheckInCompleted
↓
History
↓
Accounting
↓
Notification

などのProcessを、
必要に応じてIntegration Testする。

ただし、
External Service Failureによって
Check In Factが壊れないことを確認する。

---

# 87. End-to-End Integration

代表的なFlow：

Web Check In
↓
Check In API
↓
CheckInUseCase
↓
Check In
↓
CheckInCompleted
├── History
├── Accounting
└── Notification

Mobile QR Check In
↓
Check In API
↓
CheckInUseCase
↓
Check In
↓
CheckInCompleted
├── History
├── Accounting
└── Notification

---

# 88. Integration and Backend

Integration Architectureは、
Backend Architectureの
Infrastructure Boundaryを拡張する。

基本構造：

API
↓
Application
↓
Domain
↓
Integration Interface
↓
Adapter
↓
External Service

---

# 89. Integration and Frontend

Frontendは、
External Serviceへ
直接アクセスしないことを基本とする。

例えば、

Web Client
↓
StageArt API
↓
Application
↓
Integration
↓
External Service

とする。

Mobile Clientについても同様。

---

# 90. Integration and Data Architecture

External Dataを、
StageArt Databaseへ取り込む場合でも、

External Data
↓
Adapter
↓
Application
↓
Domain
↓
Repository
↓
Database

というMappingを行う。

External Dataを、
そのままDatabaseへInsertしない。

---

# 91. Integration and Domain Model

Domain Modelは、
External ProviderのModelに
従属しない。

例えば、

External Payment
≠
StageArt Payment

External Calendar Event
≠
StageArt Performance

External User
≠
StageArt Person

という分離を基本とする。

---

# 92. Integration and API Architecture

APIは、
StageArt Clientの入口。

Integrationは、
StageArtがExternal Systemを利用するための
出口 / 入口である。

基本構造：

Client
↓
StageArt API
↓
Application
↓
Domain
↓
Integration
↓
External Service

---

# 93. Integration and Business Fact

External ServiceのResponseによって、
StageArt Business Factを
直接上書きしない。

Applicationで、

- Validation
- Mapping
- Authorization
- Business Rule

を通した後に、
必要なBusiness Operationを実行する。

---

# 94. Integration Ownership

Integrationを通じて
取得したDataについて、

- Source of Truth
- Owner
- Synchronization Direction
- Update Authority

を明確にする。

例えば、

StageArt Person
→ StageArtがOwner

External Payment Transaction
→ Payment ProviderがSource

など。

---

# 95. Integration Direction

Integration Directionには、

StageArt → External

External → StageArt

Bidirectional

がある。

Integrationごとに、
Directionを明確にする。

---

# 96. One-Way Integration

StageArtからExternalへ
情報を送る場合、

StageArt
↓
Event / Command
↓
External Service

とする。

External ServiceのResponseは、
必要に応じてIntegration Statusとして保持する。

---

# 97. External-to-StageArt Integration

ExternalからStageArtへ
情報を受ける場合、

External
↓
Webhook / Polling
↓
Integration Adapter
↓
Application
↓
Domain

とする。

External Dataを、
直接Domain EntityへHydrateしない。

---

# 98. Bidirectional Integration

Bidirectional Integrationでは、
双方のSource of Truthを明確にする。

例えば、

StageArt
↔
External Calendar

など。

同期方向とConflict Ruleを
Integrationごとに定義する。

---

# 99. Conflict Resolution

External SystemとStageArtの
Dataが競合した場合、

- Source of Truth
- Timestamp
- Version
- Priority
- Manual Resolution

などを利用して解決する。

Integrationごとに、
Conflict Policyを定義する。

---

# 100. Integration Architecture Summary

StageArt Integrationは、

StageArt
↓
Application
↓
Integration Interface
↓
Adapter
↓
External Service

という構造を基本とする。

External Serviceから情報を受ける場合は、

External Service
↓
Webhook / API
↓
Adapter
↓
Application
↓
Domain

という構造を利用する。

External Serviceは、
StageArt Domainの直接の依存先ではない。

また、

Check In
↓
CheckInCompleted
├── History
├── Accounting
├── Notification
└── External Integration

という構造により、
Check Inそのものと
後続のExternal Integrationを分離する。

これにより、

- WordPress
- Email
- Calendar
- File Storage
- Payment
- Accounting
- LINE
- SNS
- External Ticketing
- Authentication Provider

などのExternal Serviceが変更されても、
StageArt Core Business Ruleと
Business Factへの影響を最小化できる。

---

# 101. Integration Architecture Principle

StageArt Integrationの最重要原則：

「External ServiceはStageArt Business Ruleの一部ではなく、
StageArtが利用する外部Capabilityである。」

そのため、

Client
↓
API
↓
Application
↓
Domain
↓
Integration Interface
↓
Adapter
↓
External Service

という境界を維持する。

また、

「External Serviceが失敗しても、
StageArtのBusiness Factを不正に壊さない。」

ことを原則とする。

Check Inについては、

Web / Mobile
↓
Check In API
↓
Check In
↓
CheckInCompleted
↓
History / Accounting / Notification / External Integration

という順序を基本とし、
受付のCore Operationと
外部連携のFailure Domainを分離する。

これにより、
外部サービスの追加・変更・停止があっても、
StageArt Coreを安定して維持できる
Integration Architectureを実現する。

---
