# StageArt Blueprint

# DomainMap

Version : 4.1

---

# Purpose

DomainMapはStageArt全体のDomain構造を表す。

ER図やDatabase設計ではなく、
StageArtというサービスを構成する概念同士の関係を定義する。

DomainMapは、
個々のDomainの詳細仕様を定義するものではなく、
StageArt全体のDomain構造を俯瞰するためのものである。

個々のDomainの詳細なBusiness Rule、
Lifecycle、
Permission、
Event、
Value Objectなどは、
それぞれのDomain Modelで定義する。

DomainMapは、
StageArtにおけるDomain間の基本的な責務と関係を示す
上位レベルの設計基準とする。

---

# 1. Core Domain Structure

StageArtの基本構造は、

Organization
↓
Project
↓
Production

とする。

OrganizationはStageArtにおけるTenantである。

ProjectはOrganizationが行う活動・制作の内部単位である。

ProductionはProjectに所属する
具体的な公演・活動を表す。

Production関連Domainは、
Productionを通じてOrganization Scopeに属する。

---

# 2. Authentication / Business Identity Structure

StageArtでは、
Authentication IdentityとBusiness Identityを分離する。

基本構造：

UserAccount
↓
Person

UserAccountは、
StageArtへのAuthentication Identityを表す。

Personは、
StageArt上のBusiness Identityを表す。

UserAccountはPersonそのものではない。

Personは、
必ずしもUserAccountを持つ必要はない。

---

# 3. UserAccount

UserAccountは、
StageArtへのログイン・認証を行うIdentityを表す。

UserAccountは、
Personに関連付ける。

基本構造：

UserAccount
↓
Person

UserAccountは、
OrganizationやProductionへ直接所属しない。

Organizationへの所属は、
Personを通じてMembershipによって管理する。

Productionへの参加は、
Personを通じてParticipantによって管理する。

Production Scopeの管理権限は、
Personを通じてProductionDelegateによって管理する。

UserAccount自身に、
Organization RoleやProduction Roleを直接付与しない。

---

# 4. Authentication Provider

UserAccountは、
外部Authentication Providerと連携できる。

例：

- Google
- Apple
- Microsoft
- Email / Password
- その他Authentication Provider

外部Provider固有の認証処理は、
Infrastructure Layerが担当する。

Domain Layerは、
特定Authentication ProviderのAPIへ
直接依存しない。

---

# 5. External Identity

外部Authentication Provider上のIdentityは、
UserAccountに関連付ける。

基本構造：

UserAccount
↓
External Identity
↓
Provider

External Identityは、

- Provider
- Provider User Identifier

などによって識別する。

Provider固有のAPI仕様は、
Infrastructure Layerで管理する。

---

# 6. Person Axis

Personは、
StageArt上の個人を表す。

Personは、

- 役者
- スタッフ
- 制作
- 観客
- その他舞台芸術関係者

などを区別せず表現する。

Person自身に、
Organization固有のRoleを持たせない。

OrganizationにおけるRoleは、
Membershipを通じて適用する。

Productionへの参加はParticipantによって表現する。

Productionにおける管理権限は、
ProductionDelegateを通じてRoleをAssignmentする。

---

# 7. Profile

Profileは、
Person自身が作成・編集するプロフィール情報を管理する。

基本構造：

Person
↓
Profile

Profileには、

- 自己紹介
- 経歴概要
- 活動分野
- 公開プロフィール情報
- その他本人が入力する情報

などを保持できる。

過去の出演歴・活動実績などの履歴情報は、
Profileそのものには保持しない。

活動実績はHistoricalActivityによって管理する。

---

# 8. Historical Activity

HistoricalActivityは、
Personの過去の活動実績を表す。

基本構造：

Person
↓
HistoricalActivity

HistoricalActivityには、

- 出演歴
- スタッフ歴
- 制作歴
- その他過去の活動実績

などを登録できる。

HistoricalActivityは、
本人が入力する過去実績を主な対象とする。

StageArt上で現在発生しているFactから生成される
活動履歴とは区別する。

---

# 9. Organization Axis

Organizationは、
舞台芸術活動を行う団体を表す。

Organizationは劇団に限定しない。

例：

- 劇団
- プロデュース団体
- ダンスカンパニー
- 学生劇団
- 演劇サークル
- 実行委員会
- 制作団体
- その他舞台芸術団体

