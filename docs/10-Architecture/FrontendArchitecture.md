# StageArt Blueprint

# 10 - Architecture
# Frontend Architecture

Version : 1.0

---

# Purpose

Frontend Architectureは、
StageArtにおけるClient Applicationの構造と責務を定義する。

Frontend Architectureでは、

- Web Client
- Mobile Client
- Public Client
- Management Client
- Reception Client
- UI
- Navigation
- State Management
- API Communication
- Authentication
- Authorization Context
- Check In
- QR Reception
- Web Reception
- Form
- Validation
- Error Handling
- Offline
- Responsive Design
- Accessibility
- Client Security

を定義する。

Frontend Architectureでは、
具体的なFrontend Frameworkや
Component Libraryの選定までは確定しない。

---

# 1. Frontend Architecture Principles

StageArt Frontendは、
以下を基本原則とする。

- FrontendはPresentationとUser Interactionを担当する。
- Business RuleをFrontendに持たせない。
- Business FactをFrontend側で確定しない。
- APIを通してApplicationを利用する。
- Domain EntityをFrontendの内部Modelと直接同一視しない。
- Server SideをBusiness FactのSource of Truthとする。
- AuthenticationとAuthorizationの最終判断はServer Sideで行う。
- Web ClientとMobile ClientでBusiness Ruleを分けない。
- Client固有の操作方法とBusiness Operationを分離する。
- UI StateとBusiness Stateを分離する。
- API ResponseをそのままUIへ依存させない。
- Network ErrorとBusiness Errorを区別する。
- Client側のCacheはSource of Truthとしない。
- 将来的なClient追加を妨げない構造とする。
- Accessibilityを考慮する。
- Responsive Designを基本とする。
- Check InはWebとMobileの双方から実行可能とする。
- Web Check Inは一覧操作を基本とする。
- Mobile Check InはQR Scanを基本とする。
- WebとMobileのCheck Inは同一API / Application Use Caseを利用する。

---

# 2. Frontend Client Types

StageArtでは、
用途に応じて複数のClientを持つことができる。

主なClient：

- Web Client
- Mobile Client
- Public Client
- Management Client
- Reception Client

Clientは、
すべて同じUIを持つ必要はない。

ただし、
同じBusiness Operationについては、
同じServer Side Business Ruleを利用する。

---

# 3. Web Client

Web Clientは、
Browser上で動作するClientである。

主な用途：

- Organization Management
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

Web Clientは、
Management機能だけでなく、
Reception機能も提供できる。

---

# 4. Mobile Client

Mobile Clientは、
Smartphoneなどで利用するClientである。

主な用途：

- Authentication
- Personal Information
- Production Information
- Rehearsal
- Participant Operation
- Ticket
- Reservation
- Check In
- QR Reception

Mobile Clientは、
Device機能を利用できる。

例えば、

- Camera
- QR Scanner
- Notification
- Local Storage

など。

ただし、
Device機能をBusiness Ruleの代わりにしない。

---

# 5. Public Client

Public Clientは、
一般利用者向けのClientである。

主な用途：

- Organization Public Profile
- Production Public Page
- Performance Information
- Ticket Information
- Public Announcement
- Public Survey

Public Clientから、
Internal Management Dataへ直接アクセスしない。

---

# 6. Management Client

Management Clientは、
OrganizationやProductionを
管理するためのClientである。

主な機能：

- Organization Management
- Membership Management
- Production Management
- Participant Management
- Rehearsal Management
- Performance Management
- Ticket Management
- Reservation Management
- Check In Management
- Accounting Management

Management Clientは、
Authorization Scope内のDataのみを扱う。

---

# 7. Reception Client

Reception Clientは、
公演当日の受付操作を行うためのClientである。

Reception Clientは、
Web Client上で提供することも、
Mobile Client上で提供することもできる。

基本的な受付方式：

Web Reception
→ 一覧からTicketを選択してCheck In

Mobile Reception
→ QR CodeをScanしてCheck In

どちらも同じCheck In Business Operationを利用する。

---

# 8. Frontend Layer

Frontendは、
概念的に以下のLayerへ分離する。

UI
↓
Presentation
↓
Application Client
↓
API Client
↓
HTTP / Network

Domain Business Ruleは、
Frontend Layerに持ち込まない。

---

# 9. UI Layer

UI Layerは、
画面表示とUser Interactionを担当する。

責務：

- Display
- Input
- Button
- Form
- Dialog
- List
- Navigation
- Loading
- Error Display

