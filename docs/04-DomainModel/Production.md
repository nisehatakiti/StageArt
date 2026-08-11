# StageArt Blueprint

# Domain Model : Production

Version : 3.0

---

# Purpose

Productionは、
StageArtにおいて観客へ公開される具体的な「公演」を表すDomainである。

ProductionはProjectに所属する。

基本構造は、

Organization
  ↓
Project
  ↓
Production

とする。

Productionは、
公演に関する公開情報および
公演を成立させるための各種Factとの関連を管理する。

---

# Concept

Productionは、
観客が「公演」として認識する単位である。

利用者が「公演を作る」を実行すると、
StageArtは必要に応じてProjectとProductionを生成する。

Projectは制作活動をまとめるInternal Domainであり、
Productionは具体的な公演を表す。

---

# Identity

ProductionはProductionIdによって一意に識別される。

ProductionIdは変更できない。

公演タイトルは識別子ではない。

同じタイトルのProductionが存在しても問題ない。

---

# Relationship

Productionは必ず一つのProjectに所属する。

基本構造：

Organization
  ↓
Project
  ↓
Production

Productionには以下のDomainが関連する。

- PrimaryManager
- ProductionDelegate
- Participant
- Performance
- Ticket
- Reservation
- Rehearsal
- Timetable
- Budget
- Production Actual
- Document
- Announcement
- Survey
- History

Production自身の属性として、

- Category
- Genre
- Title
- Description
- Publication Information

などを保持する。

---

# Category

CategoryはProductionの属性である。

Categoryは、
Productionがどのような舞台芸術・公演カテゴリに属するかを表す。

CategoryはProductionごとに設定する。

---

# Genre

GenreはProductionの属性である。

Genreは、
Productionの作品ジャンルを表す。

例：

- 演劇
- ミュージカル
- ダンス
- コメディ
- サスペンス
- その他

GenreはProductionごとに設定する。

CategoryとGenreは別の属性として管理する。

---

# History

HistoryはProductionにとって必須の関連Domainである。

Productionは、
公演終了後も削除せず、
過去の公演履歴として保持する。

ProductionはHistory生成の基点となる。

Productionに関連するHistoryには、

- 公演履歴
- 出演履歴
- スタッフ履歴
- 観劇履歴
- その他Productionに関連する活動履歴

などが含まれる。

HistoryはProductionに直接埋め込むのではなく、
History Domainとして管理する。

---

# Participant

Productionには複数のParticipantを登録できる。

Participantは、
PersonがProductionへ参加するというFactを表す。

Participant Typeによって、
Productionへの参加区分を表現する。

基本的なParticipant Type：

- キャスト
- スタッフ

Participant TypeはOrganization Roleとは異なる。

Roleは、

「そのOrganizationで何ができるか」

を表す。

Participant Typeは、

「そのProductionにどう関わっているか」

を表す。

---

# Participant Example

同じPersonが、

Organization Role
  管理者

でありながら、

Production A
  Participant Type = キャスト

となることができる。

また、

Production B
  Participant Type = スタッフ

となることもできる。

Organization RoleとParticipant Typeは独立して管理する。

---

# Performance

Productionは複数のPerformanceを持つことができる。

Performanceは、
実際に行われる個別の公演回を表す。

例：

- 8/1 14:00
- 8/1 18:00
- 8/2 13:00

チケット予約はPerformance単位で管理する。

---

# Ticket

TicketはProductionに関連する。

Productionごとに、
TicketTypeとPriceの組み合わせを管理する。

Productionは、
公演ごとのTicket Type / Price Masterを持つ。

例：

- 一般 / 3,000円
- 学生 / 2,000円
- 当日券 / 3,500円

TicketTypeとPriceの組み合わせは、
Productionごとに設定する。

TicketTypeおよびPriceの詳細な管理ルールは、
Ticket Domainで定義する。

---

# Reservation

Reservationは、
観客によるPerformanceへの予約というFactを表す。

基本構造：

Production
  ↓
Performance
  ↓
Reservation

ReservationはProductionから直接予約するものではなく、
Performanceを対象として管理する。

---

# Audience

一般観客は、
StageArtのInternal Portalへ参加する必要はない。