OrganizationはStageArtにおけるTenantである。

---

# 10. Membership

Membershipは、
PersonとOrganizationの所属関係を表す。

基本構造：

Person
↓
Membership
↓
Organization

一人のPersonは、
複数のOrganizationに所属できる。

Membershipは、

- 所属状態
- 所属開始
- 所属終了
- Organization内でのRole Assignment
- その他所属情報

などを管理する。

Organization自身がPersonを直接保持するのではなく、
Membershipによって所属関係を表現する。

---

# 11. Organization Role

Organization内における管理・運営上の権限は、
Roleによって表現する。

基本構造：

UserAccount
↓
Person
↓
Membership
↓
Organization
↓
Role
↓
Permission

UserAccountは認証Identityであり、
Organization Roleの主体ではない。

PersonがMembershipを通じて
Organization ScopeのRoleを持つ。

---

# 12. Role

Roleは、
Permissionのまとまりを定義する。

Role Definitionは、
Organization RoleとProduction Roleで分けない。

同じRole Definitionを、

- Organization Scope
- Production Scope

の両方で利用できる。

例：

- Administrator
- Rehearsal Manager
- Accounting Manager
- Reservation Manager
- Participant Manager
- Performance Manager

など。

Roleの具体的なPermissionは、
Role Domainで定義する。

---

# 13. Role Assignment

Role Assignmentは、
RoleをPersonへ適用する関係を表す。

基本構造：

Person
↓
Role Assignment
↓
Role
↓
Permission
↓
Scope

Role Definitionと、
Roleが誰にどのScopeで適用されているかを分離する。

Organization Scopeでは、
Membershipを通じてRoleをAssignmentする。

Production Scopeでは、
ProductionDelegateを通じてRoleをAssignmentする。

---

# 14. Production Delegate

ProductionDelegateは、
特定Productionに対するRole Assignmentを表す。

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

ProductionDelegate自身は、
Permissionを定義しない。

ProductionDelegateは、

- Person
- Production
- Role
- Status

などを関連付ける。

RoleによってPermissionを決定し、
ProductionDelegateによってScopeをProductionに限定する。

---

# 15. Primary Manager

Productionには、
PrimaryManagerが存在する。

PrimaryManagerは、
Productionに関する全管理権限を持つ。

基本構造：

Production
↓
PrimaryManager
↓
Person

PrimaryManagerは、
Organization Ownerとは異なる。

Organization OwnerはOrganization Scopeの管理者。

PrimaryManagerはProduction Scopeの管理者。

ProductionDelegateは、
PrimaryManagerから委任された
Production ScopeのRole Assignmentを表す。

---

# 16. Authorization Structure

StageArtの基本Authorization構造は、

Person
↓
Role Assignment
↓
Role
↓
Permission
↓
Scope

とする。

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

UserAccountは、
Authentication IdentityとしてPersonへ接続する。

UserAccount自身がAuthorizationの主体となるのではなく、
Personを起点としてBusiness Authorizationを評価する。

同じRole Definitionを、
Organization ScopeとProduction Scopeで利用できる。

DelegateRoleという別のRole体系は使用しない。

---

# 17. Project / Production Axis

基本構造：

Organization
↓
Project
↓
Production

---

## Project

Projectは、
Organizationが行う活動・制作の内部単位である。

ProjectはOrganizationに所属する。

Projectは、
利用者が必ずしも意識する必要のないInternal Domainである。

一つのProjectは、
一つ以上のProductionを持つことができる。

---

## Production

Productionは、
具体的な公演・活動を表す。

ProductionはProjectに所属する。

Productionは、
StageArtにおける実際の制作・公演活動の中心となるDomainである。

Productionには、

- Participant
- ProductionDelegate
- Performance
- Ticket
- Reservation
- CheckIn
- Rehearsal
- RehearsalCandidate
- Timetable
- Budget
- Production Actual
- Document
- Announcement
- Survey

などが関連する。

---

# 18. Production Classification

Productionには、
分類情報を付与できる。

主なDomain：

- Category
- Genre
- Tag

---

## Category

Productionの公演形態・活動形態を表す。

例：

- 舞台
- ライブ
- 映画
- 配信

---

## Genre

Productionの作品ジャンルを表す。

例：

- コメディ
- ホラー
- ミステリー