UI Layerは、
Business Ruleを判断しない。

例えば、

「このTicketはCheck In可能か」

をUIだけで確定しない。

Serverから返されたBusiness Resultを表示する。

---

# 10. Presentation Layer

Presentation Layerは、
UIとApplication Clientの間を担当する。

責務：

- View Model
- Screen State
- Form State
- Loading State
- Error State
- Navigation State

Presentation Layerは、
API Responseを
そのままUIへ渡さず、
必要に応じてView ModelへMappingする。

---

# 11. Application Client Layer

Application Client Layerは、
FrontendからApplication Operationを
呼び出すためのClient側Boundaryである。

例えば、

CheckInClient
ReservationClient
ProductionClient
RehearsalClient

など。

このLayerは、
HTTP通信そのものを
UIへ露出させない。

---

# 12. API Client Layer

API Client Layerは、
StageArt APIとの通信を担当する。

責務：

- HTTP Request
- HTTP Response
- Authentication Token
- Serialization
- Deserialization
- API Error Mapping
- Retry
- Timeout

API Clientは、
Business Ruleを実装しない。

---

# 13. Network Layer

Network Layerは、
外部通信を担当する。

基本構造：

UI
↓
Presentation
↓
Application Client
↓
API Client
↓
Network
↓
StageArt API

Network Layerは、
HTTP Clientや通信Libraryなどの
Technical Concernを担当する。

---

# 14. Frontend Model

Frontendでは、
以下のDataを区別する。

- API DTO
- View Model
- UI State
- Local State
- Cache Data

これらを、
StageArt Domain Entityと同一視しない。

---

# 15. API DTO

API DTOは、
Server APIとのCommunication Contractである。

Frontendは、
API DTOを受信する。

ただし、
API DTOをそのまま
画面全体のStateとして利用する必要はない。

必要に応じてView ModelへMappingする。

---

# 16. View Model

View Modelは、
画面表示に適したDataを表す。

例えばCheck In画面では、

- Person Name
- Ticket Number
- Ticket Type
- Check In Status
- Check In Time
- Display Message

などをまとめることができる。

View Modelは、
Domain Entityではない。

---

# 17. UI State

UI Stateは、
画面固有の状態を表す。

例えば、

- Selected Ticket
- Search Keyword
- Filter
- Sort
- Dialog Open
- Loading
- Error Message
- Scanner Open

など。

UI Stateを、
Business Factと混同しない。

---

# 18. Business State

Business Stateは、
Server SideのBusiness Factから取得する。

例えば、

Check In済み

というStateは、
FrontendのBooleanを正本としない。

Serverから取得したCheck In状態を、
UIへ表示する。

---

# 19. Local State

Local Stateは、
Client内で一時的に保持するDataである。

例えば、

- Search Keyword
- Form Input
- Selected Item
- Current Screen
- Scanner State

など。

Local Stateを、
Server Business Dataの代わりにしない。

---

# 20. Cache

Frontendでは、
Performance向上のためにCacheを利用できる。

Cache対象：

- Production
- Performance
- Ticket List
- Public Page
- Profile

など。

ただし、
CacheはBusiness FactのSource of Truthではない。

Cacheが削除されても、
APIから再取得できる構造を基本とする。

---

# 21. Cache and Check In

Check Inでは、
Cacheの情報だけで
受付済みと判断しない。

例えば、

Cache：
Ticket X → 未受付

Server：
Ticket X → 受付済み

の場合、
ServerのBusiness Factを優先する。

Check In実行時にも、
Server Sideで最新状態を検証する。

---

# 22. Navigation

Frontend Navigationは、
Userが利用するBusiness Contextを
基準として設計する。

例えば、

Organization
↓
Production
↓
Performance
↓
Reception

など。

Technical Entity構造を、
そのままNavigationへ反映しない。

---

# 23. Authentication UI

Authentication UIは、

- Login
- Logout
- Session Expired
- Authentication Error

などを扱う。

Authenticationそのものは、
Server Sideで実行する。

Frontendは、
Authentication Resultを利用して
UIを制御する。

---

# 24. Authentication State

Frontendでは、
Authentication Stateを管理する。

例：

- Unknown
- Authenticated
- Unauthenticated
- Expired

ただし、
Authentication StateとAuthorization Permissionを
同一視しない。

---

# 25. Authorization UI

Frontendでは、
Authorization情報を利用して
UIを表示 / 非表示にできる。

例えば、

管理者だけに
Management Menuを表示する。

ただし、
UIを非表示にすることは
Security Boundaryではない。

