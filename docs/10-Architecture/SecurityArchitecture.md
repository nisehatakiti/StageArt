# StageArt Blueprint

# 10 - Architecture
# Security Architecture

Version : 1.0

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
- Production Scope
- Performance Scope
- Role
- Permission
- Scope-based Data Isolation
- Tenant Isolation
- API Security
- Web Security
- Mobile Security
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
- Organization / Production Scopeを明確にする。
- Business Factの正本をClientに置かない。
- QR CodeをSecurity Credentialそのものとして無条件に信頼しない。
- External Service CredentialをClientへ渡さない。
- SecretをSource CodeへHard Codeしない。
- Sensitive Dataを必要以上に保存・表示・Loggingしない。
- Important OperationをAudit可能にする。
- Security FailureをBusiness Factの不整合へつなげない。
- Web ClientとMobile ClientでSecurity Ruleを分けない。
- External IntegrationをSecurity Boundaryの外側として扱う。

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

---

# 3. Zero Trust Principle

Clientから送信されたDataを、
無条件に信頼しない。

例えば、

- User ID
- Person ID
- Organization ID
- Production ID
- Performance ID
- Ticket ID
- Role
- Permission
- Price
- Status

など。

Server側で、
Authentication Contextと
Domain Dataから再検証する。

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

重要な原則：

「見えない」のではなく、
「取得できない」。

つまり、

❌

Database
↓
全Data取得
↓
Frontend Filter
↓
所属外を非表示

ではなく、

⭕️

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

---

# 14. Production Scope

Productionは、
Organization配下の主要なBusiness Scopeである。

基本構造：

Organization
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

# 15. Production Delegate

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

# 16. Performance Scope

Performanceは、
Production配下のOperation Scopeである。

基本構造：

Organization
↓
Production
↓
Performance

Performanceに関連するDataも、
親ProductionのAccess Scopeを
基本として制御する。

---

# 17. Scope Hierarchy

基本的なScope Hierarchy：

Organization
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

# 18. Scope Resolution

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
Organization

を解決する。

そのうえで、

Request Person
↓
Organization / Production Scope

とのAccessを確認する。

---

# 19. Scope-aware Query

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

# 20. Scope-aware Repository

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

# 21. Scope Filter at Database Query

可能な限り、
Scope FilterはDatabase Queryの段階で
適用する。

例えば、

User
↓
Authorized Organization IDs
↓
Production Query
↓
WHERE organization_id IN (...)

など。

具体的なSQLやORMは、
Implementation Specificationで定義する。

---

# 22. No Client-side Isolation

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

# 23. Direct ID Access

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

# 24. Enumeration Protection

Resource IDを順番に試すことで、
他UserのDataを推測できる構造を避ける。

例えば、

/productions/1
/productions/2
/productions/3

を順番にRequestするだけで
所属外Productionが取得できないようにする。

---

# 25. Not Found and Forbidden

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

# 26. Organization Isolation

Organization Aに所属するUserが、

Organization B

のDataを取得できないようにする。

対象：

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

# 27. Production Isolation

同一Organization内でも、
Production Scopeが必要なDataについては、
Production外のDataを取得できないようにする。

例えば、

Production A
→ Access可能

Production B
→ Access不可

という状態をServer Sideで強制する。

---

# 28. Performance Isolation

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

# 29. Personal Data Isolation

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

# 30. Profile Security

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

# 31. HistoricalActivity Security

HistoricalActivityは、
Personに関連するDataであるため、
Scopeに応じてAccessを制御する。

所属していないOrganization / Productionの
HistoricalActivityを、
単純なPerson検索から取得できないようにする。

---

# 32. Reservation Security

Reservationは、
Performance / Production Scopeに
関連するBusiness Dataである。

Reservation IDを知っているだけで、
他Organization / ProductionのReservationを
取得できないようにする。

---

# 33. Issued Ticket Security

Issued Ticketについても、
Scope-based Data Isolationを適用する。

Ticket Identifierを知っているだけで、
Ticket Detailを取得できる設計にしない。

---

# 34. Check In Security

Check Inでは、
以下を確認する。

