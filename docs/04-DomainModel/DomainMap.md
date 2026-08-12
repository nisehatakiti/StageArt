# StageArt Blueprint

# DomainMap

Version : 4.2

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
ProductionDelegateを通じてRoleを適用する。

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

本人が入力する過去実績は、
HistoricalActivityによって管理する。

---

# 8. Historical Activity

HistoricalActivityは、
Personが入力する過去の活動実績を表す。

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
- Organization内でのRole
- その他所属情報

などを管理する。

Organization自身がPersonを直接保持するのではなく、
Membershipによって所属関係を表現する。

---

# 11. Organization Role

Organization内における管理・運営上の権限は、
Roleによって表現する。

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

PersonがMembershipを通じて
Organization ScopeのRoleを持つ。

UserAccountは認証Identityであり、
Organization Roleの主体ではない。

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

RoleはPermission Setを定義する。

具体的なPermissionは、
Authorization Domainで定義する。

---

# 13. Role Application

RoleをPersonへ適用する方法は、
Scopeによって異なる。

Organization Scopeでは、

Person
↓
Membership
↓
Organization
↓
Role
↓
Permission

とする。

Production Scopeでは、

Person
↓
ProductionDelegate
↓
Production
↓
Role
↓
Permission

とする。

RoleAssignmentという独立Domainは作成しない。

Roleが誰にどのScopeで適用されているかは、
MembershipまたはProductionDelegateによって表現する。

---

# 14. Production Delegate

ProductionDelegateは、
特定Productionに対してPersonへRoleを適用する
Production Scopeの関係を表す。

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
PrimaryManagerとは別の権限経路として、
Roleによって限定されたProduction Scopeの権限を持つ。

---

# 16. Authorization Structure

StageArtの基本Authorization構造は、

Person
↓
Scope
↓
Role
↓
Permission

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

RoleAssignmentという独立Domainも使用しない。

---

# 17. Project / Production Axis

基本構造：

Organization
↓
Project
↓
Production

---

# 18. Project

Projectは、
Organizationが行う活動・制作の内部単位である。

ProjectはOrganizationに所属する。

Projectは、
利用者が必ずしも意識する必要のないInternal Domainである。

一つのProjectは、
一つ以上のProductionを持つことができる。

---

# 19. Production

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
- Timetable
- Budget
- Production Actual
- Document
- Announcement
- Survey

などが関連する。

---

# 20. Production Classification

Productionには、
分類情報を付与できる。

主なDomain：

- Category
- Genre
- Tag

---

# 21. Category

Categoryは、
Productionの公演形態・活動形態を表す。

例：

- 舞台
- ライブ
- 映画
- 配信

---

# 22. Genre

Genreは、
Productionの作品ジャンルを表す。

例：

- コメディ
- ホラー
- ミステリー
- ドラマ
- 音楽
- ダンス

---

# 23. Tag

Tagは、
検索・分類用の情報を表す。

Tagは必要に応じて、

- Person
- Organization
- Production
- Performance

などに関連付ける。

---

# 24. Participant Axis

Participantは、
PersonまたはOrganizationがProductionへ参加している
というFactを表す。

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

# 25. Subject

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

# 26. Participant Type

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
ProductionDelegateによってRoleを適用する。

RoleとParticipant Typeは、
明確に分離する。

---

# 27. Performance Axis

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

# 28. Ticket Axis

Ticketは、
Productionにおけるチケット販売・利用を管理する。

主な構造：

Production
↓
Ticket
↓
Issued Ticket
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

# 29. Reservation

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

# 30. Reservation Handling

Reservationには、
Participantを介した「扱い」を設定できる。

基本構造：

Reservation
↓
Participant

Reservationの扱いは、
ReservationとProduction Participantの関係として表現する。

Participantそのものの役割や権限とは区別する。

---

# 31. CheckIn

CheckInは、
Issued TicketまたはReservationに対する
入場処理を表す。

基本構造：

Performance
↓
Issued Ticket
↓
CheckIn

CheckInは、
Ticket利用の実績を表す。

CheckInの完了は、
必要に応じてAccounting Domainへの
会計Fact生成の契機となる。

---

# 32. Rehearsal Axis

Rehearsal関連Domainは、
Rehearsalを中心に一つのDomainとして管理する。

