# StageArt Blueprint

# 10 - Architecture
# Security Architecture

Version : 1.1

---

# Purpose

Security Architectureは、
StageArtにおけるSecurity Boundaryと
Access Controlの基本構造を定義する。

Security Architectureでは、

- Authentication
- Authorization
- Identity
- UserAccount
- Person
- Organization Scope
- Project Scope
- Production Scope
- Performance Scope
- Role
- Permission
- Scope-based Data Isolation
- Tenant Isolation
- System Administrator
- Selected Organization Context
- API Security
- Web Security
- Mobile Security
- Reception Security
- QR Reception Security
- Personal Data Protection
- Credential Management
- Encryption
- Session / Token
- Audit
- Logging
- Rate Limiting
- Replay Protection
- Webhook Security
- File Security
- Accounting Security
- Integration Security
- WordPress Security
- Backup Security
- Replication Security
- Recovery Security

を定義する。

Security Architectureでは、
具体的なAuthentication Providerや
Cloud Security Productなどの採用までは確定しない。

---

# 1. Security Architecture Principles

StageArt Securityは、
以下を基本原則とする。

- AuthenticationとAuthorizationを分離する。
- Authenticationは「誰か」を確認する。
- Authorizationは「何をしてよいか」を確認する。
- AuthorizationはServer Sideで必ず実行する。
- Client側のUI制御をSecurity Boundaryとしない。
- Userが所属・権限を持たないScopeのDataを取得できないようにする。
- 所属外DataをFrontendで取得してから隠す設計をしない。
- Query段階でAccess Scopeを適用する。
- Resource IDを知っているだけではAccessを許可しない。
- Organization / Project / Production / Performance Scopeを明確にする。
- Business Factの正本をClientに置かない。
- QR CodeをSecurity Credentialそのものとして無条件に信頼しない。
- External Service CredentialをClientへ渡さない。
- SecretをSource CodeへHard Codeしない。
- Sensitive Dataを必要以上に保存・表示・Loggingしない。
- Important OperationをAudit可能にする。
- Security FailureをBusiness Factの不整合へつなげない。
- Web ClientとMobile ClientでSecurity Ruleを分けない。
- Reception Modeを独立したSecurity Boundaryにしない。
- External IntegrationをSecurity Boundaryの外側として扱う。
- System Administratorであっても、Business DataへのAccessをScope Contextを経由して制御する。
- System Administrator専用のBusiness Rule Bypassを作らない。
- Background JobやIntegration Workerにも必要なSecurity Contextを適用する。
- Backup、Mirror、RecoveryなどのSystem OperationsにもSecurity Controlを適用する。

---

# 2. Security Boundary

StageArtでは、
以下を主要なSecurity Boundaryとする。

Client
↓
API
↓
Authentication
↓
Authorization
↓
Application
↓
Domain
↓
Persistence / Integration

Clientは、
信頼できないEnvironmentとして扱う。

Mobile Client、
Web Client、
Public Client、
System Administration Clientの
すべてにServer Side Securityを適用する。

---

# 3. Zero Trust Principle

Clientから送信されたDataを、
無条件に信頼しない。

例えば、

- User ID
- Person ID
- Organization ID
- Project ID
- Production ID
- Performance ID
- Ticket ID
- Role
- Permission
- Price
- Status
- Check In State

など。

Server側で、
Authentication Contextと
Domain Dataから再検証する。

Clientが送信したScope情報を、
そのままAuthorization Contextとして
利用しない。

---

# 4. Identity Model

StageArtでは、
Authentication Identityと
Business Identityを分離する。

基本構造：

External Identity
↓
UserAccount
↓
Person

External Identityは、
Authentication Providerに依存する。

Personは、
StageArtのBusiness Identityである。

Authentication Providerを変更しても、
Person Identityを変更しない。

---

# 5. UserAccount

UserAccountは、
StageArtへのAccessを持つ
Authentication Contextを表す。

UserAccountは、
Personと関連付けられる。

基本構造：

UserAccount
↓
Person

UserAccountそのものを、
StageArt上のBusiness Personと
直接同一視しない。

---

# 6. Person

Personは、
StageArtにおけるBusiness Identityである。

Personには、

- Organization Membership
- Production Delegate
- Participant
- Audience
- Role Context

などが関連する。

AuthenticationのProvider変更によって、
Person Identityを変更しない。

---

# 7. Authentication

Authenticationは、
Requestを行っているActorが
誰なのかを確認する。

基本Flow：

Request
↓
Authentication
↓
UserAccount
↓
Person
↓
Security Context

Authenticationだけでは、
OperationへのAccessを許可しない。

Authentication成功後に、
Authorizationを実行する。

---

# 8. Authentication Context

Authenticated Requestには、
必要に応じて以下のContextを持たせる。

- UserAccount
- Person
- Authentication Method
- Session / Token
- Client Type
- Device Context

Applicationは、
Security Contextを利用して
Authorizationを実行する。

Clientから送信されたRoleやPermissionを、
Security Contextの正本として扱わない。

---

# 9. Authentication Failure

Authenticationに失敗した場合、
APIへのAccessを許可しない。

例えば、

- Unauthenticated
- Invalid Credential
- Invalid Token
- Expired Session
- Revoked Session

など。

内部のAuthentication Detailを、
不要にClientへ公開しない。

---

# 10. Authorization

Authorizationは、
Authenticated Actorが
特定のOperationを実行できるかを判断する。

基本構造：

Person
↓
Scope
↓
Role
↓
Permission
↓
Use Case

Authorizationは、
Server Sideで必ず実行する。

---

# 11. Scope-based Data Isolation

StageArtでは、

**Userが所属・権限を持つScope以外のDataを見せない**

ことを基本原則とする。

より重要なのは、

**「見えない」のではなく、
「取得できない」**

という考え方である。

つまり、

Database
↓
全Data取得
↓
Frontend Filter
↓
所属外を非表示

ではなく、

Request
↓
Authentication
↓
Authorization
↓
Scope Resolution
↓
Scope内DataのみQuery
↓
Response

とする。

---

# 12. Organization Scope

Organizationは、
主要なTenant Boundaryである。

基本構造：

Person
↓
Organization Membership
↓
Organization

PersonがOrganizationに所属していない場合、
そのOrganizationのInternal Dataへ
Accessできない。

Organization Scopeは、
下位Scopeの基本Security Boundaryとなる。

---

# 13. Organization Membership

Organization Membershipによって、

Person
↓
Organization

の関係を定義する。

Membershipには、
必要に応じてRole / Permissionを
関連付ける。

Membershipが存在するだけで、
Organization内の全Operationを
許可するわけではない。

---

# 14. Project Scope

ProjectがOrganization配下に存在する場合、
Project ScopeをSecurity Boundaryとして扱う。

基本構造：

Organization
↓
Project
↓
Business Data

OrganizationへのAccessがあっても、
Project Scopeが必要なDataについては、
ProjectへのAccessを確認する。

---

# 15. Production Scope

Productionは、
Organization / Project配下の
主要なBusiness Scopeである。

