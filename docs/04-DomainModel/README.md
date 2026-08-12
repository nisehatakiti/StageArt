# StageArt Blueprint

# 04 - Domain Model

Version : 4.0

---

## Purpose

Domain Modelは、
StageArtが管理する「事実」と「業務上の概念」を定義する。

Domain ModelはUIやDatabase Schemaから独立して設計する。

StageArtにおけるBusiness Flowは、
Domain Modelを操作することで実現する。

Domain Modelは、
StageArtにおける業務上の責務、
Entity間の関係、
Lifecycle、
Business Rule、
Authorization、
Domain Eventなどを定義する。

個々のDomainの詳細仕様は、
それぞれのDomain Modelドキュメントで定義する。

---

# 1. Domain Architecture

StageArtのDomainは、
以下の主要な領域に分ける。

## Identity Domain

Authentication IdentityとBusiness Identityを分離して管理する。

- UserAccount
- External Identity
- Person
- Profile
- HistoricalActivity

---

## Organization Domain

団体と所属関係、
Organization Scopeの権限を管理する。

- Organization
- Membership
- Role
- Organization Invitation
- Organization Membership Request

---

## Project / Production Domain

団体が行う活動・制作と、
具体的な公演・活動を管理する。

- Project
- Production
- Participant
- Subject
- ProductionDelegate
- PrimaryManager

---

## Rehearsal Domain

稽古日程の調整、
確定した稽古、
参加確認、
日別進行を管理する。

- Rehearsal Candidate
- Rehearsal Availability
- Rehearsal
- Rehearsal Attendance
- Timetable
- Timetable Item

---

## Ticket Domain

公演、
チケット、
予約、
発券、
受付を管理する。

- Ticket
- Ticket Type
- Ticket Price
- Performance
- Reservation
- Issued Ticket
- Check In
- QRTicket

---

## Communication Domain

公演・活動関係者への連絡を管理する。

- Announcement
- Announcement Recipient
- Announcement Delivery

---

## Document Domain

公演・活動に関するファイルと、
外部ストレージとの連携を管理する。

- Document
- Document Share
- External Connection
- External Storage Reference

---

## Promotion Domain

団体・公演の公開情報、
SNS連携、
検索・分類情報を管理する。

- Organization Public Profile
- Production Public Page
- Social Post
- Social Post Reference
- Category
- Genre
- Tag

---

## Accounting Domain

団体会計、
公演予算、
実績、
予実、
公演単位の収支を管理する。

- Accounting Period
- Account
- Journal Entry
- Journal Entry Line
- Budget
- Budget Item
- Production Actual
- Budget vs Actual
- Production Settlement

---

## Equipment Domain

団体が保有・管理する備品を管理する。

- Equipment
- Equipment History

Equipmentは、
資産価値を管理するためのDomainではない。

---

## Regulation Domain

団体規約をVersion管理する。

- Regulation
- Regulation Version

---

## Survey Domain

公演終了後のアンケートと、
公開可能な感想を管理する。

- Survey
- Survey Response
- Public Testimonial

---

# 2. Core Domain Structure

StageArtの基本構造は、

Organization
↓
Project
↓
Production

とする。

Organizationは、
StageArtにおけるTenantである。

Projectは、
Organizationが行う活動・制作の内部単位である。

Productionは、
Projectに所属する具体的な公演・活動を表す。

Production関連Domainは、
Productionを通じてOrganization Scopeに属する。

---

# 3. Authentication / Business Identity

StageArtでは、

Authentication Identity

と

Business Identity

を分離する。

基本構造：

UserAccount
↓
Person

UserAccountは、
StageArtへのAuthentication Identityを表す。

Personは、
StageArt上のBusiness Identityを表す。

UserAccountとPersonは同一概念ではない。

Personは、
必ずしもUserAccountを持つ必要はない。

---

# 4. UserAccount

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

# 5. External Identity

UserAccountは、
外部Authentication Providerと連携できる。

例：

- Google
- Apple
- Microsoft
- Email / Password
- その他Authentication Provider

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

Provider固有の認証処理やAPI仕様は、
Infrastructure Layerで管理する。

Domain Layerは、
特定Authentication ProviderのAPIへ直接依存しない。

---

# 6. Person

Personは、
StageArt上の個人を表すBusiness Identityである。

PersonはOrganizationとは独立して存在する。

一人のPersonは、
複数のOrganizationに所属できる。

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

Productionへの参加は、
Participantによって表現する。

Productionにおける管理権限は、
ProductionDelegateによってRoleを適用する。

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

