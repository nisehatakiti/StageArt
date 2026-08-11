# StageArt Blueprint

# Domain Model : Production

Version : 4.0

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

HistoryはProductionに所属する子Entityではない。

HistoryはStageArt上で発生した活動Factから
自動生成される独立Domainである。

ProductionはHistoryの生成元となる
Business Factを提供する。

例えば、

- Productionへの参加
- Performanceでの観劇
- その他Productionに関連する活動

などがHistory生成の対象となる。

HistoryはProductionへ直接埋め込まない。

HistoryはHistory Domainとして管理する。

ProductionからHistoryを直接作成・編集・削除しない。

---

# History Relationship

ProductionとHistoryの関係は、
所有関係ではなく関連関係として扱う。

基本構造：

Production
  ↓
Activity Fact
  ↓
Domain Event
  ↓
History

例えば、

Participant
  ↓
ParticipantAdded
  ↓
Participation History

Check In
  ↓
CheckInCompleted
  ↓
Audience History

となる。

Historyの具体的な管理ルールは
History Domainで定義する。

---

# Participant

Productionには複数のParticipantを登録できる。

Participantは、
SubjectがProductionへ参加するというFactを表す。

SubjectはPersonまたはOrganizationを参照できる。

基本構造：

Production
  ↓
Participant
  ↓
Subject
  ├── Person
  └── Organization

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

# Participant and History

ParticipantがProductionへの参加Factを表す。

Participantが追加された場合、

ParticipantAdded
  ↓
History

としてParticipation Historyを生成できる。

HistoryのSubjectは、
ParticipantのSubjectを基準とする。

ParticipantRemovedによって、
過去に生成されたHistoryを削除しない。

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

基本構造：

Production
  ↓
Performance
  ↓
Reservation

Performanceの詳細な管理ルールは
Performance Domainで定義する。

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

Reservationの詳細な管理ルールは
Reservation Domainで定義する。

---

# Audience

一般観客は、
StageArtのInternal Portalへ参加する必要はない。

一般観客について、
Personを必ず作成する必要はない。

ただし、

StageArtでユーザー登録を行い、
チケットを購入・予約したPersonは、
自身の観劇履歴を確認できる。

登録ユーザーの観劇履歴は、
History Domainで管理する。

---

# Audience History

Audience Historyは、
Reservationそのものから生成しない。

予約しただけでは、
観劇履歴として扱わない。

Check Inが完了した時点で、
観劇実績として扱う。

基本Flow：

Reservation
  ↓
Issued Ticket
  ↓
Check In
  ↓
CheckInCompleted
  ↓
Audience History

Audience HistoryのSubjectは、
ReservationのBookerを基本とする。

HandledParticipantは、
Audience HistoryのSubjectにはならない。

CreatedByは、
Audience HistoryのSubjectにはならない。

UpdatedByは、
Audience HistoryのSubjectにはならない。

---

# Check In and Accounting

Check Inが完了すると、
CheckInCompletedが発行される。

CheckInCompletedは、
観劇実績の確定だけでなく、
チケット売上を会計へ連携する契機となる。

基本Flow：

Reservation
  ↓
Issued Ticket
  ↓
Check In
  ↓
CheckInCompleted
  ├── History
  │     └── Audience History
  │
  └── Accounting
        └── Journal Entry

Production Domainは、
仕訳そのものを管理しない。

Accounting Domainが、
CheckInCompletedなどのBusiness Factを受け取り、
必要なJournal Entryを生成する。

---

# Ticket Revenue

チケット売上は、
チケットを予約した時点では
正式な会計上の売上として確定しない。

Check Inが完了し、
CheckInCompletedが発生した時点で、
チケット売上を会計へ連携する。

基本Flow：

Ticket
  ↓
Reservation
  ↓
Issued Ticket
  ↓
Check In
  ↓
CheckInCompleted
  ↓
Ticket Revenue
  ↓
Journal Entry

具体的な勘定科目やDebit / Creditの処理は、
Accounting Domainで定義する。

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

Google Calendar連携の詳細は
Rehearsal Domainで定義する。

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