基本構造：

Organization
↓
Project
↓
Production
↓
Production Delegate
↓
Person

ProductionにAccessできるPersonだけが、
そのProductionのInternal Dataを
取得・操作できる。

---

# 16. Production Delegate

Production Delegateは、
PersonとProductionの
Access関係を表す。

基本構造：

Person
↓
Production Delegate
↓
Production

Production Delegateには、
Role / Permissionを
関連付けることができる。

---

# 17. Performance Scope

Performanceは、
Production配下のOperation Scopeである。

基本構造：

Organization
↓
Project
↓
Production
↓
Performance

Performanceに関連するDataも、
親ProductionのAccess Scopeを
基本として制御する。

---

# 18. Scope Hierarchy

基本的なScope Hierarchy：

Organization
↓
Project
↓
Production
↓
Performance
↓
Business Data

例えば、

Performance
↓
Reservation
↓
Issued Ticket
↓
Check In

など。

親ScopeへのAccessがない場合、
子ScopeのDataにもAccessできない。

---

# 19. Scope Resolution

API Requestごとに、
必要なScopeを解決する。

例えば、

Ticket X

へのAccessでは、

Ticket
↓
Performance
↓
Production
↓
Project
↓
Organization

を解決する。

そのうえで、

Request Person
↓
Organization / Project / Production Scope

とのAccessを確認する。

---

# 20. Scope-aware Query

Queryは、
Request UserのScopeを考慮する。

例えば、

GET /productions

を実行した場合、

「すべてのProduction」

を返すのではなく、

「Request UserがAccess可能なProduction」

だけを返す。

---

# 21. Scope-aware Repository

Repository / Query Repositoryでは、
Scopeを考慮したQueryを実行する。

概念的には、

findProductions(scopeContext)

など。

Applicationから、

findAllProductions()

してからFrontendでFilterする設計を
基本としない。

---

# 22. Scope Filter at Database Query

可能な限り、
Scope FilterはDatabase Queryの段階で
適用する。

例えば、

User
↓
Authorized Organization IDs
↓
Authorized Production IDs
↓
Production Query
↓
Authorized Data

など。

具体的なSQLやORMは、
Implementation Specificationで定義する。

---

# 23. No Client-side Isolation

Client側で、

All Productions
↓
Frontend Filter
↓
Authorized Productions

という設計をSecurity対策として
採用しない。

Frontend Filterは、
UXのために利用できるが、
Security Boundaryではない。

---

# 24. Direct ID Access

Resource IDを知っているだけでは、
Accessを許可しない。

例えば、

GET /productions/999

としても、

Request User
↓
Production 999
↓
Authorization

が失敗する場合は、
Dataを返さない。

---

# 25. Enumeration Protection

Resource IDを順番に試すことで、
他UserのDataを推測できる構造を避ける。

例えば、

/productions/1
/productions/2
/productions/3

を順番にRequestするだけで
所属外Productionが取得できないようにする。

---

# 26. Not Found and Forbidden

Security上必要な場合、
Access対象外Resourceについて、

Forbidden

または

Not Found

として扱う。

どちらを返すかは、
Resourceの性質と情報漏洩リスクを
考慮して決定する。

---

# 27. Organization Isolation

Organization Aに所属するUserが、

Organization B

のDataを取得できないようにする。

対象：

- Project
- Production
- Performance
- Participant
- Reservation
- Ticket
- Check In
- Document
- Accounting
- Communication

など。

---

# 28. Project Isolation

同一Organization内でも、
Project Scopeが必要なDataについては、
別ProjectのDataを取得できないようにする。

例えば、

Project A
→ Access可能

Project B
→ Access不可

という状態をServer Sideで強制する。

---

# 29. Production Isolation

同一Organization / Project内でも、
Production Scopeが必要なDataについては、
Production外のDataを取得できないようにする。

例えば、

Production A
→ Access可能

Production B
→ Access不可

という状態をServer Sideで強制する。

---

# 30. Performance Isolation

Performance単位で
Access Controlが必要な場合も、
親Production Scopeから
Accessを確認する。

例えば、

Production A
↓
Performance A-1
↓
Reservation A-1

というDataに対して、

Production B User

がAccessできないようにする。

---

# 31. Personal Data Isolation

Personに関連するPersonal Dataについても、
Access Scopeを適用する。

例えば、

- Profile
- Contact Information
- HistoricalActivity
- Reservation
- Ticket
- Audience History

など。

「Person IDを知っている」
だけでは取得できない。

---

# 32. Profile Security

Profile Dataについては、
Data CategoryごとにVisibilityを定義する。

例えば、

Public Profile
→ Public Clientから参照可能

Internal Profile
→ Authorized Scopeのみ

Private Data
→ 本人または許可されたActorのみ

など。

---

# 33. HistoricalActivity Security

HistoricalActivityは、
Personに関連するDataであるため、
Scopeに応じてAccessを制御する。

所属していないOrganization / Productionの
HistoricalActivityを、
単純なPerson検索から取得できないようにする。

---

# 34. Reservation Security

Reservationは、
Performance / Production Scopeに
関連するBusiness Dataである。

Reservation IDを知っているだけで、
他Organization / Project / ProductionのReservationを
取得できないようにする。

---

# 35. Issued Ticket Security

Issued Ticketについても、
Scope-based Data Isolationを適用する。

Ticket Identifierを知っているだけで、
Ticket Detailを取得できる設計にしない。

---

# 36. Check In Security

Check Inでは、
以下を確認する。

- Authentication
- Authorization
- Organization Scope
- Project Scope
- Production Scope
- Performance Context
- Reservation
- Ticket Validity
- Ticket State
- Check In State
- Idempotency

QR Codeを持っているだけでは、
Check Inを許可しない。

---

# 37. Web Check In Security

Web Receptionでは、

Web Client
↓
Authentication
↓
Authorization
↓
Organization Scope
↓
Production / Performance Scope
↓
Reservation / Ticket Selection
↓
Check In API
↓
Server-side Validation
↓
Check In

というSecurity Flowを利用する。

Frontendで表示されているTicketでも、
Check In実行時にServer側で再確認する。

---

# 38. Web Check In List Security

Web Check In一覧は、
Request UserがAccess可能な
Reservation / Issued Ticketだけを返す。

例えば、

Performance A
→ Authorized

Performance B
→ Unauthorized

の場合、

Performance BのReservationを
一覧に含めない。

FrontendでFilterするのではなく、
Query段階でScopeを適用する。

---

# 39. Web Bulk Check In Security

複数Ticket / Reservationの
Bulk Check Inでも、
各対象についてAuthorizationを確認する。

Clientが、

Ticket A
Ticket B
Ticket C

を送信した場合、

Ticket A
→ Allowed

Ticket B
→ Forbidden

Ticket C
→ Allowed

という個別判定が必要になる場合がある。

Bulk Operationだからといって、
Scope Checkを省略しない。

---

# 40. Mobile QR Check In Security

Mobile Receptionでは、