API Request時には、
Server SideでAuthorizationを再確認する。

---

# 26. Scope Context

Frontendは、
現在のBusiness Scopeを保持できる。

例えば、

- Organization
- Production
- Performance

など。

Scope Contextは、
UI NavigationやAPI Requestに利用できる。

ただし、
Scope Permissionの最終判断は
Server Sideで行う。

---

# 27. Organization Context

Management Clientでは、
現在操作しているOrganizationを
Contextとして保持できる。

例えば、

Organization A
↓
Production List

Organization B
↓
Production List

という切り替えが可能。

ただし、
Organization IDをClient側で変更するだけで
他Organizationへアクセスできる設計にはしない。

---

# 28. Production Context

Production Managementでは、
現在操作しているProductionを
Contextとして保持できる。

例えば、

Production A
↓
Participants
↓
Rehearsals
↓
Performances
↓
Tickets
↓
Reception

など。

---

# 29. Performance Context

Performanceは、
Check Inの重要なContextとなる。

基本構造：

Production
↓
Performance
↓
Reception

Reception画面では、
受付対象Performanceを明示する。

---

# 30. Web Reception UI

Web Receptionでは、
受付担当者がBrowserから
一覧を利用してCheck Inを行う。

基本画面：

Performance Selection
↓
Reservation / Issued Ticket List
↓
Search / Filter
↓
Ticket Selection
↓
Check In
↓
Result

QR Scannerを必須としない。

---

# 31. Web Reception List

Web Reception Listには、
必要な受付情報を表示する。

例えば、

- Person Name
- Ticket Number
- Ticket Type
- Reservation Status
- Check In Status
- Check In Time

など。

画面に表示する情報は、
受付業務に必要な範囲に限定する。

---

# 32. Web Reception Search

受付担当者は、
対象者を検索できる。

例えば、

- Name
- Ticket Number
- Reservation Number

など。

Searchは、
API Queryを利用する。

Frontend側で、
全Ticket Dataを取得して
ローカル検索することを基本としない。

---

# 33. Web Reception Filter

Web Receptionでは、
受付状態などでFilterできる。

例えば、

- 未受付
- 受付済み
- Ticket Type
- Reservation Status

など。

Filterは、
必要に応じてServer Side Queryを利用する。

---

# 34. Web Reception Check In

Web ClientでTicketを選択すると、
Check In Actionを実行できる。

基本Flow：

Select Ticket
↓
Check In Button
↓
Confirmation
↓
Check In API
↓
Result
↓
UI Update

UI側では、
Check In Factを直接変更しない。

API成功後に、
Serverから返されたResultを利用して
画面を更新する。

---

# 35. Web Multiple Check In

Web Receptionでは、
必要に応じて複数Ticketを選択できる。

基本UI：

Ticket List
↓
Select Multiple
↓
Check In Selected
↓
Processing
↓
Individual Results

複数Check Inでも、
Server側のBusiness Ruleを利用する。

Frontendで、

Selected Tickets
↓
Status = Checked In

と直接変更しない。

---

# 36. Web Check In Result

Check In成功時には、
受付結果を明確に表示する。

例えば、

Success

Already Checked In

Invalid Ticket

Forbidden

など。

エラー内容は、
API Error Contractに従って表示する。

---

# 37. Web Check In Refresh

Check In後には、
必要に応じてTicket ListをRefreshする。

例えば、

Before：

Ticket A
未受付

After：

Ticket A
受付済み

というように、
Serverの最新状態を再取得して表示する。

---

# 38. Mobile Reception UI

Mobile Receptionでは、
Smartphone Cameraを利用して
QR受付を行う。

基本画面：

Performance Selection
↓
QR Scanner
↓
Scan Result
↓
Check In
↓
Result

---

# 39. QR Scanner

QR Scannerは、
Mobile ClientのDevice Capabilityである。

Scannerの責務：

- Camera Access
- QR Detection
- QR Payload取得

Scannerは、
Ticket Validationや
Check In Business Ruleを実装しない。

---

# 40. QR Scan Flow

Mobile Receptionの基本Flow：

Open Scanner
↓
Camera
↓
QR Detection
↓
QR Payload
↓
API Request
↓
Check In Result
↓
Display Result

QR Payloadを取得しただけでは、
Check In完了とはしない。

---

# 41. QR Payload

QR Payloadは、
Issued Ticketなどを識別するために利用する。

Frontendは、
Payloadを無条件に信頼しない。