Profileは、
StageArt上で発生した活動履歴そのものを保持するDomainではない。

過去の出演歴・活動実績など、
本人が入力する過去実績はHistoricalActivityによって管理する。

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

例えば、
StageArt上のProductionへの参加実績は、
ParticipantなどのFactを正本とする。

HistoricalActivityは、
StageArt外で発生した過去実績など、
本人が登録する履歴情報を対象とする。

---

# 9. Organization

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

Organizationは、
StageArtにおけるTenantである。

Organizationは、
Personとは独立したDomainである。

Organizationは、
Projectを保持する。

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
複数のOrganizationにMembershipを持つことができる。

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

# 11. Role

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

# 12. Role Application

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

# 13. Production Delegate

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

Organization全体のRoleを持つPersonとは別に、
特定Productionだけについて管理権限を持つPersonを表現できる。

---

# 14. Primary Manager

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

# 15. Authorization Structure

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

# 16. Project

Projectは、
Organizationが行う活動・制作の内部単位である。

基本構造：

Organization
↓
Project

ProjectはOrganizationに所属する。

一つのProjectは、
一つ以上のProductionを持つことができる。

Projectは、
利用者が必ずしも意識する必要のないInternal Domainである。

利用者が「公演を作る」などの操作を行った際、
StageArt内部でProjectとProductionの構造を管理する。

---

# 17. Production

Productionは、
具体的な公演・活動を表す。

基本構造：

Project
↓
Production

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

Productionには、
Category、
Genre、
Tagなどの分類情報を付与できる。

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

Categoryは、
Productionの公演形態・活動形態を表す。

例：

- 舞台
- ライブ
- 映画
- 配信

---

## Genre

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

## Tag

Tagは、
検索・分類用の情報を表す。

Tagは必要に応じて、

- Person
- Organization
- Production
- Performance

などに関連付ける。

---

# 19. Participant

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
- PARTNER
- その他Productionで必要な参加区分

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

# 22. Performance

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

# 23. Ticket

Ticketは、
Productionにおけるチケット販売条件を管理する。

基本構造：

Production
↓
Ticket
├── Ticket Type
└── Price

TicketはProduction単位で管理する。

同じTicket Typeでも、
ProductionごとにPriceを変更できる。

例：

一般 → 3,000円
学生 → 2,000円
当日 → 3,500円

Ticketの詳細な販売ルールは、
Ticket Domainで定義する。

---

# 24. Reservation

Reservationは、
観客による予約というFactを表す。

Reservationは、

- Person
- Performance
- Ticket
- Quantity
- Reservation Status

などを参照する。

Reservationは、
チケット販売・受付における予約情報の正本である。

---

# 25. Issued Ticket

Issued Ticketは、
予約成立後に発行される個別チケットを表す。

基本構造：

Reservation
↓
Issued Ticket

QRコードなどのTicket Artifactは、
Issued Ticketを元に生成する。

Issued Ticketは、
実際に来場者が利用するチケットを表す。

---

# 26. QRTicket

QRTicketは、
Issued Ticketに関連するQR形式のTicket Artifactを表す。

基本構造：

Issued Ticket
↓
QRTicket

QRTicketそのものを、
予約やチケット販売の正本として扱わない。

正本はIssued Ticketであり、
QRコードはそのArtifactとして扱う。

---

# 27. Check In

Check Inは、
公演当日の来場受付というFactを表す。

基本構造：

Issued Ticket
↓
Check In

Check Inは、

- QR読取
- 予約番号検索
- 氏名検索

などによって実行できる。

受付完了後、
来場Factとして記録する。

---

# 28. Rehearsal

Rehearsalは、
確定した稽古・予定を表す。

Rehearsalは、

1. Rehearsal Candidateから生成する
2. 直接作成する

の両方に対応する。

直接作成の例：

- あらかじめ決まっている稽古
- ゲネプロ
- 本番前確認
- その他の確定予定

---

# 29. Rehearsal Candidate

Rehearsal Candidateは、
稽古日程を調整するための候補日を表す。

基本構造：

Rehearsal Candidate
↓
Rehearsal Availability
↓
Rehearsal

候補日そのものと、
確定したRehearsalは別の概念として扱う。

---

# 30. Rehearsal Availability

Rehearsal Availabilityは、
PersonがRehearsal Candidateに対して回答した情報を表す。

候補日の調整に利用する。

Rehearsal Availabilityは、
確定したRehearsalへの参加確認とは異なる。

---

# 31. Rehearsal Attendance