Mobile Client
↓
Authentication
↓
Authorization
↓
Performance Context
↓
QR Scan
↓
Ticket Identifier
↓
Server Validation
↓
Reservation Resolution
↓
Check In

というSecurity Flowを利用する。

QR Payload自体を、
Authorization Tokenとして扱わない。

---

# 41. QR Code Security

QR Codeには、
必要最小限の情報だけを含める。

QR Payloadに、

- Password
- Secret
- Authentication Credential

などを含めない。

QRを読み取っただけで、
Sensitive Dataへ直接Accessできる設計にしない。

---

# 42. QR Replay Protection

同一QRのRepeated Useを考慮する。

例えば、

QR Scan
↓
Check In

後に再度Scanしても、

Already Checked In

などとして処理する。

二重Check Inを、
Frontendだけで防止しない。

---

# 43. Check In Concurrency Security

同じReservation / Ticketを、

Web Client

と

Mobile Client

から同時にCheck Inする可能性がある。

Backendでは、

Authentication
↓
Authorization
↓
Transaction
↓
Check In State
↓
Unique Constraint / Lock
↓
Check In

などによって、
二重受付を防止する。

---

# 44. Idempotency Security

重要なOperationでは、
同一RequestのRepeated Executionを
考慮する。

対象：

- Check In
- Reservation Confirmation
- Ticket Issuance
- Journal Entry
- Payment
- External Integration

など。

---

# 45. Replay Attack Protection

Authentication Token、
Webhook、
重要なCommandなどについて、
Replayを考慮する。

必要に応じて、

- Expiration
- Nonce
- Timestamp
- Idempotency Key
- Event ID

などを利用する。

---

# 46. Session Security

Session / Tokenは、
適切なExpirationとRevocationを
考慮する。

Sessionが失効した場合、
APIへのAccessを許可しない。

---

# 47. Token Security

Tokenは、
必要に応じて安全に管理する。

Tokenを、

- Log
- URL
- Error Message
- Analytics

などへ不要に出力しない。

---

# 48. Web Client Security

Web Clientでは、

- HTTPS
- Secure Session
- CSRF Protection
- XSS Protection
- Input Validation
- Output Encoding
- Content Security Policy

などを必要に応じて利用する。

具体的なWeb Security実装は、
Implementation Specificationで定義する。

---

# 49. Mobile Client Security

Mobile Clientでは、

- HTTPS
- Secure Token Storage
- TLS Validation
- Local Data Protection
- Device Security

などを考慮する。

Client内に、
Server Secretを保存しない。

---

# 50. API Security

APIでは、

- Authentication
- Authorization
- Input Validation
- Output Filtering
- Rate Limiting
- Error Sanitization
- Audit

を行う。

---

# 51. API Rate Limiting

必要に応じて、
API Rate Limitingを実施する。

特に、

- Login
- Authentication
- Public API
- QR Check In
- Search
- Webhook

など。

Rate Limitによって、
正常な受付業務を阻害しないようにする。

---

# 52. Input Validation

API Inputは、
複数LayerでValidationする。

API Layer：

Format Validation

Application：

Operation Validation

Domain：

Business Rule Validation

Security：

Authorization / Scope Validation

---

# 53. Output Filtering

Responseには、
Request UserがAccess可能なDataだけを
含める。

例えば、

Organization A User

に対して、

Organization B Data

をResponse DTOへ含めない。

---

# 54. Sensitive Data Minimization

Clientへ返すDataは、
必要最小限とする。

例えば、
Check In画面では、
受付に不要なPersonal Dataを
返さない。

---

# 55. Personal Data Protection

Personal Dataについては、
以下を考慮する。

- Collection Minimization
- Storage Minimization
- Access Control
- Encryption
- Logging Restriction
- Retention
- Deletion

具体的なRetention Policyは、
Data Governanceで定義する。

---

# 56. Personal Data in Logs

以下のようなSensitive Dataを、
不要にLogへ出力しない。

- Password
- Token
- Email
- Phone
- Address
- Payment Information
- Personal Identification Data

必要な場合は、
Masking / Hashingなどを検討する。

---

# 57. Encryption in Transit

ClientとServer、
またはExternal Serviceとの通信では、
HTTPS / TLSを基本とする。

対象：

- Authentication
- API
- Check In
- Accounting
- File
- External Integration

など。

---

# 58. Encryption at Rest

Sensitive Dataについては、
必要に応じてEncryption at Restを利用する。

対象例：

- Personal Data
- Credential
- Secret
- Backup
- Sensitive Documents

具体的なEncryption方式は、
Deployment / Infrastructure Specificationで定義する。

---

# 59. Password Security

Passwordを扱う場合、
Plain Textで保存しない。

Password Hashingは、
適切なSecurity Libraryを利用する。

具体的なAuthentication Providerを採用する場合は、
ProviderのSecurity Modelを利用する。

---

# 60. Secret Management

Secretは、
Application Source Codeへ
Hard Codeしない。

対象：

- API Key
- OAuth Secret
- Database Credential
- Encryption Key
- Webhook Secret
- Mail Credential

など。

---

# 61. External Integration Security

External ServiceとのIntegrationでは、

- Credential Protection
- TLS
- Signature Validation
- OAuth
- API Key Protection
- Replay Protection

などを考慮する。

---

# 62. Webhook Security

Webhookを受信する場合、

External Service
↓
Webhook
↓
Signature Validation
↓
Payload Validation
↓
Idempotency
↓
Application

という順序を基本とする。

Webhook Payloadを、
無条件にTrustしない。

---

# 63. Webhook Replay Protection

同一Webhook Eventが
複数回送信される可能性を考慮する。

External Event IDなどを利用して、
既に処理済みのEventを
二重処理しない。

---

# 64. File Security

Document / FileへのAccessも、
Scope-based Data Isolationを適用する。

例えば、

Organization Document
Production Document
Personal Document

など。

Document IDを知っているだけでは、
Downloadできない。

---

# 65. File Download Authorization

File Download時には、

Request User
↓
Document
↓
Organization / Project / Production Scope
↓
Permission

を確認する。

Authorizationに失敗した場合、
File Binaryを返さない。

---

# 66. File Upload Authorization

File Uploadでも、
UserがUpload対象Scopeへ
Accessできることを確認する。

例えば、

Production A User

が、

Production B

のDocumentへ
Uploadできないようにする。

---

# 67. Accounting Security

Accounting Dataは、
特に強いAccess Controlを必要とする。

対象：

- Ticket Revenue
- Journal Entry
- Accounting Summary
- Financial Report

など。

Accounting Scopeを持たないUserは、
Accounting DataへAccessできない。

---

# 68. Journal Entry Security

Journal Entryは、
直接APIから自由に変更できる設計を
基本としない。

Business Operationを通して、
AuthorizationとAccounting Ruleを
適用する。

---

# 69. Accounting and Check In

Check InからAccountingへ連動する場合、

Check In
↓
CheckInCompleted
↓
Accounting Process
↓
Journal Entry

とする。

Clientが、