---

## Tag

検索・分類用のTagを表す。

Tagは必要に応じて、

- Person
- Organization
- Production
- Performance

などに関連付ける。

---

# 19. Participant Axis

Participantは、
PersonまたはOrganizationがProductionへ参加しているというFactを表す。

基本構造：

Production
↓
Participant
↓
Subject
├── Person
└── Organization

Participantは、
Productionへの参加関係を正本として管理する。

---

# 20. Subject

Subjectは、
Productionへの参加主体を表す。

Subjectには、

- Person
- Organization

を指定できる。

Personだけでなく、
団体・企業などをProductionの参加主体として
表現できる構造とする。

---

# 21. Participant Type

Participant Typeは、
Productionにおける参加区分を表す。

例：

- CAST
- STAFF

Participant TypeはRoleではない。

CASTであることによって、
管理権限を自動的に付与してはならない。

STAFFであることによって、
管理権限を自動的に付与してはならない。

Productionの管理権限が必要な場合は、
ProductionDelegateによってRoleをAssignmentする。

RoleとParticipant Typeは、
明確に分離する。

---

# 22. Performance Axis

Performanceは、
Productionにおける個別の公演回を表す。

基本構造：

Production
↓
Performance

Performanceには、

- 公演日時
- 開始時刻
- 終了時刻
- 会場
- 定員
- Status

などを設定できる。

TicketやReservationは、
必要に応じてPerformanceと関連する。

---

# 23. Ticket Axis

Ticketは、
Productionにおけるチケット販売・利用を管理する。

主な構造：

Production
↓
Ticket
↓
Reservation / Issued Ticket
↓
CheckIn

Ticket関連Domainには、

- Ticket
- Ticket Type
- Price
- Issued Ticket
- QRTicket

などが含まれる。

Ticketの詳細な販売ルールは、
Ticket Domainで定義する。

---

# 24. Reservation

Reservationは、
観客によるチケット予約というFactを表す。

基本的な関連：

Reservation
├── Person
├── Performance
└── Ticket

Reservationには、

- 予約者
- Performance
- Ticket
- Quantity
- Reservation Status

などを関連付ける。

Reservationの詳細なLifecycleは、
Reservation Domainで定義する。

---

# 25. Issued Ticket

Issued Ticketは、
Reservation成立後などに発行される
個別のチケットを表す。

Issued Ticketは、
CheckInの対象となる。

QRコードを利用する場合、
QRTicketなどのArtifactをIssued Ticketから生成できる。

---

# 26. CheckIn

CheckInは、
公演当日の入場受付というFactを表す。

基本構造：

Issued Ticket
↓
CheckIn
↓
CheckInCompleted

CheckInは、

- QRコード
- 予約番号
- 氏名
- その他受付情報

などを利用して実行できる。

CheckIn完了時には、
CheckInCompletedを発生させる。

---

# 27. Ticket Revenue / Accounting Flow

CheckInCompletedを契機として、
Ticket Revenueを会計側へ連携する。

基本フロー：

Reservation / Ticket
↓
CheckIn
↓
CheckInCompleted
↓
Ticket Revenue
↓
Accounting
↓
Journal Entry

Production Domain自身は、
Journal Entryを直接生成しない。

Ticket Revenueに関する会計処理は、
Accounting Domainの責務とする。

---

# 28. Seat Axis

Seatは、
Performanceにおける座席を表す。

将来的に、

Performance
↓
Seat
↓
Reservation Seat

という構造へ拡張できる。

自由席の場合は、
Seatを使用しない。

指定席機能は、
必要に応じて実装する。

---

# 29. Rehearsal Axis

Rehearsal関連Domainは、

RehearsalCandidate
↓
RehearsalAvailability
↓
Rehearsal
↓
RehearsalAttendance

という流れで管理できる。

ただし、
RehearsalはCandidateを経由せず、
直接作成することもできる。

---

# 30. Rehearsal Candidate

RehearsalCandidateは、
稽古候補日を表す。

日程調整を行うために利用する。

---

# 31. Rehearsal Availability

RehearsalAvailabilityは、
PersonがRehearsalCandidateに対して回答した
参加可能状況を表す。

候補日の調整に利用する。

---

# 32. Rehearsal

Rehearsalは、
確定した稽古・予定を表す。

Productionに所属する。