ただし、
StageArtでユーザー登録を行い、
チケットを購入・予約したPersonは、
自身の観劇履歴を確認できる。

一般観客としての予約情報と、
StageArtユーザーとしてのPersonは、
必要に応じて関連付ける。

---

# Rehearsal

RehearsalはProductionに関連付ける。

基本構造：

Project
  ↓
Production
  ↓
Rehearsal

Rehearsalは、
Rehearsal Candidateから作成することも、
単独で直接作成することもできる。

例えば、

- 本番日程
- 特別稽古
- 追加稽古
- 小屋入り後のスケジュール

など、
候補日調整を必要としない日程もRehearsalとして登録できる。

---

# Rehearsal Scheduling

候補日から稽古日を決定する場合の基本Flow：

稽古候補日を提示
  ↓
① 日程調整のための出欠
  ↓
稽古日確定
  ↓
Google Calendar連携
  ↓
② 確定した稽古への参加確認
  ↓
稽古実施

①は日程調整のための出欠である。

②は確定した稽古への参加確認であり、
稽古実施前に行う。

Google Calendarへの連携は、
稽古参加者だけを対象とするものではない。

Google Calendar連携の詳細はRehearsal Domainで定義する。

---

# Timetable

Timetableは、
Productionにおける稽古日程や
小屋入り後のタイムテーブルを管理する。

Timetableは、

- 日別の稽古内容
- 小屋入り後の進行
- 時間
- 内容
- 対象者

などを簡単に作成・共有できるようにする。

TimetableはProductionに関連付ける。

---

# Budget

BudgetはProduction単位で管理する。

一つのProductionに複数のBudgetを作成できる。

Budgetには、
利用者が簡単な名称を付けられる。

例：

- A会場案
- B会場案
- 一日2公演案
- 小劇場案

Budgetは、
単なる一つの予算表ではなく、
Productionに対する複数の計画案を管理するために利用する。

---

# Production Actual

Productionには、
実際に発生した収支を記録する。

BudgetとProduction Actualを比較することで、
公演の予実を確認できる。

基本構造：

Production
  ├── Budget
  └── Production Actual

予実管理では、

- 予算
- 実績
- 差額
- 差異

などを確認できる。

---

# Accounting Relationship

Organizationの正式な会計は、
Organization Accountingとして管理する。

ProductionのBudgetおよびProduction Actualは、
公演単位の予実管理を目的とする。

そのため、

Organization Accounting

と

Production Budget / Production Actual

は責務を分離する。

必要に応じて、
Productionの会計情報をOrganization Accountingへ連携する。

---

# Document

DocumentはProductionに関連付けることができる。

例：

- 台本
- 稽古資料
- 公演資料
- 当日資料
- フライヤー
- その他制作資料

実ファイルはGoogle Driveなどの外部ストレージへ保存する。

StageArtは、
ファイルそのものを正本として保持するのではなく、

- ファイル名
- ファイル種別
- 外部ファイル識別子
- 外部URL
- Productionとの関連
- 共有対象

などを管理する。

---

# File Sharing

Productionでは、
キャスト・スタッフなどの関係者へ
ファイル情報を共有できる。

共有対象は、

- Organization Role
- Participant Type
- 個別Person

などを必要に応じて利用する。

例えば、

- 稽古管理者
- キャスト
- スタッフ

などを対象として共有できる。

---

# Announcement

Productionに関係する参加者へ、
内部のお知らせを一括送信できる。

Announcementの対象者は、

- Organization Role
- Participant Type
- 個別Person

などから指定できる。

管理者または適切な権限を持つDelegateが、
Productionに関するAnnouncementを作成できる。

---

# Social Post

Productionには、
SNSへの投稿を行うための投稿画面を提供する。

StageArtは、
SNSへの投稿内容そのものを
Productionの正本として管理しない。

投稿画面から外部SNSへ投稿できる構造を想定する。

Social Postの投稿履歴や投稿本文を、
StageArt内で独立したDomainとして管理することは
Version 1.0では行わない。

---

# Seats

SeatsはProductionに関連する将来Domainである。

座席管理については、
将来的に実装する。

Version 1.0では、
Seats Domainを実装しない。

---

# Primary Manager