Check In
↓
Journal Entry

を直接作成することを許可しない。

---

# 70. Audit

重要なOperationは、
Audit可能にする。

対象例：

- Login
- Authorization Failure
- Check In
- Ticket Issuance
- Reservation Confirmation
- Journal Entry
- Permission Change
- Document Access
- External Integration
- Organization Selection
- System Operation

---

# 71. Audit Context

Auditでは、
必要に応じて、

- Actor
- Person
- Organization
- Project
- Production
- Performance
- Resource
- Operation
- Timestamp
- Client
- Device
- Result

などを記録する。

---

# 72. Audit and Log Separation

AuditとTechnical Logを
分離する。

Audit：

「誰が何をしたか」

Technical Log：

「System内部で何が起きたか」

という違いを持たせる。

---

# 73. Authorization Failure Audit

重要なAuthorization Failureについて、
Audit / Security Logを
記録できる。

例えば、

User A
↓
Production B
↓
Access Denied

など。

ただし、
Sensitive DataをLogへ残しすぎない。

---

# 74. Security Monitoring

Security Monitoringでは、

- Failed Login
- Authorization Failure
- Unusual API Usage
- Repeated QR Scan
- Replay
- Webhook Failure
- Rate Limit Violation
- Suspicious Access

などを監視できる。

---

# 75. Suspicious Access

例えば、

短時間に、

Production A
Production B
Production C
Production D

へ大量Accessを試みるなど、
通常と異なるAccess Patternを
検知できる構造とする。

具体的なDetection Ruleは、
運用要件に応じて定義する。

---

# 76. Brute Force Protection

Authenticationでは、
Brute Force Attackを考慮する。

必要に応じて、

- Rate Limiting
- Temporary Lock
- CAPTCHA
- MFA
- Alert

などを利用する。

---

# 77. Multi-Factor Authentication

高権限Operationでは、
将来的にMFAを利用できる。

特に、

- Organization Administration
- Accounting
- Permission Management
- Security Configuration
- System Administration

など。

具体的なMFA Policyは、
Security Implementationで定義する。

---

# 78. Privilege Separation

重要な権限を、
必要以上に一人のRoleへ集中させない。

例えば、

- Organization Administration
- Accounting
- Permission Management
- System Operations

などを、
必要に応じて分離できる。

---

# 79. Least Privilege

User / Service / Integrationには、
必要最小限のPermissionだけを付与する。

例えば、

Notification Service

が、

Accounting Data

へAccessする必要がないなら、
そのPermissionを与えない。

---

# 80. Service Account Security

External Integrationで
Service Accountを利用する場合も、
Least Privilegeを適用する。

Service Accountには、
必要なAPI Scopeだけを与える。

---

# 81. Production Role Security

Production Roleは、
Production Scope内で
適用する。

例えば、

Production Manager

の権限が、

別ProductionのManager権限

になるわけではない。

---

# 82. Organization Role Security

Organization Roleは、
Organization Scope内で
適用する。

Organization A Administratorが、
Organization BのAdministratorになることはない。

---

# 83. Role and Permission

Roleは、
Permissionの集合として扱える。

例えば、

Production Manager
↓
Production Read
Production Write
Participant Management
Rehearsal Management
Performance Management
Check In Management

など。

具体的なRole Matrixは、
Authorization Specificationで定義する。

---

# 84. Permission Evaluation

Permission判定では、

Person
↓
Membership / Delegate
↓
Scope
↓
Role
↓
Permission
↓
Operation

というContextを利用する。

Clientから送信されたRoleを
Security Contextとして利用しない。

---

# 85. Permission Change

Role / Permission変更は、
重要なSecurity Operationとして扱う。

例えば、

Organization Administrator
↓
Permission Change
↓
Audit

など。

変更履歴を追跡可能にする。

---

# 86. Self Access

Person自身のDataについては、
必要に応じてSelf Accessを許可する。

例えば、

My Profile
My Reservations
My Tickets
My History

など。

ただし、

「本人だからすべてのDataを見られる」

とはしない。

---

# 87. Cross Person Access

他PersonのDataへのAccessは、
Scope / Role / Permissionによって
制御する。

Person IDを知っているだけでは、
他PersonのPrivate Dataを取得できない。

---

# 88. Public Data

Public Dataについては、
Authenticationなしで
Accessできる場合がある。

ただし、

Public

と

Internal

を明確に区別する。

---

# 89. Public Profile Security

Person Profileに
Public情報が存在する場合、

Public Profile
→ Public API

Internal Profile
→ Authorized Scope

Private Data
→ Self / Authorized Access

というようにData Categoryを分ける。

---

# 90. Search Security

Search APIでも、
Scope-based Data Isolationを適用する。

例えば、

「佐藤」

と検索した場合、

全OrganizationのPerson

を返すのではなく、

Request UserがAccess可能なPerson

だけを返す。

---

# 91. Search Enumeration Protection

Searchを利用して、
所属外Dataを推測できないようにする。

例えば、

Name Search
↓
全Person

という構造を禁止し、

Name Search
↓
Authorized Scope
↓
Matching Person

とする。

---

# 92. List API Security

List APIでも、
Scope Filterを適用する。

対象：

- Project List
- Production List
- Participant List
- Reservation List
- Ticket List
- Check In List
- Document List
- Accounting List

など。

---

# 93. Query Security

QueryがSecurity Scopeを
バイパスしないようにする。

ApplicationからQueryを呼び出す際に、

Request User
↓
Scope Context
↓
Authorized Query

という構造を維持する。

---

# 94. Background Job Security

Background Jobでも、
Scope Contextを必要に応じて保持する。

例えば、

Production A Notification

が、

Production B

のDataを処理しないようにする。

---

# 95. Queue Security

Queue Messageに、
必要以上のPersonal DataやSecretを
含めない。

Identifierを渡し、
Worker側でAuthorized Scopeを
再確認する方式を基本とする。

---

# 96. Integration Worker Security

Integration Workerも、
必要最小限のPermissionで動作させる。

例えば、

Email Worker

が、

Accounting Database

へ直接Accessする必要がないなら、
Accessさせない。

---

# 97. Background Authorization

Background Processでは、
User Sessionが存在しない場合がある。

その場合、

- System Actor
- Service Account
- Event Context

などを明確にする。

誰の権限で処理されたかを、
Audit可能にする。

---

# 98. Data Export Security

CSV / Excel / PDFなどのExportも、
Scope-based Data Isolationを適用する。

Export対象Dataは、

Request User
↓
Authorized Scope
↓
Export Query

で取得する。

全DataをExportしてから
FrontendでFilterする設計を禁止する。

---

# 99. Report Security

Report / Dashboardでも、
所属Scope外Dataを表示しない。

例えば、

Production A Manager

が、

Production B Revenue

をDashboardで閲覧できないようにする。

---

# 100. Aggregation Security

Aggregation Dataについても、
Scopeを適用する。

例えば、

Organization Revenue

Production Revenue

など。

所属外DataをAggregationへ
混ぜない。