Candidateから生成する場合と、
直接作成する場合の両方に対応する。

---

# 33. Rehearsal Attendance

RehearsalAttendanceは、
確定したRehearsalへの参加状況を表す。

RehearsalCandidateへの日程調整回答とは、
別のFactとして管理する。

---

# 34. Timetable

Timetableは、
Productionにおける日別進行・予定を管理する。

Timetableには、

- 時刻
- 内容
- 場所
- 担当
- 対象者
- 備考

などを設定できる。

Timetableは、
RehearsalやPerformanceなどの
Production Activityと関連する。

---

# 35. Timetable Item

Timetable Itemは、
Timetable内の個別項目を表す。

---

# 36. Budget / Actual / Accounting

BudgetとAccountingは、
異なる目的を持つ。

BudgetはProduction単位の計画。

ActualはProduction単位の実績。

AccountingはOrganization単位の会計正本。

基本的な関係：

Organization
↓
Accounting

Production
├── Budget
└── Production Actual

---

# 37. Budget

Budgetは、
Productionの予算案を表す。

一つのProductionに、
複数のBudgetを持つことができる。

Budgetには、
利用者が自由に名称を付けることができる。

例：

- A会場案
- B会場案
- 一日2公演案

---

# 38. Budget Item

Budget Itemは、
Budget内の個別費目を表す。

---

# 39. Production Actual

Production Actualは、
Productionにおける実績金額を表す。

Production Actualは、
Production単位の予実管理を目的とする。

Organization Accountingにおける
Journal Entryとは異なる概念である。

---

# 40. Budget vs Actual

Budget vs Actualは、
Productionの計画と実績を比較する。

基本的な比較項目：

- Budget
- Actual
- Variance

---

# 41. Production Settlement

Production Settlementは、
Production単位の最終的な収支を確定するために利用する。

ProductionのBudget / Actualと、
Organization Accountingの関係については、
各DomainのBusiness Ruleに従う。

Production Settlementは、
Organization Accountingそのものではない。

---

# 42. Accounting Axis

Accountingは、
Organization単位で管理する。

基本構造：

Organization
↓
Accounting Period
↓
Journal Entry
↓
Journal Entry Line

Accountは、
Organizationに所属する会計上の勘定科目を表す。

基本構造：

Organization
↓
Account
↓
Journal Entry Line

AccountingにおけるAccountは、
Authentication Identityではない。

UserAccountとは完全に別のDomainである。

主なDomain：

- Accounting Period
- Account
- Journal Entry
- Journal Entry Line

Accountingは、
Organization全体の会計正本を管理する。

Production単位のBudgetおよびActualとは、
目的と責務を分離する。

---

# 43. Accounting Period

Accounting Periodは、
Organizationにおける会計期間を表す。

---

# 44. Account

Accountは、
会計上の勘定科目を表す。

AccountはOrganizationに所属する。

Accountは、

- 資産
- 負債
- 純資産
- 収益
- 費用

などの会計上の分類に利用する。

AccountとUserAccountは、
完全に異なるDomainである。

---

# 45. Journal Entry

Journal Entryは、
会計上の仕訳を表す。

Journal Entryは、
Journal Entry Lineによって構成する。

ProductionやTicketなどの他Domainは、
会計仕訳そのものを直接管理しない。

必要なBusiness Eventを発生させ、
Accounting DomainがJournal Entryへ反映する。

---

# 46. Journal Entry Line

Journal Entry Lineは、
Journal Entryを構成する借方・貸方の明細を表す。

Journal Entry Lineは、
必要に応じてAccountを参照する。

---

# 47. Equipment

Equipmentは、
Organizationが保有・管理する備品を表す。

Equipmentは、
資産価値を管理するためのAccounting Assetではない。

主な目的は、

- 何があるか
- どこにあるか
- 誰が持っているか
- 使用可能か
- 不明か
- 廃棄されたか

を管理することである。

Equipmentの取得価格、
資産価値、
減価償却は管理しない。

---

# 48. Equipment History

Equipmentの移動・状態変更などの履歴を管理する。

Equipment Historyは、
Equipmentの管理履歴を表す。

---

# 49. Regulation

Regulationは、
Organizationの規約を表す。

基本構造：

Organization
↓
Regulation
↓
Regulation Version