- Authentication
- Authorization
- Organization Scope
- Production Scope
- Performance Context
- Ticket Validity
- Ticket State

QR Codeを持っているだけでは、
Check Inを許可しない。

---

# 35. Web Check In Security

Web Receptionでは、

Web Client
↓
Authentication
↓
Authorization
↓
Performance Scope
↓
Ticket Selection
↓
Check In API
↓
Check In

というSecurity Flowを利用する。

Frontendで表示されているTicketでも、
Check In実行時にServer側で再確認する。

---

# 36. Mobile QR Check In Security

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
Check In

というSecurity Flowを利用する。

QR Payload自体を、
Authorization Tokenとして扱わない。

---

# 37. QR Code Security

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

# 38. QR Replay Protection

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

# 39. Check In Concurrency Security

同じTicketを、

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

などによって、
二重受付を防止する。

---

# 40. Idempotency Security

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

# 41. Replay Attack Protection

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

# 42. Session Security

Session / Tokenは、
適切なExpirationとRevocationを
考慮する。

Sessionが失効した場合、
APIへのAccessを許可しない。

---

# 43. Token Security

Tokenは、
必要に応じて安全に管理する。

Tokenを、

- Log
- URL
- Error Message
- Analytics

などへ不要に出力しない。

---

# 44. Web Client Security

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

# 45. Mobile Client Security

Mobile Clientでは、

- HTTPS
- Secure Token Storage
- Certificate / TLS Validation
- Local Data Protection
- Device Security

などを考慮する。

Client内に、
Server Secretを保存しない。

---

# 46. API Security

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

# 47. API Rate Limiting

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

# 48. Input Validation

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

# 49. Output Filtering

Responseには、
Request UserがAccess可能なDataだけを
含める。

例えば、

Organization A User

に対して、

Organization B Data

をResponse DTOへ含めない。

---

# 50. Sensitive Data Minimization

Clientへ返すDataは、
必要最小限とする。

例えば、

Check In画面では、
受付に不要なPersonal Dataを
返さない。

---

# 51. Personal Data Protection

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

# 52. Personal Data in Logs

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

# 53. Encryption in Transit

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

# 54. Encryption at Rest

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

# 55. Password Security

Passwordを扱う場合、
Plain Textで保存しない。

Password Hashingは、
適切なSecurity Libraryを利用する。

具体的なAuthentication Providerを採用する場合は、
ProviderのSecurity Modelを利用する。

---

# 56. Secret Management

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

# 57. External Integration Security

External ServiceとのIntegrationでは、

- Credential Protection
- TLS
- Signature Validation
- OAuth
- API Key Protection
- Replay Protection

などを考慮する。

---

# 58. Webhook Security

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

# 59. Webhook Replay Protection

同一Webhook Eventが
複数回送信される可能性を考慮する。

External Event IDなどを利用して、
既に処理済みのEventを
二重処理しない。

---

# 60. File Security

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

# 61. File Download Authorization

File Download時には、

Request User
↓
Document
↓
Organization / Production Scope
↓
Permission

を確認する。

Authorizationに失敗した場合、
File Binaryを返さない。

---

# 62. File Upload Authorization

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

# 63. Accounting Security

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

# 64. Journal Entry Security

Journal Entryは、
直接APIから自由に変更できる設計を
基本としない。

Business Operationを通して、
AuthorizationとAccounting Ruleを
適用する。

---

# 65. Accounting and Check In

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

# 66. Audit

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

---

# 67. Audit Context

Auditでは、
必要に応じて、

- Actor
- Person
- Organization
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

# 68. Audit and Log Separation

AuditとTechnical Logを
分離する。

Audit：

「誰が何をしたか」

Technical Log：

「System内部で何が起きたか」

という違いを持たせる。

---

# 69. Authorization Failure Audit

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

# 70. Security Monitoring

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

# 71. Suspicious Access

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

# 72. Brute Force Protection

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

# 73. Multi-Factor Authentication

高権限Operationでは、
将来的にMFAを利用できる。

特に、

- Organization Administration
- Accounting
- Permission Management
- Security Configuration