---

# 101. Security and Cache

CacheにScope-sensitive Dataを
保存する場合、
Scopeを考慮する。

例えば、

Organization A User

が取得したDataを、

Organization B User

へ返さない。

Cache Keyへ必要なScope Contextを
含めるなどの対策を行う。

---

# 102. Security and Search Index

Search Indexについても、
Scopeを考慮する。

所属外DataがSearch Indexに存在しても、
Query結果へ返さない。

必要に応じて、
Scope FilterをIndex / Queryレベルで
適用する。

---

# 103. Security and File Cache

File / Document Cacheについても、
Authorizationを維持する。

Public URLとPrivate Documentを
混同しない。

---

# 104. Security and Backup

Backup Dataにも、
Production Dataと同等の
Securityを適用する。

対象：

- Database Backup
- File Backup
- Log Backup
- Audit Data

など。

BackupへのAccessは、
通常のBusiness Userには
原則として許可しない。

---

# 105. Security and Mirror

Mirror Environmentにも、
Primary Environmentと同等の
Security Controlを適用する。

Mirrorが存在することを理由に、
一般UserがMirror Dataへ
直接Accessできるようにしない。

---

# 106. Security and Recovery

Recovery Operationは、
高権限のSystem Operationとして扱う。

Recoveryでは、

- Authentication
- Authorization
- Operator Verification
- Audit
- Backup Validation
- Recovery Validation

などを必要に応じて実施する。

---

# 107. Data Retention

Security上、
不要になったDataを
無期限に保持しない。

Retention Policyは、
Data Governance / Legal Requirementに
応じて定義する。

---

# 108. Data Deletion

Data削除では、
Business Requirementと
Security Requirementを考慮する。

例えば、

Person Data
↓
Delete / Anonymize

など。

Accounting / Auditなど、
保持義務があるDataは
別Policyで扱う。

---

# 109. Security Incident

Security Incidentが発生した場合に、

- Detection
- Logging
- Isolation
- Investigation
- Recovery
- Notification

などを行える運用を
想定する。

具体的なIncident Response Planは、
Operational Securityで定義する。

---

# 110. WordPress Security

WordPressを利用する場合、

- WordPress Core
- Plugin
- Theme
- REST API
- Authentication
- User Permission

などのSecurityを考慮する。

WordPress User Permissionと
StageArt Permissionを
混同しない。

---

# 111. WordPress API Security

WordPress REST APIを利用する場合でも、
StageArt API側で必要なAuthorizationを
再確認する。

WordPress Endpointへ
Accessできることだけを理由に、
StageArt Business Operationを
許可しない。

---

# 112. WordPress Plugin Boundary

WordPress Plugin Codeと
StageArt Domainを分離する。

WordPress Hook
↓
Adapter
↓
Application
↓
Domain

というBoundaryを基本とする。

---

# 113. External Integration Security

External Integrationでは、

StageArt
↓
Integration Adapter
↓
External Service

の間で、

- TLS
- Credential
- Signature
- Scope
- Rate Limit
- Replay Protection

などを考慮する。

---

# 114. External Service Scope

External Service Credentialにも、
Least Privilegeを適用する。

例えば、

Calendar Integration

が、

Accounting Data

へAccessできるCredentialを
使用しない。

---

# 115. Security and Accounting

Accountingは、
高いConfidentialityと
Integrityを必要とする。

Accounting DataへのAccessは、
必要なRole / Permissionを持つ
Actorに限定する。

---

# 116. Security and Check In

Check Inは、
受付現場で多数のUser / Deviceが
利用する可能性があるため、

- Authentication
- Authorization
- Scope
- QR Validation
- Reservation Validation
- Ticket Validation
- Idempotency
- Concurrency

を組み合わせてSecurityを確保する。

---

# 117. Security and History

History Dataも、
Personに関連するBusiness Dataとして
Scopeを考慮する。

例えば、

Production A

に関連するHistoryを、

Production B

だけに所属するUserが
取得できないようにする。

---

# 118. Security and API Architecture

API Architectureでは、
API Boundaryを定義する。

Security Architectureでは、
そのBoundaryに対して、

Authentication
↓
Authorization
↓
Scope Isolation
↓
Operation Security

を適用する。

---

# 119. Security and Backend Architecture

Backendでは、

API
↓
Authentication
↓
Authorization
↓
Application
↓
Domain
↓
Repository

というSecurity Flowを基本とする。

Security Ruleを、
Frontendだけに配置しない。

---

# 120. Security and Data Architecture

Data Architectureでは、
Business Factを定義する。

Security Architectureでは、
そのBusiness Factへの
Access Boundaryを定義する。

基本構造：

Business Fact
↓
Scope
↓
Authorization
↓
Access

---

# 121. Security and Integration Architecture

External Integrationでは、
External SystemへのAccessと
External SystemからのData受信を
Security Boundaryとして扱う。

Webhookでは、

Signature
↓
Validation
↓
Replay Protection
↓
Authorization
↓
Application

というFlowを基本とする。

---

# 122. System Administrator Security

System Administratorは、
全Organizationを管理できる
高権限Actorとして扱う。

ただし、

System Administrator
=
無条件に全Business Dataへ直接Access可能

とはしない。

System Administratorによる
Business DataへのAccessは、
Organization Selectionを経由して
Scope Contextを明示する。

---

# 123. Organization Selection Security

System Administratorが
Organizationを選択する場合、

System Administrator
↓
Organization Selector
↓
Selected Organization Context
↓
Management API

というSecurity Flowを利用する。

選択対象Organizationが
存在することと、
そのOrganization Contextを
利用してBusiness Operationを行うことを
明確に分離する。

---

# 124. Selected Organization Context

Selected Organization Contextでは、

Selected Organization
↓
Organization Scope
↓
Project Scope
↓
Production Scope
↓
Performance Scope
↓
Business Operation

というScope Chainを利用する。

System Administratorが
Organization Aを選択している場合、

Organization A

を通常のManagement Scopeとして扱う。

---

# 125. System Administrator Business Access

System Administratorは、
Organizationを選択した後、

- Production Management
- Performance Management
- Rehearsal Management
- Participant Management
- Reservation Management
- Ticket Management
- Check In Management
- Communication
- Document Management
- Accounting

などの通常Management APIを
利用できる。

System Administrator専用の
重複Business APIを作らない。

---

# 126. System Administrator Permission Model

System Administratorが
Organizationを選択した場合、

System Administrator
↓
Selected Organization
↓
Organization Administrator相当のContext
↓
通常Management API

という構造を基本とする。

ただし、
System Administratorであることによって
Business RuleをBypassしない。

---

# 127. System Administrator Scope Switching

System Administratorが
Organization AからOrganization Bへ
Contextを切り替えた場合、

現在のSelected Organization Contextを
明確に更新する。

Organization AのScope Contextを
Organization BのOperationへ
誤って利用しない。

---

# 128. System Administrator Audit

System Administratorによる
Organization Selectionや
Business Operationは、
必要に応じてAuditする。

例えば、