既存Versionを上書きせず、
変更時には新しいVersionを作成する。

---

# 50. Document

Documentは、
Organization、Project、Productionなどに関連する
文書・ファイル情報を管理する。

基本構造：

Organization
├── Document
├── Project
│   └── Document
└── Production
    └── Document

実ファイルは、
Google Driveなどの外部ストレージと連携できる。

StageArtでは、

- File Identifier
- File Name
- File Type
- External Reference
- 関連Domain
- 共有対象

などの情報を管理する。

実ファイルそのものを
StageArtの正本として保持することを前提としない。

---

# 51. Announcement

Announcementは、
OrganizationまたはProductionの関係者へ送信する
内部のお知らせを表す。

Organization Scopeでは、
Organizationの関係者を対象とする。

Production Scopeでは、
Productionの関係者を対象とする。

対象者には、

- CAST
- STAFF
- ProductionDelegate
- その他関係者

などを指定できる。

Announcement作成には、
適切なAuthorizationが必要となる。

---

# 52. Survey

Surveyは、
OrganizationまたはProductionの関係者から
回答を収集するためのDomainである。

Productionでは、

- CAST
- STAFF
- その他関係者

などを対象にできる。

基本構造：

Production
↓
Survey
↓
Survey Response

---

# 53. Public Information

StageArtでは、
Internal InformationとPublic Informationを分離する。

Public Informationは、
一般利用者が閲覧可能な情報を表す。

内部管理情報を、
Public Informationとして公開してはならない。

---

# 54. Organization Public Profile

Organization Public Profileは、
Organizationの公開情報を表示するPublic Artifactである。

基本的な公開情報：

- 団体名
- 沿革
- 代表
- メンバー
- 過去公演情報
- SNS情報

公開情報は、
Organizationおよび関連DomainのFactから生成・参照する。

---

# 55. Production Public Page

Production Public Pageは、
Productionの公開情報を表示するPublic Artifactである。

Production Public Pageには、
公開対象として定義された情報のみを表示する。

内部管理情報を公開してはならない。

---

# 56. External Connection

ExternalConnectionは、
Organizationと外部サービスとの接続を管理する。

基本構造：

Organization
↓
ExternalConnection
├── Service
└── Credential

ExternalConnectionは、
SNS専用のDomainではない。

---

# 57. Service

Serviceは、
外部サービスの種類を識別する。

例：

- X
- Instagram
- Facebook
- YouTube
- TikTok
- LINE
- Google
- Google Drive
- Google Calendar

特定サービス固有のBusiness Logicは、
Organization Domainへ持ち込まない。

---

# 58. Credential

Credentialは、
ExternalConnectionに属する認証情報を表す。

例：

- OAuth Token
- Access Token
- Refresh Token
- Secret

Credentialは平文保存しない。

暗号化、
Secret管理、
Token更新などの具体的な実装は、
Infrastructure Layerで管理する。

---

# 59. External Connection Scope

ExternalConnectionは、
Organizationに所属する。

異なるOrganizationのExternalConnectionを
共有してはならない。

基本構造：

Organization A
↓
ExternalConnection
↓
Service / Credential

Organization B
↓
ExternalConnection
↓
Service / Credential

Organization Aの認証情報を、
Organization Bから利用することはできない。

---

# 60. External Service Operations

外部サービスへの実際のAPI呼び出しは、
Infrastructure Layerが担当する。

Domain Layerは、

- X
- Instagram
- Google
- Google Drive
- Google Calendar

などの特定サービスへ直接依存しない。

---

# 61. SNS

SNSは、
ExternalConnectionの特別な子Domainとして扱わない。

SNSもServiceとして扱う。

Organization Public ProfileにSNS情報を表示する場合は、
公開対象となるアカウント情報のみを参照する。

Credentialや内部接続情報を公開してはならない。

SNS投稿内容そのものは、
StageArtのDomain上の正本として管理しない。

---

# 62. Google Drive

Google Driveは、
Documentの外部保存先として利用できる。

StageArtでは、

- File Identifier
- File Name
- File Type
- External Reference
- 関連Project
- 関連Production
- 共有対象

などを管理する。

Google Drive上の実ファイルそのものを、
StageArtの正本として管理しない。

---

# 63. Google Calendar

Google Calendarは、
Rehearsalなどの予定を外部Calendarへ連携するために利用できる。