など。

具体的なMFA Policyは、
Security Implementationで定義する。

---

# 74. Privilege Separation

重要な権限を、
必要以上に一人のRoleへ集中させない。

例えば、

- Organization Administration
- Accounting
- Permission Management

などを、
必要に応じて分離できる。

---

# 75. Least Privilege

User / Service / Integrationには、
必要最小限のPermissionだけを付与する。

例えば、

Notification Service

が、

Accounting Data

へAccessする必要がないなら、
そのPermissionを与えない。

---

# 76. Service Account Security

External Integrationで
Service Accountを利用する場合も、
Least Privilegeを適用する。

Service Accountには、
必要なAPI Scopeだけを与える。

---

# 77. Production Role Security

Production Roleは、
Production Scope内で
適用する。

例えば、

Production Manager

の権限が、

別ProductionのManager権限

になるわけではない。

---

# 78. Organization Role Security

Organization Roleは、
Organization Scope内で
適用する。

Organization A Administratorが、
Organization BのAdministratorになることはない。

---

# 79. Role and Permission

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

# 80. Permission Evaluation

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

# 81. Permission Change

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

# 82. Self Access

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

# 83. Cross Person Access

他PersonのDataへのAccessは、
Scope / Role / Permissionによって
制御する。

Person IDを知っているだけでは、
他PersonのPrivate Dataを取得できない。

---

# 84. Public Data

Public Dataについては、
Authenticationなしで
Accessできる場合がある。

ただし、

Public

と

Internal

を明確に区別する。

---

# 85. Public Profile Security

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

# 86. Search Security

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

# 87. Search Enumeration Protection

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

# 88. List API Security

List APIでも、
Scope Filterを適用する。

対象：

- Production List
- Participant List
- Reservation List
- Ticket List
- Check In List
- Document List
- Accounting List

など。

---

# 89. Query Security

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

# 90. Background Job Security

Background Jobでも、
Scope Contextを必要に応じて保持する。

例えば、

Production A Notification

が、

Production B

のDataを処理しないようにする。

---

# 91. Queue Security

Queue Messageに、
必要以上のPersonal DataやSecretを
含めない。

Identifierを渡し、
Worker側でAuthorized Scopeを
再確認する方式を基本とする。

---

# 92. Integration Worker Security

Integration Workerも、
必要最小限のPermissionで動作させる。

例えば、

Email Worker

が、

Accounting Database

へ直接Accessする必要がないなら、
Accessさせない。

---

# 93. Background Authorization

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

# 94. Data Export Security

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

# 95. Report Security

Report / Dashboardでも、
所属Scope外Dataを表示しない。

例えば、

Production A Manager

が、

Production B Revenue

をDashboardで閲覧できないようにする。

---

# 96. Aggregation Security

Aggregation Dataについても、
Scopeを適用する。

例えば、

Organization Revenue

Production Revenue

など。

所属外DataをAggregationへ
混ぜない。

---

# 97. Security and Cache

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

# 98. Security and Search Index

Search Indexについても、
Scopeを考慮する。

所属外DataがSearch Indexに存在しても、
Query結果へ返さない。

必要に応じて、
Scope FilterをIndex / Queryレベルで
適用する。

---

# 99. Security and File Cache

File / Document Cacheについても、
Authorizationを維持する。

Public URLとPrivate Documentを
混同しない。

---

# 100. Security and Backup

Backup Dataにも、
Production Dataと同等の
Securityを適用する。

対象：

- Database Backup
- File Backup
- Log Backup
- Audit Data

など。

---

# 101. Data Retention

Security上、
不要になったDataを
無期限に保持しない。

Retention Policyは、
Data Governance / Legal Requirementに
応じて定義する。

---

# 102. Data Deletion

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

# 103. Security Incident

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

# 104. WordPress Security

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

# 105. WordPress API Security

WordPress REST APIを利用する場合でも、
StageArt API側で必要なAuthorizationを
再確認する。

WordPress Endpointへ
Accessできることだけを理由に、
StageArt Business Operationを
許可しない。

---

# 106. WordPress Plugin Boundary

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