System Administrator
↓
Organization A Selected
↓
Reservation Viewed
↓
Check In Executed

など。

誰が、
どのOrganization Contextで、
何を行ったかを追跡可能にする。

---

# 129. System Operations Security

以下のSystem Operationsは、
Business Managementとは
異なる高権限Boundaryとして扱う。

- Backup
- Restore
- Replication
- Mirror
- Failover
- Recovery
- System Health
- Operational Jobs

これらは、
通常のOrganization Userには
原則として公開しない。

---

# 130. Backup Access Control

BackupへのAccessは、
System Operations権限を持つActorに
限定する。

Backup Dataには、
Business Dataと同等またはそれ以上の
Security Controlを適用する。

---

# 131. Replication Access Control

Replication Configurationや
Replication Statusは、
System Operations権限を持つActorに
限定する。

Replicationの内部情報を、
一般Userへ公開しない。

---

# 132. Recovery Access Control

Recoveryは、
System Administratorの中でも
必要なPermissionを持つActorに
限定できる。

Recovery実行時には、
Auditを残す。

---

# 133. Least Privilege for System Operations

System Administratorであっても、
System Operationsのすべてを
無条件に実行可能としない構造を
必要に応じて採用する。

例えば、

System Monitoring
→ Read

Backup
→ Execute

Recovery
→ Restricted Execute

など。

---

# 134. Background Job Security

Background Jobでは、
User Sessionが存在しない場合でも、
処理Identityを明確にする。

例えば、

System Actor
Service Account
Event Context

など。

Background Jobが、
必要以上のScopeへ
Accessしないようにする。

---

# 135. Queue Security

Queue Messageに、
必要以上のPersonal DataやSecretを
含めない。

Identifierを渡し、
Worker側で必要なScopeを
再確認する方式を基本とする。

---

# 136. Background Job Scope

Background Jobが
Production Aに関連するJobの場合、

Job
↓
Production A Context
↓
Authorized Data

として処理する。

Production A Jobが、
Production B Dataを
処理しないようにする。

---

# 137. Service Account Scope

Service Accountにも、
必要最小限のScopeを与える。

例えば、

Email Worker
→ Notification Data

Calendar Worker
→ Schedule Data

Accounting Worker
→ Accounting Data

など。

すべてのService Accountに
全Data Accessを与えない。

---

# 138. Data Export Security

CSV / Excel / PDFなどのExportも、
Scope-based Data Isolationを適用する。

Export対象Dataは、

Request User
↓
Authorized Scope
↓
Export Query

で取得する。

全DataをExportしてから
FrontendでFilterする設計を禁止する。

---

# 139. Report Security

Report / Dashboardでも、
所属Scope外Dataを表示しない。

例えば、

Production A Manager

が、

Production B Revenue

をDashboardで閲覧できないようにする。

---

# 140. Aggregation Security

Aggregation Dataについても、
Scopeを適用する。

例えば、

Organization Revenue

Production Revenue

など。

所属外DataをAggregationへ
混ぜない。

---

# 141. Security and Cache

CacheにScope-sensitive Dataを
保存する場合、
Scopeを考慮する。

例えば、

Organization A User

が取得したDataを、

Organization B User

へ返さない。

Cache Keyへ必要なScope Contextを
含めるなどの対策を行う。

---

# 142. Security and Search Index

Search Indexについても、
Scopeを考慮する。

所属外DataがSearch Indexに存在しても、
Query結果へ返さない。

必要に応じて、
Scope FilterをIndex / Queryレベルで
適用する。

---

# 143. Security and File Cache

File / Document Cacheについても、
Authorizationを維持する。

Public URLとPrivate Documentを
混同しない。

---

# 144. Security and Public API

Public APIでは、
Internal Scopeを無条件に公開しない。

Public DataとInternal Dataを分離し、

Public API
↓
Public Projection
↓
Public DTO

という構造を利用する。

---

# 145. Security and Mobile Normal Mode

Mobile ClientのNormal Modeでは、
公演関係者がAccess可能なDataだけを
返す。

例えば、

- Production
- Performance
- Rehearsal
- Personal Schedule
- Communication

など。

Mobile Clientに
全OrganizationのDataを返さない。

---

# 146. Security and Mobile Reception Mode

Reception Modeは、
Mobile ClientのOperational Modeであり、
独立したSecurity Identityを持たない。

基本構造：

Authenticated Mobile User
↓
Normal Security Context
↓
Reception Permission
↓
Performance Scope
↓
Reception Mode

Reception Modeへ切り替えたことだけで、
権限を昇格させない。

---

# 147. Reception Permission

Reception Modeでは、
対象Performanceについて
受付Operationを実行できるPermissionを
Server Sideで確認する。

例えば、

User A
→ Performance A Reception Allowed

User A
→ Performance B Reception Denied

など。

---

# 148. QR Reception Security Boundary

QR Scanは、
Security Boundaryではなく
Input Methodとして扱う。

基本構造：

QR Scan
↓
Ticket Identifier
↓
Authorization
↓
Scope Validation
↓
Reservation Resolution
↓
Check In

QR Scanそのものが
Authorizationを代替しない。

---

# 149. Reservation Resolution Security

Reservation Resolutionでは、
解決対象Reservationが
Request UserのScope内にあることを確認する。

QR、

Reservation Number、

Booker Name、

Manual Selection

など、
どのResolution Methodでも
同じScope Ruleを適用する。

---

# 150. Booker Name Search Security

Booker Name検索では、
全OrganizationのReservationを
検索対象にしない。

基本構造：

Booker Name
↓
Authorized Scope
↓
Candidate Reservation
↓
Selection

とする。

Search結果自体に
Scope外Dataを含めない。

---

# 151. Manual Selection Security

Manual Selectionでは、
Performanceに紐づくReservation Listを
Authorized Scope内で取得する。

Clientが任意のReservation IDを
指定しても、
Server側でScopeを再確認する。

---

# 152. Check In Idempotency Security

同じCheck In Requestが
複数回送信されても、
Check In Factを不必要に重複生成しない。

例えば、

Request
↓
Timeout
↓
Retry
↓
Same Request

の場合でも、
Server側でIdempotencyを確認する。

---

# 153. Check In Concurrency Security

複数Clientが
同じReservationを同時に
Check Inする可能性を考慮する。

例えば、

Web Client
↓
Check In Reservation A

Mobile Client
↓
Check In Reservation A

が同時に実行されても、
最終的なCheck In Factを
不必要に二重作成しない。

---

# 154. Issued Ticket and Check In Security

Issued TicketとCheck Inを
同一Security Factとして扱わない。

Issued Ticketは、
Reservationを特定するための
Business Dataとして扱う。

Check Inは、
Reservationに対する
独立したBusiness Factである。

---

# 155. QR and Check In Security

QR Codeは、
Issued Ticket / Reservationを
ResolutionするためのInputである。

したがって、

QR Code
↓
Check In

という直接のSecurity Trust Chainを
作らない。

必ず、