Rehearsal Attendanceは、
確定したRehearsalへの参加確認を表す。

Rehearsal Candidateへの日程調整回答とは、
別のDomainとして扱う。

基本構造：

Rehearsal
↓
Rehearsal Attendance
↓
Person

---

# 32. Timetable

Timetableは、
稽古・小屋入り・本番等の日別進行を管理する。

Timetableには、

- 時刻
- 内容
- 場所
- 担当
- 対象者
- 備考

などを設定できる。

Timetableは、
Rehearsalそのものとは異なる。

Rehearsalが「予定」を表すのに対し、
Timetableは、
その日の具体的な進行を表す。

---

# 33. Budget

Budgetは、
Productionの予算案を表す。

Budgetは、
公演前の計画を管理する。

一つのProductionに、
複数のBudgetを持つことができる。

Budgetには、
利用者が自由に名称を付ける。

例：

- A会場案
- B会場案
- 一日2公演案

Budgetは、
実際に発生した収入・支出そのものを表さない。

---

# 34. Budget Item

Budget Itemは、
Budget内の費目を表す。

収入・支出を費目ごとに管理する。

Budget Itemは、
Budget上の計画値を保持する。

実績値は、
Production ActualなどのAccounting側のDomainで管理する。

---

# 35. Journal Entry

Journal Entryは、
会計上の一つの仕訳を表す。

Journal Entryは、
実際に発生した会計Factを記録する。

---

# 36. Journal Entry Line

Journal Entry Lineは、
仕訳内の費目ごとの行を表す。

費目ごとにLineを分けて管理する。

貸借区分はFlagで管理する。

is_debit = true

または

is_debit = false

とする。

---

# 37. Production Actual

Production Actualは、
Productionに紐付く実績情報を表す。

実際に発生した収入・支出を管理する。

Production Actualは、
Budgetとは異なり、
実際に発生したFactを扱う。

---

# 38. Budget vs Actual

Budget vs Actualは、
Productionにおける予算と実績を比較する。

基本構造：

Budget
↓
Actual
↓
Variance

Budgetそのものと、
実績そのものを混在させず、
差異を比較情報として扱う。

---

# 39. Production Settlement

Production Settlementは、
Production単位の最終的な収支を表す。

団体全体のAccountingとは別の視点で、
公演単位の収入・支出・損益を確認する。

Production Settlementは、
Productionの活動結果を確認するためのDomainである。

---

# 40. Communication

Communication Domainは、
Production・Organizationなどに関係する人物へ
連絡を行うためのDomainである。

主なDomain：

- Announcement
- Announcement Recipient
- Announcement Delivery

Announcementは、
連絡内容を表す。

Announcement Recipientは、
誰を対象とするかを表す。

Announcement Deliveryは、
実際の送信・配信履歴を表す。

---

# 41. Document

Document Domainは、
公演・活動に関するファイルを管理する。

主なDomain：

- Document
- Document Share
- External Connection
- External Storage Reference

StageArtは、
外部ストレージと連携して実ファイルを管理できる。

StageArt側では、
ファイル情報、
関連付け、
共有情報などを管理する。

---

# 42. External Connection

External Connectionは、
外部Serviceとの接続情報を表す。

例：

- Google Drive
- Google Calendar
- その他External Service

外部Service固有のAPI仕様や認証処理は、
Infrastructure Layerで管理する。

Domain Layerは、
外部ServiceのAPI仕様へ直接依存しない。

---

# 43. Promotion

Promotion Domainは、
OrganizationやProductionの公開情報、
SNS連携などを管理する。

主なDomain：

- Organization Public Profile
- Production Public Page
- Social Post
- Social Post Reference
- Category
- Genre
- Tag

Public Pageは、
内部Domainをそのまま公開するものではない。

公開情報として必要な内容を、
Public Domainとして管理する。

---

# 44. Equipment

Equipmentは、
Organizationが保有・管理する備品を表す。

Equipmentは、
資産価値を管理するためのDomainではない。

主な情報：

- 名称
- 分類
- 保管場所
- 現在の管理者
- 状態
- 備考

状態の例：

- 使用可能
- 貸出中
- 不明
- 廃棄

---

# 45. Equipment History

Equipment Historyは、
Equipmentに関する変更履歴を管理する。

主な履歴：

- 保管場所変更
- 管理者変更
- 状態変更

目的は、

「以前の公演で使った備品が現在どこにあるか」

を追跡できることである。

---

# 46. Regulation

Regulationは、
Organizationの規約を表す。

基本構造：

Organization
↓
Regulation
↓
Regulation Version