基本構造：

Production
↓
Rehearsal
↓
RehearsalAttendance
↓
Person

Rehearsalは、
稽古予定の作成から、
日程確定、
実施中、
実施完了までを
一つのEntityとして管理する。

稽古予定と確定稽古を、
別のDomainとして分離しない。

---

# 33. Rehearsal

Rehearsalは、
Productionにおける稽古・活動予定を表す。

Rehearsalは、
日程調整中である場合も、
確定している場合も、
実施中である場合も、
同じDomainとして管理する。

Rehearsalの状態は、
Statusによって表現する。

基本構造：

Production
↓
Rehearsal

一つのProductionは、
複数のRehearsalを持つことができる。

---

# 34. Rehearsal Lifecycle

Rehearsalは、
以下のLifecycleを持つ。

DRAFT
↓
SCHEDULED
↓
CONFIRMED
↓
ACTIVE
↓
COMPLETED

中止の場合：

DRAFT
↓
CANCELLED

SCHEDULED
↓
CANCELLED

CONFIRMED
↓
CANCELLED

ACTIVE
↓
CANCELLED

Rehearsalの状態変更によって、
別のRehearsal Domainを生成しない。

---

# 35. Rehearsal Status

RehearsalのStatusは、

- DRAFT
- SCHEDULED
- CONFIRMED
- ACTIVE
- COMPLETED
- CANCELLED

とする。

DRAFT：

Rehearsalが作成されたが、
まだ予定調整を開始していない状態。

SCHEDULED：

Rehearsalの具体的な予定を提示し、
参加予定者の確認を行っている状態。

CONFIRMED：

Rehearsalの予定が確定した状態。

ACTIVE：

Rehearsalを実施している状態。

COMPLETED：

Rehearsalが実施済みとなった状態。

CANCELLED：

Rehearsalが中止された状態。

---

# 36. Rehearsal Schedule

Rehearsalは、
以下の予定情報を持つ。

- 日付
- 開始日時
- 終了日時
- タイムゾーン
- 場所
- Title
- Description

必要に応じて、

- 集合時刻
- 開始予定時刻
- 終了予定時刻
- 解散予定時刻

などを保持できる。

日時変更は、
Rehearsal自身の更新として扱う。

別のCandidateを作成して、
新しいRehearsalを生成する必要はない。

---

# 37. Rehearsal Attendance

RehearsalAttendanceは、
特定のRehearsalに対する
Personの参加状態を表す。

基本構造：

Rehearsal
↓
RehearsalAttendance
↓
Person

RehearsalAttendanceは、
Rehearsalの子Entityとして管理する。

RehearsalAttendanceを
独立したDomainとして扱わない。

---

# 38. Rehearsal Attendance Lifecycle

RehearsalAttendanceは、
Rehearsalの予定段階から存在できる。

予定確認段階では、

- UNANSWERED
- ATTENDING
- NOT_ATTENDING

などの状態を持つ。

RehearsalがACTIVEになった場合は、
同じRehearsalAttendanceの状態を
実際の出欠状態へ変更する。

例えば、

ATTENDING
↓
ATTENDED

ATTENDING
↓
LATE

ATTENDING
↓
ABSENT

など。

予定段階の参加予定者を削除して、
別の出欠Entityを生成することはしない。

---

# 39. Rehearsal Attendance Retention

RehearsalがCONFIRMEDになっても、
RehearsalAttendanceは保持する。

RehearsalがACTIVEになっても、
RehearsalAttendanceは保持する。

RehearsalがCOMPLETEDになった後も、
RehearsalAttendanceは履歴として保持する。

基本構造は、
RehearsalのLifecycle全体を通じて変わらない。

Production
↓
Rehearsal
↓
RehearsalAttendance
↓
Person

---

# 40. Rehearsal Attendance Target

RehearsalAttendanceの対象者は、
ProductionのParticipantを基本とする。

ただし、
Production Participant全員が
すべてのRehearsalへ参加するとは限らない。

Rehearsalごとに、
参加予定者を設定できる。

例えば、

Production
├── Participant A
├── Participant B
├── Participant C
└── Participant D

Rehearsal A
├── Participant A
├── Participant B
└── Participant C

Rehearsal B
├── Participant A
└── Participant D