Production Actualは、
Production単位の予実管理を目的とする。

正式なOrganization会計の仕訳は、
Accounting Domainで管理する。

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
Productionに関連する会計Factを
Organization Accountingへ連携する。

CheckInCompletedによって確定した
チケット売上についても、
Accounting Domainへ連携する。

Production自身は、
Journal Entryを直接管理しない。

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

Announcementの詳細な管理ルールは
Announcement Domainで定義する。

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

PrimaryManagerは、
Organization Roleとは別の概念である。

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
- 公演日時
- 会場
- 出演者
- スタッフ
- チケット情報
- 公演画像
- その他公開情報

などを含めることができる。

公開情報は、
Production Public Pageから参照できる。

---

# Production Public Page

Production Public Pageは、
一般観客向けにProductionを公開するための
Presentation / Public Resourceである。

Production Public Pageは、
Productionそのものとは別の表示モデルとして扱う。

Productionの正本情報はProduction Domainが保持する。

---

# Production Status

Productionの公開状態と
Productionそのものの存在を混同しない。

ProductionがCLOSEDまたはARCHIVEDになっても、
過去のProduction Factを削除しない。

過去のProductionは、
必要に応じて参照可能な状態を維持する。

---

# Archive

Productionは、
公演終了後も削除しない。

公演終了後は、
必要に応じてARCHIVEDとして管理する。

ProductionをArchiveしても、

- Participant
- Performance
- Reservation
- Issued Ticket
- Check In
- Budget
- Production Actual
- History

などの過去Factを削除しない。

---

# Domain Events

Productionに関連する主なDomain Event：

- ProductionCreated
- ProductionUpdated
- ProductionPublished
- ProductionClosed
- ProductionArchived

Productionに関連するBusiness Event：

Participant Domain：

- ParticipantAdded
- ParticipantUpdated
- ParticipantRemoved

Check In Domain：

- CheckInCompleted

これらのEventを契機として、
History DomainやAccounting Domainなどが
必要な処理を行う。

Production Domain自身が、
他DomainのFactを直接生成・編集しない。

---

# Business Rules

Productionは必ず一つのProjectに所属する。

ProductionはProductionIdによって識別する。

ProductionIdは変更しない。

Production Titleは識別子ではない。

CategoryはProductionの属性である。

GenreはProductionの属性である。

CategoryとGenreは別の属性として管理する。

Productionには複数のParticipantを登録できる。

ParticipantのSubjectはPersonまたはOrganizationを参照できる。

Participant TypeはProductionへの参加区分を表す。

Organization RoleとParticipant Typeを混同しない。

Productionは複数のPerformanceを持つことができる。

ReservationはPerformance単位で管理する。

ProductionごとにTicket Type / Price Masterを管理する。

RehearsalはProductionに関連付ける。

RehearsalはRehearsal Candidateから作成できる。

RehearsalはCandidateを経由せず直接作成できる。

TimetableはProductionに関連付ける。

Productionには複数のBudgetを作成できる。

BudgetはProductionの複数の計画案を管理する。

Production ActualはProduction単位の実績管理を行う。

正式な会計仕訳はAccounting Domainで管理する。

CheckInCompletedはチケット売上をAccounting Domainへ連携する契機となる。

Production自身はJournal Entryを管理しない。

HistoryはProductionの子Entityではない。

Historyは独立Domainとして管理する。

ProductionはHistory生成の元となるFactを提供する。

ParticipantAddedを契機としてParticipation Historyを生成できる。

CheckInCompletedを契機としてAudience Historyを生成する。

Audience HistoryのSubjectはReservation.Bookerを基本とする。

HandledParticipantはAudience HistoryのSubjectにならない。

CreatedByはAudience HistoryのSubjectにならない。

UpdatedByはAudience HistoryのSubjectにならない。

ReservationCreatedだけではAudience Historyを生成しない。

ReservationUpdatedだけではAudience Historyを生成しない。

ReservationCancelledだけではAudience Historyを生成しない。

Social Postは投稿画面を提供する。

Social Postの投稿内容をStageArtの正本として管理しない。