QR Code
↓
Ticket Resolution
↓
Reservation
↓
Authorization
↓
Check In

とする。

---

# 156. External Ticket Security

External Ticketing Systemの
Ticketを利用する場合でも、

External Ticket
↓
External Reference
↓
Issued Ticket / Reservation Resolution
↓
Authorization
↓
Check In

というSecurity Flowを利用する。

External Ticket自体を、
Check In Authorization Credentialとして
無条件に信頼しない。

---

# 157. Payment Security

Payment ProviderとのIntegrationでは、

- Credential Protection
- Webhook Signature
- External Transaction ID
- Idempotency
- Replay Protection
- Scope

などを考慮する。

Payment Providerの成功通知だけで、
Clientから直接Business Stateを
変更できる構造にしない。

---

# 158. Accounting Integration Security

Accounting Integrationでは、

Check In
↓
CheckInCompleted
↓
Accounting Process
↓
Journal Entry
↓
External Accounting

というFlowを利用する。

External Accounting Credentialは、
Accounting Integrationに必要な
最小Scopeだけを持たせる。

---

# 159. Integration Failure Security

External Integrationが失敗しても、
すでに確定したBusiness Factを
不正に変更しない。

例えば、

Check In
→ Completed

Accounting
→ Failed

Calendar
→ Pending

Email
→ Retry

という状態を許容する。

---

# 160. Security and Business Fact

Security Failureと
Business Fact Failureを
分離する。

例えば、

Email Serviceが停止しても、

Reservation
→ Confirmed

を維持する。

External Service Failureを理由に、
Core Business Factを不必要にRollbackしない。

---

# 161. Security and Integration Credential

External Integration Credentialは、
Clientへ公開しない。

対象：

- API Key
- OAuth Secret
- Service Account
- Webhook Secret
- Database Credential
- Encryption Key

など。

---

# 162. Security and Backup

Backupには、
Production Dataと同等以上の
Securityを適用する。

Backup FileへのAccessは、
System Operations権限を持つActorに
限定する。

Backup URLなどを、
一般Userへ公開しない。

---

# 163. Security and Recovery Audit

Recovery実行時には、

- Actor
- Timestamp
- Backup
- Recovery Target
- Operation
- Result

などをAuditできるようにする。

---

# 164. Security Testing

Securityでは、
最低限以下をTestする。

- Unauthenticated Access
- Unauthorized Access
- Cross Organization Access
- Cross Project Access
- Cross Production Access
- Cross Person Access
- ID Enumeration
- Search Enumeration
- File Access
- Accounting Access
- Check In Authorization
- QR Replay
- Duplicate Request
- Webhook Replay
- Rate Limit
- Token Expiration
- System Administrator Scope
- Selected Organization Context

---

# 165. Scope Isolation Testing

Scope Isolationでは、
特に以下をTestする。

Organization A User
→ Organization A Data
→ Allowed

Organization A User
→ Organization B Data
→ Denied

Production A User
→ Production A Data
→ Allowed

Production A User
→ Production B Data
→ Denied

---

# 166. Query Isolation Testing

List / Search APIについても、
Scope IsolationをTestする。

例えば、

GET /productions

では、

Authorized Production
→ Returned

Unauthorized Production
→ Not Returned

となることを確認する。

---

# 167. Direct Resource Access Testing

Resource IDを直接指定した場合も、
Scope Isolationを確認する。

例えば、

GET /productions/authorized-id
→ Allowed

GET /productions/unauthorized-id
→ Denied / Not Found

など。

---

# 168. Check In Security Testing

Check Inでは、

Valid User
+ Valid Scope
+ Valid Ticket
→ Allowed

Valid User
+ Invalid Scope
+ Valid Ticket
→ Denied

Valid User
+ Valid Scope
+ Already Checked In
→ Already Checked In

Invalid User
+ Valid Ticket
→ Denied

などをTestする。

---

# 169. Web Check In Security Testing

Web Receptionでは、

- Authorized Performance
- Unauthorized Performance
- Authorized Ticket
- Unauthorized Ticket
- Direct Ticket ID Access
- Bulk Check In Scope
- Concurrent Check In
- Idempotent Retry

などをTestする。

---

# 170. Mobile QR Security Testing

Mobile QR Receptionでは、

- Valid QR
- Invalid QR
- Expired QR
- Already Used QR
- Wrong Performance
- Unauthorized User
- Replay
- Duplicate Request
- Concurrent Request

などをTestする。

---

# 171. System Administrator Security Testing

System Administratorでは、

- Organization List
- Organization Selection
- Selected Organization Context
- Organization A Access
- Organization B Access
- Context Switching
- Organization Scope Isolation
- Permission Evaluation
- Audit

などをTestする。

---

# 172. System Operations Security Testing

System Operationsでは、

- Backup Access
- Backup Download
- Replication Status
- Mirror Status
- Recovery Permission
- Unauthorized Recovery
- Recovery Audit
- System-wide Scope

などをTestする。

---

# 173. Security Monitoring

Security Monitoringでは、

- Authentication Failure
- Authorization Failure
- Scope Violation Attempt
- Rate Limit Violation
- Suspicious Search
- Repeated QR Request
- Webhook Failure
- External Credential Failure
- System Operation Failure

などを監視できる。

---

# 174. Security Architecture Summary

StageArt Securityは、

Authentication
↓
UserAccount
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
↓
Business Data

というSecurity Contextを基本とする。

特に重要なのは、

**「所属していないScopeのDataは見えない」**

ではなく、

**「所属していないScopeのDataは取得できない」**

という考え方である。

したがって、

Client
↓
API
↓
Authentication
↓
Authorization
↓
Scope Resolution
↓
Scope-aware Query
↓
Response

という構造を採用する。

Frontendで全Dataを取得して
所属外を隠す方式は、
Security Architectureとして採用しない。

---

# 175. Security Scope Hierarchy

StageArtでは、

Organization
↓
Project
↓
Production
↓
Performance
↓
Business Data

というScope Hierarchyを基本とする。

Business Dataには、

- Participant
- Rehearsal
- Reservation
- Issued Ticket
- Check In
- HistoricalActivity
- Document
- Accounting
- Communication

などが含まれる。

親ScopeへのAccessがない場合、
子ScopeへのAccessも許可しない。

---

# 176. System Administrator Security Model

System Administratorについては、

System Administrator
↓
Organization Selector
↓
Selected Organization Context
↓
Organization Scope
↓
Project / Production / Performance Scope
↓
通常Management API

という構造を採用する。

System Administratorは、
全Organizationを一覧で確認できる。

ただし、
Organizationを選択した後の
Business Data Accessは、
Selected Organization Contextを通じて
通常のScope Controlを適用する。

---

# 177. System Administrator and Business Rule

System Administratorであることを理由に、

- Reservation State
- Ticket State
- Check In Rule
- Accounting Rule
- Production Rule
- Permission Rule

などを無条件にBypassしない。

System Administratorは、
通常Management APIを利用するが、
Business Ruleそのものは
通常Userと同じApplication / Domainを通る。