のように管理する。

---

# 41. Rehearsal and Participant

Rehearsalは、
Participantを直接所有しない。

ParticipantはProductionへの参加Factであり、
RehearsalAttendanceは
そのProduction Participantが
特定Rehearsalへ参加する状態を表す。

基本構造：

Production
↓
Participant
↓
Person

Production
↓
Rehearsal
↓
RehearsalAttendance
↓
Person

---

# 42. Rehearsal and Participant Type

Participant Typeは、
RehearsalAttendanceの対象者を
選択・絞り込むために利用できる。

例えば、

- CAST
- STAFF

など。

Participant TypeはRoleではない。

CASTやSTAFFであることによって、
Rehearsal管理権限を自動的に付与しない。

管理権限は、
Authorization DomainのRole / Permissionによって管理する。

---

# 43. Rehearsal and Timetable

Rehearsal内の詳細な進行は、
Timetable Domainで管理する。

基本構造：

Production
↓
Rehearsal
↓
Timetable
↓
Timetable Item

Rehearsalは、
稽古そのものの予定を管理する。

Timetableは、
そのRehearsal内の詳細な時間割・進行を管理する。

---

# 44. Rehearsal and Google Calendar

CONFIRMEDとなったRehearsalは、
Google Calendarへ連携できる。

基本構造：

Rehearsal
↓
External Calendar Event
↓
Google Calendar

Google Calendar Eventは、
Rehearsalそのものではない。

StageArt上のRehearsalを正本とする。

Google CalendarへのAPI操作は、
Infrastructure Layerが担当する。

---

# 45. Rehearsal Calendar Scope

Google Calendarへの登録対象は、
RehearsalAttendanceと完全には一致しない。

Rehearsalの共有対象となるPersonを、
Calendar連携対象として指定できる。

そのため、

RehearsalAttendance
Status = NOT_ATTENDING

であるPersonであっても、
必要に応じてCalendarへ予定を登録できる。

Calendar登録対象と、
稽古参加予定は別の概念として扱う。

---

# 46. Rehearsal History

COMPLETEDとなったRehearsalは、
削除せず履歴として保持する。

RehearsalAttendanceも保持する。

これにより、

- 稽古日時
- 稽古内容
- 参加予定者
- 実際の参加者
- 欠席者
- 遅刻者

などを後から参照できる。

History Domainが必要な場合は、
RehearsalおよびRehearsalAttendanceの
Factから生成する。

---

# 47. Timetable Axis

Timetableは、
Production Activity内の詳細な時間割・進行を管理する。

RehearsalやPerformanceなどの
Activityと関連する。

基本構造：

Activity
↓
Timetable
↓
Timetable Item

Timetableは、
Activityそのものとは別のDomainである。

---

# 48. Timetable Item

Timetable Itemは、
Timetable内の個別の進行項目を表す。

例えば、

- 集合
- 発声
- 立ち稽古
- シーン稽古
- 休憩
- 通し
- 片付け

など。

Timetable Itemは、
RehearsalそのもののStatusを変更しない。

---

# 49. Budget

Budgetは、
Production単位の予算を管理する。

基本構造：

Production
↓
Budget

Budgetは、
Organization全体のAccountingとは異なる。

BudgetはProductionの計画値を管理する。

---

# 50. Production Actual

Production Actualは、
Production単位の実績値を管理する。

基本構造：

Production
↓
Production Actual

Production Actualは、
Productionの実績管理を目的とする。

Accounting DomainのJournal Entryとは異なる。

---

# 51. Accounting

Accountingは、
Organization単位の会計を管理する。

基本構造：

Organization
↓
Accounting
├── Accounting Period
├── Account
├── Journal Entry
└── Journal Entry Line

Accountは、
Accounting Domainにおける勘定科目を表す。

AccountはAuthentication Identityではない。

Authentication IdentityはUserAccountによって表現する。

---

# 52. Equipment

Equipmentは、
Organizationに所属する備品を管理する。

基本構造：

Organization
↓
Equipment

Equipmentは、
資産価値を管理することを目的としない。

主な目的は、

- 何があるか
- どこにあるか
- 誰が持っているか
- 使用可能か
- 不明か
- 廃棄されたか

を管理することである。

取得価格、
資産価値、
減価償却は管理しない。