確定したRehearsalを、
Google Calendarへ登録できる。

Google CalendarへのAPI操作は、
Infrastructure Layerが担当する。

---

# 64. Domain Event Structure

Domain間の状態変化は、
Domain Eventによって連携できる。

代表的なEvent：

OrganizationCreated
OrganizationUpdated
OrganizationArchived
OrganizationDeleted

MembershipCreated
MembershipUpdated
MembershipRemoved

RoleAssigned
RoleChanged
RoleRemoved

ProductionCreated
ProductionUpdated
ProductionCompleted
ProductionArchived
ProductionCancelled

ProductionDelegateAdded
ProductionDelegateUpdated
ProductionDelegateRemoved

CheckInCompleted

その他のEventは、
各DomainのBusiness Ruleに従って定義する。

---

# 65. Major Business Flow

StageArtの主要なBusiness Flowは、
以下のように整理する。

---

## Authentication Flow

UserAccount
↓
Person
↓
Business Authorization

---

## Organization Flow

UserAccount
↓
Person
↓
Membership
↓
Organization
↓
Role
↓
Permission

---

## Production Flow

Organization
↓
Project
↓
Production
↓
Participant
↓
Performance / Rehearsal / Ticket / Reservation

---

## Production Authorization Flow

UserAccount
↓
Person
↓
ProductionDelegate
↓
Production
↓
Role
↓
Permission

---

## Ticket Flow

Production
↓
Performance
↓
Ticket
↓
Reservation
↓
Issued Ticket
↓
CheckIn
↓
CheckInCompleted
↓
Ticket Revenue
↓
Accounting
↓
Journal Entry

---

## Rehearsal Flow

Production
↓
Rehearsal Candidate
↓
Rehearsal Availability
↓
Rehearsal
↓
Rehearsal Attendance

または、

Production
↓
Rehearsal

---

## Person Profile Flow

Person
├── Profile
└── HistoricalActivity

Profileは本人が入力・管理するプロフィール情報。

HistoricalActivityは、
本人が登録する過去の活動実績。

---

# 66. Scope Structure

StageArtでは、
Scopeを明確に分離する。

---

## Authentication Scope

UserAccountを管理する。

主な対象：

- UserAccount
- External Identity
- Authentication Status

Authentication Scopeの情報は、
Business Domainの公開情報とは分離する。

---

## Person Scope

Person自身の情報を管理する。

主な対象：

- Person
- Profile
- HistoricalActivity

OrganizationやProductionの権限は、
Person自身のScopeには持たせない。

---

## Organization Scope

Organizationに属するBusiness Dataを管理する。

主な対象：

- Membership
- Role
- Project
- Accounting
- Equipment
- Regulation
- Document
- ExternalConnection
- Organization Announcement

---

## Production Scope

Productionに属するBusiness Dataを管理する。

主な対象：

- Participant
- ProductionDelegate
- Performance
- Ticket
- Reservation
- CheckIn
- Rehearsal
- Timetable
- Budget
- Production Actual
- Document
- Announcement
- Survey

Production Scopeは、
Productionを通じてOrganization Scopeに属する。

---

# 67. Domain Ownership

Domainの基本的な所属関係は、

Organization
↓
Project
↓
Production

を中心とする。

Authentication Scopeが管理するDomain：

- UserAccount
- External Identity

Personに関連するDomain：

- Profile
- HistoricalActivity

Organizationが直接管理するDomain：

- Membership
- Role
- Accounting
- Equipment
- Regulation
- ExternalConnection
- Organization Document
- Organization Announcement

Projectに所属するDomain：

- Production

Productionに所属するDomain：

- Participant
- ProductionDelegate
- Performance
- Ticket
- Reservation
- CheckIn
- Rehearsal
- RehearsalCandidate
- Timetable
- Budget
- Production Actual
- Document
- Announcement
- Survey

Personに関連するDomain：

- Membership
- Participant
- Reservation
- Rehearsal Availability
- Rehearsal Attendance
- ProductionDelegate

ただし、
PersonがこれらのDomainを直接所有するわけではない。

各Domainの正しいParent / Scopeに従って管理する。

---

# 68. Domain Separation Principles

StageArtでは、
異なる意味を持つDomainを統合しない。

特に以下を分離する。

---

## UserAccount / Person

UserAccountはAuthentication Identity。