SeatsはVersion 1.0では実装しない。

PrimaryManagerはProductionに対する全管理権限を持つ。

ProductionDelegateはProduction単位の権限委任を表す。

Organization RoleとProduction Delegateを混同しない。

Productionは公演終了後も削除せずArchiveできる。

ProductionのArchiveによって過去Factを削除しない。

---

# Design Decisions

Productionは、
StageArtにおける具体的な公演単位を表す。

ProductionはProjectに所属し、
ProjectはOrganizationに所属する。

基本構造：

Organization
  ↓
Project
  ↓
Production

CategoryとGenreはProduction自身の属性として保持する。

Productionへの参加はParticipantで管理する。

ParticipantはSubjectを参照し、
SubjectはPersonまたはOrganizationとなることができる。

Productionの個別公演回はPerformanceで管理する。

チケット予約はPerformance単位で管理する。

TicketはProductionごとの
Ticket Type / Price Masterとして管理する。

稽古はProductionに関連付ける。

BudgetはProduction単位で複数作成でき、
複数の計画案を比較できる。

Production Actualは公演単位の予実管理を行う。

正式な会計仕訳はAccounting Domainで管理する。

CheckInCompletedによって、
チケット売上をAccounting Domainへ連携する。

HistoryはProductionの子Entityではなく、
独立Domainとして管理する。

StageArt上の活動Factから生成されるHistoryと、
本人がProfileへ入力するHistoricalActivityは
別の概念として扱う。

ProductionからHistoryを直接作成・編集しない。

一般観客はPersonを必須とせず、
StageArtユーザーとして登録された観客は
自身のAudience Historyを参照できる。

Social Postは投稿画面を提供するが、
投稿内容そのものをStageArtの正本として管理しない。

Seatsは将来実装する。

PrimaryManagerはProductionに対する全管理権限を持つ。

ProductionDelegateは、
Production単位で管理権限を委任する。

Organization RoleはOrganization Context、
Production DelegateはProduction Contextで判定する。

---

# Design Principles

- Productionは具体的な公演を表す。
- ProductionはProjectに所属する。
- ProjectはOrganizationに所属する。
- CategoryはProductionの属性である。
- GenreはProductionの属性である。
- CategoryとGenreを分離する。
- ParticipantはProductionへの参加Factを表す。
- ParticipantのSubjectはPersonまたはOrganizationである。
- Participant TypeとOrganization Roleを分離する。
- Productionは複数のPerformanceを持つ。
- ReservationはPerformance単位で管理する。
- Ticket Type / PriceはProduction単位のMasterとして管理する。
- RehearsalはProductionに関連付ける。
- RehearsalはCandidateから作成できる。
- RehearsalはCandidateを経由せず直接作成できる。
- TimetableはProductionに関連付ける。
- BudgetはProduction単位で複数作成できる。
- Production Actualは公演単位の実績を管理する。
- Organization AccountingとProduction予実管理を分離する。
- CheckInCompletedをチケット売上のAccounting連携契機とする。
- Production自身はJournal Entryを管理しない。
- HistoryはProductionの子Entityではない。
- Historyは独立Domainとして管理する。
- ProductionはHistory生成の元となるFactを提供する。
- ParticipantAddedからParticipation Historyを生成できる。
- CheckInCompletedからAudience Historyを生成する。
- Audience HistoryのSubjectはReservation.Bookerである。
- HandledParticipantはAudience HistoryのSubjectにならない。
- CreatedByはAudience HistoryのSubjectにならない。
- UpdatedByはAudience HistoryのSubjectにならない。
- 予約と観劇実績を分離する。
- Social Postは投稿画面のみを提供する。
- Social Postの投稿内容をStageArtの正本として管理しない。
- Seatsは将来実装する。
- PrimaryManagerはProduction全権限を持つ。
- ProductionDelegateはProduction単位の権限委任を表す。
- Organization RoleとProduction Delegateを分離する。
- Production終了後もProductionを削除しない。
- Archiveによって過去Factを削除しない。
- Blueprintを唯一の設計基準とする。