---

# 53. Regulation

Regulationは、
Organizationに所属する規約を管理する。

基本構造：

Organization
↓
Regulation
├── Version 1
├── Version 2
└── Version 3

既存Versionを上書きせず、
変更時には新しいVersionを作成する。

---

# 54. Document

Documentは、
Organization、
Project、
Productionなどに関連する文書情報を管理する。

実ファイルは、
Google DriveなどのExternal Storageと連携できる。

StageArtでは、

- File Identifier
- File Name
- File Type
- External URL / Reference
- 関連Project
- 関連Production
- 共有対象

などを管理する。

外部Storage上の実ファイルそのものを、
StageArtの正本として管理しない。

---

# 55. Announcement

Announcementは、
OrganizationまたはProductionの関係者への
内部のお知らせを管理する。

対象者には、

- キャスト
- スタッフ
- 制作
- その他関係者

などを指定できる。

Announcement作成権限は、
Authorization DomainのRole / Permissionで管理する。

---

# 56. Survey

Surveyは、
OrganizationまたはProductionに関連する
回答収集を管理する。

例えば、

- 日程確認
- 出欠確認
- アンケート
- 意向確認

などに利用できる。

Rehearsalの日程確認そのものは、
RehearsalAttendanceによって管理する。

SurveyとRehearsalAttendanceを
同一Domainとして扱わない。

---

# 57. External Connection

ExternalConnectionは、
Organizationと外部サービスとの接続関係を表す。

基本構造：

Organization
↓
ExternalConnection
├── Service
└── Credential

ExternalConnectionは、
Organizationの子Entityである。

ExternalConnectionはSNS専用ではない。

---

# 58. External Service

Serviceは、
外部サービスの種類を表す。

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

# 59. External Account

ExternalConnectionは、
外部サービス上のAccountを識別する情報を保持する。

Account Identifierには、

- 外部サービスのAccount ID
- ユーザー名
- Page ID
- その他外部サービス上の識別子

などを利用できる。

StageArt内部のAccountとは別の概念である。

---

# 60. Credential

Credentialは、
ExternalConnectionに属する認証情報を表す。

Credentialには必要に応じて、

- OAuth Token
- Access Token
- Refresh Token
- Secret

などを保持する。

認証情報は平文で保存しない。

暗号化、
Secret管理、
Token更新などの具体的な実装は
Infrastructure Layerで管理する。

Domain Modelは、
特定の認証方式へ直接依存しない。

---

# 61. External Connection Scope

ExternalConnectionはOrganizationに所属する。

異なるOrganizationのExternalConnectionを
共有してはならない。

Organization A
↓
ExternalConnection
↓
External Account A

Organization B
↓
ExternalConnection
↓
External Account B

Organization Aの認証情報を、
Organization Bから利用することはできない。

---

# 62. SNS

SNSは、
ExternalConnectionの特別なDomainとして扱わない。

SNSも外部Serviceの一種として管理する。

OrganizationのPublic ProfileにSNS情報を表示する場合は、
公開対象となるアカウント情報のみを参照する。

Credentialや内部接続情報を公開してはならない。

SNS投稿内容そのものを、
StageArtの正本として管理しない。

---

# 63. Google Drive

Google Driveは、
Documentの外部保存先として利用する。

StageArtは、
Google Drive上の実ファイルそのものを
正本として管理しない。

StageArtでは、

- File Identifier
- File Name
- File Type
- External URL / Reference
- Project / Productionとの関連
- 共有対象

などを管理する。

---

# 64. Google Calendar

Google Calendarは、
Rehearsalなどの予定を
外部Calendarへ連携するために利用する。

CONFIRMEDとなったRehearsalを、
Google Calendarへ登録できる。

Google Calendar Eventは、
StageArtのRehearsalとは別のExternal Artifactである。

RehearsalをStageArt側の正本とする。

Google CalendarへのAPI操作は、
Infrastructure Layerが担当する。

---

# 65. Public Information

Organizationの公開情報は、
Public Informationとして管理する。

例：

- 団体名
- 沿革
- 代表
- メンバー
- 過去公演情報
- SNS情報

公開情報は、
Organization内部のFactから生成・参照できる。

内部管理情報をPublic Informationとして公開してはならない。