PayloadをAPIへ渡し、
Server側でTicketを検証する。

---

# 42. QR Scan Result

QR Scan後、
Serverから結果を取得する。

例えば、

Valid / Success

Already Checked In

Invalid Ticket

Wrong Performance

Forbidden

など。

Frontendは、
Server Resultを表示する。

---

# 43. QR Duplicate Scan

同じQRを連続してScanする可能性がある。

Frontendでは、
連続Scanを抑制するための
UI制御を行ってよい。

ただし、
二重受付防止の本体は
Server Sideで行う。

---

# 44. Web and Mobile Check In

WebとMobileでは、
操作方法が異なる。

Web：

一覧
↓
Ticket Selection
↓
Check In

Mobile：

QR Scan
↓
Ticket Identification
↓
Check In

しかし、

Web
↓
Check In API

Mobile
↓
Check In API

とし、
同じApplication Use Caseを利用する。

---

# 45. Check In UI State

Check In画面では、
以下のUI Stateを管理できる。

- Ready
- Loading
- Success
- Already Checked In
- Validation Error
- Forbidden
- Network Error
- Unknown Error

これらは、
Business Domain Stateと完全に同一ではない。

---

# 46. Check In Success

Check In成功時には、
Server Responseを利用して
UIを更新する。

必要に応じて、

- Ticket Status
- Check In Time
- Person Information

などを表示する。

---

# 47. Check In Already Completed

Already Checked Inの場合は、
新しいCheck In Factを作成しない。

Frontendでは、

Already Checked In

などの結果を表示する。

必要に応じて、
既存のCheck In Timeを表示する。

---

# 48. Check In Network Error

Network Errorの場合、
Check InがServerで確定している可能性がある。

例えば、

Request
↓
Server Check In Success
↓
Response Lost
↓
Client Network Error

というケース。

Frontendでは、
「未受付」と断定しない。

必要に応じて、
Serverから最新状態を再取得する。

---

# 49. Check In Retry

Network Error後にRetryする場合でも、
FrontendはBusiness Factを直接作らない。

Retry：

Check In API
↓
Server

Server側で、

- Existing Check In
- Idempotency
- Business Rule

を確認する。

---

# 50. Form Architecture

Formは、
以下を分離する。

- Input State
- Validation
- Submission
- Result

Form Validationには、

UI Validation
Server Validation

の2種類がある。

---

# 51. Client Validation

Client Validationは、
UX向上のために利用する。

例えば、

- Required
- Format
- Length
- Numeric Range

など。

Client Validationを、
Security Boundaryとしない。

---

# 52. Server Validation

Server Validationは、
Business RuleとSecurityを
確定する。

FrontendのValidationが成功していても、
Server側で再検証する。

---

# 53. Loading State

API Request中は、
Loading Stateを表示できる。

例えば、

- Button Disabled
- Spinner
- Progress
- Skeleton

など。

ただし、
Loading StateをBusiness Stateと
混同しない。

---

# 54. Error State

Frontend Errorは、
種類を分けて扱う。

- Validation Error
- Business Error
- Authentication Error
- Authorization Error
- Network Error
- System Error

Errorごとに、
Userが理解できるUIを提供する。

---

# 55. Authentication Error UI

Session Expiredなどの場合は、

Login
↓
Authentication
↓
元の画面

などへ戻れるUXを検討する。

Security上、
不要なAuthentication情報を
UIへ表示しない。

---

# 56. Authorization Error UI

Permission不足の場合は、
Userが理解できるメッセージを表示する。

例えば、

「この操作を実行する権限がありません。」

など。

Serverの内部Permission構造を、
不要に表示しない。

---

# 57. Network Error UI

Network Errorでは、

- Retry
- Refresh
- Connection Status

などを提供できる。

Check Inでは、
Network Errorが発生しても
受付済みかどうかをClient側で断定しない。

---

# 58. Notification

Frontendでは、
必要に応じてNotificationを利用する。

例えば、

- Check In Success
- Reservation Confirmed
- Announcement
- Rehearsal Change

など。

Notificationは、
Business Factの正本ではない。

---

# 59. Accessibility

Frontendでは、
Accessibilityを考慮する。

対象：

- Keyboard Operation
- Screen Reader
- Focus
- Contrast
- Font Size
- Form Label
- Error Message
- Touch Target

特にReception画面では、
操作速度とAccessibilityを両立する。

---

# 60. Responsive Web

Web Clientは、
DesktopとTabletなどの
異なる画面サイズを考慮する。

特にWeb Receptionでは、