PersonはBusiness Identity。

---

## Person / Organization

Personは個人。

Organizationは団体。

---

## Membership / Participant

MembershipはOrganizationへの所属。

ParticipantはProductionへの参加。

---

## Role / Participant Type

Roleは権限。

Participant TypeはProductionへの参加区分。

---

## Role / ProductionDelegate

Roleは権限セットの定義。

ProductionDelegateはProduction ScopeへのRole Assignment。

---

## Profile / HistoricalActivity

Profileは本人が入力するプロフィール。

HistoricalActivityは過去の活動実績。

---

## Budget / Actual / Accounting

BudgetはProductionの計画。

Production ActualはProductionの実績。

AccountingはOrganizationの会計正本。

---

## Ticket / Reservation / CheckIn

Ticketはチケット・販売条件。

Reservationは予約というFact。

CheckInは入場というFact。

---

## Production / Performance

Productionは公演・活動全体。

Performanceは個別の公演回。

---

## UserAccount / Account

UserAccountはAuthentication Identity。

AccountはAccounting上の勘定科目。

両者は完全に異なるDomainである。

---

# 69. History Principles

StageArtでは、
「現在のFact」と「過去の活動実績」を区別する。

Personの過去実績は、
HistoricalActivityとして管理する。

StageArt上で現在発生するFactから生成される
履歴情報は、
各DomainのFactを正本とする。

Historyを、
Business Dataの正本として使用しない。

Historyは、
必要に応じてFactから生成・参照する。

---

# 70. Public / Internal Separation

StageArtでは、
Public InformationとInternal Informationを明確に分離する。

Public Information：

- Organization Public Profile
- Production Public Page
- 公開Profile
- 公開History
- 公開SNS情報

Internal Information：

- UserAccount
- Authentication Information
- Role
- Permission
- Membership情報
- Accounting
- Budget
- Production Actual
- Credential
- 内部Document
- 内部Announcement
- その他管理情報

Authentication Informationを、
Public Artifactへ直接公開してはならない。

Internal Informationを、
Public Artifactへ直接公開してはならない。

---

# 71. External Integration Principles

External Serviceは、
StageArt Domainの正本ではない。

外部サービスとの接続情報は、
ExternalConnectionとしてOrganization Scopeで管理する。

実際のAPI操作は、
Infrastructure Layerが担当する。

Domain Layerは、
特定外部サービスのAPI仕様に依存しない。

SNS投稿内容は、
StageArtの正本として永続管理することを前提としない。

Google DriveはDocumentの外部保存先として利用する。

Google CalendarはRehearsalなどの外部Calendar連携先として利用する。

Authentication Providerは、
UserAccountのAuthentication Identityを提供する。

Authentication Provider固有のAPI処理は、
Infrastructure Layerで実装する。

---

# 72. Lifecycle Principles

UserAccount、
Organization、
Project、
Productionなどの主要Domainは、
それぞれ固有のLifecycleを持つ。

Lifecycleによって、

- 新規作成可否
- 更新可否
- Business Activity実行可否
- 参照可否
- Archive可否
- Delete可否

などを制御する。

UserAccountのLifecycleと、
PersonのLifecycleは分離する。

具体的なLifecycle Ruleは、
各Domainで定義する。

---

# 73. Audit Principles

重要なBusiness Dataには、
必要に応じてAudit Informationを保持する。

基本的な情報：

- CreatedBy
- CreatedAt
- UpdatedBy
- UpdatedAt

UserAccountについては、
Authentication / Security Auditを
必要に応じてBusiness Auditと分離する。

Credentialそのものを
Audit Informationとして記録しない。

---

# 74. DomainMap Design Decisions

StageArtのTenantはOrganizationである。

Organizationは劇団に限定しない。

UserAccountはAuthentication Identityである。

PersonはBusiness Identityである。

UserAccountとPersonを分離する。

PersonとOrganizationは別のIdentityとして管理する。

PersonとOrganizationの所属関係はMembershipで管理する。

Organization内の管理権限はRoleで管理する。

Role Definitionは一つの体系に統合する。

DelegateRoleという別のRole体系は使用しない。

Production単位のRole Assignmentは、
ProductionDelegateで管理する。

RoleはPermission Setを定義する。

ProductionDelegateはPermissionを直接定義しない。