---

# 66. Organization Public Profile

Organization Public Profileは、
Organizationの公開情報を表示するPublic Artifactである。

基本構造：

Organization
↓
Public Profile

Public Profileには、
公開対象として定義された情報のみを表示する。

内部権限、
会計情報、
Credential、
内部Documentなどを公開してはならない。

---

# 67. History

Historyは、
StageArt上で発生した活動履歴を管理する独立Domainである。

Historyは、
OrganizationやPersonの単純な子Entityではない。

Historyは、
Production、
Performance、
Participant、
RehearsalなどのFactから
生成・参照できる。

---

# 68. History Relationship

Productionに関連する活動：

Production
↓
History

Performanceに関連する活動：

Performance
↓
History

PersonまたはOrganizationの活動履歴として利用する場合：

Subject
↓
History

Rehearsalについても、
必要に応じてRehearsalおよび
RehearsalAttendanceのFactから
Historyを生成できる。

---

# 69. Organization Scope

Organizationに関連するBusiness Dataは、
Organization Scopeの中で管理する。

主な対象：

- Project
- Membership
- Accounting
- Equipment
- Document
- External Connection
- Regulation
- Announcement

Production関連Domainは、
Productionを通じてOrganization Scopeを判定する。

---

# 70. Production Scope

Productionに関連するBusiness Dataは、
Production Scopeの中で管理する。

主な対象：

- Participant
- ProductionDelegate
- Performance
- Ticket
- Reservation
- CheckIn
- Rehearsal
- RehearsalAttendance
- Timetable
- Budget
- Production Actual
- Document
- Announcement
- Survey

異なるProductionの内部情報へ、
権限なくアクセスしてはならない。

---

# 71. Organization Authorization Flow

Organization Scopeの基本構造：

Person
↓
Membership
↓
Organization
↓
Role
↓
Permission

Membershipによって、
PersonがOrganization ScopeのRoleを持つ。

Roleによって、
Organization ScopeのPermissionを決定する。

---

# 72. Production Authorization Flow

Production Scopeの基本構造：

Person
↓
ProductionDelegate
↓
Production
↓
Role
↓
Permission

ProductionDelegateは、
Production単位でRoleを適用する。

同一Personが複数ProductionのDelegateになる場合、
Productionごとに異なるRoleを適用できる。

PrimaryManagerは、
Productionに対する全管理権限を持つ。

---

# 73. Lifecycle Principles

UserAccount、
Organization、
Project、
Production、
Rehearsalなどの主要Domainは、
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

# 74. Audit Principles

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

# 75. DomainMap Design Decisions

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

RoleAssignmentという独立Domainは使用しない。

Production単位のRole適用は、
ProductionDelegateで管理する。

RoleはPermission Setを定義する。

Organizationの活動・制作はProjectで管理する。

ProductionはProjectに所属する。

Production関連DomainはProductionを通じてOrganization Scopeに属する。

ProductionにはPrimaryManagerが存在する。

PrimaryManagerはProductionに対する全管理権限を持つ。

ParticipantとMembershipを分離する。

ParticipantとProductionDelegateを分離する。

Participant TypeとRoleを分離する。

CAST / STAFFはParticipant TypeでありRoleではない。

AccountingはOrganization単位で管理する。

AccountはAccounting上の勘定科目である。

AccountはAuthentication Identityではない。

UserAccountとAccountを明確に分離する。

BudgetおよびProduction ActualはProduction単位で管理する。

ProfileとHistoricalActivityを分離する。

Public InformationとInternal Informationを分離する。

ExternalConnectionはOrganization Scopeで管理する。

ExternalConnectionはSNS専用ではない。

Credentialは平文保存しない。

外部サービスへのAPIアクセスはInfrastructure Layerが担当する。

Authentication Provider固有の処理はInfrastructure Layerが担当する。

RehearsalはProductionに所属する。

Rehearsalは稽古予定から実施完了までを一つのDomainとして管理する。

稽古予定と確定稽古を別Domainに分けない。

Rehearsal Candidateを使用しない。

Rehearsal Availabilityを使用しない。

RehearsalのLifecycleはStatusで管理する。

RehearsalAttendanceはRehearsalの子Entityである。

RehearsalAttendanceを独立Domainとして管理しない。