---

# 178. Reception Security Model

Receptionは、
独立したAuthentication Boundaryではない。

Mobile Client
↓
Normal Authentication
↓
Reception Permission
↓
Performance Scope
↓
Reception Mode

という構造を採用する。

Reception Modeに切り替えたことで、
Userの権限を自動的に昇格させない。

---

# 179. Common Check In Security Model

WebとMobileでは、
受付入口が異なる。

Web：

Web Client
↓
Reservation / Issued Ticket List
↓
Reservation Resolution
↓
Check In

Mobile：

Mobile Client
↓
QR Scan
↓
Ticket Resolution
↓
Reservation Resolution
↓
Check In

しかし、
Security RuleとCheck In Business Ruleは
共通とする。

---

# 180. Check In Security Chain

Check InのSecurity Chainは、

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
Reservation Resolution
↓
Ticket Validation
↓
Check In State
↓
Idempotency
↓
Concurrency Control
↓
Check In Business Fact

を基本とする。

---

# 181. External Integration Security Model

External Integrationでは、

StageArt
↓
Integration Interface
↓
Adapter
↓
Credential / Signature
↓
External Service

というSecurity Boundaryを維持する。

External ServiceのCredentialを
DomainやClientへ渡さない。

---

# 182. Business Fact and Security Failure

External Service Failureや
Security Failureが発生した場合でも、
すでに確定したBusiness Factを
不正に変更しない。

例えば、

Check In
→ Completed

Notification
→ Failed

Accounting Integration
→ Pending

という状態を許容する。

---

# 183. Security and Data Architecture

Data Architectureでは、
Business FactのOwnershipを定義する。

Security Architectureでは、
そのBusiness FactへのAccess Boundaryを
定義する。

例えば、

Reservation
→ Reservation Domain

Check In
→ Check In Domain

Accounting
→ Accounting Domain

というOwnershipに対して、

Authentication
↓
Authorization
↓
Scope
↓
Permission

を適用する。

---

# 184. Security and API Architecture

API Architectureでは、
ClientからApplicationへの
API Boundaryを定義する。

Security Architectureでは、

Authentication
↓
Authorization
↓
Scope
↓
Operation Security

をAPI Boundaryへ適用する。

APIを直接呼び出せること自体が、
Business DataへのAccess権限にはならない。

---

# 185. Security and Application Architecture

Application Architectureでは、
Use CaseとBusiness Operationを定義する。

Security Architectureでは、
Use Case実行前に、

- Authentication
- Authorization
- Scope
- Permission

を評価する。

ClientがApplication内部の
Business RuleをBypassできない構造とする。

---

# 186. Security and Integration Architecture

Integration Architectureでは、
External SystemとのBoundaryを定義する。

Security Architectureでは、

- Credential
- Signature
- Scope
- Replay Protection
- Least Privilege
- Data Minimization

を適用する。

---

# 187. Security and Operations Architecture

Operationsでは、

- Backup
- Restore
- Replication
- Mirror
- Recovery
- Monitoring

を管理する。

Security Architectureでは、
これらを高権限System Operationとして扱う。

一般Business Userが、
System Infrastructureへ
直接Accessできない構造とする。

---

# 188. Security Architecture Rules

Security Architectureでは、
以下を禁止または原則禁止とする。

- FrontendだけでAuthorizationを行うこと
- Clientから送信されたRoleをTrustすること
- Clientから送信されたPermissionをTrustすること
- Resource IDだけでAccessを許可すること
- 全Dataを取得してFrontendでFilterすること
- Organization Scopeを無視すること
- Project Scopeを無視すること
- Production Scopeを無視すること
- Performance Scopeを無視すること
- QR CodeだけでCheck Inを許可すること
- Issued TicketをCheck In Factと同一視すること
- External Ticketを無条件にTrustすること
- SecretをClientへ渡すこと
- SecretをSource CodeへHard Codeすること
- PasswordをPlain Textで保存すること
- Sensitive Dataを不要にLogへ出力すること
- Scope外DataをSearch結果へ返すこと
- Scope外DataをExportすること
- Scope外DataをCacheから返すこと
- Scope外DataをAggregationへ混ぜること
- External Integration FailureでBusiness Factを不正にRollbackすること
- System AdministratorだからといってBusiness RuleをBypassすること
- System Operationsを一般Business Userへ公開すること
- Background Jobに無制限のData Accessを与えること
- Service Accountに不要なPermissionを与えること

---

# 189. Security Architecture Principle

StageArt Securityの最重要原則：

**「認証されたUserであっても、
自分が所属・権限を持つScope以外のDataには
Accessできない。」**

そして、

**「Access制御はUIで行うのではなく、
Server SideのQuery / Application / Domain Boundaryで行う。」**

さらに、

**「Resource IDを知っていること、
QR Codeを持っていること、
APIを直接呼び出せることだけでは、
Business DataへのAccessを許可しない。」**

という原則を採用する。

StageArtでは、

Authentication
↓
Authorization
↓
Scope-based Data Isolation
↓
Business Operation
↓
Business Fact

というSecurity Boundaryを維持する。

これにより、

- Organization間のData Leakage
- Project間のData Leakage
- Production間のData Leakage
- Person Data Leakage
- Ticket Data Leakage
- Accounting Data Leakage
- Document Data Leakage
- QR Replay
- API Direct Access
- Search Enumeration
- ID Enumeration
- Unauthorized Check In
- Unauthorized System Operation

などをArchitectureレベルで防止できる
Security Architectureを実現する。

---

# 190. Final Security Principle

StageArtのSecurity Architectureは、
単純な「ログインできる / できない」の
Security Modelではない。

最終的には、

誰であるか
↓
何に所属しているか
↓
どのScopeにAccessできるか
↓
どのRoleを持つか
↓
どのPermissionを持つか
↓
何のOperationを実行するか
↓
どのBusiness DataへAccessするか

をServer Sideで一貫して評価する。

特に、

**Organization**

**Project**

**Production**

**Performance**

というScopeをSecurity Boundaryとして維持する。

System Administratorについても、

System Administrator
↓
Organization Selection
↓
Selected Organization Context
↓
通常のScope Resolution
↓
通常Management API

という構造を採用する。

Mobile Receptionについても、

Mobile Authentication
↓
Reception Permission
↓
Performance Scope
↓
QR / Manual Resolution
↓
Check In Authorization
↓
Check In

という構造を採用する。

Web Receptionについても、

Web Authentication
↓
Reception Permission
↓
Performance Scope
↓
Reservation / Ticket Selection
↓
Check In Authorization
↓
Check In

という構造を採用する。

そして最終的なCheck Inは、

Reservation
↓
Check In

というBusiness Relationshipを維持し、

QR Code、
Issued Ticket、
External Ticket、
Mobile Camera、
Web Client

などを、
Check In Business Factそのものとしない。

Security Architectureの最終原則は、

**「Clientを信用せず、
ScopeをServerで解決し、
Business OperationをAuthorizationした上で、
Business Factを確定する。」**

ことである。