- Desktop
- Tablet

での利用を考慮する。

MobileでWeb Receptionを利用する場合も、
必要に応じてResponsive Layoutを提供できる。

---

# 61. Mobile UX

Mobile Clientでは、
Touch操作を基本とする。

特にQR Receptionでは、

- Scanner起動
- Scan
- Result確認
- 次のScan

を短い操作で行えるUIを目指す。

---

# 62. Reception UX

受付業務では、
操作回数を減らすことを重視する。

Web：

Search
↓
Select
↓
Check In

Mobile：

Scan
↓
Result

という短いFlowを基本とする。

---

# 63. Reception Feedback

Check In後、
受付担当者が結果を
瞬時に理解できるUIを提供する。

例えば、

Success

Already Checked In

Error

などを、
視覚的に明確に区別する。

---

# 64. Reception Continuous Operation

公演当日の受付では、
連続してCheck Inを行う可能性がある。

Mobile：

Scan
↓
Result
↓
Next Scan

Web：

Select
↓
Check In
↓
Next Ticket

という連続Operationを
阻害しないUIを設計する。

---

# 65. Frontend and API Error Mapping

Frontendは、
API Errorを
User-facing ErrorへMappingする。

例えば、

API：

ALREADY_CHECKED_IN

UI：

「このチケットはすでに受付済みです。」

のようにする。

Internal Error Codeを、
そのままUserへ表示する必要はない。

---

# 66. Frontend Security

Frontendは、
Security Boundaryではない。

以下をClient側だけで
Security対策として扱わない。

- Hidden Button
- Hidden Menu
- Client Role
- Local Permission
- Route Guard

これらはUXとして利用できるが、
最終的なAuthorizationは
Server Sideで行う。

---

# 67. Token Security

Authentication Tokenなどの
Credential Dataは、
適切なStorage方式を利用する。

具体的なStorage方式は、
Security Architectureで定義する。

Frontend Codeへ、
SecretやAPI Keyを
Hard Codeしない。

---

# 68. Sensitive Data

Frontendでは、
必要以上のPersonal Dataを
取得・保持しない。

例えば、

Check In画面に
必要のない個人情報を
表示しない。

---

# 69. Browser Storage

Browser Local Storageや
Session Storageを利用する場合でも、
Sensitive Dataの保存を慎重に扱う。

Cache対象と
Credential Dataを分離する。

---

# 70. Mobile Local Storage

Mobile Clientでも、
Local Storageへ保存するDataを
最小限にする。

特に、

- Authentication Token
- Personal Data
- Ticket Data
- Check In Data

などは、
Security Architectureに従う。

---

# 71. Offline Architecture

初期Architectureでは、
重要なBusiness Operationを
Offlineで確定しない。

対象：

- Check In
- Reservation
- Ticket Issuance
- Accounting

など。

Offline Readについては、
必要に応じてCacheを利用できる。

---

# 72. Offline Check In

Offline Check Inは、
初期Architectureでは採用しない。

理由：

- 二重受付
- Ticket Validation
- Authorization
- Security
- Synchronization
- Accounting

などの問題が発生するため。

将来的に必要になった場合は、
別途Offline Check In Architectureを定義する。

---

# 73. State Synchronization

複数Clientから
同じBusiness Dataが更新される場合、
Server Stateを正本とする。

例えば、

Web Client
↓
Check In

Mobile Client
↓
同じTicket表示

の場合、
Mobile Clientは必要に応じて
Serverから最新Stateを取得する。

---

# 74. Real Time Update

将来的に、
Web ClientやMobile Clientへ
Real Time Updateが必要になる可能性がある。

例えば、

- Check In Status
- Reservation Status
- Rehearsal Change
- Performance Change

など。

必要になった場合は、

WebSocket
SSE
Push Notification

などを利用できる。

ただし、
Real Time Channel自体を
Business Factの正本としない。

---

# 75. Check In Real Time

受付中に複数端末を利用する場合、
他端末のCheck In結果を
リアルタイムに反映する必要が生じる可能性がある。

例えば、

Device A
↓
Check In Ticket X

Device B
↓
Ticket X displayed as 未受付

という状態。

必要に応じて、
RefreshやReal Time Updateによって
最新状態を取得する。

ただし、
Device Bが古い表示を持っていても、
Check In API側で二重受付を防止する。

---

# 76. Frontend Performance

Frontendでは、
必要なDataだけを取得する。

特に、

- Large List
- Ticket List
- Reservation List
- Audience History
- Journal Entry