# 107. External Integration Security

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

# 108. External Service Scope

External Service Credentialにも、
Least Privilegeを適用する。

例えば、

Calendar Integration

が、

Accounting Data

へAccessできるCredentialを
使用しない。

---

# 109. Security and Accounting

Accountingは、
高いConfidentialityと
Integrityを必要とする。

Accounting DataへのAccessは、
必要なRole / Permissionを持つ
Actorに限定する。

---

# 110. Security and Check In

Check Inは、
受付現場で多数のUser / Deviceが
利用する可能性があるため、

- Authentication
- Authorization
- Scope
- QR Validation
- Ticket Validation
- Idempotency
- Concurrency

を組み合わせてSecurityを確保する。

---

# 111. Security and History

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

# 112. Security and API Architecture

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

# 113. Security and Backend Architecture

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

# 114. Security and Data Architecture

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

# 115. Security and Integration Architecture

External Integrationでは、
External SystemへのAccessと
External SystemからのData受信を
Security Boundaryとして扱う。

Webhookでは、

Signature
↓
Validation
↓
Authorization
↓
Application

というFlowを基本とする。

---

# 116. Security Testing

Securityでは、
最低限以下をTestする。

- Unauthenticated Access
- Unauthorized Access
- Cross Organization Access
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

---

# 117. Scope Isolation Testing

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

# 118. Query Isolation Testing

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

# 119. Direct Resource Access Testing

Resource IDを直接指定した場合も、
Scope Isolationを確認する。

例えば、

GET /productions/authorized-id
→ Allowed

GET /productions/unauthorized-id
→ Denied / Not Found

など。

---

# 120. Check In Security Testing

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

# 121. Web Check In Security Testing

Web Receptionでは、

- Authorized Performance
- Unauthorized Performance
- Authorized Ticket
- Unauthorized Ticket
- Direct Ticket ID Access
- Bulk Check In Scope
- Concurrent Check In

などをTestする。

---

# 122. Mobile QR Security Testing

Mobile QR Receptionでは、

- Valid QR
- Invalid QR
- Expired QR
- Already Used QR
- Wrong Performance
- Unauthorized User
- Replay
- Duplicate Request

などをTestする。

---

# 123. Security Monitoring

Security Monitoringでは、

- Authentication Failure
- Authorization Failure
- Scope Violation Attempt
- Rate Limit Violation
- Suspicious Search
- Repeated QR Request
- Webhook Failure
- External Credential Failure

などを監視できる。

---

# 124. Security Architecture Summary

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

また、

Organization
↓
Production
↓
Performance
↓
Business Data

というScope Hierarchyを基本とし、

- Organization
- Production
- Performance
- Person
- Reservation
- Issued Ticket
- Check In
- HistoricalActivity
- Document
- Accounting

などのDataに対して、
必要なScope Isolationを適用する。

Check Inでは、

Web / Mobile
↓
Authentication
↓
Authorization
↓
Scope Validation
↓
Ticket Validation
↓
Check In

というSecurity Flowを利用する。

QR Codeを持っていることだけでは
Check Inを許可しない。

さらに、

Check In
↓
CheckInCompleted
├── History
├── Accounting
└── External Integration

というBusiness Flowにおいて、
External ServiceのSecurity Failureが
Check In Factそのものを
不正に変更しない構造とする。

---

# 125. Security Architecture Principle

StageArt Securityの最重要原則：

「認証されたUserであっても、
自分が所属・権限を持つScope以外のDataには
Accessできない。」

そして、

「Access制御はUIで行うのではなく、
Server SideのQuery / Application / Domain Boundaryで行う。」

さらに、

「Resource IDを知っていること、
QR Codeを持っていること、
APIを直接呼び出せることだけでは、
Business DataへのAccessを許可しない。」

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
- Production間のData Leakage
- Person Data Leakage
- Ticket Data Leakage
- Accounting Data Leakage
- Document Data Leakage
- QR Replay
- API Direct Access
- Search Enumeration
- ID Enumeration

などをArchitectureレベルで防止できる
Security Architectureを実現する。

---