PrimaryManagerは、
Productionの管理責任者を表す。

PrimaryManagerはPersonを参照する。

一つのProductionは、
一人のPrimaryManagerを持つ。

PrimaryManagerは、
Productionに関する全管理権限を持つ。

PrimaryManagerにはDelegateRoleを設定しない。

---

# Production Delegate

ProductionDelegateは、
Productionに対する管理権限を委任されたPersonを表す。

ProductionDelegateは、
Organization全体のRoleとは別の権限体系である。

ProductionDelegateは、
特定のProductionにのみ適用される。

一つのProductionには、
0人以上のProductionDelegateを設定できる。

---

# Delegate Role

DelegateRoleは、
ProductionDelegateへ付与する権限セットを表す。

DelegateRoleは、
Organization Roleとは異なる。

ProductionDelegateの権限は、
Production単位で適用される。

例：

- 稽古管理
- Participant管理
- 予約管理
- その他Production管理

など。

---

# Authorization

Productionに対する管理権限は、
Production Contextにおいて判定する。

PrimaryManager
  ↓
Production全権限

ProductionDelegate
  ↓
DelegateRole
  ↓
定義された権限

Organization Roleは、
Organization Contextにおける権限として別途判定する。

---

# Organization Role and Production Role

Organization RoleとProductionDelegateを混同しない。

Organization Role：

- 管理者
- 稽古管理者
- 会計管理者

Production Participant Type：

- キャスト
- スタッフ

Production Delegate：

- Production単位の管理権限

これらはそれぞれ異なる責務を持つ。

---

# Publication

Productionは公開状態を持つ。

基本的な状態：

- DRAFT
- PRIVATE
- PUBLISHED
- CLOSED
- ARCHIVED

PUBLISHEDとなったProductionのみ、
一般観客向けに公開する。

---

# Public Information

Productionの公開情報には、

- 公演タイトル
- キャッチコピー
- あらすじ
- 公演画像
- 公演期間
- 公演ステータス
- 公開URL
- Category
- Genre
- 出演者・スタッフなどの公開情報

などを含めることができる。

内部管理情報は公開しない。

例えば、

- 管理権限
- 会計情報
- 内部Document
- 内部Announcement
- その他内部情報

などは一般公開しない。

---

# Search

Productionは検索対象となる。

検索条件の例：

- キーワード
- Category
- Genre
- 出演者
- 劇団
- 開催地域
- 開催期間

Productionの公開情報のみを検索対象とする。

内部管理情報は検索結果へ公開しない。

---

# Lifecycle

Productionは以下のLifecycleを持つ。

- DRAFT
- PRIVATE
- PUBLISHED
- CLOSED
- ARCHIVED

Production終了後も削除しない。

過去の公演情報として保持する。

---

# History Generation

Productionに関連するFactから、
Historyを生成・更新する。

例：

Participant
  ↓
出演・スタッフ履歴

Reservation
  ↓
Check In
  ↓
観劇履歴

Production
  ↓
公演履歴

Historyは、
各Factを直接書き換えることで管理しない。

---

# Business Rules

- ProductionはProjectに所属する。
- Productionは具体的な公演を表す。
- Productionは公開情報を管理する。
- CategoryはProductionの属性として管理する。
- GenreはProductionの属性として管理する。
- HistoryはProductionにとって必須の関連Domainである。
- Production終了後も削除しない。
- ParticipantをProductionに関連付ける。
- Participant Typeとしてキャストとスタッフを管理する。
- Participant TypeとOrganization Roleを分離する。
- PerformanceはProductionに所属する。
- ReservationはPerformance単位で管理する。
- TicketTypeとPriceの組み合わせをProductionごとに管理する。
- RehearsalはProductionに関連付ける。
- RehearsalはRehearsal Candidateから作成できる。
- Rehearsalは単独でも作成できる。
- TimetableはProductionに関連付ける。
- BudgetはProduction単位で管理する。
- Productionには複数のBudgetを持つことができる。
- Budgetには利用者が簡単な名称を設定できる。
- Production ActualはProduction単位で管理する。
- BudgetとProduction Actualを比較して予実を確認できる。
- Organization AccountingとProduction予実管理は責務を分離する。
- DocumentはProductionに関連付けられる。
- Documentの実ファイルはGoogle Drive等の外部ストレージで管理できる。
- 情報共有ではOrganization RoleとParticipant Typeを参照できる。
- ProductionにはAnnouncementを作成できる。
- Social Postは投稿画面を提供する。
- Social Postの投稿内容はStageArt内で管理しない。
- Seatsは将来実装する。
- PrimaryManagerはProductionの全管理権限を持つ。
- ProductionDelegateはProduction単位の権限を持つ。
- Organization RoleとProductionDelegateは別の権限体系である。
- PUBLISHEDとなったProductionを一般観客へ公開する。
- 内部管理情報を一般公開しない。
- ProductionはHistory生成の基点となる。