などでは、
PaginationやLazy Loadingを利用する。

---

# 77. Web Reception Performance

Web Receptionでは、
大量のTicketを扱う可能性がある。

そのため、

- Pagination
- Search
- Filtering
- Incremental Loading

などを利用できる。

全Ticketを一度にBrowserへ
取得することを基本としない。

---

# 78. Mobile Reception Performance

Mobile Receptionでは、
QR ScanからResult表示までの
Latencyを小さくする。

基本Flow：

Scan
↓
Request
↓
Check In
↓
Result

不要なAPI Callを、
Scanner Flowへ挿入しない。

---

# 79. Frontend API Retry

Frontendは、
API Requestごとに
Retry Policyを適用する。

ただし、
すべてのRequestを
自動Retryしない。

特にBusiness Operationでは、
Idempotencyを考慮する。

Check Inについては、
Server SideのIdempotency / Stateを
前提としてRetryできる構造とする。

---

# 80. Frontend Timeout

API Timeoutが発生した場合、
FrontendはOperationの結果を
勝手に失敗と確定しない。

特にCheck Inでは、

Request Timeout
↓
Server Processing済みの可能性

がある。

必要に応じて、
Status QueryやRetryによって
Server Stateを確認する。

---

# 81. Frontend State Recovery

Browser Refreshや
Mobile Application Restartが発生しても、
Business Factを失わない。

例えばCheck Inでは、
Client Stateが消えても、

API
↓
Server Check In Fact

から状態を再取得できる。

---

# 82. Deep Link

Mobile Clientでは、
必要に応じてDeep Linkを利用できる。

例えば、

Production
Performance
Ticket

など。

Deep Linkに含まれるIdentifierを、
ServerがAuthorization Scope内で
検証する。

---

# 83. QR and Deep Link

QR Codeが、
単純なTicket Identifierではなく、
URL / Deep Link形式になる場合でも、

QR
↓
Mobile Client
↓
Identifier Extraction
↓
Check In API

という構造を維持する。

QR URL自体を、
Business Factの正本としない。

---

# 84. Frontend Environment

Frontendでは、
Environmentごとの設定を分離する。

例えば、

- Development
- Test
- Staging
- Production

など。

API EndpointなどのEnvironment Configurationを
適切に切り替える。

---

# 85. Frontend Configuration

Frontend Configurationには、

- API Base URL
- Feature Flag
- Environment
- Public Configuration

などを含めることができる。

SecretをFrontend Configurationへ
含めない。

---

# 86. Feature Flag

Feature Flagは、
段階的なFeature Releaseに利用できる。

例えば、

Web Check In

Mobile QR Reception

など。

ただし、
Feature FlagをSecurity Boundaryとして利用しない。

---

# 87. Web Check In Feature Flag

Web Check Inを
段階的に導入する場合、

Web Reception Feature
↓
Feature Flag
↓
Authorized User

などでUIを制御できる。

ただし、
FeatureがDisabledでも、
API側でSecurity / Authorizationを
適切に管理する。

---

# 88. Mobile QR Feature Flag

Mobile QR Receptionについても、
必要に応じてFeature Flagを利用できる。

例えば、

QR Reception
↓
Enabled

とする。

Feature Flagは、
Release Controlであり、
Business Ruleではない。

---

# 89. Frontend Testing

Frontendでは、
以下をTestする。

- Component
- Presentation
- Navigation
- API Client
- Form
- Validation
- Error Handling
- Authentication UI
- Authorization UI
- Check In
- QR Scanner
- Web Reception

---

# 90. Web Check In Testing

Web Receptionでは、

- Performance Selection
- List Loading
- Search
- Filtering
- Individual Check In
- Multiple Check In
- Already Checked In
- Error
- Network Failure
- Refresh

などをTestする。

---

# 91. Mobile QR Testing

Mobile Receptionでは、

- Camera Permission
- QR Detection
- Invalid QR
- Valid QR
- Already Checked In
- Wrong Performance
- Network Error
- Retry
- Scanner Restart

などをTestする。

---

# 92. API Integration Testing

FrontendとAPIのIntegration Testでは、

- Request
- Response
- Authentication
- Authorization
- Error
- Check In

などを確認する。

Mockだけでなく、
必要に応じて実際のAPIとのIntegrationをTestする。

---

# 93. End-to-End Testing

重要なBusiness Flowについて、
End-to-End Testを行う。

例えばWeb Check In：

Login
↓
Performance Selection
↓
Ticket Search
↓
Ticket Selection
↓
Check In
↓
Success