---

# 47. Regulation Version

Regulation Versionは、
規約の個別Versionを表す。

規約変更時には、
既存Versionを上書きせず、
新しいVersionを作成する。

これにより、
過去の規約内容を保持できる。

---

# 48. Survey

Surveyは、
Production終了後などに実施するアンケートを表す。

基本構造：

Production
↓
Survey
↓
Survey Response

Survey Responseは、
回答者によるアンケート回答を表す。

アンケート回答は、
原則として公開情報ではない。

---

# 49. Public Testimonial

Public Testimonialは、
Survey Responseなどから、
公開可能な感想・推薦コメントを表す。

Survey Responseそのものを、
そのまま公開するものではない。

公開可能な情報だけを、
Public Testimonialとして扱う。

---

# 50. Fact and Artifact

StageArtでは、
Business FactとArtifactを明確に分離する。

Factとは、
StageArt上で発生した事実を表す。

例：

- Participant
- Reservation
- Check In
- Rehearsal Attendance
- Journal Entry

Artifactとは、
FactやDomain情報をもとに生成される成果物を表す。

例：

- QRTicket
- Public Page
- Document Reference
- Report

Artifactを、
元となるFactの正本として扱わない。

---

# 51. Single Source of Truth

StageArtでは、
同じ情報を複数Domainで重複管理しない。

例えば、

Productionへの参加者情報は、
Participantを正本とする。

予約情報は、
Reservationを正本とする。

発行済みチケットは、
Issued Ticketを正本とする。

来場Factは、
Check Inを正本とする。

実際の会計Factは、
Journal EntryなどAccounting Domainを正本とする。

表示用データやArtifactは、
正本となるDomainから生成する。

---

# 52. Domain Relationship Principles

Domain間の関係は、
責務を明確にして定義する。

基本原則：

- PersonはBusiness Identity
- UserAccountはAuthentication Identity
- MembershipはOrganizationへの所属Fact
- ParticipantはProductionへの参加Fact
- ProductionDelegateはProduction Scopeの権限関係
- RoleはPermission Set
- PerformanceはProduction内の個別公演
- Reservationは予約Fact
- Issued Ticketは発行済みチケット
- Check Inは来場Fact
- Rehearsal Availabilityは候補日への回答
- Rehearsal Attendanceは確定した稽古への参加確認
- Budgetは計画
- Production Actualは実績
- Journal Entryは会計Fact
- HistoricalActivityは本人が登録する過去実績

それぞれのDomainは、
他Domainの責務を代替しない。

---

# 53. Lifecycle Principle

StageArtでは、
DomainのLifecycleを、
UIの状態やDatabaseのFlagだけで表現しない。

Domain自身が、
業務上意味のある状態遷移を管理する。

例えば、

Reservation

→ 予約作成
→ 予約確定
→ キャンセル

Issued Ticket

→ 発行
→ 利用
→ 無効化

Rehearsal

→ 作成
→ 確定
→ 実施
→ 完了

など、
各DomainのLifecycleは、
個別Domain Modelで定義する。

---

# 54. Domain Event

Domain上で重要なFactが発生した場合、
必要に応じてDomain Eventを発行する。

例：

- PersonCreated
- OrganizationCreated
- MembershipCreated
- ProductionCreated
- ParticipantAdded
- ReservationCreated
- ReservationConfirmed
- TicketIssued
- CheckInCompleted
- RehearsalConfirmed
- RehearsalCompleted
- JournalEntryPosted

Domain Eventは、
他Domainの自動更新や外部Service連携に利用できる。

ただし、
Domain EventをBusiness Factそのものの代替として扱わない。

正本は、
発生したBusiness Factを保持するDomainである。

---

# 55. External Integration Principle

StageArtは、
外部Serviceと連携する。

代表例：

- Authentication Provider
- Google Drive
- Google Calendar
- SNS
- Email Service

外部Serviceとの接続は、
Domain ModelとInfrastructureを分離する。

Domain Layerは、
外部Service固有のAPI仕様を知らない。

External ConnectionなどのDomainは、
「外部Serviceとの接続」というBusiness上の概念を表現する。

実際のAPI呼び出し、
Authentication、
Token管理などはInfrastructure Layerが担当する。

---

# 56. Authorization Principle

Authorizationは、
UserAccountではなくPersonを起点として評価する。

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

Participant Typeによって、
管理権限を自動付与しない。

Roleによって、
Permissionを定義する。

Scopeによって、
そのPermissionが有効な範囲を限定する。

---

# 57. Design Decisions