---

# Domain Events

Productionに関する主なDomain Event：

- ProductionCreated
- ProductionUpdated
- ProductionPublished
- ProductionClosed
- ProductionArchived
- PrimaryManagerAssigned
- PrimaryManagerChanged
- ProductionDelegateAssigned
- ProductionDelegateChanged
- ProductionDelegateRemoved

Participant、Performance、Reservation、Rehearsal、Budgetなどの
Eventは各Domainで定義する。

---

# Design Decisions

Productionは、
Projectに所属する具体的な公演を表す。

Productionは公開情報の中心となるDomainである。

CategoryとGenreはProductionの属性として保持する。

Historyは必須の関連Domainとして扱う。

ParticipantはProductionへの参加というFactを表す。

Participant Typeは、

- キャスト
- スタッフ

を基本とする。

Organization Roleとは明確に分離する。

TicketはProductionごとに
TicketTypeとPriceの組み合わせを管理する。

BudgetはProduction単位で管理し、
複数の予算案を保持できる。

Production Actualは実績を表し、
Budgetと比較して予実を確認できる。

RehearsalはProductionに関連付けるが、
Rehearsal Candidateを経由する場合と
直接作成する場合の両方を許可する。

DocumentはGoogle Drive等の外部ストレージと連携する。

Social Postは投稿機能のみを提供し、
投稿内容をStageArt内の正本として管理しない。

Seatsは将来実装する。

Production単位の管理権限は、
PrimaryManagerおよびProductionDelegateによって管理する。

Organization全体の権限はRoleで管理し、
Production単位の権限とは分離する。

---

# Future

将来的に以下へ対応できる構造とする。

- Seats
- 座席指定
- 配信公演
- 公演シリーズ
- 関連作品
- レビュー
- ファンクラブ限定公開
- 高度な公演分析
- 高度な予実分析
- SNS連携の拡張

ただし、
将来機能を実装する場合も、
既存のProduction Domainの責務を不必要に拡張しない。

---

# Design Principles

- ProductionはProjectに所属する。
- Productionは具体的な公演を表す。
- Productionは公開情報の中心となる。
- CategoryとGenreはProductionの属性である。
- Historyは必須の関連Domainである。
- ParticipantはProductionへの参加Factを表す。
- Participant TypeとOrganization Roleを分離する。
- キャストとスタッフはParticipant Typeで管理する。
- PerformanceはProductionに所属する。
- ReservationはPerformance単位で管理する。
- TicketTypeとPriceの組み合わせをProductionごとに管理する。
- RehearsalはProductionに関連付ける。
- Rehearsalは候補日経由でも単独でも作成できる。
- TimetableはProductionに関連付ける。
- BudgetはProduction単位で管理する。
- Production Actualによって実績を管理する。
- BudgetとProduction Actualによって予実を確認する。
- Organization AccountingとProduction予実管理を分離する。
- Documentは外部ストレージと連携できる。
- Google DriveをDocumentの外部保存先として利用できる。
- AnnouncementはProduction関係者へ共有できる。
- Social Postは投稿機能として提供する。
- Social Postの投稿内容をStageArt内で管理しない。
- Seatsは将来実装する。
- PrimaryManagerはProductionの全権限を持つ。
- ProductionDelegateはProduction単位の権限を持つ。
- Organization RoleとProduction単位の権限を分離する。
- Productionは終了後も削除しない。
- ProductionはHistory生成の基点となる。
- Blueprintを唯一の設計基準とする。