Mobile QR Check In：

Login
↓
Performance Selection
↓
QR Scan
↓
Check In
↓
Success

---

# 94. Check In End-to-End

Check InのEnd-to-End Testでは、
後続処理まで確認する。

Web：

Web Client
↓
Check In API
↓
Check In
↓
CheckInCompleted
├── History
└── Accounting

Mobile：

Mobile Client
↓
QR Scan
↓
Check In API
↓
Check In
↓
CheckInCompleted
├── History
└── Accounting

---

# 95. Frontend and Domain Independence

Frontendは、
Domain Modelの内部構造に
直接依存しない。

例えば、

Person DomainのProperty変更が、
UI Contractへ直接影響しないようにする。

API DTO / View Modelによって、
FrontendとDomainを分離する。

---

# 96. Frontend and Data Independence

Frontendは、
Database Schemaに依存しない。

禁止する基本構造：

Frontend
↓
Database Table

基本構造：

Frontend
↓
API
↓
Application
↓
Domain
↓
Repository
↓
Database

---

# 97. Frontend and API Independence

Frontendは、
API Contractへ依存する。

ただし、
APIの内部Implementationへ依存しない。

例えば、

PHP Framework
WordPress
Database

などの内部変更が、
Frontendへ直接影響しない構造を目指す。

---

# 98. Client-Specific Presentation

WebとMobileでは、
同じBusiness Operationでも
Presentationを変えてよい。

例えば、

Web：

Table
↓
Search
↓
Select
↓
Check In

Mobile：

Camera
↓
Scan
↓
Result

これは、
Client固有のPresentationである。

Business Ruleは、
Server側で共通化する。

---

# 99. Client-Specific Feature

Client固有のFeatureを持つことは許容する。

例えばMobile：

- Camera
- Push Notification
- Device Integration

Web：

- Large Table
- Keyboard Operation
- Bulk Selection

など。

ただし、
Business Factを確定するRuleは
Server側に置く。

---

# 100. Web and Mobile Common Contract

Web ClientとMobile Clientでは、
以下を共通化する。

- Authentication
- Authorization
- API Contract
- Business Operation
- Check In Rule
- Error Model
- Business Fact

Clientごとに異なるのは、
主にPresentationとDevice Capabilityである。

---

# 101. Check In Common Contract

Check Inでは、

Web：

List Selection
↓
Check In API

Mobile：

QR Scan
↓
Check In API

という異なるUIを持つ。

しかし、

Check In API
↓
Check In Use Case
↓
Check In Fact

は共通とする。

---

# 102. Frontend Deployment

Web Clientは、
Web Server / CDNなどへ
Deployできる。

Mobile Clientは、
Application Packageとして
Distributionできる。

Frontend Deployment方式は、
Deployment Architectureで定義する。

---

# 103. Web Frontend Deployment

Web Clientでは、

Browser
↓
Web Hosting
↓
Frontend Application
↓
StageArt API

という構造を基本とする。

Frontend AssetとAPI Serverを、
同一Infrastructureに限定しない。

---

# 104. Mobile Frontend Deployment

Mobile Clientでは、

Mobile Device
↓
Mobile Application
↓
StageArt API

という構造を基本とする。

Mobile Application自体に、
Database Credentialや
Server Secretを含めない。

---

# 105. Frontend Monitoring

Frontendでは、
必要に応じて、

- Error
- Performance
- API Failure
- Crash
- Network Failure

などをMonitoringする。

ただし、
Sensitive Dataを
不要に送信しない。

---

# 106. Reception Monitoring

Receptionでは、
Operational Problemを検知できるようにする。

例えば、

- API Error
- Check In Failure
- Network Failure
- Scanner Failure

など。

受付業務を止めないため、
Error状態を明確に表示する。

---

# 107. Frontend Logging

Frontend Loggingでは、
必要なTechnical Informationだけを記録する。

例えば、

- Error
- Request ID
- Client Version
- Environment

など。

TokenやSecret、
不要なPersonal DataをLogへ出さない。

---

# 108. Request Correlation

API RequestとFrontend Errorを
関連付けられるように、
Request ID / Correlation IDを
利用できる。

基本構造：

Frontend
↓
Request ID
↓
API
↓
Application
↓
Log

障害調査に利用する。

---

# 109. Client Version

Mobile Clientでは、
Application Versionを
APIへ送信できる。

これにより、

- Compatibility
- Bug Investigation
- Migration

などを管理できる。