ProductionDelegateはProduction Scopeに限定される。

ParticipantとMembershipを分離する。

ParticipantとProductionDelegateを分離する。

Participant TypeとRoleを分離する。

CAST / STAFFはParticipant TypeでありRoleではない。

Organizationの活動・制作はProjectで管理する。

ProductionはProjectに所属する。

Production関連DomainはProductionを通じてOrganization Scopeに属する。

AccountingはOrganization単位で管理する。

AccountはAccounting上の勘定科目である。

AccountはAuthentication Identityではない。

UserAccountとAccountを明確に分離する。

BudgetおよびProduction ActualはProduction単位で管理する。

Ticket Revenueの会計連携は、
CheckInCompletedを契機とする。

Production DomainはJournal Entryを直接生成しない。

Accounting Domainが会計仕訳を管理する。

ProfileとHistoricalActivityを分離する。

Public InformationとInternal Informationを分離する。

ExternalConnectionはOrganization Scopeで管理する。

ExternalConnectionはSNS専用ではない。

Credentialは平文保存しない。

外部サービスへのAPIアクセスはInfrastructure Layerが担当する。

Authentication Provider固有の処理はInfrastructure Layerが担当する。

Blueprintを唯一の設計基準とする。

---

# 75. Design Principles

- OrganizationはStageArtにおけるTenantである。
- Organizationは劇団に限定しない。
- UserAccountはAuthentication Identityである。
- PersonはBusiness Identityである。
- UserAccountとPersonを分離する。
- PersonとOrganizationは別のIdentityとして管理する。
- PersonとOrganizationの所属関係はMembershipで管理する。
- Organization内の権限はRoleで管理する。
- RoleはPermission Setを定義する。
- Role DefinitionはOrganization ScopeとProduction Scopeで共通利用する。
- Role DefinitionはScopeを持たない。
- ProductionDelegateはProduction ScopeのRole Assignmentである。
- DelegateRoleという別のRole体系を使用しない。
- ParticipantとMembershipを分離する。
- ParticipantとProductionDelegateを分離する。
- Participant TypeとRoleを分離する。
- CAST / STAFFはParticipant TypeでありRoleではない。
- Organizationの活動・制作はProjectで管理する。
- Projectの下にProductionを持つ。
- Production関連DomainはProductionを通じてOrganization Scopeに属する。
- ProductionにはPrimaryManagerが存在する。
- ProductionDelegateはPrimaryManagerから委任されたRole Assignmentを表す。
- PerformanceはProductionにおける個別公演回を表す。
- Ticketはチケット販売・利用を管理する。
- Reservationは予約Factを表す。
- CheckInは入場Factを表す。
- CheckInCompletedはTicket Revenue連携のBusiness Eventとなる。
- AccountingはOrganization単位で管理する。
- AccountはAccounting上の勘定科目である。
- UserAccountはAuthentication Identityであり、Accountとは異なる。
- Journal EntryはAccounting Domainで管理する。
- BudgetはProduction単位の計画である。
- Production ActualはProduction単位の実績である。
- BudgetとAccountingを同一概念として扱わない。
- Profileは本人が入力するプロフィール情報を管理する。
- HistoricalActivityは過去の活動実績を管理する。
- HistoryをBusiness Dataの正本として使用しない。
- Public InformationとInternal Informationを分離する。
- Authentication InformationをPublic Informationとして扱わない。
- Organization Public Profileは公開対象情報のみを表示する。
- Production Public Pageは公開対象情報のみを表示する。
- ExternalConnectionはOrganizationの子Entityである。
- ExternalConnectionはSNSに限定しない。
- Serviceは外部サービスの種類を識別する。
- Credentialは外部サービスの認証情報を管理する。
- Credentialは平文保存しない。
- 外部サービス固有のAPI処理はInfrastructure Layerで実装する。
- Authentication Provider固有のAPI処理はInfrastructure Layerで実装する。
- Google DriveはDocumentの外部保存先として利用する。
- Google CalendarはRehearsalなどの外部連携先として利用する。
- SNS投稿内容はStageArtの正本として管理しない。
- Domain間の状態変化は必要に応じてDomain Eventで連携する。
- Domainの詳細仕様は各Domain Modelで定義する。
- DomainMapはStageArt全体の上位構造を定義する。
- Blueprintを唯一の設計基準とする。