StageArtでは、
以下の設計を基本方針とする。

## AuthenticationとBusiness Identityを分離する

UserAccountとPersonを分離する。

UserAccountはAuthentication Identity。

PersonはBusiness Identity。

---

## OrganizationとPersonを分離する

PersonはOrganizationに直接所属しない。

Membershipによって所属関係を表現する。

---

## ProjectとProductionを分離する

Projectは内部的な活動・制作単位。

Productionは具体的な公演・活動。

---

## ParticipantとAuthorizationを分離する

Participantは、
Productionへの参加Factを表す。

Roleは、
Authorizationを表す。

CASTやSTAFFであること自体に、
管理権限を持たせない。

---

## Organization ScopeとProduction Scopeを分離する

Organization ScopeはMembershipを利用する。

Production ScopeはProductionDelegateを利用する。

同じRole Definitionを、
両方のScopeで利用できる。

---

## DelegateRoleを作らない

Production Scope専用の別Role体系は作らない。

Role Definitionは共通化し、
ProductionDelegateによってScopeを限定する。

---

## RoleAssignmentを作らない

Roleの適用関係を独立Domainとして作成しない。

Organization ScopeではMembership。

Production ScopeではProductionDelegate。

それぞれの関係DomainがRole Applicationを担う。

---

## HistoricalActivityとProduction Factを分離する

本人が登録する過去実績と、
StageArt上で発生した活動Factを分離する。

StageArt上の活動実績は、
ParticipantなどのFactを正本とする。

本人が入力する過去実績は、
HistoricalActivityで管理する。

---

# 58. User Experience Principle

利用者は、
Domain Modelを意識しない。

利用者が行うのは、

- 団体を作る
- メンバーを管理する
- 公演を作る
- キャストを登録する
- 稽古日程を調整する
- チケットを販売する
- 受付する
- 予算を作る
- 会計を記録する
- 関係者へ連絡する

などのBusiness Operationである。

Project、
Production、
Participant、
Reservation、
HistoricalActivityなどの内部Domain構造は、
StageArtが責任を持って管理する。

UIは、
内部Domain構造をそのまま利用者へ露出させない。

---

# 59. Domain Model Documentation

各Domainの詳細仕様は、
個別のDomain Modelドキュメントで定義する。

主なドキュメント：

- Account.md
- UserAccount.md
- Person.md
- Profile.md
- HistoricalActivity.md
- Organization.md
- Membership.md
- Role.md
- Project.md
- Production.md
- Participant.md
- Subject.md
- ProductionDelegate.md
- Performance.md
- Reservation.md
- Ticket.md
- QRTicket.md
- Rehearsal.md
- RehearsalCandidate.md
- RehearsalAvailability.md
- RehearsalAttendance.md
- Timetable.md
- Budget.md
- JournalEntry.md
- ProductionActual.md
- ExternalConnection.md
- Service.md
- Credential.md
- Category.md
- Genre.md
- Tag.md
- History.md
- HistoricalActivity.md

Domain Modelの一覧およびDomain間の上位構造は、
DomainMap.mdで管理する。

---

# 60. DomainMapとの関係

DomainMapは、
StageArt全体のDomain構造を俯瞰するための上位設計である。

DomainMapは、
個々のDomainの詳細仕様を定義しない。

個々のDomainについて、

- Business Rule
- Lifecycle
- Permission
- Domain Event
- Value Object
- 詳細なRelationship

などが必要な場合は、
個別Domain Modelで定義する。

DomainMapと個別Domain Modelの間で、
責務や関係が矛盾しないことを原則とする。

---

# 61. Versioning

Domain Model READMEのVersionは、
Domain全体の構造に重要な変更があった場合に更新する。

個別Domainの変更については、
各Domain ModelのVersionで管理する。

Domain Model全体の構造変更を行う場合は、

1. DomainMapを更新する
2. Domain Model READMEを更新する
3. 関連する個別Domain Modelを更新する
4. Business Flowへの影響を確認する
5. API仕様への影響を確認する

という順序を基本とする。

---

# 62. Design Principle

StageArtのDomain Modelは、

「舞台芸術活動を行う人が、
運営業務に追われることなく、
創作活動に集中できる時間を増やす」

というStageArtの目的を実現するために存在する。

Domain Modelの複雑さは、
利用者に押し付けない。

複雑な業務ルールはDomainが吸収し、
UIは利用者が理解しやすいBusiness Operationとして提供する。

StageArtは、
舞台芸術活動に必要な業務を、
一つのDomain構造の中で一貫して管理する。

---