Web Clientでも、
必要に応じてFrontend Versionを
管理できる。

---

# 110. API Compatibility

Frontend ClientのVersionと、
API VersionのCompatibilityを管理する。

特にMobile Clientでは、
旧Versionが長期間利用される可能性を考慮する。

---

# 111. Frontend Upgrade

Frontend Versionを更新しても、
Server側Business Factを壊さない。

Web Client：

新しいFrontendをDeployする。

Mobile：

新しいApplication VersionをReleaseする。

どちらも、
Server API ContractとのCompatibilityを
維持する。

---

# 112. Progressive Enhancement

Web Clientでは、
可能な範囲でProgressive Enhancementを
考慮する。

ただし、
重要なBusiness Operationについては、
Server APIを必須とする。

---

# 113. Browser Compatibility

Web Clientでは、
利用対象Browserを定義する。

具体的なBrowser Support Matrixは、
Implementation / Deployment Specificationで定義する。

---

# 114. Mobile Device Compatibility

Mobile Clientでは、
対象OS / Deviceを定義する。

具体的なSupport Matrixは、
Implementation / Deployment Specificationで定義する。

---

# 115. Frontend Accessibility and Reception

Reception画面では、
短時間に多数の操作を行う可能性がある。

そのため、

- Keyboard
- Touch
- Focus
- Screen Reader
- Clear Status
- Error Feedback

などを考慮する。

Web Receptionでは、
Keyboard操作を重視できる。

Mobile Receptionでは、
Touch操作を重視する。

---

# 116. Frontend Internationalization

将来的に多言語対応が必要になった場合、
UI Textを外部化できる構造とする。

Business Factそのものに、
Presentation用Languageを
直接埋め込まない。

---

# 117. Date and Time

Frontendで表示するDate / Timeは、
Business TimeとUser Localeを考慮する。

例えば、

Performance Start Time
Check In Time
Reservation Time

など。

Server側のTimestampと、
UI表示用のDate / Time Formatを分離する。

---

# 118. Timezone

StageArtでは、
Performanceの開催地域と
User DeviceのTimezoneが
異なる可能性を考慮する。

Check In TimeなどのBusiness Timestampは、
Server側で正確に管理する。

Frontendは、
必要に応じてLocal Timeへ変換して表示する。

---

# 119. Accessibility and Error

Error Messageは、
単なるColor表示だけに依存しない。

例えば、

Success
Error
Already Checked In

などを、

- Text
- Icon
- Status
- Focus

などで明確に伝える。

---

# 120. Frontend Architecture Summary

StageArt Frontendは、

UI
↓
Presentation
↓
Application Client
↓
API Client
↓
StageArt API

という構造を基本とする。

Web ClientとMobile Clientでは、
PresentationとDevice Capabilityが異なる。

Web：

Performance
↓
Reservation / Issued Ticket List
↓
Search / Filter
↓
Ticket Selection
↓
Check In API

Mobile：

Performance
↓
QR Scanner
↓
Ticket Identifier
↓
Check In API

しかし、
API以下では同じBusiness Operationを利用する。

Check In：

Check In API
↓
Check In Use Case
↓
Check In
↓
CheckInCompleted
├── History
└── Accounting

Frontendは、
Business Factを直接確定しない。

Server SideをSource of Truthとし、
Web Client、
Mobile Client、
Public Client、
Management Client、
Reception Client

が必要なBusiness Operationを
API経由で利用する。

---

# 121. Frontend Architecture Principle

StageArt Frontendの最重要原則：

「FrontendはBusiness Ruleを持つ場所ではなく、
UserがBusiness Operationを利用するためのInterfaceである。」

また、

「WebとMobileでは操作方法を変えてよいが、
Business RuleとBusiness Factは共通化する。」

さらに、

「ClientのStateではなく、
Server SideのBusiness Factを正本とする。」

Check Inについては、

Web
↓
一覧
↓
Ticket Selection
↓
Check In API

Mobile
↓
QR Scan
↓
Ticket Identifier
↓
Check In API

という2つの入口を提供する。

しかし、

Check In API
↓
Check In Use Case
↓
Check In
↓
CheckInCompleted

というServer SideのBusiness Flowは共通とする。

これにより、

- Web Client
- Mobile Client
- Browser
- Smartphone
- Camera
- QR Scanner
- PHP Server
- WordPress
- Database

などのTechnologyやClientが変更されても、
StageArtのBusiness RuleとBusiness Factを
一貫して維持できるFrontend Architectureを実現する。

---