RehearsalAttendanceは予定段階から保持する。

RehearsalがCONFIRMEDになっても、
RehearsalAttendanceを削除しない。

RehearsalがACTIVEになっても、
RehearsalAttendanceを削除しない。

同じRehearsalAttendanceのStatusを、
予定状態から実績状態へ変更する。

Rehearsalごとに参加予定者を管理する。

TimetableはRehearsal内の詳細な進行を管理する。

Google Calendar EventはRehearsalの正本ではない。

RehearsalをStageArt側の正本とする。

COMPLETEDとなったRehearsalは履歴として保持する。

RehearsalAttendanceも履歴として保持する。

Blueprintを唯一の設計基準とする。

---

# 76. Design Principles

- OrganizationはStageArtにおけるTenantである。
- Organizationは劇団に限定しない。
- UserAccountはAuthentication Identityである。
- PersonはBusiness Identityである。
- UserAccountとPersonを分離する。
- AccountはAccounting Domainの勘定科目である。
- AccountとUserAccountを分離する。
- PersonとOrganizationは別のIdentityとして管理する。
- PersonとOrganizationの所属関係はMembershipで管理する。
- Organization内の権限はRoleで管理する。
- RoleはPermission Setを定義する。
- Role DefinitionはOrganization ScopeとProduction Scopeで共通利用する。
- Role DefinitionはScopeを持たない。
- RoleAssignmentという独立Domainを作成しない。
- ProductionDelegateはProduction ScopeでRoleをPersonへ適用する。
- DelegateRoleという別のRole体系を使用しない。
- PrimaryManagerはProductionに対する全管理権限を持つ。
- ParticipantとMembershipを分離する。
- ParticipantとProductionDelegateを分離する。
- Participant TypeとRoleを分離する。
- CAST / STAFFはParticipant TypeでありRoleではない。
- Organizationの活動・制作はProjectで管理する。
- Projectの下にProductionを持つ。
- Production関連DomainはProductionを通じてOrganization Scopeに属する。
- PerformanceはProductionにおける個別公演回を表す。
- Ticketはチケット販売・利用を管理する。
- Reservationは予約Factを表す。
- CheckInはチケット利用の実績を表す。
- AccountingはOrganization単位で管理する。
- BudgetおよびProduction ActualはProduction単位で管理する。
- ProfileとHistoricalActivityを分離する。
- Public InformationとInternal Informationを分離する。
- ExternalConnectionはOrganizationの子Entityである。
- ExternalConnectionはSNSに限定しない。
- Serviceは外部サービスの種類を表す。
- CredentialはExternalConnectionに属する。
- Credentialは平文保存しない。
- 外部サービス固有のAPI処理はInfrastructure Layerで実装する。
- RehearsalはProductionに所属する。
- Rehearsalは稽古予定から実施完了までを一つのEntityとして管理する。
- 稽古予定と確定稽古を別Domainに分けない。
- Rehearsal Candidateを使用しない。
- Rehearsal Availabilityを使用しない。
- RehearsalのLifecycleはStatusで管理する。
- RehearsalAttendanceはRehearsalの子Entityである。
- RehearsalAttendanceを独立Domainとして管理しない。
- RehearsalAttendanceは予定段階から保持する。
- CONFIRMEDになってもRehearsalAttendanceを削除しない。
- ACTIVEになってもRehearsalAttendanceを削除しない。
- 同じRehearsalAttendanceのStatusを予定状態から実績状態へ変更する。
- Rehearsalごとに参加予定者を管理する。
- Production Participant全員がすべてのRehearsalへ参加するとは限らない。
- Participant TypeとAttendance Statusを分離する。
- Rehearsal管理権限はRole / Permissionで管理する。
- TimetableはRehearsal内の詳細な進行を管理する。
- Google Calendar EventはRehearsalの正本ではない。
- RehearsalをStageArt側の正本とする。
- Calendar登録対象とRehearsalAttendanceを同一視しない。
- CANCELLEDとなったRehearsalを削除しない。
- COMPLETEDとなったRehearsalを履歴として保持する。
- RehearsalAttendanceを履歴として保持する。
- Historyは独立Domainとして管理する。
- Historyは各DomainのFactから生成・参照できる。
- Blueprintを唯一の設計基準とする。